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
  warehouse: number | null;
  warehouse_name: string | null;
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
  approved_by_email?: string | null;
}

export interface Warehouse {
  id: number;
  name: string;
  address: string;
  is_default: boolean;
  is_active: boolean;
}

export type LeadStatus = "new" | "contacted" | "qualified" | "won" | "lost";

export interface LeadActivity {
  id: number;
  type: "call" | "email" | "meeting" | "note";
  summary: string;
  created_by_email: string;
  created_at: string;
}

export interface Lead {
  id: number;
  name: string;
  company: string;
  email: string;
  phone: string;
  source: string;
  status: LeadStatus;
  notes: string;
  assigned_to: number | null;
  assigned_to_email: string | null;
  customer_id: number | null;
  created_at: string;
  activities?: LeadActivity[];
}

export type AccountType = "asset" | "liability" | "equity" | "income" | "expense";

export interface Account {
  id: number;
  code: string;
  name: string;
  type: AccountType;
  is_active: boolean;
}

export interface JournalEntryLine {
  id: number;
  account_id: number;
  account_code: string;
  account_name: string;
  label: string;
  debit: string;
  credit: string;
}

export interface JournalEntry {
  id: number;
  number: string;
  entry_date: string;
  memo: string;
  reference_type: string;
  reference_id: number | null;
  created_by_email: string | null;
  total: string;
  lines: JournalEntryLine[];
  created_at: string;
}

export interface TrialBalanceRow {
  code: string;
  name: string;
  type: AccountType;
  debit: number;
  credit: number;
  balance: number;
}

export interface TrialBalance {
  title: string;
  date_from: string | null;
  date_to: string | null;
  rows: TrialBalanceRow[];
  total_debit: number;
  total_credit: number;
}

export interface IncomeStatement {
  title: string;
  date_from: string | null;
  date_to: string | null;
  income: TrialBalanceRow[];
  expenses: TrialBalanceRow[];
  total_income: number;
  total_expenses: number;
  net_profit: number;
}

export interface Paginated<T> {
  count: number;
  next: string | null;
  previous: string | null;
  results: T[];
}
