import { api } from "./client";
import type {
  Account,
  IncomeStatement,
  JournalEntry,
  Paginated,
  TrialBalance,
} from "@/types";

export async function listAccounts() {
  const { data } = await api.get<{ results: Account[] }>("/accounting/accounts/");
  return data.results;
}

export async function listJournalEntries(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<JournalEntry>>("/accounting/entries/", { params });
  return data;
}

export async function getTrialBalance(params: { from?: string; to?: string } = {}) {
  const { data } = await api.get<TrialBalance>("/accounting/trial-balance/", { params });
  return data;
}

export async function getIncomeStatement(params: { from?: string; to?: string } = {}) {
  const { data } = await api.get<IncomeStatement>("/accounting/income-statement/", { params });
  return data;
}

export interface ManualEntryInput {
  entry_date?: string;
  memo?: string;
  lines: { account: string; debit?: number; credit?: number; label?: string }[];
}

export async function postJournalEntry(input: ManualEntryInput) {
  const { data } = await api.post<JournalEntry>("/accounting/entries/", input);
  return data;
}

async function downloadBlob(url: string, params: Record<string, string>, filename: string) {
  const { data } = await api.get(url, { params, responseType: "blob" });
  const href = URL.createObjectURL(data);
  const a = document.createElement("a");
  a.href = href;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(href);
}

export function downloadStatementPdf(
  kind: "trial-balance" | "income-statement",
  params: { from?: string; to?: string } = {}
) {
  return downloadBlob(
    `/accounting/${kind}/`,
    { ...(params as Record<string, string>), export: "pdf" },
    `${kind.replace("-", "_")}.pdf`
  );
}
