@extends('layouts.bento')

@section('title', 'Painel de Entregas')
@section('page-title', 'Painel de Entregas')
@section('user-role', 'Registrador')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery',
        'dashboard',
        $currentTenant->slug ?? request()->route('tenant')
    );

    $projectCount = count($projects);
    $tenantName = $currentTenant->name ?? 'Sua organização';
@endphp

@section('content')
<style>
    .delivery-dashboard {
        --dp-primary: var(--color-primary, #20a957);
        --dp-primary-dark: var(--color-primary-dark, #16803d);
        --dp-primary-deep: var(--color-primary-deep, #116a35);
        --dp-surface: var(--color-surface, #ffffff);
        --dp-soft: var(--color-surface-soft, #f7faf8);
        --dp-muted: var(--color-surface-muted, #eef4f0);
        --dp-border: var(--color-border, #dce7e0);
        --dp-border-strong: var(--color-border-strong, #c8d7ce);
        --dp-text: var(--color-text, #102018);
        --dp-text-2: var(--color-text-secondary, #53655a);
        --dp-text-3: var(--color-text-muted, #7d8c83);
        --dp-success: var(--color-success, #16a34a);
        --dp-warning: var(--color-warning, #d97706);
        --dp-danger: var(--color-danger, #dc2626);
        --dp-info: var(--color-info, #0284c7);
        --dp-shadow-sm: 0 8px 24px rgba(15, 46, 27, .06);
        --dp-shadow-md: 0 18px 44px rgba(15, 46, 27, .10);
        --dp-shadow-lg: 0 26px 64px rgba(15, 46, 27, .16);
        --dp-radius: var(--radius-lg, 20px);
        color: var(--dp-text);
    }

    .delivery-dashboard *,
    .delivery-dashboard *::before,
    .delivery-dashboard *::after {
        box-sizing: border-box;
    }

    .dp-hero {
        position: relative;
        display: grid;
        min-height: 260px;
        grid-template-columns: minmax(0, 1.08fr) minmax(340px, .92fr);
        gap: 1rem;
        margin-bottom: 1rem;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .24);
        border-radius: 28px;
        background:
            radial-gradient(circle at 86% 10%, rgba(255, 255, 255, .18), transparent 16rem),
            linear-gradient(135deg, var(--dp-primary) 0%, var(--dp-primary-dark) 58%, var(--dp-primary-deep) 100%);
        box-shadow: 0 24px 56px rgba(22, 128, 61, .20);
        color: #fff;
    }

    .dp-hero::before {
        position: absolute;
        inset: 0;
        background:
            linear-gradient(115deg, rgba(255,255,255,.11), transparent 42%),
            radial-gradient(circle at 6% 125%, rgba(255,255,255,.13), transparent 20rem);
        content: "";
        pointer-events: none;
    }

    .dp-hero-wave {
        position: absolute;
        right: 0;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 76px;
        color: rgba(255,255,255,.10);
        pointer-events: none;
    }

    .dp-hero-copy,
    .dp-hero-summary {
        position: relative;
        z-index: 2;
    }

    .dp-hero-copy {
        display: flex;
        min-width: 0;
        justify-content: center;
        flex-direction: column;
        padding: 1.5rem 1.6rem 3rem;
    }

    .dp-eyebrow {
        display: inline-flex;
        width: max-content;
        align-items: center;
        gap: .42rem;
        margin-bottom: .7rem;
        padding: .38rem .62rem;
        border: 1px solid rgba(255,255,255,.17);
        border-radius: 999px;
        background: rgba(255,255,255,.11);
        color: rgba(255,255,255,.9);
        font-size: .7rem;
        font-weight: 760;
        backdrop-filter: blur(10px);
    }

    .dp-eyebrow svg {
        width: 15px;
        height: 15px;
    }

    .dp-hero h1 {
        max-width: 700px;
        margin: 0;
        font-size: clamp(1.8rem, 4vw, 3.25rem);
        font-weight: 850;
        letter-spacing: -.052em;
        line-height: 1.04;
    }

    .dp-hero-copy > p {
        max-width: 650px;
        margin: .78rem 0 0;
        color: rgba(255,255,255,.76);
        font-size: .85rem;
        font-weight: 580;
        line-height: 1.6;
    }

    .dp-hero-tags {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        margin-top: .9rem;
    }

    .dp-hero-tag {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .36rem .55rem;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 999px;
        background: rgba(255,255,255,.09);
        color: rgba(255,255,255,.82);
        font-size: .65rem;
        font-weight: 660;
    }

    .dp-hero-tag svg {
        width: 13px;
        height: 13px;
    }

    .dp-hero-summary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0,1fr));
        gap: .65rem;
        margin: .85rem;
        padding: .75rem;
        align-content: center;
        border: 1px solid rgba(255,255,255,.16);
        border-radius: 22px;
        background: rgba(255,255,255,.10);
        backdrop-filter: blur(16px);
    }

    .dp-hero-stat {
        min-width: 0;
        padding: .8rem;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 16px;
        background: rgba(255,255,255,.09);
    }

    .dp-hero-stat-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .5rem;
        color: rgba(255,255,255,.68);
        font-size: .61rem;
        font-weight: 720;
    }

    .dp-hero-stat-head svg {
        width: 15px;
        height: 15px;
    }

    .dp-hero-stat strong {
        display: block;
        margin-top: .38rem;
        overflow: hidden;
        color: #fff;
        font-size: 1.48rem;
        font-weight: 860;
        letter-spacing: -.04em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dp-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .8rem;
        margin-bottom: 1rem;
        padding: .8rem;
        border: 1px solid var(--dp-border);
        border-radius: 18px;
        background: color-mix(in srgb, var(--dp-surface) 94%, transparent);
        box-shadow: var(--dp-shadow-sm);
        backdrop-filter: blur(12px);
    }

    .dp-section-copy {
        min-width: 0;
    }

    .dp-section-copy h2 {
        display: flex;
        align-items: center;
        gap: .5rem;
        margin: 0;
        color: var(--dp-text);
        font-size: 1rem;
        font-weight: 810;
        letter-spacing: -.025em;
    }

    .dp-section-copy h2 svg {
        width: 18px;
        height: 18px;
        color: var(--dp-primary-dark);
    }

    .dp-section-copy p {
        margin: .18rem 0 0;
        color: var(--dp-text-3);
        font-size: .65rem;
    }

    .dp-count {
        display: inline-grid;
        min-width: 24px;
        height: 24px;
        place-items: center;
        padding: 0 .38rem;
        border-radius: 999px;
        background: var(--dp-primary);
        color: #fff;
        font-size: .62rem;
        font-weight: 800;
    }

    .dp-tools {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .5rem;
        flex-wrap: wrap;
    }

    .dp-search {
        position: relative;
        width: min(260px, 100%);
    }

    .dp-search svg {
        position: absolute;
        top: 50%;
        left: .65rem;
        width: 15px;
        height: 15px;
        color: var(--dp-text-3);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .dp-search input {
        width: 100%;
        min-height: 40px;
        padding: .55rem .7rem .55rem 2rem;
        border: 1px solid var(--dp-border);
        border-radius: 12px;
        outline: none;
        background: var(--dp-soft);
        color: var(--dp-text);
        font-size: .75rem;
        transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
    }

    .dp-search input:focus {
        border-color: var(--dp-primary);
        background: var(--dp-surface);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--dp-primary) 12%, transparent);
    }

    .dp-filter-group {
        display: flex;
        align-items: center;
        gap: .35rem;
        flex-wrap: wrap;
    }

    .dp-filter {
        min-height: 38px;
        padding: .5rem .65rem;
        border: 1px solid var(--dp-border);
        border-radius: 11px;
        background: var(--dp-surface);
        color: var(--dp-text-2);
        cursor: pointer;
        font-size: .66rem;
        font-weight: 720;
        transition: .15s ease;
    }

    .dp-filter:hover,
    .dp-filter.active {
        border-color: var(--dp-primary);
        background: var(--dp-primary);
        color: #fff;
    }

    .dp-projects {
        display: grid;
        grid-template-columns: repeat(2, minmax(0,1fr));
        gap: .85rem;
    }

    .dp-card {
        position: relative;
        display: flex;
        min-width: 0;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--dp-border);
        border-radius: 21px;
        background: var(--dp-surface);
        box-shadow: var(--dp-shadow-sm);
        transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
    }

    .dp-card::before {
        position: absolute;
        top: 0;
        right: 0;
        left: 0;
        height: 3px;
        background: var(--card-accent, var(--dp-primary));
        content: "";
    }

    .dp-card:hover {
        border-color: color-mix(in srgb, var(--card-accent, var(--dp-primary)) 42%, var(--dp-border));
        box-shadow: var(--dp-shadow-md);
        transform: translateY(-2px);
    }

    .dp-card.active {
        --card-accent: var(--dp-success);
    }

    .dp-card.draft {
        --card-accent: var(--dp-warning);
    }

    .dp-card.awaiting_delivery {
        --card-accent: var(--dp-info);
    }

    .dp-card-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        padding: 1rem 1rem .85rem;
    }

    .dp-card-identity {
        display: flex;
        min-width: 0;
        align-items: flex-start;
        gap: .7rem;
    }

    .dp-project-icon {
        display: grid;
        width: 43px;
        height: 43px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 14px;
        background: color-mix(in srgb, var(--card-accent, var(--dp-primary)) 11%, var(--dp-surface));
        color: var(--card-accent, var(--dp-primary));
    }

    .dp-project-icon svg {
        width: 21px;
        height: 21px;
    }

    .dp-card-head-info {
        min-width: 0;
    }

    .dp-card-title {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .35rem;
        margin: .05rem 0 0;
        color: var(--dp-text);
        font-size: .94rem;
        font-weight: 810;
        letter-spacing: -.02em;
    }

    .dp-card-title-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dp-badge-free {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        gap: .2rem;
        padding: .2rem .38rem;
        border-radius: 999px;
        background: #f4efff;
        color: #7c3aed;
        font-size: .53rem;
        font-weight: 780;
        text-transform: uppercase;
    }

    .dp-badge-free svg {
        width: 10px;
        height: 10px;
    }

    .dp-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        margin-top: .42rem;
    }

    .dp-meta-chip {
        display: inline-flex;
        max-width: 100%;
        align-items: center;
        gap: .3rem;
        padding: .27rem .42rem;
        border-radius: 8px;
        background: var(--dp-soft);
        color: var(--dp-text-2);
        font-size: .58rem;
        font-weight: 650;
    }

    .dp-meta-chip svg {
        width: 12px;
        height: 12px;
        flex: 0 0 auto;
        color: var(--dp-text-3);
    }

    .dp-meta-chip span {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dp-badge-status {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        gap: .28rem;
        padding: .3rem .5rem;
        border-radius: 999px;
        font-size: .56rem;
        font-weight: 780;
        letter-spacing: .035em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .dp-badge-status svg {
        width: 10px;
        height: 10px;
    }

    .dp-badge-status.active {
        background: color-mix(in srgb, var(--dp-success) 12%, transparent);
        color: var(--dp-success);
    }

    .dp-badge-status.draft {
        background: color-mix(in srgb, var(--dp-warning) 12%, transparent);
        color: var(--dp-warning);
    }

    .dp-badge-status.awaiting_delivery {
        background: color-mix(in srgb, var(--dp-info) 12%, transparent);
        color: var(--dp-info);
    }

    .dp-card-body {
        display: flex;
        flex: 1;
        flex-direction: column;
        padding: 0 1rem .9rem;
    }

    .dp-info-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0,1fr));
        gap: .5rem;
    }

    .dp-info-item {
        min-width: 0;
        padding: .62rem;
        border: 1px solid var(--dp-border);
        border-radius: 13px;
        background: var(--dp-soft);
    }

    .dp-info-label {
        overflow: hidden;
        color: var(--dp-text-3);
        font-size: .54rem;
        font-weight: 730;
        letter-spacing: .04em;
        text-overflow: ellipsis;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .dp-info-value {
        margin-top: .24rem;
        overflow: hidden;
        color: var(--dp-text);
        font-size: .92rem;
        font-weight: 820;
        letter-spacing: -.025em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .dp-info-value.ok {
        color: var(--dp-success);
    }

    .dp-info-value.warn {
        color: var(--dp-warning);
    }

    .dp-info-value.danger {
        color: var(--dp-danger);
    }

    .dp-progress-wrap {
        margin-top: .75rem;
        padding: .7rem;
        border: 1px solid var(--dp-border);
        border-radius: 13px;
        background: var(--dp-surface);
    }

    .dp-progress-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .6rem;
        margin-bottom: .42rem;
        color: var(--dp-text-2);
        font-size: .64rem;
        font-weight: 680;
    }

    .dp-progress-pct {
        color: var(--dp-primary-dark);
        font-size: .72rem;
        font-weight: 800;
    }

    .dp-progress-bar {
        height: 7px;
        overflow: hidden;
        border-radius: 999px;
        background: var(--dp-muted);
    }

    .dp-progress-fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, var(--dp-primary-dark), var(--dp-primary), var(--dp-success));
        transition: width .8s cubic-bezier(.4,0,.2,1);
    }

    .dp-draft-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .6rem;
        margin: 0 1rem .85rem;
        padding: .62rem;
        border: 1px solid color-mix(in srgb, var(--dp-warning) 24%, var(--dp-border));
        border-radius: 13px;
        background: color-mix(in srgb, var(--dp-warning) 7%, var(--dp-surface));
    }

    .dp-draft-msg {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .42rem;
        color: var(--dp-text-2);
        font-size: .64rem;
        font-weight: 620;
    }

    .dp-draft-msg svg {
        width: 15px;
        height: 15px;
        flex: 0 0 auto;
        color: var(--dp-warning);
    }

    .dp-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .7rem;
        padding: .75rem 1rem;
        border-top: 1px solid var(--dp-border);
        background: var(--dp-soft);
    }

    .dp-deadline {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .35rem;
        color: var(--dp-text-2);
        font-size: .62rem;
        font-weight: 650;
    }

    .dp-deadline svg {
        width: 14px;
        height: 14px;
        flex: 0 0 auto;
    }

    .dp-deadline.urgent {
        color: var(--dp-danger);
    }

    .dp-card-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .38rem;
        flex-wrap: wrap;
    }

    .btn {
        display: inline-flex;
        min-height: 36px;
        align-items: center;
        justify-content: center;
        gap: .34rem;
        padding: .5rem .65rem;
        border: 1px solid transparent;
        border-radius: 11px;
        cursor: pointer;
        font-size: .62rem;
        font-weight: 740;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease, background .15s ease;
    }

    .btn svg {
        width: 14px;
        height: 14px;
    }

    .btn:hover {
        transform: translateY(-1px);
    }

    .btn:disabled {
        cursor: not-allowed;
        opacity: .48;
        transform: none;
    }

    .btn-primary {
        border-color: var(--dp-primary-dark);
        background: linear-gradient(135deg, var(--dp-primary), var(--dp-primary-dark));
        color: #fff;
        box-shadow: 0 8px 18px rgba(32,169,87,.15);
    }

    .btn-success {
        border-color: var(--dp-success);
        background: var(--dp-success);
        color: #fff;
    }

    .btn-warning {
        border-color: var(--dp-warning);
        background: var(--dp-warning);
        color: #fff;
    }

    .btn-info {
        border-color: var(--dp-info);
        background: var(--dp-info);
        color: #fff;
    }

    .btn-danger {
        border-color: var(--dp-danger);
        background: var(--dp-danger);
        color: #fff;
    }

    .btn-ghost {
        border-color: var(--dp-border);
        background: var(--dp-surface);
        color: var(--dp-text-2);
    }

    .btn-ghost:hover {
        border-color: color-mix(in srgb, var(--dp-primary) 35%, var(--dp-border));
        color: var(--dp-primary-dark);
    }

    .dp-empty,
    .dp-no-results {
        display: flex;
        min-height: 280px;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .65rem;
        padding: 2rem;
        border: 1px dashed var(--dp-border-strong);
        border-radius: 20px;
        background: var(--dp-soft);
        color: var(--dp-text-2);
        text-align: center;
    }

    .dp-empty-icon {
        display: grid;
        width: 58px;
        height: 58px;
        place-items: center;
        border-radius: 18px;
        background: var(--dp-muted);
        color: var(--dp-primary-dark);
    }

    .dp-empty-icon svg {
        width: 28px;
        height: 28px;
    }

    .dp-empty-title {
        color: var(--dp-text);
        font-size: .88rem;
        font-weight: 800;
    }

    .dp-empty-msg {
        max-width: 430px;
        color: var(--dp-text-3);
        font-size: .68rem;
        line-height: 1.55;
    }

    .dp-no-results {
        display: none;
        min-height: 190px;
        grid-column: 1 / -1;
    }

    .dp-no-results.visible {
        display: flex;
    }

    .dp-modal-overlay {
        position: fixed;
        z-index: 10000;
        inset: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(8, 24, 14, .58);
        backdrop-filter: blur(7px);
    }

    .dp-modal-overlay.open {
        display: flex;
    }

    .dp-modal,
    .dp-confirm {
        width: min(100%, 500px);
        max-height: min(88vh, 760px);
        overflow-y: auto;
        border: 1px solid color-mix(in srgb, var(--dp-border) 75%, transparent);
        border-radius: 22px;
        background: var(--dp-surface);
        box-shadow: 0 28px 70px rgba(5, 22, 12, .28);
    }

    .dp-modal {
        padding: 1rem;
    }

    .dp-confirm {
        max-width: 410px;
        padding: 1.25rem;
        text-align: center;
    }

    .dp-modal-head {
        display: flex;
        align-items: flex-start;
        gap: .7rem;
        margin-bottom: .9rem;
    }

    .dp-modal-icon,
    .dp-confirm-icon {
        display: grid;
        width: 44px;
        height: 44px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 14px;
        background: var(--dp-muted);
        color: var(--dp-primary-dark);
    }

    .dp-modal-icon svg,
    .dp-confirm-icon svg {
        width: 22px;
        height: 22px;
    }

    .dp-modal-title,
    .dp-confirm-title {
        margin: 0;
        color: var(--dp-text);
        font-size: .98rem;
        font-weight: 810;
        letter-spacing: -.02em;
    }

    .dp-modal-sub,
    .dp-confirm-msg {
        margin: .22rem 0 0;
        color: var(--dp-text-3);
        font-size: .68rem;
        line-height: 1.5;
    }

    .dp-confirm-icon {
        margin: 0 auto .75rem;
    }

    .dp-confirm-msg {
        margin-top: .45rem;
    }

    .dp-form-group {
        margin-bottom: .75rem;
    }

    .dp-form-group label {
        display: block;
        margin-bottom: .3rem;
        color: var(--dp-text);
        font-size: .68rem;
        font-weight: 720;
    }

    .dp-form-group input,
    .dp-form-group select,
    .dp-form-group textarea,
    #deliver-customer {
        width: 100%;
        min-height: 42px;
        padding: .62rem .7rem;
        border: 1px solid var(--dp-border-strong);
        border-radius: 12px;
        outline: none;
        background: var(--dp-soft);
        color: var(--dp-text);
        font-size: .76rem;
    }

    .dp-form-group textarea {
        min-height: 82px;
        resize: vertical;
    }

    .dp-form-group input:focus,
    .dp-form-group select:focus,
    .dp-form-group textarea:focus,
    #deliver-customer:focus {
        border-color: var(--dp-primary);
        background: var(--dp-surface);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--dp-primary) 12%, transparent);
    }

    .dp-product-rows {
        display: grid;
        gap: .55rem;
        margin-bottom: .75rem;
    }

    .dp-product-row {
        padding: .7rem;
        border: 1px solid var(--dp-border);
        border-radius: 13px;
        background: var(--dp-soft);
    }

    .dp-product-row-name {
        color: var(--dp-text);
        font-size: .76rem;
        font-weight: 760;
    }

    .dp-product-row-meta {
        margin-top: .18rem;
        color: var(--dp-text-3);
        font-size: .61rem;
        line-height: 1.4;
    }

    .dp-qty-line {
        display: grid;
        grid-template-columns: auto minmax(0,1fr) auto;
        gap: .45rem;
        align-items: center;
        margin-top: .5rem;
    }

    .dp-qty-line label,
    .dp-qty-unit {
        color: var(--dp-text-2);
        font-size: .62rem;
        font-weight: 650;
    }

    .dp-qty-line input {
        width: 100%;
        min-width: 0;
        min-height: 38px;
        padding: .5rem .6rem;
        border: 1px solid var(--dp-border-strong);
        border-radius: 10px;
        background: var(--dp-surface);
        color: var(--dp-text);
        font-size: .72rem;
    }

    .dp-modal-footer,
    .dp-confirm-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .5rem;
        margin-top: 1rem;
    }

    .dp-confirm-footer {
        justify-content: center;
    }

    .dp-loading-products {
        display: flex;
        min-height: 100px;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .55rem;
        color: var(--dp-text-3);
        font-size: .68rem;
    }

    .dp-brand-loader {
        position: relative;
        width: 52px;
        height: 44px;
    }

    .dp-brand-loader span {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 9px;
        height: 9px;
        margin: -4.5px;
        border-radius: 50%;
        background: var(--dp-primary);
    }

    .dp-brand-loader span:nth-child(1) {
        animation: dp-orbit-1 1.15s linear infinite;
    }

    .dp-brand-loader span:nth-child(2) {
        animation: dp-orbit-2 1.15s linear infinite;
    }

    .dp-brand-loader span:nth-child(3) {
        animation: dp-orbit-3 1.15s linear infinite;
    }

    @keyframes dp-orbit-1 {
        to { transform: rotate(360deg) translateX(16px) rotate(-360deg); }
    }

    @keyframes dp-orbit-2 {
        from { transform: rotate(120deg) translateX(16px) rotate(-120deg); }
        to { transform: rotate(480deg) translateX(16px) rotate(-480deg); }
    }

    @keyframes dp-orbit-3 {
        from { transform: rotate(240deg) translateX(16px) rotate(-240deg); }
        to { transform: rotate(600deg) translateX(16px) rotate(-600deg); }
    }

    .dp-spinner {
        display: inline-block;
        width: 13px;
        height: 13px;
        border: 2px solid currentColor;
        border-top-color: transparent;
        border-radius: 50%;
        animation: dp-spin .65s linear infinite;
    }

    @keyframes dp-spin {
        to { transform: rotate(360deg); }
    }

    #dp-toasts {
        position: fixed;
        z-index: 99999;
        right: 1rem;
        bottom: calc(1rem + env(safe-area-inset-bottom));
        display: flex;
        width: min(360px, calc(100% - 2rem));
        flex-direction: column;
        gap: .5rem;
    }

    .dp-toast {
        display: grid;
        grid-template-columns: 34px minmax(0,1fr);
        gap: .6rem;
        align-items: center;
        padding: .65rem;
        border: 1px solid var(--dp-border);
        border-radius: 14px;
        background: var(--dp-surface);
        box-shadow: var(--dp-shadow-md);
        animation: dp-fadein .25s ease;
    }

    .dp-toast-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 11px;
        background: var(--dp-muted);
    }

    .dp-toast-icon svg {
        width: 17px;
        height: 17px;
    }

    .dp-toast.success .dp-toast-icon {
        background: color-mix(in srgb, var(--dp-success) 12%, transparent);
        color: var(--dp-success);
    }

    .dp-toast.error .dp-toast-icon {
        background: color-mix(in srgb, var(--dp-danger) 12%, transparent);
        color: var(--dp-danger);
    }

    .dp-toast.info .dp-toast-icon {
        background: color-mix(in srgb, var(--dp-info) 12%, transparent);
        color: var(--dp-info);
    }

    .dp-toast-message {
        color: var(--dp-text);
        font-size: .72rem;
        font-weight: 650;
        line-height: 1.45;
    }

    @keyframes dp-fadein {
        from {
            opacity: 0;
            transform: translateY(8px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 980px) {
        .dp-hero {
            grid-template-columns: 1fr;
        }

        .dp-hero-copy {
            padding-bottom: 2.3rem;
        }

        .dp-hero-summary {
            margin-top: 0;
        }

        .dp-projects {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 720px) {
        .dp-hero {
            border-radius: 23px;
        }

        .dp-hero-copy {
            padding: 1rem 1rem 2.1rem;
        }

        .dp-hero-summary {
            margin: 0 .65rem .65rem;
        }

        .dp-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .dp-tools {
            justify-content: flex-start;
        }

        .dp-search {
            width: 100%;
        }

        .dp-filter-group {
            width: 100%;
            overflow-x: auto;
            flex-wrap: nowrap;
            padding-bottom: .15rem;
            scrollbar-width: none;
        }

        .dp-filter-group::-webkit-scrollbar {
            display: none;
        }

        .dp-filter {
            flex: 0 0 auto;
        }

        .dp-card-head {
            align-items: stretch;
            flex-direction: column;
        }

        .dp-badge-status {
            width: max-content;
        }

        .dp-card-footer {
            align-items: stretch;
            flex-direction: column;
        }

        .dp-card-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            width: 100%;
        }

        .dp-card-actions .btn {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .dp-hero-summary {
            grid-template-columns: repeat(2, minmax(0,1fr));
        }

        .dp-hero-stat {
            padding: .65rem;
        }

        .dp-hero-stat strong {
            font-size: 1.25rem;
        }

        .dp-info-grid {
            grid-template-columns: repeat(2, minmax(0,1fr));
        }

        .dp-card {
            border-radius: 18px;
        }

        .dp-card-head,
        .dp-card-body,
        .dp-card-footer {
            padding-right: .75rem;
            padding-left: .75rem;
        }

        .dp-draft-bar {
            align-items: stretch;
            flex-direction: column;
            margin-right: .75rem;
            margin-left: .75rem;
        }

        .dp-draft-bar .btn {
            width: 100%;
        }

        .dp-card-actions {
            grid-template-columns: 1fr;
        }

        .dp-modal-overlay {
            align-items: flex-end;
            padding: .55rem;
        }

        .dp-modal,
        .dp-confirm {
            width: 100%;
            max-height: 90dvh;
            border-radius: 22px 22px 16px 16px;
        }

        .dp-modal-footer,
        .dp-confirm-footer {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .dp-modal-footer .btn,
        .dp-confirm-footer .btn {
            width: 100%;
        }

        #dp-toasts {
            right: .7rem;
            bottom: calc(.7rem + env(safe-area-inset-bottom));
            width: calc(100% - 1.4rem);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .dp-card,
        .btn,
        .dp-progress-fill {
            transition: none;
        }

        .dp-brand-loader span,
        .dp-spinner {
            animation-duration: 1.4s;
        }
    }
</style>

<div class="delivery-dashboard">
    <div id="dp-toasts" aria-live="polite"></div>

    <section class="dp-hero">
        <svg class="dp-hero-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
            <path
                fill="currentColor"
                d="M0,64L60,69.3C120,75,240,85,360,80C480,75,600,53,720,53.3C840,53,960,75,1080,80C1200,85,1320,75,1380,69.3L1440,64L1440,120L0,120Z"
            ></path>
        </svg>

        <div class="dp-hero-copy">
            <span class="dp-eyebrow">
                <i data-lucide="package-check"></i>
                Central de entregas
            </span>

            <h1>Projetos e entregas em um só painel.</h1>

            <p>
                Acompanhe o andamento dos projetos e acesse rapidamente as ações de registro.
            </p>

            <div class="dp-hero-tags">
                <span class="dp-hero-tag">
                    <i data-lucide="building-2"></i>
                    {{ $tenantName }}
                </span>

                <span class="dp-hero-tag">
                    <i data-lucide="folder-kanban"></i>
                    {{ $projectCount }} {{ $projectCount === 1 ? 'projeto disponível' : 'projetos disponíveis' }}
                </span>
            </div>
        </div>

        <div class="dp-hero-summary">
            <div class="dp-hero-stat">
                <div class="dp-hero-stat-head">
                    <span>Projetos ativos</span>
                    <i data-lucide="folder-check"></i>
                </div>
                <strong>{{ $stats['active_projects'] }}</strong>
            </div>

            <div class="dp-hero-stat">
                <div class="dp-hero-stat-head">
                    <span>Entregas hoje</span>
                    <i data-lucide="calendar-check-2"></i>
                </div>
                <strong>{{ $stats['deliveries_today'] }}</strong>
            </div>

            <div class="dp-hero-stat">
                <div class="dp-hero-stat-head">
                    <span>Pendentes</span>
                    <i data-lucide="clock-3"></i>
                </div>
                <strong>{{ $stats['pending_approvals'] }}</strong>
            </div>

            <div class="dp-hero-stat">
                <div class="dp-hero-stat-head">
                    <span>Semana atual</span>
                    <i data-lucide="chart-no-axes-combined"></i>
                </div>
                <strong>{{ number_format($stats['total_delivered_this_week'], 0, ',', '.') }}</strong>
            </div>
        </div>
    </section>

    <section class="dp-toolbar">
        <div class="dp-section-copy">
            <h2>
                <i data-lucide="folder-open"></i>
                Projetos
                <span class="dp-count" id="dp-visible-count">{{ $projectCount }}</span>
            </h2>
            <p>Selecione um projeto para registrar ou acompanhar as entregas.</p>
        </div>

        <div class="dp-tools">
            <label class="dp-search" aria-label="Buscar projeto">
                <i data-lucide="search"></i>
                <input
                    id="dp-project-search"
                    type="search"
                    placeholder="Buscar projeto ou cliente"
                    autocomplete="off"
                >
            </label>

            <div class="dp-filter-group" aria-label="Filtrar projetos por status">
                <button class="dp-filter active" type="button" data-status-filter="all">Todos</button>
                <button class="dp-filter" type="button" data-status-filter="active">Ativos</button>
                <button class="dp-filter" type="button" data-status-filter="draft">Rascunhos</button>
                <button class="dp-filter" type="button" data-status-filter="awaiting_delivery">Aguardando cliente</button>
            </div>
        </div>
    </section>

    @if($projects->isEmpty())
        <div class="dp-empty">
            <span class="dp-empty-icon">
                <i data-lucide="folder-x"></i>
            </span>

            <div class="dp-empty-title">Nenhum projeto disponível</div>

            <div class="dp-empty-msg">
                Não existem projetos em andamento ou em rascunho para este período.
            </div>
        </div>
    @else
        <div class="dp-projects" id="dp-projects">
            @foreach($projects as $project)
                <article
                    class="dp-card {{ $project['status_value'] }}"
                    data-project-card
                    data-status="{{ $project['status_value'] }}"
                    data-search="{{ \Illuminate\Support\Str::lower($project['title'] . ' ' . $project['customer_name']) }}"
                    data-id="{{ $project['id'] }}"
                    data-title="{{ e($project['title']) }}"
                    data-allow-any="{{ $project['allow_any_product'] ? '1' : '0' }}"
                >
                    <header class="dp-card-head">
                        <div class="dp-card-identity">
                            <span class="dp-project-icon">
                                @if($project['status_value'] === 'draft')
                                    <i data-lucide="file-pen-line"></i>
                                @elseif($project['status_value'] === 'awaiting_delivery')
                                    <i data-lucide="truck"></i>
                                @else
                                    <i data-lucide="folder-kanban"></i>
                                @endif
                            </span>

                            <div class="dp-card-head-info">
                                <h3 class="dp-card-title" title="{{ $project['title'] }}">
                                    <span class="dp-card-title-text">{{ $project['title'] }}</span>

                                    @if($project['allow_any_product'])
                                        <span class="dp-badge-free">
                                            <i data-lucide="infinity"></i>
                                            Livre
                                        </span>
                                    @endif
                                </h3>

                                <div class="dp-card-meta">
                                    <span class="dp-meta-chip">
                                        <i data-lucide="building-2"></i>
                                        <span>{{ $project['customer_name'] }}</span>
                                    </span>

                                    @if($project['start_date'])
                                        <span class="dp-meta-chip">
                                            <i data-lucide="calendar-days"></i>
                                            <span>
                                                {{ $project['start_date'] }}
                                                @if($project['end_date'])
                                                    → {{ $project['end_date'] }}
                                                @endif
                                            </span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <span class="dp-badge-status {{ $project['status_value'] }}">
                            @if($project['status_value'] === 'draft')
                                <i data-lucide="file-pen-line"></i>
                            @elseif($project['status_value'] === 'awaiting_delivery')
                                <i data-lucide="package-check"></i>
                            @else
                                <i data-lucide="circle-play"></i>
                            @endif

                            {{ $project['status'] }}
                        </span>
                    </header>

                    <div class="dp-card-body">
                        <div class="dp-info-grid">
                            @if(!$project['allow_any_product'])
                                <div class="dp-info-item">
                                    <div class="dp-info-label">Meta</div>
                                    <div class="dp-info-value">
                                        {{ number_format($project['total_target'], 0, ',', '.') }}
                                    </div>
                                </div>
                            @endif

                            <div class="dp-info-item">
                                <div class="dp-info-label">Entregue</div>
                                <div class="dp-info-value ok">
                                    {{ number_format($project['total_delivered'], 0, ',', '.') }}
                                </div>
                            </div>

                            <div class="dp-info-item">
                                <div class="dp-info-label">Aprovadas</div>
                                <div class="dp-info-value ok">{{ $project['approved_deliveries'] }}</div>
                            </div>

                            <div class="dp-info-item">
                                <div class="dp-info-label">Pendentes</div>
                                <div class="dp-info-value {{ $project['pending_deliveries'] > 0 ? 'warn' : '' }}">
                                    {{ $project['pending_deliveries'] }}
                                </div>
                            </div>

                            <div class="dp-info-item">
                                <div class="dp-info-label">Rejeitadas</div>
                                <div class="dp-info-value {{ $project['rejected_deliveries'] > 0 ? 'danger' : '' }}">
                                    {{ $project['rejected_deliveries'] }}
                                </div>
                            </div>

                            @if($project['days_remaining'] !== null)
                                <div class="dp-info-item">
                                    <div class="dp-info-label">Dias restantes</div>
                                    <div class="dp-info-value {{ $project['days_remaining'] < 3 ? 'danger' : ($project['days_remaining'] < 7 ? 'warn' : '') }}">
                                        {{ max(0, $project['days_remaining']) }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if(!$project['allow_any_product'] && $project['total_target'] > 0)
                            <div class="dp-progress-wrap">
                                <div class="dp-progress-head">
                                    <span>Progresso do projeto</span>
                                    <span class="dp-progress-pct">
                                        {{ number_format($project['progress'], 1, ',', '.') }}%
                                    </span>
                                </div>

                                <div class="dp-progress-bar">
                                    <div
                                        class="dp-progress-fill"
                                        style="width:{{ min(100, max(0, $project['progress'])) }}%"
                                    ></div>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($project['status_value'] === 'draft')
                        <div class="dp-draft-bar">
                            <div class="dp-draft-msg">
                                <i data-lucide="info"></i>
                                Inicie o projeto para liberar novos registros.
                            </div>

                            <button
                                class="btn btn-warning"
                                type="button"
                                onclick="confirmStartProject(
                                    {{ $project['id'] }},
                                    @js($project['title'])
                                )"
                            >
                                <i data-lucide="play"></i>
                                Iniciar
                            </button>
                        </div>
                    @endif

                    <footer class="dp-card-footer">
                        @if($project['days_remaining'] !== null)
                            <div class="dp-deadline {{ $project['days_remaining'] < 3 ? 'urgent' : '' }}">
                                <i data-lucide="clock-3"></i>

                                @if($project['days_remaining'] < 0)
                                    Prazo encerrado
                                @elseif($project['days_remaining'] === 0)
                                    Último dia
                                @else
                                    {{ $project['days_remaining'] }} dia(s) restante(s)
                                @endif
                            </div>
                        @else
                            <div class="dp-deadline">
                                <i data-lucide="calendar-minus"></i>
                                Sem prazo definido
                            </div>
                        @endif

                        <div class="dp-card-actions">
                            @if($project['status_value'] === 'active')
                                <a
                                    href="{{ route('delivery.register', [
                                        'tenant' => $currentTenant->slug,
                                        'project' => $project['id'],
                                    ]) }}"
                                    class="btn btn-primary"
                                >
                                    <i data-lucide="plus"></i>
                                    Registrar
                                </a>
                            @endif

                            <a
                                href="{{ route('delivery.projects.deliveries', [
                                    'tenant' => $currentTenant->slug,
                                    'project' => $project['id'],
                                ]) }}"
                                class="btn btn-ghost"
                            >
                                <i data-lucide="list-checks"></i>
                                Entregas
                            </a>

                            <a
                                href="{{ route('delivery.projects.producers', [
                                    'tenant' => $currentTenant->slug,
                                    'project' => $project['id'],
                                ]) }}"
                                class="btn btn-ghost"
                            >
                                <i data-lucide="users-round"></i>
                                Produtores
                            </a>

                            <a
                                href="{{ route('delivery.projects.associates.index', [
                                    'tenant' => $currentTenant->slug,
                                    'project' => $project['id'],
                                ]) }}"
                                class="btn btn-ghost"
                                title="Participação e limites"
                            >
                                <i data-lucide="sliders-horizontal"></i>
                                Limites
                            </a>

                            @if($project['status_value'] === 'active')
                                <button
                                    class="btn btn-info"
                                    type="button"
                                    onclick="confirmFinalizeProject(
                                        {{ $project['id'] }},
                                        @js($project['title']),
                                        {{ $project['pending_deliveries'] }}
                                    )"
                                >
                                    <i data-lucide="circle-check-big"></i>
                                    Finalizar
                                </button>
                            @elseif($project['status_value'] === 'awaiting_delivery')
                                <button
                                    class="btn btn-success"
                                    type="button"
                                    onclick="openDeliverToClientModal(
                                        {{ $project['id'] }},
                                        @js($project['title'])
                                    )"
                                >
                                    <i data-lucide="truck"></i>
                                    Entregar ao cliente
                                </button>
                            @endif
                        </div>
                    </footer>
                </article>
            @endforeach

            <div class="dp-no-results" id="dp-no-results">
                <span class="dp-empty-icon">
                    <i data-lucide="search-x"></i>
                </span>

                <div class="dp-empty-title">Nenhum projeto encontrado</div>

                <div class="dp-empty-msg">
                    Altere a busca ou selecione outro filtro.
                </div>
            </div>
        </div>
    @endif

    <div id="modal-start" class="dp-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-start-title">
        <div class="dp-confirm">
            <div class="dp-confirm-icon" style="color:var(--dp-warning)">
                <i data-lucide="circle-play"></i>
            </div>

            <div class="dp-confirm-title" id="modal-start-title">Iniciar projeto?</div>

            <div class="dp-confirm-msg" id="modal-start-msg">
                O projeto será marcado como em execução.
            </div>

            <div class="dp-confirm-footer">
                <button
                    class="btn btn-ghost"
                    type="button"
                    onclick="closeModal('modal-start')"
                >
                    Cancelar
                </button>

                <button class="btn btn-warning" type="button" id="modal-start-btn">
                    <span id="modal-start-spinner" class="dp-spinner" hidden></span>
                    Iniciar projeto
                </button>
            </div>
        </div>
    </div>

    <div id="modal-finalize" class="dp-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-finalize-title">
        <div class="dp-confirm">
            <div class="dp-confirm-icon" style="color:var(--dp-info)">
                <i data-lucide="circle-check-big"></i>
            </div>

            <div class="dp-confirm-title" id="modal-finalize-title">
                Finalizar entregas?
            </div>

            <div class="dp-confirm-msg" id="modal-finalize-msg">
                Confirme para encerrar o período de registros.
            </div>

            <div class="dp-confirm-footer">
                <button
                    class="btn btn-ghost"
                    type="button"
                    onclick="closeModal('modal-finalize')"
                >
                    Cancelar
                </button>

                <button class="btn btn-info" type="button" id="modal-finalize-btn">
                    <span id="modal-finalize-spinner" class="dp-spinner" hidden></span>
                    Finalizar
                </button>
            </div>
        </div>
    </div>

    <div id="modal-deliver" class="dp-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="modal-deliver-title">
        <div class="dp-modal">
            <div class="dp-modal-head">
                <span class="dp-modal-icon" style="color:var(--dp-success)">
                    <i data-lucide="truck"></i>
                </span>

                <div>
                    <div class="dp-modal-title" id="modal-deliver-title">
                        Entregar ao cliente
                    </div>

                    <div class="dp-modal-sub" id="modal-deliver-sub">
                        Informe o cliente e as quantidades.
                    </div>
                </div>
            </div>

            <div class="dp-form-group">
                <label for="deliver-customer">
                    Cliente <span style="color:var(--dp-danger)">*</span>
                </label>

                <select id="deliver-customer">
                    <option value="">Selecionar cliente…</option>

                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}">
                            {{ $customer->trade_name ?: $customer->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="dp-form-group">
                <label for="deliver-date">Data da entrega</label>
                <input type="date" id="deliver-date" value="{{ now()->format('Y-m-d') }}">
            </div>

            <div id="dp-product-rows" class="dp-product-rows"></div>

            <div class="dp-form-group">
                <label for="deliver-notes">Observações</label>
                <textarea
                    id="deliver-notes"
                    placeholder="Anotações sobre a entrega"
                ></textarea>
            </div>

            <div class="dp-modal-footer">
                <button
                    class="btn btn-ghost"
                    type="button"
                    onclick="closeModal('modal-deliver')"
                >
                    Cancelar
                </button>

                <button class="btn btn-success" type="button" id="modal-deliver-btn">
                    <span id="modal-deliver-spinner" class="dp-spinner" hidden></span>
                    <i data-lucide="check"></i>
                    Confirmar entrega
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const TENANT = @json($currentTenant->slug);
    const CSRF = @json(csrf_token());

    let currentStatusFilter = 'all';
    let startProjectId = null;
    let finalizeProjectId = null;
    let deliverProjectId = null;

    function refreshIcons() {
        if (window.lucide?.createIcons) {
            window.lucide.createIcons();
        }
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[character];
        });
    }

    function closeModal(id) {
        document.getElementById(id)?.classList.remove('open');
    }

    function setButtonLoading(button, spinner, loading) {
        button.disabled = loading;

        if (spinner) {
            spinner.hidden = !loading;
        }
    }

    function toast(message, type = 'success') {
        const container = document.getElementById('dp-toasts');
        const toastElement = document.createElement('div');
        const iconName = type === 'success'
            ? 'circle-check-big'
            : type === 'error'
                ? 'circle-alert'
                : 'info';

        toastElement.className = `dp-toast ${type}`;
        toastElement.innerHTML = `
            <span class="dp-toast-icon">
                <i data-lucide="${iconName}"></i>
            </span>
            <span class="dp-toast-message">${escapeHtml(message)}</span>
        `;

        container.appendChild(toastElement);
        refreshIcons();

        window.setTimeout(function () {
            toastElement.style.opacity = '0';
            toastElement.style.transform = 'translateY(8px)';

            window.setTimeout(function () {
                toastElement.remove();
            }, 250);
        }, 4200);
    }

    async function apiPost(url, body = {}) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        const data = await response.json().catch(function () {
            return {
                success: false,
                message: 'O servidor retornou uma resposta inválida.',
            };
        });

        if (!response.ok) {
            throw new Error(
                data.message
                || Object.values(data.errors || {}).flat()[0]
                || 'Não foi possível concluir a operação.'
            );
        }

        return data;
    }

    function applyProjectFilters() {
        const search = (
            document.getElementById('dp-project-search')?.value || ''
        ).trim().toLocaleLowerCase('pt-BR');

        const cards = Array.from(
            document.querySelectorAll('[data-project-card]')
        );

        let visible = 0;

        cards.forEach(function (card) {
            const matchesSearch = !search
                || card.dataset.search.includes(search);

            const matchesStatus = currentStatusFilter === 'all'
                || card.dataset.status === currentStatusFilter;

            const show = matchesSearch && matchesStatus;

            card.hidden = !show;

            if (show) {
                visible += 1;
            }
        });

        const count = document.getElementById('dp-visible-count');
        const noResults = document.getElementById('dp-no-results');

        if (count) {
            count.textContent = String(visible);
        }

        noResults?.classList.toggle('visible', visible === 0);
    }

    document.getElementById('dp-project-search')
        ?.addEventListener('input', applyProjectFilters);

    document.querySelectorAll('[data-status-filter]').forEach(function (button) {
        button.addEventListener('click', function () {
            currentStatusFilter = button.dataset.statusFilter;

            document.querySelectorAll('[data-status-filter]').forEach(function (item) {
                item.classList.toggle('active', item === button);
            });

            applyProjectFilters();
        });
    });

    function confirmStartProject(id, title) {
        startProjectId = id;

        document.getElementById('modal-start-title').textContent =
            `Iniciar: ${title}`;

        document.getElementById('modal-start-msg').textContent =
            'O projeto será marcado como em execução e os registros serão liberados.';

        document.getElementById('modal-start').classList.add('open');
    }

    document.getElementById('modal-start-btn')
        ?.addEventListener('click', async function () {
            const button = this;
            const spinner = document.getElementById('modal-start-spinner');

            setButtonLoading(button, spinner, true);

            try {
                const data = await apiPost(
                    `/${encodeURIComponent(TENANT)}/delivery/projects/${startProjectId}/start`
                );

                closeModal('modal-start');
                toast(data.message || 'Projeto iniciado.');

                window.setTimeout(function () {
                    window.location.reload();
                }, 900);
            } catch (error) {
                toast(error.message || 'Erro ao iniciar o projeto.', 'error');
            } finally {
                setButtonLoading(button, spinner, false);
            }
        });

    function confirmFinalizeProject(id, title, pending) {
        finalizeProjectId = id;

        document.getElementById('modal-finalize-msg').textContent =
            pending > 0
                ? `Existem ${pending} entrega(s) pendente(s). Elas deverão ser processadas ou rejeitadas.`
                : `O período de entregas do projeto "${title}" será finalizado.`;

        document.getElementById('modal-finalize').classList.add('open');
    }

    document.getElementById('modal-finalize-btn')
        ?.addEventListener('click', async function () {
            const button = this;
            const spinner = document.getElementById('modal-finalize-spinner');

            setButtonLoading(button, spinner, true);

            try {
                const data = await apiPost(
                    `/${encodeURIComponent(TENANT)}/delivery/projects/${finalizeProjectId}/finalize`
                );

                closeModal('modal-finalize');
                toast(data.message || 'Entregas finalizadas.');

                window.setTimeout(function () {
                    window.location.reload();
                }, 900);
            } catch (error) {
                toast(error.message || 'Erro ao finalizar as entregas.', 'error');
            } finally {
                setButtonLoading(button, spinner, false);
            }
        });

    function renderProductsLoading() {
        return `
            <div class="dp-loading-products">
                <div class="dp-brand-loader" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
                <span>Carregando produtos…</span>
            </div>
        `;
    }

    function renderProductRows(products) {
        return products.map(function (product) {
            const productName = escapeHtml(product.product_name);
            const productUnit = escapeHtml(product.product_unit);
            const approved = Number(product.approved_qty || 0);
            const stock = Number(product.current_stock || 0);
            const maxDeliverable = Number(product.max_deliverable || 0);
            const productId = Number(product.product_id);

            return `
                <div class="dp-product-row">
                    <div class="dp-product-row-name">${productName}</div>

                    <div class="dp-product-row-meta">
                        Aprovado: ${approved.toFixed(3)} ${productUnit}
                        · Estoque: ${stock.toFixed(3)} ${productUnit}
                    </div>

                    <div class="dp-qty-line">
                        <label>Quantidade</label>

                        <input
                            class="deliver-qty"
                            type="number"
                            step="0.001"
                            min="0"
                            max="${maxDeliverable}"
                            value="${maxDeliverable.toFixed(3)}"
                            data-product="${productId}"
                        >

                        <span class="dp-qty-unit">${productUnit}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    async function openDeliverToClientModal(id, title) {
        deliverProjectId = id;

        document.getElementById('modal-deliver-sub').textContent =
            `Projeto: ${title}`;

        const rows = document.getElementById('dp-product-rows');

        rows.innerHTML = renderProductsLoading();
        document.getElementById('modal-deliver').classList.add('open');

        try {
            const response = await fetch(
                `/${encodeURIComponent(TENANT)}/delivery/projects/${id}/stock-summary`,
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );

            const products = await response.json().catch(function () {
                return null;
            });

            if (!response.ok || !Array.isArray(products)) {
                throw new Error('Não foi possível carregar os produtos.');
            }

            if (!products.length) {
                rows.innerHTML = `
                    <div class="dp-empty" style="min-height:150px">
                        <span class="dp-empty-icon">
                            <i data-lucide="package-x"></i>
                        </span>
                        <div class="dp-empty-title">Nenhum produto disponível</div>
                        <div class="dp-empty-msg">
                            Não existem produtos aprovados com saldo para esta entrega.
                        </div>
                    </div>
                `;

                refreshIcons();
                return;
            }

            rows.innerHTML = renderProductRows(products);
        } catch (error) {
            rows.innerHTML = `
                <div class="dp-empty" style="min-height:150px">
                    <span class="dp-empty-icon" style="color:var(--dp-danger)">
                        <i data-lucide="circle-alert"></i>
                    </span>
                    <div class="dp-empty-title">Erro ao carregar</div>
                    <div class="dp-empty-msg">${escapeHtml(error.message)}</div>
                </div>
            `;

            refreshIcons();
        }
    }

    document.getElementById('modal-deliver-btn')
        ?.addEventListener('click', async function () {
            const button = this;
            const spinner = document.getElementById('modal-deliver-spinner');
            const customerId = document.getElementById('deliver-customer').value;

            if (!customerId) {
                toast('Selecione o cliente para a entrega.', 'error');
                document.getElementById('deliver-customer').focus();
                return;
            }

            const quantities = {};

            document.querySelectorAll('.deliver-qty').forEach(function (input) {
                quantities[input.dataset.product] =
                    Number.parseFloat(input.value) || 0;
            });

            setButtonLoading(button, spinner, true);

            try {
                const data = await apiPost(
                    `/${encodeURIComponent(TENANT)}/delivery/projects/${deliverProjectId}/deliver-to-client`,
                    {
                        delivery_date: document.getElementById('deliver-date').value,
                        customer_id: Number.parseInt(customerId, 10),
                        notes: document.getElementById('deliver-notes').value,
                        quantities,
                    }
                );

                closeModal('modal-deliver');
                toast(data.message || 'Entrega registrada.');

                window.setTimeout(function () {
                    window.location.reload();
                }, 1000);
            } catch (error) {
                toast(error.message || 'Erro ao registrar a entrega.', 'error');
            } finally {
                setButtonLoading(button, spinner, false);
            }
        });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.dp-modal-overlay.open').forEach(function (modal) {
                modal.classList.remove('open');
            });
        }
    });

    refreshIcons();
</script>
@endsection
