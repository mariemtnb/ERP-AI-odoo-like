/** Sparkline — SVG mini-chart. Port of ui_kits Spark(). */
export function Sparkline({
  data,
  up = true,
  w = 80,
  h = 28,
  fill = false,
  className,
}: {
  data: number[];
  up?: boolean;
  w?: number;
  h?: number;
  fill?: boolean;
  className?: string;
}) {
  if (!data || data.length < 2) {
    return <svg width={w} height={h} className={className} aria-hidden />;
  }
  const max = Math.max(...data);
  const min = Math.min(...data);
  const span = max - min || 1;
  const pts = data.map((v, i) => [
    (i / (data.length - 1)) * w,
    h - ((v - min) / span) * (h - 4) - 2,
  ]);
  const line = pts.map((p, i) => `${i ? "L" : "M"}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(" ");
  const color = up ? "var(--emerald-400)" : "var(--rose-400)";
  return (
    <svg
      width={w}
      height={h}
      viewBox={`0 0 ${w} ${h}`}
      preserveAspectRatio="none"
      className={className}
      aria-hidden
    >
      {fill && <path d={`${line} L${w},${h} L0,${h} Z`} fill={color} opacity="0.1" />}
      <path
        d={line}
        fill="none"
        stroke={color}
        strokeWidth="1.75"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}
