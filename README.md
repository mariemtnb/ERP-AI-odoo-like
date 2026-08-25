# Intelligent ERP with Conversational AI Agent

ERP platform for SMEs (inventory, sales, purchases, customers, suppliers) with a
local-LLM conversational agent able to query data and execute authorized business
actions. See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## Stack

| Service | Tech | Dev port |
|---|---|---|
| `frontend` | React + TypeScript + Tailwind (Vite) | 5173 |
| `backend` | Laravel (PHP 8.4+) + PostgreSQL + JWT | 8000 |
| `ai-service` | FastAPI + LangGraph + LangChain | 8001 |
| `ollama` | Ollama (qwen3:32b) | 11434 |
| `db` | PostgreSQL 16 + pgvector | 5432 |

> **PHP 8.4 is required**, not 8.3 — `composer.lock` pins Symfony 8.1, which
> needs ≥ 8.4.1. A clean install on 8.3 fails.

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

## Notifications

The bell in the top bar shows alerts generated from the state of the business:
a cheque or traite due within a week, a bounced instrument, an overdue
instalment, a product low on stock, or a purchase order waiting for approval.
They go to the people who can act (managers/admins) and each person sees only
their own. Clicking one opens the relevant screen.

Time-based alerts are produced by a scan — `php artisan notifications:scan`,
meant to run on a schedule, or `POST /notifications/scan` / the "check now"
button in the bell for managers. (There is no scheduler worker in the
deployment yet, so for now it is run on demand.) Email/SMS aren't built — they
need a provider; the service is shaped so a channel can be added later.

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

## Owner tools & payroll

- **Profit** (sidebar, managers/admins) — revenue, cost of goods, gross and net
  profit, where the money went, and the products that made the best margin.
  Read straight from the accounting, so it always matches the books.
- **Payroll** ("gestion de paie") — employees, monthly pay runs and payslips
  with bonus (prime) and deduction lines, all posted to the ledger. Employees
  can take an **advance on salary** (sickness, family matters); it moves money
  now and is taken back from the next payslip automatically. No tax or
  social-charge rate is hardcoded — those are deduction lines the company sets.
- The **AI assistant** can read the profit figures and give the owner short,
  concrete suggestions, replies in the user's language (Arabic, Derja, French,
  English), and has an optional **auto mode** — off by default; when on it
  approves its own actions, but every one still runs with the user's
  permissions and is audit-logged.
- **Themes** — dark, light and a warm **creme** paper theme; the top-bar button
  cycles between them.

## Administration

Admin → **Administration** (admin only):

- **Companies, branches and business units** — multi-company structure.
- **Fiscal years** — closing a period stops anything being backdated into
  books that have already been reported.
- **Document numbering** — configurable formats (`{PREFIX}-{YYYY}-{SEQ:4}`).
  Counters are reserved under a row lock, so two simultaneous requests cannot
  mint the same invoice number.
- **Roles and permissions** — custom roles, inheritance, per-user grants,
  temporary access that expires, and field-level visibility (e.g. hide cost
  price from employees).
- **Audit trail** — every change with who, when, from where, and the before
  and after values. AI actions are marked as such. Exports to CSV.
- **Modules** — switch accounting, CRM, treasury, AI and the rest on or off
  without a deployment.

Permissions were added *alongside* the original three roles, not instead of
them, so nothing that worked before changed behaviour.

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

## Not production-ready yet

Honest list of what is missing before this could run a real company:

- **No HTTPS.** The production nginx listens on port 80 only.
- **No queue worker**, so scheduled jobs and reminders cannot run.
- **The assistant forgets conversations when the AI service restarts**
  (in-process memory) and does not work with more than one worker.
- **No backup procedure** is written down.
- **No frontend or end-to-end tests** — the backend suite is solid, the UI is
  not covered.
- **No two-factor login.**

## Repository layout

```
backend/     Laravel API
  app/Models/        Eloquent models (plain data)
  app/Services/      all business rules live here
  app/Http/          controllers + middleware
  database/          migrations = the authoritative schema
  tests/Feature/     167 tests, run on every push
ai-service/  FastAPI + LangGraph agent
frontend/    React SPA
diagrams/    PlantUML — start with overview.puml
docs/        Architecture document and demo script
```

## Contributing

CI runs on every push (`.github/workflows/ci.yml`): backend tests on PHP 8.4,
`composer audit`, frontend typecheck and build, and a Python syntax check.
All three must be green.
