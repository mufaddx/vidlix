@extends('layouts.app')
@section('title', __('Contact form'))
@section('content')

<h1>{{ __('Your contact form') }}</h1>
<p class="muted">
    {{ __('This is what people see when they write to you. Every save publishes a new version — messages you have already received keep the form they were sent with.') }}
</p>

@if(session('status'))<p class="flash">{{ session('status') }}</p>@endif
@if($errors->any())<p class="error">{{ $errors->first() }}</p>@endif

{{-- Where it lives ------------------------------------------------------- --}}
<div class="card" style="margin-bottom:20px">
    <p class="kicker">{{ __('Your form link') }}</p>

    @if($publicUrl)
        <p class="stat" style="font-size:17px;word-break:break-all">{{ $publicUrl }}</p>
        <p>
            <button class="btn secondary" type="button" data-copy="{{ $publicUrl }}">{{ __('Copy link') }}</button>
            <a class="btn secondary" href="{{ $publicUrl }}">{{ __('Preview') }}</a>
        </p>
    @else
        <p class="muted">{{ __('You will get a link once your profile has a username.') }}</p>
    @endif

    <form method="post" action="{{ route('app.contact-form.toggle') }}" style="margin-top:12px">
        @csrf
        <input type="hidden" name="enabled" value="{{ $form->is_enabled ? 0 : 1 }}">
        @if($form->is_enabled)
            <span class="chip">{{ __('Accepting messages') }}</span>
            <button class="btn secondary" type="submit">{{ __('Turn the form off') }}</button>
            <span class="muted">{{ __('Turning it off keeps everything you have already received.') }}</span>
        @else
            <span class="chip">{{ __('Off') }}</span>
            <button class="btn" type="submit">{{ __('Turn the form on') }}</button>
            <span class="muted">{{ __('Nobody can send you a message while it is off.') }}</span>
        @endif
    </form>
</div>

{{-- Settings and fields, saved together as one version ------------------- --}}
<form class="form" method="post" action="{{ route('app.contact-form.save') }}">
    @csrf

    <h2>{{ __('Wording') }}</h2>

    <label for="title">{{ __('Heading') }}</label>
    <input id="title" name="title" value="{{ old('title', $schema['title'] ?? '') }}" required maxlength="120">

    <label for="description">{{ __('Intro') }} <span class="muted">{{ __('(optional)') }}</span></label>
    <textarea id="description" name="description" maxlength="500">{{ old('description', $schema['description'] ?? '') }}</textarea>

    <label for="submit_text">{{ __('Button text') }}</label>
    <input id="submit_text" name="submit_text" value="{{ old('submit_text', $schema['submit_text'] ?? __('Send message')) }}" maxlength="40">

    <label for="success_message">{{ __('Thank-you message') }}</label>
    <textarea id="success_message" name="success_message" maxlength="500">{{ old('success_message', $schema['success_message'] ?? '') }}</textarea>

    <h2>{{ __('Fields') }}</h2>
    <p class="muted">
        {{ __('Name, email, subject and message are always there — replies would not work without them. Everything else is yours.') }}
    </p>

    @foreach($schema['fields'] as $index => $field)
        @php($locked = in_array($field['key'], $lockedKeys, true))
        @php($earlier = array_slice($schema['fields'], 0, $index))

        <div class="card" style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px">
                <p class="kicker">
                    {{ $field['key'] }}
                    @if($locked)<span class="chip">{{ __('Always shown') }}</span>@endif
                </p>

                @unless($locked)
                    {{-- Its own form: a delete nested in the save form would
                         submit the save instead. --}}
                    <span>
                        <button class="btn secondary" type="submit" form="remove-{{ $field['key'] }}">{{ __('Remove') }}</button>
                    </span>
                @endunless
            </div>

            <input type="hidden" name="fields[{{ $index }}][key]" value="{{ $field['key'] }}">

            <label for="label-{{ $index }}">{{ __('Label') }}</label>
            <input id="label-{{ $index }}" name="fields[{{ $index }}][label]"
                   value="{{ $field['label'] }}" required maxlength="120">

            <label for="type-{{ $index }}">{{ __('Answer type') }}</label>
            <select id="type-{{ $index }}" name="fields[{{ $index }}][type]" @disabled($locked)>
                @foreach($types as $type)
                    <option value="{{ $type->value }}" @selected($field['type'] === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            @if($locked)
                {{-- Disabled inputs are not submitted, so the value still has
                     to travel. The server pins locked types regardless. --}}
                <input type="hidden" name="fields[{{ $index }}][type]" value="{{ $field['type'] }}">
            @endif

            <label for="placeholder-{{ $index }}">{{ __('Hint inside the box') }} <span class="muted">{{ __('(optional)') }}</span></label>
            <input id="placeholder-{{ $index }}" name="fields[{{ $index }}][placeholder]"
                   value="{{ $field['placeholder'] ?? '' }}" maxlength="120">

            <label for="options-{{ $index }}">{{ __('Choices, one per line') }}</label>
            <textarea id="options-{{ $index }}" name="fields[{{ $index }}][options]"
                      placeholder="{{ __("College\nBrand collaboration\nProject\nOther") }}">{{ implode("\n", $field['options'] ?? []) }}</textarea>
            <p class="muted">{{ __('Only used by dropdown and multiple choice.') }}</p>

            @if($index > 0)
                <label for="when-field-{{ $index }}">{{ __('Only show this when…') }}</label>
                <select id="when-field-{{ $index }}" name="fields[{{ $index }}][visible_when_field]">
                    <option value="">{{ __('Always show it') }}</option>
                    @foreach($earlier as $candidate)
                        <option value="{{ $candidate['key'] }}"
                            @selected(($field['visible_when']['field'] ?? null) === $candidate['key'])>
                            {{ $candidate['label'] }}
                        </option>
                    @endforeach
                </select>

                <label for="when-value-{{ $index }}">{{ __('…is answered with') }}</label>
                <input id="when-value-{{ $index }}" name="fields[{{ $index }}][visible_when_value]"
                       value="{{ $field['visible_when']['equals'] ?? '' }}" maxlength="120"
                       placeholder="{{ __('Other') }}">
                <p class="muted">{{ __('This is how “Other → please specify” works: point at the question above and type the answer that should reveal this field.') }}</p>
            @endif

            <label class="checkbox">
                <input type="checkbox" name="fields[{{ $index }}][required]" value="1"
                       @checked($field['required'] ?? false) @disabled($locked)>
                {{ __('Must be answered') }}
            </label>
        </div>
    @endforeach

    <button class="btn" type="submit">{{ __('Publish form') }}</button>
</form>

{{-- Delete forms, outside the save form so they do not nest --------------- --}}
@foreach($schema['fields'] as $field)
    @unless(in_array($field['key'], $lockedKeys, true))
        <form id="remove-{{ $field['key'] }}" method="post"
              action="{{ route('app.contact-form.fields.remove', $field['key']) }}" class="hp">
            @csrf @method('DELETE')
        </form>
    @endunless
@endforeach

{{-- Add a field ---------------------------------------------------------- --}}
<h2>{{ __('Add a field') }}</h2>
<form class="form" method="post" action="{{ route('app.contact-form.fields.add') }}">
    @csrf

    <label for="new-label">{{ __('Question') }}</label>
    <input id="new-label" name="label" required maxlength="120"
           placeholder="{{ __('What are you contacting me about?') }}">

    <label for="new-type">{{ __('Answer type') }}</label>
    <select id="new-type" name="type">
        @foreach($types as $type)
            <option value="{{ $type->value }}">{{ $type->label() }}</option>
        @endforeach
    </select>

    <label for="new-options">{{ __('Choices, one per line') }} <span class="muted">{{ __('(dropdown and multiple choice only)') }}</span></label>
    <textarea id="new-options" name="options" placeholder="{{ __("College\nBrand collaboration\nProject\nOther") }}"></textarea>

    <label class="checkbox">
        <input type="checkbox" name="required" value="1">
        {{ __('Must be answered') }}
    </label>

    <button class="btn secondary" type="submit">{{ __('Add field') }}</button>
</form>
@endsection
