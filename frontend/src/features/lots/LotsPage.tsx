import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { listProducts } from "@/api/catalog";
import { getLotAlerts, listLots, receiveLot, type StockLot } from "@/api/lots";
import type { Product } from "@/types";

const STATUS: Record<StockLot["status"], { label: string; fg: string; bg: string; bd: string }> = {
  ok: { label: "OK", fg: "var(--emerald-400)", bg: "color-mix(in oklab, var(--emerald-500) 12%, transparent)", bd: "var(--emerald-500)" },
  expiring: { label: "Expiring", fg: "var(--amber-400, #d99a2b)", bg: "color-mix(in oklab, #d99a2b 14%, transparent)", bd: "#d99a2b" },
  expired: { label: "Expired", fg: "var(--rose-400)", bg: "color-mix(in oklab, var(--rose-400) 14%, transparent)", bd: "var(--rose-400)" },
};

export default function LotsPage() {
  const qc = useQueryClient();
  const [receiving, setReceiving] = useState(false);
  const lotsQ = useQuery({ queryKey: ["lots"], queryFn: () => listLots({ in_stock: true, page_size: 100 }) });
  const alertsQ = useQuery({ queryKey: ["lot-alerts"], queryFn: () => getLotAlerts(7) });

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ["lots"] });
    qc.invalidateQueries({ queryKey: ["lot-alerts"] });
  };

  const expired = alertsQ.data?.expired.length ?? 0;
  const expiring = alertsQ.data?.expiring.length ?? 0;

  return (
    <div>
      <PageHead title="Lots & Expiry" sub="Track perishable stock by batch and expiry date. Consumption is first-expired-first-out.">
        <Button onClick={() => setReceiving((v) => !v)}>{receiving ? "Close" : "Receive lot"}</Button>
      </PageHead>

      {(expired > 0 || expiring > 0) && (
        <div style={{ display: "flex", gap: 12, marginBottom: 16, flexWrap: "wrap" }}>
          {expired > 0 && <AlertPill tone="expired" text={`${expired} lot${expired > 1 ? "s" : ""} expired`} />}
          {expiring > 0 && <AlertPill tone="expiring" text={`${expiring} lot${expiring > 1 ? "s" : ""} expiring within 7 days`} />}
        </div>
      )}

      {receiving && <ReceiveLot onDone={() => { setReceiving(false); refresh(); }} onCancel={() => setReceiving(false)} />}

      <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead>
            <tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
              <Th>Lot</Th><Th>Product</Th><Th>Warehouse</Th><Th>Expiry</Th><Th right>Days</Th><Th right>Qty</Th><Th>Status</Th>
            </tr>
          </thead>
          <tbody>
            {(lotsQ.data?.results ?? []).map((l) => {
              const s = STATUS[l.status];
              return (
                <tr key={l.id} style={{ borderTop: "1px solid var(--border)" }}>
                  <Td mono>{l.lot_number}</Td>
                  <Td>{l.product_name ?? l.sku}</Td>
                  <Td>{l.warehouse_name ?? "—"}</Td>
                  <Td mono>{l.expiry_date ?? "—"}</Td>
                  <Td right mono>{l.days_to_expiry ?? "—"}</Td>
                  <Td right mono>{Number(l.quantity).toFixed(3)}</Td>
                  <Td>
                    <span style={{ fontSize: 12, fontWeight: 600, color: s.fg, background: s.bg, border: `1px solid ${s.bd}`, borderRadius: 999, padding: "2px 10px" }}>
                      {s.label}
                    </span>
                  </Td>
                </tr>
              );
            })}
            {lotsQ.data?.results.length === 0 && <tr><Td colSpan={7} muted>No lots in stock. Receive one to start tracking batches.</Td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function ReceiveLot({ onDone, onCancel }: { onDone: () => void; onCancel: () => void }) {
  const [search, setSearch] = useState("");
  const [product, setProduct] = useState<Product | null>(null);
  const [lotNumber, setLotNumber] = useState("");
  const [expiry, setExpiry] = useState("");
  const [qty, setQty] = useState("");
  const [error, setError] = useState<string | null>(null);

  const productsQ = useQuery({
    queryKey: ["lot-products", search],
    queryFn: () => listProducts({ search, page_size: 10, is_active: true }),
    enabled: !product,
  });

  const submit = useMutation({
    mutationFn: () =>
      receiveLot({
        product: product!.id,
        lot_number: lotNumber.trim(),
        expiry_date: expiry || null,
        quantity: Number(qty),
      }),
    onSuccess: onDone,
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not receive the lot."),
  });

  const canSubmit = product && lotNumber.trim() && Number(qty) > 0 && !submit.isPending;

  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 20, marginBottom: 18 }}>
      <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 12 }}>
        <strong style={{ color: "var(--text-strong)" }}>Receive a lot</strong>
        <Button variant="ghost" size="sm" onClick={onCancel}>Cancel</Button>
      </div>

      {!product ? (
        <>
          <Input placeholder="Search product…" value={search} onChange={(e) => setSearch(e.target.value)} />
          <div style={{ display: "flex", flexDirection: "column", gap: 6, marginTop: 10 }}>
            {(productsQ.data?.results ?? []).map((p) => (
              <button key={p.id} onClick={() => setProduct(p)} style={{ textAlign: "left", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 10, padding: "8px 12px", cursor: "pointer", color: "var(--text-strong)" }}>
                {p.name} <span style={{ color: "var(--text-muted)" }}>· {p.sku}</span>
              </button>
            ))}
          </div>
        </>
      ) : (
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(150px, 1fr))", gap: 12, alignItems: "end" }}>
          <Field label="Product"><div style={{ padding: "9px 0", color: "var(--text-strong)" }}>{product.name} <button onClick={() => setProduct(null)} style={{ marginLeft: 6, color: "var(--emerald-400)", background: "none", border: 0, cursor: "pointer", fontSize: 12 }}>change</button></div></Field>
          <Field label="Lot number"><Input value={lotNumber} onChange={(e) => setLotNumber(e.target.value)} placeholder="e.g. B-2026-08" /></Field>
          <Field label="Expiry date"><Input type="date" value={expiry} onChange={(e) => setExpiry(e.target.value)} /></Field>
          <Field label="Quantity"><Input type="number" min={0} step="0.001" value={qty} onChange={(e) => setQty(e.target.value)} /></Field>
          <Button loading={submit.isPending} disabled={!canSubmit} onClick={() => submit.mutate()}>Receive</Button>
        </div>
      )}
      {error && <p style={{ color: "var(--rose-400)", fontSize: 13, marginTop: 10 }}>{error}</p>}
    </div>
  );
}

function AlertPill({ tone, text }: { tone: "expired" | "expiring"; text: string }) {
  const s = STATUS[tone];
  return <div style={{ color: s.fg, background: s.bg, border: `1px solid ${s.bd}`, borderRadius: 10, padding: "8px 14px", fontSize: 14, fontWeight: 600 }}>{text}</div>;
}
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label style={{ display: "block" }}>
      <span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>
      {children}
    </label>
  );
}
function Th({ children, right }: { children: React.ReactNode; right?: boolean }) {
  return <th style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: right ? "right" : "left" }}>{children}</th>;
}
function Td({ children, mono, right, muted, colSpan }: { children: React.ReactNode; mono?: boolean; right?: boolean; muted?: boolean; colSpan?: number }) {
  return <td colSpan={colSpan} style={{ padding: "10px 14px", textAlign: right ? "right" : "left", fontFamily: mono ? "var(--font-mono)" : undefined, color: muted ? "var(--text-muted)" : "var(--text-body)", fontVariantNumeric: mono ? "tabular-nums" : undefined }}>{children}</td>;
}
