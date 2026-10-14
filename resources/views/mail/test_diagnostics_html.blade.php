@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Testmail gelukt</h1>
    <p style="margin:0 0 10px;">Deze testmail bevestigt dat mailverzending actief is.</p>
    <p style="margin:0;">Omgeving: <strong>{{ $appEnv }}</strong><br>Tijdstip: <strong>{{ $timestamp }}</strong></p>
@endsection

