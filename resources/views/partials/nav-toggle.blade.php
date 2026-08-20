{{--
    The menu button.

    Three lines rather than the word "Menu": on a phone the label took the width
    of a real button for something people recognise by shape. The label still
    exists for screen readers, and aria-expanded tells them whether the drawer
    is open — an icon that says nothing only helps people who can see it.
--}}
<button class="nav-toggle" type="button"
        aria-expanded="false"
        @isset($controls) aria-controls="{{ $controls }}" @endisset
        aria-label="{{ __('Menu') }}"
        title="{{ __('Menu') }}"
        data-nav-toggle>
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path d="M4 7h16M4 12h16M4 17h16"/>
    </svg>
</button>
