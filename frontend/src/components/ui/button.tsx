import { cva, type VariantProps } from "class-variance-authority";
import { forwardRef, type ButtonHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

const buttonVariants = cva(
  [
    "relative inline-flex select-none items-center justify-center gap-2",
    "whitespace-nowrap rounded-md text-sm font-medium",
    "transition-all duration-200 ease-out",
    "focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/70 focus-visible:ring-offset-2 focus-visible:ring-offset-bg",
    "disabled:pointer-events-none disabled:opacity-45",
    "active:scale-[0.985]",
  ].join(" "),
  {
    variants: {
      variant: {
        default: [
          "bg-accent text-bg font-semibold",
          "shadow-[inset_0_1px_0_hsl(var(--accent-strong)/0.5)]",
          "hover:bg-accent-strong hover:shadow-accent-glow",
        ].join(" "),
        secondary: "bg-surface-3 text-text hover:bg-surface-3/80 shadow-1",
        ghost: "text-text-2 hover:bg-surface-3/60 hover:text-text",
        destructive:
          "bg-danger/15 text-danger hover:bg-danger/25 shadow-[inset_0_0_0_1px_hsl(var(--danger)/0.3)]",
        outline:
          "text-text-2 shadow-[inset_0_0_0_1px_hsl(var(--stroke))] hover:text-text hover:shadow-[inset_0_0_0_1px_hsl(var(--stroke)),0_4px_16px_-6px_rgb(0_0_0/0.4)] hover:bg-surface-2/50",
      },
      size: {
        default: "h-9 px-4",
        sm: "h-8 px-3 text-xs",
        lg: "h-11 px-6",
        icon: "h-9 w-9",
      },
    },
    defaultVariants: { variant: "default", size: "default" },
  }
);

export interface ButtonProps
  extends ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, ...props }, ref) => (
    <button
      ref={ref}
      className={cn(buttonVariants({ variant, size }), className)}
      {...props}
    />
  )
);
Button.displayName = "Button";
