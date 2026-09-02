import { api } from "./client";

export interface ChatterMessage {
  id: number;
  body: string;
  author: string | null;
  author_email: string | null;
  created_at: string;
}

export interface ChatterActivity {
  id: number;
  title: string;
  due_date: string | null;
  assigned_to: number | null;
  assignee: string | null;
  done: boolean;
  overdue: boolean;
  created_at: string;
}

export interface ChatterThread {
  messages: ChatterMessage[];
  activities: ChatterActivity[];
}

export async function getChatter(type: string, id: number) {
  const { data } = await api.get<ChatterThread>(`/chatter/${type}/${id}`);
  return data;
}

export async function postMessage(type: string, id: number, body: string) {
  const { data } = await api.post<ChatterMessage>(`/chatter/${type}/${id}/messages`, { body });
  return data;
}

export async function scheduleActivity(type: string, id: number, input: { title: string; due_date?: string | null; assigned_to?: number | null }) {
  const { data } = await api.post<ChatterActivity>(`/chatter/${type}/${id}/activities`, input);
  return data;
}

export async function toggleActivity(id: number, done: boolean) {
  const { data } = await api.post<ChatterActivity>(`/activities/${id}/toggle`, { done });
  return data;
}

export async function myActivities() {
  const { data } = await api.get<{ results: (ChatterActivity & { subject_type: string; subject_id: number })[] }>("/activities/mine");
  return data.results;
}
