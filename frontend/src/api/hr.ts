import { api } from "./client";
import type { Paginated } from "@/types";

export interface EmployeeLite {
  id: number;
  full_name: string;
  code: string;
  job_title: string;
}

export interface Attendance {
  id: number;
  employee: number;
  employee_name: string;
  work_date: string;
  check_in: string | null;
  check_out: string | null;
  hours: string;
  note: string;
}

export interface LeaveRequest {
  id: number;
  employee: number;
  employee_name: string;
  type: string;
  start_date: string;
  end_date: string;
  days: string;
  reason: string;
  status: "pending" | "approved" | "rejected";
  decided_by_email: string | null;
}

export interface ExpenseClaim {
  id: number;
  employee: number;
  employee_name: string;
  claim_date: string;
  category: string;
  amount: string;
  description: string;
  status: "pending" | "approved" | "rejected" | "reimbursed";
  decided_by_email: string | null;
}

export interface LeaveBalance {
  year: number;
  allowance: number;
  used: number;
  remaining: number;
}

export async function listEmployees() {
  const { data } = await api.get<{ results: EmployeeLite[] }>("/employees/", { params: { page_size: 200 } });
  return data.results;
}

// attendance
export async function listAttendance(employee: number) {
  const { data } = await api.get<Paginated<Attendance>>("/hr/attendance/", { params: { employee, page_size: 30 } });
  return data.results;
}
export async function clockIn(employee: number) {
  const { data } = await api.post<Attendance>("/hr/attendance/clock-in/", { employee });
  return data;
}
export async function clockOut(employee: number) {
  const { data } = await api.post<Attendance>("/hr/attendance/clock-out/", { employee });
  return data;
}

// leave
export async function listLeave(employee: number) {
  const { data } = await api.get<Paginated<LeaveRequest>>("/hr/leave/", { params: { employee, page_size: 50 } });
  return data.results;
}
export async function leaveBalance(employee: number) {
  const { data } = await api.get<LeaveBalance>("/hr/leave/balance/", { params: { employee } });
  return data;
}
export async function requestLeave(input: { employee: number; type: string; start_date: string; end_date: string; reason?: string }) {
  const { data } = await api.post<LeaveRequest>("/hr/leave/", input);
  return data;
}
export async function decideLeave(id: number, decision: "approve" | "reject") {
  const { data } = await api.post<LeaveRequest>(`/hr/leave/${id}/${decision}/`);
  return data;
}

// expenses
export async function listExpenses(employee: number) {
  const { data } = await api.get<Paginated<ExpenseClaim>>("/hr/expenses/", { params: { employee, page_size: 50 } });
  return data.results;
}
export async function submitClaim(input: { employee: number; claim_date: string; category?: string; amount: number; description?: string }) {
  const { data } = await api.post<ExpenseClaim>("/hr/expenses/", input);
  return data;
}
export async function decideClaim(id: number, decision: "approve" | "reject" | "reimburse") {
  const { data } = await api.post<ExpenseClaim>(`/hr/expenses/${id}/${decision}/`);
  return data;
}
