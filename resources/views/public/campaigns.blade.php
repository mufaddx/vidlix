@extends('layouts.public')
@section('title', __('Campaigns'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Marketplace') }}</p>
    <h1>{{ __('Open campaigns') }}</h1>
    <p class="lede">{{ __('Apply with the fee you want for the work. Payment is handled and held by the platform until the work is accepted.') }}</p>
</div>
<div class="wrap section" style="padding-top:12px;">
    <div class="grid">
        @forelse($campaigns as $campaign)
            <article class="card">
                <p class="kicker">{{ $campaign->platform }}</p>
                <h2>{{ $campaign->name }}</h2>
                <p class="muted">{{ $campaign->objective }}</p>
                <p>{{ \Illuminate\Support\Str::limit($campaign->brief, 160) }}</p>
                @auth
                    <form class="form" method="post" action="{{ route('app.campaigns.apply', $campaign) }}" style="margin-top:16px;">
                        @csrf
                        <label>{{ __('Proposed fee (paise)') }}
                            <input name="proposed_fee_minor" required>
                        </label>
                        <label>{{ __('Message') }}
                            <textarea name="message" required></textarea>
                        </label>
                        <button class="btn" type="submit">{{ __('Apply') }}</button>
                    </form>
                @else
                    <a class="btn secondary" href="{{ route('login') }}">{{ __('Log in to apply') }}</a>
                @endauth
            </article>
        @empty
            <p class="muted">{{ __('No published campaigns.') }}</p>
        @endforelse
    </div>
    {{ $campaigns->links() }}
</div>
@endsection
