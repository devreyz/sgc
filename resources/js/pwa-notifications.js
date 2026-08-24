const config = window.SgcPwaConfig || {};
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
let deferredInstallPrompt = null;
let permissionPromptShown = false;

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

async function endpointHash(endpoint) {
    if (!window.crypto?.subtle) return null;
    const digest = await window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(endpoint));
    return [...new Uint8Array(digest)].map(value => value.toString(16).padStart(2, '0')).join('');
}

async function bindCurrentSubscription(current, status) {
    if (!current) return;
    const hash = await endpointHash(current.endpoint);
    if (hash && (status.session_endpoint_hashes || []).includes(hash)) return;

    const payload = current.toJSON();
    payload.contentEncoding = PushManager.supportedContentEncodings?.[0] || 'aes128gcm';
    await jsonRequest(config.pushStoreUrl, {method:'POST',body:JSON.stringify(payload)});
}

async function subscribePush(worker, publicKey) {
    const subscription = await worker.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: base64UrlToUint8Array(publicKey),
    });
    const payload = subscription.toJSON();
    payload.contentEncoding = PushManager.supportedContentEncodings?.[0] || 'aes128gcm';
    await jsonRequest(config.pushStoreUrl, {method:'POST',body:JSON.stringify(payload)});
    return subscription;
}

function showPermissionPrompt(worker, publicKey) {
    const dialog = document.getElementById('pushPermissionDialog');
    if (!dialog || permissionPromptShown || dialog.open) return;

    permissionPromptShown = true;
    const activate = dialog.querySelector('[data-push-permission-activate]');
    const later = dialog.querySelector('[data-push-permission-later]');
    const feedback = dialog.querySelector('[data-push-permission-feedback]');

    dialog.addEventListener('cancel', event => event.preventDefault(), {once:true});
    later?.addEventListener('click', () => dialog.close(), {once:true});
    activate?.addEventListener('click', async () => {
        activate.disabled = true;
        later.disabled = true;
        if (feedback) feedback.hidden = true;

        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') throw new Error('permission_denied');
            await subscribePush(worker, publicKey);
            dialog.close();
            await refreshPushControls();
        } catch (_) {
            activate.disabled = false;
            later.disabled = false;
            if (feedback) {
                feedback.textContent = Notification.permission === 'denied'
                    ? 'A permissão foi bloqueada nas configurações do navegador.'
                    : 'Não foi possível ativar as notificações agora.';
                feedback.hidden = false;
            }
        }
    });

    dialog.showModal();
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
    if (!config.pushStatusUrl || !config.pushStoreUrl) return;

    if (!('Notification' in window) || !('PushManager' in window)) {
        labels.forEach(label => label.textContent = 'Este navegador nao oferece suporte.');
        controls.forEach(control => { control.disabled = true; control.textContent = 'Indisponivel'; });
        return;
    }

    try {
        const status = await jsonRequest(config.pushStatusUrl);
        if (status.schema_ready === false) {
            labels.forEach(label => label.textContent = 'Atualizacao pendente no servidor.');
            controls.forEach(control => { control.disabled = true; control.textContent = 'Indisponivel'; });
            return;
        }
        if (!status.configured) {
            labels.forEach(label => label.textContent = 'Configuracao pendente no servidor.');
            controls.forEach(control => { control.disabled = true; control.textContent = 'Indisponivel'; });
            return;
        }

        const worker = await registration();
        let current = await worker.pushManager.getSubscription();
        await bindCurrentSubscription(current, status);
        if (!current && Notification.permission === 'granted') {
            current = await subscribePush(worker, status.public_key);
        } else if (!current && Notification.permission === 'default') {
            showPermissionPrompt(worker, status.public_key);
        }
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
            await subscribePush(worker, publicKey);
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
