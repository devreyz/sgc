@extends('layouts.bento')

@section('title', 'Processos Contábeis')
@section('page-title', 'Processos Contábeis')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Contabilidade')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('accounting', 'processes', $tenant->slug);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/accounting-portal.css') }}">
@endpush

@section('content')
<main
    class="acc-shell"
    data-accounting-page="processes"
    data-processes-data-url="{{ route('accounting.data.processes', ['tenant' => $tenant->slug]) }}"
>
    <header class="acc-topbar">
        <div class="acc-heading">
            <p class="acc-eyebrow">Dossiês financeiros</p>
            <h1>Processos</h1>
            <p>Cada linha representa uma cobrança consolidada a partir de distribuições.</p>
        </div>
        <a class="acc-button" href="{{ route('accounting.index', ['tenant' => $tenant->slug]) }}">
            <i data-lucide="list-checks" aria-hidden="true"></i>
            Voltar à fila
        </a>
    </header>

    <section class="acc-panel">
        <form class="acc-filters" data-process-filters>
            <label class="acc-field">
                <span>Buscar</span>
                <input class="acc-input" type="search" name="search" maxlength="100" placeholder="Número, projeto ou destinatário">
            </label>
            <label class="acc-field">
                <span>Projeto</span>
                <select class="acc-select" name="project"><option value="">Todos os projetos</option></select>
            </label>
            <label class="acc-field">
                <span>Situação financeira</span>
                <select class="acc-select" name="financial_status">
                    <option value="">Todas</option>
                    <option value="draft">Rascunho</option>
                    <option value="pending_payment">Aguardando recebimento</option>
                    <option value="partially_paid">Parcialmente recebido</option>
                    <option value="paid">Recebido</option>
                </select>
            </label>
            <label class="acc-field">
                <span>Pendência</span>
                <select class="acc-select" name="pending">
                    <option value="">Todas</option>
                    <option value="review_inconsistency">Inconsistência crítica</option>
                    <option value="review_draft">Revisar rascunho</option>
                    <option value="review_closed">Conferir cobrança fechada</option>
                    <option value="track_balance">Acompanhar saldo</option>
                </select>
            </label>
            <div class="acc-filter-actions">
                <button class="acc-button acc-button-primary" type="submit"><i data-lucide="search" aria-hidden="true"></i> Filtrar</button>
                <button class="acc-button" type="button" data-clear-filters aria-label="Limpar filtros" title="Limpar filtros"><i data-lucide="rotate-ccw" aria-hidden="true"></i></button>
            </div>

            <details class="acc-advanced">
                <summary>Mais filtros</summary>
                <div class="acc-advanced-grid">
                    <label class="acc-field"><span>Organização compradora</span><select class="acc-select" name="organization"><option value="">Todas as organizações</option></select></label>
                    <label class="acc-field"><span>Cliente</span><select class="acc-select" name="customer"><option value="">Todos os clientes</option></select></label>
                    <label class="acc-field"><span>Emissão a partir de</span><input class="acc-input" type="date" name="from"></label>
                    <label class="acc-field"><span>Emissão até</span><input class="acc-input" type="date" name="until"></label>
                    <label class="acc-field"><span>Autorização</span><select class="acc-select" name="authorization_status"><option value="">Todas</option><option value="legacy_unsubmitted">Processo anterior ao workflow</option><option value="sent">Aguardando organização</option><option value="authorized">Autorizada</option><option value="correction_requested">Correção solicitada</option><option value="invalidated">Invalidada</option></select></label>
                    <label class="acc-field"><span>Fiscal</span><select class="acc-select" name="fiscal_status"><option value="">Todos</option><option value="not_started">Não iniciado</option></select></label>
                    <label class="acc-field"><span>Prestação de contas</span><select class="acc-select" name="accountability_status"><option value="">Todas</option><option value="not_started">Não iniciada</option></select></label>
                </div>
            </details>
        </form>

        <div class="acc-table-wrap">
            <table class="acc-table">
                <thead><tr><th>Processo</th><th>Projeto</th><th>Destinatário</th><th>Situação e próxima ação</th><th>Valor</th><th>Integridade</th></tr></thead>
                <tbody data-process-table></tbody>
            </table>
        </div>
        <div class="acc-mobile-list" data-process-mobile></div>
        <footer class="acc-pagination" data-process-pagination></footer>
    </section>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('assets/accounting-portal.js') }}" defer></script>
@endpush
