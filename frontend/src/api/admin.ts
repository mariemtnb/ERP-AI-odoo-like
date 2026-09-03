import { api } from "./client";
import type { Paginated } from "@/types";

/* ─────────────── session context ─────────────── */

export interface MeContext {
  permissions: string[];
  features: Record<string, boolean>;
  /** Module allowlist for a custom role, or null when unrestricted. */
  modules: string[] | null;
  company: Company | null;
}

/** Effective permissions and enabled modules for the signed-in user. */
export async function getContext() {
  const { data } = await api.get<MeContext>("/me/context");
  return data;
}

/* ─────────────── organisation ─────────────── */

export interface Company {
  id: number;
  code: string;
  name: string;
  legal_name: string;
  parent_id: number | null;
  parent_name: string | null;
  currency: string;
  locale: string;
  timezone: string;
  is_default: boolean;
  is_active: boolean;
  branch_count: number | null;
}

export interface Branch {
  id: number;
  company_id: number;
  company_name: string | null;
  code: string;
  name: string;
  address: string;
  city: string;
  phone: string;
  warehouse_id: number | null;
  warehouse_name: string | null;
  is_default: boolean;
  is_active: boolean;
}

export interface FiscalYear {
  id: number;
  company_id: number;
  name: string;
  starts_on: string;
  ends_on: string;
  status: "open" | "closed" | "locked";
  accepts_postings: boolean;
  closed_at: string | null;
  closed_by_email: string | null;
}

export interface NumberingSequence {
  id: number;
  company_id: number | null;
  key: string;
  name: string;
  format: string;
  prefix: string;
  next_number: number;
  reset_period: string;
  current_period: string;
  is_active: boolean;
  preview: string;
}

export async function listCompanies() {
  const { data } = await api.get<{ results: Company[] }>("/admin/companies");
  return data.results;
}

export async function updateCompany(id: number, input: Partial<Company>) {
  const { data } = await api.patch<Company>(`/admin/companies/${id}`, input);
  return data;
}

export async function listBranches() {
  const { data } = await api.get<{ results: Branch[] }>("/admin/branches");
  return data.results;
}

export async function listFiscalYears() {
  const { data } = await api.get<{ results: FiscalYear[] }>("/admin/fiscal-years");
  return data.results;
}

export async function setFiscalYearStatus(id: number, status: string) {
  const { data } = await api.patch<FiscalYear>(`/admin/fiscal-years/${id}`, { status });
  return data;
}

export async function listSequences() {
  const { data } = await api.get<{ results: NumberingSequence[] }>("/admin/sequences");
  return data.results;
}

export async function updateSequence(id: number, input: Partial<NumberingSequence>) {
  const { data } = await api.patch<NumberingSequence>(`/admin/sequences/${id}`, input);
  return data;
}

/* ─────────────── permissions ─────────────── */

export interface Permission {
  id: number;
  key: string;
  name: string;
  module: string;
  description: string;
  is_approval: boolean;
}

export interface PermissionGroup {
  id: number;
  key: string;
  name: string;
  description: string;
  permissions: string[];
}

export interface Role {
  id: number;
  key: string;
  name: string;
  description: string;
  parent_id: number | null;
  parent_key: string | null;
  is_system: boolean;
  level: number;
  permissions?: string[];
}

export async function listPermissions() {
  const { data } = await api.get<{ results: Permission[]; groups: PermissionGroup[] }>(
    "/admin/permissions"
  );
  return data;
}

export async function listRoles() {
  const { data } = await api.get<{ results: Role[] }>("/admin/roles");
  return data.results;
}

export async function createRole(input: Partial<Role>) {
  const { data } = await api.post<Role>("/admin/roles", input);
  return data;
}

export async function setRolePermissions(id: number, permissions: string[]) {
  const { data } = await api.patch<Role>(`/admin/roles/${id}/permissions`, { permissions });
  return data;
}

export async function deleteRole(id: number) {
  await api.delete(`/admin/roles/${id}`);
}

export interface UserPermissionOverride {
  id: number;
  user_id: number;
  permission_key: string | null;
  allow: boolean;
  starts_at: string | null;
  expires_at: string | null;
  is_active: boolean;
  reason: string;
  granted_by_email: string | null;
  created_at: string;
}

export interface UserPermissionsView {
  user_id: number;
  email: string;
  role: string;
  effective: string[];
  overrides: UserPermissionOverride[];
  object_rules: unknown[];
}

export async function getUserPermissions(userId: number) {
  const { data } = await api.get<UserPermissionsView>(`/admin/users/${userId}/permissions`);
  return data;
}

export async function grantPermission(
  userId: number,
  input: { permission: string; allow?: boolean; expires_at?: string | null; reason?: string }
) {
  const { data } = await api.post<UserPermissionOverride>(
    `/admin/users/${userId}/permissions`,
    input
  );
  return data;
}

export async function revokePermission(userId: number, permission: string, reason?: string) {
  await api.delete(`/admin/users/${userId}/permissions`, {
    data: { permission, reason },
  });
}

export interface PermissionAuditRow {
  id: number;
  action: string;
  actor_email: string | null;
  target_user_email: string | null;
  role_key: string | null;
  permission_key: string;
  detail: string;
  reason: string;
  ip: string;
  created_at: string;
}

export async function listPermissionAudit(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<PermissionAuditRow>>("/admin/permission-audit", {
    params,
  });
  return data;
}

/* ─────────────── audit trail ─────────────── */

export interface AuditRow {
  id: number;
  event: string;
  auditable_type: string;
  auditable_id: number | null;
  label: string;
  user_id: number | null;
  user_email: string | null;
  actor: "user" | "agent" | "system";
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  changed_fields: string[] | null;
  summary: string;
  reason: string;
  ip: string;
  user_agent: string;
  url: string;
  method: string;
  batch_id: string;
  created_at: string;
}

export async function listAudit(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<AuditRow>>("/admin/audit", { params });
  return data;
}

/** Activity timeline for one record - any authenticated user. */
export async function getTimeline(type: string, id: number) {
  const { data } = await api.get<Paginated<AuditRow>>(`/timeline/${type}/${id}`);
  return data;
}

export function downloadAuditCsv() {
  return api.get("/admin/audit/export", { responseType: "blob" }).then(({ data }) => {
    const href = URL.createObjectURL(data);
    const a = document.createElement("a");
    a.href = href;
    a.download = "audit_trail.csv";
    a.click();
    URL.revokeObjectURL(href);
  });
}

/* ─────────────── feature flags ─────────────── */

export interface FeatureFlag {
  id: number;
  key: string;
  name: string;
  description: string;
  enabled: boolean;
  is_locked: boolean;
  company_id: number | null;
}

export async function listFeatures() {
  const { data } = await api.get<{ results: FeatureFlag[] }>("/admin/features");
  return data.results;
}

export async function setFeature(id: number, enabled: boolean) {
  const { data } = await api.patch<FeatureFlag>(`/admin/features/${id}`, { enabled });
  return data;
}
