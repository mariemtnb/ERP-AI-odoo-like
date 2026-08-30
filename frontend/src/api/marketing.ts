import { api } from "./client";
import type { Paginated } from "@/types";

export interface Campaign {
  id: number;
  name: string;
  channel: "email" | "sms";
  subject: string;
  body: string;
  status: "draft" | "sent";
  sent_count: number;
  created_by_email: string | null;
  sent_at: string | null;
  audience_size?: number;
}

export async function listCampaigns() {
  const { data } = await api.get<Paginated<Campaign>>("/campaigns/", { params: { page_size: 100 } });
  return data.results;
}
export async function getCampaign(id: number) {
  const { data } = await api.get<Campaign & { audience_size: number; recipients: { customer_name: string | null; contact: string; status: string }[] }>(`/campaigns/${id}/`);
  return data;
}
export async function createCampaign(input: { name: string; channel: string; subject?: string; body: string }) {
  const { data } = await api.post<Campaign>("/campaigns/", input);
  return data;
}
export async function sendCampaign(id: number) {
  const { data } = await api.post<Campaign & { sent: number }>(`/campaigns/${id}/send/`);
  return data;
}
