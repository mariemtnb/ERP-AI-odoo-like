import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  createCreditNote,
  getReturnable,
  listConfirmedSales,
  listCreditNotes,
} from "@/api/returns";
import type { BusinessDoc } from "@/types";
import { useI18n } from "@/lib/i18n";

const money = (n: number | string) => Number(n).toFixed(2);

export default function ReturnsPage() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const [creating, setCreating] = useState(false);
  const notesQ = useQuery({ queryKey: ["credit-notes"], queryFn: () => listCreditNotes() });

  return (
    <div>
      <PageHead title={t("ret.title")} sub={t("ret.sub")}>
        <Button onClick={() => setCreating(true)}>{t("ret.new")}</Button>
      </PageHead>

      {creating && (
        <NewReturn
          onClose={() => setCreating(false)}
          onDone={() => {
            setCreating(false);
            qc.invalidateQueries({ queryKey: ["credit-notes"] });
          }}
        />
      )}

      <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead>
            <tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
              <Th>{t("ret.creditNote")}</Th><Th>{t("ret.sale")}</Th><Th>{t("field.customer")}</Th><Th>{t("inv.reason")}</Th>
              <Th>{t("ret.restocked")}</Th><Th style={{ textAlign: "right" }}>{t("subs.amount")}</Th>
            </tr>
          </thead>
          <tbody>
            {(notesQ.data?.results ?? []).map((n) => (
              <tr key={n.id} style={{ borderTop: "1px solid var(--border)" }}>
                <Td mono>{n.number}</Td>
                <Td mono>{n.sale_number ?? "-"}</Td>
                <Td>{n.customer_name ?? "-"}</Td>
                <Td>{n.reason || "-"}</Td>
                <Td>{n.restocked ? t("common.yes") : t("common.no")}</Td>
                <Td mono right>{money(n.total_amount)} TND</Td>
              </tr>
            ))}
            {notesQ.data?.results.length === 0 && (
              <tr><Td colSpan={6} muted>{t("ret.none")}</Td></tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

/* ---------- new return flow ---------- */
function NewReturn({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const { t } = useI18n();
  const [search, setSearch] = useState("");
  const [sale, setSale] = useState<BusinessDoc | null>(null);
  const [qtys, setQtys] = useState<Record<number, string>>({});
  const [restock, setRestock] = useState(true);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);

  const salesQ = useQuery({
    queryKey: ["confirmed-sales", search],
    queryFn: () => listConfirmedSales(search),
    enabled: !sale,
  });
  const returnableQ = useQuery({
    queryKey: ["returnable", sale?.id],
    queryFn: () => getReturnable(sale!.id),
    enabled: !!sale,
  });

  const returnable = returnableQ.data?.returnable ?? {};
  const total = useMemo(() => {
    if (!sale) return 0;
    return sale.lines.reduce((s, l) => s + Number(qtys[l.product] || 0) * Number(l.unit_price), 0);
  }, [sale, qtys]);

  const submit = useMutation({
    mutationFn: () => {
      const lines = (sale?.lines ?? [])
        .filter((l) => Number(qtys[l.product] || 0) > 0)
        .map((l) => ({ product: l.product, quantity: Number(qtys[l.product]), unit_price: Number(l.unit_price) }));
      if (lines.length === 0) throw new Error(t("ret.enterQty"));
      return createCreditNote(sale!.id, { reason, restock, lines });
    },
    onSuccess: onDone,
    onError: (e: any) => setError(e?.response?.data?.detail ?? e?.message ?? t("ret.createError")),
  });

  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 20, marginBottom: 18 }}>
      {!sale ? (
        <>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
            <strong style={{ color: "var(--text-strong)" }}>{t("ret.pickSale")}</strong>
            <Button variant="ghost" size="sm" onClick={onClose}>{t("common.cancel")}</Button>
          </div>
          <Input placeholder={t("ret.searchSale")} value={search} onChange={(e) => setSearch(e.target.value)} />
          <div style={{ marginTop: 12, display: "flex", flexDirection: "column", gap: 6 }}>
            {(salesQ.data?.results ?? []).map((s) => (
              <button
                key={s.id}
                onClick={() => setSale(s)}
                style={{ textAlign: "left", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 10, padding: "10px 12px", cursor: "pointer" }}
              >
                <span style={{ fontFamily: "var(--font-mono)", color: "var(--text-strong)" }}>{s.number}</span>
                <span style={{ color: "var(--text-muted)", marginLeft: 10 }}>{s.customer_name} · {money(s.total_amount)} TND</span>
              </button>
            ))}
            {salesQ.data?.results.length === 0 && <p style={{ color: "var(--text-muted)", fontSize: 13 }}>{t("ret.noSaleMatch")}</p>}
          </div>
        </>
      ) : (
        <>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 12 }}>
            <strong style={{ color: "var(--text-strong)" }}>{t("ret.returnFrom")} {sale.number}</strong>
            <Button variant="ghost" size="sm" onClick={() => setSale(null)}>{t("ret.changeSale")}</Button>
          </div>

          <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
            <thead>
              <tr style={{ color: "var(--text-muted)", textAlign: "left" }}>
                <Th>{t("field.product")}</Th><Th right>{t("ret.sold")}</Th><Th right>{t("ret.returnable")}</Th><Th right>{t("ret.returnQty")}</Th>
              </tr>
            </thead>
            <tbody>
              {sale.lines.map((l) => {
                const canReturn = returnable[String(l.product)] ?? 0;
                return (
                  <tr key={l.product} style={{ borderTop: "1px solid var(--border)" }}>
                    <Td>{l.product_name ?? l.product_sku}</Td>
                    <Td right mono>{l.quantity}</Td>
                    <Td right mono>{canReturn}</Td>
                    <Td right>
                      <input
                        type="number" min={0} max={canReturn} step="0.001"
                        value={qtys[l.product] ?? ""}
                        onChange={(e) => setQtys((q) => ({ ...q, [l.product]: e.target.value }))}
                        disabled={canReturn <= 0}
                        style={{ width: 90, textAlign: "right", background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, padding: "6px 8px" }}
                      />
                    </Td>
                  </tr>
                );
              })}
            </tbody>
          </table>

          <div style={{ display: "flex", gap: 16, alignItems: "center", marginTop: 14, flexWrap: "wrap" }}>
            <label style={{ display: "flex", gap: 8, alignItems: "center", fontSize: 14, color: "var(--text-body)" }}>
              <input type="checkbox" checked={restock} onChange={(e) => setRestock(e.target.checked)} />
              {t("ret.restockGoods")}
            </label>
            <Input placeholder={t("ret.reasonOptional")} value={reason} onChange={(e) => setReason(e.target.value)} style={{ flex: 1, minWidth: 180 }} />
            <div style={{ fontWeight: 700, color: "var(--text-strong)" }}>{t("ret.creditWord")} {money(total)} TND</div>
          </div>

          {error && <p style={{ color: "var(--rose-400)", fontSize: 13, marginTop: 10 }}>{error}</p>}
          <div style={{ display: "flex", gap: 8, marginTop: 14 }}>
            <Button variant="outline" onClick={onClose}>{t("common.cancel")}</Button>
            <Button loading={submit.isPending} disabled={total <= 0} onClick={() => submit.mutate()}>
              {t("ret.issue")}
            </Button>
          </div>
        </>
      )}
    </div>
  );
}

function Th({ children, right, style }: { children: React.ReactNode; right?: boolean; style?: React.CSSProperties }) {
  return <th style={{ padding: "10px 14px", fontSize: 11, letterSpacing: "0.08em", textTransform: "uppercase", textAlign: right ? "right" : "left", ...style }}>{children}</th>;
}
function Td({ children, mono, right, muted, colSpan }: { children: React.ReactNode; mono?: boolean; right?: boolean; muted?: boolean; colSpan?: number }) {
  return (
    <td colSpan={colSpan} style={{ padding: "10px 14px", textAlign: right ? "right" : "left", fontFamily: mono ? "var(--font-mono)" : undefined, color: muted ? "var(--text-muted)" : "var(--text-body)", fontVariantNumeric: mono ? "tabular-nums" : undefined }}>
      {children}
    </td>
  );
}
