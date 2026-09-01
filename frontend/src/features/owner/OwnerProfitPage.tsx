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
import { useI18n } from "@/lib/i18n";

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
  const { t } = useI18n();
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
          {t("own.sub")}
        </p>
        <Segmented
          id="profit-period"
          value={period}
          onChange={setPeriod}
          options={[
            { value: "month", label: t("own.thisMonth") },
            { value: "year", label: t("own.thisYear") },
            { value: "all", label: t("own.allTime") },
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
              <span className="eyebrow">{t("own.netProfit")}</span>
              <Badge tone={profitPositive ? "emerald" : "red"} dot>
                {profitPositive ? (
                  <TrendingUp className="h-3.5 w-3.5" />
                ) : (
                  <TrendingDown className="h-3.5 w-3.5" />
                )}
                {s.net_margin_pct}% {t("own.margin")}
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
            <Tile label={t("own.revenue")} value={formatTnd(s.revenue)} />
            <Tile label={t("own.cogs")} value={formatTnd(s.cost_of_goods_sold)} muted />
            <Tile
              label={t("own.grossProfit")}
              value={formatTnd(s.gross_profit)}
              hint={`${s.gross_margin_pct}% ${t("own.ofSales")}`}
            />
            <Tile label={t("own.totalExpenses")} value={formatTnd(s.total_expenses)} muted />
          </div>

          {/* best products */}
          <section className="space-y-2">
            <h3 className="text-sm font-semibold">{t("own.bestProducts")}</h3>
            {(data?.best_products ?? []).length === 0 ? (
              <EmptyState
                icon={TrendingUp}
                title={t("own.noSales")}
                hint={t("own.noSalesHint")}
              />
            ) : (
              <Table>
                <THead>
                  <tr>
                    <Th>{t("field.product")}</Th>
                    <Th className="text-right">{t("own.sold")}</Th>
                    <Th className="text-right">{t("own.revenue")}</Th>
                    <Th className="text-right">{t("own.profit")}</Th>
                    <Th className="text-right">{t("own.marginCol")}</Th>
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
              <h3 className="text-sm font-semibold">{t("own.whereMoney")}</h3>
              <Table>
                <THead>
                  <tr>
                    <Th>{t("own.expense")}</Th>
                    <Th className="text-right">{t("own.amount")}</Th>
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
            {t("own.footnote")}
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
