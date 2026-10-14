Statusupdate preekbeurt

{{ $inhoud }}

Predikant: {{ $spreekbeurt->spreker?->volledigeNaam() ?? '-' }}
Gemeente: {{ $spreekbeurt->dienst?->gemeente?->naam ?? '-' }}
Datum: {{ optional($spreekbeurt->dienst)->datum }}
Status: {{ $spreekbeurt->bevestigd === 1 ? 'Bevestigd' : 'Afgewezen' }}
@include('mail.partials.signature_text')

