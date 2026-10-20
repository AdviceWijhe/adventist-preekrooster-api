@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Uw verificatiecode</h1>
    <p style="margin:0 0 16px;">Beste {{ $voornaam }},</p>
    <p style="margin:0 0 16px;">Gebruik onderstaande code om uw inlog te bevestigen. De code is 10 minuten geldig.</p>
    <p style="margin:0 0 16px;font-size:28px;font-weight:700;color:{{ $branding['primary_color'] ?? '#2563EB' }};letter-spacing:3px;">
        {{ $code }}
    </p>
    <p style="margin:0;">Heeft u dit niet aangevraagd? Dan kunt u deze e-mail negeren.</p>
@endsection

