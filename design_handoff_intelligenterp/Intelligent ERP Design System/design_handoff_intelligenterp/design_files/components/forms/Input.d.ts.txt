import { InputHTMLAttributes, ReactNode } from "react";

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  hint?: string;
  error?: string;
  icon?: ReactNode;
}

/** Text field with label, hint/error, optional leading icon. Emerald focus ring. */
export function Input(props: InputProps): JSX.Element;
