import "./bootstrap";
import "./pwa-notifications";
import { Passkeys } from '@laravel/passkeys';

window.SgcPasskeys = Passkeys;
window.dispatchEvent(new CustomEvent('sgc:passkeys-ready'));

function base64FromBlob(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onerror = () => reject(new Error('Não foi possível preparar o documento.'));
        reader.onload = () => resolve(String(reader.result));
        reader.readAsDataURL(blob);
    });
}

function downloadInBrowser(blob, fileName) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url; link.download = fileName; document.body.appendChild(link); link.click(); link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 60000);
}

window.SgcDocuments = {
    async openPdf(blob, fileName, title = 'Documento SGC') {
        const native = window.Capacitor?.Plugins?.NativeDocument;
        if (isNativeAndroid() && native?.openPdf) {
            await native.openPdf({ base64: await base64FromBlob(blob), fileName, title });
            return;
        }
        window.open(URL.createObjectURL(blob), '_blank', 'noopener,noreferrer');
    },
    async downloadPdf(blob, fileName) {
        const native = window.Capacitor?.Plugins?.NativeDocument;
        if (isNativeAndroid() && native?.downloadPdf) {
            await native.downloadPdf({ base64: await base64FromBlob(blob), fileName });
            return;
        }
        downloadInBrowser(blob, fileName);
    },
};

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
        const revokePush = window.SgcNativePush?.revoke?.();
        await Promise.race([
            Promise.allSettled([clearState, revokePush].filter(Boolean)),
            new Promise((resolve) => window.setTimeout(resolve, 1800)),
        ]);
    } catch (_) {
        /* O logout da sessão Laravel deve continuar mesmo se o provedor falhar. */
    }

    HTMLFormElement.prototype.submit.call(form);
});
