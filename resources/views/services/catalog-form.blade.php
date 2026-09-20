@extends('layouts.bento')

@section('title', 'Novo serviço')
@section('page-title', 'Configurar serviço')
@section('user-role', 'Gestão de serviços')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'services',
        'catalog',
        $tenantSlug
    );
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .service-builder {
        --sb-green: var(--ws-green, #219653);
        --sb-green-soft: #edf8f2;
        --sb-green-border: #cce8d7;

        --sb-blue: var(--ws-blue, #3478d4);
        --sb-blue-soft: #edf4ff;
        --sb-blue-border: #cfe0f7;

        --sb-violet: var(--ws-purple, #8a4bd2);
        --sb-violet-soft: #f5efff;
        --sb-violet-border: #e1d2f4;

        --sb-amber: var(--ws-amber, #c38418);
        --sb-amber-soft: #fff7e8;
        --sb-amber-border: #f0dcae;

        --sb-red: var(--ws-red, #cf5050);
        --sb-red-soft: #fff0f0;
        --sb-red-border: #efcaca;

        --sb-cyan: #168eae;
        --sb-cyan-soft: #ecf8fb;
        --sb-cyan-border: #cae8ef;

        --sb-text: #17211d;
        --sb-text-2: #59655f;
        --sb-muted: #89938e;
        --sb-border: #dde5e0;
        --sb-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 980px);
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--sb-text);
    }

    .service-builder *,
    .service-builder *::before,
    .service-builder *::after {
        box-sizing: border-box;
    }

    .service-builder button,
    .service-builder input,
    .service-builder select,
    .service-builder textarea {
        font: inherit;
    }

    /* =========================================================
       HEADER
       ========================================================= */

    .sb-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .75rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--sb-border);
        border-radius: 12px;
        background: #fff;
    }

    .sb-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .sb-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--sb-violet-soft);
        color: var(--sb-violet);
        font-size: 1rem;
    }

    .sb-head-copy {
        min-width: 0;
    }

    .sb-head-copy small {
        display: block;
        color: var(--sb-muted);
        font-size: .61rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .sb-head-copy h1 {
        margin: .06rem 0 0;
        color: var(--sb-text);
        font-size: clamp(1.02rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .sb-head-copy p {
        margin: .12rem 0 0;
        color: var(--sb-muted);
        font-size: .66rem;
        line-height: 1.35;
    }

    .sb-head-step {
        display: inline-flex;
        min-height: 31px;
        gap: .24rem;
        align-items: center;
        padding: .26rem .42rem;
        border-radius: 7px;
        background: var(--sb-soft);
        color: var(--sb-text-2);
        font-size: .59rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .sb-head-step i {
        color: var(--sb-violet);
        font-size: .68rem;
    }

    /* =========================================================
       STEPPER
       ========================================================= */

    .sb-stepper {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--sb-border);
        border-radius: 11px;
        background: #fff;
        scroll-behavior: smooth;
        overscroll-behavior-inline: contain;
        scrollbar-width: thin;
        scrollbar-color:
            #cfd8d2
            transparent;
    }

    .sb-stepper::-webkit-scrollbar {
        height: 5px;
    }

    .sb-stepper::-webkit-scrollbar-track {
        background: transparent;
    }

    .sb-stepper::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: #cfd8d2;
    }

    .sb-step {
        position: relative;
        display: grid;
        min-width: 0;
        gap: .17rem;
        padding: .58rem .56rem;
        border: 0;
        border-right: 1px solid var(--sb-border);
        background: #fff;
        color: var(--sb-muted);
        cursor: pointer;
        text-align: left;
    }

    .sb-step:last-child {
        border-right: 0;
    }

    .sb-step::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 2px;
        background: transparent;
        content: "";
    }

    .sb-step-index {
        display: flex;
        gap: .3rem;
        align-items: center;
        min-width: 0;
    }

    .sb-step-number {
        display: grid;
        width: 24px;
        height: 24px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 7px;
        background: var(--sb-soft);
        color: var(--sb-muted);
        font-size: .56rem;
        font-weight: 820;
    }

    .sb-step strong {
        overflow: hidden;
        color: inherit;
        font-size: .6rem;
        font-weight: 780;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sb-step small {
        overflow: hidden;
        padding-left: 1.88rem;
        color: var(--sb-muted);
        font-size: .48rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sb-step.active {
        color: var(--sb-violet);
        background: var(--sb-violet-soft);
    }

    .sb-step.active::after {
        background: var(--sb-violet);
    }

    .sb-step.active .sb-step-number {
        background: var(--sb-violet);
        color: #fff;
    }

    .sb-step.done {
        color: var(--sb-green);
    }

    .sb-step.done .sb-step-number {
        background: var(--sb-green-soft);
        color: var(--sb-green);
    }

    .sb-step:focus-visible {
        z-index: 2;
        outline: 2px solid var(--sb-violet);
        outline-offset: -2px;
    }

    /* =========================================================
       PANELS
       ========================================================= */

    .sb-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--sb-border);
        border-radius: 12px;
        background: #fff;
    }

    .sb-panel[hidden] {
        display: none !important;
    }

    .sb-panel-head {
        --tone: var(--sb-blue);
        --soft: var(--sb-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .46rem;
        align-items: center;
        min-height: 56px;
        padding: .62rem .68rem;
        border-bottom: 1px solid var(--sb-border);
    }

    .sb-panel.identification .sb-panel-head {
        --tone: var(--sb-violet);
        --soft: var(--sb-violet-soft);
    }

    .sb-panel.data .sb-panel-head {
        --tone: var(--sb-blue);
        --soft: var(--sb-blue-soft);
    }

    .sb-panel.receivable .sb-panel-head {
        --tone: var(--sb-green);
        --soft: var(--sb-green-soft);
    }

    .sb-panel.provider .sb-panel-head {
        --tone: var(--sb-amber);
        --soft: var(--sb-amber-soft);
    }

    .sb-panel.review .sb-panel-head {
        --tone: var(--sb-cyan);
        --soft: var(--sb-cyan-soft);
    }

    .sb-panel-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--soft);
        color: var(--tone);
        font-size: .82rem;
    }

    .sb-panel-title {
        min-width: 0;
    }

    .sb-panel-title h2,
    .sb-panel-title p {
        margin: 0;
    }

    .sb-panel-title h2 {
        color: var(--sb-text);
        font-size: .77rem;
        font-weight: 830;
    }

    .sb-panel-title p {
        margin-top: .05rem;
        color: var(--sb-muted);
        font-size: .58rem;
        line-height: 1.4;
    }

    .sb-panel-body {
        display: grid;
        gap: .72rem;
        padding: .72rem;
    }

    .sb-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .65rem;
    }

    .sb-field {
        display: grid;
        min-width: 0;
        gap: .28rem;
    }

    .sb-field.full {
        grid-column: 1 / -1;
    }

    .sb-label {
        display: flex;
        gap: .28rem;
        align-items: center;
        color: var(--sb-text-2);
        font-size: .62rem;
        font-weight: 750;
    }

    .sb-label i {
        color: var(--sb-muted);
        font-size: .68rem;
    }

    .sb-note {
        margin-left: auto;
        color: var(--sb-muted);
        font-size: .51rem;
        font-weight: 680;
    }

    .sb-control {
        width: 100%;
        min-width: 0;
        min-height: 42px;
        padding: .48rem .55rem;
        border: 1px solid var(--sb-border);
        border-radius: 8px;
        outline: 0;
        background: #fff;
        color: var(--sb-text);
        font-size: .7rem;
    }

    textarea.sb-control {
        min-height: 92px;
        resize: vertical;
    }

    .sb-control:focus {
        border-color: var(--sb-blue);
        box-shadow: 0 0 0 3px var(--sb-blue-soft);
    }

    .sb-control:disabled {
        cursor: not-allowed;
        background: var(--sb-soft);
        color: var(--sb-muted);
        opacity: .78;
    }

    .sb-help {
        margin: -.04rem 0 0;
        color: var(--sb-muted);
        font-size: .54rem;
        line-height: 1.45;
    }

    /* =========================================================
       TOGGLES
       ========================================================= */

    .sb-toggle-row {
        display: flex;
        min-width: 0;
        gap: .6rem;
        align-items: center;
        justify-content: space-between;
        padding: .58rem .62rem;
        border: 1px solid var(--sb-border);
        border-radius: 9px;
        background: var(--sb-soft);
    }

    .sb-toggle-copy {
        min-width: 0;
    }

    .sb-toggle-copy strong,
    .sb-toggle-copy small {
        display: block;
    }

    .sb-toggle-copy strong {
        color: var(--sb-text);
        font-size: .65rem;
        font-weight: 790;
    }

    .sb-toggle-copy small {
        margin-top: .03rem;
        color: var(--sb-muted);
        font-size: .53rem;
        line-height: 1.4;
    }

    .sb-switch {
        position: relative;
        display: inline-flex;
        width: 42px;
        height: 24px;
        flex: 0 0 auto;
        cursor: pointer;
    }

    .sb-switch input {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        opacity: 0;
    }

    .sb-switch-track {
        position: absolute;
        inset: 0;
        border-radius: 999px;
        background: #cfd8d2;
        transition:
            background .15s ease,
            box-shadow .15s ease;
    }

    .sb-switch-track::after {
        position: absolute;
        top: 3px;
        left: 3px;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .16);
        content: "";
        transition: transform .15s ease;
    }

    .sb-switch input:checked + .sb-switch-track {
        background: var(--sb-green);
    }

    .sb-switch input:checked + .sb-switch-track::after {
        transform: translateX(18px);
    }

    .sb-switch input:focus-visible + .sb-switch-track {
        box-shadow: 0 0 0 3px var(--sb-blue-soft);
    }

    /* =========================================================
       PRESET / FIELD PREVIEW
       ========================================================= */

    .sb-preset-box {
        display: grid;
        gap: .35rem;
        padding: .58rem .62rem;
        border: 1px solid var(--sb-violet-border);
        border-radius: 9px;
        background: var(--sb-violet-soft);
    }

    .sb-preset-box strong {
        color: var(--sb-violet);
        font-size: .63rem;
        font-weight: 790;
    }

    .sb-preset-box p {
        margin: 0;
        color: var(--sb-text-2);
        font-size: .58rem;
        line-height: 1.45;
    }

    .sb-field-preview {
        display: grid;
        gap: .4rem;
    }

    .sb-preview-empty {
        display: grid;
        min-height: 110px;
        place-items: center;
        padding: .8rem;
        border: 1px dashed var(--sb-border);
        border-radius: 9px;
        color: var(--sb-muted);
        font-size: .61rem;
        text-align: center;
    }

    .sb-preview-item {
        display: grid;
        min-width: 0;
        grid-template-columns: 30px minmax(0, 1fr) auto;
        gap: .4rem;
        align-items: center;
        padding: .48rem .52rem;
        border: 1px solid var(--sb-border);
        border-radius: 8px;
        background: #fff;
    }

    .sb-preview-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 7px;
        background: var(--sb-blue-soft);
        color: var(--sb-blue);
        font-size: .7rem;
    }

    .sb-preview-copy {
        min-width: 0;
    }

    .sb-preview-copy strong,
    .sb-preview-copy small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sb-preview-copy strong {
        color: var(--sb-text);
        font-size: .63rem;
        font-weight: 770;
    }

    .sb-preview-copy small {
        margin-top: .02rem;
        color: var(--sb-muted);
        font-size: .5rem;
    }

    .sb-preview-phase {
        display: inline-flex;
        min-height: 27px;
        align-items: center;
        padding: .2rem .34rem;
        border-radius: 7px;
        background: var(--sb-soft);
        color: var(--sb-text-2);
        font-size: .52rem;
        font-weight: 720;
        white-space: nowrap;
    }

    /* =========================================================
       FINANCE SECTION
       ========================================================= */

    .sb-finance-fields {
        display: grid;
        gap: .65rem;
    }

    .sb-finance-fields.disabled {
        opacity: .52;
    }

    .sb-finance-hint {
        display: flex;
        gap: .35rem;
        align-items: flex-start;
        padding: .5rem .56rem;
        border-radius: 8px;
        background: var(--sb-soft);
        color: var(--sb-text-2);
        font-size: .56rem;
        line-height: 1.45;
    }

    .sb-finance-hint i {
        margin-top: .06rem;
        color: var(--sb-muted);
        font-size: .65rem;
    }

    /* =========================================================
       REVIEW
       ========================================================= */

    .sb-review {
        display: grid;
        gap: .55rem;
    }

    .sb-review-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--sb-border);
        border-radius: 9px;
    }

    .sb-review-item {
        display: grid;
        min-width: 0;
        gap: .05rem;
        padding: .58rem .62rem;
        border-bottom: 1px solid var(--sb-border);
    }

    .sb-review-item:nth-child(odd) {
        border-right: 1px solid var(--sb-border);
    }

    .sb-review-item:nth-last-child(-n + 2) {
        border-bottom: 0;
    }

    .sb-review-item small {
        color: var(--sb-muted);
        font-size: .51rem;
    }

    .sb-review-item strong {
        overflow: hidden;
        color: var(--sb-text);
        font-size: .64rem;
        font-weight: 790;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* =========================================================
       EXEMPLO DE VALORES
       ========================================================= */

    .sb-simulation {
        display: grid;
        min-width: 0;
        gap: .58rem;
        padding: .65rem;
        border: 1px solid var(--sb-blue-border);
        border-radius: 10px;
        background: #fbfdff;
    }

    .sb-simulation-head {
        display: flex;
        min-width: 0;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
    }

    .sb-simulation-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .4rem;
        align-items: center;
    }

    .sb-simulation-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--sb-blue-soft);
        color: var(--sb-blue);
        font-size: .74rem;
    }

    .sb-simulation-title-copy {
        min-width: 0;
    }

    .sb-simulation-title-copy strong,
    .sb-simulation-title-copy small {
        display: block;
    }

    .sb-simulation-title-copy strong {
        color: var(--sb-text);
        font-size: .66rem;
        font-weight: 810;
    }

    .sb-simulation-title-copy small {
        margin-top: .02rem;
        color: var(--sb-muted);
        font-size: .52rem;
        line-height: 1.35;
    }

    .sb-simulation-quantity {
        display: flex;
        flex: 0 0 auto;
        gap: .3rem;
        align-items: center;
    }

    .sb-simulation-quantity label {
        color: var(--sb-muted);
        font-size: .52rem;
        font-weight: 700;
    }

    .sb-simulation-quantity input {
        width: 78px;
        min-height: 34px;
        padding: .32rem .4rem;
        border: 1px solid var(--sb-border);
        border-radius: 7px;
        outline: 0;
        background: #fff;
        color: var(--sb-text);
        font-size: .64rem;
        font-weight: 760;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .sb-simulation-quantity input:focus {
        border-color: var(--sb-blue);
        box-shadow: 0 0 0 3px var(--sb-blue-soft);
    }

    .sb-simulation-unit {
        max-width: 76px;
        overflow: hidden;
        color: var(--sb-muted);
        font-size: .52rem;
        font-weight: 680;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sb-simulation-flow {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            26px
            minmax(0, 1fr)
            26px
            minmax(0, 1fr);
        gap: .3rem;
        align-items: stretch;
    }

    .sb-simulation-node {
        --tone: var(--sb-text-2);
        --soft: var(--sb-soft);

        display: grid;
        min-width: 0;
        align-content: center;
        gap: .08rem;
        padding: .55rem;
        border-radius: 8px;
        background: var(--soft);
    }

    .sb-simulation-node.customer {
        --tone: var(--sb-green);
        --soft: var(--sb-green-soft);
    }

    .sb-simulation-node.organization {
        --tone: var(--sb-blue);
        --soft: var(--sb-blue-soft);
    }

    .sb-simulation-node.provider {
        --tone: var(--sb-amber);
        --soft: var(--sb-amber-soft);
    }

    .sb-simulation-node small {
        color: var(--tone);
        font-size: .49rem;
        font-weight: 760;
    }

    .sb-simulation-node strong {
        overflow: hidden;
        color: var(--sb-text);
        font-size: .72rem;
        font-weight: 850;
        font-variant-numeric: tabular-nums;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sb-simulation-node span {
        overflow: hidden;
        color: var(--sb-muted);
        font-size: .48rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sb-simulation-arrow {
        display: grid;
        place-items: center;
        color: #b6c2bb;
        font-size: .7rem;
    }

    .sb-simulation-result {
        display: flex;
        min-width: 0;
        gap: .5rem;
        align-items: center;
        justify-content: space-between;
        padding-top: .48rem;
        border-top: 1px solid var(--sb-border);
    }

    .sb-simulation-result-copy {
        min-width: 0;
    }

    .sb-simulation-result-copy strong,
    .sb-simulation-result-copy small {
        display: block;
    }

    .sb-simulation-result-copy strong {
        color: var(--sb-text);
        font-size: .6rem;
        font-weight: 780;
    }

    .sb-simulation-result-copy small {
        margin-top: .02rem;
        color: var(--sb-muted);
        font-size: .5rem;
        line-height: 1.4;
    }

    .sb-simulation-net {
        flex: 0 0 auto;
        color: var(--sb-blue);
        font-size: .72rem;
        font-weight: 850;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .sb-simulation-net.negative {
        color: var(--sb-red);
    }

    .sb-simulation-warning {
        display: none;
        gap: .32rem;
        align-items: flex-start;
        padding: .48rem .52rem;
        border: 1px solid var(--sb-amber-border);
        border-radius: 8px;
        background: var(--sb-amber-soft);
        color: #805b1a;
        font-size: .52rem;
        line-height: 1.45;
    }

    .sb-simulation-warning.show {
        display: flex;
    }

    .sb-simulation-warning i {
        margin-top: .05rem;
        flex: 0 0 auto;
    }

    .sb-publish {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .65rem;
        align-items: center;
        padding: .62rem;
        border: 1px solid var(--sb-amber-border);
        border-radius: 9px;
        background: var(--sb-amber-soft);
    }

    .sb-publish-copy {
        min-width: 0;
    }

    .sb-publish-copy strong,
    .sb-publish-copy small {
        display: block;
    }

    .sb-publish-copy strong {
        color: #825c16;
        font-size: .65rem;
        font-weight: 800;
    }

    .sb-publish-copy small {
        margin-top: .03rem;
        color: #8a6a31;
        font-size: .53rem;
        line-height: 1.4;
    }

    /* =========================================================
       ACTIONS
       ========================================================= */

    .sb-panel-actions {
        display: flex;
        min-width: 0;
        gap: .45rem;
        align-items: center;
        justify-content: space-between;
        padding: .62rem .68rem;
        border-top: 1px solid var(--sb-border);
        background: #fbfdfc;
    }

    .sb-actions-right {
        display: flex;
        gap: .42rem;
        margin-left: auto;
    }

    .sb-action {
        display: inline-flex;
        min-height: 39px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .62rem;
        border-radius: 8px;
        cursor: pointer;
        font: inherit;
        font-size: .68rem;
        font-weight: 790;
    }

    .sb-action.secondary {
        border: 1px solid var(--sb-border);
        background: #fff;
        color: var(--sb-text-2);
    }

    .sb-action.primary {
        border: 1px solid var(--sb-green);
        background: var(--sb-green);
        color: #fff;
    }

    .sb-action.final {
        border-color: var(--sb-violet);
        background: var(--sb-violet);
    }

    .sb-action:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       RESPONSIVE
       ========================================================= */

    @media (max-width: 760px) {
        .sb-stepper {
            display: flex;
            overflow-x: auto;
            scroll-snap-type: x proximity;
        }

        .sb-step {
            min-width: 150px;
            flex: 0 0 auto;
            scroll-snap-align: start;
        }

        .sb-fields {
            grid-template-columns: 1fr;
        }

        .sb-field.full {
            grid-column: auto;
        }

        .sb-review-grid {
            grid-template-columns: 1fr;
        }

        .sb-simulation-head {
            align-items: flex-start;
        }

        .sb-simulation-flow {
            grid-template-columns: 1fr;
        }

        .sb-simulation-arrow {
            height: 20px;
            transform: rotate(90deg);
        }

        .sb-simulation-result {
            align-items: flex-end;
        }

        .sb-review-item,
        .sb-review-item:nth-child(odd),
        .sb-review-item:nth-last-child(-n + 2) {
            border-right: 0;
            border-bottom: 1px solid var(--sb-border);
        }

        .sb-review-item:last-child {
            border-bottom: 0;
        }
    }

    @media (max-width: 560px) {
        .service-builder {
            gap: .58rem;
        }

        .sb-head {
            padding: .62rem .66rem;
        }

        .sb-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .sb-head-icon {
            width: 36px;
            height: 36px;
        }

        .sb-head-copy p {
            display: none;
        }

        .sb-head-step {
            font-size: .54rem;
        }

        .sb-panel-title p {
            display: none;
        }

        .sb-panel-body {
            padding: .62rem;
        }

        .sb-control {
            min-height: 44px;
            font-size: 16px;
        }

        .sb-panel-actions {
            position: sticky;
            z-index: 20;
            bottom: 0;
            padding-bottom:
                max(.62rem, env(safe-area-inset-bottom));
        }

        .sb-action {
            min-height: 42px;
        }

        .sb-actions-right {
            flex: 1;
        }

        .sb-actions-right .sb-action {
            flex: 1;
        }

        .sb-action.secondary[data-previous] span {
            display: none;
        }

        .sb-publish {
            grid-template-columns: 1fr auto;
        }
    }
</style>

<form
    id="service-wizard"
    class="service-builder"
    method="post"
    action="{{ route(
        'services.catalog.store',
        $tenantSlug
    ) }}"
>
    @csrf

    <header class="sb-head">
        <div class="sb-head-main">
            <span
                class="sb-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-wrench"></i>
            </span>

            <div class="sb-head-copy">
                <small>Catálogo</small>

                <h1>Novo serviço</h1>

                <p>
                    Defina o serviço, regras de execução e valores.
                </p>
            </div>
        </div>

        <span class="sb-head-step">
            <i class="ph-fill ph-list-numbers"></i>
            Etapa
            <strong id="current-step-number">1</strong>
            de 5
        </span>
    </header>

    <nav
        class="sb-stepper"
        aria-label="Etapas da configuração"
    >
        @foreach([
            [
                'title' => 'Serviço',
                'subtitle' => 'Identificação',
                'icon' => 'ph-wrench',
            ],
            [
                'title' => 'Dados',
                'subtitle' => 'Execução',
                'icon' => 'ph-list-checks',
            ],
            [
                'title' => 'Cobrança',
                'subtitle' => 'Cliente',
                'icon' => 'ph-arrow-circle-down-left',
            ],
            [
                'title' => 'Prestador',
                'subtitle' => 'Remuneração',
                'icon' => 'ph-arrow-circle-up-right',
            ],
            [
                'title' => 'Finalizar',
                'subtitle' => 'Revisão',
                'icon' => 'ph-check-circle',
            ],
        ] as $index => $step)
            <button
                class="
                    sb-step
                    {{ $index === 0 ? 'active' : '' }}
                "
                type="button"
                data-go-step="{{ $index }}"
                aria-label="Ir para etapa {{ $index + 1 }}: {{ $step['title'] }}"
            >
                <span class="sb-step-index">
                    <span class="sb-step-number">
                        {{ $index + 1 }}
                    </span>

                    <strong>{{ $step['title'] }}</strong>
                </span>

                <small>{{ $step['subtitle'] }}</small>
            </button>
        @endforeach
    </nav>

    {{-- ======================================================
         ETAPA 1
         ====================================================== --}}

    <section
        class="sb-panel identification"
        data-step="0"
    >
        <header class="sb-panel-head">
            <span
                class="sb-panel-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-wrench"></i>
            </span>

            <div class="sb-panel-title">
                <h2>Identifique o serviço</h2>
                <p>
                    Escolha um modelo inicial e informe os dados básicos.
                </p>
            </div>
        </header>

        <div class="sb-panel-body">
            <div class="sb-fields">
                <label class="sb-field">
                    <span class="sb-label">
                        <i class="ph-fill ph-text-t"></i>
                        Nome do serviço
                    </span>

                    <input
                        class="sb-control"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        maxlength="191"
                        placeholder="Ex.: Transporte de paciente"
                    >
                </label>

                <label class="sb-field">
                    <span class="sb-label">
                        <i class="ph-fill ph-hash"></i>
                        Código interno
                        <span class="sb-note">opcional</span>
                    </span>

                    <input
                        class="sb-control"
                        name="code"
                        value="{{ old('code') }}"
                        maxlength="191"
                        placeholder="Ex.: TRANSP-01"
                    >
                </label>

                <label class="sb-field">
                    <span class="sb-label">
                        <i class="ph-fill ph-layout"></i>
                        Modelo inicial
                    </span>

                    <select
                        class="sb-control"
                        id="preset"
                        name="preset"
                        required
                    >
                        @foreach($presets as $key => $preset)
                            <option
                                value="{{ $key }}"
                                data-preset='@json($preset)'
                                @selected(old('preset') === $key)
                            >
                                {{ $preset['label'] }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="sb-field">
                    <span class="sb-label">
                        <i class="ph-fill ph-ruler"></i>
                        Unidade principal
                    </span>

                    <input
                        class="sb-control"
                        id="unit"
                        name="unit"
                        value="{{ old('unit') }}"
                        required
                        maxlength="30"
                        placeholder="hora, km, dia, unidade"
                    >
                </label>

                <label class="sb-field full">
                    <span class="sb-label">
                        <i class="ph-fill ph-note"></i>
                        Descrição
                        <span class="sb-note">opcional</span>
                    </span>

                    <textarea
                        class="sb-control"
                        name="description"
                        placeholder="Descrição curta do serviço"
                    >{{ old('description') }}</textarea>
                </label>
            </div>

            <div class="sb-preset-box">
                <strong>Modelo selecionado</strong>

                <p id="preset-description">
                    Carregando descrição…
                </p>
            </div>
        </div>

        <footer class="sb-panel-actions">
            <span></span>

            <div class="sb-actions-right">
                <button
                    class="sb-action primary"
                    type="button"
                    data-next
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </div>
        </footer>
    </section>

    {{-- ======================================================
         ETAPA 2
         ====================================================== --}}

    <section
        class="sb-panel data"
        data-step="1"
        hidden
    >
        <header class="sb-panel-head">
            <span
                class="sb-panel-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-list-checks"></i>
            </span>

            <div class="sb-panel-title">
                <h2>Dados da execução</h2>
                <p>
                    Confira os campos sugeridos pelo modelo escolhido.
                </p>
            </div>
        </header>

        <div class="sb-panel-body">
            <div
                id="field-preview"
                class="sb-field-preview"
            ></div>

            <div class="sb-fields">
                <label class="sb-field">
                    <span class="sb-label">
                        <i class="ph-fill ph-check-square"></i>
                        Conferência
                    </span>

                    <select
                        class="sb-control"
                        name="review_mode"
                    >
                        <option value="manual">
                            Gestor confere antes de aprovar
                        </option>

                        <option value="automatic">
                            Aprovar automaticamente após a conclusão
                        </option>
                    </select>
                </label>
            </div>

            <div class="sb-toggle-row">
                <div class="sb-toggle-copy">
                    <strong>Prestador pode criar a própria ordem</strong>

                    <small>
                        Disponível apenas para prestadores habilitados no serviço.
                    </small>
                </div>

                <label class="sb-switch">
                    <input
                        type="checkbox"
                        name="allow_provider_create_order"
                        value="1"
                        @checked(old('allow_provider_create_order'))
                    >

                    <span class="sb-switch-track"></span>
                </label>
            </div>

            <div class="sb-toggle-row">
                <div class="sb-toggle-copy">
                    <strong>Somente membros</strong>

                    <small>
                        Exige um membro vinculado ao criar a ordem.
                    </small>
                </div>

                <label class="sb-switch">
                    <input
                        type="checkbox"
                        name="members_only"
                        value="1"
                        @checked(old('members_only'))
                    >

                    <span class="sb-switch-track"></span>
                </label>
            </div>

            <div class="sb-finance-hint">
                <i class="ph-fill ph-info"></i>

                <span>
                    Os campos documentam a execução. O vínculo detalhado
                    com cálculos pode ser ajustado depois no rascunho.
                </span>
            </div>
        </div>

        <footer class="sb-panel-actions">
            <button
                class="sb-action secondary"
                type="button"
                data-previous
            >
                <i class="ph-fill ph-arrow-left"></i>
                <span>Voltar</span>
            </button>

            <div class="sb-actions-right">
                <button
                    class="sb-action primary"
                    type="button"
                    data-next
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </div>
        </footer>
    </section>

    {{-- ======================================================
         ETAPA 3
         ====================================================== --}}

    <section
        class="sb-panel receivable"
        data-step="2"
        hidden
    >
        <header class="sb-panel-head">
            <span
                class="sb-panel-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-arrow-circle-down-left"></i>
            </span>

            <div class="sb-panel-title">
                <h2>Cobrança do cliente</h2>
                <p>
                    Defina se este serviço gera valor a receber.
                </p>
            </div>
        </header>

        <div class="sb-panel-body">
            <div class="sb-toggle-row">
                <div class="sb-toggle-copy">
                    <strong>Gerar valor a receber</strong>

                    <small>
                        Cria a obrigação financeira do cliente para a organização.
                    </small>
                </div>

                <label class="sb-switch">
                    <input
                        type="hidden"
                        name="receivable_enabled"
                        value="0"
                    >

                    <input
                        id="receivable-enabled"
                        type="checkbox"
                        name="receivable_enabled"
                        value="1"
                        checked
                    >

                    <span class="sb-switch-track"></span>
                </label>
            </div>

            <div
                class="sb-finance-fields"
                id="receivable-fields"
            >
                <div class="sb-fields">
                    <label class="sb-field">
                        <span class="sb-label">
                            <i class="ph-fill ph-function"></i>
                            Como calcular
                        </span>

                        <select
                            class="sb-control"
                            id="customer-method"
                            name="customer_pricing_method"
                        >
                            @foreach(
                                \App\Support\ServiceConfigurationLabels::pricingMethods()
                                as $key => $label
                            )
                                <option value="{{ $key }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sb-field">
                        <span class="sb-label">
                            <i class="ph-fill ph-currency-circle-dollar"></i>
                            Valor fixo, tarifa ou base
                        </span>

                        <input
                            class="sb-control"
                            type="number"
                            step="0.0001"
                            min="0"
                            name="customer_rate"
                            value="{{ old('customer_rate') }}"
                            inputmode="decimal"
                            placeholder="0,00"
                        >
                    </label>

                    <label class="sb-field">
                        <span class="sb-label">
                            <i class="ph-fill ph-percent"></i>
                            Percentual
                        </span>

                        <input
                            class="sb-control"
                            type="number"
                            step="0.0001"
                            min="0"
                            max="100"
                            name="customer_percentage"
                            value="{{ old('customer_percentage') }}"
                            inputmode="decimal"
                            placeholder="0"
                        >

                        <small class="sb-help">
                            Use apenas quando a fórmula escolhida trabalhar com percentual.
                        </small>
                    </label>
                </div>
            </div>

            <div class="sb-finance-hint">
                <i class="ph-fill ph-info"></i>

                <span>
                    A cobrança do cliente é independente da remuneração do prestador.
                </span>
            </div>
        </div>

        <footer class="sb-panel-actions">
            <button
                class="sb-action secondary"
                type="button"
                data-previous
            >
                <i class="ph-fill ph-arrow-left"></i>
                <span>Voltar</span>
            </button>

            <div class="sb-actions-right">
                <button
                    class="sb-action primary"
                    type="button"
                    data-next
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </div>
        </footer>
    </section>

    {{-- ======================================================
         ETAPA 4
         ====================================================== --}}

    <section
        class="sb-panel provider"
        data-step="3"
        hidden
    >
        <header class="sb-panel-head">
            <span
                class="sb-panel-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-arrow-circle-up-right"></i>
            </span>

            <div class="sb-panel-title">
                <h2>Remuneração do prestador</h2>
                <p>
                    Defina se o serviço gera valor a pagar.
                </p>
            </div>
        </header>

        <div class="sb-panel-body">
            <div class="sb-toggle-row">
                <div class="sb-toggle-copy">
                    <strong>Gerar valor a pagar ao prestador</strong>

                    <small>
                        Cria a obrigação financeira do prestador.
                    </small>
                </div>

                <label class="sb-switch">
                    <input
                        type="hidden"
                        name="payable_enabled"
                        value="0"
                    >

                    <input
                        id="payable-enabled"
                        type="checkbox"
                        name="payable_enabled"
                        value="1"
                    >

                    <span class="sb-switch-track"></span>
                </label>
            </div>

            <div
                class="sb-finance-fields"
                id="provider-fields"
            >
                <div class="sb-fields">
                    <label class="sb-field">
                        <span class="sb-label">
                            <i class="ph-fill ph-function"></i>
                            Como calcular
                        </span>

                        <select
                            class="sb-control"
                            id="provider-method"
                            name="provider_pricing_method"
                        >
                            <option value="">
                                Não remunerar pelo sistema
                            </option>

                            @foreach(
                                \App\Support\ServiceConfigurationLabels::pricingMethods(true)
                                as $key => $label
                            )
                                <option value="{{ $key }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="sb-field">
                        <span class="sb-label">
                            <i class="ph-fill ph-currency-circle-dollar"></i>
                            Valor fixo ou tarifa
                        </span>

                        <input
                            class="sb-control"
                            type="number"
                            step="0.0001"
                            min="0"
                            name="default_provider_rate"
                            value="{{ old('default_provider_rate') }}"
                            inputmode="decimal"
                            placeholder="0,00"
                        >
                    </label>

                    <label class="sb-field">
                        <span class="sb-label">
                            <i class="ph-fill ph-percent"></i>
                            Percentual da cobrança
                        </span>

                        <input
                            class="sb-control"
                            type="number"
                            step="0.0001"
                            min="0"
                            max="100"
                            name="provider_percentage"
                            value="{{ old('provider_percentage') }}"
                            inputmode="decimal"
                            placeholder="0"
                        >
                    </label>
                </div>
            </div>

            <div class="sb-finance-hint">
                <i class="ph-fill ph-info"></i>

                <span>
                    A fórmula e a fonte de quantidade podem ser refinadas
                    depois que os campos do serviço já existirem.
                </span>
            </div>
        </div>

        <footer class="sb-panel-actions">
            <button
                class="sb-action secondary"
                type="button"
                data-previous
            >
                <i class="ph-fill ph-arrow-left"></i>
                <span>Voltar</span>
            </button>

            <div class="sb-actions-right">
                <button
                    class="sb-action primary"
                    type="button"
                    data-next
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </div>
        </footer>
    </section>

    {{-- ======================================================
         ETAPA 5
         ====================================================== --}}

    <section
        class="sb-panel review"
        data-step="4"
        hidden
    >
        <header class="sb-panel-head">
            <span
                class="sb-panel-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-check-circle"></i>
            </span>

            <div class="sb-panel-title">
                <h2>Revisar e salvar</h2>
                <p>
                    Confira o essencial antes de criar o serviço.
                </p>
            </div>
        </header>

        <div class="sb-panel-body">
            <div class="sb-review">
                <div class="sb-review-grid">
                    <div class="sb-review-item">
                        <small>Serviço</small>
                        <strong id="review-name">—</strong>
                    </div>

                    <div class="sb-review-item">
                        <small>Modelo</small>
                        <strong id="review-preset">—</strong>
                    </div>

                    <div class="sb-review-item">
                        <small>Unidade principal</small>
                        <strong id="review-unit">—</strong>
                    </div>

                    <div class="sb-review-item">
                        <small>Conferência</small>
                        <strong id="review-mode">—</strong>
                    </div>

                    <div class="sb-review-item">
                        <small>Cobrança</small>
                        <strong id="review-receivable">—</strong>
                    </div>

                    <div class="sb-review-item">
                        <small>Prestador</small>
                        <strong id="review-payable">—</strong>
                    </div>
                </div>

                <section
                    class="sb-simulation"
                    aria-labelledby="simulation-title"
                >
                    <header class="sb-simulation-head">
                        <div class="sb-simulation-title">
                            <span
                                class="sb-simulation-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-calculator"></i>
                            </span>

                            <span class="sb-simulation-title-copy">
                                <strong id="simulation-title">
                                    Exemplo de valores
                                </strong>

                                <small>
                                    Confira se o fluxo financeiro ficou como esperado.
                                </small>
                            </span>
                        </div>

                        <div class="sb-simulation-quantity">
                            <label for="simulation-quantity">
                                Exemplo
                            </label>

                            <input
                                id="simulation-quantity"
                                type="number"
                                min="0"
                                step="0.01"
                                value="10"
                                inputmode="decimal"
                                aria-label="Quantidade usada no exemplo"
                            >

                            <span
                                class="sb-simulation-unit"
                                id="simulation-unit"
                            >
                                unidades
                            </span>
                        </div>
                    </header>

                    <div class="sb-simulation-flow">
                        <div class="sb-simulation-node customer">
                            <small>Cliente</small>

                            <strong id="simulation-customer-value">
                                R$ 0,00
                            </strong>

                            <span id="simulation-customer-method">
                                Sem cobrança
                            </span>
                        </div>

                        <span
                            class="sb-simulation-arrow"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-arrow-right"></i>
                        </span>

                        <div class="sb-simulation-node organization">
                            <small>Organização</small>

                            <strong id="simulation-organization-value">
                                R$ 0,00
                            </strong>

                            <span>
                                Resultado do exemplo
                            </span>
                        </div>

                        <span
                            class="sb-simulation-arrow"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-arrow-right"></i>
                        </span>

                        <div class="sb-simulation-node provider">
                            <small>Prestador</small>

                            <strong id="simulation-provider-value">
                                R$ 0,00
                            </strong>

                            <span id="simulation-provider-method">
                                Sem remuneração
                            </span>
                        </div>
                    </div>

                    <div class="sb-simulation-result">
                        <span class="sb-simulation-result-copy">
                            <strong>
                                Saldo do exemplo para a organização
                            </strong>

                            <small id="simulation-description">
                                Ajuste a quantidade para conferir outro cenário.
                            </small>
                        </span>

                        <strong
                            class="sb-simulation-net"
                            id="simulation-net"
                        >
                            R$ 0,00
                        </strong>
                    </div>

                    <div
                        class="sb-simulation-warning"
                        id="simulation-warning"
                    >
                        <i class="ph-fill ph-info"></i>

                        <span id="simulation-warning-text">
                            Esta fórmula depende de configuração detalhada
                            no rascunho e não pode ser simulada com precisão aqui.
                        </span>
                    </div>
                </section>

                <div class="sb-publish">
                    <div class="sb-publish-copy">
                        <strong>Publicar imediatamente</strong>

                        <small>
                            Se desativado, o serviço será salvo como rascunho
                            para revisão antes da publicação.
                        </small>
                    </div>

                    <label class="sb-switch">
                        <input
                            id="publish"
                            type="checkbox"
                            name="publish"
                            value="1"
                            @checked(old('publish'))
                        >

                        <span class="sb-switch-track"></span>
                    </label>
                </div>

                <div class="sb-finance-hint">
                    <i class="ph-fill ph-lightbulb"></i>

                    <span>
                        Para serviços novos, salvar como rascunho facilita
                        testar campos e cálculos antes de disponibilizá-los.
                    </span>
                </div>
            </div>
        </div>

        <footer class="sb-panel-actions">
            <button
                class="sb-action secondary"
                type="button"
                data-previous
            >
                <i class="ph-fill ph-arrow-left"></i>
                <span>Voltar</span>
            </button>

            <div class="sb-actions-right">
                <button
                    class="sb-action primary final"
                    type="submit"
                    id="submit-wizard"
                >
                    <i class="ph-fill ph-check-circle"></i>
                    Salvar serviço
                </button>
            </div>
        </footer>
    </section>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form =
        document.getElementById(
            'service-wizard'
        );

    if (!form) {
        return;
    }

    const panels = [
        ...form.querySelectorAll(
            '[data-step]'
        ),
    ];

    const stepButtons = [
        ...form.querySelectorAll(
            '[data-go-step]'
        ),
    ];

    const stepper =
        form.querySelector(
            '.sb-stepper'
        );

    const nextButtons = [
        ...form.querySelectorAll(
            '[data-next]'
        ),
    ];

    const previousButtons = [
        ...form.querySelectorAll(
            '[data-previous]'
        ),
    ];

    const currentStepNumber =
        document.getElementById(
            'current-step-number'
        );

    const preset =
        document.getElementById(
            'preset'
        );

    const unit =
        document.getElementById(
            'unit'
        );

    const presetDescription =
        document.getElementById(
            'preset-description'
        );

    const fieldPreview =
        document.getElementById(
            'field-preview'
        );

    const receivableEnabled =
        document.getElementById(
            'receivable-enabled'
        );

    const payableEnabled =
        document.getElementById(
            'payable-enabled'
        );

    const receivableFields =
        document.getElementById(
            'receivable-fields'
        );

    const providerFields =
        document.getElementById(
            'provider-fields'
        );

    const customerMethod =
        document.getElementById(
            'customer-method'
        );

    const providerMethod =
        document.getElementById(
            'provider-method'
        );

    const customerRate =
        form.querySelector(
            '[name="customer_rate"]'
        );

    const customerPercentage =
        form.querySelector(
            '[name="customer_percentage"]'
        );

    const providerRate =
        form.querySelector(
            '[name="default_provider_rate"]'
        );

    const providerPercentage =
        form.querySelector(
            '[name="provider_percentage"]'
        );

    const simulationQuantity =
        document.getElementById(
            'simulation-quantity'
        );

    const simulationUnit =
        document.getElementById(
            'simulation-unit'
        );

    const simulationCustomerValue =
        document.getElementById(
            'simulation-customer-value'
        );

    const simulationCustomerMethod =
        document.getElementById(
            'simulation-customer-method'
        );

    const simulationProviderValue =
        document.getElementById(
            'simulation-provider-value'
        );

    const simulationProviderMethod =
        document.getElementById(
            'simulation-provider-method'
        );

    const simulationOrganizationValue =
        document.getElementById(
            'simulation-organization-value'
        );

    const simulationNet =
        document.getElementById(
            'simulation-net'
        );

    const simulationDescription =
        document.getElementById(
            'simulation-description'
        );

    const simulationWarning =
        document.getElementById(
            'simulation-warning'
        );

    const simulationWarningText =
        document.getElementById(
            'simulation-warning-text'
        );

    let currentStep = 0;
    let highestStep = 0;

    const phaseLabels = {
        order: 'Ao criar a ordem',
        start: 'Ao iniciar',
        execution: 'Durante a execução',
        finish: 'Ao concluir',
        review: 'Na conferência',
    };

    const selectedText = select => {
        if (!select) {
            return '';
        }

        return select
            .selectedOptions?.[0]
            ?.textContent
            ?.trim()
            || '';
    };

    const numberValue = input => {
        const parsed =
            Number.parseFloat(
                input?.value
                || '0'
            );

        return Number.isFinite(parsed)
            ? parsed
            : 0;
    };

    const currency = value =>
        new Intl.NumberFormat(
            'pt-BR',
            {
                style: 'currency',
                currency: 'BRL',
            }
        ).format(
            Number.isFinite(value)
                ? value
                : 0
        );

    const normalizedMethod = select => {
        const option =
            select?.selectedOptions?.[0];

        return [
            select?.value || '',
            option?.textContent || '',
        ]
            .join(' ')
            .toLocaleLowerCase('pt-BR')
            .normalize('NFD')
            .replace(
                /[\u0300-\u036f]/g,
                ''
            );
    };

    const pricingKind = select => {
        const method =
            normalizedMethod(select);

        if (
            !method.trim()
            || method.includes(
                'nao remunerar'
            )
        ) {
            return 'none';
        }

        if (
            method.includes('percent')
            || method.includes('porcent')
        ) {
            return 'percentage';
        }

        if (
            method.includes('unidade')
            || method.includes('unit')
            || method.includes('quantidade')
            || method.includes('quantity')
            || method.includes('hora')
            || method.includes('km')
            || method.includes('quilometr')
            || method.includes('distancia')
            || method.includes('dia')
        ) {
            return 'per_unit';
        }

        if (
            method.includes('fix')
            || method.includes('valor')
        ) {
            return 'fixed';
        }

        return 'unknown';
    };

    const calculateCustomerExample = quantity => {
        if (!receivableEnabled?.checked) {
            return {
                value: 0,
                known: true,
                description:
                    'Sem cobrança',
            };
        }

        const kind =
            pricingKind(customerMethod);

        const rate =
            numberValue(customerRate);

        const percentage =
            numberValue(
                customerPercentage
            );

        if (kind === 'fixed') {
            return {
                value: rate,
                known: true,
                description:
                    selectedText(
                        customerMethod
                    ) || 'Valor fixo',
            };
        }

        if (kind === 'per_unit') {
            return {
                value:
                    rate * quantity,
                known: true,
                description:
                    selectedText(
                        customerMethod
                    ) || 'Por unidade',
            };
        }

        if (kind === 'percentage') {
            return {
                value:
                    rate
                    * percentage
                    / 100,
                known: true,
                description:
                    `${percentage || 0}% sobre base de ${currency(rate)}`,
            };
        }

        return {
            value: 0,
            known: false,
            description:
                selectedText(
                    customerMethod
                ) || 'Fórmula configurada',
        };
    };

    const calculateProviderExample = (
        quantity,
        customerValue
    ) => {
        if (!payableEnabled?.checked) {
            return {
                value: 0,
                known: true,
                description:
                    'Sem remuneração',
            };
        }

        const kind =
            pricingKind(providerMethod);

        const rate =
            numberValue(providerRate);

        const percentage =
            numberValue(
                providerPercentage
            );

        if (kind === 'none') {
            return {
                value: 0,
                known: true,
                description:
                    'Sem remuneração',
            };
        }

        if (kind === 'fixed') {
            return {
                value: rate,
                known: true,
                description:
                    selectedText(
                        providerMethod
                    ) || 'Valor fixo',
            };
        }

        if (kind === 'per_unit') {
            return {
                value:
                    rate * quantity,
                known: true,
                description:
                    selectedText(
                        providerMethod
                    ) || 'Por unidade',
            };
        }

        if (kind === 'percentage') {
            return {
                value:
                    customerValue
                    * percentage
                    / 100,
                known: true,
                description:
                    `${percentage || 0}% da cobrança`,
            };
        }

        return {
            value: 0,
            known: false,
            description:
                selectedText(
                    providerMethod
                ) || 'Fórmula configurada',
        };
    };

    const updateSimulation = () => {
        if (!simulationQuantity) {
            return;
        }

        const quantity =
            Math.max(
                0,
                numberValue(
                    simulationQuantity
                )
            );

        const customer =
            calculateCustomerExample(
                quantity
            );

        const provider =
            calculateProviderExample(
                quantity,
                customer.value
            );

        const organization =
            customer.value
            - provider.value;

        if (simulationUnit) {
            simulationUnit.textContent =
                unit?.value?.trim()
                || 'unidades';
        }

        if (simulationCustomerValue) {
            simulationCustomerValue.textContent =
                customer.known
                    ? currency(
                        customer.value
                    )
                    : '—';
        }

        if (simulationCustomerMethod) {
            simulationCustomerMethod.textContent =
                customer.description;
        }

        if (simulationProviderValue) {
            simulationProviderValue.textContent =
                provider.known
                    ? currency(
                        provider.value
                    )
                    : '—';
        }

        if (simulationProviderMethod) {
            simulationProviderMethod.textContent =
                provider.description;
        }

        const fullyKnown =
            customer.known
            && provider.known;

        if (simulationOrganizationValue) {
            simulationOrganizationValue.textContent =
                fullyKnown
                    ? currency(
                        organization
                    )
                    : '—';
        }

        if (simulationNet) {
            simulationNet.textContent =
                fullyKnown
                    ? currency(
                        organization
                    )
                    : '—';

            simulationNet.classList.toggle(
                'negative',
                fullyKnown
                && organization < 0
            );
        }

        if (simulationDescription) {
            simulationDescription.textContent =
                fullyKnown
                    ? `Exemplo com ${quantity || 0} ${unit?.value?.trim() || 'unidades'}.`
                    : 'Parte do cálculo depende da configuração detalhada do serviço.';
        }

        const hasUnknown =
            !customer.known
            || !provider.known;

        if (simulationWarning) {
            simulationWarning.classList.toggle(
                'show',
                hasUnknown
            );
        }

        if (
            simulationWarningText
            && hasUnknown
        ) {
            simulationWarningText.textContent =
                'Uma das fórmulas depende de configuração detalhada no rascunho. O exemplo mostra apenas o que pode ser calculado com segurança nesta etapa.';
        }
    };

    const setFinanceState = (
        enabledInput,
        container
    ) => {
        if (
            !enabledInput
            || !container
        ) {
            return;
        }

        const enabled =
            enabledInput.checked;

        container.classList.toggle(
            'disabled',
            !enabled
        );

        container
            .querySelectorAll(
                'input,select,textarea'
            )
            .forEach(field => {
                field.disabled =
                    !enabled;
            });
    };

    const updateFinanceStates = () => {
        setFinanceState(
            receivableEnabled,
            receivableFields
        );

        setFinanceState(
            payableEnabled,
            providerFields
        );
    };

    const renderFieldPreview = fields => {
        if (!fieldPreview) {
            return;
        }

        if (
            !Array.isArray(fields)
            || fields.length === 0
        ) {
            fieldPreview.innerHTML = `
                <div class="sb-preview-empty">
                    Este modelo não adiciona campos iniciais.
                </div>
            `;

            return;
        }

        fieldPreview.innerHTML =
            fields.map(field => {
                const label =
                    field.label
                    || 'Campo';

                const requirement =
                    field.required
                        ? 'Obrigatório'
                        : 'Opcional';

                const phase =
                    phaseLabels[field.phase]
                    || field.phase
                    || 'Execução';

                return `
                    <div class="sb-preview-item">
                        <span
                            class="sb-preview-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-textbox"></i>
                        </span>

                        <span class="sb-preview-copy">
                            <strong>${label}</strong>
                            <small>${requirement}</small>
                        </span>

                        <span class="sb-preview-phase">
                            ${phase}
                        </span>
                    </div>
                `;
            }).join('');
    };

    const applyPreset = (
        overwrite = true
    ) => {
        if (!preset) {
            return;
        }

        let data = {};

        try {
            data = JSON.parse(
                preset
                    .options[
                        preset.selectedIndex
                    ]
                    ?.dataset
                    ?.preset
                || '{}'
            );
        } catch {
            data = {};
        }

        if (presetDescription) {
            presetDescription.textContent =
                data.description
                || 'Modelo sem descrição adicional.';
        }

        if (
            unit
            && (
                overwrite
                || !unit.value
            )
        ) {
            unit.value =
                data.unit
                || 'unidade';
        }

        if (receivableEnabled) {
            receivableEnabled.checked =
                Boolean(
                    data.receivable_enabled
                );
        }

        if (payableEnabled) {
            payableEnabled.checked =
                Boolean(
                    data.payable_enabled
                );
        }

        if (
            customerMethod
            && data.customer_pricing_method
        ) {
            customerMethod.value =
                data.customer_pricing_method;
        }

        if (providerMethod) {
            providerMethod.value =
                data.provider_pricing_method
                || '';
        }

        renderFieldPreview(
            data.fields
            || []
        );

        updateFinanceStates();
        updateReview();
    };

    const currentPanelIsValid = () => {
        const panel =
            panels[currentStep];

        if (!panel) {
            return true;
        }

        const invalid =
            panel.querySelector(
                ':invalid'
            );

        if (!invalid) {
            return true;
        }

        invalid.reportValidity();
        invalid.focus({
            preventScroll: true,
        });

        invalid.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });

        return false;
    };

    const updateStepper = () => {
        stepButtons.forEach(
            (
                button,
                index
            ) => {
                button.classList.toggle(
                    'active',
                    index === currentStep
                );

                button.classList.toggle(
                    'done',
                    index < currentStep
                );

                button.disabled =
                    index > highestStep;
            }
        );

        const activeStep =
            stepButtons[currentStep];

        if (
            stepper
            && activeStep
            && stepper.scrollWidth
                > stepper.clientWidth
        ) {
            const stepRect =
                activeStep
                    .getBoundingClientRect();

            const stepperRect =
                stepper
                    .getBoundingClientRect();

            const outsideView =
                stepRect.left
                    < stepperRect.left
                || stepRect.right
                    > stepperRect.right;

            if (outsideView) {
                const currentLeft =
                    stepper.scrollLeft;

                const relativeCenter =
                    (
                        stepRect.left
                        - stepperRect.left
                    )
                    + currentLeft
                    + (
                        stepRect.width / 2
                    );

                const targetLeft =
                    Math.max(
                        0,
                        relativeCenter
                        - (
                            stepper.clientWidth
                            / 2
                        )
                    );

                stepper.scrollTo({
                    left: targetLeft,
                    behavior: 'smooth',
                });
            }
        }
    };

    const showStep = (
        index,
        {
            validate = false,
        } = {}
    ) => {
        const nextIndex =
            Math.max(
                0,
                Math.min(
                    index,
                    panels.length - 1
                )
            );

        if (
            validate
            && nextIndex > currentStep
            && !currentPanelIsValid()
        ) {
            return;
        }

        currentStep =
            nextIndex;

        highestStep =
            Math.max(
                highestStep,
                currentStep
            );

        panels.forEach(
            (
                panel,
                panelIndex
            ) => {
                panel.hidden =
                    panelIndex !== currentStep;
            }
        );

        updateStepper();
        updateReview();

        window.requestAnimationFrame(
            () => {
                form.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            }
        );
    };

    const updateReview = () => {
        const nameInput =
            form.querySelector(
                '[name="name"]'
            );

        const reviewMode =
            form.querySelector(
                '[name="review_mode"]'
            );

        const setText = (
            id,
            value
        ) => {
            const element =
                document.getElementById(
                    id
                );

            if (element) {
                element.textContent =
                    value || '—';
            }
        };

        setText(
            'review-name',
            nameInput?.value?.trim()
                || '—'
        );

        setText(
            'review-preset',
            selectedText(preset)
                || '—'
        );

        setText(
            'review-unit',
            unit?.value?.trim()
                || '—'
        );

        setText(
            'review-mode',
            selectedText(reviewMode)
                || '—'
        );

        setText(
            'review-receivable',
            receivableEnabled?.checked
                ? selectedText(
                    customerMethod
                ) || 'Ativada'
                : 'Não gerar'
        );

        setText(
            'review-payable',
            payableEnabled?.checked
                ? selectedText(
                    providerMethod
                ) || 'Ativada'
                : 'Não gerar'
        );

        updateSimulation();
    };

    nextButtons.forEach(
        button => {
            button.addEventListener(
                'click',
                () => {
                    if (
                        !currentPanelIsValid()
                    ) {
                        return;
                    }

                    showStep(
                        currentStep + 1
                    );
                }
            );
        }
    );

    previousButtons.forEach(
        button => {
            button.addEventListener(
                'click',
                () => {
                    showStep(
                        currentStep - 1
                    );
                }
            );
        }
    );

    stepButtons.forEach(
        (
            button,
            index
        ) => {
            button.addEventListener(
                'click',
                () => {
                    if (
                        index > highestStep
                    ) {
                        return;
                    }

                    showStep(index);
                }
            );
        }
    );

    preset?.addEventListener(
        'change',
        () => {
            applyPreset(true);
        }
    );

    simulationQuantity
        ?.addEventListener(
            'input',
            updateSimulation
        );

    receivableEnabled
        ?.addEventListener(
            'change',
            () => {
                updateFinanceStates();
                updateReview();
            }
        );

    payableEnabled
        ?.addEventListener(
            'change',
            () => {
                updateFinanceStates();
                updateReview();
            }
        );

    form
        .querySelectorAll(
            'input,select,textarea'
        )
        .forEach(field => {
            field.addEventListener(
                'input',
                updateReview
            );

            field.addEventListener(
                'change',
                updateReview
            );
        });

    form.addEventListener(
        'submit',
        event => {
            if (!form.checkValidity()) {
                event.preventDefault();

                const invalid =
                    form.querySelector(
                        ':invalid'
                    );

                const invalidPanel =
                    invalid?.closest(
                        '[data-step]'
                    );

                if (invalidPanel) {
                    const index =
                        panels.indexOf(
                            invalidPanel
                        );

                    highestStep =
                        Math.max(
                            highestStep,
                            index
                        );

                    showStep(index);

                    window
                        .requestAnimationFrame(
                            () => {
                                invalid.reportValidity();
                            }
                        );
                }
            }
        }
    );

    applyPreset(
        !unit?.value
    );

    updateFinanceStates();
    updateReview();
    showStep(0);
});
</script>
@endsection