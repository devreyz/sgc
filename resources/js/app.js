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

function openInBrowser(blob, title, print = false) {
    const url = URL.createObjectURL(blob);
    const viewer = window.open(url, '_blank');
    if (!viewer) {
        downloadInBrowser(blob, title);
        return;
    }
    viewer.opener = null;
    if (print) {
        window.setTimeout(() => viewer.print(), 800);
    }
    window.setTimeout(() => URL.revokeObjectURL(url), 60000);
}

window.SgcDocuments = {
    async openPdf(blob, fileName, title = 'Documento SGC') {
        const native = window.Capacitor?.Plugins?.NativeDocument;
        if (isNativeAndroid() && native?.openPdf) {
            await native.openPdf({ base64: await base64FromBlob(blob), fileName, title });
            return;
        }
        openInBrowser(blob, fileName);
    },
    async downloadPdf(blob, fileName) {
        const native = window.Capacitor?.Plugins?.NativeDocument;
        if (isNativeAndroid() && native?.downloadPdf) {
            await native.downloadPdf({ base64: await base64FromBlob(blob), fileName });
            return;
        }
        downloadInBrowser(blob, fileName);
    },
    async sharePdf(blob, fileName, title = 'Documento SGC') {
        const native = window.Capacitor?.Plugins?.NativeDocument;
        if (isNativeAndroid() && native?.sharePdf) {
            await native.sharePdf({ base64: await base64FromBlob(blob), fileName, title });
            return;
        }
        const file = new File([blob], fileName, { type: 'application/pdf' });
        if (navigator.share && navigator.canShare?.({ files: [file] })) {
            await navigator.share({ title, files: [file] });
            return;
        }
        downloadInBrowser(blob, fileName);
    },
    async printPdf(blob, fileName = 'documento-sgc.pdf') {
        // O visualizador nativo contém a ação de impressão; no navegador o
        // próprio leitor de PDF oferece o diálogo de impressão.
        if (isNativeAndroid()) {
            await this.openPdf(blob, fileName, 'Documento SGC');
            return;
        }
        openInBrowser(blob, fileName, true);
    },
    async fetchPdf(url, title = 'Documento SGC') {
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/pdf' } });
        if (!response.ok) throw new Error('Não foi possível carregar o PDF.');
        const type = response.headers.get('content-type') || '';
        if (!type.includes('pdf')) return null;
        const disposition = response.headers.get('content-disposition') || '';
        const filenameMatch = disposition.match(/filename\*?=(?:UTF-8''|\")?([^;\"]+)/i);
        const name = filenameMatch?.[1]
            ? decodeURIComponent(filenameMatch[1].replace(/[\"]/g, ''))
            : 'documento-sgc.pdf';
        return { blob: await response.blob(), fileName: name, title };
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

// Rotas de impressão/visualização que retornam PDF passam pelo mesmo fluxo no
// Android. Links HTML normais continuam abrindo normalmente.
document.addEventListener('click', async (event) => {
    const link = event.target.closest?.('a[href]');
    if (!link || !isNativeAndroid() || link.dataset.sgcPdfHandled === 'true') return;
    const href = new URL(link.href, window.location.href);
    const looksLikePdf = link.matches('[data-sgc-pdf]')
        || /\/(?:pdf|print|preview|reprint|download)(?:[/?]|$)/i.test(href.pathname);
    if (href.origin !== window.location.origin || !looksLikePdf) return;

    event.preventDefault();
    link.dataset.sgcPdfHandled = 'true';
    try {
        const documentPdf = await window.SgcDocuments.fetchPdf(href.toString(), link.getAttribute('title') || 'Documento SGC');
        if (documentPdf) await window.SgcDocuments.openPdf(documentPdf.blob, documentPdf.fileName, documentPdf.title);
        else window.location.assign(href.toString());
    } catch (error) {
        window.appToast?.(error.message || 'Não foi possível abrir o documento.', 'error');
    } finally {
        delete link.dataset.sgcPdfHandled;
    }
});
