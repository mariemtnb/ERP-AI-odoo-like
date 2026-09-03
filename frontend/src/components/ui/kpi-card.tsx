import { TrendingDown, TrendingUp } from "lucide-react";
import { Sparkline } from "./sparkline";
import { cn } from "@/lib/utils";

/**
 * KpiCard - dashboard metric tile. Port of ui_kits Kpi():
 * eyebrow label + sparkline, big tabular value + unit, delta chip
 * (▲/▼ %, emerald/rose) with "vs last month".
 */
export function KpiCard({
  label,
  value,
  unit,
  delta,
  spark,
  wash = true,
}: {
  label: string;
  value: string;
  unit?: string;
  delta?: number;
  spark: number[];
  wash?: boolean;
}) {
  const up = (delta ?? 0) >= 0;
  return (
    <div
      className={cn(
        "erp-card erp-card--hover relative flex flex-col gap-4 overflow-hidden p-5"
      )}
    >
      {wash && (
        <div className="pointer-events-none absolute inset-0" style={{ background: "var(--wash-emerald)" }} />
      )}
      <div className="relative flex items-center justify-between">
        <span className="eyebrow">{label}</span>
        <Sparkline data={spark} up={up} fill />
      </div>
      <div className="relative flex items-baseline gap-1.5">
        <span
          className="tnum font-semibold"
          style={{ font: "600 30px/1 var(--font-sans)", letterSpacing: "-0.03em", color: "var(--text-strong)" }}
        >
          {value}
        </span>
        {unit && <span style={{ font: "500 13px/1 var(--font-sans)", color: "var(--text-muted)" }}>{unit}</span>}
      </div>
      {delta !== undefined && (
        <span
          className="relative inline-flex items-center gap-1.5"
          style={{ font: "600 12px/1 var(--font-sans)", color: up ? "var(--emerald-400)" : "var(--rose-400)" }}
        >
          {up ? <TrendingUp size={14} /> : <TrendingDown size={14} />}
          {Math.abs(delta)}%{" "}
          <span style={{ color: "var(--text-faint)", fontWeight: 500 }}>vs last month</span>
        </span>
      )}
    </div>
  );
}
