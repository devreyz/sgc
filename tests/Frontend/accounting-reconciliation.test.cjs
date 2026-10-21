const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/js/accounting-portal.js'), 'utf8');

function harness() {
    const output = {};
    const context = {
        quantity: new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 4 }),
        money: new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }),
        esc: (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;'),
        phIcon: () => '',
        Blob: class { constructor(parts) { output.csv = parts.join(''); } },
        URL: { createObjectURL: () => 'blob:test', revokeObjectURL: (url) => { output.revoked = url; } },
        document: { createElement: () => ({ set download(value) { output.filename = value; }, click() { output.clicked = true; } }) },
        setTimeout: (callback) => callback(),
    };
    vm.createContext(context);
    vm.runInContext(source.slice(source.indexOf('    function renderReconciliation('), source.indexOf('    async function initDossier(')), context);
    return { context, output };
}

function report() {
    return {
        summary: { distributions: 1, covered: 1, uncovered: 0, member_documents: 1, sheet_gross: '0.3', current_gross: '0.3', covered_gross: '0.3', sheet_net: '0.3', all_members_gross: null, all_members_net: '0.2', all_members_quantities: [{ unit: 'kg', quantity: '0.3' }] },
        checked_at: '21/10/2026 10:00:00', warning_count: 1, info_count: 0,
        sheet_quantities_available: true,
        alerts: [{ message: '<script>bad()</script>', solution: 'Revise a seleção', level: 'warning', ids: [301] }],
        units: [{ unit: 'kg', sheet_quantity: '0.3', current_quantity: '0.3', covered_quantity: '0.3', uncovered_quantity: '0' }],
        products: [{ product: '=HYPERLINK("bad")', project_id: 10, unit: 'kg', sheet_quantity: '0.3', current_quantity: '0.3', covered_quantity: '0.3', sheet_gross: '0.3', current_gross: '0.3' }],
        members: [{ number: '0001/2026', member: 'Maria', status: 'Pago', in_billing: 1, outside_billing: 0, quantities: [{ unit: 'kg', quantity: '0.3' }], gross: null, net: '0.2' }],
        limitations: ['Não certifica a via histórica.'],
    };
}

test('renderiza valores e limites, escapa conteúdo e oferece ações somente de leitura', () => {
    const { context } = harness();
    const html = context.renderReconciliation(report());
    assert.ok(html.includes('0,3'));
    assert.ok(html.includes('Não disponível'));
    assert.ok(html.includes('&lt;script&gt;'));
    assert.ok(!html.includes('<script>'));
    assert.ok(html.includes('#301'));
    assert.ok(html.includes('Como revisar:'));
    assert.ok(html.includes('data-recheck-documents'));
    assert.ok(html.includes('data-export-reconciliation'));
    assert.ok(html.includes('Não certifica a via histórica.'));
});

test('não apresenta quantidades históricas ausentes como zero confirmado', () => {
    const { context } = harness();
    const data = report();
    data.sheet_quantities_available = false;
    const html = context.renderReconciliation(data);
    assert.ok(html.includes('<td>Não disponível</td>'));
    assert.ok(context.renderReconciliation(null).includes('indisponível'));
});

test('exporta CSV em português, protege fórmulas e libera a URL temporária', () => {
    const { context, output } = harness();
    context.exportReconciliation(report(), '0001/2026');
    assert.ok(output.csv.startsWith('\uFEFF'));
    assert.ok(output.csv.includes('"Bruto salvo na folha";"0,3"'));
    assert.ok(output.csv.includes("\"'=HYPERLINK("));
    assert.ok(output.csv.includes('Limites da análise'));
    assert.equal(output.filename, 'conferencia-0001-2026.csv');
    assert.equal(output.clicked, true);
    assert.equal(output.revoked, 'blob:test');
});
