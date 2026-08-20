@extends('layouts.public')
@section('title', $editor->display_name.' — '.__('Editor on Vidlix'))
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($editor->bio ?? __('Work with :name on Vidlix.', ['name' => $editor->display_name])), 155))
@section('og_type', 'profile')
@section('og_title', $editor->display_name)
@section('canonical', \App\Support\PublicUrl::profile($editor->username))
@section('content')

@php($publicUrl = \App\Support\PublicUrl::profile($editor->username))
<section class="wrap page-hero">
    <p class="kicker">{{ __('Verified editor') }}</p>
    <h1>{{ $editor->display_name }}</h1>
    <p class="lede">&#64;{{ $editor->username }}</p>
    <p>
        <button class="btn secondary" type="button" data-copy="{{ $publicUrl }}">{{ __('Copy link') }}</button>
        <a class="btn secondary" href="{{ \App\Support\PublicUrl::contact($editor->username) }}">{{ __('Contact') }}</a>
    </p>
</section>

<section class="wrap section">
    <div class="profile-grid">
        <div>
            @if($editor->bio)<p>{{ $editor->bio }}</p>@endif

            @if($categories->count())
                <h2>{{ __('Works on') }}</h2>
                <div class="chips">
                    @foreach($categories as $category)
                        <span class="chip">{{ $category->name }}</span>
                    @endforeach
                </div>
            @endif

            @if($editor->starting_price_minor)
                <p class="stat">{{ __('From') }} ₹{{ number_format($editor->starting_price_minor / 100, 2) }}</p>
            @endif
            @if($editor->availability)<p class="muted">{{ __('Availability') }}: {{ $editor->availability }}</p>@endif
        </div>

        <div class="hero-card">
            <h2>{{ __('Get in touch') }}</h2>
            @if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
            @if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

            @if($editor->accepts_inquiries)
                <form class="form" method="post" action="{{ route('profile.contact.submit', $editor->username) }}">@csrf
                    <label for="name">{{ __('Your name') }}</label>
                    <input id="name" name="name" required>

                    <label for="email">{{ __('Your email') }}</label>
                    <input id="email" name="email" type="email" required>

                    <label for="company">{{ __('Company (optional)') }}</label>
                    <input id="company" name="company">

                    <label for="subject">{{ __('Subject') }}</label>
                    <input id="subject" name="subject" required>

                    <label for="message">{{ __('What do you need edited?') }}</label>
                    <textarea id="message" name="message" required></textarea>

                    <label class="hp" for="{{ $honeypot }}">{{ __('Leave this empty') }}</label>
                    <input class="hp" id="{{ $honeypot }}" name="{{ $honeypot }}" tabindex="-1" autocomplete="off">

                    @include('partials.turnstile')

                    <button class="btn" type="submit">{{ __('Send enquiry') }}</button>
                </form>
                <p class="muted">{{ __('No account needed. Your message goes to their Vidlix inbox, and their reply reaches you by email.') }}</p>
            @else
                <p class="muted">{{ __('This editor is not taking new enquiries right now.') }}</p>
            @endif
        </div>
    </div>
</section>
@endsection
