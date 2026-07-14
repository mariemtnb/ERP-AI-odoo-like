import React from "react";

const TONES = {
  emerald: "var(--emerald-400)", amber: "var(--amber-400)", rose: "var(--rose-400)",
  sky: "var(--sky-400)", violet: "var(--violet-400)", neutral: "var(--text-faint)",
};

/** StatusDot — a small colored dot with an optional soft pulse; for inline status. */
export function StatusDot({ tone = "emerald", pulse = false, label, style }) {
  const color = TONES[tone] || TONES.emerald;
  return (
    <span style={{ display: "inline-flex", alignItems: "center", gap: "var(--space-2)", ...style }}>
      <span style={{ position: "relative", width: 8, height: 8, display: "inline-flex" }}>
        {pulse && (
          <span style={{ position: "absolute", inset: -3, borderRadius: "999px", background: color, opacity: 0.35, animation: "erp-ping 1.6s var(--ease-out) infinite" }} />
        )}
        <span style={{ width: 8, height: 8, borderRadius: "999px", background: color }} />
        <style>{"@keyframes erp-ping{75%,100%{transform:scale(2);opacity:0}}"}</style>
      </span>
      {label && <span style={{ font: "var(--font-label)", color: "var(--text-body)" }}>{label}</span>}
    </span>
  );
}
