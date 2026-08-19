@extends('layouts.admin')
@section('title', __('Finance'))
@section('subheading', __('Withdrawal requests. Approving instructs a real bank transfer.'))
@section('content')

<div class="a-notice info">
    {{ __('Approving hands the payout to the provider. A withdrawal only becomes paid when a signed payout webhook is confirmed against the provider API — there is deliberately no way to mark one paid by hand.') }}
</div>

<div class="a-panel">
    <table class="a-table">
        <thead>
            <tr><th>{{ __('Member') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Provider') }}</th><th style="width:24%">{{ __('Decision') }}</th></tr>
        </thead>
        <tbody>
        @forelse($withdrawals as $w)
            <tr>
                <td>{{ $w->user?->name ?? '—' }}<span class="a-sub">{{ $w->user?->email }}</span></td>
                <td><strong>₹{{ number_format($w->amount_minor / 100, 2) }}</strong></td>
                <td>
                    <span class="a-tag {{ $w->status === 'paid' ? 'ok' : ($w->status === 'requested' ? 'warn' : '') }}">{{ $w->status }}</span>
                </td>
                <td class="a-sub">
                    {{ $w->provider_payout_id ?? '—' }}
                    @if($w->last_provider_detail)<span class="a-sub">{{ $w->last_provider_detail }}</span>@endif
                </td>
                <td>
                    @if($w->status === 'requested')
                        <form method="post" action="{{ route('admin.withdrawals.update', $w) }}" style="display:flex;gap:6px">@csrf
                            <button class="a-btn" name="decision" value="approve">{{ __('Approve') }}</button>
                            <button class="a-btn danger" name="decision" value="reject">{{ __('Reject') }}</button>
                        </form>
                    @else
                        <span class="a-sub">{{ __('No action available in this state.') }}</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="a-empty">{{ __('No withdrawal requests.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
