@extends('layouts.admin')
@section('title', __('Overview'))
@section('subheading', __('Where things stand right now'))
@section('content')
<div class="a-cards">
    <div class="a-card"><div class="a-label">{{ __('Members') }}</div><div class="a-value">{{ $counts['users'] ?? 0 }}</div></div>
    <div class="a-card"><div class="a-label">{{ __('Influencers') }}</div><div class="a-value">{{ $counts['creators'] ?? 0 }}</div></div>
    <div class="a-card"><div class="a-label">{{ __('Editors') }}</div><div class="a-value">{{ $counts['editors'] ?? 0 }}</div></div>
    <div class="a-card"><div class="a-label">{{ __('Brands') }}</div><div class="a-value">{{ $counts['brands'] ?? 0 }}</div></div>
</div>

<div class="a-panel">
    <div class="a-panel-head">{{ __('Recent activity') }}</div>
    <table class="a-table">
        <thead><tr><th>{{ __('When') }}</th><th>{{ __('Action') }}</th><th>{{ __('Request') }}</th></tr></thead>
        <tbody>
        @forelse($audit as $row)
            <tr>
                <td>{{ $row->created_at }}</td>
                <td>{{ $row->action }}</td>
                <td class="a-sub">{{ $row->request_id }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="a-empty">{{ __('Nothing recorded yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
