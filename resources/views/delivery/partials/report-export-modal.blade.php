@php
    $reportProjects = collect($reportProjects ?? []);
    $selectedReportProject = (int) ($selectedReportProject ?? 0);
@endphp

@once
<style>
    .dr-modal[hidden] { display: none !important; }
    .dr-modal { position: fixed; inset: 0; z-index: 100500; display: grid; place-items: center; padding: 1rem; background: rgba(15, 23, 42, .58); backdrop-filter: blur(4px); }
    .dr-panel { width: min(760px, 100%); max-height: min(88vh, 820px); overflow: auto; background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border); border-radius: 8px; box-shadow: 0 24px 70px rgba(0,0,0,.28); }
    .dr-head { position: sticky; top: 0; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.1rem; background: color-mix(in srgb, var(--color-surface) 94%, transparent); border-bottom: 1px solid var(--color-border); backdrop-filter: blur(10px); }
    .dr-head h2 { margin: 0; font-size: 1.05rem; letter-spacing: 0; }
    .dr-head p { margin: .15rem 0 0; font-size: .78rem; color: var(--color-text-secondary); }
    .dr-close { width: 38px; height: 38px; display: grid; place-items: center; border: 0; background: transparent; color: inherit; cursor: pointer; border-radius: 6px; }
    .dr-close:hover { background: var(--color-bg); }
    .dr-body { padding: 1rem 1.1rem; }
    .dr-label { display: block; margin-bottom: .35rem; font-size: .75rem; font-weight: 700; color: var(--color-text-secondary); }
    .dr-select, .dr-input { width: 100%; min-height: 42px; padding: .55rem .7rem; border: 1px solid var(--color-border); border-radius: 6px; background: var(--color-bg); color: var(--color-text); font: inherit; }
    .dr-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; margin-top: .85rem; }
    .dr-modes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .4rem; }
    .dr-choice { position: relative; }
    .dr-choice input { position: absolute; opacity: 0; pointer-events: none; }
    .dr-choice span { min-height: 44px; display: flex; align-items: center; justify-content: center; padding: .5rem; border: 1px solid var(--color-border); border-radius: 6px; font-size: .78rem; font-weight: 700; text-align: center; cursor: pointer; }
    .dr-choice input:checked + span { border-color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 11%, var(--color-surface)); color: var(--color-primary); }
    .dr-filters { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; margin-top: .85rem; }
    .dr-filter { min-width: 0; border: 1px solid var(--color-border); border-radius: 6px; overflow: hidden; }
    .dr-filter summary { display: flex; align-items: center; justify-content: space-between; padding: .65rem .7rem; cursor: pointer; font-size: .78rem; font-weight: 700; list-style: none; }
    .dr-filter summary::-webkit-details-marker { display: none; }
    .dr-filter-list { max-height: 210px; overflow: auto; padding: .35rem .55rem .55rem; border-top: 1px solid var(--color-border); }
    .dr-filter-search { width: 100%; margin-bottom: .35rem; padding: .45rem .5rem; border: 1px solid var(--color-border); border-radius: 5px; background: var(--color-bg); color: inherit; }
    .dr-option { display: flex; align-items: flex-start; gap: .45rem; padding: .38rem .15rem; font-size: .76rem; line-height: 1.25; cursor: pointer; }
    .dr-option input { width: 16px; height: 16px; flex: none; }
    .dr-empty, .dr-loading { padding: 1.5rem .5rem; text-align: center; color: var(--color-text-secondary); font-size: .82rem; }
    .dr-error { display: none; margin-top: .75rem; padding: .65rem .75rem; border-left: 3px solid var(--color-danger); background: color-mix(in srgb, var(--color-danger) 8%, transparent); color: var(--color-danger); font-size: .8rem; }
    .dr-layout { margin-top:.85rem; border:1px solid var(--color-border); border-radius:6px; overflow:hidden; }
    .dr-layout > summary { padding:.7rem; font-size:.8rem; font-weight:700; cursor:pointer; list-style:none; }
    .dr-layout > summary::-webkit-details-marker { display:none; }
    .dr-layout-body { padding:.75rem; border-top:1px solid var(--color-border); }
    .dr-columns { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.35rem .6rem; }
    .dr-column { display:flex; align-items:center; gap:.4rem; min-height:32px; font-size:.75rem; cursor:pointer; }
    .dr-column input { width:16px; height:16px; flex:none; }
    .dr-customer-grouping[hidden] { display:none; }
    .dr-actions { position: sticky; bottom: 0; display: flex; align-items: center; justify-content: flex-end; gap: .5rem; padding: .85rem 1.1rem; border-top: 1px solid var(--color-border); background: var(--color-surface); }
    .dr-btn { min-height: 40px; display: inline-flex; align-items: center; justify-content: center; gap: .4rem; padding: .55rem .85rem; border: 1px solid var(--color-border); border-radius: 6px; background: var(--color-bg); color: var(--color-text); font-weight: 700; cursor: pointer; }
    .dr-btn-primary { border-color: var(--color-primary); background: var(--color-primary); color: #fff; }
    .dr-btn:disabled { opacity: .55; cursor: wait; }
    @media (max-width: 680px) {
        .dr-modal { padding: 0; place-items: end stretch; }
        .dr-panel { width: 100%; max-height: 94vh; border-radius: 8px 8px 0 0; }
        .dr-grid, .dr-filters, .dr-columns { grid-template-columns: 1fr; }
        .dr-modes { grid-template-columns: 1fr; }
        .dr-actions { display: grid; grid-template-columns: 1fr 1fr; }
        .dr-actions .dr-btn:first-child { grid-column: 1 / -1; }
    }
</style>

<div class="dr-modal" id="delivery-report-modal" hidden aria-hidden="true">
    <section class="dr-panel" role="dialog" aria-modal="true" aria-labelledby="delivery-report-title">
        <header class="dr-head">
            <div><h2 id="delivery-report-title">Gerar relatório</h2><p>Escolha os dados e o formato de saída.</p></div>
            <button class="dr-close" type="button" data-dr-close aria-label="Fechar"><i data-lucide="x"></i></button>
        </header>
        <div class="dr-body">
            <label class="dr-label" for="dr-project">Projeto</label>
            <select class="dr-select" id="dr-project">
                <option value="">Selecione um projeto</option>
                @foreach($reportProjects as $projectId => $projectTitle)
                    <option value="{{ $projectId }}" @selected((int) $projectId === $selectedReportProject)>{{ $projectTitle }}</option>
                @endforeach
            </select>

            <div class="dr-grid">
                <div><label class="dr-label" for="dr-date-from">Data inicial</label><input class="dr-input" id="dr-date-from" type="date"></div>
                <div><label class="dr-label" for="dr-date-to">Data final</label><input class="dr-input" id="dr-date-to" type="date"></div>
            </div>

            <div style="margin-top:.85rem">
                <span class="dr-label">Organizar por</span>
                <div class="dr-modes">
                    <label class="dr-choice"><input type="radio" name="dr-type" value="associate" checked><span data-dr-member-label>Por membro</span></label>
                    <label class="dr-choice"><input type="radio" name="dr-type" value="product"><span>Por produto</span></label>
                    <label class="dr-choice"><input type="radio" name="dr-type" value="customer"><span>Por cliente</span></label>
                </div>
            </div>

            <div class="dr-loading" id="dr-loading">Selecione um projeto para carregar os filtros.</div>
            <div class="dr-filters" id="dr-filters" hidden>
                <details class="dr-filter"><summary><span data-dr-members-title>Membros</span><span data-dr-count="members">Todos</span></summary><div class="dr-filter-list"><input class="dr-filter-search" placeholder="Buscar" data-dr-search="members"><div data-dr-list="members"></div></div></details>
                <details class="dr-filter"><summary><span>Produtos</span><span data-dr-count="products">Todos</span></summary><div class="dr-filter-list"><input class="dr-filter-search" placeholder="Buscar" data-dr-search="products"><div data-dr-list="products"></div></div></details>
                <details class="dr-filter"><summary><span>Clientes</span><span data-dr-count="customers">Todos</span></summary><div class="dr-filter-list"><input class="dr-filter-search" placeholder="Buscar" data-dr-search="customers"><div data-dr-list="customers"></div></div></details>
            </div>
            <details class="dr-layout" id="dr-layout" hidden>
                <summary>Layout e colunas</summary>
                <div class="dr-layout-body">
                    <div class="dr-columns" id="dr-columns"></div>
                    <div class="dr-grid">
                        <div><label class="dr-label" for="dr-orientation">Orientação do PDF</label><select class="dr-select" id="dr-orientation"><option value="portrait">Retrato</option><option value="landscape">Paisagem</option></select></div>
                        <div><label class="dr-label" for="dr-scale">Escala da tabela</label><select class="dr-select" id="dr-scale"><option value="100">100%</option><option value="90">90%</option><option value="85">85%</option><option value="75">75%</option></select></div>
                    </div>
                    <div class="dr-customer-grouping" id="dr-customer-grouping" hidden style="margin-top:.75rem">
                        <label class="dr-label" for="dr-grouping">Agrupar distribuições do cliente</label>
                        <select class="dr-select" id="dr-grouping"><option value="product">Por data e produto</option><option value="associate">Por data, produto e membro</option><option value="none">Sem consolidar</option></select>
                    </div>
                </div>
            </details>
            <div class="dr-error" id="dr-error"></div>
        </div>
        <footer class="dr-actions">
            <button class="dr-btn" type="button" data-dr-close>Cancelar</button>
            <button class="dr-btn" type="button" data-dr-export="xlsx"><i data-lucide="sheet"></i> Excel</button>
            <button class="dr-btn dr-btn-primary" type="button" data-dr-export="pdf"><i data-lucide="file-text"></i> PDF</button>
        </footer>
    </section>
</div>

<script>
(() => {
    const tenant = @js($currentTenant->slug);
    const modal = document.getElementById('delivery-report-modal');
    const project = document.getElementById('dr-project');
    const loading = document.getElementById('dr-loading');
    const filters = document.getElementById('dr-filters');
    const layout = document.getElementById('dr-layout');
    const error = document.getElementById('dr-error');
    let controller = null;
    let optionData = null;

    const selected = key => [...modal.querySelectorAll(`[data-dr-list="${key}"] input:checked`)].map(input => input.value);
    const updateCount = key => {
        const count = selected(key).length;
        modal.querySelector(`[data-dr-count="${key}"]`).textContent = count ? String(count) : 'Todos';
    };
    const renderList = (key, items) => {
        const target = modal.querySelector(`[data-dr-list="${key}"]`);
        target.innerHTML = items.length ? items.map(item => `<label class="dr-option" data-dr-option><input type="checkbox" value="${Number(item.id)}"><span>${escapeHtml(item.name)}</span></label>`).join('') : '<div class="dr-empty">Nenhuma opção disponível.</div>';
        target.querySelectorAll('input').forEach(input => input.addEventListener('change', () => updateCount(key)));
        updateCount(key);
    };
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const showError = message => { error.textContent = message; error.style.display = 'block'; };
    const reportType = () => modal.querySelector('[name="dr-type"]:checked').value;
    function applyPreferences() {
        if (!optionData) return;
        const type = reportType();
        const preferences = optionData.report_preferences?.[type] || {};
        const selectedColumns = Array.isArray(preferences.columns) ? preferences.columns : [];
        document.getElementById('dr-columns').innerHTML = Object.entries(optionData.report_columns || {}).map(([value,label]) =>
            `<label class="dr-column"><input type="checkbox" value="${escapeHtml(value)}" ${selectedColumns.includes(value) ? 'checked' : ''}><span>${escapeHtml(value === 'associate' ? optionData.member_term : label)}</span></label>`
        ).join('');
        document.getElementById('dr-orientation').value = preferences.orientation || 'portrait';
        document.getElementById('dr-scale').value = String(preferences.table_scale || 90);
        document.getElementById('dr-grouping').value = preferences.grouping || 'product';
        document.getElementById('dr-customer-grouping').hidden = type !== 'customer';
    }

    async function loadOptions() {
        const id = Number(project.value || 0);
        controller?.abort();
        filters.hidden = true;
        layout.hidden = true;
        optionData = null;
        error.style.display = 'none';
        if (!id) { loading.textContent = 'Selecione um projeto para carregar os filtros.'; loading.hidden = false; return; }
        loading.textContent = 'Carregando filtros...'; loading.hidden = false;
        controller = new AbortController();
        try {
            const response = await fetch(`/${encodeURIComponent(tenant)}/delivery/projects/${id}/reports/options`, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, signal:controller.signal});
            if (!response.ok) throw new Error('Não foi possível carregar os filtros deste projeto.');
            const data = await response.json();
            optionData = data;
            renderList('members', data.members || []);
            renderList('products', data.products || []);
            renderList('customers', data.customers || []);
            document.querySelector('[data-dr-member-label]').textContent = `Por ${String(data.member_term || 'membro').toLowerCase()}`;
            document.querySelector('[data-dr-members-title]').textContent = data.member_term_plural || 'Membros';
            document.getElementById('dr-date-from').value = data.project?.start_date || '';
            document.getElementById('dr-date-to').value = data.project?.end_date || '';
            loading.hidden = true;
            filters.hidden = false;
            layout.hidden = false;
            applyPreferences();
        } catch (exception) {
            if (exception.name === 'AbortError') return;
            loading.hidden = true;
            showError(exception.message);
        }
    }

    function close() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    function open() {
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        loadOptions();
        window.setTimeout(() => project.focus(), 20);
        if (window.lucide) window.lucide.createIcons();
    }
    async function exportReport(format) {
        const id = Number(project.value || 0);
        if (!id) { showError('Selecione um projeto.'); return; }
        error.style.display = 'none';
        const type = reportType();
        const columns = [...document.querySelectorAll('#dr-columns input:checked')].map(input => input.value);
        if (!columns.length) { showError('Selecione ao menos uma coluna.'); return; }
        const preferencePayload = {
            type,
            columns,
            orientation:document.getElementById('dr-orientation').value,
            table_scale:Number(document.getElementById('dr-scale').value),
            grouping:type === 'customer' ? document.getElementById('dr-grouping').value : 'delivery',
        };
        const params = new URLSearchParams({format, type});
        const from = document.getElementById('dr-date-from').value;
        const to = document.getElementById('dr-date-to').value;
        if (from) params.set('date_from', from);
        if (to) params.set('date_to', to);
        [['members','associate_ids'],['products','product_ids'],['customers','customer_ids']].forEach(([key,name]) => selected(key).forEach(value => params.append(`${name}[]`, value)));
        const buttons = [...modal.querySelectorAll('[data-dr-export]')];
        buttons.forEach(button => { button.disabled = true; });
        modal.querySelector('.dr-panel').setAttribute('aria-busy', 'true');
        const preview = format === 'pdf' ? window.open('about:blank', '_blank') : null;
        if (preview) preview.opener = null;
        try {
            const preferenceResponse = await fetch(`/${encodeURIComponent(tenant)}/delivery/projects/${id}/reports/preferences`, {
                method:'PUT',
                headers:{Accept:'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':@js(csrf_token())},
                body:JSON.stringify(preferencePayload),
            });
            if (!preferenceResponse.ok) {
                const payload = await preferenceResponse.json().catch(() => ({}));
                throw new Error(payload.message || 'Não foi possível salvar a configuração do relatório.');
            }
            const response = await fetch(`/${encodeURIComponent(tenant)}/delivery/projects/${id}/reports/export?${params}`, {
                headers: {Accept: format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'X-Requested-With':'XMLHttpRequest'},
            });
            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.message || 'Não foi possível gerar o relatório com estes filtros.');
            }
            const blobUrl = URL.createObjectURL(await response.blob());
            if (preview) {
                preview.location.replace(blobUrl);
            } else {
                const disposition = response.headers.get('Content-Disposition') || '';
                const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
                const fallbackName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
                const link = document.createElement('a');
                link.href = blobUrl;
                link.download = encodedName ? decodeURIComponent(encodedName) : (fallbackName || `relatorio.${format}`);
                document.body.appendChild(link);
                link.click();
                link.remove();
            }
            window.setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
        } catch (exception) {
            preview?.close();
            showError(exception.message || 'Não foi possível gerar o relatório.');
        } finally {
            buttons.forEach(button => { button.disabled = false; });
            modal.querySelector('.dr-panel').removeAttribute('aria-busy');
        }
    }

    project.addEventListener('change', loadOptions);
    modal.querySelectorAll('[name="dr-type"]').forEach(input => input.addEventListener('change', applyPreferences));
    modal.querySelectorAll('[data-dr-close]').forEach(button => button.addEventListener('click', close));
    modal.querySelectorAll('[data-dr-export]').forEach(button => button.addEventListener('click', () => exportReport(button.dataset.drExport)));
    modal.querySelectorAll('[data-dr-search]').forEach(input => input.addEventListener('input', () => {
        const query = input.value.trim().toLocaleLowerCase('pt-BR');
        modal.querySelectorAll(`[data-dr-list="${input.dataset.drSearch}"] [data-dr-option]`).forEach(option => { option.hidden = query !== '' && !option.textContent.toLocaleLowerCase('pt-BR').includes(query); });
    }));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) close(); });
    window.DeliveryReports = {open, close};
})();
</script>
@endonce
