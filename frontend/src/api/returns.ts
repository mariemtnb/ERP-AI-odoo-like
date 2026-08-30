import { api } from "./client";
import type { BusinessDoc, Paginated } from "@/types";

export interface CreditNoteLine {
  product: number;
  product_name: string | null;
  sku: string | null;
  quantity: string;
  unit_price: string;
  line_total: string;
}

export interface CreditNote {
  id: number;
  number: string;
  sale: number;
  sale_number: string | null;
  customer: number | null;
  customer_name: string | null;
  reason: string;
  total_amount: string;
  restocked: boolean;
  created_by_email: string | null;
  lines: CreditNoteLine[];
  created_at: string | null;
}

export interface CreateCreditNoteInput {
  reason?: string;
  restock: boolean;
  lines: { product: number; quantity: number; unit_price: number }[];
}

export async function listCreditNotes(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<CreditNote>>("/credit-notes/", { params });
  return data;
}

export async function listConfirmedSales(search = "") {
  const { data } = await api.get<Paginated<BusinessDoc>>("/sales/", {
    params: { search, status: "confirmed", page_size: 15 },
  });
  return data;
}

export async function getReturnable(saleId: number) {
  const { data } = await api.get<{ sale: string; returnable: Record<string, number> }>(
    `/sales/${saleId}/returnable/`
  );
  return data;
}

export async function createCreditNote(saleId: number, input: CreateCreditNoteInput) {
  const { data } = await api.post<CreditNote>(`/sales/${saleId}/credit-notes/`, input);
  return data;
}
