import {
  createContext,
  useCallback,
  useContext,
  useState,
  type ReactNode,
} from "react";

export type Theme = "dark" | "light" | "creme";

/** The order the toggle cycles through. */
export const THEME_ORDER: Theme[] = ["dark", "light", "creme"];

const STORAGE_KEY = "erp-theme";

function readStoredTheme(): Theme | null {
  try {
    const v = localStorage.getItem(STORAGE_KEY);
    return v === "dark" || v === "light" || v === "creme" ? v : null;
  } catch {
    return null;
  }
}

/** Stored choice wins; otherwise follow the OS. Dark is the design default. */
function resolveInitialTheme(): Theme {
  const stored = readStoredTheme();
  if (stored) return stored;
  return typeof window !== "undefined" &&
    window.matchMedia("(prefers-color-scheme: light)").matches
    ? "light"
    : "dark";
}

function writeTheme(theme: Theme) {
  document.documentElement.dataset.theme = theme;
  try {
    localStorage.setItem(STORAGE_KEY, theme);
  } catch {
    /* storage blocked — theme still applies for this session */
  }
}

/* Applied at module import (before React paints) so there is no flash. */
const INITIAL_THEME: Theme = resolveInitialTheme();
if (typeof document !== "undefined") {
  document.documentElement.dataset.theme = INITIAL_THEME;
}

interface ThemeState {
  theme: Theme;
  setTheme: (t: Theme) => void;
  toggle: () => void;
}

const ThemeContext = createContext<ThemeState | null>(null);

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setThemeState] = useState<Theme>(INITIAL_THEME);

  const setTheme = useCallback((t: Theme) => {
    setThemeState(t);
    writeTheme(t);
  }, []);

  const toggle = useCallback(() => {
    setThemeState((prev) => {
      // Cycle dark → light → creme → dark.
      const next = THEME_ORDER[(THEME_ORDER.indexOf(prev) + 1) % THEME_ORDER.length];
      writeTheme(next);
      return next;
    });
  }, []);

  return (
    <ThemeContext.Provider value={{ theme, setTheme, toggle }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme(): ThemeState {
  const ctx = useContext(ThemeContext);
  if (!ctx) throw new Error("useTheme must be used inside ThemeProvider");
  return ctx;
}
