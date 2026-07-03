"""Stock service layer — the ONLY place stock quantities change.

Purchasing (goods receipt) and sales (confirmation) call these functions
in week 5; the AI agent's update_stock tool reaches them through the API.
"""
from decimal import Decimal

from django.db import transaction

from apps.catalog.models import Product

from .models import StockMovement


class InsufficientStock(Exception):
    def __init__(self, product: Product, requested: Decimal):
        self.product = product
        self.requested = requested
        super().__init__(
            f"Insufficient stock for {product.sku}: "
            f"requested {requested}, available {product.quantity_in_stock}"
        )


@transaction.atomic
def record_movement(
    *,
    product_id: int,
    movement_type: str,
    quantity: Decimal,
    user,
    reason: str = "",
    reference_type: str = "manual",
    reference_id: int | None = None,
) -> StockMovement:
    """Atomically create a ledger row and update the cached quantity.

    Locks the product row so concurrent sales can't oversell.
    IN/OUT require a positive quantity; ADJUSTMENT takes a signed delta.
    """
    product = Product.objects.select_for_update().get(pk=product_id)

    if movement_type in (StockMovement.Type.IN, StockMovement.Type.OUT):
        if quantity <= 0:
            raise ValueError("Quantity must be positive for in/out movements.")

    if movement_type == StockMovement.Type.IN:
        delta = quantity
    elif movement_type == StockMovement.Type.OUT:
        delta = -quantity
    elif movement_type == StockMovement.Type.ADJUSTMENT:
        delta = quantity
    else:
        raise ValueError(f"Unknown movement type: {movement_type}")

    new_quantity = product.quantity_in_stock + delta
    if new_quantity < 0:
        raise InsufficientStock(product, abs(delta))

    movement = StockMovement.objects.create(
        product=product,
        movement_type=movement_type,
        quantity=quantity,
        reason=reason,
        reference_type=reference_type,
        reference_id=reference_id,
        created_by=user,
    )
    product.quantity_in_stock = new_quantity
    product.save(update_fields=["quantity_in_stock", "updated_at"])
    return movement
