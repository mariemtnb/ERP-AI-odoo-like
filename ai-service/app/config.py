import os

OLLAMA_BASE_URL = os.environ.get("OLLAMA_BASE_URL", "http://ollama:11434")
OLLAMA_MODEL = os.environ.get("OLLAMA_MODEL", "qwen3:14b")
BACKEND_BASE_URL = os.environ.get("BACKEND_BASE_URL", "http://backend:8000")
API = f"{BACKEND_BASE_URL}/api/v1"
