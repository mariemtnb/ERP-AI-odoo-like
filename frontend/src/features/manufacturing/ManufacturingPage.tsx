import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { listProducts } from "@/api/catalog";
import * as mfg from "@/api/manufacturing";
import type { Product } from "@/types";
import { useI18n } from "@/lib/i18n";

type Tab = "orders" | "boms";
const STATUS_COLOR: Record<string, string> = {
  draft: "var(--text-muted)", in_progress: "var(--amber-400,#d99a2b)",
  done: "var(--emerald-400)", cancelled: "var(--rose-400)",
};

export default function ManufacturingPage() {
  const { t } = useI18n();
  const [tab, setTab] = useState<Tab>("orders");
  return (
    <div>
      <PageHead title={t("nav.manufacturing")} sub={t("mfg.sub")} />
      <div style={{ display: "flex", gap: 6, marginBottom: 18 }}>
        {(["orders", "boms"] as Tab[]).map((tb) => (
          <button key={tb} onClick={() => setTab(tb)} style={{
            padding: "8px 16px", borderRadius: 9, cursor: "pointer", fontSize: 14,
            border: "1px solid " + (tab === tb ? "var(--emerald-500)" : "var(--border)"),
            background: tab === tb ? "color-mix(in oklab, var(--emerald-500) 14%, transparent)" : "var(--surface)",
            color: tab === tb ? "var(--text-strong)" : "var(--text-muted)",
          }}>{tb === "orders" ? t("mfg.tab.orders") : t("mfg.tab.boms")}</button>
        ))}
      </div>
      {tab === "orders" ? <WorkOrders /> : <Boms />}
    </div>
  );
}

/* ---------------- WORK ORDERS ---------------- */
function WorkOrders() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const wosQ = useQuery({ queryKey: ["work-orders"], queryFn: mfg.listWorkOrders });
  const bomsQ = useQuery({ queryKey: ["boms"], queryFn: mfg.listBoms });
  const [bom, setBom] = useState("");
  const [qty, setQty] = useState("");
  const [error, setError] = useState<string | null>(null);
  const refresh = () => { qc.invalidateQueries({ queryKey: ["work-orders"] }); };

  const create = useMutation({
    mutationFn: () => mfg.createWorkOrder(Number(bom), Number(qty)),
    onSuccess: () => { setQty(""); setError(null); refresh(); },
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("mfg.createWOError")),
  });
  const act = useMutation({
    mutationFn: ({ id, a }: { id: number; a: "start" | "complete" | "cancel" }) => mfg.workOrderAction(id, a),
    onSuccess: () => { setError(null); refresh(); },
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("docs.actionFailed")),
  });

  return (
    <>
      <Panel>
        <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr auto", gap: 10, alignItems: "end" }}>
          <Field label={t("mfg.bomLabel")}>
            <select value={bom} onChange={(e) => setBom(e.target.value)} style={selectStyle}>
              <option value="">{t("mfg.selectBom")}</option>
              {(bomsQ.data ?? []).map((b) => <option key={b.id} value={b.id}>{b.product_name} ({t("mfg.batchOf")} {b.output_quantity})</option>)}
            </select>
          </Field>
          <Field label={t("mfg.qtyToProduce")}><Input type="number" min={0} step="0.001" value={qty} onChange={(e) => setQty(e.target.value)} /></Field>
          <Button loading={create.isPending} disabled={!bom || !(Number(qty) > 0)} onClick={() => create.mutate()}>{t("mfg.createWO")}</Button>
        </div>
        {error && <p style={{ color: "var(--rose-400)", fontSize: 13, marginTop: 10 }}>{error}</p>}
      </Panel>

      <Table head={[t("mfg.col.wo"), t("field.product"), t("docs.qty"), t("common.status"), ""]}>
        {(wosQ.data ?? []).map((w) => (
          <tr key={w.id} style={rowStyle}>
            <Td mono>{w.number}</Td><Td>{w.product_name}</Td><Td mono right>{w.quantity}</Td>
            <Td><Badge status={w.status} label={t(`mfg.status.${w.status}`)} /></Td>
            <Td right>
              <span style={{ display: "flex", gap: 6, justifyContent: "flex-end" }}>
                {w.status === "draft" && <>
                  <Button size="sm" variant="outline" onClick={() => act.mutate({ id: w.id, a: "start" })}>{t("mfg.start")}</Button>
                  <Button size="sm" onClick={() => act.mutate({ id: w.id, a: "complete" })}>{t("mfg.complete")}</Button>
                  <Button size="sm" variant="ghost" onClick={() => act.mutate({ id: w.id, a: "cancel" })}>{t("common.cancel")}</Button>
                </>}
                {w.status === "in_progress" && <Button size="sm" onClick={() => act.mutate({ id: w.id, a: "complete" })}>{t("mfg.complete")}</Button>}
              </span>
            </Td>
          </tr>
        ))}
        {wosQ.data?.length === 0 && <tr><Td colSpan={5} muted>{t("mfg.noWO")}</Td></tr>}
      </Table>
    </>
  );
}

/* ---------------- BOMs ---------------- */
function Boms() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const bomsQ = useQuery({ queryKey: ["boms"], queryFn: mfg.listBoms });
  const [creating, setCreating] = useState(false);
  return (
    <>
      <div style={{ marginBottom: 14 }}>
        <Button onClick={() => setCreating((v) => !v)} variant="outline">{creating ? t("common.close") : t("mfg.newBom")}</Button>
      </div>
      {creating && <NewBom onDone={() => { setCreating(false); qc.invalidateQueries({ queryKey: ["boms"] }); }} onCancel={() => setCreating(false)} />}
      <div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
        {(bomsQ.data ?? []).map((b) => (
          <div key={b.id} style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 12, padding: 16 }}>
            <div style={{ fontWeight: 700, color: "var(--text-strong)" }}>{b.product_name} <span style={{ color: "var(--text-muted)", fontWeight: 400 }}>· {t("mfg.batchOf")} {b.output_quantity}</span></div>
            <table style={{ width: "100%", marginTop: 8, fontSize: 13, borderCollapse: "collapse" }}>
              <tbody>
                {b.components.map((c) => (
                  <tr key={c.component}><td style={{ padding: "4px 0", color: "var(--text-body)" }}>{c.component_name}</td>
                    <td style={{ padding: "4px 0", textAlign: "right", fontFamily: "var(--font-mono)", color: "var(--text-muted)" }}>{c.quantity} {t("mfg.perBatch")} · {c.in_stock} {t("mfg.inStock")}</td></tr>
                ))}
              </tbody>
            </table>
          </div>
        ))}
        {bomsQ.data?.length === 0 && <p style={{ color: "var(--text-muted)" }}>{t("mfg.noBoms")}</p>}
      </div>
    </>
  );
}

function NewBom({ onDone, onCancel }: { onDone: () => void; onCancel: () => void }) {
  const { t } = useI18n();
  const [search, setSearch] = useState("");
  const [product, setProduct] = useState<Product | null>(null);
  const [output, setOutput] = useState("1");
  const [rows, setRows] = useState<{ product: Product; qty: string }[]>([]);
  const [compSearch, setCompSearch] = useState("");
  const [error, setError] = useState<string | null>(null);

  const prodQ = useQuery({ queryKey: ["bom-fp", search], queryFn: () => listProducts({ search, page_size: 8 }), enabled: !product });
  const compQ = useQuery({ queryKey: ["bom-comp", compSearch], queryFn: () => listProducts({ search: compSearch, page_size: 8 }), enabled: !!product && compSearch.length > 0 });

  const add = useMutation({
    mutationFn: () => mfg.createBom({
      product: product!.id, output_quantity: Number(output) || 1,
      components: rows.filter((r) => Number(r.qty) > 0).map((r) => ({ component: r.product.id, quantity: Number(r.qty) })),
    }),
    onSuccess: onDone,
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("mfg.bomError")),
  });
  const ok = product && rows.some((r) => Number(r.qty) > 0);

  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 16 }}>
      <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 12 }}>
        <strong style={{ color: "var(--text-strong)" }}>{t("mfg.newBomTitle")}</strong>
        <Button variant="ghost" size="sm" onClick={onCancel}>{t("common.cancel")}</Button>
      </div>
      {!product ? (
        <>
          <Field label={t("mfg.finishedProduct")}><Input placeholder={t("lots.searchProduct")} value={search} onChange={(e) => setSearch(e.target.value)} /></Field>
          <div style={{ display: "flex", flexDirection: "column", gap: 6, marginTop: 8 }}>
            {(prodQ.data?.results ?? []).map((p) => (
              <button key={p.id} onClick={() => setProduct(p)} style={pickStyle}>{p.name} <span style={{ color: "var(--text-muted)" }}>· {p.sku}</span></button>
            ))}
          </div>
        </>
      ) : (
        <>
          <div style={{ display: "flex", gap: 12, alignItems: "end", marginBottom: 12 }}>
            <div style={{ color: "var(--text-strong)" }}>{product.name} <button onClick={() => setProduct(null)} style={{ color: "var(--emerald-400)", background: "none", border: 0, cursor: "pointer", fontSize: 12 }}>{t("lots.change")}</button></div>
            <Field label={t("mfg.unitsPerBatch")}><Input type="number" min={1} step="0.001" value={output} onChange={(e) => setOutput(e.target.value)} style={{ width: 120 }} /></Field>
          </div>
          <div style={{ marginBottom: 8, color: "var(--text-muted)", fontSize: 13 }}>{t("mfg.components")}</div>
          {rows.map((r, i) => (
            <div key={r.product.id} style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 6 }}>
              <span style={{ flex: 1, color: "var(--text-body)" }}>{r.product.name}</span>
              <input type="number" min={0} step="0.001" value={r.qty} onChange={(e) => setRows((rs) => rs.map((x, j) => j === i ? { ...x, qty: e.target.value } : x))}
                placeholder={t("mfg.qtyPerBatch")} style={{ width: 110, background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, padding: "6px 8px", color: "var(--text-strong)" }} />
              <button onClick={() => setRows((rs) => rs.filter((_, j) => j !== i))} style={{ color: "var(--rose-400)", background: "none", border: 0, cursor: "pointer" }}>×</button>
            </div>
          ))}
          <Input placeholder={t("mfg.addComponent")} value={compSearch} onChange={(e) => setCompSearch(e.target.value)} style={{ marginTop: 6 }} />
          <div style={{ display: "flex", flexDirection: "column", gap: 6, marginTop: 6 }}>
            {(compQ.data?.results ?? []).filter((p) => p.id !== product.id && !rows.some((r) => r.product.id === p.id)).map((p) => (
              <button key={p.id} onClick={() => { setRows((rs) => [...rs, { product: p, qty: "" }]); setCompSearch(""); }} style={pickStyle}>{p.name} <span style={{ color: "var(--text-muted)" }}>· {p.sku}</span></button>
            ))}
          </div>
          <div style={{ marginTop: 14 }}>
            <Button loading={add.isPending} disabled={!ok} onClick={() => add.mutate()}>{t("mfg.createBom")}</Button>
          </div>
        </>
      )}
      {error && <p style={{ color: "var(--rose-400)", fontSize: 13, marginTop: 10 }}>{error}</p>}
    </div>
  );
}

/* shared */
const selectStyle: React.CSSProperties = { height: 38, width: "100%", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 8px" };
const rowStyle: React.CSSProperties = { borderTop: "1px solid var(--border)" };
const pickStyle: React.CSSProperties = { textAlign: "left", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 10, padding: "8px 12px", cursor: "pointer", color: "var(--text-strong)" };

function Panel({ children }: { children: React.ReactNode }) {
  return <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18 }}>{children}</div>;
}
function Table({ head, children }: { head: string[]; children: React.ReactNode }) {
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
      <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
        <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
          {head.map((h, i) => <th key={i} style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: i === 2 ? "right" : "left" }}>{h}</th>)}
        </tr></thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  );
}
function Badge({ status, label }: { status: string; label: string }) {
  const c = STATUS_COLOR[status] ?? "var(--text-muted)";
  return <span style={{ fontSize: 12, fontWeight: 600, color: c, border: `1px solid ${c}`, borderRadius: 999, padding: "2px 10px" }}>{label}</span>;
}
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block" }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
function Td({ children, mono, right, muted, colSpan }: { children: React.ReactNode; mono?: boolean; right?: boolean; muted?: boolean; colSpan?: number }) {
  return <td colSpan={colSpan} style={{ padding: "10px 14px", textAlign: right ? "right" : "left", fontFamily: mono ? "var(--font-mono)" : undefined, color: muted ? "var(--text-muted)" : "var(--text-body)", fontVariantNumeric: mono ? "tabular-nums" : undefined }}>{children}</td>;
}
