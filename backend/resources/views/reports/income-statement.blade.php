<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { size: A4; margin: 2cm; }
    body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; font-size: 11px; }
    h1 { font-size: 20px; margin-bottom: 2px; }
    h2 { font-size: 13px; margin: 22px 0 6px; }
    .sub { color: #64748b; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; background: #f1f5f9; padding: 6px 8px; border-bottom: 2px solid #cbd5e1; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; }
    td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
    .r { text-align: right; }
    .brand { color: #10b981; }
    .subtotal td { border-top: 2px solid #cbd5e1; font-weight: bold; }
    .net { margin-top: 26px; font-size: 15px; font-weight: bold; text-align: right; }
    .profit { color: #047857; }
    .loss { color: #b91c1c; }
  </style>
</head>
<body>
  <h1><span class="brand">Intelligent ERP</span> — {{ $title }}</h1>
  <p class="sub">
    @if($date_from || $date_to) Period: {{ $date_from ?? '—' }} → {{ $date_to ?? '—' }} · @endif
    Generated on {{ now()->format('Y-m-d H:i') }}
  </p>

  <h2>Income</h2>
  <table>
    <thead><tr><th>Code</th><th>Account</th><th class="r">Amount</th></tr></thead>
    <tbody>
      @forelse($income as $r)
        <tr><td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td><td class="r">{{ number_format($r['balance'], 2) }}</td></tr>
      @empty
        <tr><td colspan="3">No income recorded in this period.</td></tr>
      @endforelse
      <tr class="subtotal"><td colspan="2">Total income</td><td class="r">{{ number_format($total_income, 2) }}</td></tr>
    </tbody>
  </table>

  <h2>Expenses</h2>
  <table>
    <thead><tr><th>Code</th><th>Account</th><th class="r">Amount</th></tr></thead>
    <tbody>
      @forelse($expenses as $r)
        <tr><td>{{ $r['code'] }}</td><td>{{ $r['name'] }}</td><td class="r">{{ number_format($r['balance'], 2) }}</td></tr>
      @empty
        <tr><td colspan="3">No expenses recorded in this period.</td></tr>
      @endforelse
      <tr class="subtotal"><td colspan="2">Total expenses</td><td class="r">{{ number_format($total_expenses, 2) }}</td></tr>
    </tbody>
  </table>

  <p class="net">
    Net {{ $net_profit >= 0 ? 'profit' : 'loss' }}:
    <span class="{{ $net_profit >= 0 ? 'profit' : 'loss' }}">{{ number_format(abs($net_profit), 2) }}</span>
  </p>
</body>
</html>
