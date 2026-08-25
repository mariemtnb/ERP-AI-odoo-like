"""AI service: /chat runs one agent turn; /resume answers a pending
write-action confirmation. Django proxies both and owns persistence."""
import os
import re

import httpx
from fastapi import FastAPI, File, Header, HTTPException, UploadFile
from langgraph.types import Command
from pydantic import BaseModel

from app.config import BACKEND_BASE_URL, OLLAMA_BASE_URL
from app.graph.agent import get_agent

app = FastAPI(title="ERP AI Service")


class ChatRequest(BaseModel):
    thread_id: str
    message: str
    # Auto mode: when true, the assistant approves its own write actions and
    # runs them through without stopping for a confirmation card. Default is
    # OFF — the safe, ask-first behaviour. Even in auto mode every action still
    # goes through the ERP API with the user's token and is written to the
    # audit log, so nothing happens outside the user's own permissions.
    auto_approve: bool = False


class ResumeRequest(BaseModel):
    thread_id: str
    approved: bool


def _token_from(authorization: str | None) -> str:
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Missing bearer token")
    return authorization.removeprefix("Bearer ")


def _strip_think(text: str) -> str:
    return re.sub(r"<think>.*?</think>", "", text, flags=re.DOTALL).strip()


def _serialize(result: dict, pending) -> dict:
    """Convert the final graph state into the API response."""
    # Pending interrupt → ask the user to confirm the write action.
    if pending is not None:
        return {"type": "confirmation_required", "action": pending}

    messages = result["messages"]
    reply = _strip_think(messages[-1].content) if messages else ""
    # Only report tool calls from the current turn (after the last human message).
    turn_start = 0
    for i, m in enumerate(messages):
        if m.__class__.__name__ == "HumanMessage":
            turn_start = i
    tool_calls = []
    for m in messages[turn_start:]:
        for tc in getattr(m, "tool_calls", None) or []:
            tool_calls.append({"name": tc["name"], "args": tc["args"]})
    return {"type": "message", "reply": reply, "tool_calls": tool_calls}


def _pending_interrupt(agent, config):
    """The value of the first pending interrupt, or None."""
    for task in agent.get_state(config).tasks:
        if task.interrupts:
            return task.interrupts[0].value
    return None


def _run(agent, payload, thread_id: str, auto_approve: bool = False) -> dict:
    config = {"configurable": {"thread_id": thread_id}, "recursion_limit": 40}
    result = agent.invoke(payload, config)
    # langgraph 0.2: pending interrupts live on the state snapshot, not the result.
    pending = _pending_interrupt(agent, config)

    # Auto mode: keep approving and resuming until the graph has nothing left
    # to confirm. The bound is a safety net against a tool that loops.
    if auto_approve:
        for _ in range(20):
            if pending is None:
                break
            result = agent.invoke(Command(resume={"approved": True}), config)
            pending = _pending_interrupt(agent, config)

    return _serialize(result, pending)


@app.post("/chat")
def chat(body: ChatRequest, authorization: str | None = Header(default=None)):
    token = _token_from(authorization)
    agent = get_agent(token)
    payload = {"messages": [{"role": "user", "content": body.message}]}
    return _run(agent, payload, body.thread_id, auto_approve=body.auto_approve)


@app.post("/resume")
def resume(body: ResumeRequest, authorization: str | None = Header(default=None)):
    token = _token_from(authorization)
    agent = get_agent(token)
    return _run(agent, Command(resume={"approved": body.approved}), body.thread_id)


class EmbedRequest(BaseModel):
    texts: list[str]


@app.post("/embed")
def embed(body: EmbedRequest, authorization: str | None = Header(default=None)):
    """Embeddings for RAG (nomic-embed-text, 768 dims), local via Ollama."""
    _token_from(authorization)
    if not body.texts or len(body.texts) > 64:
        raise HTTPException(status_code=422, detail="1–64 texts per request.")
    r = httpx.post(
        f"{OLLAMA_BASE_URL}/api/embed",
        json={"model": os.environ.get("OLLAMA_EMBED_MODEL", "nomic-embed-text"), "input": body.texts},
        timeout=120,
    )
    r.raise_for_status()
    return {"embeddings": r.json()["embeddings"]}


@app.post("/extract-invoice")
async def extract_invoice_endpoint(
    file: UploadFile = File(...),
    authorization: str | None = Header(default=None),
):
    _token_from(authorization)  # only authenticated ERP users may use OCR
    from app.ocr import extract_invoice

    if file.content_type not in ("image/png", "image/jpeg", "image/webp"):
        raise HTTPException(status_code=422, detail="Send a PNG/JPEG/WebP image of the invoice.")
    data = await file.read()
    if len(data) > 10 * 1024 * 1024:
        raise HTTPException(status_code=413, detail="Image too large (max 10 MB).")
    return extract_invoice(data)


@app.get("/health")
async def health():
    return {"status": "ok", "service": "ai-service"}


@app.get("/ready")
async def ready():
    result = {"ollama": False, "backend": False, "model": os.environ.get("OLLAMA_MODEL")}
    async with httpx.AsyncClient(timeout=5) as client:
        try:
            r = await client.get(f"{OLLAMA_BASE_URL}/api/tags")
            result["ollama"] = r.status_code == 200
            result["models_pulled"] = [m["name"] for m in r.json().get("models", [])]
        except httpx.HTTPError:
            pass
        try:
            r = await client.get(f"{BACKEND_BASE_URL}/health/")
            result["backend"] = r.status_code == 200
        except httpx.HTTPError:
            pass
    return result
