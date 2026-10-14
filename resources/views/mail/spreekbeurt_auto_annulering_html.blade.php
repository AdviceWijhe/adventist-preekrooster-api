@php
    $sprekerNaam = trim(implode(' ', array_filter([
        $spreekbeurt->spreker?->voornaam,
        $spreekbeurt->spreker?->tussenvoegsel,
        $spreekbeurt->spreker?->achternaam,
    ]))) ?: '—';
@endphp
<p>{!! nl2br(e($inhoud)) !!}</p>
<ul>
    <li><strong>Gemeente:</strong> {{ $spreekbeurt->dienst?->gemeente?->naam ?? '—' }}</li>
    <li><strong>Datum:</strong> {{ $spreekbeurt->dienst?->datum?->format('d-m-Y') ?? '—' }}</li>
    <li><strong>Dienst:</strong> {{ $spreekbeurt->dienst?->type ?? '—' }}</li>
    <li><strong>Spreker:</strong> {{ $sprekerNaam }}</li>
</ul>
<p><a href="{{ config('app.url') }}/login">Log in om het rooster te bekijken</a></p>
@if(($mailSignature ?? '') !== '')
<p style="color:#475569;font-size:13px;">{!! nl2br(e($mailSignature)) !!}</p>
@endif
