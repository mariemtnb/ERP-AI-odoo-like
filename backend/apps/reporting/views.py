from datetime import date, timedelta

from django.http import HttpResponse
from rest_framework.permissions import IsAuthenticated
from rest_framework.response import Response
from rest_framework.views import APIView

from apps.core.permissions import IsManagerOrAdmin

from . import queries
from .pdf import render_pdf


def _range(request) -> tuple[date, date]:
    """?from=YYYY-MM-DD&to=YYYY-MM-DD — defaults to the last 30 days."""
    today = date.today()
    try:
        date_from = date.fromisoformat(request.query_params.get("from", ""))
    except ValueError:
        date_from = today - timedelta(days=30)
    try:
        date_to = date.fromisoformat(request.query_params.get("to", ""))
    except ValueError:
        date_to = today
    return date_from, date_to


class DashboardStatsView(APIView):
    permission_classes = [IsAuthenticated]

    def get(self, request):
        date_from, date_to = _range(request)
        return Response(queries.dashboard_stats(date_from, date_to))


class _ReportView(APIView):
    permission_classes = [IsManagerOrAdmin]
    query = None  # set in subclass
    dated = True

    def get(self, request):
        if self.dated:
            date_from, date_to = _range(request)
            data = self.__class__.query(date_from, date_to)
        else:
            data = self.__class__.query()
        # NB: "format" is reserved by DRF's content negotiation — use "export".
        if request.query_params.get("export") == "pdf":
            pdf = render_pdf("reports/report.html", data)
            response = HttpResponse(pdf, content_type="application/pdf")
            slug = data["title"].lower().replace(" ", "_")
            response["Content-Disposition"] = f'attachment; filename="{slug}.pdf"'
            return response
        return Response(data)


class SalesReportView(_ReportView):
    query = staticmethod(queries.sales_report)


class PurchasesReportView(_ReportView):
    query = staticmethod(queries.purchases_report)


class StockReportView(_ReportView):
    query = staticmethod(queries.stock_report)
    dated = False
