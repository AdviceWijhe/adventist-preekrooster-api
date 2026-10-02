@extends('mail.layout')

@section('content')
    <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">{{ $onderwerp }}</h1>
    <p style="margin:0;">{!! nl2br(e($inhoud)) !!}</p>
@endsection
