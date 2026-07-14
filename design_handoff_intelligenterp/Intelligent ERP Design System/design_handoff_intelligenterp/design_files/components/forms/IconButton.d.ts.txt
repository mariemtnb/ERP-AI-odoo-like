import { ButtonHTMLAttributes, ReactNode } from "react";

export interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  size?: "sm" | "md";
  children: ReactNode;
}

/** Chromeless square button for a single icon (toolbars, row actions). */
export function IconButton(props: IconButtonProps): JSX.Element;
