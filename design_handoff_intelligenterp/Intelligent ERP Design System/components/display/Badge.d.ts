import { HTMLAttributes, ReactNode } from "react";

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  tone?: "neutral" | "emerald" | "amber" | "rose" | "sky" | "violet";
  dot?: boolean;
  children: ReactNode;
}

/** Compact status pill with a tinted background. Optional leading status dot. */
export function Badge(props: BadgeProps): JSX.Element;
