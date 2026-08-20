{{-- The show/hide button for a password field. Pass the input's id as $for. --}}
<button type="button" class="reveal" data-reveal="{{ $for }}"
        aria-pressed="false"
        aria-label="{{ __('Show password') }}" title="{{ __('Show password') }}">
    @include('auth.partials.eye')
</button>
