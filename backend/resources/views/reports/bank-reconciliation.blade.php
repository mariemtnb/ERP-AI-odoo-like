<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { size: A4; margin: 2cm; }
    body { font-family: Helvetica, Arial, sans-serif; color: #1e293b; font-size: 11px; }
    h1 { font-size: 20px; margin-bottom: 2px; }
    h2 { font-size: 13px; margin: 22px 0 6px; }
    .sub { color: #64748b; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { text-align: left; background: #f1f5f9; padding: 6px 8px; border-bottom: 2px solid #cbd5e1; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; }
    td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
    .r { text-align: right; }
    .brand { color: #10b981; }
    .totals td { border-top: 2px solid #cbd5e1; font-weight: bold; }
    .muted { color: #64748b; }
    .warn { color: #b45309; font-weight: bold; }
  </style>
</head>
<body>
  <h1><span class="brand">Intelligent ERP</span> — {{ $title }}</h1>
  <p class="sub">
    {{ $bank_account['label'] }} · {{ $bank_account['bank_name'] }} · {{ $bank_account['currency'] }}
    @if($date_from || $date_to) <br>Period: {{ $date_from ?? '—' }} → {{ $date_to ?? '—' }} @endif
    <br>Generated on {{ now()->format('Y-m-d H:i') }}
  </p>

  <h2>Balances</h2>
  <table>
    <tbody>
      <tr><td>Opening balance</td><td class="r">{{ $opening_balance }}</td></tr>
      <tr><td>Statement movement</td><td class="r">{{ $statement_movement }}</td></tr>
      <tr><td>Statement balance</td><td class="r">{{ $statement_balance }}</td></tr>
      <tr><td>Book balance (ERP)</td><td class="r">{{ $book_balance }}</td></tr>
      <tr class="totals">
        <td>Difference</td>
        <td class="r {{ (float) $difference == 0.0 ? '' : 'warn' }}">{{ $difference }}</td>
      </tr>
    </tbody>
  </table>

  <h2>Statement lines</h2>
  <table>
    <tbody>
      <tr><td>Total lines</td><td class="r">{{ $counts['total'] }}</td><td>Matched</td><td class="r">{{ $counts['matched'] }} ({{ $amounts['matched'] }})</td></tr>
      <tr><td>Partially matched</td><td class="r">{{ $counts['partially_matched'] }} ({{ $amounts['partially_matched'] }})</td><td>Unmatched</td><td class="r">{{ $counts['unmatched'] }} ({{ $amounts['unmatched'] }})</td></tr>
      <tr><td>Disputed</td><td class="r">{{ $counts['disputed'] }} ({{ $amounts['disputed'] }})</td><td></td><td></td></tr>
    </tbody>
  </table>

  @if($instruments_in_transit['count'] > 0)
  <h2>Instruments in transit ({{ $instruments_in_transit['count'] }} · {{ $instruments_in_transit['amount'] }})</h2>
  <p class="muted">Cheques and commercial paper deposited but not yet credited by the bank.</p>
  <table>
    <thead><tr><th>Number</th><th>Type</th><th>Due date</th><th>Status</th><th class="r">Amount</th></tr></thead>
    <tbody>
      @foreach($instruments_in_transit['items'] as $i)
      <tr>
        <td>{{ $i['number'] }}</td>
        <td>{{ ucfirst($i['kind']) }}</td>
        <td>{{ $i['due_date'] ?? '—' }}</td>
        <td>{{ str_replace('_', ' ', $i['status']) }}</td>
        <td class="r">{{ $i['amount'] }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif

  @if(count($open_items) > 0)
  <h2>Open items</h2>
  <table>
    <thead><tr><th>Date</th><th>Label</th><th>Reference</th><th>Status</th><th class="r">Amount</th><th class="r">Remaining</th></tr></thead>
    <tbody>
      @foreach($open_items as $t)
      <tr>
        <td>{{ $t['operation_date'] }}</td>
        <td>{{ \Illuminate\Support\Str::limit($t['label'], 45) }}</td>
        <td>{{ $t['reference'] }}</td>
        <td>{{ str_replace('_', ' ', $t['status']) }}</td>
        <td class="r">{{ $t['amount'] }}</td>
        <td class="r">{{ $t['remaining_amount'] }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @else
  <h2>Open items</h2>
  <p class="muted">Every statement line for this period is fully matched.</p>
  @endif
</body>
</html>
