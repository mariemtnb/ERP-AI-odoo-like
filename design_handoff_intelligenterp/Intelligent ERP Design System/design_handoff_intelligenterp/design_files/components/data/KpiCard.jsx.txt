import React from "react";

/**
 * KpiCard — dashboard metric tile. Eyebrow label, large value, delta chip,
 * optional sparkline (array of numbers). The signature dashboard element.
 */
export function KpiCard({ label, value, unit, delta, tone = "emerald", spark, icon }) {
  const up = (delta ?? 0) >= 0;
  const deltaColor = up ? "var(--emerald-400)" : "var(--rose-400)";
  return (
    <div className="erp-card erp-card--hover" style={{ padding: "var(--space-5)", display: "flex", flexDirection: "column", gap: "var(--space-4)", overflow: "hidden", position: "relative" }}>
      <div style={{ position: "absolute", inset: 0, background: "var(--wash-emerald)", opacity: tone === "emerald" ? 1 : 0, pointerEvents: "none" }} />
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", position: "relative" }}>
        <span className="ds-eyebrow" style={{ letterSpacing: "var(--tracking-caps)", textTransform: "uppercase", font: "var(--weight-semibold) var(--text-2xs)/1 var(--font-sans)", color: "var(--text-faint)" }}>{label}</span>
        {icon && <span style={{ color: "var(--text-faint)", display: "flex" }}>{icon}</span>}
      </div>
      <div style={{ display: "flex", alignItems: "baseline", gap: "var(--space-2)", position: "relative" }}>
        <span style={{ font: "var(--weight-semibold) var(--text-3xl)/1 var(--font-sans)", letterSpacing: "var(--tracking-display)", color: "var(--text-strong)", fontVariantNumeric: "tabular-nums" }}>{value}</span>
        {unit && <span style={{ font: "var(--font-label)", color: "var(--text-muted)" }}>{unit}</span>}
      </div>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", position: "relative" }}>
        {delta != null && (
          <span style={{ display: "inline-flex", alignItems: "center", gap: 4, font: "var(--weight-semibold) var(--text-xs)/1 var(--font-sans)", color: deltaColor }}>
            {up ? "▲" : "▼"} {Math.abs(delta)}%
          </span>
        )}
        {spark && <Spark data={spark} up={up} />}
      </div>
    </div>
  );
}

function Spark({ data, up }) {
  const max = Math.max(...data, 1), min = Math.min(...data, 0);
  const w = 72, h = 24, span = max - min || 1;
  const pts = data.map((v, i) => `${(i / (data.length - 1)) * w},${h - ((v - min) / span) * h}`).join(" ");
  const color = up ? "var(--emerald-400)" : "var(--rose-400)";
  return (
    <svg width={w} height={h} viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" style={{ overflow: "visible" }}>
      <polyline points={pts} fill="none" stroke={color} strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}
