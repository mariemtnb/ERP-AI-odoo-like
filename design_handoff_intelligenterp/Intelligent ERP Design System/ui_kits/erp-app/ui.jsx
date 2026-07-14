// Shared UI helpers for the ERP kit. Uses DS classes from styles.css/components.css.
const { useState, useEffect, useRef } = React;

function Icon({ name, size = 18, color, style }) {
  const ref = useRef(null);
  useEffect(() => {
    if (ref.current && window.lucide) {
      ref.current.innerHTML = "";
      const el = document.createElement("i");
      el.setAttribute("data-lucide", name);
      ref.current.appendChild(el);
      window.lucide.createIcons({ attrs: { width: size, height: size, stroke: color || "currentColor", "stroke-width": 1.75 }, nameAttr: "data-lucide" });
    }
  }, [name, size, color]);
  return <span ref={ref} style={{ display: "inline-flex", width: size, height: size, color, ...style }} />;
}

function Btn({ variant = "primary", size = "md", icon, iconRight, children, ...p }) {
  return (
    <button className={`erp-btn erp-btn--${variant} erp-btn--${size}`} {...p}>
      {icon && <Icon name={icon} size={size === "sm" ? 14 : 16} />}
      {children}
      {iconRight && <Icon name={iconRight} size={16} />}
    </button>
  );
}

const BADGE = {
  neutral: ["var(--surface-hover)", "var(--text-body)"], emerald: ["var(--emerald-glow)", "var(--emerald-400)"],
  amber: ["var(--amber-glow)", "var(--amber-400)"], rose: ["var(--rose-glow)", "var(--rose-400)"],
  sky: ["var(--sky-glow)", "var(--sky-400)"], violet: ["var(--violet-glow)", "var(--violet-400)"],
};
function Badge({ tone = "neutral", dot, children }) {
  const [bg, fg] = BADGE[tone];
  return (
    <span style={{ display: "inline-flex", alignItems: "center", gap: 6, background: bg, color: fg, font: "600 11px/1 var(--font-sans)", letterSpacing: ".02em", padding: "5px 10px", borderRadius: 999, textTransform: "capitalize" }}>
      {dot && <span style={{ width: 6, height: 6, borderRadius: 999, background: "currentColor" }} />}
      {children}
    </span>
  );
}

function Spark({ data, up = true, w = 80, h = 28, fill = false }) {
  const max = Math.max(...data), min = Math.min(...data), span = max - min || 1;
  const pts = data.map((v, i) => [(i / (data.length - 1)) * w, h - ((v - min) / span) * (h - 4) - 2]);
  const line = pts.map((p, i) => `${i ? "L" : "M"}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(" ");
  const color = up ? "var(--emerald-400)" : "var(--rose-400)";
  return (
    <svg width={w} height={h} viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none">
      {fill && <path d={`${line} L${w},${h} L0,${h} Z`} fill={color} opacity="0.10" />}
      <path d={line} fill="none" stroke={color} strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

function Kpi({ label, value, unit, delta, spark, tone }) {
  const up = (delta ?? 0) >= 0;
  return (
    <div className="erp-card erp-card--hover" style={{ padding: 20, display: "flex", flexDirection: "column", gap: 16, position: "relative", overflow: "hidden" }}>
      {tone !== "neutral" && <div style={{ position: "absolute", inset: 0, background: "var(--wash-emerald)", pointerEvents: "none" }} />}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", position: "relative" }}>
        <span className="ds-eyebrow">{label}</span>
        <Spark data={spark} up={up} fill />
      </div>
      <div style={{ display: "flex", alignItems: "baseline", gap: 6, position: "relative" }}>
        <span style={{ font: "600 30px/1 var(--font-sans)", letterSpacing: "-.03em", color: "var(--text-strong)", fontVariantNumeric: "tabular-nums" }}>{value}</span>
        {unit && <span style={{ font: "500 13px/1 var(--font-sans)", color: "var(--text-muted)" }}>{unit}</span>}
      </div>
      <span style={{ display: "inline-flex", alignItems: "center", gap: 5, font: "600 12px/1 var(--font-sans)", color: up ? "var(--emerald-400)" : "var(--rose-400)", position: "relative" }}>
        <Icon name={up ? "trending-up" : "trending-down"} size={14} /> {Math.abs(delta)}% <span style={{ color: "var(--text-faint)", fontWeight: 500 }}>vs last month</span>
      </span>
    </div>
  );
}

function PageHead({ title, sub, children }) {
  return (
    <div style={{ display: "flex", alignItems: "flex-end", justifyContent: "space-between", gap: 16, marginBottom: 28 }}>
      <div>
        <h1 style={{ margin: 0, font: "600 28px/1.1 var(--font-sans)", letterSpacing: "-.03em", color: "var(--text-strong)" }}>{title}</h1>
        {sub && <p style={{ margin: "8px 0 0", font: "400 14px/1.4 var(--font-sans)", color: "var(--text-muted)" }}>{sub}</p>}
      </div>
      <div style={{ display: "flex", gap: 10 }}>{children}</div>
    </div>
  );
}

window.ERP_UI = { Icon, Btn, Badge, Spark, Kpi, PageHead };
