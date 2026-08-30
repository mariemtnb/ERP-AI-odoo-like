import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { listProducts } from "@/api/catalog";
import {
  checkout as apiCheckout,
  closeSession as apiClose,
  getCurrentSession,
  openSession as apiOpen,
  type PosSession,
} from "@/api/pos";
import type { Product } from "@/types";

type BasketLine = { product: Product; quantity: number };
type Method = "cash" | "card" | "cheque";

const money = (n: number) => n.toFixed(2);

export default function PosPage() {
  const qc = useQueryClient();
  const sessionQ = useQuery({ queryKey: ["pos-session"], queryFn: getCurrentSession });
  const session = sessionQ.data ?? null;

  if (sessionQ.isLoading) {
    return (
      <div>
        <PageHead title="Point of Sale" sub="Loading the till…" />
      </div>
    );
  }

  return session && session.status === "open" ? (
    <Register session={session} onChanged={() => qc.invalidateQueries({ queryKey: ["pos-session"] })} />
  ) : (
    <OpenTill onOpened={() => qc.invalidateQueries({ queryKey: ["pos-session"] })} />
  );
}

/* ---------- opening the till ---------- */
function OpenTill({ onOpened }: { onOpened: () => void }) {
  const [float, setFloat] = useState("0");
  const [error, setError] = useState<string | null>(null);
  const open = useMutation({
    mutationFn: () => apiOpen(Number(float) || 0),
    onSuccess: onOpened,
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Could not open the till."),
  });

  return (
    <div>
      <PageHead title="Point of Sale" sub="Open a till to start ringing up sales." />
      <div
        style={{
          maxWidth: 380,
          background: "var(--surface)",
          border: "1px solid var(--border)",
          borderRadius: 14,
          padding: 24,
        }}
      >
        <label style={{ font: "500 13px var(--font-sans)", color: "var(--text-muted)" }}>
          Opening cash float (TND)
        </label>
        <Input
          type="number"
          min={0}
          step="0.01"
          value={float}
          onChange={(e) => setFloat(e.target.value)}
          style={{ marginTop: 8 }}
        />
        {error && <p style={{ color: "var(--rose-400)", fontSize: 13, marginTop: 10 }}>{error}</p>}
        <Button style={{ marginTop: 16, width: "100%" }} loading={open.isPending} onClick={() => open.mutate()}>
          Open till
        </Button>
      </div>
    </div>
  );
}

/* ---------- the register ---------- */
function Register({ session, onChanged }: { session: PosSession; onChanged: () => void }) {
  const [search, setSearch] = useState("");
  const [basket, setBasket] = useState<BasketLine[]>([]);
  const [method, setMethod] = useState<Method>("cash");
  const [tendered, setTendered] = useState("");
  const [flash, setFlash] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [closing, setClosing] = useState(false);

  const productsQ = useQuery({
    queryKey: ["pos-products", search],
    queryFn: () => listProducts({ search, page_size: 20, is_active: true }),
  });

  const total = useMemo(
    () => basket.reduce((s, l) => s + Number(l.product.sale_price) * l.quantity, 0),
    [basket]
  );
  const change = Math.max(0, (Number(tendered) || 0) - total);

  const add = (p: Product) =>
    setBasket((b) => {
      const found = b.find((l) => l.product.id === p.id);
      return found
        ? b.map((l) => (l.product.id === p.id ? { ...l, quantity: l.quantity + 1 } : l))
        : [...b, { product: p, quantity: 1 }];
    });
  const setQty = (id: number, q: number) =>
    setBasket((b) => b.flatMap((l) => (l.product.id === id ? (q > 0 ? [{ ...l, quantity: q }] : []) : [l])));

  const charge = useMutation({
    mutationFn: () =>
      apiCheckout({
        lines: basket.map((l) => ({
          product: l.product.id,
          quantity: l.quantity,
          unit_price: Number(l.product.sale_price),
        })),
        payments: [{ method, amount: method === "cash" ? Number(tendered) || total : total }],
      }),
    onSuccess: (order) => {
      setFlash(`${order.number} charged — change ${money(Number(order.change_due))} TND`);
      setBasket([]);
      setTendered("");
      setError(null);
      onChanged();
    },
    onError: (e: any) => setError(e?.response?.data?.detail ?? "Checkout failed."),
  });

  const canCharge =
    basket.length > 0 && (method !== "cash" || (Number(tendered) || 0) + 0.001 >= total) && !charge.isPending;

  return (
    <div>
      <PageHead title="Point of Sale" sub={`Till open · ${session.orders_count} sales · float ${session.opening_float} TND`}>
        <Button variant="outline" onClick={() => setClosing(true)}>
          Close till
        </Button>
      </PageHead>

      {flash && (
        <div
          style={{
            background: "color-mix(in oklab, var(--emerald-500) 12%, transparent)",
            border: "1px solid var(--emerald-500)",
            color: "var(--text-strong)",
            borderRadius: 10,
            padding: "10px 14px",
            marginBottom: 14,
            fontSize: 14,
          }}
        >
          {flash}
        </div>
      )}

      <div style={{ display: "grid", gridTemplateColumns: "1.3fr 1fr", gap: 18, alignItems: "start" }}>
        {/* catalog */}
        <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 16 }}>
          <Input placeholder="Search product by name or SKU…" value={search} onChange={(e) => setSearch(e.target.value)} />
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 8, marginTop: 14 }}>
            {(productsQ.data?.results ?? []).map((p) => (
              <button
                key={p.id}
                onClick={() => add(p)}
                style={{
                  textAlign: "left",
                  background: "var(--surface-hover)",
                  border: "1px solid var(--border)",
                  borderRadius: 10,
                  padding: "10px 12px",
                  cursor: "pointer",
                }}
              >
                <div style={{ font: "600 14px var(--font-sans)", color: "var(--text-strong)" }}>{p.name}</div>
                <div style={{ fontSize: 12, color: "var(--text-muted)" }}>
                  {p.sku} · {money(Number(p.sale_price))} TND · stock {p.quantity_in_stock}
                </div>
              </button>
            ))}
            {productsQ.data?.results.length === 0 && (
              <p style={{ color: "var(--text-muted)", fontSize: 13 }}>No products match “{search}”.</p>
            )}
          </div>
        </div>

        {/* basket */}
        <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 16 }}>
          <h3 style={{ margin: "0 0 10px", font: "600 15px var(--font-sans)", color: "var(--text-strong)" }}>Basket</h3>
          {basket.length === 0 && <p style={{ color: "var(--text-muted)", fontSize: 13 }}>Tap products to add them.</p>}
          {basket.map((l) => (
            <div key={l.product.id} style={{ display: "flex", alignItems: "center", gap: 8, padding: "6px 0" }}>
              <div style={{ flex: 1 }}>
                <div style={{ font: "500 14px var(--font-sans)", color: "var(--text-strong)" }}>{l.product.name}</div>
                <div style={{ fontSize: 12, color: "var(--text-muted)" }}>{money(Number(l.product.sale_price))} TND each</div>
              </div>
              <input
                type="number"
                min={0}
                value={l.quantity}
                onChange={(e) => setQty(l.product.id, Number(e.target.value))}
                style={{ width: 56, textAlign: "center", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, padding: "6px" }}
              />
              <div style={{ width: 78, textAlign: "right", fontVariantNumeric: "tabular-nums", color: "var(--text-strong)" }}>
                {money(Number(l.product.sale_price) * l.quantity)}
              </div>
            </div>
          ))}

          <div style={{ borderTop: "1px solid var(--border)", margin: "12px 0", paddingTop: 12, display: "flex", justifyContent: "space-between", font: "700 18px var(--font-sans)", color: "var(--text-strong)" }}>
            <span>Total</span>
            <span style={{ fontVariantNumeric: "tabular-nums" }}>{money(total)} TND</span>
          </div>

          <div style={{ display: "flex", gap: 6, marginBottom: 10 }}>
            {(["cash", "card", "cheque"] as Method[]).map((m) => (
              <button
                key={m}
                onClick={() => setMethod(m)}
                style={{
                  flex: 1,
                  textTransform: "capitalize",
                  padding: "7px 0",
                  borderRadius: 8,
                  cursor: "pointer",
                  border: "1px solid " + (method === m ? "var(--emerald-500)" : "var(--border)"),
                  background: method === m ? "color-mix(in oklab, var(--emerald-500) 14%, transparent)" : "var(--surface-hover)",
                  color: method === m ? "var(--text-strong)" : "var(--text-muted)",
                  fontSize: 13,
                }}
              >
                {m}
              </button>
            ))}
          </div>

          {method === "cash" && (
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 10 }}>
              <span style={{ fontSize: 13, color: "var(--text-muted)" }}>Cash tendered</span>
              <input
                type="number"
                min={0}
                step="0.01"
                value={tendered}
                placeholder={money(total)}
                onChange={(e) => setTendered(e.target.value)}
                style={{ width: 110, textAlign: "right", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, padding: "7px 8px" }}
              />
            </div>
          )}
          {method === "cash" && Number(tendered) > 0 && (
            <div style={{ display: "flex", justifyContent: "space-between", fontSize: 14, color: "var(--text-strong)", marginBottom: 10 }}>
              <span>Change</span>
              <span style={{ fontVariantNumeric: "tabular-nums" }}>{money(change)} TND</span>
            </div>
          )}

          {error && <p style={{ color: "var(--rose-400)", fontSize: 13, marginBottom: 8 }}>{error}</p>}
          <Button style={{ width: "100%" }} disabled={!canCharge} loading={charge.isPending} onClick={() => charge.mutate()}>
            Charge {money(total)} TND
          </Button>
        </div>
      </div>

      {closing && <CloseDialog session={session} onDone={onChanged} onClose={() => setClosing(false)} />}
    </div>
  );
}

/* ---------- closing the till ---------- */
function CloseDialog({ session, onDone, onClose }: { session: PosSession; onDone: () => void; onClose: () => void }) {
  const [counted, setCounted] = useState("");
  const [result, setResult] = useState<PosSession | null>(null);
  const close = useMutation({
    mutationFn: () => apiClose(session.id, Number(counted) || 0),
    onSuccess: (s) => setResult(s),
  });

  return (
    <div
      role="dialog"
      style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,.45)", display: "grid", placeItems: "center", zIndex: 50 }}
      onClick={onClose}
    >
      <div
        onClick={(e) => e.stopPropagation()}
        style={{ width: 360, background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 22 }}
      >
        {result ? (
          <>
            <h3 style={{ margin: "0 0 12px", color: "var(--text-strong)" }}>Till closed</h3>
            <Row label="Expected in drawer" value={`${result.expected_cash} TND`} />
            <Row label="Counted" value={`${result.closing_counted} TND`} />
            <Row
              label="Variance"
              value={`${(result.variance ?? 0) >= 0 ? "+" : ""}${(result.variance ?? 0).toFixed(2)} TND`}
              strong
            />
            <Button style={{ marginTop: 16, width: "100%" }} onClick={() => { onDone(); onClose(); }}>
              Done
            </Button>
          </>
        ) : (
          <>
            <h3 style={{ margin: "0 0 6px", color: "var(--text-strong)" }}>Close till</h3>
            <p style={{ fontSize: 13, color: "var(--text-muted)", marginTop: 0 }}>
              Count the cash in the drawer and enter it. We compare against the opening float plus cash takings.
            </p>
            <input
              type="number"
              min={0}
              step="0.01"
              value={counted}
              onChange={(e) => setCounted(e.target.value)}
              placeholder="Counted cash (TND)"
              style={{ width: "100%", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, padding: "9px 10px", marginBottom: 14 }}
            />
            <div style={{ display: "flex", gap: 8 }}>
              <Button variant="outline" style={{ flex: 1 }} onClick={onClose}>Cancel</Button>
              <Button style={{ flex: 1 }} loading={close.isPending} onClick={() => close.mutate()}>Close</Button>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  return (
    <div style={{ display: "flex", justifyContent: "space-between", padding: "6px 0", fontSize: 14, color: strong ? "var(--text-strong)" : "var(--text-muted)", fontWeight: strong ? 700 : 400 }}>
      <span>{label}</span>
      <span style={{ fontVariantNumeric: "tabular-nums" }}>{value}</span>
    </div>
  );
}
