import { PushNotifications } from '@capacitor/push-notifications';

const config = window.SgcPwaConfig || {};
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
let deferredInstallPrompt = null;
let permissionPromptShown = false;
let nativePushInitialized = false;
let nativeRegistrationTimeout = null;
let nativeTokenBindingPromise = null;
const nativeBindingSessionKey = 'sgc.native.push.bound.auth-session.v2';

function isNativeAndroid() {
    return Boolean(window.Capacitor?.isNativePlatform?.() && window.Capacitor?.getPlatform?.() === 'android');
}

function installationId() {
    const key = 'sgc.native.push.installation.v1';
    let value = localStorage.getItem(key);
    if (value) return value;

    value = window.crypto?.randomUUID?.();
    if (!value) {
        const bytes = window.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = [...bytes].map(item => item.toString(16).padStart(2, '0')).join('');
        value = `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
    }
    localStorage.setItem(key, value);
    return value;
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

async function nativeDeviceContext() {
    try {
        const context = await window.Capacitor?.Plugins?.NativeAuth?.getDiagnosticsContext?.();
        return {
            device_name: context?.device || null,
            app_version: context?.appVersion || null,
            os_version: context?.androidVersion || null,
        };
    } catch (_) {
        return {};
    }
}

async function bindNativeToken(token) {
    if (!config.nativePushStoreUrl || !token) return;
    if (nativeTokenBindingPromise) return nativeTokenBindingPromise;

    nativeTokenBindingPromise = (async () => {
        await jsonRequest(config.nativePushStoreUrl, {
            method: 'POST',
            body: JSON.stringify({ token, installation_id: installationId(), ...(await nativeDeviceContext()) }),
        });
        if (config.nativePushBindingScope) {
            sessionStorage.setItem(nativeBindingSessionKey, config.nativePushBindingScope);
        }
        window.dispatchEvent(new CustomEvent('sgc:native-push-bound'));
    })();

    try {
        return await nativeTokenBindingPromise;
    } finally {
        nativeTokenBindingPromise = null;
    }
}

function nativeTokenAlreadyBoundForSession() {
    return Boolean(
        config.nativePushBindingScope
        && sessionStorage.getItem(nativeBindingSessionKey) === config.nativePushBindingScope
    );
}

function safeNativeNotificationRoute(value) {
    if (typeof value !== 'string' || !/^\/[A-Za-z0-9_-]+\/notifications\/[0-9a-f-]{36}\/open$/i.test(value)) return null;
    const target = new URL(value, window.location.origin);
    return target.origin === window.location.origin ? target.href : null;
}

function showForegroundNotification(notification) {
    const banner = document.createElement('button');
    banner.type = 'button';
    banner.className = 'sgc-native-notification-banner';
    banner.setAttribute('aria-label', 'Abrir nova notificação');
    banner.innerHTML = `<strong></strong><span></span>`;
    banner.querySelector('strong').textContent = notification.title || 'Nova notificação';
    banner.querySelector('span').textContent = notification.body || 'Há uma nova atualização no SGC.';
    Object.assign(banner.style, {
        position:'fixed', top:'max(12px, env(safe-area-inset-top))', left:'12px', right:'12px', zIndex:'2147483000',
        display:'grid', gap:'4px', padding:'14px 16px', border:'0', borderRadius:'16px', textAlign:'left',
        color:'#fff', background:'#173d32', boxShadow:'0 14px 40px rgba(0,0,0,.28)', cursor:'pointer'
    });
    const route = safeNativeNotificationRoute(notification.data?.route);
    banner.onclick = () => { if (route) window.location.assign(route); else banner.remove(); };
    document.body.appendChild(banner);
    window.setTimeout(() => banner.remove(), 7000);
}

async function createNativeChannels() {
    const channels = [
        ['general_high_v2', 'Geral', 'Avisos gerais do SGC', 4],
        ['operations_high_v2', 'Operações', 'Entregas, estoque e operações', 4],
        ['documents_high_v2', 'Documentos', 'Comprovantes e documentos', 4],
        ['financial_high_v2', 'Financeiro', 'Atualizações financeiras importantes', 4],
    ];
    await Promise.all(channels.map(([id, name, description, importance]) =>
        PushNotifications.createChannel({ id, name, description, importance, visibility: 0, vibration: true })
    ));
}

async function registerNativePush() {
    await createNativeChannels();
    await PushNotifications.register();

    // Em reinstalações o evento `registration` pode chegar antes de a página
    // autenticada terminar de carregar. Consulte também o token atual uma vez
    // por abertura/sessão autenticada e refaça o vínculo com o Laravel.
    try {
        const current = await window.Capacitor?.Plugins?.NativeAuth?.getFcmToken?.();
        if (current?.token) await bindNativeToken(current.token);
    } catch (error) {
        window.SgcDiagnostics?.report({
            category:'push', stage:'token_reconciliation', message:error?.message || 'fcm_token_reconciliation_failed'
        });
    }
}

function showNativePermissionPrompt() {
    const dialog = document.getElementById('pushPermissionDialog');
    if (!dialog || permissionPromptShown || dialog.open) return;
    permissionPromptShown = true;
    const activate = dialog.querySelector('[data-push-permission-activate]');
    const later = dialog.querySelector('[data-push-permission-later]');
    const feedback = dialog.querySelector('[data-push-permission-feedback]');
    dialog.addEventListener('cancel', event => event.preventDefault(), { once:true });
    later?.addEventListener('click', () => dialog.close(), { once:true });
    activate?.addEventListener('click', async () => {
        activate.disabled = true;
        later.disabled = true;
        try {
            const result = await PushNotifications.requestPermissions();
            if (result.receive !== 'granted') throw new Error('permission_denied');
            await registerNativePush();
            dialog.close();
        } catch (_) {
            activate.disabled = false;
            later.disabled = false;
            if (feedback) {
                feedback.textContent = 'Não foi possível ativar. Verifique a permissão nas configurações do aparelho.';
                feedback.hidden = false;
            }
        }
    });
    dialog.showModal();
}

async function initializeNativePush() {
    if (nativePushInitialized || !config.nativePushStoreUrl) return;
    nativePushInitialized = true;

    // Uma navegação completa recria este módulo. Remova callbacks da página
    // anterior sem repetir o registro remoto do token.
    try { await PushNotifications.removeAllListeners(); } catch (_) {}

    await PushNotifications.addListener('registration', event => {
        if (nativeRegistrationTimeout) window.clearTimeout(nativeRegistrationTimeout);
        bindNativeToken(event.value).catch(error => window.SgcDiagnostics?.report({
            category:'push', stage:'token_binding', message:error?.message || 'push_binding_failed'
        }));
    });
    await PushNotifications.addListener('registrationError', error => window.SgcDiagnostics?.report({
        category:'push', stage:'native_registration', message:error?.error || 'push_registration_failed'
    }));
    await PushNotifications.addListener('pushNotificationReceived', notification => {
        showForegroundNotification(notification);
        refreshUnreadCount();
    });
    await PushNotifications.addListener('pushNotificationActionPerformed', action => {
        const route = safeNativeNotificationRoute(action.notification?.data?.route);
        if (route) window.location.assign(route);
    });

    const permission = await PushNotifications.checkPermissions();
    if (permission.receive === 'granted') {
        if (nativeTokenAlreadyBoundForSession()) return;
        nativeRegistrationTimeout = window.setTimeout(() => window.SgcDiagnostics?.report({
            category:'push', stage:'token_timeout', message:'fcm_registration_token_not_received'
        }), 15000);
        await registerNativePush();
    }
    else if (permission.receive === 'prompt' || permission.receive === 'prompt-with-rationale') showNativePermissionPrompt();
}

async function revokeNativePush() {
    try {
        if (config.nativePushDestroyUrl) {
            await jsonRequest(config.nativePushDestroyUrl, {
                method:'DELETE', body:JSON.stringify({ installation_id:installationId() })
            });
        }
    } finally {
        sessionStorage.removeItem(nativeBindingSessionKey);
        try { await PushNotifications.unregister(); } catch (_) {}
    }
}

window.SgcNativePush = Object.freeze({ revoke: revokeNativePush });

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

if (isNativeAndroid()) initializeNativePush().catch(error => window.SgcDiagnostics?.report({
    category:'push', stage:'initialization', message:error?.message || 'push_initialization_failed'
}));
else {
    // O SGC não usa mais Web Push. Navegador e PWA mantêm apenas a central
    // interna e nunca devem exibir ou solicitar permissão de notificações.
    document.getElementById('pushPermissionDialog')?.remove();
    registration().catch(() => {});
}
refreshUnreadCount();
