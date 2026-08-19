{{--
    Admin shell. Deliberately shares nothing with the member workspace: no
    member navigation, no marketplace theme, no account switcher. Staff should
    never be one misclick from acting as a member.
--}}
@php
    $user = auth()->user();
    $sections = \App\Support\AdminNavigation::visibleFor($user);
    $current = request()->query('section') ?: \App\Support\AdminNavigation::sectionForRoute(request()->route()?->getName());
    $current = isset($sections[$current]) ? $current : array_key_first($sections);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Admin')) · {{ config('app.name') }} {{ __('Admin') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="a-shell">
    <aside class="a-side">
        <a class="a-brand" href="{{ route('admin.dashboard') }}">
            {{ config('app.name') }}
            <small>{{ __('Admin panel') }}</small>
        </a>

        <nav class="a-sections" aria-label="{{ __('Admin sections') }}">
            @foreach($sections as $key => $section)
                <div class="a-section">
                    <a class="a-section-link {{ $current === $key ? 'is-active' : '' }}"
                       href="{{ route($section['items'][0]['route']) }}?section={{ $key }}">{{ $section['label'] }}</a>

                    {{-- The section's pages open directly beneath it, so what you
                         clicked and what it contains stay together. --}}
                    @if($current === $key)
                        <div class="a-nav">
                            @foreach($section['items'] as $item)
                                <a class="{{ request()->routeIs($item['route']) ? 'is-active' : '' }}"
                                   href="{{ route($item['route']) }}?section={{ $key }}">{{ $item['label'] }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </nav>

        <div class="a-side-foot">
            {{ $user->name }}<br>
            {{ $user->isSuperAdmin() ? __('Super admin') : ($user->employee?->employee_code ?? __('Staff')) }}
            <form method="post" action="{{ route('admin.logout') }}">@csrf
                <button type="submit">{{ __('Sign out of admin') }}</button>
            </form>
        </div>
    </aside>

    <main class="a-main">
        <header class="a-head">
            <h1>@yield('heading', View::getSection('title', __('Admin')))</h1>
            @hasSection('subheading')<p>@yield('subheading')</p>@endif
        </header>

        <div class="a-body">
            @if($errors->any())
                <div class="a-notice danger">{{ $errors->first() }}</div>
            @endif
            @if(session('status'))
                <div class="a-notice ok">{{ session('status') }}</div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
