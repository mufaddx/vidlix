@extends('layouts.admin')
@section('title', __('Campaigns'))
@section('subheading', __('Every campaign, whoever created it'))
@section('content')
<div class="a-panel">
    <table class="a-table">
        <thead><tr><th>{{ __('Campaign') }}</th><th>{{ __('Brand') }}</th><th>{{ __('Objective') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
        @forelse($campaigns as $campaign)
            <tr>
                <td>{{ $campaign->name }}<span class="a-sub">{{ $campaign->slug }}</span></td>
                <td>{{ $campaign->brand?->company_name ?? '—' }}</td>
                <td>{{ $campaign->objective ?? '—' }}</td>
                <td><span class="a-tag {{ $campaign->status === 'published' ? 'ok' : 'warn' }}">{{ $campaign->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="4" class="a-empty">{{ __('No campaigns yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $campaigns->links() }}
@endsection
