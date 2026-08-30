import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import * as hr from "@/api/hr";

type Tab = "leave" | "attendance" | "expenses";
const money = (n: string | number) => Number(n).toFixed(2);

const STATUS_COLOR: Record<string, string> = {
  pending: "var(--amber-400,#d99a2b)", approved: "var(--emerald-400)",
  rejected: "var(--rose-400)", reimbursed: "var(--emerald-400)",
};

export default function HrPage() {
  const [tab, setTab] = useState<Tab>("leave");
  const [employee, setEmployee] = useState<number | null>(null);
  const employeesQ = useQuery({ queryKey: ["hr-employees"], queryFn: hr.listEmployees });

  useEffect(() => {
    if (!employee && employeesQ.data?.length) setEmployee(employeesQ.data[0].id);
  }, [employeesQ.data, employee]);

  return (
    <div>
      <PageHead title="Human Resources" sub="Attendance, leave and employee expense claims." >
        <select
          value={employee ?? ""}
          onChange={(e) => setEmployee(Number(e.target.value))}
          style={{ height: 38, background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 10px" }}
        >
          {(employeesQ.data ?? []).map((e) => <option key={e.id} value={e.id}>{e.full_name} · {e.code}</option>)}
        </select>
      </PageHead>

      <div style={{ display: "flex", gap: 6, marginBottom: 18 }}>
        {(["leave", "attendance", "expenses"] as Tab[]).map((t) => (
          <button key={t} onClick={() => setTab(t)} style={{
            textTransform: "capitalize", padding: "8px 16px", borderRadius: 9, cursor: "pointer", fontSize: 14,
            border: "1px solid " + (tab === t ? "var(--emerald-500)" : "var(--border)"),
            background: tab === t ? "color-mix(in oklab, var(--emerald-500) 14%, transparent)" : "var(--surface)",
            color: tab === t ? "var(--text-strong)" : "var(--text-muted)",
          }}>{t}</button>
        ))}
      </div>

      {employee && tab === "leave" && <LeaveTab employee={employee} />}
      {employee && tab === "attendance" && <AttendanceTab employee={employee} />}
      {employee && tab === "expenses" && <ExpensesTab employee={employee} />}
    </div>
  );
}

/* ---------------- LEAVE ---------------- */
function LeaveTab({ employee }: { employee: number }) {
  const qc = useQueryClient();
  const listQ = useQuery({ queryKey: ["hr-leave", employee], queryFn: () => hr.listLeave(employee) });
  const balQ = useQuery({ queryKey: ["hr-leave-bal", employee], queryFn: () => hr.leaveBalance(employee) });
  const refresh = () => { qc.invalidateQueries({ queryKey: ["hr-leave", employee] }); qc.invalidateQueries({ queryKey: ["hr-leave-bal", employee] }); };

  const [type, setType] = useState("annual");
  const [start, setStart] = useState("");
  const [end, setEnd] = useState("");
  const [reason, setReason] = useState("");

  const create = useMutation({ mutationFn: () => hr.requestLeave({ employee, type, start_date: start, end_date: end, reason }), onSuccess: () => { setStart(""); setEnd(""); setReason(""); refresh(); } });
  const decide = useMutation({ mutationFn: ({ id, d }: { id: number; d: "approve" | "reject" }) => hr.decideLeave(id, d), onSuccess: refresh });

  const bal = balQ.data;
  return (
    <>
      {bal && (
        <div style={{ display: "flex", gap: 12, marginBottom: 16 }}>
          <Stat label={`Annual allowance ${bal.year}`} value={`${bal.allowance} d`} />
          <Stat label="Used" value={`${bal.used} d`} />
          <Stat label="Remaining" value={`${bal.remaining} d`} accent />
        </div>
      )}
      <Panel>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(130px,1fr))", gap: 10, alignItems: "end" }}>
          <Field label="Type">
            <select value={type} onChange={(e) => setType(e.target.value)} style={selectStyle}>
              <option value="annual">Annual</option><option value="sick">Sick</option><option value="unpaid">Unpaid</option>
            </select>
          </Field>
          <Field label="From"><Input type="date" value={start} onChange={(e) => setStart(e.target.value)} /></Field>
          <Field label="To"><Input type="date" value={end} onChange={(e) => setEnd(e.target.value)} /></Field>
          <Field label="Reason"><Input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="optional" /></Field>
          <Button loading={create.isPending} disabled={!start || !end} onClick={() => create.mutate()}>Request leave</Button>
        </div>
      </Panel>
      <Table head={["Type", "From", "To", "Days", "Status", ""]}>
        {(listQ.data ?? []).map((l) => (
          <tr key={l.id} style={rowStyle}>
            <Td cap>{l.type}</Td><Td mono>{l.start_date}</Td><Td mono>{l.end_date}</Td><Td mono right>{l.days}</Td>
            <Td><Badge status={l.status} /></Td>
            <Td right>{l.status === "pending" && (
              <span style={{ display: "flex", gap: 6, justifyContent: "flex-end" }}>
                <Button size="sm" onClick={() => decide.mutate({ id: l.id, d: "approve" })}>Approve</Button>
                <Button size="sm" variant="outline" onClick={() => decide.mutate({ id: l.id, d: "reject" })}>Reject</Button>
              </span>
            )}</Td>
          </tr>
        ))}
        {listQ.data?.length === 0 && <tr><Td colSpan={6} muted>No leave requests.</Td></tr>}
      </Table>
    </>
  );
}

/* ---------------- ATTENDANCE ---------------- */
function AttendanceTab({ employee }: { employee: number }) {
  const qc = useQueryClient();
  const listQ = useQuery({ queryKey: ["hr-att", employee], queryFn: () => hr.listAttendance(employee) });
  const refresh = () => qc.invalidateQueries({ queryKey: ["hr-att", employee] });
  const [error, setError] = useState<string | null>(null);
  const inM = useMutation({ mutationFn: () => hr.clockIn(employee), onSuccess: () => { setError(null); refresh(); }, onError: (e: any) => setError(e?.response?.data?.detail ?? "Failed.") });
  const outM = useMutation({ mutationFn: () => hr.clockOut(employee), onSuccess: () => { setError(null); refresh(); }, onError: (e: any) => setError(e?.response?.data?.detail ?? "Failed.") });

  return (
    <>
      <Panel>
        <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
          <Button loading={inM.isPending} onClick={() => inM.mutate()}>Clock in (today)</Button>
          <Button loading={outM.isPending} variant="outline" onClick={() => outM.mutate()}>Clock out (today)</Button>
          {error && <span style={{ color: "var(--rose-400)", fontSize: 13 }}>{error}</span>}
        </div>
      </Panel>
      <Table head={["Date", "In", "Out", "Hours"]}>
        {(listQ.data ?? []).map((a) => (
          <tr key={a.id} style={rowStyle}><Td mono>{a.work_date}</Td><Td mono>{a.check_in ?? "—"}</Td><Td mono>{a.check_out ?? "—"}</Td><Td mono right>{a.hours}</Td></tr>
        ))}
        {listQ.data?.length === 0 && <tr><Td colSpan={4} muted>No attendance records.</Td></tr>}
      </Table>
    </>
  );
}

/* ---------------- EXPENSES ---------------- */
function ExpensesTab({ employee }: { employee: number }) {
  const qc = useQueryClient();
  const listQ = useQuery({ queryKey: ["hr-exp", employee], queryFn: () => hr.listExpenses(employee) });
  const refresh = () => qc.invalidateQueries({ queryKey: ["hr-exp", employee] });
  const [date, setDate] = useState("");
  const [category, setCategory] = useState("");
  const [amount, setAmount] = useState("");
  const [desc, setDesc] = useState("");
  const create = useMutation({ mutationFn: () => hr.submitClaim({ employee, claim_date: date, category, amount: Number(amount), description: desc }), onSuccess: () => { setDate(""); setCategory(""); setAmount(""); setDesc(""); refresh(); } });
  const decide = useMutation({ mutationFn: ({ id, d }: { id: number; d: "approve" | "reject" | "reimburse" }) => hr.decideClaim(id, d), onSuccess: refresh });

  return (
    <>
      <Panel>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(120px,1fr))", gap: 10, alignItems: "end" }}>
          <Field label="Date"><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
          <Field label="Category"><Input value={category} onChange={(e) => setCategory(e.target.value)} placeholder="travel" /></Field>
          <Field label="Amount"><Input type="number" min={0} step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} /></Field>
          <Field label="Description"><Input value={desc} onChange={(e) => setDesc(e.target.value)} placeholder="optional" /></Field>
          <Button loading={create.isPending} disabled={!date || !(Number(amount) > 0)} onClick={() => create.mutate()}>Submit claim</Button>
        </div>
      </Panel>
      <Table head={["Date", "Category", "Amount", "Status", ""]}>
        {(listQ.data ?? []).map((c) => (
          <tr key={c.id} style={rowStyle}>
            <Td mono>{c.claim_date}</Td><Td>{c.category || "—"}</Td><Td mono right>{money(c.amount)} TND</Td>
            <Td><Badge status={c.status} /></Td>
            <Td right>
              <span style={{ display: "flex", gap: 6, justifyContent: "flex-end" }}>
                {c.status === "pending" && <>
                  <Button size="sm" onClick={() => decide.mutate({ id: c.id, d: "approve" })}>Approve</Button>
                  <Button size="sm" variant="outline" onClick={() => decide.mutate({ id: c.id, d: "reject" })}>Reject</Button>
                </>}
                {c.status === "approved" && <Button size="sm" onClick={() => decide.mutate({ id: c.id, d: "reimburse" })}>Reimburse</Button>}
              </span>
            </Td>
          </tr>
        ))}
        {listQ.data?.length === 0 && <tr><Td colSpan={5} muted>No expense claims.</Td></tr>}
      </Table>
    </>
  );
}

/* ---------------- shared bits ---------------- */
const selectStyle: React.CSSProperties = { height: 38, width: "100%", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 8px" };
const rowStyle: React.CSSProperties = { borderTop: "1px solid var(--border)" };

function Stat({ label, value, accent }: { label: string; value: string; accent?: boolean }) {
  return (
    <div style={{ flex: 1, background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 12, padding: "14px 16px" }}>
      <div style={{ fontSize: 12, color: "var(--text-muted)", textTransform: "uppercase", letterSpacing: "0.06em" }}>{label}</div>
      <div style={{ fontSize: 24, fontWeight: 700, color: accent ? "var(--emerald-400)" : "var(--text-strong)", marginTop: 4 }}>{value}</div>
    </div>
  );
}
function Panel({ children }: { children: React.ReactNode }) {
  return <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18 }}>{children}</div>;
}
function Table({ head, children }: { head: string[]; children: React.ReactNode }) {
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
        <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
          {head.map((h, i) => <th key={i} style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: i >= 3 ? "right" : "left" }}>{h}</th>)}
        </tr></thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  );
}
function Badge({ status }: { status: string }) {
  const c = STATUS_COLOR[status] ?? "var(--text-muted)";
  return <span style={{ textTransform: "capitalize", fontSize: 12, fontWeight: 600, color: c, border: `1px solid ${c}`, borderRadius: 999, padding: "2px 10px" }}>{status}</span>;
}
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block" }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
function Td({ children, mono, right, muted, cap, colSpan }: { children: React.ReactNode; mono?: boolean; right?: boolean; muted?: boolean; cap?: boolean; colSpan?: number }) {
  return <td colSpan={colSpan} style={{ padding: "10px 14px", textAlign: right ? "right" : "left", fontFamily: mono ? "var(--font-mono)" : undefined, textTransform: cap ? "capitalize" : undefined, color: muted ? "var(--text-muted)" : "var(--text-body)", fontVariantNumeric: mono ? "tabular-nums" : undefined }}>{children}</td>;
}
