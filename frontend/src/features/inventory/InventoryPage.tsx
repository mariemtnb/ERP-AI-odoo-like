import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { History } from "lucide-react";
import { createMovement, listMovements } from "@/api/inventory";
import { listProducts } from "@/api/catalog";
import { listWarehouses, transferStock } from "@/api/crm";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { useAuth } from "@/features/auth/AuthContext";

const typeTone: Record<string, string> = { in: "green", out: "red", adjustment: "manager" };

function TransferCard({
  warehouses,
  products,
}: {
  warehouses: import("@/types").Warehouse[];
  products: import("@/types").Product[];
}) {
  const qc = useQueryClient();
  const [form, setForm] = useState({ product: "", from: "", to: "", quantity: "" });
  const [error, setError] = useState("");

  const mutation = useMutation({
    mutationFn: () =>
      transferStock({
        product: Number(form.product),
        from_warehouse: Number(form.from),
        to_warehouse: Number(form.to),
        quantity: form.quantity,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["movements"] });
      qc.invalidateQueries({ queryKey: ["products"] });
      setForm((f) => ({ ...f, quantity: "" }));
      setError("");
    },
    onError: (e: any) =>
      setError(e?.response?.data?.detail ?? "Transfer failed."),
  });

  const set = (k: keyof typeof form) => (e: { target: { value: string } }) =>
    setForm((f) => ({ ...f, [k]: e.target.value }));

  return (
    <Card>
      <CardHeader>
        <CardTitle>Warehouse transfer</CardTitle>
      </CardHeader>
      <CardContent>
        <form
          className="space-y-3"
          onSubmit={(e) => {
            e.preventDefault();
            mutation.mutate();
          }}
        >
          <Select value={form.product} onChange={set("product")} required aria-label="transfer-product">
            <option value="">Product…</option>
            {products.map((p) => (
              <option key={p.id} value={p.id}>{p.sku} — {p.name}</option>
            ))}
          </Select>
          <div className="grid grid-cols-2 gap-3">
            <Select value={form.from} onChange={set("from")} required aria-label="transfer-from">
              <option value="">From…</option>
              {warehouses.map((w) => (
                <option key={w.id} value={w.id}>{w.name}</option>
              ))}
            </Select>
            <Select value={form.to} onChange={set("to")} required aria-label="transfer-to">
              <option value="">To…</option>
              {warehouses.map((w) => (
                <option key={w.id} value={w.id}>{w.name}</option>
              ))}
            </Select>
          </div>
          <Input
            type="number"
            step="0.001"
            min="0.001"
            placeholder="Quantity"
            value={form.quantity}
            onChange={set("quantity")}
            required
            aria-label="transfer-quantity"
          />
          {error && <p className="text-sm text-danger">{error}</p>}
          <Button type="submit" className="w-full" disabled={mutation.isPending}>
            {mutation.isPending ? "Transferring…" : "Transfer"}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

export default function InventoryPage() {
  const { user } = useAuth();
  const canWrite = user!.role !== "employee";
  const qc = useQueryClient();

  const { data: movements, isLoading } = useQuery({
    queryKey: ["movements"],
    queryFn: () => listMovements(),
  });
  const { data: lowStock } = useQuery({
    queryKey: ["products", "low"],
    queryFn: () => listProducts({ low_stock: "true", is_active: "true" }),
  });
  const { data: products } = useQuery({
    queryKey: ["products", "all"],
    queryFn: () => listProducts({ page_size: 100, is_active: "true" }),
  });
  const { data: warehouses = [] } = useQuery({
    queryKey: ["warehouses"],
    queryFn: listWarehouses,
  });

  const [form, setForm] = useState({ product: "", movement_type: "in", quantity: "", reason: "", warehouse: "" });
  const [error, setError] = useState("");

  const mutation = useMutation({
    mutationFn: () =>
      createMovement({
        product: Number(form.product),
        movement_type: form.movement_type,
        quantity: form.quantity,
        reason: form.reason,
        ...(form.warehouse ? { warehouse: Number(form.warehouse) } : {}),
      } as any),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["movements"] });
      qc.invalidateQueries({ queryKey: ["products"] });
      setForm((f) => ({ ...f, quantity: "", reason: "" }));
      setError("");
    },
    onError: (err: any) => {
      const data = err?.response?.data;
      setError(
        data ? Object.values(data).flat().join(" ") : "Failed to record movement."
      );
    },
  });

  function submit(e: FormEvent) {
    e.preventDefault();
    mutation.mutate();
  }

  return (
    <div className="space-y-6">
      <p className="text-sm text-text-3">
        Every time stock goes up or down it is written here — nothing is ever erased,
        so you can always see who changed what, and why.
      </p>

      <div className="grid gap-6 lg:grid-cols-3">
        {canWrite && (
          <Card>
            <CardHeader>
              <CardTitle>Record movement</CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={submit} className="space-y-3">
                <div className="space-y-1.5">
                  <Label htmlFor="product">Product</Label>
                  <Select
                    id="product"
                    value={form.product}
                    onChange={(e) => setForm((f) => ({ ...f, product: e.target.value }))}
                    required
                  >
                    <option value="">Select product…</option>
                    {products?.results.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.sku} — {p.name} (stock: {Number(p.quantity_in_stock)})
                      </option>
                    ))}
                  </Select>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="movement_type">Type</Label>
                    <Select
                      id="movement_type"
                      value={form.movement_type}
                      onChange={(e) =>
                        setForm((f) => ({ ...f, movement_type: e.target.value }))
                      }
                    >
                      <option value="in">Add stock (goods arrived)</option>
                      <option value="out">Remove stock (goods left)</option>
                      <option value="adjustment">Fix a counting mistake (±)</option>
                    </Select>
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="quantity">Quantity</Label>
                    <Input
                      id="quantity"
                      type="number"
                      step="0.001"
                      value={form.quantity}
                      onChange={(e) => setForm((f) => ({ ...f, quantity: e.target.value }))}
                      required
                    />
                  </div>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="warehouse">Warehouse</Label>
                  <Select
                    id="warehouse"
                    value={form.warehouse}
                    onChange={(e) => setForm((f) => ({ ...f, warehouse: e.target.value }))}
                  >
                    {warehouses.filter((w) => w.is_active).map((w) => (
                      <option key={w.id} value={w.is_default ? "" : w.id}>
                        {w.name}{w.is_default ? " (default)" : ""}
                      </option>
                    ))}
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="reason">Reason</Label>
                  <Input
                    id="reason"
                    value={form.reason}
                    onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))}
                    placeholder="Why? e.g. delivery arrived, item damaged…"
                  />
                </div>
                {error && <p className="text-sm text-danger">{error}</p>}
                <Button type="submit" className="w-full" disabled={mutation.isPending}>
                  {mutation.isPending ? "Saving…" : "Record"}
                </Button>
              </form>
            </CardContent>
          </Card>
        )}

        {canWrite && warehouses.length > 1 && (
          <TransferCard warehouses={warehouses} products={products?.results ?? []} />
        )}

        <Card className={canWrite ? (warehouses.length > 1 ? "" : "lg:col-span-2") : "lg:col-span-3"}>
          <CardHeader>
            <CardTitle>
              Low stock alerts{" "}
              {lowStock && lowStock.count > 0 && (
                <Badge tone="red" className="ml-1">{lowStock.count}</Badge>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {!lowStock || lowStock.count === 0 ? (
              <p className="text-sm text-text-2">No products below their minimum level.</p>
            ) : (
              <ul className="divide-y divide-stroke-soft text-sm">
                {lowStock.results.map((p) => (
                  <li key={p.id} className="flex justify-between py-2">
                    <span>
                      <span className="font-mono text-xs text-text-2">{p.sku}</span>{" "}
                      {p.name}
                    </span>
                    <span className="text-danger">
                      {Number(p.quantity_in_stock)} / min {Number(p.min_stock_level)}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </div>

      <div>
        <h2 className="mb-3 text-lg font-semibold">Movement history</h2>
        {isLoading ? (
          <TableSkeleton rows={6} />
        ) : movements!.results.length === 0 ? (
          <EmptyState
            icon={History}
            title="No movements yet"
            hint="Record a stock-in, receive a purchase order, or confirm a sale — every change lands here, permanently."
          />
        ) : (
          <Table>
            <THead>
              <tr>
                <Th>Date</Th>
                <Th>Product</Th>
                <Th>Type</Th>
                <Th className="text-right">Qty</Th>
                <Th>Warehouse</Th>
                <Th>Reason</Th>
                <Th>Source</Th>
                <Th>By</Th>
              </tr>
            </THead>
            <TBody>
              {movements!.results.map((m) => (
                <tr key={m.id}>
                  <Td className="whitespace-nowrap text-xs text-text-2">
                    {new Date(m.created_at).toLocaleString()}
                  </Td>
                  <Td>
                    <span className="font-mono text-xs text-text-2">{m.product_sku}</span>{" "}
                    {m.product_name}
                  </Td>
                  <Td>
                    <Badge tone={typeTone[m.movement_type]}>{m.movement_type}</Badge>
                  </Td>
                  <Td className="text-right">{Number(m.quantity)}</Td>
                  <Td className="text-text-2">{m.warehouse_name ?? "—"}</Td>
                  <Td className="text-text-2">{m.reason || "—"}</Td>
                  <Td className="text-text-2">{m.reference_type}</Td>
                  <Td className="text-xs text-text-2">{m.created_by_email}</Td>
                </tr>
              ))}
            </TBody>
          </Table>
        )}
      </div>
    </div>
  );
}
