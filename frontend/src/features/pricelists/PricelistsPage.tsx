import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Tag, Trash2 } from "lucide-react";
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
import * as pl from "@/api/pricelists";
import { listCategories, listProducts } from "@/api/catalog";
import type { Pricelist } from "@/api/pricelists";

export default function PricelistsPage() {
  const qc = useQueryClient();
  const listsQ = useQuery({ queryKey: ["pricelists"], queryFn: () => pl.listPricelists() });
  const [newOpen, setNewOpen] = useState(false);
  const [name, setName] = useState("");
  const [isDefault, setIsDefault] = useState(false);
  const [openId, setOpenId] = useState<number | null>(null);

  const refresh = () => qc.invalidateQueries({ queryKey: ["pricelists"] });
  const create = useMutation({
    mutationFn: () => pl.createPricelist({ name, is_default: isDefault }),
    onSuccess: () => { setNewOpen(false); setName(""); setIsDefault(false); refresh(); },
  });
  const remove = useMutation({ mutationFn: (id: number) => pl.deletePricelist(id), onSuccess: refresh });
  const toggleDefault = useMutation({
    mutationFn: (l: Pricelist) => pl.updatePricelist(l.id, { is_default: !l.is_default }),
    onSuccess: refresh,
  });

  if (listsQ.isLoading) return <TableSkeleton rows={4} />;
  const lists = listsQ.data ?? [];

  return (
    <div>
      <PageHead title="Pricelists & Discounts" sub="Set customer- and quantity-based prices. A customer without a pricelist uses the default one.">
        <Button onClick={() => setNewOpen(true)}><Plus className="h-4 w-4" /> New pricelist</Button>
      </PageHead>

      {lists.length === 0 ? (
        <EmptyState icon={Tag} title="No pricelists yet" hint="Create one, then add rules for products, categories or everything." />
      ) : (
        <Table>
          <THead>
            <tr><Th>Name</Th><Th>Rules</Th><Th>Default</Th><Th>Status</Th><Th /></tr>
          </THead>
          <TBody>
            {lists.map((l) => (
              <tr key={l.id}>
                <Td>
                  <button className="font-medium text-accent hover:underline" onClick={() => setOpenId(l.id)}>{l.name}</button>
                </Td>
                <Td>{l.rule_count}</Td>
                <Td>
                  <button onClick={() => toggleDefault.mutate(l)}>
                    <Badge tone={l.is_default ? "emerald" : "neutral"}>{l.is_default ? "Default" : "Set default"}</Badge>
                  </button>
                </Td>
                <Td><Badge tone={l.is_active ? "green" : "red"}>{l.is_active ? "active" : "inactive"}</Badge></Td>
                <Td className="text-right">
                  <Button size="sm" variant="ghost" onClick={() => remove.mutate(l.id)}><Trash2 className="h-3.5 w-3.5" /></Button>
                </Td>
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <Dialog open={newOpen} onClose={() => setNewOpen(false)} title="New pricelist">
        <form onSubmit={(e: FormEvent) => { e.preventDefault(); create.mutate(); }} className="space-y-4">
          <div className="space-y-1.5"><Label htmlFor="pl-name">Name</Label>
            <Input id="pl-name" value={name} onChange={(e) => setName(e.target.value)} placeholder="e.g. Wholesale" required /></div>
          <label className="flex items-center gap-2 text-sm text-text-2">
            <input type="checkbox" checked={isDefault} onChange={(e) => setIsDefault(e.target.checked)} />
            Make this the default pricelist
          </label>
          <Button type="submit" className="w-full" disabled={!name || create.isPending}>
            {create.isPending ? "Saving…" : "Create"}
          </Button>
        </form>
      </Dialog>

      {openId !== null && <RulesDialog id={openId} onClose={() => { setOpenId(null); refresh(); }} />}
    </div>
  );
}

function RulesDialog({ id, onClose }: { id: number; onClose: () => void }) {
  const qc = useQueryClient();
  const detailQ = useQuery({ queryKey: ["pricelist", id], queryFn: () => pl.getPricelist(id) });
  const productsQ = useQuery({ queryKey: ["products", "all"], queryFn: () => listProducts({ page_size: 200 }) });
  const catsQ = useQuery({ queryKey: ["categories"], queryFn: () => listCategories() });

  const [target, setTarget] = useState("all");   // all | product | category
  const [refId, setRefId] = useState("");
  const [minQty, setMinQty] = useState("1");
  const [mode, setMode] = useState<"fixed" | "discount">("fixed");
  const [value, setValue] = useState("");
  const [error, setError] = useState("");

  const invalidate = () => { qc.invalidateQueries({ queryKey: ["pricelist", id] }); qc.invalidateQueries({ queryKey: ["pricelists"] }); };
  const add = useMutation({
    mutationFn: () => pl.addPricelistRule(id, {
      product_id: target === "product" ? Number(refId) : null,
      category_id: target === "category" ? Number(refId) : null,
      min_qty: Number(minQty || 0),
      mode, value: Number(value),
    }),
    onSuccess: () => { invalidate(); setValue(""); setRefId(""); setError(""); },
    onError: (e: any) => setError(e?.response?.data?.value?.[0] ?? e?.response?.data?.detail ?? "Could not add the rule."),
  });
  const del = useMutation({ mutationFn: (rid: number) => pl.removePricelistRule(rid), onSuccess: invalidate });

  const list = detailQ.data;
  const products = productsQ.data?.results ?? [];
  const cats = catsQ.data ?? [];

  return (
    <Dialog open onClose={onClose} title={list ? `${list.name} — rules` : "Rules"} className="max-w-2xl">
      {!list ? <TableSkeleton rows={3} /> : (
        <div className="space-y-4">
          {(list.rules ?? []).length === 0 ? (
            <p className="text-sm text-text-3">No rules yet — add one below.</p>
          ) : (
            <Table>
              <THead><tr><Th>Applies to</Th><Th>From qty</Th><Th>Price rule</Th><Th /></tr></THead>
              <TBody>
                {list.rules!.map((r) => (
                  <tr key={r.id}>
                    <Td>{r.product_name ? `Product: ${r.product_name}` : r.category_name ? `Category: ${r.category_name}` : "All products"}</Td>
                    <Td>{Number(r.min_qty)}</Td>
                    <Td>{r.mode === "discount" ? `${Number(r.value)}% off` : `Fixed ${Number(r.value).toFixed(2)}`}</Td>
                    <Td className="text-right"><Button size="sm" variant="ghost" onClick={() => del.mutate(r.id)}><Trash2 className="h-3.5 w-3.5" /></Button></Td>
                  </tr>
                ))}
              </TBody>
            </Table>
          )}

          <form onSubmit={(e: FormEvent) => { e.preventDefault(); add.mutate(); }} className="space-y-3 rounded-md bg-surface-2 p-3">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5"><Label>Applies to</Label>
                <Select value={target} onChange={(e) => { setTarget(e.target.value); setRefId(""); }}>
                  <option value="all">All products</option>
                  <option value="product">A product</option>
                  <option value="category">A category</option>
                </Select></div>
              {target === "product" && (
                <div className="space-y-1.5"><Label>Product</Label>
                  <Select value={refId} onChange={(e) => setRefId(e.target.value)} required>
                    <option value="">Choose…</option>
                    {products.map((p: any) => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </Select></div>
              )}
              {target === "category" && (
                <div className="space-y-1.5"><Label>Category</Label>
                  <Select value={refId} onChange={(e) => setRefId(e.target.value)} required>
                    <option value="">Choose…</option>
                    {cats.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </Select></div>
              )}
              <div className="space-y-1.5"><Label>From quantity</Label>
                <Input type="number" min="0" step="0.001" value={minQty} onChange={(e) => setMinQty(e.target.value)} /></div>
              <div className="space-y-1.5"><Label>Rule</Label>
                <Select value={mode} onChange={(e) => setMode(e.target.value as "fixed" | "discount")}>
                  <option value="fixed">Fixed price</option>
                  <option value="discount">% discount off base</option>
                </Select></div>
              <div className="space-y-1.5"><Label>{mode === "discount" ? "Percent off" : "Price"}</Label>
                <Input type="number" min="0" step="0.001" value={value} onChange={(e) => setValue(e.target.value)} required /></div>
            </div>
            {error && <p className="text-sm text-danger">{error}</p>}
            <Button size="sm" type="submit" disabled={add.isPending || !value || (target !== "all" && !refId)}>
              <Plus className="h-3.5 w-3.5" /> Add rule
            </Button>
          </form>
        </div>
      )}
    </Dialog>
  );
}
