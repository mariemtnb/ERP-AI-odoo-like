import { api } from "./client";
import type { Paginated } from "@/types";

export interface VendorBillLine {
  id: number;
  product: number;
  product_name: string | null;
  quantity: string;
  unit_price: string;
  subtotal: string;
}

export interface MatchLine {
  product: number;
  product_name: string | null;
  billed_qty: number;
  billed_price: number;
  ordered_qty: number;
  ordered_price: number | null;
  received_qty: number;
  flags: string[];
}

export interface VendorBill {
  id: number;
  number: string;
  supplier: number;
  supplier_name: string | null;
  purchase_order_id: number | null;
  purchase_order_number: string | null;
  bill_date: string;
  supplier_ref: string;
  total_amount: string;
  status: "matched" | "exception" | "approved" | "paid";
  approved_by_email: string | null;
  lines?: VendorBillLine[];
  match?: MatchLine[];
}

export async function listVendorBills(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<VendorBill>>("/vendor-bills", { params });
  return data;
}

export async function getVendorBill(id: number) {
  const { data } = await api.get<VendorBill>(`/vendor-bills/${id}`);
  return data;
}

export interface VendorBillInput {
  supplier: number;
  purchase_order?: number | null;
  bill_date: string;
  supplier_ref?: string;
  lines: { product: number; quantity: number; unit_price: number }[];
}

export async function createVendorBill(input: VendorBillInput) {
  const { data } = await api.post<VendorBill>("/vendor-bills", input);
  return data;
}

export async function approveVendorBill(id: number) {
  const { data } = await api.post<VendorBill>(`/vendor-bills/${id}/approve`);
  return data;
}
