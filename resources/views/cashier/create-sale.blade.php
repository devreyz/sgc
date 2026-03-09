@extends('layouts.bento')

@section('title', 'Nova Venda')
@section('page-title', 'Nova Venda')
@section('user-role', 'Operador de Caixa')

@php
    $routeTenant = request()->route('tenant');
    $routeSlug = is_string($routeTenant) ? $routeTenant : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);
    $tenantSlug = $currentTenant?->slug ?? session('tenant_slug') ?? $routeSlug ?? null;
@endphp

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ $tenantSlug ? route('cashier.dashboard', ['tenant' => $tenantSlug]) : url('/') }}" class="nav-tab">Caixa</a>
    <a href="{{ $tenantSlug ? route('cashier.create', ['tenant' => $tenantSlug]) : url('/') }}" class="nav-tab active">Nova Venda</a>
    <a href="{{ $tenantSlug ? route('cashier.history', ['tenant' => $tenantSlug]) : url('/') }}" class="nav-tab">Histórico</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">

    @if(session('error'))
    <div class="bento-card col-span-full" style="border-left: 4px solid var(--color-danger); background: rgba(239, 68, 68, 0.05);">
        <p style="color: var(--color-danger); font-weight: 500;">{{ session('error') }}</p>
    </div>
    @endif

    @if($errors->any())
    <div class="bento-card col-span-full" style="border-left: 4px solid var(--color-danger); background: rgba(239, 68, 68, 0.05);">
        <ul style="color: var(--color-danger); font-size: 0.875rem; list-style: disc; padding-left: 1.5rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <!-- Carrinho de Vendas -->
    <div class="bento-card col-span-full lg:col-span-8">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">🛒 Carrinho de Vendas</h2>

        <form action="{{ $tenantSlug ? route('cashier.store', ['tenant' => $tenantSlug]) : url('/') }}" method="POST" id="sale-form">
            @csrf

            <!-- Adicionar Produto -->
            <div style="background: var(--color-bg); border-radius: var(--radius-lg); padding: 1rem; margin-bottom: 1rem; border: 2px dashed var(--color-border);">
                <div style="display: grid; grid-template-columns: 1fr auto auto auto; gap: 0.75rem; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.75rem;">Produto</label>
                        <select id="add-product-select" class="form-select" style="padding: 0.5rem;">
                            <option value="">Selecione...</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}"
                                        data-name="{{ $product->name }}"
                                        data-price="{{ $product->sale_price }}"
                                        data-stock="{{ $product->current_stock }}"
                                        data-unit="{{ $product->unit }}">
                                    {{ $product->name }} — R$ {{ number_format($product->sale_price, 2, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0; width: 100px;">
                        <label class="form-label" style="font-size: 0.75rem;">Qtd</label>
                        <input type="number" id="add-quantity" class="form-input" step="0.001" min="0.001" value="1" style="padding: 0.5rem;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; width: 120px;">
                        <label class="form-label" style="font-size: 0.75rem;">Preço (R$)</label>
                        <input type="number" id="add-price" class="form-input" step="0.01" min="0.01" style="padding: 0.5rem;">
                    </div>
                    <button type="button" onclick="addToCart()" class="btn btn-primary" style="padding: 0.5rem 1rem; white-space: nowrap; height: fit-content;">
                        + Adicionar
                    </button>
                </div>
            </div>

            <!-- Lista de Itens no Carrinho -->
            <div id="cart-items" style="min-height: 60px;">
                <div id="cart-empty" style="text-align: center; padding: 2rem; color: var(--color-text-muted); font-size: 0.875rem;">
                    Nenhum produto adicionado. Selecione um produto acima ou clique na lista ao lado.
                </div>
            </div>

            <!-- Pagamento e Finalização -->
            <div style="margin-top: 1rem; padding: 1rem; background: var(--color-bg); border-radius: var(--radius-lg); border: 1px solid var(--color-border);">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Forma de Pagamento *</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="dinheiro">💵 Dinheiro</option>
                            <option value="pix">📱 PIX</option>
                            <option value="cartao_debito">💳 Cartão Débito</option>
                            <option value="cartao_credito">💳 Cartão Crédito</option>
                            <option value="boleto">📄 Boleto</option>
                            <option value="outro">🔹 Outro</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Cliente (opcional)</label>
                        <input type="text" name="customer_name" class="form-input" placeholder="Nome do cliente">
                    </div>
                </div>
                <div class="form-group" style="margin-top: 0.75rem; margin-bottom: 0;">
                    <label class="form-label">Observações</label>
                    <textarea name="notes" class="form-textarea" rows="1" placeholder="Observações..."></textarea>
                </div>
            </div>

            <!-- Total e Botão -->
            <div style="margin-top: 1rem; padding: 1.5rem; background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(59,130,246,0.08)); border-radius: var(--radius-lg); border: 1px solid var(--color-border);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div class="text-sm text-muted">Total (<span id="cart-count">0</span> produto(s))</div>
                        <div id="cart-total" style="font-size: 2rem; font-weight: 700; color: var(--color-success);">R$ 0,00</div>
                    </div>
                    <button type="submit" id="btn-submit" class="btn btn-primary" disabled style="padding: 1rem 2rem; font-size: 1.125rem; opacity: 0.5;">
                        ✅ Confirmar Venda
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Produtos - Seleção Rápida -->
    <div class="bento-card col-span-full lg:col-span-4">
        <h2 class="font-bold mb-2" style="font-size: 1.125rem;">📋 Produtos</h2>
        <input type="text" id="product-search" class="form-input" placeholder="Buscar produto..."
               style="margin-bottom: 0.75rem; padding: 0.5rem;" oninput="filterProducts()">
        <div style="max-height: 500px; overflow-y: auto;" id="product-list">
            @foreach($products as $product)
            <div class="product-item" data-name="{{ strtolower($product->name) }}"
                 style="padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--color-border); cursor: pointer; transition: background 0.15s;"
                 onmouseover="this.style.background='var(--color-bg)'" onmouseout="this.style.background=''"
                 onclick="quickAdd({{ $product->id }}, '{{ addslashes($product->name) }}', {{ $product->sale_price }}, {{ $product->current_stock }}, '{{ $product->unit }}')">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div class="font-semibold text-sm">{{ $product->name }}</div>
                        <div class="text-xs text-muted">{{ number_format($product->current_stock, 3, ',', '.') }} {{ $product->unit }}</div>
                    </div>
                    <div class="font-bold text-sm" style="color: var(--color-success);">
                        R$ {{ number_format($product->sale_price, 2, ',', '.') }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
let cart = [];
let itemCounter = 0;

document.getElementById('add-product-select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    if (opt.value) {
        document.getElementById('add-price').value = opt.dataset.price;
        document.getElementById('add-quantity').value = 1;
        document.getElementById('add-quantity').focus();
    }
});

function addToCart() {
    const select = document.getElementById('add-product-select');
    const productId = select.value;
    if (!productId) { alert('Selecione um produto.'); return; }

    const opt = select.options[select.selectedIndex];
    const qty = parseFloat(document.getElementById('add-quantity').value) || 0;
    const price = parseFloat(document.getElementById('add-price').value) || 0;

    if (qty <= 0) { alert('Quantidade deve ser maior que zero.'); return; }
    if (price <= 0) { alert('Preço deve ser maior que zero.'); return; }

    itemCounter++;
    cart.push({
        idx: itemCounter,
        productId: productId,
        name: opt.dataset.name,
        unit: opt.dataset.unit,
        quantity: qty,
        unitPrice: price,
        total: qty * price
    });

    select.value = '';
    document.getElementById('add-quantity').value = 1;
    document.getElementById('add-price').value = '';
    renderCart();
    select.focus();
}

function quickAdd(productId, name, price, stock, unit) {
    if (price <= 0) { alert('Este produto não tem preço de venda definido.'); return; }
    itemCounter++;
    cart.push({ idx: itemCounter, productId: productId, name: name, unit: unit, quantity: 1, unitPrice: price, total: price });
    renderCart();
}

function removeFromCart(idx) {
    cart = cart.filter(i => i.idx !== idx);
    renderCart();
}

function updateCartItem(idx, field, value) {
    const item = cart.find(i => i.idx === idx);
    if (!item) return;
    if (field === 'quantity') item.quantity = parseFloat(value) || 0;
    if (field === 'unitPrice') item.unitPrice = parseFloat(value) || 0;
    item.total = item.quantity * item.unitPrice;
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cart-items');
    const emptyMsg = document.getElementById('cart-empty');

    container.querySelectorAll('.cart-row, input[name^="items"]').forEach(el => el.remove());

    if (cart.length === 0) {
        emptyMsg.style.display = 'block';
        updateTotals();
        return;
    }
    emptyMsg.style.display = 'none';

    cart.forEach((item, i) => {
        // Hidden form inputs
        ['product_id', 'quantity', 'unit_price'].forEach(field => {
            const h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'items[' + i + '][' + field + ']';
            h.value = field === 'product_id' ? item.productId : (field === 'quantity' ? item.quantity : item.unitPrice);
            h.className = 'cart-row';
            container.appendChild(h);
        });

        const row = document.createElement('div');
        row.className = 'cart-row';
        row.style.cssText = 'display:grid;grid-template-columns:1fr 90px 110px 90px 36px;gap:0.5rem;align-items:center;padding:0.6rem 0;border-bottom:1px solid var(--color-border);';
        row.innerHTML = '<div><div class="font-semibold text-sm">' + escapeHtml(item.name) + '</div><div class="text-xs text-muted">' + escapeHtml(item.unit) + '</div></div>'
            + '<input type="number" value="' + item.quantity + '" step="0.001" min="0.001" class="form-input" style="padding:0.35rem;font-size:0.85rem;text-align:center;" onchange="updateCartItem(' + item.idx + ',\'quantity\',this.value)">'
            + '<input type="number" value="' + item.unitPrice.toFixed(2) + '" step="0.01" min="0.01" class="form-input" style="padding:0.35rem;font-size:0.85rem;text-align:right;" onchange="updateCartItem(' + item.idx + ',\'unitPrice\',this.value)">'
            + '<div class="font-bold text-sm text-right" style="color:var(--color-success);">R$ ' + item.total.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}) + '</div>'
            + '<button type="button" onclick="removeFromCart(' + item.idx + ')" style="background:none;border:none;color:var(--color-danger);cursor:pointer;font-size:1.1rem;padding:0;" title="Remover">✕</button>';
        container.appendChild(row);
    });
    updateTotals();
}

function updateTotals() {
    const total = cart.reduce((s, i) => s + i.total, 0);
    document.getElementById('cart-total').textContent = 'R$ ' + total.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
    document.getElementById('cart-count').textContent = cart.length;
    const btn = document.getElementById('btn-submit');
    btn.disabled = cart.length === 0;
    btn.style.opacity = cart.length > 0 ? '1' : '0.5';
}

function filterProducts() {
    const q = document.getElementById('product-search').value.toLowerCase();
    document.querySelectorAll('.product-item').forEach(el => {
        el.style.display = el.dataset.name.includes(q) ? '' : 'none';
    });
}

function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', renderCart);
</script>
@endsection
