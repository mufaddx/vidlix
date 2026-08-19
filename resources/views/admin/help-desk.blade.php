@extends('layouts.app')
@section('title', __('Help desk'))
@section('content')
<h1>{{ __('Help desk') }}</h1>
@if($address)
    <p class="muted">{{ __('Anyone can write to') }} <strong>{{ $address }}</strong>. {{ __('In-app help requests arrive here too, and your reply goes back to them by email on the same thread.') }}</p>
@else
    <p class="banner">{{ __('EMAIL_INBOUND_DOMAIN is not configured, so the help desk mailbox does not exist yet. In-app requests still arrive here.') }}</p>
@endif

<nav class="chips">
    @foreach(['open', 'pending', 'closed', 'all'] as $filter)
        <a class="chip {{ $status === $filter ? 'is-active' : '' }}" href="{{ route('admin.help-desk', ['status' => $filter]) }}">{{ ucfirst($filter) }}</a>
    @endforeach
</nav>

@forelse($threads as $thread)
    <div class="card">
        <a href="{{ route('admin.help-desk.show', $thread) }}"><strong>{{ $thread->conversation?->subject }}</strong></a>
        <p class="muted">
            {{ $thread->reference }} ·
            {{ $thread->user?->name ?? $thread->conversation?->externalContact?->name ?? __('Unknown') }}
            ({{ $thread->user?->email ?? $thread->conversation?->externalContact?->email }}) ·
            {{ $thread->status }}
            @if($thread->assignee) · {{ __('with') }} {{ $thread->assignee->name }} @endif
        </p>
    </div>
@empty
    <p class="muted">{{ __('Nothing here.') }}</p>
@endforelse

{{ $threads->links() }}
@endsection
