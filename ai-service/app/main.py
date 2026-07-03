"""AI service: /chat runs one agent turn; /resume answers a pending
write-action confirmation. Django proxies both and owns persistence."""
import os
import re

import httpx
from fastapi import FastAPI, Header, HTTPException
from langgraph.types import Command
from pydantic import BaseModel

from app.config import BACKEND_BASE_URL, OLLAMA_BASE_URL
from app.graph.agent import get_agent

app = FastAPI(title="ERP AI Service")


class ChatRequest(BaseModel):
    thread_id: str
    message: str


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


def _run(agent, payload, thread_id: str) -> dict:
    config = {"configurable": {"thread_id": thread_id}, "recursion_limit": 40}
    result = agent.invoke(payload, config)
    # langgraph 0.2: pending interrupts live on the state snapshot, not the result.
    pending = None
    snapshot = agent.get_state(config)
    for task in snapshot.tasks:
        if task.interrupts:
            pending = task.interrupts[0].value
            break
    return _serialize(result, pending)


@app.post("/chat")
def chat(body: ChatRequest, authorization: str | None = Header(default=None)):
    token = _token_from(authorization)
    agent = get_agent(token)
    payload = {"messages": [{"role": "user", "content": body.message}]}
    return _run(agent, payload, body.thread_id)


@app.post("/resume")
def resume(body: ResumeRequest, authorization: str | None = Header(default=None)):
    token = _token_from(authorization)
    agent = get_agent(token)
    return _run(agent, Command(resume={"approved": body.approved}), body.thread_id)


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
