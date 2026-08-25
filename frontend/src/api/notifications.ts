import { api } from "./client";
import type { Paginated } from "@/types";

export interface AppNotification {
  id: number;
  type: string;
  category: string;
  severity: "info" | "warning" | "critical";
  title: string;
  body: string;
  link: string;
  subject_type: string;
  subject_id: number | null;
  is_read: boolean;
  read_at: string | null;
  created_at: string;
}

export async function listNotifications(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<AppNotification>>("/notifications", { params });
  return data;
}

export async function getUnreadCount() {
  const { data } = await api.get<{ count: number }>("/notifications/unread-count");
  return data.count;
}

export async function markNotificationRead(id: number) {
  const { data } = await api.post<AppNotification>(`/notifications/${id}/read`, {});
  return data;
}

export async function markAllNotificationsRead() {
  const { data } = await api.post<{ updated: number }>("/notifications/read-all", {});
  return data.updated;
}

/** Managers/admins: generate the time-based notifications now. */
export async function scanNotifications() {
  const { data } = await api.post<{ created: Record<string, number> }>("/notifications/scan", {});
  return data.created;
}
