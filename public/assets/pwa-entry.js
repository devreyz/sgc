(() => {
    const isInstalledApp = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    if (isInstalledApp && window.location.pathname === '/') {
        const splash = document.getElementById('pwa-launch-splash');

        if (splash) {
            splash.hidden = false;
            requestAnimationFrame(() => splash.classList.add('is-visible'));
        }

        window.setTimeout(() => window.location.replace('/login'), 650);
    }
})();
