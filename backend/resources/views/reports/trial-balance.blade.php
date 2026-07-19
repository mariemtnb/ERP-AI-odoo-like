<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { size: A4; margin: 2cm; }
    body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; font-size: 11px; }
    h1 { font-size: 20px; margin-bottom: 2px; }
    .sub { color: #64748b; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { text-align: left; background: #f1f5f9; padding: 6px 8px; border-bottom: 2px solid #cbd5e1; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; }
    td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
    .r { text-align: right; }
    .brand { color: #10b981; }
    .totals td { border-top: 2px solid #cbd5e1; font-weight: bold; }
  </style>
</head>
<body>
  <h1><span class="brand">Intelligent ERP</span> — {{ $title }}</h1>
  <p class="sub">
    @if($date_from || $date_to) Period: {{ $date_from ?? '—' }} → {{ $date_to ?? '—' }} · @endif
    Generated on {{ now()->format('Y-m-d H:i') }}
  </p>

  <table>
    <thead>
      <tr><th>Code</th><th>Account</th><th>Type</th><th class="r">Debit</th><th class="r">Credit</th><th class="r">Balance</th></tr>
    </thead>
    <tbody>
      @foreach($rows as $r)
      <tr>
        <td>{{ $r['code'] }}</td>
        <td>{{ $r['name'] }}</td>
        <td>{{ ucfirst($r['type']) }}</td>
        <td class="r">{{ number_format($r['debit'], 2) }}</td>
        <td class="r">{{ number_format($r['credit'], 2) }}</td>
        <td class="r">{{ number_format($r['balance'], 2) }}</td>
      </tr>
      @endforeach
      <tr class="totals">
        <td colspan="3">Total</td>
        <td class="r">{{ number_format($total_debit, 2) }}</td>
        <td class="r">{{ number_format($total_credit, 2) }}</td>
        <td class="r">{{ $total_debit == $total_credit ? 'Balanced' : 'OUT OF BALANCE' }}</td>
      </tr>
    </tbody>
  </table>
</body>
</html>
