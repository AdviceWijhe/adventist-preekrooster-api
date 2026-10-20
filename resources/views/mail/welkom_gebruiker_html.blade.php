@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Welkom bij {{ $branding['app_name'] ?? 'Preekrooster' }}</h1>
    <p style="margin:0 0 16px;">{!! nl2br(e($inhoud)) !!}</p>
    <p style="margin:0;">
        <a href="{{ $setPasswordUrl }}" style="display:inline-block;background:{{ $branding['primary_color'] ?? '#2563EB' }};color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">Wachtwoord aanmaken</a>
    </p>
@endsection
