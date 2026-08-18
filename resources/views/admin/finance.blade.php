@extends('layouts.app')
@section('title', __('Finance'))
@section('content')
<h1>{{ __('Withdrawals') }}</h1>
<p class="muted">{{ __('Approving instructs the payout provider. A withdrawal only becomes paid when a signed payout webhook is confirmed against the provider API.') }}</p>
@forelse($withdrawals as $w)
    <form method="post" action="{{ route('admin.withdrawals.update', $w) }}">@csrf
        <span>#{{ $w->id }} · {{ $w->status }} · ₹{{ number_format($w->amount_minor / 100, 2) }}</span>
        @if($w->provider_payout_id)<span class="muted">· {{ $w->provider_payout_id }}</span>@endif
        @if($w->last_provider_detail)<p class="muted">{{ $w->last_provider_detail }}</p>@endif
        @if($w->status === 'requested')
            <button class="btn" type="submit" name="decision" value="approve">{{ __('Approve and instruct payout') }}</button>
            <button type="submit" name="decision" value="reject">{{ __('Reject') }}</button>
        @else
            <p class="muted">{{ __('No admin action available in this state.') }}</p>
        @endif
    </form>
@empty
    <p>{{ __('No withdrawal requests.') }}</p>
@endforelse
@endsection
