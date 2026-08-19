@extends('layouts.admin')
@section('title', __('Website copy'))
@section('subheading', __('Text shown on the public site'))
@section('content')

@foreach($sections as $section)
    <div class="a-panel">
        <div class="a-panel-head">{{ $section->key }}</div>
        <div class="a-panel-body">
            <form class="a-form" method="post" action="{{ route('admin.cms.section', $section) }}">@csrf
                <div>
                    <label for="title-{{ $section->id }}">{{ __('Title') }}</label>
                    <input id="title-{{ $section->id }}" name="title" value="{{ $section->title }}" required>
                </div>
                <div>
                    <label for="subtitle-{{ $section->id }}">{{ __('Subtitle') }}</label>
                    <textarea id="subtitle-{{ $section->id }}" name="subtitle">{{ $section->subtitle }}</textarea>
                </div>
                <label class="a-check">
                    <input type="checkbox" name="is_visible" value="1" @checked($section->is_visible)>
                    <span>{{ __('Show this section on the site') }}</span>
                </label>
                <button class="a-btn" type="submit">{{ __('Save') }}</button>
            </form>
        </div>
    </div>
@endforeach
@endsection
