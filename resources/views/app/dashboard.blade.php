@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')
<p class="kicker">{{ __('Workspace') }}</p>
<h1>{{ __('Hello, :name', ['name' => $user->name]) }}</h1>

@if($profile)
    <div class="grid grid-4">
        <article class="card">
            <p class="muted">{{ __('Profile completion') }}</p>
            <p class="stat">{{ $profile->profile_completion }}%</p>
        </article>
        <article class="card">
            <p class="muted">{{ __('Instagram') }}</p>
            <p class="stat">{{ $profile->instagram_connection_status }}</p>
            <p class="muted">{{ $instagramConfigured ? __('Meta OAuth is configured.') : __('Provider not configured — no fake analytics.') }}</p>
        </article>
        <article class="card">
            <p class="muted">{{ __('External inquiries') }}</p>
            <p class="stat">{{ $inquiryCount }}</p>
        </article>
        <article class="card">
            <p class="muted">{{ __('Earnings (available)') }}</p>
            @if($ledgerCount === 0)
                <p>{{ __('No transactions') }}</p>
            @else
                <p class="stat">₹{{ number_format($availableMinor / 100, 2) }}</p>
            @endif
            <p class="muted">{{ $paymentsConfigured ? __('Payments are open.') : __('Payments are not available yet. Nothing can be charged or paid out until they are.') }}</p>
        </article>
    </div>
@else
    <p class="banner">{{ __('This account has no creator workspace yet. Editor/brand onboarding continues in later phases.') }}</p>
@endif
@endsection
