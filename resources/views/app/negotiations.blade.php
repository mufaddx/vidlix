@extends('layouts.app')
@section('title', __('Negotiations'))
@section('content')

<h1>{{ __('Negotiations') }}</h1>
<p class="muted">{{ __('Offers you have sent and received. Each one keeps its full history, so what was agreed is always readable.') }}</p>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif

@forelse($negotiations as $negotiation)
    @php($other = $negotiation->initiator_user_id === auth()->id() ? $negotiation->counterparty : $negotiation->initiator)
    <article class="card" style="margin-bottom:12px">
        <p class="kicker">
            <span class="chip">{{ str_replace('_', ' ', $negotiation->status) }}</span>
            @if($negotiation->expires_at && $negotiation->isOpen())
                <span class="muted">{{ __('Expires :when', ['when' => $negotiation->expires_at->diffForHumans()]) }}</span>
            @endif
        </p>
        <h2><a href="{{ route('app.negotiations.show', $negotiation->uuid) }}">{{ $other?->name ?? __('Unknown') }}</a></h2>
        <p class="muted">{{ __('Started :when', ['when' => $negotiation->created_at?->diffForHumans()]) }}</p>
    </article>
@empty
    <p class="muted">{{ __('Nothing here yet. A negotiation starts when you or somebody else makes an offer.') }}</p>
@endforelse

{{ $negotiations->links() }}
@endsection
