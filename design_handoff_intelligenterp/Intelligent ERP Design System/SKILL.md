---
name: intelligent-erp-design
description: Use this skill to generate well-branded interfaces and assets for Intelligent ERP (a premium, dark-mode, AI-native ERP), either for production or throwaway prototypes/mocks/etc. Contains essential design guidelines, colors, type, fonts, and UI kit components for prototyping.
user-invocable: true
---

Read the README.md file within this skill, and explore the other available files.

If creating visual artifacts (slides, mocks, throwaway prototypes, etc), copy assets out
and create static HTML files for the user to view. If working on production code, you can
copy assets and read the rules here to become an expert in designing with this brand.

Key files:
- `styles.css` — the single stylesheet to link; it `@import`s all tokens + fonts + component styles.
- `tokens/` — colors, typography, spacing, effects (radius/elevation/motion), fonts.
- `components/` — reusable React primitives (Button, IconButton, Input, Select, Card,
  Badge, StatusDot, Skeleton, Table, Dialog, KpiCard). Compiled to `window.IntelligentERPDesignSystem_b41ec1`.
- `ui_kits/erp-app/` — a full interactive recreation of the product (login, sidebar,
  topbar, command palette, dashboard, products, CRM, AI assistant). Best starting reference.
- `guidelines/` — foundation specimen cards.

Design essentials: dark-mode first, warm charcoal surfaces (never pure black/white), a
single **emerald** accent, Schibsted Grotesk (UI) + JetBrains Mono (data), 4px spacing
grid with generous breathing room, 12/16/22px radii, soft layered shadows + inner
hairline, Lucide line icons at stroke-width 1.75, calm sentence-case copy, and quiet
`ease-out` motion. No emoji in chrome, no hand-drawn icons.

If the user invokes this skill without any other guidance, ask them what they want to
build or design, ask some questions, and act as an expert designer who outputs HTML
artifacts _or_ production code, depending on the need.
