@extends('layouts.bento')

@section('title', 'Entregas do Projeto')
@section('page-title', 'Histórico de Entregas')
@section('page-subtitle', $project->title)
@section('user-role', 'Registrador')

<div class="pd-modal-scope">
    <!-- Custom Confirm Modal -->
    <div
        id="customConfirmOverlay"
        class="confirm-overlay hidden"
        role="dialog"
        aria-hidden="true"
        aria-modal="true"
        aria-labelledby="confirmTitle"
    >
        <div class="confirm-box">
            <span class="confirm-icon" aria-hidden="true">
                <i class="ph-duotone ph-warning"></i>
            </span>

            <div class="confirm-copy">
                <div class="confirm-title" id="confirmTitle">
                    Confirmar ação
                </div>

                <div
                    class="confirm-message"
                    id="confirmMessage"
                ></div>
            </div>

            <div class="confirm-buttons">
                <button
                    class="btn btn-ghost btn-sm"
                    id="confirmCancel"
                    type="button"
                >
                    Cancelar
                </button>

                <button
                    class="btn btn-sm btn-primary"
                    id="confirmOk"
                    type="button"
                >
                    Confirmar
                </button>
            </div>
        </div>
    </div>

    <div id="pd-toasts" aria-live="polite"></div>
</div>


<x-delivery.dist-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
    :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name])->values()->all()"
/>
{{-- Componentes Blade --}}
<x-delivery.edit-delivery-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
/>
<x-delivery.notes-modal />
@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery',
        'projects',
        $currentTenant->slug ?? request()->route('tenant'),
    );
@endphp

@section('content')
<style>
    .pd-page,
    .pd-modal-scope,
    #pd-toasts,
    .confirm-overlay,
    .modal-overlay,
    .pd-integrity-overlay,
    .dist-summary-overlay {
        --pd-green: #168a4d;
        --pd-green-soft: #eaf8ef;
        --pd-blue: #2563eb;
        --pd-blue-soft: #eef4ff;
        --pd-sky: #0284c7;
        --pd-sky-soft: #edf8fe;
        --pd-violet: #7c3aed;
        --pd-violet-soft: #f4f0ff;
        --pd-amber: #c87408;
        --pd-amber-soft: #fff7e8;
        --pd-red: #cf3f3f;
        --pd-red-soft: #fff0f0;
        --pd-slate: #64748b;
        --pd-slate-soft: #f1f5f9;

        --pd-surface: var(--color-surface, #fff);
        --pd-soft: var(--color-surface-soft, #f8faf9);
        --pd-muted: var(--color-surface-muted, #eef4f0);
        --pd-border: var(--color-border, #dce7e0);
        --pd-border-strong: var(--color-border-strong, #c8d6cd);
        --pd-text: var(--color-text, #102018);
        --pd-text-2: var(--color-text-secondary, #52645a);
        --pd-text-3: var(--color-text-muted, #809087);
        --pd-shadow-sm: 0 4px 14px rgba(15, 35, 24, .045);
        --pd-shadow-md: 0 12px 30px rgba(15, 35, 24, .10);
        --pd-shadow-lg: 0 24px 64px rgba(8, 24, 15, .22);
    }

    .pd-page {
        display: grid;
        width: 100%;
        min-width: 0;
        grid-column: 1 / -1;
        gap: .76rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--pd-text);
    }

    .pd-page *,
    .pd-page *::before,
    .pd-page *::after,
    .pd-modal-scope *,
    .pd-modal-scope *::before,
    .pd-modal-scope *::after,
    .confirm-overlay *,
    .confirm-overlay *::before,
    .confirm-overlay *::after,
    .modal-overlay *,
    .modal-overlay *::before,
    .modal-overlay *::after,
    .pd-integrity-overlay *,
    .pd-integrity-overlay *::before,
    .pd-integrity-overlay *::after,
    .dist-summary-overlay *,
    .dist-summary-overlay *::before,
    .dist-summary-overlay *::after {
        box-sizing: border-box;
    }

    /* ---------- Projeto ---------- */

    .pd-context {
        display: grid;
        min-width: 0;
        grid-template-columns: auto auto minmax(0, 1fr) auto;
        gap: .6rem;
        align-items: center;
        min-height: 70px;
        padding: .66rem .72rem;
        overflow: hidden;
        border: 1px solid var(--pd-border);
        border-radius: 15px;
        background:
            radial-gradient(circle at 100% 0, rgba(200, 116, 8, .08), transparent 17rem),
            linear-gradient(180deg, var(--pd-soft), var(--pd-surface));
        box-shadow: var(--pd-shadow-sm);
    }

    .pd-back,
    .pd-context-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .pd-back {
        border: 1px solid var(--pd-border);
        background: #fff;
        color: var(--pd-text-2);
        text-decoration: none;
        transition: .15s ease;
    }

    .pd-back:hover,
    .pd-back:focus-visible {
        border-color: rgba(37, 99, 235, .25);
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
        outline: none;
        transform: translateX(-1px);
    }

    .pd-context-icon {
        background: var(--pd-amber-soft);
        color: var(--pd-amber);
    }

    .pd-back > i,
    .pd-back > svg,
    .pd-context-icon > i,
    .pd-context-icon > svg {
        width: 17px;
        height: 17px;
    }

    .pd-context-copy {
        min-width: 0;
    }

    .pd-context-kicker {
        display: grid;
        width: max-content;
        grid-template-columns: auto auto;
        gap: .25rem;
        align-items: center;
        color: var(--pd-amber);
        font-size: .68rem;
        font-weight: 800;
    }

    .pd-context-kicker > i,
    .pd-context-kicker > svg {
        width: 13px;
        height: 13px;
    }

    .pd-title {
        margin: .06rem 0 0;
        color: var(--pd-text);
        font-size: clamp(1rem, 2vw, 1.18rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    .pd-sub {
        display: none;
    }

    .pd-header-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .32rem;
        align-items: center;
    }

    /* ---------- Botões ---------- */

    .btn,
    .report-btn,
    .delivery-page-btn,
    .pd-integrity-toggle,
    .pd-integrity-close,
    .dist-summary-close,
    .btn-approve,
    .btn-reject,
    .btn-edit,
    .btn-distribute,
    .btn-delete-approved {
        font-family: inherit;
    }

    .btn {
        display: inline-grid;
        min-height: 38px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .42rem .58rem;
        border: 1px solid transparent;
        border-radius: 9px;
        cursor: pointer;
        font-size: .7rem;
        font-weight: 790;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        transition: .15s ease;
    }

    .btn > i,
    .btn > svg {
        width: 14px;
        height: 14px;
    }

    .btn:hover:not(:disabled),
    .btn:focus-visible:not(:disabled) {
        outline: none;
        transform: translateY(-1px);
    }

    .btn:disabled {
        cursor: not-allowed;
        opacity: .48;
        transform: none;
    }

    .btn-sm {
        min-height: 36px;
        padding: .38rem .5rem;
        font-size: .69rem;
    }

    .btn-xs {
        min-height: 31px;
        padding: .32rem .42rem;
        font-size: .65rem;
    }

    .btn-primary {
        border-color: rgba(37, 99, 235, .17);
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
    }

    .btn-success {
        border-color: rgba(22, 138, 77, .17);
        background: var(--pd-green-soft);
        color: var(--pd-green);
    }

    .btn-danger {
        border-color: rgba(207, 63, 63, .16);
        background: var(--pd-red-soft);
        color: var(--pd-red);
    }

    .btn-ghost {
        border-color: var(--pd-border-strong);
        background: #fff;
        color: var(--pd-text-2);
    }

    .btn-ghost:hover:not(:disabled),
    .btn-ghost:focus-visible:not(:disabled) {
        border-color: rgba(37, 99, 235, .22);
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
    }

    .pd-action-delivery {
        border-color: rgba(200, 116, 8, .18);
        background: var(--pd-amber-soft);
        color: #92400e;
    }

    .pd-action-limits {
        border-color: rgba(124, 58, 237, .17);
        background: var(--pd-violet-soft);
        color: var(--pd-violet);
    }

    .pd-action-producers {
        border-color: rgba(37, 99, 235, .15);
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
    }

    /* ---------- Resumo ---------- */

    .pd-summary {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--pd-border);
        border-radius: 14px;
        background: #fff;
        box-shadow: var(--pd-shadow-sm);
    }

    .pd-stat {
        --stat-tone: var(--pd-blue);
        --stat-soft: var(--pd-blue-soft);
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
        min-height: 66px;
        padding: .48rem .54rem;
    }

    .pd-stat:nth-child(2) {
        --stat-tone: var(--pd-green);
        --stat-soft: var(--pd-green-soft);
    }

    .pd-stat:nth-child(3) {
        --stat-tone: var(--pd-amber);
        --stat-soft: var(--pd-amber-soft);
    }

    .pd-stat:nth-child(4) {
        --stat-tone: var(--pd-red);
        --stat-soft: var(--pd-red-soft);
    }

    .pd-stat:nth-child(5) {
        --stat-tone: var(--pd-violet);
        --stat-soft: var(--pd-violet-soft);
    }

    .pd-stat + .pd-stat {
        border-left: 1px solid var(--pd-border);
    }

    .pd-stat-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
        background: var(--stat-soft);
        color: var(--stat-tone);
    }

    .pd-stat-icon > i,
    .pd-stat-icon > svg {
        width: 15px;
        height: 15px;
    }

    .pd-stat-copy {
        min-width: 0;
    }

    .pd-stat-lbl {
        color: var(--pd-text-3);
        font-size: .64rem;
        font-weight: 700;
        line-height: 1.25;
    }

    .pd-stat-val {
        margin-top: .03rem;
        color: var(--stat-tone);
        font-size: .88rem;
        font-weight: 860;
        letter-spacing: -.02em;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    /* ---------- Superfícies ---------- */

    .pd-card,
    .pd-integrity-panel,
    .reports-bar {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--pd-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: var(--pd-shadow-sm);
    }

    .pd-card-header,
    .pd-panel-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .55rem;
        align-items: center;
        min-height: 58px;
        padding: .68rem .76rem;
        border-bottom: 1px solid var(--pd-border);
        background: linear-gradient(180deg, var(--pd-soft), #fff);
    }

    .pd-card-title,
    .pd-panel-title-wrap {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .46rem;
        align-items: center;
    }

    .pd-card-title > i,
    .pd-card-title > svg,
    .pd-panel-icon {
        display: grid;
        width: 38px !important;
        height: 38px !important;
        place-items: center;
        padding: 10px;
        border-radius: 10px;
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
    }

    .pd-deliveries-card .pd-card-title > i,
    .pd-deliveries-card .pd-card-title > svg {
        background: var(--pd-amber-soft);
        color: var(--pd-amber);
    }

    .pd-panel-icon.warning {
        background: var(--pd-amber-soft);
        color: var(--pd-amber);
    }

    .pd-panel-copy {
        min-width: 0;
    }

    .pd-panel-title,
    .pd-card-title {
        color: var(--pd-text);
        font-size: .88rem;
        font-weight: 840;
        letter-spacing: -.015em;
        line-height: 1.3;
    }

    .pd-panel-sub {
        margin-top: .04rem;
        color: var(--pd-text-3);
        font-size: .67rem;
        line-height: 1.35;
    }

    .pd-card-head-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .3rem;
        align-items: center;
    }

    .pd-pending-chip {
        display: grid;
        min-height: 27px;
        grid-template-columns: auto auto;
        gap: .22rem;
        align-items: center;
        padding: .18rem .38rem;
        border-radius: 999px;
        background: var(--pd-amber-soft);
        color: var(--pd-amber);
        font-size: .64rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .pd-pending-chip > i,
    .pd-pending-chip > svg {
        width: 12px;
        height: 12px;
    }

    /* ---------- Integridade ---------- */

    .pd-integrity-summary {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .28rem;
        align-items: center;
    }

    .pd-integrity-count {
        display: grid;
        min-height: 26px;
        grid-template-columns: auto auto;
        gap: .2rem;
        align-items: center;
        padding: .18rem .34rem;
        border-radius: 999px;
        font-size: .63rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .pd-integrity-count.critical {
        background: var(--pd-red-soft);
        color: var(--pd-red);
    }

    .pd-integrity-count.warning {
        background: var(--pd-amber-soft);
        color: var(--pd-amber);
    }

    .pd-integrity-count.info {
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
    }

    .pd-integrity-toggle,
    .pd-integrity-close,
    .dist-summary-close {
        display: grid;
        width: 100%;
        height: 34px;
        place-items: center;
        border: 1px solid var(--pd-border);
        border-radius: 9px;
        background: #fff;
        color: var(--pd-text-2);
        cursor: pointer;
    }

    .pd-integrity-toggle:hover,
    .pd-integrity-toggle:focus-visible,
    .pd-integrity-close:hover,
    .pd-integrity-close:focus-visible,
    .dist-summary-close:hover,
    .dist-summary-close:focus-visible {
        border-color: rgba(37, 99, 235, .22);
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
        outline: none;
    }

    .pd-integrity-toggle > i,
    .pd-integrity-toggle > svg,
    .pd-integrity-close > i,
    .pd-integrity-close > svg,
    .dist-summary-close > i,
    .dist-summary-close > svg {
        width: 15px;
        height: 15px;
    }

    .pd-integrity-content[hidden] {
        display: none !important;
    }

    .pd-integrity-grid,
    .pd-integrity-body {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0;
        padding: .08rem .64rem .64rem;
    }

    .pd-integrity-column {
        --severity-tone: var(--pd-blue);
        --severity-soft: var(--pd-blue-soft);
        min-width: 0;
        overflow: hidden;
        background: #fff;
    }

    .pd-integrity-column + .pd-integrity-column {
        border-left: 1px solid var(--pd-border);
    }

    .pd-integrity-column.critical {
        --severity-tone: var(--pd-red);
        --severity-soft: var(--pd-red-soft);
    }

    .pd-integrity-column.warning {
        --severity-tone: var(--pd-amber);
        --severity-soft: var(--pd-amber-soft);
    }

    .pd-integrity-column-head {
        display: grid;
        min-height: 36px;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .28rem;
        align-items: center;
        padding: .4rem .5rem;
        background: var(--severity-soft);
        color: var(--severity-tone);
        font-size: .66rem;
        font-weight: 820;
    }

    .pd-integrity-column-head > i,
    .pd-integrity-column-head > svg {
        width: 13px;
        height: 13px;
    }

    .pd-integrity-items {
        display: grid;
    }

    .pd-integrity-item {
        min-width: 0;
        padding: .48rem .52rem;
    }

    .pd-integrity-item + .pd-integrity-item {
        border-top: 1px solid var(--pd-border);
    }

    .pd-integrity-item-title {
        color: var(--pd-text);
        font-size: .73rem;
        font-weight: 810;
        line-height: 1.35;
    }

    .pd-integrity-item-message {
        margin-top: .1rem;
        color: var(--pd-text-2);
        font-size: .68rem;
        line-height: 1.4;
    }

    .pd-integrity-item-action {
        margin-top: .16rem;
        color: var(--severity-tone);
        font-size: .65rem;
        font-weight: 760;
        line-height: 1.35;
    }

    .pd-integrity-actions {
        display: flex;
        gap: .28rem;
        flex-wrap: wrap;
        margin-top: .34rem;
    }

    .pd-integrity-empty {
        padding: .55rem;
        color: var(--pd-text-3);
        font-size: .67rem;
    }

    .pd-issue-focus {
        outline: 2px solid var(--pd-blue);
        outline-offset: -2px;
    }

    /* ---------- Relatórios ---------- */

    .reports-bar {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        padding: .5rem .62rem;
        background: linear-gradient(135deg, #fff, var(--pd-violet-soft));
    }

    .reports-bar-title {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .4rem;
        align-items: center;
        margin: 0;
        color: var(--pd-text);
        font-size: .75rem;
        font-weight: 810;
    }

    .reports-bar-title > i,
    .reports-bar-title > svg {
        width: 32px !important;
        height: 32px !important;
        padding: 8px;
        border-radius: 9px;
        background: var(--pd-violet-soft);
        color: var(--pd-violet);
    }

    .reports-row {
        display: flex;
        gap: .3rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .report-btn {
        display: inline-grid;
        min-height: 34px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .25rem;
        align-items: center;
        justify-content: center;
        padding: .36rem .48rem;
        border: 1px solid var(--pd-border-strong);
        border-radius: 9px;
        background: #fff;
        color: var(--pd-text-2);
        cursor: pointer;
        font-size: .68rem;
        font-weight: 780;
        text-decoration: none;
        white-space: nowrap;
    }

    .report-btn > i,
    .report-btn > svg {
        width: 13px;
        height: 13px;
    }

    .report-generate {
        border-color: rgba(124, 58, 237, .18);
        background: var(--pd-violet-soft);
        color: var(--pd-violet);
    }

    .report-receipts {
        border-color: rgba(100, 116, 139, .18);
        background: var(--pd-slate-soft);
        color: var(--pd-slate);
    }

    /* ---------- Filtros ---------- */

    .filters-bar {
        display: grid;
        min-width: 0;
        gap: 0;
        border-bottom: 1px solid var(--pd-border);
        background: var(--pd-soft);
    }

    .pd-filters-primary {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(230px, 1fr) minmax(150px, 205px) auto;
        gap: .4rem;
        align-items: end;
        padding: .62rem .74rem;
    }

    .pd-filters-advanced {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(170px, 1fr)
            minmax(170px, 1fr)
            minmax(125px, .65fr)
            minmax(125px, .65fr)
            minmax(120px, .58fr);
        gap: .4rem;
        padding: .52rem .64rem;
        border-top: 1px solid var(--pd-border);
        background: linear-gradient(180deg, var(--pd-blue-soft), #fff);
    }

    .pd-filters-advanced[hidden] {
        display: none !important;
    }

    .pd-filter-field {
        display: grid;
        min-width: 0;
        gap: .18rem;
    }

    .pd-filter-label {
        color: var(--pd-text-3);
        font-size: .62rem;
        font-weight: 720;
        line-height: 1.2;
    }

    .pd-filter-control {
        position: relative;
        min-width: 0;
    }

    .pd-filter-control > i,
    .pd-filter-control > svg {
        position: absolute;
        top: 50%;
        left: .58rem;
        width: 14px;
        height: 14px;
        color: var(--pd-text-3);
        pointer-events: none;
        transform: translateY(-50%);
    }

    .filter-input,
    .filter-select,
    .delivery-page-size {
        width: 100%;
        min-width: 0;
        min-height: 39px;
        padding: .46rem .54rem;
        border: 1px solid var(--pd-border-strong);
        border-radius: 9px;
        outline: none;
        background: #fff;
        color: var(--pd-text);
        font: inherit;
        font-size: .71rem;
    }

    .pd-filter-control.has-icon .filter-input {
        padding-left: 1.82rem;
    }

    .filter-input:focus,
    .filter-select:focus,
    .delivery-page-size:focus {
        border-color: var(--pd-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
    }

    .pd-filter-more {
        display: grid;
        min-height: 39px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .24rem;
        align-items: center;
        justify-content: center;
        padding: .43rem .5rem;
        border: 1px solid rgba(37, 99, 235, .16);
        border-radius: 9px;
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
        cursor: pointer;
        font: inherit;
        font-size: .68rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .pd-filter-more:hover,
    .pd-filter-more:focus-visible,
    .pd-filter-more.open {
        border-color: rgba(37, 99, 235, .28);
        outline: none;
    }

    .pd-filter-more.has-active {
        border-color: rgba(124, 58, 237, .18);
        background: var(--pd-violet-soft);
        color: var(--pd-violet);
    }

    .pd-filter-more > i,
    .pd-filter-more > svg {
        width: 13px;
        height: 13px;
    }

    .pd-filter-more-count {
        display: grid;
        min-width: 19px;
        height: 19px;
        place-items: center;
        padding: 0 .22rem;
        border-radius: 999px;
        background: #fff;
        color: currentColor;
        font-size: .61rem;
        font-weight: 850;
    }

    .pd-filter-more-count[hidden] {
        display: none;
    }

    /* ---------- Tabela desktop ---------- */

    .table-scroll {
        min-width: 0;
        overflow-x: auto;
        background: #fff;
        scrollbar-width: thin;
    }

    .data-table {
        width: 100%;
        min-width: 1040px;
        border-collapse: separate;
        border-spacing: 0;
        color: var(--pd-text);
        font-size: .73rem;
    }

    .data-table thead {
        position: sticky;
        z-index: 4;
        top: 0;
    }

    .data-table th {
        padding: .58rem .64rem;
        border-bottom: 1px solid var(--pd-border);
        background: #f7f9f8;
        color: var(--pd-text-3);
        font-size: .63rem;
        font-weight: 770;
        text-align: left;
        white-space: nowrap;
    }

    .data-table td {
        padding: .62rem .64rem;
        border-bottom: 1px solid var(--pd-border);
        background: #fff;
        vertical-align: middle;
    }

    .data-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .data-table tbody tr:hover td {
        background: color-mix(in srgb, var(--pd-blue-soft) 30%, #fff);
    }

    /* Seleção para comprovante removida desta tela. */
    .chk-cell,
    .mc-chk {
        display: none !important;
    }


    /* ---------- Status e ações ---------- */

    .badge-status {
        display: inline-grid;
        min-height: 24px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .2rem;
        align-items: center;
        padding: .17rem .34rem;
        border-radius: 999px;
        font-size: .62rem;
        font-weight: 810;
        white-space: nowrap;
    }

    .badge-status.pending {
        background: var(--pd-amber-soft);
        color: var(--pd-amber);
    }

    .badge-status.approved {
        background: var(--pd-green-soft);
        color: var(--pd-green);
    }

    .badge-status.rejected {
        background: var(--pd-red-soft);
        color: var(--pd-red);
    }

    .badge-status.cancelled {
        background: var(--pd-slate-soft);
        color: var(--pd-slate);
    }

    .action-btns,
    .mc-actions {
        display: flex;
        gap: .24rem;
        flex-wrap: wrap;
        align-items: center;
    }

    .btn-approve,
    .btn-reject,
    .btn-edit,
    .btn-distribute,
    .btn-delete-approved {
        display: inline-grid;
        min-height: 30px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .2rem;
        align-items: center;
        justify-content: center;
        padding: .3rem .4rem;
        border: 1px solid transparent;
        border-radius: 8px;
        cursor: pointer;
        font-size: .64rem;
        font-weight: 770;
        line-height: 1;
        white-space: nowrap;
        transition: .14s ease;
    }

    .btn-approve:hover:not(:disabled),
    .btn-reject:hover:not(:disabled),
    .btn-edit:hover:not(:disabled),
    .btn-distribute:hover:not(:disabled),
    .btn-delete-approved:hover:not(:disabled) {
        transform: translateY(-1px);
    }

    .btn-approve {
        border-color: rgba(22, 138, 77, .15);
        background: var(--pd-green-soft);
        color: var(--pd-green);
    }

    .btn-reject {
        border-color: rgba(207, 63, 63, .15);
        background: var(--pd-red-soft);
        color: var(--pd-red);
    }

    .btn-edit {
        border-color: rgba(37, 99, 235, .15);
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
    }

    .btn-distribute {
        border-color: rgba(124, 58, 237, .18);
        background: var(--pd-violet-soft);
        color: var(--pd-violet);
    }

    .btn-distribute:hover:not(:disabled),
    .btn-distribute:focus-visible:not(:disabled) {
        border-color: rgba(124, 58, 237, .30);
        background: color-mix(in srgb, var(--pd-violet-soft) 78%, #fff);
        color: var(--pd-violet);
        outline: none;
    }

    .btn-delete-approved {
        border-color: rgba(207, 63, 63, .12);
        background: #fff;
        color: var(--pd-red);
    }

    .btn-approve:disabled,
    .btn-reject:disabled,
    .btn-edit:disabled,
    .btn-distribute:disabled,
    .btn-delete-approved:disabled {
        cursor: not-allowed;
        opacity: .48;
    }

    .action-btns > button > svg,
    .action-btns > button > i {
        width: 13px;
        height: 13px;
        flex: 0 0 auto;
    }

    .action-btns .pd-action-label {
        display: inline;
    }


    /* ---------- Distribuição ---------- */

    .dist-indicator,
    .mc-dist-indicator {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto auto;
        gap: .28rem;
        align-items: center;
        padding: .13rem .2rem;
        border-radius: 7px;
        background: var(--pd-sky-soft);
        cursor: pointer;
    }

    .dist-bar-bg,
    .mc-dist-bar-bg {
        width: 62px;
        height: 6px;
        overflow: hidden;
        border-radius: 999px;
        background: color-mix(in srgb, var(--pd-sky-soft) 70%, var(--pd-border));
    }

    .dist-bar-fill,
    .mc-dist-bar-fill {
        height: 100%;
        border-radius: inherit;
        transition: width .3s ease;
    }

    .dist-bar-fill.full,
    .mc-dist-bar-fill.full {
        background: var(--pd-green);
    }

    .dist-bar-fill.partial,
    .mc-dist-bar-fill.partial {
        background: var(--pd-amber);
    }

    .dist-bar-fill.over,
    .mc-dist-bar-fill.over {
        background: var(--pd-red);
    }

    .dist-text,
    .mc-dist-text {
        min-width: 31px;
        color: var(--pd-text-2);
        font-size: .63rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .dist-warning {
        display: block;
        margin-top: .07rem;
        color: var(--pd-red);
        font-size: .61rem;
        font-weight: 730;
        line-height: 1.3;
    }

    /* ---------- Mobile cards ---------- */

    .mobile-cards {
        display: none;
        gap: .5rem;
        padding: .62rem;
        background: var(--pd-soft);
    }

    .mobile-card {
        --delivery-state: var(--pd-slate);
        --delivery-state-bg: var(--pd-slate-soft);
        min-width: 0;
        padding: .68rem;
        border: 1px solid var(--pd-border);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(15, 35, 24, .035);
        font-size: .72rem;
    }

    .mobile-card + .mobile-card {
        margin-top: 0;
    }

    .mobile-card.status-pending {
        --delivery-state: var(--pd-amber);
        --delivery-state-bg: var(--pd-amber-soft);
    }

    .mobile-card.status-approved {
        --delivery-state: var(--pd-sky);
        --delivery-state-bg: var(--pd-sky-soft);
    }
    
    .mobile-card.status-distributed {
        --delivery-state: var(--pd-green);
        --delivery-state-bg: var(--pd-green-soft);
    }

    .mobile-card.status-rejected {
        --delivery-state: var(--pd-red);
        --delivery-state-bg: var(--pd-red-soft);
    }

    .mobile-card.status-cancelled {
        --delivery-state: var(--pd-slate);
        --delivery-state-bg: var(--pd-slate-soft);
    }

    .mc-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto auto;
        gap: .34rem;
        align-items: center;
        padding: 0 .02rem .38rem;
    }

    .mc-state-icon {
        display: grid;
        width: 24px;
        height: 24px;
        place-items: center;
        border-radius: 8px;
        background: var(--delivery-state-bg);
        color: var(--delivery-state);
    }

    .mc-state-icon > i,
    .mc-state-icon > svg {
        width: 18px;
        height: 18px;
        font-size: 18px !important;
    }

    .mc-head-main {
        display: flex;
        min-width: 0;
        gap: .22rem;
        align-items: center;
        overflow: hidden;
    }

    .mc-head-line {
        display: contents;
    }

    .mc-date {
        color: var(--pd-text-3);
        font-size: .65rem;
        font-weight: 720;
        white-space: nowrap;
    }

    .mc-sep {
        color: var(--pd-text-3);
        opacity: .6;
    }

    .mc-head-product {
        min-width: 0;
        overflow: hidden;
        color: var(--pd-text);
        font-size: .75rem;
        font-weight: 830;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .mc-head-qty {
        color: var(--pd-text-2);
        font-size: .66rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .mc-body {
        display: grid;
        gap: .44rem;
        padding: .52rem .56rem;
        border-radius: 10px;
        background: var(--pd-soft);
    }

    .mc-row {
        display: flex;
        min-width: 0;
        gap: .34rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .mc-chk {
        min-width: 18px;
    }

    .mc-status {
        margin: 0;
    }

    .mc-assoc {
        color: var(--pd-text-2);
        font-size: .69rem;
        line-height: 1.35;
    }

    .mc-product {
        color: var(--pd-text);
        font-size: .73rem;
        font-weight: 770;
    }

    .mc-details {
        display: flex;
        gap: .5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .mc-qty {
        color: var(--pd-text);
        font-weight: 800;
    }

    .mc-net {
        color: var(--pd-green);
        font-weight: 800;
    }

    .mc-actions {
        gap: .34rem;
        margin-top: .12rem;
        padding-top: .42rem;
        border-top: 1px solid var(--pd-border);
    }

    .mc-actions > .btn-approve,
    .mc-actions > .btn-reject,
    .mc-actions > .btn-edit,
    .mc-actions > .btn-distribute,
    .mc-actions > .btn-delete-approved {
        width: 36px;
        min-width: 36px;
        height: 36px;
        min-height: 36px;
        padding: 0;
        border-radius: 10px;
        font-size: 0;
    }

    .mc-actions > button > svg,
    .mc-actions > button > i {
        width: 16px !important;
        height: 16px !important;
        margin: 0 !important;
    }

    .mc-actions .pd-action-label {
        display: none !important;
    }

    /* ---------- Paginação ---------- */

    .delivery-pagination {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        padding: .62rem .74rem;
        border-top: 1px solid var(--pd-border);
        background: linear-gradient(180deg, #fff, var(--pd-soft));
    }

    .delivery-pagination-info {
        color: var(--pd-text-3);
        font-size: .67rem;
        font-weight: 690;
    }

    .delivery-pagination-actions {
        display: flex;
        gap: .28rem;
    }

    .delivery-page-btn {
        min-height: 34px;
        padding: .36rem .48rem;
        border: 1px solid var(--pd-border-strong);
        border-radius: 9px;
        background: #fff;
        color: var(--pd-text-2);
        cursor: pointer;
        font-size: .67rem;
        font-weight: 770;
    }

    .delivery-page-btn:hover:not(:disabled),
    .delivery-page-btn:focus-visible:not(:disabled) {
        border-color: rgba(37, 99, 235, .22);
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
        outline: none;
    }

    .delivery-page-btn:disabled {
        cursor: not-allowed;
        opacity: .42;
    }

    /* ---------- Vazios ---------- */

    .pd-empty {
        display: grid;
        min-height: 160px;
        place-items: center;
        padding: 1rem;
        background: var(--pd-soft);
        color: var(--pd-text-2);
        text-align: center;
    }

    .pd-empty-icon {
        width: 42px;
        height: 42px;
        margin-bottom: .3rem;
        padding: 10px;
        border-radius: 12px;
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
    }

    .pd-empty p {
        margin: 0;
        color: var(--pd-text-2);
        font-size: .72rem;
    }

    /* ---------- Modais ---------- */

    .modal-overlay,
    .confirm-overlay,
    .pd-integrity-overlay,
    .dist-summary-overlay {
        position: fixed;
        z-index: 100000;
        inset: 0;
        display: grid;
        place-items: center;
        padding:
            max(14px, env(safe-area-inset-top))
            max(12px, env(safe-area-inset-right))
            max(14px, env(safe-area-inset-bottom))
            max(12px, env(safe-area-inset-left));
        overflow: auto;
        background: rgba(8, 24, 15, .52);
        backdrop-filter: blur(2px);
    }

    .modal-overlay.hidden,
    .confirm-overlay.hidden {
        display: none;
    }

    .pd-integrity-overlay,
    .dist-summary-overlay {
        display: none;
    }

    .pd-integrity-overlay {
        z-index: 320000;
    }

    .dist-summary-overlay {
        z-index: 310000;
    }

    .pd-integrity-overlay.open,
    .dist-summary-overlay.open {
        display: grid;
    }

    .modal-box,
    .confirm-box,
    .pd-integrity-box,
    .dist-summary-box {
        width: min(100%, 500px);
        max-height: min(90dvh, 760px);
        overflow: auto;
        border: 1px solid var(--pd-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: var(--pd-shadow-lg);
    }

    .modal-box {
        padding: .72rem;
    }

    .confirm-box {
        display: grid;
        width: min(100%, 420px);
        grid-template-columns: auto minmax(0, 1fr);
        gap: .14rem .5rem;
        padding: .7rem;
    }

    .confirm-icon {
        display: grid;
        width: 38px;
        height: 38px;
        grid-column: 1;
        grid-row: 1;
        place-items: center;
        border-radius: 10px;
        background: var(--pd-amber-soft);
        color: var(--pd-amber);
    }

    .confirm-icon > i,
    .confirm-icon > svg {
        width: 18px;
        height: 18px;
    }

    .confirm-copy {
        min-width: 0;
    }

    .confirm-title {
        color: var(--pd-text);
        font-size: .81rem;
        font-weight: 830;
    }

    .confirm-message {
        margin-top: .07rem;
        color: var(--pd-text-2);
        font-size: .71rem;
        line-height: 1.45;
    }

    .confirm-buttons {
        display: flex;
        grid-column: 1 / -1;
        gap: .34rem;
        justify-content: flex-end;
        margin-top: .54rem;
        padding-top: .54rem;
        border-top: 1px solid var(--pd-border);
    }

    .modal-title {
        display: flex;
        gap: .34rem;
        align-items: center;
        margin: 0 0 .65rem;
        color: var(--pd-text);
        font-size: .86rem;
        font-weight: 830;
    }

    .form-group {
        margin-bottom: .64rem;
    }

    .form-label {
        display: block;
        margin-bottom: .22rem;
        color: var(--pd-text-2);
        font-size: .67rem;
        font-weight: 730;
    }

    .form-control {
        width: 100%;
        min-height: 40px;
        padding: .48rem .56rem;
        border: 1px solid var(--pd-border-strong);
        border-radius: 9px;
        outline: none;
        background: #fff;
        color: var(--pd-text);
        font: inherit;
        font-size: .73rem;
    }

    .form-control:focus {
        border-color: var(--pd-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .08);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .46rem;
    }

    .modal-footer {
        display: flex;
        gap: .34rem;
        justify-content: flex-end;
        margin-top: .68rem;
        padding-top: .58rem;
        border-top: 1px solid var(--pd-border);
    }

    .pd-integrity-box {
        width: min(880px, 100%);
    }

    .pd-integrity-head,
    .dist-summary-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .52rem;
        align-items: center;
        padding: .58rem .64rem;
        border-bottom: 1px solid var(--pd-border);
        background: linear-gradient(180deg, var(--pd-soft), #fff);
    }

    .pd-integrity-head-main,
    .dist-summary-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .pd-integrity-title,
    .dist-summary-title {
        color: var(--pd-text);
        font-size: .84rem;
        font-weight: 840;
    }

    .pd-integrity-sub,
    .dist-summary-sub {
        margin-top: .03rem;
        color: var(--pd-text-3);
        font-size: .66rem;
        line-height: 1.35;
    }

    .dist-summary-box {
        width: min(460px, 100%);
    }

    .dist-summary-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 10px;
        background: var(--pd-sky-soft);
        color: var(--pd-sky);
    }

    .dist-summary-icon > i,
    .dist-summary-icon > svg {
        width: 17px;
        height: 17px;
    }

    .dist-summary-body {
        display: grid;
        gap: .34rem;
        padding: .54rem .58rem .62rem;
    }

    .dist-summary-row {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .55rem;
        align-items: center;
        padding: .44rem .48rem;
        border-radius: 9px;
        background: linear-gradient(135deg, #fff, var(--pd-sky-soft));
        font-size: .7rem;
    }

    .dist-summary-row strong {
        color: var(--pd-text);
        font-weight: 780;
        overflow-wrap: anywhere;
    }

    .dist-summary-row span {
        color: var(--pd-text-2);
        white-space: nowrap;
    }

    /* ---------- Toast ---------- */

    #pd-toasts {
        position: fixed;
        z-index: 99999;
        right: 1rem;
        bottom: max(1rem, env(safe-area-inset-bottom));
        display: grid;
        width: min(350px, calc(100% - 2rem));
        gap: .4rem;
    }

    .pd-toast {
        display: grid;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .46rem;
        align-items: center;
        padding: .56rem .6rem;
        border: 1px solid var(--pd-border);
        border-radius: 11px;
        background: #fff;
        box-shadow: var(--pd-shadow-md);
        animation: pd-toast-in .2s ease;
    }

    .pd-toast-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 9px;
        background: var(--pd-green-soft);
        color: var(--pd-green);
    }

    .pd-toast.error .pd-toast-icon {
        background: var(--pd-red-soft);
        color: var(--pd-red);
    }

    .pd-toast.info .pd-toast-icon {
        background: var(--pd-blue-soft);
        color: var(--pd-blue);
    }

    .pd-toast-icon > i,
    .pd-toast-icon > svg {
        width: 15px;
        height: 15px;
    }

    .pd-toast-message {
        color: var(--pd-text);
        font-size: .7rem;
        font-weight: 690;
        line-height: 1.4;
    }

    @keyframes pd-toast-in {
        from {
            opacity: 0;
            transform: translateY(5px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ---------- Responsivo ---------- */

    @media (max-width: 1080px) {
        .pd-summary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .pd-stat:nth-child(4),
        .pd-stat:nth-child(5) {
            border-top: 1px solid var(--pd-border);
        }

        .pd-stat:nth-child(4) {
            border-left: 0;
        }

        .pd-filters-advanced {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .pd-integrity-grid,
        .pd-integrity-body {
            grid-template-columns: 1fr;
        }

        .pd-integrity-column + .pd-integrity-column {
            border-top: 1px solid var(--pd-border);
            border-left: 0;
        }
    }

    @media (max-width: 900px) {
        .pd-context {
            grid-template-columns: auto auto minmax(0, 1fr);
        }

        .pd-header-actions {
            grid-column: 3;
            grid-row: 2;
            justify-content: start;
        }

        .reports-bar {
            grid-template-columns: 1fr;
        }

        .reports-row {
            justify-content: flex-start;
        }
    }

    @media (max-width: 767px) {
        .desktop-only {
            display: none !important;
        }

        .mobile-cards {
            display: grid !important;
        }

        .pd-page {
            gap: .66rem;
        }

        .pd-context {
            grid-template-columns: 36px minmax(0, 1fr);
            padding: .58rem;
        }

        .pd-back {
            width: 36px;
            height: 36px;
        }

        .pd-context-icon {
            display: none;
        }

        .pd-context-kicker {
            font-size: .64rem;
        }

        .pd-title {
            font-size: .98rem;
        }

        .pd-header-actions {
            grid-column: 1 / -1;
            grid-row: auto;
            width: 100%;
            grid-template-columns: 1fr 1fr;
            grid-auto-flow: row;
        }

        .pd-header-actions .btn {
            width: 100%;
        }

        .pd-header-actions .pd-action-delivery {
            grid-column: 1 / -1;
            min-height: 41px;
        }

        .pd-summary {
            display: flex;
            gap: .38rem;
            overflow-x: auto;
            padding: .38rem;
            border-radius: 13px;
            scrollbar-width: none;
            scroll-snap-type: x proximity;
        }

        .pd-summary::-webkit-scrollbar {
            display: none;
        }

        .pd-stat {
            min-width: 138px;
            min-height: 62px;
            padding: .44rem .48rem;
            border: 0 !important;
            border-radius: 10px;
            background: color-mix(in srgb, var(--stat-soft) 65%, #fff);
            scroll-snap-align: start;
        }

        .pd-stat-icon {
            width: 31px;
            height: 31px;
        }

        .pd-card-header,
        .pd-panel-head {
            padding: .64rem;
        }

        .pd-panel-sub {
            display: none;
        }

        .pd-card-head-actions {
            justify-content: start;
        }

        .pd-integrity-summary {
            grid-template-columns: repeat(3, max-content) auto;
            grid-auto-flow: row;
        }

        .reports-bar {
            padding: .5rem .56rem;
        }

        .reports-row {
            display: grid;
            width: 100%;
            grid-template-columns: 1fr 1fr;
        }

        .report-btn {
            width: 100%;
        }

        .pd-filters-primary {
            grid-template-columns: 1fr 1fr;
            padding: .5rem .58rem;
        }

        .pd-filter-search {
            grid-column: 1 / -1;
        }

        .pd-filter-more {
            width: 100%;
        }

        .pd-filters-advanced {
            grid-template-columns: 1fr 1fr;
            padding: .5rem .58rem;
        }

        .mobile-cards {
            gap: .5rem;
            padding: .62rem;
        }

        .delivery-pagination {
            grid-template-columns: 1fr;
            padding: .52rem .58rem;
        }

        .delivery-pagination-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .delivery-page-btn {
            width: 100%;
        }

        #pd-toasts {
            right: .62rem;
            bottom: calc(5rem + env(safe-area-inset-bottom));
            width: calc(100% - 1.24rem);
        }

        .modal-overlay,
        .confirm-overlay,
        .pd-integrity-overlay,
        .dist-summary-overlay {
            align-items: end;
            padding: 0;
        }

        .modal-box,
        .confirm-box,
        .pd-integrity-box,
        .dist-summary-box {
            width: 100%;
            max-height: 92svh;
            border-radius: 16px 16px 0 0;
        }

        .confirm-box {
            padding-bottom: calc(.7rem + env(safe-area-inset-bottom));
        }

        .pd-integrity-body {
            padding-bottom: calc(.64rem + env(safe-area-inset-bottom));
        }

        .dist-summary-body {
            padding-bottom: calc(.62rem + env(safe-area-inset-bottom));
        }
    }

    @media (max-width: 520px) {
        .pd-card-header {
            grid-template-columns: 1fr;
        }

        .pd-card-head-actions {
            width: 100%;
        }

        .pd-pending-chip {
            width: max-content;
        }

        .pd-integrity-summary {
            grid-template-columns: 1fr 1fr;
        }

        .pd-integrity-toggle {
            justify-self: end;
        }

        .pd-filters-primary,
        .pd-filters-advanced {
            grid-template-columns: 1fr 1fr;
        }

        .pd-filter-search {
            grid-column: 1 / -1;
        }

        .pd-filter-more {
            grid-column: auto;
        }

        .pd-filter-page-size {
            grid-column: 1 / -1;
        }

        .mc-head {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .mc-state-icon {
            display: none;
        }

        .mc-details {
            gap: .34rem .5rem;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .confirm-buttons,
        .modal-footer {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .confirm-buttons .btn,
        .modal-footer .btn {
            width: 100%;
        }
    }

    @media (max-width: 380px) {
        .pd-header-actions {
            grid-template-columns: 1fr;
        }

        .pd-header-actions .pd-action-delivery {
            grid-column: auto;
        }

        .pd-filters-primary,
        .pd-filters-advanced {
            grid-template-columns: 1fr;
        }

        .pd-filter-search,
        .pd-filter-page-size {
            grid-column: auto;
        }

        .reports-row,
        .confirm-buttons,
        .modal-footer {
            grid-template-columns: 1fr;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .pd-page *,
        .pd-page *::before,
        .pd-page *::after,
        .pd-modal-scope *,
        .pd-modal-scope *::before,
        .pd-modal-scope *::after,
        .confirm-overlay *,
        .confirm-overlay *::before,
        .confirm-overlay *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<style id="pd-standardized-ux">
/* ================================================================
   Project deliveries — padrão operacional SGC
   Mantém a camada funcional existente e reduz informação passiva.
   ================================================================ */
.pd-page,
.pd-modal-scope,
.pd-integrity-overlay,
.dist-summary-overlay {
    --pdx-green:#168a4d;--pdx-green-soft:#eaf8ef;
    --pdx-blue:#2563eb;--pdx-blue-soft:#eef4ff;
    --pdx-violet:#7c3aed;--pdx-violet-soft:#f4f0ff;
    --pdx-amber:#c87408;--pdx-amber-soft:#fff7e8;
    --pdx-red:#cf3f3f;--pdx-red-soft:#fff0f0;
    --pdx-slate:#64748b;--pdx-slate-soft:#f1f5f9;
}

.pd-page { gap:.62rem; }

/* Cabeçalho mais operacional, sem virar uma faixa de botões coloridos. */
.pd-context {
    min-height:64px;
    padding:.56rem .62rem;
    background:
        radial-gradient(circle at 100% 0,rgba(124,58,237,.07),transparent 16rem),
        linear-gradient(180deg,var(--pd-soft),#fff);
}
.pd-context-icon { background:var(--pdx-violet-soft);color:var(--pdx-violet); }
.pd-context-kicker { color:var(--pdx-violet);font-size:.63rem; }
.pd-title { font-size:clamp(.98rem,1.8vw,1.12rem); }
.pd-header-actions .btn { background:#fff;border-color:var(--pd-border);color:var(--pd-text-2); }
.pd-header-actions .pd-action-delivery { background:var(--pdx-green-soft);border-color:rgba(22,138,77,.15);color:var(--pdx-green); }
.pd-header-actions .pd-action-limits { color:var(--pdx-violet); }

/* Uma única barra de ferramentas: relatório, comprovantes e pendências reais. */
.pd-tools-bar {
    display:flex;
    min-width:0;
    align-items:center;
    justify-content:space-between;
    gap:.55rem;
    padding:.48rem .56rem;
    border:1px solid var(--pd-border);
    border-radius:12px;
    background:linear-gradient(135deg,#fff,var(--pdx-violet-soft));
    box-shadow:var(--pd-shadow-sm);
}
.pd-tools-copy { display:flex;min-width:0;align-items:center;gap:.42rem; }
.pd-tools-icon {
    display:inline-flex;width:32px;height:32px;align-items:center;justify-content:center;flex:0 0 auto;
    border-radius:9px;background:#fff;color:var(--pdx-violet);border:1px solid rgba(124,58,237,.10);line-height:0;
}
.pd-tools-icon svg,.pd-tools-icon i { display:block;width:14px;height:14px;margin:0; }
.pd-tools-title { font-size:.72rem;font-weight:820;color:var(--pd-text);line-height:1.2; }
.pd-tools-sub { margin-top:.03rem;font-size:.61rem;color:var(--pd-text-3);line-height:1.3; }
.pd-tools-actions { display:flex;gap:.28rem;align-items:center;justify-content:flex-end;flex-wrap:wrap; }
.pd-tool-action {
    display:inline-flex;min-height:34px;align-items:center;justify-content:center;gap:.26rem;
    padding:.34rem .44rem;border:1px solid var(--pd-border);border-radius:8px;background:#fff;color:var(--pd-text-2);
    font:inherit;font-size:.64rem;font-weight:780;text-decoration:none;cursor:pointer;white-space:nowrap;
}
.pd-tool-action:hover,.pd-tool-action:focus-visible { outline:none;background:var(--pd-soft);border-color:var(--pd-border-strong); }
.pd-tool-action svg,.pd-tool-action i { display:block;width:13px;height:13px;margin:0; }
.pd-tool-integrity { border-color:rgba(200,116,8,.18);background:var(--pdx-amber-soft);color:#92400e; }
.pd-tool-count {
    display:inline-flex;min-width:20px;height:20px;align-items:center;justify-content:center;padding:0 .24rem;
    border-radius:999px;background:#fff;color:currentColor;font-size:.58rem;font-weight:860;
}
.pd-integrity-overlay .pd-integrity-body { grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
.pd-integrity-overlay .pd-integrity-column[hidden] { display:none!important; }

/* A antiga seção de métricas foi substituída por filtros que fazem algo. */
.pd-deliveries-card .pd-card-header { min-height:54px;padding:.54rem .62rem; }
.pd-card-head-actions { display:flex;min-width:0;align-items:center;justify-content:flex-end;gap:.35rem; }
.pd-status-shortcuts { display:flex;min-width:0;gap:.22rem;align-items:center;overflow-x:auto;scrollbar-width:none; }
.pd-status-shortcuts::-webkit-scrollbar { display:none; }
.pd-status-shortcut {
    display:inline-flex;min-height:31px;align-items:center;justify-content:center;gap:.2rem;padding:.28rem .38rem;
    border:1px solid var(--pd-border);border-radius:8px;background:#fff;color:var(--pd-text-2);
    font:inherit;font-size:.61rem;font-weight:760;cursor:pointer;white-space:nowrap;
}
.pd-status-shortcut svg,.pd-status-shortcut i { display:block;width:12px;height:12px;margin:0; }
.pd-status-shortcut strong { font-size:.6rem;font-weight:860;color:currentColor; }
.pd-status-shortcut.active { border-color:rgba(37,99,235,.16);background:var(--pdx-blue-soft);color:var(--pdx-blue); }
.pd-status-shortcut.pending:not(.active) { color:var(--pdx-amber); }
.pd-status-shortcut.approved:not(.active) { color:var(--pdx-green); }
.pd-status-shortcut.rejected:not(.active) { color:var(--pdx-red); }
.pd-status-shortcut[hidden] { display:none!important; }
.pd-clear-filters { flex:0 0 auto;min-width:34px; }

/* Filtros menores, mantendo os avançados recolhíveis. */
.pd-filters-primary { grid-template-columns:minmax(220px,1fr) minmax(140px,185px) auto;padding:.5rem .6rem; }
.pd-filters-advanced { padding:.46rem .6rem; }
.filter-input,.filter-select,.delivery-page-size { min-height:37px; }

/* TABELA: qualidade/status saem; ações entram em distribuição. */
.table-scroll { overflow-x:hidden; }
.data-table { width:100%;min-width:0;table-layout:fixed;font-size:.7rem; }
.data-table th,.data-table td { padding:.52rem .48rem; }
.data-table th:nth-child(1) { width:82px; }
.data-table th:nth-child(2) { width:19%; }
.data-table th:nth-child(3) { width:17%; }
.data-table th:nth-child(4) { width:104px; }
.data-table th:nth-child(5) { width:112px; }
.data-table th:nth-child(8) { width:165px; }
.data-table th:nth-child(9) { width:242px; }
.pd-col-suppressed,.pd-action-cell,.chk-cell { display:none!important; }
#desktop-tbody tr[data-filter-status="pending"] .pd-date-cell { box-shadow:inset 3px 0 0 rgba(200,116,8,.52); }
#desktop-tbody tr[data-filter-status="approved"] .pd-date-cell { box-shadow:inset 3px 0 0 rgba(22,138,77,.42); }
#desktop-tbody tr[data-filter-status="rejected"] .pd-date-cell { box-shadow:inset 3px 0 0 rgba(207,63,63,.46); }
#desktop-tbody td { overflow:hidden; }
#desktop-tbody td:nth-child(3),#desktop-tbody td:nth-child(4) { text-overflow:ellipsis;white-space:nowrap; }
.pd-limit-cell { min-width:0!important;overflow:visible!important; }
.pd-limit-cell > div,.pd-limit-cell .pdr-limit { width:100%!important;min-width:0!important;max-width:none!important; }
.pd-limit-cell .pdr-limit-head { gap:.2rem!important; }
.pd-limit-cell .pdr-limit-used,.pd-limit-cell .pdr-limit-free { font-size:.61rem!important; }
.pd-limit-cell .pdr-limit-track { width:100%!important;height:6px!important; }
.pd-control-cell { overflow:visible!important; }
.pd-table-control-row {
    display:flex;min-width:0;align-items:center;justify-content:flex-start;gap:.3rem;flex-wrap:nowrap;
}
.pd-table-control-row .dist-indicator,.pd-table-control-row .pdr-dist { min-width:92px!important;max-width:122px!important;flex:1 1 100px; }
.pd-table-control-row .action-btns { display:flex;gap:.18rem;align-items:center;flex:0 0 auto;flex-wrap:nowrap; }
.pd-table-control-row .action-btns button,
.pd-table-control-row > .delivery-note-trigger {
    width:31px!important;min-width:31px!important;height:31px!important;min-height:31px!important;padding:0!important;
    display:inline-flex!important;align-items:center!important;justify-content:center!important;border-radius:8px!important;font-size:0!important;line-height:0!important;
}
.pd-table-control-row .action-btns button svg,
.pd-table-control-row .action-btns button i,
.pd-table-control-row > .delivery-note-trigger svg,
.pd-table-control-row > .delivery-note-trigger i { width:13px!important;height:13px!important;margin:0!important; }
.pd-table-control-row .pd-action-label { display:none!important; }

/* Cards: preserva a leitura confortável e põe progresso + ações juntos. */
.mobile-cards { background:var(--pd-soft); }
.mobile-card {
    padding:0!important;overflow:hidden;border-radius:12px!important;border:1px solid var(--pd-border)!important;
    border-left:3px solid var(--delivery-state)!important;background:#fff!important;
}
.mobile-card .mc-head { min-height:45px;padding:.5rem .58rem!important;background:linear-gradient(90deg,var(--delivery-state-bg),#fff 72%)!important;border-bottom:1px solid var(--pd-border); }
.mobile-card .mc-head-product { font-size:.79rem!important; }
.mobile-card .mc-body { gap:.48rem!important;padding:.56rem .58rem .6rem!important;background:#fff!important; }
.mobile-card .mc-assoc { font-size:.74rem!important;font-weight:790!important; }
.mobile-card .mc-net { font-size:.7rem!important; }
.pd-card-control-row {
    display:flex;min-width:0;align-items:center;gap:.4rem;flex-wrap:wrap;margin-top:.08rem;padding:.42rem .46rem;
    border:1px solid var(--pd-border);border-radius:9px;background:var(--pd-soft);
}
.pd-card-control-row .mc-dist-indicator { flex:1 1 118px;min-width:105px; }
.pd-card-control-row .mc-dist-bar-bg { width:auto!important;max-width:none!important;min-width:52px;flex:1 1 auto; }
.pd-card-control-row .mc-actions { display:flex;gap:.18rem;align-items:center;justify-content:flex-end;flex:0 0 auto;flex-wrap:nowrap;margin:0!important;padding:0!important;border:0!important; }
.pd-card-control-row .mc-actions button,
.pd-card-control-row .delivery-note-trigger {
    width:38px!important;min-width:38px!important;height:38px!important;min-height:38px!important;padding:0!important;
    display:inline-flex!important;align-items:center!important;justify-content:center!important;border-radius:9px!important;font-size:0!important;line-height:0!important;
}
.pd-card-control-row .mc-actions button svg,
.pd-card-control-row .mc-actions button i,
.pd-card-control-row .delivery-note-trigger svg,
.pd-card-control-row .delivery-note-trigger i { width:15px!important;height:15px!important;margin:0!important; }

/* Em notebook/tablet, cards são mais úteis que uma tabela comprimida. */
@media(max-width:1099px){
    .desktop-only { display:none!important; }
    .mobile-cards { display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr));gap:.52rem;padding:.56rem; }
}
@media(min-width:1100px){
    .desktop-only { display:block!important; }
    .mobile-cards { display:none!important; }
}

@media(max-width:767px){
    .pd-page { gap:.55rem; }
    .pd-context { min-height:58px;padding:.5rem; }
    .pd-tools-bar { padding:.42rem .48rem; }
    .pd-tools-sub { display:none; }
    .pd-tool-action { width:36px;min-width:36px;height:36px;min-height:36px;padding:0;font-size:0; }
    .pd-tool-integrity { width:auto;min-width:42px;padding:0 .35rem;font-size:.62rem; }
    .pd-tool-integrity .pd-tool-label { display:none; }
    .pd-tool-count { font-size:.58rem; }
    .pd-deliveries-card .pd-card-header { grid-template-columns:1fr;gap:.36rem; }
    .pd-card-head-actions { width:100%;justify-content:flex-start; }
    .pd-status-shortcuts { flex:1 1 auto; }
    .pd-clear-filters { width:34px;min-width:34px;padding:0;font-size:0; }
    .pd-filters-primary { grid-template-columns:minmax(0,1fr) 112px 44px;gap:.3rem;padding:.44rem .5rem; }
    .pd-filter-search { grid-column:auto; }
    .pd-filter-more { width:44px;min-width:44px;padding:0;font-size:0; }
    .pd-filter-more > span:not(.pd-filter-more-count) { display:none; }
    .pd-filter-more-chevron { display:none; }
    .pd-filters-advanced { grid-template-columns:1fr 1fr;padding:.44rem .5rem; }
    .mobile-cards { grid-template-columns:1fr;gap:.45rem;padding:.48rem; }
    .mobile-card .mc-head { min-height:47px;padding:.52rem!important; }
    .mobile-card .mc-body { padding:.54rem!important; }
    .pd-card-control-row { padding:.4rem; }
}
@media(max-width:390px){
    .pd-card-control-row { align-items:stretch; }
    .pd-card-control-row .mc-dist-indicator { flex-basis:100%; }
    .pd-card-control-row .mc-actions { width:100%;justify-content:flex-end; }
    .pd-status-shortcut { min-height:34px; }
}
</style>


@php
    $deliveryStyleDummy = [
        'id' => 0, 'quantity' => 0, 'distributed_qty' => 0, 'unit' => 'un',
        'limit' => [], 'status_value' => 'pending', 'distributions' => [],
    ];
@endphp
@include('delivery.partials.project-delivery-row', ['delivery' => $deliveryStyleDummy, 'customers' => $customers, 'stylesOnly' => true])
@include('delivery.partials.project-delivery-mobile-card', ['delivery' => $deliveryStyleDummy, 'customers' => $customers, 'stylesOnly' => true])

<main class="pd-page">
    {{-- CONTEXTO DO PROJETO --}}
    <section class="pd-context">
        <a
            class="pd-back"
            href="{{ route('delivery.dashboard', ['tenant' => $currentTenant->slug]) }}"
            aria-label="Voltar ao painel de entregas"
            title="Voltar ao painel de entregas"
        >
            <i class="ph-duotone ph-arrow-left"></i>
        </a>

        <span
            class="pd-context-icon"
            aria-hidden="true"
        >
            <i class="ph-duotone ph-package"></i>
        </span>

        <div class="pd-context-copy">
            <span class="pd-context-kicker">
                <i class="ph-duotone ph-package"></i>
                Entregas
            </span>

            <h1 class="pd-title">
                {{ $project->title }}
            </h1>

        </div>

        <div class="pd-header-actions">
            @if($project->status->value === 'active')
                <a
                    href="{{ route('delivery.register', [
                        'tenant' => $currentTenant->slug,
                        'project' => $project->id,
                    ]) }}"
                    class="btn btn-success btn-sm pd-action-delivery"
                >
                    <i class="ph-duotone ph-package"></i>
                    Nova entrega
                </a>
            @endif

            <a
                href="{{ route('delivery.projects.associates.index', [
                    'tenant' => $currentTenant->slug,
                    'project' => $project->id,
                ]) }}"
                class="btn btn-primary btn-sm pd-action-limits"
            >
                <i class="ph-duotone ph-sliders-horizontal"></i>
                Limites
            </a>


        </div>
    </section>

    {{-- FERRAMENTAS OPERACIONAIS: só aparece quando há algo útil para fazer --}}
        <section class="pd-tools-bar" id="pd-tools-bar" aria-label="Ferramentas do projeto" hidden>
            <div class="pd-tools-copy">
                <span class="pd-tools-icon" aria-hidden="true">
                    <i class="ph-duotone ph-wrench"></i>
                </span>
                <div>
                    <div class="pd-tools-title">Ferramentas</div>
                    <div class="pd-tools-sub">
                        <span id="pd-tools-summary">Carregando entregas...</span>
                    </div>
                </div>
            </div>

            <div class="pd-tools-actions">
                    <button
                        type="button"
                        class="pd-tool-action pd-tool-integrity"
                        id="pd-integrity-launch"
                        onclick="openIntegrityModal()"
                        title="Ver pendências e inconsistências"
                        aria-label="Ver pendências e inconsistências"
                        hidden
                    >
                        <i class="ph-duotone ph-shield-warning"></i>
                        <span class="pd-tool-label">Pendências</span>
                        <strong class="pd-tool-count" id="pd-integrity-total">0</strong>
                    </button>

                    <button
                        type="button"
                        class="pd-tool-action"
                        onclick="DeliveryReports.open()"
                        title="Gerar relatório"
                        aria-label="Gerar relatório"
                    >
                        <i class="ph-duotone ph-chart-bar"></i>
                        <span class="pd-tool-label">Relatório</span>
                    </button>

                    <a
                        href="{{ route('delivery.projects.producers', [
                            'tenant' => $currentTenant->slug,
                            'project' => $project->id,
                        ]) }}"
                        class="pd-tool-action"
                        title="Abrir comprovantes"
                        aria-label="Abrir comprovantes"
                    >
                        <i class="ph-duotone ph-clipboard-text"></i>
                        <span class="pd-tool-label">Comprovantes</span>
                    </a>
            </div>
        </section>

        <div
            class="pd-integrity-overlay"
            id="pd-integrity-modal"
            aria-hidden="true"
        >
            <div
                class="pd-integrity-box"
                role="dialog"
                aria-modal="true"
                aria-labelledby="pd-integrity-title"
            >
                <header class="pd-integrity-head">
                    <div class="pd-integrity-head-main">
                        <span
                            class="pd-panel-icon warning"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-shield-warning"></i>
                        </span>

                        <div>
                            <div
                                class="pd-integrity-title"
                                id="pd-integrity-title"
                            >
                                Central de inconsistências
                            </div>

                            <div class="pd-integrity-sub" id="pd-integrity-sub">
                                Carregando verificações...
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="pd-integrity-close"
                        onclick="closeIntegrityModal()"
                        aria-label="Fechar"
                    >
                        <i class="ph-duotone ph-x"></i>
                    </button>
                </header>

                <div class="pd-integrity-body">
                    <div class="pd-integrity-empty">Carregando...</div>
                </div>

                <footer class="pd-integrity-foot">
                    <button
                        type="button"
                        class="btn btn-ghost btn-sm"
                        id="pd-integrity-show-all"
                        onclick="openIntegrityModal()"
                        hidden
                    >
                        <i class="ph-duotone ph-magnifying-glass" aria-hidden="true"></i>
                        Ver todas
                    </button>

                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        onclick="closeIntegrityModal()"
                    >
                        Fechar
                    </button>
                </footer>
            </div>
        </div>

    @include('delivery.partials.report-export-modal', [
        'reportProjects' => collect([
            $project->id => $project->title,
        ]),
        'selectedReportProject' => $project->id,
    ])

    {{-- LISTA DE ENTREGAS --}}
    <section class="pd-card pd-deliveries-card" id="pd-deliveries-surface" aria-busy="true">
        <header class="pd-card-header">
            <div class="pd-card-title">
                <i class="ph-duotone ph-list-checks"></i>

                <div class="pd-panel-copy">
                    <div class="pd-panel-title">
                        Entregas
                    </div>

                    <div class="pd-panel-sub">
                        <span id="filtered-count">0</span>
                        registros
                    </div>
                </div>
            </div>

            <div class="pd-card-head-actions">
                <div class="pd-status-shortcuts" role="group" aria-label="Filtrar entregas por situação">
                    <button type="button" class="pd-status-shortcut active" data-pd-status="" onclick="setQuickStatusFilter('')">
                        <span>Todos</span>
                        <strong id="pd-count-all">0</strong>
                    </button>

                    <button type="button" class="pd-status-shortcut pending" id="pd-shortcut-pending" data-pd-status="pending" onclick="setQuickStatusFilter('pending')" hidden>
                        <i class="ph-duotone ph-clock"></i>
                        <span>Pendentes</span>
                        <strong id="pd-count-pending">0</strong>
                    </button>

                    <button type="button" class="pd-status-shortcut approved" id="pd-shortcut-approved" data-pd-status="approved" onclick="setQuickStatusFilter('approved')" hidden>
                        <i class="ph-duotone ph-check-circle"></i>
                        <span>Aprovadas</span>
                        <strong id="pd-count-approved">0</strong>
                    </button>

                    <button type="button" class="pd-status-shortcut rejected" id="pd-shortcut-rejected" data-pd-status="rejected" onclick="setQuickStatusFilter('rejected')" hidden>
                        <i class="ph-duotone ph-x-circle"></i>
                        <span>Rejeitadas</span>
                        <strong id="pd-count-rejected">0</strong>
                    </button>
                </div>

                <button
                    class="btn btn-ghost btn-sm pd-clear-filters"
                    id="clear-filters-btn"
                    type="button"
                    style="display:none;"
                    onclick="clearAllFilters()"
                    title="Limpar filtros"
                    aria-label="Limpar filtros"
                >
                    <i class="ph-duotone ph-x"></i>
                    <span>Limpar</span>
                </button>
            </div>
            </div>
        </header>

        {{-- FILTROS --}}
        <div class="filters-bar" id="filters-bar">
            <div class="pd-filters-primary">
                <label class="pd-filter-field pd-filter-search">
                    <span class="pd-filter-label">
                        Buscar
                    </span>

                    <span class="pd-filter-control has-icon">
                        <i class="ph-duotone ph-magnifying-glass"></i>

                        <input
                            type="search"
                            class="filter-input"
                            id="filter-search"
                            placeholder="Associado, produto ou data"
                            autocomplete="off"
                        >
                    </span>
                </label>

                <label class="pd-filter-field pd-filter-status">
                    <span class="pd-filter-label">
                        Status
                    </span>

                    <select
                        class="filter-select"
                        id="filter-status"
                    >
                        <option value="">
                            Todos os status
                        </option>

                        <option value="pending">
                            Pendente
                        </option>

                        <option value="approved">
                            Aprovada
                        </option>

                        <option value="rejected">
                            Rejeitada
                        </option>

                        <option value="cancelled">
                            Cancelada
                        </option>
                    </select>
                </label>

                <button
                    class="pd-filter-more"
                    id="pd-filter-more"
                    type="button"
                    aria-controls="pd-advanced-filters"
                    aria-expanded="false"
                    onclick="toggleAdvancedFilters()"
                >
                    <i class="ph-duotone ph-sliders-horizontal"></i>

                    <span>Mais filtros</span>

                    <span
                        class="pd-filter-more-count"
                        id="pd-filter-more-count"
                        hidden
                    >
                        0
                    </span>

                    <i class="pd-filter-more-chevron ph-duotone ph-caret-down"></i>
                </button>
            </div>

            <div
                class="pd-filters-advanced"
                id="pd-advanced-filters"
                hidden
            >
                <label class="pd-filter-field">
                    <span class="pd-filter-label">
                        Associado
                    </span>

                    <select
                        class="filter-select"
                        id="filter-associate"
                    >
                        <option value="">
                            Todos os associados
                        </option>

                    </select>
                </label>

                <label class="pd-filter-field">
                    <span class="pd-filter-label">
                        Produto
                    </span>

                    <select
                        class="filter-select"
                        id="filter-product"
                    >
                        <option value="">
                            Todos os produtos
                        </option>

                    </select>
                </label>

                <label class="pd-filter-field pd-filter-date">
                    <span class="pd-filter-label">
                        De
                    </span>

                    <input
                        type="date"
                        class="filter-input"
                        id="filter-date-from"
                    >
                </label>

                <label class="pd-filter-field pd-filter-date">
                    <span class="pd-filter-label">
                        Até
                    </span>

                    <input
                        type="date"
                        class="filter-input"
                        id="filter-date-to"
                    >
                </label>

                <label class="pd-filter-field pd-filter-page-size">
                    <span class="pd-filter-label">
                        Por página
                    </span>

                    <select
                        class="filter-select"
                        id="delivery-page-size"
                    >
                        <option value="12">12 por página</option>
                        <option value="24" selected>
                            24 por página
                        </option>

                        <option value="48">
                            48 por página
                        </option>

                    </select>
                </label>
            </div>
        </div>

            <div class="pd-empty" id="pd-empty" hidden>
                <i class="pd-empty-icon ph-duotone ph-package" aria-hidden="true"></i>

                <p>
                    Nenhuma entrega foi registrada para este projeto.
                </p>
            </div>
            <div class="pd-delivery-skeleton" id="pd-delivery-skeleton" aria-label="Carregando entregas">
                @for($i = 0; $i < 6; $i++)<span></span>@endfor
            </div>
            {{-- TABELA DESKTOP --}}
            <div class="table-scroll desktop-only">
                <table
                    class="data-table"
                    id="desktop-table"
                >
                    <thead>
                        <tr>
<th>Data</th>
                            <th>Associado</th>
                            <th>Produto</th>
                            <th>Quantidade</th>
                            <th class="pd-col-net">Valor</th>
                            <th class="pd-col-suppressed">Qualidade</th>
                            <th class="pd-col-suppressed">Status</th>
                            <th>Limite</th>
                            <th>Distribuição</th>
                            <th class="pd-col-suppressed">Ações</th>
                        </tr>
                    </thead>

                    <tbody id="desktop-tbody">
                    </tbody>
                </table>
            </div>

            {{-- MOBILE --}}
            <div
                class="mobile-cards"
                id="mobile-cards"
            >
            </div>

            <div
                class="delivery-pagination"
                id="project-pagination"
            >
                <div
                    class="delivery-pagination-info"
                    id="project-page-info"
                ></div>

                <div class="delivery-pagination-actions">
                    <button
                        type="button"
                        class="delivery-page-btn"
                        id="project-prev"
                    >
                        Anterior
                    </button>

                    <button
                        type="button"
                        class="delivery-page-btn"
                        id="project-next"
                    >
                        Próxima
                    </button>
                </div>
            </div>
    </section>
</main>

{{-- RESUMO DE DISTRIBUIÇÕES --}}
<div
    class="dist-summary-overlay"
    id="dist-summary-overlay"
    aria-hidden="true"
>
    <div
        class="dist-summary-box"
        role="dialog"
        aria-modal="true"
        aria-labelledby="dist-summary-title"
    >
        <header class="dist-summary-head">
            <div class="dist-summary-head-main">
                <span
                    class="dist-summary-icon"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-git-merge"></i>
                </span>

                <div>
                    <div
                        class="dist-summary-title"
                        id="dist-summary-title"
                    >
                        Distribuições
                    </div>

                    <div
                        class="dist-summary-sub"
                        id="dist-summary-sub"
                    ></div>
                </div>
            </div>

            
        </header>

        <div
            class="dist-summary-body"
            id="dist-summary-body"
        ></div>
        <footer class="dist-summary-head">
            <button
                type="button"
                class="dist-summary-close"
                onclick="closeDistSummary()"
                aria-label="Fechar"
            >
                Fechar
            </button>
        </footer>
    </div>
</div>


<style id="pd-deliveries-refinement">
/* ================================================================
   Refinos de interface — mantém o desenho atual.
   ================================================================ */
.pd-delivery-skeleton{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem;padding:.65rem}.pd-delivery-skeleton[hidden]{display:none}.pd-delivery-skeleton span{display:block;height:142px;border:1px solid var(--pd-border);border-radius:10px;background:linear-gradient(90deg,var(--pd-soft) 25%,#fff 45%,var(--pd-soft) 65%);background-size:220% 100%;animation:pd-skeleton 1.15s ease-in-out infinite}.pd-deliveries-card[aria-busy="true"] .table-scroll,.pd-deliveries-card[aria-busy="true"] .mobile-cards{opacity:.42;transition:opacity .16s ease}.pd-deliveries-card[aria-busy="false"] .table-scroll,.pd-deliveries-card[aria-busy="false"] .mobile-cards{opacity:1;transition:opacity .16s ease}@keyframes pd-skeleton{to{background-position:-220% 0}}@media(max-width:700px){.pd-delivery-skeleton{grid-template-columns:1fr}.pd-delivery-skeleton span{height:156px}}

/* Phosphor Duotone deve herdar a cor funcional de cada componente. */
.pd-page .ph-duotone,
.pd-modal-scope .ph-duotone,
.pd-integrity-overlay .ph-duotone,
.dist-summary-overlay .ph-duotone,
#dm-overlay .ph-duotone,
#em-overlay .ph-duotone,
#delivery-report-modal .ph-duotone,
#delivery-notes-overlay .ph-duotone {
    display: inline-block;
    flex: 0 0 auto;
    color: currentColor;
    line-height: 1;
    vertical-align: -.08em;
}

/* Não exibimos o seletor de projeto: esta tela já pertence a um projeto. */
#delivery-report-modal label[for="dr-project"],
#delivery-report-modal #dr-project {
    display: none !important;
}

/* Sheets/modais: estrutura estável. Cabeçalho e rodapé nunca rolam. */
#dm-overlay .dm-box,
#em-overlay .em-box,
#pd-integrity-modal .pd-integrity-box,
#dist-summary-overlay .dist-summary-box,
#delivery-report-modal .dr-panel,
#delivery-notes-overlay .delivery-notes-box {
    display: flex !important;
    min-height: 0;
    flex-direction: column;
    overflow: hidden !important;
}

#dm-overlay .dm-head,
#dm-overlay .dm-progress-wrap,
#dm-overlay .dm-foot,
#em-overlay .em-head,
#em-overlay .em-foot,
#pd-integrity-modal .pd-integrity-head,
#pd-integrity-modal .pd-integrity-foot,
#dist-summary-overlay .dist-summary-head,
#delivery-report-modal .dr-head,
#delivery-report-modal .dr-actions,
#delivery-notes-overlay .delivery-notes-head,
#delivery-notes-overlay .delivery-notes-foot {
    position: relative !important;
    z-index: 3;
    flex: 0 0 auto;
    background: color-mix(in srgb, var(--color-surface, #fff) 96%, transparent);
    backdrop-filter: blur(10px);
}

/* Somente o conteúdo interno é rolável. */
#dm-overlay .dm-body,
#em-overlay .em-body,
#pd-integrity-modal .pd-integrity-body,
#dist-summary-overlay .dist-summary-body,
#delivery-report-modal .dr-body,
#delivery-notes-overlay .delivery-notes-content {
    min-height: 0;
    flex: 1 1 auto;
    overflow-x: hidden !important;
    overflow-y: auto !important;
    overscroll-behavior: contain;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

#dm-overlay .dm-body::-webkit-scrollbar,
#em-overlay .em-body::-webkit-scrollbar,
#pd-integrity-modal .pd-integrity-body::-webkit-scrollbar,
#dist-summary-overlay .dist-summary-body::-webkit-scrollbar,
#delivery-report-modal .dr-body::-webkit-scrollbar,
#delivery-report-modal .dr-filter-list::-webkit-scrollbar,
#delivery-notes-overlay .delivery-notes-content::-webkit-scrollbar {
    width: .1px;
    height: .1px;
}

#dm-overlay .dm-body::-webkit-scrollbar-thumb,
#em-overlay .em-body::-webkit-scrollbar-thumb,
#pd-integrity-modal .pd-integrity-body::-webkit-scrollbar-thumb,
#dist-summary-overlay .dist-summary-body::-webkit-scrollbar-thumb,
#delivery-report-modal .dr-body::-webkit-scrollbar-thumb,
#delivery-report-modal .dr-filter-list::-webkit-scrollbar-thumb,
#delivery-notes-overlay .delivery-notes-content::-webkit-scrollbar-thumb {
    background: transparent;
}

/* O relatório também passa a ter body rolável, não painel inteiro. */
#delivery-report-modal .dr-panel {
    max-height: min(90dvh, 820px);
}
#delivery-report-modal .dr-body {
    padding-bottom: 1rem;
}
#delivery-report-modal .dr-filter-list {
    scrollbar-width: none;
}

/* Central: leitura vertical, menos colunas e menos ruído. */
#pd-integrity-modal .pd-integrity-box {
    width: min(680px, 100%);
    max-height: min(88dvh, 760px);
}
#pd-integrity-modal .pd-integrity-body {
    display: grid;
    grid-template-columns: 1fr !important;
    gap: .55rem;
    padding: .62rem;
}
#pd-integrity-modal .pd-integrity-column {
    overflow: hidden;
    border: 1px solid var(--pd-border);
    border-radius: 11px;
}
#pd-integrity-modal .pd-integrity-column + .pd-integrity-column {
    border-top: 1px solid var(--pd-border);
    border-left: 1px solid var(--pd-border);
}
#pd-integrity-modal .pd-integrity-column-head {
    min-height: 34px;
    padding: .4rem .55rem;
}
#pd-integrity-modal .pd-integrity-item {
    padding: .55rem .62rem;
}
#pd-integrity-modal .pd-integrity-item-message {
    margin-top: .12rem;
}
#pd-integrity-modal .pd-integrity-item-action {
    margin-top: .18rem;
    color: var(--pd-text-3);
    font-weight: 650;
}
#pd-integrity-modal .pd-integrity-actions {
    margin-top: .42rem;
}
#pd-integrity-modal .pd-integrity-empty {
    display: none !important;
}
body.pd-sheet-open {
    overflow: hidden;
}

#pd-integrity-modal .pd-integrity-foot {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: .4rem;
    padding: .58rem .64rem max(.58rem, env(safe-area-inset-bottom));
    border-top: 1px solid var(--pd-border);
}
#pd-integrity-modal #pd-integrity-show-all {
    margin-right: auto;
}
#pd-integrity-modal .pd-integrity-empty-state {
    display: grid;
    min-height: 180px;
    place-items: center;
    align-content: center;
    gap: .3rem;
    padding: 1.2rem;
    color: var(--pd-text-2);
    text-align: center;
}
#pd-integrity-modal .pd-integrity-empty-state > .ph-duotone {
    margin-bottom: .2rem;
    color: var(--pd-green);
    font-size: 30px;
}
#pd-integrity-modal .pd-integrity-empty-state strong {
    color: var(--pd-text);
    font-size: .82rem;
}
#pd-integrity-modal .pd-integrity-empty-state span {
    max-width: 360px;
    color: var(--pd-text-3);
    font-size: .69rem;
}

/* Confirmação própria mais clara e compacta. */
.confirm-overlay {
    animation: pd-overlay-in .16s ease both;
}
.confirm-box {
    animation: pd-dialog-in .2s cubic-bezier(.2,.78,.28,1) both;
}
.confirm-icon .ph-duotone {
    width: 19px;
    height: 19px;
    font-size: 19px;
}

/* Animações discretas dos sheets. */
#dm-overlay.dm-open,
#em-overlay.em-open,
#pd-integrity-modal.open,
#dist-summary-overlay.open,
#delivery-report-modal:not([hidden]),
#delivery-notes-overlay.open {
    animation: pd-overlay-in .17s ease both;
}

#dm-overlay.dm-open .dm-box,
#em-overlay.em-open .em-box,
#pd-integrity-modal.open .pd-integrity-box,
#dist-summary-overlay.open .dist-summary-box,
#delivery-report-modal:not([hidden]) .dr-panel,
#delivery-notes-overlay.open .delivery-notes-box {
    animation: pd-dialog-in .22s cubic-bezier(.2,.78,.28,1) both;
}

/* Pequenos refinamentos nos componentes incorporados. */
#dm-overlay .dm-box,
#em-overlay .em-box {
    border: 1px solid var(--color-border);
    box-shadow: 0 24px 62px rgba(15, 35, 24, .24);
}
#dm-overlay .dm-close-btn,
#em-overlay .em-close-btn,
#delivery-notes-overlay .delivery-notes-close,
#delivery-report-modal .dr-close {
    border: 1px solid var(--color-border);
    background: var(--color-surface);
}
#dm-overlay .dm-close-btn .ph-duotone,
#em-overlay .em-close-btn .ph-duotone,
#delivery-notes-overlay .delivery-notes-close .ph-duotone,
#delivery-report-modal .dr-close .ph-duotone {
    font-size: 16px;
}
#dm-overlay .dm-edit-btn .ph-duotone,
#dm-overlay .dm-del-btn .ph-duotone,
#dm-overlay .dm-mini-btn .ph-duotone,
#dm-overlay .dm-rm-btn .ph-duotone {
    font-size: 16px;
}
.delivery-note-trigger .ph-duotone {
    width: 14px;
    height: 14px;
    font-size: 14px;
}

/* Indicadores com alerta usam ícone, não símbolo textual. */
.dist-text .ph-duotone,
.mc-dist-text .ph-duotone {
    margin-right: .15rem;
    font-size: 12px;
}

/* Transição somente em elementos alterados pelo servidor. */
[data-delivery-id].pd-fragment-updated {
    animation: pd-fragment-update .34s ease both;
}

@keyframes pd-overlay-in {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes pd-dialog-in {
    from { opacity: 0; transform: translateY(8px) scale(.99); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes pd-fragment-update {
    0% { opacity: .72; }
    100% { opacity: 1; }
}

@media (max-width: 767px) {
    #dm-overlay,
    #em-overlay,
    #pd-integrity-modal,
    #dist-summary-overlay,
    #delivery-report-modal,
    #delivery-notes-overlay {
        align-items: flex-end !important;
        justify-content: stretch !important;
        padding: 0 !important;
    }

    #dm-overlay .dm-box,
    #em-overlay .em-box,
    #pd-integrity-modal .pd-integrity-box,
    #dist-summary-overlay .dist-summary-box,
    #delivery-report-modal .dr-panel,
    #delivery-notes-overlay .delivery-notes-box {
        width: 100% !important;
        max-width: none !important;
        max-height: 92svh !important;
        border-right: 0;
        border-bottom: 0;
        border-left: 0;
        border-radius: 18px 18px 0 0 !important;
    }

    #dm-overlay.dm-open .dm-box,
    #em-overlay.em-open .em-box,
    #pd-integrity-modal.open .pd-integrity-box,
    #dist-summary-overlay.open .dist-summary-box,
    #delivery-report-modal:not([hidden]) .dr-panel,
    #delivery-notes-overlay.open .delivery-notes-box {
        animation-name: pd-sheet-in;
    }

    #dm-overlay .dm-foot,
    #em-overlay .em-foot,
    #pd-integrity-modal .pd-integrity-foot,
    #delivery-report-modal .dr-actions,
    #delivery-notes-overlay .delivery-notes-foot {
        padding-bottom: max(.75rem, env(safe-area-inset-bottom));
    }
}

@keyframes pd-sheet-in {
    from { opacity: 0; transform: translateY(24px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
    #dm-overlay.dm-open,
    #em-overlay.em-open,
    #pd-integrity-modal.open,
    #dist-summary-overlay.open,
    #delivery-report-modal:not([hidden]),
    #delivery-notes-overlay.open,
    #dm-overlay.dm-open .dm-box,
    #em-overlay.em-open .em-box,
    #pd-integrity-modal.open .pd-integrity-box,
    #dist-summary-overlay.open .dist-summary-box,
    #delivery-report-modal:not([hidden]) .dr-panel,
    #delivery-notes-overlay.open .delivery-notes-box,
    [data-delivery-id].pd-fragment-updated {
        animation: none !important;
    }
}
</style>


<style id="pd-final-corrections">
/* ================================================================
   Correções finais — responsividade, duotone, tabela e segurança.
   ================================================================ */

/* ---------- PHOSPHOR: garantir que o glifo tenha tamanho real ----------
   Vários botões compactos usam font-size:0 para esconder o texto.
   Ícones-font precisam de font-size próprio; width/height não bastam. */
.pd-page .ph-duotone,
.pd-modal-scope .ph-duotone,
.pd-integrity-overlay .ph-duotone,
.dist-summary-overlay .ph-duotone,
#dm-overlay .ph-duotone,
#em-overlay .ph-duotone,
#delivery-report-modal .ph-duotone,
#delivery-notes-overlay .ph-duotone {
    font-family: "Phosphor-Duotone" !important;
    font-style: normal !important;
    font-weight: normal !important;
    speak: never;
}

.pd-table-control-row button .ph-duotone,
.pd-table-control-row .delivery-note-trigger .ph-duotone {
    font-size: 14px !important;
    line-height: 1 !important;
}

.pd-card-control-row button .ph-duotone,
.pd-card-control-row .delivery-note-trigger .ph-duotone,
.mc-actions button .ph-duotone {
    font-size: 17px !important;
    line-height: 1 !important;
}

.pd-header-actions .ph-duotone {
    font-size: 15px;
}

.pd-tools-icon .ph-duotone,
.pd-tool-action .ph-duotone,
.pd-status-shortcut .ph-duotone,
.pd-filter-control .ph-duotone,
.pd-filter-more .ph-duotone,
.pd-integrity-close .ph-duotone,
.dist-summary-close .ph-duotone {
    font-size: 15px !important;
}

.pd-stat-icon .ph-duotone,
.pd-card-title .ph-duotone,
.pd-panel-icon.ph-duotone {
    font-size: 17px !important;
}

.mc-state-icon .ph-duotone {
    font-size: 15px;
}

#dm-overlay .dm-title .ph-duotone,
#em-overlay .em-title .ph-duotone {
    font-size: 18px !important;
}

#dm-overlay .dm-edit-btn .ph-duotone,
#dm-overlay .dm-del-btn .ph-duotone,
#dm-overlay .dm-mini-btn .ph-duotone,
#dm-overlay .dm-rm-btn .ph-duotone,
#dm-overlay .dm-close-btn .ph-duotone,
#em-overlay .em-close-btn .ph-duotone {
    font-size: 16px !important;
}

/* Fallback: se por algum motivo o CSS do Phosphor ainda estiver atrasado,
   o elemento não desaparece por font-size herdado igual a zero. */
.ph-duotone[class*=" ph-"],
.ph-duotone[class^="ph-"] {
    min-width: 1em;
    min-height: 1em;
}

/* ---------- Header ----------
   Nova entrega + Limites ficam lado a lado; Produtores foi removido. */
.pd-header-actions {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-end !important;
    gap: .34rem !important;
    grid-column: auto !important;
    grid-row: auto !important;
    width: auto !important;
}

.pd-header-actions .pd-action-delivery {
    border-color: rgba(22,138,77,.18) !important;
    background: var(--pdx-green-soft) !important;
    color: var(--pdx-green) !important;
}

.pd-header-actions .pd-action-limits {
    border-color: rgba(124,58,237,.18) !important;
    background: var(--pdx-violet-soft) !important;
    color: var(--pdx-violet) !important;
}

@media (min-width: 680px) {
    .pd-context {
        grid-template-columns: auto auto minmax(0, 1fr) auto !important;
    }
}

@media (max-width: 679px) {
    .pd-context {
        grid-template-columns: 36px minmax(0,1fr) !important;
    }

    .pd-context-icon {
        display: none !important;
    }

    .pd-header-actions {
        grid-column: 1 / -1 !important;
        grid-row: auto !important;
        justify-content: stretch !important;
        width: 100% !important;
    }

    .pd-header-actions .btn {
        flex: 1 1 0;
        min-width: 0;
    }
}

/* ---------- Tablet / intermediário ----------
   Fonte única do breakpoint: até 1099px = cards; 1100+ = tabela. */
@media (max-width: 1099px) {
    .desktop-only {
        display: none !important;
    }

    .mobile-cards {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1100px) {
    .desktop-only {
        display: block !important;
    }

    .mobile-cards {
        display: none !important;
    }
}

@media (max-width: 767px) {
    .mobile-cards {
        grid-template-columns: 1fr !important;
    }
}

/* ---------- Tabela ----------
   Menos informação simultânea, sem remover os dados do DOM. */
.data-table {
    font-size: .68rem !important;
}

.data-table th,
.data-table td {
    padding: .48rem .44rem !important;
}

.data-table th {
    font-size: .61rem !important;
    letter-spacing: .01em;
}

/* Valor líquido continua disponível no card/mobile, mas sai da tabela
   para reduzir ruído visual. */
.data-table thead th.pd-col-net,
#desktop-tbody tr > td:nth-child(6) {
    display: none !important;
}

/* Após ocultar valor/qualidade/status, redistribuir espaço. */
.data-table th:nth-child(1) { width: 78px !important; }
.data-table th:nth-child(2) { width: 23% !important; }
.data-table th:nth-child(3) { width: 21% !important; }
.data-table th:nth-child(4) { width: 100px !important; }
.data-table th:nth-child(8) { width: 150px !important; }
.data-table th:nth-child(9) { width: 225px !important; }

.pd-limit-cell .pdr-limit-head {
    line-height: 1.15;
}

.pd-limit-cell .pdr-limit-free {
    opacity: .82;
}

/* ---------- Progresso do sheet de distribuição ----------
   Já distribuído muda de cor conforme quantidade/estado.
   Novas inclusões permanecem roxas para não se confundir com histórico. */
#dm-overlay .dm-progress-wrap {
    --dm-existing-tone: #0284c7;
    --dm-existing-soft: #e0f2fe;
    --dm-new-tone: #7c3aed;
}

#dm-overlay .dm-progress-track {
    background: color-mix(in srgb, var(--color-border) 62%, transparent) !important;
}

#dm-overlay .dm-bar-existing {
    background: var(--dm-existing-tone) !important;
    transition: width .24s ease, background-color .18s ease !important;
}

#dm-overlay .dm-bar-new {
    background: var(--dm-new-tone) !important;
    transition: width .24s ease !important;
}

#dm-overlay .dm-progress-wrap[data-progress-state="empty"] {
    --dm-existing-tone: #94a3b8;
}
#dm-overlay .dm-progress-wrap[data-progress-state="partial"] {
    --dm-existing-tone: #0284c7;
}
#dm-overlay .dm-progress-wrap[data-progress-state="high"] {
    --dm-existing-tone: #c87408;
}
#dm-overlay .dm-progress-wrap[data-progress-state="complete"] {
    --dm-existing-tone: #168a4d;
}
#dm-overlay .dm-progress-wrap[data-progress-state="over"] {
    --dm-existing-tone: #cf3f3f;
}

#dm-overlay .dm-progress-wrap[data-progress-state="high"] #dm-lbl-existing strong {
    color: #a16207 !important;
}
#dm-overlay .dm-progress-wrap[data-progress-state="complete"] #dm-lbl-existing strong {
    color: #168a4d !important;
}
#dm-overlay .dm-progress-wrap[data-progress-state="over"] #dm-lbl-existing strong {
    color: #cf3f3f !important;
}

/* ---------- Confirmação com desafio ----------
   Usado para exclusões de impacto (ex.: distribuição em comprovante). */
.confirm-challenge {
    grid-column: 1 / -1;
    display: grid;
    gap: .34rem;
    margin-top: .48rem;
    padding: .58rem;
    border: 1px solid rgba(207,63,63,.16);
    border-radius: 10px;
    background: color-mix(in srgb, var(--pd-red-soft) 72%, #fff);
}

.confirm-challenge[hidden] {
    display: none !important;
}

.confirm-challenge-label {
    display: flex;
    align-items: center;
    gap: .3rem;
    color: var(--pd-text-2);
    font-size: .68rem;
    font-weight: 720;
    line-height: 1.35;
}

.confirm-challenge-label .ph-duotone {
    color: var(--pd-red);
    font-size: 16px !important;
}

.confirm-challenge-question {
    color: var(--pd-text);
    font-size: .77rem;
    font-weight: 820;
}

.confirm-challenge-input {
    width: 100%;
    min-height: 40px;
    padding: .46rem .54rem;
    border: 1px solid var(--pd-border-strong);
    border-radius: 9px;
    outline: 0;
    background: #fff;
    color: var(--pd-text);
    font: inherit;
    font-size: .76rem;
}

.confirm-challenge-input:focus {
    border-color: var(--pd-red);
    box-shadow: 0 0 0 3px rgba(207,63,63,.08);
}

.confirm-challenge-error {
    color: var(--pd-red);
    font-size: .64rem;
    font-weight: 720;
}

.confirm-box.is-danger .confirm-icon {
    background: var(--pd-red-soft);
    color: var(--pd-red);
}

.confirm-box.is-danger #confirmOk {
    border-color: rgba(207,63,63,.18);
    background: var(--pd-red-soft);
    color: var(--pd-red);
}
</style>

<script>
const PD_TENANT    = '{{ $currentTenant->slug }}';
const PD_CSRF      = '{{ csrf_token() }}';
const PD_PROJECT   = {{ $project->id }};
const PD_CUSTOMERS = @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->trade_name ?: $c->name]));
const projectListState = {
    page: 1,
    perPage: 24,
    lastPage: 1,
    loading: false,
    abort: null,
    summary: null,
    filtersLoaded: false,
};

let integrityFilterDeliveryId = null;

function setIntegrityModalScope(deliveryId = null) {
    const modal = document.getElementById('pd-integrity-modal');
    if (!modal) return 0;

    integrityFilterDeliveryId = deliveryId ? String(deliveryId) : null;

    const issues = Array.from(
        modal.querySelectorAll('article[data-modal-issue-delivery]')
    );

    let visibleCount = 0;

    issues.forEach(item => {
        const belongs = !integrityFilterDeliveryId
            || String(item.dataset.modalIssueDelivery || '') === integrityFilterDeliveryId;

        item.hidden = !belongs;

        if (belongs) {
            visibleCount++;
        }
    });

    modal.querySelectorAll('.pd-integrity-column').forEach(column => {
        const hasVisibleIssue = Array.from(
            column.querySelectorAll('article[data-modal-issue-delivery]')
        ).some(item => !item.hidden);

        column.hidden = !hasVisibleIssue;
    });

    const title = document.getElementById('pd-integrity-title');
    const sub = document.getElementById('pd-integrity-sub');
    const showAll = document.getElementById('pd-integrity-show-all');

    if (title) {
        title.textContent = integrityFilterDeliveryId
            ? 'Inconsistências desta entrega'
            : 'Central de inconsistências';
    }

    if (sub) {
        if (integrityFilterDeliveryId) {
            sub.textContent = visibleCount === 1
                ? '1 ponto desta entrega precisa de revisão'
                : `${visibleCount} pontos desta entrega precisam de revisão`;
        } else {
            sub.textContent = issues.length === 1
                ? '1 ponto precisa de revisão'
                : `${issues.length} pontos precisam de revisão`;
        }
    }

    if (showAll) {
        showAll.hidden = !integrityFilterDeliveryId;
    }

    return visibleCount;
}

function openIntegrityModal(deliveryId = null) {
    const modal = document.getElementById('pd-integrity-modal');
    if (!modal) return;

    setIntegrityModalScope(deliveryId);

    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('pd-sheet-open');
}

function closeIntegrityModal() {
    const modal = document.getElementById('pd-integrity-modal');
    if (!modal) return;

    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('pd-sheet-open');
}

function toggleIntegrityPanel() {
    const content = document.getElementById('pd-integrity-content');
    const button = document.querySelector('.pd-integrity-toggle');

    if (!content || !button) return;

    const opening = content.hidden;
    content.hidden = !opening;
    button.setAttribute('aria-expanded', opening ? 'true' : 'false');

    const icon = button.querySelector('i');

    if (icon) {
        icon.className = `pd-filter-more-chevron ph-duotone ph-${opening ? 'caret-up' : 'caret-down'}`;
    }
}

function focusIntegrityDelivery(deliveryId) {
    closeIntegrityModal();
    const row = document.querySelector(`[data-delivery-id="${deliveryId}"]`);
    row?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function openIntegrityDistribution(deliveryId, distributionId = 0, edit = false) {
    const button = document.querySelector(`.btn-distribute[data-id="${deliveryId}"]`);
    if (!button) {
        focusIntegrityDelivery(deliveryId);
        pdToast('Abra uma entrega aprovada para corrigir as distribuições.', 'info');
        return;
    }

    closeIntegrityModal();
    DistModal.openFromBtn(button);
    if (edit && distributionId) {
        setTimeout(() => DistModal.editExisting(distributionId), 120);
    }
}

function applyResolvedIntegrity(integrity) {
    renderIntegrityCenter(integrity);

    const counts = integrity?.counts || {};
    const total =
        Number(counts.critical || 0)
        + Number(counts.warning || 0)
        + Number(counts.info || 0);

    if (total <= 0) {
        closeIntegrityModal();
    }
}

async function handleIntegrityAction(actionKey, deliveryId = 0, distributionId = 0, associateId = 0, associateName = '') {
    if (actionKey === 'open_distribution') {
        openIntegrityDistribution(deliveryId, distributionId);
        return;
    }
    if (actionKey === 'edit_distribution') {
        openIntegrityDistribution(deliveryId, distributionId, true);
        return;
    }
    if (actionKey === 'open_producers') {
        const query = associateId ? `?associate=${associateId}&name=${encodeURIComponent(associateName)}` : '';
        window.location.href = `/${PD_TENANT}/delivery/projects/${PD_PROJECT}/producers${query}`;
        return;
    }

    const message = actionKey === 'detach_missing_associate_receipt'
        ? 'Desvincular este comprovante inexistente? A distribuição voltará a ficar disponível para um novo comprovante.'
        : actionKey === 'restore_parent_delivery'
            ? 'Restaurar a entrega-pai excluída? Quantidades, valores e comprovantes não serão alterados.'
            : 'Excluir esta distribuição órfã? Esta correção não pode ser desfeita.';
    const confirmed = await customConfirm(message);
    if (!confirmed) return;

    try {
        const res = await fetch(`/${PD_TENANT}/delivery/projects/${PD_PROJECT}/integrity/resolve`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PD_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ action: actionKey, distribution_id: distributionId }),
        });
        const data = await res.json();
        if (!data.success) {
            pdToast(data.message || 'Não foi possível aplicar esta correção.', 'error');
            return;
        }

        applyResolvedIntegrity(data.integrity, actionKey, distributionId);
        if (deliveryId) refreshDeliveryItem(deliveryId).catch(() => {});
        pdToast(data.message);
    } catch (error) {
        pdToast('Erro de comunicação ao aplicar a correção.', 'error');
    }
}

document.addEventListener('click', event => {
    const button = event.target.closest('[data-pd-integrity-action]');

    if (!button) return;

    event.preventDefault();

    handleIntegrityAction(
        button.dataset.pdIntegrityAction || '',
        Number(button.dataset.deliveryId || 0),
        Number(button.dataset.distributionId || 0),
        Number(button.dataset.associateId || 0),
        button.dataset.associateName || ''
    );
});


/* ========== CUSTOM CONFIRM ========== */
function customConfirm(message, options = {}) {
    return new Promise(resolve => {
        const overlay = document.getElementById('customConfirmOverlay');
        const box = overlay?.querySelector('.confirm-box');
        const title = document.getElementById('confirmTitle');
        const messageEl = document.getElementById('confirmMessage');
        const okBtn = document.getElementById('confirmOk');
        const cancelBtn = document.getElementById('confirmCancel');

        if (!overlay || !box || !messageEl || !okBtn || !cancelBtn) {
            resolve(false);
            return;
        }

        const returnFocus = document.activeElement;

        title.textContent = options.title || 'Confirmar ação';
        messageEl.textContent = message || 'Deseja continuar?';
        okBtn.textContent = options.confirmLabel || 'Confirmar';

        box.classList.toggle('is-danger', !!options.danger);

        let challengeWrap = box.querySelector('.confirm-challenge');
        if (!challengeWrap) {
            challengeWrap = document.createElement('div');
            challengeWrap.className = 'confirm-challenge';
            challengeWrap.innerHTML = `
                <div class="confirm-challenge-label">
                    <i class="ph-duotone ph-shield-warning" aria-hidden="true"></i>
                    Confirmação adicional para uma ação de impacto
                </div>
                <div class="confirm-challenge-question"></div>
                <input
                    class="confirm-challenge-input"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="Resposta da confirmação adicional"
                >
                <div class="confirm-challenge-error" hidden></div>
            `;

            const buttons = box.querySelector('.confirm-buttons');
            box.insertBefore(challengeWrap, buttons);
        }

        const questionEl = challengeWrap.querySelector('.confirm-challenge-question');
        const inputEl = challengeWrap.querySelector('.confirm-challenge-input');
        const errorEl = challengeWrap.querySelector('.confirm-challenge-error');

        let expectedAnswer = null;

        if (options.challenge) {
            /*
             * Desafio simples, propositalmente variável. Ele não substitui
             * as validações do backend; serve como confirmação consciente
             * antes de uma exclusão de impacto.
             */
            const a = Math.floor(Math.random() * 5) + 2;
            const b = Math.floor(Math.random() * 4) + 1;
            expectedAnswer = String(a + b);

            questionEl.textContent = `Para continuar, responda: ${a} + ${b} = ?`;
            inputEl.value = '';
            errorEl.hidden = true;
            errorEl.textContent = '';
            challengeWrap.hidden = false;
        } else {
            challengeWrap.hidden = true;
            inputEl.value = '';
            errorEl.hidden = true;
            errorEl.textContent = '';
        }

        overlay.classList.remove('hidden');
        overlay.setAttribute('aria-hidden', 'false');

        const cleanup = () => {
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            overlay.removeEventListener('click', onBackdrop);
            document.removeEventListener('keydown', onKeydown, true);
            inputEl.removeEventListener('keydown', onInputKeydown);
        };

        const finish = value => {
            cleanup();

            overlay.classList.add('hidden');
            overlay.setAttribute('aria-hidden', 'true');
            box.classList.remove('is-danger');

            returnFocus?.focus?.({ preventScroll: true });
            resolve(value);
        };

        const validateChallenge = () => {
            if (!options.challenge) return true;

            const answer = String(inputEl.value || '').trim();

            if (answer !== expectedAnswer) {
                errorEl.textContent = 'Resposta incorreta. Confira a conta antes de continuar.';
                errorEl.hidden = false;
                inputEl.focus({ preventScroll: true });
                inputEl.select?.();
                return false;
            }

            errorEl.hidden = true;
            return true;
        };

        const onOk = () => {
            if (!validateChallenge()) return;
            finish(true);
        };

        const onCancel = () => finish(false);

        const onBackdrop = event => {
            if (event.target === overlay) finish(false);
        };

        const onKeydown = event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopImmediatePropagation();
                finish(false);
            }
        };

        const onInputKeydown = event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                onOk();
            }
        };

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
        overlay.addEventListener('click', onBackdrop);
        document.addEventListener('keydown', onKeydown, true);
        inputEl.addEventListener('keydown', onInputKeydown);

        if (!window.matchMedia('(max-width: 767px)').matches) {
            window.setTimeout(() => {
                if (options.challenge) {
                    inputEl.focus({ preventScroll: true });
                } else {
                    cancelBtn.focus({ preventScroll: true });
                }
            }, 30);
        }
    });
}

/* ========== TOAST ========== */
function pdToast(msg, type = 'success') {
    const container = document.getElementById('pd-toasts');

    if (!container) {
        return;
    }

    const toast = document.createElement('div');
    const icon = type === 'error'
        ? 'warning-circle'
        : type === 'info'
            ? 'info'
            : 'check-circle';

    toast.className = `pd-toast ${type}`;

    toast.innerHTML = `
        <span class="pd-toast-icon" aria-hidden="true">
            <i class="ph-duotone ph-${icon}"></i>
        </span>

        <span class="pd-toast-message">
            ${esc(msg)}
        </span>
    `;

    container.appendChild(toast);

    window.setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(5px)';
        toast.style.transition = 'opacity .18s ease, transform .18s ease';

        window.setTimeout(() => {
            toast.remove();
        }, 190);
    }, 4000);
}

/* ========== FILTROS ========== */
function advancedFilterCount() {
    return [
        document.getElementById('filter-associate')?.value || '',
        document.getElementById('filter-product')?.value || '',
        document.getElementById('filter-date-from')?.value || '',
        document.getElementById('filter-date-to')?.value || '',
    ].filter(Boolean).length;
}

function setAdvancedFilters(open) {
    const panel = document.getElementById('pd-advanced-filters');
    const button = document.getElementById('pd-filter-more');
    const chevron = button?.querySelector('.pd-filter-more-chevron');

    if (!panel || !button) return;

    panel.hidden = !open;
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    button.classList.toggle('open', open);

    if (chevron) {
        chevron.className = `pd-filter-more-chevron ph-duotone ph-${open ? 'caret-up' : 'caret-down'}`;
    }
}

function toggleAdvancedFilters() {
    const panel = document.getElementById('pd-advanced-filters');
    if (!panel) return;
    setAdvancedFilters(panel.hidden);
}

function updateAdvancedFilterState() {
    const count = advancedFilterCount();
    const badge = document.getElementById('pd-filter-more-count');
    const button = document.getElementById('pd-filter-more');

    if (badge) {
        badge.textContent = count;
        badge.hidden = count === 0;
    }

    button?.classList.toggle('has-active', count > 0);
}

function applyFilters() {
    const hasFilter = ['filter-search','filter-status','filter-associate','filter-product','filter-date-from','filter-date-to']
        .some(id => document.getElementById(id)?.value);
    document.getElementById('clear-filters-btn').style.display = hasFilter ? '' : 'none';
    updateAdvancedFilterState();
    window.clearTimeout(projectListState.filterTimer);
    projectListState.filterTimer = window.setTimeout(() => loadDeliveryPage(), 220);
}

function pdStatusLabel(status) {
    return ({pending:'Pendente',approved:'Aprovada',rejected:'Rejeitada',cancelled:'Cancelada'})[status] || status;
}

function pdQty(value, unit = '') {
    const number = Number(value || 0);
    return number.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 }) + (unit ? ` ${unit}` : '');
}

function pdMoney(value) {
    return Number(value || 0).toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
}

function pdActions(item, mobile = false) {
    const label = mobile ? 'dc-action-label' : 'pd-action-label';
    const button = (classes, icon, text, attrs = '') => {
        let renderedClasses = classes;
        if (mobile) {
            const tone = classes.includes('btn-approve') ? 'approve'
                : classes.includes('btn-reject') ? 'reject'
                : classes.includes('btn-edit') ? 'edit'
                : classes.includes('btn-distribute') ? 'distribute'
                : classes.includes('btn-delete') ? 'delete'
                : 'notes';
            renderedClasses += ` dc-action ${tone}`;
        }
        return `<button type="button" class="${renderedClasses}" ${attrs} title="${text}" aria-label="${text}"><i class="ph-duotone ph-${icon}"></i><span class="${label}">${text}</span></button>`;
    };
    let html = item.notes ? button('delivery-note-trigger', 'chat-text', 'Observações', `data-delivery-notes="${esc(item.notes)}" data-delivery-notes-title="Observações da entrega" data-delivery-notes-meta="${esc(item.product_name + ' · ' + item.associate_name)}"`) : '';
    const editAttrs = `data-id="${item.id}" data-date="${item.delivery_date_raw}" data-qty="${item.quantity}" data-price="${item.unit_price}" data-quality="${esc(item.quality_grade || '')}" data-notes="${esc(item.notes || '')}" data-unit="${esc(item.unit)}" data-distributions="${esc(JSON.stringify(item.distributions || []))}"`;
    if (item.status_value === 'pending') {
        html += button('btn-approve btn-xs', 'check-circle', 'Aprovar', `data-id="${item.id}"`);
        html += button('btn-reject btn-xs', 'x-circle', 'Rejeitar', `data-id="${item.id}"`);
        html += button('btn-edit btn-xs', 'pencil-simple', 'Editar', editAttrs);
        html += button('btn-delete-approved btn-xs', 'trash', 'Excluir', `data-id="${item.id}"`);
    } else if (item.status_value === 'approved') {
        html += button('btn-distribute btn-xs', 'git-merge', 'Distribuir', `data-id="${item.id}" data-product="${esc(item.product_name)}" data-unit="${esc(item.unit)}" data-qty="${item.quantity}" data-distributed="${item.distributed_qty}" data-existing="${esc(JSON.stringify(item.distributions || []))}" data-participants="${esc(JSON.stringify(DM_PROJECT_PARTICIPANTS))}" data-default-customer-id="${item.default_customer_id || ''}" data-notes="${esc(item.notes || '')}" data-context="${item.sales_project_id}:${item.associate_id}"`);
        html += button('btn-edit btn-xs', 'pencil-simple', 'Editar', editAttrs);
        if (!item.has_billed) html += button('btn-delete-approved btn-xs', 'trash', 'Excluir', `data-id="${item.id}"`);
    } else if (item.status_value === 'rejected' && !item.has_billed) {
        html += button('btn-delete-approved btn-xs', 'trash', 'Excluir', `data-id="${item.id}"`);
    }
    return html;
}

function pdMetrics(item) {
    const total = Number(item.quantity || 0);
    const distributed = Number(item.distributed_qty || 0);
    const over = distributed > total + .0005;
    const percent = total > 0 ? Math.min(100, Math.round(distributed / total * 100)) : 0;
    return { total, distributed, over, percent, display: over ? 100 : percent };
}

function renderProjectRow(item) {
    const m = pdMetrics(item), limit = item.limit || {}, limitPct = limit.associate_percent == null ? null : Math.min(100, Number(limit.associate_percent));
    const distributions = esc(JSON.stringify(item.distributions || []));
    return `<tr id="desktop-row-${item.id}" class="pdr-row status-${item.status_value} ${item.status_value === 'approved' ? 'approved-row' : ''}" data-delivery-id="${item.id}" data-total-qty="${m.total}" data-unit="${esc(item.unit)}" data-product="${esc(item.product_name)}" data-distributed="${m.distributed}" data-distributions="${distributions}" data-filter-date="${item.delivery_date_raw}" data-filter-associate="${esc(item.associate_name)}" data-filter-product="${esc(item.product_name)}" data-filter-status="${item.status_value}">
        <td><span class="pdr-date">${esc(item.delivery_date)}</span></td><td><strong class="pdr-primary-text">${esc(item.associate_name)}</strong></td><td><strong class="pdr-primary-text pdr-product">${esc(item.product_name)}</strong></td><td><span class="pdr-qty">${pdQty(m.total,item.unit)}</span></td><td class="pd-col-net"><strong class="pdr-money">${item.dist_net_value > 0 ? pdMoney(item.dist_net_value) : '—'}</strong></td><td class="pd-col-suppressed"><span class="pdr-quality">${esc(item.quality_grade || '—')}</span></td><td class="pd-col-suppressed"><span class="badge-status ${item.status_value}">${pdStatusLabel(item.status_value)}</span></td>
        <td class="pd-limit-cell">${limitPct == null ? '<span class="pdr-no-limit">Sem cota</span>' : `<div class="pdr-limit ${limitPct >= 100 ? 'red' : limitPct >= 80 ? 'amber' : 'green'}"><div class="pdr-limit-head"><span class="pdr-limit-used">${pdQty(limit.associate_delivered,item.unit)}</span><strong class="pdr-limit-free">${pdQty(limit.associate_remaining,item.unit)} livres</strong></div><div class="pdr-limit-track"><span style="width:${limitPct}%"></span></div></div>`}</td>
        <td class="pd-control-cell"><button type="button" class="pdr-dist ${m.over ? 'over' : m.percent >= 100 ? 'full' : 'partial'}" data-summary="1"><div class="pdr-dist-head"><span>Distribuição</span><strong>${m.over ? '!' : m.percent + '%'}</strong></div><div class="dist-bar-bg"><span class="dist-bar-fill ${m.over ? 'over' : m.percent >= 100 ? 'full' : 'partial'}" style="display:block;width:${m.display}%"></span></div></button></td><td class="pd-action-cell"><div class="action-btns pdr-actions">${pdActions(item)}</div></td></tr>`;
}

function renderProjectCard(item) {
    const m = pdMetrics(item), limit = item.limit || {}, limitPct = limit.associate_percent == null ? null : Math.min(100, Number(limit.associate_percent));
    const visualStatus = item.status_value === 'approved' && m.percent >= 100 && !m.over ? 'distributed' : item.status_value;
    const signals = `${item.has_billed ? '<span class="dc-signal billed" title="Entrega faturada"><i class="ph-duotone ph-receipt"></i></span>' : ''}${item.issue_count > 0 ? `<button type="button" class="pd-issue-btn ${item.issue_severity || 'warning'}" onclick="openIntegrityModal(${item.id})" title="Ver pendências desta entrega" aria-label="Ver ${item.issue_count} pendência(s) desta entrega"><i class="ph-duotone ph-warning"></i>${item.issue_count}</button>` : ''}`;
    return `<article class="mobile-card delivery-card-v2 status-${visualStatus}" id="mobile-row-${item.id}" data-delivery-id="${item.id}" data-total-qty="${m.total}" data-unit="${esc(item.unit)}" data-product="${esc(item.product_name)}" data-distributed="${m.distributed}" data-distributions="${esc(JSON.stringify(item.distributions || []))}" data-filter-date="${item.delivery_date_raw}" data-filter-associate="${esc(item.associate_name)}" data-filter-product="${esc(item.product_name)}" data-filter-status="${item.status_value}">
        <div class="dc-head mc-head"><div class="dc-main"><div class="dc-product-line"><strong class="dc-product mc-head-product">${esc(item.product_name)}</strong><span class="badge-status ${item.status_value}">${pdStatusLabel(item.status_value)}</span></div>${signals ? `<div class="dc-signals">${signals}</div>` : ''}<div class="dc-context"><span class="dc-context-item"><i class="ph-duotone ph-user"></i><span class="dc-associate mc-assoc">${esc(item.associate_name)}</span></span><span class="dc-context-item date"><i class="ph-duotone ph-calendar-dots"></i>${esc(item.delivery_date)}</span></div></div><div class="dc-side"><strong class="dc-qty mc-head-qty">${pdQty(m.total,item.unit)}</strong></div></div>
        <div class="dc-body mc-body">${limitPct == null ? '' : `<div class="dc-meter dc-limit-meter ${limitPct >= 100 ? 'red' : limitPct >= 80 ? 'amber' : 'green'}"><div class="dc-meter-head"><span class="dc-meter-label"><i class="ph-duotone ph-gauge"></i>Cota</span><strong class="dc-meter-value">${pdQty(limit.associate_remaining,item.unit)} livres</strong></div><div class="dc-track"><span style="width:${limitPct}%"></span></div></div>`}<div class="dc-meter dc-distribution"><div class="dc-meter-head"><span class="dc-meter-label"><i class="ph-duotone ph-git-merge"></i>Distribuição</span><strong class="dc-meter-value">${m.over ? 'Excedeu ' + pdQty(m.distributed-m.total,item.unit) : m.percent >= 100 ? 'Concluída' : pdQty(m.total-m.distributed,item.unit) + ' restantes'}</strong></div><div class="mc-dist-indicator" role="button" tabindex="0" data-summary="1"><div class="mc-dist-bar-bg"><div class="mc-dist-bar-fill ${m.over ? 'over' : m.percent >= 100 ? 'full' : 'partial'}" style="width:${m.display}%"></div></div><span class="mc-dist-text">${m.over ? '!' : m.percent + '%'}</span></div></div><div class="dc-actions mc-actions">${pdActions(item,true)}</div></div></article>`;
}

function updateProjectFilterOptions(filters) {
    [['filter-associate', filters?.associates || [], 'Todos os associados'], ['filter-product', filters?.products || [], 'Todos os produtos']].forEach(([id, options, placeholder]) => {
        const select = document.getElementById(id), current = select?.value || '';
        if (!select) return;
        select.innerHTML = `<option value="">${placeholder}</option>` + options.map(option => `<option value="${option.id}">${esc(option.name)}</option>`).join('');
        select.value = current;
    });
}

async function loadDeliveryPage(silent = false) {
    projectListState.abort?.abort();
    const controller = new AbortController(); projectListState.abort = controller; projectListState.loading = true;
    const skeleton = document.getElementById('pd-delivery-skeleton');
    const surface = document.getElementById('pd-deliveries-surface');
    surface?.setAttribute('aria-busy', 'true');
    if (!silent) skeleton.hidden = false;
    const query = new URLSearchParams({ page:String(projectListState.page), per_page:String(projectListState.perPage) });
    if (!projectListState.filtersLoaded) query.set('include_filters', '1');
    const requestedDelivery = Number(new URLSearchParams(window.location.search).get('open_delivery') || 0);
    if (requestedDelivery > 0) query.set('delivery_id', String(requestedDelivery));
    const fields = {search:'filter-search',status:'filter-status',associate_id:'filter-associate',product_id:'filter-product',date_from:'filter-date-from',date_to:'filter-date-to'};
    Object.entries(fields).forEach(([key,id]) => { const value = document.getElementById(id)?.value; if (value) query.set(key,value); });
    try {
        const response = await fetch(`/${PD_TENANT}/delivery/projects/${PD_PROJECT}/deliveries-data?${query}`, { cache:'no-store', signal:controller.signal, headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, globalLoader:false });
        const payload = await response.json(); if (!response.ok || !payload.success) throw new Error(payload.message || 'Não foi possível carregar as entregas.');

        if (payload.data.length === 0 && payload.meta.total > 0 && projectListState.page > payload.meta.last_page) {
            projectListState.page = payload.meta.last_page;
            return loadDeliveryPage(silent);
        }

        document.getElementById('desktop-tbody').innerHTML = payload.data.map(renderProjectRow).join('');
        document.getElementById('mobile-cards').innerHTML = payload.data.map(renderProjectCard).join('');
        document.getElementById('pd-empty').hidden = payload.data.length > 0;
        projectListState.page = payload.meta.current_page; projectListState.lastPage = payload.meta.last_page;
        document.getElementById('filtered-count').textContent = payload.meta.total;
        updateProjectPagination(payload.meta.total, payload.meta.from || 0, payload.meta.to || 0, payload.meta.current_page, payload.meta.last_page);
        if (payload.filters) {
            updateProjectFilterOptions(payload.filters);
            projectListState.filtersLoaded = true;
        }
        updateProjectSummary(payload.summary); enhanceDeliveryActions();
    } catch (error) { if (error.name !== 'AbortError') pdToast(error.message, 'error'); }
    finally { if (projectListState.abort === controller) { projectListState.loading = false; skeleton.hidden = true; surface?.setAttribute('aria-busy', 'false'); } }
}

function updateProjectSummary(summary) {
    projectListState.summary = summary || {};
    [['pd-count-all','all'],['pd-count-pending','pending'],['pd-count-approved','approved'],['pd-count-rejected','rejected']].forEach(([id,key]) => { const el=document.getElementById(id); if(el) el.textContent=summary?.[key] || 0; });
    ['pending','approved','rejected'].forEach(status => { const el=document.getElementById('pd-shortcut-'+status); if(el) el.hidden = !(summary?.[status] > 0); });
    const tools=document.getElementById('pd-tools-bar'); if(tools) tools.hidden = !(summary?.approved > 0 || Number(document.getElementById('pd-integrity-total')?.textContent || 0) > 0);
    const text=document.getElementById('pd-tools-summary'); if(text) text.textContent = `${summary?.all || 0} entrega(s)${summary?.net > 0 ? ' · ' + pdMoney(summary.net) + ' líquido' : ''}`;
    refreshOperationalUi();
}

function rowMatchesFilter(row, search, status, assoc, prod, dateFrom, dateTo) {
    const cells = row.querySelectorAll('td');
    const dateText   = row.dataset.filterDate || (cells[1]?.textContent || '').trim();
    const assocText  = row.dataset.filterAssociate || (cells[2]?.textContent || '').trim();
    const prodText   = row.dataset.filterProduct || (cells[3]?.textContent || '').trim();
    const statusText = row.dataset.filterStatus || (row.querySelector('.badge-status')?.textContent || '').trim().toLowerCase();

    if (status && statusText !== status) return false;
    if (assoc && assocText !== assoc) return false;
    if (prod && prodText !== prod) return false;
    if (dateFrom && dateText < dateFrom) return false;
    if (dateTo && dateText > dateTo) return false;
    if (search && !normalizeFilterText(`${dateText} ${assocText} ${prodText}`).includes(search)) return false;
    return true;
}

function cardMatchesFilter(card, search, status, assoc, prod, dateFrom, dateTo) {
    const dateText   = card.dataset.filterDate || (card.querySelector('.mc-date')?.textContent || '').trim();
    const assocText  = card.dataset.filterAssociate || (card.querySelector('.mc-assoc')?.textContent || '').trim();
    const prodText   = card.dataset.filterProduct || (card.querySelector('.mc-product')?.textContent || '').trim();
    const statusText = card.dataset.filterStatus || (card.querySelector('.badge-status')?.textContent || '').trim().toLowerCase();

    if (status && statusText !== status) return false;
    if (assoc && assocText !== assoc) return false;
    if (prod && prodText !== prod) return false;
    if (dateFrom && dateText < dateFrom) return false;
    if (dateTo && dateText > dateTo) return false;
    if (search && !normalizeFilterText(`${dateText} ${assocText} ${prodText}`).includes(search)) return false;
    return true;
}

function normalizeFilterText(value) {
    return (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function updateProjectPagination(total, start, end, page, totalPages) {
    const wrap = document.getElementById('project-pagination');
    if (!wrap) return;
    wrap.style.display = total > 0 ? 'grid' : 'none';
    document.getElementById('project-page-info').textContent = total > 0
        ? `${start}-${end} de ${total} · Página ${page} de ${totalPages}`
        : '';
    document.getElementById('project-prev').disabled = page <= 1;
    document.getElementById('project-next').disabled = page >= totalPages;
}

function parseCardDistributions(card) {
    try {
        if (card?.dataset?.distributionsB64) {
            return JSON.parse(atob(card.dataset.distributionsB64)) || [];
        }
        return JSON.parse(card?.dataset?.distributions || '[]') || [];
    }
    catch (e) { return []; }
}

function fmtProjectQty(n, unit) {
    const num = parseFloat(n) || 0;
    const str = num % 1 === 0 ? String(num) : num.toFixed(2).replace(/\.?0+$/, '');
    return str + (unit ? ' ' + unit : '');
}

function openDistSummaryFromCard(card) {
    const product = card?.dataset?.product || 'Produto';
    const unit = card?.dataset?.unit || '';
    const totalQty = parseFloat(card?.dataset?.totalQty || 0);
    const distQty = parseFloat(card?.dataset?.distributed || 0);
    const distributions = parseCardDistributions(card);
    document.getElementById('dist-summary-title').textContent = product;
    document.getElementById('dist-summary-sub').textContent = `${fmtProjectQty(distQty, unit)} distribuídos de ${fmtProjectQty(totalQty, unit)}`;
    document.getElementById('dist-summary-body').innerHTML = distributions.length
        ? distributions.map(d => {
            const customer = d.customer || d.customer_name || d.customerName || 'Cliente';
            const qty = parseFloat(d.qty || d.quantity || 0);
            const net = parseFloat(d.net || d.net_value || 0);
            return `<div class="dist-summary-row"><strong>${esc(customer)}</strong><span>${fmtProjectQty(qty, unit)}${net > 0 ? ' - R$ ' + net.toFixed(2) : ''}</span></div>`;
        }).join('')
        : '<div class="dist-summary-row"><strong>Nenhuma distribuição</strong><span>0%</span></div>';
    const overlay = document.getElementById('dist-summary-overlay');
    overlay?.classList.add('open');
    overlay?.setAttribute('aria-hidden', 'false');
    document.body.classList.add('pd-sheet-open');
}

function closeDistSummary() {
    const overlay = document.getElementById('dist-summary-overlay');
    overlay?.classList.remove('open');
    overlay?.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('pd-sheet-open');
}

function clearAllFilters() {
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-associate').value = '';
    document.getElementById('filter-product').value = '';
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value = '';
    projectListState.page = 1;
    setAdvancedFilters(false);
    applyFilters();
}

// Attach filter listeners
['filter-search','filter-status','filter-associate','filter-product','filter-date-from','filter-date-to'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => {
        projectListState.page = 1;
        applyFilters();
    });
});

document.getElementById('delivery-page-size')?.addEventListener('change', function() {
    projectListState.perPage = this.value === 'all' ? 'all' : parseInt(this.value || 30, 10);
    projectListState.page = 1;
    applyFilters();
});
document.getElementById('project-prev')?.addEventListener('click', function() {
    projectListState.page = Math.max(1, projectListState.page - 1);
    applyFilters();
});
document.getElementById('project-next')?.addEventListener('click', function() {
    projectListState.page += 1;
    applyFilters();
});

/* ========== DISTRIBUTION INDICATOR UPDATE ========== */
function updateDistIndicator(container, totalQty, distQty, unit) {
    const total = parseFloat(totalQty) || 0;
    const dist  = parseFloat(distQty) || 0;
    const percent = total > 0 ? Math.min(Math.round((dist / total) * 100), 100) : 0;
    const over = dist > total;

    const fill = container.querySelector('.dist-bar-fill, .mc-dist-bar-fill');
    const text = container.querySelector('.dist-text, .mc-dist-text');

    if (fill) {
        fill.style.width = (over ? 100 : percent) + '%';
        fill.className = fill.className.replace(/\b(full|partial|over)\b/g, '');
        fill.classList.add(over ? 'over' : (percent >= 100 ? 'full' : 'partial'));
    }
    if (text) {
        text.innerHTML = over
            ? `<i class="ph-duotone ph-warning" aria-hidden="true"></i>${dist.toFixed(1)}`
            : `${percent}%`;
    }

    // Additional warning
    let warningEl = container.nextElementSibling;
    if (warningEl && warningEl.classList.contains('dist-warning')) warningEl.remove();
    if (over) {
        const w = document.createElement('span');
        w.className = 'dist-warning';
        w.textContent = 'Excede ' + unit;
        container.after(w);
    } else if (dist > 0 && percent < 100) {
        const w = document.createElement('span');
        w.className = 'dist-warning';
        w.textContent = 'Falta ' + (total - dist).toFixed(2) + ' ' + unit;
        w.style.color = '#d97706';
        container.after(w);
    }
}

/* ========== ACTION HANDLERS ========== */
document.addEventListener('click', async function(e) {
    const summary = e.target.closest('.mc-dist-indicator[data-summary], .dist-indicator[data-summary]');
    if (summary) {
        openDistSummaryFromCard(summary.closest('.mobile-card, tr[data-delivery-id]'));
        return;
    }

    const approveBtn  = e.target.closest('.btn-approve');
    const rejectBtn   = e.target.closest('.btn-reject');
    const editBtn     = e.target.closest('.btn-edit');
    const distBtn     = e.target.closest('.btn-distribute');
    const deleteBtn   = e.target.closest('.btn-delete-approved');

    if (editBtn)  { EditModal.openFromBtn(editBtn); return; }
    if (distBtn)  { DistModal.openFromBtn(distBtn); return; }

    if (deleteBtn) {
        const id = deleteBtn.dataset.id;
        const confirmed = await customConfirm('Excluir esta entrega? Esta ação também removerá as distribuicoes associadas quando existirem e nao pode ser desfeita.');
        if (!confirmed) return;
        deleteBtn.disabled = true;
        try {
            const res  = await fetch(`/${PD_TENANT}/delivery/deliveries/${id}`, {
                method : 'DELETE',
                headers: { 'X-CSRF-TOKEN': PD_CSRF, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                // Remove both desktop row and mobile card
                document.getElementById('desktop-row-' + id)?.remove();
                document.getElementById('mobile-row-' + id)?.remove();
                applyFilters();
                pdToast('Entrega excluída.');
            } else {
                pdToast(data.message || 'Erro ao excluir.', 'error');
                deleteBtn.disabled = false;
            }
        } catch(err) {
            pdToast('Erro de comunicação com o servidor.', 'error');
            deleteBtn.disabled = false;
        }
        return;
    }

    if (approveBtn || rejectBtn) {
        const btn = approveBtn || rejectBtn;
        const id = btn.dataset.id;
        const action = approveBtn ? 'approve' : 'reject';
        const confirmed = await customConfirm(action === 'approve' ? 'Aprovar esta entrega?' : 'Rejeitar esta entrega?');
        if (!confirmed) return;

        // Find both desktop row and mobile card
        const row  = document.getElementById('desktop-row-' + id);
        const card = document.getElementById('mobile-row-' + id);
        const btns = document.querySelectorAll(`.btn-approve[data-id="${id}"], .btn-reject[data-id="${id}"]`);
        btns.forEach(b => b.disabled = true);

        try {
            const res  = await fetch(`/${PD_TENANT}/delivery/deliveries/${id}/${action}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PD_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                try {
                    await refreshDeliveryItem(id);
                    pdToast(data.message || (action === 'approve' ? 'Entrega aprovada.' : 'Entrega rejeitada.'));
                } catch (refreshError) {
                    pdToast(
                        refreshError.message || 'A operação foi salva, mas não foi possível atualizar a entrega.',
                        'error'
                    );
                }
            } else {
                pdToast(data.message || 'Erro ao processar.', 'error');
                btns.forEach(b => b.disabled = false);
            }
        } catch(err) {
            pdToast('Erro de comunicação com o servidor.', 'error');
            btns.forEach(b => b.disabled = false);
        }
    }
});

function buildApprovedActions(id, rowEl) {
    const qty  = parseFloat(rowEl?.querySelector('.btn-edit')?.dataset.qty || rowEl?.dataset?.qty || 0);
    const prod = rowEl?.querySelector('td:nth-child(4)')?.textContent?.trim() || '';
    const unit = rowEl?.querySelector('.btn-edit')?.dataset?.unit || '';
    return `
        <button class="btn-distribute" data-id="${id}" data-product="${esc(prod)}" data-unit="${esc(unit)}"
            data-qty="${qty}" data-distributed="0" data-existing="[]"
            data-participants="${esc(JSON.stringify(DM_PROJECT_PARTICIPANTS))}" title="Distribuir" aria-label="Distribuir">
            <i class="ph-duotone ph-git-merge"></i><span class="pd-action-label">Distribuir</span>
        </button>
        <button class="btn-edit" data-id="${id}" data-date="" data-qty="${qty}" data-price="" data-quality="" data-notes="" data-unit="${unit}" data-distributions="[]" title="Editar" aria-label="Editar">
            <i class="ph-duotone ph-pencil-simple"></i><span class="pd-action-label">Editar</span>
        </button>
        <button class="btn-delete-approved" data-id="${id}" title="Excluir entrega" aria-label="Excluir entrega">
            <i class="ph-duotone ph-trash"></i><span class="pd-action-label">Excluir</span>
        </button>
    `;
}

function buildApprovedActionsMobile(id, cardEl) {
    const qty  = parseFloat(cardEl?.dataset?.totalQty || 0);
    const unit = cardEl?.dataset?.unit || '';
    const prod = cardEl?.querySelector('.mc-product')?.textContent?.trim() || '';
    return `
        <button class="btn-distribute btn-xs" data-id="${id}" data-product="${esc(prod)}" data-unit="${esc(unit)}"
            data-qty="${qty}" data-distributed="0" data-existing="[]"
            data-participants="${esc(JSON.stringify(DM_PROJECT_PARTICIPANTS))}"
            title="Distribuir" aria-label="Distribuir">
            <i class="ph-duotone ph-git-merge"></i>
        </button>
        <button class="btn-edit btn-xs" data-id="${id}" data-date="" data-qty="${qty}" data-price="" data-quality="" data-notes="" data-unit="${unit}" data-distributions="[]"
            title="Editar" aria-label="Editar">
            <i class="ph-duotone ph-pencil-simple"></i>
        </button>
        <button class="btn-delete-approved btn-xs" data-id="${id}" title="Excluir entrega" aria-label="Excluir entrega">
            <i class="ph-duotone ph-trash"></i>
        </button>
    `;
}

function buildRejectedActions(id) {
    return `
        <button class="btn-delete-approved" data-id="${id}" title="Excluir entrega rejeitada" aria-label="Excluir entrega rejeitada">
            <i class="ph-duotone ph-trash"></i><span class="pd-action-label">Excluir</span>
        </button>
    `;
}

function buildRejectedActionsMobile(id) {
    return `
        <button class="btn-delete-approved btn-xs" data-id="${id}" title="Excluir entrega rejeitada" aria-label="Excluir entrega rejeitada">
            <i class="ph-duotone ph-trash"></i>
        </button>
    `;
}

function esc(s) { return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function setQuickStatusFilter(status) {
    const select = document.getElementById('filter-status');
    if (!select) return;
    select.value = status || '';
    projectListState.page = 1;
    applyFilters();
}

function refreshOperationalUi() {
    const counts = projectListState.summary || {
        all: 0,
        pending: 0,
        approved: 0,
        rejected: 0,
        cancelled: 0,
    };

    const write = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = String(value);
    };
    write('pd-count-all', counts.all);
    write('pd-count-pending', counts.pending);
    write('pd-count-approved', counts.approved);
    write('pd-count-rejected', counts.rejected);

    const pending = document.getElementById('pd-shortcut-pending');
    const approved = document.getElementById('pd-shortcut-approved');
    const rejected = document.getElementById('pd-shortcut-rejected');
    if (pending) pending.hidden = counts.pending === 0;
    if (approved) approved.hidden = counts.approved === 0;
    if (rejected) rejected.hidden = counts.rejected === 0;

    const activeStatus = document.getElementById('filter-status')?.value || '';
    document.querySelectorAll('[data-pd-status]').forEach(button => {
        button.classList.toggle('active', (button.dataset.pdStatus || '') === activeStatus);
    });
}

function arrangeDeliveryControls(root = document) {
    root.querySelectorAll('#desktop-tbody tr[data-delivery-id]').forEach(row => {
        if (row.dataset.pdArranged === '1') return;

        const cells = Array.from(row.children).filter(cell => cell.tagName === 'TD');
        if (!cells.length) return;

        const offset = cells[0]?.classList.contains('chk-cell') ? 1 : 0;
        const qualityCell = cells[5 + offset];
        const statusCell = cells[6 + offset];
        const limitCell = cells[7 + offset];
        const distributionCell = cells[8 + offset];
        const actionCell = cells[9 + offset];

        cells[offset]?.classList.add('pd-date-cell');
        qualityCell?.classList.add('pd-col-suppressed');
        statusCell?.classList.add('pd-col-suppressed');
        limitCell?.classList.add('pd-limit-cell');
        distributionCell?.classList.add('pd-control-cell');
        actionCell?.classList.add('pd-action-cell');

        if (cells[1 + offset]) cells[1 + offset].title ||= row.dataset.filterAssociate || '';
        if (cells[2 + offset]) cells[2 + offset].title ||= row.dataset.filterProduct || '';

        if (!distributionCell || !actionCell) return;

        let control = distributionCell.querySelector(':scope > .pd-table-control-row');
        if (!control) {
            control = document.createElement('div');
            control.className = 'pd-table-control-row';
            distributionCell.prepend(control);
        }

        const indicator = distributionCell.querySelector('.dist-indicator, .pdr-dist');
        const note = actionCell.querySelector('.delivery-note-trigger');
        const actions = actionCell.querySelector('.action-btns');

        if (indicator && indicator.parentElement !== control) control.appendChild(indicator);
        if (note && note.parentElement !== control) control.appendChild(note);
        if (actions && actions.parentElement !== control) control.appendChild(actions);

        row.dataset.pdArranged = '1';
    });

    root.querySelectorAll('#mobile-cards .mobile-card').forEach(card => {
        if (card.dataset.pdArranged === '1') return;

        const indicator = card.querySelector('.mc-dist-indicator');
        const actions = card.querySelector('.mc-actions');
        if (!indicator || !actions) return;

        let control = card.querySelector('.pd-card-control-row');
        if (!control) {
            const commonParent = indicator.parentElement === actions.parentElement ? indicator.parentElement : null;
            if (commonParent && commonParent !== card) {
                control = commonParent;
                control.classList.add('pd-card-control-row');
            } else {
                control = document.createElement('div');
                control.className = 'pd-card-control-row';
                const body = card.querySelector('.mc-body') || card;
                body.appendChild(control);
                control.appendChild(indicator);
                control.appendChild(actions);
            }
        } else {
            if (indicator.parentElement !== control) control.appendChild(indicator);
            if (actions.parentElement !== control) control.appendChild(actions);
        }

        card.dataset.pdArranged = '1';
    });
}

function deliveryActionMeta(button) {
    if (button.classList.contains('btn-distribute')) return { icon: 'git-merge', label: 'Distribuir' };
    if (button.classList.contains('btn-edit')) return { icon: 'pencil-simple', label: 'Editar' };
    if (button.classList.contains('btn-approve')) return { icon: 'check-circle', label: 'Aprovar' };
    if (button.classList.contains('btn-reject')) return { icon: 'x-circle', label: 'Rejeitar' };
    if (button.classList.contains('btn-delete-approved')) return { icon: 'trash', label: 'Excluir' };
    return null;
}

function enhanceDeliveryActions(root = document) {
    // Seleção de entregas não faz mais parte desta tela.
    root.querySelectorAll('.delivery-chk').forEach(input => input.remove());

    root.querySelectorAll('#desktop-tbody .action-btns button').forEach(button => {
        const meta = deliveryActionMeta(button);
        if (!meta || button.dataset.pdEnhanced === '1') return;

        button.dataset.pdEnhanced = '1';
        button.title = button.title || meta.label;
        button.setAttribute('aria-label', button.getAttribute('aria-label') || meta.label);
        button.innerHTML = `<i class="ph-duotone ph-${meta.icon}" aria-hidden="true"></i><span class="pd-action-label">${meta.label}</span>`;
    });

    root.querySelectorAll('#mobile-cards .mc-actions button').forEach(button => {
        const meta = deliveryActionMeta(button);
        if (!meta || button.dataset.pdEnhanced === '1') return;

        button.dataset.pdEnhanced = '1';
        button.title = meta.label;
        button.setAttribute('aria-label', meta.label);
        button.innerHTML = `<i class="ph-duotone ph-${meta.icon}" aria-hidden="true"></i>`;
    });

    root.querySelectorAll('.delivery-note-trigger').forEach(button => {
        if (button.querySelector('.ph-duotone')) return;

        button.insertAdjacentHTML(
            'afterbegin',
            '<i class="ph-duotone ph-note-pencil" aria-hidden="true"></i>'
        );
    });

    arrangeDeliveryControls(root);
    refreshOperationalUi();
    upgradeDuotoneIcons(root);
}

const DM_PROJECT_PARTICIPANTS = @json($customers->pluck('id')->values()->all());

/* ========== Central de integridade reativa ========== */
function integrityActionLabel(actionKey) {
    return ({
        open_distribution: 'Distribuir',
        edit_distribution: 'Corrigir distribuição',
        open_producers: 'Abrir produtor',
        detach_missing_associate_receipt: 'Desvincular',
        delete_orphan_distribution: 'Excluir órfã',
        restore_parent_delivery: 'Restaurar entrega-pai',
    })[actionKey] || 'Ver detalhes';
}

function renderIntegrityCenter(integrity) {
    const modal = document.getElementById('pd-integrity-modal');
    const body = modal?.querySelector('.pd-integrity-body');
    const counts = integrity?.counts || {};

    const critical = Number(counts.critical || 0);
    const warning = Number(counts.warning || 0);
    const info = Number(counts.info || 0);
    const total = critical + warning + info;

    const totalEl = document.getElementById('pd-integrity-total');
    const launch = document.getElementById('pd-integrity-launch');

    if (totalEl) {
        totalEl.textContent = String(total);
    }

    if (launch) {
        launch.hidden = total <= 0;
        launch.setAttribute(
            'aria-label',
            `Ver ${total} pendência(s) e inconsistência(s)`
        );
    }
    const tools = document.getElementById('pd-tools-bar');
    if (tools && total > 0) tools.hidden = false;

    if (!body) {
        return;
    }

    const severityMeta = {
        critical: {
            label: 'Crítico',
            icon: 'warning-circle',
        },
        warning: {
            label: 'Atenção',
            icon: 'warning',
        },
        info: {
            label: 'Informativo',
            icon: 'info',
        },
    };

    body.innerHTML = ['critical', 'warning', 'info']
        .map(severity => {
            const issues = Array.isArray(integrity?.[severity])
                ? integrity[severity]
                : [];

            if (!issues.length) {
                return '';
            }

            const items = issues.map(issue => {
                const actionKey = String(issue.actionKey || '');
                const deliveryId = Number(issue.deliveryId || 0);
                const distributionId = Number(issue.distributionId || 0);
                const associateId = Number(issue.associateId || 0);
                const associateName = String(issue.associateName || '');

                const actionButton = actionKey
                    ? `
                        <div class="pd-integrity-actions">
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                data-pd-integrity-action="${esc(actionKey)}"
                                data-delivery-id="${deliveryId}"
                                data-distribution-id="${distributionId}"
                                data-associate-id="${associateId}"
                                data-associate-name="${esc(associateName)}"
                            >
                                <i class="ph-duotone ph-wrench" aria-hidden="true"></i>
                                ${esc(integrityActionLabel(actionKey))}
                            </button>
                        </div>
                    `
                    : '';

                return `
                    <article
                        class="pd-integrity-item"
                        data-modal-issue-delivery="${deliveryId || ''}"
                        data-integrity-item="${esc(actionKey)}-${distributionId || ''}"
                    >
                        <div class="pd-integrity-item-title">
                            ${esc(issue.title || 'Revisão necessária')}
                        </div>

                        <div class="pd-integrity-item-message">
                            ${esc(issue.message || '')}
                        </div>

                        ${issue.action ? `
                            <div class="pd-integrity-item-action">
                                ${esc(issue.action)}
                            </div>
                        ` : ''}

                        ${actionButton}
                    </article>
                `;
            }).join('');

            return `
                <section class="pd-integrity-column ${severity}">
                    <header class="pd-integrity-column-head">
                        <i
                            class="ph-duotone ph-${severityMeta[severity].icon}"
                            aria-hidden="true"
                        ></i>
                        ${severityMeta[severity].label}
                    </header>

                    <div class="pd-integrity-items">
                        ${items}
                    </div>
                </section>
            `;
        })
        .join('');

    if (!total) {
        body.innerHTML = `
            <div class="pd-integrity-empty-state">
                <i class="ph-duotone ph-check-circle" aria-hidden="true"></i>
                <strong>Nenhuma inconsistência pendente</strong>
                <span>Os dados deste projeto estão consistentes neste momento.</span>
            </div>
        `;
    }

    setIntegrityModalScope(
        total > 0 ? integrityFilterDeliveryId : null
    );

    upgradeDuotoneIcons(body);
}

async function refreshIntegrityCenter() {
    const modal = document.getElementById('pd-integrity-modal');

    if (!modal) return null;

    const url =
        `/${PD_TENANT}/delivery/projects/${PD_PROJECT}/integrity`
        + `?_=${Date.now()}`;

    const response = await fetch(url, {
        cache: 'no-store',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        globalLoader: false,
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
        throw new Error(
            data.message || 'Não foi possível atualizar a central de inconsistências.'
        );
    }

    renderIntegrityCenter(data.integrity);
    return data.integrity;
}

/* ========== Sincronização reativa da listagem ========== */
function resolveDeliveryId(...values) {
    for (const value of values) {
        const candidate = Number(
            value?.delivery_id
            ?? value?.parent_delivery_id
            ?? value?.id
            ?? value
            ?? 0
        );

        if (candidate > 0) return candidate;
    }

    return 0;
}

async function refreshDeliveryItem(id) {
    const deliveryId = resolveDeliveryId(id);

    if (!deliveryId) {
        throw new Error('Entrega inválida para atualização.');
    }

    await loadDeliveryPage(true);
    return { success: true, delivery_id: deliveryId };
}

async function syncDeliveryAfterMutation(id, successMessage, options = {}) {
    const deliveryId = resolveDeliveryId(id);

    if (!deliveryId) return false;

    const maxAttempts = Math.max(1, Number(options.attempts || 3));
    let deliveryError = null;

    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            // A lista e a central voltam da fonte de verdade após cada mutação.
            const [deliveryResult, integrityResult] = await Promise.allSettled([
                refreshDeliveryItem(deliveryId),
                refreshIntegrityCenter(),
            ]);

            if (deliveryResult.status === 'fulfilled') {
                if (integrityResult.status === 'rejected') {
                    console.warn('Central de inconsistências não atualizada:', integrityResult.reason);
                }

                updateDistributionProgressTone();
                upgradeDuotoneIcons(document);

                if (successMessage) {
                    pdToast(successMessage);
                }

                return true;
            }

            deliveryError = deliveryResult.reason;

            if (attempt < maxAttempts) {
                await new Promise(resolve =>
                    window.setTimeout(resolve, 180 * attempt)
                );
            }
        } catch (error) {
            deliveryError = error;
        }
    }

    pdToast(
        deliveryError?.message
            || 'A alteração foi salva, mas a interface não pôde ser sincronizada.',
        'error'
    );

    /*
     * Última proteção: se a sincronização falhar repetidamente,
     * recarregamos automaticamente. O usuário nunca precisa descobrir
     * manualmente que a tela ficou desatualizada.
     */
    if (options.reloadOnFailure !== false) {
        window.setTimeout(() => window.location.reload(), 350);
    }

    return false;
}

/* ========== EditModal callbacks ========== */
EditModal.onSaved = function(delivery) {
    return syncDeliveryAfterMutation(
        delivery,
        'Entrega atualizada.'
    );
};

/* ========== DistModal callbacks ========== */
window._DistModalReload = function(data) {
    updateDistributionProgressTone();

    return syncDeliveryAfterMutation(
        data,
        'Distribuição salva.'
    );
};

window._DistModalOnDelete = function(receptionId, data) {
    updateDistributionProgressTone();

    return syncDeliveryAfterMutation(
        resolveDeliveryId(receptionId, data),
        'Distribuição removida.',
        {
            attempts: 4,
            reloadOnFailure: true,
        }
    );
};

window._DistModalOnUpdate = function(receptionId, data) {
    updateDistributionProgressTone();

    return syncDeliveryAfterMutation(
        resolveDeliveryId(receptionId, data),
        'Distribuição atualizada.'
    );
};

/* ========== Phosphor Duotone + componentes incorporados ========== */
const PD_PHOSPHOR_MAP = {
    'triangle-alert': 'warning',
    'alert-triangle': 'warning',
    'circle-alert': 'warning-circle',
    'circle-check': 'check-circle',
    'circle-check-big': 'check-circle',
    'circle-x': 'x-circle',
    'package-check': 'package',
    'package-plus': 'package',
    'users-round': 'users-three',
    'file-chart-column': 'chart-bar',
    'clipboard-list': 'clipboard-text',
    'trash-2': 'trash',
    'pencil': 'pencil-simple',
    'search': 'magnifying-glass',
    'chevron-down': 'caret-down',
    'chevron-up': 'caret-up',
    'sheet': 'file-xls',
    'file-text': 'file-pdf',
    'locate-fixed': 'crosshair',
};

function makePhosphorIcon(name, extraClass = '') {
    const mapped = PD_PHOSPHOR_MAP[name] || name || 'circle';
    return `<i class="${extraClass ? esc(extraClass) + ' ' : ''}ph-duotone ph-${esc(mapped)}" aria-hidden="true"></i>`;
}

function replaceSvgWithPhosphor(selector, iconName, root = document) {
    root.querySelectorAll(selector).forEach(svg => {
        if (svg.tagName !== 'svg') return;

        const holder = document.createElement('span');
        holder.innerHTML = makePhosphorIcon(iconName);
        svg.replaceWith(holder.firstElementChild);
    });
}

function upgradeDuotoneIcons(root = document) {
    const lucideIcons = [];

    if (root.matches?.('i[data-lucide]')) {
        lucideIcons.push(root);
    }

    root.querySelectorAll?.('i[data-lucide]').forEach(icon => {
        lucideIcons.push(icon);
    });

    lucideIcons.forEach(icon => {
        const name = icon.getAttribute('data-lucide') || 'circle';
        const mapped = PD_PHOSPHOR_MAP[name] || name;
        const preserved = Array.from(icon.classList).filter(
            className => className !== 'lucide'
                && !className.startsWith('lucide-')
                && className !== 'ph-duotone'
                && !className.startsWith('ph-')
        );

        icon.removeAttribute('data-lucide');
        icon.className = [...preserved, 'ph-duotone', `ph-${mapped}`].join(' ');
    });

    replaceSvgWithPhosphor('.dm-title > svg', 'git-merge', root);
    replaceSvgWithPhosphor('.dm-add-btn > svg', 'plus-circle', root);
    replaceSvgWithPhosphor('#dm-save-btn > svg', 'check-circle', root);
    replaceSvgWithPhosphor('.dm-notice-icon > svg', 'warning-circle', root);
    replaceSvgWithPhosphor('.em-title > svg', 'pencil-simple', root);
    replaceSvgWithPhosphor('#em-save-btn > svg', 'floppy-disk', root);

    root.querySelectorAll?.('.dm-close-btn').forEach(button => {
        if (!button.querySelector('.ph-duotone')) {
            button.innerHTML = makePhosphorIcon('x');
        }
    });

    root.querySelectorAll?.('.em-close-btn').forEach(button => {
        if (!button.querySelector('.ph-duotone')) {
            button.innerHTML = makePhosphorIcon('x');
        }
    });

    root.querySelectorAll?.('.delivery-notes-close').forEach(button => {
        if (!button.querySelector('.ph-duotone')) {
            button.innerHTML = makePhosphorIcon('x');
        }
    });

    root.querySelectorAll?.('.dm-edit-btn').forEach(button => {
        button.innerHTML = makePhosphorIcon('pencil-simple');
    });

    root.querySelectorAll?.('.dm-del-btn').forEach(button => {
        button.innerHTML = makePhosphorIcon('trash');
    });

    root.querySelectorAll?.('.dm-mini-btn.save').forEach(button => {
        button.innerHTML = makePhosphorIcon('check');
    });

    root.querySelectorAll?.('.dm-mini-btn.cancel, .dm-rm-btn').forEach(button => {
        button.innerHTML = makePhosphorIcon('x');
    });

    root.querySelectorAll?.('.dm-action-disabled').forEach(element => {
        element.innerHTML = makePhosphorIcon('lock-simple');
    });

    root.querySelectorAll?.('.delivery-note-trigger').forEach(button => {
        if (!button.querySelector('.ph-duotone')) {
            button.insertAdjacentHTML(
                'afterbegin',
                makePhosphorIcon('note-pencil')
            );
        }
    });

    root.querySelectorAll?.('.mc-state-icon').forEach(element => {
        if (element.querySelector('.ph-duotone')) return;

        const card = element.closest('.mobile-card');
        const label = String(element.getAttribute('aria-label') || element.title || '').toLowerCase();

        let icon = 'x-circle';

        if (label.includes('acima') || label.includes('excede')) {
            icon = 'warning';
        } else if (card?.classList.contains('status-distributed')) {
            icon = 'check-circle';
        } else if (card?.classList.contains('status-approved')) {
            icon = 'plus-circle';
        } else if (card?.classList.contains('status-pending')) {
            icon = 'clock';
        } else if (card?.classList.contains('status-rejected')) {
            icon = 'x-circle';
        } else if (card?.classList.contains('status-cancelled')) {
            icon = 'minus-circle';
        }

        element.innerHTML = makePhosphorIcon(icon);
    });

    root.querySelectorAll?.('#desktop-tbody svg').forEach(svg => {
        const parent = svg.parentElement;

        if (parent && /faturado/i.test(parent.textContent || '')) {
            const holder = document.createElement('span');
            holder.innerHTML = makePhosphorIcon('lock-simple');
            svg.replaceWith(holder.firstElementChild);
        }
    });

    root.querySelectorAll?.('.dist-text, .mc-dist-text').forEach(element => {
        const value = String(element.textContent || '').trim();

        if ((value.startsWith('⚠') || value.startsWith('!')) && !element.querySelector('.ph-duotone')) {
            const clean = value.replace(/^[⚠!]\s*/, '');
            element.innerHTML = `${makePhosphorIcon('warning')}${esc(clean)}`;
        }
    });

    const overflow = document.getElementById('dm-warning-overflow');
    if (overflow && !overflow.querySelector('.ph-duotone')) {
        overflow.innerHTML =
            `${makePhosphorIcon('warning-circle')}<span>Total excede a quantidade disponível</span>`;
    }

    const done = document.getElementById('dm-done-badge');
    if (done && !done.querySelector('.ph-duotone')) {
        done.innerHTML =
            `${makePhosphorIcon('check-circle')}<span>100% distribuído</span>`;
    }
}


function updateDistributionProgressTone() {
    const wrap = document.querySelector('#dm-overlay .dm-progress-wrap');
    const existingBar = document.getElementById('dm-bar-existing');
    const newBar = document.getElementById('dm-bar-new');
    const overflow = document.getElementById('dm-warning-overflow');
    const done = document.getElementById('dm-done-badge');

    if (!wrap || !existingBar) return;

    const existingPct = Math.max(
        0,
        parseFloat(existingBar.style.width || '0') || 0
    );

    const newPct = Math.max(
        0,
        parseFloat(newBar?.style.width || '0') || 0
    );

    const totalPct = existingPct + newPct;

    let state = 'empty';

    if (overflow?.classList.contains('visible') || totalPct > 100.05) {
        state = 'over';
    } else if (done?.classList.contains('visible') || totalPct >= 99.95) {
        state = 'complete';
    } else if (existingPct >= 75) {
        state = 'high';
    } else if (existingPct > 0) {
        state = 'partial';
    }

    wrap.dataset.progressState = state;
}

function installComponentUxOverrides() {
    if (window.__pdComponentUxInstalled) return;
    window.__pdComponentUxInstalled = true;

    /*
     * O componente de distribuição possui autofocus próprio.
     * No mobile deixamos o corpo inert durante a janela desse autofocus,
     * portanto o teclado não é aberto ao mostrar o sheet.
     * No desktop o comportamento original permanece.
     */
    if (window.DistModal?.open) {
        const originalOpen = window.DistModal.open.bind(window.DistModal);

        window.DistModal.open = function(config) {
            const mobile = window.matchMedia('(max-width: 767px)').matches;
            const body = document.querySelector('#dm-overlay .dm-body');

            if (mobile) {
                body?.setAttribute('inert', '');
            }

            const result = originalOpen(config);

            upgradeDuotoneIcons(document);
            window.setTimeout(updateDistributionProgressTone, 0);
            window.setTimeout(updateDistributionProgressTone, 80);

            if (mobile) {
                window.setTimeout(() => {
                    body?.removeAttribute('inert');

                    const active = document.activeElement;
                    const overlay = document.getElementById('dm-overlay');

                    if (active && overlay?.contains(active)) {
                        active.blur?.();
                    }
                }, 145);
            }

            return result;
        };
    }

    /*
     * O componente legado ainda possui dois confirm() nativos.
     * Interceptamos apenas esses fluxos e usamos o confirm do SGC.
     */
    if (window.DistModal?.editExisting && window.DistModal?.saveExistingEdit) {
        const originalEditExisting = window.DistModal.editExisting.bind(window.DistModal);
        const originalSaveExistingEdit = window.DistModal.saveExistingEdit.bind(window.DistModal);

        window.DistModal.editExisting = function(distributionId) {
            const row = document.getElementById('dmex-' + distributionId);

            if (row) {
                row.dataset.sgcWasInReceipt = row.querySelector('.dm-status-badge.receipt')
                    ? '1'
                    : '0';
            }

            const result = originalEditExisting(distributionId);
            upgradeDuotoneIcons(document);
            return result;
        };

        window.DistModal.saveExistingEdit = async function(distributionId) {
            const row = document.getElementById('dmex-' + distributionId);
            const needsReceiptConfirm = row?.dataset.sgcWasInReceipt === '1';

            if (needsReceiptConfirm) {
                const confirmed = await customConfirm(
                    'Esta distribuição já está em um comprovante. Ao editar, o comprovante será marcado como obsoleto e precisará ser conferido.',
                    {
                        title: 'Editar distribuição vinculada',
                        confirmLabel: 'Continuar edição',
                    }
                );

                if (!confirmed) {
                    window.DistModal.cancelExistingEdit();
                    return;
                }
            }

            const nativeConfirm = window.confirm;

            try {
                // O confirm legado ocorre de forma síncrona antes do primeiro await.
                window.confirm = () => true;
                const result = originalSaveExistingEdit(distributionId);
                window.confirm = nativeConfirm;
                return await result;
            } finally {
                window.confirm = nativeConfirm;
                upgradeDuotoneIcons(document);
            }
        };
    }

    if (window.DistModal?.deleteExisting && window.DistModal?.performDelete) {
        window.DistModal.deleteExisting = async function(distributionId) {
            const row = document.getElementById('dmex-' + distributionId);
            const inReceipt = !!row?.querySelector('.dm-status-badge.receipt');

            if (inReceipt) {
                const receiptLabel =
                    row.querySelector('.dm-status-badge.receipt')?.getAttribute('title')
                    || 'um comprovante';

                const confirmed = await customConfirm(
                    `Esta distribuição está vinculada a ${receiptLabel}. A exclusão forçada removerá o vínculo e recalculará os totais. Esta ação exige confirmação adicional.`,
                    {
                        title: 'Exclusão forçada',
                        confirmLabel: 'Excluir distribuição',
                        challenge: true,
                        danger: true,
                    }
                );

                if (!confirmed) return;

                /*
                 * O backend do componente já exige estas duas flags para
                 * confirmar o impacto. O desafio acima é uma camada extra
                 * de UX; a validação do servidor continua intacta.
                 */
                const result = await window.DistModal.performDelete(
                    distributionId,
                    {
                        impact_confirmed: true,
                        math_answer: 2,
                    }
                );

                updateDistributionProgressTone();
                upgradeDuotoneIcons(document);

                return result;
            }

            const confirmed = await customConfirm(
                'Remover esta distribuição? Os totais da entrega serão atualizados imediatamente.',
                {
                    title: 'Remover distribuição',
                    confirmLabel: 'Remover',
                    danger: true,
                }
            );

            if (!confirmed) return;

            const result = await window.DistModal.performDelete(
                distributionId,
                {}
            );

            updateDistributionProgressTone();
            upgradeDuotoneIcons(document);

            return result;
        };
    }

    // Contexto do relatório já é o projeto atual.
    const reportSubtitle = document.querySelector(
        '#delivery-report-modal .dr-head p'
    );

    if (reportSubtitle) {
        reportSubtitle.textContent = @json($project->title) + ' · filtros e formato de saída';
    }
}

/* Atualiza também ícones criados posteriormente pelos componentes. */
const pdIconObserver = new MutationObserver(mutations => {
    const roots = new Set();

    mutations.forEach(mutation => {
        mutation.addedNodes.forEach(node => {
            if (node.nodeType === Node.ELEMENT_NODE) {
                roots.add(node);
            }
        });
    });

    roots.forEach(root => upgradeDuotoneIcons(root));
});

/* ========== Inicialização ========== */

/*
 * A preparação visual é executada imediatamente neste ponto.
 * Como o script fica depois de toda a marcação da página, não precisamos
 * esperar DOMContentLoaded e evitamos a sensação de uma segunda renderização.
 */
installComponentUxOverrides();
enhanceDeliveryActions();
upgradeDuotoneIcons(document);
applyFilters();
refreshIntegrityCenter().catch(error => console.warn('Central de inconsistências:', error));

/* Mantém somente os ajustes visuais ao alternar entre tabela e cards. */
let pdResizeTimer = null;
let pdLastCardLayout = window.matchMedia('(max-width: 1099px)').matches;

window.addEventListener('resize', () => {
    window.clearTimeout(pdResizeTimer);

    pdResizeTimer = window.setTimeout(() => {
        const nextCardLayout = window.matchMedia('(max-width: 1099px)').matches;

        if (nextCardLayout !== pdLastCardLayout) {
            pdLastCardLayout = nextCardLayout;
            refreshOperationalUi();
            upgradeDuotoneIcons(document);
        }
    }, 90);
}, { passive: true });

if (document.body) {
    pdIconObserver.observe(document.body, {
        childList: true,
        subtree: true,
    });
}

/*
 * O componente de distribuição recalcula as barras alterando style/class,
 * não adicionando nós. Observamos somente a área de progresso para atualizar
 * a cor do "Já distribuído" sem polling.
 */
const pdProgressRoot = document.querySelector('#dm-overlay .dm-progress-wrap');

if (pdProgressRoot) {
    const pdProgressObserver = new MutationObserver(() => {
        updateDistributionProgressTone();
        upgradeDuotoneIcons(pdProgressRoot);
    });

    pdProgressObserver.observe(pdProgressRoot, {
        attributes: true,
        childList: true,
        subtree: true,
        attributeFilter: ['class', 'style'],
    });

    updateDistributionProgressTone();
}

const params = new URLSearchParams(window.location.search);
const requestedDeliveryId = Number(params.get('open_delivery') || 0);
const requestedDistributionId = Number(params.get('edit_distribution') || 0);

if (requestedDeliveryId) {
    let attempts = 0;

    const openRequestedDistribution = () => {
        attempts++;

        const button = document.querySelector(
            `.btn-distribute[data-id="${requestedDeliveryId}"]`
        );

        if (!button && attempts < 8) {
            window.setTimeout(openRequestedDistribution, 250);
            return;
        }

        openIntegrityDistribution(
            requestedDeliveryId,
            requestedDistributionId,
            requestedDistributionId > 0
        );

        params.delete('open_delivery');
        params.delete('edit_distribution');

        const query = params.toString();

        window.history.replaceState(
            null,
            '',
            `${window.location.pathname}${query ? `?${query}` : ''}`
        );
    };

    openRequestedDistribution();
}

</script>
@endsection
