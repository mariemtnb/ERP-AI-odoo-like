from rest_framework import status, viewsets
from rest_framework.decorators import action
from rest_framework.response import Response

from apps.core.permissions import EmployeeCanCreate

from . import services
from .models import Sale
from .serializers import SaleSerializer


class SaleViewSet(viewsets.ModelViewSet):
    queryset = Sale.objects.select_related("customer", "created_by").prefetch_related("lines__product")
    serializer_class = SaleSerializer
    permission_classes = [EmployeeCanCreate]
    filterset_fields = ["status", "customer"]
    search_fields = ["number", "customer__name"]

    def destroy(self, request, *args, **kwargs):
        return Response(
            {"detail": "Sales cannot be deleted — cancel them instead."},
            status=status.HTTP_405_METHOD_NOT_ALLOWED,
        )

    def _transition(self, request, fn):
        sale = self.get_object()
        try:
            fn(sale, user=request.user)
        except services.InvalidTransition as exc:
            return Response({"detail": str(exc)}, status=status.HTTP_409_CONFLICT)
        except services.InsufficientStock as exc:
            return Response({"detail": str(exc)}, status=status.HTTP_409_CONFLICT)
        return Response(self.get_serializer(sale).data)

    @action(detail=True, methods=["post"])
    def confirm(self, request, pk=None):
        return self._transition(request, services.confirm)

    @action(detail=True, methods=["post"])
    def cancel(self, request, pk=None):
        return self._transition(request, services.cancel)

    @action(detail=True, methods=["get", "post"])
    def invoice(self, request, pk=None):
        """POST: generate (idempotent). GET: download the PDF."""
        from django.http import HttpResponse

        from apps.reporting.pdf import render_pdf

        sale = self.get_object()
        try:
            invoice = services.generate_invoice(sale)
        except services.InvalidTransition as exc:
            return Response({"detail": str(exc)}, status=status.HTTP_409_CONFLICT)
        if request.method == "POST":
            return Response({"number": invoice.number, "issued_at": invoice.issued_at})
        pdf = render_pdf("reports/invoice.html", {"invoice": invoice, "sale": sale})
        response = HttpResponse(pdf, content_type="application/pdf")
        response["Content-Disposition"] = f'attachment; filename="{invoice.number}.pdf"'
        return response
