from django.db import models

from apps.core.models import TimeStampedModel


class PartnerBase(TimeStampedModel):
    """Shared shape for customers and suppliers."""

    name = models.CharField(max_length=200, db_index=True)
    email = models.EmailField(blank=True)
    phone = models.CharField(max_length=30, blank=True)
    address = models.TextField(blank=True)
    notes = models.TextField(blank=True)
    is_active = models.BooleanField(default=True)

    class Meta:
        abstract = True
        ordering = ["name"]

    def __str__(self):
        return self.name


class Customer(PartnerBase):
    pass


class Supplier(PartnerBase):
    pass
