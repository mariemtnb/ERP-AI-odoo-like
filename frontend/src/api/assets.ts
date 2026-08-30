import { api } from "./client";
import type { Paginated } from "@/types";

export interface FixedAsset {
  id: number;
  name: string;
  category: string;
  acquisition_date: string;
  acquisition_cost: string;
  salvage_value: string;
  useful_life_months: number;
  method: string;
  accumulated_depreciation: string;
  book_value: string;
  status: "active" | "disposed";
  disposed_date: string | null;
  fully_depreciated: boolean;
}

export interface ScheduleRow { month: number; amount: number; book_value_after: number }

export async function listAssets() {
  const { data } = await api.get<Paginated<FixedAsset>>("/assets/", { params: { page_size: 100 } });
  return data.results;
}

export async function createAsset(input: {
  name: string;
  category?: string;
  acquisition_date: string;
  acquisition_cost: number;
  salvage_value?: number;
  useful_life_months: number;
}) {
  const { data } = await api.post<FixedAsset>("/assets/", input);
  return data;
}

export async function depreciateAsset(id: number, period?: string) {
  const { data } = await api.post<FixedAsset>(`/assets/${id}/depreciate/`, { period });
  return data;
}

export async function disposeAsset(id: number, disposed_date?: string) {
  const { data } = await api.post<FixedAsset>(`/assets/${id}/dispose/`, { disposed_date });
  return data;
}

export async function getSchedule(id: number) {
  const { data } = await api.get<{ monthly_charge: number; schedule: ScheduleRow[] }>(`/assets/${id}/schedule/`);
  return data;
}
