(() => {
    'use strict';

    const config = window.AssociatePortalConfig || {};
    const root = document.querySelector('[data-associate-page]');
    if (!root || !config.page) return;

    const controllers = new Map();
    const money = value => new Intl.NumberFormat('pt-BR', {style:'currency', currency:'BRL'}).format(Number(value || 0));
    const qty = value => new Intl.NumberFormat('pt-BR', {maximumFractionDigits:3}).format(Number(value || 0));
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const skeleton = (count = 4) => `<div class="portal-skeleton-list" aria-busy="true">${Array.from({length:count}, () => '<div class="portal-skeleton"></div>').join('')}</div>`;
    const errorHtml = message => `<div class="portal-async-error"><i class="ph-duotone ph-warning-circle"></i><strong>Não foi possível carregar</strong><span>${esc(message || 'Tente novamente em instantes.')}</span><button class="portal-retry" type="button">Tentar novamente</button></div>`;

    async function request(key, url, params = {}) {
        controllers.get(key)?.abort();
        const controller = new AbortController();
        controllers.set(key, controller);
        const target = new URL(url, window.location.origin);
        Object.entries(params).forEach(([name, value]) => {
            if (value !== '' && value !== null && value !== undefined) target.searchParams.set(name, value);
        });
        const response = await fetch(target, {
            headers: {Accept: 'application/json'},
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller.signal,
        });
        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || 'Falha ao consultar os dados.');
        }
        return response.json();
    }

    function pages(meta) {
        if (!meta || meta.last_page <= 1) return '';
        const current = Number(meta.current_page || 1);
        const last = Number(meta.last_page || 1);
        const start = Math.max(1, Math.min(current - 2, last - 4));
        const end = Math.min(last, start + 4);
        let buttons = `<button class="portal-page-button" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''} aria-label="Página anterior"><i class="ph ph-arrow-left"></i></button>`;
        for (let page = start; page <= end; page++) buttons += `<button class="portal-page-button ${page === current ? 'active' : ''}" data-page="${page}" ${page === current ? 'aria-current="page"' : ''}>${page}</button>`;
        buttons += `<button class="portal-page-button" data-page="${current + 1}" ${current >= last ? 'disabled' : ''} aria-label="Próxima página"><i class="ph ph-arrow-right"></i></button>`;
        return `<nav class="portal-pagination" aria-label="Paginação">${buttons}</nav>`;
    }

    function bindPages(host, callback) {
        host.querySelectorAll('[data-page]').forEach(button => button.addEventListener('click', () => callback(Number(button.dataset.page))));
    }

    function replaceSectionBody(section, html) {
        [...section.children].slice(1).forEach(child => child.remove());
        section.insertAdjacentHTML('beforeend', html);
    }

    function statusClass(value) { return ['approved','pending','rejected','cancelled','paid','partially_paid','pending_payment','obsolete'].includes(value) ? value : ''; }

    async function dashboard() {
        const financialValues = root.querySelectorAll('.financial-primary > strong, .financial-metric > strong');
        financialValues.forEach(node => node.classList.add('portal-loading-value'));
        const sections = root.querySelectorAll('.dashboard-workspace .dashboard-section');
        sections.forEach(section => replaceSectionBody(section, skeleton(3)));
        try {
            const data = await request('dashboard', config.urls.dashboard);
            const values = [data.summary.receivable, data.summary.issued_this_month, data.summary.paid_this_month, data.summary.total_net];
            financialValues.forEach((node, index) => { node.classList.remove('portal-loading-value'); node.textContent = money(values[index]); });
            renderDashboardProjects(sections[0], data.projects || []);
            renderDashboardDeliveries(sections[1], data.deliveries || []);
        } catch (error) {
            if (error.name === 'AbortError') return;
            financialValues.forEach(node => node.classList.remove('portal-loading-value'));
            sections.forEach(section => { replaceSectionBody(section, errorHtml(error.message)); section.querySelector('.portal-retry')?.addEventListener('click', dashboard); });
        }
    }

    function renderDashboardProjects(section, projects) {
        if (!projects.length) return replaceSectionBody(section, '<div class="dashboard-empty"><div class="dashboard-empty-content"><span class="dashboard-empty-icon"><i class="ph-duotone ph-folder-open"></i></span><strong>Nenhum projeto em execução</strong><span>Quando houver um projeto ativo, ele aparecerá aqui.</span></div></div>');
        const attention = projects.filter(item => item.limit?.is_near || item.limit?.is_full).length;
        const notice = attention ? `<div class="projects-attention"><span class="projects-attention-icon"><i class="ph-duotone ph-warning-circle"></i></span><div><strong>${attention} ${attention === 1 ? 'projeto precisa' : 'projetos precisam'} de atenção</strong><span>O limite financeiro está próximo ou foi atingido.</span></div></div>` : '';
        const rows = projects.map(item => {
            const limit = item.limit || {};
            const percent = Number(limit.percent || 0);
            const tone = percent >= 100 ? 'danger' : (percent >= 80 ? 'warning' : '');
            const limitHtml = limit.max === null ? '<span class="project-no-limit"><i class="ph-duotone ph-info"></i><span>Este projeto não possui limite financeiro informado.</span></span>' : `<div class="project-limit-area"><div class="project-limit-head"><div class="project-limit-copy"><strong>Limite financeiro</strong><span>${limit.is_full ? 'Limite atingido' : (limit.is_near ? 'Próximo do limite' : 'Dentro do limite')}</span></div><span class="project-limit-percent">${Math.round(percent)}%</span></div><div class="project-progress ${tone}"><span style="width:${Math.min(100,percent)}%"></span></div><div class="project-limit-values"><div class="project-limit-value"><span>Utilizado</span><strong>${money(limit.accumulated)}</strong></div><div class="project-limit-value remaining"><span>Disponível</span><strong>${money(limit.remaining)}</strong></div><div class="project-limit-value"><span>Limite total</span><strong>${money(limit.max)}</strong></div></div></div>`;
            return `<a class="project-item" href="${esc(item.url)}"><div class="project-main"><span class="project-icon"><i class="ph-duotone ph-folder"></i></span><div class="project-info"><div class="project-title-line"><strong class="project-title">${esc(item.title)}</strong><span class="project-status"><i class="ph ph-circle-fill"></i>Em execução</span></div>${item.customer ? `<span class="project-customer"><i class="ph ph-buildings"></i><span>${esc(item.customer)}</span></span>` : ''}</div><span class="project-open"><i class="ph ph-arrow-right"></i></span></div>${limitHtml}</a>`;
        }).join('');
        replaceSectionBody(section, `${notice}<div class="project-list">${rows}</div>`);
    }

    function renderDashboardDeliveries(section, deliveries) {
        if (!deliveries.length) return replaceSectionBody(section, '<div class="dashboard-empty"><div class="dashboard-empty-content"><span class="dashboard-empty-icon"><i class="ph-duotone ph-package"></i></span><strong>Nenhuma entrega registrada</strong><span>Suas entregas recentes aparecerão aqui.</span></div></div>');
        const rows = deliveries.map(item => `<article class="delivery-item"><span class="delivery-date" aria-label="${esc(item.date)}"><strong>${esc(item.day || '--')}</strong><span>${esc(String(item.month || '---').slice(0,3).toUpperCase())}</span></span><div class="delivery-content"><div class="delivery-title-line"><strong class="delivery-title">${esc(item.product || 'Produto')}</strong><div><strong>${qty(item.quantity)} ${esc(item.unit || '')}</strong><span class="delivery-status ${statusClass(item.status)}">${esc(item.status_label)}</span></div></div><span class="delivery-project"><i class="ph ph-folder"></i><span>${esc(item.project || 'Projeto')}</span></span></div></article>`).join('');
        replaceSectionBody(section, `<div class="delivery-list">${rows}</div>`);
    }

    const projectState = {page:1, status:'active', search:''};
    async function projects(page = 1) {
        projectState.page = page;
        const sections = root.querySelectorAll('.projects-section');
        const listSection = sections[1];
        const results = listSection?.querySelector('[data-project-results]');
        if (!results) return;
        results.innerHTML = skeleton(4);
        try {
            const data = await request('projects', config.urls.projects, projectState);
            const items = data.data || [];
            root.querySelectorAll('.projects-result-count').forEach(node => node.innerHTML = `<i class="ph ph-folder-simple"></i>${data.total} ${data.total === 1 ? 'projeto' : 'projetos'}`);
            const overviewNumber = sections[0]?.querySelector('.projects-overview-main > strong');
            if (overviewNumber) overviewNumber.textContent = data.total;
            updateProjectFilterSummary(data);
            history.replaceState({}, '', projectQueryUrl());
            if (!items.length) {
                results.innerHTML = '<div class="projects-empty"><div class="projects-empty-content"><span class="projects-empty-icon"><i class="ph-duotone ph-folder-open"></i></span><strong>Nenhum projeto encontrado</strong><span>Ajuste a busca ou selecione outra situação.</span></div></div>';
                return;
            }
            const cards = items.map(projectCard).join('');
            results.innerHTML = `<div class="projects-list">${cards}</div>${pages(data)}`;
            bindPages(results, projects);
        } catch (error) {
            if (error.name === 'AbortError') return;
            results.innerHTML = errorHtml(error.message);
            results.querySelector('.portal-retry')?.addEventListener('click', () => projects(projectState.page));
        }
    }

    function projectFilterLabel(status) {
        return ({
            active: 'Projetos em execução',
            history: 'Histórico encerrado',
            all: 'Todos os projetos',
            suspended: 'Projetos suspensos',
            deliveries_closed: 'Entregas encerradas',
            completed: 'Projetos concluídos',
            cancelled: 'Projetos cancelados',
            archived: 'Projetos arquivados',
        })[status] || 'Seus projetos';
    }

    function updateProjectFilterSummary(data) {
        const label = root.querySelector('[data-project-filter-label]');
        const counts = root.querySelector('[data-project-counts]');
        if (label) label.textContent = projectFilterLabel(data.filter || projectState.status);
        if (counts) counts.textContent = `${Number(data.counts?.active || 0)} em execução · ${Number(data.counts?.history || 0)} no histórico · ${Number(data.counts?.all || 0)} no total`;
        const activeCount = root.querySelector('[data-project-active-count]');
        const historyCount = root.querySelector('[data-project-history-count]');
        const allCount = root.querySelector('[data-project-all-count]');
        if (activeCount) activeCount.textContent = `${Number(data.counts?.active || 0)} em execução`;
        if (historyCount) historyCount.textContent = `${Number(data.counts?.history || 0)} no histórico`;
        if (allCount) allCount.textContent = `${Number(data.counts?.all || 0)} participações`;

        const overviewTitle = root.querySelector('.projects-section:first-child .projects-section-copy h2');
        const overviewLabel = root.querySelector('.projects-overview-label');
        if (overviewTitle) overviewTitle.textContent = projectFilterLabel(data.filter || projectState.status);
        if (overviewLabel) overviewLabel.innerHTML = `<i class="ph-duotone ph-folder-open"></i>${projectFilterLabel(data.filter || projectState.status)}`;
    }

    function projectQueryUrl() {
        const url = new URL(location.href);
        ['page', 'status', 'search'].forEach(key => url.searchParams.delete(key));
        if (projectState.status && projectState.status !== 'active') url.searchParams.set('status', projectState.status);
        if (projectState.search) url.searchParams.set('search', projectState.search);
        if (projectState.page > 1) url.searchParams.set('page', projectState.page);
        return url.pathname + url.search;
    }

    function setupProjectFilters() {
        const form = root.querySelector('[data-project-filters]');
        if (!form) return;
        const query = new URLSearchParams(location.search);
        const allowed = ['active', 'history', 'all', 'suspended', 'deliveries_closed', 'completed', 'cancelled', 'archived'];
        projectState.status = allowed.includes(query.get('status')) ? query.get('status') : 'active';
        projectState.search = String(query.get('search') || '').slice(0, 80);
        projectState.page = Math.max(1, Number(query.get('page') || 1));
        form.status.value = projectState.status;
        form.search.value = projectState.search;

        form.addEventListener('submit', event => {
            event.preventDefault();
            projectState.status = form.status.value;
            projectState.search = form.search.value.trim();
            projects(1);
        });
        form.status.addEventListener('change', () => {
            projectState.status = form.status.value;
            projectState.search = form.search.value.trim();
            projects(1);
        });
        let searchTimer;
        form.search.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                projectState.search = form.search.value.trim();
                projects(1);
            }, 350);
        });
        form.querySelector('[data-project-clear]')?.addEventListener('click', () => {
            form.reset();
            form.status.value = 'active';
            projectState.status = 'active';
            projectState.search = '';
            projects(1);
        });
    }

    function projectCard(item) {
        const limit = item.limit || {};
        const financial = item.financial || {};
        const status = ['active','suspended','deliveries_closed','completed','cancelled','archived'].includes(item.status) ? item.status : 'active';
        const isCurrent = status === 'active';
        const actionLabel = isCurrent ? 'Acompanhar projeto' : 'Consultar projeto';
        const icon = isCurrent ? 'ph-folder-open' : 'ph-archive-box';
        const percent = Number(limit.percent || 0);
        const total = Number(financial.total || 0);
        const width = value => total > 0 ? Math.min(100, Number(value || 0) / total * 100) : 0;
        const limitHtml = limit.max === null ? '<section class="project-no-limit"><span class="project-no-limit-icon"><i class="ph-duotone ph-infinity"></i></span><span><strong>Sem limite financeiro informado</strong><span>Este projeto não possui teto financeiro definido.</span></span></section>' : `<section class="project-limit"><div class="project-subhead"><span class="project-subhead-icon"><i class="ph-duotone ph-gauge"></i></span><strong>Limite financeiro</strong><span class="project-limit-percent">${Math.round(percent)}%</span></div><div class="project-limit-highlight"><span>Disponível para utilizar</span><strong>${money(limit.remaining)}</strong></div><div class="project-limit-stats"><div class="project-limit-stat"><span>Utilizado</span><strong>${money(limit.accumulated)}</strong></div><div class="project-limit-stat"><span>Limite total</span><strong>${money(limit.max)}</strong></div></div><div class="project-progress"><span style="width:${Math.min(100,percent)}%"></span></div></section>`;
        const financialHtml = total > 0 ? `<div class="project-financial-total"><span>Total distribuído</span><strong>${money(total)}</strong></div><div class="project-financial-bar"><span class="unbilled" style="width:${width(financial.unbilled)}%"></span><span class="billed" style="width:${width(financial.billed)}%"></span><span class="paid" style="width:${width(financial.paid)}%"></span></div><div class="project-financial-values"><div class="project-financial-value unbilled"><span>A faturar</span><strong>${money(financial.unbilled)}</strong></div><div class="project-financial-value billed"><span>Em comprovante</span><strong>${money(financial.billed)}</strong></div><div class="project-financial-value paid"><span>Pago</span><strong>${money(financial.paid)}</strong></div></div>` : '<div class="project-financial-empty"><i class="ph-duotone ph-info"></i><span>Ainda não há distribuições financeiras.</span></div>';
        return `<article class="project-entry status-${status} ${limit.is_full ? 'has-danger' : (limit.is_near ? 'has-warning' : '')}"><header class="project-entry-head"><span class="project-entry-icon"><i class="ph-duotone ${icon}"></i></span><div class="project-entry-heading"><div class="project-entry-title-line"><strong class="project-entry-title">${esc(item.title)}</strong><span class="project-status status-${status}"><i class="ph ph-circle-fill"></i>${esc(item.status_label || projectFilterLabel(status))}</span></div><div class="project-entry-meta">${item.customer ? `<span class="project-meta-item customer"><i class="ph ph-buildings"></i><span>${esc(item.customer)}</span></span>` : ''}${item.type ? `<span class="project-meta-item type"><i class="ph ph-tag"></i><span>${esc(String(item.type).toUpperCase())}</span></span>` : ''}${item.period ? `<span class="project-meta-item period"><i class="ph ph-calendar-dots"></i><span>${esc(item.period)}</span></span>` : ''}</div></div><a class="project-open-main" href="${esc(item.url)}">${actionLabel}<i class="ph ph-arrow-right"></i></a></header><div class="project-entry-body">${limitHtml}<section class="project-financial"><div class="project-subhead"><span class="project-subhead-icon"><i class="ph-duotone ph-wallet"></i></span><strong>Distribuições</strong></div>${financialHtml}</section></div><footer class="project-entry-footer"><a class="project-open-main" href="${esc(item.url)}">${actionLabel}<i class="ph ph-arrow-right"></i></a></footer></article>`;
    }

    const deliveryState = {page:1, status:'', project_id:'', start_date:'', end_date:''};
    async function deliveries(page = 1) {
        deliveryState.page = page;
        const sections = root.querySelectorAll('.delivery-section');
        const listSection = sections[2];
        replaceSectionBody(listSection, skeleton(5));
        try {
            const payload = await request('deliveries', config.urls.deliveries, deliveryState);
            updateDeliverySummary(sections[0], payload);
            populateProjectFilter(payload.projects || []);
            const data = payload.items;
            listSection.querySelector('.delivery-result-count')?.remove();
            const count = listSection.querySelector('.delivery-section-head');
            count?.insertAdjacentHTML('beforeend', `<span class="delivery-result-count"><i class="ph ph-magnifying-glass"></i>${data.total} ${data.total === 1 ? 'resultado' : 'resultados'}</span>`);
            if (!(data.data || []).length) return replaceSectionBody(listSection, '<div class="delivery-empty"><div class="delivery-empty-content"><span class="delivery-empty-icon"><i class="ph-duotone ph-package"></i></span><strong>Nenhuma entrega encontrada</strong><span>Tente alterar os filtros.</span></div></div>');
            replaceSectionBody(listSection, `<div class="delivery-list">${data.data.map(deliveryCard).join('')}</div>${pages(data)}`);
            bindPages(listSection, deliveries);
            history.replaceState({}, '', queryUrl(deliveryState));
        } catch (error) {
            if (error.name === 'AbortError') return;
            replaceSectionBody(listSection, errorHtml(error.message));
            listSection.querySelector('.portal-retry')?.addEventListener('click', () => deliveries(deliveryState.page));
        }
    }

    function updateDeliverySummary(section, payload) {
        const summary = payload.summary || {};
        const financial = payload.financial || {};
        const primary = section.querySelector('.delivery-overview-main > strong');
        if (primary) primary.textContent = money(summary.gross);
        const counts = section.querySelectorAll('.overview-count strong');
        [summary.total, summary.approved, summary.pending].forEach((value,index) => { if (counts[index]) counts[index].textContent = value || 0; });
        const finance = section.querySelectorAll('.financial-item-copy strong');
        [financial.total_net, financial.receivable, financial.paid, financial.total_fees].forEach((value,index) => { if (finance[index]) finance[index].textContent = money(value); });
        const header = section.querySelector('.financial-header span span');
        if (header) header.textContent = `${financial.distribution_count || 0} distribuições`;
    }

    function populateProjectFilter(projects) {
        const select = root.querySelector('#delivery-project');
        if (!select || select.dataset.loaded) return;
        select.innerHTML = '<option value="">Todos os projetos ativos</option>' + projects.map(item => `<option value="${item.id}">${esc(item.title)}</option>`).join('');
        select.value = deliveryState.project_id;
        select.dataset.loaded = '1';
    }

    function deliveryCard(item) {
        const hasFinancial = Number(item.distribution_count || 0) > 0;
        const tone = item.billing_status === 'paid' ? 'paid' : (item.billing_status === 'billed' ? 'billed' : 'unbilled');
        return `<article class="delivery-entry"><span class="delivery-date" aria-label="${esc(item.date)}"><strong>${esc(item.day || '--')}</strong><span>${esc(String(item.month || '---').slice(0,3).toUpperCase())}</span></span><div class="delivery-entry-content"><div class="delivery-entry-head"><div class="delivery-entry-title"><div class="delivery-entry-title-line"><strong class="delivery-product">${esc(item.product || 'Produto')}</strong><span class="delivery-status ${statusClass(item.status)}">${esc(item.status_label)}</span></div><span class="delivery-project"><i class="ph ph-folder"></i><span>${esc(item.project || 'Projeto')}</span></span></div><div class="delivery-entry-value"><span>Valor distribuído</span><strong>${hasFinancial ? money(item.gross) : 'Aguardando distribuição'}</strong></div></div><div class="delivery-details"><div class="delivery-detail"><span>Quantidade</span><strong>${qty(item.quantity)} ${esc(item.unit || '')}</strong></div><div class="delivery-detail"><span>Distribuído</span><strong>${qty(item.distributed_quantity)} ${esc(item.unit || '')}</strong></div><div class="delivery-detail"><span>${hasFinancial ? 'Líquido' : 'Financeiro'}</span><strong class="billing-value ${tone}">${hasFinancial ? money(item.net) : esc(item.billing_label)}</strong></div>${hasFinancial ? `<div class="delivery-detail"><span>Faturamento</span><strong class="billing-value ${tone}">${esc(item.billing_label)}</strong></div>` : ''}</div></div></article>`;
    }

    function setupDeliveryFilters() {
        const query = new URLSearchParams(location.search);
        ['status','project_id','start_date','end_date'].forEach(key => deliveryState[key] = query.get(key) || '');
        root.querySelectorAll('.delivery-status-tab').forEach(tab => tab.addEventListener('click', event => { event.preventDefault(); deliveryState.status = new URL(tab.href).searchParams.get('status') || ''; deliveryState.page = 1; root.querySelectorAll('.delivery-status-tab').forEach(item => item.classList.toggle('active', item === tab)); deliveries(1); }));
        const form = root.querySelector('.delivery-filter-form');
        form?.addEventListener('submit', event => { event.preventDefault(); deliveryState.project_id = form.project_id.value; deliveryState.start_date = form.start_date.value; deliveryState.end_date = form.end_date.value; deliveries(1); });
    }

    const ledgerState = {active:'receipts', pages:{receipts:1,payments:1,transactions:1}, start_date:'', end_date:''};
    async function ledger() {
        const panels = root.querySelectorAll('.ledger-panel');
        const summary = panels[0];
        summary.querySelectorAll('.ledger-summary-copy strong').forEach(node => node.classList.add('portal-loading-value'));
        [...panels].slice(1).forEach(panel => panel.remove());
        root.insertAdjacentHTML('beforeend', '<section class="ledger-panel" id="ledger-async-panel"><nav class="portal-tabs" aria-label="Seções do extrato"><button class="portal-tab active" data-ledger-tab="receipts"><i class="ph-duotone ph-receipt"></i>Comprovantes</button><button class="portal-tab" data-ledger-tab="payments"><i class="ph-duotone ph-hand-coins"></i>Pagamentos</button><button class="portal-tab" data-ledger-tab="transactions"><i class="ph-duotone ph-clock-counter-clockwise"></i>Movimentações</button></nav><div id="ledger-tab-content">'+skeleton(5)+'</div></section>');
        root.querySelectorAll('[data-ledger-tab]').forEach(tab => tab.addEventListener('click', () => { ledgerState.active = tab.dataset.ledgerTab; root.querySelectorAll('[data-ledger-tab]').forEach(item => item.classList.toggle('active', item === tab)); loadLedgerTab(); }));
        try {
            const data = await request('ledger-summary', config.urls.ledger.replace('__SECTION__','summary'));
            const values = [data.total_net, data.receivable, data.paid, data.total_fees];
            summary.querySelectorAll('.ledger-summary-copy strong').forEach((node,index) => { node.classList.remove('portal-loading-value'); node.textContent = money(values[index]); });
        } catch (error) {
            summary.querySelectorAll('.ledger-summary-copy strong').forEach(node => node.classList.remove('portal-loading-value'));
        }
        loadLedgerTab();
    }

    async function loadLedgerTab(page = null) {
        const section = ledgerState.active;
        if (page) ledgerState.pages[section] = page;
        const host = root.querySelector('#ledger-tab-content');
        host.innerHTML = skeleton(5);
        const params = {page:ledgerState.pages[section]};
        if (section === 'transactions') Object.assign(params, {start_date:ledgerState.start_date,end_date:ledgerState.end_date});
        try {
            const data = await request(`ledger-${section}`, config.urls.ledger.replace('__SECTION__',section), params);
            const toolbar = section === 'transactions' ? `<form class="portal-tab-toolbar" id="ledger-transaction-filter"><label>Data inicial<input type="date" name="start_date" value="${esc(ledgerState.start_date)}"></label><label>Data final<input type="date" name="end_date" value="${esc(ledgerState.end_date)}"></label><button type="submit"><i class="ph ph-funnel"></i>Aplicar filtro</button></form>` : '';
            const rows = (data.data || []).map(item => ledgerRow(section,item)).join('');
            host.innerHTML = `${toolbar}${rows ? `<div class="ledger-list">${rows}</div>` : '<div class="ledger-empty"><div><i class="ph-duotone ph-receipt-x"></i><strong>Nenhum registro encontrado</strong></div></div>'}${pages(data)}`;
            bindPages(host, loadLedgerTab);
            host.querySelector('#ledger-transaction-filter')?.addEventListener('submit', event => { event.preventDefault(); const form = event.currentTarget; ledgerState.start_date=form.start_date.value; ledgerState.end_date=form.end_date.value; ledgerState.pages.transactions=1; loadLedgerTab(); });
        } catch (error) {
            if (error.name === 'AbortError') return;
            host.innerHTML = errorHtml(error.message);
            host.querySelector('.portal-retry')?.addEventListener('click', () => loadLedgerTab());
        }
    }

    function ledgerRow(section,item) {
        if (section === 'receipts') return `<article class="ledger-row"><span class="ledger-row-icon receipt"><i class="ph-duotone ph-receipt"></i></span><div class="ledger-row-copy"><div class="ledger-row-title-line"><strong class="ledger-row-title">${esc(item.number)}</strong><span class="ledger-status ${statusClass(item.status)}">${esc(item.status_label)}</span></div><div class="ledger-row-meta"><span><i class="ph ph-folder"></i>${esc(item.project || 'Projeto')}</span><span><i class="ph ph-calendar-blank"></i>${esc(item.date || '-')}</span></div>${item.preview_url ? `<a class="ledger-receipt-link" href="${esc(item.preview_url)}" target="_blank" rel="noopener"><i class="ph ph-eye"></i>Visualizar comprovante</a>` : ''}</div><div class="ledger-row-values cols-3"><div class="ledger-data"><span>Líquido</span><strong>${money(item.net)}</strong></div><div class="ledger-data positive"><span>Pago</span><strong>${money(item.paid)}</strong></div><div class="ledger-data warning"><span>A receber</span><strong>${money(item.remaining)}</strong></div></div></article>`;
        if (section === 'payments') return `<article class="ledger-row"><span class="ledger-row-icon payment"><i class="ph-duotone ph-check-circle"></i></span><div class="ledger-row-copy"><div class="ledger-row-title-line"><strong class="ledger-row-title">Comprovante ${esc(item.receipt || '-')}</strong><span class="ledger-status paid">Pago</span></div><div class="ledger-row-meta"><span><i class="ph ph-folder"></i>${esc(item.project || 'Projeto')}</span><span><i class="ph ph-calendar-blank"></i>${esc(item.date || '-')}</span></div></div><div class="ledger-row-values cols-1"><div class="ledger-data positive"><span>Valor recebido</span><strong>${money(item.amount)}</strong></div></div></article>`;
        const credit = item.type === 'credit';
        return `<article class="ledger-row ledger-row-history"><span class="ledger-row-icon ${credit ? 'credit' : 'debit'}"><i class="ph-duotone ${credit ? 'ph-arrow-circle-down' : 'ph-arrow-circle-up'}"></i></span><div class="ledger-row-copy"><div class="ledger-row-title-line"><strong class="ledger-row-title">${esc(item.description || item.category)}</strong><span class="ledger-status ${credit ? 'paid' : 'cancelled'}">${esc(item.type_label)}</span></div><div class="ledger-row-meta"><span><i class="ph ph-calendar-blank"></i>${esc(item.date || '-')}</span><span><i class="ph ph-tag"></i>${esc(item.category || 'Movimentação')}</span></div></div><div class="ledger-row-values cols-2"><div class="ledger-data ${credit ? 'positive' : 'negative'}"><span>Valor</span><strong>${credit ? '+' : '-'} ${money(item.amount)}</strong></div><div class="ledger-data"><span>Saldo após</span><strong>${money(item.balance_after)}</strong></div></div></article>`;
    }

    function queryUrl(state) {
        const url = new URL(location.href);
        ['page','status','project_id','start_date','end_date'].forEach(key => url.searchParams.delete(key));
        Object.entries(state).forEach(([key,value]) => { if (value && key !== 'page') url.searchParams.set(key,value); });
        return url.pathname + url.search;
    }

    if (config.page === 'dashboard') dashboard();
    if (config.page === 'projects') { setupProjectFilters(); projects(projectState.page); }
    if (config.page === 'deliveries') { setupDeliveryFilters(); deliveries(); }
    if (config.page === 'ledger') ledger();
})();
