@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Nieuwe uitvraag voor een dienst</h1>
    <p style="margin:0 0 16px;">{!! nl2br(e($inhoud)) !!}</p>
    <ul style="margin:0 0 16px;padding-left:18px;">
        <li>Datum: {{ \Carbon\Carbon::parse($spreekbeurt->dienst->datum)->isoFormat('dddd D MMMM YYYY') }}</li>
        <li>Gemeente: {{ $spreekbeurt->dienst->gemeente->naam ?? 'Onbekend' }}</li>
        <li>Type: {{ $spreekbeurt->dienst->type }}</li>
    </ul>
    <p style="margin:0;">
        <a href="{{ $branding['frontend_url'] ?? config('app.frontend_url') }}/login" style="display:inline-block;background:{{ $branding['primary_color'] ?? '#2563EB' }};color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">Bevestig of wijs af</a>
    </p>
@endsection

