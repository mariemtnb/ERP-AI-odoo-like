import { api } from "./client";
import type { Paginated } from "@/types";

export interface Subscription {
  id: number;
  number: string;
  customer: number;
  customer_name: string | null;
  description: string;
  amount: string;
  interval: "monthly" | "quarterly" | "yearly";
  status: "active" | "paused" | "cancelled";
  start_date: string;
  next_invoice_date: string;
  invoices_count: number;
  billed_total: string;
}

export async function listSubscriptions() {
  const { data } = await api.get<Paginated<Subscription>>("/subscriptions/", { params: { page_size: 100 } });
  return data.results;
}
export async function createSubscription(input: {
  customer: number; description: string; amount: number; interval: string; start_date: string;
}) {
  const { data } = await api.post<Subscription>("/subscriptions/", input);
  return data;
}
export async function setSubscriptionStatus(id: number, status: "active" | "paused" | "cancelled") {
  const { data } = await api.post<Subscription>(`/subscriptions/${id}/status/${status}/`);
  return data;
}
export async function runBilling(as_of?: string) {
  const { data } = await api.post<{ generated: number; total_amount: string }>("/subscriptions/run-billing/", { as_of });
  return data;
}
export async function listCustomers() {
  const { data } = await api.get<Paginated<{ id: number; name: string }>>("/customers/", { params: { page_size: 200 } });
  return data.results;
}
