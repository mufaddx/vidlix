@extends('layouts.public')
@section('title', __('Back shortly'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('503') }}</p>
    <h1>{{ __('We are back shortly') }}</h1>
    <p class="lede">{{ __('Vidlix is briefly down for maintenance. Nothing has been lost — your work, messages and payments are exactly where you left them.') }}</p>
</div>
@endsection
