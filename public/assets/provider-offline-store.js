const DB_NAME = 'sgc-provider-offline';
const MAX_AGE = 10 * 60 * 1000;
const page = document.querySelector('[data-provider-offline-url]');
const signal = typeof BroadcastChannel === 'function' ? new BroadcastChannel(DB_NAME) : null;
const signedOutKey = 'sgc-provider-offline-signed-out';
const accountKey = 'sgc-offline-account';
let opening, busy;

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
        return !tenant || tenant === bundle.tenant ? fieldworkBundle(bundle) : null;
    }
    if (bundle) await setProfile(null);
    return null;
}

export function fieldworkBundle(bundle, now = new Date()) {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('pt-BR', {
        timeZone: bundle.timezone || 'America/Sao_Paulo', year: 'numeric', month: 'numeric', day: 'numeric',
    }).formatToParts(now).map(({type, value}) => [type, Number(value)]));
    const {year, month, day} = parts;
    const lastDay = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
    const dateString = date => date.toISOString().slice(0, 10);
    const from = dateString(new Date(Date.UTC(year, month - 1, day - 7)));
    const to = dateString(new Date(Date.UTC(year, month, Math.min(day, lastDay))));
    const orders = (bundle.orders || []).filter(order => {
        const date = order.executionDate || order.scheduled?.slice(0, 10);
        return ['scheduled', 'in_progress'].includes(order.state)
            && ['draft', 'in_progress'].includes(order.execution) && date >= from && date <= to;
    });

    return {...bundle, orders, window: {from, to}};
}

function account() {
    try { return JSON.parse(localStorage.getItem(accountKey) || 'null'); }
    catch { return null; }
}

export function hasOfflineAccess(bundle) {
    const session = account();
    return Boolean(bundle && session?.epoch && bundle.accountEpoch === session.epoch
        && bundle.user === session.user && !localStorage.getItem(signedOutKey)
        && Date.parse(bundle.expiresAt) > Date.now());
}

function notify() {
    window.dispatchEvent(new Event('sgc-provider-data'));
    signal?.postMessage('changed');
}
signal?.addEventListener('message', () => window.dispatchEvent(new Event('sgc-provider-data')));
window.addEventListener('storage', event => {
    if (event.key === signedOutKey || event.key === accountKey || event.key === null) window.dispatchEvent(new Event('sgc-provider-data'));
});

function offlineRetryKey(endpoint) {
    return DB_NAME + ':retry:' + new URL(endpoint, location.href).pathname;
}

function checkRateLimit(response, endpoint) {
    if (response.status !== 429) return;
    const retryAfter = response.headers.get('Retry-After');
    const retryAt = /^\d+$/.test(retryAfter) ? Date.now() + Number(retryAfter) * 1000 : Date.parse(retryAfter);
    localStorage.setItem(offlineRetryKey(endpoint), String(retryAt > Date.now() ? retryAt : Date.now() + 60_000));
    throw new Error('Aguarde um pouco antes de sincronizar novamente. Os registros pendentes continuam na fila.');
}

async function download(endpoint, epoch) {
    const response = await fetch(endpoint, {cache: 'no-store', credentials: 'same-origin', globalLoader: false, headers: {Accept: 'application/json'}});
    checkRateLimit(response, endpoint);
    if ([401, 403, 419, 404].includes(response.status) || response.redirected) {
        if (account()?.epoch === epoch) { await setProfile(null); notify(); }
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
    if ([401, 403, 419].includes(response.status) || response.redirected) {
        bundle.expiresAt = null;
        if (account()?.epoch === bundle.accountEpoch) { await setProfile(null); notify(); }
        throw new Error('Entre novamente para sincronizar.');
    }
    if (response.ok) {
        const {order, available} = await response.json();
        bundle.orders = bundle.orders.filter(item => item.id !== order.id);
        if (available !== false) bundle.orders.push(order);
        await discardOperation(row.id);
        return true;
    }
    checkRateLimit(response, bundle.endpoint);
    if (response.status >= 500) throw new Error('O servidor está ocupado. Os registros continuam na fila.');
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
    const update = async () => {
        const session = account();
        if (!session?.epoch || localStorage.getItem(signedOutKey) || (page && session.user !== page.dataset.providerOfflineUser)) return null;
        const cached = await getBundle();
        const age = Date.now() - Date.parse(cached?.issuedAt);
        if (!force && cached?.endpoint === endpoint && (!page || cached.user === page.dataset.providerOfflineUser)
            && age >= 0 && age < MAX_AGE && (!sync || (await queued(cached.scope)).every(row => row.rejected))) {
            return cached;
        }
        if (Number(localStorage.getItem(offlineRetryKey(endpoint))) > Date.now()) {
            throw new Error('Aguarde um pouco antes de sincronizar novamente. Os registros pendentes continuam na fila.');
        }
        refreshOfflineShell().catch(() => {});
        const bundle = await download(endpoint, session.epoch);
        if (!bundle) return null;
        if (bundle.user !== session.user || account()?.epoch !== session.epoch) {
            if (account()?.epoch === session.epoch) { await setProfile(null); notify(); }
            return null;
        }
        bundle.endpoint = endpoint;
        bundle.accountEpoch = session.epoch;
        const publish = async () => {
            if (!hasOfflineAccess(bundle)) return null;
            const {csrf, ...stored} = fieldworkBundle(bundle);
            await setProfile(stored);
            notify();
            return stored;
        };
        if (!await publish()) return null;
        if (sync) {
            try {
                for (const row of await queued(bundle.scope)) {
                    if (!hasOfflineAccess(bundle)) break;
                    await send(bundle, row);
                }
            } finally {
                await publish();
            }
        }
        return getBundle();
    };
    busy = (navigator.locks?.request(DB_NAME, update) || update()).finally(() => { busy = null; });
    return busy;
}

let shellUpdate;
function refreshOfflineShell() {
    if (!navigator.serviceWorker || !navigator.onLine) return Promise.resolve();
    shellUpdate ??= navigator.serviceWorker.register('/sw.js', {scope: '/', updateViaCache: 'none'})
        .then(async registration => {
            registration.active?.postMessage({type: 'SGC_REFRESH_OFFLINE_SHELL'});
            await registration.update();
        }).finally(() => { shellUpdate = null; });
    return shellUpdate;
}

async function bootstrap() {
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

