import { api } from "./client";
import type { Lead, LeadActivity, Paginated, Warehouse } from "@/types";

// --- warehouses ---
export async function listWarehouses() {
  const { data } = await api.get<{ results: Warehouse[] }>("/warehouses/");
  return data.results;
}

export async function transferStock(payload: {
  product: number;
  from_warehouse: number;
  to_warehouse: number;
  quantity: string;
  reason?: string;
}) {
  const { data } = await api.post("/warehouses/transfer/", payload);
  return data;
}

// --- CRM ---
export async function listLeads(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<Lead>>("/leads/", {
    params: { page_size: 100, ...params },
  });
  return data;
}

export async function createLead(payload: Partial<Lead>) {
  const { data } = await api.post<Lead>("/leads/", payload);
  return data;
}

export async function getLead(id: number) {
  const { data } = await api.get<Lead>(`/leads/${id}/`);
  return data;
}

export async function updateLead(id: number, payload: Partial<Lead>) {
  const { data } = await api.patch<Lead>(`/leads/${id}/`, payload);
  return data;
}

export async function addLeadActivity(
  id: number,
  payload: { type: LeadActivity["type"]; summary: string }
) {
  const { data } = await api.post<LeadActivity>(`/leads/${id}/activities/`, payload);
  return data;
}

export async function convertLead(id: number) {
  const { data } = await api.post<{ customer_id: number; lead: Lead }>(
    `/leads/${id}/convert/`
  );
  return data;
}
