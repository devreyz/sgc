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
        <p style="color: var(--color-danger); font-weight: 500;">❌ {{ session('error') }}</p>
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

    <!-- Formulário de Venda -->
    <div class="bento-card col-span-full lg:col-span-8">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">🛒 Registrar Venda</h2>

        <form action="{{ $tenantSlug ? route('cashier.store', ['tenant' => $tenantSlug]) : url('/') }}" method="POST" id="sale-form">
            @csrf

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <!-- Produto -->
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Produto *</label>
                    <select name="product_id" id="product_id" class="form-select" required onchange="updateProductInfo()">
                        <option value="">-- Selecione o Produto --</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}"
                                    data-price="{{ $product->sale_price }}"
                                    data-stock="{{ $product->current_stock }}"
                                    data-unit="{{ $product->unit }}"
                                    {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                {{ $product->name }} ({{ $product->unit }}) — R$ {{ number_format($product->sale_price, 2, ',', '.') }} — Estoque: {{ number_format($product->current_stock, 3, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Info do Produto -->
                <div id="product-info" style="grid-column:1/-1;display:none;padding:1rem;background:var(--color-bg);border-radius:var(--radius-lg);margin-bottom:0.5rem;">
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;text-align:center;">
                        <div>
                            <div class="text-xs text-muted">Preço Venda</div>
                            <div class="font-bold" style="color:var(--color-success);" id="info-price">-</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted">Estoque Disponível</div>
                            <div class="font-bold" id="info-stock">-</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted">Unidade</div>
                            <div class="font-bold" id="info-unit">-</div>
                        </div>
                    </div>
                </div>

                <!-- Quantidade -->
                <div class="form-group">
                    <label class="form-label">Quantidade *</label>
                    <input type="number" name="quantity" id="quantity" class="form-input"
                           step="0.001" min="0.001" required
                           value="{{ old('quantity') }}"
                           placeholder="Ex: 1.500"
                           oninput="updateTotal()">
                </div>

                <!-- Preço Unitário -->
                <div class="form-group">
                    <label class="form-label">Preço Unitário (R$) *</label>
                    <input type="number" name="unit_price" id="unit_price" class="form-input"
                           step="0.01" min="0.01" required
                           value="{{ old('unit_price') }}"
                           placeholder="0.00"
                           oninput="updateTotal()">
                </div>

                <!-- Forma de Pagamento -->
                <div class="form-group">
                    <label class="form-label">Forma de Pagamento *</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="dinheiro" {{ old('payment_method', 'dinheiro') == 'dinheiro' ? 'selected' : '' }}>💵 Dinheiro</option>
                        <option value="pix" {{ old('payment_method') == 'pix' ? 'selected' : '' }}>📱 PIX</option>
                        <option value="cartao_debito" {{ old('payment_method') == 'cartao_debito' ? 'selected' : '' }}>💳 Cartão Débito</option>
                        <option value="cartao_credito" {{ old('payment_method') == 'cartao_credito' ? 'selected' : '' }}>💳 Cartão Crédito</option>
                        <option value="boleto" {{ old('payment_method') == 'boleto' ? 'selected' : '' }}>📄 Boleto</option>
                        <option value="outro" {{ old('payment_method') == 'outro' ? 'selected' : '' }}>🔹 Outro</option>
                    </select>
                </div>

                <!-- Nome do Cliente (opcional) -->
                <div class="form-group">
                    <label class="form-label">Nome do Cliente (opcional)</label>
                    <input type="text" name="customer_name" class="form-input"
                           value="{{ old('customer_name') }}"
                           placeholder="Nome do cliente (se necessário)">
                </div>

                <!-- Observações -->
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="form-label">Observações</label>
                    <textarea name="notes" class="form-textarea" rows="2"
                              placeholder="Observações adicionais...">{{ old('notes') }}</textarea>
                </div>
            </div>

            <!-- Total e Botão -->
            <div style="margin-top:1.5rem;padding:1.5rem;background:linear-gradient(135deg, rgba(16,185,129,0.05), rgba(59,130,246,0.05));border-radius:var(--radius-lg);border:1px solid var(--color-border);">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
                    <div>
                        <div class="text-sm text-muted">Valor Total</div>
                        <div id="total-display" style="font-size:2rem;font-weight:700;color:var(--color-success);">R$ 0,00</div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding:1rem 2rem;font-size:1.125rem;">
                        🛒 Registrar Venda
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Referência Rápida -->
    <div class="bento-card col-span-full lg:col-span-4">
        <h2 class="font-bold mb-4" style="font-size: 1.125rem;">📋 Produtos</h2>
        <div style="max-height:500px;overflow-y:auto;">
            @foreach($products as $product)
            <div style="padding:0.75rem;border-bottom:1px solid var(--color-border);cursor:pointer;" 
                 onclick="selectProduct({{ $product->id }})">
                <div class="font-semibold text-sm">{{ $product->name }}</div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:0.25rem;">
                    <span class="text-xs text-muted">{{ number_format($product->current_stock, 3, ',', '.') }} {{ $product->unit }}</span>
                    <span class="font-bold text-sm" style="color:var(--color-success);">R$ {{ number_format($product->sale_price, 2, ',', '.') }}</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<script>
function updateProductInfo() {
    const select = document.getElementById('product_id');
    const option = select.options[select.selectedIndex];
    const info = document.getElementById('product-info');
    
    if (!option.value) {
        info.style.display = 'none';
        return;
    }
    
    info.style.display = 'block';
    document.getElementById('info-price').textContent = 'R$ ' + parseFloat(option.dataset.price).toFixed(2).replace('.', ',');
    document.getElementById('info-stock').textContent = parseFloat(option.dataset.stock).toFixed(3).replace('.', ',') + ' ' + option.dataset.unit;
    document.getElementById('info-unit').textContent = option.dataset.unit;
    
    // Auto-preencher preço
    document.getElementById('unit_price').value = option.dataset.price;
    updateTotal();
}

function updateTotal() {
    const qty = parseFloat(document.getElementById('quantity').value) || 0;
    const price = parseFloat(document.getElementById('unit_price').value) || 0;
    const total = qty * price;
    
    document.getElementById('total-display').textContent = 'R$ ' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, '.').replace('.', ',');
    // Fix: proper BR formatting
    document.getElementById('total-display').textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function selectProduct(productId) {
    const select = document.getElementById('product_id');
    select.value = productId;
    updateProductInfo();
    document.getElementById('quantity').focus();
}

// Init
document.addEventListener('DOMContentLoaded', function() {
    updateProductInfo();
});
</script>
@endsection
