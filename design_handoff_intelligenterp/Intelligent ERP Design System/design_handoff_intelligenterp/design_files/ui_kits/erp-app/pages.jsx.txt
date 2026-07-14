// ERP kit pages: Dashboard, Products, CRM, Assistant.
const { Icon, Btn, Badge, Spark, Kpi, PageHead } = window.ERP_UI;
const D = window.ERP_DATA;

function SectionCard({ title, action, children }) {
  return (
    <div className="erp-card" style={{ display: "flex", flexDirection: "column" }}>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "18px 22px", borderBottom: "1px solid var(--border-subtle)" }}>
        <h3 style={{ margin: 0, font: "600 16px/1 var(--font-sans)", letterSpacing: "-.01em", color: "var(--text-strong)" }}>{title}</h3>
        {action}
      </div>
      <div style={{ padding: "8px 22px 14px" }}>{children}</div>
    </div>
  );
}

function Dashboard() {
  return (
    <div>
      <PageHead title="Good afternoon, Amine" sub="Here's what's moving across your business today.">
        <Btn variant="outline" size="md" icon="calendar">This month</Btn>
        <Btn variant="primary" size="md" icon="sparkles">Ask AI</Btn>
      </PageHead>

      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))", gap: 16, marginBottom: 20 }}>
        {D.kpis.map((k) => <Kpi key={k.label} {...k} />)}
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))", gap: 16, marginBottom: 20 }}>
        <div className="erp-card" style={{ padding: 22 }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: 18 }}>
            <div>
              <span className="ds-eyebrow">Revenue trend</span>
              <div style={{ font: "600 26px/1 var(--font-sans)", letterSpacing: "-.03em", color: "var(--text-strong)", marginTop: 8, fontVariantNumeric: "tabular-nums" }}>48,250 <span style={{ font: "500 14px/1 var(--font-sans)", color: "var(--text-muted)" }}>TND</span></div>
            </div>
            <Badge tone="emerald" dot>+12.4%</Badge>
          </div>
          <Spark data={D.revenueSeries} up fill w={640} h={150} />
        </div>

        <div className="erp-card" style={{ padding: 0, background: "linear-gradient(160deg, color-mix(in oklab, var(--emerald-500) 12%, var(--surface-card)), var(--surface-card))", position: "relative", overflow: "hidden" }}>
          <div style={{ padding: 22, display: "flex", flexDirection: "column", gap: 14, height: "100%" }}>
            <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <div style={{ width: 34, height: 34, borderRadius: 10, background: "var(--emerald-glow)", display: "grid", placeItems: "center", color: "var(--emerald-400)" }}><Icon name="sparkles" size={18} /></div>
              <span style={{ font: "600 15px/1 var(--font-sans)", color: "var(--text-strong)" }}>AI insight</span>
            </div>
            <p style={{ margin: 0, font: "400 14px/1.55 var(--font-sans)", color: "var(--text-body)" }}>
              Copper coil demand is up <b style={{ color: "var(--emerald-400)" }}>34%</b> this week while stock covers only ~9 days. Consider a purchase order to avoid a stockout before month-end.
            </p>
            <div style={{ marginTop: "auto", display: "flex", gap: 8 }}>
              <Btn variant="primary" size="sm" icon="check">Draft PO</Btn>
              <Btn variant="ghost" size="sm">Dismiss</Btn>
            </div>
          </div>
        </div>
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
        <SectionCard title="Top products" action={<Btn variant="ghost" size="sm" iconRight="arrow-right">View all</Btn>}>
          {D.topProducts.map((p) => (
            <div key={p.sku} style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "12px 0", borderBottom: "1px solid var(--border-subtle)" }}>
              <div style={{ display: "flex", flexDirection: "column", gap: 3 }}>
                <span style={{ font: "500 14px/1 var(--font-sans)", color: "var(--text-strong)" }}>{p.name}</span>
                <span style={{ font: "400 12px/1 var(--font-mono)", color: "var(--text-faint)" }}>{p.sku} · {p.sold} sold</span>
              </div>
              <span style={{ font: "500 14px/1 var(--font-mono)", color: "var(--emerald-400)" }}>{p.revenue}</span>
            </div>
          ))}
        </SectionCard>

        <SectionCard title="Low stock" action={<Badge tone="rose">{D.lowStock.length} items</Badge>}>
          {D.lowStock.map((p) => {
            const pct = Math.min(100, (p.qty / p.min) * 100);
            return (
              <div key={p.sku} style={{ padding: "12px 0", borderBottom: "1px solid var(--border-subtle)" }}>
                <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 8 }}>
                  <span style={{ font: "500 14px/1 var(--font-sans)", color: "var(--text-strong)" }}>{p.name}</span>
                  <span style={{ font: "500 13px/1 var(--font-mono)", color: "var(--rose-400)" }}>{p.qty} / {p.min}</span>
                </div>
                <div style={{ height: 5, borderRadius: 999, background: "var(--surface-hover)", overflow: "hidden" }}>
                  <div style={{ width: pct + "%", height: "100%", borderRadius: 999, background: "var(--rose-400)" }} />
                </div>
              </div>
            );
          })}
        </SectionCard>
      </div>
    </div>
  );
}

function Products() {
  const [q, setQ] = React.useState("");
  const rows = D.products.filter((p) => (p.name + p.sku).toLowerCase().includes(q.toLowerCase()));
  return (
    <div>
      <PageHead title="Products" sub={`${D.products.length} items in your catalog`}>
        <Btn variant="outline" size="md" icon="folder">Categories</Btn>
        <Btn variant="primary" size="md" icon="plus">New product</Btn>
      </PageHead>
      <div style={{ display: "flex", gap: 12, marginBottom: 18 }}>
        <div style={{ position: "relative", maxWidth: 320, width: "100%" }}>
          <span style={{ position: "absolute", left: 12, top: "50%", transform: "translateY(-50%)", color: "var(--text-faint)", display: "flex" }}><Icon name="search" size={16} /></span>
          <input className="erp-field" style={{ paddingLeft: 38 }} placeholder="Search by SKU or name…" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <button className="erp-btn erp-btn--outline erp-btn--md"><Icon name="sliders-horizontal" size={16} />Filters</button>
      </div>
      <div style={{ width: "100%", overflow: "hidden", borderRadius: "var(--radius-lg)", border: "1px solid var(--border-subtle)", background: "var(--surface-card)", boxShadow: "var(--shadow-sm), var(--hairline)" }}>
        <table className="erp-table">
          <thead><tr><th>SKU</th><th>Name</th><th>Category</th><th style={{ textAlign: "right" }}>Stock</th><th style={{ textAlign: "right" }}>Sale price</th><th>Status</th><th></th></tr></thead>
          <tbody>
            {rows.map((p) => (
              <tr key={p.sku} style={{ opacity: p.active ? 1 : 0.5 }}>
                <td style={{ fontFamily: "var(--font-mono)", fontSize: 13, color: "var(--text-muted)" }}>{p.sku}</td>
                <td style={{ color: "var(--text-strong)", fontWeight: 500 }}>{p.name}</td>
                <td>{p.cat}</td>
                <td style={{ textAlign: "right", fontFamily: "var(--font-mono)" }}>
                  {p.stock} {p.unit} {p.low && <span style={{ marginLeft: 6 }}><Badge tone="rose">low</Badge></span>}
                </td>
                <td style={{ textAlign: "right", fontFamily: "var(--font-mono)" }}>{p.price}</td>
                <td><Badge tone={p.active ? "emerald" : "neutral"} dot>{p.active ? "active" : "inactive"}</Badge></td>
                <td style={{ textAlign: "right" }}>
                  <span style={{ display: "inline-flex", gap: 2 }}>
                    <button className="erp-iconbtn erp-iconbtn--sm" aria-label="edit"><Icon name="pencil" size={15} /></button>
                    <button className="erp-iconbtn erp-iconbtn--sm" aria-label="archive"><Icon name="trash-2" size={15} /></button>
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

const CRM_COLS = [
  { k: "new", label: "New", tone: "neutral" },
  { k: "contacted", label: "Contacted", tone: "amber" },
  { k: "qualified", label: "Qualified", tone: "sky" },
  { k: "won", label: "Won", tone: "emerald" },
  { k: "lost", label: "Lost", tone: "rose" },
];
function Crm() {
  return (
    <div>
      <PageHead title="CRM" sub="Prospect pipeline — 7 active leads">
        <Btn variant="primary" size="md" icon="plus">New lead</Btn>
      </PageHead>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(5,1fr)", gap: 14, alignItems: "start" }}>
        {CRM_COLS.map((c) => {
          const items = D.leads[c.k] || [];
          return (
            <div key={c.k} style={{ display: "flex", flexDirection: "column", gap: 12 }}>
              <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 2px" }}>
                <Badge tone={c.tone} dot>{c.label}</Badge>
                <span style={{ font: "500 12px/1 var(--font-mono)", color: "var(--text-faint)" }}>{items.length}</span>
              </div>
              {items.map((l) => (
                <div key={l.id} className="erp-card erp-card--hover" style={{ padding: 14, cursor: "pointer", display: "flex", flexDirection: "column", gap: 8 }}>
                  <span style={{ font: "500 14px/1.2 var(--font-sans)", color: "var(--text-strong)" }}>{l.name}</span>
                  {l.company !== "—" && <span style={{ font: "400 12px/1 var(--font-sans)", color: "var(--text-muted)" }}>{l.company}</span>}
                  <span style={{ display: "inline-flex", alignItems: "center", gap: 6, font: "400 12px/1 var(--font-mono)", color: "var(--text-faint)" }}><Icon name="phone" size={12} /> {l.phone}</span>
                </div>
              ))}
            </div>
          );
        })}
      </div>
    </div>
  );
}

const PROMPTS = ["Which products are low on stock?", "Create a customer named Ahmed Ben Ali", "What's this month's revenue?", "Top 5 products by margin"];
const CONV = [
  { role: "user", content: "Which products are low on stock right now?" },
  { role: "assistant", tools: ["list_products"], content: "Three products are below their minimum level:\n\n• Brass fitting 1/2\" — 3 / 30\n• Steel hinge 60mm — 8 / 25\n• PVC elbow joint — 14 / 40\n\nThe brass fitting is critical. Want me to draft a purchase order to the default supplier?" },
  { role: "user", content: "Yes, draft it for 50 units." },
  { role: "assistant", tools: ["create_purchase_order"], pending: true, content: "I've prepared a purchase order — please review and approve." },
];
// Reusable conversation. `compact` tightens spacing for the dock; `promptCols` wraps chips.
function Conversation({ compact }) {
  const [msgs, setMsgs] = React.useState(CONV);
  const [input, setInput] = React.useState("");
  const scroller = React.useRef(null);
  const send = (t) => { if (!t.trim()) return; setMsgs((m) => [...m, { role: "user", content: t }]); setInput(""); };
  React.useEffect(() => { if (scroller.current) scroller.current.scrollTop = scroller.current.scrollHeight; }, [msgs]);
  return (
    <React.Fragment>
      <div ref={scroller} style={{ flex: 1, overflowY: "auto", overflowX: "hidden", padding: compact ? "14px 4px" : "20px 4px", display: "flex", flexDirection: "column", gap: compact ? 14 : 18 }}>
        {msgs.map((m, i) => <Bubble key={i} m={m} compact={compact} />)}
      </div>
      <div style={{ display: "flex", gap: 8, flexWrap: "wrap", margin: compact ? "0 0 10px" : "0 0 12px" }}>
        {(compact ? PROMPTS.slice(0, 2) : PROMPTS).map((p) => (
          <button key={p} onClick={() => send(p)} style={{ background: "var(--surface-card)", border: "1px solid var(--border)", color: "var(--text-body)", borderRadius: 999, padding: "7px 14px", font: "500 12px/1 var(--font-sans)", cursor: "pointer", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis", maxWidth: compact ? 190 : "none" }}>{p}</button>
        ))}
      </div>
      <div style={{ display: "flex", gap: 10 }}>
        <div style={{ position: "relative", flex: 1 }}>
          <input className="erp-field" style={{ height: 48, paddingRight: 46 }} placeholder="Ask about your data, or ask it to act…" value={input} onChange={(e) => setInput(e.target.value)} onKeyDown={(e) => e.key === "Enter" && send(input)} />
          <button className="erp-iconbtn erp-iconbtn--sm" style={{ position: "absolute", right: 8, top: 8, color: "var(--text-muted)" }} aria-label="mic"><Icon name="mic" size={16} /></button>
        </div>
        <button className="erp-btn erp-btn--primary erp-btn--lg" onClick={() => send(input)} aria-label="send"><Icon name="arrow-up" size={18} /></button>
      </div>
    </React.Fragment>
  );
}

function Assistant() {
  return (
    <div style={{ display: "flex", flexDirection: "column", height: "calc(100vh - var(--topbar-h) - 80px)" }}>
      <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 8 }}>
        <div style={{ width: 40, height: 40, borderRadius: 12, background: "var(--emerald-glow)", display: "grid", placeItems: "center", color: "var(--emerald-400)" }}><Icon name="sparkles" size={20} /></div>
        <div>
          <h1 style={{ margin: 0, font: "600 22px/1 var(--font-sans)", letterSpacing: "-.02em", color: "var(--text-strong)" }}>AI Assistant</h1>
          <span style={{ display: "inline-flex", alignItems: "center", gap: 6, font: "400 12px/1 var(--font-sans)", color: "var(--text-muted)", marginTop: 5 }}>
            <span style={{ width: 6, height: 6, borderRadius: 999, background: "var(--emerald-400)" }} /> Local model · every action needs your approval
          </span>
        </div>
      </div>
      <Conversation />
    </div>
  );
}

function Bubble({ m, compact }) {
  const user = m.role === "user";
  return (
    <div style={{ display: "flex", gap: 12, justifyContent: user ? "flex-end" : "flex-start" }}>
      {!user && <div style={{ width: 32, height: 32, borderRadius: 10, background: "var(--emerald-glow)", color: "var(--emerald-400)", display: "grid", placeItems: "center", flex: "none" }}><Icon name="sparkles" size={16} /></div>}
      <div style={{ maxWidth: compact ? 300 : 560, display: "flex", flexDirection: "column", gap: 8, alignItems: user ? "flex-end" : "flex-start" }}>
        {m.tools && (
          <div style={{ display: "flex", gap: 6 }}>
            {m.tools.map((t) => <span key={t} style={{ display: "inline-flex", alignItems: "center", gap: 5, background: "var(--surface-hover)", color: "var(--text-muted)", font: "500 11px/1 var(--font-mono)", padding: "5px 9px", borderRadius: 999 }}><Icon name="wrench" size={11} color="var(--emerald-400)" /> {t}</span>)}
          </div>
        )}
        <div style={{ whiteSpace: "pre-wrap", font: "400 14px/1.55 var(--font-sans)", padding: "12px 16px", borderRadius: 16, background: user ? "var(--emerald-500)" : "var(--surface-card)", color: user ? "var(--text-on-accent)" : "var(--text-body)", border: user ? "none" : "1px solid var(--border-subtle)", borderTopRightRadius: user ? 4 : 16, borderTopLeftRadius: user ? 16 : 4 }}>{m.content}</div>
        {m.pending && (
          <div className="erp-card" style={{ padding: 16, borderColor: "var(--amber-glow)", maxWidth: 420 }}>
            <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 10 }}><Icon name="shield-alert" size={16} color="var(--amber-400)" /><span style={{ font: "600 13px/1 var(--font-sans)", color: "var(--amber-400)" }}>Confirmation required — create_purchase_order</span></div>
            <pre style={{ margin: "0 0 12px", background: "var(--surface-inset)", borderRadius: 10, padding: 12, font: "400 12px/1.5 var(--font-mono)", color: "var(--text-body)", overflow: "auto" }}>{`{
  "supplier": "Default supplier",
  "product": "ERP-0210",
  "quantity": 50
}`}</pre>
            <div style={{ display: "flex", gap: 8 }}>
              <Btn variant="primary" size="sm" icon="check">Approve</Btn>
              <Btn variant="danger" size="sm" icon="x">Reject</Btn>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

const MOVE_TONE = { in: "emerald", out: "rose", adjustment: "amber", transfer: "sky" };
const MOVE_ICON = { in: "arrow-down-to-line", out: "arrow-up-from-line", adjustment: "scale", transfer: "arrow-left-right" };

function Segmented({ value, onChange, options }) {
  return (
    <div style={{ display: "flex", gap: 3, padding: 3, background: "var(--surface-inset)", border: "1px solid var(--border)", borderRadius: 12 }}>
      {options.map((o) => {
        const active = value === o.v;
        return (
          <button key={o.v} type="button" onClick={() => onChange(o.v)}
            style={{ flex: 1, display: "inline-flex", alignItems: "center", justifyContent: "center", gap: 6, height: 32, borderRadius: 9, border: "none", cursor: "pointer",
              font: "600 12px/1 var(--font-sans)", background: active ? "var(--surface-hover)" : "transparent", color: active ? o.color || "var(--text-strong)" : "var(--text-muted)",
              boxShadow: active ? "var(--shadow-xs), var(--hairline)" : "none", transition: "all var(--dur-1) var(--ease-out)" }}>
            <Icon name={o.icon} size={14} /> {o.label}
          </button>
        );
      })}
    </div>
  );
}

function FormRow({ label, children }) {
  return (
    <label style={{ display: "flex", flexDirection: "column", gap: 8 }}>
      <span style={{ font: "500 13px/1 var(--font-sans)", color: "var(--text-body)" }}>{label}</span>
      {children}
    </label>
  );
}

function Inventory() {
  const [type, setType] = React.useState("in");
  const [filter, setFilter] = React.useState("all");
  const rows = D.movements.filter((m) => filter === "all" || m.type === filter);
  const inQty = D.movements.filter((m) => m.type === "in").reduce((s, m) => s + m.qty, 0);
  const outQty = D.movements.filter((m) => m.type === "out").reduce((s, m) => s + Math.abs(m.qty), 0);

  return (
    <div>
      <PageHead title="Inventory" sub="Stock movements across 3 warehouses">
        <Btn variant="outline" size="md" icon="download">Export</Btn>
        <Btn variant="primary" size="md" icon="arrow-left-right">New transfer</Btn>
      </PageHead>

      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: 16, marginBottom: 20 }}>
        <Kpi label="Stock in (30d)" value={inQty.toLocaleString()} unit="units" delta={8.2} spark={[10,12,11,14,13,16,18,20]} />
        <Kpi label="Stock out (30d)" value={outQty.toLocaleString()} unit="units" delta={-4.5} tone="neutral" spark={[18,16,17,15,14,13,12,11]} />
        <Kpi label="Warehouses" value="3" tone="neutral" delta={0} spark={[3,3,3,3,3,3,3,3]} />
        <Kpi label="Low stock" value={D.lowStock.length} unit="items" delta={-2} tone="neutral" spark={[6,5,5,4,4,3,3,3]} />
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "minmax(320px, 380px) 1fr", gap: 16, alignItems: "start" }}>
        {/* Record movement */}
        <div className="erp-card" style={{ padding: 22, display: "flex", flexDirection: "column", gap: 16 }}>
          <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
            <div style={{ width: 34, height: 34, borderRadius: 10, background: "var(--emerald-glow)", display: "grid", placeItems: "center", color: "var(--emerald-400)" }}><Icon name="plus-circle" size={18} /></div>
            <h3 style={{ margin: 0, font: "600 16px/1 var(--font-sans)", color: "var(--text-strong)" }}>Record movement</h3>
          </div>
          <FormRow label="Product">
            <select className="erp-field"><option>ERP-0088 — Copper coil 2.5mm</option><option>ERP-0042 — Aluminium bracket 40mm</option><option>ERP-0119 — Stainless bolt M8</option></select>
          </FormRow>
          <FormRow label="Type">
            <Segmented value={type} onChange={setType} options={[
              { v: "in", label: "In", icon: "arrow-down-to-line", color: "var(--emerald-400)" },
              { v: "out", label: "Out", icon: "arrow-up-from-line", color: "var(--rose-400)" },
              { v: "adjustment", label: "Adjust", icon: "scale", color: "var(--amber-400)" },
            ]} />
          </FormRow>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
            <FormRow label="Quantity"><input className="erp-field" type="number" placeholder="0" defaultValue={120} /></FormRow>
            <FormRow label="Warehouse"><select className="erp-field">{D.warehouses.map((w) => <option key={w.id}>{w.name}{w.def ? " (default)" : ""}</option>)}</select></FormRow>
          </div>
          <FormRow label="Reason"><input className="erp-field" placeholder="e.g. PO receipt, damage, recount…" /></FormRow>
          <button className="erp-btn erp-btn--primary erp-btn--md" style={{ width: "100%" }}><Icon name="check" size={16} /> Record movement</button>
        </div>

        {/* Movement history */}
        <div className="erp-card" style={{ display: "flex", flexDirection: "column", overflow: "hidden" }}>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 12, padding: "16px 20px", borderBottom: "1px solid var(--border-subtle)", flexWrap: "wrap" }}>
            <h3 style={{ margin: 0, font: "600 16px/1 var(--font-sans)", color: "var(--text-strong)" }}>Movement history</h3>
            <div style={{ display: "flex", gap: 3, padding: 3, background: "var(--surface-inset)", border: "1px solid var(--border)", borderRadius: 999 }}>
              {["all", "in", "out", "adjustment", "transfer"].map((f) => (
                <button key={f} onClick={() => setFilter(f)} style={{ height: 26, padding: "0 12px", borderRadius: 999, border: "none", cursor: "pointer", textTransform: "capitalize",
                  font: "600 12px/1 var(--font-sans)", background: filter === f ? "var(--surface-hover)" : "transparent", color: filter === f ? "var(--text-strong)" : "var(--text-muted)", transition: "all var(--dur-1)" }}>{f}</button>
              ))}
            </div>
          </div>
          <div style={{ overflowX: "auto" }}>
            <table className="erp-table">
              <thead><tr><th>When</th><th>Product</th><th>Type</th><th style={{ textAlign: "right" }}>Qty</th><th>Warehouse</th><th>Reason</th><th>By</th></tr></thead>
              <tbody>
                {rows.map((m) => (
                  <tr key={m.id}>
                    <td style={{ whiteSpace: "nowrap", color: "var(--text-faint)", fontSize: 12, fontFamily: "var(--font-mono)" }}>{m.at}</td>
                    <td><span style={{ fontFamily: "var(--font-mono)", fontSize: 12, color: "var(--text-faint)" }}>{m.sku}</span> <span style={{ color: "var(--text-strong)" }}>{m.name}</span></td>
                    <td><span style={{ display: "inline-flex", alignItems: "center", gap: 6 }}><Icon name={MOVE_ICON[m.type]} size={13} color={`var(--${MOVE_TONE[m.type] === "emerald" ? "emerald" : MOVE_TONE[m.type] === "rose" ? "rose" : MOVE_TONE[m.type] === "amber" ? "amber" : "sky"}-400)`} /><Badge tone={MOVE_TONE[m.type]}>{m.type}</Badge></span></td>
                    <td style={{ textAlign: "right", fontFamily: "var(--font-mono)", color: m.qty < 0 ? "var(--rose-400)" : "var(--text-strong)" }}>{m.qty > 0 ? "+" : ""}{m.qty}</td>
                    <td style={{ color: "var(--text-muted)" }}>{m.wh}</td>
                    <td style={{ color: "var(--text-muted)" }}>{m.reason}</td>
                    <td style={{ fontSize: 12, color: "var(--text-faint)", fontFamily: "var(--font-mono)" }}>{m.by}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}

function Placeholder({ label }) {
  return (
    <div>
      <PageHead title={label} sub="This module keeps its existing functionality — restyled with the ERP design system." />
      <div className="erp-card" style={{ padding: 64, display: "grid", placeItems: "center", textAlign: "center" }}>
        <div style={{ width: 56, height: 56, borderRadius: 16, background: "var(--surface-hover)", display: "grid", placeItems: "center", color: "var(--text-faint)", marginBottom: 16 }}><Icon name="layers" size={26} /></div>
        <p style={{ margin: 0, font: "400 14px/1.5 var(--font-sans)", color: "var(--text-muted)", maxWidth: 340 }}>{label} view — same data and workflows, wrapped in the new premium interface.</p>
      </div>
    </div>
  );
}

window.ERP_PAGES = { Dashboard, Products, Crm, Assistant, Inventory, Placeholder, Conversation };
