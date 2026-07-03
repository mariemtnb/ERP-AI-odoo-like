import { api } from "./client";
import type { Category, Paginated, Product } from "@/types";

export async function listCategories() {
  const { data } = await api.get<Paginated<Category>>("/categories/", {
    params: { page_size: 100 },
  });
  return data.results;
}

export async function createCategory(payload: Partial<Category>) {
  const { data } = await api.post<Category>("/categories/", payload);
  return data;
}

export async function deleteCategory(id: number) {
  await api.delete(`/categories/${id}/`);
}

export async function listProducts(params: Record<string, unknown> = {}) {
  const { data } = await api.get<Paginated<Product>>("/products/", { params });
  return data;
}

export async function createProduct(payload: Partial<Product>) {
  const { data } = await api.post<Product>("/products/", payload);
  return data;
}

export async function updateProduct(id: number, payload: Partial<Product>) {
  const { data } = await api.patch<Product>(`/products/${id}/`, payload);
  return data;
}

export async function deactivateProduct(id: number) {
  await api.delete(`/products/${id}/`);
}
