@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Preekbeurt geannuleerd</h1>
    <p style="margin:0 0 16px;">{!! nl2br(e($inhoud)) !!}</p>
    <ul style="margin:0 0 16px;padding-left:18px;">
        <li>Gemeente: {{ $spreekbeurt->dienst?->gemeente?->naam ?? '—' }}</li>
        <li>Datum: {{ \Carbon\Carbon::parse($spreekbeurt->dienst?->datum)->isoFormat('dddd D MMMM YYYY') }}</li>
        <li>Dienst: {{ $spreekbeurt->dienst?->type ?? '—' }}</li>
    </ul>
    <p style="margin:0 0 8px;font-weight:600;color:#0f172a;">Reden van annulering</p>
    <p style="margin:0 0 16px;white-space:pre-wrap;">{{ $notitie }}</p>
    <p style="margin:0;color:#64748b;font-size:14px;">De dienst staat weer open voor een andere spreker.</p>
@endsection
