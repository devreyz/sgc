import './qr-scanner-core';

(() => {
    const root = document.querySelector('[data-quick-verification]');
    if (!root) return;
    const q = selector => root.querySelector(selector);
    const qa = selector => [...root.querySelectorAll(selector)];
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const dialog = q('[data-camera-dialog]');
    const video = q('[data-camera-video]');
    let scannerSession = null;
    let historyArmed = false;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const money = value => Number(value || 0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});

    const setStage = stage => {
        root.dataset.stage = stage;
        q('[data-ready]').hidden = stage !== 'ready';
        q('[data-loading]').hidden = stage !== 'loading';
        q('[data-report]').hidden = stage !== 'report';
    };
    const closeCamera = (useHistory = true) => {
        scannerSession?.stop?.(); scannerSession = null; video.srcObject = null;
        if (dialog.open) dialog.close();
        if (useHistory && historyArmed) { historyArmed = false; history.back(); }
    };
    const render = data => {
        const verdict = q('[data-verdict]'); verdict.dataset.tone = data.verdict || 'invalid';
        q('[data-headline]').textContent = data.headline || 'Não foi possível validar';
        q('[data-message]').textContent = data.message || '';
        const doc = data.document;
        q('[data-summary]').innerHTML = doc ? [
            ['Documento', doc.number], ['Tipo', doc.type], ['Titular', doc.party], ['Projeto', doc.project],
            ['Situação', doc.status], ['Emissão', doc.issued_at], ['Valor', money(doc.total)], ['Distribuições', doc.distribution_count]
        ].filter(([,value]) => value !== null && value !== undefined && value !== '').map(([label,value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('') : '';
        const issues = data.issues || [];
        q('[data-issue-count]').textContent = issues.length ? `${issues.length} ponto(s)` : 'Nenhuma falha';
        q('[data-issues]').innerHTML = issues.length ? issues.map(issue => `<div class="qv-issue" data-tone="${escapeHtml(issue.severity)}"><i data-lucide="${issue.severity === 'critical' ? 'octagon-alert' : 'triangle-alert'}"></i><span>${escapeHtml(issue.message)}</span></div>`).join('') : '<div class="qv-clean"><i data-lucide="badge-check"></i><span>Nenhuma inconsistência conhecida foi encontrada.</span></div>';
        const distributions = data.distributions || [];
        q('[data-distributions-section]').hidden = !distributions.length;
        q('[data-distributions]').hidden = true;
        q('[data-distributions]').innerHTML = distributions.map(item => `<article class="qv-distribution${item.removed ? ' is-removed' : ''}"><strong>#${item.id} · ${escapeHtml(item.product)}</strong><span>${escapeHtml(item.quantity)} ${escapeHtml(item.unit)} · ${escapeHtml(item.recipient)}</span><small>${escapeHtml(item.date || '')}${item.removed ? ' · Removida' : ''}</small></article>`).join('');
        q('[data-toggle-distributions]').textContent = `Mostrar ${distributions.length} detalhe(s)`;
        const printCurrent = q('[data-print-current]');
        printCurrent.hidden = !data.print_url;
        if (data.print_url) printCurrent.href = data.print_url;
        window.lucide?.createIcons(); setStage('report');
    };
    const verify = async raw => {
        const code = String(raw || '').trim(); if (!code) return;
        closeCamera(); setStage('loading');
        try {
            const response = await fetch(root.dataset.verifyUrl, {method:'POST', credentials:'same-origin', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':token}, body:JSON.stringify({code})});
            const data = await response.json().catch(() => ({})); render(data);
        } catch (_) { render({verdict:'invalid', headline:'Falha na conferência', message:'Não foi possível consultar o documento. Verifique sua conexão e tente novamente.', issues:[{severity:'critical', message:'Consulta indisponível neste momento.'}]}); }
    };
    const start = async () => {
        const core = window.SgcQrScanner;
        if (!core) return;
        if (core.nativePlugin()) {
            try { const result = await core.scanNative({batch:false, verificationUrl:root.dataset.verifyUrl, csrfToken:token}); if (result?.code) verify(result.code); }
            catch (error) { if (error?.code !== 'SCAN_CANCELLED') render({verdict:'invalid', headline:'Câmera indisponível', message:error?.message || 'Não foi possível abrir a câmera.', issues:[]}); }
            return;
        }
        if (!core.supportsWeb()) { q('[data-manual-code]').focus(); return; }
        try {
            dialog.showModal();
            if (!historyArmed) { history.pushState({quickVerificationCamera:true}, ''); historyArmed = true; }
            scannerSession = await core.openWeb({video, single:true, interval:140, onCode:code => verify(code)});
        } catch (_) { closeCamera(); q('[data-manual-code]').focus(); }
    };
    root.addEventListener('click', event => {
        if (event.target.closest('[data-start-scan]')) start();
        if (event.target.closest('[data-close-camera]')) closeCamera();
        if (event.target.closest('[data-verify-manual]')) verify(q('[data-manual-code]').value);
        if (event.target.closest('[data-reset]')) setStage('ready');
        if (event.target.closest('[data-toggle-distributions]')) { const list = q('[data-distributions]'); list.hidden = !list.hidden; event.target.closest('button').textContent = list.hidden ? `Mostrar ${list.children.length} detalhe(s)` : 'Ocultar detalhes'; }
    });
    window.addEventListener('popstate', () => { if (historyArmed) { historyArmed = false; closeCamera(false); } });
    window.addEventListener('pagehide', () => closeCamera(false));
})();

