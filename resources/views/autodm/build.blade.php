@extends('layouts.app')
@section('title', $automation ? __('Edit automation') : __('New automation'))
@section('content')

<p><a href="{{ route('autodm.index') }}">{{ __('Back to AutoDM') }}</a></p>

<h1>{{ $automation ? __('Edit automation') : __('New automation') }}</h1>
<p class="muted">{{ __('Select a post, say what to watch for, write the reply. Nothing runs until you activate it on the next screen.') }}</p>

@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

<form class="form" method="post"
      action="{{ $automation ? route('autodm.update', $automation->uuid) : route('autodm.store') }}">
    @csrf

    <h2>{{ __('1 · Select') }}</h2>

    <label for="name">{{ __('Name it, for your own reference') }}</label>
    <input id="name" name="name" maxlength="120"
           value="{{ old('name', $automation?->name) }}"
           placeholder="{{ __('Reply to people asking for the link') }}">

    <label for="instagram_media_id">{{ __('Which post?') }}</label>
    <select id="instagram_media_id" name="instagram_media_id">
        {{-- Every post is the broader and more dangerous default, so it is
             offered explicitly rather than assumed when nothing is chosen. --}}
        <option value="">{{ __('Every post on the account') }}</option>
        @foreach($media as $medium)
            <option value="{{ $medium->id }}"
                @selected(old('instagram_media_id', $automation?->instagram_media_id) == $medium->id)>
                {{ $medium->media_type }} · {{ $medium->published_at?->toDateString() }} · {{ \Illuminate\Support\Str::limit($medium->caption_excerpt, 60) }}
            </option>
        @endforeach
    </select>

    @if($media->isEmpty())
        <p class="muted">{{ __('No media has been refreshed yet, so only the whole-account option is available.') }}</p>
    @endif

    <h2>{{ __('2 · Trigger') }}</h2>

    <label for="trigger_type">{{ __('When should it fire?') }}</label>
    <select id="trigger_type" name="trigger_type">
        <option value="keywords" @selected(old('trigger_type', $version?->trigger_type ?? 'keywords') === 'keywords')>
            {{ __('When a comment contains certain words') }}
        </option>
        <option value="any_comment" @selected(old('trigger_type', $version?->trigger_type) === 'any_comment')>
            {{ __('On any comment') }}
        </option>
    </select>

    <label for="keywords">{{ __('Words or phrases, one per line') }}</label>
    <textarea id="keywords" name="keywords" placeholder="link&#10;send me the link&#10;price">{{ old('keywords', implode("\n", $version?->keywordList() ?? [])) }}</textarea>
    <p class="muted">{{ __('Capitals and accents are ignored, because people do not type carefully in comments.') }}</p>

    <label class="checkbox">
        <input type="checkbox" name="whole_word" value="1" @checked(old('whole_word', $version?->whole_word))>
        {{ __('Match whole words only') }}
    </label>
    <p class="muted">{{ __('With this on, “art” stops matching “start” and “party”.') }}</p>

    <h2>{{ __('3 · Action') }}</h2>

    @php($publicAllowed = $capabilities['public_reply']['allowed'] ?? false)
    @php($privateAllowed = $capabilities['private_reply']['allowed'] ?? false)

    <label class="checkbox">
        <input type="checkbox" name="public_reply_enabled" value="1"
               @checked(old('public_reply_enabled', $version?->public_reply_enabled))
               @disabled(! $publicAllowed)>
        {{ __('Reply publicly under the comment') }}
    </label>
    @unless($publicAllowed)
        <p class="muted">{{ $capabilities['public_reply']['reason'] ?? __('Not available on this account.') }}</p>
    @endunless

    <label for="public_reply_text">{{ __('Public reply') }}</label>
    <textarea id="public_reply_text" name="public_reply_text" maxlength="1000">{{ old('public_reply_text', $version?->public_reply_text) }}</textarea>

    <label class="checkbox">
        <input type="checkbox" name="private_reply_enabled" value="1"
               @checked(old('private_reply_enabled', $version?->private_reply_enabled))
               @disabled(! $privateAllowed)>
        {{ __('Send a private reply') }}
    </label>
    @unless($privateAllowed)
        {{-- Disabled with the reason rather than hidden. Somebody who came here
             expecting to send DMs deserves to know why they cannot, and that
             the rest of the automation still works without it. --}}
        <p class="muted">{{ $capabilities['private_reply']['reason'] ?? __('Not available on this account.') }}</p>
    @endunless

    <label for="private_reply_text">{{ __('Private reply') }}</label>
    <textarea id="private_reply_text" name="private_reply_text" maxlength="1000">{{ old('private_reply_text', $version?->private_reply_text) }}</textarea>

    <label for="private_reply_url">{{ __('A link to include') }} <span class="muted">{{ __('(optional)') }}</span></label>
    <input id="private_reply_url" name="private_reply_url" maxlength="2000"
           value="{{ old('private_reply_url', $version?->private_reply_url) }}"
           placeholder="https://">

    <button class="btn" type="submit">{{ __('Save and review') }}</button>
</form>
@endsection
