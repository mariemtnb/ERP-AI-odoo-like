# Handoff: IntelligentERP — UI/UX Redesign (Design System, App Reskin & Landing)

## Overview
This bundle is the complete visual specification for redesigning **IntelligentERP** — an
AI-powered ERP for SMEs (products, inventory, customers, suppliers, purchases, sales, CRM,
reports, users, and a conversational AI assistant). It contains a full design system
(tokens + components), a high-fidelity recreation of the entire application UI, and a
cinematic pre-auth landing page.

**Your existing backend is untouched.** The task is a UI/UX reskin only — do NOT change
routes, API calls, business logic, database models, or the backend. Rebuild the *interface*
against the visual spec below.

## About the Design Files
The files here are **design references authored in HTML/CSS/vanilla-JS** — prototypes that
show the intended look, motion, and behavior. They are **not production code to copy verbatim.**
Your job is to **recreate them in the real codebase's environment**:

- Target stack (from the source repo): **React + Vite + TypeScript + TailwindCSS**,
  shadcn-style UI primitives, **lucide-react** icons, TanStack Query for data.
- Recreate the visuals faithfully using that stack + the brief's animation libraries
  (**Framer Motion**, **GSAP + ScrollTrigger**, **Lenis**, and **Three.js / React Three Fiber**
  for the landing's 3D core). The prototype uses a hand-rolled canvas engine and CSS/IntersectionObserver
  because it must be dependency-free; in production, prefer the real libraries.
- The ERP prototype runs on **mock data** (`ui_kits/erp-app/data.js`). Wire the recreated
  components to your existing API layer instead.

## Fidelity
**High-fidelity.** Final colors, typography, spacing, radii, shadows, and interactions are
all specified via CSS custom properties in `styles.css` + `tokens/`. Recreate pixel-accurately.
Pull exact values from the token files rather than eyeballing screenshots.

## Design Tokens
All tokens live in `tokens/*.css` and are the single source of truth. Highlights:

**Color (dark-mode first; never pure black/white):**
- App bg `--charcoal-950 #0b0d0c`; surface `#131615`; card `--surface-card #191d1b`; hover `#1f2422`.
- Border `--border #313835`; subtle border = that at ~55% alpha.
- Text: strong `#f4f6f4`, body `#ccd2ce`, muted `#8b938e`, faint `#6b736f`.
- **Accent = emerald**: primary `--emerald-500 #10b981`, accent/focus `--emerald-400 #34d399`,
  hover lightens 500→400. `--text-on-accent #04140d`.
- Semantic (tinted quiet bg + saturated fg): amber `#fbbf24`, rose `#fb7185`, sky `#38bdf8`, violet `#a78bfa`.

**Typography:** `--font-sans` = **Schibsted Grotesk** (Google Fonts), `--font-mono` = **JetBrains Mono**
(SKUs, IDs, currency, tabular numbers). Scale 11→84px; app body 14px/1.5. Weights 400/500/600
dominate; large display uses tight tracking (`-0.03em` to `-0.045em`). All-caps eyebrows at 11px, `.14em`.

**Spacing:** 4px base grid; page padding 36–40px; card padding 20–22px; section gaps ≥20px. Generous.

**Radius:** inputs/buttons 12px (`--radius-md`), cards 16px (`--radius-lg`), panels/modals 22px
(`--radius-xl`), pills 999px.

**Elevation:** low-contrast layered black shadows (`--shadow-xs`…`--shadow-xl`) + inner top
hairline `--hairline: inset 0 1px 0 rgba(255,255,255,0.04)`. Emerald glow `--shadow-accent`
only on primary hover / brand mark. Focus ring `--ring-focus` (1px emerald + 3px glow).

**Motion:** `--ease-out cubic-bezier(0.16,1,0.3,1)` (signature), `--ease-spring cubic-bezier(0.34,1.56,0.64,1)`
(modal pop). Durations 120/200/320/480ms. Page content fades + rises 8px on route change. Reduced-motion fully honored.

## Components (recreate as your primitives)
Specs + props in `components/**/*.d.ts` and `.prompt.md`; states in `components/components.css`.
- **Button** — variants primary/secondary/ghost/outline/danger; sizes sm(32)/md(38)/lg(46);
  loading spinner, leading/trailing icon; active scales 0.985.
- **IconButton** — chromeless square (32/38), scale 0.92 on press.
- **Input / Select** — 40px, inset bg, hover border-strong, emerald focus ring; label + hint/error; select has custom chevron.
- **Card** (+ Header/Title/Content) — surface-card, subtle border, `--shadow-sm` + hairline; `hover` lifts 2px + `--shadow-lg`.
- **Badge** — pill, tones neutral/emerald/amber/rose/sky/violet, optional leading dot.
- **StatusDot** — colored dot + optional ping pulse + label.
- **Skeleton** — shimmer placeholder (`erp-shimmer` keyframe).
- **Table** (+ THead/TBody/Tr/Th/Td) — sticky uppercase header, soft row hover, `mono`/right-align helpers.
- **Dialog** — centered modal, blurred backdrop (blur 6px over rgba charcoal), spring `erp-pop` entrance.
- **KpiCard** — eyebrow label, large tabular value, delta chip (▲/▼, emerald/rose), inline sparkline.

## Screens / Views (from `ui_kits/erp-app/`)
The prototype is an SPA shell: **collapsible sidebar (264↔72px)** + **sticky glass topbar (64px)** +
centered content (max 1320px). Route change animates `erp-page-enter`.

- **Login** — split screen: left emerald-wash hero with wordmark + value prop; right sign-in form (email/password, primary submit). Demo creds shown in mono.
- **Sidebar** — brand mark (emerald hexagon tile + "Intelligent**ERP**"), WORKSPACE eyebrow, 11 nav items with lucide icons; active item = surface-hover bg + 3px emerald left rail + emerald icon; assistant item has a live dot. Collapsible with panel-left icons. User chip (avatar initials + role) pinned bottom.
- **Topbar** — ⌘K search-launcher pill, "Ask AI" secondary button, notifications (rose dot), theme toggle, sign-out. Translucent `backdrop-filter: blur(12px)`.
- **Command palette (⌘K)** — top-anchored modal, search field + ESC chip, "Quick actions" list with icon tiles + hint labels.
- **Dashboard** — greeting header + This month / Ask AI actions; 4 KPI cards (auto-fit ≥210px) with sparklines + animated deltas; revenue trend panel + AI-insight panel (emerald gradient, Draft PO / Dismiss); Top products list + Low-stock progress bars.
- **Products** — search field + Filters; table (SKU mono, name, category, stock w/ low badge, price mono, active/inactive badge, edit/archive row actions); inactive rows dimmed.
- **Inventory** — 4 KPIs (stock in/out, warehouses, low stock); "Record movement" form with **segmented in/out/adjust** control, product/qty/warehouse/reason fields; filterable **movement history** table with typed movement badges (in=emerald, out=rose, adjustment=amber, transfer=sky) + directional icons.
- **Customers / Suppliers** (`Partners`) — searchable table with avatar initials, contact info, order/PO counts, active status, edit/deactivate actions.
- **Purchases / Sales** (`Documents`) — status filter chips (draft/pending/confirmed/received/cancelled); order table (number mono emerald, partner, date, status badge, total, by); **row click opens a detail dialog** with line items, computed subtotals, total, and status-contextual actions (Confirm / Approve order / Receive goods / Invoice PDF / Cancel). Purchases also has "Import from invoice".
- **Reports** — segmented Sales/Purchases/Stock tabs; date range for dated reports; result table (sales/purchases show docs; stock shows SKU/qty/min/value with low rows in rose); footer total.
- **Users** — team roster: avatar, email mono, role badge (admin=violet/manager=sky/employee=neutral), active status.
- **AI Assistant** — see below.

## AI Assistant (the centerpiece)
One shared `Conversation` component rendered in three modes:
- **Full page** (`assistant` route) — header (sparkles tile + "approval-first · local model" status), scrollable conversation, suggested-prompt chips, input row (48px field + mic + emerald send).
- **Dock** — floating 420×640 panel, bottom-right, own header with fullscreen/close, compact bubbles, 2 prompt chips.
- **Fullscreen** — immersive overlay, centered 860px conversation column.
- **Launcher** — pulsing emerald FAB bottom-right on every page except the full Assistant view; topbar "Ask AI" also opens the dock. Esc closes.

**Conversation behavior:** user bubbles = emerald, right-aligned; assistant bubbles = surface-card,
left, with **tool-call chips** (wrench icon + `list_products`, `create_purchase_order`, …). Destructive/
write actions render an **approval card** (amber, "Confirmation required — <tool>", JSON args in mono `<pre>`,
Approve/Reject) — nothing is written until the user approves. This mirrors the real agent's approval-first contract.

## Interactions & Behavior
- Route change: `erp-page-enter` (fade + 8px rise, 320ms ease-out).
- Buttons: hover lightens + glow (primary), press scale 0.985; ghost gains faint fill; sheen sweep on landing buttons.
- Cards: `hover` lift 2px + shadow-lg.
- Command palette: ⌘K/Ctrl-K toggles, Esc closes.
- Assistant: FAB → dock → fullscreen transitions; Esc closes; typed/streamed assistant text.
- Tables: row hover fill; document rows clickable → dialog; backdrop click closes dialog.
- Everything respects `prefers-reduced-motion`.

## Landing page (`landing/index.html`) — pre-auth cinematic experience
8 scroll "scenes": (1) full-viewport hero with word-by-word reveal + procedural backdrop,
(2) "AI awakens" scroll-lit text, (3) ecosystem tilt-cards, (4) 9 modules orbiting a live AI core,
(5) self-defusing-stockout workflow with beam reveals, (6) live analytics (animated counters + bars +
typed assistant reasoning), (7) future statement, (8) CTA card → workspace. Fixed **canvas** renders a
rotating neural core (spherical node projection) + linked particle field, reactive to mouse + scroll.
Custom cursor w/ magnetic ring, magnetic buttons, 3D tilt + spotlight cards, scroll progress bar,
sticky glass nav. **In production, rebuild the core with Three.js / R3F and the scroll story with GSAP
ScrollTrigger + Lenis** (the brief's stack); the prototype hand-rolls these to stay dependency-free.
CTA/nav buttons link to `../ui_kits/erp-app/index.html` (your real workspace route in production).

## State Management
Recreate with your existing patterns (React state / TanStack Query). Prototype-local UI state to
reproduce: sidebar collapsed, active route, command-palette open, assistant mode (closed/dock/full),
per-table search + status filter, open document detail, inventory movement-type toggle, conversation messages.
All data comes from your API in production — the prototype's `data.js` shapes match `frontend/src/types/index.ts`.

## Assets
- **Icons:** lucide (`lucide-react` in your app; prototype loads lucide UMD). Stroke width 1.75, currentColor.
- **Fonts:** Schibsted Grotesk + JetBrains Mono via Google Fonts (`tokens/fonts.css`). The source app shipped
  no brand font — swap if you have a licensed face.
- **Logo:** none in source. Brand = "Intelligent**ERP**" wordmark + generic lucide `hexagon` in an emerald tile.
  No real logo was invented — replace with your mark if you have one.
- No stock images or bitmaps anywhere; all visuals are CSS/SVG/canvas.

## Files
- `styles.css` — single entry; `@import`s all tokens + fonts + component styles.
- `tokens/colors.css · typography.css · spacing.css · effects.css · fonts.css` — the token source of truth.
- `components/components.css` — interactive states.
- `components/{forms,display,feedback,data}/*.{jsx,d.ts,prompt.md}` — component specs.
- `ui_kits/erp-app/{index.html, data.js, ui.jsx, pages.jsx, modules.jsx, app.jsx}` — full app recreation.
- `landing/{index.html, landing.css, core.js, interactions.js}` — cinematic landing.
- `readme.md` (project root) — CONTENT FUNDAMENTALS, VISUAL FOUNDATIONS, ICONOGRAPHY (read this first).
- `SKILL.md` — brand design guidelines summary.
