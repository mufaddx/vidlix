@extends('layouts.public')
@section('title', __('Not found'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('404') }}</p>
    <h1>{{ __('There is nothing here') }}</h1>
    {{-- Deliberately says nothing about whether the thing exists. A page that
         distinguishes "never existed" from "you may not see it" hands out the
         second fact for free. --}}
    <p class="lede">{{ __('The page you asked for either does not exist or is not yours to see. If you followed a link, it may be out of date.') }}</p>
    <p>
        <a class="btn" href="{{ route('home') }}">{{ __('Go to the home page') }}</a>
        <a class="btn secondary" href="{{ route('login') }}">{{ __('Sign in') }}</a>
    </p>
</div>
@endsection
