export interface StatusDotProps {
  tone?: "emerald" | "amber" | "rose" | "sky" | "violet" | "neutral";
  pulse?: boolean;
  label?: string;
  style?: React.CSSProperties;
}

/** Small colored status dot with optional pulse and label. */
export function StatusDot(props: StatusDotProps): JSX.Element;
