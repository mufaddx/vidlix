@extends('layouts.admin')
@section('title', __('Webhook deliveries'))
@section('content')

@if($rejectedLastDay > 0)
    <div class="a-panel">
        <div class="a-panel-body">
            {{-- A run of rejections almost always means a rotated secret, and
                 nothing else in the interface would surface that. --}}
            <p class="a-tag danger">
                {{ trans_choice(
                    ':count delivery was rejected in the last day.|:count deliveries were rejected in the last day.',
                    $rejectedLastDay,
                    ['count' => $rejectedLastDay],
                ) }}
            </p>
            <p class="a-hint">{{ __('Repeated rejections usually mean a signing secret was rotated at the provider but not here.') }}</p>
        </div>
    </div>
@endif

<div class="a-panel">
    <div class="a-panel-head">{{ __('Deliveries') }}</div>
    <table class="a-table">
        <thead>
        <tr>
            <th>{{ __('When') }}</th>
            <th>{{ __('Provider') }}</th>
            <th>{{ __('Signature') }}</th>
            <th>{{ __('Processing') }}</th>
            <th>{{ __('Event') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="a-sub">{{ $log->created_at }}</td>
                <td>{{ $log->provider }}</td>
                <td>
                    <span class="a-tag {{ $log->signature_status === 'valid' ? 'ok' : 'danger' }}">
                        {{ str_replace('_', ' ', $log->signature_status) }}
                    </span>
                </td>
                <td>
                    <span class="a-tag {{ $log->processing_status === 'accepted' ? 'ok' : 'warn' }}">
                        {{ $log->processing_status }}
                    </span>
                </td>
                {{-- The payload is deliberately not shown. It can carry personal
                     data, and reading it is a different decision from checking
                     that deliveries are arriving. --}}
                <td class="a-sub">{{ $log->error_message ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="a-empty">{{ __('No provider has ever called us.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $logs->links() }}
@endsection
