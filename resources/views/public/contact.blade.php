@extends('layouts.public')
@section('title', __('Contact :name', ['name' => $profile->display_name]))
@section('meta_description', __('Send :name a message on Vidlix. No account needed.', ['name' => $profile->display_name]))
@section('content')

<section class="wrap page-hero">
    <p class="kicker">{{ $kind === 'creator' ? __('Creator') : __('Editor') }}</p>
    <h1>{{ $form['title'] ?? __('Contact :name', ['name' => $profile->display_name]) }}</h1>
    <p class="lede">
        <a href="{{ \App\Support\PublicUrl::profile($profile->username) }}">&#64;{{ $profile->username }}</a>
    </p>
    @if(!empty($form['description']))
        <p class="lede">{{ $form['description'] }}</p>
    @endif
</section>

<section class="wrap section" style="padding-top:12px;">
    <div class="hero-card" style="max-width:640px;margin-inline:auto">

        {{-- Success. Shown instead of the form, so nobody sends the same
             message twice wondering whether the first one landed. --}}
        @if(session('inquiry_sent'))
            <h2>{{ __('Message sent') }}</h2>
            <p>{{ $form['success_message'] ?? __('Thanks — :name has your message and will reply by email.', ['name' => $profile->display_name]) }}</p>
            <p class="muted">{{ __('The reply comes from Vidlix on their behalf, so keep an eye on your inbox and your spam folder.') }}</p>
            <p><a class="btn secondary" href="{{ \App\Support\PublicUrl::profile($profile->username) }}">{{ __('Back to profile') }}</a></p>
        @else
            @if($errors->any())
                <p class="error">{{ $errors->first() }}</p>
            @endif

            <form class="form" method="post" action="{{ route('profile.contact.submit', $profile->username) }}">
                @csrf

                @include('partials.inquiry-fields')

                <label class="hp" for="{{ $honeypot }}">{{ __('Leave this empty') }}</label>
                <input class="hp" id="{{ $honeypot }}" name="{{ $honeypot }}" tabindex="-1" autocomplete="off">

                @include('partials.turnstile')

                <button class="btn" type="submit">{{ $form['submit_text'] ?? __('Send message') }}</button>
            </form>

            <p class="muted">{{ __('No account needed. Your message goes to their Vidlix inbox, and their reply reaches you by email.') }}</p>
        @endif
    </div>
</section>
@endsection
