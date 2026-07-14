import { ReactNode } from "react";

export interface DialogProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  description?: string;
  maxWidth?: number;
  children?: ReactNode;
}

/** Centered modal with blurred backdrop and spring entrance. */
export function Dialog(props: DialogProps): JSX.Element | null;
