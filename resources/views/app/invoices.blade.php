@extends('layouts.app')
@section('title', __('Invoices'))
@section('content')
<h1>{{ __('Invoices') }}</h1>
@if($items->isEmpty())
    <p class="muted">{{ __('No invoices yet.') }}</p>
@else
<table class="table">
    <thead><tr>
        <th>{{ __('Number') }}</th>
        <th>{{ __('Status') }}</th>
        <th>{{ __('Due') }}</th>
        <th>{{ __('Total') }}</th>
        <th></th>
    </tr></thead>
    <tbody>
    @foreach($items as $i)
        <tr>
            <td>{{ $i->invoice_number }}</td>
            <td>{{ $i->status }}</td>
            <td>{{ $i->due_date?->toDateString() }}</td>
            <td>{{ number_format($i->total_minor / 100, 2) }} {{ $i->currency }}</td>
            <td><a href="{{ route('app.invoices.pdf', $i) }}">{{ __('Download PDF') }}</a></td>
        </tr>
    @endforeach
    </tbody>
</table>
<p class="muted">{{ __('An invoice records what is owed. It is marked paid only when the payment is actually confirmed.') }}</p>
@endif
@endsection
