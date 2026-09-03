import { useEffect, useRef } from "react";

/**
 * Ambient backdrop for the app shell. Kept deliberately cheap: the soft glow is
 * a static CSS gradient (GPU-composited, no per-frame cost), and the canvas is
 * transparent and only draws a slow constellation - cleared, not repainted with
 * full-screen gradient fills. Throttled to ~30fps, capped device-pixel-ratio,
 * paused when the tab is hidden, and skipped entirely for reduced-motion or
 * low-core devices. This replaces an earlier version whose per-frame radial
 * gradients made the whole app lag.
 */
export default function AppBackground() {
  const ref = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = ref.current;
    if (!canvas) return;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    const reduce =
      window.matchMedia("(prefers-reduced-motion: reduce)").matches ||
      (navigator.hardwareConcurrency ?? 8) <= 4; // spare weak machines

    let raf = 0;
    let last = 0;
    let w = 0;
    let h = 0;
    let dpr = 1;
    type P = { x: number; y: number; vx: number; vy: number };
    let pts: P[] = [];

    function seed() {
      const count = Math.min(38, Math.round((w * h) / 42000));
      pts = Array.from({ length: count }, () => ({
        x: Math.random() * w,
        y: Math.random() * h,
        vx: (Math.random() - 0.5) * 0.12,
        vy: (Math.random() - 0.5) * 0.12,
      }));
    }

    function resize() {
      dpr = Math.min(1.5, window.devicePixelRatio || 1);
      w = window.innerWidth;
      h = window.innerHeight;
      canvas!.width = Math.floor(w * dpr);
      canvas!.height = Math.floor(h * dpr);
      canvas!.style.width = w + "px";
      canvas!.style.height = h + "px";
      ctx!.setTransform(dpr, 0, 0, dpr, 0, 0);
      seed();
    }

    function frame() {
      ctx!.clearRect(0, 0, w, h);
      for (const p of pts) {
        p.x += p.vx;
        p.y += p.vy;
        if (p.x < 0 || p.x > w) p.vx *= -1;
        if (p.y < 0 || p.y > h) p.vy *= -1;
      }
      const LINK = 120;
      ctx!.lineWidth = 1;
      for (let i = 0; i < pts.length; i++) {
        for (let j = i + 1; j < pts.length; j++) {
          const dx = pts[i].x - pts[j].x;
          const dy = pts[i].y - pts[j].y;
          const d2 = dx * dx + dy * dy;
          if (d2 < LINK * LINK) {
            const a = (1 - Math.sqrt(d2) / LINK) * 0.14;
            ctx!.strokeStyle = `rgba(52,211,153,${a})`;
            ctx!.beginPath();
            ctx!.moveTo(pts[i].x, pts[i].y);
            ctx!.lineTo(pts[j].x, pts[j].y);
            ctx!.stroke();
          }
        }
      }
      ctx!.fillStyle = "rgba(120,230,190,0.45)";
      for (const p of pts) {
        ctx!.beginPath();
        ctx!.arc(p.x, p.y, 1, 0, Math.PI * 2);
        ctx!.fill();
      }
    }

    function loop(now: number) {
      raf = requestAnimationFrame(loop);
      if (document.hidden) return; // pause off-screen
      if (now - last < 33) return; // ~30fps
      last = now;
      frame();
    }

    resize();
    window.addEventListener("resize", resize);
    if (reduce) {
      frame(); // single static frame
    } else {
      raf = requestAnimationFrame(loop);
    }

    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener("resize", resize);
    };
  }, []);

  return (
    <>
      <div
        aria-hidden
        style={{
          position: "fixed",
          inset: 0,
          zIndex: 0,
          pointerEvents: "none",
          background:
            "radial-gradient(60% 55% at 82% 10%, color-mix(in oklab, var(--emerald-500) 9%, transparent), transparent 70%)," +
            "radial-gradient(55% 45% at 12% 90%, color-mix(in oklab, var(--emerald-500) 7%, transparent), transparent 70%)",
        }}
      />
      <canvas ref={ref} aria-hidden style={{ position: "fixed", inset: 0, zIndex: 0, pointerEvents: "none" }} />
    </>
  );
}
