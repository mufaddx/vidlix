@extends('layouts.public')
@section('title', __('Not allowed'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('403') }}</p>
    <h1>{{ __('You do not have access to this') }}</h1>
    <p class="lede">{{ __('Your account is signed in, but it does not hold the permission this page needs. If you think it should, the person who manages your account can grant it.') }}</p>
    <p>
        <a class="btn" href="{{ route('dashboard') }}">{{ __('Back to your dashboard') }}</a>
        <a class="btn secondary" href="{{ route('app.tickets') }}">{{ __('Ask support') }}</a>
    </p>
</div>
@endsection
