import { type ReactNode } from "react";
import { BrandMark } from "@/components/BrandMark";

/**
 * Split-screen auth layout — port of ui_kits/erp-app Login().
 * Left: emerald-wash hero with wordmark, headline, value prop, live-model
 * footer. Right: the form column (children).
 */
export function AuthShell({ children }: { children: ReactNode }) {
  return (
    <div
      className="grid min-h-dvh lg:grid-cols-2"
      style={{ background: "var(--bg-app)" }}
    >
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
            The ERP that thinks alongside you.
          </h1>
          <p
            style={{
              margin: "20px 0 0",
              font: "400 16px/1.6 var(--font-sans)",
              color: "var(--text-muted)",
              maxWidth: 400,
            }}
          >
            Inventory, sales, purchasing and CRM — with a conversational AI agent
            that queries your data and acts, with your approval.
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
          Local model online · your data never leaves your servers
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
