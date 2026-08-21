(() => {
    let pending = 0;

    const loader = () => document.getElementById('sgc-global-loader');
    const labelFor = (value, fallback) => String(value || '').replace(/\s+/g, ' ').trim().slice(0, 58) || fallback;

    const ensureLoader = () => {
        if (loader()) return;
        document.body.insertAdjacentHTML('beforeend', `
          <div id="sgc-global-loader" aria-live="polite" aria-hidden="true">
            <div class="sgc-global-loader__card" role="status">
              <span class="sgc-global-loader__mark"><img src="/assets/sgc-symbol.png" alt=""></span>
              <span class="sgc-global-loader__title">Carregando</span>
              <span class="sgc-global-loader__message">Aguarde só um instante...</span>
              <span class="sgc-global-loader__track" aria-hidden="true"></span>
            </div>
          </div>`);
    };

    window.showGlobalLoading = (context = 'Carregando') => {
        ensureLoader();
        pending += 1;
        const overlay = loader();
        overlay.querySelector('.sgc-global-loader__title').textContent = labelFor(context, 'Carregando');
        overlay.classList.add('is-active');
        overlay.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('sgc-loading-lock');
        document.body.classList.add('sgc-loading-lock');
    };

    window.hideGlobalLoading = () => {
        pending = Math.max(0, pending - 1);
        if (pending) return;
        const overlay = loader();
        overlay?.classList.remove('is-active');
        overlay?.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('sgc-loading-lock');
        document.body.classList.remove('sgc-loading-lock');
    };

    const isMutation = (method) => ['POST', 'PUT', 'PATCH', 'DELETE'].includes(String(method || 'GET').toUpperCase());
    const isInternalNavigation = (anchor) => {
        if (!anchor || anchor.target || anchor.hasAttribute('download') || anchor.dataset.globalLoader === 'false') return false;
        const href = anchor.getAttribute('href') || '';
        if (!href || href.startsWith('#') || /^(mailto:|tel:|javascript:)/i.test(href)) return false;
        try { return new URL(anchor.href, location.href).origin === location.origin; } catch (_) { return false; }
    };

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const anchor = event.target.closest('a[href]');
        if (!isInternalNavigation(anchor)) return;
        if (anchor.dataset.sgcNavigating === 'true') {
            event.preventDefault();
            return;
        }
        anchor.dataset.sgcNavigating = 'true';
        window.showGlobalLoading(anchor.dataset.loadingLabel || labelFor(anchor.textContent, 'Abrindo página'));
        // Alguns componentes interceptam links GET para atualizar somente parte da tela.
        // Nesse caso não há pagehide; encerra a transição curta para não manter a tela bloqueada.
        window.setTimeout(() => {
            if (anchor.dataset.sgcNavigating !== 'true') return;
            delete anchor.dataset.sgcNavigating;
            window.hideGlobalLoading();
        }, 1200);
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.target || form.dataset.globalLoader === 'false') return;
        if (form.dataset.sgcSubmitting === 'true') {
            event.preventDefault();
            return;
        }
        window.setTimeout(() => {
            if (event.defaultPrevented) return;
            form.dataset.sgcSubmitting = 'true';
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => { control.disabled = true; });
            window.showGlobalLoading(form.dataset.loadingLabel || 'Processando solicitação');
        }, 0);
    }, true);

    const nativeFetch = window.fetch?.bind(window);
    if (nativeFetch) {
        window.fetch = (input, init = {}) => {
            const requestMethod = input instanceof Request ? input.method : 'GET';
            const method = init.method || requestMethod;
            const visible = isMutation(method) && init.globalLoader !== false;
            if (visible) window.showGlobalLoading(init.loadingLabel || 'Processando solicitação');
            return nativeFetch(input, init).finally(() => { if (visible) window.hideGlobalLoading(); });
        };
    }

    const nativeOpen = XMLHttpRequest.prototype.open;
    const nativeSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, ...args) { this.__sgcLoadingMethod = method; return nativeOpen.call(this, method, ...args); };
    XMLHttpRequest.prototype.send = function (...args) {
        const visible = isMutation(this.__sgcLoadingMethod) && this.__sgcGlobalLoader !== false;
        if (visible) {
            window.showGlobalLoading('Processando solicitação');
            this.addEventListener('loadend', () => window.hideGlobalLoading(), { once: true });
        }
        return nativeSend.apply(this, args);
    };

    const resetAfterNavigation = () => {
        pending = 0;
        const overlay = loader();
        overlay?.classList.remove('is-active');
        overlay?.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('sgc-loading-lock');
        document.body.classList.remove('sgc-loading-lock');
        document.querySelectorAll('[data-sgc-navigating]').forEach((anchor) => {
            delete anchor.dataset.sgcNavigating;
        });
        document.querySelectorAll('form[data-sgc-submitting]').forEach((form) => {
            delete form.dataset.sgcSubmitting;
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((control) => {
                control.disabled = false;
            });
        });
    };

    // Limpa antes do snapshot de histórico e ao retornar por voltar/avançar.
    window.addEventListener('pagehide', resetAfterNavigation);
    window.addEventListener('pageshow', resetAfterNavigation);
})();
