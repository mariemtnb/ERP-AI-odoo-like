/* Interaction layer: custom cursor, magnetic buttons, tilt cards, scroll reveals,
   hero word-reveal, awaken word-lighting, animated counters, orbit motion, typed AI. */
(function () {
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const lerp = (a, b, n) => a + (b - a) * n;

  /* ---------- custom cursor ---------- */
  const dot = document.querySelector(".cursor-dot");
  const ring = document.querySelector(".cursor-ring");
  let mx = innerWidth / 2, my = innerHeight / 2, rx = mx, ry = my;
  if (dot && !matchMedia("(hover: none)").matches) {
    addEventListener("pointermove", (e) => { mx = e.clientX; my = e.clientY; dot.style.transform = `translate(${mx}px,${my}px) translate(-50%,-50%)`; });
    (function ring_loop() { rx = lerp(rx, mx, 0.18); ry = lerp(ry, my, 0.18); ring.style.transform = `translate(${rx}px,${ry}px) translate(-50%,-50%)`; requestAnimationFrame(ring_loop); })();
    const hot = () => ring.classList.add("hot"), cool = () => ring.classList.remove("hot");
    document.querySelectorAll("a, button, .btn, .tilt-card, .nav-link").forEach((el) => { el.addEventListener("pointerenter", hot); el.addEventListener("pointerleave", cool); });
  }

  /* ---------- magnetic buttons ---------- */
  if (!reduce) document.querySelectorAll("[data-magnetic]").forEach((el) => {
    el.addEventListener("pointermove", (e) => {
      const r = el.getBoundingClientRect();
      const dx = e.clientX - (r.left + r.width / 2), dy = e.clientY - (r.top + r.height / 2);
      el.style.transform = `translate(${dx * 0.28}px, ${dy * 0.4}px)`;
    });
    el.addEventListener("pointerleave", () => { el.style.transform = ""; });
  });

  /* ---------- tilt cards + spotlight ---------- */
  if (!reduce) document.querySelectorAll(".tilt-card").forEach((card) => {
    card.addEventListener("pointermove", (e) => {
      const r = card.getBoundingClientRect();
      const px = (e.clientX - r.left) / r.width, py = (e.clientY - r.top) / r.height;
      card.style.transform = `perspective(900px) rotateY(${(px - 0.5) * 9}deg) rotateX(${(0.5 - py) * 9}deg) translateY(-4px)`;
      card.style.setProperty("--mx", px * 100 + "%");
      card.style.setProperty("--my", py * 100 + "%");
    });
    card.addEventListener("pointerleave", () => { card.style.transform = ""; });
  });

  /* ---------- scroll reveals ---------- */
  const io = new IntersectionObserver((entries) => {
    entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add("in"); if (en.target.dataset.once !== undefined) io.unobserve(en.target); } });
  }, { threshold: 0.18 });
  document.querySelectorAll(".r, .flow-step, .panel").forEach((el) => io.observe(el));

  /* ---------- counters ---------- */
  const cio = new IntersectionObserver((entries) => {
    entries.forEach((en) => {
      if (!en.isIntersecting) return;
      const el = en.target, to = parseFloat(el.dataset.count), dec = el.dataset.dec ? +el.dataset.dec : 0, dur = 1400;
      const pre = el.dataset.pre || "", suf = el.dataset.suf || "";
      let start;
      const step = (ts) => { start ??= ts; const p = Math.min(1, (ts - start) / dur); const e = 1 - Math.pow(1 - p, 3); const v = to * e;
        el.textContent = pre + v.toLocaleString("en-US", { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suf; if (p < 1) requestAnimationFrame(step); };
      requestAnimationFrame(step); cio.unobserve(el);
    });
  }, { threshold: 0.5 });
  document.querySelectorAll("[data-count]").forEach((el) => cio.observe(el));

  /* ---------- hero word reveal ---------- */
  const heroWords = document.querySelectorAll(".reveal-word");
  heroWords.forEach((w, i) => {
    if (reduce) { w.style.opacity = 1; w.style.transform = "none"; return; }
    w.animate([{ opacity: 0, transform: "translateY(0.6em) rotateX(-40deg)" }, { opacity: 1, transform: "none" }],
      { duration: 900, delay: 260 + i * 85, easing: "cubic-bezier(0.16,1,0.3,1)", fill: "forwards" });
  });

  /* ---------- nav stuck + progress ---------- */
  const nav = document.querySelector(".nav"), prog = document.querySelector(".progress");
  const onScroll = () => {
    nav.classList.toggle("stuck", scrollY > 40);
    const max = document.body.scrollHeight - innerHeight;
    prog.style.width = (max > 0 ? (scrollY / max) * 100 : 0) + "%";
    lightAwaken();
    orbitTick();
  };
  addEventListener("scroll", onScroll, { passive: true });

  /* ---------- awaken word lighting ---------- */
  const awaken = document.querySelector(".awaken");
  const fadeWords = awaken ? [...awaken.querySelectorAll(".fade-word")] : [];
  function lightAwaken() {
    if (!awaken) return;
    const r = awaken.getBoundingClientRect();
    const prog = Math.min(1, Math.max(0, (innerHeight * 0.75 - r.top) / (r.height * 0.55)));
    const lit = Math.floor(prog * fadeWords.length);
    fadeWords.forEach((w, i) => w.classList.toggle("lit", i < lit));
  }

  /* ---------- orbit motion ---------- */
  const nodes = [...document.querySelectorAll(".orbit-node")];
  const stage = document.querySelector(".orbit-stage");
  function orbitTick() {
    if (!stage) return;
    const t = performance.now() * 0.00018;
    const r = stage.getBoundingClientRect();
    const cx = r.width / 2, cy = r.height / 2;
    nodes.forEach((n, i) => {
      const radius = (+n.dataset.r) * Math.min(cx, cy);
      const a = t * (+n.dataset.speed) + (i / nodes.length) * Math.PI * 2;
      const x = cx + Math.cos(a) * radius, y = cy + Math.sin(a) * radius * 0.62;
      n.style.transform = `translate(${x}px, ${y}px) translate(-50%, -50%)`;
      n.style.zIndex = y > cy ? 4 : 2;
      n.style.opacity = 0.55 + 0.45 * ((Math.sin(a) + 1) / 2);
    });
  }
  if (nodes.length && !reduce) (function loop() { orbitTick(); requestAnimationFrame(loop); })();
  else orbitTick();

  /* ---------- typed AI console ---------- */
  const typed = document.querySelector(".ai-typed");
  if (typed) {
    const full = typed.dataset.text;
    const tio = new IntersectionObserver((e) => {
      if (!e[0].isIntersecting) return;
      if (reduce) { typed.textContent = full; typed.classList.remove("ai-typed"); tio.disconnect(); return; }
      let i = 0; typed.textContent = "";
      const tick = () => { typed.textContent = full.slice(0, i++); if (i <= full.length) setTimeout(tick, 22); else typed.classList.remove("ai-typed"); };
      tick(); tio.disconnect();
    }, { threshold: 0.6 });
    tio.observe(typed.closest(".panel") || typed);
  }

  onScroll();
})();
