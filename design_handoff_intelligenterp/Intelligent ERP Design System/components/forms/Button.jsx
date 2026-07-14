import React from "react";

/**
 * Button — the primary action primitive for Intelligent ERP.
 * Variants: primary (emerald), secondary, ghost, outline, danger.
 * Sizes: sm, md, lg. Supports leading/trailing icons and a loading state.
 */
export function Button({
  variant = "primary",
  size = "md",
  loading = false,
  icon = null,
  iconRight = null,
  children,
  className = "",
  disabled,
  ...props
}) {
  return (
    <button
      className={`erp-btn erp-btn--${variant} erp-btn--${size} ${className}`}
      disabled={disabled || loading}
      {...props}
    >
      {loading ? <Spinner /> : icon}
      {children}
      {!loading && iconRight}
    </button>
  );
}

function Spinner() {
  return (
    <span
      aria-hidden
      style={{
        width: 14,
        height: 14,
        border: "2px solid currentColor",
        borderTopColor: "transparent",
        borderRadius: "999px",
        display: "inline-block",
        animation: "erp-spin 0.7s linear infinite",
      }}
    >
      <style>{"@keyframes erp-spin{to{transform:rotate(360deg)}}"}</style>
    </span>
  );
}
