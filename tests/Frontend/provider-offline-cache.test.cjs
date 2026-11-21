const {readFileSync} = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');

const script = readFileSync(path.join(__dirname, '../../public/sw.js'), 'utf8');

function setup() {
    const events = {};
    const cacheContents = new Map();
    const source = new Map();
    let now = 1;
    let fail = false;
    function cache(name) {
        if (!cacheContents.has(name)) cacheContents.set(name, new Map());
        const contents = cacheContents.get(name);
        return {
            match: async request => contents.get(request.url || request),
            add: async request => contents.set(request.url, source.get(request.url)),
            addAll: async requests => {
                const values = requests.map(request => {
                    if (!source.has(request.url)) throw Error('Incomplete release');
                    return [request.url, source.get(request.url)];
                });
                for (const [url, response] of values) contents.set(url, response);
            },
            put: async (request, response) => contents.set(request.url || request, response),
        };
    }
    vm.runInNewContext(script, {
        URL, URLSearchParams, Request: class { constructor(url, options) { this.url = url; this.options = options; } },
        Date: {now: () => now}, Response: {error: () => ({status: 0}), redirect: location => ({status: 302, location})},
        self: {
            location: {origin: 'https://sgc.test'},
            addEventListener: (event, handler) => events[event] = handler,
            skipWaiting() {}, clients: {claim() {}},
        },
        caches: {
            open: async name => cache(name), keys: async () => [...cacheContents.keys()],
            delete: async name => cacheContents.delete(name),
        },
        fetch: async (request, options) => {
            assert.equal(options.cache, 'no-cache');
            if (fail) throw Error('Offline');
            const url = new URL(request.url);
            return source.get(url.pathname) || {status: 404};
        },
    });
    function run(event, data = {}) {
        let response;
        const pending = [];
        events[event]({...data, waitUntil: promise => pending.push(promise), respondWith: promise => response = promise});
        return {response, pending: Promise.all(pending)};
    }
    function fetchPath(url, mode = 'cors', method = 'GET') {
        return run('fetch', {request: {url: 'https://sgc.test' + url, mode, method}});
    }
    function onlineFile(url, release) {
        const response = {release, status: 200, ok: true, type: 'basic', headers: {get: () => ''}};
        response.clone = () => response;
        source.set(url, response);
    }
    function release(version) {
        for (const url of ['/provider-offline.html', '/assets/provider-offline.css', '/assets/provider-offline.js',
            '/assets/provider-offline-store.js', '/assets/provider-offline-document.js', '/assets/icons/campo-dark-192.png', '/offline']) onlineFile(url, version);
    }
    return {run, fetchPath, onlineFile, release, source, cacheContents,
        network: online => { fail = !online; }, advanceTime: () => { now += 600001; }};
}

test('public files are revalidated online and cached as a fallback only', async () => {
    const sw = setup();
    const url = '/assets/logo.png';
    sw.onlineFile(url, 'old');
    assert.equal((await sw.fetchPath(url).response).release, 'old');
    sw.onlineFile(url, 'new');
    assert.equal((await sw.fetchPath(url).response).release, 'new');
    sw.network(false);
    assert.equal((await sw.fetchPath(url).response).release, 'new');
    assert.equal(sw.fetchPath('/organization/private/logo.png').response, undefined);
    assert.equal(sw.fetchPath('/organization/provider/offline/data').response, undefined);
    assert.equal(sw.fetchPath('/organization/provider/offline/sync', 'cors', 'POST').response, undefined);
});

test('a partial update leaves the previous offline package usable', async () => {
    const sw = setup();
    sw.release('old');
    await sw.run('install').pending;
    sw.advanceTime();
    sw.release('new');
    sw.source.delete('/assets/provider-offline-document.js');
    await sw.run('message', {data: {type: 'SGC_REFRESH_OFFLINE_SHELL'}}).pending;
    sw.network(false);
    assert.equal((await sw.fetchPath('/sgc/provider/orders/1', 'navigate').response).location, 'https://sgc.test/provider-offline.html?tenant=sgc&os=1');
    assert.equal((await sw.fetchPath('/assets/provider-offline-store.js').response).release, 'old');
    sw.network(true);
    sw.release('new');
    await sw.run('message', {data: {type: 'SGC_REFRESH_OFFLINE_SHELL'}}).pending;
    sw.network(false);
    assert.equal((await sw.fetchPath('/provider-offline.html').response).release, 'new');
    assert.equal((await sw.fetchPath('/assets/provider-offline-store.js').response).release, 'new');
});

test('activation replaces legacy caches and preserves unrelated caches', async () => {
    const sw = setup();
    sw.cacheContents.set('sgc-provider-shell-1-old', new Map());
    sw.cacheContents.set('sgc-outbox', new Map());
    await sw.run('activate').pending;
    assert.ok(!sw.cacheContents.has('sgc-provider-shell-1-old'));
    assert.ok(sw.cacheContents.has('sgc-outbox'));
});

test('a permission denial from the server takes precedence over the offline page', async () => {
    const sw = setup();
    sw.release('previous');
    await sw.run('install').pending;
    const url = '/sgc/provider/dashboard';
    sw.source.set(url, {status: 403});
    assert.equal((await sw.fetchPath(url, 'navigate').response).status, 403);
});

test('the provider create route opens the offline order form for the same organization', async () => {
    const sw = setup();
    sw.release('current');
    await sw.run('install').pending;
    const url = '/minha-coop/provider/orders/create';
    sw.onlineFile(url, 'online-form');
    assert.equal((await sw.fetchPath(url, 'navigate').response).release, 'online-form');
    sw.network(false);
    const offline = await sw.fetchPath(url, 'navigate').response;
    assert.equal(offline.location, 'https://sgc.test/provider-offline.html?tenant=minha-coop&new=1');
});

test('the provider offline link stays in the requested organization', async () => {
    const sw = setup();
    sw.release('current');
    await sw.run('install').pending;
    sw.network(false);
    assert.equal((await sw.fetchPath('/coop/provider/dashboard', 'navigate').response).location, 'https://sgc.test/provider-offline.html?tenant=coop');
    assert.equal(sw.fetchPath('/coop/provider/financial', 'navigate').response, undefined);
});

test('the cached fieldwork window excludes older, distant and completed orders', () => {
    const source = readFileSync(path.join(__dirname, '../../public/assets/provider-offline-store.js'), 'utf8');
    const start = source.indexOf('function fieldworkBundle(');
    const end = source.indexOf('export function hasOfflineAccess', start);
    const filter = vm.runInNewContext('(' + source.slice(start, end).trim() + ')');
    const order = (id, scheduled, state = 'scheduled', execution = 'draft') => ({id, scheduled, state, execution});
    const bundle = {orders: [
        order(1, '2026-10-23'), order(2, '2026-10-24'),
        {...order(3, '2026-10-01', 'in_progress', 'in_progress'), executionDate: '2026-10-25'},
        order(4, '2026-11-30'), order(5, '2026-12-01'), order(6, '2026-11-02', 'validated', 'validated'),
        order(7, null), order(8, '2026-11-01', 'scheduled', 'submitted'),
    ]};
    const filtered = filter(bundle, new Date('2026-11-01T00:30:00Z'));
    assert.equal(filtered.window.from, '2026-10-24');
    assert.equal(filtered.window.to, '2026-11-30');
    assert.equal(filtered.orders.map(item => item.id).join(','), '2,3,4');
});

