"""Sale lifecycle. Confirmation is the moment stock leaves the shelf."""
from django.db import transaction
from django.utils import timezone

from apps.inventory.models import StockMovement
from apps.inventory.services import InsufficientStock, record_movement

from .models import Sale


class InvalidTransition(Exception):
    pass


def next_number() -> str:
    year = timezone.now().year
    count = Sale.objects.filter(number__startswith=f"SO-{year}-").count()
    return f"SO-{year}-{count + 1:04d}"


@transaction.atomic
def confirm(sale: Sale, user) -> Sale:
    """Validate availability and create one OUT movement per line.

    record_movement locks each product row and raises InsufficientStock,
    rolling back the whole confirmation — no partial stock-outs.
    """
    if sale.status != Sale.Status.DRAFT:
        raise InvalidTransition(f"Only draft sales can be confirmed (status: {sale.status}).")
    if not sale.lines.exists():
        raise InvalidTransition("Cannot confirm a sale without lines.")
    for line in sale.lines.select_related("product"):
        record_movement(
            product_id=line.product_id,
            movement_type=StockMovement.Type.OUT,
            quantity=line.quantity,
            user=user,
            reason=f"Sale {sale.number}",
            reference_type="sale",
            reference_id=sale.pk,
        )
    sale.status = Sale.Status.CONFIRMED
    sale.save(update_fields=["status", "updated_at"])
    return sale


@transaction.atomic
def cancel(sale: Sale, user) -> Sale:
    """Cancel a draft (no-op on stock) or a confirmed sale (reverses stock)."""
    if sale.status == Sale.Status.CANCELLED:
        raise InvalidTransition("Sale is already cancelled.")
    if sale.status == Sale.Status.CONFIRMED:
        for line in sale.lines.select_related("product"):
            record_movement(
                product_id=line.product_id,
                movement_type=StockMovement.Type.IN,
                quantity=line.quantity,
                user=user,
                reason=f"Cancellation of {sale.number}",
                reference_type="sale",
                reference_id=sale.pk,
            )
    sale.status = Sale.Status.CANCELLED
    sale.save(update_fields=["status", "updated_at"])
    return sale


__all__ = ["confirm", "cancel", "next_number", "InvalidTransition", "InsufficientStock"]
