@extends('layouts.bento')

@section('title', $simulatorTitle ?? 'Simular entrega')
@section('page-title', $simulatorTitle ?? 'Simular entrega')
@section('page-subtitle', $simulatorSubtitle ?? $project->title)
@section('user-role', $simulatorRole ?? 'Associado')

@php
    $tenantSlug = request()->route('tenant') instanceof \App\Models\Tenant
        ? request()->route('tenant')->slug
        : request()->route('tenant');

    $bentoNavigation = $simulatorNavigation ?? \App\Support\PortalNavigation::make(
        'associate',
        null,
        $tenantSlug
    );

    $simulatorEndpoint ??= route('associate.projects.data', [
        'tenant' => $tenantSlug,
        'project' => $project->id,
        'section' => 'simulator',
    ]);
    $simulatorBackUrl ??= route('associate.projects.show', [
        'tenant' => $tenantSlug,
        'project' => $project->id,
    ]);
    $simulatorHeading ??= 'Simular entrega';
    $simulatorProjectLabel ??= $project->title;
@endphp

@push('styles')
<style>
    .delivery-simulator {
        --sim-green:#168a4d;
        --sim-green-soft:#eaf8ef;
        --sim-blue:#2563eb;
        --sim-blue-soft:#eef4ff;
        --sim-sky:#0284c7;
        --sim-sky-soft:#edf8fe;
        --sim-violet:#7c3aed;
        --sim-violet-soft:#f4f0ff;
        --sim-amber:#c87408;
        --sim-amber-soft:#fff7e8;
        --sim-red:#cf3f3f;
        --sim-red-soft:#fff0f0;
        --sim-slate:#64748b;
        --sim-slate-soft:#f1f5f9;

        --sim-text:var(--color-text,#102018);
        --sim-secondary:var(--color-text-secondary,#52645a);
        --sim-muted:var(--color-text-muted,#74857b);
        --sim-border:var(--color-border,#dce7e0);
        --sim-border-strong:var(--color-border-strong,#c8d6cd);
        --sim-surface:var(--color-surface,#fff);
        --sim-soft:var(--color-surface-soft,#f8faf9);

        display:grid;
        width:min(100%,1100px);
        min-width:0;
        grid-column:1 / -1;
        gap:.8rem;
        margin:0 auto;
        padding-bottom:7.2rem;
        color:var(--sim-text);
        font-size:16px;
    }

    .delivery-simulator *,
    .delivery-simulator *::before,
    .delivery-simulator *::after {
        box-sizing:border-box;
    }

    .delivery-simulator .ph,
    .delivery-simulator .ph-duotone {
        line-height:1;
    }

    /* ======================================================
       Cabeçalho
       ====================================================== */

    .sim-header {
        display:grid;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.75rem;
        align-items:center;
        min-height:76px;
        padding:.7rem .8rem;
        border:1px solid var(--sim-border);
        border-radius:16px;
        background:
            radial-gradient(circle at 100% 0,rgba(124,58,237,.08),transparent 16rem),
            linear-gradient(180deg,var(--sim-soft),#fff);
        box-shadow:0 4px 16px rgba(15,35,24,.04);
    }

    .sim-back,
    .sim-icon-button {
        display:inline-flex;
        width:46px;
        height:46px;
        align-items:center;
        justify-content:center;
        border:1px solid var(--sim-border-strong);
        border-radius:12px;
        background:#fff;
        color:var(--sim-secondary);
        cursor:pointer;
        font:inherit;
        text-decoration:none;
        transition:.16s ease;
    }

    .sim-back:hover,
    .sim-back:focus-visible,
    .sim-icon-button:hover,
    .sim-icon-button:focus-visible {
        border-color:rgba(37,99,235,.24);
        background:var(--sim-blue-soft);
        color:var(--sim-blue);
        outline:none;
    }

    .sim-back .ph,
    .sim-icon-button .ph {
        font-size:1.15rem;
    }

    .sim-header-copy {
        min-width:0;
    }

    .sim-header-copy h1 {
        margin:0;
        overflow:hidden;
        color:var(--sim-text);
        font-size:1.1rem;
        font-weight:900;
        letter-spacing:-.025em;
        line-height:1.25;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .sim-header-copy p {
        margin:.14rem 0 0;
        overflow:hidden;
        color:var(--sim-muted);
        font-size:.82rem;
        font-weight:600;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    /* ======================================================
       Progresso
       ====================================================== */

    .sim-steps {
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:.5rem;
        padding:0 .1rem;
    }

    .sim-step {
        display:grid;
        min-width:0;
        gap:.28rem;
    }

    .sim-step-bar {
        height:7px;
        overflow:hidden;
        border-radius:999px;
        background:var(--sim-border);
    }

    .sim-step-bar > span {
        display:block;
        width:0;
        height:100%;
        border-radius:inherit;
        background:linear-gradient(90deg,var(--sim-green),var(--sim-violet));
        transition:width .2s ease;
    }

    .sim-step.is-complete .sim-step-bar > span {
        width:100%;
    }

    .sim-step-label {
        overflow:hidden;
        color:var(--sim-muted);
        font-size:.74rem;
        font-weight:700;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .sim-step.is-current .sim-step-label {
        color:var(--sim-text);
        font-weight:850;
    }

    /* ======================================================
       Painéis
       ====================================================== */

    .sim-panel {
        min-width:0;
        overflow:hidden;
        border:1px solid var(--sim-border);
        border-radius:16px;
        background:#fff;
        box-shadow:0 4px 16px rgba(15,35,24,.04);
    }

    .sim-panel[hidden] {
        display:none !important;
    }

    .sim-panel-head {
        display:grid;
        grid-template-columns:auto minmax(0,1fr);
        gap:.75rem;
        align-items:start;
        padding:.9rem 1rem;
        border-bottom:1px solid var(--sim-border);
        background:linear-gradient(180deg,var(--sim-soft),#fff);
    }

    .sim-panel-head-icon {
        display:inline-flex;
        width:46px;
        height:46px;
        align-items:center;
        justify-content:center;
        border-radius:12px;
        background:var(--sim-violet-soft);
        color:var(--sim-violet);
    }

    .sim-panel-head-icon.blue {
        background:var(--sim-blue-soft);
        color:var(--sim-blue);
    }

    .sim-panel-head-icon.green {
        background:var(--sim-green-soft);
        color:var(--sim-green);
    }

    .sim-panel-head-icon .ph-duotone {
        font-size:1.35rem;
    }

    .sim-panel-head-copy {
        min-width:0;
    }

    .sim-eyebrow {
        display:block;
        color:var(--sim-violet);
        font-size:.7rem;
        font-weight:900;
        letter-spacing:.055em;
        text-transform:uppercase;
    }

    .sim-panel-head h2 {
        margin:.12rem 0 0;
        color:var(--sim-text);
        font-size:1.1rem;
        font-weight:900;
        line-height:1.3;
    }

    .sim-panel-head p {
        max-width:760px;
        margin:.3rem 0 0;
        color:var(--sim-secondary);
        font-size:.86rem;
        line-height:1.55;
    }

    .sim-panel-body {
        padding:1rem;
    }

    /* ======================================================
       Etapa 1 — Cota financeira
       ====================================================== */

    .sim-explainer {
        display:grid;
        grid-template-columns:auto minmax(0,1fr);
        gap:.65rem;
        align-items:start;
        margin-bottom:.8rem;
        padding:.75rem .8rem;
        border:1px solid rgba(37,99,235,.12);
        border-radius:12px;
        background:var(--sim-blue-soft);
    }

    .sim-explainer-icon {
        display:inline-flex;
        width:38px;
        height:38px;
        align-items:center;
        justify-content:center;
        border-radius:10px;
        background:#fff;
        color:var(--sim-blue);
    }

    .sim-explainer strong,
    .sim-explainer span {
        display:block;
    }

    .sim-explainer strong {
        color:var(--sim-text);
        font-size:.9rem;
        font-weight:850;
    }

    .sim-explainer span {
        margin-top:.18rem;
        color:var(--sim-secondary);
        font-size:.8rem;
        line-height:1.55;
    }

    .sim-quota-overview {
        display:grid;
        grid-template-columns:minmax(0,1fr) auto;
        gap:.75rem;
        align-items:center;
        margin-bottom:.8rem;
        padding:.85rem .9rem;
        border:1px solid rgba(22,138,77,.16);
        border-left:4px solid var(--sim-green);
        border-radius:12px;
        background:linear-gradient(90deg,var(--sim-green-soft),#fff 72%);
    }

    .sim-quota-overview-copy strong,
    .sim-quota-overview-copy span {
        display:block;
    }

    .sim-quota-overview-copy strong {
        font-size:.94rem;
        font-weight:850;
    }

    .sim-quota-overview-copy span {
        margin-top:.15rem;
        color:var(--sim-secondary);
        font-size:.78rem;
        line-height:1.45;
    }

    .sim-quota-overview-value {
        color:var(--sim-green);
        font-size:1.2rem;
        font-weight:950;
        font-variant-numeric:tabular-nums;
        white-space:nowrap;
    }

    .sim-choice-grid {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:.7rem;
    }

    .sim-choice {
        display:grid;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.65rem;
        align-items:center;
        min-width:0;
        min-height:88px;
        padding:.8rem;
        border:2px solid var(--sim-border);
        border-radius:13px;
        background:#fff;
        color:var(--sim-text);
        cursor:pointer;
        font:inherit;
        text-align:left;
        transition:.15s ease;
    }

    .sim-choice:hover,
    .sim-choice:focus-visible {
        border-color:rgba(124,58,237,.3);
        outline:none;
    }

    .sim-choice.is-active {
        border-color:var(--sim-violet);
        background:linear-gradient(90deg,var(--sim-violet-soft),#fff 82%);
        box-shadow:0 0 0 3px rgba(124,58,237,.06);
    }

    .sim-choice:disabled {
        cursor:not-allowed;
        opacity:.5;
    }

    .sim-choice-icon {
        display:inline-flex;
        width:48px;
        height:48px;
        align-items:center;
        justify-content:center;
        border-radius:12px;
        background:var(--sim-violet-soft);
        color:var(--sim-violet);
    }

    .sim-choice-icon .ph-duotone {
        font-size:1.35rem;
    }

    .sim-choice.is-active .sim-choice-icon {
        background:#fff;
    }

    .sim-choice-copy strong,
    .sim-choice-copy small {
        display:block;
    }

    .sim-choice-copy strong {
        font-size:.92rem;
        font-weight:850;
    }

    .sim-choice-copy small {
        margin-top:.18rem;
        color:var(--sim-secondary);
        font-size:.78rem;
        line-height:1.4;
    }

    .sim-choice-check {
        color:var(--sim-violet);
        font-size:1.35rem;
    }

    .sim-custom-card {
        display:grid;
        grid-template-columns:minmax(0,1fr) minmax(180px,.34fr);
        gap:.75rem;
        align-items:end;
        margin-top:.75rem;
        padding:.8rem;
        border:1px solid var(--sim-border);
        border-radius:12px;
        background:var(--sim-soft);
    }

    .sim-custom-card[hidden] {
        display:none !important;
    }

    .sim-field {
        min-width:0;
    }

    .sim-field label {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:.4rem;
        margin-bottom:.35rem;
        color:var(--sim-secondary);
        font-size:.8rem;
        font-weight:800;
    }

    .sim-input,
    .sim-select,
    .sim-search {
        width:100%;
        min-width:0;
        min-height:50px;
        padding:.7rem .8rem;
        border:1px solid var(--sim-border-strong);
        border-radius:11px;
        outline:none;
        background:#fff;
        color:var(--sim-text);
        font:inherit;
        font-size:.9rem;
        transition:border-color .14s ease,box-shadow .14s ease;
    }

    .sim-input:focus,
    .sim-select:focus,
    .sim-search:focus {
        border-color:var(--sim-violet);
        box-shadow:0 0 0 4px rgba(124,58,237,.08);
    }

    .sim-money-input,
    .sim-quantity-input {
        font-size:1rem;
        font-weight:900;
        font-variant-numeric:tabular-nums;
    }

    .sim-custom-preview {
        min-height:50px;
        padding:.5rem .65rem;
        border:1px solid var(--sim-border);
        border-radius:10px;
        background:#fff;
    }

    .sim-custom-preview span,
    .sim-custom-preview strong {
        display:block;
    }

    .sim-custom-preview span {
        color:var(--sim-muted);
        font-size:.72rem;
    }

    .sim-custom-preview strong {
        margin-top:.08rem;
        color:var(--sim-violet);
        font-size:1rem;
        font-weight:900;
    }

    .sim-note {
        display:grid;
        grid-template-columns:auto minmax(0,1fr);
        gap:.5rem;
        align-items:start;
        margin-top:.75rem;
        padding:.65rem .75rem;
        border:1px solid rgba(200,116,8,.15);
        border-radius:11px;
        background:var(--sim-amber-soft);
        color:#8a4a06;
        font-size:.8rem;
        line-height:1.5;
    }

    .sim-note[hidden] {
        display:none !important;
    }

    .sim-feedback {
        margin:.6rem 0 0;
        color:var(--sim-red);
        font-size:.82rem;
        font-weight:750;
    }

    .sim-feedback[hidden] {
        display:none;
    }

    /* ======================================================
       Histórico em JSON — sem imagem persistida
       ====================================================== */

    .sim-history {
        margin-top:.9rem;
        border:1px solid var(--sim-border);
        border-radius:12px;
        background:#fff;
    }

    .sim-history[hidden] {
        display:none !important;
    }

    .sim-history summary {
        display:grid;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.55rem;
        align-items:center;
        min-height:58px;
        padding:.6rem .7rem;
        cursor:pointer;
        list-style:none;
    }

    .sim-history summary::-webkit-details-marker {
        display:none;
    }

    .sim-history-icon {
        display:inline-flex;
        width:40px;
        height:40px;
        align-items:center;
        justify-content:center;
        border-radius:10px;
        background:var(--sim-slate-soft);
        color:var(--sim-slate);
    }

    .sim-history-copy strong,
    .sim-history-copy span {
        display:block;
    }

    .sim-history-copy strong {
        font-size:.86rem;
        font-weight:850;
    }

    .sim-history-copy span {
        margin-top:.1rem;
        color:var(--sim-muted);
        font-size:.74rem;
    }

    .sim-history-caret {
        color:var(--sim-muted);
        transition:transform .14s ease;
    }

    .sim-history[open] .sim-history-caret {
        transform:rotate(180deg);
    }

    .sim-history-body {
        padding:.65rem;
        border-top:1px solid var(--sim-border);
        background:var(--sim-soft);
    }

    .sim-history-toolbar {
        display:flex;
        justify-content:flex-end;
        margin-bottom:.5rem;
    }

    .sim-history-list {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:.5rem;
    }

    .sim-history-card {
        display:grid;
        gap:.45rem;
        padding:.65rem;
        border:1px solid var(--sim-border);
        border-radius:11px;
        background:#fff;
    }

    .sim-history-card-head {
        display:grid;
        grid-template-columns:auto minmax(0,1fr);
        gap:.5rem;
        align-items:center;
    }

    .sim-history-card-icon {
        display:inline-flex;
        width:38px;
        height:38px;
        align-items:center;
        justify-content:center;
        border-radius:10px;
        background:var(--sim-violet-soft);
        color:var(--sim-violet);
    }

    .sim-history-card strong,
    .sim-history-card span {
        display:block;
    }

    .sim-history-card strong {
        font-size:.82rem;
        font-weight:850;
    }

    .sim-history-card span {
        margin-top:.08rem;
        color:var(--sim-muted);
        font-size:.72rem;
        line-height:1.4;
    }

    .sim-history-actions {
        display:flex;
        gap:.35rem;
        flex-wrap:wrap;
    }

    /* ======================================================
       Produtos
       ====================================================== */

    .sim-product-tools {
        display:grid;
        grid-template-columns:minmax(0,1fr) auto;
        gap:.6rem;
        align-items:center;
        margin-bottom:.65rem;
    }

    .sim-search-wrap {
        position:relative;
    }

    .sim-search-wrap > .ph {
        position:absolute;
        top:50%;
        left:.8rem;
        color:var(--sim-muted);
        font-size:1rem;
        transform:translateY(-50%);
        pointer-events:none;
    }

    .sim-search {
        padding-left:2.4rem;
    }

    .sim-segmented {
        display:inline-grid;
        grid-template-columns:1fr 1fr;
        gap:3px;
        padding:4px;
        border:1px solid var(--sim-border);
        border-radius:11px;
        background:var(--sim-soft);
    }

    .sim-segmented button {
        min-height:42px;
        padding:.5rem .7rem;
        border:0;
        border-radius:8px;
        background:transparent;
        color:var(--sim-secondary);
        cursor:pointer;
        font:inherit;
        font-size:.8rem;
        font-weight:800;
        white-space:nowrap;
    }

    .sim-segmented button.is-active {
        background:#fff;
        color:var(--sim-violet);
        box-shadow:0 2px 7px rgba(15,35,24,.06);
    }

    .sim-selection-status {
        display:flex;
        gap:.5rem;
        align-items:center;
        justify-content:space-between;
        margin-bottom:.55rem;
        color:var(--sim-muted);
        font-size:.78rem;
    }

    .sim-selected-count {
        display:inline-flex;
        min-height:30px;
        align-items:center;
        gap:.3rem;
        padding:.25rem .5rem;
        border-radius:999px;
        background:var(--sim-violet-soft);
        color:var(--sim-violet);
        font-weight:850;
    }

    .sim-selected-fallback {
        margin-bottom:.7rem;
        padding:.7rem;
        border:1px solid var(--sim-border);
        border-radius:12px;
        background:var(--sim-soft);
    }

    .sim-selected-fallback-head {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:.5rem;
        margin-bottom:.5rem;
    }

    .sim-selected-fallback-head strong {
        font-size:.82rem;
        font-weight:850;
    }

    .sim-selected-fallback-head span {
        color:var(--sim-muted);
        font-size:.72rem;
    }

    .sim-selected-chips {
        display:flex;
        gap:.4rem;
        flex-wrap:wrap;
    }

    .sim-selected-chip {
        display:inline-flex;
        min-height:38px;
        align-items:center;
        gap:.35rem;
        padding:.35rem .5rem;
        border:1px solid rgba(124,58,237,.14);
        border-radius:999px;
        background:#fff;
        color:var(--sim-text);
        font-size:.78rem;
        font-weight:780;
    }

    .sim-selected-chip .ph-package {
        color:var(--sim-violet);
    }

    .sim-selected-chip button {
        display:inline-flex;
        width:26px;
        height:26px;
        align-items:center;
        justify-content:center;
        border:0;
        border-radius:50%;
        background:var(--sim-red-soft);
        color:var(--sim-red);
        cursor:pointer;
    }

    .sim-selected-empty {
        color:var(--sim-muted);
        font-size:.78rem;
        line-height:1.45;
    }

    .sim-product-list {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:.6rem;
    }

    .sim-product-option {
        display:grid;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.6rem;
        align-items:center;
        min-width:0;
        min-height:92px;
        padding:.7rem;
        border:2px solid var(--sim-border);
        border-radius:13px;
        background:#fff;
        color:var(--sim-text);
        cursor:pointer;
        font:inherit;
        text-align:left;
        transition:.15s ease;
    }

    .sim-product-option:hover,
    .sim-product-option:focus-visible {
        border-color:rgba(124,58,237,.28);
        outline:none;
    }

    .sim-product-option.is-selected {
        border-color:var(--sim-violet);
        background:linear-gradient(90deg,var(--sim-violet-soft),#fff 84%);
        box-shadow:0 0 0 3px rgba(124,58,237,.05);
    }

    .sim-product-option-icon {
        display:inline-flex;
        width:48px;
        height:48px;
        align-items:center;
        justify-content:center;
        border-radius:12px;
        background:var(--sim-green-soft);
        color:var(--sim-green);
    }

    .sim-product-option.is-selected .sim-product-option-icon {
        background:#fff;
        color:var(--sim-violet);
    }

    .sim-product-option-icon .ph-duotone {
        font-size:1.3rem;
    }

    .sim-product-option-copy {
        min-width:0;
    }

    .sim-product-option-copy strong,
    .sim-product-option-copy small {
        display:block;
    }

    .sim-product-option-copy strong {
        overflow:hidden;
        font-size:.92rem;
        font-weight:850;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .sim-product-option-copy small {
        margin-top:.15rem;
        color:var(--sim-secondary);
        font-size:.77rem;
        line-height:1.4;
    }

    .sim-product-badges {
        display:flex;
        gap:.3rem;
        flex-wrap:wrap;
        margin-top:.35rem;
    }

    .sim-product-badge {
        display:inline-flex;
        min-height:23px;
        align-items:center;
        padding:.12rem .35rem;
        border-radius:999px;
        background:var(--sim-green-soft);
        color:var(--sim-green);
        font-size:.68rem;
        font-weight:820;
    }

    .sim-product-badge.info {
        background:var(--sim-blue-soft);
        color:var(--sim-blue);
    }

    .sim-product-option-check {
        color:var(--sim-violet);
        font-size:1.35rem;
    }

    .sim-empty {
        grid-column:1 / -1;
        padding:1.2rem;
        border:1px dashed var(--sim-border-strong);
        border-radius:12px;
        background:var(--sim-soft);
        color:var(--sim-muted);
        font-size:.82rem;
        line-height:1.5;
        text-align:center;
    }

    /* ======================================================
       Modo de limites — opcional
       ====================================================== */

    .sim-limit-mode {
        margin-bottom:.75rem;
        padding:.75rem;
        border:1px solid var(--sim-border);
        border-radius:13px;
        background:var(--sim-soft);
    }

    .sim-limit-mode-head strong,
    .sim-limit-mode-head span {
        display:block;
    }

    .sim-limit-mode-head strong {
        font-size:.9rem;
        font-weight:900;
    }

    .sim-limit-mode-head span {
        margin-top:.16rem;
        color:var(--sim-secondary);
        font-size:.78rem;
        line-height:1.5;
    }

    .sim-limit-mode-grid {
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:.55rem;
        margin-top:.6rem;
    }

    .sim-mode-button {
        display:grid;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.5rem;
        align-items:center;
        min-height:78px;
        padding:.65rem;
        border:2px solid var(--sim-border);
        border-radius:12px;
        background:#fff;
        color:var(--sim-text);
        cursor:pointer;
        font:inherit;
        text-align:left;
    }

    .sim-mode-button.is-active {
        border-color:var(--sim-blue);
        background:linear-gradient(90deg,var(--sim-blue-soft),#fff 84%);
    }

    .sim-mode-button-icon {
        display:inline-flex;
        width:42px;
        height:42px;
        align-items:center;
        justify-content:center;
        border-radius:11px;
        background:var(--sim-blue-soft);
        color:var(--sim-blue);
    }

    .sim-mode-button strong,
    .sim-mode-button small {
        display:block;
    }

    .sim-mode-button strong {
        font-size:.86rem;
        font-weight:850;
    }

    .sim-mode-button small {
        margin-top:.12rem;
        color:var(--sim-secondary);
        font-size:.74rem;
        line-height:1.4;
    }

    .sim-mode-check {
        color:var(--sim-blue);
        font-size:1.25rem;
    }

    /* ======================================================
       Resumo da simulação
       ====================================================== */

    .sim-summary {
        position:sticky;
        z-index:4;
        top:calc(var(--app-header-rendered-height,58px) + .4rem);
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:.45rem;
        margin-bottom:.75rem;
        padding:.55rem;
        border:1px solid var(--sim-border);
        border-radius:13px;
        background:rgba(255,255,255,.97);
        box-shadow:0 8px 24px rgba(15,35,24,.08);
        backdrop-filter:blur(12px);
    }

    .sim-summary-item {
        min-width:0;
        padding:.55rem .6rem;
        border-radius:10px;
        background:var(--sim-soft);
    }

    .sim-summary-item span,
    .sim-summary-item strong {
        display:block;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .sim-summary-item span {
        color:var(--sim-muted);
        font-size:.72rem;
        font-weight:700;
    }

    .sim-summary-item strong {
        margin-top:.1rem;
        color:var(--sim-text);
        font-size:1rem;
        font-weight:950;
        font-variant-numeric:tabular-nums;
    }

    .sim-summary-item.available strong {
        color:var(--sim-green);
    }

    .sim-progress {
        grid-column:1 / -1;
        height:9px;
        overflow:hidden;
        border-radius:999px;
        background:var(--sim-border);
    }

    .sim-progress > span {
        display:block;
        width:0;
        height:100%;
        border-radius:inherit;
        background:linear-gradient(90deg,var(--sim-violet),var(--sim-green));
        transition:width .18s ease;
    }

    .sim-budget-rule {
        grid-column:1 / -1;
        display:flex;
        gap:.35rem;
        align-items:center;
        color:var(--sim-secondary);
        font-size:.74rem;
        line-height:1.4;
    }

    .sim-budget-rule .ph {
        color:var(--sim-green);
    }

    /* ======================================================
       Quantidades por produto
       ====================================================== */

    .sim-allocation-list {
        display:grid;
        gap:.7rem;
    }

    .sim-allocation {
        overflow:hidden;
        border:1px solid var(--sim-border);
        border-left:4px solid var(--sim-violet);
        border-radius:13px;
        background:#fff;
    }

    .sim-allocation-head {
        display:grid;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.55rem;
        align-items:center;
        min-height:68px;
        padding:.65rem .75rem;
        border-bottom:1px solid var(--sim-border);
        background:linear-gradient(90deg,var(--sim-violet-soft),#fff 76%);
    }

    .sim-allocation-icon {
        display:inline-flex;
        width:42px;
        height:42px;
        align-items:center;
        justify-content:center;
        border-radius:11px;
        background:#fff;
        color:var(--sim-violet);
    }

    .sim-allocation-icon .ph-duotone {
        font-size:1.15rem;
    }

    .sim-allocation-product {
        min-width:0;
    }

    .sim-allocation-product strong,
    .sim-allocation-product span {
        display:block;
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .sim-allocation-product strong {
        font-size:.92rem;
        font-weight:900;
    }

    .sim-allocation-product span {
        margin-top:.12rem;
        color:var(--sim-secondary);
        font-size:.76rem;
    }

    .sim-row-total {
        color:var(--sim-green);
        font-size:1rem;
        font-weight:950;
        font-variant-numeric:tabular-nums;
        white-space:nowrap;
    }

    .sim-allocation-body {
        display:grid;
        gap:.7rem;
        padding:.75rem;
    }

    .sim-capacity {
        display:grid;
        grid-template-columns:auto minmax(0,1fr) auto;
        gap:.55rem;
        align-items:center;
        min-height:66px;
        padding:.55rem .65rem;
        border:1px solid rgba(37,99,235,.14);
        border-radius:11px;
        background:var(--sim-blue-soft);
    }

    .sim-capacity-icon {
        display:inline-flex;
        width:38px;
        height:38px;
        align-items:center;
        justify-content:center;
        border-radius:10px;
        background:#fff;
        color:var(--sim-blue);
    }

    .sim-capacity-copy strong,
    .sim-capacity-copy span {
        display:block;
    }

    .sim-capacity-copy strong {
        color:var(--sim-blue);
        font-size:.86rem;
        font-weight:900;
    }

    .sim-capacity-copy span {
        margin-top:.12rem;
        color:var(--sim-secondary);
        font-size:.74rem;
        line-height:1.45;
    }

    .sim-capacity-value {
        color:var(--sim-blue);
        font-size:1.05rem;
        font-weight:950;
        font-variant-numeric:tabular-nums;
        white-space:nowrap;
    }

    .sim-limit-info {
        display:flex;
        gap:.4rem;
        align-items:flex-start;
        padding:.5rem .6rem;
        border:1px solid var(--sim-border);
        border-radius:10px;
        background:var(--sim-soft);
        color:var(--sim-secondary);
        font-size:.76rem;
        line-height:1.45;
    }

    .sim-limit-info .ph {
        margin-top:.08rem;
        color:var(--sim-amber);
    }

    .sim-allocation-grid {
        display:grid;
        grid-template-columns:minmax(200px,.9fr) minmax(0,1.1fr);
        gap:.6rem;
        align-items:end;
    }

    .sim-quantity-control {
        display:grid;
        grid-template-columns:minmax(0,1fr) 120px;
        gap:.5rem;
        align-items:center;
    }

    .sim-range {
        width:100%;
        min-height:38px;
        accent-color:var(--sim-violet);
    }

    .sim-allocation-actions {
        display:flex;
        gap:.4rem;
        justify-content:flex-end;
        flex-wrap:wrap;
    }

    .sim-small-button {
        display:inline-flex;
        min-height:40px;
        align-items:center;
        justify-content:center;
        gap:.28rem;
        padding:.42rem .62rem;
        border:1px solid var(--sim-border);
        border-radius:9px;
        background:#fff;
        color:var(--sim-secondary);
        cursor:pointer;
        font:inherit;
        font-size:.75rem;
        font-weight:800;
    }

    .sim-small-button.primary-soft {
        border-color:rgba(124,58,237,.15);
        background:var(--sim-violet-soft);
        color:var(--sim-violet);
    }

    .sim-small-button.danger {
        border-color:rgba(207,63,63,.12);
        background:var(--sim-red-soft);
        color:var(--sim-red);
    }

    /* ======================================================
       Barra fixa — botões visíveis
       ====================================================== */

    .sim-action-bar {
        position:fixed;
        z-index:calc(var(--app-layer-navigation,40) + 1);
        right:0;
        bottom:0;
        left:0;
        padding:
            .65rem
            max(.8rem,env(safe-area-inset-right))
            calc(.65rem + env(safe-area-inset-bottom))
            max(.8rem,env(safe-area-inset-left));
        border-top:1px solid var(--sim-border);
        background:rgba(255,255,255,.97);
        box-shadow:0 -10px 30px rgba(18,45,29,.12);
        backdrop-filter:blur(14px);
    }

    .sim-action-inner {
        display:flex;
        width:min(100%,1100px);
        gap:.5rem;
        align-items:center;
        justify-content:flex-end;
        margin:0 auto;
    }

    .sim-button {
        display:inline-flex;
        min-height:50px;
        align-items:center;
        justify-content:center;
        gap:.4rem;
        padding:.65rem .95rem;
        border:1px solid var(--sim-border);
        border-radius:11px;
        background:#fff;
        color:var(--sim-text);
        cursor:pointer;
        font:inherit;
        font-size:.84rem;
        font-weight:850;
        text-decoration:none;
        white-space:nowrap;
        transition:.14s ease;
    }

    .sim-button.back {
        margin-right:auto;
    }

    .sim-button.primary {
        border-color:var(--sim-violet);
        background:var(--sim-violet);
        color:#fff;
        box-shadow:0 6px 16px rgba(124,58,237,.2);
    }

    .sim-button.success {
        border-color:var(--sim-green);
        background:var(--sim-green);
        color:#fff;
        box-shadow:0 6px 16px rgba(22,138,77,.18);
    }

    .sim-button.share {
        border-color:var(--sim-blue);
        background:var(--sim-blue);
        color:#fff;
        box-shadow:0 6px 16px rgba(37,99,235,.18);
    }

    .sim-button:hover:not(:disabled),
    .sim-button:focus-visible:not(:disabled) {
        filter:brightness(.98);
        outline:none;
        transform:translateY(-1px);
    }

    .sim-button:disabled {
        cursor:not-allowed;
        opacity:.5;
    }

    .sim-button[hidden] {
        display:none !important;
    }

    .sim-toast {
        position:fixed;
        z-index:100;
        right:1rem;
        bottom:6.3rem;
        max-width:min(420px,calc(100vw - 2rem));
        padding:.75rem .85rem;
        border:1px solid var(--sim-border);
        border-radius:11px;
        background:#fff;
        color:var(--sim-text);
        box-shadow:0 14px 32px rgba(15,35,24,.18);
        font-size:.82rem;
        font-weight:700;
        line-height:1.45;
    }

    .sim-toast[hidden] {
        display:none !important;
    }

    /* ======================================================
       Responsivo
       ====================================================== */

    @media(max-width:760px) {
        .delivery-simulator {
            width:min(100%,calc(100dvw - 18px));
        }

        .sim-choice-grid,
        .sim-product-list,
        .sim-limit-mode-grid,
        .sim-allocation-grid {
            grid-template-columns:1fr;
        }

        .sim-product-tools {
            grid-template-columns:1fr;
        }

        .sim-segmented {
            width:100%;
        }

        .sim-history-list {
            grid-template-columns:1fr;
        }
    }

    @media(max-width:540px) {
        .delivery-simulator {
            width:min(100%,calc(100dvw - 12px));
            gap:.62rem;
            padding-bottom:8rem;
        }

        .sim-header {
            min-height:66px;
            padding:.55rem;
        }

        .sim-back,
        .sim-icon-button {
            width:44px;
            height:44px;
        }

        .sim-header-copy h1 {
            font-size:1rem;
        }

        .sim-header-copy p {
            font-size:.76rem;
        }

        .sim-step-label {
            font-size:.68rem;
        }

        .sim-panel-head {
            padding:.8rem;
        }

        .sim-panel-head-icon {
            width:44px;
            height:44px;
        }

        .sim-panel-head h2 {
            font-size:1rem;
        }

        .sim-panel-head p {
            font-size:.82rem;
        }

        .sim-panel-body {
            padding:.8rem;
        }

        .sim-quota-overview {
            grid-template-columns:1fr;
            gap:.3rem;
        }

        .sim-choice {
            min-height:84px;
        }

        .sim-custom-card {
            grid-template-columns:1fr;
        }

        .sim-selected-fallback {
            padding:.65rem;
        }

        .sim-product-option {
            min-height:88px;
        }

        .sim-summary {
            top:calc(var(--app-header-rendered-height,58px) + .2rem);
            grid-template-columns:1fr 1fr;
            padding:.45rem;
        }

        .sim-summary-item.available {
            grid-column:1 / -1;
        }

        .sim-summary-item strong {
            font-size:.95rem;
        }

        .sim-budget-rule {
            font-size:.7rem;
        }

        .sim-capacity {
            grid-template-columns:auto minmax(0,1fr);
        }

        .sim-capacity-value {
            grid-column:2;
            font-size:1rem;
        }

        .sim-quantity-control {
            grid-template-columns:minmax(0,1fr) 110px;
        }

        .sim-action-bar {
            padding:
                .5rem
                .45rem
                calc(.5rem + env(safe-area-inset-bottom));
        }

        .sim-action-inner {
            gap:.35rem;
        }

        .sim-button {
            min-height:52px;
            padding:.6rem .7rem;
            font-size:.8rem;
        }

        .sim-button.back {
            width:52px;
            min-width:52px;
            padding:0;
            font-size:0;
        }

        .sim-button.back .ph {
            font-size:1rem;
        }

        .sim-button.final-action {
            flex:1 1 0;
        }
    }

    @media(max-width:390px) {
        .sim-button.final-action span {
            font-size:.74rem;
        }
    }

    @media(prefers-reduced-motion:reduce) {
        .delivery-simulator *,
        .delivery-simulator *::before,
        .delivery-simulator *::after {
            animation-duration:.01ms !important;
            transition-duration:.01ms !important;
            scroll-behavior:auto !important;
        }
    }
</style>
<style>/* SIMULADOR — VISUAL SIMPLES / PROJECT WORKSPACE */.delivery-simulator{\n --sim-green:#168a4d;--sim-green-soft:#eaf8ef;--sim-blue:#2563eb;--sim-blue-soft:#eef4ff;--sim-sky:#0284c7;--sim-sky-soft:#edf8fe;--sim-violet:#7c3aed;--sim-violet-soft:#f4f0ff;--sim-amber:#c87408;--sim-amber-soft:#fff7e8;--sim-red:#cf3f3f;--sim-red-soft:#fff0f0;--sim-slate:#64748b;--sim-slate-soft:#f1f5f9;\n --sim-text:var(--color-text,#102018);--sim-secondary:var(--color-text-secondary,#52645a);--sim-muted:var(--color-text-muted,#809087);--sim-border:var(--color-border,#dce6df);--sim-border-strong:var(--color-border-strong,#c8d6cd);--sim-surface:var(--color-surface,#fff);--sim-soft:var(--color-surface-soft,#f8faf9);\n width:min(100%,1080px);gap:.72rem}\n.delivery-simulator .sim-header{min-height:68px;padding:.65rem .72rem;border:1px solid var(--sim-border);border-radius:15px;background:linear-gradient(180deg,var(--sim-soft),var(--sim-surface));box-shadow:var(--shadow-sm)}\n.delivery-simulator .sim-back,.delivery-simulator .sim-icon-button{width:40px;height:40px;border-radius:11px;background:#fff}\n.delivery-simulator .sim-header-copy h1{font-size:1rem}.delivery-simulator .sim-header-copy p{margin-top:.08rem;font-size:.7rem}\n.delivery-simulator .sim-steps{gap:.4rem;padding:0 .12rem}.delivery-simulator .sim-step{gap:.22rem}.delivery-simulator .sim-step-bar{height:4px;background:var(--sim-border)}.delivery-simulator .sim-step-bar>span{background:var(--sim-green)}\n.delivery-simulator .sim-step-label{font-size:.65rem;font-weight:700}.delivery-simulator .sim-step.is-current .sim-step-label{color:var(--sim-green)}\n.delivery-simulator .sim-panel{border:1px solid var(--sim-border);border-radius:15px;background:#fff;box-shadow:var(--shadow-sm)}\n.delivery-simulator .sim-panel-head{min-height:64px;padding:.68rem .76rem;gap:.58rem;background:linear-gradient(180deg,var(--sim-soft),var(--sim-surface))}\n.delivery-simulator .sim-panel-head-icon{width:40px;height:40px;border-radius:11px}.delivery-simulator .sim-panel-head-icon:not(.blue):not(.green){background:var(--sim-violet-soft);color:var(--sim-violet)}.delivery-simulator .sim-panel-head-icon.blue{background:var(--sim-blue-soft);color:var(--sim-blue)}.delivery-simulator .sim-panel-head-icon.green{background:var(--sim-green-soft);color:var(--sim-green)}\n.delivery-simulator .sim-eyebrow{display:none}.delivery-simulator .sim-panel-head h2{margin:0;font-size:.95rem;font-weight:840}.delivery-simulator .sim-panel-head p{margin-top:.08rem;font-size:.72rem;line-height:1.4}.delivery-simulator .sim-panel-body{padding:.72rem}\n.delivery-simulator .sim-explainer{display:none!important}\n.delivery-simulator .sim-quota-overview{margin:0 0 .62rem;padding:.6rem .68rem;border:0;border-radius:10px;background:var(--sim-soft)}.delivery-simulator .sim-quota-overview-copy strong{font-size:.72rem}.delivery-simulator .sim-quota-overview-copy span{font-size:.64rem}.delivery-simulator .sim-quota-overview-value{font-size:1rem}\n.delivery-simulator .sim-choice-grid{gap:.5rem}.delivery-simulator .sim-choice{min-height:68px;padding:.58rem;border:1px solid var(--sim-border);border-radius:10px;background:#fff}.delivery-simulator .sim-choice.is-active{border-color:color-mix(in srgb,var(--sim-violet) 18%,var(--sim-border));background:var(--sim-violet-soft);box-shadow:none}.delivery-simulator .sim-choice-icon{width:38px;height:38px;border-radius:10px}.delivery-simulator .sim-choice-copy strong{font-size:.75rem}.delivery-simulator .sim-choice-copy small{margin-top:.08rem;font-size:.64rem}\n.delivery-simulator .sim-custom-card{margin-top:.55rem;padding:.58rem;background:var(--sim-soft)}.delivery-simulator .sim-custom-preview{background:transparent}.delivery-simulator .sim-note{margin-top:.5rem;padding:.48rem .55rem;background:var(--sim-amber-soft);color:var(--sim-amber);font-size:.66rem}\n.delivery-simulator .sim-input,.delivery-simulator .sim-select,.delivery-simulator .sim-search{min-height:42px;border:1px solid var(--sim-border-strong);border-radius:9px;font-size:.73rem}.delivery-simulator .sim-input:focus,.delivery-simulator .sim-select:focus,.delivery-simulator .sim-search:focus{border-color:var(--sim-green);box-shadow:0 0 0 3px rgba(22,138,77,.07)}\n.delivery-simulator .sim-selected-fallback{margin:.55rem 0;padding:.48rem .55rem;border:0;border-radius:10px;background:var(--sim-soft)}.delivery-simulator .sim-selected-fallback-head{margin-bottom:.32rem}.delivery-simulator .sim-selected-fallback-head strong{font-size:.68rem}.delivery-simulator .sim-selected-fallback-head span:empty{display:none}.delivery-simulator .sim-selected-chip{min-height:30px;border:1px solid var(--sim-border);background:#fff;color:var(--sim-secondary);font-size:.65rem}.delivery-simulator .sim-selected-chip>i{color:var(--sim-violet)}\n.delivery-simulator .sim-product-tools{gap:.48rem}.delivery-simulator .sim-segmented{border:1px solid var(--sim-border);background:var(--sim-soft)}.delivery-simulator .sim-segmented button{min-height:36px;font-size:.68rem}.delivery-simulator .sim-segmented button.is-active{color:var(--sim-green)}.delivery-simulator .sim-selection-status{margin:.5rem 0 .3rem;font-size:.65rem}\n.delivery-simulator .sim-product-list{gap:.48rem}.delivery-simulator .sim-product-option{min-height:72px;padding:.56rem;border:1px solid var(--sim-border);border-left:1px solid var(--sim-border);border-radius:10px;background:#fff}.delivery-simulator .sim-product-option:hover,.delivery-simulator .sim-product-option:focus-visible{border-color:var(--sim-border-strong)}.delivery-simulator .sim-product-option.is-selected{border-color:color-mix(in srgb,var(--sim-violet) 22%,var(--sim-border));background:var(--sim-violet-soft)}.delivery-simulator .sim-product-option-icon{width:38px;height:38px;background:var(--sim-green-soft);color:var(--sim-green)}.delivery-simulator .sim-product-option.is-selected .sim-product-option-icon{background:#fff;color:var(--sim-violet)}.delivery-simulator .sim-product-option-copy strong{font-size:.74rem}.delivery-simulator .sim-product-option-copy small{font-size:.63rem}.delivery-simulator .sim-product-badges{margin-top:.16rem}.delivery-simulator .sim-product-badge{display:none}.delivery-simulator .sim-product-badge.info{display:inline-flex;min-height:19px;background:transparent;color:var(--sim-muted);padding:0;font-size:.59rem;font-weight:650}\n.delivery-simulator .sim-limit-mode{margin-bottom:.6rem;padding:.6rem;border:1px solid var(--sim-border);border-radius:10px;background:var(--sim-soft)}.delivery-simulator .sim-limit-mode-head{margin-bottom:.42rem}.delivery-simulator .sim-limit-mode-head strong{font-size:.72rem}.delivery-simulator .sim-limit-mode-head>span{display:none}.delivery-simulator .sim-limit-mode-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:.3rem;padding:3px;border:1px solid var(--sim-border);border-radius:9px;background:#fff}.delivery-simulator .sim-mode-button{min-height:40px;grid-template-columns:minmax(0,1fr) auto;gap:.35rem;padding:.42rem .52rem;border:0;border-radius:7px;background:transparent}.delivery-simulator .sim-mode-button:hover,.delivery-simulator .sim-mode-button:focus-visible,.delivery-simulator .sim-mode-button.is-active{border:0;background:var(--sim-violet-soft);box-shadow:none}.delivery-simulator .sim-mode-button-icon,.delivery-simulator .sim-mode-button small{display:none}.delivery-simulator .sim-mode-button strong{font-size:.69rem}.delivery-simulator .sim-mode-button.is-active strong,.delivery-simulator .sim-mode-button.is-active .sim-mode-check{color:var(--sim-violet)}\n.delivery-simulator .sim-summary{position:sticky;top:calc(var(--app-header-rendered-height,58px) + .3rem);gap:0;margin-bottom:.6rem;padding:.5rem .55rem;border:1px solid var(--sim-border);border-radius:11px;background:rgba(255,255,255,.97);box-shadow:var(--shadow-sm)}.delivery-simulator .sim-summary-item{padding:.28rem .5rem;border-radius:0;background:transparent}.delivery-simulator .sim-summary-item+.sim-summary-item{border-left:1px solid var(--sim-border)}.delivery-simulator .sim-summary-item span{font-size:.62rem}.delivery-simulator .sim-summary-item strong{font-size:.8rem}.delivery-simulator .sim-progress{margin-top:.38rem;height:7px;background:#e5ece7}.delivery-simulator .sim-progress>span{background:linear-gradient(90deg,#4ade80,var(--sim-green))}.delivery-simulator .sim-budget-rule{display:none}\n.delivery-simulator .sim-allocation-list{gap:.52rem}.delivery-simulator .sim-allocation{border:1px solid var(--sim-border);border-left:1px solid var(--sim-border);border-radius:11px;background:#fff}.delivery-simulator .sim-allocation-head{min-height:58px;padding:.5rem .58rem;border-bottom:1px solid var(--sim-border);background:linear-gradient(180deg,var(--sim-soft),#fff)}.delivery-simulator .sim-allocation-icon{width:36px;height:36px;background:var(--sim-violet-soft);color:var(--sim-violet)}.delivery-simulator .sim-allocation-product strong{font-size:.75rem}.delivery-simulator .sim-allocation-product span{font-size:.63rem}.delivery-simulator .sim-row-total{font-size:.78rem}.delivery-simulator .sim-allocation-body{gap:.52rem;padding:.58rem}.delivery-simulator .sim-capacity{min-height:auto;padding:.48rem .55rem;border:0;border-radius:9px;background:var(--sim-blue-soft)}.delivery-simulator .sim-capacity-icon{width:32px;height:32px}.delivery-simulator .sim-capacity-copy strong{font-size:.66rem}.delivery-simulator .sim-capacity-copy span{display:none}.delivery-simulator .sim-capacity-value{font-size:.82rem}.delivery-simulator .sim-limit-info{display:none}.delivery-simulator .sim-allocation-grid{gap:.52rem}.delivery-simulator .sim-field label{font-size:.66rem}.delivery-simulator .sim-quantity-control{gap:.45rem}.delivery-simulator .sim-allocation-actions{padding-top:.05rem}.delivery-simulator .sim-small-button{min-height:36px;border-radius:9px;font-size:.65rem}.delivery-simulator .sim-small-button.primary-soft{background:#fff;color:var(--sim-violet)}.delivery-simulator .sim-small-button.danger{background:#fff}\n.delivery-simulator .sim-history{margin-top:.6rem;border:1px solid var(--sim-border)}.delivery-simulator .sim-history summary{min-height:44px}.delivery-simulator .sim-history-body{background:var(--sim-soft)}\n.sim-action-bar{border-top:1px solid var(--sim-border,#dce6df);background:rgba(255,255,255,.97)}.sim-action-inner{width:min(100%,1080px);gap:.4rem}.sim-button{min-height:40px;padding:.46rem .64rem;border:1px solid var(--sim-border-strong,#c8d6cd);border-radius:9px;background:#fff;color:var(--sim-text,#102018);font-size:.73rem;font-weight:780}.sim-button.primary,.sim-button.success{border-color:var(--color-primary-dark,#15803d);background:linear-gradient(135deg,var(--color-primary,#16a34a),var(--color-primary-dark,#15803d));color:#fff;box-shadow:0 7px 16px rgba(22,163,74,.14)}.sim-button.share{border-color:var(--sim-border-strong,#c8d6cd);background:#fff;color:var(--sim-blue,#2563eb);box-shadow:none}\n@media(max-width:700px){.delivery-simulator{width:min(100%,calc(100dvw - 16px))}.delivery-simulator .sim-panel-head{min-height:60px;padding:.62rem}.delivery-simulator .sim-panel-body{padding:.62rem}.delivery-simulator .sim-choice-grid,.delivery-simulator .sim-product-list{grid-template-columns:1fr}.delivery-simulator .sim-limit-mode-grid{grid-template-columns:1fr 1fr}.delivery-simulator .sim-allocation-grid{grid-template-columns:1fr}}\n@media(max-width:520px){.delivery-simulator .sim-panel-head-icon{width:36px;height:36px}.delivery-simulator .sim-panel-head h2{font-size:.9rem}.delivery-simulator .sim-panel-head p{font-size:.68rem}.delivery-simulator .sim-summary{padding:.42rem}.delivery-simulator .sim-summary-item{padding:.24rem .32rem}.delivery-simulator .sim-summary-item span{font-size:.57rem}.delivery-simulator .sim-summary-item strong{font-size:.72rem}.delivery-simulator .sim-mode-button{padding:.4rem .35rem}.delivery-simulator .sim-mode-button strong{font-size:.63rem}.delivery-simulator .sim-capacity{grid-template-columns:auto minmax(0,1fr) auto}.sim-action-inner .final-action{flex:1 1 0}}\n</style>
@endpush

@section('content')
<main
    class="delivery-simulator"
    id="delivery-simulator"
    data-endpoint="{{ $simulatorEndpoint }}"
    data-history-key="sgc.simulations.{{ $historyScope }}.v2"
    data-legacy-history-key="sgc.simulations.{{ $historyScope }}.v1"
>
    <header class="sim-header">
        <a
            class="sim-back"
            href="{{ $simulatorBackUrl }}"
            aria-label="Voltar ao projeto"
        >
            <i class="ph ph-arrow-left"></i>
        </a>

        <div class="sim-header-copy">
            <h1>{{ $simulatorHeading }}</h1>
            <p>{{ $simulatorProjectLabel }}</p>
        </div>

        <button
            class="sim-icon-button"
            type="button"
            id="sim-restart"
            aria-label="Recomeçar simulação"
            title="Recomeçar"
        >
            <i class="ph ph-arrow-counter-clockwise"></i>
        </button>
    </header>

    <div class="sim-steps" aria-label="Etapas da simulação">
        <div class="sim-step" data-step-marker="0">
            <span class="sim-step-bar"><span></span></span>
            <span class="sim-step-label">1. Valor</span>
        </div>

        <div class="sim-step" data-step-marker="1">
            <span class="sim-step-bar"><span></span></span>
            <span class="sim-step-label">2. Produtos</span>
        </div>

        <div class="sim-step" data-step-marker="2">
            <span class="sim-step-bar"><span></span></span>
            <span class="sim-step-label">3. Quantidades</span>
        </div>
    </div>

    {{-- ETAPA 1 --}}
    <section class="sim-panel" data-step-panel="0">
        <header class="sim-panel-head">
            <span class="sim-panel-head-icon" aria-hidden="true">
                <i class="ph-duotone ph-wallet"></i>
            </span>

            <div class="sim-panel-head-copy">
                <span class="sim-eyebrow">Etapa 1 de 3</span>
                <h2>Escolha o valor</h2>
                <p>
                    Este será o valor total da simulação.
                </p>
            </div>
        </header>

        <div class="sim-panel-body">
            <div class="sim-explainer">
                <span class="sim-explainer-icon" aria-hidden="true">
                    <i class="ph-duotone ph-info"></i>
                </span>

                <div>
                    <strong>Este valor é a cota financeira da simulação</strong>
                    <span>
                        Você pode usar o saldo real do projeto ou testar outro valor.
                        Depois escolherá um ou vários produtos para descobrir quanto poderia entregar.
                    </span>
                </div>
            </div>

            <div class="sim-quota-overview">
                <div class="sim-quota-overview-copy">
                    <strong>Saldo disponível no projeto</strong>
                    <span>Referência real disponível neste momento.</span>
                </div>

                <strong class="sim-quota-overview-value" id="sim-real-remaining">
                    Carregando...
                </strong>
            </div>

            <div class="sim-choice-grid">
                <button class="sim-choice" type="button" data-quota-mode="remaining">
                    <span class="sim-choice-icon" aria-hidden="true">
                        <i class="ph-duotone ph-wallet"></i>
                    </span>

                    <span class="sim-choice-copy">
                        <strong>Usar o saldo disponível</strong>
                        <small id="sim-remaining-label">Carregando...</small>
                    </span>

                    <i class="sim-choice-check ph ph-circle" data-choice-check="remaining" aria-hidden="true"></i>
                </button>

                <button class="sim-choice" type="button" data-quota-mode="custom">
                    <span class="sim-choice-icon" aria-hidden="true">
                        <i class="ph-duotone ph-pencil-simple-line"></i>
                    </span>

                    <span class="sim-choice-copy">
                        <strong>Testar outro valor</strong>
                        <small>Útil para estudos e comparações.</small>
                    </span>

                    <i class="sim-choice-check ph ph-circle" data-choice-check="custom" aria-hidden="true"></i>
                </button>
            </div>

            <div class="sim-custom-card" id="sim-custom-quota-field" hidden>
                <div class="sim-field">
                    <label for="sim-custom-quota">Valor que deseja simular</label>
                    <input
                        class="sim-input sim-money-input"
                        id="sim-custom-quota"
                        type="number"
                        min="0.01"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="0,00"
                    >
                </div>

                <div class="sim-custom-preview">
                    <span>Valor escolhido</span>
                    <strong id="sim-quota-preview">R$ 0,00</strong>
                </div>
            </div>

            <div class="sim-note" id="sim-custom-warning" hidden>
                <i class="ph-duotone ph-warning" aria-hidden="true"></i>
                <span>
                    O valor informado está acima do saldo real do projeto.
                    Isso é permitido porque o simulador também serve para estudos.
                </span>
            </div>

            <p class="sim-feedback" id="sim-quota-error" hidden></p>

            <details class="sim-history" id="sim-history" hidden>
                <summary>
                    <span class="sim-history-icon" aria-hidden="true">
                        <i class="ph-duotone ph-clock-counter-clockwise"></i>
                    </span>

                    <span class="sim-history-copy">
                        <strong>Simulações salvas neste aparelho</strong>
                        <span id="sim-history-count"></span>
                    </span>

                    <i class="sim-history-caret ph ph-caret-down" aria-hidden="true"></i>
                </summary>

                <div class="sim-history-body">
                    <div class="sim-history-toolbar">
                        <button class="sim-small-button danger" type="button" id="sim-clear-history">
                            <i class="ph ph-trash"></i>
                            Limpar histórico
                        </button>
                    </div>

                    <div class="sim-history-list" id="sim-history-list"></div>
                </div>
            </details>
        </div>
    </section>

    {{-- ETAPA 2 --}}
    <section class="sim-panel" data-step-panel="1" hidden>
        <header class="sim-panel-head">
            <span class="sim-panel-head-icon blue" aria-hidden="true">
                <i class="ph-duotone ph-package"></i>
            </span>

            <div class="sim-panel-head-copy">
                <span class="sim-eyebrow">Etapa 2 de 3</span>
                <h2>Escolha os produtos</h2>
                <p>
                    Selecione um ou mais produtos.
                </p>
            </div>
        </header>

        <div class="sim-panel-body">
            <div class="sim-product-tools">
                <div class="sim-search-wrap">
                    <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                    <input
                        class="sim-search"
                        id="sim-product-search"
                        type="search"
                        autocomplete="off"
                        placeholder="Buscar produto pelo nome..."
                    >
                </div>

                <div class="sim-segmented" aria-label="Filtrar produtos">
                    <button class="is-active" type="button" data-product-filter="enabled">Liberados</button>
                    <button type="button" data-product-filter="all">Todos</button>
                </div>
            </div>

            <div class="sim-selection-status">
                <span class="sim-selected-count">
                    <i class="ph ph-check-circle" aria-hidden="true"></i>
                    <strong id="sim-selected-count">0</strong>
                    selecionado(s)
                </span>
                <span id="sim-product-result-count"></span>
            </div>

            <div class="sim-selected-fallback">
                <div class="sim-selected-fallback-head">
                    <strong>Selecionados</strong>
                    <span></span>
                </div>
                <div class="sim-selected-chips" id="sim-selected-products-step2"></div>
            </div>

            <div class="sim-product-list" id="sim-product-list"></div>
            <p class="sim-feedback" id="sim-products-error" hidden></p>
        </div>
    </section>

    {{-- ETAPA 3 --}}
    <section class="sim-panel" data-step-panel="2" hidden>
        <header class="sim-panel-head">
            <span class="sim-panel-head-icon green" aria-hidden="true">
                <i class="ph-duotone ph-scales"></i>
            </span>

            <div class="sim-panel-head-copy">
                <span class="sim-eyebrow">Etapa 3 de 3</span>
                <h2>Defina as quantidades</h2>
                <p>
                    Ajuste as quantidades e veja o resultado.
                </p>
            </div>
        </header>

        <div class="sim-panel-body">
            <div class="sim-selected-fallback">
                <div class="sim-selected-fallback-head">
                    <strong>Selecionados</strong>
                    <span id="sim-selected-summary-label"></span>
                </div>
                <div class="sim-selected-chips" id="sim-selected-products-step3"></div>
            </div>

            <div class="sim-limit-mode">
                <div class="sim-limit-mode-head">
                    <strong>Limites dos produtos</strong>
                    <span>
                        Escolha como deseja simular.
                    </span>
                </div>

                <div class="sim-limit-mode-grid">
                    <button class="sim-mode-button is-active" type="button" data-limit-mode="free">
                        <span class="sim-mode-button-icon" aria-hidden="true">
                            <i class="ph-duotone ph-flask"></i>
                        </span>
                        <span>
                            <strong>Estudo livre</strong>
                            <small>Ignora os limites individuais.</small>
                        </span>
                        <i class="sim-mode-check ph ph-check-circle-fill" data-limit-check="free" aria-hidden="true"></i>
                    </button>

                    <button class="sim-mode-button" type="button" data-limit-mode="configured">
                        <span class="sim-mode-button-icon" aria-hidden="true">
                            <i class="ph-duotone ph-ruler"></i>
                        </span>
                        <span>
                            <strong>Usar limites atuais</strong>
                            <small>Respeita os limites configurados.</small>
                        </span>
                        <i class="sim-mode-check ph ph-circle" data-limit-check="configured" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="sim-summary" id="sim-summary">
                <div class="sim-summary-item">
                    <span>Cota escolhida</span>
                    <strong id="sim-budget">R$ 0,00</strong>
                </div>

                <div class="sim-summary-item">
                    <span>Total simulado</span>
                    <strong id="sim-total">R$ 0,00</strong>
                </div>

                <div class="sim-summary-item available">
                    <span>Ainda disponível</span>
                    <strong id="sim-balance">R$ 0,00</strong>
                </div>

                <div class="sim-progress"><span id="sim-progress"></span></div>

                <div class="sim-budget-rule">
                    <i class="ph ph-lock-simple" aria-hidden="true"></i>
                    Total limitado pela cota escolhida.
                </div>
            </div>

            <div class="sim-allocation-list" id="sim-allocation-list"></div>
        </div>
    </section>
</main>

<div class="sim-action-bar" aria-label="Ações da simulação">
    <div class="sim-action-inner">
        <button class="sim-button back" type="button" id="sim-previous" hidden>
            <i class="ph ph-arrow-left"></i>
            <span>Voltar</span>
        </button>

        <button class="sim-button primary" type="button" id="sim-next">
            Escolher produtos
            <i class="ph ph-arrow-right"></i>
        </button>

        <button class="sim-button success final-action" type="button" id="sim-save" hidden>
            <i class="ph-duotone ph-floppy-disk"></i>
            <span>Salvar simulação</span>
        </button>

        <button class="sim-button share final-action" type="button" id="sim-share" hidden>
            <i class="ph-duotone ph-share-network"></i>
            <span>Compartilhar imagem</span>
        </button>
    </div>
</div>

<div class="sim-toast" id="sim-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
(() => {
    'use strict';

    const root = document.getElementById('delivery-simulator');
    if (!root) return;

    const PROJECT_TITLE = @json($project->title);
    const EPSILON = 0.0005;

    const state = {
        step:0,
        products:[],
        summary:{},
        quotaMode:'remaining',
        customQuota:0,
        productFilter:'enabled',
        search:'',
        useProductLimits:false,
        selected:new Map(),
        history:[],
    };

    const STEP_SLUGS = ['valor','produtos','quantidades'];

    const historyKey = root.dataset.historyKey;
    const legacyHistoryKey = root.dataset.legacyHistoryKey;

    const panels = [...document.querySelectorAll('[data-step-panel]')];
    const markers = [...document.querySelectorAll('[data-step-marker]')];
    const previousButton = document.getElementById('sim-previous');
    const nextButton = document.getElementById('sim-next');
    const saveButton = document.getElementById('sim-save');
    const shareButton = document.getElementById('sim-share');

    function stepFromLocation() {
        const match = window.location.hash.match(/^#etapa=(valor|produtos|quantidades)$/);
        const step = match ? STEP_SLUGS.indexOf(match[1]) : 0;

        return step >= 0 ? step : 0;
    }

    function syncStepHistory(replace = false) {
        const url = new URL(window.location.href);
        url.hash = `etapa=${STEP_SLUGS[state.step]}`;
        const payload = { simulatorStep: state.step };

        if (replace) {
            window.history.replaceState(payload,'',url);
        } else {
            window.history.pushState(payload,'',url);
        }
    }

    function setStep(step,{historyMode = 'push'} = {}) {
        state.step = Math.max(0,Math.min(STEP_SLUGS.length - 1,Number(step) || 0));

        if (historyMode === 'push') syncStepHistory();
        if (historyMode === 'replace') syncStepHistory(true);

        renderStep();
    }

    const money = value => new Intl.NumberFormat('pt-BR', {
        style:'currency',
        currency:'BRL',
    }).format(Number(value || 0));

    const quantity = value => new Intl.NumberFormat('pt-BR', {
        minimumFractionDigits:0,
        maximumFractionDigits:3,
    }).format(Number(value || 0));

    const escapeHtml = value => String(value ?? '').replace(
        /[&<>'"]/g,
        character => ({
            '&':'&amp;',
            '<':'&lt;',
            '>':'&gt;',
            "'":'&#039;',
            '"':'&quot;',
        })[character]
    );

    const floorQuantity = value => Math.max(
        0,
        Math.floor((Number(value || 0) + 1e-9) * 1000) / 1000
    );

    function quotaValue() {
        return state.quotaMode === 'remaining'
            ? Math.max(0, Number(state.summary.financial_remaining || 0))
            : Math.max(0, Number(state.customQuota || 0));
    }

    function productFor(productId) {
        return state.products.find(
            product => Number(product.product_id) === Number(productId)
        );
    }

    function destinationFor(entry, product) {
        return (product?.destinations || []).find(
            destination => Number(destination.customer_id) === Number(entry.destinationId)
        ) || [...(product?.destinations || [])].sort(
            (left,right) => Number(right.price) - Number(left.price)
        )[0] || null;
    }

    function rowsForCalculation() {
        return [...state.selected.values()].map(entry => {
            const product = productFor(entry.productId);
            const destination = destinationFor(entry, product);
            const price = Number(destination?.price || 0);
            const amount = Math.max(0, Number(entry.quantity || 0));

            return {
                entry,
                product,
                destination,
                price,
                quantity:amount,
                total:amount * price,
            };
        }).filter(row => row.product && row.destination && row.price > 0);
    }

    function totals() {
        const rows = rowsForCalculation();
        return {
            rows,
            total:rows.reduce((sum,row) => sum + row.total,0),
        };
    }

    function configuredLimitFor(product) {
        if (
            product?.remaining_quantity === null
            || product?.remaining_quantity === undefined
        ) {
            return Infinity;
        }

        return Math.max(0,Number(product.remaining_quantity || 0));
    }

    function otherProductsTotal(productId) {
        return rowsForCalculation().reduce((sum,row) => {
            return Number(row.entry.productId) === Number(productId)
                ? sum
                : sum + row.total;
        },0);
    }

    function maxQuantityFor(productId) {
        const id = Number(productId);
        const product = productFor(id);
        const entry = state.selected.get(id);
        if (!product || !entry) return 0;

        const destination = destinationFor(entry,product);
        const price = Number(destination?.price || 0);
        if (!(price > 0)) return 0;

        const remainingBudget = Math.max(
            0,
            quotaValue() - otherProductsTotal(id)
        );

        let max = remainingBudget / price;

        if (state.useProductLimits) {
            max = Math.min(max,configuredLimitFor(product));
        }

        return floorQuantity(max);
    }

    function theoreticalFullQuotaQuantity(row) {
        if (!(row.price > 0)) return 0;
        return floorQuantity(quotaValue() / row.price);
    }

    function clampEntry(productId,{notify=false}={}) {
        const id = Number(productId);
        const entry = state.selected.get(id);
        if (!entry) return false;

        const max = maxQuantityFor(id);
        const before = Math.max(0,Number(entry.quantity || 0));
        const after = Math.min(before,max);

        if (before > after + EPSILON) {
            entry.quantity = after;

            if (notify) {
                showToast(
                    `A quantidade foi ajustada para ${quantity(after)} ${productFor(id)?.unit || 'un'}, o máximo permitido neste modo.`
                );
            }

            return true;
        }

        entry.quantity = floorQuantity(before);
        return false;
    }

    function clampAllEntries() {
        for (let pass = 0; pass < 2; pass += 1) {
            [...state.selected.keys()].forEach(id => clampEntry(id));
        }
    }

    function showToast(message) {
        const toast = document.getElementById('sim-toast');
        toast.textContent = message;
        toast.hidden = false;
        window.clearTimeout(showToast.timer);
        showToast.timer = window.setTimeout(() => {
            toast.hidden = true;
        },3200);
    }

    function renderStep() {
        panels.forEach(panel => {
            panel.hidden = Number(panel.dataset.stepPanel) !== state.step;
        });

        markers.forEach(marker => {
            const markerStep = Number(marker.dataset.stepMarker);
            marker.classList.toggle('is-complete',markerStep <= state.step);
            marker.classList.toggle('is-current',markerStep === state.step);
        });

        previousButton.hidden = state.step === 0;
        nextButton.hidden = state.step === 2;
        saveButton.hidden = state.step !== 2;
        shareButton.hidden = state.step !== 2;

        nextButton.innerHTML = state.step === 0
            ? 'Escolher produtos <i class="ph ph-arrow-right"></i>'
            : 'Ver quantidades <i class="ph ph-arrow-right"></i>';

        if (state.step === 0) renderQuota();
        if (state.step === 1) renderProducts();
        if (state.step === 2) {
            clampAllEntries();
            renderSimulation();
        }

        window.scrollTo({
            top:0,
            behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 'auto'
                : 'smooth',
        });
    }

    function renderQuota() {
        const remaining = state.summary.financial_remaining;
        const remainingNumber = remaining === null || remaining === undefined
            ? null
            : Number(remaining);

        const remainingButton = document.querySelector('[data-quota-mode="remaining"]');
        const customButton = document.querySelector('[data-quota-mode="custom"]');
        const remainingAvailable = remainingNumber !== null && remainingNumber > 0;

        remainingButton.disabled = !remainingAvailable;

        if (!remainingAvailable && state.quotaMode === 'remaining') {
            state.quotaMode = 'custom';
        }

        remainingButton.classList.toggle('is-active',state.quotaMode === 'remaining');
        customButton.classList.toggle('is-active',state.quotaMode === 'custom');

        document.querySelectorAll('[data-choice-check]').forEach(icon => {
            const active = icon.dataset.choiceCheck === state.quotaMode;
            icon.className = `sim-choice-check ph ${active ? 'ph-check-circle-fill' : 'ph-circle'}`;
        });

        document.getElementById('sim-real-remaining').textContent = remainingNumber === null
            ? 'Não definido'
            : money(Math.max(0,remainingNumber));

        document.getElementById('sim-remaining-label').textContent = remainingNumber === null
            ? 'Este projeto não possui saldo financeiro definido.'
            : remainingNumber > 0
                ? `${money(remainingNumber)} disponíveis agora`
                : 'Não há saldo disponível agora.';

        document.getElementById('sim-custom-quota-field').hidden = state.quotaMode !== 'custom';

        const input = document.getElementById('sim-custom-quota');
        if (document.activeElement !== input) {
            input.value = state.customQuota > 0 ? state.customQuota : '';
        }

        document.getElementById('sim-quota-preview').textContent = money(quotaValue());

        document.getElementById('sim-custom-warning').hidden = !(
            state.quotaMode === 'custom'
            && remainingNumber !== null
            && state.customQuota > remainingNumber + .005
        );
    }

    function renderSelectedFallback() {
        const selectedProducts = [...state.selected.keys()]
            .map(productFor)
            .filter(Boolean);

        const html = selectedProducts.length
            ? selectedProducts.map(product => `
                <span class="sim-selected-chip">
                    <i class="ph ph-package" aria-hidden="true"></i>
                    ${escapeHtml(product.product_name)}
                    <button
                        type="button"
                        data-remove-selected-chip="${Number(product.product_id)}"
                        aria-label="Remover ${escapeHtml(product.product_name)}"
                    >
                        <i class="ph ph-x"></i>
                    </button>
                </span>
            `).join('')
            : '<span class="sim-selected-empty">Nenhum produto selecionado.</span>';

        document.querySelectorAll('#sim-selected-products-step2, #sim-selected-products-step3').forEach(container => {
            container.innerHTML = html;
        });

        const summaryLabel = document.getElementById('sim-selected-summary-label');
        if (summaryLabel) {
            summaryLabel.textContent = `${selectedProducts.length} ${selectedProducts.length === 1 ? 'produto' : 'produtos'}`;
        }
    }

    function renderProducts() {
        const search = state.search.trim().toLocaleLowerCase('pt-BR');

        const filtered = state.products.filter(product => {
            const matchesFilter = search !== ''
                || state.productFilter === 'all'
                || product.delivery_enabled;

            const matchesSearch = !search
                || `${product.product_name} ${product.unit}`
                    .toLocaleLowerCase('pt-BR')
                    .includes(search);

            return matchesFilter && matchesSearch;
        });

        const visible = filtered.slice(0,48);

        document.getElementById('sim-selected-count').textContent = state.selected.size;
        document.getElementById('sim-product-result-count').textContent = visible.length < filtered.length
            ? `${visible.length} de ${filtered.length} produto(s)`
            : `${filtered.length} produto(s)`;

        document.querySelectorAll('[data-product-filter]').forEach(button => {
            button.classList.toggle('is-active',button.dataset.productFilter === state.productFilter);
        });

        renderSelectedFallback();

        document.getElementById('sim-product-list').innerHTML = visible.length
            ? visible.map(product => {
                const productId = Number(product.product_id);
                const selected = state.selected.has(productId);
                const configured = configuredLimitFor(product);
                const configuredBadge = Number.isFinite(configured)
                    ? `<span class="sim-product-badge info">Limite atual: ${quantity(configured)} ${escapeHtml(product.unit || '')}</span>`
                    : '';

                return `
                    <button
                        class="sim-product-option ${selected ? 'is-selected' : ''}"
                        type="button"
                        data-product-id="${productId}"
                        aria-pressed="${selected}"
                    >
                        <span class="sim-product-option-icon" aria-hidden="true">
                            <i class="ph-duotone ${product.delivery_enabled ? 'ph-check-circle' : 'ph-package'}"></i>
                        </span>

                        <span class="sim-product-option-copy">
                            <strong>${escapeHtml(product.product_name)}</strong>
                            <small>${escapeHtml(product.price_label)} · ${escapeHtml(product.unit || 'un')}</small>
                            <span class="sim-product-badges">
                                ${product.delivery_enabled ? '<span class="sim-product-badge">Liberado para entrega</span>' : ''}
                                ${configuredBadge}
                            </span>
                        </span>

                        <i class="ph ${selected ? 'ph-check-circle-fill' : 'ph-circle'} sim-product-option-check" aria-hidden="true"></i>
                    </button>
                `;
            }).join('')
            : '<div class="sim-empty">Nenhum produto encontrado. Tente outro nome ou altere o filtro.</div>';
    }

    function renderLimitMode() {
        document.querySelectorAll('[data-limit-mode]').forEach(button => {
            const active = button.dataset.limitMode === (state.useProductLimits ? 'configured' : 'free');
            button.classList.toggle('is-active',active);
        });

        document.querySelectorAll('[data-limit-check]').forEach(icon => {
            const active = icon.dataset.limitCheck === (state.useProductLimits ? 'configured' : 'free');
            icon.className = `sim-mode-check ph ${active ? 'ph-check-circle-fill' : 'ph-circle'}`;
        });
    }

    function limitInfoText(row) {
        const configured = configuredLimitFor(row.product);

        if (!Number.isFinite(configured)) {
            return 'Sem limite individual.';
        }

        if (state.useProductLimits) {
            return `Limite atual do projeto: ${quantity(configured)} ${row.product.unit || 'un'}. Limite em uso.`;
        }

        return `Limite atual do projeto: ${quantity(configured)} ${row.product.unit || 'un'}. Apenas informativo.`;
    }

    function capacityDescription(row,max) {
        if (state.selected.size === 1) {
            return state.useProductLimits
                ? 'Máximo disponível.'
                : 'Máximo pela cota.';
        }

        return state.useProductLimits
            ? 'Máximo disponível.'
            : 'Máximo após os outros produtos.';
    }

    function renderSimulation() {
        clampAllEntries();
        renderLimitMode();
        renderSelectedFallback();

        const result = totals();
        const budget = quotaValue();
        const available = Math.max(0,budget - result.total);

        document.getElementById('sim-budget').textContent = money(budget);
        document.getElementById('sim-total').textContent = money(result.total);
        document.getElementById('sim-balance').textContent = money(available);
        document.getElementById('sim-progress').style.width = `${budget > 0 ? Math.min(100,result.total / budget * 100) : 0}%`;

        document.getElementById('sim-allocation-list').innerHTML = result.rows.length
            ? result.rows.map(row => {
                const productId = Number(row.product.product_id);
                const max = maxQuantityFor(productId);
                const fullQuotaQty = theoreticalFullQuotaQuantity(row);
                const fullQuotaValue = fullQuotaQty * row.price;

                const options = (row.product.destinations || []).map(destination => `
                    <option
                        value="${Number(destination.customer_id)}"
                        ${Number(destination.customer_id) === Number(row.destination.customer_id) ? 'selected' : ''}
                    >
                        ${escapeHtml(destination.customer)} · ${money(destination.price)}
                    </option>
                `).join('');

                return `
                    <article class="sim-allocation" data-allocation="${productId}">
                        <header class="sim-allocation-head">
                            <span class="sim-allocation-icon" aria-hidden="true">
                                <i class="ph-duotone ph-package"></i>
                            </span>

                            <span class="sim-allocation-product">
                                <strong>${escapeHtml(row.product.product_name)}</strong>
                                <span>${money(row.price)} por ${escapeHtml(row.product.unit || 'un')}</span>
                            </span>

                            <strong class="sim-row-total" data-row-total="${productId}">${money(row.total)}</strong>
                        </header>

                        <div class="sim-allocation-body">
                            <div class="sim-capacity">
                                <span class="sim-capacity-icon" aria-hidden="true">
                                    <i class="ph-duotone ph-gauge"></i>
                                </span>

                                <span class="sim-capacity-copy">
                                    <strong>Você pode entregar até</strong>
                                    <span data-capacity-description="${productId}">${escapeHtml(capacityDescription(row,max))}</span>
                                </span>

                                <strong class="sim-capacity-value" data-capacity-value="${productId}">
                                    ${quantity(max)} ${escapeHtml(row.product.unit || 'un')}
                                </strong>
                            </div>

                            ${state.selected.size === 1 ? `
                                <div class="sim-limit-info">
                                    <i class="ph ph-lightbulb" aria-hidden="true"></i>
                                    <span>
                                        Se toda a cota fosse usada neste produto pelo preço atual,
                                        seriam aproximadamente <strong>${quantity(fullQuotaQty)} ${escapeHtml(row.product.unit || 'un')}</strong>,
                                        correspondendo a <strong>${money(fullQuotaValue)}</strong>.
                                    </span>
                                </div>
                            ` : ''}

                            <div class="sim-limit-info">
                                <i class="ph ph-ruler" aria-hidden="true"></i>
                                <span data-limit-info="${productId}">${escapeHtml(limitInfoText(row))}</span>
                            </div>

                            <div class="sim-allocation-grid">
                                <div class="sim-field">
                                    <label>Preço e destino</label>
                                    <select class="sim-select" data-destination-product="${productId}">
                                        ${options}
                                    </select>
                                </div>

                                <div class="sim-field">
                                    <label>
                                        <span>Quantidade (${escapeHtml(row.product.unit || 'un')})</span>
                                        <span data-current-max-label="${productId}">máx. ${quantity(max)}</span>
                                    </label>

                                    <div class="sim-quantity-control">
                                        <input
                                            class="sim-range"
                                            type="range"
                                            min="0"
                                            max="${Math.max(max,0.001)}"
                                            step="0.001"
                                            value="${Math.min(row.quantity,max)}"
                                            data-range-product="${productId}"
                                        >

                                        <input
                                            class="sim-input sim-quantity-input"
                                            type="number"
                                            min="0"
                                            max="${max}"
                                            step="0.001"
                                            inputmode="decimal"
                                            value="${row.quantity || ''}"
                                            data-quantity-product="${productId}"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="sim-allocation-actions">
                                <button class="sim-small-button primary-soft" type="button" data-fill-product="${productId}">
                                    <i class="ph ph-arrow-line-up"></i>
                                    Usar máximo
                                </button>

                                <button class="sim-small-button danger" type="button" data-remove-product="${productId}">
                                    <i class="ph ph-trash"></i>
                                    Remover
                                </button>
                            </div>
                        </div>
                    </article>
                `;
            }).join('')
            : '<div class="sim-empty">Nenhum produto selecionado.</div>';
    }

    function refreshSimulationValues() {
        clampAllEntries();

        const result = totals();
        const budget = quotaValue();
        const available = Math.max(0,budget - result.total);

        document.getElementById('sim-total').textContent = money(result.total);
        document.getElementById('sim-balance').textContent = money(available);
        document.getElementById('sim-progress').style.width = `${budget > 0 ? Math.min(100,result.total / budget * 100) : 0}%`;

        result.rows.forEach(row => {
            const id = Number(row.product.product_id);
            const max = maxQuantityFor(id);

            const totalElement = document.querySelector(`[data-row-total="${id}"]`);
            if (totalElement) totalElement.textContent = money(row.total);

            const capacityValue = document.querySelector(`[data-capacity-value="${id}"]`);
            if (capacityValue) capacityValue.textContent = `${quantity(max)} ${row.product.unit || 'un'}`;

            const capacityDescriptionElement = document.querySelector(`[data-capacity-description="${id}"]`);
            if (capacityDescriptionElement) capacityDescriptionElement.textContent = capacityDescription(row,max);

            const limitInfo = document.querySelector(`[data-limit-info="${id}"]`);
            if (limitInfo) limitInfo.textContent = limitInfoText(row);

            const maxLabel = document.querySelector(`[data-current-max-label="${id}"]`);
            if (maxLabel) maxLabel.textContent = `máx. ${quantity(max)}`;

            const range = document.querySelector(`[data-range-product="${id}"]`);
            const input = document.querySelector(`[data-quantity-product="${id}"]`);

            if (range) {
                range.max = Math.max(max,0.001);
                if (document.activeElement !== range) range.value = Math.min(row.entry.quantity,max);
            }

            if (input) {
                input.max = max;
                if (document.activeElement !== input) input.value = row.entry.quantity || '';
            }
        });
    }

    function updateQuantity(productId,value) {
        const id = Number(productId);
        const entry = state.selected.get(id);
        if (!entry) return;

        const requested = value === ''
            ? 0
            : Math.max(0,Number(String(value).replace(',','.')) || 0);

        entry.quantity = requested;
        const max = maxQuantityFor(id);

        if (requested > max + EPSILON) {
            entry.quantity = max;
            showToast(`O máximo disponível para este produto é ${quantity(max)} ${productFor(id)?.unit || 'un'}.`);
        } else {
            entry.quantity = floorQuantity(requested);
        }

        const range = document.querySelector(`[data-range-product="${id}"]`);
        const input = document.querySelector(`[data-quantity-product="${id}"]`);

        if (range && document.activeElement !== range) range.value = entry.quantity;
        if (input && document.activeElement !== input) input.value = entry.quantity || '';

        refreshSimulationValues();
    }

    function validateCurrentStep() {
        document.getElementById('sim-quota-error').hidden = true;
        document.getElementById('sim-products-error').hidden = true;

        if (state.step === 0 && quotaValue() <= 0) {
            const error = document.getElementById('sim-quota-error');
            error.textContent = 'Informe um valor maior que zero para continuar.';
            error.hidden = false;
            return false;
        }

        if (state.step === 1 && state.selected.size === 0) {
            const error = document.getElementById('sim-products-error');
            error.textContent = 'Selecione pelo menos um produto para continuar.';
            error.hidden = false;
            return false;
        }

        return true;
    }

    /* ======================================================
       Histórico: SOMENTE JSON
       ====================================================== */

    function sanitizeHistoryItem(item) {
        if (!item || !Array.isArray(item.rows)) return null;

        const rows = item.rows.map(row => ({
            productId:Number(row.productId),
            destinationId:Number(row.destinationId),
            quantity:Math.max(0,Number(row.quantity || 0)),
        })).filter(row => Number.isFinite(row.productId) && Number.isFinite(row.destinationId));

        if (!rows.length) return null;

        return {
            id:/^[a-z0-9-]+$/i.test(String(item.id || ''))
                ? String(item.id)
                : `${Date.now()}-${Math.random().toString(36).slice(2,7)}`,
            createdAt:Number(item.createdAt || Date.now()),
            quotaMode:item.quotaMode === 'remaining' ? 'remaining' : 'custom',
            customQuota:Number(item.customQuota || 0),
            budget:Number(item.budget || item.customQuota || 0),
            total:Number(item.total || 0),
            useProductLimits:Boolean(item.useProductLimits),
            rows,
        };
    }

    function persistHistory() {
        try {
            localStorage.setItem(historyKey,JSON.stringify(state.history.slice(0,12)));
            return true;
        } catch (error) {
            showToast('Não foi possível salvar a simulação neste aparelho.');
            return false;
        }
    }

    function readHistory() {
        try {
            const cutoff = Date.now() - 60 * 24 * 60 * 60 * 1000;
            const current = JSON.parse(localStorage.getItem(historyKey) || '[]');
            const legacy = JSON.parse(localStorage.getItem(legacyHistoryKey) || '[]');

            const merged = [
                ...(Array.isArray(current) ? current : []),
                ...(Array.isArray(legacy) ? legacy : []),
            ];

            const seen = new Set();
            state.history = merged
                .map(sanitizeHistoryItem)
                .filter(Boolean)
                .filter(item => item.createdAt >= cutoff)
                .filter(item => {
                    if (seen.has(item.id)) return false;
                    seen.add(item.id);
                    return true;
                })
                .sort((a,b) => b.createdAt - a.createdAt)
                .slice(0,12);

            /*
             * Regrava imediatamente sem o antigo campo image/base64.
             * Se existia histórico v1 com imagens, ele é migrado para JSON
             * e a chave antiga é removida para liberar o espaço ocupado.
             */
            persistHistory();
            if (legacyHistoryKey && legacyHistoryKey !== historyKey) {
                localStorage.removeItem(legacyHistoryKey);
            }
        } catch (error) {
            state.history = [];
        }

        renderHistory();
    }

    function recordRows(record) {
        return (record.rows || []).map(saved => {
            const product = productFor(saved.productId);
            if (!product) return null;

            const entry = {
                productId:Number(saved.productId),
                destinationId:Number(saved.destinationId),
                quantity:Number(saved.quantity || 0),
            };

            const destination = destinationFor(entry,product);
            const price = Number(destination?.price || 0);
            if (!destination || !(price > 0)) return null;

            return {
                entry,
                product,
                destination,
                price,
                quantity:entry.quantity,
                total:entry.quantity * price,
            };
        }).filter(Boolean);
    }

    function currentRecord() {
        const result = totals();
        const positiveRows = result.rows.filter(row => row.quantity > EPSILON);
        if (!positiveRows.length) return null;

        return {
            id:`${Date.now()}-${Math.random().toString(36).slice(2,7)}`,
            createdAt:Date.now(),
            quotaMode:state.quotaMode,
            customQuota:state.customQuota,
            budget:quotaValue(),
            total:positiveRows.reduce((sum,row) => sum + row.total,0),
            useProductLimits:state.useProductLimits,
            rows:positiveRows.map(row => ({
                productId:Number(row.product.product_id),
                destinationId:Number(row.destination.customer_id),
                quantity:row.quantity,
            })),
        };
    }

    function renderHistory() {
        const section = document.getElementById('sim-history');
        section.hidden = state.history.length === 0;

        document.getElementById('sim-history-count').textContent = `${state.history.length} ${state.history.length === 1 ? 'simulação salva neste aparelho' : 'simulações salvas neste aparelho'}`;

        document.getElementById('sim-history-list').innerHTML = state.history.map(item => {
            const rows = recordRows(item);
            const total = rows.reduce((sum,row) => sum + row.total,0);

            return `
                <article class="sim-history-card">
                    <div class="sim-history-card-head">
                        <span class="sim-history-card-icon" aria-hidden="true">
                            <i class="ph-duotone ph-floppy-disk"></i>
                        </span>
                        <div>
                            <strong>${money(total || item.total)} · ${item.rows.length} produto(s)</strong>
                            <span>${new Date(item.createdAt).toLocaleString('pt-BR')} · ${item.useProductLimits ? 'com limites atuais' : 'estudo livre'}</span>
                        </div>
                    </div>

                    <div class="sim-history-actions">
                        <button class="sim-small-button" type="button" data-open-history="${item.id}">
                            <i class="ph ph-arrow-u-up-left"></i>
                            Abrir
                        </button>

                        <button class="sim-small-button primary-soft" type="button" data-share-history="${item.id}">
                            <i class="ph ph-share-network"></i>
                            Compartilhar imagem
                        </button>

                        <button class="sim-small-button" type="button" data-download-history="${item.id}">
                            <i class="ph ph-download-simple"></i>
                            Baixar imagem
                        </button>

                        <button class="sim-small-button danger" type="button" data-delete-history="${item.id}">
                            <i class="ph ph-trash"></i>
                            Excluir
                        </button>
                    </div>
                </article>
            `;
        }).join('');
    }

    function saveCurrentSimulation() {
        const record = currentRecord();
        if (!record) {
            showToast('Defina pelo menos uma quantidade antes de salvar.');
            return;
        }

        state.history = [record,...state.history].slice(0,12);

        if (persistHistory()) {
            renderHistory();
            showToast('Simulação salva neste aparelho.');
        }
    }

    function restoreHistory(item) {
        state.quotaMode = item.quotaMode === 'remaining' ? 'remaining' : 'custom';
        state.customQuota = Number(item.customQuota || item.budget || 0);
        state.useProductLimits = Boolean(item.useProductLimits);
        state.selected.clear();

        item.rows.forEach(row => {
            if (productFor(row.productId)) {
                state.selected.set(Number(row.productId),{
                    productId:Number(row.productId),
                    destinationId:Number(row.destinationId),
                    quantity:Number(row.quantity || 0),
                });
            }
        });

        clampAllEntries();
        setStep(2);
    }

    /* ======================================================
       Imagem gerada somente sob demanda
       ====================================================== */

    function canvasForRecord(record) {
        const rows = recordRows(record).slice(0,12);
        const total = rows.reduce((sum,row) => sum + row.total,0);
        const budget = Number(record.budget || 0);
        const available = Math.max(0,budget - total);

        const scale = 2;
        const logicalWidth = 900;
        const logicalHeight = 350 + rows.length * 68 + ((record.rows || []).length > 12 ? 42 : 0);
        const canvas = document.createElement('canvas');
        canvas.width = logicalWidth * scale;
        canvas.height = logicalHeight * scale;

        const c = canvas.getContext('2d');
        c.scale(scale,scale);
        c.fillStyle = '#ffffff';
        c.fillRect(0,0,logicalWidth,logicalHeight);

        const gradient = c.createLinearGradient(0,0,logicalWidth,0);
        gradient.addColorStop(0,'#168a4d');
        gradient.addColorStop(.5,'#7c3aed');
        gradient.addColorStop(1,'#2563eb');
        c.fillStyle = gradient;
        c.fillRect(0,0,canvas.width,14);

        c.fillStyle = '#102018';
        c.font = '700 30px Arial';
        c.fillText('Simulação de entrega',48,66);

        c.fillStyle = '#52645a';
        c.font = '18px Arial';
        c.fillText(PROJECT_TITLE,48,100);

        c.fillStyle = '#f7faf8';
        c.fillRect(48,132,804,118);

        c.fillStyle = '#809087';
        c.font = '15px Arial';
        c.fillText('Cota',72,168);
        c.fillText('Total simulado',330,168);
        c.fillText('Ainda disponível',610,168);

        c.fillStyle = '#102018';
        c.font = '700 22px Arial';
        c.fillText(money(budget),72,208);
        c.fillText(money(total),330,208);
        c.fillStyle = '#168a4d';
        c.fillText(money(available),610,208);

        c.fillStyle = record.useProductLimits ? '#2563eb' : '#7c3aed';
        c.font = '700 15px Arial';
        c.fillText(record.useProductLimits ? 'Modo: considerando limites atuais' : 'Modo: estudo livre',48,286);

        rows.forEach((row,index) => {
            const y = 330 + index * 68;
            c.strokeStyle = '#dce7e0';
            c.beginPath();
            c.moveTo(48,y + 36);
            c.lineTo(852,y + 36);
            c.stroke();

            c.fillStyle = '#102018';
            c.font = '700 17px Arial';
            c.fillText(String(row.product.product_name).slice(0,38),48,y);

            c.fillStyle = '#52645a';
            c.font = '16px Arial';
            c.fillText(`${quantity(row.quantity)} ${row.product.unit || 'un'}`,500,y);

            c.fillStyle = '#168a4d';
            c.font = '700 17px Arial';
            c.textAlign = 'right';
            c.fillText(money(row.total),852,y);
            c.textAlign = 'left';
        });

        if ((record.rows || []).length > 12) {
            c.fillStyle = '#52645a';
            c.font = '16px Arial';
            c.fillText(`+ ${(record.rows || []).length - 12} produto(s)`,48,logicalHeight - 22);
        }

        return canvas;
    }

    function canvasToBlob(canvas) {
        return new Promise((resolve,reject) => {
            canvas.toBlob(blob => {
                if (blob) resolve(blob);
                else reject(new Error('Não foi possível gerar a imagem.'));
            },'image/png');
        });
    }

    async function imageFileForRecord(record) {
        const blob = await canvasToBlob(canvasForRecord(record));
        return new File(
            [blob],
            `simulacao-entrega-${new Date(record.createdAt || Date.now()).toISOString().slice(0,10)}.png`,
            {type:'image/png'}
        );
    }

    async function shareRecord(record) {
        try {
            const file = await imageFileForRecord(record);
            const shareData = {
                title:'Simulação de entrega',
                text:PROJECT_TITLE,
                files:[file],
            };

            const supported = typeof navigator.share === 'function'
                && (typeof navigator.canShare !== 'function' || navigator.canShare(shareData));

            if (supported) {
                await navigator.share(shareData);
                return;
            }

            downloadRecord(record);
            showToast('Este navegador não compartilha arquivos diretamente. A imagem foi baixada como alternativa.');
        } catch (error) {
            if (error?.name !== 'AbortError') {
                showToast('Não foi possível compartilhar a imagem.');
            }
        }
    }

    async function downloadRecord(record) {
        try {
            const file = await imageFileForRecord(record);
            const url = URL.createObjectURL(file);
            const link = document.createElement('a');
            link.href = url;
            link.download = file.name;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(url),1000);
        } catch (error) {
            showToast('Não foi possível gerar a imagem.');
        }
    }

    async function shareCurrentSimulation() {
        const record = currentRecord();
        if (!record) {
            showToast('Defina pelo menos uma quantidade antes de compartilhar.');
            return;
        }

        await shareRecord(record);
    }

    /* ======================================================
       Eventos
       ====================================================== */

    root.addEventListener('click',event => {
        const quotaButton = event.target.closest('[data-quota-mode]');
        if (quotaButton && !quotaButton.disabled) {
            state.quotaMode = quotaButton.dataset.quotaMode;
            clampAllEntries();
            renderQuota();
        }

        const filterButton = event.target.closest('[data-product-filter]');
        if (filterButton) {
            state.productFilter = filterButton.dataset.productFilter;
            renderProducts();
        }

        const productButton = event.target.closest('[data-product-id]');
        if (productButton) {
            const productId = Number(productButton.dataset.productId);

            if (state.selected.has(productId)) {
                state.selected.delete(productId);
            } else {
                const product = productFor(productId);
                const destination = [...(product?.destinations || [])].sort(
                    (left,right) => Number(right.price) - Number(left.price)
                )[0];

                if (destination) {
                    state.selected.set(productId,{
                        productId,
                        destinationId:Number(destination.customer_id),
                        quantity:0,
                    });
                }
            }

            renderProducts();
        }

        const chipRemove = event.target.closest('[data-remove-selected-chip]');
        if (chipRemove) {
            state.selected.delete(Number(chipRemove.dataset.removeSelectedChip));
            if (state.step === 1) renderProducts();
            if (state.step === 2) renderSimulation();
        }

        const modeButton = event.target.closest('[data-limit-mode]');
        if (modeButton) {
            state.useProductLimits = modeButton.dataset.limitMode === 'configured';
            clampAllEntries();
            renderSimulation();
        }

        const fillButton = event.target.closest('[data-fill-product]');
        if (fillButton) {
            const id = Number(fillButton.dataset.fillProduct);
            const entry = state.selected.get(id);
            if (entry) {
                entry.quantity = maxQuantityFor(id);
                renderSimulation();
            }
        }

        const removeButton = event.target.closest('[data-remove-product]');
        if (removeButton) {
            state.selected.delete(Number(removeButton.dataset.removeProduct));
            renderSimulation();
        }
    });

    root.addEventListener('input',event => {
        if (event.target.id === 'sim-custom-quota') {
            state.customQuota = Math.max(0,Number(String(event.target.value).replace(',','.')) || 0);
            clampAllEntries();
            renderQuota();
            if (state.step === 2) renderSimulation();
        }

        if (event.target.id === 'sim-product-search') {
            state.search = event.target.value || '';
            renderProducts();
        }

        if (event.target.matches('[data-range-product], [data-quantity-product]')) {
            updateQuantity(
                event.target.dataset.rangeProduct || event.target.dataset.quantityProduct,
                event.target.value
            );
        }
    });

    root.addEventListener('change',event => {
        if (event.target.matches('[data-destination-product]')) {
            const id = Number(event.target.dataset.destinationProduct);
            const entry = state.selected.get(id);

            if (entry) {
                entry.destinationId = Number(event.target.value);
                clampEntry(id,{notify:true});
                renderSimulation();
            }
        }
    });

    previousButton.addEventListener('click',() => {
        if (state.step > 0) {
            setStep(state.step - 1);
        }
    });

    nextButton.addEventListener('click',() => {
        if (validateCurrentStep() && state.step < 2) {
            setStep(state.step + 1);
        }
    });

    saveButton.addEventListener('click',saveCurrentSimulation);
    shareButton.addEventListener('click',shareCurrentSimulation);

    document.getElementById('sim-restart').addEventListener('click',() => {
        state.selected.clear();
        state.search = '';
        state.productFilter = 'enabled';
        state.customQuota = 0;
        state.useProductLimits = false;
        state.quotaMode = state.summary.financial_remaining !== null
            && Number(state.summary.financial_remaining) > 0
                ? 'remaining'
                : 'custom';
        setStep(0);
    });

    document.getElementById('sim-clear-history').addEventListener('click',() => {
        state.history = [];
        localStorage.removeItem(historyKey);
        if (legacyHistoryKey) localStorage.removeItem(legacyHistoryKey);
        renderHistory();
    });

    document.getElementById('sim-history-list').addEventListener('click',event => {
        const open = event.target.closest('[data-open-history]');
        const share = event.target.closest('[data-share-history]');
        const download = event.target.closest('[data-download-history]');
        const remove = event.target.closest('[data-delete-history]');

        const id = open?.dataset.openHistory
            || share?.dataset.shareHistory
            || download?.dataset.downloadHistory
            || remove?.dataset.deleteHistory;

        const item = state.history.find(historyItem => historyItem.id === id);
        if (!item) return;

        if (open) restoreHistory(item);
        if (share) shareRecord(item);
        if (download) downloadRecord(item);

        if (remove) {
            state.history = state.history.filter(historyItem => historyItem.id !== id);
            persistHistory();
            renderHistory();
        }
    });

    window.addEventListener('popstate',() => {
        state.step = stepFromLocation();
        renderStep();
    });

    async function load() {
        nextButton.disabled = true;

        try {
            const response = await fetch(root.dataset.endpoint,{
                headers:{
                    Accept:'application/json',
                    'X-Requested-With':'XMLHttpRequest',
                },
                credentials:'same-origin',
                cache:'no-store',
            });

            if (!response.ok) {
                throw new Error('Não foi possível carregar os dados deste projeto.');
            }

            const data = await response.json();
            state.products = Array.isArray(data.products) ? data.products : [];
            state.summary = data.summary || {};

            if (
                state.summary.financial_remaining === null
                || Number(state.summary.financial_remaining) <= 0
            ) {
                state.quotaMode = 'custom';
            }

            readHistory();
            state.step = stepFromLocation();
            setStep(state.step,{historyMode:'replace'});
        } catch (error) {
            showToast(error.message || 'Não foi possível abrir o simulador.');
        } finally {
            nextButton.disabled = false;
        }
    }

    load();
})();
</script>
@endpush
