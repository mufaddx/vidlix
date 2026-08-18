@extends('layouts.app')
@section('title', __('Proposals'))
@section('content')
<h1>{{ __('Proposals') }}</h1>
<form method="post" action="{{ route('app.proposals.store') }}">@csrf<input name="to_user_id" required><input type="number" name="amount_minor" required><textarea name="notes"></textarea><button class="btn" type="submit">{{ __('Send v1') }}</button></form>
@foreach($items as $p)
    <p>{{ $p->proposal_uuid }} · {{ $p->status }} · v{{ $p->latestVersion()?->version_number }} ₹{{ number_format(($p->latestVersion()?->amount_minor ?? 0)/100,0) }}</p>
@endforeach
@endsection
