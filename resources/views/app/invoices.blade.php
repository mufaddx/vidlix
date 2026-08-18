@extends('layouts.app')
@section('title', __('Invoices'))
@section('content')
<h1>{{ __('Invoices') }}</h1>
@forelse($items as $i)
    <p>{{ $i->invoice_number }} · {{ $i->status }} · {{ $i->total_minor }}</p>
@empty
    <p class="muted">{{ __('No invoices') }}</p>
@endforelse
@endsection
