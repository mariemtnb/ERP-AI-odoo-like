"""AI service entrypoint. The LangGraph agent is added in Phase 2 (week 7);
for now this exposes health/readiness so the compose topology can be verified."""
import os

import httpx
from fastapi import FastAPI

app = FastAPI(title="ERP AI Service")

OLLAMA_BASE_URL = os.environ.get("OLLAMA_BASE_URL", "http://ollama:11434")
BACKEND_BASE_URL = os.environ.get("BACKEND_BASE_URL", "http://backend:8000")


@app.get("/health")
async def health():
    return {"status": "ok", "service": "ai-service"}


@app.get("/ready")
async def ready():
    """Checks connectivity to Ollama and the backend."""
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
