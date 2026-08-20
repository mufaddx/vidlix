/**
 * Site chrome: the mobile navigation drawer.
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

    // The public site opens a drawer; the workspace slides its sidebar.
    var panel = document.getElementById('site-nav') || document.getElementById('app-shell');

    if (!panel) {
        return;
    }

    // Queried from the document: the scrim sits outside the header so the
    // header's backdrop-filter cannot clip it to its own height.
    var scrim = document.querySelector('[data-nav-close]');
    var links = document.getElementById('primary-nav');

    function setOpen(open) {
        panel.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

        // Hold the page still behind the drawer. Without this the background
        // scrolls under your thumb and reads as the page itself moving.
        document.body.classList.toggle('nav-locked', open);

        if (scrim) {
            // hidden is removed only while open, so the scrim never sits over
            // the page as an invisible tap-blocker.
            if (open) {
                scrim.removeAttribute('hidden');
            } else {
                scrim.setAttribute('hidden', '');
            }
        }

        if (open && links) {
            var first = links.querySelector('a');

            if (first) {
                first.focus({ preventScroll: true });
            }
        }
    }

    toggle.addEventListener('click', function () {
        setOpen(!panel.classList.contains('is-open'));
    });

    if (scrim) {
        scrim.addEventListener('click', function () {
            setOpen(false);
            toggle.focus();
        });
    }

    // Escape closes it, and focus returns to the button that opened it.
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && panel.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });

    // Following a link should not leave the drawer hanging open behind the new page.
    panel.addEventListener('click', function (event) {
        if (event.target.closest('a') && panel.classList.contains('is-open')) {
            setOpen(false);
        }
    });

    // Growing past the breakpoint leaves a drawer stranded over a desktop layout.
    window.addEventListener('resize', function () {
        if (window.innerWidth > 900 && panel.classList.contains('is-open')) {
            setOpen(false);
        }
    });
})();
