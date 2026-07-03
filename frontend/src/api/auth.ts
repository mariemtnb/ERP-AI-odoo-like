import { api, tokens } from "./client";
import type { User } from "@/types";

export async function login(email: string, password: string): Promise<User> {
  const { data } = await api.post("/auth/login/", { email, password });
  tokens.set(data.access, data.refresh);
  return getMe();
}

export async function getMe(): Promise<User> {
  const { data } = await api.get<User>("/auth/me/");
  return data;
}

export async function logout() {
  try {
    if (tokens.refresh) await api.post("/auth/logout/", { refresh: tokens.refresh });
  } finally {
    tokens.clear();
  }
}
