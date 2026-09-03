import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, CheckCircle2, Plus, ReceiptText, Trash2 } from "lucide-react";
import { PageHead } from "@/components/ui/page-head";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { api } from "@/api/client";
import * as vb from "@/api/vendorBills";
import { partnersApi } from "@/api/partners";
import { documentsApi } from "@/api/documents";
import { listProducts } from "@/api/catalog";

const STATUS_TONE: Record<string, string> = { matched: "green", approved: "sky", exception: "amber", paid: "neutral" };
const FLAG_LABEL: Record<string, string> = {
  over_billed: "Billed more than received",
  price_mismatch: "Price differs from the order",
  not_on_po: "Not on the purchase order",
};

export default function VendorBillsPage() {
  const qc = useQueryClient();
  const billsQ = useQuery({ queryKey: ["vendor-bills"], queryFn: () => vb.listVendorBills() });
  const [createOpen, setCreateOpen] = useState(false);
  const [viewId, setViewId] = useState<number | null>(null);
  const refresh = () => qc.invalidateQueries({ queryKey: ["vendor-bills"] });

  if (billsQ.isLoading) return <TableSkeleton rows={4} />;
  const bills = billsQ.data?.results ?? [];

  return (
    <div>
      <PageHead title="Vendor Bills" sub="Record supplier invoices and match them against the order and what was received before paying.">
        <Button onClick={() => setCreateOpen(true)}><Plus className="h-4 w-4" /> New bill</Button>
      </PageHead>

      {bills.length === 0 ? (
        <EmptyState icon={ReceiptText} title="No vendor bills yet" hint="Record a supplier invoice to check it against the purchase order." />
      ) : (
        <Table>
          <THead>
            <tr><Th>Bill</Th><Th>Supplier</Th><Th>Order</Th><Th className="text-right">Total</Th><Th>Match</Th></tr>
          </THead>
          <TBody>
            {bills.map((b) => (
              <tr key={b.id} className="cursor-pointer" onClick={() => setViewId(b.id)}>
                <Td>
                  <span className="font-medium text-accent">{b.number}</span>
                  {b.supplier_ref && <span className="ml-2 text-xs text-text-3">{b.supplier_ref}</span>}
                </Td>
                <Td>{b.supplier_name}</Td>
                <Td>{b.purchase_order_number ?? "-"}</Td>
                <Td className="text-right">{Number(b.total_amount).toFixed(2)}</Td>
                <Td><Badge tone={STATUS_TONE[b.status] ?? "neutral"}>{b.status}</Badge></Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      {createOpen && <CreateDialog onClose={() => setCreateOpen(false)} onSaved={() => { setCreateOpen(false); refresh(); }} />}
      {viewId !== null && <DetailDialog id={viewId} onClose={() => setViewId(null)} onChanged={refresh} />}
    </div>
  );
}

function DetailDialog({ id, onClose, onChanged }: { id: number; onClose: () => void; onChanged: () => void }) {
  const qc = useQueryClient();
  const q = useQuery({ queryKey: ["vendor-bill", id], queryFn: () => vb.getVendorBill(id) });
  const approve = useMutation({
    mutationFn: () => vb.approveVendorBill(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["vendor-bill", id] }); onChanged(); },
  });
  const b = q.data;

  return (
    <Dialog open onClose={onClose} title={b ? `${b.number}` : "Bill"} className="max-w-3xl">
      {!b ? <TableSkeleton rows={3} /> : (
        <div className="space-y-4">
          <div className="flex flex-wrap items-center gap-3 text-sm">
            <Badge tone={STATUS_TONE[b.status] ?? "neutral"}>{b.status}</Badge>
            <span className="text-text-2">{b.supplier_name}</span>
            {b.purchase_order_number && <span className="text-text-3">vs {b.purchase_order_number}</span>}
            <span className="ml-auto font-semibold">Total {Number(b.total_amount).toFixed(2)}</span>
          </div>

          <Table>
            <THead>
              <tr>
                <Th>Product</Th>
                <Th className="text-right">Billed</Th><Th className="text-right">Ordered</Th><Th className="text-right">Received</Th>
                <Th className="text-right">Bill price</Th><Th className="text-right">PO price</Th><Th>Check</Th>
              </tr>
            </THead>
            <TBody>
              {(b.match ?? []).map((m, i) => (
                <tr key={i}>
                  <Td>{m.product_name}</Td>
                  <Td className="text-right">{m.billed_qty}</Td>
                  <Td className="text-right">{m.ordered_qty}</Td>
                  <Td className="text-right">{m.received_qty}</Td>
                  <Td className="text-right">{m.billed_price.toFixed(2)}</Td>
                  <Td className="text-right">{m.ordered_price != null ? m.ordered_price.toFixed(2) : "-"}</Td>
                  <Td>
                    {m.flags.length === 0 ? (
                      <span className="inline-flex items-center gap-1 text-emerald-500"><CheckCircle2 className="h-4 w-4" /> OK</span>
                    ) : (
                      <span className="inline-flex flex-col gap-0.5">
                        {m.flags.map((f) => (
                          <span key={f} className="inline-flex items-center gap-1 text-amber-500 text-xs">
                            <AlertTriangle className="h-3.5 w-3.5" /> {FLAG_LABEL[f] ?? f}
                          </span>
                        ))}
                      </span>
                    )}
                  </Td>
                </tr>
              ))}
            </TBody>
          </Table>

          {b.status === "exception" && (
            <div className="flex items-center justify-between rounded-md bg-amber-500/10 p-3">
              <span className="text-sm text-text-2">This bill doesn't match the order/receipt. Approve to clear it for payment.</span>
              <Button size="sm" onClick={() => approve.mutate()} disabled={approve.isPending}>
                {approve.isPending ? "Approving…" : "Approve anyway"}
              </Button>
            </div>
          )}
          {b.status === "approved" && b.approved_by_email && (
            <p className="text-sm text-text-3">Exception approved by {b.approved_by_email}.</p>
          )}
        </div>
      )}
    </Dialog>
  );
}

type LineForm = { product: string; quantity: string; unit_price: string };

function CreateDialog({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const suppliers = useQuery({ queryKey: ["suppliers", "active"], queryFn: () => partnersApi("suppliers").list({ is_active: true, page_size: 200 }) });
  const purchases = useQuery({ queryKey: ["purchases", "all"], queryFn: () => documentsApi("purchases").list({ page_size: 200 }) });
  const products = useQuery({ queryKey: ["products", "all"], queryFn: () => listProducts({ page_size: 200 }) });

  const [supplier, setSupplier] = useState("");
  const [po, setPo] = useState("");
  const [ref, setRef] = useState("");
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [lines, setLines] = useState<LineForm[]>([{ product: "", quantity: "", unit_price: "" }]);
  const [error, setError] = useState("");

  const save = useMutation({
    mutationFn: () => vb.createVendorBill({
      supplier: Number(supplier),
      purchase_order: po ? Number(po) : null,
      bill_date: date,
      supplier_ref: ref,
      lines: lines.filter((l) => l.product && l.quantity).map((l) => ({
        product: Number(l.product), quantity: Number(l.quantity), unit_price: Number(l.unit_price || 0),
      })),
    }),
    onSuccess: onSaved,
    onError: (e: any) => setError(e?.response?.data?.detail ?? e?.response?.data?.purchase_order?.[0] ?? "Could not save the bill."),
  });

  // Prefill the lines from a chosen purchase order.
  async function pickPo(id: string) {
    setPo(id);
    if (!id) return;
    const { data } = await api.get<any>(`/purchases/${id}/`);
    if (data?.supplier) setSupplier(String(data.supplier));
    setLines((data.lines ?? []).map((l: any) => ({ product: String(l.product), quantity: String(l.quantity), unit_price: String(l.unit_price) })));
  }

  const supplierPos = (purchases.data?.results ?? []).filter((p: any) => !supplier || String(p.supplier) === supplier);

  return (
    <Dialog open onClose={onClose} title="New vendor bill" className="max-w-2xl">
      <form onSubmit={(e: FormEvent) => { e.preventDefault(); save.mutate(); }} className="space-y-4">
        <div className="grid grid-cols-2 gap-4">
          <div className="space-y-1.5"><Label>Supplier</Label>
            <Select value={supplier} onChange={(e) => setSupplier(e.target.value)} required>
              <option value="">Choose…</option>
              {(suppliers.data?.results ?? []).map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}
            </Select></div>
          <div className="space-y-1.5"><Label>Purchase order (optional)</Label>
            <Select value={po} onChange={(e) => pickPo(e.target.value)}>
              <option value="">None - direct bill</option>
              {supplierPos.map((p: any) => <option key={p.id} value={p.id}>{p.number} ({p.status})</option>)}
            </Select></div>
          <div className="space-y-1.5"><Label>Supplier invoice #</Label>
            <Input value={ref} onChange={(e) => setRef(e.target.value)} placeholder="Their reference" /></div>
          <div className="space-y-1.5"><Label>Bill date</Label>
            <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} required /></div>
        </div>

        <div className="space-y-2">
          <Label>Lines</Label>
          {lines.map((l, i) => (
            <div key={i} className="grid grid-cols-[1fr_90px_110px_auto] gap-2">
              <Select value={l.product} onChange={(e) => setLines((ls) => ls.map((x, j) => j === i ? { ...x, product: e.target.value } : x))} required>
                <option value="">Product…</option>
                {(products.data?.results ?? []).map((p: any) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </Select>
              <Input type="number" step="0.001" min="0" placeholder="Qty" value={l.quantity}
                onChange={(e) => setLines((ls) => ls.map((x, j) => j === i ? { ...x, quantity: e.target.value } : x))} required />
              <Input type="number" step="0.01" min="0" placeholder="Price" value={l.unit_price}
                onChange={(e) => setLines((ls) => ls.map((x, j) => j === i ? { ...x, unit_price: e.target.value } : x))} />
              <Button type="button" size="sm" variant="ghost" onClick={() => setLines((ls) => ls.filter((_, j) => j !== i))}><Trash2 className="h-3.5 w-3.5" /></Button>
            </div>
          ))}
          <Button type="button" size="sm" variant="outline" onClick={() => setLines((ls) => [...ls, { product: "", quantity: "", unit_price: "" }])}>
            <Plus className="h-3.5 w-3.5" /> Add line
          </Button>
        </div>

        {error && <p className="text-sm text-danger">{error}</p>}
        <Button type="submit" className="w-full" disabled={!supplier || save.isPending}>
          {save.isPending ? "Checking…" : "Record & match"}
        </Button>
      </form>
    </Dialog>
  );
}
