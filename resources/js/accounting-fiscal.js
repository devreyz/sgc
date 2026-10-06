(() => {
    const root = document.querySelector('[data-fiscal-queue]');
    if (!root) return;
    const form = root.querySelector('[data-fiscal-filters]');
    const body = root.querySelector('[data-fiscal-table]');
    const mobile = root.querySelector('[data-fiscal-mobile]');
    const pager = root.querySelector('[data-fiscal-pagination]');
    const esc = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
    const money = new Intl.NumberFormat('pt-BR', {style:'currency', currency:'BRL'});
    let filtersLoaded = false;
    let controller;

    const action = process => process.gate === 'ready'
        ? `<a class="acc-link" href="${esc(process.billing_sheet_url)}" target="_blank" rel="noopener">Imprimir faturamento</a>`
        : `<a class="acc-link" href="${esc(process.review_url)}">Revisar faturamento</a>`;

    const load = async (page = 1) => {
        controller?.abort();
        controller = new AbortController();
        body.innerHTML = '<tr><td colspan="7"><div class="acc-empty">Carregando...</div></td></tr>';
        mobile.innerHTML = '';
        const parameters = new URLSearchParams(new FormData(form));
        parameters.set('page', page);
        try {
            const response = await fetch(`${root.dataset.url}?${parameters}`, {headers:{Accept:'application/json'}, signal:controller.signal, credentials:'same-origin'});
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Falha ao carregar a fila.');
            if (!filtersLoaded) {
                form.elements.project.innerHTML = '<option value="">Todos</option>' + payload.filters.projects.map(item => `<option value="${item.id}">${esc(item.label)}</option>`).join('');
                form.elements.organization.innerHTML = '<option value="">Todas</option>' + payload.filters.organizations.map(item => `<option value="${item.id}">${esc(item.label)}</option>`).join('');
                filtersLoaded = true;
            }
            const rows = payload.processes.data;
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="7"><div class="acc-empty">Nenhum faturamento encontrado.</div></td></tr>';
                mobile.innerHTML = '<div class="acc-empty">Nenhum faturamento encontrado.</div>';
            } else {
                body.innerHTML = rows.map(item => `<tr><td><a class="acc-link" href="${esc(item.review_url)}">${esc(item.number)}</a></td><td>${esc(item.recipient)}</td><td>${esc(item.project)}</td><td>${esc(item.authorized_at || 'Não exigida')}</td><td class="acc-money">${money.format(item.amount)}</td><td><span class="acc-badge acc-badge-${item.gate === 'ready' ? 'success' : 'warning'}">${esc(item.label)}</span></td><td>${action(item)}</td></tr>`).join('');
                mobile.innerHTML = rows.map(item => `<article class="acc-mobile-row"><div class="acc-mobile-head"><a class="acc-link" href="${esc(item.review_url)}">${esc(item.number)}</a><span class="acc-badge acc-badge-${item.gate === 'ready' ? 'success' : 'warning'}">${esc(item.label)}</span></div><div class="acc-mobile-meta"><span>Destinatário<strong>${esc(item.recipient)}</strong></span><span>Projeto<strong>${esc(item.project)}</strong></span><span>Valor para emissão<strong>${money.format(item.amount)}</strong></span><span>Próxima ação<strong>${esc(item.action)}</strong></span></div><div class="acc-mobile-action">${action(item)}</div></article>`).join('');
            }
            pager.innerHTML = `<span>${payload.processes.from || 0}–${payload.processes.to || 0} de ${payload.processes.total}</span><div class="acc-pagination-actions"><button class="acc-button" ${payload.processes.current_page <= 1 ? 'disabled' : ''} data-page="${payload.processes.current_page - 1}">Anterior</button><button class="acc-button" ${payload.processes.current_page >= payload.processes.last_page ? 'disabled' : ''} data-page="${payload.processes.current_page + 1}">Próxima</button></div>`;
            pager.querySelectorAll('[data-page]').forEach(button => button.onclick = () => load(button.dataset.page));
        } catch (error) {
            if (error.name !== 'AbortError') body.innerHTML = `<tr><td colspan="7"><div class="acc-error">${esc(error.message)}</div></td></tr>`;
        }
    };

    let timer;
    form.oninput = event => { clearTimeout(timer); timer = setTimeout(() => load(1), event.target.name === 'search' ? 300 : 0); };
    root.querySelector('[data-clear]').onclick = () => { form.reset(); load(1); };
    load();
})();
