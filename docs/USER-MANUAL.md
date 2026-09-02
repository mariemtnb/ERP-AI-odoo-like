# Intelligent ERP — User Manual

A complete, plain-language guide to using the app. No technical background
needed. If a word looks unfamiliar, check the **Glossary** at the end.

---

## Table of contents

1. [What this app is](#1-what-this-app-is)
2. [Getting started](#2-getting-started)
3. [Finding your way around](#3-finding-your-way-around)
4. [Who can do what — roles](#4-who-can-do-what--roles)
5. [The AI assistant](#5-the-ai-assistant)
6. [The modules, one by one](#6-the-modules-one-by-one)
7. [Your account](#7-your-account)
8. [Step-by-step: common tasks](#8-step-by-step-common-tasks)
9. [Language and appearance](#9-language-and-appearance)
10. [Questions & troubleshooting](#10-questions--troubleshooting)
11. [Glossary](#11-glossary)

---

## 1. What this app is

This is an **ERP** — one program that runs the whole of a small business in a
single place, so you are not juggling separate tools and spreadsheets for
stock, sales, invoices, money and staff.

With it you can:

- Keep a catalog of **products** and track how many you have in each warehouse.
- Record **sales** to customers and **purchases** from suppliers.
- Ring up walk-in sales at a counter with **Point of Sale**.
- Keep the **books** (accounting), handle **cheques and instalments** the
  Tunisian way, and reconcile the **bank**.
- Manage **staff**, **payroll**, **projects** and a support **helpdesk**.
- See how the business is doing with **reports** and a dashboard.
- Ask an **AI assistant**, in your own words, to look things up or do tasks for
  you — always with your approval before anything changes.

It is built for Tunisian small and medium businesses and understands local
essentials like *traites/kembyelet* and "khlas bel taqsit" (instalment sales).

---

## 2. Getting started

### Opening the app

1. Make sure the app is running (see [10. Troubleshooting](#10-questions--troubleshooting)
   if it is not).
2. Open a web browser (Chrome, Edge, Firefox…) and go to:
   **http://localhost:5173**
3. You will see a welcome page. Click **Enter workspace** (or **Log in**).

### Logging in

Type your email and password, then click **Sign in**. For trying things out,
these demo accounts exist:

| Account | Email | Password | What it can do |
|---|---|---|---|
| Super admin | `superadmin@erp.local` | `Super123!` | Everything, incl. managing roles |
| Admin | `admin@erp.local` | `Admin123!` | Everything except role management |
| Manager | `manager@erp.local` | `Manager123!` | Run the day-to-day modules |
| Employee | `employee@erp.local` | `Employee123!` | Basic daily work |

> **Please change these passwords** before using the app for real. See
> [7. Your account](#7-your-account).

### Forgot your password?

On the login screen click **Forgot password?**, enter your email, and follow
the link you are given to set a new one.

---

## 3. Finding your way around

Once you are in, the screen has three parts:

- **The sidebar (left)** — your menu. It is grouped into sections (Overview,
  Catalog & Stock, Sales, Finance, and so on). Click any item to open that
  screen. You will only see the parts your role is allowed to use.
- **The top bar** — a **search box** (click it or press `Ctrl/⌘ + K` to jump to
  anything or ask the AI), an **Ask AI** button, a **notifications bell**, a
  **theme switch** (light/dark/crème), and **sign out**.
- **The main area** — where the screen you picked appears.

At the **bottom-left** is your name and role; click it to open **My Account**.

> **Tip:** the search box (`Ctrl/⌘ + K`) is the fastest way to move around —
> start typing the name of a screen, a product, or a question for the AI.

---

## 4. Who can do what — roles

Every person has a **role** that decides what they can see and do. There are
four built-in roles, and each one can do everything the one below it can:

1. **Employee** — daily work: look things up, create sales, add customers, use
   the assistant, edit their own profile.
2. **Manager** — all of the above, plus running the operational modules
   (catalog, purchasing, point of sale, HR, projects, treasury, reports).
3. **Admin** — all of the above, plus managing users, company settings,
   switching modules on/off, and the audit trail.
4. **Super admin** — all of the above, and the only role that can create other
   admins and **build custom roles**.

### Custom roles

A super admin can create their **own roles** and choose exactly which modules
each one can open. For example, a **Cashier** role might see only *Sales*,
*Point of Sale* and *Products* — everything else stays hidden. This is a real
limit: someone with that role cannot reach the other areas even by typing the
address directly. See [8. Common tasks](#8-step-by-step-common-tasks) to create
one.

---

## 5. The AI assistant

The assistant lets you use the app by **describing what you want** instead of
clicking through screens. Open it from **AI Assistant** in the sidebar, the
**Ask AI** button, or the search box.

**Examples of what you can ask:**

- "How much stock of Mineral Water 1L do we have?"
- "Create a sale for customer Ahmed: 3 chairs and 1 table."
- "Which invoices are overdue?"
- "Add a new customer named Sonia, phone 20 123 456."

**Two things keep it safe:**

1. **It acts as you.** It can only see and do what your own role allows — never
   more.
2. **It asks before changing anything.** When you ask it to create or change
   something, it shows a **confirmation card** in plain language (in the
   language you are using). Nothing is saved until you press **Approve**. If it
   looks wrong, press **Reject**.

You can talk to it in **English, French or Arabic** — it answers in the same
language.

---

## 6. The modules, one by one

Below is every area of the app, grouped exactly as the sidebar is. You may not
see all of them — that depends on your role and which modules are switched on.

### Overview

- **Dashboard** — your home screen. Key numbers at a glance: revenue, orders,
  money to collect, overdue items, bank position, plus AI insights (e.g. "this
  product will run out in ~2 weeks — consider reordering").

### Catalog & Stock

- **Products** — your list of items (goods or services). Each has a name, a
  code, a price and a category. This is the foundation everything else builds
  on.
- **Inventory** — how many of each product you have, in each **warehouse**.
  Every increase or decrease is recorded as a **movement**, so stock can never
  silently go wrong. You can transfer stock between warehouses.
- **Lots & Expiry** — for products sold in batches with expiry dates (food,
  medicine). Track each lot and be warned before things expire.
- **Manufacturing** — if you build products from parts: define a **bill of
  materials** (the recipe) and create **work orders** to produce them, which
  consumes the parts and adds the finished product to stock.

### Purchasing

- **Suppliers** — the businesses you buy from.
- **Purchases** — purchase orders you send to suppliers. Confirm them, then
  **receive** the goods, which adds them to stock and records what you owe.
- **Reordering** — set a minimum level per product; the app suggests (or
  creates) purchase orders when stock runs low, so you never run out.
- **RFQs** (Requests for Quotation) — ask several suppliers to quote a price,
  compare the bids, and **award** the best one.
- **Vendor Bills** — record the supplier's invoice and let the app **3-way
  match** it against the purchase order and what was actually received. If the
  quantity, price or receipt all agree it's *matched* (ready to pay); if
  something is off (billed for more than arrived, a price that changed, or no
  order at all) it's flagged as an *exception* a manager must approve first.

### Sales

- **Customers** — the people and businesses you sell to. A customer can be put
  on a **pricelist** to get their own prices.
- **Sales** — sales orders and invoices. Create an order, **confirm** it (which
  reserves/reduces stock), and turn it into an **invoice**. From a sale you can
  **email it to the customer** — they get a link to a private page where they
  can view it and **pay online**.
- **Pricelists** — set special prices: a fixed price or a percentage off, for a
  product, a whole category, or everything, optionally from a minimum quantity
  (e.g. cheaper when they buy 10+). A customer's pricelist, or the default one,
  is applied automatically on their sales and at the till.
- **Point of Sale (POS)** — a fast counter screen for walk-in sales: open a
  till session, add products, take payment, done. Great for a shop.
- **Subscriptions** — recurring billing (e.g. a monthly service). The app
  raises each invoice automatically on schedule.
- **Returns** — when a customer returns goods, issue a **credit note** that puts
  the items back and adjusts what they owe.
- **Shipping** — track deliveries: mark orders as shipped, then delivered.
- **Marketing** — simple campaigns you can send to groups of customers.
- **CRM** — track **leads** (potential customers) and their activities, and
  convert a lead into a real customer when it closes.

### Services

- **Projects** — organize work into projects with **tasks** and **timesheets**
  (hours logged against the project).
- **Helpdesk** — a support inbox: customers' **tickets**, replies, assignment
  to staff, and status (open → resolved).

### Finance

- **Profit** — an owner's view of what the business is actually making.
- **Accounting** — the formal books using **double-entry** (every entry has a
  matching debit and credit, so it always balances). Chart of accounts,
  journal entries, trial balance, income statement.
- **Fixed Assets** — big items you own (vehicles, equipment). The app spreads
  their cost over time (**depreciation**) and handles disposal.

### People

- **Payroll** — employees' pay with a proper Tunisian **gross-to-net**
  calculation: it works out **CNSS** (social security), **IRPP** (income tax,
  using the family situation you set on the employee) and the **CSS** solidarity
  levy automatically, so the net pay and the amounts owed to CNSS and the tax
  office are all computed for you. The rates and tax brackets are **settings** —
  update them when the finance law changes. Also handles bonuses and **salary
  advances**.
- **Human Resources (HR)** — attendance (clock in/out), **leave** requests, and
  **expense claims**, with manager approval.

### Treasury (Tunisia)

- **Cheques & Kembyelet** — record cheques and *traites* you receive or issue,
  and move each through its life (received → deposited → cleared, or bounced).
- **Installments** — instalment sales ("khlas bel taqsit"): set up a plan,
  collect each payment, and see overdue instalments.
- **Banking** — your bank accounts and their transactions; import a bank
  statement.
- **Reconciliation** — match your recorded payments against the real bank lines
  so your books agree with the bank.

### Insights

- **Reports** — ready-made reports on sales, stock, money and more.
- **VAT Return** — the periodic tax declaration for a month: the VAT you
  collected on sales, minus the VAT you paid on purchases, giving the amount to
  pay (or a credit to carry forward). Pick a month and read off the figure.
- **Report Builder** — build your own report by choosing what to measure and
  how to group it.
- **AI Assistant** — the assistant described in [section 5](#5-the-ai-assistant).

### Admin (admins & super admins)

- **Users** — add people, edit them, deactivate/reactivate accounts, and reset
  passwords.
- **Roles & Access** (super admin) — create custom roles and choose their
  modules.
- **Localization** — Tunisian settings: tax, accounting templates, journals.
- **Currencies** — currencies and exchange rates.
- **Administration** — company details, branches, numbering (invoice number
  formats), which **modules are switched on**, feature flags, and the **audit
  trail** (a record of who changed what).

### On every record: notes & activities

Open a record — a sale, a purchase, a customer — and at the bottom you'll find
**Activity & notes**. You can:

- write a **note** (a comment others on the team can see), and
- schedule an **activity** — a follow-up with a title and a due date ("Call to
  confirm payment on Friday"). Tick it off when done; overdue ones show in red,
  and your open activities gather into your own to-do list.

---

## 7. Your account

Click your name at the **bottom-left**, then **My Account**. From there you can:

- Change your **name**.
- Change your **email**.
- Change your **password** (you will confirm your current one).

To sign out, use the **exit icon** at the top-right.

---

## 8. Step-by-step: common tasks

### Make a sale

1. Sidebar → **Sales** → **New**.
2. Choose the **customer** (or add one on the fly).
3. Add **products** and quantities. The total is worked out for you.
4. **Save**, then **Confirm** the order (this updates stock).
5. Open the order and choose **Invoice** to bill the customer.

### Ring up a counter sale (POS)

1. Sidebar → **Point of Sale**.
2. If asked, **Open** a till session.
3. Click products to add them to the ticket, adjust quantities.
4. Choose **Checkout**, take payment, and finish. Stock updates automatically.

### Receive goods from a supplier

1. Sidebar → **Purchases** → **New**; pick the **supplier** and items.
2. **Confirm** the order (this sends it).
3. When the goods arrive, open the order and click **Receive** — stock goes up
   and the amount you owe is recorded.

### Email an invoice and get paid online

1. Open a **confirmed sale** and click **Email to customer**.
2. The customer receives the invoice with a link. A **portal link** also appears
   for you to copy.
3. On that page the customer clicks **Pay online**, completes the payment, and
   the sale shows as **Paid**.

### Set up a pricelist (special prices)

1. Sidebar → **Pricelists** → **New pricelist**; tick **default** if it should
   apply to everyone.
2. Open it and **add rules** — e.g. *Product X, fixed 80* or *Category Tools,
   10% off*, optionally *from quantity 10*.
3. To give one customer their own prices, edit the customer and choose the
   pricelist. Prices then apply automatically on their sales and at the till.

### Record a supplier's bill (with 3-way match)

1. Sidebar → **Vendor Bills** → **New bill**; pick the **supplier** and, if
   there is one, the **purchase order** (its lines fill in for you).
2. **Record & match**. The bill is checked against the order and receipt: a
   green **matched** means it's ready to pay; an amber **exception** lists what
   doesn't line up.
3. If the exception is acceptable, a manager clicks **Approve** to clear it.

### Add a new user

*(admin or super admin)*

1. Sidebar → **Users** → **New user**.
2. Enter their email, name and a temporary password, and pick a **role**.
3. **Create**. Tell them their password; they can change it in My Account.

### Create a custom role (e.g. "Cashier")

*(super admin only)*

1. Sidebar → **Roles & Access** → **New role**.
2. Give it a name (e.g. *Cashier*).
3. **Tick the modules** it may open (e.g. Products, Sales, Point of Sale).
4. **Create role**.
5. Now go to **Users**, edit a person, and set their role to *Cashier*. They
   will see only those areas.

### Ask the AI to do something

1. Click **Ask AI** (top bar) or open **AI Assistant**.
2. Type what you want, e.g. *"Create a customer named Leila, phone 22 334 455."*
3. Read the **confirmation card** it shows. If correct, press **Approve**.

---

## 9. Language and appearance

- **Language** — the app speaks **English, French and Arabic**. Arabic switches
  the layout to right-to-left automatically. Change it from the settings/menu.
- **Theme** — click the sun/moon/coffee icon in the top bar to switch between
  **light**, **dark** and **crème** looks. Pick whatever is easiest on your
  eyes.

---

## 10. Questions & troubleshooting

**The web page won't open / "can't reach the site".**
The app isn't running. Ask whoever set it up to start it, or run the start
command (see below). Then reopen **http://localhost:5173**.

**I can't see a menu item someone else has.**
Menus depend on your **role**. If you need access, ask an admin to change your
role or grant the module.

**A screen didn't load / looks empty.**
Refresh the page (F5). If it keeps happening, sign out and back in.

**The AI is slow to answer.**
The AI runs on your own machine. The first answer after starting up can be
slow while it "warms up"; later answers are quicker. A computer with a graphics
card (GPU) is much faster.

**I made a mistake — can I undo it?**
Most documents can be **cancelled** or reversed (a sale can be cancelled, a
return issues a credit note, etc.) rather than deleted, so there is always a
trail. Admins can see the full history in **Administration → Audit trail**.

**Starting and stopping the app** *(for whoever runs it)*
From the project folder:

```bash
./install.sh          # first time on a new computer: installs & starts everything
docker compose up -d  # start it again later
docker compose down   # stop it (your data is kept)
```

---

## 11. Glossary

- **ERP** — one program that runs the whole business (stock, sales, money,
  staff) in one place.
- **Module** — one area of the app (e.g. Sales, Payroll), shown in the sidebar.
- **Role** — the label on your account that decides what you can see and do.
- **Dashboard** — the home screen with key numbers.
- **Product / SKU** — an item you sell or stock; its code is often called a SKU.
- **Warehouse** — a place stock is kept; you can have several.
- **Stock movement** — a recorded change in how much you have.
- **Customer / Supplier** — who you sell to / buy from.
- **Sales order** — a customer's order before it becomes an invoice.
- **Invoice** — the bill you give a customer.
- **Purchase order** — your order to a supplier.
- **Credit note** — a "reverse invoice" used for returns.
- **POS (Point of Sale)** — the quick counter-sale screen.
- **Lead** — a potential customer you are still trying to win (in CRM).
- **Accounting / double-entry** — the formal books, where every entry balances
  (debits equal credits).
- **Debit / credit** — the two sides of every accounting entry; they must match.
- **Trial balance** — a check that all the books add up.
- **Depreciation** — spreading the cost of a big purchase over the years you
  use it.
- **Cheque / traite (kembyel)** — payment instruments; a traite is a dated
  promise to pay, common in Tunisia.
- **Instalments ("khlas bel taqsit")** — paying for something in scheduled
  parts.
- **Reconciliation** — matching your records to the real bank statement.
- **Payroll** — calculating and paying staff wages.
- **Audit trail** — the record of who changed what, and when.
- **Pricelist** — a set of special-price rules for products or categories.
- **Vendor bill** — a supplier's invoice recorded in the app.
- **3-way match** — checking a supplier's bill against the order and the goods
  received before paying it.
- **Portal** — the private web page a customer opens (via a link) to view and
  pay their document, without logging in.
- **CNSS** — Tunisia's social-security contribution, deducted from pay.
- **IRPP** — Tunisia's personal income tax, deducted from pay.
- **CSS** — a small solidarity levy on taxable income.
- **VAT return** — the periodic declaration of VAT collected minus VAT paid.
- **Chatter** — the notes-and-activities panel on a record.
- **Moving-average cost** — the running average price your stock cost, used to
  value it and work out cost of goods sold.
- **Feature flag** — a switch that turns a whole module on or off.
- **JWT / token** — the invisible "pass" your browser holds after you log in so
  you don't retype your password on every click.

---

*Need something not covered here? Ask the built-in **AI assistant** — it can
explain features and help you find the right screen.*
