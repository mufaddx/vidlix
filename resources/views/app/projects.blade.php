@extends('layouts.app')
@section('title', __('Projects'))
@section('content')
<h1>{{ __('Projects') }}</h1>
<form class="form" method="post" action="{{ route('app.projects.store') }}">
    @csrf
    <label>{{ __('Name') }}<input name="name" required></label>
    <label>{{ __('Counterparty user id') }}<input name="counterparty_user_id" required></label>
    <label>{{ __('Total paise') }}<input type="number" name="total_amount_minor" required></label>
    <label>{{ __('Advance paise') }}<input type="number" name="advance_amount_minor"></label>
    <label>{{ __('Deadline') }}<input type="date" name="deadline"></label>
    <button class="btn" type="submit">{{ __('Create draft') }}</button>
</form>
@foreach($projects as $p)
    <p><a href="{{ route('app.projects.show', $p) }}">{{ $p->name }}</a> · {{ $p->status }}</p>
@endforeach
@if($projects->isEmpty())<p class="muted">{{ __('No active projects') }}</p>@endif
@endsection
