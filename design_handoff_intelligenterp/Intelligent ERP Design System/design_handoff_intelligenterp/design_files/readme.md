# Intelligent ERP — Design System

A premium, dark-mode-first design system for **Intelligent ERP**, an AI-powered ERP for
SMEs (inventory, sales, purchases, customers, suppliers, CRM) built around a
conversational AI agent. This system reskins a working Laravel + React/Vite/Tailwind +
MongoDB application from a generic slate/indigo admin theme into a calm, confident,
"2030 SaaS" interface — without touching business logic, routes, or APIs.

## Sources

- **Codebase (ground truth):** attached local folder `odoo-like-stage-dete/`
  — Laravel backend, FastAPI + LangGraph AI service, React SPA in `frontend/`.
  - UI primitives: `frontend/src/components/ui/` (shadcn-style Button, Card, Badge,
    Input, Select, Table, Dialog, Label).
  - Feature screens: `frontend/src/features/` (dashboard, products, inventory,
    partners, documents, crm, assistant, reports, users, auth).
  - Icon set in use: **lucide-react**. Data shapes: `frontend/src/types/index.ts`.
- **Design brief:** provided by the user — dark charcoal, emerald accents, generous
  spacing, premium typography, AI assistant as the centerpiece.

The original app shipped **no brand font and no logo** — see Caveats.

---

## Components

Reusable React primitives under `components/`, bundled to `window.IntelligentERPDesignSystem_b41ec1`.

- **Button** (`forms/`) — primary/secondary/ghost/outline/danger, sizes sm/md/lg, loading + icon slots.
- **IconButton** (`forms/`) — chromeless square icon button.
- **Input** (`forms/`) — labelled text field, leading icon, hint/error, emerald focus ring.
- **Select** (`forms/`) — native select styled to match Input.
- **Card** + CardHeader/CardTitle/CardContent (`display/`) — elevated surface, optional hover lift.
- **Badge** (`display/`) — status pill, tones neutral/emerald/amber/rose/sky/violet, optional dot.
- **StatusDot** (`display/`) — inline status dot with optional pulse.
- **Skeleton** (`display/`) — shimmering loading placeholder.
- **Table** + THead/TBody/Tr/Th/Td (`display/`) — sticky-header data table, mono/right-align helpers.
- **Dialog** (`feedback/`) — centered modal, blurred backdrop, spring entrance.
- **KpiCard** (`data/`) — dashboard metric tile with delta chip + sparkline.

### Intentional additions (not in the source shadcn set)
- **StatusDot**, **Skeleton**, **KpiCard** — the brief explicitly calls for live status,
  loading skeletons, and animated KPI cards; these are new primitives that formalize
  patterns the redesigned dashboard/assistant need.

## UI kits

- **`ui_kits/erp-app/`** — interactive recreation of the whole product: login, collapsible
  sidebar + glass topbar, command palette (⌘K), dashboard (KPIs, revenue trend, AI insight,
  top products, low stock), products table with search, CRM pipeline board, and the AI
  assistant (streaming-style conversation, tool chips, approval card, suggested prompts).
  Open `index.html`; it is self-contained (React + Babel + Lucide from CDN, DS tokens via `styles.css`).

## Foundations & specimen cards

- `styles.css` — the single entry consumers link; `@import`s everything below.
- `tokens/colors.css` · `typography.css` · `spacing.css` · `effects.css` (radius/elevation/motion).
- `tokens/fonts.css` — Google Fonts import (Schibsted Grotesk + JetBrains Mono).
- `components/components.css` — interactive states for the primitives.
- `guidelines/*.card.html` — Colors, Type, Spacing, Radius/Elevation, Motion specimens.

---

## CONTENT FUNDAMENTALS

- **Voice:** calm, direct, second-person. Speaks *to* the user ("Ask about your data",
  "every action needs your approval"). Confident, never salesy.
- **Casing:** Sentence case everywhere — headings, buttons, nav labels ("New product",
  "Ask AI", "Low stock"). Micro-labels/eyebrows are ALL-CAPS with wide tracking
  ("WORKSPACE", "REVENUE").
- **Tone examples:** "Here's what's moving across your business today." · "The ERP that
  thinks alongside you." · "Confirmation required — create_purchase_order."
- **Numbers & data:** tabular figures in JetBrains Mono; SKUs, IDs, currency and code
  in mono. Currency shown as `48,250 TND`. Deltas as `▲ 12.4%` / `▼ 2.3%`.
- **Bilingual:** the product supports EN/FR (assistant accepts French). Keep copy short
  and translatable.
- **Emoji:** not used in UI chrome. The assistant may echo ✔/✘ for approve/reject only.
- **Vibe:** enterprise software that feels like a premium consumer product — quiet
  luxury, lots of breathing room, one clear action per view.

## VISUAL FOUNDATIONS

- **Color:** dark-mode first. Warm charcoal ramp (`--charcoal-950` app bg → `--charcoal-50`
  text), never pure black/white. Single accent: **emerald** (`--emerald-500` primary,
  `--emerald-400` accent/focus). Semantic amber/rose/sky/violet used only as tinted
  "quiet" backgrounds + saturated foreground. Oversaturation avoided.
- **Typography:** **Schibsted Grotesk** for UI + display (geometric grotesque, tight
  negative tracking on large sizes); **JetBrains Mono** for data. Scale runs 11px→84px;
  app body is 14px/1.5. Weights 400/500/600 dominate; 700+ reserved for display.
- **Spacing:** 4px base grid; generous — page padding 36–40px, card padding 20–22px,
  large gaps between sections (20px+). Nothing cramped.
- **Backgrounds:** flat charcoal surfaces + occasional subtle radial "wash"
  (`--wash-emerald`) on hero/KPI/login panels. No photography, no textures, no busy
  gradients. Topbar uses a translucent blur (`backdrop-filter`) over the app bg.
- **Corners:** inputs/buttons 12px (`--radius-md`), cards 16px (`--radius-lg`), panels
  & modals 22px (`--radius-xl`), pills fully round.
- **Cards:** `--surface-card` fill, hairline border (`--border-subtle`), soft layered
  shadow (`--shadow-sm`) plus an inner top highlight (`--hairline`) for the "premium"
  edge. Hover cards lift 2px and gain `--shadow-lg`.
- **Elevation:** low-contrast, multi-layer black shadows (xs→xl); emerald glow
  (`--shadow-accent`) only on primary hover and the brand mark.
- **Borders:** hairline, low-contrast. Prefer a single hairline + shadow over heavy
  borders. Dividers use `--border-subtle`.
- **Focus:** emerald ring (`--ring-focus`) — 1px solid + 3px glow. Always visible.
- **Hover:** surfaces lighten toward `--surface-hover`; primary button lightens
  (500→400) and gains glow; ghost gains a faint fill.
- **Press:** buttons scale to ~0.985 + 0.5px nudge; icon buttons scale to 0.92.
- **Motion:** signature easing `--ease-out` cubic-bezier(0.16,1,0.3,1); `--ease-spring`
  for modal pop. Durations 120/200/320/480ms. Page content fades+rises 8px on route
  change. Skeletons shimmer. Reduced-motion fully respected.
- **Transparency & blur:** used sparingly — sticky topbar glass, modal backdrop
  (blur 6px over rgba charcoal). Tinted status backgrounds use `color-mix`/alpha.
- **Layout:** fixed collapsible sidebar (264px ↔ 72px) + sticky glass topbar (64px);
  content max-width 1320px, centered. Active nav item shows an emerald left rail.

## ICONOGRAPHY

- **Set:** **Lucide** — the exact library the source app uses (`lucide-react`). In this
  design system the UI kit loads Lucide from CDN (`lucide@0.460.0` UMD) and renders via
  `data-lucide` names; production code keeps using `lucide-react`. Stroke width **1.75**,
  `currentColor`, sizes 14–20px in UI (18px default in nav/topbar).
- **Usage:** line icons only, one weight, never filled. Nav uses semantic glyphs
  (`layout-dashboard`, `package`, `boxes`, `contact`, `sparkles`, …). The AI assistant
  uses `sparkles`; tool calls use `wrench`; approvals use `shield-alert`/`check`/`x`.
- **No emoji** as icons in chrome. No custom/hand-drawn SVG icons — use Lucide names.
  The only bespoke marks are the brand hexagon (`hexagon` glyph in an emerald tile) and
  data sparklines (generated from numbers, not icons).

---

## Namespace

Components are exposed at `window.IntelligentERPDesignSystem_b41ec1`. In card/kit HTML:
```js
const { Button, Card, KpiCard, Badge } = window.IntelligentERPDesignSystem_b41ec1;
```

## Caveats

- **No brand font in source** — Schibsted Grotesk + JetBrains Mono are a deliberate
  design choice (source used the Tailwind system stack). Swap `tokens/fonts.css` if a
  licensed brand face is provided.
- **No logo in source** — the brand mark is the product name "Intelligent**ERP**" set in
  type, paired with a generic Lucide `hexagon` tile. No real logo was invented.
