@extends('layouts.bento')

@section('title', 'Limites do associado')
@section('page-title', 'Limites do associado')
@section('user-role', 'Gestao de entregas')

@php
    $tenantSlug = request()->route('tenant')->slug ?? request()->route('tenant');
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', $tenantSlug);
@endphp

@section('content')
<style>
    .aql { grid-column:1/-1; display:grid; gap:.8rem; min-width:0; }
    .aql-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.7rem; flex-wrap:wrap; }
    .aql-back { display:inline-flex; align-items:center; gap:.35rem; color:var(--color-text-secondary); text-decoration:none; font-size:.72rem; font-weight:750; }
    .aql-head h1 { margin:.35rem 0 .12rem; font-size:1.12rem; }
    .aql-head p { margin:0; color:var(--color-text-secondary); font-size:.74rem; }
    .aql-btn { min-height:40px; display:inline-flex; align-items:center; justify-content:center; gap:.35rem; padding:.5rem .7rem; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:var(--color-text); font:inherit; font-size:.74rem; font-weight:800; cursor:pointer; text-decoration:none; }
    .aql-btn.primary { border-color:var(--color-primary); background:var(--color-primary); color:#fff; }
    .aql-btn.danger { border-color:#fecaca; color:#b91c1c; }
    .aql-btn:disabled { opacity:.62; cursor:not-allowed; }
    .aql-summary { position:sticky; z-index:20; top:calc(var(--app-header-height) + .2rem); display:grid; grid-template-columns:minmax(0,1.25fr) minmax(230px,.75fr); gap:.65rem; padding:.65rem; border:1px solid var(--color-border); border-radius:8px; background:color-mix(in srgb,var(--color-surface) 96%,transparent); box-shadow:0 6px 18px rgba(15,23,42,.08); backdrop-filter:blur(12px); }
    .aql-budget,.aql-financial { padding:.65rem; border-radius:7px; background:var(--color-bg); }
    .aql-budget-head { display:flex; align-items:flex-start; justify-content:space-between; gap:.65rem; }
    .aql-label { color:var(--color-text-secondary); font-size:.68rem; font-weight:700; }
    .aql-budget strong { display:block; margin-top:.12rem; font-size:1rem; }
    .aql-budget-status { text-align:right; }
    .aql-budget-status strong { font-size:.82rem; }
    .aql-meter { height:9px; margin-top:.5rem; overflow:hidden; border-radius:999px; background:var(--color-surface); }
    .aql-meter span { display:block; height:100%; border-radius:inherit; background:var(--color-primary); transition:width .18s ease; }
    .aql-meter.warning span { background:#d97706; }
    .aql-meter.danger span { background:#dc2626; }
    .aql-financial-row { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.45rem; align-items:end; }
    .aql-control { width:100%; min-height:42px; padding:.52rem .62rem; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:var(--color-text); font:inherit; font-size:.86rem; }
    .aql-tools { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.55rem; }
    .aql-picker { display:grid; max-height:270px; gap:.35rem; padding:.45rem; overflow:auto; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .aql-option { display:flex; align-items:center; justify-content:space-between; gap:.7rem; min-height:58px; padding:.65rem; border:1px solid var(--color-border); border-radius:7px; background:var(--color-surface); color:var(--color-text); text-align:left; cursor:pointer; }
    .aql-option strong { display:block; font-size:.86rem; }
    .aql-option span { display:block; margin-top:.12rem; color:var(--color-text-secondary); font-size:.7rem; }
    .aql-option:disabled { opacity:.56; cursor:not-allowed; }
    .aql-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:.65rem; }
    .aql-card { padding:.75rem; border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); }
    .aql-card.editing { border-color:var(--color-primary); box-shadow:inset 3px 0 var(--color-primary); }
    .aql-card.invalid { border-color:#dc2626; box-shadow:inset 3px 0 #dc2626; }
    .aql-card-head,.aql-actions,.aql-values { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
    .aql-card h2 { margin:0; font-size:.92rem; line-height:1.3; }
    .aql-sub { margin-top:.14rem; color:var(--color-text-secondary); font-size:.72rem; }
    .aql-actions { flex:none; }
    .aql-actions .aql-btn { min-width:38px; padding:.45rem; }
    .aql-values { margin-top:.65rem; padding:.55rem; border-radius:7px; background:var(--color-bg); }
    .aql-value span { display:block; color:var(--color-text-secondary); font-size:.65rem; }
    .aql-value strong { display:block; margin-top:.12rem; font-size:.78rem; }
    .aql-use { display:flex; justify-content:space-between; gap:.5rem; margin-top:.55rem; color:var(--color-text-secondary); font-size:.67rem; font-weight:700; }
    .aql-edit { display:grid; grid-template-columns:minmax(0,1fr) 145px; gap:.65rem; align-items:end; margin-top:.6rem; }
    .aql-edit label { display:grid; gap:.25rem; color:var(--color-text-secondary); font-size:.68rem; font-weight:750; }
    .aql-slider { width:100%; min-height:38px; accent-color:var(--color-primary); touch-action:pan-y; }
    .aql-slider:disabled,.aql-control:disabled { opacity:.78; cursor:not-allowed; }
    .aql-message { min-height:1.15rem; margin-top:.42rem; color:var(--color-text-secondary); font-size:.69rem; line-height:1.4; }
    .aql-message.error { color:#b91c1c; font-weight:750; }
    .aql-empty,.aql-loading { min-height:180px; display:grid; place-items:center; padding:1rem; border:1px dashed var(--color-border); border-radius:8px; color:var(--color-text-secondary); text-align:center; font-size:.75rem; }
    .aql-footer { position:sticky; z-index:19; bottom:calc(var(--app-bottom-nav-height,0px) + .5rem); display:flex; align-items:center; justify-content:space-between; gap:.7rem; padding:.65rem; border:1px solid var(--color-border); border-radius:8px; background:color-mix(in srgb,var(--color-surface) 96%,transparent); box-shadow:0 -5px 18px rgba(15,23,42,.08); backdrop-filter:blur(12px); }
    .aql-feedback { color:var(--color-text-secondary); font-size:.72rem; font-weight:700; }
    .aql-feedback.error { color:#b91c1c; }
    .aql-dialog { width:min(430px,calc(100vw - 1rem)); border:1px solid var(--color-border); border-radius:8px; background:var(--color-surface); color:var(--color-text); padding:0; }
    .aql-dialog::backdrop { background:rgba(15,23,42,.5); backdrop-filter:blur(3px); }
    .aql-dialog-body { padding:1rem; }
    .aql-dialog h3 { margin:0 0 .4rem; font-size:.95rem; }
    .aql-dialog p { margin:0; color:var(--color-text-secondary); font-size:.78rem; line-height:1.5; }
    .aql-dialog-actions { display:flex; justify-content:flex-end; gap:.45rem; padding:.75rem 1rem; border-top:1px solid var(--color-border); }
    @media (max-width:720px) {
        .aql-summary { position:relative; top:auto; grid-template-columns:1fr; }
        .aql-tools,.aql-edit { grid-template-columns:1fr; }
        .aql-grid { grid-template-columns:1fr; }
        .aql-values { flex-wrap:wrap; }
        .aql-footer { bottom:calc(4.8rem + env(safe-area-inset-bottom)); }
        .aql-footer .aql-btn { min-width:135px; }
    }
</style>

<main
    class="aql"
    id="aql-app"
    data-limits-url="{{ route('delivery.projects.associates.data', ['tenant' => $tenantSlug, 'project' => $project->id, 'associate' => $associate->id, 'section' => 'limits']) }}"
    data-products-url="{{ route('delivery.projects.associates.data', ['tenant' => $tenantSlug, 'project' => $project->id, 'associate' => $associate->id, 'section' => 'products']) }}"
    data-financial-url="{{ route('delivery.projects.associates.limits.financial', ['tenant' => $tenantSlug, 'project' => $project->id, 'associate' => $associate->id]) }}"
    data-can-manage="{{ $canManageLimits ? '1' : '0' }}"
>
    <header class="aql-head">
        <div>
            <a class="aql-back" href="{{ route('delivery.projects.associates.show', ['tenant' => $tenantSlug, 'project' => $project->id, 'associate' => $associate->id]) }}">
                <i data-lucide="arrow-left"></i> Voltar ao associado
            </a>
            <h1>{{ $associate->display_name }}</h1>
            <p>{{ $project->title }} · Produtos permitidos e limites de entrega</p>
        </div>
        <a class="aql-btn" href="{{ route('delivery.projects.product-limits.index', ['tenant' => $tenantSlug, 'project' => $project->id]) }}">
            <i data-lucide="boxes"></i> Ver por produto
        </a>
    </header>

    <section class="aql-summary">
        <div class="aql-budget">
            <div class="aql-budget-head">
                <div><span class="aql-label">Valor planejado nas cotas</span><strong id="aql-planned">R$ 0,00</strong></div>
                <div class="aql-budget-status"><span class="aql-label">Disponível</span><strong id="aql-remaining">—</strong></div>
            </div>
            <div class="aql-meter" id="aql-budget-meter"><span></span></div>
        </div>
        <div class="aql-financial">
            <div class="aql-financial-row">
                <label>
                    <span class="aql-label">Teto financeiro do associado</span>
                    <input class="aql-control" id="aql-financial-input" type="number" min="0" step="0.01" {{ $canManageLimits ? '' : 'disabled' }}>
                </label>
                @if($canManageLimits)
                    <button class="aql-btn" id="aql-financial-save" type="button"><i data-lucide="save"></i> Salvar</button>
                @endif
            </div>
        </div>
    </section>

    @if($canManageLimits)
        <section class="aql-tools">
            <input class="aql-control" id="aql-search" type="search" placeholder="Buscar produto para adicionar" autocomplete="off">
            <button class="aql-btn primary" id="aql-toggle-products" type="button"><i data-lucide="package-plus"></i> Adicionar produto</button>
        </section>
        <section class="aql-picker" id="aql-picker" hidden></section>
    @endif

    <section class="aql-grid" id="aql-grid"><div class="aql-loading">Carregando limites...</div></section>

    @if($canManageLimits)
        <footer class="aql-footer">
            <span class="aql-feedback" id="aql-feedback">Nenhuma alteração pendente.</span>
            <button class="aql-btn primary" id="aql-save-all" type="button" disabled><i data-lucide="save"></i> Salvar alterações</button>
        </footer>
    @endif
</main>

<dialog class="aql-dialog" id="aql-remove-dialog">
    <div class="aql-dialog-body">
        <h3>Remover limite do produto</h3>
        <p id="aql-remove-message"></p>
    </div>
    <div class="aql-dialog-actions">
        <button class="aql-btn" type="button" id="aql-remove-cancel">Cancelar</button>
        <button class="aql-btn danger" type="button" id="aql-remove-confirm">Remover limite</button>
    </div>
</dialog>

<script>
(() => {
    const root = document.getElementById('aql-app');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const canManage = root.dataset.canManage === '1';
    const state = { products: [], rows: new Map(), originals: new Map(), summary: {}, batchUrl: null, editing: null, busy: false, removal: null };
    const money = value => Number(value || 0).toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
    const qty = value => Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits:3 });
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const icons = () => window.lucide?.createIcons();
    const json = async (url, options = {}) => {
        const response = await fetch(url, { ...options, headers:{ Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':csrf, ...(options.headers || {}) } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Não foi possível concluir a operação.');
        return data;
    };
    const rowFrom = (product, current = null) => {
        const delivered = Number(current?.delivered_quantity ?? product.delivered_quantity ?? 0);
        return {
            id:Number(product.id), name:product.name || current?.product || 'Produto', unit:product.unit || current?.unit || '',
            price:Number(product.price ?? current?.reference_unit_price ?? 0), delivered,
            quantity:Number(current?.maximum_quantity ?? Math.max(delivered, .001)),
            productMaximum:product.available_for_associate === null ? null : Number(product.available_for_associate),
            projectMaximum:product.project_maximum === null ? null : Number(product.project_maximum),
            allocatedOthers:Number(product.allocated_to_others || 0), deleteUrl:current?.delete_url || null, isNew:!current,
        };
    };
    const planned = exceptId => [...state.rows.values()].reduce((sum, row) => {
        const quantity = Number.isFinite(row.quantity) ? row.quantity : 0;
        return sum + (row.id === exceptId ? 0 : quantity * row.price);
    }, 0);
    const ceiling = () => state.summary.financial_limit === null ? null : Number(state.summary.financial_limit || 0);
    const allowedMaximum = row => {
        const productMax = row.productMaximum === null ? Infinity : row.productMaximum;
        const budgetMax = ceiling() === null || row.price <= 0 ? Infinity : Math.max(0, (ceiling() - planned(row.id)) / row.price);
        return Math.max(row.delivered, Math.min(productMax, budgetMax));
    };
    const sliderMaximum = row => Number.isFinite(allowedMaximum(row))
        ? allowedMaximum(row)
        : Math.max(100, row.delivered, row.quantity, Math.ceil(row.quantity * 1.5));
    const validation = row => {
        if (!Number.isFinite(row.quantity) || row.quantity <= 0) return 'Informe uma cota maior que zero.';
        if (row.quantity < row.delivered - .000001) return `Mínimo de ${qty(row.delivered)} ${row.unit}, pois esta quantidade já foi entregue.`;
        if (row.quantity > allowedMaximum(row) + .000001) return `Limite disponível: ${qty(allowedMaximum(row))} ${row.unit}.`;
        return '';
    };
    const changed = () => state.rows.size !== state.originals.size || [...state.rows].some(([key,row]) => !state.originals.has(key) || !Number.isFinite(row.quantity) || Math.abs(row.quantity - state.originals.get(key)) > .000001);
    function hydrate(limits, products) {
        state.products = products; state.summary = limits.summary || {}; state.batchUrl = limits.batch_update_url;
        state.rows = new Map(); state.originals = new Map(); state.editing = null;
        const current = new Map((limits.products || []).map(item => [String(item.product_id), item]));
        products.forEach(product => {
            const item = current.get(String(product.id)); if (!item) return;
            const row = rowFrom(product, item); state.rows.set(String(row.id), row); state.originals.set(String(row.id), row.quantity);
        });
        document.getElementById('aql-financial-input').value = state.summary.financial_limit ?? '';
    }
    function render() {
        const rows = [...state.rows.values()].sort((a,b) => a.name.localeCompare(b.name, 'pt-BR'));
        document.getElementById('aql-grid').innerHTML = rows.length ? rows.map(card).join('') : '<div class="aql-empty">Nenhum produto configurado para este associado.</div>';
        renderPicker(); refresh(); icons();
    }
    function card(row) {
        const editing = state.editing === String(row.id);
        const used = row.quantity > 0 ? Math.min(100, row.delivered / row.quantity * 100) : 0;
        return `<article class="aql-card ${editing ? 'editing' : ''}" id="produto-${row.id}">
            <div class="aql-card-head"><div><h2>${esc(row.name)}</h2><div class="aql-sub">${money(row.price)} por ${esc(row.unit || 'unidade')}</div></div>
            ${canManage ? `<div class="aql-actions"><button class="aql-btn" type="button" onclick="aqlEdit(${row.id})" title="Editar cota"><i data-lucide="${editing ? 'lock-open' : 'pencil'}"></i></button><button class="aql-btn danger" type="button" onclick="aqlRemove(${row.id})" title="Remover limite"><i data-lucide="trash-2"></i></button></div>` : ''}</div>
            <div class="aql-values"><div class="aql-value"><span>Entregue</span><strong>${qty(row.delivered)} ${esc(row.unit)}</strong></div><div class="aql-value"><span>Cota</span><strong id="aql-quota-${row.id}">${qty(row.quantity)} ${esc(row.unit)}</strong></div><div class="aql-value"><span>Saldo</span><strong id="aql-balance-${row.id}">${qty(Math.max(0,row.quantity-row.delivered))} ${esc(row.unit)}</strong></div><div class="aql-value"><span>Valor</span><strong id="aql-value-${row.id}">${money(row.quantity*row.price)}</strong></div></div>
            <div class="aql-use"><span>Uso da cota</span><span id="aql-use-${row.id}">${Math.round(used)}% entregue</span></div><div class="aql-meter" id="aql-use-meter-${row.id}"><span style="width:${used}%"></span></div>
            ${canManage ? `<div class="aql-edit"><label>Ajustar deslizando<input class="aql-slider" id="aql-slider-${row.id}" type="range" min="${row.delivered}" max="${sliderMaximum(row)}" step=".001" value="${row.quantity}" ${editing ? '' : 'disabled'} oninput="aqlQuantity(${row.id},this.value,'slider')"></label><label>Cota máxima (${esc(row.unit)})<input class="aql-control" id="aql-input-${row.id}" type="number" min="${row.delivered}" ${Number.isFinite(allowedMaximum(row)) ? `max="${allowedMaximum(row)}"` : ''} step=".001" value="${row.quantity}" ${editing ? '' : 'disabled'} oninput="aqlQuantity(${row.id},this.value,'input')" onblur="aqlCommitQuantity(${row.id})"></label></div>` : ''}
            <div class="aql-message" id="aql-message-${row.id}">${availability(row)}</div></article>`;
    }
    const availability = row => row.projectMaximum === null
        ? (Number.isFinite(allowedMaximum(row))
            ? `Sem meta de quantidade no projeto. Máximo atual pelo teto financeiro: ${qty(allowedMaximum(row))} ${row.unit}.`
            : 'Sem meta de quantidade nem teto financeiro para este produto.')
        : `Meta: ${qty(row.projectMaximum)} ${row.unit} · demais associados: ${qty(row.allocatedOthers)} · máximo atual: ${qty(allowedMaximum(row))}.`;
    function refresh(note = '') {
        let invalid = false;
        state.rows.forEach(row => {
            const error = validation(row); invalid ||= Boolean(error);
            const max = allowedMaximum(row); const used = row.quantity > 0 ? Math.min(100,row.delivered/row.quantity*100) : 0;
            const cardEl = document.getElementById(`produto-${row.id}`); cardEl?.classList.toggle('invalid', Boolean(error));
            const input = document.getElementById(`aql-input-${row.id}`); const slider = document.getElementById(`aql-slider-${row.id}`);
            if (input) {
                if (Number.isFinite(max)) input.max = String(max); else input.removeAttribute('max');
            }
            if (slider) {
                slider.max = String(sliderMaximum(row));
                if (Number.isFinite(row.quantity)) slider.value = String(Math.min(row.quantity, sliderMaximum(row)));
            }
            const set = (id,text) => { const el=document.getElementById(id); if(el) el.textContent=text; };
            set(`aql-quota-${row.id}`,`${qty(row.quantity)} ${row.unit}`); set(`aql-balance-${row.id}`,`${qty(Math.max(0,row.quantity-row.delivered))} ${row.unit}`);
            set(`aql-value-${row.id}`,money(row.quantity*row.price)); set(`aql-use-${row.id}`,`${Math.round(used)}% entregue`);
            const meter=document.querySelector(`#aql-use-meter-${row.id} span`); if(meter) meter.style.width=`${used}%`;
            const message=document.getElementById(`aql-message-${row.id}`); if(message){message.textContent=error || availability(row);message.classList.toggle('error',Boolean(error));}
        });
        const total=planned(null); const limit=ceiling(); const percent=limit && limit>0 ? total/limit*100 : 0;
        document.getElementById('aql-planned').textContent=money(total);
        document.getElementById('aql-remaining').textContent=limit===null?'Sem teto':money(Math.max(0,limit-total));
        const budgetMeter=document.getElementById('aql-budget-meter'); budgetMeter.classList.toggle('warning',percent>=80&&percent<100);budgetMeter.classList.toggle('danger',percent>=100);
        budgetMeter.querySelector('span').style.width=`${Math.min(100,percent)}%`;
        const save=document.getElementById('aql-save-all'); if(save) save.disabled=state.busy||!changed()||invalid||total>(limit??Infinity)+.005;
        const feedback=document.getElementById('aql-feedback'); if(feedback){feedback.textContent=note || (changed()?'Alterações ainda não salvas.':'Nenhuma alteração pendente.');feedback.classList.toggle('error',invalid);}
    }
    function renderPicker() {
        const picker=document.getElementById('aql-picker'); if(!picker) return;
        const term=(document.getElementById('aql-search')?.value||'').trim().toLocaleLowerCase('pt-BR');
        const products=state.products.filter(product=>!state.rows.has(String(product.id))&&(!term||String(product.name).toLocaleLowerCase('pt-BR').includes(term)));
        picker.innerHTML=products.length?products.slice(0,80).map(product=>{
            const preview=rowFrom(product); const max=allowedMaximum(preview); const unavailable=max<Math.max(preview.delivered,.001)-.000001;
            return `<button class="aql-option" type="button" ${unavailable?'disabled':''} onclick="aqlAdd(${Number(product.id)})"><div><strong>${esc(product.name)}</strong><span>${money(product.price)} por ${esc(product.unit||'unidade')}</span></div><span>${unavailable?'Sem saldo':Number.isFinite(max)?qty(max)+' disponível':'Sem limite'}</span></button>`;
        }).join(''):'<div class="aql-empty">Nenhum produto disponível para esta busca.</div>';
    }
    window.aqlEdit=id=>{state.editing=String(id);render();document.getElementById(`aql-input-${id}`)?.focus();};
    window.aqlQuantity=(id,value,source)=>{
        const row=state.rows.get(String(id)); if(!row)return;
        if(source==='input') {
            row.quantity=value===''?NaN:Number(String(value).replace(',','.'));
            refresh();
            return;
        }
        const parsed=Number(String(value).replace(',','.'));if(!Number.isFinite(parsed))return;
        const max=allowedMaximum(row);row.quantity=Math.max(row.delivered,Math.min(parsed,max));
        const input=document.getElementById(`aql-input-${id}`);if(input)input.value=String(row.quantity);
        const slider=document.getElementById(`aql-slider-${id}`);if(slider)slider.value=String(row.quantity);
        refresh(parsed>max+.000001?'Cota limitada pelo saldo financeiro ou pela meta do produto.':'');
    };
    window.aqlCommitQuantity=id=>{
        const row=state.rows.get(String(id)); if(!row||!Number.isFinite(row.quantity))return;
        const max=allowedMaximum(row);
        row.quantity=Math.max(row.delivered,Math.min(row.quantity,max));
        const input=document.getElementById(`aql-input-${id}`);if(input)input.value=String(row.quantity);
        const slider=document.getElementById(`aql-slider-${id}`);if(slider)slider.value=String(row.quantity);
        refresh();
    };
    window.aqlAdd=id=>{const product=state.products.find(item=>String(item.id)===String(id));if(!product)return;const row=rowFrom(product);const max=allowedMaximum(row);if(max<row.quantity-.000001)return;state.rows.set(String(id),row);state.editing=String(id);document.getElementById('aql-picker').hidden=true;render();document.getElementById(`aql-input-${id}`)?.focus();};
    window.aqlRemove=id=>{const row=state.rows.get(String(id));if(!row)return;if(row.isNew){state.rows.delete(String(id));render();return;}state.removal=row;document.getElementById('aql-remove-message').textContent=`A definição de ${row.name} será removida. Entregas já registradas serão preservadas.`;document.getElementById('aql-remove-dialog').showModal();};
    document.getElementById('aql-remove-cancel').addEventListener('click',()=>document.getElementById('aql-remove-dialog').close());
    document.getElementById('aql-remove-confirm').addEventListener('click',async()=>{const row=state.removal;if(!row)return;const button=document.getElementById('aql-remove-confirm');try{button.disabled=true;const response=await json(row.deleteUrl,{method:'DELETE',body:'{}'});document.getElementById('aql-remove-dialog').close();hydrate(response.data,state.products);render();}catch(error){refresh(error.message);}finally{button.disabled=false;}});
    document.getElementById('aql-toggle-products')?.addEventListener('click',()=>{const picker=document.getElementById('aql-picker');picker.hidden=!picker.hidden;if(!picker.hidden)document.getElementById('aql-search').focus();});
    document.getElementById('aql-search')?.addEventListener('input',renderPicker);
    document.getElementById('aql-save-all')?.addEventListener('click',async event=>{
        const changes=[...state.rows].filter(([key,row])=>!state.originals.has(key)||Math.abs(row.quantity-state.originals.get(key))>.000001).map(([,row])=>({product_id:row.id,max_quantity:Number(row.quantity.toFixed(3))}));
        if(!changes.length)return;
        let feedback='Alterações salvas.';
        try{
            state.busy=true;refresh('Salvando alterações...');
            const response=await json(state.batchUrl,{method:'PUT',body:JSON.stringify({limits:changes})});
            hydrate(response,state.products);render();
        }catch(error){
            feedback=error.message;
        }finally{
            state.busy=false;refresh(feedback);
            document.getElementById('aql-feedback').classList.toggle('error',feedback!=='Alterações salvas.');
        }
    });
    document.getElementById('aql-financial-save')?.addEventListener('click',async event=>{const button=event.currentTarget;try{button.disabled=true;const value=document.getElementById('aql-financial-input').value;await json(root.dataset.financialUrl,{method:'PUT',body:JSON.stringify({financial_limit:value===''?null:value})});state.summary.financial_limit=value===''?null:Number(value);refresh('Teto financeiro atualizado.');}catch(error){refresh(error.message);document.getElementById('aql-feedback').classList.add('error');}finally{button.disabled=false;}});
    Promise.all([json(root.dataset.limitsUrl),json(root.dataset.productsUrl)]).then(([limits,products])=>{
        hydrate(limits,products.data||[]);render();
        const productId=window.location.hash.match(/^#produto-(\d+)$/)?.[1];
        if(productId&&state.rows.has(String(productId))){
            if(canManage)window.aqlEdit(Number(productId));
            document.getElementById(`produto-${productId}`)?.scrollIntoView({behavior:'smooth',block:'center'});
        }
    }).catch(error=>{document.getElementById('aql-grid').innerHTML=`<div class="aql-empty">${esc(error.message)}</div>`;});
    window.addEventListener('beforeunload',event=>{if(!changed()||state.busy)return;event.preventDefault();event.returnValue='';});
    icons();
})();
</script>
@endsection
