{{--
    Authentication shell. Self-contained dark theme: it shares no stylesheet
    with the marketplace or the admin panel, so changing one can never quietly
    break the screen a stranger sees first.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/auth.css') }}">
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <a class="auth-brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
            @yield('content')
        </div>
    </div>

    @stack('modals')
        <script src="{{ \App\Support\Asset::url('js/site.js') }}" defer></script>
    <script src="{{ \App\Support\Asset::url('js/auth.js') }}" defer></script>
</body>
</html>
