import { api } from "./client";
import type { Paginated } from "@/types";

export interface Ticket {
  id: number;
  number: string;
  subject: string;
  customer: number | null;
  customer_name: string | null;
  priority: "low" | "normal" | "high" | "urgent";
  status: "open" | "in_progress" | "resolved" | "closed";
  assigned_to: number | null;
  assignee_email: string | null;
  created_by_email: string | null;
  messages_count: number;
  created_at: string | null;
  updated_at: string | null;
}
export interface TicketMessage {
  id: number;
  user_email: string | null;
  body: string;
  created_at: string | null;
}

export async function listTickets(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<Ticket>>("/tickets/", { params: { page_size: 100, ...params } });
  return data.results;
}
export async function getTicket(id: number) {
  const { data } = await api.get<Ticket & { messages: TicketMessage[] }>(`/tickets/${id}/`);
  return data;
}
export async function createTicket(input: { subject: string; customer?: number | null; priority: string; message?: string }) {
  const { data } = await api.post<Ticket>("/tickets/", input);
  return data;
}
export async function replyTicket(id: number, body: string) {
  const { data } = await api.post<TicketMessage>(`/tickets/${id}/reply/`, { body });
  return data;
}
export async function setTicketStatus(id: number, status: string) {
  const { data } = await api.post<Ticket>(`/tickets/${id}/status/${status}/`);
  return data;
}
