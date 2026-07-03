import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { getDashboardStats } from "@/api/reports";
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
        </>
      )}
    </div>
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
