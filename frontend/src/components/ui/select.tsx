import { forwardRef, type SelectHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

export const Select = forwardRef<
  HTMLSelectElement,
  SelectHTMLAttributes<HTMLSelectElement>
>(({ className, ...props }, ref) => (
  <select
    ref={ref}
    className={cn(
      "flex h-10 w-full appearance-none rounded-md bg-surface-2 px-3.5 pr-9 text-sm text-text",
      "shadow-[inset_0_0_0_1px_hsl(var(--stroke-soft))]",
      "transition-all duration-200 ease-out",
      "hover:shadow-[inset_0_0_0_1px_hsl(var(--stroke))]",
      "focus:shadow-[inset_0_0_0_1.5px_hsl(var(--accent)/0.8),0_0_0_4px_hsl(var(--accent)/0.12)]",
      "focus:outline-none focus-visible:outline-none",
      "disabled:opacity-45",
      // custom chevron
      "bg-no-repeat",
      "bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%23707a8a%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpath%20d%3D%22m6%209%206%206%206-6%22%2F%3E%3C%2Fsvg%3E')]",
      "bg-[position:right_0.75rem_center]",
      className
    )}
    {...props}
  />
));
Select.displayName = "Select";
