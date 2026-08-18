@extends('layouts.app')
@section('title', __('Tickets'))
@section('content')
<h1>{{ __('Tickets') }}</h1>
@foreach($items as $t)<p>{{ $t->id }} · {{ $t->subject }} · {{ $t->status }}</p>@endforeach
@endsection
