import { type HTMLAttributes } from "react";
import { cn } from "@/lib/utils";

/** Shimmering placeholder — use instead of "Loading…" text. */
export function Skeleton({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn(
        "relative overflow-hidden rounded-md bg-surface-2",
        "after:absolute after:inset-0 after:-translate-x-full after:animate-shimmer",
        "after:bg-gradient-to-r after:from-transparent after:via-white/[0.05] after:to-transparent",
        className
      )}
      {...props}
    />
  );
}

export function TableSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="space-y-3 rounded-xl bg-surface p-5 shadow-2 ring-1 ring-inset ring-white/[0.045]">
      <Skeleton className="h-4 w-1/3" />
      {Array.from({ length: rows }).map((_, i) => (
        <Skeleton key={i} className="h-9 w-full" style={{ opacity: 1 - i * 0.15 }} />
      ))}
    </div>
  );
}
