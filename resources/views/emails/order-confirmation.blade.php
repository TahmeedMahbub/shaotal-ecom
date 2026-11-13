<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="color-scheme" content="light">
  <meta name="x-apple-disable-message-reformatting">
  <title>Your {{ $brand }} order {{ $number }}</title>
  <style>
    @media only screen and (max-width: 520px) {
      .wrap { padding: 16px 8px !important; }
      .pad { padding-left: 20px !important; padding-right: 20px !important; }
      .stack { display: block !important; width: 100% !important; }
      .stack-gap { padding-top: 16px !important; }
      .btn a { display: block !important; }
    }
  </style>
</head>
{{-- Table layout + inline styles: email clients ignore most modern CSS. The <style> block only adds the mobile tweaks above
     and is harmless where it is stripped. Colours are the Shaotal tokens used by the other emails. --}}
<body style="margin:0; padding:0; background-color:#FAF8F4; -webkit-text-size-adjust:100%;">
  <div style="display:none; max-height:0; overflow:hidden; opacity:0; font-size:1px; line-height:1px;">Order {{ $number }}, placed {{ $placedOn }}. Here is everything we have on file for it.</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FAF8F4;">
    <tr>
      <td align="center" class="wrap" style="padding:32px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; background-color:#FFFFFF; border:1px solid #E5DED6; border-radius:12px;">
          <tr>
            <td align="center" style="padding:28px 24px 4px;">
              <img src="{{ $logoUrl }}" width="160" height="29" alt="{{ $brand }}" style="display:block; width:160px; max-width:60%; height:auto; border:0; outline:none; text-decoration:none;">
            </td>
          </tr>

          <tr>
            <td class="pad" style="padding:20px 40px 0; font-family:Arial, Helvetica, sans-serif; color:#231E1A;">
              <h1 style="margin:0 0 12px; font-size:24px; line-height:1.3; font-weight:bold;">Thanks for your order, {{ $firstName }}.</h1>
              <p style="margin:0 0 8px; font-size:15px; line-height:1.6; color:#4A4440;">We have order <strong style="color:#231E1A;">{{ $number }}</strong> and we are getting it ready. It was placed on {{ $placedOn }}. Everything we have on file is below, so you can check it over.</p>
            </td>
          </tr>

          {{-- Payment --}}
          <tr>
            <td class="pad" style="padding:16px 40px 0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FBF3EC; border:1px solid #EBD3BF; border-radius:10px;">
                <tr>
                  <td style="padding:14px 18px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.6; color:#4A4440;">
                    <div style="font-size:12px; letter-spacing:.06em; text-transform:uppercase; color:#8A5326; font-weight:bold; margin-bottom:4px;">Payment &middot; {{ $payment['status'] }}</div>
                    @foreach ($payment['lines'] as $line)
                      <div>{{ $line }}</div>
                    @endforeach
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          {{-- Gift --}}
          @if ($gift)
            <tr>
              <td class="pad" style="padding:12px 40px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FFFFFF; border:1px dashed #BE6D37; border-radius:10px;">
                  <tr>
                    <td style="padding:14px 18px; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.6; color:#4A4440;">
                      <div style="font-size:12px; letter-spacing:.06em; text-transform:uppercase; color:#8A5326; font-weight:bold; margin-bottom:4px;">This order is a gift</div>
                      <div>{{ $gift['count'] }} {{ $gift['count'] === 1 ? 'item' : 'items' }} will be gift wrapped ({{ $gift['fee'] }}):</div>
                      <ul style="margin:6px 0 0; padding-left:18px;">
                        @foreach ($gift['items'] as $g)
                          <li>{{ $g }}</li>
                        @endforeach
                      </ul>
                      @if ($gift['message'] !== '')
                        <div style="margin-top:10px; padding-top:10px; border-top:1px solid #EBD3BF;"><span style="font-size:12px; text-transform:uppercase; letter-spacing:.06em; color:#8A5326; font-weight:bold;">Your gift message</span><br>&ldquo;{!! nl2br(e($gift['message'])) !!}&rdquo;</div>
                      @endif
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          @endif

          {{-- Items --}}
          <tr>
            <td class="pad" style="padding:24px 40px 0; font-family:Arial, Helvetica, sans-serif;">
              <div style="font-size:12px; letter-spacing:.06em; text-transform:uppercase; color:#8A837D; font-weight:bold; padding-bottom:8px; border-bottom:1px solid #E5DED6;">Your items</div>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                @foreach ($items as $i)
                  <tr>
                    <td style="padding:12px 0; border-bottom:1px solid #F0EAE3; font-size:14px; line-height:1.5; color:#231E1A;">
                      <strong>{{ $i['name'] }}</strong>
                      @if ($i['variant'] !== '')<br><span style="color:#6C6560;">{{ $i['variant'] }}</span>@endif
                      <br><span style="color:#6C6560;">Qty {{ $i['qty'] }} &times; {{ $i['unit'] }}</span>
                    </td>
                    <td align="right" valign="top" style="padding:12px 0; border-bottom:1px solid #F0EAE3; font-size:14px; color:#231E1A; white-space:nowrap;">{{ $i['total'] }}</td>
                  </tr>
                @endforeach
              </table>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;">
                @foreach ($rows as [$label, $value, $strong])
                  <tr>
                    <td style="padding:5px 0; font-size:{{ $strong ? '16' : '14' }}px; color:{{ $strong ? '#231E1A' : '#4A4440' }}; {{ $strong ? 'font-weight:bold; border-top:1px solid #E5DED6; padding-top:10px;' : '' }}">{{ $label }}</td>
                    <td align="right" style="padding:5px 0; font-size:{{ $strong ? '16' : '14' }}px; color:#231E1A; {{ $strong ? 'font-weight:bold; border-top:1px solid #E5DED6; padding-top:10px;' : '' }} white-space:nowrap;">{{ $value }}</td>
                  </tr>
                @endforeach
              </table>
            </td>
          </tr>

          {{-- Delivery --}}
          <tr>
            <td class="pad" style="padding:24px 40px 0; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.6; color:#4A4440;">
              <div style="font-size:12px; letter-spacing:.06em; text-transform:uppercase; color:#8A837D; font-weight:bold; padding-bottom:8px; border-bottom:1px solid #E5DED6; margin-bottom:8px;">Delivering to</div>
              @foreach ($address as $line)
                <div>{{ $line }}</div>
              @endforeach
              @if ($phone)<div>{{ $phone }}</div>@endif
              <div style="margin-top:8px; color:#6C6560;">We deliver within Australia. You can follow this order at any time from My Orders.</div>
            </td>
          </tr>

          <tr>
            <td align="center" class="pad btn" style="padding:28px 40px 8px;">
              <a href="{{ $orderUrl }}" style="display:inline-block; background-color:#BE6D37; color:#FFFFFF; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; text-decoration:none; padding:13px 28px; border-radius:8px;">View your order</a>
            </td>
          </tr>
          <tr>
            <td class="pad" style="padding:8px 40px 32px; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#6C6560; text-align:center;">
              You will need to sign in to see it. If something here looks wrong, just reply to this email and we will sort it out.
            </td>
          </tr>
        </table>
        <p style="margin:16px 0 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.5; color:#8A837D;">&copy; {{ $year }} {{ $brand }}. Curated artisan goods for Australia.</p>
      </td>
    </tr>
  </table>
</body>
</html>
