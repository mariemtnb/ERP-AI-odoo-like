import React from "react";

const TONES = {
  neutral: { bg: "var(--surface-hover)", fg: "var(--text-body)" },
  emerald: { bg: "var(--emerald-glow)", fg: "var(--emerald-400)" },
  amber: { bg: "var(--amber-glow)", fg: "var(--amber-400)" },
  rose: { bg: "var(--rose-glow)", fg: "var(--rose-400)" },
  sky: { bg: "var(--sky-glow)", fg: "var(--sky-400)" },
  violet: { bg: "var(--violet-glow)", fg: "var(--violet-400)" },
};

/** Badge — compact status/label pill. Optional leading dot. */
export function Badge({ tone = "neutral", dot = false, children, className = "", style, ...props }) {
  const t = TONES[tone] || TONES.neutral;
  return (
    <span
      className={className}
      style={{
        display: "inline-flex", alignItems: "center", gap: "var(--space-2)",
        background: t.bg, color: t.fg,
        font: "var(--weight-semibold) var(--text-2xs)/1 var(--font-sans)",
        letterSpacing: "var(--tracking-wide)",
        padding: "5px 10px", borderRadius: "var(--radius-full)",
        textTransform: "capitalize", ...style,
      }}
      {...props}
    >
      {dot && <span style={{ width: 6, height: 6, borderRadius: "999px", background: "currentColor" }} />}
      {children}
    </span>
  );
}
