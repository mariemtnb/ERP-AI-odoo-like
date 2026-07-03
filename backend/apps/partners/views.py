from rest_framework import status, viewsets
from rest_framework.decorators import action
from rest_framework.response import Response

from apps.core.permissions import EmployeeCanCreate, ManagerWritesEmployeeReads

from .models import Customer, Supplier
from .serializers import CustomerSerializer, SupplierSerializer


class _PartnerViewSet(viewsets.ModelViewSet):
    search_fields = ["name", "email", "phone"]
    filterset_fields = ["is_active"]

    def destroy(self, request, *args, **kwargs):
        partner = self.get_object()
        partner.is_active = False
        partner.save(update_fields=["is_active"])
        return Response(status=status.HTTP_204_NO_CONTENT)


class CustomerViewSet(_PartnerViewSet):
    queryset = Customer.objects.all()
    serializer_class = CustomerSerializer
    # Employees record walk-in customers at the counter.
    permission_classes = [EmployeeCanCreate]

    @action(detail=True, methods=["get"])
    def history(self, request, pk=None):
        from apps.sales.serializers import SaleSerializer

        sales = self.get_object().sales.prefetch_related("lines__product")[:50]
        return Response({"results": SaleSerializer(sales, many=True).data})


class SupplierViewSet(_PartnerViewSet):
    queryset = Supplier.objects.all()
    serializer_class = SupplierSerializer
    permission_classes = [ManagerWritesEmployeeReads]

    @action(detail=True, methods=["get"])
    def history(self, request, pk=None):
        from apps.purchasing.serializers import PurchaseOrderSerializer

        orders = self.get_object().purchase_orders.prefetch_related("lines__product")[:50]
        return Response({"results": PurchaseOrderSerializer(orders, many=True).data})
