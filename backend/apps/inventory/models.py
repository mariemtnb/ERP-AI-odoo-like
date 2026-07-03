from django.conf import settings
from django.db import models

from apps.catalog.models import Product


class StockMovement(models.Model):
    """Append-only ledger: rows are created, never updated or deleted.

    The product's cached quantity is maintained exclusively by
    services.record_movement inside the same DB transaction.
    """

    class Type(models.TextChoices):
        IN = "in", "Stock in"
        OUT = "out", "Stock out"
        ADJUSTMENT = "adjustment", "Adjustment"

    product = models.ForeignKey(
        Product, on_delete=models.PROTECT, related_name="movements"
    )
    movement_type = models.CharField(max_length=12, choices=Type.choices)
    # Positive for IN/OUT; signed delta for ADJUSTMENT.
    quantity = models.DecimalField(max_digits=12, decimal_places=3)
    reason = models.CharField(max_length=255, blank=True)
    # Link back to the business document that caused the movement.
    reference_type = models.CharField(max_length=20, blank=True)  # 'sale' | 'purchase' | 'manual'
    reference_id = models.BigIntegerField(null=True, blank=True)
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.PROTECT, related_name="stock_movements"
    )
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ["-created_at"]
        indexes = [models.Index(fields=["product", "created_at"])]

    def __str__(self):
        return f"{self.movement_type} {self.quantity} × {self.product.sku}"
