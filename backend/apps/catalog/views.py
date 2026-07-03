from django.db.models import Count, F
from rest_framework import status, viewsets
from rest_framework.response import Response

from apps.core.permissions import ManagerWritesEmployeeReads

from .models import Category, Product
from .serializers import CategorySerializer, ProductSerializer


class CategoryViewSet(viewsets.ModelViewSet):
    queryset = Category.objects.annotate(product_count=Count("products"))
    serializer_class = CategorySerializer
    permission_classes = [ManagerWritesEmployeeReads]
    search_fields = ["name"]


class ProductViewSet(viewsets.ModelViewSet):
    serializer_class = ProductSerializer
    permission_classes = [ManagerWritesEmployeeReads]
    filterset_fields = ["category", "is_active"]
    search_fields = ["sku", "name"]
    ordering_fields = ["name", "sku", "quantity_in_stock", "sale_price"]

    def get_queryset(self):
        qs = Product.objects.select_related("category")
        if self.request.query_params.get("low_stock") == "true":
            qs = qs.filter(quantity_in_stock__lte=F("min_stock_level"))
        return qs

    def destroy(self, request, *args, **kwargs):
        product = self.get_object()
        product.is_active = False
        product.save(update_fields=["is_active"])
        return Response(status=status.HTTP_204_NO_CONTENT)
