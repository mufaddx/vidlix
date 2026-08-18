@extends('layouts.app')
@section('title', __('Support'))
@section('content')
<h1>{{ __('Support') }}</h1>
<form method="post" action="{{ route('app.tickets.store') }}">@csrf<input name="category" required><input name="subject" required><textarea name="body" required></textarea><button class="btn" type="submit">{{ __('Open ticket') }}</button></form>
@foreach($items as $t)<p>{{ $t->status }} · {{ $t->subject }}</p>@endforeach
@endsection
