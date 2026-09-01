import { api } from "./client";

export interface PricelistRule {
  id: number;
  pricelist_id: number;
  product_id: number | null;
  product_name: string | null;
  category_id: number | null;
  category_name: string | null;
  min_qty: string;
  mode: "fixed" | "discount";
  value: string;
}

export interface Pricelist {
  id: number;
  name: string;
  is_active: boolean;
  is_default: boolean;
  notes: string;
  rule_count: number;
  rules?: PricelistRule[];
}

export async function listPricelists() {
  const { data } = await api.get<{ results: Pricelist[] }>("/pricelists");
  return data.results;
}

export async function getPricelist(id: number) {
  const { data } = await api.get<Pricelist>(`/pricelists/${id}`);
  return data;
}

export async function createPricelist(input: { name: string; is_default?: boolean; notes?: string }) {
  const { data } = await api.post<Pricelist>("/pricelists", input);
  return data;
}

export async function updatePricelist(id: number, input: Partial<{ name: string; is_active: boolean; is_default: boolean; notes: string }>) {
  const { data } = await api.patch<Pricelist>(`/pricelists/${id}`, input);
  return data;
}

export async function deletePricelist(id: number) {
  await api.delete(`/pricelists/${id}`);
}

export interface RuleInput {
  product_id?: number | null;
  category_id?: number | null;
  min_qty?: number;
  mode: "fixed" | "discount";
  value: number;
}

export async function addPricelistRule(pricelistId: number, input: RuleInput) {
  const { data } = await api.post<PricelistRule>(`/pricelists/${pricelistId}/rules`, input);
  return data;
}

export async function removePricelistRule(ruleId: number) {
  await api.delete(`/pricelist-rules/${ruleId}`);
}

/** Live price for a product/quantity/customer, honouring the pricelist. */
export async function resolvePrice(params: { product: number; quantity?: number; customer?: number | null }) {
  const { data } = await api.get<{ product: number; base_price: string; unit_price: string; pricelist: string | null }>(
    "/pricing/resolve",
    { params }
  );
  return data;
}
