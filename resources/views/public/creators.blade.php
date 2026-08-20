@extends('layouts.public')
@section('title', __('Creators'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Directory') }}</p>
    <h1>{{ __('Creators') }}</h1>
    <p class="lede">{{ __('Public media kits you can actually brief. Search by name or handle.') }}</p>
    <form class="search" action="{{ route('creators.index') }}" method="get" role="search" style="max-width:640px;">
        <label class="hp" for="q">{{ __('Search') }}</label>
        <input id="q" name="q" type="search" value="{{ request('q') }}" placeholder="{{ __('Name or handle') }}">
        <button class="btn" type="submit">{{ __('Search') }}</button>
    </form>
</div>
<div class="wrap section">
    <div class="grid">
        @forelse($creators as $creator)
            <a class="card" href="{{ route('profile.show', $creator->username) }}">
                <span class="avatar" aria-hidden="true">{{ strtoupper(substr($creator->display_name, 0, 1)) }}</span>
                <strong>{{ $creator->display_name }}</strong>
                <p class="muted">{{ '@'.$creator->username }}</p>
                <p>{{ \Illuminate\Support\Str::limit($creator->bio, 120) }}</p>
            </a>
        @empty
            <p class="muted">{{ __('No published creator pages yet.') }}</p>
        @endforelse
    </div>
    {{ $creators->links() }}
</div>
@endsection
