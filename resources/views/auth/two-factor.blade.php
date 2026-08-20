@extends('layouts.app')
@section('title', __('Two-factor authentication'))
@section('content')
<h1>{{ __('Two-factor authentication') }}</h1>

@if(session('status'))
    <div class="flash">{{ session('status') }}</div>
@endif

@if($codes)
    <div class="banner">
        <strong>{{ __('Your recovery codes') }}</strong>
        <p class="muted">{{ __('Each one works once, if you lose your phone. They are shown now and never again — save them somewhere safe.') }}</p>
        <pre style="margin:0;font-size:0.95rem;line-height:1.9">{{ implode(PHP_EOL, $codes) }}</pre>
    </div>
@endif

@if($enabled)
    <p>{{ __('Two-factor authentication is on.') }}
       <span class="muted">{{ trans_choice(':count unused recovery code left|:count unused recovery codes left', $remaining, ['count' => $remaining]) }}</span></p>

    <form class="form" method="post" action="{{ route('app.two-factor.recovery') }}">@csrf
        <button class="btn secondary" type="submit">{{ __('Generate new recovery codes') }}</button>
    </form>

    <h2>{{ __('Turn it off') }}</h2>
    <form class="form" method="post" action="{{ route('app.two-factor.disable') }}">
        @csrf @method('DELETE')
        <label for="tf-password">{{ __('Confirm your password') }}</label>
        <input id="tf-password" type="password" name="password" required autocomplete="current-password">
        @error('password')<p class="error">{{ $message }}</p>@enderror
        <button class="btn secondary" type="submit">{{ __('Turn off two-factor') }}</button>
    </form>
@elseif($secret)
    <h2>{{ __('Step 2 — confirm') }}</h2>
    <p class="muted">{{ __('Add this key to your authenticator app, then enter the six-digit code it shows.') }}</p>
    <p><code style="font-size:1.1rem;letter-spacing:0.1em">{{ trim(chunk_split($secret, 4, ' ')) }}</code></p>
    <p class="muted" style="font-size:0.85rem;word-break:break-all">{{ $otpauth }}</p>

    <form class="form" method="post" action="{{ route('app.two-factor.confirm') }}">@csrf
        <label for="tf-code">{{ __('Six-digit code') }}</label>
        <input id="tf-code" name="code" inputmode="numeric" autocomplete="one-time-code" required>
        @error('code')<p class="error">{{ $message }}</p>@enderror
        {{-- Nothing is in force until this succeeds: a half-finished set-up
             must never be able to lock somebody out of their own account. --}}
        <p class="muted">{{ __('Two-factor is not on until this code is accepted.') }}</p>
        <button class="btn" type="submit">{{ __('Turn on two-factor') }}</button>
    </form>
@else
    <p class="muted">{{ __('Ask for a code from an authenticator app every time you sign in. It protects the account even if your password leaks.') }}</p>
    <form class="form" method="post" action="{{ route('app.two-factor.begin') }}">@csrf
        <button class="btn" type="submit">{{ __('Set up two-factor') }}</button>
    </form>
@endif
@endsection
