Beste {{ $voornaam }},

Uw verificatiecode voor het preekrooster is:

{{ $code }}

Deze code is 10 minuten geldig en kan maar één keer gebruikt worden.

Als u niet hebt geprobeerd in te loggen, kunt u deze e-mail negeren.

Met vriendelijke groet,
{{ $branding['from_name'] ?? 'Preekrooster Adventist Nederland' }}
@include('mail.partials.signature_text')
