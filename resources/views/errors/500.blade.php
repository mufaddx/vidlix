@extends('layouts.public')
@section('title', __('Something went wrong'))
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('500') }}</p>
    <h1>{{ __('Something went wrong at our end') }}</h1>
    {{-- No exception text, no stack trace, no hint at what broke. The request
         id is enough for us to find it and useless to anybody else. --}}
    <p class="lede">{{ __('This one is ours, not yours. Nothing you did caused it, and it has been recorded.') }}</p>
    @if($id = \App\Support\RequestId::get())
        <p class="muted">{{ __('If you contact support, quote this reference: :id', ['id' => $id]) }}</p>
    @endif
    <p><a class="btn" href="{{ route('home') }}">{{ __('Go to the home page') }}</a></p>
</div>
@endsection
