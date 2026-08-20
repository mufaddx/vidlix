@extends('layouts.app')
@section('title', __('What do you do?'))
@section('content')
<h1>{{ __('What do you do on Vidlix?') }}</h1>
<p class="muted">{{ __('Pick as many as apply. You can be a creator and an editor at the same time, and add another later.') }}</p>

<div class="grid">
    @foreach($applicable as $role)
        <div class="card">
            <h2>{{ ucfirst($role) }}</h2>
            <p class="muted">
                @switch($role)
                    @case('creator') {{ __('Publish a profile, get brand inquiries, run collaborations.') }} @break
                    @case('editor') {{ __('Offer editing work and take on projects.') }} @break
                    @case('brand') {{ __('Find creators, run campaigns, hire editors.') }} @break
                @endswitch
            </p>
            @if(in_array($role, $held, true))
                <p class="kicker">{{ __('Active') }}</p>
            @else
                <form method="post" action="{{ route('app.roles.apply') }}">@csrf
                    <input type="hidden" name="role" value="{{ $role }}">
                    <button class="btn" type="submit">{{ __('Apply as :role', ['role' => $role]) }}</button>
                </form>
            @endif
        </div>
    @endforeach
</div>


@if(in_array('creator', $held, true))
    <h2>{{ __('Your categories') }}</h2>
    <p class="muted">{{ __('Choose up to :max. Brands search by these, so pick what you actually make.', ['max' => $maxCreatorCategories]) }}</p>

    <form class="form" method="post" action="{{ route('app.roles.creator-categories') }}">@csrf
        <div class="chips">
            @foreach($creatorCategories as $category)
                <label class="chip">
                    <input type="checkbox" name="category_ids[]" value="{{ $category->id }}"
                           @checked(in_array($category->id, $creatorSelected, true))>
                    <span>{{ $category->name }}</span>
                    @if($category->isPending())<em class="muted">{{ __('pending review') }}</em>@endif
                </label>
            @endforeach
        </div>

        <label for="new_category">{{ __('Not listed? Add your own') }}</label>
        <input id="new_category" name="new_categories[]" placeholder="{{ __('e.g. Street Food Reviews') }}" maxlength="48">
        <p class="muted">{{ __('It works straight away and is added to the public list once reviewed.') }}</p>

        <button class="btn" type="submit">{{ __('Save categories') }}</button>
    </form>
@endif
@endsection
