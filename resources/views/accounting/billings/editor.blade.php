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
    @vite('resources/css/accounting-portal.css')
    @once
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
    @endonce
@endpush

@section('content')
<main
    class="acc-shell acc-billing-editor billing-workspace"
    data-billing-editor
    data-context-url="{{ route($receipt ? 'accounting.billings.context.edit' : 'accounting.billings.context', $routeArgs) }}"
    data-manual-url="{{ route($receipt ? 'accounting.billings.manual.edit' : 'accounting.billings.manual', $routeArgs) }}"
    data-select-url="{{ route($receipt ? 'accounting.billings.select.edit' : 'accounting.billings.select', $routeArgs) }}"
    data-preview-url="{{ route($receipt ? 'accounting.billings.preview.edit' : 'accounting.billings.preview', $routeArgs) }}"
    data-save-url="{{ $receipt ? route('accounting.billings.update', $routeArgs) : route('accounting.billings.store', ['tenant' => $tenant->slug]) }}"
    data-save-method="{{ $receipt ? 'PUT' : 'POST' }}"
    data-freeze-url="{{ $receipt ? route('accounting.billings.freeze', $routeArgs) : '' }}"
>
    <header class="billing-head">
        <div class="billing-head-main">
            <span class="billing-head-icon" aria-hidden="true"><i class="ph-fill ph-receipt"></i></span>
            <div class="billing-head-copy">
                <p class="billing-eyebrow">Contabilidade · Faturamento</p>
                <h1>{{ $receipt ? 'Revisar '.$receipt->formatted_number : 'Novo faturamento' }}</h1>
                <div class="billing-head-meta">
                    <span><i class="ph ph-buildings"></i>{{ $tenant->name }}</span>
                    <span class="billing-head-secondary"><i class="ph ph-shield-check"></i>Valores calculados no servidor</span>
                </div>
            </div>
        </div>

        <a
            class="billing-btn"
            href="{{ route('accounting.processes.index', ['tenant' => $tenant->slug]) }}"
            title="Voltar para faturamentos"
        >
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
            <span>Faturamentos</span>
        </a>
    </header>

    <nav class="billing-flow" aria-label="Etapas do faturamento">
        <button type="button" class="billing-flow-btn is-active" data-step-button="1"><b>1</b><span><strong>Dados</strong><small>Projetos e destinatário</small></span></button>
        <button type="button" class="billing-flow-btn" data-step-button="2"><b>2</b><span><strong>Itens</strong><small>Escolher distribuições</small></span></button>
        <button type="button" class="billing-flow-btn" data-step-button="3"><b>3</b><span><strong>Revisão</strong><small>Conferir e observar</small></span></button>
        <button type="button" class="billing-flow-btn" data-step-button="4"><b>4</b><span><strong>Emitir</strong><small>Valores e conclusão</small></span></button>
    </nav>

    <div class="billing-message" data-editor-message role="status" aria-live="polite" hidden></div>

    <form class="billing-panel" data-billing-form novalidate>
        <section data-step="1">
            <header class="billing-section-head">
                <div class="billing-section-start">
                    <span class="billing-section-icon" aria-hidden="true"><i class="ph-fill ph-folder-open"></i></span>
                    <div class="billing-section-copy">
                        <h2>1. Informe os dados do faturamento</h2>
                        <p>Defina os projetos, quem será cobrado e o período. Depois, avance para selecionar os itens.</p>
                    </div>
                </div>
            </header>

            <div class="billing-section-body">
                <div class="billing-context-grid">
                    <section class="billing-subsection">
                        <header class="billing-subsection-head">
                            <strong>Projetos</strong>
                            <span>Selecione um ou mais projetos compatíveis com o mesmo faturamento.</span>
                        </header>
                        <div class="billing-subsection-body">
                            <select class="billing-project-select-native" name="project_ids[]" multiple required data-project-select aria-hidden="true" tabindex="-1"></select>
                            <div class="billing-project-summary" data-project-summary>
                                <div class="billing-project-empty">Carregando projetos disponíveis...</div>
                            </div>
                            <div class="billing-project-actions">
                                <button class="billing-btn" type="button" data-open-project-picker>
                                    <i class="ph ph-folder-open" aria-hidden="true"></i>
                                    Escolher projetos
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="billing-subsection">
                        <header class="billing-subsection-head">
                            <strong>Destinatário</strong>
                            <span>Escolha se a cobrança será feita para a organização ou para uma unidade específica.</span>
                        </header>
                        <div class="billing-subsection-body">
                            <fieldset class="billing-recipient-choice">
                                <legend>Quem será cobrado?</legend>
                                <label class="billing-choice">
                                    <input type="radio" name="recipient_type" value="organization" checked>
                                    <span>Organização compradora</span>
                                </label>
                                <label class="billing-choice">
                                    <input type="radio" name="recipient_type" value="customer">
                                    <span>Cliente / unidade</span>
                                </label>
                            </fieldset>

                            <label class="billing-field" data-recipient-field="organization">
                                <span>Organização *</span>
                                <select class="billing-select" name="organization_id"><option value="">Selecione</option></select>
                            </label>

                            <label class="billing-field" data-recipient-field="customer" hidden>
                                <span>Cliente ou unidade *</span>
                                <select class="billing-select" name="customer_id"><option value="">Selecione</option></select>
                            </label>
                        </div>
                    </section>
                </div>

                <section class="billing-subsection billing-period-section">
                    <header class="billing-subsection-head">
                        <strong>Período e emissão</strong>
                        <span>O período limita as distribuições elegíveis; a data de emissão identifica o faturamento.</span>
                    </header>
                    <div class="billing-subsection-body">
                        <div class="billing-fields-3">
                            <label class="billing-field">
                                <span>Data de emissão *</span>
                                <input class="billing-input" type="date" name="issued_at" required>
                            </label>
                            <label class="billing-field">
                                <span>Distribuições desde</span>
                                <input class="billing-input" type="date" name="from_date">
                            </label>
                            <label class="billing-field">
                                <span>Distribuições até</span>
                                <input class="billing-input" type="date" name="to_date">
                            </label>
                            <label class="billing-field billing-span-all">
                                <span>Observações internas</span>
                                <textarea class="billing-textarea" name="notes" rows="3" maxlength="2000" placeholder="Opcional. Use apenas para informações internas deste faturamento."></textarea>
                            </label>
                        </div>
                    </div>
                </section>
            </div>
        </section>

        <section data-step="2" hidden>
            <header class="billing-section-head">
                <div class="billing-section-start">
                    <span class="billing-section-icon is-blue" aria-hidden="true"><i class="ph-fill ph-package"></i></span>
                    <div class="billing-section-copy">
                        <h2>2. Escolha as distribuições</h2>
                        <p>Use o período, os comprovantes de origem ou a seleção manual. Você poderá revisar tudo no próximo passo.</p>
                    </div>
                </div>
                <strong class="billing-section-count" data-selected-count>0 selecionadas</strong>
            </header>

            <div class="billing-section-body">
                <div class="billing-mode-tabs" role="tablist" aria-label="Forma de seleção">
                    <button type="button" class="billing-mode-btn is-active" data-mode="period"><i class="ph ph-calendar-dots" aria-hidden="true"></i><span>Todo o período</span></button>
                    <button type="button" class="billing-mode-btn" data-mode="receipts"><i class="ph ph-receipt" aria-hidden="true"></i><span>Documentos de origem</span></button>
                    <button type="button" class="billing-mode-btn" data-mode="manual"><i class="ph ph-hand-pointing" aria-hidden="true"></i><span>Seleção manual</span></button>
                </div>

                <div class="billing-mode-panel" data-mode-panel="period">
                    <p class="billing-help">Seleciona as distribuições aprovadas que correspondem aos projetos, destinatário e período informados.</p>
                    <button class="billing-btn billing-btn-primary" type="button" data-load-selection>
                        <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                        Localizar distribuições
                    </button>
                </div>

                <div class="billing-mode-panel" data-mode-panel="receipts" hidden>
                    <label class="billing-field">
                        <span>Códigos ou QR Codes dos documentos de origem</span>
                        <textarea class="billing-textarea" data-receipt-codes rows="4" placeholder="Um código por linha: CP-..., UUID ou número do comprovante"></textarea>
                    </label>
                    <div class="billing-inline-actions billing-inline-actions-spaced">
                        <button class="billing-btn" type="button" data-open-qr-scanner>
                            <i class="ph ph-qr-code" aria-hidden="true"></i>
                            Usar câmera
                        </button>
                        <button class="billing-btn billing-btn-primary" type="button" data-load-selection>Selecionar itens compatíveis</button>
                    </div>
                    <x-accounting.qr-batch-scanner />
                    <div class="billing-batch-list" data-scanned-batches><p class="billing-help">Nenhum lote escaneado.</p></div>
                </div>

                <div class="billing-mode-panel" data-mode-panel="manual" hidden>
                    <div class="billing-inline-actions billing-manual-tools">
                        <input class="billing-input" type="search" data-manual-search placeholder="Produto, produtor ou ID" autocomplete="off">
                        <button class="billing-btn" type="button" data-manual-load>
                            <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                            Buscar
                        </button>
                    </div>
                    <div class="billing-table-wrap">
                        <table class="billing-table">
                            <thead><tr><th class="billing-col-check"></th><th>Data</th><th>Produtor</th><th>Produto</th><th>Quantidade</th><th>Destinatário</th></tr></thead>
                            <tbody data-manual-rows></tbody>
                        </table>
                    </div>
                    <footer class="billing-pagination" data-manual-pagination></footer>
                </div>
            </div>
        </section>

        <section data-step="3" hidden>
            <header class="billing-section-head">
                <div class="billing-section-start">
                    <span class="billing-section-icon is-cyan" aria-hidden="true"><i class="ph-fill ph-check-circle"></i></span>
                    <div class="billing-section-copy">
                        <h2>3. Revise o que será faturado</h2>
                        <p>Confira os itens, remova o que não pertence ao faturamento e inclua observações opcionais no comprovante.</p>
                    </div>
                </div>
                <strong class="billing-section-count" data-review-count></strong>
            </header>

            <div class="billing-section-body">
                <div data-exclusions></div>
                <div class="billing-table-wrap">
                    <table class="billing-table">
                        <thead><tr><th>Data</th><th>Produtor</th><th>Produto</th><th>Quantidade</th><th>Destinatário</th><th class="billing-col-action"></th></tr></thead>
                        <tbody data-review-rows></tbody>
                    </table>
                </div>
                <section class="billing-subsection" style="margin-top:1rem">
                    <header class="billing-subsection-head">
                        <strong>Observações</strong>
                        <span>Notas gerais ou referências a produto/distribuição. São independentes das observações das entregas.</span>
                    </header>
                    <div class="billing-subsection-body">
                        <label class="billing-field">
                            <span>Posição no PDF</span>
                            <select class="billing-select" name="report_annotations_position">
                                <option value="after">Depois da tabela</option>
                                <option value="before">Antes da tabela</option>
                            </select>
                        </label>
                        <div data-report-annotations></div>
                        <button class="billing-btn" type="button" data-add-report-annotation><i class="ph ph-plus"></i> Adicionar observação</button>
                    </div>
                </section>
            </div>
        </section>

        <section data-step="4" hidden>
            <header class="billing-section-head">
                <div class="billing-section-start">
                    <span class="billing-section-icon is-green" aria-hidden="true"><i class="ph-fill ph-calculator"></i></span>
                    <div class="billing-section-copy">
                        <h2>4. Confira os valores e emita</h2>
                        <p>Esta é a última etapa. Os valores abaixo foram recalculados no servidor com a seleção atual.</p>
                    </div>
                </div>
            </header>

            <div class="billing-section-body">
                <div class="billing-summary" data-preview-summary></div>

                <h3 class="billing-subtitle">Linhas consolidadas</h3>
                <div class="billing-table-wrap">
                    <table class="billing-table">
                        <thead><tr><th>Produto</th><th>Quantidade</th><th>Unidade</th><th>Preço unitário</th><th>Total</th></tr></thead>
                        <tbody data-financial-lines></tbody>
                    </table>
                </div>

                <h3 class="billing-subtitle">Taxas, descontos e acréscimos</h3>
                <div data-financial-fees></div>
                <div class="billing-totals" data-financial-totals></div>
                <div class="billing-emission-guide">
                    <i class="ph-fill ph-shield-check" aria-hidden="true"></i>
                    <div><strong>Pronto para emitir</strong><span>Ao emitir, o sistema salva a versão atual, valida novamente todos os itens e congela o snapshot usado nos documentos, autorizações e recebimentos.</span></div>
                </div>
            </div>
        </section>

        <footer class="billing-actions">
            <button class="billing-btn" type="button" data-previous hidden>
                <i class="ph ph-arrow-left" aria-hidden="true"></i>
                Voltar
            </button>
            <span class="billing-actions-spacer"></span>
            <button class="billing-btn billing-btn-primary" type="button" data-next>
                Continuar
                <i class="ph ph-arrow-right" aria-hidden="true"></i>
            </button>
            <button class="billing-btn" type="button" data-save hidden>
                <i class="ph ph-floppy-disk" aria-hidden="true"></i>
                Salvar para continuar depois
            </button>
            <button class="billing-btn billing-btn-primary" type="button" data-freeze hidden>
                <i class="ph ph-paper-plane-tilt" aria-hidden="true"></i>
                Emitir faturamento
            </button>
        </footer>
    </form>

    <dialog class="billing-dialog" data-project-dialog aria-labelledby="billing-project-dialog-title">
        <div class="billing-dialog-shell">
            <header class="billing-dialog-head">
                <div>
                    <h3 id="billing-project-dialog-title">Selecionar projetos</h3>
                    <p>Marque os projetos que participarão deste faturamento.</p>
                </div>
                <button class="billing-btn" type="button" data-close-project-picker aria-label="Fechar seleção de projetos" title="Fechar">
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </header>
            <div class="billing-dialog-body">
                <label class="billing-field billing-project-search">
                    <span>Buscar projeto</span>
                    <div class="billing-input-icon">
                        <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                        <input class="billing-input" type="search" data-project-search placeholder="Nome ou código" autocomplete="off">
                    </div>
                </label>
                <div class="billing-project-options" data-project-options></div>
            </div>
            <footer class="billing-dialog-foot">
                <div class="billing-dialog-selection">
                    <span class="billing-dialog-count" data-project-dialog-count>0 selecionados</span>
                    <button class="billing-text-button" type="button" data-clear-project-picker>Limpar</button>
                </div>
                <div class="billing-dialog-actions">
                    <button class="billing-btn" type="button" data-cancel-project-picker>Cancelar</button>
                    <button class="billing-btn billing-btn-primary" type="button" data-apply-project-picker><i class="ph ph-check" aria-hidden="true"></i> Aplicar seleção</button>
                </div>
            </footer>
        </div>
    </dialog>

    <dialog class="billing-dialog" data-freeze-dialog aria-labelledby="billing-freeze-dialog-title">
        <div class="billing-dialog-shell">
            <header class="billing-dialog-head">
                <div>
                    <h3 id="billing-freeze-dialog-title">Emitir este faturamento?</h3>
                    <p>Você não precisa salvar antes: a versão atual será salva e validada agora.</p>
                </div>
                <button class="billing-btn" type="button" data-cancel-freeze aria-label="Fechar confirmação" title="Fechar">
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </header>
            <div class="billing-confirm-copy">
                O sistema fará uma última conferência de integridade. Se estiver tudo correto, as distribuições e os valores serão <strong>congelados no snapshot</strong>. Se houver alguma mudança ou inconsistência, nada será emitido e você receberá a orientação para corrigir.
            </div>
            <footer class="billing-dialog-foot">
                <span></span>
                <div class="billing-dialog-actions">
                    <button class="billing-btn" type="button" data-cancel-freeze>Cancelar</button>
                    <button class="billing-btn billing-btn-primary" type="button" data-confirm-freeze><i class="ph ph-lock-key" aria-hidden="true"></i> Emitir faturamento</button>
                </div>
            </footer>
        </div>
    </dialog>
</main>
@endsection

@push('scripts')
    @vite('resources/js/accounting-billing-editor.js')
@endpush
