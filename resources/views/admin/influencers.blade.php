@extends('layouts.admin')
@section('title', __('All influencers'))
@section('subheading', __(':count creator accounts', ['count' => $creators->total()]))
@section('content')
<form class="a-form" method="get" style="margin-bottom:18px">
    <input type="hidden" name="section" value="influencers">
    <input name="q" value="{{ $q }}" placeholder="{{ __('Search name or username') }}">
    <button class="a-btn ghost" type="submit">{{ __('Search') }}</button>
</form>

<div class="a-panel">
    <table class="a-table">
        <thead><tr><th>{{ __('Influencer') }}</th><th>{{ __('Followers') }}</th><th>{{ __('Categories') }}</th><th>{{ __('Instagram') }}</th><th>{{ __('Visibility') }}</th></tr></thead>
        <tbody>
        @forelse($creators as $creator)
            <tr>
                <td>{{ $creator->display_name }}<span class="a-sub">&#64;{{ $creator->username }} · {{ $creator->user?->email }}</span></td>
                <td>{{ $creator->follower_count !== null ? number_format($creator->follower_count) : '—' }}</td>
                <td>{{ ! empty($categoryMap[$creator->id]) ? implode(', ', $categoryMap[$creator->id]) : '—' }}</td>
                <td><span class="a-tag {{ $creator->instagram_connection_status === 'connected' ? 'ok' : '' }}">{{ $creator->instagram_connection_status ?? 'disconnected' }}</span></td>
                <td><span class="a-tag {{ $creator->visibility === 'public' ? 'ok' : 'warn' }}">{{ $creator->visibility }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="a-empty">{{ __('No influencers yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $creators->links() }}
@endsection
