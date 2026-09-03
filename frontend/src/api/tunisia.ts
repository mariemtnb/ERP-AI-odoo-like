import { api } from "./client";
import type {
  AccountMapping,
  Bank,
  BankAccount,
  BankTransaction,
  CompanyProfile,
  CustomerCredit,
  Installment,
  InstallmentPlan,
  InstrumentSummary,
  Journal,
  MatchSuggestion,
  Paginated,
  Payment,
  PaymentInstrument,
  PaymentMethod,
  ReconciliationMatch,
  ReconciliationReport,
  TreasuryDashboard,
} from "@/types";

/* ─────────────── localization settings ─────────────── */

export async function getCompanyProfile() {
  const { data } = await api.get<CompanyProfile>("/localization/profile");
  return data;
}

export async function updateCompanyProfile(input: Partial<CompanyProfile>) {
  const { data } = await api.patch<CompanyProfile>("/localization/profile", input);
  return data;
}

export async function listJournals() {
  const { data } = await api.get<{ results: Journal[] }>("/localization/journals");
  return data.results;
}

export async function listAccountMappings() {
  const { data } = await api.get<{ results: AccountMapping[]; required_keys: string[] }>(
    "/localization/mappings"
  );
  return data;
}

export async function updateAccountMappings(
  mappings: { key: string; account_code: string }[]
) {
  const { data } = await api.patch<{ results: AccountMapping[] }>(
    "/localization/mappings",
    { mappings }
  );
  return data.results;
}

export async function applyChartTemplate(template: "tunisia" | "default") {
  const { data } = await api.post<{ detail: string; results: AccountMapping[] }>(
    "/localization/chart-template",
    { template }
  );
  return data;
}

/* ─────────────── banks ─────────────── */

export async function listBanks(params: Record<string, unknown> = {}) {
  const { data } = await api.get<{ results: Bank[] }>("/banks", { params });
  return data.results;
}

export async function createBank(input: Partial<Bank>) {
  const { data } = await api.post<Bank>("/banks", input);
  return data;
}

export async function listBankAccounts(params: Record<string, unknown> = {}) {
  const { data } = await api.get<{ results: BankAccount[] }>("/bank-accounts", { params });
  return data.results;
}

export async function getBankAccount(id: number) {
  const { data } = await api.get<BankAccount>(`/bank-accounts/${id}`);
  return data;
}

export async function createBankAccount(input: Partial<BankAccount>) {
  const { data } = await api.post<BankAccount>("/bank-accounts", input);
  return data;
}

export async function updateBankAccount(id: number, input: Partial<BankAccount>) {
  const { data } = await api.patch<BankAccount>(`/bank-accounts/${id}`, input);
  return data;
}

/* ─────────────── bank statement lines ─────────────── */

export async function listBankTransactions(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<BankTransaction>>("/bank-transactions", { params });
  return data;
}

export async function getBankTransaction(id: number) {
  const { data } = await api.get<BankTransaction>(`/bank-transactions/${id}`);
  return data;
}

export async function createBankTransaction(input: Record<string, unknown>) {
  const { data } = await api.post<BankTransaction>("/bank-transactions", input);
  return data;
}

/** CSV upload - the backend parses it. */
export async function importStatementFile(bankAccountId: number, file: File) {
  const form = new FormData();
  form.append("bank_account_id", String(bankAccountId));
  form.append("file", file);
  const { data } = await api.post<{ imported: number; skipped: number; batch: string }>(
    "/bank-transactions/import",
    form,
    { headers: { "Content-Type": "multipart/form-data" } }
  );
  return data;
}

/**
 * XLSX is parsed in the browser and sent as rows, so the backend needs no
 * spreadsheet dependency.
 */
export async function importStatementRows(
  bankAccountId: number,
  rows: Record<string, unknown>[]
) {
  const { data } = await api.post<{ imported: number; skipped: number; batch: string }>(
    "/bank-transactions/import",
    { bank_account_id: bankAccountId, rows }
  );
  return data;
}

export async function previewStatementFile(file: File) {
  const form = new FormData();
  form.append("file", file);
  const { data } = await api.post<{ count: number; rows: Record<string, unknown>[] }>(
    "/bank-transactions/preview",
    form,
    { headers: { "Content-Type": "multipart/form-data" } }
  );
  return data;
}

/* ─────────────── reconciliation ─────────────── */

export async function getMatchSuggestions(transactionId: number) {
  const { data } = await api.get<{
    transaction: BankTransaction;
    suggestions: MatchSuggestion[];
  }>(`/reconciliation/${transactionId}/suggestions`);
  return data;
}

export async function matchTransaction(
  transactionId: number,
  input: {
    matchable_type: string;
    matchable_id?: number | null;
    amount: number;
    note?: string;
    apply_side_effects?: boolean;
  }
) {
  const { data } = await api.post<{
    match: ReconciliationMatch;
    transaction: BankTransaction;
  }>(`/reconciliation/${transactionId}/match`, input);
  return data;
}

export async function unmatch(matchId: number) {
  const { data } = await api.delete<BankTransaction>(`/reconciliation/matches/${matchId}`);
  return data;
}

export async function disputeTransaction(transactionId: number, reason: string) {
  const { data } = await api.post<BankTransaction>(
    `/reconciliation/${transactionId}/dispute`,
    { reason }
  );
  return data;
}

export async function getReconciliationReport(params: {
  bank_account: number;
  from?: string;
  to?: string;
}) {
  const { data } = await api.get<ReconciliationReport>("/reconciliation/report", { params });
  return data;
}

/* ─────────────── instruments (cheques & effets) ─────────────── */

export async function listInstruments(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<PaymentInstrument>>("/instruments", { params });
  return data;
}

export async function getInstrument(id: number) {
  const { data } = await api.get<PaymentInstrument>(`/instruments/${id}`);
  return data;
}

export async function getInstrumentSummary() {
  const { data } = await api.get<InstrumentSummary>("/instruments/summary");
  return data;
}

export async function createInstrument(input: Record<string, unknown>) {
  const { data } = await api.post<PaymentInstrument>("/instruments", input);
  return data;
}

type InstrumentAction =
  | "receive"
  | "issue"
  | "deposit"
  | "pending"
  | "clear"
  | "bounce"
  | "settle"
  | "cancel";

export async function instrumentAction(
  id: number,
  action: InstrumentAction,
  payload: Record<string, unknown> = {}
) {
  const { data } = await api.post<PaymentInstrument>(`/instruments/${id}/${action}`, payload);
  return data;
}

export async function attachToInstrument(id: number, file: File) {
  const form = new FormData();
  form.append("file", file);
  const { data } = await api.post(`/instruments/${id}/attachments`, form, {
    headers: { "Content-Type": "multipart/form-data" },
  });
  return data;
}

/* ─────────────── installments ─────────────── */

export async function listInstallmentPlans(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<InstallmentPlan>>("/installment-plans", { params });
  return data;
}

export async function getInstallmentPlan(id: number) {
  const { data } = await api.get<InstallmentPlan>(`/installment-plans/${id}`);
  return data;
}

export interface CreatePlanInput {
  reference_type: "sale" | "purchase";
  reference_id: number;
  total_amount: number;
  installment_count?: number;
  frequency?: string;
  start_date?: string;
  down_payment?: number;
  notes?: string;
  installments?: { due_date: string; amount: number }[];
}

export async function createInstallmentPlan(input: CreatePlanInput) {
  const { data } = await api.post<InstallmentPlan>("/installment-plans", input);
  return data;
}

export async function cancelInstallmentPlan(id: number, reason?: string) {
  const { data } = await api.post<InstallmentPlan>(`/installment-plans/${id}/cancel`, { reason });
  return data;
}

export async function getPlanHistory(id: number) {
  const { data } = await api.get<Paginated<Payment>>(`/installment-plans/${id}/history`);
  return data;
}

export async function listOverdueInstallments(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<Installment>>("/installments/overdue", { params });
  return data;
}

export async function payInstallment(
  id: number,
  input: {
    amount: number;
    method: PaymentMethod;
    bank_account_id?: number | null;
    instrument_id?: number | null;
    date?: string;
    reference?: string;
  }
) {
  const { data } = await api.post<{
    payment: Payment;
    installment: Installment;
    plan: InstallmentPlan;
  }>(`/installments/${id}/pay`, input);
  return data;
}

export async function getCustomerCredit(customerId: number) {
  const { data } = await api.get<CustomerCredit>(`/customers/${customerId}/credit`);
  return data;
}

/* ─────────────── payments & treasury ─────────────── */

export async function listPayments(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<Payment>>("/payments", { params });
  return data;
}

export async function createPayment(input: Record<string, unknown>) {
  const { data } = await api.post<Payment>("/payments", input);
  return data;
}

export async function getTreasuryDashboard(params: Record<string, unknown> = {}) {
  const { data } = await api.get<TreasuryDashboard>("/dashboard/treasury", { params });
  return data;
}

/** Download the reconciliation statement as a PDF. */
export async function downloadReconciliationPdf(params: {
  bank_account: number;
  from?: string;
  to?: string;
}) {
  const { data } = await api.get("/reconciliation/report", {
    params: { ...params, export: "pdf" },
    responseType: "blob",
  });
  const href = URL.createObjectURL(data);
  const a = document.createElement("a");
  a.href = href;
  a.download = "bank_reconciliation.pdf";
  a.click();
  URL.revokeObjectURL(href);
}
