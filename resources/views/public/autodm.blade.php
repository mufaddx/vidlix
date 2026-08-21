@extends('layouts.public')
@section('title', __('Instagram AutoDM — Vidlix'))
@section('meta_description', __('Turn comments on your Instagram posts into conversations, using official Instagram APIs only. Honest about what Instagram does and does not allow.'))
@section('content')

<section class="wrap page-hero">
    <p class="kicker">{{ __('Vidlix AutoDM') }}</p>
    <h1>{{ __('Someone comments. They hear back.') }}</h1>
    <p class="lede">
        {{ __('Pick a post, choose the words to watch for, write the reply once. Every matching comment gets an answer without you being at your phone.') }}
    </p>
    <p>
        <a class="btn" href="{{ \App\Support\Host::urlFor('autodm', 'register') }}">{{ __('Get started') }}</a>
        <a class="btn secondary" href="#limits">{{ __('What Instagram allows') }}</a>
    </p>
</section>

{{--
    What actually happens, shown rather than described.

    Three steps that play in a loop: a comment arrives, it matches, a reply goes
    back. It is a diagram, not a demo — no invented handles, no fake screenshot
    of Instagram — because a mock-up of somebody else's app implies a
    relationship with them that does not exist.

    The whole thing is CSS. It respects prefers-reduced-motion, so a person who
    has asked for stillness gets the finished state instead of the loop.
--}}
<section class="wrap section">
    <h2>{{ __('What happens') }}</h2>

    <div class="autodm-flow" aria-hidden="true">
        <div class="autodm-step" style="--delay:0s">
            <p class="kicker">{{ __('Someone comments') }}</p>
            <p class="autodm-bubble">{{ __('send me the link!') }}</p>
        </div>

        <div class="autodm-arrow" style="--delay:1.2s">→</div>

        <div class="autodm-step" style="--delay:2.4s">
            <p class="kicker">{{ __('It matches your word') }}</p>
            <p class="autodm-bubble autodm-match">{{ __('“link”') }}</p>
        </div>

        <div class="autodm-arrow" style="--delay:3.6s">→</div>

        <div class="autodm-step" style="--delay:4.8s">
            <p class="kicker">{{ __('They hear back') }}</p>
            <p class="autodm-bubble autodm-reply">{{ __('Here you go 👇') }}</p>
        </div>
    </div>

    {{-- The animation is decoration; this sentence is the actual content, and
         it is what a screen reader gets. --}}
    <p class="muted">
        {{ __('Somebody comments on your post. If it contains a word you chose, Vidlix answers them — publicly under the comment, or privately where Instagram permits it.') }}
    </p>
</section>

<section class="wrap section">
    <h2>{{ __('How it works') }}</h2>
    <div class="grid">
        <article class="card">
            <p class="kicker">{{ __('1 · Connect') }}</p>
            <h3>{{ __('Sign in through Instagram') }}</h3>
            <p class="muted">{{ __('Official Meta sign-in. We never ask for your password, and you can disconnect at any time.') }}</p>
        </article>
        <article class="card">
            <p class="kicker">{{ __('2 · Choose') }}</p>
            <h3>{{ __('Pick a post or reel') }}</h3>
            <p class="muted">{{ __('One post, or every post on the account. Your media is listed straight from Instagram.') }}</p>
        </article>
        <article class="card">
            <p class="kicker">{{ __('3 · Trigger') }}</p>
            <h3>{{ __('Say what to watch for') }}</h3>
            <p class="muted">{{ __('A word, a few words, or a whole phrase. Matching ignores capitals and accents, because people do not type carefully in comments.') }}</p>
        </article>
        <article class="card">
            <p class="kicker">{{ __('4 · Reply') }}</p>
            <h3>{{ __('Write it once') }}</h3>
            <p class="muted">{{ __('A public reply under the comment, a private reply where Instagram permits it, or both.') }}</p>
        </article>
        <article class="card">
            <p class="kicker">{{ __('5 · Review') }}</p>
            <h3>{{ __('Read it back before it runs') }}</h3>
            <p class="muted">{{ __('The review screen shows exactly what will be sent, and anything that will not be — with the reason.') }}</p>
        </article>
        <article class="card">
            <p class="kicker">{{ __('6 · Watch') }}</p>
            <h3>{{ __('See every run') }}</h3>
            <p class="muted">{{ __('Sent, skipped or failed, each with a reason. Nothing is recorded as sent unless Instagram accepted it.') }}</p>
        </article>
    </div>
</section>

{{--
    The section that matters most, and the reason this page exists in this shape.
    A product built on somebody else's platform inherits that platform's limits,
    and a page that sells around them is a page that lies.
--}}
<section class="wrap section" id="limits">
    <div class="section-head">
        <div>
            <h2>{{ __('What Instagram allows, and what it does not') }}</h2>
            <p class="muted">{{ __('These are Instagram’s rules, not ours. We would rather you read them here than discover them from an empty log.') }}</p>
        </div>
    </div>

    <div class="grid">
        <article class="card">
            <h3>{{ __('Public replies work') }}</h3>
            <p class="muted">{{ __('Replying underneath a comment on your own post is permitted and needs nothing beyond connecting your account.') }}</p>
        </article>

        <article class="card">
            <h3>{{ __('Private replies are bounded') }}</h3>
            <p class="muted">
                {{ __('Instagram allows one private reply to somebody who commented, within roughly :hours hours. It needs messaging permissions that Meta grants after reviewing the app.', ['hours' => $windowHours]) }}
            </p>
        </article>

        <article class="card">
            <h3>{{ __('There are no follow-ups') }}</h3>
            <p class="muted">{{ __('No sequences, no drip campaigns, no second message days later. Anyone promising those on Instagram is either breaking the rules or about to lose the account.') }}</p>
        </article>

        <article class="card">
            <h3>{{ __('No messaging strangers') }}</h3>
            <p class="muted">{{ __('AutoDM only ever answers somebody who wrote to you first. It cannot start a conversation with a person who has not.') }}</p>
        </article>

        <article class="card">
            <h3>{{ __('Nothing is scraped') }}</h3>
            <p class="muted">{{ __('Official APIs only. No browser bots, no logging in as you, no automation Instagram cannot see.') }}</p>
        </article>

        <article class="card">
            <h3>{{ __('A professional account is needed') }}</h3>
            <p class="muted">{{ __('Instagram exposes comments and replies to business and creator accounts. A personal account cannot be automated at all.') }}</p>
        </article>
    </div>
</section>

<section class="wrap section">
    <h2>{{ __('Common questions') }}</h2>
    <div class="grid">
        <article class="card">
            <h3>{{ __('Will this get my account banned?') }}</h3>
            <p class="muted">{{ __('Not for using this. Everything AutoDM does goes through Instagram’s own API within the limits it sets — which is exactly why those limits are described so plainly above.') }}</p>
        </article>
        <article class="card">
            <h3>{{ __('What if a reply cannot be sent?') }}</h3>
            <p class="muted">{{ __('It is recorded as skipped, with the reason, and never as sent. A log that claims a message went out when it did not is worse than no log.') }}</p>
        </article>
        <article class="card">
            <h3>{{ __('What does it cost?') }}</h3>
            <p class="muted">{{ __('Plans are not published yet. What AutoDM can do depends on the permissions Instagram grants, and we would rather price it once that is settled than quote a number we might have to withdraw.') }}</p>
        </article>
    </div>
</section>

<section class="wrap section">
    <h2>{{ __('Start with your own account') }}</h2>
    <p class="lede">{{ __('Connect Instagram, build one automation, and watch what it does before you build a second.') }}</p>
    <p><a class="btn" href="{{ \App\Support\Host::urlFor('autodm', 'register') }}">{{ __('Create an account') }}</a></p>
</section>
@endsection
