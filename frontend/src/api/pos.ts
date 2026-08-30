import { api } from "./client";
import type { Paginated } from "@/types";

export interface PosSession {
  id: number;
  status: "open" | "closed";
  cashier: number;
  cashier_email: string | null;
  opening_float: string;
  expected_cash: string | null;
  closing_counted: string | null;
  variance: number | null;
  orders_count: number;
  opened_at: string | null;
  closed_at: string | null;
}

export interface PosOrderLine {
  product: number;
  product_name: string | null;
  sku: string | null;
  quantity: string;
  unit_price: string;
  line_total: string;
}

export interface PosOrder {
  id: number;
  number: string;
  session: number;
  customer: number | null;
  customer_name: string | null;
  status: string;
  total_amount: string;
  paid_amount: string;
  change_due: string;
  created_by_email: string | null;
  lines: PosOrderLine[];
  payments: { method: string; amount: string }[];
  created_at: string | null;
}

export interface CheckoutInput {
  customer?: number | null;
  lines: { product: number; quantity: number; unit_price: number }[];
  payments: { method: "cash" | "card" | "cheque"; amount: number }[];
}

export async function getCurrentSession() {
  const { data } = await api.get<PosSession | null>("/pos/session/");
  return data;
}

export async function openSession(opening_float: number) {
  const { data } = await api.post<PosSession>("/pos/session/open/", { opening_float });
  return data;
}

export async function closeSession(id: number, counted_cash: number) {
  const { data } = await api.post<PosSession>(`/pos/session/${id}/close/`, { counted_cash });
  return data;
}

export async function checkout(input: CheckoutInput) {
  const { data } = await api.post<PosOrder>("/pos/orders/", input);
  return data;
}

export async function listOrders(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<PosOrder>>("/pos/orders/", { params });
  return data;
}
