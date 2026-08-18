@extends('layouts.app')
@section('title', __('Brand'))
@section('content')
<h1>{{ __('Brand verification') }}</h1>
<p class="muted">{{ $profile?->verification_status }}</p>
<form class="form" method="post" action="{{ route('app.brand.save') }}">
    @csrf
    <label>{{ __('Company') }}<input name="company_name" value="{{ $profile?->company_name }}" required></label>
    <label>{{ __('Website') }}<input name="website" value="{{ $profile?->website }}"></label>
    <label>{{ __('Industry') }}<input name="industry" value="{{ $profile?->industry }}"></label>
    <button class="btn" type="submit">{{ __('Save') }}</button>
</form>
@endsection
