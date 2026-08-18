@extends('layouts.app')
@section('title', __('Notifications'))
@section('content')
<h1>{{ __('Notifications') }}</h1>
@forelse($items as $n)
    <p>{{ $n->created_at }} · {{ $n->data['type'] ?? '' }}</p>
@empty
    <p class="muted">{{ __('None yet') }}</p>
@endforelse
@endsection
