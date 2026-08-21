@extends('layouts.autodm')
@section('title', __('AutoDM'))
@section('content')

<h1>{{ __('AutoDM') }}</h1>
<p class="muted">{{ __('Turn comments on your posts into conversations.') }}</p>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

{{-- Connection first: everything below it is meaningless without one. --}}
@if(! $providerConfigured)
    @include('partials.state', [
        'state' => 'provider_unconfigured',
        'detail' => __('Instagram is not configured on this installation, so AutoDM cannot connect to anything yet.'),
    ])
@elseif(! $account)
    @include('partials.state', [
        'state' => 'empty',
        'detail' => __('Connect Instagram to start. You will need a professional account — Instagram does not expose comments on personal ones.'),
        'action' => route('app.instagram'),
        'actionLabel' => __('Connect Instagram'),
    ])
@elseif($account->status !== 'connected')
    @include('partials.state', [
        'state' => 'provider_disconnected',
        'detail' => $account->last_error ?: null,
        'action' => route('app.instagram'),
        'actionLabel' => __('Reconnect'),
    ])
@else

    <div class="card" style="margin-bottom:20px">
        <p class="kicker">{{ __('Connected account') }}</p>
        <h2>&#64;{{ $account->username }}</h2>
        <p class="muted">
            @if($account->last_synced_at)
                {{ __('Media last refreshed :when', ['when' => $account->last_synced_at->diffForHumans()]) }}
            @else
                {{ __('Media has not been refreshed yet.') }}
            @endif
        </p>

        {{-- What this account may actually do, with a reason for anything it
             may not. Shown here rather than only at activation, so nobody
             designs an automation around an action they cannot perform. --}}
        <ul>
            @foreach($capabilities as $capability)
                <li>
                    {{ $capability['allowed'] ? '✓' : '○' }} {{ $capability['label'] }}
                    @unless($capability['allowed'])
                        <span class="muted">— {{ $capability['reason'] }}</span>
                    @endunless
                </li>
            @endforeach
        </ul>

        <p>
            <a class="btn" href="{{ route('autodm.create') }}">{{ __('New automation') }}</a>
            <a class="btn secondary" href="{{ route('app.instagram') }}">{{ __('Refresh media') }}</a>
        </p>
    </div>

    <h2>{{ __('Your automations') }}</h2>
    @forelse($automations as $automation)
        <article class="card" style="margin-bottom:12px">
            <p class="kicker">
                <span class="chip">{{ $automation->status }}</span>
                @if($automation->last_run_at)
                    <span class="muted">{{ __('Last ran :when', ['when' => $automation->last_run_at->diffForHumans()]) }}</span>
                @endif
            </p>
            <h3>{{ $automation->name }}</h3>
            <p class="muted">
                @if($automation->instagram_media_id)
                    {{ __('One post') }}
                @else
                    {{ __('Every post on the account') }}
                @endif
            </p>
            <p style="display:flex;gap:8px;flex-wrap:wrap">
                <a class="btn secondary" href="{{ route('autodm.edit', $automation->uuid) }}">{{ __('Edit') }}</a>
                <a class="btn secondary" href="{{ route('autodm.runs', $automation->uuid) }}">{{ __('History') }}</a>

                @if($automation->isActive())
                    <form method="post" action="{{ route('autodm.deactivate', $automation->uuid) }}">
                        @csrf
                        <button class="btn secondary" type="submit">{{ __('Switch off') }}</button>
                    </form>
                @else
                    <a class="btn" href="{{ route('autodm.review', $automation->uuid) }}">{{ __('Review and activate') }}</a>
                @endif

                <form method="post" action="{{ route('autodm.duplicate', $automation->uuid) }}">
                    @csrf
                    <button class="btn secondary" type="submit">{{ __('Duplicate') }}</button>
                </form>
            </p>
        </article>
    @empty
        @include('partials.state', [
            'state' => 'empty',
            'detail' => __('No automations yet. Build one and it will answer comments for you.'),
            'action' => route('autodm.create'),
            'actionLabel' => __('New automation'),
        ])
    @endforelse

    <h2>{{ __('Recent activity') }}</h2>
    @forelse($recentRuns as $run)
        <p class="muted">
            <span class="chip">{{ str_replace('_', ' ', $run->status) }}</span>
            {{ str_replace('_', ' ', $run->action) }}
            · {{ $run->created_at?->diffForHumans() }}
            @if($run->reason_code)
                — {{ $run->detail }}
            @endif
        </p>
    @empty
        <p class="muted">{{ __('Nothing has run yet. Activity appears here the first time somebody comments.') }}</p>
    @endforelse
@endif
@endsection
