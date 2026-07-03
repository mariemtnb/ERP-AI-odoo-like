from django.db import transaction
from rest_framework import serializers

from .models import PurchaseOrder, PurchaseOrderLine
from .services import next_number


class PurchaseOrderLineSerializer(serializers.ModelSerializer):
    product_sku = serializers.CharField(source="product.sku", read_only=True)
    product_name = serializers.CharField(source="product.name", read_only=True)
    subtotal = serializers.DecimalField(max_digits=14, decimal_places=2, read_only=True)

    class Meta:
        model = PurchaseOrderLine
        fields = ["id", "product", "product_sku", "product_name", "quantity", "unit_price", "subtotal"]

    def validate_quantity(self, value):
        if value <= 0:
            raise serializers.ValidationError("Quantity must be positive.")
        return value


class PurchaseOrderSerializer(serializers.ModelSerializer):
    lines = PurchaseOrderLineSerializer(many=True)
    supplier_name = serializers.CharField(source="supplier.name", read_only=True)
    created_by_email = serializers.CharField(source="created_by.email", read_only=True)

    class Meta:
        model = PurchaseOrder
        fields = [
            "id", "number", "supplier", "supplier_name", "status",
            "order_date", "received_date", "total_amount",
            "created_by_email", "lines", "created_at",
        ]
        read_only_fields = ["number", "status", "received_date", "total_amount"]

    def validate_lines(self, value):
        if not value:
            raise serializers.ValidationError("At least one line is required.")
        return value

    @transaction.atomic
    def create(self, validated_data):
        lines = validated_data.pop("lines")
        po = PurchaseOrder.objects.create(
            number=next_number(),
            created_by=self.context["request"].user,
            **validated_data,
        )
        for line in lines:
            PurchaseOrderLine.objects.create(purchase_order=po, **line)
        po.recompute_total()
        return po

    @transaction.atomic
    def update(self, instance, validated_data):
        if instance.status != PurchaseOrder.Status.DRAFT:
            raise serializers.ValidationError("Only draft orders can be edited.")
        lines = validated_data.pop("lines", None)
        for attr, value in validated_data.items():
            setattr(instance, attr, value)
        instance.save()
        if lines is not None:
            instance.lines.all().delete()
            for line in lines:
                PurchaseOrderLine.objects.create(purchase_order=instance, **line)
        instance.recompute_total()
        return instance
