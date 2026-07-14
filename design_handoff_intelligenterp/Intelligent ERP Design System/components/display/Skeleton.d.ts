import { HTMLAttributes } from "react";

export interface SkeletonProps extends HTMLAttributes<HTMLDivElement> {
  width?: string | number;
  height?: string | number;
  radius?: string | number;
}

/** Shimmering placeholder block for loading states. */
export function Skeleton(props: SkeletonProps): JSX.Element;
