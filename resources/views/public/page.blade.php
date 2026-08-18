@extends('layouts.public')
@section('title', $page->seo_title ?: $page->title)
@section('meta_description', $page->seo_description)
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Company') }}</p>
    <h1>{{ $page->title }}</h1>
</div>
<div class="wrap section prose" style="padding-top:8px;">
    <div>{!! nl2br(e($page->body)) !!}</div>
</div>
@endsection
