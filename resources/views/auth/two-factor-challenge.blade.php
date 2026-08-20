@extends('layouts.auth')
@section('title', __('Two-factor code'))

@section('content')
<h1 class="auth-title">{{ __('Enter your code') }}</h1>
<p class="auth-sub">{{ __('Open your authenticator app and enter the six-digit code. A recovery code works too.') }}</p>

@if($errors->any())
    <div class="notice bad">{{ $errors->first() }}</div>
@endif

<form action="{{ route('two-factor.verify') }}" method="post" data-loading>
    @csrf

    <div class="field">
        <label for="code">{{ __('Code') }}</label>
        <input id="code" name="code" type="text" inputmode="text" autocomplete="one-time-code" autofocus required
               placeholder="{{ __('123456') }}">
    </div>

    <button class="btn" type="submit">
        <span class="spinner" aria-hidden="true"></span>
        {{ __('Continue') }}
    </button>
</form>

<p class="auth-foot"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
@endsection
