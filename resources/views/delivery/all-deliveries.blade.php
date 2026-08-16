@extends('layouts.bento')

@section('title', 'Todas as Entregas')
@section('page-title', 'Gestão de Entregas e Estoque')
@section('user-role', 'Registrador')

{{-- Componente unificado de distribuição --}}
<x-delivery.dist-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
    :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name])->values()->all()"
/>
<x-delivery.notes-modal />
@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery',
        'deliveries',
        $currentTenant->slug ?? request()->route('tenant'),
    );
@endphp


@section('content')
<style>
    .delivery-admin-page {
        --da-green: #168a4d;
        --da-green-soft: #eaf8ef;

        --da-blue: #2563eb;
        --da-blue-soft: #eef4ff;

        --da-violet: #7c3aed;
        --da-violet-soft: #f4f0ff;

        --da-amber: #c87408;
        --da-amber-soft: #fff7e8;

        --da-red: #cf3f3f;
        --da-red-soft: #fff0f0;

        --da-slate: #64748b;
        --da-slate-soft: #f1f5f9;

        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
    }

    .delivery-admin-page *,
    .delivery-admin-page *::before,
    .delivery-admin-page *::after {
        box-sizing: border-box;
    }

    .da-section {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 15px;
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }

    .da-section-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        min-height: 64px;
        padding: .68rem .76rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .da-section-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .da-section-icon.overview {
        background: var(--da-blue-soft);
        color: var(--da-blue);
    }

    .da-section-icon.stock {
        background: var(--da-green-soft);
        color: var(--da-green);
    }

    .da-section-icon.filters {
        background: var(--da-violet-soft);
        color: var(--da-violet);
    }

    .da-section-icon.list {
        background: var(--da-amber-soft);
        color: var(--da-amber);
    }

    .da-section-icon > i {
        display: block;
        font-size: 1.08rem;
        line-height: 1;
    }

    .da-section-copy {
        min-width: 0;
    }

    .da-section-copy h2,
    .da-section-copy p {
        margin: 0;
    }

    .da-section-copy h2 {
        color: var(--color-text);
        font-size: .95rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .da-section-copy p {
        margin-top: .08rem;
        color: var(--color-text-muted);
        font-size: .75rem;
        line-height: 1.42;
    }

    .da-count {
        display: grid;
        min-height: 30px;
        grid-template-columns: auto auto;
        gap: .28rem;
        align-items: center;
        padding: .28rem .44rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .7rem;
        font-weight: 770;
        white-space: nowrap;
    }

    .da-count > i {
        display: block;
        font-size: .82rem;
        line-height: 1;
    }

    /* =========================================================
       VISÃO GERAL
       ========================================================= */

    .da-overview {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(280px, .88fr)
            minmax(0, 1.12fr);
    }

    .da-overview-main {
        display: grid;
        min-height: 195px;
        align-content: center;
        padding: 1rem;
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(37, 99, 235, .10),
                transparent 16rem
            ),
            linear-gradient(
                135deg,
                #fff,
                var(--da-blue-soft)
            );
    }

    .da-overview-label {
        display: grid;
        width: max-content;
        grid-template-columns: auto auto;
        gap: .34rem;
        align-items: center;
        color: var(--da-blue);
        font-size: .74rem;
        font-weight: 790;
    }

    .da-overview-label > i {
        display: block;
        font-size: .94rem;
        line-height: 1;
    }

    .da-overview-main > strong {
        display: block;
        margin-top: .34rem;
        color: var(--color-text);
        font-size: clamp(1.9rem, 4vw, 2.5rem);
        font-weight: 875;
        letter-spacing: -.045em;
        line-height: 1;
    }

    .da-overview-main > p {
        max-width: 390px;
        margin: .42rem 0 0;
        color: var(--color-text-secondary);
        font-size: .78rem;
        line-height: 1.5;
    }

    .da-stat-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: .5rem;
        align-content: center;
        padding: .78rem;
        background: #fff;
    }

    .da-stat {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .5rem;
        align-items: center;
        min-height: 74px;
        padding: .58rem;
        border-radius: 11px;
        background: var(--color-surface-soft);
    }

    .da-stat-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 10px;
    }

    .da-stat.pending .da-stat-icon {
        background: var(--da-amber-soft);
        color: var(--da-amber);
    }

    .da-stat.approved .da-stat-icon {
        background: var(--da-green-soft);
        color: var(--da-green);
    }

    .da-stat.rejected .da-stat-icon {
        background: var(--da-red-soft);
        color: var(--da-red);
    }

    .da-stat-icon > i {
        display: block;
        font-size: .95rem;
        line-height: 1;
    }

    .da-stat-copy {
        min-width: 0;
    }

    .da-stat-copy span,
    .da-stat-copy strong {
        display: block;
    }

    .da-stat-copy span {
        color: var(--color-text-muted);
        font-size: .69rem;
        font-weight: 680;
    }

    .da-stat-copy strong {
        margin-top: .04rem;
        color: var(--color-text);
        font-size: .94rem;
        font-weight: 840;
    }

    .da-stat.pending .da-stat-copy strong {
        color: var(--da-amber);
    }

    .da-stat.approved .da-stat-copy strong {
        color: var(--da-green);
    }

    .da-stat.rejected .da-stat-copy strong {
        color: var(--da-red);
    }

    /* =========================================================
       ESTOQUE
       ========================================================= */

    .da-stock-list {
        display: grid;
        grid-template-columns:
            repeat(auto-fit, minmax(220px, 1fr));
        gap: .5rem;
        padding: .7rem;
    }

    .da-stock-item {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        min-height: 66px;
        padding: .55rem .6rem;
        border: 1px solid var(--color-border);
        border-radius: 11px;
        background: #fff;
    }

    .da-stock-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 10px;
        background: var(--da-green-soft);
        color: var(--da-green);
    }

    .da-stock-icon > i {
        display: block;
        font-size: .94rem;
        line-height: 1;
    }

    .da-stock-copy {
        min-width: 0;
    }

    .da-stock-copy strong,
    .da-stock-copy span {
        display: block;
    }

    .da-stock-copy strong {
        overflow: hidden;
        color: var(--color-text);
        font-size: .78rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .da-stock-copy span {
        margin-top: .04rem;
        color: var(--color-text-muted);
        font-size: .68rem;
    }

    .da-stock-value {
        color: var(--da-green);
        font-size: .84rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .da-stock-value small {
        font-size: .68rem;
        font-weight: 700;
    }

    /* =========================================================
       FILTROS
       ========================================================= */

    .da-status-tabs {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .32rem;
        padding: .55rem .65rem;
        overflow-x: auto;
        border-bottom: 1px solid var(--color-border);
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .da-status-tabs::-webkit-scrollbar {
        display: none;
    }

    .da-status-tab {
        --tab-tone: var(--da-slate);
        --tab-soft: var(--da-slate-soft);

        display: grid;
        min-width: max-content;
        min-height: 40px;
        grid-template-columns: auto auto;
        gap: .34rem;
        align-items: center;
        padding: .42rem .58rem;
        border: 1px solid transparent;
        border-radius: 10px;
        background: transparent;
        color: var(--color-text-secondary);
        font-size: .73rem;
        font-weight: 760;
        text-decoration: none;
        white-space: nowrap;
    }

    .da-status-tab.pending {
        --tab-tone: var(--da-amber);
        --tab-soft: var(--da-amber-soft);
    }

    .da-status-tab.approved {
        --tab-tone: var(--da-green);
        --tab-soft: var(--da-green-soft);
    }

    .da-status-tab.rejected {
        --tab-tone: var(--da-red);
        --tab-soft: var(--da-red-soft);
    }

    .da-status-tab.cancelled {
        --tab-tone: var(--da-slate);
        --tab-soft: var(--da-slate-soft);
    }

    .da-status-tab > i {
        display: block;
        color: var(--tab-tone);
        font-size: .92rem;
        line-height: 1;
    }

    .da-status-tab:hover,
    .da-status-tab:focus-visible,
    .da-status-tab.active {
        border-color:
            color-mix(
                in srgb,
                var(--tab-tone) 16%,
                var(--color-border)
            );
        background: var(--tab-soft);
        color: var(--tab-tone);
        outline: none;
    }

    .da-filter-form {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(210px, 1.25fr)
            minmax(180px, .95fr)
            minmax(145px, .72fr)
            minmax(145px, .72fr)
            auto;
        gap: .55rem;
        align-items: end;
        padding: .68rem .72rem .75rem;
    }

    .da-field {
        min-width: 0;
    }

    .da-field label {
        display: block;
        margin-bottom: .28rem;
        color: var(--color-text-secondary);
        font-size: .7rem;
        font-weight: 740;
    }

    .da-field input,
    .da-field select {
        width: 100%;
        min-height: 43px;
        padding: .52rem .64rem;
        border: 1px solid var(--color-border-strong);
        border-radius: 10px;
        background: #fff;
        color: var(--color-text);
        font: inherit;
        font-size: .76rem;
    }

    .da-filter-actions {
        display: grid;
        grid-auto-flow: column;
        gap: .34rem;
    }

    .da-button,
    .report-btn {
        display: grid;
        min-height: 43px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .48rem .64rem;
        border: 1px solid var(--color-border-strong);
        border-radius: 10px;
        background: #fff;
        color: var(--color-text-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .73rem;
        font-weight: 780;
        text-decoration: none;
        white-space: nowrap;
    }

    .da-button > i,
    .report-btn > i {
        display: block;
        font-size: .88rem;
        line-height: 1;
    }

    .da-button.primary,
    .report-btn.primary {
        border-color: var(--color-primary-dark);
        background:
            linear-gradient(
                135deg,
                var(--color-primary),
                var(--color-primary-dark)
            );
        color: #fff;
        box-shadow:
            0 7px 16px rgba(22, 163, 74, .13);
    }

    .da-button:hover,
    .da-button:focus-visible,
    .report-btn:hover,
    .report-btn:focus-visible {
        outline: none;
        transform: translateY(-1px);
    }

    .da-filter-note {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .48rem;
        align-items: center;
        margin: 0 .72rem .68rem;
        padding: .5rem .56rem;
        border-radius: 10px;
        background: var(--da-violet-soft);
        color: var(--da-violet);
        font-size: .71rem;
        font-weight: 720;
    }

    .da-filter-note > i {
        display: block;
        font-size: .88rem;
        line-height: 1;
    }

    .da-filter-note a {
        color: var(--da-violet);
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
    }

    .da-report-action {
        display: grid;
        justify-content: end;
    }

    /* =========================================================
       LISTA DE ENTREGAS
       ========================================================= */

    .da-delivery-list {
        display: grid;
        min-width: 0;
        padding: .3rem .72rem .72rem;
    }

    .da-delivery {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .64rem;
        padding: .78rem .04rem;
    }

    .da-delivery + .da-delivery {
        border-top: 1px solid var(--color-border);
    }

    .da-date {
        display: grid;
        width: 52px;
        height: 52px;
        place-items: center;
        align-content: center;
        border-radius: 13px;
        background: var(--da-blue-soft);
        color: var(--da-blue);
        text-align: center;
    }

    .da-date strong,
    .da-date span {
        display: block;
    }

    .da-date strong {
        font-size: .88rem;
        font-weight: 850;
        line-height: 1;
    }

    .da-date span {
        margin-top: .14rem;
        font-size: .64rem;
        font-weight: 790;
        line-height: 1;
        text-transform: uppercase;
    }

    .da-delivery-content {
        min-width: 0;
    }

    .da-delivery-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: start;
    }

    .da-delivery-title {
        min-width: 0;
    }

    .da-title-line {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: minmax(0, auto) auto;
        gap: .34rem;
        align-items: center;
    }

    .da-product-name {
        min-width: 0;
        color: var(--color-text);
        font-size: .9rem;
        font-weight: 840;
        letter-spacing: -.02em;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .badge-status,
    .da-billing {
        display: grid;
        width: max-content;
        min-height: 24px;
        place-items: center;
        padding: .19rem .36rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .64rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .badge-status.pending {
        background: var(--da-amber-soft);
        color: #92400e;
    }

    .badge-status.approved {
        background: var(--da-green-soft);
        color: var(--da-green);
    }

    .badge-status.rejected {
        background: var(--da-red-soft);
        color: #991b1b;
    }

    .badge-status.cancelled {
        background: var(--da-slate-soft);
        color: var(--da-slate);
    }

    .da-delivery-meta {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .46rem;
        margin-top: .14rem;
        color: var(--color-text-muted);
        font-size: .7rem;
    }

    .da-delivery-meta span {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, auto);
        gap: .22rem;
        align-items: center;
    }

    .da-delivery-meta i {
        display: block;
        font-size: .77rem;
        line-height: 1;
    }

    .da-delivery-meta .associate i {
        color: var(--da-violet);
    }

    .da-delivery-meta .project i {
        color: var(--da-blue);
    }

    .da-delivery-meta span span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .da-gross {
        min-width: 116px;
        text-align: right;
    }

    .da-gross span,
    .da-gross strong {
        display: block;
    }

    .da-gross span {
        color: var(--color-text-muted);
        font-size: .68rem;
        font-weight: 680;
    }

    .da-gross strong {
        margin-top: .04rem;
        color: var(--da-green);
        font-size: .88rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .da-details {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        gap: .48rem;
        margin-top: .55rem;
        padding: .54rem .6rem;
        border-radius: 11px;
        background: var(--color-surface-soft);
    }

    .da-detail {
        min-width: 0;
    }

    .da-detail span,
    .da-detail strong {
        display: block;
    }

    .da-detail > span {
        color: var(--color-text-muted);
        font-size: .67rem;
        font-weight: 680;
    }

    .da-detail > strong {
        margin-top: .05rem;
        color: var(--color-text);
        font-size: .75rem;
        font-weight: 820;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .da-billing {
        margin-top: .05rem;
    }

    .da-billing.paid {
        background: var(--da-green-soft);
        color: var(--da-green);
    }

    .da-billing.billed {
        background: var(--da-blue-soft);
        color: var(--da-blue);
    }

    .da-billing.none {
        min-height: 22px;
        padding: 0;
        background: transparent;
        color: var(--color-text-muted);
    }

    .da-actions-row {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .48rem;
        align-items: center;
        margin-top: .48rem;
    }

    .da-note-slot {
        min-width: 0;
    }

    .delivery-note-trigger {
        display: grid;
        width: max-content;
        max-width: 100%;
        min-height: 34px;
        grid-template-columns: auto auto;
        gap: .3rem;
        align-items: center;
        padding: .36rem .5rem;
        border: 1px solid var(--color-border);
        border-radius: 9px;
        background: #fff;
        color: var(--color-text-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 760;
    }

    .delivery-note-trigger::before {
        content: "✎";
        color: var(--da-violet);
        font-size: .8rem;
    }

    .action-btns {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .3rem;
        justify-content: end;
    }

    .btn-xs {
        display: grid;
        min-height: 34px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .26rem;
        align-items: center;
        justify-content: center;
        padding: .34rem .48rem;
        border: 1px solid transparent;
        border-radius: 9px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .69rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .btn-xs:disabled {
        cursor: not-allowed;
        opacity: .5;
    }

    .btn-xs i,
    .btn-xs svg {
        width: 12px !important;
        height: 12px !important;
    }

    .btn-approve {
        background: var(--da-green-soft);
        color: var(--da-green);
    }

    .btn-reject,
    .btn-delete-approved {
        background: var(--da-red-soft);
        color: var(--da-red);
    }

    .btn-distribute {
        background: var(--da-violet-soft);
        color: var(--da-violet);
    }

    .btn-approve:hover:not(:disabled) {
        background: var(--da-green);
        color: #fff;
    }

    .btn-reject:hover:not(:disabled),
    .btn-delete-approved:hover:not(:disabled) {
        background: var(--da-red);
        color: #fff;
    }

    .btn-distribute:hover:not(:disabled) {
        background: var(--da-violet);
        color: #fff;
    }

    .da-blocked {
        background: var(--da-red-soft);
        color: var(--da-red);
        opacity: .58;
    }

    /* =========================================================
       EMPTY E PAGINAÇÃO
       ========================================================= */

    .da-empty {
        display: grid;
        min-height: 220px;
        place-items: center;
        padding: 1.35rem;
        text-align: center;
    }

    .da-empty-content {
        width: min(100%, 390px);
    }

    .da-empty-icon {
        display: grid;
        width: 58px;
        height: 58px;
        place-items: center;
        margin: 0 auto .62rem;
        border-radius: 16px;
        background: var(--da-blue-soft);
        color: var(--da-blue);
    }

    .da-empty-icon > i {
        display: block;
        font-size: 1.4rem;
        line-height: 1;
    }

    .da-empty strong,
    .da-empty span {
        display: block;
    }

    .da-empty strong {
        color: var(--color-text);
        font-size: .86rem;
        font-weight: 820;
    }

    .da-empty span {
        margin-top: .2rem;
        color: var(--color-text-muted);
        font-size: .75rem;
        line-height: 1.45;
    }

    .pagination-wrap {
        display: grid;
        place-items: center;
        padding: .7rem;
        border-top: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface),
                var(--color-surface-soft)
            );
    }

    .pagination-wrap nav {
        width: 100%;
        max-width: 760px;
    }

    .pagination-wrap nav a,
    .pagination-wrap nav [aria-current="page"] > span,
    .pagination-wrap nav [aria-disabled="true"] > span {
        min-width: 38px;
        min-height: 38px;
        border: 1px solid var(--color-border) !important;
        border-radius: 10px !important;
        background: #fff !important;
        color: var(--color-text-secondary) !important;
        font-size: .75rem !important;
        font-weight: 780 !important;
        box-shadow: none !important;
    }

    .pagination-wrap nav a:hover,
    .pagination-wrap nav a:focus-visible {
        border-color: rgba(34, 197, 94, .3) !important;
        background: var(--color-primary-50) !important;
        color: var(--color-primary-deep) !important;
        outline: none;
    }

    .pagination-wrap nav [aria-current="page"] > span {
        border-color: var(--color-primary-dark) !important;
        background:
            linear-gradient(
                135deg,
                var(--color-primary),
                var(--color-primary-dark)
            ) !important;
        color: #fff !important;
        box-shadow:
            0 5px 13px rgba(22, 163, 74, .14) !important;
    }

    .pagination-wrap nav [aria-disabled="true"] > span {
        background: var(--color-surface-muted) !important;
        color: var(--color-text-muted) !important;
        opacity: .62;
    }

    .pagination-wrap nav svg {
        width: 16px !important;
        height: 16px !important;
    }

    /* Compatibilidade com componentes/modais existentes */
    .modal-overlay {
        position: fixed;
        z-index: 9000;
        inset: 0;
        display: none;
        place-items: center;
        background: rgba(0, 0, 0, .48);
        backdrop-filter: blur(2px);
    }

    .modal-overlay.active {
        display: grid;
    }

    .receipt-modal {
        width: min(92vw, 440px);
        padding: 1rem;
        border-radius: 15px;
        background: var(--color-surface);
        box-shadow:
            0 24px 70px rgba(0, 0, 0, .24);
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 980px) {
        .da-overview {
            grid-template-columns: 1fr;
        }

        .da-stat-grid {
            border-top: 1px solid var(--color-border);
        }

        .da-filter-form {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .da-filter-actions {
            grid-column: 1 / -1;
            justify-self: start;
        }
    }

    @media (max-width: 700px) {
        .da-stat-grid {
            grid-template-columns: 1fr;
            gap: 0;
        }

        .da-stat {
            border-radius: 0;
            background: #fff;
        }

        .da-stat + .da-stat {
            border-top: 1px solid var(--color-border);
        }

        .da-details {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 620px) {
        .delivery-admin-page {
            gap: .7rem;
        }

        .da-section-head {
            padding: .63rem;
        }

        .da-section-copy p {
            display: none;
        }

        .da-overview-main {
            min-height: 175px;
            padding: .85rem;
        }

        .da-filter-form {
            grid-template-columns: 1fr;
            padding: .62rem;
        }

        .da-filter-actions {
            grid-column: auto;
            width: 100%;
            grid-template-columns: 1fr 1fr;
        }

        .da-button {
            width: 100%;
        }

        .da-filter-note {
            margin-right: .62rem;
            margin-left: .62rem;
        }

        .da-delivery-list {
            padding-right: .58rem;
            padding-left: .58rem;
        }

        .da-delivery-head {
            grid-template-columns: 1fr;
        }

        .da-gross {
            min-width: 0;
            text-align: left;
        }

        .da-title-line {
            grid-template-columns: 1fr;
            width: 100%;
            gap: .2rem;
        }

        .badge-status {
            justify-self: start;
        }

        .da-delivery-meta {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            width: 100%;
            gap: .12rem;
        }

        .da-actions-row {
            grid-template-columns: 1fr;
        }

        .action-btns {
            justify-content: start;
            overflow-x: auto;
            padding-bottom: .04rem;
        }
    }

    @media (max-width: 430px) {
        .da-count {
            display: none;
        }

        .da-delivery {
            grid-template-columns: 1fr;
        }

        .da-date {
            width: max-content;
            height: auto;
            min-height: 36px;
            grid-template-columns: auto auto;
            gap: .24rem;
            justify-content: start;
            padding: .37rem .5rem;
        }

        .da-date span {
            margin-top: 0;
        }

        .da-details {
            grid-template-columns: 1fr 1fr;
            gap: .36rem;
        }

        .da-stock-list {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 360px) {
        .da-details {
            grid-template-columns: 1fr;
        }

        .da-filter-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

@php
    $hasActiveFilters = filled($search)
        || filled($statusFilter)
        || filled($projectFilter)
        || filled($dateFrom)
        || filled($dateTo);

    $statusTabs = [
        '' => [
            'label' => 'Todas',
            'icon' => 'ph-list-bullets',
            'class' => '',
        ],
        'pending' => [
            'label' => 'Pendentes',
            'icon' => 'ph-clock-countdown',
            'class' => 'pending',
        ],
        'approved' => [
            'label' => 'Aprovadas',
            'icon' => 'ph-check-circle',
            'class' => 'approved',
        ],
        'rejected' => [
            'label' => 'Rejeitadas',
            'icon' => 'ph-x-circle',
            'class' => 'rejected',
        ],
        'cancelled' => [
            'label' => 'Canceladas',
            'icon' => 'ph-prohibit',
            'class' => 'cancelled',
        ],
    ];

    $statusUrl = static function (string $status) use ($currentTenant): string {
        $parameters = array_merge(
            ['tenant' => $currentTenant->slug],
            request()->except('status', 'page')
        );

        if ($status !== '') {
            $parameters['status'] = $status;
        }

        return route('delivery.all-deliveries', $parameters);
    };
@endphp

<main class="delivery-admin-page">

    {{-- =========================================================
         RESUMO
         ========================================================= --}}
    <section class="da-section">
        <header class="da-section-head">
            <span class="da-section-icon overview" aria-hidden="true">
                <i class="ph-duotone ph-package"></i>
            </span>

            <div class="da-section-copy">
                <h2>Visão geral das entregas</h2>
                <p>Acompanhe o volume de registros e o que ainda exige ação.</p>
            </div>

            <span class="da-count">
                <i class="ph ph-list-bullets"></i>
                {{ $stats['total'] }}
                {{ $stats['total'] === 1 ? 'entrega' : 'entregas' }}
            </span>
        </header>

        <div class="da-overview">
            <div class="da-overview-main">
                <span class="da-overview-label">
                    <i class="ph-duotone ph-archive-box"></i>
                    Total registrado
                </span>

                <strong>{{ $stats['total'] }}</strong>

                <p>
                    Entregas cadastradas para acompanhamento,
                    aprovação, distribuição e faturamento.
                </p>
            </div>

            <div class="da-stat-grid">
                <div class="da-stat pending">
                    <span class="da-stat-icon" aria-hidden="true">
                        <i class="ph-duotone ph-clock-countdown"></i>
                    </span>

                    <span class="da-stat-copy">
                        <span>Pendentes</span>
                        <strong>{{ $stats['pending'] }}</strong>
                    </span>
                </div>

                <div class="da-stat approved">
                    <span class="da-stat-icon" aria-hidden="true">
                        <i class="ph-duotone ph-check-circle"></i>
                    </span>

                    <span class="da-stat-copy">
                        <span>Aprovadas</span>
                        <strong>{{ $stats['approved'] }}</strong>
                    </span>
                </div>

                <div class="da-stat rejected">
                    <span class="da-stat-icon" aria-hidden="true">
                        <i class="ph-duotone ph-x-circle"></i>
                    </span>

                    <span class="da-stat-copy">
                        <span>Rejeitadas</span>
                        <strong>{{ $stats['rejected'] }}</strong>
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- =========================================================
         ESTOQUE
         ========================================================= --}}
    @if($stockSummary->isNotEmpty())
        <section class="da-section">
            <header class="da-section-head">
                <span class="da-section-icon stock" aria-hidden="true">
                    <i class="ph-duotone ph-warehouse"></i>
                </span>

                <div class="da-section-copy">
                    <h2>Estoque das entregas aprovadas</h2>
                    <p>Quantidade aprovada agrupada por produto.</p>
                </div>

                <span class="da-count">
                    <i class="ph ph-cube"></i>
                    {{ $stockSummary->count() }}
                    {{ $stockSummary->count() === 1 ? 'produto' : 'produtos' }}
                </span>
            </header>

            <div class="da-stock-list">
                @foreach($stockSummary as $item)
                    <article class="da-stock-item">
                        <span class="da-stock-icon" aria-hidden="true">
                            <i class="ph-duotone ph-cube"></i>
                        </span>

                        <div class="da-stock-copy">
                            <strong title="{{ $item['product_name'] }}">
                                {{ $item['product_name'] }}
                            </strong>

                            <span>
                                {{ $item['total_deliveries'] }}
                                {{ $item['total_deliveries'] === 1
                                    ? 'entrega aprovada'
                                    : 'entregas aprovadas' }}
                            </span>
                        </div>

                        <strong class="da-stock-value">
                            {{ number_format(
                                $item['total_quantity'],
                                1,
                                ',',
                                '.'
                            ) }}
                            <small>kg</small>
                        </strong>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- =========================================================
         FILTROS / RELATÓRIOS
         ========================================================= --}}
    <section class="da-section">
        <header class="da-section-head">
            <span class="da-section-icon filters" aria-hidden="true">
                <i class="ph-duotone ph-funnel"></i>
            </span>

            <div class="da-section-copy">
                <h2>Encontrar entregas</h2>
                <p>Busque por associado, produto, projeto, status ou período.</p>
            </div>

            <div class="da-report-action">
                <button
                    type="button"
                    class="report-btn primary"
                    onclick="DeliveryReports.open()"
                >
                    <i class="ph ph-file-text"></i>
                    Gerar relatório
                </button>
            </div>
        </header>

        <nav
            class="da-status-tabs"
            aria-label="Filtrar entregas por status"
        >
            @foreach($statusTabs as $value => $tab)
                <a
                    class="da-status-tab {{ $tab['class'] }} {{ ($statusFilter ?? '') === $value ? 'active' : '' }}"
                    href="{{ $statusUrl($value) }}"
                    @if(($statusFilter ?? '') === $value)
                        aria-current="page"
                    @endif
                >
                    <i class="ph-duotone {{ $tab['icon'] }}"></i>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>

        <form
            method="GET"
            action="{{ route(
                'delivery.all-deliveries',
                ['tenant' => $currentTenant->slug]
            ) }}"
            class="da-filter-form"
        >
            @if($statusFilter)
                <input
                    type="hidden"
                    name="status"
                    value="{{ $statusFilter }}"
                >
            @endif

            <div class="da-field">
                <label for="da-search">Buscar</label>

                <input
                    id="da-search"
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Produto ou associado..."
                >
            </div>

            <div class="da-field">
                <label for="da-project">Projeto</label>

                <select id="da-project" name="project_id">
                    <option value="">Todos os projetos</option>

                    @foreach($projects as $id => $title)
                        <option
                            value="{{ $id }}"
                            @selected($projectFilter == $id)
                        >
                            {{ \Illuminate\Support\Str::limit(
                                $title,
                                42
                            ) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="da-field">
                <label for="da-date-from">De</label>

                <input
                    id="da-date-from"
                    type="date"
                    name="date_from"
                    value="{{ $dateFrom }}"
                >
            </div>

            <div class="da-field">
                <label for="da-date-to">Até</label>

                <input
                    id="da-date-to"
                    type="date"
                    name="date_to"
                    value="{{ $dateTo }}"
                >
            </div>

            <div class="da-filter-actions">
                <button
                    type="submit"
                    class="da-button primary"
                >
                    <i class="ph ph-funnel"></i>
                    Filtrar
                </button>

                @if($hasActiveFilters)
                    <a
                        class="da-button"
                        href="{{ route(
                            'delivery.all-deliveries',
                            ['tenant' => $currentTenant->slug]
                        ) }}"
                    >
                        <i class="ph ph-x"></i>
                        Limpar
                    </a>
                @endif
            </div>
        </form>

        @if($hasActiveFilters)
            <div class="da-filter-note">
                <i class="ph-duotone ph-info"></i>

                <span>
                    A lista abaixo está limitada aos filtros selecionados.
                </span>

                <a
                    href="{{ route(
                        'delivery.all-deliveries',
                        ['tenant' => $currentTenant->slug]
                    ) }}"
                >
                    Ver todas
                </a>
            </div>
        @endif
    </section>

    @include('delivery.partials.report-export-modal', [
        'reportProjects' => $projects,
        'selectedReportProject' => (int) request('project_id'),
    ])

    {{-- =========================================================
         ENTREGAS
         ========================================================= --}}
    <section class="da-section">
        <header class="da-section-head">
            <span class="da-section-icon list" aria-hidden="true">
                <i class="ph-duotone ph-list-dashes"></i>
            </span>

            <div class="da-section-copy">
                <h2>Entregas registradas</h2>
                <p>
                    Dados físicos, financeiro, faturamento
                    e ações no mesmo registro.
                </p>
            </div>

            <span class="da-count">
                <i class="ph ph-magnifying-glass"></i>
                {{ $deliveries->total() }}
                {{ $deliveries->total() === 1
                    ? 'resultado'
                    : 'resultados' }}
            </span>
        </header>

        @if($deliveries->isEmpty())
            <div class="da-empty">
                <div class="da-empty-content">
                    <span class="da-empty-icon" aria-hidden="true">
                        <i class="ph-duotone ph-package"></i>
                    </span>

                    <strong>Nenhuma entrega encontrada</strong>

                    <span>
                        Ajuste os filtros ou aguarde o registro
                        de novas entregas.
                    </span>
                </div>
            </div>
        @else
            <div class="da-delivery-list">
                @foreach($deliveries as $d)
                    @php
                        $paidDists = $d->distributions
                            ->filter(
                                fn ($dist) =>
                                    $dist->billing_status
                                    === \App\Enums\BillingStatus::PAID
                            )
                            ->count();

                        $billedDists = $d->distributions
                            ->filter(
                                fn ($dist) =>
                                    $dist->billing_status
                                    === \App\Enums\BillingStatus::BILLED
                            )
                            ->count();

                        $totalDists = $d->distributions->count();

                        $hasBilledDists = $d->distributions
                            ->contains(
                                fn ($dist) =>
                                    $dist->billing_status
                                    instanceof \App\Enums\BillingStatus
                                    && $dist->billing_status
                                        !== \App\Enums\BillingStatus::UNBILLED
                            );

                        $distributedQuantity = (float) $d
                            ->distributions
                            ->sum('quantity');

                        $billingTone = 'none';
                        $billingLabel = '—';

                        if (
                            $totalDists > 0
                            && $paidDists === $totalDists
                        ) {
                            $billingTone = 'paid';
                            $billingLabel = 'Pago';
                        } elseif(
                            $paidDists > 0
                            || $billedDists > 0
                        ) {
                            $billingTone = 'billed';
                            $billingLabel = 'Faturado';
                        }

                        $deliveryDay = $d->delivery_date?->format('d')
                            ?? '--';

                        $deliveryMonth = $d->delivery_date
                            ? strtoupper(
                                $d->delivery_date
                                    ->locale('pt_BR')
                                    ->translatedFormat('M')
                            )
                            : '---';

                        $projectTitle = optional(
                            $d->salesProject
                        )->title ?? 'Avulsa';

                        $associateName = optional(
                            optional($d->associate)->user
                        )->name ?? '-';

                        $unit = optional($d->product)->unit ?? 'un';
                    @endphp

                    <article
                        class="da-delivery"
                        id="row-{{ $d->id }}"
                    >
                        <span
                            class="da-date"
                            aria-label="{{ $d->delivery_date?->format('d/m/Y') ?? 'Data não informada' }}"
                        >
                            <strong>{{ $deliveryDay }}</strong>

                            <span>
                                {{ \Illuminate\Support\Str::limit(
                                    $deliveryMonth,
                                    3,
                                    ''
                                ) }}
                            </span>
                        </span>

                        <div class="da-delivery-content">
                            <div class="da-delivery-head">
                                <div class="da-delivery-title">
                                    <div class="da-title-line">
                                        <strong class="da-product-name">
                                            {{ optional($d->product)->name ?? '-' }}
                                        </strong>

                                        <span
                                            class="badge-status {{ $d->status->value }}"
                                        >
                                            {{ $d->status->getLabel() }}
                                        </span>
                                    </div>

                                    <div class="da-delivery-meta">
                                        <span class="associate">
                                            <i class="ph ph-user-circle"></i>

                                            <span>
                                                {{ $associateName }}
                                            </span>
                                        </span>

                                        <span class="project">
                                            <i class="ph ph-folder"></i>

                                            <span title="{{ $projectTitle }}">
                                                {{ $projectTitle }}
                                            </span>
                                        </span>
                                    </div>
                                </div>

                                <div class="da-gross">
                                    <span>Valor bruto</span>

                                    <strong>
                                        R$
                                        {{ number_format(
                                            $d->gross_value,
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </strong>
                                </div>
                            </div>

                            <div class="da-details">
                                <div class="da-detail">
                                    <span>Quantidade</span>

                                    <strong>
                                        {{ number_format(
                                            $d->quantity,
                                            3,
                                            ',',
                                            '.'
                                        ) }}
                                        {{ $unit }}
                                    </strong>
                                </div>

                                <div class="da-detail">
                                    <span>Distribuído</span>

                                    <strong>
                                        {{ number_format(
                                            $distributedQuantity,
                                            3,
                                            ',',
                                            '.'
                                        ) }}
                                        {{ $unit }}
                                    </strong>
                                </div>

                                <div class="da-detail">
                                    <span>Faturamento</span>

                                    <strong
                                        class="da-billing {{ $billingTone }}"
                                    >
                                        {{ $billingLabel }}
                                    </strong>
                                </div>

                                <div class="da-detail">
                                    <span>Qualidade</span>

                                    <strong>
                                        {{ $d->quality_grade ?? '-' }}
                                    </strong>
                                </div>
                            </div>

                            <div class="da-actions-row">
                                <div class="da-note-slot">
                                    @if(filled($d->notes))
                                        <button
                                            type="button"
                                            class="delivery-note-trigger"
                                            data-delivery-notes="{{ $d->notes }}"
                                            data-delivery-notes-title="Observações da entrega"
                                            data-delivery-notes-meta="{{ optional($d->product)->name ?? 'Produto' }} · {{ $d->delivery_date?->format('d/m/Y') }}"
                                        >
                                            Observações
                                        </button>
                                    @endif
                                </div>

                                @if($d->status->value === 'pending')
                                    <div class="action-btns">
                                        <button
                                            class="btn-xs btn-approve"
                                            data-id="{{ $d->id }}"
                                            title="Aprovar"
                                        >
                                            <i data-lucide="check"></i>
                                            Aprovar
                                        </button>

                                        <button
                                            class="btn-xs btn-reject"
                                            data-id="{{ $d->id }}"
                                            title="Rejeitar"
                                        >
                                            <i data-lucide="x"></i>
                                            Rejeitar
                                        </button>
                                    </div>
                                @elseif(
                                    $d->status->value === 'approved'
                                    && is_null($d->customer_id)
                                )
                                    <div class="action-btns">
                                        <button
                                            class="btn-xs btn-distribute"
                                            data-id="{{ $d->id }}"
                                            data-product="{{ optional($d->product)->name ?? '-' }}"
                                            data-unit="{{ $unit }}"
                                            data-qty="{{ $d->quantity }}"
                                            data-distributed="{{ $distributedQuantity }}"
                                            data-notes="{{ $d->notes ?? '' }}"
                                            data-existing="{{ json_encode(
                                                $d->distributions->map(
                                                    fn ($dist) => [
                                                        'id' => $dist->id,
                                                        'customer_id' => $dist->customer_id,
                                                        'customer' => optional($dist->customer)->name ?? '?',
                                                        'qty' => $dist->quantity,
                                                        'net' => (float) $dist->net_value,
                                                        'billed' => $dist->billing_status instanceof \App\Enums\BillingStatus
                                                            && $dist->billing_status !== \App\Enums\BillingStatus::UNBILLED,
                                                    ]
                                                )
                                            ) }}"
                                            title="Distribuir para clientes"
                                        >
                                            <i data-lucide="git-branch"></i>
                                            Distribuir
                                        </button>

                                        @if($hasBilledDists)
                                            <button
                                                class="btn-xs da-blocked"
                                                disabled
                                                title="Entrega faturada — exclusão bloqueada"
                                            >
                                                <i data-lucide="lock"></i>
                                                Bloqueado
                                            </button>
                                        @else
                                            <button
                                                class="btn-xs btn-delete-approved"
                                                data-id="{{ $d->id }}"
                                                title="Excluir entrega aprovada"
                                                aria-label="Excluir entrega aprovada"
                                            >
                                                <i data-lucide="trash-2"></i>
                                                Excluir
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if($deliveries->hasPages())
                <div class="pagination-wrap">
                    {{ $deliveries
                        ->withQueryString()
                        ->links('vendor.pagination.bento') }}
                </div>
            @endif
        @endif
    </section>
</main>

<!-- Mantido por compatibilidade; o componente x-delivery.dist-modal é o modal real. -->
<div
    class="modal-overlay"
    id="distModal"
    style="display:none"
></div>
@endsection


@push('scripts')
<script>
const TENANT_SLUG = '{{ $currentTenant->slug }}';
const CSRF_TOKEN  = '{{ csrf_token() }}';
const ALL_CUSTOMERS = @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->trade_name ?: $c->name]));

/* ── Distribute button click → component DistModal ── */
document.addEventListener('click', function(e) {
    const distBtn = e.target.closest('.btn-distribute');
    if (distBtn) { DistModal.openFromBtn(distBtn); return; }
});

/* ── Inline approve/reject ── */
document.addEventListener('click', async function(e) {
    const approveBtn = e.target.closest('.btn-approve');
    const rejectBtn  = e.target.closest('.btn-reject');
    if (!approveBtn && !rejectBtn) return;

    const btn    = approveBtn || rejectBtn;
    const id     = btn.dataset.id;
    const action = approveBtn ? 'approve' : 'reject';

    if (!confirm(action === 'approve' ? 'Aprovar esta entrega?' : 'Rejeitar esta entrega?')) return;

    btn.disabled = true;
    const row     = document.getElementById('row-' + id);
    const allBtns = row ? row.querySelectorAll('.btn-xs') : [btn];
    allBtns.forEach(b => b.disabled = true);

    try {
        const res  = await fetch(`/${TENANT_SLUG}/delivery/deliveries/${id}/${action}`, {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Content-Type': 'application/json', 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            if (row) {
                const statusCell = row.querySelector('.badge-status');
                const actionCell = row.querySelector('.action-btns');
                if (statusCell) {
                    statusCell.className  = 'badge-status ' + (action === 'approve' ? 'approved' : 'rejected');
                    statusCell.textContent = action === 'approve' ? 'Aprovada' : 'Rejeitada';
                }
                if (actionCell) {
                    if (action === 'approve') {
                        location.reload();
                    } else {
                        actionCell.innerHTML = '<span style="font-size:.7rem;color:var(--color-text-secondary)">—</span>';
                    }
                }
            }
        } else {
            alert(data.message || 'Erro ao processar.');
            allBtns.forEach(b => b.disabled = false);
        }
    } catch(err) {
        alert('Erro de comunicação com o servidor.');
        allBtns.forEach(b => b.disabled = false);
    }
});

function esc(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

/* ── Delete approved delivery ── */
document.addEventListener('click', async function(e) {
    const btn = e.target.closest('.btn-delete-approved');
    if (!btn) return;
    const id = btn.dataset.id;
    if (!confirm('Excluir esta entrega aprovada? Esta ação também removerá as distribuições associadas e não pode ser desfeita.')) return;
    btn.disabled = true;
    const row = document.getElementById('row-' + id);
    try {
        const res  = await fetch(`/${TENANT_SLUG}/delivery/deliveries/${id}`, {
            method : 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            row?.remove();
        } else {
            alert(data.message || 'Erro ao excluir.');
            btn.disabled = false;
        }
    } catch(err) {
        alert('Erro de comunicação com o servidor.');
        btn.disabled = false;
    }
});
</script>
@endpush