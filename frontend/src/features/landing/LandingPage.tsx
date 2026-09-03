/**
 * Landing - port of design_files/landing/ (8 scroll scenes).
 * Prototype's hand-rolled IntersectionObserver/scroll/raf layer rebuilt on
 * GSAP + ScrollTrigger + Lenis; canvas core rebuilt in R3F (NeuralCore).
 */
import { useEffect, useMemo, useRef } from "react";
import { useNavigate } from "react-router-dom";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import Lenis from "lenis";
import {
  ArrowRight, Boxes, Contact, FileText, Hexagon, Package, PackageOpen,
  ShoppingBag, ShoppingCart, Sparkles, TrendingUp, Truck, Users,
} from "lucide-react";
import { NeuralCore, type CoreState } from "./NeuralCore";
import { useI18n } from "@/lib/i18n";
import "./landing.css";

gsap.registerPlugin(ScrollTrigger);
if (import.meta.env.DEV) (window as unknown as { gsap: typeof gsap }).gsap = gsap;

const ORBIT_MODULES = [
  { key: "nav.products", icon: Package, r: 0.3, speed: 1.4 },
  { key: "nav.inventory", icon: Boxes, r: 0.3, speed: 1.4 },
  { key: "nav.customers", icon: Users, r: 0.3, speed: 1.4 },
  { key: "nav.suppliers", icon: Truck, r: 0.48, speed: -1 },
  { key: "nav.purchases", icon: ShoppingBag, r: 0.48, speed: -1 },
  { key: "nav.sales", icon: ShoppingCart, r: 0.48, speed: -1 },
  { key: "land.crmPipeline", icon: Contact, r: 0.66, speed: 0.7 },
  { key: "nav.reports", icon: FileText, r: 0.66, speed: 0.7 },
  { key: "nav.assistant", icon: Sparkles, r: 0.66, speed: 0.7 },
];

export default function LandingPage() {
  const { t } = useI18n();
  const AI_TYPED_TEXT = t("land.aiTyped");
  const navigate = useNavigate();
  const root = useRef<HTMLDivElement>(null);
  const coreState = useMemo<CoreState>(
    () => ({ mouse: { x: 0.5, y: 0.5, tx: 0.5, ty: 0.5 }, scrollN: 0 }),
    []
  );

  useEffect(() => {
    const el = root.current!;
    const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const ctx = gsap.context(() => {
      /* ---------- Lenis smooth scroll, driven by the GSAP ticker ---------- */
      const lenis = reduce
        ? null
        : new Lenis({ duration: 1.1, easing: (t) => 1 - Math.pow(1 - t, 3) });
      if (lenis) {
        lenis.on("scroll", ScrollTrigger.update);
        gsap.ticker.add((time) => lenis.raf(time * 1000));
        gsap.ticker.lagSmoothing(0);
      }

      /* ---------- hero word-by-word reveal ---------- */
      gsap.fromTo(
        el.querySelectorAll(".reveal-word"),
        { opacity: 0, y: "0.6em", rotateX: -40 },
        {
          opacity: 1, y: 0, rotateX: 0,
          duration: reduce ? 0 : 0.9,
          delay: reduce ? 0 : 0.26,
          stagger: reduce ? 0 : 0.085,
          ease: "expo.out",
        }
      );

      /* ---------- scroll progress + nav stuck + core scrollN ---------- */
      const nav = el.querySelector(".nav")!;
      const progress = el.querySelector(".progress") as HTMLElement;
      ScrollTrigger.create({
        start: 0,
        end: "max",
        onUpdate: (self) => {
          progress.style.width = `${self.progress * 100}%`;
          nav.classList.toggle("stuck", self.scroll() > 40);
          coreState.scrollN = Math.min(1, self.scroll() / (window.innerHeight * 0.9));
        },
      });

      /* ---------- generic reveals (.r → .in) + in-panel counters ---------- */
      const runCounters = (scope: Element) => {
        scope.querySelectorAll<HTMLElement>("[data-count]").forEach((counter) => {
          if (counter.dataset.done) return;
          counter.dataset.done = "1";
          const to = parseFloat(counter.dataset.count!);
          const dec = counter.dataset.dec ? +counter.dataset.dec : 0;
          const suf = counter.dataset.suf ?? "";
          const state = { v: 0 };
          gsap.to(state, {
            v: to,
            duration: reduce ? 0 : 1.4,
            ease: "power3.out",
            onUpdate: () => {
              counter.textContent =
                state.v.toLocaleString("en-US", {
                  minimumFractionDigits: dec,
                  maximumFractionDigits: dec,
                }) + suf;
            },
          });
        });
      };
      el.querySelectorAll(".r, .flow-step, .panel").forEach((target) => {
        ScrollTrigger.create({
          trigger: target,
          start: "top 82%",
          once: true,
          onEnter: () => {
            target.classList.add("in");
            runCounters(target);
          },
        });
      });

      /* ---------- awaken: words light up on scrub ---------- */
      const fadeWords = [...el.querySelectorAll<HTMLElement>(".fade-word")];
      ScrollTrigger.create({
        trigger: el.querySelector(".awaken"),
        start: "top 75%",
        end: "bottom 70%",
        scrub: true,
        onUpdate: (self) => {
          const lit = Math.floor(self.progress * fadeWords.length * 1.4);
          fadeWords.forEach((w, i) => w.classList.toggle("lit", i < lit));
        },
      });

      /* ---------- animated counters ---------- */

      /* ---------- orbit motion (gsap ticker, prototype formula) ---------- */
      const stage = el.querySelector<HTMLElement>(".orbit-stage");
      const orbitNodes = [...el.querySelectorAll<HTMLElement>(".orbit-node")];
      const orbitTick = () => {
        if (!stage) return;
        const t = performance.now() * 0.00018;
        const r = stage.getBoundingClientRect();
        const cx = r.width / 2;
        const cy = r.height / 2;
        orbitNodes.forEach((n, i) => {
          const radius = Number(n.dataset.r) * Math.min(cx, cy);
          const a = t * Number(n.dataset.speed) + (i / orbitNodes.length) * Math.PI * 2;
          const x = cx + Math.cos(a) * radius;
          const y = cy + Math.sin(a) * radius * 0.62;
          n.style.transform = `translate(${x}px, ${y}px) translate(-50%, -50%)`;
          n.style.zIndex = y > cy ? "4" : "2";
          n.style.opacity = String(0.55 + 0.45 * ((Math.sin(a) + 1) / 2));
        });
      };
      if (reduce) orbitTick();
      else gsap.ticker.add(orbitTick);

      /* ---------- typed AI console ---------- */
      const typed = el.querySelector<HTMLElement>(".ai-typed");
      if (typed) {
        ScrollTrigger.create({
          trigger: typed.closest(".panel"),
          start: "top 60%",
          once: true,
          onEnter: () => {
            if (reduce) {
              typed.textContent = AI_TYPED_TEXT;
              typed.classList.remove("ai-typed");
              return;
            }
            let i = 0;
            typed.textContent = "";
            const tick = () => {
              typed.textContent = AI_TYPED_TEXT.slice(0, i++);
              if (i <= AI_TYPED_TEXT.length) setTimeout(tick, 22);
              else typed.classList.remove("ai-typed");
            };
            tick();
          },
        });
      }

      /* ---------- custom cursor ---------- */
      const dot = el.querySelector<HTMLElement>(".cursor-dot");
      const ring = el.querySelector<HTMLElement>(".cursor-ring");
      if (dot && ring && !matchMedia("(hover: none)").matches) {
        const dotX = gsap.quickSetter(dot, "x", "px");
        const dotY = gsap.quickSetter(dot, "y", "px");
        const ringX = gsap.quickTo(ring, "x", { duration: 0.35, ease: "power3.out" });
        const ringY = gsap.quickTo(ring, "y", { duration: 0.35, ease: "power3.out" });
        const move = (e: PointerEvent) => {
          dotX(e.clientX); dotY(e.clientY);
          ringX(e.clientX); ringY(e.clientY);
          coreState.mouse.tx = e.clientX / innerWidth;
          coreState.mouse.ty = e.clientY / innerHeight;
        };
        window.addEventListener("pointermove", move);
        const hot = () => ring.classList.add("hot");
        const cool = () => ring.classList.remove("hot");
        el.querySelectorAll("a, button, .lbtn, .tilt-card, .nav-link").forEach((t) => {
          t.addEventListener("pointerenter", hot);
          t.addEventListener("pointerleave", cool);
        });
      }

      /* ---------- magnetic buttons ---------- */
      if (!reduce) {
        el.querySelectorAll<HTMLElement>("[data-magnetic]").forEach((btn) => {
          const qx = gsap.quickTo(btn, "x", { duration: 0.3, ease: "power3.out" });
          const qy = gsap.quickTo(btn, "y", { duration: 0.3, ease: "power3.out" });
          btn.addEventListener("pointermove", (e) => {
            const r = btn.getBoundingClientRect();
            qx((e.clientX - (r.left + r.width / 2)) * 0.28);
            qy((e.clientY - (r.top + r.height / 2)) * 0.4);
          });
          btn.addEventListener("pointerleave", () => { qx(0); qy(0); });
        });
      }

      /* ---------- tilt cards + spotlight ---------- */
      if (!reduce) {
        el.querySelectorAll<HTMLElement>(".tilt-card").forEach((card) => {
          card.addEventListener("pointermove", (e) => {
            const r = card.getBoundingClientRect();
            const px = (e.clientX - r.left) / r.width;
            const py = (e.clientY - r.top) / r.height;
            gsap.to(card, {
              rotateY: (px - 0.5) * 9,
              rotateX: (0.5 - py) * 9,
              y: -4,
              transformPerspective: 900,
              duration: 0.4,
              ease: "power2.out",
            });
            card.style.setProperty("--mx", `${px * 100}%`);
            card.style.setProperty("--my", `${py * 100}%`);
          });
          card.addEventListener("pointerleave", () =>
            gsap.to(card, { rotateY: 0, rotateX: 0, y: 0, duration: 0.5, ease: "power3.out" })
          );
        });
      }

      return () => {
        lenis?.destroy();
        gsap.ticker.remove(orbitTick);
      };
    }, el);

    return () => ctx.revert();
  }, [coreState]);

  const goWorkspace = () => navigate("/login");
  const scrollTo = (id: string) =>
    document.getElementById(id)?.scrollIntoView({ behavior: "smooth" });

  return (
    <div className="landing" ref={root}>
      <div className="progress" />
      <div className="cursor-ring" />
      <div className="cursor-dot" />
      <NeuralCore state={coreState} />

      {/* NAV */}
      <nav className="nav">
        <a className="brand" href="#top">
          <span className="brand-mark"><Hexagon size={18} strokeWidth={2.4} /></span>
          <span className="brand-name">Intelligent<b>ERP</b></span>
        </a>
        <div className="nav-links">
          <button className="nav-link" onClick={() => scrollTo("ecosystem")}>{t("land.nav.platform")}</button>
          <button className="nav-link" onClick={() => scrollTo("orbit-section")}>{t("land.nav.modules")}</button>
          <button className="nav-link" onClick={() => scrollTo("analytics")}>{t("land.nav.intelligence")}</button>
          <button className="nav-link" onClick={() => scrollTo("future")}>{t("land.nav.vision")}</button>
        </div>
        <button className="lbtn lbtn-ghost" data-magnetic onClick={goWorkspace}>
          <span className="sheen" />{t("land.enterWorkspace")} <ArrowRight size={15} />
        </button>
      </nav>

      {/* SCENE 1 - HERO */}
      <header className="hero scene" id="top">
        <span className="hero-eyebrow"><span className="pulse" /> {t("land.heroEyebrow")}</span>
        <h1>
          <span className="reveal-line">
            {t("land.heroLine1").split(" ").map((w, i) => (
              <span key={i} className="reveal-word">{w}{" "}</span>
            ))}
          </span>
          <br />
          <span className="reveal-line">
            {t("land.heroLine2").split(" ").map((w, i) => (
              <span key={i} className="reveal-word grad">{w}{" "}</span>
            ))}
          </span>
        </h1>
        <p className="lede">
          {t("land.lede")}
        </p>
        <div className="cta-row">
          <button className="lbtn lbtn-primary" data-magnetic onClick={goWorkspace}>
            <span className="sheen" />{t("land.launchDemo")} <ArrowRight size={15} strokeWidth={2.2} />
          </button>
          <button className="lbtn lbtn-ghost" data-magnetic onClick={() => scrollTo("ecosystem")}>
            <span className="sheen" />{t("land.seeThinks")}
          </button>
        </div>
        <div className="scroll-hint"><span>{t("land.scroll")}</span><span className="track" /></div>
      </header>

      {/* SCENE 2 - THE AI AWAKENS */}
      <section className="awaken scene">
        <div className="awaken-inner">
          <h2>
            {t("land.awaken1").split(" ").map((w, i) => (
              <span key={i} className="fade-word">{w} </span>
            ))}
            {t("land.awaken2").split(" ").map((w, i) => (
              <span key={`a${i}`} className="fade-word accent">{w} </span>
            ))}
          </h2>
        </div>
      </section>

      {/* SCENE 3 - ECOSYSTEM */}
      <section className="section scene" id="ecosystem">
        <div className="section-tag r">{t("land.platform")}</div>
        <h2 className="r d1">{t("land.ecoH")}</h2>
        <p className="sub r d2">
          {t("land.ecoSub")}
        </p>
        <div className="grid-cards">
          <div className="tilt-card r">
            <div className="card-ico"><PackageOpen size={22} strokeWidth={1.8} /></div>
            <h3>{t("land.card1H")}</h3>
            <p>{t("land.card1P")}</p>
          </div>
          <div className="tilt-card r d1">
            <div className="card-ico"><Sparkles size={22} strokeWidth={1.8} /></div>
            <h3>{t("land.card2H")}</h3>
            <p>{t("land.card2P")}</p>
          </div>
          <div className="tilt-card r d2">
            <div className="card-ico"><Boxes size={22} strokeWidth={1.8} /></div>
            <h3>{t("land.card3H")}</h3>
            <p>{t("land.card3P")}</p>
          </div>
        </div>
      </section>

      {/* SCENE 4 - MODULES ORBITING AI */}
      <section className="section scene" id="orbit-section">
        <div className="section-tag r">{t("land.ecosystem")}</div>
        <h2 className="r d1">{t("land.orbitH")}</h2>
        <div className="orbit-stage">
          <div className="orbit-ring" style={{ width: "44%", height: "28%" }} />
          <div className="orbit-ring" style={{ width: "70%", height: "46%" }} />
          <div className="orbit-ring" style={{ width: "96%", height: "64%" }} />
          <div className="orbit-core"><Sparkles size={42} strokeWidth={1.7} /></div>
          {ORBIT_MODULES.map((m) => (
            <div key={m.key} className="orbit-node" data-r={m.r} data-speed={m.speed}>
              <span className="ico"><m.icon size={15} strokeWidth={1.8} /></span>
              {t(m.key)}
            </div>
          ))}
        </div>
      </section>

      {/* SCENE 5 - WORKFLOW */}
      <section className="section scene" id="workflow">
        <div className="section-tag r">{t("land.liveWorkflow")}</div>
        <h2 className="r d1">{t("land.workflowH")}</h2>
        <p className="sub r d2">
          {t("land.workflowSub")}
        </p>
        <div className="flow">
          {[
            [t("land.step1n"), t("land.step1h"), t("land.step1p")],
            [t("land.step2n"), t("land.step2h"), t("land.step2p")],
            [t("land.step3n"), t("land.step3h"), t("land.step3p")],
            [t("land.step4n"), t("land.step4h"), t("land.step4p")],
          ].map(([n, h, p], i) => (
            <div key={n} className={`flow-step r${i ? ` d${i}` : ""}`}>
              <div className="step-n">{n}</div>
              <h4>{h}</h4>
              <p>{p}</p>
              <span className="beam" />
            </div>
          ))}
        </div>
      </section>

      {/* SCENE 6 - LIVE ANALYTICS */}
      <section className="section scene" id="analytics">
        <div className="section-tag r">{t("land.intelligence")}</div>
        <h2 className="r d1">{t("land.analyticsH")}</h2>
        <div className="analytics">
          <div className="panel r">
            <div className="glow-wash" />
            <span className="section-tag" style={{ margin: "0 0 18px" }}>{t("land.revenueThisMonth")}</span>
            <div className="metric-row">
              <span className="metric-val" data-count="48250">0</span>
              <span className="metric-unit">TND</span>
              <span className="metric-delta">
                <TrendingUp size={14} strokeWidth={2.4} />
                <span data-count="12.4" data-dec="1" data-suf="%">0</span>
              </span>
            </div>
            <div className="spark-wrap">
              <div className="bars">
                {[42, 56, 48, 64, 58, 74, 66, 82, 78, 92, 88, 100].map((h, i) => (
                  <div key={i} className="bar" style={{ height: `${h}%`, transitionDelay: `${i * 40}ms` }} />
                ))}
              </div>
            </div>
          </div>
          <div className="panel r d1">
            <div className="glow-wash" />
            <span className="section-tag" style={{ margin: "0 0 18px" }}>{t("land.assistantReasoning")}</span>
            <div className="ai-console">
              <div className="ai-msg" style={{ justifyContent: "flex-end" }}>
                <div className="ai-bubble user">{t("land.userBubble")}</div>
              </div>
              <div className="ai-msg">
                <span className="ai-av"><Sparkles size={15} strokeWidth={1.8} /></span>
                <div className="ai-bubble"><span className="ai-typed" /></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* SCENE 7 - FUTURE */}
      <section className="future scene" id="future">
        <h2 className="r">
          {t("land.futureH1")} <span className="grad">{t("land.futureH2")}</span>
        </h2>
      </section>

      {/* SCENE 8 - CTA */}
      <section className="cta scene">
        <div className="cta-card r">
          <h2>{t("land.ctaH")}</h2>
          <p>
            {t("land.ctaP")}
          </p>
          <div className="cta-row">
            <button className="lbtn lbtn-primary" data-magnetic onClick={goWorkspace}>
              <span className="sheen" />{t("land.enterWorkspace2")} <ArrowRight size={15} strokeWidth={2.2} />
            </button>
            <button className="lbtn lbtn-ghost" data-magnetic onClick={() => scrollTo("top")}>
              <span className="sheen" />{t("land.backToTop")}
            </button>
          </div>
        </div>
      </section>

      <footer className="footer">
        <a className="brand" href="#top">
          <span className="brand-mark"><Hexagon size={18} strokeWidth={2.4} /></span>
          <span className="brand-name">Intelligent<b>ERP</b></span>
        </a>
        <div className="foot-links">
          <button onClick={() => scrollTo("ecosystem")}>{t("land.nav.platform")}</button>
          <button onClick={() => scrollTo("orbit-section")}>{t("land.nav.modules")}</button>
          <button onClick={() => scrollTo("analytics")}>{t("land.nav.intelligence")}</button>
          <button onClick={goWorkspace}>{t("land.demo")}</button>
        </div>
        <small>{t("land.footer")}</small>
      </footer>
    </div>
  );
}
