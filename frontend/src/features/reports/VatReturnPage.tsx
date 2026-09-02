import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { PageHead } from "@/components/ui/page-head";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { TableSkeleton } from "@/components/ui/skeleton";
import { vatReturn } from "@/api/reports";
import { formatTnd } from "@/lib/tnLabels";

function monthRange(month: string) {
  const [y, m] = month.split("-").map(Number);
  const from = `${month}-01`;
  const to = new Date(y, m, 0).toISOString().slice(0, 10); // last day of month
  return { from, to };
}

export default function VatReturnPage() {
  const [month, setMonth] = useState(new Date().toISOString().slice(0, 7));
  const { from, to } = monthRange(month);
  const q = useQuery({ queryKey: ["vat", from, to], queryFn: () => vatReturn(from, to) });
  const v = q.data;

  return (
    <div>
      <PageHead title="VAT Return" sub="Output VAT collected on sales minus input VAT paid on purchases, for the chosen period." />

      <div className="mb-5 max-w-xs">
        <Label htmlFor="vat-month">Period (month)</Label>
        <Input id="vat-month" type="month" value={month} onChange={(e) => setMonth(e.target.value)} />
      </div>

      {q.isLoading || !v ? (
        <TableSkeleton rows={4} />
      ) : (
        <div className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-3">
            <Stat label="Output VAT (sales)" value={formatTnd(v.output_vat)} tone="rose" />
            <Stat label="Input VAT (purchases)" value={formatTnd(v.input_vat)} tone="sky" />
            <Stat
              label={v.vat_credit > 0 ? "VAT credit carried forward" : "Net VAT due"}
              value={formatTnd(v.vat_credit > 0 ? v.vat_credit : v.net_vat_due)}
              tone={v.vat_credit > 0 ? "emerald" : "amber"}
              big
            />
          </div>

          <div className="rounded-xl border border-border bg-surface-card p-4">
            <table className="w-full text-sm">
              <tbody>
                <Row label={`Taxable sales (net, excl. ${Number(v.rate)}% VAT)`} value={formatTnd(v.sales_net)} />
                <Row label="Output VAT collected" value={formatTnd(v.output_vat)} strong />
                <Row label={`Taxable purchases (net, excl. ${Number(v.rate)}% VAT)`} value={formatTnd(v.purchases_net)} />
                <Row label="Input VAT deductible" value={formatTnd(v.input_vat)} strong />
                <Row
                  label={v.vat_credit > 0 ? "VAT credit" : "Net VAT payable"}
                  value={formatTnd(v.vat_credit > 0 ? v.vat_credit : v.net_vat_due)}
                  strong
                  top
                />
              </tbody>
            </table>
          </div>
          {(v.output_by_rate.length > 1 || v.input_by_rate.length > 1) && (
            <div className="grid gap-4 sm:grid-cols-2">
              <RateTable title="Output VAT by rate" rows={v.output_by_rate} />
              <RateTable title="Input VAT by rate" rows={v.input_by_rate} />
            </div>
          )}
          <p className="text-xs text-text-3">
            VAT is summed line by line at each line's own rate, so mixed rates (0 / 7 / 13 / 19%) are exact.
            Confirmed sales and received purchases dated {v.date_from} to {v.date_to} are included.
          </p>
        </div>
      )}
    </div>
  );
}

const TONES: Record<string, string> = {
  rose: "var(--rose-400)", sky: "var(--sky-400)", emerald: "var(--emerald-400)", amber: "var(--amber-400)",
};

function Stat({ label, value, tone, big }: { label: string; value: string; tone: string; big?: boolean }) {
  return (
    <div className="rounded-xl border border-border bg-surface-card p-4">
      <div className="text-xs uppercase tracking-wide text-text-3">{label}</div>
      <div className="mt-1 font-semibold" style={{ color: TONES[tone], fontSize: big ? 26 : 20 }}>{value}</div>
    </div>
  );
}

function RateTable({ title, rows }: { title: string; rows: { rate: number; base: number; vat: number }[] }) {
  if (rows.length === 0) return null;
  return (
    <div className="rounded-xl border border-border bg-surface-card p-4">
      <div className="mb-2 text-xs uppercase tracking-wide text-text-3">{title}</div>
      <table className="w-full text-sm">
        <thead><tr className="text-text-3"><th className="text-left font-normal">Rate</th><th className="text-right font-normal">Base</th><th className="text-right font-normal">VAT</th></tr></thead>
        <tbody>
          {rows.map((r) => (
            <tr key={r.rate}>
              <td className="py-1">{r.rate}%</td>
              <td className="py-1 text-right">{formatTnd(r.base)}</td>
              <td className="py-1 text-right">{formatTnd(r.vat)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function Row({ label, value, strong, top }: { label: string; value: string; strong?: boolean; top?: boolean }) {
  return (
    <tr style={top ? { borderTop: "2px solid var(--border)" } : undefined}>
      <td className="py-2 text-text-2">{label}</td>
      <td className="py-2 text-right" style={{ fontWeight: strong ? 600 : 400, color: strong ? "var(--text-strong)" : undefined }}>{value}</td>
    </tr>
  );
}
