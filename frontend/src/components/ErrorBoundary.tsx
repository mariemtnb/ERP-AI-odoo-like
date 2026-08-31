import { Component, type ReactNode } from "react";

/**
 * Catches render errors from a page so one broken screen shows a retry card
 * instead of unmounting the whole app to a blank page (which used to require a
 * full refresh to recover). Resets automatically when the route changes.
 */
interface Props {
  resetKey: string;
  children: ReactNode;
}
interface State {
  error: Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
  state: State = { error: null };

  static getDerivedStateFromError(error: Error): State {
    return { error };
  }

  componentDidUpdate(prev: Props) {
    // New route → clear the error and try rendering the new page.
    if (prev.resetKey !== this.props.resetKey && this.state.error) {
      this.setState({ error: null });
    }
  }

  render() {
    if (this.state.error) {
      return (
        <div
          style={{
            maxWidth: 520,
            margin: "10vh auto",
            padding: 28,
            background: "var(--surface)",
            border: "1px solid var(--border)",
            borderRadius: 16,
            textAlign: "center",
          }}
        >
          <div style={{ fontSize: 28, marginBottom: 8 }}>⚠️</div>
          <h2 style={{ margin: "0 0 6px", color: "var(--text-strong)", font: "600 20px var(--font-sans)" }}>
            This page hit a snag
          </h2>
          <p style={{ color: "var(--text-muted)", fontSize: 14, margin: "0 0 18px" }}>
            Something went wrong while loading this screen. You can retry, or switch to another page from the menu.
          </p>
          <button
            onClick={() => this.setState({ error: null })}
            style={{
              height: 38,
              padding: "0 18px",
              borderRadius: 10,
              border: 0,
              cursor: "pointer",
              background: "var(--emerald-500)",
              color: "var(--text-on-accent)",
              font: "600 14px var(--font-sans)",
            }}
          >
            Retry
          </button>
        </div>
      );
    }
    return this.props.children;
  }
}
