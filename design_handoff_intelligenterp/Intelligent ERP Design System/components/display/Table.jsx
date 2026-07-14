import React from "react";

/** Table — data table with sticky header, soft row hover, and comfortable rows. */
export function Table({ className = "", children, ...props }) {
  return (
    <div style={{ width: "100%", overflow: "auto", borderRadius: "var(--radius-lg)", border: "1px solid var(--border-subtle)", background: "var(--surface-card)", boxShadow: "var(--shadow-sm), var(--hairline)" }}>
      <table className={`erp-table ${className}`} {...props}>{children}</table>
    </div>
  );
}
export function THead(props) { return <thead {...props} />; }
export function TBody(props) { return <tbody {...props} />; }
export function Tr(props) { return <tr {...props} />; }
export function Th({ align, style, ...props }) {
  return <th style={{ textAlign: align, ...style }} {...props} />;
}
export function Td({ align, mono, style, ...props }) {
  return <td style={{ textAlign: align, fontFamily: mono ? "var(--font-mono)" : undefined, fontSize: mono ? "var(--text-sm)" : undefined, ...style }} {...props} />;
}
