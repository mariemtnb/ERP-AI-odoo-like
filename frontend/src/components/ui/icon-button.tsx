import { forwardRef, type ButtonHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

/**
 * IconButton — chromeless square icon button.
 * Port of design_files/components/forms/IconButton + .erp-iconbtn.
 */
export interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  size?: "sm" | "md";
}

export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(
  ({ size = "md", className, type = "button", ...props }, ref) => (
    <button
      ref={ref}
      type={type}
      className={cn("erp-iconbtn", `erp-iconbtn--${size}`, className)}
      {...props}
    />
  )
);
IconButton.displayName = "IconButton";
