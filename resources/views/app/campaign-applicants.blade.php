@extends('layouts.app')
@section('title', __('Applicants'))
@section('content')

<p><a href="{{ route('app.campaigns') }}">{{ __('Back to campaigns') }}</a></p>

<h1>{{ $campaign->name }}</h1>
<p class="muted">
    <span class="chip">{{ str_replace('_', ' ', $campaign->status) }}</span>
    {{ trans_choice(':count applicant|:count applicants', $applications->count(), ['count' => $applications->count()]) }}
</p>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

@forelse($applications as $application)
    @php($creator = $application->creator)
    @php($isShortlisted = $creator && in_array($creator->id, $shortlisted, true))

    <article class="card" style="margin-bottom:12px">
        <p class="kicker">
            <span class="chip">{{ str_replace('_', ' ', $application->status) }}</span>
            @if($isShortlisted)<span class="chip">{{ __('Shortlisted') }}</span>@endif
            <span class="muted">{{ __('Applied :when', ['when' => $application->created_at?->diffForHumans()]) }}</span>
        </p>

        <h2>
            @if($creator?->username)
                <a href="{{ \App\Support\PublicUrl::profile($creator->username) }}">{{ $creator->display_name }}</a>
            @else
                {{ __('Unknown applicant') }}
            @endif
        </h2>

        <dl class="a-facts">
            @if($creator?->follower_count)
                <dt>{{ __('Followers') }}</dt>
                <dd>
                    {{ number_format($creator->follower_count) }}
                    {{-- Says when, because a reach figure with no date is a
                         number somebody will read as current when it is not. --}}
                    <span class="muted">{{ $creator->follower_count_synced_at
                        ? __('synced :when', ['when' => $creator->follower_count_synced_at->diffForHumans()])
                        : __('never synced') }}</span>
                </dd>
            @endif

            @if($application->proposed_fee_minor)
                <dt>{{ __('Asking') }}</dt>
                <dd>₹{{ number_format($application->proposed_fee_minor / 100, 2) }}</dd>
            @endif

            @if($application->availability)
                <dt>{{ __('Availability') }}</dt><dd>{{ $application->availability }}</dd>
            @endif
        </dl>

        @if($application->message)
            <p style="white-space:pre-wrap">{{ $application->message }}</p>
        @endif

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
            @if($creator)
                <form method="post" action="{{ route('app.campaigns.shortlist', $campaign) }}">
                    @csrf
                    <input type="hidden" name="subject_type" value="creator">
                    <input type="hidden" name="subject_id" value="{{ $creator->id }}">
                    <input type="hidden" name="shortlisted" value="{{ $isShortlisted ? 0 : 1 }}">
                    <button class="btn secondary" type="submit">
                        {{ $isShortlisted ? __('Remove from shortlist') : __('Shortlist') }}
                    </button>
                </form>
            @endif

            @foreach(['shortlisted' => __('Mark shortlisted'), 'negotiation' => __('Negotiate'), 'rejected' => __('Reject')] as $status => $label)
                <form method="post" action="{{ route('app.applications.status', $application) }}">
                    @csrf
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button class="btn secondary" type="submit">{{ $label }}</button>
                </form>
            @endforeach
        </div>
    </article>
@empty
    @include('partials.state', [
        'state' => 'empty',
        'detail' => __('Nobody has applied yet. Applications appear here as they arrive.'),
    ])
@endforelse
@endsection
