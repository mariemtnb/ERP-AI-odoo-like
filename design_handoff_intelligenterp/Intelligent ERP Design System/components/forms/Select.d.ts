import { SelectHTMLAttributes, ReactNode } from "react";

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  hint?: string;
  error?: string;
  children: ReactNode;
}

/** Native select styled to match Input, with custom chevron and emerald focus ring. */
export function Select(props: SelectProps): JSX.Element;
