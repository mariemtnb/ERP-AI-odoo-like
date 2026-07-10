import {
  type HTMLAttributes,
  type TdHTMLAttributes,
  type ThHTMLAttributes,
} from "react";
import { cn } from "@/lib/utils";

/* Borderless data tables: separation via row hover + hairline dividers,
   sticky header, comfortable 52px rows, tabular numerals. */

export function Table({ className, ...props }: HTMLAttributes<HTMLTableElement>) {
  return (
    <div className="relative w-full overflow-auto rounded-xl bg-surface shadow-2 ring-1 ring-inset ring-white/[0.045]">
      <table className={cn("w-full text-sm", className)} {...props} />
    </div>
  );
}

export function THead(props: HTMLAttributes<HTMLTableSectionElement>) {
  return (
    <thead
      className="sticky top-0 z-10 bg-surface/95 text-left backdrop-blur-sm
                 [&_tr]:shadow-[inset_0_-1px_0_hsl(var(--stroke-soft))]"
      {...props}
    />
  );
}

export function TBody(props: HTMLAttributes<HTMLTableSectionElement>) {
  return (
    <tbody
      className="[&_tr]:transition-colors [&_tr]:duration-150
                 [&_tr:hover]:bg-white/[0.025]
                 [&_tr+tr]:shadow-[inset_0_1px_0_hsl(var(--stroke-soft)/0.6)]"
      {...props}
    />
  );
}

export function Th({ className, ...props }: ThHTMLAttributes<HTMLTableCellElement>) {
  return (
    <th
      className={cn(
        "px-5 py-3.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-text-3",
        className
      )}
      {...props}
    />
  );
}

export function Td({ className, ...props }: TdHTMLAttributes<HTMLTableCellElement>) {
  return (
    <td
      className={cn("tnum px-5 py-3.5 align-middle text-text-2", className)}
      {...props}
    />
  );
}
