@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Wachtwoord resetten</h1>
    <p style="margin:0 0 16px;">Er is een verzoek ontvangen om uw wachtwoord te resetten.</p>
    <p style="margin:0 0 16px;">
        <a href="{{ $resetUrl }}" style="display:inline-block;background:{{ $branding['primary_color'] ?? '#2563EB' }};color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">Reset wachtwoord</a>
    </p>
    <p style="margin:0;">Deze link verloopt na {{ $expireMinutes }} minuten.</p>
@endsection

