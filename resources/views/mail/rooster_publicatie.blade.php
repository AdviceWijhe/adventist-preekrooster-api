@php $maand = \Carbon\Carbon::parse($periode . '-01')->isoFormat('MMMM YYYY'); @endphp

{{ $inhoud }}

@if ($gemeente)
Rooster voor {{ $gemeente->naam }}:
@else
Volledig rooster:
@endif

@foreach ($diensten as $dienst)
{{ \Carbon\Carbon::parse($dienst->datum)->isoFormat('dddd D MMMM') }} | {{ ucfirst($dienst->type) }}
  Gemeente: {{ $dienst->gemeente?->naam ?? '-' }}
@if ($dienst->spreekbeurten->isNotEmpty())
  Spreker: {{ $dienst->spreekbeurten->first()->spreker?->volledigeNaam() ?? '(Onbekend)' }}
  Status: {{ $dienst->spreekbeurten->first()->bevestigd === 1 ? 'Bevestigd' : 'Nog niet bevestigd' }}
@else
  Spreker: (Nog niet ingevuld)
@endif

@endforeach

Bekijk het volledige rooster op: {{ $branding['frontend_url'] ?? config('app.frontend_url') }}

Met vriendelijke groet,
{{ $branding['from_name'] ?? 'Preekrooster Adventist Nederland' }}
@include('mail.partials.signature_text')
