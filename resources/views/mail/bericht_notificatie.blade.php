{{ $bericht->titel }}

{{ $bericht->inhoud }}

—
{{ $branding['app_name'] ?? config('app.name') }}
@include('mail.partials.signature_text')
