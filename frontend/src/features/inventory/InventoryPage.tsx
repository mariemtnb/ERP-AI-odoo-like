import { useState, type FormEvent } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createMovement, listMovements } from "@/api/inventory";
import { listProducts } from "@/api/catalog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { useAuth } from "@/features/auth/AuthContext";

const typeTone: Record<string, string> = { in: "green", out: "red", adjustment: "manager" };

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

  const [form, setForm] = useState({ product: "", movement_type: "in", quantity: "", reason: "" });
  const [error, setError] = useState("");

  const mutation = useMutation({
    mutationFn: () =>
      createMovement({
        product: Number(form.product),
        movement_type: form.movement_type,
        quantity: form.quantity,
        reason: form.reason,
      }),
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
      <h1 className="text-2xl font-bold">Inventory</h1>

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
                      <option value="in">Stock in</option>
                      <option value="out">Stock out</option>
                      <option value="adjustment">Adjustment (±)</option>
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
                  <Label htmlFor="reason">Reason</Label>
                  <Input
                    id="reason"
                    value={form.reason}
                    onChange={(e) => setForm((f) => ({ ...f, reason: e.target.value }))}
                    placeholder="e.g. initial stock, damage, recount…"
                  />
                </div>
                {error && <p className="text-sm text-red-400">{error}</p>}
                <Button type="submit" className="w-full" disabled={mutation.isPending}>
                  {mutation.isPending ? "Saving…" : "Record"}
                </Button>
              </form>
            </CardContent>
          </Card>
        )}

        <Card className={canWrite ? "lg:col-span-2" : "lg:col-span-3"}>
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
              <p className="text-sm text-slate-400">No products below their minimum level.</p>
            ) : (
              <ul className="divide-y divide-slate-800 text-sm">
                {lowStock.results.map((p) => (
                  <li key={p.id} className="flex justify-between py-2">
                    <span>
                      <span className="font-mono text-xs text-slate-400">{p.sku}</span>{" "}
                      {p.name}
                    </span>
                    <span className="text-red-400">
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
          <p className="text-slate-400">Loading…</p>
        ) : (
          <Table>
            <THead>
              <tr>
                <Th>Date</Th>
                <Th>Product</Th>
                <Th>Type</Th>
                <Th className="text-right">Qty</Th>
                <Th>Reason</Th>
                <Th>Source</Th>
                <Th>By</Th>
              </tr>
            </THead>
            <TBody>
              {movements!.results.map((m) => (
                <tr key={m.id}>
                  <Td className="whitespace-nowrap text-xs text-slate-400">
                    {new Date(m.created_at).toLocaleString()}
                  </Td>
                  <Td>
                    <span className="font-mono text-xs text-slate-400">{m.product_sku}</span>{" "}
                    {m.product_name}
                  </Td>
                  <Td>
                    <Badge tone={typeTone[m.movement_type]}>{m.movement_type}</Badge>
                  </Td>
                  <Td className="text-right">{Number(m.quantity)}</Td>
                  <Td className="text-slate-400">{m.reason || "—"}</Td>
                  <Td className="text-slate-400">{m.reference_type}</Td>
                  <Td className="text-xs text-slate-400">{m.created_by_email}</Td>
                </tr>
              ))}
            </TBody>
          </Table>
        )}
      </div>
    </div>
  );
}
