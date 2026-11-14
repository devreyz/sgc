<script>
    (() => {
        const root = document.documentElement;
        const meta = document.querySelector('meta[name="theme-color"]');
        const manifest = document.querySelector('link[rel="manifest"]');
        const applyTheme = (theme) => {
            const dark = theme === 'dark';
            root.dataset.theme = dark ? 'dark' : 'light';
            if (meta) meta.content = dark ? '#151a20' : '#173c2e';
            if (manifest) manifest.href = dark ? '/manifest-dark.json?v=campo' : '/manifest.json?v=campo';
        };

        try {
            applyTheme(localStorage.getItem('sgc-campo-theme') || localStorage.getItem('sgc-theme'));
        } catch {
            applyTheme('light');
        }

        document.addEventListener('click', (event) => {
            if (!event.target.closest('[data-public-theme-toggle]')) return;
            applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
            try { localStorage.setItem('sgc-campo-theme', root.dataset.theme); } catch {}
            document.querySelectorAll('[data-public-theme-toggle]').forEach((button) => {
                button.setAttribute('aria-pressed', String(root.dataset.theme === 'dark'));
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-public-theme-toggle]').forEach((button) => {
                button.setAttribute('aria-pressed', String(root.dataset.theme === 'dark'));
            });
        });
    })();
</script>
