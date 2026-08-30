import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import * as sub from "@/api/subscriptions";

const money = (n: string | number) => Number(n).toFixed(2);
const STATUS_COLOR: Record<string, string> = { active: "var(--emerald-400)", paused: "var(--amber-400,#d99a2b)", cancelled: "var(--rose-400)" };

export default function SubscriptionsPage() {
  const qc = useQueryClient();
  const listQ = useQuery({ queryKey: ["subscriptions"], queryFn: sub.listSubscriptions });
  const [creating, setCreating] = useState(false);
  const [flash, setFlash] = useState<string | null>(null);
  const refresh = () => qc.invalidateQueries({ queryKey: ["subscriptions"] });

  const bill = useMutation({
    mutationFn: () => sub.runBilling(),
    onSuccess: (r) => { setFlash(`Generated ${r.generated} invoice${r.generated !== 1 ? "s" : ""} for ${r.total_amount} TND.`); refresh(); },
  });
  const setStatus = useMutation({ mutationFn: ({ id, s }: { id: number; s: "active" | "paused" | "cancelled" }) => sub.setSubscriptionStatus(id, s), onSuccess: refresh });

  return (
    <div>
      <PageHead title="Subscriptions" sub="Recurring billing on a monthly, quarterly or yearly cadence.">
        <Button variant="outline" onClick={() => setCreating((v) => !v)}>{creating ? "Close" : "New subscription"}</Button>
        <Button loading={bill.isPending} onClick={() => bill.mutate()}>Run billing (today)</Button>
      </PageHead>

      {flash && (
        <div style={{ background: "color-mix(in oklab, var(--emerald-500) 10%, transparent)", border: "1px solid var(--emerald-500)", borderRadius: 12, padding: "10px 16px", marginBottom: 16, fontSize: 14, color: "var(--text-strong)" }}>{flash}</div>
      )}

      {creating && <NewSubscription onDone={() => { setCreating(false); refresh(); }} onCancel={() => setCreating(false)} />}

      <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
            <Th>Subscription</Th><Th>Customer</Th><Th right>Amount</Th><Th>Interval</Th><Th>Next invoice</Th><Th right>Billed</Th><Th>Status</Th><Th></Th>
          </tr></thead>
          <tbody>
            {(listQ.data ?? []).map((s) => (
              <tr key={s.id} style={{ borderTop: "1px solid var(--border)" }}>
                <Td>{s.description} <span style={{ fontFamily: "var(--font-mono)", color: "var(--text-muted)", fontSize: 12 }}>{s.number}</span></Td>
                <Td>{s.customer_name}</Td>
                <Td right mono>{money(s.amount)}</Td>
                <Td cap>{s.interval}</Td>
                <Td mono>{s.next_invoice_date}</Td>
                <Td right mono>{money(s.billed_total)} <span style={{ color: "var(--text-muted)" }}>({s.invoices_count})</span></Td>
                <Td><span style={{ textTransform: "capitalize", fontSize: 12, fontWeight: 600, color: STATUS_COLOR[s.status] }}>{s.status}</span></Td>
                <Td right>
                  <span style={{ display: "flex", gap: 6, justifyContent: "flex-end" }}>
                    {s.status === "active" && <Button size="sm" variant="outline" onClick={() => setStatus.mutate({ id: s.id, s: "paused" })}>Pause</Button>}
                    {s.status === "paused" && <Button size="sm" variant="outline" onClick={() => setStatus.mutate({ id: s.id, s: "active" })}>Resume</Button>}
                    {s.status !== "cancelled" && <Button size="sm" variant="ghost" onClick={() => setStatus.mutate({ id: s.id, s: "cancelled" })}>Cancel</Button>}
                  </span>
                </Td>
              </tr>
            ))}
            {listQ.data?.length === 0 && <tr><Td colSpan={8} muted>No subscriptions yet.</Td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function NewSubscription({ onDone, onCancel }: { onDone: () => void; onCancel: () => void }) {
  const customersQ = useQuery({ queryKey: ["sub-customers"], queryFn: sub.listCustomers });
  const [customer, setCustomer] = useState("");
  const [description, setDescription] = useState("");
  const [amount, setAmount] = useState("");
  const [interval, setInterval] = useState("monthly");
  const [start, setStart] = useState("");
  const [error, setError] = useState<string | null>(null);
  const add = useMutation({
    mutationFn: () => sub.createSubscription({ customer: Number(customer), description, amount: Number(amount), interval, start_date: start }),
    onSuccess: onDone,
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not create the subscription."),
  });
  const ok = customer && description.trim() && Number(amount) > 0 && start;
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18, display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(140px,1fr))", gap: 12, alignItems: "end" }}>
      <Field label="Customer">
        <select value={customer} onChange={(e) => setCustomer(e.target.value)} style={selectStyle}>
          <option value="">Select…</option>
          {(customersQ.data ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      </Field>
      <Field label="Description"><Input value={description} onChange={(e) => setDescription(e.target.value)} placeholder="Support plan" /></Field>
      <Field label="Amount (TND)"><Input type="number" min={0} step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} /></Field>
      <Field label="Interval">
        <select value={interval} onChange={(e) => setInterval(e.target.value)} style={selectStyle}>
          <option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="yearly">Yearly</option>
        </select>
      </Field>
      <Field label="Start date"><Input type="date" value={start} onChange={(e) => setStart(e.target.value)} /></Field>
      <div style={{ display: "flex", gap: 8 }}>
        <Button variant="outline" onClick={onCancel}>Cancel</Button>
        <Button loading={add.isPending} disabled={!ok} onClick={() => add.mutate()}>Create</Button>
      </div>
      {error && <p style={{ color: "var(--rose-400)", fontSize: 13, gridColumn: "1/-1" }}>{error}</p>}
    </div>
  );
}

const selectStyle: React.CSSProperties = { height: 38, width: "100%", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 8px" };
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block" }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
function Th({ children, right }: { children?: React.ReactNode; right?: boolean }) {
  return <th style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: right ? "right" : "left" }}>{children}</th>;
}
function Td({ children, mono, right, muted, cap, colSpan }: { children: React.ReactNode; mono?: boolean; right?: boolean; muted?: boolean; cap?: boolean; colSpan?: number }) {
  return <td colSpan={colSpan} style={{ padding: "10px 14px", textAlign: right ? "right" : "left", fontFamily: mono ? "var(--font-mono)" : undefined, textTransform: cap ? "capitalize" : undefined, color: muted ? "var(--text-muted)" : "var(--text-body)", fontVariantNumeric: mono ? "tabular-nums" : undefined }}>{children}</td>;
}
