@extends('layouts.admin')
@section('title', __('AutoDM accounts'))
@section('content')

<div class="a-panel">
    <div class="a-panel-head">{{ __('Connected Instagram accounts') }}</div>
    <table class="a-table">
        <thead>
        <tr>
            <th>{{ __('Account') }}</th>
            <th>{{ __('Member') }}</th>
            <th>{{ __('State') }}</th>
            <th>{{ __('Active') }}</th>
            <th>{{ __('Sent') }}</th>
            <th>{{ __('Skipped') }}</th>
            <th>{{ __('Failed') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($accounts as $row)
            @php($account = $row['account'])
            <tr>
                <td>&#64;{{ $account->username ?? '—' }}</td>
                <td class="a-sub">{{ $account->creatorProfile?->display_name ?? '—' }}</td>
                <td>
                    <span class="a-tag {{ $account->status === 'connected' ? 'ok' : 'warn' }}">{{ $account->status }}</span>
                    @if($account->token_expires_at && $account->token_expires_at->isPast())
                        <span class="a-tag danger">{{ __('token expired') }}</span>
                    @endif
                </td>
                <td>{{ $row['automations'] }}</td>
                <td>{{ $row['sent'] }}</td>
                {{-- Skipped is its own column, not folded into failed. A skip is
                     something the platform would not permit; there is nothing
                     to chase. --}}
                <td class="a-sub">{{ $row['skipped'] }}</td>
                <td>
                    @if($row['failed'] > 0)
                        <span class="a-tag danger">{{ $row['failed'] }}</span>
                    @else
                        0
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="a-empty">{{ __('No Instagram accounts are connected.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="a-panel-body">
        <p class="a-hint">{{ __('Counts cover the last seven days.') }}</p>
    </div>
</div>

<div class="a-panel">
    <div class="a-panel-head">{{ __('Recent skips and failures') }}</div>
    <table class="a-table">
        <thead>
        <tr>
            <th>{{ __('When') }}</th>
            <th>{{ __('Automation') }}</th>
            <th>{{ __('Action') }}</th>
            <th>{{ __('Outcome') }}</th>
            <th>{{ __('Reason') }}</th>
        </tr>
        </thead>
        <tbody>
        @forelse($recentFailures as $run)
            <tr>
                <td class="a-sub">{{ $run->created_at }}</td>
                <td>{{ $run->automation?->name ?? '—' }}</td>
                <td>{{ str_replace('_', ' ', $run->action) }}</td>
                <td>
                    <span class="a-tag {{ $run->status === 'skipped' ? 'warn' : 'danger' }}">
                        {{ str_replace('_', ' ', $run->status) }}
                    </span>
                </td>
                <td class="a-sub">{{ $run->reason_code ?? '—' }} · {{ $run->detail }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="a-empty">{{ __('Nothing has been skipped or failed.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
