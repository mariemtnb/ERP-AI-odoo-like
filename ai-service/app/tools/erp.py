"""ERP business tools for the agent.

Every tool is an HTTP call to the Django REST API carrying the END USER's JWT,
so the backend enforces RBAC exactly as if the user clicked the UI.
The agent never touches the database.

Write tools call `interrupt()` first: the graph pauses and the UI must send
an approval before the action executes (human-in-the-loop).
"""
from typing import Annotated

import httpx
from langchain_core.tools import tool
from langgraph.types import interrupt

from app.config import API

WRITE_TOOL_NAMES = {
    "create_customer", "update_customer", "create_supplier", "create_product",
    "update_stock", "create_purchase_order", "create_sale", "confirm_sale",
    "generate_invoice", "transfer_stock", "create_lead",
    # Tunisian treasury — every one of these moves money or the books.
    "create_installment_plan", "register_instrument", "deposit_instrument",
    "clear_instrument", "bounce_instrument", "record_payment",
    "settle_installment", "reconcile_bank_transaction",
}


def _client(token: str) -> httpx.Client:
    return httpx.Client(
        base_url=API,
        headers={"Authorization": f"Bearer {token}"},
        timeout=30,
    )


def _fmt(response: httpx.Response) -> dict | list | str:
    if response.status_code >= 400:
        try:
            return {"error": response.json(), "status": response.status_code}
        except ValueError:
            return {"error": response.text[:500], "status": response.status_code}
    try:
        return response.json()
    except ValueError:
        return response.text[:500]


def _confirm(action: str, details: dict):
    """Pause the graph until the user approves this write action."""
    decision = interrupt({"action": action, "details": details})
    if not (isinstance(decision, dict) and decision.get("approved")):
        raise PermissionError("The user rejected this action. Do not retry it.")


def build_tools(token: str) -> list:
    """Build the toolset bound to one user's JWT."""
    http = _client(token)

    # ---------- read tools ----------

    @tool
    def search_product(query: str) -> dict:
        """Search products by SKU or name. Returns matching products with
        current stock, prices and low-stock flag."""
        return _fmt(http.get("/products/", params={"search": query}))

    @tool
    def get_low_stock_products() -> dict:
        """List active products at or below their minimum stock level."""
        return _fmt(http.get("/products/", params={"low_stock": "true", "is_active": "true"}))

    @tool
    def search_customer(query: str) -> dict:
        """Search customers by name, email or phone."""
        return _fmt(http.get("/customers/", params={"search": query}))

    @tool
    def search_supplier(query: str) -> dict:
        """Search suppliers by name, email or phone."""
        return _fmt(http.get("/suppliers/", params={"search": query}))

    @tool
    def get_customer_history(customer_id: int) -> dict:
        """Get a customer's sales history (their recent sales documents)."""
        return _fmt(http.get(f"/customers/{customer_id}/history/"))

    @tool
    def get_dashboard_statistics(date_from: str = "", date_to: str = "") -> dict:
        """Business KPIs for a period (dates YYYY-MM-DD; defaults to last 30
        days): revenue, sales count, purchases, top products, low stock."""
        params = {}
        if date_from:
            params["from"] = date_from
        if date_to:
            params["to"] = date_to
        return _fmt(http.get("/dashboard/stats/", params=params))

    @tool
    def get_sales_report(date_from: str = "", date_to: str = "") -> dict:
        """Sales report for a period (dates YYYY-MM-DD): all sale documents
        with totals. Requires manager or admin role."""
        params = {}
        if date_from:
            params["from"] = date_from
        if date_to:
            params["to"] = date_to
        return _fmt(http.get("/reports/sales/", params=params))

    @tool
    def get_sales_forecast() -> dict:
        """Predictive analytics: 14-day revenue projection (trend over the
        last 30 days) and per-product estimated days until stockout."""
        return _fmt(http.get("/dashboard/forecast/"))

    @tool
    def get_warehouse_stock(product_id: int | None = None) -> dict:
        """Stock breakdown per warehouse; optionally for one product id."""
        params = {"product": product_id} if product_id else {}
        return _fmt(http.get("/warehouses/stock", params=params))

    @tool
    def search_leads(query: str = "", status: str = "") -> dict:
        """Search CRM leads by name/company/email, optionally filtered by
        status (new, contacted, qualified, won, lost)."""
        params = {}
        if query:
            params["search"] = query
        if status:
            params["status"] = status
        return _fmt(http.get("/leads", params=params))

    @tool
    def get_trial_balance(date_from: str = "", date_to: str = "") -> dict:
        """Accounting trial balance for a period (dates YYYY-MM-DD): every
        account with its total debit, credit and balance. Requires manager or
        admin role."""
        params = {}
        if date_from:
            params["from"] = date_from
        if date_to:
            params["to"] = date_to
        return _fmt(http.get("/accounting/trial-balance", params=params))

    @tool
    def get_income_statement(date_from: str = "", date_to: str = "") -> dict:
        """Profit and loss for a period (dates YYYY-MM-DD): income accounts,
        expense accounts and the net profit. Requires manager or admin role."""
        params = {}
        if date_from:
            params["from"] = date_from
        if date_to:
            params["to"] = date_to
        return _fmt(http.get("/accounting/income-statement", params=params))

    @tool
    def list_journal_entries(reference_type: str = "") -> dict:
        """Recent double-entry journal entries with their lines. Optionally
        filter by reference_type (sale, purchase, manual)."""
        params = {"reference_type": reference_type} if reference_type else {}
        return _fmt(http.get("/accounting/entries", params=params))

    # ---------- Tunisian treasury: cheques, effets, échéances, banque ----------

    @tool
    def list_outstanding_instruments(kind: str = "", direction: str = "") -> dict:
        """List cheques and commercial paper (traites / kembyelet) still
        expected to move money — received but not yet cashed, or issued but not
        yet debited. kind: 'cheque' or 'traite'. direction: 'incoming' (from
        customers) or 'outgoing' (to suppliers). Use for questions like
        "which cheques are still uncashed?" or "chèques en portefeuille"."""
        params: dict = {"outstanding": "true"}
        if kind:
            params["kind"] = kind
        if direction:
            params["direction"] = direction
        return _fmt(http.get("/instruments", params=params))

    @tool
    def get_instrument(instrument_id: int) -> dict:
        """Full detail of one cheque or traite: amount, dates, counterparty,
        current status, and its complete lifecycle history with the journal
        entry each step produced. Use this before explaining a cheque's
        status so the explanation is based on what actually happened."""
        return _fmt(http.get(f"/instruments/{instrument_id}"))

    @tool
    def get_instrument_summary() -> dict:
        """Portfolio totals for cheques and commercial paper: outstanding
        incoming and outgoing amounts, how many are overdue, and how many
        bounced (impayés). Good for "how much is out in cheques?"."""
        return _fmt(http.get("/instruments/summary"))

    @tool
    def list_bounced_instruments() -> dict:
        """Cheques and traites returned unpaid by the bank (chèque sans
        provision / effet impayé) that still need to be settled."""
        return _fmt(http.get("/instruments", params={"status": "bounced"}))

    @tool
    def list_overdue_installments(customer_id: int | None = None) -> dict:
        """Instalments (échéances) past their due date and not fully paid,
        with how many days late and how much is left. Optionally for one
        customer. Use for "khlas bel taqsit" follow-up and late-payment
        chasing."""
        params = {"customer": customer_id} if customer_id else {}
        return _fmt(http.get("/installments/overdue", params=params))

    @tool
    def list_installment_plans(customer_id: int | None = None, status: str = "") -> dict:
        """Instalment payment plans with their full schedule: amount due per
        date, what is paid, what remains. status: active, completed,
        cancelled, defaulted."""
        params: dict = {}
        if customer_id:
            params["customer"] = customer_id
        if status:
            params["status"] = status
        return _fmt(http.get("/installment-plans", params=params))

    @tool
    def get_customer_credit(customer_id: int) -> dict:
        """Credit exposure of one customer: total financed, outstanding,
        overdue amount, pending cheques and any bounced instruments. Use
        before agreeing to a new instalment plan for that customer."""
        return _fmt(http.get(f"/customers/{customer_id}/credit"))

    @tool
    def list_unreconciled_bank_transactions(bank_account_id: int | None = None) -> dict:
        """Bank statement lines that are not yet matched to anything in the
        ERP. These are what a bank reconciliation still has to explain."""
        params: dict = {"status": "unmatched"}
        if bank_account_id:
            params["bank_account"] = bank_account_id
        return _fmt(http.get("/bank-transactions", params=params))

    @tool
    def suggest_bank_match(transaction_id: int) -> dict:
        """For one bank statement line, the ranked candidate payments,
        cheques and instalments it could correspond to, with a score. Use
        this to help the user reconcile — then let THEM choose, or call
        reconcile_bank_transaction once they say which one."""
        return _fmt(http.get(f"/reconciliation/{transaction_id}/suggestions"))

    @tool
    def get_reconciliation_report(bank_account_id: int) -> dict:
        """Bank reconciliation statement for one account: statement balance
        versus book balance, the difference, how many lines are matched or
        still open, and which instruments are deposited but not yet
        credited."""
        return _fmt(http.get("/reconciliation/report", params={"bank_account": bank_account_id}))

    @tool
    def list_bank_accounts() -> dict:
        """The company's bank accounts with their bank, currency and current
        balance. Use to resolve a bank account id before other calls."""
        return _fmt(http.get("/bank-accounts"))

    @tool
    def explain_journal_entry(entry_id: int) -> dict:
        """Explain why a journal entry exists: the business event that caused
        it, and what each debit and credit line does to which account. Use
        this whenever the user asks why something was posted — answer from
        this result, never from your own assumptions about accounting rules."""
        return _fmt(http.get(f"/accounting/entries/{entry_id}/explain"))

    @tool
    def get_localization_settings() -> dict:
        """The company's fiscal profile (tax identifiers, VAT rate, currency,
        payment terms, stamp duty) and how each kind of movement maps to a
        ledger account. Consult this before stating anything about the
        company's accounting setup — these are settings, not fixed rules."""
        profile = _fmt(http.get("/localization/profile"))
        mappings = _fmt(http.get("/localization/mappings"))
        return {"profile": profile, "account_mappings": mappings}

    @tool
    def get_profit_summary(date_from: str = "", date_to: str = "") -> dict:
        """The owner's profit picture for a period (dates YYYY-MM-DD): revenue,
        cost of goods, gross profit, salaries, other expenses, net profit and
        the margins. Read this before giving the owner any read of how the
        business is doing. Manager/admin only."""
        params = {}
        if date_from:
            params["from"] = date_from
        if date_to:
            params["to"] = date_to
        result = _fmt(http.get("/owner/profit", params=params))
        return result.get("summary", result) if isinstance(result, dict) else result

    @tool
    def get_best_products(date_from: str = "", date_to: str = "") -> dict:
        """The products that made the most profit in a period, with quantity
        sold, revenue, margin and margin %. Use this to answer "what sells best"
        or to suggest what to push. Manager/admin only."""
        params = {}
        if date_from:
            params["from"] = date_from
        if date_to:
            params["to"] = date_to
        result = _fmt(http.get("/owner/profit", params=params))
        return {"best_products": result.get("best_products", [])} if isinstance(result, dict) else result

    @tool
    def search_documents(query: str) -> dict:
        """Semantic search in the company's document base (contracts, notes,
        policies…). Returns the most relevant passages with similarity scores.
        Use this when the user asks about company documents or policies."""
        return _fmt(http.get("/documents/search", params={"q": query}))

    @tool
    def get_stock_report() -> dict:
        """Full stock report: every active product with quantity and stock
        value. Requires manager or admin role."""
        return _fmt(http.get("/reports/stock/"))

    @tool
    def list_recent_sales(status: str = "") -> dict:
        """List recent sales documents, optionally filtered by status
        (draft, confirmed, cancelled)."""
        params = {"status": status} if status else {}
        return _fmt(http.get("/sales/", params=params))

    # ---------- write tools (require user confirmation) ----------

    @tool
    def create_customer(name: str, email: str = "", phone: str = "", address: str = "") -> dict:
        """Create a new customer."""
        payload = {"name": name, "email": email, "phone": phone, "address": address}
        _confirm("create_customer", payload)
        return _fmt(http.post("/customers/", json=payload))

    @tool
    def update_customer(customer_id: int, name: str = "", email: str = "", phone: str = "") -> dict:
        """Update an existing customer's contact details. Only pass the
        fields to change."""
        payload = {k: v for k, v in
                   {"name": name, "email": email, "phone": phone}.items() if v}
        _confirm("update_customer", {"customer_id": customer_id, **payload})
        return _fmt(http.patch(f"/customers/{customer_id}/", json=payload))

    @tool
    def create_supplier(name: str, email: str = "", phone: str = "", address: str = "") -> dict:
        """Create a new supplier."""
        payload = {"name": name, "email": email, "phone": phone, "address": address}
        _confirm("create_supplier", payload)
        return _fmt(http.post("/suppliers/", json=payload))

    @tool
    def create_product(
        sku: str, name: str, sale_price: float, cost_price: float = 0,
        category_id: int | None = None, min_stock_level: float = 0,
    ) -> dict:
        """Create a new product in the catalog."""
        payload = {
            "sku": sku, "name": name, "sale_price": str(sale_price),
            "cost_price": str(cost_price), "min_stock_level": str(min_stock_level),
            "category": category_id,
        }
        _confirm("create_product", payload)
        return _fmt(http.post("/products/", json=payload))

    @tool
    def update_stock(
        product_id: int,
        movement_type: Annotated[str, "one of: in, out, adjustment"],
        quantity: float,
        reason: str = "",
    ) -> dict:
        """Record a stock movement. 'in' adds stock, 'out' removes stock,
        'adjustment' applies a signed correction."""
        payload = {
            "product": product_id, "movement_type": movement_type,
            "quantity": str(quantity), "reason": reason or "via AI assistant",
        }
        _confirm("update_stock", payload)
        return _fmt(http.post("/stock/movements/", json=payload))

    @tool
    def create_purchase_order(
        supplier_id: int,
        lines: Annotated[list[dict], "list of {product: id, quantity: number, unit_price: number}"],
        order_date: str = "",
    ) -> dict:
        """Create a DRAFT purchase order for a supplier."""
        from datetime import date

        payload = {
            "supplier": supplier_id,
            "order_date": order_date or date.today().isoformat(),
            "lines": [
                {"product": l["product"], "quantity": str(l["quantity"]),
                 "unit_price": str(l["unit_price"])}
                for l in lines
            ],
        }
        _confirm("create_purchase_order", payload)
        return _fmt(http.post("/purchases/", json=payload))

    @tool
    def create_sale(
        customer_id: int,
        lines: Annotated[list[dict], "list of {product: id, quantity: number, unit_price: number}"],
        sale_date: str = "",
    ) -> dict:
        """Create a DRAFT sale for a customer. Use confirm_sale afterwards to
        confirm it and move stock."""
        from datetime import date

        payload = {
            "customer": customer_id,
            "sale_date": sale_date or date.today().isoformat(),
            "lines": [
                {"product": l["product"], "quantity": str(l["quantity"]),
                 "unit_price": str(l["unit_price"])}
                for l in lines
            ],
        }
        _confirm("create_sale", payload)
        return _fmt(http.post("/sales/", json=payload))

    @tool
    def confirm_sale(sale_id: int) -> dict:
        """Confirm a draft sale: validates stock and records the stock-out
        movements. Fails if stock is insufficient."""
        _confirm("confirm_sale", {"sale_id": sale_id})
        return _fmt(http.post(f"/sales/{sale_id}/confirm/"))

    @tool
    def transfer_stock(
        product_id: int, from_warehouse_id: int, to_warehouse_id: int,
        quantity: float, reason: str = "",
    ) -> dict:
        """Transfer stock between two warehouses (out at source, in at
        destination). Use get_warehouse_stock first to check availability."""
        payload = {
            "product": product_id, "from_warehouse": from_warehouse_id,
            "to_warehouse": to_warehouse_id, "quantity": str(quantity),
            "reason": reason,
        }
        _confirm("transfer_stock", payload)
        return _fmt(http.post("/warehouses/transfer", json=payload))

    @tool
    def create_lead(
        name: str, company: str = "", email: str = "", phone: str = "",
        source: str = "", notes: str = "",
    ) -> dict:
        """Create a CRM lead (prospect) to follow up commercially."""
        payload = {
            "name": name, "company": company, "email": email,
            "phone": phone, "source": source, "notes": notes,
        }
        _confirm("create_lead", payload)
        return _fmt(http.post("/leads", json=payload))

    @tool
    def generate_invoice(sale_id: int) -> dict:
        """Generate the invoice for a confirmed sale. Returns the invoice
        number; the user can download the PDF from the Sales page."""
        _confirm("generate_invoice", {"sale_id": sale_id})
        return _fmt(http.post(f"/sales/{sale_id}/invoice/"))

    # ---------- Tunisian treasury writes (all confirmed by the user) ----------

    @tool
    def create_installment_plan(
        reference_type: str, reference_id: int, total_amount: float,
        installment_count: int, frequency: str = "monthly",
        start_date: str = "", down_payment: float = 0, notes: str = "",
    ) -> dict:
        """Create an instalment payment plan ("khlas bel taqsit") splitting a
        sale or purchase into scheduled échéances. reference_type is 'sale' or
        'purchase'. frequency: weekly, biweekly, monthly, quarterly. The plan
        reschedules an existing debt — it does not create a new one, and
        nothing is posted until an instalment is actually paid."""
        payload = {
            "reference_type": reference_type,
            "reference_id": reference_id,
            "total_amount": total_amount,
            "installment_count": installment_count,
            "frequency": frequency,
            "down_payment": down_payment,
            "notes": notes,
        }
        if start_date:
            payload["start_date"] = start_date
        _confirm("create_installment_plan", payload)
        return _fmt(http.post("/installment-plans", json=payload))

    @tool
    def register_instrument(
        kind: str, direction: str, amount: float, issue_date: str,
        instrument_reference: str = "", due_date: str = "",
        customer_id: int | None = None, supplier_id: int | None = None,
        counterparty_name: str = "", bank_account_id: int | None = None,
        notes: str = "",
    ) -> dict:
        """Register a cheque or traite (kembya). kind: 'cheque' or 'traite'.
        direction: 'incoming' (received from a customer) or 'outgoing' (issued
        to a supplier). A traite always needs a due_date. This posts
        immediately: an incoming instrument turns the customer's debt into a
        cheque in hand."""
        payload: dict = {
            "kind": kind,
            "direction": direction,
            "amount": amount,
            "issue_date": issue_date,
            "instrument_reference": instrument_reference,
            "counterparty_name": counterparty_name,
            "notes": notes,
        }
        if due_date:
            payload["due_date"] = due_date
        if customer_id:
            payload["customer_id"] = customer_id
        if supplier_id:
            payload["supplier_id"] = supplier_id
        if bank_account_id:
            payload["bank_account_id"] = bank_account_id
        _confirm("register_instrument", payload)
        return _fmt(http.post("/instruments", json=payload))

    @tool
    def deposit_instrument(instrument_id: int, bank_account_id: int | None = None) -> dict:
        """Hand a received cheque or traite to the bank for collection
        (remise à l'encaissement). Moves it out of 'in hand' and into
        'in collection' in the books."""
        payload = {"bank_account_id": bank_account_id} if bank_account_id else {}
        _confirm("deposit_instrument", {"instrument_id": instrument_id, **payload})
        return _fmt(http.post(f"/instruments/{instrument_id}/deposit", json=payload))

    @tool
    def clear_instrument(instrument_id: int, date: str = "", fees: float = 0) -> dict:
        """Mark a cheque or traite as cleared — the bank credited (or debited)
        the money. Posts the movement to the bank account and expenses any
        bank fees. Only do this when the user confirms it appeared on the
        statement."""
        payload = {"fees": fees}
        if date:
            payload["date"] = date
        _confirm("clear_instrument", {"instrument_id": instrument_id, **payload})
        return _fmt(http.post(f"/instruments/{instrument_id}/clear", json=payload))

    @tool
    def bounce_instrument(
        instrument_id: int, reason: str = "", fees: float = 0,
        move_to_doubtful: bool = False,
    ) -> dict:
        """Record a cheque or traite returned unpaid (chèque sans provision /
        effet impayé). Reverses the recognition, puts the debt back on the
        counterparty, expenses any return fee, and reopens whatever instalment
        it was covering. This is a serious accounting event — always confirm
        the reason with the user first."""
        payload = {
            "reason": reason,
            "fees": fees,
            "move_to_doubtful": move_to_doubtful,
        }
        _confirm("bounce_instrument", {"instrument_id": instrument_id, **payload})
        return _fmt(http.post(f"/instruments/{instrument_id}/bounce", json=payload))

    @tool
    def record_payment(
        direction: str, method: str, amount: float,
        customer_id: int | None = None, supplier_id: int | None = None,
        bank_account_id: int | None = None, payment_date: str = "",
        is_advance: bool = False, reference: str = "", notes: str = "",
    ) -> dict:
        """Record money actually moving. direction: 'inbound' (received) or
        'outbound' (paid). method: cash, bank_transfer, card, bank_deposit,
        bank_withdrawal. Set is_advance for an avance/acompte received before
        any invoice. Do NOT use this for cheques or traites — register those
        with register_instrument instead, since they only hit the bank when
        they clear."""
        payload: dict = {
            "direction": direction,
            "method": method,
            "amount": amount,
            "is_advance": is_advance,
            "reference": reference,
            "notes": notes,
        }
        for key, value in (
            ("customer_id", customer_id),
            ("supplier_id", supplier_id),
            ("bank_account_id", bank_account_id),
        ):
            if value:
                payload[key] = value
        if payment_date:
            payload["payment_date"] = payment_date
        _confirm("record_payment", payload)
        return _fmt(http.post("/payments", json=payload))

    @tool
    def settle_installment(
        installment_id: int, amount: float, method: str,
        bank_account_id: int | None = None, reference: str = "",
    ) -> dict:
        """Pay one instalment of a plan. method: cash, bank_transfer, card,
        cheque, traite. Use list_overdue_installments or list_installment_plans
        first to get the instalment id and the exact amount remaining."""
        payload: dict = {"amount": amount, "method": method, "reference": reference}
        if bank_account_id:
            payload["bank_account_id"] = bank_account_id
        _confirm("settle_installment", {"installment_id": installment_id, **payload})
        return _fmt(http.post(f"/installments/{installment_id}/pay", json=payload))

    @tool
    def reconcile_bank_transaction(
        transaction_id: int, matchable_type: str, amount: float,
        matchable_id: int | None = None, note: str = "",
    ) -> dict:
        """Match a bank statement line to what it represents. matchable_type:
        payment, instrument, installment, sale, purchase, or 'adjustment' for
        a bank charge or unidentified line. Call suggest_bank_match first and
        let the user pick — never guess which invoice a line belongs to."""
        payload: dict = {
            "matchable_type": matchable_type,
            "amount": amount,
            "note": note,
        }
        if matchable_id:
            payload["matchable_id"] = matchable_id
        _confirm("reconcile_bank_transaction", {"transaction_id": transaction_id, **payload})
        return _fmt(http.post(f"/reconciliation/{transaction_id}/match", json=payload))

    return [
        search_product, get_low_stock_products, search_customer, search_supplier,
        get_customer_history, get_dashboard_statistics, get_sales_report,
        get_sales_forecast, get_stock_report, list_recent_sales, search_documents,
        get_warehouse_stock, search_leads,
        get_trial_balance, get_income_statement, list_journal_entries,
        # Tunisian treasury reads
        list_outstanding_instruments, get_instrument, get_instrument_summary,
        list_bounced_instruments, list_overdue_installments, list_installment_plans,
        get_customer_credit, list_unreconciled_bank_transactions, suggest_bank_match,
        get_reconciliation_report, list_bank_accounts, explain_journal_entry,
        get_localization_settings,
        # owner / profit
        get_profit_summary, get_best_products,
        # writes
        create_customer, update_customer, create_supplier, create_product,
        update_stock, create_purchase_order, create_sale, confirm_sale,
        generate_invoice, transfer_stock, create_lead,
        create_installment_plan, register_instrument, deposit_instrument,
        clear_instrument, bounce_instrument, record_payment, settle_installment,
        reconcile_bank_transaction,
    ]
