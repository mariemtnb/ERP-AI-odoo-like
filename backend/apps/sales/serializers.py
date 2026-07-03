from django.db import transaction
from rest_framework import serializers

from .models import Sale, SaleLine
from .services import next_number


class SaleLineSerializer(serializers.ModelSerializer):
    product_sku = serializers.CharField(source="product.sku", read_only=True)
    product_name = serializers.CharField(source="product.name", read_only=True)
    subtotal = serializers.DecimalField(max_digits=14, decimal_places=2, read_only=True)

    class Meta:
        model = SaleLine
        fields = ["id", "product", "product_sku", "product_name", "quantity", "unit_price", "subtotal"]

    def validate_quantity(self, value):
        if value <= 0:
            raise serializers.ValidationError("Quantity must be positive.")
        return value


class SaleSerializer(serializers.ModelSerializer):
    lines = SaleLineSerializer(many=True)
    customer_name = serializers.CharField(source="customer.name", read_only=True)
    created_by_email = serializers.CharField(source="created_by.email", read_only=True)

    class Meta:
        model = Sale
        fields = [
            "id", "number", "customer", "customer_name", "status", "sale_date",
            "total_amount", "created_by_email", "lines", "created_at",
        ]
        read_only_fields = ["number", "status", "total_amount"]

    def validate_lines(self, value):
        if not value:
            raise serializers.ValidationError("At least one line is required.")
        return value

    @transaction.atomic
    def create(self, validated_data):
        lines = validated_data.pop("lines")
        sale = Sale.objects.create(
            number=next_number(),
            created_by=self.context["request"].user,
            **validated_data,
        )
        for line in lines:
            SaleLine.objects.create(sale=sale, **line)
        sale.recompute_total()
        return sale

    @transaction.atomic
    def update(self, instance, validated_data):
        if instance.status != Sale.Status.DRAFT:
            raise serializers.ValidationError("Only draft sales can be edited.")
        lines = validated_data.pop("lines", None)
        for attr, value in validated_data.items():
            setattr(instance, attr, value)
        instance.save()
        if lines is not None:
            instance.lines.all().delete()
            for line in lines:
                SaleLine.objects.create(sale=instance, **line)
        instance.recompute_total()
        return instance
