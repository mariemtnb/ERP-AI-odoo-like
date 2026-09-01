import { api } from "./client";
import type { Paginated } from "@/types";

/* ─────────────── types ─────────────── */

export interface Employee {
  id: number;
  code: string;
  first_name: string;
  last_name: string;
  full_name: string;
  job_title: string;
  department: string;
  base_salary: string;
  head_of_family: boolean;
  dependent_children: number;
  currency: string;
  hire_date: string | null;
  phone: string;
  email: string;
  rib: string;
  bank_account_id: number | null;
  is_active: boolean;
  outstanding_advance: string;
  notes: string;
}

export interface EmployeeAdvance {
  id: number;
  number: string;
  employee_id: number;
  employee_name: string | null;
  amount: string;
  request_date: string;
  reason: string;
  method: string;
  status: "pending" | "paid" | "recovered" | "cancelled";
  paid_at: string | null;
  recovered_amount: string;
  remaining: string;
  journal_entry_number: string | null;
  created_at: string;
}

export interface PayslipLine {
  id: number;
  type: "earning" | "deduction";
  label: string;
  amount: string;
  is_bonus: boolean;
  employee_advance_id: number | null;
}

export interface Payslip {
  id: number;
  payroll_run_id: number;
  employee_id: number;
  employee_name: string | null;
  base_salary: string;
  earnings_total: string;
  deductions_total: string;
  advance_recovered: string;
  gross_pay: string;
  cnss_employee: string;
  cnss_employer: string;
  taxable_base: string;
  irpp: string;
  css: string;
  net_pay: string;
  status: string;
  lines?: PayslipLine[];
}

export interface PayrollRun {
  id: number;
  number: string;
  period_month: string;
  period_label: string;
  label: string;
  status: "draft" | "approved" | "paid";
  gross_total: string;
  net_total: string;
  employee_count: number;
  journal_entry_number: string | null;
  approved_at: string | null;
  paid_at: string | null;
  notes: string;
  created_at: string;
  payslips?: Payslip[];
}

/* ─────────────── employees ─────────────── */

export async function listEmployees(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<Employee>>("/employees", { params });
  return data;
}

export async function createEmployee(input: Record<string, unknown>) {
  const { data } = await api.post<Employee>("/employees", input);
  return data;
}

export async function updateEmployee(id: number, input: Record<string, unknown>) {
  const { data } = await api.patch<Employee>(`/employees/${id}`, input);
  return data;
}

/* ─────────────── advances ─────────────── */

export async function listAdvances(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<EmployeeAdvance>>("/advances", { params });
  return data;
}

export async function requestAdvance(input: {
  employee_id: number;
  amount: number;
  reason?: string;
  method?: string;
  bank_account_id?: number | null;
}) {
  const { data } = await api.post<EmployeeAdvance>("/advances", input);
  return data;
}

export async function payAdvance(id: number) {
  const { data } = await api.post<EmployeeAdvance>(`/advances/${id}/pay`, {});
  return data;
}

export async function cancelAdvance(id: number) {
  const { data } = await api.post<EmployeeAdvance>(`/advances/${id}/cancel`, {});
  return data;
}

/* ─────────────── pay runs ─────────────── */

export async function listRuns(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<PayrollRun>>("/payroll/runs", { params });
  return data;
}

export async function getRun(id: number) {
  const { data } = await api.get<PayrollRun>(`/payroll/runs/${id}`);
  return data;
}

export async function createRun(input: { period_month: string; label?: string }) {
  const { data } = await api.post<PayrollRun>("/payroll/runs", input);
  return data;
}

export async function addPayslipLine(
  payslipId: number,
  input: { type: string; label: string; amount: number; is_bonus?: boolean; employee_advance_id?: number | null }
) {
  const { data } = await api.post<Payslip>(`/payroll/payslips/${payslipId}/lines`, input);
  return data;
}

export async function removePayslipLine(lineId: number) {
  const { data } = await api.delete<Payslip>(`/payroll/lines/${lineId}`);
  return data;
}

export async function approveRun(id: number) {
  const { data } = await api.post<PayrollRun>(`/payroll/runs/${id}/approve`, {});
  return data;
}

export async function payRun(id: number, input: { method?: string; bank_account_id?: number | null }) {
  const { data } = await api.post<PayrollRun>(`/payroll/runs/${id}/pay`, input);
  return data;
}

/* ─────────────── owner profit ─────────────── */

export interface ProfitSummary {
  revenue: number;
  cost_of_goods_sold: number;
  gross_profit: number;
  gross_margin_pct: number;
  salaries: number;
  other_expenses: number;
  total_expenses: number;
  net_profit: number;
  net_margin_pct: number;
  expense_breakdown: { code: string; name: string; amount: number }[];
}

export interface BestProduct {
  sku: string;
  name: string;
  quantity_sold: number;
  revenue: number;
  margin: number;
  margin_pct: number;
}

export interface OwnerView {
  summary: ProfitSummary;
  best_products: BestProduct[];
}

export async function getOwnerProfit(params: { from?: string; to?: string } = {}) {
  const { data } = await api.get<OwnerView>("/owner/profit", { params });
  return data;
}
