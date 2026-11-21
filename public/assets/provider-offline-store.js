const DB_NAME = 'sgc-provider-offline';
const MAX_AGE = 10 * 60 * 1000;
const page = document.querySelector('[data-provider-offline-url]');
const signal = typeof BroadcastChannel === 'function' ? new BroadcastChannel(DB_NAME) : null;
const signedOutKey = 'sgc-provider-offline-signed-out';
let opening, busy, renewedAt = 0;

export function requestedTenant() {
    if (page) return null;
    const url = new URL(location.href);
    const tenant = url.searchParams.get('tenant') || url.pathname.match(/^\/([^/]+)\/provider\//)?.[1];
    return tenant && /^[a-z0-9][a-z0-9_-]{0,100}$/i.test(tenant) ? tenant : null;
}

function database() {
    opening ??= new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => {
            request.result.createObjectStore('profile');
            request.result.createObjectStore('queue', {keyPath: 'id'});
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    return opening;
}

export async function transact(store, mode, start) {
    const db = await database();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(store, mode);
        const request = start(tx.objectStore(store));
        tx.oncomplete = () => resolve(request?.result);
        tx.onabort = tx.onerror = () => reject(tx.error || new Error('Não foi possível salvar no aparelho.'));
    });
}
const profile = () => transact('profile', 'readonly', store => store.get('active'));
const setProfile = value => transact('profile', 'readwrite', store => value ? store.put(value, 'active') : store.delete('active'));
export const saveOperation = row => transact('queue', 'readwrite', store => store.put(row));
export const discardOperation = id => transact('queue', 'readwrite', store => store.delete(id));
export async function guardedUpdate(row, previous, sending = false) {
    const db = await database();
    return new Promise((resolve, reject) => {
        const tx = db.transaction('queue', 'readwrite');
        const store = tx.objectStore('queue');
        const request = store.get(previous?.id || row.id);
        let saved = false;
        request.onsuccess = () => {
            const current = request.result;
            if (previous ? !current || current.revision !== previous.revision || (current.sent && !sending) : current) return;
            if (previous && (!row || previous.id !== row.id)) store.delete(previous.id);
            if (row) store.put(row);
            saved = true;
        };
        tx.oncomplete = () => resolve(saved);
        tx.onabort = tx.onerror = () => reject(tx.error || new Error('Não foi possível salvar no aparelho.'));
    });
}
export async function queued(scope) {
    return (await transact('queue', 'readonly', store => store.getAll())).filter(row => row.scope === scope);
}
export async function getBundle() {
    const bundle = await profile();
    if (hasOfflineAccess(bundle)) {
        const tenant = requestedTenant();
        return !tenant || tenant === bundle.tenant ? bundle : null;
    }
    if (bundle) await setProfile(null);
    return null;
}

export function hasOfflineAccess(bundle) {
    return Boolean(bundle && !localStorage.getItem(signedOutKey) && Date.parse(bundle.expiresAt) > Date.now());
}

function notify() {
    window.dispatchEvent(new Event('sgc-provider-data'));
    signal?.postMessage('changed');
}
signal?.addEventListener('message', () => window.dispatchEvent(new Event('sgc-provider-data')));

async function download(endpoint) {
    const response = await fetch(endpoint, {cache: 'no-store', credentials: 'same-origin', globalLoader: false, headers: {Accept: 'application/json'}});
    if ([401, 403, 419, 404].includes(response.status) || response.redirected) {
        await setProfile(null);
        notify();
        return null;
    }
    if (!response.ok) throw new Error('O servidor não conseguiu atualizar a cópia offline.');
    return response.json();
}

async function send(bundle, row) {
    if (row.rejected) return false;
    const form = new FormData();
    function append(key, value) {
        if (value == null) return;
        if (typeof value === 'object' && !(value instanceof Blob)) {
            for (const [name, nested] of Object.entries(value)) append(key + '[' + name + ']', nested);
        } else form.append(key, value);
    }
    for (const [key, value] of Object.entries({...row.payload, scope: row.scope, operation_key: row.id, evidences: row.files})) append(key, value);
    if (!await guardedUpdate({...row, sent: true}, row, true)) return false;
    const response = await fetch(bundle.syncUrl, {
        method: 'POST', body: form, cache: 'no-store', credentials: 'same-origin', globalLoader: false,
        headers: {Accept: 'application/json', 'X-CSRF-TOKEN': bundle.csrf},
    });
    if (response.ok) {
        const {order} = await response.json();
        bundle.orders = [...bundle.orders.filter(item => item.id !== order.id), order];
        await discardOperation(row.id);
        return true;
    }
    if ([401, 403, 419].includes(response.status) || response.redirected) throw new Error('Entre novamente para sincronizar.');
    if (response.status === 429 || response.status >= 500) throw new Error('O servidor está ocupado. Os registros continuam na fila.');
    const result = await response.json().catch(() => ({}));
    await saveOperation({...row, sent: false, rejected: true, conflict: response.status === 409 || response.status === 404,
        error: Object.values(result.errors || {}).flat().join(' ') || result.message || 'Confira os dados antes de reenviar.'});
    return false;
}

export async function refreshBundle({force = false, sync = true} = {}) {
    if (busy) return busy;
    const current = await getBundle();
    const tenant = requestedTenant();
    const endpoint = page?.dataset.providerOfflineUrl || (tenant && '/'+tenant+'/provider/offline/data') || current?.endpoint;
    if (!endpoint || !navigator.onLine) return current;
    if (busy) return busy;
    if (!force && Date.now() - renewedAt < MAX_AGE) return current;
    const update = async () => {
        const bundle = await download(endpoint);
        if (!bundle) return null;
        if (page && bundle.user !== page.dataset.providerOfflineUser) return null;
        bundle.endpoint = endpoint;
        const publish = async () => {
            if (localStorage.getItem(signedOutKey)) return null;
            const {csrf, ...stored} = bundle;
            await setProfile(stored);
            notify();
            return stored;
        };
        await publish();
        if (sync) {
            try {
                for (const row of await queued(bundle.scope)) await send(bundle, row);
            } finally {
                await publish();
            }
        }
        renewedAt = Date.now();
        return getBundle();
    };
    busy = (navigator.locks?.request(DB_NAME, update) || update()).finally(() => { busy = null; });
    return busy;
}

async function bootstrap() {
    const previous = await profile();
    if (previous && previous.user !== page.dataset.providerOfflineUser) await setProfile(null);
    localStorage.removeItem(signedOutKey);
    const version = document.querySelector('meta[name="app-version"]')?.content || 'dev';
    navigator.serviceWorker?.register('/sw.js?v='+encodeURIComponent(version), {scope: '/', updateViaCache: 'none'}).catch(() => {});
    await refreshBundle();
}

if (page) {
    bootstrap().catch(() => {});
    window.addEventListener('sgc-provider-data', async () => {
        const link = document.querySelector('[data-provider-offline-link]');
        const bundle = await getBundle();
        if (!link || !bundle) return;
        const count = (await queued(bundle.scope)).length;
        link.textContent = count ? 'Modo offline · '+count+' pendente(s)' : 'Modo offline';
    });
    window.addEventListener('online', () => refreshBundle({force: true}).catch(() => {}));
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshBundle().catch(() => {});
    });
    setInterval(() => refreshBundle().catch(() => {}), MAX_AGE);
    document.addEventListener('submit', event => {
        if (/\/logout\/?$/.test(new URL(event.target.action).pathname)) {
            localStorage.setItem(signedOutKey, '1');
            setProfile(null).catch(() => {});
        }
    }, true);
}

