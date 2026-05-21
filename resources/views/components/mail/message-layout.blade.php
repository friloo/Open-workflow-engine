<!DOCTYPE html>
<html lang="de">
<head><meta charset="utf-8"><title>{{ $title ?? config('app.name') }}</title></head>
<body style="margin:0;background:#f1f5f9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;padding:24px 0;">
  <tr><td align="center">
    <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="background:white;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
      <tr><td style="padding:20px 28px;border-bottom:1px solid #f1f5f9;font-weight:600;color:#0f172a;">
        <span style="display:inline-block;width:24px;height:24px;background:#6366f1;color:white;text-align:center;line-height:24px;border-radius:6px;font-weight:700;font-size:12px;margin-right:8px;">W</span>
        {{ config('app.name') }}
      </td></tr>
      <tr><td style="padding:24px 28px;line-height:1.5;font-size:14px;">
        {{ $slot }}
      </td></tr>
      <tr><td style="padding:16px 28px;border-top:1px solid #f1f5f9;color:#64748b;font-size:12px;">
        Diese E-Mail wurde automatisch erzeugt. Falls du keine Workflow-Benachrichtigungen mehr erhalten möchtest, kannst du das in deinen Profil-Einstellungen ändern.
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
