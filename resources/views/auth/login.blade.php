@extends('layouts.auth')
@section('title', __('Log in'))

@section('content')
<h1 class="auth-title">{{ __('Welcome back') }}</h1>
<p class="auth-sub">{{ __('Sign in with your email or mobile number.') }}</p>

@if($errors->any())
    <div class="notice bad">{{ $errors->first() }}</div>
@endif
@if(session('status'))
    <div class="notice ok">{{ session('status') }}</div>
@endif

<form action="{{ route('login') }}" method="post" data-loading>
    @csrf

    <div class="field">
        <label for="login">{{ __('Email address or mobile number') }}</label>
        <input id="login" name="login" type="text" required autofocus autocomplete="username"
               value="{{ old('login') }}" placeholder="{{ __('Email or mobile number') }}">
    </div>

    <div class="field field-password">
        <label for="password">{{ __('Password') }}</label>
        <input id="password" name="password" type="password" required autocomplete="current-password">
        <button type="button" class="reveal" data-reveal="password" aria-pressed="false" aria-label="{{ __('Show password') }}">
            @include('auth.partials.eye')
        </button>
    </div>

    <div class="auth-alt">
        <label class="check" style="margin:0">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span>{{ __('Remember me') }}</span>
        </label>
        <a href="{{ route('password.request') }}">{{ __('Forgot password?') }}</a>
    </div>

    <button class="btn" type="submit">
        <span class="spinner" aria-hidden="true"></span>
        {{ __('Log in') }}
    </button>
</form>

<p class="auth-foot">{{ __('New to Vidlix?') }} <a href="{{ route('register') }}">{{ __('Create an account') }}</a></p>
@endsection
