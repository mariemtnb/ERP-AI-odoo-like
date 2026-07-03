from django.conf import settings
from django.db import models

from apps.catalog.models import Product
from apps.core.models import TimeStampedModel
from apps.partners.models import Customer


class Sale(TimeStampedModel):
    class Status(models.TextChoices):
        DRAFT = "draft", "Draft"
        CONFIRMED = "confirmed", "Confirmed"
        CANCELLED = "cancelled", "Cancelled"

    number = models.CharField(max_length=20, unique=True)
    customer = models.ForeignKey(
        Customer, on_delete=models.PROTECT, related_name="sales"
    )
    status = models.CharField(max_length=12, choices=Status.choices, default=Status.DRAFT)
    sale_date = models.DateField()
    total_amount = models.DecimalField(max_digits=14, decimal_places=2, default=0)
    created_by = models.ForeignKey(
        settings.AUTH_USER_MODEL, on_delete=models.PROTECT, related_name="sales"
    )

    class Meta:
        ordering = ["-created_at"]

    def __str__(self):
        return self.number

    def recompute_total(self):
        self.total_amount = sum((line.subtotal for line in self.lines.all()), start=0)
        self.save(update_fields=["total_amount", "updated_at"])


class SaleLine(models.Model):
    sale = models.ForeignKey(Sale, on_delete=models.CASCADE, related_name="lines")
    product = models.ForeignKey(
        Product, on_delete=models.PROTECT, related_name="sale_lines"
    )
    quantity = models.DecimalField(max_digits=12, decimal_places=3)
    unit_price = models.DecimalField(max_digits=12, decimal_places=2)

    @property
    def subtotal(self):
        return self.quantity * self.unit_price
