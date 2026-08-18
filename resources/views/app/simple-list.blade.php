@extends('layouts.app')
@section('title', $title ?? __('Workspace'))
@section('content')
<h1>{{ $title ?? __('Workspace') }}</h1>
@if(!empty($hint))<p class="muted">{{ $hint }}</p>@endif
{!! $slot ?? '' !!}
@endsection
