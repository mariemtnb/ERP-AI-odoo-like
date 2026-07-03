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

    @action(detail=True, methods=["get"])
    def history(self, request, pk=None):
        """Transaction history. Populated by the sales/purchasing apps (week 5);
        exposed now so the frontend contract is stable."""
        return Response({"results": [], "detail": "History available once sales/purchases exist."})


class CustomerViewSet(_PartnerViewSet):
    queryset = Customer.objects.all()
    serializer_class = CustomerSerializer
    # Employees record walk-in customers at the counter.
    permission_classes = [EmployeeCanCreate]


class SupplierViewSet(_PartnerViewSet):
    queryset = Supplier.objects.all()
    serializer_class = SupplierSerializer
    permission_classes = [ManagerWritesEmployeeReads]
