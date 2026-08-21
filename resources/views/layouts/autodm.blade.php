<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('AutoDM')) · {{ config('app.name') }}</title>
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
            <a class="brand" href="{{ route('autodm.index') }}">{{ config('app.name') }} <small>AutoDM</small></a>
            <span class="spacer"></span>
            @include('partials.theme-toggle')
            @include('partials.nav-toggle', ['controls' => 'app-nav'])
        </header>

        {{--
            AutoDM's own navigation. Deliberately short: somebody here came for
            Instagram automation, and putting invoices and disputes in front of
            them would be the marketplace leaking into a different product.
        --}}
        <aside class="side" id="app-nav">
            <div class="side-head">
                <a class="brand" href="{{ route('autodm.index') }}">{{ __('AutoDM') }}</a>
                @include('partials.theme-toggle')
            </div>

            <nav class="side-nav" aria-label="{{ __('AutoDM') }}">
                <a class="{{ request()->routeIs('autodm.index') ? 'active' : '' }}"
                   href="{{ route('autodm.index') }}">{{ __('Overview') }}</a>
                <a class="{{ request()->routeIs('autodm.create') ? 'active' : '' }}"
                   href="{{ route('autodm.create') }}">{{ __('New automation') }}</a>
                <a href="{{ route('app.instagram') }}">{{ __('Instagram account') }}</a>
            </nav>

            {{-- The way back to the rest of the product, said plainly. One
                 account covers both, so this is a change of place, not of
                 identity — and it should not read as signing out. --}}
            <div class="side-foot">
                <p class="muted">{{ __('Same account, different product.') }}</p>
                <a class="btn secondary" href="{{ \App\Support\Host::urlFor('app', 'dashboard') }}">
                    {{ __('Creator workspace') }}
                </a>

                @auth
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn secondary" type="submit">{{ __('Sign out') }}</button>
                    </form>
                @endauth
            </div>
        </aside>

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
        </div>
    </div>

    <script src="{{ \App\Support\Asset::url('js/site.js') }}" defer></script>
</body>
</html>
