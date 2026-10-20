<p><strong>{{ $bericht->titel }}</strong></p>
<p>{!! nl2br(e($bericht->inhoud)) !!}</p>
<p style="color:#666;font-size:12px;">{{ $branding['app_name'] ?? config('app.name') }}</p>
@if(($mailSignature ?? '') !== '')
<p style="color:#475569;font-size:13px;">{!! nl2br(e($mailSignature)) !!}</p>
@endif
