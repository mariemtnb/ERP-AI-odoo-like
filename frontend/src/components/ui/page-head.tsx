import { type ReactNode } from "react";

/** Page header - title + subtitle left, actions right. Port of ui_kits PageHead(). */
export function PageHead({
  title,
  sub,
  children,
}: {
  title: string;
  sub?: string;
  children?: ReactNode;
}) {
  return (
    <div className="mb-7 flex items-end justify-between gap-4">
      <div>
        <h1 style={{ margin: 0, font: "600 28px/1.1 var(--font-sans)", letterSpacing: "-0.03em", color: "var(--text-strong)" }}>
          {title}
        </h1>
        {sub && (
          <p style={{ margin: "8px 0 0", font: "400 14px/1.4 var(--font-sans)", color: "var(--text-muted)" }}>
            {sub}
          </p>
        )}
      </div>
      {children && <div className="flex gap-2.5">{children}</div>}
    </div>
  );
}
