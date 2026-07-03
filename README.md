# Intelligent ERP with Conversational AI Agent

ERP platform for SMEs (inventory, sales, purchases, customers, suppliers) with a
local-LLM conversational agent able to query data and execute authorized business
actions. See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Stack

| Service | Tech | Dev port |
|---|---|---|
| `frontend` | React + TypeScript + Tailwind + shadcn/ui (Vite) | 5173 |
| `backend` | Django REST Framework + PostgreSQL + JWT | 8000 |
| `ai-service` | FastAPI + LangGraph + LangChain | 8001 |
| `ollama` | Ollama (qwen3:32b) | 11434 |
| `db` | PostgreSQL 16 | 5432 |

## Quick start

```bash
cp .env.example .env        # then edit secrets
docker compose up --build
docker compose exec ollama ollama pull qwen3:32b   # first time only
```

- Frontend: http://localhost:5173
- API docs (Swagger): http://localhost:8000/api/docs/
- AI service readiness: http://localhost:8001/ready

## AI agent

The assistant (sidebar → AI Assistant) is a LangGraph ReAct agent running on a
local Ollama model. Its tools call this same REST API with the logged-in
user's JWT, so RBAC applies to the agent exactly as to the UI. Write actions
(create customer/sale, update stock…) pause for explicit user approval in the
chat, and every executed tool call is recorded in the audit log.

## Repository layout

```
backend/     Django project (config/) + business apps (apps/)
ai-service/  FastAPI + LangGraph agent
frontend/    React SPA
docs/        Architecture document and diagrams
```
