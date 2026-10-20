@php
    $brand = $branding ?? [];
    $appName = $brand['app_name'] ?? config('app.name');
    $logoUrl = $brand['logo_url'] ?? '';
    $primaryColor = $brand['primary_color'] ?? '#2563EB';
    $secondaryColor = $brand['secondary_color'] ?? '#0F172A';
    $accentColor = $brand['accent_color'] ?? '#E2E8F0';
    $footerText = $brand['footer_text'] ?? '';
@endphp
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>{{ $subject ?? $appName }}</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:20px 24px;background:{{ $secondaryColor }};color:#ffffff;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td>
                                    <strong style="font-size:18px;line-height:1.3;">{{ $appName }}</strong>
                                </td>
                                @if($logoUrl !== '')
                                    <td align="right">
                                        <img src="{{ $logoUrl }}" alt="{{ $appName }}" style="max-height:36px;max-width:180px;">
                                    </td>
                                @endif
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px 24px;line-height:1.6;font-size:15px;">
                        @yield('content')
                    </td>
                </tr>
                @include('mail.partials.signature_html')
                <tr>
                    <td style="padding:18px 24px;background:{{ $accentColor }};font-size:12px;color:#475569;">
                        {{ $footerText }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

