@extends('layouts.auth')
@section('title', __('Reset your password'))

@section('content')
<div data-flow="forgot"
     data-step="{{ $step }}"
     data-cooldown="{{ $resendCooldown }}"
     data-verify-url="{{ route('password.verify') }}"
     data-resend-url="{{ route('password.resend') }}">

    <div class="steps" aria-hidden="true">
        <span data-step-dot="1" class="is-done"></span>
        <span data-step-dot="2"></span>
        <span data-step-dot="3"></span>
    </div>

    <div class="notice" data-notice hidden></div>

    @if($errors->any())
        <div class="notice bad">{{ $errors->first() }}</div>
    @endif

    {{-- Step 1 — where to send it ----------------------------------------- --}}
    <section class="step" data-step="1">
        <h1 class="auth-title">{{ __('Reset your password') }}</h1>
        <p class="auth-sub">{{ __('We will send a 6-digit code to the email on your account.') }}</p>

        <form action="{{ route('password.start') }}" method="post" novalidate>
            @csrf
            <div class="field">
                <label for="login">{{ __('Email address or mobile number') }}</label>
                <input id="login" name="login" type="text" required autofocus autocomplete="username"
                       placeholder="{{ __('Enter your email address') }}">
            </div>

            <button class="btn" type="submit">
                <span class="spinner" aria-hidden="true"></span>
                {{ __('Send OTP') }}
            </button>
        </form>

        <p class="auth-foot">{{ __('Remembered it?') }} <a href="{{ route('login') }}">{{ __('Back to log in') }}</a></p>
    </section>

    {{-- Step 2 — the code ------------------------------------------------- --}}
    <section class="step" data-step="2" hidden>
        <h1 class="auth-title">{{ __('Check your email') }}</h1>
        @include('auth.partials.orbit', ['target' => $pending['masked'] ?? ''])
        <p class="auth-foot"><a href="{{ route('password.request') }}">{{ __('Use a different account') }}</a></p>
    </section>

    {{-- Step 3 — the new password ----------------------------------------- --}}
    <section class="step" data-step="3" hidden>
        <h1 class="auth-title">{{ __('Choose a new password') }}</h1>
        <p class="auth-sub">{{ __('At least 10 characters, with upper and lower case and a number. Signing in elsewhere will end.') }}</p>

        <form action="{{ route('password.complete') }}" method="post" data-loading>
            @csrf
            <div class="field field-password">
                <label for="password">{{ __('New password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="new-password">
                <button type="button" class="reveal" data-reveal="password" aria-pressed="false" aria-label="{{ __('Show password') }}">
                    @include('auth.partials.eye')
                </button>
            </div>

            <div class="field field-password">
                <label for="password_confirmation">{{ __('Confirm new password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                <button type="button" class="reveal" data-reveal="password_confirmation" aria-pressed="false" aria-label="{{ __('Show password') }}">
                    @include('auth.partials.eye')
                </button>
            </div>

            <button class="btn" type="submit">
                <span class="spinner" aria-hidden="true"></span>
                {{ __('Update password') }}
            </button>
        </form>
    </section>
</div>
@endsection
