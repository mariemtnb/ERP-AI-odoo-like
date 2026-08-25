# Demo script (soutenance) — ~12 minutes

Accounts: `admin@erp.local / Admin123!` · `employee@erp.local / Employee123!`
Start: `docker compose up -d` → http://localhost:5173 (dev) or the prod stack on :8080.

## 1. Pitch (1 min)
Centralized ERP for SMEs (stock, sales, purchases, customers, suppliers) with a
conversational AI agent that can query data AND execute authorized business
actions — locally, no data leaves the machine.

## 2. RBAC in 2 logins (2 min)
1. Log in as **employee** → sidebar has no Users/Reports; open Products → no
   create/edit buttons (and the API returns 403 — UI is not the security layer).
2. Log in as **admin** → full sidebar. Open Users: roles, active/inactive.

## 3. The business cycle (3 min)
1. **Products**: show catalog, categories, low-stock badge.
2. **Purchases**: create PO for a low-stock product → Confirm → **Receive
   goods** → show stock increased.
3. **Inventory**: the movement ledger — every line has a reason, a source
   document and a user. Point out `purchase` reference from the receipt.
4. **Sales**: create a sale → Confirm → stock decreases → **Invoice PDF**.
5. Try selling more than the stock → clean error, nothing recorded.

## 4. Dashboard & reports (1 min)
Dashboard: revenue, counts, top products, low stock, date range.
Reports: switch tabs, **Export PDF** (sales report).

## 5. The AI agent — the highlight (4 min)
Open **AI Assistant**:
1. Read: *"Quel est le chiffre d'affaires de ce mois ?"* → tool chip
   `get_dashboard_statistics`, correct figures, French answer.
2. Read: *"Which products are low on stock?"* → real list.
3. **Write with confirmation**: *"Create a customer named Ali Ben Salem,
   phone +216 20 000 111"* → amber confirmation card with the exact payload →
   **Approve** → created (verify in Customers page).
4. Show the audit trail: every agent action logged with `actor=agent`.

Key sentences for the jury:
- "The agent never touches the database — its tools are HTTP calls to our own
  REST API carrying the logged-in user's JWT, so RBAC is enforced in exactly
  one place, for humans and AI alike."
- "Write actions pause the LangGraph graph (interrupt/resume) until the user
  approves — human-in-the-loop by construction, not by prompt."
- "The stock ledger is append-only; cancelling a confirmed sale writes a
  reversal movement instead of rewriting history."

## 5bis. Phase 3 features (2 min, optional)
1. **Voice**: click the mic in the chat and speak a request (Chrome/Edge).
2. **Forecasting**: dashboard shows the 14-day revenue projection (sparkline)
   and per-product days-until-stockout.
3. **OCR**: Purchases → *Import from invoice* → upload an invoice photo →
   the local vision model prefills supplier, quantities and prices.
4. **RAG**: ask the assistant *"How many days do customers have to return a
   product?"* → `search_documents` finds the answer in the ingested policy
   (pgvector semantic search, local embeddings).

## 5ter. Tunisia localization (3 min)

The part that makes this an ERP for a *Tunisian* SME, not a generic one.

1. **Settings → Localization**: matricule fiscal and its parts, régime réel,
   TVA 19%, timbre fiscal, TND with 3 decimals. Then the **Account mapping**
   tab — point out that every posting resolves through it, and hit
   *Apply Tunisian chart* to switch the whole ledger to 411/401/5112…
   *"Nothing legal is hardcoded — a change in practice is a settings change."*
2. **Cheques & Kembyelet**: open the cleared cheque, show its history — each
   step names the journal entry it produced. Then open the **bounced** one:
   *"Receiving a cheque doesn't settle a debt, it changes its form — which is
   exactly why the bounce can put it back."* Show the reversal and the
   *clients douteux* reclassification.
3. **Installments**: the plan with a 400 DT down payment and 6 mensualités —
   one paid in cash, one by transfer, one overdue. Point at the schedule.
4. **Reconciliation**: pick the "REMISE CHEQUE" line → the deposited cheque is
   ranked first → **Match**. It clears the cheque in one gesture.
   *"That bank line is the moment it cleared."*
5. **Dashboard**: the treasury row — cheques to collect, bounced instruments,
   overdue instalments, lines to reconcile, cash vs bank collections.

Ask the assistant, in French: *"Quels chèques sont impayés ?"* then
*"Pourquoi cette écriture a-t-elle été comptabilisée ?"* — it calls
`explain_journal_entry` and answers from the recorded facts.

## 6. Architecture slide (1 min)
Show diagrams/: ERD, use-case, agent sequence. Stack: React+TS, Laravel 12,
PostgreSQL, FastAPI+LangGraph, Ollama (qwen3:14b), Docker.

## Fallbacks
- If the LLM is slow: mention 20 GB model partially offloaded to CPU; the
  swap to `qwen3:32b` is one env var (`OLLAMA_MODEL`).
- If a chat turn errors: start a new conversation (fresh agent memory).
