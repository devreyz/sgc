@extends('layouts.bento')

@section('title', 'Imprimiveis')
@section('page-title', 'Imprimiveis')
@section('user-role', 'Ferramentas')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'printables', $currentTenant->slug ?? request()->route('tenant'));
@endphp

@section('content')
<style>
    .print-shell{display:grid;grid-template-columns:260px minmax(0,1fr);gap:1rem;max-width:1120px;margin:0 auto}.tool-list{display:grid;gap:.55rem;align-content:start}.tool-card{display:flex;align-items:center;gap:.7rem;width:100%;padding:.85rem;border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);color:var(--color-text);text-decoration:none;text-align:left}.tool-card.active{border-color:var(--color-primary);background:color-mix(in srgb,var(--color-primary) 6%,var(--color-surface))}.tool-card svg{width:18px;height:18px;color:var(--color-primary)}.tool-card strong{display:block;font-size:.8rem}.tool-card span{display:block;font-size:.68rem;color:var(--color-text-secondary);margin-top:.12rem}.print-workspace{padding:1.15rem}.print-title{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--color-border);padding-bottom:.85rem;margin-bottom:1rem}.print-title h2{font-size:1rem;margin:0}.print-grid{display:grid;grid-template-columns:minmax(0,1fr) 180px;gap:.8rem}.field label{display:block;font-size:.72rem;font-weight:750;margin-bottom:.35rem}.field select,.field input,.search-products{width:100%;border:1px solid var(--color-border);border-radius:7px;background:var(--color-bg);color:var(--color-text);padding:.62rem .7rem;font-size:.82rem}.product-tools{display:flex;align-items:center;gap:.5rem;margin:1rem 0 .55rem}.product-tools .search-products{flex:1}.small-button{border:1px solid var(--color-border);background:var(--color-surface);color:var(--color-text);border-radius:7px;padding:.58rem .7rem;font-size:.72rem;font-weight:700;white-space:nowrap}.product-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:.45rem;max-height:360px;overflow:auto}.product-item{display:flex;gap:.55rem;align-items:flex-start;border:1px solid var(--color-border);border-radius:7px;padding:.65rem;background:var(--color-bg);cursor:pointer}.product-item:has(input:checked){border-color:var(--color-primary);background:color-mix(in srgb,var(--color-primary) 6%,var(--color-bg))}.product-item input{accent-color:var(--color-primary);margin-top:.12rem}.product-item strong{display:block;font-size:.76rem}.product-item span{font-size:.68rem;color:var(--color-text-secondary)}.print-footer{display:flex;align-items:center;justify-content:space-between;gap:1rem;border-top:1px solid var(--color-border);padding-top:1rem;margin-top:1rem}.print-footer p{font-size:.72rem;color:var(--color-text-secondary);margin:0}.generate-button{display:inline-flex;align-items:center;gap:.4rem;border:0;border-radius:7px;background:var(--color-primary);color:#fff;padding:.7rem 1rem;font-size:.78rem;font-weight:800}.generate-button:disabled{opacity:.45}.inline-state{padding:1rem;text-align:center;color:var(--color-text-secondary);font-size:.78rem}.form-error{display:none;margin-top:.7rem;padding:.65rem .75rem;border:1px solid color-mix(in srgb,var(--color-danger) 40%,var(--color-border));border-radius:7px;color:var(--color-danger);font-size:.75rem}@media(max-width:760px){.print-shell{grid-template-columns:1fr}.tool-list{grid-template-columns:1fr 1fr}.print-grid{grid-template-columns:1fr}.print-footer{align-items:stretch;flex-direction:column}.generate-button{justify-content:center;width:100%}}@media(max-width:460px){.tool-list{grid-template-columns:1fr}.product-list{grid-template-columns:1fr}}
    #selection-count{font-size:.7rem;font-weight:750;color:var(--color-primary)}
</style>

<div class="print-shell">
    <nav class="tool-list" aria-label="Ferramentas de impressao">
        <button type="button" class="tool-card active"><i data-lucide="clipboard-list"></i><span><strong>Ficha de entrega</strong><span>Produtos e precos por cliente</span></span></button>
        <a class="tool-card" href="{{ route('delivery.projects-list', ['tenant' => request()->route('tenant')]) }}"><i data-lucide="folder-kanban"></i><span><strong>Relatorios do projeto</strong><span>Abrir projetos de venda</span></span></a>
    </nav>

    <form class="bento-card print-workspace" id="sheet-form" method="POST" action="{{ route('delivery.sheet.generate', ['tenant' => request()->route('tenant')]) }}">
        @csrf
        <div class="print-title"><h2>Ficha de entrega</h2><span id="selection-count" class="panel-selector-heading-badge">0 produtos</span></div>
        @if($errors->any())<div class="form-error" style="display:block" role="alert">{{ $errors->first() }}</div>@endif
        <div class="print-grid">
            <div class="field"><label for="customer-select">Cliente</label><select id="customer-select" name="customer_id" required><option value="">Selecione</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></div>
            <div class="field"><label for="sheet-date">Data</label><input id="sheet-date" type="date" name="sheet_date" value="{{ now()->toDateString() }}"></div>
        </div>
        <div class="product-tools"><input class="search-products" id="product-search" type="search" placeholder="Buscar produto" disabled><button class="small-button" id="toggle-products" type="button" disabled>Desmarcar todos</button></div>
        <div id="product-list" class="product-list"><div class="inline-state">Selecione um cliente.</div></div>
        <div class="form-error" id="form-error" role="alert"></div>
        <div class="print-footer">
            <div class="field"><label for="layout">Formato</label><select id="layout" name="layout"><option value="landscape">Paisagem, duas vias</option><option value="portrait">Retrato, uma via</option></select></div>
            <button type="submit" class="generate-button" id="generate-button" disabled><i data-lucide="download"></i>Gerar PDF</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const customer = document.getElementById('customer-select');
    const list = document.getElementById('product-list');
    const search = document.getElementById('product-search');
    const toggle = document.getElementById('toggle-products');
    const count = document.getElementById('selection-count');
    const submit = document.getElementById('generate-button');
    const error = document.getElementById('form-error');
    const endpoint = @json(url('/'.request()->route('tenant').'/delivery/sheet/products'));
    let products = [];

    function updateState() {
        const checked = list.querySelectorAll('input:checked').length;
        count.textContent = `${checked} produto${checked === 1 ? '' : 's'}`;
        submit.disabled = checked === 0;
        toggle.textContent = checked ? 'Desmarcar todos' : 'Marcar todos';
    }

    function render() {
        const term = search.value.trim().toLocaleLowerCase('pt-BR');
        list.innerHTML = '';
        products.filter(product => product.name.toLocaleLowerCase('pt-BR').includes(term)).forEach(product => {
            const label = document.createElement('label'); label.className = 'product-item';
            const input = document.createElement('input'); input.type = 'checkbox'; input.name = 'product_ids[]'; input.value = product.id; input.checked = product.selected;
            const copy = document.createElement('span'); const name = document.createElement('strong'); name.textContent = product.name;
            const price = document.createElement('span'); price.textContent = `R$ ${Number(product.sale_price).toLocaleString('pt-BR',{minimumFractionDigits:2})} / ${product.unit}`;
            copy.append(name, price); label.append(input, copy); list.append(label);
            input.addEventListener('change', () => { product.selected = input.checked; updateState(); });
        });
        if (!list.children.length) list.innerHTML = '<div class="inline-state">Nenhum produto com preco encontrado.</div>';
        updateState();
    }

    customer.addEventListener('change', async () => {
        products = []; search.disabled = true; toggle.disabled = true; submit.disabled = true;
        list.innerHTML = '<div class="inline-state">Carregando...</div>'; error.style.display = 'none';
        if (!customer.value) { list.innerHTML = '<div class="inline-state">Selecione um cliente.</div>'; return; }
        try {
            const response = await fetch(`${endpoint}/${encodeURIComponent(customer.value)}`, {headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error();
            products = (await response.json()).map(product => ({...product, selected:true}));
            search.disabled = false; toggle.disabled = false; render();
        } catch (_) {
            list.innerHTML = ''; error.textContent = 'Nao foi possivel carregar os produtos.'; error.style.display = 'block';
        }
    });
    search.addEventListener('input', render);
    toggle.addEventListener('click', () => { const select = !products.some(product => product.selected); products.forEach(product => product.selected = select); render(); });
    document.getElementById('sheet-form').addEventListener('submit', event => {
        if (!products.some(product => product.selected)) { event.preventDefault(); return; }
        event.currentTarget.querySelectorAll('.persisted-product').forEach(input => input.remove());
        list.querySelectorAll('input[name="product_ids[]"]').forEach(input => input.disabled = true);
        products.filter(product => product.selected).forEach(product => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = 'product_ids[]'; input.value = product.id; input.className = 'persisted-product';
            event.currentTarget.append(input);
        });
        submit.disabled = true; submit.textContent = 'Gerando...';
        setTimeout(() => { list.querySelectorAll('input[name="product_ids[]"]').forEach(input => input.disabled = false); submit.disabled = false; submit.innerHTML = '<i data-lucide="download"></i>Gerar PDF'; window.lucide?.createIcons(); }, 5000);
    });
})();
</script>
@endpush
