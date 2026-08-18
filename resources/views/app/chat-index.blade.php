@extends('layouts.app')
@section('title', __('Chat'))
@section('content')
<h1>{{ __('Internal chat') }}</h1>
<form class="form" method="post" action="{{ route('app.chat.start') }}">
    @csrf
    <label>{{ __('User id') }}<input name="user_id" required></label>
    <label>{{ __('Subject') }}<input name="subject" required></label>
    <button class="btn" type="submit">{{ __('Start') }}</button>
</form>
@forelse($conversations as $c)
    <p><a href="{{ route('app.chat.show', $c->conversation_uuid) }}">{{ $c->subject }}</a></p>
@empty
    <p class="muted">{{ __('No messages yet') }}</p>
@endforelse
@endsection
