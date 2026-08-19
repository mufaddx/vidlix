<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Admin') }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="a-login">
    <div class="a-login-card">
        <h1>{{ __('Already signed in') }}</h1>
        <p class="sub">{{ __('You are signed in as :name.', ['name' => auth()->user()->name]) }}</p>
        <a class="a-btn" href="{{ route('admin.dashboard') }}">{{ __('Go to the admin panel') }}</a>
        <form method="post" action="{{ route('admin.logout') }}" style="margin-top:12px">@csrf
            <button class="a-btn ghost" type="submit">{{ __('Sign out') }}</button>
        </form>
    </div>
</div>
</body>
</html>
