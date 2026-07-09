@extends('layouts.bento')

@section('title', 'Nova Solicitacao')
@section('page-title', 'Nova Solicitacao')
@section('user-role', $organization->short_name ?: $organization->name)

@php
    $routeTenant = request()->route('tenant');
    $tenantSlug = is_object($routeTenant) ? $routeTenant->slug : $routeTenant;
@endphp

@section('navigation')
<x-portal.nav portal="buyer" active="projects" :tenant="$tenantSlug" />
@endsection

@section('content')
<div class="bento-grid">
    <div class="bento-card col-span-full">
        <h1 style="font-size:1.35rem;">Nova solicitacao</h1>
        <p class="text-muted text-sm">{{ $project->title }}</p>
    </div>

    <form class="bento-card col-span-full" method="POST" action="{{ route('buyer.requests.store', ['tenant' => $tenantSlug, 'project' => $project]) }}">
        @csrf

        @if($errors->any())
            <div class="app-alert app-alert-error" style="margin-bottom:1rem;">
                <p>{{ $errors->first() }}</p>
            </div>
        @endif

        <div class="form-group">
            <label class="form-label" for="customer_id">Unidade de destino</label>
            <select class="form-select" id="customer_id" name="customer_id" required>
                <option value="">Selecione</option>
                @foreach($customers as $customer)
                    <option value="{{ $customer->id }}">{{ $customer->trade_name ?: $customer->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="reference_date">Data de referencia</label>
            <input class="form-input" id="reference_date" type="date" name="reference_date" value="{{ old('reference_date') }}">
        </div>

        <div id="items" style="display:grid;gap:1rem;">
            <div class="request-item" style="border:1px solid var(--color-border);border-radius:8px;padding:1rem;">
                <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Produto</label>
                        <select class="form-select product-select" name="items[0][product_id]" required></select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Quantidade</label>
                        <input class="form-input" type="number" step="0.001" min="0.001" name="items[0][quantity]" required>
                    </div>
                </div>
                <input type="hidden" class="item-customer" name="items[0][customer_id]">
            </div>
        </div>

        <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem;">
            <button type="button" class="btn btn-outline" id="add-item">Adicionar produto</button>
        </div>

        <div class="form-group" style="margin-top:1rem;">
            <label class="form-label" for="notes">Observacoes</label>
            <textarea class="form-textarea" id="notes" name="notes">{{ old('notes') }}</textarea>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:.75rem;flex-wrap:wrap;">
            <a class="btn btn-outline" href="{{ route('buyer.projects.show', ['tenant' => $tenantSlug, 'project' => $project]) }}">Cancelar</a>
            <button class="btn btn-primary" type="submit">Enviar solicitacao</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
const productsByCustomer = @json($productsByCustomer);
const customerSelect = document.getElementById('customer_id');
const items = document.getElementById('items');
const addButton = document.getElementById('add-item');

function fillProductSelect(select) {
    const customerId = customerSelect.value;
    const products = productsByCustomer[customerId] || [];
    select.innerHTML = '<option value="">Selecione</option>' + products.map(product => {
        const unit = product.unit ? ` (${product.unit})` : '';
        return `<option value="${product.id}">${product.name}${unit}</option>`;
    }).join('');
}

function syncCustomerFields() {
    document.querySelectorAll('.item-customer').forEach(input => input.value = customerSelect.value);
    document.querySelectorAll('.product-select').forEach(fillProductSelect);
}

customerSelect.addEventListener('change', syncCustomerFields);
addButton.addEventListener('click', () => {
    const index = document.querySelectorAll('.request-item').length;
    const wrapper = document.createElement('div');
    wrapper.className = 'request-item';
    wrapper.style = 'border:1px solid var(--color-border);border-radius:8px;padding:1rem;';
    wrapper.innerHTML = `
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Produto</label>
                <select class="form-select product-select" name="items[${index}][product_id]" required></select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Quantidade</label>
                <input class="form-input" type="number" step="0.001" min="0.001" name="items[${index}][quantity]" required>
            </div>
        </div>
        <input type="hidden" class="item-customer" name="items[${index}][customer_id]">
    `;
    items.appendChild(wrapper);
    syncCustomerFields();
});
syncCustomerFields();
</script>
@endpush
