@extends('layouts.auth')
@section('title', __('Log in'))
@section('content')
<h1>{{ __('Log in') }}</h1>
<p class="muted">{{ __('Email or mobile + password') }}</p>
<form class="form" method="post" action="{{ route('login') }}">
    @csrf
    <label>{{ __('Email or mobile') }} <input name="login" value="{{ old('login') }}" required autocomplete="username"></label>
    <label>{{ __('Password') }} <input type="password" name="password" required autocomplete="current-password"></label>
    <label><input type="checkbox" name="remember" value="1"> {{ __('Remember this device') }}</label>
    <button class="btn" type="submit">{{ __('Log in') }}</button>
</form>
<p><a href="{{ route('register') }}">{{ __('Create an account') }}</a></p>
@endsection
