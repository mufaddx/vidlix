@extends('layouts.public')
@section('title', __('Journal'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Company') }}</p>
    <h1>{{ __('Journal') }}</h1>
    <p class="lede">{{ __('Product notes and operating principles. CMS-managed, no invented metrics.') }}</p>
</div>
<div class="wrap section" style="padding-top:12px;">
    <div class="grid">
        @forelse($posts as $post)
            <article class="card">
                <p class="kicker">{{ optional($post->published_at)->toFormattedDateString() ?? $post->published_at }}</p>
                <h2><a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a></h2>
            </article>
        @empty
            <p class="muted">{{ __('No published posts.') }}</p>
        @endforelse
    </div>
    {{ $posts->links() }}
</div>
@endsection
