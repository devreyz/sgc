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
            'icon' => 'ph-check',
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
     * Valores preparados para JavaScript.
     * Evita @json() com expressões complexas dentro do script.
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

    $executionUnit = $execution->unit;
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

    .svc-form {
        display: grid;
        grid-template-columns:
            repeat(
                auto-fit,
                minmax(
                    min(100%, 17rem),
                    1fr
                )
            );
        gap: .72rem;
    }

    /*
     * O partial provider._service-fields pode usar as classes
     * globais já existentes no layout. Aqui apenas harmonizamos
     * os campos dentro deste módulo.
     */
    .svc-form .form-group,
    .svc-form .field-group,
    .svc-form .svc-field {
        min-width: 0;
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
        grid-column: 1 / -1;
        display: flex;
        gap: .42rem;
        align-items: center;
        justify-content: flex-end;
        flex-wrap: wrap;
        padding-top: .2rem;
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
        grid-column: 1 / -1;
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

    @media (max-width: 720px) {
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
</style>

<main class="service-execution">
    @if($errors->any())
        <div class="svc-alert danger" role="alert" tabindex="-1" id="form-errors">
            <span class="svc-alert-icon" aria-hidden="true"><i class="ph-fill ph-warning-circle"></i></span>
            <div><strong>Não foi possível concluir a ordem.</strong><ul style="margin:.35rem 0 0;padding-left:1.15rem">@foreach($errors->all() as $message)<li>{{$message}}</li>@endforeach</ul><small>Os dados preenchidos permanecem na tela. Corrija somente os itens indicados e envie novamente.</small></div>
        </div>
    @endif
    {{-- =========================================================
         CABEÇALHO + FLUXO
         ========================================================= --}}
    <section class="svc-panel">
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
                    <i class="ph ph-arrow-left"></i>
                </a>

                <span
                    class="svc-header-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-wrench"></i>
                </span>

                <div class="svc-header-copy">
                    <h1>
                        {{ $order->number }}
                        ·
                        {{ $order->service->name }}
                    </h1>

                    <p>
                        {{ $beneficiaryName }}
                        ·
                        {{ $locationLabel }}
                    </p>
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
            <div
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
                        "
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
                                    {{ $stepDone
                                        ? 'ph-check'
                                        : $step['icon'] }}
                                "
                            ></i>
                        </span>

                        <span class="svc-step-copy">
                            <strong>
                                {{ $step['label'] }}
                            </strong>

                            <span>
                                @if($stepDone)
                                    concluída
                                @elseif($stepCurrent)
                                    etapa atual
                                @else
                                    próxima etapa
                                @endif
                            </span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- =========================================================
         RESUMO OPERACIONAL E FINANCEIRO
         ========================================================= --}}
    <div class="svc-summary-grid">
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
                        <i class="ph-fill ph-list-checks"></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>Dados da ordem</h2>

                        <p>
                            Informações operacionais principais.
                        </p>
                    </div>
                </div>
            </header>

            <table
                class="svc-info-table"
                aria-label="Dados da ordem de serviço"
            >
                <tbody>
                    <tr>
                        <th>Agendamento</th>

                        <td>
                            {{ $scheduledLabel }}
                        </td>
                    </tr>

                    <tr>
                        <th>Beneficiário</th>

                        <td>
                            {{ $beneficiaryName }}
                        </td>
                    </tr>

                    <tr>
                        <th>Prestador</th>

                        <td>
                            {{ $providerName }}
                        </td>
                    </tr>

                    <tr>
                        <th>Serviço</th>

                        <td>
                            {{ $order->service->name }}
                        </td>
                    </tr>

                    <tr>
                        <th>Local</th>

                        <td>
                            {{ $locationLabel }}
                        </td>
                    </tr>

                    <tr>
                        <th>Evidências</th>

                        <td>
                            {{ $evidenceTotal }}

                            @if($evidenceConfiguredTotal > 0)
                                de
                                {{ $evidenceConfiguredTotal }}
                                campo(s) de evidência configurado(s)
                            @else
                                arquivo(s) enviado(s)
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="svc-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:var(--svc-green);
                    --panel-soft:var(--svc-green-soft);
                "
            >
                <div class="svc-panel-title">
                    <span
                        class="svc-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-calculator"></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>Prévia financeira</h2>

                        <p>
                            Cobrança e remuneração previstas.
                        </p>
                    </div>
                </div>
            </header>

            <table
                class="svc-money-table"
                aria-label="Prévia financeira da ordem"
            >
                <tbody>
                    <tr>
                        <th>
                            Solicitante paga à organização

                            <span class="svc-money-note">
                                Regra:
                                {{ $formatMethod(
                                    $customerPricingMethod
                                ) }}

                                @if(
                                    $order->serviceVersion
                                        ?->customer_rate
                                )
                                    ·
                                    {{ $formatMoney(
                                        $order
                                            ->serviceVersion
                                            ->customer_rate
                                    ) }}
                                @endif
                            </span>
                        </th>

                        <td>
                            <span
                                class="
                                    svc-money-value
                                    receivable
                                "
                            >
                                {{ $receivablePreview !== null
                                    ? $formatMoney(
                                        $receivablePreview
                                    )
                                    : 'A calcular' }}
                            </span>

                            @if($receivablePreview === null)
                                <span class="svc-money-note">
                                    Preencha os dados necessários
                                    para obter o valor.
                                </span>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <th>
                            Prestador recebe

                            <span class="svc-money-note">
                                Regra:
                                {{ $formatMethod(
                                    $providerPricingMethod
                                ) }}

                                @if($providerConfiguredRate)
                                    ·
                                    {{ $providerPricingMethod === 'percent_of_base'
                                        ? number_format((float) $providerConfiguredRate, 2, ',', '.') . '%'
                                        : $formatMoney($providerConfiguredRate) }}
                                @endif
                                <br>
                                <strong>{{ in_array($providerPricingSource, ['provider_override', 'provider_service_version'], true) ? 'Exceção individual deste prestador' : 'Regra padrão da versão' }}</strong>
                            </span>
                        </th>

                        <td>
                            <span
                                class="
                                    svc-money-value
                                    payable
                                "
                            >
                                {{ $payablePreview !== null
                                    ? $formatMoney(
                                        $payablePreview
                                    )
                                    : 'A calcular' }}
                            </span>

                            @if($payablePreview === null)
                                <span class="svc-money-note">
                                    O valor será atualizado
                                    conforme a execução.
                                </span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>

    @if(collect($execution->values)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty())
        <section class="svc-panel">
            <header class="svc-panel-head" style="--panel-tone:var(--svc-blue);--panel-soft:var(--svc-blue-soft)">
                <div class="svc-panel-title"><span class="svc-panel-icon"><i class="ph-fill ph-clock-counter-clockwise"></i></span><div class="svc-panel-copy"><h2>Dados já registrados</h2><p>Valores salvos anteriormente nesta execução.</p></div></div>
            </header>
            <table class="svc-info-table"><tbody>
                @foreach(collect($execution->values)->filter(fn ($value) => $value !== null && $value !== '') as $key => $value)
                    @php($savedField = $fields->firstWhere('key', $key))
                    <tr><th>{{$savedField['label'] ?? \Illuminate\Support\Str::headline($key)}}</th><td>{{is_bool($value) ? ($value ? 'Sim' : 'Não') : (is_array($value) ? implode(', ', $value) : $value)}} @if(data_get($savedField, 'unit')) {{data_get($savedField, 'unit')}} @endif</td></tr>
                @endforeach
            </tbody></table>
        </section>
    @endif

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
                        ]
                    )

                    @include(
                        'provider._service-fields',
                        [
                            'execution' => $execution,
                            'phase' => 'finish',
                            'operator' => $operator ?? false,
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
                            onclick="saveDraft()"
                        >
                            <i class="ph ph-floppy-disk"></i>
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

    {{-- =========================================================
         EVIDÊNCIAS JÁ ENVIADAS
         ========================================================= --}}
    @if($execution->evidences->isNotEmpty())
        <section class="svc-panel">
            <header
                class="svc-panel-head"
                style="
                    --panel-tone:var(--svc-violet);
                    --panel-soft:var(--svc-violet-soft);
                "
            >
                <div class="svc-panel-title">
                    <span
                        class="svc-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-paperclip"></i>
                    </span>

                    <div class="svc-panel-copy">
                        <h2>Arquivos enviados</h2>

                        <p>
                            Evidências já vinculadas à execução.
                        </p>
                    </div>
                </div>

                <span class="svc-count">
                    <i class="ph ph-files"></i>
                    {{ $evidenceTotal }}
                </span>
            </header>

            <div class="svc-evidence-wrap">
                <table
                    class="svc-evidence-table"
                    aria-label="Arquivos enviados"
                >
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Campo</th>
                            <th>Ação</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($execution->evidences as $evidence)
                            <tr>
                                <td>
                                    <div class="evidence-name">
                                        <span
                                            class="evidence-icon"
                                            aria-hidden="true"
                                        >
                                            <i class="ph-fill ph-file"></i>
                                        </span>

                                        <strong>
                                            {{ $evidence
                                                ->document
                                                ->name }}
                                        </strong>
                                    </div>
                                </td>

                                <td>
                                    {{ $evidence->field_key
                                        ?: '—' }}
                                </td>

                                <td>
                                    <a
                                        class="svc-table-link"
                                        href="{{ route(
                                            'provider.evidences.download',
                                            [
                                                $tenantSlug,
                                                $evidence,
                                            ]
                                        ) }}"
                                        target="_blank"
                                    >
                                        <i class="ph ph-eye"></i>
                                        Visualizar
                                    </a>
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
                                @foreach(['customer' => 'Cobrança', 'provider' => 'Remuneração'] as $direction => $label)
                                    @php($calculation = data_get($execution->derived_values, "calculation.$direction", []))

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
</main>

<script>
    function saveDraft() {
        const form =
            document.getElementById(
                'execution-form'
            );

        if (!form) {
            return;
        }

        form.action =
            {{ \Illuminate\Support\Js::from(
                $draftUrl
            ) }};

        let method =
            form.querySelector(
                'input[name="_method"]'
            );

        if (!method) {
            method =
                document.createElement('input');

            method.type = 'hidden';
            method.name = '_method';

            form.appendChild(method);
        }

        method.value = 'PUT';

        form.submit();
    }

    document.addEventListener(
        'DOMContentLoaded',
        () => {
            const startKey =
                {{ \Illuminate\Support\Js::from(
                    $meterStartField
                ) }};

            const endKey =
                {{ \Illuminate\Support\Js::from(
                    $meterEndField
                ) }};

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
                            + {{ \Illuminate\Support\Js::from(
                                $executionUnit
                            ) }}
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

                            const box =
                                document.getElementById(
                                    input.dataset.preview
                                );

                            if (!file || !box) {
                                return;
                            }

                            if (
                                !file.type.startsWith(
                                    'image/'
                                )
                            ) {
                                box.innerHTML =
                                    `<small>${escapeHtml(
                                        file.name
                                    )}</small>`;

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

                                        const objectUrl =
                                            URL.createObjectURL(
                                                blob
                                            );

                                        image.src = objectUrl;
                                        image.alt =
                                            'Prévia da evidência';

                                        image.addEventListener(
                                            'load',
                                            () => {
                                                URL.revokeObjectURL(
                                                    objectUrl
                                                );
                                            },
                                            {
                                                once: true,
                                            }
                                        );

                                        box.appendChild(image);
                                    },
                                    'image/webp',
                                    .82
                                );
                            } catch (error) {
                                renderOriginalFile(
                                    box,
                                    file
                                );
                            }
                        }
                    );
                });
        }
    );

    function renderOriginalFile(
        box,
        file
    ) {
        box.innerHTML =
            `<small>${escapeHtml(
                file.name
            )}</small>`;
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
