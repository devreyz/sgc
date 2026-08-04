@extends('layouts.bento')

@php
    $editing = $receipt instanceof \App\Models\FinancialReceipt;
    $savedItems = old('items', $editing
        ? $receipt->items->map(fn ($item) => [
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'unit_price' => $item->unit_price,
            'reference' => $item->reference,
        ])->all()
        : []);
@endphp

@section('title', $editing ? 'Editar Recebimento' : 'Novo Recebimento')
@section('page-title', $editing ? 'Editar Recebimento' : 'Novo Recebimento')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Financeiro')
@php
    $bentoNavigation = \App\Support\PortalNavigation::make('finance', 'new-receipt', $tenant->slug);
@endphp

@section('content')
@include('finance.partials.styles')
<main class="fin-shell">
    <header class="fin-head">
        <div>
            <h1>{{ $editing ? 'Editar rascunho' : 'Novo recebimento' }}</h1>
            <p>Use um valor direto ou detalhe os itens quando isso ajudar na conferencia.</p>
        </div>
        <div class="fin-actions">
            <a class="fin-btn" href="{{ $editing ? route('finance.receipts.show', ['tenant' => $tenant->slug, 'financialReceipt' => $receipt]) : route('finance.receipts.index', ['tenant' => $tenant->slug]) }}">
                <i data-lucide="x"></i> Cancelar
            </a>
        </div>
    </header>

    @if ($errors->any())
        <div class="fin-alert fin-alert-error"><strong>Confira os dados informados.</strong><div>{{ $errors->first() }}</div></div>
    @endif
    @if ($accounts->isEmpty())
        <div class="fin-alert fin-alert-error">Cadastre e ative uma conta ou caixa antes de registrar um recebimento.</div>
    @endif

    <form id="receipt-form" method="POST" action="{{ $editing ? route('finance.receipts.update', ['tenant' => $tenant->slug, 'financialReceipt' => $receipt]) : route('finance.receipts.store', ['tenant' => $tenant->slug]) }}">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="fin-card fin-form-grid">
            <div class="fin-field fin-col-3"><label for="payer_type">Tipo de pagador</label><select class="fin-select" id="payer_type" name="payer_type" required>@foreach (['other' => 'Pessoa ou entidade', 'customer' => 'Cliente', 'organization' => 'Organizacao', 'associate' => 'Associado', 'supplier' => 'Fornecedor', 'service_provider' => 'Prestador'] as $value => $label)<option value="{{ $value }}" @selected(old('payer_type', $receipt?->payer_type ?? 'other') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="fin-field fin-col-6"><label for="payer_name">Nome ou razao social</label><input class="fin-input" id="payer_name" name="payer_name" maxlength="255" required value="{{ old('payer_name', $receipt?->payer_name ?? '') }}"></div>
            <div class="fin-field fin-col-3"><label for="payer_document">CPF, CNPJ ou documento</label><input class="fin-input" id="payer_document" name="payer_document" maxlength="30" value="{{ old('payer_document', $receipt?->payer_document ?? '') }}"></div>
            <div class="fin-field fin-col-4"><label for="received_on">Data do recebimento</label><input class="fin-input" id="received_on" type="date" max="{{ now()->toDateString() }}" name="received_on" required value="{{ old('received_on', $editing ? $receipt->received_on->format('Y-m-d') : now()->toDateString()) }}"></div>
            <div class="fin-field fin-col-4"><label for="payment_method">Meio de pagamento</label><select class="fin-select" id="payment_method" name="payment_method" required>@foreach ($paymentMethods as $method)<option value="{{ $method->value }}" @selected(old('payment_method', $receipt?->payment_method?->value ?? 'dinheiro') === $method->value)>{{ $method->getLabel() }}</option>@endforeach</select></div>
            <div class="fin-field fin-col-4"><label for="payment_reference">Referencia do pagamento</label><input class="fin-input" id="payment_reference" name="payment_reference" maxlength="255" placeholder="PIX, cheque, contrato ou parcela" value="{{ old('payment_reference', $receipt?->payment_reference ?? '') }}"></div>
            <div class="fin-field fin-col-6"><label for="bank_account_id">Conta ou caixa de entrada</label><select class="fin-select" id="bank_account_id" name="bank_account_id" required><option value="">Selecione</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((string) old('bank_account_id', $receipt?->bank_account_id ?? '') === (string) $account->id)>{{ $account->name }} - saldo R$ {{ number_format((float) $account->current_balance, 2, ',', '.') }}</option>@endforeach</select></div>
            <div class="fin-field fin-col-6"><label for="chart_account_id">Classificacao financeira</label><select class="fin-select" id="chart_account_id" name="chart_account_id"><option value="">Sem classificacao</option>@foreach ($chartAccounts as $account)<option value="{{ $account->id }}" @selected((string) old('chart_account_id', $receipt?->chart_account_id ?? '') === (string) $account->id)>{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
            <div class="fin-field fin-col-12"><label for="purpose">Referente a</label><input class="fin-input" id="purpose" name="purpose" maxlength="2000" placeholder="Descreva a origem ou referencia deste recebimento" value="{{ old('purpose', $receipt?->purpose ?? '') }}"></div>
        </section>

        <section class="fin-card" style="margin-top:.8rem">
            <div class="fin-section-title"><div><h2>Valor e detalhamento</h2><p style="margin:.2rem 0 0;font-size:.75rem;color:var(--color-text-secondary)">O detalhamento e opcional. Ao adicionar itens, o total sera calculado por eles.</p></div><button class="fin-btn" id="add-item" type="button"><i data-lucide="plus"></i> Detalhar itens</button></div>
            <div class="fin-field" style="max-width:300px;margin:.4rem 0 .8rem"><label for="manual_amount">Valor recebido</label><input class="fin-input" id="manual_amount" name="manual_amount" type="number" min="0.01" step="0.01" inputmode="decimal" value="{{ old('manual_amount', $receipt?->manual_amount ?? '') }}" placeholder="0,00"></div>
            <div id="receipt-items"></div>
            <div class="fin-total"><span id="receipt-total-label">Total do recebimento</span><strong id="receipt-total">R$ 0,00</strong></div>
        </section>

        <section class="fin-card fin-form-grid" style="margin-top:.8rem"><div class="fin-field fin-col-12"><label for="notes">Observacoes internas</label><textarea class="fin-textarea" id="notes" name="notes" maxlength="3000">{{ old('notes', $receipt?->notes ?? '') }}</textarea></div></section>
        <div class="fin-actions" style="justify-content:flex-end;margin-top:.8rem"><button class="fin-btn fin-btn-primary" type="submit" @disabled($accounts->isEmpty())><i data-lucide="save"></i> Salvar rascunho</button></div>
    </form>
</main>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('receipt-items');
    const form = document.getElementById('receipt-form');
    const manualAmount = document.getElementById('manual_amount');
    const total = document.getElementById('receipt-total');
    const totalLabel = document.getElementById('receipt-total-label');
    let items = @json(array_values($savedItems));
    const money = value => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value) || 0);
    const escape = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));

    function sync() {
        root.querySelectorAll('.fin-item').forEach(row => row.querySelectorAll('input').forEach(input => {
            const key = input.name.match(/\[([^\]]+)\]$/)?.[1];
            if (key) items[Number(row.dataset.index)][key] = input.value;
        }));
    }

    function calculate() {
        let calculated = 0;
        root.querySelectorAll('.fin-item').forEach(row => {
            const quantity = Number(row.querySelector('[name$="[quantity]"]').value) || 0;
            const price = Number(row.querySelector('[name$="[unit_price]"]').value) || 0;
            const line = Math.round(quantity * price * 100) / 100;
            row.querySelector('[data-total]').textContent = money(line);
            calculated += line;
        });
        const hasItems = items.length > 0;
        manualAmount.disabled = hasItems;
        manualAmount.required = !hasItems;
        totalLabel.textContent = hasItems ? 'Total calculado pelos itens' : 'Valor do recebimento';
        total.textContent = money(hasItems ? calculated : manualAmount.value);
    }

    function render() {
        root.innerHTML = items.map((item, index) => `<div class="fin-item" data-index="${index}"><div class="fin-field fin-description"><label>Descricao</label><input class="fin-input" name="items[${index}][description]" maxlength="1000" value="${escape(item.description)}"></div><div class="fin-field"><label>Quantidade</label><input class="fin-input" name="items[${index}][quantity]" type="number" min="0.0001" step="0.0001" value="${escape(item.quantity)}"></div><div class="fin-field"><label>Unidade</label><input class="fin-input" name="items[${index}][unit]" maxlength="30" value="${escape(item.unit || 'un')}"></div><div class="fin-field"><label>Valor unitario</label><input class="fin-input" name="items[${index}][unit_price]" type="number" min="0" step="0.0001" value="${escape(item.unit_price)}"></div><div class="fin-field fin-reference"><label>Referencia</label><input class="fin-input" name="items[${index}][reference]" maxlength="255" value="${escape(item.reference)}"></div><div><button class="fin-icon-btn js-remove" type="button" title="Remover item" aria-label="Remover item"><i data-lucide="trash-2"></i></button></div><div class="fin-item-total" data-total></div></div>`).join('');
        lucide.createIcons();
        calculate();
    }

    root.addEventListener('input', event => { if (event.target.matches('input')) { sync(); calculate(); } });
    root.addEventListener('click', event => {
        const button = event.target.closest('.js-remove');
        if (!button) return;
        sync();
        items.splice(Number(button.closest('.fin-item').dataset.index), 1);
        render();
    });
    manualAmount.addEventListener('input', calculate);
    document.getElementById('add-item').addEventListener('click', () => {
        sync();
        items.push({ description: '', quantity: 1, unit: 'un', unit_price: '', reference: '' });
        render();
        root.lastElementChild.querySelector('input').focus();
    });
    form.addEventListener('submit', () => form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.textContent = 'Salvando...'; }));
    render();
});
</script>
@endsection
