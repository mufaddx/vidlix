@extends('layouts.app')
@section('title', __('Admin'))
@section('content')
<h1>{{ __('Operations') }}</h1>
<div class="grid">
    <article class="card"><p class="muted">{{ __('Users') }}</p><p class="stat">{{ $users }}</p></article>
    <article class="card"><p class="muted">{{ __('Creators') }}</p><p class="stat">{{ $creators }}</p></article>
    <article class="card"><p class="muted">{{ __('Editors') }}</p><p class="stat">{{ $editors }}</p></article>
    <article class="card"><p class="muted">{{ __('Brands') }}</p><p class="stat">{{ $brands }}</p></article>
</div>
<p><a href="{{ route('admin.cms') }}">{{ __('CMS') }}</a> · <a href="{{ route('admin.users') }}">{{ __('Users') }}</a> · <a href="{{ route('admin.verification') }}">{{ __('Verification') }}</a> · <a href="{{ route('admin.finance') }}">{{ __('Finance') }}</a> · <a href="{{ route('admin.disputes') }}">{{ __('Disputes') }}</a> · <a href="{{ route('admin.tickets') }}">{{ __('Tickets') }}</a></p>
<h2>{{ __('Recent audit') }}</h2>
<table class="table">
    @foreach($audits as $log)
        <tr><td>{{ $log->created_at }}</td><td>{{ $log->action }}</td><td>{{ $log->request_id }}</td></tr>
    @endforeach
</table>
@endsection
