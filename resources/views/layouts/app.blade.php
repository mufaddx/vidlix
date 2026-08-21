<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Workspace')) · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/app.css') }}">
    @include('partials.theme-head')
</head>
<body>
    <a class="skip" href="#main">{{ __('Skip to content') }}</a>
    <div class="app-shell" id="app-shell">
        <header class="app-top">
            <a class="brand" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
            <span class="spacer"></span>
            @include('partials.theme-toggle')
            @include('partials.nav-toggle')
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
            {{-- The marketing footer belongs to the public site. Somebody
                 signed in is working, not being sold to. --}}
            @guest
                @include('partials.public-footer')
            @endguest
        </div>
    </div>
    <script src="{{ \App\Support\Asset::url('js/site.js') }}" defer></script>
</body>
</html>
