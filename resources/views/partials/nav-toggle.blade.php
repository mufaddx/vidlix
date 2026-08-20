{{--
    The menu button.

    Three dots rather than the word "Menu": on a phone the label took the width
    of a real button for something that only needs to be recognised, and the
    dots are the affordance people already know.

    The label still exists for screen readers, and aria-expanded tells them
    whether the menu is open — an icon that says nothing is only an improvement
    for people who can see it.
--}}
<button class="nav-toggle" type="button"
        aria-expanded="false"
        @isset($controls) aria-controls="{{ $controls }}" @endisset
        aria-label="{{ __('Menu') }}"
        title="{{ __('Menu') }}"
        data-nav-toggle>
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <circle cx="12" cy="5" r="1.8"/>
        <circle cx="12" cy="12" r="1.8"/>
        <circle cx="12" cy="19" r="1.8"/>
    </svg>
</button>
