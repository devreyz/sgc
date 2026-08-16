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
        --ap-danger: var(--color-danger, #ef4444);
        --ap-warning: var(--color-warning, #f59e0b);
        --ap-info: var(--color-info, #0284c7);
    }

    .ap-shell {
        display: grid;
        width: min(100%, 1920px);
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

    .ap-hero {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .7rem;
        overflow: hidden;
        border: 1px solid var(--ap-border);
        border-radius: 15px;
        background:
            radial-gradient(circle at 100% 0, rgba(34,197,94,.075), transparent 17rem),
            linear-gradient(180deg, var(--ap-soft), #fff);
        box-shadow: var(--shadow-sm);
    }

    .ap-hero-wave { display: none; }

    .ap-hero-main {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .62rem;
        align-items: center;
        padding: .72rem .76rem;
    }

    .ap-back {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        align-self: start;
        margin: 0;
        padding: 0;
        border: 1px solid var(--ap-border);
        border-radius: 11px;
        background: #fff;
        color: var(--ap-secondary);
        text-decoration: none;
        font-size: 0;
        transition: border-color 150ms ease, background 150ms ease, color 150ms ease, transform 150ms ease;
    }

    .ap-back:hover,
    .ap-back:focus-visible {
        border-color: rgba(34,197,94,.28);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
        outline: none;
        transform: translateX(-1px);
    }

    .ap-back > i,
    .ap-back > svg { width: 17px; height: 17px; }

    .ap-hero-badges {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .3rem;
        grid-column: 2;
        margin: 0 0 .15rem;
    }

    .ap-hero-badge {
        display: grid;
        min-height: 25px;
        grid-template-columns: auto auto;
        gap: .25rem;
        align-items: center;
        padding: .2rem .38rem;
        border-radius: 999px;
        background: var(--ap-green-soft);
        color: var(--ap-green);
        font-size: .66rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .ap-hero-badge:nth-child(2) {
        background: var(--ap-violet-soft);
        color: var(--ap-violet);
    }

    .ap-hero-badge > i,
    .ap-hero-badge > svg { width: 12px; height: 12px; }

    .ap-title {
        grid-column: 2;
        min-width: 0;
        max-width: 100%;
        margin: 0;
        color: var(--ap-text);
        font-size: clamp(1.05rem, 2vw, 1.25rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.28;
        overflow-wrap: anywhere;
    }

    .ap-subtitle {
        grid-column: 2;
        margin: .08rem 0 0;
        color: var(--ap-secondary);
        font-size: .75rem;
        line-height: 1.45;
    }

    .ap-meta-row {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        grid-column: 2;
        gap: .42rem;
        margin-top: .12rem;
        color: var(--ap-faded);
        font-size: .7rem;
        line-height: 1.4;
    }

    .ap-meta-row > span {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, auto);
        gap: .22rem;
        align-items: center;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ap-meta-row > span > i,
    .ap-meta-row > span > svg { width: 13px; height: 13px; }

    .ap-hero-aside {
        display: grid;
        min-width: 255px;
        align-content: center;
        padding: .66rem .72rem;
        background: #fff;
    }

    .ap-aside-label {
        color: var(--ap-faded);
        font-size: .68rem;
        font-weight: 760;
    }

    .ap-hero-aside > strong {
        margin-top: .08rem;
        color: var(--ap-text);
        font-size: .79rem;
        font-weight: 820;
    }

    .ap-hero-aside > p {
        margin: .1rem 0 0;
        color: var(--ap-faded);
        font-size: .69rem;
        line-height: 1.4;
    }

    .ap-hero-actions {
        display: grid;
        grid-template-columns: 1fr;
        gap: .38rem;
        margin-top: .55rem;
    }

    .ap-hero-btn {
        display: grid;
        min-height: 42px;
        grid-template-columns: auto auto;
        gap: .34rem;
        align-items: center;
        justify-content: center;
        padding: .48rem .65rem;
        border: 1px solid var(--ap-border-strong);
        border-radius: 10px;
        background: #fff;
        color: var(--ap-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .73rem;
        font-weight: 800;
        text-decoration: none;
        white-space: nowrap;
        transition: box-shadow 150ms ease, transform 150ms ease;
    }

    .ap-hero-btn.primary {
        border-color: var(--ap-primary-dark);
        background: linear-gradient(135deg, var(--ap-primary), var(--ap-primary-dark));
        color: #fff;
        box-shadow: 0 7px 16px rgba(22,163,74,.13);
    }

    .ap-hero-btn.secondary {
        border-color: rgba(124,58,237,.18);
        background: var(--ap-violet-soft);
        color: var(--ap-violet);
    }

    .ap-hero-btn:hover,
    .ap-hero-btn:focus-visible { outline: none; transform: translateY(-1px); }

    .ap-hero-btn.primary:hover,
    .ap-hero-btn.primary:focus-visible {
        color: #fff;
        box-shadow: 0 10px 20px rgba(22,163,74,.18);
    }

    .ap-hero-btn.secondary:hover,
    .ap-hero-btn.secondary:focus-visible { color: var(--ap-violet); }

    .ap-hero-btn > i,
    .ap-hero-btn > svg { width: 15px; height: 15px; }

    .ap-tabs-wrap {
        position: sticky;
        z-index: 28;
        top: .2rem;
        min-width: 0;
    }

    .ap-tabs {
        display: grid;
        min-width: 0;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .32rem;
        padding: .45rem;
        overflow-x: auto;
        border: 1px solid var(--ap-border);
        border-radius: 13px;
        background: rgba(255,255,255,.98);
        box-shadow: var(--shadow-sm);
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .ap-tabs::-webkit-scrollbar { display: none; }

    .ap-tab {
        --tab-tone: var(--ap-slate);
        --tab-soft: var(--ap-slate-soft);
        display: grid;
        min-width: max-content;
        min-height: 40px;
        grid-template-columns: auto auto;
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

    .ap-tab[data-section="summary"] { --tab-tone: var(--ap-blue); --tab-soft: var(--ap-blue-soft); }
    .ap-tab[data-section="limits"] { --tab-tone: var(--ap-violet); --tab-soft: var(--ap-violet-soft); }
    .ap-tab[data-section="deliveries"] { --tab-tone: var(--ap-amber); --tab-soft: var(--ap-amber-soft); }
    .ap-tab[data-section="distributions"] { --tab-tone: var(--ap-sky); --tab-soft: var(--ap-sky-soft); }
    .ap-tab[data-section="receipts"] { --tab-tone: var(--ap-slate); --tab-soft: var(--ap-slate-soft); }
    .ap-tab[data-section="payments"] { --tab-tone: var(--ap-green); --tab-soft: var(--ap-green-soft); }
    .ap-tab[data-section="history"] { --tab-tone: #475569; --tab-soft: #f1f5f9; }

    .ap-tab > i,
    .ap-tab > svg { width: 15px; height: 15px; color: var(--tab-tone); }

    .ap-tab:hover,
    .ap-tab:focus-visible,
    .ap-tab.active {
        border-color: color-mix(in srgb, var(--tab-tone) 16%, var(--ap-border));
        background: var(--tab-soft);
        color: var(--tab-tone);
        outline: none;
    }

    .ap-content { min-width: 0; min-height: 280px; }

    .ap-overview {
        overflow: hidden;
        border: 1px solid var(--ap-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: var(--shadow-sm);
    }

    .ap-overview-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0,1fr);
        gap: .58rem;
        align-items: center;
        min-height: 64px;
        padding: .68rem .76rem;
        border-bottom: 1px solid var(--ap-border);
        background: linear-gradient(180deg, var(--ap-soft), #fff);
    }

    .ap-overview-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
        background: var(--ap-blue-soft);
        color: var(--ap-blue);
    }

    .ap-overview-head-icon > i,
    .ap-overview-head-icon > svg { width: 18px; height: 18px; }

    .ap-overview-head h2,
    .ap-overview-head p { margin: 0; }

    .ap-overview-head h2 {
        color: var(--ap-text);
        font-size: .95rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .ap-overview-head p {
        margin-top: .08rem;
        color: var(--ap-faded);
        font-size: .74rem;
        line-height: 1.42;
    }

    .ap-overview-grid {
        display: grid;
        grid-template-columns: minmax(290px,.92fr) minmax(0,1.08fr);
    }

    .ap-financial-hero {
        --financial-tone: var(--ap-green);
        display: grid;
        min-height: 220px;
        align-content: center;
        padding: 1rem;
        background:
            radial-gradient(circle at 100% 0, rgba(34,197,94,.10), transparent 16rem),
            linear-gradient(135deg, #fff, var(--ap-green-soft));
    }

    .ap-financial-hero.warning {
        --financial-tone: var(--ap-amber);
        background:
            radial-gradient(circle at 100% 0, rgba(200,116,8,.10), transparent 16rem),
            linear-gradient(135deg, #fff, var(--ap-amber-soft));
    }

    .ap-financial-hero.danger {
        --financial-tone: var(--ap-red);
        background:
            radial-gradient(circle at 100% 0, rgba(207,63,63,.10), transparent 16rem),
            linear-gradient(135deg, #fff, var(--ap-red-soft));
    }

    .ap-financial-label {
        display: grid;
        width: max-content;
        grid-template-columns: auto auto;
        gap: .32rem;
        align-items: center;
        color: var(--financial-tone);
        font-size: .74rem;
        font-weight: 790;
    }

    .ap-financial-label > i,
    .ap-financial-label > svg { width: 16px; height: 16px; }

    .ap-financial-value {
        margin-top: .34rem;
        color: var(--ap-text);
        font-size: clamp(1.75rem,4vw,2.4rem);
        font-weight: 875;
        letter-spacing: -.045em;
        line-height: 1;
        overflow-wrap: anywhere;
    }

    .ap-financial-helper {
        max-width: 400px;
        margin-top: .42rem;
        color: var(--ap-secondary);
        font-size: .77rem;
        line-height: 1.5;
    }

    .ap-financial-facts {
        display: grid;
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: .42rem;
        margin-top: .72rem;
    }

    .ap-financial-fact { min-width: 0; }
    .ap-financial-fact span,
    .ap-financial-fact strong { display: block; }

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
        align-content: center;
        padding: .72rem;
        background: #fff;
    }

    .ap-overview-row {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0,1fr) auto;
        gap: .5rem;
        align-items: center;
        min-height: 56px;
        padding: .42rem .02rem;
    }

    .ap-overview-row + .ap-overview-row { border-top: 1px solid var(--ap-border); }

    .ap-overview-row-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
    }

    .ap-overview-row.participation .ap-overview-row-icon { background: var(--ap-violet-soft); color: var(--ap-violet); }
    .ap-overview-row.received .ap-overview-row-icon { background: var(--ap-blue-soft); color: var(--ap-blue); }
    .ap-overview-row.distributed .ap-overview-row-icon { background: var(--ap-green-soft); color: var(--ap-green); }
    .ap-overview-row.pending .ap-overview-row-icon { background: var(--ap-amber-soft); color: var(--ap-amber); }
    .ap-overview-row.receivable .ap-overview-row-icon { background: var(--ap-amber-soft); color: var(--ap-amber); }
    .ap-overview-row.receipts .ap-overview-row-icon { background: var(--ap-slate-soft); color: var(--ap-slate); }

    .ap-overview-row-icon > i,
    .ap-overview-row-icon > svg { width: 15px; height: 15px; }

    .ap-overview-row-copy { min-width: 0; }
    .ap-overview-row-copy span,
    .ap-overview-row-copy strong { display: block; }

    .ap-overview-row-copy span {
        color: var(--ap-faded);
        font-size: .67rem;
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
        font-size: .78rem;
        font-weight: 840;
        text-align: right;
        white-space: nowrap;
    }

    .ap-progress {
        height: 8px;
        margin-top: .52rem;
        overflow: hidden;
        border-radius: 999px;
        background: #e5ece7;
    }

    .ap-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg,#4ade80,var(--ap-green));
    }

    .ap-progress.warning > span { background: linear-gradient(90deg,#fbbf24,var(--ap-amber)); }
    .ap-progress.danger > span { background: linear-gradient(90deg,#fb7185,var(--ap-red)); }

    .ap-grid {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(12,minmax(0,1fr));
        gap: .62rem;
    }

    .ap-card {
        min-width: 0;
        grid-column: span 4;
        overflow: hidden;
        border: 1px solid var(--ap-border);
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 3px 10px rgba(15,35,24,.035);
    }

    .ap-card-inner {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0,1fr);
        gap: .55rem;
        align-items: start;
        padding: .68rem;
    }

    .ap-card-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 10px;
        background: var(--ap-green-soft);
        color: var(--ap-green);
    }

    .ap-card-icon.warning { background: var(--ap-amber-soft); color: var(--ap-amber); }
    .ap-card-icon.info { background: var(--ap-blue-soft); color: var(--ap-blue); }
    .ap-card-icon.danger { background: var(--ap-red-soft); color: var(--ap-red); }

    .ap-card-icon > i,
    .ap-card-icon > svg { width: 16px; height: 16px; }

    .ap-card-copy { min-width: 0; }

    .ap-card-label {
        color: var(--ap-faded);
        font-size: .68rem;
        font-weight: 700;
    }

    .ap-card-value {
        margin-top: .08rem;
        color: var(--ap-text);
        font-size: .92rem;
        font-weight: 850;
        letter-spacing: -.02em;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .ap-card-helper {
        margin-top: .08rem;
        color: var(--ap-faded);
        font-size: .68rem;
        line-height: 1.4;
    }

    .ap-section-card {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ap-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: var(--shadow-sm);
    }

    .ap-section-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0,1fr) auto;
        gap: .62rem;
        align-items: center;
        min-height: 62px;
        padding: .66rem .72rem;
        border-bottom: 1px solid var(--ap-border);
        background: linear-gradient(180deg,var(--ap-soft),#fff);
    }

    .ap-section-head-copy { min-width: 0; }

    .ap-section-title {
        color: var(--ap-text);
        font-size: .9rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .ap-section-subtitle {
        margin-top: .08rem;
        color: var(--ap-faded);
        font-size: .72rem;
        line-height: 1.4;
    }

    .ap-toolbar {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(220px,1fr) auto auto;
        gap: .48rem;
        align-items: end;
        padding: .62rem .7rem;
        border-bottom: 1px solid var(--ap-border);
        background: var(--ap-soft);
    }

    .ap-search-wrap { position: relative; min-width: 0; }

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
        border-radius: 10px;
        outline: none;
        background: #fff;
        color: var(--ap-text);
        font: inherit;
        font-size: .75rem;
    }

    .ap-input { padding-left: 2rem; }
    .ap-select { min-width: 165px; }

    .ap-input:focus,
    .ap-select:focus,
    .ap-field input:focus,
    .ap-field select:focus,
    .ap-field textarea:focus,
    .ap-quota-input:focus {
        border-color: var(--ap-primary);
        box-shadow: 0 0 0 3px rgba(34,197,94,.10);
    }

    .ap-actions {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .3rem;
        align-items: center;
    }

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
        transition: border-color 150ms ease, background 150ms ease, color 150ms ease, transform 150ms ease;
    }

    .ap-btn > i,
    .ap-btn > svg { width: 14px; height: 14px; }

    .ap-btn:hover,
    .ap-btn:focus-visible {
        border-color: rgba(34,197,94,.28);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
        outline: none;
        transform: translateY(-1px);
    }

    .ap-btn.primary {
        border-color: var(--ap-primary-dark);
        background: linear-gradient(135deg,var(--ap-primary),var(--ap-primary-dark));
        color: #fff;
        box-shadow: 0 6px 14px rgba(22,163,74,.12);
    }

    .ap-btn.primary:hover,
    .ap-btn.primary:focus-visible { color: #fff; }

    .ap-btn.warning {
        border-color: rgba(200,116,8,.18);
        background: var(--ap-amber-soft);
        color: #92400e;
    }

    .ap-btn.danger {
        border-color: rgba(207,63,63,.18);
        background: var(--ap-red-soft);
        color: #991b1b;
    }

    .ap-btn:disabled { cursor: not-allowed; opacity: .48; transform: none; }

    .delivery-note-trigger {
        display: grid;
        min-height: 34px;
        place-items: center;
        padding: .34rem .48rem;
        border: 1px solid var(--ap-border);
        border-radius: 9px;
        background: #fff;
        color: var(--ap-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .69rem;
        font-weight: 760;
    }

    .ap-table-wrap { width: 100%; overflow-x: auto; background: #fff; }

    .ap-table {
        width: 100%;
        min-width: 860px;
        border-collapse: collapse;
        color: var(--ap-text);
        font-size: .74rem;
    }

    .ap-table th,
    .ap-table td {
        padding: .65rem .7rem;
        border-bottom: 1px solid var(--ap-border);
        text-align: left;
        vertical-align: middle;
        white-space: nowrap;
    }

    .ap-table th {
        background: var(--ap-soft);
        color: var(--ap-secondary);
        font-size: .67rem;
        font-weight: 780;
        letter-spacing: .01em;
    }

    .ap-table tbody tr:hover { background: #fbfdfc; }
    .ap-table tbody tr:last-child td { border-bottom: 0; }

    .ap-badge {
        display: grid;
        width: max-content;
        min-height: 23px;
        grid-template-columns: auto auto;
        gap: .23rem;
        align-items: center;
        padding: .18rem .35rem;
        border-radius: 999px;
        background: var(--ap-slate-soft);
        color: #475569;
        font-size: .63rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .ap-badge.approved,
    .ap-badge.paid,
    .ap-badge.active { background: var(--ap-green-soft); color: var(--ap-green); }

    .ap-badge.pending,
    .ap-badge.pending_payment,
    .ap-badge.partially_paid,
    .ap-badge.billed { background: var(--ap-amber-soft); color: #92400e; }

    .ap-badge.rejected,
    .ap-badge.obsolete,
    .ap-badge.cancelled,
    .ap-badge.blocked { background: var(--ap-red-soft); color: #991b1b; }

    .ap-badge > i,
    .ap-badge > svg { width: 12px; height: 12px; }

    .ap-mobile-list {
        display: none;
        min-width: 0;
        padding: .28rem .68rem .68rem;
    }

    .ap-mobile-card { min-width: 0; padding: .68rem .02rem; }
    .ap-mobile-card + .ap-mobile-card { border-top: 1px solid var(--ap-border); }

    .ap-mobile-card-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0,1fr) auto;
        gap: .52rem;
        align-items: start;
    }

    .ap-mobile-card-title { min-width: 0; }
    .ap-mobile-card-title strong,
    .ap-mobile-card-title span { display: block; }

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
        font-size: .68rem;
        line-height: 1.4;
        overflow-wrap: anywhere;
    }

    .ap-mobile-card-body {
        display: grid;
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: .36rem;
        margin-top: .5rem;
        padding: .5rem;
        border-radius: 10px;
        background: var(--ap-soft);
    }

    .ap-mobile-metric { min-width: 0; }
    .ap-mobile-metric span,
    .ap-mobile-metric strong { display: block; }

    .ap-mobile-metric span {
        color: var(--ap-faded);
        font-size: .64rem;
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
    }

    .ap-pager {
        display: grid;
        grid-template-columns: minmax(0,1fr) auto;
        gap: .55rem;
        align-items: center;
        padding: .65rem .72rem;
        border-top: 1px solid var(--ap-border);
        background: linear-gradient(180deg,#fff,var(--ap-soft));
    }

    .ap-pager-info {
        color: var(--ap-faded);
        font-size: .69rem;
        font-weight: 680;
    }

    .ap-pager-actions {
        display: grid;
        grid-auto-flow: column;
        gap: .32rem;
    }

    .ap-state {
        display: grid;
        min-height: 220px;
        place-items: center;
        align-content: center;
        gap: .45rem;
        padding: 1.3rem;
        color: var(--ap-secondary);
        text-align: center;
    }

    .ap-state-icon {
        display: grid;
        width: 54px;
        height: 54px;
        place-items: center;
        border-radius: 15px;
        background: var(--ap-slate-soft);
        color: var(--ap-slate);
    }

    .ap-state-icon > i,
    .ap-state-icon > svg { width: 23px; height: 23px; }

    .ap-state strong {
        color: var(--ap-text);
        font-size: .82rem;
        font-weight: 820;
    }

    .ap-state p {
        max-width: 420px;
        margin: 0;
        color: var(--ap-secondary);
        font-size: .73rem;
        line-height: 1.5;
    }

    .ap-skeleton-grid {
        display: grid;
        grid-template-columns: repeat(3,minmax(0,1fr));
        gap: .62rem;
    }

    .ap-skeleton {
        position: relative;
        height: 92px;
        overflow: hidden;
        border-radius: 12px;
        background: #e9efeb;
    }

    .ap-skeleton::after {
        display: block;
        width: 50%;
        height: 100%;
        background: linear-gradient(90deg,transparent,rgba(255,255,255,.72),transparent);
        content: "";
        animation: ap-shimmer 1.1s infinite;
    }

    @keyframes ap-shimmer {
        from { transform: translateX(-120%); }
        to { transform: translateX(240%); }
    }

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
        background: rgba(8,24,15,.50);
        backdrop-filter: blur(2px);
    }

    .ap-modal.open { display: grid; }

    .ap-dialog {
        width: min(100%,540px);
        max-height: min(92dvh,760px);
        overflow-y: auto;
        border: 1px solid var(--ap-border);
        border-radius: 15px;
        background: #fff;
        box-shadow: 0 24px 68px rgba(8,24,15,.22);
        animation: ap-modal-in 180ms cubic-bezier(.2,.8,.2,1);
    }

    @keyframes ap-modal-in {
        from { opacity: 0; transform: translateY(8px) scale(.985); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .ap-dialog-head {
        position: sticky;
        z-index: 3;
        top: 0;
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0,1fr) auto;
        gap: .58rem;
        align-items: center;
        padding: .7rem .74rem;
        border-bottom: 1px solid var(--ap-border);
        background: linear-gradient(180deg,var(--ap-soft),#fff);
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
        border-color: rgba(37,99,235,.24);
        background: var(--ap-blue-soft);
        color: var(--ap-blue);
        outline: none;
    }

    .ap-dialog-close > i,
    .ap-dialog-close > svg { width: 15px; height: 15px; }

    .ap-dialog-body { padding: .78rem; }

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

    .ap-field textarea { min-height: 90px; resize: vertical; }

    .ap-dialog-actions {
        position: sticky;
        bottom: 0;
        display: grid;
        grid-auto-flow: column;
        gap: .4rem;
        justify-content: end;
        padding: .65rem .76rem .72rem;
        border-top: 1px solid var(--ap-border);
        background: rgba(255,255,255,.98);
    }

    .ap-confirm-box {
        display: grid;
        grid-template-columns: auto minmax(0,1fr);
        gap: .55rem;
        align-items: start;
        padding: .65rem;
        border: 1px solid rgba(200,116,8,.20);
        border-radius: 10px;
        background: var(--ap-amber-soft);
        color: #92400e;
    }

    .ap-confirm-box > i,
    .ap-confirm-box > svg { width: 18px; height: 18px; margin-top: .03rem; }

    .ap-confirm-box p {
        margin: 0;
        font-size: .73rem;
        line-height: 1.5;
    }

    .ap-quota-dialog {
        width: min(100%,880px);
        max-height: min(94dvh,880px);
    }

    .ap-quota-summary {
        position: sticky;
        z-index: 2;
        top: 57px;
        display: grid;
        grid-template-columns: minmax(0,1fr) auto;
        gap: .65rem;
        align-items: center;
        margin-bottom: .7rem;
        padding: .62rem .66rem;
        border: 1px solid var(--ap-border);
        border-radius: 11px;
        background: rgba(248,250,249,.99);
        box-shadow: 0 5px 16px rgba(15,35,24,.05);
    }

    .ap-quota-summary strong {
        display: block;
        margin-top: .05rem;
        color: var(--ap-text);
        font-size: .86rem;
        font-weight: 840;
    }

    .ap-quota-summary small,
    .ap-quota-card small {
        color: var(--ap-faded);
        font-size: .67rem;
        line-height: 1.4;
    }

    .ap-quota-summary-value { min-width: 145px; text-align: right; }

    .ap-quota-summary.danger {
        border-color: rgba(207,63,63,.26);
        background: var(--ap-red-soft);
    }

    .ap-quota-summary.danger .ap-quota-summary-value strong { color: var(--ap-red); }

    .ap-quota-tools {
        display: grid;
        grid-template-columns: minmax(0,1fr) auto;
        gap: .48rem;
        margin-bottom: .6rem;
    }

    .ap-quota-search-results {
        display: grid;
        max-height: 240px;
        gap: .34rem;
        margin-bottom: .65rem;
        padding: .42rem;
        overflow-y: auto;
        border: 1px solid var(--ap-border);
        border-radius: 10px;
        background: var(--ap-soft);
    }

    .ap-quota-product-option {
        display: grid;
        width: 100%;
        min-width: 0;
        grid-template-columns: minmax(0,1fr) auto;
        gap: .55rem;
        align-items: center;
        padding: .55rem .6rem;
        border: 1px solid var(--ap-border);
        border-radius: 9px;
        background: #fff;
        color: var(--ap-text);
        text-align: left;
        cursor: pointer;
    }

    .ap-quota-product-option:hover,
    .ap-quota-product-option:focus-visible {
        border-color: rgba(124,58,237,.22);
        background: var(--ap-violet-soft);
        outline: none;
    }

    .ap-quota-product-option strong {
        display: block;
        font-size: .76rem;
        font-weight: 810;
    }

    .ap-quota-product-option span {
        color: var(--ap-green);
        font-size: .69rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .ap-quota-list { display: grid; gap: .52rem; }

    .ap-quota-card {
        min-width: 0;
        overflow: hidden;
        padding: .62rem;
        border: 1px solid var(--ap-border);
        border-radius: 12px;
        background: #fff;
    }

    .ap-quota-card.editing {
        border-color: rgba(124,58,237,.28);
        box-shadow: 0 0 0 3px rgba(124,58,237,.055);
    }

    .ap-quota-card.invalid {
        border-color: rgba(207,63,63,.30);
        background: #fffafa;
    }

    .ap-quota-card-head {
        display: grid;
        grid-template-columns: minmax(0,1fr) auto;
        gap: .55rem;
        align-items: start;
    }

    .ap-quota-card-title { min-width: 0; }

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
    .ap-quota-card-actions .ap-btn > svg { width: 14px; height: 14px; }

    .ap-quota-numbers {
        display: grid;
        grid-template-columns: repeat(4,minmax(0,1fr));
        gap: .36rem;
        margin-top: .52rem;
        padding: .48rem;
        border-radius: 10px;
        background: var(--ap-soft);
    }

    .ap-quota-number { min-width: 0; }
    .ap-quota-number span,
    .ap-quota-number strong { display: block; }

    .ap-quota-number span {
        color: var(--ap-faded);
        font-size: .63rem;
        font-weight: 680;
    }

    .ap-quota-number strong {
        margin-top: .04rem;
        color: var(--ap-text);
        font-size: .71rem;
        font-weight: 810;
        overflow-wrap: anywhere;
    }

    .ap-quota-use {
        display: grid;
        grid-template-columns: minmax(0,1fr) auto;
        gap: .5rem;
        margin-top: .48rem;
        color: var(--ap-secondary);
        font-size: .68rem;
        font-weight: 710;
    }

    .ap-quota-controls {
        display: none;
        grid-template-columns: minmax(0,1fr) 150px;
        gap: .55rem;
        align-items: end;
        margin-top: .55rem;
        padding: .52rem;
        border-radius: 10px;
        background: var(--ap-violet-soft);
    }

    .ap-quota-card.editing .ap-quota-controls { display: grid; }

    .ap-quota-controls label {
        display: grid;
        gap: .25rem;
        color: var(--ap-secondary);
        font-size: .68rem;
        font-weight: 740;
    }

    .ap-quota-slider {
        width: 100%;
        min-height: 38px;
        accent-color: var(--ap-violet);
        touch-action: pan-y;
    }

    .ap-quota-slider:disabled { cursor: not-allowed; opacity: .72; }

    .ap-quota-message {
        min-height: 32px;
        margin-top: .45rem;
        padding: .4rem .46rem;
        border-radius: 9px;
        background: var(--ap-soft);
        color: var(--ap-secondary);
        font-size: .68rem;
        line-height: 1.42;
    }

    .ap-quota-message.error {
        background: var(--ap-red-soft);
        color: #991b1b;
        font-weight: 730;
    }

    .ap-quota-empty {
        padding: 1rem;
        border-radius: 10px;
        background: var(--ap-soft);
        color: var(--ap-secondary);
        text-align: center;
        font-size: .73rem;
    }

    .ap-toast-root {
        position: fixed;
        z-index: 2400;
        top: 1rem;
        right: 1rem;
        display: grid;
        width: min(380px,calc(100vw - 2rem));
        gap: .42rem;
        pointer-events: none;
    }

    .ap-toast {
        display: grid;
        grid-template-columns: auto minmax(0,1fr);
        gap: .52rem;
        align-items: center;
        padding: .62rem .66rem;
        border: 1px solid var(--ap-border);
        border-radius: 11px;
        background: rgba(255,255,255,.99);
        box-shadow: 0 14px 32px rgba(15,35,24,.12);
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

    .ap-toast.error .ap-toast-icon { background: var(--ap-red-soft); color: var(--ap-red); }
    .ap-toast-icon > i,
    .ap-toast-icon > svg { width: 15px; height: 15px; }

    @keyframes ap-toast-in {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 980px) {
        .ap-hero { grid-template-columns: 1fr; }
        .ap-hero-aside { min-width: 0; border-top: 1px solid var(--ap-border); }
        .ap-hero-actions { grid-template-columns: repeat(2,minmax(0,1fr)); }
        .ap-overview-grid { grid-template-columns: 1fr; }
        .ap-overview-list { border-top: 1px solid var(--ap-border); }
        .ap-card { grid-column: span 6; }
    }

    @media (max-width: 860px) {
        .ap-table-wrap { display: none; }
        .ap-mobile-list { display: grid; }
    }

    @media (max-width: 700px) {
        .ap-toolbar { grid-template-columns: 1fr; }
        .ap-select { min-width: 0; }
        .ap-actions { grid-auto-flow: row; grid-auto-columns: 1fr; }
        .ap-actions .ap-btn { width: 100%; }
        .ap-card { grid-column: span 12; }
        .ap-pager { grid-template-columns: 1fr; }
        .ap-pager-actions { grid-template-columns: 1fr 1fr; grid-auto-flow: row; }
        .ap-pager-actions .ap-btn { width: 100%; }
    }

    @media (max-width: 560px) {
        .ap-shell { gap: .7rem; }

        .ap-hero-main {
            grid-template-columns: 36px minmax(0,1fr);
            padding: .62rem;
        }

        .ap-back { width: 36px; height: 36px; }

        .ap-hero-badges {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            justify-items: start;
        }

        .ap-title { font-size: 1rem; }

        .ap-meta-row {
            grid-auto-flow: row;
            grid-auto-columns: 1fr;
            gap: .1rem;
            width: 100%;
        }

        .ap-hero-aside { padding: .58rem .62rem .62rem; }
        .ap-hero-actions { grid-template-columns: 1fr; }
        .ap-tabs { padding: .4rem; }

        .ap-overview-head p,
        .ap-section-subtitle { display: none; }

        .ap-financial-hero { min-height: 190px; padding: .85rem; }

        .ap-overview-row { grid-template-columns: auto minmax(0,1fr); }

        .ap-overview-row-value {
            grid-column: 2;
            justify-self: start;
            margin-top: -.1rem;
            text-align: left;
        }

        .ap-mobile-card-actions { justify-content: start; }

        .ap-quota-summary {
            top: 55px;
            grid-template-columns: 1fr;
        }

        .ap-quota-summary-value { min-width: 0; text-align: left; }
        .ap-quota-tools { grid-template-columns: 1fr; }
        .ap-quota-tools .ap-btn { width: 100%; }
        .ap-quota-numbers { grid-template-columns: 1fr 1fr; }
        .ap-quota-controls { grid-template-columns: 1fr; }

        .ap-dialog-actions {
            grid-template-columns: 1fr 1fr;
            grid-auto-flow: row;
        }

        .ap-dialog-actions .ap-btn { width: 100%; }

        .ap-toast-root {
            top: auto;
            right: .65rem;
            bottom: calc(5rem + env(safe-area-inset-bottom));
            left: .65rem;
            width: auto;
        }
    }

    @media (max-width: 400px) {
        .ap-financial-facts { grid-template-columns: 1fr; }
        .ap-mobile-card-body { grid-template-columns: 1fr; }
        .ap-quota-numbers { grid-template-columns: 1fr; }
        .ap-dialog-actions { grid-template-columns: 1fr; }
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

<div class="ap-shell" id="associate-project-app">
    <section class="ap-hero">
        <svg class="ap-hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
            <path fill="currentColor" d="M0,64L60,69.3C120,75,240,85,360,80C480,75,600,53,720,53.3C840,53,960,75,1080,80C1200,85,1320,75,1380,69.3L1440,64L1440,120L1380,120C1320,120,1200,120,1080,120C960,120,840,120,720,120C600,120,480,120,360,120C240,120,120,120,60,120L0,120Z"></path>
        </svg>

        <div class="ap-hero-main">
            <a
                class="ap-back"
                href="{{ route('delivery.projects.producers', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            >
                <i data-lucide="arrow-left"></i>
                Voltar aos produtores
            </a>

            <div class="ap-hero-badges">
                <span class="ap-hero-badge">
                    <i data-lucide="user-round"></i>
                    Associado
                </span>
                <span class="ap-hero-badge">
                    <i data-lucide="folder-kanban"></i>
                    Projeto #{{ $project->id }}
                </span>
            </div>

            <h1 class="ap-title">{{ $associate->display_name }}</h1>

            <div class="ap-meta-row">
                <span>
                    <i data-lucide="hash"></i>
                    {{ $associateCode }}
                </span>
                <span>
                    <i data-lucide="map-pin"></i>
                    {{ $associateLocation }}
                </span>
                <span>
                    <i data-lucide="folder-open"></i>
                    {{ $project->title }}
                </span>

                @if($projectPeriod)
                    <span>
                        <i data-lucide="calendar-days"></i>
                        {{ $projectPeriod }}
                    </span>
                @endif
            </div>
        </div>

        <aside class="ap-hero-aside">
            <span class="ap-aside-label">Ações rápidas</span>
            <strong>O que você precisa fazer agora?</strong>
            <p>Registre uma nova entrega ou revise as cotas deste associado.</p>

            <div class="ap-hero-actions">
                <a
                    class="ap-hero-btn primary"
                    href="{{ route('delivery.register', ['tenant' => $tenantSlug, 'project' => $project->id, 'associate' => $associate->id]) }}"
                >
                    <i data-lucide="package-plus"></i>
                    Registrar entrega
                </a>

                @if($canManageLimits)
                    <button class="ap-hero-btn secondary" type="button" onclick="showLimits()">
                        <i data-lucide="sliders-horizontal"></i>
                        Configurar limites
                    </button>
                @endif
            </div>
        </aside>
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
                <button class="ap-btn primary" type="submit">
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

                <button class="ap-btn primary" type="button" onclick="toggleQuotaProductOptions()">
                    <i data-lucide="package-plus"></i>
                    Adicionar produto
                </button>
            </div>

            <div class="ap-quota-search-results" id="quota-product-options" hidden></div>
            <div class="ap-quota-list" id="quota-list"></div>
        </div>

        <div class="ap-dialog-actions">
            <button class="ap-btn" type="button" onclick="closeProductLimitsManager()">Cancelar</button>
            <button class="ap-btn primary" type="button" id="quota-save-all" onclick="saveProductLimitChanges()">
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
        const rawPercent = Number(data.financial_percent || 0);
        const percent = Math.min(100, Math.max(0, rawPercent));

        const participation = data.participation_status === 'active'
            ? 'Entregas permitidas'
            : data.participation_status === 'blocked'
                ? 'Entregas bloqueadas'
                : data.restrict_participants
                    ? 'Participação pendente'
                    : 'Projeto aberto';

        const participationShort = data.participation_status === 'blocked'
            ? 'Bloqueada'
            : data.participation_status === 'active'
                ? 'Ativa'
                : 'Pendente';

        const financialTone = rawPercent >= 100
            ? 'danger'
            : rawPercent >= 80
                ? 'warning'
                : '';

        const availableValue = data.financial_remaining === null
            ? 'Sem teto'
            : money(data.financial_remaining);

        const financialHelper = data.financial_limit === null
            ? 'Não existe teto financeiro definido para este associado.'
            : `${Math.round(rawPercent)}% do teto financeiro já foi utilizado.`;

        apRoot.innerHTML = `
            <section class="ap-overview">
                <header class="ap-overview-head">
                    <span class="ap-overview-head-icon" aria-hidden="true">
                        <i data-lucide="layout-dashboard"></i>
                    </span>

                    <div>
                        <h2>Visão geral da participação</h2>
                        <p>
                            Situação financeira, entregas e pendências
                            deste associado no projeto.
                        </p>
                    </div>
                </header>

                <div class="ap-overview-grid">
                    <div class="ap-financial-hero ${financialTone}">
                        <span class="ap-financial-label">
                            <i data-lucide="hand-coins"></i>
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
                                    <span style="width:${percent}%"></span>
                                </div>
                            `}

                        <div class="ap-financial-facts">
                            <div class="ap-financial-fact">
                                <span>Teto financeiro</span>
                                <strong>
                                    ${data.financial_limit === null
                                        ? 'Sem limite'
                                        : money(data.financial_limit)}
                                </strong>
                            </div>

                            <div class="ap-financial-fact">
                                <span>Já utilizado</span>
                                <strong>${money(data.financial_consumed)}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="ap-overview-list">
                        <div class="ap-overview-row participation">
                            <span class="ap-overview-row-icon">
                                <i data-lucide="${data.participation_status === 'blocked'
                                    ? 'user-round-x'
                                    : 'user-round-check'}"></i>
                            </span>

                            <span class="ap-overview-row-copy">
                                <span>Participação</span>
                                <strong>${esc(participation)}</strong>
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
                                <strong>Total registrado para o associado</strong>
                            </span>

                            <strong class="ap-overview-row-value">
                                ${qty(data.received_quantity)}
                            </strong>
                        </div>

                        <div class="ap-overview-row distributed">
                            <span class="ap-overview-row-icon">
                                <i data-lucide="route"></i>
                            </span>

                            <span class="ap-overview-row-copy">
                                <span>Quantidade distribuída</span>
                                <strong>Volume que já recebeu destino</strong>
                            </span>

                            <strong class="ap-overview-row-value">
                                ${qty(data.distributed_quantity)}
                            </strong>
                        </div>

                        <div class="ap-overview-row pending">
                            <span class="ap-overview-row-icon">
                                <i data-lucide="package-open"></i>
                            </span>

                            <span class="ap-overview-row-copy">
                                <span>Sem distribuição</span>
                                <strong>
                                    ${Number(data.undistributed_quantity || 0) > 0
                                        ? 'Ainda existe quantidade aguardando destino'
                                        : 'Nenhuma quantidade aguardando destino'}
                                </strong>
                            </span>

                            <strong class="ap-overview-row-value">
                                ${qty(data.undistributed_quantity)}
                            </strong>
                        </div>

                        <div class="ap-overview-row receivable">
                            <span class="ap-overview-row-icon">
                                <i data-lucide="wallet-cards"></i>
                            </span>

                            <span class="ap-overview-row-copy">
                                <span>A receber</span>
                                <strong>Saldo financeiro ainda pendente</strong>
                            </span>

                            <strong class="ap-overview-row-value">
                                ${money(data.receivable)}
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
                                ${String(data.receipt_count || 0)}
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
        apLimitRows = Object.fromEntries(
            (data.products || []).map(item => [String(item.id), item])
        );

        let actions = '';

        if (AP_CAN_MANAGE) {
            actions += `
                <button class="ap-btn" type="button" onclick="openFinancialLimit(${summary.financial_limit ?? ''})">
                    <i data-lucide="wallet-cards"></i>
                    Editar limite financeiro
                </button>

                <button
                    class="ap-btn ${summary.participation_status === 'active' ? 'warning' : ''}"
                    type="button"
                    onclick="requestParticipation('${summary.participation_status === 'active' ? 'blocked' : 'active'}')"
                >
                    <i data-lucide="${summary.participation_status === 'active' ? 'user-round-x' : 'user-round-check'}"></i>
                    ${summary.participation_status === 'active' ? 'Bloquear entregas' : 'Permitir entregas'}
                </button>
            `;
        }

        if (AP_CAN_MANAGE && summary.allows_product_limits) {
            actions += `
                <a class="ap-btn primary" href="${AP_LIMITS_PAGE}">
                    <i data-lucide="package-plus"></i>
                    Gerenciar produtos e cotas
                </a>
            `;
        }

        const rows = (data.products || []).map(item => `
            <tr>
                <td>${esc(item.product)}</td>
                <td>${qty(item.maximum_quantity)} ${esc(item.unit)}</td>
                <td>${qty(item.delivered_quantity)}</td>
                <td>${qty(item.remaining_quantity)}</td>
                <td>${money(item.reference_unit_price)}</td>
                <td>${money(item.estimated_maximum_value)}</td>
                <td>
                    <div class="ap-progress ${progressTone(Number(item.percent || 0))}">
                        <span style="width:${Math.min(100, Number(item.percent || 0))}%"></span>
                    </div>
                    <div style="margin-top:.2rem;color:var(--ap-faded);font-size:.58rem">
                        ${Math.round(Number(item.percent || 0))}% utilizado
                    </div>
                </td>
                <td>
                    ${AP_CAN_MANAGE ? `
                        <a
                            class="ap-btn"
                            href="${AP_LIMITS_PAGE}#produto-${Number(item.product_id)}"
                            title="Editar limite"
                        >
                            <i data-lucide="pencil"></i>
                            Editar
                        </a>
                    ` : '-'}
                </td>
            </tr>
        `).join('');

        const mobileCards = (data.products || []).map(item => `
            <article class="ap-mobile-card">
                <div class="ap-mobile-card-head">
                    <div class="ap-mobile-card-title">
                        <strong>${esc(item.product)}</strong>
                        <span>${money(item.reference_unit_price)} por ${esc(item.unit)}</span>
                    </div>

                    <span class="ap-badge">
                        ${Math.round(Number(item.percent || 0))}%
                    </span>
                </div>

                <div class="ap-mobile-card-body">
                    <div class="ap-mobile-metric">
                        <span>Limite</span>
                        <strong>${qty(item.maximum_quantity)} ${esc(item.unit)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Entregue</span>
                        <strong>${qty(item.delivered_quantity)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Saldo</span>
                        <strong>${qty(item.remaining_quantity)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Preço</span>
                        <strong>${money(item.reference_unit_price)}</strong>
                    </div>

                    <div class="ap-mobile-metric">
                        <span>Planejado</span>
                        <strong>${money(item.estimated_maximum_value)}</strong>
                    </div>
                </div>

                <div style="padding:0 .75rem .75rem">
                    <div class="ap-progress ${progressTone(Number(item.percent || 0))}">
                        <span style="width:${Math.min(100, Number(item.percent || 0))}%"></span>
                    </div>
                </div>

                ${AP_CAN_MANAGE ? `
                    <div class="ap-mobile-card-actions">
                        <a class="ap-btn primary" href="${AP_LIMITS_PAGE}#produto-${Number(item.product_id)}">
                            <i data-lucide="pencil"></i>
                            Editar limite
                        </a>
                    </div>
                ` : ''}
            </article>
        `).join('');

        apRoot.innerHTML = `
            <div class="ap-grid" style="margin-bottom:.75rem">
                ${statCard(
                    'Participação',
                    summary.participation_status === 'active'
                        ? 'Ativa'
                        : summary.participation_status === 'blocked'
                            ? 'Bloqueada'
                            : 'Não configurada',
                    'Define se este associado pode registrar novas entregas.',
                    'user-round-check'
                )}

                ${statCard(
                    'Limite financeiro',
                    summary.financial_limit === null ? 'Sem limite' : money(summary.financial_limit),
                    'Teto financeiro definido para o associado.',
                    'wallet-cards'
                )}

                ${statCard(
                    'Utilizado',
                    money(summary.financial_consumed),
                    'Valor consumido pelas distribuições.',
                    'circle-dollar-sign'
                )}

                ${statCard(
                    'Planejado nos produtos',
                    money(summary.simulated_limit_value),
                    summary.simulated_limit_remaining === null
                        ? 'Soma das quantidades pelos preços de referência.'
                        : money(summary.simulated_limit_remaining) + ' livre no teto.',
                    'calculator'
                )}

                ${statCard(
                    'Saldo disponível',
                    summary.financial_remaining === null ? 'Livre' : money(summary.financial_remaining),
                    'Valor restante para novas distribuições.',
                    'hand-coins'
                )}
            </div>

            <section class="ap-section-card">
                <div class="ap-section-head">
                    <div class="ap-section-head-copy">
                        <div class="ap-section-title">Participação e produtos permitidos</div>
                    </div>
                </div>

                ${actions ? `<div class="ap-toolbar">${actions}</div>` : ''}

                <div class="ap-table-wrap">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Produto permitido</th>
                                <th>Limite</th>
                                <th>Entregue</th>
                                <th>Saldo</th>
                                <th>Preço de referência</th>
                                <th>Valor planejado</th>
                                <th>Uso</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows || `
                                <tr>
                                    <td colspan="8">
                                        ${stateView(
                                            'Nenhum produto autorizado',
                                            'Adicione um limite de produto ou revise as regras do projeto.',
                                            'package-x'
                                        )}
                                    </td>
                                </tr>
                            `}
                        </tbody>
                    </table>
                </div>

                <div class="ap-mobile-list">
                    ${mobileCards || stateView(
                        'Nenhum produto autorizado',
                        'Adicione um limite de produto ou revise as regras do projeto.',
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

    function sectionShell(title, subtitle, body, mobileBody = '', withToolbar = true) {
        return `
            <section class="ap-section-card">
                <div class="ap-section-head">
                    <div class="ap-section-head-copy">
                        <div class="ap-section-title">${esc(title)}</div>
                        <div class="ap-section-subtitle">${esc(subtitle)}</div>
                    </div>
                </div>

                ${withToolbar ? toolbar() : ''}
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
                            <button class="ap-btn primary" type="button" onclick="requestDeliveryAction(${item.id}, 'approve')">
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

                        <a class="ap-btn" href="${esc(item.manage_url)}">
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
                        <button class="ap-btn primary" type="button" onclick="requestDeliveryAction(${item.id}, 'approve')">
                            Aprovar
                        </button>
                    ` : ''}

                    ${item.can_reject ? `
                        <button class="ap-btn danger" type="button" onclick="requestDeliveryAction(${item.id}, 'reject')">
                            Rejeitar
                        </button>
                    ` : ''}

                    <a class="ap-btn" href="${esc(item.manage_url)}">
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
                        <a class="ap-btn" href="${esc(item.reprint_url)}">
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
                        <a class="ap-btn primary" href="${esc(item.reprint_url)}">
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
            : '<div class="ap-quota-empty">Nenhum produto configurado. Use a busca acima para adicionar o primeiro.</div>';

        refreshQuotaState();
        renderQuotaProductOptions();
        icons();
    }

    function renderQuotaCard(row) {
        const key = String(row.productId);
        const editing = apQuotaEditing === key;
        const sliderMaximum = quotaSliderMaximum(row);
        const percent = Number(row.quantity) > 0
            ? Math.min(100, (row.delivered / Number(row.quantity)) * 100)
            : 0;

        return `
            <article class="ap-quota-card ${editing ? 'editing' : ''}" id="quota-card-${row.productId}">
                <div class="ap-quota-card-head">
                    <div class="ap-quota-card-title">
                        <strong>${esc(row.name)}</strong>
                        <small>${money(row.price)} por ${esc(row.unit || 'unidade')}</small>
                    </div>

                    <div class="ap-quota-card-actions">
                        <button class="ap-btn ${editing ? 'primary' : ''}" type="button" onclick="unlockQuotaCard(${row.productId})">
                            <i data-lucide="${editing ? 'lock-open' : 'pencil'}"></i>
                            <span>${editing ? 'Editando' : 'Editar'}</span>
                        </button>
                        <button class="ap-btn danger" type="button" onclick="requestQuotaRemoval(${row.productId})">
                            <i data-lucide="trash-2"></i>
                            <span>Remover</span>
                        </button>
                    </div>
                </div>

                <div class="ap-quota-numbers">
                    <div class="ap-quota-number">
                        <span>Já entregue</span>
                        <strong>${qty(row.delivered)} ${esc(row.unit)}</strong>
                    </div>
                    <div class="ap-quota-number">
                        <span>Cota definida</span>
                        <strong id="quota-label-${row.productId}">${qty(row.quantity)} ${esc(row.unit)}</strong>
                    </div>
                    <div class="ap-quota-number">
                        <span>Saldo para entregar</span>
                        <strong id="quota-remaining-${row.productId}">${qty(Math.max(0, row.quantity - row.delivered))} ${esc(row.unit)}</strong>
                    </div>
                    <div class="ap-quota-number">
                        <span>Valor planejado</span>
                        <strong id="quota-value-${row.productId}">${money(row.quantity * row.price)}</strong>
                    </div>
                </div>

                <div class="ap-quota-use">
                    <span>Uso da cota</span>
                    <span id="quota-use-label-${row.productId}">${Math.round(percent)}% já entregue</span>
                </div>
                <div class="ap-progress ${progressTone(percent)}">
                    <span id="quota-progress-${row.productId}" style="width:${percent}%"></span>
                </div>

                <div class="ap-quota-controls">
                    <label>
                        Ajustar cota deslizando
                        <input
                            class="ap-quota-slider"
                            id="quota-slider-${row.productId}"
                            type="range"
                            min="${row.delivered}"
                            max="${sliderMaximum}"
                            step="0.001"
                            value="${row.quantity}"
                            ${editing ? '' : 'disabled'}
                            oninput="setQuotaQuantity(${row.productId}, this.value, 'slider')"
                        >
                    </label>

                    <label>
                        Cota máxima (${esc(row.unit)})
                        <input
                            class="ap-quota-input"
                            id="quota-input-${row.productId}"
                            type="number"
                            min="${row.delivered}"
                            ${row.maximum === null ? '' : `max="${row.maximum}"`}
                            step="0.001"
                            value="${row.quantity}"
                            ${editing ? '' : 'disabled'}
                            oninput="setQuotaQuantity(${row.productId}, this.value, 'input')"
                        >
                    </label>
                </div>

                <div class="ap-quota-message" id="quota-message-${row.productId}">
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
            slider.max = String(Math.max(quotaSliderMaximum(row), row.quantity));
            slider.value = String(Math.min(row.quantity, Number(slider.max)));
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
            if (label) label.textContent = `${qty(row.quantity)} ${row.unit}`;
            if (remaining) remaining.textContent = `${qty(Math.max(0, row.quantity - row.delivered))} ${row.unit}`;
            if (value) value.textContent = money(row.quantity * row.price);
            if (progress) {
                progress.style.width = `${percent}%`;
                progress.parentElement?.classList.toggle('warning', percent >= 80 && percent < 100);
                progress.parentElement?.classList.toggle('danger', percent >= 100);
            }
            if (useLabel) useLabel.textContent = `${Math.round(percent)}% já entregue`;
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
                <button class="ap-quota-product-option" type="button" onclick="addQuotaProduct(${Number(product.id)})">
                    <div>
                        <strong>${esc(product.name)}</strong>
                        <small>${money(product.price)} por ${esc(product.unit || 'unidade')}</small>
                    </div>
                    <span>${product.available_for_associate === null
                        ? 'Sem meta geral'
                        : qty(product.available_for_associate) + ' disponível'}</span>
                </button>
            `).join('')
            : '<div class="ap-quota-empty">Nenhum produto disponível para esta busca.</div>';
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