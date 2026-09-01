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
- [ ] 2. Pricelists & discounts — *in progress*
- [ ] 3. Email documents + customer portal
- [ ] 4. Online payments
- [ ] 5. Vendor bill 3-way matching
- [ ] 6. VAT return / fiscal declarations
- [ ] 7. Chatter + activities
- [ ] 8. Inventory valuation to the ledger
