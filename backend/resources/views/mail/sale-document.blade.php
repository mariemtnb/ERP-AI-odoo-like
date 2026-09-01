<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;background:#f5f8f6;font-family:Arial,Helvetica,sans-serif;color:#13211c;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border:1px solid #e2e9e5;border-radius:14px;overflow:hidden;">
        <tr><td style="background:#0e7c5a;color:#ffffff;padding:20px 28px;font-size:18px;font-weight:bold;">
          {{ $docLabel }} {{ $sale->number }}
        </td></tr>
        <tr><td style="padding:28px;">
          <p style="margin:0 0 14px;font-size:15px;">Hello {{ $sale->customer?->name ?? 'there' }},</p>
          <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">
            Please find your {{ strtolower($docLabel) }}
            <strong>{{ $sale->number }}</strong> for a total of
            <strong>{{ number_format((float) $sale->total_amount, 3) }} TND</strong> attached to this email.
          </p>
          <p style="margin:0 0 22px;font-size:15px;line-height:1.6;">
            You can also view it any time online:
          </p>
          <p style="margin:0 0 22px;">
            <a href="{{ $portalUrl }}"
               style="background:#0e7c5a;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:8px;font-size:15px;display:inline-block;">
              View your {{ strtolower($docLabel) }}
            </a>
          </p>
          <p style="margin:0;font-size:12px;color:#58665f;word-break:break-all;">
            Or paste this link into your browser:<br>{{ $portalUrl }}
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
