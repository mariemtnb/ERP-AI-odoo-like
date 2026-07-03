import { api } from "./client";

export interface ChatMessage {
  id: number;
  role: "user" | "assistant";
  content: string;
  tool_calls: { name: string; args: Record<string, unknown> }[] | null;
  pending_action: { action: string; details: Record<string, unknown> } | null;
  created_at: string;
}

export interface ChatResponse {
  conversation_id: number;
  type: "message" | "confirmation_required";
  message: ChatMessage;
}

export async function sendMessage(message: string, conversationId?: number) {
  const { data } = await api.post<ChatResponse>("/agent/chat/", {
    message,
    conversation_id: conversationId,
  });
  return data;
}

export async function sendApproval(conversationId: number, approve: boolean) {
  const { data } = await api.post<ChatResponse>("/agent/chat/", {
    conversation_id: conversationId,
    approve,
  });
  return data;
}
