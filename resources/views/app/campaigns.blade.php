@extends('layouts.app')
@section('title', __('Campaigns'))
@section('content')
<h1>{{ __('Campaigns') }}</h1>
<form class="form" method="post" action="{{ route('app.campaigns.store') }}">
    @csrf
    <label>{{ __('Name') }}<input name="name" required></label>
    <label>{{ __('Objective') }}<input name="objective"></label>
    <label>{{ __('Platform') }}<input name="platform"></label>
    <label>{{ __('Budget (paise)') }}<input type="number" name="budget_minor"></label>
    <label>{{ __('Brief') }}<textarea name="brief"></textarea></label>
    <button class="btn" type="submit">{{ __('Save draft') }}</button>
</form>
<h2>{{ __('Mine') }}</h2>
@foreach($mine as $c)
    <article class="card" style="margin-bottom:8px;">
        <strong>{{ $c->name }}</strong> · {{ $c->status }}
        @if($c->status === 'draft')
            <form method="post" action="{{ route('app.campaigns.submit', $c) }}">@csrf<button class="btn secondary" type="submit">{{ __('Submit review') }}</button></form>
        @endif
    </article>
@endforeach
<h2>{{ __('Open') }}</h2>
@foreach($open as $c)
    <article class="card" style="margin-bottom:8px;">
        <strong>{{ $c->name }}</strong>
        <form method="post" action="{{ route('app.campaigns.apply', $c) }}">
            @csrf
            <input name="proposed_fee_minor" placeholder="fee paise" required>
            <textarea name="message" required></textarea>
            <button class="btn" type="submit">{{ __('Apply') }}</button>
        </form>
    </article>
@endforeach
@endsection
