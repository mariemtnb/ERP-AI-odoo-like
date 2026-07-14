import { HTMLAttributes, ReactNode } from "react";

export interface CardProps extends HTMLAttributes<HTMLDivElement> {
  /** Lift + glow on hover — use for clickable cards. */
  hover?: boolean;
  children?: ReactNode;
}

/**
 * Surface container with soft elevation and a top hairline.
 * @startingPoint section="Display" subtitle="Elevated surface card with header/content" viewport="700x220"
 */
export function Card(props: CardProps): JSX.Element;
export function CardHeader(props: HTMLAttributes<HTMLDivElement>): JSX.Element;
export function CardTitle(props: HTMLAttributes<HTMLHeadingElement>): JSX.Element;
export function CardContent(props: HTMLAttributes<HTMLDivElement>): JSX.Element;
