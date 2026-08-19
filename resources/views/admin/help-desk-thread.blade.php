@extends('layouts.app')
@section('title', $thread->conversation?->subject)
@section('content')
<p class="kicker">{{ $thread->reference }} · {{ $thread->status }}</p>
<h1>{{ $thread->conversation?->subject }}</h1>
<p class="muted">
    {{ $thread->user?->name ?? $thread->conversation?->externalContact?->name }}
    ({{ $thread->user?->email ?? $thread->conversation?->externalContact?->email }})
    @if($thread->user) · {{ __('has a Vidlix account') }} @else · {{ __('not a member') }} @endif
</p>

@foreach($messages as $message)
    <div class="card">
        <p class="kicker">{{ $message->direction }} · {{ $message->delivery_status }}</p>
        <p>{{ $message->body }}</p>
        <p class="muted">{{ $message->created_at }}</p>
    </div>
@endforeach

@if($canReply && $thread->status !== 'closed')
    <h2>{{ __('Reply') }}</h2>
    <form class="form" method="post" action="{{ route('admin.help-desk.reply', $thread) }}">@csrf
        <textarea name="body" required maxlength="8000"></textarea>
        <button class="btn" type="submit">{{ __('Send reply') }}</button>
    </form>
    <p class="muted">{{ __('It is queued and reported as sent only when the email provider confirms it.') }}</p>

    <form method="post" action="{{ route('admin.help-desk.close', $thread) }}">@csrf
        <button type="submit">{{ __('Close thread') }}</button>
    </form>
@elseif(! $canReply)
    <p class="muted">{{ __('You can read this thread but not reply. That needs the "reply to people from the help desk" ability.') }}</p>
@endif
@endsection
