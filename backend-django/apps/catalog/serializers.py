from rest_framework import serializers

from .models import Category, Product


class CategorySerializer(serializers.ModelSerializer):
    product_count = serializers.IntegerField(read_only=True)

    class Meta:
        model = Category
        fields = ["id", "name", "description", "product_count"]


class ProductSerializer(serializers.ModelSerializer):
    category_name = serializers.CharField(source="category.name", read_only=True)
    is_low_stock = serializers.BooleanField(read_only=True)

    class Meta:
        model = Product
        fields = [
            "id", "sku", "name", "category", "category_name", "description",
            "cost_price", "sale_price", "unit", "quantity_in_stock",
            "min_stock_level", "is_low_stock", "is_active",
        ]
        # Stock changes only through movements — never by editing the product.
        read_only_fields = ["quantity_in_stock"]

    def validate_sale_price(self, value):
        if value < 0:
            raise serializers.ValidationError("Price cannot be negative.")
        return value

    def validate_cost_price(self, value):
        if value < 0:
            raise serializers.ValidationError("Price cannot be negative.")
        return value
