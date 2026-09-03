import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { useAuth } from "@/features/auth/AuthContext";
import { useI18n } from "@/lib/i18n";
import * as usersApi from "@/api/users";
import * as rolesApi from "@/api/roles";
import type { User } from "@/types";

export default function UsersPage() {
  const { user } = useAuth();
  const { t } = useI18n();
  const SYSTEM_LABEL: Record<string, string> = {
    super_admin: t("role.super_admin"), admin: t("role.admin"), manager: t("role.manager"), employee: t("role.employee"),
  };
  const isSuper = user?.role === "super_admin";
  const qc = useQueryClient();
  const usersQ = useQuery({ queryKey: ["users"], queryFn: () => usersApi.listUsers() });
  // Custom roles a user can be assigned to, alongside the built-ins.
  const rolesQ = useQuery({ queryKey: ["roles"], queryFn: () => rolesApi.listRoles() });
  const customRoles = (rolesQ.data ?? []).filter((r) => !r.is_system);
  const roleLabel = (key: string) =>
    SYSTEM_LABEL[key] ?? customRoles.find((r) => r.key === key)?.name ?? key;
  const [editing, setEditing] = useState<User | "new" | null>(null);
  const refresh = () => qc.invalidateQueries({ queryKey: ["users"] });

  const deactivate = useMutation({ mutationFn: (id: number) => usersApi.deactivateUser(id), onSuccess: refresh });
  const reactivate = useMutation({ mutationFn: (id: number) => usersApi.updateUser(id, { is_active: true }), onSuccess: refresh });

  // Whether the current user is allowed to edit a given account (backend also enforces).
  const canManage = (u: User) => isSuper || !["admin", "super_admin"].includes(u.role);

  return (
    <div>
      <PageHead title={t("users.title")} sub={t("users.sub")}>
        <Button onClick={() => setEditing("new")}>{t("users.new")}</Button>
      </PageHead>

      {editing && (
        <UserDialog
          user={editing === "new" ? null : editing}
          isSuper={isSuper}
          customRoles={customRoles}
          onClose={() => setEditing(null)}
          onSaved={() => { setEditing(null); refresh(); }}
        />
      )}

      <div style={{ background: "var(--surface-card)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
            <Th>{t("users.col.email")}</Th><Th>{t("users.col.name")}</Th><Th>{t("users.col.role")}</Th><Th>{t("users.col.status")}</Th><Th></Th>
          </tr></thead>
          <tbody>
            {(usersQ.data ?? []).map((u) => (
              <tr key={u.id} style={{ borderTop: "1px solid var(--border)" }}>
                <Td>{u.email}{u.id === user?.id && <span style={{ color: "var(--text-muted)" }}> · {t("users.you")}</span>}</Td>
                <Td>{[u.first_name, u.last_name].filter(Boolean).join(" ") || "-"}</Td>
                <Td><Badge tone={u.role}>{roleLabel(u.role)}</Badge></Td>
                <Td><Badge tone={u.is_active ? "green" : "red"}>{u.is_active ? t("users.active") : t("users.inactive")}</Badge></Td>
                <Td right>
                  <span style={{ display: "flex", gap: 6, justifyContent: "flex-end" }}>
                    {canManage(u) && <Button size="sm" variant="outline" onClick={() => setEditing(u)}>{t("common.edit")}</Button>}
                    {canManage(u) && u.id !== user?.id && (
                      u.is_active
                        ? <Button size="sm" variant="ghost" onClick={() => deactivate.mutate(u.id)}>{t("users.deactivate")}</Button>
                        : <Button size="sm" variant="ghost" onClick={() => reactivate.mutate(u.id)}>{t("users.reactivate")}</Button>
                    )}
                  </span>
                </Td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function UserDialog({ user, isSuper, customRoles, onClose, onSaved }: { user: User | null; isSuper: boolean; customRoles: rolesApi.ManagedRole[]; onClose: () => void; onSaved: () => void }) {
  const editing = !!user;
  const [email, setEmail] = useState(user?.email ?? "");
  const [first, setFirst] = useState(user?.first_name ?? "");
  const [last, setLast] = useState(user?.last_name ?? "");
  const [role, setRole] = useState<string>(user?.role ?? "employee");
  const [password, setPassword] = useState("");
  const [resetPw, setResetPw] = useState("");
  const [error, setError] = useState<string | null>(null);

  // Built-in roles (admin tiers only for a super admin) plus every custom role.
  const roleOptions: { value: string; label: string }[] = [
    { value: "employee", label: "Employee" },
    { value: "manager", label: "Manager" },
    ...(isSuper ? [{ value: "admin", label: "Admin" }, { value: "super_admin", label: "Super admin" }] : []),
    ...customRoles.map((r) => ({ value: r.key, label: r.name })),
  ];

  const save = useMutation({
    mutationFn: () => {
      if (editing) {
        return usersApi.updateUser(user!.id, { email, first_name: first, last_name: last, role });
      }
      return usersApi.createUser({ email, first_name: first, last_name: last, role, password });
    },
    onSuccess: onSaved,
    onError: (e: any) => setError(firstError(e, "Could not save the user.")),
  });
  const resetPwM = useMutation({
    mutationFn: () => usersApi.resetUserPassword(user!.id, resetPw),
    onSuccess: () => { setResetPw(""); setError(null); alert("Password reset."); },
    onError: (e: any) => setError(firstError(e, "Could not reset the password.")),
  });

  const ok = email.trim() && (editing || password.length >= 8);

  return (
    <div role="dialog" style={{ position: "fixed", inset: 0, background: "rgba(3,7,12,.72)", backdropFilter: "blur(6px)", WebkitBackdropFilter: "blur(6px)", display: "grid", placeItems: "center", zIndex: 60 }} onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} style={{ width: 460, maxWidth: "92vw", background: "var(--surface-card)", border: "1px solid var(--border)", borderRadius: 16, padding: 22, boxShadow: "0 24px 60px -12px rgba(0,0,0,.7)" }}>
        <h3 style={{ margin: "0 0 14px", color: "var(--text-strong)", font: "600 18px var(--font-sans)" }}>
          {editing ? "Edit user" : "New user"}
        </h3>
        <Field label="Email"><Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} /></Field>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
          <Field label="First name"><Input value={first} onChange={(e) => setFirst(e.target.value)} /></Field>
          <Field label="Last name"><Input value={last} onChange={(e) => setLast(e.target.value)} /></Field>
        </div>
        <Field label="Role">
          <select value={role} onChange={(e) => setRole(e.target.value)} style={selectStyle}>
            {roleOptions.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
          </select>
          {!isSuper && <span style={{ fontSize: 12, color: "var(--text-muted)" }}>Only a super admin can assign admin roles.</span>}
        </Field>
        {!editing && <Field label="Temporary password (min 8)"><Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} /></Field>}

        {error && <p style={{ color: "var(--rose-400)", fontSize: 13, margin: "4px 0 10px" }}>{error}</p>}
        <div style={{ display: "flex", gap: 8, marginTop: 8 }}>
          <Button variant="outline" style={{ flex: 1 }} onClick={onClose}>Cancel</Button>
          <Button style={{ flex: 1 }} loading={save.isPending} disabled={!ok} onClick={() => save.mutate()}>{editing ? "Save" : "Create"}</Button>
        </div>

        {editing && (
          <div style={{ marginTop: 18, paddingTop: 16, borderTop: "1px solid var(--border)" }}>
            <div style={{ fontSize: 13, color: "var(--text-muted)", marginBottom: 8 }}>Reset this user's password</div>
            <div style={{ display: "flex", gap: 8 }}>
              <Input type="password" placeholder="New password (min 8)" value={resetPw} onChange={(e) => setResetPw(e.target.value)} style={{ flex: 1 }} />
              <Button variant="outline" loading={resetPwM.isPending} disabled={resetPw.length < 8} onClick={() => resetPwM.mutate()}>Reset</Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

function firstError(e: any, fallback: string): string {
  const d = e?.response?.data;
  if (typeof d?.detail === "string") return d.detail;
  const k = d && Object.keys(d).find((key) => Array.isArray(d[key]));
  if (k) return d[k][0];
  return fallback;
}

const selectStyle: React.CSSProperties = { height: 38, width: "100%", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 10px" };
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block", marginBottom: 12 }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
function Th({ children, right }: { children?: React.ReactNode; right?: boolean }) {
  return <th style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: right ? "right" : "left" }}>{children}</th>;
}
function Td({ children, right }: { children: React.ReactNode; right?: boolean }) {
  return <td style={{ padding: "10px 14px", textAlign: right ? "right" : "left", color: "var(--text-body)" }}>{children}</td>;
}
