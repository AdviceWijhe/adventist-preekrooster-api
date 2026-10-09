@extends('mail.layout')

@section('content')
    @php $maand = \Carbon\Carbon::parse($periode . '-01')->isoFormat('MMMM YYYY'); @endphp
    <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Rooster {{ $maand }}</h1>
    <p style="margin:0 0 16px;">{!! nl2br(e($inhoud)) !!}</p>
    <ul style="margin:0 0 16px;padding-left:18px;">
        @foreach ($diensten->take(8) as $dienst)
            <li style="margin-bottom:6px;">
                {{ \Carbon\Carbon::parse($dienst->datum)->isoFormat('dddd D MMMM') }} - {{ ucfirst($dienst->type) }}
            </li>
        @endforeach
    </ul>
    <p style="margin:0;">
        <a href="{{ $branding['frontend_url'] ?? config('app.frontend_url') }}" style="display:inline-block;background:{{ $branding['primary_color'] ?? '#2563EB' }};color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:8px;font-weight:600;">Bekijk rooster</a>
    </p>
@endsection

