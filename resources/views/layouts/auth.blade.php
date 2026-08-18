<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <a class="brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
            @if ($errors->any())
                <p class="error">{{ $errors->first() }}</p>
            @endif
            @if (session('status'))
                <p class="flash">{{ session('status') }}</p>
            @endif
            @yield('content')
        </div>
    </div>
</body>
</html>
