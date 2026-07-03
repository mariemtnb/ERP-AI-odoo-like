import { api } from "./client";
import type { BusinessDoc, Paginated } from "@/types";

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
    async action(id: number, name: "confirm" | "receive" | "cancel") {
      const { data } = await api.post<BusinessDoc>(`/${kind}/${id}/${name}/`);
      return data;
    },
  };
}
