import "./bootstrap";
import "./pwa-notifications";
import { Passkeys } from '@laravel/passkeys';

window.SgcPasskeys = Passkeys;
window.dispatchEvent(new CustomEvent('sgc:passkeys-ready'));

function isNativeAndroid() {
    return Boolean(
        window.Capacitor?.isNativePlatform?.()
        && window.Capacitor?.getPlatform?.() === 'android'
    );
}

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !isNativeAndroid()) {
        return;
    }

    const action = new URL(form.action, window.location.href);
    if (action.origin !== window.location.origin || action.pathname !== '/logout') {
        return;
    }

    event.preventDefault();

    try {
        const clearState = window.Capacitor?.Plugins?.NativeAuth?.clearCredentialState?.();
        if (clearState) {
            await Promise.race([
                clearState,
                new Promise((resolve) => window.setTimeout(resolve, 1200)),
            ]);
        }
    } catch (_) {
        /* O logout da sessão Laravel deve continuar mesmo se o provedor falhar. */
    }

    HTMLFormElement.prototype.submit.call(form);
});
