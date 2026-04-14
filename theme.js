(function () {
    const STORAGE_KEY = 'elearn-theme';
    const root = document.documentElement;

    function applyTheme(theme) {
        const value = theme === 'light' ? 'light' : 'dark';
        root.setAttribute('data-theme', value);
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch (_) {
            // ignore
        }
        const btn = document.querySelector('[data-role="theme-toggle"]');
        if (btn) {
            btn.textContent = value === 'dark' ? 'Svetlá téma' : 'Tmavá téma';
        }
    }

    function detectInitialTheme() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored === 'light' || stored === 'dark') {
                return stored;
            }
        } catch (_) {
            // ignore
        }
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            return 'light';
        }
        return 'dark';
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(detectInitialTheme());

        let btn = document.querySelector('[data-role="theme-toggle"]');
        if (!btn) {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-secondary theme-toggle-floating';
            btn.setAttribute('data-role', 'theme-toggle');
            document.body.appendChild(btn);
        }

        btn.addEventListener('click', function () {
            const current = root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });

        // Ensure label text is correct on first paint
        const current = root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
        applyTheme(current);
    });
})();

