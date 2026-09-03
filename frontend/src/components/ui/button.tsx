import { forwardRef, type ButtonHTMLAttributes, type ReactNode } from "react";
import { cn } from "@/lib/utils";

/**
 * Button - Intelligent ERP design system (design_files/components/forms/Button).
 * Variants: primary (emerald) / secondary / ghost / outline / danger.
 * Sizes: sm (32px) / md (38px) / lg (46px). Loading spinner, icon slots.
 *
 * Legacy aliases accepted during the screen-by-screen reskin:
 *   variant "default"→primary, "destructive"→danger; size "default"→md,
 *   "icon"→square md. Remove once all call sites are migrated.
 */
type Variant = "primary" | "secondary" | "ghost" | "outline" | "danger";
type Size = "sm" | "md" | "lg";
type LegacyVariant = "default" | "destructive";
type LegacySize = "default" | "icon";

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant | LegacyVariant;
  size?: Size | LegacySize;
  loading?: boolean;
  icon?: ReactNode;
  iconRight?: ReactNode;
}

const VARIANT_ALIAS: Record<string, Variant> = {
  default: "primary",
  destructive: "danger",
};
const SIZE_ALIAS: Record<string, Size> = { default: "md" };

/* components.css .erp-btn--* translated 1:1 */
const variantClasses: Record<Variant, string> = {
  primary: [
    "bg-[var(--emerald-500)] text-[var(--text-on-accent)]",
    "shadow-[var(--shadow-sm),var(--hairline)]",
    "hover:bg-[var(--emerald-400)] hover:shadow-[var(--shadow-accent)]",
  ].join(" "),
  secondary: [
    "bg-[var(--surface-hover)] text-[var(--text-strong)]",
    "border-[var(--border)] shadow-hairline",
    "hover:bg-[var(--charcoal-750)] hover:border-[var(--border-strong)]",
  ].join(" "),
  ghost: "text-[var(--text-body)] hover:bg-[var(--surface-hover)] hover:text-[var(--text-strong)]",
  outline: [
    "text-[var(--text-body)] border-[var(--border)]",
    "hover:border-[var(--emerald-500)] hover:text-[var(--text-strong)]",
  ].join(" "),
  danger: [
    "bg-[color-mix(in_oklab,var(--rose-400)_18%,transparent)] text-[var(--rose-400)]",
    "hover:bg-[color-mix(in_oklab,var(--rose-400)_26%,transparent)]",
  ].join(" "),
};

const sizeClasses: Record<Size, string> = {
  sm: "h-8 px-3 text-xs", // 32px
  md: "h-[38px] px-4 text-[13px]", // 38px
  lg: "h-[46px] px-6 text-sm rounded-lg", // 46px, radius-lg
};

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  (
    {
      variant = "primary",
      size = "md",
      loading = false,
      icon = null,
      iconRight = null,
      children,
      className,
      disabled,
      ...props
    },
    ref
  ) => {
    const v: Variant = (VARIANT_ALIAS[variant] ?? variant) as Variant;
    const isIconSize = size === "icon";
    const s: Size = isIconSize ? "md" : ((SIZE_ALIAS[size] ?? size) as Size);

    return (
      <button
        ref={ref}
        disabled={disabled || loading}
        className={cn(
          // .erp-btn base
          "inline-flex select-none items-center justify-center gap-2 whitespace-nowrap",
          "rounded-md border border-transparent font-sans font-semibold tracking-snug",
          "cursor-pointer transition-all duration-120 ease-out",
          "active:translate-y-[0.5px] active:scale-[0.985]",
          "focus-visible:outline-none focus-visible:shadow-ring-focus",
          "disabled:pointer-events-none disabled:opacity-45",
          variantClasses[v],
          sizeClasses[s],
          isIconSize && "w-[38px] px-0",
          className
        )}
        {...props}
      >
        {loading ? <Spinner /> : icon}
        {children}
        {!loading && iconRight}
      </button>
    );
  }
);
Button.displayName = "Button";

function Spinner() {
  return (
    <span
      aria-hidden
      className="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
    />
  );
}
