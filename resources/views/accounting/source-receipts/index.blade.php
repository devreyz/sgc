@extends('layouts.bento')

@section('title', 'Comprovantes de Produtores')
@section('page-title', 'Comprovantes de Produtores')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Contabilidade')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('accounting', 'source-receipts', $tenant->slug);
@endphp

@push('styles')
    @vite('resources/css/accounting-portal.css')
@endpush

@section('content')
<main
    class="acc-shell"
    data-accounting-page="source-receipts"
    data-source-receipts-url="{{ route('accounting.data.source-receipts', ['tenant' => $tenant->slug]) }}"
>
    <header class="acc-topbar">
        <div class="acc-heading">
            <p class="acc-eyebrow">Origem do faturamento</p>
            <h1>Localizar comprovantes e entregas</h1>
            <p>Pesquise pelo número, produtor ou projeto, ou leia o QR Code. A leitura mostra as distribuições; o total do comprovante nunca substitui o cálculo do faturamento.</p>
        </div>
        <a class="acc-button" href="{{ route('accounting.processes.index', ['tenant' => $tenant->slug]) }}">
            <i data-lucide="flow-arrow" aria-hidden="true"></i> Processos
        </a>
    </header>

    <section class="acc-panel">
        <form class="acc-filters acc-receipt-filters" data-source-receipt-filters>
            <label class="acc-field">
                <span>Número, produtor, projeto ou conteúdo do QR Code</span>
                <input class="acc-input" type="search" name="search" maxlength="180" autocomplete="off" placeholder="Ex.: 0008/2026-PAA, CP-… ou link lido">
            </label>
            <label class="acc-field">
                <span>Projeto</span>
                <select class="acc-select" name="project"><option value="">Todos os projetos</option></select>
            </label>
            <label class="acc-field">
                <span>Situação</span>
                <select class="acc-select" name="status">
                    <option value="">Todas</option>
                    <option value="draft">Rascunho</option>
                    <option value="pending_payment">Aguardando pagamento</option>
                    <option value="partially_paid">Pago parcialmente</option>
                    <option value="paid">Pago</option>
                    <option value="obsolete">Obsoleto</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </label>
            <div class="acc-filter-actions">
                <button class="acc-button acc-button-primary" type="submit"><i data-lucide="search"></i> Buscar</button>
                <button class="acc-button" type="button" data-open-scanner><i data-lucide="scan-line"></i> Ler QR</button>
            </div>
        </form>
        <div class="acc-receipt-results" data-source-receipt-results aria-live="polite"></div>
        <footer class="acc-pagination" data-source-receipt-pagination></footer>
    </section>

    <dialog class="acc-dialog" data-qr-dialog>
        <div class="acc-dialog-head"><div><strong>Ler QR Code</strong><span>Aponte a câmera para o código do comprovante.</span></div><button class="acc-button" type="button" data-close-scanner aria-label="Fechar"><i data-lucide="x"></i></button></div>
        <video class="acc-scanner-video" data-scanner-video playsinline muted></video>
        <div class="acc-error" data-scanner-error hidden></div>
        <label class="acc-field"><span>Ou cole o código/link</span><input class="acc-input" data-scanner-manual maxlength="180"></label>
        <button class="acc-button acc-button-primary" type="button" data-use-manual>Localizar comprovante</button>
    </dialog>

    <dialog class="acc-dialog acc-dialog-wide" data-receipt-dialog>
        <div class="acc-dialog-head"><div><strong data-receipt-title>Comprovante</strong><span>Distribuições e processos relacionados</span></div><button class="acc-button" type="button" data-close-receipt aria-label="Fechar"><i data-lucide="x"></i></button></div>
        <div data-receipt-detail></div>
    </dialog>
</main>
@endsection

@push('scripts')
    @vite('resources/js/accounting-portal.js')
@endpush
