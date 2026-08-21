(() => {
    let installPrompt = null;
    const buttons = () => document.querySelectorAll('[data-pwa-install]');

    const setVisible = (visible) => {
        buttons().forEach((button) => {
            button.hidden = !visible;
            button.disabled = !visible;
        });
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
        setVisible(true);
    });

    window.addEventListener('appinstalled', () => {
        installPrompt = null;
        setVisible(false);
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-pwa-install]');
        if (!button || !installPrompt) return;

        button.disabled = true;
        await installPrompt.prompt();
        installPrompt = null;
        setVisible(false);
    });

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js', {
                scope: '/',
                updateViaCache: 'none',
            }).catch(() => {});
        });
    }
})();
