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

const STATUS_TONE: Record<string, string> = {
  pending: "employee", paid: "sky", recovered: "green", cancelled: "employee",
  draft: "employee", approved: "sky", completed: "green",
};

export default function PayrollPage() {
  const [tab, setTab] = useState("employees");

  return (
    <div className="space-y-6">
      <p className="text-sm text-text-3">
        Gestion de paie — your staff, their pay, advances on salary, and monthly pay runs.
      </p>
      <Segmented
        id="payroll-tab"
        value={tab}
        onChange={setTab}
        options={[
          { value: "employees", label: "Employees" },
          { value: "advances", label: "Advances" },
          { value: "runs", label: "Pay runs" },
        ]}
      />
      {tab === "employees" && <EmployeesTab />}
      {tab === "advances" && <AdvancesTab />}
      {tab === "runs" && <RunsTab />}
    </div>
  );
}

/* ─────────────── employees ─────────────── */

const emptyEmp = { first_name: "", last_name: "", job_title: "", base_salary: "", phone: "", rib: "" };

function EmployeesTab() {
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(emptyEmp);
  const [error, setError] = useState("");

  const { data, isLoading } = useQuery({ queryKey: ["employees"], queryFn: () => listEmployees() });

  const create = useMutation({
    mutationFn: () => createEmployee({ ...form, base_salary: Number(form.base_salary) }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["employees"] });
      setOpen(false);
      setForm(emptyEmp);
      setError("");
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not save."),
  });

  const set = (k: keyof typeof emptyEmp) => (e: { target: { value: string } }) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  if (isLoading) return <TableSkeleton rows={4} />;
  const rows = data?.results ?? [];

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button onClick={() => { setError(""); setOpen(true); }}>
          <UserPlus className="h-4 w-4" /> New employee
        </Button>
      </div>
      {rows.length === 0 ? (
        <EmptyState icon={Wallet} title="No employees yet" hint="Add your staff to start running payroll." />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>Name</Th><Th>Job</Th><Th className="text-right">Base salary</Th>
              <Th className="text-right">Advance owed</Th><Th>Status</Th>
            </tr>
          </THead>
          <TBody>
            {rows.map((e) => (
              <tr key={e.id}>
                <Td>
                  <span className="font-medium">{e.full_name}</span>
                  <span className="ml-2 text-xs text-text-3">{e.code}</span>
                </Td>
                <Td>{e.job_title || "—"}</Td>
                <Td className="text-right">{formatTnd(e.base_salary)}</Td>
                <Td className="text-right">
                  {Number(e.outstanding_advance) > 0 ? formatTnd(e.outstanding_advance) : "—"}
                </Td>
                <Td><Badge tone={e.is_active ? "green" : "red"}>{e.is_active ? "active" : "inactive"}</Badge></Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <Dialog open={open} onClose={() => setOpen(false)} title="New employee">
        <form onSubmit={(ev: FormEvent) => { ev.preventDefault(); create.mutate(); }} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5"><Label htmlFor="e-fn">First name</Label>
              <Input id="e-fn" value={form.first_name} onChange={set("first_name")} required /></div>
            <div className="space-y-1.5"><Label htmlFor="e-ln">Last name</Label>
              <Input id="e-ln" value={form.last_name} onChange={set("last_name")} /></div>
            <div className="space-y-1.5"><Label htmlFor="e-job">Job title</Label>
              <Input id="e-job" value={form.job_title} onChange={set("job_title")} /></div>
            <div className="space-y-1.5"><Label htmlFor="e-sal">Base salary</Label>
              <Input id="e-sal" type="number" step="0.001" min="0" value={form.base_salary} onChange={set("base_salary")} required /></div>
            <div className="space-y-1.5"><Label htmlFor="e-ph">Phone</Label>
              <Input id="e-ph" value={form.phone} onChange={set("phone")} /></div>
            <div className="space-y-1.5"><Label htmlFor="e-rib">RIB (for salary transfer)</Label>
              <Input id="e-rib" value={form.rib} onChange={set("rib")} /></div>
          </div>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={create.isPending}>
            {create.isPending ? "Saving…" : "Add employee"}
          </Button>
        </form>
      </Dialog>
    </div>
  );
}

/* ─────────────── advances ─────────────── */

function AdvancesTab() {
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
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not save."),
  });
  const pay = useMutation({ mutationFn: (a: EmployeeAdvance) => payAdvance(a.id), onSuccess: invalidate });
  const cancel = useMutation({ mutationFn: (a: EmployeeAdvance) => cancelAdvance(a.id), onSuccess: invalidate });

  if (isLoading) return <TableSkeleton rows={4} />;
  const rows = data?.results ?? [];

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button onClick={() => { setError(""); setOpen(true); }}>
          <HandCoins className="h-4 w-4" /> New advance
        </Button>
      </div>
      {rows.length === 0 ? (
        <EmptyState icon={HandCoins} title="No advances" hint="An employee can take part of their salary early — record it here so it's taken back at pay time." />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>Number</Th><Th>Employee</Th><Th>Reason</Th>
              <Th className="text-right">Amount</Th><Th className="text-right">Left to recover</Th>
              <Th>Status</Th><Th />
            </tr>
          </THead>
          <TBody>
            {rows.map((a) => (
              <tr key={a.id}>
                <Td className="font-medium">{a.number}</Td>
                <Td>{a.employee_name}</Td>
                <Td>{a.reason || "—"}</Td>
                <Td className="text-right">{formatTnd(a.amount)}</Td>
                <Td className="text-right">{a.status === "paid" ? formatTnd(a.remaining) : "—"}</Td>
                <Td><Badge tone={STATUS_TONE[a.status] ?? "employee"}>{a.status}</Badge></Td>
                <Td className="text-right">
                  {a.status === "pending" && (
                    <>
                      <Button size="sm" onClick={() => pay.mutate(a)}>Pay</Button>
                      <Button size="sm" variant="ghost" className="ml-1" onClick={() => cancel.mutate(a)}>Cancel</Button>
                    </>
                  )}
                </Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <Dialog open={open} onClose={() => setOpen(false)} title="New advance on salary">
        <form onSubmit={(ev: FormEvent) => { ev.preventDefault(); create.mutate(); }} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="a-emp">Employee</Label>
            <Select id="a-emp" value={form.employee_id} onChange={(e) => setForm((f) => ({ ...f, employee_id: e.target.value }))} required>
              <option value="">— choose —</option>
              {(emps?.results ?? []).map((e) => <option key={e.id} value={e.id}>{e.full_name}</option>)}
            </Select>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5"><Label htmlFor="a-amt">Amount</Label>
              <Input id="a-amt" type="number" step="0.001" min="0" value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))} required /></div>
            <div className="space-y-1.5"><Label htmlFor="a-method">Method</Label>
              <Select id="a-method" value={form.method} onChange={(e) => setForm((f) => ({ ...f, method: e.target.value }))}>
                <option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option>
              </Select></div>
          </div>
          <div className="space-y-1.5"><Label htmlFor="a-reason">Reason</Label>
            <Input id="a-reason" value={form.reason} onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))} placeholder="Sickness, family matter…" /></div>
          <p className="text-xs text-text-3">Paying it moves money now; it's taken back from the next payslip automatically.</p>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={create.isPending}>
            {create.isPending ? "Saving…" : "Record advance"}
          </Button>
        </form>
      </Dialog>
    </div>
  );
}

/* ─────────────── pay runs ─────────────── */

function RunsTab() {
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
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not create the run."),
  });

  if (isLoading) return <TableSkeleton rows={4} />;
  const rows = data?.results ?? [];

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <Button onClick={() => { setError(""); setOpen(true); }}>
          <CalendarPlus className="h-4 w-4" /> New pay run
        </Button>
      </div>
      {rows.length === 0 ? (
        <EmptyState icon={Wallet} title="No pay runs yet" hint="Create a run for a month — it fills in a payslip for every active employee." />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>Run</Th><Th>Period</Th><Th className="text-right">Staff</Th>
              <Th className="text-right">Net total</Th><Th>Status</Th>
            </tr>
          </THead>
          <TBody>
            {rows.map((r) => (
              <tr key={r.id} className="cursor-pointer" onClick={() => setDetailId(r.id)}>
                <Td className="font-medium">{r.number}</Td>
                <Td>{r.period_label}</Td>
                <Td className="text-right">{r.employee_count}</Td>
                <Td className="text-right">{formatTnd(r.net_total)}</Td>
                <Td><Badge tone={STATUS_TONE[r.status] ?? "employee"}>{r.status}</Badge></Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <Dialog open={open} onClose={() => setOpen(false)} title="New pay run">
        <form onSubmit={(ev: FormEvent) => { ev.preventDefault(); create.mutate(); }} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="r-month">Month</Label>
            <Input id="r-month" type="month" value={month} onChange={(e) => setMonth(e.target.value)} required />
          </div>
          <p className="text-xs text-text-3">A payslip is created for every active employee, pre-filled with their base salary and any advance to recover.</p>
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={create.isPending}>
            {create.isPending ? "Creating…" : "Create run"}
          </Button>
        </form>
      </Dialog>

      <RunDetail id={detailId} onClose={() => setDetailId(null)} />
    </div>
  );
}

function RunDetail({ id, onClose }: { id: number | null; onClose: () => void }) {
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
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not approve."),
  });
  const pay = useMutation({
    mutationFn: () => payRun(id!, { method: "bank_transfer" }),
    onSuccess: invalidate,
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not pay."),
  });
  const addLine = useMutation({
    mutationFn: () => addPayslipLine(lineFor!, {
      type: line.type, label: line.label, amount: Number(line.amount),
      is_bonus: line.type === "earning" && line.is_bonus,
    }),
    onSuccess: () => { invalidate(); setLineFor(null); setLine({ type: "earning", label: "", amount: "", is_bonus: true }); },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not add the line."),
  });

  if (!run) {
    return <Dialog open={id !== null} onClose={onClose} title="Pay run"><TableSkeleton rows={3} /></Dialog>;
  }

  const draft = run.status === "draft";

  return (
    <Dialog open={id !== null} onClose={onClose} title={`${run.number} · ${run.period_label}`} className="max-w-3xl">
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <Badge tone={STATUS_TONE[run.status] ?? "employee"}>{run.status}</Badge>
          <span className="text-sm text-text-2">Net total: <strong>{formatTnd(run.net_total)}</strong></span>
          {run.journal_entry_number && <span className="text-xs text-text-3">posted as {run.journal_entry_number}</span>}
        </div>

        <Table>
          <THead>
            <tr>
              <Th>Employee</Th><Th className="text-right">Base</Th><Th className="text-right">Bonus</Th>
              <Th className="text-right">Deductions</Th><Th className="text-right">Advance</Th>
              <Th className="text-right">Net</Th>{draft && <Th />}
            </tr>
          </THead>
          <TBody>
            {(run.payslips ?? []).map((p) => (
              <tr key={p.id}>
                <Td>{p.employee_name}</Td>
                <Td className="text-right">{formatTnd(p.base_salary)}</Td>
                <Td className="text-right">{Number(p.earnings_total) > 0 ? formatTnd(p.earnings_total) : "—"}</Td>
                <Td className="text-right">{Number(p.deductions_total) > 0 ? formatTnd(p.deductions_total) : "—"}</Td>
                <Td className="text-right">{Number(p.advance_recovered) > 0 ? formatTnd(p.advance_recovered) : "—"}</Td>
                <Td className="text-right font-medium">{formatTnd(p.net_pay)}</Td>
                {draft && (
                  <Td className="text-right">
                    <Button size="sm" variant="ghost" onClick={() => { setError(""); setLineFor(p.id); }}>
                      <Plus className="h-3.5 w-3.5" /> Line
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
              {approve.isPending ? "Posting…" : "Approve & post to books"}
            </Button>
          )}
          {run.status === "approved" && (
            <Button onClick={() => pay.mutate()} disabled={pay.isPending}>
              {pay.isPending ? "Paying…" : "Mark salaries paid"}
            </Button>
          )}
        </div>

        {/* add-line inline form */}
        {lineFor !== null && (
          <div className="space-y-3 rounded-md bg-surface-2 p-3">
            <div className="grid grid-cols-3 gap-3">
              <div className="space-y-1.5"><Label htmlFor="l-type">Type</Label>
                <Select id="l-type" value={line.type} onChange={(e) => setLine((l) => ({ ...l, type: e.target.value }))}>
                  <option value="earning">Bonus / earning</option>
                  <option value="deduction">Deduction</option>
                </Select></div>
              <div className="space-y-1.5"><Label htmlFor="l-label">Label</Label>
                <Input id="l-label" value={line.label} onChange={(e) => setLine((l) => ({ ...l, label: e.target.value }))} placeholder="Prime, retenue…" /></div>
              <div className="space-y-1.5"><Label htmlFor="l-amt">Amount</Label>
                <Input id="l-amt" type="number" step="0.001" min="0" value={line.amount} onChange={(e) => setLine((l) => ({ ...l, amount: e.target.value }))} /></div>
            </div>
            <div className="flex gap-2">
              <Button size="sm" onClick={() => addLine.mutate()} disabled={!line.label || !line.amount || addLine.isPending}>Add</Button>
              <Button size="sm" variant="ghost" onClick={() => setLineFor(null)}>Cancel</Button>
            </div>
          </div>
        )}
      </div>
    </Dialog>
  );
}
