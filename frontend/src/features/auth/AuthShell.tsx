import { type ReactNode } from "react";
import { BrandMark } from "@/components/BrandMark";
import { LanguageSwitcher } from "@/components/LanguageSwitcher";
import { useI18n } from "@/lib/i18n";

/**
 * Split-screen auth layout - port of ui_kits/erp-app Login().
 * Left: emerald-wash hero with wordmark, headline, value prop, live-model
 * footer. Right: the form column (children).
 */
export function AuthShell({ children }: { children: ReactNode }) {
  const { t } = useI18n();
  return (
    <div
      className="relative grid min-h-dvh lg:grid-cols-2"
      style={{ background: "var(--bg-app)" }}
    >
      {/* Language picker, reachable before signing in */}
      <div style={{ position: "absolute", top: 16, insetInlineEnd: 16, zIndex: 10 }}>
        <LanguageSwitcher />
      </div>
      {/* Left hero */}
      <div
        className="relative hidden flex-col justify-between overflow-hidden p-14 lg:flex"
        style={{
          borderRight: "1px solid var(--border-subtle)",
          background:
            "radial-gradient(120% 100% at 0% 0%, color-mix(in oklab, var(--emerald-500) 14%, var(--bg-app)), var(--bg-app) 60%)",
        }}
      >
        <BrandMark />
        <div>
          <h1
            className="font-semibold"
            style={{
              margin: 0,
              font: "600 44px/1.05 var(--font-sans)",
              letterSpacing: "-0.035em",
              color: "var(--text-strong)",
              maxWidth: 440,
            }}
          >
            {t("auth.heroTitle")}
          </h1>
          <p
            style={{
              margin: "20px 0 0",
              font: "400 16px/1.6 var(--font-sans)",
              color: "var(--text-muted)",
              maxWidth: 400,
            }}
          >
            {t("auth.heroSub")}
          </p>
        </div>
        <div
          className="flex items-center gap-2.5"
          style={{ font: "400 13px/1 var(--font-sans)", color: "var(--text-faint)" }}
        >
          <span
            className="rounded-full"
            style={{ width: 7, height: 7, background: "var(--emerald-400)" }}
          />
          {t("auth.heroFooter")}
        </div>
      </div>

      {/* Right form column */}
      <div className="grid place-items-center p-6 sm:p-10">
        <div className="w-full" style={{ maxWidth: 360 }}>
          {/* Brand shows on small screens where the hero is hidden */}
          <div className="mb-8 lg:hidden">
            <BrandMark />
          </div>
          {children}
        </div>
      </div>
    </div>
  );
}
