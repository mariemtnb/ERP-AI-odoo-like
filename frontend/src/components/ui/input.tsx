import { forwardRef, type InputHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(
  ({ className, ...props }, ref) => (
    <input
      ref={ref}
      className={cn(
        "flex h-10 w-full rounded-md bg-surface-2 px-3.5 text-sm text-text",
        "shadow-[inset_0_0_0_1px_hsl(var(--stroke-soft))]",
        "placeholder:text-text-3",
        "transition-all duration-200 ease-out",
        "hover:shadow-[inset_0_0_0_1px_hsl(var(--stroke))]",
        "focus:shadow-[inset_0_0_0_1.5px_hsl(var(--accent)/0.8),0_0_0_4px_hsl(var(--accent)/0.12)]",
        "focus:outline-none focus-visible:outline-none",
        "disabled:opacity-45",
        className
      )}
      {...props}
    />
  )
);
Input.displayName = "Input";
