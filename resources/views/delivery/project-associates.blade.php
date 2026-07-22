@extends('layouts.bento')

@section('title', 'Associados do projeto')
@section('page-title', 'Participação e limites')
@section('page-subtitle', $project->title)

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', request()->route('tenant'));
@endphp

@section('content')
@php
    $tenantSlug = request()->route('tenant') instanceof \App\Models\Tenant
        ? request()->route('tenant')->slug
        : request()->route('tenant');

    $projectPeriod = collect([
        $project->start_date?->format('d/m/Y'),
        $project->end_date?->format('d/m/Y'),
    ])->filter()->implode(' a ');
@endphp

<style>
    .pam-shell {
        --pam-green: var(--color-primary, #22c55e);
        --pam-green-dark: var(--color-primary-dark, #16a34a);
        --pam-green-deep: var(--color-primary-deep, #15803d);
        --pam-surface: var(--color-surface, #fff);
        --pam-soft: var(--color-surface-soft, #f8faf9);
        --pam-muted: var(--color-surface-muted, #f1f5f3);
        --pam-border: var(--color-border, #dfe7e2);
        --pam-border-strong: var(--color-border-strong, #cbd8d0);
        --pam-text: var(--color-text, #102018);
        --pam-secondary: var(--color-text-secondary, #52645a);
        --pam-faded: var(--color-text-muted, #839187);
        --pam-danger: var(--color-danger, #ef4444);
        --pam-warning: var(--color-warning, #f59e0b);
        --pam-info: var(--color-info, #0284c7);
        --pam-radius: 20px;
        --pam-radius-sm: 14px;
        --pam-shadow: 0 12px 34px rgba(15, 35, 24, .07);
        width: min(100%, 1320px);
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--pam-text);
    }

    .pam-shell *,
    .pam-shell *::before,
    .pam-shell *::after {
        box-sizing: border-box;
    }

    .pam-hero {
        position: relative;
        display: grid;
        min-height: 210px;
        grid-template-columns: minmax(0, 1.35fr) minmax(290px, .65fr);
        gap: 1rem;
        margin-bottom: 1rem;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .26);
        border-radius: 28px;
        background:
            radial-gradient(circle at 82% 5%, rgba(255, 255, 255, .17), transparent 14rem),
            linear-gradient(135deg, var(--pam-green) 0%, var(--pam-green-dark) 55%, var(--pam-green-deep) 100%);
        box-shadow: 0 22px 50px rgba(21, 128, 61, .18);
        color: #fff;
    }

    .pam-hero::before {
        position: absolute;
        inset: 0;
        background:
            linear-gradient(115deg, rgba(255, 255, 255, .08), transparent 40%),
            radial-gradient(circle at 5% 120%, rgba(255, 255, 255, .13), transparent 18rem);
        content: "";
        pointer-events: none;
    }

    .pam-hero-wave {
        position: absolute;
        right: 0;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 64px;
        color: rgba(255, 255, 255, .10);
        pointer-events: none;
    }

    .pam-hero-main,
    .pam-hero-aside {
        position: relative;
        z-index: 2;
    }

    .pam-hero-main {
        display: flex;
        min-width: 0;
        justify-content: center;
        flex-direction: column;
        padding: 1.35rem 1.5rem 2.75rem;
    }

    .pam-back {
        display: inline-flex;
        width: max-content;
        align-items: center;
        gap: .45rem;
        margin-bottom: .85rem;
        padding: .48rem .72rem;
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 999px;
        background: rgba(255, 255, 255, .10);
        color: #fff;
        font-size: .73rem;
        font-weight: 760;
        text-decoration: none;
        backdrop-filter: blur(10px);
        transition: background 150ms ease, border-color 150ms ease, transform 150ms ease;
    }

    .pam-back:hover {
        border-color: rgba(255, 255, 255, .38);
        background: rgba(255, 255, 255, .18);
        transform: translateY(-1px);
    }

    .pam-back svg {
        width: 16px;
        height: 16px;
    }

    .pam-eyebrow {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .42rem;
        margin-bottom: .48rem;
    }

    .pam-eyebrow span {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .31rem .56rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, .12);
        color: rgba(255, 255, 255, .88);
        font-size: .64rem;
        font-weight: 760;
    }

    .pam-eyebrow svg {
        width: 13px;
        height: 13px;
    }

    .pam-title {
        max-width: 760px;
        margin: 0;
        overflow-wrap: anywhere;
        font-size: clamp(1.45rem, 3vw, 2.35rem);
        font-weight: 860;
        letter-spacing: -.045em;
        line-height: 1.06;
    }

    .pam-subtitle {
        max-width: 760px;
        margin: .7rem 0 0;
        color: rgba(255, 255, 255, .76);
        font-size: .76rem;
        font-weight: 610;
        line-height: 1.55;
    }

    .pam-project-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .42rem .8rem;
        margin-top: .75rem;
        color: rgba(255, 255, 255, .78);
        font-size: .7rem;
        font-weight: 650;
    }

    .pam-project-meta span {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
    }

    .pam-project-meta svg {
        width: 14px;
        height: 14px;
    }

    .pam-hero-aside {
        display: flex;
        justify-content: center;
        flex-direction: column;
        margin: .85rem;
        padding: 1rem;
        border: 1px solid rgba(255, 255, 255, .16);
        border-radius: 22px;
        background: rgba(255, 255, 255, .11);
        backdrop-filter: blur(16px);
    }

    .pam-aside-label {
        color: rgba(255, 255, 255, .67);
        font-size: .63rem;
        font-weight: 760;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .pam-hero-aside strong {
        display: block;
        margin-top: .35rem;
        overflow: hidden;
        font-size: 1.02rem;
        font-weight: 830;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pam-hero-aside p {
        margin: .42rem 0 0;
        color: rgba(255, 255, 255, .70);
        font-size: .69rem;
        line-height: 1.5;
    }

    .pam-primary-action {
        display: inline-flex;
        min-height: 43px;
        align-items: center;
        justify-content: center;
        gap: .43rem;
        margin-top: .85rem;
        padding: .6rem .78rem;
        border: 1px solid rgba(255, 255, 255, .20);
        border-radius: 13px;
        background: #fff;
        color: var(--pam-green-dark);
        font-size: .72rem;
        font-weight: 810;
        text-decoration: none;
        box-shadow: 0 10px 24px rgba(15, 35, 24, .13);
        transition: transform 150ms ease, box-shadow 150ms ease;
    }

    .pam-primary-action:hover {
        color: var(--pam-green-dark);
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(15, 35, 24, .17);
    }

    .pam-primary-action svg {
        width: 17px;
        height: 17px;
    }

    .pam-summary {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: .78rem;
        margin-bottom: 1rem;
    }

    .pam-summary-card {
        position: relative;
        min-width: 0;
        grid-column: span 4;
        overflow: hidden;
        border: 1px solid rgba(223, 231, 226, .94);
        border-radius: var(--pam-radius);
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 8px 26px rgba(15, 35, 24, .055);
        backdrop-filter: blur(10px);
    }

    .pam-summary-card-inner {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding: 1rem;
    }

    .pam-summary-icon {
        display: grid;
        width: 43px;
        height: 43px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 14px;
        background: var(--pam-muted);
        color: var(--pam-green-dark);
    }

    .pam-summary-icon svg {
        width: 20px;
        height: 20px;
    }

    .pam-summary-copy {
        min-width: 0;
        flex: 1;
    }

    .pam-summary-label {
        color: var(--pam-secondary);
        font-size: .65rem;
        font-weight: 720;
    }

    .pam-summary-value {
        margin-top: .28rem;
        overflow: hidden;
        color: var(--pam-text);
        font-size: clamp(1rem, 2vw, 1.35rem);
        font-weight: 850;
        letter-spacing: -.03em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pam-summary-helper {
        margin-top: .22rem;
        color: var(--pam-faded);
        font-size: .61rem;
        line-height: 1.45;
    }

    .pam-workspace {
        overflow: hidden;
        border: 1px solid rgba(223, 231, 226, .94);
        border-radius: 22px;
        background: rgba(255, 255, 255, .94);
        box-shadow: var(--pam-shadow);
        backdrop-filter: blur(12px);
    }

    .pam-toolbar {
        display: flex;
        align-items: center;
        gap: .55rem;
        padding: .8rem;
        border-bottom: 1px solid var(--pam-border);
        background: linear-gradient(180deg, rgba(248, 250, 249, .97), rgba(255, 255, 255, .94));
    }

    .pam-search-wrap {
        position: relative;
        min-width: 0;
        flex: 1;
    }

    .pam-search-icon {
        position: absolute;
        top: 50%;
        left: .75rem;
        width: 16px;
        height: 16px;
        transform: translateY(-50%);
        color: var(--pam-faded);
        pointer-events: none;
    }

    .pam-input,
    .pam-select {
        min-height: 42px;
        border: 1px solid var(--pam-border-strong);
        border-radius: 12px;
        background: var(--pam-surface);
        color: var(--pam-text);
        font: inherit;
        font-size: .75rem;
        font-weight: 600;
        transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
    }

    .pam-input {
        width: 100%;
        padding: .55rem 2.4rem .55rem 2.35rem;
    }

    .pam-select {
        min-width: 210px;
        padding: .55rem .72rem;
    }

    .pam-input:focus,
    .pam-select:focus {
        border-color: var(--pam-green);
        outline: none;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .13);
        background: #fff;
    }

    .pam-clear-search {
        position: absolute;
        top: 50%;
        right: .48rem;
        display: none;
        width: 29px;
        height: 29px;
        place-items: center;
        border: 0;
        border-radius: 9px;
        background: transparent;
        color: var(--pam-faded);
        cursor: pointer;
        transform: translateY(-50%);
    }

    .pam-clear-search.visible {
        display: grid;
    }

    .pam-clear-search:hover {
        background: var(--pam-muted);
        color: var(--pam-text);
    }

    .pam-clear-search svg {
        width: 15px;
        height: 15px;
    }

    .pam-toolbar-meta {
        display: flex;
        align-items: center;
        gap: .35rem;
        color: var(--pam-faded);
        font-size: .63rem;
        font-weight: 680;
        white-space: nowrap;
    }

    .pam-list {
        display: grid;
        gap: .65rem;
        padding: .8rem;
    }

    .pam-item {
        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(210px, 1.45fr) minmax(210px, 1fr) minmax(155px, .72fr) auto;
        gap: .85rem;
        align-items: center;
        padding: .9rem;
        overflow: hidden;
        border: 1px solid var(--pam-border);
        border-radius: 17px;
        background: var(--pam-surface);
        box-shadow: 0 4px 16px rgba(15, 35, 24, .035);
        transition: transform 150ms ease, box-shadow 150ms ease, border-color 150ms ease;
    }

    .pam-item:hover {
        border-color: rgba(34, 197, 94, .34);
        box-shadow: 0 10px 25px rgba(15, 35, 24, .075);
        transform: translateY(-1px);
    }

    .pam-item::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--pam-border);
        content: "";
    }

    .pam-item.is-active::after {
        background: linear-gradient(90deg, var(--pam-green), var(--pam-green-dark));
    }

    .pam-item.is-blocked::after {
        background: linear-gradient(90deg, #fb7185, var(--pam-danger));
    }

    .pam-person {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .72rem;
    }

    .pam-avatar {
        display: grid;
        width: 46px;
        height: 46px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 15px;
        background: linear-gradient(145deg, #f0fdf4, #dcfce7);
        color: var(--pam-green-dark);
        font-size: .82rem;
        font-weight: 860;
        box-shadow: inset 0 0 0 1px rgba(34, 197, 94, .11);
    }

    .pam-person-copy {
        min-width: 0;
        flex: 1;
    }

    .pam-name {
        overflow: hidden;
        color: var(--pam-text);
        font-size: .82rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pam-meta {
        margin-top: .16rem;
        overflow: hidden;
        color: var(--pam-faded);
        font-size: .62rem;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pam-person-status {
        margin-top: .38rem;
    }

    .pam-badge {
        display: inline-flex;
        min-height: 23px;
        align-items: center;
        gap: .3rem;
        padding: .2rem .5rem;
        border-radius: 999px;
        font-size: .59rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .pam-badge svg {
        width: 12px;
        height: 12px;
    }

    .pam-badge.active {
        background: #ecfdf5;
        color: #047857;
    }

    .pam-badge.blocked {
        background: #fef2f2;
        color: #b91c1c;
    }

    .pam-badge.unconfigured {
        background: #f1f5f9;
        color: #475569;
    }

    .pam-financial {
        min-width: 0;
        padding: .65rem;
        border: 1px solid var(--pam-border);
        border-radius: 14px;
        background: var(--pam-soft);
    }

    .pam-metric-label {
        color: var(--pam-secondary);
        font-size: .6rem;
        font-weight: 720;
    }

    .pam-metric-value {
        margin-top: .23rem;
        overflow: hidden;
        color: var(--pam-text);
        font-size: .82rem;
        font-weight: 840;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pam-metric-helper {
        margin-top: .18rem;
        overflow: hidden;
        color: var(--pam-faded);
        font-size: .58rem;
        font-weight: 620;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pam-progress {
        height: 7px;
        margin-top: .48rem;
        overflow: hidden;
        border-radius: 999px;
        background: rgba(148, 163, 184, .18);
    }

    .pam-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #4ade80, var(--pam-green-dark));
        transition: width 280ms ease;
    }

    .pam-progress.is-warning > span {
        background: linear-gradient(90deg, #fbbf24, var(--pam-warning));
    }

    .pam-progress.is-danger > span {
        background: linear-gradient(90deg, #fb7185, var(--pam-danger));
    }

    .pam-products {
        min-width: 0;
        padding: .65rem;
        border: 1px solid var(--pam-border);
        border-radius: 14px;
        background: var(--pam-soft);
    }

    .pam-products-value {
        display: flex;
        align-items: center;
        gap: .55rem;
    }

    .pam-products-icon {
        display: grid;
        width: 34px;
        height: 34px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
        background: #ecfdf5;
        color: var(--pam-green-dark);
    }

    .pam-products-icon svg {
        width: 16px;
        height: 16px;
    }

    .pam-products strong {
        display: block;
        color: var(--pam-text);
        font-size: .9rem;
        font-weight: 850;
    }

    .pam-products span {
        display: block;
        margin-top: .1rem;
        color: var(--pam-faded);
        font-size: .58rem;
        font-weight: 620;
    }

    .pam-row-actions {
        display: flex;
        min-width: 154px;
        align-items: stretch;
        justify-content: flex-end;
        gap: .4rem;
    }

    .pam-btn {
        display: inline-flex;
        min-height: 40px;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        padding: .54rem .7rem;
        border: 1px solid var(--pam-border);
        border-radius: 12px;
        background: var(--pam-surface);
        color: var(--pam-text);
        cursor: pointer;
        font-size: .66rem;
        font-weight: 780;
        text-decoration: none;
        transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease, background 140ms ease;
        white-space: nowrap;
    }

    .pam-btn:hover {
        border-color: rgba(34, 197, 94, .35);
        color: var(--pam-green-dark);
        box-shadow: 0 6px 16px rgba(15, 35, 24, .07);
        transform: translateY(-1px);
    }

    .pam-btn.primary {
        border-color: var(--pam-green-dark);
        background: linear-gradient(135deg, var(--pam-green), var(--pam-green-dark));
        color: #fff;
        box-shadow: 0 8px 18px rgba(22, 163, 74, .16);
    }

    .pam-btn.primary:hover {
        color: #fff;
    }

    .pam-btn.warning {
        border-color: rgba(245, 158, 11, .38);
        background: #fffbeb;
        color: #92400e;
    }

    .pam-btn:disabled {
        cursor: not-allowed;
        opacity: .45;
        transform: none;
    }

    .pam-pager {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .75rem .8rem;
        border-top: 1px solid var(--pam-border);
        background: var(--pam-soft);
    }

    .pam-pager-info {
        color: var(--pam-faded);
        font-size: .63rem;
        font-weight: 650;
    }

    .pam-pager-actions {
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .pam-state {
        display: flex;
        min-height: 300px;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .7rem;
        padding: 2rem;
        color: var(--pam-secondary);
        text-align: center;
    }

    .pam-state-icon {
        display: grid;
        width: 58px;
        height: 58px;
        place-items: center;
        border-radius: 19px;
        background: var(--pam-muted);
        color: var(--pam-faded);
    }

    .pam-state-icon svg {
        width: 27px;
        height: 27px;
    }

    .pam-state strong {
        color: var(--pam-text);
        font-size: .83rem;
        font-weight: 820;
    }

    .pam-state p {
        max-width: 420px;
        margin: 0;
        color: var(--pam-secondary);
        font-size: .68rem;
        line-height: 1.55;
    }

    .pam-skeleton-list {
        display: grid;
        gap: .65rem;
        padding: .8rem;
    }

    .pam-skeleton {
        height: 104px;
        border-radius: 17px;
        background:
            linear-gradient(90deg, #eef3f0 25%, #f8faf9 50%, #eef3f0 75%);
        background-size: 200% 100%;
        animation: pam-shimmer 1.2s infinite linear;
    }

    @keyframes pam-shimmer {
        to {
            background-position: -200% 0;
        }
    }

    .pam-toast-root {
        position: fixed;
        z-index: 1200;
        top: 1rem;
        right: 1rem;
        display: flex;
        width: min(380px, calc(100vw - 2rem));
        flex-direction: column;
        gap: .5rem;
        pointer-events: none;
    }

    .pam-toast {
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .72rem .8rem;
        border: 1px solid var(--pam-border);
        border-radius: 14px;
        background: rgba(255, 255, 255, .97);
        box-shadow: 0 16px 36px rgba(15, 35, 24, .14);
        color: var(--pam-text);
        font-size: .69rem;
        font-weight: 720;
        pointer-events: auto;
        animation: pam-toast-in .18s ease both;
    }

    .pam-toast-icon {
        display: grid;
        width: 34px;
        height: 34px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
        background: #ecfdf5;
        color: var(--pam-green-dark);
    }

    .pam-toast.error .pam-toast-icon {
        background: #fef2f2;
        color: var(--pam-danger);
    }

    .pam-toast-icon svg {
        width: 16px;
        height: 16px;
    }

    @keyframes pam-toast-in {
        from {
            opacity: 0;
            transform: translateY(-6px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .pam-modal {
        position: fixed;
        z-index: 1150;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, .58);
        backdrop-filter: blur(7px);
    }

    .pam-modal.active {
        display: flex;
    }

    .pam-modal-card {
        width: min(100%, 440px);
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .72);
        border-radius: 22px;
        background: var(--pam-surface);
        box-shadow: 0 24px 64px rgba(15, 23, 42, .22);
        animation: pam-modal-in .2s cubic-bezier(.2, .8, .2, 1);
    }

    @keyframes pam-modal-in {
        from {
            opacity: 0;
            transform: translateY(10px) scale(.985);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .pam-modal-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .9rem 1rem;
        border-bottom: 1px solid var(--pam-border);
    }

    .pam-modal-head strong {
        color: var(--pam-text);
        font-size: .82rem;
        font-weight: 830;
    }

    .pam-modal-close {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 0;
        border-radius: 11px;
        background: var(--pam-muted);
        color: var(--pam-secondary);
        cursor: pointer;
    }

    .pam-modal-close svg {
        width: 16px;
        height: 16px;
    }

    .pam-modal-body {
        padding: 1rem;
    }

    .pam-modal-warning {
        display: flex;
        align-items: flex-start;
        gap: .7rem;
        padding: .75rem;
        border: 1px solid rgba(245, 158, 11, .30);
        border-radius: 14px;
        background: #fffbeb;
        color: #92400e;
    }

    .pam-modal-warning svg {
        width: 19px;
        height: 19px;
        flex: 0 0 auto;
        margin-top: .05rem;
    }

    .pam-modal-warning p {
        margin: 0;
        font-size: .7rem;
        line-height: 1.55;
    }

    .pam-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: .5rem;
        padding: .8rem 1rem;
        border-top: 1px solid var(--pam-border);
        background: var(--pam-soft);
    }

    @media (max-width: 1050px) {
        .pam-item {
            grid-template-columns: minmax(200px, 1.3fr) minmax(195px, 1fr) auto;
        }

        .pam-products {
            display: none;
        }
    }

    @media (max-width: 820px) {
        .pam-hero {
            min-height: 0;
            grid-template-columns: 1fr;
        }

        .pam-hero-main {
            padding-bottom: 2.3rem;
        }

        .pam-hero-aside {
            margin-top: 0;
        }

        .pam-summary-card {
            grid-column: span 6;
        }

        .pam-summary-card:last-child {
            grid-column: span 12;
        }

        .pam-item {
            grid-template-columns: minmax(0, 1fr) minmax(190px, .9fr);
        }

        .pam-products {
            display: block;
        }

        .pam-row-actions {
            grid-column: 1 / -1;
            justify-content: stretch;
        }

        .pam-row-actions .pam-btn {
            flex: 1;
        }
    }

    @media (max-width: 620px) {
        .pam-hero {
            margin-right: -.1rem;
            margin-left: -.1rem;
            border-radius: 24px;
        }

        .pam-hero-main {
            padding: 1rem 1rem 2.1rem;
        }

        .pam-hero-aside {
            margin: 0 .7rem .7rem;
            padding: .85rem;
            border-radius: 18px;
        }

        .pam-title {
            font-size: 1.45rem;
        }

        .pam-summary {
            gap: .65rem;
        }

        .pam-summary-card,
        .pam-summary-card:last-child {
            grid-column: span 12;
        }

        .pam-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .pam-select {
            width: 100%;
            min-width: 0;
        }

        .pam-toolbar-meta {
            justify-content: center;
        }

        .pam-list {
            padding: .65rem;
        }

        .pam-item {
            grid-template-columns: 1fr;
            gap: .65rem;
            padding: .75rem;
        }

        .pam-financial,
        .pam-products {
            padding: .6rem;
        }

        .pam-row-actions {
            grid-column: auto;
        }

        .pam-pager {
            align-items: stretch;
            flex-direction: column;
        }

        .pam-pager-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
        }

        .pam-pager-actions .pam-btn {
            width: 100%;
        }

        .pam-toast-root {
            top: auto;
            right: .7rem;
            bottom: calc(5rem + env(safe-area-inset-bottom));
            left: .7rem;
            width: auto;
        }

        .pam-modal {
            align-items: flex-end;
            padding: 0;
        }

        .pam-modal-card {
            width: 100%;
            border-right: 0;
            border-bottom: 0;
            border-left: 0;
            border-radius: 22px 22px 0 0;
        }
    }
</style>

<main class="pam-shell" id="project-associates-manager">
    <section class="pam-hero">
        <svg class="pam-hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
            <path fill="currentColor" d="M0,64L60,69.3C120,75,240,85,360,80C480,75,600,53,720,53.3C840,53,960,75,1080,80C1200,85,1320,75,1380,69.3L1440,64L1440,120L1380,120C1320,120,1200,120,1080,120C960,120,840,120,720,120C600,120,480,120,360,120C240,120,120,120,60,120L0,120Z"></path>
        </svg>

        <div class="pam-hero-main">
            <a
                class="pam-back"
                href="{{ route('delivery.projects.producers', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            >
                <i data-lucide="arrow-left"></i>
                Voltar ao projeto
            </a>

            <h1 class="pam-title">Participação e limites</h1>

            <div class="pam-project-meta">
                <span>
                    <i data-lucide="folder-kanban"></i>
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

        <aside class="pam-hero-aside">
            <a
                class="pam-primary-action"
                href="{{ route('delivery.register', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            >
                <i data-lucide="package-plus"></i>
                Registrar entrega
            </a>
        </aside>
    </section>

    <section class="pam-summary" aria-label="Resumo da configuração do projeto">
        <article class="pam-summary-card">
            <div class="pam-summary-card-inner">
                <div class="pam-summary-icon">
                    <i data-lucide="users-round"></i>
                </div>
                <div class="pam-summary-copy">
                    <div class="pam-summary-label">Associados</div>
                    <div class="pam-summary-value" id="pam-total">—</div>
                </div>
            </div>
        </article>

        <article class="pam-summary-card">
            <div class="pam-summary-card-inner">
                <div class="pam-summary-icon">
                    <i data-lucide="{{ $project->restrict_participants ? 'user-round-check' : 'users-round' }}"></i>
                </div>
                <div class="pam-summary-copy">
                    <div class="pam-summary-label">Participação</div>
                    <div class="pam-summary-value">
                        {{ $project->restrict_participants ? 'Somente participantes' : 'Participação aberta' }}
                    </div>
                </div>
            </div>
        </article>

        <article class="pam-summary-card">
            <div class="pam-summary-card-inner">
                <div class="pam-summary-icon">
                    <i data-lucide="{{ $project->allow_any_product ? 'package-open' : 'package-check' }}"></i>
                </div>
                <div class="pam-summary-copy">
                    <div class="pam-summary-label">Produtos</div>
                    <div class="pam-summary-value">
                        {{ $project->allow_any_product ? 'Catálogo livre' : 'Conforme limites' }}
                    </div>
                </div>
            </div>
        </article>
    </section>

    <section class="pam-workspace">
        <div class="pam-toolbar">
            <div class="pam-search-wrap">
                <i class="pam-search-icon" data-lucide="search"></i>

                <input
                    class="pam-input"
                    id="pam-search"
                    type="search"
                    autocomplete="off"
                    placeholder="Buscar associado, matrícula ou localidade"
                    aria-label="Buscar associado"
                >

                <button
                    class="pam-clear-search"
                    id="pam-clear-search"
                    type="button"
                    aria-label="Limpar busca"
                >
                    <i data-lucide="x"></i>
                </button>
            </div>

            <select class="pam-select" id="pam-status" aria-label="Filtrar participação">
                <option value="" @selected(! $project->restrict_participants)>Todas as participações</option>
                <option value="active" @selected($project->restrict_participants)>Participantes ativos</option>
                <option value="blocked">Bloqueados</option>
                <option value="unconfigured">Ainda não configurados</option>
            </select>

        </div>

        <div class="pam-skeleton-list" id="pam-skeleton">
            @for($index = 0; $index < 5; $index++)
                <div class="pam-skeleton"></div>
            @endfor
        </div>

        <section class="pam-list" id="pam-list" aria-live="polite" hidden></section>
        <div class="pam-pager" id="pam-pager" hidden></div>
    </section>
</main>

<div class="pam-toast-root" id="pam-toast-root" aria-live="polite"></div>

<div class="pam-modal" id="pam-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="pam-confirm-title">
    <div class="pam-modal-card">
        <div class="pam-modal-head">
            <strong id="pam-confirm-title">Confirmar alteração</strong>
            <button class="pam-modal-close" type="button" id="pam-confirm-close" aria-label="Fechar">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="pam-modal-body">
            <div class="pam-modal-warning">
                <i data-lucide="triangle-alert"></i>
                <p id="pam-confirm-message"></p>
            </div>
        </div>

        <div class="pam-modal-actions">
            <button class="pam-btn" type="button" id="pam-confirm-cancel">Voltar</button>
            <button class="pam-btn primary" type="button" id="pam-confirm-action">Confirmar</button>
        </div>
    </div>
</div>

<script>
    const PAM_BASE = @json(url('/'.$tenantSlug.'/delivery/projects/'.$project->id));
    const PAM_CSRF = @json(csrf_token());
    const PAM_CAN_MANAGE = @json($canManage);

    let pamPage = 1;
    let pamAbort = null;
    let pamTimer = null;
    let pamPendingConfirmation = null;

    const pamElements = {
        search: document.getElementById('pam-search'),
        clearSearch: document.getElementById('pam-clear-search'),
        status: document.getElementById('pam-status'),
        total: document.getElementById('pam-total'),
        list: document.getElementById('pam-list'),
        skeleton: document.getElementById('pam-skeleton'),
        pager: document.getElementById('pam-pager'),
        toastRoot: document.getElementById('pam-toast-root'),
        confirmModal: document.getElementById('pam-confirm-modal'),
        confirmTitle: document.getElementById('pam-confirm-title'),
        confirmMessage: document.getElementById('pam-confirm-message'),
        confirmAction: document.getElementById('pam-confirm-action'),
    };

    const pamEsc = value => String(value ?? '').replace(
        /[&<>"']/g,
        character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[character])
    );

    const pamMoney = value => value === null
        ? 'Sem limite'
        : Number(value || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });

    const pamNumber = value => Number(value || 0).toLocaleString('pt-BR', {
        maximumFractionDigits: 3,
    });

    function pamInitials(name) {
        return String(name || '?')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map(part => part.charAt(0))
            .join('')
            .toUpperCase();
    }

    function pamProgressTone(percent) {
        if (percent >= 100) return 'is-danger';
        if (percent >= 80) return 'is-warning';
        return '';
    }

    function pamStatusMeta(status) {
        return {
            active: {
                label: 'Ativo',
                icon: 'circle-check',
            },
            blocked: {
                label: 'Bloqueado',
                icon: 'circle-x',
            },
            unconfigured: {
                label: 'Não configurado',
                icon: 'circle-dashed',
            },
        }[status] || {
            label: 'Não configurado',
            icon: 'circle-dashed',
        };
    }

    function pamSetLoading(loading) {
        pamElements.skeleton.hidden = !loading;
        pamElements.list.hidden = loading;
        pamElements.pager.hidden = loading;
    }

    function pamEmptyState(title, description, icon = 'users-round') {
        return `
            <div class="pam-state" style="grid-column:1/-1">
                <div class="pam-state-icon">
                    <i data-lucide="${icon}"></i>
                </div>
                <strong>${pamEsc(title)}</strong>
                <p>${pamEsc(description)}</p>
            </div>
        `;
    }

    function pamShowToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `pam-toast ${type === 'error' ? 'error' : ''}`;
        toast.innerHTML = `
            <div class="pam-toast-icon">
                <i data-lucide="${type === 'error' ? 'circle-alert' : 'circle-check'}"></i>
            </div>
            <span>${pamEsc(message)}</span>
        `;

        pamElements.toastRoot.appendChild(toast);
        pamIcons();

        window.setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-5px)';
            toast.style.transition = 'all .18s ease';

            window.setTimeout(() => toast.remove(), 190);
        }, 3400);
    }

    async function pamApi(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
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

    async function pamLoad() {
        if (pamAbort) {
            pamAbort.abort();
        }

        pamAbort = new AbortController();

        const search = pamElements.search.value.trim();
        const status = pamElements.status.value;

        pamSetLoading(true);

        try {
            const data = await pamApi(
                `${PAM_BASE}/associates-data?page=${pamPage}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`,
                {
                    signal: pamAbort.signal,
                }
            );

            pamRender(data);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            pamElements.total.textContent = '—';
            pamElements.list.innerHTML = pamEmptyState(
                'Não foi possível carregar os associados',
                error.message,
                'wifi-off'
            );
            pamElements.pager.innerHTML = `
                <div class="pam-pager-info">Falha ao carregar a página.</div>
                <div class="pam-pager-actions">
                    <button class="pam-btn primary" type="button" onclick="pamLoad()">
                        <i data-lucide="refresh-cw"></i>
                        Tentar novamente
                    </button>
                </div>
            `;
            pamSetLoading(false);
            pamIcons();
        }
    }

    function pamRender(data) {
        const items = Array.isArray(data.data) ? data.data : [];

        pamElements.total.textContent = pamNumber(data.total || 0);

        pamElements.list.innerHTML = items.length
            ? items.map(pamAssociateCard).join('')
            : pamEmptyState(
                'Nenhum associado encontrado',
                'Altere a busca ou o filtro de participação para ampliar os resultados.',
                'user-round-search'
            );

        pamElements.pager.innerHTML = pamPagination(data);

        pamSetLoading(false);
        pamIcons();
    }

    function pamAssociateCard(item) {
        const limit = item.financial_limit === null
            ? null
            : Number(item.financial_limit || 0);

        const consumed = Number(item.financial_consumed || 0);
        const remaining = item.financial_remaining === null
            ? null
            : Number(item.financial_remaining || 0);

        const percent = limit && limit > 0
            ? Math.min(100, (consumed / limit) * 100)
            : 0;

        const status = item.participation_status || 'unconfigured';
        const meta = pamStatusMeta(status);
        const nextStatus = status === 'active' ? 'blocked' : 'active';
        const products = Number(item.product_limits || 0);

        const codeLabel = item.code
            ? `Associado #${pamEsc(item.code)}`
            : 'Sem matrícula';

        const locationLabel = item.location
            ? ` · ${pamEsc(item.location)}`
            : '';

        return `
            <article class="pam-item ${status === 'active' ? 'is-active' : status === 'blocked' ? 'is-blocked' : ''}">
                <div class="pam-person">
                    <div class="pam-avatar">${pamEsc(pamInitials(item.name))}</div>

                    <div class="pam-person-copy">
                        <div class="pam-name" title="${pamEsc(item.name)}">${pamEsc(item.name)}</div>
                        <div class="pam-meta">${codeLabel}${locationLabel}</div>

                        <div class="pam-person-status">
                            <span class="pam-badge ${pamEsc(status)}">
                                <i data-lucide="${meta.icon}"></i>
                                ${pamEsc(meta.label)}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="pam-financial">
                    <div class="pam-metric-label">Saldo financeiro disponível</div>
                    <div class="pam-metric-value">${pamMoney(remaining)}</div>
                    <div class="pam-metric-helper">
                        ${pamMoney(consumed)} utilizado${limit === null ? '' : ` de ${pamMoney(limit)}`}
                    </div>

                    ${limit !== null ? `
                        <div class="pam-progress ${pamProgressTone(percent)}" title="${Math.round(percent)}% utilizado">
                            <span style="width:${Math.min(100, percent)}%"></span>
                        </div>
                    ` : ''}
                </div>

                <div class="pam-products">
                    <div class="pam-products-value">
                        <div class="pam-products-icon">
                            <i data-lucide="package-check"></i>
                        </div>

                        <div>
                            <strong>${pamNumber(products)}</strong>
                            <span>${products === 1 ? 'produto com limite' : 'produtos com limite'}</span>
                        </div>
                    </div>
                </div>

                <div class="pam-row-actions">
                    <a class="pam-btn primary" href="${pamEsc(item.manage_url)}">
                        <i data-lucide="sliders-horizontal"></i>
                        Gerenciar
                    </a>

                    ${PAM_CAN_MANAGE ? `
                        <button
                            class="pam-btn ${nextStatus === 'blocked' ? 'warning' : ''}"
                            type="button"
                            onclick="pamRequestParticipation(${Number(item.id)}, '${nextStatus}', '${pamEsc(item.name).replace(/'/g, "\\'")}')"
                        >
                            <i data-lucide="${nextStatus === 'active' ? 'user-round-check' : 'user-round-x'}"></i>
                            ${nextStatus === 'active' ? 'Permitir' : 'Bloquear'}
                        </button>
                    ` : ''}
                </div>
            </article>
        `;
    }

    function pamPagination(data) {
        const currentPage = Number(data.current_page || 1);
        const lastPage = Number(data.last_page || 1);
        const from = Number(data.from || 0);
        const to = Number(data.to || 0);
        const total = Number(data.total || 0);

        return `
            <div class="pam-pager-info">
                ${total
                    ? `Exibindo ${pamNumber(from)} a ${pamNumber(to)} de ${pamNumber(total)} associados`
                    : 'Nenhum resultado para exibir'}
            </div>

            <div class="pam-pager-actions">
                <button
                    class="pam-btn"
                    type="button"
                    ${currentPage <= 1 ? 'disabled' : ''}
                    onclick="pamGo(${currentPage - 1})"
                >
                    <i data-lucide="chevron-left"></i>
                    Anterior
                </button>

                <button
                    class="pam-btn"
                    type="button"
                    ${currentPage >= lastPage ? 'disabled' : ''}
                    onclick="pamGo(${currentPage + 1})"
                >
                    Próxima
                    <i data-lucide="chevron-right"></i>
                </button>
            </div>
        `;
    }

    function pamGo(page) {
        const targetPage = Number(page);

        if (!Number.isFinite(targetPage) || targetPage < 1) {
            return;
        }

        pamPage = targetPage;
        pamLoad();

        document
            .getElementById('project-associates-manager')
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
    }

    function pamRequestParticipation(id, status, name) {
        const allowing = status === 'active';

        pamPendingConfirmation = async () => {
            pamElements.confirmAction.disabled = true;

            try {
                await pamApi(`${PAM_BASE}/associates/${id}/participation`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': PAM_CSRF,
                    },
                    body: JSON.stringify({
                        status,
                    }),
                });

                pamCloseConfirm();
                pamShowToast(
                    allowing
                        ? `${name} agora pode registrar entregas.`
                        : `Novas entregas foram bloqueadas para ${name}.`
                );
                await pamLoad();
            } catch (error) {
                pamShowToast(error.message, 'error');
            } finally {
                pamElements.confirmAction.disabled = false;
            }
        };

        pamElements.confirmTitle.textContent = allowing
            ? 'Permitir novas entregas'
            : 'Bloquear novas entregas';

        pamElements.confirmMessage.textContent = allowing
            ? `${name} será incluído como participante ativo e poderá registrar novas entregas neste projeto.`
            : `${name} deixará de poder registrar novas entregas. Os registros históricos não serão removidos.`;

        pamElements.confirmAction.textContent = allowing
            ? 'Permitir entregas'
            : 'Bloquear entregas';

        pamElements.confirmModal.classList.add('active');
        pamIcons();
    }

    function pamCloseConfirm() {
        pamPendingConfirmation = null;
        pamElements.confirmModal.classList.remove('active');
    }

    function pamRunConfirmation() {
        if (typeof pamPendingConfirmation === 'function') {
            pamPendingConfirmation();
        }
    }

    function pamClearSearch() {
        pamElements.search.value = '';
        pamElements.clearSearch.classList.remove('visible');
        pamPage = 1;
        pamLoad();
        pamElements.search.focus();
    }

    function pamScheduleSearch() {
        window.clearTimeout(pamTimer);
        pamElements.clearSearch.classList.toggle(
            'visible',
            pamElements.search.value.length > 0
        );

        pamTimer = window.setTimeout(() => {
            pamPage = 1;
            pamLoad();
        }, 350);
    }

    function pamIcons() {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    pamElements.search.addEventListener('input', pamScheduleSearch);
    pamElements.clearSearch.addEventListener('click', pamClearSearch);

    pamElements.status.addEventListener('change', () => {
        pamPage = 1;
        pamLoad();
    });

    document.getElementById('pam-confirm-close').addEventListener('click', pamCloseConfirm);
    document.getElementById('pam-confirm-cancel').addEventListener('click', pamCloseConfirm);
    pamElements.confirmAction.addEventListener('click', pamRunConfirmation);

    pamElements.confirmModal.addEventListener('click', event => {
        if (event.target === pamElements.confirmModal) {
            pamCloseConfirm();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && pamElements.confirmModal.classList.contains('active')) {
            pamCloseConfirm();
        }
    });

    window.pamGo = pamGo;
    window.pamLoad = pamLoad;
    window.pamRequestParticipation = pamRequestParticipation;

    pamLoad();
    pamIcons();
</script>
@endsection
