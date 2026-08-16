@extends('layouts.bento')

@section('title', 'Limites do associado')
@section('page-title', 'Limites do associado')
@section('user-role', 'Gestao de entregas')

@php
    $tenantSlug = request()->route('tenant')->slug ?? request()->route('tenant');
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', $tenantSlug);
@endphp


@section('content')
<style>
    .limits-page {
        --limit-green: #168a4d;
        --limit-green-soft: #eaf8ef;
        --limit-blue: #2563eb;
        --limit-blue-soft: #eef4ff;
        --limit-violet: #7c3aed;
        --limit-violet-soft: #f4f0ff;
        --limit-amber: #c87408;
        --limit-amber-soft: #fff7e8;
        --limit-red: #cf3f3f;
        --limit-red-soft: #fff0f0;
        --limit-slate: #64748b;
        --limit-slate-soft: #f1f5f9;

        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
        padding-bottom: 1rem;
    }

    .limits-page *,
    .limits-page *::before,
    .limits-page *::after {
        box-sizing: border-box;
    }

    .limits-surface {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 15px;
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }

    .limits-section-head {
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

    .limits-section-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .limits-section-icon.summary {
        background: var(--limit-blue-soft);
        color: var(--limit-blue);
    }

    .limits-section-icon.products {
        background: var(--limit-violet-soft);
        color: var(--limit-violet);
    }

    .limits-section-icon > i,
    .limits-section-icon > svg {
        width: 18px;
        height: 18px;
        stroke-width: 2;
    }

    .limits-section-copy {
        min-width: 0;
    }

    .limits-section-copy h2,
    .limits-section-copy p {
        margin: 0;
    }

    .limits-section-copy h2 {
        color: var(--color-text);
        font-size: .95rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .limits-section-copy p {
        margin-top: .08rem;
        color: var(--color-text-muted);
        font-size: .75rem;
        line-height: 1.42;
    }

    .limits-count {
        display: grid;
        min-height: 30px;
        place-items: center;
        padding: .28rem .44rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .7rem;
        font-weight: 770;
        white-space: nowrap;
    }

    /* =========================================================
       CABEÇALHO CONTEXTUAL
       ========================================================= */

    .limits-context {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: center;
        padding: .72rem .76rem;
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(34, 197, 94, .075),
                transparent 16rem
            ),
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                #fff
            );
    }

    .limits-back {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border: 1px solid var(--color-border);
        border-radius: 11px;
        background: #fff;
        color: var(--color-text-secondary);
        text-decoration: none;
    }

    .limits-back:hover,
    .limits-back:focus-visible {
        border-color: rgba(34, 197, 94, .28);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
        outline: none;
    }

    .limits-back > i,
    .limits-back > svg {
        width: 17px;
        height: 17px;
    }

    .limits-context-copy {
        min-width: 0;
    }

    .limits-context-label {
        display: block;
        color: var(--limit-green);
        font-size: .69rem;
        font-weight: 780;
    }

    .limits-context-copy h1 {
        margin: .08rem 0 0;
        color: var(--color-text);
        font-size: clamp(1rem, 2vw, 1.18rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.28;
        overflow-wrap: anywhere;
    }

    .limits-context-meta {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .46rem;
        margin-top: .14rem;
        color: var(--color-text-muted);
        font-size: .71rem;
    }

    .limits-context-meta span {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, auto);
        gap: .22rem;
        align-items: center;
    }

    .limits-context-meta i,
    .limits-context-meta svg {
        width: 13px;
        height: 13px;
    }

    .limits-context-meta span span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .limits-secondary-action {
        display: grid;
        min-height: 40px;
        grid-template-columns: auto auto;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .46rem .62rem;
        border: 1px solid var(--color-border);
        border-radius: 10px;
        background: #fff;
        color: var(--color-text-secondary);
        font-size: .72rem;
        font-weight: 780;
        text-decoration: none;
        white-space: nowrap;
    }

    .limits-secondary-action > i,
    .limits-secondary-action > svg {
        width: 15px;
        height: 15px;
    }

    /* =========================================================
       GUIA RÁPIDO
       ========================================================= */

    .limits-guide {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: .48rem;
        padding: .65rem .7rem;
        border-top: 1px solid var(--color-border);
        background: #fff;
    }

    .limits-guide-item {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .46rem;
        align-items: start;
        padding: .5rem;
        border-radius: 10px;
        background: var(--color-surface-soft);
    }

    .limits-guide-number {
        display: grid;
        width: 27px;
        height: 27px;
        place-items: center;
        border-radius: 8px;
        background: var(--limit-blue-soft);
        color: var(--limit-blue);
        font-size: .67rem;
        font-weight: 850;
    }

    .limits-guide-copy strong,
    .limits-guide-copy span {
        display: block;
    }

    .limits-guide-copy strong {
        color: var(--color-text);
        font-size: .73rem;
        font-weight: 810;
    }

    .limits-guide-copy span {
        margin-top: .04rem;
        color: var(--color-text-muted);
        font-size: .68rem;
        line-height: 1.4;
    }

    /* =========================================================
       RESUMO FINANCEIRO
       ========================================================= */

    .limits-summary-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(290px, .9fr)
            minmax(0, 1.1fr);
    }

    .limits-budget {
        display: grid;
        min-height: 205px;
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
                var(--limit-blue-soft)
            );
    }

    .limits-budget-label {
        display: grid;
        width: max-content;
        grid-template-columns: auto auto;
        gap: .32rem;
        align-items: center;
        color: var(--limit-blue);
        font-size: .73rem;
        font-weight: 790;
    }

    .limits-budget-label > i,
    .limits-budget-label > svg {
        width: 16px;
        height: 16px;
    }

    .limits-budget-value {
        display: block;
        margin-top: .34rem;
        color: var(--color-text);
        font-size: clamp(1.7rem, 4vw, 2.35rem);
        font-weight: 875;
        letter-spacing: -.045em;
        line-height: 1;
    }

    .limits-budget-helper {
        max-width: 390px;
        margin-top: .42rem;
        color: var(--color-text-secondary);
        font-size: .76rem;
        line-height: 1.5;
    }

    .limits-budget-meter {
        height: 8px;
        margin-top: .62rem;
        overflow: hidden;
        border-radius: 999px;
        background: #dce7e0;
    }

    .limits-budget-meter > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #60a5fa,
                var(--limit-blue)
            );
        transition: width 180ms ease;
    }

    .limits-budget-meter.warning > span {
        background:
            linear-gradient(
                90deg,
                #fbbf24,
                var(--limit-amber)
            );
    }

    .limits-budget-meter.danger > span {
        background:
            linear-gradient(
                90deg,
                #fb7185,
                var(--limit-red)
            );
    }

    .limits-summary-side {
        display: grid;
        min-width: 0;
        align-content: center;
        padding: .74rem;
        background: #fff;
    }

    .limits-financial-info {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: .48rem;
        margin-bottom: .62rem;
    }

    .limits-financial-stat {
        min-width: 0;
        padding: .56rem .6rem;
        border-radius: 10px;
        background: var(--color-surface-soft);
    }

    .limits-financial-stat span,
    .limits-financial-stat strong {
        display: block;
    }

    .limits-financial-stat span {
        color: var(--color-text-muted);
        font-size: .67rem;
        font-weight: 680;
    }

    .limits-financial-stat strong {
        margin-top: .05rem;
        color: var(--color-text);
        font-size: .8rem;
        font-weight: 830;
        overflow-wrap: anywhere;
    }

    .limits-financial-stat.remaining strong {
        color: var(--limit-green);
    }

    .limits-ceiling {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: end;
        padding-top: .62rem;
        border-top: 1px solid var(--color-border);
    }

    .limits-field {
        min-width: 0;
    }

    .limits-field label {
        display: block;
        margin-bottom: .28rem;
        color: var(--color-text-secondary);
        font-size: .7rem;
        font-weight: 740;
    }

    .limits-field-helper {
        display: block;
        margin-top: .24rem;
        color: var(--color-text-muted);
        font-size: .66rem;
        line-height: 1.35;
    }

    .limits-control {
        width: 100%;
        min-height: 43px;
        padding: .52rem .64rem;
        border: 1px solid var(--color-border-strong);
        border-radius: 10px;
        outline: none;
        background: #fff;
        color: var(--color-text);
        font: inherit;
        font-size: .78rem;
    }

    .limits-control:focus {
        border-color: var(--color-primary);
        box-shadow:
            0 0 0 3px rgba(34, 197, 94, .10);
    }

    .limits-control:disabled {
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        cursor: not-allowed;
    }

    /* =========================================================
       PRODUTOS / FERRAMENTAS
       ========================================================= */

    .limits-product-toolbar {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(220px, 1fr) auto;
        gap: .5rem;
        padding: .66rem .72rem;
        border-bottom: 1px solid var(--color-border);
        background: #fff;
    }

    .limits-search-wrap {
        position: relative;
        min-width: 0;
    }

    .limits-search-wrap > i,
    .limits-search-wrap > svg {
        position: absolute;
        top: 50%;
        left: .68rem;
        width: 15px;
        height: 15px;
        color: var(--color-text-muted);
        pointer-events: none;
        transform: translateY(-50%);
    }

    .limits-search-wrap .limits-control {
        padding-left: 2.08rem;
    }

    .limits-button {
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

    .limits-button > i,
    .limits-button > svg {
        width: 15px;
        height: 15px;
    }

    .limits-button.primary {
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

    .limits-button.danger {
        border-color: rgba(207, 63, 63, .2);
        background: #fff;
        color: var(--limit-red);
    }

    .limits-button:disabled {
        cursor: not-allowed;
        opacity: .55;
    }

    .limits-button:hover:not(:disabled),
    .limits-button:focus-visible:not(:disabled) {
        outline: none;
        transform: translateY(-1px);
    }

    /* =========================================================
       SELETOR DE PRODUTOS
       ========================================================= */

    .limits-picker {
        display: grid;
        max-height: 320px;
        gap: .36rem;
        padding: .58rem .72rem .72rem;
        overflow: auto;
        border-bottom: 1px solid var(--color-border);
        background: var(--color-surface-soft);
    }

    .limits-picker[hidden] {
        display: none;
    }

    .limits-option {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        min-height: 58px;
        padding: .55rem .6rem;
        border: 1px solid var(--color-border);
        border-radius: 10px;
        background: #fff;
        color: var(--color-text);
        text-align: left;
        cursor: pointer;
    }

    .limits-option-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 9px;
        background: var(--limit-violet-soft);
        color: var(--limit-violet);
    }

    .limits-option-icon > i,
    .limits-option-icon > svg {
        width: 15px;
        height: 15px;
    }

    .limits-option-copy {
        min-width: 0;
    }

    .limits-option-copy strong,
    .limits-option-copy span {
        display: block;
    }

    .limits-option-copy strong {
        color: var(--color-text);
        font-size: .77rem;
        font-weight: 810;
    }

    .limits-option-copy span {
        margin-top: .04rem;
        color: var(--color-text-muted);
        font-size: .68rem;
    }

    .limits-option-availability {
        color: var(--limit-green);
        font-size: .7rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .limits-option:disabled {
        cursor: not-allowed;
        opacity: .52;
    }

    .limits-option:disabled .limits-option-availability {
        color: var(--limit-red);
    }

    /* =========================================================
       CARDS DE PRODUTO
       ========================================================= */

    .limits-grid {
        display: grid;
        grid-template-columns:
            repeat(auto-fill, minmax(390px, 1fr));
        gap: .62rem;
        padding: .68rem;
    }

    .limit-card {
        --card-tone: var(--limit-green);
        --card-soft: var(--limit-green-soft);

        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 13px;
        background: #fff;
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease;
    }

    .limit-card.is-editing {
        border-color: rgba(124, 58, 237, .28);
        box-shadow:
            0 0 0 3px rgba(124, 58, 237, .055);
    }

    .limit-card.is-invalid {
        --card-tone: var(--limit-red);
        --card-soft: var(--limit-red-soft);
        border-color: rgba(207, 63, 63, .30);
    }

    .limit-card-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .55rem;
        align-items: center;
        padding: .62rem .64rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                #fff
            );
    }

    .limit-product-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 10px;
        background: var(--card-soft);
        color: var(--card-tone);
    }

    .limit-product-icon > i,
    .limit-product-icon > svg {
        width: 16px;
        height: 16px;
    }

    .limit-product-copy {
        min-width: 0;
    }

    .limit-product-name {
        display: block;
        color: var(--color-text);
        font-size: .84rem;
        font-weight: 830;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .limit-product-price {
        display: block;
        margin-top: .05rem;
        color: var(--color-text-muted);
        font-size: .68rem;
    }

    .limit-card-actions {
        display: grid;
        grid-auto-flow: column;
        gap: .28rem;
    }

    .limit-icon-button {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 1px solid var(--color-border);
        border-radius: 9px;
        background: #fff;
        color: var(--color-text-secondary);
        cursor: pointer;
    }

    .limit-icon-button.edit {
        color: var(--limit-violet);
    }

    .limit-icon-button.remove {
        color: var(--limit-red);
    }

    .limit-icon-button > i,
    .limit-icon-button > svg {
        width: 14px;
        height: 14px;
    }

    .limit-card-body {
        display: grid;
        gap: .56rem;
        padding: .62rem .64rem;
    }

    .limit-card-primary {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        gap: .42rem;
    }

    .limit-metric {
        min-width: 0;
        padding: .48rem .52rem;
        border-radius: 10px;
        background: var(--color-surface-soft);
    }

    .limit-metric span,
    .limit-metric strong {
        display: block;
    }

    .limit-metric span {
        color: var(--color-text-muted);
        font-size: .65rem;
        font-weight: 680;
    }

    .limit-metric strong {
        margin-top: .05rem;
        color: var(--color-text);
        font-size: .75rem;
        font-weight: 830;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .limit-metric.balance strong {
        color: var(--card-tone);
    }

    .limit-usage {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
    }

    .limit-usage span {
        color: var(--color-text-muted);
        font-size: .68rem;
        font-weight: 710;
    }

    .limit-usage strong {
        color: var(--card-tone);
        font-size: .7rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .limit-meter {
        height: 8px;
        overflow: hidden;
        border-radius: 999px;
        background: #e5ece7;
    }

    .limit-meter > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                #4ade80,
                var(--card-tone)
            );
        transition: width 180ms ease;
    }

    .limit-editor {
        display: none;
        gap: .52rem;
        padding: .58rem;
        border-radius: 10px;
        background: var(--limit-violet-soft);
    }

    .limit-card.is-editing .limit-editor {
        display: grid;
    }

    .limit-editor-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
    }

    .limit-editor-head strong {
        color: var(--limit-violet);
        font-size: .73rem;
        font-weight: 820;
    }

    .limit-editor-head span {
        color: var(--color-text-muted);
        font-size: .66rem;
        text-align: right;
    }

    .limit-editor-fields {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 155px;
        gap: .52rem;
        align-items: end;
    }

    .limit-editor label {
        display: grid;
        gap: .26rem;
        color: var(--color-text-secondary);
        font-size: .68rem;
        font-weight: 740;
    }

    .limit-slider {
        width: 100%;
        min-height: 38px;
        accent-color: var(--limit-violet);
        touch-action: pan-y;
    }

    .limit-slider:disabled {
        cursor: not-allowed;
        opacity: .7;
    }

    .limit-message {
        display: grid;
        min-height: 34px;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .36rem;
        align-items: start;
        padding: .4rem .48rem;
        border-radius: 9px;
        background: var(--color-surface-soft);
        color: var(--color-text-secondary);
        font-size: .68rem;
        line-height: 1.42;
    }

    .limit-message::before {
        content: "i";
        display: grid;
        width: 18px;
        height: 18px;
        place-items: center;
        border-radius: 999px;
        background: var(--limit-blue-soft);
        color: var(--limit-blue);
        font-size: .61rem;
        font-weight: 900;
        line-height: 1;
    }

    .limit-message.error {
        background: var(--limit-red-soft);
        color: #991b1b;
        font-weight: 730;
    }

    .limit-message.error::before {
        content: "!";
        background: #fee2e2;
        color: var(--limit-red);
    }

    /* =========================================================
       ESTADOS / SALVAMENTO
       ========================================================= */

    .limits-empty,
    .limits-loading {
        display: grid;
        min-height: 190px;
        place-items: center;
        grid-column: 1 / -1;
        padding: 1rem;
        border-radius: 11px;
        background: var(--color-surface-soft);
        color: var(--color-text-secondary);
        text-align: center;
        font-size: .75rem;
        line-height: 1.45;
    }

    .limits-savebar {
        position: sticky;
        z-index: 24;
        bottom:
            calc(
                var(--app-bottom-nav-height, 0px)
                + .5rem
                + env(safe-area-inset-bottom)
            );
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        padding: .6rem .68rem;
        border: 1px solid var(--color-border);
        border-radius: 12px;
        background: rgba(255, 255, 255, .98);
        box-shadow:
            0 -6px 20px rgba(15, 35, 24, .07);
    }

    .limits-feedback {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .4rem;
        align-items: center;
        color: var(--color-text-secondary);
        font-size: .72rem;
        font-weight: 710;
    }

    .limits-feedback::before {
        content: "";
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: var(--limit-slate);
    }

    .limits-feedback.changed::before {
        background: var(--limit-amber);
    }

    .limits-feedback.saved::before {
        background: var(--limit-green);
    }

    .limits-feedback.error {
        color: #991b1b;
    }

    .limits-feedback.error::before {
        background: var(--limit-red);
    }

    /* =========================================================
       DIÁLOGO
       ========================================================= */

    .limits-dialog {
        width: min(430px, calc(100vw - 1rem));
        overflow: hidden;
        padding: 0;
        border: 1px solid var(--color-border);
        border-radius: 15px;
        background: var(--color-surface);
        color: var(--color-text);
        box-shadow:
            0 24px 70px rgba(8, 24, 15, .24);
    }

    .limits-dialog::backdrop {
        background: rgba(15, 23, 42, .48);
        backdrop-filter: blur(2px);
    }

    .limits-dialog-head {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .52rem;
        align-items: center;
        padding: .72rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                #fff
            );
    }

    .limits-dialog-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 10px;
        background: var(--limit-red-soft);
        color: var(--limit-red);
    }

    .limits-dialog-icon > i,
    .limits-dialog-icon > svg {
        width: 16px;
        height: 16px;
    }

    .limits-dialog-head h3 {
        margin: 0;
        color: var(--color-text);
        font-size: .84rem;
        font-weight: 830;
    }

    .limits-dialog-body {
        padding: .75rem;
    }

    .limits-dialog-body p {
        margin: 0;
        color: var(--color-text-secondary);
        font-size: .75rem;
        line-height: 1.5;
    }

    .limits-dialog-actions {
        display: grid;
        grid-auto-flow: column;
        gap: .4rem;
        justify-content: end;
        padding: .65rem .75rem .75rem;
        border-top: 1px solid var(--color-border);
        background: var(--color-surface-soft);
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 920px) {
        .limits-summary-grid {
            grid-template-columns: 1fr;
        }

        .limits-summary-side {
            border-top: 1px solid var(--color-border);
        }

        .limits-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 700px) {
        .limits-context {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .limits-secondary-action {
            grid-column: 2;
            justify-self: start;
        }

        .limits-guide {
            grid-template-columns: 1fr;
            gap: .26rem;
        }

        .limits-guide-item {
            background: #fff;
        }

        .limits-guide-item + .limits-guide-item {
            border-top: 1px solid var(--color-border);
            border-radius: 0;
        }

        .limits-product-toolbar {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 560px) {
        .limits-page {
            gap: .7rem;
        }

        .limits-section-head,
        .limits-context {
            padding: .62rem;
        }

        .limits-section-copy p {
            display: none;
        }

        .limits-budget {
            min-height: 180px;
            padding: .82rem;
        }

        .limits-financial-info {
            grid-template-columns: 1fr 1fr;
        }

        .limits-ceiling {
            grid-template-columns: 1fr;
        }

        .limits-ceiling .limits-button {
            width: 100%;
        }

        .limits-grid {
            padding: .58rem;
        }

        .limit-card-primary {
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
            gap: .3rem;
        }

        .limit-metric {
            padding: .42rem .4rem;
        }

        .limit-metric span {
            font-size: .61rem;
        }

        .limit-metric strong {
            font-size: .69rem;
        }

        .limit-editor-fields {
            grid-template-columns: 1fr;
        }

        .limits-savebar {
            grid-template-columns: 1fr;
        }

        .limits-savebar .limits-button {
            width: 100%;
        }
    }

    @media (max-width: 420px) {
        .limits-context {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .limits-back {
            width: 36px;
            height: 36px;
        }

        .limits-context-meta {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            gap: .1rem;
        }

        .limits-financial-info {
            grid-template-columns: 1fr;
        }

        .limit-card-head {
            grid-template-columns: 36px minmax(0, 1fr) auto;
            gap: .46rem;
        }

        .limit-product-icon {
            width: 36px;
            height: 36px;
        }

        .limit-card-primary {
            grid-template-columns: 1fr 1fr;
        }

        .limit-metric.balance {
            grid-column: 1 / -1;
        }

        .limits-option {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .limits-option-availability {
            grid-column: 2;
            justify-self: start;
        }
    }
</style>

<main
    class="limits-page"
    id="aql-app"
    data-limits-url="{{ route(
        'delivery.projects.associates.data',
        [
            'tenant' => $tenantSlug,
            'project' => $project->id,
            'associate' => $associate->id,
            'section' => 'limits',
        ]
    ) }}"
    data-products-url="{{ route(
        'delivery.projects.associates.data',
        [
            'tenant' => $tenantSlug,
            'project' => $project->id,
            'associate' => $associate->id,
            'section' => 'products',
        ]
    ) }}"
    data-financial-url="{{ route(
        'delivery.projects.associates.limits.financial',
        [
            'tenant' => $tenantSlug,
            'project' => $project->id,
            'associate' => $associate->id,
        ]
    ) }}"
    data-can-manage="{{ $canManageLimits ? '1' : '0' }}"
>
    {{-- =========================================================
         CONTEXTO
         ========================================================= --}}
    <section class="limits-surface">
        <div class="limits-context">
            <a
                class="limits-back"
                href="{{ route(
                    'delivery.projects.associates.show',
                    [
                        'tenant' => $tenantSlug,
                        'project' => $project->id,
                        'associate' => $associate->id,
                    ]
                ) }}"
                aria-label="Voltar ao associado"
                title="Voltar ao associado"
            >
                <i data-lucide="arrow-left"></i>
            </a>

            <div class="limits-context-copy">
                <span class="limits-context-label">
                    Limites individuais
                </span>

                <h1>{{ $associate->display_name }}</h1>

                <div class="limits-context-meta">
                    <span>
                        <i data-lucide="folder"></i>

                        <span>
                            {{ $project->title }}
                        </span>
                    </span>

                    <span>
                        <i data-lucide="package-check"></i>

                        <span>
                            Cotas de entrega por produto
                        </span>
                    </span>
                </div>
            </div>

            <a
                class="limits-secondary-action"
                href="{{ route(
                    'delivery.projects.product-limits.index',
                    [
                        'tenant' => $tenantSlug,
                        'project' => $project->id,
                    ]
                ) }}"
            >
                <i data-lucide="boxes"></i>
                Ver limites por produto
            </a>
        </div>

        @if($canManageLimits)
            <div class="limits-guide">
                <div class="limits-guide-item">
                    <span class="limits-guide-number">1</span>

                    <span class="limits-guide-copy">
                        <strong>Defina o teto financeiro</strong>

                        <span>
                            Ele limita o valor total das cotas
                            deste associado.
                        </span>
                    </span>
                </div>

                <div class="limits-guide-item">
                    <span class="limits-guide-number">2</span>

                    <span class="limits-guide-copy">
                        <strong>Escolha os produtos</strong>

                        <span>
                            Adicione somente o que este associado
                            poderá entregar.
                        </span>
                    </span>
                </div>

                <div class="limits-guide-item">
                    <span class="limits-guide-number">3</span>

                    <span class="limits-guide-copy">
                        <strong>Ajuste e salve as cotas</strong>

                        <span>
                            A cota nunca pode ficar abaixo
                            do que já foi entregue.
                        </span>
                    </span>
                </div>
            </div>
        @endif
    </section>

    {{-- =========================================================
         RESUMO FINANCEIRO
         ========================================================= --}}
    <section class="limits-surface">
        <header class="limits-section-head">
            <span class="limits-section-icon summary" aria-hidden="true">
                <i data-lucide="wallet-cards"></i>
            </span>

            <div class="limits-section-copy">
                <h2>Planejamento financeiro</h2>

                <p>
                    Compare o valor das cotas com o teto disponível
                    para este associado.
                </p>
            </div>

            <span class="limits-count" id="aql-budget-label">
                Calculando...
            </span>
        </header>

        <div class="limits-summary-grid">
            <div class="limits-budget">
                <span class="limits-budget-label">
                    <i data-lucide="calculator"></i>
                    Valor planejado nas cotas
                </span>

                <strong
                    class="limits-budget-value"
                    id="aql-planned"
                >
                    R$ 0,00
                </strong>

                <div class="limits-budget-helper" id="aql-budget-helper">
                    Soma da quantidade máxima de cada produto
                    multiplicada pelo preço de referência.
                </div>

                <div
                    class="limits-budget-meter"
                    id="aql-budget-meter"
                    role="progressbar"
                    aria-label="Uso do teto financeiro"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="0"
                >
                    <span></span>
                </div>
            </div>

            <div class="limits-summary-side">
                <div class="limits-financial-info">
                    <div class="limits-financial-stat">
                        <span>Teto financeiro</span>

                        <strong id="aql-ceiling-display">
                            —
                        </strong>
                    </div>

                    <div class="limits-financial-stat remaining">
                        <span>Ainda disponível</span>

                        <strong id="aql-remaining">
                            —
                        </strong>
                    </div>
                </div>

                <div class="limits-ceiling">
                    <div class="limits-field">
                        <label for="aql-financial-input">
                            Teto financeiro do associado
                        </label>

                        <input
                            class="limits-control"
                            id="aql-financial-input"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="Ex.: 5000,00"
                            {{ $canManageLimits ? '' : 'disabled' }}
                        >

                        <span class="limits-field-helper">
                            Deixe vazio somente quando o projeto
                            não utilizar limite financeiro.
                        </span>
                    </div>

                    @if($canManageLimits)
                        <button
                            class="limits-button"
                            id="aql-financial-save"
                            type="button"
                        >
                            <i data-lucide="save"></i>
                            Salvar teto
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- =========================================================
         PRODUTOS
         ========================================================= --}}
    <section class="limits-surface">
        <header class="limits-section-head">
            <span class="limits-section-icon products" aria-hidden="true">
                <i data-lucide="package-open"></i>
            </span>

            <div class="limits-section-copy">
                <h2>Produtos permitidos e cotas</h2>

                <p>
                    Veja o que já foi entregue e ajuste
                    a quantidade máxima permitida.
                </p>
            </div>

            <span class="limits-count" id="aql-products-count">
                0 produtos
            </span>
        </header>

        @if($canManageLimits)
            <div class="limits-product-toolbar">
                <div class="limits-search-wrap">
                    <i data-lucide="search"></i>

                    <input
                        class="limits-control"
                        id="aql-search"
                        type="search"
                        placeholder="Buscar produto para adicionar..."
                        autocomplete="off"
                    >
                </div>

                <button
                    class="limits-button primary"
                    id="aql-toggle-products"
                    type="button"
                >
                    <i data-lucide="package-plus"></i>
                    Adicionar produto
                </button>
            </div>

            <section
                class="limits-picker"
                id="aql-picker"
                hidden
            ></section>
        @endif

        <section class="limits-grid" id="aql-grid">
            <div class="limits-loading">
                Carregando produtos e limites...
            </div>
        </section>
    </section>

    @if($canManageLimits)
        <footer class="limits-savebar">
            <span
                class="limits-feedback"
                id="aql-feedback"
            >
                Nenhuma alteração pendente.
            </span>

            <button
                class="limits-button primary"
                id="aql-save-all"
                type="button"
                disabled
            >
                <i data-lucide="save"></i>
                Salvar alterações
            </button>
        </footer>
    @endif
</main>

<dialog
    class="limits-dialog"
    id="aql-remove-dialog"
    aria-labelledby="aql-remove-title"
>
    <header class="limits-dialog-head">
        <span class="limits-dialog-icon" aria-hidden="true">
            <i data-lucide="trash-2"></i>
        </span>

        <h3 id="aql-remove-title">
            Remover produto deste associado?
        </h3>
    </header>

    <div class="limits-dialog-body">
        <p id="aql-remove-message"></p>
    </div>

    <div class="limits-dialog-actions">
        <button
            class="limits-button"
            type="button"
            id="aql-remove-cancel"
        >
            Cancelar
        </button>

        <button
            class="limits-button danger"
            type="button"
            id="aql-remove-confirm"
        >
            Remover produto
        </button>
    </div>
</dialog>

<script>
(() => {
    const root =
        document.getElementById('aql-app');

    const csrf =
        document
            .querySelector('meta[name="csrf-token"]')
            .content;

    const canManage =
        root.dataset.canManage === '1';

    const state = {
        products: [],
        rows: new Map(),
        originals: new Map(),
        summary: {},
        batchUrl: null,
        editing: null,
        busy: false,
        removal: null,
    };

    const money = value =>
        Number(value || 0).toLocaleString(
            'pt-BR',
            {
                style: 'currency',
                currency: 'BRL',
            }
        );

    const qty = value =>
        Number(value || 0).toLocaleString(
            'pt-BR',
            {
                maximumFractionDigits: 3,
            }
        );

    const esc = value =>
        String(value ?? '').replace(
            /[&<>"']/g,
            char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            })[char]
        );

    const icons = () =>
        window.lucide?.createIcons();

    const json = async (
        url,
        options = {}
    ) => {
        const response = await fetch(
            url,
            {
                ...options,

                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    ...(options.headers || {}),
                },
            }
        );

        const data = await response
            .json()
            .catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                data.message
                || Object
                    .values(data.errors || {})
                    .flat()[0]
                || 'Não foi possível concluir a operação.'
            );
        }

        return data;
    };

    const rowFrom = (
        product,
        current = null
    ) => {
        const delivered = Number(
            current?.delivered_quantity
            ?? product.delivered_quantity
            ?? 0
        );

        return {
            id: Number(product.id),

            name:
                product.name
                || current?.product
                || 'Produto',

            unit:
                product.unit
                || current?.unit
                || '',

            price: Number(
                product.price
                ?? current?.reference_unit_price
                ?? 0
            ),

            delivered,

            quantity: Number(
                current?.maximum_quantity
                ?? Math.max(
                    delivered,
                    .001
                )
            ),

            productMaximum:
                product.available_for_associate === null
                    ? null
                    : Number(
                        product.available_for_associate
                    ),

            projectMaximum:
                product.project_maximum === null
                    ? null
                    : Number(product.project_maximum),

            allocatedOthers: Number(
                product.allocated_to_others
                || 0
            ),

            deleteUrl:
                current?.delete_url
                || null,

            isNew: !current,
        };
    };

    const planned = exceptId =>
        [...state.rows.values()]
            .reduce(
                (sum, row) => {
                    const quantity =
                        Number.isFinite(row.quantity)
                            ? row.quantity
                            : 0;

                    return sum
                        + (
                            row.id === exceptId
                                ? 0
                                : quantity * row.price
                        );
                },
                0
            );

    const ceiling = () =>
        state.summary.financial_limit === null
            ? null
            : Number(
                state.summary.financial_limit
                || 0
            );

    const allowedMaximum = row => {
        const productMax =
            row.productMaximum === null
                ? Infinity
                : row.productMaximum;

        const budgetMax =
            ceiling() === null
            || row.price <= 0
                ? Infinity
                : Math.max(
                    0,
                    (
                        ceiling()
                        - planned(row.id)
                    ) / row.price
                );

        return Math.max(
            row.delivered,
            Math.min(
                productMax,
                budgetMax
            )
        );
    };

    const sliderMaximum = row =>
        Number.isFinite(allowedMaximum(row))
            ? allowedMaximum(row)
            : Math.max(
                100,
                row.delivered,
                row.quantity,
                Math.ceil(
                    row.quantity * 1.5
                )
            );

    const validation = row => {
        if (
            !Number.isFinite(row.quantity)
            || row.quantity <= 0
        ) {
            return 'Informe uma cota maior que zero.';
        }

        if (
            row.quantity
            < row.delivered - .000001
        ) {
            return (
                `A cota não pode ser menor que `
                + `${qty(row.delivered)} ${row.unit}, `
                + `pois essa quantidade já foi entregue.`
            );
        }

        if (
            row.quantity
            > allowedMaximum(row) + .000001
        ) {
            return (
                `O máximo disponível neste momento é `
                + `${qty(allowedMaximum(row))} ${row.unit}.`
            );
        }

        return '';
    };

    const rowChanged = row => {
        const key = String(row.id);

        return (
            !state.originals.has(key)
            || !Number.isFinite(row.quantity)
            || Math.abs(
                row.quantity
                - state.originals.get(key)
            ) > .000001
        );
    };

    const changedCount = () =>
        [...state.rows.values()]
            .filter(row => rowChanged(row))
            .length
        + Math.max(
            0,
            state.originals.size
            - [...state.rows.keys()]
                .filter(
                    key => state.originals.has(key)
                )
                .length
        );

    const changed = () =>
        changedCount() > 0;

    function hydrate(
        limits,
        products
    ) {
        state.products = products;
        state.summary = limits.summary || {};
        state.batchUrl =
            limits.batch_update_url;

        state.rows = new Map();
        state.originals = new Map();
        state.editing = null;

        const current = new Map(
            (limits.products || [])
                .map(
                    item => [
                        String(item.product_id),
                        item,
                    ]
                )
        );

        products.forEach(product => {
            const item =
                current.get(String(product.id));

            if (!item) {
                return;
            }

            const row =
                rowFrom(product, item);

            state.rows.set(
                String(row.id),
                row
            );

            state.originals.set(
                String(row.id),
                row.quantity
            );
        });

        document
            .getElementById(
                'aql-financial-input'
            )
            .value =
                state.summary.financial_limit
                ?? '';
    }

    function render() {
        const rows =
            [...state.rows.values()]
                .sort(
                    (a, b) =>
                        a.name.localeCompare(
                            b.name,
                            'pt-BR'
                        )
                );

        const grid =
            document.getElementById('aql-grid');

        grid.innerHTML =
            rows.length
                ? rows.map(card).join('')
                : `
                    <div class="limits-empty">
                        <div>
                            <strong>
                                Nenhum produto configurado
                            </strong>
                            <br>
                            ${canManage
                                ? 'Use “Adicionar produto” para definir o que este associado poderá entregar.'
                                : 'Ainda não existem cotas de produto para este associado.'}
                        </div>
                    </div>
                `;

        const count =
            document.getElementById(
                'aql-products-count'
            );

        if (count) {
            count.textContent =
                `${rows.length} `
                + (
                    rows.length === 1
                        ? 'produto'
                        : 'produtos'
                );
        }

        renderPicker();
        refresh();
        icons();
    }

    function card(row) {
        const editing =
            state.editing === String(row.id);

        const used =
            row.quantity > 0
                ? Math.min(
                    100,
                    row.delivered
                    / row.quantity
                    * 100
                )
                : 0;

        const balance =
            Math.max(
                0,
                row.quantity
                - row.delivered
            );

        const cardState =
            editing
                ? 'is-editing'
                : '';

        return `
            <article
                class="limit-card ${cardState}"
                id="produto-${row.id}"
            >
                <header class="limit-card-head">
                    <span
                        class="limit-product-icon"
                        aria-hidden="true"
                    >
                        <i data-lucide="package"></i>
                    </span>

                    <div class="limit-product-copy">
                        <strong class="limit-product-name">
                            ${esc(row.name)}
                        </strong>

                        <span class="limit-product-price">
                            ${money(row.price)}
                            por
                            ${esc(row.unit || 'unidade')}
                        </span>
                    </div>

                    ${canManage
                        ? `
                            <div class="limit-card-actions">
                                <button
                                    class="limit-icon-button edit"
                                    type="button"
                                    onclick="aqlEdit(${row.id})"
                                    title="${editing
                                        ? 'Fechar edição'
                                        : 'Editar cota'}"
                                    aria-label="${editing
                                        ? 'Fechar edição'
                                        : 'Editar cota'}"
                                >
                                    <i
                                        data-lucide="${editing
                                            ? 'check'
                                            : 'pencil'}"
                                    ></i>
                                </button>

                                <button
                                    class="limit-icon-button remove"
                                    type="button"
                                    onclick="aqlRemove(${row.id})"
                                    title="Remover produto"
                                    aria-label="Remover produto"
                                >
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>
                        `
                        : ''}
                </header>

                <div class="limit-card-body">
                    <div class="limit-card-primary">
                        <div class="limit-metric">
                            <span>Já entregue</span>

                            <strong>
                                ${qty(row.delivered)}
                                ${esc(row.unit)}
                            </strong>
                        </div>

                        <div class="limit-metric">
                            <span>Cota máxima</span>

                            <strong id="aql-quota-${row.id}">
                                ${qty(row.quantity)}
                                ${esc(row.unit)}
                            </strong>
                        </div>

                        <div class="limit-metric balance">
                            <span>Ainda pode entregar</span>

                            <strong id="aql-balance-${row.id}">
                                ${qty(balance)}
                                ${esc(row.unit)}
                            </strong>
                        </div>
                    </div>

                    <div class="limit-usage">
                        <span>Uso da cota</span>

                        <strong id="aql-use-${row.id}">
                            ${Math.round(used)}% entregue
                        </strong>
                    </div>

                    <div
                        class="limit-meter"
                        id="aql-use-meter-${row.id}"
                        role="progressbar"
                        aria-label="Uso da cota de ${esc(row.name)}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="${Math.round(used)}"
                    >
                        <span style="width:${used}%"></span>
                    </div>

                    <div class="limit-metric">
                        <span>Valor planejado desta cota</span>

                        <strong id="aql-value-${row.id}">
                            ${money(
                                row.quantity
                                * row.price
                            )}
                        </strong>
                    </div>

                    ${canManage
                        ? `
                            <div class="limit-editor">
                                <div class="limit-editor-head">
                                    <strong>
                                        Editando cota máxima
                                    </strong>

                                    <span>
                                        Mínimo:
                                        ${qty(row.delivered)}
                                        ${esc(row.unit)}
                                    </span>
                                </div>

                                <div class="limit-editor-fields">
                                    <label>
                                        Ajuste rápido
                                        <input
                                            class="limit-slider"
                                            id="aql-slider-${row.id}"
                                            type="range"
                                            min="${row.delivered}"
                                            max="${sliderMaximum(row)}"
                                            step=".001"
                                            value="${row.quantity}"
                                            ${editing ? '' : 'disabled'}
                                            oninput="aqlQuantity(
                                                ${row.id},
                                                this.value,
                                                'slider'
                                            )"
                                        >
                                    </label>

                                    <label>
                                        Cota máxima (${esc(row.unit)})
                                        <input
                                            class="limits-control"
                                            id="aql-input-${row.id}"
                                            type="number"
                                            min="${row.delivered}"
                                            ${Number.isFinite(
                                                allowedMaximum(row)
                                            )
                                                ? `max="${allowedMaximum(row)}"`
                                                : ''}
                                            step=".001"
                                            value="${row.quantity}"
                                            ${editing ? '' : 'disabled'}
                                            oninput="aqlQuantity(
                                                ${row.id},
                                                this.value,
                                                'input'
                                            )"
                                            onblur="aqlCommitQuantity(
                                                ${row.id}
                                            )"
                                        >
                                    </label>
                                </div>
                            </div>
                        `
                        : ''}

                    <div
                        class="limit-message"
                        id="aql-message-${row.id}"
                    >
                        ${availability(row)}
                    </div>
                </div>
            </article>
        `;
    }

    const availability = row => {
        const max =
            allowedMaximum(row);

        if (row.projectMaximum === null) {
            return Number.isFinite(max)
                ? (
                    `Este produto não possui meta de quantidade no projeto. `
                    + `Pelo teto financeiro atual, a cota pode chegar a `
                    + `${qty(max)} ${row.unit}.`
                )
                : (
                    'Este produto não possui meta de quantidade '
                    + 'nem teto financeiro definido.'
                );
        }

        return (
            `Meta do projeto: `
            + `${qty(row.projectMaximum)} ${row.unit}. `
            + `Outros associados já utilizam `
            + `${qty(row.allocatedOthers)} ${row.unit}. `
            + `Máximo disponível agora: `
            + `${qty(max)} ${row.unit}.`
        );
    };

    function refresh(note = '') {
        let invalid = false;

        state.rows.forEach(row => {
            const error =
                validation(row);

            invalid ||= Boolean(error);

            const max =
                allowedMaximum(row);

            const used =
                row.quantity > 0
                    ? Math.min(
                        100,
                        row.delivered
                        / row.quantity
                        * 100
                    )
                    : 0;

            const cardEl =
                document.getElementById(
                    `produto-${row.id}`
                );

            cardEl?.classList.toggle(
                'is-invalid',
                Boolean(error)
            );

            const input =
                document.getElementById(
                    `aql-input-${row.id}`
                );

            const slider =
                document.getElementById(
                    `aql-slider-${row.id}`
                );

            if (input) {
                if (Number.isFinite(max)) {
                    input.max = String(max);
                } else {
                    input.removeAttribute('max');
                }
            }

            if (slider) {
                slider.max =
                    String(sliderMaximum(row));

                if (
                    Number.isFinite(row.quantity)
                ) {
                    slider.value =
                        String(
                            Math.min(
                                row.quantity,
                                sliderMaximum(row)
                            )
                        );
                }
            }

            const set = (
                id,
                value
            ) => {
                const element =
                    document.getElementById(id);

                if (element) {
                    element.textContent = value;
                }
            };

            set(
                `aql-quota-${row.id}`,
                `${qty(row.quantity)} ${row.unit}`
            );

            set(
                `aql-balance-${row.id}`,
                `${qty(
                    Math.max(
                        0,
                        row.quantity
                        - row.delivered
                    )
                )} ${row.unit}`
            );

            set(
                `aql-value-${row.id}`,
                money(
                    row.quantity
                    * row.price
                )
            );

            set(
                `aql-use-${row.id}`,
                `${Math.round(used)}% entregue`
            );

            const meter =
                document.querySelector(
                    `#aql-use-meter-${row.id} span`
                );

            if (meter) {
                meter.style.width =
                    `${used}%`;

                meter
                    .parentElement
                    ?.setAttribute(
                        'aria-valuenow',
                        String(Math.round(used))
                    );
            }

            const message =
                document.getElementById(
                    `aql-message-${row.id}`
                );

            if (message) {
                message.textContent =
                    error
                    || availability(row);

                message.classList.toggle(
                    'error',
                    Boolean(error)
                );
            }
        });

        const total =
            planned(null);

        const limit =
            ceiling();

        const percent =
            limit
            && limit > 0
                ? total / limit * 100
                : 0;

        document
            .getElementById('aql-planned')
            .textContent =
                money(total);

        document
            .getElementById('aql-remaining')
            .textContent =
                limit === null
                    ? 'Sem teto'
                    : money(
                        Math.max(
                            0,
                            limit - total
                        )
                    );

        document
            .getElementById('aql-ceiling-display')
            .textContent =
                limit === null
                    ? 'Sem teto'
                    : money(limit);

        const budgetMeter =
            document.getElementById(
                'aql-budget-meter'
            );

        budgetMeter.classList.toggle(
            'warning',
            percent >= 80
            && percent < 100
        );

        budgetMeter.classList.toggle(
            'danger',
            percent >= 100
        );

        budgetMeter
            .querySelector('span')
            .style.width =
                `${Math.min(100, percent)}%`;

        budgetMeter.setAttribute(
            'aria-valuenow',
            String(
                Math.min(
                    100,
                    Math.round(percent)
                )
            )
        );

        const budgetLabel =
            document.getElementById(
                'aql-budget-label'
            );

        if (budgetLabel) {
            budgetLabel.textContent =
                limit === null
                    ? 'Sem teto financeiro'
                    : `${Math.round(percent)}% do teto`;
        }

        const budgetHelper =
            document.getElementById(
                'aql-budget-helper'
            );

        if (budgetHelper) {
            if (limit === null) {
                budgetHelper.textContent =
                    'O valor planejado é apenas informativo, '
                    + 'pois este associado não possui teto financeiro.';
            } else if (total > limit + .005) {
                budgetHelper.textContent =
                    'As cotas ultrapassam o teto financeiro. '
                    + 'Reduza uma ou mais cotas antes de salvar.';
            } else if (percent >= 80) {
                budgetHelper.textContent =
                    'O planejamento está próximo do teto financeiro. '
                    + 'Confira os valores antes de salvar.';
            } else {
                budgetHelper.textContent =
                    'O planejamento está dentro do teto financeiro disponível.';
            }
        }

        const save =
            document.getElementById(
                'aql-save-all'
            );

        const changes =
            changedCount();

        if (save) {
            save.disabled =
                state.busy
                || !changed()
                || invalid
                || total
                    > (limit ?? Infinity)
                    + .005;

            save.innerHTML =
                changes > 0
                    ? `
                        <i data-lucide="save"></i>
                        Salvar ${changes}
                        ${changes === 1
                            ? 'alteração'
                            : 'alterações'}
                    `
                    : `
                        <i data-lucide="check"></i>
                        Tudo salvo
                    `;
        }

        const feedback =
            document.getElementById(
                'aql-feedback'
            );

        if (feedback) {
            const hasError =
                invalid
                || total
                    > (limit ?? Infinity)
                    + .005;

            if (note) {
                feedback.textContent = note;
            } else if (hasError) {
                feedback.textContent =
                    'Existem valores que precisam ser corrigidos antes de salvar.';
            } else if (changes > 0) {
                feedback.textContent =
                    `${changes} `
                    + (
                        changes === 1
                            ? 'alteração ainda não foi salva.'
                            : 'alterações ainda não foram salvas.'
                    );
            } else {
                feedback.textContent =
                    'Todas as alterações estão salvas.';
            }

            feedback.classList.toggle(
                'error',
                hasError
            );

            feedback.classList.toggle(
                'changed',
                !hasError
                && changes > 0
            );

            feedback.classList.toggle(
                'saved',
                !hasError
                && changes === 0
            );
        }

        icons();
    }

    function renderPicker() {
        const picker =
            document.getElementById(
                'aql-picker'
            );

        if (!picker) {
            return;
        }

        const term =
            (
                document
                    .getElementById(
                        'aql-search'
                    )
                    ?.value
                || ''
            )
                .trim()
                .toLocaleLowerCase('pt-BR');

        const products =
            state.products.filter(
                product =>
                    !state.rows.has(
                        String(product.id)
                    )
                    && (
                        !term
                        || String(product.name)
                            .toLocaleLowerCase('pt-BR')
                            .includes(term)
                    )
            );

        picker.innerHTML =
            products.length
                ? products
                    .slice(0, 80)
                    .map(product => {
                        const preview =
                            rowFrom(product);

                        const max =
                            allowedMaximum(preview);

                        const unavailable =
                            max
                            < Math.max(
                                preview.delivered,
                                .001
                            )
                            - .000001;

                        return `
                            <button
                                class="limits-option"
                                type="button"
                                ${unavailable
                                    ? 'disabled'
                                    : ''}
                                onclick="aqlAdd(
                                    ${Number(product.id)}
                                )"
                            >
                                <span
                                    class="limits-option-icon"
                                    aria-hidden="true"
                                >
                                    <i data-lucide="package"></i>
                                </span>

                                <span class="limits-option-copy">
                                    <strong>
                                        ${esc(product.name)}
                                    </strong>

                                    <span>
                                        ${money(product.price)}
                                        por
                                        ${esc(
                                            product.unit
                                            || 'unidade'
                                        )}
                                    </span>
                                </span>

                                <span class="limits-option-availability">
                                    ${unavailable
                                        ? 'Sem saldo'
                                        : Number.isFinite(max)
                                            ? `${qty(max)} disponível`
                                            : 'Sem limite'}
                                </span>
                            </button>
                        `;
                    })
                    .join('')
                : `
                    <div class="limits-empty">
                        Nenhum produto disponível para esta busca.
                    </div>
                `;

        icons();
    }

    window.aqlEdit = id => {
        const key =
            String(id);

        state.editing =
            state.editing === key
                ? null
                : key;

        render();

        if (state.editing === key) {
            document
                .getElementById(
                    `aql-input-${id}`
                )
                ?.focus();
        }
    };

    window.aqlQuantity = (
        id,
        value,
        source
    ) => {
        const row =
            state.rows.get(String(id));

        if (!row) {
            return;
        }

        if (source === 'input') {
            row.quantity =
                value === ''
                    ? NaN
                    : Number(
                        String(value)
                            .replace(',', '.')
                    );

            refresh();
            return;
        }

        const parsed =
            Number(
                String(value)
                    .replace(',', '.')
            );

        if (!Number.isFinite(parsed)) {
            return;
        }

        const max =
            allowedMaximum(row);

        row.quantity =
            Math.max(
                row.delivered,
                Math.min(
                    parsed,
                    max
                )
            );

        const input =
            document.getElementById(
                `aql-input-${id}`
            );

        if (input) {
            input.value =
                String(row.quantity);
        }

        const slider =
            document.getElementById(
                `aql-slider-${id}`
            );

        if (slider) {
            slider.value =
                String(row.quantity);
        }

        refresh(
            parsed > max + .000001
                ? (
                    'A cota foi limitada pelo saldo financeiro '
                    + 'ou pela disponibilidade do produto.'
                )
                : ''
        );
    };

    window.aqlCommitQuantity = id => {
        const row =
            state.rows.get(String(id));

        if (
            !row
            || !Number.isFinite(row.quantity)
        ) {
            return;
        }

        const max =
            allowedMaximum(row);

        row.quantity =
            Math.max(
                row.delivered,
                Math.min(
                    row.quantity,
                    max
                )
            );

        const input =
            document.getElementById(
                `aql-input-${id}`
            );

        if (input) {
            input.value =
                String(row.quantity);
        }

        const slider =
            document.getElementById(
                `aql-slider-${id}`
            );

        if (slider) {
            slider.value =
                String(row.quantity);
        }

        refresh();
    };

    window.aqlAdd = id => {
        const product =
            state.products.find(
                item =>
                    String(item.id)
                    === String(id)
            );

        if (!product) {
            return;
        }

        const row =
            rowFrom(product);

        const max =
            allowedMaximum(row);

        if (
            max
            < row.quantity - .000001
        ) {
            return;
        }

        state.rows.set(
            String(id),
            row
        );

        state.editing =
            String(id);

        const picker =
            document.getElementById(
                'aql-picker'
            );

        if (picker) {
            picker.hidden = true;
        }

        render();

        document
            .getElementById(
                `produto-${id}`
            )
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });

        document
            .getElementById(
                `aql-input-${id}`
            )
            ?.focus();
    };

    window.aqlRemove = id => {
        const row =
            state.rows.get(String(id));

        if (!row) {
            return;
        }

        if (row.isNew) {
            state.rows.delete(
                String(id)
            );

            render();
            return;
        }

        state.removal = row;

        document
            .getElementById(
                'aql-remove-message'
            )
            .textContent =
                `O produto “${row.name}” deixará de possuir `
                + `uma cota definida para este associado. `
                + `Entregas já registradas serão preservadas.`;

        document
            .getElementById(
                'aql-remove-dialog'
            )
            .showModal();
    };

    document
        .getElementById(
            'aql-remove-cancel'
        )
        .addEventListener(
            'click',
            () =>
                document
                    .getElementById(
                        'aql-remove-dialog'
                    )
                    .close()
        );

    document
        .getElementById(
            'aql-remove-confirm'
        )
        .addEventListener(
            'click',
            async event => {
                const row =
                    state.removal;

                if (!row) {
                    return;
                }

                const button =
                    event.currentTarget;

                try {
                    button.disabled = true;

                    const response =
                        await json(
                            row.deleteUrl,
                            {
                                method: 'DELETE',
                                body: '{}',
                            }
                        );

                    document
                        .getElementById(
                            'aql-remove-dialog'
                        )
                        .close();

                    hydrate(
                        response.data,
                        state.products
                    );

                    render();

                    refresh(
                        'Produto removido com sucesso.'
                    );
                } catch (error) {
                    refresh(error.message);
                } finally {
                    button.disabled = false;
                }
            }
        );

    document
        .getElementById(
            'aql-toggle-products'
        )
        ?.addEventListener(
            'click',
            event => {
                const picker =
                    document.getElementById(
                        'aql-picker'
                    );

                picker.hidden =
                    !picker.hidden;

                event.currentTarget.innerHTML =
                    picker.hidden
                        ? `
                            <i data-lucide="package-plus"></i>
                            Adicionar produto
                        `
                        : `
                            <i data-lucide="x"></i>
                            Fechar seleção
                        `;

                if (!picker.hidden) {
                    document
                        .getElementById(
                            'aql-search'
                        )
                        .focus();
                }

                icons();
            }
        );

    document
        .getElementById(
            'aql-search'
        )
        ?.addEventListener(
            'input',
            renderPicker
        );

    document
        .getElementById(
            'aql-save-all'
        )
        ?.addEventListener(
            'click',
            async () => {
                const changes =
                    [...state.rows]
                        .filter(
                            ([key, row]) =>
                                !state.originals.has(key)
                                || Math.abs(
                                    row.quantity
                                    - state.originals.get(key)
                                ) > .000001
                        )
                        .map(
                            ([, row]) => ({
                                product_id: row.id,
                                max_quantity:
                                    Number(
                                        row.quantity
                                            .toFixed(3)
                                    ),
                            })
                        );

                if (!changes.length) {
                    return;
                }

                let feedback =
                    'Alterações salvas com sucesso.';

                try {
                    state.busy = true;

                    refresh(
                        'Salvando alterações...'
                    );

                    const response =
                        await json(
                            state.batchUrl,
                            {
                                method: 'PUT',
                                body:
                                    JSON.stringify({
                                        limits: changes,
                                    }),
                            }
                        );

                    hydrate(
                        response,
                        state.products
                    );

                    render();
                } catch (error) {
                    feedback =
                        error.message;
                } finally {
                    state.busy = false;
                    refresh(feedback);

                    const feedbackEl =
                        document.getElementById(
                            'aql-feedback'
                        );

                    feedbackEl?.classList.toggle(
                        'error',
                        feedback
                        !== 'Alterações salvas com sucesso.'
                    );
                }
            }
        );

    document
        .getElementById(
            'aql-financial-save'
        )
        ?.addEventListener(
            'click',
            async event => {
                const button =
                    event.currentTarget;

                try {
                    button.disabled = true;

                    const value =
                        document
                            .getElementById(
                                'aql-financial-input'
                            )
                            .value;

                    await json(
                        root.dataset.financialUrl,
                        {
                            method: 'PUT',

                            body:
                                JSON.stringify({
                                    financial_limit:
                                        value === ''
                                            ? null
                                            : value,
                                }),
                        }
                    );

                    state.summary.financial_limit =
                        value === ''
                            ? null
                            : Number(value);

                    refresh(
                        'Teto financeiro atualizado.'
                    );
                } catch (error) {
                    refresh(error.message);

                    document
                        .getElementById(
                            'aql-feedback'
                        )
                        ?.classList.add(
                            'error'
                        );
                } finally {
                    button.disabled = false;
                }
            }
        );

    Promise
        .all([
            json(root.dataset.limitsUrl),
            json(root.dataset.productsUrl),
        ])
        .then(
            ([limits, products]) => {
                hydrate(
                    limits,
                    products.data || []
                );

                render();

                const productId =
                    window.location.hash
                        .match(
                            /^#produto-(\d+)$/
                        )?.[1];

                if (
                    productId
                    && state.rows.has(
                        String(productId)
                    )
                ) {
                    if (canManage) {
                        window.aqlEdit(
                            Number(productId)
                        );
                    }

                    document
                        .getElementById(
                            `produto-${productId}`
                        )
                        ?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                        });
                }
            }
        )
        .catch(error => {
            document
                .getElementById(
                    'aql-grid'
                )
                .innerHTML =
                    `
                        <div class="limits-empty">
                            ${esc(error.message)}
                        </div>
                    `;
        });

    window.addEventListener(
        'beforeunload',
        event => {
            if (
                !changed()
                || state.busy
            ) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        }
    );

    icons();
})();
</script>
@endsection