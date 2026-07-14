**Button** — the primary action element. Use for any user-triggered action; one primary per view, the rest quiet.

```jsx
<Button variant="primary" icon={<PlusIcon/>}>New product</Button>
<Button variant="secondary">Cancel</Button>
<Button variant="outline" size="sm">Filter</Button>
<Button variant="danger" loading>Deleting…</Button>
```

Variants: `primary` (emerald), `secondary`, `ghost`, `outline`, `danger`. Sizes: `sm|md|lg`. Pass `loading` for the spinner state, `icon`/`iconRight` for glyphs.
