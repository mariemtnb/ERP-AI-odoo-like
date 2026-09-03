import { useMemo, useRef, useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { FileText, Mail, Plus, ScanLine, Trash2 } from "lucide-react";
import { Chatter } from "@/components/Chatter";
import { documentsApi, extractInvoice, receivePurchase } from "@/api/documents";
import { downloadInvoice, emailSale, generateInvoice } from "@/api/reports";
import { listProducts } from "@/api/catalog";
import { partnersApi } from "@/api/partners";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { useAuth } from "@/features/auth/AuthContext";
import { useI18n } from "@/lib/i18n";
import type { BusinessDoc } from "@/types";

const statusTone: Record<string, string> = {
  draft: "manager",
  pending_approval: "manager",
  confirmed: "admin",
  partial: "amber",
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
}: {
  kind: "purchases" | "sales";
  title?: string;
}) {
  const { t } = useI18n();
  const isPurchase = kind === "purchases";
  const partnerLabel = isPurchase ? t("field.supplier") : t("field.customer");
  const statusLabel = (s: string) => t(`status.${s}`);
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
  const [portalLink, setPortalLink] = useState("");
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
          (hints ? ` - lines: ${hints}` : "") +
          ". Match each line to a product below."
      );
      setCreateOpen(true);
    } catch (e: any) {
      setScanNote("");
      setError(
        e?.response?.data?.detail ?? e?.message ?? t("docs.extractFailed")
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
    mutationFn: ({ id, name }: { id: number; name: "confirm" | "receive" | "cancel" | "approve" | "reject" }) =>
      client.action(id, name),
    onSuccess: (doc) => {
      invalidate();
      setViewDoc(doc);
      setActionError("");
    },
    onError: (err: any) =>
      setActionError(err?.response?.data?.detail ?? t("docs.actionFailed")),
  });

  const emailMutation = useMutation({
    mutationFn: (id: number) => emailSale(id),
    onSuccess: (r) => { setPortalLink(r.portal_url); invalidate(); },
    onError: (err: any) => setActionError(err?.response?.data?.detail ?? t("docs.actionFailed")),
  });

  // Partial goods receipt: a map of line id -> quantity to receive now.
  const [receiveQty, setReceiveQty] = useState<Record<number, string> | null>(null);
  const receiveMutation = useMutation({
    mutationFn: ({ id, lines }: { id: number; lines: { line: number; quantity: number }[] }) => receivePurchase(id, lines),
    onSuccess: (doc) => { invalidate(); setViewDoc(doc); setReceiveQty(null); setActionError(""); },
    onError: (err: any) => setActionError(err?.response?.data?.detail ?? t("docs.actionFailed")),
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
        <p className="text-sm text-text-3">
          {t(`docs.sub.${kind}`)}
        </p>
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
                  {scanning ? t("docs.readingInvoice") : t("docs.importInvoice")}
                </Button>
              </>
            )}
            <Button onClick={() => { setError(""); setScanNote(""); setCreateOpen(true); }}>
              <Plus className="h-4 w-4" /> {t(`docs.new.${kind}`)}
            </Button>
          </div>
        )}
      </div>

      {isLoading ? (
        <TableSkeleton rows={5} />
      ) : data!.results.length === 0 ? (
        <EmptyState
          icon={isPurchase ? ScanLine : FileText}
          title={t(`docs.empty.${kind}`)}
          hint={t(`docs.emptyHint.${kind}`)}
          action={
            canWrite ? (
              <Button onClick={() => { setError(""); setScanNote(""); setCreateOpen(true); }}>
                <Plus className="h-4 w-4" /> {t(`docs.new.${kind}`)}
              </Button>
            ) : undefined
          }
        />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>{t("docs.col.number")}</Th>
              <Th>{partnerLabel}</Th>
              <Th>{t("common.date")}</Th>
              <Th>{t("common.status")}</Th>
              <Th className="text-right">{t("common.total")}</Th>
              <Th>{t("docs.col.by")}</Th>
            </tr>
          </THead>
          <TBody>
            {data!.results.map((d) => (
              <tr
                key={d.id}
                className="cursor-pointer hover:bg-white/[0.03]"
                onClick={() => { setActionError(""); setPortalLink(""); setViewDoc(d); }}
              >
                <Td className="font-mono text-xs">{d.number}</Td>
                <Td>{isPurchase ? d.supplier_name : d.customer_name}</Td>
                <Td className="text-text-2">
                  {isPurchase ? d.order_date : d.sale_date}
                </Td>
                <Td>
                  <Badge tone={statusTone[d.status]}>{statusLabel(d.status)}</Badge>
                </Td>
                <Td className="text-right">{Number(d.total_amount).toFixed(2)}</Td>
                <Td className="text-xs text-text-2">{d.created_by_email}</Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      {/* Create dialog */}
      <Dialog
        open={createOpen}
        onClose={() => setCreateOpen(false)}
        title={t(`docs.new.${kind}`)}
        className="max-w-2xl"
      >
        <form onSubmit={submit} className="space-y-4">
          {scanNote && (
            <p className="rounded-md border border-accent/40 bg-accent/10 p-2 text-xs text-accent-strong">
              {scanNote}
            </p>
          )}
          <div className="space-y-1.5">
            <Label htmlFor="doc-partner">{partnerLabel}</Label>
            <Select
              id="doc-partner"
              value={partner}
              onChange={(e) => setPartner(e.target.value)}
              required
            >
              <option value="">{t("docs.select")}</option>
              {partnerList?.results.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </Select>
          </div>

          <div className="space-y-2">
            <Label>{t("docs.lines")}</Label>
            {lines.map((line, i) => (
              <div key={i} className="flex items-center gap-2">
                <Select
                  aria-label="line-product"
                  value={line.product}
                  onChange={(e) => setLine(i, "product", e.target.value)}
                  required
                  className="flex-1"
                >
                  <option value="">{t("docs.productPlaceholder")}</option>
                  {products?.results.map((p) => (
                    <option key={p.id} value={p.id}>
                      {p.sku} - {p.name} (stock: {Number(p.quantity_in_stock)})
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
                  placeholder={t("docs.price")}
                  required
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  disabled={lines.length === 1}
                  onClick={() => setLines((ls) => ls.filter((_, j) => j !== i))}
                >
                  <Trash2 className="h-4 w-4 text-danger" />
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
              <Plus className="h-4 w-4" /> {t("docs.addLine")}
            </Button>
          </div>

          <p className="text-right text-sm text-text-2">
            {t("common.total")}: <span className="font-semibold">{total.toFixed(2)}</span>
          </p>
          {error && <p className="break-all text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={createMutation.isPending}>
            {createMutation.isPending ? t("common.saving") : t("docs.createDraft")}
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
                {isPurchase ? viewDoc.supplier_name : viewDoc.customer_name} -{" "}
                <Badge tone={statusTone[viewDoc.status]}>{statusLabel(viewDoc.status)}</Badge>
              </span>
              <span className="text-text-2">
                {t("docs.by")} {viewDoc.created_by_email}
              </span>
            </div>
            <Table>
              <THead>
                <tr>
                  <Th>{t("field.product")}</Th>
                  <Th className="text-right">{t("docs.qty")}</Th>
                  <Th className="text-right">{t("docs.unitPrice")}</Th>
                  <Th className="text-right">{t("docs.subtotal")}</Th>
                </tr>
              </THead>
              <TBody>
                {viewDoc.lines.map((l) => (
                  <tr key={l.id}>
                    <Td>
                      <span className="font-mono text-xs text-text-2">{l.product_sku}</span>{" "}
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
              {t("common.total")}: {Number(viewDoc.total_amount).toFixed(2)}
            </p>
            {actionError && <p className="text-sm text-danger">{actionError}</p>}
            {portalLink && (
              <div className="rounded-md bg-surface-2 p-3 text-sm">
                <p className="mb-1 text-text-2">{t("docs.emailSent")}</p>
                <div className="flex items-center gap-2">
                  <input readOnly value={portalLink} className="flex-1 rounded border border-border bg-surface px-2 py-1 text-xs text-text-2" />
                  <Button size="sm" variant="outline" onClick={() => navigator.clipboard?.writeText(portalLink)}>{t("common.copy")}</Button>
                </div>
              </div>
            )}
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
                    <FileText className="h-4 w-4" /> {t("docs.invoicePdf")}
                  </Button>
                )}
                {!isPurchase && (
                  <Button
                    variant="outline"
                    disabled={emailMutation.isPending}
                    onClick={() => { setActionError(""); emailMutation.mutate(viewDoc.id); }}
                  >
                    <Mail className="h-4 w-4" /> {emailMutation.isPending ? t("docs.emailing") : t("docs.emailCustomer")}
                  </Button>
                )}
                {viewDoc.status === "draft" && (
                  <Button
                    onClick={() => actionMutation.mutate({ id: viewDoc.id, name: "confirm" })}
                    disabled={actionMutation.isPending}
                  >
                    {t("docs.confirm")}
                  </Button>
                )}
                {isPurchase && viewDoc.status === "pending_approval" && user!.role === "admin" && (
                  <>
                    <Button
                      onClick={() => actionMutation.mutate({ id: viewDoc.id, name: "approve" })}
                      disabled={actionMutation.isPending}
                    >
                      {t("docs.approve")}
                    </Button>
                    <Button
                      variant="outline"
                      onClick={() => actionMutation.mutate({ id: viewDoc.id, name: "reject" })}
                      disabled={actionMutation.isPending}
                    >
                      {t("docs.sendBack")}
                    </Button>
                  </>
                )}
                {isPurchase && viewDoc.status === "pending_approval" && user!.role !== "admin" && (
                  <p className="self-center text-sm text-warning">
                    {t("docs.awaiting")}
                  </p>
                )}
                {isPurchase && (viewDoc.status === "confirmed" || viewDoc.status === "partial") && (
                  <Button
                    onClick={() => setReceiveQty(Object.fromEntries(
                      (viewDoc.lines ?? []).map((l) => [l.id!, l.remaining ?? l.quantity])
                    ))}
                    disabled={receiveMutation.isPending}
                  >
                    {t("docs.receive")}
                  </Button>
                )}
                {(viewDoc.status === "draft" || viewDoc.status === "confirmed") && (
                  <Button
                    variant="destructive"
                    onClick={() => actionMutation.mutate({ id: viewDoc.id, name: "cancel" })}
                    disabled={actionMutation.isPending}
                  >
                    {t(`docs.cancel.${kind}`)}
                  </Button>
                )}
              </div>
            )}
            <Chatter type={kind} id={viewDoc.id} />
          </div>
        )}
      </Dialog>

      {/* partial goods receipt */}
      {receiveQty && viewDoc && (
        <Dialog open onClose={() => setReceiveQty(null)} title={t("docs.receiveGoods")}>
          <div className="space-y-3">
            <p className="text-sm text-text-2">{t("docs.receiveHint")}</p>
            {(viewDoc.lines ?? []).map((l) => {
              const remaining = Number(l.remaining ?? l.quantity);
              return (
                <div key={l.id} className="flex items-center gap-3">
                  <span className="flex-1 text-sm">{l.product_name}
                    <span className="ml-2 text-xs text-text-3">{t("docs.remaining")}: {remaining}</span>
                  </span>
                  <Input type="number" min="0" max={remaining} step="0.001" className="w-28"
                    value={receiveQty[l.id!] ?? ""}
                    onChange={(e) => setReceiveQty((m) => ({ ...m!, [l.id!]: e.target.value }))}
                    disabled={remaining <= 0} />
                </div>
              );
            })}
            {actionError && <p className="text-sm text-danger">{actionError}</p>}
            <div className="flex justify-end gap-2 pt-1">
              <Button variant="ghost" onClick={() => setReceiveQty(null)}>{t("common.cancel")}</Button>
              <Button
                disabled={receiveMutation.isPending}
                onClick={() => receiveMutation.mutate({
                  id: viewDoc.id,
                  lines: Object.entries(receiveQty)
                    .map(([line, q]) => ({ line: Number(line), quantity: Number(q || 0) }))
                    .filter((x) => x.quantity > 0),
                })}
              >
                {receiveMutation.isPending ? t("docs.receiving") : t("docs.receive")}
              </Button>
            </div>
          </div>
        </Dialog>
      )}
    </div>
  );
}
