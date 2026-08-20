@extends('layouts.public')
@section('title', __('Pricing'))
@section('meta_description', __('What Vidlix costs: free to join, a single commission on completed work, and nothing charged until money actually moves.'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Pricing') }}</p>
    <h1>{{ __('Free to join. Charged only when you get paid.') }}</h1>
    <p class="lede">{{ __('There is no subscription to work on Vidlix. A single commission is taken from completed, settled work — nothing is charged for signing up, publishing a profile, or receiving inquiries.') }}</p>
</div>

<div class="wrap section" style="padding-top:12px;">
    <div class="grid">
        <article class="card">
            <p class="kicker">{{ __('Creators and editors') }}</p>
            <h2>{{ __('Free') }}</h2>
            <p class="muted">{{ __('Public profile, inquiry form, unified inbox, portfolio, campaign applications and projects. No card required.') }}</p>
        </article>

        <article class="card">
            <p class="kicker">{{ __('Platform commission') }}</p>
            @if($commissionBps)
                <p class="stat">{{ rtrim(rtrim(number_format($commissionBps / 100, 2), '0'), '.') }}%</p>
                <p class="muted">{{ __('Taken from the value of completed work when it settles. It is recorded as its own ledger entry, so you can always see exactly what was deducted and when.') }}</p>
            @else
                {{-- No active rule configured. Saying nothing beats quoting a rate the ledger would not apply. --}}
                <p class="stat">{{ __('Not set') }}</p>
                <p class="muted">{{ __('No commission rule is currently active. Contact us and we will confirm the rate that applies to your work in writing before you start.') }}</p>
            @endif
        </article>

        <article class="card">
            <p class="kicker">{{ __('Brands') }}</p>
            <h2>{{ __('Free to post') }}</h2>
            <p class="muted">{{ __('Publishing a campaign and reviewing applicants costs nothing. You pay the agreed amount for the work itself, through a licensed payment provider.') }}</p>
        </article>
    </div>
</div>

<div class="wrap section">
    <div class="section-head">
        <div>
            <h2>{{ __('Instagram AutoDM') }}</h2>
            <p class="muted">{{ __('Turn comments on your posts into conversations, using official Instagram APIs only.') }}</p>
        </div>
        <a href="{{ config('vidlix.domains.autodm') }}">{{ __('See AutoDM') }}</a>
    </div>
    <p class="muted">{{ __('AutoDM is billed separately from the marketplace. Plans are not published yet — what it can do depends on the permissions Instagram grants an account, and we would rather price it once that is settled than quote a number we might have to withdraw.') }}</p>
</div>

<div class="wrap section">
    <h2>{{ __('What is never charged') }}</h2>
    <div class="grid">
        <article class="card"><h3>{{ __('Signing up') }}</h3><p class="muted">{{ __('Creating an account, adding a creator or editor profile, and applying for verification are all free.') }}</p></article>
        <article class="card"><h3>{{ __('Receiving inquiries') }}</h3><p class="muted">{{ __('Your public page, contact form and inbox do not cost anything, however many people write to you.') }}</p></article>
        <article class="card"><h3>{{ __('Withdrawing') }}</h3><p class="muted">{{ __('Vidlix adds no fee to a withdrawal. Your bank or the payout provider may apply their own charges.') }}</p></article>
    </div>
</div>
@endsection
