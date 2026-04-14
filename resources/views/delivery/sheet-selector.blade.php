@extends('layouts.bento')

@section('title', 'Fichas de Entrega')
@section('page-title', 'Fichas de Entrega')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard', ['tenant' => $currentTenant->slug ?? request()->route('tenant')]) }}" class="nav-tab">
        <i data-lucide="layout-dashboard" style="width:14px;height:14px"></i> Dashboard
    </a>
    <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug ?? request()->route('tenant')]) }}" class="nav-tab">
        <i data-lucide="plus-circle" style="width:14px;height:14px"></i> Registrar
    </a>
    <a href="{{ route('delivery.sheet.index', ['tenant' => $currentTenant->slug ?? request()->route('tenant')]) }}" class="nav-tab active">
        <i data-lucide="file-text" style="width:14px;height:14px"></i> Fichas
    </a>
    <form action="{{ route('logout') }}" method="POST" style="display:inline">
        @csrf
        <button type="submit" class="nav-tab" style="background:none;cursor:pointer;color:var(--color-danger)">
            <i data-lucide="log-out" style="width:14px;height:14px"></i> Sair
        </button>
    </form>
</nav>
@endsection

@section('content')
<style>
.sheet-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    margin-bottom: 1.25rem;
}
.sheet-card h2 {
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: .75rem;
}
.form-group { margin-bottom: 1rem; }
.form-label { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--color-text-secondary); display: block; margin-bottom: .35rem; }
.form-select, .form-input {
    width: 100%;
    padding: .55rem .85rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg);
    color: var(--color-text);
    font-size: .9rem;
}
.form-select:focus, .form-input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}

/* Products grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: .6rem;
    margin-top: .5rem;
    max-height: 380px;
    overflow-y: auto;
    padding: .25rem;
}
.product-checkbox {
    display: flex;
    align-items: flex-start;
    gap: .5rem;
    background: var(--color-bg);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    padding: .6rem .75rem;
    cursor: pointer;
    transition: .15s;
    user-select: none;
}
.product-checkbox:hover { border-color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 5%, transparent); }
.product-checkbox.selected { border-color: var(--color-primary); background: color-mix(in srgb, var(--color-primary) 10%, transparent); }
.product-checkbox input[type=checkbox] { margin-top: .2rem; accent-color: var(--color-primary); width: 15px; height: 15px; flex-shrink: 0; }
.product-checkbox .p-name { font-size: .82rem; font-weight: 600; display: block; }
.product-checkbox .p-price { font-size: .75rem; color: var(--color-text-secondary); display: block; margin-top: .1rem; }
.product-checkbox .p-unit  { font-size: .7rem; color: var(--color-text-secondary); }

.bulk-actions { display: flex; gap: .5rem; margin-bottom: .5rem; flex-wrap: wrap; }
.btn-sm { padding: .3rem .7rem; border-radius: var(--radius-sm); font-size: .78rem; font-weight: 600; cursor: pointer; border: 1px solid var(--color-border); background: var(--color-bg); color: var(--color-text); }
.btn-sm:hover { background: var(--color-surface); }
.btn-primary { background: var(--color-primary); color: #fff; border-color: var(--color-primary); padding: .6rem 1.4rem; border-radius: var(--radius-md); font-size: .9rem; font-weight: 700; cursor: pointer; width: 100%; }
.btn-primary:hover { opacity: .9; }
.btn-primary:disabled { opacity: .5; cursor: not-allowed; }

.loading-overlay { display: none; align-items: center; gap: .5rem; color: var(--color-text-secondary); font-size: .82rem; margin-top: .35rem; }
.spinner { width: 14px; height: 14px; border: 2px solid var(--color-border); border-top-color: var(--color-primary); border-radius: 50%; animation: spin .6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.selected-count { font-size: .78rem; color: var(--color-primary); font-weight: 600; margin-left: auto; }
.preview-note { font-size: .75rem; color: var(--color-text-secondary); margin-top: .75rem; text-align: center; }
#no-products-msg { display: none; padding: 1.5rem; text-align: center; color: var(--color-text-secondary); font-size: .85rem; }
</style>

<form id="sheet-form" method="POST" action="{{ route('delivery.sheet.generate', ['tenant' => request()->route('tenant')]) }}">
@csrf

<div class="sheet-card">
    <h2><i data-lucide="user" style="width:16px;height:16px"></i> 1. Selecione o Cliente</h2>

    <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:end;">
        <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Cliente</label>
            <select class="form-select" id="customer-select" name="customer_id" required>
                <option value="">— selecione um cliente —</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}{{ $c->trade_name ? ' ('.$c->trade_name.')' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Data da Ficha</label>
            <input type="date" class="form-input" style="width:160px" name="sheet_date" value="{{ date('Y-m-d') }}">
        </div>
    </div>

    <div class="loading-overlay" id="loading-products">
        <div class="spinner"></div> Carregando produtos...
    </div>
</div>

<div class="sheet-card" id="products-card" style="display:none">
    <h2>
        <i data-lucide="package" style="width:16px;height:16px"></i> 2. Selecione os Produtos
        <span class="selected-count" id="selected-count">0 selecionados</span>
    </h2>

    <div class="bulk-actions">
        <button type="button" class="btn-sm" id="select-all">Selecionar Todos</button>
        <button type="button" class="btn-sm" id="deselect-all">Desmarcar Todos</button>
    </div>

    <div class="products-grid" id="products-grid"></div>
    <div id="no-products-msg">Nenhum produto encontrado para este cliente.</div>
</div>

<div class="sheet-card" id="generate-card" style="display:none">
    <h2><i data-lucide="printer" style="width:16px;height:16px"></i> 3. Gerar Ficha</h2>

    <button type="submit" class="btn-primary" id="generate-btn" disabled>
        <i data-lucide="download" style="width:15px;height:15px;display:inline-block;vertical-align:middle;margin-right:4px"></i>
        Gerar e Baixar PDF
    </button>
    <p class="preview-note">
        O PDF será gerado em A4 horizontal com duas vias (organização e produtor) e duas colunas de produtos em cada via.
    </p>
</div>

</form>

<script>
(function () {
    const tenantSlug  = '{{ request()->route('tenant') }}';
    const apiBase     = '/{{ request()->route('tenant') }}/delivery/sheet/products';
    const customerSel = document.getElementById('customer-select');
    const productsGrid= document.getElementById('products-grid');
    const productsCard= document.getElementById('products-card');
    const generateCard= document.getElementById('generate-card');
    const generateBtn = document.getElementById('generate-btn');
    const loadingEl   = document.getElementById('loading-products');
    const countEl     = document.getElementById('selected-count');
    const noMsg       = document.getElementById('no-products-msg');

    function updateCount() {
        const checked = productsGrid.querySelectorAll('input[type=checkbox]:checked').length;
        countEl.textContent = checked + ' selecionado' + (checked !== 1 ? 's' : '');
        generateBtn.disabled = checked === 0;
    }

    function renderProducts(products) {
        productsGrid.innerHTML = '';
        noMsg.style.display = 'none';

        if (!products.length) {
            noMsg.style.display = 'block';
            return;
        }

        products.forEach(p => {
            const label = document.createElement('label');
            label.className = 'product-checkbox';
            label.innerHTML = `
                <input type="checkbox" name="product_ids[]" value="${p.id}" checked>
                <div>
                    <span class="p-name">${p.name}</span>
                    <span class="p-price">R$ ${p.sale_price.toFixed(2).replace('.', ',')} / ${p.unit}${p.has_custom ? ' <span style="color:var(--color-primary)">★</span>' : ''}</span>
                </div>
            `;
            const cb = label.querySelector('input');
            cb.addEventListener('change', () => {
                label.classList.toggle('selected', cb.checked);
                updateCount();
            });
            label.classList.add('selected');
            productsGrid.appendChild(label);
        });

        updateCount();
    }

    customerSel.addEventListener('change', function () {
        const id = this.value;
        if (!id) {
            productsCard.style.display = 'none';
            generateCard.style.display = 'none';
            return;
        }

        loadingEl.style.display = 'flex';
        productsCard.style.display = 'none';
        generateCard.style.display = 'none';

        fetch(`${apiBase}/${id}`)
            .then(r => r.json())
            .then(products => {
                loadingEl.style.display = 'none';
                productsCard.style.display = 'block';
                generateCard.style.display = 'block';
                renderProducts(products);
            })
            .catch(() => {
                loadingEl.style.display = 'none';
                alert('Erro ao carregar produtos. Tente novamente.');
            });
    });

    document.getElementById('select-all').addEventListener('click', () => {
        productsGrid.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.checked = true;
            cb.closest('label').classList.add('selected');
        });
        updateCount();
    });

    document.getElementById('deselect-all').addEventListener('click', () => {
        productsGrid.querySelectorAll('input[type=checkbox]').forEach(cb => {
            cb.checked = false;
            cb.closest('label').classList.remove('selected');
        });
        updateCount();
    });

    // Show loader on submit
    document.getElementById('sheet-form').addEventListener('submit', function () {
        generateBtn.disabled = true;
        generateBtn.textContent = 'Gerando PDF...';
        setTimeout(() => {
            generateBtn.disabled = false;
            generateBtn.innerHTML = '<i data-lucide="download" style="width:15px;height:15px;display:inline-block;vertical-align:middle;margin-right:4px"></i> Gerar e Baixar PDF';
            if (window.lucide) lucide.createIcons();
        }, 4000);
    });
})();
</script>
@endsection
