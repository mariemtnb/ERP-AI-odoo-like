import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { CalendarPlus, HandCoins, Plus, UserPlus, Wallet } from "lucide-react";
import {
  addPayslipLine, approveRun, cancelAdvance, createEmployee, createRun, getRun,
  listAdvances, listEmployees, listRuns, payAdvance, payRun, requestAdvance,
  type EmployeeAdvance,
} from "@/api/payroll";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Segmented } from "@/components/ui/segmented";
import { Select } from "@/components/ui/select";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { formatTnd } from "@/lib/tnLabels";
import { useI18n } from "@/lib/i18n";

const STATUS_TONE: Record<string, string> = {
  pending: "employee", paid: "sky", recovered: "green", cancelled: "employee",
  draft: "employee", approved: "sky", completed: "green",
};

export default function PayrollPage() {
  const { t } = useI18n();
  const [tab, setTab] = useState("employees");

  return (
    <div className="space-y-6">
      <p className="text-sm text-text-3">
        {t("pay.sub")}
      </p>
      <Segmented
        id="payroll-tab"
        value={tab}
        onChange={setTab}
        options={[
          { value: "employees", label: t("pay.tab.employees") },
          { value: "advances", label: t("pay.tab.advances") },
          { value: "runs", label: t("pay.tab.runs") },
        ]}
      />
      {tab === "employees" && <EmployeesTab />}
      {tab === "advances" && <AdvancesTab />}
      {tab === "runs" && <RunsTab />}
    </div>
  );
}

/* ─────────────── employees ─────────────── */

const emptyEmp = { first_name: "", last_name: "", job_title: "", base_salary: "", head_of_family: false, dependent_children: "", phone: "", rib: "" };

function EmployeesTab() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(emptyEmp);
  const [error, setError] = useState("");

  const { data, isLoading } = useQuery({ queryKey: ["employees"], queryFn: () => listEmployees() });

  const create = useMutation({
    mutationFn: () => createEmployee({
      ...form,
      base_salary: Number(form.base_salary),
      dependent_children: Number(form.dependent_children || 0),
    }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["employees"] });
      setOpen(false);
      setForm(emptyEmp);
      setError("");
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("pay.couldNotSave")),
  });

  const set = (k: keyof typeof emptyEmp) => (e: { target: { value: string } }) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  if (isLoading) return <TableSkeleton rows={4} />;
  const rows = data?.results ?? [];

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button onClick={() => { setError(""); setOpen(true); }}>
          <UserPlus className="h-4 w-4" /> {t("pay.newEmployee")}
        </Button>
      </div>
      {rows.length === 0 ? (
        <EmptyState icon={Wallet} title={t("pay.noEmployees")} hint={t("pay.noEmployeesHint")} />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>{t("field.name")}</Th><Th>{t("pay.job")}</Th><Th className="text-right">{t("pay.baseSalary")}</Th>
              <Th className="text-right">{t("pay.advanceOwed")}</Th><Th>{t("common.status")}</Th>
            </tr>
          </THead>
          <TBody>
            {rows.map((e) => (
              <tr key={e.id}>
                <Td>
                  <span className="font-medium">{e.full_name}</span>
                  <span className="ml-2 text-xs text-text-3">{e.code}</span>
                </Td>
                <Td>{e.job_title || "-"}</Td>
                <Td className="text-right">{formatTnd(e.base_salary)}</Td>
                <Td className="text-right">
                  {Number(e.outstanding_advance) > 0 ? formatTnd(e.outstanding_advance) : "-"}
                </Td>
                <Td><Badge tone={e.is_active ? "green" : "red"}>{e.is_active ? t("common.active") : t("common.inactive")}</Badge></Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <Dialog open={open} onClose={() => setOpen(false)} title={t("pay.newEmployeeTitle")}>
        <form onSubmit={(ev: FormEvent) => { ev.preventDefault(); create.mutate(); }} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5"><Label htmlFor="e-fn">{t("field.firstName")}</Label>
              <Input id="e-fn" value={form.first_name} onChange={set("first_name")} required /></div>
            <div className="space-y-1.5"><Label htmlFor="e-ln">{t("field.lastName")}</Label>
              <Input id="e-ln" value={form.last_name} onChange={set("last_name")} /></div>
            <div className="space-y-1.5"><Label htmlFor="e-job">{t("pay.jobTitle")}</Label>
              <Input id="e-job" value={form.job_title} onChange={set("job_title")} /></div>
            <div className="space-y-1.5"><Label htmlFor="e-sal">{t("pay.baseSalary")}</Label>
              <Input id="e-sal" type="number" step="0.001" min="0" value={form.base_salary} onChange={set("base_salary")} required /></div>
            <div className="space-y-1.5"><Label htmlFor="e-kids">{t("pay.dependentChildren")}</Label>
              <Input id="e-kids" type="number" min="0" max="20" value={form.dependent_children} onChange={set("dependent_children")} /></div>
            <label className="col-span-2 flex items-center gap-2 text-sm text-text-2">
              <input type="checkbox" checked={form.head_of_family}
                onChange={(e) => setForm((f) => ({ ...f, head_of_family: e.target.checked }))} />
              {t("pay.headOfFamily")}
            </label>
            <div className="space-y-1.5"><Label htmlFor="e-ph">{t("field.phone")}</Label>
              <Input id="e-ph" value={form.phone} onChange={set("phone")} /></div>
            <div className="space-y-1.5"><Label htmlFor="e-rib">{t("pay.rib")}</Label>
              <Input id="e-rib" value={form.rib} onChange={set("rib")} /></div>
          </div>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={create.isPending}>
            {create.isPending ? t("common.saving") : t("pay.addEmployee")}
          </Button>
        </form>
      </Dialog>
    </div>
  );
}

/* ─────────────── advances ─────────────── */

function AdvancesTab() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ employee_id: "", amount: "", reason: "", method: "cash" });
  const [error, setError] = useState("");

  const { data, isLoading } = useQuery({ queryKey: ["advances"], queryFn: () => listAdvances() });
  const { data: emps } = useQuery({ queryKey: ["employees", "active"], queryFn: () => listEmployees({ active_only: true }) });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["advances"] });
    qc.invalidateQueries({ queryKey: ["employees"] });
  };

  const create = useMutation({
    mutationFn: () => requestAdvance({
      employee_id: Number(form.employee_id), amount: Number(form.amount),
      reason: form.reason, method: form.method,
    }),
    onSuccess: () => { invalidate(); setOpen(false); setForm({ employee_id: "", amount: "", reason: "", method: "cash" }); setError(""); },
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("pay.couldNotSave")),
  });
  const pay = useMutation({ mutationFn: (a: EmployeeAdvance) => payAdvance(a.id), onSuccess: invalidate });
  const cancel = useMutation({ mutationFn: (a: EmployeeAdvance) => cancelAdvance(a.id), onSuccess: invalidate });

  if (isLoading) return <TableSkeleton rows={4} />;
  const rows = data?.results ?? [];

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button onClick={() => { setError(""); setOpen(true); }}>
          <HandCoins className="h-4 w-4" /> {t("pay.newAdvance")}
        </Button>
      </div>
      {rows.length === 0 ? (
        <EmptyState icon={HandCoins} title={t("pay.noAdvances")} hint={t("pay.noAdvancesHint")} />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>{t("docs.col.number")}</Th><Th>{t("pay.employee")}</Th><Th>{t("inv.reason")}</Th>
              <Th className="text-right">{t("subs.amount")}</Th><Th className="text-right">{t("pay.leftToRecover")}</Th>
              <Th>{t("common.status")}</Th><Th />
            </tr>
          </THead>
          <TBody>
            {rows.map((a) => (
              <tr key={a.id}>
                <Td className="font-medium">{a.number}</Td>
                <Td>{a.employee_name}</Td>
                <Td>{a.reason || "-"}</Td>
                <Td className="text-right">{formatTnd(a.amount)}</Td>
                <Td className="text-right">{a.status === "paid" ? formatTnd(a.remaining) : "-"}</Td>
                <Td><Badge tone={STATUS_TONE[a.status] ?? "employee"}>{t(`pay.status.${a.status}`)}</Badge></Td>
                <Td className="text-right">
                  {a.status === "pending" && (
                    <>
                      <Button size="sm" onClick={() => pay.mutate(a)}>{t("pay.pay")}</Button>
                      <Button size="sm" variant="ghost" className="ml-1" onClick={() => cancel.mutate(a)}>{t("common.cancel")}</Button>
                    </>
                  )}
                </Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <Dialog open={open} onClose={() => setOpen(false)} title={t("pay.newAdvanceTitle")}>
        <form onSubmit={(ev: FormEvent) => { ev.preventDefault(); create.mutate(); }} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="a-emp">{t("pay.employee")}</Label>
            <Select id="a-emp" value={form.employee_id} onChange={(e) => setForm((f) => ({ ...f, employee_id: e.target.value }))} required>
              <option value="">{t("pay.choose")}</option>
              {(emps?.results ?? []).map((e) => <option key={e.id} value={e.id}>{e.full_name}</option>)}
            </Select>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5"><Label htmlFor="a-amt">{t("subs.amount")}</Label>
              <Input id="a-amt" type="number" step="0.001" min="0" value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))} required /></div>
            <div className="space-y-1.5"><Label htmlFor="a-method">{t("pay.method")}</Label>
              <Select id="a-method" value={form.method} onChange={(e) => setForm((f) => ({ ...f, method: e.target.value }))}>
                <option value="cash">{t("pay.methodCash")}</option><option value="bank_transfer">{t("pay.methodBank")}</option>
              </Select></div>
          </div>
          <div className="space-y-1.5"><Label htmlFor="a-reason">{t("inv.reason")}</Label>
            <Input id="a-reason" value={form.reason} onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))} placeholder={t("pay.reasonPlaceholder")} /></div>
          <p className="text-xs text-text-3">{t("pay.advanceNote")}</p>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={create.isPending}>
            {create.isPending ? t("common.saving") : t("pay.recordAdvance")}
          </Button>
        </form>
      </Dialog>
    </div>
  );
}

/* ─────────────── pay runs ─────────────── */

function RunsTab() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [month, setMonth] = useState(new Date().toISOString().slice(0, 7));
  const [detailId, setDetailId] = useState<number | null>(null);
  const [error, setError] = useState("");

  const { data, isLoading } = useQuery({ queryKey: ["payroll-runs"], queryFn: () => listRuns() });

  const create = useMutation({
    mutationFn: () => createRun({ period_month: `${month}-01` }),
    onSuccess: (run) => {
      qc.invalidateQueries({ queryKey: ["payroll-runs"] });
      setOpen(false); setError(""); setDetailId(run.id);
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("pay.createRunError")),
  });

  if (isLoading) return <TableSkeleton rows={4} />;
  const rows = data?.results ?? [];

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button onClick={() => { setError(""); setOpen(true); }}>
          <CalendarPlus className="h-4 w-4" /> {t("pay.newRun")}
        </Button>
      </div>
      {rows.length === 0 ? (
        <EmptyState icon={Wallet} title={t("pay.noRuns")} hint={t("pay.noRunsHint")} />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>{t("pay.run")}</Th><Th>{t("pay.period")}</Th><Th className="text-right">{t("pay.staff")}</Th>
              <Th className="text-right">{t("pay.netTotal")}</Th><Th>{t("common.status")}</Th>
            </tr>
          </THead>
          <TBody>
            {rows.map((r) => (
              <tr key={r.id} className="cursor-pointer" onClick={() => setDetailId(r.id)}>
                <Td className="font-medium">{r.number}</Td>
                <Td>{r.period_label}</Td>
                <Td className="text-right">{r.employee_count}</Td>
                <Td className="text-right">{formatTnd(r.net_total)}</Td>
                <Td><Badge tone={STATUS_TONE[r.status] ?? "employee"}>{t(`pay.status.${r.status}`)}</Badge></Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <Dialog open={open} onClose={() => setOpen(false)} title={t("pay.newRun")}>
        <form onSubmit={(ev: FormEvent) => { ev.preventDefault(); create.mutate(); }} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="r-month">{t("pay.month")}</Label>
            <Input id="r-month" type="month" value={month} onChange={(e) => setMonth(e.target.value)} required />
          </div>
          <p className="text-xs text-text-3">{t("pay.runNote")}</p>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={create.isPending}>
            {create.isPending ? t("pay.creating") : t("pay.createRun")}
          </Button>
        </form>
      </Dialog>

      <RunDetail id={detailId} onClose={() => setDetailId(null)} />
    </div>
  );
}

function RunDetail({ id, onClose }: { id: number | null; onClose: () => void }) {
  const { t } = useI18n();
  const qc = useQueryClient();
  const [error, setError] = useState("");
  const [lineFor, setLineFor] = useState<number | null>(null);
  const [line, setLine] = useState({ type: "earning", label: "", amount: "", is_bonus: true });

  const { data: run } = useQuery({
    queryKey: ["payroll-run", id],
    queryFn: () => getRun(id!),
    enabled: id !== null,
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["payroll-runs"] });
    qc.invalidateQueries({ queryKey: ["payroll-run", id] });
    qc.invalidateQueries({ queryKey: ["employees"] });
  };

  const approve = useMutation({
    mutationFn: () => approveRun(id!),
    onSuccess: invalidate,
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("pay.couldNotApprove")),
  });
  const pay = useMutation({
    mutationFn: () => payRun(id!, { method: "bank_transfer" }),
    onSuccess: invalidate,
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("pay.couldNotPay")),
  });
  const addLine = useMutation({
    mutationFn: () => addPayslipLine(lineFor!, {
      type: line.type, label: line.label, amount: Number(line.amount),
      is_bonus: line.type === "earning" && line.is_bonus,
    }),
    onSuccess: () => { invalidate(); setLineFor(null); setLine({ type: "earning", label: "", amount: "", is_bonus: true }); },
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("pay.addLineError")),
  });

  if (!run) {
    return <Dialog open={id !== null} onClose={onClose} title={t("pay.payRun")}><TableSkeleton rows={3} /></Dialog>;
  }

  const draft = run.status === "draft";

  return (
    <Dialog open={id !== null} onClose={onClose} title={`${run.number} · ${run.period_label}`} className="max-w-3xl">
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <Badge tone={STATUS_TONE[run.status] ?? "employee"}>{t(`pay.status.${run.status}`)}</Badge>
          <span className="text-sm text-text-2">{t("pay.netTotalLabel")} <strong>{formatTnd(run.net_total)}</strong></span>
          {run.journal_entry_number && <span className="text-xs text-text-3">{t("pay.postedAs")} {run.journal_entry_number}</span>}
        </div>

        <Table>
          <THead>
            <tr>
              <Th>{t("pay.employee")}</Th><Th className="text-right">{t("pay.base")}</Th><Th className="text-right">{t("pay.bonus")}</Th>
              <Th className="text-right">{t("pay.cnss")}</Th><Th className="text-right">{t("pay.irpp")}</Th>
              <Th className="text-right">{t("pay.deductions")}</Th><Th className="text-right">{t("pay.advance")}</Th>
              <Th className="text-right">{t("pay.net")}</Th>{draft && <Th />}
            </tr>
          </THead>
          <TBody>
            {(run.payslips ?? []).map((p) => (
              <tr key={p.id}>
                <Td>{p.employee_name}</Td>
                <Td className="text-right">{formatTnd(p.base_salary)}</Td>
                <Td className="text-right">{Number(p.earnings_total) > 0 ? formatTnd(p.earnings_total) : "-"}</Td>
                <Td className="text-right text-text-2">{Number(p.cnss_employee) > 0 ? formatTnd(p.cnss_employee) : "-"}</Td>
                <Td className="text-right text-text-2">{Number(p.irpp) + Number(p.css) > 0 ? formatTnd(Number(p.irpp) + Number(p.css)) : "-"}</Td>
                <Td className="text-right">{Number(p.deductions_total) > 0 ? formatTnd(p.deductions_total) : "-"}</Td>
                <Td className="text-right">{Number(p.advance_recovered) > 0 ? formatTnd(p.advance_recovered) : "-"}</Td>
                <Td className="text-right font-medium">{formatTnd(p.net_pay)}</Td>
                {draft && (
                  <Td className="text-right">
                    <Button size="sm" variant="ghost" onClick={() => { setError(""); setLineFor(p.id); }}>
                      <Plus className="h-3.5 w-3.5" /> {t("pay.line")}
                    </Button>
                  </Td>
                )}
              </tr>
            ))}
          </TBody>
        </Table>

        {error && <p className="text-sm text-danger">{error}</p>}

        <div className="flex gap-2">
          {draft && (
            <Button onClick={() => approve.mutate()} disabled={approve.isPending}>
              {approve.isPending ? t("pay.posting") : t("pay.approvePost")}
            </Button>
          )}
          {run.status === "approved" && (
            <Button onClick={() => pay.mutate()} disabled={pay.isPending}>
              {pay.isPending ? t("pay.paying") : t("pay.markPaid")}
            </Button>
          )}
        </div>

        {/* add-line inline form */}
        {lineFor !== null && (
          <div className="space-y-3 rounded-md bg-surface-2 p-3">
            <div className="grid grid-cols-3 gap-3">
              <div className="space-y-1.5"><Label htmlFor="l-type">{t("pay.type")}</Label>
                <Select id="l-type" value={line.type} onChange={(e) => setLine((l) => ({ ...l, type: e.target.value }))}>
                  <option value="earning">{t("pay.bonusEarning")}</option>
                  <option value="deduction">{t("pay.deduction")}</option>
                </Select></div>
              <div className="space-y-1.5"><Label htmlFor="l-label">{t("pay.label")}</Label>
                <Input id="l-label" value={line.label} onChange={(e) => setLine((l) => ({ ...l, label: e.target.value }))} placeholder={t("pay.linePlaceholder")} /></div>
              <div className="space-y-1.5"><Label htmlFor="l-amt">{t("subs.amount")}</Label>
                <Input id="l-amt" type="number" step="0.001" min="0" value={line.amount} onChange={(e) => setLine((l) => ({ ...l, amount: e.target.value }))} /></div>
            </div>
            <div className="flex gap-2">
              <Button size="sm" onClick={() => addLine.mutate()} disabled={!line.label || !line.amount || addLine.isPending}>{t("common.add")}</Button>
              <Button size="sm" variant="ghost" onClick={() => setLineFor(null)}>{t("common.cancel")}</Button>
            </div>
          </div>
        )}
      </div>
    </Dialog>
  );
}
