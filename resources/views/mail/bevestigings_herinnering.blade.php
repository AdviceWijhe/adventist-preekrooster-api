{{ $inhoud }}

Uw openstaande beurten:

@foreach ($beurten as $beurt)
- {{ \Carbon\Carbon::parse($beurt->dienst->datum)->isoFormat('dddd D MMMM YYYY') }}
  Gemeente: {{ $beurt->dienst->gemeente->naam }}
  Dienst: {{ $beurt->dienst->type }}
@endforeach

Log in om te bevestigen: {{ $branding['frontend_url'] ?? config('app.frontend_url') }}/login

Met vriendelijke groet,
{{ $branding['from_name'] ?? 'Preekrooster Adventist Nederland' }}
@include('mail.partials.signature_text')
