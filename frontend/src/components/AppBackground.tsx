import { useEffect, useRef } from "react";

/**
 * Ambient animated backdrop for the app shell — a slow emerald constellation
 * over two drifting radial glows, echoing the landing page's neural motif but
 * far quieter so content stays readable. Sits behind everything (the sidebar
 * and cards are opaque and cover it); it shows through the workspace ground.
 * Honours prefers-reduced-motion by painting a single static frame.
 */
export default function AppBackground() {
  const ref = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = ref.current;
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    let raf = 0;
    let w = 0;
    let h = 0;
    let dpr = 1;

    const groundOf = () =>
      getComputedStyle(document.documentElement).getPropertyValue("--bg-app").trim() || "#0b0f0d";

    type P = { x: number; y: number; vx: number; vy: number };
    let pts: P[] = [];

    function seed() {
      const count = Math.min(64, Math.round((w * h) / 26000));
      pts = Array.from({ length: count }, () => ({
        x: Math.random() * w,
        y: Math.random() * h,
        vx: (Math.random() - 0.5) * 0.14,
        vy: (Math.random() - 0.5) * 0.14,
      }));
    }

    function resize() {
      dpr = Math.min(2, window.devicePixelRatio || 1);
      w = window.innerWidth;
      h = window.innerHeight;
      canvas!.width = w * dpr;
      canvas!.height = h * dpr;
      canvas!.style.width = w + "px";
      canvas!.style.height = h + "px";
      ctx!.setTransform(dpr, 0, 0, dpr, 0, 0);
      seed();
    }

    function draw(time: number) {
      const ground = groundOf();
      ctx!.fillStyle = ground;
      ctx!.fillRect(0, 0, w, h);

      // two soft drifting glows
      const glow = (cx: number, cy: number, r: number, a: number) => {
        const g = ctx!.createRadialGradient(cx, cy, 0, cx, cy, r);
        g.addColorStop(0, `rgba(52,211,153,${a})`);
        g.addColorStop(1, "rgba(52,211,153,0)");
        ctx!.fillStyle = g;
        ctx!.fillRect(0, 0, w, h);
      };
      const tt = time * 0.00006;
      glow(w * (0.72 + Math.sin(tt) * 0.06), h * (0.18 + Math.cos(tt * 0.8) * 0.05), Math.max(w, h) * 0.5, 0.05);
      glow(w * (0.2 + Math.cos(tt * 0.7) * 0.05), h * (0.85 + Math.sin(tt) * 0.04), Math.max(w, h) * 0.45, 0.04);

      // constellation
      for (const p of pts) {
        p.x += p.vx;
        p.y += p.vy;
        if (p.x < 0 || p.x > w) p.vx *= -1;
        if (p.y < 0 || p.y > h) p.vy *= -1;
      }
      const LINK = 130;
      ctx!.lineWidth = 1;
      for (let i = 0; i < pts.length; i++) {
        for (let j = i + 1; j < pts.length; j++) {
          const dx = pts[i].x - pts[j].x;
          const dy = pts[i].y - pts[j].y;
          const d2 = dx * dx + dy * dy;
          if (d2 < LINK * LINK) {
            const a = (1 - Math.sqrt(d2) / LINK) * 0.16;
            ctx!.strokeStyle = `rgba(52,211,153,${a})`;
            ctx!.beginPath();
            ctx!.moveTo(pts[i].x, pts[i].y);
            ctx!.lineTo(pts[j].x, pts[j].y);
            ctx!.stroke();
          }
        }
      }
      ctx!.fillStyle = "rgba(120,230,190,0.5)";
      for (const p of pts) {
        ctx!.beginPath();
        ctx!.arc(p.x, p.y, 1.1, 0, Math.PI * 2);
        ctx!.fill();
      }

      if (!reduce) raf = requestAnimationFrame(draw);
    }

    resize();
    window.addEventListener("resize", resize);
    if (reduce) draw(0);
    else raf = requestAnimationFrame(draw);

    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener("resize", resize);
    };
  }, []);

  return (
    <canvas
      ref={ref}
      aria-hidden
      style={{ position: "fixed", inset: 0, zIndex: 0, pointerEvents: "none" }}
    />
  );
}
