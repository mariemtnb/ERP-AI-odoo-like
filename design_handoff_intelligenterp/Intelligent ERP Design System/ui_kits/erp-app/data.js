// Mock data for the Intelligent ERP UI kit (matches src/types shapes).
window.ERP_DATA = {
  user: { first_name: "Amine", last_name: "Khelifi", email: "admin@erp.local", role: "admin" },
  kpis: [
    { label: "Revenue", value: "48,250", unit: "TND", delta: 12.4, spark: [22,26,24,30,28,34,33,40,44,48] },
    { label: "Sales orders", value: "184", delta: 4.1, tone: "neutral", spark: [12,14,13,15,14,16,15,17,18,18] },
    { label: "Purchase orders", value: "62", delta: -2.3, tone: "neutral", spark: [9,8,10,9,8,7,8,7,6,6] },
    { label: "Purchases", value: "21,900", unit: "TND", delta: 6.8, tone: "neutral", spark: [10,12,11,14,13,16,15,18,20,22] },
  ],
  revenueSeries: [28,31,30,35,33,38,36,42,40,45,43,49,47,52,50,56],
  topProducts: [
    { sku: "ERP-0042", name: "Aluminium bracket 40mm", sold: 320, revenue: "7,840" },
    { sku: "ERP-0088", name: "Copper coil 2.5mm", sold: 148, revenue: "6,512" },
    { sku: "ERP-0051", name: "Rubber gasket set", sold: 890, revenue: "3,916" },
    { sku: "ERP-0119", name: "Stainless bolt M8", sold: 1240, revenue: "2,480" },
  ],
  lowStock: [
    { sku: "ERP-0043", name: "Steel hinge 60mm", qty: 8, min: 25 },
    { sku: "ERP-0072", name: "PVC elbow joint", qty: 14, min: 40 },
    { sku: "ERP-0210", name: "Brass fitting 1/2\"", qty: 3, min: 30 },
  ],
  products: [
    { sku: "ERP-0042", name: "Aluminium bracket 40mm", cat: "Hardware", stock: 142, unit: "pcs", price: "24.50", active: true, low: false },
    { sku: "ERP-0043", name: "Steel hinge 60mm", cat: "Hardware", stock: 8, unit: "pcs", price: "6.20", active: true, low: true },
    { sku: "ERP-0051", name: "Rubber gasket set", cat: "Seals", stock: 326, unit: "set", price: "1.10", active: true, low: false },
    { sku: "ERP-0072", name: "PVC elbow joint", cat: "Plumbing", stock: 14, unit: "pcs", price: "3.40", active: true, low: true },
    { sku: "ERP-0088", name: "Copper coil 2.5mm", cat: "Electrical", stock: 54, unit: "m", price: "44.00", active: true, low: false },
    { sku: "ERP-0119", name: "Stainless bolt M8", cat: "Hardware", stock: 1240, unit: "pcs", price: "0.20", active: true, low: false },
    { sku: "ERP-0210", name: "Brass fitting 1/2\"", cat: "Plumbing", stock: 3, unit: "pcs", price: "5.80", active: false, low: true },
  ],
  leads: {
    new: [
      { id: 1, name: "Sofia Trabelsi", company: "Medina Textiles", phone: "+216 22 145 900" },
      { id: 2, name: "Karim Zouari", company: "Zouari Logistics", phone: "+216 98 231 774" },
    ],
    contacted: [
      { id: 3, name: "Leila Mansour", company: "Atlas Foods", phone: "+216 55 620 118" },
    ],
    qualified: [
      { id: 4, name: "Omar Belhaj", company: "BelTech", phone: "+216 71 004 552" },
      { id: 5, name: "Nadia Cherif", company: "Cherif & Co", phone: "+216 29 887 210" },
    ],
    won: [
      { id: 6, name: "Youssef Gharbi", company: "Gharbi Retail", phone: "+216 50 119 663" },
    ],
    lost: [
      { id: 7, name: "Rania Ben Salah", company: "—", phone: "+216 24 700 401" },
    ],
  },
  warehouses: [
    { id: 1, name: "Central Depot", def: true },
    { id: 2, name: "Sfax Branch", def: false },
    { id: 3, name: "Sousse Store", def: false },
  ],
  movements: [
    { id: 1, at: "Today · 14:22", sku: "ERP-0088", name: "Copper coil 2.5mm", type: "in", qty: 120, wh: "Central Depot", reason: "PO-2041 receipt", src: "purchase", by: "amine@erp.local" },
    { id: 2, at: "Today · 11:05", sku: "ERP-0119", name: "Stainless bolt M8", type: "out", qty: 400, wh: "Central Depot", reason: "SO-3312 shipment", src: "sale", by: "sofia@erp.local" },
    { id: 3, at: "Yesterday · 17:40", sku: "ERP-0210", name: "Brass fitting 1/2\"", type: "adjustment", qty: -6, wh: "Sfax Branch", reason: "Damage — recount", src: "manual", by: "amine@erp.local" },
    { id: 4, at: "Yesterday · 09:12", sku: "ERP-0051", name: "Rubber gasket set", type: "transfer", qty: 80, wh: "Central → Sousse", reason: "Rebalance stock", src: "transfer", by: "karim@erp.local" },
    { id: 5, at: "Mar 12 · 16:31", sku: "ERP-0042", name: "Aluminium bracket 40mm", type: "in", qty: 200, wh: "Central Depot", reason: "Initial stock", src: "manual", by: "amine@erp.local" },
    { id: 6, at: "Mar 12 · 10:08", sku: "ERP-0043", name: "Steel hinge 60mm", type: "out", qty: 17, wh: "Sfax Branch", reason: "SO-3298 shipment", src: "sale", by: "sofia@erp.local" },
  ],
  customers: [
    { id: 1, name: "Gharbi Retail", email: "contact@gharbi.tn", phone: "+216 50 119 663", city: "Tunis", orders: 42, active: true },
    { id: 2, name: "Medina Textiles", email: "hello@medinatex.tn", phone: "+216 22 145 900", city: "Sfax", orders: 18, active: true },
    { id: 3, name: "Atlas Foods", email: "purchasing@atlasfoods.tn", phone: "+216 55 620 118", city: "Sousse", orders: 27, active: true },
    { id: 4, name: "BelTech", email: "info@beltech.tn", phone: "+216 71 004 552", city: "Ariana", orders: 9, active: true },
    { id: 5, name: "Cherif & Co", email: "", phone: "+216 29 887 210", city: "Bizerte", orders: 4, active: false },
  ],
  suppliers: [
    { id: 1, name: "MetalWorks SARL", email: "sales@metalworks.tn", phone: "+216 71 330 210", city: "Tunis", orders: 61, active: true },
    { id: 2, name: "Poly Distribution", email: "orders@polydist.tn", phone: "+216 74 118 004", city: "Sfax", orders: 33, active: true },
    { id: 3, name: "ElectroSupply", email: "b2b@electrosupply.tn", phone: "+216 73 550 900", city: "Sousse", orders: 22, active: true },
    { id: 4, name: "Fastener Depot", email: "", phone: "+216 70 221 447", city: "Nabeul", orders: 7, active: false },
  ],
  purchases: [
    { number: "PO-2041", partner: "MetalWorks SARL", date: "2026-07-09", status: "received", total: "5,280.00", by: "amine@erp.local", lines: [["ERP-0088","Copper coil 2.5mm",120,"44.00"],["ERP-0042","Aluminium bracket 40mm",200,"24.50"]] },
    { number: "PO-2040", partner: "Poly Distribution", date: "2026-07-07", status: "confirmed", total: "1,960.00", by: "karim@erp.local", lines: [["ERP-0072","PVC elbow joint",400,"3.40"],["ERP-0051","Rubber gasket set",200,"1.10"]] },
    { number: "PO-2039", partner: "ElectroSupply", date: "2026-07-05", status: "pending_approval", total: "8,800.00", by: "karim@erp.local", lines: [["ERP-0088","Copper coil 2.5mm",200,"44.00"]] },
    { number: "PO-2038", partner: "Fastener Depot", date: "2026-07-02", status: "draft", total: "248.00", by: "amine@erp.local", lines: [["ERP-0119","Stainless bolt M8",1240,"0.20"]] },
    { number: "PO-2037", partner: "MetalWorks SARL", date: "2026-06-28", status: "cancelled", total: "1,470.00", by: "amine@erp.local", lines: [["ERP-0043","Steel hinge 60mm",50,"6.20"]] },
  ],
  sales: [
    { number: "SO-3312", partner: "Gharbi Retail", date: "2026-07-11", status: "confirmed", total: "3,140.00", by: "sofia@erp.local", lines: [["ERP-0119","Stainless bolt M8",400,"0.35"],["ERP-0042","Aluminium bracket 40mm",100,"29.90"]] },
    { number: "SO-3311", partner: "Atlas Foods", date: "2026-07-10", status: "received", total: "890.00", by: "sofia@erp.local", lines: [["ERP-0051","Rubber gasket set",600,"1.48"]] },
    { number: "SO-3310", partner: "Medina Textiles", date: "2026-07-08", status: "draft", total: "1,196.00", by: "sofia@erp.local", lines: [["ERP-0088","Copper coil 2.5mm",20,"59.80"]] },
    { number: "SO-3309", partner: "BelTech", date: "2026-07-06", status: "confirmed", total: "418.00", by: "omar@erp.local", lines: [["ERP-0072","PVC elbow joint",110,"3.80"]] },
    { number: "SO-3308", partner: "Gharbi Retail", date: "2026-07-03", status: "cancelled", total: "220.00", by: "sofia@erp.local", lines: [["ERP-0043","Steel hinge 60mm",20,"11.00"]] },
  ],
  users: [
    { email: "amine@erp.local", first: "Amine", last: "Khelifi", role: "admin", active: true },
    { email: "sofia@erp.local", first: "Sofia", last: "Trabelsi", role: "manager", active: true },
    { email: "karim@erp.local", first: "Karim", last: "Zouari", role: "manager", active: true },
    { email: "omar@erp.local", first: "Omar", last: "Belhaj", role: "employee", active: true },
    { email: "leila@erp.local", first: "Leila", last: "Mansour", role: "employee", active: false },
  ],
  reportRows: {
    sales: [
      { number: "SO-3312", date: "2026-07-11", partner: "Gharbi Retail", status: "confirmed", total: "3,140.00" },
      { number: "SO-3311", date: "2026-07-10", partner: "Atlas Foods", status: "received", total: "890.00" },
      { number: "SO-3309", date: "2026-07-06", partner: "BelTech", status: "confirmed", total: "418.00" },
      { number: "SO-3308", date: "2026-07-03", partner: "Gharbi Retail", status: "cancelled", total: "220.00" },
    ],
    purchases: [
      { number: "PO-2041", date: "2026-07-09", partner: "MetalWorks SARL", status: "received", total: "5,280.00" },
      { number: "PO-2040", date: "2026-07-07", partner: "Poly Distribution", status: "confirmed", total: "1,960.00" },
      { number: "PO-2039", date: "2026-07-05", partner: "ElectroSupply", status: "pending_approval", total: "8,800.00" },
    ],
    stock: [
      { sku: "ERP-0042", name: "Aluminium bracket 40mm", cat: "Hardware", qty: 142, min: 40, value: "3,479.00", low: false },
      { sku: "ERP-0043", name: "Steel hinge 60mm", cat: "Hardware", qty: 8, min: 25, value: "49.60", low: true },
      { sku: "ERP-0088", name: "Copper coil 2.5mm", cat: "Electrical", qty: 54, min: 20, value: "2,376.00", low: false },
      { sku: "ERP-0210", name: "Brass fitting 1/2\"", cat: "Plumbing", qty: 3, min: 30, value: "17.40", low: true },
    ],
  },
  nav: [
    { to: "dashboard", label: "Dashboard", icon: "layout-dashboard" },
    { to: "products", label: "Products", icon: "package" },
    { to: "inventory", label: "Inventory", icon: "boxes" },
    { to: "customers", label: "Customers", icon: "user-square-2" },
    { to: "suppliers", label: "Suppliers", icon: "truck" },
    { to: "purchases", label: "Purchases", icon: "shopping-cart" },
    { to: "sales", label: "Sales", icon: "receipt" },
    { to: "crm", label: "CRM", icon: "contact" },
    { to: "reports", label: "Reports", icon: "file-text" },
    { to: "assistant", label: "AI Assistant", icon: "sparkles" },
    { to: "users", label: "Users", icon: "users" },
  ],
};
