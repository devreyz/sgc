@extends('layouts.bento')

@section('title', $project->title)
@section('page-title', $project->title)
@section('page-subtitle', $project->title)

@php($bentoNavigation = \App\Support\PortalNavigation::make('associate', 'projects', request()->route('tenant')))

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
    .aw-shell {
        --aw-green: var(--color-primary, #22c55e);
        --aw-green-dark: var(--color-primary-dark, #16a34a);
        --aw-green-deep: var(--color-primary-deep, #15803d);
        --aw-surface: var(--color-surface, #fff);
        --aw-soft: var(--color-surface-soft, #f8faf9);
        --aw-muted: var(--color-surface-muted, #f1f5f3);
        --aw-border: var(--color-border, #dfe7e2);
        --aw-border-strong: var(--color-border-strong, #cbd8d0);
        --aw-text: var(--color-text, #102018);
        --aw-secondary: var(--color-text-secondary, #52645a);
        --aw-faded: var(--color-text-muted, #839187);
        --aw-danger: var(--color-danger, #ef4444);
        --aw-warning: var(--color-warning, #f59e0b);
        --aw-info: var(--color-info, #0284c7);
        --aw-radius: 20px;
        --aw-radius-sm: 14px;
        --aw-shadow: 0 12px 34px rgba(15, 35, 24, .07);
        width: min(100%, 1320px);
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--aw-text);
    }

    .aw-shell *,
    .aw-shell *::before,
    .aw-shell *::after {
        box-sizing: border-box;
    }

    .aw-hero {
        position: relative;
        display: grid;
        min-height: 198px;
        grid-template-columns: minmax(0, 1.45fr) minmax(260px, .55fr);
        align-items: stretch;
        gap: 1rem;
        margin-bottom: 1rem;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, .28);
        border-radius: 28px;
        background:
            radial-gradient(circle at 78% 12%, rgba(255, 255, 255, .17), transparent 13rem),
            linear-gradient(135deg, var(--aw-green) 0%, var(--aw-green-dark) 54%, var(--aw-green-deep) 100%);
        box-shadow: 0 22px 50px rgba(21, 128, 61, .18);
        color: #fff;
    }

    .aw-hero::before {
        position: absolute;
        inset: 0;
        background:
            linear-gradient(115deg, rgba(255, 255, 255, .08), transparent 38%),
            radial-gradient(circle at 10% 120%, rgba(255, 255, 255, .13), transparent 18rem);
        content: "";
        pointer-events: none;
    }

    .aw-hero-wave {
        position: absolute;
        right: 0;
        bottom: -1px;
        left: 0;
        width: 100%;
        height: 58px;
        color: rgba(255, 255, 255, .10);
        pointer-events: none;
    }

    .aw-hero-main,
    .aw-hero-side {
        position: relative;
        z-index: 2;
    }

    .aw-hero-main {
        display: flex;
        min-width: 0;
        justify-content: center;
        flex-direction: column;
        padding: 1.35rem 1.5rem 2.6rem;
    }

    .aw-back {
        display: inline-flex;
        width: max-content;
        align-items: center;
        gap: .45rem;
        margin-bottom: .9rem;
        padding: .48rem .72rem;
        border: 1px solid rgba(255, 255, 255, .22);
        border-radius: 999px;
        background: rgba(255, 255, 255, .10);
        color: #fff;
        font-size: .74rem;
        font-weight: 750;
        text-decoration: none;
        backdrop-filter: blur(10px);
        transition: background 150ms ease, border-color 150ms ease, transform 150ms ease;
    }

    .aw-back:hover {
        border-color: rgba(255, 255, 255, .38);
        background: rgba(255, 255, 255, .18);
        transform: translateY(-1px);
    }

    .aw-back svg {
        width: 16px;
        height: 16px;
    }

    .aw-eyebrow {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .45rem;
        margin-bottom: .5rem;
    }

    .aw-eyebrow span {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .32rem .58rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, .12);
        color: rgba(255, 255, 255, .88);
        font-size: .66rem;
        font-weight: 760;
    }

    .aw-eyebrow svg {
        width: 13px;
        height: 13px;
    }

    .aw-title {
        max-width: 760px;
        margin: 0;
        overflow-wrap: anywhere;
        font-size: clamp(1.45rem, 3.1vw, 2.45rem);
        font-weight: 850;
        letter-spacing: -.045em;
        line-height: 1.05;
    }

    .aw-subtitle {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .45rem .8rem;
        margin-top: .75rem;
        color: rgba(255, 255, 255, .78);
        font-size: .78rem;
        font-weight: 620;
    }

    .aw-subtitle span {
        display: inline-flex;
        align-items: center;
        gap: .38rem;
    }

    .aw-subtitle svg {
        width: 15px;
        height: 15px;
    }

    .aw-hero-side {
        display: flex;
        justify-content: center;
        flex-direction: column;
        margin: .8rem;
        padding: 1rem;
        border: 1px solid rgba(255, 255, 255, .16);
        border-radius: 22px;
        background: rgba(255, 255, 255, .11);
        backdrop-filter: blur(16px);
    }

    .aw-hero-side-label {
        color: rgba(255, 255, 255, .68);
        font-size: .66rem;
        font-weight: 760;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .aw-hero-side strong {
        display: block;
        margin-top: .35rem;
        overflow: hidden;
        font-size: 1rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .aw-hero-side p {
        margin: .45rem 0 0;
        color: rgba(255, 255, 255, .70);
        font-size: .7rem;
        line-height: 1.5;
    }

    .aw-hero-action {
        display: inline-flex;
        min-height: 40px;
        align-items: center;
        justify-content: center;
        gap: .42rem;
        margin-top: .85rem;
        padding: .55rem .75rem;
        border: 1px solid rgba(255, 255, 255, .20);
        border-radius: 13px;
        background: rgba(255, 255, 255, .14);
        color: #fff;
        cursor: pointer;
        font-size: .7rem;
        font-weight: 780;
        transition: background 150ms ease, transform 150ms ease;
    }

    .aw-hero-action:hover {
        background: rgba(255, 255, 255, .23);
        transform: translateY(-1px);
    }

    .aw-hero-action svg {
        width: 16px;
        height: 16px;
    }

    .aw-nav-wrap {
        position: sticky;
        z-index: 35;
        top: 0;
        margin-bottom: 1rem;
        padding: .2rem 0;
        background: linear-gradient(
            180deg,
            rgba(243, 246, 244, .96) 0%,
            rgba(243, 246, 244, .86) 78%,
            rgba(243, 246, 244, 0) 100%
        );
        backdrop-filter: blur(12px);
    }

    .aw-nav {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: .35rem;
        padding: .38rem;
        border: 1px solid rgba(223, 231, 226, .92);
        border-radius: 18px;
        background: rgba(255, 255, 255, .91);
        box-shadow: var(--aw-shadow);
        backdrop-filter: blur(16px);
    }

    .aw-tab {
        position: relative;
        display: inline-flex;
        min-width: 0;
        min-height: 44px;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        padding: .55rem .65rem;
        border: 0;
        border-radius: 13px;
        background: transparent;
        color: var(--aw-secondary);
        cursor: pointer;
        font-size: .72rem;
        font-weight: 760;
        transition: background 150ms ease, color 150ms ease, transform 150ms ease;
    }

    .aw-tab:hover {
        background: var(--aw-soft);
        color: var(--aw-green-dark);
    }

    .aw-tab.active {
        background: linear-gradient(135deg, var(--aw-green), var(--aw-green-dark));
        color: #fff;
        box-shadow: 0 9px 20px rgba(22, 163, 74, .19);
    }

    .aw-tab:active {
        transform: scale(.98);
    }

    .aw-tab svg {
        width: 17px;
        height: 17px;
        flex: 0 0 auto;
    }

    .aw-content {
        min-height: 360px;
    }

    .aw-section-head {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .85rem;
    }

    .aw-section-head h2 {
        margin: 0;
        color: var(--aw-text);
        font-size: 1.05rem;
        font-weight: 830;
        letter-spacing: -.025em;
    }

    .aw-section-head p {
        margin: .2rem 0 0;
        color: var(--aw-secondary);
        font-size: .72rem;
        line-height: 1.5;
    }

    .aw-section-meta {
        color: var(--aw-faded);
        font-size: .65rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .aw-bento {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: .8rem;
    }

    .aw-card {
        position: relative;
        min-width: 0;
        overflow: hidden;
        border: 1px solid rgba(223, 231, 226, .94);
        border-radius: var(--aw-radius);
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 8px 26px rgba(15, 35, 24, .055);
        backdrop-filter: blur(10px);
    }

    .aw-card-inner {
        padding: 1rem;
    }

    .aw-card-label {
        display: flex;
        align-items: center;
        gap: .42rem;
        color: var(--aw-secondary);
        font-size: .67rem;
        font-weight: 720;
    }

    .aw-card-label svg {
        width: 15px;
        height: 15px;
        color: var(--aw-green-dark);
    }

    .aw-card-value {
        margin-top: .45rem;
        overflow: hidden;
        color: var(--aw-text);
        font-size: clamp(1.05rem, 2vw, 1.45rem);
        font-weight: 850;
        letter-spacing: -.035em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .aw-card-helper {
        margin-top: .28rem;
        color: var(--aw-faded);
        font-size: .64rem;
        font-weight: 600;
        line-height: 1.45;
    }

    .aw-card-sm {
        grid-column: span 3;
    }

    .aw-card-md {
        grid-column: span 4;
    }

    .aw-card-lg {
        grid-column: span 6;
    }

    .aw-card-wide {
        grid-column: span 8;
    }

    .aw-financial-card {
        grid-column: span 6;
        min-height: 184px;
        border-color: transparent;
        background:
            radial-gradient(circle at 88% 5%, rgba(255, 255, 255, .16), transparent 10rem),
            linear-gradient(135deg, #102018 0%, #183c29 52%, #166534 100%);
        color: #fff;
        box-shadow: 0 20px 44px rgba(15, 35, 24, .16);
    }

    .aw-financial-card .aw-card-inner {
        padding: 1.2rem;
    }

    .aw-financial-card .aw-card-label,
    .aw-financial-card .aw-card-helper {
        color: rgba(255, 255, 255, .65);
    }

    .aw-financial-card .aw-card-label svg {
        color: #86efac;
    }

    .aw-financial-card .aw-card-value {
        color: #fff;
        font-size: clamp(1.7rem, 4vw, 2.45rem);
    }

    .aw-financial-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .5rem;
        margin-top: .85rem;
    }

    .aw-financial-mini {
        padding: .65rem;
        border: 1px solid rgba(255, 255, 255, .10);
        border-radius: 13px;
        background: rgba(255, 255, 255, .07);
    }

    .aw-financial-mini span {
        display: block;
        color: rgba(255, 255, 255, .56);
        font-size: .58rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .aw-financial-mini strong {
        display: block;
        margin-top: .25rem;
        overflow: hidden;
        color: #fff;
        font-size: .78rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .aw-progress {
        height: 8px;
        margin-top: .72rem;
        overflow: hidden;
        border-radius: 999px;
        background: rgba(148, 163, 184, .20);
    }

    .aw-progress > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #4ade80, var(--aw-green-dark));
        transition: width 280ms ease;
    }

    .aw-progress.is-warning > span {
        background: linear-gradient(90deg, #fbbf24, #f59e0b);
    }

    .aw-progress.is-danger > span {
        background: linear-gradient(90deg, #fb7185, #ef4444);
    }

    .aw-financial-card .aw-progress {
        background: rgba(255, 255, 255, .13);
    }

    .aw-financial-card .aw-progress > span {
        background: linear-gradient(90deg, #86efac, #4ade80);
    }

    .aw-panel {
        overflow: hidden;
        border: 1px solid rgba(223, 231, 226, .94);
        border-radius: var(--aw-radius);
        background: rgba(255, 255, 255, .94);
        box-shadow: var(--aw-shadow);
    }

    .aw-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .85rem 1rem;
        border-bottom: 1px solid var(--aw-border);
        background: linear-gradient(180deg, rgba(248, 250, 249, .96), rgba(255, 255, 255, .94));
    }

    .aw-panel-head strong {
        color: var(--aw-text);
        font-size: .78rem;
        font-weight: 800;
    }

    .aw-panel-head span {
        color: var(--aw-faded);
        font-size: .63rem;
        font-weight: 650;
    }

    .aw-tools {
        display: flex;
        align-items: center;
        gap: .55rem;
        margin-bottom: .8rem;
    }

    .aw-search-wrap {
        position: relative;
        min-width: 0;
        flex: 1;
    }

    .aw-search-wrap svg {
        position: absolute;
        top: 50%;
        left: .78rem;
        width: 16px;
        height: 16px;
        transform: translateY(-50%);
        color: var(--aw-faded);
        pointer-events: none;
    }

    .aw-input,
    .aw-select {
        width: 100%;
        min-height: 44px;
        border: 1px solid var(--aw-border-strong) !important;
        border-radius: 13px !important;
        background: rgba(255, 255, 255, .96) !important;
        color: var(--aw-text) !important;
        font-size: .72rem !important;
        font-weight: 640;
        transition: border-color 150ms ease, box-shadow 150ms ease, background 150ms ease;
    }

    .aw-input {
        padding: .6rem .75rem .6rem 2.35rem !important;
    }

    .aw-select {
        width: auto;
        min-width: 150px;
        padding: .6rem 2rem .6rem .75rem !important;
    }

    .aw-input:focus,
    .aw-select:focus {
        border-color: var(--aw-green) !important;
        background: #fff !important;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .12) !important;
        outline: none;
    }

    .aw-info {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        margin-bottom: .8rem;
        padding: .78rem .85rem;
        border: 1px solid rgba(2, 132, 199, .16);
        border-radius: 15px;
        background: rgba(239, 246, 255, .92);
        color: #1e3a8a;
        font-size: .7rem;
        line-height: 1.5;
    }

    .aw-info svg {
        width: 18px;
        height: 18px;
        flex: 0 0 auto;
        color: var(--aw-info);
    }

    .aw-list {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .7rem;
    }

    .aw-record {
        display: flex;
        min-width: 0;
        flex-direction: column;
        padding: .9rem;
        border: 1px solid var(--aw-border);
        border-radius: 17px;
        background: rgba(255, 255, 255, .94);
        box-shadow: 0 5px 18px rgba(15, 35, 24, .04);
        transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease;
    }

    .aw-record:hover {
        border-color: rgba(34, 197, 94, .30);
        box-shadow: 0 12px 26px rgba(15, 35, 24, .07);
        transform: translateY(-1px);
    }

    .aw-record-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .65rem;
    }

    .aw-record-title {
        min-width: 0;
    }

    .aw-record-title strong {
        display: block;
        overflow: hidden;
        color: var(--aw-text);
        font-size: .78rem;
        font-weight: 810;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .aw-record-title span {
        display: block;
        margin-top: .15rem;
        color: var(--aw-faded);
        font-size: .62rem;
        font-weight: 620;
    }

    .aw-badge {
        display: inline-flex;
        min-height: 23px;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        padding: .24rem .5rem;
        border-radius: 999px;
        background: #f1f5f9;
        color: #475569;
        font-size: .59rem;
        font-weight: 800;
        line-height: 1;
        white-space: nowrap;
    }

    .aw-badge.approved,
    .aw-badge.paid,
    .aw-badge.completed {
        background: #dcfce7;
        color: #166534;
    }

    .aw-badge.pending,
    .aw-badge.pending_payment,
    .aw-badge.partially_paid,
    .aw-badge.processing {
        background: #fef3c7;
        color: #92400e;
    }

    .aw-badge.rejected,
    .aw-badge.obsolete,
    .aw-badge.cancelled {
        background: #fee2e2;
        color: #991b1b;
    }

    .aw-badge.draft {
        background: #e2e8f0;
        color: #475569;
    }

    .aw-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .42rem;
        margin-top: .72rem;
    }

    .aw-metric {
        min-width: 0;
        padding: .55rem;
        border-radius: 12px;
        background: var(--aw-soft);
    }

    .aw-metric span {
        display: block;
        color: var(--aw-faded);
        font-size: .55rem;
        font-weight: 720;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .aw-metric strong {
        display: block;
        margin-top: .2rem;
        overflow: hidden;
        color: var(--aw-text);
        font-size: .7rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .aw-record-foot {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: .7rem;
        margin-top: auto;
        padding-top: .65rem;
    }

    .aw-record-note {
        display: -webkit-box;
        overflow: hidden;
        color: var(--aw-secondary);
        font-size: .63rem;
        line-height: 1.45;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }

    .aw-record-amount {
        flex: 0 0 auto;
        color: var(--aw-text);
        font-size: .88rem;
        font-weight: 850;
        letter-spacing: -.02em;
        white-space: nowrap;
    }

    .aw-product-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .7rem;
    }

    .aw-product {
        padding: .9rem;
        border: 1px solid var(--aw-border);
        border-radius: 17px;
        background: rgba(255, 255, 255, .95);
        box-shadow: 0 6px 20px rgba(15, 35, 24, .045);
    }

    .aw-product-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .6rem;
    }

    .aw-product-head strong {
        display: block;
        overflow: hidden;
        color: var(--aw-text);
        font-size: .78rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .aw-product-head span {
        display: block;
        margin-top: .12rem;
        color: var(--aw-faded);
        font-size: .61rem;
    }

    .aw-product-price {
        color: var(--aw-green-dark);
        font-size: .72rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .aw-product-numbers {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .42rem;
        margin-top: .72rem;
    }

    .aw-product-number {
        min-width: 0;
        padding: .55rem;
        border-radius: 12px;
        background: var(--aw-soft);
    }

    .aw-product-number span {
        display: block;
        color: var(--aw-faded);
        font-size: .53rem;
        font-weight: 720;
        text-transform: uppercase;
    }

    .aw-product-number strong {
        display: block;
        margin-top: .2rem;
        overflow: hidden;
        color: var(--aw-text);
        font-size: .69rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .aw-product-usage {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .7rem;
        margin-top: .65rem;
        color: var(--aw-secondary);
        font-size: .62rem;
        font-weight: 700;
    }

    .aw-pager {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        margin-top: .85rem;
    }

    .aw-btn {
        display: inline-flex;
        min-height: 40px;
        align-items: center;
        justify-content: center;
        gap: .38rem;
        padding: .52rem .72rem;
        border: 1px solid var(--aw-border);
        border-radius: 12px;
        background: rgba(255, 255, 255, .96);
        color: var(--aw-secondary);
        cursor: pointer;
        font-size: .68rem;
        font-weight: 760;
        text-decoration: none;
        transition: background 150ms ease, border-color 150ms ease, color 150ms ease, transform 150ms ease;
    }

    .aw-btn:hover:not(:disabled) {
        border-color: rgba(34, 197, 94, .35);
        background: var(--aw-soft);
        color: var(--aw-green-dark);
        transform: translateY(-1px);
    }

    .aw-btn:disabled {
        cursor: not-allowed;
        opacity: .42;
    }

    .aw-btn svg {
        width: 15px;
        height: 15px;
    }

    .aw-pager-label {
        min-width: 92px;
        color: var(--aw-secondary);
        font-size: .66rem;
        font-weight: 720;
        text-align: center;
    }

    .aw-state {
        display: flex;
        min-height: 300px;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: .7rem;
        padding: 2rem;
        border: 1px dashed var(--aw-border-strong);
        border-radius: var(--aw-radius);
        background: rgba(255, 255, 255, .72);
        color: var(--aw-secondary);
        text-align: center;
    }

    .aw-state-icon {
        display: grid;
        width: 58px;
        height: 58px;
        place-items: center;
        border-radius: 19px;
        background: var(--aw-muted);
        color: var(--aw-faded);
    }

    .aw-state-icon svg {
        width: 26px;
        height: 26px;
    }

    .aw-state strong {
        color: var(--aw-text);
        font-size: .8rem;
        font-weight: 820;
    }

    .aw-state p {
        max-width: 380px;
        margin: 0;
        color: var(--aw-secondary);
        font-size: .68rem;
        line-height: 1.5;
    }

    .aw-state.error {
        border-color: rgba(239, 68, 68, .24);
        background: rgba(254, 242, 242, .84);
    }

    .aw-state.error .aw-state-icon {
        background: #fee2e2;
        color: #b91c1c;
    }

    .aw-skeleton-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .75rem;
    }

    .aw-skeleton {
        height: 122px;
        overflow: hidden;
        border: 1px solid var(--aw-border);
        border-radius: var(--aw-radius);
        background:
            linear-gradient(90deg, #eef3ef 25%, #f8faf9 50%, #eef3ef 75%);
        background-size: 200% 100%;
        animation: awShimmer 1.25s linear infinite;
    }

    @keyframes awShimmer {
        to { background-position: -200% 0; }
    }

    @media (max-width: 1040px) {
        .aw-hero {
            grid-template-columns: minmax(0, 1fr) 250px;
        }

        .aw-card-sm {
            grid-column: span 4;
        }

        .aw-card-md {
            grid-column: span 6;
        }

        .aw-financial-card {
            grid-column: span 12;
        }

        .aw-product-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 760px) {
        .aw-shell {
            padding-bottom: .5rem;
        }

        .aw-hero {
            min-height: 0;
            grid-template-columns: 1fr;
            border-radius: 24px;
        }

        .aw-hero-main {
            padding: 1rem 1rem 2.5rem;
        }

        .aw-hero-side {
            display: none;
        }

        .aw-title {
            font-size: 1.48rem;
        }

        .aw-subtitle {
            font-size: .69rem;
        }

        .aw-nav-wrap {
            margin-right: -.2rem;
            margin-left: -.2rem;
        }

        .aw-nav {
            display: flex;
            gap: .35rem;
            padding: .35rem;
            overflow-x: auto;
            border-radius: 17px;
            scrollbar-width: none;
            scroll-snap-type: x proximity;
        }

        .aw-nav::-webkit-scrollbar {
            display: none;
        }

        .aw-tab {
            min-width: max-content;
            flex: 0 0 auto;
            padding-right: .75rem;
            padding-left: .75rem;
            scroll-snap-align: start;
        }

        .aw-section-head {
            align-items: flex-start;
            flex-direction: column;
            gap: .25rem;
        }

        .aw-section-meta {
            white-space: normal;
        }

        .aw-bento {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .65rem;
        }

        .aw-card-sm,
        .aw-card-md,
        .aw-card-lg,
        .aw-card-wide {
            grid-column: span 1;
        }

        .aw-financial-card {
            grid-column: 1 / -1;
        }

        .aw-card-inner {
            padding: .85rem;
        }

        .aw-card-value {
            font-size: 1.02rem;
        }

        .aw-financial-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .aw-tools {
            align-items: stretch;
            flex-direction: column;
        }

        .aw-select {
            width: 100%;
        }

        .aw-list,
        .aw-product-grid {
            grid-template-columns: 1fr;
        }

        .aw-skeleton-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .65rem;
        }
    }

    @media (max-width: 420px) {
        .aw-hero-main {
            padding-right: .85rem;
            padding-left: .85rem;
        }

        .aw-eyebrow span:nth-child(n+2) {
            display: none;
        }

        .aw-tab {
            min-height: 42px;
            font-size: .68rem;
        }

        .aw-tab svg {
            width: 16px;
            height: 16px;
        }

        .aw-bento {
            gap: .55rem;
        }

        .aw-card-sm,
        .aw-card-md {
            grid-column: 1 / -1;
        }

        .aw-financial-grid {
            gap: .35rem;
        }

        .aw-financial-mini {
            padding: .52rem;
        }

        .aw-financial-mini strong {
            font-size: .7rem;
        }

        .aw-metrics,
        .aw-product-numbers {
            gap: .35rem;
        }

        .aw-metric,
        .aw-product-number {
            padding: .48rem;
        }

        .aw-record {
            padding: .78rem;
        }
    }
</style>

<main class="aw-shell" id="associate-workspace">
    <section class="aw-hero" aria-labelledby="workspace-title">
        <div class="aw-hero-main">
            <a class="aw-back" href="{{ route('associate.projects', ['tenant' => $tenantSlug]) }}">
                <i data-lucide="arrow-left"></i>
                Voltar aos projetos
            </a>

            <div class="aw-eyebrow">
                <span><i data-lucide="folder-kanban"></i> Projeto de vendas</span>
                @if($projectPeriod)
                    <span><i data-lucide="calendar-days"></i> {{ $projectPeriod }}</span>
                @endif
            </div>

            <h1 class="aw-title" id="workspace-title">{{ $project->title }}</h1>

            <div class="aw-subtitle">
                <span><i data-lucide="user-round"></i> {{ $associate->display_name }}</span>
                @if($projectPeriod)
                    <span><i data-lucide="calendar-range"></i> {{ $projectPeriod }}</span>
                @endif
            </div>
        </div>

        <aside class="aw-hero-side">
            <span class="aw-hero-side-label">Visão do associado</span>
            <strong>{{ $associate->display_name }}</strong>
            <p>Consulte o que pode ser entregue, o que já foi destinado e os valores gerados neste projeto.</p>
            <button class="aw-hero-action" type="button" onclick="awRefresh()">
                <i data-lucide="refresh-cw"></i>
                Atualizar informações
            </button>
        </aside>

        <svg class="aw-hero-wave" viewBox="0 0 1440 110" preserveAspectRatio="none" aria-hidden="true">
            <path fill="currentColor" d="M0,74 C180,118 340,18 532,66 C720,112 874,102 1040,62 C1210,20 1324,42 1440,74 L1440,110 L0,110 Z"></path>
        </svg>
    </section>

    <div class="aw-nav-wrap">
        <nav class="aw-nav" aria-label="Seções do projeto">
            <button class="aw-tab active" type="button" data-section="summary" aria-current="page">
                <i data-lucide="layout-dashboard"></i>
                <span>Resumo</span>
            </button>
            <button class="aw-tab" type="button" data-section="limits">
                <i data-lucide="gauge"></i>
                <span>Limites</span>
            </button>
            <button class="aw-tab" type="button" data-section="deliveries">
                <i data-lucide="package-check"></i>
                <span>Entregas</span>
            </button>
            <button class="aw-tab" type="button" data-section="distributions">
                <i data-lucide="route"></i>
                <span>Destinos</span>
            </button>
            <button class="aw-tab" type="button" data-section="receipts">
                <i data-lucide="receipt-text"></i>
                <span>Comprovantes</span>
            </button>
            <button class="aw-tab" type="button" data-section="payments">
                <i data-lucide="wallet-cards"></i>
                <span>Pagamentos</span>
            </button>
        </nav>
    </div>

    <section class="aw-content" id="aw-content" aria-live="polite">
        <div class="aw-skeleton-grid">
            @for($i = 0; $i < 8; $i++)
                <div class="aw-skeleton"></div>
            @endfor
        </div>
    </section>
</main>

<script>
    const AW_BASE = @json(url('/'.$tenantSlug.'/associate/projects/'.$project->id));

    const awState = {
        section: 'summary',
        page: 1,
        abort: null,
        timer: null,
        filters: {
            deliveries: { search: '', status: '' },
            distributions: { search: '', status: '' },
        },
    };

    const awSections = {
        summary: {
            title: 'Visão geral',
            icon: 'layout-dashboard',
        },
        limits: {
            title: 'Limites e produtos permitidos',
            icon: 'gauge',
        },
        deliveries: {
            title: 'Minhas entregas',
            icon: 'package-check',
        },
        distributions: {
            title: 'Destinos dos produtos',
            icon: 'route',
        },
        receipts: {
            title: 'Comprovantes',
            icon: 'receipt-text',
        },
        payments: {
            title: 'Pagamentos recebidos',
            icon: 'wallet-cards',
        },
    };

    const awMoney = value => Number(value || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });

    const awQty = value => Number(value || 0).toLocaleString('pt-BR', {
        maximumFractionDigits: 3,
    });

    const awEsc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const awBadge = (status, label) => `
        <span class="aw-badge ${awEsc(status)}">${awEsc(label || status || '-')}</span>
    `;

    function awIcons() {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function awClampPercent(value) {
        return Math.max(0, Math.min(100, Number(value || 0)));
    }

    function awProgressClass(percent) {
        const value = Number(percent || 0);

        if (value >= 100) return 'is-danger';
        if (value >= 80) return 'is-warning';

        return '';
    }

    function awSectionHeader(extra = '') {
        const current = awSections[awState.section] || awSections.summary;

        return `
            <header class="aw-section-head">
                <div>
                    <h2>${awEsc(current.title)}</h2>
                </div>
                ${extra ? `<div class="aw-section-meta">${extra}</div>` : ''}
            </header>
        `;
    }

    function awSummaryCard(label, value, helper, icon, size = 'aw-card-sm') {
        return `
            <article class="aw-card ${size}">
                <div class="aw-card-inner">
                    <div class="aw-card-label">
                        <i data-lucide="${icon}"></i>
                        ${awEsc(label)}
                    </div>
                    <div class="aw-card-value">${value}</div>
                    ${helper ? `<div class="aw-card-helper">${helper}</div>` : ''}
                </div>
            </article>
        `;
    }

    function awLoading() {
        return `
            <div class="aw-skeleton-grid">
                ${Array.from({ length: 8 }, () => '<div class="aw-skeleton"></div>').join('')}
            </div>
        `;
    }

    function awEmpty(title, description, icon = 'inbox') {
        return `
            <div class="aw-state">
                <div class="aw-state-icon"><i data-lucide="${icon}"></i></div>
                <strong>${awEsc(title)}</strong>
                <p>${awEsc(description)}</p>
            </div>
        `;
    }

    function awError(message) {
        return `
            <div class="aw-state error">
                <div class="aw-state-icon"><i data-lucide="triangle-alert"></i></div>
                <strong>Não foi possível carregar esta seção</strong>
                <p>${awEsc(message)}</p>
                <button class="aw-btn" type="button" onclick="awRefresh()">
                    <i data-lucide="refresh-cw"></i>
                    Tentar novamente
                </button>
            </div>
        `;
    }

    document.querySelectorAll('.aw-tab[data-section]').forEach(button => {
        button.addEventListener('click', () => awSetSection(button.dataset.section));
    });

    function awSetSection(section, options = {}) {
        if (!awSections[section]) return;

        awState.section = section;
        awState.page = 1;

        document.querySelectorAll('.aw-tab[data-section]').forEach(button => {
            const active = button.dataset.section === section;
            button.classList.toggle('active', active);
            button.setAttribute('aria-current', active ? 'page' : 'false');

            if (active && window.innerWidth < 760) {
                button.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
            }
        });

        if (!options.skipHash) {
            history.replaceState(null, '', `#${section}`);
        }

        awLoad();

        document.getElementById('associate-workspace')?.scrollIntoView({
            behavior: options.instant ? 'auto' : 'smooth',
            block: 'start',
        });
    }

    async function awApi(url) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            signal: awState.abort?.signal,
        });

        const data = await response.json().catch(() => ({
            message: 'O servidor retornou uma resposta inválida.',
        }));

        if (!response.ok) {
            throw new Error(data.message || 'Não foi possível carregar os dados.');
        }

        return data;
    }

    function awBuildParams() {
        const filters = awState.filters[awState.section] || {};
        const params = new URLSearchParams({ page: String(awState.page) });

        if (filters.search) params.set('search', filters.search);
        if (filters.status) params.set('status', filters.status);

        return params.toString();
    }

    async function awLoad() {
        awState.abort?.abort();
        awState.abort = new AbortController();

        const root = document.getElementById('aw-content');
        root.innerHTML = awLoading();
        awIcons();

        try {
            const data = await awApi(
                `${AW_BASE}/data/${awState.section}?${awBuildParams()}`
            );

            awRender(data);
        } catch (error) {
            if (error.name !== 'AbortError') {
                root.innerHTML = awError(error.message);
                awIcons();
            }
        }
    }

    function awRefresh() {
        awLoad();
    }

    function awRender(data) {
        const renderers = {
            summary: awSummary,
            limits: awLimits,
            deliveries: awDeliveries,
            distributions: awDistributions,
            receipts: awReceipts,
            payments: awPayments,
        };

        (renderers[awState.section] || awSummary)(data);
        awIcons();
    }

    function awSummary(data) {
        const percent = awClampPercent(data.financial_percent);
        const rawPercent = Number(data.financial_percent || 0);
        const financialLimit = data.financial_limit === null
            ? 'Sem limite'
            : awMoney(data.financial_limit);

        const financialRemaining = data.financial_remaining === null
            ? 'Livre'
            : awMoney(data.financial_remaining);

        document.getElementById('aw-content').innerHTML = `
            ${awSectionHeader('Dados atualizados ao abrir esta seção')}

            <div class="aw-bento">
                <article class="aw-card aw-financial-card">
                    <div class="aw-card-inner">
                        <div class="aw-card-label">
                            <i data-lucide="gauge"></i>
                            Limite financeiro do projeto
                        </div>
                        <div class="aw-card-value">${financialLimit}</div>
                        <div class="aw-card-helper">
                            ${rawPercent > 0
                                ? `${Math.round(rawPercent)}% do limite utilizado`
                                : 'Acompanhe aqui quanto do limite financeiro já foi consumido.'}
                        </div>

                        <div class="aw-progress ${awProgressClass(rawPercent)}">
                            <span style="width:${percent}%"></span>
                        </div>

                        <div class="aw-financial-grid">
                            <div class="aw-financial-mini">
                                <span>Utilizado</span>
                                <strong>${awMoney(data.financial_consumed)}</strong>
                            </div>
                            <div class="aw-financial-mini">
                                <span>Disponível</span>
                                <strong>${financialRemaining}</strong>
                            </div>
                            <div class="aw-financial-mini">
                                <span>Valor pago</span>
                                <strong>${awMoney(data.paid)}</strong>
                            </div>
                        </div>
                    </div>
                </article>

                ${awSummaryCard(
                    'Quantidade entregue',
                    awQty(data.received_quantity),
                    'Total físico registrado no projeto.',
                    'package-check',
                    'aw-card-sm'
                )}

                ${awSummaryCard(
                    'Quantidade distribuída',
                    awQty(data.distributed_quantity),
                    'Quantidade que já possui destino.',
                    'route',
                    'aw-card-sm'
                )}

                ${awSummaryCard(
                    'Ainda sem destino',
                    awQty(data.undistributed_quantity),
                    'Saldo físico aguardando distribuição.',
                    'package-open',
                    'aw-card-sm'
                )}

                ${awSummaryCard(
                    'Valor bruto',
                    awMoney(data.total_gross),
                    'Soma das distribuições registradas.',
                    'banknote',
                    'aw-card-sm'
                )}

                ${awSummaryCard(
                    'Valor líquido',
                    awMoney(data.total_net),
                    'Valor após taxas e descontos aplicáveis.',
                    'circle-dollar-sign',
                    'aw-card-sm'
                )}

                ${awSummaryCard(
                    'Pagamentos',
                    awMoney(data.paid),
                    'Total já registrado como pago.',
                    'badge-check',
                    'aw-card-sm'
                )}
            </div>
        `;
    }

    function awLimits(data) {
        const summary = data.summary || {};
        const products = data.products || [];

        const cards = products.map(product => {
            const name = product.product || product.product_name || 'Produto';
            const unit = product.unit || product.product_unit || '';
            const maximum = product.maximum_quantity
                ?? product.associate_limit
                ?? product.project_limit;

            const delivered = product.delivered_quantity
                ?? product.associate_delivered
                ?? 0;

            const remaining = product.remaining_quantity
                ?? product.associate_remaining
                ?? product.project_remaining;

            const percent = product.percent
                ?? product.limit_percent
                ?? (Number(maximum) > 0 ? (Number(delivered) / Number(maximum)) * 100 : 0);

            const price = product.reference_unit_price
                ?? product.unit_price
                ?? 0;

            const limitLabel = maximum === null || maximum === undefined
                ? 'Sem teto'
                : `${awQty(maximum)} ${awEsc(unit)}`;

            const remainingLabel = remaining === null || remaining === undefined
                ? 'Livre'
                : `${awQty(remaining)} ${awEsc(unit)}`;

            return `
                <article class="aw-product">
                    <div class="aw-product-head">
                        <div>
                            <strong>${awEsc(name)}</strong>
                            <span>${awEsc(unit || 'Unidade não informada')}</span>
                        </div>
                        <div class="aw-product-price">
                            ${Number(price) > 0 ? awMoney(price) : 'Sem preço'}
                        </div>
                    </div>

                    <div class="aw-product-numbers">
                        <div class="aw-product-number">
                            <span>Limite</span>
                            <strong>${limitLabel}</strong>
                        </div>
                        <div class="aw-product-number">
                            <span>Entregue</span>
                            <strong>${awQty(delivered)} ${awEsc(unit)}</strong>
                        </div>
                        <div class="aw-product-number">
                            <span>Saldo</span>
                            <strong>${remainingLabel}</strong>
                        </div>
                    </div>

                    <div class="aw-product-usage">
                        <span>Utilização do limite</span>
                        <strong>${Math.round(Number(percent || 0))}%</strong>
                    </div>

                    <div class="aw-progress ${awProgressClass(percent)}">
                        <span style="width:${awClampPercent(percent)}%"></span>
                    </div>
                </article>
            `;
        }).join('');

        const note = data.catalog_open
            ? 'Este projeto aceita produtos ativos do catálogo. Limites específicos, quando configurados, continuam sendo respeitados.'
            : 'Estes são os produtos permitidos para você. O sistema considera o menor saldo disponível entre o limite do projeto e o seu limite individual.';

        document.getElementById('aw-content').innerHTML = `
            ${awSectionHeader(`${products.length} ${products.length === 1 ? 'produto permitido' : 'produtos permitidos'}`)}

            <div class="aw-bento" style="margin-bottom:.8rem">
                ${awSummaryCard(
                    'Limite financeiro',
                    summary.financial_limit === null ? 'Sem limite' : awMoney(summary.financial_limit),
                    'Limite total definido para sua participação.',
                    'gauge',
                    'aw-card-md'
                )}

                ${awSummaryCard(
                    'Valor utilizado',
                    awMoney(summary.financial_consumed),
                    'Valor consumido pelas distribuições.',
                    'chart-no-axes-combined',
                    'aw-card-md'
                )}

                ${awSummaryCard(
                    'Saldo financeiro',
                    summary.financial_remaining === null ? 'Livre' : awMoney(summary.financial_remaining),
                    'Valor ainda disponível para novas distribuições.',
                    'wallet-cards',
                    'aw-card-md'
                )}
            </div>

            <div class="aw-info">
                <i data-lucide="${data.catalog_open ? 'package-search' : 'shield-check'}"></i>
                <div>${awEsc(note)}</div>
            </div>

            ${cards
                ? `<div class="aw-product-grid">${cards}</div>`
                : awEmpty(
                    'Nenhum produto disponível',
                    'Ainda não há produtos liberados para entrega neste projeto.',
                    'package-x'
                )}
        `;
    }

    function awTools(section) {
        const filters = awState.filters[section] || { search: '', status: '' };

        return `
            <div class="aw-tools">
                <div class="aw-search-wrap">
                    <i data-lucide="search"></i>
                    <input
                        class="aw-input"
                        id="aw-search"
                        type="search"
                        value="${awEsc(filters.search)}"
                        placeholder="Buscar por produto..."
                        autocomplete="off"
                        oninput="awDebounce()"
                    >
                </div>

                <select class="aw-select" id="aw-status" onchange="awApplyFilters()">
                    <option value="" ${filters.status === '' ? 'selected' : ''}>Todos os status</option>
                    <option value="pending" ${filters.status === 'pending' ? 'selected' : ''}>Pendentes</option>
                    <option value="approved" ${filters.status === 'approved' ? 'selected' : ''}>Aprovadas</option>
                    <option value="rejected" ${filters.status === 'rejected' ? 'selected' : ''}>Rejeitadas</option>
                    <option value="cancelled" ${filters.status === 'cancelled' ? 'selected' : ''}>Canceladas</option>
                </select>
            </div>
        `;
    }

    function awPager(data) {
        const current = Number(data.current_page || 1);
        const last = Number(data.last_page || 1);

        if (last <= 1) return '';

        return `
            <div class="aw-pager">
                <button class="aw-btn" type="button" ${current <= 1 ? 'disabled' : ''} onclick="awGo(${current - 1})">
                    <i data-lucide="chevron-left"></i>
                    Anterior
                </button>

                <span class="aw-pager-label">Página ${current} de ${last}</span>

                <button class="aw-btn" type="button" ${current >= last ? 'disabled' : ''} onclick="awGo(${current + 1})">
                    Próxima
                    <i data-lucide="chevron-right"></i>
                </button>
            </div>
        `;
    }

    function awDeliveries(data) {
        const records = data.data || [];

        const items = records.map(item => `
            <article class="aw-record">
                <div class="aw-record-head">
                    <div class="aw-record-title">
                        <strong>${awEsc(item.product)}</strong>
                        <span>${awEsc(item.date || '-')} · ${awEsc(item.quality || 'Qualidade não informada')}</span>
                    </div>
                    ${awBadge(item.status, item.status_label)}
                </div>

                <div class="aw-metrics">
                    <div class="aw-metric">
                        <span>Entregue</span>
                        <strong>${awQty(item.quantity)} ${awEsc(item.unit)}</strong>
                    </div>
                    <div class="aw-metric">
                        <span>Distribuído</span>
                        <strong>${awQty(item.distributed)} ${awEsc(item.unit)}</strong>
                    </div>
                    <div class="aw-metric">
                        <span>Saldo</span>
                        <strong>${awQty(item.remaining)} ${awEsc(item.unit)}</strong>
                    </div>
                </div>

                <div class="aw-record-foot">
                    <div class="aw-record-note">
                        ${awEsc(item.rejection_reason || item.notes || 'Nenhuma observação informada.')}
                    </div>
                </div>
            </article>
        `).join('');

        document.getElementById('aw-content').innerHTML = `
            ${awSectionHeader(`${records.length} ${records.length === 1 ? 'registro nesta página' : 'registros nesta página'}`)}
            ${awTools('deliveries')}

            ${items
                ? `<div class="aw-list">${items}</div>`
                : awEmpty(
                    'Nenhuma entrega encontrada',
                    'Ajuste os filtros ou aguarde o registro de novas entregas.',
                    'package-search'
                )}

            ${awPager(data)}
        `;
    }

    function awDistributions(data) {
        const records = data.data || [];

        const items = records.map(item => `
            <article class="aw-record">
                <div class="aw-record-head">
                    <div class="aw-record-title">
                        <strong>${awEsc(item.product)}</strong>
                        <span>${awEsc(item.date || '-')} · ${awEsc(item.customer || 'Destino não informado')}</span>
                    </div>
                    ${item.receipt
                        ? '<span class="aw-badge approved">Em comprovante</span>'
                        : '<span class="aw-badge pending">Pendente</span>'}
                </div>

                <div class="aw-metrics">
                    <div class="aw-metric">
                        <span>Quantidade</span>
                        <strong>${awQty(item.quantity)} ${awEsc(item.unit)}</strong>
                    </div>
                    <div class="aw-metric">
                        <span>Preço unitário</span>
                        <strong>${awMoney(item.unit_price)}</strong>
                    </div>
                    <div class="aw-metric">
                        <span>Valor bruto</span>
                        <strong>${awMoney(item.gross)}</strong>
                    </div>
                </div>

                <div class="aw-record-foot">
                    <div class="aw-record-note">
                        ${item.receipt
                            ? `Comprovante: ${awEsc(item.receipt)}`
                            : 'Esta distribuição ainda não foi incluída em um comprovante.'}
                    </div>
                    <div class="aw-record-amount">${awMoney(item.gross)}</div>
                </div>
            </article>
        `).join('');

        document.getElementById('aw-content').innerHTML = `
            ${awSectionHeader(`${records.length} ${records.length === 1 ? 'destino nesta página' : 'destinos nesta página'}`)}

            <div class="aw-info">
                <i data-lucide="info"></i>
                <div>As distribuições mostram para onde seus produtos foram destinados e são a base dos valores dos comprovantes.</div>
            </div>

            ${awTools('distributions')}

            ${items
                ? `<div class="aw-list">${items}</div>`
                : awEmpty(
                    'Nenhuma distribuição encontrada',
                    'Ainda não há destinos registrados para os filtros selecionados.',
                    'route-off'
                )}

            ${awPager(data)}
        `;
    }

    function awReceipts(data) {
        const records = data.data || [];

        const items = records.map(item => `
            <article class="aw-record">
                <div class="aw-record-head">
                    <div class="aw-record-title">
                        <strong>Comprovante ${awEsc(item.number)}</strong>
                        <span>${awEsc(item.date || 'Data não informada')}</span>
                    </div>
                    ${awBadge(item.status, item.status_label)}
                </div>

                <div class="aw-metrics">
                    <div class="aw-metric">
                        <span>Bruto</span>
                        <strong>${awMoney(item.gross)}</strong>
                    </div>
                    <div class="aw-metric">
                        <span>Taxas</span>
                        <strong>${awMoney(item.fees)}</strong>
                    </div>
                    <div class="aw-metric">
                        <span>Líquido</span>
                        <strong>${awMoney(item.net)}</strong>
                    </div>
                </div>

                <div class="aw-record-foot">
                    <div class="aw-record-note">
                        ${item.current_receipt
                            ? `Documento atual: ${awEsc(item.current_receipt)}`
                            : `Valor pago: ${awMoney(item.paid)}`}
                    </div>

                    ${item.download_url
                        ? `<a class="aw-btn" href="${awEsc(item.download_url)}">
                            <i data-lucide="download"></i>
                            Baixar
                           </a>`
                        : '<span class="aw-badge obsolete">Histórico</span>'}
                </div>
            </article>
        `).join('');

        document.getElementById('aw-content').innerHTML = `
            ${awSectionHeader(`${records.length} ${records.length === 1 ? 'comprovante nesta página' : 'comprovantes nesta página'}`)}

            ${items
                ? `<div class="aw-list">${items}</div>`
                : awEmpty(
                    'Nenhum comprovante',
                    'Os comprovantes gerados para este projeto aparecerão aqui.',
                    'receipt-text'
                )}

            ${awPager(data)}
        `;
    }

    function awPayments(data) {
        const records = data.data || [];

        const items = records.map(item => `
            <article class="aw-record">
                <div class="aw-record-head">
                    <div class="aw-record-title">
                        <strong>${awEsc(item.receipt || 'Pagamento')}</strong>
                        <span>${awEsc(item.date || 'Data não informada')}</span>
                    </div>
                    <span class="aw-badge paid">Pago</span>
                </div>

                <div class="aw-record-foot" style="margin-top:.85rem">
                    <div>
                        <div class="aw-card-label">
                            <i data-lucide="credit-card"></i>
                            ${awEsc(item.method || 'Método não informado')}
                        </div>
                    </div>
                    <div class="aw-record-amount">${awMoney(item.amount)}</div>
                </div>
            </article>
        `).join('');

        document.getElementById('aw-content').innerHTML = `
            ${awSectionHeader(`${records.length} ${records.length === 1 ? 'pagamento nesta página' : 'pagamentos nesta página'}`)}

            ${items
                ? `<div class="aw-list">${items}</div>`
                : awEmpty(
                    'Nenhum pagamento registrado',
                    'Os pagamentos vinculados aos comprovantes aparecerão aqui.',
                    'wallet-cards'
                )}

            ${awPager(data)}
        `;
    }

    function awDebounce() {
        clearTimeout(awState.timer);

        awState.timer = setTimeout(() => {
            awApplyFilters();
        }, 350);
    }

    function awApplyFilters() {
        const filters = awState.filters[awState.section];

        if (!filters) return;

        filters.search = document.getElementById('aw-search')?.value || '';
        filters.status = document.getElementById('aw-status')?.value || '';
        awState.page = 1;
        awLoad();
    }

    function awGo(page) {
        awState.page = Math.max(1, Number(page || 1));
        awLoad();

        document.querySelector('.aw-section-head')?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    }

    const initialSection = location.hash.replace('#', '');

    awSetSection(awSections[initialSection] ? initialSection : 'summary', {
        skipHash: !awSections[initialSection],
        instant: true,
    });

    awIcons();
</script>
@endsection
