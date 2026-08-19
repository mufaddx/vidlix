@extends('layouts.app')
@section('title', $conversation->subject ?: __('Conversation'))
@section('content')
<h1>{{ $conversation->subject }}</h1>
@foreach($conversation->messages as $m)
    <article class="card" style="margin-bottom:8px;"><p class="muted">{{ $m->actor_user_id }} · {{ $m->created_at }}</p><p>{{ $m->body }}</p></article>
@endforeach
<form method="post" action="{{ route('app.chat.reply', $conversation->conversation_uuid) }}">@csrf<textarea name="body" required></textarea><button class="btn" type="submit">{{ __('Send') }}</button></form>
@endsection
