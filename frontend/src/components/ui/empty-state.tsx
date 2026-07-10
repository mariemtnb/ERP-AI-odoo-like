import { type ComponentType, type ReactNode } from "react";
import { motion } from "framer-motion";

export function EmptyState({
  icon: Icon,
  title,
  hint,
  action,
}: {
  icon: ComponentType<{ className?: string }>;
  title: string;
  hint?: string;
  action?: ReactNode;
}) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.34, ease: [0.22, 1, 0.36, 1] }}
      className="flex flex-col items-center justify-center gap-3 rounded-xl bg-surface px-8 py-16 text-center shadow-2 ring-1 ring-inset ring-white/[0.045]"
    >
      <div className="glow-accent rounded-full p-5">
        <Icon className="h-8 w-8 text-text-3" />
      </div>
      <p className="text-[15px] font-medium text-text">{title}</p>
      {hint && <p className="max-w-sm text-sm leading-relaxed text-text-3">{hint}</p>}
      {action && <div className="mt-2">{action}</div>}
    </motion.div>
  );
}
