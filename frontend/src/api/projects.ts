import { api } from "./client";
import type { Paginated } from "@/types";

export interface Project {
  id: number;
  name: string;
  customer: number | null;
  customer_name: string | null;
  budget_hours: string | null;
  status: "active" | "closed";
  logged_hours: string;
}
export interface ProjectTask {
  id: number;
  project: number;
  name: string;
  estimate_hours: string | null;
  status: string;
  logged_hours: string;
}
export interface Timesheet {
  id: number;
  task: number | null;
  task_name: string | null;
  user_email: string | null;
  work_date: string;
  hours: string;
  billable: boolean;
  note: string;
}
export interface Summary {
  project: number;
  name: string;
  budget_hours: number | null;
  logged_hours: number;
  billable_hours: number;
  non_billable_hours: number;
  remaining_hours: number | null;
  over_budget: boolean;
  by_task: { task: number; name: string; estimate_hours: number | null; logged_hours: number }[];
}

export async function listProjectsApi() {
  const { data } = await api.get<{ results: Project[] }>("/projects/");
  return data.results;
}
export async function createProject(input: { name: string; customer?: number | null; budget_hours?: number | null }) {
  const { data } = await api.post<Project>("/projects/", input);
  return data;
}
export async function getProjectDetail(id: number) {
  const { data } = await api.get<Project & { tasks: ProjectTask[] }>(`/projects/${id}/`);
  return data;
}
export async function getSummary(id: number) {
  const { data } = await api.get<Summary>(`/projects/${id}/summary/`);
  return data;
}
export async function addTask(projectId: number, name: string, estimate_hours?: number | null) {
  const { data } = await api.post<ProjectTask>(`/projects/${projectId}/tasks/`, { name, estimate_hours });
  return data;
}
export async function listTimesheets(projectId: number) {
  const { data } = await api.get<Paginated<Timesheet>>(`/projects/${projectId}/timesheets/`, { params: { page_size: 50 } });
  return data.results;
}
export async function logTime(projectId: number, input: { task?: number | null; work_date: string; hours: number; billable: boolean; note?: string }) {
  const { data } = await api.post<Timesheet>(`/projects/${projectId}/timesheets/`, input);
  return data;
}
