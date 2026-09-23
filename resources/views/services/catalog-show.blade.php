@extends('layouts.bento')

@section('title', $version->service->name)
@section(
    'page-title',
    $version->service->name.' · versão '.$version->version
)
@section('user-role', 'Configuração do serviço')

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

    $labels = \App\Support\ServiceConfigurationLabels::class;

    $numericFields = $version
        ->fields
        ->whereIn(
            'type',
            [
                'integer',
                'decimal',
                'money',
                'quantity',
                'meter',
            ]
        );

    $evidenceFields = $version
        ->fields
        ->whereIn(
            'type',
            [
                'image',
                'file',
                'signature',
            ]
        );

    $fieldOptions = fn ($selected = null) =>
        $numericFields;

    $statusLabels = [
        'draft' => 'Rascunho',
        'published' => 'Publicada',
        'retired' => 'Substituída',
    ];

    $statusMeta = match ($version->status) {
        'draft' => [
            'label' => 'Rascunho',
            'class' => 'is-draft',
            'icon' => 'ph-note-pencil',
        ],

        'published' => [
            'label' => 'Publicada',
            'class' => 'is-published',
            'icon' => 'ph-check-circle',
        ],

        'retired' => [
            'label' => 'Substituída',
            'class' => 'is-retired',
            'icon' => 'ph-archive-box',
        ],

        default => [
            'label' => $statusLabels[$version->status]
                ?? \Illuminate\Support\Str::headline(
                    (string) $version->status
                ),
            'class' => 'is-neutral',
            'icon' => 'ph-circle',
        ],
    };

    $fieldsCount = $version->fields->count();

    $rulesCount = count(
        (array) data_get(
            $version->financial_config,
            'rules',
            []
        )
    );

    $providerRatesCount =
        $version->providerRates->count();

    $isDraft =
        $version->status === 'draft';

    $fieldStoreUrl = route(
        'services.catalog.fields.store',
        [$tenantSlug, $version]
    );

    $fieldUpdateUrlTemplate = route(
        'services.catalog.fields.update',
        [$tenantSlug, $version, '__FIELD__']
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
    .catalog-config {
        --cc-green: var(--ws-green, #219653);
        --cc-green-soft: #edf8f2;
        --cc-green-border: #cce8d7;

        --cc-blue: var(--ws-blue, #3478d4);
        --cc-blue-soft: #edf4ff;
        --cc-blue-border: #cfe0f7;

        --cc-violet: var(--ws-purple, #8a4bd2);
        --cc-violet-soft: #f5efff;
        --cc-violet-border: #e1d2f4;

        --cc-amber: var(--ws-amber, #c38418);
        --cc-amber-soft: #fff7e8;
        --cc-amber-border: #f0dcae;

        --cc-red: var(--ws-red, #cf5050);
        --cc-red-soft: #fff0f0;
        --cc-red-border: #efcaca;

        --cc-cyan: #168eae;
        --cc-cyan-soft: #ecf8fb;
        --cc-cyan-border: #cae8ef;

        --cc-text: #17211d;
        --cc-text-2: #59655f;
        --cc-muted: #89938e;
        --cc-border: #dde5e0;
        --cc-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1280px);
        min-width: 0;
        gap: .7rem;
        margin-inline: auto;
        color: var(--cc-text);
    }

    .catalog-config *,
    .catalog-config *::before,
    .catalog-config *::after,
    .cc-dialog *,
    .cc-dialog *::before,
    .cc-dialog *::after {
        box-sizing: border-box;
    }

    .catalog-config a {
        text-decoration: none;
    }

    .catalog-config button,
    .catalog-config input,
    .catalog-config select,
    .catalog-config textarea,
    .cc-dialog button,
    .cc-dialog input,
    .cc-dialog select,
    .cc-dialog textarea {
        font: inherit;
    }

    /* =========================================================
       HEADER
       ========================================================= */

    .cc-head {
        display: grid;
        min-width: 0;
        gap: .65rem;
        padding: .76rem .84rem;
        border: 1px solid var(--cc-border);
        border-radius: 12px;
        background: #fff;
    }

    .cc-head-top {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
    }

    .cc-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 42px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .cc-head-icon {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 10px;
        background: var(--cc-violet-soft);
        color: var(--cc-violet);
        font-size: 1rem;
    }

    .cc-head-copy {
        min-width: 0;
    }

    .cc-head-copy small {
        display: block;
        color: var(--cc-muted);
        font-size: .61rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .cc-head-copy h1 {
        margin: .06rem 0 0;
        overflow: hidden;
        color: var(--cc-text);
        font-size: clamp(1.03rem, 2vw, 1.24rem);
        font-weight: 860;
        line-height: 1.15;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cc-head-copy p {
        margin: .12rem 0 0;
        color: var(--cc-muted);
        font-size: .65rem;
        line-height: 1.4;
    }

    .cc-head-copy p strong {
        color: var(--cc-text-2);
        font-weight: 750;
    }

    .cc-status {
        --tone: var(--cc-muted);
        --soft: var(--cc-soft);
        --border: var(--cc-border);

        display: inline-flex;
        width: max-content;
        min-height: 31px;
        gap: .26rem;
        align-items: center;
        padding: .25rem .42rem;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .6rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .cc-status.is-draft {
        --tone: var(--cc-amber);
        --soft: var(--cc-amber-soft);
        --border: var(--cc-amber-border);
    }

    .cc-status.is-published {
        --tone: var(--cc-green);
        --soft: var(--cc-green-soft);
        --border: var(--cc-green-border);
    }

    .cc-status.is-retired {
        --tone: var(--cc-red);
        --soft: var(--cc-red-soft);
        --border: var(--cc-red-border);
    }

    .cc-head-actions {
        display: flex;
        gap: .4rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .cc-action {
        display: inline-flex;
        min-height: 37px;
        gap: .27rem;
        align-items: center;
        justify-content: center;
        padding: .36rem .53rem;
        border: 1px solid var(--cc-border);
        border-radius: 8px;
        background: #fff;
        color: var(--cc-text-2);
        cursor: pointer;
        font-size: .64rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .cc-action.primary {
        border-color: var(--cc-green);
        background: var(--cc-green);
        color: #fff;
    }

    .cc-action.blue {
        border-color: var(--cc-blue-border);
        background: var(--cc-blue-soft);
        color: var(--cc-blue);
    }

    .cc-action.violet {
        border-color: var(--cc-violet-border);
        background: var(--cc-violet-soft);
        color: var(--cc-violet);
    }

    .cc-action.danger {
        border-color: var(--cc-red-border);
        background: var(--cc-red-soft);
        color: var(--cc-red);
    }

    .cc-action:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }

    /* =========================================================
       TABS
       ========================================================= */

    .cc-tabs {
        display: flex;
        min-width: 0;
        overflow-x: auto;
        border: 1px solid var(--cc-border);
        border-radius: 11px;
        background: #fff;
        scrollbar-width: thin;
        scrollbar-color: #cfd8d2 transparent;
    }

    .cc-tabs::-webkit-scrollbar {
        height: 5px;
    }

    .cc-tabs::-webkit-scrollbar-track {
        background: transparent;
    }

    .cc-tabs::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: #cfd8d2;
    }

    .cc-tab {
        position: relative;
        display: flex;
        min-width: max-content;
        flex: 1 0 auto;
        gap: .38rem;
        align-items: center;
        justify-content: center;
        min-height: 46px;
        padding: .42rem .62rem;
        border: 0;
        border-right: 1px solid var(--cc-border);
        background: #fff;
        color: var(--cc-muted);
        cursor: pointer;
    }

    .cc-tab:last-child {
        border-right: 0;
    }

    .cc-tab::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 2px;
        background: transparent;
        content: "";
    }

    .cc-tab i {
        font-size: .76rem;
    }

    .cc-tab-copy {
        display: grid;
        gap: .01rem;
        text-align: left;
    }

    .cc-tab-copy strong {
        color: inherit;
        font-size: .61rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .cc-tab-copy small {
        color: var(--cc-muted);
        font-size: .48rem;
        white-space: nowrap;
    }

    .cc-tab-count {
        display: inline-grid;
        min-width: 22px;
        min-height: 22px;
        place-items: center;
        padding: 0 .25rem;
        border-radius: 6px;
        background: var(--cc-soft);
        color: var(--cc-muted);
        font-size: .5rem;
        font-weight: 800;
    }

    .cc-tab.active {
        background: var(--cc-blue-soft);
        color: var(--cc-blue);
    }

    .cc-tab.active::after {
        background: var(--cc-blue);
    }

    .cc-tab.active .cc-tab-count {
        background: #fff;
        color: var(--cc-blue);
    }

    .cc-tab:focus-visible {
        z-index: 2;
        outline: 2px solid var(--cc-blue);
        outline-offset: -2px;
    }

    /* =========================================================
       TAB PANELS
       ========================================================= */

    .cc-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--cc-border);
        border-radius: 12px;
        background: #fff;
    }

    .cc-panel[hidden] {
        display: none !important;
    }

    .cc-panel-head {
        display: flex;
        min-width: 0;
        min-height: 54px;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .58rem .68rem;
        border-bottom: 1px solid var(--cc-border);
    }

    .cc-panel-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 32px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .cc-panel-title-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 8px;
        background: var(--cc-blue-soft);
        color: var(--cc-blue);
        font-size: .76rem;
    }

    .cc-panel-title-copy {
        min-width: 0;
    }

    .cc-panel-title-copy strong,
    .cc-panel-title-copy span {
        display: block;
        min-width: 0;
    }

    .cc-panel-title-copy strong {
        color: var(--cc-text);
        font-size: .73rem;
        font-weight: 820;
    }

    .cc-panel-title-copy span {
        margin-top: .03rem;
        color: var(--cc-muted);
        font-size: .55rem;
        line-height: 1.4;
    }

    .cc-panel-body {
        min-width: 0;
        padding: .7rem;
    }

    /* =========================================================
       TABLE
       ========================================================= */

    .cc-table-wrap {
        min-width: 0;
        overflow-x: auto;
    }

    .cc-table {
        width: 100%;
        min-width: 840px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .cc-table th {
        padding: .46rem .52rem;
        border-bottom: 1px solid var(--cc-border);
        background: var(--cc-soft);
        color: var(--cc-muted);
        font-size: .51rem;
        font-weight: 790;
        letter-spacing: .025em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .cc-table td {
        padding: .52rem;
        border-bottom: 1px solid var(--cc-border);
        color: var(--cc-text-2);
        font-size: .62rem;
        vertical-align: middle;
    }

    .cc-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .cc-field-main {
        min-width: 0;
    }

    .cc-field-main strong,
    .cc-field-main small {
        display: block;
        min-width: 0;
    }

    .cc-field-main strong {
        overflow: hidden;
        color: var(--cc-text);
        font-size: .66rem;
        font-weight: 790;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cc-field-main small {
        margin-top: .02rem;
        color: var(--cc-muted);
        font-family:
            ui-monospace,
            SFMono-Regular,
            Menlo,
            Monaco,
            Consolas,
            monospace;
        font-size: .49rem;
    }

    .cc-yes,
    .cc-no {
        display: inline-flex;
        min-height: 25px;
        align-items: center;
        padding: .18rem .32rem;
        border-radius: 6px;
        font-size: .5rem;
        font-weight: 750;
    }

    .cc-yes {
        background: var(--cc-green-soft);
        color: var(--cc-green);
    }

    .cc-no {
        background: var(--cc-soft);
        color: var(--cc-muted);
    }

    /* =========================================================
       FORMS / WORKSPACE
       ========================================================= */

    .cc-form {
        display: grid;
        gap: .7rem;
    }

    .cc-section {
        min-width: 0;
        padding: .05rem 0 .7rem;
        border-bottom: 1px solid var(--cc-border);
    }

    .cc-section:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .cc-section-head {
        display: flex;
        min-width: 0;
        gap: .55rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: .55rem;
    }

    .cc-section-copy {
        min-width: 0;
    }

    .cc-section-copy strong,
    .cc-section-copy span {
        display: block;
    }

    .cc-section-copy strong {
        color: var(--cc-text);
        font-size: .69rem;
        font-weight: 810;
    }

    .cc-section-copy span {
        margin-top: .03rem;
        color: var(--cc-muted);
        font-size: .54rem;
        line-height: 1.4;
    }

    .cc-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .58rem;
    }

    .cc-field {
        display: grid;
        min-width: 0;
        gap: .25rem;
    }

    .cc-field.full {
        grid-column: 1 / -1;
    }

    .cc-label {
        display: flex;
        gap: .28rem;
        align-items: center;
        color: var(--cc-text-2);
        font-size: .58rem;
        font-weight: 730;
    }

    .cc-label i {
        color: var(--cc-muted);
        font-size: .64rem;
    }

    .cc-control {
        width: 100%;
        min-width: 0;
        min-height: 40px;
        padding: .44rem .5rem;
        border: 1px solid var(--cc-border);
        border-radius: 8px;
        outline: 0;
        background: #fff;
        color: var(--cc-text);
        font-size: .66rem;
    }

    textarea.cc-control {
        min-height: 84px;
        resize: vertical;
    }

    .cc-control:focus {
        border-color: var(--cc-blue);
        box-shadow: 0 0 0 3px var(--cc-blue-soft);
    }

    .cc-help {
        color: var(--cc-muted);
        font-size: .5rem;
        line-height: 1.4;
    }

    .cc-checks {
        display: grid;
        grid-template-columns:
            repeat(
                auto-fit,
                minmax(210px, 1fr)
            );
        gap: .42rem;
    }

    .cc-check {
        display: flex;
        min-width: 0;
        gap: .45rem;
        align-items: flex-start;
        padding: .48rem .52rem;
        border: 1px solid var(--cc-border);
        border-radius: 8px;
        background: var(--cc-soft);
        color: var(--cc-text-2);
        cursor: pointer;
        font-size: .57rem;
        line-height: 1.4;
    }

    .cc-check input {
        margin-top: .08rem;
        accent-color: var(--cc-green);
    }

    .cc-inline-note {
        display: flex;
        gap: .34rem;
        align-items: flex-start;
        padding: .48rem .52rem;
        border-radius: 8px;
        background: var(--cc-soft);
        color: var(--cc-text-2);
        font-size: .54rem;
        line-height: 1.45;
    }

    .cc-inline-note i {
        margin-top: .04rem;
        color: var(--cc-muted);
        flex: 0 0 auto;
        font-size: .64rem;
    }

    .cc-form-actions {
        display: flex;
        gap: .45rem;
        justify-content: flex-end;
        padding-top: .62rem;
        border-top: 1px solid var(--cc-border);
    }

    /* =========================================================
       FINANCEIRO
       ========================================================= */

    .cc-financial-overview {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        overflow: hidden;
        margin-bottom: .7rem;
        border: 1px solid var(--cc-border);
        border-radius: 9px;
    }

    .cc-financial-side {
        --tone: var(--cc-green);
        --soft: var(--cc-green-soft);

        display: grid;
        min-width: 0;
        gap: .3rem;
        padding: .62rem;
        background: #fff;
    }

    .cc-financial-side + .cc-financial-side {
        border-left: 1px solid var(--cc-border);
    }

    .cc-financial-side.provider {
        --tone: var(--cc-amber);
        --soft: var(--cc-amber-soft);
    }

    .cc-financial-side-head {
        display: flex;
        gap: .35rem;
        align-items: center;
        color: var(--tone);
        font-size: .62rem;
        font-weight: 800;
    }

    .cc-financial-side strong {
        overflow: hidden;
        color: var(--cc-text);
        font-size: .68rem;
        font-weight: 810;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cc-financial-side small {
        color: var(--cc-muted);
        font-size: .51rem;
        line-height: 1.4;
    }

    .cc-financial-block {
        min-width: 0;
        padding: .7rem 0;
        border-bottom: 1px solid var(--cc-border);
    }

    .cc-financial-block:first-child {
        padding-top: 0;
    }

    .cc-financial-block:last-child {
        padding-bottom: 0;
        border-bottom: 0;
    }

    .cc-financial-block-head {
        display: flex;
        min-width: 0;
        gap: .6rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: .55rem;
    }

    .cc-financial-block-title {
        display: grid;
        gap: .02rem;
    }

    .cc-financial-block-title strong {
        color: var(--cc-text);
        font-size: .69rem;
        font-weight: 810;
    }

    .cc-financial-block-title small {
        color: var(--cc-muted);
        font-size: .52rem;
    }

    .cc-enabled {
        display: inline-flex;
        min-height: 26px;
        align-items: center;
        padding: .18rem .34rem;
        border-radius: 6px;
        background: var(--cc-green-soft);
        color: var(--cc-green);
        font-size: .5rem;
        font-weight: 780;
    }

    .cc-disabled {
        background: var(--cc-soft);
        color: var(--cc-muted);
    }

    #financial-rules {
        display: grid;
        gap: .5rem;
    }

    /* =========================================================
       PROVIDER RATES
       ========================================================= */

    .cc-rate-list {
        display: grid;
    }

    .cc-rate {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .6rem;
        align-items: center;
        min-width: 0;
        padding: .54rem 0;
        border-bottom: 1px solid var(--cc-border);
    }

    .cc-rate:last-child {
        border-bottom: 0;
    }

    .cc-rate-copy {
        min-width: 0;
    }

    .cc-rate-copy strong,
    .cc-rate-copy small {
        display: block;
        min-width: 0;
    }

    .cc-rate-copy strong {
        overflow: hidden;
        color: var(--cc-text);
        font-size: .65rem;
        font-weight: 790;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cc-rate-copy small {
        margin-top: .02rem;
        color: var(--cc-muted);
        font-size: .51rem;
    }

    .cc-rate-value {
        color: var(--cc-violet);
        font-size: .64rem;
        font-weight: 810;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .cc-empty {
        display: grid;
        min-height: 180px;
        place-items: center;
        padding: 1rem;
        color: var(--cc-muted);
        font-size: .62rem;
        text-align: center;
    }

    /* =========================================================
       DIALOG
       ========================================================= */

    .cc-dialog {
        width: min(94vw, 760px);
        max-width: 760px;
        max-height: min(90dvh, 820px);
        margin: auto;
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 14px;
        background: #fff;
        color: var(--cc-text);
        box-shadow: 0 24px 80px rgba(21, 49, 31, .22);
    }

    .cc-dialog::backdrop {
        background: rgba(10, 22, 14, .58);
    }

    .cc-dialog-layout {
        display: grid;
        max-height: min(90dvh, 820px);
        grid-template-rows: auto minmax(0, 1fr) auto;
    }

    .cc-dialog-head {
        display: flex;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .7rem .75rem;
        border-bottom: 1px solid var(--cc-border);
    }

    .cc-dialog-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .cc-dialog-title-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--cc-violet-soft);
        color: var(--cc-violet);
        font-size: .8rem;
    }

    .cc-dialog-title-copy {
        min-width: 0;
    }

    .cc-dialog-title-copy small,
    .cc-dialog-title-copy strong {
        display: block;
    }

    .cc-dialog-title-copy small {
        color: var(--cc-muted);
        font-size: .53rem;
    }

    .cc-dialog-title-copy strong {
        margin-top: .02rem;
        color: var(--cc-text);
        font-size: .79rem;
        font-weight: 820;
    }

    .cc-dialog-close {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: var(--cc-soft);
        color: var(--cc-text-2);
        cursor: pointer;
        font-size: .85rem;
    }

    .cc-dialog-body {
        min-height: 0;
        overflow-y: auto;
        padding: .72rem;
    }

    .cc-dialog-foot {
        display: flex;
        gap: .45rem;
        align-items: center;
        justify-content: flex-end;
        padding: .62rem .7rem;
        border-top: 1px solid var(--cc-border);
        background: var(--cc-soft);
    }

    /* =========================================================
       MOBILE
       ========================================================= */

    .cc-mobile-fields,
    .cc-mobile-versions {
        display: none;
    }

    @media (max-width: 800px) {
        .cc-head-top {
            grid-template-columns: minmax(0, 1fr);
        }

        .cc-status {
            position: absolute;
            top: .72rem;
            right: .72rem;
        }

        .cc-head {
            position: relative;
        }

        .cc-head-actions {
            padding-right: 0;
        }

        .cc-financial-overview {
            grid-template-columns: 1fr;
        }

        .cc-financial-side + .cc-financial-side {
            border-top: 1px solid var(--cc-border);
            border-left: 0;
        }
    }

    @media (max-width: 700px) {
        .cc-table-wrap {
            display: none;
        }

        .cc-mobile-fields,
        .cc-mobile-versions {
            display: grid;
        }

        .cc-mobile-versions {
            gap:.55rem;
            padding:.65rem;
        }

        .cc-mobile-version {
            display:grid;
            gap:.45rem;
            padding:.7rem;
            border:1px solid var(--cc-border);
            border-radius:10px;
            background:#fff;
        }

        .cc-mobile-version-head {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:.5rem;
        }

        .cc-mobile-field {
            display: grid;
            min-width: 0;
            gap: .4rem;
            padding: .58rem .62rem;
            border-bottom: 1px solid var(--cc-border);
        }

        .cc-mobile-field:last-child {
            border-bottom: 0;
        }

        .cc-mobile-field-top {
            display: flex;
            min-width: 0;
            gap: .5rem;
            align-items: center;
            justify-content: space-between;
        }

        .cc-mobile-field-meta {
            display: flex;
            gap: .35rem;
            align-items: center;
            flex-wrap: wrap;
            color: var(--cc-muted);
            font-size: .52rem;
        }

        .cc-grid {
            grid-template-columns: 1fr;
        }

        .cc-field.full {
            grid-column: auto;
        }

        .cc-control {
            min-height: 44px;
            font-size: 16px;
        }

        .cc-dialog {
            width: calc(100vw - .8rem);
            max-height: calc(100dvh - .8rem);
        }

        .cc-dialog-layout {
            max-height: calc(100dvh - .8rem);
            height: calc(100dvh - .8rem);
        }

        .cc-dialog-foot {
            position:sticky;
            bottom:0;
            z-index:3;
            padding-bottom:max(.62rem, env(safe-area-inset-bottom));
        }

        .cc-dialog-foot .cc-action {
            min-height:46px;
        }
    }

    @media (max-width: 560px) {
        .cc-head {
            padding: .62rem .66rem;
        }

        .cc-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .cc-head-icon {
            width: 36px;
            height: 36px;
        }

        .cc-head-copy p {
            padding-right: 5.2rem;
        }

        .cc-head-actions {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(40px, 1fr)
                );
        }

        .cc-action {
            min-width: 0;
        }

        .cc-action span {
            display: none;
        }

        .cc-tab {
            min-width: 145px;
            justify-content: flex-start;
        }

        .cc-panel-title-copy span {
            display: none;
        }

        .cc-form-actions {
            position: sticky;
            z-index: 15;
            bottom: 0;
            margin:
                .7rem
                -.7rem
                -.7rem;
            padding:
                .58rem
                .7rem
                max(.58rem, env(safe-area-inset-bottom));
            background: #fbfdfc;
        }
    }
</style>

<main class="catalog-config">
    <header class="cc-head">
        <div class="cc-head-top">
            <div class="cc-head-main">
                <span
                    class="cc-head-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-wrench"></i>
                </span>

                <div class="cc-head-copy">
                    <small>
                        Versão {{ $version->version }}
                    </small>

                    <h1>
                        {{ $version->service->name }}
                    </h1>

                    <p>
                        Unidade:
                        <strong>{{ $version->unit }}</strong>
                        · Conferência:
                        <strong>
                            {{
                                $version->review_mode === 'manual'
                                    ? 'pela gestão'
                                    : 'automática'
                            }}
                        </strong>
                    </p>
                </div>
            </div>

            <span
                class="
                    cc-status
                    {{ $statusMeta['class'] }}
                "
            >
                <i
                    class="
                        ph-fill
                        {{ $statusMeta['icon'] }}
                    "
                ></i>

                {{ $statusMeta['label'] }}
            </span>
        </div>

        <div class="cc-head-actions">
            <a
                class="cc-action blue"
                href="{{ route(
                    'services.catalog.preview',
                    [
                        $tenantSlug,
                        $version,
                    ]
                ) }}"
                title="Ver como prestador"
            >
                <i class="ph-fill ph-eye"></i>
                <span>Ver como prestador</span>
            </a>

            @can('simulate_services')
                <a
                    class="cc-action violet"
                    href="{{ route(
                        'services.simulations.index',
                        [
                            'tenant' => $tenantSlug,
                            'version' => $version->id,
                        ]
                    ) }}"
                    title="Simular"
                >
                    <i class="ph-fill ph-flask"></i>
                    <span>Simular</span>
                </a>
            @endcan

            <form
                method="post"
                action="{{ route(
                    'services.catalog.clone',
                    [
                        $tenantSlug,
                        $version,
                    ]
                ) }}"
            >
                @csrf

                <button
                    class="cc-action"
                    type="submit"
                    title="Criar nova versão"
                >
                    <i class="ph-fill ph-copy"></i>
                    <span>Nova versão</span>
                </button>
            </form>

            @if($version->status === 'draft')
                <form
                    method="post"
                    action="{{ route(
                        'services.catalog.publish',
                        [
                            $tenantSlug,
                            $version,
                        ]
                    ) }}"
                >
                    @csrf

                    <button
                        class="cc-action primary"
                        type="submit"
                        title="Publicar"
                    >
                        <i class="ph-fill ph-paper-plane-tilt"></i>
                        <span>Publicar</span>
                    </button>
                </form>
            @endif

            @if(
                in_array(
                    $version->status,
                    [
                        'published',
                        'retired',
                    ],
                    true
                )
            )
                <form
                    method="post"
                    action="{{ route(
                        'services.catalog.active',
                        [
                            $tenantSlug,
                            $version,
                        ]
                    ) }}"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="active"
                        value="{{
                            $version->status === 'published'
                                ? 0
                                : 1
                        }}"
                    >

                    <button
                        class="
                            cc-action
                            {{
                                $version->status === 'published'
                                    ? 'danger'
                                    : ''
                            }}
                        "
                        type="submit"
                        title="{{
                            $version->status === 'published'
                                ? 'Desativar para novas ordens'
                                : 'Ativar para novas ordens'
                        }}"
                    >
                        <i
                            class="
                                ph-fill
                                {{
                                    $version->status === 'published'
                                        ? 'ph-pause-circle'
                                        : 'ph-play-circle'
                                }}
                            "
                        ></i>

                        <span>
                            {{
                                $version->status === 'published'
                                    ? 'Desativar'
                                    : 'Ativar'
                            }}
                        </span>
                    </button>
                </form>
            @endif
        </div>
    </header>

    <nav
        class="cc-tabs"
        aria-label="Configuração da versão"
    >
        <button
            class="cc-tab active"
            type="button"
            data-tab="fields"
        >
            <i class="ph-fill ph-list-checks"></i>

            <span class="cc-tab-copy">
                <strong>Campos</strong>
                <small>Dados da execução</small>
            </span>

            <span class="cc-tab-count">
                {{ $fieldsCount }}
            </span>
        </button>

        <button
            class="cc-tab"
            type="button"
            data-tab="execution"
        >
            <i class="ph-fill ph-flow-arrow"></i>

            <span class="cc-tab-copy">
                <strong>Execução</strong>
                <small>Fluxo operacional</small>
            </span>
        </button>

        <button
            class="cc-tab"
            type="button"
            data-tab="finance"
        >
            <i class="ph-fill ph-wallet"></i>

            <span class="cc-tab-copy">
                <strong>Financeiro</strong>
                <small>Cobrança e remuneração</small>
            </span>

            @if($rulesCount > 0)
                <span class="cc-tab-count">
                    {{ $rulesCount }}
                </span>
            @endif
        </button>

        <button
            class="cc-tab"
            type="button"
            data-tab="providers"
        >
            <i class="ph-fill ph-users-three"></i>

            <span class="cc-tab-copy">
                <strong>Prestadores</strong>
                <small>Tarifas específicas</small>
            </span>

            @if($providerRatesCount > 0)
                <span class="cc-tab-count">
                    {{ $providerRatesCount }}
                </span>
            @endif
        </button>
    </nav>

    {{-- ======================================================
         CAMPOS
         ====================================================== --}}

    <section class="cc-panel" aria-label="Histórico de versões e preços" style="margin-bottom:1rem">
        <header class="cc-panel-head"><div class="cc-panel-title"><span class="cc-panel-title-icon"><i class="ph-fill ph-clock-counter-clockwise"></i></span><span class="cc-panel-title-copy"><strong>Versões e preços anteriores</strong><span>Versões publicadas permanecem somente para consulta; duplique para fazer alterações.</span></span></div></header>
        <div class="cc-panel-body cc-table-wrap">
            <table class="cc-table"><thead><tr><th>Versão</th><th>Situação</th><th>Cobrança padrão</th><th>Remuneração padrão</th><th></th></tr></thead><tbody>
                @foreach($version->service->versions->sortByDesc('version') as $item)
                    <tr><td>v{{ $item->version }}</td><td>{{ $statusLabels[$item->status] ?? $item->status }}</td><td>{{ $item->receivable_enabled ? ($item->customer_pricing_method === 'percent_of_base' ? number_format((float) $item->customer_percentage, 2, ',', '.').'%' : 'R$ '.number_format((float) $item->customer_rate, 2, ',', '.')) : 'Sem cobrança' }}</td><td>{{ $item->payable_enabled ? ($item->provider_pricing_method === 'percent_of_base' ? number_format((float) $item->provider_percentage, 2, ',', '.').'%' : 'R$ '.number_format((float) $item->default_provider_rate, 2, ',', '.')) : 'Sem remuneração' }}</td><td><a class="cc-action" href="{{ route('services.catalog.show', [$tenantSlug, $item]) }}">{{ $item->id === $version->id ? 'Atual' : 'Ver versão' }}</a></td></tr>
                @endforeach
            </tbody></table>
        </div>
        <div class="cc-mobile-versions">
            @foreach($version->service->versions->sortByDesc('version') as $item)
                <article class="cc-mobile-version">
                    <div class="cc-mobile-version-head"><strong>Versão {{ $item->version }}</strong><span class="cc-status">{{ $statusLabels[$item->status] ?? $item->status }}</span></div>
                    <div class="cc-mobile-field-meta"><span>Cobrança: {{ $item->receivable_enabled ? ($item->customer_pricing_method === 'percent_of_base' ? number_format((float) $item->customer_percentage, 2, ',', '.').'%' : 'R$ '.number_format((float) $item->customer_rate, 2, ',', '.')) : 'não gera' }}</span><span>·</span><span>Prestador: {{ $item->payable_enabled ? ($item->provider_pricing_method === 'percent_of_base' ? number_format((float) $item->provider_percentage, 2, ',', '.').'%' : 'R$ '.number_format((float) $item->default_provider_rate, 2, ',', '.')) : 'não gera' }}</span></div>
                    <a class="cc-action" href="{{ route('services.catalog.show', [$tenantSlug, $item]) }}">{{ $item->id === $version->id ? 'Versão aberta' : 'Ver esta versão' }}</a>
                </article>
            @endforeach
        </div>
    </section>

    <section
        class="cc-panel"
        data-tab-panel="fields"
    >
        <header class="cc-panel-head">
            <div class="cc-panel-title">
                <span
                    class="cc-panel-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-list-checks"></i>
                </span>

                <span class="cc-panel-title-copy">
                    <strong>Dados coletados</strong>

                    <span>
                        Informações registradas durante a execução do serviço.
                    </span>
                </span>
            </div>

            @if($isDraft)
                <button
                    class="cc-action primary"
                    type="button"
                    data-open-dialog="field-dialog"
                >
                    <i class="ph-fill ph-plus-circle"></i>
                    <span>Adicionar campo</span>
                </button>
            @endif
        </header>

        <div class="cc-panel-body">
            <div class="cc-inline-note">
                <i class="ph-fill ph-info"></i>

                <span>Estes campos registram a execução. Eles somente participam de valores quando usados em uma fórmula ou regra financeira. Para exigir um comprovante junto de uma medição, crie um campo do tipo Foto ou Arquivo, vincule-o à medição e marque-o obrigatório.</span>
            </div>

            @if($version->fields->isEmpty())
                <div class="cc-empty">
                    Nenhum campo adicional configurado.
                </div>
            @else
                <div class="cc-table-wrap">
                    <table
                        class="cc-table"
                        aria-label="Campos da execução"
                    >
                        <thead>
                            <tr>
                                <th>Campo</th>
                                <th>Etapa</th>
                                <th>Formato</th>
                                <th>Obrigatório</th>
                                <th>Prestação de contas</th>
                                @if($isDraft)
                                    <th></th>
                                @endif
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($version->fields as $field)
                                @php
                                    $fieldEditPayload = $field->only([
                                        'id', 'key', 'label', 'type', 'phase',
                                        'section', 'unit', 'minimum', 'maximum',
                                        'sort_order', 'placeholder', 'help',
                                        'options', 'evidence_for_field',
                                        'accepted_mime_types', 'required',
                                        'visible_to_provider',
                                        'editable_by_provider',
                                        'visible_to_management',
                                        'include_in_documents', 'reportable',
                                    ]);
                                @endphp
                                <tr>
                                    <td>
                                        <div class="cc-field-main">
                                            <strong>
                                                {{ $field->label }}
                                            </strong>

                        <small>
                            {{ $field->key }}
                            @if($field->evidence_for_field)
                                · Anexado a {{ $version->fields->firstWhere('key', $field->evidence_for_field)?->label ?? $field->evidence_for_field }}
                            @endif
                        </small>
                                        </div>
                                    </td>

                                    <td>
                                        {{ $labels::phase($field->phase) }}
                                    </td>

                                    <td>
                                        {{ $labels::fieldType($field->type) }}
                                    </td>

                                    <td>
                                        <span
                                            class="{{
                                                $field->required
                                                    ? 'cc-yes'
                                                    : 'cc-no'
                                            }}"
                                        >
                                            {{
                                                $field->required
                                                    ? 'Sim'
                                                    : 'Não'
                                            }}
                                        </span>
                                    </td>

                                    <td>
                                        <span
                                            class="{{
                                                $field->reportable
                                                    ? 'cc-yes'
                                                    : 'cc-no'
                                            }}"
                                        >
                                            {{
                                                $field->reportable
                                                    ? 'Sim'
                                                    : 'Não'
                                            }}
                                        </span>
                                    </td>

                                    @if($isDraft)
                                        <td>
                                            <button type="button" class="cc-action" data-edit-field="{{ json_encode($fieldEditPayload, JSON_HEX_APOS | JSON_HEX_QUOT) }}" aria-label="Editar {{ $field->label }}"><i class="ph-fill ph-pencil"></i></button>
                                            <form
                                                method="post"
                                                action="{{ route(
                                                    'services.catalog.fields.delete',
                                                    [
                                                        $tenantSlug,
                                                        $version,
                                                        $field,
                                                    ]
                                                ) }}"
                                            >
                                                @csrf
                                                @method('delete')

                                                <button
                                                    class="cc-action danger"
                                                    type="submit"
                                                    title="Remover campo"
                                                >
                                                    <i class="ph-fill ph-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="cc-mobile-fields">
                    @foreach($version->fields as $field)
                        @php
                            $fieldEditPayload = $field->only([
                                'id', 'key', 'label', 'type', 'phase',
                                'section', 'unit', 'minimum', 'maximum',
                                'sort_order', 'placeholder', 'help',
                                'options', 'evidence_for_field',
                                'accepted_mime_types', 'required',
                                'visible_to_provider',
                                'editable_by_provider',
                                'visible_to_management',
                                'include_in_documents', 'reportable',
                            ]);
                        @endphp
                        <article class="cc-mobile-field">
                            <div class="cc-mobile-field-top">
                                <div class="cc-field-main">
                                    <strong>
                                        {{ $field->label }}
                                    </strong>

                                    <small>
                                        {{ $field->key }}
                                    </small>
                                </div>

                                @if($isDraft)
                                    <button type="button" class="cc-action" data-edit-field="{{ json_encode($fieldEditPayload, JSON_HEX_APOS | JSON_HEX_QUOT) }}" aria-label="Editar {{ $field->label }}"><i class="ph-fill ph-pencil"></i></button>
                                    <form
                                        method="post"
                                        action="{{ route(
                                            'services.catalog.fields.delete',
                                            [
                                                $tenantSlug,
                                                $version,
                                                $field,
                                            ]
                                        ) }}"
                                    >
                                        @csrf
                                        @method('delete')

                                        <button
                                            class="cc-action danger"
                                            type="submit"
                                            aria-label="Remover {{ $field->label }}"
                                        >
                                            <i class="ph-fill ph-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <div class="cc-mobile-field-meta">
                                <span>
                                    {{ $labels::phase($field->phase) }}
                                </span>

                                <span>·</span>

                                <span>
                                    {{ $labels::fieldType($field->type) }}
                                </span>

                                <span>·</span>

                                <span>
                                    {{
                                        $field->required
                                            ? 'Obrigatório'
                                            : 'Opcional'
                                    }}
                                </span>

                                @if($field->reportable)
                                    <span>· prestação de contas</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ======================================================
         EXECUÇÃO
         ====================================================== --}}

    <section
        class="cc-panel"
        data-tab-panel="execution"
        hidden
    >
        <header class="cc-panel-head">
            <div class="cc-panel-title">
                <span
                    class="cc-panel-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-flow-arrow"></i>
                </span>

                <span class="cc-panel-title-copy">
                    <strong>Fluxo operacional</strong>

                    <span>
                        Como a execução é medida e conferida.
                    </span>
                </span>
            </div>
        </header>

        <div class="cc-panel-body">
            @if($isDraft)
                <form
                    method="post"
                    action="{{ route(
                        'services.catalog.update',
                        [
                            $tenantSlug,
                            $version,
                        ]
                    ) }}"
                    class="cc-form"
                    id="service-config-form"
                >
                    @csrf
                    @method('put')

                    <section class="cc-section">
                        <div class="cc-section-head">
                            <div class="cc-section-copy">
                                <strong>Configuração principal</strong>

                                <span>
                                    Regras gerais da execução.
                                </span>
                            </div>
                        </div>

                        <div class="cc-grid">
                            <label class="cc-field">
                                <span class="cc-label">
                                    <i class="ph-fill ph-ruler"></i>
                                    Unidade principal
                                </span>

                                <input
                                    class="cc-control"
                                    name="unit"
                                    value="{{ $version->unit }}"
                                    required
                                >
                            </label>

                            <label class="cc-field">
                                <span class="cc-label">
                                    <i class="ph-fill ph-check-square"></i>
                                    Conferência
                                </span>

                                <select
                                    class="cc-control"
                                    name="review_mode"
                                >
                                    <option
                                        value="manual"
                                        @selected(
                                            $version->review_mode === 'manual'
                                        )
                                    >
                                        Gestor confere antes
                                    </option>

                                    <option
                                        value="automatic"
                                        @selected(
                                            $version->review_mode === 'automatic'
                                        )
                                    >
                                        Aprovação automática
                                    </option>
                                </select>
                            </label>
                        </div>

                        <div class="cc-checks">
                            <label class="cc-check">
                                <input
                                    type="checkbox"
                                    name="allow_provider_create_order"
                                    value="1"
                                    @checked(
                                        $version->allow_provider_create_order
                                    )
                                >

                                <span>
                                    Prestador habilitado pode criar ordem
                                </span>
                            </label>

                            <label class="cc-check">
                                <input
                                    type="checkbox"
                                    name="members_only"
                                    value="1"
                                    @checked($version->members_only)
                                >

                                <span>
                                    Somente membros podem receber este serviço
                                </span>
                            </label>
                        </div>
                    </section>

                    <section class="cc-section">
                        <div class="cc-section-head">
                            <div class="cc-section-copy">
                                <strong>Quantidade executada</strong>

                                <span>
                                    Define a medida principal da execução.
                                </span>
                            </div>
                        </div>

                        <div class="cc-grid">
                            <label class="cc-field">
                                <span class="cc-label">
                                    Como obter a quantidade
                                </span>

                                <select
                                    class="cc-control"
                                    name="execution_config[quantity_mode]"
                                >
                                    @foreach([
                                        'fixed_one' => 'Uma unidade por execução',
                                        'field' => 'Usar um campo numérico',
                                        'meter_difference' => 'Diferença entre duas leituras',
                                    ] as $key => $label)
                                        <option
                                            value="{{ $key }}"
                                            @selected(
                                                data_get(
                                                    $version->execution_config,
                                                    'quantity_mode',
                                                    'fixed_one'
                                                ) === $key
                                            )
                                        >
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="cc-field">
                                <span class="cc-label">
                                    Campo da quantidade
                                </span>

                                <select
                                    class="cc-control"
                                    name="execution_config[quantity_field]"
                                >
                                    <option value="">
                                        Não usar
                                    </option>

                                    @foreach($numericFields as $field)
                                        <option
                                            value="{{ $field->key }}"
                                            @selected(
                                                data_get(
                                                    $version->execution_config,
                                                    'quantity_field'
                                                ) === $field->key
                                            )
                                        >
                                            {{ $field->label }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="cc-field">
                                <span class="cc-label">
                                    Leitura inicial
                                </span>

                                <select
                                    class="cc-control"
                                    name="execution_config[meter_start_field]"
                                >
                                    <option value="">
                                        Selecione
                                    </option>

                                    @foreach($numericFields as $field)
                                        <option
                                            value="{{ $field->key }}"
                                            @selected(
                                                data_get(
                                                    $version->execution_config,
                                                    'meter_start_field'
                                                ) === $field->key
                                            )
                                        >
                                            {{ $field->label }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="cc-field">
                                <span class="cc-label">
                                    Leitura final
                                </span>

                                <select
                                    class="cc-control"
                                    name="execution_config[meter_end_field]"
                                >
                                    <option value="">
                                        Selecione
                                    </option>

                                    @foreach($numericFields as $field)
                                        <option
                                            value="{{ $field->key }}"
                                            @selected(
                                                data_get(
                                                    $version->execution_config,
                                                    'meter_end_field'
                                                ) === $field->key
                                            )
                                        >
                                            {{ $field->label }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    </section>

                    <div class="cc-form-actions">
                        <button
                            class="cc-action primary"
                            type="submit"
                        >
                            <i class="ph-fill ph-floppy-disk"></i>
                            <span>Salvar execução</span>
                        </button>
                    </div>
                </form>
            @else
                <div class="cc-empty">
                    Esta versão está protegida contra alterações.
                    Crie uma nova versão para modificar o fluxo operacional.
                </div>
            @endif
        </div>
    </section>

    {{-- ======================================================
         FINANCEIRO
         ====================================================== --}}

    <section
        class="cc-panel"
        data-tab-panel="finance"
        hidden
    >
        <header class="cc-panel-head">
            <div class="cc-panel-title">
                <span
                    class="cc-panel-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-wallet"></i>
                </span>

                <span class="cc-panel-title-copy">
                    <strong>Financeiro</strong>

                    <span>
                        Cobrança, remuneração e ajustes do serviço.
                    </span>
                </span>
            </div>

            @can('simulate_services')
                <a
                    class="cc-action violet"
                    href="{{ route(
                        'services.simulations.index',
                        [
                            'tenant' => $tenantSlug,
                            'version' => $version->id,
                        ]
                    ) }}"
                >
                    <i class="ph-fill ph-flask"></i>
                    <span>Simular</span>
                </a>
            @endcan
        </header>

        <div class="cc-panel-body">
            <div class="cc-financial-overview">
                <div class="cc-financial-side">
                    <div class="cc-financial-side-head">
                        <i class="ph-fill ph-arrow-circle-down-left"></i>
                        Cobrança
                    </div>

                    <strong>
                        {{
                            $version->receivable_enabled
                                ? (
                                    $labels::pricingMethods()[
                                        $version->customer_pricing_method
                                    ]
                                    ?? \Illuminate\Support\Str::headline(
                                        (string) $version->customer_pricing_method
                                    )
                                )
                                : 'Não gera valor a receber'
                        }}
                    </strong>

                    <small>
                        Valor da organização junto ao cliente.
                    </small>
                </div>

                <div class="cc-financial-side provider">
                    <div class="cc-financial-side-head">
                        <i class="ph-fill ph-arrow-circle-up-right"></i>
                        Prestador
                    </div>

                    <strong>
                        {{
                            $version->payable_enabled
                                ? (
                                    $labels::pricingMethods(true)[
                                        $version->provider_pricing_method
                                    ]
                                    ?? \Illuminate\Support\Str::headline(
                                        (string) $version->provider_pricing_method
                                    )
                                )
                                : 'Não gera valor a pagar'
                        }}
                    </strong>

                    <small>
                        Remuneração vinculada à execução.
                    </small>
                </div>
            </div>

            @if($isDraft)
                <form
                    method="post"
                    action="{{ route(
                        'services.catalog.update',
                        [
                            $tenantSlug,
                            $version,
                        ]
                    ) }}"
                    class="cc-form"
                >
                    @csrf
                    @method('put')

                    @foreach([
                        'customer' => [
                            'Cobrança do beneficiário',
                            'receivable_enabled',
                            'customer_pricing_method',
                            'customer_rate',
                            'customer_percentage',
                            'Valor que a organização receberá',
                            'ph-arrow-circle-down-left',
                        ],

                        'provider' => [
                            'Remuneração do prestador',
                            'payable_enabled',
                            'provider_pricing_method',
                            'default_provider_rate',
                            'provider_percentage',
                            'Valor que será pago ao prestador',
                            'ph-arrow-circle-up-right',
                        ],
                    ] as $side => $item)
                        <section class="cc-financial-block">
                            <div class="cc-financial-block-head">
                                <div class="cc-financial-block-title">
                                    <strong>
                                        {{ $item[0] }}
                                    </strong>

                                    <small>
                                        {{ $item[5] }}.
                                        Este lado é calculado de forma independente.
                                    </small>
                                </div>

                                <span
                                    class="{{
                                        $version->{$item[1]}
                                            ? 'cc-enabled'
                                            : 'cc-enabled cc-disabled'
                                    }}"
                                >
                                    {{
                                        $version->{$item[1]}
                                            ? 'Ativo'
                                            : 'Desativado'
                                    }}
                                </span>
                            </div>

                            <div class="cc-checks">
                                <label class="cc-check">
                                    <input
                                        type="checkbox"
                                        name="{{ $item[1] }}"
                                        value="1"
                                        @checked(
                                            $version->{$item[1]}
                                        )
                                    >

                                    <span>
                                        Gerar este compromisso financeiro
                                    </span>
                                </label>
                            </div>

                            <div
                                class="cc-grid"
                                style="margin-top:.55rem"
                            >
                                <label class="cc-field">
                                    <span class="cc-label">
                                        Fórmula base
                                    </span>

                                    <select
                                        class="cc-control"
                                        name="{{ $item[2] }}"
                                    >
                                        <option value="">
                                            Selecione
                                        </option>

                                        @foreach(
                                            $labels::pricingMethods(
                                                $side === 'provider'
                                            )
                                            as $key => $label
                                        )
                                            <option
                                                value="{{ $key }}"
                                                @selected(
                                                    $version->{$item[2]}
                                                        === $key
                                                )
                                            >
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="cc-field">
                                    <span class="cc-label">
                                        Fonte da quantidade
                                    </span>

                                    <select
                                        class="cc-control"
                                        name="execution_config[{{ $side }}_quantity_mode]"
                                    >
                                        @foreach(
                                            $labels::quantityModes()
                                            as $key => $label
                                        )
                                            <option
                                                value="{{ $key }}"
                                                @selected(
                                                    data_get(
                                                        $version->execution_config,
                                                        $side.'_quantity_mode',
                                                        'primary'
                                                    ) === $key
                                                )
                                            >
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="cc-field">
                                    <span class="cc-label">
                                        Campo numérico usado
                                    </span>

                                    <select
                                        class="cc-control"
                                        name="execution_config[{{ $side }}_quantity_field]"
                                    >
                                        <option value="">
                                            Selecione
                                        </option>

                                        @foreach($numericFields as $field)
                                            <option
                                                value="{{ $field->key }}"
                                                @selected(
                                                    data_get(
                                                        $version->execution_config,
                                                        $side.'_quantity_field'
                                                    ) === $field->key
                                                )
                                            >
                                                {{ $field->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="cc-field">
                                    <span class="cc-label">
                                        Unidade exibida
                                    </span>

                                    <input
                                        class="cc-control"
                                        name="execution_config[{{ $side }}_unit]"
                                        value="{{
                                            data_get(
                                                $version->execution_config,
                                                $side.'_unit',
                                                $version->unit
                                            )
                                        }}"
                                    >
                                </label>

                                <label class="cc-field">
                                    <span class="cc-label">
                                        Leitura inicial
                                    </span>

                                    <select
                                        class="cc-control"
                                        name="execution_config[{{ $side }}_meter_start_field]"
                                    >
                                        <option value="">
                                            Selecione
                                        </option>

                                        @foreach($numericFields as $field)
                                            <option
                                                value="{{ $field->key }}"
                                                @selected(
                                                    data_get(
                                                        $version->execution_config,
                                                        $side.'_meter_start_field'
                                                    ) === $field->key
                                                )
                                            >
                                                {{ $field->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="cc-field">
                                    <span class="cc-label">
                                        Leitura final
                                    </span>

                                    <select
                                        class="cc-control"
                                        name="execution_config[{{ $side }}_meter_end_field]"
                                    >
                                        <option value="">
                                            Selecione
                                        </option>

                                        @foreach($numericFields as $field)
                                            <option
                                                value="{{ $field->key }}"
                                                @selected(
                                                    data_get(
                                                        $version->execution_config,
                                                        $side.'_meter_end_field'
                                                    ) === $field->key
                                                )
                                            >
                                                {{ $field->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="cc-field">
                                    <span class="cc-label">
                                        Valor fixo, tarifa ou base
                                    </span>

                                    <input
                                        class="cc-control"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        name="{{ $item[3] }}"
                                        value="{{ $version->{$item[3]} }}"
                                        inputmode="decimal"
                                    >
                                </label>

                                <label class="cc-field">
                                    <span class="cc-label">
                                        Percentual
                                    </span>

                                    <input
                                        class="cc-control"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        max="100"
                                        name="{{ $item[4] }}"
                                        value="{{ $version->{$item[4]} }}"
                                        inputmode="decimal"
                                    >
                                </label>
                            </div>
                        </section>
                    @endforeach

                    <section class="cc-financial-block">
                        <div class="cc-financial-block-head">
                            <div class="cc-financial-block-title">
                                <strong>
                                    Adicionais e descontos
                                </strong>

                                <small>
                                    Termos complementares aplicados a apenas
                                    um dos lados financeiros.
                                </small>
                            </div>

                            <button
                                id="add-financial-rule"
                                type="button"
                                class="cc-action blue"
                            >
                                <i class="ph-fill ph-plus-circle"></i>
                                <span>Adicionar termo</span>
                            </button>
                        </div>

                        <div class="cc-inline-note">
                            <i class="ph-fill ph-info"></i>

                            <span>
                                O total de cada lado é calculado pela fórmula
                                base, acrescida dos adicionais e reduzida pelos
                                descontos configurados para aquele lado.
                            </span>
                        </div>

                        <div
                            id="financial-rules"
                            style="margin-top:.55rem"
                        >
                            @foreach(
                                (array) data_get(
                                    $version->financial_config,
                                    'rules',
                                    []
                                )
                                as $index => $rule
                            )
                                @include(
                                    'services._financial-rule-editor',
                                    compact(
                                        'index',
                                        'rule',
                                        'numericFields',
                                        'evidenceFields'
                                    )
                                )
                            @endforeach
                        </div>
                    </section>

                    <div class="cc-form-actions">
                        <button
                            class="cc-action primary"
                            type="submit"
                        >
                            <i class="ph-fill ph-floppy-disk"></i>
                            <span>Salvar financeiro</span>
                        </button>
                    </div>
                </form>

                <template id="financial-rule-template">
                    @include(
                        'services._financial-rule-editor',
                        [
                            'index' => '__INDEX__',
                            'rule' => [],
                            'numericFields' => $numericFields,
                            'evidenceFields' => $evidenceFields,
                        ]
                    )
                </template>
            @else
                <div class="cc-inline-note">
                    <i class="ph-fill ph-lock-key"></i>

                    <span>
                        Para alterar regras financeiras, crie uma nova versão.
                    </span>
                </div>

                <div style="display:grid;gap:.6rem;margin-top:.7rem">
                    @if(count((array) data_get($version->financial_config, 'rules', [])) > 0)
                    @foreach((array) data_get($version->financial_config, 'rules', []) as $rule)
                        <article class="cc-financial-block" style="border:1px solid var(--cc-border);border-radius:10px;padding:.7rem">
                            <div class="cc-financial-block-title">
                                <strong>{{ $rule['description'] ?? 'Termo financeiro' }}</strong>
                                <small>{{ ($rule['direction'] ?? '') === 'payable' ? 'Remuneração do prestador' : 'Cobrança da organização' }} · {{ ($rule['effect'] ?? 'add') === 'subtract' ? 'Desconto' : 'Acréscimo' }}</small>
                            </div>
                            <div class="cc-mobile-field-meta" style="margin-top:.45rem">
                                <span>Método: {{ match($rule['method'] ?? '') {'quantity_x_rate' => 'quantidade × tarifa', 'percent_addition', 'percent_deduction' => 'percentual do valor base', default => 'valor fixo ou informado'} }}</span>
                                @if(filled($rule['input_label'] ?? null))<span>· Campo: {{ $rule['input_label'] }}</span>@endif
                                @if($rule['evidence_required'] ?? false)<span>· Comprovante obrigatório</span>@endif
                            </div>
                        </article>
                    @endforeach
                    @else
                        <div class="cc-empty">Nenhum adicional ou desconto configurado nesta versão.</div>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- ======================================================
         PRESTADORES
         ====================================================== --}}

    <section
        class="cc-panel"
        data-tab-panel="providers"
        hidden
    >
        <header class="cc-panel-head">
            <div class="cc-panel-title">
                <span
                    class="cc-panel-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-users-three"></i>
                </span>

                <span class="cc-panel-title-copy">
                    <strong>Tarifas específicas</strong>

                    <span>
                        Exceções individuais à remuneração padrão.
                    </span>
                </span>
            </div>

            @if(
                $isDraft
                && $version->provider_pricing_method
            )
                <button
                    class="cc-action primary"
                    type="button"
                    data-open-dialog="rate-dialog"
                >
                    <i class="ph-fill ph-plus-circle"></i>
                    <span>Nova tarifa</span>
                </button>
            @endif
        </header>

        <div class="cc-panel-body">
            <div class="cc-inline-note">
                <i class="ph-fill ph-info"></i>

                <span>
                    Uma tarifa individual substitui apenas o valor padrão
                    daquele prestador. A forma de cálculo continua sendo
                    a definida para o serviço.
                </span>
            </div>

            @if($version->providerRates->isEmpty())
                <div class="cc-empty">
                    Nenhuma tarifa específica configurada.
                </div>
            @else
                <div class="cc-rate-list">
                    @foreach($version->providerRates as $rate)
                        <div class="cc-rate">
                            <div class="cc-rate-copy">
                                <strong>
                                    {{ $rate->provider->name }}
                                </strong>

                                <small>
                                    Tarifa específica deste serviço
                                </small>
                            </div>

                            <strong class="cc-rate-value">
                                @if(
                                    $version->provider_pricing_method
                                        === 'percent_of_base'
                                )
                                    {{ number_format(
                                        (float) $rate->percentage,
                                        4,
                                        ',',
                                        '.'
                                    ) }}%
                                @else
                                    R$ {{ number_format(
                                        (float) (
                                            $rate->rate
                                            ?? $rate->fixed_amount
                                        ),
                                        4,
                                        ',',
                                        '.'
                                    ) }}
                                @endif
                            </strong>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</main>

{{-- ============================================================
     DIALOG: ADICIONAR CAMPO
     ============================================================ --}}

@if($isDraft)
    <dialog
        class="cc-dialog"
        id="field-dialog"
        aria-label="Adicionar campo"
    >
        <div class="cc-dialog-layout">
            <header class="cc-dialog-head">
                <div class="cc-dialog-title">
                    <span class="cc-dialog-title-icon">
                        <i class="ph-fill ph-plus-circle"></i>
                    </span>

                    <span class="cc-dialog-title-copy">
                        <small>Dados da execução</small>
                        <strong>Adicionar campo</strong>
                    </span>
                </div>

                <button
                    class="cc-dialog-close"
                    type="button"
                    data-close-dialog="field-dialog"
                    aria-label="Fechar"
                >
                    <i class="ph-fill ph-x"></i>
                </button>
            </header>

            <div class="cc-dialog-body">
                <form
                    method="post"
                    action="{{ route(
                        'services.catalog.fields.store',
                        [
                            $tenantSlug,
                            $version,
                        ]
                    ) }}"
                    class="cc-form"
                    id="field-form"
                >
                    @csrf
                    <input type="hidden" name="_method" value="PUT" disabled>

                    <div class="cc-grid">
                        <label class="cc-field">
                            <span class="cc-label">
                                Nome mostrado
                            </span>

                            <input
                                class="cc-control"
                                name="label"
                                required
                                maxlength="191"
                                placeholder="Ex.: Quilômetros percorridos"
                            >
                        </label>

                        <label class="cc-field">
                            <span class="cc-label">
                                Identificador técnico
                            </span>

                            <input
                                class="cc-control"
                                name="key"
                                required
                                pattern="[a-z][a-z0-9_]*"
                                placeholder="Ex.: quilometros_percorridos"
                            >

                            <small class="cc-help">
                                Use letras minúsculas, números e underscore.
                            </small>
                        </label>

                        <label class="cc-field">
                            <span class="cc-label">
                                Formato do dado
                            </span>

                            <select
                                class="cc-control"
                                name="type"
                            >
                                @foreach(
                                    $labels::fieldTypes()
                                    as $key => $label
                                )
                                    <option value="{{ $key }}">
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="cc-field">
                            <span class="cc-label">
                                Quando preencher
                            </span>

                            <select
                                class="cc-control"
                                name="phase"
                            >
                                @foreach(
                                    $labels::phases()
                                    as $key => $label
                                )
                                    <option value="{{ $key }}">
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="cc-field">
                            <span class="cc-label">
                                Grupo ou seção
                            </span>

                            <input
                                class="cc-control"
                                name="section"
                                placeholder="Ex.: Medições"
                            >
                        </label>

                        <label class="cc-field">
                            <span class="cc-label">
                                Unidade
                            </span>

                            <input
                                class="cc-control"
                                name="unit"
                                placeholder="hora, litro, km"
                            >
                        </label>

                        <label class="cc-field full" data-field-setting="evidence">
                            <span class="cc-label">
                                Vincular evidência a um dado
                            </span>

                            <select
                                class="cc-control"
                                name="evidence_for_field"
                            >
                                <option value="">
                                    Não vincular
                                </option>

                                @foreach(
                                    $version->fields->whereNotIn(
                                        'type',
                                        [
                                            'image',
                                            'file',
                                            'signature',
                                        ]
                                    )
                                    as $target
                                )
                                    <option value="{{ $target->key }}" data-phase="{{ $target->phase }}">
                                        {{ $target->label }}
                                        —
                                        {{ $labels::phase($target->phase) }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="cc-help">Crie primeiro o dado, depois o campo de arquivo na mesma etapa. Quando o comprovante for obrigatório, marque também “Preenchimento obrigatório”.</small>
                        </label>

                        <fieldset class="cc-field full" data-field-setting="mimes" style="border:0;padding:0;margin:0">
                            <legend class="cc-label">Formatos aceitos neste comprovante</legend>
                            @foreach(['image/jpeg' => 'JPG / JPEG', 'image/png' => 'PNG', 'image/webp' => 'WebP', 'application/pdf' => 'PDF'] as $mime => $mimeLabel)
                                <label style="display:inline-flex;align-items:center;gap:.35rem;margin-right:1rem"><input type="checkbox" name="accepted_mime_types[]" value="{{ $mime }}"> {{ $mimeLabel }}</label>
                            @endforeach
                            <small class="cc-help">Se nada for marcado, fotos aceitam imagens e arquivos aceitam imagens ou PDF.</small>
                        </fieldset>

                        <label class="cc-field">
                            <span class="cc-label">
                                Valor mínimo
                            </span>

                            <input
                                class="cc-control"
                                type="number"
                                name="minimum"
                                step="0.0001"
                            >
                        </label>

                        <label class="cc-field">
                            <span class="cc-label">
                                Valor máximo
                            </span>

                            <input
                                class="cc-control"
                                type="number"
                                name="maximum"
                                step="0.0001"
                            >
                        </label>

                        <label class="cc-field"><span class="cc-label">Ordem de exibição</span><input class="cc-control" type="number" name="sort_order" min="0" max="10000" placeholder="Automática"></label>
                        <label class="cc-field"><span class="cc-label">Exemplo no campo</span><input class="cc-control" name="placeholder" maxlength="191" placeholder="Texto de orientação"></label>
                        <label class="cc-field full"><span class="cc-label">Ajuda para preenchimento</span><textarea class="cc-control" name="help" maxlength="500" placeholder="Explique o que deve ser informado"></textarea></label>

                        <label class="cc-field full">
                            <span class="cc-label">
                                Opções da lista
                            </span>

                            <textarea
                                class="cc-control"
                                name="options_text"
                                placeholder="Uma opção por linha. Ex.: bom|Bom"
                            ></textarea>
                            <small class="cc-help">Para Lista, escreva uma opção por linha. Use valor|Nome exibido para separar o valor salvo do texto mostrado; sem |, o mesmo texto é usado nos dois.</small>
                        </label>
                    </div>

                    <div class="cc-checks">
                        <label class="cc-check">
                            <input
                                type="checkbox"
                                name="required"
                                value="1"
                            >
                            <span>Preenchimento obrigatório</span>
                        </label>

                        <label class="cc-check">
                            <input
                                type="checkbox"
                                name="visible_to_provider"
                                value="1"
                                checked
                            >
                            <span>Visível ao prestador</span>
                        </label>

                        <label class="cc-check">
                            <input
                                type="checkbox"
                                name="editable_by_provider"
                                value="1"
                                checked
                            >
                            <span>Prestador pode preencher</span>
                        </label>

                        <label class="cc-check">
                            <input
                                type="checkbox"
                                name="visible_to_management"
                                value="1"
                                checked
                            >
                            <span>Visível à gestão</span>
                        </label>

                        <label class="cc-check">
                            <input
                                type="checkbox"
                                name="include_in_documents"
                                value="1"
                            >
                            <span>Incluir nos documentos</span>
                        </label>

                        <label class="cc-check">
                            <input
                                type="checkbox"
                                name="reportable"
                                value="1"
                            >
                            <span>Incluir na prestação de contas</span>
                        </label>
                    </div>
                </form>
            </div>

            <footer class="cc-dialog-foot">
                <button
                    class="cc-action"
                    type="button"
                    data-close-dialog="field-dialog"
                >
                    Cancelar
                </button>

                <button
                    class="cc-action primary"
                    type="submit"
                    form="field-form"
                >
                    <i class="ph-fill ph-plus-circle"></i>
                    <span>Adicionar campo</span>
                </button>
            </footer>
        </div>
    </dialog>
@endif

{{-- ============================================================
     DIALOG: TARIFA DO PRESTADOR
     ============================================================ --}}

@if(
    $isDraft
    && $version->provider_pricing_method
)
    <dialog
        class="cc-dialog"
        id="rate-dialog"
        aria-label="Nova tarifa específica"
    >
        <div class="cc-dialog-layout">
            <header class="cc-dialog-head">
                <div class="cc-dialog-title">
                    <span class="cc-dialog-title-icon">
                        <i class="ph-fill ph-user-gear"></i>
                    </span>

                    <span class="cc-dialog-title-copy">
                        <small>Prestador</small>
                        <strong>Nova tarifa específica</strong>
                    </span>
                </div>

                <button
                    class="cc-dialog-close"
                    type="button"
                    data-close-dialog="rate-dialog"
                    aria-label="Fechar"
                >
                    <i class="ph-fill ph-x"></i>
                </button>
            </header>

            <div class="cc-dialog-body">
                <form
                    method="post"
                    action="{{ route(
                        'services.catalog.rates.store',
                        [
                            $tenantSlug,
                            $version,
                        ]
                    ) }}"
                    class="cc-form"
                    id="rate-form"
                >
                    @csrf

                    <div class="cc-grid">
                        <label class="cc-field full">
                            <span class="cc-label">
                                Prestador
                            </span>

                            <select
                                class="cc-control"
                                name="service_provider_id"
                                required
                            >
                                @foreach($providers as $provider)
                                    <option value="{{ $provider->id }}">
                                        {{ $provider->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        @if(
                            $version->provider_pricing_method
                                === 'quantity_x_rate'
                        )
                            <label class="cc-field full">
                                <span class="cc-label">
                                    Tarifa por unidade
                                </span>

                                <input
                                    class="cc-control"
                                    type="number"
                                    step="0.0001"
                                    min="0.0001"
                                    name="rate"
                                    required
                                    inputmode="decimal"
                                >
                            </label>
                        @elseif(
                            $version->provider_pricing_method
                                === 'fixed'
                        )
                            <label class="cc-field full">
                                <span class="cc-label">
                                    Valor fixo
                                </span>

                                <input
                                    class="cc-control"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    name="fixed_amount"
                                    required
                                    inputmode="decimal"
                                >
                            </label>
                        @else
                            <label class="cc-field full">
                                <span class="cc-label">
                                    Percentual
                                </span>

                                <input
                                    class="cc-control"
                                    type="number"
                                    step="0.0001"
                                    min="0.0001"
                                    max="100"
                                    name="percentage"
                                    required
                                    inputmode="decimal"
                                >
                            </label>
                        @endif
                    </div>
                </form>
            </div>

            <footer class="cc-dialog-foot">
                <button
                    class="cc-action"
                    type="button"
                    data-close-dialog="rate-dialog"
                >
                    Cancelar
                </button>

                <button
                    class="cc-action primary"
                    type="submit"
                    form="rate-form"
                >
                    <i class="ph-fill ph-floppy-disk"></i>
                    <span>Salvar tarifa</span>
                </button>
            </footer>
        </div>
    </dialog>
@endif

@if($isDraft)
    <script>
    document.addEventListener(
        'DOMContentLoaded',
        () => {
            const list =
                document.getElementById(
                    'financial-rules'
                );

            const template =
                document.getElementById(
                    'financial-rule-template'
                );

            const add =
                document.getElementById(
                    'add-financial-rule'
                );

            if (
                list
                && template
                && add
            ) {
                let next =
                    list.querySelectorAll(
                        '[data-rule]'
                    ).length;

                const bind = scope => {
                    const syncRule = rule => {
                        const method = rule.querySelector('[data-rule-method]')?.value || 'fixed_addition';
                        const evidenceRequired = rule.querySelector('[data-rule-evidence-toggle]')?.checked;
                        rule.querySelectorAll('[data-rule-setting]').forEach(element => {
                            const setting = element.dataset.ruleSetting;
                            element.hidden = (setting === 'percentage' && !method.startsWith('percent'))
                                || (setting === 'quantity-field' && method !== 'quantity_x_rate')
                                || (setting === 'value' && method.startsWith('percent'))
                                || (setting === 'value-field' && method === 'quantity_x_rate');
                        });
                        const evidence = rule.querySelector('[data-rule-evidence]');
                        if (evidence) evidence.hidden = !evidenceRequired;
                        const description = rule.querySelector('[data-rule-description]')?.value?.trim();
                        const title = rule.querySelector('[data-rule-title]');
                        if (title) title.textContent = description || 'Novo termo financeiro';
                    };

                    scope
                        .querySelectorAll(
                            '[data-remove-rule]'
                        )
                        .forEach(button => {
                            button.onclick =
                                () => {
                                    button
                                        .closest(
                                            '[data-rule]'
                                        )
                                        ?.remove();
                                };
                        });
                    scope.querySelectorAll('[data-rule]').forEach(rule => {
                        rule.querySelectorAll('select,input').forEach(control => {
                            control.addEventListener('change', () => syncRule(rule));
                            control.addEventListener('input', () => syncRule(rule));
                        });
                        syncRule(rule);
                    });
                };

                add.addEventListener(
                    'click',
                    () => {
                        const wrapper =
                            document.createElement(
                                'div'
                            );

                        wrapper.innerHTML =
                            template
                                .innerHTML
                                .replaceAll(
                                    '__INDEX__',
                                    String(next++)
                                );

                        const item =
                            wrapper
                                .firstElementChild;

                        if (!item) {
                            return;
                        }

                        list.appendChild(item);
                        bind(item);
                    }
                );

                bind(list);
            }
        }
    );
    </script>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('field-form');
    const dialog = document.getElementById('field-dialog');
    if (!form || !dialog) return;
    const storeUrl = @json($fieldStoreUrl);
    const updateUrl = @json($fieldUpdateUrlTemplate);
    const title = dialog.querySelector('.cc-dialog-title-copy strong');
    const saveLabel = dialog.querySelector('.cc-dialog-foot button[type="submit"] span');
    const method = form.querySelector('input[name="_method"]');
    const type = form.elements.namedItem('type');
    const phase = form.elements.namedItem('phase');
    const linked = form.elements.namedItem('evidence_for_field');
    const updateSettings = () => {
        const evidence = ['image', 'file', 'signature'].includes(type.value);
        form.querySelector('[data-field-setting="evidence"]').hidden = !evidence;
        form.querySelector('[data-field-setting="mimes"]').hidden = !evidence;
        form.elements.namedItem('options_text').closest('.cc-field').hidden = type.value !== 'select';
        [...linked.options].forEach(option => {
            if (!option.value) return;
            option.disabled = option.dataset.phase !== phase.value;
        });
        if (linked.selectedOptions[0]?.disabled) linked.value = '';
        const pdf = form.querySelector('input[value="application/pdf"]');
        pdf.closest('label').hidden = type.value !== 'file';
        if (type.value !== 'file') pdf.checked = false;
    };
    type.addEventListener('change', updateSettings);
    phase.addEventListener('change', updateSettings);
    document.querySelectorAll('[data-edit-field]').forEach(button => button.addEventListener('click', () => {
        const field = JSON.parse(button.dataset.editField);
        form.reset();
        form.action = updateUrl.replace('__FIELD__', String(field.id));
        method.disabled = false;
        title.textContent = 'Editar campo';
        saveLabel.textContent = 'Salvar alterações';
        for (const [key, value] of Object.entries(field)) {
            const input = form.elements.namedItem(key);
            if (!input || key === 'id' || key === 'options' || key === 'accepted_mime_types') continue;
            if (input.type === 'checkbox') input.checked = Boolean(value);
            else input.value = value ?? '';
        }
        form.elements.namedItem('key').readOnly = true;
        form.elements.namedItem('options_text').value = Array.isArray(field.options) ? field.options.join('\n') : Object.entries(field.options ?? {}).map(([key, label]) => `${key}|${label}`).join('\n');
        form.querySelectorAll('input[name="accepted_mime_types[]"]').forEach(input => input.checked = (field.accepted_mime_types ?? []).includes(input.value));
        updateSettings();
        dialog.showModal();
    }));
    document.querySelectorAll('[data-open-dialog="field-dialog"]').forEach(button => button.addEventListener('click', () => {
        form.reset();
        form.action = storeUrl;
        method.disabled = true;
        form.elements.namedItem('key').readOnly = false;
        title.textContent = 'Adicionar campo';
        saveLabel.textContent = 'Adicionar campo';
        updateSettings();
    }, {capture:true}));
    updateSettings();
});
</script>

<script>
document.addEventListener(
    'DOMContentLoaded',
    () => {
        const tabs = [
            ...document.querySelectorAll(
                '[data-tab]'
            ),
        ];

        const panels = [
            ...document.querySelectorAll(
                '[data-tab-panel]'
            ),
        ];

        const tabBar =
            document.querySelector(
                '.cc-tabs'
            );

        const validTabs =
            new Set(
                panels.map(
                    panel =>
                        panel.dataset.tabPanel
                )
            );

        const showTab = (
            name,
            {
                updateHash = true,
            } = {}
        ) => {
            const targetName =
                validTabs.has(name)
                    ? name
                    : 'fields';

            tabs.forEach(tab => {
                const active =
                    tab.dataset.tab
                    === targetName;

                tab.classList.toggle(
                    'active',
                    active
                );

                tab.setAttribute(
                    'aria-selected',
                    active
                        ? 'true'
                        : 'false'
                );
            });

            panels.forEach(panel => {
                panel.hidden =
                    panel.dataset.tabPanel
                    !== targetName;
            });

            const activeTab =
                tabs.find(
                    tab =>
                        tab.dataset.tab
                        === targetName
                );

            if (
                tabBar
                && activeTab
                && tabBar.scrollWidth
                    > tabBar.clientWidth
            ) {
                activeTab.scrollIntoView({
                    behavior: 'smooth',
                    inline: 'center',
                    block: 'nearest',
                });
            }

            if (
                updateHash
                && window.location.hash
                    !== `#${targetName}`
            ) {
                history.replaceState(
                    history.state,
                    '',
                    `#${targetName}`
                );
            }
        };

        tabs.forEach(tab => {
            tab.addEventListener(
                'click',
                () => {
                    showTab(
                        tab.dataset.tab
                    );
                }
            );
        });

        window.addEventListener(
            'hashchange',
            () => {
                showTab(
                    window.location.hash
                        .replace('#', ''),
                    {
                        updateHash: false,
                    }
                );
            }
        );

        showTab(
            window.location.hash
                .replace('#', '')
                || 'fields',
            {
                updateHash: false,
            }
        );

        const dialogs = [
            ...document.querySelectorAll(
                '.cc-dialog'
            ),
        ];

        const directClose = dialog => {
            if (!dialog) {
                return;
            }

            if (
                typeof dialog.close
                    === 'function'
                && dialog.open
            ) {
                dialog.close();
            } else {
                dialog.removeAttribute(
                    'open'
                );
            }
        };

        const openDialog = id => {
            const dialog =
                document.getElementById(id);

            if (!dialog) {
                return;
            }

            dialogs.forEach(other => {
                if (other !== dialog) {
                    directClose(other);
                }
            });

            if (
                typeof dialog.showModal
                    === 'function'
            ) {
                dialog.showModal();
            } else {
                dialog.setAttribute(
                    'open',
                    ''
                );
            }

            if (
                history.state
                    ?.catalogDialog
                !== id
            ) {
                history.pushState(
                    {
                        ...(history.state || {}),
                        catalogDialog: id,
                    },
                    '',
                    window.location.href
                );
            }
        };

        const requestClose = id => {
            const dialog =
                document.getElementById(id);

            if (!dialog) {
                return;
            }

            if (
                history.state
                    ?.catalogDialog
                === id
            ) {
                history.back();
                return;
            }

            directClose(dialog);
        };

        document
            .querySelectorAll(
                '[data-open-dialog]'
            )
            .forEach(button => {
                button.addEventListener(
                    'click',
                    () => {
                        openDialog(
                            button.dataset
                                .openDialog
                        );
                    }
                );
            });

        document
            .querySelectorAll(
                '[data-close-dialog]'
            )
            .forEach(button => {
                button.addEventListener(
                    'click',
                    () => {
                        requestClose(
                            button.dataset
                                .closeDialog
                        );
                    }
                );
            });

        dialogs.forEach(dialog => {
            dialog.addEventListener(
                'cancel',
                event => {
                    event.preventDefault();
                    requestClose(dialog.id);
                }
            );
        });

        window.addEventListener(
            'popstate',
            () => {
                dialogs.forEach(dialog => {
                    if (
                        dialog.hasAttribute(
                            'open'
                        )
                        && history.state
                            ?.catalogDialog
                            !== dialog.id
                    ) {
                        directClose(dialog);
                    }
                });
            }
        );
    }
);
</script>
@endsection
