<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Back shortly') }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <main class="auth-shell">
        <div class="auth-card">
            <p class="kicker">{{ __('Maintenance') }}</p>
            <h1 style="margin:0 0 8px;font-size:1.5rem;letter-spacing:-0.03em;">{{ __('Back shortly') }}</h1>
            <p class="muted">{{ $message }}</p>
            <p class="muted" style="font-size:0.86rem;">
                {{ __('Payments already in flight are unaffected: providers confirm them to us directly, whether or not the site is open.') }}
            </p>
        </div>
    </main>
</body>
</html>
