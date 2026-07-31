import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, Lock, Save, ShieldCheck, ToggleLeft } from "lucide-react";
import {
  deleteRole, downloadAuditCsv, listAudit, listBranches, listCompanies,
  listFeatures, listFiscalYears, listPermissions, listRoles, listSequences,
  setFeature, setFiscalYearStatus, setRolePermissions, updateSequence,
  type AuditRow, type Role,
} from "@/api/admin";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Segmented } from "@/components/ui/segmented";
import { Select } from "@/components/ui/select";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { Tooltip } from "@/components/ui/tooltip";

export default function AdministrationPage() {
  const [tab, setTab] = useState("organisation");

  return (
    <div className="space-y-6">
      <p className="text-sm text-text-3">
        Administration — company structure, fiscal periods, document numbering, the
        permission model, the audit trail and which modules are switched on.
      </p>

      <Segmented
        id="admin-tab"
        value={tab}
        onChange={setTab}
        options={[
          { value: "organisation", label: "Organisation" },
          { value: "fiscal", label: "Fiscal & numbering" },
          { value: "permissions", label: "Roles & permissions" },
          { value: "audit", label: "Audit trail" },
          { value: "modules", label: "Modules" },
        ]}
      />

      {tab === "organisation" && <OrganisationTab />}
      {tab === "fiscal" && <FiscalTab />}
      {tab === "permissions" && <PermissionsTab />}
      {tab === "audit" && <AuditTab />}
      {tab === "modules" && <ModulesTab />}
    </div>
  );
}

/* ─────────────── organisation ─────────────── */

function OrganisationTab() {
  const { data: companies, isLoading } = useQuery({
    queryKey: ["admin-companies"],
    queryFn: listCompanies,
  });
  const { data: branches } = useQuery({ queryKey: ["admin-branches"], queryFn: listBranches });

  if (isLoading) return <TableSkeleton rows={4} />;

  return (
    <div className="space-y-6">
      <section className="space-y-2">
        <h3 className="text-sm font-semibold">Companies</h3>
        <Table>
          <THead>
            <tr>
              <Th>Code</Th>
              <Th>Name</Th>
              <Th>Parent</Th>
              <Th>Currency</Th>
              <Th>Timezone</Th>
              <Th>Branches</Th>
            </tr>
          </THead>
          <TBody>
            {(companies ?? []).map((c) => (
              <tr key={c.id}>
                <Td className="font-mono">{c.code}</Td>
                <Td>
                  <span className="font-medium">{c.name}</span>
                  {c.is_default && <Badge tone="emerald" className="ml-2">default</Badge>}
                </Td>
                <Td>{c.parent_name ?? "—"}</Td>
                <Td>{c.currency}</Td>
                <Td>{c.timezone}</Td>
                <Td>{c.branch_count ?? 0}</Td>
              </tr>
            ))}
          </TBody>
        </Table>
      </section>

      <section className="space-y-2">
        <h3 className="text-sm font-semibold">Branches</h3>
        <Table>
          <THead>
            <tr>
              <Th>Code</Th>
              <Th>Name</Th>
              <Th>Company</Th>
              <Th>City</Th>
              <Th>Warehouse</Th>
            </tr>
          </THead>
          <TBody>
            {(branches ?? []).map((b) => (
              <tr key={b.id}>
                <Td className="font-mono">{b.code}</Td>
                <Td>{b.name}</Td>
                <Td>{b.company_name}</Td>
                <Td>{b.city || "—"}</Td>
                <Td>{b.warehouse_name ?? "—"}</Td>
              </tr>
            ))}
          </TBody>
        </Table>
      </section>
    </div>
  );
}

/* ─────────────── fiscal years & numbering ─────────────── */

function FiscalTab() {
  const qc = useQueryClient();
  const [error, setError] = useState("");

  const { data: years, isLoading } = useQuery({
    queryKey: ["admin-fiscal-years"],
    queryFn: listFiscalYears,
  });
  const { data: sequences } = useQuery({
    queryKey: ["admin-sequences"],
    queryFn: listSequences,
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) =>
      setFiscalYearStatus(id, status),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin-fiscal-years"] }),
  });

  const sequenceMutation = useMutation({
    mutationFn: ({ id, format }: { id: number; format: string }) =>
      updateSequence(id, { format }),
    onSuccess: () => {
      setError("");
      qc.invalidateQueries({ queryKey: ["admin-sequences"] });
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not save."),
  });

  if (isLoading) return <TableSkeleton rows={4} />;

  return (
    <div className="space-y-6">
      <section className="space-y-2">
        <h3 className="text-sm font-semibold">Fiscal years</h3>
        <p className="text-sm text-text-3">
          Closing a period stops anything being backdated into books that have already
          been reported.
        </p>
        <Table>
          <THead>
            <tr>
              <Th>Year</Th>
              <Th>From</Th>
              <Th>To</Th>
              <Th>Status</Th>
              <Th>Closed by</Th>
              <Th />
            </tr>
          </THead>
          <TBody>
            {(years ?? []).map((y) => (
              <tr key={y.id}>
                <Td className="font-medium">{y.name}</Td>
                <Td>{y.starts_on}</Td>
                <Td>{y.ends_on}</Td>
                <Td>
                  <Badge tone={y.status === "open" ? "emerald" : y.status === "locked" ? "red" : "employee"}>
                    {y.status}
                  </Badge>
                </Td>
                <Td>{y.closed_by_email ?? "—"}</Td>
                <Td className="text-right">
                  <Select
                    value={y.status}
                    onChange={(e) => statusMutation.mutate({ id: y.id, status: e.target.value })}
                    className="max-w-32"
                  >
                    <option value="open">Open</option>
                    <option value="closed">Closed</option>
                    <option value="locked">Locked</option>
                  </Select>
                </Td>
              </tr>
            ))}
          </TBody>
        </Table>
      </section>

      <section className="space-y-2">
        <h3 className="text-sm font-semibold">Document numbering</h3>
        <p className="text-sm text-text-3">
          Tokens: <code>{"{PREFIX}"}</code> <code>{"{YYYY}"}</code> <code>{"{YY}"}</code>{" "}
          <code>{"{MM}"}</code> <code>{"{SEQ:4}"}</code>. The counter is reserved under a
          lock, so concurrent requests cannot mint the same number.
        </p>
        {error && <p className="text-sm text-danger">{error}</p>}
        <Table>
          <THead>
            <tr>
              <Th>Document</Th>
              <Th>Format</Th>
              <Th>Next</Th>
              <Th>Resets</Th>
              <Th>Preview</Th>
            </tr>
          </THead>
          <TBody>
            {(sequences ?? []).map((s) => (
              <tr key={s.id}>
                <Td className="font-medium">{s.name || s.key}</Td>
                <Td>
                  <Input
                    defaultValue={s.format}
                    className="max-w-48"
                    onBlur={(e) => {
                      if (e.target.value !== s.format) {
                        sequenceMutation.mutate({ id: s.id, format: e.target.value });
                      }
                    }}
                  />
                </Td>
                <Td className="tnum">{s.next_number}</Td>
                <Td>{s.reset_period}</Td>
                <Td className="font-mono text-xs">{s.preview}</Td>
              </tr>
            ))}
          </TBody>
        </Table>
      </section>
    </div>
  );
}

/* ─────────────── roles & permissions ─────────────── */

function PermissionsTab() {
  const qc = useQueryClient();
  const [selected, setSelected] = useState<Role | null>(null);
  const [draft, setDraft] = useState<Set<string>>(new Set());
  const [error, setError] = useState("");

  const { data: roles, isLoading } = useQuery({ queryKey: ["admin-roles"], queryFn: listRoles });
  const { data: catalogue } = useQuery({
    queryKey: ["admin-permissions"],
    queryFn: listPermissions,
  });

  const saveMutation = useMutation({
    mutationFn: () => setRolePermissions(selected!.id, [...draft]),
    onSuccess: () => {
      setError("");
      setSelected(null);
      qc.invalidateQueries({ queryKey: ["admin-roles"] });
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not save."),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteRole(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["admin-roles"] }),
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not delete."),
  });

  if (isLoading) return <TableSkeleton rows={4} />;

  // Group the catalogue by module so the editor is navigable.
  const permissions = catalogue?.results ?? [];
  const byModule = permissions.reduce<Record<string, typeof permissions>>((acc, p) => {
    (acc[p.module] ??= []).push(p);
    return acc;
  }, {});

  return (
    <div className="space-y-4">
      {error && <p className="text-sm text-danger">{error}</p>}
      <Table>
        <THead>
          <tr>
            <Th>Role</Th>
            <Th>Inherits</Th>
            <Th>Permissions</Th>
            <Th>Type</Th>
            <Th />
          </tr>
        </THead>
        <TBody>
          {(roles ?? []).map((r) => (
            <tr key={r.id}>
              <Td>
                <span className="font-medium">{r.name}</span>
                <p className="text-xs text-text-3">{r.description}</p>
              </Td>
              <Td>{r.parent_key ?? "—"}</Td>
              <Td className="tnum">{r.permissions?.length ?? 0}</Td>
              <Td>
                {r.is_system ? (
                  <Tooltip label="Built-in role — the users.role column depends on it">
                    <Badge tone="sky"><Lock className="mr-1 h-3 w-3" />built-in</Badge>
                  </Tooltip>
                ) : (
                  <Badge tone="employee">custom</Badge>
                )}
              </Td>
              <Td className="text-right">
                <Button
                  size="sm"
                  variant="secondary"
                  onClick={() => {
                    setError("");
                    setSelected(r);
                    setDraft(new Set(r.permissions ?? []));
                  }}
                >
                  <ShieldCheck className="h-3.5 w-3.5" /> Edit
                </Button>
                {!r.is_system && (
                  <Button
                    size="sm"
                    variant="ghost"
                    className="ml-1"
                    onClick={() => deleteMutation.mutate(r.id)}
                  >
                    Delete
                  </Button>
                )}
              </Td>
            </tr>
          ))}
        </TBody>
      </Table>

      <Dialog
        open={selected !== null}
        onClose={() => setSelected(null)}
        title={selected ? `Permissions — ${selected.name}` : "Permissions"}
        className="max-w-3xl"
      >
        <div className="space-y-4">
          <p className="text-sm text-text-2">
            Inherited permissions are not shown here: a role automatically holds
            everything its parent holds. Ticking a box grants it directly.
          </p>
          <div className="max-h-96 space-y-4 overflow-y-auto pr-2">
            {Object.entries(byModule).map(([module, perms]) => (
              <div key={module}>
                <p className="eyebrow mb-1">{module}</p>
                <div className="grid gap-1 sm:grid-cols-2">
                  {perms.map((p) => (
                    <label key={p.key} className="flex items-center gap-2 text-sm text-text-2">
                      <input
                        type="checkbox"
                        checked={draft.has(p.key)}
                        onChange={(e) => {
                          const next = new Set(draft);
                          e.target.checked ? next.add(p.key) : next.delete(p.key);
                          setDraft(next);
                        }}
                      />
                      <span className="font-mono text-xs">{p.key}</span>
                      {p.is_approval && <Badge tone="amber">approval</Badge>}
                    </label>
                  ))}
                </div>
              </div>
            ))}
          </div>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button
            className="w-full"
            onClick={() => saveMutation.mutate()}
            disabled={saveMutation.isPending}
          >
            <Save className="h-4 w-4" />
            {saveMutation.isPending ? "Saving…" : `Save ${draft.size} permission(s)`}
          </Button>
        </div>
      </Dialog>
    </div>
  );
}

/* ─────────────── audit trail ─────────────── */

function AuditTab() {
  const [actor, setActor] = useState("");
  const [detail, setDetail] = useState<AuditRow | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ["admin-audit", actor],
    queryFn: () => listAudit({ ...(actor ? { actor } : {}), page_size: 50 }),
  });

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="w-56 space-y-1.5">
          <Label htmlFor="audit-actor">Actor</Label>
          <Select id="audit-actor" value={actor} onChange={(e) => setActor(e.target.value)}>
            <option value="">Everyone</option>
            <option value="user">People</option>
            <option value="agent">AI assistant</option>
            <option value="system">System</option>
          </Select>
        </div>
        <Button variant="secondary" onClick={() => downloadAuditCsv()}>
          <Download className="h-4 w-4" /> Export CSV
        </Button>
      </div>

      {isLoading ? (
        <TableSkeleton rows={6} />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>When</Th>
              <Th>Record</Th>
              <Th>What happened</Th>
              <Th>Actor</Th>
              <Th>IP</Th>
            </tr>
          </THead>
          <TBody>
            {(data?.results ?? []).map((row) => (
              <tr key={row.id} className="cursor-pointer" onClick={() => setDetail(row)}>
                <Td className="whitespace-nowrap">
                  {new Date(row.created_at).toLocaleString()}
                </Td>
                <Td>
                  <span className="font-medium">{row.auditable_type}</span>
                  <span className="ml-1 text-xs text-text-3">{row.label}</span>
                </Td>
                <Td>{row.summary}</Td>
                <Td>
                  <Badge tone={row.actor === "agent" ? "violet" : "employee"}>
                    {row.actor === "agent" ? "AI" : row.user_email ?? row.actor}
                  </Badge>
                </Td>
                <Td className="font-mono text-xs">{row.ip || "—"}</Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <Dialog
        open={detail !== null}
        onClose={() => setDetail(null)}
        title={detail ? `${detail.auditable_type} — ${detail.event}` : "Audit entry"}
        className="max-w-2xl"
      >
        {detail && (
          <div className="space-y-3 text-sm">
            <div className="grid grid-cols-2 gap-2 text-text-2">
              <p>When: {new Date(detail.created_at).toLocaleString()}</p>
              <p>Who: {detail.user_email ?? detail.actor}</p>
              <p>IP: {detail.ip || "—"}</p>
              <p>Method: {detail.method || "—"}</p>
            </div>
            {detail.reason && <p className="text-text-2">Reason: {detail.reason}</p>}
            {detail.batch_id && (
              <p className="text-xs text-text-3">Bulk batch {detail.batch_id}</p>
            )}
            {detail.changed_fields && (
              <div>
                <Label>Changed</Label>
                <Table>
                  <THead>
                    <tr>
                      <Th>Field</Th>
                      <Th>Before</Th>
                      <Th>After</Th>
                    </tr>
                  </THead>
                  <TBody>
                    {detail.changed_fields.map((f) => (
                      <tr key={f}>
                        <Td className="font-mono text-xs">{f}</Td>
                        <Td>{String(detail.old_values?.[f] ?? "—")}</Td>
                        <Td>{String(detail.new_values?.[f] ?? "—")}</Td>
                      </tr>
                    ))}
                  </TBody>
                </Table>
              </div>
            )}
            <p className="break-all text-xs text-text-3">{detail.user_agent}</p>
          </div>
        )}
      </Dialog>
    </div>
  );
}

/* ─────────────── modules ─────────────── */

function ModulesTab() {
  const qc = useQueryClient();
  const [error, setError] = useState("");

  const { data, isLoading } = useQuery({ queryKey: ["admin-features"], queryFn: listFeatures });

  const mutation = useMutation({
    mutationFn: ({ id, enabled }: { id: number; enabled: boolean }) => setFeature(id, enabled),
    onSuccess: () => {
      setError("");
      qc.invalidateQueries({ queryKey: ["admin-features"] });
      qc.invalidateQueries({ queryKey: ["me-context"] });
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not change this module."),
  });

  if (isLoading) return <TableSkeleton rows={6} />;

  return (
    <div className="space-y-4">
      <p className="text-sm text-text-3">
        Switching a module off hides its navigation and makes its endpoints return 404 —
        no deployment required.
      </p>
      {error && <p className="text-sm text-danger">{error}</p>}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {(data ?? []).map((f) => (
          <div key={f.id} className="erp-card flex items-center justify-between gap-3 p-4">
            <div>
              <p className="text-sm font-medium">{f.name}</p>
              <p className="font-mono text-xs text-text-3">{f.key}</p>
            </div>
            {f.is_locked ? (
              <Tooltip label="Required module — cannot be switched off">
                <Badge tone="sky"><Lock className="h-3 w-3" /></Badge>
              </Tooltip>
            ) : (
              <Button
                size="sm"
                variant={f.enabled ? "secondary" : "ghost"}
                onClick={() => mutation.mutate({ id: f.id, enabled: !f.enabled })}
              >
                <ToggleLeft className="h-4 w-4" />
                {f.enabled ? "On" : "Off"}
              </Button>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
