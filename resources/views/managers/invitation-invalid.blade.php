@extends('layouts.auth')
@section('title', __('Invitation not valid'))
@section('content')
<h1>{{ __('This invitation is not valid') }}</h1>
<p class="muted">{{ __('It may have expired, already been used, or been withdrawn by the account holder. Ask them to send a new one.') }}</p>
<a class="btn secondary" href="{{ route('home') }}">{{ __('Back to Vidlix') }}</a>
@endsection
