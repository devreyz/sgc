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

function documentTitle(fileName, fallback = 'Documento SGC') {
    const normalizedFallback = String(fallback || '').trim();
    if (normalizedFallback && !/^(documento|comprovante)\s+sgc$/i.test(normalizedFallback)) {
        return normalizedFallback;
    }

    const name = decodeURIComponent(String(fileName || ''))
        .replace(/\.pdf$/i, '')
        .replace(/[-_]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    return name ? name.replace(/\b\w/g, (letter) => letter.toUpperCase()) : 'Documento SGC';
}

let navigationLoading = null;

function ensureNavigationLoading() {
    if (navigationLoading) return navigationLoading;
    const overlay = document.createElement('div');
    overlay.id = 'sgc-navigation-loading';
    overlay.hidden = true;
    overlay.setAttribute('aria-live', 'polite');
    overlay.innerHTML = '<div><span aria-hidden="true"></span><strong>Carregando</strong><small>Preparando a próxima tela</small></div>';
    const style = document.createElement('style');
    style.textContent = `
        #sgc-navigation-loading{position:fixed;inset:0;z-index:2147482999;display:grid;place-items:center;background:rgba(8,39,27,.18);backdrop-filter:blur(2px);opacity:1;transition:opacity .16s ease}
        #sgc-navigation-loading[hidden]{display:none}
        #sgc-navigation-loading>div{display:grid;justify-items:center;gap:8px;min-width:178px;padding:22px 24px;border-radius:22px;background:#176146;color:#fff;box-shadow:0 18px 50px rgba(8,46,31,.28);animation:sgc-nav-enter .18s cubic-bezier(.2,.8,.2,1)}
        #sgc-navigation-loading span{width:30px;height:30px;border:3px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:sgc-nav-spin .85s linear infinite}
        #sgc-navigation-loading strong{font:700 14px/1.1 system-ui}#sgc-navigation-loading small{font:500 12px/1.2 system-ui;color:#d8f2e4}
        @keyframes sgc-nav-spin{to{transform:rotate(360deg)}}@keyframes sgc-nav-enter{from{transform:translateY(8px) scale(.96);opacity:0}to{transform:none;opacity:1}}
    `;
    document.head.appendChild(style);
    document.body.appendChild(overlay);
    navigationLoading = overlay;
    return overlay;
}

function showNavigationLoading(message = 'Carregando', detail = 'Preparando a próxima tela') {
    if (isNativeAndroid()) {
        window.Capacitor?.Plugins?.NativeNavigation?.show?.({ message }).catch?.(() => {});
        return;
    }
    const overlay = ensureNavigationLoading();
    overlay.querySelector('strong').textContent = message;
    overlay.querySelector('small').textContent = detail;
    overlay.hidden = false;
}

function hideNavigationLoading() {
    if (navigationLoading) navigationLoading.hidden = true;
    if (isNativeAndroid()) {
        window.Capacitor?.Plugins?.NativeNavigation?.hide?.().catch?.(() => {});
    }
}

window.SgcNavigation = Object.freeze({ show: showNavigationLoading, hide: hideNavigationLoading });

function downloadInBrowser(blob, fileName) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    link.dataset.sgcDirectDownload = 'true';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 60000);
}

let webPdfState = null;

function defaultDocumentPath(title = '') {
    const now = new Date();
    const year = String(now.getFullYear());
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const project = String(title || 'Documentos')
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') || 'Documentos';
    return `Comprovantes/${year}/${month}/${project}`;
}

function closeWebPdfViewer() {
    const viewer = document.getElementById('sgc-web-pdf-viewer');
    if (viewer) viewer.hidden = true;
    if (webPdfState?.url) URL.revokeObjectURL(webPdfState.url);
    webPdfState = null;
    document.documentElement.style.overflow = '';
}

function ensureWebPdfViewer() {
    let viewer = document.getElementById('sgc-web-pdf-viewer');
    if (viewer) return viewer;

    viewer = document.createElement('section');
    viewer.id = 'sgc-web-pdf-viewer';
    viewer.hidden = true;
    viewer.setAttribute('role', 'dialog');
    viewer.setAttribute('aria-modal', 'true');
    viewer.setAttribute('aria-label', 'Visualizador de PDF');
    viewer.innerHTML = `
        <header class="sgc-pdf-toolbar">
            <div class="sgc-pdf-heading"><span>Documento</span><strong data-sgc-pdf-title></strong></div>
            <nav aria-label="Ações do documento">
                <button type="button" data-sgc-pdf-download>Baixar</button>
                <button type="button" data-sgc-pdf-share>Compartilhar</button>
                <button type="button" data-sgc-pdf-print>Imprimir</button>
                <button type="button" data-sgc-pdf-close>Fechar</button>
            </nav>
        </header>
        <iframe title="Conteúdo do PDF" data-sgc-pdf-frame></iframe>
    `;
    const style = document.createElement('style');
    style.textContent = `
        #sgc-web-pdf-viewer{position:fixed;inset:0;z-index:2147482000;display:grid;grid-template-rows:auto minmax(0,1fr);background:#eef4f0;color:#102018;padding-top:env(safe-area-inset-top);padding-bottom:env(safe-area-inset-bottom)}
        #sgc-web-pdf-viewer[hidden]{display:none}
        .sgc-pdf-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;background:#fff;border-bottom:1px solid #dce6df;box-shadow:0 5px 20px rgba(15,35,24,.1)}
        .sgc-pdf-heading{display:grid;min-width:0}.sgc-pdf-heading span{font-size:11px;color:#718078}.sgc-pdf-heading strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:15px}
        .sgc-pdf-toolbar nav{display:flex;gap:7px;overflow-x:auto;scrollbar-width:none}.sgc-pdf-toolbar button{min-height:38px;padding:7px 12px;border:1px solid #b9cec1;border-radius:10px;background:#fff;color:#173d32;font:700 12px/1 system-ui;white-space:nowrap}.sgc-pdf-toolbar button[data-sgc-pdf-close]{background:#173d32;color:#fff;border-color:#173d32}
        #sgc-web-pdf-viewer iframe{width:100%;height:100%;border:0;background:#e7ece9}
        @media(max-width:720px){.sgc-pdf-toolbar{align-items:stretch;flex-direction:column}.sgc-pdf-toolbar nav{width:100%}.sgc-pdf-toolbar button{flex:1}.sgc-pdf-heading span{display:none}}
    `;
    document.head.appendChild(style);
    document.body.appendChild(viewer);
    viewer.querySelector('[data-sgc-pdf-close]').addEventListener('click', closeWebPdfViewer);
    viewer.querySelector('[data-sgc-pdf-download]').addEventListener('click', () => {
        if (webPdfState) downloadInBrowser(webPdfState.blob, webPdfState.fileName);
    });
    viewer.querySelector('[data-sgc-pdf-share]').addEventListener('click', () => {
        if (webPdfState) window.SgcDocuments.sharePdf(webPdfState.blob, webPdfState.fileName, webPdfState.title);
    });
    viewer.querySelector('[data-sgc-pdf-print]').addEventListener('click', () => {
        const frame = viewer.querySelector('[data-sgc-pdf-frame]');
        try { frame.contentWindow.focus(); frame.contentWindow.print(); }
        catch (_) { window.open(webPdfState?.url, '_blank', 'noopener,noreferrer'); }
    });
    return viewer;
}

function openInBrowser(blob, fileName, title) {
    closeWebPdfViewer();
    const viewer = ensureWebPdfViewer();
    const url = URL.createObjectURL(blob);
    webPdfState = { blob, fileName, title, url };
    viewer.querySelector('[data-sgc-pdf-title]').textContent = title;
    viewer.querySelector('[data-sgc-pdf-frame]').src = `${url}#view=FitH&toolbar=0`;
    viewer.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    hideNavigationLoading();
}

window.SgcDocuments = {
    async openPdf(blob, fileName, title = 'Documento SGC', options = {}) {
        const native = window.Capacitor?.Plugins?.NativeDocument;
        const resolvedTitle = documentTitle(fileName, options.documentTitle || title);
        showNavigationLoading('Abrindo documento', 'Preparando o visualizador');
        if (isNativeAndroid() && native?.openPdf) {
            try {
                await native.openPdf({
                    base64: await base64FromBlob(blob), fileName, title: resolvedTitle,
                    relativePath: options.relativePath || defaultDocumentPath(resolvedTitle),
                    origin: options.origin || '',
                });
            } catch (error) {
                hideNavigationLoading();
                throw error;
            }
            return;
        }
        openInBrowser(blob, fileName, resolvedTitle);
    },
    async downloadPdf(blob, fileName) {
        const native = window.Capacitor?.Plugins?.NativeDocument;
        if (isNativeAndroid() && native?.downloadPdf) {
            await native.downloadPdf({ base64: await base64FromBlob(blob), fileName, relativePath: defaultDocumentPath() });
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
        openInBrowser(blob, fileName, 'Documento SGC');
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
        return {
            blob: await response.blob(), fileName: name,
            title: response.headers.get('x-sgc-document-title') || documentTitle(name, title),
            relativePath: response.headers.get('x-sgc-document-path') || defaultDocumentPath(title),
            origin: response.headers.get('x-sgc-document-origin') || '',
        };
    },
};

function isNativeAndroid() {
    return Boolean(
        window.Capacitor?.isNativePlatform?.()
        && window.Capacitor?.getPlatform?.() === 'android'
    );
}

function isInstalledPwa() {
    return window.matchMedia?.('(display-mode: standalone)').matches === true
        || window.navigator.standalone === true;
}

window.SgcPlatform = Object.freeze({
    kind: isNativeAndroid() ? 'android' : (isInstalledPwa() ? 'pwa' : 'web'),
    nativeAndroid: isNativeAndroid(),
    installedPwa: isInstalledPwa(),
    canShareFiles: Boolean(navigator.share && navigator.canShare),
    canPrint: typeof window.print === 'function',
});

// A WebView mostra imediatamente uma transição enquanto a próxima rota ainda
// está sendo buscada. Links de PDF possuem seu próprio fluxo acima.
document.addEventListener('click', (event) => {
    if (!isNativeAndroid() || event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest?.('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
    const target = new URL(link.href, window.location.href);
    if (target.origin !== window.location.origin || target.href === window.location.href || target.hash && target.pathname === window.location.pathname) return;
    if (link.matches('[data-sgc-pdf]') || /\/(?:pdf|print|preview|reprint)(?:[/?]|$)/i.test(target.pathname)) return;
    showNavigationLoading('Abrindo', 'Carregando a próxima tela');
}, true);

document.addEventListener('submit', (event) => {
    if (!isNativeAndroid() || event.defaultPrevented) return;
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.sgcNoNavigationLoading !== undefined) return;
    showNavigationLoading('Processando', 'Aguarde um instante');
}, true);

window.addEventListener('pageshow', hideNavigationLoading);
window.addEventListener('focus', hideNavigationLoading);
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') hideNavigationLoading();
});
window.addEventListener('offline', hideNavigationLoading);

// O Livewire/Filament transforma respostas de download em um link blob:
// temporário. PDFs passam pelo visualizador universal antes do download, o que
// também evita depender do gerenciador de downloads da WebView do Android.
function installLivewirePdfDownloadBridge() {
    if (HTMLAnchorElement.prototype.sgcPdfBridgeInstalled) return;

    const originalClick = HTMLAnchorElement.prototype.click;
    Object.defineProperty(HTMLAnchorElement.prototype, 'sgcPdfBridgeInstalled', {
        configurable: false,
        value: true,
    });

    HTMLAnchorElement.prototype.click = function sgcPdfAwareClick() {
        const fileName = String(this.download || '');
        const isPdfBlob = this.href.startsWith('blob:') && /\.pdf$/i.test(fileName);
        const usesAppViewer = isNativeAndroid() || isInstalledPwa();
        if (!usesAppViewer || !isPdfBlob || this.dataset.sgcDirectDownload === 'true') {
            return originalClick.call(this);
        }

        fetch(this.href)
            .then((response) => response.blob())
            .then((blob) => window.SgcDocuments.openPdf(
                blob,
                fileName || 'documento-sgc.pdf',
                fileName.replace(/\.pdf$/i, '').replace(/[-_]+/g, ' ') || 'Documento SGC',
                { relativePath: defaultDocumentPath(document.title) },
            ))
            .catch(() => originalClick.call(this));

        return undefined;
    };
}

installLivewirePdfDownloadBridge();

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
    if (!link || link.dataset.sgcPdfHandled === 'true') return;
    const href = new URL(link.href, window.location.href);
    const hint = `${link.textContent || ''} ${link.title || ''}`;
    const looksLikePdf = link.matches('[data-sgc-pdf]')
        || /\/(?:pdf|print|preview|reprint)(?:[/?]|$)/i.test(href.pathname)
        || (/\/download(?:[/?]|$)/i.test(href.pathname) && /pdf|comprovante|recibo|imprimir|visualizar/i.test(hint));
    if (href.origin !== window.location.origin || !looksLikePdf) return;
    // No navegador tradicional preserva a resposta inline e o nome fornecido
    // pelo próprio servidor. Android e PWA usam o visualizador unificado.
    if (!isNativeAndroid() && !isInstalledPwa()) return;

    event.preventDefault();
    link.dataset.sgcPdfHandled = 'true';
    showNavigationLoading('Abrindo documento', 'Buscando o arquivo');
    try {
        const documentPdf = await window.SgcDocuments.fetchPdf(href.toString(), link.getAttribute('title') || 'Documento SGC');
        if (documentPdf) await window.SgcDocuments.openPdf(documentPdf.blob, documentPdf.fileName, documentPdf.title, { relativePath: documentPdf.relativePath, origin: documentPdf.origin });
        else window.location.assign(href.toString());
    } catch (error) {
        hideNavigationLoading();
        window.appToast?.(error.message || 'Não foi possível abrir o documento.', 'error');
    } finally {
        delete link.dataset.sgcPdfHandled;
    }
});
