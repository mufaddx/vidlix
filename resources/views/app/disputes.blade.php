@extends('layouts.app')
@section('title', __('Disputes'))
@section('content')
<h1>{{ __('Disputes') }}</h1>
<form method="post" action="{{ route('app.disputes.store') }}">
    @csrf
    <input name="project_id" placeholder="project id" required>
    <input name="reason" placeholder="reason" required>
    <textarea name="statement" required></textarea>
    <button class="btn" type="submit">{{ __('Open') }}</button>
</form>
@foreach($items as $d)<p>{{ $d->dispute_uuid }} · {{ $d->status }} · {{ $d->reason }}</p>@endforeach
@endsection
