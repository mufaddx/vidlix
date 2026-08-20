@extends('layouts.app')
@section('title', __('Your data'))
@section('content')
<h1>{{ __('Your data') }}</h1>

@if(session('status'))
    <div class="banner">{{ session('status') }}</div>
@endif

<h2>{{ __('Download a copy') }}</h2>
<p class="muted">{{ __('A JSON file with your account, profiles, messages you sent, and your ledger entries. Uploaded files stay in storage; the file lists their keys.') }}</p>
<p><a class="btn secondary" href="{{ route('app.privacy.export') }}">{{ __('Download my data') }}</a></p>

<h2>{{ __('Close my account') }}</h2>
<p class="muted">
    {{ __('This cannot be undone. Your profiles, sessions and API tokens are deleted and your name and email are removed from the account.') }}
</p>
<p class="muted">
    {{-- Said plainly rather than buried: promising an erasure that will not
         happen would be worse than explaining the one that does. --}}
    {{ __('Money records are kept. The ledger is append-only and financial records must be retained, so those entries stay with your identity stripped out of them.') }}
</p>

<form class="form" method="post" action="{{ route('app.privacy.destroy') }}">
    @csrf
    @method('DELETE')

    <label for="privacy-password">{{ __('Your password') }}</label>
    <div class="field-password">
        <input id="privacy-password" type="password" name="password" required autocomplete="current-password">
        @include('partials.reveal', ['for' => 'privacy-password'])
    </div>
    @error('password')<p class="error">{{ $message }}</p>@enderror

    <label for="privacy-reason">{{ __('Why are you leaving? (optional)') }}</label>
    <textarea id="privacy-reason" name="reason" rows="3" maxlength="500"></textarea>

    <label for="privacy-confirm">{{ __('Type DELETE to confirm') }}</label>
    <input id="privacy-confirm" type="text" name="confirm" required autocomplete="off">
    @error('confirm')<p class="error">{{ $message }}</p>@enderror

    <button class="btn" type="submit">{{ __('Close my account permanently') }}</button>
</form>
@endsection
