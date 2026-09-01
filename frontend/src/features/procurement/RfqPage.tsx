import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { listProducts } from "@/api/catalog";
import { listSuppliers } from "@/api/reorder";
import * as rfqApi from "@/api/rfq";
import type { Product } from "@/types";
import { useI18n } from "@/lib/i18n";

const money = (n: string | number) => Number(n).toFixed(2);

export default function RfqPage() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const rfqsQ = useQuery({ queryKey: ["rfqs"], queryFn: rfqApi.listRfqs });
  const [selected, setSelected] = useState<number | null>(null);
  const [creating, setCreating] = useState(false);

  return (
    <div>
      <PageHead title={t("rfq.title")} sub={t("rfq.sub")}>
        <Button onClick={() => setCreating((v) => !v)}>{creating ? t("common.close") : t("rfq.new")}</Button>
      </PageHead>

      {creating && <NewRfq onDone={() => { setCreating(false); qc.invalidateQueries({ queryKey: ["rfqs"] }); }} onCancel={() => setCreating(false)} />}

      <div style={{ display: "grid", gridTemplateColumns: "280px 1fr", gap: 18, alignItems: "start" }}>
        <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
          {(rfqsQ.data ?? []).map((r) => (
            <button key={r.id} onClick={() => setSelected(r.id)} style={{
              display: "block", width: "100%", textAlign: "left", padding: "12px 14px", cursor: "pointer",
              background: selected === r.id ? "color-mix(in oklab, var(--emerald-500) 12%, transparent)" : "transparent",
              border: 0, borderBottom: "1px solid var(--border)", color: "var(--text-strong)",
            }}>
              <div style={{ fontWeight: 600 }}>{r.title} <span style={{ fontFamily: "var(--font-mono)", fontWeight: 400, color: "var(--text-muted)", fontSize: 12 }}>{r.number}</span></div>
              <div style={{ fontSize: 12, color: "var(--text-muted)" }}>{r.bids_count} {t("rfq.bids")} · {t(`rfq.status.${r.status}`)}</div>
            </button>
          ))}
          {rfqsQ.data?.length === 0 && <p style={{ padding: 14, color: "var(--text-muted)", fontSize: 13 }}>{t("rfq.none")}</p>}
        </div>

        <div>{selected ? <RfqDetail rfqId={selected} /> : <p style={{ color: "var(--text-muted)" }}>{t("rfq.select")}</p>}</div>
      </div>
    </div>
  );
}

function RfqDetail({ rfqId }: { rfqId: number }) {
  const { t } = useI18n();
  const qc = useQueryClient();
  const q = useQuery({ queryKey: ["rfq", rfqId], queryFn: () => rfqApi.getRfq(rfqId) });
  const [bidding, setBidding] = useState(false);
  const refresh = () => { qc.invalidateQueries({ queryKey: ["rfq", rfqId] }); qc.invalidateQueries({ queryKey: ["rfqs"] }); };

  const award = useMutation({ mutationFn: (bidId: number) => rfqApi.awardBid(rfqId, bidId), onSuccess: refresh });

  const rfq = q.data;
  if (!rfq) return <p style={{ color: "var(--text-muted)" }}>{t("common.loading")}</p>;
  const open = rfq.status === "open";

  return (
    <>
      <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 16, marginBottom: 16 }}>
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 8 }}>
          <strong style={{ color: "var(--text-strong)" }}>{rfq.title} — {t("rfq.requestedItems")}</strong>
          {open && <Button size="sm" variant="outline" onClick={() => setBidding((v) => !v)}>{bidding ? t("common.close") : t("rfq.enterBid")}</Button>}
        </div>
        <table style={{ width: "100%", fontSize: 13, borderCollapse: "collapse" }}>
          <tbody>
            {rfq.lines.map((l) => (
              <tr key={l.id}><td style={{ padding: "4px 0", color: "var(--text-body)" }}>{l.product_name} <span style={{ color: "var(--text-muted)" }}>· {l.sku}</span></td>
                <td style={{ padding: "4px 0", textAlign: "right", fontFamily: "var(--font-mono)", color: "var(--text-muted)" }}>{t("rfq.qtyLabel")} {l.quantity}</td></tr>
            ))}
          </tbody>
        </table>
      </div>

      {bidding && open && <BidForm rfq={rfq} onDone={() => { setBidding(false); refresh(); }} />}

      <h3 style={{ font: "600 15px var(--font-sans)", color: "var(--text-strong)", margin: "4px 0 10px" }}>{t("rfq.bidComparison")}</h3>
      <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
            <Th>{t("field.supplier")}</Th><Th right>{t("common.total")}</Th><Th>{t("common.status")}</Th><Th></Th>
          </tr></thead>
          <tbody>
            {rfq.comparison.map((b) => (
              <tr key={b.id} style={{ borderTop: "1px solid var(--border)", background: b.is_lowest ? "color-mix(in oklab, var(--emerald-500) 8%, transparent)" : undefined }}>
                <Td>{b.supplier_name} {b.is_lowest && <span style={{ marginInlineStart: 8, fontSize: 11, color: "var(--emerald-400)", border: "1px solid var(--emerald-500)", borderRadius: 999, padding: "1px 7px" }}>{t("rfq.lowest")}</span>}</Td>
                <Td right mono style={{ fontWeight: 600, color: "var(--text-strong)" }}>{money(b.total_amount)} TND</Td>
                <Td><span style={{ fontSize: 12, fontWeight: 600, color: b.status === "awarded" ? "var(--emerald-400)" : b.status === "rejected" ? "var(--rose-400)" : "var(--text-muted)" }}>{t(`rfq.status.${b.status}`)}</span></Td>
                <Td right>{open && <Button size="sm" loading={award.isPending} onClick={() => award.mutate(b.id)}>{t("rfq.award")}</Button>}</Td>
              </tr>
            ))}
            {rfq.comparison.length === 0 && <tr><Td colSpan={4} muted>{t("rfq.noBids")}</Td></tr>}
          </tbody>
        </table>
      </div>
    </>
  );
}

function BidForm({ rfq, onDone }: { rfq: rfqApi.Rfq; onDone: () => void }) {
  const { t } = useI18n();
  const suppliersQ = useQuery({ queryKey: ["suppliers-min"], queryFn: listSuppliers });
  const [supplier, setSupplier] = useState("");
  const [prices, setPrices] = useState<Record<number, string>>({});
  const [note, setNote] = useState("");
  const [error, setError] = useState<string | null>(null);

  const submit = useMutation({
    mutationFn: () => rfqApi.submitBid(rfq.id, {
      supplier: Number(supplier), note,
      prices: Object.fromEntries(rfq.lines.map((l) => [l.id, Number(prices[l.id] || 0)])),
    }),
    onSuccess: onDone,
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("rfq.submitError")),
  });
  const allPriced = rfq.lines.every((l) => Number(prices[l.id]) > 0);

  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 16 }}>
      <div style={{ marginBottom: 12, maxWidth: 300 }}>
        <span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{t("field.supplier")}</span>
        <select value={supplier} onChange={(e) => setSupplier(e.target.value)} style={{ width: "100%", height: 38, background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 8px" }}>
          <option value="">{t("rfq.selectSupplier")}</option>
          {(suppliersQ.data ?? []).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
        </select>
      </div>
      <table style={{ width: "100%", fontSize: 14, borderCollapse: "collapse" }}>
        <thead><tr style={{ color: "var(--text-muted)", textAlign: "left" }}><Th>{t("rfq.item")}</Th><Th right>{t("docs.qty")}</Th><Th right>{t("docs.unitPrice")}</Th></tr></thead>
        <tbody>
          {rfq.lines.map((l) => (
            <tr key={l.id} style={{ borderTop: "1px solid var(--border)" }}>
              <Td>{l.product_name}</Td><Td right mono>{l.quantity}</Td>
              <Td right><input type="number" min={0} step="0.01" value={prices[l.id] ?? ""} onChange={(e) => setPrices((p) => ({ ...p, [l.id]: e.target.value }))}
                style={{ width: 100, textAlign: "right", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, padding: "6px 8px", color: "var(--text-strong)" }} /></Td>
            </tr>
          ))}
        </tbody>
      </table>
      <div style={{ display: "flex", gap: 10, alignItems: "center", marginTop: 12 }}>
        <Input placeholder={t("rfq.noteOptional")} value={note} onChange={(e) => setNote(e.target.value)} style={{ flex: 1 }} />
        <Button loading={submit.isPending} disabled={!supplier || !allPriced} onClick={() => submit.mutate()}>{t("rfq.submitBid")}</Button>
      </div>
      {error && <p style={{ color: "var(--rose-400)", fontSize: 13, marginTop: 8 }}>{error}</p>}
    </div>
  );
}

function NewRfq({ onDone, onCancel }: { onDone: () => void; onCancel: () => void }) {
  const { t } = useI18n();
  const [title, setTitle] = useState("");
  const [rows, setRows] = useState<{ product: Product; qty: string }[]>([]);
  const [search, setSearch] = useState("");
  const [error, setError] = useState<string | null>(null);
  const prodQ = useQuery({ queryKey: ["rfq-prod", search], queryFn: () => listProducts({ search, page_size: 8 }), enabled: search.length > 0 });

  const add = useMutation({
    mutationFn: () => rfqApi.createRfq({ title, lines: rows.filter((r) => Number(r.qty) > 0).map((r) => ({ product: r.product.id, quantity: Number(r.qty) })) }),
    onSuccess: onDone,
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("rfq.createError")),
  });
  const ok = title.trim() && rows.some((r) => Number(r.qty) > 0);

  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18 }}>
      <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 12 }}>
        <strong style={{ color: "var(--text-strong)" }}>{t("rfq.newTitle")}</strong>
        <Button variant="ghost" size="sm" onClick={onCancel}>{t("common.cancel")}</Button>
      </div>
      <Input placeholder={t("rfq.titlePlaceholder")} value={title} onChange={(e) => setTitle(e.target.value)} />
      <div style={{ margin: "12px 0 6px", color: "var(--text-muted)", fontSize: 13 }}>{t("rfq.items")}</div>
      {rows.map((r, i) => (
        <div key={r.product.id} style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 6 }}>
          <span style={{ flex: 1, color: "var(--text-body)" }}>{r.product.name}</span>
          <input type="number" min={0} step="0.001" value={r.qty} onChange={(e) => setRows((rs) => rs.map((x, j) => j === i ? { ...x, qty: e.target.value } : x))}
            placeholder={t("rfq.qtyLabel")} style={{ width: 100, background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, padding: "6px 8px", color: "var(--text-strong)" }} />
          <button onClick={() => setRows((rs) => rs.filter((_, j) => j !== i))} style={{ color: "var(--rose-400)", background: "none", border: 0, cursor: "pointer" }}>×</button>
        </div>
      ))}
      <Input placeholder={t("rfq.addProduct")} value={search} onChange={(e) => setSearch(e.target.value)} style={{ marginTop: 6 }} />
      <div style={{ display: "flex", flexDirection: "column", gap: 6, marginTop: 6 }}>
        {(prodQ.data?.results ?? []).filter((p) => !rows.some((r) => r.product.id === p.id)).map((p) => (
          <button key={p.id} onClick={() => { setRows((rs) => [...rs, { product: p, qty: "" }]); setSearch(""); }} style={{ textAlign: "left", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 10, padding: "8px 12px", cursor: "pointer", color: "var(--text-strong)" }}>{p.name} <span style={{ color: "var(--text-muted)" }}>· {p.sku}</span></button>
        ))}
      </div>
      <div style={{ marginTop: 14 }}><Button loading={add.isPending} disabled={!ok} onClick={() => add.mutate()}>{t("rfq.createBtn")}</Button></div>
      {error && <p style={{ color: "var(--rose-400)", fontSize: 13, marginTop: 8 }}>{error}</p>}
    </div>
  );
}

function Th({ children, right }: { children?: React.ReactNode; right?: boolean }) {
  return <th style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: right ? "right" : "left" }}>{children}</th>;
}
function Td({ children, mono, right, muted, colSpan, style }: { children: React.ReactNode; mono?: boolean; right?: boolean; muted?: boolean; colSpan?: number; style?: React.CSSProperties }) {
  return <td colSpan={colSpan} style={{ padding: "10px 14px", textAlign: right ? "right" : "left", fontFamily: mono ? "var(--font-mono)" : undefined, color: muted ? "var(--text-muted)" : "var(--text-body)", fontVariantNumeric: mono ? "tabular-nums" : undefined, ...style }}>{children}</td>;
}
