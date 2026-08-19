@extends('layouts.public')
@section('title', $page->seo_title ?: $page->title)
{{-- Only emit the section when there is something to say. Blade reads
     @section('x', null) as "open a buffer and wait for @endsection", which
     never comes, so the buffer is never closed. It also lets the layout's own
     default description stand rather than blanking it. --}}
@if(filled($page->seo_description))
    @section('meta_description', $page->seo_description)
@endif
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Company') }}</p>
    <h1>{{ $page->title }}</h1>
</div>
<div class="wrap section prose" style="padding-top:8px;">
    <div>{!! nl2br(e($page->body)) !!}</div>
</div>
@endsection
