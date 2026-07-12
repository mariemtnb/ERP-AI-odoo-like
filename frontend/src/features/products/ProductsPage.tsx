import { useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Package, Pencil, Plus, Trash2 } from "lucide-react";
import {
  createCategory,
  createProduct,
  deactivateProduct,
  deleteCategory,
  listCategories,
  listProducts,
  updateProduct,
} from "@/api/catalog";
import { Badge } from "@/components/ui/badge";
import { Tooltip } from "@/components/ui/tooltip";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { Input } from "@/components/ui/input";
import { TableSkeleton } from "@/components/ui/skeleton";
import { Table, TBody, Td, Th, THead } from "@/components/ui/table";
import { useAuth } from "@/features/auth/AuthContext";
import type { Product } from "@/types";
import { ProductForm, type ProductFormValues } from "./ProductForm";

function apiError(err: any): string {
  const data = err?.response?.data;
  if (!data) return "Request failed.";
  if (typeof data === "string") return data;
  return Object.entries(data)
    .map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(" ") : v}`)
    .join(" — ");
}

export default function ProductsPage() {
  const { user } = useAuth();
  const canWrite = user!.role !== "employee";
  const qc = useQueryClient();
  const [search, setSearch] = useState("");
  const [dialog, setDialog] = useState<"create" | Product | null>(null);
  const [error, setError] = useState("");

  const { data, isLoading } = useQuery({
    queryKey: ["products", search],
    queryFn: () => listProducts(search ? { search } : {}),
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ["products"] });
    qc.invalidateQueries({ queryKey: ["categories"] });
  };

  const saveMutation = useMutation({
    mutationFn: async (values: ProductFormValues) =>
      dialog === "create"
        ? createProduct(values as Partial<Product>)
        : updateProduct((dialog as Product).id, values as Partial<Product>),
    onSuccess: () => {
      invalidate();
      setDialog(null);
      setError("");
    },
    onError: (err) => setError(apiError(err)),
  });

  const deactivateMutation = useMutation({
    mutationFn: deactivateProduct,
    onSuccess: invalidate,
  });

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div />
        {canWrite && (
          <Button onClick={() => { setError(""); setDialog("create"); }}>
            <Plus className="h-4 w-4" /> New product
          </Button>
        )}
      </div>

      <div className="flex gap-3">
        <Input
          placeholder="Search by SKU or name…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="max-w-xs"
        />
        <CategoriesManager canWrite={canWrite} />
      </div>

      {isLoading ? (
        <TableSkeleton rows={5} />
      ) : data!.results.length === 0 ? (
        <EmptyState
          icon={Package}
          title={search ? "No products match your search" : "No products yet"}
          hint={
            search
              ? "Try a different SKU or name."
              : "Create your first product to start tracking stock and sales."
          }
          action={
            canWrite && !search ? (
              <Button onClick={() => { setError(""); setDialog("create"); }}>
                <Plus className="h-4 w-4" /> New product
              </Button>
            ) : undefined
          }
        />
      ) : (
        <Table>
          <THead>
            <tr>
              <Th>SKU</Th>
              <Th>Name</Th>
              <Th>Category</Th>
              <Th className="text-right">Stock</Th>
              <Th className="text-right">Sale price</Th>
              <Th>Status</Th>
              {canWrite && <Th />}
            </tr>
          </THead>
          <TBody>
            {data!.results.map((p) => (
              <tr key={p.id} className={!p.is_active ? "opacity-50" : undefined}>
                <Td className="font-mono text-xs">{p.sku}</Td>
                <Td>{p.name}</Td>
                <Td>{p.category_name ?? "—"}</Td>
                <Td className="text-right">
                  {Number(p.quantity_in_stock)} {p.unit}
                  {p.is_low_stock && p.is_active && (
                    <Badge tone="red" className="ml-2">low</Badge>
                  )}
                </Td>
                <Td className="text-right">{Number(p.sale_price).toFixed(2)}</Td>
                <Td>
                  <Badge tone={p.is_active ? "green" : "red"}>
                    {p.is_active ? "active" : "inactive"}
                  </Badge>
                </Td>
                {canWrite && (
                  <Td className="text-right">
                    <Tooltip label="Change this product's name, price or details">
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label="Edit product"
                        onClick={() => { setError(""); setDialog(p); }}
                      >
                        <Pencil className="h-4 w-4" />
                      </Button>
                    </Tooltip>
                    {p.is_active && (
                      <Tooltip label="Hide this product from the catalog (its history is kept)">
                        <Button
                          variant="ghost"
                          size="icon"
                          aria-label="Deactivate product"
                          onClick={() => deactivateMutation.mutate(p.id)}
                        >
                          <Trash2 className="h-4 w-4 text-danger" />
                        </Button>
                      </Tooltip>
                    )}
                  </Td>
                )}
              </tr>
            ))}
          </TBody>
        </Table>
      )}

      <Dialog
        open={dialog !== null}
        onClose={() => setDialog(null)}
        title={dialog === "create" ? "New product" : "Edit product"}
      >
        {dialog !== null && (
          <ProductForm
            initial={dialog === "create" ? undefined : dialog}
            onSubmit={(v) => saveMutation.mutate(v)}
            busy={saveMutation.isPending}
            error={error}
          />
        )}
      </Dialog>
    </div>
  );
}

function CategoriesManager({ canWrite }: { canWrite: boolean }) {
  const qc = useQueryClient();
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("");
  const [error, setError] = useState("");

  const { data: categories = [] } = useQuery({
    queryKey: ["categories"],
    queryFn: listCategories,
  });

  const addMutation = useMutation({
    mutationFn: () => createCategory({ name }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ["categories"] });
      setName("");
      setError("");
    },
    onError: (err) => setError(apiError(err)),
  });

  const removeMutation = useMutation({
    mutationFn: deleteCategory,
    onSuccess: () => qc.invalidateQueries({ queryKey: ["categories"] }),
    onError: (err) => setError(apiError(err)),
  });

  return (
    <>
      <Button variant="outline" onClick={() => setOpen(true)}>
        Categories ({categories.length})
      </Button>
      <Dialog open={open} onClose={() => setOpen(false)} title="Categories">
        <div className="space-y-4">
          {canWrite && (
            <form
              className="flex gap-2"
              onSubmit={(e) => {
                e.preventDefault();
                if (name.trim()) addMutation.mutate();
              }}
            >
              <Input
                placeholder="New category name"
                value={name}
                onChange={(e) => setName(e.target.value)}
              />
              <Button type="submit" disabled={addMutation.isPending}>
                Add
              </Button>
            </form>
          )}
          {error && <p className="text-sm text-danger">{error}</p>}
          <ul className="divide-y divide-stroke-soft">
            {categories.map((c) => (
              <li key={c.id} className="flex items-center justify-between py-2">
                <span>
                  {c.name}{" "}
                  <span className="text-xs text-text-3">
                    ({c.product_count} products)
                  </span>
                </span>
                {canWrite && c.product_count === 0 && (
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={() => removeMutation.mutate(c.id)}
                  >
                    <Trash2 className="h-4 w-4 text-danger" />
                  </Button>
                )}
              </li>
            ))}
          </ul>
        </div>
      </Dialog>
    </>
  );
}
