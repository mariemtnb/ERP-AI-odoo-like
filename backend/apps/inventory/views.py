from rest_framework import mixins, viewsets

from apps.core.permissions import ManagerWritesEmployeeReads

from .models import StockMovement
from .serializers import StockMovementSerializer


class StockMovementViewSet(
    mixins.CreateModelMixin,
    mixins.ListModelMixin,
    mixins.RetrieveModelMixin,
    viewsets.GenericViewSet,
):
    """Ledger rows can be listed and created — never edited or deleted."""

    queryset = StockMovement.objects.select_related("product", "created_by")
    serializer_class = StockMovementSerializer
    permission_classes = [ManagerWritesEmployeeReads]
    filterset_fields = ["product", "movement_type", "reference_type"]
    search_fields = ["product__sku", "product__name", "reason"]
