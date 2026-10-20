Preekbeurt geannuleerd

{{ $inhoud }}

Gemeente: {{ $spreekbeurt->dienst?->gemeente?->naam ?? '—' }}
Datum: {{ \Carbon\Carbon::parse($spreekbeurt->dienst?->datum)->isoFormat('dddd D MMMM YYYY') }}
Dienst: {{ $spreekbeurt->dienst?->type ?? '—' }}

Reden van annulering:
{{ $notitie }}

De dienst staat weer open voor een andere spreker.
@include('mail.partials.signature_text')
