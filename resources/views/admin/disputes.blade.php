@extends('layouts.app')
@section('title', __('Disputes'))
@section('content')
<h1>{{ __('Disputes') }}</h1>
@foreach($items as $d)
    <form method="post" action="{{ route('admin.disputes.resolve', $d) }}">@csrf {{ $d->dispute_uuid }} {{ $d->reason }}
        <textarea name="resolution" required></textarea><button type="submit">{{ __('Resolve') }}</button>
    </form>
@endforeach
@endsection
