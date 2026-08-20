@extends('layouts.admin')
@section('title', __('Reported threads'))
@section('content')

<div class="a-panel">
    <div class="a-panel-head">{{ __('Reported conversations') }}</div>
    <table class="a-table">
        <thead>
        <tr>
            <th>{{ __('When') }}</th>
            <th>{{ __('Reported by') }}</th>
            <th>{{ __('Reason') }}</th>
            <th>{{ __('Detail') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Decision') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($items as $report)
            <tr>
                <td class="a-sub">{{ $report->created_at }}</td>
                <td>
                    {{ $report->reporter?->name }}
                    <span class="a-sub">{{ $report->reporter?->email }}</span>
                </td>
                <td>{{ ucfirst($report->reason) }}</td>
                <td class="a-sub">{{ $report->detail ?: '—' }}</td>
                <td>
                    <span class="a-tag {{ $report->status === 'open' ? 'warn' : ($report->status === 'actioned' ? 'danger' : 'ok') }}">
                        {{ $report->status }}
                    </span>
                    @if($report->reviewed_at)
                        <span class="a-sub">{{ $report->reviewed_at->diffForHumans() }}</span>
                    @endif
                </td>
                <td>
                    {{-- The thread itself is not shown here. Reading a member's
                         messages is a separate decision from triaging the
                         complaint about them. --}}
                    <form class="a-form" method="post" action="{{ route('admin.reports.resolve', $report) }}">
                        @csrf
                        <select name="status">
                            <option value="reviewing" @selected($report->status === 'reviewing')>{{ __('Reviewing') }}</option>
                            <option value="actioned" @selected($report->status === 'actioned')>{{ __('Actioned') }}</option>
                            <option value="dismissed" @selected($report->status === 'dismissed')>{{ __('Dismissed') }}</option>
                        </select>
                        <input name="review_note" maxlength="2000" value="{{ $report->review_note }}"
                               placeholder="{{ __('Note (recorded in the audit log)') }}">
                        <button class="a-btn" type="submit">{{ __('Save') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="a-empty">{{ __('Nothing has been reported.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $items->links() }}
@endsection
