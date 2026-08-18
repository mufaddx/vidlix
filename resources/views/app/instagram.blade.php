@extends('layouts.app')
@section('title', __('Instagram'))
@section('content')
<h1>{{ __('Instagram') }}</h1>

@if(! $configured)
    <p class="muted">{{ __('Instagram is unavailable: the Meta app credentials are not configured. Nothing is connected and no numbers are shown.') }}</p>
@else
    <p>{{ __('Connection') }}: {{ $account?->status ?? __('not connected') }}
        @if($account?->username) · &#64;{{ $account->username }} @endif
    </p>

    @if($account?->last_synced_at)
        <p class="muted">{{ __('Last synced from the Meta Graph API at') }} {{ $account->last_synced_at }}</p>
    @else
        <p class="muted">{{ __('Never synced. Insights stay empty until an authorised Graph API read succeeds.') }}</p>
    @endif

    <form method="post" action="{{ route('app.instagram.connect') }}">@csrf
        <button class="btn" type="submit">{{ $account?->ig_user_id ? __('Reconnect Instagram') : __('Connect Instagram') }}</button>
    </form>

    @if($account?->ig_user_id)
        <form method="post" action="{{ route('app.instagram.sync') }}">@csrf
            <button type="submit">{{ __('Sync now') }}</button>
        </form>
    @endif
@endif

<h2>{{ __('Insights') }}</h2>
@if(empty($insights))
    <p>{{ __('None. Vidlix shows only what the official Meta Graph API returned; nothing is estimated or invented.') }}</p>
@else
    <table class="table">
        @foreach($insights as $key => $value)
            <tr><td>{{ $key }}</td><td>{{ is_scalar($value) ? $value : json_encode($value) }}</td></tr>
        @endforeach
    </table>
@endif
@endsection
