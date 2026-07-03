from rest_framework import serializers

from .models import StockMovement
from .services import InsufficientStock, record_movement


class StockMovementSerializer(serializers.ModelSerializer):
    product_sku = serializers.CharField(source="product.sku", read_only=True)
    product_name = serializers.CharField(source="product.name", read_only=True)
    created_by_email = serializers.CharField(source="created_by.email", read_only=True)

    class Meta:
        model = StockMovement
        fields = [
            "id", "product", "product_sku", "product_name", "movement_type",
            "quantity", "reason", "reference_type", "reference_id",
            "created_by_email", "created_at",
        ]
        read_only_fields = ["reference_type", "reference_id"]

    def create(self, validated_data):
        try:
            return record_movement(
                product_id=validated_data["product"].pk,
                movement_type=validated_data["movement_type"],
                quantity=validated_data["quantity"],
                reason=validated_data.get("reason", ""),
                user=self.context["request"].user,
                reference_type="manual",
            )
        except (InsufficientStock, ValueError) as exc:
            raise serializers.ValidationError({"quantity": str(exc)})
