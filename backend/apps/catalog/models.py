from django.db import models

from apps.core.models import TimeStampedModel


class Category(TimeStampedModel):
    name = models.CharField(max_length=100, unique=True)
    description = models.TextField(blank=True)

    class Meta:
        verbose_name_plural = "categories"
        ordering = ["name"]

    def __str__(self):
        return self.name


class Product(TimeStampedModel):
    sku = models.CharField(max_length=50, unique=True)
    name = models.CharField(max_length=200, db_index=True)
    category = models.ForeignKey(
        Category, on_delete=models.PROTECT, related_name="products",
        null=True, blank=True,
    )
    description = models.TextField(blank=True)
    cost_price = models.DecimalField(max_digits=12, decimal_places=2, default=0)
    sale_price = models.DecimalField(max_digits=12, decimal_places=2, default=0)
    unit = models.CharField(max_length=20, default="unit")
    # Cached; source of truth is the sum of StockMovements (see inventory app).
    quantity_in_stock = models.DecimalField(max_digits=12, decimal_places=3, default=0)
    min_stock_level = models.DecimalField(max_digits=12, decimal_places=3, default=0)
    is_active = models.BooleanField(default=True)

    class Meta:
        ordering = ["name"]

    def __str__(self):
        return f"{self.sku} — {self.name}"

    @property
    def is_low_stock(self) -> bool:
        return self.quantity_in_stock <= self.min_stock_level
