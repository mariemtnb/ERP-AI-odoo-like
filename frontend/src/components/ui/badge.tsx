import { type HTMLAttributes } from "react";
import { cn } from "@/lib/utils";

/* Soft-tint badges - design tones (neutral/emerald/amber/rose/sky/violet)
   plus legacy aliases kept so existing screens keep working. */
const TONES: Record<string, { bg: string; fg: string }> = {
  neutral: { bg: "var(--surface-hover)", fg: "var(--text-body)" },
  emerald: { bg: "var(--emerald-glow)", fg: "var(--emerald-400)" },
  amber: { bg: "var(--amber-glow)", fg: "var(--amber-400)" },
  rose: { bg: "var(--rose-glow)", fg: "var(--rose-400)" },
  sky: { bg: "var(--sky-glow)", fg: "var(--sky-400)" },
  violet: { bg: "var(--violet-glow)", fg: "var(--violet-400)" },
  // legacy aliases
  green: { bg: "var(--emerald-glow)", fg: "var(--emerald-400)" },
  red: { bg: "var(--rose-glow)", fg: "var(--rose-400)" },
  super_admin: { bg: "var(--emerald-glow)", fg: "var(--emerald-400)" },
  admin: { bg: "var(--violet-glow)", fg: "var(--violet-400)" },
  manager: { bg: "var(--sky-glow)", fg: "var(--sky-400)" },
  employee: { bg: "var(--surface-hover)", fg: "var(--text-body)" },
};

export function Badge({
  tone = "neutral",
  dot = false,
  className,
  children,
  ...props
}: HTMLAttributes<HTMLSpanElement> & { tone?: string; dot?: boolean }) {
  const s = TONES[tone] ?? TONES.neutral;
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold capitalize leading-none tracking-[0.02em]",
        className
      )}
      style={{ background: s.bg, color: s.fg }}
      {...props}
    >
      {dot && <span className="h-1.5 w-1.5 rounded-full" style={{ background: "currentColor" }} />}
      {children}
    </span>
  );
}
