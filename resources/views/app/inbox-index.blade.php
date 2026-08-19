@extends('layouts.app')
@section('title', __('Inbox'))
@section('content')
<h1>{{ __('Inbox') }}</h1>

<nav class="inbox-tabs" aria-label="{{ __('Filter conversations') }}">
    @foreach($filters as $key)
        <a class="inbox-tab {{ $filter === $key ? 'active' : '' }}"
           @if($filter === $key) aria-current="page" @endif
           href="{{ route('inbox', array_filter(['filter' => $key, 'q' => $search])) }}">
            {{ __(ucfirst($key)) }}
            <span class="inbox-tab-count">{{ $counts[$key] ?? 0 }}</span>
        </a>
    @endforeach
</nav>

<form class="inbox-search" method="get" action="{{ route('inbox') }}">
    <input type="hidden" name="filter" value="{{ $filter }}">
    <label class="sr-only" for="inbox-q">{{ __('Search conversations') }}</label>
    <input id="inbox-q" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Search subject or sender') }}">
    <button class="btn secondary" type="submit">{{ __('Search') }}</button>
</form>

@if($conversations->isEmpty())
    <p class="muted">
        @if($filter === 'all' && ! filled($search))
            {{ __('No messages yet.') }}
        @else
            {{ __('No conversations match this filter.') }}
        @endif
    </p>
@else
<table class="table">
    <thead><tr>
        <th>{{ __('Subject') }}</th>
        <th>{{ __('With') }}</th>
        <th>{{ __('Updated') }}</th>
    </tr></thead>
    <tbody>
    @foreach($conversations as $c)
        @php($new = $unread[$c->id] ?? 0)
        <tr class="{{ $new > 0 ? 'is-unread' : '' }}">
            <td>
                <a href="{{ route('inbox.show', $c->conversation_uuid) }}">{{ $c->subject ?: __('(no subject)') }}</a>
                @if($new > 0)
                    <span class="badge unread">{{ $new }} {{ __('new') }}</span>
                @endif
            </td>
            @php($others = $c->participants->where('user_id', '!=', auth()->id()))
            <td>
                @foreach($others->pluck('marketplace_role')->filter()->unique() as $role)
                    <span class="badge role">{{ __(ucfirst($role)) }}</span>
                @endforeach
                @if($others->pluck('marketplace_role')->filter()->isEmpty())
                    {{-- No role is recorded for someone who arrived through a public
                         form. Saying so is honest; guessing a role would not be. --}}
                    <span class="badge external">{{ __('External') }}</span>
                @endif
                {{ $c->externalContact?->email ?: $others->pluck('user.name')->filter()->join(', ') }}
            </td>
            <td>{{ $c->last_message_at }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
{{ $conversations->links() }}
@endif
@endsection
