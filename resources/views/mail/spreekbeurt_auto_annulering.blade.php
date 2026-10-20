Preekbeurt automatisch geannuleerd

{{ $inhoud }}

Gemeente: {{ $spreekbeurt->dienst?->gemeente?->naam ?? '—' }}
Datum: {{ $spreekbeurt->dienst?->datum?->format('d-m-Y') ?? '—' }}
Dienst: {{ $spreekbeurt->dienst?->type ?? '—' }}
Spreker: {{ trim(implode(' ', array_filter([$spreekbeurt->spreker?->voornaam, $spreekbeurt->spreker?->tussenvoegsel, $spreekbeurt->spreker?->achternaam]))) ?: '—' }}

Log in om het rooster te bekijken: {{ config('app.url') }}/login
@include('mail.partials.signature_text')
