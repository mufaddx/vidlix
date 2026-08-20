@extends('layouts.app')
@section('title', __('Public page'))
@section('content')
<h1>{{ __('Public page studio') }}</h1>
<p class="muted">{{ __('Drafts stay private until you publish. This is the link to share:') }}</p>

@php($publicUrl = \App\Support\PublicUrl::profile($profile->username))
<div class="card" style="margin-bottom:16px">
    <p class="kicker">{{ __('Your public link') }}</p>
    <p class="stat" style="font-size:18px;word-break:break-all">{{ $publicUrl }}</p>

    @if($profile->isPublished())
        <p class="muted">{{ __('Live now — anyone with this link can see your page.') }}</p>
    @else
        {{-- Shown before publishing too: people want to know the address they
             are going to get before they commit to it. --}}
        <p class="muted">{{ __('Not published yet. This will be the address once you publish.') }}</p>
    @endif

    <p>
        <button class="btn secondary" type="button" data-copy="{{ $publicUrl }}">{{ __('Copy public link') }}</button>
        <a class="btn secondary" href="{{ \App\Support\PublicUrl::contact($profile->username) }}">{{ __('Open contact form') }}</a>
        @if($profile->isPublished())
            <a class="btn secondary" href="{{ $publicUrl }}">{{ __('View public page') }}</a>
        @endif
    </p>
</div>
<form class="form" method="post" action="{{ route('creator.public-page.draft') }}">
    @csrf
    <label>{{ __('Hero title') }}<input name="hero_title" value="{{ $profile->publicPage->draft_payload['hero_title'] ?? '' }}" required></label>
    <label>{{ __('Hero subtitle') }}<input name="hero_subtitle" value="{{ $profile->publicPage->draft_payload['hero_subtitle'] ?? '' }}"></label>
    <label>{{ __('Description') }}<textarea name="description">{{ $profile->publicPage->draft_payload['description'] ?? '' }}</textarea></label>
    <label>{{ __('CTA text') }}<input name="cta_text" value="{{ $profile->publicPage->draft_payload['cta_text'] ?? 'Work with me' }}" required></label>
    <label>{{ __('Bio') }}<textarea name="bio">{{ $profile->bio }}</textarea></label>
    <button class="btn secondary" type="submit">{{ __('Save draft') }}</button>
</form>
<form method="post" action="{{ route('creator.public-page.publish') }}" style="margin:16px 0;">
    @csrf
    <button class="btn" type="submit">{{ __('Publish') }}</button>
</form>
<h2>{{ __('Social links') }}</h2>
<form class="form" method="post" action="{{ route('creator.public-page.social') }}">
    @csrf
    <label>{{ __('Platform') }}
        <select name="social_platform_id">
            @foreach($platforms as $platform)
                <option value="{{ $platform->id }}">{{ $platform->name }}</option>
            @endforeach
        </select>
    </label>
    <label>{{ __('Mode') }}
        <select name="input_mode">
            <option value="username">{{ __('Username') }}</option>
            <option value="full_url">{{ __('Full URL') }}</option>
        </select>
    </label>
    <label>{{ __('Value') }}<input name="input_value" required></label>
    <button class="btn secondary" type="submit">{{ __('Add social') }}</button>
</form>
<ul>
    @foreach($profile->socialLinks as $link)
        <li>{{ $link->platform->name }}: <a href="{{ $link->resolved_url }}">{{ $link->resolved_url }}</a></li>
    @endforeach
</ul>
<h2>{{ __('Contact form') }}</h2>
<form class="form" method="post" action="{{ route('creator.public-page.form') }}">
    @csrf
    <label>{{ __('Form title') }}<input name="form_title" value="{{ $profile->publicPage->contactForm?->publishedVersion()?->schema_json['title'] ?? '' }}" required></label>
    <label>{{ __('Form description') }}<textarea name="form_description">{{ $profile->publicPage->contactForm?->publishedVersion()?->schema_json['description'] ?? '' }}</textarea></label>
    <button class="btn secondary" type="submit">{{ __('Publish form version') }}</button>
</form>
@endsection
