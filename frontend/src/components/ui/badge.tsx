import { type HTMLAttributes } from "react";
import { cn } from "@/lib/utils";

/* Soft-tint badges: colored dot + tinted pill, readable in any context. */
const styles: Record<string, { pill: string; dot: string }> = {
  admin: { pill: "bg-info/10 text-info", dot: "bg-info" },
  manager: { pill: "bg-warning/10 text-warning", dot: "bg-warning" },
  employee: { pill: "bg-white/[0.06] text-text-2", dot: "bg-text-3" },
  green: { pill: "bg-positive/10 text-positive", dot: "bg-positive" },
  red: { pill: "bg-danger/10 text-danger", dot: "bg-danger" },
};

export function Badge({
  tone = "employee",
  className,
  children,
  ...props
}: HTMLAttributes<HTMLSpanElement> & { tone?: string }) {
  const s = styles[tone] ?? styles.employee;
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium leading-none",
        s.pill,
        className
      )}
      {...props}
    >
      <span className={cn("h-1.5 w-1.5 rounded-full", s.dot)} />
      {children}
    </span>
  );
}
