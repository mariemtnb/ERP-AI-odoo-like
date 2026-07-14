// ERP kit shell: login, sidebar, topbar, command palette, router.
const { Icon, Btn, Badge } = window.ERP_UI;
const { Dashboard, Products, Crm, Assistant, Inventory, Placeholder, Conversation } = window.ERP_PAGES;
const { Partners, Documents, Reports, Users } = window.ERP_MODULES;
const DAT = window.ERP_DATA;

function Login({ onLogin }) {
  const [email, setEmail] = React.useState("admin@erp.local");
  const [pw, setPw] = React.useState("Admin123!");
  return (
    <div style={{ minHeight: "100vh", display: "grid", gridTemplateColumns: "1fr 1fr", background: "var(--bg-app)" }}>
      <div style={{ position: "relative", overflow: "hidden", borderRight: "1px solid var(--border-subtle)", background: "radial-gradient(120% 100% at 0% 0%, color-mix(in oklab, var(--emerald-500) 14%, var(--bg-app)), var(--bg-app) 60%)", padding: 56, display: "flex", flexDirection: "column", justifyContent: "space-between" }}>
        <Brand />
        <div>
          <h1 style={{ margin: 0, font: "600 44px/1.05 var(--font-sans)", letterSpacing: "-.035em", color: "var(--text-strong)", maxWidth: 440 }}>The ERP that thinks alongside you.</h1>
          <p style={{ margin: "20px 0 0", font: "400 16px/1.6 var(--font-sans)", color: "var(--text-muted)", maxWidth: 400 }}>Inventory, sales, purchasing and CRM — with a conversational AI agent that queries your data and acts, with your approval.</p>
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: 10, font: "400 13px/1 var(--font-sans)", color: "var(--text-faint)" }}>
          <span style={{ width: 7, height: 7, borderRadius: 999, background: "var(--emerald-400)" }} /> Local model online · your data never leaves your servers
        </div>
      </div>
      <div style={{ display: "grid", placeItems: "center", padding: 40 }}>
        <div style={{ width: "100%", maxWidth: 360 }}>
          <h2 style={{ margin: 0, font: "600 26px/1 var(--font-sans)", letterSpacing: "-.02em", color: "var(--text-strong)" }}>Sign in</h2>
          <p style={{ margin: "8px 0 28px", font: "400 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>Welcome back to your workspace.</p>
          <form onSubmit={(e) => { e.preventDefault(); onLogin(); }} style={{ display: "flex", flexDirection: "column", gap: 16 }}>
            <Field label="Email"><input className="erp-field" value={email} onChange={(e) => setEmail(e.target.value)} /></Field>
            <Field label="Password"><input className="erp-field" type="password" value={pw} onChange={(e) => setPw(e.target.value)} /></Field>
            <button className="erp-btn erp-btn--primary erp-btn--lg" style={{ marginTop: 6, width: "100%" }} type="submit">Sign in <Icon name="arrow-right" size={16} /></button>
          </form>
          <p style={{ margin: "20px 0 0", font: "400 12px/1.5 var(--font-mono)", color: "var(--text-faint)" }}>Demo · admin@erp.local / Admin123!</p>
        </div>
      </div>
    </div>
  );
}
function Field({ label, children }) {
  return <label style={{ display: "flex", flexDirection: "column", gap: 8 }}><span style={{ font: "500 13px/1 var(--font-sans)", color: "var(--text-body)" }}>{label}</span>{children}</label>;
}
function Brand({ small }) {
  return (
    <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
      <div style={{ width: small ? 30 : 36, height: small ? 30 : 36, borderRadius: 10, background: "var(--emerald-500)", display: "grid", placeItems: "center", color: "var(--text-on-accent)", boxShadow: "var(--shadow-accent)" }}><Icon name="hexagon" size={small ? 17 : 20} /></div>
      <span style={{ font: "600 17px/1 var(--font-sans)", letterSpacing: "-.02em", color: "var(--text-strong)" }}>Intelligent<span style={{ color: "var(--emerald-400)" }}>ERP</span></span>
    </div>
  );
}

function Sidebar({ page, setPage, collapsed, setCollapsed }) {
  const w = collapsed ? "var(--sidebar-w-collapsed)" : "var(--sidebar-w)";
  return (
    <aside style={{ width: w, flex: "none", display: "flex", flexDirection: "column", background: "var(--bg-panel)", borderRight: "1px solid var(--border-subtle)", transition: "width var(--dur-3) var(--ease-out)", overflow: "hidden" }}>
      <div style={{ height: "var(--topbar-h)", display: "flex", alignItems: "center", justifyContent: collapsed ? "center" : "space-between", padding: collapsed ? 0 : "0 18px", borderBottom: "1px solid var(--border-subtle)" }}>
        {collapsed ? <div style={{ width: 32, height: 32, borderRadius: 9, background: "var(--emerald-500)", display: "grid", placeItems: "center", color: "var(--text-on-accent)" }}><Icon name="hexagon" size={18} /></div> : <Brand small />}
        {!collapsed && <button className="erp-iconbtn erp-iconbtn--sm" onClick={() => setCollapsed(true)} aria-label="collapse"><Icon name="panel-left-close" size={16} /></button>}
      </div>
      {collapsed && <button className="erp-iconbtn erp-iconbtn--md" onClick={() => setCollapsed(false)} aria-label="expand" style={{ margin: "10px auto 0" }}><Icon name="panel-left-open" size={16} /></button>}
      <nav style={{ flex: 1, padding: collapsed ? "10px 12px" : "12px 12px", display: "flex", flexDirection: "column", gap: 2, overflowY: "auto" }}>
        {!collapsed && <div className="ds-eyebrow" style={{ padding: "10px 10px 6px" }}>Workspace</div>}
        {DAT.nav.map((n) => {
          const active = page === n.to;
          return (
            <button key={n.to} onClick={() => setPage(n.to)} title={n.label}
              style={{ display: "flex", alignItems: "center", gap: 12, justifyContent: collapsed ? "center" : "flex-start", padding: collapsed ? "10px 0" : "9px 10px", borderRadius: 10, border: "none", cursor: "pointer", position: "relative",
                background: active ? "var(--surface-hover)" : "transparent", color: active ? "var(--text-strong)" : "var(--text-muted)",
                font: "500 14px/1 var(--font-sans)", transition: "background var(--dur-1), color var(--dur-1)" }}
              onMouseEnter={(e) => { if (!active) e.currentTarget.style.background = "color-mix(in oklab, var(--surface-hover) 55%, transparent)"; }}
              onMouseLeave={(e) => { if (!active) e.currentTarget.style.background = "transparent"; }}>
              {active && !collapsed && <span style={{ position: "absolute", left: 0, top: 8, bottom: 8, width: 3, borderRadius: 999, background: "var(--emerald-400)" }} />}
              <Icon name={n.icon} size={18} color={active ? "var(--emerald-400)" : undefined} />
              {!collapsed && <span>{n.label}</span>}
              {!collapsed && n.to === "assistant" && <span style={{ marginLeft: "auto", width: 6, height: 6, borderRadius: 999, background: "var(--emerald-400)" }} />}
            </button>
          );
        })}
      </nav>
      <div style={{ padding: 12, borderTop: "1px solid var(--border-subtle)" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, padding: collapsed ? 0 : "6px 8px", justifyContent: collapsed ? "center" : "flex-start" }}>
          <div style={{ width: 34, height: 34, borderRadius: 999, background: "linear-gradient(135deg,var(--emerald-500),var(--emerald-700))", display: "grid", placeItems: "center", color: "var(--text-on-accent)", font: "600 13px/1 var(--font-sans)", flex: "none" }}>AK</div>
          {!collapsed && <div style={{ minWidth: 0 }}><div style={{ font: "500 13px/1.2 var(--font-sans)", color: "var(--text-strong)", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>Amine Khelifi</div><div style={{ font: "400 11px/1 var(--font-sans)", color: "var(--text-faint)", textTransform: "uppercase", letterSpacing: ".1em", marginTop: 3 }}>Admin</div></div>}
        </div>
      </div>
    </aside>
  );
}

function Topbar({ onOpenCmd, onLogout, onAskAi }) {
  return (
    <header style={{ height: "var(--topbar-h)", flex: "none", display: "flex", alignItems: "center", gap: 16, padding: "0 24px", borderBottom: "1px solid var(--border-subtle)", background: "color-mix(in oklab, var(--bg-app) 82%, transparent)", backdropFilter: "blur(12px)", position: "sticky", top: 0, zIndex: 20 }}>
      <button onClick={onOpenCmd} style={{ display: "flex", alignItems: "center", gap: 10, width: 340, maxWidth: "40vw", height: 38, padding: "0 12px", borderRadius: 10, background: "var(--surface-inset)", border: "1px solid var(--border)", color: "var(--text-faint)", cursor: "pointer", font: "400 13px/1 var(--font-sans)" }}>
        <Icon name="search" size={16} /> Search or ask anything…
        <span style={{ marginLeft: "auto", font: "500 11px/1 var(--font-mono)", background: "var(--surface-hover)", padding: "3px 6px", borderRadius: 6 }}>⌘K</span>
      </button>
      <div style={{ marginLeft: "auto", display: "flex", alignItems: "center", gap: 8 }}>
        <button className="erp-btn erp-btn--secondary erp-btn--sm" onClick={onAskAi}><Icon name="sparkles" size={15} color="var(--emerald-400)" /> Ask AI</button>
        <button className="erp-iconbtn erp-iconbtn--md" aria-label="notifications" style={{ position: "relative" }}><Icon name="bell" size={18} /><span style={{ position: "absolute", top: 8, right: 8, width: 7, height: 7, borderRadius: 999, background: "var(--rose-400)", border: "2px solid var(--bg-app)" }} /></button>
        <button className="erp-iconbtn erp-iconbtn--md" aria-label="theme"><Icon name="moon" size={18} /></button>
        <button className="erp-iconbtn erp-iconbtn--md" onClick={onLogout} aria-label="sign out"><Icon name="log-out" size={18} /></button>
      </div>
    </header>
  );
}

const CMD_ITEMS = [
  { icon: "plus", label: "New product", hint: "Create" },
  { icon: "user-plus", label: "New lead", hint: "Create" },
  { icon: "sparkles", label: "Ask the AI assistant", hint: "AI" },
  { icon: "package", label: "Go to Products", hint: "Navigate" },
  { icon: "contact", label: "Go to CRM", hint: "Navigate" },
  { icon: "file-text", label: "Export monthly report", hint: "Action" },
];
function CommandPalette({ open, onClose }) {
  if (!open) return null;
  return (
    <div className="erp-dialog-backdrop" style={{ alignItems: "flex-start", paddingTop: "14vh" }} onClick={onClose}>
      <div className="erp-dialog" style={{ maxWidth: 560 }} onClick={(e) => e.stopPropagation()}>
        <div style={{ display: "flex", alignItems: "center", gap: 10, padding: "16px 18px", borderBottom: "1px solid var(--border-subtle)" }}>
          <Icon name="search" size={18} color="var(--text-muted)" />
          <input autoFocus className="erp-field" style={{ border: "none", background: "transparent", height: 24, padding: 0, fontSize: 15 }} placeholder="Type a command or search…" />
          <span style={{ font: "500 11px/1 var(--font-mono)", color: "var(--text-faint)", background: "var(--surface-hover)", padding: "4px 7px", borderRadius: 6 }}>ESC</span>
        </div>
        <div style={{ padding: 8 }}>
          <div className="ds-eyebrow" style={{ padding: "8px 10px 6px" }}>Quick actions</div>
          {CMD_ITEMS.map((c) => (
            <button key={c.label} onClick={onClose} style={{ width: "100%", display: "flex", alignItems: "center", gap: 12, padding: "10px 10px", borderRadius: 10, border: "none", background: "transparent", cursor: "pointer", color: "var(--text-body)", font: "500 14px/1 var(--font-sans)" }}
              onMouseEnter={(e) => e.currentTarget.style.background = "var(--surface-hover)"} onMouseLeave={(e) => e.currentTarget.style.background = "transparent"}>
              <span style={{ width: 30, height: 30, borderRadius: 8, background: "var(--surface-hover)", display: "grid", placeItems: "center", color: "var(--text-muted)" }}><Icon name={c.icon} size={16} /></span>
              {c.label}
              <span style={{ marginLeft: "auto", font: "500 11px/1 var(--font-sans)", color: "var(--text-faint)", textTransform: "uppercase", letterSpacing: ".08em" }}>{c.hint}</span>
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}

function FloatingAssistant({ mode, setMode }) {
  const open = mode !== "closed";
  const full = mode === "full";
  React.useEffect(() => {
    const h = (e) => { if (e.key === "Escape" && open) setMode("closed"); };
    window.addEventListener("keydown", h); return () => window.removeEventListener("keydown", h);
  }, [open]);

  if (!open) {
    return (
      <button className="erp-fab" onClick={() => setMode("dock")} aria-label="Open AI assistant"
        style={{ position: "fixed", right: 28, bottom: 28, zIndex: 60, width: 58, height: 58, borderRadius: 999, border: "none", cursor: "pointer",
          background: "linear-gradient(135deg, var(--emerald-400), var(--emerald-600))", color: "var(--text-on-accent)", display: "grid", placeItems: "center",
          boxShadow: "0 10px 34px var(--emerald-glow), var(--hairline)" }}>
        <Icon name="sparkles" size={24} />
      </button>
    );
  }

  const shell = full
    ? { position: "fixed", inset: 0, zIndex: 60, borderRadius: 0, border: "none", animation: "full-in var(--dur-3) var(--ease-out)" }
    : { position: "fixed", right: 24, bottom: 24, zIndex: 60, width: "min(420px, calc(100vw - 32px))", height: "min(640px, calc(100vh - 48px))", borderRadius: "var(--radius-xl)", border: "1px solid var(--border)", animation: "dock-in var(--dur-3) var(--ease-out)" };

  const body = (
    <div style={{ ...shell, background: "var(--surface-card)", boxShadow: "var(--shadow-xl), var(--hairline)", display: "flex", flexDirection: "column", overflow: "hidden" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 12, padding: "14px 16px", borderBottom: "1px solid var(--border-subtle)", flex: "none" }}>
        <div style={{ width: 34, height: 34, borderRadius: 10, background: "var(--emerald-glow)", display: "grid", placeItems: "center", color: "var(--emerald-400)", flex: "none" }}><Icon name="sparkles" size={18} /></div>
        <div style={{ minWidth: 0 }}>
          <div style={{ font: "600 15px/1 var(--font-sans)", color: "var(--text-strong)" }}>AI Assistant</div>
          <span style={{ display: "inline-flex", alignItems: "center", gap: 6, font: "400 11px/1 var(--font-sans)", color: "var(--text-muted)", marginTop: 4 }}>
            <span style={{ width: 5, height: 5, borderRadius: 999, background: "var(--emerald-400)" }} /> Approval-first · local model
          </span>
        </div>
        <div style={{ marginLeft: "auto", display: "flex", gap: 2 }}>
          <button className="erp-iconbtn erp-iconbtn--sm" onClick={() => setMode(full ? "dock" : "full")} aria-label={full ? "Exit fullscreen" : "Fullscreen"}><Icon name={full ? "minimize-2" : "maximize-2"} size={16} /></button>
          <button className="erp-iconbtn erp-iconbtn--sm" onClick={() => setMode("closed")} aria-label="Close assistant"><Icon name="x" size={16} /></button>
        </div>
      </div>
      <div style={{ flex: 1, minHeight: 0, display: "flex", flexDirection: "column", padding: full ? "20px" : "14px", maxWidth: full ? 860 : "none", width: "100%", margin: full ? "0 auto" : 0 }}>
        <Conversation compact={!full} />
      </div>
    </div>
  );

  if (full) return <div className="erp-dialog-backdrop" style={{ padding: 0, alignItems: "stretch", justifyContent: "stretch" }}>{body}</div>;
  return body;
}

function App() {
  const [authed, setAuthed] = React.useState(true);
  const [page, setPage] = React.useState("dashboard");
  const [collapsed, setCollapsed] = React.useState(false);
  const [cmd, setCmd] = React.useState(false);
  const [ai, setAi] = React.useState("closed");
  React.useEffect(() => {
    const h = (e) => { if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") { e.preventDefault(); setCmd((v) => !v); } if (e.key === "Escape") setCmd(false); };
    window.addEventListener("keydown", h); return () => window.removeEventListener("keydown", h);
  }, []);
  if (!authed) return <Login onLogin={() => setAuthed(true)} />;
  const PAGES = { dashboard: <Dashboard />, products: <Products />, crm: <Crm />, assistant: <Assistant />, inventory: <Inventory />,
    customers: <Partners kind="customers" />, suppliers: <Partners kind="suppliers" />, purchases: <Documents kind="purchases" />, sales: <Documents kind="sales" />, reports: <Reports />, users: <Users /> };
  const labels = {};
  const content = PAGES[page] || <Placeholder label={labels[page] || "Module"} />;
  return (
    <div style={{ display: "flex", minHeight: "100vh", background: "var(--bg-app)" }}>
      <Sidebar page={page} setPage={setPage} collapsed={collapsed} setCollapsed={setCollapsed} />
      <div style={{ flex: 1, minWidth: 0, display: "flex", flexDirection: "column" }}>
        <Topbar onOpenCmd={() => setCmd(true)} onLogout={() => setAuthed(false)} onAskAi={() => setAi("dock")} />
        <main style={{ flex: 1, padding: "36px 40px", maxWidth: 1320, width: "100%", margin: "0 auto" }} key={page} className="erp-page-enter">{content}</main>
      </div>
      <CommandPalette open={cmd} onClose={() => setCmd(false)} />
      {page !== "assistant" && <FloatingAssistant mode={ai} setMode={setAi} />}
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
