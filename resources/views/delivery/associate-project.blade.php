@extends('layouts.bento')

@section('title', 'Associado no projeto')
@section('page-title', 'Associado no projeto')
@section('page-subtitle', $project->title)
@section('user-role', 'Gestão de entregas')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', request()->route('tenant'));
@endphp

<x-delivery.notes-modal />

@section('content')
@php
    $tenantSlug = request()->route('tenant') instanceof \App\Models\Tenant
        ? request()->route('tenant')->slug
        : request()->route('tenant');

    $associateCode = $associate->member_code
        ?: $associate->registration_number
        ?: 'Sem código';

    $associateLocation = $associate->district
        ?: $associate->city
        ?: 'Localidade não informada';

    $projectPeriod = collect([
        $project->start_date?->format('d/m/Y'),
        $project->end_date?->format('d/m/Y'),
    ])->filter()->implode(' a ');

    $associateLimitsUrl = route('delivery.projects.associates.limits.index', [
        'tenant' => $tenantSlug,
        'project' => $project->id,
        'associate' => $associate->id,
    ]);

    $associateSimulatorUrl = route('delivery.projects.associates.simulator', [
        'tenant' => $tenantSlug,
        'project' => $project->id,
        'associate' => $associate->id,
    ]);
@endphp

<style>
    .ap-shell,
    .ap-modal,
    .ap-toast-root {
        --ap-primary: var(--color-primary, #22c55e);
        --ap-primary-dark: var(--color-primary-dark, #16a34a);
        --ap-primary-deep: var(--color-primary-deep, #15803d);

        --ap-green: #168a4d;
        --ap-green-soft: #eaf8ef;

        --ap-blue: #2563eb;
        --ap-blue-soft: #eef4ff;

        --ap-sky: #0284c7;
        --ap-sky-soft: #edf8fe;

        --ap-violet: #7c3aed;
        --ap-violet-soft: #f4f0ff;

        --ap-amber: #c87408;
        --ap-amber-soft: #fff7e8;

        --ap-red: #cf3f3f;
        --ap-red-soft: #fff0f0;

        --ap-slate: #64748b;
        --ap-slate-soft: #f1f5f9;

        --ap-surface: var(--color-surface, #fff);
        --ap-soft: var(--color-surface-soft, #f8faf9);
        --ap-muted: var(--color-surface-muted, #eef4f0);
        --ap-border: var(--color-border, #dce6df);
        --ap-border-strong: var(--color-border-strong, #c8d6cd);
        --ap-text: var(--color-text, #102018);
        --ap-secondary: var(--color-text-secondary, #52645a);
        --ap-faded: var(--color-text-muted, #809087);
    }

    .ap-shell {
        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--ap-text);
    }

    .ap-shell *,
    .ap-shell *::before,
    .ap-shell *::after,
    .ap-modal *,
    .ap-modal *::before,
    .ap-modal *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       CABEÇALHO CONTEXTUAL
       ========================================================= */

    .ap-hero {
        --hero-tone: var(--ap-blue);
        --hero-soft: var(--ap-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            auto
            minmax(0, 1fr)
            auto;
        gap: .62rem;
        align-items: center;
        min-height: 72px;
        padding: .7rem .76rem;
        overflow: hidden;
        border: 1px solid var(--ap-border);
        border-radius: 15px;
        background:
            radial-gradient(
                circle at 100% 0,
                color-mix(
                    in srgb,
                    var(--hero-tone) 8%,
                    transparent
                ),
                transparent 17rem
            ),
            linear-gradient(
                180deg,
                var(--ap-soft),
                var(--ap-surface)
            );
        box-shadow: var(--shadow-sm);
    }

    .ap-hero-wave {
        display: none;
    }

    .ap-back,
    .ap-context-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .ap-back {
        border: 1px solid var(--ap-border);
        background: #fff;
        color: var(--ap-secondary);
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .ap-back:hover,
    .ap-back:focus-visible {
        border-color: rgba(37, 99, 235, .24);
        background: var(--ap-blue-soft);
        color: var(--ap-blue);
        outline: none;
        transform: translateX(-1px);
    }

    .ap-context-icon {
        background: var(--hero-soft);
        color: var(--hero-tone);
    }

    .ap-back > i,
    .ap-back > svg,
    .ap-context-icon > i,
    .ap-context-icon > svg {
        width: 17px;
        height: 17px;
    }

    .ap-hero-copy {
        min-width: 0;
    }

    .ap-title {
        margin: 0;
        color: var(--ap-text);
        font-size: clamp(1rem, 2vw, 1.18rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.28;
        overflow-wrap: anywhere;
    }

    .ap-meta-row {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .48rem;
        margin-top: .16rem;
        color: var(--ap-faded);
        font-size: .72rem;
        line-height: 1.4;
    }

    .ap-meta-row > span {
        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            minmax(0, auto);
        gap: .24rem;
        align-items: center;
    }

    .ap-meta-row > span > i,
    .ap-meta-row > span > svg {
        width: 13px;
        height: 13px;
    }

    .ap-meta-text {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ap-hero-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .36rem;
        align-items: center;
    }

    .ap-hero-btn {
        display: grid;
        min-height: 40px;
        grid-template-columns:
            auto
            auto;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .46rem .62rem;
        border: 1px solid var(--ap-border-strong);
        border-radius: 10px;
        background: #fff;
        color: var(--ap-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .72rem;
        font-weight: 780;
        text-decoration: none;
        white-space: nowrap;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .ap-hero-btn > i,
    .ap-hero-btn > svg {
        width: 15px;
        height: 15px;
    }

    .ap-hero-btn:hover,
    .ap-hero-btn:focus-visible {
        outline: none;
        transform: translateY(-1px);
    }

    .ap-hero-btn.delivery {
        border-color: rgba(200, 116, 8, .18);
        background: var(--ap-amber-soft);
        color: var(--ap-amber);
    }

    .ap-hero-btn.limits {
        border-color: rgba(124, 58, 237, .18);
        background: var(--ap-violet-soft);
        color: var(--ap-violet);
    }

    .ap-hero-btn.simulator {
        border-color: rgba(37, 99, 235, .18);
        background: var(--ap-blue-soft);
        color: var(--ap-blue);
    }

    /* =========================================================
       ABAS
       ========================================================= */

    .ap-tabs-wrap {
        position: sticky;
        z-index: 28;
        top: .25rem;
        min-width: 0;
    }

    .ap-tabs {
        display: grid;
        min-width: 0;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .32rem;
        padding: .46rem;
        overflow-x: auto;
        border: 1px solid var(--ap-border);
        border-radius: 13px;
        background: rgba(255, 255, 255, .97);
        box-shadow: var(--shadow-sm);
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .ap-tabs::-webkit-scrollbar {
        display: none;
    }

    .ap-tab {
        --tab-tone: var(--ap-slate);
        --tab-soft: var(--ap-slate-soft);

        display: grid;
        min-width: max-content;
        min-height: 40px;
        grid-template-columns:
            auto
            auto;
        gap: .34rem;
        align-items: center;
        justify-content: center;
        padding: .42rem .58rem;
        border: 1px solid transparent;
        border-radius: 10px;
        background: transparent;
        color: var(--ap-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .73rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .ap-tab[data-section="summary"] {
        --tab-tone: var(--ap-blue);
        --tab-soft: var(--ap-blue-soft);
    }

    .ap-tab[data-section="limits"] {
        --tab-tone: var(--ap-violet);
        --tab-soft: var(--ap-violet-soft);
    }

    .ap-tab[data-section="deliveries"] {
        --tab-tone: var(--ap-amber);
        --tab-soft: var(--ap-amber-soft);
    }

    .ap-tab[data-section="distributions"] {
        --tab-tone: var(--ap-sky);
        --tab-soft: var(--ap-sky-soft);
    }

    .ap-tab[data-section="receipts"] {
        --tab-tone: var(--ap-slate);
        --tab-soft: var(--ap-slate-soft);
    }

    .ap-tab[data-section="payments"] {
        --tab-tone: var(--ap-green);
        --tab-soft: var(--ap-green-soft);
    }

    .ap-tab[data-section="history"] {
        --tab-tone: #475569;
        --tab-soft: #f1f5f9;
    }

    .ap-tab > i,
    .ap-tab > svg {
        width: 15px;
        height: 15px;
        color: var(--tab-tone);
    }

    .ap-tab:hover,
    .ap-tab:focus-visible,
    .ap-tab.active {
        border-color:
            color-mix(
                in srgb,
                var(--tab-tone) 16%,
                var(--ap-border)
            );
        background: var(--tab-soft);
        color: var(--tab-tone);
        outline: none;
    }

    .ap-content {
        min-width: 0;
        min-height: 280px;
    }

    /* =========================================================
       SUPERFÍCIES DE SEÇÃO
       ========================================================= */

    .ap-overview,
    .ap-section-card {
        --section-tone: var(--ap-blue);
        --section-soft: var(--ap-blue-soft);

        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ap-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: var(--shadow-sm);
    }

    .ap-section-card.tone-violet {
        --section-tone: var(--ap-violet);
        --section-soft: var(--ap-violet-soft);
    }

    .ap-section-card.tone-amber {
        --section-tone: var(--ap-amber);
        --section-soft: var(--ap-amber-soft);
    }

    .ap-section-card.tone-sky {
        --section-tone: var(--ap-sky);
        --section-soft: var(--ap-sky-soft);
    }

    .ap-section-card.tone-slate {
        --section-tone: var(--ap-slate);
        --section-soft: var(--ap-slate-soft);
    }

    .ap-section-card.tone-green {
        --section-tone: var(--ap-green);
        --section-soft: var(--ap-green-soft);
    }

    .ap-overview-head,
    .ap-section-head {
        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            minmax(0, 1fr)
            auto;
        gap: .58rem;
        align-items: center;
        min-height: 64px;
        padding: .68rem .76rem;
        border-bottom: 1px solid var(--ap-border);
        background:
            linear-gradient(
                180deg,
                var(--ap-soft),
                #fff
            );
    }

    .ap-overview-head-icon,
    .ap-section-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
        background: var(--section-soft);
        color: var(--section-tone);
    }

    .ap-overview-head-icon {
        background: var(--ap-blue-soft);
        color: var(--ap-blue);
    }

    .ap-overview-head-icon > i,
    .ap-overview-head-icon > svg,
    .ap-section-icon > i,
    .ap-section-icon > svg {
        width: 18px;
        height: 18px;
    }

    .ap-section-head-copy,
    .ap-overview-head-copy {
        min-width: 0;
    }

    .ap-overview-head h2,
    .ap-overview-head p {
        margin: 0;
    }

    .ap-overview-head h2,
    .ap-section-title {
        color: var(--ap-text);
        font-size: .95rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .ap-overview-head p,
    .ap-section-subtitle {
        margin-top: .08rem;
        color: var(--ap-faded);
        font-size: .74rem;
        line-height: 1.42;
    }

    .ap-section-head-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .32rem;
        align-items: center;
    }

    /* =========================================================
       RESUMO
       ========================================================= */

    .ap-overview-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(290px, .92fr)
            minmax(0, 1.08fr);
    }

    .ap-financial-hero {
        --financial-tone: var(--ap-blue);
        --financial-soft: var(--ap-blue-soft);

        display: grid;
        min-width: 0;
        min-height: 220px;
        align-content: center;
        padding: 1rem;
        background:
            radial-gradient(
                circle at 100% 0,
                color-mix(
                    in srgb,
                    var(--financial-tone) 10%,
                    transparent
                ),
                transparent 16rem
            ),
            linear-gradient(
                135deg,
                #fff,
                var(--financial-soft)
            );
    }

    .ap-financial-hero.warning {
        --financial-tone: var(--ap-amber);
        --financial-soft: var(--ap-amber-soft);
    }

    .ap-financial-hero.danger {
        --financial-tone: var(--ap-red);
        --financial-soft: var(--ap-red-soft);
    }

    .ap-financial-label {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns:
            auto
            auto;
        gap: .32rem;
        align-items: center;
        color: var(--financial-tone);
        font-size: .74rem;
        font-weight: 790;
    }

    .ap-financial-label > i,
    .ap-financial-label > svg {
        width: 16px;
        height: 16px;
    }

    .ap-financial-value {
        margin-top: .34rem;
        color: var(--ap-text);
        font-size: clamp(1.75rem, 4vw, 2.4rem);
        font-weight: 875;
        letter-spacing: -.045em;
        line-height: 1;
        overflow-wrap: anywhere;
    }

    .ap-financial-helper {
        max-width: 410px;
        margin-top: .42rem;
        color: var(--ap-secondary);
        font-size: .77rem;
        line-height: 1.5;
    }

    .ap-financial-facts {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: .42rem;
        margin-top: .72rem;
    }

    .ap-financial-fact {
        min-width: 0;
        padding: .46rem .5rem;
        border-radius: 9px;
        background: rgba(255, 255, 255, .62);
    }

    .ap-financial-fact span,
    .ap-financial-fact strong {
        display: block;
    }

    .ap-financial-fact span {
        color: var(--ap-faded);
        font-size: .67rem;
        font-weight: 680;
    }

    .ap-financial-fact strong {
        margin-top: .04rem;
        color: var(--ap-text);
        font-size: .75rem;
        font-weight: 820;
        overflow-wrap: anywhere;
    }

    .ap-overview-list {
        display: grid;
        min-width: 0;
        align-content: center;
        padding: .72rem;
        background: #fff;
    }

    .ap-overview-row {
        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            minmax(0, 1fr)
            auto;
        gap: .5rem;
        align-items: center;
        min-height: 56px;
        padding: .42rem .02rem;
    }

    .ap-overview-row + .ap-overview-row {
        border-top: 1px solid var(--ap-border);
    }

    .ap-overview-row-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
    }

    .ap-overview-row.participation .ap-overview-row-icon {
        background: var(--ap-violet-soft);
        color: var(--ap-violet);
    }

    .ap-overview-row.received .ap-overview-row-icon {
        background: var(--ap-amber-soft);
        color: var(--ap-amber);
    }

    .ap-overview-row.distributed .ap-overview-row-icon {
        background: var(--ap-sky-soft);
        color: var(--ap-sky);
    }

    .ap-overview-row.pending .ap-overview-row-icon {
        background: var(--ap-amber-soft);
        color: var(--ap-amber);
    }

    .ap-overview-row.receivable .ap-overview-row-icon {
        background: var(--ap-blue-soft);
        color: var(--ap-blue);
    }

    .ap-overview-row.receipts .ap-overview-row-icon {
        background: var(--ap-slate-soft);
        color: var(--ap-slate);
    }

    .ap-overview-row-icon > i,
    .ap-overview-row-icon > svg {
        width: 15px;
        height: 15px;
    }

    .ap-overview-row-copy {
        min-width: 0;
    }

    .ap-overview-row-copy span,
    .ap-overview-row-copy strong {
        display: block;
    }

    .ap-overview-row-copy span {
        color: var(--ap-faded);
        font-size: .68rem;
        font-weight: 680;
    }

    .ap-overview-row-copy strong {
        margin-top: .04rem;
        color: var(--ap-text);
        font-size: .75rem;
        font-weight: 810;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .ap-overview-row-value {
        color: var(--ap-text);
        font-size: .79rem;
        font-weight: 840;
        text-align: right;
        white-space: nowrap;
    }

    /* =========================================================
       BARRAS DE PROGRESSO
       ========================================================= */

    .ap-progress {
        --progress-tone: var(--ap-blue);
        --progress-soft: var(--ap-blue-soft);

        height: 8px;
        margin-top: .52rem;
        overflow: hidden;
        border-radius: 999px;
        background: var(--progress-soft);
    }

    .ap-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                color-mix(
                    in srgb,
                    var(--progress-tone) 52%,
                    #fff
                ),
                var(--progress-tone)
            );
    }

    .ap-progress.warning {
        --progress-tone: var(--ap-amber);
        --progress-soft: var(--ap-amber-soft);
    }

    .ap-progress.danger {
        --progress-tone: var(--ap-red);
        --progress-soft: var(--ap-red-soft);
    }

    /* =========================================================
       BOTÕES E CAMPOS
       ========================================================= */

    .ap-btn {
        display: grid;
        min-height: 38px;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .43rem .58rem;
        border: 1px solid var(--ap-border-strong);
        border-radius: 9px;
        background: #fff;
        color: var(--ap-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 780;
        text-decoration: none;
        white-space: nowrap;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease,
            box-shadow 150ms ease;
    }

    .ap-btn > i,
    .ap-btn > svg {
        width: 14px;
        height: 14px;
    }

    .ap-btn:hover:not(:disabled),
    .ap-btn:focus-visible:not(:disabled) {
        outline: none;
        transform: translateY(-1px);
    }

    .ap-btn.primary,
    .ap-btn.blue {
        border-color: rgba(37, 99, 235, .18);
        background: var(--ap-blue-soft);
        color: var(--ap-blue);
    }

    .ap-btn.violet {
        border-color: rgba(124, 58, 237, .18);
        background: var(--ap-violet-soft);
        color: var(--ap-violet);
    }

    .ap-btn.amber,
    .ap-btn.warning {
        border-color: rgba(200, 116, 8, .18);
        background: var(--ap-amber-soft);
        color: #92400e;
    }

    .ap-btn.sky {
        border-color: rgba(2, 132, 199, .18);
        background: var(--ap-sky-soft);
        color: var(--ap-sky);
    }

    .ap-btn.success {
        border-color: rgba(22, 138, 77, .18);
        background: var(--ap-green-soft);
        color: var(--ap-green);
    }

    .ap-btn.slate {
        border-color: rgba(100, 116, 139, .18);
        background: var(--ap-slate-soft);
        color: #475569;
    }

    .ap-btn.danger {
        border-color: rgba(207, 63, 63, .18);
        background: var(--ap-red-soft);
        color: #991b1b;
    }

    .ap-btn:disabled {
        cursor: not-allowed;
        opacity: .48;
        transform: none;
    }

    .ap-input,
    .ap-select,
    .ap-field input,
    .ap-field select,
    .ap-field textarea,
    .ap-quota-input {
        width: 100%;
        min-height: 42px;
        padding: .5rem .62rem;
        border: 1px solid var(--ap-border-strong);
        border-radius: 9px;
        outline: none;
        background: #fff;
        color: var(--ap-text);
        font: inherit;
        font-size: .75rem;
    }

    .ap-input:focus,
    .ap-select:focus,
    .ap-field input:focus,
    .ap-field select:focus,
    .ap-field textarea:focus,
    .ap-quota-input:focus {
        border-color: var(--ap-blue);
        box-shadow:
            0 0 0 3px rgba(37, 99, 235, .08);
    }

    .ap-select {
        min-width: 165px;
    }

    .ap-toolbar {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(220px, 1fr)
            minmax(165px, 230px)
            auto;
        gap: .48rem;
        align-items: center;
        padding: .58rem .7rem;
        border-bottom: 1px solid var(--ap-border);
        background: var(--ap-soft);
    }

    .ap-toolbar .ap-actions {
        justify-content: end;
    }

    .ap-search-wrap {
        position: relative;
        min-width: 0;
    }

    .ap-search-icon {
        position: absolute;
        top: 50%;
        left: .66rem;
        width: 15px;
        height: 15px;
        color: var(--ap-faded);
        pointer-events: none;
        transform: translateY(-50%);
    }

    .ap-input {
        padding-left: 2rem;
    }

    .ap-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .3rem;
        align-items: center;
    }

    .delivery-note-trigger {
        display: grid;
        min-height: 34px;
        place-items: center;
        padding: .34rem .48rem;
        border: 1px solid var(--ap-border);
        border-radius: 9px;
        background: var(--ap-slate-soft);
        color: var(--ap-slate);
        cursor: pointer;
        font: inherit;
        font-size: .69rem;
        font-weight: 760;
    }

    /* =========================================================
       LIMITES — RESUMO E PRODUTOS
       ========================================================= */

    .ap-limit-summary {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(5, minmax(0, 1fr));
        overflow: hidden;
        border-bottom: 1px solid var(--ap-border);
        background: #fff;
    }

    .ap-limit-fact {
        --fact-tone: var(--ap-blue);
        --fact-soft: var(--ap-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
        min-height: 72px;
        padding: .5rem .56rem;
    }

    .ap-limit-fact + .ap-limit-fact {
        box-shadow:
            inset 1px 0 0 var(--ap-border);
    }

    .ap-limit-fact.violet {
        --fact-tone: var(--ap-violet);
        --fact-soft: var(--ap-violet-soft);
    }

    .ap-limit-fact.amber {
        --fact-tone: var(--ap-amber);
        --fact-soft: var(--ap-amber-soft);
    }

    .ap-limit-fact.sky {
        --fact-tone: var(--ap-sky);
        --fact-soft: var(--ap-sky-soft);
    }

    .ap-limit-fact.green {
        --fact-tone: var(--ap-green);
        --fact-soft: var(--ap-green-soft);
    }

    .ap-limit-fact-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 9px;
        background: var(--fact-soft);
        color: var(--fact-tone);
    }

    .ap-limit-fact-icon > i,
    .ap-limit-fact-icon > svg {
        width: 15px;
        height: 15px;
    }

    .ap-limit-fact-copy {
        min-width: 0;
    }

    .ap-limit-fact-copy span,
    .ap-limit-fact-copy strong {
        display: block;
    }

    .ap-limit-fact-copy span {
        color: var(--ap-faded);
        font-size: .65rem;
        font-weight: 680;
    }

    .ap-limit-fact-copy strong {
        margin-top: .04rem;
        color: var(--ap-text);
        font-size: .75rem;
        font-weight: 820;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .ap-limit-products {
        display: grid;
        min-width: 0;
        padding: .12rem .72rem .72rem;
    }

    .ap-limit-row {
        --row-tone: var(--ap-blue);
        --row-soft: var(--ap-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            minmax(180px, .8fr)
            minmax(0, 1.45fr)
            minmax(190px, .7fr)
            auto;
        gap: .58rem;
        align-items: center;
        padding: .68rem .02rem;
    }

    .ap-limit-row + .ap-limit-row {
        border-top: 1px solid var(--ap-border);
    }

    .tone-blue {
        --row-tone: var(--ap-blue);
        --row-soft: var(--ap-blue-soft);
        --quota-tone: var(--ap-blue);
        --quota-soft: var(--ap-blue-soft);
    }

    .tone-violet {
        --row-tone: var(--ap-violet);
        --row-soft: var(--ap-violet-soft);
        --quota-tone: var(--ap-violet);
        --quota-soft: var(--ap-violet-soft);
    }

    .tone-sky {
        --row-tone: var(--ap-sky);
        --row-soft: var(--ap-sky-soft);
        --quota-tone: var(--ap-sky);
        --quota-soft: var(--ap-sky-soft);
    }

    .tone-amber {
        --row-tone: var(--ap-amber);
        --row-soft: var(--ap-amber-soft);
        --quota-tone: var(--ap-amber);
        --quota-soft: var(--ap-amber-soft);
    }

    .tone-green {
        --row-tone: var(--ap-green);
        --row-soft: var(--ap-green-soft);
        --quota-tone: var(--ap-green);
        --quota-soft: var(--ap-green-soft);
    }

    .ap-limit-row-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 10px;
        background: var(--row-soft);
        color: var(--row-tone);
    }

    .ap-limit-row-icon > i,
    .ap-limit-row-icon > svg {
        width: 16px;
        height: 16px;
    }

    .ap-limit-row-copy {
        min-width: 0;
    }

    .ap-limit-row-copy strong,
    .ap-limit-row-copy span {
        display: block;
    }

    .ap-limit-row-copy strong {
        color: var(--ap-text);
        font-size: .81rem;
        font-weight: 820;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .ap-limit-row-copy span {
        margin-top: .05rem;
        color: var(--ap-faded);
        font-size: .68rem;
        line-height: 1.4;
    }

    .ap-limit-row-metrics {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        overflow: hidden;
        border-radius: 10px;
        background: var(--ap-soft);
    }

    .ap-limit-row-metric {
        min-width: 0;
        padding: .44rem .48rem;
    }

    .ap-limit-row-metric + .ap-limit-row-metric {
        box-shadow:
            inset 1px 0 0 var(--ap-border);
    }

    .ap-limit-row-metric span,
    .ap-limit-row-metric strong {
        display: block;
    }

    .ap-limit-row-metric span {
        color: var(--ap-faded);
        font-size: .63rem;
        font-weight: 680;
    }

    .ap-limit-row-metric strong {
        margin-top: .04rem;
        color: var(--ap-text);
        font-size: .71rem;
        font-weight: 810;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .ap-limit-row-metric.balance {
        background: var(--row-soft);
    }

    .ap-limit-row-metric.balance strong {
        color: var(--row-tone);
    }

    .ap-limit-row-use {
        min-width: 0;
    }

    .ap-limit-row-use-head {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .4rem;
        align-items: center;
        color: var(--ap-faded);
        font-size: .66rem;
        font-weight: 710;
    }

    .ap-limit-row-use-head strong {
        color: var(--row-tone);
        font-size: .68rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .ap-limit-row .ap-progress {
        --progress-tone: var(--row-tone);
        --progress-soft: var(--row-soft);
        margin-top: .32rem;
    }

    /* =========================================================
       TABELAS
       ========================================================= */

    .ap-table-wrap {
        width: 100%;
        overflow-x: auto;
        background: #fff;
        scrollbar-width: thin;
    }

    .ap-table {
        width: 100%;
        min-width: 860px;
        border-collapse: separate;
        border-spacing: 0;
        color: var(--ap-text);
        font-size: .74rem;
    }

    .ap-table th,
    .ap-table td {
        padding: .6rem .68rem;
        border-bottom: 1px solid var(--ap-border);
        text-align: left;
        vertical-align: middle;
        white-space: nowrap;
    }

    .ap-table th {
        background: #f6f9f7;
        color: var(--ap-secondary);
        font-size: .67rem;
        font-weight: 780;
        letter-spacing: .01em;
    }

    .ap-table tbody tr:hover td {
        background: #fbfdfc;
    }

    .ap-table tbody tr:last-child td {
        border-bottom: 0;
    }

    /* =========================================================
       BADGES
       ========================================================= */

    .ap-badge {
        display: grid;
        width: max-content;
        min-height: 25px;
        grid-template-columns:
            auto
            auto;
        gap: .23rem;
        align-items: center;
        padding: .18rem .35rem;
        border-radius: 999px;
        background: var(--ap-slate-soft);
        color: #475569;
        font-size: .64rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .ap-badge.approved,
    .ap-badge.paid,
    .ap-badge.active {
        background: var(--ap-green-soft);
        color: var(--ap-green);
    }

    .ap-badge.pending,
    .ap-badge.pending_payment,
    .ap-badge.partially_paid,
    .ap-badge.billed {
        background: var(--ap-amber-soft);
        color: #92400e;
    }

    .ap-badge.rejected,
    .ap-badge.obsolete,
    .ap-badge.cancelled,
    .ap-badge.blocked {
        background: var(--ap-red-soft);
        color: #991b1b;
    }

    .ap-badge > i,
    .ap-badge > svg {
        width: 12px;
        height: 12px;
    }

    /* =========================================================
       MOBILE LIST
       ========================================================= */

    .ap-mobile-list {
        display: none;
        min-width: 0;
        padding: .18rem .68rem .68rem;
    }

    .ap-mobile-card {
        min-width: 0;
        padding: .68rem .02rem;
    }

    .ap-mobile-card + .ap-mobile-card {
        border-top: 1px solid var(--ap-border);
    }

    .ap-mobile-card-head {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .52rem;
        align-items: start;
    }

    .ap-mobile-card-title {
        min-width: 0;
    }

    .ap-mobile-card-title strong,
    .ap-mobile-card-title span {
        display: block;
    }

    .ap-mobile-card-title strong {
        color: var(--ap-text);
        font-size: .82rem;
        font-weight: 820;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .ap-mobile-card-title span {
        margin-top: .08rem;
        color: var(--ap-faded);
        font-size: .69rem;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .ap-mobile-card-body {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: .36rem;
        margin-top: .48rem;
        padding: .48rem;
        border-radius: 10px;
        background: var(--section-soft, var(--ap-soft));
    }

    .ap-mobile-metric {
        min-width: 0;
    }

    .ap-mobile-metric span,
    .ap-mobile-metric strong {
        display: block;
    }

    .ap-mobile-metric span {
        color: var(--ap-faded);
        font-size: .65rem;
        font-weight: 680;
    }

    .ap-mobile-metric strong {
        margin-top: .04rem;
        color: var(--ap-text);
        font-size: .72rem;
        font-weight: 810;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .ap-mobile-card-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .3rem;
        justify-content: end;
        margin-top: .46rem;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .ap-mobile-card-actions::-webkit-scrollbar {
        display: none;
    }

    /* =========================================================
       PAGINAÇÃO / ESTADOS
       ========================================================= */

    .ap-pager {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .55rem;
        align-items: center;
        padding: .62rem .72rem;
        border-top: 1px solid var(--ap-border);
        background:
            linear-gradient(
                180deg,
                #fff,
                var(--ap-soft)
            );
    }

    .ap-pager-info {
        color: var(--ap-faded);
        font-size: .69rem;
        font-weight: 680;
    }

    .ap-pager-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .32rem;
    }

    .ap-state {
        display: grid;
        min-height: 180px;
        grid-template-columns:
            auto
            minmax(0, 1fr);
        grid-template-rows:
            auto
            auto;
        gap: .1rem .52rem;
        align-content: center;
        align-items: center;
        padding: 1rem;
        color: var(--ap-secondary);
        text-align: left;
    }

    .ap-state-icon {
        display: grid;
        width: 46px;
        height: 46px;
        grid-column: 1;
        grid-row: 1 / 3;
        place-items: center;
        border-radius: 13px;
        background: var(--section-soft, var(--ap-blue-soft));
        color: var(--section-tone, var(--ap-blue));
    }

    .ap-state-icon > i,
    .ap-state-icon > svg {
        width: 21px;
        height: 21px;
    }

    .ap-state strong {
        grid-column: 2;
        grid-row: 1;
        align-self: end;
        color: var(--ap-text);
        font-size: .8rem;
        font-weight: 820;
    }

    .ap-state p {
        grid-column: 2;
        grid-row: 2;
        align-self: start;
        max-width: 520px;
        margin: 0;
        color: var(--ap-faded);
        font-size: .71rem;
        line-height: 1.45;
    }

    .ap-skeleton-grid {
        display: grid;
        min-width: 0;
        gap: .48rem;
    }

    .ap-skeleton {
        position: relative;
        height: 86px;
        overflow: hidden;
        border-radius: 12px;
        background: #e9efeb;
    }

    .ap-skeleton::after {
        display: block;
        width: 50%;
        height: 100%;
        background:
            linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, .72),
                transparent
            );
        content: "";
        animation: ap-shimmer 1.1s infinite;
    }

    @keyframes ap-shimmer {
        from {
            transform: translateX(-120%);
        }

        to {
            transform: translateX(240%);
        }
    }

    /* =========================================================
       MODAIS
       ========================================================= */

    .ap-modal {
        position: fixed;
        z-index: 2200;
        inset: 0;
        display: none;
        place-items: center;
        padding:
            max(14px, env(safe-area-inset-top))
            max(12px, env(safe-area-inset-right))
            max(14px, env(safe-area-inset-bottom))
            max(12px, env(safe-area-inset-left));
        overflow: auto;
        background: rgba(15, 23, 42, .48);
        backdrop-filter: blur(2px);
    }

    .ap-modal.open {
        display: grid;
    }

    .ap-dialog {
        width: min(100%, 540px);
        max-height: min(92dvh, 760px);
        overflow-y: auto;
        border: 1px solid var(--ap-border);
        border-radius: 15px;
        background: #fff;
        box-shadow:
            0 24px 68px rgba(15, 23, 42, .22);
        animation:
            ap-modal-in
            180ms
            cubic-bezier(.2, .8, .2, 1);
    }

    @keyframes ap-modal-in {
        from {
            opacity: 0;
            transform:
                translateY(8px)
                scale(.985);
        }

        to {
            opacity: 1;
            transform:
                translateY(0)
                scale(1);
        }
    }

    .ap-dialog-head {
        position: sticky;
        z-index: 3;
        top: 0;
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .58rem;
        align-items: center;
        padding: .68rem .72rem;
        border-bottom: 1px solid var(--ap-border);
        background:
            linear-gradient(
                180deg,
                var(--ap-soft),
                #fff
            );
    }

    .ap-dialog-head strong {
        color: var(--ap-text);
        font-size: .84rem;
        font-weight: 840;
    }

    .ap-dialog-head small {
        display: block;
        margin-top: .06rem;
        color: var(--ap-faded) !important;
        font-size: .68rem !important;
    }

    .ap-dialog-close {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 1px solid var(--ap-border);
        border-radius: 9px;
        background: #fff;
        color: var(--ap-secondary);
        cursor: pointer;
    }

    .ap-dialog-close:hover,
    .ap-dialog-close:focus-visible {
        border-color: rgba(37, 99, 235, .24);
        background: var(--ap-blue-soft);
        color: var(--ap-blue);
        outline: none;
    }

    .ap-dialog-close > i,
    .ap-dialog-close > svg {
        width: 15px;
        height: 15px;
    }

    .ap-dialog-body {
        padding: .72rem;
    }

    .ap-field {
        display: grid;
        gap: .28rem;
        margin-bottom: .68rem;
    }

    .ap-field label {
        color: var(--ap-text);
        font-size: .7rem;
        font-weight: 760;
    }

    .ap-field small {
        color: var(--ap-faded);
        font-size: .67rem;
        line-height: 1.4;
    }

    .ap-field textarea {
        min-height: 90px;
        resize: vertical;
    }

    .ap-dialog-actions {
        position: sticky;
        bottom: 0;
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .4rem;
        justify-content: end;
        padding: .62rem .72rem .68rem;
        border-top: 1px solid var(--ap-border);
        background: rgba(255, 255, 255, .98);
    }

    .ap-card {
        min-width: 0;
        border-radius: 10px;
        background: var(--ap-violet-soft);
        color: var(--ap-violet);
    }

    .ap-confirm-box {
        display: grid;
        grid-template-columns:
            auto
            minmax(0, 1fr);
        gap: .55rem;
        align-items: start;
        padding: .58rem;
        border-radius: 10px;
        background: var(--ap-amber-soft);
        color: #92400e;
    }

    .ap-confirm-box > i,
    .ap-confirm-box > svg {
        width: 18px;
        height: 18px;
        margin-top: .03rem;
    }

    .ap-confirm-box p {
        margin: 0;
        font-size: .73rem;
        line-height: 1.5;
    }

    /* =========================================================
       MODAL DE COTAS
       ========================================================= */

    .ap-quota-dialog {
        width: min(100%, 920px);
        max-height: min(94dvh, 880px);
    }

    .ap-quota-summary {
        position: sticky;
        z-index: 2;
        top: 57px;
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .65rem;
        align-items: center;
        margin-bottom: .58rem;
        padding: .58rem .62rem;
        border-radius: 11px;
        background:
            linear-gradient(
                135deg,
                #fff,
                var(--ap-violet-soft)
            );
    }

    .ap-quota-summary strong {
        display: block;
        margin-top: .05rem;
        color: var(--ap-text);
        font-size: .84rem;
        font-weight: 840;
    }

    .ap-quota-summary small,
    .ap-quota-card small {
        color: var(--ap-faded);
        font-size: .67rem;
        line-height: 1.4;
    }

    .ap-quota-summary-value {
        min-width: 145px;
        text-align: right;
    }

    .ap-quota-summary.danger {
        background:
            linear-gradient(
                135deg,
                #fff,
                var(--ap-red-soft)
            );
    }

    .ap-quota-summary.danger .ap-quota-summary-value strong {
        color: var(--ap-red);
    }

    .ap-quota-tools {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .48rem;
        margin-bottom: .5rem;
    }

    .ap-quota-search-results {
        display: grid;
        max-height: 240px;
        margin-bottom: .56rem;
        padding: 0 .52rem;
        overflow-y: auto;
        border-radius: 10px;
        background: var(--ap-soft);
    }

    .ap-quota-product-option {
        --option-tone: var(--ap-blue);
        --option-soft: var(--ap-blue-soft);

        display: grid;
        width: 100%;
        min-width: 0;
        grid-template-columns:
            auto
            minmax(0, 1fr)
            auto;
        gap: .5rem;
        align-items: center;
        min-height: 58px;
        padding: .5rem .02rem;
        border: 0;
        background: transparent;
        color: var(--ap-text);
        text-align: left;
        cursor: pointer;
    }

    .ap-quota-product-option + .ap-quota-product-option {
        border-top: 1px solid var(--ap-border);
    }

    .ap-quota-product-option.tone-violet {
        --option-tone: var(--ap-violet);
        --option-soft: var(--ap-violet-soft);
    }

    .ap-quota-product-option.tone-sky {
        --option-tone: var(--ap-sky);
        --option-soft: var(--ap-sky-soft);
    }

    .ap-quota-product-option.tone-amber {
        --option-tone: var(--ap-amber);
        --option-soft: var(--ap-amber-soft);
    }

    .ap-quota-product-option.tone-green {
        --option-tone: var(--ap-green);
        --option-soft: var(--ap-green-soft);
    }

    .ap-quota-product-option:hover,
    .ap-quota-product-option:focus-visible {
        outline: none;
        background:
            linear-gradient(
                90deg,
                var(--option-soft),
                transparent 68%
            );
    }

    .ap-quota-option-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 9px;
        background: var(--option-soft);
        color: var(--option-tone);
    }

    .ap-quota-option-icon > i,
    .ap-quota-option-icon > svg {
        width: 15px;
        height: 15px;
    }

    .ap-quota-product-option strong {
        display: block;
        font-size: .76rem;
        font-weight: 810;
    }

    .ap-quota-product-option small {
        display: block;
        margin-top: .04rem;
        color: var(--ap-faded);
        font-size: .67rem;
    }

    .ap-quota-product-option > span {
        color: var(--option-tone);
        font-size: .69rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .ap-quota-list {
        display: grid;
        min-width: 0;
    }

    .ap-quota-card {
        --quota-tone: var(--ap-blue);
        --quota-soft: var(--ap-blue-soft);

        min-width: 0;
        padding: .68rem .02rem;
    }

    .ap-quota-card + .ap-quota-card {
        border-top: 1px solid var(--ap-border);
    }

    .ap-quota-card.invalid {
        --quota-tone: var(--ap-red);
        --quota-soft: var(--ap-red-soft);
    }

    .ap-quota-card-head {
        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            minmax(0, 1fr)
            auto;
        gap: .52rem;
        align-items: center;
    }

    .ap-quota-card-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 10px;
        background: var(--quota-soft);
        color: var(--quota-tone);
    }

    .ap-quota-card-icon > i,
    .ap-quota-card-icon > svg {
        width: 16px;
        height: 16px;
    }

    .ap-quota-card-title {
        min-width: 0;
    }

    .ap-quota-card-title strong {
        display: block;
        color: var(--ap-text);
        font-size: .82rem;
        font-weight: 820;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .ap-quota-card-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .28rem;
    }

    .ap-quota-card-actions .ap-btn {
        width: 34px;
        min-width: 34px;
        height: 34px;
        min-height: 34px;
        padding: 0;
        font-size: 0;
    }

    .ap-quota-card-actions .ap-btn > i,
    .ap-quota-card-actions .ap-btn > svg {
        width: 14px;
        height: 14px;
    }

    .ap-quota-card-actions .ap-btn.edit {
        border-color:
            color-mix(
                in srgb,
                var(--quota-tone) 20%,
                var(--ap-border)
            );
        background: var(--quota-soft);
        color: var(--quota-tone);
    }

    .ap-quota-numbers {
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        gap: 0;
        margin-top: .5rem;
        overflow: hidden;
        border-radius: 10px;
        background: var(--ap-soft);
    }

    .ap-quota-number {
        min-width: 0;
        padding: .44rem .48rem;
    }

    .ap-quota-number + .ap-quota-number {
        box-shadow:
            inset 1px 0 0 var(--ap-border);
    }

    .ap-quota-number:nth-child(3) {
        background: var(--quota-soft);
    }

    .ap-quota-number span,
    .ap-quota-number strong {
        display: block;
    }

    .ap-quota-number span {
        color: var(--ap-faded);
        font-size: .64rem;
        font-weight: 680;
    }

    .ap-quota-number strong {
        margin-top: .04rem;
        color: var(--ap-text);
        font-size: .72rem;
        font-weight: 810;
        overflow-wrap: anywhere;
    }

    .ap-quota-number:nth-child(3) strong {
        color: var(--quota-tone);
    }

    .ap-quota-use {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .5rem;
        margin-top: .46rem;
        color: var(--ap-secondary);
        font-size: .68rem;
        font-weight: 710;
    }

    .ap-quota-card .ap-progress {
        --progress-tone: var(--quota-tone);
        --progress-soft: var(--quota-soft);
        margin-top: .3rem;
    }

    .ap-quota-controls {
        display: none;
        grid-template-columns:
            minmax(0, 1fr)
            160px;
        gap: .55rem;
        align-items: end;
        margin-top: .52rem;
        padding: .52rem;
        border-radius: 10px;
        background:
            linear-gradient(
                135deg,
                #fff,
                var(--quota-soft)
            );
    }

    .ap-quota-card.editing .ap-quota-controls {
        display: grid;
    }

    .ap-quota-controls label {
        display: grid;
        gap: .25rem;
        color: var(--ap-secondary);
        font-size: .68rem;
        font-weight: 740;
    }

    /* slider semântico/pastel */
    .ap-quota-slider {
        --slider-pct: 0%;

        width: 100%;
        height: 38px;
        margin: 0;
        appearance: none;
        -webkit-appearance: none;
        background: transparent;
        cursor: pointer;
        touch-action: pan-y;
    }

    .ap-quota-slider:focus {
        outline: none;
    }

    .ap-quota-slider::-webkit-slider-runnable-track {
        height: 8px;
        border-radius: 999px;
        background:
            linear-gradient(
                90deg,
                color-mix(
                    in srgb,
                    var(--quota-tone) 56%,
                    #fff
                ) 0,
                var(--quota-tone) var(--slider-pct),
                var(--quota-soft) var(--slider-pct),
                var(--quota-soft) 100%
            );
    }

    .ap-quota-slider::-webkit-slider-thumb {
        width: 20px;
        height: 20px;
        margin-top: -6px;
        border: 4px solid #fff;
        border-radius: 50%;
        appearance: none;
        -webkit-appearance: none;
        background: var(--quota-tone);
        box-shadow:
            0 0 0 1px
            color-mix(
                in srgb,
                var(--quota-tone) 24%,
                var(--ap-border)
            ),
            0 3px 8px rgba(15, 35, 24, .12);
    }

    .ap-quota-slider::-moz-range-track {
        height: 8px;
        border: 0;
        border-radius: 999px;
        background: var(--quota-soft);
    }

    .ap-quota-slider::-moz-range-progress {
        height: 8px;
        border-radius: 999px;
        background:
            linear-gradient(
                90deg,
                color-mix(
                    in srgb,
                    var(--quota-tone) 56%,
                    #fff
                ),
                var(--quota-tone)
            );
    }

    .ap-quota-slider::-moz-range-thumb {
        width: 14px;
        height: 14px;
        border: 4px solid #fff;
        border-radius: 50%;
        background: var(--quota-tone);
        box-shadow:
            0 0 0 1px
            color-mix(
                in srgb,
                var(--quota-tone) 24%,
                var(--ap-border)
            );
    }

    .ap-quota-slider:disabled {
        cursor: not-allowed;
        opacity: .6;
    }

    .ap-quota-message {
        display: grid;
        min-height: 34px;
        grid-template-columns:
            auto
            minmax(0, 1fr);
        gap: .34rem;
        align-items: start;
        margin-top: .44rem;
        padding: .4rem .46rem;
        border-radius: 9px;
        background: var(--quota-soft);
        color: var(--ap-secondary);
        font-size: .68rem;
        line-height: 1.42;
    }

    .ap-quota-message::before {
        content: "i";
        display: grid;
        width: 18px;
        height: 18px;
        place-items: center;
        border-radius: 999px;
        background: #fff;
        color: var(--quota-tone);
        font-size: .61rem;
        font-weight: 900;
        line-height: 1;
    }

    .ap-quota-message.error {
        background: var(--ap-red-soft);
        color: #991b1b;
        font-weight: 730;
    }

    .ap-quota-message.error::before {
        content: "!";
        color: var(--ap-red);
    }

    .ap-quota-empty {
        display: grid;
        min-height: 130px;
        grid-template-columns:
            auto
            minmax(0, 1fr);
        gap: .5rem;
        align-content: center;
        align-items: center;
        padding: .8rem;
        color: var(--ap-secondary);
        font-size: .72rem;
        text-align: left;
    }

    /* =========================================================
       TOAST
       ========================================================= */

    .ap-toast-root {
        position: fixed;
        z-index: 2400;
        top: 1rem;
        right: 1rem;
        display: grid;
        width: min(380px, calc(100vw - 2rem));
        gap: .42rem;
        pointer-events: none;
    }

    .ap-toast {
        display: grid;
        grid-template-columns:
            auto
            minmax(0, 1fr);
        gap: .52rem;
        align-items: center;
        padding: .62rem .66rem;
        border: 1px solid var(--ap-border);
        border-radius: 11px;
        background: rgba(255, 255, 255, .99);
        box-shadow:
            0 14px 32px rgba(15, 35, 24, .12);
        color: var(--ap-text);
        font-size: .71rem;
        font-weight: 720;
        pointer-events: auto;
        animation: ap-toast-in 180ms ease both;
    }

    .ap-toast-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 9px;
        background: var(--ap-green-soft);
        color: var(--ap-green);
    }

    .ap-toast.error .ap-toast-icon {
        background: var(--ap-red-soft);
        color: var(--ap-red);
    }

    .ap-toast-icon > i,
    .ap-toast-icon > svg {
        width: 15px;
        height: 15px;
    }

    @keyframes ap-toast-in {
        from {
            opacity: 0;
            transform: translateY(-5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 1080px) {
        .ap-limit-summary {
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
        }

        .ap-limit-fact:nth-child(4),
        .ap-limit-fact:nth-child(5) {
            box-shadow:
                inset 0 1px 0 var(--ap-border);
        }

        .ap-limit-row {
            grid-template-columns:
                auto
                minmax(170px, .7fr)
                minmax(0, 1.3fr)
                auto;
        }

        .ap-limit-row-use {
            grid-column: 2 / 4;
        }
    }

    @media (max-width: 980px) {
        .ap-hero {
            grid-template-columns:
                auto
                auto
                minmax(0, 1fr);
        }

        .ap-hero-actions {
            grid-column: 3;
            justify-self: start;
        }

        .ap-overview-grid {
            grid-template-columns: 1fr;
        }

        .ap-overview-list {
            border-top: 1px solid var(--ap-border);
        }
    }

    @media (max-width: 860px) {
        .ap-table-wrap {
            display: none;
        }

        .ap-mobile-list {
            display: grid;
        }

        .ap-limit-row {
            grid-template-columns:
                auto
                minmax(0, 1fr)
                auto;
        }

        .ap-limit-row-metrics {
            grid-column: 2 / -1;
        }

        .ap-limit-row-use {
            grid-column: 2 / -1;
        }
    }

    @media (max-width: 700px) {
        .ap-toolbar {
            grid-template-columns: 1fr;
        }

        .ap-select {
            min-width: 0;
        }

        .ap-actions {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
        }

        .ap-actions .ap-btn {
            width: 100%;
        }

        .ap-pager {
            grid-template-columns: 1fr;
        }

        .ap-pager-actions {
            grid-template-columns:
                1fr
                1fr;
            grid-auto-flow: row;
        }

        .ap-pager-actions .ap-btn {
            width: 100%;
        }

        .ap-limit-summary {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .ap-limit-fact {
            box-shadow:
                inset 0 1px 0 var(--ap-border) !important;
        }

        .ap-limit-fact:nth-child(1),
        .ap-limit-fact:nth-child(2) {
            box-shadow: none !important;
        }

        .ap-limit-fact:nth-child(even) {
            box-shadow:
                inset 1px 0 0 var(--ap-border),
                inset 0 1px 0 var(--ap-border) !important;
        }

        .ap-limit-fact:nth-child(2) {
            box-shadow:
                inset 1px 0 0 var(--ap-border) !important;
        }
    }

    @media (max-width: 560px) {
        .ap-shell {
            gap: .7rem;
        }

        .ap-hero {
            grid-template-columns:
                36px
                36px
                minmax(0, 1fr);
            padding: .62rem;
        }

        .ap-back,
        .ap-context-icon {
            width: 36px;
            height: 36px;
        }

        .ap-title {
            font-size: 1rem;
        }

        .ap-meta-row {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            gap: .08rem;
            width: 100%;
        }

        .ap-hero-actions {
            width: 100%;
            grid-column: 2 / -1;
            grid-template-columns:
                1fr
                1fr;
            grid-auto-flow: row;
        }

        .ap-hero-actions .ap-hero-btn {
            width: 100%;
        }

        .ap-tabs {
            padding: .4rem;
        }

        .ap-overview-head p,
        .ap-section-subtitle {
            display: none;
        }

        .ap-financial-hero {
            min-height: 190px;
            padding: .85rem;
        }

        .ap-overview-row {
            grid-template-columns:
                auto
                minmax(0, 1fr);
        }

        .ap-overview-row-value {
            grid-column: 2;
            justify-self: start;
            margin-top: -.1rem;
            text-align: left;
        }

        .ap-mobile-card-actions {
            justify-content: start;
        }

        .ap-limit-products {
            padding-right: .6rem;
            padding-left: .6rem;
        }

        .ap-limit-row {
            grid-template-columns:
                auto
                minmax(0, 1fr);
        }

        .ap-limit-row > .ap-btn {
            grid-column: 2;
            width: 100%;
        }

        .ap-limit-row-metrics,
        .ap-limit-row-use {
            grid-column: 1 / -1;
        }

        .ap-quota-summary {
            top: 55px;
            grid-template-columns: 1fr;
        }

        .ap-quota-summary-value {
            min-width: 0;
            text-align: left;
        }

        .ap-quota-tools {
            grid-template-columns: 1fr;
        }

        .ap-quota-tools .ap-btn {
            width: 100%;
        }

        .ap-quota-numbers {
            grid-template-columns:
                1fr
                1fr;
        }

        .ap-quota-number:nth-child(3),
        .ap-quota-number:nth-child(4) {
            box-shadow:
                inset 0 1px 0 var(--ap-border);
        }

        .ap-quota-number:nth-child(2),
        .ap-quota-number:nth-child(4) {
            box-shadow:
                inset 1px 0 0 var(--ap-border);
        }

        .ap-quota-number:nth-child(4) {
            box-shadow:
                inset 1px 0 0 var(--ap-border),
                inset 0 1px 0 var(--ap-border);
        }

        .ap-quota-controls {
            grid-template-columns: 1fr;
        }

        .ap-dialog-actions {
            grid-template-columns:
                1fr
                1fr;
            grid-auto-flow: row;
        }

        .ap-dialog-actions .ap-btn {
            width: 100%;
        }

        .ap-toast-root {
            top: auto;
            right: .65rem;
            bottom:
                calc(
                    5rem
                    + env(safe-area-inset-bottom)
                );
            left: .65rem;
            width: auto;
        }
    }

    @media (max-width: 400px) {
        .ap-hero-actions {
            grid-template-columns: 1fr;
        }

        .ap-financial-facts,
        .ap-mobile-card-body,
        .ap-limit-summary,
        .ap-quota-numbers,
        .ap-dialog-actions {
            grid-template-columns: 1fr;
        }

        .ap-limit-fact,
        .ap-limit-fact:nth-child(1),
        .ap-limit-fact:nth-child(2),
        .ap-limit-fact:nth-child(even) {
            box-shadow:
                inset 0 1px 0 var(--ap-border) !important;
        }

        .ap-limit-fact:first-child {
            box-shadow: none !important;
        }

        .ap-limit-row-metrics {
            grid-template-columns:
                1fr
                1fr;
        }

        .ap-quota-number:nth-child(2),
        .ap-quota-number:nth-child(3),
        .ap-quota-number:nth-child(4) {
            box-shadow:
                inset 0 1px 0 var(--ap-border);
        }

        .ap-quota-card-head {
            grid-template-columns:
                auto
                minmax(0, 1fr);
        }

        .ap-quota-card-actions {
            grid-column: 2;
            justify-self: start;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .ap-shell *,
        .ap-shell *::before,
        .ap-shell *::after,
        .ap-modal *,
        .ap-modal *::before,
        .ap-modal *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<style id="ap-layout-refinement">
    /* =========================================================
       REFINAMENTO VISUAL — layout / responsividade
       Mantém a estrutura e o comportamento da view intactos.
       ========================================================= */

    .ap-shell,
    .ap-modal,
    .ap-toast-root {
        --ap-radius-lg: 15px;
        --ap-radius-md: 11px;
        --ap-radius-sm: 9px;
        --ap-shadow-soft: 0 5px 18px rgba(15, 35, 24, .045);
        --ap-shadow-float: 0 18px 48px rgba(15, 35, 24, .14);
    }

    .ap-shell {
        gap: .72rem;
        padding-bottom: 1.2rem;
    }

    /* Ícones: centralização consistente depois do lucide.createIcons() */
    .ap-back,
    .ap-context-icon,
    .ap-overview-head-icon,
    .ap-section-icon,
    .ap-overview-row-icon,
    .ap-limit-fact-icon,
    .ap-limit-row-icon,
    .ap-state-icon,
    .ap-dialog-close,
    .ap-quota-option-icon,
    .ap-quota-card-icon,
    .ap-toast-icon {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        line-height: 0;
        flex: 0 0 auto;
    }

    .ap-back > svg,
    .ap-context-icon > svg,
    .ap-overview-head-icon > svg,
    .ap-section-icon > svg,
    .ap-overview-row-icon > svg,
    .ap-limit-fact-icon > svg,
    .ap-limit-row-icon > svg,
    .ap-state-icon > svg,
    .ap-dialog-close > svg,
    .ap-quota-option-icon > svg,
    .ap-quota-card-icon > svg,
    .ap-toast-icon > svg,
    .ap-btn > svg,
    .ap-hero-btn > svg,
    .ap-tab > svg {
        display: block;
        flex: 0 0 auto;
    }

    /* ---------- Cabeçalho ---------- */

    .ap-hero {
        min-height: 76px;
        padding: .7rem .78rem;
        border-radius: var(--ap-radius-lg);
        background:
            radial-gradient(circle at 96% -25%, rgba(37, 99, 235, .13), transparent 19rem),
            radial-gradient(circle at 72% 145%, rgba(124, 58, 237, .06), transparent 17rem),
            linear-gradient(180deg, var(--ap-soft), #fff);
        box-shadow: var(--ap-shadow-soft);
    }

    .ap-title {
        font-size: clamp(1.03rem, 1.7vw, 1.2rem);
    }

    .ap-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: .16rem .52rem;
        margin-top: .14rem;
        font-size: .68rem;
    }

    .ap-meta-row > span {
        display: inline-flex;
        align-items: center;
        gap: .2rem;
    }

    .ap-hero-actions {
        gap: .3rem;
    }

    .ap-hero-btn {
        min-height: 38px;
        padding: .42rem .58rem;
        border-radius: var(--ap-radius-sm);
    }

    /* ---------- Navegação por seções ---------- */

    .ap-tabs-wrap {
        top: max(.25rem, env(safe-area-inset-top));
    }

    .ap-tabs {
        gap: .2rem;
        padding: .34rem;
        border-radius: 12px;
        box-shadow: var(--ap-shadow-soft);
    }

    .ap-tab {
        min-height: 37px;
        padding: .38rem .52rem;
        border-radius: 8px;
        font-size: .69rem;
    }

    .ap-tab.active {
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--tab-tone) 7%, transparent);
    }

    /* ---------- Superfícies ---------- */

    .ap-overview,
    .ap-section-card {
        border-radius: var(--ap-radius-lg);
        box-shadow: var(--ap-shadow-soft);
    }

    .ap-overview-head,
    .ap-section-head {
        min-height: 58px;
        padding: .56rem .66rem;
        gap: .5rem;
    }

    .ap-overview-head-icon,
    .ap-section-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
    }

    .ap-overview-head-icon > svg,
    .ap-section-icon > svg {
        width: 16px;
        height: 16px;
    }

    .ap-overview-head h2,
    .ap-section-title {
        font-size: .88rem;
    }

    .ap-overview-head p,
    .ap-section-subtitle {
        margin-top: .04rem;
        font-size: .67rem;
    }

    /* ---------- Resumo ---------- */

    .ap-overview-grid {
        grid-template-columns: minmax(300px, .82fr) minmax(0, 1.18fr);
    }

    .ap-financial-hero {
        min-height: 190px;
        padding: .86rem;
        background:
            radial-gradient(circle at 100% 0, color-mix(in srgb, var(--financial-tone) 9%, transparent), transparent 15rem),
            linear-gradient(135deg, #fff, var(--financial-soft));
    }

    .ap-financial-value {
        margin-top: .27rem;
        font-size: clamp(1.55rem, 3.2vw, 2.15rem);
    }

    .ap-financial-helper {
        max-width: 360px;
        margin-top: .3rem;
        font-size: .69rem;
        line-height: 1.42;
    }

    .ap-financial-facts {
        gap: .34rem;
        margin-top: .56rem;
    }

    .ap-financial-fact {
        padding: .4rem .46rem;
        border: 1px solid rgba(255,255,255,.55);
    }

    .ap-overview-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0 .72rem;
        align-content: stretch;
        padding: .5rem .68rem;
    }

    .ap-overview-row {
        min-height: 58px;
        padding: .42rem 0;
    }

    .ap-overview-row + .ap-overview-row {
        border-top: 0;
    }

    .ap-overview-row:nth-child(n + 3) {
        border-top: 1px solid var(--ap-border);
    }

    .ap-overview-row-icon {
        width: 32px;
        height: 32px;
        border-radius: 9px;
    }

    .ap-overview-row-copy span {
        font-size: .63rem;
    }

    .ap-overview-row-copy strong {
        font-size: .7rem;
    }

    .ap-overview-row-value {
        font-size: .75rem;
    }

    /* ---------- Ferramentas ---------- */

    .ap-toolbar {
        grid-template-columns: minmax(260px, 1fr) minmax(155px, 210px);
        padding: .5rem .62rem;
        gap: .4rem;
    }

    .ap-input,
    .ap-select,
    .ap-field input,
    .ap-field select,
    .ap-field textarea,
    .ap-quota-input {
        min-height: 39px;
        border-radius: var(--ap-radius-sm);
        font-size: .71rem;
    }

    /* ---------- Limites ---------- */

    .ap-limit-summary {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .ap-limit-fact {
        min-height: 62px;
        padding: .42rem .48rem;
    }

    .ap-limit-fact-icon {
        width: 31px;
        height: 31px;
    }

    .ap-limit-fact-copy span {
        font-size: .61rem;
    }

    .ap-limit-fact-copy strong {
        font-size: .7rem;
    }

    .ap-limit-products {
        padding: .02rem .66rem .58rem;
    }

    .ap-limit-row {
        grid-template-columns: auto minmax(160px, .78fr) minmax(360px, 1.52fr) minmax(160px, .62fr) auto;
        gap: .48rem;
        padding: .58rem 0;
    }

    .ap-limit-row-icon {
        width: 35px;
        height: 35px;
    }

    .ap-limit-row-metrics {
        border: 1px solid var(--ap-border);
        background: #fff;
    }

    .ap-limit-row-metric {
        padding: .38rem .42rem;
    }

    .ap-limit-row-use {
        padding: 0 .08rem;
    }

    /* ---------- Tabelas desktop ---------- */

    .ap-table {
        min-width: 820px;
        font-size: .7rem;
    }

    .ap-table th,
    .ap-table td {
        padding: .5rem .58rem;
    }

    .ap-table th {
        font-size: .61rem;
    }

    .ap-table tbody tr:hover td {
        background: color-mix(in srgb, var(--section-soft) 24%, #fff);
    }

    /* ---------- Cards mobile base ---------- */

    .ap-mobile-list {
        gap: .5rem;
        padding: .56rem;
        background: var(--ap-soft);
    }

    .ap-mobile-card {
        padding: .58rem;
        border: 1px solid var(--ap-border);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(15, 35, 24, .025);
    }

    .ap-mobile-card + .ap-mobile-card {
        border-top: 1px solid var(--ap-border);
    }

    .ap-mobile-card-title strong {
        font-size: .77rem;
    }

    .ap-mobile-card-title span {
        font-size: .65rem;
    }

    .ap-mobile-card-body {
        gap: .3rem;
        margin-top: .42rem;
        padding: .42rem;
        border-radius: 9px;
    }

    .ap-mobile-card-actions {
        gap: .26rem;
        margin-top: .42rem;
        justify-content: flex-start;
        overflow: visible;
        flex-wrap: wrap;
    }

    /* ---------- Paginação ---------- */

    .ap-pager {
        padding: .5rem .62rem;
    }

    /* ---------- Modais ---------- */

    .ap-dialog {
        border-radius: var(--ap-radius-lg);
        box-shadow: var(--ap-shadow-float);
    }

    .ap-dialog-head {
        padding: .58rem .64rem;
    }

    .ap-dialog-body {
        padding: .62rem;
    }

    .ap-dialog-actions {
        padding: .54rem .64rem calc(.58rem + env(safe-area-inset-bottom));
    }

    .ap-quota-dialog {
        width: min(100%, 860px);
    }

    .ap-quota-summary {
        top: 53px;
        padding: .5rem .56rem;
    }

    .ap-quota-card {
        padding: .58rem 0;
    }

    .ap-quota-numbers {
        margin-top: .42rem;
    }

    .ap-quota-number {
        padding: .4rem .44rem;
    }

    .ap-quota-controls {
        margin-top: .44rem;
        padding: .46rem;
    }

    /* =========================================================
       TABLET
       ========================================================= */

    @media (max-width: 1100px) {
        .ap-overview-grid {
            grid-template-columns: minmax(280px, .9fr) minmax(0, 1.1fr);
        }

        .ap-overview-list {
            grid-template-columns: 1fr;
        }

        .ap-overview-row + .ap-overview-row {
            border-top: 1px solid var(--ap-border);
        }

        .ap-overview-row:nth-child(2) {
            border-top: 0;
        }

        .ap-limit-row {
            grid-template-columns: auto minmax(160px, .7fr) minmax(0, 1.3fr) auto;
        }

        .ap-limit-row-use {
            grid-column: 2 / 4;
        }
    }

    @media (max-width: 920px) {
        .ap-overview-grid {
            grid-template-columns: 1fr;
        }

        .ap-financial-hero {
            min-height: 165px;
        }

        .ap-overview-list {
            grid-template-columns: repeat(2, minmax(0,1fr));
            border-top: 1px solid var(--ap-border);
        }

        .ap-overview-row + .ap-overview-row {
            border-top: 0;
        }

        .ap-overview-row:nth-child(n + 3) {
            border-top: 1px solid var(--ap-border);
        }
    }

    /* =========================================================
       MOBILE
       ========================================================= */

    @media (max-width: 760px) {
        .ap-shell {
            gap: .58rem;
        }

        .ap-hero {
            grid-template-columns: 36px minmax(0, 1fr);
            gap: .48rem;
            min-height: auto;
            padding: .56rem;
        }

        .ap-back {
            width: 36px;
            height: 36px;
        }

        .ap-context-icon {
            display: none !important;
        }

        .ap-hero-copy {
            align-self: center;
        }

        .ap-title {
            font-size: .98rem;
        }

        .ap-meta-row {
            gap: .1rem .4rem;
            margin-top: .1rem;
            font-size: .63rem;
        }

        .ap-meta-row > span {
            max-width: 100%;
        }

        .ap-hero-actions {
            grid-column: 1 / -1;
            width: 100%;
            grid-template-columns: repeat(2, minmax(0,1fr));
            grid-auto-flow: row;
            gap: .34rem;
            margin-top: .06rem;
        }

        .ap-hero-btn {
            width: 100%;
            min-height: 40px;
        }

        .ap-tabs-wrap {
            top: max(.15rem, env(safe-area-inset-top));
        }

        .ap-tabs {
            width: 100%;
            padding: .3rem;
            gap: .18rem;
            border-radius: 11px;
            scroll-snap-type: x proximity;
        }

        .ap-tab {
            min-height: 38px;
            padding: .38rem .5rem;
            scroll-snap-align: start;
        }

        .ap-overview-head,
        .ap-section-head {
            min-height: 54px;
            padding: .5rem .56rem;
        }

        .ap-overview-head-icon,
        .ap-section-icon {
            width: 34px;
            height: 34px;
        }

        .ap-overview-head p,
        .ap-section-subtitle {
            display: none;
        }

        .ap-section-head-actions {
            grid-column: 1 / -1;
            width: 100%;
            grid-template-columns: repeat(2, minmax(0,1fr));
            grid-auto-flow: row;
        }

        .ap-section-head-actions .ap-btn,
        .ap-section-head-actions a.ap-btn {
            width: 100%;
        }

        .ap-financial-hero {
            min-height: 150px;
            padding: .7rem;
        }

        .ap-financial-value {
            font-size: 1.55rem;
        }

        .ap-financial-helper {
            font-size: .66rem;
        }

        .ap-overview-list {
            grid-template-columns: 1fr;
            padding: .4rem .58rem;
        }

        .ap-overview-row,
        .ap-overview-row:nth-child(n + 3) {
            min-height: 52px;
            border-top: 1px solid var(--ap-border);
        }

        .ap-overview-row:first-child {
            border-top: 0;
        }

        .ap-toolbar {
            grid-template-columns: 1fr 150px;
            padding: .48rem .56rem;
        }

        .ap-limit-summary {
            display: flex;
            gap: .34rem;
            overflow-x: auto;
            padding: .36rem;
            border-bottom: 1px solid var(--ap-border);
            scrollbar-width: none;
            scroll-snap-type: x proximity;
        }

        .ap-limit-summary::-webkit-scrollbar {
            display: none;
        }

        .ap-limit-fact {
            min-width: 138px;
            min-height: 58px;
            border: 0 !important;
            border-radius: 10px;
            background: color-mix(in srgb, var(--fact-soft) 68%, #fff);
            scroll-snap-align: start;
            box-shadow: none !important;
        }

        .ap-limit-products {
            display: grid;
            gap: .5rem;
            padding: .54rem;
            background: var(--ap-soft);
        }

        .ap-limit-row {
            display: grid;
            grid-template-columns: 34px minmax(0,1fr) auto;
            gap: .42rem;
            padding: .56rem;
            border: 1px solid var(--ap-border);
            border-radius: 12px;
            background: #fff;
        }

        .ap-limit-row + .ap-limit-row {
            border-top: 1px solid var(--ap-border);
        }

        .ap-limit-row-icon {
            width: 34px;
            height: 34px;
        }

        .ap-limit-row-metrics {
            grid-column: 1 / -1;
            grid-template-columns: repeat(2, minmax(0,1fr));
            border-radius: 9px;
        }

        .ap-limit-row-metric:nth-child(3),
        .ap-limit-row-metric:nth-child(4) {
            box-shadow: inset 0 1px 0 var(--ap-border);
        }

        .ap-limit-row-metric:nth-child(2),
        .ap-limit-row-metric:nth-child(4) {
            box-shadow: inset 1px 0 0 var(--ap-border);
        }

        .ap-limit-row-use {
            grid-column: 1 / -1;
            padding: 0;
        }

        .ap-limit-row > .ap-btn {
            grid-column: 1 / -1;
            width: 100%;
        }

        .ap-table-wrap {
            display: none;
        }

        .ap-mobile-list {
            display: grid;
        }

        .ap-pager {
            grid-template-columns: 1fr;
            gap: .4rem;
        }

        .ap-pager-actions {
            grid-template-columns: repeat(2, minmax(0,1fr));
            grid-auto-flow: row;
        }

        .ap-pager-actions .ap-btn {
            width: 100%;
        }

        .ap-modal {
            place-items: end center;
            padding: 0;
        }

        .ap-dialog,
        .ap-quota-dialog {
            width: 100%;
            max-width: none;
            max-height: 92svh;
            border-right: 0;
            border-bottom: 0;
            border-left: 0;
            border-radius: 16px 16px 0 0;
        }

        .ap-dialog-head {
            min-height: 54px;
        }

        .ap-quota-summary {
            top: 54px;
        }

        .ap-quota-tools {
            grid-template-columns: 1fr;
        }

        .ap-quota-tools .ap-btn {
            width: 100%;
        }

        .ap-quota-card {
            padding: .54rem 0;
        }

        .ap-quota-card-head {
            align-items: start;
        }

        .ap-quota-numbers {
            grid-template-columns: repeat(2, minmax(0,1fr));
        }

        .ap-quota-number:nth-child(3),
        .ap-quota-number:nth-child(4) {
            box-shadow: inset 0 1px 0 var(--ap-border);
        }

        .ap-quota-number:nth-child(2),
        .ap-quota-number:nth-child(4) {
            box-shadow: inset 1px 0 0 var(--ap-border);
        }

        .ap-quota-controls {
            grid-template-columns: 1fr;
        }

        .ap-dialog-actions {
            grid-template-columns: repeat(2, minmax(0,1fr));
            grid-auto-flow: row;
        }

        .ap-dialog-actions .ap-btn {
            width: 100%;
        }

        .ap-toast-root {
            top: auto;
            right: .58rem;
            bottom: calc(4.7rem + env(safe-area-inset-bottom));
            left: .58rem;
            width: auto;
        }
    }

    @media (max-width: 480px) {
        .ap-hero-actions {
            grid-template-columns: 1fr;
        }

        .ap-meta-row > span:nth-child(n + 3) {
            flex-basis: 100%;
        }

        .ap-toolbar {
            grid-template-columns: 1fr;
        }

        .ap-overview-row {
            grid-template-columns: auto minmax(0,1fr) auto;
        }

        .ap-overview-row-value {
            grid-column: auto;
            justify-self: end;
            margin-top: 0;
            text-align: right;
        }

        .ap-section-head {
            grid-template-columns: auto minmax(0,1fr);
        }

        .ap-section-head-actions {
            grid-column: 1 / -1;
            grid-template-columns: 1fr;
        }

        .ap-mobile-card-body,
        .ap-financial-facts {
            grid-template-columns: repeat(2, minmax(0,1fr));
        }

        .ap-mobile-card-actions .ap-btn,
        .ap-mobile-card-actions .delivery-note-trigger,
        .ap-mobile-card-actions a.ap-btn {
            min-height: 34px;
            padding: .34rem .46rem;
            font-size: .65rem;
        }

        .ap-quota-card-head {
            grid-template-columns: 36px minmax(0,1fr);
        }

        .ap-quota-card-actions {
            grid-column: 2;
            justify-self: start;
        }

        .ap-dialog-actions {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 360px) {
        .ap-financial-facts,
        .ap-mobile-card-body,
        .ap-quota-numbers {
            grid-template-columns: 1fr;
        }

        .ap-limit-row-metrics {
            grid-template-columns: 1fr;
        }

        .ap-limit-row-metric,
        .ap-limit-row-metric:nth-child(2),
        .ap-limit-row-metric:nth-child(3),
        .ap-limit-row-metric:nth-child(4) {
            box-shadow: inset 0 1px 0 var(--ap-border);
        }

        .ap-limit-row-metric:first-child {
            box-shadow: none;
        }
    }
</style>


<div class="ap-shell" id="associate-project-app">
    <section class="ap-hero">
        <a
            class="ap-back"
            href="{{ route(
                'delivery.projects.producers',
                [
                    'tenant' => $tenantSlug,
                    'project' => $project->id,
                ]
            ) }}"
            aria-label="Voltar aos produtores"
            title="Voltar aos produtores"
        >
            <i data-lucide="arrow-left"></i>
        </a>

        <span
            class="ap-context-icon"
            aria-hidden="true"
        >
            <i data-lucide="user-round-cog"></i>
        </span>

        <div class="ap-hero-copy">
            <h1 class="ap-title">
                {{ $associate->display_name }}
            </h1>

            <div class="ap-meta-row">
                <span>
                    <i data-lucide="hash"></i>

                    <span class="ap-meta-text">
                        {{ $associateCode }}
                    </span>
                </span>

                <span>
                    <i data-lucide="map-pin"></i>

                    <span class="ap-meta-text">
                        {{ $associateLocation }}
                    </span>
                </span>

                <span>
                    <i data-lucide="folder-kanban"></i>

                    <span class="ap-meta-text">
                        {{ $project->title }}
                    </span>
                </span>

                @if($projectPeriod)
                    <span>
                        <i data-lucide="calendar-days"></i>

                        <span class="ap-meta-text">
                            {{ $projectPeriod }}
                        </span>
                    </span>
                @endif
            </div>
        </div>

        <div class="ap-hero-actions">
            <a
                class="ap-hero-btn simulator"
                href="{{ $associateSimulatorUrl }}"
            >
                <i data-lucide="calculator"></i>
                Simular entregas
            </a>

            @if($canManageLimits)
                <button
                    class="ap-hero-btn limits"
                    type="button"
                    onclick="showLimits()"
                >
                    <i data-lucide="sliders-horizontal"></i>
                    Limites e cotas
                </button>
            @endif

            <a
                class="ap-hero-btn delivery"
                href="{{ route(
                    'delivery.register',
                    [
                        'tenant' => $tenantSlug,
                        'project' => $project->id,
                        'associate' => $associate->id,
                    ]
                ) }}"
            >
                <i data-lucide="package-plus"></i>
                Registrar entrega
            </a>
        </div>
    </section>

    <div class="ap-tabs-wrap">
        <nav class="ap-tabs" aria-label="Seções do associado no projeto">
            <button class="ap-tab active" data-section="summary" type="button">
                <i data-lucide="layout-dashboard"></i>
                Resumo
            </button>

            <button class="ap-tab" data-section="limits" type="button">
                <i data-lucide="gauge"></i>
                Limites
            </button>

            <button class="ap-tab" data-section="deliveries" type="button">
                <i data-lucide="package-check"></i>
                Entregas
            </button>

            <button class="ap-tab" data-section="distributions" type="button">
                <i data-lucide="route"></i>
                Distribuições
            </button>

            <button class="ap-tab" data-section="receipts" type="button">
                <i data-lucide="receipt-text"></i>
                Comprovantes
            </button>

            <button class="ap-tab" data-section="payments" type="button">
                <i data-lucide="wallet-cards"></i>
                Pagamentos
            </button>

            <button class="ap-tab" data-section="history" type="button">
                <i data-lucide="history"></i>
                Histórico
            </button>
        </nav>
    </div>

    <section id="ap-content" class="ap-content" aria-live="polite">
        <div class="ap-skeleton-grid">
            @for($index = 0; $index < 8; $index++)
                <div class="ap-skeleton"></div>
            @endfor
        </div>
    </section>
</div>

<div class="ap-modal" id="limit-modal" aria-hidden="true">
    <div class="ap-dialog">
        <div class="ap-dialog-head">
            <strong id="limit-title">Editar limite</strong>

            <button class="ap-dialog-close" type="button" onclick="closeLimitModal()" aria-label="Fechar">
                <i data-lucide="x"></i>
            </button>
        </div>

        <form id="limit-form">
            <div class="ap-dialog-body">
                <input type="hidden" name="kind" id="limit-kind">

                <div id="product-field" class="ap-field" hidden>
                    <label for="limit-product">Produto</label>
                    <select name="product_id" id="limit-product"></select>
                    <small>O preço exibido vem da tabela de referência do projeto.</small>
                </div>

                <div class="ap-field">
                    <label id="limit-value-label" for="limit-value">Limite</label>
                    <input
                        type="number"
                        min="0"
                        step="0.001"
                        name="value"
                        id="limit-value"
                        required
                    >
                    <small id="limit-availability" hidden></small>
                </div>

                <div class="ap-field" id="limit-simulation" hidden>
                    <label>Valor simulado</label>
                    <div class="ap-card" style="padding:.65rem">
                        <strong id="limit-simulated-value">R$ 0,00</strong>
                        <small id="limit-simulated-total" style="display:block;margin-top:.2rem"></small>
                    </div>
                </div>

                <div class="ap-field">
                    <label for="limit-notes">Observação</label>
                    <textarea
                        name="notes"
                        rows="3"
                        id="limit-notes"
                        placeholder="Informação opcional sobre este limite"
                    ></textarea>
                </div>
            </div>

            <div class="ap-dialog-actions">
                <button class="ap-btn" type="button" onclick="closeLimitModal()">Cancelar</button>
                <button class="ap-btn violet" type="submit">
                    <i data-lucide="save"></i>
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<div class="ap-modal" id="product-limits-modal" aria-hidden="true">
    <div class="ap-dialog ap-quota-dialog">
        <div class="ap-dialog-head">
            <div>
                <strong>Produtos e cotas</strong>
                <small style="display:block;margin-top:.15rem;color:var(--ap-faded)">
                    {{ $associate->display_name }}
                </small>
            </div>

            <button class="ap-dialog-close" type="button" onclick="closeProductLimitsManager()" aria-label="Fechar">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="ap-dialog-body">
            <div id="quota-summary" class="ap-quota-summary"></div>

            <div class="ap-quota-tools">
                <input
                    class="ap-input"
                    id="quota-product-search"
                    type="search"
                    placeholder="Buscar produto para adicionar"
                    autocomplete="off"
                    oninput="renderQuotaProductOptions()"
                >

                <button class="ap-btn blue" type="button" onclick="toggleQuotaProductOptions()">
                    <i data-lucide="package-plus"></i>
                    Adicionar produto
                </button>
            </div>

            <div class="ap-quota-search-results" id="quota-product-options" hidden></div>
            <div class="ap-quota-list" id="quota-list"></div>
        </div>

        <div class="ap-dialog-actions">
            <button class="ap-btn" type="button" onclick="closeProductLimitsManager()">Cancelar</button>
            <button class="ap-btn violet" type="button" id="quota-save-all" onclick="saveProductLimitChanges()">
                <i data-lucide="save"></i>
                Salvar alterações
            </button>
        </div>
    </div>
</div>

<div class="ap-modal" id="confirm-modal" aria-hidden="true">
    <div class="ap-dialog" style="max-width:440px">
        <div class="ap-dialog-head">
            <strong id="confirm-title">Confirmar ação</strong>

            <button class="ap-dialog-close" type="button" onclick="closeConfirmModal()" aria-label="Fechar">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="ap-dialog-body">
            <div class="ap-confirm-box">
                <i data-lucide="triangle-alert"></i>
                <p id="confirm-message"></p>
            </div>
        </div>

        <div class="ap-dialog-actions">
            <button class="ap-btn" type="button" onclick="closeConfirmModal()">Voltar</button>
            <button class="ap-btn primary" type="button" id="confirm-action">Confirmar</button>
        </div>
    </div>
</div>

<div class="ap-toast-root" id="ap-toast-root" aria-live="polite"></div>

<script>
    const AP_BASE = @json(url('/'.$tenantSlug.'/delivery/projects/'.$project->id.'/associates/'.$associate->id));
    const AP_TENANT = @json($tenantSlug);
    const AP_CSRF = @json(csrf_token());
    const AP_CAN_MANAGE = @json($canManageLimits);
    const AP_LIMITS_PAGE = @json($associateLimitsUrl);

    let apSection = 'summary';
    let apPage = 1;
    let apAbort = null;
    let apProducts = [];
    let apLimitRows = {};
    let apLimitSummary = {};
    let apTimer = null;
    let apPendingConfirmation = null;
    let apQuotaRows = new Map();
    let apQuotaOriginals = new Map();
    let apQuotaEditing = null;
    let apQuotaBatchUrl = null;
    let apQuotaBusy = false;

    const apRoot = document.getElementById('ap-content');
    const apTabs = [...document.querySelectorAll('.ap-tab')];

    const money = value => Number(value || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

    const qty = value => Number(value || 0).toLocaleString('pt-BR', {
        maximumFractionDigits: 3,
    });

    const esc = value => String(value ?? '').replace(
        /[&<>"']/g,
        character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[character])
    );

    function icons() {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function progressTone(percent) {
        if (percent >= 100) return 'danger';
        if (percent >= 80) return 'warning';
        return '';
    }

    function productTone(id) {
        const tones = [
            'tone-blue',
            'tone-violet',
            'tone-sky',
            'tone-amber',
            'tone-green',
        ];

        return tones[
            Math.abs(Number(id) || 0)
            % tones.length
        ];
    }

    function quotaSliderPercent(row) {
        const minimum = Number(row.delivered || 0);
        const maximum = Number(quotaSliderMaximum(row));
        const current = Number(row.quantity || 0);

        if (
            !Number.isFinite(maximum)
            || maximum <= minimum
        ) {
            return 100;
        }

        return Math.max(
            0,
            Math.min(
                100,
                (
                    (current - minimum)
                    / (maximum - minimum)
                ) * 100
            )
        );
    }

    function badgeIcon(value) {
        return {
            approved: 'circle-check',
            paid: 'badge-check',
            pending: 'clock-3',
            pending_payment: 'clock-3',
            partially_paid: 'circle-dollar-sign',
            rejected: 'circle-x',
            obsolete: 'triangle-alert',
            cancelled: 'ban',
        }[value] || 'circle-dashed';
    }

    function badge(value, label) {
        return `
            <span class="ap-badge ${esc(value)}">
                <i data-lucide="${badgeIcon(value)}"></i>
                ${esc(label || value || '-')}
            </span>
        `;
    }

    function stateView(title, description, icon = 'inbox') {
        return `
            <div class="ap-state">
                <div class="ap-state-icon">
                    <i data-lucide="${icon}"></i>
                </div>

                <strong>${esc(title)}</strong>

                <p>${esc(description)}</p>
            </div>
        `;
    }

    function showSkeleton() {
        apRoot.innerHTML = `
            <div class="ap-skeleton-grid">
                ${Array.from({ length: 8 }).map(() => '<div class="ap-skeleton"></div>').join('')}
            </div>
        `;
    }

    function notify(message, type = 'success') {
        const root = document.getElementById('ap-toast-root');
        const toast = document.createElement('div');

        toast.className = `ap-toast ${type === 'error' ? 'error' : ''}`;
        toast.innerHTML = `
            <div class="ap-toast-icon">
                <i data-lucide="${type === 'error' ? 'circle-alert' : 'circle-check'}"></i>
            </div>
            <span>${esc(message)}</span>
        `;

        root.appendChild(toast);
        icons();

        window.setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-5px)';
            toast.style.transition = 'all .18s ease';

            window.setTimeout(() => toast.remove(), 190);
        }, 3400);
    }

    async function api(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': AP_CSRF,
                ...(options.headers || {}),
            },
        });

        const data = await response.json().catch(() => ({
            message: 'A resposta do servidor não pôde ser interpretada.',
        }));

        if (!response.ok) {
            throw new Error(
                data.message
                || Object.values(data.errors || {}).flat()[0]
                || 'Não foi possível concluir a solicitação.'
            );
        }

        return data;
    }

    apTabs.forEach(button => {
        button.addEventListener('click', () => {
            apTabs.forEach(tab => tab.classList.remove('active'));
            button.classList.add('active');

            apSection = button.dataset.section;
            apPage = 1;
            history.replaceState(null, '', `#${apSection}`);
            loadSection();
        });
    });

    function showLimits() {
        document.querySelector('[data-section="limits"]')?.click();
    }

    async function loadSection() {
        if (apAbort) {
            apAbort.abort();
        }

        apAbort = new AbortController();
        showSkeleton();

        try {
            const data = await api(
                `${AP_BASE}/data/${apSection}?page=${apPage}`,
                {
                    signal: apAbort.signal,
                }
            );

            render(data);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            apRoot.innerHTML = stateView(
                'Não foi possível carregar esta seção',
                error.message,
                'wifi-off'
            );

            icons();
        }
    }

    function render(data) {
        ({
            summary: renderSummary,
            limits: renderLimits,
            deliveries: renderDeliveries,
            distributions: renderDistributions,
            receipts: renderReceipts,
            payments: renderPayments,
            history: renderHistory,
        }[apSection] || renderSummary)(data);
    }

    function statCard(label, value, helper = '', icon = 'circle-dollar-sign', tone = '') {
        return `
            <article class="ap-card">
                <div class="ap-card-inner">
                    <div class="ap-card-icon ${tone}">
                        <i data-lucide="${icon}"></i>
                    </div>

                    <div class="ap-card-copy">
                        <div class="ap-card-label">${esc(label)}</div>
                        <div class="ap-card-value">${value}</div>
                        ${helper ? `<div class="ap-card-helper">${helper}</div>` : ''}
                    </div>
                </div>
            </article>
        `;
    }

    function renderSummary(data) {
        const rawPercent =
            Number(
                data.financial_percent
                || 0
            );

        const percent =
            Math.min(
                100,
                Math.max(
                    0,
                    rawPercent
                )
            );

        const participation =
            data.participation_status === 'active'
                ? 'Entregas permitidas'
                : data.participation_status === 'blocked'
                    ? 'Entregas bloqueadas'
                    : data.restrict_participants
                        ? 'Participação pendente'
                        : 'Projeto aberto';

        const participationShort =
            data.participation_status === 'blocked'
                ? 'Bloqueada'
                : data.participation_status === 'active'
                    ? 'Ativa'
                    : data.restrict_participants
                        ? 'Pendente'
                        : 'Aberta';

        const financialTone =
            rawPercent >= 100
                ? 'danger'
                : rawPercent >= 80
                    ? 'warning'
                    : '';

        const availableValue =
            data.financial_remaining === null
                ? 'Sem teto'
                : money(
                    data.financial_remaining
                );

        const financialHelper =
            data.financial_limit === null
                ? 'Este associado não possui teto financeiro definido.'
                : (
                    `${Math.round(rawPercent)}% `
                    + 'do teto financeiro já foi utilizado.'
                );

        apRoot.innerHTML = `
            <section class="ap-overview">
                <header class="ap-overview-head">
                    <span
                        class="ap-overview-head-icon"
                        aria-hidden="true"
                    >
                        <i data-lucide="layout-dashboard"></i>
                    </span>

                    <div class="ap-overview-head-copy">
                        <h2>Visão geral do associado</h2>

                        <p>
                            Participação, limite financeiro
                            e andamento das entregas.
                        </p>
                    </div>
                </header>

                <div class="ap-overview-grid">
                    <div class="ap-financial-hero ${financialTone}">
                        <span class="ap-financial-label">
                            <i data-lucide="wallet-cards"></i>
                            Saldo financeiro disponível
                        </span>

                        <div class="ap-financial-value">
                            ${availableValue}
                        </div>

                        <div class="ap-financial-helper">
                            ${financialHelper}
                        </div>

                        ${data.financial_limit === null
                            ? ''
                            : `
                                <div
                                    class="ap-progress ${progressTone(rawPercent)}"
                                    role="progressbar"
                                    aria-label="Uso do limite financeiro"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="${Math.round(percent)}"
                                >
                                    <span
                                        style="width:${percent}%"
                                    ></span>
                                </div>
                            `}

                        <div class="ap-financial-facts">
                            <div class="ap-financial-fact">
                                <span>Teto financeiro</span>

                                <strong>
                                    ${data.financial_limit === null
                                        ? 'Sem limite'
                                        : money(
                                            data.financial_limit
                                        )}
                                </strong>
                            </div>

                            <div class="ap-financial-fact">
                                <span>Já utilizado</span>

                                <strong>
                                    ${money(
                                        data.financial_consumed
                                    )}
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div class="ap-overview-list">
                        <div class="ap-overview-row participation">
                            <span class="ap-overview-row-icon">
                                <i
                                    data-lucide="${data.participation_status === 'blocked'
                                        ? 'user-round-x'
                                        : 'user-round-check'}"
                                ></i>
                            </span>

                            <span class="ap-overview-row-copy">
                                <span>Participação</span>

                                <strong>
                                    ${esc(participation)}
                                </strong>
                            </span>

                            <strong class="ap-overview-row-value">
                                ${esc(participationShort)}
                            </strong>
                        </div>

                        <div class="ap-overview-row received">
                            <span class="ap-overview-row-icon">
                                <i data-lucide="package-check"></i>
                            </span>

                            <span class="ap-overview-row-copy">
                                <span>Quantidade recebida</span>

                                <strong>
                                    Total registrado para o associado
                                </strong>
                            </span>

                            <strong class="ap-overview-row-value">
                                ${qty(
                                    data.received_quantity
                                )}
                            </strong>
                        </div>

                        <div class="ap-overview-row distributed">
                            <span class="ap-overview-row-icon">
                                <i data-lucide="route"></i>
                            </span>

                            <span class="ap-overview-row-copy">
                                <span>Quantidade distribuída</span>

                                <strong>
                                    Volume que já recebeu destino
                                </strong>
                            </span>

                            <strong class="ap-overview-row-value">
                                ${qty(
                                    data.distributed_quantity
                                )}
                            </strong>
                        </div>

                        <div class="ap-overview-row pending">
                            <span class="ap-overview-row-icon">
                                <i data-lucide="package-open"></i>
                            </span>

                            <span class="ap-overview-row-copy">
                                <span>Sem distribuição</span>

                                <strong>
                                    ${Number(
                                        data.undistributed_quantity
                                        || 0
                                    ) > 0
                                        ? 'Ainda existe quantidade aguardando destino'
                                        : 'Nenhuma quantidade aguardando destino'}
                                </strong>
                            </span>

                            <strong class="ap-overview-row-value">
                                ${qty(
                                    data.undistributed_quantity
                                )}
                            </strong>
                        </div>

                        <div class="ap-overview-row receivable">
                            <span class="ap-overview-row-icon">
                                <i data-lucide="hand-coins"></i>
                            </span>

                            <span class="ap-overview-row-copy">
                                <span>A receber</span>

                                <strong>
                                    Saldo financeiro ainda pendente
                                </strong>
                            </span>

                            <strong class="ap-overview-row-value">
                                ${money(
                                    data.receivable
                                )}
                            </strong>
                        </div>

                        <div class="ap-overview-row receipts">
                            <span class="ap-overview-row-icon">
                                <i data-lucide="receipt-text"></i>
                            </span>

                            <span class="ap-overview-row-copy">
                                <span>Comprovantes</span>

                                <strong>
                                    ${data.obsolete_receipt_count || 0}
                                    obsoleto(s)
                                </strong>
                            </span>

                            <strong class="ap-overview-row-value">
                                ${String(
                                    data.receipt_count
                                    || 0
                                )}
                            </strong>
                        </div>
                    </div>
                </div>
            </section>
        `;

        icons();
    }

    async function renderLimits(data) {
        const summary = data.summary;

        apLimitSummary = summary;

        apLimitRows =
            Object.fromEntries(
                (data.products || [])
                    .map(
                        item => [
                            String(item.id),
                            item,
                        ]
                    )
            );

        let actions = '';

        if (AP_CAN_MANAGE) {
            actions += `
                <button
                    class="ap-btn violet"
                    type="button"
                    onclick="openFinancialLimit(
                        ${summary.financial_limit ?? ''}
                    )"
                >
                    <i data-lucide="wallet-cards"></i>
                    Teto financeiro
                </button>

                <button
                    class="ap-btn ${summary.participation_status === 'active'
                        ? 'warning'
                        : 'success'}"
                    type="button"
                    onclick="requestParticipation(
                        '${summary.participation_status === 'active'
                            ? 'blocked'
                            : 'active'}'
                    )"
                >
                    <i
                        data-lucide="${summary.participation_status === 'active'
                            ? 'user-round-x'
                            : 'user-round-check'}"
                    ></i>

                    ${summary.participation_status === 'active'
                        ? 'Bloquear entregas'
                        : 'Permitir entregas'}
                </button>
            `;
        }

        if (
            AP_CAN_MANAGE
            && summary.allows_product_limits
        ) {
            actions += `
                <a
                    class="ap-btn violet"
                    href="${AP_LIMITS_PAGE}"
                >
                    <i data-lucide="sliders-horizontal"></i>
                    Gerenciar cotas
                </a>
            `;
        }

        const participation =
            summary.participation_status === 'active'
                ? 'Ativa'
                : summary.participation_status === 'blocked'
                    ? 'Bloqueada'
                    : 'Não configurada';

        const productRows =
            (data.products || [])
                .map(item => {
                    const percent =
                        Number(
                            item.percent
                            || 0
                        );

                    const tone =
                        productTone(
                            item.product_id
                            || item.id
                        );

                    return `
                        <article
                            class="ap-limit-row ${tone}"
                            id="produto-resumo-${Number(
                                item.product_id
                                || item.id
                            )}"
                        >
                            <span
                                class="ap-limit-row-icon"
                                aria-hidden="true"
                            >
                                <i data-lucide="package"></i>
                            </span>

                            <div class="ap-limit-row-copy">
                                <strong>
                                    ${esc(item.product)}
                                </strong>

                                <span>
                                    ${money(
                                        item.reference_unit_price
                                    )}
                                    por
                                    ${esc(
                                        item.unit
                                        || 'unidade'
                                    )}
                                </span>
                            </div>

                            <div class="ap-limit-row-metrics">
                                <div class="ap-limit-row-metric">
                                    <span>Cota</span>

                                    <strong>
                                        ${qty(
                                            item.maximum_quantity
                                        )}
                                        ${esc(item.unit)}
                                    </strong>
                                </div>

                                <div class="ap-limit-row-metric">
                                    <span>Entregue</span>

                                    <strong>
                                        ${qty(
                                            item.delivered_quantity
                                        )}
                                        ${esc(item.unit)}
                                    </strong>
                                </div>

                                <div class="ap-limit-row-metric balance">
                                    <span>Disponível</span>

                                    <strong>
                                        ${qty(
                                            item.remaining_quantity
                                        )}
                                        ${esc(item.unit)}
                                    </strong>
                                </div>

                                <div class="ap-limit-row-metric">
                                    <span>Planejado</span>

                                    <strong>
                                        ${money(
                                            item.estimated_maximum_value
                                        )}
                                    </strong>
                                </div>
                            </div>

                            <div class="ap-limit-row-use">
                                <div class="ap-limit-row-use-head">
                                    <span>Uso da cota</span>

                                    <strong>
                                        ${Math.round(percent)}%
                                    </strong>
                                </div>

                                <div
                                    class="ap-progress ${progressTone(
                                        percent
                                    )}"
                                    role="progressbar"
                                    aria-label="Uso da cota de ${esc(
                                        item.product
                                    )}"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow="${Math.round(
                                        Math.min(
                                            100,
                                            percent
                                        )
                                    )}"
                                >
                                    <span
                                        style="width:${Math.min(
                                            100,
                                            percent
                                        )}%"
                                    ></span>
                                </div>
                            </div>

                            ${AP_CAN_MANAGE
                                ? `
                                    <a
                                        class="ap-btn violet"
                                        href="${AP_LIMITS_PAGE}#produto-${Number(
                                            item.product_id
                                        )}"
                                    >
                                        <i data-lucide="pencil"></i>
                                        Editar
                                    </a>
                                `
                                : ''}
                        </article>
                    `;
                })
                .join('');

        apRoot.innerHTML = `
            <section class="ap-section-card tone-violet">
                <header class="ap-section-head">
                    <span
                        class="ap-section-icon"
                        aria-hidden="true"
                    >
                        <i data-lucide="gauge"></i>
                    </span>

                    <div class="ap-section-head-copy">
                        <div class="ap-section-title">
                            Participação e limites
                        </div>

                        <div class="ap-section-subtitle">
                            Teto financeiro e cotas
                            disponíveis para este associado.
                        </div>
                    </div>

                    ${actions
                        ? `
                            <div class="ap-section-head-actions">
                                ${actions}
                            </div>
                        `
                        : ''}
                </header>

                <div class="ap-limit-summary">
                    <div class="ap-limit-fact violet">
                        <span class="ap-limit-fact-icon">
                            <i data-lucide="user-round-check"></i>
                        </span>

                        <span class="ap-limit-fact-copy">
                            <span>Participação</span>

                            <strong>
                                ${esc(participation)}
                            </strong>
                        </span>
                    </div>

                    <div class="ap-limit-fact">
                        <span class="ap-limit-fact-icon">
                            <i data-lucide="wallet-cards"></i>
                        </span>

                        <span class="ap-limit-fact-copy">
                            <span>Teto financeiro</span>

                            <strong>
                                ${summary.financial_limit === null
                                    ? 'Sem limite'
                                    : money(
                                        summary.financial_limit
                                    )}
                            </strong>
                        </span>
                    </div>

                    <div class="ap-limit-fact amber">
                        <span class="ap-limit-fact-icon">
                            <i data-lucide="circle-dollar-sign"></i>
                        </span>

                        <span class="ap-limit-fact-copy">
                            <span>Utilizado</span>

                            <strong>
                                ${money(
                                    summary.financial_consumed
                                )}
                            </strong>
                        </span>
                    </div>

                    <div class="ap-limit-fact sky">
                        <span class="ap-limit-fact-icon">
                            <i data-lucide="calculator"></i>
                        </span>

                        <span class="ap-limit-fact-copy">
                            <span>Planejado</span>

                            <strong>
                                ${money(
                                    summary.simulated_limit_value
                                )}
                            </strong>
                        </span>
                    </div>

                    <div class="ap-limit-fact green">
                        <span class="ap-limit-fact-icon">
                            <i data-lucide="hand-coins"></i>
                        </span>

                        <span class="ap-limit-fact-copy">
                            <span>Disponível</span>

                            <strong>
                                ${summary.financial_remaining === null
                                    ? 'Livre'
                                    : money(
                                        summary.financial_remaining
                                    )}
                            </strong>
                        </span>
                    </div>
                </div>

                <div class="ap-limit-products">
                    ${productRows
                        || stateView(
                            'Nenhum produto autorizado',
                            'Adicione uma cota de produto ou revise as regras do projeto.',
                            'package-x'
                        )}
                </div>
            </section>
        `;

        icons();
    }

    function toolbar() {
        return `
            <div class="ap-toolbar">
                <div class="ap-search-wrap">
                    <i class="ap-search-icon" data-lucide="search"></i>
                    <input
                        class="ap-input"
                        id="ap-search"
                        placeholder="Buscar produto, cliente ou registro"
                        oninput="debouncedReload()"
                    >
                </div>

                <select class="ap-select" id="ap-status" onchange="apPage=1;loadList()">
                    <option value="">Todos os status</option>
                    <option value="pending">Pendente</option>
                    <option value="approved">Aprovada</option>
                    <option value="rejected">Rejeitada</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </div>
        `;
    }

    function pager(data) {
        const current = Number(data.current_page || 1);
        const last = Number(data.last_page || 1);
        const from = Number(data.from || 0);
        const to = Number(data.to || 0);
        const total = Number(data.total || 0);

        return `
            <div class="ap-pager">
                <div class="ap-pager-info">
                    ${total
                        ? `Exibindo ${from} a ${to} de ${total} registros`
                        : `Página ${current} de ${last}`}
                </div>

                <div class="ap-pager-actions">
                    <button
                        class="ap-btn"
                        type="button"
                        ${current <= 1 ? 'disabled' : ''}
                        onclick="pageTo(${current - 1})"
                    >
                        <i data-lucide="chevron-left"></i>
                        Anterior
                    </button>

                    <button
                        class="ap-btn"
                        type="button"
                        ${current >= last ? 'disabled' : ''}
                        onclick="pageTo(${current + 1})"
                    >
                        Próxima
                        <i data-lucide="chevron-right"></i>
                    </button>
                </div>
            </div>
        `;
    }

    function sectionShell(
        title,
        subtitle,
        body,
        mobileBody = '',
        withToolbar = true
    ) {
        const meta = {
            deliveries: {
                tone: 'amber',
                icon: 'package-check',
            },
            distributions: {
                tone: 'sky',
                icon: 'route',
            },
            receipts: {
                tone: 'slate',
                icon: 'receipt-text',
            },
            payments: {
                tone: 'green',
                icon: 'wallet-cards',
            },
            history: {
                tone: 'slate',
                icon: 'history',
            },
        }[apSection] || {
            tone: 'blue',
            icon: 'layout-dashboard',
        };

        return `
            <section
                class="ap-section-card tone-${meta.tone}"
            >
                <header class="ap-section-head">
                    <span
                        class="ap-section-icon"
                        aria-hidden="true"
                    >
                        <i data-lucide="${meta.icon}"></i>
                    </span>

                    <div class="ap-section-head-copy">
                        <div class="ap-section-title">
                            ${esc(title)}
                        </div>

                        <div class="ap-section-subtitle">
                            ${esc(subtitle)}
                        </div>
                    </div>
                </header>

                ${withToolbar
                    ? toolbar()
                    : ''}

                ${body}
                ${mobileBody}
            </section>
        `;
    }

    function renderDeliveries(data) {
        const rows = (data.data || []).map(item => `
            <tr>
                <td>${esc(item.date)}</td>
                <td>${esc(item.product)}</td>
                <td>${qty(item.quantity)} ${esc(item.unit)}</td>
                <td>${qty(item.distributed)}</td>
                <td>${qty(item.remaining)}</td>
                <td>${badge(item.status, item.status_label)}</td>
                <td>${esc(item.registered_by)}</td>
                <td>
                    ${item.paid
                        ? badge('paid', 'Paga')
                        : item.billed
                            ? badge('pending', 'Faturada')
                            : item.in_receipt
                                ? badge('pending', 'Em comprovante')
                                : '-'}
                </td>
                <td>
                    <div class="ap-actions">
                        ${item.notes ? `<button type="button" class="delivery-note-trigger"
                            data-delivery-notes="${esc(item.notes)}"
                            data-delivery-notes-title="Observações da entrega"
                            data-delivery-notes-meta="${esc(item.product + ' · ' + item.date)}">Observações</button>` : ''}
                        ${item.can_approve ? `
                            <button class="ap-btn success" type="button" onclick="requestDeliveryAction(${item.id}, 'approve')">
                                <i data-lucide="check"></i>
                                Aprovar
                            </button>
                        ` : ''}

                        ${item.can_reject ? `
                            <button class="ap-btn danger" type="button" onclick="requestDeliveryAction(${item.id}, 'reject')">
                                <i data-lucide="x"></i>
                                Rejeitar
                            </button>
                        ` : ''}

                        <a
                            class="ap-btn ${item.status === 'approved' ? 'sky' : 'blue'}"
                            href="${esc(item.manage_url)}"
                        >
                            <i data-lucide="${item.status === 'approved' ? 'route' : 'eye'}"></i>
                            ${item.status === 'approved' ? 'Distribuir' : 'Detalhes'}
                        </a>
                    </div>
                </td>
            </tr>
        `).join('');

        const mobile = (data.data || []).map(item => `
            <article class="ap-mobile-card">
                <div class="ap-mobile-card-head">
                    <div class="ap-mobile-card-title">
                        <strong>${esc(item.product)}</strong>
                        <span>${esc(item.date)} · ${esc(item.registered_by)}</span>
                    </div>

                    ${badge(item.status, item.status_label)}
                </div>

                <div class="ap-mobile-card-body">
                    <div class="ap-mobile-metric">
                        <span>Recebido</span>
                        <strong>${qty(item.quantity)} ${esc(item.unit)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Distribuído</span>
                        <strong>${qty(item.distributed)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Saldo</span>
                        <strong>${qty(item.remaining)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Financeiro</span>
                        <strong>
                            ${item.paid
                                ? 'Paga'
                                : item.billed
                                    ? 'Faturada'
                                    : item.in_receipt
                                        ? 'Em comprovante'
                                        : 'Pendente'}
                        </strong>
                    </div>
                </div>

                <div class="ap-mobile-card-actions">
                    ${item.notes ? `<button type="button" class="delivery-note-trigger"
                        data-delivery-notes="${esc(item.notes)}"
                        data-delivery-notes-title="Observações da entrega"
                        data-delivery-notes-meta="${esc(item.product + ' · ' + item.date)}">Observações</button>` : ''}
                    ${item.can_approve ? `
                        <button class="ap-btn success" type="button" onclick="requestDeliveryAction(${item.id}, 'approve')">
                            Aprovar
                        </button>
                    ` : ''}

                    ${item.can_reject ? `
                        <button class="ap-btn danger" type="button" onclick="requestDeliveryAction(${item.id}, 'reject')">
                            Rejeitar
                        </button>
                    ` : ''}

                    <a
                        class="ap-btn ${item.status === 'approved' ? 'sky' : 'blue'}"
                        href="${esc(item.manage_url)}"
                    >
                        ${item.status === 'approved' ? 'Distribuir' : 'Detalhes'}
                    </a>
                </div>
            </article>
        `).join('');

        apRoot.innerHTML = sectionShell(
            'Entregas do associado',
            'Acompanhe status, quantidades distribuídas e situação financeira.',
            `
                <div class="ap-table-wrap">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Produto</th>
                                <th>Recebido</th>
                                <th>Distribuído</th>
                                <th>Saldo</th>
                                <th>Status</th>
                                <th>Registrado por</th>
                                <th>Financeiro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || `
                                <tr>
                                    <td colspan="9">
                                        ${stateView(
                                            'Nenhuma entrega encontrada',
                                            'As entregas deste associado aparecerão aqui.',
                                            'package-search'
                                        )}
                                    </td>
                                </tr>
                            `}
                        </tbody>
                    </table>
                </div>
            `,
            `<div class="ap-mobile-list">${mobile || stateView(
                'Nenhuma entrega encontrada',
                'As entregas deste associado aparecerão aqui.',
                'package-search'
            )}</div>`
        ) + pager(data);

        icons();
    }

    function renderDistributions(data) {
        const rows = (data.data || []).map(item => `
            <tr>
                <td>${esc(item.date)}</td>
                <td>${esc(item.product)}</td>
                <td>${esc(item.customer)}</td>
                <td>${qty(item.quantity)} ${esc(item.unit)}</td>
                <td>${money(item.unit_price)}</td>
                <td>${money(item.gross)}</td>
                <td>${esc(item.receipt || '-')}</td>
                <td>${item.paid ? badge('paid', 'Paga') : badge(item.billing_status, item.billing_status)}</td>
            </tr>
        `).join('');

        const mobile = (data.data || []).map(item => `
            <article class="ap-mobile-card">
                <div class="ap-mobile-card-head">
                    <div class="ap-mobile-card-title">
                        <strong>${esc(item.product)}</strong>
                        <span>${esc(item.customer)} · ${esc(item.date)}</span>
                    </div>

                    ${item.paid ? badge('paid', 'Paga') : badge(item.billing_status, item.billing_status)}
                </div>

                <div class="ap-mobile-card-body">
                    <div class="ap-mobile-metric">
                        <span>Quantidade</span>
                        <strong>${qty(item.quantity)} ${esc(item.unit)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Preço</span>
                        <strong>${money(item.unit_price)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Valor bruto</span>
                        <strong>${money(item.gross)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Comprovante</span>
                        <strong>${esc(item.receipt || 'Pendente')}</strong>
                    </div>
                </div>
            </article>
        `).join('');

        apRoot.innerHTML = sectionShell(
            'Distribuições',
            'Veja os destinos dos produtos e os valores que formam os comprovantes.',
            `
                <div class="ap-table-wrap">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Produto</th>
                                <th>Cliente</th>
                                <th>Quantidade</th>
                                <th>Preço</th>
                                <th>Bruto</th>
                                <th>Comprovante</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || `
                                <tr>
                                    <td colspan="8">
                                        ${stateView(
                                            'Nenhuma distribuição encontrada',
                                            'As distribuições deste associado aparecerão aqui.',
                                            'route-off'
                                        )}
                                    </td>
                                </tr>
                            `}
                        </tbody>
                    </table>
                </div>
            `,
            `<div class="ap-mobile-list">${mobile || stateView(
                'Nenhuma distribuição encontrada',
                'As distribuições deste associado aparecerão aqui.',
                'route-off'
            )}</div>`
        ) + pager(data);

        icons();
    }

    function renderReceipts(data) {
        const rows = (data.data || []).map(item => `
            <tr>
                <td>${esc(item.number)}</td>
                <td>${esc(item.date)}</td>
                <td>${money(item.gross)}</td>
                <td>${money(item.fees)}</td>
                <td>${money(item.net)}</td>
                <td>${money(item.paid)}</td>
                <td>${badge(item.status, item.status_label)}</td>
                <td>${esc(item.obsolete_reason || '-')}</td>
                <td>
                    ${item.reprint_url ? `
                        <a class="ap-btn slate" href="${esc(item.reprint_url)}">
                            <i data-lucide="printer"></i>
                            Reimprimir
                        </a>
                    ` : '-'}
                </td>
            </tr>
        `).join('');

        const mobile = (data.data || []).map(item => `
            <article class="ap-mobile-card">
                <div class="ap-mobile-card-head">
                    <div class="ap-mobile-card-title">
                        <strong>Comprovante ${esc(item.number)}</strong>
                        <span>${esc(item.date)}</span>
                    </div>

                    ${badge(item.status, item.status_label)}
                </div>

                <div class="ap-mobile-card-body">
                    <div class="ap-mobile-metric">
                        <span>Bruto</span>
                        <strong>${money(item.gross)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Taxas</span>
                        <strong>${money(item.fees)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Líquido</span>
                        <strong>${money(item.net)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Pago</span>
                        <strong>${money(item.paid)}</strong>
                    </div>
                </div>

                ${item.obsolete_reason ? `
                    <div style="padding:0 .75rem .75rem;color:var(--ap-faded);font-size:.6rem">
                        ${esc(item.obsolete_reason)}
                    </div>
                ` : ''}

                ${item.reprint_url ? `
                    <div class="ap-mobile-card-actions">
                        <a class="ap-btn slate" href="${esc(item.reprint_url)}">
                            <i data-lucide="printer"></i>
                            Reimprimir
                        </a>
                    </div>
                ` : ''}
            </article>
        `).join('');

        apRoot.innerHTML = sectionShell(
            'Comprovantes',
            'Consulte os valores, status e versões obsoletas.',
            `
                <div class="ap-table-wrap">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Data</th>
                                <th>Bruto</th>
                                <th>Taxas</th>
                                <th>Líquido</th>
                                <th>Pago</th>
                                <th>Status</th>
                                <th>Observação</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || `
                                <tr>
                                    <td colspan="9">
                                        ${stateView(
                                            'Nenhum comprovante',
                                            'Os comprovantes deste associado aparecerão aqui.',
                                            'receipt'
                                        )}
                                    </td>
                                </tr>
                            `}
                        </tbody>
                    </table>
                </div>
            `,
            `<div class="ap-mobile-list">${mobile || stateView(
                'Nenhum comprovante',
                'Os comprovantes deste associado aparecerão aqui.',
                'receipt'
            )}</div>`,
            false
        ) + pager(data);

        icons();
    }

    function renderPayments(data) {
        const rows = (data.data || []).map(item => `
            <tr>
                <td>${esc(item.receipt)}</td>
                <td>${esc(item.date)}</td>
                <td>${money(item.amount)}</td>
                <td>${esc(item.method || '-')}</td>
            </tr>
        `).join('');

        const mobile = (data.data || []).map(item => `
            <article class="ap-mobile-card">
                <div class="ap-mobile-card-head">
                    <div class="ap-mobile-card-title">
                        <strong>${esc(item.receipt)}</strong>
                        <span>${esc(item.date)}</span>
                    </div>
                </div>

                <div class="ap-mobile-card-body">
                    <div class="ap-mobile-metric">
                        <span>Valor</span>
                        <strong>${money(item.amount)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Método</span>
                        <strong>${esc(item.method || '-')}</strong>
                    </div>
                </div>
            </article>
        `).join('');

        apRoot.innerHTML = sectionShell(
            'Pagamentos',
            'Consulte os valores pagos e o método utilizado.',
            `
                <div class="ap-table-wrap">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Comprovante</th>
                                <th>Data</th>
                                <th>Valor</th>
                                <th>Método</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || `
                                <tr>
                                    <td colspan="4">
                                        ${stateView(
                                            'Nenhum pagamento',
                                            'Os pagamentos deste associado aparecerão aqui.',
                                            'wallet-minimal'
                                        )}
                                    </td>
                                </tr>
                            `}
                        </tbody>
                    </table>
                </div>
            `,
            `<div class="ap-mobile-list">${mobile || stateView(
                'Nenhum pagamento',
                'Os pagamentos deste associado aparecerão aqui.',
                'wallet-minimal'
            )}</div>`,
            false
        ) + pager(data);

        icons();
    }

    function renderHistory(data) {
        const rows = (data.data || []).map(item => `
            <tr>
                <td>${esc(item.date)}</td>
                <td>${esc(item.actor)}</td>
                <td>${esc(item.action)}</td>
                <td>${esc(item.subject)}</td>
            </tr>
        `).join('');

        const mobile = (data.data || []).map(item => `
            <article class="ap-mobile-card">
                <div class="ap-mobile-card-head">
                    <div class="ap-mobile-card-title">
                        <strong>${esc(item.action)}</strong>
                        <span>${esc(item.date)} · ${esc(item.actor)}</span>
                    </div>
                </div>

                <div class="ap-mobile-card-body" style="grid-template-columns:1fr">
                    <div class="ap-mobile-metric">
                        <span>Registro</span>
                        <strong>${esc(item.subject)}</strong>
                    </div>
                </div>
            </article>
        `).join('');

        apRoot.innerHTML = sectionShell(
            'Histórico de atividades',
            'Alterações recentes neste projeto.',
            `
                <div class="ap-table-wrap">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Responsável</th>
                                <th>Ação</th>
                                <th>Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || `
                                <tr>
                                    <td colspan="4">
                                        ${stateView(
                                            'Nenhuma atividade registrada',
                                            'As alterações deste associado aparecerão aqui.',
                                            'history'
                                        )}
                                    </td>
                                </tr>
                            `}
                        </tbody>
                    </table>
                </div>
            `,
            `<div class="ap-mobile-list">${mobile || stateView(
                'Nenhuma atividade registrada',
                'As alterações deste associado aparecerão aqui.',
                'history'
            )}</div>`,
            false
        ) + pager(data);

        icons();
    }

    function debouncedReload() {
        window.clearTimeout(apTimer);
        apTimer = window.setTimeout(() => {
            apPage = 1;
            loadList();
        }, 350);
    }

    function loadList() {
        const search = document.getElementById('ap-search')?.value || '';
        const status = document.getElementById('ap-status')?.value || '';

        if (apAbort) {
            apAbort.abort();
        }

        apAbort = new AbortController();

        api(
            `${AP_BASE}/data/${apSection}?page=${apPage}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`,
            {
                signal: apAbort.signal,
            }
        )
            .then(render)
            .catch(error => {
                if (error.name !== 'AbortError') {
                    notify(error.message, 'error');
                }
            });
    }

    function pageTo(page) {
        const target = Number(page);

        if (!Number.isFinite(target) || target < 1) {
            return;
        }

        apPage = target;
        loadList();

        document.getElementById('associate-project-app')?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }

    function requestDeliveryAction(id, action) {
        const approving = action === 'approve';

        openConfirmModal(
            approving ? 'Aprovar entrega' : 'Rejeitar entrega',
            approving
                ? 'A entrega será aprovada e poderá seguir para distribuição.'
                : 'A entrega será rejeitada. Confirme apenas se a análise já foi concluída.',
            async () => {
                try {
                    document.getElementById('confirm-action').disabled = true;

                    const path = `/${AP_TENANT}/delivery/deliveries/${id}/${action}`;
                    const data = await api(path, {
                        method: 'POST',
                        body: '{}',
                    });

                    closeConfirmModal();
                    notify(data.message || 'Entrega atualizada.');
                    loadList();
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    document.getElementById('confirm-action').disabled = false;
                }
            }
        );
    }

    function openFinancialLimit(value) {
        document.getElementById('limit-kind').value = 'financial';
        document.getElementById('limit-title').textContent = 'Limite financeiro';
        document.getElementById('limit-value-label').textContent = 'Valor total';
        document.getElementById('limit-value').step = '0.01';
        document.getElementById('limit-value').value = value ?? '';
        document.getElementById('product-field').hidden = true;
        document.getElementById('limit-simulation').hidden = true;
        openLimitModal();
    }

    async function openProductLimitsManager(focusProductId = null) {
        if (apQuotaBusy) return;

        const modal = document.getElementById('product-limits-modal');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.getElementById('quota-list').innerHTML = stateView(
            'Carregando produtos',
            'Aguarde um instante.',
            'loader-circle'
        );

        try {
            const [limits, products] = await Promise.all([
                api(`${AP_BASE}/data/limits`),
                api(`${AP_BASE}/data/products`),
            ]);

            hydrateQuotaManager(limits, products.data || []);
            renderQuotaManager();

            if (focusProductId && apQuotaRows.has(String(focusProductId))) {
                unlockQuotaCard(focusProductId);
                window.setTimeout(() => {
                    document.getElementById(`quota-card-${focusProductId}`)?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                    });
                }, 80);
            }
        } catch (error) {
            notify(error.message, 'error');
            closeProductLimitsManager();
        }
    }

    function hydrateQuotaManager(limits, products) {
        apProducts = products;
        apLimitSummary = limits.summary || {};
        apQuotaBatchUrl = limits.batch_update_url;
        apQuotaRows = new Map();
        apQuotaOriginals = new Map();
        apQuotaEditing = null;

        const currentByProduct = new Map(
            (limits.products || []).map(item => [String(item.product_id), item])
        );

        products.forEach(product => {
            const current = currentByProduct.get(String(product.id));
            if (!current) return;

            const row = quotaRow(product, current);
            apQuotaRows.set(String(product.id), row);
            apQuotaOriginals.set(String(product.id), Number(row.quantity));
        });
    }

    function quotaRow(product, current = null) {
        const delivered = Number(current?.delivered_quantity ?? product.delivered_quantity ?? 0);
        const quantity = Number(current?.maximum_quantity ?? Math.max(delivered, .001));

        return {
            productId: Number(product.id),
            name: product.name || current?.product || 'Produto',
            unit: product.unit || current?.unit || '',
            price: Number(product.price ?? current?.reference_unit_price ?? 0),
            quantity,
            delivered,
            projectMaximum: product.project_maximum === null ? null : Number(product.project_maximum),
            allocatedToOthers: Number(product.allocated_to_others || 0),
            maximum: product.available_for_associate === null
                ? null
                : Number(product.available_for_associate),
            deleteUrl: current?.delete_url || null,
            isNew: !current,
        };
    }

    function renderQuotaManager() {
        const root = document.getElementById('quota-list');
        const rows = [...apQuotaRows.values()].sort((left, right) =>
            left.name.localeCompare(right.name, 'pt-BR')
        );

        root.innerHTML = rows.length
            ? rows.map(renderQuotaCard).join('')
            : `
                <div class="ap-quota-empty">
                    <span class="ap-state-icon">
                        <i data-lucide="package-open"></i>
                    </span>

                    <span>
                        Nenhum produto configurado.
                        Use a busca acima para adicionar o primeiro.
                    </span>
                </div>
            `;

        refreshQuotaState();
        renderQuotaProductOptions();
        icons();
    }

    function renderQuotaCard(row) {
        const key =
            String(row.productId);

        const editing =
            apQuotaEditing === key;

        const sliderMaximum =
            quotaSliderMaximum(row);

        const percent =
            Number(row.quantity) > 0
                ? Math.min(
                    100,
                    (
                        row.delivered
                        / Number(row.quantity)
                    ) * 100
                )
                : 0;

        const tone =
            productTone(
                row.productId
            );

        return `
            <article
                class="ap-quota-card ${tone} ${editing ? 'editing' : ''}"
                id="quota-card-${row.productId}"
            >
                <div class="ap-quota-card-head">
                    <span
                        class="ap-quota-card-icon"
                        aria-hidden="true"
                    >
                        <i data-lucide="package"></i>
                    </span>

                    <div class="ap-quota-card-title">
                        <strong>
                            ${esc(row.name)}
                        </strong>

                        <small>
                            ${money(row.price)}
                            por
                            ${esc(
                                row.unit
                                || 'unidade'
                            )}
                        </small>
                    </div>

                    <div class="ap-quota-card-actions">
                        <button
                            class="ap-btn edit"
                            type="button"
                            onclick="unlockQuotaCard(
                                ${row.productId}
                            )"
                            title="${editing
                                ? 'Editando cota'
                                : 'Editar cota'}"
                            aria-label="${editing
                                ? 'Editando cota'
                                : 'Editar cota'}"
                        >
                            <i
                                data-lucide="${editing
                                    ? 'check'
                                    : 'pencil'}"
                            ></i>

                            <span>
                                ${editing
                                    ? 'Editando'
                                    : 'Editar'}
                            </span>
                        </button>

                        <button
                            class="ap-btn danger"
                            type="button"
                            onclick="requestQuotaRemoval(
                                ${row.productId}
                            )"
                            title="Remover produto"
                            aria-label="Remover produto"
                        >
                            <i data-lucide="trash-2"></i>
                            <span>Remover</span>
                        </button>
                    </div>
                </div>

                <div class="ap-quota-numbers">
                    <div class="ap-quota-number">
                        <span>Já entregue</span>

                        <strong>
                            ${qty(row.delivered)}
                            ${esc(row.unit)}
                        </strong>
                    </div>

                    <div class="ap-quota-number">
                        <span>Cota definida</span>

                        <strong
                            id="quota-label-${row.productId}"
                        >
                            ${qty(row.quantity)}
                            ${esc(row.unit)}
                        </strong>
                    </div>

                    <div class="ap-quota-number">
                        <span>Disponível</span>

                        <strong
                            id="quota-remaining-${row.productId}"
                        >
                            ${qty(
                                Math.max(
                                    0,
                                    row.quantity
                                    - row.delivered
                                )
                            )}
                            ${esc(row.unit)}
                        </strong>
                    </div>

                    <div class="ap-quota-number">
                        <span>Valor planejado</span>

                        <strong
                            id="quota-value-${row.productId}"
                        >
                            ${money(
                                row.quantity
                                * row.price
                            )}
                        </strong>
                    </div>
                </div>

                <div class="ap-quota-use">
                    <span>Uso da cota</span>

                    <span
                        id="quota-use-label-${row.productId}"
                    >
                        ${Math.round(percent)}%
                        já entregue
                    </span>
                </div>

                <div
                    class="ap-progress ${progressTone(percent)}"
                >
                    <span
                        id="quota-progress-${row.productId}"
                        style="width:${percent}%"
                    ></span>
                </div>

                <div class="ap-quota-controls">
                    <label>
                        Ajuste rápido

                        <input
                            class="ap-quota-slider"
                            id="quota-slider-${row.productId}"
                            type="range"
                            min="${row.delivered}"
                            max="${sliderMaximum}"
                            step="0.001"
                            value="${row.quantity}"
                            style="--slider-pct:${quotaSliderPercent(row)}%"
                            ${editing
                                ? ''
                                : 'disabled'}
                            oninput="setQuotaQuantity(
                                ${row.productId},
                                this.value,
                                'slider'
                            )"
                        >
                    </label>

                    <label>
                        Cota máxima
                        (${esc(row.unit)})

                        <input
                            class="ap-quota-input"
                            id="quota-input-${row.productId}"
                            type="number"
                            min="${row.delivered}"
                            ${row.maximum === null
                                ? ''
                                : `max="${row.maximum}"`}
                            step="0.001"
                            value="${row.quantity}"
                            ${editing
                                ? ''
                                : 'disabled'}
                            oninput="setQuotaQuantity(
                                ${row.productId},
                                this.value,
                                'input'
                            )"
                        >
                    </label>
                </div>

                <div
                    class="ap-quota-message"
                    id="quota-message-${row.productId}"
                >
                    ${quotaAvailabilityText(row)}
                </div>
            </article>
        `;
    }

    function quotaSliderMaximum(row) {
        if (row.maximum !== null) {
            return Math.max(row.delivered, row.maximum);
        }

        return Math.max(100, row.delivered, row.quantity, Math.ceil(row.quantity * 1.5));
    }

    function quotaAvailabilityText(row) {
        if (row.projectMaximum === null) {
            return 'Sem meta geral para este produto. O limite financeiro continua sendo validado.';
        }

        return `Meta do projeto: ${qty(row.projectMaximum)} ${row.unit} · reservado aos demais: ${qty(row.allocatedToOthers)} · máximo para este associado: ${qty(row.maximum)}.`;
    }

    function unlockQuotaCard(productId) {
        apQuotaEditing = String(productId);
        renderQuotaManager();

        window.setTimeout(() => {
            const input = document.getElementById(`quota-input-${productId}`);
            input?.focus();
            input?.select();
        }, 30);
    }

    function setQuotaQuantity(productId, rawValue, source) {
        const row = apQuotaRows.get(String(productId));
        if (!row) return;

        const input = document.getElementById(`quota-input-${productId}`);
        const slider = document.getElementById(`quota-slider-${productId}`);
        const parsed = Number(String(rawValue).replace(',', '.'));

        if (source === 'input' && rawValue === '') {
            row.quantity = NaN;
            refreshQuotaState();
            return;
        }
        if (!Number.isFinite(parsed)) return;

        row.quantity = Math.max(0, parsed);
        if (source !== 'input' && input) input.value = String(row.quantity);
        if (source !== 'slider' && slider) {
            slider.max =
                String(
                    Math.max(
                        quotaSliderMaximum(row),
                        row.quantity
                    )
                );

            slider.value =
                String(
                    Math.min(
                        row.quantity,
                        Number(slider.max)
                    )
                );
        }

        if (slider) {
            slider.style.setProperty(
                '--slider-pct',
                `${quotaSliderPercent(row)}%`
            );
        }

        refreshQuotaState();
    }

    function quotaValidation(row) {
        if (!Number.isFinite(row.quantity) || row.quantity <= 0) {
            return 'Informe uma cota maior que zero.';
        }
        if (row.quantity + .000001 < row.delivered) {
            return `A cota não pode ser menor que ${qty(row.delivered)} ${row.unit}, pois essa quantidade já foi entregue.`;
        }
        if (row.maximum !== null && row.quantity > row.maximum + .000001) {
            return `A cota máxima disponível para este associado é ${qty(row.maximum)} ${row.unit}.`;
        }

        return '';
    }

    function quotaTotals() {
        const total = [...apQuotaRows.values()].reduce(
            (sum, row) => sum + (Number.isFinite(row.quantity) ? row.quantity * row.price : 0),
            0
        );
        const ceiling = apLimitSummary.financial_limit === null
            ? null
            : Number(apLimitSummary.financial_limit || 0);

        return { total, ceiling, excess: ceiling === null ? 0 : Math.max(0, total - ceiling) };
    }

    function quotaHasChanges() {
        if (apQuotaRows.size !== apQuotaOriginals.size) return true;

        return [...apQuotaRows.entries()].some(([key, row]) =>
            Math.abs(Number(row.quantity) - Number(apQuotaOriginals.get(key))) > .000001
        );
    }

    function refreshQuotaState() {
        const totals = quotaTotals();
        const summary = document.getElementById('quota-summary');
        const invalidRows = [];

        apQuotaRows.forEach(row => {
            const validation = quotaValidation(row);
            const card = document.getElementById(`quota-card-${row.productId}`);
            const message = document.getElementById(`quota-message-${row.productId}`);
            const percent = Number(row.quantity) > 0
                ? Math.min(100, (row.delivered / Number(row.quantity)) * 100)
                : 0;

            if (validation) invalidRows.push(row.productId);
            card?.classList.toggle('invalid', Boolean(validation));
            if (message) {
                message.textContent = validation || quotaAvailabilityText(row);
                message.classList.toggle('error', Boolean(validation));
            }

            const label = document.getElementById(`quota-label-${row.productId}`);
            const remaining = document.getElementById(`quota-remaining-${row.productId}`);
            const value = document.getElementById(`quota-value-${row.productId}`);
            const progress = document.getElementById(`quota-progress-${row.productId}`);
            const useLabel = document.getElementById(`quota-use-label-${row.productId}`);
            const slider = document.getElementById(`quota-slider-${row.productId}`);
            if (label) label.textContent = `${qty(row.quantity)} ${row.unit}`;
            if (remaining) remaining.textContent = `${qty(Math.max(0, row.quantity - row.delivered))} ${row.unit}`;
            if (value) value.textContent = money(row.quantity * row.price);
            if (progress) {
                progress.style.width = `${percent}%`;
                progress.parentElement?.classList.toggle('warning', percent >= 80 && percent < 100);
                progress.parentElement?.classList.toggle('danger', percent >= 100);
            }
            if (useLabel) {
                useLabel.textContent =
                    `${Math.round(percent)}% já entregue`;
            }

            if (slider) {
                slider.style.setProperty(
                    '--slider-pct',
                    `${quotaSliderPercent(row)}%`
                );
            }
        });

        const ceilingText = totals.ceiling === null
            ? 'Sem teto financeiro definido'
            : `${money(Math.max(0, totals.ceiling - totals.total))} ainda disponível`;
        summary.classList.toggle('danger', totals.excess > .005);
        summary.innerHTML = `
            <div>
                <small>Valor simulado de todas as cotas</small>
                <strong>${money(totals.total)}</strong>
                <div class="ap-progress ${totals.excess > .005 ? 'danger' : progressTone(
                    totals.ceiling ? (totals.total / totals.ceiling) * 100 : 0
                )}">
                    <span style="width:${totals.ceiling ? Math.min(100, (totals.total / totals.ceiling) * 100) : 0}%"></span>
                </div>
            </div>
            <div class="ap-quota-summary-value">
                <small>${totals.excess > .005 ? 'Teto ultrapassado' : 'Situação financeira'}</small>
                <strong>${totals.excess > .005 ? money(totals.excess) + ' acima' : ceilingText}</strong>
            </div>
        `;

        const save = document.getElementById('quota-save-all');
        save.disabled = apQuotaBusy
            || !quotaHasChanges()
            || invalidRows.length > 0
            || totals.excess > .005;
    }

    function toggleQuotaProductOptions() {
        const options = document.getElementById('quota-product-options');
        options.hidden = !options.hidden;
        if (!options.hidden) {
            document.getElementById('quota-product-search')?.focus();
            renderQuotaProductOptions();
        }
    }

    function renderQuotaProductOptions() {
        const root = document.getElementById('quota-product-options');
        const term = (document.getElementById('quota-product-search')?.value || '')
            .trim()
            .toLocaleLowerCase('pt-BR');
        const available = apProducts.filter(product =>
            !apQuotaRows.has(String(product.id))
            && (!term || String(product.name || '').toLocaleLowerCase('pt-BR').includes(term))
        );

        root.innerHTML = available.length
            ? available.slice(0, 60).map(product => `
                <button
                    class="ap-quota-product-option ${productTone(product.id)}"
                    type="button"
                    onclick="addQuotaProduct(${Number(product.id)})"
                >
                    <span
                        class="ap-quota-option-icon"
                        aria-hidden="true"
                    >
                        <i data-lucide="package-plus"></i>
                    </span>

                    <div>
                        <strong>
                            ${esc(product.name)}
                        </strong>

                        <small>
                            ${money(product.price)}
                            por
                            ${esc(
                                product.unit
                                || 'unidade'
                            )}
                        </small>
                    </div>

                    <span>
                        ${product.available_for_associate === null
                            ? 'Sem meta geral'
                            : (
                                qty(
                                    product.available_for_associate
                                )
                                + ' disponível'
                            )}
                    </span>
                </button>
            `).join('')
            : `
                <div class="ap-quota-empty">
                    <span class="ap-state-icon">
                        <i data-lucide="search-x"></i>
                    </span>

                    <span>
                        Nenhum produto disponível
                        para esta busca.
                    </span>
                </div>
            `;

        icons();
    }

    function addQuotaProduct(productId) {
        const product = apProducts.find(item => String(item.id) === String(productId));
        if (!product) return;

        const row = quotaRow(product);
        apQuotaRows.set(String(productId), row);
        apQuotaEditing = String(productId);
        document.getElementById('quota-product-options').hidden = true;
        document.getElementById('quota-product-search').value = '';
        renderQuotaManager();

        window.setTimeout(() => {
            document.getElementById(`quota-card-${productId}`)?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
            const input = document.getElementById(`quota-input-${productId}`);
            input?.focus();
            input?.select();
        }, 50);
    }

    function requestQuotaRemoval(productId) {
        const row = apQuotaRows.get(String(productId));
        if (!row) return;

        if (row.isNew || !row.deleteUrl) {
            apQuotaRows.delete(String(productId));
            apQuotaEditing = null;
            renderQuotaManager();
            return;
        }

        openConfirmModal(
            'Remover limite do produto',
            `A definição de ${row.name} será removida. Entregas já registradas serão preservadas.`,
            async () => {
                const button = document.getElementById('confirm-action');
                try {
                    button.disabled = true;
                    const response = await api(row.deleteUrl, {
                        method: 'DELETE',
                        body: '{}',
                    });
                    closeConfirmModal();
                    notify(response.message || 'Limite removido.');
                    hydrateQuotaManager(response.data, apProducts);
                    renderQuotaManager();
                    if (apSection === 'limits') loadSection();
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    button.disabled = false;
                }
            }
        );
    }

    async function saveProductLimitChanges() {
        if (apQuotaBusy || !apQuotaBatchUrl) return;

        const changes = [...apQuotaRows.entries()]
            .filter(([key, row]) =>
                !apQuotaOriginals.has(key)
                || Math.abs(Number(row.quantity) - Number(apQuotaOriginals.get(key))) > .000001
            )
            .map(([, row]) => ({
                product_id: row.productId,
                max_quantity: Number(row.quantity.toFixed(3)),
            }));

        if (!changes.length) return;

        const button = document.getElementById('quota-save-all');
        try {
            apQuotaBusy = true;
            button.disabled = true;
            button.innerHTML = '<i data-lucide="loader-circle"></i> Salvando';
            icons();

            const response = await api(apQuotaBatchUrl, {
                method: 'PUT',
                body: JSON.stringify({ limits: changes }),
            });
            notify(response.message || 'Cotas atualizadas.');
            closeProductLimitsManager();
            if (apSection === 'limits') loadSection();
        } catch (error) {
            notify(error.message, 'error');
        } finally {
            apQuotaBusy = false;
            button.innerHTML = '<i data-lucide="save"></i> Salvar alterações';
            refreshQuotaState();
            icons();
        }
    }

    function closeProductLimitsManager() {
        document.getElementById('product-limits-modal').classList.remove('open');
        document.getElementById('product-limits-modal').setAttribute('aria-hidden', 'true');
        document.getElementById('quota-product-options').hidden = true;
        document.getElementById('quota-product-search').value = '';
        apQuotaEditing = null;
    }

    function openProductLimit() {
        openProductLimitsManager();
    }

    function openProductLimitById(id) {
        const current = apLimitRows[String(id)];
        openProductLimitsManager(current?.product_id || id);
    }

    function requestParticipation(status) {
        const allowing = status === 'active';

        openConfirmModal(
            allowing ? 'Permitir novas entregas' : 'Bloquear novas entregas',
            allowing
                ? 'O associado será ativado neste projeto e poderá registrar novas entregas.'
                : 'O associado não poderá registrar novas entregas. Os registros históricos serão preservados.',
            async () => {
                try {
                    document.getElementById('confirm-action').disabled = true;

                    const data = await api(`${AP_BASE}/participation`, {
                        method: 'PUT',
                        body: JSON.stringify({ status }),
                    });

                    closeConfirmModal();
                    notify(data.message || 'Participação atualizada.');
                    loadSection();
                } catch (error) {
                    notify(error.message, 'error');
                } finally {
                    document.getElementById('confirm-action').disabled = false;
                }
            }
        );
    }

    function openLimitModal() {
        document.getElementById('limit-modal').classList.add('open');
        document.getElementById('limit-modal').setAttribute('aria-hidden', 'false');

        window.setTimeout(() => {
            document.getElementById('limit-value')?.focus();
        }, 50);

        icons();
    }

    function closeLimitModal() {
        document.getElementById('limit-modal').classList.remove('open');
        document.getElementById('limit-modal').setAttribute('aria-hidden', 'true');
        document.getElementById('limit-product').disabled = false;
        document.getElementById('limit-notes').value = '';
        document.getElementById('limit-availability').hidden = true;
        document.getElementById('limit-simulation').hidden = true;
        document.getElementById('limit-value').removeAttribute('max');
    }

    function openConfirmModal(title, message, callback) {
        apPendingConfirmation = callback;

        document.getElementById('confirm-title').textContent = title;
        document.getElementById('confirm-message').textContent = message;
        document.getElementById('confirm-modal').classList.add('open');
        document.getElementById('confirm-modal').setAttribute('aria-hidden', 'false');

        icons();
    }

    function closeConfirmModal() {
        apPendingConfirmation = null;
        document.getElementById('confirm-modal').classList.remove('open');
        document.getElementById('confirm-modal').setAttribute('aria-hidden', 'true');
    }

    document.getElementById('confirm-action').addEventListener('click', () => {
        if (typeof apPendingConfirmation === 'function') {
            apPendingConfirmation();
        }
    });

    document.getElementById('limit-form').addEventListener('submit', async event => {
        event.preventDefault();

        const kind = document.getElementById('limit-kind').value;

        const body = kind === 'financial'
            ? {
                financial_limit: document.getElementById('limit-value').value || null,
                notes: document.getElementById('limit-notes').value,
            }
            : {
                product_id: document.getElementById('limit-product').value,
                max_quantity: document.getElementById('limit-value').value,
                notes: document.getElementById('limit-notes').value,
            };

        try {
            const submitButton = event.currentTarget.querySelector('button[type="submit"]');
            submitButton.disabled = true;

            const data = await api(
                `${AP_BASE}/limits/${kind === 'financial' ? 'financial' : 'product'}`,
                {
                    method: 'PUT',
                    body: JSON.stringify(body),
                }
            );

            closeLimitModal();
            notify(data.message || 'Limite atualizado.');
            apProducts = [];
            loadSection();
        } catch (error) {
            notify(error.message, 'error');
        } finally {
            const submitButton = event.currentTarget.querySelector('button[type="submit"]');
            submitButton.disabled = false;
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeLimitModal();
            closeProductLimitsManager();
            closeConfirmModal();
        }
    });

    const initialHash = window.location.hash.replace('#', '');
    const validSections = [
        'summary',
        'limits',
        'deliveries',
        'distributions',
        'receipts',
        'payments',
        'history',
    ];

    if (validSections.includes(initialHash)) {
        document.querySelector(`[data-section="${initialHash}"]`)?.click();
    } else {
        loadSection();
    }

    icons();
</script>
@endsection
