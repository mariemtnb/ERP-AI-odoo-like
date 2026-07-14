/* Cinematic backdrop: drifting particle field + neural AI core wired to scroll & mouse.
   Pure canvas, GPU-friendly, capped DPR, respects reduced-motion. */
(function () {
  const canvas = document.getElementById("core-canvas");
  const ctx = canvas.getContext("2d");
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const EM = { c1: "16,185,129", c2: "52,211,153", c3: "56,189,248" };
  let W, H, DPR;
  const mouse = { x: 0.5, y: 0.5, tx: 0.5, ty: 0.5 };
  let scrollN = 0; // 0..1 through hero region

  function resize() {
    DPR = Math.min(window.devicePixelRatio || 1, 2);
    W = canvas.width = innerWidth * DPR;
    H = canvas.height = innerHeight * DPR;
    canvas.style.width = innerWidth + "px";
    canvas.style.height = innerHeight + "px";
    build();
  }

  /* ---- particle field ---- */
  let particles = [];
  function build() {
    const count = Math.min(120, Math.floor((innerWidth * innerHeight) / 14000));
    particles = Array.from({ length: count }, () => ({
      x: Math.random() * W, y: Math.random() * H,
      z: Math.random() * 0.8 + 0.2,
      vx: (Math.random() - 0.5) * 0.12, vy: (Math.random() - 0.5) * 0.12,
      r: Math.random() * 1.6 + 0.4,
    }));
  }

  /* ---- neural core nodes (spherical projection) ---- */
  const NODES = 34;
  const core = Array.from({ length: NODES }, (_, i) => {
    const phi = Math.acos(1 - 2 * (i + 0.5) / NODES);
    const theta = Math.PI * (1 + Math.sqrt(5)) * i;
    return { x: Math.sin(phi) * Math.cos(theta), y: Math.sin(phi) * Math.sin(theta), z: Math.cos(phi), p: Math.random() * Math.PI * 2 };
  });

  function draw(t) {
    mouse.x += (mouse.tx - mouse.x) * 0.06;
    mouse.y += (mouse.ty - mouse.y) * 0.06;
    ctx.clearRect(0, 0, W, H);

    const cx = W * (0.5 + (mouse.x - 0.5) * 0.05);
    const cy = H * (0.46 + (mouse.y - 0.5) * 0.05);

    // ---- particles + links ----
    for (const p of particles) {
      p.x += p.vx * p.z * DPR + (mouse.x - 0.5) * 0.25 * p.z;
      p.y += p.vy * p.z * DPR + (mouse.y - 0.5) * 0.25 * p.z;
      if (p.x < 0) p.x += W; if (p.x > W) p.x -= W;
      if (p.y < 0) p.y += H; if (p.y > H) p.y -= H;
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r * DPR, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${EM.c2},${0.10 + p.z * 0.18})`;
      ctx.fill();
    }
    // subtle links between near particles
    ctx.lineWidth = DPR * 0.6;
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const a = particles[i], b = particles[j];
        const dx = a.x - b.x, dy = a.y - b.y, d2 = dx * dx + dy * dy;
        const max = (140 * DPR) ** 2;
        if (d2 < max) {
          const o = (1 - d2 / max) * 0.12;
          ctx.strokeStyle = `rgba(${EM.c1},${o})`;
          ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
        }
      }
    }

    // ---- AI neural core ----
    const R = Math.min(W, H) * (0.20 - scrollN * 0.06);
    const rotY = t * 0.00022 + mouse.x * 0.8;
    const rotX = 0.5 + mouse.y * 0.5 + scrollN * 0.6;
    const cosY = Math.cos(rotY), sinY = Math.sin(rotY), cosX = Math.cos(rotX), sinX = Math.sin(rotX);
    const proj = core.map((n) => {
      let x = n.x * cosY - n.z * sinY;
      let z = n.x * sinY + n.z * cosY;
      let y = n.y * cosX - z * sinX;
      z = n.y * sinX + z * cosX;
      const scale = 1 / (1.8 - z);
      return { sx: cx + x * R * scale, sy: cy + y * R * scale, z, scale, pulse: 0.5 + 0.5 * Math.sin(t * 0.002 + n.p) };
    });

    // halo
    const halo = ctx.createRadialGradient(cx, cy, R * 0.2, cx, cy, R * 2.4);
    halo.addColorStop(0, `rgba(${EM.c1},${0.14 * (1 - scrollN * 0.7)})`);
    halo.addColorStop(1, "rgba(0,0,0,0)");
    ctx.fillStyle = halo;
    ctx.fillRect(cx - R * 2.6, cy - R * 2.6, R * 5.2, R * 5.2);

    // connections
    ctx.lineWidth = DPR * 0.7;
    for (let i = 0; i < proj.length; i++) {
      for (let j = i + 1; j < proj.length; j++) {
        const a = proj[i], b = proj[j];
        const dx = a.sx - b.sx, dy = a.sy - b.sy, d2 = dx * dx + dy * dy;
        const max = (R * 0.82) ** 2;
        if (d2 < max) {
          const o = (1 - d2 / max) * 0.5 * Math.min(a.z + 1.2, 1) * (1 - scrollN * 0.6);
          ctx.strokeStyle = `rgba(${EM.c2},${o})`;
          ctx.beginPath(); ctx.moveTo(a.sx, a.sy); ctx.lineTo(b.sx, b.sy); ctx.stroke();
        }
      }
    }
    // nodes
    for (const n of proj) {
      const rad = (1.4 + n.pulse * 2.4) * n.scale * DPR;
      const alpha = (0.4 + n.pulse * 0.6) * (1 - scrollN * 0.5);
      const g = ctx.createRadialGradient(n.sx, n.sy, 0, n.sx, n.sy, rad * 3);
      g.addColorStop(0, `rgba(${EM.c2},${alpha})`);
      g.addColorStop(1, "rgba(0,0,0,0)");
      ctx.fillStyle = g;
      ctx.beginPath(); ctx.arc(n.sx, n.sy, rad * 3, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.arc(n.sx, n.sy, rad, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(230,255,245,${alpha})`; ctx.fill();
    }

    raf = requestAnimationFrame(draw);
  }

  let raf;
  function onScroll() {
    scrollN = Math.min(1, window.scrollY / (innerHeight * 0.9));
  }
  window.addEventListener("resize", resize);
  window.addEventListener("scroll", onScroll, { passive: true });
  window.addEventListener("pointermove", (e) => { mouse.tx = e.clientX / innerWidth; mouse.ty = e.clientY / innerHeight; });

  resize();
  if (reduce) {
    // draw a single static frame
    scrollN = 0; draw(0); cancelAnimationFrame(raf);
  } else {
    raf = requestAnimationFrame(draw);
  }
})();
