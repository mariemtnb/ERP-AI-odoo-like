import { api } from "./client";
import type { Paginated, User } from "@/types";

export async function listUsers(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<User>>("/users/", { params: { page_size: 100, ...params } });
  return data.results;
}

export interface UserInput {
  email?: string;
  password?: string;
  first_name?: string;
  last_name?: string;
  role?: string;
  is_active?: boolean;
}

export async function createUser(input: UserInput) {
  const { data } = await api.post<User>("/users/", input);
  return data;
}

export async function updateUser(id: number, input: UserInput) {
  const { data } = await api.patch<User>(`/users/${id}/`, input);
  return data;
}

export async function deactivateUser(id: number) {
  await api.delete(`/users/${id}/`);
}

export async function resetUserPassword(id: number, new_password: string) {
  const { data } = await api.post<{ detail: string }>(`/users/${id}/reset-password/`, { new_password });
  return data;
}
