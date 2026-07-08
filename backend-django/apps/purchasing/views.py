from rest_framework import status, viewsets
from rest_framework.decorators import action
from rest_framework.response import Response

from apps.core.permissions import ManagerWritesEmployeeReads

from . import services
from .models import PurchaseOrder
from .serializers import PurchaseOrderSerializer


class PurchaseOrderViewSet(viewsets.ModelViewSet):
    queryset = PurchaseOrder.objects.select_related("supplier", "created_by").prefetch_related("lines__product")
    serializer_class = PurchaseOrderSerializer
    permission_classes = [ManagerWritesEmployeeReads]
    filterset_fields = ["status", "supplier"]
    search_fields = ["number", "supplier__name"]

    def destroy(self, request, *args, **kwargs):
        return Response(
            {"detail": "Purchase orders cannot be deleted — cancel them instead."},
            status=status.HTTP_405_METHOD_NOT_ALLOWED,
        )

    def _transition(self, request, fn, **kwargs):
        po = self.get_object()
        try:
            fn(po, **kwargs)
        except services.InvalidTransition as exc:
            return Response({"detail": str(exc)}, status=status.HTTP_409_CONFLICT)
        return Response(self.get_serializer(po).data)

    @action(detail=True, methods=["post"])
    def confirm(self, request, pk=None):
        return self._transition(request, services.confirm)

    @action(detail=True, methods=["post"])
    def receive(self, request, pk=None):
        return self._transition(request, services.receive, user=request.user)

    @action(detail=True, methods=["post"])
    def cancel(self, request, pk=None):
        return self._transition(request, services.cancel)
