@extends('layouts.app')
@section('title', __('Campaigns'))
@section('content')

<h1>{{ __('Campaigns') }}</h1>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

@php($lifecycle = app(\App\Services\Marketplace\CampaignLifecycle::class))
@php($labels = [
    'pending_review' => __('Send for review'),
    'published' => __('Reopen'),
    'paused' => __('Pause'),
    'closed' => __('Close'),
    'cancelled' => __('Cancel'),
    'completed' => __('Mark complete'),
    'draft' => __('Back to draft'),
])

<h2>{{ __('Yours') }}</h2>
@forelse($mine as $campaign)
    <article class="card" style="margin-bottom:12px">
        <p class="kicker">
            <span class="chip">{{ str_replace('_', ' ', $campaign->status) }}</span>
            @if($campaign->published_at)
                <span class="muted">{{ __('Published :when', ['when' => $campaign->published_at->diffForHumans()]) }}</span>
            @endif
        </p>

        <h3>{{ $campaign->name }}</h3>
        @if($campaign->objective)<p class="muted">{{ $campaign->objective }}</p>@endif
        @if($campaign->budget_minor)
            <p class="stat">₹{{ number_format($campaign->budget_minor / 100, 2) }}</p>
        @endif

        <p style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <a class="btn secondary" href="{{ route('app.campaigns.applicants', $campaign) }}">{{ __('Applicants') }}</a>

            {{-- Only the moves this campaign can actually make. Offering one it
                 cannot take would be a button that exists to fail. --}}
            @foreach($lifecycle->availableTo($campaign) as $next)
                @continue($campaign->status === 'pending_review' && $next === 'published')
                <form method="post" action="{{ route('app.campaigns.transition', $campaign) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $next }}">
                    <button class="btn secondary" type="submit"
                        @if($next === 'closed') onclick="return confirm('{{ __('Close this campaign? Anyone still waiting will be told it is over.') }}')" @endif>
                        {{ $labels[$next] ?? $next }}
                    </button>
                </form>
            @endforeach
        </p>

        @if($campaign->status === 'pending_review')
            <p class="muted">{{ __('With a reviewer. You will hear either way.') }}</p>
        @endif
    </article>
@empty
    @include('partials.state', [
        'state' => 'empty',
        'detail' => __('No campaigns yet. Draft one below — nothing goes live until a reviewer has seen it.'),
    ])
@endforelse

<h2>{{ __('Draft a campaign') }}</h2>
<form class="form" method="post" action="{{ route('app.campaigns.store') }}">
    @csrf
    <label for="name">{{ __('Name') }}</label>
    <input id="name" name="name" required maxlength="160">

    <label for="objective">{{ __('Objective') }}</label>
    <input id="objective" name="objective" maxlength="160">

    <label for="platform">{{ __('Platform') }}</label>
    <input id="platform" name="platform" maxlength="80">

    <label for="budget_minor">{{ __('Budget, in paise') }}</label>
    <input id="budget_minor" name="budget_minor" type="number" min="0">
    <p class="muted">{{ __('Paise, not rupees — ₹50,000 is 5000000.') }}</p>

    <label for="brief">{{ __('Brief') }}</label>
    <textarea id="brief" name="brief"></textarea>

    <button class="btn" type="submit">{{ __('Save draft') }}</button>
</form>

<h2>{{ __('Open to apply') }}</h2>
@forelse($open as $campaign)
    <article class="card" style="margin-bottom:12px">
        <h3>{{ $campaign->name }}</h3>
        @if($campaign->objective)<p class="muted">{{ $campaign->objective }}</p>@endif
        @if($campaign->budget_minor)
            <p class="stat">₹{{ number_format($campaign->budget_minor / 100, 2) }}</p>
        @endif
        @if($campaign->brief)<p style="white-space:pre-wrap">{{ $campaign->brief }}</p>@endif

        <form class="form" method="post" action="{{ route('app.campaigns.apply', $campaign) }}">
            @csrf
            <label for="fee-{{ $campaign->id }}">{{ __('What you would charge, in paise') }}</label>
            <input id="fee-{{ $campaign->id }}" name="proposed_fee_minor" type="number" min="1" required>

            <label for="msg-{{ $campaign->id }}">{{ __('Why you') }}</label>
            <textarea id="msg-{{ $campaign->id }}" name="message" required maxlength="4000"></textarea>

            <label for="avail-{{ $campaign->id }}">{{ __('When you are free') }}</label>
            <input id="avail-{{ $campaign->id }}" name="availability" maxlength="120">

            <button class="btn" type="submit">{{ __('Apply') }}</button>
        </form>
    </article>
@empty
    @include('partials.state', [
        'state' => 'empty',
        'detail' => __('No open campaigns right now. New ones appear here as brands publish them.'),
    ])
@endforelse
@endsection
