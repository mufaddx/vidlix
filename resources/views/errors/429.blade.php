@extends('layouts.public')
@section('title', __('Too many attempts'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('429') }}</p>
    <h1>{{ __('Slow down for a moment') }}</h1>
    {{-- Says what to do rather than only what went wrong. Most people meeting
         this are not attackers, they are somebody whose code did not arrive. --}}
    <p class="lede">{{ __('That was a lot of attempts in a short time, so we have paused them briefly. Wait a minute and try again.') }}</p>
    <p class="muted">{{ __('If you were waiting for a code by email, check your spam folder before asking for another one.') }}</p>
    <p><a class="btn" href="{{ route('home') }}">{{ __('Go to the home page') }}</a></p>
</div>
@endsection
