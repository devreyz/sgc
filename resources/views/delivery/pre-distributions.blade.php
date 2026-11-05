@extends('layouts.bento')
@section('title', 'Planejamento e Pré-distribuições')
@section('page-title', 'Pré-distribuições')
@section('user-role', $manager ? 'Gestão operacional' : 'Entregador')
@php($bentoNavigation = \App\Support\PortalNavigation::make('pre-distributions', 'sheets', $tenant))
@push('styles')
@vite('resources/js/pre-distributions.js')
@endpush
@section('content')
<main class="pd-workspace" data-pre-distributions
      data-base-url="{{ route('pre-distributions.index', ['tenant' => $tenant->slug]) }}"
      data-manager="{{ $manager ? '1' : '0' }}">
    <header class="pd-app-head">
        <div class="pd-app-head-copy">
            <span class="pd-eyebrow"><i class="ph ph-circles-three" aria-hidden="true"></i> Operação de entregas</span>
            <h2>Pré-distribuições</h2>
            <p>{{ $manager ? 'Organize as rotas, acompanhe as planilhas e prepare relações sem afetar o financeiro.' : 'Confira os destinos e registre o que foi entregue, com rapidez e segurança.' }}</p>
        </div>
        @if($manager)
            <button class="btn-primary pd-head-cta" type="button" data-new-sheet>
                <i class="ph ph-plus" aria-hidden="true"></i> Nova planilha
            </button>
        @endif
    </header>

    <div class="pd-feedback" data-message role="status" aria-live="polite" hidden></div>

    <section data-sheet-list aria-label="Planilhas de pré-distribuição">
        <div class="pd-section-heading">
            <div>
                <h3>{{ $manager ? 'Planilhas operacionais' : 'Minhas planilhas' }}</h3>
                <p>Selecione uma planilha para conferir quantidades e acompanhar os registros.</p>
            </div>
        </div>
        <form class="pd-filters" data-filter>
            <label class="pd-field-search">
                <span>Pesquisar</span>
                <span class="pd-input-icon"><i class="ph ph-magnifying-glass" aria-hidden="true"></i><input type="search" name="q" maxlength="100" placeholder="Nome ou código PD"></span>
            </label>
            <label><span>Data</span><input type="date" name="date"></label>
            <label><span>Situação</span><select name="status"><option value="">Todas as situações</option><option value="draft">Rascunho</option><option value="completed">Concluída</option><option value="cancelled">Cancelada</option></select></label>
            <button class="btn-secondary" type="submit"><i class="ph ph-funnel" aria-hidden="true"></i> Filtrar</button>
        </form>
        @if($manager)
            <div class="pd-selection-bar">
                <div class="pd-selection-copy"><i class="ph ph-check-square-offset" aria-hidden="true"></i><span data-selection-count>Selecione planilhas para consolidar</span></div>
                <div class="pd-actions">
                    <button class="btn-secondary" type="button" data-consolidate disabled><i class="ph ph-arrows-merge" aria-hidden="true"></i> Consolidar selecionadas</button>
                    <button class="pd-button-quiet" type="button" data-reports><i class="ph ph-files" aria-hidden="true"></i> Relações emitidas</button>
                </div>
            </div>
        @endif
        <div class="pd-list-content" data-list-content aria-live="polite"></div>
        <nav data-list-pagination class="pd-pagination" aria-label="Paginação das planilhas"></nav>
    </section>

    <section data-sheet-create hidden aria-label="Nova planilha">
        <button class="pd-link-back" type="button" data-back><i class="ph ph-arrow-left" aria-hidden="true"></i> Todas as planilhas</button>
        <div class="pd-section-heading pd-create-heading"><div><span class="pd-eyebrow">Preparar operação</span><h3>Nova planilha</h3><p>Defina a data, os projetos e quem ficará responsável. Os produtos podem ser adicionados depois.</p></div></div>
        <form data-create-form class="pd-create-form">
            <section class="pd-form-section">
                <div class="pd-form-section-title"><span class="pd-step-index">01</span><div><h4>Identificação</h4><p>As informações principais da operação.</p></div></div>
                <div class="pd-form-grid">
                    <label class="pd-field-wide"><span>Nome da planilha</span><input name="title" maxlength="180" placeholder="Ex.: Entregas escolas — rota manhã" required></label>
                    <label><span>Data da operação</span><input name="operational_date" type="date" required></label>
                </div>
            </section>
            <section class="pd-form-section">
                <div class="pd-form-section-title"><span class="pd-step-index">02</span><div><h4>Responsável e projetos</h4><p>Escolha o entregador e os projetos atendidos.</p></div></div>
                <div class="pd-form-grid">
                    <label><span>Pesquisar entregador</span><input name="operator_q" maxlength="80" placeholder="Nome do responsável"></label>
                    <label><span>Pesquisar projeto</span><input name="project_q" maxlength="80" placeholder="Nome do projeto"></label>
                    <div class="pd-form-search-action"><button class="btn-secondary" type="button" data-find-context><i class="ph ph-magnifying-glass" aria-hidden="true"></i> Procurar</button></div>
                    <label class="pd-field-wide"><span>Entregador responsável</span><select name="assigned_to" required><option value="">Selecione o responsável</option></select></label>
                </div>
                <fieldset class="pd-project-picker"><legend>Projetos desta planilha</legend><div data-project-choices></div></fieldset>
                <p data-catalog-hint class="pd-inline-hint" hidden></p>
            </section>
            <section class="pd-form-section">
                <div class="pd-form-section-title"><span class="pd-step-index">03</span><div><h4>Observações <span class="pd-optional">opcional</span></h4><p>Instruções úteis para quem realizará as entregas.</p></div></div>
                <label><span>Anotações para a operação</span><textarea name="notes" rows="3" maxlength="5000" placeholder="Ex.: conferir quantidades na chegada à escola"></textarea></label>
            </section>
            <footer class="pd-form-footer"><p><i class="ph ph-info" aria-hidden="true"></i> Os itens começam pendentes até que suas quantidades sejam registradas.</p><div class="pd-actions"><button type="button" class="btn-secondary" data-back>Cancelar</button><button class="btn-primary" type="submit"><i class="ph ph-check" aria-hidden="true"></i> Criar planilha</button></div></footer>
        </form>
    </section>

    <section data-sheet-detail hidden aria-label="Detalhes da planilha"></section>
    <section data-consolidated hidden aria-label="Consolidação e documentos"></section>

    <dialog class="pd-action-dialog" data-action-dialog aria-labelledby="pd-action-dialog-title">
        <form data-action-form>
            <div class="pd-action-dialog-top"><span class="pd-action-icon"><i class="ph ph-question" aria-hidden="true"></i></span><button type="button" class="pd-icon-button" data-action-cancel aria-label="Fechar"><i class="ph ph-x" aria-hidden="true"></i></button></div>
            <h3 id="pd-action-dialog-title" data-action-title>Confirmar ação</h3>
            <p data-action-description></p>
            <label data-action-field hidden><span data-action-label></span><input data-action-input autocomplete="off"></label>
            <label data-action-choice-field hidden><span>Opção</span><select data-action-choice></select></label>
            <div class="pd-action-footer"><button type="button" class="btn-secondary" data-action-cancel>Voltar</button><button type="submit" class="btn-primary" data-action-confirm>Confirmar</button></div>
        </form>
    </dialog>
</main>
@endsection
