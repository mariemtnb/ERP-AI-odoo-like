import { api } from "./client";
import type { BusinessDoc, Paginated } from "@/types";

export interface ExtractedInvoice {
  supplier_name?: string | null;
  invoice_number?: string | null;
  date?: string | null;
  lines?: { description: string; quantity: number; unit_price: number }[];
  total?: number | null;
  matched_supplier_id?: number | null;
  error?: string;
}

export async function extractInvoice(file: File): Promise<ExtractedInvoice> {
  const form = new FormData();
  form.append("file", file);
  const { data } = await api.post<ExtractedInvoice>("/ocr/invoice/", form, {
    headers: { "Content-Type": "multipart/form-data" },
  });
  return data;
}

export function documentsApi(kind: "purchases" | "sales") {
  return {
    async list(params: Record<string, unknown> = {}) {
      const { data } = await api.get<Paginated<BusinessDoc>>(`/${kind}/`, { params });
      return data;
    },
    async create(payload: Record<string, unknown>) {
      const { data } = await api.post<BusinessDoc>(`/${kind}/`, payload);
      return data;
    },
    async action(id: number, name: "confirm" | "receive" | "cancel" | "approve" | "reject") {
      const { data } = await api.post<BusinessDoc>(`/${kind}/${id}/${name}/`);
      return data;
    },
  };
}
