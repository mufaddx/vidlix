@extends('layouts.app')
@section('title', __('Applications'))
@section('content')
<h1>{{ __('Applications') }}</h1>
<h2>{{ __('Sent') }}</h2>
@foreach($asCreator as $a)
    <p>{{ $a->campaign->name }} · {{ $a->status }} · ₹{{ number_format(($a->proposed_fee_minor ?? 0)/100, 0) }}</p>
@endforeach
<h2>{{ __('Received') }}</h2>
@foreach($asBrand as $a)
    <article class="card">
        <p>{{ $a->creator->display_name }} · {{ $a->status }}</p>
        <form method="post" action="{{ route('app.applications.status', $a) }}">
            @csrf
            <select name="status">
                <option>viewed</option><option>shortlisted</option><option>negotiation</option><option>accepted</option><option>rejected</option>
            </select>
            <button class="btn secondary" type="submit">{{ __('Update') }}</button>
        </form>
    </article>
@endforeach
@endsection
