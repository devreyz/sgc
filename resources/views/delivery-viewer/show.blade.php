@extends('layouts.bento')

@section('title', 'Acompanhamento do Projeto')
@section('page-title', $project->title)
@section('user-role', 'Visualização')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery-viewer',
        'projects',
        $tenant->slug ?? request()->route('tenant'),
    );
@endphp

@section('content')
<style>
    .watch-shell {
        --watch-green: var(--color-primary, #22c55e);
        --watch-green-dark: var(--color-primary-dark, #16a34a);
        --watch-green-deep: var(--color-primary-deep, #15803d);
        --watch-surface: var(--color-surface, #ffffff);
        --watch-soft: var(--color-surface-soft, #f8faf9);
        --watch-muted: var(--color-surface-muted, #eef4f0);
        --watch-border: var(--color-border, #dce6df);
        --watch-border-strong: var(--color-border-strong, #c8d6cd);
        --watch-text: var(--color-text, #102018);
        --watch-secondary: var(--color-text-secondary, #52645a);
        --watch-faded: var(--color-text-muted, #809087);
        --watch-danger: var(--color-danger, #dc2626);
        --watch-warning: var(--color-warning, #d97706);
        --watch-info: var(--color-info, #0284c7);
        --watch-shadow-sm: 0 5px 18px rgba(15, 35, 24, .055);
        --watch-shadow: 0 12px 34px rgba(15, 35, 24, .075);

        display: grid;
        width: min(100%, 1320px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .85rem;
        margin: 0 auto;
        padding-bottom: 1.25rem;
        color: var(--watch-text);
    }

    .watch-shell *,
    .watch-shell *::before,
    .watch-shell *::after {
        box-sizing: border-box;
    }

    .watch-projectbar {
        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        overflow: visible;
        padding: .78rem .85rem;
        border: 1px solid var(--watch-border);
        border-left: 4px solid var(--watch-green-dark);
        border-radius: 14px;
        background:
            linear-gradient(90deg, rgba(236, 253, 245, .75), rgba(255, 255, 255, .96) 36%),
            var(--watch-surface);
        box-shadow: var(--watch-shadow-sm);
    }

    .watch-back {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border: 1px solid var(--watch-border);
        border-radius: 11px;
        background: var(--watch-surface);
        color: var(--watch-secondary);
        text-decoration: none;
        transition: border-color 150ms ease, color 150ms ease, transform 150ms ease;
    }

    .watch-back:hover {
        border-color: rgba(34, 197, 94, .48);
        color: var(--watch-green-dark);
        transform: translateX(-1px);
    }

    .watch-back svg {
        width: 18px;
        height: 18px;
    }

    .watch-project-copy {
        min-width: 0;
    }

    .watch-project-kicker {
        display: flex;
        align-items: center;
        gap: .38rem;
        color: var(--watch-green-dark);
        font-size: .62rem;
        font-weight: 820;
        letter-spacing: .065em;
        text-transform: uppercase;
    }

    .watch-project-kicker svg {
        width: 13px;
        height: 13px;
    }

    .watch-project-title {
        margin: .14rem 0 0;
        overflow: hidden;
        color: var(--watch-text);
        font-size: clamp(1.02rem, 2vw, 1.35rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.2;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .watch-project-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .35rem .65rem;
        margin-top: .34rem;
        color: var(--watch-secondary);
        font-size: .68rem;
        font-weight: 650;
    }

    .watch-project-meta > span {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }

    .watch-project-meta svg {
        width: 13px;
        height: 13px;
        color: var(--watch-faded);
    }

    .watch-status {
        display: inline-flex;
        min-height: 28px;
        align-items: center;
        gap: .3rem;
        padding: .3rem .55rem;
        border: 1px solid var(--watch-border);
        border-radius: 999px;
        background: var(--watch-surface);
        color: var(--watch-secondary);
        font-size: .61rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .watch-status::before {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: var(--watch-faded);
        content: "";
    }

    .watch-status.is-active {
        border-color: rgba(22, 163, 74, .24);
        background: #ecfdf5;
        color: #047857;
    }

    .watch-status.is-active::before {
        background: #10b981;
    }

    .watch-status.is-warning {
        border-color: rgba(217, 119, 6, .25);
        background: #fffbeb;
        color: #92400e;
    }

    .watch-status.is-warning::before {
        background: #f59e0b;
    }

    .watch-status.is-closed {
        border-color: rgba(100, 116, 139, .24);
        background: #f1f5f9;
        color: #475569;
    }

    .watch-status.is-closed::before {
        background: #64748b;
    }

    .watch-project-actions {
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .watch-icon-btn {
        position: relative;
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: 1px solid var(--watch-border);
        border-radius: 10px;
        background: var(--watch-surface);
        color: var(--watch-secondary);
        cursor: help;
    }

    .watch-icon-btn:hover,
    .watch-icon-btn:focus-visible {
        border-color: rgba(34, 197, 94, .42);
        color: var(--watch-green-dark);
        outline: none;
    }

    .watch-icon-btn svg {
        width: 17px;
        height: 17px;
    }

    .watch-tabs {
        position: sticky;
        z-index: 20;
        top: .45rem;
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .3rem;
        padding: .34rem;
        border: 1px solid var(--watch-border);
        border-radius: 12px;
        background: color-mix(in srgb, var(--watch-surface) 94%, transparent);
        box-shadow: 0 7px 22px rgba(15, 35, 24, .055);
        backdrop-filter: blur(12px);
    }

    .watch-tab {
        display: flex;
        min-width: 0;
        min-height: 44px;
        align-items: center;
        justify-content: center;
        gap: .38rem;
        padding: .48rem .55rem;
        border: 1px solid transparent;
        border-radius: 9px;
        background: transparent;
        color: var(--watch-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .69rem;
        font-weight: 790;
        transition: background 150ms ease, border-color 150ms ease, color 150ms ease;
    }

    .watch-tab svg {
        width: 16px;
        height: 16px;
        flex: 0 0 auto;
    }

    .watch-tab:hover {
        border-color: var(--watch-border);
        background: var(--watch-soft);
        color: var(--watch-text);
    }

    .watch-tab.active {
        border-color: var(--watch-green-dark);
        background: linear-gradient(135deg, var(--watch-green), var(--watch-green-dark));
        color: #fff;
        box-shadow: 0 6px 16px rgba(22, 163, 74, .16);
    }

    .watch-tab-count {
        display: inline-grid;
        min-width: 20px;
        height: 20px;
        place-items: center;
        padding: 0 .25rem;
        border-radius: 999px;
        background: var(--watch-muted);
        color: var(--watch-secondary);
        font-size: .54rem;
        font-weight: 850;
    }

    .watch-tab.active .watch-tab-count {
        background: rgba(255, 255, 255, .18);
        color: #fff;
    }

    .watch-panel[hidden] {
        display: none !important;
    }

    .watch-panel {
        min-width: 0;
    }

    .watch-loading {
        display: grid;
        min-height: 250px;
        place-items: center;
        border: 1px solid var(--watch-border);
        border-radius: 14px;
        background: var(--watch-surface);
        color: var(--watch-secondary);
        font-size: .74rem;
        text-align: center;
    }

    .watch-spinner {
        width: 29px;
        height: 29px;
        margin: 0 auto .65rem;
        border: 3px solid var(--watch-border);
        border-top-color: var(--watch-green-dark);
        border-radius: 50%;
        animation: watch-spin .72s linear infinite;
    }

    @keyframes watch-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .watch-error {
        padding: .85rem;
        border: 1px solid #fecaca;
        border-radius: 11px;
        background: #fff7f7;
        color: #991b1b;
        font-size: .72rem;
        font-weight: 650;
    }

    .watch-summary {
        display: grid;
        grid-template-columns: minmax(285px, .85fr) minmax(0, 1.65fr);
        gap: .75rem;
        align-items: stretch;
    }

    .watch-progress-card,
    .watch-number,
    .watch-card,
    .watch-delivery,
    .watch-note-form,
    .watch-note {
        border: 1px solid var(--watch-border);
        background: var(--watch-surface);
        box-shadow: var(--watch-shadow-sm);
    }

    .watch-progress-card {
        position: relative;
        display: flex;
        min-width: 0;
        justify-content: space-between;
        flex-direction: column;
        overflow: hidden;
        padding: 1rem;
        border-radius: 15px;
    }

    .watch-progress-card::before {
        position: absolute;
        top: 0;
        right: 0;
        left: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--watch-green), var(--watch-green-dark));
        content: "";
    }

    .watch-progress-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
    }

    .watch-progress-label {
        display: flex;
        align-items: center;
        gap: .35rem;
        color: var(--watch-secondary);
        font-size: .67rem;
        font-weight: 750;
    }

    .watch-progress-label svg {
        width: 15px;
        height: 15px;
        color: var(--watch-green-dark);
    }

    .watch-tip {
        display: inline-grid;
        width: 20px;
        height: 20px;
        place-items: center;
        border: 0;
        border-radius: 50%;
        background: var(--watch-muted);
        color: var(--watch-secondary);
        cursor: help;
        font: inherit;
    }

    .watch-tip svg {
        width: 12px;
        height: 12px;
    }

    .watch-progress-percent {
        color: var(--watch-text);
        font-size: clamp(1.45rem, 3vw, 2.1rem);
        font-weight: 880;
        letter-spacing: -.05em;
        line-height: 1;
    }

    .watch-main-meter {
        height: 13px;
        margin: 1.1rem 0 .65rem;
        overflow: hidden;
        border-radius: 999px;
        background: var(--watch-muted);
        box-shadow: inset 0 0 0 1px rgba(148, 163, 184, .12);
    }

    .watch-main-meter span {
        display: block;
        width: 0;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #4ade80, var(--watch-green-dark));
        transition: width 350ms ease;
    }

    .watch-progress-message {
        margin: 0;
        color: var(--watch-secondary);
        font-size: .7rem;
        line-height: 1.55;
    }

    .watch-numbers {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .65rem;
    }

    .watch-number {
        position: relative;
        min-width: 0;
        overflow: hidden;
        padding: .82rem;
        border-radius: 14px;
    }

    .watch-number::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--metric-tone, var(--watch-border));
        content: "";
    }

    .watch-number.is-green {
        --metric-tone: linear-gradient(90deg, #4ade80, var(--watch-green-dark));
    }

    .watch-number.is-blue {
        --metric-tone: linear-gradient(90deg, #38bdf8, var(--watch-info));
    }

    .watch-number.is-warning {
        --metric-tone: linear-gradient(90deg, #fbbf24, var(--watch-warning));
    }

    .watch-number-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .4rem;
    }

    .watch-number-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 11px;
        background: var(--watch-muted);
        color: var(--watch-green-dark);
    }

    .watch-number-icon svg {
        width: 17px;
        height: 17px;
    }

    .watch-number-label {
        margin-top: .62rem;
        color: var(--watch-secondary);
        font-size: .62rem;
        font-weight: 720;
    }

    .watch-number-value {
        margin-top: .18rem;
        overflow: hidden;
        color: var(--watch-text);
        font-size: clamp(.92rem, 2vw, 1.15rem);
        font-weight: 850;
        letter-spacing: -.03em;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .watch-number-hint {
        margin-top: .18rem;
        overflow: hidden;
        color: var(--watch-faded);
        font-size: .58rem;
        line-height: 1.4;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .watch-section {
        margin-top: .85rem;
        overflow: hidden;
        border: 1px solid var(--watch-border);
        border-radius: 15px;
        background: rgba(255, 255, 255, .95);
        box-shadow: var(--watch-shadow);
    }

    .watch-section-head {
        display: flex;
        min-height: 66px;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .72rem .82rem;
        border-bottom: 1px solid var(--watch-border);
        background: linear-gradient(180deg, var(--watch-soft), var(--watch-surface));
    }

    .watch-section-title {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .62rem;
    }

    .watch-section-icon {
        display: grid;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
        background: #ecfdf5;
        color: var(--watch-green-dark);
    }

    .watch-section-icon svg {
        width: 18px;
        height: 18px;
    }

    .watch-section-head h2 {
        margin: 0;
        color: var(--watch-text);
        font-size: .94rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .watch-section-head p {
        margin: .16rem 0 0;
        color: var(--watch-faded);
        font-size: .62rem;
        line-height: 1.35;
    }

    .watch-search-wrap {
        position: relative;
        width: min(310px, 100%);
    }

    .watch-search-wrap > svg {
        position: absolute;
        top: 50%;
        left: .7rem;
        width: 15px;
        height: 15px;
        color: var(--watch-faded);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .watch-search,
    .watch-control {
        width: 100%;
        min-height: 42px;
        border: 1px solid var(--watch-border-strong);
        border-radius: 10px;
        outline: none;
        background: var(--watch-surface);
        color: var(--watch-text);
        font: inherit;
        font-size: .74rem;
        font-weight: 600;
        transition: border-color 150ms ease, box-shadow 150ms ease;
    }

    .watch-search {
        padding: .55rem .68rem .55rem 2.15rem;
    }

    .watch-control {
        padding: .55rem .68rem;
    }

    .watch-search:focus,
    .watch-control:focus {
        border-color: var(--watch-green);
        box-shadow: 0 0 0 3px rgba(34, 197, 94, .12);
    }

    .watch-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: .72rem;
        padding: .78rem;
    }

    .watch-card {
        position: relative;
        min-width: 0;
        overflow: hidden;
        padding: .85rem;
        border-radius: 14px;
        transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
    }

    .watch-card::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 3px;
        background: var(--watch-border);
        content: "";
    }

    .watch-card:hover {
        border-color: rgba(34, 197, 94, .38);
        box-shadow: 0 11px 26px rgba(15, 35, 24, .085);
        transform: translateY(-1px);
    }

    .watch-card:hover::after {
        background: linear-gradient(90deg, var(--watch-green), var(--watch-green-dark));
    }

    .watch-card-top {
        display: flex;
        min-width: 0;
        align-items: flex-start;
        justify-content: space-between;
        gap: .65rem;
    }

    .watch-card-heading {
        min-width: 0;
    }

    .watch-card h3 {
        margin: 0;
        overflow: hidden;
        color: var(--watch-text);
        font-size: .86rem;
        font-weight: 820;
        line-height: 1.35;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .watch-card-sub {
        margin-top: .18rem;
        color: var(--watch-faded);
        font-size: .63rem;
        line-height: 1.45;
    }

    .watch-card-icon {
        display: grid;
        width: 35px;
        height: 35px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 10px;
        background: var(--watch-muted);
        color: var(--watch-green-dark);
    }

    .watch-card-icon svg {
        width: 16px;
        height: 16px;
    }

    .watch-meter {
        height: 9px;
        margin: .72rem 0 .4rem;
        overflow: hidden;
        border-radius: 999px;
        background: var(--watch-muted);
    }

    .watch-meter span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #4ade80, var(--watch-green-dark));
        transition: width 300ms ease;
    }

    .watch-meter.warn span {
        background: linear-gradient(90deg, #fbbf24, var(--watch-warning));
    }

    .watch-meter.done span {
        background: linear-gradient(90deg, #34d399, #047857);
    }

    .watch-values {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .38rem;
        margin-top: .62rem;
    }

    .watch-value {
        min-width: 0;
        padding: .46rem;
        border: 1px solid var(--watch-border);
        border-radius: 9px;
        background: var(--watch-soft);
    }

    .watch-value span {
        display: block;
        overflow: hidden;
        color: var(--watch-secondary);
        font-size: .57rem;
        font-weight: 680;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .watch-value strong {
        display: block;
        margin-top: .16rem;
        overflow: hidden;
        color: var(--watch-text);
        font-size: .72rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .watch-link-card {
        display: block;
        color: inherit;
        text-decoration: none;
    }

    .watch-open {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .6rem;
        margin-top: .72rem;
        padding-top: .62rem;
        border-top: 1px solid var(--watch-border);
        color: var(--watch-green-dark);
        font-size: .66rem;
        font-weight: 820;
    }

    .watch-open svg {
        width: 14px;
        height: 14px;
    }

    .watch-filter {
        display: grid;
        grid-template-columns: minmax(170px, 1fr) minmax(150px, 205px) auto;
        gap: .5rem;
        padding: .75rem .78rem;
        border-bottom: 1px solid var(--watch-border);
        background: var(--watch-soft);
    }

    .watch-button {
        display: inline-flex;
        min-height: 42px;
        align-items: center;
        justify-content: center;
        gap: .38rem;
        padding: .54rem .74rem;
        border: 1px solid var(--watch-green-dark);
        border-radius: 10px;
        background: linear-gradient(135deg, var(--watch-green), var(--watch-green-dark));
        color: #fff;
        cursor: pointer;
        font: inherit;
        font-size: .68rem;
        font-weight: 810;
        box-shadow: 0 7px 16px rgba(22, 163, 74, .14);
        transition: transform 140ms ease, box-shadow 140ms ease;
    }

    .watch-button:hover {
        box-shadow: 0 10px 20px rgba(22, 163, 74, .18);
        transform: translateY(-1px);
    }

    .watch-button:disabled {
        cursor: not-allowed;
        opacity: .48;
        transform: none;
    }

    .watch-button svg {
        width: 15px;
        height: 15px;
    }

    .watch-deliveries {
        display: grid;
        gap: .65rem;
        padding: .78rem;
    }

    .watch-delivery-table-wrap { overflow:auto; padding:.78rem; }
    .watch-delivery-table { width:100%; min-width:840px; border-collapse:separate; border-spacing:0; font-size:.72rem; }
    .watch-delivery-table th { padding:.62rem .58rem; text-align:left; color:var(--watch-secondary); background:var(--watch-soft); border-bottom:1px solid var(--watch-border-strong); font-size:.59rem; letter-spacing:.04em; text-transform:uppercase; }
    .watch-delivery-table td { padding:.62rem .58rem; vertical-align:top; border-bottom:1px solid var(--watch-border); }
    .watch-delivery-table .is-number { text-align:right; white-space:nowrap; }
    .watch-distribution-row td { padding:.45rem .58rem .45rem 2.05rem; background:#f8fbf9; color:var(--watch-secondary); font-size:.68rem; }
    .watch-resource-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.72rem; padding:.78rem; }
    .watch-resource-card { min-width:0; padding:.78rem; border:1px solid var(--watch-border); border-radius:12px; background:var(--watch-surface); }
    .watch-resource-card h3 { margin:0 0 .48rem; font-size:.78rem; }.watch-resource-item { display:flex; justify-content:space-between; gap:.5rem; padding:.5rem 0; border-top:1px solid var(--watch-border); font-size:.67rem; }.watch-resource-item:first-of-type{border-top:0}.watch-resource-item strong{display:block;color:var(--watch-text)}.watch-resource-link{color:var(--watch-green-dark);font-weight:800;text-decoration:none}
    @media (min-width:769px) { .watch-deliveries { display:none; } }

    .watch-delivery {
        min-width: 0;
        overflow: hidden;
        border-radius: 14px;
    }

    .watch-delivery-main {
        display: grid;
        grid-template-columns: minmax(190px, 1.1fr) minmax(260px, .9fr) auto;
        gap: .75rem;
        align-items: center;
        padding: .78rem;
    }

    .watch-delivery h3 {
        margin: 0;
        overflow: hidden;
        color: var(--watch-text);
        font-size: .82rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .watch-delivery p {
        margin: .2rem 0 0;
        color: var(--watch-faded);
        font-size: .62rem;
        line-height: 1.4;
    }

    .watch-badge {
        display: inline-flex;
        min-height: 25px;
        align-items: center;
        padding: .22rem .48rem;
        border-radius: 999px;
        background: var(--watch-muted);
        color: var(--watch-secondary);
        font-size: .57rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .watch-badge.approved {
        background: #dcfce7;
        color: #166534;
    }

    .watch-badge.pending {
        background: #fef3c7;
        color: #92400e;
    }

    .watch-badge.rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .watch-destinations {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        padding: .62rem .78rem;
        border-top: 1px solid var(--watch-border);
        background: var(--watch-soft);
    }

    .watch-destination {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .3rem .44rem;
        border: 1px solid var(--watch-border);
        border-radius: 8px;
        background: var(--watch-surface);
        color: var(--watch-secondary);
        font-size: .58rem;
    }

    .delivery-note-trigger {
        display: inline-flex;
        min-height: 32px;
        align-items: center;
        justify-content: center;
        gap: .3rem;
        margin: 0 .78rem .7rem;
        padding: .38rem .52rem;
        border: 1px solid var(--watch-border);
        border-radius: 8px;
        background: var(--watch-surface);
        color: var(--watch-secondary);
        cursor: pointer;
        font: inherit;
        font-size: .6rem;
        font-weight: 760;
    }

    .watch-more {
        width: calc(100% - 1.56rem);
        margin: 0 .78rem .78rem;
    }

    .watch-empty {
        grid-column: 1 / -1;
        padding: 1.8rem .9rem;
        border: 1px dashed var(--watch-border-strong);
        border-radius: 12px;
        background: var(--watch-soft);
        color: var(--watch-secondary);
        font-size: .68rem;
        line-height: 1.5;
        text-align: center;
    }

    .watch-filter-empty {
        margin: 0 .78rem .78rem;
    }

    .watch-notes {
        display: grid;
        grid-template-columns: minmax(250px, .72fr) minmax(0, 1.28fr);
        gap: .7rem;
        padding: .78rem;
    }

    .watch-note-form,
    .watch-note {
        padding: .78rem;
        border-radius: 13px;
    }

    .watch-note-form textarea {
        min-height: 125px;
        resize: vertical;
    }

    .watch-note-list {
        display: grid;
        align-content: start;
        gap: .6rem;
    }

    .watch-note-meta {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .6rem;
        color: var(--watch-faded);
        font-size: .58rem;
        line-height: 1.35;
    }

    .watch-note p {
        margin: .5rem 0 0;
        color: var(--watch-text);
        font-size: .68rem;
        line-height: 1.55;
        white-space: pre-wrap;
    }

    .watch-delete {
        flex: 0 0 auto;
        border: 0;
        background: transparent;
        color: #b91c1c;
        cursor: pointer;
        font: inherit;
        font-size: .58rem;
        font-weight: 820;
    }


    /*
     * Tooltip global:
     * o elemento é movido para document.body e usa position:fixed.
     * Assim não é cortado por cards, seções ou modais com overflow.
     */
    .watch-floating-tooltip {
        position: fixed;
        z-index: 99999;
        top: 0;
        left: 0;
        display: none;
        width: max-content;
        max-width: min(300px, calc(100vw - 24px));
        padding: .52rem .64rem;
        border: 1px solid rgba(255, 255, 255, .10);
        border-radius: 8px;
        background: #142219;
        color: #fff;
        box-shadow: 0 12px 30px rgba(15, 35, 24, .24);
        font-size: .64rem;
        font-weight: 650;
        line-height: 1.48;
        pointer-events: none;
        text-align: left;
        opacity: 0;
        transform: translateY(4px);
        transition: opacity 120ms ease, transform 120ms ease;
    }

    .watch-floating-tooltip.is-visible {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    .watch-floating-tooltip::after {
        position: absolute;
        left: var(--tooltip-arrow-left, 50%);
        width: 9px;
        height: 9px;
        background: #142219;
        content: "";
        transform: translateX(-50%) rotate(45deg);
    }

    .watch-floating-tooltip.is-above::after {
        bottom: -4px;
    }

    .watch-floating-tooltip.is-below::after {
        top: -4px;
    }

    @media (max-width: 1050px) {
        .watch-summary {
            grid-template-columns: minmax(260px, .8fr) minmax(0, 1.2fr);
        }

        .watch-numbers {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .watch-number:last-child {
            grid-column: span 2;
        }

        .watch-delivery-main {
            grid-template-columns: minmax(180px, 1fr) minmax(250px, 1fr);
        }

        .watch-delivery-main > .watch-badge {
            grid-column: 1 / -1;
            width: max-content;
        }
    }

    @media (max-width: 780px) {
        .watch-projectbar {
            grid-template-columns: auto minmax(0, 1fr);
        }

        .watch-project-actions {
            position: absolute;
            top: .72rem;
            right: .72rem;
        }

        .watch-project-copy {
            padding-right: 2.8rem;
        }

        .watch-tabs {
            display: flex;
            overflow-x: auto;
            scrollbar-width: none;
        }

        .watch-tabs::-webkit-scrollbar {
            display: none;
        }

        .watch-tab {
            min-width: 104px;
            flex: 1 0 104px;
        }

        .watch-summary,
        .watch-notes {
            grid-template-columns: 1fr;
        }

        .watch-numbers {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .watch-number:last-child {
            grid-column: auto;
        }

        .watch-section-head {
            align-items: stretch;
            flex-direction: column;
        }

        .watch-search-wrap {
            width: 100%;
        }

        .watch-filter {
            grid-template-columns: 1fr 1fr;
        }

        .watch-filter .watch-button {
            grid-column: 1 / -1;
        }

        .watch-delivery-main {
            grid-template-columns: 1fr;
        }

        .watch-delivery-main > .watch-badge {
            grid-column: auto;
        }
    }

    @media (max-width: 600px) {
        .watch-shell {
            gap: .7rem;
        }

        .watch-projectbar {
            padding: .68rem;
            border-radius: 12px;
        }

        .watch-back {
            width: 38px;
            height: 38px;
            border-radius: 10px;
        }

        .watch-project-title {
            font-size: 1rem;
        }

        .watch-project-meta {
            gap: .28rem .5rem;
            font-size: .62rem;
        }

        .watch-tabs {
            top: .25rem;
            border-radius: 11px;
        }

        .watch-tab {
            min-width: 88px;
            flex-basis: 88px;
            min-height: 42px;
            padding: .45rem .5rem;
        }

        .watch-tab span:not(.watch-tab-count) {
            display: none;
        }

        .watch-summary {
            gap: .62rem;
        }

        .watch-progress-card {
            padding: .82rem;
            border-radius: 13px;
        }

        .watch-numbers {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .52rem;
        }

        .watch-number,
        .watch-card {
            padding: .72rem;
            border-radius: 12px;
        }

        .watch-number:last-child {
            grid-column: span 2;
        }

        .watch-section {
            border-radius: 13px;
        }

        .watch-section-head {
            min-height: 0;
            padding: .65rem;
        }

        .watch-grid,
        .watch-deliveries,
        .watch-notes {
            grid-template-columns: 1fr;
            gap: .58rem;
            padding: .65rem;
        }
        .watch-resource-grid { grid-template-columns:1fr; padding:.65rem; }
        .watch-delivery-table-wrap { display:none; }

        .watch-filter {
            grid-template-columns: 1fr;
            padding: .65rem;
        }

        .watch-filter .watch-button {
            grid-column: auto;
        }

        .watch-values {
            gap: .3rem;
        }

        .watch-value {
            padding: .4rem;
        }

        .watch-delivery {
            border-radius: 12px;
        }

        .watch-delivery-main {
            padding: .68rem;
        }

        .watch-destinations {
            padding: .55rem .68rem;
        }

        .watch-more {
            width: calc(100% - 1.3rem);
            margin: 0 .65rem .65rem;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<div
    class="watch-shell"
    id="deliveryViewer"
    data-summary-url="{{ route('delivery-viewer.projects.data', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
    data-deliveries-url="{{ route('delivery-viewer.projects.deliveries', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
    data-notes-url="{{ route('delivery-viewer.notes.index', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
    data-note-store-url="{{ route('delivery-viewer.notes.store', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"
>
    <header class="watch-projectbar">
        <a
            class="watch-back"
            href="{{ route('delivery-viewer.index', ['tenant' => $tenant->slug]) }}"
            aria-label="Voltar aos projetos"
            title="Voltar aos projetos"
        >
            <i data-lucide="arrow-left"></i>
        </a>

        <div class="watch-project-copy">
            <div class="watch-project-kicker">
                <i data-lucide="folder-kanban"></i>
                Acompanhamento
            </div>

            <h1 class="watch-project-title" title="{{ $project->title }}">
                {{ $project->title }}
            </h1>

            <div class="watch-project-meta">
                <span>
                    <i data-lucide="calendar-days"></i>
                    <span id="projectPeriod">Carregando período...</span>
                </span>

                <span class="watch-status" id="projectStatus">Carregando</span>
            </div>
        </div>

        <div class="watch-project-actions">
            <button
                class="watch-icon-btn"
                type="button"
                aria-label="Ajuda sobre esta página"
                data-tooltip="Use as abas para consultar o resumo, produtos, associados, entregas e anotações deste projeto."
            >
                <i data-lucide="circle-help"></i>
            </button>
        </div>
    </header>

    <nav class="watch-tabs" role="tablist" aria-label="Dados do projeto">
        <button class="watch-tab active" type="button" role="tab" aria-selected="true" aria-controls="watch-panel-overview" data-panel="overview">
            <i data-lucide="layout-dashboard"></i>
            <span>Visão geral</span>
        </button>

        <button class="watch-tab" type="button" role="tab" aria-selected="false" aria-controls="watch-panel-products" data-panel="products">
            <i data-lucide="package"></i>
            <span>Produtos</span>
            <span class="watch-tab-count" id="productTabCount">—</span>
        </button>

        <button class="watch-tab" type="button" role="tab" aria-selected="false" aria-controls="watch-panel-associates" data-panel="associates">
            <i data-lucide="users-round"></i>
            <span>Associados</span>
            <span class="watch-tab-count" id="associateTabCount">—</span>
        </button>

        <button class="watch-tab" type="button" role="tab" aria-selected="false" aria-controls="watch-panel-deliveries" data-panel="deliveries">
            <i data-lucide="truck"></i>
            <span>Entregas</span>
            <span class="watch-tab-count" id="pendingTabCount">—</span>
        </button>

        <button class="watch-tab" type="button" role="tab" aria-selected="false" aria-controls="watch-panel-documents" data-panel="documents">
            <i data-lucide="files"></i>
            <span>Documentos</span>
        </button>

        <button class="watch-tab" type="button" role="tab" aria-selected="false" aria-controls="watch-panel-notes" data-panel="notes">
            <i data-lucide="notebook-pen"></i>
            <span>Anotações</span>
        </button>
    </nav>

    <div class="watch-loading" id="pageLoading">
        <div>
            <div class="watch-spinner"></div>
            Carregando o projeto...
        </div>
    </div>

    <div class="watch-error" id="pageError" hidden></div>

    <section class="watch-panel" id="watch-panel-overview" role="tabpanel" data-panel-content="overview" hidden>
        <div class="watch-summary">
            <article class="watch-progress-card">
                <div class="watch-progress-head">
                    <div class="watch-progress-label">
                        <i data-lucide="route"></i>
                        Distribuição do recebido

                        <button
                            class="watch-tip"
                            type="button"
                            aria-label="Como o percentual é calculado"
                            data-tooltip="Percentual calculado pela quantidade distribuída dividida pela quantidade recebida."
                        >
                            <i data-lucide="info"></i>
                        </button>
                    </div>

                    <strong class="watch-progress-percent" id="distributionPercent">0%</strong>
                </div>

                <div
                    class="watch-main-meter"
                    role="progressbar"
                    aria-label="Percentual distribuído"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="0"
                    id="distributionMeter"
                >
                    <span id="distributionMeterFill"></span>
                </div>

                <p class="watch-progress-message" id="overviewMessage">
                    Aguardando os dados do projeto.
                </p>
            </article>

            <div class="watch-numbers" id="summaryNumbers"></div>
        </div>

        <section class="watch-section">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon">
                        <i data-lucide="building-2"></i>
                    </span>

                    <div>
                        <h2>Destinos dos produtos</h2>
                        <p>Quantidade já distribuída por cliente.</p>
                    </div>
                </div>
            </header>

            <div class="watch-grid" id="customerGrid"></div>
        </section>
    </section>

    <section class="watch-panel" id="watch-panel-products" role="tabpanel" data-panel-content="products" hidden>
        <section class="watch-section" style="margin-top:0">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon">
                        <i data-lucide="package"></i>
                    </span>

                    <div>
                        <h2>Produtos</h2>
                        <p>Meta, recebido, distribuído e saldo.</p>
                    </div>
                </div>

                <label class="watch-search-wrap">
                    <i data-lucide="search"></i>
                    <input
                        class="watch-search"
                        id="productSearch"
                        type="search"
                        autocomplete="off"
                        placeholder="Buscar produto"
                        aria-label="Buscar produto"
                    >
                </label>
            </header>

            <div class="watch-grid" id="productGrid"></div>

            <div class="watch-empty watch-filter-empty" id="productFilterEmpty" hidden>
                Nenhum produto corresponde à busca.
            </div>
        </section>
    </section>

    <section class="watch-panel" id="watch-panel-associates" role="tabpanel" data-panel-content="associates" hidden>
        <section class="watch-section" style="margin-top:0">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon">
                        <i data-lucide="users-round"></i>
                    </span>

                    <div>
                        <h2>Associados</h2>
                        <p>Participação, limites e movimentação.</p>
                    </div>
                </div>

                <label class="watch-search-wrap">
                    <i data-lucide="search"></i>
                    <input
                        class="watch-search"
                        id="associateSearch"
                        type="search"
                        autocomplete="off"
                        placeholder="Buscar associado"
                        aria-label="Buscar associado"
                    >
                </label>
            </header>

            <div class="watch-grid" id="associateGrid"></div>

            <div class="watch-empty watch-filter-empty" id="associateFilterEmpty" hidden>
                Nenhum associado corresponde à busca.
            </div>
        </section>
    </section>

    <section class="watch-panel" id="watch-panel-deliveries" role="tabpanel" data-panel-content="deliveries" hidden>
        <section class="watch-section" style="margin-top:0">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon">
                        <i data-lucide="truck"></i>
                    </span>

                    <div>
                        <h2>Entregas</h2>
                        <p>Registros, quantidades e destinos.</p>
                    </div>
                </div>
            </header>

            <form class="watch-filter" id="deliveryFilter">
                <input
                    class="watch-control"
                    id="deliverySearch"
                    type="search"
                    autocomplete="off"
                    placeholder="Buscar produto ou associado"
                >

                <select class="watch-control" id="deliveryStatus">
                    <option value="">Todos os status</option>

                    @foreach(\App\Enums\DeliveryStatus::cases() as $status)
                        <option value="{{ $status->value }}">
                            {{ $status->getLabel() }}
                        </option>
                    @endforeach
                </select>

                <button class="watch-button" type="submit">
                    <i data-lucide="search"></i>
                    Buscar
                </button>
            </form>

            <div class="watch-deliveries" id="deliveryList"></div>

            <div class="watch-delivery-table-wrap">
                <table class="watch-delivery-table" aria-label="Tabela de entregas e distribuições">
                    <thead><tr><th>Entrega</th><th>Associado</th><th>Produto</th><th>Data</th><th class="is-number">Recebido</th><th class="is-number">Distribuído</th><th class="is-number">Saldo</th><th>Status / destino</th></tr></thead>
                    <tbody id="deliveryTableBody"></tbody>
                </table>
            </div>

            <button
                class="watch-button watch-more"
                id="loadMoreDeliveries"
                type="button"
                hidden
            >
                Mostrar mais entregas
            </button>
        </section>
    </section>

    <section class="watch-panel" id="watch-panel-documents" role="tabpanel" data-panel-content="documents" hidden>
        <section class="watch-section" style="margin-top:0">
            <header class="watch-section-head"><div class="watch-section-title"><span class="watch-section-icon"><i data-lucide="files"></i></span><div><h2>Documentos do projeto</h2><p>Comprovantes dos associados, cobranças ao cliente e folhas de conferência.</p></div></div><a class="watch-button" href="{{ route('delivery.conference-sheets.index', ['tenant' => $tenant->slug, 'project' => $project->id]) }}"><i data-lucide="clipboard-check"></i> Folhas de conferência</a></header>
            <div class="watch-resource-grid" id="documentGrid"></div>
        </section>
    </section>

    <section class="watch-panel" id="watch-panel-notes" role="tabpanel" data-panel-content="notes" hidden>
        <section class="watch-section" style="margin-top:0">
            <header class="watch-section-head">
                <div class="watch-section-title">
                    <span class="watch-section-icon">
                        <i data-lucide="notebook-pen"></i>
                    </span>

                    <div>
                        <h2>Anotações</h2>
                        <p>Registros internos sobre o andamento.</p>
                    </div>
                </div>
            </header>

            <div class="watch-error" id="noteFeedback" hidden style="margin:.78rem .78rem 0"></div>

            <div class="watch-notes">
                <form class="watch-note-form" id="noteForm">
                    <textarea
                        class="watch-control"
                        id="noteContent"
                        maxlength="1500"
                        required
                        placeholder="Escreva uma anotação..."
                    ></textarea>

                    <button class="watch-button" style="width:100%;margin-top:.5rem" type="submit">
                        <i data-lucide="plus"></i>
                        Adicionar anotação
                    </button>
                </form>

                <div class="watch-note-list" id="noteList"></div>
            </div>
        </section>
    </section>

</div>

<div
    class="watch-floating-tooltip"
    id="watchFloatingTooltip"
    role="tooltip"
    aria-hidden="true"
></div>

<x-delivery.notes-modal />
@endsection

@push('scripts')
<script>
(() => {
    const root = document.getElementById('deliveryViewer');

    if (!root) {
        return;
    }

    const elements = {
        loading: document.getElementById('pageLoading'),
        error: document.getElementById('pageError'),
        projectPeriod: document.getElementById('projectPeriod'),
        projectStatus: document.getElementById('projectStatus'),
        distributionPercent: document.getElementById('distributionPercent'),
        distributionMeter: document.getElementById('distributionMeter'),
        distributionMeterFill: document.getElementById('distributionMeterFill'),
        overviewMessage: document.getElementById('overviewMessage'),
        summaryNumbers: document.getElementById('summaryNumbers'),
        customerGrid: document.getElementById('customerGrid'),
        productGrid: document.getElementById('productGrid'),
        associateGrid: document.getElementById('associateGrid'),
        productSearch: document.getElementById('productSearch'),
        associateSearch: document.getElementById('associateSearch'),
        productFilterEmpty: document.getElementById('productFilterEmpty'),
        associateFilterEmpty: document.getElementById('associateFilterEmpty'),
        productTabCount: document.getElementById('productTabCount'),
        associateTabCount: document.getElementById('associateTabCount'),
        pendingTabCount: document.getElementById('pendingTabCount'),
        deliveryFilter: document.getElementById('deliveryFilter'),
        deliverySearch: document.getElementById('deliverySearch'),
        deliveryStatus: document.getElementById('deliveryStatus'),
        deliveryList: document.getElementById('deliveryList'),
        deliveryTableBody: document.getElementById('deliveryTableBody'),
        documentGrid: document.getElementById('documentGrid'),
        loadMoreDeliveries: document.getElementById('loadMoreDeliveries'),
        noteForm: document.getElementById('noteForm'),
        noteContent: document.getElementById('noteContent'),
        noteFeedback: document.getElementById('noteFeedback'),
        noteList: document.getElementById('noteList'),
    };

    const validPanels = new Set([
        'overview',
        'products',
        'associates',
        'deliveries',
        'documents',
        'notes',
    ]);

    const state = {
        data: null,
        activePanel: 'overview',
        deliveryPage: 1,
        lastDeliveryPage: 1,
        deliveryLoading: false,
        deliveryAbort: null,
        notesLoaded: false,
        notesLoading: false,
        notesAbort: null,
    };

    const csrf =
        document.querySelector('meta[name="csrf-token"]')?.content || '';

    const fmt = value => new Intl.NumberFormat('pt-BR', {
        maximumFractionDigits: 3,
    }).format(Number(value || 0));

    const money = value => new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(Number(value || 0));

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

    const safeClass = value => String(value ?? '')
        .toLowerCase()
        .replace(/[^a-z0-9_-]/g, '');

    const asArray = value => Array.isArray(value) ? value : [];

    const empty = message => `
        <div class="watch-empty">${esc(message)}</div>
    `;

    const refreshIcons = () => window.lucide?.createIcons();

    function errorMessage(body, fallback) {
        if (body?.message) {
            return body.message;
        }

        if (body?.errors && typeof body.errors === 'object') {
            const first = Object
                .values(body.errors)
                .flat()
                .find(Boolean);

            if (first) {
                return String(first);
            }
        }

        return fallback;
    }

    async function getJson(url, options = {}) {
        const {
            headers: customHeaders = {},
            ...requestOptions
        } = options;

        const response = await fetch(url, {
            credentials: 'same-origin',
            ...requestOptions,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                ...customHeaders,
            },
        });

        const raw = await response.text();

        let body = {};

        if (raw) {
            try {
                body = JSON.parse(raw);
            } catch {
                body = {};
            }
        }

        if (!response.ok) {
            throw new Error(
                errorMessage(
                    body,
                    `Não foi possível concluir a operação (${response.status}).`
                )
            );
        }

        return body;
    }

    function appendQuery(url, params) {
        const separator = url.includes('?') ? '&' : '?';
        return `${url}${separator}${params.toString()}`;
    }

    function appendPath(url, segment) {
        const [base, query = ''] = String(url).split('?');
        const normalized = base.replace(/\/+$/, '');
        const finalUrl = `${normalized}/${encodeURIComponent(segment)}`;

        return query ? `${finalUrl}?${query}` : finalUrl;
    }

    function setHash(panel) {
        const url = new URL(window.location.href);

        url.hash = panel === 'overview'
            ? ''
            : panel;

        window.history.replaceState(
            window.history.state,
            '',
            url
        );
    }

    function panelFromHash() {
        const panel = window.location.hash
            .replace(/^#/, '')
            .trim();

        return validPanels.has(panel)
            ? panel
            : 'overview';
    }

    function activatePanel(name, {
        updateHash = true,
        focusTab = false,
    } = {}) {
        const panelName = validPanels.has(name)
            ? name
            : 'overview';

        state.activePanel = panelName;

        root.querySelectorAll('.watch-tab').forEach(button => {
            const active = button.dataset.panel === panelName;

            button.classList.toggle('active', active);
            button.setAttribute(
                'aria-selected',
                active ? 'true' : 'false'
            );

            button.tabIndex = active ? 0 : -1;

            if (active && focusTab) {
                button.focus();
            }
        });

        root
            .querySelectorAll('[data-panel-content]')
            .forEach(panel => {
                panel.hidden =
                    panel.dataset.panelContent !== panelName;
            });

        if (updateHash) {
            setHash(panelName);
        }

        if (
            panelName === 'deliveries'
            && !elements.deliveryList.dataset.loaded
        ) {
            loadDeliveries(true);
        }

        if (panelName === 'notes') {
            loadNotes(false);
        }

        refreshIcons();
    }

    function projectStatusTone(label) {
        const normalized = String(label || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

        if (
            normalized.includes('ativo')
            || normalized.includes('execucao')
            || normalized.includes('andamento')
        ) {
            return 'is-active';
        }

        if (
            normalized.includes('rascunho')
            || normalized.includes('aguard')
            || normalized.includes('pendente')
        ) {
            return 'is-warning';
        }

        return 'is-closed';
    }

    function metricCard({
        icon,
        label,
        value,
        hint = '',
        tooltip = '',
        tone = '',
    }) {
        return `
            <article class="watch-number ${tone}">
                <div class="watch-number-head">
                    <span class="watch-number-icon">
                        <i data-lucide="${icon}"></i>
                    </span>

                    ${tooltip ? `
                        <button
                            class="watch-tip"
                            type="button"
                            aria-label="${esc(tooltip)}"
                            data-tooltip="${esc(tooltip)}"
                        >
                            <i data-lucide="info"></i>
                        </button>
                    ` : ''}
                </div>

                <div class="watch-number-label">
                    ${esc(label)}
                </div>

                <div
                    class="watch-number-value"
                    title="${esc(value)}"
                >
                    ${esc(value)}
                </div>

                ${hint ? `
                    <div
                        class="watch-number-hint"
                        title="${esc(hint)}"
                    >
                        ${esc(hint)}
                    </div>
                ` : ''}
            </article>
        `;
    }

    function normalizeData(data) {
        return {
            project: data?.project || {},
            summary: data?.summary || {},
            customers: asArray(data?.customers),
            products: asArray(data?.products),
            associates: asArray(data?.associates),
            documents: data?.documents || {},
        };
    }

    function renderSummary(rawData) {
        const data = normalizeData(rawData);
        const { project, summary } = data;

        renderDocuments(data.documents);

        const received = Number(summary.received || 0);
        const distributed = Number(summary.distributed || 0);
        const physicalBalance = Number(summary.physical_balance || 0);
        const pending = Number(summary.pending || 0);

        const percent = received > 0
            ? Math.min(
                100,
                Math.max(0, distributed / received * 100)
            )
            : 0;

        const statusLabel =
            project.status_label
            || project.status
            || 'Sem status';

        elements.projectStatus.textContent = statusLabel;

        elements.projectStatus.className =
            `watch-status ${projectStatusTone(statusLabel)}`;

        elements.projectPeriod.textContent =
            `${project.start_date || 'Sem início'} a `
            + `${project.end_date || 'sem prazo final'}`;

        elements.distributionPercent.textContent =
            `${Math.round(percent)}%`;

        elements.distributionMeter.setAttribute(
            'aria-valuenow',
            String(Math.round(percent))
        );

        elements.distributionMeterFill.style.width =
            `${percent}%`;

        elements.overviewMessage.textContent = received > 0
            ? `${fmt(distributed)} distribuídos e `
                + `${fmt(physicalBalance)} ainda aguardando destino.`
            : 'Ainda não existem entregas registradas neste projeto.';

        elements.summaryNumbers.innerHTML =
            metricCard({
                icon: 'package-check',
                label: 'Recebido',
                value: fmt(received),
                hint: 'Entrada física',
                tone: 'is-green',
            })
            + metricCard({
                icon: 'route',
                label: 'Distribuído',
                value: fmt(distributed),
                hint: 'Destino confirmado',
                tone: 'is-blue',
            })
            + metricCard({
                icon: 'users-round',
                label: 'Associados',
                value: fmt(summary.associates),
                hint: 'Com participação',
            })
            + metricCard({
                icon: 'boxes',
                label: 'Produtos',
                value: fmt(summary.products),
                hint: `${pending} entrega(s) pendente(s)`,
                tone: pending > 0 ? 'is-warning' : '',
            })
            + metricCard({
                icon: 'calculator',
                label: 'Limites planeados',
                value: money(summary.planned_limit_value),
                hint: summary.project_ceiling == null
                    ? 'Projeto sem teto financeiro'
                    : `${money(summary.project_budget_remaining)} disponível`,
                tooltip:
                    'Soma dos limites financeiros configurados '
                    + 'para os participantes do projeto.',
            });

        elements.productTabCount.textContent = String(
            Number(summary.products)
            || data.products.length
            || 0
        );

        elements.associateTabCount.textContent = String(
            Number(summary.associates)
            || data.associates.length
            || 0
        );

        elements.pendingTabCount.textContent =
            String(pending);

        elements.customerGrid.innerHTML = data.customers.length
            ? data.customers.map(customer => `
                <article class="watch-card">
                    <div class="watch-card-top">
                        <div class="watch-card-heading">
                            <h3 title="${esc(customer?.name || 'Cliente')}">
                                ${esc(customer?.name || 'Cliente')}
                            </h3>

                            <div class="watch-card-sub">
                                Quantidade distribuída no projeto
                            </div>
                        </div>

                        <span class="watch-card-icon">
                            <i data-lucide="building-2"></i>
                        </span>
                    </div>

                    <div class="watch-values">
                        <div
                            class="watch-value"
                            style="grid-column:1/-1"
                        >
                            <span>Distribuído</span>
                            <strong>${fmt(customer?.quantity)}</strong>
                        </div>
                    </div>
                </article>
            `).join('')
            : empty(
                'Nenhuma distribuição aprovada até o momento.'
            );

        return data;
    }

    function renderDocuments(documents) {
        const section = (title, icon, items, renderItem, emptyText) => `<article class="watch-resource-card"><h3><i data-lucide="${icon}"></i> ${esc(title)}</h3>${items.length ? items.map(renderItem).join('') : `<p class="watch-empty">${esc(emptyText)}</p>`}</article>`;
        const receiptItems = asArray(documents?.receipts);
        const billingItems = asArray(documents?.billings);
        const sheetItems = asArray(documents?.sheets);
        elements.documentGrid.innerHTML = section('Comprovantes', 'file-check-2', receiptItems, item => `<div class="watch-resource-item"><span><strong>N&ordm; ${esc(item.number)}</strong>${esc(item.associate)} · ${esc(item.date || '—')}</span><span>${money(item.total)}</span></div>`, 'Nenhum comprovante gerado.') + section('Cobranças ao cliente', 'receipt-text', billingItems, item => `<div class="watch-resource-item"><span><strong>N&ordm; ${esc(item.number)}</strong>${esc(item.recipient)} · ${esc(item.status)}</span><span>${money(item.total)}</span></div>`, 'Nenhuma cobrança criada.') + section('Folhas de conferência', 'clipboard-check', sheetItems, item => `<a class="watch-resource-item watch-resource-link" href="${esc(item.url)}"><span><strong>${esc(item.number)}</strong>${esc(item.status)} · ${fmt(item.distributions)} distribuições</span><i data-lucide="arrow-up-right"></i></a>`, 'Nenhuma folha criada.');
        refreshIcons();
    }

    function productCard(product) {
        const name = String(product?.name || 'Produto');
        const unit = String(product?.unit || '');
        const target = product?.target;
        const hasTarget = target !== null && target !== undefined;

        const received = Number(product?.received || 0);
        const distributed = Number(product?.distributed || 0);

        const progress = hasTarget
            ? Number(product?.progress || 0)
            : (
                received > 0
                    ? Math.min(
                        100,
                        distributed / received * 100
                    )
                    : 0
            );

        const safeProgress = Math.min(
            100,
            Math.max(0, Number(progress || 0))
        );

        const progressLabel = hasTarget
            ? 'da meta recebida'
            : 'do recebido distribuído';

        const meterTone = safeProgress >= 100
            ? 'done'
            : safeProgress >= 80
                ? 'warn'
                : '';

        const url = product?.url
            ? String(product.url)
            : '#';

        return `
            <a
                class="watch-card watch-link-card"
                href="${esc(url)}"
                data-search="${esc(name.toLocaleLowerCase('pt-BR'))}"
            >
                <div class="watch-card-top">
                    <div class="watch-card-heading">
                        <h3 title="${esc(name)}">${esc(name)}</h3>

                        <div class="watch-card-sub">
                            ${hasTarget
                                ? `Meta: ${fmt(target)} ${esc(unit)}`
                                : 'Sem meta geral definida'}
                        </div>
                    </div>

                    <span class="watch-card-icon">
                        <i data-lucide="package"></i>
                    </span>
                </div>

                <div
                    class="watch-meter ${meterTone}"
                    role="progressbar"
                    aria-label="Progresso de ${esc(name)}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="${Math.round(safeProgress)}"
                >
                    <span style="width:${safeProgress}%"></span>
                </div>

                <div class="watch-card-sub">
                    ${Math.round(safeProgress)}% ${progressLabel}
                </div>

                <div class="watch-values">
                    <div class="watch-value">
                        <span>Recebido</span>
                        <strong>${fmt(received)}</strong>
                    </div>

                    <div class="watch-value">
                        <span>Distribuído</span>
                        <strong>${fmt(distributed)}</strong>
                    </div>

                    <div class="watch-value">
                        <span>${hasTarget ? 'Pode receber' : 'Saldo'}</span>
                        <strong>
                            ${fmt(
                                hasTarget
                                    ? product?.remaining_target
                                    : product?.physical_balance
                            )}
                        </strong>
                    </div>
                </div>

                <div class="watch-open">
                    <span>Ver produto</span>
                    <i data-lucide="arrow-right"></i>
                </div>
            </a>
        `;
    }

    function associateCard(associate) {
        const name = String(associate?.name || 'Associado');

        const subtitle =
            associate?.nickname
            || associate?.registration
            || `${Number(associate?.deliveries_count || 0)} entrega(s)`;

        const progress = Math.min(
            100,
            Math.max(0, Number(associate?.progress || 0))
        );

        const meterTone = progress >= 100
            ? 'done'
            : progress >= 80
                ? 'warn'
                : '';

        const url = associate?.url
            ? String(associate.url)
            : '#';

        return `
            <a
                class="watch-card watch-link-card"
                href="${esc(url)}"
                data-search="${esc(
                    `${name} ${associate?.nickname || ''} `
                    + `${associate?.registration || ''}`
                .toLocaleLowerCase('pt-BR'))}"
            >
                <div class="watch-card-top">
                    <div class="watch-card-heading">
                        <h3 title="${esc(name)}">${esc(name)}</h3>
                        <div class="watch-card-sub">${esc(subtitle)}</div>
                    </div>

                    <span class="watch-card-icon">
                        <i data-lucide="user-round"></i>
                    </span>
                </div>

                ${Number(associate?.maximum || 0) > 0
                    ? `
                        <div
                            class="watch-meter ${meterTone}"
                            role="progressbar"
                            aria-label="Uso dos limites do associado"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow="${Math.round(progress)}"
                        >
                            <span style="width:${progress}%"></span>
                        </div>

                        <div class="watch-card-sub">
                            ${Math.round(progress)}% dos limites utilizados
                        </div>
                    `
                    : `
                        <div
                            class="watch-card-sub"
                            style="margin-top:.65rem"
                        >
                            Sem limites individuais de produto
                        </div>
                    `
                }

                <div class="watch-values">
                    <div class="watch-value">
                        <span>Recebido</span>
                        <strong>${fmt(associate?.received)}</strong>
                    </div>

                    <div class="watch-value">
                        <span>Distribuído</span>
                        <strong>${fmt(associate?.distributed)}</strong>
                    </div>

                    <div class="watch-value">
                        <span>Produtos</span>
                        <strong>
                            ${Number(associate?.limited_products || 0)}
                        </strong>
                    </div>
                </div>

                <div class="watch-open">
                    <span>Ver associado</span>
                    <i data-lucide="arrow-right"></i>
                </div>
            </a>
        `;
    }

    function filterCards(
        inputElement,
        gridElement,
        emptyElement
    ) {
        const term = inputElement.value
            .trim()
            .toLocaleLowerCase('pt-BR');

        const cards = Array.from(
            gridElement.querySelectorAll('[data-search]')
        );

        let visible = 0;

        cards.forEach(card => {
            const show =
                !term
                || card.dataset.search.includes(term);

            card.hidden = !show;

            if (show) {
                visible += 1;
            }
        });

        emptyElement.hidden =
            !term
            || visible > 0
            || cards.length === 0;
    }

    function renderDelivery(delivery) {
        const destinations = asArray(delivery?.destinations);
        const status = safeClass(delivery?.status);

        return `
            <article class="watch-delivery">
                <div class="watch-delivery-main">
                    <div>
                        <h3 title="${esc(delivery?.associate || 'Associado')}">
                            ${esc(delivery?.associate || 'Associado')}
                        </h3>

                        <p>
                            #${Number(delivery?.id || 0)}
                            · ${esc(delivery?.product || 'Produto')}
                            · ${esc(delivery?.date || '')}
                        </p>
                    </div>

                    <div
                        class="watch-values"
                        style="margin-top:0"
                    >
                        <div class="watch-value">
                            <span>Recebido</span>
                            <strong>
                                ${fmt(delivery?.quantity)}
                                ${esc(delivery?.unit || '')}
                            </strong>
                        </div>

                        <div class="watch-value">
                            <span>Distribuído</span>
                            <strong>
                                ${fmt(delivery?.distributed)}
                                ${esc(delivery?.unit || '')}
                            </strong>
                        </div>

                        <div class="watch-value">
                            <span>Saldo</span>
                            <strong>
                                ${fmt(delivery?.balance)}
                                ${esc(delivery?.unit || '')}
                            </strong>
                        </div>
                    </div>

                    <span class="watch-badge ${status}">
                        ${esc(delivery?.status_label || 'Sem status')}
                    </span>
                </div>

                <div class="watch-destinations">
                    ${destinations.length
                        ? destinations.map(item => `
                            <span class="watch-destination">
                                ${esc(item?.customer || 'Destino')}
                                <strong>${fmt(item?.quantity)}</strong>
                            </span>
                        `).join('')
                        : `
                            <span class="watch-destination">
                                Ainda sem distribuição
                            </span>
                        `
                    }
                </div>

                ${delivery?.notes
                    ? `
                        <button
                            type="button"
                            class="delivery-note-trigger"
                            data-delivery-notes="${esc(delivery.notes)}"
                            data-delivery-notes-title="Observações da entrega"
                            data-delivery-notes-meta="${esc(
                                `${delivery?.product || 'Produto'}`
                                + ` · ${delivery?.date || ''}`
                            )}"
                        >
                            <i data-lucide="notebook-text"></i>
                            Observações
                        </button>
                    `
                    : ''
                }
            </article>
        `;
    }

    function renderDeliveryTableRows(delivery) {
        const destinations = asArray(delivery?.destinations);
        const unit = esc(delivery?.unit || '');
        const main = `<tr><td><strong>#${Number(delivery?.id || 0)}</strong></td><td>${esc(delivery?.associate || 'Associado')}</td><td>${esc(delivery?.product || 'Produto')}</td><td>${esc(delivery?.date || '—')}</td><td class="is-number">${fmt(delivery?.quantity)} ${unit}</td><td class="is-number">${fmt(delivery?.distributed)} ${unit}</td><td class="is-number">${fmt(delivery?.balance)} ${unit}</td><td><span class="watch-badge ${safeClass(delivery?.status)}">${esc(delivery?.status_label || '—')}</span></td></tr>`;
        const distributions = destinations.length ? destinations.map(item => `<tr class="watch-distribution-row"><td colspan="4">↳ Distribuição #${Number(item?.id || 0)} · ${esc(item?.customer || 'Destino')} · ${esc(item?.date || '—')} · ${esc(item?.status || '—')}</td><td colspan="2" class="is-number">${fmt(item?.quantity)} ${unit}</td><td colspan="2" class="is-number">${money(item?.gross_value)}</td></tr>`).join('') : `<tr class="watch-distribution-row"><td colspan="8">↳ Nenhuma distribuição registrada para esta entrega.</td></tr>`;
        return main + distributions;
    }

    async function loadDeliveries(reset = false) {
        if (state.deliveryLoading) {
            return;
        }

        if (reset) {
            state.deliveryPage = 1;
            state.deliveryAbort?.abort();
        }

        state.deliveryLoading = true;
        state.deliveryAbort = new AbortController();

        elements.loadMoreDeliveries.disabled = true;

        if (reset) {
            elements.deliveryList.innerHTML = `
                <div class="watch-loading">
                    <div>
                        <div class="watch-spinner"></div>
                        Carregando entregas...
                    </div>
                </div>
            `;
        }

        const params = new URLSearchParams({
            page: String(state.deliveryPage),
            search: elements.deliverySearch.value.trim(),
            status: elements.deliveryStatus.value,
        });

        try {
            const result = await getJson(
                appendQuery(root.dataset.deliveriesUrl, params),
                {
                    signal: state.deliveryAbort.signal,
                }
            );

            const deliveries = Array.isArray(result)
                ? result
                : asArray(result?.data);

            const cards = deliveries
                .map(renderDelivery)
                .join('');
            const tableRows = deliveries.map(renderDeliveryTableRows).join('');

            elements.deliveryList.innerHTML = reset
                ? (
                    cards
                    || empty('Nenhuma entrega encontrada.')
                )
                : elements.deliveryList.innerHTML + cards;
            elements.deliveryTableBody.innerHTML = reset
                ? tableRows
                : elements.deliveryTableBody.innerHTML + tableRows;

            elements.deliveryList.dataset.loaded = '1';

            state.lastDeliveryPage = Number(
                result?.last_page
                ?? result?.meta?.last_page
                ?? 1
            );

            elements.loadMoreDeliveries.hidden =
                state.deliveryPage >= state.lastDeliveryPage
                || deliveries.length === 0;

            refreshIcons();
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            if (!reset) {
                state.deliveryPage = Math.max(
                    1,
                    state.deliveryPage - 1
                );
            }

            const errorMarkup = `
                <div class="watch-error">${esc(error.message)}</div>
            `;

            elements.deliveryList.innerHTML = reset
                ? errorMarkup
                : elements.deliveryList.innerHTML + errorMarkup;
        } finally {
            state.deliveryLoading = false;
            elements.loadMoreDeliveries.disabled = false;
        }
    }

    function noteDeleteUrl(note) {
        if (note?.delete_url) {
            return String(note.delete_url);
        }

        return appendPath(
            root.dataset.notesUrl,
            note?.id || ''
        );
    }

    function renderNote(note) {
        return `
            <article class="watch-note">
                <div class="watch-note-meta">
                    <span>
                        ${esc(note?.author || 'Usuário')}
                        · ${esc(note?.created_at || '')}
                        ${note?.delivery_id
                            ? ` · Entrega #${Number(note.delivery_id)}`
                            : ''}
                    </span>

                    ${note?.can_delete
                        ? `
                            <button
                                class="watch-delete"
                                type="button"
                                data-delete-note="${Number(note?.id || 0)}"
                                data-delete-url="${esc(noteDeleteUrl(note))}"
                            >
                                Remover
                            </button>
                        `
                        : ''
                    }
                </div>

                <p>${esc(note?.content || '')}</p>
            </article>
        `;
    }

    async function loadNotes(force = false) {
        if (state.notesLoading) {
            return;
        }

        if (state.notesLoaded && !force) {
            return;
        }

        state.notesLoading = true;
        state.notesAbort?.abort();
        state.notesAbort = new AbortController();

        if (!state.notesLoaded) {
            elements.noteList.innerHTML = `
                <div class="watch-loading">
                    <div>
                        <div class="watch-spinner"></div>
                        Carregando anotações...
                    </div>
                </div>
            `;
        }

        try {
            const result = await getJson(
                root.dataset.notesUrl,
                {
                    signal: state.notesAbort.signal,
                }
            );

            const notes = Array.isArray(result)
                ? result
                : asArray(result?.data);

            elements.noteList.innerHTML = notes.length
                ? notes.map(renderNote).join('')
                : empty('Nenhuma anotação neste projeto.');

            state.notesLoaded = true;
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            elements.noteList.innerHTML = `
                <div class="watch-error">${esc(error.message)}</div>
            `;
        } finally {
            state.notesLoading = false;
        }
    }

    function showNoteFeedback(message) {
        elements.noteFeedback.textContent = message;
        elements.noteFeedback.hidden = false;
    }

    function clearNoteFeedback() {
        elements.noteFeedback.textContent = '';
        elements.noteFeedback.hidden = true;
    }

    function initializeFloatingTooltip() {
        const tooltip =
            document.getElementById('watchFloatingTooltip');

        let activeTrigger = null;

        if (!tooltip) {
            return;
        }

        document.body.appendChild(tooltip);

        function positionTooltip(trigger) {
            if (
                !trigger
                || !trigger.isConnected
                || !tooltip.classList.contains('is-visible')
            ) {
                return;
            }

            const gap = 10;
            const viewportPadding = 12;
            const triggerRect = trigger.getBoundingClientRect();
            const tooltipRect = tooltip.getBoundingClientRect();

            let left =
                triggerRect.left
                + triggerRect.width / 2
                - tooltipRect.width / 2;

            left = Math.max(
                viewportPadding,
                Math.min(
                    window.innerWidth
                    - tooltipRect.width
                    - viewportPadding,
                    left
                )
            );

            const placeBelow =
                triggerRect.top
                < tooltipRect.height
                + gap
                + viewportPadding;

            let top = placeBelow
                ? triggerRect.bottom + gap
                : triggerRect.top
                    - tooltipRect.height
                    - gap;

            top = Math.max(
                viewportPadding,
                Math.min(
                    window.innerHeight
                    - tooltipRect.height
                    - viewportPadding,
                    top
                )
            );

            const triggerCenter =
                triggerRect.left + triggerRect.width / 2;

            const arrowLeft = Math.max(
                12,
                Math.min(
                    tooltipRect.width - 12,
                    triggerCenter - left
                )
            );

            tooltip.style.left = `${Math.round(left)}px`;
            tooltip.style.top = `${Math.round(top)}px`;

            tooltip.style.setProperty(
                '--tooltip-arrow-left',
                `${Math.round(arrowLeft)}px`
            );

            tooltip.classList.toggle(
                'is-below',
                placeBelow
            );

            tooltip.classList.toggle(
                'is-above',
                !placeBelow
            );
        }

        function showTooltip(trigger) {
            const message = trigger?.dataset?.tooltip;

            if (!message) {
                return;
            }

            if (
                activeTrigger === trigger
                && tooltip.classList.contains('is-visible')
            ) {
                positionTooltip(trigger);
                return;
            }

            activeTrigger?.removeAttribute('aria-describedby');

            activeTrigger = trigger;
            tooltip.textContent = message;
            tooltip.style.display = 'block';
            tooltip.setAttribute('aria-hidden', 'false');

            trigger.setAttribute(
                'aria-describedby',
                tooltip.id
            );

            window.requestAnimationFrame(() => {
                tooltip.classList.add('is-visible');
                positionTooltip(trigger);
            });
        }

        function hideTooltip(trigger = null) {
            if (
                trigger
                && activeTrigger !== trigger
            ) {
                return;
            }

            activeTrigger?.removeAttribute('aria-describedby');
            activeTrigger = null;

            tooltip.classList.remove('is-visible');
            tooltip.setAttribute('aria-hidden', 'true');

            window.setTimeout(() => {
                if (
                    !tooltip.classList.contains('is-visible')
                ) {
                    tooltip.style.display = 'none';
                }
            }, 130);
        }

        document.addEventListener(
            'pointerover',
            event => {
                const trigger =
                    event.target.closest(
                        '#deliveryViewer [data-tooltip]'
                    );

                if (trigger) {
                    showTooltip(trigger);
                }
            }
        );

        document.addEventListener(
            'pointerout',
            event => {
                const trigger =
                    event.target.closest(
                        '#deliveryViewer [data-tooltip]'
                    );

                if (
                    !trigger
                    || trigger.contains(event.relatedTarget)
                ) {
                    return;
                }

                hideTooltip(trigger);
            }
        );

        document.addEventListener(
            'focusin',
            event => {
                const trigger =
                    event.target.closest(
                        '#deliveryViewer [data-tooltip]'
                    );

                if (trigger) {
                    showTooltip(trigger);
                }
            }
        );

        document.addEventListener(
            'focusout',
            event => {
                const trigger =
                    event.target.closest(
                        '#deliveryViewer [data-tooltip]'
                    );

                if (trigger) {
                    hideTooltip(trigger);
                }
            }
        );

        document.addEventListener(
            'click',
            event => {
                const trigger =
                    event.target.closest(
                        '#deliveryViewer [data-tooltip]'
                    );

                if (!trigger) {
                    hideTooltip();
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (
                    window
                        .matchMedia('(hover: none)')
                        .matches
                ) {
                    if (
                        activeTrigger === trigger
                        && tooltip.classList.contains('is-visible')
                    ) {
                        hideTooltip(trigger);
                    } else {
                        showTooltip(trigger);
                    }
                }
            },
            true
        );

        window.addEventListener(
            'resize',
            () => {
                if (activeTrigger) {
                    positionTooltip(activeTrigger);
                }
            }
        );

        window.addEventListener(
            'scroll',
            () => {
                if (activeTrigger) {
                    positionTooltip(activeTrigger);
                }
            },
            true
        );

        window.addEventListener(
            'blur',
            () => hideTooltip()
        );

        document.addEventListener(
            'keydown',
            event => {
                if (event.key === 'Escape') {
                    hideTooltip();
                }
            }
        );
    }

    root.querySelectorAll('.watch-tab').forEach(button => {
        button.addEventListener('click', () => {
            activatePanel(button.dataset.panel);
        });

        button.addEventListener('keydown', event => {
            if (
                !['ArrowLeft', 'ArrowRight', 'Home', 'End']
                    .includes(event.key)
            ) {
                return;
            }

            const tabs = Array.from(
                root.querySelectorAll('.watch-tab')
            );

            const current = tabs.indexOf(button);

            let next = current;

            if (event.key === 'ArrowRight') {
                next = (current + 1) % tabs.length;
            }

            if (event.key === 'ArrowLeft') {
                next = (current - 1 + tabs.length) % tabs.length;
            }

            if (event.key === 'Home') {
                next = 0;
            }

            if (event.key === 'End') {
                next = tabs.length - 1;
            }

            event.preventDefault();

            activatePanel(
                tabs[next].dataset.panel,
                {
                    focusTab: true,
                }
            );
        });
    });

    elements.productSearch.addEventListener(
        'input',
        () => {
            filterCards(
                elements.productSearch,
                elements.productGrid,
                elements.productFilterEmpty
            );
        }
    );

    elements.associateSearch.addEventListener(
        'input',
        () => {
            filterCards(
                elements.associateSearch,
                elements.associateGrid,
                elements.associateFilterEmpty
            );
        }
    );

    elements.deliveryFilter.addEventListener(
        'submit',
        event => {
            event.preventDefault();
            loadDeliveries(true);
        }
    );

    elements.loadMoreDeliveries.addEventListener(
        'click',
        () => {
            if (
                state.deliveryLoading
                || state.deliveryPage >= state.lastDeliveryPage
            ) {
                return;
            }

            state.deliveryPage += 1;
            loadDeliveries(false);
        }
    );

    elements.noteForm.addEventListener(
        'submit',
        async event => {
            event.preventDefault();

            const content =
                elements.noteContent.value.trim();

            if (!content) {
                showNoteFeedback(
                    'Escreva uma anotação antes de adicionar.'
                );

                elements.noteContent.focus();
                return;
            }

            const button =
                event.currentTarget.querySelector('button');

            clearNoteFeedback();
            button.disabled = true;

            try {
                await getJson(
                    root.dataset.noteStoreUrl,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            content,
                        }),
                    }
                );

                elements.noteContent.value = '';
                state.notesLoaded = false;

                await loadNotes(true);
            } catch (error) {
                showNoteFeedback(error.message);
            } finally {
                button.disabled = false;
            }
        }
    );

    elements.noteList.addEventListener(
        'click',
        async event => {
            const button =
                event.target.closest('[data-delete-note]');

            if (!button) {
                return;
            }

            if (button.dataset.confirmed !== '1') {
                button.dataset.confirmed = '1';
                button.textContent = 'Confirmar remoção';

                window.setTimeout(() => {
                    if (!button.isConnected) {
                        return;
                    }

                    button.dataset.confirmed = '0';
                    button.textContent = 'Remover';
                }, 4000);

                return;
            }

            clearNoteFeedback();
            button.disabled = true;

            try {
                await getJson(
                    button.dataset.deleteUrl
                    || appendPath(
                        root.dataset.notesUrl,
                        button.dataset.deleteNote
                    ),
                    {
                        method: 'DELETE',
                    }
                );

                state.notesLoaded = false;
                await loadNotes(true);
            } catch (error) {
                showNoteFeedback(error.message);
                button.disabled = false;
            }
        }
    );

    window.addEventListener(
        'hashchange',
        () => {
            activatePanel(
                panelFromHash(),
                {
                    updateHash: false,
                }
            );
        }
    );

    initializeFloatingTooltip();

    getJson(root.dataset.summaryUrl)
        .then(rawData => {
            const data = renderSummary(rawData);

            state.data = data;

            elements.productGrid.innerHTML = data.products.length
                ? data.products.map(productCard).join('')
                : empty('Nenhum produto movimentado.');

            elements.associateGrid.innerHTML =
                data.associates.length
                    ? data.associates.map(associateCard).join('')
                    : empty(
                        'Nenhum associado vinculado ao projeto.'
                    );

            elements.loading.hidden = true;
            elements.error.hidden = true;

            activatePanel(
                panelFromHash(),
                {
                    updateHash: false,
                }
            );

            refreshIcons();
        })
        .catch(error => {
            elements.loading.hidden = true;
            elements.error.hidden = false;
            elements.error.textContent = error.message;
        });
})();
</script>
@endpush
