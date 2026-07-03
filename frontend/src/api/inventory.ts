import { api } from "./client";
import type { Paginated, StockMovement } from "@/types";

export async function listMovements(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<StockMovement>>("/stock/movements/", {
    params,
  });
  return data;
}

export async function createMovement(payload: {
  product: number;
  movement_type: string;
  quantity: string;
  reason?: string;
}) {
  const { data } = await api.post<StockMovement>("/stock/movements/", payload);
  return data;
}
