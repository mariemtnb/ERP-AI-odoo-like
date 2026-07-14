import React from "react";

/** Card — the primary surface container. Soft elevation + hairline. */
export function Card({ hover = false, className = "", style, children, ...props }) {
  return (
    <div className={`erp-card ${hover ? "erp-card--hover" : ""} ${className}`} style={style} {...props}>
      {children}
    </div>
  );
}

export function CardHeader({ className = "", children, ...props }) {
  return (
    <div className={className} style={{ padding: "var(--space-6) var(--space-6) var(--space-3)" }} {...props}>
      {children}
    </div>
  );
}

export function CardTitle({ className = "", children, ...props }) {
  return (
    <h3 className={className} style={{ font: "var(--weight-semibold) var(--text-lg)/1.2 var(--font-sans)", letterSpacing: "var(--tracking-tight)", color: "var(--text-strong)", margin: 0 }} {...props}>
      {children}
    </h3>
  );
}

export function CardContent({ className = "", children, ...props }) {
  return (
    <div className={className} style={{ padding: "var(--space-3) var(--space-6) var(--space-6)" }} {...props}>
      {children}
    </div>
  );
}
