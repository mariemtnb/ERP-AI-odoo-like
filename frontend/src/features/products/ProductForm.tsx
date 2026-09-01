import { useState, type FormEvent } from "react";
import { useQuery } from "@tanstack/react-query";
import { listCategories } from "@/api/catalog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useI18n } from "@/lib/i18n";
import type { Product } from "@/types";

export interface ProductFormValues {
  sku: string;
  name: string;
  category: number | null;
  unit: string;
  cost_price: string;
  sale_price: string;
  min_stock_level: string;
  description: string;
}

export function ProductForm({
  initial,
  onSubmit,
  busy,
  error,
}: {
  initial?: Product;
  onSubmit: (values: ProductFormValues) => void;
  busy: boolean;
  error?: string;
}) {
  const { t } = useI18n();
  const { data: categories = [] } = useQuery({
    queryKey: ["categories"],
    queryFn: listCategories,
  });

  const [values, setValues] = useState<ProductFormValues>({
    sku: initial?.sku ?? "",
    name: initial?.name ?? "",
    category: initial?.category ?? null,
    unit: initial?.unit ?? "unit",
    cost_price: initial?.cost_price ?? "0",
    sale_price: initial?.sale_price ?? "0",
    min_stock_level: initial?.min_stock_level ?? "0",
    description: initial?.description ?? "",
  });

  const set = (k: keyof ProductFormValues) => (e: { target: { value: string } }) =>
    setValues((v) => ({ ...v, [k]: e.target.value }));

  function submit(e: FormEvent) {
    e.preventDefault();
    onSubmit(values);
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="sku">{t("products.sku")}</Label>
          <Input id="sku" value={values.sku} onChange={set("sku")} required />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="name">{t("field.name")}</Label>
          <Input id="name" value={values.name} onChange={set("name")} required />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="category">{t("products.category")}</Label>
          <Select
            id="category"
            value={values.category ?? ""}
            onChange={(e) =>
              setValues((v) => ({
                ...v,
                category: e.target.value ? Number(e.target.value) : null,
              }))
            }
          >
            <option value="">{t("products.none")}</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </Select>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="unit">{t("products.unit")}</Label>
          <Input id="unit" value={values.unit} onChange={set("unit")} />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="cost_price">{t("products.costPrice")}</Label>
          <Input
            id="cost_price"
            type="number"
            step="0.01"
            min="0"
            value={values.cost_price}
            onChange={set("cost_price")}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="sale_price">{t("products.salePrice")}</Label>
          <Input
            id="sale_price"
            type="number"
            step="0.01"
            min="0"
            value={values.sale_price}
            onChange={set("sale_price")}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="min_stock_level">{t("products.minStock")}</Label>
          <Input
            id="min_stock_level"
            type="number"
            step="0.001"
            min="0"
            value={values.min_stock_level}
            onChange={set("min_stock_level")}
          />
        </div>
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="description">{t("field.description")}</Label>
        <Input id="description" value={values.description} onChange={set("description")} />
      </div>
      {error && <p className="text-sm text-danger">{error}</p>}
      <Button type="submit" className="w-full" disabled={busy}>
        {busy ? t("common.saving") : t("products.save")}
      </Button>
    </form>
  );
}
