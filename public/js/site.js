/**
 * Site chrome: the mobile navigation.
 *
 * This lives in a file rather than inline in the layout because the
 * Content-Security-Policy allows scripts from 'self' and nothing else. As an
 * inline block it was silently blocked, which meant the menu button on a phone
 * did nothing at all - the page looked fine and simply would not open.
 */
(function () {
    'use strict';

    var toggle = document.querySelector('[data-nav-toggle]');

    if (!toggle) {
        return;
    }

    // The public site collapses a nav; the workspace slides a sidebar.
    var panel = document.getElementById('site-nav') || document.getElementById('app-shell');

    if (!panel) {
        return;
    }

    function setOpen(open) {
        panel.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    toggle.addEventListener('click', function () {
        setOpen(!panel.classList.contains('is-open'));
    });

    // Escape closes it, and focus goes back to the button that opened it.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && panel.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });

    // Following a link should not leave the menu hanging open behind the new page.
    panel.addEventListener('click', function (event) {
        if (event.target.closest('a') && panel.classList.contains('is-open')) {
            setOpen(false);
        }
    });
})();
