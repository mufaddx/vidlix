@extends('layouts.app')
@section('title', __('Negotiation'))
@section('content')

@php($me = auth()->id())
@php($other = $negotiation->initiator_user_id === $me ? $negotiation->counterparty : $negotiation->initiator)

<p><a href="{{ route('app.negotiations') }}">{{ __('Back to negotiations') }}</a></p>

<h1>{{ __('Negotiation with :name', ['name' => $other?->name ?? __('Unknown')]) }}</h1>
<p>
    <span class="chip">{{ str_replace('_', ' ', $negotiation->status) }}</span>
    @if($negotiation->expires_at && $negotiation->isOpen())
        <span class="muted">{{ __('Expires :when', ['when' => $negotiation->expires_at->diffForHumans()]) }}</span>
    @endif
</p>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

@if($negotiation->status === 'accepted' && $negotiation->project_id)
    <div class="card">
        <h2>{{ __('Agreed') }}</h2>
        <p class="muted">{{ __('These terms are final and cannot be edited — that is what makes them worth relying on.') }}</p>
        <p><a class="btn" href="{{ route('app.projects.show', $negotiation->project_id) }}">{{ __('Open the project') }}</a></p>
    </div>
@endif

<h2>{{ __('Offers') }}</h2>
@foreach($negotiation->offers as $offer)
    <article class="card" style="margin-bottom:12px">
        <p class="kicker">
            {{ __('Offer :n', ['n' => $offer->sequence]) }} ·
            {{ $offer->offered_by_user_id === $me ? __('You') : ($offer->offeredBy?->name ?? __('Them')) }} ·
            {{ $offer->created_at?->diffForHumans() }}
            @if($negotiation->accepted_offer_id === $offer->id)
                <span class="chip">{{ __('Accepted') }}</span>
            @endif
        </p>

        <p class="stat">₹{{ number_format($offer->amount_minor / 100, 2) }}</p>

        <dl class="a-facts">
            @if($offer->deliverableList())
                <dt>{{ __('Deliverables') }}</dt>
                <dd>{{ implode(' · ', $offer->deliverableList()) }}</dd>
            @endif
            @if($offer->deadline)<dt>{{ __('Deadline') }}</dt><dd>{{ $offer->deadline->toDateString() }}</dd>@endif
            @if($offer->revision_limit !== null)<dt>{{ __('Revisions') }}</dt><dd>{{ $offer->revision_limit }}</dd>@endif
            @if($offer->usage_rights)<dt>{{ __('Usage rights') }}</dt><dd>{{ $offer->usage_rights }}</dd>@endif
        </dl>

        @if($offer->note)<p style="white-space:pre-wrap">{{ $offer->note }}</p>@endif
    </article>
@endforeach

@if($negotiation->isOpen())
    @if($canAccept)
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
            <form method="post" action="{{ route('app.negotiations.accept', $negotiation->uuid) }}"
                  onsubmit="return confirm('{{ __('Accept these terms? This starts the project, and the terms cannot be changed afterwards.') }}')">
                @csrf
                <button class="btn" type="submit">{{ __('Accept these terms') }}</button>
            </form>

            <form method="post" action="{{ route('app.negotiations.reject', $negotiation->uuid) }}">
                @csrf
                <button class="btn secondary" type="submit">{{ __('Decline') }}</button>
            </form>
        </div>
    @else
        {{-- The offer on the table is yours, so there is nothing to accept:
             agreeing with yourself is not agreement. --}}
        <p class="muted">{{ __('Waiting for :name to respond. You can still change your offer below.', ['name' => $other?->name ?? __('them')]) }}</p>
    @endif

    <h2>{{ $canAccept ? __('Or counter') : __('Change your offer') }}</h2>
    <form class="form" method="post" action="{{ route('app.negotiations.counter', $negotiation->uuid) }}">
        @csrf
        @include('app.partials.offer-fields', ['offer' => $latest])
        <button class="btn secondary" type="submit">{{ __('Send offer') }}</button>
    </form>

    @if($negotiation->initiator_user_id === $me)
        <form method="post" action="{{ route('app.negotiations.cancel', $negotiation->uuid) }}" style="margin-top:12px">
            @csrf
            <button class="btn secondary" type="submit">{{ __('Cancel this negotiation') }}</button>
        </form>
    @endif
@else
    <p class="muted">{{ __('This negotiation is closed. Nothing further can be offered on it.') }}</p>
@endif
@endsection
