(() => {
    const root = document.querySelector('[data-billing-editor]');
    if (!root) return;
    const form = root.querySelector('[data-billing-form]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const selected = new Set();
    const batches = new Map();
    let step = 1;
    let mode = 'period';
    let preview = null;
    let recipientRequest = 0;
    const q = (selector) => root.querySelector(selector);
    const qa = (selector) => [...root.querySelectorAll(selector)];
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
    const money = (value) => Number(value || 0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
    const message = (text, error = false) => {
        const box = q('[data-editor-message]'); box.hidden = !text; box.textContent = text || '';
        box.classList.toggle('is-error', error); if (text) box.scrollIntoView({behavior:'smooth', block:'nearest'});
    };
    const request = async (url, options = {}) => {
        const response = await fetch(url, {credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token,...(options.headers || {})}, ...options});
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = Object.values(data.errors || {}).flat().join(' ');
            throw new Error(errors || data.message || 'Não foi possível concluir esta operação.');
        }
        return data;
    };
    const values = () => {
        const projectIds = [...form.querySelector('[name="project_ids[]"]').selectedOptions].map(o => Number(o.value)).filter(Boolean);
        const recipientType = form.querySelector('[name="recipient_type"]:checked')?.value;
        return {
            project_ids: projectIds,
            customer_id: recipientType === 'customer' ? Number(form.customer_id.value) || null : null,
            organization_id: recipientType === 'organization' ? Number(form.organization_id.value) || null : null,
            issued_at: form.issued_at.value,
            from_date: form.from_date.value || null,
            to_date: form.to_date.value || null,
            notes: form.notes.value || null,
            distribution_ids: [...selected],
        };
    };
    const validateContext = () => {
        const data = values();
        if (!data.project_ids.length) throw new Error('Selecione ao menos um projeto.');
        if (!data.customer_id && !data.organization_id) throw new Error('Selecione quem será cobrado.');
        if (!data.issued_at) throw new Error('Informe a data de emissão.');
        if (data.from_date && data.to_date && data.to_date < data.from_date) throw new Error('A data final não pode ser anterior à inicial.');
        return data;
    };
    const setStep = (next) => {
        step = next; qa('[data-step]').forEach(el => el.hidden = Number(el.dataset.step) !== step);
        qa('[data-step-button]').forEach(el => el.classList.toggle('is-active', Number(el.dataset.stepButton) === step));
        q('[data-previous]').hidden = step === 1;
        q('[data-next]').hidden = step === 4;
        q('[data-save]').hidden = step < 3;
        if (q('[data-freeze]')) q('[data-freeze]').hidden = step !== 4;
        root.scrollIntoView({behavior:'smooth', block:'start'});
    };
    const updateCount = () => qa('[data-selected-count]').forEach(el => el.textContent = `${selected.size} selecionada(s)`);
    const renderBatches = () => {
        const target = q('[data-scanned-batches]');
        target.innerHTML = batches.size ? `<h3>Lotes lidos</h3>${[...batches.values()].map(batch => `<article class="acc-batch-card"><div><strong>${escapeHtml(batch.display)}</strong><span>${batch.ids.length} distribuição(ões) selecionada(s)${batch.excluded ? ` · ${batch.excluded} ignorada(s)` : ''}</span></div><button class="acc-button" type="button" data-remove-batch="${escapeHtml(batch.key)}"><i data-lucide="trash-2"></i> Remover lote</button></article>`).join('')}` : '<p class="acc-help">Nenhum lote escaneado.</p>';
        window.lucide?.createIcons();
    };
    const rebuildBatchSelection = () => {
        selected.clear(); batches.forEach(batch => batch.ids.forEach(id => selected.add(Number(id)))); updateCount(); renderBatches();
    };
    const recipientToggle = () => {
        const type = form.querySelector('[name="recipient_type"]:checked')?.value;
        qa('[data-recipient-field]').forEach(el => el.hidden = el.dataset.recipientField !== type);
    };
    const populateRecipients = (data, preserve = true) => {
        const customer = preserve ? form.customer_id.value : '';
        const organization = preserve ? form.organization_id.value : '';
        form.customer_id.innerHTML = '<option value="">Selecione um cliente</option>' + (data.customers || []).map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        form.organization_id.innerHTML = '<option value="">Selecione uma organização</option>' + (data.organizations || []).map(o => `<option value="${o.id}">${escapeHtml(o.name)}</option>`).join('');
        if ([...form.customer_id.options].some(option => option.value === customer)) form.customer_id.value = customer;
        if ([...form.organization_id.options].some(option => option.value === organization)) form.organization_id.value = organization;
    };
    const loadRecipientsForProjects = async () => {
        const requestId = ++recipientRequest;
        const params = new URLSearchParams();
        [...form.querySelector('[name="project_ids[]"]').selectedOptions].forEach(option => params.append('project_ids[]', option.value));
        try {
            const data = await request(`${root.dataset.contextUrl}?${params}`, {method:'GET', headers:{'Content-Type':'application/json'}});
            if (requestId !== recipientRequest) return;
            populateRecipients(data);
            if (!(data.customers || []).length && !(data.organizations || []).length && params.has('project_ids[]')) {
                message('Os projetos selecionados não possuem distribuições elegíveis para faturamento.', true);
            }
        } catch (error) { if (requestId === recipientRequest) message(error.message, true); }
    };
    const selectionPayload = () => ({...validateContext(), mode,
        receipt_codes: q('[data-receipt-codes]').value.split(/[\n,;]+/).map(v => v.trim()).filter(Boolean),
        distribution_ids: [...selected],
    });
    const loadSelection = async () => {
        message('');
        try {
            const result = await request(root.dataset.selectUrl, {method:'POST', body:JSON.stringify(selectionPayload())});
            if (mode === 'receipts') {
                batches.clear();
                (result.batches || []).forEach(batch => batches.set(batch.key, {key:batch.key, display:batch.label, ids:(batch.selected_ids || []).map(Number), excluded:Number(batch.excluded_count || 0)}));
                rebuildBatchSelection();
            } else {
                selected.clear(); (result.selected_ids || []).forEach(id => selected.add(Number(id))); updateCount();
            }
            const excluded = result.excluded_count ? ` ${result.excluded_count} item(ns) incompatível(is) foram ignorados.` : '';
            message(`${selected.size} distribuição(ões) selecionada(s).${excluded}`, result.excluded_count > 0);
        } catch (error) { message(error.message, true); }
    };
    const loadManual = async (page = 1) => {
        try {
            const params = new URLSearchParams(); const data = validateContext();
            data.project_ids.forEach(id => params.append('project_ids[]', id));
            ['customer_id','organization_id','from_date','to_date'].forEach(key => data[key] && params.set(key, data[key]));
            params.set('page', page); params.set('search', q('[data-manual-search]').value || '');
            const result = await request(`${root.dataset.manualUrl}?${params}`, {method:'GET', headers:{'Content-Type':'application/json'}});
            const pager = result.distributions; q('[data-manual-rows]').innerHTML = (pager.data || []).map(row => `<tr>
                <td><input type="checkbox" data-pick="${row.id}" ${selected.has(Number(row.id)) ? 'checked' : ''} aria-label="Selecionar distribuição ${row.id}"></td>
                <td>${escapeHtml(row.date)}</td><td>${escapeHtml(row.producer)}</td><td>${escapeHtml(row.product)}</td>
                <td>${escapeHtml(row.quantity)} ${escapeHtml(row.unit)}</td><td>${escapeHtml(row.recipient)}</td></tr>`).join('') || '<tr><td colspan="6">Nenhuma distribuição elegível.</td></tr>';
            q('[data-manual-pagination]').innerHTML = `<span>Página ${pager.current_page} de ${pager.last_page} · ${pager.total} item(ns)</span><div class="acc-pagination-actions"><button class="acc-button" type="button" data-manual-page="${pager.current_page - 1}" ${pager.current_page <= 1 ? 'disabled' : ''}>Anterior</button><button class="acc-button" type="button" data-manual-page="${pager.current_page + 1}" ${pager.current_page >= pager.last_page ? 'disabled' : ''}>Próxima</button></div>`;
        } catch (error) { message(error.message, true); }
    };
    const loadPreview = async () => {
        preview = await request(root.dataset.previewUrl, {method:'POST', body:JSON.stringify(values())});
        selected.clear(); (preview.selected_ids || []).forEach(id => selected.add(Number(id))); updateCount(); renderPreview();
        return preview;
    };
    const renderPreview = () => {
        q('[data-review-count]').textContent = `${preview.summary.distributions} distribuição(ões) · ${preview.summary.producers} produtor(es)`;
        q('[data-review-rows]').innerHTML = preview.distributions.map(row => `<tr><td>${escapeHtml(row.date)}</td><td>${escapeHtml(row.producer)}</td><td>${escapeHtml(row.product)}</td><td>${escapeHtml(row.quantity)} ${escapeHtml(row.unit)}</td><td>${escapeHtml(row.recipient)}</td><td><button class="acc-link-button" type="button" data-remove-id="${row.id}" aria-label="Remover"><i data-lucide="x"></i></button></td></tr>`).join('');
        const reasons = Object.entries(preview.exclusion_reasons || {});
        q('[data-exclusions]').innerHTML = reasons.length ? `<div class="acc-alert is-error"><strong>Itens ignorados</strong><ul>${reasons.map(([reason,count]) => `<li>${escapeHtml(reason.replaceAll('_',' '))}: ${count}</li>`).join('')}</ul></div>` : '';
        q('[data-preview-summary]').innerHTML = `<article><span>Distribuições</span><strong>${preview.summary.distributions}</strong></article><article><span>Produtores</span><strong>${preview.summary.producers}</strong></article><article><span>Documentos de origem</span><strong>${preview.summary.source_receipts}</strong></article>`;
        q('[data-financial-lines]').innerHTML = preview.lines.map(line => `<tr><td>${escapeHtml(line.product)}</td><td>${escapeHtml(line.quantity)}</td><td>${escapeHtml(line.unit)}</td><td>${money(line.unit_price)}</td><td>${money(line.document_amount)}</td></tr>`).join('') || '<tr><td colspan="5">Sem linhas.</td></tr>';
        q('[data-financial-fees]').innerHTML = preview.fees.length ? `<div class="acc-fee-list">${preview.fees.map(fee => `<div><span>${escapeHtml(fee.name || 'Ajuste')} · ${escapeHtml(fee.nature === 'accrual' ? 'Acréscimo' : 'Desconto')}</span><strong>${money(fee.amount)}</strong></div>`).join('')}</div>` : '<p class="acc-help">Nenhuma taxa, desconto ou acréscimo configurado.</p>';
        q('[data-financial-totals]').innerHTML = `<div><span>Bruto</span><strong>${money(preview.totals.gross)}</strong></div><div><span>Ajustes líquidos</span><strong>${money(preview.totals.fees)}</strong></div><div class="is-total"><span>Total a cobrar</span><strong>${money(preview.totals.net)}</strong></div>`;
        window.lucide?.createIcons();
    };
    const save = async () => {
        try {
            await loadPreview();
            const payload = {...values(), operation_key: crypto.randomUUID()};
            const result = await request(root.dataset.saveUrl, {method:root.dataset.saveMethod, body:JSON.stringify(payload)});
            message(result.message || 'Rascunho salvo.'); if (result.redirect_url) location.assign(result.redirect_url);
        } catch (error) { message(error.message, true); }
    };
    const freeze = async () => {
        if (!confirm('Emitir este faturamento? Os valores e as distribuições serão congelados para os documentos e a autorização.')) return;
        try { const result = await request(root.dataset.freezeUrl, {method:'POST', body:'{}'}); location.assign(result.redirect_url); }
        catch (error) { message(error.message, true); }
    };
    const addReceiptBatch = async (code) => {
        const feedback = q('[data-scan-feedback]'); feedback.classList.remove('is-error'); feedback.textContent = 'Conferindo QR Code e selecionando as distribuições...';
        try {
            const result = await request(root.dataset.selectUrl, {method:'POST', body:JSON.stringify({...validateContext(), mode:'receipts', receipt_codes:[code], distribution_ids:[]})});
            const batch = result.batches?.[0];
            if (!batch?.receipt_found) throw new Error('Este QR Code não corresponde a um documento de origem desta organização.');
            batches.set(batch.key, {key:batch.key, display:batch.label, ids:(batch.selected_ids || []).map(Number), excluded:Number(batch.excluded_count || 0)});
            const lines = q('[data-receipt-codes]').value.split(/\n+/).map(value => value.trim()).filter(Boolean); if (!lines.includes(code)) lines.push(code); q('[data-receipt-codes]').value = lines.join('\n');
            rebuildBatchSelection(); feedback.textContent = `Lote adicionado: ${batch.selected_count} distribuição(ões). Continue apontando para outros comprovantes.`;
        } catch (error) { feedback.textContent = error.message; feedback.classList.add('is-error'); }
    };
    const init = async () => {
        try {
            const data = await request(root.dataset.contextUrl, {method:'GET', headers:{'Content-Type':'application/json'}});
            form.querySelector('[name="project_ids[]"]').innerHTML = data.projects.map(p => `<option value="${p.id}">${escapeHtml(p.name)}${p.code ? ` · ${escapeHtml(p.code)}` : ''}</option>`).join('');
            populateRecipients(data, false);
            form.issued_at.value = new Date().toISOString().slice(0,10);
            if (data.draft) {
                [...form.querySelector('[name="project_ids[]"]').options].forEach(o => o.selected = data.draft.project_ids.includes(Number(o.value)));
                const type = data.draft.organization_id ? 'organization' : 'customer'; form.querySelector(`[name="recipient_type"][value="${type}"]`).checked = true;
                form.organization_id.value = data.draft.organization_id || ''; form.customer_id.value = data.draft.customer_id || '';
                ['issued_at','from_date','to_date','notes'].forEach(key => form[key].value = data.draft[key] || '');
                data.draft.distribution_ids.forEach(id => selected.add(Number(id))); recipientToggle(); updateCount();
            }
        } catch (error) { message(error.message, true); }
    };
    root.addEventListener('click', async event => {
        const target = event.target.closest('button'); if (!target) return;
        if (target.matches('[data-step-button]')) { const wanted = Number(target.dataset.stepButton); if (wanted <= step) setStep(wanted); }
        if (target.matches('[data-next]')) { try { validateContext(); if (step === 2) await loadPreview(); if (step === 3) await loadPreview(); setStep(step + 1); message(''); } catch (error) { message(error.message, true); } }
        if (target.matches('[data-previous]')) setStep(step - 1);
        if (target.matches('[data-mode]')) { mode = target.dataset.mode; qa('[data-mode]').forEach(b => b.classList.toggle('is-active', b === target)); qa('[data-mode-panel]').forEach(p => p.hidden = p.dataset.modePanel !== mode); if (mode === 'manual') loadManual(); }
        if (target.matches('[data-load-selection]')) loadSelection();
        if (target.matches('[data-manual-load]')) loadManual();
        if (target.matches('[data-manual-page]')) loadManual(Number(target.dataset.manualPage));
        if (target.matches('[data-remove-id]')) { selected.delete(Number(target.dataset.removeId)); updateCount(); try { await loadPreview(); } catch (error) { setStep(2); message(error.message, true); } }
        if (target.matches('[data-remove-batch]')) { batches.delete(target.dataset.removeBatch); rebuildBatchSelection(); q('[data-receipt-codes]').value = [...batches.values()].map(batch => batch.display).join('\n'); }
        if (target.matches('[data-save]')) save(); if (target.matches('[data-freeze]')) freeze();
    });
    root.addEventListener('accounting:qr-code', event => addReceiptBatch(event.detail.code));
    root.addEventListener('change', event => {
        if (event.target.matches('[name="recipient_type"]')) recipientToggle();
        if (event.target.matches('[name="project_ids[]"]')) { selected.clear(); batches.clear(); updateCount(); renderBatches(); loadRecipientsForProjects(); }
        if (event.target.matches('[data-pick]')) { const id = Number(event.target.dataset.pick); event.target.checked ? selected.add(id) : selected.delete(id); updateCount(); }
    });
    init();
})();
