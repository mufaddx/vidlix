<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name').' — Creator collaboration marketplace')</title>
    <meta name="description" content="@yield('meta_description', 'Vidlix is a professional marketplace for creators, editors, and brands — from discovery to settlement.')">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/app.css') }}">
    @include('partials.theme-head')
</head>
<body>
    <a class="skip" href="#main">{{ __('Skip to content') }}</a>
    <header class="site-header">
        <div class="wrap nav" id="site-nav">
            <a class="brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
            <div class="nav-actions">
                @include('partials.theme-toggle')
                @include('partials.nav-toggle', ['controls' => 'primary-nav'])
            </div>
            <nav class="nav-links" id="primary-nav" aria-label="{{ __('Primary') }}">
                <a href="{{ route('creators.index') }}">{{ __('Creators') }}</a>
                <a href="{{ route('editors.index') }}">{{ __('Editors') }}</a>
                <a href="{{ route('brands.index') }}">{{ __('Brands') }}</a>
                <a href="{{ route('campaigns.index') }}">{{ __('Campaigns') }}</a>
                <a href="{{ route('pages.show', 'how-it-works') }}">{{ __('How it works') }}</a>
                <a href="{{ route('pricing') }}">{{ __('Pricing') }}</a>
                <a href="{{ route('blog.index') }}">{{ __('Journal') }}</a>
                @auth
                    <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}">{{ __('Login') }}</a>
                    <a class="btn" href="{{ route('register') }}">{{ __('Join') }}</a>
                @endauth
            </nav>
        </div>
    </header>
    {{-- Outside the header on purpose. The header has a backdrop-filter, which
         makes it the containing block for fixed-position descendants: in there
         this covered only the header's own 76px and taps went straight through
         to the page behind the open drawer. --}}
    <div class="nav-scrim" data-nav-close hidden></div>
    <main id="main">
        @yield('content')
    </main>
    @include('partials.public-footer')
    <script src="{{ \App\Support\Asset::url('js/site.js') }}" defer></script>
</body>
</html>
