import React from "react";

/** Dialog — centered modal with blurred backdrop. Controlled via `open`/`onClose`. */
export function Dialog({ open, onClose, title, description, maxWidth = 480, children }) {
  if (!open) return null;
  return (
    <div className="erp-dialog-backdrop" onClick={onClose}>
      <div className="erp-dialog" style={{ maxWidth }} onClick={(e) => e.stopPropagation()}>
        {(title || description) && (
          <div style={{ padding: "var(--space-6) var(--space-6) var(--space-3)" }}>
            {title && <h2 style={{ font: "var(--weight-semibold) var(--text-xl)/1.2 var(--font-sans)", letterSpacing: "var(--tracking-tight)", color: "var(--text-strong)", margin: 0 }}>{title}</h2>}
            {description && <p style={{ marginTop: "var(--space-2)", font: "var(--font-body)", color: "var(--text-muted)" }}>{description}</p>}
          </div>
        )}
        <div style={{ padding: "var(--space-3) var(--space-6) var(--space-6)" }}>{children}</div>
      </div>
    </div>
  );
}
