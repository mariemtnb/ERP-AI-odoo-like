/**
 * Tunisian business vocabulary.
 *
 * Tunisian SMEs run their books in French, but the everyday spoken terms are
 * Arabic/Derja — a "traite" is a *kembya*, paying in instalments is *khlas bel
 * taqsit*. Showing the French term with the familiar one underneath is what
 * makes these screens readable to the person actually doing the work.
 *
 * `en` stays the interface language; `fr` and `local` are shown as helper text.
 */

export interface TnLabel {
  en: string;
  fr: string;
  /** The term used in conversation, transliterated. */
  local?: string;
}

export const INSTRUMENT_KIND: Record<string, TnLabel> = {
  cheque: { en: "Cheque", fr: "Chèque", local: "Chèque / صك" },
  traite: { en: "Commercial paper", fr: "Traite / Effet de commerce", local: "Kembya / كمبيالة" },
};

export const INSTRUMENT_STATUS: Record<string, TnLabel> = {
  draft: { en: "Draft", fr: "Brouillon" },
  issued: { en: "Issued", fr: "Émis" },
  received: { en: "Received", fr: "Reçu" },
  deposited: { en: "Deposited", fr: "Remis à l'encaissement" },
  pending_clearance: { en: "Pending clearance", fr: "En cours d'encaissement" },
  cleared: { en: "Cleared", fr: "Encaissé" },
  bounced: { en: "Bounced", fr: "Impayé / Sans provision" },
  cancelled: { en: "Cancelled", fr: "Annulé" },
  settled: { en: "Settled", fr: "Régularisé" },
};

export const INSTALLMENT_STATUS: Record<string, TnLabel> = {
  pending: { en: "Pending", fr: "À échoir" },
  partially_paid: { en: "Partly paid", fr: "Partiellement réglée" },
  paid: { en: "Paid", fr: "Réglée" },
  overdue: { en: "Overdue", fr: "En retard" },
  cancelled: { en: "Cancelled", fr: "Annulée" },
};

export const PLAN_STATUS: Record<string, TnLabel> = {
  active: { en: "Active", fr: "En cours" },
  completed: { en: "Completed", fr: "Soldé" },
  cancelled: { en: "Cancelled", fr: "Annulé" },
  defaulted: { en: "Defaulted", fr: "En défaut" },
};

export const PAYMENT_METHOD: Record<string, TnLabel> = {
  cash: { en: "Cash", fr: "Espèces", local: "Cash / كاش" },
  bank_transfer: { en: "Bank transfer", fr: "Virement bancaire" },
  cheque: { en: "Cheque", fr: "Chèque" },
  traite: { en: "Commercial paper", fr: "Traite", local: "Kembya" },
  card: { en: "Card", fr: "Carte bancaire" },
  bank_deposit: { en: "Cash deposit", fr: "Versement en banque" },
  bank_withdrawal: { en: "Bank withdrawal", fr: "Retrait bancaire" },
};

export const BANK_TX_STATUS: Record<string, TnLabel> = {
  unmatched: { en: "Unmatched", fr: "Non rapproché" },
  partially_matched: { en: "Partly matched", fr: "Partiellement rapproché" },
  matched: { en: "Matched", fr: "Rapproché" },
  disputed: { en: "Disputed", fr: "En litige" },
  ignored: { en: "Ignored", fr: "Ignoré" },
};

export const FISCAL_REGIME: Record<string, TnLabel> = {
  reel: { en: "Standard regime", fr: "Régime réel" },
  forfaitaire: { en: "Flat-rate regime", fr: "Régime forfaitaire" },
  export: { en: "Exporting company", fr: "Entreprise exportatrice" },
  exempt: { en: "Exempt", fr: "Exonéré" },
};

/** Badge tone per status, reusing the design system's palette. */
export const STATUS_TONE: Record<string, string> = {
  draft: "employee",
  issued: "manager",
  received: "manager",
  deposited: "admin",
  pending_clearance: "admin",
  cleared: "green",
  bounced: "red",
  cancelled: "employee",
  settled: "green",
  pending: "employee",
  partially_paid: "manager",
  paid: "green",
  overdue: "red",
  active: "manager",
  completed: "green",
  defaulted: "red",
  unmatched: "employee",
  partially_matched: "manager",
  matched: "green",
  disputed: "red",
};

export function label(map: Record<string, TnLabel>, key: string): string {
  return map[key]?.en ?? key.replace(/_/g, " ");
}

/** "Émis" or "Traite / Effet de commerce · Kembya" — for tooltips and hints. */
export function frLabel(map: Record<string, TnLabel>, key: string): string {
  const entry = map[key];
  if (!entry) return "";
  return entry.local ? `${entry.fr} · ${entry.local}` : entry.fr;
}

/** Format an amount the Tunisian way: 3 decimals, space thousands separator. */
export function formatTnd(value: string | number, currency = "TND", decimals = 3): string {
  const n = typeof value === "string" ? parseFloat(value) : value;
  if (Number.isNaN(n)) return "—";
  return `${n.toLocaleString("fr-TN", {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })} ${currency}`;
}
