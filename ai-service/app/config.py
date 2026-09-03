import os

OLLAMA_BASE_URL = os.environ.get("OLLAMA_BASE_URL", "http://ollama:11434")
OLLAMA_MODEL = os.environ.get("OLLAMA_MODEL", "qwen3:14b")
BACKEND_BASE_URL = os.environ.get("BACKEND_BASE_URL", "http://backend:8000")
API = f"{BACKEND_BASE_URL}/api/v1"

# Hard caps on how much work one request can trigger (the local-model
# equivalent of a spend cap): the maximum reasoning/tool super-steps per
# invocation, and the maximum auto-approve resume loops in auto mode.
AGENT_RECURSION_LIMIT = int(os.environ.get("AGENT_RECURSION_LIMIT", "40"))
AGENT_AUTO_APPROVE_MAX = int(os.environ.get("AGENT_AUTO_APPROVE_MAX", "20"))
