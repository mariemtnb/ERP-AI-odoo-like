import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { getDashboardStats, getForecast } from "@/api/reports";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

function firstOfMonth() {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
}
const today = () => new Date().toISOString().slice(0, 10);

export default function DashboardPage() {
  const [from, setFrom] = useState(firstOfMonth());
  const [to, setTo] = useState(today());

  const { data, isLoading } = useQuery({
    queryKey: ["dashboard", from, to],
    queryFn: () => getDashboardStats({ from, to }),
  });
  const { data: forecast } = useQuery({
    queryKey: ["forecast"],
    queryFn: getForecast,
  });

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <h1 className="text-2xl font-bold">Dashboard</h1>
        <div className="flex items-end gap-3">
          <div className="space-y-1">
            <Label htmlFor="from">From</Label>
            <Input id="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          </div>
          <div className="space-y-1">
            <Label htmlFor="to">To</Label>
            <Input id="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
          </div>
        </div>
      </div>

      {isLoading || !data ? (
        <p className="text-slate-400">Loading…</p>
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Revenue" value={data.revenue.toFixed(2)} accent="text-emerald-400" />
            <StatCard label="Sales" value={String(data.sales_count)} />
            <StatCard label="Purchase orders" value={String(data.purchases_count)} />
            <StatCard
              label="Purchases amount"
              value={data.purchases_amount.toFixed(2)}
              accent="text-amber-400"
            />
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>Top products</CardTitle>
              </CardHeader>
              <CardContent>
                {data.top_products.length === 0 ? (
                  <p className="text-sm text-slate-400">No confirmed sales in this period.</p>
                ) : (
                  <ul className="divide-y divide-slate-800 text-sm">
                    {data.top_products.map((p) => (
                      <li key={p.product__id} className="flex justify-between py-2">
                        <span>
                          <span className="font-mono text-xs text-slate-400">{p.product__sku}</span>{" "}
                          {p.product__name}
                        </span>
                        <span className="text-slate-300">
                          {Number(p.quantity_sold)} sold ·{" "}
                          <span className="text-emerald-400">{Number(p.revenue).toFixed(2)}</span>
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>
                  Low stock{" "}
                  {data.low_stock.length > 0 && <Badge tone="red">{data.low_stock.length}</Badge>}
                </CardTitle>
              </CardHeader>
              <CardContent>
                {data.low_stock.length === 0 ? (
                  <p className="text-sm text-slate-400">All products above their minimum level.</p>
                ) : (
                  <ul className="divide-y divide-slate-800 text-sm">
                    {data.low_stock.map((p) => (
                      <li key={p.id} className="flex justify-between py-2">
                        <span>
                          <span className="font-mono text-xs text-slate-400">{p.sku}</span> {p.name}
                        </span>
                        <span className="text-red-400">
                          {Number(p.quantity_in_stock)} / min {Number(p.min_stock_level)}
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>
          </div>

          {forecast && (
            <div className="grid gap-6 lg:grid-cols-2">
              <Card>
                <CardHeader>
                  <CardTitle>Revenue forecast (next {forecast.horizon_days} days)</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                  <p className="text-3xl font-bold text-indigo-400">
                    {forecast.projected_total.toFixed(2)}
                  </p>
                  <p className="text-sm text-slate-400">
                    Trend{" "}
                    <span className={forecast.trend_per_day >= 0 ? "text-emerald-400" : "text-red-400"}>
                      {forecast.trend_per_day >= 0 ? "+" : ""}
                      {forecast.trend_per_day.toFixed(2)}/day
                    </span>{" "}
                    · least-squares over the last {forecast.window_days} days of confirmed sales
                  </p>
                  <Sparkline
                    history={forecast.daily_revenue.map((d) => d.revenue)}
                    projection={forecast.projection.map((d) => d.revenue)}
                  />
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Stockout risk</CardTitle>
                </CardHeader>
                <CardContent>
                  {forecast.stockout_risk.length === 0 ? (
                    <p className="text-sm text-slate-400">
                      No consumption recorded in the last {forecast.window_days} days.
                    </p>
                  ) : (
                    <ul className="divide-y divide-slate-800 text-sm">
                      {forecast.stockout_risk.map((r) => (
                        <li key={r.id} className="flex justify-between py-2">
                          <span>
                            <span className="font-mono text-xs text-slate-400">{r.sku}</span> {r.name}
                          </span>
                          <span className={r.days_until_stockout <= 14 ? "text-red-400" : "text-slate-300"}>
                            ~{r.days_until_stockout} days left
                            <span className="ml-2 text-xs text-slate-500">
                              ({r.daily_consumption}/day)
                            </span>
                          </span>
                        </li>
                      ))}
                    </ul>
                  )}
                </CardContent>
              </Card>
            </div>
          )}
        </>
      )}
    </div>
  );
}

function Sparkline({ history, projection }: { history: number[]; projection: number[] }) {
  const all = [...history, ...projection];
  const max = Math.max(...all, 1);
  const w = 300;
  const h = 48;
  const step = w / Math.max(all.length - 1, 1);
  const y = (v: number) => h - (v / max) * (h - 4) - 2;
  const path = (values: number[], offset: number) =>
    values.map((v, i) => `${i === 0 ? "M" : "L"}${((offset + i) * step).toFixed(1)},${y(v).toFixed(1)}`).join(" ");
  return (
    <svg viewBox={`0 0 ${w} ${h}`} className="h-12 w-full" preserveAspectRatio="none">
      <path d={path(history, 0)} fill="none" stroke="#34d399" strokeWidth="1.5" />
      <path
        d={`M${((history.length - 1) * step).toFixed(1)},${y(history.at(-1) ?? 0).toFixed(1)} ` +
          path(projection, history.length).slice(1)}
        fill="none"
        stroke="#818cf8"
        strokeWidth="1.5"
        strokeDasharray="4 3"
      />
    </svg>
  );
}

function StatCard({
  label,
  value,
  accent = "text-slate-100",
}: {
  label: string;
  value: string;
  accent?: string;
}) {
  return (
    <Card>
      <CardContent className="p-6">
        <p className="text-sm text-slate-400">{label}</p>
        <p className={`mt-1 text-3xl font-bold ${accent}`}>{value}</p>
      </CardContent>
    </Card>
  );
}
