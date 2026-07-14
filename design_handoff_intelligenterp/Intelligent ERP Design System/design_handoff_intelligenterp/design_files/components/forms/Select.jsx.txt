import React from "react";

/** Select — native select styled to match Input, with optional label. */
export function Select({ label, hint, error, children, className = "", id, ...props }) {
  const fieldId = id || (label ? `s-${label.replace(/\s+/g, "-").toLowerCase()}` : undefined);
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "var(--space-2)", width: "100%" }}>
      {label && (
        <label htmlFor={fieldId} style={{ font: "var(--weight-medium) var(--text-sm)/1 var(--font-sans)", color: "var(--text-body)" }}>
          {label}
        </label>
      )}
      <select id={fieldId} className={`erp-field ${className}`} style={{ borderColor: error ? "var(--rose-400)" : undefined }} {...props}>
        {children}
      </select>
      {(hint || error) && (
        <span style={{ fontSize: "var(--text-xs)", color: error ? "var(--rose-400)" : "var(--text-faint)" }}>
          {error || hint}
        </span>
      )}
    </div>
  );
}
