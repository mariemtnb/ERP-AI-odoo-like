/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{ts,tsx}"],
  theme: {
    extend: {
      colors: {
        bg: "hsl(var(--bg) / <alpha-value>)",
        surface: {
          DEFAULT: "hsl(var(--surface) / <alpha-value>)",
          2: "hsl(var(--surface-2) / <alpha-value>)",
          3: "hsl(var(--surface-3) / <alpha-value>)",
        },
        stroke: {
          DEFAULT: "hsl(var(--stroke) / <alpha-value>)",
          soft: "hsl(var(--stroke-soft) / <alpha-value>)",
        },
        text: {
          DEFAULT: "hsl(var(--text) / <alpha-value>)",
          2: "hsl(var(--text-2) / <alpha-value>)",
          3: "hsl(var(--text-3) / <alpha-value>)",
        },
        accent: {
          DEFAULT: "hsl(var(--accent) / <alpha-value>)",
          soft: "hsl(var(--accent-soft) / <alpha-value>)",
          strong: "hsl(var(--accent-strong) / <alpha-value>)",
        },
        positive: "hsl(var(--positive) / <alpha-value>)",
        warning: "hsl(var(--warning) / <alpha-value>)",
        danger: "hsl(var(--danger) / <alpha-value>)",
        info: "hsl(var(--info) / <alpha-value>)",
      },
      borderRadius: {
        md: "10px",
        lg: "14px",
        xl: "20px",
        "2xl": "28px",
      },
      boxShadow: {
        // elevation scale: whisper → speak → shout
        1: "0 1px 2px 0 rgb(0 0 0 / 0.25)",
        2: "0 4px 16px -4px rgb(0 0 0 / 0.35)",
        3: "0 16px 40px -12px rgb(0 0 0 / 0.5)",
        "accent-glow": "0 0 24px -6px hsl(var(--accent) / 0.45)",
      },
      transitionTimingFunction: {
        out: "cubic-bezier(0.22, 1, 0.36, 1)",
      },
      keyframes: {
        shimmer: {
          "100%": { transform: "translateX(100%)" },
        },
        "fade-up": {
          from: { opacity: "0", transform: "translateY(8px)" },
          to: { opacity: "1", transform: "translateY(0)" },
        },
      },
      animation: {
        shimmer: "shimmer 1.6s infinite",
        "fade-up": "fade-up 340ms cubic-bezier(0.22, 1, 0.36, 1) both",
      },
    },
  },
  plugins: [],
};
