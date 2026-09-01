import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from "react";
import { STRINGS, type Lang } from "./translations";

export type { Lang } from "./translations";

/** The languages offered, in switcher order. */
export const LANGS: { code: Lang; label: string; native: string }[] = [
  { code: "en", label: "English", native: "English" },
  { code: "fr", label: "French", native: "Français" },
  { code: "ar", label: "Arabic", native: "العربية" },
];

const RTL_LANGS: Lang[] = ["ar"];
const STORAGE_KEY = "erp-lang";

function isLang(v: unknown): v is Lang {
  return v === "en" || v === "fr" || v === "ar";
}

function readStored(): Lang | null {
  try {
    const v = localStorage.getItem(STORAGE_KEY);
    return isLang(v) ? v : null;
  } catch {
    return null;
  }
}

/** Stored choice wins; otherwise a French browser gets French, else English. */
function resolveInitial(): Lang {
  const stored = readStored();
  if (stored) return stored;
  const nav = typeof navigator !== "undefined" ? navigator.language.toLowerCase() : "en";
  if (nav.startsWith("fr")) return "fr";
  if (nav.startsWith("ar")) return "ar";
  return "en";
}

export function dirFor(lang: Lang): "rtl" | "ltr" {
  return RTL_LANGS.includes(lang) ? "rtl" : "ltr";
}

/** Reflect the language on <html> so `dir`/`lang` drive global RTL + a11y. */
function apply(lang: Lang) {
  if (typeof document === "undefined") return;
  document.documentElement.lang = lang;
  document.documentElement.dir = dirFor(lang);
  try {
    localStorage.setItem(STORAGE_KEY, lang);
  } catch {
    /* storage blocked — the choice still applies for this session */
  }
}

const INITIAL: Lang = resolveInitial();
apply(INITIAL);

/** Resolve a dotted key ("nav.dashboard") against a language, with fallbacks. */
function lookup(lang: Lang, key: string): string {
  return STRINGS[lang]?.[key] ?? STRINGS.en[key] ?? key;
}

interface I18n {
  lang: Lang;
  dir: "rtl" | "ltr";
  setLang: (l: Lang) => void;
  /** Translate a dotted key; unknown keys fall back to English then the key. */
  t: (key: string) => string;
}

const I18nContext = createContext<I18n | null>(null);

export function I18nProvider({ children }: { children: ReactNode }) {
  const [lang, setLangState] = useState<Lang>(INITIAL);

  const setLang = useCallback((l: Lang) => {
    setLangState(l);
    apply(l);
  }, []);

  const value = useMemo<I18n>(
    () => ({ lang, dir: dirFor(lang), setLang, t: (key: string) => lookup(lang, key) }),
    [lang, setLang]
  );

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n(): I18n {
  const ctx = useContext(I18nContext);
  if (!ctx) throw new Error("useI18n must be used within I18nProvider");
  return ctx;
}
