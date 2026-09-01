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
          <p className="text-xs text-text-3">
            Figures assume document totals include VAT at the company rate ({Number(v.rate)}%). Confirmed sales and
            received purchases dated {v.date_from} to {v.date_to} are included.
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

function Row({ label, value, strong, top }: { label: string; value: string; strong?: boolean; top?: boolean }) {
  return (
    <tr style={top ? { borderTop: "2px solid var(--border)" } : undefined}>
      <td className="py-2 text-text-2">{label}</td>
      <td className="py-2 text-right" style={{ fontWeight: strong ? 600 : 400, color: strong ? "var(--text-strong)" : undefined }}>{value}</td>
    </tr>
  );
}
