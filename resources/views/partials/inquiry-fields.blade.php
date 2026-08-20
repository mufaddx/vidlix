{{--
    The fields of a published inquiry form.

    Shared by the profile page and the standalone contact page so the two can
    never drift apart — a field that renders in one place and not the other is a
    field whose answer sometimes arrives and sometimes does not.

    Conditional fields are hidden by CSS and revealed by site.js; the server
    re-evaluates the same condition on submit, so hiding is a convenience and
    never the check.
--}}
@php($fields = $form['fields'] ?? [])

@forelse($fields as $field)
    @php($key = $field['key'] ?? null)
    @continue(!$key)

    @php($type = $field['type'] ?? 'text')
    @php($required = (bool) ($field['required'] ?? false))
    @php($condition = $field['visible_when'] ?? null)

    <div class="field"
         @if($condition)
             data-visible-when="{{ $condition['field'] }}"
             data-visible-value="{{ $condition['equals'] }}"
             hidden
         @endif>

        <label for="{{ $key }}">
            {{ $field['label'] ?? $key }}@if($required) *@endif
        </label>

        @if($type === 'textarea')
            <textarea id="{{ $key }}" name="{{ $key }}" data-field="{{ $key }}"
                      placeholder="{{ $field['placeholder'] ?? '' }}"
                      @required($required && !$condition)>{{ old($key) }}</textarea>

        @elseif($type === 'select')
            <select id="{{ $key }}" name="{{ $key }}" data-field="{{ $key }}"
                    @required($required && !$condition)>
                <option value="">{{ __('Choose one') }}</option>
                @foreach($field['options'] ?? [] as $option)
                    <option value="{{ $option }}" @selected(old($key) === $option)>{{ $option }}</option>
                @endforeach
            </select>

        @elseif($type === 'radio')
            @foreach($field['options'] ?? [] as $option)
                <label class="checkbox">
                    <input type="radio" name="{{ $key }}" value="{{ $option }}"
                           data-field="{{ $key }}" @checked(old($key) === $option)>
                    {{ $option }}
                </label>
            @endforeach

        @elseif($type === 'checkbox')
            <label class="checkbox">
                <input type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1"
                       data-field="{{ $key }}" @checked(old($key))>
                {{ $field['placeholder'] ?: ($field['label'] ?? $key) }}
            </label>

        @else
            <input id="{{ $key }}" name="{{ $key }}" data-field="{{ $key }}"
                   type="{{ $type === 'email' ? 'email' : ($type === 'phone' ? 'tel' : ($type === 'url' ? 'url' : 'text')) }}"
                   value="{{ old($key) }}"
                   placeholder="{{ $field['placeholder'] ?? '' }}"
                   @required($required && !$condition)>
        @endif
    </div>
@empty
    {{-- No published schema. The four fields a reply cannot work without. --}}
    <label for="name">{{ __('Your name') }} *</label>
    <input id="name" name="name" value="{{ old('name') }}" required>

    <label for="email">{{ __('Your email') }} *</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" required>

    <label for="subject">{{ __('Subject') }} *</label>
    <input id="subject" name="subject" value="{{ old('subject') }}" required>

    <label for="message">{{ __('Message') }} *</label>
    <textarea id="message" name="message" required>{{ old('message') }}</textarea>
@endforelse
