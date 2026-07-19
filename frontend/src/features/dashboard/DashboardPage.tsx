import { useMemo } from "react";
import { useQuery } from "@tanstack/react-query";
import { Calendar, Check, Sparkles } from "lucide-react";
import { getDashboardStats, getForecast } from "@/api/reports";
import { useAuth } from "@/features/auth/AuthContext";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { KpiCard } from "@/components/ui/kpi-card";
import { PageHead } from "@/components/ui/page-head";
import { Skeleton } from "@/components/ui/skeleton";
import { Sparkline } from "@/components/ui/sparkline";
import { useNavigate } from "react-router-dom";

function monthRange() {
  const d = new Date();
  const from = new Date(d.getFullYear(), d.getMonth(), 1);
  return { from: from.toISOString().slice(0, 10), to: d.toISOString().slice(0, 10) };
}
/** Previous window of equal length, immediately before `from`. */
function previousRange(from: string, to: string) {
  const a = new Date(from);
  const b = new Date(to);
  const days = Math.max(1, Math.round((b.getTime() - a.getTime()) / 86400000) + 1);
  const prevTo = new Date(a.getTime() - 86400000);
  const prevFrom = new Date(prevTo.getTime() - (days - 1) * 86400000);
  return { from: prevFrom.toISOString().slice(0, 10), to: prevTo.toISOString().slice(0, 10) };
}
function pctDelta(current: number, previous: number): number {
  if (!previous) return current > 0 ? 100 : 0;
  return Math.round(((current - previous) / previous) * 1000) / 10;
}
/** Gentle 8-point micro-series ending near `value` — decorative KPI sparkline. */
function microSeries(value: number, up: boolean): number[] {
  const base = value || 1;
  return Array.from({ length: 8 }, (_, i) => {
    const t = i / 7;
    const drift = up ? t : 1 - t;
    const wobble = Math.sin(i * 1.7) * 0.05;
    return base * (0.72 + drift * 0.28 + wobble);
  });
}

function greeting() {
  const h = new Date().getHours();
  if (h < 12) return "Good morning";
  if (h < 18) return "Good afternoon";
  return "Good evening";
}

export default function DashboardPage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const range = useMemo(monthRange, []);
  const prev = useMemo(() => previousRange(range.from, range.to), [range]);

  const { data, isLoading } = useQuery({
    queryKey: ["dashboard", range.from, range.to],
    queryFn: () => getDashboardStats(range),
  });
  const { data: prevData } = useQuery({
    queryKey: ["dashboard", prev.from, prev.to],
    queryFn: () => getDashboardStats(prev),
  });
  const { data: forecast } = useQuery({ queryKey: ["forecast"], queryFn: getForecast });

  const revenueSeries = forecast?.daily_revenue.map((d) => d.revenue) ?? [];
  const firstName = user?.first_name || (user?.email?.split("@")[0] ?? "there");

  // headline insight, derived from the most urgent real signal
  const risk = forecast?.stockout_risk?.[0];
  const low = data?.low_stock?.[0];
  const insight = risk
    ? `${risk.name} is selling ~${risk.daily_consumption}/day and will run out in about ${risk.days_until_stockout} days. Consider a purchase order to avoid a stockout.`
    : low
      ? `${low.name} is low on stock (${Number(low.quantity_in_stock)}/${Number(low.min_stock_level)}). Consider a purchase order to restock before it runs out.`
      : "Everything looks healthy right now — no stockouts on the horizon and stock is above minimums.";

  return (
    <div>
      <PageHead title={`${greeting()}, ${firstName}`} sub="Here's what's moving across your business today.">
        <Button variant="outline" size="md" icon={<Calendar size={16} />}>This month</Button>
        <Button variant="primary" size="md" icon={<Sparkles size={16} />} onClick={() => navigate("/assistant")}>
          Ask AI
        </Button>
      </PageHead>

      {/* KPI row */}
      <div className="mb-5 grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))" }}>
        {isLoading || !data ? (
          Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-[128px] rounded-lg" />)
        ) : (
          <>
            {(() => {
              const d = pctDelta(data.revenue, prevData?.revenue ?? 0);
              return (
                <KpiCard
                  label="Revenue"
                  value={Math.round(data.revenue).toLocaleString("en-US")}
                  unit="TND"
                  delta={d}
                  spark={revenueSeries.length >= 2 ? revenueSeries : microSeries(data.revenue, d >= 0)}
                />
              );
            })()}
            {(() => {
              const d = pctDelta(data.sales_count, prevData?.sales_count ?? 0);
              return (
                <KpiCard label="Sales orders" value={data.sales_count.toLocaleString("en-US")} delta={d} spark={microSeries(data.sales_count, d >= 0)} />
              );
            })()}
            {(() => {
              const d = pctDelta(data.purchases_count, prevData?.purchases_count ?? 0);
              return (
                <KpiCard label="Purchase orders" value={data.purchases_count.toLocaleString("en-US")} delta={d} spark={microSeries(data.purchases_count, d >= 0)} />
              );
            })()}
            {(() => {
              const d = pctDelta(data.purchases_amount, prevData?.purchases_amount ?? 0);
              return (
                <KpiCard label="Purchases" value={Math.round(data.purchases_amount).toLocaleString("en-US")} unit="TND" delta={d} spark={microSeries(data.purchases_amount, d >= 0)} />
              );
            })()}
          </>
        )}
      </div>

      {/* Revenue trend + AI insight */}
      <div className="mb-5 grid gap-4" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))" }}>
        <div className="erp-card" style={{ padding: 22 }}>
          <div className="mb-4 flex items-start justify-between">
            <div>
              <span className="eyebrow">Revenue trend</span>
              <div className="tnum" style={{ font: "600 26px/1 var(--font-sans)", letterSpacing: "-0.03em", color: "var(--text-strong)", marginTop: 8 }}>
                {Math.round(data?.revenue ?? 0).toLocaleString("en-US")}{" "}
                <span style={{ font: "500 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>TND</span>
              </div>
            </div>
            {forecast && (
              <Badge tone="emerald" dot>
                {forecast.trend_per_day >= 0 ? "+" : ""}
                {forecast.trend_per_day.toFixed(1)}/day
              </Badge>
            )}
          </div>
          {revenueSeries.length >= 2 ? (
            <Sparkline data={revenueSeries} up fill w={640} h={150} className="w-full" />
          ) : (
            <div style={{ height: 150 }} className="grid place-items-center text-sm" >
              <span style={{ color: "var(--text-faint)" }}>Not enough sales yet to chart a trend.</span>
            </div>
          )}
        </div>

        <div
          className="erp-card relative overflow-hidden"
          style={{ padding: 0, background: "linear-gradient(160deg, color-mix(in oklab, var(--emerald-500) 12%, var(--surface-card)), var(--surface-card))" }}
        >
          <div className="flex h-full flex-col gap-3.5" style={{ padding: 22 }}>
            <div className="flex items-center gap-2.5">
              <div className="grid place-items-center rounded-[10px]" style={{ width: 34, height: 34, background: "var(--emerald-glow)", color: "var(--emerald-400)" }}>
                <Sparkles size={18} />
              </div>
              <span style={{ font: "600 15px/1 var(--font-sans)", color: "var(--text-strong)" }}>AI insight</span>
            </div>
            <p style={{ margin: 0, font: "400 14px/1.55 var(--font-sans)", color: "var(--text-body)" }}>{insight}</p>
            <div className="mt-auto flex gap-2">
              <Button variant="primary" size="sm" icon={<Check size={14} />} onClick={() => navigate("/purchases")}>
                Draft PO
              </Button>
              <Button variant="ghost" size="sm">Dismiss</Button>
            </div>
          </div>
        </div>
      </div>

      {/* Top products + Low stock */}
      <div className="grid gap-4" style={{ gridTemplateColumns: "1fr 1fr" }}>
        <SectionCard
          title="Top products"
          action={
            <Button variant="ghost" size="sm" iconRight={<span aria-hidden>→</span>} onClick={() => navigate("/products")}>
              View all
            </Button>
          }
        >
          {(data?.top_products ?? []).length === 0 ? (
            <EmptyRow text="No confirmed sales in this period." />
          ) : (
            data!.top_products.map((p) => (
              <div key={p.product__id} className="flex items-center justify-between" style={{ padding: "12px 0", borderBottom: "1px solid var(--border-subtle)" }}>
                <div className="flex flex-col gap-1">
                  <span style={{ font: "500 14px/1 var(--font-sans)", color: "var(--text-strong)" }}>{p.product__name}</span>
                  <span style={{ font: "400 12px/1 var(--font-mono)", color: "var(--text-faint)" }}>
                    {p.product__sku} · {Number(p.quantity_sold)} sold
                  </span>
                </div>
                <span style={{ font: "500 14px/1 var(--font-mono)", color: "var(--emerald-400)" }}>
                  {Number(p.revenue).toLocaleString("en-US")}
                </span>
              </div>
            ))
          )}
        </SectionCard>

        <SectionCard
          title="Low stock"
          action={<Badge tone="rose">{data?.low_stock.length ?? 0} items</Badge>}
        >
          {(data?.low_stock ?? []).length === 0 ? (
            <EmptyRow text="All products above their minimum level." />
          ) : (
            data!.low_stock.map((p) => {
              const qty = Number(p.quantity_in_stock);
              const min = Number(p.min_stock_level) || 1;
              const pct = Math.min(100, (qty / min) * 100);
              return (
                <div key={p.id} style={{ padding: "12px 0", borderBottom: "1px solid var(--border-subtle)" }}>
                  <div className="mb-2 flex justify-between">
                    <span style={{ font: "500 14px/1 var(--font-sans)", color: "var(--text-strong)" }}>{p.name}</span>
                    <span style={{ font: "500 13px/1 var(--font-mono)", color: "var(--rose-400)" }}>{qty} / {Number(p.min_stock_level)}</span>
                  </div>
                  <div style={{ height: 5, borderRadius: 999, background: "var(--surface-hover)", overflow: "hidden" }}>
                    <div style={{ width: `${pct}%`, height: "100%", borderRadius: 999, background: "var(--rose-400)" }} />
                  </div>
                </div>
              );
            })
          )}
        </SectionCard>
      </div>
    </div>
  );
}

function SectionCard({ title, action, children }: { title: string; action?: React.ReactNode; children: React.ReactNode }) {
  return (
    <div className="erp-card flex flex-col">
      <div className="flex items-center justify-between" style={{ padding: "18px 22px", borderBottom: "1px solid var(--border-subtle)" }}>
        <h3 style={{ margin: 0, font: "600 16px/1 var(--font-sans)", letterSpacing: "-0.01em", color: "var(--text-strong)" }}>{title}</h3>
        {action}
      </div>
      <div style={{ padding: "8px 22px 14px" }}>{children}</div>
    </div>
  );
}

function EmptyRow({ text }: { text: string }) {
  return (
    <p style={{ padding: "22px 0", textAlign: "center", font: "400 13px/1.4 var(--font-sans)", color: "var(--text-faint)" }}>
      {text}
    </p>
  );
}
