@extends('layouts.app')
@section('title', $conversation->subject ?: __('Conversation'))
@section('content')
<p><a href="{{ route('inbox') }}">{{ __('Back') }}</a></p>
<h1>{{ $conversation->subject }}</h1>
<p class="muted">{{ $conversation->externalContact?->email }} · {{ $conversation->conversation_uuid }}</p>
@foreach($conversation->messages as $message)
    <article class="card" style="margin-bottom:12px;">
        <p class="muted">{{ $message->direction }} · {{ $message->created_at }} · {{ $message->delivery_status }}</p>
        <p>{{ $message->body }}</p>
    </article>
@endforeach
<form class="form" method="post" action="{{ route('inbox.reply', $conversation->conversation_uuid) }}">
    @csrf
    <label>{{ __('Reply') }}<textarea name="body" required></textarea></label>
    <button class="btn" type="submit">{{ __('Store reply') }}</button>
</form>
@endsection
