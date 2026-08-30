import { api } from "./client";
import type { Paginated } from "@/types";

export interface StockLot {
  id: number;
  product: number;
  product_name: string | null;
  sku: string | null;
  warehouse: number;
  warehouse_name: string | null;
  lot_number: string;
  expiry_date: string | null;
  days_to_expiry: number | null;
  quantity: string;
  status: "ok" | "expiring" | "expired";
  received_at: string | null;
}

export interface ReceiveLotInput {
  product: number;
  warehouse?: number | null;
  lot_number: string;
  expiry_date?: string | null;
  quantity: number;
}

export async function listLots(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<StockLot>>("/lots/", { params });
  return data;
}

export async function getLotAlerts(days = 7) {
  const { data } = await api.get<{ expired: StockLot[]; expiring: StockLot[] }>("/lots/alerts/", {
    params: { days },
  });
  return data;
}

export async function receiveLot(input: ReceiveLotInput) {
  const { data } = await api.post<StockLot>("/lots/", input);
  return data;
}

export async function consumeFefo(product: number, quantity: number, reason?: string) {
  const { data } = await api.post<{ consumed: { lot_number: string; taken: number }[] }>(
    "/lots/consume/",
    { product, quantity, reason }
  );
  return data;
}
