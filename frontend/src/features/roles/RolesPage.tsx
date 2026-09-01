import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { useI18n } from "@/lib/i18n";
import * as rolesApi from "@/api/roles";
import type { ManagedRole } from "@/api/roles";

export default function RolesPage() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const rolesQ = useQuery({ queryKey: ["roles"], queryFn: () => rolesApi.listRoles() });
  const modulesQ = useQuery({ queryKey: ["role-modules"], queryFn: () => rolesApi.listModules() });
  const [editing, setEditing] = useState<ManagedRole | "new" | null>(null);
  const refresh = () => qc.invalidateQueries({ queryKey: ["roles"] });

  const del = useMutation({
    mutationFn: (id: number) => rolesApi.deleteRole(id),
    onSuccess: refresh,
    onError: (e: any) => alert(firstError(e, "Could not delete the role.")),
  });

  const modules = modulesQ.data ?? [];
  const labelFor = useMemo(
    () => Object.fromEntries(modules.map((m) => [m.key, m.label])),
    [modules]
  );

  return (
    <div>
      <PageHead title={t("roles.title")} sub={t("roles.sub")}>
        <Button onClick={() => setEditing("new")}>{t("roles.new")}</Button>
      </PageHead>

      {editing && (
        <RoleDialog
          role={editing === "new" ? null : editing}
          modules={modules}
          onClose={() => setEditing(null)}
          onSaved={() => { setEditing(null); refresh(); }}
        />
      )}

      <div style={card}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead>
            <tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
              <Th>{t("roles.col.role")}</Th><Th>{t("roles.col.access")}</Th><Th>{t("roles.col.users")}</Th><Th></Th>
            </tr>
          </thead>
          <tbody>
            {(rolesQ.data ?? []).map((r) => (
              <tr key={r.key} style={{ borderTop: "1px solid var(--border)" }}>
                <Td>
                  <div style={{ color: "var(--text-strong)", fontWeight: 500 }}>{r.name}</div>
                  <div style={{ color: "var(--text-muted)", fontSize: 12 }}>{r.description || r.key}</div>
                </Td>
                <Td>
                  {r.is_system ? (
                    <Badge tone="emerald">{t("roles.fullAccess")}</Badge>
                  ) : r.modules.length === 0 ? (
                    <Badge tone="rose">{t("roles.noModules")}</Badge>
                  ) : (
                    <span style={{ display: "flex", flexWrap: "wrap", gap: 6, maxWidth: 460 }}>
                      {r.modules.map((m) => (
                        <Badge key={m} tone="neutral">{labelFor[m] ?? m}</Badge>
                      ))}
                    </span>
                  )}
                </Td>
                <Td>{r.user_count}</Td>
                <Td right>
                  {!r.is_system && (
                    <span style={{ display: "flex", gap: 6, justifyContent: "flex-end" }}>
                      <Button size="sm" variant="outline" onClick={() => setEditing(r)}>{t("common.edit")}</Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        disabled={r.user_count > 0}
                        onClick={() => r.id && del.mutate(r.id)}
                      >
                        {t("common.delete")}
                      </Button>
                    </span>
                  )}
                </Td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function RoleDialog({
  role, modules, onClose, onSaved,
}: {
  role: ManagedRole | null;
  modules: rolesApi.ModuleOption[];
  onClose: () => void;
  onSaved: () => void;
}) {
  const editing = !!role;
  const [name, setName] = useState(role?.name ?? "");
  const [description, setDescription] = useState(role?.description ?? "");
  const [picked, setPicked] = useState<Set<string>>(new Set(role?.modules ?? []));
  const [error, setError] = useState<string | null>(null);

  const toggle = (key: string) =>
    setPicked((prev) => {
      const next = new Set(prev);
      next.has(key) ? next.delete(key) : next.add(key);
      return next;
    });

  const save = useMutation({
    mutationFn: () => {
      const payload = { name, description, modules: [...picked] };
      return editing ? rolesApi.updateRole(role!.id!, payload) : rolesApi.createRole(payload);
    },
    onSuccess: onSaved,
    onError: (e: any) => setError(firstError(e, "Could not save the role.")),
  });

  const ok = name.trim().length > 0 && picked.size > 0;

  return (
    <div role="dialog" style={overlay} onClick={onClose}>
      <div onClick={(e) => e.stopPropagation()} style={dialog}>
        <h3 style={{ margin: "0 0 4px", color: "var(--text-strong)", font: "600 18px var(--font-sans)" }}>
          {editing ? "Edit role" : "New role"}
        </h3>
        <p style={{ margin: "0 0 16px", color: "var(--text-muted)", fontSize: 13 }}>
          Tick every module this role may open. Everything else stays hidden.
        </p>

        <Field label="Role name"><Input value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Cashier" /></Field>
        <Field label="Description (optional)"><Input value={description} onChange={(e) => setDescription(e.target.value)} /></Field>

        <div style={{ fontSize: 12, color: "var(--text-muted)", margin: "6px 0 8px", display: "flex", justifyContent: "space-between" }}>
          <span>Modules ({picked.size} selected)</span>
          <button type="button" onClick={() => setPicked(picked.size === modules.length ? new Set() : new Set(modules.map((m) => m.key)))}
            style={{ background: "none", border: "none", color: "var(--emerald-400)", cursor: "pointer", fontSize: 12 }}>
            {picked.size === modules.length ? "Clear all" : "Select all"}
          </button>
        </div>
        <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 6, maxHeight: 300, overflowY: "auto", padding: 2 }}>
          {modules.map((m) => {
            const on = picked.has(m.key);
            return (
              <label key={m.key} style={{
                display: "flex", alignItems: "center", gap: 8, padding: "8px 10px", borderRadius: 8, cursor: "pointer",
                border: `1px solid ${on ? "var(--emerald-400)" : "var(--border)"}`,
                background: on ? "var(--emerald-glow)" : "var(--surface-hover)",
              }}>
                <input type="checkbox" checked={on} onChange={() => toggle(m.key)} />
                <span style={{ fontSize: 13, color: "var(--text-body)" }}>{m.label}</span>
              </label>
            );
          })}
        </div>

        {error && <p style={{ color: "var(--rose-400)", fontSize: 13, margin: "10px 0 0" }}>{error}</p>}
        <div style={{ display: "flex", gap: 8, marginTop: 16 }}>
          <Button variant="outline" style={{ flex: 1 }} onClick={onClose}>Cancel</Button>
          <Button style={{ flex: 1 }} loading={save.isPending} disabled={!ok} onClick={() => save.mutate()}>
            {editing ? "Save" : "Create role"}
          </Button>
        </div>
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

const card: React.CSSProperties = { background: "var(--surface-card)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" };
const overlay: React.CSSProperties = { position: "fixed", inset: 0, background: "rgba(3,7,12,.72)", backdropFilter: "blur(6px)", WebkitBackdropFilter: "blur(6px)", display: "grid", placeItems: "center", zIndex: 60 };
const dialog: React.CSSProperties = { width: 560, maxWidth: "94vw", background: "var(--surface-card)", border: "1px solid var(--border)", borderRadius: 16, padding: 22, boxShadow: "0 24px 60px -12px rgba(0,0,0,.7)" };

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block", marginBottom: 12 }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
function Th({ children, right }: { children?: React.ReactNode; right?: boolean }) {
  return <th style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: right ? "right" : "left" }}>{children}</th>;
}
function Td({ children, right }: { children: React.ReactNode; right?: boolean }) {
  return <td style={{ padding: "10px 14px", textAlign: right ? "right" : "left", color: "var(--text-body)", verticalAlign: "top" }}>{children}</td>;
}
