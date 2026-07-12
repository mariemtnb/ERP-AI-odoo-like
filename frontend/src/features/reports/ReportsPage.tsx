import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Download, FileSearch } from "lucide-react";
import { downloadReportPdf, getReport } from "@/api/reports";
import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Segmented } from "@/components/ui/segmented";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { cn } from "@/lib/utils";

type Kind = "sales" | "purchases" | "stock";
const kinds: { key: Kind; label: string }[] = [
  { key: "sales", label: "Sales" },
  { key: "purchases", label: "Purchases" },
  { key: "stock", label: "Stock" },
];

function firstOfMonth() {
  const d = new Date();
  return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().slice(0, 10);
}
const today = () => new Date().toISOString().slice(0, 10);

export default function ReportsPage() {
  const [kind, setKind] = useState<Kind>("sales");
  const [from, setFrom] = useState(firstOfMonth());
  const [to, setTo] = useState(today());
  const dated = kind !== "stock";
  const params = dated ? { from, to } : {};

  const { data, isLoading } = useQuery({
    queryKey: ["report", kind, from, to],
    queryFn: () => getReport(kind, params),
  });

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div className="flex flex-wrap items-end gap-4">
          <Segmented
            id="report-kind"
            options={kinds.map((k) => ({ value: k.key, label: k.label }))}
            value={kind}
            onChange={setKind}
          />
          {dated && (
            <>
              <div className="space-y-1.5">
                <Label htmlFor="r-from">From</Label>
                <Input id="r-from" type="date" className="w-40" value={from} onChange={(e) => setFrom(e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="r-to">To</Label>
                <Input id="r-to" type="date" className="w-40" value={to} onChange={(e) => setTo(e.target.value)} />
              </div>
            </>
          )}
        </div>
        <Button onClick={() => downloadReportPdf(kind, params)}>
          <Download className="h-4 w-4" /> Export PDF
        </Button>
      </div>

      {isLoading || !data ? (
        <TableSkeleton rows={6} />
      ) : data.rows.length === 0 ? (
        <EmptyState
          icon={FileSearch}
          title="Nothing in this period"
          hint="Widen the date range, or come back once documents exist for this report."
        />
      ) : kind === "stock" ? (
        <>
          <Table>
            <THead>
              <tr>
                <Th>SKU</Th><Th>Product</Th><Th>Category</Th>
                <Th className="text-right">Quantity</Th>
                <Th className="text-right">Min level</Th>
                <Th className="text-right">Stock value</Th>
              </tr>
            </THead>
            <TBody>
              {data.rows.map((r: any) => (
                <tr key={r.sku}>
                  <Td className="font-mono text-xs">{r.sku}</Td>
                  <Td className={r.low ? "text-danger" : undefined}>{r.name}</Td>
                  <Td className="text-text-2">{r.category}</Td>
                  <Td className={cn("text-right", r.low && "text-danger")}>{Number(r.quantity)}</Td>
                  <Td className="text-right text-text-2">{Number(r.min_level)}</Td>
                  <Td className="text-right">{Number(r.value).toFixed(2)}</Td>
                </tr>
              ))}
            </TBody>
          </Table>
          <p className="text-right text-sm text-text-2">
            {data.count} products · Total stock value:{" "}
            <span className="font-semibold">{Number(data.total).toFixed(2)}</span>
          </p>
        </>
      ) : (
        <>
          <Table>
            <THead>
              <tr>
                <Th>Number</Th><Th>Date</Th><Th>Partner</Th><Th>Status</Th>
                <Th className="text-right">Total</Th>
              </tr>
            </THead>
            <TBody>
              {data.rows.map((r: any) => (
                <tr key={r.number} className={r.status === "cancelled" ? "opacity-50" : undefined}>
                  <Td className="font-mono text-xs">{r.number}</Td>
                  <Td className="text-text-2">{r.date}</Td>
                  <Td>{r.customer}</Td>
                  <Td className="text-text-2">{r.status}</Td>
                  <Td className="text-right">{Number(r.total).toFixed(2)}</Td>
                </tr>
              ))}
            </TBody>
          </Table>
          <p className="text-right text-sm text-text-2">
            {data.count} documents · Total:{" "}
            <span className="font-semibold">{Number(data.total).toFixed(2)}</span>
          </p>
        </>
      )}
    </div>
  );
}
