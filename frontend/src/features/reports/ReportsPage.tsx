import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { Download } from "lucide-react";
import { downloadReportPdf, getReport } from "@/api/reports";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
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
        <div />
        <Button onClick={() => downloadReportPdf(kind, params)}>
          <Download className="h-4 w-4" /> Export PDF
        </Button>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        <div className="flex rounded-md border border-stroke-soft p-1">
          {kinds.map((k) => (
            <button
              key={k.key}
              onClick={() => setKind(k.key)}
              className={cn(
                "rounded px-4 py-1.5 text-sm",
                kind === k.key ? "bg-accent text-bg" : "text-text-2 hover:text-text"
              )}
            >
              {k.label}
            </button>
          ))}
        </div>
        {dated && (
          <>
            <div className="space-y-1">
              <Label htmlFor="r-from">From</Label>
              <Input id="r-from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label htmlFor="r-to">To</Label>
              <Input id="r-to" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
            </div>
          </>
        )}
      </div>

      {isLoading || !data ? (
        <p className="text-text-2">Loading…</p>
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
