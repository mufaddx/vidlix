@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')

<p class="kicker">{{ __('Workspace') }}</p>
<h1>{{ __('Hello, :name', ['name' => $user->name]) }}</h1>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif

@if(! $profile && ! $editorProfile)
    @include('partials.state', [
        'state' => 'empty',
        'detail' => __('You have not set up a profile yet. Tell us what you do and the rest of Vidlix opens up.'),
        'action' => route('app.roles'),
        'actionLabel' => __('Choose what you do'),
    ])
@else

    {{-- Quick actions first. On a phone this is the top of the screen, and it is
         what somebody opening the app actually came to do. --}}
    <div class="grid grid-4" style="margin-bottom:20px">
        <a class="card" href="{{ route('app.campaigns') }}"><strong>{{ __('Find campaigns') }}</strong></a>
        <a class="card" href="{{ route('app.discover') }}"><strong>{{ __('Find editors') }}</strong></a>
        <a class="card" href="{{ route('inbox') }}">
            <strong>{{ __('Inbox') }}</strong>
            @if($unread > 0)<span class="chip">{{ $unread }}</span>@endif
        </a>
        <a class="card" href="{{ route('autodm.index') }}"><strong>{{ __('AutoDM') }}</strong></a>
    </div>

    @if($profile)
        <div class="grid grid-4">
            <article class="card">
                <p class="muted">{{ __('Profile completion') }}</p>
                <p class="stat">{{ $profile->profile_completion }}%</p>
                @if($profile->profile_completion < 100)
                    <a href="{{ route('creator.public-page') }}">{{ __('Finish it') }}</a>
                @endif
            </article>

            <article class="card">
                <p class="muted">{{ __('Public page') }}</p>
                <p class="stat">{{ $profile->isPublished() ? __('Live') : __('Not live') }}</p>
                @unless($profile->isPublished())
                    <a href="{{ route('creator.public-page') }}">{{ __('Publish it') }}</a>
                @endunless
            </article>

            <article class="card">
                <p class="muted">{{ __('Instagram') }}</p>
                <p class="stat">{{ str_replace('_', ' ', $profile->instagram_connection_status) }}</p>
                <p class="muted">
                    {{ $instagramConfigured
                        ? __('Connected through official Meta sign-in.')
                        : __('Not configured here, so no numbers are shown rather than invented ones.') }}
                </p>
            </article>

            <article class="card">
                <p class="muted">{{ __('Reach') }}</p>
                @if($profile->follower_count_synced_at)
                    <p class="stat">{{ number_format($profile->follower_count) }}</p>
                    {{-- Always dated. A reach figure with no date is one somebody
                         will read as current when it is not. --}}
                    <p class="muted">{{ __('Synced :when', ['when' => $profile->follower_count_synced_at->diffForHumans()]) }}</p>
                @else
                    <p class="stat">{{ __('Not synced') }}</p>
                    <p class="muted">{{ __('Connect Instagram and this fills itself in.') }}</p>
                @endif
            </article>
        </div>
    @endif

    <div class="grid grid-4" style="margin-top:16px">
        <article class="card">
            <p class="muted">{{ __('Unread messages') }}</p>
            <p class="stat">{{ $unread }}</p>
            <a href="{{ route('inbox') }}">{{ __('Open inbox') }}</a>
        </article>

        <article class="card">
            <p class="muted">{{ __('Open negotiations') }}</p>
            <p class="stat">{{ $openNegotiations->count() }}</p>
            <a href="{{ route('app.negotiations') }}">{{ __('See them') }}</a>
        </article>

        <article class="card">
            <p class="muted">{{ __('Active projects') }}</p>
            <p class="stat">{{ $activeProjects->count() }}</p>
            <a href="{{ route('app.projects') }}">{{ __('See them') }}</a>
        </article>

        <article class="card">
            <p class="muted">{{ __('Available to withdraw') }}</p>
            @if($ledgerCount === 0)
                {{-- "No transactions" and "₹0.00" mean different things, and only
                     one of them is true of an account that has never traded. --}}
                <p class="stat">{{ __('No transactions') }}</p>
            @else
                <p class="stat">₹{{ number_format($availableMinor / 100, 2) }}</p>
                <a href="{{ route('app.earnings') }}">{{ __('Earnings') }}</a>
            @endif
            @unless($paymentsConfigured)
                <p class="muted">{{ __('Payments are not switched on yet, so nothing can be charged or paid out.') }}</p>
            @endunless
        </article>
    </div>

    {{-- Waiting on somebody else ------------------------------------------ --}}
    @if($pendingApplications->isNotEmpty())
        <h2>{{ __('Applications you are waiting on') }}</h2>
        @foreach($pendingApplications as $application)
            <p>
                <span class="chip">{{ str_replace('_', ' ', $application->status) }}</span>
                {{ $application->campaign?->name ?? __('A campaign') }}
                <span class="muted">{{ __('applied :when', ['when' => $application->created_at?->diffForHumans()]) }}</span>
            </p>
        @endforeach
    @endif

    @if($openNegotiations->isNotEmpty())
        <h2>{{ __('Negotiations in progress') }}</h2>
        @foreach($openNegotiations as $negotiation)
            @php($other = $negotiation->initiator_user_id === $user->id ? $negotiation->counterparty : $negotiation->initiator)
            <p>
                <span class="chip">{{ str_replace('_', ' ', $negotiation->status) }}</span>
                <a href="{{ route('app.negotiations.show', $negotiation->uuid) }}">{{ $other?->name ?? __('Unknown') }}</a>
            </p>
        @endforeach
    @endif

    @if($activeProjects->isNotEmpty())
        <h2>{{ __('Projects on the go') }}</h2>
        @foreach($activeProjects as $project)
            <p>
                <span class="chip">{{ str_replace('_', ' ', $project->status) }}</span>
                <a href="{{ route('app.projects.show', $project) }}">{{ $project->name }}</a>
            </p>
        @endforeach
    @endif

    {{-- Worth a look ------------------------------------------------------ --}}
    @if($recommended->isNotEmpty())
        <h2>{{ __('Campaigns worth a look') }}</h2>
        <p class="muted">{{ __('Open campaigns you have not applied to, within the reach they asked for.') }}</p>

        @foreach($recommended as $campaign)
            <article class="card" style="margin-bottom:8px">
                <strong>{{ $campaign->name }}</strong>
                @if($campaign->budget_minor)
                    <span class="muted">₹{{ number_format($campaign->budget_minor / 100, 2) }}</span>
                @endif
                @if($campaign->objective)<p class="muted">{{ $campaign->objective }}</p>@endif
            </article>
        @endforeach

        <p><a class="btn secondary" href="{{ route('app.campaigns') }}">{{ __('All campaigns') }}</a></p>
    @endif

    @if($notifications->isNotEmpty())
        <h2>{{ __('Recent notifications') }}</h2>
        @foreach($notifications as $notification)
            <p class="muted">
                {{ $notification->data['title'] ?? __('Notification') }}
                <span class="muted">{{ $notification->created_at?->diffForHumans() }}</span>
            </p>
        @endforeach
        <p><a href="{{ route('app.notifications') }}">{{ __('All notifications') }}</a></p>
    @endif
@endif
@endsection
