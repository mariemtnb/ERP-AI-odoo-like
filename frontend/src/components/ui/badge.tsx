import { type HTMLAttributes } from "react";
import { cn } from "@/lib/utils";

const styles: Record<string, string> = {
  admin: "bg-indigo-500/15 text-indigo-400",
  manager: "bg-amber-500/15 text-amber-400",
  employee: "bg-slate-500/15 text-slate-300",
  green: "bg-emerald-500/15 text-emerald-400",
  red: "bg-red-500/15 text-red-400",
};

export function Badge({
  tone = "employee",
  className,
  ...props
}: HTMLAttributes<HTMLSpanElement> & { tone?: string }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium",
        styles[tone] ?? styles.employee,
        className
      )}
      {...props}
    />
  );
}
