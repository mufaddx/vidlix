@extends('layouts.public')
@section('title', $title)
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Coming soon') }}</p>
    <h1>{{ $title }}</h1>
    <p class="lede">{{ $body }}</p>
</div>
@endsection
