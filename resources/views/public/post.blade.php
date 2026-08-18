@extends('layouts.public')
@section('title', $post->title)
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Journal') }}</p>
    <h1>{{ $post->title }}</h1>
</div>
<div class="wrap section prose" style="padding-top:8px;">
    <div>{!! nl2br(e($post->body)) !!}</div>
    <p style="margin-top:28px;"><a href="{{ route('blog.index') }}">{{ __('Back to journal') }}</a></p>
</div>
@endsection
