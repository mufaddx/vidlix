@extends('layouts.auth')
@section('title', __('Verify email'))
@section('content')
<h1>{{ __('Verify your email') }}</h1>
<p class="muted">{{ __('Sensitive actions stay locked until you verify. Locally, the link is written to storage/logs if MAIL_MAILER=log.') }}</p>
<form method="post" action="{{ route('verification.send') }}">
    @csrf
    <button class="btn" type="submit">{{ __('Resend verification') }}</button>
</form>
@endsection
