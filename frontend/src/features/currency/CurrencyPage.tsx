import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { addCurrency, convert, listCurrencies, setRate, type Currency } from "@/api/currency";
import { useI18n } from "@/lib/i18n";

export default function CurrencyPage() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const currenciesQ = useQuery({ queryKey: ["currencies"], queryFn: listCurrencies });
  const [adding, setAdding] = useState(false);
  const currencies = currenciesQ.data ?? [];
  const refresh = () => qc.invalidateQueries({ queryKey: ["currencies"] });

  return (
    <div>
      <PageHead title={t("nav.currencies")} sub={t("cur.sub")}>
        <Button variant="outline" onClick={() => setAdding((v) => !v)}>{adding ? t("common.close") : t("cur.add")}</Button>
      </PageHead>

      <Converter currencies={currencies} />

      {adding && <AddCurrency onDone={() => { setAdding(false); refresh(); }} onCancel={() => setAdding(false)} />}

      <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
        <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 14 }}>
          <thead><tr style={{ background: "var(--surface-hover)", color: "var(--text-muted)", textAlign: "left" }}>
            <Th>{t("cur.code")}</Th><Th>{t("field.name")}</Th><Th>{t("cur.symbol")}</Th><Th right>{t("cur.rateInBase")}</Th><Th>{t("cur.setNewRate")}</Th>
          </tr></thead>
          <tbody>
            {currencies.map((c) => (
              <RateRow key={c.code} currency={c} onSaved={refresh} />
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function RateRow({ currency: c, onSaved }: { currency: Currency; onSaved: () => void }) {
  const { t } = useI18n();
  const [rate, setRateInput] = useState("");
  const save = useMutation({
    mutationFn: () => setRate(c.code, Number(rate)),
    onSuccess: () => { setRateInput(""); onSaved(); },
  });

  return (
    <tr style={{ borderTop: "1px solid var(--border)" }}>
      <Td mono>{c.code}{c.is_base && <span style={{ marginInlineStart: 8, fontSize: 11, color: "var(--emerald-400)", border: "1px solid var(--emerald-500)", borderRadius: 999, padding: "1px 7px" }}>{t("cur.base")}</span>}</Td>
      <Td>{c.name}</Td>
      <Td>{c.symbol}</Td>
      <Td right mono>{c.is_base ? "1" : c.latest_rate ?? <span style={{ color: "var(--amber-400,#d99a2b)" }}>{t("cur.notSet")}</span>}</Td>
      <Td>
        {c.is_base ? <span style={{ color: "var(--text-muted)" }}>—</span> : (
          <div style={{ display: "flex", gap: 6 }}>
            <input type="number" min={0} step="0.00001" value={rate} onChange={(e) => setRateInput(e.target.value)} placeholder="e.g. 3.4"
              style={{ width: 90, background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, padding: "5px 8px", color: "var(--text-strong)" }} />
            <Button size="sm" loading={save.isPending} disabled={!(Number(rate) > 0)} onClick={() => save.mutate()}>{t("common.save")}</Button>
          </div>
        )}
      </Td>
    </tr>
  );
}

function Converter({ currencies }: { currencies: Currency[] }) {
  const { t } = useI18n();
  const [amount, setAmount] = useState("100");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [result, setResult] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (currencies.length && !from) setFrom(currencies.find((c) => !c.is_base)?.code ?? currencies[0].code);
    if (currencies.length && !to) setTo(currencies.find((c) => c.is_base)?.code ?? currencies[0].code);
  }, [currencies, from, to]);

  const run = useMutation({
    mutationFn: () => convert(Number(amount), from, to),
    onSuccess: (d) => { setResult(d.result); setError(null); },
    onError: (e: any) => { setResult(null); setError(e?.response?.data?.detail ?? t("cur.conversionFailed")); },
  });

  const opts = (v: string, set: (s: string) => void) => (
    <select value={v} onChange={(e) => set(e.target.value)} style={{ height: 38, background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 8px" }}>
      {currencies.map((c) => <option key={c.code} value={c.code}>{c.code}</option>)}
    </select>
  );

  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18, display: "flex", gap: 10, alignItems: "center", flexWrap: "wrap" }}>
      <strong style={{ color: "var(--text-strong)", marginInlineEnd: 6 }}>{t("cur.convert")}</strong>
      <Input type="number" value={amount} onChange={(e) => setAmount(e.target.value)} style={{ width: 120 }} />
      {opts(from, setFrom)}
      <span style={{ color: "var(--text-muted)" }}>→</span>
      {opts(to, setTo)}
      <Button loading={run.isPending} onClick={() => run.mutate()}>{t("cur.convert")}</Button>
      {result !== null && <span style={{ color: "var(--text-strong)", fontWeight: 700, fontVariantNumeric: "tabular-nums" }}>= {result.toLocaleString(undefined, { maximumFractionDigits: 4 })} {to}</span>}
      {error && <span style={{ color: "var(--rose-400)", fontSize: 13 }}>{error}</span>}
    </div>
  );
}

function AddCurrency({ onDone, onCancel }: { onDone: () => void; onCancel: () => void }) {
  const { t } = useI18n();
  const [code, setCode] = useState("");
  const [name, setName] = useState("");
  const [symbol, setSymbol] = useState("");
  const [error, setError] = useState<string | null>(null);
  const add = useMutation({
    mutationFn: () => addCurrency({ code: code.toUpperCase(), name, symbol }),
    onSuccess: onDone,
    onError: (e: any) => setError(e?.response?.data?.detail ?? t("cur.addError")),
  });
  return (
    <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18, display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(140px,1fr))", gap: 12, alignItems: "end" }}>
      <Field label={t("cur.codeIso")}><Input value={code} maxLength={3} onChange={(e) => setCode(e.target.value)} placeholder="EUR" /></Field>
      <Field label={t("field.name")}><Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Euro" /></Field>
      <Field label={t("cur.symbol")}><Input value={symbol} onChange={(e) => setSymbol(e.target.value)} placeholder="€" /></Field>
      <div style={{ display: "flex", gap: 8 }}>
        <Button variant="outline" onClick={onCancel}>{t("common.cancel")}</Button>
        <Button loading={add.isPending} disabled={code.length !== 3 || !name} onClick={() => add.mutate()}>{t("common.add")}</Button>
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
function Td({ children, mono, right }: { children: React.ReactNode; mono?: boolean; right?: boolean }) {
  return <td style={{ padding: "10px 14px", textAlign: right ? "right" : "left", fontFamily: mono ? "var(--font-mono)" : undefined, color: "var(--text-body)", fontVariantNumeric: mono ? "tabular-nums" : undefined }}>{children}</td>;
}
