import { test } from 'node:test';
import assert from 'node:assert/strict';
import { documentText, documentFilename } from '../../resources/js/document-text.js';

test('preserves complete Portuguese document titles through HTTP headers', () => {
    const title = 'Comprovante Nº 0030/2026-PAA · PAA - Miravânia';
    assert.equal(documentText(encodeURIComponent(title)), title);
    assert.equal(documentText(Buffer.from(title).toString('latin1')), title);
    assert.equal(documentText(title), title);
    assert.equal(documentText('Desconto 10%'), 'Desconto 10%');
});

test('prefers RFC 5987 filename and accepts literal percent signs', () => {
    assert.equal(documentFilename("inline; filename=receipt.pdf; filename*=UTF-8''Presta%C3%A7%C3%A3o.pdf"), 'Prestação.pdf');
    assert.equal(documentFilename('attachment; filename="100%.pdf"'), '100%.pdf');
});
