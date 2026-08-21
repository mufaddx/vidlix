{{--
    One shape for every "this is not ready" state a page can be in.

    The spec asks for eleven of these on every screen. Written once and included
    means a suspended account reads the same wherever you meet it, and a new
    page inherits the wording instead of inventing its own.

    Each state says what happened, whether the person can do anything about it,
    and what happens next — in that order, because "is this my fault?" is the
    first question and "how long?" is the second.

    @param string $state
    @param string|null $detail  something specific to add, where there is any
    @param string|null $action  a route to offer, where one helps
    @param string|null $actionLabel
--}}
@php($detail = $detail ?? null)
@php($action = $action ?? null)
@php($actionLabel = $actionLabel ?? null)

@php($copy = [
    'loading' => [
        'kicker' => __('Loading'),
        'title' => __('Fetching this now'),
        'body' => __('One moment.'),
    ],
    'empty' => [
        'kicker' => __('Nothing yet'),
        'title' => __('There is nothing here yet'),
        'body' => __('When there is, it will appear here.'),
    ],
    'error' => [
        'kicker' => __('Problem'),
        'title' => __('That did not work'),
        'body' => __('Nothing was changed. Try again, and tell support if it keeps happening.'),
    ],
    'denied' => [
        'kicker' => __('Not allowed'),
        'title' => __('You do not have access to this'),
        'body' => __('Your account does not hold the permission this needs.'),
    ],
    'suspended' => [
        'kicker' => __('Suspended'),
        'title' => __('This account is suspended'),
        // Says plainly that nothing was destroyed, because that is the thing
        // somebody in this state is actually frightened of.
        'body' => __('You cannot use this part of Vidlix while the suspension stands. Nothing has been deleted — your work, messages and balance are exactly as you left them.'),
    ],
    'verification_pending' => [
        'kicker' => __('In review'),
        'title' => __('Your application is with a reviewer'),
        'body' => __('A person reads every application, so this is not instant. You will get an email as soon as there is a decision, and you do not need to submit anything again.'),
    ],
    'verification_rejected' => [
        'kicker' => __('Not approved'),
        'title' => __('This application was not approved'),
        'body' => __('The reason is below. You can fix what it mentions and apply again — a rejection is not permanent.'),
    ],
    'more_info' => [
        'kicker' => __('More needed'),
        'title' => __('A reviewer needs something else'),
        'body' => __('Add what is described below and submit again. Everything you have already entered has been kept.'),
    ],
    'provider_disconnected' => [
        'kicker' => __('Disconnected'),
        'title' => __('This connection has stopped working'),
        // Names the cause, because "reconnect" without a reason reads as a
        // fault the person caused.
        'body' => __('The link to the provider has expired or been revoked. Reconnecting takes a moment and nothing on your side is lost.'),
    ],
    'provider_unconfigured' => [
        'kicker' => __('Not set up'),
        'title' => __('This is not connected yet'),
        'body' => __('The provider behind this feature is not configured on this installation, so it is switched off rather than left to fail quietly.'),
    ],
    'rate_limited' => [
        'kicker' => __('Slow down'),
        'title' => __('Too many attempts just now'),
        'body' => __('We have paused these briefly. Wait a minute and try again.'),
    ],
    'offline' => [
        'kicker' => __('Offline'),
        'title' => __('You appear to be offline'),
        'body' => __('Reconnect and this will pick up where it left off. Nothing you typed has been sent yet.'),
    ],
])

@php($current = $copy[$state] ?? $copy['error'])

<div class="card state state-{{ $state }}" role="status">
    <p class="kicker">{{ $current['kicker'] }}</p>
    <h2>{{ $current['title'] }}</h2>
    <p class="muted">{{ $current['body'] }}</p>

    @if($detail)
        <p>{{ $detail }}</p>
    @endif

    @if($action)
        <p><a class="btn secondary" href="{{ $action }}">{{ $actionLabel ?? __('Continue') }}</a></p>
    @endif
</div>
