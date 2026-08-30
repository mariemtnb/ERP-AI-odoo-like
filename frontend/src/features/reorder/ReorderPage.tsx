import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { listProducts } from "@/api/catalog";
import {
  createRule,
  deleteRule,
  getSuggestions,
  listRules,
  listSuppliers,
  runReplenishment,
  type RunResult,
} from "@/api/reorder";
import type { Product } from "@/types";

export default function ReorderPage() {
  const qc = useQueryClient();
  const rulesQ = useQuery({ queryKey: ["reorder-rules"], queryFn: listRules });
  const suggQ = useQuery({ queryKey: ["reorder-suggestions"], queryFn: getSuggestions });
  const [runResult, setRunResult] = useState<RunResult | null>(null);
  const [adding, setAdding] = useState(false);

  const refresh = () => {
    qc.invalidateQueries({ queryKey: ["reorder-rules"] });
    qc.invalidateQueries({ queryKey: ["reorder-suggestions"] });
  };

  const run = useMutation({
    mutationFn: runReplenishment,
    onSuccess: (r) => { setRunResult(r); refresh(); },
  });
  const del = useMutation({ mutationFn: deleteRule, onSuccess: refresh });

  const suggestions = suggQ.data ?? [];

  return (
    <div>
      <PageHead title="Reordering" sub="Set reorder points and turn low stock into draft purchase orders in one click.">
        <Button onClick={() => setAdding((v) => !v)} variant="outline">{adding ? "Close" : "Add rule"}</Button>
        <Button loading={run.isPending} disabled={suggestions.length === 0} onClick={() => run.mutate()}>
          Generate draft POs ({suggestions.filter((s) => s.supplier).length})
        </Button>
      </PageHead>

      {runResult && (
        <div style={{ background: "color-mix(in oklab, var(--emerald-500) 10%, transparent)", border: "1px solid var(--emerald-500)", borderRadius: 12, padding: "12px 16px", marginBottom: 16, fontSize: 14, color: "var(--text-strong)" }}>
          Created {runResult.created.length} draft PO{runResult.created.length !== 1 ? "s" : ""}
          {runResult.created.length > 0 && ": " + runResult.created.map((c) => c.number).join(", ")}.
          {runResult.unassigned.length > 0 && ` ${runResult.unassigned.length} product(s) below point have no supplier — assign one on their rule.`}
        </div>
      )}

      {adding && <AddRule onDone={() => { setAdding(false); refresh(); }} onCancel={() => setAdding(false)} />}

      <h3 style={{ font: "600 15px var(--font-sans)", color: "var(--text-strong)", margin: "4px 0 10px" }}>
        Below reorder point ({suggestions.length})
      </h3>
      <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden", marginBottom: 24 }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
            <Th>Product</Th><Th right>On hand</Th><Th right>Reorder point</Th><Th right>Suggested order</Th><Th>Supplier</Th>
          </tr></thead>
          <tbody>
            {suggestions.map((s) => (
              <tr key={s.product} style={{ borderTop: "1px solid var(--border)" }}>
                <Td>{s.product_name} <span style={{ color: "var(--text-muted)" }}>· {s.sku}</span></Td>
                <Td right mono style={{ color: "var(--rose-400)" }}>{s.current_stock}</Td>
                <Td right mono>{s.min_qty}</Td>
                <Td right mono>{s.reorder_qty}</Td>
                <Td>{s.supplier_name ?? <span style={{ color: "var(--amber-400,#d99a2b)" }}>— none —</span>}</Td>
              </tr>
            ))}
            {suggestions.length === 0 && <tr><Td colSpan={5} muted>Everything is above its reorder point.</Td></tr>}
          </tbody>
        </table>
      </div>

      <h3 style={{ font: "600 15px var(--font-sans)", color: "var(--text-strong)", margin: "4px 0 10px" }}>Rules</h3>
      <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
            <Th>Product</Th><Th right>Reorder point</Th><Th right>Order qty</Th><Th>Supplier</Th><Th>Active</Th><Th></Th>
          </tr></thead>
          <tbody>
            {(rulesQ.data ?? []).map((r) => (
              <tr key={r.id} style={{ borderTop: "1px solid var(--border)" }}>
                <Td>{r.product_name} <span style={{ color: "var(--text-muted)" }}>· {r.sku}</span></Td>
                <Td right mono>{r.min_qty}</Td>
                <Td right mono>{r.reorder_qty}</Td>
                <Td>{r.supplier_name ?? "—"}</Td>
                <Td>{r.is_active ? "Yes" : "No"}</Td>
                <Td right><button onClick={() => del.mutate(r.id)} style={{ color: "var(--rose-400)", background: "none", border: 0, cursor: "pointer", fontSize: 13 }}>Delete</button></Td>
              </tr>
            ))}
            {rulesQ.data?.length === 0 && <tr><Td colSpan={6} muted>No reorder rules yet. Add one to start.</Td></tr>}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function AddRule({ onDone, onCancel }: { onDone: () => void; onCancel: () => void }) {
  const [search, setSearch] = useState("");
  const [product, setProduct] = useState<Product | null>(null);
  const [supplier, setSupplier] = useState<string>("");
  const [min, setMin] = useState("");
  const [qty, setQty] = useState("");
  const [error, setError] = useState<string | null>(null);

  const productsQ = useQuery({ queryKey: ["reorder-prod", search], queryFn: () => listProducts({ search, page_size: 10 }), enabled: !product });
  const suppliersQ = useQuery({ queryKey: ["suppliers-min"], queryFn: listSuppliers });

  const submit = useMutation({
    mutationFn: () => createRule({ product: product!.id, supplier: supplier ? Number(supplier) : null, min_qty: Number(min), reorder_qty: Number(qty) }),
    onSuccess: onDone,
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not create the rule (already has one?)."),
  });

  const canSubmit = product && Number(qty) > 0 && min !== "" && !submit.isPending;

  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 20, marginBottom: 18 }}>
      <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 12 }}>
        <strong style={{ color: "var(--text-strong)" }}>New reorder rule</strong>
        <Button variant="ghost" size="sm" onClick={onCancel}>Cancel</Button>
      </div>
      {!product ? (
        <>
          <Input placeholder="Search product…" value={search} onChange={(e) => setSearch(e.target.value)} />
          <div style={{ display: "flex", flexDirection: "column", gap: 6, marginTop: 10 }}>
            {(productsQ.data?.results ?? []).map((p) => (
              <button key={p.id} onClick={() => setProduct(p)} style={{ textAlign: "left", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 10, padding: "8px 12px", cursor: "pointer", color: "var(--text-strong)" }}>
                {p.name} <span style={{ color: "var(--text-muted)" }}>· {p.sku} · stock {p.quantity_in_stock}</span>
              </button>
            ))}
          </div>
        </>
      ) : (
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(150px, 1fr))", gap: 12, alignItems: "end" }}>
          <Field label="Product"><div style={{ padding: "9px 0", color: "var(--text-strong)" }}>{product.name} <button onClick={() => setProduct(null)} style={{ marginLeft: 6, color: "var(--emerald-400)", background: "none", border: 0, cursor: "pointer", fontSize: 12 }}>change</button></div></Field>
          <Field label="Reorder point"><Input type="number" min={0} step="0.001" value={min} onChange={(e) => setMin(e.target.value)} /></Field>
          <Field label="Order quantity"><Input type="number" min={0} step="0.001" value={qty} onChange={(e) => setQty(e.target.value)} /></Field>
          <Field label="Preferred supplier">
            <select value={supplier} onChange={(e) => setSupplier(e.target.value)} style={{ width: "100%", height: 38, background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 8px" }}>
              <option value="">— none —</option>
              {(suppliersQ.data ?? []).map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </select>
          </Field>
          <Button loading={submit.isPending} disabled={!canSubmit} onClick={() => submit.mutate()}>Add rule</Button>
        </div>
      )}
      {error && <p style={{ color: "var(--rose-400)", fontSize: 13, marginTop: 10 }}>{error}</p>}
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
