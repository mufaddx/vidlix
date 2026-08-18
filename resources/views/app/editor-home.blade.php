@extends('layouts.app')
@section('title', __('Editor'))
@section('content')
<h1>{{ __('Editor access') }}</h1>
@include('app.partials.editor-apply', ['profile' => $profile])
@endsection
