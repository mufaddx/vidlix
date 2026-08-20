@extends('layouts.app')
@section('title', $conversation->subject ?: __('Conversation'))
@section('content')

<p><a href="{{ route('inbox') }}">{{ __('Back to inbox') }}</a></p>

<h1>{{ $conversation->subject }}</h1>

<p class="muted">
    @if($isExternal)
        <span class="chip">{{ __('Email') }}</span>
        {{ $conversation->externalContact?->name }}
        &lt;{{ $conversation->externalContact?->email }}&gt;
    @else
        <span class="chip">{{ __('Internal') }}</span>
        @foreach($conversation->participants->whereNotNull('user') as $participant)
            {{ $participant->user->name }}@if(!$loop->last), @endif
        @endforeach
    @endif
    @if($isArchived)<span class="chip">{{ __('Archived') }}</span>@endif
    @if($isMuted)<span class="chip">{{ __('Muted') }}</span>@endif
</p>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

{{-- Thread controls. Each is its own form so none of them can be submitted
     accidentally by the reply box. --}}
<div class="card" style="margin-bottom:16px">
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <form method="post" action="{{ route('inbox.archive', $conversation->conversation_uuid) }}">
            @csrf
            <input type="hidden" name="archived" value="{{ $isArchived ? 0 : 1 }}">
            <button class="btn secondary" type="submit">
                {{ $isArchived ? __('Move back to inbox') : __('Archive') }}
            </button>
        </form>

        <form method="post" action="{{ route('inbox.mute', $conversation->conversation_uuid) }}">
            @csrf
            <input type="hidden" name="muted" value="{{ $isMuted ? 0 : 1 }}">
            <button class="btn secondary" type="submit">
                {{ $isMuted ? __('Unmute') : __('Mute notifications') }}
            </button>
        </form>

        @if($blockable)
            <form method="post" action="{{ route('inbox.block', $conversation->conversation_uuid) }}"
                  onsubmit="return confirm('{{ __('Block :name? They will not be able to start a new thread with you.', ['name' => $blockable->name]) }}')">
                @csrf
                <button class="btn secondary" type="submit">{{ __('Block :name', ['name' => $blockable->name]) }}</button>
            </form>
        @endif
    </div>

    @if($reported)
        <p class="muted" style="margin-top:12px">{{ __('You have reported this thread. Someone is looking at it.') }}</p>
    @else
        <details style="margin-top:12px">
            <summary>{{ __('Report this conversation') }}</summary>
            <form class="form" method="post" action="{{ route('inbox.report', $conversation->conversation_uuid) }}">
                @csrf
                <label for="reason">{{ __('What is wrong?') }}</label>
                <select id="reason" name="reason" required>
                    @foreach($reasons as $reason)
                        <option value="{{ $reason }}">{{ __(ucfirst($reason)) }}</option>
                    @endforeach
                </select>

                <label for="detail">{{ __('Anything else we should know?') }} <span class="muted">{{ __('(optional)') }}</span></label>
                <textarea id="detail" name="detail" maxlength="2000"></textarea>

                <button class="btn secondary" type="submit">{{ __('Send report') }}</button>
            </form>
        </details>
    @endif
</div>

@forelse($conversation->messages as $message)
    <article class="card" style="margin-bottom:12px;">
        <p class="muted">
            {{ $message->actor?->name ?? $conversation->externalContact?->name ?? __('Them') }}
            · {{ $message->created_at?->diffForHumans() }}
            @if($isExternal)
                {{-- The provider's word, not ours: nothing here says "sent"
                     because a form was submitted. --}}
                · <span class="chip">{{ $message->delivery_status }}</span>
            @endif
        </p>
        <p style="white-space:pre-wrap">{{ $message->body }}</p>
    </article>
@empty
    <p class="muted">{{ __('Nothing in this thread yet.') }}</p>
@endforelse

<form class="form" method="post" action="{{ route('inbox.reply', $conversation->conversation_uuid) }}">
    @csrf
    <label for="body">{{ __('Reply') }}</label>
    <textarea id="body" name="body" required maxlength="8000"></textarea>

    <button class="btn" type="submit">{{ __('Send reply') }}</button>

    @if($isExternal)
        <p class="muted">{{ __('This leaves Vidlix as email, from your role address, and their reply comes back to this same thread.') }}</p>
    @endif
</form>
@endsection
