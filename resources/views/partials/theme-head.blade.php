{{-- Theme bootstrap. Must stay inline and in <head>: it runs before first paint,
     so a returning visitor never sees a flash of the wrong theme. --}}
<script>
(function () {
    var KEY = 'vidlix-theme';
    var root = document.documentElement;

    function stored() {
        try {
            var value = localStorage.getItem(KEY);
            return value === 'light' || value === 'dark' ? value : null;
        } catch (e) {
            return null;
        }
    }

    function systemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function apply(theme) {
        root.setAttribute('data-theme', theme);
        var label = theme === 'dark' ? @json(__('Switch to light theme')) : @json(__('Switch to dark theme'));
        var buttons = document.querySelectorAll('[data-theme-toggle]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            buttons[i].setAttribute('aria-label', label);
            buttons[i].setAttribute('title', label);
        }
    }

    apply(stored() || systemTheme());

    // The buttons do not exist yet at head time, so label them once they do.
    document.addEventListener('DOMContentLoaded', function () {
        apply(root.getAttribute('data-theme'));
    });

    // Delegated, so it works for every toggle on the page without rebinding.
    document.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
        if (!button) {
            return;
        }
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem(KEY, next);
        } catch (e) {
            // Private mode: the choice simply does not persist.
        }
        apply(next);
    });

    // Keep following the OS, but only while the visitor has not chosen for themselves.
    if (window.matchMedia) {
        var query = window.matchMedia('(prefers-color-scheme: dark)');
        var onChange = function (event) {
            if (!stored()) {
                apply(event.matches ? 'dark' : 'light');
            }
        };
        if (query.addEventListener) {
            query.addEventListener('change', onChange);
        } else if (query.addListener) {
            query.addListener(onChange);
        }
    }
})();
</script>
