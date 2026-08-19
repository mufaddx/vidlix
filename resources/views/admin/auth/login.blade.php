<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Staff sign in') }} · {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="a-login">
    <div class="a-login-card">
        <h1>{{ config('app.name') }} {{ __('Admin') }}</h1>
        <p class="sub">{{ __('Staff and employee sign in.') }}</p>

        @if($errors->any())<div class="a-notice danger">{{ $errors->first() }}</div>@endif
        @if(session('status'))<div class="a-notice info">{{ session('status') }}</div>@endif

        <form class="a-form" method="post" action="{{ route('admin.login.store') }}">@csrf
            <div>
                <label for="email">{{ __('Work email') }}</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}">
            </div>
            <div>
                <label for="password">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password">
            </div>
            <label class="a-check">
                <input type="checkbox" name="remember" value="1">
                <span>{{ __('Remember this device') }}</span>
            </label>
            <button class="a-btn" type="submit">{{ __('Sign in') }}</button>
        </form>

        <p class="a-hint" style="margin-top:16px;color:var(--a-muted);font-size:12px;">
            {{ __('This is not the member sign in. A member account without staff access cannot sign in here.') }}
        </p>
    </div>
</div>
</body>
</html>
