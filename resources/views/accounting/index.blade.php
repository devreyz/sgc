@extends('layouts.bento')

@section('title', 'Portal Contábil')
@section('page-title', 'Portal Contábil')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Contabilidade')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('accounting', 'queue', $tenant->slug);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/accounting-portal.css') }}">
@endpush

@section('content')
<main
    class="acc-shell"
    data-accounting-page="queue"
    data-queue-url="{{ route('accounting.data.queue', ['tenant' => $tenant->slug]) }}"
    data-processes-url="{{ route('accounting.processes.index', ['tenant' => $tenant->slug]) }}"
>
    <header class="acc-topbar">
        <div class="acc-heading">
            <p class="acc-eyebrow">Trabalho contábil</p>
            <h1>Fila de processos</h1>
            <p>Prioridades calculadas a partir das cobranças e distribuições do tenant.</p>
        </div>
        <a class="acc-button acc-button-primary" href="{{ route('accounting.processes.index', ['tenant' => $tenant->slug]) }}">
            <i data-lucide="folder-search" aria-hidden="true"></i>
            Ver processos
        </a>
    </header>

    <section class="acc-summary" data-queue-summary aria-live="polite">
        <div class="acc-summary-item"><span>Carregando</span><strong>...</strong></div>
        <div class="acc-summary-item"><span>Carregando</span><strong>...</strong></div>
        <div class="acc-summary-item"><span>Carregando</span><strong>...</strong></div>
    </section>

    <section class="acc-panel">
        <header class="acc-panel-head">
            <div>
                <h2>Ações que exigem atenção</h2>
                <p>Filas sem pendências não ocupam espaço.</p>
            </div>
        </header>
        <div class="acc-queue" data-queue-list aria-live="polite"></div>
    </section>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('assets/accounting-portal.js') }}" defer></script>
@endpush
