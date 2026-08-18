@extends('layouts.public')
@section('title', $brand->company_name)
@section('content')
<div class="wrap page-hero">
    <p class="kicker">{{ __('Verified brand') }}</p>
    <h1>{{ $brand->company_name }}</h1>
    <p class="lede">{{ $brand->industry }}</p>
</div>
<div class="wrap section" style="padding-top:8px;">
    <div class="card" style="max-width:44rem;">
        @if($brand->website)
            <p><a href="{{ $brand->website }}" rel="noopener noreferrer">{{ $brand->website }}</a></p>
        @endif
        <a class="btn secondary" href="{{ route('campaigns.index') }}">{{ __('See open campaigns') }}</a>
    </div>
</div>
@endsection
