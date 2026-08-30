import { api } from "./client";

export interface ReportRow { label: string; value: number }
export interface ReportResult { group_by: string; measure: string; rows: ReportRow[]; total: number }
export interface SavedReport {
  id: number;
  name: string;
  source: string;
  group_by: string;
  measure: string;
  created_by_email: string | null;
}

export async function runReport(group_by: string, measure: string) {
  const { data } = await api.post<ReportResult>("/bi/run/", { group_by, measure });
  return data;
}
export async function listReports() {
  const { data } = await api.get<{ results: SavedReport[] }>("/bi/reports/");
  return data.results;
}
export async function saveReport(input: { name: string; group_by: string; measure: string }) {
  const { data } = await api.post<SavedReport>("/bi/reports/", input);
  return data;
}
export async function runSavedReport(id: number) {
  const { data } = await api.get<SavedReport & { rows: ReportRow[]; total: number }>(`/bi/reports/${id}/run/`);
  return data;
}
export async function deleteReport(id: number) {
  await api.delete(`/bi/reports/${id}/`);
}
