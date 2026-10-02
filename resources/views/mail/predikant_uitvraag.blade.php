{{ $inhoud }}

- Datum: {{ \Carbon\Carbon::parse($spreekbeurt->dienst->datum)->isoFormat('dddd D MMMM YYYY') }}
- Gemeente: {{ $spreekbeurt->dienst->gemeente->naam ?? 'Onbekend' }}
- Type dienst: {{ $spreekbeurt->dienst->type }}

U kunt deze uitvraag accepteren of weigeren via:
{{ $branding['frontend_url'] ?? config('app.frontend_url') }}/login

Met vriendelijke groet,
{{ $branding['from_name'] ?? 'Preekrooster Adventist Nederland' }}
@include('mail.partials.signature_text')
