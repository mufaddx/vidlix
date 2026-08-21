@extends('layouts.autodm')
@section('title', __('Review automation'))
@section('content')

<p><a href="{{ route('autodm.edit', $automation->uuid) }}">{{ __('Back to editing') }}</a></p>

<h1>{{ __('Review') }}</h1>
<p class="muted">{{ __('This is exactly what will happen. Nothing has been switched on yet.') }}</p>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

<div class="card" style="margin-bottom:16px">
    <dl class="a-facts">
        <dt>{{ __('Name') }}</dt><dd>{{ $automation->name }}</dd>
        <dt>{{ __('Account') }}</dt><dd>&#64;{{ $account?->username ?? __('Not connected') }}</dd>
        <dt>{{ __('Applies to') }}</dt>
        <dd>{{ $automation->instagram_media_id ? __('One post') : __('Every post on the account') }}</dd>

        <dt>{{ __('Trigger') }}</dt>
        <dd>
            @if($version?->trigger_type === 'any_comment')
                {{ __('Any comment') }}
            @else
                {{ implode(' · ', $version?->keywordList() ?? []) }}
                @if($version?->whole_word)<span class="muted">{{ __('(whole words)') }}</span>@endif
            @endif
        </dd>

        @if($version?->public_reply_enabled)
            <dt>{{ __('Public reply') }}</dt><dd>{{ $version->public_reply_text }}</dd>
        @endif
        @if($version?->private_reply_enabled)
            <dt>{{ __('Private reply') }}</dt><dd>{{ $version->private_reply_text }}</dd>
        @endif
        @if($version?->private_reply_url)
            <dt>{{ __('Link') }}</dt><dd>{{ $version->private_reply_url }}</dd>
        @endif
    </dl>
</div>

{{-- The limits, on the screen where somebody is about to commit. Reading them
     afterwards, from an empty log, is reading them too late. --}}
<div class="card" style="margin-bottom:16px">
    <p class="kicker">{{ __('Before you switch this on') }}</p>
    <ul>
        @foreach($limitations as $note)
            <li class="muted">{{ $note }}</li>
        @endforeach
    </ul>
</div>

<div class="card" style="margin-bottom:16px">
    <p class="kicker">{{ __('What this account can do') }}</p>
    <ul>
        @foreach($capabilities as $capability)
            <li>
                {{ $capability['allowed'] ? '✓' : '○' }} {{ $capability['label'] }}
                @unless($capability['allowed'])
                    <span class="muted">— {{ $capability['reason'] }}</span>
                @endunless
            </li>
        @endforeach
    </ul>
</div>

<form method="post" action="{{ route('autodm.activate', $automation->uuid) }}">
    @csrf
    <button class="btn" type="submit">{{ __('Activate') }}</button>
    <span class="muted">{{ __('Permissions and post ownership are checked again at this moment, not taken from when you drafted it.') }}</span>
</form>
@endsection
