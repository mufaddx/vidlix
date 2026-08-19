@extends('layouts.admin')
@section('title', __('All editors'))
@section('subheading', __(':count editor accounts', ['count' => $editors->total()]))
@section('content')
<form class="a-form" method="get" style="margin-bottom:18px">
    <input type="hidden" name="section" value="editors">
    <input name="q" value="{{ $q }}" placeholder="{{ __('Search name or username') }}">
    <button class="a-btn ghost" type="submit">{{ __('Search') }}</button>
</form>

<div class="a-panel">
    <table class="a-table">
        <thead><tr><th>{{ __('Editor') }}</th><th>{{ __('Works on') }}</th><th>{{ __('From') }}</th><th>{{ __('Application') }}</th></tr></thead>
        <tbody>
        @forelse($editors as $editor)
            <tr>
                <td>{{ $editor->display_name }}<span class="a-sub">&#64;{{ $editor->username }} · {{ $editor->user?->email }}</span></td>
                <td>{{ ! empty($categoryMap[$editor->id]) ? implode(', ', $categoryMap[$editor->id]) : '—' }}</td>
                <td>{{ $editor->starting_price_minor ? '₹'.number_format($editor->starting_price_minor / 100) : '—' }}</td>
                <td><span class="a-tag {{ $editor->application_status === 'approved' ? 'ok' : 'warn' }}">{{ $editor->application_status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="4" class="a-empty">{{ __('No editors yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $editors->links() }}
@endsection
