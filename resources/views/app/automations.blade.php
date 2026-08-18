@extends('layouts.app')
@section('title', __('Instagram automation'))
@section('content')
<h1>{{ __('Instagram automation') }}</h1>
<p class="banner">{{ $configured ? __('Meta configured.') : __('Unsupported until official Meta messaging permissions exist.') }}</p>
<form method="post" action="{{ route('app.automations.store') }}">@csrf<input name="name" required><input name="keywords" placeholder="keywords"><button class="btn" type="submit">{{ __('Save') }}</button></form>
@foreach($items as $i)<p>{{ $i->name }} · {{ $i->status }}</p>@endforeach
@endsection
