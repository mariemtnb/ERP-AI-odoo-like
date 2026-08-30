import { api } from "./client";

export interface ReorderRule {
  id: number;
  product: number;
  product_name: string | null;
  sku: string | null;
  current_stock: string | null;
  supplier: number | null;
  supplier_name: string | null;
  min_qty: string;
  reorder_qty: string;
  is_active: boolean;
}

export interface Suggestion {
  product: number;
  product_name: string;
  sku: string;
  current_stock: number;
  min_qty: number;
  reorder_qty: number;
  supplier: number | null;
  supplier_name: string | null;
}

export interface RunResult {
  created: { id: number; number: string; supplier: number; total_amount: string; lines: number }[];
  unassigned: Suggestion[];
}

export async function listRules() {
  const { data } = await api.get<{ results: ReorderRule[] }>("/reorder-rules/");
  return data.results;
}

export async function getSuggestions() {
  const { data } = await api.get<{ results: Suggestion[] }>("/reorder-suggestions/");
  return data.results;
}

export async function createRule(input: {
  product: number;
  supplier?: number | null;
  min_qty: number;
  reorder_qty: number;
}) {
  const { data } = await api.post<ReorderRule>("/reorder-rules/", input);
  return data;
}

export async function deleteRule(id: number) {
  await api.delete(`/reorder-rules/${id}/`);
}

export async function runReplenishment() {
  const { data } = await api.post<RunResult>("/reorder-run/");
  return data;
}

export async function listSuppliers() {
  const { data } = await api.get<{ results: { id: number; name: string }[] }>("/suppliers/", {
    params: { page_size: 100 },
  });
  return data.results;
}
