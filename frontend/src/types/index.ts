export type Role = "admin" | "manager" | "employee";

export interface User {
  id: number;
  email: string;
  first_name: string;
  last_name: string;
  role: Role;
  is_active: boolean;
}

export interface Category {
  id: number;
  name: string;
  description: string;
  product_count: number;
}

export interface Product {
  id: number;
  sku: string;
  name: string;
  category: number | null;
  category_name: string | null;
  description: string;
  cost_price: string;
  sale_price: string;
  unit: string;
  quantity_in_stock: string;
  min_stock_level: string;
  is_low_stock: boolean;
  is_active: boolean;
}

export interface StockMovement {
  id: number;
  product: number;
  product_sku: string;
  product_name: string;
  movement_type: "in" | "out" | "adjustment";
  quantity: string;
  reason: string;
  reference_type: string;
  reference_id: number | null;
  created_by_email: string;
  created_at: string;
}

export interface Partner {
  id: number;
  name: string;
  email: string;
  phone: string;
  address: string;
  notes: string;
  is_active: boolean;
  created_at: string;
}

export interface DocLine {
  id?: number;
  product: number;
  product_sku?: string;
  product_name?: string;
  quantity: string;
  unit_price: string;
  subtotal?: string;
}

export interface BusinessDoc {
  id: number;
  number: string;
  status: string;
  total_amount: string;
  created_by_email: string;
  lines: DocLine[];
  created_at: string;
  // purchases
  supplier?: number;
  supplier_name?: string;
  order_date?: string;
  received_date?: string | null;
  // sales
  customer?: number;
  customer_name?: string;
  sale_date?: string;
}

export interface Paginated<T> {
  count: number;
  next: string | null;
  previous: string | null;
  results: T[];
}
