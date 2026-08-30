import { api } from "./client";
import type { Paginated } from "@/types";

export interface RfqLine {
  id: number;
  product: number;
  product_name: string | null;
  sku: string | null;
  quantity: string;
}
export interface Rfq {
  id: number;
  number: string;
  title: string;
  status: "open" | "awarded" | "closed";
  due_date: string | null;
  created_by_email: string | null;
  lines: RfqLine[];
  bids_count: number;
}
export interface Bid {
  id: number;
  rfq: number;
  supplier: number;
  supplier_name: string | null;
  status: string;
  total_amount: string;
  note: string;
  lines: { rfq_line: number; unit_price: string }[];
  is_lowest?: boolean;
}

export async function listRfqs() {
  const { data } = await api.get<Paginated<Rfq>>("/rfqs/", { params: { page_size: 100 } });
  return data.results;
}
export async function getRfq(id: number) {
  const { data } = await api.get<Rfq & { comparison: Bid[] }>(`/rfqs/${id}/`);
  return data;
}
export async function createRfq(input: { title: string; due_date?: string | null; lines: { product: number; quantity: number }[] }) {
  const { data } = await api.post<Rfq>("/rfqs/", input);
  return data;
}
export async function submitBid(rfqId: number, input: { supplier: number; note?: string; prices: Record<number, number> }) {
  const { data } = await api.post<Bid>(`/rfqs/${rfqId}/bids/`, input);
  return data;
}
export async function awardBid(rfqId: number, bidId: number) {
  const { data } = await api.post<{ awarded_bid: number; purchase_order: string; total_amount: string }>(`/rfqs/${rfqId}/award/${bidId}/`);
  return data;
}
