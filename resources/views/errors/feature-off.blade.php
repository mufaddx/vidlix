@extends('layouts.public')
@section('title', __('Temporarily unavailable'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Paused') }}</p>
    <h1>{{ __('Temporarily unavailable') }}</h1>
    <p class="lede">{{ $message }}</p>
</div>
@endsection
