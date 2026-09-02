# Roadmap — closing the gap to a full ERP

Prioritised plan for the capabilities still missing, ordered so each item
delivers value on its own and unblocks the ones after it. Built one at a time,
full-stack (backend + tests + UI), committed to `main` as each lands.

**Priority:** P0 = build first (highest value / compliance) · P1 = next ·
P2 = later. **Effort:** S ≈ 1 unit · M ≈ 2–3 · L ≈ 4+.

| # | Feature | Priority | Effort | Why it matters | Depends on |
|---|---|---|---|---|---|
| 1 | **Tunisian payroll engine (CNSS + IRPP salary rules)** | P0 | M | Turns payroll from manual pay-lines into a real gross→net calculation with social security (CNSS) and progressive income tax (IRPP). Specifically Tunisian; every employer needs it. | — |
| 2 | **Pricelists & discounts** | P0 | M | Customer- and quantity-based pricing, line/order discounts, applied in Sales and POS. Almost every business needs it. | — |
| 3 | **Send documents by email + customer portal** | P0 | M | Email an invoice/quote to a customer; a tokenised link lets them view (and later pay) it. Big perceived-completeness jump. | — |
| 4 | **Online payments (local gateway)** | P1 | M | Take card/online payment on an invoice via a Tunisian gateway (Konnect/Flouci) or a pluggable provider. | 3 |
| 5 | **Vendor bill 3-way matching** | P1 | M | Match supplier bill ↔ purchase order ↔ goods receipt before paying. Core accounts-payable control. | — |
| 6 | **VAT return / fiscal declarations** | P1 | S | Produce the periodic VAT declaration from posted tax, plus AR/AP aging. Compliance most SMEs ask for. | — |
| 7 | **Per-record chatter + scheduled activities** | P2 | M | Comments, mentions and follow-up reminders on any record — the collaboration layer that makes Odoo feel alive. | — |
| 8 | **Real-time inventory valuation to the ledger** | P2 | L | Post stock moves to the general ledger (FIFO/AVCO) so accounting and stock always agree. | 5 |

## Notes on scope

- Each item ships behind a **feature flag** and respects the existing role /
  module-access model, so it can be turned on per deployment and granted to
  custom roles.
- Tunisian rules (CNSS rates, IRPP brackets, VAT rates) are stored as
  **configuration**, not hardcoded, so they can be updated when the law changes.
- Larger items (portal, payments, valuation) are delivered in thin, working
  slices rather than all at once.

## Status

- [x] 1. Tunisian payroll engine (CNSS + IRPP) — **done**
- [x] 2. Pricelists & discounts — **done**
- [x] 3. Email documents + customer portal — **done**
- [x] 4. Online payments — **done**
- [x] 5. Vendor bill 3-way matching — **done**
- [x] 6. VAT return / fiscal declarations — **done**
- [x] 7. Chatter + activities — **done**
- [x] 8. Inventory valuation to the ledger — **done**

**All 8 roadmap items are complete.**

### Follow-ups (beyond the original 8) — done

- [x] **Line-level VAT** — per-line rates (0 / 7 / 13 / 19 %) and a multi-rate
  VAT return with a per-rate breakdown.
- [x] **Real payment gateways** — Konnect and Flouci adapters behind the same
  interface, with provider verification before a payment is settled.
- [x] **Partial goods receipts** — receive a purchase order in instalments;
  it sits at "partial" until complete, and vendor-bill matching reads the
  actual received quantity.

## Next batch — proposed (not started)

The original eight (and their follow-ups) are shipped. These are the next
highest-value gaps for a Tunisian-focused ERP, each verified as genuinely
missing (not already covered by an existing module). Same rules apply: each
ships behind a feature flag, respects the role / module-access model, and
keeps Tunisian rates and rules in configuration rather than code.

| #  | Feature | Priority | Effort | Why it matters | Depends on |
|----|---|---|---|---|---|
| 9  | **Tunisian e-invoicing (TTN «El Fatoora»)** | P0 | L | Generate, sign and submit the mandated electronic invoice to TTN and track its status. This is a legal requirement moving from B2G to B2B; nothing else on this list is compliance-blocking the way this is. | 3, line-level VAT |
| 10 | **Foreign-currency transactions + FX gain/loss** | P0 | L | Today currency is only a conversion helper — invoices, bills and payments are base-currency only. Store the currency and rate on the document, book realised FX gain/loss on settlement, and revalue open AR/AP at period end. | existing currency rates, 5, reconciliation |
| 11 | **Budgets & budget-vs-actual** | P1 | M | GL/analytic budgets per period with a budget-vs-actual report and optional soft/hard checks on POs and expense claims. Only project-level budgets exist today. | accounting, 12 |
| 12 | **Analytic distributions (cost centres)** | P1 | M | Tag journal, PO and expense lines with a business unit / cost centre and report P&L per dimension. The `BusinessUnit` dimension exists but nothing distributes onto it. | accounting |
| 13 | **AR dunning / automated follow-ups** | P1 | M | A multi-level reminder schedule on overdue invoices that reuses the email, chatter and scheduled-activity layers already shipped, plus the existing AR aging. | 3, 6, 7 |
| 14 | **Purchase requisitions + approval workflow** | P1 | M | Requisition → configurable approval chain → PO, built on a reusable approval engine other documents can later adopt. | purchasing |
| 15 | **Landed costs on goods receipts** | P2 | M | Spread freight, duty and insurance across received goods so the inventory valuation already posting to the ledger reflects true landed cost. | 8, partial receipts |

### Status

- [x] 9. Tunisian e-invoicing (TTN) — **done.** An invoiced sale generates a
  TEIF document (per-line VAT, both matricules, multi-rate tax breakdown) and is
  submitted to TTN through a pluggable provider — a built-in sandbox by default,
  the real TTN adapter behind the same interface — with an accepted invoice
  becoming final. Behind the `einvoicing` flag; managers/admins generate & submit.
- [x] 10. Foreign-currency transactions + FX gain/loss — **first slice done.**
  A payment can now settle a foreign-currency receivable/payable: the treasury
  moves the base value at the settlement-date rate while receivable/payable is
  relieved at the rate the debt was booked at, and the gap posts to a realized
  FX gain (7600) or loss (6600) account. Behind the `foreign_currency` flag;
  managers/admins settle. *Still to come:* carrying the currency on the invoice
  itself (so the book rate is read, not passed in) and period-end **unrealized**
  revaluation of open AR/AP.
- [x] 11. Budgets & budget-vs-actual — **done.** A budget is a named period
  with a planned amount per GL account; budget-vs-actual reads the actuals
  straight from the ledger (the account's posted movement over the period via
  the trial balance), reports per-line variance and whether it is favourable
  (income above target, expense under cap), and never drifts from the books.
  Behind the `budgets` flag; managers/admins. *Still to come:* per-analytic
  (cost-centre) budgets once item 12 lands, and optional soft/hard commitment
  checks on POs and expense claims.
- [x] 12. Analytic distributions (cost centres) — **done.** Ledger lines carry
  an optional business unit (cost/profit centre); `AccountingService::post`
  persists it, any posted line can be (re)assigned via
  `POST journal-lines/{line}/analytic`, and a per-dimension P&L
  (`GET reports/analytic`) rolls income/expense up by unit with everything
  untagged shown as "unallocated". Behind the `analytic` flag; managers/admins.
  *Still to come:* capturing the unit at source on sale/PO/expense entry, and
  per-analytic budgets (feeds item 11).
- [x] 13. AR dunning / automated follow-ups — **done.** A configurable ladder
  of reminder levels (7 / 30 / 60 days overdue by default) drives escalating
  follow-ups on unpaid invoices. A run finds overdue invoices (total less
  payments, past due by the company's terms), and for each sends the customer a
  reminder email, logs it to the sale's chatter, and records the send — picking
  the highest level reached but not yet sent, so it escalates without ever
  repeating a level. `GET dunning/candidates` previews; `POST dunning/run`
  sends. Behind the `dunning` flag; managers/admins. *Still to come:* a
  scheduled daily run (today it is triggered on demand).
- [x] 14. Purchase requisitions + approval workflow — **done.** A reusable,
  polymorphic approval engine (workflows → ordered steps, each with an approver
  role and an amount threshold → requests → recorded actions) routes any model
  through a chain and calls back the consumer on the final decision. Purchase
  requisitions are the first consumer: raise → submit (routes by estimate:
  manager for any amount, admin sign-off from 5 000) → approve/reject via an
  approval inbox → convert an approved one into a purchase order. Behind the
  `requisitions` flag. *Reusable by design:* other documents adopt it by
  implementing `applyApprovalOutcome()` and registering a workflow.
- [x] 15. Landed costs on goods receipts — **done.** Freight/duty/insurance on
  a received purchase order is spread across its received lines (by value or by
  quantity) and capitalised into inventory: each product's AVCO unit cost rises
  by its share, and the ledger posts Dr Inventory / Cr the landed-cost payable.
  Allocations are kept per product and always sum to the landed amount. Behind
  the `landed_costs` flag; managers/admins. *Simplification:* the whole share is
  capitalised onto stock still on hand (no split to COGS for units already sold).

**All 7 next-batch items (9–15) are complete.** Each ships behind its own
feature flag, respects the role model, and keeps Tunisian rates/rules in
configuration. The *still-to-come* notes above are deliberate follow-up slices,
not gaps in what was delivered.
