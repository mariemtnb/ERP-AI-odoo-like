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
    "generate_invoice",
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
    def generate_invoice(sale_id: int) -> dict:
        """Generate the invoice for a confirmed sale. Returns the invoice
        number; the user can download the PDF from the Sales page."""
        _confirm("generate_invoice", {"sale_id": sale_id})
        return _fmt(http.post(f"/sales/{sale_id}/invoice/"))

    return [
        search_product, get_low_stock_products, search_customer, search_supplier,
        get_customer_history, get_dashboard_statistics, get_sales_report,
        get_stock_report, list_recent_sales,
        create_customer, update_customer, create_supplier, create_product,
        update_stock, create_purchase_order, create_sale, confirm_sale,
        generate_invoice,
    ]
