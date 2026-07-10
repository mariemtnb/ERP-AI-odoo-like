import { type ReactNode } from "react";
import { AnimatePresence, motion } from "framer-motion";
import { X } from "lucide-react";
import { cn } from "@/lib/utils";

export function Dialog({
  open,
  onClose,
  title,
  children,
  className,
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <AnimatePresence>
      {open && (
        <motion.div
          className="fixed inset-0 z-50 flex items-center justify-center p-4"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.18 }}
          style={{ background: "hsl(var(--overlay) / 0.65)", backdropFilter: "blur(6px)" }}
          onMouseDown={(e) => e.target === e.currentTarget && onClose()}
        >
          <motion.div
            initial={{ opacity: 0, scale: 0.96, y: 12 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.97, y: 8 }}
            transition={{ duration: 0.22, ease: [0.22, 1, 0.36, 1] }}
            className={cn(
              "max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-xl bg-surface p-6 shadow-3",
              "ring-1 ring-inset ring-white/[0.06]",
              className
            )}
          >
            <div className="mb-5 flex items-center justify-between">
              <h2 className="text-lg font-semibold tracking-tight text-text">{title}</h2>
              <button
                onClick={onClose}
                aria-label="Close dialog"
                className="rounded-md p-1.5 text-text-3 transition-colors duration-150 hover:bg-surface-3 hover:text-text"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
            {children}
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
