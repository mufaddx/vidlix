@extends('layouts.app')
@section('title', __('Settings'))
@section('content')
<h1>{{ __('Sessions') }}</h1>
@foreach($sessions as $s)
    <form method="post" action="{{ route('app.sessions.revoke', $s->id) }}">@csrf {{ $s->ip_address }} {{ $s->user_agent }} <button type="submit">{{ __('Revoke') }}</button></form>
@endforeach
<p class="muted">{{ __('High-risk payout/Instagram disconnect remains creator-only.') }}</p>

<h2>{{ __('Your data') }}</h2>
<p><a href="{{ route('app.privacy') }}">{{ __('Download a copy of your data, or close your account') }}</a></p>
@endsection
