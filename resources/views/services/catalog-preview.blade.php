@extends('layouts.bento')

@section('title', 'Prévia')
@section('page-title', 'Prévia do prestador')
@section('user-role', 'Catálogo de serviços')

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

    $providerFields = $version
        ->fields
        ->filter(
            fn ($field) =>
                (bool) $field->visible_to_provider
                && (bool) $field->editable_by_provider
        );

    $phaseMeta = [
        'order' => [
            'label' => 'Dados do serviço',
            'short' => 'Finalizar',
            'icon' => 'ph-clipboard-text',
            'description' => 'Informações solicitadas ao criar a ordem.',
        ],

        'start' => [
            'label' => 'Iniciar serviço',
            'short' => 'Iniciar',
            'icon' => 'ph-play-circle',
            'description' => 'Dados solicitados no início da execução.',
        ],

        'execution' => [
            'label' => 'Durante a execução',
            'short' => 'Executar',
            'icon' => 'ph-wrench',
            'description' => 'Informações preenchidas enquanto o serviço acontece.',
        ],

        'finish' => [
            'label' => 'Finalizar serviço',
            'short' => 'Finalizar',
            'icon' => 'ph-flag-checkered',
            'description' => 'Dados e evidências solicitados antes do envio.',
        ],

        'review' => [
            'label' => 'Conferência',
            'short' => 'Conferência',
            'icon' => 'ph-magnifying-glass',
            'description' => 'Campos disponíveis durante a etapa de conferência.',
        ],
    ];

    $phaseFields = collect();

    foreach (array_keys($phaseMeta) as $phase) {
        $phaseFields->put(
            $phase,
            $providerFields
                ->where('phase', $phase)
                ->values()
        );
    }

    $evidenceTypes = [
        'image',
        'file',
        'signature',
    ];

    $createOrderAllowed =
        (bool) $version->allow_provider_create_order;

    $membersOnly =
        (bool) $version->members_only;

    $reviewLabel =
        $version->review_mode === 'automatic'
            ? 'Aprovação automática'
            : 'Conferência pela gestão';

    $statusLabel = match ($version->status) {
        'draft' => 'Rascunho',
        'published' => 'Publicada',
        'retired' => 'Substituída',
        default => \Illuminate\Support\Str::headline(
            (string) $version->status
        ),
    };
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .provider-preview {
        --pv-green: var(--ws-green, #219653);
        --pv-green-soft: #edf8f2;
        --pv-green-border: #cce8d7;

        --pv-blue: var(--ws-blue, #3478d4);
        --pv-blue-soft: #edf4ff;
        --pv-blue-border: #cfe0f7;

        --pv-violet: var(--ws-purple, #8a4bd2);
        --pv-violet-soft: #f5efff;
        --pv-violet-border: #e1d2f4;

        --pv-amber: var(--ws-amber, #c38418);
        --pv-amber-soft: #fff7e8;
        --pv-amber-border: #f0dcae;

        --pv-red: var(--ws-red, #cf5050);
        --pv-red-soft: #fff0f0;
        --pv-red-border: #efcaca;

        --pv-cyan: #168eae;
        --pv-cyan-soft: #ecf8fb;
        --pv-cyan-border: #cae8ef;

        --pv-text: #17211d;
        --pv-text-2: #59655f;
        --pv-muted: #89938e;
        --pv-border: #dde5e0;
        --pv-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 980px);
        min-width: 0;
        gap: .7rem;
        margin-inline: auto;
        color: var(--pv-text);
    }

    .provider-preview *,
    .provider-preview *::before,
    .provider-preview *::after {
        box-sizing: border-box;
    }

    .provider-preview button,
    .provider-preview input,
    .provider-preview select,
    .provider-preview textarea {
        font: inherit;
    }

    .provider-preview a {
        text-decoration: none;
    }

    /* =========================================================
       CABEÇALHO DA PRÉVIA
       ========================================================= */

    .pv-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .75rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--pv-border);
        border-radius: 12px;
        background: #fff;
    }

    .pv-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .pv-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--pv-violet-soft);
        color: var(--pv-violet);
        font-size: 1rem;
    }

    .pv-head-copy {
        min-width: 0;
    }

    .pv-head-copy small {
        display: block;
        color: var(--pv-muted);
        font-size: .61rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .pv-head-copy h1 {
        margin: .06rem 0 0;
        overflow: hidden;
        color: var(--pv-text);
        font-size: clamp(1.02rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pv-head-copy p {
        margin: .12rem 0 0;
        color: var(--pv-muted);
        font-size: .65rem;
        line-height: 1.4;
    }

    .pv-back {
        display: inline-flex;
        min-height: 38px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .38rem .56rem;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        background: #fff;
        color: var(--pv-text-2);
        font-size: .66rem;
        font-weight: 760;
        white-space: nowrap;
    }

    /* =========================================================
       MODO DA PRÉVIA
       ========================================================= */

    .pv-mode {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--pv-border);
        border-radius: 11px;
        background: #fff;
    }

    .pv-mode-button {
        position: relative;
        display: flex;
        min-width: 0;
        min-height: 48px;
        gap: .42rem;
        align-items: center;
        justify-content: center;
        padding: .45rem .6rem;
        border: 0;
        border-right: 1px solid var(--pv-border);
        background: #fff;
        color: var(--pv-muted);
        cursor: pointer;
    }

    .pv-mode-button:last-child {
        border-right: 0;
    }

    .pv-mode-button::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 2px;
        background: transparent;
        content: "";
    }

    .pv-mode-button i {
        font-size: .8rem;
    }

    .pv-mode-copy {
        min-width: 0;
        text-align: left;
    }

    .pv-mode-copy strong,
    .pv-mode-copy small {
        display: block;
    }

    .pv-mode-copy strong {
        color: inherit;
        font-size: .63rem;
        font-weight: 800;
    }

    .pv-mode-copy small {
        margin-top: .02rem;
        color: var(--pv-muted);
        font-size: .49rem;
    }

    .pv-mode-button.active {
        background: var(--pv-violet-soft);
        color: var(--pv-violet);
    }

    .pv-mode-button.active::after {
        background: var(--pv-violet);
    }

    .pv-mode-button:focus-visible {
        outline: 2px solid var(--pv-violet);
        outline-offset: -2px;
    }

    /* =========================================================
       MOLDURA DO PRESTADOR
       ========================================================= */

    .pv-app {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--pv-border);
        border-radius: 14px;
        background: #fff;
        box-shadow:
            0 12px 34px rgba(31, 70, 44, .05);
    }

    .pv-app-top {
        display: flex;
        min-width: 0;
        gap: .6rem;
        align-items: center;
        justify-content: space-between;
        padding: .68rem .72rem;
        border-bottom: 1px solid var(--pv-border);
        background: #fff;
    }

    .pv-app-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
    }

    .pv-app-title-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--pv-violet-soft);
        color: var(--pv-violet);
        font-size: .78rem;
    }

    .pv-app-title-copy {
        min-width: 0;
    }

    .pv-app-title-copy small,
    .pv-app-title-copy strong {
        display: block;
        min-width: 0;
    }

    .pv-app-title-copy small {
        color: var(--pv-muted);
        font-size: .52rem;
        font-weight: 690;
    }

    .pv-app-title-copy strong {
        margin-top: .02rem;
        overflow: hidden;
        color: var(--pv-text);
        font-size: .72rem;
        font-weight: 810;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pv-preview-badge {
        display: inline-flex;
        min-height: 28px;
        gap: .22rem;
        align-items: center;
        padding: .22rem .36rem;
        border-radius: 7px;
        background: var(--pv-soft);
        color: var(--pv-muted);
        font-size: .52rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .pv-screen {
        min-width: 0;
    }

    .pv-screen[hidden] {
        display: none !important;
    }

    /* =========================================================
       CRIAÇÃO DA ORDEM
       ========================================================= */

    .pv-create-steps {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        overflow: hidden;
        border-bottom: 1px solid var(--pv-border);
        background: #fff;
    }

    .pv-create-step {
        position: relative;
        display: grid;
        min-width: 0;
        gap: .12rem;
        padding: .52rem .48rem;
        border: 0;
        border-right: 1px solid var(--pv-border);
        background: #fff;
        color: var(--pv-muted);
        cursor: pointer;
        text-align: left;
    }

    .pv-create-step:last-child {
        border-right: 0;
    }

    .pv-create-step::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 2px;
        background: transparent;
        content: "";
    }

    .pv-create-step strong {
        overflow: hidden;
        color: inherit;
        font-size: .57rem;
        font-weight: 790;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pv-create-step small {
        color: var(--pv-muted);
        font-size: .46rem;
        white-space: nowrap;
    }

    .pv-create-step.active {
        background: var(--pv-violet-soft);
        color: var(--pv-violet);
    }

    .pv-create-step.active::after {
        background: var(--pv-violet);
    }

    .pv-create-body,
    .pv-execution-body {
        display: grid;
        gap: .65rem;
        padding: .72rem;
    }

    .pv-create-pane[hidden],
    .pv-phase-pane[hidden] {
        display: none !important;
    }

    .pv-section-title {
        display: grid;
        min-width: 0;
        grid-template-columns: 31px minmax(0, 1fr);
        gap: .4rem;
        align-items: center;
    }

    .pv-section-title-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 8px;
        background: var(--pv-blue-soft);
        color: var(--pv-blue);
        font-size: .72rem;
    }

    .pv-section-title-copy {
        min-width: 0;
    }

    .pv-section-title-copy strong,
    .pv-section-title-copy span {
        display: block;
    }

    .pv-section-title-copy strong {
        color: var(--pv-text);
        font-size: .7rem;
        font-weight: 810;
    }

    .pv-section-title-copy span {
        margin-top: .02rem;
        color: var(--pv-muted);
        font-size: .53rem;
        line-height: 1.4;
    }

    .pv-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .58rem;
    }

    .pv-field {
        display: grid;
        min-width: 0;
        gap: .24rem;
    }

    .pv-field.full {
        grid-column: 1 / -1;
    }

    .pv-label {
        display: flex;
        gap: .25rem;
        align-items: center;
        color: var(--pv-text-2);
        font-size: .58rem;
        font-weight: 730;
    }

    .pv-required {
        color: var(--pv-red);
    }

    .pv-unit {
        color: var(--pv-muted);
        font-size: .5rem;
        font-weight: 650;
    }

    .pv-control {
        width: 100%;
        min-width: 0;
        min-height: 40px;
        padding: .44rem .5rem;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        outline: 0;
        background: #fff;
        color: var(--pv-text);
        font-size: .66rem;
    }

    textarea.pv-control {
        min-height: 82px;
        resize: none;
    }

    .pv-control:disabled {
        opacity: 1;
        background: #fff;
        color: var(--pv-text-2);
        -webkit-text-fill-color: var(--pv-text-2);
    }

    .pv-help {
        color: var(--pv-muted);
        font-size: .5rem;
        line-height: 1.4;
    }

    .pv-choice {
        display: grid;
        min-height: 40px;
        grid-template-columns: 30px minmax(0, 1fr) auto;
        gap: .4rem;
        align-items: center;
        padding: .4rem .46rem;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        background: #fff;
    }

    .pv-choice-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 7px;
        background: var(--pv-soft);
        color: var(--pv-text-2);
        font-size: .7rem;
    }

    .pv-choice-copy {
        min-width: 0;
    }

    .pv-choice-copy small,
    .pv-choice-copy strong {
        display: block;
    }

    .pv-choice-copy small {
        color: var(--pv-muted);
        font-size: .48rem;
    }

    .pv-choice-copy strong {
        margin-top: .02rem;
        overflow: hidden;
        color: var(--pv-text);
        font-size: .62rem;
        font-weight: 760;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pv-choice i:last-child {
        color: var(--pv-muted);
        font-size: .62rem;
    }

    .pv-create-actions {
        display: flex;
        gap: .45rem;
        align-items: center;
        justify-content: space-between;
        padding: .62rem .72rem;
        border-top: 1px solid var(--pv-border);
        background: #fbfdfc;
    }

    .pv-button {
        display: inline-flex;
        min-height: 38px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .36rem .56rem;
        border: 1px solid var(--pv-border);
        border-radius: 8px;
        background: #fff;
        color: var(--pv-text-2);
        cursor: pointer;
        font-size: .64rem;
        font-weight: 780;
    }

    .pv-button.primary {
        border-color: var(--pv-green);
        background: var(--pv-green);
        color: #fff;
    }

    .pv-button:disabled {
        cursor: default;
        opacity: .62;
    }

    /* =========================================================
       EXECUÇÃO
       ========================================================= */

    .pv-order-facts {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        border-bottom: 1px solid var(--pv-border);
        background: var(--pv-soft);
    }

    .pv-order-fact {
        display: grid;
        min-width: 0;
        gap: .03rem;
        padding: .5rem .56rem;
    }

    .pv-order-fact + .pv-order-fact {
        border-left: 1px solid var(--pv-border);
    }

    .pv-order-fact small {
        color: var(--pv-muted);
        font-size: .47rem;
    }

    .pv-order-fact strong {
        overflow: hidden;
        color: var(--pv-text);
        font-size: .57rem;
        font-weight: 740;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pv-workflow {
        display: flex;
        min-width: 0;
        overflow-x: auto;
        border-bottom: 1px solid var(--pv-border);
        background: #fff;
        scrollbar-width: thin;
        scrollbar-color: #cfd8d2 transparent;
    }

    .pv-workflow::-webkit-scrollbar {
        height: 5px;
    }

    .pv-workflow::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: #cfd8d2;
    }

    .pv-workflow-step {
        position: relative;
        display: grid;
        min-width: 150px;
        flex: 1 0 auto;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: .38rem;
        align-items: center;
        min-height: 52px;
        padding: .46rem .52rem;
        border: 0;
        border-right: 1px solid var(--pv-border);
        background: #fff;
        color: var(--pv-muted);
        cursor: pointer;
        text-align: left;
    }

    .pv-workflow-step:last-child {
        border-right: 0;
    }

    .pv-workflow-step::after {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 2px;
        background: transparent;
        content: "";
    }

    .pv-workflow-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 8px;
        background: var(--pv-soft);
        color: var(--pv-muted);
        font-size: .7rem;
    }

    .pv-workflow-copy {
        min-width: 0;
    }

    .pv-workflow-copy strong,
    .pv-workflow-copy small {
        display: block;
    }

    .pv-workflow-copy strong {
        color: inherit;
        font-size: .58rem;
        font-weight: 790;
    }

    .pv-workflow-copy small {
        margin-top: .02rem;
        overflow: hidden;
        color: var(--pv-muted);
        font-size: .46rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pv-workflow-step.active {
        color: var(--pv-blue);
        background: var(--pv-blue-soft);
    }

    .pv-workflow-step.active::after {
        background: var(--pv-blue);
    }

    .pv-workflow-step.active .pv-workflow-icon {
        background: #fff;
        color: var(--pv-blue);
    }

    .pv-evidence {
        display: grid;
        min-height: 112px;
        gap: .3rem;
        place-items: center;
        padding: .7rem;
        border: 1px dashed var(--pv-violet-border);
        border-radius: 9px;
        background: var(--pv-violet-soft);
        color: var(--pv-violet);
        text-align: center;
    }

    .pv-evidence i {
        font-size: 1.35rem;
    }

    .pv-evidence strong {
        font-size: .61rem;
        font-weight: 790;
    }

    .pv-evidence small {
        max-width: 280px;
        color: var(--pv-text-2);
        font-size: .5rem;
        line-height: 1.4;
    }

    .pv-empty {
        display: grid;
        min-height: 150px;
        place-items: center;
        padding: 1rem;
        color: var(--pv-muted);
        font-size: .61rem;
        text-align: center;
    }

    .pv-readonly-note {
        display: flex;
        gap: .34rem;
        align-items: flex-start;
        padding: .48rem .54rem;
        border-radius: 8px;
        background: var(--pv-soft);
        color: var(--pv-text-2);
        font-size: .53rem;
        line-height: 1.45;
    }

    .pv-readonly-note i {
        margin-top: .04rem;
        flex: 0 0 auto;
        color: var(--pv-muted);
        font-size: .64rem;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 720px) {
        .pv-grid {
            grid-template-columns: 1fr;
        }

        .pv-field.full {
            grid-column: auto;
        }

        .pv-order-facts {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pv-order-fact:nth-child(3) {
            border-top: 1px solid var(--pv-border);
            border-left: 0;
        }

        .pv-order-fact:nth-child(4) {
            border-top: 1px solid var(--pv-border);
        }

        .pv-create-steps {
            display: flex;
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: #cfd8d2 transparent;
        }

        .pv-create-step {
            min-width: 145px;
            flex: 0 0 auto;
        }
    }

    @media (max-width: 560px) {
        .provider-preview {
            gap: .58rem;
        }

        .pv-head {
            padding: .62rem .66rem;
        }

        .pv-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .pv-head-icon {
            width: 36px;
            height: 36px;
        }

        .pv-head-copy p {
            display: none;
        }

        .pv-back {
            width: 38px;
            min-width: 38px;
            padding: 0;
        }

        .pv-back span {
            display: none;
        }

        .pv-mode-copy small {
            display: none;
        }

        .pv-app-top {
            padding: .58rem .62rem;
        }

        .pv-create-body,
        .pv-execution-body {
            padding: .62rem;
        }

        .pv-control {
            min-height: 44px;
            font-size: 16px;
        }

        .pv-create-actions {
            position: sticky;
            z-index: 10;
            bottom: 0;
            padding-bottom:
                max(.62rem, env(safe-area-inset-bottom));
        }
    }
</style>

<main class="provider-preview">
    <header class="pv-head">
        <div class="pv-head-main">
            <span
                class="pv-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-eye"></i>
            </span>

            <div class="pv-head-copy">
                <small>Prévia do prestador</small>

                <h1>{{ $version->service->name }}</h1>

                <p>
                    Versão {{ $version->version }}
                    · {{ $statusLabel }}
                    · {{ $reviewLabel }}
                </p>
            </div>
        </div>

        <a
            class="pv-back"
            href="{{ route(
                'services.catalog.show',
                [
                    $tenantSlug,
                    $version,
                ]
            ) }}"
        >
            <i class="ph-fill ph-arrow-left"></i>
            <span>Voltar à configuração</span>
        </a>
    </header>

    <nav
        class="pv-mode"
        aria-label="Contexto da prévia"
    >
        <button
            class="pv-mode-button active"
            type="button"
            data-preview-mode="create"
        >
            <i class="ph-fill ph-plus-circle"></i>

            <span class="pv-mode-copy">
                <strong>Criação da ordem</strong>
                <small>Como o prestador registra uma nova ordem</small>
            </span>
        </button>

        <button
            class="pv-mode-button"
            type="button"
            data-preview-mode="execution"
        >
            <i class="ph-fill ph-play-circle"></i>

            <span class="pv-mode-copy">
                <strong>Execução do serviço</strong>
                <small>Como os campos aparecem durante o trabalho</small>
            </span>
        </button>
    </nav>

    <section class="pv-app">
        <header class="pv-app-top">
            <div class="pv-app-title">
                <span
                    class="pv-app-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-briefcase"></i>
                </span>

                <span class="pv-app-title-copy">
                    <small>Área do prestador</small>

                    <strong>
                        {{ $version->service->name }}
                    </strong>
                </span>
            </div>

            <span class="pv-preview-badge">
                <i class="ph-fill ph-eye"></i>
                Somente prévia
            </span>
        </header>

        {{-- ==================================================
             CRIAÇÃO
             ================================================== --}}

        <div
            class="pv-screen"
            data-preview-screen="create"
        >
            @if(!$createOrderAllowed)
                <div class="pv-readonly-note">
                    <i class="ph-fill ph-info"></i>

                    <span>
                        A criação de ordens pelo prestador está desativada
                        para este serviço. A sequência abaixo mostra apenas
                        como a configuração ficaria caso essa permissão
                        fosse habilitada.
                    </span>
                </div>
            @endif

            <nav
                class="pv-create-steps"
                aria-label="Etapas da criação da ordem"
            >
                @foreach([
                    ['Serviço', 'Escolha'],
                    ['Pessoa', 'Beneficiário'],
                    ['Agendamento', 'Data e local'],
                    ['Finalizar', 'Dados do serviço'],
                ] as $index => $step)
                    <button
                        class="
                            pv-create-step
                            {{ $index === 0 ? 'active' : '' }}
                        "
                        type="button"
                        data-create-step="{{ $index }}"
                    >
                        <strong>
                            {{ $index + 1 }}. {{ $step[0] }}
                        </strong>

                        <small>{{ $step[1] }}</small>
                    </button>
                @endforeach
            </nav>

            <div class="pv-create-body">
                <div
                    class="pv-create-pane"
                    data-create-pane="0"
                >
                    <div class="pv-section-title">
                        <span class="pv-section-title-icon">
                            <i class="ph-fill ph-wrench"></i>
                        </span>

                        <span class="pv-section-title-copy">
                            <strong>Serviço</strong>

                            <span>
                                O serviço já aparece selecionado nesta prévia.
                            </span>
                        </span>
                    </div>

                    <div class="pv-choice">
                        <span class="pv-choice-icon">
                            <i class="ph-fill ph-wrench"></i>
                        </span>

                        <span class="pv-choice-copy">
                            <small>Serviço selecionado</small>

                            <strong>
                                {{ $version->service->name }}
                                · v{{ $version->version }}
                            </strong>
                        </span>

                        <i class="ph-fill ph-check-circle"></i>
                    </div>

                    <div class="pv-readonly-note">
                        <i class="ph-fill ph-info"></i>

                        <span>
                            Unidade principal:
                            <strong>{{ $version->unit }}</strong>.
                            Nesta tela de catálogo não há outros serviços
                            para selecionar.
                        </span>
                    </div>
                </div>

                <div
                    class="pv-create-pane"
                    data-create-pane="1"
                    hidden
                >
                    <div class="pv-section-title">
                        <span class="pv-section-title-icon">
                            <i class="ph-fill ph-user-circle"></i>
                        </span>

                        <span class="pv-section-title-copy">
                            <strong>Pessoa</strong>

                            <span>
                                Identificação do beneficiário da ordem.
                            </span>
                        </span>
                    </div>

                    <div class="pv-grid">
                        <div class="pv-field">
                            <span class="pv-label">
                                Associado
                                @if($membersOnly)
                                    <span class="pv-required">*</span>
                                @endif
                            </span>

                            <div class="pv-choice">
                                <span class="pv-choice-icon">
                                    <i class="ph-fill ph-user"></i>
                                </span>

                                <span class="pv-choice-copy">
                                    <small>
                                        {{
                                            $membersOnly
                                                ? 'Obrigatório neste serviço'
                                                : 'Opcional'
                                        }}
                                    </small>

                                    <strong>
                                        Selecionar associado
                                    </strong>
                                </span>

                                <i class="ph-fill ph-caret-right"></i>
                            </div>
                        </div>

                        <label class="pv-field">
                            <span class="pv-label">
                                Nome do beneficiário
                            </span>

                            <input
                                class="pv-control"
                                disabled
                                placeholder="Nome da pessoa atendida"
                            >
                        </label>
                    </div>
                </div>

                <div
                    class="pv-create-pane"
                    data-create-pane="2"
                    hidden
                >
                    <div class="pv-section-title">
                        <span class="pv-section-title-icon">
                            <i class="ph-fill ph-calendar-check"></i>
                        </span>

                        <span class="pv-section-title-copy">
                            <strong>Agendamento</strong>

                            <span>
                                Data, horário e local planejados.
                            </span>
                        </span>
                    </div>

                    <div class="pv-grid">
                        <label class="pv-field">
                            <span class="pv-label">
                                Data
                                <span class="pv-required">*</span>
                            </span>

                            <input
                                class="pv-control"
                                type="date"
                                disabled
                            >
                        </label>

                        <label class="pv-field">
                            <span class="pv-label">
                                Horário
                                <span class="pv-required">*</span>
                            </span>

                            <input
                                class="pv-control"
                                type="time"
                                disabled
                            >
                        </label>

                        <label class="pv-field full">
                            <span class="pv-label">
                                Local
                            </span>

                            <input
                                class="pv-control"
                                disabled
                                placeholder="Local de execução"
                            >
                        </label>
                    </div>
                </div>

                <div
                    class="pv-create-pane"
                    data-create-pane="3"
                    hidden
                >
                    <div class="pv-section-title">
                        <span class="pv-section-title-icon">
                            <i class="ph-fill ph-clipboard-text"></i>
                        </span>

                        <span class="pv-section-title-copy">
                            <strong>Dados do serviço</strong>

                            <span>
                                Campos definidos para o momento da criação.
                            </span>
                        </span>
                    </div>

                    @php
                        $orderFields =
                            $phaseFields->get('order');

                        $orderDataFields =
                            $orderFields->reject(
                                fn ($field) =>
                                    in_array(
                                        $field->type,
                                        $evidenceTypes,
                                        true
                                    )
                            );

                        $orderEvidenceFields =
                            $orderFields->filter(
                                fn ($field) =>
                                    in_array(
                                        $field->type,
                                        $evidenceTypes,
                                        true
                                    )
                            );
                    @endphp

                    @if($orderFields->isEmpty())
                        <div class="pv-empty">
                            Nenhum campo adicional será solicitado
                            durante a criação desta ordem.
                        </div>
                    @else
                        <div class="pv-grid">
                            @foreach($orderDataFields as $field)
                                <label
                                    class="
                                        pv-field
                                        {{
                                            $field->type === 'textarea'
                                                ? 'full'
                                                : ''
                                        }}
                                    "
                                >
                                    <span class="pv-label">
                                        {{ $field->label }}

                                        @if($field->required)
                                            <span class="pv-required">*</span>
                                        @endif

                                        @if($field->unit)
                                            <span class="pv-unit">
                                                ({{ $field->unit }})
                                            </span>
                                        @endif
                                    </span>

                                    @if($field->type === 'textarea')
                                        <textarea
                                            class="pv-control"
                                            disabled
                                            placeholder="{{ $field->placeholder }}"
                                        ></textarea>
                                    @elseif($field->type === 'boolean')
                                        <select
                                            class="pv-control"
                                            disabled
                                        >
                                            <option>Selecione</option>
                                            <option>Sim</option>
                                            <option>Não</option>
                                        </select>
                                    @elseif(
                                        in_array(
                                            $field->type,
                                            [
                                                'select',
                                                'member',
                                                'associate',
                                                'provider',
                                                'asset',
                                            ],
                                            true
                                        )
                                    )
                                        <select
                                            class="pv-control"
                                            disabled
                                        >
                                            <option>Selecione</option>

                                            @foreach(
                                                (array) ($field->options ?? [])
                                                as $key => $option
                                            )
                                                <option>
                                                    {{ $option }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input
                                            class="pv-control"
                                            type="{{
                                                in_array(
                                                    $field->type,
                                                    [
                                                        'integer',
                                                        'decimal',
                                                        'money',
                                                        'quantity',
                                                        'meter',
                                                    ],
                                                    true
                                                )
                                                    ? 'number'
                                                    : (
                                                        $field->type === 'date'
                                                            ? 'date'
                                                            : (
                                                                $field->type === 'datetime'
                                                                    ? 'datetime-local'
                                                                    : 'text'
                                                            )
                                                    )
                                            }}"
                                            disabled
                                            placeholder="{{ $field->placeholder }}"
                                        >
                                    @endif

                                    @if($field->help)
                                        <small class="pv-help">
                                            {{ $field->help }}
                                        </small>
                                    @endif
                                </label>
                            @endforeach

                            @foreach($orderEvidenceFields as $field)
                                <div class="pv-field full">
                                    <span class="pv-label">
                                        {{ $field->label }}

                                        @if($field->required)
                                            <span class="pv-required">*</span>
                                        @endif
                                    </span>

                                    <div class="pv-evidence">
                                        <i
                                            class="
                                                ph-fill
                                                {{
                                                    $field->type === 'image'
                                                        ? 'ph-camera'
                                                        : (
                                                            $field->type === 'signature'
                                                                ? 'ph-signature'
                                                                : 'ph-file-arrow-up'
                                                        )
                                                }}
                                            "
                                        ></i>

                                        <strong>
                                            {{
                                                $field->type === 'image'
                                                    ? 'Adicionar foto'
                                                    : (
                                                        $field->type === 'signature'
                                                            ? 'Registrar assinatura'
                                                            : 'Adicionar arquivo'
                                                    )
                                            }}
                                        </strong>

                                        <small>
                                            Este controle ficará disponível
                                            ao prestador durante o preenchimento.
                                        </small>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <footer class="pv-create-actions">
                <button
                    class="pv-button"
                    type="button"
                    data-create-previous
                    disabled
                >
                    <i class="ph-fill ph-arrow-left"></i>
                    Voltar
                </button>

                <button
                    class="pv-button primary"
                    type="button"
                    data-create-next
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </footer>
        </div>

        {{-- ==================================================
             EXECUÇÃO
             ================================================== --}}

        <div
            class="pv-screen"
            data-preview-screen="execution"
            hidden
        >
            <div class="pv-order-facts">
                <div class="pv-order-fact">
                    <small>Beneficiário</small>
                    <strong>Pessoa vinculada à ordem</strong>
                </div>

                <div class="pv-order-fact">
                    <small>Prestador</small>
                    <strong>Prestador responsável</strong>
                </div>

                <div class="pv-order-fact">
                    <small>Agendamento</small>
                    <strong>Data e horário da ordem</strong>
                </div>

                <div class="pv-order-fact">
                    <small>Local</small>
                    <strong>Local informado</strong>
                </div>
            </div>

            <nav
                class="pv-workflow"
                aria-label="Fluxo da execução"
            >
                @foreach([
                    'start',
                    'execution',
                    'finish',
                    'review',
                ] as $index => $phase)
                    <button
                        class="
                            pv-workflow-step
                            {{ $index === 0 ? 'active' : '' }}
                        "
                        type="button"
                        data-execution-phase="{{ $phase }}"
                    >
                        <span class="pv-workflow-icon">
                            <i
                                class="
                                    ph-fill
                                    {{ $phaseMeta[$phase]['icon'] }}
                                "
                            ></i>
                        </span>

                        <span class="pv-workflow-copy">
                            <strong>
                                {{ $phaseMeta[$phase]['short'] }}
                            </strong>

                            <small>
                                {{ $phaseMeta[$phase]['description'] }}
                            </small>
                        </span>
                    </button>
                @endforeach
            </nav>

            <div class="pv-execution-body">
                @foreach([
                    'start',
                    'execution',
                    'finish',
                    'review',
                ] as $phase)
                    @php
                        $fields =
                            $phaseFields->get($phase);

                        $dataFields =
                            $fields->reject(
                                fn ($field) =>
                                    in_array(
                                        $field->type,
                                        $evidenceTypes,
                                        true
                                    )
                            );

                        $evidenceFields =
                            $fields->filter(
                                fn ($field) =>
                                    in_array(
                                        $field->type,
                                        $evidenceTypes,
                                        true
                                    )
                            );
                    @endphp

                    <div
                        class="pv-phase-pane"
                        data-phase-pane="{{ $phase }}"
                        @if($phase !== 'start') hidden @endif
                    >
                        <div class="pv-section-title">
                            <span class="pv-section-title-icon">
                                <i
                                    class="
                                        ph-fill
                                        {{ $phaseMeta[$phase]['icon'] }}
                                    "
                                ></i>
                            </span>

                            <span class="pv-section-title-copy">
                                <strong>
                                    {{ $phaseMeta[$phase]['label'] }}
                                </strong>

                                <span>
                                    {{ $phaseMeta[$phase]['description'] }}
                                </span>
                            </span>
                        </div>

                        @if($fields->isEmpty())
                            <div class="pv-empty">
                                Nenhum campo será solicitado ao prestador
                                nesta fase.
                            </div>
                        @else
                            <div class="pv-grid">
                                @foreach($dataFields as $field)
                                    <label
                                        class="
                                            pv-field
                                            {{
                                                $field->type === 'textarea'
                                                    ? 'full'
                                                    : ''
                                            }}
                                        "
                                    >
                                        <span class="pv-label">
                                            {{ $field->label }}

                                            @if($field->required)
                                                <span class="pv-required">*</span>
                                            @endif

                                            @if($field->unit)
                                                <span class="pv-unit">
                                                    ({{ $field->unit }})
                                                </span>
                                            @endif
                                        </span>

                                        @if($field->type === 'textarea')
                                            <textarea
                                                class="pv-control"
                                                disabled
                                                placeholder="{{ $field->placeholder }}"
                                            ></textarea>
                                        @elseif($field->type === 'boolean')
                                            <select
                                                class="pv-control"
                                                disabled
                                            >
                                                @unless($field->required)
                                                    <option>Não informado</option>
                                                @endunless

                                                <option>Não</option>
                                                <option>Sim</option>
                                            </select>
                                        @elseif(
                                            in_array(
                                                $field->type,
                                                [
                                                    'select',
                                                    'member',
                                                    'associate',
                                                    'provider',
                                                    'asset',
                                                ],
                                                true
                                            )
                                        )
                                            <select
                                                class="pv-control"
                                                disabled
                                            >
                                                <option>Selecione</option>

                                                @foreach(
                                                    (array) ($field->options ?? [])
                                                    as $key => $option
                                                )
                                                    <option>
                                                        {{ $option }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input
                                                class="pv-control"
                                                type="{{
                                                    in_array(
                                                        $field->type,
                                                        [
                                                            'integer',
                                                            'decimal',
                                                            'money',
                                                            'quantity',
                                                            'meter',
                                                        ],
                                                        true
                                                    )
                                                        ? 'number'
                                                        : (
                                                            $field->type === 'date'
                                                                ? 'date'
                                                                : (
                                                                    $field->type === 'datetime'
                                                                        ? 'datetime-local'
                                                                        : 'text'
                                                                )
                                                        )
                                                }}"
                                                disabled
                                                placeholder="{{ $field->placeholder }}"
                                            >
                                        @endif

                                        @if($field->help)
                                            <small class="pv-help">
                                                {{ $field->help }}
                                            </small>
                                        @endif
                                    </label>
                                @endforeach

                                @foreach($evidenceFields as $field)
                                    <div class="pv-field full">
                                        <span class="pv-label">
                                            {{ $field->label }}

                                            @if($field->required)
                                                <span class="pv-required">*</span>
                                            @endif
                                        </span>

                                        <div class="pv-evidence">
                                            <i
                                                class="
                                                    ph-fill
                                                    {{
                                                        $field->type === 'image'
                                                            ? 'ph-camera'
                                                            : (
                                                                $field->type === 'signature'
                                                                    ? 'ph-signature'
                                                                    : 'ph-file-arrow-up'
                                                            )
                                                    }}
                                                "
                                            ></i>

                                            <strong>
                                                {{
                                                    $field->type === 'image'
                                                        ? 'Adicionar foto'
                                                        : (
                                                            $field->type === 'signature'
                                                                ? 'Registrar assinatura'
                                                                : 'Adicionar arquivo'
                                                        )
                                                }}
                                            </strong>

                                            <small>
                                                {{
                                                    $field->help
                                                        ?: 'Controle de evidência exibido ao prestador.'
                                                }}
                                            </small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($phase === 'start')
                            <div class="pv-readonly-note">
                                <i class="ph-fill ph-info"></i>

                                <span>
                                    Ao confirmar esta etapa, a ordem entra
                                    em execução e os campos seguintes ficam
                                    disponíveis.
                                </span>
                            </div>
                        @elseif($phase === 'finish')
                            <div class="pv-readonly-note">
                                <i class="ph-fill ph-paper-plane-tilt"></i>

                                <span>
                                    Após preencher os dados obrigatórios,
                                    o prestador envia a execução para
                                    {{
                                        $version->review_mode === 'automatic'
                                            ? 'conclusão automática'
                                            : 'conferência da gestão'
                                    }}.
                                </span>
                            </div>
                        @elseif($phase === 'review')
                            <div class="pv-readonly-note">
                                <i class="ph-fill ph-magnifying-glass"></i>

                                <span>
                                    Nesta etapa o prestador acompanha a
                                    situação da execução e eventuais solicitações
                                    de correção.
                                </span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener(
    'DOMContentLoaded',
    () => {
        const modeButtons = [
            ...document.querySelectorAll(
                '[data-preview-mode]'
            ),
        ];

        const screens = [
            ...document.querySelectorAll(
                '[data-preview-screen]'
            ),
        ];

        const showMode = mode => {
            modeButtons.forEach(button => {
                button.classList.toggle(
                    'active',
                    button.dataset.previewMode
                        === mode
                );
            });

            screens.forEach(screen => {
                screen.hidden =
                    screen.dataset.previewScreen
                    !== mode;
            });
        };

        modeButtons.forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    showMode(
                        button.dataset.previewMode
                    );
                }
            );
        });

        const createButtons = [
            ...document.querySelectorAll(
                '[data-create-step]'
            ),
        ];

        const createPanes = [
            ...document.querySelectorAll(
                '[data-create-pane]'
            ),
        ];

        const previous =
            document.querySelector(
                '[data-create-previous]'
            );

        const next =
            document.querySelector(
                '[data-create-next]'
            );

        let createStep = 0;

        const showCreateStep = index => {
            createStep =
                Math.max(
                    0,
                    Math.min(
                        index,
                        createPanes.length - 1
                    )
                );

            createButtons.forEach(
                (
                    button,
                    buttonIndex
                ) => {
                    button.classList.toggle(
                        'active',
                        buttonIndex
                            === createStep
                    );

                    if (
                        buttonIndex
                            === createStep
                    ) {
                        button.scrollIntoView({
                            behavior: 'smooth',
                            inline: 'center',
                            block: 'nearest',
                        });
                    }
                }
            );

            createPanes.forEach(
                (
                    pane,
                    paneIndex
                ) => {
                    pane.hidden =
                        paneIndex
                            !== createStep;
                }
            );

            if (previous) {
                previous.disabled =
                    createStep === 0;
            }

            if (next) {
                const last =
                    createStep
                    === createPanes.length - 1;

                next.innerHTML =
                    last
                        ? '<i class="ph-fill ph-eye"></i> Prévia concluída'
                        : 'Continuar <i class="ph-fill ph-arrow-right"></i>';

                next.disabled = last;
            }
        };

        createButtons.forEach(
            (
                button,
                index
            ) => {
                button.addEventListener(
                    'click',
                    () => {
                        showCreateStep(index);
                    }
                );
            }
        );

        previous?.addEventListener(
            'click',
            () => {
                showCreateStep(
                    createStep - 1
                );
            }
        );

        next?.addEventListener(
            'click',
            () => {
                showCreateStep(
                    createStep + 1
                );
            }
        );

        const workflowButtons = [
            ...document.querySelectorAll(
                '[data-execution-phase]'
            ),
        ];

        const phasePanes = [
            ...document.querySelectorAll(
                '[data-phase-pane]'
            ),
        ];

        const showPhase = phase => {
            workflowButtons.forEach(
                button => {
                    const active =
                        button.dataset
                            .executionPhase
                        === phase;

                    button.classList.toggle(
                        'active',
                        active
                    );

                    if (active) {
                        button.scrollIntoView({
                            behavior: 'smooth',
                            inline: 'center',
                            block: 'nearest',
                        });
                    }
                }
            );

            phasePanes.forEach(pane => {
                pane.hidden =
                    pane.dataset.phasePane
                    !== phase;
            });
        };

        workflowButtons.forEach(
            button => {
                button.addEventListener(
                    'click',
                    () => {
                        showPhase(
                            button.dataset
                                .executionPhase
                        );
                    }
                );
            }
        );

        showMode('create');
        showCreateStep(0);
        showPhase('start');
    }
);
</script>
@endsection