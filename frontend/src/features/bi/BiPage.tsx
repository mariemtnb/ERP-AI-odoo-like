import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import * as bi from "@/api/bi";
import { useI18n } from "@/lib/i18n";

export default function BiPage() {
  const { t } = useI18n();
  const qc = useQueryClient();
  const groupLabel = (k: string) => t(`bi.group.${k}`);
  const measureFull = (k: string) => t(`bi.measureFull.${k}`);
  const [groupBy, setGroupBy] = useState("month");
  const [measure, setMeasure] = useState("total");
  const [result, setResult] = useState<bi.ReportResult | null>(null);
  const [name, setName] = useState("");
  const reportsQ = useQuery({ queryKey: ["bi-reports"], queryFn: bi.listReports });

  const run = useMutation({ mutationFn: () => bi.runReport(groupBy, measure), onSuccess: setResult });
  const runSaved = useMutation({
    mutationFn: (id: number) => bi.runSavedReport(id),
    onSuccess: (r) => { setGroupBy(r.group_by); setMeasure(r.measure); setResult({ group_by: r.group_by, measure: r.measure, rows: r.rows, total: r.total }); },
  });
  const save = useMutation({
    mutationFn: () => bi.saveReport({ name, group_by: groupBy, measure }),
    onSuccess: () => { setName(""); qc.invalidateQueries({ queryKey: ["bi-reports"] }); },
  });
  const del = useMutation({ mutationFn: (id: number) => bi.deleteReport(id), onSuccess: () => qc.invalidateQueries({ queryKey: ["bi-reports"] }) });

  const max = result ? Math.max(1, ...result.rows.map((r) => r.value)) : 1;
  const fmt = (v: number) => measure === "total" ? v.toLocaleString(undefined, { maximumFractionDigits: 2 }) : v.toLocaleString();

  return (
    <div>
      <PageHead title={t("nav.reportBuilder")} sub={t("bi.sub")} />

      <div style={{ display: "grid", gridTemplateColumns: "1fr 280px", gap: 18, alignItems: "start" }}>
        <div>
          <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18, marginBottom: 18, display: "flex", gap: 12, alignItems: "end", flexWrap: "wrap" }}>
            <Field label={t("bi.source")}><div style={{ padding: "9px 0", color: "var(--text-strong)" }}>{t("bi.sourceSales")}</div></Field>
            <Field label={t("bi.groupBy")}>
              <select value={groupBy} onChange={(e) => setGroupBy(e.target.value)} style={selectStyle}>
                <option value="month">{t("bi.group.month")}</option><option value="customer">{t("bi.group.customer")}</option><option value="product">{t("bi.group.product")}</option>
              </select>
            </Field>
            <Field label={t("bi.measure")}>
              <select value={measure} onChange={(e) => setMeasure(e.target.value)} style={selectStyle}>
                <option value="total">{t("bi.measure.total")}</option><option value="count">{t("bi.measure.count")}</option>
              </select>
            </Field>
            <Button loading={run.isPending} onClick={() => run.mutate()}>{t("bi.run")}</Button>
          </div>

          {result && (
            <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, padding: 18 }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 14 }}>
                <strong style={{ color: "var(--text-strong)" }}>{measureFull(result.measure)} {t("bi.byWord")} {groupLabel(result.group_by).toLowerCase()}</strong>
                <span style={{ fontSize: 13, color: "var(--text-muted)" }}>{t("common.total")}: <span style={{ color: "var(--text-strong)", fontWeight: 600 }}>{fmt(result.total)}</span></span>
              </div>
              {result.rows.length === 0 && <p style={{ color: "var(--text-muted)", fontSize: 14 }}>{t("bi.noData")}</p>}
              <div style={{ display: "flex", flexDirection: "column", gap: 8 }}>
                {result.rows.map((r) => (
                  <div key={r.label} style={{ display: "grid", gridTemplateColumns: "140px 1fr 90px", gap: 10, alignItems: "center" }}>
                    <span style={{ fontSize: 13, color: "var(--text-body)", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{r.label}</span>
                    <div style={{ background: "var(--surface-hover)", borderRadius: 6, height: 22, overflow: "hidden" }}>
                      <div style={{ width: `${(r.value / max) * 100}%`, height: "100%", background: "var(--emerald-500)", borderRadius: 6, transition: "width .3s" }} />
                    </div>
                    <span style={{ fontSize: 13, textAlign: "right", fontVariantNumeric: "tabular-nums", color: "var(--text-strong)" }}>{fmt(r.value)}</span>
                  </div>
                ))}
              </div>
              <div style={{ display: "flex", gap: 8, marginTop: 18, borderTop: "1px solid var(--border)", paddingTop: 14 }}>
                <Input placeholder={t("bi.saveNamePlaceholder")} value={name} onChange={(e) => setName(e.target.value)} style={{ maxWidth: 260 }} />
                <Button size="sm" variant="outline" loading={save.isPending} disabled={!name.trim()} onClick={() => save.mutate()}>{t("bi.saveReport")}</Button>
              </div>
            </div>
          )}
        </div>

        <div style={{ background: "var(--surface)", border: "1px solid var(--border)", borderRadius: 14, overflow: "hidden" }}>
          <div style={{ padding: "12px 14px", borderBottom: "1px solid var(--border)", fontWeight: 600, color: "var(--text-strong)" }}>{t("bi.savedReports")}</div>
          {(reportsQ.data ?? []).map((r) => (
            <div key={r.id} style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "10px 14px", borderBottom: "1px solid var(--border)" }}>
              <button onClick={() => runSaved.mutate(r.id)} style={{ textAlign: "left", background: "none", border: 0, cursor: "pointer", color: "var(--text-strong)" }}>
                <div style={{ fontWeight: 600 }}>{r.name}</div>
                <div style={{ fontSize: 12, color: "var(--text-muted)" }}>{measureFull(r.measure)} · {t("bi.byWord")} {groupLabel(r.group_by).toLowerCase()}</div>
              </button>
              <button onClick={() => del.mutate(r.id)} style={{ color: "var(--rose-400)", background: "none", border: 0, cursor: "pointer", fontSize: 12 }}>{t("common.delete")}</button>
            </div>
          ))}
          {reportsQ.data?.length === 0 && <p style={{ padding: 14, color: "var(--text-muted)", fontSize: 13 }}>{t("bi.noSaved")}</p>}
        </div>
      </div>
    </div>
  );
}

const selectStyle: React.CSSProperties = { height: 38, background: "var(--surface-hover)", border: "1px solid var(--border)", borderRadius: 8, color: "var(--text-strong)", padding: "0 10px", minWidth: 140 };
function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return <label style={{ display: "block" }}><span style={{ display: "block", fontSize: 12, color: "var(--text-muted)", marginBottom: 4 }}>{label}</span>{children}</label>;
}
