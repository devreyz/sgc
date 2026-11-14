(() => {
    const url = new URL(window.location.href);
    if (url.pathname === '/app' || document.documentElement.hasAttribute('data-app-entry')) {
        let dark = false;
        try { dark = (localStorage.getItem('sgc-campo-theme') || localStorage.getItem('sgc-theme')) === 'dark'; } catch {}
        document.documentElement.dataset.theme = dark ? 'dark' : 'light';
        if (dark) {
            document.querySelector('meta[name="theme-color"]').content = '#151a20';
            document.querySelector('link[rel="manifest"]').href = '/manifest-dark.json?v=campo';
        }
        return;
    }
    if (url.pathname !== '/' || url.searchParams.get('apresentacao') === '1' || url.hash) return;

    const installed = window.navigator.standalone === true
        || ['standalone', 'fullscreen', 'minimal-ui'].some(mode => window.matchMedia(`(display-mode: ${mode})`).matches);
    let returning = false;
    try { returning = localStorage.getItem('sgc-has-signed-in') === '1'; } catch {}

    if (installed || returning) window.location.replace('/app');
})();
