@extends('layouts.app')
@section('title', __('Portfolio'))
@section('content')
<h1>{{ __('Portfolio') }}</h1>
<form method="post" action="{{ route('app.portfolio.store') }}">@csrf<input name="title" required><input name="url" placeholder="https://"><textarea name="description"></textarea><button class="btn" type="submit">{{ __('Add') }}</button></form>
@foreach($items as $i)<p>{{ $i->title }} · {{ $i->url }}</p>@endforeach
@if($items->isEmpty())<p class="muted">{{ __('Add your first project') }}</p>@endif
@endsection
