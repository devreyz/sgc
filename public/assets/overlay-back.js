(() => {
    if (window.sgcOverlayBack) return;

    const selector = 'dialog[open], [role="dialog"][aria-modal="true"], [role="alertdialog"][aria-modal="true"]';
    const closeSelector = '[data-back-close], button[aria-label^="Fechar" i], button[aria-label^="Cancelar" i]';
    const historyKey = 'sgcOverlayBack';
    const legacyKeys = [
        'sgcAgendaDay', 'financialDialog', 'svcDialog', 'expenseDialog', 'svcFileViewer',
        'catalogDialog', 'dossierDialog', 'dossierFileViewer', 'simulationDeleteDialog',
        '__sgcRegisterSheet', '__sgcDeliveryReport', 'sgcProducerReceiptsLayer',
    ];
    const layers = new Map();
    let token;
    let traversing = false;
    let closingLegacy = false;
    let previousState = history.state;

    function isVisible(element) {
        return !element.closest('[hidden], [aria-hidden="true"], [inert]')
            && element.getClientRects().length > 0
            && getComputedStyle(element).visibility === 'visible';
    }

    function findCloseButton(element) {
        return [...element.querySelectorAll('button')].find(button =>
            !button.disabled && button.closest(selector) === element && isVisible(button)
            && (button.matches(closeSelector) || (button.type === 'button'
                && /^(fechar|cancelar|voltar)$/i.test(button.textContent.trim()))));
    }

    function closeOverlay(element, usesLocalHistory = false) {
        const button = findCloseButton(element);
        if (usesLocalHistory && button) {
            button.click();
        } else if (element instanceof HTMLDialogElement) {
            if (typeof element.requestClose === 'function') element.requestClose();
            else if (element.dispatchEvent(new Event('cancel', {cancelable: true}))) element.close();
        } else {
            button?.click();
        }
    }

    function sync() {
        const state = history.state;
        const localHistory = legacyKeys.some(key => state?.[key] && state[key] !== previousState?.[key]);
        const visible = new Set([...document.querySelectorAll(selector)].filter(element =>
            isVisible(element) && (element instanceof HTMLDialogElement || findCloseButton(element))));

        for (const element of layers.keys()) {
            if (!visible.has(element)) layers.delete(element);
        }
        for (const element of visible) {
            if (!layers.has(element)) {
                layers.set(element, localHistory || element.hasAttribute('data-back-history'));
            }
        }

        if (!traversing) {
            const hasManagedLayer = [...layers.values()].includes(false);
            if (hasManagedLayer && !token) {
                token = crypto.randomUUID?.() ?? String(Date.now());
                history.pushState({...state, [historyKey]: token}, '');
            } else if (!hasManagedLayer && token && state?.[historyKey] === token) {
                traversing = true;
                history.back();
            }
        }
        previousState = history.state;
    }

    window.addEventListener('popstate', event => {
        closingLegacy = false;
        if (token && event.state?.[historyKey] !== token) {
            if (!traversing) {
                const top = [...layers.keys()].at(-1);
                if (top && !layers.get(top)) closeOverlay(top);
            }
            token = undefined;
            traversing = false;
            queueMicrotask(sync);
        }
        previousState = event.state;
    });

    window.sgcOverlayBack = () => {
        if (traversing || closingLegacy) return true;
        sync();
        const top = [...layers.keys()].at(-1);
        if ((!top || layers.get(top)) && legacyKeys.some(key => history.state?.[key])) {
            closingLegacy = true;
            history.back();
            return true;
        }
        if (!top) return traversing;
        closeOverlay(top, layers.get(top));
        return true;
    };

    const observer = new MutationObserver(sync);
    observer.observe(document.documentElement, {
        subtree: true, childList: true, attributes: true,
        attributeFilter: ['open', 'class', 'style', 'hidden', 'aria-hidden', 'aria-modal', 'inert'],
    });
    sync();
})();
