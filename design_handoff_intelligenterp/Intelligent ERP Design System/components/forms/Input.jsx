import React from "react";

/** Input — text field with optional label, hint, error, and leading icon. */
export function Input({ label, hint, error, icon, className = "", id, ...props }) {
  const fieldId = id || (label ? `f-${label.replace(/\s+/g, "-").toLowerCase()}` : undefined);
  return (
    <div style={{ display: "flex", flexDirection: "column", gap: "var(--space-2)", width: "100%" }}>
      {label && (
        <label htmlFor={fieldId} className="ds-label" style={labelStyle}>
          {label}
        </label>
      )}
      <div style={{ position: "relative", display: "flex", alignItems: "center" }}>
        {icon && (
          <span style={{ position: "absolute", left: "var(--space-3)", color: "var(--text-faint)", display: "flex" }}>
            {icon}
          </span>
        )}
        <input
          id={fieldId}
          className={`erp-field ${className}`}
          style={{
            paddingLeft: icon ? "var(--space-10)" : undefined,
            borderColor: error ? "var(--rose-400)" : undefined,
          }}
          {...props}
        />
      </div>
      {(hint || error) && (
        <span style={{ font: "var(--font-label)", fontSize: "var(--text-xs)", color: error ? "var(--rose-400)" : "var(--text-faint)" }}>
          {error || hint}
        </span>
      )}
    </div>
  );
}

const labelStyle = {
  font: "var(--weight-medium) var(--text-sm)/1 var(--font-sans)",
  color: "var(--text-body)",
};
