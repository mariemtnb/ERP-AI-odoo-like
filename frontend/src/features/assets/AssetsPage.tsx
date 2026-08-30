import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  createAsset,
  depreciateAsset,
  disposeAsset,
  getSchedule,
  listAssets,
  type FixedAsset,
} from "@/api/assets";

const money = (n: string | number) => Number(n).toFixed(2);

export default function AssetsPage() {
  const qc = useQueryClient();
  const assetsQ = useQuery({ queryKey: ["assets"], queryFn: listAssets });
  const [adding, setAdding] = useState(false);
  const [scheduleFor, setScheduleFor] = useState<FixedAsset | null>(null);
  const refresh = () => qc.invalidateQueries({ queryKey: ["assets"] });

  const dep = useMutation({ mutationFn: (id: number) => depreciateAsset(id), onSuccess: refresh });
  const disp = useMutation({ mutationFn: (id: number) => disposeAsset(id), onSuccess: refresh });

  return (
    <div>
      <PageHead title="Fixed Assets" sub="Track assets and their straight-line depreciation and book value.">
        <Button onClick={() => setAdding((v) => !v)}>{adding ? "Close" : "Add asset"}</Button>
      </PageHead>

      {adding && <AddAsset onDone={() => { setAdding(false); refresh(); }} onCancel={() => setAdding(false)} />}
      {scheduleFor && <SchedulePanel asset={scheduleFor} onClose={() => setScheduleFor(null)} />}

      <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
            <Th>Asset</Th><Th right>Cost</Th><Th right>Accum. dep.</Th><Th right>Book value</Th><Th>Status</Th><Th></Th>
          </tr></thead>
          <tbody>
            {(assetsQ.data ?? []).map((a) => (
              <tr key={a.id} style={{ borderTop: "1px solid var(--border)" }}>
                <Td>{a.name} <span style={{ color: "var(--text-muted)" }}>{a.category && `· ${a.category}`} · {a.useful_life_months}mo</span></Td>
                <Td right mono>{money(a.acquisition_cost)}</Td>
                <Td right mono>{money(a.accumulated_depreciation)}</Td>
                <Td right mono style={{ color: "var(--text-strong)", fontWeight: 600 }}>{money(a.book_value)}</Td>
                <Td>
                  <span style={{ fontSize: 12, fontWeight: 600, color: a.status === "disposed" ? "var(--text-muted)" : a.fully_depreciated ? "var(--amber-400,#d99a2b)" : "var(--emerald-400)" }}>
                    {a.status === "disposed" ? "Disposed" : a.fully_depreciated ? "Fully depreciated" : "Active"}
                  </span>
                </Td>
                <Td right>
                  <span style={{ display: "flex", gap: 6, justifyContent: "flex-end" }}>
                    <Button size="sm" variant="ghost" onClick={() => setScheduleFor(a)}>Schedule</Button>
                    {a.status === "active" && !a.fully_depreciated && (
                      <Button size="sm" loading={dep.isPending} onClick={() => dep.mutate(a.id)}>Depreciate month</Button>
                    )}
                    {a.status === "active" && (
                      <Button size="sm" variant="outline" loading={disp.isPending} onClick={() => disp.mutate(a.id)}>Dispose</Button>
                    )}
                  </span>
                </Td>
              </tr>
            ))}
            {assetsQ.data?.length === 0 && <tr><Td colSpan={6} muted>No assets yet. Add one to start depreciating.</Td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function SchedulePanel({ asset, onClose }: { asset: FixedAsset; onClose: () => void }) {
  const q = useQuery({ queryKey: ["asset-schedule", asset.id], queryFn: () => getSchedule(asset.id) });
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18 }}>
      <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 10 }}>
        <strong style={{ color: "var(--text-strong)" }}>{asset.name} — remaining schedule ({q.data?.monthly_charge != null ? `${money(q.data.monthly_charge)}/mo` : "…"})</strong>
        <Button variant="ghost" size="sm" onClick={onClose}>Close</Button>
      </div>
      <div style={{ maxHeight: 240, overflowY: "auto" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>
          <thead><tr style={{ color: "var(--text-muted)", textAlign: "left" }}><Th>Month</Th><Th right>Charge</Th><Th right>Book value after</Th></tr></thead>
          <tbody>
            {(q.data?.schedule ?? []).map((r) => (
              <tr key={r.month} style={{ borderTop: "1px solid var(--border)" }}>
                <Td mono>#{r.month}</Td><Td right mono>{money(r.amount)}</Td><Td right mono>{money(r.book_value_after)}</Td>
              </tr>
            ))}
            {q.data?.schedule.length === 0 && <tr><Td colSpan={3} muted>Fully depreciated — nothing remaining.</Td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function AddAsset({ onDone, onCancel }: { onDone: () => void; onCancel: () => void }) {
  const [name, setName] = useState("");
  const [category, setCategory] = useState("");
  const [date, setDate] = useState("");
  const [cost, setCost] = useState("");
  const [salvage, setSalvage] = useState("");
  const [life, setLife] = useState("");
  const [error, setError] = useState<string | null>(null);
  const add = useMutation({
    mutationFn: () => createAsset({ name, category, acquisition_date: date, acquisition_cost: Number(cost), salvage_value: Number(salvage) || 0, useful_life_months: Number(life) }),
    onSuccess: onDone,
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not add the asset."),
  });
  const ok = name && date && Number(cost) > 0 && Number(life) > 0;
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18, display: "grid", gridTemplateColumns: "repeat(auto-fit,minmax(130px,1fr))", gap: 12, alignItems: "end" }}>
      <Field label="Name"><Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Delivery van" /></Field>
      <Field label="Category"><Input value={category} onChange={(e) => setCategory(e.target.value)} placeholder="Vehicles" /></Field>
      <Field label="Acquired"><Input type="date" value={date} onChange={(e) => setDate(e.target.value)} /></Field>
      <Field label="Cost (TND)"><Input type="number" min={0} step="0.01" value={cost} onChange={(e) => setCost(e.target.value)} /></Field>
      <Field label="Salvage (TND)"><Input type="number" min={0} step="0.01" value={salvage} onChange={(e) => setSalvage(e.target.value)} /></Field>
      <Field label="Life (months)"><Input type="number" min={1} value={life} onChange={(e) => setLife(e.target.value)} /></Field>
      <div style={{ display: "flex", gap: 8 }}>
        <Button variant="outline" onClick={onCancel}>Cancel</Button>
        <Button loading={add.isPending} disabled={!ok} onClick={() => add.mutate()}>Add</Button>
      </div>
      {error && <p style={{ color: "var(--rose-400)", fontSize: 13, gridColumn: "1/-1" }}>{error}</p>}
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block" }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
function Th({ children, right }: { children?: React.ReactNode; right?: boolean }) {
  return <th style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: right ? "right" : "left" }}>{children}</th>;
}
function Td({ children, mono, right, muted, colSpan, style }: { children: React.ReactNode; mono?: boolean; right?: boolean; muted?: boolean; colSpan?: number; style?: React.CSSProperties }) {
  return <td colSpan={colSpan} style={{ padding: "10px 14px", textAlign: right ? "right" : "left", fontFamily: mono ? "var(--font-mono)" : undefined, color: muted ? "var(--text-muted)" : "var(--text-body)", fontVariantNumeric: mono ? "tabular-nums" : undefined, ...style }}>{children}</td>;
}
