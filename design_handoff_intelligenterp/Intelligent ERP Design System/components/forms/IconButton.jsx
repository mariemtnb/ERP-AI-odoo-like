import React from "react";

/** IconButton — square, chromeless button for a single glyph (toolbar, table row actions). */
export function IconButton({ size = "md", children, className = "", ...props }) {
  return (
    <button className={`erp-iconbtn erp-iconbtn--${size} ${className}`} {...props}>
      {children}
    </button>
  );
}
