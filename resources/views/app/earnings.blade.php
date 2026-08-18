@extends('layouts.app')
@section('title', __('Earnings'))
@section('content')
<h1>{{ __('Ledger') }}</h1>
@if($count === 0)
    <p>{{ __('No transactions') }}</p>
@else
    <p class="stat">{{ __('Available') }} ₹{{ number_format($available/100, 2) }}</p>
    <p class="muted">{{ __('Held in escrow') }} ₹{{ number_format($reserved/100, 2) }} · {{ __('released when the project completes') }}</p>
@endif
<table class="table">
@foreach($ledger as $row)
    <tr><td>{{ $row->created_at }}</td><td>{{ $row->state }}</td><td>{{ $row->amount_minor }}</td><td>{{ $row->provider_reference }}</td></tr>
@endforeach
</table>
<form method="post" action="{{ route('app.withdraw') }}">@csrf<input type="number" name="amount_minor" required><button class="btn" type="submit">{{ __('Request withdrawal') }}</button></form>
@foreach($withdrawals as $w)<p>{{ $w->status }} · {{ $w->amount_minor }}</p>@endforeach
@endsection
