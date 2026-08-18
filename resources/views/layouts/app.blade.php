<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Workspace')) · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <a class="skip" href="#main">{{ __('Skip to content') }}</a>
    <div class="app-shell" id="app-shell">
        <header class="app-top">
            <a class="brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
            <button class="nav-toggle" type="button" aria-expanded="false" data-nav-toggle>{{ __('Menu') }}</button>
        </header>
        @include('partials.app-sidebar')
        <div class="workspace">
            <main class="main" id="main">
                @if ($errors->any())
                    <p class="error">{{ $errors->first() }}</p>
                @endif
                @if (session('status'))
                    <p class="flash">{{ session('status') }}</p>
                @endif
                @yield('content')
            </main>
            @include('partials.public-footer')
        </div>
    </div>
    <script>
        const shell = document.getElementById('app-shell');
        const toggle = document.querySelector('[data-nav-toggle]');
        if (shell && toggle) {
            toggle.addEventListener('click', () => {
                const open = shell.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
    </script>
</body>
</html>
