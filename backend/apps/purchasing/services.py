"""Purchase order lifecycle. Stock only moves here via the inventory service."""
from django.db import transaction
from django.utils import timezone

from apps.inventory.models import StockMovement
from apps.inventory.services import record_movement

from .models import PurchaseOrder


class InvalidTransition(Exception):
    pass


def next_number() -> str:
    year = timezone.now().year
    count = PurchaseOrder.objects.filter(number__startswith=f"PO-{year}-").count()
    return f"PO-{year}-{count + 1:04d}"


def confirm(po: PurchaseOrder) -> PurchaseOrder:
    if po.status != PurchaseOrder.Status.DRAFT:
        raise InvalidTransition(f"Only draft orders can be confirmed (status: {po.status}).")
    if not po.lines.exists():
        raise InvalidTransition("Cannot confirm an order without lines.")
    po.status = PurchaseOrder.Status.CONFIRMED
    po.save(update_fields=["status", "updated_at"])
    return po


@transaction.atomic
def receive(po: PurchaseOrder, user) -> PurchaseOrder:
    """Goods receipt: one stock-IN movement per line, atomically."""
    if po.status != PurchaseOrder.Status.CONFIRMED:
        raise InvalidTransition(f"Only confirmed orders can be received (status: {po.status}).")
    for line in po.lines.select_related("product"):
        record_movement(
            product_id=line.product_id,
            movement_type=StockMovement.Type.IN,
            quantity=line.quantity,
            user=user,
            reason=f"Goods receipt {po.number}",
            reference_type="purchase",
            reference_id=po.pk,
        )
    po.status = PurchaseOrder.Status.RECEIVED
    po.received_date = timezone.now().date()
    po.save(update_fields=["status", "received_date", "updated_at"])
    return po


def cancel(po: PurchaseOrder) -> PurchaseOrder:
    if po.status not in (PurchaseOrder.Status.DRAFT, PurchaseOrder.Status.CONFIRMED):
        raise InvalidTransition(f"Cannot cancel a {po.status} order.")
    po.status = PurchaseOrder.Status.CANCELLED
    po.save(update_fields=["status", "updated_at"])
    return po
