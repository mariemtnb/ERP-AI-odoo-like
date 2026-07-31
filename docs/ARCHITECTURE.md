# Software Architecture Document

**Project:** Intelligent ERP with Conversational AI Agent
**Author:** Mariem Tanabene (Internship Project)
**Version:** 1.3 — Sections 1–10 rewritten for the Laravel stack (they still
described Django long after the port), plus the Tunisia localization layer
(§11) and the administration layer (§12).
**Date:** 2026-07-31

> **History:** v1.1 moved the backend from Django REST Framework to Laravel,
> keeping the REST contract, routes and RBAC matrix identical. JWT via
> php-open-source-saver/jwt-auth, PDFs via dompdf; the stock ledger and
> document lifecycles were preserved 1:1.

---

## 1. Requirements Analysis

### 1.1 Problem Statement
SMEs manage operations across fragmented tools (Excel, paper, email), causing data-entry errors, wasted time, and poor visibility. We will build a centralized ERP covering inventory, sales, purchases, customers, and suppliers, augmented by a conversational AI agent that can query data and execute **authorized** business actions via natural language.

### 1.2 Stakeholders
| Stakeholder | Interest |
|---|---|
| Administrator | Full system control, user management, configuration |
| Manager (Gestionnaire) | Operations: products, stock, purchases, sales, reports |
| Employee | Day-to-day data entry and consultation within permissions |
| Internship supervisor | Professional-quality, documented, deployable system |

### 1.3 Functional Requirements (FR)

**FR-1 Authentication & Users**
- FR-1.1 Login with email/password, JWT access + refresh tokens
- FR-1.2 Role-based access control (Admin, Manager, Employee)
- FR-1.3 Admin can create/deactivate accounts and assign roles
- FR-1.4 Password change; token refresh & logout (refresh-token blacklist)

**FR-2 Products & Categories**
- FR-2.1 CRUD products (name, SKU, category, cost price, sale price, unit, min-stock threshold, image optional)
- FR-2.2 CRUD categories (hierarchical optional; flat for v1)
- FR-2.3 Soft delete (products referenced by sales/purchases are never hard-deleted)

**FR-3 Inventory**
- FR-3.1 Stock movements: IN, OUT, ADJUSTMENT — every quantity change is a movement record (append-only ledger)
- FR-3.2 Current stock = derived + cached on product
- FR-3.3 Low-stock alerts when quantity ≤ threshold
- FR-3.4 Full movement history with user, reason, timestamp, source document

**FR-4 Customers / FR-5 Suppliers**
- CRUD, contact details, per-entity sales/purchase history

**FR-6 Purchases**
- FR-6.1 Purchase order lifecycle: `draft → confirmed → received → cancelled`
- FR-6.2 Receiving goods creates stock-IN movements automatically

**FR-7 Sales**
- FR-7.1 Sale lifecycle: `draft → confirmed → cancelled`; confirmation creates stock-OUT movements and validates availability
- FR-7.2 Invoice generation (PDF)

**FR-8 Dashboard & Reports**
- Revenue, sales count, purchases count, top products, low-stock list, date-range filters
- Sales / purchases / stock reports with PDF export

**FR-9 AI Agent**
- FR-9.1 Chat interface with conversation history
- FR-9.2 Agent answers questions by calling read tools (search, stats, reports)
- FR-9.3 Agent executes write actions (create customer, update stock, create sale…) **only** through predefined backend tools, enforcing the calling user's permissions
- FR-9.4 Destructive/write actions require an explicit confirmation step in chat
- FR-9.5 Every agent action is audit-logged

### 1.4 Non-Functional Requirements (NFR)
| NFR | Target |
|---|---|
| Security | JWT (short-lived access, rotating refresh), RBAC on every endpoint, agent inherits user permissions, audit log, input validation, OWASP top-10 hygiene |
| Performance | API p95 < 300 ms (excluding LLM); pagination everywhere; DB indexes on FKs and search fields |
| Reliability | Stock changes are atomic DB transactions; append-only movement ledger enables reconciliation |
| Maintainability | All business rules in a service layer, typed frontend, meaningful test coverage on the rules that matter |
| Portability | Fully dockerized; single `docker compose up` for dev |
| Usability | Responsive UI, French/English-ready labels, optimistic loading states |
| Privacy | LLM runs locally via Ollama — no business data leaves the machine |

---

## 2. Use Cases

**Actors:** Admin, Manager, Employee, AI Agent (secondary actor acting *on behalf of* a user).

Key use cases (UC):
- UC-01 Authenticate (all) — includes token refresh
- UC-02 Manage users & roles (Admin)
- UC-03 Manage products/categories (Admin, Manager)
- UC-04 Record stock movement (Admin, Manager; Employee = view)
- UC-05 Manage customers/suppliers (Admin, Manager, Employee-create/view)
- UC-06 Create & receive purchase order (Admin, Manager)
- UC-07 Create & confirm sale (Admin, Manager, Employee)
- UC-08 View dashboard (all, scoped)
- UC-09 Generate/export report (Admin, Manager)
- UC-10 Chat with AI agent (all) — agent tool calls are constrained by the user's own permissions
- UC-11 Confirm agent-proposed action (all) — extends UC-10

Textual UML use-case description example (UC-07 Confirm sale):
- *Precondition:* user authenticated with `sales.add_sale`; sale in `draft` with ≥1 line.
- *Main flow:* validate stock per line → begin transaction → create OUT movements → decrement cached stock → set status `confirmed` → commit.
- *Alternative:* insufficient stock → 409 with per-line shortages, no changes.

---

## 3. User Roles & Permission Matrix

| Capability | Admin | Manager | Employee |
|---|---|---|---|
| Users & roles | ✔ | ✖ | ✖ |
| Products/categories CRUD | ✔ | ✔ | view |
| Stock movements | ✔ | ✔ | view |
| Customers | ✔ | ✔ | create/view |
| Suppliers | ✔ | ✔ | view |
| Purchases | ✔ | ✔ | view |
| Sales | ✔ | ✔ | create/view |
| Dashboard | ✔ | ✔ | limited |
| Reports/PDF | ✔ | ✔ | ✖ |
| AI agent | ✔ (all tools) | ✔ (scoped) | ✔ (read + create customer/sale) |

Implementation: the `role:` middleware gates route groups in `routes/api.php`, backed by the permission engine in §12. The AI service passes the user's JWT to the backend, so **the backend remains the single enforcement point** for people and the agent alike.

---

## 4. Database Design (PostgreSQL)

Conventions: UUID or BigAuto PKs (BigAuto chosen — simpler, fine for single-DB), `created_at`/`updated_at` on all tables, money as `DECIMAL(12,2)`, quantities as `DECIMAL(12,3)`.

### Entities
```
users(id, email UNIQUE, password_hash, first_name, last_name, role FK→groups, is_active, ...)

categories(id, name UNIQUE, description)

products(id, sku UNIQUE, name, category_id FK, description,
         cost_price, sale_price, unit, quantity_in_stock,  -- cached
         min_stock_level, is_active, created_at, updated_at)
  INDEX(name), INDEX(category_id)

stock_movements(id, product_id FK, movement_type ENUM(IN,OUT,ADJUSTMENT),
                quantity, reason, reference_type NULL,      -- 'sale'|'purchase'|'manual'
                reference_id NULL, created_by FK→users, created_at)
  INDEX(product_id, created_at)   -- append-only, never updated

customers(id, name, email, phone, address, is_active, created_at)
suppliers(id, name, email, phone, address, is_active, created_at)

purchase_orders(id, number UNIQUE, supplier_id FK, status ENUM(draft,confirmed,received,cancelled),
                order_date, received_date NULL, total_amount, created_by FK)
purchase_order_lines(id, purchase_order_id FK, product_id FK, quantity, unit_price, subtotal)

sales(id, number UNIQUE, customer_id FK, status ENUM(draft,confirmed,cancelled),
      sale_date, total_amount, created_by FK)
sale_lines(id, sale_id FK, product_id FK, quantity, unit_price, subtotal)

invoices(id, number UNIQUE, sale_id FK 1-1, issued_at, pdf_file)

conversations(id, user_id FK, title, created_at)
messages(id, conversation_id FK, role ENUM(user,assistant,tool), content, tool_calls JSONB NULL, created_at)

audit_log(id, user_id FK, actor ENUM(user,agent), action, entity_type, entity_id,
          payload JSONB, created_at)
```

### Key relationships (textual class diagram)
- Category 1—* Product
- Product 1—* StockMovement / SaleLine / PurchaseOrderLine
- Customer 1—* Sale ; Supplier 1—* PurchaseOrder
- Sale 1—* SaleLine ; Sale 1—1 Invoice (optional)
- PurchaseOrder 1—* PurchaseOrderLine
- User 1—* Conversation 1—* Message

### Critical business rule
`products.quantity_in_stock` is a **cache**; the source of truth is the sum of `stock_movements`. All mutations happen inside `SELECT ... FOR UPDATE` transactions in a service layer (`services/stock.py`) — never in views or serializers.

---

## 5. API Architecture (Laravel)

Style: REST, JSON, versioned under `/api/v1/`. JWT via `php-open-source-saver/jwt-auth`.

```
POST   /api/v1/auth/login            → access + refresh
POST   /api/v1/auth/refresh
POST   /api/v1/auth/logout           (blacklist refresh)
GET    /api/v1/auth/me

/api/v1/users/                       (Admin only)
/api/v1/categories/                  CRUD
/api/v1/products/                    CRUD + ?search= &category= &low_stock=true
/api/v1/stock/movements/             GET list, POST manual movement
/api/v1/customers/  /suppliers/      CRUD + /{id}/history/
/api/v1/purchases/                   CRUD + POST /{id}/confirm/ + POST /{id}/receive/
/api/v1/sales/                       CRUD + POST /{id}/confirm/ + POST /{id}/cancel/
/api/v1/sales/{id}/invoice/          POST generate, GET download PDF
/api/v1/dashboard/stats/             GET ?from=&to=
/api/v1/reports/{sales|purchases|stock}/   GET (+ ?format=pdf)
/api/v1/agent/chat/                  POST {conversation_id?, message} → streamed reply (proxied to AI service)
/api/v1/agent/conversations/         GET history
```

Standards: pagination (`page`/`page_size`) via `Support\DrfPagination`, consistent error envelope `{ "detail": ..., "errors": {...} }`.

> **Not yet built:** there is no OpenAPI/Swagger endpoint. The README used to
> advertise `/api/docs/`; that was inherited from the Django stack and the
> route does not exist. `routes/api.php` is currently the API reference.

---

## 6. System & Folder Structure

Monorepo — one Git repository, three deployable services:

```
erp-ai/
├── docker-compose.yml
├── docker-compose.prod.yml
├── .env.example
├── docs/                         # this document, diagrams, API notes
├── backend/                      # Laravel
│   ├── Dockerfile
│   ├── composer.json
│   ├── artisan
│   ├── bootstrap/app.php         # routing, middleware aliases, exceptions
│   ├── config/                   # database, auth, jwt, cors…
│   ├── database/migrations/      # the authoritative schema
│   ├── routes/api.php            # every endpoint + the RBAC matrix
│   └── app/
│       ├── Models/               # Eloquent models (plain data + toApi())
│       ├── Services/             # ALL business rules live here
│       │                         # Stock, Document, Accounting, Instrument,
│       │                         # Installment, Payment, Reconciliation,
│       │                         # Permission, Audit
│       ├── Http/Controllers/     # validate → call a service → return JSON
│       ├── Http/Middleware/      # role, permission, feature, active-user
│       └── Support/              # AccountMap, LegalValidation, pagination
├── ai-service/                   # FastAPI + LangGraph + LangChain + Ollama
│   ├── Dockerfile
│   ├── requirements.txt
│   └── app/
│       ├── main.py               # /chat, /resume, /embed, /extract-invoice
│       ├── graph/                # LangGraph agent + system prompt
│       └── tools/                # ERP tools → HTTP calls to backend with user JWT
└── frontend/                     # React + TS + Tailwind + shadcn/ui (Vite)
    ├── Dockerfile
    └── src/
        ├── api/                  # axios client, endpoints, interceptors (token refresh)
        ├── components/ui/        # shadcn
        ├── features/             # auth, products, inventory, customers, suppliers,
        │                         # purchases, sales, dashboard, reports, chat
        ├── hooks/  lib/  routes/  types/
        └── App.tsx
```

Why three services (vs. the AI inside the Laravel app): the LLM workload is async-heavy and evolves independently; FastAPI + LangGraph is the idiomatic stack for it; and the separation *forces* the agent to go through the public API with a user JWT — which is exactly the security model we promised ("the agent never touches the database").

---

## 7. AI Architecture

```
User ──chat──▶ Frontend ──POST /agent/chat (JWT)──▶ Laravel (AssistantController)
                                                        │  validates JWT, stores message
                                                        ▼
                                              AI Service (FastAPI)
                                              LangGraph ReAct agent
                                              ├─ LLM: Ollama (qwen3:32b — best local tool-calling)
                                              └─ Tools (LangChain @tool) ──HTTP + user's JWT──▶ Laravel REST API
                                                        │
                                              reply + tool calls back ──▶ user
```

See also `diagrams/sequence-agent.puml` for the human-approval step drawn out.

Design decisions:
1. **Tools = HTTP calls to the existing REST API** with the *end user's* JWT forwarded. RBAC is enforced once, in Laravel. The AI service holds no DB credentials.
2. **LangGraph** graph: `agent → (tool_calls?) → tools → agent → ... → end`, with an interrupt-before-write node: write tools (`create_sale`, `update_stock`, …) pause the graph and return a confirmation card to the UI; the user approves → graph resumes.
3. **Tool registry** (Phase 2): `search_product`, `search_customer`, `search_supplier`, `get_dashboard_statistics`, `get_low_stock`, `generate_sales_report`, `generate_stock_report`, `create_customer`, `update_customer`, `create_supplier`, `create_product`, `update_stock`, `create_purchase_order`, `create_sale`, `generate_invoice`. Each tool = Pydantic schema + one API call — trivially extensible.
4. **Audit:** every tool invocation logged to `audit_log` with `actor=agent`.
5. Model: `qwen3:32b` (top-tier local function calling; hardware is not a constraint); config-swappable via `OLLAMA_MODEL` env var — `qwen3:14b` as a faster fallback if chat latency ever matters more than reasoning depth.

---

## 8. Security Architecture

- **AuthN:** JWT — access 15 min, refresh 7 days with rotation + blacklist; refresh stored in memory/localStorage with axios interceptor auto-refresh (httpOnly cookie is the upgrade path).
- **AuthZ:** route-group middleware (`role:`, `can.perm:`, `feature:`, `active`) plus the permission engine (roles, inheritance, per-user grants, object- and field-level rules). Single enforcement point (backend) for humans *and* the agent.
- **Agent safety:** allow-listed tools only; user-JWT passthrough; human-in-the-loop confirmation for writes; audit log; prompt-injection mitigation (tool outputs treated as data, system prompt constraints, no raw SQL ever).
- **Transport/app:** CORS allow-list, rate limiting (a blanket per-user ceiling plus tighter limits on auth and agent endpoints), Eloquent query builder with bound parameters everywhere, request validation, secrets via env vars (`.env` git-ignored, `.env.example` committed).
- **Not yet done:** HTTPS is *not* configured — the production nginx listens on port 80 only. This is a deployment blocker, not a design choice.
- **Data:** local LLM ⇒ zero data egress; DB volume backups.

---

## 9. Deployment Architecture (Docker)

Dev: `docker compose up` starts 5 containers —
`pgvector/pgvector:pg16` (volume) · `backend` (PHP built-in server) · `ai-service` (FastAPI/uvicorn) · `ollama/ollama` (volume for model weights) · `frontend` (Vite dev server, HMR).

Prod (compose.prod): backend under **FrankenPHP** with cached config and routes, frontend built and served by **nginx**, which also reverse-proxies `/api` → backend; only nginx exposes a port. No queue worker runs yet, so scheduled and background jobs are not available.

---

## 10. Development Roadmap (8 weeks)

| Week | Deliverable | Definition of done |
|---|---|---|
| 1 | This document validated; repo + Docker skeleton; DB schema migrated | `docker compose up` runs all containers |
| 2 | Auth + RBAC (backend & frontend login, protected routes) | Login/refresh/roles tested |
| 3 | Products, categories, inventory (movement ledger + alerts) | Stock service unit-tested |
| 4 | Customers, suppliers + histories | CRUD + history views working |
| 5 | Purchases (receive → stock IN) & sales (confirm → stock OUT) | Transactional flows tested incl. insufficient stock |
| 6 | Dashboard, reports, PDF invoices; UI polish | Demoable ERP without AI |
| 7 | AI service: LangGraph agent, tool registry, chat UI, confirmations | Agent executes ≥10 tools respecting RBAC |
| 8 | Hardening, prod compose, seed data, docs, defense prep | Full demo script runs clean |

Phase 3 (optional, post-week 8): OCR (invoice extraction), RAG over documents, sales forecasting, voice.

---

## 11. Tunisia Localization Layer

Added on top of the generic ERP so the system fits how Tunisian SMEs actually
work: cheques and *effets de commerce* circulate as money, sales are financed
in instalments, and the books are kept per journal against a local chart of
accounts.

### 11.1 Design principle — configuration, not code

**No Tunisian legal or fiscal rule is hardcoded.** Two mechanisms enforce this:

1. **Semantic account mapping** (`account_mappings`). Posting services ask for
   `cheques_receivable`, never for a literal code; `App\Support\AccountMap`
   resolves the key. Re-pointing a key, or applying the Tunisian chart
   wholesale, is a settings change made from the UI.
2. **Advisory legal validation** (`App\Support\LegalValidation`). Checks on RIB
   length, IBAN prefix and tax-identifier shape return *warnings*; the system
   saves anyway. A company that wants hard enforcement flips
   `enforce_legal_validation` on its profile, and the same warnings become
   422s. System behaviour and legal validation are deliberately separate.

The Tunisian chart shipped in the migration (411 Clients, 401 Fournisseurs,
413 Effets à recevoir, 5112 Chèques à encaisser, 532 Banques, 54 Caisse…) is a
**practical default to confirm with the company's accountant**, not an
authoritative statement of Tunisian accounting law. Defaults keep pointing at
the pre-existing generic chart so the localization migration changes no
existing behaviour; the Tunisian chart is opt-in.

### 11.2 Payment instruments (cheques & kembyelet)

Cheques and traites share one table and one state machine — their lifecycle is
identical, only the vocabulary and the resolved accounts differ.

```
incoming:  draft → received → deposited → pending_clearance → cleared
                                       ↘ bounced → deposited | settled
                                          cleared → bounced   (sauf bonne fin)
outgoing:  draft → issued → cleared | bounced → issued | settled
                            cleared → bounced
```

`cleared → bounced` is deliberate: banks credit *sauf bonne fin* and can debit
the money back days later when the instrument is returned. The bounce posting
credits back whichever account is actually holding the money — the bank if it
already cleared, the collection account if it had not — so the treasury
accounts cannot end up overstated.

Every transition posts its own balanced entry and appends an immutable
`instrument_events` row carrying the journal entry it produced, so books and
instrument history cannot drift apart. The core insight the postings encode:

> Receiving a cheque does not settle a debt — it changes its form.
> `Dr Cheques receivable / Cr Accounts receivable`. That is precisely why a
> bounce can put the debt back.

Bouncing reverses whatever was recognised, restores the counterparty's debt
(optionally onto *clients douteux*), expenses the return fee, and reopens any
instalment the instrument was covering.

### 11.3 Installments — "khlas bel taqsit"

A plan reschedules an existing debt, so **creating one posts nothing**; the
receivable already exists from the invoice. Money is recognised when an
instalment is actually paid. Down payment becomes instalment #1 due
immediately; the last instalment absorbs the rounding remainder so the schedule
always sums exactly to the financed amount.

### 11.4 Banking & reconciliation

Statement lines are imported from CSV (comma/semicolon, FR or EN headers,
`d/m/Y` or ISO dates, signed amount or separate débit/crédit columns) or keyed
in; XLSX is parsed client-side and posted as rows, keeping the backend free of
a spreadsheet dependency. Re-importing an overlapping statement is safe —
identical lines are skipped.

Matching **asserts** that a bank line *is* a given payment; it does not post,
because that payment posted when it was recorded. Only `adjustment` matches
(bank charges, interest, unidentified lines) post — to the mapped fees or
suspense account. Matching a deposited cheque runs its `clear` transition,
since the bank line *is* the moment it cleared.

### 11.5 Postings summary

| Event | Entry |
|---|---|
| Cheque/traite received | Dr Instruments receivable / Cr Accounts receivable |
| Deposited for collection | Dr Instruments in collection / Cr Instruments receivable |
| Cleared (incoming) | Dr Bank + Dr Fees / Cr Instruments in collection |
| Bounced (incoming) | Dr Receivable (or Doubtful) / Cr Instruments in collection; fees to Bank |
| Cheque issued to supplier | Dr Accounts payable / Cr Instruments payable |
| Cleared (outgoing) | Dr Instruments payable / Cr Bank |
| Cash/transfer received | Dr Cash or Bank / Cr Receivable |
| Advance received | Dr Cash or Bank / Cr Customer advances |
| Cash deposited to bank | Dr Bank / Cr Cash |
| Instalment paid | as per its payment method (above) |
| Reconciliation adjustment | Dr Fees or Bank / Cr Bank or Suspense |

Instalment plans and bank matches post nothing by themselves — by design.

### 11.6 RBAC

Reads are open to any authenticated user; anything that posts to the ledger
(instrument transitions, payments, matching, instalment settlement) is
manager/admin. Localization settings — fiscal profile, journals, account
mapping, chart template — are **admin only**, since re-pointing a mapping
changes where every future entry lands.

### 11.7 AI agent

Thirteen read tools and eight write tools cover the layer. Writes keep the same
human-in-the-loop interrupt as the rest of the agent. Two prompt rules matter:
the agent must call `explain_journal_entry` rather than reasoning about
accounting from memory, and it must never assert what Tunisian law requires —
it describes what the system is configured to do and defers to the accountant.

---

## 12. Administration Layer (Phase 1)

Enterprise foundations added on top of the business modules: organisation
structure, a permission engine, an audit trail and feature flags.

### 12.1 Additive by construction

The permission engine did **not** replace the existing RBAC. `users.role`
remains the source of truth for the built-in roles, and `EnsureRole` runs its
original `in_array($user->role, $roles)` check *first*. Custom roles are an
extra way **in** — a user holding a role that inherits from `manager` satisfies
`role:manager` — never a way to be locked out. Every pre-existing test passes
unchanged, which is the property that matters.

New routes should prefer the finer-grained `can.perm:sales.confirm`; existing
routes keep their `role:` groups until each module is migrated deliberately.

### 12.2 Permission resolution

First decisive answer wins:

1. `user_permissions` — explicit per-user grant/deny, optionally time-boxed
2. role lineage — the user's role and everything it inherits
3. deny by default

Within a level a **deny beats an allow**, so an inherited grant can be revoked
without unpicking the hierarchy. An unknown permission key is denied, never
treated as an open door.

**Restrictions do not inherit.** Object and field rules resolve against
*directly held* roles only. Inheritance propagates capability upward — an admin
has everything an employee has — but propagating a restriction the same way
would invert the hierarchy, hiding a column from employees *and* from their
managers. Object rules likewise **narrow** an existing permission and can never
widen one: a user who cannot `sales.update` at all is not rescued by an object
grant.

### 12.3 Audit trail

An `Auditable` trait hooks a model's own lifecycle events, so auditing an
existing model is one line and touches neither its logic nor any controller.
Captured: who, when, IP, browser, URL, method, old and new values, changed
fields only, a business reason, AI attribution, and a batch id grouping bulk
operations.

Two deliberate properties: secrets are redacted before anything is written, and
writes are **best-effort** — a logging failure must never roll back the invoice
it was observing.

Permission changes go to a separate `permission_audits` table. "Who could do
what, when" is the first question an auditor asks and should not have to be
dug out of a table full of row edits.

### 12.4 Numbering sequences

Replaces the `count()`-based scheme, which had two real defects: two concurrent
requests could read the same count and mint duplicate numbers, and deleting a
record made the next one reuse a number already printed on a document. The
counter is now reserved under a row lock, with a documented fallback to the old
behaviour when no sequence row exists.

### 12.5 Feature flags

Modules are gated at the middleware and return **404, not 403** — a disabled
module should look absent rather than forbidden, so probing the API cannot map
out which features exist. An unknown flag defaults to **enabled**: a missing row
must never silently disable a working module.

---

## 13. Decision Log

| # | Decision | Rationale |
|---|---|---|
| D1 | Monorepo, 3 services | Simple for one intern; clean service boundaries |
| D2 | AI service separate from the Laravel app (FastAPI) | Independent evolution, security boundary |
| D3 | Agent tools call REST API with user JWT | Single RBAC enforcement point; agent never touches DB |
| D4 | Append-only stock movement ledger + cached quantity | Auditability + performance |
| D5 | Vite (not Next.js) | SPA behind an API; no SSR need; simpler Docker |
| D6 | ~~drf-spectacular OpenAPI~~ | Dropped with Django. No OpenAPI spec exists yet — see §5 |
| D7 | qwen3:32b on Ollama (env-swappable) | Strongest local tool calling; no hardware constraint; local privacy |
| D8 | dompdf for PDF (`barryvdh/laravel-dompdf`) | Blade templates → invoices, reports, statements |
| D9 | Human-in-the-loop for agent writes | Safety requirement FR-9.4 |
