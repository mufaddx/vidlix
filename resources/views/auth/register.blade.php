@extends('layouts.auth')
@section('title', __('Join'))
@section('content')
<h1>{{ __('Create account') }}</h1>
<form class="form" method="post" action="{{ url('/register') }}">
    @csrf
    <label>{{ __('Name') }} <input name="name" value="{{ old('name') }}" required></label>
    <label>{{ __('Email') }} <input type="email" name="email" value="{{ old('email') }}" required></label>
    <label>{{ __('Mobile') }} <input name="mobile" value="{{ old('mobile') }}" required></label>
    <label>{{ __('Password') }} <input type="password" name="password" required></label>
    <label>{{ __('Confirm password') }} <input type="password" name="password_confirmation" required></label>
    <label>{{ __('Start as') }}
        <select name="role">
            <option value="creator">{{ __('Creator') }}</option>
            <option value="editor">{{ __('Editor') }}</option>
            <option value="brand">{{ __('Brand') }}</option>
            <option value="manager">{{ __('Manager') }}</option>
        </select>
    </label>
    <button class="btn" type="submit">{{ __('Register') }}</button>
</form>
<p><a href="{{ route('login') }}">{{ __('Already have an account?') }}</a></p>
@endsection
