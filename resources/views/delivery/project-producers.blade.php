@extends('layouts.bento')

@php
    $tenantSlug = $tenant->slug ?? request()->route('tenant');
    $memberTerm = $tenant->associateTerm() ?: 'Membro';
    $memberTermPlural = $tenant->associateTerm(plural: true) ?: 'Membros';
    $memberTermLower = mb_strtolower($memberTerm);
    $memberTermPluralLower = mb_strtolower($memberTermPlural);
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', $tenantSlug);

    $projectPeriod = collect([
        $project->start_date?->format('d/m/Y'),
        $project->end_date?->format('d/m/Y'),
    ])->filter()->implode(' a ');

    $projectStatusValue = $project->status->value
        ?? (is_string($project->status ?? null)
            ? $project->status
            : 'active');

    $projectStatusLabel = is_object($project->status ?? null)
        && method_exists($project->status, 'getLabel')
            ? $project->status->getLabel()
            : match ($projectStatusValue) {
                'active' => 'Em execução',
                'draft' => 'Rascunho',
                'completed' => 'Concluído',
                'cancelled' => 'Cancelado',
                default => ucfirst((string) $projectStatusValue),
            };

    $projectStatusIcon = match ($projectStatusValue) {
        'active' => 'ph-play-circle',
        'draft' => 'ph-note-pencil',
        'completed' => 'ph-check-circle',
        'cancelled' => 'ph-x-circle',
        default => 'ph-circle',
    };
@endphp

@section('title', 'Comprovantes de '.$memberTermPluralLower)
@section('page-title', 'Comprovantes de '.$memberTermPluralLower)
@section('page-subtitle', 'Acompanhe e gere comprovantes por associado.')
@section('user-role', 'Registrador')

<x-delivery.notes-modal />

@section('content')
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css"
>
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/duotone/style.css"
>

<style>
    .pr,
    .pr-overlay,
    .pr-dialog,
    .pr-toast-wrap {
        --pr-green: #168a4d;
        --pr-green-soft: #eaf8ef;
        --pr-blue: #2563eb;
        --pr-blue-soft: #eef4ff;
        --pr-sky: #0284c7;
        --pr-sky-soft: #edf8fe;
        --pr-violet: #7c3aed;
        --pr-violet-soft: #f4f0ff;
        --pr-amber: #c87408;
        --pr-amber-soft: #fff7e8;
        --pr-red: #cf3f3f;
        --pr-red-soft: #fff0f0;
        --pr-slate: #64748b;
        --pr-slate-soft: #f1f5f9;

        --pr-text: var(--color-text, #102018);
        --pr-secondary: var(--color-text-secondary, #52645a);
        --pr-muted-text: var(--color-text-muted, #809087);
        --pr-border: var(--color-border, #dce6df);
        --pr-border-strong: var(--color-border-strong, #c8d6cd);
        --pr-surface: var(--color-surface, #fff);
        --pr-soft: var(--color-surface-soft, #f8faf9);
        --pr-muted: var(--color-surface-muted, #eef4f0);
    }

    .pr {
        display: grid;
        width: min(100%, 1280px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--pr-text);
    }

    .pr *,
    .pr *::before,
    .pr *::after,
    .pr-overlay *,
    .pr-overlay *::before,
    .pr-overlay *::after,
    .pr-dialog *,
    .pr-dialog *::before,
    .pr-dialog *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       CABEÇALHO
       ========================================================= */

    .pr-head {
        --head-tone: var(--pr-blue);
        --head-soft: var(--pr-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns: auto auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        min-height: 72px;
        padding: .7rem .76rem;
        overflow: hidden;
        border: 1px solid var(--pr-border);
        border-radius: 15px;
        background:
            radial-gradient(
                circle at 100% 0,
                color-mix(in srgb, var(--head-tone) 8%, transparent),
                transparent 17rem
            ),
            linear-gradient(180deg, var(--pr-soft), var(--pr-surface));
        box-shadow: var(--shadow-sm);
    }

    .pr-head.status-draft {
        --head-tone: var(--pr-amber);
        --head-soft: var(--pr-amber-soft);
    }

    .pr-head.status-completed {
        --head-tone: var(--pr-green);
        --head-soft: var(--pr-green-soft);
    }

    .pr-head.status-cancelled {
        --head-tone: var(--pr-red);
        --head-soft: var(--pr-red-soft);
    }

    .pr-back,
    .pr-head-icon,
    .pr-head-action {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 11px;
    }

    .pr-back {
        border: 1px solid var(--pr-border);
        background: #fff;
        color: var(--pr-secondary);
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .pr-back:hover,
    .pr-back:focus-visible {
        border-color: rgba(37, 99, 235, .24);
        background: var(--pr-blue-soft);
        color: var(--pr-blue);
        outline: none;
        transform: translateX(-1px);
    }

    .pr-head-icon {
        background: var(--head-soft);
        color: var(--head-tone);
    }

    .pr-back > i,
    .pr-head-icon > i,
    .pr-head-action > i {
        display: block;
        font-size: 1rem;
        line-height: 1;
    }

    .pr-head-icon > i {
        font-size: 1.16rem;
    }

    .pr-head-copy {
        min-width: 0;
    }

    .pr-head-copy h1 {
        margin: 0;
        color: var(--pr-text);
        font-size: clamp(1rem, 2vw, 1.18rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.28;
        overflow-wrap: anywhere;
    }

    .pr-head-meta {
        display: flex;
        min-width: 0;
        gap: .48rem;
        align-items: center;
        flex-wrap: wrap;
        margin-top: .16rem;
        color: var(--pr-muted-text);
        font-size: .72rem;
        line-height: 1.4;
    }

    .pr-head-meta > span {
        display: inline-flex;
        min-width: 0;
        gap: .24rem;
        align-items: center;
    }

    .pr-head-meta i {
        display: block;
        font-size: .78rem;
        line-height: 1;
    }

    .pr-head-side {
        display: flex;
        gap: .38rem;
        align-items: center;
    }

    .pr-project-status {
        display: inline-flex;
        min-height: 30px;
        gap: .28rem;
        align-items: center;
        padding: .28rem .46rem;
        border-radius: 999px;
        background: var(--head-soft);
        color: var(--head-tone);
        font-size: .68rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .pr-project-status i {
        font-size: .78rem;
    }

    .pr-head-action {
        border: 1px solid var(--pr-border);
        background: #fff;
        color: var(--pr-secondary);
        cursor: pointer;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease;
    }

    .pr-head-action:hover,
    .pr-head-action:focus-visible {
        border-color: rgba(124, 58, 237, .22);
        background: var(--pr-violet-soft);
        color: var(--pr-violet);
        outline: none;
    }

    /* =========================================================
       AÇÕES / BOTÕES
       ========================================================= */

    .pr-btn {
        display: inline-flex;
        min-height: 40px;
        align-items: center;
        justify-content: center;
        gap: .34rem;
        padding: .46rem .64rem;
        border: 1px solid var(--pr-border-strong);
        border-radius: 9px;
        background: #fff;
        color: var(--pr-text);
        cursor: pointer;
        font: inherit;
        font-size: .73rem;
        font-weight: 780;
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .pr-btn > i {
        display: block;
        font-size: .88rem;
        line-height: 1;
    }

    .pr-btn:hover:not(:disabled),
    .pr-btn:focus-visible:not(:disabled) {
        border-color: rgba(34, 197, 94, .28);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
        outline: none;
    }

    .pr-btn.primary {
        border-color: var(--color-primary-dark);
        background:
            linear-gradient(
                135deg,
                var(--color-primary),
                var(--color-primary-dark)
            );
        color: #fff;
        box-shadow: 0 7px 16px rgba(22, 163, 74, .14);
    }

    .pr-btn.primary:hover:not(:disabled),
    .pr-btn.primary:focus-visible:not(:disabled) {
        color: #fff;
        box-shadow: 0 10px 20px rgba(22, 163, 74, .20);
        transform: translateY(-1px);
    }

    .pr-btn.danger {
        border-color: color-mix(in srgb, var(--pr-red) 20%, var(--pr-border));
        background: var(--pr-red-soft);
        color: #a82929;
    }

    .pr-btn.danger:hover:not(:disabled),
    .pr-btn.danger:focus-visible:not(:disabled) {
        border-color: rgba(207, 63, 63, .34);
        background: #ffe8e8;
        color: #991b1b;
    }

    .pr-btn.icon {
        width: 38px;
        min-width: 38px;
        padding: 0;
    }

    .pr-btn:disabled {
        cursor: not-allowed;
        opacity: .48;
    }

    .pr-actions,
    .pr-pager,
    .pr-card-actions,
    .pr-receipt-actions,
    .pr-modal-actions {
        display: flex;
        gap: .42rem;
        align-items: center;
        flex-wrap: wrap;
    }

    /* =========================================================
       RESUMO
       ========================================================= */

    .pr-overview {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--pr-border);
        border-radius: 15px;
        background: var(--pr-surface);
        box-shadow: var(--shadow-sm);
    }

    .pr-overview-head {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .62rem;
        padding: .62rem .72rem;
        border-bottom: 1px solid var(--pr-border);
        background: linear-gradient(180deg, var(--pr-soft), #fff);
    }

    .pr-overview-title {
        display: flex;
        min-width: 0;
        gap: .52rem;
        align-items: center;
    }

    .pr-overview-icon {
        display: grid;
        width: 36px;
        height: 36px;
        flex: none;
        place-items: center;
        border-radius: 10px;
        background: var(--pr-violet-soft);
        color: var(--pr-violet);
    }

    .pr-overview-icon i {
        font-size: .98rem;
    }

    .pr-overview-copy h2,
    .pr-overview-copy p {
        margin: 0;
    }

    .pr-overview-copy h2 {
        font-size: .88rem;
        font-weight: 830;
        letter-spacing: -.015em;
    }

    .pr-overview-copy p {
        margin-top: .06rem;
        color: var(--pr-muted-text);
        font-size: .69rem;
        line-height: 1.35;
    }

    .pr-overview-action {
        color: var(--pr-violet);
        font-size: .69rem;
        font-weight: 770;
        text-decoration: none;
        white-space: nowrap;
    }

    .pr-overview-action:hover {
        text-decoration: underline;
    }

    .pr-summary {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .42rem;
        padding: .62rem;
    }

    .pr-stat {
        --stat-tone: var(--pr-slate);
        --stat-soft: var(--pr-slate-soft);

        display: grid;
        min-width: 0;
        min-height: 76px;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .48rem;
        align-items: center;
        padding: .58rem;
        border: 1px solid transparent;
        border-radius: 11px;
        background: var(--stat-soft);
        color: var(--pr-text);
        text-align: left;
        cursor: pointer;
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .pr-stat[data-summary-filter="all"] {
        --stat-tone: var(--pr-blue);
        --stat-soft: var(--pr-blue-soft);
    }

    .pr-stat[data-summary-filter="pending"] {
        --stat-tone: var(--pr-amber);
        --stat-soft: var(--pr-amber-soft);
    }

    .pr-stat[data-summary-filter="complement"] {
        --stat-tone: var(--pr-violet);
        --stat-soft: var(--pr-violet-soft);
    }

    .pr-stat[data-summary-filter="obsolete"] {
        --stat-tone: var(--pr-red);
        --stat-soft: var(--pr-red-soft);
    }

    .pr-stat[data-summary-filter="paid"] {
        --stat-tone: var(--pr-green);
        --stat-soft: var(--pr-green-soft);
    }

    .pr-stat:hover,
    .pr-stat:focus-visible,
    .pr-stat.active {
        border-color:
            color-mix(
                in srgb,
                var(--stat-tone) 22%,
                var(--pr-border)
            );
        box-shadow: inset 3px 0 var(--stat-tone);
        outline: none;
    }

    .pr-stat.active {
        transform: translateY(-1px);
    }

    .pr-stat-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 10px;
        background:
            color-mix(
                in srgb,
                var(--stat-tone) 10%,
                #fff
            );
        color: var(--stat-tone);
    }

    .pr-stat-icon i {
        font-size: .96rem;
    }

    .pr-stat-copy {
        min-width: 0;
    }

    .pr-stat-copy strong,
    .pr-stat-copy small {
        display: block;
    }

    .pr-stat-copy strong {
        color: var(--stat-tone);
        font-size: 1rem;
        font-weight: 870;
        line-height: 1;
    }

    .pr-stat-copy small {
        margin-top: .18rem;
        color: var(--pr-secondary);
        font-size: .64rem;
        font-weight: 730;
        line-height: 1.28;
        overflow-wrap: anywhere;
    }

    /* =========================================================
       FERRAMENTAS
       ========================================================= */

    .pr-tools-shell {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
        padding: .52rem;
        border: 1px solid var(--pr-border);
        border-radius: 13px;
        background: var(--pr-surface);
        box-shadow: var(--shadow-sm);
    }

    .pr-tools {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(230px, 1fr) minmax(190px, 250px);
        gap: .5rem;
    }

    .pr-search {
        position: relative;
        min-width: 0;
    }

    .pr-search > i {
        position: absolute;
        top: 50%;
        left: .68rem;
        color: var(--pr-muted-text);
        font-size: .88rem;
        pointer-events: none;
        transform: translateY(-50%);
    }

    .pr-control {
        width: 100%;
        min-height: 42px;
        border: 1px solid var(--pr-border-strong);
        border-radius: 10px;
        outline: none;
        background: #fff;
        color: var(--pr-text);
        font: inherit;
        font-size: .75rem;
    }

    input.pr-control {
        padding: .5rem .66rem;
    }

    .pr-search .pr-control {
        padding-left: 2.08rem;
    }

    select.pr-control {
        padding: .5rem 1.9rem .5rem .64rem;
        cursor: pointer;
    }

    .pr-control:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .10);
    }

    /* =========================================================
       CARDS DOS ASSOCIADOS / PRODUTORES
       ========================================================= */

    .pr-grid {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
        gap: .62rem;
    }

    .pr-card {
        --card-tone: var(--pr-slate);
        --card-soft: var(--pr-slate-soft);

        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--pr-border);
        border-radius: 13px;
        background: var(--pr-surface);
        box-shadow: var(--shadow-sm);
    }

    .pr-card.is-warning {
        --card-tone: var(--pr-amber);
        --card-soft: var(--pr-amber-soft);
    }

    .pr-card.is-danger {
        --card-tone: var(--pr-red);
        --card-soft: var(--pr-red-soft);
    }

    .pr-card.is-success {
        --card-tone: var(--pr-green);
        --card-soft: var(--pr-green-soft);
    }

    .pr-card.is-blue {
        --card-tone: var(--pr-blue);
        --card-soft: var(--pr-blue-soft);
    }

    .pr-card.is-violet {
        --card-tone: var(--pr-violet);
        --card-soft: var(--pr-violet-soft);
    }

    .pr-card-head {
        display: flex;
        min-width: 0;
        align-items: flex-start;
        justify-content: space-between;
        gap: .58rem;
        padding: .72rem .72rem .54rem;
    }

    .pr-person {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .58rem;
    }

    .pr-avatar {
        display: grid;
        width: 40px;
        height: 40px;
        flex: none;
        place-items: center;
        border-radius: 11px;
        background: var(--card-soft);
        color: var(--card-tone);
        font-size: .74rem;
        font-weight: 860;
    }

    .pr-card h2 {
        margin: 0;
        color: var(--pr-text);
        font-size: .86rem;
        font-weight: 830;
        line-height: 1.32;
        overflow-wrap: anywhere;
    }

    .pr-sub {
        margin-top: .08rem;
        color: var(--pr-muted-text);
        font-size: .68rem;
        line-height: 1.4;
    }

    .pr-badge {
        display: inline-flex;
        width: max-content;
        min-height: 23px;
        flex: none;
        align-items: center;
        gap: .24rem;
        padding: .18rem .36rem;
        border-radius: 999px;
        background: var(--pr-muted);
        color: var(--pr-secondary);
        font-size: .61rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .pr-badge.green {
        background: var(--pr-green-soft);
        color: var(--pr-green);
    }

    .pr-badge.yellow {
        background: var(--pr-amber-soft);
        color: #92400e;
    }

    .pr-badge.red {
        background: var(--pr-red-soft);
        color: #991b1b;
    }

    .pr-badge.blue {
        background: var(--pr-blue-soft);
        color: var(--pr-blue);
    }

    .pr-meter {
        height: 7px;
        margin: 0 .72rem;
        overflow: hidden;
        border-radius: 999px;
        background: #e6ece8;
    }

    .pr-meter span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                color-mix(in srgb, var(--card-tone) 58%, #fff),
                var(--card-tone)
            );
    }

    .pr-progress-label {
        display: flex;
        justify-content: space-between;
        gap: .5rem;
        margin-top: .34rem;
        padding: 0 .72rem;
        color: var(--pr-muted-text);
        font-size: .66rem;
        line-height: 1.3;
    }

    .pr-progress-label strong {
        color: var(--card-tone);
        font-weight: 820;
    }

    .pr-values {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .34rem;
        margin: .58rem .72rem 0;
        padding: .48rem;
        border-radius: 10px;
        background: var(--pr-soft);
    }

    .pr-value {
        min-width: 0;
    }

    .pr-value span,
    .pr-value strong {
        display: block;
    }

    .pr-value span {
        color: var(--pr-muted-text);
        font-size: .62rem;
        font-weight: 680;
    }

    .pr-value strong {
        margin-top: .04rem;
        color: var(--pr-text);
        font-size: .73rem;
        font-weight: 820;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .pr-value:nth-child(2) strong {
        color: var(--pr-green);
    }

    .pr-value:nth-child(3) strong {
        color: var(--card-tone);
    }

    .pr-card-actions {
        display: grid;
        grid-template-columns: minmax(0, .82fr) minmax(0, 1.18fr);
        gap: .4rem;
        margin-top: .62rem;
        padding: .58rem .72rem .72rem;
        border-top: 1px solid var(--pr-border);
        background:
            linear-gradient(
                180deg,
                rgba(248, 250, 249, .5),
                var(--pr-soft)
            );
    }

    .pr-card-actions .pr-btn {
        width: 100%;
        min-width: 0;
    }

    /* =========================================================
       ESTADOS E PAGINAÇÃO
       ========================================================= */

    .pr-loading,
    .pr-empty {
        grid-column: 1 / -1;
        display: grid;
        min-height: 220px;
        place-items: center;
        padding: 1.2rem;
        border: 1px solid var(--pr-border);
        border-radius: 13px;
        background: var(--pr-surface);
        color: var(--pr-secondary);
        text-align: center;
        font-size: .74rem;
        line-height: 1.5;
    }

    .pr-loading-ring {
        width: 28px;
        height: 28px;
        margin: 0 auto .55rem;
        border: 3px solid var(--pr-border);
        border-top-color: var(--pr-violet);
        border-radius: 50%;
        animation: pr-spin .7s linear infinite;
    }

    .pr-footer {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .65rem;
        padding: .42rem .48rem;
        border: 1px solid var(--pr-border);
        border-radius: 11px;
        background: var(--pr-soft);
    }

    .pr-page-info {
        min-width: 0;
        padding-left: .2rem;
        color: var(--pr-secondary);
        font-size: .69rem;
        font-weight: 690;
    }

    @keyframes pr-spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* =========================================================
       MODAL / SHEET
       ========================================================= */

    .pr-overlay {
        position: fixed;
        z-index: 100000;
        inset: 0;
        display: grid;
        place-items: center;
        padding:
            max(.75rem, env(safe-area-inset-top))
            max(.75rem, env(safe-area-inset-right))
            max(.75rem, env(safe-area-inset-bottom))
            max(.75rem, env(safe-area-inset-left));
        background: rgba(11, 28, 18, .34);
        backdrop-filter: none;
    }

    .pr-overlay[hidden] {
        display: none !important;
    }

    .pr-sheet {
        display: flex;
        width: min(820px, 100%);
        max-height: min(92dvh, 880px);
        min-width: 0;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--pr-border);
        border-radius: 16px;
        background: #f3f7f4;
        color: var(--pr-text);
        isolation: isolate;
        box-shadow: -12px 0 38px rgba(8, 24, 15, .16);
        animation:
            pr-sheet-enter
            180ms
            cubic-bezier(.2, .8, .2, 1)
            both;
    }

    .pr-sheet-head {
        display: grid;
        flex: none;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
        padding: .72rem;
        border-bottom: 1px solid #d8e4dc;
        background: #fff;
    }

    .pr-sheet-icon {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border-radius: 11px;
        background: var(--pr-blue-soft);
        color: var(--pr-blue);
    }

    .pr-sheet-icon i {
        font-size: 1.04rem;
    }

    .pr-sheet-head h2 {
        margin: 0;
        font-size: .86rem;
        font-weight: 840;
    }

    .pr-sheet-head p {
        margin: .07rem 0 0;
        color: var(--pr-muted-text);
        font-size: .68rem;
        line-height: 1.35;
    }

    .pr-sheet-body {
        min-height: 210px;
        overflow: auto;
        padding: .74rem;
        overscroll-behavior: contain;
    }

    .pr-sheet-footer {
        display: flex;
        flex: none;
        align-items: center;
        justify-content: space-between;
        gap: .65rem;
        padding:
            .62rem
            .74rem
            calc(.62rem + env(safe-area-inset-bottom));
        border-top: 1px solid #d8e4dc;
        background: #fff;
    }

    .pr-sheet-summary {
        min-width: 0;
    }

    .pr-sheet-summary span,
    .pr-sheet-summary strong {
        display: block;
    }

    .pr-sheet-summary span {
        color: var(--pr-muted-text);
        font-size: .64rem;
        font-weight: 670;
    }

    .pr-sheet-summary strong {
        margin-top: .04rem;
        color: var(--pr-text);
        font-size: .78rem;
        font-weight: 820;
        overflow-wrap: anywhere;
    }

    .pr-state-loading {
        display: grid;
        min-height: 230px;
        place-items: center;
        color: var(--pr-secondary);
        text-align: center;
        font-size: .74rem;
    }

    /* =========================================================
       CONFIGURAÇÕES DE IMPRESSÃO
       ========================================================= */

    .pr-columns {
        margin-bottom: .7rem;
        overflow: hidden;
        border: 1px solid var(--pr-border);
        border-radius: 11px;
        background: #fff;
    }

    .pr-columns summary {
        display: flex;
        min-height: 40px;
        align-items: center;
        gap: .38rem;
        padding: .5rem .6rem;
        cursor: pointer;
        color: var(--pr-secondary);
        font-size: .7rem;
        font-weight: 790;
        list-style: none;
        background: var(--pr-soft);
    }

    .pr-columns summary::-webkit-details-marker {
        display: none;
    }

    .pr-columns summary::before {
        color: var(--pr-violet);
        content: "⚙";
        font-size: .75rem;
    }

    .pr-column-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .32rem .5rem;
        padding: .58rem .64rem .46rem;
    }

    .pr-column-grid label {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .36rem;
        color: var(--pr-secondary);
        font-size: .69rem;
        line-height: 1.35;
    }

    .pr-column-grid input,
    .pr-dist input {
        accent-color: var(--color-primary);
    }

    .pr-scale-wrap {
        padding: .12rem .64rem .48rem;
    }

    .pr-scale-wrap label {
        display: block;
        margin-bottom: .26rem;
        color: var(--pr-secondary);
        font-size: .66rem;
        font-weight: 790;
    }

    .pr-print-status {
        min-height: 1rem;
        padding: 0 .64rem .58rem;
        color: var(--pr-muted-text);
        font-size: .64rem;
        line-height: 1.4;
    }

    .pr-print-status.saved {
        color: var(--pr-green);
    }

    .pr-print-status.error {
        color: var(--pr-red);
    }

    /* =========================================================
       INTEGRIDADE / PENDÊNCIAS
       ========================================================= */

    .pr-ready {
        display: flex;
        align-items: center;
        gap: .42rem;
        margin-bottom: .68rem;
        padding: .58rem .62rem;
        border: 1px solid
            color-mix(
                in srgb,
                var(--pr-green) 18%,
                var(--pr-border)
            );
        border-radius: 10px;
        background: var(--pr-green-soft);
        color: var(--pr-green);
        font-size: .7rem;
        font-weight: 760;
        line-height: 1.4;
    }

    .pr-issues {
        margin-bottom: .7rem;
        overflow: hidden;
        border: 1px solid var(--pr-border);
        border-radius: 11px;
        background: #fff;
    }

    .pr-issues > summary {
        display: flex;
        min-height: 42px;
        align-items: center;
        gap: .42rem;
        padding: .54rem .62rem;
        background: var(--pr-soft);
        cursor: pointer;
        color: var(--pr-secondary);
        font-size: .72rem;
        font-weight: 800;
        list-style: none;
    }

    .pr-issues > summary::-webkit-details-marker {
        display: none;
    }

    .pr-issues > summary i {
        color: var(--pr-amber);
        font-size: .9rem;
    }

    .pr-issue-list {
        display: grid;
        gap: .44rem;
        padding: .5rem;
    }

    .pr-issue {
        --issue-tone: var(--pr-amber);
        --issue-soft: var(--pr-amber-soft);

        padding: .58rem;
        border: 1px solid
            color-mix(
                in srgb,
                var(--issue-tone) 16%,
                var(--pr-border)
            );
        border-radius: 10px;
        background: var(--issue-soft);
    }

    .pr-issue.critical {
        --issue-tone: var(--pr-red);
        --issue-soft: var(--pr-red-soft);
    }

    .pr-issue-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .45rem;
        color: var(--pr-text);
        font-size: .73rem;
        font-weight: 830;
    }

    .pr-issue p {
        margin: .24rem 0 0;
        color: var(--pr-secondary);
        font-size: .68rem;
        line-height: 1.5;
    }

    .pr-issue-action {
        margin-top: .46rem;
    }

    /* =========================================================
       COMPROVANTES
       ========================================================= */

    .pr-section-head {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: .7rem;
        margin-bottom: .52rem;
    }

    .pr-section-head h3,
    .pr-section-head p {
        margin: 0;
    }

    .pr-section-head h3 {
        font-size: .8rem;
        font-weight: 830;
    }

    .pr-section-head p {
        margin-top: .06rem;
        color: var(--pr-muted-text);
        font-size: .65rem;
    }

    .pr-receipts {
        display: grid;
        gap: .46rem;
    }

    .pr-receipt {
        padding: .62rem;
        border: 1px solid var(--pr-border);
        border-radius: 11px;
        background: #fff;
    }

    .pr-receipt-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .56rem;
    }

    .pr-receipt h4 {
        margin: 0;
        font-size: .8rem;
    }

    .pr-receipt-link {
        padding: 0;
        border: 0;
        background: transparent;
        color: var(--pr-blue);
        cursor: pointer;
        font: inherit;
        font-weight: 830;
        text-align: left;
    }

    .pr-receipt-link:hover {
        text-decoration: underline;
    }

    .pr-receipt-meta {
        display: flex;
        gap: .48rem;
        flex-wrap: wrap;
        margin-top: .18rem;
        color: var(--pr-muted-text);
        font-size: .66rem;
        line-height: 1.35;
    }

    .pr-receipt-meta strong {
        color: var(--pr-green);
    }

    .pr-receipt-note {
        margin-top: .42rem;
        padding: .44rem .5rem;
        border-radius: 8px;
        background: var(--pr-red-soft);
        color: #991b1b;
        font-size: .64rem;
        line-height: 1.42;
    }

    .pr-receipt-actions {
        margin-top: .5rem;
    }

    /* =========================================================
       SELEÇÃO DE DISTRIBUIÇÕES
       ========================================================= */

    .pr-selection-tools {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .55rem;
        margin-bottom: .52rem;
    }

    .pr-selection-tools strong {
        color: var(--pr-text);
        font-size: .78rem;
        font-weight: 830;
    }

    .pr-selection-list {
        display: grid;
        gap: .44rem;
    }

    .pr-dist {
        --dist-tone: var(--pr-sky);
        --dist-soft: var(--pr-sky-soft);

        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .58rem;
        padding: .62rem;
        border: 1px solid var(--pr-border);
        border-radius: 11px;
        background: #fff;
        cursor: pointer;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            box-shadow 150ms ease;
    }

    .pr-dist:has(input:checked) {
        border-color:
            color-mix(
                in srgb,
                var(--dist-tone) 24%,
                var(--pr-border)
            );
        background:
            linear-gradient(
                135deg,
                #fff,
                var(--dist-soft)
            );
        box-shadow: inset 3px 0 var(--dist-tone);
    }

    .pr-dist.disabled {
        cursor: not-allowed;
        opacity: .58;
    }

    .pr-dist input {
        width: 18px;
        height: 18px;
        margin-top: .08rem;
    }

    .pr-dist-head {
        display: flex;
        min-width: 0;
        align-items: flex-start;
        justify-content: space-between;
        gap: .5rem;
    }

    .pr-dist h4 {
        margin: 0;
        color: var(--pr-text);
        font-size: .79rem;
        font-weight: 820;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .pr-dist-client {
        margin-top: .08rem;
        color: var(--pr-muted-text);
        font-size: .67rem;
        line-height: 1.35;
    }

    .pr-dist-values {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .32rem;
        margin-top: .5rem;
        padding: .44rem;
        border-radius: 9px;
        background: var(--pr-soft);
    }

    .pr-dist-value {
        min-width: 0;
    }

    .pr-dist-value span,
    .pr-dist-value strong {
        display: block;
    }

    .pr-dist-value span {
        color: var(--pr-muted-text);
        font-size: .61rem;
    }

    .pr-dist-value strong {
        margin-top: .04rem;
        color: var(--pr-text);
        font-size: .68rem;
        font-weight: 800;
        line-height: 1.3;
        overflow-wrap: anywhere;
    }

    .pr-dist-error {
        margin-top: .42rem;
        color: #b91c1c;
        font-size: .67rem;
        font-weight: 750;
        line-height: 1.4;
    }

    /* =========================================================
       CONFIRMAÇÃO E TOASTS
       ========================================================= */

    .pr-dialog {
        width: min(430px, calc(100vw - 1rem));
        padding: 0;
        overflow: hidden;
        border: 1px solid var(--pr-border);
        border-radius: 14px;
        background: var(--pr-surface);
        color: var(--pr-text);
        box-shadow: 0 24px 68px rgba(8, 24, 15, .24);
    }

    .pr-dialog::backdrop {
        background: rgba(11, 28, 18, .34);
        backdrop-filter: none;
    }

    .pr-dialog-body {
        padding: .82rem;
    }

    .pr-dialog h3 {
        margin: 0;
        font-size: .84rem;
        font-weight: 840;
    }

    .pr-dialog p {
        margin: .28rem 0 0;
        color: var(--pr-secondary);
        font-size: .7rem;
        line-height: 1.5;
    }

    .pr-dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: .42rem;
        padding: .62rem .82rem;
        border-top: 1px solid var(--pr-border);
        background: var(--pr-soft);
    }

    .pr-toast-wrap {
        position: fixed;
        z-index: 100100;
        right: 1rem;
        bottom: calc(1rem + var(--app-bottom-nav-height, 0px));
        display: grid;
        gap: .42rem;
    }

    .pr-toast {
        max-width: 360px;
        padding: .62rem .72rem;
        border: 1px solid var(--pr-border);
        border-left: 3px solid var(--pr-green);
        border-radius: 10px;
        background: var(--pr-surface);
        box-shadow: 0 12px 30px rgba(15, 23, 42, .15);
        color: var(--pr-secondary);
        font-size: .7rem;
        line-height: 1.45;
    }

    .pr-toast.error {
        border-left-color: var(--pr-red);
    }

    @keyframes pr-sheet-enter {
        from {
            opacity: 0;
            transform: translateY(8px) scale(.985);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 980px) {
        .pr-summary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .pr-head {
            grid-template-columns: auto minmax(0, 1fr) auto;
        }

        .pr-head-icon {
            display: none;
        }

        .pr-head-side {
            grid-column: 2 / -1;
            grid-row: 2;
            justify-content: flex-start;
        }

        .pr-overview-copy p {
            display: none;
        }

        .pr-summary {
            display: flex;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scrollbar-width: none;
        }

        .pr-summary::-webkit-scrollbar {
            display: none;
        }

        .pr-stat {
            min-width: 154px;
            scroll-snap-align: start;
        }

        .pr-tools-shell,
        .pr-tools {
            grid-template-columns: 1fr;
        }

        .pr-tools-shell > .pr-btn {
            width: 100%;
        }

        .pr-grid {
            grid-template-columns: 1fr;
        }

        .pr-overlay {
            align-items: end;
            padding: 0;
        }

        .pr-sheet {
            width: 100%;
            max-height: 94dvh;
            border-right: 0;
            border-bottom: 0;
            border-left: 0;
            border-radius: 16px 16px 0 0;
            animation-name: pr-sheet-mobile-enter;
        }

        .pr-sheet-body {
            padding: .62rem;
        }

        .pr-sheet-head {
            padding: .66rem;
        }

        .pr-sheet-footer {
            align-items: stretch;
            flex-direction: column;
            padding-right: .62rem;
            padding-left: .62rem;
        }

        .pr-modal-actions {
            display: grid;
            width: 100%;
            grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr);
        }

        .pr-modal-actions .pr-btn {
            width: 100%;
        }

        .pr-receipt-top {
            flex-direction: column;
        }

        .pr-dist-values {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pr-toast-wrap {
            right: .6rem;
            left: .6rem;
        }

        .pr-toast {
            max-width: none;
        }
    }

    @media (max-width: 560px) {
        .pr {
            gap: .7rem;
        }

        .pr-head {
            padding: .62rem;
        }

        .pr-head-copy h1 {
            font-size: .98rem;
        }

        .pr-head-meta {
            gap: .2rem .44rem;
            font-size: .67rem;
        }

        .pr-project-status {
            min-height: 27px;
            font-size: .63rem;
        }

        .pr-overview-head {
            padding: .56rem .62rem;
        }

        .pr-summary {
            padding: .5rem;
        }

        .pr-stat {
            min-width: 146px;
            min-height: 70px;
        }

        .pr-tools-shell {
            padding: .44rem;
        }

        .pr-card-head {
            padding: .64rem .62rem .5rem;
        }

        .pr-meter {
            margin-right: .62rem;
            margin-left: .62rem;
        }

        .pr-progress-label {
            padding-right: .62rem;
            padding-left: .62rem;
        }

        .pr-values {
            margin-right: .62rem;
            margin-left: .62rem;
        }

        .pr-card-actions {
            padding-right: .62rem;
            padding-left: .62rem;
        }

        .pr-column-grid {
            grid-template-columns: 1fr;
        }

        .pr-selection-tools {
            align-items: stretch;
            flex-direction: column;
        }

        .pr-selection-tools .pr-btn {
            width: 100%;
        }

        .pr-footer {
            align-items: stretch;
            flex-direction: column;
        }

        .pr-page-info {
            padding-left: 0;
            text-align: center;
        }

        .pr-pager {
            display: grid;
            width: 100%;
            grid-template-columns: 1fr 1fr;
        }

        .pr-pager .pr-btn {
            width: 100%;
        }
    }

    @media (max-width: 400px) {
        .pr-head {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .pr-back {
            width: 36px;
            height: 36px;
        }

        .pr-head-action {
            display: none;
        }

        .pr-head-side {
            grid-column: 2;
        }

        .pr-overview-action {
            display: none;
        }

        .pr-values {
            grid-template-columns: 1fr 1fr;
        }

        .pr-value:first-child {
            grid-column: 1 / -1;
        }

        .pr-card-actions {
            grid-template-columns: 1fr;
        }

        .pr-receipt-actions {
            display: grid;
            grid-template-columns: 1fr;
        }

        .pr-receipt-actions .pr-btn {
            width: 100%;
        }
    }

    @keyframes pr-sheet-mobile-enter {
        from {
            opacity: 0;
            transform: translateY(22px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* =========================================================
       COMPOSIÇÃO WORKSPACE — LISTA + RESUMO OPERACIONAL
       ========================================================= */

    .pr-card {
        transition:
            border-color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .pr-card:hover {
        border-color:
            color-mix(
                in srgb,
                var(--card-tone) 18%,
                var(--pr-border)
            );
        box-shadow:
            inset 3px 0 var(--card-tone),
            0 8px 20px rgba(15, 35, 23, .06);
    }

    .pr-columns summary::after,
    .pr-issues > summary::after {
        margin-left: auto;
        color: var(--pr-muted-text);
        content: "\e136";
        font-family: "Phosphor";
        font-size: .8rem;
        transition: transform 150ms ease;
    }

    .pr-columns[open] summary::after,
    .pr-issues[open] > summary::after {
        transform: rotate(180deg);
    }

    .pr-sheet-body {
        background: #f3f7f4;
        scrollbar-gutter: stable;
    }

    .pr-receipt,
    .pr-dist,
    .pr-columns,
    .pr-issues {
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 35, 23, .025);
    }

    .pr-receipt {
        border-left: 3px solid var(--pr-blue);
    }

    .pr-selection-tools {
        padding: .62rem;
        border: 1px solid var(--pr-border);
        border-radius: 11px;
        background: #fff;
    }

    @media (min-width: 1024px) {
        .pr {
            grid-template-columns: minmax(0, 1fr) 292px;
            align-items: start;
        }

        .pr-head {
            grid-column: 1 / -1;
        }

        .pr-overview {
            position: sticky;
            top: .75rem;
            grid-column: 2;
            grid-row: 2 / span 3;
        }

        .pr-overview-head {
            align-items: flex-start;
            flex-direction: column;
        }

        .pr-overview-action {
            margin-left: 2.75rem;
        }

        .pr-summary {
            grid-template-columns: 1fr;
        }

        .pr-stat {
            min-height: 58px;
            padding: .48rem .52rem;
        }

        .pr-stat-icon {
            width: 32px;
            height: 32px;
        }

        .pr-tools-shell,
        .pr-grid,
        .pr-footer {
            grid-column: 1;
        }

        .pr-grid {
            grid-template-columns: 1fr;
        }

        .pr-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 228px;
            grid-template-rows: auto auto auto auto;
            box-shadow:
                inset 3px 0 var(--card-tone),
                var(--shadow-sm);
        }

        .pr-card-head {
            grid-column: 1;
            grid-row: 1;
            padding: .72rem .8rem .54rem .88rem;
        }

        .pr-meter {
            grid-column: 1;
            grid-row: 2;
            margin-right: .8rem;
            margin-left: .88rem;
        }

        .pr-progress-label {
            grid-column: 1;
            grid-row: 3;
            padding-right: .8rem;
            padding-left: .88rem;
        }

        .pr-values {
            grid-column: 1;
            grid-row: 4;
            margin: .56rem .8rem .72rem .88rem;
        }

        .pr-card-actions {
            display: flex;
            grid-column: 2;
            grid-row: 1 / 5;
            align-items: stretch;
            justify-content: center;
            flex-direction: column;
            margin-top: 0;
            padding: .72rem;
            border-top: 0;
            border-left: 1px solid var(--pr-border);
        }

        .pr-card-actions .pr-btn {
            min-height: 42px;
        }

        .pr-overlay {
            place-items: stretch end;
            padding: 0;
        }

        .pr-sheet {
            width: min(720px, 100dvw);
            height: 100dvh;
            max-height: 100dvh;
            border-top: 0;
            border-right: 0;
            border-bottom: 0;
            border-radius: 18px 0 0 18px;
            animation-name: pr-sheet-desktop-enter;
        }

        .pr-sheet-head {
            padding: .82rem .9rem;
        }

        .pr-sheet-body {
            padding: .86rem .9rem;
        }

        .pr-sheet-footer {
            padding-right: .9rem;
            padding-left: .9rem;
            box-shadow: 0 -5px 16px rgba(15, 35, 23, .035);
        }
    }

    @media (max-width: 1023px) {
        .pr-summary {
            grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
        }

        .pr-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 760px) {
        .pr-summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            overflow: visible;
            scroll-snap-type: none;
        }

        .pr-stat {
            min-width: 0;
            min-height: 62px;
        }

        .pr-stat:last-child {
            grid-column: 1 / -1;
        }

        .pr-card {
            box-shadow:
                inset 3px 0 var(--card-tone),
                var(--shadow-sm);
        }

        .pr-sheet {
            height: min(96dvh, 900px);
            max-height: 96dvh;
        }

        .pr-sheet-head {
            position: relative;
            padding-top: .82rem;
        }

        .pr-columns summary,
        .pr-issues > summary {
            min-height: 44px;
        }

        .pr-receipt-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pr-receipt-actions .pr-btn {
            width: 100%;
        }
    }

    @media (max-width: 520px) {
        .pr-summary {
            gap: .36rem;
            padding: .44rem;
        }

        .pr-stat {
            grid-template-columns: 30px minmax(0, 1fr);
            gap: .4rem;
            padding: .46rem;
        }

        .pr-stat-icon {
            width: 30px;
            height: 30px;
        }

        .pr-card-head {
            flex-direction: column;
        }

        .pr-card-head > .pr-badge {
            margin-left: 3rem;
        }

        .pr-card-actions,
        .pr-receipt-actions {
            grid-template-columns: 1fr;
        }

        .pr-selection-tools {
            padding: .54rem;
        }

        .pr-sheet-footer {
            gap: .5rem;
        }
    }

    @keyframes pr-sheet-desktop-enter {
        from {
            opacity: .72;
            transform: translateX(28px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .pr *,
        .pr *::before,
        .pr *::after,
        .pr-overlay *,
        .pr-dialog * {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<main
    class="pr"
    id="producerReceipts"
    data-tenant="{{ $tenantSlug }}"
    data-project="{{ $project->id }}"
    data-project-title="{{ $project->title ?: 'Projeto' }}"
    data-document-path="Comprovantes/{{ now()->format('Y/m') }}/{{ \Illuminate\Support\Str::slug($project->title ?: 'projeto-'.$project->id) }}"
    data-list-url="{{ route('delivery.projects.producers-data', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
    data-preferences-url="{{ route('delivery.projects.receipt-print-preferences.update', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
>
    <header
        class="pr-head status-{{ $projectStatusValue }}"
        aria-labelledby="pr-page-title"
    >
        <a
            class="pr-back"
            href="{{ route('delivery.projects.deliveries', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            aria-label="Voltar às entregas do projeto"
            title="Voltar às entregas"
        >
            <i class="ph ph-arrow-left" aria-hidden="true"></i>
        </a>

        <span class="pr-head-icon" aria-hidden="true">
            <i class="ph-duotone ph-receipt"></i>
        </span>

        <div class="pr-head-copy">
            <h1 id="pr-page-title">{{ $project->title }}</h1>

            <div class="pr-head-meta">
                <span>
                    <i class="ph ph-users-three" aria-hidden="true"></i>
                    Comprovantes de {{ $memberTermPluralLower }}
                </span>

                @if($projectPeriod)
                    <span>
                        <i class="ph ph-calendar-dots" aria-hidden="true"></i>
                        {{ $projectPeriod }}
                    </span>
                @endif
            </div>
        </div>

        <div class="pr-head-side">
            <span class="pr-project-status">
                <i class="ph {{ $projectStatusIcon }}" aria-hidden="true"></i>
                {{ $projectStatusLabel }}
            </span>

            <a
                class="pr-head-action"
                href="{{ route('delivery.projects.associates.index', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
                aria-label="Participação e limites"
                title="Participação e limites"
            >
                <i class="ph ph-sliders-horizontal" aria-hidden="true"></i>
            </a>
        </div>
    </header>

    <section class="pr-overview" aria-labelledby="pr-overview-title">
        <header class="pr-overview-head">
            <div class="pr-overview-title">
                <span class="pr-overview-icon" aria-hidden="true">
                    <i class="ph-duotone ph-chart-donut"></i>
                </span>

                <div class="pr-overview-copy">
                    <h2 id="pr-overview-title">Situação dos comprovantes</h2>
                    <p>Toque em um indicador para filtrar a lista.</p>
                </div>
            </div>

            <a
                class="pr-overview-action"
                href="{{ route('delivery.projects.associates.index', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            >
                Participação e limites
            </a>
        </header>

        <div
            class="pr-summary"
            id="pr-summary"
            aria-label="Resumo dos comprovantes"
        ></div>
    </section>

    <section class="pr-tools-shell" aria-label="Busca e filtros">
        <div class="pr-tools">
            <label class="pr-search">
                <i class="ph ph-magnifying-glass" aria-hidden="true"></i>

                <input
                    class="pr-control"
                    id="pr-search"
                    type="search"
                    placeholder="Buscar {{ $memberTermLower }} ou matrícula"
                    autocomplete="off"
                    aria-label="Buscar {{ $memberTermLower }} ou matrícula"
                >
            </label>

            <select
                class="pr-control"
                id="pr-filter"
                aria-label="Filtrar {{ $memberTermPluralLower }}"
            >
                <option value="all">Todos os {{ $memberTermPluralLower }}</option>
                <option value="pending">Com distribuições pendentes</option>
                <option value="complement">Precisam de complemento</option>
                <option value="obsolete">Com comprovante obsoleto</option>
                <option value="billed">Comprovantes bloqueados</option>
                <option value="paid">Pagos ou parcialmente pagos</option>
            </select>
        </div>

        <button
            class="pr-btn"
            type="button"
            onclick="document.getElementById('pr-search').value='';document.getElementById('pr-filter').value='all';document.getElementById('pr-filter').dispatchEvent(new Event('change'));"
            title="Limpar filtros"
        >
            <i class="ph ph-funnel-x" aria-hidden="true"></i>
            Limpar
        </button>
    </section>

    <section class="pr-grid" id="pr-grid" aria-live="polite">
        <div class="pr-loading">
            <div>
                <div class="pr-loading-ring"></div>
                Carregando {{ $memberTermPluralLower }}...
            </div>
        </div>
    </section>

    <footer class="pr-footer">
        <span class="pr-page-info" id="pr-page-info">-</span>

        <div class="pr-pager">
            <button class="pr-btn" id="pr-prev" type="button">
                <i class="ph ph-caret-left" aria-hidden="true"></i>
                Anterior
            </button>

            <button class="pr-btn" id="pr-next" type="button">
                Próxima
                <i class="ph ph-caret-right" aria-hidden="true"></i>
            </button>
        </div>
    </footer>
</main>

<div
    class="pr-overlay"
    id="pr-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pr-modal-title"
    hidden
>
    <section class="pr-sheet">
        <header class="pr-sheet-head">
            <span class="pr-sheet-icon" aria-hidden="true">
                <i class="ph-duotone ph-files"></i>
            </span>

            <div>
                <h2 id="pr-modal-title">Comprovantes</h2>
                <p id="pr-modal-person">{{ $memberTerm }}</p>
            </div>

            <button
                class="pr-btn icon"
                id="pr-modal-close"
                type="button"
                aria-label="Fechar"
            >
                <i class="ph ph-x" aria-hidden="true"></i>
            </button>
        </header>

        <div class="pr-sheet-body">
            <details class="pr-columns" id="pr-print-settings">
                <summary>
                    Aparência do PDF
                </summary>

                <div class="pr-column-grid">
                    <div id="pr-fee-columns" style="display:contents"></div>

                    <label>
                        <input
                            class="pr-column"
                            type="checkbox"
                            value="delivery_date"
                            checked
                        >
                        Data da entrega
                    </label>

                    <label>
                        <input
                            class="pr-column"
                            type="checkbox"
                            value="unit_price"
                            checked
                        >
                        Valor unitário
                    </label>

                    <label>
                        <input
                            class="pr-column"
                            type="checkbox"
                            value="gross"
                            checked
                        >
                        Valor bruto
                    </label>

                    <label>
                        <input
                            class="pr-column"
                            type="checkbox"
                            value="admin_fee"
                        >
                        Taxa administrativa
                    </label>

                    <label>
                        <input
                            class="pr-column"
                            type="checkbox"
                            value="net"
                        >
                        Valor líquido
                    </label>
                </div>

                <div class="pr-scale-wrap">
                    <label for="pr-table-scale">
                        Escala da tabela
                    </label>

                    <select
                        id="pr-table-scale"
                        class="pr-control"
                    >
                        <option value="100">100% · Normal</option>
                        <option value="90">90% · Compacta</option>
                        <option value="80">80% · Reduzida</option>
                        <option value="70">70% · Muito reduzida</option>
                    </select>
                </div>

                <div class="pr-print-status" id="pr-print-status">
                    Configuração compartilhada por todos os comprovantes deste projeto.
                </div>
            </details>

            <div class="pr-state-loading" id="pr-modal-loading">
                <div>
                    <div class="pr-loading-ring"></div>
                    Carregando comprovantes...
                </div>
            </div>

            <div id="pr-overview" hidden>
                <div id="pr-issues-slot"></div>

                <div class="pr-section-head">
                    <div>
                        <h3>Histórico de comprovantes</h3>
                        <p>Consulte, atualize ou gere uma nova via.</p>
                    </div>
                </div>

                <div class="pr-receipts" id="pr-receipts"></div>
            </div>

            <div id="pr-selection" hidden>
                <div id="pr-selection-issues"></div>

                <div class="pr-selection-tools">
                    <div>
                        <strong id="pr-selection-title">
                            Novo comprovante
                        </strong>

                        <div class="pr-sub" id="pr-selection-help">
                            Escolha as distribuições.
                        </div>
                    </div>

                    <button class="pr-btn" id="pr-toggle-all" type="button">
                        <i class="ph ph-list-checks" aria-hidden="true"></i>
                        Marcar disponíveis
                    </button>
                </div>

                <div
                    class="pr-selection-list"
                    id="pr-distributions"
                ></div>
            </div>
        </div>

        <footer class="pr-sheet-footer">
            <div class="pr-sheet-summary">
                <span id="pr-footer-label">
                    Distribuições selecionadas
                </span>

                <strong id="pr-footer-value">
                    0 itens · R$ 0,00
                </strong>
            </div>

            <div class="pr-modal-actions">
                <button
                    class="pr-btn"
                    id="pr-modal-back"
                    type="button"
                >
                    Fechar
                </button>

                <button
                    class="pr-btn primary"
                    id="pr-modal-primary"
                    type="button"
                    hidden
                ></button>
            </div>
        </footer>
    </section>
</div>

<dialog class="pr-dialog" id="pr-confirm">
    <div class="pr-dialog-body">
        <h3>Confirmar correção</h3>
        <p id="pr-confirm-message"></p>
    </div>

    <div class="pr-dialog-actions">
        <button
            class="pr-btn"
            id="pr-confirm-cancel"
            type="button"
        >
            Cancelar
        </button>

        <button
            class="pr-btn danger"
            id="pr-confirm-ok"
            type="button"
        >
            Confirmar
        </button>
    </div>
</dialog>

<div class="pr-toast-wrap" id="pr-toasts"></div>

<script>
(() => {
    const root = document.getElementById('producerReceipts');
    if (!root) return;

    const tenant = root.dataset.tenant;
    const project = Number(root.dataset.project);
    const memberTerm = @js($memberTerm);
    const memberTermLower = @js($memberTermLower);
    const memberTermPlural = @js($memberTermPlural);
    const memberTermPluralLower = @js($memberTermPluralLower);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const state = {
        page: 1, lastPage: 1, filter: 'all', timer: null, busy: false,
        associateId: null, associateName: '', check: null, receiptId: null, distributions: [],
        preferenceTimer: null, preferencePromise: null,
    };
    const $ = id => document.getElementById(id);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const money = value => Number(value || 0).toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
    const qty = value => Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits:3 });
    const icons = () => {};
    const initials = name => String(name || '?').trim().split(/\s+/).slice(0,2).map(part => part[0] || '').join('').toUpperCase();

    async function json(url, options = {}) {
        const response = await fetch(url, {
            credentials:'same-origin',
            ...options,
            headers:{ Accept:'application/json', 'X-CSRF-TOKEN':csrf, ...(options.headers || {}) },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) {
            throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Não foi possível concluir a operação.');
        }
        return data;
    }

    function toast(message, type = 'success') {
        const node = document.createElement('div');
        node.className = `pr-toast ${type === 'error' ? 'error' : ''}`;
        node.textContent = message;
        $('pr-toasts').appendChild(node);
        setTimeout(() => node.remove(), 4500);
    }

    function badge(status, label, locked = false) {
        const tone = status === 'obsolete' ? 'red'
            : status === 'paid' ? 'green'
            : status === 'partially_paid' ? 'blue'
            : locked || status === 'pending_payment' ? 'yellow' : '';
        return `<span class="pr-badge ${tone}">${esc(label || status || 'Rascunho')}</span>`;
    }

    function renderSummary(summary) {
        const items = [
            ['all', memberTermPlural, summary.producers || 0, 'ph-users-three'],
            ['pending', 'Distribuições pendentes', summary.pending_distributions || 0, 'ph-clock-countdown'],
            ['complement', 'Precisam de complemento', summary.needs_complement || 0, 'ph-puzzle-piece'],
            ['obsolete', 'Comprovantes obsoletos', summary.obsolete_receipts || 0, 'ph-warning-circle'],
            ['paid', 'Pagos ou parciais', summary.paid_receipts || 0, 'ph-wallet'],
        ];
        $('pr-summary').innerHTML = items.map(([filter,label,value,icon]) => `
            <button class="pr-stat ${state.filter === filter ? 'active' : ''}" type="button" data-summary-filter="${filter}">
                <span class="pr-stat-icon" aria-hidden="true"><i class="ph-duotone ${icon}"></i></span>
                <span class="pr-stat-copy"><strong>${value}</strong><small>${label}</small></span>
            </button>`).join('');
    }

    function producerCard(row) {
        const covered = Math.max(0, Number(row.deliveries || 0) - Number(row.pending_distributions || 0));
        const percent = row.deliveries > 0 ? Math.min(100, covered / row.deliveries * 100) : 0;
        const receipt = row.latest_receipt;
        const status = receipt
            ? badge(receipt.status, receipt.status_label, receipt.is_locked)
            : '<span class="pr-badge">Sem comprovante</span>';
        const actionLabel = receipt?.status === 'obsolete'
            ? 'Revisar comprovante'
            : row.pending_distributions > 0
                ? `Incluir ${row.pending_distributions} pendente(s)`
                : receipt ? 'Abrir comprovantes' : 'Criar comprovante';
        const cardTone = receipt?.status === 'obsolete'
            ? 'is-danger'
            : Number(row.pending_distributions || 0) > 0
                ? 'is-warning'
                : receipt?.status === 'paid'
                    ? 'is-success'
                    : receipt?.status === 'partially_paid'
                        ? 'is-blue'
                        : receipt
                            ? 'is-violet'
                            : 'is-slate';
        return `<article class="pr-card ${cardTone}">
            <div class="pr-card-head">
                <div class="pr-person">
                    <div class="pr-avatar">${esc(initials(row.name))}</div>
                    <div><h2>${esc(row.name)}</h2><div class="pr-sub">${row.registration && row.registration !== '-' ? `Matrícula ${esc(row.registration)}` : `${row.receipt_count} comprovante(s)`}</div></div>
                </div>
                ${status}
            </div>
            <div class="pr-meter ${percent >= 100 ? 'done' : ''}" role="progressbar" aria-label="Distribuições em comprovantes" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(percent)}"><span style="width:${percent}%"></span></div>
            <div class="pr-progress-label"><span>Incluídas em comprovantes</span><strong>${covered} de ${row.deliveries}</strong></div>
            <div class="pr-values">
                <div class="pr-value"><span>Quantidade entregue</span><strong>${qty(row.quantity)}</strong></div>
                <div class="pr-value"><span>Valor líquido</span><strong>${money(row.net_value)}</strong></div>
                <div class="pr-value"><span>A incluir</span><strong>${row.pending_distributions}</strong></div>
            </div>
            <div class="pr-card-actions">
                <a class="pr-btn" href="/${encodeURIComponent(tenant)}/delivery/projects/${project}/associates/${row.associate_id}">
                    <i class="ph ph-user"></i> Ver cadastro
                </a>
                <button class="pr-btn primary" type="button" data-open-receipts="${row.associate_id}" data-associate-name="${esc(row.name)}">
                    <i class="ph ph-file-check"></i> ${esc(actionLabel)}
                </button>
            </div>
        </article>`;
    }

    async function loadProducers(reset = false) {
        if (reset) state.page = 1;
        $('pr-grid').innerHTML = `<div class="pr-loading"><div><div class="pr-loading-ring"></div>Carregando ${esc(memberTermPluralLower)}...</div></div>`;
        const params = new URLSearchParams({
            page: state.page,
            filter: state.filter,
            search: $('pr-search').value.trim(),
            per_page: 18,
        });
        try {
            const data = await json(`${root.dataset.listUrl}?${params}`);
            renderSummary(data.summary || {});
            $('pr-grid').innerHTML = data.rows?.length
                ? data.rows.map(producerCard).join('')
                : `<div class="pr-empty">Nenhum ${esc(memberTermLower)} encontrado neste filtro.</div>`;
            state.page = data.pagination?.current_page || 1;
            state.lastPage = data.pagination?.last_page || 1;
            $('pr-page-info').textContent = `${data.pagination?.total || 0} ${memberTermPluralLower} · página ${state.page} de ${state.lastPage}`;
            $('pr-prev').disabled = state.page <= 1;
            $('pr-next').disabled = state.page >= state.lastPage;
            icons();
        } catch (error) {
            $('pr-grid').innerHTML = `<div class="pr-empty">${esc(error.message)}</div>`;
        }
    }

    function setModalView(view) {
        $('pr-modal-loading').hidden = view !== 'loading';
        $('pr-overview').hidden = view !== 'overview';
        $('pr-selection').hidden = view !== 'selection';
        $('pr-modal-primary').hidden = view !== 'selection';
        $('pr-footer-label').textContent = view === 'selection' ? 'Distribuições selecionadas' : `Situação do ${memberTermLower}`;
        if (view !== 'selection') $('pr-footer-value').textContent = state.check?.uncovered_count ? `${state.check.uncovered_count} pendente(s)` : 'Sem distribuições pendentes';
        $('pr-modal-back').textContent = view === 'selection' && state.check?.has_receipts ? 'Voltar' : 'Fechar';
    }

    function actionableIssues() {
        return (state.check?.issues || []).filter(issue => issue.severity === 'critical' || issue.severity === 'warning');
    }

    function issueAction(issue) {
        if (!issue.actionKey) return '';
        const labels = {
            open_distribution:'Abrir distribuição',
            edit_distribution:'Corrigir distribuição',
            detach_missing_associate_receipt:'Desvincular comprovante',
            delete_orphan_distribution:'Excluir registro órfão',
            restore_parent_delivery:'Restaurar entrega-pai',
            open_producers:'Selecionar pendentes',
        };
        return `<button class="pr-btn ${issue.severity === 'critical' ? 'danger' : ''}" type="button"
            data-issue-action="${esc(issue.actionKey)}"
            data-delivery-id="${Number(issue.deliveryId || 0)}"
            data-distribution-id="${Number(issue.distributionId || 0)}">
            ${esc(labels[issue.actionKey] || 'Resolver')}
        </button>`;
    }

    function renderIssues(targetId) {
        const target = $(targetId);
        const issues = actionableIssues();
        if (!issues.length) {
            target.innerHTML = `<div class="pr-ready"><i class="ph-duotone ph-check-circle"></i> Nenhuma pendência deste ${esc(memberTermLower)} bloqueia o comprovante.</div>`;
            return;
        }
        const critical = issues.filter(issue => issue.severity === 'critical').length;
        const warning = issues.filter(issue => issue.severity === 'warning').length;
        target.innerHTML = `<details class="pr-issues" ${critical ? 'open' : ''}>
            <summary><i class="ph-duotone ${critical ? 'ph-warning-circle' : 'ph-warning'}"></i>
                ${critical ? `${critical} correção(ões) necessária(s)` : `${warning} aviso(s) operacional(is)`}
            </summary>
            <div class="pr-issue-list">${issues.map(issue => `<article class="pr-issue ${esc(issue.severity)}">
                <div class="pr-issue-title"><span>${esc(issue.title)}</span>${badge(issue.severity === 'critical' ? 'obsolete' : 'pending_payment', issue.severity === 'critical' ? 'Crítico' : 'Atenção')}</div>
                <p>${esc(issue.message)}</p>
                <p><strong>Como resolver:</strong> ${esc(issue.action || '')}</p>
                <div class="pr-issue-action">${issueAction(issue)}</div>
            </article>`).join('')}</div>
        </details>`;
    }

    function renderReceipts() {
        renderIssues('pr-issues-slot');
        const receipts = state.check?.receipts || [];
        $('pr-receipts').innerHTML = receipts.length ? receipts.map(receipt => `
            <article class="pr-receipt">
                <div class="pr-receipt-top">
                    <div>
                        <h4><button class="pr-receipt-link" type="button" data-preview-url="${esc(receipt.reprint_url)}?preview=1">Comprovante ${esc(receipt.number)}</button></h4>
                        <div class="pr-receipt-meta">
                            <span>${esc(receipt.issued_at)}</span>
                            <span>${receipt.distribution_count} distribuição(ões)</span>
                            ${receipt.total_net_value > 0 ? `<strong>${money(receipt.total_net_value)}</strong>` : ''}
                        </div>
                    </div>
                    ${badge(receipt.status, receipt.status_label, !receipt.can_update)}
                </div>
                ${receipt.status === 'obsolete' ? `<div class="pr-receipt-note">${esc(receipt.obsolete_reason || 'Este comprovante precisa ser regenerado.')}${receipt.obsolete_at ? ` · ${esc(receipt.obsolete_at)}` : ''}</div>` : ''}
                <div class="pr-receipt-actions">
                    ${receipt.can_update ? `<button class="pr-btn" type="button" data-edit-receipt="${receipt.id}"><i class="ph ph-list-checks"></i> Alterar distribuições</button>` : ''}
                    ${receipt.can_regenerate ? `<button class="pr-btn danger" type="button" data-regenerate="${receipt.id}"><i class="ph ph-arrows-clockwise"></i> Regenerar</button>` : ''}
                    ${receipt.status !== 'obsolete' ? `<button class="pr-btn" type="button" data-reprint-url="${esc(receipt.reprint_url)}?preview=1"><i class="ph ph-eye"></i> Visualizar e imprimir</button>` : ''}
                </div>
            </article>`).join('') : `<div class="pr-empty">Nenhum comprovante gerado para este ${esc(memberTermLower)}.</div>`;
        $('pr-modal-primary').hidden = false;
        $('pr-modal-primary').innerHTML = '<i class="ph ph-plus"></i> Novo comprovante';
        $('pr-modal-primary').dataset.action = 'new';
        setModalView('overview');
        $('pr-modal-primary').hidden = false;
        icons();
    }

    async function openModal(associateId, name) {
        state.associateId = Number(associateId);
        state.associateName = name;
        state.receiptId = null;
        state.check = null;
        $('pr-modal-person').textContent = name;
        $('pr-modal').hidden = false;
        setModalView('loading');
        try {
            state.check = await json(`/${tenant}/delivery/projects/${project}/associates/${state.associateId}/receipt-check`);
            renderFeeColumns();
            if (state.check.has_receipts) renderReceipts();
            else await openSelection();
        } catch (error) {
            closeModal();
            toast(error.message, 'error');
        }
    }

    function distributionCard(item) {
        const invalid = !item.selectable;
        const checked = item.in_current_receipt && !invalid;
        const reason = !item.customer_name || item.customer_name === '—'
            ? 'Informe um cliente para esta distribuição.'
            : Number(item.unit_price) <= 0 ? 'Configure um preço válido antes de incluir.' : '';
        return `<label class="pr-dist ${invalid ? 'disabled' : ''}">
            <input class="pr-dist-check" type="checkbox" value="${item.id}" data-net="${item.net_value}" ${checked ? 'checked' : ''} ${invalid ? 'disabled' : ''}>
            <div>
                <div class="pr-dist-head">
                    <div><h4>${esc(item.product_name)}</h4><div class="pr-dist-client">${esc(item.customer_name || 'Cliente não informado')}</div></div>
                    ${item.in_current_receipt ? '<span class="pr-badge blue">Neste comprovante</span>' : '<span class="pr-badge">Disponível</span>'}
                </div>
                <div class="pr-dist-values">
                    <div class="pr-dist-value"><span>Data</span><strong>${esc(item.delivery_date)}</strong></div>
                    <div class="pr-dist-value"><span>Quantidade</span><strong>${qty(item.quantity)} ${esc(item.unit)}</strong></div>
                    <div class="pr-dist-value"><span>Preço unitário</span><strong>${money(item.unit_price)}</strong></div>
                    <div class="pr-dist-value"><span>Valor líquido</span><strong>${money(item.net_value)}</strong></div>
                </div>
                ${item.notes ? `<button type="button" class="delivery-note-trigger"
                    data-delivery-notes="${esc(item.notes)}"
                    data-delivery-notes-title="Observações da entrega"
                    data-delivery-notes-meta="${esc(item.product_name + ' · ' + item.delivery_date)}">Observações</button>` : ''}
                ${reason ? `<div class="pr-dist-error">${esc(reason)}</div>` : ''}
            </div>
        </label>`;
    }

    async function openSelection(receiptId = null) {
        state.receiptId = receiptId ? Number(receiptId) : null;
        setModalView('loading');
        const params = new URLSearchParams({ approved_only:'1' });
        if (state.receiptId) params.set('receipt_id', String(state.receiptId));
        try {
            state.distributions = await json(`/${tenant}/delivery/projects/${project}/associates/${state.associateId}/deliveries?${params}`);
            $('pr-selection-title').textContent = state.receiptId ? 'Alterar comprovante' : 'Novo comprovante';
            $('pr-selection-help').textContent = state.receiptId
                ? 'Os itens marcados permanecerão no comprovante.'
                : 'Marque somente as distribuições que deseja incluir.';
            renderIssues('pr-selection-issues');
            $('pr-distributions').innerHTML = state.distributions.length
                ? state.distributions.map(distributionCard).join('')
                : '<div class="pr-empty">Não há distribuições disponíveis para este comprovante.</div>';
            $('pr-modal-primary').innerHTML = state.receiptId
                ? '<i class="ph ph-floppy-disk"></i> Salvar e gerar PDF'
                : '<i class="ph ph-file-arrow-down"></i> Gerar comprovante';
            $('pr-modal-primary').dataset.action = 'save';
            setModalView('selection');
            updateSelection();
            icons();
        } catch (error) {
            toast(error.message, 'error');
            state.check?.has_receipts ? renderReceipts() : closeModal();
        }
    }

    function selectedIds() {
        return [...document.querySelectorAll('.pr-dist-check:checked')].map(input => Number(input.value));
    }

    function updateSelection() {
        const selected = [...document.querySelectorAll('.pr-dist-check:checked')];
        const total = selected.reduce((sum,input) => sum + Number(input.dataset.net || 0), 0);
        $('pr-footer-value').textContent = `${selected.length} item(ns) · ${money(total)}`;
        const critical = Number(state.check?.critical_issues || 0);
        $('pr-modal-primary').disabled = !selected.length || state.busy || critical > 0;
        $('pr-modal-primary').title = critical > 0
            ? 'Corrija as inconsistências críticas antes de gerar o comprovante.'
            : '';
        const available = [...document.querySelectorAll('.pr-dist-check:not(:disabled)')];
        $('pr-toggle-all').innerHTML = available.length && available.every(input => input.checked)
            ? '<i class="ph ph-list-dashes"></i> Desmarcar'
            : '<i class="ph ph-list-checks"></i> Marcar disponíveis';
        icons();
    }

    function columns() {
        return [...document.querySelectorAll('.pr-column:checked')].map(input => input.value);
    }

    function tableScale() {
        return Number($('pr-table-scale')?.value || 100);
    }

    function applyPrintPreferences() {
        const preferences = state.check?.print_preferences || {};
        const selected = Array.isArray(preferences.columns) ? preferences.columns : ['delivery_date', 'unit_price', 'gross'];
        document.querySelectorAll('.pr-column').forEach(input => {
            input.checked = selected.includes(input.value);
        });
        const scale = [70, 80, 90, 100].includes(Number(preferences.table_scale))
            ? Number(preferences.table_scale)
            : 100;
        $('pr-table-scale').value = String(scale);
    }

    async function savePrintPreferences(showFeedback = true) {
        clearTimeout(state.preferenceTimer);
        const status = $('pr-print-status');
        status.className = 'pr-print-status';
        status.textContent = 'Salvando configuração do projeto...';

        const payload = {
            visible_columns:columns(),
            table_scale:tableScale(),
        };
        const previous = state.preferencePromise;
        const request = (previous ? previous.catch(() => null) : Promise.resolve())
            .then(() => json(root.dataset.preferencesUrl, {
                method:'PUT',
                headers:{ 'Content-Type':'application/json' },
                body:JSON.stringify(payload),
            }));
        state.preferencePromise = request;

        try {
            const data = await request;
            if (state.check) state.check.print_preferences = data.print_preferences;
            if (state.preferencePromise === request) {
                status.classList.add('saved');
                status.textContent = 'Configuração salva para todos os comprovantes deste projeto.';
            }
            if (showFeedback) toast(data.message);
            return data;
        } catch (error) {
            status.classList.add('error');
            status.textContent = error.message;
            throw error;
        } finally {
            if (state.preferencePromise === request) state.preferencePromise = null;
        }
    }

    function schedulePrintPreferenceSave() {
        clearTimeout(state.preferenceTimer);
        state.preferenceTimer = setTimeout(() => {
            savePrintPreferences(false).catch(error => toast(error.message, 'error'));
        }, 350);
    }

    function renderFeeColumns() {
        const options = state.check?.fee_columns || {};
        $('pr-fee-columns').innerHTML = Object.entries(options).map(([value, label]) =>
            `<label><input class="pr-column" type="checkbox" value="${esc(value)}"> ${esc(label)}</label>`
        ).join('');
        applyPrintPreferences();
    }

    async function downloadPdf(data) {
        window.SgcNavigation?.show('Abrindo documento', 'Preparando o visualizador');
        const bytes = atob(data.pdf);
        const array = new Uint8Array(bytes.length);
        for (let index = 0; index < bytes.length; index++) array[index] = bytes.charCodeAt(index);
        const pdf = new Blob([array], { type:'application/pdf' });
        const receiptTitle = data.document_title
            || `Comprovante Nº ${data.receipt_number || ''} · ${root.dataset.projectTitle || 'Projeto'}`;
        if (window.SgcDocuments) return window.SgcDocuments.openPdf(pdf, data.filename, receiptTitle, {
            relativePath: root.dataset.documentPath,
            documentTitle: receiptTitle,
        });
        const url = URL.createObjectURL(pdf); const link = document.createElement('a');
        link.href = url; link.download = data.filename; link.click(); URL.revokeObjectURL(url);
    }

    async function saveReceipt() {
        const ids = selectedIds();
        if (!ids.length || state.busy || Number(state.check?.critical_issues || 0) > 0) return;
        state.busy = true;
        updateSelection();
        $('pr-modal-primary').innerHTML = '<i class="ph ph-spinner-gap"></i> Processando...';
        try {
            await savePrintPreferences(false);
            const url = state.receiptId
                ? `/${tenant}/delivery/projects/${project}/receipts/${state.receiptId}/distributions`
                : `/${tenant}/delivery/projects/${project}/receipt-selected`;
            const data = await json(url, {
                method: state.receiptId ? 'PUT' : 'POST',
                headers:{ 'Content-Type':'application/json' },
                body:JSON.stringify({ delivery_ids:ids, visible_columns:columns(), table_scale:tableScale() }),
            });
            await downloadPdf(data);
            toast(data.message || `Comprovante ${data.receipt_number} gerado.`);
            await openModal(state.associateId, state.associateName);
            loadProducers();
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            state.busy = false;
            $('pr-modal-primary').innerHTML = state.receiptId
                ? '<i class="ph ph-floppy-disk"></i> Salvar e gerar PDF'
                : '<i class="ph ph-file-arrow-down"></i> Gerar comprovante';
            updateSelection();
        }
    }

    async function regenerate(receiptId, button) {
        if (state.busy) return;
        state.busy = true;
        button.disabled = true;
        try {
            await savePrintPreferences(false);
            const data = await json(`/${tenant}/delivery/projects/${project}/receipts/${receiptId}/regenerate`, {
                method:'POST',
                headers:{ 'Content-Type':'application/json' },
                body:JSON.stringify({ visible_columns:columns(), table_scale:tableScale() }),
            });
            await downloadPdf(data);
            toast(data.message);
            await openModal(state.associateId, state.associateName);
            loadProducers();
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            state.busy = false;
            button.disabled = false;
        }
    }

    function confirmAction(message) {
        return new Promise(resolve => {
            const dialog = $('pr-confirm');
            $('pr-confirm-message').textContent = message;
            dialog.showModal();
            const finish = value => {
                dialog.close();
                $('pr-confirm-ok').onclick = null;
                $('pr-confirm-cancel').onclick = null;
                resolve(value);
            };
            $('pr-confirm-ok').onclick = () => finish(true);
            $('pr-confirm-cancel').onclick = () => finish(false);
        });
    }

    async function handleIssue(button) {
        const action = button.dataset.issueAction;
        const deliveryId = Number(button.dataset.deliveryId || 0);
        const distributionId = Number(button.dataset.distributionId || 0);
        if (action === 'open_producers') {
            if (! $('pr-selection').hidden) {
                $('pr-distributions').scrollIntoView({ behavior:'smooth', block:'start' });
                toast('Selecione as distribuições pendentes abaixo.');
                return;
            }
            await openSelection();
            return;
        }
        if (action === 'open_distribution' || action === 'edit_distribution') {
            const params = new URLSearchParams({ open_delivery:String(deliveryId) });
            if (action === 'edit_distribution' && distributionId) params.set('edit_distribution', String(distributionId));
            window.location.href = `/${tenant}/delivery/projects/${project}/deliveries?${params}`;
            return;
        }
        const message = action === 'detach_missing_associate_receipt'
            ? 'Desvincular o comprovante inexistente? A distribuição voltará a ficar disponível.'
            : action === 'restore_parent_delivery'
                ? 'Restaurar a entrega-pai excluída? Quantidades, valores e comprovantes não serão alterados.'
                : 'Excluir esta distribuição órfã? Esta ação não pode ser desfeita.';
        if (!await confirmAction(message)) return;
        button.disabled = true;
        try {
            await json(`/${tenant}/delivery/projects/${project}/integrity/resolve`, {
                method:'POST',
                headers:{ 'Content-Type':'application/json' },
                body:JSON.stringify({ action, distribution_id:distributionId }),
            });
            toast('Correção aplicada.');
            await openModal(state.associateId, state.associateName);
            loadProducers();
        } catch (error) {
            toast(error.message, 'error');
            button.disabled = false;
        }
    }

    function closeModal() {
        if (state.busy) return;
        $('pr-modal').hidden = true;
        state.associateId = null;
        state.check = null;
        state.receiptId = null;
        state.distributions = [];
    }

    root.addEventListener('click', event => {
        const summary = event.target.closest('[data-summary-filter]');
        if (summary) {
            state.filter = summary.dataset.summaryFilter;
            $('pr-filter').value = state.filter;
            loadProducers(true);
            return;
        }
        const open = event.target.closest('[data-open-receipts]');
        if (open) openModal(open.dataset.openReceipts, open.dataset.associateName);
    });
    $('pr-search').addEventListener('input', () => {
        clearTimeout(state.timer);
        state.timer = setTimeout(() => loadProducers(true), 280);
    });
    $('pr-filter').addEventListener('change', event => {
        state.filter = event.target.value;
        loadProducers(true);
    });
    $('pr-prev').addEventListener('click', () => { state.page = Math.max(1, state.page - 1); loadProducers(); });
    $('pr-next').addEventListener('click', () => { state.page = Math.min(state.lastPage, state.page + 1); loadProducers(); });
    $('pr-modal-close').addEventListener('click', closeModal);
    $('pr-modal-back').addEventListener('click', () => {
        if (! $('pr-selection').hidden && state.check?.has_receipts) renderReceipts();
        else closeModal();
    });
    $('pr-modal-primary').addEventListener('click', () => {
        if ($('pr-modal-primary').dataset.action === 'new') openSelection();
        else saveReceipt();
    });
    $('pr-toggle-all').addEventListener('click', () => {
        const inputs = [...document.querySelectorAll('.pr-dist-check:not(:disabled)')];
        const mark = !inputs.every(input => input.checked);
        inputs.forEach(input => input.checked = mark);
        updateSelection();
    });
    $('pr-distributions').addEventListener('change', event => {
        if (event.target.classList.contains('pr-dist-check')) updateSelection();
    });
    $('pr-print-settings')?.addEventListener('change', event => {
        if (event.target.classList.contains('pr-column') || event.target.id === 'pr-table-scale') {
            schedulePrintPreferenceSave();
        }
    });
    $('pr-receipts').addEventListener('click', event => {
        const preview = event.target.closest('[data-preview-url]');
        if (preview) {
            if (window.SgcPlatform?.kind === 'web') {
                window.open(preview.dataset.previewUrl, '_blank', 'noopener,noreferrer');
                return;
            }
            preview.disabled = true;
            window.SgcNavigation?.show('Abrindo documento', 'Buscando o comprovante');
            savePrintPreferences(false)
                .then(async () => {
                    const documentPdf = await window.SgcDocuments.fetchPdf(preview.dataset.previewUrl, `Comprovante · ${root.dataset.projectTitle}`);
                    if (!documentPdf) throw new Error('Não foi possível abrir o comprovante.');
                    await window.SgcDocuments.openPdf(documentPdf.blob, documentPdf.fileName, documentPdf.title, {
                        relativePath: documentPdf.relativePath || root.dataset.documentPath,
                        origin: documentPdf.origin,
                        documentTitle: documentPdf.title,
                    });
                })
                .catch(error => { window.SgcNavigation?.hide(); toast(error.message, 'error'); })
                .finally(() => { preview.disabled = false; });
            return;
        }
        const edit = event.target.closest('[data-edit-receipt]');
        if (edit) { openSelection(edit.dataset.editReceipt); return; }
        const refresh = event.target.closest('[data-regenerate]');
        if (refresh) regenerate(Number(refresh.dataset.regenerate), refresh);
        const reprint = event.target.closest('[data-reprint-url]');
        if (reprint) {
            if (window.SgcPlatform?.kind === 'web') {
                window.open(reprint.dataset.reprintUrl, '_blank', 'noopener,noreferrer');
                return;
            }
            reprint.disabled = true;
            window.SgcNavigation?.show('Abrindo documento', 'Preparando a segunda via');
            savePrintPreferences(false)
                .then(async () => {
                    const documentPdf = await window.SgcDocuments.fetchPdf(reprint.dataset.reprintUrl, `Comprovante · ${root.dataset.projectTitle}`);
                    if (!documentPdf) throw new Error('Não foi possível abrir o comprovante.');
                    await window.SgcDocuments.openPdf(documentPdf.blob, documentPdf.fileName, documentPdf.title, {
                        relativePath: documentPdf.relativePath || root.dataset.documentPath,
                        origin: documentPdf.origin,
                        documentTitle: documentPdf.title,
                    });
                })
                .catch(error => { window.SgcNavigation?.hide(); toast(error.message, 'error'); })
                .finally(() => { reprint.disabled = false; });
        }
    });
    $('pr-issues-slot').addEventListener('click', event => {
        const action = event.target.closest('[data-issue-action]');
        if (action) handleIssue(action);
    });
    $('pr-selection-issues').addEventListener('click', event => {
        const action = event.target.closest('[data-issue-action]');
        if (action) handleIssue(action);
    });

    loadProducers(true);
    const auto = Number(new URLSearchParams(location.search).get('associate') || 0);
    if (auto) {
        openModal(auto, new URLSearchParams(location.search).get('name') || memberTerm);
        history.replaceState(null, '', location.pathname);
    }
})();
</script>
@endsection
