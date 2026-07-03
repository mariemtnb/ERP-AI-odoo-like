import { api } from "./client";
import type { Paginated, Partner } from "@/types";

export function partnersApi(kind: "customers" | "suppliers") {
  return {
    async list(params: Record<string, unknown> = {}) {
      const { data } = await api.get<Paginated<Partner>>(`/${kind}/`, { params });
      return data;
    },
    async create(payload: Partial<Partner>) {
      const { data } = await api.post<Partner>(`/${kind}/`, payload);
      return data;
    },
    async update(id: number, payload: Partial<Partner>) {
      const { data } = await api.patch<Partner>(`/${kind}/${id}/`, payload);
      return data;
    },
    async deactivate(id: number) {
      await api.delete(`/${kind}/${id}/`);
    },
  };
}
