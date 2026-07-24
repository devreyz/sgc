@extends('layouts.bento')

@section('title', 'Associado no projeto')
@section('page-title', 'Associado no projeto')
@section('page-subtitle', $project->title)

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', request()->route('tenant'));
@endphp

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
@endphp

<style>
    .ap-shell {
        --ap-primary: var(--color-primary, #22c55e);
        --ap-primary-dark: var(--color-primary-dark, #16a34a);
        --ap-primary-deep: var(--color-primary-deep, #15803d);
        --ap-surface: var(--color-surface, #ffffff);
        --ap-soft: var(--color-surface-soft, #f8faf9);
        --ap-muted: var(--color-surface-muted, #f1f5f3);
        --ap-border: var(--color-border, #dfe7e2);
        --ap-border-strong: var(--color-border-strong, #cbd8d0);
        --ap-text: var(--color-text, #102018);
        --ap-secondary: var(--color-text-secondary, #52645a);
        --ap-faded: var(--color-text-muted, #839187);
        --ap-danger: var(--color-danger, #ef4444);
        --ap-warning: var(--color-warning, #f59e0b);
        --ap-info: var(--color-info, #0284c7);
        --ap-radius: 20px;
        --ap-radius-sm: 14px;
        --ap-shadow: 0 14px 38px rgba(15, 35, 24, .08);
        width: min(100%, 1320px);
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--ap-text);
    }

    .ap-modal,
    .ap-toast-root {
        --ap-primary: var(--color-primary, #22c55e);
        --ap-primary-dark: var(--color-primary-dark, #16a34a);
        --ap-primary-deep: var(--color-primary-deep, #15803d);
        --ap-surface: var(--color-surface, #ffffff);
        --ap-soft: var(--color-surface-soft, #f8faf9);
        --ap-muted: var(--color-surface-muted, #f1f5f3);
        --ap-border: var(--color-border, #dfe7e2);
        --ap-border-strong: var(--color-border-strong, #cbd8d0);
        --ap-text: var(--color-text, #102018);
        --ap-secondary: var(--color-text-secondary, #52645a);
        --ap-faded: var(--color-text-muted, #839187);
        --ap-danger: var(--color-danger, #ef4444);
        --ap-warning: var(--color-warning, #f59e0b);
        --ap-info: var(--color-info, #0284c7);
    }

    .ap-shell *,
    .ap-shell *::before,
    .ap-shell *::after {
        box-sizing: border-box;
    }

    .ap-hero {
        position: relative;
        display: grid;
        min-height: 230px;
        grid-template-columns: minmax(0, 1.4fr) minmax(290px, .6fr);
        gap: 1rem;
        margin-bottom: 1rem;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .24);
        border-radius: 30px;
        background:
            radial-gradient(circle at 86% 10%, rgba(255,255,255,.18), transparent 15rem),
            linear-gradient(135deg, var(--ap-primary) 0%, var(--ap-primary-dark) 54%, var(--ap-primary-deep) 100%);
        box-shadow: 0 24px 54px rgba(21, 128, 61, .19);
        color: #fff;
    }

    .ap-hero::before {
        position: absolute;
        inset: 0;
        background:
            linear-gradient(115deg, rgba(255,255,255,.10), transparent 40%),
            radial-gradient(circle at 6% 120%, rgba(255,255,255,.14), transparent 19rem);
        content: "";
        pointer-events: none;
    }

    .ap-hero-wave {
        position: absolute;
        right: 0;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 70px;
        color: rgba(255, 255, 255, .10);
        pointer-events: none;
    }

    .ap-hero-main,
    .ap-hero-aside {
        position: relative;
        z-index: 2;
    }

    .ap-hero-main {
        display: flex;
        min-width: 0;
        justify-content: center;
        flex-direction: column;
        padding: 1.4rem 1.5rem 3rem;
    }

    .ap-back {
        display: inline-flex;
        width: max-content;
        align-items: center;
        gap: .45rem;
        margin-bottom: .9rem;
        padding: .48rem .72rem;
        border: 1px solid rgba(255,255,255,.22);
        border-radius: 999px;
        background: rgba(255,255,255,.10);
        color: #fff;
        font-size: .72rem;
        font-weight: 760;
        text-decoration: none;
        backdrop-filter: blur(10px);
        transition: .15s ease;
    }

    .ap-back:hover {
        border-color: rgba(255,255,255,.38);
        background: rgba(255,255,255,.18);
        color: #fff;
        transform: translateY(-1px);
    }

    .ap-back svg {
        width: 16px;
        height: 16px;
    }

    .ap-hero-badges {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
        margin-bottom: .55rem;
    }

    .ap-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: .34rem;
        padding: .3rem .55rem;
        border-radius: 999px;
        background: rgba(255,255,255,.12);
        color: rgba(255,255,255,.88);
        font-size: .63rem;
        font-weight: 760;
    }

    .ap-hero-badge svg {
        width: 13px;
        height: 13px;
    }

    .ap-title {
        max-width: 760px;
        margin: 0;
        overflow-wrap: anywhere;
        font-size: clamp(1.55rem, 3vw, 2.45rem);
        font-weight: 870;
        letter-spacing: -.045em;
        line-height: 1.05;
    }

    .ap-subtitle {
        max-width: 800px;
        margin: .72rem 0 0;
        color: rgba(255,255,255,.76);
        font-size: .75rem;
        font-weight: 610;
        line-height: 1.55;
    }

    .ap-meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: .42rem .8rem;
        margin-top: .78rem;
        color: rgba(255,255,255,.78);
        font-size: .69rem;
        font-weight: 650;
    }

    .ap-meta-row span {
        display: inline-flex;
        align-items: center;
        gap: .34rem;
    }

    .ap-meta-row svg {
        width: 14px;
        height: 14px;
    }

    .ap-hero-aside {
        display: flex;
        justify-content: center;
        flex-direction: column;
        margin: .85rem;
        padding: 1rem;
        border: 1px solid rgba(255,255,255,.16);
        border-radius: 22px;
        background: rgba(255,255,255,.11);
        backdrop-filter: blur(16px);
    }

    .ap-aside-label {
        color: rgba(255,255,255,.68);
        font-size: .62rem;
        font-weight: 760;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .ap-hero-aside strong {
        display: block;
        margin-top: .35rem;
        font-size: 1rem;
        font-weight: 830;
    }

    .ap-hero-aside p {
        margin: .4rem 0 0;
        color: rgba(255,255,255,.70);
        font-size: .68rem;
        line-height: 1.5;
    }

    .ap-hero-actions {
        display: grid;
        gap: .5rem;
        margin-top: .85rem;
    }

    .ap-hero-btn {
        display: inline-flex;
        min-height: 43px;
        align-items: center;
        justify-content: center;
        gap: .42rem;
        padding: .6rem .78rem;
        border: 1px solid rgba(255,255,255,.20);
        border-radius: 13px;
        font-size: .71rem;
        font-weight: 810;
        text-decoration: none;
        transition: .15s ease;
    }

    .ap-hero-btn.primary {
        background: #fff;
        color: var(--ap-primary-dark);
        box-shadow: 0 10px 24px rgba(15,35,24,.13);
    }

    .ap-hero-btn.secondary {
        background: rgba(255,255,255,.12);
        color: #fff;
        backdrop-filter: blur(10px);
    }

    .ap-hero-btn:hover {
        transform: translateY(-1px);
    }

    .ap-hero-btn.primary:hover {
        color: var(--ap-primary-dark);
    }

    .ap-hero-btn.secondary:hover {
        color: #fff;
        background: rgba(255,255,255,.18);
    }

    .ap-hero-btn svg {
        width: 17px;
        height: 17px;
    }

    .ap-tabs-wrap {
        position: sticky;
        z-index: 25;
        top: calc(var(--app-header-height, 68px) + .35rem);
        margin-bottom: 1rem;
    }

    .ap-tabs {
        display: flex;
        align-items: center;
        gap: .35rem;
        padding: .38rem;
        overflow-x: auto;
        border: 1px solid rgba(223,231,226,.95);
        border-radius: 17px;
        background: rgba(255,255,255,.92);
        box-shadow: 0 8px 24px rgba(15,35,24,.06);
        backdrop-filter: blur(14px);
        scrollbar-width: none;
    }

    .ap-tabs::-webkit-scrollbar {
        display: none;
    }

    .ap-tab {
        display: inline-flex;
        min-height: 39px;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        gap: .38rem;
        padding: .5rem .68rem;
        border: 0;
        border-radius: 11px;
        background: transparent;
        color: var(--ap-secondary);
        cursor: pointer;
        font-size: .68rem;
        font-weight: 780;
        transition: .14s ease;
        white-space: nowrap;
    }

    .ap-tab:hover {
        background: var(--ap-muted);
        color: var(--ap-text);
    }

    .ap-tab.active {
        background: linear-gradient(135deg, var(--ap-primary), var(--ap-primary-dark));
        color: #fff;
        box-shadow: 0 8px 18px rgba(22,163,74,.16);
    }

    .ap-tab svg {
        width: 15px;
        height: 15px;
    }

    .ap-content {
        min-height: 320px;
    }

    .ap-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: .75rem;
    }

    .ap-card {
        position: relative;
        min-width: 0;
        grid-column: span 3;
        overflow: hidden;
        border: 1px solid rgba(223,231,226,.94);
        border-radius: var(--ap-radius);
        background: rgba(255,255,255,.95);
        box-shadow: 0 8px 26px rgba(15,35,24,.055);
        backdrop-filter: blur(10px);
    }

    .ap-card-inner {
        display: flex;
        align-items: flex-start;
        gap: .72rem;
        padding: 1rem;
    }

    .ap-card-icon {
        display: grid;
        width: 42px;
        height: 42px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 14px;
        background: var(--ap-muted);
        color: var(--ap-primary-dark);
    }

    .ap-card-icon.warning {
        background: #fffbeb;
        color: #b45309;
    }

    .ap-card-icon.info {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .ap-card-icon.danger {
        background: #fef2f2;
        color: #b91c1c;
    }

    .ap-card-icon svg {
        width: 19px;
        height: 19px;
    }

    .ap-card-copy {
        min-width: 0;
        flex: 1;
    }

    .ap-card-label {
        color: var(--ap-secondary);
        font-size: .64rem;
        font-weight: 720;
    }

    .ap-card-value {
        margin-top: .27rem;
        overflow: hidden;
        color: var(--ap-text);
        font-size: clamp(1rem, 2vw, 1.28rem);
        font-weight: 850;
        letter-spacing: -.03em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ap-card-helper {
        margin-top: .23rem;
        color: var(--ap-faded);
        font-size: .6rem;
        line-height: 1.45;
    }

    .ap-progress {
        height: 7px;
        margin-top: .52rem;
        overflow: hidden;
        border-radius: 999px;
        background: rgba(148,163,184,.18);
    }

    .ap-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #4ade80, var(--ap-primary-dark));
    }

    .ap-progress.warning > span {
        background: linear-gradient(90deg, #fbbf24, var(--ap-warning));
    }

    .ap-progress.danger > span {
        background: linear-gradient(90deg, #fb7185, var(--ap-danger));
    }

    .ap-section-card {
        overflow: hidden;
        border: 1px solid rgba(223,231,226,.94);
        border-radius: 22px;
        background: rgba(255,255,255,.95);
        box-shadow: var(--ap-shadow);
        backdrop-filter: blur(12px);
    }

    .ap-section-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .85rem .9rem;
        border-bottom: 1px solid var(--ap-border);
        background: linear-gradient(180deg, rgba(248,250,249,.97), rgba(255,255,255,.94));
    }

    .ap-section-head-copy {
        min-width: 0;
    }

    .ap-section-title {
        color: var(--ap-text);
        font-size: .82rem;
        font-weight: 830;
    }

    .ap-section-subtitle {
        margin-top: .12rem;
        color: var(--ap-faded);
        font-size: .6rem;
        line-height: 1.4;
    }

    .ap-toolbar {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
        padding: .75rem .85rem;
        border-bottom: 1px solid var(--ap-border);
        background: var(--ap-soft);
    }

    .ap-search-wrap {
        position: relative;
        min-width: 240px;
        flex: 1;
    }

    .ap-search-icon {
        position: absolute;
        top: 50%;
        left: .72rem;
        width: 16px;
        height: 16px;
        transform: translateY(-50%);
        color: var(--ap-faded);
        pointer-events: none;
    }

    .ap-input,
    .ap-select,
    .ap-field input,
    .ap-field select,
    .ap-field textarea {
        border: 1px solid var(--ap-border-strong);
        border-radius: 12px;
        background: var(--ap-surface);
        color: var(--ap-text);
        font: inherit;
        font-size: .74rem;
        font-weight: 600;
        transition: .15s ease;
    }

    .ap-input {
        width: 100%;
        min-height: 42px;
        padding: .55rem .72rem .55rem 2.25rem;
    }

    .ap-select {
        min-height: 42px;
        padding: .55rem .72rem;
    }

    .ap-input:focus,
    .ap-select:focus,
    .ap-field input:focus,
    .ap-field select:focus,
    .ap-field textarea:focus {
        border-color: var(--ap-primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(34,197,94,.13);
        background: #fff;
    }

    .ap-actions {
        display: flex;
        align-items: center;
        gap: .4rem;
        flex-wrap: wrap;
    }

    .ap-btn {
        display: inline-flex;
        min-height: 40px;
        align-items: center;
        justify-content: center;
        gap: .38rem;
        padding: .54rem .7rem;
        border: 1px solid var(--ap-border);
        border-radius: 12px;
        background: var(--ap-surface);
        color: var(--ap-text);
        cursor: pointer;
        font-size: .66rem;
        font-weight: 780;
        text-decoration: none;
        transition: .14s ease;
        white-space: nowrap;
    }

    .ap-btn:hover {
        border-color: rgba(34,197,94,.35);
        color: var(--ap-primary-dark);
        box-shadow: 0 6px 16px rgba(15,35,24,.07);
        transform: translateY(-1px);
    }

    .ap-btn.primary {
        border-color: var(--ap-primary-dark);
        background: linear-gradient(135deg, var(--ap-primary), var(--ap-primary-dark));
        color: #fff;
        box-shadow: 0 8px 18px rgba(22,163,74,.16);
    }

    .ap-btn.primary:hover {
        color: #fff;
    }

    .ap-btn.warning {
        border-color: rgba(245,158,11,.38);
        background: #fffbeb;
        color: #92400e;
    }

    .ap-btn.danger {
        border-color: rgba(239,68,68,.30);
        background: #fef2f2;
        color: #b91c1c;
    }

    .ap-btn:disabled {
        cursor: not-allowed;
        opacity: .45;
        transform: none;
    }

    .ap-table-wrap {
        width: 100%;
        overflow-x: auto;
        background: #fff;
    }

    .ap-table {
        width: 100%;
        min-width: 880px;
        border-collapse: collapse;
        font-size: .72rem;
    }

    .ap-table th,
    .ap-table td {
        padding: .72rem .76rem;
        border-bottom: 1px solid rgba(223,231,226,.74);
        text-align: left;
        vertical-align: middle;
        white-space: nowrap;
    }

    .ap-table th {
        background: #f8faf9;
        color: var(--ap-secondary);
        font-size: .59rem;
        font-weight: 820;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .ap-table tbody tr:hover {
        background: #fbfdfc;
    }

    .ap-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .ap-badge {
        display: inline-flex;
        min-height: 23px;
        align-items: center;
        gap: .28rem;
        padding: .2rem .5rem;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: .58rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .ap-badge.approved,
    .ap-badge.paid {
        background: #ecfdf5;
        color: #047857;
    }

    .ap-badge.pending,
    .ap-badge.pending_payment,
    .ap-badge.partially_paid {
        background: #fffbeb;
        color: #92400e;
    }

    .ap-badge.rejected,
    .ap-badge.obsolete,
    .ap-badge.cancelled {
        background: #fef2f2;
        color: #b91c1c;
    }

    .ap-badge svg {
        width: 12px;
        height: 12px;
    }

    .ap-mobile-list {
        display: none;
        gap: .65rem;
        padding: .7rem;
    }

    .ap-mobile-card {
        overflow: hidden;
        border: 1px solid var(--ap-border);
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 5px 18px rgba(15,35,24,.045);
    }

    .ap-mobile-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .7rem;
        padding: .75rem;
        border-bottom: 1px solid var(--ap-border);
        background: var(--ap-soft);
    }

    .ap-mobile-card-title {
        min-width: 0;
        flex: 1;
    }

    .ap-mobile-card-title strong {
        display: block;
        overflow: hidden;
        color: var(--ap-text);
        font-size: .75rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ap-mobile-card-title span {
        display: block;
        margin-top: .14rem;
        color: var(--ap-faded);
        font-size: .59rem;
    }

    .ap-mobile-card-body {
        display: grid;
        grid-template-columns: repeat(2, minmax(0,1fr));
        gap: .55rem;
        padding: .75rem;
    }

    .ap-mobile-metric {
        min-width: 0;
        padding: .55rem;
        border: 1px solid var(--ap-border);
        border-radius: 12px;
        background: var(--ap-soft);
    }

    .ap-mobile-metric span {
        display: block;
        color: var(--ap-faded);
        font-size: .56rem;
        font-weight: 700;
    }

    .ap-mobile-metric strong {
        display: block;
        margin-top: .18rem;
        overflow: hidden;
        color: var(--ap-text);
        font-size: .69rem;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ap-mobile-card-actions {
        display: flex;
        gap: .4rem;
        padding: 0 .75rem .75rem;
    }

    .ap-mobile-card-actions .ap-btn {
        flex: 1;
    }

    .ap-pager {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .75rem .85rem;
        border-top: 1px solid var(--ap-border);
        background: var(--ap-soft);
    }

    .ap-pager-info {
        color: var(--ap-faded);
        font-size: .62rem;
        font-weight: 650;
    }

    .ap-pager-actions {
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .ap-state {
        display: flex;
        min-height: 300px;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .7rem;
        padding: 2rem;
        color: var(--ap-secondary);
        text-align: center;
    }

    .ap-state-icon {
        display: grid;
        width: 58px;
        height: 58px;
        place-items: center;
        border-radius: 19px;
        background: var(--ap-muted);
        color: var(--ap-faded);
    }

    .ap-state-icon svg {
        width: 27px;
        height: 27px;
    }

    .ap-state strong {
        color: var(--ap-text);
        font-size: .82rem;
        font-weight: 820;
    }

    .ap-state p {
        max-width: 440px;
        margin: 0;
        color: var(--ap-secondary);
        font-size: .67rem;
        line-height: 1.55;
    }

    .ap-skeleton-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0,1fr));
        gap: .75rem;
    }

    .ap-skeleton {
        height: 118px;
        border-radius: var(--ap-radius);
        background:
            linear-gradient(90deg, #eef3f0 25%, #f8faf9 50%, #eef3f0 75%);
        background-size: 200% 100%;
        animation: ap-shimmer 1.2s infinite linear;
    }

    @keyframes ap-shimmer {
        to {
            background-position: -200% 0;
        }
    }

    .ap-modal {
        position: fixed;
        z-index: 1150;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15,23,42,.58);
        backdrop-filter: blur(7px);
    }

    .ap-modal.open {
        display: flex;
    }

    .ap-dialog {
        width: min(100%, 540px);
        max-height: min(92dvh, 760px);
        overflow-y: auto;
        border: 1px solid rgba(255,255,255,.72);
        border-radius: 8px;
        background: var(--ap-surface);
        box-shadow: 0 24px 64px rgba(15,23,42,.22);
        animation: ap-modal-in .2s cubic-bezier(.2,.8,.2,1);
    }

    @keyframes ap-modal-in {
        from {
            opacity: 0;
            transform: translateY(10px) scale(.985);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .ap-dialog-head {
        position: sticky;
        z-index: 2;
        top: 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .9rem 1rem;
        border-bottom: 1px solid var(--ap-border);
        background: var(--ap-surface);
        backdrop-filter: blur(14px);
    }

    .ap-dialog-head strong {
        color: var(--ap-text);
        font-size: .82rem;
        font-weight: 830;
    }

    .ap-dialog-close {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: var(--ap-muted);
        color: var(--ap-secondary);
        cursor: pointer;
    }

    .ap-dialog-close svg {
        width: 16px;
        height: 16px;
    }

    .ap-dialog-body {
        padding: 1rem;
    }

    .ap-field {
        display: grid;
        gap: .35rem;
        margin-bottom: .8rem;
    }

    .ap-field label {
        color: var(--ap-text);
        font-size: .66rem;
        font-weight: 760;
    }

    .ap-field small {
        color: var(--ap-faded);
        font-size: .59rem;
        line-height: 1.4;
    }

    .ap-field input,
    .ap-field select,
    .ap-field textarea {
        width: 100%;
        min-height: 42px;
        padding: .58rem .68rem;
    }

    .ap-field textarea {
        min-height: 90px;
        resize: vertical;
    }

    .ap-dialog-actions {
        position: sticky;
        bottom: 0;
        display: flex;
        justify-content: flex-end;
        gap: .5rem;
        padding: .8rem 1rem;
        border-top: 1px solid var(--ap-border);
        background: rgba(255,255,255,.96);
        backdrop-filter: blur(14px);
    }

    .ap-confirm-box {
        display: flex;
        align-items: flex-start;
        gap: .7rem;
        padding: .75rem;
        border: 1px solid rgba(245,158,11,.30);
        border-radius: 14px;
        background: #fffbeb;
        color: #92400e;
    }

    .ap-confirm-box svg {
        width: 19px;
        height: 19px;
        flex: 0 0 auto;
        margin-top: .05rem;
    }

    .ap-confirm-box p {
        margin: 0;
        font-size: .7rem;
        line-height: 1.55;
    }

    .ap-toast-root {
        position: fixed;
        z-index: 1250;
        top: 1rem;
        right: 1rem;
        display: flex;
        width: min(380px, calc(100vw - 2rem));
        flex-direction: column;
        gap: .5rem;
        pointer-events: none;
    }

    .ap-toast {
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .72rem .8rem;
        border: 1px solid var(--ap-border);
        border-radius: 14px;
        background: rgba(255,255,255,.97);
        box-shadow: 0 16px 36px rgba(15,35,24,.14);
        color: var(--ap-text);
        font-size: .69rem;
        font-weight: 720;
        pointer-events: auto;
        animation: ap-toast-in .18s ease both;
    }

    .ap-toast-icon {
        display: grid;
        width: 34px;
        height: 34px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
        background: #ecfdf5;
        color: var(--ap-primary-dark);
    }

    .ap-toast.error .ap-toast-icon {
        background: #fef2f2;
        color: var(--ap-danger);
    }

    .ap-toast-icon svg {
        width: 16px;
        height: 16px;
    }

    @keyframes ap-toast-in {
        from {
            opacity: 0;
            transform: translateY(-6px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 1100px) {
        .ap-card {
            grid-column: span 4;
        }
    }

    @media (max-width: 860px) {
        .ap-hero {
            min-height: 0;
            grid-template-columns: 1fr;
        }

        .ap-hero-main {
            padding-bottom: 2.35rem;
        }

        .ap-hero-aside {
            margin-top: 0;
        }

        .ap-card {
            grid-column: span 6;
        }

        .ap-table-wrap {
            display: none;
        }

        .ap-mobile-list {
            display: grid;
        }
    }

    @media (max-width: 640px) {
        .ap-hero {
            margin-right: -.1rem;
            margin-left: -.1rem;
            border-radius: 24px;
        }

        .ap-hero-main {
            padding: 1rem 1rem 2.15rem;
        }

        .ap-hero-aside {
            margin: 0 .7rem .7rem;
            padding: .85rem;
            border-radius: 18px;
        }

        .ap-title {
            font-size: 1.5rem;
        }

        .ap-tabs-wrap {
            top: calc(var(--app-header-height, 58px) + .25rem);
        }

        .ap-tabs {
            border-radius: 15px;
        }

        .ap-tab {
            min-height: 38px;
            padding: .48rem .62rem;
        }

        .ap-card {
            grid-column: span 12;
        }

        .ap-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .ap-search-wrap {
            width: 100%;
            min-width: 0;
        }

        .ap-select {
            width: 100%;
        }

        .ap-actions {
            display: grid;
            grid-template-columns: 1fr;
            width: 100%;
        }

        .ap-actions .ap-btn {
            width: 100%;
        }

        .ap-mobile-card-body {
            grid-template-columns: 1fr 1fr;
        }

        .ap-pager {
            align-items: stretch;
            flex-direction: column;
        }

        .ap-pager-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
        }

        .ap-pager-actions .ap-btn {
            width: 100%;
        }

        .ap-toast-root {
            top: auto;
            right: .7rem;
            bottom: calc(5rem + env(safe-area-inset-bottom));
            left: .7rem;
            width: auto;
        }

        .ap-modal {
            align-items: flex-end;
            padding: 0;
        }

        .ap-dialog {
            width: 100%;
            max-height: 94dvh;
            border-right: 0;
            border-bottom: 0;
            border-left: 0;
            border-radius: 22px 22px 0 0;
        }

        .ap-skeleton-grid {
            grid-template-columns: 1fr;
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

    let apSection = 'summary';
    let apPage = 1;
    let apAbort = null;
    let apProducts = [];
    let apLimitRows = {};
    let apLimitSummary = {};
    let apTimer = null;
    let apPendingConfirmation = null;

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
        const percent = Math.min(100, Number(data.financial_percent || 0));

        const participation = data.participation_status === 'active'
            ? 'Entregas permitidas'
            : data.participation_status === 'blocked'
                ? 'Entregas bloqueadas'
                : data.restrict_participants
                    ? 'Participação pendente'
                    : 'Projeto aberto';

        apRoot.innerHTML = `
            <div class="ap-grid">
                ${statCard(
                    'Participação',
                    esc(participation),
                    '',
                    data.participation_status === 'blocked' ? 'user-round-x' : 'user-round-check',
                    data.participation_status === 'blocked' ? 'danger' : ''
                )}

                <article class="ap-card">
                    <div class="ap-card-inner">
                        <div class="ap-card-icon">
                            <i data-lucide="wallet-cards"></i>
                        </div>

                        <div class="ap-card-copy">
                            <div class="ap-card-label">Limite financeiro</div>
                            <div class="ap-card-value">
                                ${data.financial_limit === null ? 'Sem limite' : money(data.financial_limit)}
                            </div>

                            <div class="ap-progress ${progressTone(percent)}">
                                <span style="width:${percent}%"></span>
                            </div>

                            <div class="ap-card-helper">
                                ${money(data.financial_consumed)} utilizado · ${Math.round(percent)}%
                            </div>
                        </div>
                    </div>
                </article>

                ${statCard(
                    'Saldo financeiro',
                    data.financial_remaining === null ? 'Livre' : money(data.financial_remaining),
                    'Valor ainda disponível para novas distribuições.',
                    'circle-dollar-sign'
                )}

                ${statCard(
                    'Quantidade recebida',
                    qty(data.received_quantity),
                    'Volume total registrado para o associado.',
                    'package-check'
                )}

                ${statCard(
                    'Quantidade distribuída',
                    qty(data.distributed_quantity),
                    'Volume com destino definido.',
                    'route',
                    'info'
                )}

                ${statCard(
                    'Saldo sem distribuição',
                    qty(data.undistributed_quantity),
                    'Quantidade que ainda aguarda destino.',
                    'package-open',
                    Number(data.undistributed_quantity || 0) > 0 ? 'warning' : ''
                )}

                ${statCard(
                    'A receber',
                    money(data.receivable),
                    'Saldo financeiro ainda pendente.',
                    'hand-coins',
                    Number(data.receivable || 0) > 0 ? 'warning' : ''
                )}

                ${statCard(
                    'Comprovantes',
                    String(data.receipt_count || 0),
                    `${data.obsolete_receipt_count || 0} comprovante(s) obsoleto(s).`,
                    'receipt-text'
                )}
            </div>
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
                <button class="ap-btn primary" type="button" onclick="openProductLimit()">
                    <i data-lucide="package-plus"></i>
                    Adicionar produto
                </button>
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
                        <button
                            class="ap-btn"
                            type="button"
                            onclick="openProductLimitById(${Number(item.id)})"
                            title="Editar limite"
                        >
                            <i data-lucide="pencil"></i>
                            Editar
                        </button>
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
                        <button class="ap-btn primary" type="button" onclick="openProductLimitById(${Number(item.id)})">
                            <i data-lucide="pencil"></i>
                            Editar limite
                        </button>
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
                    'Situação atual para novas entregas.',
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

    async function openProductLimit(current = null) {
        if (!apProducts.length) {
            const data = await api(`${AP_BASE}/data/products`);
            apProducts = data.data || [];
        }

        document.getElementById('limit-kind').value = 'product';
        document.getElementById('limit-title').textContent = current
            ? 'Editar limite do produto'
            : 'Adicionar produto permitido';

        document.getElementById('limit-value-label').textContent = 'Quantidade máxima';
        document.getElementById('limit-value').step = '0.001';
        document.getElementById('limit-value').value = current?.maximum_quantity ?? '';
        document.getElementById('limit-notes').value = current?.notes ?? '';
        document.getElementById('product-field').hidden = false;
        document.getElementById('limit-simulation').hidden = false;

        document.getElementById('limit-product').innerHTML = apProducts.map(item => `
            <option
                value="${item.id}"
                ${current && String(current.product_id) === String(item.id) ? 'selected' : ''}
            >
                ${esc(item.name)} · ${money(item.price)}/${esc(item.unit)}${item.available_for_associate === null ? '' : ' · disponível ' + qty(item.available_for_associate)}
            </option>
        `).join('');

        document.getElementById('limit-product').disabled = Boolean(current);
        updateProductLimitAvailability();
        openLimitModal();
    }

    function updateProductLimitAvailability() {
        const productId = document.getElementById('limit-product').value;
        const product = apProducts.find(item => String(item.id) === String(productId));
        const helper = document.getElementById('limit-availability');
        const input = document.getElementById('limit-value');
        const simulation = document.getElementById('limit-simulation');
        const simulatedValue = document.getElementById('limit-simulated-value');
        const simulatedTotal = document.getElementById('limit-simulated-total');

        if (product) {
            const current = Object.values(apLimitRows).find(
                item => String(item.product_id) === String(productId)
            );
            const quantity = Math.max(0, Number(input.value || 0));
            const value = quantity * Number(product.price || 0);
            const currentValue = Number(current?.estimated_maximum_value || 0);
            const total = Math.max(0, Number(apLimitSummary.simulated_limit_value || 0) - currentValue) + value;
            const ceiling = apLimitSummary.financial_limit === null
                ? null
                : Number(apLimitSummary.financial_limit || 0);
            simulation.hidden = false;
            simulatedValue.textContent = `${money(value)} neste produto`;
            simulatedTotal.textContent = ceiling === null
                ? `${money(total)} planejado no total`
                : `${money(total)} de ${money(ceiling)} planejado`;
            simulatedTotal.style.color = ceiling !== null && total > ceiling
                ? '#b91c1c'
                : '';
        }

        if (!product || product.project_maximum === null) {
            helper.textContent = 'Sem meta geral para este produto.';
            helper.hidden = false;
            input.removeAttribute('max');
            return;
        }

        helper.textContent = `Meta: ${qty(product.project_maximum)} ${product.unit || ''} · comprometido com outros: ${qty(product.allocated_to_others)} · disponível: ${qty(product.available_for_associate)}`;
        helper.hidden = false;
        input.max = String(product.available_for_associate);
    }

    function openProductLimitById(id) {
        openProductLimit(apLimitRows[String(id)] || null);
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

    document.getElementById('limit-product').addEventListener('change', updateProductLimitAvailability);
    document.getElementById('limit-value').addEventListener('input', updateProductLimitAvailability);

    document.getElementById('limit-modal').addEventListener('click', event => {
        if (event.target.id === 'limit-modal') {
            closeLimitModal();
        }
    });

    document.getElementById('confirm-modal').addEventListener('click', event => {
        if (event.target.id === 'confirm-modal') {
            closeConfirmModal();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeLimitModal();
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
