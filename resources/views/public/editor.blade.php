@extends('layouts.public')
@section('title', $editor->display_name)
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Verified editor') }}</p>
    <h1>{{ $editor->display_name }}</h1>
    <p class="lede">{{ '@'.$editor->username }} · {{ $editor->availability }}</p>
</div>
<div class="wrap section" style="padding-top:8px;">
    <div class="card" style="max-width:44rem;">
        <p>{{ $editor->bio }}</p>
        <p class="muted">{{ implode(' · ', $editor->software ?? []) }}</p>
        <p class="muted">{{ implode(' · ', $editor->specializations ?? []) }}</p>
        @auth
            <form method="post" action="{{ route('app.chat.start') }}" style="margin-top:16px;">
                @csrf
                <input type="hidden" name="user_id" value="{{ $editor->user_id }}">
                <input type="hidden" name="subject" value="Editor inquiry">
                <button class="btn" type="submit">{{ __('Message') }}</button>
            </form>
        @else
            <a class="btn secondary" href="{{ route('login') }}">{{ __('Log in to message') }}</a>
        @endauth
    </div>
</div>
@endsection
