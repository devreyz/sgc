const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/accounting-portal.js'), 'utf8');
const context = { URL, location: { href: 'https://sgc.test/accounting' },
    esc: value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('"', '&quot;'), phIcon: () => '' };
vm.createContext(context);
vm.runInContext(source.slice(source.indexOf('    function renderDossierReports('), source.indexOf('    function renderReconciliation(')), context);

test('oferece os três relatórios e não sugere escopo amplo sem acesso ao projeto', () => {
    const html = context.renderDossierReports({ projects: [{ id: 10, title: 'Projeto <seguro>' }],
        can_project_period: false, settings_url: null,
        documents: ['members', 'acceptance', 'documents'].map(type => ({ type, title: type, url: '/reports/' + type })) });
    assert.equal((html.match(/Baixar PDF/g) || []).length, 3);
    assert.ok(!html.includes('value="project_period"'));
    assert.ok(html.includes('Projeto &lt;seguro>'));
    assert.ok(html.includes('sem emitir comprovantes'));
    assert.equal((html.match(/data-sgc-pdf/g) || []).length, 3);
    assert.equal((html.match(/_sgc_report=/g) || []).length, 6);
});

test('cada abertura de visualização usa uma URL nova sem perder os filtros ou o modo inline', () => {
    const link = { href: 'https://sgc.test/reports/members?download=0&scope=project_period&project=10' };
    const event = { target: { closest: () => link } };
    context.prepareDossierReportOpen(event);
    const first = new URL(link.href);
    context.prepareDossierReportOpen(event);
    const second = new URL(link.href);
    assert.notEqual(first.searchParams.get('_sgc_report'), second.searchParams.get('_sgc_report'));
    assert.equal(second.searchParams.get('download'), '0');
    assert.equal(second.searchParams.get('scope'), 'project_period');
    assert.equal(second.searchParams.get('project'), '10');
    assert.equal(second.searchParams.getAll('_sgc_report').length, 1);
});

test('escopo e projeto alteram apenas os relatórios físicos, preservando o inventário da folha inteira', () => {
    const links = ['members', 'acceptance', 'documents'].map(type => ({ dataset: { reportExport: type, reportBaseUrl: '/reports/' + type }, hasAttribute: () => false }));
    const container = { querySelector: selector => ({ value: selector.includes('scope') ? 'project_period' : '10' }), querySelectorAll: () => links };
    context.updateDossierReportLinks(container);
    for (const link of links) {
        const url = new URL(link.href);
        assert.equal(url.searchParams.get('download'), '1');
        assert.equal(url.searchParams.get('scope'), link.dataset.reportExport === 'documents' ? null : 'project_period');
        assert.equal(url.searchParams.get('project'), link.dataset.reportExport === 'documents' ? null : '10');
    }
});
