@php
    $reportProjects = collect($reportProjects ?? []);
    $selectedReportProject = (int) ($selectedReportProject ?? 0);
@endphp

@once
<style>
    .dr-modal,
    .dr-modal * {
        box-sizing: border-box;
    }

    .dr-modal {
        --dr-green: #168a4d;
        --dr-green-soft: #eaf8ef;
        --dr-blue: #2563eb;
        --dr-blue-soft: #eef4ff;
        --dr-violet: #7c3aed;
        --dr-violet-soft: #f4f0ff;
        --dr-amber: #c87408;
        --dr-amber-soft: #fff7e8;
        --dr-red: #cf3f3f;
        --dr-red-soft: #fff0f0;
        --dr-slate: #64748b;
        --dr-slate-soft: #f1f5f9;

        --dr-surface: var(--color-surface, #fff);
        --dr-soft: var(--color-surface-soft, #f8faf9);
        --dr-border: var(--color-border, #dce7e0);
        --dr-border-strong: var(--color-border-strong, #c8d6cd);
        --dr-text: var(--color-text, #102018);
        --dr-text-2: var(--color-text-secondary, #52645a);
        --dr-text-3: var(--color-text-muted, #809087);

        position: fixed;
        z-index: 100500;
        inset: 0;
        display: grid;
        place-items: center;
        padding:
            max(1rem, env(safe-area-inset-top))
            max(1rem, env(safe-area-inset-right))
            max(1rem, env(safe-area-inset-bottom))
            max(1rem, env(safe-area-inset-left));
        background: rgba(15, 23, 42, .48);
        backdrop-filter: blur(4px);
    }

    .dr-modal[hidden] {
        display: none !important;
    }

    .dr-panel {
        display: flex;
        width: min(820px, 100%);
        max-height: min(90dvh, 840px);
        min-height: 0;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--dr-border);
        border-radius: 15px;
        background: var(--dr-surface);
        color: var(--dr-text);
        box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
    }

    .dr-head {
        display: grid;
        flex: 0 0 auto;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .62rem;
        align-items: center;
        min-height: 68px;
        padding: .7rem .78rem;
        border-bottom: 1px solid var(--dr-border);
        background:
            radial-gradient(circle at 100% 0, rgba(124, 58, 237, .09), transparent 16rem),
            linear-gradient(180deg, var(--dr-soft), #fff);
    }

    .dr-head-icon,
    .dr-close,
    .dr-section-icon,
    .dr-choice-icon,
    .dr-filter-icon,
    .dr-btn > i,
    .dr-btn > svg {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        line-height: 0;
    }

    .dr-head-icon {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        background: var(--dr-violet-soft);
        color: var(--dr-violet);
    }

    .dr-head-icon > i,
    .dr-head-icon > svg {
        display: block;
        width: 18px;
        height: 18px;
        margin: 0;
    }

    .dr-head-copy {
        min-width: 0;
    }

    .dr-head h2,
    .dr-head p {
        margin: 0;
    }

    .dr-head h2 {
        color: var(--dr-text);
        font-size: .98rem;
        font-weight: 850;
        letter-spacing: -.025em;
        line-height: 1.25;
    }

    .dr-head p {
        margin-top: .08rem;
        color: var(--dr-text-3);
        font-size: .69rem;
        line-height: 1.35;
    }

    .dr-close {
        width: 36px;
        height: 36px;
        border: 1px solid var(--dr-border);
        border-radius: 9px;
        background: #fff;
        color: var(--dr-text-2);
        cursor: pointer;
        transition:
            border-color 140ms ease,
            background 140ms ease,
            color 140ms ease;
    }

    .dr-close:hover,
    .dr-close:focus-visible {
        border-color: rgba(124, 58, 237, .18);
        background: var(--dr-violet-soft);
        color: var(--dr-violet);
        outline: none;
    }

    .dr-close > i,
    .dr-close > svg {
        display: block;
        width: 15px;
        height: 15px;
        margin: 0;
    }

    .dr-body {
        display: grid;
        min-height: 0;
        flex: 1 1 auto;
        gap: .72rem;
        padding: .72rem .78rem .82rem;
        overflow-y: auto;
        overscroll-behavior: contain;
        scrollbar-width: thin;
    }

    .dr-section {
        min-width: 0;
        border: 1px solid var(--dr-border);
        border-radius: 12px;
        background: #fff;
    }

    .dr-section-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .48rem;
        align-items: center;
        min-height: 46px;
        padding: .46rem .54rem;
        border-bottom: 1px solid var(--dr-border);
        background:
            linear-gradient(180deg, var(--dr-soft), #fff);
    }

    .dr-section-icon {
        width: 32px;
        height: 32px;
        border-radius: 9px;
        background: var(--dr-blue-soft);
        color: var(--dr-blue);
    }

    .dr-section-icon.violet {
        background: var(--dr-violet-soft);
        color: var(--dr-violet);
    }

    .dr-section-icon > i,
    .dr-section-icon > svg {
        display: block;
        width: 14px;
        height: 14px;
        margin: 0;
    }

    .dr-section-title {
        color: var(--dr-text);
        font-size: .76rem;
        font-weight: 820;
        line-height: 1.25;
    }

    .dr-section-sub {
        margin-top: .03rem;
        color: var(--dr-text-3);
        font-size: .63rem;
        line-height: 1.3;
    }

    .dr-section-body {
        padding: .58rem;
    }

    .dr-label {
        display: block;
        margin-bottom: .26rem;
        color: var(--dr-text-2);
        font-size: .66rem;
        font-weight: 760;
        line-height: 1.25;
    }

    .dr-select,
    .dr-input,
    .dr-filter-search {
        width: 100%;
        min-height: 40px;
        border: 1px solid var(--dr-border-strong);
        border-radius: 9px;
        outline: none;
        background: #fff;
        color: var(--dr-text);
        font: inherit;
        font-size: .72rem;
        transition:
            border-color 140ms ease,
            box-shadow 140ms ease;
    }

    .dr-select,
    .dr-input {
        padding: .46rem .56rem;
    }

    .dr-select:focus,
    .dr-input:focus,
    .dr-filter-search:focus {
        border-color: var(--dr-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .07);
    }

    .dr-scope-grid {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1.55fr) minmax(135px, .72fr) minmax(135px, .72fr);
        gap: .48rem;
        align-items: end;
    }

    .dr-field {
        min-width: 0;
    }

    .dr-grid {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .5rem;
    }

    /* Tipo do relatório */
    .dr-mode-wrap {
        min-width: 0;
    }

    .dr-modes {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .36rem;
    }

    .dr-choice {
        position: relative;
        min-width: 0;
    }

    .dr-choice input {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        pointer-events: none;
    }

    .dr-choice > span {
        display: grid;
        min-width: 0;
        min-height: 48px;
        grid-template-columns: auto minmax(0, auto);
        gap: .34rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .46rem;
        border: 1px solid var(--dr-border);
        border-radius: 9px;
        background: #fff;
        color: var(--dr-text-2);
        cursor: pointer;
        font-size: .69rem;
        font-weight: 760;
        line-height: 1.2;
        text-align: center;
        transition:
            border-color 140ms ease,
            background 140ms ease,
            color 140ms ease,
            transform 140ms ease;
    }

    .dr-choice-icon {
        width: 27px;
        height: 27px;
        border-radius: 8px;
        background: var(--dr-slate-soft);
        color: var(--dr-slate);
    }

    .dr-choice-icon > i,
    .dr-choice-icon > svg {
        display: block;
        width: 13px;
        height: 13px;
        margin: 0;
    }

    .dr-choice input:checked + span {
        border-color: rgba(124, 58, 237, .22);
        background: var(--dr-violet-soft);
        color: var(--dr-violet);
    }

    .dr-choice input:checked + span .dr-choice-icon {
        background: #fff;
        color: var(--dr-violet);
    }

    .dr-choice input:focus-visible + span {
        outline: 2px solid rgba(124, 58, 237, .20);
        outline-offset: 1px;
    }

    .dr-choice > span:hover {
        transform: translateY(-1px);
    }

    /* Loading / erro */
    .dr-loading {
        display: grid;
        min-height: 62px;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .48rem;
        align-items: center;
        padding: .52rem .58rem;
        border: 1px dashed var(--dr-border-strong);
        border-radius: 10px;
        background: var(--dr-soft);
        color: var(--dr-text-3);
        font-size: .69rem;
        line-height: 1.35;
    }

    .dr-loading::before {
        content: "";
        width: 26px;
        height: 26px;
        border: 3px solid var(--dr-blue-soft);
        border-top-color: var(--dr-blue);
        border-radius: 50%;
        animation: dr-spin .8s linear infinite;
    }

    .dr-loading:not(:has(+ .dr-filters[hidden]))::before {
        animation: none;
    }

    @keyframes dr-spin {
        to { transform: rotate(360deg); }
    }

    .dr-error {
        display: none;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .4rem;
        align-items: center;
        padding: .52rem .58rem;
        border: 1px solid rgba(207, 63, 63, .16);
        border-radius: 9px;
        background: var(--dr-red-soft);
        color: #991b1b;
        font-size: .69rem;
        font-weight: 700;
        line-height: 1.4;
    }

    .dr-error::before {
        content: "!";
        display: inline-flex;
        width: 22px;
        height: 22px;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: #fff;
        color: var(--dr-red);
        font-size: .68rem;
        font-weight: 900;
    }

    /* Filtros */
    .dr-filters {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .4rem;
    }

    .dr-filter {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--dr-border);
        border-radius: 10px;
        background: #fff;
    }

    .dr-filter[open] {
        border-color: rgba(37, 99, 235, .16);
        box-shadow: 0 4px 12px rgba(15, 35, 24, .035);
    }

    .dr-filter summary {
        display: grid;
        min-width: 0;
        min-height: 44px;
        grid-template-columns: auto minmax(0, 1fr) auto auto;
        gap: .35rem;
        align-items: center;
        padding: .42rem .48rem;
        color: var(--dr-text-2);
        cursor: pointer;
        font-size: .68rem;
        font-weight: 770;
        list-style: none;
    }

    .dr-filter summary::-webkit-details-marker {
        display: none;
    }

    .dr-filter summary::after {
        content: "";
        width: 7px;
        height: 7px;
        border-right: 1.5px solid var(--dr-text-3);
        border-bottom: 1.5px solid var(--dr-text-3);
        transform: rotate(45deg) translateY(-2px);
        transition: transform 140ms ease;
    }

    .dr-filter[open] summary::after {
        transform: rotate(225deg) translate(-1px, -1px);
    }

    .dr-filter-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: var(--dr-slate-soft);
        color: var(--dr-slate);
    }

    .dr-filter.products .dr-filter-icon {
        background: var(--dr-violet-soft);
        color: var(--dr-violet);
    }

    .dr-filter.customers .dr-filter-icon {
        background: var(--dr-blue-soft);
        color: var(--dr-blue);
    }

    .dr-filter-icon > i,
    .dr-filter-icon > svg {
        display: block;
        width: 13px;
        height: 13px;
        margin: 0;
    }

    .dr-filter-count {
        display: inline-flex;
        min-width: 34px;
        min-height: 23px;
        align-items: center;
        justify-content: center;
        padding: .12rem .3rem;
        border-radius: 999px;
        background: var(--dr-slate-soft);
        color: var(--dr-slate);
        font-size: .59rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .dr-filter-list {
        max-height: 230px;
        overflow-y: auto;
        padding: .4rem;
        border-top: 1px solid var(--dr-border);
        background: var(--dr-soft);
        scrollbar-width: thin;
    }

    .dr-filter-search {
        position: sticky;
        z-index: 1;
        top: 0;
        min-height: 36px;
        margin-bottom: .3rem;
        padding: .38rem .46rem;
        background: #fff;
        font-size: .68rem;
    }

    .dr-option {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .4rem;
        align-items: start;
        min-height: 34px;
        padding: .34rem .28rem;
        border-radius: 7px;
        color: var(--dr-text-2);
        cursor: pointer;
        font-size: .68rem;
        line-height: 1.3;
    }

    .dr-option:hover {
        background: #fff;
        color: var(--dr-text);
    }

    .dr-option input {
        width: 15px;
        height: 15px;
        margin: .05rem 0 0;
        accent-color: var(--dr-violet);
        flex: none;
    }

    .dr-empty {
        padding: 1.15rem .5rem;
        color: var(--dr-text-3);
        font-size: .7rem;
        text-align: center;
    }

    /* Layout avançado */
    .dr-layout {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--dr-border);
        border-radius: 10px;
        background: #fff;
    }

    .dr-layout > summary {
        display: grid;
        min-width: 0;
        min-height: 45px;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .4rem;
        align-items: center;
        padding: .44rem .5rem;
        color: var(--dr-text-2);
        cursor: pointer;
        font-size: .69rem;
        font-weight: 790;
        list-style: none;
    }

    .dr-layout > summary::-webkit-details-marker {
        display: none;
    }

    .dr-layout > summary::after {
        content: "";
        width: 7px;
        height: 7px;
        margin-right: .2rem;
        border-right: 1.5px solid var(--dr-text-3);
        border-bottom: 1.5px solid var(--dr-text-3);
        transform: rotate(45deg) translateY(-2px);
        transition: transform 140ms ease;
    }

    .dr-layout[open] > summary::after {
        transform: rotate(225deg) translate(-1px, -1px);
    }

    .dr-layout-summary-icon {
        display: inline-flex;
        width: 30px;
        height: 30px;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        background: var(--dr-slate-soft);
        color: var(--dr-slate);
        line-height: 0;
    }

    .dr-layout-summary-icon > i,
    .dr-layout-summary-icon > svg {
        display: block;
        width: 14px;
        height: 14px;
    }

    .dr-layout-body {
        display: grid;
        gap: .62rem;
        padding: .58rem;
        border-top: 1px solid var(--dr-border);
        background:
            linear-gradient(
                180deg,
                var(--dr-soft),
                #fff
            );
    }

    .dr-columns {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .24rem .42rem;
    }

    .dr-column {
        display: grid;
        min-width: 0;
        min-height: 34px;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .35rem;
        align-items: center;
        padding: .28rem .34rem;
        border-radius: 7px;
        color: var(--dr-text-2);
        cursor: pointer;
        font-size: .67rem;
        line-height: 1.25;
    }

    .dr-column:hover {
        background: #fff;
        color: var(--dr-text);
    }

    .dr-column input {
        width: 15px;
        height: 15px;
        margin: 0;
        accent-color: var(--dr-violet);
    }

    .dr-customer-grouping[hidden] {
        display: none;
    }

    /* Rodapé */
    .dr-actions {
        display: flex;
        flex: 0 0 auto;
        min-width: 0;
        gap: .4rem;
        align-items: center;
        padding: .62rem .78rem
            max(.62rem, env(safe-area-inset-bottom));
        border-top: 1px solid var(--dr-border);
        background:
            linear-gradient(
                180deg,
                rgba(255,255,255,.96),
                #fff
            );
    }

    .dr-actions-spacer {
        flex: 1 1 auto;
    }

    .dr-btn {
        display: inline-flex;
        min-height: 38px;
        align-items: center;
        justify-content: center;
        gap: .3rem;
        padding: .42rem .58rem;
        border: 1px solid var(--dr-border-strong);
        border-radius: 9px;
        background: #fff;
        color: var(--dr-text-2);
        cursor: pointer;
        font: inherit;
        font-size: .68rem;
        font-weight: 790;
        white-space: nowrap;
        transition:
            transform 140ms ease,
            border-color 140ms ease,
            background 140ms ease,
            color 140ms ease;
    }

    .dr-btn:hover:not(:disabled),
    .dr-btn:focus-visible:not(:disabled) {
        outline: none;
        transform: translateY(-1px);
    }

    .dr-btn > i,
    .dr-btn > svg {
        display: block;
        width: 14px;
        height: 14px;
        margin: 0;
    }

    .dr-btn-excel {
        border-color: rgba(22, 138, 77, .16);
        background: var(--dr-green-soft);
        color: var(--dr-green);
    }

    .dr-btn-primary {
        border-color: rgba(37, 99, 235, .16);
        background: var(--dr-blue-soft);
        color: var(--dr-blue);
    }

    .dr-btn:disabled {
        cursor: wait;
        opacity: .5;
        transform: none;
    }

    .dr-btn-cancel {
        color: var(--dr-text-3);
    }

    /* Responsividade */
    @media (max-width: 760px) {
        .dr-scope-grid {
            grid-template-columns: minmax(0, 1fr) minmax(130px, .68fr) minmax(130px, .68fr);
        }

        .dr-columns {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 680px) {
        .dr-modal {
            place-items: end stretch;
            padding: 0;
            background: rgba(15, 23, 42, .42);
        }

        .dr-panel {
            position: relative;
            width: 100%;
            max-height: min(94dvh, 920px);
            border-right: 0;
            border-bottom: 0;
            border-left: 0;
            border-radius: 17px 17px 0 0;
            box-shadow: 0 -16px 44px rgba(15, 23, 42, .22);
        }

        .dr-panel::before {
            position: absolute;
            z-index: 4;
            top: 6px;
            left: 50%;
            width: 38px;
            height: 4px;
            border-radius: 999px;
            background: rgba(100, 116, 139, .30);
            content: "";
            transform: translateX(-50%);
        }

        .dr-head {
            min-height: 62px;
            padding: .82rem .62rem .58rem;
        }

        .dr-head-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
        }

        .dr-head p {
            display: none;
        }

        .dr-body {
            gap: .6rem;
            padding: .58rem .6rem .68rem;
        }

        .dr-section-head {
            min-height: 43px;
            padding: .4rem .46rem;
        }

        .dr-section-sub {
            display: none;
        }

        .dr-section-body {
            padding: .5rem;
        }

        .dr-scope-grid {
            grid-template-columns: 1fr 1fr;
            gap: .42rem;
        }

        .dr-scope-grid .dr-field:first-child {
            grid-column: 1 / -1;
        }

        .dr-modes {
            gap: .28rem;
        }

        .dr-choice > span {
            min-height: 44px;
            grid-template-columns: 1fr;
            gap: .16rem;
            padding: .3rem .24rem;
            font-size: .62rem;
        }

        .dr-choice-icon {
            width: 24px;
            height: 24px;
            margin: 0 auto;
            border-radius: 7px;
        }

        .dr-filters {
            grid-template-columns: 1fr;
            gap: .32rem;
        }

        .dr-filter summary {
            min-height: 42px;
        }

        .dr-filter-list {
            max-height: 190px;
        }

        .dr-columns {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dr-layout-body .dr-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .dr-actions {
            gap: .34rem;
            padding-right: .6rem;
            padding-left: .6rem;
        }

        .dr-actions-spacer {
            display: none;
        }

        .dr-btn {
            flex: 1 1 0;
            min-width: 0;
            min-height: 42px;
            padding: .4rem .46rem;
        }

        .dr-btn-cancel {
            width: 42px;
            min-width: 42px;
            flex: 0 0 42px;
            padding: 0;
            font-size: 0;
        }

        .dr-btn-cancel > i,
        .dr-btn-cancel > svg {
            width: 15px;
            height: 15px;
        }
    }

    @media (max-width: 380px) {
        .dr-head-icon {
            display: none;
        }

        .dr-head {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .dr-columns {
            grid-template-columns: 1fr;
        }

        .dr-layout-body .dr-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .dr-modal *,
        .dr-modal *::before,
        .dr-modal *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<style id="delivery-report-v3-refinement">
    /*
     * Modal de exportação no mesmo sistema visual de dist/edit sheets.
     * Mantém IDs, inputs, endpoints e DeliveryReports.open/close.
     */
    .dr-modal {
        --dr-vv-height:100dvh;
        background:rgba(8,24,15,.52) !important;
        backdrop-filter:blur(6px) !important;
        animation:dr-overlay-enter .17s ease both;
    }

    .dr-panel {
        width:min(840px,100%) !important;
        max-height:min(var(--dr-vv-height,92dvh),860px) !important;
        border-radius:16px !important;
        box-shadow:0 26px 70px rgba(8,24,15,.24) !important;
        animation:dr-panel-enter .24s cubic-bezier(.22,.78,.24,1) both;
    }

    .dr-head {
        min-height:70px !important;
        padding:.62rem .68rem !important;
        background:
            radial-gradient(circle at 100% 0,rgba(124,58,237,.10),transparent 17rem),
            linear-gradient(180deg,var(--dr-soft),#fff) !important;
    }

    .dr-head-icon {
        width:40px !important;
        height:40px !important;
        border-radius:11px !important;
    }

    .dr-head h2 {
        font-size:.9rem !important;
        font-weight:870 !important;
    }

    .dr-head p {
        margin-top:.04rem !important;
        font-size:.62rem !important;
    }

    .dr-close {
        width:35px !important;
        height:35px !important;
        border-radius:9px !important;
    }

    .dr-head-icon .ph-duotone {
        font-size:19px !important;
    }

    .dr-close .ph-duotone {
        font-size:16px !important;
    }

    .dr-body {
        gap:.58rem !important;
        padding:.58rem .64rem .68rem !important;
        background:var(--dr-soft);
        scrollbar-width:none !important;
    }

    .dr-body::-webkit-scrollbar,
    .dr-filter-list::-webkit-scrollbar {
        width:0;
        height:0;
    }

    .dr-section {
        border-radius:12px !important;
        background:#fff;
    }

    .dr-section-head {
        min-height:48px !important;
        padding:.44rem .5rem !important;
    }

    .dr-section-icon {
        width:32px !important;
        height:32px !important;
        border-radius:9px !important;
    }

    .dr-section-icon .ph-duotone {
        font-size:14px !important;
    }

    .dr-section-title {
        font-size:.7rem !important;
        font-weight:830 !important;
    }

    .dr-section-sub {
        font-size:.58rem !important;
    }

    .dr-section-body {
        padding:.52rem !important;
    }

    .dr-label {
        margin-bottom:.22rem !important;
        font-size:.6rem !important;
        font-weight:780 !important;
    }

    .dr-select,
    .dr-input,
    .dr-filter-search {
        min-height:42px !important;
        border-radius:9px !important;
        font-size:.68rem !important;
    }

    .dr-scope-grid {
        gap:.4rem !important;
    }

    .dr-modes {
        gap:.32rem !important;
    }

    .dr-choice > span {
        min-height:50px !important;
        border-radius:9px !important;
        font-size:.65rem !important;
        font-weight:790 !important;
    }

    .dr-choice-icon {
        width:29px !important;
        height:29px !important;
        border-radius:8px !important;
    }

    .dr-choice-icon .ph-duotone {
        font-size:14px !important;
    }

    .dr-loading {
        min-height:58px !important;
        border-style:solid !important;
        background:#fff !important;
        font-size:.65rem !important;
    }

    .dr-filters {
        gap:.34rem !important;
    }

    .dr-filter {
        border-radius:10px !important;
    }

    .dr-filter summary {
        min-height:45px !important;
        padding:.4rem .44rem !important;
        font-size:.64rem !important;
    }

    .dr-filter-icon {
        width:29px !important;
        height:29px !important;
    }

    .dr-filter-icon .ph-duotone {
        font-size:14px !important;
    }

    .dr-filter-count {
        min-height:22px !important;
        font-size:.55rem !important;
    }

    .dr-filter-list {
        max-height:220px !important;
        overscroll-behavior:contain;
        scrollbar-width:none !important;
    }

    .dr-option {
        min-height:36px !important;
        font-size:.64rem !important;
    }

    .dr-layout {
        border-radius:10px !important;
    }

    .dr-layout > summary {
        min-height:46px !important;
        font-size:.66rem !important;
        font-weight:790 !important;
    }

    .dr-columns {
        gap:.32rem !important;
    }

    .dr-column {
        min-height:38px !important;
        border-radius:8px !important;
        font-size:.63rem !important;
    }

    .dr-error {
        border-radius:9px !important;
        font-size:.65rem !important;
    }

    .dr-actions {
        min-height:62px;
        gap:.38rem !important;
        padding:.52rem .68rem max(.52rem,env(safe-area-inset-bottom)) !important;
        background:linear-gradient(180deg,#fff,var(--dr-soft)) !important;
    }

    .dr-btn {
        min-height:42px !important;
        border-radius:9px !important;
        font-size:.67rem !important;
        font-weight:800 !important;
    }

    .dr-btn .ph-duotone {
        width:auto !important;
        height:auto !important;
        font-size:15px !important;
    }

    .dr-btn-excel {
        border-color:rgba(22,138,77,.15) !important;
        background:var(--dr-green-soft) !important;
        color:var(--dr-green) !important;
    }

    .dr-btn-primary {
        border-color:rgba(124,58,237,.16) !important;
        background:var(--dr-violet-soft) !important;
        color:var(--dr-violet) !important;
    }

    .dr-modal .ph-duotone {
        font-family:"Phosphor-Duotone" !important;
        font-style:normal !important;
        font-weight:normal !important;
        line-height:1 !important;
    }

    html.dr-page-locked,
    body.dr-page-locked {
        overflow:hidden !important;
        overscroll-behavior-y:none !important;
    }

    @keyframes dr-overlay-enter {
        from { opacity:0; }
        to { opacity:1; }
    }

    @keyframes dr-panel-enter {
        from {
            opacity:.74;
            transform:translateY(18px) scale(.994);
        }
        to {
            opacity:1;
            transform:translateY(0) scale(1);
        }
    }

    @media(max-width:767px) {
        .dr-modal {
            display:flex !important;
            align-items:flex-end !important;
            justify-content:center !important;
            padding:0 !important;
        }

        .dr-modal[hidden] {
            display:none !important;
        }

        .dr-panel {
            position:relative;
            width:100% !important;
            max-width:none !important;
            max-height:min(var(--dr-vv-height,96dvh),96dvh) !important;
            margin:0 !important;
            border-right:0 !important;
            border-bottom:0 !important;
            border-left:0 !important;
            border-radius:18px 18px 0 0 !important;
            box-shadow:0 -18px 48px rgba(8,24,15,.22) !important;
        }

        .dr-panel::before {
            position:absolute;
            z-index:5;
            top:6px;
            left:50%;
            width:38px;
            height:4px;
            border-radius:999px;
            background:rgba(100,116,139,.28);
            content:"";
            transform:translateX(-50%);
        }

        .dr-head {
            min-height:68px !important;
            padding:.78rem .56rem .52rem !important;
        }

        .dr-head-icon {
            width:37px !important;
            height:37px !important;
        }

        .dr-head h2 {
            font-size:.84rem !important;
        }

        .dr-head p {
            font-size:.59rem !important;
        }

        .dr-body {
            padding:.54rem .56rem .64rem !important;
        }

        .dr-scope-grid {
            grid-template-columns:1fr 1fr !important;
        }

        .dr-scope-grid .dr-field:first-child {
            grid-column:1 / -1;
        }

        .dr-modes {
            grid-template-columns:1fr !important;
        }

        .dr-choice > span {
            min-height:46px !important;
            grid-template-columns:auto minmax(0,1fr) !important;
            justify-content:start !important;
            text-align:left !important;
        }

        .dr-filters {
            grid-template-columns:1fr !important;
        }

        .dr-filter-list {
            max-height:min(220px,36vh) !important;
        }

        .dr-grid {
            grid-template-columns:1fr 1fr !important;
        }

        .dr-actions {
            min-height:62px;
            padding:.5rem .56rem max(.5rem,env(safe-area-inset-bottom)) !important;
        }

        .dr-actions-spacer {
            display:none !important;
        }

        .dr-btn {
            min-height:44px !important;
        }

        .dr-btn-cancel {
            width:44px !important;
            min-width:44px !important;
            padding:0 !important;
            font-size:0 !important;
        }

        .dr-btn-cancel span {
            display:none !important;
        }

        .dr-btn-excel,
        .dr-btn-primary {
            flex:1 1 0 !important;
        }
    }

    @media(max-width:420px) {
        .dr-scope-grid,
        .dr-grid {
            grid-template-columns:1fr !important;
        }

        .dr-scope-grid .dr-field:first-child {
            grid-column:auto;
        }
    }

    @media(prefers-reduced-motion:reduce) {
        .dr-modal,
        .dr-panel {
            animation:none !important;
        }
    }
</style>


<div
    class="dr-modal"
    id="delivery-report-modal"
    hidden
    aria-hidden="true"
>
    <section
        class="dr-panel"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delivery-report-title"
    >
        <header class="dr-head">
            <span class="dr-head-icon" aria-hidden="true">
                <i class="ph-duotone ph-chart-bar"></i>
            </span>

            <div class="dr-head-copy">
                <h2 id="delivery-report-title">
                    Gerar relatório
                </h2>
                <p>
                    Defina o período, os filtros e o formato de saída.
                </p>
            </div>

            <button
                class="dr-close"
                type="button"
                data-dr-close
                aria-label="Fechar relatório"
                title="Fechar"
            >
                <i class="ph-duotone ph-x"></i>
            </button>
        </header>

        <div class="dr-body">
            <section class="dr-section">
                <header class="dr-section-head">
                    <span class="dr-section-icon" aria-hidden="true">
                        <i class="ph-duotone ph-calendar-dots"></i>
                    </span>

                    <div>
                        <div class="dr-section-title">
                            Período
                        </div>
                        <div class="dr-section-sub">
                            Projeto e período incluídos no relatório.
                        </div>
                    </div>
                </header>

                <div class="dr-section-body">
                    <div class="dr-scope-grid">
                        <div class="dr-field">
                            <label class="dr-label" for="dr-project">
                                Projeto
                            </label>
                            <select class="dr-select" id="dr-project">
                                <option value="">
                                    Selecione um projeto
                                </option>
                                @foreach($reportProjects as $projectId => $projectTitle)
                                    <option
                                        value="{{ $projectId }}"
                                        @selected((int) $projectId === $selectedReportProject)
                                    >
                                        {{ $projectTitle }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="dr-field">
                            <label class="dr-label" for="dr-date-from">
                                Data inicial
                            </label>
                            <input
                                class="dr-input"
                                id="dr-date-from"
                                type="date"
                            >
                        </div>

                        <div class="dr-field">
                            <label class="dr-label" for="dr-date-to">
                                Data final
                            </label>
                            <input
                                class="dr-input"
                                id="dr-date-to"
                                type="date"
                            >
                        </div>
                    </div>
                </div>
            </section>

            <section class="dr-section">
                <header class="dr-section-head">
                    <span class="dr-section-icon violet" aria-hidden="true">
                        <i class="ph-duotone ph-rows"></i>
                    </span>

                    <div>
                        <div class="dr-section-title">
                            Organização
                        </div>
                        <div class="dr-section-sub">
                            Escolha como os dados serão agrupados.
                        </div>
                    </div>
                </header>

                <div class="dr-section-body dr-mode-wrap">
                    <div class="dr-modes" role="radiogroup" aria-label="Organizar relatório por">
                        <label class="dr-choice">
                            <input
                                type="radio"
                                name="dr-type"
                                value="associate"
                                checked
                            >
                            <span>
                                <span class="dr-choice-icon" aria-hidden="true">
                                    <i class="ph-duotone ph-users-three"></i>
                                </span>
                                <span data-dr-member-label>Por membro</span>
                            </span>
                        </label>

                        <label class="dr-choice">
                            <input
                                type="radio"
                                name="dr-type"
                                value="product"
                            >
                            <span>
                                <span class="dr-choice-icon" aria-hidden="true">
                                    <i class="ph-duotone ph-package"></i>
                                </span>
                                <span>Por produto</span>
                            </span>
                        </label>

                        <label class="dr-choice">
                            <input
                                type="radio"
                                name="dr-type"
                                value="customer"
                            >
                            <span>
                                <span class="dr-choice-icon" aria-hidden="true">
                                    <i class="ph-duotone ph-buildings"></i>
                                </span>
                                <span>Por cliente</span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

            <div class="dr-loading" id="dr-loading">
                Selecione um projeto para carregar os filtros.
            </div>

            <div class="dr-filters" id="dr-filters" hidden>
                <details class="dr-filter members">
                    <summary>
                        <span class="dr-filter-icon" aria-hidden="true">
                            <i class="ph-duotone ph-users-three"></i>
                        </span>
                        <span data-dr-members-title>Membros</span>
                        <span
                            class="dr-filter-count"
                            data-dr-count="members"
                        >
                            Todos
                        </span>
                    </summary>

                    <div class="dr-filter-list">
                        <input
                            class="dr-filter-search"
                            type="search"
                            placeholder="Buscar membro"
                            data-dr-search="members"
                            autocomplete="off"
                            aria-label="Buscar membro"
                        >
                        <div data-dr-list="members"></div>
                    </div>
                </details>

                <details class="dr-filter products">
                    <summary>
                        <span class="dr-filter-icon" aria-hidden="true">
                            <i class="ph-duotone ph-package"></i>
                        </span>
                        <span>Produtos</span>
                        <span
                            class="dr-filter-count"
                            data-dr-count="products"
                        >
                            Todos
                        </span>
                    </summary>

                    <div class="dr-filter-list">
                        <input
                            class="dr-filter-search"
                            type="search"
                            placeholder="Buscar produto"
                            data-dr-search="products"
                            autocomplete="off"
                            aria-label="Buscar produto"
                        >
                        <div data-dr-list="products"></div>
                    </div>
                </details>

                <details class="dr-filter customers">
                    <summary>
                        <span class="dr-filter-icon" aria-hidden="true">
                            <i class="ph-duotone ph-buildings"></i>
                        </span>
                        <span>Clientes</span>
                        <span
                            class="dr-filter-count"
                            data-dr-count="customers"
                        >
                            Todos
                        </span>
                    </summary>

                    <div class="dr-filter-list">
                        <input
                            class="dr-filter-search"
                            type="search"
                            placeholder="Buscar cliente"
                            data-dr-search="customers"
                            autocomplete="off"
                            aria-label="Buscar cliente"
                        >
                        <div data-dr-list="customers"></div>
                    </div>
                </details>
            </div>

            <details
                class="dr-layout"
                id="dr-layout"
                hidden
            >
                <summary>
                    <span class="dr-layout-summary-icon" aria-hidden="true">
                        <i class="ph-duotone ph-sliders-horizontal"></i>
                    </span>
                    <span>Layout e colunas</span>
                </summary>

                <div class="dr-layout-body">
                    <div
                        class="dr-columns"
                        id="dr-columns"
                    ></div>

                    <div class="dr-grid">
                        <div class="dr-field">
                            <label
                                class="dr-label"
                                for="dr-orientation"
                            >
                                Orientação do PDF
                            </label>
                            <select
                                class="dr-select"
                                id="dr-orientation"
                            >
                                <option value="portrait">Retrato</option>
                                <option value="landscape">Paisagem</option>
                            </select>
                        </div>

                        <div class="dr-field">
                            <label
                                class="dr-label"
                                for="dr-scale"
                            >
                                Escala da tabela
                            </label>
                            <select
                                class="dr-select"
                                id="dr-scale"
                            >
                                <option value="100">100%</option>
                                <option value="90">90%</option>
                                <option value="85">85%</option>
                                <option value="75">75%</option>
                            </select>
                        </div>
                    </div>

                    <div
                        class="dr-customer-grouping"
                        id="dr-customer-grouping"
                        hidden
                    >
                        <label
                            class="dr-label"
                            for="dr-grouping"
                        >
                            Agrupar distribuições do cliente
                        </label>
                        <select
                            class="dr-select"
                            id="dr-grouping"
                        >
                            <option value="product">
                                Por data e produto
                            </option>
                            <option value="associate">
                                Por data, produto e membro
                            </option>
                            <option value="none">
                                Sem consolidar
                            </option>
                        </select>
                    </div>
                </div>
            </details>

            <div
                class="dr-error"
                id="dr-error"
                role="alert"
                aria-live="polite"
            ></div>
        </div>

        <footer class="dr-actions">
            <button
                class="dr-btn dr-btn-cancel"
                type="button"
                data-dr-close
                title="Cancelar"
                aria-label="Cancelar e fechar"
            >
                <i class="ph-duotone ph-x"></i>
                <span>Cancelar</span>
            </button>

            <span class="dr-actions-spacer" aria-hidden="true"></span>

            <button
                class="dr-btn dr-btn-excel"
                type="button"
                data-dr-export="xlsx"
            >
                <i class="ph-duotone ph-file-xls"></i>
                <span>Excel</span>
            </button>

            <button
                class="dr-btn dr-btn-primary"
                type="button"
                data-dr-export="pdf"
            >
                <i class="ph-duotone ph-file-pdf"></i>
                <span>PDF</span>
            </button>
        </footer>
    </section>
</div>

<script>
(() => {
    const tenant = @js($currentTenant->slug);
    const modal = document.getElementById('delivery-report-modal');
    const project = document.getElementById('dr-project');
    const loading = document.getElementById('dr-loading');
    const filters = document.getElementById('dr-filters');
    const layout = document.getElementById('dr-layout');
    const error = document.getElementById('dr-error');
    let controller = null;
    let optionData = null;
    let reportScrollY = 0;
    let reportOwnsScrollLock = false;
    let reportPushedHistory = false;
    const REPORT_HISTORY_KEY = '__sgcDeliveryReport';

    const compactReportViewport = () =>
        window.matchMedia('(max-width: 767px)').matches;

    function syncReportViewport() {
        const viewport = window.visualViewport;
        const height = viewport?.height || window.innerHeight;
        modal.style.setProperty('--dr-vv-height', `${Math.round(height)}px`);
    }

    function lockReportPage() {
        if (
            document.body.classList.contains('register-sheet-open')
            || document.body.classList.contains('dm-page-locked')
            || document.body.classList.contains('em-page-locked')
            || document.body.classList.contains('dr-page-locked')
        ) {
            syncReportViewport();
            return;
        }

        reportOwnsScrollLock = true;
        reportScrollY = window.scrollY || window.pageYOffset || 0;

        document.documentElement.classList.add('dr-page-locked');
        document.body.classList.add('dr-page-locked');

        document.body.style.position = 'fixed';
        document.body.style.top = `-${reportScrollY}px`;
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';

        syncReportViewport();
    }

    function unlockReportPage() {
        if (!reportOwnsScrollLock) return;

        reportOwnsScrollLock = false;

        document.documentElement.classList.remove('dr-page-locked');
        document.body.classList.remove('dr-page-locked');

        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';

        window.scrollTo(0, reportScrollY);
    }

    function pushReportHistory() {
        if (history.state?.[REPORT_HISTORY_KEY]) {
            reportPushedHistory = true;
            return;
        }

        history.pushState(
            {
                ...(history.state || {}),
                [REPORT_HISTORY_KEY]: true,
            },
            ''
        );

        reportPushedHistory = true;
    }

    const selected = key => [...modal.querySelectorAll(`[data-dr-list="${key}"] input:checked`)].map(input => input.value);
    const updateCount = key => {
        const count = selected(key).length;
        modal.querySelector(`[data-dr-count="${key}"]`).textContent = count ? String(count) : 'Todos';
    };
    const renderList = (key, items) => {
        const target = modal.querySelector(`[data-dr-list="${key}"]`);
        target.innerHTML = items.length ? items.map(item => `<label class="dr-option" data-dr-option><input type="checkbox" value="${Number(item.id)}"><span>${escapeHtml(item.name)}</span></label>`).join('') : '<div class="dr-empty">Nenhuma opção disponível.</div>';
        target.querySelectorAll('input').forEach(input => input.addEventListener('change', () => updateCount(key)));
        updateCount(key);
    };
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
    const showError = message => { error.textContent = message; error.style.display = 'block'; };
    const reportType = () => modal.querySelector('[name="dr-type"]:checked').value;
    function applyPreferences() {
        if (!optionData) return;
        const type = reportType();
        const preferences = optionData.report_preferences?.[type] || {};
        const selectedColumns = Array.isArray(preferences.columns) ? preferences.columns : [];
        document.getElementById('dr-columns').innerHTML = Object.entries(optionData.report_columns || {}).map(([value,label]) =>
            `<label class="dr-column"><input type="checkbox" value="${escapeHtml(value)}" ${selectedColumns.includes(value) ? 'checked' : ''}><span>${escapeHtml(value === 'associate' ? optionData.member_term : label)}</span></label>`
        ).join('');
        document.getElementById('dr-orientation').value = preferences.orientation || 'portrait';
        document.getElementById('dr-scale').value = String(preferences.table_scale || 90);
        document.getElementById('dr-grouping').value = preferences.grouping || 'product';
        document.getElementById('dr-customer-grouping').hidden = type !== 'customer';
    }

    async function loadOptions() {
        const id = Number(project.value || 0);
        controller?.abort();
        filters.hidden = true;
        layout.hidden = true;
        optionData = null;
        error.style.display = 'none';
        if (!id) { loading.textContent = 'Selecione um projeto para carregar os filtros.'; loading.hidden = false; return; }
        loading.textContent = 'Carregando filtros...'; loading.hidden = false;
        controller = new AbortController();
        try {
            const response = await fetch(`/${encodeURIComponent(tenant)}/delivery/projects/${id}/reports/options`, {headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}, signal:controller.signal});
            if (!response.ok) throw new Error('Não foi possível carregar os filtros deste projeto.');
            const data = await response.json();
            optionData = data;
            renderList('members', data.members || []);
            renderList('products', data.products || []);
            renderList('customers', data.customers || []);
            document.querySelector('[data-dr-member-label]').textContent = `Por ${String(data.member_term || 'membro').toLowerCase()}`;
            document.querySelector('[data-dr-members-title]').textContent = data.member_term_plural || 'Membros';
            document.getElementById('dr-date-from').value = data.project?.start_date || '';
            document.getElementById('dr-date-to').value = data.project?.end_date || '';
            loading.hidden = true;
            filters.hidden = false;
            layout.hidden = false;
            applyPreferences();
        } catch (exception) {
            if (exception.name === 'AbortError') return;
            loading.hidden = true;
            showError(exception.message);
        }
    }

    function closeDirect() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        unlockReportPage();
    }

    function close() {
        if (
            reportPushedHistory
            && history.state?.[REPORT_HISTORY_KEY]
        ) {
            reportPushedHistory = false;
            history.back();
            return;
        }

        closeDirect();
    }

    function open() {
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');

        lockReportPage();
        syncReportViewport();
        pushReportHistory();
        loadOptions();

        /*
         * Desktop pode focar o projeto para agilizar a operação.
         * Mobile abre sem teclado/foco forçado.
         */
        if (!compactReportViewport()) {
            window.setTimeout(
                () => project.focus({ preventScroll:true }),
                30
            );
        }
    }
    async function exportReport(format) {
        const id = Number(project.value || 0);
        if (!id) { showError('Selecione um projeto.'); return; }
        error.style.display = 'none';
        const type = reportType();
        const columns = [...document.querySelectorAll('#dr-columns input:checked')].map(input => input.value);
        if (!columns.length) { showError('Selecione ao menos uma coluna.'); return; }
        const preferencePayload = {
            type,
            columns,
            orientation:document.getElementById('dr-orientation').value,
            table_scale:Number(document.getElementById('dr-scale').value),
            grouping:type === 'customer' ? document.getElementById('dr-grouping').value : 'delivery',
        };
        const params = new URLSearchParams({format, type});
        const from = document.getElementById('dr-date-from').value;
        const to = document.getElementById('dr-date-to').value;
        if (from) params.set('date_from', from);
        if (to) params.set('date_to', to);
        [['members','associate_ids'],['products','product_ids'],['customers','customer_ids']].forEach(([key,name]) => selected(key).forEach(value => params.append(`${name}[]`, value)));
        const buttons = [...modal.querySelectorAll('[data-dr-export]')];
        buttons.forEach(button => { button.disabled = true; });
        modal.querySelector('.dr-panel').setAttribute('aria-busy', 'true');
        try {
            const preferenceResponse = await fetch(`/${encodeURIComponent(tenant)}/delivery/projects/${id}/reports/preferences`, {
                method:'PUT',
                headers:{Accept:'application/json','Content-Type':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':@js(csrf_token())},
                body:JSON.stringify(preferencePayload),
            });
            if (!preferenceResponse.ok) {
                const payload = await preferenceResponse.json().catch(() => ({}));
                throw new Error(payload.message || 'Não foi possível salvar a configuração do relatório.');
            }
            const response = await fetch(`/${encodeURIComponent(tenant)}/delivery/projects/${id}/reports/export?${params}`, {
                headers: {Accept: format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'X-Requested-With':'XMLHttpRequest'},
            });
            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.message || 'Não foi possível gerar o relatório com estes filtros.');
            }
            const blob = await response.blob();
            const disposition = response.headers.get('Content-Disposition') || '';
            const encodedName = disposition.match(/filename\*=UTF-8''([^;]+)/i)?.[1];
            const fallbackName = disposition.match(/filename="?([^";]+)"?/i)?.[1];
            const fileName = encodedName ? decodeURIComponent(encodedName) : (fallbackName || `relatorio.${format}`);
            if (format === 'pdf' && window.SgcDocuments) {
                await window.SgcDocuments.openPdf(blob, fileName, 'Relatório SGC');
            } else {
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                link.remove();
            }
        } catch (exception) {
            showError(exception.message || 'Não foi possível gerar o relatório.');
        } finally {
            buttons.forEach(button => { button.disabled = false; });
            modal.querySelector('.dr-panel').removeAttribute('aria-busy');
        }
    }

    project.addEventListener('change', loadOptions);
    modal.querySelectorAll('[name="dr-type"]').forEach(input => input.addEventListener('change', applyPreferences));
    modal.querySelectorAll('[data-dr-close]').forEach(button => button.addEventListener('click', close));
    modal.querySelectorAll('[data-dr-export]').forEach(button => button.addEventListener('click', () => exportReport(button.dataset.drExport)));
    modal.querySelectorAll('[data-dr-search]').forEach(input => input.addEventListener('input', () => {
        const query = input.value.trim().toLocaleLowerCase('pt-BR');
        modal.querySelectorAll(`[data-dr-list="${input.dataset.drSearch}"] [data-dr-option]`).forEach(option => { option.hidden = query !== '' && !option.textContent.toLocaleLowerCase('pt-BR').includes(query); });
    }));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !modal.hidden) {
            close();
        }
    });

    window.addEventListener('popstate', () => {
        if (!modal.hidden && !history.state?.[REPORT_HISTORY_KEY]) {
            reportPushedHistory = false;
            closeDirect();
        }
    });

    if (window.visualViewport) {
        window.visualViewport.addEventListener(
            'resize',
            syncReportViewport,
            { passive:true }
        );
        window.visualViewport.addEventListener(
            'scroll',
            syncReportViewport,
            { passive:true }
        );
    }

    syncReportViewport();

    window.DeliveryReports = {open, close};
})();
</script>
@endonce
