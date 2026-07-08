from rest_framework import serializers

from .models import Customer, Supplier

_FIELDS = ["id", "name", "email", "phone", "address", "notes", "is_active", "created_at"]


class CustomerSerializer(serializers.ModelSerializer):
    class Meta:
        model = Customer
        fields = _FIELDS


class SupplierSerializer(serializers.ModelSerializer):
    class Meta:
        model = Supplier
        fields = _FIELDS
