import { api } from "./client";

export interface ManagedRole {
  id: number | null;
  key: string;
  name: string;
  description: string;
  is_system: boolean;
  modules: string[];
  user_count: number;
}

export interface ModuleOption {
  key: string;
  label: string;
}

export async function listRoles() {
  const { data } = await api.get<{ results: ManagedRole[] }>("/roles");
  return data.results;
}

export async function listModules() {
  const { data } = await api.get<{ results: ModuleOption[] }>("/roles/modules");
  return data.results;
}

export interface RoleInput {
  name: string;
  description?: string;
  modules: string[];
}

export async function createRole(input: RoleInput) {
  const { data } = await api.post<ManagedRole>("/roles", input);
  return data;
}

export async function updateRole(id: number, input: Partial<RoleInput>) {
  const { data } = await api.patch<ManagedRole>(`/roles/${id}`, input);
  return data;
}

export async function deleteRole(id: number) {
  await api.delete(`/roles/${id}`);
}
