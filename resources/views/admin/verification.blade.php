@extends('layouts.app')
@section('title', __('Verification'))
@section('content')
<h1>{{ __('Verification') }}</h1>
<h2>{{ __('Editors') }}</h2>
@foreach($editors as $e)
    <form method="post" action="{{ route('admin.editors.decide', $e) }}">@csrf {{ $e->display_name }}
        <button name="decision" value="approved">{{ __('Approve') }}</button>
        <button name="decision" value="rejected">{{ __('Reject') }}</button>
    </form>
@endforeach
<h2>{{ __('Brands') }}</h2>
@foreach($brands as $b)
    <form method="post" action="{{ route('admin.brands.decide', $b) }}">@csrf {{ $b->company_name }}
        <button name="decision" value="verified">{{ __('Verify') }}</button>
        <button name="decision" value="rejected">{{ __('Reject') }}</button>
    </form>
@endforeach
<h2>{{ __('Campaigns') }}</h2>
@foreach($campaigns as $c)
    <form method="post" action="{{ route('admin.campaigns.decide', $c) }}">@csrf {{ $c->name }}
        <button name="decision" value="published">{{ __('Publish') }}</button>
        <button name="decision" value="cancelled">{{ __('Cancel') }}</button>
    </form>
@endforeach
@endsection
