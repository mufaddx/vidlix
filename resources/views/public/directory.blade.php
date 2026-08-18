@extends('layouts.public')
@section('title', $title)
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Directory') }}</p>
    <h1>{{ $title }}</h1>
    <p class="lede">{{ __('Verified profiles only. Nothing is invented for display.') }}</p>
</div>
<div class="wrap section" style="padding-top:12px;">
    <div class="grid">
        @forelse($items as $item)
            <a class="card" href="{{ $href($item) }}">
                <span class="avatar" aria-hidden="true">{{ strtoupper(substr($name($item), 0, 1)) }}</span>
                <strong>{{ $name($item) }}</strong>
                <p class="muted">{{ $meta($item) }}</p>
            </a>
        @empty
            <p class="muted">{{ $empty }}</p>
        @endforelse
    </div>
    {{ $items->links() }}
</div>
@endsection
