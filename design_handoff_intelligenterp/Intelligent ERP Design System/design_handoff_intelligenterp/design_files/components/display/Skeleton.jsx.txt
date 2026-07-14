import React from "react";

/** Skeleton — shimmering placeholder block for loading states. */
export function Skeleton({ width = "100%", height = 16, radius = "var(--radius-sm)", className = "", style, ...props }) {
  return (
    <div
      className={`erp-skel ${className}`}
      style={{ width, height, borderRadius: radius, ...style }}
      {...props}
    />
  );
}
