# Intelligent ERP — Project Report

A plain-language summary of what the system does and everything that has been
built. Written to be read by anyone — no deep technical background needed.

> For diagrams, see [`diagrams/`](../diagrams/README.md). For the technical
> architecture, see [`docs/ARCHITECTURE.md`](ARCHITECTURE.md). For a guided
> click-through, see [`docs/DEMO.md`](DEMO.md).

---

## 1. What this is

An all-in-one business management system (an "ERP") aimed at Tunisian small and
medium businesses, with a built-in **AI assistant** you can talk to in plain
language. It runs the day-to-day of a business: products and stock, buying and
selling, customers, money, people, and reporting — plus the local essentials
(cheques, *traites/kembyelet*, instalment sales, Tunisian accounting).

### The five moving parts

| Piece | Plain meaning |
|---|---|
| **React app** | The website you click around in, in the browser. |
| **Laravel API** | The brain: it holds all the rules and talks to the database. |
| **AI service** | The assistant that understands your questions. |
| **PostgreSQL** | The database where everything is stored. |
| **Ollama** | A local AI model, running on your own machine — nothing leaves it. |

Everything runs with one command via Docker. The assistant has **no direct
database access** — it uses the very same API a person uses, signed in as that
person, so it can never do more than the user could.

---

## 2. The modules (what the business can actually do)

The system started with catalog, stock, sales, purchasing, accounting and the
Tunisian treasury layer. It has since grown to a full suite. Each module has a
back end (rules + storage), automated tests, and its own screen in the app.

**Catalog & stock** — Products, multi-warehouse inventory, **lots & expiry
dates**, and **manufacturing** (bills of materials + work orders).

**Purchasing** — Suppliers, purchase orders (received in full or **in part**,
instalment by instalment), **automatic reordering** rules, **RFQs** (ask several
suppliers to quote, then award the best), and **vendor bills with 3-way
matching** — a supplier's invoice is checked against the order and what was
actually received, and anything that doesn't match is flagged before it can be
paid.

**Sales** — Customers, sales orders and invoices, **pricelists & discounts**
(customer- and quantity-based pricing), **Point of Sale (POS)** for
over-the-counter selling, **subscriptions** (recurring billing), **returns /
credit notes**, **shipping**, **marketing campaigns**, and a light **CRM** for
leads. Any quote or invoice can be **emailed to the customer** with a link to a
public **portal** where they view it and **pay online** through a Tunisian
gateway (Konnect or Flouci), or a built-in sandbox.

**Services** — **Projects** (tasks + timesheets) and a **Helpdesk** (support
tickets).

**Finance** — Double-entry **accounting**, an owner **profit** view, and
**fixed assets** (depreciation).

**People** — **Payroll** with a real Tunisian **gross-to-net calculation**
(CNSS social security, IRPP income tax and the CSS solidarity levy, all from
editable settings), salary advances, and **HR** (attendance, leave, expense
claims).

**Treasury (Tunisia)** — Cheques and *traites/kembyelet*, **instalment plans**
("khlas bel taqsit"), **banking** and **bank reconciliation**.

**Insights** — **Reports** (including a periodic **VAT return** computed line by
line, so mixed rates of 0 / 7 / 13 / 19 % are exact), a **report builder**, and
the **AI assistant**.

**Everywhere** — any record (a sale, a customer, a ticket…) carries **chatter**:
comments and **follow-up activities** with due dates that roll up into each
person's own to-do list.

Behind the scenes each module reuses the same trustworthy building blocks: every
stock change is a recorded movement that refuses to go below zero; stock is
valued at a **moving-average cost** so the books track what it really cost;
every accounting entry must balance (debits = credits); every document number
comes from a controlled sequence; and every important change is written to an
audit trail (who, when, before, after).

---

## 3. Accounts, roles and access

This is the part that decides **who can see and do what**.

### Built-in roles

From least to most powerful, each includes everything below it:

1. **Employee** — day-to-day work: look things up, create sales, add customers,
   ask the assistant, and edit their own profile.
2. **Manager** — everything an employee does, plus running the operational
   modules (catalog, purchasing, POS, HR, projects, treasury, reports…).
3. **Admin** — everything a manager does, plus managing users, company setup,
   turning modules on/off, and reading the audit trail.
4. **Super admin** — everything an admin does, and is the **only** role that can
   create or edit other admin (and super-admin) accounts, and manage roles.

### Custom roles (new)

A super admin can now **build their own roles** and tick exactly which modules
each one may open. For example, a **Cashier** role might get only *Sales*,
*Point of Sale* and *Products* — and nothing else.

This "allowlist" is enforced in **three places at once**, so it is a real
boundary, not just a hidden menu:

- **The sidebar** shows only the modules the role is allowed.
- **The page guard** sends the user back to the dashboard if they type a
  forbidden address by hand.
- **The API** refuses any request — read *or* write — to a module the role was
  not granted (returns a "not allowed" error).

Built-in roles are untouched by this: they carry no allowlist and keep working
exactly as before. The dashboard and a user's own profile are always reachable.

### Managing it all in the app

- **Users** screen (admin): create people, edit them, deactivate/reactivate,
  and reset passwords. Only a super admin can hand out admin roles.
- **Roles & Access** screen (super admin): create, edit and delete custom
  roles, ticking their modules. Built-in roles are shown as "full access" and
  cannot be changed; a role still assigned to someone cannot be deleted.

---

## 4. Security

Several hardening measures protect accounts and data:

- **Password reset** — a "forgot password" flow on the login screen, plus
  in-app password changes.
- **Account lockout** — after 5 failed sign-ins, that account is locked for 15
  minutes, which blunts password-guessing attacks. A correct sign-in clears the
  counter.
- **Security headers + Content-Security-Policy** — sent by both the API and the
  web server. These tell the browser to refuse risky behaviour (loading scripts
  from unknown places, embedding the app in a hostile page, guessing file
  types), which shuts down whole classes of web attacks.
- **Everything is re-checked on the server** — the browser hides what you
  cannot use, but the API never trusts the browser: it verifies your identity,
  that your account is active, your role, and (for custom roles) your module
  allowlist on **every** request.
- **Passwords are stored hashed** (never in plain text), and the sign-in
  session uses short-lived tokens that refresh safely.

---

## 5. The AI assistant

You can ask the assistant to do real work — "create a sale for customer X",
"how much stock of product Y do we have?", "which invoices are overdue?". Two
principles keep it safe:

1. **It acts as you.** It calls the same API you would, with your permissions —
   so it can never read or change anything you couldn't yourself.
2. **It asks before changing anything.** Any action that writes data pauses and
   shows you a plain-language confirmation card — in the language you were
   using — so you approve it before it happens.

---

## 6. Usability & polish

Alongside the features, the app itself was made smoother:

- The sidebar is **grouped into sections** (Overview, Catalog & Stock,
  Purchasing, Sales, …) so 30+ modules stay navigable, and it stays put while
  the page scrolls.
- **Navigation was made reliable** — pages used to occasionally not appear until
  a manual refresh; that is fixed, and a safety net now catches any single
  page's error instead of blanking the whole app.
- A **light animated background** matches the landing page without slowing
  things down.
- A **multi-language interface** and multiple themes (dark / light / crème).
- The **AI approval cards** are written in readable, human language.

---

## 7. How it all fits together (diagrams)

The [`diagrams/`](../diagrams/README.md) folder has one-page visual maps:

- **overview** — the five pieces and how they talk (start here).
- **use-case** — who can do what, now including super admin, custom roles and
  the module allowlist.
- **class-diagram** — where the business rules live, including the role and
  module-access gates.
- **erd** — the database by domain.
- **sequence-sale-posting** — what happens when a sale is confirmed (stock +
  accounting together).
- **sequence-agent** — how the assistant asks before it changes data.

---

## 8. Quality

Every module ships with automated tests. The full back-end test suite currently
passes at **362 tests / 1,031 checks, with zero failures**, covering the business
rules, the role and module-access boundaries, statutory payroll, pricing,
multi-rate VAT, 3-way matching, partial receipts, inventory valuation, gateway
verification, account lockout, and the security headers. The front end
type-checks cleanly.
