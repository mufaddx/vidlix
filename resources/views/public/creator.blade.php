@extends('layouts.public')
@section('title', $page['hero_title'] ?? $creator->display_name)
@section('content')
<div class="wrap section profile-grid">
    <div>
        <p class="kicker">{{ __('Creator') }}</p>
        <h1>{{ $page['hero_title'] ?? $creator->display_name }}</h1>
        <p class="muted">{{ $page['hero_subtitle'] ?? '@'.$creator->username }}</p>
        <p>{{ $page['description'] ?? $creator->bio }}</p>
        <p style="display:flex;gap:10px;flex-wrap:wrap;">
            @foreach($links as $link)
                <a class="btn secondary" href="{{ $link->resolved_url }}" rel="noopener noreferrer" target="_blank">{{ $link->platform->name }}</a>
            @endforeach
        </p>
    </div>
    <aside class="hero-card">
        @if(session('inquiry_sent'))
            <div class="flash">{{ $form['success_message'] ?? __('Inquiry received.') }}</div>
        @else
            <h2>{{ $form['title'] ?? __('Let’s work together') }}</h2>
            <p class="muted">{{ $form['description'] ?? '' }}</p>
            <form class="form" method="post" action="{{ route('creators.inquire', $creator->username) }}">
                @csrf
                <div class="hp" aria-hidden="true">
                    <label>{{ __('Company website') }} <input type="text" name="{{ config('vidlix.public_form_honeypot') }}" tabindex="-1" autocomplete="off"></label>
                </div>
                @foreach(($form['fields'] ?? []) as $field)
                    <label>
                        {{ $field['label'] }}{{ ($field['required'] ?? false) ? ' *' : '' }}
                        @if(($field['type'] ?? 'text') === 'textarea')
                            <textarea name="{{ $field['key'] }}" @required($field['required'] ?? false)>{{ old($field['key']) }}</textarea>
                        @else
                            <input type="{{ $field['type'] === 'email' ? 'email' : 'text' }}" name="{{ $field['key'] }}" value="{{ old($field['key']) }}" @required($field['required'] ?? false)>
                        @endif
                        @error($field['key'])<span class="error">{{ $message }}</span>@enderror
                    </label>
                @endforeach
                <button class="btn" type="submit">{{ __('Send inquiry') }}</button>
            </form>
        @endif
    </aside>
</div>
@endsection
