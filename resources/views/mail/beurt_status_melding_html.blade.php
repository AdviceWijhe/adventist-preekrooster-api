@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Preekbeurt bijgewerkt</h1>
    <p style="margin:0 0 16px;">{!! nl2br(e($inhoud)) !!}</p>
    <ul style="margin:0;padding-left:18px;">
        <li>Predikant: {{ $spreekbeurt->spreker?->volledigeNaam() ?? '-' }}</li>
        <li>Gemeente: {{ $spreekbeurt->dienst?->gemeente?->naam ?? '-' }}</li>
        <li>Datum: {{ \Carbon\Carbon::parse($spreekbeurt->dienst?->datum)->isoFormat('dddd D MMMM YYYY') }}</li>
        <li>Status: <strong>{{ $spreekbeurt->bevestigd === 1 ? 'Bevestigd' : 'Afgewezen' }}</strong></li>
    </ul>
@endsection

