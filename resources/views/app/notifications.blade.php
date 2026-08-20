@extends('layouts.app')
@section('title', __('Notifications'))
@section('content')
<h1>{{ __('Notifications') }}</h1>

@if(session('status'))
    <div class="flash">{{ session('status') }}</div>
@endif

@if(! $pushConfigured)
    <div class="banner">
        {{-- Said plainly rather than leaving a dead toggle that looks live. --}}
        {{ __('Push is not configured on this server, so nothing is delivered to devices. Everything still appears on this page.') }}
    </div>
@endif

@if($items->isEmpty())
    <p class="muted">{{ __('Nothing yet.') }}</p>
@else
    <form method="post" action="{{ route('app.notifications.read') }}" style="margin-bottom:12px">@csrf
        <button class="btn secondary" type="submit">{{ __('Mark all as read') }}</button>
    </form>

    <table class="table">
        <tbody>
        @foreach($items as $n)
            <tr class="{{ $n->read_at === null ? 'is-unread' : '' }}">
                <td>
                    <strong>{{ $n->data['title'] ?? $n->type }}</strong>
                    @if(! empty($n->data['body']))<br><span class="muted">{{ $n->data['body'] }}</span>@endif
                </td>
                <td class="muted" style="white-space:nowrap">{{ $n->created_at?->diffForHumans() }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>{{ __('What you want to hear about') }}</h2>
<form class="form" method="post" action="{{ route('app.notifications.preferences') }}">@csrf
    <table class="table">
        <thead><tr>
            <th>{{ __('Event') }}</th>
            <th style="width:14%">{{ __('Push') }}</th>
            <th style="width:14%">{{ __('Email') }}</th>
        </tr></thead>
        <tbody>
        @foreach($preferences as $event => $row)
            <tr>
                <td>{{ __($row['label']) }}</td>
                <td><label><input type="checkbox" name="events[{{ $event }}][push]" value="1" @checked($row['push'])></label></td>
                <td><label><input type="checkbox" name="events[{{ $event }}][email]" value="1" @checked($row['email'])></label></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <button class="btn" type="submit">{{ __('Save preferences') }}</button>
</form>
@endsection
