# Intelligent ERP with Conversational AI Agent

ERP platform for SMEs (inventory, sales, purchases, customers, suppliers) with a
local-LLM conversational agent able to query data and execute authorized business
actions. See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Stack

| Service | Tech | Dev port |
|---|---|---|
| `frontend` | React + TypeScript + Tailwind + shadcn/ui (Vite) | 5173 |
| `backend` | Laravel 12 (PHP 8.4) + PostgreSQL + JWT | 8000 |
| `ai-service` | FastAPI + LangGraph + LangChain | 8001 |
| `ollama` | Ollama (qwen3:32b) | 11434 |
| `db` | PostgreSQL 16 | 5432 |

## Quick start

```bash
cp .env.example .env        # then edit secrets
docker compose up --build
docker compose exec ollama ollama pull qwen3:32b   # first time only
docker compose exec backend php artisan db:seed --class=DemoSeeder --force
```

Demo accounts: `admin@erp.local` / `Admin123!`, `manager@erp.local` /
`Manager123!`, `employee@erp.local` / `Employee123!`.

Backend tests: `docker compose exec backend php artisan test`.

- Frontend: http://localhost:5173
- API reference: `backend/routes/api.php` (no Swagger endpoint yet — the old
  `/api/docs/` came from the Django stack and does not exist)
- AI service readiness: http://localhost:8001/ready

## Tunisia localization

The ERP ships a localization layer for Tunisian business practice:

- **Company fiscal profile** — matricule fiscal and its parts, régime fiscal,
  VAT rate, timbre fiscal, TND with 3 decimals, invoice numbering, payment
  terms (Settings → Localization, admin only).
- **Cheques and effets de commerce** (traites / *kembyelet*) with their full
  lifecycle: received → deposited for collection → cleared, or **bounced**
  with a proper accounting reversal.
- **Installments** — *khlas bel taqsit*: down payment plus scheduled
  échéances, partial settlement, overdue tracking, customer credit view.
- **Banks & reconciliation** — Tunisian banks seeded, RIB/IBAN, CSV statement
  import, assisted matching against payments, cheques and instalments, plus a
  reconciliation report (PDF).

**Nothing legal is hardcoded.** Every posting resolves its account through a
configurable semantic mapping (`account_mappings`), and identifier checks are
advisory warnings by default. The Tunisian chart of accounts shipped here
(411 Clients, 401 Fournisseurs, 5112 Chèques à encaisser…) is a practical
starting point to confirm with your accountant — apply it, edit it, or switch
back from Settings → Localization → Account mapping.

## AI agent

The assistant (sidebar → AI Assistant) is a LangGraph ReAct agent running on a
local Ollama model. Its tools call this same REST API with the logged-in
user's JWT, so RBAC applies to the agent exactly as to the UI. Write actions
(create customer/sale, update stock…) pause for explicit user approval in the
chat, and every executed tool call is recorded in the audit log.

## Production

```bash
docker compose -f docker-compose.prod.yml -p erp-prod up -d --build
```

Single exposed port: **http://localhost:8080** (nginx serves the built React
app and proxies `/api` to the backend — same origin, no CORS). The backend
runs on FrankenPHP with cached config/routes and `APP_DEBUG=false`; db,
ollama and the AI service are internal-only. Secrets (`APP_KEY`, `JWT_SECRET`,
DB password) come from `.env`, never baked into images. The prod stack reuses
the dev Ollama volume so the model is not downloaded twice.

Seed demo data: `docker compose -f docker-compose.prod.yml -p erp-prod exec backend php artisan db:seed --class=DemoSeeder --force`

## Troubleshooting

- **Text lookups fail after changing the Postgres image** (e.g. login says
  "no active account" although the user exists): alpine (musl) and debian
  (glibc) images collate text differently, corrupting btree indexes on text
  columns. Fix: `docker compose exec db psql -U erp -d erp -c "REINDEX DATABASE erp;"`

## Repository layout

```
backend/     Laravel API (app/Http/Controllers, app/Services, app/Models)
ai-service/  FastAPI + LangGraph agent
frontend/    React SPA
docs/        Architecture document and diagrams
```
