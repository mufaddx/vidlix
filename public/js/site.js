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

/**
 * Show-password buttons, wherever a password is typed.
 *
 * Delegated from the document, so it covers the sign-in screens, the admin
 * front door, the manager invitation, and every confirm-your-password step -
 * these all used to have a bare field because the handler lived in the file
 * only the three public auth screens loaded.
 */
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-reveal]') : null;

        if (!button) {
            return;
        }

        var input = document.getElementById(button.getAttribute('data-reveal'));

        if (!input) {
            return;
        }

        var show = input.type === 'password';

        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.setAttribute('title', show ? 'Hide password' : 'Show password');
    });
})();

/*
 * Copy-to-clipboard for anything carrying data-copy.
 *
 * The button says what happened rather than staying silent, because a copy that
 * gives no feedback is indistinguishable from one that failed. It also restores
 * its own label, so the page does not end up permanently reading "Copied".
 *
 * navigator.clipboard needs a secure context; over plain HTTP it is simply
 * absent, so there is a selection-based fallback rather than a dead button.
 */
(function () {
    var RESTORE_MS = 2000;

    function flash(button, message) {
        if (button.getAttribute('data-copy-busy') === '1') {
            return;
        }

        var original = button.textContent;

        button.setAttribute('data-copy-busy', '1');
        button.textContent = message;

        window.setTimeout(function () {
            button.textContent = original;
            button.removeAttribute('data-copy-busy');
        }, RESTORE_MS);
    }

    function fallbackCopy(text) {
        var field = document.createElement('textarea');

        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';

        document.body.appendChild(field);
        field.select();

        var copied = false;

        try {
            copied = document.execCommand('copy');
        } catch (e) {
            copied = false;
        }

        document.body.removeChild(field);

        return copied;
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-copy]') : null;

        if (!button) {
            return;
        }

        event.preventDefault();

        var text = button.getAttribute('data-copy');

        if (!text) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                flash(button, 'Copied');
            }, function () {
                flash(button, fallbackCopy(text) ? 'Copied' : 'Press Ctrl+C');
            });

            return;
        }

        flash(button, fallbackCopy(text) ? 'Copied' : 'Press Ctrl+C');
    });
})();
