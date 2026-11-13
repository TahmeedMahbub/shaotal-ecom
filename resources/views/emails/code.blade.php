<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <title>{{ $subject ?? $heading }}</title>
</head>
{{-- Shared one-time-code email (verification, password reset). Table layout + inline styles only: email clients
     ignore <style> and modern CSS. Colours: Shaotal tokens. --}}
<body style="margin:0; padding:0; background-color:#FAF8F4; -webkit-text-size-adjust:100%;">
  <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $heading }}: your {{ $brand }} code expires in {{ $minutes }} minutes.</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FAF8F4;">
    <tr>
      <td align="center" style="padding:32px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px; background-color:#FFFFFF; border:1px solid #E5DED6; border-radius:12px;">
          <tr>
            <td align="center" style="padding:28px 24px 8px;">
              {{-- logo.png is 365×66: 160×29 keeps the ratio; width/height attributes reserve the space before it loads. --}}
              <img src="{{ $logoUrl }}" width="160" height="29" alt="{{ $brand }} logo" style="display:block; width:160px; max-width:60%; height:auto; border:0; outline:none; text-decoration:none;">
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 0; font-family:Arial, Helvetica, sans-serif; color:#231E1A;">
              <h1 style="margin:0 0 12px; font-size:22px; line-height:1.3; font-weight:bold; text-align:center;">{{ $heading }}</h1>
              <p style="margin:0 0 8px; font-size:15px; line-height:1.6; color:#4A4440;">Hi {{ $firstName }},</p>
              <p style="margin:0; font-size:15px; line-height:1.6; color:#4A4440;">{{ $intro }}</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:24px 32px;">
              <div style="display:inline-block; padding:16px 28px 16px 40px; background-color:#FBF3EC; border:2px solid #BE6D37; border-radius:12px; font-family:'Courier New', Courier, monospace; font-size:36px; line-height:1; font-weight:bold; letter-spacing:12px; color:#231E1A;">{{ $code }}</div>
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 28px; font-family:Arial, Helvetica, sans-serif; color:#6C6560; font-size:14px; line-height:1.6;">
              <p style="margin:0 0 8px; text-align:center;">This code expires in <strong style="color:#231E1A;">{{ $minutes }} minutes</strong> and can be used once.</p>
              <p style="margin:0; text-align:center;">{{ $ignore }}</p>
            </td>
          </tr>
        </table>
        <p style="margin:16px 0 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.5; color:#8A837D;">&copy; {{ date('Y') }} {{ $brand }}. Curated artisan goods for Australia.</p>
      </td>
    </tr>
  </table>
</body>
</html>
