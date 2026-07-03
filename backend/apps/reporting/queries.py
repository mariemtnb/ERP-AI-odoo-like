"""Aggregation queries shared by the dashboard, reports and the AI agent."""
from datetime import date

from django.db.models import Count, DecimalField, F, Sum
from django.db.models.functions import Coalesce

from apps.catalog.models import Product
from apps.purchasing.models import PurchaseOrder
from apps.sales.models import Sale, SaleLine

_dec = DecimalField(max_digits=14, decimal_places=2)


def dashboard_stats(date_from: date, date_to: date) -> dict:
    sales = Sale.objects.filter(
        status=Sale.Status.CONFIRMED, sale_date__range=(date_from, date_to)
    )
    purchases = PurchaseOrder.objects.filter(
        order_date__range=(date_from, date_to)
    ).exclude(status=PurchaseOrder.Status.CANCELLED)

    top_products = (
        SaleLine.objects.filter(
            sale__status=Sale.Status.CONFIRMED,
            sale__sale_date__range=(date_from, date_to),
        )
        .values("product__id", "product__sku", "product__name")
        .annotate(
            quantity_sold=Sum("quantity"),
            revenue=Sum(F("quantity") * F("unit_price"), output_field=_dec),
        )
        .order_by("-quantity_sold")[:5]
    )

    low_stock = Product.objects.filter(
        is_active=True, quantity_in_stock__lte=F("min_stock_level")
    ).values("id", "sku", "name", "quantity_in_stock", "min_stock_level")

    return {
        "date_from": date_from,
        "date_to": date_to,
        "revenue": sales.aggregate(v=Coalesce(Sum("total_amount"), 0, output_field=_dec))["v"],
        "sales_count": sales.count(),
        "purchases_count": purchases.count(),
        "purchases_amount": purchases.aggregate(
            v=Coalesce(Sum("total_amount"), 0, output_field=_dec)
        )["v"],
        "top_products": list(top_products),
        "low_stock": list(low_stock),
    }


def sales_report(date_from: date, date_to: date) -> dict:
    qs = (
        Sale.objects.filter(sale_date__range=(date_from, date_to))
        .exclude(status=Sale.Status.DRAFT)
        .select_related("customer")
        .order_by("sale_date", "number")
    )
    rows = [
        {
            "number": s.number,
            "date": s.sale_date,
            "customer": s.customer.name,
            "status": s.status,
            "total": s.total_amount,
        }
        for s in qs
    ]
    confirmed = [r for r in rows if r["status"] == "confirmed"]
    return {
        "title": "Sales report",
        "date_from": date_from,
        "date_to": date_to,
        "rows": rows,
        "count": len(confirmed),
        "total": sum((r["total"] for r in confirmed), start=0),
    }


def purchases_report(date_from: date, date_to: date) -> dict:
    qs = (
        PurchaseOrder.objects.filter(order_date__range=(date_from, date_to))
        .exclude(status=PurchaseOrder.Status.DRAFT)
        .select_related("supplier")
        .order_by("order_date", "number")
    )
    rows = [
        {
            "number": p.number,
            "date": p.order_date,
            "customer": p.supplier.name,  # column shared with sales template
            "status": p.status,
            "total": p.total_amount,
        }
        for p in qs
    ]
    active = [r for r in rows if r["status"] != "cancelled"]
    return {
        "title": "Purchases report",
        "date_from": date_from,
        "date_to": date_to,
        "rows": rows,
        "count": len(active),
        "total": sum((r["total"] for r in active), start=0),
    }


def stock_report() -> dict:
    products = (
        Product.objects.filter(is_active=True)
        .select_related("category")
        .order_by("category__name", "name")
    )
    rows = [
        {
            "sku": p.sku,
            "name": p.name,
            "category": p.category.name if p.category else "—",
            "quantity": p.quantity_in_stock,
            "min_level": p.min_stock_level,
            "value": p.quantity_in_stock * p.cost_price,
            "low": p.is_low_stock,
        }
        for p in products
    ]
    return {
        "title": "Stock report",
        "rows": rows,
        "count": len(rows),
        "total": sum((r["value"] for r in rows), start=0),
    }
