import { Hexagon } from "lucide-react";
import { cn } from "@/lib/utils";

/**
 * IntelligentERP brand mark — emerald hexagon tile + wordmark.
 * Matches ui_kits/erp-app Brand(). `size="sm"` for the sidebar/topbar.
 */
export function BrandMark({
  size = "md",
  tileOnly = false,
  className,
}: {
  size?: "sm" | "md";
  tileOnly?: boolean;
  className?: string;
}) {
  const sm = size === "sm";
  return (
    <div className={cn("flex items-center gap-2.5", className)}>
      <div
        className="grid place-items-center rounded-[10px] text-[var(--text-on-accent)] shadow-[var(--shadow-accent)]"
        style={{
          width: sm ? 30 : 36,
          height: sm ? 30 : 36,
          background: "var(--emerald-500)",
        }}
      >
        <Hexagon size={sm ? 17 : 20} strokeWidth={1.9} />
      </div>
      {!tileOnly && (
        <span
          className="font-semibold tracking-[-0.02em]"
          style={{ fontSize: 17, color: "var(--text-strong)" }}
        >
          Intelligent<span style={{ color: "var(--emerald-400)" }}>ERP</span>
        </span>
      )}
    </div>
  );
}
