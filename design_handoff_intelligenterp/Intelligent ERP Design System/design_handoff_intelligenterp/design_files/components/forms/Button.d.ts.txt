import { ButtonHTMLAttributes, ReactNode } from "react";

export interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: "primary" | "secondary" | "ghost" | "outline" | "danger";
  size?: "sm" | "md" | "lg";
  loading?: boolean;
  icon?: ReactNode;
  iconRight?: ReactNode;
}

/**
 * Primary action primitive. Emerald primary; quiet secondary/ghost/outline; danger for destructive.
 * @startingPoint section="Forms" subtitle="Emerald primary button with variants & sizes" viewport="700x160"
 */
export function Button(props: ButtonProps): JSX.Element;
