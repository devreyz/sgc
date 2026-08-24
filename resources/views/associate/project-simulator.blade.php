@extends('layouts.bento')

@section('title', 'Simular entrega')
@section('page-title', 'Simular entrega')
@section('page-subtitle', $project->title)
@section('user-role', 'Associado')

@php
    $tenantSlug = request()->route('tenant') instanceof \App\Models\Tenant
        ? request()->route('tenant')->slug
        : request()->route('tenant');
    $bentoNavigation = \App\Support\PortalNavigation::make('associate', null, $tenantSlug);
@endphp

@push('styles')
<style>
    .delivery-simulator {
        --sim-green: #168a4d;
        --sim-green-soft: #eaf8ef;
        --sim-blue: #2563eb;
        --sim-blue-soft: #eef4ff;
        --sim-amber: #b86906;
        --sim-amber-soft: #fff7e8;
        --sim-red: #c83d3d;
        --sim-red-soft: #fff0f0;
        --sim-text: var(--color-text, #102018);
        --sim-secondary: var(--color-text-secondary, #52645a);
        --sim-muted-text: var(--color-text-muted, #74857b);
        --sim-border: var(--color-border, #dce6df);
        --sim-surface: var(--color-surface, #fff);
        --sim-soft: var(--color-surface-soft, #f7faf8);
        display: grid;
        width: min(100%, 980px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .75rem;
        margin: 0 auto;
        padding-bottom: 6.2rem;
        color: var(--sim-text);
    }

    .delivery-simulator *,
    .delivery-simulator *::before,
    .delivery-simulator *::after { box-sizing: border-box; }

    .sim-header {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: center;
        padding: .75rem;
        border: 1px solid var(--sim-border);
        border-radius: 12px;
        background: var(--sim-surface);
        box-shadow: var(--shadow-xs);
    }

    .sim-back,
    .sim-icon-button {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border: 1px solid var(--sim-border);
        border-radius: 10px;
        color: var(--sim-secondary);
        background: var(--sim-surface);
        text-decoration: none;
        cursor: pointer;
    }

    .sim-header-copy { min-width: 0; }
    .sim-header-copy h1 {
        margin: 0;
        overflow: hidden;
        color: var(--sim-text);
        font-size: 1rem;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .sim-header-copy p {
        margin: .12rem 0 0;
        overflow: hidden;
        color: var(--sim-muted-text);
        font-size: .7rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sim-steps {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .28rem;
    }
    .sim-step-marker {
        height: 5px;
        overflow: hidden;
        border-radius: 999px;
        background: var(--sim-border);
    }
    .sim-step-marker span {
        display: block;
        width: 0;
        height: 100%;
        border-radius: inherit;
        background: var(--sim-green);
        transition: width .2s ease;
    }
    .sim-step-marker.is-complete span { width: 100%; }

    .sim-panel {
        padding: clamp(.8rem, 2vw, 1.15rem);
        border: 1px solid var(--sim-border);
        border-radius: 12px;
        background: var(--sim-surface);
        box-shadow: var(--shadow-xs);
    }
    .sim-panel[hidden] { display: none; }
    .sim-panel-head { margin-bottom: .82rem; }
    .sim-eyebrow {
        display: block;
        margin-bottom: .2rem;
        color: var(--sim-green);
        font-size: .66rem;
        font-weight: 800;
    }
    .sim-panel-head h2 { margin: 0; font-size: 1.08rem; line-height: 1.25; }
    .sim-panel-head p {
        max-width: 680px;
        margin: .3rem 0 0;
        color: var(--sim-secondary);
        font-size: .78rem;
        line-height: 1.48;
    }

    .sim-intro-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .55rem;
    }
    .sim-intro-item {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .5rem;
        align-items: start;
        padding: .68rem;
        border: 1px solid var(--sim-border);
        border-radius: 10px;
        background: var(--sim-soft);
    }
    .sim-intro-number {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 8px;
        color: var(--sim-green);
        background: var(--sim-green-soft);
        font-size: .72rem;
        font-weight: 850;
    }
    .sim-intro-item strong { display: block; font-size: .75rem; }
    .sim-intro-item span:last-child { display: block; margin-top: .12rem; color: var(--sim-muted-text); font-size: .66rem; line-height: 1.4; }

    .sim-history { margin-top: .9rem; padding-top: .8rem; border-top: 1px solid var(--sim-border); }
    .sim-history-head { display: flex; gap: .6rem; align-items: center; justify-content: space-between; }
    .sim-history-head h3 { margin: 0; font-size: .8rem; }
    .sim-history-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: .5rem; margin-top: .5rem; }
    .sim-history-card { overflow: hidden; border: 1px solid var(--sim-border); border-radius: 9px; background: var(--sim-surface); }
    .sim-history-card img { display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: cover; border-bottom: 1px solid var(--sim-border); background: var(--sim-soft); }
    .sim-history-card-body { padding: .5rem; }
    .sim-history-card-body strong { display: block; font-size: .7rem; }
    .sim-history-card-body span { display: block; margin-top: .1rem; color: var(--sim-muted-text); font-size: .61rem; }
    .sim-history-actions { display: flex; gap: .3rem; margin-top: .42rem; }

    .sim-choice-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .55rem; }
    .sim-choice {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .55rem;
        align-items: center;
        min-width: 0;
        padding: .72rem;
        border: 1px solid var(--sim-border);
        border-radius: 10px;
        color: var(--sim-text);
        background: var(--sim-surface);
        text-align: left;
        cursor: pointer;
    }
    .sim-choice.is-active { border-color: var(--sim-green); background: var(--sim-green-soft); }
    .sim-choice-icon { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 9px; color: var(--sim-green); background: var(--sim-green-soft); font-size: 1.05rem; }
    .sim-choice strong { display: block; font-size: .76rem; }
    .sim-choice small { display: block; margin-top: .12rem; color: var(--sim-muted-text); font-size: .64rem; line-height: 1.35; }

    .sim-field { margin-top: .72rem; }
    .sim-field label { display: block; margin-bottom: .25rem; color: var(--sim-secondary); font-size: .68rem; font-weight: 750; }
    .sim-input,
    .sim-select,
    .sim-search {
        width: 100%;
        min-height: 44px;
        padding: .6rem .68rem;
        border: 1px solid var(--sim-border);
        border-radius: 9px;
        color: var(--sim-text);
        background: var(--sim-surface);
        font: inherit;
        font-size: .78rem;
    }
    .sim-input:focus,
    .sim-select:focus,
    .sim-search:focus { border-color: var(--sim-green); outline: 3px solid color-mix(in srgb, var(--sim-green) 12%, transparent); }
    .sim-quota-preview { display: grid; grid-template-columns: 1fr auto; gap: .7rem; align-items: center; margin-top: .65rem; padding: .65rem; border-radius: 9px; background: var(--sim-soft); }
    .sim-quota-preview span { color: var(--sim-muted-text); font-size: .67rem; }
    .sim-quota-preview strong { font-size: .9rem; }

    .sim-product-tools { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .5rem; align-items: center; }
    .sim-segmented { display: inline-grid; grid-template-columns: 1fr 1fr; padding: 3px; border: 1px solid var(--sim-border); border-radius: 9px; background: var(--sim-soft); }
    .sim-segmented button { min-height: 36px; padding: .4rem .58rem; border: 0; border-radius: 7px; color: var(--sim-secondary); background: transparent; font: inherit; font-size: .66rem; font-weight: 750; cursor: pointer; }
    .sim-segmented button.is-active { color: var(--sim-green); background: var(--sim-surface); box-shadow: var(--shadow-xs); }
    .sim-selection-status { display: flex; gap: .45rem; align-items: center; justify-content: space-between; margin: .62rem 0 .45rem; color: var(--sim-muted-text); font-size: .68rem; }
    .sim-selection-status strong { color: var(--sim-text); }
    .sim-product-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: .42rem; }
    .sim-product-option {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .48rem;
        align-items: center;
        min-width: 0;
        min-height: 62px;
        padding: .56rem;
        border: 1px solid var(--sim-border);
        border-radius: 9px;
        color: var(--sim-text);
        background: var(--sim-surface);
        text-align: left;
        cursor: pointer;
    }
    .sim-product-option.is-selected { border-color: var(--sim-green); background: var(--sim-green-soft); }
    .sim-product-option-icon { display: grid; width: 34px; height: 34px; place-items: center; border-radius: 9px; color: var(--sim-green); background: var(--sim-green-soft); }
    .sim-product-option-copy { min-width: 0; }
    .sim-product-option-copy strong { display: block; overflow: hidden; font-size: .72rem; text-overflow: ellipsis; white-space: nowrap; }
    .sim-product-option-copy small { display: block; margin-top: .1rem; color: var(--sim-muted-text); font-size: .61rem; }
    .sim-product-option-check { color: var(--sim-green); font-size: 1.05rem; }
    .sim-product-badge { display: inline-flex; width: max-content; margin-top: .16rem; padding: .08rem .26rem; border-radius: 999px; color: var(--sim-green); background: var(--sim-green-soft); font-size: .55rem; font-weight: 800; }

    .sim-summary {
        position: sticky;
        z-index: 4;
        top: calc(var(--app-header-rendered-height, 58px) + .35rem);
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .45rem;
        margin-bottom: .65rem;
        padding: .62rem;
        border: 1px solid var(--sim-border);
        border-radius: 10px;
        background: color-mix(in srgb, var(--sim-surface) 94%, transparent);
        box-shadow: var(--shadow-sm);
        backdrop-filter: blur(12px);
    }
    .sim-summary.is-over { border-color: var(--sim-red); }
    .sim-summary-item span { display: block; color: var(--sim-muted-text); font-size: .61rem; }
    .sim-summary-item strong { display: block; margin-top: .12rem; font-size: .82rem; font-variant-numeric: tabular-nums; }
    .sim-summary-item.balance strong { color: var(--sim-green); }
    .sim-summary.is-over .sim-summary-item.balance strong { color: var(--sim-red); }
    .sim-progress { grid-column: 1 / -1; height: 7px; overflow: hidden; border-radius: 999px; background: var(--sim-border); }
    .sim-progress span { display: block; width: 0; height: 100%; border-radius: inherit; background: var(--sim-green); transition: width .18s ease; }
    .sim-summary.is-over .sim-progress span { background: var(--sim-red); }

    .sim-allocation-list { display: grid; gap: .48rem; }
    .sim-allocation {
        display: grid;
        grid-template-columns: minmax(170px, 1.1fr) minmax(150px, .9fr) minmax(170px, 1fr) auto;
        gap: .55rem;
        align-items: end;
        padding: .68rem;
        border: 1px solid var(--sim-border);
        border-radius: 10px;
        background: var(--sim-surface);
    }
    .sim-allocation-product { align-self: center; min-width: 0; }
    .sim-allocation-product strong { display: block; font-size: .76rem; overflow-wrap: anywhere; }
    .sim-allocation-product span { display: block; margin-top: .1rem; color: var(--sim-muted-text); font-size: .62rem; }
    .sim-allocation-quantity { display: grid; grid-template-columns: minmax(0, 1fr) 92px; gap: .4rem; align-items: center; }
    .sim-range { width: 100%; accent-color: var(--sim-green); }
    .sim-row-total { align-self: center; color: var(--sim-green); font-size: .82rem; font-weight: 850; white-space: nowrap; }
    .sim-row-actions { display: flex; gap: .28rem; margin-top: .38rem; }
    .sim-small-button { min-height: 32px; padding: .36rem .48rem; border: 1px solid var(--sim-border); border-radius: 7px; color: var(--sim-secondary); background: var(--sim-surface); font: inherit; font-size: .62rem; font-weight: 750; cursor: pointer; }
    .sim-small-button.danger { color: var(--sim-red); }
    .sim-allocation-note { grid-column: 1 / -1; margin-top: -.2rem; color: var(--sim-muted-text); font-size: .61rem; }
    .sim-allocation-note.warning { color: var(--sim-amber); }

    .sim-empty { padding: 1.4rem .8rem; color: var(--sim-muted-text); text-align: center; font-size: .72rem; }
    .sim-feedback { margin-top: .55rem; color: var(--sim-red); font-size: .68rem; }
    .sim-feedback[hidden] { display: none; }

    .sim-action-bar {
        position: fixed;
        z-index: calc(var(--app-layer-navigation, 40) + 1);
        right: 0;
        bottom: 0;
        left: 0;
        padding: .55rem max(.75rem, env(safe-area-inset-right)) calc(.55rem + env(safe-area-inset-bottom)) max(.75rem, env(safe-area-inset-left));
        border-top: 1px solid var(--sim-border);
        background: color-mix(in srgb, var(--sim-surface) 95%, transparent);
        box-shadow: 0 -8px 24px rgba(18, 45, 29, .08);
        backdrop-filter: blur(14px);
    }
    .sim-action-inner { display: flex; width: min(100%, 980px); gap: .5rem; align-items: center; justify-content: space-between; margin: 0 auto; }
    .sim-button { display: inline-flex; min-height: 44px; gap: .38rem; align-items: center; justify-content: center; padding: .58rem .78rem; border: 1px solid var(--sim-border); border-radius: 9px; color: var(--sim-text); background: var(--sim-surface); font: inherit; font-size: .74rem; font-weight: 780; text-decoration: none; cursor: pointer; }
    .sim-button.primary { border-color: var(--sim-green); color: #fff; background: var(--sim-green); }
    .sim-button:disabled { opacity: .5; cursor: not-allowed; }
    .sim-button[hidden] { display: none; }

    .sim-toast { position: fixed; z-index: 90; right: 1rem; bottom: 5.2rem; max-width: min(360px, calc(100vw - 2rem)); padding: .65rem .75rem; border: 1px solid var(--sim-border); border-radius: 9px; color: var(--sim-text); background: var(--sim-surface); box-shadow: var(--shadow-lg); font-size: .72rem; }
    .sim-toast[hidden] { display: none; }

    @media (max-width: 720px) {
        .sim-intro-grid { grid-template-columns: 1fr; }
        .sim-choice-grid { grid-template-columns: 1fr; }
        .sim-product-tools { grid-template-columns: 1fr; }
        .sim-allocation { grid-template-columns: minmax(0, 1fr) auto; }
        .sim-allocation .sim-field { grid-column: 1 / -1; }
        .sim-allocation-product { grid-column: 1; }
        .sim-row-total { grid-column: 2; grid-row: 1; }
        .sim-allocation-note { grid-column: 1 / -1; }
    }

    @media (max-width: 520px) {
        .delivery-simulator { padding-bottom: 5.7rem; }
        .sim-header { padding: .62rem; }
        .sim-header-copy h1 { font-size: .9rem; }
        .sim-summary { grid-template-columns: 1fr 1fr; }
        .sim-summary-item.balance { grid-column: 1 / -1; }
        .sim-product-list { grid-template-columns: 1fr; }
        .sim-history-list { grid-template-columns: 1fr 1fr; }
        .sim-action-inner .sim-button { flex: 1; }
        .sim-action-inner .sim-button.primary { flex: 1.4; }
    }
</style>
@endpush

@section('content')
<main
    class="delivery-simulator"
    id="delivery-simulator"
    data-endpoint="{{ route('associate.projects.data', ['tenant' => $tenantSlug, 'project' => $project->id, 'section' => 'simulator']) }}"
    data-history-key="sgc.simulations.{{ $historyScope }}.v1"
>
    <header class="sim-header">
        <a class="sim-back" href="{{ route('associate.projects.show', ['tenant' => $tenantSlug, 'project' => $project->id]) }}" aria-label="Voltar ao projeto">
            <i class="ph ph-arrow-left"></i>
        </a>
        <div class="sim-header-copy">
            <h1>Simular entrega</h1>
            <p>{{ $project->title }}</p>
        </div>
        <button class="sim-icon-button" type="button" id="sim-restart" aria-label="Recomeçar simulação" title="Recomeçar">
            <i class="ph ph-arrow-counter-clockwise"></i>
        </button>
    </header>

    <div class="sim-steps" aria-label="Progresso da simulação">
        @foreach(range(0, 3) as $step)
            <span class="sim-step-marker" data-step-marker="{{ $step }}"><span></span></span>
        @endforeach
    </div>

    <section class="sim-panel" data-step-panel="0">
        <div class="sim-panel-head">
            <span class="sim-eyebrow">Antes de começar</span>
            <h2>Planeje uma combinação de produtos</h2>
            <p>Use o valor disponível no projeto ou informe outro valor para comparar quantidades e preços.</p>
        </div>
        <div class="sim-intro-grid">
            <article class="sim-intro-item"><span class="sim-intro-number">1</span><div><strong>Defina o valor</strong><span>Comece pelo saldo disponível ou use um valor personalizado.</span></div></article>
            <article class="sim-intro-item"><span class="sim-intro-number">2</span><div><strong>Escolha os produtos</strong><span>Os liberados para entrega aparecem primeiro.</span></div></article>
            <article class="sim-intro-item"><span class="sim-intro-number">3</span><div><strong>Ajuste as quantidades</strong><span>Use os controles até chegar à combinação desejada.</span></div></article>
        </div>
        <div class="sim-history" id="sim-history" hidden>
            <div class="sim-history-head"><h3>Simulações salvas neste dispositivo</h3><button class="sim-small-button danger" type="button" id="sim-clear-history">Limpar</button></div>
            <div class="sim-history-list" id="sim-history-list"></div>
        </div>
    </section>

    <section class="sim-panel" data-step-panel="1" hidden>
        <div class="sim-panel-head">
            <span class="sim-eyebrow">Passo 1 de 3</span>
            <h2>Qual valor deseja usar?</h2>
            <p>O saldo do projeto é a opção principal. Você poderá voltar e alterar este valor.</p>
        </div>
        <div class="sim-choice-grid">
            <button class="sim-choice" type="button" data-quota-mode="remaining">
                <span class="sim-choice-icon"><i class="ph-duotone ph-wallet"></i></span>
                <span><strong>Usar valor disponível</strong><small id="sim-remaining-label">Carregando...</small></span>
            </button>
            <button class="sim-choice" type="button" data-quota-mode="custom">
                <span class="sim-choice-icon"><i class="ph-duotone ph-pencil-simple-line"></i></span>
                <span><strong>Usar outro valor</strong><small>Para testar uma possibilidade diferente.</small></span>
            </button>
        </div>
        <div class="sim-field" id="sim-custom-quota-field" hidden>
            <label for="sim-custom-quota">Valor para a simulação</label>
            <input class="sim-input" id="sim-custom-quota" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="0,00">
        </div>
        <div class="sim-quota-preview"><span>Valor escolhido</span><strong id="sim-quota-preview">R$ 0,00</strong></div>
        <p class="sim-feedback" id="sim-quota-error" hidden></p>
    </section>

    <section class="sim-panel" data-step-panel="2" hidden>
        <div class="sim-panel-head">
            <span class="sim-eyebrow">Passo 2 de 3</span>
            <h2>Escolha um ou mais produtos</h2>
            <p>Produtos liberados para suas entregas aparecem primeiro. A busca também encontra outros produtos precificados.</p>
        </div>
        <div class="sim-product-tools">
            <input class="sim-search" id="sim-product-search" type="search" autocomplete="off" placeholder="Buscar produto...">
            <div class="sim-segmented" aria-label="Filtrar produtos">
                <button class="is-active" type="button" data-product-filter="enabled">Liberados</button>
                <button type="button" data-product-filter="all">Todos</button>
            </div>
        </div>
        <div class="sim-selection-status"><span><strong id="sim-selected-count">0</strong> selecionado(s)</span><span id="sim-product-result-count"></span></div>
        <div class="sim-product-list" id="sim-product-list"></div>
        <p class="sim-feedback" id="sim-products-error" hidden></p>
    </section>

    <section class="sim-panel" data-step-panel="3" hidden>
        <div class="sim-panel-head">
            <span class="sim-eyebrow">Passo 3 de 3</span>
            <h2>Ajuste as quantidades</h2>
            <p>Arraste o controle ou digite a quantidade. O total é atualizado na hora.</p>
        </div>
        <div class="sim-summary" id="sim-summary">
            <div class="sim-summary-item"><span>Valor escolhido</span><strong id="sim-budget">R$ 0,00</strong></div>
            <div class="sim-summary-item"><span>Total simulado</span><strong id="sim-total">R$ 0,00</strong></div>
            <div class="sim-summary-item balance"><span id="sim-balance-label">Saldo</span><strong id="sim-balance">R$ 0,00</strong></div>
            <div class="sim-progress"><span id="sim-progress"></span></div>
        </div>
        <div class="sim-allocation-list" id="sim-allocation-list"></div>
    </section>
</main>

<div class="sim-action-bar" aria-label="Ações da simulação">
    <div class="sim-action-inner">
        <button class="sim-button" type="button" id="sim-previous"><i class="ph ph-arrow-left"></i> Voltar</button>
        <button class="sim-button primary" type="button" id="sim-next">Começar <i class="ph ph-arrow-right"></i></button>
        <button class="sim-button primary" type="button" id="sim-save" hidden><i class="ph ph-image-square"></i> Salvar imagem</button>
    </div>
</div>
<div class="sim-toast" id="sim-toast" role="status" hidden></div>
@endsection

@push('scripts')
<script>
(() => {
    'use strict';

    const root = document.getElementById('delivery-simulator');
    if (!root) return;

    const state = {
        step: 0,
        products: [],
        summary: {},
        quotaMode: 'remaining',
        customQuota: 0,
        productFilter: 'enabled',
        search: '',
        selected: new Map(),
        history: [],
    };
    const historyKey = root.dataset.historyKey;
    const money = value => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0));
    const quantity = value => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 }).format(Number(value || 0));
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
    const panels = [...document.querySelectorAll('[data-step-panel]')];
    const markers = [...document.querySelectorAll('[data-step-marker]')];
    const previousButton = document.getElementById('sim-previous');
    const nextButton = document.getElementById('sim-next');
    const saveButton = document.getElementById('sim-save');

    function quotaValue() {
        return state.quotaMode === 'remaining'
            ? Math.max(0, Number(state.summary.financial_remaining || 0))
            : Math.max(0, Number(state.customQuota || 0));
    }

    function destinationFor(entry, product) {
        return (product?.destinations || []).find(destination => Number(destination.customer_id) === Number(entry.destinationId))
            || [...(product?.destinations || [])].sort((left, right) => Number(right.price) - Number(left.price))[0]
            || null;
    }

    function productFor(productId) {
        return state.products.find(product => Number(product.product_id) === Number(productId));
    }

    function totals() {
        const rows = [...state.selected.values()].map(entry => {
            const product = productFor(entry.productId);
            const destination = destinationFor(entry, product);
            const price = Number(destination?.price || 0);
            const amount = Math.max(0, Number(entry.quantity || 0));
            return { entry, product, destination, price, quantity: amount, total: amount * price };
        }).filter(row => row.product && row.destination);
        return { rows, total: rows.reduce((sum, row) => sum + row.total, 0) };
    }

    function showToast(message) {
        const toast = document.getElementById('sim-toast');
        toast.textContent = message;
        toast.hidden = false;
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => { toast.hidden = true; }, 3200);
    }

    function renderStep() {
        panels.forEach(panel => { panel.hidden = Number(panel.dataset.stepPanel) !== state.step; });
        markers.forEach(marker => marker.classList.toggle('is-complete', Number(marker.dataset.stepMarker) <= state.step));
        previousButton.hidden = state.step === 0;
        nextButton.hidden = state.step === 3;
        saveButton.hidden = state.step !== 3;
        nextButton.innerHTML = state.step === 0
            ? 'Começar <i class="ph ph-arrow-right"></i>'
            : state.step === 2
                ? 'Simular <i class="ph ph-arrow-right"></i>'
                : 'Continuar <i class="ph ph-arrow-right"></i>';
        if (state.step === 1) renderQuota();
        if (state.step === 2) renderProducts();
        if (state.step === 3) renderSimulation();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function renderQuota() {
        const remaining = state.summary.financial_remaining;
        const remainingButton = document.querySelector('[data-quota-mode="remaining"]');
        remainingButton.disabled = remaining === null || Number(remaining) <= 0;
        remainingButton.classList.toggle('is-active', state.quotaMode === 'remaining');
        document.querySelector('[data-quota-mode="custom"]').classList.toggle('is-active', state.quotaMode === 'custom');
        document.getElementById('sim-remaining-label').textContent = remaining === null
            ? 'Este projeto não possui saldo financeiro definido.'
            : `${money(remaining)} disponíveis agora`;
        document.getElementById('sim-custom-quota-field').hidden = state.quotaMode !== 'custom';
        document.getElementById('sim-custom-quota').value = state.customQuota > 0 ? state.customQuota : '';
        document.getElementById('sim-quota-preview').textContent = money(quotaValue());
    }

    function renderProducts() {
        const search = state.search.trim().toLocaleLowerCase('pt-BR');
        const filtered = state.products.filter(product => {
            const matchesFilter = search !== '' || state.productFilter === 'all' || product.delivery_enabled;
            const matchesSearch = !search || `${product.product_name} ${product.unit}`.toLocaleLowerCase('pt-BR').includes(search);
            return matchesFilter && matchesSearch;
        });
        const visible = filtered.slice(0, 48);
        document.getElementById('sim-selected-count').textContent = state.selected.size;
        document.getElementById('sim-product-result-count').textContent = visible.length < filtered.length
            ? `${visible.length} de ${filtered.length} produto(s)`
            : `${filtered.length} produto(s)`;
        document.querySelectorAll('[data-product-filter]').forEach(button => button.classList.toggle('is-active', button.dataset.productFilter === state.productFilter));
        document.getElementById('sim-product-list').innerHTML = visible.length ? visible.map(product => {
            const productId = Number(product.product_id);
            const selected = state.selected.has(productId);
            return `
                <button class="sim-product-option ${selected ? 'is-selected' : ''}" type="button" data-product-id="${productId}" aria-pressed="${selected}">
                    <span class="sim-product-option-icon"><i class="ph-duotone ${product.delivery_enabled ? 'ph-check-circle' : 'ph-cube'}"></i></span>
                    <span class="sim-product-option-copy">
                        <strong>${escapeHtml(product.product_name)}</strong>
                        <small>${escapeHtml(product.price_label)} · ${escapeHtml(product.unit || 'un')}</small>
                        ${product.delivery_enabled ? '<span class="sim-product-badge">Liberado para entrega</span>' : ''}
                    </span>
                    <i class="ph ${selected ? 'ph-check-circle-fill' : 'ph-circle'} sim-product-option-check"></i>
                </button>`;
        }).join('') : '<div class="sim-empty">Nenhum produto encontrado neste filtro.</div>';
    }

    function renderSimulation() {
        const result = totals();
        const budget = quotaValue();
        const balance = budget - result.total;
        const summary = document.getElementById('sim-summary');
        summary.classList.toggle('is-over', balance < -.005);
        document.getElementById('sim-budget').textContent = money(budget);
        document.getElementById('sim-total').textContent = money(result.total);
        document.getElementById('sim-balance-label').textContent = balance < 0 ? 'Valor excedente' : 'Saldo';
        document.getElementById('sim-balance').textContent = money(Math.abs(balance));
        document.getElementById('sim-progress').style.width = `${budget > 0 ? Math.min(100, result.total / budget * 100) : 0}%`;
        document.getElementById('sim-allocation-list').innerHTML = result.rows.map(row => {
            const maxByBudget = row.price > 0 ? Math.max(row.quantity, budget / row.price) : row.quantity;
            const options = (row.product.destinations || []).map(destination => `<option value="${Number(destination.customer_id)}" ${Number(destination.customer_id) === Number(row.destination.customer_id) ? 'selected' : ''}>${escapeHtml(destination.customer)} · ${money(destination.price)}</option>`).join('');
            const remaining = row.product.remaining_quantity;
            const exceedsConfigured = row.product.delivery_enabled && remaining !== null && remaining !== undefined && row.quantity > Number(remaining) + .0005;
            return `
                <article class="sim-allocation" data-allocation="${Number(row.product.product_id)}">
                    <div class="sim-allocation-product"><strong>${escapeHtml(row.product.product_name)}</strong><span>${money(row.price)} por ${escapeHtml(row.product.unit || 'un')}</span><div class="sim-row-actions"><button class="sim-small-button" type="button" data-fill-product="${Number(row.product.product_id)}">Usar saldo</button><button class="sim-small-button danger" type="button" data-remove-product="${Number(row.product.product_id)}">Remover</button></div></div>
                    <div class="sim-field"><label>Preço e destino</label><select class="sim-select" data-destination-product="${Number(row.product.product_id)}">${options}</select></div>
                    <div class="sim-field"><label>Quantidade (${escapeHtml(row.product.unit || 'un')})</label><div class="sim-allocation-quantity"><input class="sim-range" type="range" min="0" max="${maxByBudget || 1}" step="0.001" value="${row.quantity}" data-range-product="${Number(row.product.product_id)}"><input class="sim-input" type="number" min="0" step="0.001" inputmode="decimal" value="${row.quantity || ''}" data-quantity-product="${Number(row.product.product_id)}"></div></div>
                    <strong class="sim-row-total" data-row-total="${Number(row.product.product_id)}">${money(row.total)}</strong>
                    <div class="sim-allocation-note ${exceedsConfigured ? 'warning' : ''}" data-row-note="${Number(row.product.product_id)}">${row.product.delivery_enabled && remaining !== null ? `${quantity(remaining)} ${escapeHtml(row.product.unit || '')} disponíveis na configuração atual.${exceedsConfigured ? ' Esta simulação está acima desse valor.' : ''}` : 'Cenário livre para comparação.'}</div>
                </article>`;
        }).join('');
    }

    function refreshSimulationValues() {
        const result = totals();
        const budget = quotaValue();
        const balance = budget - result.total;
        const summary = document.getElementById('sim-summary');
        summary?.classList.toggle('is-over', balance < -.005);
        document.getElementById('sim-total').textContent = money(result.total);
        document.getElementById('sim-balance-label').textContent = balance < 0 ? 'Valor excedente' : 'Saldo';
        document.getElementById('sim-balance').textContent = money(Math.abs(balance));
        document.getElementById('sim-progress').style.width = `${budget > 0 ? Math.min(100, result.total / budget * 100) : 0}%`;
        result.rows.forEach(row => {
            const total = document.querySelector(`[data-row-total="${Number(row.product.product_id)}"]`);
            if (total) total.textContent = money(row.total);
            const note = document.querySelector(`[data-row-note="${Number(row.product.product_id)}"]`);
            const remaining = row.product.remaining_quantity;
            const exceeds = row.product.delivery_enabled && remaining !== null && remaining !== undefined && row.quantity > Number(remaining) + .0005;
            if (note) {
                note.classList.toggle('warning', exceeds);
                note.textContent = row.product.delivery_enabled && remaining !== null
                    ? `${quantity(remaining)} ${row.product.unit || ''} disponíveis na configuração atual.${exceeds ? ' Esta simulação está acima desse valor.' : ''}`
                    : 'Cenário livre para comparação.';
            }
        });
    }

    function updateQuantity(productId, value) {
        const entry = state.selected.get(Number(productId));
        if (!entry) return;
        entry.quantity = value === '' ? 0 : Math.max(0, Number(String(value).replace(',', '.')) || 0);
        const range = document.querySelector(`[data-range-product="${Number(productId)}"]`);
        const input = document.querySelector(`[data-quantity-product="${Number(productId)}"]`);
        if (range && document.activeElement !== range) range.value = entry.quantity;
        if (input && document.activeElement !== input) input.value = entry.quantity || '';
        refreshSimulationValues();
    }

    function validateCurrentStep() {
        document.getElementById('sim-quota-error').hidden = true;
        document.getElementById('sim-products-error').hidden = true;
        if (state.step === 1 && quotaValue() <= 0) {
            const error = document.getElementById('sim-quota-error');
            error.textContent = 'Informe um valor maior que zero para continuar.';
            error.hidden = false;
            return false;
        }
        if (state.step === 2 && state.selected.size === 0) {
            const error = document.getElementById('sim-products-error');
            error.textContent = 'Selecione pelo menos um produto.';
            error.hidden = false;
            return false;
        }
        return true;
    }

    function readHistory() {
        try {
            const parsed = JSON.parse(localStorage.getItem(historyKey) || '[]');
            const cutoff = Date.now() - (30 * 24 * 60 * 60 * 1000);
            state.history = Array.isArray(parsed) ? parsed.filter(item =>
                Number(item.createdAt) >= cutoff
                && /^[a-z0-9-]+$/i.test(String(item.id || ''))
                && /^data:image\/(?:webp|png);base64,[a-z0-9+/=]+$/i.test(String(item.image || ''))
                && Array.isArray(item.rows)
            ).slice(0, 4) : [];
        } catch (_) { state.history = []; }
        renderHistory();
    }

    function persistHistory() {
        try {
            localStorage.setItem(historyKey, JSON.stringify(state.history.slice(0, 4)));
            return true;
        } catch (_) {
            showToast('Não foi possível salvar a imagem neste dispositivo.');
            return false;
        }
    }

    function renderHistory() {
        const section = document.getElementById('sim-history');
        section.hidden = state.history.length === 0;
        document.getElementById('sim-history-list').innerHTML = state.history.map(item => `
            <article class="sim-history-card">
                <img src="${item.image}" alt="Prévia da simulação salva">
                <div class="sim-history-card-body"><strong>${money(item.total)} em ${item.rows.length} produto(s)</strong><span>${new Date(item.createdAt).toLocaleString('pt-BR')}</span><div class="sim-history-actions"><button class="sim-small-button" type="button" data-open-history="${item.id}">Abrir</button><button class="sim-small-button" type="button" data-download-history="${item.id}">Baixar</button><button class="sim-small-button danger" type="button" data-delete-history="${item.id}"><i class="ph ph-trash"></i></button></div></div>
            </article>`).join('');
    }

    function snapshotImage(result, budget) {
        const visibleRows = result.rows.slice(0, 12);
        const canvas = document.createElement('canvas');
        canvas.width = 900;
        canvas.height = 300 + (visibleRows.length * 62) + (result.rows.length > 12 ? 42 : 0);
        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff'; context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#168a4d'; context.fillRect(0, 0, canvas.width, 12);
        context.fillStyle = '#102018'; context.font = '700 30px Arial'; context.fillText('Simulação de entrega', 48, 66);
        context.fillStyle = '#52645a'; context.font = '18px Arial'; context.fillText(@json($project->title), 48, 98);
        context.fillStyle = '#f4f8f5'; context.fillRect(48, 128, 804, 104);
        context.fillStyle = '#52645a'; context.font = '16px Arial'; context.fillText('Valor escolhido', 72, 162); context.fillText('Total simulado', 330, 162); context.fillText('Saldo', 594, 162);
        context.fillStyle = '#102018'; context.font = '700 22px Arial'; context.fillText(money(budget), 72, 199); context.fillText(money(result.total), 330, 199); context.fillText(money(Math.abs(budget - result.total)), 594, 199);
        visibleRows.forEach((row, index) => {
            const y = 275 + (index * 62);
            context.strokeStyle = '#dce6df'; context.beginPath(); context.moveTo(48, y + 34); context.lineTo(852, y + 34); context.stroke();
            context.fillStyle = '#102018'; context.font = '700 17px Arial'; context.fillText(row.product.product_name.slice(0, 42), 48, y);
            context.fillStyle = '#52645a'; context.font = '16px Arial'; context.fillText(`${quantity(row.quantity)} ${row.product.unit || 'un'}`, 500, y);
            context.fillStyle = '#168a4d'; context.font = '700 17px Arial'; context.textAlign = 'right'; context.fillText(money(row.total), 852, y); context.textAlign = 'left';
        });
        if (result.rows.length > 12) { context.fillStyle = '#52645a'; context.font = '16px Arial'; context.fillText(`+ ${result.rows.length - 12} produto(s)`, 48, canvas.height - 22); }
        return canvas.toDataURL('image/webp', .76);
    }

    function saveSnapshot() {
        const result = totals();
        if (!result.rows.length) return;
        const item = {
            id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
            createdAt: Date.now(),
            quotaMode: state.quotaMode,
            customQuota: state.customQuota,
            budget: quotaValue(),
            total: result.total,
            rows: result.rows.map(row => ({ productId: Number(row.product.product_id), destinationId: Number(row.destination.customer_id), quantity: row.quantity })),
            image: snapshotImage(result, quotaValue()),
        };
        state.history = [item, ...state.history].slice(0, 4);
        if (persistHistory()) { renderHistory(); showToast('Simulação salva neste dispositivo.'); }
    }

    function restoreHistory(item) {
        state.quotaMode = 'custom';
        state.customQuota = Number(item.budget || item.customQuota || 0);
        state.selected.clear();
        item.rows.forEach(row => {
            if (productFor(row.productId)) state.selected.set(Number(row.productId), { productId: Number(row.productId), destinationId: Number(row.destinationId), quantity: Number(row.quantity || 0) });
        });
        state.step = 3;
        renderStep();
    }

    function downloadImage(item) {
        const link = document.createElement('a');
        link.href = item.image;
        const extension = item.image.startsWith('data:image/png') ? 'png' : 'webp';
        link.download = `simulacao-entrega-${new Date(item.createdAt).toISOString().slice(0, 10)}.${extension}`;
        link.click();
    }

    root.addEventListener('click', event => {
        const quotaButton = event.target.closest('[data-quota-mode]');
        if (quotaButton && !quotaButton.disabled) { state.quotaMode = quotaButton.dataset.quotaMode; renderQuota(); }
        const filterButton = event.target.closest('[data-product-filter]');
        if (filterButton) { state.productFilter = filterButton.dataset.productFilter; renderProducts(); }
        const productButton = event.target.closest('[data-product-id]');
        if (productButton) {
            const productId = Number(productButton.dataset.productId);
            if (state.selected.has(productId)) state.selected.delete(productId);
            else {
                const product = productFor(productId);
                const destination = [...(product?.destinations || [])].sort((left, right) => Number(right.price) - Number(left.price))[0];
                if (destination) state.selected.set(productId, { productId, destinationId: Number(destination.customer_id), quantity: 0 });
            }
            renderProducts();
        }
        const fillButton = event.target.closest('[data-fill-product]');
        if (fillButton) {
            const productId = Number(fillButton.dataset.fillProduct);
            const result = totals();
            const row = result.rows.find(item => Number(item.entry.productId) === productId);
            if (row?.price > 0) {
                const otherTotal = result.total - row.total;
                let amount = Math.max(0, (quotaValue() - otherTotal) / row.price);
                if (row.product.delivery_enabled && row.product.remaining_quantity !== null) amount = Math.min(amount, Number(row.product.remaining_quantity));
                row.entry.quantity = Math.floor(amount * 1000) / 1000;
                renderSimulation();
            }
        }
        const removeButton = event.target.closest('[data-remove-product]');
        if (removeButton) { state.selected.delete(Number(removeButton.dataset.removeProduct)); renderSimulation(); }
    });

    root.addEventListener('input', event => {
        if (event.target.id === 'sim-custom-quota') { state.customQuota = Math.max(0, Number(String(event.target.value).replace(',', '.')) || 0); renderQuota(); }
        if (event.target.id === 'sim-product-search') { state.search = event.target.value || ''; renderProducts(); }
        if (event.target.matches('[data-range-product], [data-quantity-product]')) updateQuantity(event.target.dataset.rangeProduct || event.target.dataset.quantityProduct, event.target.value);
    });

    root.addEventListener('change', event => {
        if (event.target.matches('[data-destination-product]')) {
            const entry = state.selected.get(Number(event.target.dataset.destinationProduct));
            if (entry) { entry.destinationId = Number(event.target.value); renderSimulation(); }
        }
    });

    previousButton.addEventListener('click', () => { if (state.step > 0) { state.step -= 1; renderStep(); } });
    nextButton.addEventListener('click', () => { if (validateCurrentStep() && state.step < 3) { state.step += 1; renderStep(); } });
    saveButton.addEventListener('click', saveSnapshot);
    document.getElementById('sim-restart').addEventListener('click', () => { state.step = 0; state.selected.clear(); state.search = ''; state.productFilter = 'enabled'; renderStep(); });
    document.getElementById('sim-clear-history').addEventListener('click', () => { state.history = []; localStorage.removeItem(historyKey); renderHistory(); });
    document.getElementById('sim-history-list').addEventListener('click', event => {
        const open = event.target.closest('[data-open-history]');
        const download = event.target.closest('[data-download-history]');
        const remove = event.target.closest('[data-delete-history]');
        const id = open?.dataset.openHistory || download?.dataset.downloadHistory || remove?.dataset.deleteHistory;
        const item = state.history.find(historyItem => historyItem.id === id);
        if (open && item) restoreHistory(item);
        if (download && item) downloadImage(item);
        if (remove && item) { state.history = state.history.filter(historyItem => historyItem.id !== id); persistHistory(); renderHistory(); }
    });

    async function load() {
        nextButton.disabled = true;
        try {
            const response = await fetch(root.dataset.endpoint, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error('Não foi possível carregar os dados deste projeto.');
            const data = await response.json();
            state.products = Array.isArray(data.products) ? data.products : [];
            state.summary = data.summary || {};
            if (state.summary.financial_remaining === null || Number(state.summary.financial_remaining) <= 0) state.quotaMode = 'custom';
            readHistory();
            renderStep();
        } catch (error) {
            showToast(error.message || 'Não foi possível abrir o simulador.');
        } finally { nextButton.disabled = false; }
    }

    load();
})();
</script>
@endpush
