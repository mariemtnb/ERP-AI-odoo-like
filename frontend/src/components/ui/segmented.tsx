import { motion } from "framer-motion";
import { cn } from "@/lib/utils";

/** Animated segmented control — the active pill glides between options. */
export function Segmented<T extends string>({
  options,
  value,
  onChange,
  id,
}: {
  options: { value: T; label: string }[];
  value: T;
  onChange: (v: T) => void;
  id: string;
}) {
  return (
    <div className="inline-flex rounded-lg bg-surface-2 p-1 shadow-[inset_0_0_0_1px_hsl(var(--stroke-soft))]">
      {options.map((o) => {
        const active = o.value === value;
        return (
          <button
            key={o.value}
            onClick={() => onChange(o.value)}
            className={cn(
              "relative rounded-md px-4 py-1.5 text-[13px] font-medium transition-colors duration-150",
              active ? "text-text" : "text-text-3 hover:text-text-2"
            )}
          >
            {active && (
              <motion.span
                layoutId={`segmented-${id}`}
                transition={{ duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
                className="absolute inset-0 rounded-md bg-surface-3 shadow-1"
              />
            )}
            <span className="relative">{o.label}</span>
          </button>
        );
      })}
    </div>
  );
}
