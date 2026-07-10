import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { motion } from "framer-motion";
import {
  AlertTriangle, ArrowDownRight, ArrowUpRight, Banknote,
  PackageOpen, ShoppingBag, ShoppingCart, TrendingUp,
} from "lucide-react";
import { getDashboardStats, getForecast } from "@/api/reports";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Skeleton } from "@/components/ui/skeleton";
import { cn } from "@/lib/utils";

function firstOfMonth() {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
}
const today = () => new Date().toISOString().slice(0, 10);

const EASE = [0.22, 1, 0.36, 1] as const;
const stagger = {
  hidden: {},
  show: { transition: { staggerChildren: 0.06 } },
};
const rise = {
  hidden: { opacity: 0, y: 14 },
  show: { opacity: 1, y: 0, transition: { duration: 0.4, ease: EASE } },
};

export default function DashboardPage() {
  const [from, setFrom] = useState(firstOfMonth());
  const [to, setTo] = useState(today());

  const { data, isLoading } = useQuery({
    queryKey: ["dashboard", from, to],
    queryFn: () => getDashboardStats({ from, to }),
  });
  const { data: forecast } = useQuery({ queryKey: ["forecast"], queryFn: getForecast });

  return (
    <div className="space-y-8">
      {/* period picker — quiet, right-aligned; the topbar owns the title */}
      <div className="flex flex-wrap items-end justify-between gap-4">
        <p className="text-sm text-text-3">
          Your business at a glance for the selected period.
        </p>
        <div className="flex items-end gap-3">
          <div className="space-y-1.5">
            <Label htmlFor="from">From</Label>
            <Input id="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40" />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="to">To</Label>
            <Input id="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40" />
          </div>
        </div>
      </div>

      {isLoading || !data ? (
        <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-32" />
          ))}
        </div>
      ) : (
        <motion.div variants={stagger} initial="hidden" animate="show" className="space-y-8">
          {/* KPI row */}
          <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <Kpi
              icon={Banknote}
              label="Revenue"
              value={data.revenue.toFixed(2)}
              accent
              trend={forecast?.trend_per_day}
            />
            <Kpi icon={ShoppingCart} label="Sales" value={String(data.sales_count)} />
            <Kpi icon={ShoppingBag} label="Purchase orders" value={String(data.purchases_count)} />
            <Kpi icon={PackageOpen} label="Purchases amount" value={data.purchases_amount.toFixed(2)} />
          </div>

          {/* forecast chart — the hero */}
          {forecast && (
            <motion.div variants={rise}>
              <Card className="overflow-hidden">
                <CardHeader className="flex flex-row items-start justify-between">
                  <div>
                    <CardTitle>Revenue trajectory</CardTitle>
                    <p className="mt-1 text-[13px] text-text-3">
                      Last {forecast.window_days} days · projection {forecast.horizon_days} days ahead
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="tnum text-2xl font-semibold tracking-tight text-accent-strong">
                      {forecast.projected_total.toFixed(2)}
                    </p>
                    <p className="flex items-center justify-end gap-1 text-xs text-text-3">
                      {forecast.trend_per_day >= 0 ? (
                        <ArrowUpRight className="h-3.5 w-3.5 text-positive" />
                      ) : (
                        <ArrowDownRight className="h-3.5 w-3.5 text-danger" />
                      )}
                      {forecast.trend_per_day >= 0 ? "+" : ""}
                      {forecast.trend_per_day.toFixed(2)}/day
                    </p>
                  </div>
                </CardHeader>
                <CardContent>
                  <AreaChart
                    history={forecast.daily_revenue.map((d) => d.revenue)}
                    projection={forecast.projection.map((d) => d.revenue)}
                    days={forecast.window_days}
                  />
                </CardContent>
              </Card>
            </motion.div>
          )}

          {/* insight cards */}
          <div className="grid gap-5 lg:grid-cols-3">
            <motion.div variants={rise}>
              <Card className="h-full">
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <TrendingUp className="h-4 w-4 text-accent-strong" /> Top products
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {data.top_products.length === 0 ? (
                    <p className="py-6 text-center text-sm text-text-3">
                      No confirmed sales in this period.
                    </p>
                  ) : (
                    <ul className="space-y-1">
                      {data.top_products.map((p, i) => (
                        <li
                          key={p.product__id}
                          className="flex items-center gap-3 rounded-lg px-2 py-2.5 transition-colors duration-150 hover:bg-white/[0.03]"
                        >
                          <span className="tnum w-5 text-center text-xs font-semibold text-text-3">
                            {i + 1}
                          </span>
                          <div className="min-w-0">
                            <p className="truncate text-sm text-text">{p.product__name}</p>
                            <p className="font-mono text-[11px] text-text-3">{p.product__sku}</p>
                          </div>
                          <div className="ml-auto text-right">
                            <p className="tnum text-sm text-text">{Number(p.quantity_sold)} sold</p>
                            <p className="tnum text-xs text-positive">{Number(p.revenue).toFixed(2)}</p>
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                </CardContent>
              </Card>
            </motion.div>

            <motion.div variants={rise}>
              <Card className="h-full">
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <AlertTriangle className="h-4 w-4 text-warning" /> Low stock
                    {data.low_stock.length > 0 && <Badge tone="red">{data.low_stock.length}</Badge>}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {data.low_stock.length === 0 ? (
                    <p className="py-6 text-center text-sm text-text-3">
                      All products above their minimum level.
                    </p>
                  ) : (
                    <ul className="space-y-1">
                      {data.low_stock.map((p) => (
                        <li
                          key={p.id}
                          className="flex items-center justify-between rounded-lg px-2 py-2.5 transition-colors duration-150 hover:bg-white/[0.03]"
                        >
                          <div className="min-w-0">
                            <p className="truncate text-sm text-text">{p.name}</p>
                            <p className="font-mono text-[11px] text-text-3">{p.sku}</p>
                          </div>
                          <p className="tnum text-sm text-danger">
                            {Number(p.quantity_in_stock)}{" "}
                            <span className="text-text-3">/ min {Number(p.min_stock_level)}</span>
                          </p>
                        </li>
                      ))}
                    </ul>
                  )}
                </CardContent>
              </Card>
            </motion.div>

            <motion.div variants={rise}>
              <Card className="h-full">
                <CardHeader>
                  <CardTitle>Stockout risk</CardTitle>
                </CardHeader>
                <CardContent>
                  {!forecast || forecast.stockout_risk.length === 0 ? (
                    <p className="py-6 text-center text-sm text-text-3">
                      No consumption recorded recently.
                    </p>
                  ) : (
                    <ul className="space-y-1">
                      {forecast.stockout_risk.map((r) => (
                        <li
                          key={r.id}
                          className="flex items-center justify-between rounded-lg px-2 py-2.5 transition-colors duration-150 hover:bg-white/[0.03]"
                        >
                          <div className="min-w-0">
                            <p className="truncate text-sm text-text">{r.name}</p>
                            <p className="font-mono text-[11px] text-text-3">{r.sku}</p>
                          </div>
                          <p
                            className={cn(
                              "tnum text-sm",
                              r.days_until_stockout <= 14 ? "text-danger" : "text-text-2"
                            )}
                          >
                            ~{r.days_until_stockout}d
                            <span className="ml-1.5 text-xs text-text-3">
                              ({r.daily_consumption}/day)
                            </span>
                          </p>
                        </li>
                      ))}
                    </ul>
                  )}
                </CardContent>
              </Card>
            </motion.div>
          </div>
        </motion.div>
      )}
    </div>
  );
}

function Kpi({
  icon: Icon,
  label,
  value,
  accent = false,
  trend,
}: {
  icon: typeof Banknote;
  label: string;
  value: string;
  accent?: boolean;
  trend?: number;
}) {
  return (
    <motion.div variants={rise}>
      <Card
        className={cn(
          "group relative overflow-hidden p-6 hover:-translate-y-0.5 hover:shadow-3",
          accent && "shadow-[inset_0_0_0_1px_hsl(var(--accent)/0.25)]"
        )}
      >
        {accent && <div className="glow-accent pointer-events-none absolute -right-10 -top-14 h-40 w-40" />}
        <div className="flex items-center justify-between">
          <p className="text-[13px] font-medium text-text-3">{label}</p>
          <Icon
            className={cn(
              "h-4 w-4 transition-transform duration-300 group-hover:scale-110",
              accent ? "text-accent-strong" : "text-text-3"
            )}
          />
        </div>
        <p
          className={cn(
            "tnum mt-3 text-[34px] font-semibold leading-none tracking-tight",
            accent ? "text-gradient" : "text-text"
          )}
        >
          {value}
        </p>
        {trend !== undefined && (
          <p className="mt-2.5 flex items-center gap-1 text-xs text-text-3">
            {trend >= 0 ? (
              <ArrowUpRight className="h-3.5 w-3.5 text-positive" />
            ) : (
              <ArrowDownRight className="h-3.5 w-3.5 text-danger" />
            )}
            {trend >= 0 ? "+" : ""}
            {trend.toFixed(2)}/day trend
          </p>
        )}
      </Card>
    </motion.div>
  );
}

/** Gradient area chart: solid history, dashed emerald projection. Pure SVG. */
function AreaChart({
  history,
  projection,
  days,
}: {
  history: number[];
  projection: number[];
  days: number;
}) {
  // Build a continuous series over the window, indexed by day offset.
  const all = [...Array(days).fill(0).map((_, i) => history[i] ?? 0), ...projection];
  const max = Math.max(...all, 1) * 1.15;
  const w = 900;
  const h = 180;
  const step = w / Math.max(all.length - 1, 1);
  const x = (i: number) => i * step;
  const y = (v: number) => h - (v / max) * (h - 16) - 6;

  const histPts = Array(days).fill(0).map((_, i) => [x(i), y(history[i] ?? 0)] as const);
  const projPts = projection.map((v, i) => [x(days - 1 + i + 1), y(v)] as const);
  const line = (pts: readonly (readonly [number, number])[]) =>
    pts.map(([px, py], i) => `${i === 0 ? "M" : "L"}${px.toFixed(1)},${py.toFixed(1)}`).join(" ");

  const histArea = `${line(histPts)} L${histPts.at(-1)![0]},${h} L0,${h} Z`;
  const bridge = [[histPts.at(-1)![0], histPts.at(-1)![1]] as const, ...projPts];

  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="h-44 w-full" preserveAspectRatio="none" role="img" aria-label="Revenue history and 14-day projection">
      <defs>
        <linearGradient id="hist-fill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="hsl(var(--accent))" stopOpacity="0.28" />
          <stop offset="100%" stopColor="hsl(var(--accent))" stopOpacity="0" />
        </linearGradient>
      </defs>
      {/* soft horizontal guides */}
      {[0.25, 0.5, 0.75].map((f) => (
        <line key={f} x1="0" x2={w} y1={h * f} y2={h * f} stroke="hsl(var(--stroke-soft))" strokeWidth="1" strokeDasharray="2 6" />
      ))}
      <motion.path
        d={histArea}
        fill="url(#hist-fill)"
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.8, delay: 0.2 }}
      />
      <motion.path
        d={line(histPts)}
        fill="none"
        stroke="hsl(var(--accent-strong))"
        strokeWidth="2"
        strokeLinecap="round"
        initial={{ pathLength: 0 }}
        animate={{ pathLength: 1 }}
        transition={{ duration: 1, ease: [0.22, 1, 0.36, 1] }}
      />
      <motion.path
        d={line(bridge)}
        fill="none"
        stroke="hsl(var(--accent-soft))"
        strokeWidth="2"
        strokeDasharray="5 5"
        strokeLinecap="round"
        initial={{ opacity: 0 }}
        animate={{ opacity: 0.8 }}
        transition={{ duration: 0.6, delay: 0.9 }}
      />
    </svg>
  );
}
