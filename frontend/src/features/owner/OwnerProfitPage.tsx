import { useMemo, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { TrendingDown, TrendingUp } from "lucide-react";
import { getOwnerProfit } from "@/api/payroll";
import { Badge } from "@/components/ui/badge";
import { EmptyState } from "@/components/ui/empty-state";
import { Segmented } from "@/components/ui/segmented";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { formatTnd } from "@/lib/tnLabels";

/** Preset date ranges the owner is likely to want. */
function range(kind: string): { from?: string; to?: string } {
  const now = new Date();
  const iso = (d: Date) => d.toISOString().slice(0, 10);
  if (kind === "month") {
    return { from: iso(new Date(now.getFullYear(), now.getMonth(), 1)), to: iso(now) };
  }
  if (kind === "year") {
    return { from: iso(new Date(now.getFullYear(), 0, 1)), to: iso(now) };
  }
  return {}; // all time
}

export default function OwnerProfitPage() {
  const [period, setPeriod] = useState("month");
  const params = useMemo(() => range(period), [period]);

  const { data, isLoading } = useQuery({
    queryKey: ["owner-profit", period],
    queryFn: () => getOwnerProfit(params),
  });

  const s = data?.summary;
  const profitPositive = (s?.net_profit ?? 0) >= 0;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-text-3">
          Your money at a glance — what came in, what went out, and what you kept.
        </p>
        <Segmented
          id="profit-period"
          value={period}
          onChange={setPeriod}
          options={[
            { value: "month", label: "This month" },
            { value: "year", label: "This year" },
            { value: "all", label: "All time" },
          ]}
        />
      </div>

      {isLoading || !s ? (
        <TableSkeleton rows={4} />
      ) : (
        <>
          {/* headline profit */}
          <div className="erp-card p-5">
            <div className="flex items-center justify-between">
              <span className="eyebrow">Net profit</span>
              <Badge tone={profitPositive ? "emerald" : "red"} dot>
                {profitPositive ? (
                  <TrendingUp className="h-3.5 w-3.5" />
                ) : (
                  <TrendingDown className="h-3.5 w-3.5" />
                )}
                {s.net_margin_pct}% margin
              </Badge>
            </div>
            <p
              className="tnum mt-2"
              style={{
                font: "600 34px/1 var(--font-sans)",
                letterSpacing: "-0.03em",
                color: profitPositive ? "var(--emerald-400)" : "var(--rose-400)",
              }}
            >
              {formatTnd(s.net_profit)}
            </p>
          </div>

          {/* the money flow */}
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Tile label="Revenue" value={formatTnd(s.revenue)} />
            <Tile label="Cost of goods" value={formatTnd(s.cost_of_goods_sold)} muted />
            <Tile
              label="Gross profit"
              value={formatTnd(s.gross_profit)}
              hint={`${s.gross_margin_pct}% of sales`}
            />
            <Tile label="Total expenses" value={formatTnd(s.total_expenses)} muted />
          </div>

          {/* best products */}
          <section className="space-y-2">
            <h3 className="text-sm font-semibold">Best products by profit</h3>
            {(data?.best_products ?? []).length === 0 ? (
              <EmptyState
                icon={TrendingUp}
                title="No sales in this period yet"
                hint="Once you confirm some sales, the products that earn you the most will show here."
              />
            ) : (
              <Table>
                <THead>
                  <tr>
                    <Th>Product</Th>
                    <Th className="text-right">Sold</Th>
                    <Th className="text-right">Revenue</Th>
                    <Th className="text-right">Profit</Th>
                    <Th className="text-right">Margin</Th>
                  </tr>
                </THead>
                <TBody>
                  {data!.best_products.map((p) => (
                    <tr key={p.sku}>
                      <Td>
                        <span className="font-medium">{p.name}</span>
                        <span className="ml-2 text-xs text-text-3">{p.sku}</span>
                      </Td>
                      <Td className="text-right">{p.quantity_sold}</Td>
                      <Td className="text-right">{formatTnd(p.revenue)}</Td>
                      <Td className="text-right font-medium text-positive">{formatTnd(p.margin)}</Td>
                      <Td className="text-right">{p.margin_pct}%</Td>
                    </tr>
                  ))}
                </TBody>
              </Table>
            )}
          </section>

          {/* where the money went */}
          {s.expense_breakdown.length > 0 && (
            <section className="space-y-2">
              <h3 className="text-sm font-semibold">Where the money went</h3>
              <Table>
                <THead>
                  <tr>
                    <Th>Expense</Th>
                    <Th className="text-right">Amount</Th>
                  </tr>
                </THead>
                <TBody>
                  {s.expense_breakdown.map((e) => (
                    <tr key={e.code}>
                      <Td>{e.name}</Td>
                      <Td className="text-right">{formatTnd(e.amount)}</Td>
                    </tr>
                  ))}
                </TBody>
              </Table>
            </section>
          )}

          <p className="text-xs text-text-3">
            These figures come straight from your accounting, so they always match your books.
          </p>
        </>
      )}
    </div>
  );
}

function Tile({
  label,
  value,
  hint,
  muted,
}: {
  label: string;
  value: string;
  hint?: string;
  muted?: boolean;
}) {
  return (
    <div className="erp-card p-4">
      <p className="eyebrow">{label}</p>
      <p
        className="tnum mt-1 text-xl font-semibold"
        style={{ color: muted ? "var(--text-2)" : "var(--text-strong)" }}
      >
        {value}
      </p>
      {hint && <p className="mt-1 text-xs text-text-3">{hint}</p>}
    </div>
  );
}
