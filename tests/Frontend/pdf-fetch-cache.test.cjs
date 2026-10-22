const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/app.js'), 'utf8');
const start = source.indexOf('    async fetchPdf(');
const end = source.indexOf('\n    },', start) + 7;

test('visualizador busca o PDF sem cache a cada abertura e mantém nome e metadados', async () => {
    const requests = [];
    const context = {
        fetch: async (url, options) => {
            requests.push({ url, options });
            const version = requests.length;
            return { ok: true, headers: { get: name => ({
                'content-type': 'application/pdf', 'content-disposition': 'inline; filename="relatorio.pdf"',
                'x-sgc-document-title': 'Relatório atualizado', 'x-sgc-document-origin': 'generated',
                'x-sgc-document-path': 'Relatórios',
            }[name] || '') }, blob: async () => ({ version }) };
        },
    };
    vm.createContext(context);
    vm.runInContext('globalThis.documents = ({' + source.slice(start, end) + '});', context);
    const first = await context.documents.fetchPdf('/reports/members?download=0');
    const second = await context.documents.fetchPdf('/reports/members?download=0');
    assert.equal(first.blob.version, 1);
    assert.equal(second.blob.version, 2);
    for (const request of requests) {
        assert.equal(request.options.cache, 'no-store');
        assert.equal(request.options.credentials, 'same-origin');
        assert.equal(request.options.headers.Accept, 'application/pdf');
        assert.equal(request.options.headers['Cache-Control'], 'no-cache');
        assert.equal(request.options.headers.Pragma, 'no-cache');
    }
    assert.equal(second.fileName, 'relatorio.pdf');
    assert.equal(second.title, 'Relatório atualizado');
    assert.equal(second.origin, 'generated');
});
