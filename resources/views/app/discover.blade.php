@extends('layouts.app')
@section('title', __('Find creators'))
@section('content')
<h1>{{ __('Find creators') }}</h1>

<form class="form" method="get" action="{{ route('app.discover') }}">
    <label for="q">{{ __('Search by name') }}</label>
    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Name or @username') }}">

    <label>{{ __('Categories') }}</label>
    <div class="chips">
        @foreach($categories as $category)
            <label class="chip">
                <input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, $selected))>
                <span>{{ $category->name }}</span>
            </label>
        @endforeach
    </div>

    <div class="grid">
        <div>
            <label for="min_followers">{{ __('Minimum followers') }}</label>
            <input id="min_followers" name="min_followers" type="number" min="0" value="{{ $filters['min_followers'] ?? '' }}">
        </div>
        <div>
            <label for="max_followers">{{ __('Maximum followers') }}</label>
            <input id="max_followers" name="max_followers" type="number" min="0" value="{{ $filters['max_followers'] ?? '' }}">
        </div>
    </div>

    <button class="btn" type="submit">{{ __('Search') }}</button>
</form>

<p class="muted">{{ __('Follower counts come from a creator\'s connected Instagram. Creators who have not connected are not shown when you filter by followers — we do not know their reach, and will not guess it.') }}</p>

<h2>{{ __(':count creators', ['count' => $creators->total()]) }}</h2>

@forelse($creators as $creator)
    <div class="card">
        <strong>{{ $creator->display_name }}</strong> <span class="muted">&#64;{{ $creator->username }}</span>
        <p class="muted">
            @if($creator->follower_count !== null)
                {{ number_format($creator->follower_count) }} {{ __('followers') }}
            @else
                {{ __('Followers not synced') }}
            @endif
            @if(! empty($categoryMap[$creator->id]))
                · {{ implode(' · ', $categoryMap[$creator->id]) }}
            @endif
        </p>

        <details>
            <summary>{{ __('Connect') }}</summary>
            <form class="form" method="post" action="{{ route('app.discover.connect', $creator) }}">@csrf
                <label for="subject-{{ $creator->id }}">{{ __('Subject') }}</label>
                <input id="subject-{{ $creator->id }}" name="subject" required maxlength="160">
                <label for="message-{{ $creator->id }}">{{ __('Message') }}</label>
                <textarea id="message-{{ $creator->id }}" name="message" required maxlength="4000"></textarea>
                <button class="btn" type="submit">{{ __('Start conversation') }}</button>
            </form>
        </details>
    </div>
@empty
    <p class="muted">{{ __('No creators match those filters yet.') }}</p>
@endforelse

{{ $creators->links() }}
@endsection
