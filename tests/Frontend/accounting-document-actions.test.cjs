const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/accounting-portal.js'), 'utf8');
const context = {
    esc: (value) => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;'),
    phIcon: () => '',
};
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('    function renderDocumentActions('), source.indexOf('    const pendingDocumentRefreshes')), context);

test('mostra download novo, atualização e último horário do Drive com escape de conteúdo', () => {
    const html = context.renderDocumentActions({ download_url: '/document?download=1', refresh_url: '/refresh', status_url: '/status', drive_status_label: '<script>bad()</script>', drive_updated_at: '21/10/2026 10:20:30' });
    assert.ok(html.includes('Baixar PDF atualizado'));
    assert.ok(html.includes('Atualizar PDF no Drive'));
    assert.ok(html.includes('21/10/2026 10:20:30'));
    assert.ok(html.includes('data-status-url="/status"'));
    assert.ok(html.includes('aria-live="polite"'));
    assert.ok(!html.includes('<script>'));
});

test('não oferece atualização sem permissão nem download de documentos inválidos', () => {
    const html = context.renderDocumentActions({ download_url: null, refresh_url: null, status_url: '/status', drive_status_label: 'Sem cópia' });
    assert.ok(!html.includes('data-refresh-document'));
    assert.ok(!html.includes('href='));
    assert.equal(context.renderDocumentActions(null), '');
});
