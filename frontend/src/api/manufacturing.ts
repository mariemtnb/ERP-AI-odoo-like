import { api } from "./client";
import type { Paginated } from "@/types";

export interface BomComponent {
  component: number;
  component_name: string | null;
  sku: string | null;
  quantity: string;
  in_stock: string | null;
}
export interface Bom {
  id: number;
  product: number;
  product_name: string | null;
  sku: string | null;
  output_quantity: string;
  is_active: boolean;
  components: BomComponent[];
}
export interface WorkOrder {
  id: number;
  number: string;
  bom: number;
  product: number;
  product_name: string | null;
  quantity: string;
  status: "draft" | "in_progress" | "done" | "cancelled";
  created_by_email: string | null;
}

export async function listBoms() {
  const { data } = await api.get<{ results: Bom[] }>("/boms/");
  return data.results;
}
export async function createBom(input: { product: number; output_quantity: number; components: { component: number; quantity: number }[] }) {
  const { data } = await api.post<Bom>("/boms/", input);
  return data;
}
export async function listWorkOrders() {
  const { data } = await api.get<Paginated<WorkOrder>>("/work-orders/", { params: { page_size: 100 } });
  return data.results;
}
export async function createWorkOrder(bom: number, quantity: number) {
  const { data } = await api.post<WorkOrder>("/work-orders/", { bom, quantity });
  return data;
}
export async function workOrderAction(id: number, action: "start" | "complete" | "cancel") {
  const { data } = await api.post<WorkOrder>(`/work-orders/${id}/${action}/`);
  return data;
}
