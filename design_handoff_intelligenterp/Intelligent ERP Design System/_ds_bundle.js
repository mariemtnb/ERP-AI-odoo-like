/* @ds-bundle: {"format":4,"namespace":"IntelligentERPDesignSystem_b41ec1","components":[{"name":"KpiCard","sourcePath":"components/data/KpiCard.jsx"},{"name":"Badge","sourcePath":"components/display/Badge.jsx"},{"name":"Card","sourcePath":"components/display/Card.jsx"},{"name":"CardHeader","sourcePath":"components/display/Card.jsx"},{"name":"CardTitle","sourcePath":"components/display/Card.jsx"},{"name":"CardContent","sourcePath":"components/display/Card.jsx"},{"name":"Skeleton","sourcePath":"components/display/Skeleton.jsx"},{"name":"StatusDot","sourcePath":"components/display/StatusDot.jsx"},{"name":"Table","sourcePath":"components/display/Table.jsx"},{"name":"THead","sourcePath":"components/display/Table.jsx"},{"name":"TBody","sourcePath":"components/display/Table.jsx"},{"name":"Tr","sourcePath":"components/display/Table.jsx"},{"name":"Th","sourcePath":"components/display/Table.jsx"},{"name":"Td","sourcePath":"components/display/Table.jsx"},{"name":"Dialog","sourcePath":"components/feedback/Dialog.jsx"},{"name":"Button","sourcePath":"components/forms/Button.jsx"},{"name":"IconButton","sourcePath":"components/forms/IconButton.jsx"},{"name":"Input","sourcePath":"components/forms/Input.jsx"},{"name":"Select","sourcePath":"components/forms/Select.jsx"}],"sourceHashes":{"components/data/KpiCard.jsx":"2d1f6e344001","components/display/Badge.jsx":"91bf32ad50f1","components/display/Card.jsx":"2ad40051eb01","components/display/Skeleton.jsx":"731423a22e55","components/display/StatusDot.jsx":"026194812473","components/display/Table.jsx":"aae8e28e1ada","components/feedback/Dialog.jsx":"78a0358b9e3e","components/forms/Button.jsx":"64465972f7f2","components/forms/IconButton.jsx":"08411a6abd7e","components/forms/Input.jsx":"8283d2dfbb54","components/forms/Select.jsx":"fc57c23c6c00","design_handoff_intelligenterp/design_files/landing/core.js":"d1b3daa26b88","design_handoff_intelligenterp/design_files/landing/interactions.js":"46ee554d284b","design_handoff_intelligenterp/design_files/ui_kits/erp-app/data.js":"fb8441e83257","landing/core.js":"d1b3daa26b88","landing/interactions.js":"46ee554d284b","ui_kits/erp-app/app.jsx":"f72c1d95dbd4","ui_kits/erp-app/data.js":"fb8441e83257","ui_kits/erp-app/modules.jsx":"16a2622454d4","ui_kits/erp-app/pages.jsx":"f95707c1e4f1","ui_kits/erp-app/ui.jsx":"3aff22f6db1c"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.IntelligentERPDesignSystem_b41ec1 = window.IntelligentERPDesignSystem_b41ec1 || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/data/KpiCard.jsx
try { (() => {
/**
 * KpiCard — dashboard metric tile. Eyebrow label, large value, delta chip,
 * optional sparkline (array of numbers). The signature dashboard element.
 */
function KpiCard({
  label,
  value,
  unit,
  delta,
  tone = "emerald",
  spark,
  icon
}) {
  const up = (delta ?? 0) >= 0;
  const deltaColor = up ? "var(--emerald-400)" : "var(--rose-400)";
  return /*#__PURE__*/React.createElement("div", {
    className: "erp-card erp-card--hover",
    style: {
      padding: "var(--space-5)",
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-4)",
      overflow: "hidden",
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "absolute",
      inset: 0,
      background: "var(--wash-emerald)",
      opacity: tone === "emerald" ? 1 : 0,
      pointerEvents: "none"
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "ds-eyebrow",
    style: {
      letterSpacing: "var(--tracking-caps)",
      textTransform: "uppercase",
      font: "var(--weight-semibold) var(--text-2xs)/1 var(--font-sans)",
      color: "var(--text-faint)"
    }
  }, label), icon && /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--text-faint)",
      display: "flex"
    }
  }, icon)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "baseline",
      gap: "var(--space-2)",
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--weight-semibold) var(--text-3xl)/1 var(--font-sans)",
      letterSpacing: "var(--tracking-display)",
      color: "var(--text-strong)",
      fontVariantNumeric: "tabular-nums"
    }
  }, value), unit && /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-label)",
      color: "var(--text-muted)"
    }
  }, unit)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      position: "relative"
    }
  }, delta != null && /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 4,
      font: "var(--weight-semibold) var(--text-xs)/1 var(--font-sans)",
      color: deltaColor
    }
  }, up ? "▲" : "▼", " ", Math.abs(delta), "%"), spark && /*#__PURE__*/React.createElement(Spark, {
    data: spark,
    up: up
  })));
}
function Spark({
  data,
  up
}) {
  const max = Math.max(...data, 1),
    min = Math.min(...data, 0);
  const w = 72,
    h = 24,
    span = max - min || 1;
  const pts = data.map((v, i) => `${i / (data.length - 1) * w},${h - (v - min) / span * h}`).join(" ");
  const color = up ? "var(--emerald-400)" : "var(--rose-400)";
  return /*#__PURE__*/React.createElement("svg", {
    width: w,
    height: h,
    viewBox: `0 0 ${w} ${h}`,
    preserveAspectRatio: "none",
    style: {
      overflow: "visible"
    }
  }, /*#__PURE__*/React.createElement("polyline", {
    points: pts,
    fill: "none",
    stroke: color,
    strokeWidth: "1.75",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }));
}
Object.assign(__ds_scope, { KpiCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/data/KpiCard.jsx", error: String((e && e.message) || e) }); }

// components/display/Badge.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const TONES = {
  neutral: {
    bg: "var(--surface-hover)",
    fg: "var(--text-body)"
  },
  emerald: {
    bg: "var(--emerald-glow)",
    fg: "var(--emerald-400)"
  },
  amber: {
    bg: "var(--amber-glow)",
    fg: "var(--amber-400)"
  },
  rose: {
    bg: "var(--rose-glow)",
    fg: "var(--rose-400)"
  },
  sky: {
    bg: "var(--sky-glow)",
    fg: "var(--sky-400)"
  },
  violet: {
    bg: "var(--violet-glow)",
    fg: "var(--violet-400)"
  }
};

/** Badge — compact status/label pill. Optional leading dot. */
function Badge({
  tone = "neutral",
  dot = false,
  children,
  className = "",
  style,
  ...props
}) {
  const t = TONES[tone] || TONES.neutral;
  return /*#__PURE__*/React.createElement("span", _extends({
    className: className,
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: "var(--space-2)",
      background: t.bg,
      color: t.fg,
      font: "var(--weight-semibold) var(--text-2xs)/1 var(--font-sans)",
      letterSpacing: "var(--tracking-wide)",
      padding: "5px 10px",
      borderRadius: "var(--radius-full)",
      textTransform: "capitalize",
      ...style
    }
  }, props), dot && /*#__PURE__*/React.createElement("span", {
    style: {
      width: 6,
      height: 6,
      borderRadius: "999px",
      background: "currentColor"
    }
  }), children);
}
Object.assign(__ds_scope, { Badge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/display/Badge.jsx", error: String((e && e.message) || e) }); }

// components/display/Card.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Card — the primary surface container. Soft elevation + hairline. */
function Card({
  hover = false,
  className = "",
  style,
  children,
  ...props
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    className: `erp-card ${hover ? "erp-card--hover" : ""} ${className}`,
    style: style
  }, props), children);
}
function CardHeader({
  className = "",
  children,
  ...props
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    className: className,
    style: {
      padding: "var(--space-6) var(--space-6) var(--space-3)"
    }
  }, props), children);
}
function CardTitle({
  className = "",
  children,
  ...props
}) {
  return /*#__PURE__*/React.createElement("h3", _extends({
    className: className,
    style: {
      font: "var(--weight-semibold) var(--text-lg)/1.2 var(--font-sans)",
      letterSpacing: "var(--tracking-tight)",
      color: "var(--text-strong)",
      margin: 0
    }
  }, props), children);
}
function CardContent({
  className = "",
  children,
  ...props
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    className: className,
    style: {
      padding: "var(--space-3) var(--space-6) var(--space-6)"
    }
  }, props), children);
}
Object.assign(__ds_scope, { Card, CardHeader, CardTitle, CardContent });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/display/Card.jsx", error: String((e && e.message) || e) }); }

// components/display/Skeleton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Skeleton — shimmering placeholder block for loading states. */
function Skeleton({
  width = "100%",
  height = 16,
  radius = "var(--radius-sm)",
  className = "",
  style,
  ...props
}) {
  return /*#__PURE__*/React.createElement("div", _extends({
    className: `erp-skel ${className}`,
    style: {
      width,
      height,
      borderRadius: radius,
      ...style
    }
  }, props));
}
Object.assign(__ds_scope, { Skeleton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/display/Skeleton.jsx", error: String((e && e.message) || e) }); }

// components/display/StatusDot.jsx
try { (() => {
const TONES = {
  emerald: "var(--emerald-400)",
  amber: "var(--amber-400)",
  rose: "var(--rose-400)",
  sky: "var(--sky-400)",
  violet: "var(--violet-400)",
  neutral: "var(--text-faint)"
};

/** StatusDot — a small colored dot with an optional soft pulse; for inline status. */
function StatusDot({
  tone = "emerald",
  pulse = false,
  label,
  style
}) {
  const color = TONES[tone] || TONES.emerald;
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: "var(--space-2)",
      ...style
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      position: "relative",
      width: 8,
      height: 8,
      display: "inline-flex"
    }
  }, pulse && /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      inset: -3,
      borderRadius: "999px",
      background: color,
      opacity: 0.35,
      animation: "erp-ping 1.6s var(--ease-out) infinite"
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      width: 8,
      height: 8,
      borderRadius: "999px",
      background: color
    }
  }), /*#__PURE__*/React.createElement("style", null, "@keyframes erp-ping{75%,100%{transform:scale(2);opacity:0}}")), label && /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-label)",
      color: "var(--text-body)"
    }
  }, label));
}
Object.assign(__ds_scope, { StatusDot });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/display/StatusDot.jsx", error: String((e && e.message) || e) }); }

// components/display/Table.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Table — data table with sticky header, soft row hover, and comfortable rows. */
function Table({
  className = "",
  children,
  ...props
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      width: "100%",
      overflow: "auto",
      borderRadius: "var(--radius-lg)",
      border: "1px solid var(--border-subtle)",
      background: "var(--surface-card)",
      boxShadow: "var(--shadow-sm), var(--hairline)"
    }
  }, /*#__PURE__*/React.createElement("table", _extends({
    className: `erp-table ${className}`
  }, props), children));
}
function THead(props) {
  return /*#__PURE__*/React.createElement("thead", props);
}
function TBody(props) {
  return /*#__PURE__*/React.createElement("tbody", props);
}
function Tr(props) {
  return /*#__PURE__*/React.createElement("tr", props);
}
function Th({
  align,
  style,
  ...props
}) {
  return /*#__PURE__*/React.createElement("th", _extends({
    style: {
      textAlign: align,
      ...style
    }
  }, props));
}
function Td({
  align,
  mono,
  style,
  ...props
}) {
  return /*#__PURE__*/React.createElement("td", _extends({
    style: {
      textAlign: align,
      fontFamily: mono ? "var(--font-mono)" : undefined,
      fontSize: mono ? "var(--text-sm)" : undefined,
      ...style
    }
  }, props));
}
Object.assign(__ds_scope, { Table, THead, TBody, Tr, Th, Td });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/display/Table.jsx", error: String((e && e.message) || e) }); }

// components/feedback/Dialog.jsx
try { (() => {
/** Dialog — centered modal with blurred backdrop. Controlled via `open`/`onClose`. */
function Dialog({
  open,
  onClose,
  title,
  description,
  maxWidth = 480,
  children
}) {
  if (!open) return null;
  return /*#__PURE__*/React.createElement("div", {
    className: "erp-dialog-backdrop",
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    className: "erp-dialog",
    style: {
      maxWidth
    },
    onClick: e => e.stopPropagation()
  }, (title || description) && /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-6) var(--space-6) var(--space-3)"
    }
  }, title && /*#__PURE__*/React.createElement("h2", {
    style: {
      font: "var(--weight-semibold) var(--text-xl)/1.2 var(--font-sans)",
      letterSpacing: "var(--tracking-tight)",
      color: "var(--text-strong)",
      margin: 0
    }
  }, title), description && /*#__PURE__*/React.createElement("p", {
    style: {
      marginTop: "var(--space-2)",
      font: "var(--font-body)",
      color: "var(--text-muted)"
    }
  }, description)), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "var(--space-3) var(--space-6) var(--space-6)"
    }
  }, children)));
}
Object.assign(__ds_scope, { Dialog });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/feedback/Dialog.jsx", error: String((e && e.message) || e) }); }

// components/forms/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/**
 * Button — the primary action primitive for Intelligent ERP.
 * Variants: primary (emerald), secondary, ghost, outline, danger.
 * Sizes: sm, md, lg. Supports leading/trailing icons and a loading state.
 */
function Button({
  variant = "primary",
  size = "md",
  loading = false,
  icon = null,
  iconRight = null,
  children,
  className = "",
  disabled,
  ...props
}) {
  return /*#__PURE__*/React.createElement("button", _extends({
    className: `erp-btn erp-btn--${variant} erp-btn--${size} ${className}`,
    disabled: disabled || loading
  }, props), loading ? /*#__PURE__*/React.createElement(Spinner, null) : icon, children, !loading && iconRight);
}
function Spinner() {
  return /*#__PURE__*/React.createElement("span", {
    "aria-hidden": true,
    style: {
      width: 14,
      height: 14,
      border: "2px solid currentColor",
      borderTopColor: "transparent",
      borderRadius: "999px",
      display: "inline-block",
      animation: "erp-spin 0.7s linear infinite"
    }
  }, /*#__PURE__*/React.createElement("style", null, "@keyframes erp-spin{to{transform:rotate(360deg)}}"));
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Button.jsx", error: String((e && e.message) || e) }); }

// components/forms/IconButton.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** IconButton — square, chromeless button for a single glyph (toolbar, table row actions). */
function IconButton({
  size = "md",
  children,
  className = "",
  ...props
}) {
  return /*#__PURE__*/React.createElement("button", _extends({
    className: `erp-iconbtn erp-iconbtn--${size} ${className}`
  }, props), children);
}
Object.assign(__ds_scope, { IconButton });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/IconButton.jsx", error: String((e && e.message) || e) }); }

// components/forms/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Input — text field with optional label, hint, error, and leading icon. */
function Input({
  label,
  hint,
  error,
  icon,
  className = "",
  id,
  ...props
}) {
  const fieldId = id || (label ? `f-${label.replace(/\s+/g, "-").toLowerCase()}` : undefined);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-2)",
      width: "100%"
    }
  }, label && /*#__PURE__*/React.createElement("label", {
    htmlFor: fieldId,
    className: "ds-label",
    style: labelStyle
  }, label), /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      display: "flex",
      alignItems: "center"
    }
  }, icon && /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      left: "var(--space-3)",
      color: "var(--text-faint)",
      display: "flex"
    }
  }, icon), /*#__PURE__*/React.createElement("input", _extends({
    id: fieldId,
    className: `erp-field ${className}`,
    style: {
      paddingLeft: icon ? "var(--space-10)" : undefined,
      borderColor: error ? "var(--rose-400)" : undefined
    }
  }, props))), (hint || error) && /*#__PURE__*/React.createElement("span", {
    style: {
      font: "var(--font-label)",
      fontSize: "var(--text-xs)",
      color: error ? "var(--rose-400)" : "var(--text-faint)"
    }
  }, error || hint));
}
const labelStyle = {
  font: "var(--weight-medium) var(--text-sm)/1 var(--font-sans)",
  color: "var(--text-body)"
};
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Input.jsx", error: String((e && e.message) || e) }); }

// components/forms/Select.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
/** Select — native select styled to match Input, with optional label. */
function Select({
  label,
  hint,
  error,
  children,
  className = "",
  id,
  ...props
}) {
  const fieldId = id || (label ? `s-${label.replace(/\s+/g, "-").toLowerCase()}` : undefined);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: "var(--space-2)",
      width: "100%"
    }
  }, label && /*#__PURE__*/React.createElement("label", {
    htmlFor: fieldId,
    style: {
      font: "var(--weight-medium) var(--text-sm)/1 var(--font-sans)",
      color: "var(--text-body)"
    }
  }, label), /*#__PURE__*/React.createElement("select", _extends({
    id: fieldId,
    className: `erp-field ${className}`,
    style: {
      borderColor: error ? "var(--rose-400)" : undefined
    }
  }, props), children), (hint || error) && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: "var(--text-xs)",
      color: error ? "var(--rose-400)" : "var(--text-faint)"
    }
  }, error || hint));
}
Object.assign(__ds_scope, { Select });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Select.jsx", error: String((e && e.message) || e) }); }

// design_handoff_intelligenterp/design_files/landing/core.js
try { (() => {
/* Cinematic backdrop: drifting particle field + neural AI core wired to scroll & mouse.
   Pure canvas, GPU-friendly, capped DPR, respects reduced-motion. */
(function () {
  const canvas = document.getElementById("core-canvas");
  const ctx = canvas.getContext("2d");
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const EM = {
    c1: "16,185,129",
    c2: "52,211,153",
    c3: "56,189,248"
  };
  let W, H, DPR;
  const mouse = {
    x: 0.5,
    y: 0.5,
    tx: 0.5,
    ty: 0.5
  };
  let scrollN = 0; // 0..1 through hero region

  function resize() {
    DPR = Math.min(window.devicePixelRatio || 1, 2);
    W = canvas.width = innerWidth * DPR;
    H = canvas.height = innerHeight * DPR;
    canvas.style.width = innerWidth + "px";
    canvas.style.height = innerHeight + "px";
    build();
  }

  /* ---- particle field ---- */
  let particles = [];
  function build() {
    const count = Math.min(120, Math.floor(innerWidth * innerHeight / 14000));
    particles = Array.from({
      length: count
    }, () => ({
      x: Math.random() * W,
      y: Math.random() * H,
      z: Math.random() * 0.8 + 0.2,
      vx: (Math.random() - 0.5) * 0.12,
      vy: (Math.random() - 0.5) * 0.12,
      r: Math.random() * 1.6 + 0.4
    }));
  }

  /* ---- neural core nodes (spherical projection) ---- */
  const NODES = 34;
  const core = Array.from({
    length: NODES
  }, (_, i) => {
    const phi = Math.acos(1 - 2 * (i + 0.5) / NODES);
    const theta = Math.PI * (1 + Math.sqrt(5)) * i;
    return {
      x: Math.sin(phi) * Math.cos(theta),
      y: Math.sin(phi) * Math.sin(theta),
      z: Math.cos(phi),
      p: Math.random() * Math.PI * 2
    };
  });
  function draw(t) {
    mouse.x += (mouse.tx - mouse.x) * 0.06;
    mouse.y += (mouse.ty - mouse.y) * 0.06;
    ctx.clearRect(0, 0, W, H);
    const cx = W * (0.5 + (mouse.x - 0.5) * 0.05);
    const cy = H * (0.46 + (mouse.y - 0.5) * 0.05);

    // ---- particles + links ----
    for (const p of particles) {
      p.x += p.vx * p.z * DPR + (mouse.x - 0.5) * 0.25 * p.z;
      p.y += p.vy * p.z * DPR + (mouse.y - 0.5) * 0.25 * p.z;
      if (p.x < 0) p.x += W;
      if (p.x > W) p.x -= W;
      if (p.y < 0) p.y += H;
      if (p.y > H) p.y -= H;
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r * DPR, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${EM.c2},${0.10 + p.z * 0.18})`;
      ctx.fill();
    }
    // subtle links between near particles
    ctx.lineWidth = DPR * 0.6;
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const a = particles[i],
          b = particles[j];
        const dx = a.x - b.x,
          dy = a.y - b.y,
          d2 = dx * dx + dy * dy;
        const max = (140 * DPR) ** 2;
        if (d2 < max) {
          const o = (1 - d2 / max) * 0.12;
          ctx.strokeStyle = `rgba(${EM.c1},${o})`;
          ctx.beginPath();
          ctx.moveTo(a.x, a.y);
          ctx.lineTo(b.x, b.y);
          ctx.stroke();
        }
      }
    }

    // ---- AI neural core ----
    const R = Math.min(W, H) * (0.20 - scrollN * 0.06);
    const rotY = t * 0.00022 + mouse.x * 0.8;
    const rotX = 0.5 + mouse.y * 0.5 + scrollN * 0.6;
    const cosY = Math.cos(rotY),
      sinY = Math.sin(rotY),
      cosX = Math.cos(rotX),
      sinX = Math.sin(rotX);
    const proj = core.map(n => {
      let x = n.x * cosY - n.z * sinY;
      let z = n.x * sinY + n.z * cosY;
      let y = n.y * cosX - z * sinX;
      z = n.y * sinX + z * cosX;
      const scale = 1 / (1.8 - z);
      return {
        sx: cx + x * R * scale,
        sy: cy + y * R * scale,
        z,
        scale,
        pulse: 0.5 + 0.5 * Math.sin(t * 0.002 + n.p)
      };
    });

    // halo
    const halo = ctx.createRadialGradient(cx, cy, R * 0.2, cx, cy, R * 2.4);
    halo.addColorStop(0, `rgba(${EM.c1},${0.14 * (1 - scrollN * 0.7)})`);
    halo.addColorStop(1, "rgba(0,0,0,0)");
    ctx.fillStyle = halo;
    ctx.fillRect(cx - R * 2.6, cy - R * 2.6, R * 5.2, R * 5.2);

    // connections
    ctx.lineWidth = DPR * 0.7;
    for (let i = 0; i < proj.length; i++) {
      for (let j = i + 1; j < proj.length; j++) {
        const a = proj[i],
          b = proj[j];
        const dx = a.sx - b.sx,
          dy = a.sy - b.sy,
          d2 = dx * dx + dy * dy;
        const max = (R * 0.82) ** 2;
        if (d2 < max) {
          const o = (1 - d2 / max) * 0.5 * Math.min(a.z + 1.2, 1) * (1 - scrollN * 0.6);
          ctx.strokeStyle = `rgba(${EM.c2},${o})`;
          ctx.beginPath();
          ctx.moveTo(a.sx, a.sy);
          ctx.lineTo(b.sx, b.sy);
          ctx.stroke();
        }
      }
    }
    // nodes
    for (const n of proj) {
      const rad = (1.4 + n.pulse * 2.4) * n.scale * DPR;
      const alpha = (0.4 + n.pulse * 0.6) * (1 - scrollN * 0.5);
      const g = ctx.createRadialGradient(n.sx, n.sy, 0, n.sx, n.sy, rad * 3);
      g.addColorStop(0, `rgba(${EM.c2},${alpha})`);
      g.addColorStop(1, "rgba(0,0,0,0)");
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.arc(n.sx, n.sy, rad * 3, 0, Math.PI * 2);
      ctx.fill();
      ctx.beginPath();
      ctx.arc(n.sx, n.sy, rad, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(230,255,245,${alpha})`;
      ctx.fill();
    }
    raf = requestAnimationFrame(draw);
  }
  let raf;
  function onScroll() {
    scrollN = Math.min(1, window.scrollY / (innerHeight * 0.9));
  }
  window.addEventListener("resize", resize);
  window.addEventListener("scroll", onScroll, {
    passive: true
  });
  window.addEventListener("pointermove", e => {
    mouse.tx = e.clientX / innerWidth;
    mouse.ty = e.clientY / innerHeight;
  });
  resize();
  if (reduce) {
    // draw a single static frame
    scrollN = 0;
    draw(0);
    cancelAnimationFrame(raf);
  } else {
    raf = requestAnimationFrame(draw);
  }
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "design_handoff_intelligenterp/design_files/landing/core.js", error: String((e && e.message) || e) }); }

// design_handoff_intelligenterp/design_files/landing/interactions.js
try { (() => {
/* Interaction layer: custom cursor, magnetic buttons, tilt cards, scroll reveals,
   hero word-reveal, awaken word-lighting, animated counters, orbit motion, typed AI. */
(function () {
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const lerp = (a, b, n) => a + (b - a) * n;

  /* ---------- custom cursor ---------- */
  const dot = document.querySelector(".cursor-dot");
  const ring = document.querySelector(".cursor-ring");
  let mx = innerWidth / 2,
    my = innerHeight / 2,
    rx = mx,
    ry = my;
  if (dot && !matchMedia("(hover: none)").matches) {
    addEventListener("pointermove", e => {
      mx = e.clientX;
      my = e.clientY;
      dot.style.transform = `translate(${mx}px,${my}px) translate(-50%,-50%)`;
    });
    (function ring_loop() {
      rx = lerp(rx, mx, 0.18);
      ry = lerp(ry, my, 0.18);
      ring.style.transform = `translate(${rx}px,${ry}px) translate(-50%,-50%)`;
      requestAnimationFrame(ring_loop);
    })();
    const hot = () => ring.classList.add("hot"),
      cool = () => ring.classList.remove("hot");
    document.querySelectorAll("a, button, .btn, .tilt-card, .nav-link").forEach(el => {
      el.addEventListener("pointerenter", hot);
      el.addEventListener("pointerleave", cool);
    });
  }

  /* ---------- magnetic buttons ---------- */
  if (!reduce) document.querySelectorAll("[data-magnetic]").forEach(el => {
    el.addEventListener("pointermove", e => {
      const r = el.getBoundingClientRect();
      const dx = e.clientX - (r.left + r.width / 2),
        dy = e.clientY - (r.top + r.height / 2);
      el.style.transform = `translate(${dx * 0.28}px, ${dy * 0.4}px)`;
    });
    el.addEventListener("pointerleave", () => {
      el.style.transform = "";
    });
  });

  /* ---------- tilt cards + spotlight ---------- */
  if (!reduce) document.querySelectorAll(".tilt-card").forEach(card => {
    card.addEventListener("pointermove", e => {
      const r = card.getBoundingClientRect();
      const px = (e.clientX - r.left) / r.width,
        py = (e.clientY - r.top) / r.height;
      card.style.transform = `perspective(900px) rotateY(${(px - 0.5) * 9}deg) rotateX(${(0.5 - py) * 9}deg) translateY(-4px)`;
      card.style.setProperty("--mx", px * 100 + "%");
      card.style.setProperty("--my", py * 100 + "%");
    });
    card.addEventListener("pointerleave", () => {
      card.style.transform = "";
    });
  });

  /* ---------- scroll reveals ---------- */
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (en.isIntersecting) {
        en.target.classList.add("in");
        if (en.target.dataset.once !== undefined) io.unobserve(en.target);
      }
    });
  }, {
    threshold: 0.18
  });
  document.querySelectorAll(".r, .flow-step, .panel").forEach(el => io.observe(el));

  /* ---------- counters ---------- */
  const cio = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (!en.isIntersecting) return;
      const el = en.target,
        to = parseFloat(el.dataset.count),
        dec = el.dataset.dec ? +el.dataset.dec : 0,
        dur = 1400;
      const pre = el.dataset.pre || "",
        suf = el.dataset.suf || "";
      let start;
      const step = ts => {
        start ??= ts;
        const p = Math.min(1, (ts - start) / dur);
        const e = 1 - Math.pow(1 - p, 3);
        const v = to * e;
        el.textContent = pre + v.toLocaleString("en-US", {
          minimumFractionDigits: dec,
          maximumFractionDigits: dec
        }) + suf;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
      cio.unobserve(el);
    });
  }, {
    threshold: 0.5
  });
  document.querySelectorAll("[data-count]").forEach(el => cio.observe(el));

  /* ---------- hero word reveal ---------- */
  const heroWords = document.querySelectorAll(".reveal-word");
  heroWords.forEach((w, i) => {
    if (reduce) {
      w.style.opacity = 1;
      w.style.transform = "none";
      return;
    }
    w.animate([{
      opacity: 0,
      transform: "translateY(0.6em) rotateX(-40deg)"
    }, {
      opacity: 1,
      transform: "none"
    }], {
      duration: 900,
      delay: 260 + i * 85,
      easing: "cubic-bezier(0.16,1,0.3,1)",
      fill: "forwards"
    });
  });

  /* ---------- nav stuck + progress ---------- */
  const nav = document.querySelector(".nav"),
    prog = document.querySelector(".progress");
  const onScroll = () => {
    nav.classList.toggle("stuck", scrollY > 40);
    const max = document.body.scrollHeight - innerHeight;
    prog.style.width = (max > 0 ? scrollY / max * 100 : 0) + "%";
    lightAwaken();
    orbitTick();
  };
  addEventListener("scroll", onScroll, {
    passive: true
  });

  /* ---------- awaken word lighting ---------- */
  const awaken = document.querySelector(".awaken");
  const fadeWords = awaken ? [...awaken.querySelectorAll(".fade-word")] : [];
  function lightAwaken() {
    if (!awaken) return;
    const r = awaken.getBoundingClientRect();
    const prog = Math.min(1, Math.max(0, (innerHeight * 0.75 - r.top) / (r.height * 0.55)));
    const lit = Math.floor(prog * fadeWords.length);
    fadeWords.forEach((w, i) => w.classList.toggle("lit", i < lit));
  }

  /* ---------- orbit motion ---------- */
  const nodes = [...document.querySelectorAll(".orbit-node")];
  const stage = document.querySelector(".orbit-stage");
  function orbitTick() {
    if (!stage) return;
    const t = performance.now() * 0.00018;
    const r = stage.getBoundingClientRect();
    const cx = r.width / 2,
      cy = r.height / 2;
    nodes.forEach((n, i) => {
      const radius = +n.dataset.r * Math.min(cx, cy);
      const a = t * +n.dataset.speed + i / nodes.length * Math.PI * 2;
      const x = cx + Math.cos(a) * radius,
        y = cy + Math.sin(a) * radius * 0.62;
      n.style.transform = `translate(${x}px, ${y}px) translate(-50%, -50%)`;
      n.style.zIndex = y > cy ? 4 : 2;
      n.style.opacity = 0.55 + 0.45 * ((Math.sin(a) + 1) / 2);
    });
  }
  if (nodes.length && !reduce) (function loop() {
    orbitTick();
    requestAnimationFrame(loop);
  })();else orbitTick();

  /* ---------- typed AI console ---------- */
  const typed = document.querySelector(".ai-typed");
  if (typed) {
    const full = typed.dataset.text;
    const tio = new IntersectionObserver(e => {
      if (!e[0].isIntersecting) return;
      if (reduce) {
        typed.textContent = full;
        typed.classList.remove("ai-typed");
        tio.disconnect();
        return;
      }
      let i = 0;
      typed.textContent = "";
      const tick = () => {
        typed.textContent = full.slice(0, i++);
        if (i <= full.length) setTimeout(tick, 22);else typed.classList.remove("ai-typed");
      };
      tick();
      tio.disconnect();
    }, {
      threshold: 0.6
    });
    tio.observe(typed.closest(".panel") || typed);
  }
  onScroll();
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "design_handoff_intelligenterp/design_files/landing/interactions.js", error: String((e && e.message) || e) }); }

// design_handoff_intelligenterp/design_files/ui_kits/erp-app/data.js
try { (() => {
// Mock data for the Intelligent ERP UI kit (matches src/types shapes).
window.ERP_DATA = {
  user: {
    first_name: "Amine",
    last_name: "Khelifi",
    email: "admin@erp.local",
    role: "admin"
  },
  kpis: [{
    label: "Revenue",
    value: "48,250",
    unit: "TND",
    delta: 12.4,
    spark: [22, 26, 24, 30, 28, 34, 33, 40, 44, 48]
  }, {
    label: "Sales orders",
    value: "184",
    delta: 4.1,
    tone: "neutral",
    spark: [12, 14, 13, 15, 14, 16, 15, 17, 18, 18]
  }, {
    label: "Purchase orders",
    value: "62",
    delta: -2.3,
    tone: "neutral",
    spark: [9, 8, 10, 9, 8, 7, 8, 7, 6, 6]
  }, {
    label: "Purchases",
    value: "21,900",
    unit: "TND",
    delta: 6.8,
    tone: "neutral",
    spark: [10, 12, 11, 14, 13, 16, 15, 18, 20, 22]
  }],
  revenueSeries: [28, 31, 30, 35, 33, 38, 36, 42, 40, 45, 43, 49, 47, 52, 50, 56],
  topProducts: [{
    sku: "ERP-0042",
    name: "Aluminium bracket 40mm",
    sold: 320,
    revenue: "7,840"
  }, {
    sku: "ERP-0088",
    name: "Copper coil 2.5mm",
    sold: 148,
    revenue: "6,512"
  }, {
    sku: "ERP-0051",
    name: "Rubber gasket set",
    sold: 890,
    revenue: "3,916"
  }, {
    sku: "ERP-0119",
    name: "Stainless bolt M8",
    sold: 1240,
    revenue: "2,480"
  }],
  lowStock: [{
    sku: "ERP-0043",
    name: "Steel hinge 60mm",
    qty: 8,
    min: 25
  }, {
    sku: "ERP-0072",
    name: "PVC elbow joint",
    qty: 14,
    min: 40
  }, {
    sku: "ERP-0210",
    name: "Brass fitting 1/2\"",
    qty: 3,
    min: 30
  }],
  products: [{
    sku: "ERP-0042",
    name: "Aluminium bracket 40mm",
    cat: "Hardware",
    stock: 142,
    unit: "pcs",
    price: "24.50",
    active: true,
    low: false
  }, {
    sku: "ERP-0043",
    name: "Steel hinge 60mm",
    cat: "Hardware",
    stock: 8,
    unit: "pcs",
    price: "6.20",
    active: true,
    low: true
  }, {
    sku: "ERP-0051",
    name: "Rubber gasket set",
    cat: "Seals",
    stock: 326,
    unit: "set",
    price: "1.10",
    active: true,
    low: false
  }, {
    sku: "ERP-0072",
    name: "PVC elbow joint",
    cat: "Plumbing",
    stock: 14,
    unit: "pcs",
    price: "3.40",
    active: true,
    low: true
  }, {
    sku: "ERP-0088",
    name: "Copper coil 2.5mm",
    cat: "Electrical",
    stock: 54,
    unit: "m",
    price: "44.00",
    active: true,
    low: false
  }, {
    sku: "ERP-0119",
    name: "Stainless bolt M8",
    cat: "Hardware",
    stock: 1240,
    unit: "pcs",
    price: "0.20",
    active: true,
    low: false
  }, {
    sku: "ERP-0210",
    name: "Brass fitting 1/2\"",
    cat: "Plumbing",
    stock: 3,
    unit: "pcs",
    price: "5.80",
    active: false,
    low: true
  }],
  leads: {
    new: [{
      id: 1,
      name: "Sofia Trabelsi",
      company: "Medina Textiles",
      phone: "+216 22 145 900"
    }, {
      id: 2,
      name: "Karim Zouari",
      company: "Zouari Logistics",
      phone: "+216 98 231 774"
    }],
    contacted: [{
      id: 3,
      name: "Leila Mansour",
      company: "Atlas Foods",
      phone: "+216 55 620 118"
    }],
    qualified: [{
      id: 4,
      name: "Omar Belhaj",
      company: "BelTech",
      phone: "+216 71 004 552"
    }, {
      id: 5,
      name: "Nadia Cherif",
      company: "Cherif & Co",
      phone: "+216 29 887 210"
    }],
    won: [{
      id: 6,
      name: "Youssef Gharbi",
      company: "Gharbi Retail",
      phone: "+216 50 119 663"
    }],
    lost: [{
      id: 7,
      name: "Rania Ben Salah",
      company: "—",
      phone: "+216 24 700 401"
    }]
  },
  warehouses: [{
    id: 1,
    name: "Central Depot",
    def: true
  }, {
    id: 2,
    name: "Sfax Branch",
    def: false
  }, {
    id: 3,
    name: "Sousse Store",
    def: false
  }],
  movements: [{
    id: 1,
    at: "Today · 14:22",
    sku: "ERP-0088",
    name: "Copper coil 2.5mm",
    type: "in",
    qty: 120,
    wh: "Central Depot",
    reason: "PO-2041 receipt",
    src: "purchase",
    by: "amine@erp.local"
  }, {
    id: 2,
    at: "Today · 11:05",
    sku: "ERP-0119",
    name: "Stainless bolt M8",
    type: "out",
    qty: 400,
    wh: "Central Depot",
    reason: "SO-3312 shipment",
    src: "sale",
    by: "sofia@erp.local"
  }, {
    id: 3,
    at: "Yesterday · 17:40",
    sku: "ERP-0210",
    name: "Brass fitting 1/2\"",
    type: "adjustment",
    qty: -6,
    wh: "Sfax Branch",
    reason: "Damage — recount",
    src: "manual",
    by: "amine@erp.local"
  }, {
    id: 4,
    at: "Yesterday · 09:12",
    sku: "ERP-0051",
    name: "Rubber gasket set",
    type: "transfer",
    qty: 80,
    wh: "Central → Sousse",
    reason: "Rebalance stock",
    src: "transfer",
    by: "karim@erp.local"
  }, {
    id: 5,
    at: "Mar 12 · 16:31",
    sku: "ERP-0042",
    name: "Aluminium bracket 40mm",
    type: "in",
    qty: 200,
    wh: "Central Depot",
    reason: "Initial stock",
    src: "manual",
    by: "amine@erp.local"
  }, {
    id: 6,
    at: "Mar 12 · 10:08",
    sku: "ERP-0043",
    name: "Steel hinge 60mm",
    type: "out",
    qty: 17,
    wh: "Sfax Branch",
    reason: "SO-3298 shipment",
    src: "sale",
    by: "sofia@erp.local"
  }],
  customers: [{
    id: 1,
    name: "Gharbi Retail",
    email: "contact@gharbi.tn",
    phone: "+216 50 119 663",
    city: "Tunis",
    orders: 42,
    active: true
  }, {
    id: 2,
    name: "Medina Textiles",
    email: "hello@medinatex.tn",
    phone: "+216 22 145 900",
    city: "Sfax",
    orders: 18,
    active: true
  }, {
    id: 3,
    name: "Atlas Foods",
    email: "purchasing@atlasfoods.tn",
    phone: "+216 55 620 118",
    city: "Sousse",
    orders: 27,
    active: true
  }, {
    id: 4,
    name: "BelTech",
    email: "info@beltech.tn",
    phone: "+216 71 004 552",
    city: "Ariana",
    orders: 9,
    active: true
  }, {
    id: 5,
    name: "Cherif & Co",
    email: "",
    phone: "+216 29 887 210",
    city: "Bizerte",
    orders: 4,
    active: false
  }],
  suppliers: [{
    id: 1,
    name: "MetalWorks SARL",
    email: "sales@metalworks.tn",
    phone: "+216 71 330 210",
    city: "Tunis",
    orders: 61,
    active: true
  }, {
    id: 2,
    name: "Poly Distribution",
    email: "orders@polydist.tn",
    phone: "+216 74 118 004",
    city: "Sfax",
    orders: 33,
    active: true
  }, {
    id: 3,
    name: "ElectroSupply",
    email: "b2b@electrosupply.tn",
    phone: "+216 73 550 900",
    city: "Sousse",
    orders: 22,
    active: true
  }, {
    id: 4,
    name: "Fastener Depot",
    email: "",
    phone: "+216 70 221 447",
    city: "Nabeul",
    orders: 7,
    active: false
  }],
  purchases: [{
    number: "PO-2041",
    partner: "MetalWorks SARL",
    date: "2026-07-09",
    status: "received",
    total: "5,280.00",
    by: "amine@erp.local",
    lines: [["ERP-0088", "Copper coil 2.5mm", 120, "44.00"], ["ERP-0042", "Aluminium bracket 40mm", 200, "24.50"]]
  }, {
    number: "PO-2040",
    partner: "Poly Distribution",
    date: "2026-07-07",
    status: "confirmed",
    total: "1,960.00",
    by: "karim@erp.local",
    lines: [["ERP-0072", "PVC elbow joint", 400, "3.40"], ["ERP-0051", "Rubber gasket set", 200, "1.10"]]
  }, {
    number: "PO-2039",
    partner: "ElectroSupply",
    date: "2026-07-05",
    status: "pending_approval",
    total: "8,800.00",
    by: "karim@erp.local",
    lines: [["ERP-0088", "Copper coil 2.5mm", 200, "44.00"]]
  }, {
    number: "PO-2038",
    partner: "Fastener Depot",
    date: "2026-07-02",
    status: "draft",
    total: "248.00",
    by: "amine@erp.local",
    lines: [["ERP-0119", "Stainless bolt M8", 1240, "0.20"]]
  }, {
    number: "PO-2037",
    partner: "MetalWorks SARL",
    date: "2026-06-28",
    status: "cancelled",
    total: "1,470.00",
    by: "amine@erp.local",
    lines: [["ERP-0043", "Steel hinge 60mm", 50, "6.20"]]
  }],
  sales: [{
    number: "SO-3312",
    partner: "Gharbi Retail",
    date: "2026-07-11",
    status: "confirmed",
    total: "3,140.00",
    by: "sofia@erp.local",
    lines: [["ERP-0119", "Stainless bolt M8", 400, "0.35"], ["ERP-0042", "Aluminium bracket 40mm", 100, "29.90"]]
  }, {
    number: "SO-3311",
    partner: "Atlas Foods",
    date: "2026-07-10",
    status: "received",
    total: "890.00",
    by: "sofia@erp.local",
    lines: [["ERP-0051", "Rubber gasket set", 600, "1.48"]]
  }, {
    number: "SO-3310",
    partner: "Medina Textiles",
    date: "2026-07-08",
    status: "draft",
    total: "1,196.00",
    by: "sofia@erp.local",
    lines: [["ERP-0088", "Copper coil 2.5mm", 20, "59.80"]]
  }, {
    number: "SO-3309",
    partner: "BelTech",
    date: "2026-07-06",
    status: "confirmed",
    total: "418.00",
    by: "omar@erp.local",
    lines: [["ERP-0072", "PVC elbow joint", 110, "3.80"]]
  }, {
    number: "SO-3308",
    partner: "Gharbi Retail",
    date: "2026-07-03",
    status: "cancelled",
    total: "220.00",
    by: "sofia@erp.local",
    lines: [["ERP-0043", "Steel hinge 60mm", 20, "11.00"]]
  }],
  users: [{
    email: "amine@erp.local",
    first: "Amine",
    last: "Khelifi",
    role: "admin",
    active: true
  }, {
    email: "sofia@erp.local",
    first: "Sofia",
    last: "Trabelsi",
    role: "manager",
    active: true
  }, {
    email: "karim@erp.local",
    first: "Karim",
    last: "Zouari",
    role: "manager",
    active: true
  }, {
    email: "omar@erp.local",
    first: "Omar",
    last: "Belhaj",
    role: "employee",
    active: true
  }, {
    email: "leila@erp.local",
    first: "Leila",
    last: "Mansour",
    role: "employee",
    active: false
  }],
  reportRows: {
    sales: [{
      number: "SO-3312",
      date: "2026-07-11",
      partner: "Gharbi Retail",
      status: "confirmed",
      total: "3,140.00"
    }, {
      number: "SO-3311",
      date: "2026-07-10",
      partner: "Atlas Foods",
      status: "received",
      total: "890.00"
    }, {
      number: "SO-3309",
      date: "2026-07-06",
      partner: "BelTech",
      status: "confirmed",
      total: "418.00"
    }, {
      number: "SO-3308",
      date: "2026-07-03",
      partner: "Gharbi Retail",
      status: "cancelled",
      total: "220.00"
    }],
    purchases: [{
      number: "PO-2041",
      date: "2026-07-09",
      partner: "MetalWorks SARL",
      status: "received",
      total: "5,280.00"
    }, {
      number: "PO-2040",
      date: "2026-07-07",
      partner: "Poly Distribution",
      status: "confirmed",
      total: "1,960.00"
    }, {
      number: "PO-2039",
      date: "2026-07-05",
      partner: "ElectroSupply",
      status: "pending_approval",
      total: "8,800.00"
    }],
    stock: [{
      sku: "ERP-0042",
      name: "Aluminium bracket 40mm",
      cat: "Hardware",
      qty: 142,
      min: 40,
      value: "3,479.00",
      low: false
    }, {
      sku: "ERP-0043",
      name: "Steel hinge 60mm",
      cat: "Hardware",
      qty: 8,
      min: 25,
      value: "49.60",
      low: true
    }, {
      sku: "ERP-0088",
      name: "Copper coil 2.5mm",
      cat: "Electrical",
      qty: 54,
      min: 20,
      value: "2,376.00",
      low: false
    }, {
      sku: "ERP-0210",
      name: "Brass fitting 1/2\"",
      cat: "Plumbing",
      qty: 3,
      min: 30,
      value: "17.40",
      low: true
    }]
  },
  nav: [{
    to: "dashboard",
    label: "Dashboard",
    icon: "layout-dashboard"
  }, {
    to: "products",
    label: "Products",
    icon: "package"
  }, {
    to: "inventory",
    label: "Inventory",
    icon: "boxes"
  }, {
    to: "customers",
    label: "Customers",
    icon: "user-square-2"
  }, {
    to: "suppliers",
    label: "Suppliers",
    icon: "truck"
  }, {
    to: "purchases",
    label: "Purchases",
    icon: "shopping-cart"
  }, {
    to: "sales",
    label: "Sales",
    icon: "receipt"
  }, {
    to: "crm",
    label: "CRM",
    icon: "contact"
  }, {
    to: "reports",
    label: "Reports",
    icon: "file-text"
  }, {
    to: "assistant",
    label: "AI Assistant",
    icon: "sparkles"
  }, {
    to: "users",
    label: "Users",
    icon: "users"
  }]
};
})(); } catch (e) { __ds_ns.__errors.push({ path: "design_handoff_intelligenterp/design_files/ui_kits/erp-app/data.js", error: String((e && e.message) || e) }); }

// landing/core.js
try { (() => {
/* Cinematic backdrop: drifting particle field + neural AI core wired to scroll & mouse.
   Pure canvas, GPU-friendly, capped DPR, respects reduced-motion. */
(function () {
  const canvas = document.getElementById("core-canvas");
  const ctx = canvas.getContext("2d");
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const EM = {
    c1: "16,185,129",
    c2: "52,211,153",
    c3: "56,189,248"
  };
  let W, H, DPR;
  const mouse = {
    x: 0.5,
    y: 0.5,
    tx: 0.5,
    ty: 0.5
  };
  let scrollN = 0; // 0..1 through hero region

  function resize() {
    DPR = Math.min(window.devicePixelRatio || 1, 2);
    W = canvas.width = innerWidth * DPR;
    H = canvas.height = innerHeight * DPR;
    canvas.style.width = innerWidth + "px";
    canvas.style.height = innerHeight + "px";
    build();
  }

  /* ---- particle field ---- */
  let particles = [];
  function build() {
    const count = Math.min(120, Math.floor(innerWidth * innerHeight / 14000));
    particles = Array.from({
      length: count
    }, () => ({
      x: Math.random() * W,
      y: Math.random() * H,
      z: Math.random() * 0.8 + 0.2,
      vx: (Math.random() - 0.5) * 0.12,
      vy: (Math.random() - 0.5) * 0.12,
      r: Math.random() * 1.6 + 0.4
    }));
  }

  /* ---- neural core nodes (spherical projection) ---- */
  const NODES = 34;
  const core = Array.from({
    length: NODES
  }, (_, i) => {
    const phi = Math.acos(1 - 2 * (i + 0.5) / NODES);
    const theta = Math.PI * (1 + Math.sqrt(5)) * i;
    return {
      x: Math.sin(phi) * Math.cos(theta),
      y: Math.sin(phi) * Math.sin(theta),
      z: Math.cos(phi),
      p: Math.random() * Math.PI * 2
    };
  });
  function draw(t) {
    mouse.x += (mouse.tx - mouse.x) * 0.06;
    mouse.y += (mouse.ty - mouse.y) * 0.06;
    ctx.clearRect(0, 0, W, H);
    const cx = W * (0.5 + (mouse.x - 0.5) * 0.05);
    const cy = H * (0.46 + (mouse.y - 0.5) * 0.05);

    // ---- particles + links ----
    for (const p of particles) {
      p.x += p.vx * p.z * DPR + (mouse.x - 0.5) * 0.25 * p.z;
      p.y += p.vy * p.z * DPR + (mouse.y - 0.5) * 0.25 * p.z;
      if (p.x < 0) p.x += W;
      if (p.x > W) p.x -= W;
      if (p.y < 0) p.y += H;
      if (p.y > H) p.y -= H;
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r * DPR, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${EM.c2},${0.10 + p.z * 0.18})`;
      ctx.fill();
    }
    // subtle links between near particles
    ctx.lineWidth = DPR * 0.6;
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const a = particles[i],
          b = particles[j];
        const dx = a.x - b.x,
          dy = a.y - b.y,
          d2 = dx * dx + dy * dy;
        const max = (140 * DPR) ** 2;
        if (d2 < max) {
          const o = (1 - d2 / max) * 0.12;
          ctx.strokeStyle = `rgba(${EM.c1},${o})`;
          ctx.beginPath();
          ctx.moveTo(a.x, a.y);
          ctx.lineTo(b.x, b.y);
          ctx.stroke();
        }
      }
    }

    // ---- AI neural core ----
    const R = Math.min(W, H) * (0.20 - scrollN * 0.06);
    const rotY = t * 0.00022 + mouse.x * 0.8;
    const rotX = 0.5 + mouse.y * 0.5 + scrollN * 0.6;
    const cosY = Math.cos(rotY),
      sinY = Math.sin(rotY),
      cosX = Math.cos(rotX),
      sinX = Math.sin(rotX);
    const proj = core.map(n => {
      let x = n.x * cosY - n.z * sinY;
      let z = n.x * sinY + n.z * cosY;
      let y = n.y * cosX - z * sinX;
      z = n.y * sinX + z * cosX;
      const scale = 1 / (1.8 - z);
      return {
        sx: cx + x * R * scale,
        sy: cy + y * R * scale,
        z,
        scale,
        pulse: 0.5 + 0.5 * Math.sin(t * 0.002 + n.p)
      };
    });

    // halo
    const halo = ctx.createRadialGradient(cx, cy, R * 0.2, cx, cy, R * 2.4);
    halo.addColorStop(0, `rgba(${EM.c1},${0.14 * (1 - scrollN * 0.7)})`);
    halo.addColorStop(1, "rgba(0,0,0,0)");
    ctx.fillStyle = halo;
    ctx.fillRect(cx - R * 2.6, cy - R * 2.6, R * 5.2, R * 5.2);

    // connections
    ctx.lineWidth = DPR * 0.7;
    for (let i = 0; i < proj.length; i++) {
      for (let j = i + 1; j < proj.length; j++) {
        const a = proj[i],
          b = proj[j];
        const dx = a.sx - b.sx,
          dy = a.sy - b.sy,
          d2 = dx * dx + dy * dy;
        const max = (R * 0.82) ** 2;
        if (d2 < max) {
          const o = (1 - d2 / max) * 0.5 * Math.min(a.z + 1.2, 1) * (1 - scrollN * 0.6);
          ctx.strokeStyle = `rgba(${EM.c2},${o})`;
          ctx.beginPath();
          ctx.moveTo(a.sx, a.sy);
          ctx.lineTo(b.sx, b.sy);
          ctx.stroke();
        }
      }
    }
    // nodes
    for (const n of proj) {
      const rad = (1.4 + n.pulse * 2.4) * n.scale * DPR;
      const alpha = (0.4 + n.pulse * 0.6) * (1 - scrollN * 0.5);
      const g = ctx.createRadialGradient(n.sx, n.sy, 0, n.sx, n.sy, rad * 3);
      g.addColorStop(0, `rgba(${EM.c2},${alpha})`);
      g.addColorStop(1, "rgba(0,0,0,0)");
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.arc(n.sx, n.sy, rad * 3, 0, Math.PI * 2);
      ctx.fill();
      ctx.beginPath();
      ctx.arc(n.sx, n.sy, rad, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(230,255,245,${alpha})`;
      ctx.fill();
    }
    raf = requestAnimationFrame(draw);
  }
  let raf;
  function onScroll() {
    scrollN = Math.min(1, window.scrollY / (innerHeight * 0.9));
  }
  window.addEventListener("resize", resize);
  window.addEventListener("scroll", onScroll, {
    passive: true
  });
  window.addEventListener("pointermove", e => {
    mouse.tx = e.clientX / innerWidth;
    mouse.ty = e.clientY / innerHeight;
  });
  resize();
  if (reduce) {
    // draw a single static frame
    scrollN = 0;
    draw(0);
    cancelAnimationFrame(raf);
  } else {
    raf = requestAnimationFrame(draw);
  }
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "landing/core.js", error: String((e && e.message) || e) }); }

// landing/interactions.js
try { (() => {
/* Interaction layer: custom cursor, magnetic buttons, tilt cards, scroll reveals,
   hero word-reveal, awaken word-lighting, animated counters, orbit motion, typed AI. */
(function () {
  const reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const lerp = (a, b, n) => a + (b - a) * n;

  /* ---------- custom cursor ---------- */
  const dot = document.querySelector(".cursor-dot");
  const ring = document.querySelector(".cursor-ring");
  let mx = innerWidth / 2,
    my = innerHeight / 2,
    rx = mx,
    ry = my;
  if (dot && !matchMedia("(hover: none)").matches) {
    addEventListener("pointermove", e => {
      mx = e.clientX;
      my = e.clientY;
      dot.style.transform = `translate(${mx}px,${my}px) translate(-50%,-50%)`;
    });
    (function ring_loop() {
      rx = lerp(rx, mx, 0.18);
      ry = lerp(ry, my, 0.18);
      ring.style.transform = `translate(${rx}px,${ry}px) translate(-50%,-50%)`;
      requestAnimationFrame(ring_loop);
    })();
    const hot = () => ring.classList.add("hot"),
      cool = () => ring.classList.remove("hot");
    document.querySelectorAll("a, button, .btn, .tilt-card, .nav-link").forEach(el => {
      el.addEventListener("pointerenter", hot);
      el.addEventListener("pointerleave", cool);
    });
  }

  /* ---------- magnetic buttons ---------- */
  if (!reduce) document.querySelectorAll("[data-magnetic]").forEach(el => {
    el.addEventListener("pointermove", e => {
      const r = el.getBoundingClientRect();
      const dx = e.clientX - (r.left + r.width / 2),
        dy = e.clientY - (r.top + r.height / 2);
      el.style.transform = `translate(${dx * 0.28}px, ${dy * 0.4}px)`;
    });
    el.addEventListener("pointerleave", () => {
      el.style.transform = "";
    });
  });

  /* ---------- tilt cards + spotlight ---------- */
  if (!reduce) document.querySelectorAll(".tilt-card").forEach(card => {
    card.addEventListener("pointermove", e => {
      const r = card.getBoundingClientRect();
      const px = (e.clientX - r.left) / r.width,
        py = (e.clientY - r.top) / r.height;
      card.style.transform = `perspective(900px) rotateY(${(px - 0.5) * 9}deg) rotateX(${(0.5 - py) * 9}deg) translateY(-4px)`;
      card.style.setProperty("--mx", px * 100 + "%");
      card.style.setProperty("--my", py * 100 + "%");
    });
    card.addEventListener("pointerleave", () => {
      card.style.transform = "";
    });
  });

  /* ---------- scroll reveals ---------- */
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (en.isIntersecting) {
        en.target.classList.add("in");
        if (en.target.dataset.once !== undefined) io.unobserve(en.target);
      }
    });
  }, {
    threshold: 0.18
  });
  document.querySelectorAll(".r, .flow-step, .panel").forEach(el => io.observe(el));

  /* ---------- counters ---------- */
  const cio = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (!en.isIntersecting) return;
      const el = en.target,
        to = parseFloat(el.dataset.count),
        dec = el.dataset.dec ? +el.dataset.dec : 0,
        dur = 1400;
      const pre = el.dataset.pre || "",
        suf = el.dataset.suf || "";
      let start;
      const step = ts => {
        start ??= ts;
        const p = Math.min(1, (ts - start) / dur);
        const e = 1 - Math.pow(1 - p, 3);
        const v = to * e;
        el.textContent = pre + v.toLocaleString("en-US", {
          minimumFractionDigits: dec,
          maximumFractionDigits: dec
        }) + suf;
        if (p < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
      cio.unobserve(el);
    });
  }, {
    threshold: 0.5
  });
  document.querySelectorAll("[data-count]").forEach(el => cio.observe(el));

  /* ---------- hero word reveal ---------- */
  const heroWords = document.querySelectorAll(".reveal-word");
  heroWords.forEach((w, i) => {
    if (reduce) {
      w.style.opacity = 1;
      w.style.transform = "none";
      return;
    }
    w.animate([{
      opacity: 0,
      transform: "translateY(0.6em) rotateX(-40deg)"
    }, {
      opacity: 1,
      transform: "none"
    }], {
      duration: 900,
      delay: 260 + i * 85,
      easing: "cubic-bezier(0.16,1,0.3,1)",
      fill: "forwards"
    });
  });

  /* ---------- nav stuck + progress ---------- */
  const nav = document.querySelector(".nav"),
    prog = document.querySelector(".progress");
  const onScroll = () => {
    nav.classList.toggle("stuck", scrollY > 40);
    const max = document.body.scrollHeight - innerHeight;
    prog.style.width = (max > 0 ? scrollY / max * 100 : 0) + "%";
    lightAwaken();
    orbitTick();
  };
  addEventListener("scroll", onScroll, {
    passive: true
  });

  /* ---------- awaken word lighting ---------- */
  const awaken = document.querySelector(".awaken");
  const fadeWords = awaken ? [...awaken.querySelectorAll(".fade-word")] : [];
  function lightAwaken() {
    if (!awaken) return;
    const r = awaken.getBoundingClientRect();
    const prog = Math.min(1, Math.max(0, (innerHeight * 0.75 - r.top) / (r.height * 0.55)));
    const lit = Math.floor(prog * fadeWords.length);
    fadeWords.forEach((w, i) => w.classList.toggle("lit", i < lit));
  }

  /* ---------- orbit motion ---------- */
  const nodes = [...document.querySelectorAll(".orbit-node")];
  const stage = document.querySelector(".orbit-stage");
  function orbitTick() {
    if (!stage) return;
    const t = performance.now() * 0.00018;
    const r = stage.getBoundingClientRect();
    const cx = r.width / 2,
      cy = r.height / 2;
    nodes.forEach((n, i) => {
      const radius = +n.dataset.r * Math.min(cx, cy);
      const a = t * +n.dataset.speed + i / nodes.length * Math.PI * 2;
      const x = cx + Math.cos(a) * radius,
        y = cy + Math.sin(a) * radius * 0.62;
      n.style.transform = `translate(${x}px, ${y}px) translate(-50%, -50%)`;
      n.style.zIndex = y > cy ? 4 : 2;
      n.style.opacity = 0.55 + 0.45 * ((Math.sin(a) + 1) / 2);
    });
  }
  if (nodes.length && !reduce) (function loop() {
    orbitTick();
    requestAnimationFrame(loop);
  })();else orbitTick();

  /* ---------- typed AI console ---------- */
  const typed = document.querySelector(".ai-typed");
  if (typed) {
    const full = typed.dataset.text;
    const tio = new IntersectionObserver(e => {
      if (!e[0].isIntersecting) return;
      if (reduce) {
        typed.textContent = full;
        typed.classList.remove("ai-typed");
        tio.disconnect();
        return;
      }
      let i = 0;
      typed.textContent = "";
      const tick = () => {
        typed.textContent = full.slice(0, i++);
        if (i <= full.length) setTimeout(tick, 22);else typed.classList.remove("ai-typed");
      };
      tick();
      tio.disconnect();
    }, {
      threshold: 0.6
    });
    tio.observe(typed.closest(".panel") || typed);
  }
  onScroll();
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "landing/interactions.js", error: String((e && e.message) || e) }); }

// ui_kits/erp-app/app.jsx
try { (() => {
// ERP kit shell: login, sidebar, topbar, command palette, router.
const {
  Icon,
  Btn,
  Badge
} = window.ERP_UI;
const {
  Dashboard,
  Products,
  Crm,
  Assistant,
  Inventory,
  Placeholder,
  Conversation
} = window.ERP_PAGES;
const {
  Partners,
  Documents,
  Reports,
  Users
} = window.ERP_MODULES;
const DAT = window.ERP_DATA;
function Login({
  onLogin
}) {
  const [email, setEmail] = React.useState("admin@erp.local");
  const [pw, setPw] = React.useState("Admin123!");
  return /*#__PURE__*/React.createElement("div", {
    style: {
      minHeight: "100vh",
      display: "grid",
      gridTemplateColumns: "1fr 1fr",
      background: "var(--bg-app)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      overflow: "hidden",
      borderRight: "1px solid var(--border-subtle)",
      background: "radial-gradient(120% 100% at 0% 0%, color-mix(in oklab, var(--emerald-500) 14%, var(--bg-app)), var(--bg-app) 60%)",
      padding: 56,
      display: "flex",
      flexDirection: "column",
      justifyContent: "space-between"
    }
  }, /*#__PURE__*/React.createElement(Brand, null), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      font: "600 44px/1.05 var(--font-sans)",
      letterSpacing: "-.035em",
      color: "var(--text-strong)",
      maxWidth: 440
    }
  }, "The ERP that thinks alongside you."), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: "20px 0 0",
      font: "400 16px/1.6 var(--font-sans)",
      color: "var(--text-muted)",
      maxWidth: 400
    }
  }, "Inventory, sales, purchasing and CRM \u2014 with a conversational AI agent that queries your data and acts, with your approval.")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10,
      font: "400 13px/1 var(--font-sans)",
      color: "var(--text-faint)"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 7,
      height: 7,
      borderRadius: 999,
      background: "var(--emerald-400)"
    }
  }), " Local model online \xB7 your data never leaves your servers")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      placeItems: "center",
      padding: 40
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: "100%",
      maxWidth: 360
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: 0,
      font: "600 26px/1 var(--font-sans)",
      letterSpacing: "-.02em",
      color: "var(--text-strong)"
    }
  }, "Sign in"), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: "8px 0 28px",
      font: "400 14px/1 var(--font-sans)",
      color: "var(--text-muted)"
    }
  }, "Welcome back to your workspace."), /*#__PURE__*/React.createElement("form", {
    onSubmit: e => {
      e.preventDefault();
      onLogin();
    },
    style: {
      display: "flex",
      flexDirection: "column",
      gap: 16
    }
  }, /*#__PURE__*/React.createElement(Field, {
    label: "Email"
  }, /*#__PURE__*/React.createElement("input", {
    className: "erp-field",
    value: email,
    onChange: e => setEmail(e.target.value)
  })), /*#__PURE__*/React.createElement(Field, {
    label: "Password"
  }, /*#__PURE__*/React.createElement("input", {
    className: "erp-field",
    type: "password",
    value: pw,
    onChange: e => setPw(e.target.value)
  })), /*#__PURE__*/React.createElement("button", {
    className: "erp-btn erp-btn--primary erp-btn--lg",
    style: {
      marginTop: 6,
      width: "100%"
    },
    type: "submit"
  }, "Sign in ", /*#__PURE__*/React.createElement(Icon, {
    name: "arrow-right",
    size: 16
  }))), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: "20px 0 0",
      font: "400 12px/1.5 var(--font-mono)",
      color: "var(--text-faint)"
    }
  }, "Demo \xB7 admin@erp.local / Admin123!"))));
}
function Field({
  label,
  children
}) {
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: 8
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      font: "500 13px/1 var(--font-sans)",
      color: "var(--text-body)"
    }
  }, label), children);
}
function Brand({
  small
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: small ? 30 : 36,
      height: small ? 30 : 36,
      borderRadius: 10,
      background: "var(--emerald-500)",
      display: "grid",
      placeItems: "center",
      color: "var(--text-on-accent)",
      boxShadow: "var(--shadow-accent)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "hexagon",
    size: small ? 17 : 20
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "600 17px/1 var(--font-sans)",
      letterSpacing: "-.02em",
      color: "var(--text-strong)"
    }
  }, "Intelligent", /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--emerald-400)"
    }
  }, "ERP")));
}
function Sidebar({
  page,
  setPage,
  collapsed,
  setCollapsed
}) {
  const w = collapsed ? "var(--sidebar-w-collapsed)" : "var(--sidebar-w)";
  return /*#__PURE__*/React.createElement("aside", {
    style: {
      width: w,
      flex: "none",
      display: "flex",
      flexDirection: "column",
      background: "var(--bg-panel)",
      borderRight: "1px solid var(--border-subtle)",
      transition: "width var(--dur-3) var(--ease-out)",
      overflow: "hidden"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      height: "var(--topbar-h)",
      display: "flex",
      alignItems: "center",
      justifyContent: collapsed ? "center" : "space-between",
      padding: collapsed ? 0 : "0 18px",
      borderBottom: "1px solid var(--border-subtle)"
    }
  }, collapsed ? /*#__PURE__*/React.createElement("div", {
    style: {
      width: 32,
      height: 32,
      borderRadius: 9,
      background: "var(--emerald-500)",
      display: "grid",
      placeItems: "center",
      color: "var(--text-on-accent)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "hexagon",
    size: 18
  })) : /*#__PURE__*/React.createElement(Brand, {
    small: true
  }), !collapsed && /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--sm",
    onClick: () => setCollapsed(true),
    "aria-label": "collapse"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "panel-left-close",
    size: 16
  }))), collapsed && /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--md",
    onClick: () => setCollapsed(false),
    "aria-label": "expand",
    style: {
      margin: "10px auto 0"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "panel-left-open",
    size: 16
  })), /*#__PURE__*/React.createElement("nav", {
    style: {
      flex: 1,
      padding: collapsed ? "10px 12px" : "12px 12px",
      display: "flex",
      flexDirection: "column",
      gap: 2,
      overflowY: "auto"
    }
  }, !collapsed && /*#__PURE__*/React.createElement("div", {
    className: "ds-eyebrow",
    style: {
      padding: "10px 10px 6px"
    }
  }, "Workspace"), DAT.nav.map(n => {
    const active = page === n.to;
    return /*#__PURE__*/React.createElement("button", {
      key: n.to,
      onClick: () => setPage(n.to),
      title: n.label,
      style: {
        display: "flex",
        alignItems: "center",
        gap: 12,
        justifyContent: collapsed ? "center" : "flex-start",
        padding: collapsed ? "10px 0" : "9px 10px",
        borderRadius: 10,
        border: "none",
        cursor: "pointer",
        position: "relative",
        background: active ? "var(--surface-hover)" : "transparent",
        color: active ? "var(--text-strong)" : "var(--text-muted)",
        font: "500 14px/1 var(--font-sans)",
        transition: "background var(--dur-1), color var(--dur-1)"
      },
      onMouseEnter: e => {
        if (!active) e.currentTarget.style.background = "color-mix(in oklab, var(--surface-hover) 55%, transparent)";
      },
      onMouseLeave: e => {
        if (!active) e.currentTarget.style.background = "transparent";
      }
    }, active && !collapsed && /*#__PURE__*/React.createElement("span", {
      style: {
        position: "absolute",
        left: 0,
        top: 8,
        bottom: 8,
        width: 3,
        borderRadius: 999,
        background: "var(--emerald-400)"
      }
    }), /*#__PURE__*/React.createElement(Icon, {
      name: n.icon,
      size: 18,
      color: active ? "var(--emerald-400)" : undefined
    }), !collapsed && /*#__PURE__*/React.createElement("span", null, n.label), !collapsed && n.to === "assistant" && /*#__PURE__*/React.createElement("span", {
      style: {
        marginLeft: "auto",
        width: 6,
        height: 6,
        borderRadius: 999,
        background: "var(--emerald-400)"
      }
    }));
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 12,
      borderTop: "1px solid var(--border-subtle)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10,
      padding: collapsed ? 0 : "6px 8px",
      justifyContent: collapsed ? "center" : "flex-start"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 34,
      height: 34,
      borderRadius: 999,
      background: "linear-gradient(135deg,var(--emerald-500),var(--emerald-700))",
      display: "grid",
      placeItems: "center",
      color: "var(--text-on-accent)",
      font: "600 13px/1 var(--font-sans)",
      flex: "none"
    }
  }, "AK"), !collapsed && /*#__PURE__*/React.createElement("div", {
    style: {
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      font: "500 13px/1.2 var(--font-sans)",
      color: "var(--text-strong)",
      overflow: "hidden",
      textOverflow: "ellipsis",
      whiteSpace: "nowrap"
    }
  }, "Amine Khelifi"), /*#__PURE__*/React.createElement("div", {
    style: {
      font: "400 11px/1 var(--font-sans)",
      color: "var(--text-faint)",
      textTransform: "uppercase",
      letterSpacing: ".1em",
      marginTop: 3
    }
  }, "Admin")))));
}
function Topbar({
  onOpenCmd,
  onLogout,
  onAskAi
}) {
  return /*#__PURE__*/React.createElement("header", {
    style: {
      height: "var(--topbar-h)",
      flex: "none",
      display: "flex",
      alignItems: "center",
      gap: 16,
      padding: "0 24px",
      borderBottom: "1px solid var(--border-subtle)",
      background: "color-mix(in oklab, var(--bg-app) 82%, transparent)",
      backdropFilter: "blur(12px)",
      position: "sticky",
      top: 0,
      zIndex: 20
    }
  }, /*#__PURE__*/React.createElement("button", {
    onClick: onOpenCmd,
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10,
      width: 340,
      maxWidth: "40vw",
      height: 38,
      padding: "0 12px",
      borderRadius: 10,
      background: "var(--surface-inset)",
      border: "1px solid var(--border)",
      color: "var(--text-faint)",
      cursor: "pointer",
      font: "400 13px/1 var(--font-sans)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "search",
    size: 16
  }), " Search or ask anything\u2026", /*#__PURE__*/React.createElement("span", {
    style: {
      marginLeft: "auto",
      font: "500 11px/1 var(--font-mono)",
      background: "var(--surface-hover)",
      padding: "3px 6px",
      borderRadius: 6
    }
  }, "\u2318K")), /*#__PURE__*/React.createElement("div", {
    style: {
      marginLeft: "auto",
      display: "flex",
      alignItems: "center",
      gap: 8
    }
  }, /*#__PURE__*/React.createElement("button", {
    className: "erp-btn erp-btn--secondary erp-btn--sm",
    onClick: onAskAi
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "sparkles",
    size: 15,
    color: "var(--emerald-400)"
  }), " Ask AI"), /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--md",
    "aria-label": "notifications",
    style: {
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "bell",
    size: 18
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      top: 8,
      right: 8,
      width: 7,
      height: 7,
      borderRadius: 999,
      background: "var(--rose-400)",
      border: "2px solid var(--bg-app)"
    }
  })), /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--md",
    "aria-label": "theme"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "moon",
    size: 18
  })), /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--md",
    onClick: onLogout,
    "aria-label": "sign out"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "log-out",
    size: 18
  }))));
}
const CMD_ITEMS = [{
  icon: "plus",
  label: "New product",
  hint: "Create"
}, {
  icon: "user-plus",
  label: "New lead",
  hint: "Create"
}, {
  icon: "sparkles",
  label: "Ask the AI assistant",
  hint: "AI"
}, {
  icon: "package",
  label: "Go to Products",
  hint: "Navigate"
}, {
  icon: "contact",
  label: "Go to CRM",
  hint: "Navigate"
}, {
  icon: "file-text",
  label: "Export monthly report",
  hint: "Action"
}];
function CommandPalette({
  open,
  onClose
}) {
  if (!open) return null;
  return /*#__PURE__*/React.createElement("div", {
    className: "erp-dialog-backdrop",
    style: {
      alignItems: "flex-start",
      paddingTop: "14vh"
    },
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    className: "erp-dialog",
    style: {
      maxWidth: 560
    },
    onClick: e => e.stopPropagation()
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10,
      padding: "16px 18px",
      borderBottom: "1px solid var(--border-subtle)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "search",
    size: 18,
    color: "var(--text-muted)"
  }), /*#__PURE__*/React.createElement("input", {
    autoFocus: true,
    className: "erp-field",
    style: {
      border: "none",
      background: "transparent",
      height: 24,
      padding: 0,
      fontSize: 15
    },
    placeholder: "Type a command or search\u2026"
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "500 11px/1 var(--font-mono)",
      color: "var(--text-faint)",
      background: "var(--surface-hover)",
      padding: "4px 7px",
      borderRadius: 6
    }
  }, "ESC")), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 8
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "ds-eyebrow",
    style: {
      padding: "8px 10px 6px"
    }
  }, "Quick actions"), CMD_ITEMS.map(c => /*#__PURE__*/React.createElement("button", {
    key: c.label,
    onClick: onClose,
    style: {
      width: "100%",
      display: "flex",
      alignItems: "center",
      gap: 12,
      padding: "10px 10px",
      borderRadius: 10,
      border: "none",
      background: "transparent",
      cursor: "pointer",
      color: "var(--text-body)",
      font: "500 14px/1 var(--font-sans)"
    },
    onMouseEnter: e => e.currentTarget.style.background = "var(--surface-hover)",
    onMouseLeave: e => e.currentTarget.style.background = "transparent"
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 30,
      height: 30,
      borderRadius: 8,
      background: "var(--surface-hover)",
      display: "grid",
      placeItems: "center",
      color: "var(--text-muted)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: c.icon,
    size: 16
  })), c.label, /*#__PURE__*/React.createElement("span", {
    style: {
      marginLeft: "auto",
      font: "500 11px/1 var(--font-sans)",
      color: "var(--text-faint)",
      textTransform: "uppercase",
      letterSpacing: ".08em"
    }
  }, c.hint))))));
}
function FloatingAssistant({
  mode,
  setMode
}) {
  const open = mode !== "closed";
  const full = mode === "full";
  React.useEffect(() => {
    const h = e => {
      if (e.key === "Escape" && open) setMode("closed");
    };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, [open]);
  if (!open) {
    return /*#__PURE__*/React.createElement("button", {
      className: "erp-fab",
      onClick: () => setMode("dock"),
      "aria-label": "Open AI assistant",
      style: {
        position: "fixed",
        right: 28,
        bottom: 28,
        zIndex: 60,
        width: 58,
        height: 58,
        borderRadius: 999,
        border: "none",
        cursor: "pointer",
        background: "linear-gradient(135deg, var(--emerald-400), var(--emerald-600))",
        color: "var(--text-on-accent)",
        display: "grid",
        placeItems: "center",
        boxShadow: "0 10px 34px var(--emerald-glow), var(--hairline)"
      }
    }, /*#__PURE__*/React.createElement(Icon, {
      name: "sparkles",
      size: 24
    }));
  }
  const shell = full ? {
    position: "fixed",
    inset: 0,
    zIndex: 60,
    borderRadius: 0,
    border: "none",
    animation: "full-in var(--dur-3) var(--ease-out)"
  } : {
    position: "fixed",
    right: 24,
    bottom: 24,
    zIndex: 60,
    width: "min(420px, calc(100vw - 32px))",
    height: "min(640px, calc(100vh - 48px))",
    borderRadius: "var(--radius-xl)",
    border: "1px solid var(--border)",
    animation: "dock-in var(--dur-3) var(--ease-out)"
  };
  const body = /*#__PURE__*/React.createElement("div", {
    style: {
      ...shell,
      background: "var(--surface-card)",
      boxShadow: "var(--shadow-xl), var(--hairline)",
      display: "flex",
      flexDirection: "column",
      overflow: "hidden"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 12,
      padding: "14px 16px",
      borderBottom: "1px solid var(--border-subtle)",
      flex: "none"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 34,
      height: 34,
      borderRadius: 10,
      background: "var(--emerald-glow)",
      display: "grid",
      placeItems: "center",
      color: "var(--emerald-400)",
      flex: "none"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "sparkles",
    size: 18
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      minWidth: 0
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      font: "600 15px/1 var(--font-sans)",
      color: "var(--text-strong)"
    }
  }, "AI Assistant"), /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 6,
      font: "400 11px/1 var(--font-sans)",
      color: "var(--text-muted)",
      marginTop: 4
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 5,
      height: 5,
      borderRadius: 999,
      background: "var(--emerald-400)"
    }
  }), " Approval-first \xB7 local model")), /*#__PURE__*/React.createElement("div", {
    style: {
      marginLeft: "auto",
      display: "flex",
      gap: 2
    }
  }, /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--sm",
    onClick: () => setMode(full ? "dock" : "full"),
    "aria-label": full ? "Exit fullscreen" : "Fullscreen"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: full ? "minimize-2" : "maximize-2",
    size: 16
  })), /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--sm",
    onClick: () => setMode("closed"),
    "aria-label": "Close assistant"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "x",
    size: 16
  })))), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minHeight: 0,
      display: "flex",
      flexDirection: "column",
      padding: full ? "20px" : "14px",
      maxWidth: full ? 860 : "none",
      width: "100%",
      margin: full ? "0 auto" : 0
    }
  }, /*#__PURE__*/React.createElement(Conversation, {
    compact: !full
  })));
  if (full) return /*#__PURE__*/React.createElement("div", {
    className: "erp-dialog-backdrop",
    style: {
      padding: 0,
      alignItems: "stretch",
      justifyContent: "stretch"
    }
  }, body);
  return body;
}
function App() {
  const [authed, setAuthed] = React.useState(true);
  const [page, setPage] = React.useState("dashboard");
  const [collapsed, setCollapsed] = React.useState(false);
  const [cmd, setCmd] = React.useState(false);
  const [ai, setAi] = React.useState("closed");
  React.useEffect(() => {
    const h = e => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
        e.preventDefault();
        setCmd(v => !v);
      }
      if (e.key === "Escape") setCmd(false);
    };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, []);
  if (!authed) return /*#__PURE__*/React.createElement(Login, {
    onLogin: () => setAuthed(true)
  });
  const PAGES = {
    dashboard: /*#__PURE__*/React.createElement(Dashboard, null),
    products: /*#__PURE__*/React.createElement(Products, null),
    crm: /*#__PURE__*/React.createElement(Crm, null),
    assistant: /*#__PURE__*/React.createElement(Assistant, null),
    inventory: /*#__PURE__*/React.createElement(Inventory, null),
    customers: /*#__PURE__*/React.createElement(Partners, {
      kind: "customers"
    }),
    suppliers: /*#__PURE__*/React.createElement(Partners, {
      kind: "suppliers"
    }),
    purchases: /*#__PURE__*/React.createElement(Documents, {
      kind: "purchases"
    }),
    sales: /*#__PURE__*/React.createElement(Documents, {
      kind: "sales"
    }),
    reports: /*#__PURE__*/React.createElement(Reports, null),
    users: /*#__PURE__*/React.createElement(Users, null)
  };
  const labels = {};
  const content = PAGES[page] || /*#__PURE__*/React.createElement(Placeholder, {
    label: labels[page] || "Module"
  });
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      minHeight: "100vh",
      background: "var(--bg-app)"
    }
  }, /*#__PURE__*/React.createElement(Sidebar, {
    page: page,
    setPage: setPage,
    collapsed: collapsed,
    setCollapsed: setCollapsed
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      flex: 1,
      minWidth: 0,
      display: "flex",
      flexDirection: "column"
    }
  }, /*#__PURE__*/React.createElement(Topbar, {
    onOpenCmd: () => setCmd(true),
    onLogout: () => setAuthed(false),
    onAskAi: () => setAi("dock")
  }), /*#__PURE__*/React.createElement("main", {
    style: {
      flex: 1,
      padding: "36px 40px",
      maxWidth: 1320,
      width: "100%",
      margin: "0 auto"
    },
    key: page,
    className: "erp-page-enter"
  }, content)), /*#__PURE__*/React.createElement(CommandPalette, {
    open: cmd,
    onClose: () => setCmd(false)
  }), page !== "assistant" && /*#__PURE__*/React.createElement(FloatingAssistant, {
    mode: ai,
    setMode: setAi
  }));
}
ReactDOM.createRoot(document.getElementById("root")).render(/*#__PURE__*/React.createElement(App, null));
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/erp-app/app.jsx", error: String((e && e.message) || e) }); }

// ui_kits/erp-app/data.js
try { (() => {
// Mock data for the Intelligent ERP UI kit (matches src/types shapes).
window.ERP_DATA = {
  user: {
    first_name: "Amine",
    last_name: "Khelifi",
    email: "admin@erp.local",
    role: "admin"
  },
  kpis: [{
    label: "Revenue",
    value: "48,250",
    unit: "TND",
    delta: 12.4,
    spark: [22, 26, 24, 30, 28, 34, 33, 40, 44, 48]
  }, {
    label: "Sales orders",
    value: "184",
    delta: 4.1,
    tone: "neutral",
    spark: [12, 14, 13, 15, 14, 16, 15, 17, 18, 18]
  }, {
    label: "Purchase orders",
    value: "62",
    delta: -2.3,
    tone: "neutral",
    spark: [9, 8, 10, 9, 8, 7, 8, 7, 6, 6]
  }, {
    label: "Purchases",
    value: "21,900",
    unit: "TND",
    delta: 6.8,
    tone: "neutral",
    spark: [10, 12, 11, 14, 13, 16, 15, 18, 20, 22]
  }],
  revenueSeries: [28, 31, 30, 35, 33, 38, 36, 42, 40, 45, 43, 49, 47, 52, 50, 56],
  topProducts: [{
    sku: "ERP-0042",
    name: "Aluminium bracket 40mm",
    sold: 320,
    revenue: "7,840"
  }, {
    sku: "ERP-0088",
    name: "Copper coil 2.5mm",
    sold: 148,
    revenue: "6,512"
  }, {
    sku: "ERP-0051",
    name: "Rubber gasket set",
    sold: 890,
    revenue: "3,916"
  }, {
    sku: "ERP-0119",
    name: "Stainless bolt M8",
    sold: 1240,
    revenue: "2,480"
  }],
  lowStock: [{
    sku: "ERP-0043",
    name: "Steel hinge 60mm",
    qty: 8,
    min: 25
  }, {
    sku: "ERP-0072",
    name: "PVC elbow joint",
    qty: 14,
    min: 40
  }, {
    sku: "ERP-0210",
    name: "Brass fitting 1/2\"",
    qty: 3,
    min: 30
  }],
  products: [{
    sku: "ERP-0042",
    name: "Aluminium bracket 40mm",
    cat: "Hardware",
    stock: 142,
    unit: "pcs",
    price: "24.50",
    active: true,
    low: false
  }, {
    sku: "ERP-0043",
    name: "Steel hinge 60mm",
    cat: "Hardware",
    stock: 8,
    unit: "pcs",
    price: "6.20",
    active: true,
    low: true
  }, {
    sku: "ERP-0051",
    name: "Rubber gasket set",
    cat: "Seals",
    stock: 326,
    unit: "set",
    price: "1.10",
    active: true,
    low: false
  }, {
    sku: "ERP-0072",
    name: "PVC elbow joint",
    cat: "Plumbing",
    stock: 14,
    unit: "pcs",
    price: "3.40",
    active: true,
    low: true
  }, {
    sku: "ERP-0088",
    name: "Copper coil 2.5mm",
    cat: "Electrical",
    stock: 54,
    unit: "m",
    price: "44.00",
    active: true,
    low: false
  }, {
    sku: "ERP-0119",
    name: "Stainless bolt M8",
    cat: "Hardware",
    stock: 1240,
    unit: "pcs",
    price: "0.20",
    active: true,
    low: false
  }, {
    sku: "ERP-0210",
    name: "Brass fitting 1/2\"",
    cat: "Plumbing",
    stock: 3,
    unit: "pcs",
    price: "5.80",
    active: false,
    low: true
  }],
  leads: {
    new: [{
      id: 1,
      name: "Sofia Trabelsi",
      company: "Medina Textiles",
      phone: "+216 22 145 900"
    }, {
      id: 2,
      name: "Karim Zouari",
      company: "Zouari Logistics",
      phone: "+216 98 231 774"
    }],
    contacted: [{
      id: 3,
      name: "Leila Mansour",
      company: "Atlas Foods",
      phone: "+216 55 620 118"
    }],
    qualified: [{
      id: 4,
      name: "Omar Belhaj",
      company: "BelTech",
      phone: "+216 71 004 552"
    }, {
      id: 5,
      name: "Nadia Cherif",
      company: "Cherif & Co",
      phone: "+216 29 887 210"
    }],
    won: [{
      id: 6,
      name: "Youssef Gharbi",
      company: "Gharbi Retail",
      phone: "+216 50 119 663"
    }],
    lost: [{
      id: 7,
      name: "Rania Ben Salah",
      company: "—",
      phone: "+216 24 700 401"
    }]
  },
  warehouses: [{
    id: 1,
    name: "Central Depot",
    def: true
  }, {
    id: 2,
    name: "Sfax Branch",
    def: false
  }, {
    id: 3,
    name: "Sousse Store",
    def: false
  }],
  movements: [{
    id: 1,
    at: "Today · 14:22",
    sku: "ERP-0088",
    name: "Copper coil 2.5mm",
    type: "in",
    qty: 120,
    wh: "Central Depot",
    reason: "PO-2041 receipt",
    src: "purchase",
    by: "amine@erp.local"
  }, {
    id: 2,
    at: "Today · 11:05",
    sku: "ERP-0119",
    name: "Stainless bolt M8",
    type: "out",
    qty: 400,
    wh: "Central Depot",
    reason: "SO-3312 shipment",
    src: "sale",
    by: "sofia@erp.local"
  }, {
    id: 3,
    at: "Yesterday · 17:40",
    sku: "ERP-0210",
    name: "Brass fitting 1/2\"",
    type: "adjustment",
    qty: -6,
    wh: "Sfax Branch",
    reason: "Damage — recount",
    src: "manual",
    by: "amine@erp.local"
  }, {
    id: 4,
    at: "Yesterday · 09:12",
    sku: "ERP-0051",
    name: "Rubber gasket set",
    type: "transfer",
    qty: 80,
    wh: "Central → Sousse",
    reason: "Rebalance stock",
    src: "transfer",
    by: "karim@erp.local"
  }, {
    id: 5,
    at: "Mar 12 · 16:31",
    sku: "ERP-0042",
    name: "Aluminium bracket 40mm",
    type: "in",
    qty: 200,
    wh: "Central Depot",
    reason: "Initial stock",
    src: "manual",
    by: "amine@erp.local"
  }, {
    id: 6,
    at: "Mar 12 · 10:08",
    sku: "ERP-0043",
    name: "Steel hinge 60mm",
    type: "out",
    qty: 17,
    wh: "Sfax Branch",
    reason: "SO-3298 shipment",
    src: "sale",
    by: "sofia@erp.local"
  }],
  customers: [{
    id: 1,
    name: "Gharbi Retail",
    email: "contact@gharbi.tn",
    phone: "+216 50 119 663",
    city: "Tunis",
    orders: 42,
    active: true
  }, {
    id: 2,
    name: "Medina Textiles",
    email: "hello@medinatex.tn",
    phone: "+216 22 145 900",
    city: "Sfax",
    orders: 18,
    active: true
  }, {
    id: 3,
    name: "Atlas Foods",
    email: "purchasing@atlasfoods.tn",
    phone: "+216 55 620 118",
    city: "Sousse",
    orders: 27,
    active: true
  }, {
    id: 4,
    name: "BelTech",
    email: "info@beltech.tn",
    phone: "+216 71 004 552",
    city: "Ariana",
    orders: 9,
    active: true
  }, {
    id: 5,
    name: "Cherif & Co",
    email: "",
    phone: "+216 29 887 210",
    city: "Bizerte",
    orders: 4,
    active: false
  }],
  suppliers: [{
    id: 1,
    name: "MetalWorks SARL",
    email: "sales@metalworks.tn",
    phone: "+216 71 330 210",
    city: "Tunis",
    orders: 61,
    active: true
  }, {
    id: 2,
    name: "Poly Distribution",
    email: "orders@polydist.tn",
    phone: "+216 74 118 004",
    city: "Sfax",
    orders: 33,
    active: true
  }, {
    id: 3,
    name: "ElectroSupply",
    email: "b2b@electrosupply.tn",
    phone: "+216 73 550 900",
    city: "Sousse",
    orders: 22,
    active: true
  }, {
    id: 4,
    name: "Fastener Depot",
    email: "",
    phone: "+216 70 221 447",
    city: "Nabeul",
    orders: 7,
    active: false
  }],
  purchases: [{
    number: "PO-2041",
    partner: "MetalWorks SARL",
    date: "2026-07-09",
    status: "received",
    total: "5,280.00",
    by: "amine@erp.local",
    lines: [["ERP-0088", "Copper coil 2.5mm", 120, "44.00"], ["ERP-0042", "Aluminium bracket 40mm", 200, "24.50"]]
  }, {
    number: "PO-2040",
    partner: "Poly Distribution",
    date: "2026-07-07",
    status: "confirmed",
    total: "1,960.00",
    by: "karim@erp.local",
    lines: [["ERP-0072", "PVC elbow joint", 400, "3.40"], ["ERP-0051", "Rubber gasket set", 200, "1.10"]]
  }, {
    number: "PO-2039",
    partner: "ElectroSupply",
    date: "2026-07-05",
    status: "pending_approval",
    total: "8,800.00",
    by: "karim@erp.local",
    lines: [["ERP-0088", "Copper coil 2.5mm", 200, "44.00"]]
  }, {
    number: "PO-2038",
    partner: "Fastener Depot",
    date: "2026-07-02",
    status: "draft",
    total: "248.00",
    by: "amine@erp.local",
    lines: [["ERP-0119", "Stainless bolt M8", 1240, "0.20"]]
  }, {
    number: "PO-2037",
    partner: "MetalWorks SARL",
    date: "2026-06-28",
    status: "cancelled",
    total: "1,470.00",
    by: "amine@erp.local",
    lines: [["ERP-0043", "Steel hinge 60mm", 50, "6.20"]]
  }],
  sales: [{
    number: "SO-3312",
    partner: "Gharbi Retail",
    date: "2026-07-11",
    status: "confirmed",
    total: "3,140.00",
    by: "sofia@erp.local",
    lines: [["ERP-0119", "Stainless bolt M8", 400, "0.35"], ["ERP-0042", "Aluminium bracket 40mm", 100, "29.90"]]
  }, {
    number: "SO-3311",
    partner: "Atlas Foods",
    date: "2026-07-10",
    status: "received",
    total: "890.00",
    by: "sofia@erp.local",
    lines: [["ERP-0051", "Rubber gasket set", 600, "1.48"]]
  }, {
    number: "SO-3310",
    partner: "Medina Textiles",
    date: "2026-07-08",
    status: "draft",
    total: "1,196.00",
    by: "sofia@erp.local",
    lines: [["ERP-0088", "Copper coil 2.5mm", 20, "59.80"]]
  }, {
    number: "SO-3309",
    partner: "BelTech",
    date: "2026-07-06",
    status: "confirmed",
    total: "418.00",
    by: "omar@erp.local",
    lines: [["ERP-0072", "PVC elbow joint", 110, "3.80"]]
  }, {
    number: "SO-3308",
    partner: "Gharbi Retail",
    date: "2026-07-03",
    status: "cancelled",
    total: "220.00",
    by: "sofia@erp.local",
    lines: [["ERP-0043", "Steel hinge 60mm", 20, "11.00"]]
  }],
  users: [{
    email: "amine@erp.local",
    first: "Amine",
    last: "Khelifi",
    role: "admin",
    active: true
  }, {
    email: "sofia@erp.local",
    first: "Sofia",
    last: "Trabelsi",
    role: "manager",
    active: true
  }, {
    email: "karim@erp.local",
    first: "Karim",
    last: "Zouari",
    role: "manager",
    active: true
  }, {
    email: "omar@erp.local",
    first: "Omar",
    last: "Belhaj",
    role: "employee",
    active: true
  }, {
    email: "leila@erp.local",
    first: "Leila",
    last: "Mansour",
    role: "employee",
    active: false
  }],
  reportRows: {
    sales: [{
      number: "SO-3312",
      date: "2026-07-11",
      partner: "Gharbi Retail",
      status: "confirmed",
      total: "3,140.00"
    }, {
      number: "SO-3311",
      date: "2026-07-10",
      partner: "Atlas Foods",
      status: "received",
      total: "890.00"
    }, {
      number: "SO-3309",
      date: "2026-07-06",
      partner: "BelTech",
      status: "confirmed",
      total: "418.00"
    }, {
      number: "SO-3308",
      date: "2026-07-03",
      partner: "Gharbi Retail",
      status: "cancelled",
      total: "220.00"
    }],
    purchases: [{
      number: "PO-2041",
      date: "2026-07-09",
      partner: "MetalWorks SARL",
      status: "received",
      total: "5,280.00"
    }, {
      number: "PO-2040",
      date: "2026-07-07",
      partner: "Poly Distribution",
      status: "confirmed",
      total: "1,960.00"
    }, {
      number: "PO-2039",
      date: "2026-07-05",
      partner: "ElectroSupply",
      status: "pending_approval",
      total: "8,800.00"
    }],
    stock: [{
      sku: "ERP-0042",
      name: "Aluminium bracket 40mm",
      cat: "Hardware",
      qty: 142,
      min: 40,
      value: "3,479.00",
      low: false
    }, {
      sku: "ERP-0043",
      name: "Steel hinge 60mm",
      cat: "Hardware",
      qty: 8,
      min: 25,
      value: "49.60",
      low: true
    }, {
      sku: "ERP-0088",
      name: "Copper coil 2.5mm",
      cat: "Electrical",
      qty: 54,
      min: 20,
      value: "2,376.00",
      low: false
    }, {
      sku: "ERP-0210",
      name: "Brass fitting 1/2\"",
      cat: "Plumbing",
      qty: 3,
      min: 30,
      value: "17.40",
      low: true
    }]
  },
  nav: [{
    to: "dashboard",
    label: "Dashboard",
    icon: "layout-dashboard"
  }, {
    to: "products",
    label: "Products",
    icon: "package"
  }, {
    to: "inventory",
    label: "Inventory",
    icon: "boxes"
  }, {
    to: "customers",
    label: "Customers",
    icon: "user-square-2"
  }, {
    to: "suppliers",
    label: "Suppliers",
    icon: "truck"
  }, {
    to: "purchases",
    label: "Purchases",
    icon: "shopping-cart"
  }, {
    to: "sales",
    label: "Sales",
    icon: "receipt"
  }, {
    to: "crm",
    label: "CRM",
    icon: "contact"
  }, {
    to: "reports",
    label: "Reports",
    icon: "file-text"
  }, {
    to: "assistant",
    label: "AI Assistant",
    icon: "sparkles"
  }, {
    to: "users",
    label: "Users",
    icon: "users"
  }]
};
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/erp-app/data.js", error: String((e && e.message) || e) }); }

// ui_kits/erp-app/modules.jsx
try { (() => {
// Additional ERP modules: Customers/Suppliers (Partners), Purchases/Sales (Documents), Reports, Users.
const {
  Icon,
  Btn,
  Badge,
  PageHead
} = window.ERP_UI;
const MD = window.ERP_DATA;
const DOC_STATUS = {
  draft: "neutral",
  pending_approval: "amber",
  confirmed: "sky",
  received: "emerald",
  cancelled: "rose"
};
const ROLE_TONE = {
  admin: "violet",
  manager: "sky",
  employee: "neutral"
};
function Toolbar({
  children
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 12,
      marginBottom: 18,
      flexWrap: "wrap",
      alignItems: "center"
    }
  }, children);
}
function SearchField({
  value,
  onChange,
  placeholder
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      maxWidth: 320,
      width: "100%"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      left: 12,
      top: "50%",
      transform: "translateY(-50%)",
      color: "var(--text-faint)",
      display: "flex"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "search",
    size: 16
  })), /*#__PURE__*/React.createElement("input", {
    className: "erp-field",
    style: {
      paddingLeft: 38
    },
    placeholder: placeholder,
    value: value,
    onChange: e => onChange(e.target.value)
  }));
}
function Sheet({
  children
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      width: "100%",
      overflow: "hidden",
      borderRadius: "var(--radius-lg)",
      border: "1px solid var(--border-subtle)",
      background: "var(--surface-card)",
      boxShadow: "var(--shadow-sm), var(--hairline)"
    }
  }, /*#__PURE__*/React.createElement("table", {
    className: "erp-table"
  }, children));
}
function Money({
  children,
  strong
}) {
  return /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: "var(--font-mono)",
      fontVariantNumeric: "tabular-nums",
      color: strong ? "var(--text-strong)" : "var(--text-body)"
    }
  }, children);
}

// ---------- Customers / Suppliers ----------
function Partners({
  kind
}) {
  const isCust = kind === "customers";
  const list = isCust ? MD.customers : MD.suppliers;
  const [q, setQ] = React.useState("");
  const rows = list.filter(p => (p.name + p.email + p.phone).toLowerCase().includes(q.toLowerCase()));
  const single = isCust ? "customer" : "supplier";
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHead, {
    title: isCust ? "Customers" : "Suppliers",
    sub: `${list.length} ${single}s · ${list.filter(p => p.active).length} active`
  }, /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "plus"
  }, "New ", single)), /*#__PURE__*/React.createElement(Toolbar, null, /*#__PURE__*/React.createElement(SearchField, {
    value: q,
    onChange: setQ,
    placeholder: "Search by name, email or phone\u2026"
  })), /*#__PURE__*/React.createElement(Sheet, null, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Name"), /*#__PURE__*/React.createElement("th", null, "Email"), /*#__PURE__*/React.createElement("th", null, "Phone"), /*#__PURE__*/React.createElement("th", null, "City"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, isCust ? "Orders" : "POs"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, rows.map(p => /*#__PURE__*/React.createElement("tr", {
    key: p.id,
    style: {
      opacity: p.active ? 1 : 0.5
    }
  }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 30,
      height: 30,
      borderRadius: 999,
      background: "var(--surface-hover)",
      display: "grid",
      placeItems: "center",
      font: "600 12px/1 var(--font-sans)",
      color: "var(--text-muted)",
      flex: "none"
    }
  }, p.name.split(" ").map(w => w[0]).slice(0, 2).join("")), /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--text-strong)",
      fontWeight: 500
    }
  }, p.name))), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-muted)"
    }
  }, p.email || "—"), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-muted)",
      fontFamily: "var(--font-mono)",
      fontSize: 13,
      whiteSpace: "nowrap"
    }
  }, p.phone), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-muted)"
    }
  }, p.city), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right",
      fontFamily: "var(--font-mono)"
    }
  }, p.orders), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(Badge, {
    tone: p.active ? "emerald" : "neutral",
    dot: true
  }, p.active ? "active" : "inactive")), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      gap: 2
    }
  }, /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--sm",
    "aria-label": "edit"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "pencil",
    size: 15
  })), /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--sm",
    "aria-label": "deactivate"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "trash-2",
    size: 15
  })))))))));
}

// ---------- Purchases / Sales ----------
function Documents({
  kind
}) {
  const isPur = kind === "purchases";
  const list = isPur ? MD.purchases : MD.sales;
  const [open, setOpen] = React.useState(null);
  const [status, setStatus] = React.useState("all");
  const rows = list.filter(d => status === "all" || d.status === status);
  const partnerLabel = isPur ? "Supplier" : "Customer";
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHead, {
    title: isPur ? "Purchases" : "Sales",
    sub: `${list.length} ${isPur ? "purchase orders" : "sales orders"}`
  }, isPur && /*#__PURE__*/React.createElement(Btn, {
    variant: "outline",
    size: "md",
    icon: "scan-line"
  }, "Import from invoice"), /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "plus"
  }, "New ", isPur ? "purchase order" : "sale")), /*#__PURE__*/React.createElement(Toolbar, null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 3,
      padding: 3,
      background: "var(--surface-inset)",
      border: "1px solid var(--border)",
      borderRadius: 999
    }
  }, ["all", "draft", "pending_approval", "confirmed", "received", "cancelled"].map(s => /*#__PURE__*/React.createElement("button", {
    key: s,
    onClick: () => setStatus(s),
    style: {
      height: 28,
      padding: "0 12px",
      borderRadius: 999,
      border: "none",
      cursor: "pointer",
      whiteSpace: "nowrap",
      font: "600 12px/1 var(--font-sans)",
      background: status === s ? "var(--surface-hover)" : "transparent",
      color: status === s ? "var(--text-strong)" : "var(--text-muted)",
      transition: "all var(--dur-1)"
    }
  }, s === "pending_approval" ? "pending" : s)))), /*#__PURE__*/React.createElement(Sheet, null, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Number"), /*#__PURE__*/React.createElement("th", null, partnerLabel), /*#__PURE__*/React.createElement("th", null, "Date"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Total"), /*#__PURE__*/React.createElement("th", null, "By"))), /*#__PURE__*/React.createElement("tbody", null, rows.map(d => /*#__PURE__*/React.createElement("tr", {
    key: d.number,
    onClick: () => setOpen(d),
    style: {
      cursor: "pointer"
    }
  }, /*#__PURE__*/React.createElement("td", {
    style: {
      fontFamily: "var(--font-mono)",
      fontSize: 13,
      color: "var(--emerald-400)"
    }
  }, d.number), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-strong)"
    }
  }, d.partner), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-muted)",
      fontFamily: "var(--font-mono)",
      fontSize: 13
    }
  }, d.date), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(Badge, {
    tone: DOC_STATUS[d.status],
    dot: true
  }, d.status === "pending_approval" ? "pending" : d.status)), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right"
    }
  }, /*#__PURE__*/React.createElement(Money, {
    strong: true
  }, d.total)), /*#__PURE__*/React.createElement("td", {
    style: {
      fontSize: 12,
      color: "var(--text-faint)",
      fontFamily: "var(--font-mono)"
    }
  }, d.by))))), open && /*#__PURE__*/React.createElement(DocDetail, {
    doc: open,
    isPur: isPur,
    onClose: () => setOpen(null)
  }));
}
function DocDetail({
  doc,
  isPur,
  onClose
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: "erp-dialog-backdrop",
    onClick: onClose
  }, /*#__PURE__*/React.createElement("div", {
    className: "erp-dialog",
    style: {
      maxWidth: 620
    },
    onClick: e => e.stopPropagation()
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "flex-start",
      justifyContent: "space-between",
      padding: "22px 24px 16px",
      borderBottom: "1px solid var(--border-subtle)"
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: 0,
      font: "600 20px/1 var(--font-mono)",
      color: "var(--text-strong)"
    }
  }, doc.number), /*#__PURE__*/React.createElement(Badge, {
    tone: DOC_STATUS[doc.status],
    dot: true
  }, doc.status === "pending_approval" ? "pending" : doc.status)), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: "8px 0 0",
      font: "400 13px/1 var(--font-sans)",
      color: "var(--text-muted)"
    }
  }, doc.partner, " \xB7 ", doc.date, " \xB7 by ", doc.by)), /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--sm",
    onClick: onClose,
    "aria-label": "close"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "x",
    size: 16
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "16px 24px 24px"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      borderRadius: "var(--radius-md)",
      border: "1px solid var(--border-subtle)",
      overflow: "hidden",
      marginBottom: 16
    }
  }, /*#__PURE__*/React.createElement("table", {
    className: "erp-table"
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Product"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Qty"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Unit price"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Subtotal"))), /*#__PURE__*/React.createElement("tbody", null, doc.lines.map((l, i) => /*#__PURE__*/React.createElement("tr", {
    key: i
  }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: "var(--font-mono)",
      fontSize: 12,
      color: "var(--text-faint)"
    }
  }, l[0]), " ", /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--text-strong)"
    }
  }, l[1])), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right",
      fontFamily: "var(--font-mono)"
    }
  }, l[2]), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right",
      fontFamily: "var(--font-mono)"
    }
  }, l[3]), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right",
      fontFamily: "var(--font-mono)",
      color: "var(--text-strong)"
    }
  }, (l[2] * parseFloat(l[3].replace(",", ""))).toFixed(2))))))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      font: "400 13px/1 var(--font-sans)",
      color: "var(--text-muted)"
    }
  }, "Total"), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "600 22px/1 var(--font-sans)",
      letterSpacing: "-.02em",
      color: "var(--text-strong)",
      fontVariantNumeric: "tabular-nums"
    }
  }, doc.total, " ", /*#__PURE__*/React.createElement("span", {
    style: {
      font: "500 13px/1 var(--font-sans)",
      color: "var(--text-muted)"
    }
  }, "TND"))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      justifyContent: "flex-end",
      gap: 8,
      marginTop: 20
    }
  }, !isPur && doc.status === "confirmed" && /*#__PURE__*/React.createElement(Btn, {
    variant: "outline",
    size: "md",
    icon: "file-text"
  }, "Invoice PDF"), doc.status === "draft" && /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "check"
  }, "Confirm"), isPur && doc.status === "pending_approval" && /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "shield-check"
  }, "Approve order"), isPur && doc.status === "confirmed" && /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "package-check"
  }, "Receive goods"), (doc.status === "draft" || doc.status === "confirmed") && /*#__PURE__*/React.createElement(Btn, {
    variant: "danger",
    size: "md"
  }, "Cancel")))));
}

// ---------- Reports ----------
function Reports() {
  const [kind, setKind] = React.useState("sales");
  const dated = kind !== "stock";
  const rows = MD.reportRows[kind];
  const total = kind === "stock" ? "5,922.00" : kind === "sales" ? "4,448.00" : "16,040.00";
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHead, {
    title: "Reports",
    sub: "Export sales, purchases and stock valuation"
  }, /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "download"
  }, "Export PDF")), /*#__PURE__*/React.createElement(Toolbar, null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 3,
      padding: 3,
      background: "var(--surface-inset)",
      border: "1px solid var(--border)",
      borderRadius: 12
    }
  }, [["sales", "Sales", "trending-up"], ["purchases", "Purchases", "shopping-cart"], ["stock", "Stock", "boxes"]].map(([k, l, ic]) => /*#__PURE__*/React.createElement("button", {
    key: k,
    onClick: () => setKind(k),
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 7,
      height: 34,
      padding: "0 16px",
      borderRadius: 9,
      border: "none",
      cursor: "pointer",
      font: "600 13px/1 var(--font-sans)",
      background: kind === k ? "var(--surface-hover)" : "transparent",
      color: kind === k ? "var(--text-strong)" : "var(--text-muted)",
      boxShadow: kind === k ? "var(--shadow-xs), var(--hairline)" : "none",
      transition: "all var(--dur-1)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: ic,
    size: 15,
    color: kind === k ? "var(--emerald-400)" : undefined
  }), " ", l))), dated && /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 8,
      marginLeft: "auto"
    }
  }, /*#__PURE__*/React.createElement("input", {
    className: "erp-field",
    type: "date",
    defaultValue: "2026-07-01",
    style: {
      width: 160
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--text-faint)"
    }
  }, "\u2192"), /*#__PURE__*/React.createElement("input", {
    className: "erp-field",
    type: "date",
    defaultValue: "2026-07-12",
    style: {
      width: 160
    }
  }))), /*#__PURE__*/React.createElement(Sheet, null, kind === "stock" ? /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "SKU"), /*#__PURE__*/React.createElement("th", null, "Product"), /*#__PURE__*/React.createElement("th", null, "Category"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Qty"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Min"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Stock value"))), /*#__PURE__*/React.createElement("tbody", null, rows.map(r => /*#__PURE__*/React.createElement("tr", {
    key: r.sku
  }, /*#__PURE__*/React.createElement("td", {
    style: {
      fontFamily: "var(--font-mono)",
      fontSize: 13,
      color: "var(--text-faint)"
    }
  }, r.sku), /*#__PURE__*/React.createElement("td", {
    style: {
      color: r.low ? "var(--rose-400)" : "var(--text-strong)"
    }
  }, r.name), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-muted)"
    }
  }, r.cat), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right",
      fontFamily: "var(--font-mono)",
      color: r.low ? "var(--rose-400)" : undefined
    }
  }, r.qty), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right",
      fontFamily: "var(--font-mono)",
      color: "var(--text-muted)"
    }
  }, r.min), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right"
    }
  }, /*#__PURE__*/React.createElement(Money, {
    strong: true
  }, r.value)))))) : /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "Number"), /*#__PURE__*/React.createElement("th", null, "Date"), /*#__PURE__*/React.createElement("th", null, "Partner"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Total"))), /*#__PURE__*/React.createElement("tbody", null, rows.map(r => /*#__PURE__*/React.createElement("tr", {
    key: r.number,
    style: {
      opacity: r.status === "cancelled" ? 0.5 : 1
    }
  }, /*#__PURE__*/React.createElement("td", {
    style: {
      fontFamily: "var(--font-mono)",
      fontSize: 13,
      color: "var(--emerald-400)"
    }
  }, r.number), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-muted)",
      fontFamily: "var(--font-mono)",
      fontSize: 13
    }
  }, r.date), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-strong)"
    }
  }, r.partner), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(Badge, {
    tone: DOC_STATUS[r.status]
  }, r.status === "pending_approval" ? "pending" : r.status)), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right"
    }
  }, /*#__PURE__*/React.createElement(Money, {
    strong: true
  }, r.total))))))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      justifyContent: "flex-end",
      gap: 8,
      marginTop: 14,
      font: "400 13px/1 var(--font-sans)",
      color: "var(--text-muted)"
    }
  }, rows.length, " ", kind === "stock" ? "products" : "documents", " \xB7 ", kind === "stock" ? "Total stock value" : "Total", ": ", /*#__PURE__*/React.createElement("b", {
    style: {
      color: "var(--text-strong)",
      fontFamily: "var(--font-mono)"
    }
  }, total, " TND")));
}

// ---------- Users ----------
function Users() {
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHead, {
    title: "Users",
    sub: `${MD.users.length} team members`
  }, /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "user-plus"
  }, "Invite user")), /*#__PURE__*/React.createElement(Sheet, null, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "User"), /*#__PURE__*/React.createElement("th", null, "Email"), /*#__PURE__*/React.createElement("th", null, "Role"), /*#__PURE__*/React.createElement("th", null, "Status"))), /*#__PURE__*/React.createElement("tbody", null, MD.users.map(u => /*#__PURE__*/React.createElement("tr", {
    key: u.email,
    style: {
      opacity: u.active ? 1 : 0.5
    }
  }, /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 32,
      height: 32,
      borderRadius: 999,
      background: "linear-gradient(135deg,var(--emerald-500),var(--emerald-700))",
      display: "grid",
      placeItems: "center",
      font: "600 12px/1 var(--font-sans)",
      color: "var(--text-on-accent)",
      flex: "none"
    }
  }, u.first[0], u.last[0]), /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--text-strong)",
      fontWeight: 500
    }
  }, u.first, " ", u.last))), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-muted)",
      fontFamily: "var(--font-mono)",
      fontSize: 13
    }
  }, u.email), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(Badge, {
    tone: ROLE_TONE[u.role]
  }, u.role)), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(Badge, {
    tone: u.active ? "emerald" : "neutral",
    dot: true
  }, u.active ? "active" : "inactive")))))));
}
window.ERP_MODULES = {
  Partners,
  Documents,
  Reports,
  Users
};
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/erp-app/modules.jsx", error: String((e && e.message) || e) }); }

// ui_kits/erp-app/pages.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
// ERP kit pages: Dashboard, Products, CRM, Assistant.
const {
  Icon,
  Btn,
  Badge,
  Spark,
  Kpi,
  PageHead
} = window.ERP_UI;
const D = window.ERP_DATA;
function SectionCard({
  title,
  action,
  children
}) {
  return /*#__PURE__*/React.createElement("div", {
    className: "erp-card",
    style: {
      display: "flex",
      flexDirection: "column"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      padding: "18px 22px",
      borderBottom: "1px solid var(--border-subtle)"
    }
  }, /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 0,
      font: "600 16px/1 var(--font-sans)",
      letterSpacing: "-.01em",
      color: "var(--text-strong)"
    }
  }, title), action), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: "8px 22px 14px"
    }
  }, children));
}
function Dashboard() {
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHead, {
    title: "Good afternoon, Amine",
    sub: "Here's what's moving across your business today."
  }, /*#__PURE__*/React.createElement(Btn, {
    variant: "outline",
    size: "md",
    icon: "calendar"
  }, "This month"), /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "sparkles"
  }, "Ask AI")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))",
      gap: 16,
      marginBottom: 20
    }
  }, D.kpis.map(k => /*#__PURE__*/React.createElement(Kpi, _extends({
    key: k.label
  }, k)))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(auto-fit, minmax(300px, 1fr))",
      gap: 16,
      marginBottom: 20
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "erp-card",
    style: {
      padding: 22
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      justifyContent: "space-between",
      alignItems: "flex-start",
      marginBottom: 18
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("span", {
    className: "ds-eyebrow"
  }, "Revenue trend"), /*#__PURE__*/React.createElement("div", {
    style: {
      font: "600 26px/1 var(--font-sans)",
      letterSpacing: "-.03em",
      color: "var(--text-strong)",
      marginTop: 8,
      fontVariantNumeric: "tabular-nums"
    }
  }, "48,250 ", /*#__PURE__*/React.createElement("span", {
    style: {
      font: "500 14px/1 var(--font-sans)",
      color: "var(--text-muted)"
    }
  }, "TND"))), /*#__PURE__*/React.createElement(Badge, {
    tone: "emerald",
    dot: true
  }, "+12.4%")), /*#__PURE__*/React.createElement(Spark, {
    data: D.revenueSeries,
    up: true,
    fill: true,
    w: 640,
    h: 150
  })), /*#__PURE__*/React.createElement("div", {
    className: "erp-card",
    style: {
      padding: 0,
      background: "linear-gradient(160deg, color-mix(in oklab, var(--emerald-500) 12%, var(--surface-card)), var(--surface-card))",
      position: "relative",
      overflow: "hidden"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      padding: 22,
      display: "flex",
      flexDirection: "column",
      gap: 14,
      height: "100%"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 34,
      height: 34,
      borderRadius: 10,
      background: "var(--emerald-glow)",
      display: "grid",
      placeItems: "center",
      color: "var(--emerald-400)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "sparkles",
    size: 18
  })), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "600 15px/1 var(--font-sans)",
      color: "var(--text-strong)"
    }
  }, "AI insight")), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      font: "400 14px/1.55 var(--font-sans)",
      color: "var(--text-body)"
    }
  }, "Copper coil demand is up ", /*#__PURE__*/React.createElement("b", {
    style: {
      color: "var(--emerald-400)"
    }
  }, "34%"), " this week while stock covers only ~9 days. Consider a purchase order to avoid a stockout before month-end."), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: "auto",
      display: "flex",
      gap: 8
    }
  }, /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "sm",
    icon: "check"
  }, "Draft PO"), /*#__PURE__*/React.createElement(Btn, {
    variant: "ghost",
    size: "sm"
  }, "Dismiss"))))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "1fr 1fr",
      gap: 16
    }
  }, /*#__PURE__*/React.createElement(SectionCard, {
    title: "Top products",
    action: /*#__PURE__*/React.createElement(Btn, {
      variant: "ghost",
      size: "sm",
      iconRight: "arrow-right"
    }, "View all")
  }, D.topProducts.map(p => /*#__PURE__*/React.createElement("div", {
    key: p.sku,
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      padding: "12px 0",
      borderBottom: "1px solid var(--border-subtle)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: 3
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      font: "500 14px/1 var(--font-sans)",
      color: "var(--text-strong)"
    }
  }, p.name), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "400 12px/1 var(--font-mono)",
      color: "var(--text-faint)"
    }
  }, p.sku, " \xB7 ", p.sold, " sold")), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "500 14px/1 var(--font-mono)",
      color: "var(--emerald-400)"
    }
  }, p.revenue)))), /*#__PURE__*/React.createElement(SectionCard, {
    title: "Low stock",
    action: /*#__PURE__*/React.createElement(Badge, {
      tone: "rose"
    }, D.lowStock.length, " items")
  }, D.lowStock.map(p => {
    const pct = Math.min(100, p.qty / p.min * 100);
    return /*#__PURE__*/React.createElement("div", {
      key: p.sku,
      style: {
        padding: "12px 0",
        borderBottom: "1px solid var(--border-subtle)"
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: "flex",
        justifyContent: "space-between",
        marginBottom: 8
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        font: "500 14px/1 var(--font-sans)",
        color: "var(--text-strong)"
      }
    }, p.name), /*#__PURE__*/React.createElement("span", {
      style: {
        font: "500 13px/1 var(--font-mono)",
        color: "var(--rose-400)"
      }
    }, p.qty, " / ", p.min)), /*#__PURE__*/React.createElement("div", {
      style: {
        height: 5,
        borderRadius: 999,
        background: "var(--surface-hover)",
        overflow: "hidden"
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        width: pct + "%",
        height: "100%",
        borderRadius: 999,
        background: "var(--rose-400)"
      }
    })));
  }))));
}
function Products() {
  const [q, setQ] = React.useState("");
  const rows = D.products.filter(p => (p.name + p.sku).toLowerCase().includes(q.toLowerCase()));
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHead, {
    title: "Products",
    sub: `${D.products.length} items in your catalog`
  }, /*#__PURE__*/React.createElement(Btn, {
    variant: "outline",
    size: "md",
    icon: "folder"
  }, "Categories"), /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "plus"
  }, "New product")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 12,
      marginBottom: 18
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      maxWidth: 320,
      width: "100%"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      position: "absolute",
      left: 12,
      top: "50%",
      transform: "translateY(-50%)",
      color: "var(--text-faint)",
      display: "flex"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "search",
    size: 16
  })), /*#__PURE__*/React.createElement("input", {
    className: "erp-field",
    style: {
      paddingLeft: 38
    },
    placeholder: "Search by SKU or name\u2026",
    value: q,
    onChange: e => setQ(e.target.value)
  })), /*#__PURE__*/React.createElement("button", {
    className: "erp-btn erp-btn--outline erp-btn--md"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "sliders-horizontal",
    size: 16
  }), "Filters")), /*#__PURE__*/React.createElement("div", {
    style: {
      width: "100%",
      overflow: "hidden",
      borderRadius: "var(--radius-lg)",
      border: "1px solid var(--border-subtle)",
      background: "var(--surface-card)",
      boxShadow: "var(--shadow-sm), var(--hairline)"
    }
  }, /*#__PURE__*/React.createElement("table", {
    className: "erp-table"
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "SKU"), /*#__PURE__*/React.createElement("th", null, "Name"), /*#__PURE__*/React.createElement("th", null, "Category"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Stock"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Sale price"), /*#__PURE__*/React.createElement("th", null, "Status"), /*#__PURE__*/React.createElement("th", null))), /*#__PURE__*/React.createElement("tbody", null, rows.map(p => /*#__PURE__*/React.createElement("tr", {
    key: p.sku,
    style: {
      opacity: p.active ? 1 : 0.5
    }
  }, /*#__PURE__*/React.createElement("td", {
    style: {
      fontFamily: "var(--font-mono)",
      fontSize: 13,
      color: "var(--text-muted)"
    }
  }, p.sku), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-strong)",
      fontWeight: 500
    }
  }, p.name), /*#__PURE__*/React.createElement("td", null, p.cat), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right",
      fontFamily: "var(--font-mono)"
    }
  }, p.stock, " ", p.unit, " ", p.low && /*#__PURE__*/React.createElement("span", {
    style: {
      marginLeft: 6
    }
  }, /*#__PURE__*/React.createElement(Badge, {
    tone: "rose"
  }, "low"))), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right",
      fontFamily: "var(--font-mono)"
    }
  }, p.price), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement(Badge, {
    tone: p.active ? "emerald" : "neutral",
    dot: true
  }, p.active ? "active" : "inactive")), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      gap: 2
    }
  }, /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--sm",
    "aria-label": "edit"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "pencil",
    size: 15
  })), /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--sm",
    "aria-label": "archive"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "trash-2",
    size: 15
  }))))))))));
}
const CRM_COLS = [{
  k: "new",
  label: "New",
  tone: "neutral"
}, {
  k: "contacted",
  label: "Contacted",
  tone: "amber"
}, {
  k: "qualified",
  label: "Qualified",
  tone: "sky"
}, {
  k: "won",
  label: "Won",
  tone: "emerald"
}, {
  k: "lost",
  label: "Lost",
  tone: "rose"
}];
function Crm() {
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHead, {
    title: "CRM",
    sub: "Prospect pipeline \u2014 7 active leads"
  }, /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "plus"
  }, "New lead")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(5,1fr)",
      gap: 14,
      alignItems: "start"
    }
  }, CRM_COLS.map(c => {
    const items = D.leads[c.k] || [];
    return /*#__PURE__*/React.createElement("div", {
      key: c.k,
      style: {
        display: "flex",
        flexDirection: "column",
        gap: 12
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: "flex",
        alignItems: "center",
        justifyContent: "space-between",
        padding: "0 2px"
      }
    }, /*#__PURE__*/React.createElement(Badge, {
      tone: c.tone,
      dot: true
    }, c.label), /*#__PURE__*/React.createElement("span", {
      style: {
        font: "500 12px/1 var(--font-mono)",
        color: "var(--text-faint)"
      }
    }, items.length)), items.map(l => /*#__PURE__*/React.createElement("div", {
      key: l.id,
      className: "erp-card erp-card--hover",
      style: {
        padding: 14,
        cursor: "pointer",
        display: "flex",
        flexDirection: "column",
        gap: 8
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        font: "500 14px/1.2 var(--font-sans)",
        color: "var(--text-strong)"
      }
    }, l.name), l.company !== "—" && /*#__PURE__*/React.createElement("span", {
      style: {
        font: "400 12px/1 var(--font-sans)",
        color: "var(--text-muted)"
      }
    }, l.company), /*#__PURE__*/React.createElement("span", {
      style: {
        display: "inline-flex",
        alignItems: "center",
        gap: 6,
        font: "400 12px/1 var(--font-mono)",
        color: "var(--text-faint)"
      }
    }, /*#__PURE__*/React.createElement(Icon, {
      name: "phone",
      size: 12
    }), " ", l.phone))));
  })));
}
const PROMPTS = ["Which products are low on stock?", "Create a customer named Ahmed Ben Ali", "What's this month's revenue?", "Top 5 products by margin"];
const CONV = [{
  role: "user",
  content: "Which products are low on stock right now?"
}, {
  role: "assistant",
  tools: ["list_products"],
  content: "Three products are below their minimum level:\n\n• Brass fitting 1/2\" — 3 / 30\n• Steel hinge 60mm — 8 / 25\n• PVC elbow joint — 14 / 40\n\nThe brass fitting is critical. Want me to draft a purchase order to the default supplier?"
}, {
  role: "user",
  content: "Yes, draft it for 50 units."
}, {
  role: "assistant",
  tools: ["create_purchase_order"],
  pending: true,
  content: "I've prepared a purchase order — please review and approve."
}];
// Reusable conversation. `compact` tightens spacing for the dock; `promptCols` wraps chips.
function Conversation({
  compact
}) {
  const [msgs, setMsgs] = React.useState(CONV);
  const [input, setInput] = React.useState("");
  const scroller = React.useRef(null);
  const send = t => {
    if (!t.trim()) return;
    setMsgs(m => [...m, {
      role: "user",
      content: t
    }]);
    setInput("");
  };
  React.useEffect(() => {
    if (scroller.current) scroller.current.scrollTop = scroller.current.scrollHeight;
  }, [msgs]);
  return /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
    ref: scroller,
    style: {
      flex: 1,
      overflowY: "auto",
      overflowX: "hidden",
      padding: compact ? "14px 4px" : "20px 4px",
      display: "flex",
      flexDirection: "column",
      gap: compact ? 14 : 18
    }
  }, msgs.map((m, i) => /*#__PURE__*/React.createElement(Bubble, {
    key: i,
    m: m,
    compact: compact
  }))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 8,
      flexWrap: "wrap",
      margin: compact ? "0 0 10px" : "0 0 12px"
    }
  }, (compact ? PROMPTS.slice(0, 2) : PROMPTS).map(p => /*#__PURE__*/React.createElement("button", {
    key: p,
    onClick: () => send(p),
    style: {
      background: "var(--surface-card)",
      border: "1px solid var(--border)",
      color: "var(--text-body)",
      borderRadius: 999,
      padding: "7px 14px",
      font: "500 12px/1 var(--font-sans)",
      cursor: "pointer",
      whiteSpace: "nowrap",
      overflow: "hidden",
      textOverflow: "ellipsis",
      maxWidth: compact ? 190 : "none"
    }
  }, p))), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      position: "relative",
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("input", {
    className: "erp-field",
    style: {
      height: 48,
      paddingRight: 46
    },
    placeholder: "Ask about your data, or ask it to act\u2026",
    value: input,
    onChange: e => setInput(e.target.value),
    onKeyDown: e => e.key === "Enter" && send(input)
  }), /*#__PURE__*/React.createElement("button", {
    className: "erp-iconbtn erp-iconbtn--sm",
    style: {
      position: "absolute",
      right: 8,
      top: 8,
      color: "var(--text-muted)"
    },
    "aria-label": "mic"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "mic",
    size: 16
  }))), /*#__PURE__*/React.createElement("button", {
    className: "erp-btn erp-btn--primary erp-btn--lg",
    onClick: () => send(input),
    "aria-label": "send"
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "arrow-up",
    size: 18
  }))));
}
function Assistant() {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      flexDirection: "column",
      height: "calc(100vh - var(--topbar-h) - 80px)"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 12,
      marginBottom: 8
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 40,
      height: 40,
      borderRadius: 12,
      background: "var(--emerald-glow)",
      display: "grid",
      placeItems: "center",
      color: "var(--emerald-400)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "sparkles",
    size: 20
  })), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      font: "600 22px/1 var(--font-sans)",
      letterSpacing: "-.02em",
      color: "var(--text-strong)"
    }
  }, "AI Assistant"), /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 6,
      font: "400 12px/1 var(--font-sans)",
      color: "var(--text-muted)",
      marginTop: 5
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      width: 6,
      height: 6,
      borderRadius: 999,
      background: "var(--emerald-400)"
    }
  }), " Local model \xB7 every action needs your approval"))), /*#__PURE__*/React.createElement(Conversation, null));
}
function Bubble({
  m,
  compact
}) {
  const user = m.role === "user";
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 12,
      justifyContent: user ? "flex-end" : "flex-start"
    }
  }, !user && /*#__PURE__*/React.createElement("div", {
    style: {
      width: 32,
      height: 32,
      borderRadius: 10,
      background: "var(--emerald-glow)",
      color: "var(--emerald-400)",
      display: "grid",
      placeItems: "center",
      flex: "none"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "sparkles",
    size: 16
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: compact ? 300 : 560,
      display: "flex",
      flexDirection: "column",
      gap: 8,
      alignItems: user ? "flex-end" : "flex-start"
    }
  }, m.tools && /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 6
    }
  }, m.tools.map(t => /*#__PURE__*/React.createElement("span", {
    key: t,
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 5,
      background: "var(--surface-hover)",
      color: "var(--text-muted)",
      font: "500 11px/1 var(--font-mono)",
      padding: "5px 9px",
      borderRadius: 999
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "wrench",
    size: 11,
    color: "var(--emerald-400)"
  }), " ", t))), /*#__PURE__*/React.createElement("div", {
    style: {
      whiteSpace: "pre-wrap",
      font: "400 14px/1.55 var(--font-sans)",
      padding: "12px 16px",
      borderRadius: 16,
      background: user ? "var(--emerald-500)" : "var(--surface-card)",
      color: user ? "var(--text-on-accent)" : "var(--text-body)",
      border: user ? "none" : "1px solid var(--border-subtle)",
      borderTopRightRadius: user ? 4 : 16,
      borderTopLeftRadius: user ? 16 : 4
    }
  }, m.content), m.pending && /*#__PURE__*/React.createElement("div", {
    className: "erp-card",
    style: {
      padding: 16,
      borderColor: "var(--amber-glow)",
      maxWidth: 420
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 8,
      marginBottom: 10
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "shield-alert",
    size: 16,
    color: "var(--amber-400)"
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      font: "600 13px/1 var(--font-sans)",
      color: "var(--amber-400)"
    }
  }, "Confirmation required \u2014 create_purchase_order")), /*#__PURE__*/React.createElement("pre", {
    style: {
      margin: "0 0 12px",
      background: "var(--surface-inset)",
      borderRadius: 10,
      padding: 12,
      font: "400 12px/1.5 var(--font-mono)",
      color: "var(--text-body)",
      overflow: "auto"
    }
  }, `{
  "supplier": "Default supplier",
  "product": "ERP-0210",
  "quantity": 50
}`), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 8
    }
  }, /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "sm",
    icon: "check"
  }, "Approve"), /*#__PURE__*/React.createElement(Btn, {
    variant: "danger",
    size: "sm",
    icon: "x"
  }, "Reject")))));
}
const MOVE_TONE = {
  in: "emerald",
  out: "rose",
  adjustment: "amber",
  transfer: "sky"
};
const MOVE_ICON = {
  in: "arrow-down-to-line",
  out: "arrow-up-from-line",
  adjustment: "scale",
  transfer: "arrow-left-right"
};
function Segmented({
  value,
  onChange,
  options
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 3,
      padding: 3,
      background: "var(--surface-inset)",
      border: "1px solid var(--border)",
      borderRadius: 12
    }
  }, options.map(o => {
    const active = value === o.v;
    return /*#__PURE__*/React.createElement("button", {
      key: o.v,
      type: "button",
      onClick: () => onChange(o.v),
      style: {
        flex: 1,
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "center",
        gap: 6,
        height: 32,
        borderRadius: 9,
        border: "none",
        cursor: "pointer",
        font: "600 12px/1 var(--font-sans)",
        background: active ? "var(--surface-hover)" : "transparent",
        color: active ? o.color || "var(--text-strong)" : "var(--text-muted)",
        boxShadow: active ? "var(--shadow-xs), var(--hairline)" : "none",
        transition: "all var(--dur-1) var(--ease-out)"
      }
    }, /*#__PURE__*/React.createElement(Icon, {
      name: o.icon,
      size: 14
    }), " ", o.label);
  }));
}
function FormRow({
  label,
  children
}) {
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: "flex",
      flexDirection: "column",
      gap: 8
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      font: "500 13px/1 var(--font-sans)",
      color: "var(--text-body)"
    }
  }, label), children);
}
function Inventory() {
  const [type, setType] = React.useState("in");
  const [filter, setFilter] = React.useState("all");
  const rows = D.movements.filter(m => filter === "all" || m.type === filter);
  const inQty = D.movements.filter(m => m.type === "in").reduce((s, m) => s + m.qty, 0);
  const outQty = D.movements.filter(m => m.type === "out").reduce((s, m) => s + Math.abs(m.qty), 0);
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHead, {
    title: "Inventory",
    sub: "Stock movements across 3 warehouses"
  }, /*#__PURE__*/React.createElement(Btn, {
    variant: "outline",
    size: "md",
    icon: "download"
  }, "Export"), /*#__PURE__*/React.createElement(Btn, {
    variant: "primary",
    size: "md",
    icon: "arrow-left-right"
  }, "New transfer")), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
      gap: 16,
      marginBottom: 20
    }
  }, /*#__PURE__*/React.createElement(Kpi, {
    label: "Stock in (30d)",
    value: inQty.toLocaleString(),
    unit: "units",
    delta: 8.2,
    spark: [10, 12, 11, 14, 13, 16, 18, 20]
  }), /*#__PURE__*/React.createElement(Kpi, {
    label: "Stock out (30d)",
    value: outQty.toLocaleString(),
    unit: "units",
    delta: -4.5,
    tone: "neutral",
    spark: [18, 16, 17, 15, 14, 13, 12, 11]
  }), /*#__PURE__*/React.createElement(Kpi, {
    label: "Warehouses",
    value: "3",
    tone: "neutral",
    delta: 0,
    spark: [3, 3, 3, 3, 3, 3, 3, 3]
  }), /*#__PURE__*/React.createElement(Kpi, {
    label: "Low stock",
    value: D.lowStock.length,
    unit: "items",
    delta: -2,
    tone: "neutral",
    spark: [6, 5, 5, 4, 4, 3, 3, 3]
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "minmax(320px, 380px) 1fr",
      gap: 16,
      alignItems: "start"
    }
  }, /*#__PURE__*/React.createElement("div", {
    className: "erp-card",
    style: {
      padding: 22,
      display: "flex",
      flexDirection: "column",
      gap: 16
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 34,
      height: 34,
      borderRadius: 10,
      background: "var(--emerald-glow)",
      display: "grid",
      placeItems: "center",
      color: "var(--emerald-400)"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "plus-circle",
    size: 18
  })), /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 0,
      font: "600 16px/1 var(--font-sans)",
      color: "var(--text-strong)"
    }
  }, "Record movement")), /*#__PURE__*/React.createElement(FormRow, {
    label: "Product"
  }, /*#__PURE__*/React.createElement("select", {
    className: "erp-field"
  }, /*#__PURE__*/React.createElement("option", null, "ERP-0088 \u2014 Copper coil 2.5mm"), /*#__PURE__*/React.createElement("option", null, "ERP-0042 \u2014 Aluminium bracket 40mm"), /*#__PURE__*/React.createElement("option", null, "ERP-0119 \u2014 Stainless bolt M8"))), /*#__PURE__*/React.createElement(FormRow, {
    label: "Type"
  }, /*#__PURE__*/React.createElement(Segmented, {
    value: type,
    onChange: setType,
    options: [{
      v: "in",
      label: "In",
      icon: "arrow-down-to-line",
      color: "var(--emerald-400)"
    }, {
      v: "out",
      label: "Out",
      icon: "arrow-up-from-line",
      color: "var(--rose-400)"
    }, {
      v: "adjustment",
      label: "Adjust",
      icon: "scale",
      color: "var(--amber-400)"
    }]
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "grid",
      gridTemplateColumns: "1fr 1fr",
      gap: 12
    }
  }, /*#__PURE__*/React.createElement(FormRow, {
    label: "Quantity"
  }, /*#__PURE__*/React.createElement("input", {
    className: "erp-field",
    type: "number",
    placeholder: "0",
    defaultValue: 120
  })), /*#__PURE__*/React.createElement(FormRow, {
    label: "Warehouse"
  }, /*#__PURE__*/React.createElement("select", {
    className: "erp-field"
  }, D.warehouses.map(w => /*#__PURE__*/React.createElement("option", {
    key: w.id
  }, w.name, w.def ? " (default)" : ""))))), /*#__PURE__*/React.createElement(FormRow, {
    label: "Reason"
  }, /*#__PURE__*/React.createElement("input", {
    className: "erp-field",
    placeholder: "e.g. PO receipt, damage, recount\u2026"
  })), /*#__PURE__*/React.createElement("button", {
    className: "erp-btn erp-btn--primary erp-btn--md",
    style: {
      width: "100%"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "check",
    size: 16
  }), " Record movement")), /*#__PURE__*/React.createElement("div", {
    className: "erp-card",
    style: {
      display: "flex",
      flexDirection: "column",
      overflow: "hidden"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "center",
      justifyContent: "space-between",
      gap: 12,
      padding: "16px 20px",
      borderBottom: "1px solid var(--border-subtle)",
      flexWrap: "wrap"
    }
  }, /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 0,
      font: "600 16px/1 var(--font-sans)",
      color: "var(--text-strong)"
    }
  }, "Movement history"), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 3,
      padding: 3,
      background: "var(--surface-inset)",
      border: "1px solid var(--border)",
      borderRadius: 999
    }
  }, ["all", "in", "out", "adjustment", "transfer"].map(f => /*#__PURE__*/React.createElement("button", {
    key: f,
    onClick: () => setFilter(f),
    style: {
      height: 26,
      padding: "0 12px",
      borderRadius: 999,
      border: "none",
      cursor: "pointer",
      textTransform: "capitalize",
      font: "600 12px/1 var(--font-sans)",
      background: filter === f ? "var(--surface-hover)" : "transparent",
      color: filter === f ? "var(--text-strong)" : "var(--text-muted)",
      transition: "all var(--dur-1)"
    }
  }, f)))), /*#__PURE__*/React.createElement("div", {
    style: {
      overflowX: "auto"
    }
  }, /*#__PURE__*/React.createElement("table", {
    className: "erp-table"
  }, /*#__PURE__*/React.createElement("thead", null, /*#__PURE__*/React.createElement("tr", null, /*#__PURE__*/React.createElement("th", null, "When"), /*#__PURE__*/React.createElement("th", null, "Product"), /*#__PURE__*/React.createElement("th", null, "Type"), /*#__PURE__*/React.createElement("th", {
    style: {
      textAlign: "right"
    }
  }, "Qty"), /*#__PURE__*/React.createElement("th", null, "Warehouse"), /*#__PURE__*/React.createElement("th", null, "Reason"), /*#__PURE__*/React.createElement("th", null, "By"))), /*#__PURE__*/React.createElement("tbody", null, rows.map(m => /*#__PURE__*/React.createElement("tr", {
    key: m.id
  }, /*#__PURE__*/React.createElement("td", {
    style: {
      whiteSpace: "nowrap",
      color: "var(--text-faint)",
      fontSize: 12,
      fontFamily: "var(--font-mono)"
    }
  }, m.at), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
    style: {
      fontFamily: "var(--font-mono)",
      fontSize: 12,
      color: "var(--text-faint)"
    }
  }, m.sku), " ", /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--text-strong)"
    }
  }, m.name)), /*#__PURE__*/React.createElement("td", null, /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 6
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: MOVE_ICON[m.type],
    size: 13,
    color: `var(--${MOVE_TONE[m.type] === "emerald" ? "emerald" : MOVE_TONE[m.type] === "rose" ? "rose" : MOVE_TONE[m.type] === "amber" ? "amber" : "sky"}-400)`
  }), /*#__PURE__*/React.createElement(Badge, {
    tone: MOVE_TONE[m.type]
  }, m.type))), /*#__PURE__*/React.createElement("td", {
    style: {
      textAlign: "right",
      fontFamily: "var(--font-mono)",
      color: m.qty < 0 ? "var(--rose-400)" : "var(--text-strong)"
    }
  }, m.qty > 0 ? "+" : "", m.qty), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-muted)"
    }
  }, m.wh), /*#__PURE__*/React.createElement("td", {
    style: {
      color: "var(--text-muted)"
    }
  }, m.reason), /*#__PURE__*/React.createElement("td", {
    style: {
      fontSize: 12,
      color: "var(--text-faint)",
      fontFamily: "var(--font-mono)"
    }
  }, m.by)))))))));
}
function Placeholder({
  label
}) {
  return /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement(PageHead, {
    title: label,
    sub: "This module keeps its existing functionality \u2014 restyled with the ERP design system."
  }), /*#__PURE__*/React.createElement("div", {
    className: "erp-card",
    style: {
      padding: 64,
      display: "grid",
      placeItems: "center",
      textAlign: "center"
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      width: 56,
      height: 56,
      borderRadius: 16,
      background: "var(--surface-hover)",
      display: "grid",
      placeItems: "center",
      color: "var(--text-faint)",
      marginBottom: 16
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: "layers",
    size: 26
  })), /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      font: "400 14px/1.5 var(--font-sans)",
      color: "var(--text-muted)",
      maxWidth: 340
    }
  }, label, " view \u2014 same data and workflows, wrapped in the new premium interface.")));
}
window.ERP_PAGES = {
  Dashboard,
  Products,
  Crm,
  Assistant,
  Inventory,
  Placeholder,
  Conversation
};
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/erp-app/pages.jsx", error: String((e && e.message) || e) }); }

// ui_kits/erp-app/ui.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
// Shared UI helpers for the ERP kit. Uses DS classes from styles.css/components.css.
const {
  useState,
  useEffect,
  useRef
} = React;
function Icon({
  name,
  size = 18,
  color,
  style
}) {
  const ref = useRef(null);
  useEffect(() => {
    if (ref.current && window.lucide) {
      ref.current.innerHTML = "";
      const el = document.createElement("i");
      el.setAttribute("data-lucide", name);
      ref.current.appendChild(el);
      window.lucide.createIcons({
        attrs: {
          width: size,
          height: size,
          stroke: color || "currentColor",
          "stroke-width": 1.75
        },
        nameAttr: "data-lucide"
      });
    }
  }, [name, size, color]);
  return /*#__PURE__*/React.createElement("span", {
    ref: ref,
    style: {
      display: "inline-flex",
      width: size,
      height: size,
      color,
      ...style
    }
  });
}
function Btn({
  variant = "primary",
  size = "md",
  icon,
  iconRight,
  children,
  ...p
}) {
  return /*#__PURE__*/React.createElement("button", _extends({
    className: `erp-btn erp-btn--${variant} erp-btn--${size}`
  }, p), icon && /*#__PURE__*/React.createElement(Icon, {
    name: icon,
    size: size === "sm" ? 14 : 16
  }), children, iconRight && /*#__PURE__*/React.createElement(Icon, {
    name: iconRight,
    size: 16
  }));
}
const BADGE = {
  neutral: ["var(--surface-hover)", "var(--text-body)"],
  emerald: ["var(--emerald-glow)", "var(--emerald-400)"],
  amber: ["var(--amber-glow)", "var(--amber-400)"],
  rose: ["var(--rose-glow)", "var(--rose-400)"],
  sky: ["var(--sky-glow)", "var(--sky-400)"],
  violet: ["var(--violet-glow)", "var(--violet-400)"]
};
function Badge({
  tone = "neutral",
  dot,
  children
}) {
  const [bg, fg] = BADGE[tone];
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 6,
      background: bg,
      color: fg,
      font: "600 11px/1 var(--font-sans)",
      letterSpacing: ".02em",
      padding: "5px 10px",
      borderRadius: 999,
      textTransform: "capitalize"
    }
  }, dot && /*#__PURE__*/React.createElement("span", {
    style: {
      width: 6,
      height: 6,
      borderRadius: 999,
      background: "currentColor"
    }
  }), children);
}
function Spark({
  data,
  up = true,
  w = 80,
  h = 28,
  fill = false
}) {
  const max = Math.max(...data),
    min = Math.min(...data),
    span = max - min || 1;
  const pts = data.map((v, i) => [i / (data.length - 1) * w, h - (v - min) / span * (h - 4) - 2]);
  const line = pts.map((p, i) => `${i ? "L" : "M"}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(" ");
  const color = up ? "var(--emerald-400)" : "var(--rose-400)";
  return /*#__PURE__*/React.createElement("svg", {
    width: w,
    height: h,
    viewBox: `0 0 ${w} ${h}`,
    preserveAspectRatio: "none"
  }, fill && /*#__PURE__*/React.createElement("path", {
    d: `${line} L${w},${h} L0,${h} Z`,
    fill: color,
    opacity: "0.10"
  }), /*#__PURE__*/React.createElement("path", {
    d: line,
    fill: "none",
    stroke: color,
    strokeWidth: "1.75",
    strokeLinecap: "round",
    strokeLinejoin: "round"
  }));
}
function Kpi({
  label,
  value,
  unit,
  delta,
  spark,
  tone
}) {
  const up = (delta ?? 0) >= 0;
  return /*#__PURE__*/React.createElement("div", {
    className: "erp-card erp-card--hover",
    style: {
      padding: 20,
      display: "flex",
      flexDirection: "column",
      gap: 16,
      position: "relative",
      overflow: "hidden"
    }
  }, tone !== "neutral" && /*#__PURE__*/React.createElement("div", {
    style: {
      position: "absolute",
      inset: 0,
      background: "var(--wash-emerald)",
      pointerEvents: "none"
    }
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      justifyContent: "space-between",
      alignItems: "center",
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement("span", {
    className: "ds-eyebrow"
  }, label), /*#__PURE__*/React.createElement(Spark, {
    data: spark,
    up: up,
    fill: true
  })), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "baseline",
      gap: 6,
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      font: "600 30px/1 var(--font-sans)",
      letterSpacing: "-.03em",
      color: "var(--text-strong)",
      fontVariantNumeric: "tabular-nums"
    }
  }, value), unit && /*#__PURE__*/React.createElement("span", {
    style: {
      font: "500 13px/1 var(--font-sans)",
      color: "var(--text-muted)"
    }
  }, unit)), /*#__PURE__*/React.createElement("span", {
    style: {
      display: "inline-flex",
      alignItems: "center",
      gap: 5,
      font: "600 12px/1 var(--font-sans)",
      color: up ? "var(--emerald-400)" : "var(--rose-400)",
      position: "relative"
    }
  }, /*#__PURE__*/React.createElement(Icon, {
    name: up ? "trending-up" : "trending-down",
    size: 14
  }), " ", Math.abs(delta), "% ", /*#__PURE__*/React.createElement("span", {
    style: {
      color: "var(--text-faint)",
      fontWeight: 500
    }
  }, "vs last month")));
}
function PageHead({
  title,
  sub,
  children
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      alignItems: "flex-end",
      justifyContent: "space-between",
      gap: 16,
      marginBottom: 28
    }
  }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      font: "600 28px/1.1 var(--font-sans)",
      letterSpacing: "-.03em",
      color: "var(--text-strong)"
    }
  }, title), sub && /*#__PURE__*/React.createElement("p", {
    style: {
      margin: "8px 0 0",
      font: "400 14px/1.4 var(--font-sans)",
      color: "var(--text-muted)"
    }
  }, sub)), /*#__PURE__*/React.createElement("div", {
    style: {
      display: "flex",
      gap: 10
    }
  }, children));
}
window.ERP_UI = {
  Icon,
  Btn,
  Badge,
  Spark,
  Kpi,
  PageHead
};
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/erp-app/ui.jsx", error: String((e && e.message) || e) }); }

__ds_ns.KpiCard = __ds_scope.KpiCard;

__ds_ns.Badge = __ds_scope.Badge;

__ds_ns.Card = __ds_scope.Card;

__ds_ns.CardHeader = __ds_scope.CardHeader;

__ds_ns.CardTitle = __ds_scope.CardTitle;

__ds_ns.CardContent = __ds_scope.CardContent;

__ds_ns.Skeleton = __ds_scope.Skeleton;

__ds_ns.StatusDot = __ds_scope.StatusDot;

__ds_ns.Table = __ds_scope.Table;

__ds_ns.THead = __ds_scope.THead;

__ds_ns.TBody = __ds_scope.TBody;

__ds_ns.Tr = __ds_scope.Tr;

__ds_ns.Th = __ds_scope.Th;

__ds_ns.Td = __ds_scope.Td;

__ds_ns.Dialog = __ds_scope.Dialog;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.IconButton = __ds_scope.IconButton;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.Select = __ds_scope.Select;

})();
