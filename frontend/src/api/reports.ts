import { api } from "./client";

export interface DashboardStats {
  date_from: string;
  date_to: string;
  revenue: number;
  sales_count: number;
  purchases_count: number;
  purchases_amount: number;
  top_products: {
    product__id: number;
    product__sku: string;
    product__name: string;
    quantity_sold: number;
    revenue: number;
  }[];
  low_stock: {
    id: number;
    sku: string;
    name: string;
    quantity_in_stock: string;
    min_stock_level: string;
  }[];
}

export interface ReportData {
  title: string;
  date_from?: string;
  date_to?: string;
  rows: Record<string, unknown>[];
  count: number;
  total: number;
}

export interface Forecast {
  window_days: number;
  horizon_days: number;
  daily_revenue: { date: string; revenue: number }[];
  trend_per_day: number;
  projection: { date: string; revenue: number }[];
  projected_total: number;
  stockout_risk: {
    id: number;
    sku: string;
    name: string;
    quantity_in_stock: string;
    daily_consumption: number;
    days_until_stockout: number;
  }[];
}

export async function getForecast() {
  const { data } = await api.get<Forecast>("/dashboard/forecast/");
  return data;
}

export async function getDashboardStats(params: { from?: string; to?: string } = {}) {
  const { data } = await api.get<DashboardStats>("/dashboard/stats/", { params });
  return data;
}

export async function getReport(
  kind: "sales" | "purchases" | "stock",
  params: { from?: string; to?: string } = {}
) {
  const { data } = await api.get<ReportData>(`/reports/${kind}/`, { params });
  return data;
}

async function downloadBlob(url: string, params: Record<string, string>, filename: string) {
  const { data } = await api.get(url, { params, responseType: "blob" });
  const href = URL.createObjectURL(data);
  const a = document.createElement("a");
  a.href = href;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(href);
}

export function downloadReportPdf(
  kind: "sales" | "purchases" | "stock",
  params: { from?: string; to?: string } = {}
) {
  return downloadBlob(`/reports/${kind}/`, { ...params, export: "pdf" }, `${kind}_report.pdf`);
}

export async function generateInvoice(saleId: number) {
  const { data } = await api.post<{ number: string }>(`/sales/${saleId}/invoice/`);
  return data;
}

export function downloadInvoice(saleId: number, number: string) {
  return downloadBlob(`/sales/${saleId}/invoice/`, {}, `${number}.pdf`);
}

export async function emailSale(saleId: number) {
  const { data } = await api.post<{ sent: boolean; emailed_to: string; portal_url: string }>(`/sales/${saleId}/email`);
  return data;
}

export interface VatRateRow { rate: number; base: number; vat: number; }

export interface VatReturn {
  date_from: string;
  date_to: string;
  rate: number;
  sales_gross: number;
  sales_net: number;
  output_vat: number;
  output_by_rate: VatRateRow[];
  purchases_gross: number;
  purchases_net: number;
  input_vat: number;
  input_by_rate: VatRateRow[];
  net_vat_due: number;
  vat_credit: number;
}

export async function vatReturn(from: string, to: string) {
  const { data } = await api.get<VatReturn>("/reports/vat", { params: { from, to } });
  return data;
}
