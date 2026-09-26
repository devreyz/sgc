@extends('layouts.bento')

@section('title', $receipt ? 'Editar faturamento' : 'Novo faturamento')
@section('page-title', $receipt ? 'Editar faturamento' : 'Novo faturamento')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Contabilidade')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('accounting', 'processes', $tenant->slug);
    $routeArgs = ['tenant' => $tenant->slug] + ($receipt ? ['receipt' => $receipt->id] : []);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/accounting-portal.css') }}">
@endpush

@section('content')
<main class="acc-shell acc-billing-editor" data-billing-editor
    data-context-url="{{ route($receipt ? 'accounting.billings.context.edit' : 'accounting.billings.context', $routeArgs) }}"
    data-manual-url="{{ route($receipt ? 'accounting.billings.manual.edit' : 'accounting.billings.manual', $routeArgs) }}"
    data-select-url="{{ route($receipt ? 'accounting.billings.select.edit' : 'accounting.billings.select', $routeArgs) }}"
    data-preview-url="{{ route($receipt ? 'accounting.billings.preview.edit' : 'accounting.billings.preview', $routeArgs) }}"
    data-save-url="{{ $receipt ? route('accounting.billings.update', $routeArgs) : route('accounting.billings.store', ['tenant' => $tenant->slug]) }}"
    data-save-method="{{ $receipt ? 'PUT' : 'POST' }}"
    data-freeze-url="{{ $receipt ? route('accounting.billings.freeze', $routeArgs) : '' }}">
    <header class="acc-topbar">
        <div class="acc-heading">
            <p class="acc-eyebrow">Cobrança do cliente</p>
            <h1>{{ $receipt ? 'Revisar '.$receipt->formatted_number : 'Criar faturamento' }}</h1>
            <p>Selecione as distribuições. Valores, taxas e arredondamentos são sempre calculados no servidor.</p>
        </div>
        <a class="acc-button" href="{{ route('accounting.processes.index', ['tenant' => $tenant->slug]) }}"><i data-lucide="arrow-left"></i> Faturamentos</a>
    </header>

    <nav class="acc-wizard-steps" aria-label="Etapas do faturamento">
        <button type="button" class="is-active" data-step-button="1"><b>1</b><span>Contexto</span></button>
        <button type="button" data-step-button="2"><b>2</b><span>Distribuições</span></button>
        <button type="button" data-step-button="3"><b>3</b><span>Conferência</span></button>
        <button type="button" data-step-button="4"><b>4</b><span>Valores e emissão</span></button>
    </nav>

    <div class="acc-alert" data-editor-message hidden></div>
    <form class="acc-panel" data-billing-form novalidate>
        <section data-step="1">
            <div class="acc-section-heading"><div><span>Etapa 1</span><h2>Projeto, destinatário e período</h2></div></div>
            <div class="acc-form-grid">
                <label class="acc-field acc-span-2"><span>Projetos *</span><select class="acc-select" name="project_ids[]" multiple required size="5"></select><small>Use Ctrl ou toque nos itens para selecionar mais de um projeto compatível.</small></label>
                <fieldset class="acc-recipient-choice acc-span-2"><legend>Quem será cobrado? *</legend>
                    <label><input type="radio" name="recipient_type" value="organization" checked> Organização compradora</label>
                    <label><input type="radio" name="recipient_type" value="customer"> Cliente/unidade</label>
                </fieldset>
                <label class="acc-field acc-span-2" data-recipient-field="organization"><span>Organização *</span><select class="acc-select" name="organization_id"><option value="">Selecione</option></select></label>
                <label class="acc-field acc-span-2" data-recipient-field="customer" hidden><span>Cliente ou unidade *</span><select class="acc-select" name="customer_id"><option value="">Selecione</option></select></label>
                <label class="acc-field"><span>Data de emissão *</span><input class="acc-input" type="date" name="issued_at" required></label>
                <label class="acc-field"><span>Distribuições desde</span><input class="acc-input" type="date" name="from_date"></label>
                <label class="acc-field"><span>Distribuições até</span><input class="acc-input" type="date" name="to_date"></label>
                <label class="acc-field acc-span-2"><span>Observações internas</span><textarea class="acc-input" name="notes" rows="3" maxlength="2000"></textarea></label>
            </div>
        </section>

        <section data-step="2" hidden>
            <div class="acc-section-heading"><div><span>Etapa 2</span><h2>Como deseja localizar as distribuições?</h2></div><strong data-selected-count>0 selecionadas</strong></div>
            <div class="acc-mode-tabs" role="tablist">
                <button type="button" class="is-active" data-mode="period">Todo o período</button>
                <button type="button" data-mode="receipts">Documentos de origem</button>
                <button type="button" data-mode="manual">Seleção manual</button>
            </div>
            <div data-mode-panel="period"><p class="acc-help">Busca todas as distribuições aprovadas do destinatário e período definidos.</p><button class="acc-button acc-button-primary" type="button" data-load-selection><i data-lucide="search"></i> Localizar distribuições</button></div>
            <div data-mode-panel="receipts" hidden>
                <label class="acc-field"><span>Códigos ou QR Codes dos documentos de origem</span><textarea class="acc-input" data-receipt-codes rows="5" placeholder="Um código por linha: CP-..., UUID ou número do comprovante"></textarea></label>
                <div class="acc-inline-actions"><button class="acc-button" type="button" data-open-qr-scanner><i data-lucide="scan-line"></i> Usar câmera</button><button class="acc-button acc-button-primary" type="button" data-load-selection>Selecionar itens compatíveis</button></div>
                <x-accounting.qr-batch-scanner />
                <div class="acc-scanned-batches" data-scanned-batches><p class="acc-help">Nenhum lote escaneado.</p></div>
            </div>
            <div data-mode-panel="manual" hidden>
                <div class="acc-inline-actions"><input class="acc-input" type="search" data-manual-search placeholder="Produto, produtor ou ID"><button class="acc-button" type="button" data-manual-load>Buscar</button></div>
                <div class="acc-table-wrap"><table class="acc-table"><thead><tr><th></th><th>Data</th><th>Produtor</th><th>Produto</th><th>Quantidade</th><th>Destinatário</th></tr></thead><tbody data-manual-rows></tbody></table></div>
                <footer class="acc-pagination" data-manual-pagination></footer>
            </div>
        </section>

        <section data-step="3" hidden>
            <div class="acc-section-heading"><div><span>Etapa 3</span><h2>Conferência da origem</h2></div><strong data-review-count></strong></div>
            <div data-exclusions></div>
            <div class="acc-table-wrap"><table class="acc-table"><thead><tr><th>Data</th><th>Produtor</th><th>Produto</th><th>Quantidade</th><th>Destinatário</th><th></th></tr></thead><tbody data-review-rows></tbody></table></div>
        </section>

        <section data-step="4" hidden>
            <div class="acc-section-heading"><div><span>Etapa 4</span><h2>Valores calculados e emissão</h2></div></div>
            <div class="acc-summary-cards" data-preview-summary></div>
            <h3>Linhas consolidadas</h3>
            <div class="acc-table-wrap"><table class="acc-table"><thead><tr><th>Produto</th><th>Quantidade</th><th>Unidade</th><th>Preço unitário</th><th>Total</th></tr></thead><tbody data-financial-lines></tbody></table></div>
            <h3>Taxas, descontos e acréscimos</h3><div data-financial-fees></div>
            <div class="acc-totals" data-financial-totals></div>
            <p class="acc-help">Ao emitir, o snapshot é congelado e passa a ser a fonte dos documentos, da autorização e do recebimento.</p>
        </section>

        <footer class="acc-editor-actions">
            <button class="acc-button" type="button" data-previous hidden><i data-lucide="arrow-left"></i> Voltar</button>
            <span></span>
            <button class="acc-button acc-button-primary" type="button" data-next>Continuar <i data-lucide="arrow-right"></i></button>
            <button class="acc-button" type="button" data-save hidden><i data-lucide="save"></i> Salvar rascunho</button>
            @if($receipt)<button class="acc-button acc-button-primary" type="button" data-freeze hidden><i data-lucide="lock-keyhole"></i> Conferir e emitir</button>@endif
        </footer>
    </form>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('assets/accounting-billing-editor.js') }}" defer></script>
@endpush
