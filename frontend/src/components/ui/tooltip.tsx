import { type ReactNode } from "react";
import { cn } from "@/lib/utils";

/**
 * Hover tooltip - pure CSS (group-hover), no JS, works on any child.
 * Appears after a short delay so it never nags; hidden from the
 * pointer (pointer-events-none) so it can't be "caught".
 */
export function Tooltip({
  label,
  side = "top",
  children,
  className,
}: {
  label: string;
  side?: "top" | "bottom" | "right" | "left";
  children: ReactNode;
  className?: string;
}) {
  const pos = {
    top: "bottom-full left-1/2 mb-2 -translate-x-1/2",
    bottom: "top-full left-1/2 mt-2 -translate-x-1/2",
    right: "left-full top-1/2 ml-2 -translate-y-1/2",
    left: "right-full top-1/2 mr-2 -translate-y-1/2",
  }[side];

  return (
    <span className={cn("group/tip relative inline-flex", className)}>
      {children}
      <span
        role="tooltip"
        className={cn(
          "pointer-events-none absolute z-50 w-max max-w-[240px] rounded-md bg-surface-3 px-2.5 py-1.5",
          "text-center text-xs font-medium leading-snug text-text shadow-3 ring-1 ring-inset ring-white/[0.08]",
          "opacity-0 transition-opacity duration-150 delay-300 group-hover/tip:opacity-100",
          pos
        )}
      >
        {label}
      </span>
    </span>
  );
}
