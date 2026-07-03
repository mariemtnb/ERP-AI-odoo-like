from django.urls import path

from .views import (
    DashboardStatsView,
    PurchasesReportView,
    SalesReportView,
    StockReportView,
)

urlpatterns = [
    path("dashboard/stats/", DashboardStatsView.as_view(), name="dashboard-stats"),
    path("reports/sales/", SalesReportView.as_view(), name="report-sales"),
    path("reports/purchases/", PurchasesReportView.as_view(), name="report-purchases"),
    path("reports/stock/", StockReportView.as_view(), name="report-stock"),
]
