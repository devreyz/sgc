const {readFileSync} = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');
const {randomUUID} = require('node:crypto');
const vm = require('node:vm');

const script = readFileSync(path.join(__dirname, '../../public/assets/provider-offline.js'), 'utf8');
const saveFunction = script.slice(script.indexOf('async function save(action)'), script.indexOf('let redrawVersion'));

function setup(extra = []) {
    const rows = [];
    const message = {};
    const fields = new Map([
        ['service_version_id', '7'], ['beneficiary_name', 'Maria'],
        ['scheduled_at', '2026-11-24T08:30'], ['order:local', 'Comunidade'], ...extra,
    ]);
    const scope = '9:1:5';
    const active = {order: null, row: null, scope};
    const context = {
        active, bundle: {scope}, saving: false, message, crypto: {randomUUID},
        hasOfflineAccess: () => true, form: {reportValidity: () => true},
        currentOrder: () => ({service: 'Trator', beneficiary: 'Maria', catalog: {}}),
        values: () => Object.fromEntries([...fields].filter(([key]) => key.startsWith('value:')).map(([key, value]) => [key.slice(6),value])),
        FormData: class { get(key) { return fields.get(key); } has(key) { return fields.has(key); } [Symbol.iterator]() { return fields[Symbol.iterator](); } },
        File: class {}, navigator: {onLine: false},
        synchronize() { assert.fail('Must not sync while offline'); },
        async guardedUpdate(row) { rows.push(row); return true; },
        async redraw() {},
        history: {replaceState() {}},
    };
    const save = vm.runInNewContext('(' + saveFunction + ')',context);
    return {save, rows, message};
}

test('a new order is stored offline once even when the button is tapped twice', async () => {
    const {save, rows, message} = setup();
    await Promise.all([save('schedule'),save('schedule')]);
    assert.equal(rows.length,1);
    assert.equal(rows[0].scope,'9:1:5');
    assert.equal(rows[0].payload.action,'schedule');
    assert.equal(rows[0].payload.order_id,undefined);
    assert.equal(rows[0].payload.creation.service_version_id,'7');
    assert.equal(rows[0].payload.creation.beneficiary_name,'Maria');
    assert.equal(rows[0].payload.creation.order_data.local,'Comunidade');
    assert.match(message.textContent,/Ordem salva neste aparelho/);
});

test('creating an offline order keeps execution readings for synchronization', async () => {
    const {save, rows} = setup([['value:hours','3.5']]);
    await save('schedule');
    assert.equal(rows[0].payload.action,'draft');
    assert.equal(rows[0].payload.values.hours,'3.5');
});

test('an offline order created by an operator retains its assigned provider', async () => {
    const {save, rows} = setup([['service_provider_id','12']]);
    await save('schedule');
    assert.equal(rows[0].payload.creation.service_provider_id, '12');
    assert.equal(rows[0].scope, '9:1:5');
});

