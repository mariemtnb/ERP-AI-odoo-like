<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;background:#f5f8f6;font-family:Arial,Helvetica,sans-serif;color:#13211c;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border:1px solid #e2e9e5;border-radius:14px;overflow:hidden;">
        <tr><td style="background:#b45309;color:#ffffff;padding:20px 28px;font-size:18px;font-weight:bold;">
          {{ $dunningLevel->name }} — Invoice {{ $sale->number }}
        </td></tr>
        <tr><td style="padding:28px;">
          <p style="margin:0 0 14px;font-size:15px;">Hello {{ $sale->customer?->name ?? 'there' }},</p>
          <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">{{ $dunningLevel->message }}</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                 style="margin:0 0 20px;border:1px solid #e2e9e5;border-radius:10px;">
            <tr><td style="padding:12px 16px;font-size:14px;">Invoice</td>
                <td style="padding:12px 16px;font-size:14px;text-align:right;"><strong>{{ $sale->number }}</strong></td></tr>
            <tr><td style="padding:12px 16px;font-size:14px;border-top:1px solid #eef3f0;">Days overdue</td>
                <td style="padding:12px 16px;font-size:14px;text-align:right;border-top:1px solid #eef3f0;">{{ $daysOverdue }}</td></tr>
            <tr><td style="padding:12px 16px;font-size:14px;border-top:1px solid #eef3f0;">Amount outstanding</td>
                <td style="padding:12px 16px;font-size:14px;text-align:right;border-top:1px solid #eef3f0;">
                  <strong>{{ number_format($outstanding, 3) }} TND</strong></td></tr>
          </table>
          <p style="margin:0 0 22px;font-size:15px;line-height:1.6;">
            You can view and pay the invoice online here:<br>
            <a href="{{ $portalUrl }}" style="color:#0e7c5a;">{{ $portalUrl }}</a>
          </p>
          <p style="margin:0;font-size:13px;color:#5b6b64;">
            If you have already paid, please disregard this message.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
