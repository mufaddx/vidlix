@extends('layouts.app')
@section('title', __('CMS'))
@section('content')
<h1>{{ __('Homepage CMS') }}</h1>
@foreach($sections as $section)
    <form class="card form" method="post" action="{{ route('admin.cms.section', $section) }}" style="margin-bottom:16px;">
        @csrf
        <p class="kicker">{{ $section->key }}</p>
        <label>{{ __('Title') }}<input name="title" value="{{ $section->title }}" required></label>
        <label>{{ __('Subtitle') }}<textarea name="subtitle">{{ $section->subtitle }}</textarea></label>
        <label><input type="checkbox" name="is_visible" value="1" @checked($section->is_visible)> {{ __('Visible') }}</label>
        <button class="btn secondary" type="submit">{{ __('Save') }}</button>
    </form>
@endforeach
@endsection
