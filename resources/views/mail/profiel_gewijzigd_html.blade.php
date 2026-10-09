@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Uw profiel is gewijzigd</h1>
    <p style="margin:0 0 16px;">{!! nl2br(e($inhoud)) !!}</p>
    <p style="margin:0 0 10px;">Gewijzigde velden:</p>
    <ul style="margin:0 0 16px;padding-left:18px;">
        @foreach($velden as $veld)
            <li>{{ $veld }}</li>
        @endforeach
    </ul>
    <p style="margin:0;">Heeft u dit niet zelf gedaan? Neem dan direct contact op met een beheerder.</p>
@endsection

