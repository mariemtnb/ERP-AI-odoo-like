import { useEffect, useRef, useState } from "react";
import { Globe, Check } from "lucide-react";
import { IconButton } from "@/components/ui/icon-button";
import { LANGS, useI18n } from "@/lib/i18n";

/** Topbar language picker: English / Français / العربية. */
export function LanguageSwitcher() {
  const { lang, setLang, dir, t } = useI18n();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    function onDown(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    window.addEventListener("mousedown", onDown);
    return () => window.removeEventListener("mousedown", onDown);
  }, [open]);

  return (
    <div ref={ref} style={{ position: "relative" }}>
      <IconButton size="md" onClick={() => setOpen((v) => !v)} aria-label={t("top.language")} title={t("top.language")}>
        <Globe size={18} />
      </IconButton>
      {open && (
        <div
          role="menu"
          style={{
            position: "absolute",
            top: "calc(100% + 6px)",
            [dir === "rtl" ? "left" : "right"]: 0,
            minWidth: 168,
            background: "var(--surface-card)",
            border: "1px solid var(--border)",
            borderRadius: 12,
            boxShadow: "0 18px 44px -12px rgba(0,0,0,.6)",
            padding: 6,
            zIndex: 50,
          }}
        >
          {LANGS.map((l) => {
            const active = l.code === lang;
            return (
              <button
                key={l.code}
                role="menuitemradio"
                aria-checked={active}
                onClick={() => { setLang(l.code); setOpen(false); }}
                style={{
                  display: "flex",
                  alignItems: "center",
                  gap: 10,
                  width: "100%",
                  padding: "9px 10px",
                  borderRadius: 8,
                  border: "none",
                  cursor: "pointer",
                  background: active ? "var(--surface-hover)" : "transparent",
                  color: "var(--text-body)",
                  font: "500 14px var(--font-sans)",
                  textAlign: "start",
                }}
                onMouseEnter={(e) => { if (!active) e.currentTarget.style.background = "var(--surface-hover)"; }}
                onMouseLeave={(e) => { if (!active) e.currentTarget.style.background = "transparent"; }}
              >
                <span style={{ flex: 1 }}>{l.native}</span>
                {active && <Check size={15} color="var(--emerald-400)" />}
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}
