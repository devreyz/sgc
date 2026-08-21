(function () {
    'use strict';

    const root = document.querySelector('[data-accounting-page]');
    if (!root) return;

    const page = root.dataset.accountingPage;
    const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const quantity = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 4 });

    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const icon = (name, className = '') => `<i data-lucide="${esc(name)}" class="${esc(className)}" aria-hidden="true"></i>`;
    const refreshIcons = () => window.lucide?.createIcons?.();
    const badge = (label, tone = 'neutral') => `<span class="acc-badge acc-badge-${esc(tone)}">${esc(label)}</span>`;
    const skeletons = (count = 4) => Array.from({ length: count }, () => '<div class="acc-skeleton"></div>').join('');

    async function getJson(url, signal) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
            signal,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Não foi possível carregar os dados.');
        return payload;
    }

    async function postJson(url, body) {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
            credentials: 'same-origin',
            cache: 'no-store',
            body: JSON.stringify(body || {}),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const details = Array.isArray(payload.issues) ? payload.issues.map(issue => issue.message).join(' ') : '';
            throw new Error(details || payload.message || 'Não foi possível concluir a ação.');
        }
        return payload;
    }

    function showError(container, error) {
        container.innerHTML = `<div class="acc-error" role="alert">${esc(error.message || error)}</div>`;
    }

    function filtersUrl(base, filters) {
        const url = new URL(base, window.location.origin);
        Object.entries(filters || {}).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) url.searchParams.set(key, value);
        });
        return url.toString();
    }

    async function loadQueue() {
        const target = document.querySelector('[data-queue-list]');
        const summary = document.querySelector('[data-queue-summary]');
        target.innerHTML = skeletons(4);
        try {
            const payload = await getJson(root.dataset.queueUrl);
            const baseProcesses = root.dataset.processesUrl;
            summary.innerHTML = `
                <div class="acc-summary-item"><span>Processos em aberto</span><strong>${esc(payload.summary.open_processes)}</strong><small>Fechados ou parcialmente recebidos</small></div>
                <div class="acc-summary-item"><span>Saldo a receber</span><strong>${money.format(payload.summary.open_amount || 0)}</strong><small>Conforme snapshots das cobranças</small></div>
                <div class="acc-summary-item"><span>Fluxo atual</span><strong>Fase 2A</strong><small>${esc(payload.summary.legacy_state)}</small></div>`;
            if (payload.empty) {
                target.innerHTML = `<div class="acc-empty">${icon('circle-check')}<div><strong>Nenhuma ação pendente</strong><br><span>A fila está limpa neste momento.</span></div></div>`;
            } else {
                target.innerHTML = payload.queue.map(item => {
                    const url = filtersUrl(baseProcesses, item.filters);
                    return `<a class="acc-queue-row acc-tone-${esc(item.tone)}" href="${esc(url)}">
                        <span class="acc-queue-icon">${icon(item.icon)}</span>
                        <span class="acc-queue-copy"><strong>${esc(item.label)}</strong><span>Abrir processos relacionados</span></span>
                        <span class="acc-queue-count">${esc(item.count)}</span>${icon('chevron-right', 'acc-queue-arrow')}
                    </a>`;
                }).join('');
            }
            refreshIcons();
        } catch (error) {
            showError(target, error);
        }
    }

    function processDesktopRow(process) {
        return `<tr>
            <td><a class="acc-link" href="${esc(process.url)}">${esc(process.number)}</a><div class="acc-muted">${esc(process.issued_at || '')}</div></td>
            <td><strong>${esc(process.project)}</strong><div class="acc-muted">${esc(process.project_code || '')}</div></td>
            <td>${esc(process.recipient)}<div class="acc-muted">${esc(process.recipient_type)}</div></td>
            <td>${badge(process.state.label, process.state.tone)}<div class="acc-muted">${esc(process.state.next_action)}</div></td>
            <td class="acc-money">${money.format(process.net || 0)}<div class="acc-muted">Saldo ${money.format(process.remaining || 0)}</div></td>
            <td>${process.critical_issues ? badge(`${process.critical_issues} crítica(s)`, 'danger') : badge('Íntegro', 'success')}</td>
        </tr>`;
    }

    function processMobileRow(process) {
        return `<article class="acc-mobile-row">
            <div class="acc-mobile-head"><div><a class="acc-link" href="${esc(process.url)}">${esc(process.number)}</a><div class="acc-muted">${esc(process.project)}</div></div>${badge(process.state.label, process.state.tone)}</div>
            <div class="acc-mobile-meta">
                <span>Destinatário<strong>${esc(process.recipient)}</strong></span>
                <span>Valor líquido<strong>${money.format(process.net || 0)}</strong></span>
                <span>Próxima ação<strong>${esc(process.state.next_action)}</strong></span>
                <span>Integridade<strong>${process.critical_issues ? `${esc(process.critical_issues)} crítica(s)` : 'Sem bloqueios'}</strong></span>
            </div>
        </article>`;
    }

    async function initProcesses() {
        const form = document.querySelector('[data-process-filters]');
        const tableBody = document.querySelector('[data-process-table]');
        const mobile = document.querySelector('[data-process-mobile]');
        const pagination = document.querySelector('[data-process-pagination]');
        const projectSelect = form.elements.project;
        const organizationSelect = form.elements.organization;
        const customerSelect = form.elements.customer;
        let controller;
        let filterOptionsLoaded = false;

        const current = new URLSearchParams(window.location.search);
        ['search', 'project', 'organization', 'customer', 'from', 'until', 'financial_status', 'authorization_status', 'fiscal_status', 'accountability_status', 'pending'].forEach(name => {
            if (current.has(name) && form.elements[name]) form.elements[name].value = current.get(name);
        });

        async function load(pageNumber = 1) {
            controller?.abort();
            controller = new AbortController();
            tableBody.innerHTML = `<tr><td colspan="6">${skeletons(5)}</td></tr>`;
            mobile.innerHTML = skeletons(5);
            pagination.innerHTML = '';
            const params = Object.fromEntries(new FormData(form).entries());
            params.page = pageNumber;
            try {
                const payload = await getJson(filtersUrl(root.dataset.processesDataUrl, params), controller.signal);
                const paginator = payload.processes;
                if (!filterOptionsLoaded) {
                    projectSelect.innerHTML = '<option value="">Todos os projetos</option>' + payload.filters.projects.map(option => `<option value="${esc(option.id)}">${esc(option.label)}</option>`).join('');
                    projectSelect.value = params.project || '';
                    organizationSelect.innerHTML = '<option value="">Todas as organizações</option>' + payload.filters.organizations.map(option => `<option value="${esc(option.id)}">${esc(option.label)}</option>`).join('');
                    organizationSelect.value = params.organization || '';
                    customerSelect.innerHTML = '<option value="">Todos os clientes</option>' + payload.filters.customers.map(option => `<option value="${esc(option.id)}">${esc(option.label)}</option>`).join('');
                    customerSelect.value = params.customer || '';
                    filterOptionsLoaded = true;
                }
                if (!paginator.data.length) {
                    tableBody.innerHTML = '<tr><td colspan="6"><div class="acc-empty">Nenhum processo encontrado.</div></td></tr>';
                    mobile.innerHTML = '<div class="acc-empty">Nenhum processo encontrado.</div>';
                } else {
                    tableBody.innerHTML = paginator.data.map(processDesktopRow).join('');
                    mobile.innerHTML = paginator.data.map(processMobileRow).join('');
                }
                pagination.innerHTML = `<span>${esc(paginator.from || 0)}–${esc(paginator.to || 0)} de ${esc(paginator.total || 0)}</span>
                    <div class="acc-pagination-actions">
                        <button class="acc-button" type="button" data-page="${paginator.current_page - 1}" ${paginator.current_page <= 1 ? 'disabled' : ''}>${icon('chevron-left')} Anterior</button>
                        <button class="acc-button" type="button" data-page="${paginator.current_page + 1}" ${paginator.current_page >= paginator.last_page ? 'disabled' : ''}>Próxima ${icon('chevron-right')}</button>
                    </div>`;
                pagination.querySelectorAll('[data-page]').forEach(button => button.addEventListener('click', () => load(Number(button.dataset.page))));
                const browserUrl = new URL(window.location.href);
                Object.entries(params).forEach(([key, value]) => value ? browserUrl.searchParams.set(key, value) : browserUrl.searchParams.delete(key));
                browserUrl.searchParams.delete('page');
                history.replaceState({}, '', browserUrl);
                refreshIcons();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    tableBody.innerHTML = `<tr><td colspan="6"><div class="acc-error">${esc(error.message)}</div></td></tr>`;
                    showError(mobile, error);
                }
            }
        }

        let timer;
        form.addEventListener('input', event => {
            clearTimeout(timer);
            timer = setTimeout(() => load(1), event.target.name === 'search' ? 320 : 0);
        });
        form.addEventListener('submit', event => { event.preventDefault(); load(1); });
        form.querySelector('[data-clear-filters]').addEventListener('click', () => { form.reset(); load(1); });
        load(Number(current.get('page') || 1));
    }

    function renderDistributions(distributions) {
        const rows = distributions.data || [];
        if (!rows.length) return '<div class="acc-empty">Nenhuma distribuição vinculada.</div>';
        const table = `<div class="acc-table-wrap"><table class="acc-table"><thead><tr><th>Origem</th><th>Produto e destino</th><th>Membro</th><th>Quantidade</th><th>Valor</th></tr></thead><tbody>${rows.map(row => `<tr>
            <td>${esc(row.parent?.date || 'Sem origem')}<div class="acc-muted">Entrega #${esc(row.parent?.id || '—')}</div></td>
            <td><strong>${esc(row.product)}</strong><div class="acc-muted">${esc(row.customer)}</div></td>
            <td>${esc(row.member)}</td>
            <td>${quantity.format(row.quantity || 0)} ${esc(row.unit)}</td>
            <td class="acc-money">${money.format(row.gross_value || 0)}<div class="acc-muted">${money.format(row.unit_price || 0)} / ${esc(row.unit)}</div></td>
        </tr>`).join('')}</tbody></table></div>`;
        if ((distributions.last_page || 1) <= 1) return table;
        return table + `<div class="acc-pagination"><span>${esc(distributions.from || 0)}–${esc(distributions.to || 0)} de ${esc(distributions.total || 0)}</span><div class="acc-pagination-actions">
            <button class="acc-button" type="button" data-dist-page="${distributions.current_page - 1}" ${distributions.current_page <= 1 ? 'disabled' : ''}>${icon('chevron-left')} Anterior</button>
            <button class="acc-button" type="button" data-dist-page="${distributions.current_page + 1}" ${distributions.current_page >= distributions.last_page ? 'disabled' : ''}>Próxima ${icon('chevron-right')}</button>
        </div></div>`;
    }

    function renderProducerReceipts(receipts) {
        const rows = receipts.data || [];
        const list = `<ul class="acc-simple-list">${rows.length ? rows.map(receipt => `<li class="acc-simple-item"><strong>${esc(receipt.number)} · ${esc(receipt.member)}</strong><span>${esc(receipt.status_label)} · ${money.format(receipt.net || 0)}</span></li>`).join('') : '<li class="acc-simple-item">Nenhum comprovante de membro relacionado.</li>'}</ul>`;
        if ((receipts.last_page || 1) <= 1) return list;
        return list + `<div class="acc-pagination"><span>${esc(receipts.from || 0)}–${esc(receipts.to || 0)} de ${esc(receipts.total || 0)}</span><div class="acc-pagination-actions">
            <button class="acc-button" type="button" data-producer-page="${receipts.current_page - 1}" ${receipts.current_page <= 1 ? 'disabled' : ''}>${icon('chevron-left')} Anterior</button>
            <button class="acc-button" type="button" data-producer-page="${receipts.current_page + 1}" ${receipts.current_page >= receipts.last_page ? 'disabled' : ''}>Próxima ${icon('chevron-right')}</button>
        </div></div>`;
    }

    function renderAuthorizations(rounds) {
        if (!rounds?.length) return '<div class="acc-empty">Processo anterior ao workflow de autorização.</div>';
        return `<ul class="acc-simple-list">${rounds.map(round => `<li class="acc-simple-item">
            <strong>Versão ${esc(round.sequence)} · ${esc(round.label)}</strong>
            ${round.organization ? `<span>Organização: ${esc(round.organization)}</span>` : ''}
            <span>Enviada em ${esc(round.sent_at || '—')} por ${esc(round.sent_by || 'Membro não identificado')}</span>
            ${round.responded_at ? `<span>Resposta em ${esc(round.responded_at)} por ${esc(round.responded_by || 'Representante autorizado')}</span>` : ''}
            ${round.validity ? `<span>Validade atual: ${esc(round.validity)}</span>` : ''}
            ${round.message ? `<p class="acc-auth-message">${esc(round.message)}</p>` : ''}
            ${round.invalidation_reason ? `<p class="acc-auth-message acc-auth-warning">${esc(round.invalidation_reason)}</p>` : ''}
        </li>`).join('')}</ul>`;
    }

    async function initDossier() {
        const target = document.querySelector('[data-dossier]');
        target.innerHTML = skeletons(7);
        try {
            const payload = await getJson(root.dataset.processDataUrl);
            const process = payload.process;
            const integrity = process.integrity;
            target.innerHTML = `
                <section class="acc-panel">
                    <div class="acc-state-strip">
                        <div class="acc-state-item"><span>Processo</span><strong>${esc(process.state.label)}</strong></div>
                        <div class="acc-state-item"><span>Autorização</span><strong>${esc(process.workflow.authorization.label)}</strong></div>
                        <div class="acc-state-item"><span>Fiscal</span><strong>${esc(process.workflow.fiscal.label)}</strong></div>
                        <div class="acc-state-item"><span>Prestação de contas</span><strong>${esc(process.workflow.accountability.label)}</strong></div>
                    </div>
                    <div class="acc-tabs" role="tablist">
                        <button class="acc-tab is-active" type="button" data-tab="overview">Visão geral</button>
                        <button class="acc-tab" type="button" data-tab="origin">Distribuições</button>
                        <button class="acc-tab" type="button" data-tab="finance">Financeiro</button>
                        <button class="acc-tab" type="button" data-tab="authorization">Autorização</button>
                        <button class="acc-tab" type="button" data-tab="related">Comprovantes relacionados</button>
                        <button class="acc-tab" type="button" data-tab="timeline">Histórico</button>
                    </div>
                    <div class="acc-tab-panel is-active" data-panel="overview">
                        <div class="acc-detail-grid">
                            <div class="acc-detail"><span>Projeto</span><strong>${esc(process.project?.title || 'Não identificado')}</strong></div>
                            <div class="acc-detail"><span>Destinatário</span><strong>${esc(process.recipient.name)}</strong></div>
                            <div class="acc-detail"><span>Período</span><strong>${esc(process.period)}</strong></div>
                            <div class="acc-detail"><span>Valor bruto</span><strong>${money.format(process.financial.gross || 0)}</strong></div>
                            <div class="acc-detail"><span>Taxas</span><strong>${money.format(process.financial.fees || 0)}</strong></div>
                            <div class="acc-detail"><span>Valor líquido</span><strong>${money.format(process.financial.net || 0)}</strong></div>
                        </div>
                    </div>
                    <div class="acc-tab-panel" data-panel="origin">${renderDistributions(payload.distributions)}</div>
                    <div class="acc-tab-panel" data-panel="finance">
                        <div class="acc-detail-grid">
                            <div class="acc-detail"><span>Total recebido</span><strong>${money.format(process.financial.received || 0)}</strong></div>
                            <div class="acc-detail"><span>Saldo restante</span><strong>${money.format(process.financial.remaining || 0)}</strong></div>
                            <div class="acc-detail"><span>Situação financeira</span><strong>${esc(process.financial.status_label)}</strong></div>
                        </div>
                        <h3 class="acc-section-title" style="margin-top:.8rem">Recebimentos</h3>
                        <ul class="acc-simple-list">${payload.payments.length ? payload.payments.map(payment => `<li class="acc-simple-item"><strong>${money.format(payment.amount || 0)} · ${esc(payment.date)}</strong><span>${esc(payment.account || payment.method || 'Sem conta informada')}</span></li>`).join('') : '<li class="acc-simple-item">Nenhum recebimento registrado.</li>'}</ul>
                    </div>
                    <div class="acc-tab-panel" data-panel="authorization">${renderAuthorizations(payload.authorizations)}</div>
                    <div class="acc-tab-panel" data-panel="related">
                        ${renderProducerReceipts(payload.producer_receipts)}
                    </div>
                    <div class="acc-tab-panel" data-panel="timeline">
                        <ul class="acc-simple-list">${payload.timeline.length ? payload.timeline.map(event => `<li class="acc-simple-item"><strong>${esc(event.description)}</strong><span>${esc(event.date)} · ${esc(event.actor)}</span></li>`).join('') : '<li class="acc-simple-item">Nenhum evento registrado.</li>'}</ul>
                    </div>
                </section>
                <aside class="acc-side-stack">
                    <section class="acc-panel"><div class="acc-panel-head"><div><h2>Próxima ação</h2><p>${esc(process.state.next_action)}</p></div>${badge(process.workflow.authorization.label, process.workflow.authorization.state === 'authorized' ? 'success' : (['invalidated','correction_requested'].includes(process.workflow.authorization.state) ? 'danger' : 'warning'))}</div>
                        <div class="acc-action-box" data-authorization-action>${root.dataset.canSendAuthorization === '1' && ['legacy_unsubmitted','correction_requested','invalidated','cancelled'].includes(process.workflow.authorization.state) && process.financial.status === 'pending_payment' && !integrity.critical_count ? `<button class="acc-button acc-button-primary" type="button" data-send-authorization>${icon('send')} Enviar para organização</button><div class="acc-action-feedback" aria-live="polite"></div>` : '<span class="acc-muted">Nenhuma ação interna disponível agora.</span>'}</div></section>
                    <section class="acc-panel"><div class="acc-panel-head"><div><h2>Integridade</h2><p>${esc(integrity.critical_count)} bloqueio(s)</p></div>${badge(integrity.critical_count ? 'Conferir' : 'Íntegro', integrity.critical_count ? 'danger' : 'success')}</div>
                        <div style="padding:.72rem"><ul class="acc-integrity-list">${integrity.issues.length ? integrity.issues.map(issue => `<li class="acc-integrity-item">${esc(issue.message)}</li>`).join('') : '<li class="acc-simple-item">Nenhuma inconsistência crítica encontrada.</li>'}</ul></div></section>
                    <section class="acc-panel"><div class="acc-panel-head"><div><h2>Documentos</h2><p>${esc(payload.documents.length)} arquivo(s)</p></div></div>
                        <div style="padding:.72rem"><ul class="acc-simple-list">${payload.documents.length ? payload.documents.map(document => `<li class="acc-simple-item"><strong>${esc(document.name)}</strong><span>${esc(document.category)} · ${esc(document.date)}</span></li>`).join('') : '<li class="acc-simple-item">Nenhum documento anexado.</li>'}</ul></div></section>
                </aside>`;
            target.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => {
                target.querySelectorAll('[data-tab]').forEach(tab => tab.classList.toggle('is-active', tab === button));
                target.querySelectorAll('[data-panel]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.panel === button.dataset.tab));
            }));
            const sendButton = target.querySelector('[data-send-authorization]');
            sendButton?.addEventListener('click', async () => {
                if (!window.confirm(`Enviar esta cobrança para autorização?\n\nValor: ${money.format(process.financial.net || 0)}\nPeríodo: ${process.period}`)) return;
                const feedback = target.querySelector('.acc-action-feedback');
                sendButton.disabled = true;
                feedback.textContent = 'Enviando...';
                try {
                    await postJson(root.dataset.authorizationSendUrl, { operation_key: crypto.randomUUID() });
                    feedback.textContent = 'Cobrança enviada.';
                    await initDossier();
                } catch (error) {
                    feedback.textContent = error.message;
                    feedback.classList.add('is-error');
                    sendButton.disabled = false;
                }
            });
            const bindDistributionPagination = () => target.querySelectorAll('[data-dist-page]').forEach(button => button.addEventListener('click', async () => {
                const panel = target.querySelector('[data-panel="origin"]');
                panel.innerHTML = skeletons(3);
                try {
                    const pagePayload = await getJson(filtersUrl(root.dataset.processDataUrl, { distributions_page: button.dataset.distPage }));
                    panel.innerHTML = renderDistributions(pagePayload.distributions);
                    bindDistributionPagination();
                    refreshIcons();
                } catch (error) {
                    showError(panel, error);
                }
            }));
            bindDistributionPagination();
            const bindProducerPagination = () => target.querySelectorAll('[data-producer-page]').forEach(button => button.addEventListener('click', async () => {
                const panel = target.querySelector('[data-panel="related"]');
                panel.innerHTML = skeletons(3);
                try {
                    const pagePayload = await getJson(filtersUrl(root.dataset.processDataUrl, { producer_receipts_page: button.dataset.producerPage }));
                    panel.innerHTML = renderProducerReceipts(pagePayload.producer_receipts);
                    bindProducerPagination();
                    refreshIcons();
                } catch (error) {
                    showError(panel, error);
                }
            }));
            bindProducerPagination();
            refreshIcons();
        } catch (error) {
            showError(target, error);
        }
    }

    if (page === 'queue') loadQueue();
    if (page === 'processes') initProcesses();
    if (page === 'dossier') initDossier();
})();
