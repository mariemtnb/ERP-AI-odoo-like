<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 11px; }
    h1 { font-size: 20px; margin-bottom: 2px; }
    .sub { color: #64748b; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th { text-align: left; background: #f1f5f9; padding: 6px 8px; border-bottom: 2px solid #cbd5e1; font-size: 10px; text-transform: uppercase; }
    td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
    .r { text-align: right; }
    .low { color: #dc2626; font-weight: bold; }
    .cancelled { color: #94a3b8; text-decoration: line-through; }
    .totals { margin-top: 16px; text-align: right; font-size: 13px; }
    .brand { color: #4f46e5; }
  </style>
</head>
<body>
  <h1><span class="brand">Intelligent ERP</span> — {{ $title }}</h1>
  <p class="sub">
    @isset($date_from) Period: {{ $date_from }} → {{ $date_to }} · @endisset
    Generated on {{ now()->format('Y-m-d H:i') }}
  </p>

  @if (!empty($rows) && isset($rows[0]['sku']))
  <table>
    <thead>
      <tr><th>SKU</th><th>Product</th><th>Category</th><th class="r">Quantity</th><th class="r">Min level</th><th class="r">Stock value</th></tr>
    </thead>
    <tbody>
      @foreach ($rows as $r)
      <tr>
        <td>{{ $r['sku'] }}</td>
        <td @if($r['low']) class="low" @endif>{{ $r['name'] }}</td>
        <td>{{ $r['category'] }}</td>
        <td class="r @if($r['low']) low @endif">{{ $r['quantity'] }}</td>
        <td class="r">{{ $r['min_level'] }}</td>
        <td class="r">{{ number_format($r['value'], 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <p class="totals">{{ $count }} products · Total stock value: <strong>{{ number_format($total, 2) }}</strong></p>
  @else
  <table>
    <thead>
      <tr><th>Number</th><th>Date</th><th>Partner</th><th>Status</th><th class="r">Total</th></tr>
    </thead>
    <tbody>
      @foreach ($rows as $r)
      <tr @if($r['status'] === 'cancelled') class="cancelled" @endif>
        <td>{{ $r['number'] }}</td>
        <td>{{ $r['date'] }}</td>
        <td>{{ $r['customer'] }}</td>
        <td>{{ $r['status'] }}</td>
        <td class="r">{{ number_format($r['total'], 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  <p class="totals">{{ $count }} documents · Total: <strong>{{ number_format($total, 2) }}</strong></p>
  @endif
</body>
</html>
