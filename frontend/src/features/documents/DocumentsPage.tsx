import { useMemo, useRef, useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FileText, Plus, ScanLine, Trash2 } from "lucide-react";
import { documentsApi, extractInvoice } from "@/api/documents";
import { downloadInvoice, generateInvoice } from "@/api/reports";
import { listProducts } from "@/api/catalog";
import { partnersApi } from "@/api/partners";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { useAuth } from "@/features/auth/AuthContext";
import type { BusinessDoc } from "@/types";

const statusTone: Record<string, string> = {
  draft: "manager",
  confirmed: "admin",
  received: "green",
  cancelled: "red",
};

interface LineDraft {
  product: string;
  quantity: string;
  unit_price: string;
}

export default function DocumentsPage({
  kind,
  title,
}: {
  kind: "purchases" | "sales";
  title: string;
}) {
  const isPurchase = kind === "purchases";
  const partnerKind = isPurchase ? "suppliers" : "customers";
  const partnerField = isPurchase ? "supplier" : "customer";
  const dateField = isPurchase ? "order_date" : "sale_date";

  const { user } = useAuth();
  // Purchases: managers only. Sales: employees may create/confirm too.
  const canWrite = !isPurchase || user!.role !== "employee";

  const client = useMemo(() => documentsApi(kind), [kind]);
  const partners = useMemo(() => partnersApi(partnerKind), [partnerKind]);
  const qc = useQueryClient();

  const { data, isLoading } = useQuery({
    queryKey: [kind],
    queryFn: () => client.list(),
  });
  const { data: partnerList } = useQuery({
    queryKey: [partnerKind, "all"],
    queryFn: () => partners.list({ page_size: 100, is_active: "true" }),
  });
  const { data: products } = useQuery({
    queryKey: ["products", "all"],
    queryFn: () => listProducts({ page_size: 100, is_active: "true" }),
  });

  const [createOpen, setCreateOpen] = useState(false);
  const [viewDoc, setViewDoc] = useState<BusinessDoc | null>(null);
  const [partner, setPartner] = useState("");
  const [lines, setLines] = useState<LineDraft[]>([
    { product: "", quantity: "1", unit_price: "" },
  ]);
  const [error, setError] = useState("");
  const [actionError, setActionError] = useState("");
  const [scanning, setScanning] = useState(false);
  const [scanNote, setScanNote] = useState("");
  const fileRef = useRef<HTMLInputElement>(null);

  async function onInvoiceFile(file: File) {
    setScanning(true);
    setScanNote("");
    setError("");
    try {
      const data = await extractInvoice(file);
      if (data.error) throw new Error(data.error);
      setPartner(data.matched_supplier_id ? String(data.matched_supplier_id) : "");
      setLines(
        (data.lines?.length ? data.lines : []).map((l) => ({
          product: "",
          quantity: String(l.quantity || 1),
          unit_price: String(l.unit_price ?? ""),
        }))
      );
      const hints = (data.lines ?? []).map((l) => l.description).join(" · ");
      setScanNote(
        `Extracted from ${data.supplier_name ?? "unknown supplier"}` +
          (data.invoice_number ? ` (${data.invoice_number})` : "") +
          (hints ? ` — lines: ${hints}` : "") +
          ". Match each line to a product below."
      );
      setCreateOpen(true);
    } catch (e: any) {
      setScanNote("");
      setError(
        e?.response?.data?.detail ?? e?.message ?? "Invoice extraction failed."
      );
      setCreateOpen(true);
    } finally {
      setScanning(false);
    }
  }

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: [kind] });
    qc.invalidateQueries({ queryKey: ["products"] });
    qc.invalidateQueries({ queryKey: ["movements"] });
  };

  const createMutation = useMutation({
    mutationFn: () =>
      client.create({
        [partnerField]: Number(partner),
        [dateField]: new Date().toISOString().slice(0, 10),
        lines: lines.map((l) => ({
          product: Number(l.product),
          quantity: l.quantity,
          unit_price: l.unit_price,
        })),
      }),
    onSuccess: () => {
      invalidate();
      setCreateOpen(false);
      setPartner("");
      setLines([{ product: "", quantity: "1", unit_price: "" }]);
      setError("");
    },
    onError: (err: any) => {
      setError(JSON.stringify(err?.response?.data ?? "Request failed."));
    },
  });

  const actionMutation = useMutation({
    mutationFn: ({ id, name }: { id: number; name: "confirm" | "receive" | "cancel" }) =>
      client.action(id, name),
    onSuccess: (doc) => {
      invalidate();
      setViewDoc(doc);
      setActionError("");
    },
    onError: (err: any) =>
      setActionError(err?.response?.data?.detail ?? "Action failed."),
  });

  function submit(e: FormEvent) {
    e.preventDefault();
    createMutation.mutate();
  }

  function setLine(i: number, key: keyof LineDraft, value: string) {
    setLines((ls) => {
      const next = [...ls];
      next[i] = { ...next[i], [key]: value };
      // Prefill price from the product's default when product changes.
      if (key === "product") {
        const p = products?.results.find((p) => String(p.id) === value);
        if (p) next[i].unit_price = isPurchase ? p.cost_price : p.sale_price;
      }
      return next;
    });
  }

  const total = lines.reduce(
    (sum, l) => sum + (Number(l.quantity) || 0) * (Number(l.unit_price) || 0),
    0
  );

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">{title}</h1>
        {canWrite && (
          <div className="flex gap-2">
            {isPurchase && (
              <>
                <input
                  ref={fileRef}
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  className="hidden"
                  onChange={(e) => {
                    const f = e.target.files?.[0];
                    if (f) void onInvoiceFile(f);
                    e.target.value = "";
                  }}
                />
                <Button
                  variant="outline"
                  disabled={scanning}
                  onClick={() => fileRef.current?.click()}
                >
                  <ScanLine className="h-4 w-4" />
                  {scanning ? "Reading invoice…" : "Import from invoice"}
                </Button>
              </>
            )}
            <Button onClick={() => { setError(""); setScanNote(""); setCreateOpen(true); }}>
              <Plus className="h-4 w-4" /> New {isPurchase ? "purchase order" : "sale"}
            </Button>
          </div>
        )}
      </div>

      {isLoading ? (
        <p className="text-slate-400">Loading…</p>
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>Number</Th>
              <Th>{isPurchase ? "Supplier" : "Customer"}</Th>
              <Th>Date</Th>
              <Th>Status</Th>
              <Th className="text-right">Total</Th>
              <Th>By</Th>
            </tr>
          </THead>
          <TBody>
            {data!.results.map((d) => (
              <tr
                key={d.id}
                className="cursor-pointer hover:bg-slate-900"
                onClick={() => { setActionError(""); setViewDoc(d); }}
              >
                <Td className="font-mono text-xs">{d.number}</Td>
                <Td>{isPurchase ? d.supplier_name : d.customer_name}</Td>
                <Td className="text-slate-400">
                  {isPurchase ? d.order_date : d.sale_date}
                </Td>
                <Td>
                  <Badge tone={statusTone[d.status]}>{d.status}</Badge>
                </Td>
                <Td className="text-right">{Number(d.total_amount).toFixed(2)}</Td>
                <Td className="text-xs text-slate-400">{d.created_by_email}</Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      {/* Create dialog */}
      <Dialog
        open={createOpen}
        onClose={() => setCreateOpen(false)}
        title={isPurchase ? "New purchase order" : "New sale"}
        className="max-w-2xl"
      >
        <form onSubmit={submit} className="space-y-4">
          {scanNote && (
            <p className="rounded-md border border-indigo-500/40 bg-indigo-500/10 p-2 text-xs text-indigo-300">
              {scanNote}
            </p>
          )}
          <div className="space-y-1.5">
            <Label htmlFor="doc-partner">{isPurchase ? "Supplier" : "Customer"}</Label>
            <Select
              id="doc-partner"
              value={partner}
              onChange={(e) => setPartner(e.target.value)}
              required
            >
              <option value="">Select…</option>
              {partnerList?.results.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </Select>
          </div>

          <div className="space-y-2">
            <Label>Lines</Label>
            {lines.map((line, i) => (
              <div key={i} className="flex items-center gap-2">
                <Select
                  aria-label="line-product"
                  value={line.product}
                  onChange={(e) => setLine(i, "product", e.target.value)}
                  required
                  className="flex-1"
                >
                  <option value="">Product…</option>
                  {products?.results.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.sku} — {p.name} (stock: {Number(p.quantity_in_stock)})
                    </option>
                  ))}
                </Select>
                <Input
                  aria-label="line-quantity"
                  type="number"
                  step="0.001"
                  min="0.001"
                  value={line.quantity}
                  onChange={(e) => setLine(i, "quantity", e.target.value)}
                  className="w-24"
                  required
                />
                <Input
                  aria-label="line-price"
                  type="number"
                  step="0.01"
                  min="0"
                  value={line.unit_price}
                  onChange={(e) => setLine(i, "unit_price", e.target.value)}
                  className="w-28"
                  placeholder="Price"
                  required
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  disabled={lines.length === 1}
                  onClick={() => setLines((ls) => ls.filter((_, j) => j !== i))}
                >
                  <Trash2 className="h-4 w-4 text-red-400" />
                </Button>
              </div>
            ))}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() =>
                setLines((ls) => [...ls, { product: "", quantity: "1", unit_price: "" }])
              }
            >
              <Plus className="h-4 w-4" /> Add line
            </Button>
          </div>

          <p className="text-right text-sm text-slate-300">
            Total: <span className="font-semibold">{total.toFixed(2)}</span>
          </p>
          {error && <p className="break-all text-sm text-red-400">{error}</p>}
          <Button type="submit" className="w-full" disabled={createMutation.isPending}>
            {createMutation.isPending ? "Saving…" : "Create draft"}
          </Button>
        </form>
      </Dialog>

      {/* Detail dialog */}
      <Dialog
        open={viewDoc !== null}
        onClose={() => setViewDoc(null)}
        title={viewDoc?.number ?? ""}
        className="max-w-2xl"
      >
        {viewDoc && (
          <div className="space-y-4">
            <div className="flex items-center justify-between text-sm">
              <span>
                {isPurchase ? viewDoc.supplier_name : viewDoc.customer_name} —{" "}
                <Badge tone={statusTone[viewDoc.status]}>{viewDoc.status}</Badge>
              </span>
              <span className="text-slate-400">
                by {viewDoc.created_by_email}
              </span>
            </div>
            <Table>
              <THead>
                <tr>
                  <Th>Product</Th>
                  <Th className="text-right">Qty</Th>
                  <Th className="text-right">Unit price</Th>
                  <Th className="text-right">Subtotal</Th>
                </tr>
              </THead>
              <TBody>
                {viewDoc.lines.map((l) => (
                  <tr key={l.id}>
                    <Td>
                      <span className="font-mono text-xs text-slate-400">{l.product_sku}</span>{" "}
                      {l.product_name}
                    </Td>
                    <Td className="text-right">{Number(l.quantity)}</Td>
                    <Td className="text-right">{Number(l.unit_price).toFixed(2)}</Td>
                    <Td className="text-right">{Number(l.subtotal).toFixed(2)}</Td>
                  </tr>
                ))}
              </TBody>
            </Table>
            <p className="text-right font-semibold">
              Total: {Number(viewDoc.total_amount).toFixed(2)}
            </p>
            {actionError && <p className="text-sm text-red-400">{actionError}</p>}
            {canWrite && (
              <div className="flex justify-end gap-2">
                {!isPurchase && viewDoc.status === "confirmed" && (
                  <Button
                    variant="outline"
                    onClick={async () => {
                      const inv = await generateInvoice(viewDoc.id);
                      await downloadInvoice(viewDoc.id, inv.number);
                    }}
                  >
                    <FileText className="h-4 w-4" /> Invoice PDF
                  </Button>
                )}
                {viewDoc.status === "draft" && (
                  <Button
                    onClick={() => actionMutation.mutate({ id: viewDoc.id, name: "confirm" })}
                    disabled={actionMutation.isPending}
                  >
                    Confirm
                  </Button>
                )}
                {isPurchase && viewDoc.status === "confirmed" && (
                  <Button
                    onClick={() => actionMutation.mutate({ id: viewDoc.id, name: "receive" })}
                    disabled={actionMutation.isPending}
                  >
                    Receive goods
                  </Button>
                )}
                {(viewDoc.status === "draft" || viewDoc.status === "confirmed") && (
                  <Button
                    variant="destructive"
                    onClick={() => actionMutation.mutate({ id: viewDoc.id, name: "cancel" })}
                    disabled={actionMutation.isPending}
                  >
                    Cancel {isPurchase ? "order" : "sale"}
                  </Button>
                )}
              </div>
            )}
          </div>
        )}
      </Dialog>
    </div>
  );
}
