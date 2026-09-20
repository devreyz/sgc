@extends('layouts.bento')

@section('title', $order->number)
@section('page-title', $order->service->name)
@section('page-subtitle', 'Acompanhe a execução, evidências, conferência e valores desta ordem.')
@section('user-role', ($operator ?? false) ? 'Operação de serviços' : 'Prestador')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'provider',
        'orders',
        $tenantSlug
    );

    $execution = $order->execution;

    /*
     * Mantidos porque também ficam disponíveis para o partial
     * provider._service-fields.
     */
    $fields = collect(
        data_get(
            $execution->catalog_snapshot,
            'fields',
            []
        )
    );

    $evidenceFields = $fields->whereIn(
        'type',
        ['image', 'file', 'signature']
    );

    $uploadedKeys = $execution->evidences
        ->pluck('field_key');

    $providerOverride = $order
        ->serviceVersion
        ?->providerRates
        ?->first(
            fn ($rate) =>
                $rate->active
                && (int) $rate->service_provider_id
                    === (int) $order->service_provider_id
        );

    $snapshottedCompensation = data_get(
        $execution->catalog_snapshot,
        'provider_compensation'
    );

    $providerPricingMethod = is_array($snapshottedCompensation)
        ? data_get($snapshottedCompensation, 'method')
        : data_get($execution->catalog_snapshot, 'provider_pricing_method', $order->serviceVersion?->provider_pricing_method);

    $providerConfiguredRate = is_array($snapshottedCompensation)
        ? data_get($snapshottedCompensation, $providerPricingMethod === 'percent_of_base' ? 'percentage' : 'rate')
        : ($providerPricingMethod === 'fixed'
            ? ($providerOverride?->fixed_amount ?? $providerOverride?->rate ?? $order->serviceVersion?->default_provider_rate)
            : ($providerPricingMethod === 'percent_of_base'
                ? ($providerOverride?->percentage ?? $order->serviceVersion?->provider_percentage)
                : ($providerOverride?->rate ?? $providerOverride?->fixed_amount ?? $order->serviceVersion?->default_provider_rate)));

    $providerPricingSource = is_array($snapshottedCompensation)
        ? data_get($snapshottedCompensation, 'source')
        : ($providerOverride ? 'provider_override' : 'service_version_default');

    $statusLabels = [
        'draft' => 'Aguardando início',
        'rejected' => 'Correção solicitada',
        'in_progress' => 'Em execução',
        'submitted' => 'Enviado para conferência',
        'validated' => 'Concluído',
    ];

    $statusMeta = [
        'draft' => [
            'class' => 'is-draft',
            'icon' => 'ph-clock-countdown',
        ],
        'rejected' => [
            'class' => 'is-rejected',
            'icon' => 'ph-warning-circle',
        ],
        'in_progress' => [
            'class' => 'is-progress',
            'icon' => 'ph-play-circle',
        ],
        'submitted' => [
            'class' => 'is-submitted',
            'icon' => 'ph-paper-plane-tilt',
        ],
        'validated' => [
            'class' => 'is-validated',
            'icon' => 'ph-seal-check',
        ],
    ];

    $executionStatus =
        $execution->status
        ?? 'draft';

    $executionStatusLabel =
        $statusLabels[$executionStatus]
        ?? \Illuminate\Support\Str::headline(
            (string) $executionStatus
        );

    $executionStatusMeta =
        $statusMeta[$executionStatus]
        ?? [
            'class' => 'is-neutral',
            'icon' => 'ph-circle',
        ];

    $stepIndex = match ($executionStatus) {
        'draft', 'rejected' => 0,
        'in_progress' => 1,
        'submitted' => 2,
        'validated' => 3,
        default => 0,
    };

    $workflowSteps = [
        [
            'label' => 'Iniciar',
            'icon' => 'ph-play',
        ],
        [
            'label' => 'Executar',
            'icon' => 'ph-wrench',
        ],
        [
            'label' => 'Conferência',
            'icon' => 'ph-magnifying-glass',
        ],
        [
            'label' => 'Concluído',
            'icon' => 'ph-flag',
        ],
    ];

    $beneficiaryName =
        $order->beneficiary_snapshot['name']
        ?? 'Sem beneficiário';

    $providerName =
        $order->provider_snapshot['name']
        ?? $order->serviceProvider?->name
        ?? '—';

    $locationLabel =
        $order->location
        ?: 'Local não informado';

    $scheduledLabel =
        $order->scheduled_at
            ?->format('d/m/Y H:i')
        ?? 'Não agendado';

    $customerPricingMethod =
        $order->serviceVersion?->customer_pricing_method;

    $formatMethod = static function ($method): string {
        return match ($method) {
            'fixed' => 'Valor fixo por execução',
            'quantity_x_rate' => 'Quantidade × tarifa',
            'percent_of_base' => 'Percentual da cobrança',
            default => '—',
        };
    };

    $formatMoney = static fn ($value): string =>
        'R$ ' . number_format(
            (float) ($value ?? 0),
            2,
            ',',
            '.'
        );

    $receivablePreview =
        $financialPreview
            ? (float) (
                $financialPreview['receivable_total']
                ?? 0
            )
            : null;

    $payablePreview =
        $financialPreview
            ? (float) (
                $financialPreview['payable_total']
                ?? 0
            )
            : null;

    $evidenceTotal =
        $execution->evidences->count();

    $evidenceConfiguredTotal =
        $evidenceFields->count();

    $canApprove =
        $executionStatus === 'submitted'
        && ($operator ?? false)
        && auth()
            ->user()
            ->checkPermissionTo(
                'approve_service_execution'
            );

    /*
     * Valores escalares usados pela interface.
     * O JavaScript os lê pelos atributos data-* do <main>.
     */
    $draftUrl = route(
        'provider.orders.draft',
        [
            $tenantSlug,
            $order,
        ]
    );

    $meterStartField = data_get(
        $execution->catalog_snapshot,
        'execution_config.meter_start_field'
    );

    $meterEndField = data_get(
        $execution->catalog_snapshot,
        'execution_config.meter_end_field'
    );

    $executionUnit = (string) ($execution->unit ?? '');

    $serviceLocalKey = implode(
        ':',
        [
            'service-order-draft',
            (string) $tenantSlug,
            (string) $order->id,
        ]
    );

    $savedValues = collect(
        $execution->values ?? []
    )->filter(
        static fn ($value) =>
            $value !== null
            && $value !== ''
    );
@endphp

@section('content')
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css"
>
<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css"
>

<style>
    .service-execution {
        --svc-green: #219653;
        --svc-green-dark: #177c43;
        --svc-green-soft: #edf8f1;
        --svc-green-border: #cde8d6;

        --svc-blue: #3478d4;
        --svc-blue-soft: #eef4ff;
        --svc-blue-border: #d4e2f8;

        --svc-violet: #8a4bd2;
        --svc-violet-soft: #f5effc;
        --svc-violet-border: #e5d8f5;

        --svc-cyan: #168eae;
        --svc-cyan-soft: #edf8fb;
        --svc-cyan-border: #d2eaf0;

        --svc-amber: #c38418;
        --svc-amber-soft: #fff7e8;
        --svc-amber-border: #efdcb8;

        --svc-red: #cf5050;
        --svc-red-soft: #fff1f1;
        --svc-red-border: #f1cccc;

        --svc-slate: #64748b;
        --svc-slate-soft: #f2f5f7;

        --svc-text: var(--color-text, #17251c);
        --svc-text-2: var(--color-text-secondary, #58685e);
        --svc-muted: var(--color-text-muted, #87938b);
        --svc-border: var(--color-border, #d7e2da);
        --svc-border-strong: var(--color-border-strong, #becdc3);
        --svc-surface: var(--color-surface, #fff);
        --svc-soft: var(--color-surface-soft, #f7faf8);
        --svc-shadow: 0 5px 18px rgba(25, 61, 39, .055);

        display: grid;
        width: min(100%, 1380px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .72rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--svc-text);
    }

    .service-execution *,
    .service-execution *::before,
    .service-execution *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       SUPERFÍCIES
       ========================================================= */

    .svc-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 12px;
        background: var(--svc-surface);
        box-shadow: var(--svc-shadow);
    }

    .svc-panel-head {
        display: flex;
        min-width: 0;
        min-height: 62px;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        padding: .65rem .72rem;
        border-bottom: 1px solid var(--svc-border);
        background:
            linear-gradient(
                180deg,
                #fafcfb,
                #fff
            );
    }

    .svc-panel-title {
        display: flex;
        min-width: 0;
        gap: .58rem;
        align-items: center;
    }

    .svc-panel-icon {
        display: grid;
        width: 39px;
        height: 39px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 9px;
        background:
            var(
                --panel-soft,
                var(--svc-blue-soft)
            );
        color:
            var(
                --panel-tone,
                var(--svc-blue)
            );
    }

    .svc-panel-icon > i {
        display: block;
        font-size: 1.05rem;
        line-height: 1;
    }

    .svc-panel-copy {
        min-width: 0;
    }

    .svc-panel-copy h2,
    .svc-panel-copy p {
        margin: 0;
    }

    .svc-panel-copy h2 {
        color: var(--svc-text);
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .svc-panel-copy p {
        margin-top: .08rem;
        color: var(--svc-muted);
        font-size: .69rem;
        line-height: 1.35;
    }

    /* =========================================================
       CABEÇALHO PRINCIPAL
       ========================================================= */

    .svc-header {
        --status-tone: var(--svc-slate);
        --status-soft: var(--svc-slate-soft);
        --status-border: var(--svc-border);

        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .72rem;
        align-items: center;
        min-height: 84px;
        padding: .76rem .8rem;
        border: 1px solid var(--svc-border);
        border-radius: 12px;
        background:
            radial-gradient(
                circle at 100% 0,
                color-mix(
                    in srgb,
                    var(--status-tone) 9%,
                    transparent
                ),
                transparent 18rem
            ),
            linear-gradient(
                180deg,
                #fbfdfb,
                #fff
            );
        box-shadow: var(--svc-shadow);
    }

    .svc-header.is-draft {
        --status-tone: var(--svc-slate);
        --status-soft: var(--svc-slate-soft);
    }

    .svc-header.is-rejected {
        --status-tone: var(--svc-red);
        --status-soft: var(--svc-red-soft);
        --status-border: var(--svc-red-border);
    }

    .svc-header.is-progress {
        --status-tone: var(--svc-cyan);
        --status-soft: var(--svc-cyan-soft);
        --status-border: var(--svc-cyan-border);
    }

    .svc-header.is-submitted {
        --status-tone: var(--svc-amber);
        --status-soft: var(--svc-amber-soft);
        --status-border: var(--svc-amber-border);
    }

    .svc-header.is-validated {
        --status-tone: var(--svc-green);
        --status-soft: var(--svc-green-soft);
        --status-border: var(--svc-green-border);
    }

    .svc-header-main {
        display: flex;
        min-width: 0;
        gap: .62rem;
        align-items: center;
    }

    .svc-back,
    .svc-header-icon {
        display: grid;
        width: 42px;
        height: 42px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 9px;
    }

    .svc-back {
        border: 1px solid var(--svc-border);
        background: #fff;
        color: var(--svc-text-2);
        text-decoration: none;
        transition: .14s ease;
    }

    .svc-back:hover,
    .svc-back:focus-visible {
        border-color: var(--svc-blue-border);
        background: var(--svc-blue-soft);
        color: var(--svc-blue);
        outline: none;
    }

    .svc-header-icon {
        background: var(--status-soft);
        color: var(--status-tone);
    }

    .svc-header-copy {
        min-width: 0;
    }

    .svc-header-copy h1,
    .svc-header-copy p {
        margin: 0;
    }

    .svc-header-copy h1 {
        overflow: hidden;
        color: var(--svc-text);
        font-size: clamp(1.05rem, 2vw, 1.28rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-header-copy p {
        margin-top: .12rem;
        color: var(--svc-muted);
        font-size: .69rem;
        line-height: 1.4;
    }

    .svc-header-side {
        display: flex;
        gap: .38rem;
        align-items: center;
    }

    .svc-status {
        display: inline-flex;
        min-height: 32px;
        gap: .28rem;
        align-items: center;
        padding: .3rem .5rem;
        border: 1px solid var(--status-border);
        border-radius: 999px;
        background: var(--status-soft);
        color: var(--status-tone);
        font-size: .66rem;
        font-weight: 820;
        white-space: nowrap;
    }

    /* =========================================================
       ETAPAS
       ========================================================= */

    .svc-workflow {
        min-width: 0;
        padding: .62rem .7rem;
        border-top: 1px solid var(--svc-border);
        background: var(--svc-soft);
    }

    .svc-steps {
        position: relative;
        display: grid;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
        min-width: 0;
        gap: .35rem;
    }

    .svc-step {
        --step-tone: var(--svc-slate);
        --step-soft: #fff;
        --step-border: var(--svc-border);

        position: relative;
        z-index: 1;
        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            minmax(0, 1fr);
        gap: .35rem;
        align-items: center;
        min-height: 42px;
        padding: .38rem .48rem;
        border: 1px solid var(--step-border);
        border-radius: 8px;
        background: var(--step-soft);
        color: var(--step-tone);
    }

    .svc-step.done {
        --step-tone: var(--svc-green);
        --step-soft: var(--svc-green-soft);
        --step-border: var(--svc-green-border);
    }

    .svc-step.current {
        --step-tone: var(--svc-blue);
        --step-soft: var(--svc-blue-soft);
        --step-border: var(--svc-blue-border);
        box-shadow:
            inset 0 -2px 0
            color-mix(
                in srgb,
                var(--svc-blue) 48%,
                transparent
            );
    }

    .svc-step.rejected.current {
        --step-tone: var(--svc-red);
        --step-soft: var(--svc-red-soft);
        --step-border: var(--svc-red-border);
    }

    .svc-step-icon {
        display: grid;
        width: 25px;
        height: 25px;
        place-items: center;
        border-radius: 6px;
        background: #fff;
        color: var(--step-tone);
        font-size: .73rem;
    }

    .svc-step-copy {
        min-width: 0;
    }

    .svc-step-copy strong,
    .svc-step-copy span {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-step-copy strong {
        font-size: .64rem;
        font-weight: 800;
    }

    .svc-step-copy span {
        margin-top: .03rem;
        color: var(--svc-muted);
        font-size: .53rem;
        font-weight: 650;
    }

    /* =========================================================
       RESUMO TABULAR
       ========================================================= */

    .svc-summary-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1.12fr)
            minmax(320px, .88fr);
        gap: .72rem;
        align-items: start;
    }

    .svc-info-table,
    .svc-money-table,
    .svc-review-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
        font-size: .69rem;
    }

    .svc-info-table th,
    .svc-info-table td,
    .svc-money-table th,
    .svc-money-table td,
    .svc-review-table th,
    .svc-review-table td {
        padding: .52rem .62rem;
        border-bottom: 1px solid var(--svc-border);
        vertical-align: middle;
    }

    .svc-info-table tr:last-child th,
    .svc-info-table tr:last-child td,
    .svc-money-table tr:last-child th,
    .svc-money-table tr:last-child td,
    .svc-review-table tr:last-child th,
    .svc-review-table tr:last-child td {
        border-bottom: 0;
    }

    .svc-info-table th,
    .svc-money-table th,
    .svc-review-table th {
        width: 145px;
        background: #fbfdfc;
        color: var(--svc-muted);
        font-size: .58rem;
        font-weight: 800;
        letter-spacing: .025em;
        text-align: left;
        text-transform: uppercase;
    }

    .svc-info-table td,
    .svc-review-table td {
        color: var(--svc-text);
        font-weight: 720;
    }

    .svc-money-table td {
        text-align: right;
    }

    .svc-money-value {
        display: block;
        color: var(--svc-text);
        font-size: .85rem;
        font-weight: 860;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .svc-money-value.receivable {
        color: var(--svc-green);
    }

    .svc-money-value.payable {
        color: var(--svc-blue);
    }

    .svc-money-note {
        display: block;
        margin-top: .06rem;
        color: var(--svc-muted);
        font-size: .56rem;
        font-weight: 620;
        line-height: 1.38;
    }

    /* =========================================================
       FORMULÁRIOS DINÂMICOS
       ========================================================= */

    .svc-form-body {
        padding: .72rem;
    }

    /*
     * Formulário em colunas tipo masonry/Pinterest.
     *
     * Diferente do CSS Grid tradicional, cada campo mantém
     * sua altura natural. Um textarea, ajuda, evidência ou
     * preview maior não aumenta a altura dos campos vizinhos.
     *
     * A ordem do DOM continua preservada:
     * a leitura flui verticalmente dentro de cada coluna.
     */
    .svc-form {
        display: block;
        width: 100%;
        min-width: 0;
        column-count: 3;
        column-gap: .68rem;
        column-fill: balance;
    }

    /*
     * O partial provider._service-fields pode usar as classes
     * globais já existentes no layout. Aqui apenas harmonizamos
     * os campos dentro deste módulo.
     */
    .svc-form .form-group,
    .svc-form .field-group,
    .svc-form .svc-field {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        margin: 0 0 .62rem;
        break-inside: avoid;
        page-break-inside: avoid;
        -webkit-column-break-inside: avoid;
    }

    /*
     * Os partials atuais usam .svc-field como unidade principal.
     * inline-grid faz o bloco participar corretamente do fluxo
     * multicoluna sem esticar a altura da coluna vizinha.
     */
    .svc-form .svc-field {
        display: inline-grid;
        vertical-align: top;
    }

    .svc-form label,
    .svc-form .form-label {
        display: block;
        margin-bottom: .2rem;
        color: var(--svc-text-2);
        font-size: .62rem;
        font-weight: 770;
    }

    .svc-form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),
    .svc-form select,
    .svc-form textarea {
        width: 100%;
        min-height: 40px;
        border: 1px solid var(--svc-border-strong);
        border-radius: 8px;
        outline: none;
        background: #fff;
        color: var(--svc-text);
        font: inherit;
        font-size: .7rem;
    }

    .svc-form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),
    .svc-form select {
        padding: .48rem .56rem;
    }

    .svc-form textarea {
        min-height: 88px;
        padding: .5rem .56rem;
        resize: vertical;
    }

    .svc-form input:focus,
    .svc-form select:focus,
    .svc-form textarea:focus {
        border-color: var(--svc-blue);
        box-shadow:
            0 0 0 3px
            rgba(52, 120, 212, .1);
    }

    .svc-file {
        width: 100%;
        min-height: 42px;
        padding: .52rem;
        border: 1px dashed var(--svc-border-strong);
        border-radius: 8px;
        background: var(--svc-soft);
        color: var(--svc-text-2);
    }

    .svc-file-preview {
        min-width: 0;
        margin-top: .35rem;
    }

    .svc-file-preview small {
        color: var(--svc-muted);
        font-size: .6rem;
    }

    .svc-file-preview img {
        display: block;
        max-width: 100%;
        max-height: 12rem;
        object-fit: contain;
        border: 1px solid var(--svc-border);
        border-radius: 8px;
        background: #fff;
    }

    .svc-form-actions {
        column-span: all;
        width: 100%;
        break-inside: avoid;
        display: flex;
        gap: .42rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        margin-top: .05rem;
        padding-top: .68rem;
        border-top: 1px solid var(--svc-border);
    }

    /* =========================================================
       BOTÕES
       ========================================================= */

    .svc-btn {
        display: inline-flex;
        min-height: 39px;
        gap: .34rem;
        align-items: center;
        justify-content: center;
        padding: .44rem .64rem;
        border: 1px solid var(--svc-border-strong);
        border-radius: 8px;
        background: #fff;
        color: var(--svc-text);
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 790;
        text-decoration: none;
        transition: .14s ease;
        white-space: nowrap;
    }

    .svc-btn:hover,
    .svc-btn:focus-visible {
        border-color: var(--svc-blue-border);
        background: var(--svc-blue-soft);
        color: var(--svc-blue);
        outline: none;
    }

    .svc-btn.primary {
        border-color: var(--svc-green-dark);
        background:
            linear-gradient(
                180deg,
                #25a95f,
                #1d914f
            );
        color: #fff;
        box-shadow:
            0 6px 14px rgba(33, 150, 83, .14);
    }

    .svc-btn.primary:hover,
    .svc-btn.primary:focus-visible {
        border-color: var(--svc-green-dark);
        background: var(--svc-green-dark);
        color: #fff;
    }

    /* =========================================================
       ALERTA DE CORREÇÃO
       ========================================================= */

    .svc-alert {
        --alert-tone: var(--svc-blue);
        --alert-soft: var(--svc-blue-soft);
        --alert-border: var(--svc-blue-border);

        display: flex;
        min-width: 0;
        gap: .5rem;
        align-items: flex-start;
        margin-bottom: .65rem;
        padding: .55rem .6rem;
        border: 1px solid var(--alert-border);
        border-radius: 8px;
        background: var(--alert-soft);
        color: var(--svc-text-2);
        font-size: .67rem;
        line-height: 1.45;
    }

    .svc-alert.danger {
        --alert-tone: var(--svc-red);
        --alert-soft: var(--svc-red-soft);
        --alert-border: var(--svc-red-border);
    }

    .svc-alert.warning {
        --alert-tone: var(--svc-amber);
        --alert-soft: var(--svc-amber-soft);
        --alert-border: var(--svc-amber-border);
    }

    .svc-alert.success {
        --alert-tone: var(--svc-green);
        --alert-soft: var(--svc-green-soft);
        --alert-border: var(--svc-green-border);
    }

    .svc-alert-icon {
        display: grid;
        width: 29px;
        height: 29px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 7px;
        background: #fff;
        color: var(--alert-tone);
    }

    .svc-alert strong {
        color: var(--svc-text);
    }

    /* =========================================================
       PREVIEW DE MEDIÇÃO
       ========================================================= */

    .svc-preview {
        column-span: all;
        width: 100%;
        break-inside: avoid;
        display: none;
        grid-template-columns:
            auto
            minmax(0, 1fr)
            auto;
        gap: .5rem;
        align-items: center;
        padding: .54rem .6rem;
        border: 1px solid var(--svc-blue-border);
        border-radius: 8px;
        background: var(--svc-blue-soft);
    }

    .svc-preview-icon {
        display: grid;
        width: 31px;
        height: 31px;
        place-items: center;
        border-radius: 7px;
        background: #fff;
        color: var(--svc-blue);
    }

    .svc-preview-copy {
        min-width: 0;
    }

    .svc-preview-copy span,
    .svc-preview-copy small {
        display: block;
    }

    .svc-preview-copy span {
        color: var(--svc-text-2);
        font-size: .61rem;
        font-weight: 720;
    }

    .svc-preview-copy small {
        margin-top: .04rem;
        color: var(--svc-muted);
        font-size: .56rem;
    }

    .svc-preview-result {
        color: var(--svc-blue);
        font-size: .9rem;
        font-weight: 860;
        white-space: nowrap;
    }

    .svc-preview.is-invalid {
        border-color: var(--svc-red-border);
        background: var(--svc-red-soft);
    }

    .svc-preview.is-invalid
    .svc-preview-icon,
    .svc-preview.is-invalid
    .svc-preview-result {
        color: var(--svc-red);
    }

    /* =========================================================
       EVIDÊNCIAS
       ========================================================= */

    .svc-evidence-wrap {
        min-width: 0;
        overflow-x: auto;
    }

    .svc-evidence-table {
        width: 100%;
        min-width: 620px;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .68rem;
    }

    .svc-evidence-table th {
        padding: .5rem .6rem;
        border-bottom: 1px solid var(--svc-border-strong);
        background:
            linear-gradient(
                180deg,
                #f5f8f6,
                #eff4f1
            );
        color: #6f7c74;
        font-size: .57rem;
        font-weight: 820;
        letter-spacing: .04em;
        text-align: left;
        text-transform: uppercase;
    }

    .svc-evidence-table td {
        padding: .54rem .6rem;
        border-bottom: 1px solid var(--svc-border);
        color: var(--svc-text-2);
        vertical-align: middle;
    }

    .svc-evidence-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .evidence-name {
        display: flex;
        min-width: 0;
        gap: .38rem;
        align-items: center;
    }

    .evidence-icon {
        display: grid;
        width: 30px;
        height: 30px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 7px;
        background: var(--svc-violet-soft);
        color: var(--svc-violet);
    }

    .evidence-name strong {
        min-width: 0;
        overflow: hidden;
        color: var(--svc-text);
        font-size: .67rem;
        font-weight: 790;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-table-link {
        display: inline-flex;
        min-height: 31px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .3rem .45rem;
        border: 1px solid var(--svc-border);
        border-radius: 7px;
        background: #fff;
        color: var(--svc-blue);
        font-size: .62rem;
        font-weight: 770;
        text-decoration: none;
    }

    /* =========================================================
       CONFERÊNCIA
       ========================================================= */

    .svc-review-body {
        padding: .7rem;
    }

    .svc-review-layout {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .65rem;
        align-items: end;
    }

    .svc-review-table-wrap {
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 8px;
    }

    .svc-review-table th {
        width: 145px;
    }

    .svc-review-result {
        color: var(--svc-blue);
        font-weight: 840;
    }

    .svc-review-action {
        display: flex;
        justify-content: flex-end;
    }

    /* =========================================================
       BADGES AUXILIARES
       ========================================================= */

    .svc-count {
        display: inline-flex;
        min-height: 28px;
        gap: .25rem;
        align-items: center;
        padding: .24rem .42rem;
        border-radius: 999px;
        background: var(--svc-slate-soft);
        color: var(--svc-text-2);
        font-size: .6rem;
        font-weight: 780;
        white-space: nowrap;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 980px) {
        .svc-summary-grid {
            grid-template-columns: 1fr;
        }

        .svc-review-layout {
            grid-template-columns: 1fr;
        }

        .svc-review-action .svc-btn {
            width: 100%;
        }
    }

    /*
     * Tablet: duas colunas independentes.
     * Mobile: uma coluna, mantendo a ordem natural do formulário.
     */
    @media (max-width: 1120px) and (min-width: 721px) {
        .svc-form {
            column-count: 2;
            column-gap: .62rem;
        }
    }

    @media (max-width: 720px) {
        .svc-form {
            column-count: 1;
            column-gap: 0;
        }

        .svc-form .svc-field {
            display: grid;
            margin-bottom: .5rem;
        }

        .svc-header {
            grid-template-columns: 1fr;
        }

        .svc-header-side {
            justify-content: flex-start;
        }

        .svc-panel-copy p {
            display: none;
        }

        .svc-steps {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .svc-form-actions {
            display: grid;
            grid-template-columns: 1fr;
        }

        .svc-form-actions .svc-btn,
        .svc-form-actions .btn {
            width: 100%;
        }

        .svc-evidence-wrap {
            overflow: visible;
            padding: .55rem;
        }

        .svc-evidence-table {
            display: block;
            min-width: 0;
        }

        .svc-evidence-table thead {
            display: none;
        }

        .svc-evidence-table tbody {
            display: grid;
            gap: .42rem;
        }

        .svc-evidence-table tr {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                auto;
            gap: .4rem;
            padding: .5rem;
            border: 1px solid var(--svc-border);
            border-left: 3px solid var(--svc-violet);
            border-radius: 8px;
            background: #fff;
        }

        .svc-evidence-table td {
            padding: 0;
            border: 0;
        }
    }

    @media (max-width: 560px) {
        .svc-header-icon {
            display: none;
        }

        .svc-header-copy h1 {
            white-space: normal;
        }

        .svc-info-table,
        .svc-money-table,
        .svc-review-table {
            display: block;
        }

        .svc-info-table tbody,
        .svc-money-table tbody,
        .svc-review-table tbody {
            display: grid;
        }

        .svc-info-table tr,
        .svc-money-table tr,
        .svc-review-table tr {
            display: grid;
            grid-template-columns: 1fr;
            padding: .44rem .55rem;
            border-bottom: 1px solid var(--svc-border);
        }

        .svc-info-table tr:last-child,
        .svc-money-table tr:last-child,
        .svc-review-table tr:last-child {
            border-bottom: 0;
        }

        .svc-info-table th,
        .svc-info-table td,
        .svc-money-table th,
        .svc-money-table td,
        .svc-review-table th,
        .svc-review-table td {
            width: auto;
            padding: 0;
            border: 0;
            background: transparent;
            text-align: left;
        }

        .svc-info-table th,
        .svc-money-table th,
        .svc-review-table th {
            margin-bottom: .08rem;
        }

        .svc-money-table td {
            margin-top: .05rem;
        }

        .svc-preview {
            grid-template-columns:
                auto
                minmax(0, 1fr);
        }

        .svc-preview-result {
            grid-column: 2;
            justify-self: start;
        }
    }

    @media (max-width: 420px) {
        .svc-steps {
            grid-template-columns: 1fr;
        }

        .svc-header-main {
            align-items: flex-start;
        }

        .svc-back {
            width: 38px;
            height: 38px;
        }

        .svc-evidence-table tr {
            grid-template-columns: 1fr;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .service-execution *,
        .service-execution *::before,
        .service-execution *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }

    /* =========================================================
       WORKSPACE V2 — coerência com Project Workspace
       ========================================================= */

    .service-execution {
        --svc-green: #219653;
        --svc-green-dark: #177c43;
        --svc-green-soft: #edf8f1;
        --svc-green-border: #cde8d6;

        --svc-blue: #3478d4;
        --svc-blue-soft: #eef4ff;
        --svc-blue-border: #d4e2f8;

        --svc-violet: #8a4bd2;
        --svc-violet-soft: #f5effc;
        --svc-violet-border: #e5d8f5;

        --svc-cyan: #168eae;
        --svc-cyan-soft: #edf8fb;
        --svc-cyan-border: #d2eaf0;

        --svc-amber: #c38418;
        --svc-amber-soft: #fff7e8;
        --svc-amber-border: #efdcb8;

        --svc-red: #cf5050;
        --svc-red-soft: #fff1f1;
        --svc-red-border: #f1cccc;

        --svc-slate: #64748b;
        --svc-slate-soft: #f2f5f7;

        --svc-text: var(--color-text, #17251c);
        --svc-text-2: var(--color-text-secondary, #58685e);
        --svc-muted: var(--color-text-muted, #87938b);
        --svc-border: var(--color-border, #d7e2da);
        --svc-border-strong: var(--color-border-strong, #becdc3);
        --svc-surface: var(--color-surface, #fff);
        --svc-soft: var(--color-surface-soft, #f7faf8);
        --svc-shadow: 0 5px 18px rgba(25, 61, 39, .055);

        width: min(100%, 1380px);
        gap: .78rem;
    }

    /* O topo passa a ser composto por duas peças:
       cabeçalho + stepper sticky, como header + tabs do Project Workspace. */
    .svc-top {
        display: grid;
        gap: .58rem;
        overflow: visible;
        border: 0;
        border-radius: 0;
        background: transparent;
        box-shadow: none;
    }

    .svc-top .svc-header {
        --status-tone: var(--svc-blue);
        --status-soft: var(--svc-blue-soft);
        --status-border: var(--svc-blue-border);

        min-height: 76px;
        padding: .72rem .78rem;
        border: 1px solid var(--svc-border);
        border-radius: 12px;
        background:
            radial-gradient(
                circle at 100% 0,
                color-mix(
                    in srgb,
                    var(--status-tone) 10%,
                    transparent
                ),
                transparent 19rem
            ),
            linear-gradient(
                180deg,
                #fbfdfb,
                #fff
            );
        box-shadow: var(--svc-shadow);
    }

    .svc-top .svc-header.is-draft {
        --status-tone: var(--svc-slate);
        --status-soft: var(--svc-slate-soft);
        --status-border: var(--svc-border);
    }

    .svc-top .svc-header.is-rejected {
        --status-tone: var(--svc-red);
        --status-soft: var(--svc-red-soft);
        --status-border: var(--svc-red-border);
    }

    .svc-top .svc-header.is-progress {
        --status-tone: var(--svc-cyan);
        --status-soft: var(--svc-cyan-soft);
        --status-border: var(--svc-cyan-border);
    }

    .svc-top .svc-header.is-submitted {
        --status-tone: var(--svc-amber);
        --status-soft: var(--svc-amber-soft);
        --status-border: var(--svc-amber-border);
    }

    .svc-top .svc-header.is-validated {
        --status-tone: var(--svc-green);
        --status-soft: var(--svc-green-soft);
        --status-border: var(--svc-green-border);
    }

    .svc-header-main {
        gap: .62rem;
    }

    .svc-back,
    .svc-header-icon {
        width: 42px;
        height: 42px;
        border-radius: 9px;
    }

    .svc-header-copy h1 {
        font-size: clamp(1.03rem, 2vw, 1.25rem);
        font-weight: 850;
        letter-spacing: -.03em;
    }

    .svc-meta {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        gap: .16rem .65rem;
        margin-top: .2rem;
        color: var(--svc-muted);
        font-size: .68rem;
        font-weight: 610;
    }

    .svc-meta > span {
        display: inline-flex;
        min-width: 0;
        gap: .25rem;
        align-items: center;
    }

    .svc-meta i {
        flex: 0 0 auto;
        color: var(--svc-blue);
        font-size: .76rem;
    }

    .svc-meta-text {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* STEPS NO TOPO */
    .svc-top .svc-workflow {
        position: sticky;
        z-index: 34;
        top: .2rem;
        min-width: 0;
        padding: .34rem;
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 12px;
        background: rgba(255, 255, 255, .97);
        box-shadow:
            0 4px 15px rgba(25, 61, 39, .05);
    }

    .svc-top .svc-steps {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(4, minmax(145px, 1fr));
        gap: .22rem;
        overflow-x: auto;
        scrollbar-width: none;
        overscroll-behavior-inline: contain;
    }

    .svc-top .svc-steps::-webkit-scrollbar {
        display: none;
    }

    .svc-top .svc-step {
        --step-tone: var(--svc-slate);
        --step-soft: transparent;
        --step-border: transparent;

        position: relative;
        display: grid;
        min-width: 145px;
        min-height: 44px;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: .38rem;
        align-items: center;
        padding: .36rem .48rem;
        overflow: hidden;
        border: 1px solid var(--step-border);
        border-radius: 8px;
        background: var(--step-soft);
        color: var(--step-tone);
        box-shadow: none;
    }

    .svc-top .svc-step:nth-child(1) {
        --step-tone: var(--svc-blue);
    }

    .svc-top .svc-step:nth-child(2) {
        --step-tone: var(--svc-cyan);
    }

    .svc-top .svc-step:nth-child(3) {
        --step-tone: var(--svc-amber);
    }

    .svc-top .svc-step:nth-child(4) {
        --step-tone: var(--svc-green);
    }

    .svc-top .svc-step.done {
        --step-soft: var(--svc-green-soft);
        --step-border: var(--svc-green-border);
        --step-tone: var(--svc-green);
    }

    .svc-top .svc-step.current {
        --step-soft:
            color-mix(
                in srgb,
                var(--step-tone) 9%,
                #fff
            );
        --step-border:
            color-mix(
                in srgb,
                var(--step-tone) 27%,
                var(--svc-border)
            );
        box-shadow:
            inset 0 -2px 0
            color-mix(
                in srgb,
                var(--step-tone) 52%,
                transparent
            );
    }

    .svc-top .svc-step.rejected.current {
        --step-tone: var(--svc-red);
        --step-soft: var(--svc-red-soft);
        --step-border: var(--svc-red-border);
    }

    .svc-top .svc-step-icon {
        position: relative;
        width: 28px;
        height: 28px;
        border: 1px solid
            color-mix(
                in srgb,
                var(--step-tone) 18%,
                var(--svc-border)
            );
        border-radius: 7px;
        background: #fff;
        color: var(--step-tone);
    }

    .svc-top .svc-step-copy strong {
        color: var(--step-tone);
        font-size: .66rem;
        font-weight: 810;
    }

    .svc-top .svc-step-copy span {
        margin-top: .02rem;
        color: var(--svc-muted);
        font-size: .54rem;
        font-weight: 660;
    }

    /* Seções no mesmo vocabulário do Project Workspace. */
    .svc-panel {
        border-radius: 12px;
        box-shadow: var(--svc-shadow);
    }

    .svc-panel-head {
        min-height: 62px;
        padding: .65rem .72rem;
        background:
            linear-gradient(
                180deg,
                #fafcfb,
                #fff
            );
    }

    .svc-panel-icon {
        width: 39px;
        height: 39px;
        border-radius: 9px;
    }

    .svc-panel-copy h2 {
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .svc-panel-copy p {
        margin-top: .08rem;
        font-size: .69rem;
    }

    /*
     * Resumo vira uma única superfície visual.
     * Continua usando exatamente as duas tabelas e os mesmos dados.
     */
    .svc-summary-grid {
        gap: 0;
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 12px;
        background: #fff;
        box-shadow: var(--svc-shadow);
    }

    .svc-summary-grid > .svc-panel {
        border: 0;
        border-radius: 0;
        box-shadow: none;
    }

    .svc-summary-grid > .svc-panel + .svc-panel {
        border-left: 1px solid var(--svc-border);
    }

    .svc-summary-grid .svc-panel-head {
        background:
            linear-gradient(
                180deg,
                #fbfdfc,
                #fff
            );
    }

    /* Tabelas mais próximas da linguagem data-table do workspace. */
    .svc-info-table th,
    .svc-money-table th,
    .svc-review-table th,
    .svc-evidence-table th {
        background:
            linear-gradient(
                180deg,
                #f5f8f6,
                #eff4f1
            );
    }

    .svc-info-table tr:hover td,
    .svc-money-table tr:hover td,
    .svc-review-table tr:hover td,
    .svc-evidence-table tbody tr:hover td {
        background: #fafcfb;
    }

    .svc-money-value {
        font-size: .82rem;
    }

    /* Formulários são área operacional, sem "cards dentro de cards". */
    .svc-form-body {
        padding: .7rem;
        background: #fff;
    }

    .svc-form {
        gap: .62rem;
    }

    .svc-form input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]),
    .svc-form select,
    .svc-form textarea,
    .svc-file {
        border-radius: 8px;
    }

    .svc-form-actions {
        margin-top: .08rem;
        padding-top: .62rem;
        border-top: 1px solid var(--svc-border);
    }

    .svc-btn {
        min-height: 38px;
        border-radius: 8px;
    }

    .svc-alert {
        border-radius: 9px;
    }

    /* Conferência mais tabular / operacional. */
    .svc-review-table-wrap {
        border-radius: 9px;
    }

    .svc-evidence-table {
        background: #fff;
    }

    /* Desktop largo: mantém leitura densa. */
    @media (min-width: 1100px) {
        .svc-summary-grid {
            grid-template-columns:
                minmax(0, 1.15fr)
                minmax(320px, .85fr);
        }

        .svc-info-table th {
            width: 150px;
        }

        .svc-money-table th {
            width: auto;
        }
    }

    @media (max-width: 980px) {
        .svc-summary-grid {
            display: grid;
            grid-template-columns: 1fr;
        }

        .svc-summary-grid > .svc-panel + .svc-panel {
            border-top: 1px solid var(--svc-border);
            border-left: 0;
        }
    }

    @media (max-width: 720px) {
        .svc-top .svc-header {
            grid-template-columns: 1fr;
        }

        .svc-top .svc-header-side {
            justify-content: flex-start;
        }

        .svc-top .svc-steps {
            display: flex;
            gap: .2rem;
        }

        .svc-top .svc-step {
            flex: 0 0 154px;
            min-width: 154px;
        }

        .svc-meta {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            width: 100%;
            gap: .15rem .55rem;
        }

        .svc-meta > span {
            min-width: 0;
        }

        .svc-meta-text {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    }

    @media (max-width: 520px) {
        .svc-top .svc-header {
            padding: .64rem;
        }

        .svc-header-icon {
            display: grid;
        }

        .svc-header-copy h1 {
            white-space: normal;
        }

        .svc-meta {
            grid-template-columns: 1fr;
        }

        .svc-top .svc-workflow {
            top: .12rem;
            padding: .3rem;
        }

        .svc-top .svc-step {
            flex-basis: 142px;
            min-width: 142px;
        }

        .svc-summary-grid {
            border-radius: 10px;
        }
    }

    @media (max-width: 390px) {
        .svc-header-icon {
            display: none;
        }
    }


    /* Campos dinâmicos e evidências */
    .svc-field {
        display: grid;
        width: 100%;
        min-width: 0;
        gap: .42rem;
        padding: .54rem .58rem;
        border: 1px solid var(--svc-border);
        border-radius: 8px;
        background: #fff;
        box-shadow: none;
    }

    .svc-field > label {
        display: grid;
        gap: .35rem;
    }

    .svc-field > label > span,
    .svc-evidence-label {
        color: var(--svc-text);
        font-size: .66rem;
        font-weight: 770;
    }

    .svc-required {
        color: var(--svc-red);
    }

    .svc-field-unit,
    .svc-optional,
    .svc-field-help {
        color: var(--svc-muted);
        font-size: .58rem;
        font-weight: 560;
        line-height: 1.4;
    }

    .svc-evidence-field {
        display: grid;
        min-width: 0;
        gap: .35rem;
        padding: .5rem .52rem;
        border: 1px dashed var(--svc-border-strong);
        border-radius: 7px;
        background: var(--svc-soft);
    }

    .svc-form .svc-field > label,
    .svc-form .svc-evidence-field {
        min-width: 0;
        max-width: 100%;
    }

    .svc-form .svc-field > label > span,
    .svc-form .svc-evidence-label {
        display: block;
        min-width: 0;
        overflow-wrap: anywhere;
        word-break: normal;
        line-height: 1.35;
    }

    .svc-form .svc-field input,
    .svc-form .svc-field select,
    .svc-form .svc-field textarea {
        min-width: 0;
        max-width: 100%;
    }

    .svc-form .svc-field textarea {
        min-height: 92px;
    }

    .svc-form .svc-file-preview img {
        width: auto;
        max-width: 100%;
        height: auto;
    }

    /*
     * Selects muito longos não devem forçar a largura da coluna.
     */
    .svc-form select {
        text-overflow: ellipsis;
    }


    .svc-evidence-field[hidden],
    .svc-field[hidden] {
        display: none !important;
    }


    /* =========================================================
       TOPO COMPACTO — prioridade para a ação atual
       ========================================================= */

    .svc-top {
        gap: .38rem;
    }

    .svc-top .svc-header {
        min-height: 66px;
        padding: .56rem .68rem;
    }

    .svc-back,
    .svc-header-icon {
        width: 38px;
        height: 38px;
    }

    .svc-header-copy h1 {
        font-size: clamp(.98rem, 1.8vw, 1.15rem);
    }

    .svc-meta {
        margin-top: .12rem;
        gap: .14rem .55rem;
        font-size: .63rem;
    }

    .svc-status {
        min-height: 28px;
        padding: .24rem .42rem;
        font-size: .61rem;
    }

    /* Stepper deliberadamente baixo e sem subtítulos. */
    .svc-top .svc-workflow {
        padding: .26rem;
        border-radius: 10px;
    }

    .svc-top .svc-steps {
        grid-template-columns:
            repeat(4, minmax(110px, 1fr));
        gap: .16rem;
    }

    .svc-top .svc-step {
        min-width: 110px;
        min-height: 34px;
        grid-template-columns: 22px minmax(0, 1fr);
        gap: .28rem;
        padding: .26rem .38rem;
        border-radius: 7px;
    }

    .svc-top .svc-step-icon {
        width: 22px;
        height: 22px;
        border: 0;
        border-radius: 6px;
        font-size: .62rem;
    }

    .svc-top .svc-step-copy strong {
        font-size: .61rem;
    }

    /* =========================================================
       CONTEXTO COMPACTO
       ========================================================= */

    .svc-context-strip {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .45rem;
        align-items: stretch;
        min-width: 0;
        padding: .44rem .52rem;
        border: 1px solid var(--svc-border);
        border-radius: 10px;
        background: #fff;
        box-shadow: var(--svc-shadow);
    }

    .svc-context-main {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
        min-width: 0;
    }

    .svc-context-item {
        display: grid;
        min-width: 0;
        grid-template-columns: 27px minmax(0, 1fr);
        gap: .34rem;
        align-items: center;
        padding: .12rem .48rem;
    }

    .svc-context-item + .svc-context-item {
        border-left: 1px solid var(--svc-border);
    }

    .svc-context-item > i {
        display: grid;
        width: 27px;
        height: 27px;
        place-items: center;
        border-radius: 7px;
        background: var(--svc-blue-soft);
        color: var(--svc-blue);
        font-size: .74rem;
    }

    .svc-context-item span {
        min-width: 0;
    }

    .svc-context-item small,
    .svc-context-item strong {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-context-item small {
        color: var(--svc-muted);
        font-size: .52rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .025em;
    }

    .svc-context-item strong {
        margin-top: .03rem;
        color: var(--svc-text);
        font-size: .65rem;
        font-weight: 790;
    }

    .svc-order-details {
        position: relative;
        align-self: center;
    }

    .svc-order-details > summary {
        display: inline-flex;
        min-height: 34px;
        gap: .28rem;
        align-items: center;
        padding: .3rem .42rem;
        border: 1px solid var(--svc-border);
        border-radius: 7px;
        background: var(--svc-soft);
        color: var(--svc-text-2);
        cursor: pointer;
        font-size: .61rem;
        font-weight: 760;
        list-style: none;
        white-space: nowrap;
    }

    .svc-order-details > summary::-webkit-details-marker {
        display: none;
    }

    .svc-details-caret {
        transition: transform .15s ease;
    }

    .svc-order-details[open] .svc-details-caret {
        transform: rotate(180deg);
    }

    .svc-order-details-body {
        position: absolute;
        z-index: 45;
        top: calc(100% + .4rem);
        right: 0;
        width: min(88vw, 430px);
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 9px;
        background: #fff;
        box-shadow: 0 18px 42px rgba(19, 50, 30, .16);
    }

    .svc-details-list {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        margin: 0;
    }

    .svc-details-list > div {
        min-width: 0;
        padding: .52rem .58rem;
        border-bottom: 1px solid var(--svc-border);
    }

    .svc-details-list > div:nth-child(odd) {
        border-right: 1px solid var(--svc-border);
    }

    .svc-details-list dt,
    .svc-details-list dd {
        margin: 0;
    }

    .svc-details-list dt {
        color: var(--svc-muted);
        font-size: .54rem;
        font-weight: 760;
        text-transform: uppercase;
    }

    .svc-details-list dd {
        margin-top: .08rem;
        color: var(--svc-text);
        font-size: .65rem;
        font-weight: 740;
        overflow-wrap: anywhere;
    }

    /* Financeiro vira faixa curta, não seção/card. */
    .svc-finance-strip {
        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            1px
            minmax(0, 1fr);
        gap: .48rem;
        align-items: center;
        min-width: 0;
        padding: .4rem .54rem;
        border: 1px solid var(--svc-border);
        border-radius: 10px;
        background: #fff;
    }

    .svc-finance-divider {
        align-self: stretch;
        background: var(--svc-border);
    }

    .svc-finance-item {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .04rem .5rem;
        align-items: center;
    }

    .svc-finance-item > span {
        display: inline-flex;
        min-width: 0;
        gap: .25rem;
        align-items: center;
        color: var(--svc-muted);
        font-size: .57rem;
        font-weight: 730;
    }

    .svc-finance-item > span i {
        color: currentColor;
        font-size: .72rem;
    }

    .svc-finance-item strong {
        grid-row: 1 / span 2;
        grid-column: 2;
        color: var(--svc-text);
        font-size: .73rem;
        font-weight: 850;
        white-space: nowrap;
    }

    .svc-finance-item small {
        min-width: 0;
        overflow: hidden;
        color: var(--svc-muted);
        font-size: .53rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-finance-item.receivable strong {
        color: var(--svc-green);
    }

    .svc-finance-item.payable strong {
        color: var(--svc-blue);
    }

    /* =========================================================
       IMAGEM: seletor normal + preview clicável
       ========================================================= */

    .svc-file-preview img {
        cursor: zoom-in;
    }

    .svc-image-preview-button {
        position: relative;
        display: block;
        width: 100%;
        padding: 0;
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 8px;
        background: #fff;
        cursor: zoom-in;
    }

    .svc-image-preview-button img {
        display: block;
        width: 100%;
        max-height: 210px;
        object-fit: contain;
        background: #f3f6f4;
    }

    .svc-image-preview-hint {
        position: absolute;
        right: .38rem;
        bottom: .38rem;
        display: inline-flex;
        gap: .22rem;
        align-items: center;
        padding: .22rem .35rem;
        border-radius: 6px;
        background: rgba(23, 37, 28, .82);
        color: #fff;
        font-size: .55rem;
        font-weight: 760;
        pointer-events: none;
    }

    .svc-image-viewer {
        position: fixed;
        z-index: 2600;
        inset: 0;
        width: 100%;
        max-width: none;
        height: 100%;
        max-height: none;
        margin: 0;
        padding: 0;
        border: 0;
        background: rgba(8, 18, 12, .96);
    }

    .svc-image-viewer:not([open]) {
        display: none;
    }

    .svc-image-viewer[open] {
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
    }

    .svc-image-viewer::backdrop {
        background: rgba(8, 18, 12, .96);
    }

    .svc-image-viewer-toolbar {
        display: flex;
        min-height: 54px;
        gap: .5rem;
        align-items: center;
        justify-content: space-between;
        padding:
            max(.5rem, env(safe-area-inset-top))
            max(.65rem, env(safe-area-inset-right))
            .5rem
            max(.65rem, env(safe-area-inset-left));
        border-bottom: 1px solid rgba(255, 255, 255, .12);
        color: #fff;
    }

    .svc-image-viewer-title {
        min-width: 0;
        overflow: hidden;
        font-size: .71rem;
        font-weight: 760;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-image-viewer-close {
        display: grid;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        place-items: center;
        border: 1px solid rgba(255, 255, 255, .18);
        border-radius: 8px;
        background: rgba(255, 255, 255, .08);
        color: #fff;
        cursor: pointer;
    }

    .svc-image-viewer-stage {
        display: grid;
        min-height: 0;
        place-items: center;
        overflow: auto;
        padding: .7rem;
    }

    .svc-image-viewer-stage img {
        display: block;
        max-width: 100%;
        max-height: calc(100dvh - 80px);
        object-fit: contain;
    }

    @media (max-width: 720px) {
        .svc-top .svc-steps {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            overflow: visible;
        }

        .svc-top .svc-step {
            min-width: 0;
            grid-template-columns: 1fr;
            justify-items: center;
            gap: .12rem;
            padding: .24rem .15rem;
            text-align: center;
        }

        .svc-top .svc-step-copy strong {
            font-size: .55rem;
        }

        .svc-context-strip {
            grid-template-columns: 1fr;
        }

        .svc-context-main {
            grid-template-columns:
                repeat(3, minmax(0, 1fr));
        }

        .svc-context-item {
            grid-template-columns: 1fr;
            justify-items: center;
            padding: .14rem .22rem;
            text-align: center;
        }

        .svc-context-item > i {
            width: 24px;
            height: 24px;
        }

        .svc-order-details {
            justify-self: stretch;
        }

        .svc-order-details > summary {
            width: 100%;
            justify-content: center;
        }

        .svc-order-details-body {
            position: static;
            width: 100%;
            margin-top: .35rem;
            box-shadow: none;
        }

        .svc-finance-strip {
            grid-template-columns: 1fr;
        }

        .svc-finance-divider {
            width: 100%;
            height: 1px;
        }
    }

    @media (max-width: 430px) {
        .svc-header-icon {
            display: none;
        }

        .svc-top .svc-step-icon {
            width: 20px;
            height: 20px;
        }

        .svc-context-main {
            grid-template-columns: 1fr 1fr;
        }

        .svc-context-item:nth-child(3) {
            grid-column: 1 / -1;
            border-top: 1px solid var(--svc-border);
            border-left: 0;
            padding-top: .3rem;
        }

        .svc-details-list {
            grid-template-columns: 1fr;
        }

        .svc-details-list > div:nth-child(odd) {
            border-right: 0;
        }
    }


    /* =========================================================
       AJUSTES FINAIS — clareza, estado e responsividade
       ========================================================= */

    .svc-top .svc-header-icon {
        background: var(--status-soft);
        color: var(--status-tone);
    }

    /* Stepper */
    .svc-top .svc-workflow {
        padding: .34rem .5rem .4rem;
        overflow: visible;
        border-radius: 10px;
        background: #fff;
    }

    .svc-top .svc-steps {
        position: relative;
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0;
        overflow: visible;
    }

    .svc-top .svc-step {
        --step-tone: var(--svc-slate);
        --step-soft: #fff;
        --step-border: var(--svc-border);

        position: relative;
        z-index: 1;
        display: grid;
        min-width: 0;
        min-height: 42px;
        grid-template-columns: 1fr;
        gap: .16rem;
        align-content: start;
        justify-items: center;
        padding: 0 .18rem;
        overflow: visible;
        border: 0;
        border-radius: 0;
        background: transparent;
        color: var(--step-tone);
        box-shadow: none;
        text-align: center;
    }

    .svc-top .svc-step::after {
        position: absolute;
        z-index: -1;
        top: 14px;
        left: calc(50% + 17px);
        width: calc(100% - 34px);
        height: 2px;
        background: var(--svc-border);
        content: "";
    }

    .svc-top .svc-step:last-child::after {
        display: none;
    }

    .svc-top .svc-step.done {
        --step-tone: var(--svc-green);
        --step-soft: var(--svc-green-soft);
        --step-border: var(--svc-green-border);
    }

    .svc-top .svc-step.done::after {
        background: var(--svc-green);
    }

    .svc-top .svc-step:nth-child(1).current {
        --step-tone: var(--svc-blue);
        --step-soft: var(--svc-blue-soft);
        --step-border: var(--svc-blue-border);
    }

    .svc-top .svc-step:nth-child(2).current {
        --step-tone: var(--svc-cyan);
        --step-soft: var(--svc-cyan-soft);
        --step-border: var(--svc-cyan-border);
    }

    .svc-top .svc-step:nth-child(3).current {
        --step-tone: var(--svc-amber);
        --step-soft: var(--svc-amber-soft);
        --step-border: var(--svc-amber-border);
    }

    .svc-top .svc-step:nth-child(4).current {
        --step-tone: var(--svc-green);
        --step-soft: var(--svc-green-soft);
        --step-border: var(--svc-green-border);
    }

    .svc-top .svc-step.rejected.current {
        --step-tone: var(--svc-red);
        --step-soft: var(--svc-red-soft);
        --step-border: var(--svc-red-border);
    }

    .svc-top .svc-step-icon {
        display: grid;
        width: 29px;
        height: 29px;
        place-items: center;
        border: 1px solid var(--step-border);
        border-radius: 50%;
        background: var(--step-soft);
        color: var(--step-tone);
        font-size: .72rem;
        box-shadow: 0 0 0 4px #fff;
    }

    .svc-top .svc-step.current .svc-step-icon {
        box-shadow:
            0 0 0 4px #fff,
            0 0 0 5px var(--step-border);
    }

    .svc-top .svc-step-copy strong {
        display: block;
        max-width: 100%;
        overflow: hidden;
        color: var(--step-tone);
        font-size: .58rem;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Context */
    .svc-quick-context {
        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .45rem;
        align-items: center;
        padding: .42rem .48rem;
        border: 1px solid var(--svc-border);
        border-radius: 10px;
        background: #fff;
        box-shadow: var(--svc-shadow);
    }

    .svc-quick-facts {
        display: grid;
        min-width: 0;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 8px;
        background: #fff;
    }

    .svc-quick-fact {
        --fact-tone: var(--svc-blue);
        --fact-soft: var(--svc-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: .38rem;
        align-items: center;
        min-height: 48px;
        padding: .35rem .46rem;
        background: #fff;
    }

    .svc-quick-fact + .svc-quick-fact {
        border-left: 1px solid var(--svc-border);
    }

    .svc-quick-fact.provider {
        --fact-tone: var(--svc-violet);
        --fact-soft: var(--svc-violet-soft);
    }

    .svc-quick-fact.evidence {
        --fact-tone: var(--svc-cyan);
        --fact-soft: var(--svc-cyan-soft);
    }

    .svc-quick-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 7px;
        background: var(--fact-soft);
        color: var(--fact-tone);
        font-size: .8rem;
    }

    .svc-quick-copy {
        min-width: 0;
    }

    .svc-quick-copy small,
    .svc-quick-copy strong {
        display: block;
        min-width: 0;
    }

    .svc-quick-copy small {
        color: var(--svc-muted);
        font-size: .5rem;
        font-weight: 760;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .svc-quick-copy strong {
        margin-top: .03rem;
        overflow: hidden;
        color: var(--svc-text);
        font-size: .63rem;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Details */
    .svc-order-details {
        position: relative;
        min-width: 0;
        align-self: stretch;
    }

    .svc-order-details > summary {
        display: flex;
        min-width: 150px;
        height: 100%;
        min-height: 48px;
        gap: .5rem;
        align-items: center;
        justify-content: space-between;
        padding: .4rem .52rem;
        border: 1px solid var(--svc-border);
        border-radius: 8px;
        background: #fff;
        color: var(--svc-text-2);
        cursor: pointer;
        font-size: .61rem;
        font-weight: 780;
        list-style: none;
        white-space: nowrap;
    }

    .svc-order-details > summary > span {
        display: inline-flex;
        gap: .3rem;
        align-items: center;
    }

    .svc-order-details > summary > span i {
        color: var(--svc-blue);
    }

    .svc-order-details > summary::-webkit-details-marker {
        display: none;
    }

    .svc-details-caret {
        flex: 0 0 auto;
        color: var(--svc-muted);
        transition: transform .15s ease;
    }

    .svc-order-details[open] .svc-details-caret {
        transform: rotate(180deg);
    }

    .svc-order-details-body {
        position: absolute;
        z-index: 55;
        top: calc(100% + .4rem);
        right: 0;
        width: min(92vw, 540px);
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 18px 44px rgba(19, 50, 30, .16);
    }

    .svc-details-list {
        display: grid;
        grid-template-columns: 1fr;
        margin: 0;
    }

    .svc-details-list > div {
        display: grid;
        min-width: 0;
        grid-template-columns: 112px minmax(0, 1fr);
        gap: .65rem;
        align-items: start;
        padding: .5rem .62rem;
        border-bottom: 1px solid var(--svc-border);
    }

    .svc-details-list > div:last-child {
        border-bottom: 0;
    }

    .svc-details-list > div:nth-child(odd) {
        border-right: 0;
    }

    .svc-details-list dt,
    .svc-details-list dd {
        margin: 0;
    }

    .svc-details-list dt {
        color: var(--svc-muted);
        font-size: .55rem;
        font-weight: 780;
        letter-spacing: .02em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .svc-details-list dd {
        min-width: 0;
        color: var(--svc-text);
        font-size: .65rem;
        font-weight: 740;
        line-height: 1.4;
        overflow-wrap: break-word;
        word-break: normal;
    }

    /* Finance */
    .svc-finance-strip {
        padding: 0;
        overflow: hidden;
        border-radius: 10px;
        background: #fff;
    }

    .svc-finance-item {
        min-height: 56px;
        padding: .48rem .58rem;
        border-left: 3px solid transparent;
    }

    .svc-finance-item.receivable {
        border-left-color: var(--svc-green);
    }

    .svc-finance-item.payable {
        border-left-color: var(--svc-blue);
    }

    .svc-finance-item.receivable > span i {
        color: var(--svc-green);
    }

    .svc-finance-item.payable > span i {
        color: var(--svc-blue);
    }

    /* Files */
    .evidence-name {
        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr) auto;
        gap: .45rem;
        align-items: center;
    }

    .evidence-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: var(--svc-slate-soft);
        color: var(--svc-slate);
    }

    .evidence-icon.image {
        background: var(--svc-violet-soft);
        color: var(--svc-violet);
    }

    .evidence-icon.pdf {
        background: var(--svc-red-soft);
        color: var(--svc-red);
    }

    .evidence-icon.signature {
        background: var(--svc-blue-soft);
        color: var(--svc-blue);
    }

    .evidence-copy {
        min-width: 0;
    }

    .evidence-copy strong,
    .evidence-copy small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .evidence-copy strong {
        color: var(--svc-text);
        font-size: .66rem;
        font-weight: 800;
    }

    .evidence-copy small {
        margin-top: .04rem;
        color: var(--svc-muted);
        font-size: .55rem;
        font-weight: 650;
    }

    .svc-evidence-reference {
        color: var(--svc-text-2);
        font-size: .64rem;
        font-weight: 690;
        overflow-wrap: break-word;
    }

    .svc-evidence-thumb {
        position: relative;
        display: block;
        width: 58px;
        height: 40px;
        padding: 0;
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 7px;
        background: var(--svc-soft);
        cursor: zoom-in;
    }

    .svc-evidence-thumb img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .svc-evidence-thumb > span {
        position: absolute;
        right: 3px;
        bottom: 3px;
        display: grid;
        width: 18px;
        height: 18px;
        place-items: center;
        border-radius: 5px;
        background: rgba(23, 37, 28, .82);
        color: #fff;
        font-size: .52rem;
    }

    .svc-table-link {
        cursor: pointer;
    }

    /* Viewer */
    .svc-file-viewer {
        position: fixed;
        z-index: 2700;
        inset: 0;
        width: 100%;
        max-width: none;
        height: 100%;
        max-height: none;
        margin: 0;
        padding: 0;
        border: 0;
        background: rgba(8, 18, 12, .97);
    }

    .svc-file-viewer:not([open]) {
        display: none;
    }

    .svc-file-viewer[open] {
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
    }

    .svc-file-viewer::backdrop {
        background: rgba(8, 18, 12, .97);
    }

    .svc-file-viewer-toolbar {
        display: flex;
        min-height: 54px;
        gap: .6rem;
        align-items: center;
        justify-content: space-between;
        padding:
            max(.48rem, env(safe-area-inset-top))
            max(.65rem, env(safe-area-inset-right))
            .48rem
            max(.65rem, env(safe-area-inset-left));
        border-bottom: 1px solid rgba(255, 255, 255, .12);
        color: #fff;
    }

    .svc-file-viewer-title {
        min-width: 0;
        overflow: hidden;
        font-size: .7rem;
        font-weight: 780;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-file-viewer-close {
        display: grid;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        place-items: center;
        border: 1px solid rgba(255, 255, 255, .16);
        border-radius: 8px;
        background: rgba(255, 255, 255, .08);
        color: #fff;
        cursor: pointer;
        font-size: 1rem;
    }

    .svc-file-viewer-stage {
        display: grid;
        min-height: 0;
        place-items: center;
        overflow: hidden;
        padding: .7rem;
    }

    .svc-file-viewer-image {
        display: block;
        max-width: 100%;
        max-height: calc(100dvh - 80px);
        object-fit: contain;
    }

    .svc-file-viewer-frame {
        width: min(100%, 1080px);
        height: 100%;
        min-height: 0;
        border: 0;
        border-radius: 8px;
        background: #fff;
    }

    .svc-file-viewer-fallback {
        display: grid;
        width: min(100%, 360px);
        gap: .55rem;
        justify-items: center;
        padding: 1rem;
        border: 1px solid rgba(255, 255, 255, .14);
        border-radius: 10px;
        color: #fff;
        text-align: center;
    }

    .svc-file-viewer-fallback[hidden],
    .svc-file-viewer-image[hidden],
    .svc-file-viewer-frame[hidden] {
        display: none !important;
    }

    .svc-file-viewer-fallback-icon {
        display: grid;
        width: 48px;
        height: 48px;
        place-items: center;
        border-radius: 10px;
        background: rgba(255, 255, 255, .08);
        font-size: 1.2rem;
    }

    @media (max-width: 880px) {
        .svc-quick-context {
            grid-template-columns: 1fr;
        }

        .svc-order-details {
            width: 100%;
        }

        .svc-order-details > summary {
            width: 100%;
            min-height: 38px;
            justify-content: center;
        }

        .svc-order-details-body {
            right: auto;
            left: 0;
            width: min(100%, 540px);
        }
    }

    @media (max-width: 720px) {
        .svc-top .svc-step {
            min-height: 40px;
            padding-inline: .08rem;
        }

        .svc-top .svc-step::after {
            left: calc(50% + 15px);
            width: calc(100% - 30px);
        }

        .svc-top .svc-step-icon {
            width: 27px;
            height: 27px;
        }

        .svc-quick-facts {
            display: flex;
            min-width: 0;
            overflow-x: auto;
            scroll-snap-type: x proximity;
            scrollbar-width: none;
        }

        .svc-quick-facts::-webkit-scrollbar {
            display: none;
        }

        .svc-quick-fact {
            flex: 0 0 min(72vw, 200px);
            scroll-snap-align: start;
        }

        .svc-quick-fact + .svc-quick-fact {
            border-left: 1px solid var(--svc-border);
        }

        .svc-order-details-body {
            position: static;
            width: 100%;
            margin-top: .36rem;
            box-shadow: none;
        }

        .svc-finance-strip {
            grid-template-columns: 1fr;
            gap: 0;
        }

        .svc-finance-divider {
            width: auto;
            height: 1px;
        }

        .svc-evidence-table tr {
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .svc-evidence-table td[data-label="Referência"] {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 470px) {
        .svc-top .svc-step-copy strong {
            font-size: .52rem;
        }

        .svc-details-list > div {
            grid-template-columns: 92px minmax(0, 1fr);
            gap: .45rem;
        }

        .evidence-name {
            grid-template-columns: 32px minmax(0, 1fr);
        }

        .svc-evidence-thumb {
            grid-column: 1 / -1;
            width: 100%;
            height: 92px;
        }
    }


    /* =========================================================
       AJUSTES — detalhes no fluxo + evidência vinculada
       ========================================================= */

    /*
     * Detalhes passam a fazer parte do fluxo normal.
     * Ao abrir, empurram o restante da página para baixo;
     * nada fica sobreposto.
     */
    .svc-quick-context {
        display: grid;
        grid-template-columns: 1fr;
        gap: .42rem;
        align-items: stretch;
    }

    .svc-order-details {
        position: static;
        width: 100%;
        min-width: 0;
    }

    .svc-order-details > summary {
        width: 100%;
        height: auto;
        min-height: 38px;
        justify-content: space-between;
        padding: .36rem .5rem;
        background: var(--svc-soft);
    }

    .svc-order-details-body {
        position: static;
        z-index: auto;
        top: auto;
        right: auto;
        left: auto;
        width: 100%;
        margin-top: .38rem;
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 9px;
        background: #fff;
        box-shadow: none;
    }

    .svc-details-list {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: 0;
        margin: 0;
    }

    .svc-details-list > .svc-detail-item {
        --detail-tone: var(--svc-blue);
        --detail-soft: var(--svc-blue-soft);

        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .48rem;
        align-items: center;
        min-height: 58px;
        padding: .46rem .55rem;
        border-bottom: 1px solid var(--svc-border);
        background: #fff;
    }

    .svc-details-list > .svc-detail-item:nth-child(odd) {
        border-right: 1px solid var(--svc-border);
    }

    .svc-details-list > .svc-detail-item.order {
        --detail-tone: var(--svc-blue);
        --detail-soft: var(--svc-blue-soft);
    }

    .svc-details-list > .svc-detail-item.service {
        --detail-tone: var(--svc-cyan);
        --detail-soft: var(--svc-cyan-soft);
    }

    .svc-details-list > .svc-detail-item.beneficiary {
        --detail-tone: var(--svc-green);
        --detail-soft: var(--svc-green-soft);
    }

    .svc-details-list > .svc-detail-item.provider {
        --detail-tone: var(--svc-violet);
        --detail-soft: var(--svc-violet-soft);
    }

    .svc-details-list > .svc-detail-item.schedule {
        --detail-tone: var(--svc-amber);
        --detail-soft: var(--svc-amber-soft);
    }

    .svc-details-list > .svc-detail-item.location {
        --detail-tone: var(--svc-red);
        --detail-soft: var(--svc-red-soft);
    }

    .svc-detail-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--detail-soft);
        color: var(--detail-tone);
        font-size: .86rem;
    }

    .svc-detail-copy {
        display: block;
        min-width: 0;
    }

    .svc-detail-copy dt,
    .svc-detail-copy dd {
        margin: 0;
    }

    .svc-detail-copy dt {
        color: var(--svc-muted);
        font-size: .52rem;
        font-weight: 780;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .svc-detail-copy dd {
        margin-top: .06rem;
        min-width: 0;
        color: var(--svc-text);
        font-size: .65rem;
        font-weight: 760;
        line-height: 1.38;
        overflow-wrap: break-word;
        word-break: normal;
    }

    /*
     * Arquivos enviados:
     * arquivo à esquerda, vínculo/valor no centro, preview à direita.
     */
    .svc-files-panel .svc-evidence-table {
        min-width: 760px;
    }

    .svc-files-panel .svc-evidence-table th:nth-child(1) {
        width: 38%;
    }

    .svc-files-panel .svc-evidence-table th:nth-child(2) {
        width: 34%;
    }

    .svc-files-panel .svc-evidence-table th:nth-child(3) {
        width: 28%;
        text-align: right;
    }

    .svc-files-panel .svc-evidence-table td:nth-child(3) {
        text-align: right;
    }

    .svc-evidence-link {
        display: grid;
        min-width: 0;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: .4rem;
        align-items: center;
    }

    .svc-evidence-link-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 7px;
        background: var(--svc-cyan-soft);
        color: var(--svc-cyan);
        font-size: .75rem;
    }

    .svc-evidence-link-copy {
        min-width: 0;
    }

    .svc-evidence-link-copy strong,
    .svc-evidence-link-copy small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-evidence-link-copy strong {
        color: var(--svc-text);
        font-size: .64rem;
        font-weight: 800;
    }

    .svc-evidence-link-copy small {
        margin-top: .05rem;
        color: var(--svc-blue);
        font-size: .6rem;
        font-weight: 780;
        font-variant-numeric: tabular-nums;
    }

    .svc-evidence-preview-cell {
        display: inline-flex;
        gap: .38rem;
        align-items: center;
        justify-content: flex-end;
    }

    .svc-evidence-thumb {
        width: 76px;
        height: 52px;
        flex: 0 0 auto;
    }

    .svc-file-preview-button {
        --preview-tone: var(--svc-slate);
        --preview-soft: var(--svc-slate-soft);

        display: grid;
        width: 52px;
        height: 52px;
        flex: 0 0 auto;
        place-items: center;
        border: 1px solid var(--svc-border);
        border-radius: 8px;
        background: var(--preview-soft);
        color: var(--preview-tone);
        cursor: pointer;
        font-size: 1.05rem;
    }

    .svc-file-preview-button.pdf {
        --preview-tone: var(--svc-red);
        --preview-soft: var(--svc-red-soft);
    }

    .svc-file-preview-button.signature {
        --preview-tone: var(--svc-blue);
        --preview-soft: var(--svc-blue-soft);
    }

    @media (max-width: 820px) {
        .svc-details-list {
            grid-template-columns: 1fr;
        }

        .svc-details-list > .svc-detail-item:nth-child(odd) {
            border-right: 0;
        }

        .svc-files-panel .svc-evidence-table {
            min-width: 0;
        }

        .svc-files-panel .svc-evidence-table tr {
            display: grid;
            grid-template-columns:
                minmax(0, 1fr)
                auto;
            gap: .42rem;
        }

        .svc-files-panel .svc-evidence-table td[data-label="Vinculado a"] {
            grid-column: 1;
        }

        .svc-files-panel .svc-evidence-table td[data-label="Prévia"] {
            grid-column: 2;
            grid-row: 1 / span 2;
            align-self: center;
        }

        .svc-evidence-preview-cell {
            display: grid;
            justify-items: end;
        }

        .svc-evidence-thumb {
            width: 82px;
            height: 58px;
        }
    }

    @media (max-width: 520px) {
        .svc-detail-item {
            min-height: 54px;
        }

        .svc-files-panel .svc-evidence-table tr {
            grid-template-columns:
                minmax(0, 1fr)
                72px;
        }

        .svc-files-panel .svc-evidence-table td[data-label="Prévia"] {
            width: 72px;
        }

        .svc-evidence-preview-cell .svc-table-link {
            width: 100%;
            min-height: 32px;
            padding-inline: .3rem;
            font-size: 0;
        }

        .svc-evidence-preview-cell .svc-table-link i {
            font-size: .75rem;
        }

        .svc-evidence-thumb,
        .svc-file-preview-button {
            width: 72px;
        }

        .svc-evidence-thumb {
            height: 58px;
        }
    }


    /* =========================================================
       CORREÇÃO DO STEPPER
       ========================================================= */

    /*
     * Etapa concluída mantém o ícone que representa a própria etapa.
     * A conclusão é indicada pela cor verde, não por um visto genérico.
     */
    .svc-top .svc-step.done .svc-step-icon {
        background: var(--svc-green-soft);
        border-color: var(--svc-green-border);
        color: var(--svc-green);
    }

    /*
     * "Concluído" recebe o check somente quando o status da execução
     * realmente é validated.
     */
    .svc-top .svc-step.validated-step .svc-step-icon {
        background: var(--svc-green);
        border-color: var(--svc-green);
        color: #fff;
        box-shadow:
            0 0 0 4px #fff,
            0 0 0 5px var(--svc-green-border);
    }

    .svc-top .svc-step.validated-step .svc-step-copy strong {
        color: var(--svc-green);
    }

    /*
     * Em Conferência, a última etapa continua visualmente futura/neutra.
     */
    .svc-top .svc-step:not(.done):not(.current):not(.validated-step) {
        --step-tone: var(--svc-slate);
        --step-soft: #fff;
        --step-border: var(--svc-border);
    }

    .svc-top .svc-step:not(.done):not(.current):not(.validated-step)
    .svc-step-icon {
        background: #fff;
        border-color: var(--svc-border);
        color: var(--svc-muted);
    }

    .svc-top .svc-step:not(.done):not(.current):not(.validated-step)
    .svc-step-copy strong {
        color: var(--svc-muted);
    }

    /*
     * Imagem já é a própria ação de visualização.
     * Sem botão redundante abaixo dela.
     */
    .svc-evidence-preview-cell:has(.svc-evidence-thumb) {
        align-items: center;
    }


    /* =========================================================
       EVIDÊNCIA — ARQUIVOS / CÂMERA
       ========================================================= */

    .svc-evidence-picker {
        display: grid;
        min-width: 0;
        gap: .34rem;
    }

    /*
     * O input que realmente será enviado permanece no formulário,
     * mas a interação visual é feita pelos dois botões explícitos.
     */
    .svc-evidence-native-input,
    .svc-evidence-camera-input {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
        opacity: 0 !important;
    }

    .svc-evidence-picker-actions {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: .38rem;
    }

    .svc-evidence-pick-btn {
        --picker-tone: var(--svc-blue);
        --picker-soft: var(--svc-blue-soft);
        --picker-border: var(--svc-blue-border);

        display: inline-flex;
        min-width: 0;
        min-height: 39px;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .52rem;
        border: 1px solid var(--picker-border);
        border-radius: 8px;
        background: var(--picker-soft);
        color: var(--picker-tone);
        cursor: pointer;
        font: inherit;
        font-size: .65rem;
        font-weight: 790;
    }

    .svc-evidence-pick-btn.camera {
        --picker-tone: var(--svc-green);
        --picker-soft: var(--svc-green-soft);
        --picker-border: var(--svc-green-border);
    }

    .svc-evidence-pick-btn i {
        flex: 0 0 auto;
        font-size: .84rem;
    }

    .svc-evidence-pick-btn:focus-visible {
        outline: 2px solid var(--picker-tone);
        outline-offset: 2px;
    }

    .svc-evidence-selection {
        display: inline-flex;
        min-width: 0;
        gap: .24rem;
        align-items: center;
        color: var(--svc-muted);
        font-size: .56rem;
        font-weight: 650;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .svc-evidence-selection.is-selected,
    .svc-evidence-selection.is-saved {
        color: var(--svc-green);
        font-weight: 740;
    }

    .svc-evidence-field.has-evidence-error {
        border-color: var(--svc-red);
        background: var(--svc-red-soft);
    }

    /* =========================================================
       FEEDBACK DE TRANSIÇÃO / RELOAD
       ========================================================= */

    .svc-reload-overlay {
        position: fixed;
        z-index: 3100;
        inset: 0;
        display: grid;
        place-items: center;
        padding: 1rem;
        background: rgba(14, 25, 18, .72);
    }

    .svc-reload-overlay[hidden] {
        display: none !important;
    }

    .svc-reload-card {
        display: grid;
        width: min(100%, 310px);
        gap: .42rem;
        justify-items: center;
        padding: 1rem;
        border: 1px solid var(--svc-border);
        border-radius: 12px;
        background: #fff;
        color: var(--svc-text);
        box-shadow: 0 18px 50px rgba(12, 28, 18, .2);
        text-align: center;
    }

    .svc-reload-spinner {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 10px;
        background: var(--svc-green-soft);
        color: var(--svc-green);
        font-size: 1.2rem;
    }

    .svc-reload-spinner i {
        animation: svc-spin .82s linear infinite;
    }

    .svc-reload-card strong {
        font-size: .78rem;
        font-weight: 840;
    }

    .svc-reload-card small {
        color: var(--svc-muted);
        font-size: .61rem;
        line-height: 1.4;
    }

    @keyframes svc-spin {
        to {
            transform: rotate(360deg);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .svc-reload-spinner i {
            animation-duration: 1.8s;
        }
    }

    @media (max-width: 420px) {
        .svc-evidence-picker-actions {
            grid-template-columns: 1fr;
        }
    }


    .svc-evidence-file-error {
        display: inline-flex;
        gap: .25rem;
        align-items: flex-start;
        color: var(--svc-red);
        font-size: .58rem;
        font-weight: 740;
        line-height: 1.4;
    }

    .svc-evidence-file-error i {
        flex: 0 0 auto;
        margin-top: .04rem;
        font-size: .72rem;
    }

</style>

<main
    class="service-execution"
    data-draft-url="{{ $draftUrl }}"
    data-local-key="{{ $serviceLocalKey }}"
    data-meter-start="{{ $meterStartField }}"
    data-meter-end="{{ $meterEndField }}"
    data-execution-unit="{{ $executionUnit }}"
    data-execution-status="{{ $executionStatus }}"
>
    @if($errors->any())
        <div class="svc-alert danger" role="alert" tabindex="-1" id="form-errors">
            <span class="svc-alert-icon" aria-hidden="true"><i class="ph-fill ph-warning-circle"></i></span>
            <div><strong>Não foi possível concluir a ordem.</strong><ul style="margin:.35rem 0 0;padding-left:1.15rem">@foreach($errors->all() as $message)<li>{{$message}}</li>@endforeach</ul><small>Os dados preenchidos permanecem na tela. Corrija somente os itens indicados e envie novamente.</small></div>
        </div>
    @endif
    {{-- =========================================================
         CABEÇALHO + FLUXO
         ========================================================= --}}
    <section class="svc-panel svc-top">
        <header
            class="
                svc-header
                {{ $executionStatusMeta['class'] }}
            "
        >
            <div class="svc-header-main">
                <a
                    class="svc-back"
                    href="{{ route(
                        'provider.orders',
                        ['tenant' => $tenantSlug]
                    ) }}"
                    aria-label="Voltar às ordens"
                    title="Voltar às ordens"
                >
                    <i class="ph-fill ph-arrow-left"></i>
                </a>

                <span
                    class="svc-header-icon"
                    aria-hidden="true"
                >
                    <i
                        class="
                            ph-fill
                            {{ $executionStatusMeta['icon'] }}
                        "
                    ></i>
                </span>

                <div class="svc-header-copy">
                    <h1>
                        {{ $order->number }}
                        ·
                        {{ $order->service->name }}
                    </h1>

                    <div class="svc-meta">
                        <span>
                            <i class="ph-fill ph-user-circle" aria-hidden="true"></i>
                            <span class="svc-meta-text">
                                {{ $beneficiaryName }}
                            </span>
                        </span>

                        <span>
                            <i class="ph-fill ph-map-pin" aria-hidden="true"></i>
                            <span class="svc-meta-text">
                                {{ $locationLabel }}
                            </span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="svc-header-side">
                <span class="svc-status">
                    <i
                        class="
                            ph-fill
                            {{ $executionStatusMeta['icon'] }}
                        "
                    ></i>

                    {{ $executionStatusLabel }}
                </span>
            </div>
        </header>

        <div class="svc-workflow">
            <nav
                class="svc-steps"
                aria-label="Etapas da execução"
            >
                @foreach($workflowSteps as $index => $step)
                    @php
                        $stepDone =
                            $index < $stepIndex;

                        $stepCurrent =
                            $index === $stepIndex;

                        $stepRejected =
                            $executionStatus === 'rejected'
                            && $stepCurrent;
                    @endphp

                    <div
                        class="
                            svc-step
                            {{ $stepDone ? 'done' : '' }}
                            {{ $stepCurrent ? 'current' : '' }}
                            {{ $stepRejected ? 'rejected' : '' }}
                            {{
                                $executionStatus === 'validated'
                                && $index === 3
                                    ? 'validated-step'
                                    : ''
                            }}
                        "
                        data-step="{{ $index + 1 }}"
                        @if($stepCurrent)
                            aria-current="step"
                        @endif
                    >
                        <span
                            class="svc-step-icon"
                            aria-hidden="true"
                        >
                            <i
                                class="
                                    ph-fill
                                    {{
                                        $executionStatus === 'validated'
                                        && $index === 3
                                            ? 'ph-check-circle'
                                            : $step['icon']
                                    }}
                                "
                            ></i>
                        </span>

                        <span class="svc-step-copy">
                            <strong>
                                {{ $executionStatus === 'rejected' && $index === 0
                                    ? 'Corrigir'
                                    : $step['label'] }}
                            </strong>
                        </span>
                    </div>
                @endforeach
            </nav>
        </div>
    </section>

    {{-- =========================================================
         CONTEXTO RÁPIDO DA ORDEM
         ========================================================= --}}
    <section class="svc-quick-context">
        <div class="svc-quick-facts">
            <div class="svc-quick-fact schedule">
                <span class="svc-quick-icon" aria-hidden="true">
                    <i class="ph-fill ph-calendar-check"></i>
                </span>
                <span class="svc-quick-copy">
                    <small>Agendamento</small>
                    <strong>{{ $scheduledLabel }}</strong>
                </span>
            </div>

            <div class="svc-quick-fact provider">
                <span class="svc-quick-icon" aria-hidden="true">
                    <i class="ph-fill ph-user-gear"></i>
                </span>
                <span class="svc-quick-copy">
                    <small>Prestador</small>
                    <strong>{{ $providerName }}</strong>
                </span>
            </div>

            <div class="svc-quick-fact evidence">
                <span class="svc-quick-icon" aria-hidden="true">
                    <i class="ph-fill ph-images-square"></i>
                </span>
                <span class="svc-quick-copy">
                    <small>Evidências</small>
                    <strong>
                        {{ $evidenceTotal }}
                        @if($evidenceConfiguredTotal > 0)
                            de {{ $evidenceConfiguredTotal }}
                        @else
                            enviada(s)
                        @endif
                    </strong>
                </span>
            </div>
        </div>

        <details class="svc-order-details">
            <summary>
                <span>
                    <i class="ph-fill ph-info"></i>
                    Detalhes da ordem
                </span>

                <i
                    class="ph-fill ph-caret-down svc-details-caret"
                    aria-hidden="true"
                ></i>
            </summary>

            <div class="svc-order-details-body">
                <dl class="svc-details-list">
                    <div class="svc-detail-item order">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-receipt"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Ordem</dt>
                            <dd>{{ $order->number }}</dd>
                        </span>
                    </div>

                    <div class="svc-detail-item service">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-wrench"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Serviço</dt>
                            <dd>{{ $order->service->name }}</dd>
                        </span>
                    </div>

                    <div class="svc-detail-item beneficiary">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-user-circle"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Beneficiário</dt>
                            <dd>{{ $beneficiaryName }}</dd>
                        </span>
                    </div>

                    <div class="svc-detail-item provider">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-user-gear"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Prestador</dt>
                            <dd>{{ $providerName }}</dd>
                        </span>
                    </div>

                    <div class="svc-detail-item schedule">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-calendar-check"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Agendamento</dt>
                            <dd>{{ $scheduledLabel }}</dd>
                        </span>
                    </div>

                    <div class="svc-detail-item location">
                        <span class="svc-detail-icon" aria-hidden="true">
                            <i class="ph-fill ph-map-pin"></i>
                        </span>

                        <span class="svc-detail-copy">
                            <dt>Local</dt>
                            <dd>{{ $locationLabel }}</dd>
                        </span>
                    </div>
                </dl>
            </div>
        </details>
    </section>

    <section class="svc-finance-strip">
        <div class="svc-finance-item receivable">
            <span>
                <i class="ph-fill ph-bank"></i>
                Organização recebe
            </span>

            <strong>
                {{ $receivablePreview !== null
                    ? $formatMoney($receivablePreview)
                    : 'A calcular' }}
            </strong>

            <small>
                {{ $formatMethod($customerPricingMethod) }}
                @if($order->serviceVersion?->customer_rate)
                    · {{ $formatMoney($order->serviceVersion->customer_rate) }}
                @endif
            </small>
        </div>

        <div class="svc-finance-divider" aria-hidden="true"></div>

        <div class="svc-finance-item payable">
            <span>
                <i class="ph-fill ph-hand-coins"></i>
                Prestador recebe
            </span>

            <strong>
                {{ $payablePreview !== null
                    ? $formatMoney($payablePreview)
                    : 'A calcular' }}
            </strong>

            <small>
                {{ $formatMethod($providerPricingMethod) }}
                @if($providerConfiguredRate)
                    · {{ $formatMoney($providerConfiguredRate) }}
                @endif
            </small>
        </div>
    </section>

    {{-- =========================================================
         INÍCIO / CORREÇÃO
         ========================================================= --}}
    @if(
        in_array(
            $executionStatus,
            ['draft', 'rejected'],
            true
        )
    )
        <section class="svc-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:
                        {{ $executionStatus === 'rejected'
                            ? 'var(--svc-red)'
                            : 'var(--svc-blue)' }};
                    --panel-soft:
                        {{ $executionStatus === 'rejected'
                            ? 'var(--svc-red-soft)'
                            : 'var(--svc-blue-soft)' }};
                "
            >
                <div class="svc-panel-title">
                    <span
                        class="svc-panel-icon"
                        aria-hidden="true"
                    >
                        <i
                            class="
                                ph-fill
                                {{ $executionStatus === 'rejected'
                                    ? 'ph-arrow-counter-clockwise'
                                    : 'ph-play-circle' }}
                            "
                        ></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>
                            {{ $executionStatus === 'rejected'
                                ? 'Corrigir e reiniciar'
                                : 'Iniciar serviço' }}
                        </h2>

                        <p>
                            Preencha somente os dados solicitados
                            para esta etapa.
                        </p>
                    </div>
                </div>
            </header>

            <div class="svc-form-body">
                @if($execution->review_reason)
                    <div class="svc-alert danger">
                        <span
                            class="svc-alert-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-warning-circle"></i>
                        </span>

                        <div>
                            <strong>
                                Correção solicitada:
                            </strong>

                            {{ $execution->review_reason }}
                        </div>
                    </div>
                @endif

                <form
                    data-async-service-form
                    method="post"
                    enctype="multipart/form-data"
                    action="{{ route(
                        'provider.orders.start',
                        [
                            $tenantSlug,
                            $order,
                        ]
                    ) }}"
                    class="svc-form"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="operation_key"
                        value="{{ \Illuminate\Support\Str::uuid() }}"
                    >

                    @include(
                        'provider._service-fields',
                        [
                            'execution' => $execution,
                            'phase' => 'start',
                            'operator' => $operator ?? false,
                            'tenantSlug' => $tenantSlug,
                        ]
                    )

                    <div class="svc-form-actions">
                        <button
                            type="submit"
                            class="svc-btn primary"
                        >
                            <i class="ph-fill ph-play-circle"></i>

                            {{ $executionStatus === 'rejected'
                                ? 'Salvar correções e reiniciar'
                                : 'Salvar dados e iniciar' }}
                        </button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    {{-- =========================================================
         EXECUÇÃO
         ========================================================= --}}
    @if($executionStatus === 'in_progress')
        <section class="svc-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:var(--svc-cyan);
                    --panel-soft:var(--svc-cyan-soft);
                "
            >
                <div class="svc-panel-title">
                    <span
                        class="svc-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-wrench"></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>Registrar execução</h2>

                        <p>
                            Dados realizados e evidências
                            ficam no mesmo fluxo.
                        </p>
                    </div>
                </div>
            </header>

            <div class="svc-form-body">
                <div class="svc-alert">
                    <span
                        class="svc-alert-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-info"></i>
                    </span>

                    <div>
                        Preencha cada dado e seu comprovante
                        no mesmo bloco. Evidências opcionais
                        podem permanecer vazias.
                    </div>
                </div>

                <form
                    id="execution-form"
                    data-async-service-form
                    method="post"
                    enctype="multipart/form-data"
                    action="{{ route(
                        'provider.orders.submit',
                        [
                            $tenantSlug,
                            $order,
                        ]
                    ) }}"
                    class="svc-form"
                >
                    @csrf

                    <input
                        type="hidden"
                        name="operation_key"
                        value="{{ \Illuminate\Support\Str::uuid() }}"
                    >

                    @include(
                        'provider._service-fields',
                        [
                            'execution' => $execution,
                            'phase' => 'execution',
                            'operator' => $operator ?? false,
                            'tenantSlug' => $tenantSlug,
                        ]
                    )

                    @include(
                        'provider._service-fields',
                        [
                            'execution' => $execution,
                            'phase' => 'finish',
                            'operator' => $operator ?? false,
                            'tenantSlug' => $tenantSlug,
                        ]
                    )

                    @if(
                        data_get(
                            $execution->catalog_snapshot,
                            'execution_config.quantity_mode'
                        ) === 'meter_difference'
                    )
                        <div
                            class="svc-preview"
                            id="meter-preview"
                            aria-live="polite"
                        >
                            <span
                                class="svc-preview-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-gauge"></i>
                            </span>

                            <span class="svc-preview-copy">
                                <span>
                                    Quantidade calculada
                                    pela diferença
                                </span>

                                <small>
                                    Medição final − medição inicial
                                </small>
                            </span>

                            <strong
                                class="svc-preview-result"
                                id="meter-result"
                            >
                                —
                            </strong>
                        </div>
                    @endif

                    <div class="svc-form-actions">
                        <button
                            type="button"
                            class="svc-btn"
                            data-save-draft
                        >
                            <i class="ph-fill ph-floppy-disk"></i>
                            Salvar rascunho
                        </button>

                        <button
                            type="submit"
                            class="svc-btn primary"
                        >
                            <i class="ph-fill ph-paper-plane-tilt"></i>
                            Finalizar e enviar
                        </button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    @if($savedValues->isNotEmpty())
        <section class="svc-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:var(--svc-blue);
                    --panel-soft:var(--svc-blue-soft);
                "
            >
                <div class="svc-panel-title">
                    <span
                        class="svc-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-clock-counter-clockwise"></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>Dados já registrados</h2>
                        <p>Valores salvos anteriormente nesta execução.</p>
                    </div>
                </div>
            </header>

            <table
                class="svc-info-table"
                aria-label="Dados já registrados"
            >
                <tbody>
                    @foreach($savedValues as $key => $value)
                        @php
                            $savedField = $fields->firstWhere(
                                'key',
                                $key
                            );

                            $savedLabel =
                                $savedField['label']
                                ?? \Illuminate\Support\Str::headline(
                                    (string) $key
                                );

                            $savedUnit = data_get(
                                $savedField,
                                'unit'
                            );

                            $savedDisplayValue = is_bool($value)
                                ? ($value ? 'Sim' : 'Não')
                                : (
                                    is_array($value)
                                        ? implode(', ', $value)
                                        : $value
                                );
                        @endphp

                        <tr>
                            <th>{{ $savedLabel }}</th>

                            <td>
                                {{ $savedDisplayValue }}

                                @if($savedUnit)
                                    {{ $savedUnit }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif


    {{-- =========================================================
         EVIDÊNCIAS JÁ ENVIADAS
         ========================================================= --}}
    @if($execution->evidences->isNotEmpty())
        <section class="svc-panel svc-files-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:var(--svc-violet);
                    --panel-soft:var(--svc-violet-soft);
                "
            >
                <div class="svc-panel-title">
                    <span class="svc-panel-icon" aria-hidden="true">
                        <i class="ph-fill ph-files"></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>Arquivos enviados</h2>
                        <p>Evidências vinculadas a esta execução.</p>
                    </div>
                </div>

                <span class="svc-count">
                    <i class="ph-fill ph-paperclip"></i>
                    {{ $evidenceTotal }}
                </span>
            </header>

            <div class="svc-evidence-wrap">
                <table class="svc-evidence-table" aria-label="Arquivos enviados">
                    <thead>
                        <tr>
                            <th>Arquivo</th>
                            <th>Vinculado a</th>
                            <th>Prévia</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($execution->evidences as $evidence)
                            @php
                                $evidenceMime = (string) (
                                    $evidence->document->mime_type
                                    ?? ''
                                );

                                $evidenceUrl = route(
                                    'provider.evidences.download',
                                    [
                                        $tenantSlug,
                                        $evidence,
                                    ]
                                );

                                $evidenceIsImage = str_starts_with(
                                    $evidenceMime,
                                    'image/'
                                );

                                $evidenceIsPdf =
                                    $evidenceMime === 'application/pdf'
                                    || str_ends_with(
                                        strtolower(
                                            (string) $evidence
                                                ->document
                                                ->name
                                        ),
                                        '.pdf'
                                    );

                                $evidenceField = $fields->firstWhere(
                                    'key',
                                    $evidence->field_key
                                );

                                $evidenceFieldType = data_get(
                                    $evidenceField,
                                    'type'
                                );

                                $evidenceFieldLabel =
                                    data_get(
                                        $evidenceField,
                                        'label'
                                    )
                                    ?? (
                                        $evidence->field_key
                                            ? \Illuminate\Support\Str::headline(
                                                (string) $evidence->field_key
                                            )
                                            : 'Arquivo da execução'
                                    );

                                /*
                                 * Campo de dado ao qual esta evidência pertence.
                                 * Primeiro usa evidence_for_field do catálogo.
                                 * Se não existir, tenta a mesma inferência
                                 * inicial/final usada no partial de campos.
                                 */
                                $representedFieldKey = data_get(
                                    $evidenceField,
                                    'evidence_for_field'
                                );

                                if (!$representedFieldKey && $evidenceField) {
                                    $normalizedEvidenceLabel = str(
                                        (string) $evidenceFieldLabel
                                    )
                                        ->ascii()
                                        ->lower()
                                        ->toString();

                                    $evidenceKeyword = collect(
                                        [
                                            'inicial',
                                            'inicio',
                                            'final',
                                            'fim',
                                        ]
                                    )->first(
                                        static fn (string $word): bool =>
                                            str_contains(
                                                $normalizedEvidenceLabel,
                                                $word
                                            )
                                    );

                                    if ($evidenceKeyword) {
                                        $representedField = $fields->first(
                                            static function ($candidate) use ($evidenceKeyword): bool {
                                                $candidateType = data_get(
                                                    $candidate,
                                                    'type'
                                                );

                                                if (
                                                    in_array(
                                                        $candidateType,
                                                        [
                                                            'image',
                                                            'file',
                                                            'signature',
                                                        ],
                                                        true
                                                    )
                                                ) {
                                                    return false;
                                                }

                                                $candidateLabel = str(
                                                    (string) data_get(
                                                        $candidate,
                                                        'label',
                                                        ''
                                                    )
                                                )
                                                    ->ascii()
                                                    ->lower()
                                                    ->toString();

                                                return str_contains(
                                                    $candidateLabel,
                                                    $evidenceKeyword
                                                );
                                            }
                                        );

                                        $representedFieldKey = data_get(
                                            $representedField,
                                            'key'
                                        );
                                    }
                                }

                                $representedField = $representedFieldKey
                                    ? $fields->firstWhere(
                                        'key',
                                        $representedFieldKey
                                    )
                                    : null;

                                $representedLabel = $representedField
                                    ? (
                                        data_get(
                                            $representedField,
                                            'label'
                                        )
                                        ?? \Illuminate\Support\Str::headline(
                                            (string) $representedFieldKey
                                        )
                                    )
                                    : $evidenceFieldLabel;

                                $representedUnit = data_get(
                                    $representedField,
                                    'unit'
                                );

                                $representedValue = $representedFieldKey
                                    ? data_get(
                                        $execution->values,
                                        $representedFieldKey
                                    )
                                    : null;

                                if (is_bool($representedValue)) {
                                    $representedDisplayValue = $representedValue
                                        ? 'Sim'
                                        : 'Não';
                                } elseif (is_array($representedValue)) {
                                    $representedDisplayValue = implode(
                                        ', ',
                                        $representedValue
                                    );
                                } else {
                                    $representedDisplayValue =
                                        $representedValue;
                                }

                                if ($evidenceFieldType === 'signature') {
                                    $evidenceIcon = 'ph-signature';
                                    $evidenceKind = 'signature';
                                    $previewType = $evidenceIsImage
                                        ? 'image'
                                        : 'file';
                                } elseif ($evidenceIsImage) {
                                    $evidenceIcon = 'ph-image-square';
                                    $evidenceKind = 'image';
                                    $previewType = 'image';
                                } elseif ($evidenceIsPdf) {
                                    $evidenceIcon = 'ph-file-pdf';
                                    $evidenceKind = 'pdf';
                                    $previewType = 'pdf';
                                } else {
                                    $evidenceIcon = 'ph-file';
                                    $evidenceKind = 'file';
                                    $previewType = 'file';
                                }
                            @endphp

                            <tr class="svc-evidence-row">
                                <td data-label="Arquivo">
                                    <div class="evidence-name">
                                        <span
                                            class="evidence-icon {{ $evidenceKind }}"
                                            aria-hidden="true"
                                        >
                                            <i class="ph-fill {{ $evidenceIcon }}"></i>
                                        </span>

                                        <span class="evidence-copy">
                                            <strong>
                                                {{ $evidence->document->name }}
                                            </strong>

                                            <small>
                                                {{ $evidenceIsImage
                                                    ? 'Imagem'
                                                    : (
                                                        $evidenceIsPdf
                                                            ? 'Documento PDF'
                                                            : (
                                                                $evidenceFieldType === 'signature'
                                                                    ? 'Assinatura'
                                                                    : 'Arquivo'
                                                            )
                                                    ) }}
                                            </small>
                                        </span>
                                    </div>
                                </td>

                                <td data-label="Vinculado a">
                                    <div class="svc-evidence-link">
                                        <span class="svc-evidence-link-icon">
                                            <i class="ph-fill ph-link-simple"></i>
                                        </span>

                                        <span class="svc-evidence-link-copy">
                                            <strong>
                                                {{ $representedLabel }}
                                            </strong>

                                            @if(
                                                $representedDisplayValue !== null
                                                && $representedDisplayValue !== ''
                                            )
                                                <small>
                                                    {{ $representedDisplayValue }}
                                                    @if($representedUnit)
                                                        {{ $representedUnit }}
                                                    @endif
                                                </small>
                                            @else
                                                <small>
                                                    {{ $representedFieldKey
                                                        ? 'Valor não informado'
                                                        : 'Evidência independente' }}
                                                </small>
                                            @endif
                                        </span>
                                    </div>
                                </td>

                                <td data-label="Prévia">
                                    <div class="svc-evidence-preview-cell">
                                        @if($evidenceIsImage)
                                            <button
                                                type="button"
                                                class="
                                                    svc-evidence-thumb
                                                    svc-preview-trigger
                                                "
                                                data-preview-url="{{ $evidenceUrl }}"
                                                data-preview-type="image"
                                                data-preview-title="{{ $evidence->document->name }}"
                                                aria-label="Ampliar {{ $evidence->document->name }}"
                                            >
                                                <img
                                                    src="{{ $evidenceUrl }}"
                                                    alt=""
                                                    loading="lazy"
                                                >

                                                <span aria-hidden="true">
                                                    <i class="ph-fill ph-arrows-out"></i>
                                                </span>
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                class="
                                                    svc-file-preview-button
                                                    {{ $evidenceKind }}
                                                    svc-preview-trigger
                                                "
                                                data-preview-url="{{ $evidenceUrl }}"
                                                data-preview-type="{{ $previewType }}"
                                                data-preview-title="{{ $evidence->document->name }}"
                                            >
                                                <i
                                                    class="
                                                        ph-fill
                                                        {{ $evidenceIcon }}
                                                    "
                                                ></i>
                                            </button>
                                        @endif

                                        @unless($evidenceIsImage)
                                            <button
                                                type="button"
                                                class="
                                                    svc-table-link
                                                    svc-preview-trigger
                                                "
                                                data-preview-url="{{ $evidenceUrl }}"
                                                data-preview-type="{{ $previewType }}"
                                                data-preview-title="{{ $evidence->document->name }}"
                                            >
                                                <i class="ph-fill ph-eye"></i>
                                                Visualizar
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- =========================================================
         CONFERÊNCIA / CONCLUSÃO
         ========================================================= --}}
    @if(
        in_array(
            $executionStatus,
            ['submitted', 'validated'],
            true
        )
    )
        <section class="svc-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:
                        {{ $executionStatus === 'validated'
                            ? 'var(--svc-green)'
                            : 'var(--svc-amber)' }};
                    --panel-soft:
                        {{ $executionStatus === 'validated'
                            ? 'var(--svc-green-soft)'
                            : 'var(--svc-amber-soft)' }};
                "
            >
                <div class="svc-panel-title">
                    <span
                        class="svc-panel-icon"
                        aria-hidden="true"
                    >
                        <i
                            class="
                                ph-fill
                                {{ $executionStatus === 'validated'
                                    ? 'ph-seal-check'
                                    : 'ph-magnifying-glass' }}
                            "
                        ></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>
                            {{ $executionStatus === 'validated'
                                ? 'Execução concluída'
                                : 'Aguardando conferência' }}
                        </h2>

                        <p>
                            Dados e cálculos registrados
                            para conferência.
                        </p>
                    </div>
                </div>
            </header>

            <div class="svc-review-body">
                <div class="svc-review-layout">
                    <div class="svc-review-table-wrap">
                        <table
                            class="svc-review-table"
                            aria-label="Cálculos da execução"
                        >
                            <tbody>
                                @foreach(
                                    [
                                        'customer' => 'Cobrança',
                                        'provider' => 'Remuneração',
                                    ]
                                    as $direction => $label
                                )
                                    @php
                                        $calculation = data_get(
                                            $execution->derived_values,
                                            "calculation.$direction",
                                            []
                                        );
                                    @endphp

                                    @if(
                                        data_get($calculation, 'mode') ===
                                        'meter_difference'
                                    )
                                        <tr>
                                            <th>
                                                {{ $label }}
                                            </th>

                                            <td>
                                                <span
                                                    class="svc-review-result"
                                                >
                                                    {{ data_get(
                                                        $calculation,
                                                        'meter_end_value'
                                                    ) }}
                                                    −
                                                    {{ data_get(
                                                        $calculation,
                                                        'meter_start_value'
                                                    ) }}
                                                    =
                                                    {{ data_get(
                                                        $calculation,
                                                        'result'
                                                    ) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @elseif(
                                        data_get($calculation, 'mode') ===
                                        'field'
                                    )
                                        <tr>
                                            <th>
                                                {{ $label }}
                                            </th>

                                            <td>
                                                <span
                                                    class="svc-review-result"
                                                >
                                                    {{ data_get(
                                                        $calculation,
                                                        'result'
                                                    ) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach

                                @if(
                                    !data_get(
                                        $execution->derived_values,
                                        'calculation.customer'
                                    )
                                    && !data_get(
                                        $execution->derived_values,
                                        'calculation.provider'
                                    )
                                )
                                    <tr>
                                        <th>Cálculos</th>

                                        <td>
                                            Nenhum cálculo derivado
                                            disponível.
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    @if($canApprove)
                        <div class="svc-review-action">
                            <form
                                data-async-service-form
                                method="post"
                                action="{{ route(
                                    'provider.orders.approve',
                                    [
                                        $tenantSlug,
                                        $order,
                                    ]
                                ) }}"
                            >
                                @csrf

                                <input
                                    type="hidden"
                                    name="operation_key"
                                    value="{{ \Illuminate\Support\Str::uuid() }}"
                                >

                                <button
                                    type="submit"
                                    class="svc-btn primary"
                                >
                                    <i class="ph-fill ph-seal-check"></i>
                                    Conferir e concluir ordem
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <dialog
        class="svc-file-viewer"
        id="svc-file-viewer"
        aria-label="Visualização do arquivo"
    >
        <div class="svc-file-viewer-toolbar">
            <span class="svc-file-viewer-title" id="svc-file-viewer-title">
                Visualização
            </span>

            <button
                type="button"
                class="svc-file-viewer-close"
                data-file-viewer-close
                aria-label="Fechar visualização"
                title="Fechar"
            >
                <i class="ph-fill ph-x-circle"></i>
            </button>
        </div>

        <div class="svc-file-viewer-stage">
            <img
                id="svc-file-viewer-image"
                class="svc-file-viewer-image"
                src=""
                alt="Imagem ampliada"
                hidden
            >

            <iframe
                id="svc-file-viewer-frame"
                class="svc-file-viewer-frame"
                src="about:blank"
                title="Prévia do documento"
                hidden
            ></iframe>

            <div
                id="svc-file-viewer-fallback"
                class="svc-file-viewer-fallback"
                hidden
            >
                <span class="svc-file-viewer-fallback-icon">
                    <i class="ph-fill ph-file"></i>
                </span>

                <strong>Este arquivo não possui prévia incorporada.</strong>

                <a
                    id="svc-file-viewer-open"
                    class="svc-btn"
                    href="#"
                    target="_blank"
                    rel="noopener"
                >
                    <i class="ph-fill ph-arrow-square-out"></i>
                    Abrir arquivo
                </a>
            </div>
        </div>
    </dialog>


    <div
        class="svc-reload-overlay"
        id="svc-reload-overlay"
        hidden
        role="status"
        aria-live="assertive"
        aria-busy="true"
    >
        <div class="svc-reload-card">
            <span class="svc-reload-spinner" aria-hidden="true">
                <i class="ph-fill ph-spinner-gap"></i>
            </span>

            <strong id="svc-reload-title">
                Atualizando ordem…
            </strong>

            <small id="svc-reload-copy">
                Salvando o novo estado e recarregando a tela.
            </small>
        </div>
    </div>

</main>

<script>
    const serviceWorkspace =
        document.querySelector('.service-execution');

    const serviceDraftUrl =
        serviceWorkspace?.dataset.draftUrl || '';

    const serviceLocalKey =
        serviceWorkspace?.dataset.localKey || '';

    const serviceMeterStartField =
        serviceWorkspace?.dataset.meterStart || '';

    const serviceMeterEndField =
        serviceWorkspace?.dataset.meterEnd || '';

    const serviceExecutionUnit =
        serviceWorkspace?.dataset.executionUnit || '';

    const serviceExecutionStatus =
        serviceWorkspace?.dataset.executionStatus || '';

    const serviceReloadOverlay =
        document.getElementById('svc-reload-overlay');

    const serviceReloadTitle =
        document.getElementById('svc-reload-title');

    const serviceReloadCopy =
        document.getElementById('svc-reload-copy');

    const serviceFileViewer =
        document.getElementById('svc-file-viewer');

    const serviceFileViewerImage =
        document.getElementById('svc-file-viewer-image');

    const serviceFileViewerFrame =
        document.getElementById('svc-file-viewer-frame');

    const serviceFileViewerFallback =
        document.getElementById('svc-file-viewer-fallback');

    const serviceFileViewerOpen =
        document.getElementById('svc-file-viewer-open');

    const serviceFileViewerTitle =
        document.getElementById('svc-file-viewer-title');

    let serviceViewerPushedHistory = false;

    function resetServiceFileViewer() {
        if (serviceFileViewerImage) {
            serviceFileViewerImage.hidden = true;
            serviceFileViewerImage.src = '';
        }

        if (serviceFileViewerFrame) {
            serviceFileViewerFrame.hidden = true;
            serviceFileViewerFrame.src = 'about:blank';
        }

        if (serviceFileViewerFallback) {
            serviceFileViewerFallback.hidden = true;
        }

        if (serviceFileViewerOpen) {
            serviceFileViewerOpen.href = '#';
        }
    }

    function openServiceFileViewer(
        source,
        type = 'file',
        title = 'Visualização'
    ) {
        if (!serviceFileViewer || !source) {
            return;
        }

        resetServiceFileViewer();

        if (serviceFileViewerTitle) {
            serviceFileViewerTitle.textContent =
                title || 'Visualização';
        }

        if (type === 'image' && serviceFileViewerImage) {
            serviceFileViewerImage.src = source;
            serviceFileViewerImage.hidden = false;
        } else if (type === 'pdf' && serviceFileViewerFrame) {
            serviceFileViewerFrame.src = source;
            serviceFileViewerFrame.hidden = false;
        } else if (serviceFileViewerFallback) {
            serviceFileViewerFallback.hidden = false;

            if (serviceFileViewerOpen) {
                serviceFileViewerOpen.href = source;
            }
        }

        if (!serviceFileViewer.hasAttribute('open')) {
            if (
                typeof serviceFileViewer.showModal
                === 'function'
            ) {
                serviceFileViewer.showModal();
            } else {
                serviceFileViewer.setAttribute('open', '');
            }
        }

        if (!history.state?.svcFileViewer) {
            history.pushState(
                {
                    ...(history.state || {}),
                    svcFileViewer: true,
                },
                '',
                window.location.href
            );

            serviceViewerPushedHistory = true;
        }
    }

    function closeServiceFileViewerDirect() {
        if (!serviceFileViewer) {
            return;
        }

        if (
            typeof serviceFileViewer.close === 'function'
            && serviceFileViewer.open
        ) {
            serviceFileViewer.close();
        } else {
            serviceFileViewer.removeAttribute('open');
        }

        resetServiceFileViewer();
        serviceViewerPushedHistory = false;
    }

    function requestCloseServiceFileViewer() {
        if (
            serviceViewerPushedHistory
            && history.state?.svcFileViewer
        ) {
            history.back();
            return;
        }

        closeServiceFileViewerDirect();
    }

    function bindServicePreviewTriggers(root = document) {
        root
            .querySelectorAll(
                '.svc-preview-trigger:not([data-preview-bound])'
            )
            .forEach(trigger => {
                trigger.dataset.previewBound = '1';

                trigger.addEventListener(
                    'click',
                    event => {
                        event.preventDefault();

                        openServiceFileViewer(
                            trigger.dataset.previewUrl,
                            trigger.dataset.previewType || 'file',
                            trigger.dataset.previewTitle || 'Visualização'
                        );
                    }
                );
            });
    }

    function bindServiceImagePreview(
        image,
        title = 'Prévia da imagem',
        sourceOverride = null
    ) {
        if (!image) {
            return;
        }

        image.setAttribute('tabindex', '0');
        image.setAttribute('role', 'button');
        image.setAttribute('aria-label', 'Ampliar imagem');

        const open = () => {
            openServiceFileViewer(
                sourceOverride || image.currentSrc || image.src,
                'image',
                title
            );
        };

        image.addEventListener('click', event => {
            event.preventDefault();
            open();
        });

        image.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });
    }

    bindServicePreviewTriggers();

    document
        .querySelectorAll(
            '.svc-file-preview img'
        )
        .forEach(image => {
            if (!image.closest('.svc-preview-trigger')) {
                bindServiceImagePreview(
                    image,
                    image.alt || 'Prévia da imagem'
                );
            }
        });

    document
        .querySelector('[data-file-viewer-close]')
        ?.addEventListener(
            'click',
            requestCloseServiceFileViewer
        );

    serviceFileViewer?.addEventListener(
        'cancel',
        event => {
            event.preventDefault();
            requestCloseServiceFileViewer();
        }
    );

    serviceFileViewer?.addEventListener(
        'click',
        event => {
            if (event.target === serviceFileViewer) {
                requestCloseServiceFileViewer();
            }
        }
    );

    window.addEventListener(
        'popstate',
        () => {
            if (serviceFileViewer?.hasAttribute('open')) {
                closeServiceFileViewerDirect();
            }
        }
    );

    const SERVICE_DRAFT_DB_NAME =
        'sgc-service-drafts';

    const SERVICE_DRAFT_DB_VERSION =
        1;

    const SERVICE_DRAFT_EVIDENCE_STORE =
        'evidenceFiles';

    function serviceDraftEvidenceId(input) {
        if (!serviceLocalKey || !input?.name) {
            return '';
        }

        return `${serviceLocalKey}:${input.name}`;
    }

    function openServiceDraftDb() {
        return new Promise((resolve, reject) => {
            if (!('indexedDB' in window)) {
                reject(
                    new Error(
                        'IndexedDB indisponível'
                    )
                );
                return;
            }

            const request =
                indexedDB.open(
                    SERVICE_DRAFT_DB_NAME,
                    SERVICE_DRAFT_DB_VERSION
                );

            request.onupgradeneeded = () => {
                const db = request.result;

                if (
                    !db.objectStoreNames.contains(
                        SERVICE_DRAFT_EVIDENCE_STORE
                    )
                ) {
                    const store =
                        db.createObjectStore(
                            SERVICE_DRAFT_EVIDENCE_STORE,
                            {
                                keyPath: 'id',
                            }
                        );

                    store.createIndex(
                        'draftKey',
                        'draftKey',
                        {
                            unique: false,
                        }
                    );
                }
            };

            request.onsuccess = () => {
                resolve(request.result);
            };

            request.onerror = () => {
                reject(
                    request.error
                    || new Error(
                        'Falha ao abrir cache local'
                    )
                );
            };
        });
    }

    async function saveServiceDraftEvidenceFile(
        input,
        file
    ) {
        const id =
            serviceDraftEvidenceId(input);

        if (!id || !file) {
            return;
        }

        try {
            const db =
                await openServiceDraftDb();

            await new Promise(
                (resolve, reject) => {
                    const transaction =
                        db.transaction(
                            SERVICE_DRAFT_EVIDENCE_STORE,
                            'readwrite'
                        );

                    transaction
                        .objectStore(
                            SERVICE_DRAFT_EVIDENCE_STORE
                        )
                        .put({
                            id,
                            draftKey:
                                serviceLocalKey,
                            inputName:
                                input.name,
                            fileName:
                                file.name,
                            type:
                                file.type,
                            lastModified:
                                file.lastModified
                                || Date.now(),
                            blob:
                                file,
                            savedAt:
                                Date.now(),
                        });

                    transaction.oncomplete =
                        () => resolve();

                    transaction.onerror =
                        () => reject(
                            transaction.error
                        );
                }
            );

            db.close();
        } catch (error) {
            /*
             * Cache local é uma melhoria de resiliência.
             * Falha nele não deve impedir o envio normal.
             */
        }
    }

    async function removeServiceDraftEvidenceFile(
        input
    ) {
        const id =
            serviceDraftEvidenceId(input);

        if (!id) {
            return;
        }

        try {
            const db =
                await openServiceDraftDb();

            await new Promise(
                (resolve, reject) => {
                    const transaction =
                        db.transaction(
                            SERVICE_DRAFT_EVIDENCE_STORE,
                            'readwrite'
                        );

                    transaction
                        .objectStore(
                            SERVICE_DRAFT_EVIDENCE_STORE
                        )
                        .delete(id);

                    transaction.oncomplete =
                        () => resolve();

                    transaction.onerror =
                        () => reject(
                            transaction.error
                        );
                }
            );

            db.close();
        } catch (error) {}
    }

    async function clearServiceDraftEvidenceFiles() {
        if (!serviceLocalKey) {
            return;
        }

        try {
            const db =
                await openServiceDraftDb();

            await new Promise(
                (resolve, reject) => {
                    const transaction =
                        db.transaction(
                            SERVICE_DRAFT_EVIDENCE_STORE,
                            'readwrite'
                        );

                    const store =
                        transaction.objectStore(
                            SERVICE_DRAFT_EVIDENCE_STORE
                        );

                    const index =
                        store.index('draftKey');

                    const request =
                        index.openCursor(
                            IDBKeyRange.only(
                                serviceLocalKey
                            )
                        );

                    request.onsuccess = () => {
                        const cursor =
                            request.result;

                        if (!cursor) {
                            return;
                        }

                        cursor.delete();
                        cursor.continue();
                    };

                    transaction.oncomplete =
                        () => resolve();

                    transaction.onerror =
                        () => reject(
                            transaction.error
                        );
                }
            );

            db.close();
        } catch (error) {}
    }

    const SERVICE_ALLOWED_IMAGE_EXTENSIONS =
        new Set([
            'jpg',
            'jpeg',
            'png',
            'webp',
        ]);

    const SERVICE_ALLOWED_IMAGE_TYPES =
        new Set([
            'image/jpeg',
            'image/png',
            'image/webp',
        ]);

    function serviceFileExtension(file) {
        const name =
            String(
                file?.name
                || ''
            );

        const dotIndex =
            name.lastIndexOf('.');

        return dotIndex >= 0
            ? name
                .slice(dotIndex + 1)
                .toLowerCase()
            : '';
    }

    function isServiceEvidenceImage(file) {
        if (!file) {
            return false;
        }

        return (
            SERVICE_ALLOWED_IMAGE_EXTENSIONS
                .has(
                    serviceFileExtension(file)
                )
            || SERVICE_ALLOWED_IMAGE_TYPES
                .has(
                    String(
                        file.type
                        || ''
                    ).toLowerCase()
                )
        );
    }

    function isServiceEvidencePdf(file) {
        if (!file) {
            return false;
        }

        return (
            serviceFileExtension(file) === 'pdf'
            || String(
                file.type
                || ''
            ).toLowerCase()
                === 'application/pdf'
        );
    }

    function validateServiceEvidenceFile(
        input,
        file
    ) {
        if (!file) {
            return {
                valid: false,
                message: 'Nenhum arquivo selecionado.',
            };
        }

        const extension =
            serviceFileExtension(file);

        const type =
            String(
                file.type
                || ''
            ).toLowerCase();

        const isVideo =
            type.startsWith('video/')
            || [
                'mp4',
                'mov',
                'm4v',
                'avi',
                'mkv',
                'webm',
                '3gp',
                '3gpp',
            ].includes(extension);

        if (isVideo) {
            return {
                valid: false,
                message:
                    'Vídeos não são permitidos. Escolha uma foto ou imagem.',
            };
        }

        const allowedKind =
            input?.dataset.allowedKind
            || 'image';

        const isImage =
            isServiceEvidenceImage(file);

        const isPdf =
            isServiceEvidencePdf(file);

        if (
            allowedKind === 'image'
            && !isImage
        ) {
            return {
                valid: false,
                message:
                    'Este campo aceita somente JPG, JPEG, PNG ou WebP.',
            };
        }

        if (
            allowedKind === 'image_or_pdf'
            && !isImage
            && !isPdf
        ) {
            return {
                valid: false,
                message:
                    'Escolha JPG, JPEG, PNG, WebP ou PDF.',
            };
        }

        return {
            valid: true,
            message: '',
        };
    }

    function clearEvidenceFileError(input) {
        const wrapper =
            input?.closest(
                '.svc-evidence-field'
            );

        wrapper?.classList.remove(
            'has-evidence-error'
        );

        wrapper
            ?.querySelector(
                '.svc-evidence-file-error'
            )
            ?.remove();
    }

    function showEvidenceFileError(
        input,
        message
    ) {
        const wrapper =
            input?.closest(
                '.svc-evidence-field'
            );

        if (!wrapper) {
            return;
        }

        wrapper.classList.add(
            'has-evidence-error'
        );

        let error =
            wrapper.querySelector(
                '.svc-evidence-file-error'
            );

        if (!error) {
            error =
                document.createElement(
                    'small'
                );

            error.className =
                'svc-evidence-file-error';

            const picker =
                wrapper.querySelector(
                    '.svc-evidence-picker'
                );

            if (picker) {
                picker.insertAdjacentElement(
                    'afterend',
                    error
                );
            } else {
                wrapper.appendChild(error);
            }
        }

        error.innerHTML = `
            <i class="ph-fill ph-warning-circle"></i>
            ${escapeHtml(message)}
        `;
    }

    function setEvidenceInputFile(
        input,
        file
    ) {
        if (!input || !file) {
            return false;
        }

        try {
            const transfer =
                new DataTransfer();

            transfer.items.add(file);
            input.files = transfer.files;

            return true;
        } catch (error) {
            return false;
        }
    }

    function evidenceSelectionFor(input) {
        const id =
            input?.dataset.selection;

        return id
            ? document.getElementById(id)
            : null;
    }

    function updateEvidenceSelection(
        input,
        {
            saved = false,
            restored = false,
        } = {}
    ) {
        const selection =
            evidenceSelectionFor(input);

        if (!selection) {
            return;
        }

        const file =
            input.files?.[0];

        selection.classList.toggle(
            'is-selected',
            Boolean(file)
        );

        selection.classList.toggle(
            'is-saved',
            saved
            || input.dataset.evidenceSaved === '1'
        );

        if (file) {
            selection.innerHTML = `
                <i class="ph-fill ph-check-circle"></i>
                ${escapeHtml(file.name)}
                ${restored ? ' · recuperado do rascunho' : ''}
            `;
            return;
        }

        if (
            saved
            || input.dataset.evidenceSaved === '1'
        ) {
            selection.innerHTML = `
                <i class="ph-fill ph-check-circle"></i>
                Arquivo já salvo
            `;
            return;
        }

        selection.textContent =
            'Nenhum novo arquivo selecionado';
    }

    async function restoreServiceDraftEvidenceFiles(
        root = document
    ) {
        if (!serviceLocalKey) {
            return;
        }

        let db;

        try {
            db =
                await openServiceDraftDb();
        } catch (error) {
            return;
        }

        const inputs = [
            ...root.querySelectorAll(
                '.svc-evidence-input'
            ),
        ];

        for (const input of inputs) {
            if (input.files?.length) {
                updateEvidenceSelection(input);
                continue;
            }

            const id =
                serviceDraftEvidenceId(input);

            if (!id) {
                continue;
            }

            const record =
                await new Promise(resolve => {
                    const transaction =
                        db.transaction(
                            SERVICE_DRAFT_EVIDENCE_STORE,
                            'readonly'
                        );

                    const request =
                        transaction
                            .objectStore(
                                SERVICE_DRAFT_EVIDENCE_STORE
                            )
                            .get(id);

                    request.onsuccess =
                        () => resolve(
                            request.result
                            || null
                        );

                    request.onerror =
                        () => resolve(null);
                });

            if (!record?.blob) {
                updateEvidenceSelection(
                    input,
                    {
                        saved:
                            input.dataset.evidenceSaved
                            === '1',
                    }
                );
                continue;
            }

            const restoredFile =
                new File(
                    [record.blob],
                    record.fileName
                    || 'evidencia',
                    {
                        type:
                            record.type
                            || record.blob.type
                            || 'application/octet-stream',
                        lastModified:
                            record.lastModified
                            || Date.now(),
                    }
                );

            const restoredValidation =
                validateServiceEvidenceFile(
                    input,
                    restoredFile
                );

            if (!restoredValidation.valid) {
                await removeServiceDraftEvidenceFile(
                    input
                );
                continue;
            }

            if (
                !setEvidenceInputFile(
                    input,
                    restoredFile
                )
            ) {
                continue;
            }

            const box =
                document.getElementById(
                    input.dataset.preview
                );

            if (box) {
                renderOriginalFile(
                    box,
                    restoredFile
                );
            }

            updateEvidenceSelection(
                input,
                {
                    restored: true,
                }
            );
        }

        db.close();
    }

    function validateServiceEvidenceInputs(
        form
    ) {
        let firstInvalid = null;

        form
            .querySelectorAll(
                '.svc-evidence-input'
            )
            .forEach(input => {
                const wrapper =
                    input.closest(
                        '.svc-evidence-field'
                    );

                wrapper?.classList.remove(
                    'has-evidence-error'
                );

                if (
                    input.disabled
                    || wrapper?.hidden
                    || input.dataset.required !== '1'
                ) {
                    return;
                }

                const hasNewFile =
                    Boolean(
                        input.files?.length
                    );

                const hasSavedFile =
                    input.dataset.evidenceSaved
                    === '1';

                if (
                    !hasNewFile
                    && !hasSavedFile
                ) {
                    wrapper?.classList.add(
                        'has-evidence-error'
                    );

                    firstInvalid ??=
                        input;
                }
            });

        if (!firstInvalid) {
            return true;
        }

        const wrapper =
            firstInvalid.closest(
                '.svc-evidence-field'
            );

        wrapper?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });

        const fileButton =
            wrapper?.querySelector(
                '[data-open-file-picker]'
            );

        fileButton?.focus({
            preventScroll: true,
        });

        return false;
    }

    function showServiceReloadOverlay(
        title = 'Atualizando ordem…',
        copy = 'Salvando o novo estado e recarregando a tela.'
    ) {
        if (!serviceReloadOverlay) {
            return;
        }

        if (serviceReloadTitle) {
            serviceReloadTitle.textContent =
                title;
        }

        if (serviceReloadCopy) {
            serviceReloadCopy.textContent =
                copy;
        }

        serviceReloadOverlay.hidden = false;
    }

    document.addEventListener(
        'click',
        async event => {
            const fileButton =
                event.target.closest(
                    '[data-open-file-picker]'
                );

            if (fileButton) {
                const target =
                    document.getElementById(
                        fileButton.dataset
                            .openFilePicker
                    );

                target?.click();
                return;
            }

            const cameraButton =
                event.target.closest(
                    '[data-open-camera]'
                );

            if (cameraButton) {
                const cameraInput =
                    document.getElementById(
                        cameraButton.dataset
                            .openCamera
                    );

                const evidenceInput =
                    document.getElementById(
                        cameraInput?.dataset
                            .targetInput
                    );

                const nativeCamera =
                    window.Capacitor?.Plugins
                        ?.NativeCamera;

                if (
                    window.Capacitor
                        ?.isNativePlatform?.()
                    && nativeCamera?.takePhoto
                    && evidenceInput
                ) {
                    cameraButton.disabled = true;

                    try {
                        const capture =
                            await nativeCamera
                                .takePhoto();

                        const blob =
                            await fetch(
                                capture.dataUrl
                            ).then(
                                response => response.blob()
                            );

                        const file = new File(
                            [blob],
                            capture.fileName
                                || 'evidencia.jpg',
                            {
                                type:
                                    capture.mimeType
                                    || 'image/jpeg',
                            }
                        );

                        if (
                            setEvidenceInputFile(
                                evidenceInput,
                                file
                            )
                        ) {
                            evidenceInput.dispatchEvent(
                                new Event(
                                    'change',
                                    {bubbles: true}
                                )
                            );
                        }
                    } catch (error) {
                        if (
                            error?.code
                            !== 'CAMERA_CANCELLED'
                        ) {
                            window.appToast?.(
                                'A câmera nativa não respondeu. Abrindo a câmera compatível do aparelho.',
                                'warning'
                            );
                            cameraInput?.click();
                        }
                    } finally {
                        cameraButton.disabled = false;
                    }

                    return;
                }

                cameraInput?.click();
            }
        }
    );

    document.addEventListener(
        'change',
        event => {
            const cameraInput =
                event.target.closest(
                    '.svc-evidence-camera-input'
                );

            if (!cameraInput) {
                return;
            }

            const file =
                cameraInput.files?.[0];

            const target =
                document.getElementById(
                    cameraInput.dataset
                        .targetInput
                );

            if (file && target) {
                const validation =
                    validateServiceEvidenceFile(
                        target,
                        file
                    );

                if (!validation.valid) {
                    showEvidenceFileError(
                        target,
                        validation.message
                    );

                    cameraInput.value = '';
                    return;
                }

                clearEvidenceFileError(target);

                if (
                    setEvidenceInputFile(
                        target,
                        file
                    )
                ) {
                    target.dispatchEvent(
                        new Event(
                            'change',
                            {
                                bubbles: true,
                            }
                        )
                    );
                }
            }

            cameraInput.value = '';
        }
    );

    async function submitServiceForm(form, {draft = false} = {}) {
        if (
            !draft
            && !validateServiceEvidenceInputs(form)
        ) {
            const feedback = feedbackFor(form);

            feedback.className =
                'svc-alert danger svc-async-feedback';

            feedback.innerHTML = `
                <span class="svc-alert-icon">
                    <i class="ph-fill ph-warning-circle"></i>
                </span>
                <div>
                    <strong>Falta uma evidência obrigatória.</strong>
                    <br>
                    <small>
                        Escolha um arquivo ou tire uma foto no campo destacado.
                    </small>
                </div>
            `;

            feedback.hidden = false;
            return;
        }

        if (!draft && !form.reportValidity()) {
            return;
        }

        let navigating = false;
        const buttons = [...form.querySelectorAll('button')];
        const feedback = feedbackFor(form);
        buttons.forEach(button => button.disabled = true);
        feedback.className = 'svc-alert svc-async-feedback';
        feedback.innerHTML = '<span class="svc-alert-icon"><i class="ph-fill ph-spinner-gap"></i></span><div>Salvando sem recarregar a página…</div>';
        feedback.hidden = false;

        try {
            const data = new FormData(form);
            if (draft) data.set('_method', 'PUT');
            const response = await fetch(draft ? serviceDraftUrl : form.action, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            const payload = await response.json().catch(() => ({message: 'O servidor retornou uma resposta inválida.'}));
            if (!response.ok) {
                const messages = payload.errors ? Object.values(payload.errors).flat() : [payload.message || 'Não foi possível salvar.'];
                showAsyncErrors(form, feedback, messages, payload.errors || {});
                return;
            }

            feedback.className =
                'svc-alert svc-async-feedback';

            feedback.innerHTML = `
                <span class="svc-alert-icon">
                    <i class="ph-fill ph-check-circle"></i>
                </span>
                <div>
                    <strong>
                        ${escapeHtml(payload.message || 'Dados salvos.')}
                    </strong>
                    <br>
                    <small>
                        ${draft
                            ? 'Rascunho salvo com segurança.'
                            : 'Atualizando o estado da ordem…'}
                    </small>
                </div>
            `;

            await updateEvidencePreviews(
                form,
                payload.evidences || []
            );

            /*
             * Se o servidor persistiu a evidência, a referência
             * deixa de depender do FileList local.
             */
            form
                .querySelectorAll(
                    '.svc-evidence-input'
                )
                .forEach(input => {
                    if (
                        input.dataset.evidenceSaved
                        === '1'
                    ) {
                        input.value = '';
                        updateEvidenceSelection(
                            input,
                            {
                                saved: true,
                            }
                        );
                    }
                });

            if (serviceLocalKey) {
                localStorage.removeItem(
                    serviceLocalKey
                );
            }

            /*
             * Toda ação não-rascunho é uma transição de estado
             * nesta tela. Recarrega por padrão, salvo se o backend
             * explicitamente responder reload:false.
             */
            const shouldReload =
                draft
                    ? Boolean(payload.reload)
                    : payload.reload !== false;

            if (!draft) {
                await clearServiceDraftEvidenceFiles();
            }

            if (shouldReload) {
                navigating = true;

                showServiceReloadOverlay(
                    payload.message
                    || 'Atualizando ordem…',
                    'Aplicando o novo estado e recarregando a tela.'
                );

                window.setTimeout(
                    () => {
                        window.location.assign(
                            payload.url
                            || window.location.href
                        );
                    },
                    750
                );
            }
        } catch (error) {
            showAsyncErrors(form, feedback, ['Falha de conexão. Os dados continuam nesta tela; tente novamente.'], {});
        } finally {
            if (!navigating) {
                buttons.forEach(
                    button => {
                        button.disabled = false;
                    }
                );
            }
        }
    }

    function feedbackFor(form) {
        let feedback = form.querySelector('.svc-async-feedback');
        if (!feedback) {
            feedback = document.createElement('div'); feedback.className = 'svc-alert svc-async-feedback'; feedback.hidden = true;
            form.prepend(feedback);
        }
        return feedback;
    }

    function showAsyncErrors(form, feedback, messages, errors) {
        feedback.className = 'svc-alert danger svc-async-feedback';
        feedback.innerHTML = `<span class="svc-alert-icon"><i class="ph-fill ph-warning-circle"></i></span><div><strong>Não foi possível concluir.</strong><ul style="margin:.35rem 0 0;padding-left:1.15rem">${messages.map(message => `<li>${escapeHtml(message)}</li>`).join('')}</ul><small>Corrija somente os itens indicados. Tudo o que foi preenchido continua na tela.</small></div>`;
        form.querySelectorAll('[aria-invalid="true"]').forEach(field => field.removeAttribute('aria-invalid'));
        let first = null;
        Object.keys(errors).forEach(key => {
            const simple = key.replace(/^values\./, '').replace(/^evidences\./, '');
            const field = form.querySelector(`[name="values[${CSS.escape(simple)}]"], [name="evidences[${CSS.escape(simple)}]"]`);
            if (field) { field.setAttribute('aria-invalid', 'true'); first ||= field; }
        });
        (first || feedback).scrollIntoView({behavior: 'smooth', block: 'center'});
        first?.focus({preventScroll: true});
    }

    async function updateEvidencePreviews(form, evidences) {
        for (const item of evidences) {
            const input = form.querySelector(
                `[name="evidences[${CSS.escape(item.field_key)}]"]`
            );

            const box = input
                ? document.getElementById(input.dataset.preview)
                : null;

            if (!box) {
                return;
            }

            const name =
                escapeHtml(item.name || 'Evidência enviada');

            const url =
                escapeHtml(item.url || '');

            const mime =
                String(item.mime_type || '');

            const previewType =
                mime.startsWith('image/')
                    ? 'image'
                    : (
                        mime === 'application/pdf'
                            ? 'pdf'
                            : 'file'
                    );

            if (previewType === 'image') {
                box.innerHTML = `
                    <button
                        type="button"
                        class="svc-image-preview-button svc-preview-trigger"
                        data-preview-url="${url}"
                        data-preview-type="image"
                        data-preview-title="${name}"
                    >
                        <img
                            src="${url}"
                            alt="Prévia de ${name}"
                        >
                        <span class="svc-image-preview-hint">
                            <i class="ph-fill ph-arrows-out"></i>
                            Ampliar
                        </span>
                    </button>
                `;
            } else {
                box.innerHTML = `
                    <button
                        type="button"
                        class="svc-table-link svc-preview-trigger"
                        data-preview-url="${url}"
                        data-preview-type="${previewType}"
                        data-preview-title="${name}"
                    >
                        <i class="ph-fill ph-eye"></i>
                        Visualizar arquivo
                    </button>
                `;
            }

            input.dataset.evidenceSaved = '1';

            input
                .closest('.svc-evidence-field')
                ?.classList.remove(
                    'has-evidence-error'
                );

            updateEvidenceSelection(
                input,
                {
                    saved: true,
                }
            );

            await removeServiceDraftEvidenceFile(
                input
            );

            bindServicePreviewTriggers(box);
        }
    }

    document.addEventListener(
        'DOMContentLoaded',
        async () => {
            bindServicePreviewTriggers();

            /*
             * Se a ordem já chegou ao estado final, não há motivo
             * para manter rascunho ou arquivos temporários locais.
             */
            if (
                serviceExecutionStatus === 'validated'
            ) {
                if (serviceLocalKey) {
                    localStorage.removeItem(
                        serviceLocalKey
                    );
                }

                await clearServiceDraftEvidenceFiles();
            }

            document.querySelectorAll('[data-async-service-form]').forEach(form => {
                form.addEventListener('submit', event => { event.preventDefault(); submitServiceForm(form); });
                form.querySelector('[data-save-draft]')?.addEventListener('click', () => submitServiceForm(form, {draft: true}));
            });

            const executionForm = document.getElementById('execution-form');
            if (executionForm) {
                try {
                    const saved = serviceLocalKey
                        ? JSON.parse(
                            localStorage.getItem(serviceLocalKey)
                            || '{}'
                        )
                        : {};
                    Object.entries(saved).forEach(([name, value]) => {
                        const field = executionForm.elements.namedItem(name);
                        if (field && field.type !== 'file') field.value = value;
                    });
                } catch (error) {
                    if (serviceLocalKey) {
                        localStorage.removeItem(serviceLocalKey);
                    }
                }
                const remember = () => {
                    const values = {};
                    executionForm.querySelectorAll('[name^="values["]').forEach(field => { if (!field.disabled) values[field.name] = field.value; });
                    if (serviceLocalKey) {
                        localStorage.setItem(
                            serviceLocalKey,
                            JSON.stringify(values)
                        );
                    }
                };
                executionForm.addEventListener('input', remember);
                executionForm.addEventListener('change', remember);
            }

            const syncConditions = () => {
                document.querySelectorAll('[data-condition]').forEach(wrapper => {
                    let rule = null; try { rule = JSON.parse(wrapper.dataset.condition || 'null'); } catch (error) {}
                    if (!rule?.field) { wrapper.hidden = false; return; }
                    const source = document.querySelector(`[name="values[${CSS.escape(rule.field)}]"]`);
                    const actual = source?.value; const expected = rule.value;
                    const visible = ({equals:actual == expected, not_equals:actual != expected, is_true:['1','true',1,true].includes(actual), is_false:!['1','true',1,true].includes(actual), greater_than:Number(actual)>Number(expected), less_than:Number(actual)<Number(expected)})[rule.operator || 'equals'] ?? false;
                    wrapper.hidden = !visible;
                    wrapper
                        .querySelectorAll(
                            'input,select,textarea,button'
                        )
                        .forEach(
                            field => {
                                field.disabled =
                                    !visible;
                            }
                        );
                });
            };
            document.addEventListener('input', syncConditions); document.addEventListener('change', syncConditions); syncConditions();

            const startKey =
                serviceMeterStartField;

            const endKey =
                serviceMeterEndField;

            const preview =
                document.getElementById(
                    'meter-preview'
                );

            const result =
                document.getElementById(
                    'meter-result'
                );

            /*
             * Mantém a lógica original da diferença de medidores,
             * mas evita tratar campo vazio como zero.
             */
            if (
                startKey
                && endKey
                && preview
                && result
            ) {
                const start =
                    document.querySelector(
                        `[name="values[${startKey}]"]`
                    );

                const end =
                    document.querySelector(
                        `[name="values[${endKey}]"]`
                    );

                const syncMeterPreview = () => {
                    const startRaw =
                        String(
                            start?.value ?? ''
                        ).trim();

                    const endRaw =
                        String(
                            end?.value ?? ''
                        ).trim();

                    if (!startRaw || !endRaw) {
                        preview.style.display =
                            'none';

                        preview.classList.remove(
                            'is-invalid'
                        );

                        result.textContent = '—';

                        return;
                    }

                    const startValue =
                        Number(startRaw);

                    const endValue =
                        Number(endRaw);

                    const validNumbers =
                        Number.isFinite(startValue)
                        && Number.isFinite(endValue);

                    preview.style.display =
                        validNumbers
                            ? 'grid'
                            : 'none';

                    if (!validNumbers) {
                        return;
                    }

                    const validDifference =
                        endValue >= startValue;

                    preview.classList.toggle(
                        'is-invalid',
                        !validDifference
                    );

                    result.textContent =
                        validDifference
                            ? (
                                endValue
                                - startValue
                            ).toLocaleString(
                                'pt-BR',
                                {
                                    maximumFractionDigits: 4,
                                }
                            )
                            + ' '
                            + serviceExecutionUnit
                            : 'Medição final inválida';
                };

                start?.addEventListener(
                    'input',
                    syncMeterPreview
                );

                end?.addEventListener(
                    'input',
                    syncMeterPreview
                );

                syncMeterPreview();
            }

            /*
             * Mantém a otimização local das imagens antes do
             * upload. Em caso de incompatibilidade do navegador,
             * o arquivo original permanece selecionado.
             */
            document
                .querySelectorAll(
                    '.svc-evidence-input'
                )
                .forEach(input => {
                    input.addEventListener(
                        'change',
                        async () => {
                            const file =
                                input.files?.[0];

                            if (file) {
                                const validation =
                                    validateServiceEvidenceFile(
                                        input,
                                        file
                                    );

                                if (!validation.valid) {
                                    input.value = '';

                                    showEvidenceFileError(
                                        input,
                                        validation.message
                                    );

                                    updateEvidenceSelection(
                                        input
                                    );

                                    return;
                                }

                                clearEvidenceFileError(
                                    input
                                );
                            }

                            const box =
                                document.getElementById(
                                    input.dataset.preview
                                );

                            if (!file || !box) {
                                return;
                            }

                            if (
                                !isServiceEvidenceImage(
                                    file
                                )
                            ) {
                                if (box.dataset.objectUrl) {
                                    URL.revokeObjectURL(
                                        box.dataset.objectUrl
                                    );
                                }

                                const objectUrl =
                                    URL.createObjectURL(file);

                                box.dataset.objectUrl =
                                    objectUrl;

                                const previewType =
                                    isServiceEvidencePdf(file)
                                        ? 'pdf'
                                        : 'file';

                                box.innerHTML = `
                                    <button
                                        type="button"
                                        class="svc-table-link svc-preview-trigger"
                                        data-preview-url="${escapeHtml(objectUrl)}"
                                        data-preview-type="${previewType}"
                                        data-preview-title="${escapeHtml(file.name)}"
                                    >
                                        <i class="ph-fill ph-eye"></i>
                                        Visualizar ${previewType === 'pdf' ? 'PDF' : 'arquivo'}
                                    </button>
                                `;

                                bindServicePreviewTriggers(box);

                                await saveServiceDraftEvidenceFile(
                                    input,
                                    file
                                );

                                updateEvidenceSelection(
                                    input
                                );

                                input.dataset.evidenceSaved =
                                    '0';

                                return;
                            }

                            try {
                                const bitmap =
                                    await createImageBitmap(
                                        file
                                    );

                                const scale =
                                    Math.min(
                                        1,
                                        2048
                                        / Math.max(
                                            bitmap.width,
                                            bitmap.height
                                        )
                                    );

                                const canvas =
                                    document.createElement(
                                        'canvas'
                                    );

                                canvas.width =
                                    Math.round(
                                        bitmap.width
                                        * scale
                                    );

                                canvas.height =
                                    Math.round(
                                        bitmap.height
                                        * scale
                                    );

                                const context =
                                    canvas.getContext('2d');

                                if (!context) {
                                    throw new Error(
                                        'Canvas indisponível'
                                    );
                                }

                                context.drawImage(
                                    bitmap,
                                    0,
                                    0,
                                    canvas.width,
                                    canvas.height
                                );

                                canvas.toBlob(
                                    blob => {
                                        if (!blob) {
                                            renderOriginalFile(
                                                box,
                                                file
                                            );

                                            return;
                                        }

                                        try {
                                            const optimized =
                                                new File(
                                                    [blob],
                                                    file.name.replace(
                                                        /\.[^.]+$/,
                                                        ''
                                                    )
                                                    + '.webp',
                                                    {
                                                        type:
                                                            'image/webp',
                                                    }
                                                );

                                            const transfer =
                                                new DataTransfer();

                                            transfer.items.add(
                                                optimized
                                            );

                                            input.files =
                                                transfer.files;
                                        } catch (error) {
                                            /*
                                             * Se DataTransfer/File
                                             * não permitirem troca,
                                             * mantém o original.
                                             */
                                        }

                                        box.innerHTML = '';

                                        const image =
                                            document.createElement(
                                                'img'
                                            );

                                        if (box.dataset.objectUrl) {
                                            URL.revokeObjectURL(
                                                box.dataset.objectUrl
                                            );
                                        }

                                        const objectUrl =
                                            URL.createObjectURL(blob);

                                        box.dataset.objectUrl =
                                            objectUrl;

                                        image.src = objectUrl;
                                        image.alt =
                                            'Prévia da evidência';

                                        box.appendChild(image);

                                        bindServiceImagePreview(
                                            image,
                                            file.name,
                                            objectUrl
                                        );

                                        input.dataset.evidenceSaved =
                                            '0';

                                        const cachedFile =
                                            input.files?.[0]
                                            || file;

                                        saveServiceDraftEvidenceFile(
                                            input,
                                            cachedFile
                                        );

                                        updateEvidenceSelection(
                                            input
                                        );
                                    },
                                    'image/webp',
                                    .82
                                );
                            } catch (error) {
                                renderOriginalFile(
                                    box,
                                    file
                                );

                                input.dataset.evidenceSaved =
                                    '0';

                                await saveServiceDraftEvidenceFile(
                                    input,
                                    file
                                );

                                updateEvidenceSelection(
                                    input
                                );
                            }
                        }
                    );
                });

            await restoreServiceDraftEvidenceFiles(
                document
            );
        }
    );

    function renderOriginalFile(
        box,
        file
    ) {
        if (!isServiceEvidenceImage(file)) {
            if (box.dataset.objectUrl) {
                URL.revokeObjectURL(box.dataset.objectUrl);
            }

            const objectUrl =
                URL.createObjectURL(file);

            box.dataset.objectUrl =
                objectUrl;

            const previewType =
                isServiceEvidencePdf(file)
                    ? 'pdf'
                    : 'file';

            box.innerHTML = `
                <button
                    type="button"
                    class="svc-table-link svc-preview-trigger"
                    data-preview-url="${escapeHtml(objectUrl)}"
                    data-preview-type="${previewType}"
                    data-preview-title="${escapeHtml(file.name)}"
                >
                    <i class="ph-fill ph-eye"></i>
                    Visualizar arquivo
                </button>
            `;

            bindServicePreviewTriggers(box);
            return;
        }

        const reader = new FileReader();

        reader.addEventListener(
            'load',
            () => {
                box.innerHTML = '';

                const image =
                    document.createElement('img');

                image.src =
                    String(reader.result || '');

                image.alt =
                    `Prévia de ${file.name}`;

                box.appendChild(image);

                bindServiceImagePreview(
                    image,
                    file.name,
                    image.src
                );
            },
            {once: true}
        );

        reader.readAsDataURL(file);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(
                /[&<>"']/g,
                character => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;',
                })[character]
            );
    }
</script>
@endsection
