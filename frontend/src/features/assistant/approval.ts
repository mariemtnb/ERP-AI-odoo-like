/**
 * Turns a raw agent write-action ({action, details}) into a plain-language
 * confirmation a non-technical person can read, in the language the user wrote
 * in (Tunisian Arabic / French / English).
 */
export type Lang = "ar" | "fr" | "en";

const ARABIC = /[؀-ۿ]/;
const FRENCH =
  /[àâçéèêëîïôûùü]|\b(le|la|les|un|une|des|de|du|ajoute|ajouter|cr[ée]{1,2}r?|nouveau|client|vendre|vente|ch[èe]que|facture|acheter|ach[èe]te|traite|fournisseur|produit|payer|paiement|remise|stock)\b/i;

export function detectLang(text: string | undefined): Lang {
  if (!text) return "en";
  if (ARABIC.test(text)) return "ar";
  if (FRENCH.test(text)) return "fr";
  return "en";
}

export const t = {
  approvalNeeded: { ar: "مطلوب موافقة", fr: "Confirmation requise", en: "Confirmation needed" },
  approve: { ar: "موافقة", fr: "Confirmer", en: "Approve" },
  reject: { ar: "رفض", fr: "Refuser", en: "Reject" },
  question: {
    ar: "هل تريد أن يقوم المساعد بما يلي؟",
    fr: "Voulez-vous que l'assistant fasse ceci ?",
    en: "Do you want the assistant to do this?",
  },
} as const;

// Plain-language title for each write action.
const TITLES: Record<string, Record<Lang, string>> = {
  create_customer: { ar: "إضافة زبون جديد", fr: "Ajouter un nouveau client", en: "Add a new customer" },
  update_customer: { ar: "تعديل بيانات زبون", fr: "Modifier un client", en: "Update a customer" },
  create_supplier: { ar: "إضافة مورّد جديد", fr: "Ajouter un nouveau fournisseur", en: "Add a new supplier" },
  create_product: { ar: "إضافة منتج جديد", fr: "Ajouter un nouveau produit", en: "Add a new product" },
  update_stock: { ar: "تعديل المخزون", fr: "Ajuster le stock", en: "Adjust stock" },
  transfer_stock: { ar: "تحويل مخزون بين المستودعات", fr: "Transférer du stock", en: "Transfer stock" },
  create_purchase_order: { ar: "إنشاء طلب شراء", fr: "Créer un bon de commande", en: "Create a purchase order" },
  create_sale: { ar: "تسجيل عملية بيع", fr: "Enregistrer une vente", en: "Record a sale" },
  confirm_sale: { ar: "تأكيد عملية بيع", fr: "Confirmer une vente", en: "Confirm a sale" },
  generate_invoice: { ar: "إصدار فاتورة", fr: "Générer une facture", en: "Generate an invoice" },
  create_lead: { ar: "إضافة فرصة بيع", fr: "Ajouter un prospect", en: "Add a sales lead" },
  register_instrument: { ar: "تسجيل شيك أو كمبيالة", fr: "Enregistrer un chèque / une traite", en: "Register a cheque / traite" },
  deposit_instrument: { ar: "إيداع شيك للتحصيل", fr: "Remettre un chèque à l'encaissement", en: "Deposit a cheque for collection" },
  clear_instrument: { ar: "تحصيل شيك", fr: "Encaisser un chèque", en: "Mark a cheque as cleared" },
  bounce_instrument: { ar: "تسجيل شيك بدون رصيد", fr: "Enregistrer un chèque impayé", en: "Mark a cheque as bounced" },
  record_payment: { ar: "تسجيل دفعة", fr: "Enregistrer un paiement", en: "Record a payment" },
  create_installment_plan: { ar: "إنشاء تقسيط (خلاص بالتقسيط)", fr: "Créer un paiement par facilités", en: "Set up an instalment plan" },
  settle_installment: { ar: "تسديد قسط", fr: "Régler une échéance", en: "Settle an instalment" },
  reconcile_bank_transaction: { ar: "تسوية حركة بنكية", fr: "Rapprocher une opération bancaire", en: "Reconcile a bank line" },
};

// Friendly field labels.
const LABELS: Record<string, Record<Lang, string>> = {
  name: { ar: "الاسم", fr: "Nom", en: "Name" },
  email: { ar: "البريد الإلكتروني", fr: "E-mail", en: "Email" },
  phone: { ar: "الهاتف", fr: "Téléphone", en: "Phone" },
  address: { ar: "العنوان", fr: "Adresse", en: "Address" },
  sku: { ar: "المرجع", fr: "Référence", en: "SKU" },
  sale_price: { ar: "سعر البيع", fr: "Prix de vente", en: "Sale price" },
  cost_price: { ar: "سعر التكلفة", fr: "Prix d'achat", en: "Cost price" },
  min_stock_level: { ar: "الحد الأدنى للمخزون", fr: "Stock minimum", en: "Min stock" },
  quantity: { ar: "الكمية", fr: "Quantité", en: "Quantity" },
  movement_type: { ar: "نوع الحركة", fr: "Type de mouvement", en: "Movement" },
  reason: { ar: "السبب", fr: "Motif", en: "Reason" },
  customer: { ar: "الزبون", fr: "Client", en: "Customer" },
  customer_id: { ar: "الزبون", fr: "Client", en: "Customer" },
  supplier: { ar: "المورّد", fr: "Fournisseur", en: "Supplier" },
  supplier_id: { ar: "المورّد", fr: "Fournisseur", en: "Supplier" },
  sale_date: { ar: "التاريخ", fr: "Date", en: "Date" },
  sale_id: { ar: "رقم البيع", fr: "N° de vente", en: "Sale #" },
  amount: { ar: "المبلغ", fr: "Montant", en: "Amount" },
  due_date: { ar: "تاريخ الاستحقاق", fr: "Échéance", en: "Due date" },
  type: { ar: "النوع", fr: "Type", en: "Type" },
  method: { ar: "طريقة الدفع", fr: "Moyen", en: "Method" },
  product: { ar: "المنتج", fr: "Produit", en: "Product" },
  unit_price: { ar: "سعر الوحدة", fr: "Prix unitaire", en: "Unit price" },
};

const productWord: Record<Lang, string> = { ar: "منتج", fr: "produit", en: "product" };
const eachWord: Record<Lang, string> = { ar: "للوحدة", fr: "l'unité", en: "each" };
const linesWord: Record<Lang, string> = { ar: "العناصر", fr: "Articles", en: "Items" };

export interface FriendlyAction {
  title: string;
  rows: { label: string; value: string }[];
}

function label(key: string, lang: Lang): string {
  return LABELS[key]?.[lang] ?? key.replaceAll("_", " ");
}

function formatValue(key: string, value: unknown, lang: Lang): string {
  if (value === null || value === undefined || value === "") return "-";
  if (key === "lines" && Array.isArray(value)) {
    return value
      .map((l: any) => `${l.quantity} × ${productWord[lang]} #${l.product} @ ${l.unit_price} ${eachWord[lang]}`)
      .join(" · ");
  }
  if (typeof value === "object") return JSON.stringify(value);
  return String(value);
}

export function describeAction(action: string, details: Record<string, unknown>, lang: Lang): FriendlyAction {
  const title = TITLES[action]?.[lang] ?? action.replaceAll("_", " ");
  const rows: { label: string; value: string }[] = [];
  for (const [key, value] of Object.entries(details ?? {})) {
    if (value === "" || value === null || value === undefined) continue; // skip empty optionals
    const l = key === "lines" ? linesWord[lang] : label(key, lang);
    rows.push({ label: l, value: formatValue(key, value, lang) });
  }
  return { title, rows };
}
