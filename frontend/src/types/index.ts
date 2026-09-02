export type Role = "super_admin" | "admin" | "manager" | "employee";

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
  received_qty?: string;
  remaining?: string;
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

/* ─────────────── Tunisia localization ─────────────── */

export type FiscalRegime = "reel" | "forfaitaire" | "export" | "exempt";

export interface CompanyProfile {
  id: number;
  legal_name: string;
  trade_name: string;
  address: string;
  city: string;
  postal_code: string;
  country: string;
  phone: string;
  email: string;
  tax_id: string;
  vat_code: string;
  category_code: string;
  establishment_code: string;
  full_tax_id: string;
  trade_register: string;
  customs_code: string;
  fiscal_regime: FiscalRegime;
  vat_registered: boolean;
  default_vat_rate: string;
  withholding_rate: string;
  stamp_duty_amount: string;
  stamp_duty_enabled: boolean;
  fiscal_year_start_month: number;
  currency: string;
  currency_decimals: number;
  locale: "fr" | "ar" | "en";
  invoice_number_format: string;
  default_payment_terms_days: number;
  late_payment_grace_days: number;
  enforce_legal_validation: boolean;
  warnings?: string[];
}

export interface Journal {
  id: number;
  code: string;
  name: string;
  name_fr: string;
  type: string;
  is_active: boolean;
}

export interface AccountMapping {
  id: number;
  key: string;
  account_code: string;
  account_name: string | null;
  account_exists: boolean;
  label: string;
  description: string;
}

export interface Bank {
  id: number;
  code: string;
  name: string;
  short_name: string;
  swift: string;
  country: string;
  is_active: boolean;
  account_count: number;
}

export interface BankAccount {
  id: number;
  bank_id: number;
  bank_name: string | null;
  label: string;
  branch: string;
  rib: string;
  iban: string;
  account_number: string;
  currency: string;
  gl_account_id: number | null;
  gl_account_code: string | null;
  opening_balance: string;
  opening_date: string | null;
  current_balance: string;
  last_reconciled_at: string | null;
  is_default: boolean;
  is_active: boolean;
  warnings?: string[];
}

export type BankTxStatus =
  | "unmatched"
  | "partially_matched"
  | "matched"
  | "disputed"
  | "ignored";

export interface ReconciliationMatch {
  id: number;
  bank_transaction_id: number;
  matchable_type: string;
  matchable_id: number | null;
  matched_label: string;
  amount: string;
  journal_entry_id: number | null;
  journal_entry_number: string | null;
  note: string;
  created_by_email: string | null;
  created_at: string;
}

export interface BankTransaction {
  id: number;
  bank_account_id: number;
  bank_account_label: string | null;
  operation_date: string;
  value_date: string | null;
  label: string;
  reference: string;
  amount: string;
  direction: "credit" | "debit";
  running_balance: string | null;
  status: BankTxStatus;
  matched_amount: string;
  remaining_amount: string;
  source: string;
  import_batch: string;
  notes: string;
  created_at: string;
  matches?: ReconciliationMatch[];
}

export interface MatchSuggestion {
  type: string;
  id: number;
  label: string;
  amount: string;
  date: string | null;
  score: number;
}

export type InstrumentKind = "cheque" | "traite";
export type InstrumentDirection = "incoming" | "outgoing";
export type InstrumentStatus =
  | "draft"
  | "issued"
  | "received"
  | "deposited"
  | "pending_clearance"
  | "cleared"
  | "bounced"
  | "cancelled"
  | "settled";

export interface InstrumentEvent {
  id: number;
  event: string;
  from_status: string;
  to_status: string;
  amount: string;
  journal_entry_id: number | null;
  journal_entry_number: string | null;
  notes: string;
  created_by_email: string | null;
  created_at: string;
}

export interface Attachment {
  id: number;
  owner_type: string;
  owner_id: number;
  filename: string;
  mime: string;
  size: number;
  download_url: string;
  uploaded_by_email: string | null;
  created_at: string;
}

export interface PaymentInstrument {
  id: number;
  number: string;
  kind: InstrumentKind;
  direction: InstrumentDirection;
  instrument_reference: string;
  amount: string;
  issue_date: string;
  due_date: string | null;
  place_of_issue: string;
  customer_id: number | null;
  supplier_id: number | null;
  counterparty_name: string;
  status: InstrumentStatus;
  is_overdue: boolean;
  bank_account_id: number | null;
  bank_account_label: string | null;
  drawee_bank_id: number | null;
  drawee_bank_name: string | null;
  drawee_rib: string;
  reference_type: string;
  reference_id: number | null;
  deposited_at: string | null;
  cleared_at: string | null;
  bounced_at: string | null;
  bounce_reason: string;
  bank_fees: string;
  notes: string;
  created_at: string;
  events?: InstrumentEvent[];
  attachments?: Attachment[];
}

export interface InstrumentSummary {
  outstanding_incoming_count: number;
  outstanding_incoming_amount: number;
  outstanding_outgoing_count: number;
  outstanding_outgoing_amount: number;
  overdue_count: number;
  overdue_amount: number;
  bounced_count: number;
  bounced_amount: number;
}

export type InstallmentStatus =
  | "pending"
  | "partially_paid"
  | "paid"
  | "overdue"
  | "cancelled";

export interface Installment {
  id: number;
  plan_id: number;
  sequence: number;
  due_date: string;
  amount: string;
  paid_amount: string;
  remaining_amount: string;
  status: InstallmentStatus;
  is_overdue: boolean;
  days_late: number;
  payment_method: string;
  paid_at: string | null;
  is_down_payment: boolean;
  notes: string;
  plan_number?: string;
  customer_name?: string | null;
}

export interface InstallmentPlan {
  id: number;
  number: string;
  reference_type: string;
  reference_id: number;
  customer_id: number | null;
  customer_name: string | null;
  supplier_id: number | null;
  supplier_name: string | null;
  total_amount: string;
  down_payment: string;
  paid_amount: string;
  remaining_amount: string;
  installment_count: number;
  frequency: string;
  start_date: string;
  status: "active" | "completed" | "cancelled" | "defaulted";
  notes: string;
  created_at: string;
  overdue_amount?: string;
  next_due_date?: string | null;
  installments?: Installment[];
}

export type PaymentMethod =
  | "cash"
  | "bank_transfer"
  | "cheque"
  | "traite"
  | "card"
  | "bank_deposit"
  | "bank_withdrawal";

export interface Payment {
  id: number;
  number: string;
  direction: "inbound" | "outbound";
  method: PaymentMethod;
  amount: string;
  payment_date: string;
  customer_id: number | null;
  customer_name: string | null;
  supplier_id: number | null;
  supplier_name: string | null;
  bank_account_id: number | null;
  bank_account_label: string | null;
  instrument_id: number | null;
  instrument_number: string | null;
  installment_id: number | null;
  reference_type: string;
  reference_id: number | null;
  is_advance: boolean;
  journal_entry_id: number | null;
  journal_entry_number: string | null;
  reference: string;
  notes: string;
  created_at: string;
}

export interface CustomerCredit {
  customer_id: number;
  plan_count: number;
  active_plan_count: number;
  total_financed: number;
  total_paid: number;
  outstanding_amount: number;
  overdue_amount: number;
  instruments_pending_count: number;
  instruments_pending_amount: number;
  bounced_count: number;
  bounced_amount: number;
  has_arrears: boolean;
  plans: InstallmentPlan[];
}

export interface ReconciliationReport {
  title: string;
  bank_account: BankAccount;
  date_from: string | null;
  date_to: string | null;
  opening_balance: string;
  statement_movement: string;
  statement_balance: string;
  book_balance: string;
  difference: string;
  counts: {
    total: number;
    matched: number;
    partially_matched: number;
    unmatched: number;
    disputed: number;
  };
  amounts: {
    matched: number;
    unmatched: number;
    partially_matched: number;
    disputed: number;
  };
  instruments_in_transit: {
    count: number;
    amount: number;
    items: {
      number: string;
      kind: string;
      amount: string;
      due_date: string | null;
      status: string;
    }[];
  };
  open_items: BankTransaction[];
}

export interface TreasuryDashboard {
  date_from: string | null;
  date_to: string | null;
  instruments: InstrumentSummary;
  reconciliation: {
    pending_count: number;
    pending_amount: number;
    disputed_count: number;
  };
  collections: {
    cash_collected: number;
    bank_collected: number;
    total_collected: number;
  };
  installments: {
    active_plans: number;
    overdue_count: number;
    overdue_amount: number;
    due_next_30_days_count: number;
    due_next_30_days_amount: number;
  };
  bank_accounts: {
    id: number;
    label: string;
    bank_name: string | null;
    currency: string;
    current_balance: string;
    last_reconciled_at: string | null;
  }[];
}
