/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{ts,tsx}"],
  theme: {
    extend: {
      fontFamily: {
        sans: ["Schibsted Grotesk", "ui-sans-serif", "system-ui", "sans-serif"],
        mono: ["JetBrains Mono", "ui-monospace", "SF Mono", "Menlo", "monospace"],
      },
      colors: {
        // HSL channel layer (alpha-capable) — values track the DS palette.
        bg: "hsl(var(--bg) / <alpha-value>)",
        surface: {
          DEFAULT: "hsl(var(--surface) / <alpha-value>)", // charcoal-850 card
          2: "hsl(var(--surface-2) / <alpha-value>)", // charcoal-925 inset
          3: "hsl(var(--surface-3) / <alpha-value>)", // charcoal-800 hover
        },
        stroke: {
          DEFAULT: "hsl(var(--stroke) / <alpha-value>)",
          soft: "hsl(var(--stroke-soft) / <alpha-value>)",
        },
        text: {
          DEFAULT: "hsl(var(--text) / <alpha-value>)", // strong
          2: "hsl(var(--text-2) / <alpha-value>)", // muted
          3: "hsl(var(--text-3) / <alpha-value>)", // faint
        },
        accent: {
          DEFAULT: "hsl(var(--accent) / <alpha-value>)", // emerald-500
          soft: "hsl(var(--accent-soft) / <alpha-value>)", // emerald-300
          strong: "hsl(var(--accent-strong) / <alpha-value>)", // emerald-400
        },
        positive: "hsl(var(--positive) / <alpha-value>)",
        warning: "hsl(var(--warning) / <alpha-value>)",
        danger: "hsl(var(--danger) / <alpha-value>)",
        info: "hsl(var(--info) / <alpha-value>)",
        violet: "hsl(var(--violet) / <alpha-value>)",

        // DS aliases (opaque, exact hex via vars) for pixel-accurate work.
        app: "var(--bg-app)",
        panel: "var(--bg-panel)",
        card: "var(--surface-card)",
        hover: "var(--surface-hover)",
        inset: "var(--surface-inset)",
        body: "var(--text-body)",
        "on-accent": "var(--text-on-accent)",
      },
      borderRadius: {
        // effects.css radius scale
        xs: "6px",
        sm: "8px",
        md: "12px", // inputs, buttons
        lg: "16px", // cards
        xl: "22px", // panels, modals
        "2xl": "28px",
      },
      boxShadow: {
        // effects.css elevation (legacy keys 1/2/3 kept for migrated screens)
        xs: "var(--shadow-xs)",
        sm: "var(--shadow-sm)",
        md: "var(--shadow-md)",
        lg: "var(--shadow-lg)",
        xl: "var(--shadow-xl)",
        1: "var(--shadow-xs)",
        2: "var(--shadow-sm)",
        3: "var(--shadow-lg)",
        "accent-glow": "var(--shadow-accent)",
        hairline: "var(--hairline)",
        "ring-focus": "var(--ring-focus)",
      },
      transitionTimingFunction: {
        out: "cubic-bezier(0.16, 1, 0.3, 1)", // --ease-out signature
        spring: "cubic-bezier(0.34, 1.56, 0.64, 1)", // --ease-spring
      },
      transitionDuration: {
        120: "120ms",
        200: "200ms",
        320: "320ms",
        480: "480ms",
      },
      letterSpacing: {
        display: "-0.03em",
        snug: "-0.015em",
        caps: "0.14em",
      },
      maxWidth: {
        content: "1240px", // --content-max
      },
      keyframes: {
        shimmer: {
          "100%": { transform: "translateX(100%)" },
        },
        "fade-up": {
          from: { opacity: "0", transform: "translateY(8px)" },
          to: { opacity: "1", transform: "translateY(0)" },
        },
        "erp-pop": {
          from: { opacity: "0", transform: "scale(0.96) translateY(10px)" },
          to: { opacity: "1", transform: "scale(1) translateY(0)" },
        },
      },
      animation: {
        shimmer: "shimmer 1.6s infinite",
        "fade-up": "fade-up 320ms cubic-bezier(0.16, 1, 0.3, 1) both",
        "erp-pop": "erp-pop 320ms cubic-bezier(0.34, 1.56, 0.64, 1) both",
      },
    },
  },
  plugins: [],
};
