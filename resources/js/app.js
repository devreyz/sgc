import "./bootstrap";
import "./pwa-notifications";
import { Passkeys } from '@laravel/passkeys';
import pdfWorkerUrl from 'pdfjs-dist/legacy/build/pdf.worker.min.mjs?url';
import { documentText, documentFilename } from './document-text';
import {
    browserSupportsWebAuthn,
    startAuthentication,
} from '@simplewebauthn/browser';

const nativeFetch = window.fetch.bind(window);
let csrfRefreshPromise = null;
let csrfLastRefreshAt = 0;

function currentCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function applyCsrfToken(token) {
    if (!token) return '';

    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
    document.querySelectorAll('input[name="_token"]').forEach(input => {
        input.value = token;
    });
    window.dispatchEvent(new CustomEvent('sgc:csrf-refreshed', {
        detail: { token },
    }));

    return token;
}

async function refreshCsrfToken(options = {}) {
    const force = options.force === true;
    if (!force && Date.now() - csrfLastRefreshAt < 15000 && currentCsrfToken()) {
        return currentCsrfToken();
    }
    if (csrfRefreshPromise) return csrfRefreshPromise;

    csrfRefreshPromise = nativeFetch(`/session/csrf-token?_=${Date.now()}`, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    }).then(async response => {
        if (!response.ok) {
            throw new Error('Não foi possível atualizar a sessão.');
        }
        const payload = await response.json();
        csrfLastRefreshAt = Date.now();
        return applyCsrfToken(payload.csrf_token);
    }).finally(() => {
        csrfRefreshPromise = null;
    });

    return csrfRefreshPromise;
}

function isUnsafeSameOriginRequest(input, init = {}) {
    const url = new URL(input instanceof Request ? input.url : String(input), window.location.href);
    const method = String(init.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
    return url.origin === window.location.origin && !['GET', 'HEAD', 'OPTIONS'].includes(method);
}

function csrfRequestOptions(input, init, token) {
    const existing = init.headers || (input instanceof Request ? input.headers : undefined);
    const headers = new Headers(existing || {});
    if (token) headers.set('X-CSRF-TOKEN', token);
    headers.set('X-Requested-With', headers.get('X-Requested-With') || 'XMLHttpRequest');
    return { ...init, credentials: init.credentials || 'same-origin', headers };
}

window.fetch = async function csrfAwareFetch(input, init = {}) {
    if (!isUnsafeSameOriginRequest(input, init)) {
        return nativeFetch(input, init);
    }

    const requestInput = input instanceof Request ? input.clone() : input;
    const retryInput = input instanceof Request ? input.clone() : input;
    let response = await nativeFetch(
        requestInput,
        csrfRequestOptions(requestInput, init, currentCsrfToken()),
    );

    if (response.status !== 419) return response;

    const token = await refreshCsrfToken({ force: true });
    response = await nativeFetch(
        retryInput,
        csrfRequestOptions(retryInput, init, token),
    );

    return response;
};

window.SgcCsrf = Object.freeze({
    token: currentCsrfToken,
    refresh: refreshCsrfToken,
    fetch: (...args) => window.fetch(...args),
});

window.SgcPasskeys = Passkeys;
window.SgcPreparedPasskey = Object.freeze({
    isSupported: () => browserSupportsWebAuthn(),
    authenticate: options => startAuthentication({ optionsJSON: options }),
});
window.dispatchEvent(new CustomEvent('sgc:passkeys-ready'));
let pdfJsPromise = null;

function loadPdfJs() {
    if (!pdfJsPromise) {
        pdfJsPromise = import('pdfjs-dist/legacy/build/pdf.mjs').then(module => {
            module.GlobalWorkerOptions.workerSrc = pdfWorkerUrl;
            return module;
        }).catch(error => {
            pdfJsPromise = null;
            throw error;
        });
    }
    return pdfJsPromise;
}

function base64FromBlob(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onerror = () => reject(new Error('Não foi possível preparar o documento.'));
        reader.onload = () => resolve(String(reader.result));
        reader.readAsDataURL(blob);
    });
}

function documentTitle(fileName, fallback = 'Documento SGC') {
    const normalizedFallback = documentText(fallback).trim();
    if (normalizedFallback && !/^(documento|comprovante)\s+sgc$/i.test(normalizedFallback)) {
        return normalizedFallback;
    }

    const name = documentText(fileName)
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
    if (viewer) {
        viewer.hidden = true;
        const pages = viewer.querySelector('[data-sgc-pdf-pages]');
        if (pages) pages.replaceChildren();
    }
    webPdfState?.renderTask?.cancel();
    webPdfState?.loadingTask?.destroy().catch(() => {});
    if (!webPdfState) return;
    if (webPdfState.url) URL.revokeObjectURL(webPdfState.url);
    document.documentElement.style.overflow = webPdfState.previousOverflow;
    webPdfState.previousFocus?.focus({preventScroll: true});
    webPdfState = null;
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
            <i class="ph ph-file-pdf sgc-pdf-file-icon" aria-hidden="true"></i>
            <strong class="sgc-pdf-heading" data-sgc-pdf-title></strong>
            <details class="sgc-pdf-actions" data-sgc-pdf-actions>
                <summary aria-label="Mais ações" title="Mais ações"><i class="ph ph-dots-three-vertical" aria-hidden="true"></i></summary>
                <div aria-label="Ações do documento">
                    <button type="button" data-sgc-pdf-download><i class="ph ph-download-simple" aria-hidden="true"></i> Baixar</button>
                    <button type="button" data-sgc-pdf-open><i class="ph ph-arrow-square-out" aria-hidden="true"></i> Abrir em nova aba</button>
                    <button type="button" data-sgc-pdf-share><i class="ph ph-share-network" aria-hidden="true"></i> Compartilhar</button>
                    <button type="button" data-sgc-pdf-print><i class="ph ph-printer" aria-hidden="true"></i> Imprimir</button>
                </div>
            </details>
            <button type="button" data-sgc-pdf-close aria-label="Fechar visualizador"><i class="ph ph-x" aria-hidden="true"></i></button>
        </header>
        <div class="sgc-pdf-controls" data-sgc-pdf-controls hidden>
            <div role="group" aria-label="Páginas">
                <button type="button" data-sgc-pdf-prev aria-label="Página anterior"><i class="ph ph-caret-left" aria-hidden="true"></i></button>
                <label><input type="number" min="1" value="1" inputmode="numeric" data-sgc-pdf-page aria-label="Ir para a página"> / <span data-sgc-pdf-total></span></label>
                <button type="button" data-sgc-pdf-next aria-label="Próxima página"><i class="ph ph-caret-right" aria-hidden="true"></i></button>
            </div>
            <div role="group" aria-label="Zoom">
                <button type="button" data-sgc-pdf-minus aria-label="Diminuir zoom"><i class="ph ph-minus" aria-hidden="true"></i></button>
                <button type="button" data-sgc-pdf-fit aria-label="Ajustar à largura" title="Ajustar à largura">100%</button>
                <button type="button" data-sgc-pdf-plus aria-label="Aumentar zoom"><i class="ph ph-plus" aria-hidden="true"></i></button>
            </div>
        </div>
        <div class="sgc-pdf-progress" data-sgc-pdf-progress hidden>
            <span role="status" data-sgc-pdf-progress-text></span><progress aria-label="Progresso da prévia"></progress>
        </div>
        <div class="sgc-pdf-pages" data-sgc-pdf-pages aria-label="Páginas do PDF"></div>
    `;
    const style = document.createElement('style');
    style.textContent = `
        #sgc-web-pdf-viewer{position:fixed;inset:0;z-index:2147482000;display:grid;grid-template-rows:auto auto auto minmax(0,1fr);background:var(--ui-color-canvas,#eef4f0);color:var(--ui-color-text,#102018);padding:env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left)}
        #sgc-web-pdf-viewer[hidden],#sgc-web-pdf-viewer [hidden]{display:none}
        .sgc-pdf-toolbar,.sgc-pdf-controls{display:flex;align-items:center;gap:8px;padding:5px 10px;background:var(--ui-color-surface,#fff);border-bottom:1px solid var(--ui-color-border,#dce6df)}
        .sgc-pdf-toolbar{position:relative;z-index:2}
        .sgc-pdf-file-icon{flex:none;color:var(--ui-color-primary-strong,#173d32);font-size:1.3rem}
        .sgc-pdf-heading{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.95rem}
        .sgc-pdf-actions{position:relative;flex:none}
        .sgc-pdf-actions summary{width:44px;min-height:44px;display:grid;place-items:center;cursor:pointer;list-style:none;border-radius:.5rem;background:var(--ui-color-surface-soft,#eef4f0)}
        .sgc-pdf-actions summary::-webkit-details-marker{display:none}
        .sgc-pdf-actions summary i{font-size:1.25rem}
        .sgc-pdf-actions>div{position:absolute;inset:calc(100% + 6px) 0 auto auto;display:grid;min-width:11rem;padding:4px;border-radius:.65rem;border:1px solid var(--ui-color-border,#dce6df);background:var(--ui-color-surface,#fff);box-shadow:0 6px 22px #0002}
        .sgc-pdf-controls>div{display:flex;align-items:center;gap:3px;min-width:0}
        #sgc-web-pdf-viewer button{min-height:44px;min-width:40px;padding:0 .55rem;border:1px solid var(--ui-color-border,#dce6df);border-radius:.5rem;background:var(--ui-color-surface-soft,#eef4f0);color:inherit;font-weight:700;font-size:.8rem;white-space:nowrap}
        #sgc-web-pdf-viewer .sgc-pdf-actions button{display:flex;align-items:center;gap:.5rem;text-align:left;border:0;background:transparent}
        #sgc-web-pdf-viewer .sgc-pdf-actions button:hover{background:var(--ui-color-surface-soft,#eef4f0)}
        #sgc-web-pdf-viewer button:disabled{opacity:.48}
        #sgc-web-pdf-viewer button i{font-size:1.05rem}
        #sgc-web-pdf-viewer [data-sgc-pdf-close]{min-width:44px;background:var(--sgc-accent);color:var(--sgc-on-accent)}
        .sgc-pdf-controls{justify-content:center;flex-wrap:wrap;column-gap:14px}
        .sgc-pdf-controls label{font-size:.8rem;white-space:nowrap}
        .sgc-pdf-controls input{width:3.2rem;height:40px;padding:.15rem;text-align:center;background:var(--ui-color-surface-soft,#eef4f0);border:1px solid var(--ui-color-border,#dce6df);border-radius:.5rem;color:inherit}
        .sgc-pdf-progress{padding:.4rem .75rem;background:var(--ui-color-surface,#fff);font-size:.8rem}
        .sgc-pdf-progress progress{display:block;width:100%;height:.55rem;accent-color:var(--ui-color-primary-strong,#173d32)}
        .sgc-pdf-pages{grid-row:4;min-height:0;overflow:auto;padding:8px;background:var(--ui-color-canvas-deep,#dfe7e2);overscroll-behavior:contain;touch-action:pan-x pan-y;overflow-anchor:none}
        .sgc-pdf-pages canvas{display:block;max-width:none;margin:0 auto 8px;background:#fff;box-shadow:0 2px 10px rgba(15,35,24,.16)}
        .sgc-pdf-pages>p{padding:24px;font-size:.85rem}
        @media(max-width:759px){
            .sgc-pdf-heading{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;white-space:normal;overflow-wrap:anywhere;line-height:1.2}
        }
        @media(min-width:760px){
            #sgc-web-pdf-viewer{grid-template-columns:minmax(0,1fr) auto}
            .sgc-pdf-controls{grid-column:2;grid-row:1}
            .sgc-pdf-pages,.sgc-pdf-progress{grid-column:1/-1}
        }
        @media print {
            html:has(#sgc-web-pdf-viewer:not([hidden])) { overflow:visible !important; }
            body:has(> #sgc-web-pdf-viewer:not([hidden])) > :not(#sgc-web-pdf-viewer) { display:none !important; }
            #sgc-web-pdf-viewer:not([hidden]), #sgc-web-pdf-viewer .sgc-pdf-pages { display:block;position:static;overflow:visible;padding:0;background:#fff; }
            #sgc-web-pdf-viewer :is(.sgc-pdf-toolbar,.sgc-pdf-controls,.sgc-pdf-progress) { display:none; }
            #sgc-web-pdf-viewer canvas { width:100% !important;height:auto !important;max-height:94vh;object-fit:contain;margin:0 auto;box-shadow:none;break-inside:avoid; }
            #sgc-web-pdf-viewer canvas:not(:last-child) { break-after:page; }
        }
    `;
    document.head.appendChild(style);
    document.body.appendChild(viewer);
    const actions = viewer.querySelector('[data-sgc-pdf-actions]');
    viewer.querySelector('[data-sgc-pdf-close]').addEventListener('click', closeWebPdfViewer);
    viewer.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (actions.open) {
            actions.open = false;
            actions.querySelector('summary').focus();
        } else closeWebPdfViewer();
    });
    viewer.addEventListener('click', event => {
        if (!actions.contains(event.target)) actions.open = false;
    });
    actions.addEventListener('click', event => {
        if (event.target.closest('button')) actions.open = false;
    });
    viewer.querySelector('[data-sgc-pdf-download]').addEventListener('click', () => {
        if (webPdfState) downloadInBrowser(webPdfState.blob, webPdfState.fileName);
    });
    viewer.querySelector('[data-sgc-pdf-open]').addEventListener('click', () => {
        if (webPdfState?.url) window.open(webPdfState.url, '_blank', 'noopener,noreferrer');
    });
    viewer.querySelector('[data-sgc-pdf-share]').addEventListener('click', () => {
        if (webPdfState) window.SgcDocuments.sharePdf(webPdfState.blob, webPdfState.fileName, webPdfState.title);
    });
    viewer.querySelector('[data-sgc-pdf-print]').addEventListener('click', () => window.print());
    const host = viewer.querySelector('[data-sgc-pdf-pages]');
    const pageInput = viewer.querySelector('[data-sgc-pdf-page]');
    pageInput.addEventListener('change', () => goToPdfPage(Number(pageInput.value)));
    viewer.querySelector('[data-sgc-pdf-prev]').addEventListener('click', () => goToPdfPage(webPdfState.page - 1));
    viewer.querySelector('[data-sgc-pdf-next]').addEventListener('click', () => goToPdfPage(webPdfState.page + 1));
    viewer.querySelector('[data-sgc-pdf-minus]').addEventListener('click', () => zoomPdf(webPdfState.zoom - .25));
    viewer.querySelector('[data-sgc-pdf-fit]').addEventListener('click', () => zoomPdf(1));
    viewer.querySelector('[data-sgc-pdf-plus]').addEventListener('click', () => zoomPdf(webPdfState.zoom + .25));
    bindPdfPinch(host);
    host.addEventListener('scroll', () => {
        const state = webPdfState;
        if (!state?.pdf || state.rendering || state.pinching) return;
        const target = host.getBoundingClientRect().top + host.clientHeight * .4;
        const visible = Array.from(host.children).find(canvas => canvas.getBoundingClientRect().bottom >= target);
        if (visible?.dataset.page) setPdfPage(state, Number(visible.dataset.page));
    }, {passive: true});
    let resizeTimer;
    if (window.ResizeObserver) new ResizeObserver(() => {
        clearTimeout(resizeTimer);
        const state = webPdfState;
        if (!state?.pdf || state.pinching || host.clientWidth === state.width) return;
        resizeTimer = setTimeout(() => {
            if (state === webPdfState) renderPdfPages(viewer, state);
        }, 180);
    }).observe(host);

    return viewer;
}

function clampPdfZoom(value) {
    return Math.max(.5, Math.min(3, value));
}

function zoomPdf(value, preserveScroll = false) {
    const state = webPdfState;
    if (!state?.pdf) return;
    const zoom = clampPdfZoom(value);
    if (state.zoom === zoom && !preserveScroll) return;
    state.zoom = zoom;
    renderPdfPages(state.viewer, state, preserveScroll);
}

function bindPdfPinch(host) {
    let gesture;
    const midpoint = (first, second) => ({
        x: (first.clientX + second.clientX) / 2,
        y: (first.clientY + second.clientY) / 2,
        distance: Math.hypot(first.clientX - second.clientX, first.clientY - second.clientY),
    });
    const clampRatio = value => Math.max(0, Math.min(1, value));
    host.addEventListener('touchstart', event => {
        const state = webPdfState;
        if (event.touches.length !== 2 || !state?.pdf || state.rendering || !host.firstElementChild?.dataset.page) return;
        const start = midpoint(...Array.from(event.touches));
        if (start.distance < 1) return;
        event.preventDefault();
        const pages = Array.from(host.querySelectorAll('canvas[data-page]'));
        const page = pages.find(canvas => canvas.getBoundingClientRect().bottom >= start.y) || pages.at(-1);
        const bounds = page.getBoundingClientRect();
        const sizes = pages.map(canvas => ({canvas, width: parseFloat(canvas.style.width), height: parseFloat(canvas.style.height)}));
        state.revision++;
        state.renderTask?.cancel();
        state.pinching = true;
        gesture = {
            state, start, page, sizes, zoom: state.zoom,
            relativeX: clampRatio((start.x - bounds.left) / bounds.width),
            relativeY: clampRatio((start.y - bounds.top) / bounds.height),
        };
    }, {passive: false});
    host.addEventListener('touchmove', event => {
        if (!gesture || gesture.state !== webPdfState || event.touches.length !== 2) return;
        event.preventDefault();
        const center = midpoint(...Array.from(event.touches));
        const scale = clampPdfZoom(gesture.zoom * center.distance / gesture.start.distance) / gesture.zoom;
        gesture.commitZoom = gesture.zoom * scale;
        for (const {canvas, width, height} of gesture.sizes) {
            canvas.style.width = width * scale + 'px';
            canvas.style.height = height * scale + 'px';
        }
        const bounds = gesture.page.getBoundingClientRect();
        host.scrollLeft += bounds.left + gesture.relativeX * bounds.width - center.x;
        host.scrollTop += bounds.top + gesture.relativeY * bounds.height - center.y;
        gesture.state.viewer.querySelector('[data-sgc-pdf-fit]').textContent = Math.round(gesture.commitZoom * 100) + '%';
    }, {passive: false});
    const finish = event => {
        if (!gesture || (event.type === 'touchend' && event.touches.length >= 2)) return;
        const {state, commitZoom, zoom} = gesture;
        gesture = null;
        if (state !== webPdfState) return;
        state.pinching = false;
        zoomPdf(commitZoom ?? zoom, true);
    };
    host.addEventListener('touchend', finish);
    host.addEventListener('touchcancel', finish);
}

function setPdfPage(state, page) {
    state.page = page;
    state.viewer.querySelector('[data-sgc-pdf-page]').value = page;
    state.viewer.querySelector('[data-sgc-pdf-prev]').disabled = page <= 1;
    state.viewer.querySelector('[data-sgc-pdf-next]').disabled = page >= state.pdf.numPages;
}

function goToPdfPage(number) {
    const state = webPdfState;
    if (!state?.pdf) return;
    const page = Math.max(1, Math.min(state.pdf.numPages, Math.trunc(number) || 1));
    setPdfPage(state, page);
    state.viewer.querySelector('[data-page="' + page + '"]')?.scrollIntoView({block: 'start', inline: 'nearest'});
}

function showPdfProgress(viewer, message, value, max) {
    const progress = viewer.querySelector('[data-sgc-pdf-progress]');
    progress.hidden = !message;
    progress.querySelector('span').textContent = message;
    const bar = progress.querySelector('progress');
    if (max) {
        bar.max = max;
        bar.value = value;
    } else {
        bar.removeAttribute('value');
    }
}

function openInBrowser(blob, fileName, title) {
    closeWebPdfViewer();
    const viewer = ensureWebPdfViewer();
    const url = URL.createObjectURL(blob);
    const state = webPdfState = {
        blob, fileName, title, url, viewer, zoom: 1, page: 1, revision: 0,
        previousFocus: document.activeElement,
        previousOverflow: document.documentElement.style.overflow,
    };
    viewer.hidden = false;
    viewer.querySelector('[data-sgc-pdf-title]').textContent = title;
    viewer.querySelector('[data-sgc-pdf-title]').title = title;
    viewer.querySelector('[data-sgc-pdf-actions]').open = false;
    viewer.querySelector('[data-sgc-pdf-controls]').hidden = true;
    viewer.querySelector('[data-sgc-pdf-print]').disabled = true;
    showPdfProgress(viewer, 'Preparando o PDF…');
    document.documentElement.style.overflow = 'hidden';
    hideNavigationLoading();
    requestAnimationFrame(() => {
        if (state === webPdfState) viewer.querySelector('[data-sgc-pdf-close]').focus();
    });
    loadWebPdf(viewer, state);
}

async function loadWebPdf(viewer, state) {
    try {
        const pdfjsLib = await loadPdfJs();
        const data = new Uint8Array(await state.blob.arrayBuffer());
        if (state !== webPdfState) return;
        state.loadingTask = pdfjsLib.getDocument({data});
        state.pdf = await state.loadingTask.promise;
        if (state !== webPdfState) return;
        viewer.querySelector('[data-sgc-pdf-total]').textContent = state.pdf.numPages;
        viewer.querySelector('[data-sgc-pdf-page]').max = state.pdf.numPages;
        viewer.querySelector('[data-sgc-pdf-controls]').hidden = false;
        setPdfPage(state, 1);
        await renderPdfPages(viewer, state);
    } catch (error) {
        if (state === webPdfState) pdfRenderError(viewer, error);
    }
}

function pdfRenderError(viewer, error) {
    console.warn('SGC PDF rendering failed', error);
    showPdfProgress(viewer, '');
    viewer.querySelector('[data-sgc-pdf-pages]').innerHTML = '<p>Não foi possível renderizar este PDF. Use "Baixar" ou "Abrir em nova aba".</p>';
}

async function renderPdfPages(viewer, state, preserveScroll = false) {
    const host = viewer.querySelector('[data-sgc-pdf-pages]');
    const print = viewer.querySelector('[data-sgc-pdf-print]');
    const {pdf, zoom} = state;
    const revision = ++state.revision;
    const active = () => state === webPdfState && revision === state.revision;
    state.renderTask?.cancel();
    state.width = host.clientWidth;
    state.rendering = true;
    print.disabled = true;
    viewer.querySelector('[data-sgc-pdf-fit]').textContent = Math.round(zoom * 100) + '%';
    viewer.querySelector('[data-sgc-pdf-minus]').disabled = zoom <= .5;
    viewer.querySelector('[data-sgc-pdf-plus]').disabled = zoom >= 3;
    if (!preserveScroll) showPdfProgress(viewer, 'Renderizando páginas…', 0, pdf.numPages);
    const availableWidth = Math.max(1, Math.min(1100, state.width - 16));
    try {
        for (let number = 1; number <= pdf.numPages && active(); number += 1) {
            const page = await pdf.getPage(number);
            if (!active()) return;
            const initial = page.getViewport({scale: 1});
            const viewport = page.getViewport({scale: (availableWidth / initial.width) * zoom});
            const pixelBudget = Math.min(8_000_000, 60_000_000 / pdf.numPages);
            const density = Math.min(window.devicePixelRatio || 1, Math.sqrt(pixelBudget / (viewport.width * viewport.height)),
                4096 / Math.max(viewport.width, viewport.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width * density);
            canvas.height = Math.ceil(viewport.height * density);
            canvas.style.width = viewport.width + 'px';
            canvas.style.height = viewport.height + 'px';
            canvas.dataset.page = number;
            canvas.setAttribute('aria-label', 'Página ' + number + ' de ' + pdf.numPages);
            state.renderTask = page.render({canvas, viewport, transform: [density, 0, 0, density, 0, 0]});
            await state.renderTask.promise;
            if (!active()) return;
            const previous = host.children[number - 1];
            if (previous) previous.replaceWith(canvas); else host.appendChild(canvas);
            if (!preserveScroll && number === state.page && (number > 1 || host.scrollTop > 0)) goToPdfPage(number);
            if (!preserveScroll) showPdfProgress(viewer, 'Páginas renderizadas: ' + number + ' de ' + pdf.numPages, number, pdf.numPages);
            page.cleanup();
        }
        if (active()) {
            state.renderTask = null;
            state.rendering = false;
            showPdfProgress(viewer, '');
            print.disabled = false;
        }
    } catch (error) {
        if (active()) pdfRenderError(viewer, error);
    }
}

window.SgcDocuments = {
    async openPdf(blob, fileName, title = 'Documento SGC', options = {}) {
        const native = window.Capacitor?.Plugins?.NativeDocument;
        const resolvedTitle = documentTitle(fileName, options.documentTitle || title);
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
        } else {
            showNavigationLoading("Abrindo documento", "Preparando o visualizador");
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
    async fetchPdf(url, title = 'Documento SGC', options = {}) {
        const headers = new Headers(options.headers);
        if (!headers.has('Accept')) headers.set('Accept', 'application/pdf');
        headers.set('Cache-Control', 'no-cache');
        headers.set('Pragma', 'no-cache');
        const response = await fetch(url, {
            ...options,
            credentials: 'same-origin', cache: 'no-store',
            headers,
        });
        const type = response.headers.get('content-type') || '';
        if (!response.ok) {
            const error = type.includes('application/json') ? await response.json().catch(() => null) : null;
            throw new Error(error?.message || 'Não foi possível carregar o PDF.');
        }
        if (!type.includes('pdf')) return null;
        const disposition = response.headers.get('content-disposition') || '';
        const name = documentFilename(disposition);
        return {
            blob: await response.blob(), fileName: name,
            title: documentText(response.headers.get('x-sgc-document-title')) || documentTitle(name, title),
            relativePath: documentText(response.headers.get('x-sgc-document-path')) || defaultDocumentPath(title),
            origin: documentText(response.headers.get('x-sgc-document-origin')) || '',
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

function isMobileBrowser() {
    return /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
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
    if (link.matches('[data-sgc-pdf]') || /\/(?:pdf|print|preview|reprint|billing-sheet)(?:[/?]|$)/i.test(target.pathname)) return;
    showNavigationLoading('Abrindo', 'Carregando a próxima tela');
}, true);

document.addEventListener('submit', (event) => {
    if (!isNativeAndroid() || event.defaultPrevented) return;
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.matches('[data-sgc-no-navigation-loading], [data-sgc-pdf-form]')) return;
    showNavigationLoading('Processando', 'Aguarde um instante');
}, true);

window.addEventListener('pageshow', event => {
    hideNavigationLoading();
    refreshCsrfToken({ force: event.persisted }).catch(() => {});
});
window.addEventListener('focus', () => {
    hideNavigationLoading();
    refreshCsrfToken().catch(() => {});
});
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        hideNavigationLoading();
        refreshCsrfToken().catch(() => {});
    }
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
        const usesAppViewer = isNativeAndroid() || isInstalledPwa() || isMobileBrowser();
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
    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !form.matches('[data-sgc-pdf-form]')) return;
    const action = new URL(form.action, window.location.href);
    if (action.origin !== window.location.origin) return;
    event.preventDefault();
    if (form.dataset.sgcPdfHandled === 'true') return;

    const body = new FormData(form);
    // The shared fetch wrapper supplies the current CSRF header and retries a 419.
    body.delete('_token');
    const button = event.submitter;
    const feedback = form.querySelector('[data-sgc-pdf-error]');
    form.dataset.sgcPdfHandled = 'true';
    if (button) button.disabled = true;
    if (feedback) feedback.textContent = '';
    showNavigationLoading('Abrindo documento', 'Preparando o PDF');
    try {
        const pdf = await window.SgcDocuments.fetchPdf(action.href, form.dataset.sgcPdfForm, {
            method: 'POST', body, headers: { Accept: 'application/json, application/pdf' },
        });
        if (!pdf) throw new Error('A emissão não retornou um PDF. Confira se sua sessão está ativa.');
        await window.SgcDocuments.openPdf(pdf.blob, pdf.fileName, pdf.title, { relativePath: pdf.relativePath, origin: pdf.origin });
    } catch (error) {
        if (feedback) feedback.textContent = error.message;
        else window.appToast?.(error.message, 'error');
    } finally {
        hideNavigationLoading();
        if (button) button.disabled = false;
        delete form.dataset.sgcPdfHandled;
    }
});

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
        || /\/(?:pdf|print|preview|reprint|billing-sheet)(?:[/?]|$)/i.test(href.pathname)
        || (/\/download(?:[/?]|$)/i.test(href.pathname) && /pdf|comprovante|recibo|imprimir|visualizar/i.test(hint));
    if (href.origin !== window.location.origin || !looksLikePdf) return;
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    if (!link.matches('[data-sgc-pdf]') && !isNativeAndroid() && !isInstalledPwa() && !isMobileBrowser()) return;

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
