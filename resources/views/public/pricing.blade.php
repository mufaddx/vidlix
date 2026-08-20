@extends('layouts.public')
@section('title', __('Management pricing'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Workspace') }}</p>
    <h1>{{ __('Creator management plans') }}</h1>
    <p class="lede">{{ __('Simple plans, no joining fee. You are only charged for what you book.') }}</p>
</div>
<div class="wrap section" style="padding-top:12px;">
    <div class="grid">
        @forelse($plans as $plan)
            <article class="card">
                <p class="kicker">{{ __('Plan') }}</p>
                <h2>{{ $plan->name }}</h2>
                <p class="stat">₹{{ number_format($plan->price_minor / 100, 0) }}</p>
                <p class="muted">{{ implode(' · ', $plan->features['bullets'] ?? []) }}</p>
                <a class="btn" href="{{ route('register') }}">{{ __('Get started') }}</a>
            </article>
        @empty
            <p class="muted">{{ __('No plans published.') }}</p>
        @endforelse
    </div>
</div>
@endsection
