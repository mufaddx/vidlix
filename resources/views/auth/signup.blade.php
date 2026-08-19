@extends('layouts.auth')
@section('title', __('Create your account'))

@section('content')
<div data-flow="signup"
     data-step="{{ $step }}"
     data-cooldown="{{ $resendCooldown }}"
     data-verify-url="{{ route('register.verify') }}"
     data-resend-url="{{ route('register.resend') }}">

    <div class="steps" aria-hidden="true">
        <span data-step-dot="1" class="is-done"></span>
        <span data-step-dot="2"></span>
        <span data-step-dot="3"></span>
    </div>

    <div class="notice" data-notice hidden></div>

    @if($errors->any())
        <div class="notice bad">{{ $errors->first() }}</div>
    @endif

    {{-- Step 1 — who you are, and what you do ---------------------------- --}}
    <section class="step" data-step="1">
        <h1 class="auth-title">{{ __('Create your account') }}</h1>
        <p class="auth-sub">{{ __('One account. Add another role whenever you need it.') }}</p>

        <form action="{{ route('register.start') }}" method="post" novalidate>
            @csrf
            <div class="roles" role="radiogroup" aria-label="{{ __('What do you do?') }}">
                @foreach($terms as $key => $role)
                    <label class="role">
                        <input type="radio" name="role" value="{{ $key }}" data-label="{{ $role['label'] }}"
                               @checked(($pending['role'] ?? old('role')) === $key)>
                        <span class="role-name">{{ $role['label'] }}</span>
                        <span class="role-hint">
                            @switch($key)
                                @case('creator') {{ __('I create') }} @break
                                @case('editor') {{ __('I edit') }} @break
                                @default {{ __('I hire') }}
                            @endswitch
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="field">
                <label for="name">{{ __('Full name') }}</label>
                <input id="name" name="name" type="text" required autocomplete="name"
                       value="{{ $pending['name'] ?? old('name') }}" placeholder="{{ __('Your name') }}">
            </div>

            <div class="field">
                <label for="mobile">{{ __('Mobile number') }}</label>
                <input id="mobile" name="mobile" type="tel" required autocomplete="tel"
                       value="{{ $pending['mobile'] ?? old('mobile') }}" placeholder="{{ __('10-digit number') }}">
            </div>

            <div class="field">
                <label for="email">{{ __('Email address') }}</label>
                <input id="email" name="email" type="email" required autocomplete="email"
                       value="{{ $pending['email'] ?? old('email') }}" placeholder="{{ __('you@example.com') }}">
            </div>

            <label class="check">
                <input type="checkbox" id="accepted_terms" name="accepted_terms" value="1">
                <span>{{ __('I have read and accept the') }}
                    <a href="#" data-terms-open>{{ __('Terms & Conditions') }}</a>
                    {{ __('for my role.') }}</span>
            </label>

            <button class="btn" type="submit">
                <span class="spinner" aria-hidden="true"></span>
                {{ __('Continue') }}
            </button>
        </form>

        <p class="auth-foot">{{ __('Already have an account?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a></p>
    </section>

    {{-- Step 2 — the emailed code ---------------------------------------- --}}
    <section class="step" data-step="2" hidden>
        <h1 class="auth-title">{{ __('Verify your email') }}</h1>
        @include('auth.partials.orbit', ['target' => $pending['email'] ?? ''])
        <p class="auth-foot">{{ __('Wrong address?') }} <a href="{{ route('register') }}?restart=1">{{ __('Start again') }}</a></p>
    </section>

    {{-- Step 3 — password ------------------------------------------------- --}}
    <section class="step" data-step="3" hidden>
        <h1 class="auth-title">{{ __('Set your password') }}</h1>
        <p class="auth-sub">{{ __('At least 10 characters, with upper and lower case and a number.') }}</p>

        <form action="{{ route('register.complete') }}" method="post" data-loading>
            @csrf
            <div class="field field-password">
                <label for="password">{{ __('Create password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="new-password">
                <button type="button" class="reveal" data-reveal="password" aria-pressed="false" aria-label="{{ __('Show password') }}">
                    @include('auth.partials.eye')
                </button>
            </div>

            <div class="field field-password">
                <label for="password_confirmation">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                <button type="button" class="reveal" data-reveal="password_confirmation" aria-pressed="false" aria-label="{{ __('Show password') }}">
                    @include('auth.partials.eye')
                </button>
            </div>

            <button class="btn" type="submit">
                <span class="spinner" aria-hidden="true"></span>
                {{ __('Complete registration') }}
            </button>
        </form>
    </section>
</div>
@endsection

@push('modals')
    @include('auth.partials.terms-modal', ['terms' => $terms])
@endpush
