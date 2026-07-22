const config = window.SgcPwaConfig || {};
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
let deferredInstallPrompt = null;

function base64UrlToUint8Array(value) {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map(char => char.charCodeAt(0)));
}

async function registration() {
    if (!('serviceWorker' in navigator)) return null;
    await navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' });
    return navigator.serviceWorker.ready;
}

async function jsonRequest(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {'Accept':'application/json','Content-Type':'application/json',...(csrf ? {'X-CSRF-TOKEN':csrf} : {})},
        ...options,
    });
    if (!response.ok) throw new Error('request_failed');
    return response.json();
}

async function refreshUnreadCount() {
    if (!config.unreadCountUrl) return;
    try {
        const { count } = await jsonRequest(config.unreadCountUrl);
        document.querySelectorAll('[data-notification-count]').forEach(badge => {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count < 1;
        });
    } catch (_) {}
}

async function refreshPushControls() {
    const controls = [...document.querySelectorAll('#push-toggle,[data-push-toggle]')];
    const labels = [...document.querySelectorAll('#push-status-label,[data-push-status]')];
    if (!controls.length) return;

    if (!('Notification' in window) || !('PushManager' in window)) {
        labels.forEach(label => label.textContent = 'Este navegador nao oferece suporte.');
        controls.forEach(control => { control.disabled = true; control.textContent = 'Indisponivel'; });
        return;
    }

    try {
        const status = await jsonRequest(config.pushStatusUrl);
        if (!status.configured) {
            labels.forEach(label => label.textContent = 'Configuracao pendente no servidor.');
            controls.forEach(control => { control.disabled = true; control.textContent = 'Indisponivel'; });
            return;
        }

        const worker = await registration();
        const current = await worker.pushManager.getSubscription();
        labels.forEach(label => label.textContent = current ? 'Ativas neste dispositivo.' : (Notification.permission === 'denied' ? 'Permissao bloqueada no navegador.' : 'Desativadas neste dispositivo.'));
        controls.forEach(control => {
            control.disabled = Notification.permission === 'denied';
            control.dataset.subscribed = current ? '1' : '0';
            control.textContent = current ? 'Desativar notificacoes' : 'Ativar notificacoes';
            control.onclick = () => togglePush(worker, status.public_key, current);
        });
    } catch (_) {
        labels.forEach(label => label.textContent = 'Nao foi possivel verificar agora.');
    }
}

async function togglePush(worker, publicKey, current) {
    const controls = [...document.querySelectorAll('#push-toggle,[data-push-toggle]')];
    controls.forEach(control => control.disabled = true);
    try {
        if (current) {
            await jsonRequest(config.pushDestroyUrl, {method:'DELETE',body:JSON.stringify({endpoint:current.endpoint})});
            await current.unsubscribe();
        } else {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') throw new Error('permission_denied');
            const subscription = await worker.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: base64UrlToUint8Array(publicKey),
            });
            const payload = subscription.toJSON();
            payload.contentEncoding = PushManager.supportedContentEncodings?.[0] || 'aes128gcm';
            await jsonRequest(config.pushStoreUrl, {method:'POST',body:JSON.stringify(payload)});
        }
        await refreshPushControls();
    } catch (_) {
        controls.forEach(control => control.disabled = false);
        document.querySelectorAll('#push-status-label,[data-push-status]').forEach(label => label.textContent = 'Nao foi possivel alterar a permissao.');
    }
}

window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    deferredInstallPrompt = event;
    document.querySelectorAll('[data-pwa-install]').forEach(button => button.hidden = false);
});

window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    document.querySelectorAll('[data-pwa-install]').forEach(button => button.hidden = true);
});

document.addEventListener('click', async event => {
    const button = event.target.closest('[data-pwa-install]');
    if (!button || !deferredInstallPrompt) return;
    await deferredInstallPrompt.prompt();
    deferredInstallPrompt = null;
    button.hidden = true;
});

window.addEventListener('notifications:changed', refreshUnreadCount);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshUnreadCount(); });

registration().catch(() => {});
refreshUnreadCount();
refreshPushControls();
