import { api } from "./client";
import type { Paginated } from "@/types";

export interface Shipment {
  id: number;
  number: string;
  sale: number | null;
  sale_number: string | null;
  customer: number | null;
  customer_name: string | null;
  carrier: string;
  tracking_number: string | null;
  address: string;
  status: "pending" | "shipped" | "delivered" | "cancelled";
  shipped_at: string | null;
  delivered_at: string | null;
}

export async function listShipments(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<Shipment>>("/shipments/", { params: { page_size: 100, ...params } });
  return data.results;
}
export async function createShipment(input: { sale?: number | null; customer?: number | null; carrier: string; address?: string }) {
  const { data } = await api.post<Shipment>("/shipments/", input);
  return data;
}
export async function shipShipment(id: number, tracking_number?: string) {
  const { data } = await api.post<Shipment>(`/shipments/${id}/ship/`, { tracking_number });
  return data;
}
export async function deliverShipment(id: number) {
  const { data } = await api.post<Shipment>(`/shipments/${id}/deliver/`);
  return data;
}
export async function cancelShipment(id: number) {
  const { data } = await api.post<Shipment>(`/shipments/${id}/cancel/`);
  return data;
}
