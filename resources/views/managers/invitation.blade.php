@extends('layouts.auth')
@section('title', __('Manager invitation'))
@section('content')

@if($invitation->isCompanyProvided())
    <p class="kicker">{{ __('Provided by Vidlix') }}</p>
    <h1>{{ __('Vidlix has arranged for you to manage this account') }}</h1>
@else
    <p class="kicker">{{ __('Invitation') }}</p>
    <h1>{{ __(':owner asked you to manage their :scope account', ['owner' => $owner?->name ?? __('An account holder'), 'scope' => $invitation->scope]) }}</h1>
@endif

<p class="muted">{{ __('Invitation for') }} <strong>{{ $invitation->email }}</strong></p>

@if($signedInAsInvitee)
    <p>{{ __('You are signed in as this person, so you can accept straight away.') }}</p>
    <form method="post" action="{{ route('managers.invitation.activate', $token) }}">@csrf
        <button class="btn" type="submit">{{ __('Accept and start managing') }}</button>
    </form>
@elseif(! $needsAccount)
    <p>{{ __('An account already exists for this email. Sign in as that account to accept — this link cannot change an existing password.') }}</p>
    <a class="btn" href="{{ route('login') }}">{{ __('Sign in to accept') }}</a>
@else
    <p>{{ __('Set a password to create your Vidlix account. You will then be able to switch between your own account and the accounts you manage.') }}</p>
    <form class="form" method="post" action="{{ route('managers.invitation.activate', $token) }}">@csrf
        <label for="name">{{ __('Your name') }}</label>
        <input id="name" name="name" value="{{ old('name', $invitation->name) }}" required autocomplete="name">

        <label for="mobile">{{ __('Mobile number') }}</label>
        <input id="mobile" name="mobile" value="{{ old('mobile', $invitation->mobile) }}" autocomplete="tel">

        <label for="password">{{ __('Choose a password') }}</label>
        <input id="password" name="password" type="password" required autocomplete="new-password">

        <label for="password_confirmation">{{ __('Confirm password') }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">

        <button class="btn" type="submit">{{ __('Create account and accept') }}</button>
    </form>
    <p class="muted">{{ __('At least 10 characters, with upper and lower case and a number.') }}</p>
@endif
@endsection
