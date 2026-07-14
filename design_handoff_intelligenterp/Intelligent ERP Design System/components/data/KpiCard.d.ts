import { ReactNode } from "react";

export interface KpiCardProps {
  label: string;
  value: string | number;
  unit?: string;
  /** Percentage change; sign drives the up/down chip color. */
  delta?: number;
  tone?: "emerald" | "neutral";
  /** Values for the inline sparkline. */
  spark?: number[];
  icon?: ReactNode;
}

/**
 * Dashboard metric tile: eyebrow label, large tabular value, delta chip, sparkline.
 * @startingPoint section="Data" subtitle="KPI metric tile with delta & sparkline" viewport="360x180"
 */
export function KpiCard(props: KpiCardProps): JSX.Element;
