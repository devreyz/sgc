@extends('layouts.bento')

@section('title', 'Ordem #' . $order->number)
@section('page-title', 'Ordem #' . $order->number)
@section('page-subtitle', 'Acompanhe execução, valores, pagamentos e movimentações desta ordem.')
@section('user-role', 'Prestador de Serviço')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = $currentTenant?->slug
        ?? (
            is_object($routeTenant)
                ? ($routeTenant->slug ?? null)
                : $routeTenant
        );

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'provider',
        'orders',
        $tenantSlug
    );

    $orderStatusValue = is_object($order->status ?? null)
        ? ($order->status->value ?? null)
        : ($order->status ?? null);

    $orderStatusLabel = is_object($order->status ?? null)
        && method_exists($order->status, 'getLabel')
            ? $order->status->getLabel()
            : \Illuminate\Support\Str::headline(
                (string) ($orderStatusValue ?: 'registrada')
            );

    $orderStatusMeta = match ($orderStatusValue) {
        'scheduled' => [
            'class' => 'is-scheduled',
            'icon' => 'ph-calendar-check',
        ],
        'in_progress' => [
            'class' => 'is-progress',
            'icon' => 'ph-play-circle',
        ],
        'awaiting_payment' => [
            'class' => 'is-waiting',
            'icon' => 'ph-clock-countdown',
        ],
        'completed' => [
            'class' => 'is-completed',
            'icon' => 'ph-check-circle',
        ],
        'paid' => [
            'class' => 'is-paid',
            'icon' => 'ph-seal-check',
        ],
        'cancelled' => [
            'class' => 'is-cancelled',
            'icon' => 'ph-x-circle',
        ],
        'billed' => [
            'class' => 'is-billed',
            'icon' => 'ph-receipt',
        ],
        default => [
            'class' => 'is-neutral',
            'icon' => 'ph-circle',
        ],
    };

    $providerRateForUnit = $providerService
        ? match ($order->unit) {
            'hora' => $providerService->provider_hourly_rate,
            'diaria', 'dia' => $providerService->provider_daily_rate,
            default => $providerService->provider_unit_rate,
        }
        : 0;

    $clientName = '-';
    $clientType = 'Avulso';

    if ($order->associate_id) {
        $clientName =
            optional(optional($order->associate)->user)->name
            ?? optional($order->associate)->name
            ?? '-';

        $clientType = 'Associado';
    } else {
        if (
            preg_match(
                '/\[PESSOA AVULSA\]\nNome:\s*(.+)/m',
                $order->notes ?? '',
                $matches
            )
        ) {
            $clientName = trim($matches[1]);
        }
    }

    $hasExecutedValues =
        (bool) $order->actual_quantity
        && (float) $order->final_price > 0;

    $paymentsCount =
        $order->payments?->count() ?? 0;

    $formatMoney = static fn ($value): string =>
        'R$ ' . number_format(
            (float) ($value ?? 0),
            2,
            ',',
            '.'
        );

    $formatQty = static fn ($value, int $decimals = 1): string =>
        number_format(
            (float) ($value ?? 0),
            $decimals,
            ',',
            '.'
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
    .service-order {
        --so-green: #219653;
        --so-green-dark: #177c43;
        --so-green-soft: #edf8f1;
        --so-green-border: #cde8d6;

        --so-blue: #3478d4;
        --so-blue-soft: #eef4ff;
        --so-blue-border: #d4e2f8;

        --so-violet: #8a4bd2;
        --so-violet-soft: #f5effc;
        --so-violet-border: #e5d8f5;

        --so-cyan: #168eae;
        --so-cyan-soft: #edf8fb;
        --so-cyan-border: #d2eaf0;

        --so-amber: #c38418;
        --so-amber-soft: #fff7e8;
        --so-amber-border: #efdcb8;

        --so-red: #cf5050;
        --so-red-soft: #fff1f1;
        --so-red-border: #f1cccc;

        --so-slate: #64748b;
        --so-slate-soft: #f2f5f7;

        --so-text: var(--color-text, #17251c);
        --so-text-2: var(--color-text-secondary, #58685e);
        --so-muted: var(--color-text-muted, #87938b);
        --so-border: var(--color-border, #d7e2da);
        --so-border-strong: var(--color-border-strong, #becdc3);
        --so-surface: var(--color-surface, #fff);
        --so-soft: var(--color-surface-soft, #f7faf8);
        --so-shadow: 0 5px 18px rgba(25, 61, 39, .055);

        display: grid;
        width: min(100%, 1380px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .72rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--so-text);
    }

    .service-order *,
    .service-order *::before,
    .service-order *::after {
        box-sizing: border-box;
    }

    /* =========================================================
       SUPERFÍCIES
       ========================================================= */

    .so-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--so-border);
        border-radius: 12px;
        background: var(--so-surface);
        box-shadow: var(--so-shadow);
    }

    .so-panel-head {
        display: flex;
        min-width: 0;
        min-height: 62px;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        padding: .65rem .72rem;
        border-bottom: 1px solid var(--so-border);
        background:
            linear-gradient(
                180deg,
                #fafcfb,
                #fff
            );
    }

    .so-panel-title {
        display: flex;
        min-width: 0;
        gap: .58rem;
        align-items: center;
    }

    .so-panel-icon {
        display: grid;
        width: 39px;
        height: 39px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 9px;
        background: var(--panel-soft, var(--so-blue-soft));
        color: var(--panel-tone, var(--so-blue));
    }

    .so-panel-copy {
        min-width: 0;
    }

    .so-panel-copy h2,
    .so-panel-copy p {
        margin: 0;
    }

    .so-panel-copy h2 {
        color: var(--so-text);
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .so-panel-copy p {
        margin-top: .08rem;
        color: var(--so-muted);
        font-size: .69rem;
        line-height: 1.35;
    }

    /* =========================================================
       HEADER DA ORDEM
       ========================================================= */

    .so-header {
        --status-tone: var(--so-slate);
        --status-soft: var(--so-slate-soft);

        display: grid;
        grid-template-columns:
            minmax(0, 1fr)
            auto;
        gap: .72rem;
        align-items: center;
        min-height: 82px;
        padding: .76rem .8rem;
        border: 1px solid var(--so-border);
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
        box-shadow: var(--so-shadow);
    }

    .so-header.status-scheduled {
        --status-tone: var(--so-blue);
        --status-soft: var(--so-blue-soft);
    }

    .so-header.status-in_progress {
        --status-tone: var(--so-cyan);
        --status-soft: var(--so-cyan-soft);
    }

    .so-header.status-awaiting_payment {
        --status-tone: var(--so-amber);
        --status-soft: var(--so-amber-soft);
    }

    .so-header.status-completed,
    .so-header.status-paid {
        --status-tone: var(--so-green);
        --status-soft: var(--so-green-soft);
    }

    .so-header.status-cancelled {
        --status-tone: var(--so-red);
        --status-soft: var(--so-red-soft);
    }

    .so-header.status-billed {
        --status-tone: var(--so-violet);
        --status-soft: var(--so-violet-soft);
    }

    .so-header-main {
        display: flex;
        min-width: 0;
        gap: .62rem;
        align-items: center;
    }

    .so-back,
    .so-header-icon {
        display: grid;
        width: 42px;
        height: 42px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 9px;
    }

    .so-back {
        border: 1px solid var(--so-border);
        background: #fff;
        color: var(--so-text-2);
        text-decoration: none;
        transition: .14s ease;
    }

    .so-back:hover,
    .so-back:focus-visible {
        border-color: var(--so-blue-border);
        background: var(--so-blue-soft);
        color: var(--so-blue);
        outline: none;
    }

    .so-header-icon {
        background: var(--status-soft);
        color: var(--status-tone);
    }

    .so-header-copy {
        min-width: 0;
    }

    .so-header-copy h1,
    .so-header-copy p {
        margin: 0;
    }

    .so-header-copy h1 {
        color: var(--so-text);
        font-size: clamp(1.05rem, 2vw, 1.3rem);
        font-weight: 860;
        letter-spacing: -.03em;
        line-height: 1.25;
    }

    .so-header-copy p {
        margin-top: .12rem;
        color: var(--so-muted);
        font-size: .69rem;
        line-height: 1.4;
    }

    .so-header-side {
        display: flex;
        gap: .4rem;
        align-items: center;
    }

    .so-status {
        display: inline-flex;
        min-height: 32px;
        gap: .28rem;
        align-items: center;
        padding: .3rem .5rem;
        border: 1px solid
            color-mix(
                in srgb,
                var(--status-tone) 17%,
                transparent
            );
        border-radius: 999px;
        background: var(--status-soft);
        color: var(--status-tone);
        font-size: .67rem;
        font-weight: 820;
        white-space: nowrap;
    }

    /* =========================================================
       ALERTAS
       ========================================================= */

    .so-alert {
        --alert-tone: var(--so-blue);
        --alert-soft: var(--so-blue-soft);
        --alert-border: var(--so-blue-border);

        display: flex;
        min-width: 0;
        gap: .5rem;
        align-items: flex-start;
        padding: .58rem .65rem;
        border: 1px solid var(--alert-border);
        border-radius: 9px;
        background: var(--alert-soft);
        color: var(--so-text-2);
    }

    .so-alert.success {
        --alert-tone: var(--so-green);
        --alert-soft: var(--so-green-soft);
        --alert-border: var(--so-green-border);
    }

    .so-alert.warning {
        --alert-tone: var(--so-amber);
        --alert-soft: var(--so-amber-soft);
        --alert-border: var(--so-amber-border);
    }

    .so-alert.danger {
        --alert-tone: var(--so-red);
        --alert-soft: var(--so-red-soft);
        --alert-border: var(--so-red-border);
    }

    .so-alert-icon {
        display: grid;
        width: 30px;
        height: 30px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 7px;
        background: #fff;
        color: var(--alert-tone);
    }

    .so-alert-copy {
        min-width: 0;
        font-size: .69rem;
        line-height: 1.45;
    }

    .so-alert-copy strong {
        color: var(--so-text);
    }

    .so-alert-action {
        margin-left: auto;
        flex: 0 0 auto;
    }

    /* =========================================================
       BOTÕES
       ========================================================= */

    .so-btn {
        display: inline-flex;
        min-height: 39px;
        gap: .34rem;
        align-items: center;
        justify-content: center;
        padding: .44rem .64rem;
        border: 1px solid var(--so-border-strong);
        border-radius: 8px;
        background: #fff;
        color: var(--so-text);
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 790;
        text-decoration: none;
        transition: .14s ease;
        white-space: nowrap;
    }

    .so-btn:hover,
    .so-btn:focus-visible {
        border-color: var(--so-blue-border);
        background: var(--so-blue-soft);
        color: var(--so-blue);
        outline: none;
    }

    .so-btn.primary {
        border-color: var(--so-green-dark);
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

    .so-btn.primary:hover,
    .so-btn.primary:focus-visible {
        border-color: var(--so-green-dark);
        background: var(--so-green-dark);
        color: #fff;
    }

    .so-btn.warning {
        border-color: var(--so-amber);
        background:
            linear-gradient(
                180deg,
                #d79827,
                #be7912
            );
        color: #fff;
    }

    .so-btn.danger {
        border-color: var(--so-red);
        color: var(--so-red);
    }

    .so-icon-btn {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border: 1px solid var(--so-border);
        border-radius: 7px;
        background: #fff;
        color: var(--so-red);
        cursor: pointer;
    }

    /* =========================================================
       LAYOUT PRINCIPAL
       ========================================================= */

    .so-main-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1.2fr)
            minmax(330px, .8fr);
        gap: .72rem;
        align-items: start;
    }

    /* =========================================================
       TABELA DE INFORMAÇÕES
       ========================================================= */

    .info-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .7rem;
    }

    .info-table th,
    .info-table td {
        padding: .52rem .62rem;
        border-bottom: 1px solid var(--so-border);
        vertical-align: middle;
    }

    .info-table tr:last-child th,
    .info-table tr:last-child td {
        border-bottom: 0;
    }

    .info-table th {
        width: 165px;
        background: #fbfdfc;
        color: var(--so-muted);
        font-size: .6rem;
        font-weight: 800;
        letter-spacing: .025em;
        text-align: left;
        text-transform: uppercase;
    }

    .info-table td {
        color: var(--so-text);
        font-weight: 720;
    }

    .info-inline {
        display: inline-flex;
        max-width: 100%;
        gap: .3rem;
        align-items: center;
    }

    .mini-badge {
        display: inline-flex;
        min-height: 22px;
        align-items: center;
        padding: .16rem .34rem;
        border-radius: 999px;
        background: var(--so-slate-soft);
        color: var(--so-slate);
        font-size: .56rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .mini-badge.associate {
        background: var(--so-green-soft);
        color: var(--so-green);
    }

    /* =========================================================
       VALORES — TABELA, NÃO CARDS
       ========================================================= */

    .money-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .69rem;
    }

    .money-table th,
    .money-table td {
        padding: .54rem .62rem;
        border-bottom: 1px solid var(--so-border);
        vertical-align: middle;
    }

    .money-table tbody tr:last-child th,
    .money-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .money-table th {
        color: var(--so-text-2);
        font-size: .65rem;
        font-weight: 720;
        text-align: left;
    }

    .money-table td {
        text-align: right;
    }

    .money-value {
        display: block;
        color: var(--so-text);
        font-size: .82rem;
        font-weight: 850;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .money-value.client {
        color: var(--so-amber);
    }

    .money-value.provider {
        color: var(--so-green);
    }

    .money-value.coop {
        color: var(--so-violet);
    }

    .money-note {
        display: block;
        margin-top: .06rem;
        color: var(--so-muted);
        font-size: .57rem;
        font-weight: 620;
        line-height: 1.35;
    }

    .payment-state {
        display: inline-flex;
        width: max-content;
        min-height: 22px;
        gap: .24rem;
        align-items: center;
        padding: .16rem .34rem;
        border-radius: 999px;
        background: var(--so-amber-soft);
        color: #8e5c0d;
        font-size: .56rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .payment-state.paid {
        background: var(--so-green-soft);
        color: var(--so-green);
    }

    /* =========================================================
       TEXTO / ANEXO
       ========================================================= */

    .text-section {
        padding: .68rem .72rem;
        color: var(--so-text-2);
        font-size: .72rem;
        line-height: 1.55;
        overflow-wrap: anywhere;
        white-space: pre-wrap;
    }

    .attachment-row {
        display: grid;
        grid-template-columns:
            auto
            minmax(0, 1fr)
            auto;
        gap: .45rem;
        align-items: center;
        margin-top: .58rem;
        padding: .48rem .55rem;
        border: 1px solid var(--so-border);
        border-radius: 8px;
        background: var(--so-soft);
    }

    .attachment-row > i {
        color: var(--so-violet);
        font-size: .92rem;
    }

    .attachment-name {
        min-width: 0;
        overflow: hidden;
        color: var(--so-text);
        font-size: .65rem;
        font-weight: 720;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* =========================================================
       PAGAMENTOS
       ========================================================= */

    .payments-wrap {
        min-width: 0;
        overflow-x: auto;
    }

    .payments-table {
        width: 100%;
        min-width: 720px;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
        font-size: .68rem;
    }

    .payments-table th {
        padding: .52rem .6rem;
        border-bottom: 1px solid var(--so-border-strong);
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

    .payments-table td {
        padding: .54rem .6rem;
        border-bottom: 1px solid var(--so-border);
        color: var(--so-text-2);
        vertical-align: middle;
    }

    .payments-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .payment-type {
        display: inline-flex;
        min-height: 23px;
        align-items: center;
        padding: .18rem .36rem;
        border-radius: 999px;
        font-size: .57rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .payment-type.client {
        background: var(--so-amber-soft);
        color: #8c5a11;
    }

    .payment-type.provider {
        background: var(--so-green-soft);
        color: var(--so-green);
    }

    .payment-amount {
        color: var(--so-text);
        font-weight: 820;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .table-link {
        display: inline-flex;
        gap: .25rem;
        align-items: center;
        color: var(--so-blue);
        font-size: .63rem;
        font-weight: 760;
        text-decoration: none;
    }

    /* =========================================================
       AÇÕES DE FLUXO
       ========================================================= */

    .flow-action {
        display: flex;
        min-width: 0;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .72rem;
    }

    .flow-copy {
        min-width: 0;
    }

    .flow-copy strong,
    .flow-copy span {
        display: block;
    }

    .flow-copy strong {
        color: var(--so-text);
        font-size: .76rem;
        font-weight: 820;
    }

    .flow-copy span {
        margin-top: .08rem;
        color: var(--so-muted);
        font-size: .67rem;
        line-height: 1.42;
    }

    /* =========================================================
       FORMULÁRIO DE CONCLUSÃO
       ========================================================= */

    .complete-body {
        padding: .72rem;
    }

    .complete-guide {
        display: flex;
        gap: .5rem;
        align-items: flex-start;
        margin-bottom: .7rem;
        padding: .56rem .62rem;
        border: 1px solid var(--so-amber-border);
        border-radius: 9px;
        background: var(--so-amber-soft);
        color: #84540e;
        font-size: .68rem;
        line-height: 1.48;
    }

    .complete-guide > i {
        flex: 0 0 auto;
        margin-top: .02rem;
        color: var(--so-amber);
        font-size: .92rem;
    }

    .complete-guide strong {
        color: #704307;
    }

    .form-table {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: .58rem;
    }

    .form-field {
        min-width: 0;
    }

    .form-field.full {
        grid-column: 1 / -1;
    }

    .form-label {
        display: block;
        margin-bottom: .2rem;
        color: var(--so-text-2);
        font-size: .62rem;
        font-weight: 770;
    }

    .form-input,
    .form-textarea,
    .form-select {
        width: 100%;
        min-height: 40px;
        border: 1px solid var(--so-border-strong);
        border-radius: 8px;
        outline: none;
        background: #fff;
        color: var(--so-text);
        font: inherit;
        font-size: .71rem;
    }

    .form-input,
    .form-select {
        padding: .48rem .56rem;
    }

    .form-textarea {
        min-height: 88px;
        padding: .52rem .56rem;
        resize: vertical;
    }

    .form-input:focus,
    .form-textarea:focus,
    .form-select:focus {
        border-color: var(--so-blue);
        box-shadow:
            0 0 0 3px rgba(52, 120, 212, .1);
    }

    .field-help {
        display: block;
        margin-top: .18rem;
        color: var(--so-muted);
        font-size: .59rem;
        line-height: 1.4;
    }

    .calc-preview {
        display: flex;
        flex-wrap: wrap;
        gap: .3rem;
        margin-top: .32rem;
    }

    .calc-chip {
        display: inline-flex;
        min-height: 25px;
        gap: .24rem;
        align-items: center;
        padding: .2rem .36rem;
        border-radius: 7px;
        background: var(--so-soft);
        color: var(--so-text-2);
        font-size: .58rem;
        font-weight: 720;
    }

    .calc-chip.client {
        background: var(--so-amber-soft);
        color: #8c5a11;
    }

    .calc-chip.provider {
        background: var(--so-green-soft);
        color: var(--so-green);
    }

    /* =========================================================
       ADICIONAIS
       ========================================================= */

    .additions-section {
        margin-top: .85rem;
        padding-top: .75rem;
        border-top: 1px solid var(--so-border);
    }

    .additions-head {
        display: flex;
        gap: .6rem;
        align-items: center;
        justify-content: space-between;
        margin-bottom: .35rem;
    }

    .additions-title {
        display: flex;
        gap: .35rem;
        align-items: center;
        color: var(--so-text);
        font-size: .76rem;
        font-weight: 830;
    }

    .additions-help {
        margin: 0 0 .6rem;
        color: var(--so-muted);
        font-size: .61rem;
        line-height: 1.45;
    }

    .additions-table-head,
    .addition-item {
        display: grid;
        grid-template-columns:
            125px
            minmax(180px, 1fr)
            120px
            minmax(170px, .8fr)
            36px;
        gap: .42rem;
        align-items: center;
    }

    .additions-table-head {
        min-height: 34px;
        padding: .3rem .45rem;
        border: 1px solid var(--so-border);
        border-bottom: 0;
        border-radius: 8px 8px 0 0;
        background:
            linear-gradient(
                180deg,
                #f5f8f6,
                #eff4f1
            );
        color: #6f7c74;
        font-size: .55rem;
        font-weight: 810;
        letter-spacing: .035em;
        text-transform: uppercase;
    }

    .additions-table-head.is-empty {
        display: none;
    }

    #additions-container {
        display: grid;
    }

    .addition-item {
        padding: .46rem;
        border: 1px solid var(--so-border);
        border-bottom: 0;
        background: #fff;
    }

    .addition-item:first-child {
        border-radius: 0;
    }

    .addition-item:last-child {
        border-bottom: 1px solid var(--so-border);
        border-radius: 0 0 8px 8px;
    }

    .addition-item .form-label {
        display: none;
    }

    .addition-item .form-input,
    .addition-item .form-select {
        min-height: 36px;
        padding: .4rem .46rem;
        font-size: .66rem;
    }

    .addition-remove-wrap {
        display: grid;
        place-items: center;
    }

    .additions-summary {
        display: none;
        margin-top: .52rem;
        overflow: hidden;
        border: 1px solid var(--so-border);
        border-radius: 8px;
        background: var(--so-soft);
    }

    .additions-summary-row {
        display: grid;
        grid-template-columns:
            repeat(3, minmax(0, 1fr));
    }

    .addition-summary-item {
        min-width: 0;
        padding: .5rem .56rem;
    }

    .addition-summary-item +
    .addition-summary-item {
        border-left: 1px solid var(--so-border);
    }

    .addition-summary-item span,
    .addition-summary-item strong {
        display: block;
    }

    .addition-summary-item span {
        color: var(--so-muted);
        font-size: .58rem;
        font-weight: 720;
    }

    .addition-summary-item strong {
        margin-top: .08rem;
        color: var(--so-text);
        font-size: .72rem;
        font-weight: 840;
    }

    .addition-summary-item.fee strong {
        color: var(--so-red);
    }

    .addition-summary-item.discount strong {
        color: var(--so-green);
    }

    .complete-actions {
        display: flex;
        gap: .45rem;
        align-items: center;
        justify-content: flex-end;
        margin-top: .85rem;
        padding-top: .72rem;
        border-top: 1px solid var(--so-border);
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 980px) {
        .so-main-grid {
            grid-template-columns: 1fr;
        }

        .additions-table-head,
        .addition-item {
            grid-template-columns:
                110px
                minmax(150px, 1fr)
                105px
                minmax(140px, .8fr)
                34px;
        }
    }

    @media (max-width: 760px) {
        .so-header {
            grid-template-columns: 1fr;
        }

        .so-header-side {
            justify-content: flex-start;
        }

        .so-panel-copy p {
            display: none;
        }

        .payments-wrap {
            overflow: visible;
            padding: .55rem;
        }

        .payments-table {
            display: block;
            min-width: 0;
        }

        .payments-table thead {
            display: none;
        }

        .payments-table tbody {
            display: grid;
            gap: .45rem;
        }

        .payments-table tr {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: .38rem;
            padding: .52rem;
            border: 1px solid var(--so-border);
            border-left: 3px solid var(--so-green);
            border-radius: 8px;
            background: #fff;
        }

        .payments-table td {
            display: grid;
            gap: .05rem;
            padding: 0;
            border: 0;
        }

        .payments-table td::before {
            color: var(--so-muted);
            content: attr(data-label);
            font-size: .53rem;
            font-weight: 770;
            text-transform: uppercase;
        }

        .form-table {
            grid-template-columns: 1fr;
        }

        .form-field.full {
            grid-column: 1;
        }

        .additions-table-head {
            display: none !important;
        }

        .addition-item {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: .42rem;
            margin-bottom: .45rem;
            padding: .55rem;
            border: 1px solid var(--so-border);
            border-left: 3px solid var(--so-violet);
            border-radius: 8px !important;
        }

        .addition-item > div {
            min-width: 0;
        }

        .addition-item .form-label {
            display: block;
        }

        .addition-item > div:nth-child(2),
        .addition-item > div:nth-child(4) {
            grid-column: 1 / -1;
        }

        .addition-remove-wrap {
            align-self: end;
            justify-self: end;
        }
    }

    @media (max-width: 560px) {
        .so-header-icon {
            display: none;
        }

        .so-header-copy h1 {
            font-size: 1.02rem;
        }

        .so-alert {
            display: grid;
            grid-template-columns:
                auto
                minmax(0, 1fr);
        }

        .so-alert-action {
            grid-column: 1 / -1;
            margin-left: 0;
        }

        .so-alert-action .so-btn {
            width: 100%;
        }

        .info-table,
        .money-table {
            display: block;
        }

        .info-table tbody,
        .money-table tbody {
            display: grid;
        }

        .info-table tr,
        .money-table tr {
            display: grid;
            grid-template-columns: 1fr;
            padding: .45rem .55rem;
            border-bottom: 1px solid var(--so-border);
        }

        .info-table tr:last-child,
        .money-table tr:last-child {
            border-bottom: 0;
        }

        .info-table th,
        .info-table td,
        .money-table th,
        .money-table td {
            width: auto;
            padding: 0;
            border: 0;
            background: transparent;
            text-align: left;
        }

        .info-table th,
        .money-table th {
            margin-bottom: .08rem;
        }

        .money-table td {
            margin-top: .05rem;
        }

        .money-value {
            white-space: normal;
        }

        .attachment-row {
            grid-template-columns:
                auto
                minmax(0, 1fr);
        }

        .attachment-row .so-btn {
            grid-column: 1 / -1;
            width: 100%;
        }

        .flow-action {
            align-items: stretch;
            flex-direction: column;
        }

        .flow-action .so-btn {
            width: 100%;
        }

        .additions-summary-row {
            grid-template-columns: 1fr;
        }

        .addition-summary-item +
        .addition-summary-item {
            border-top: 1px solid var(--so-border);
            border-left: 0;
        }

        .complete-actions {
            display: grid;
            grid-template-columns: 1fr;
        }

        .complete-actions .so-btn {
            width: 100%;
        }
    }

    @media (max-width: 420px) {
        .so-header-main {
            align-items: flex-start;
        }

        .so-back {
            width: 38px;
            height: 38px;
        }

        .addition-item {
            grid-template-columns: 1fr;
        }

        .addition-item > div:nth-child(2),
        .addition-item > div:nth-child(4) {
            grid-column: 1;
        }

        .addition-remove-wrap {
            justify-self: start;
        }

        .payments-table tr {
            grid-template-columns: 1fr;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .service-order *,
        .service-order *::before,
        .service-order *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<main class="service-order">
    {{-- =========================================================
         CABEÇALHO
         ========================================================= --}}
    <header
        class="
            so-header
            status-{{ $orderStatusValue }}
        "
    >
        <div class="so-header-main">
            <a
                class="so-back"
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
                class="so-header-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-clipboard-text"></i>
            </span>

            <div class="so-header-copy">
                <h1>
                    Ordem #{{ $order->number }}
                </h1>

                <p>
                    Criada em
                    {{ $order->created_at->format('d/m/Y H:i') }}
                    ·
                    {{ optional($order->service)->name
                        ?? 'Serviço não informado' }}
                </p>
            </div>
        </div>

        <div class="so-header-side">
            <span
                class="
                    so-status
                    {{ $orderStatusMeta['class'] }}
                "
            >
                <i
                    class="
                        ph-fill
                        {{ $orderStatusMeta['icon'] }}
                    "
                ></i>

                {{ $orderStatusLabel }}
            </span>
        </div>
    </header>

    {{-- =========================================================
         FEEDBACK
         ========================================================= --}}
    @if(
        session('success')
        && session('success') !== $orderStatusLabel
    )
        <div class="so-alert success">
            <span
                class="so-alert-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-check-circle"></i>
            </span>

            <div class="so-alert-copy">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="so-alert danger">
            <span
                class="so-alert-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-warning-circle"></i>
            </span>

            <div class="so-alert-copy">
                {{ session('error') }}
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="so-alert danger">
            <span
                class="so-alert-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-warning-circle"></i>
            </span>

            <div class="so-alert-copy">
                <strong>
                    Verifique os dados informados:
                </strong>

                @foreach($errors->all() as $error)
                    <div>
                        {{ $error }}
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- =========================================================
         DETALHES + VALORES
         ========================================================= --}}
    <div class="so-main-grid">
        <section class="so-panel">
            <header
                class="so-panel-head"
                style="
                    --panel-tone:var(--so-blue);
                    --panel-soft:var(--so-blue-soft);
                "
            >
                <div class="so-panel-title">
                    <span
                        class="so-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-list-checks"></i>
                    </span>

                    <div class="so-panel-copy">
                        <h2>Dados da ordem</h2>
                        <p>
                            Serviço, cliente, local e execução.
                        </p>
                    </div>
                </div>
            </header>

            <table
                class="info-table"
                aria-label="Dados da ordem de serviço"
            >
                <tbody>
                    <tr>
                        <th>Serviço</th>
                        <td>
                            {{ optional($order->service)->name
                                ?? 'N/A' }}
                        </td>
                    </tr>

                    <tr>
                        <th>Unidade</th>
                        <td>
                            {{ $order->unit }}
                        </td>
                    </tr>

                    <tr>
                        <th>Data agendada</th>
                        <td>
                            {{ $order->scheduled_date
                                ?->format('d/m/Y')
                                ?? '-' }}
                        </td>
                    </tr>

                    <tr>
                        <th>Local</th>
                        <td>
                            {{ $order->location ?? '-' }}
                        </td>
                    </tr>

                    <tr>
                        <th>Cliente</th>
                        <td>
                            <span class="info-inline">
                                <span>
                                    {{ $clientName }}
                                </span>

                                <span
                                    class="
                                        mini-badge
                                        {{ $order->associate_id
                                            ? 'associate'
                                            : '' }}
                                    "
                                >
                                    {{ $clientType }}
                                </span>
                            </span>
                        </td>
                    </tr>

                    @if($order->asset)
                        <tr>
                            <th>Equipamento</th>
                            <td>
                                {{ $order->asset->name }}
                            </td>
                        </tr>
                    @endif

                    @if($order->execution_date)
                        <tr>
                            <th>Data da execução</th>
                            <td>
                                {{ $order->execution_date
                                    ->format('d/m/Y') }}
                            </td>
                        </tr>
                    @endif

                    @if($order->actual_quantity)
                        <tr>
                            <th>Quantidade executada</th>
                            <td>
                                {{ $formatQty(
                                    $order->actual_quantity
                                ) }}
                                {{ $order->unit }}
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </section>

        <section class="so-panel">
            <header
                class="so-panel-head"
                style="
                    --panel-tone:var(--so-green);
                    --panel-soft:var(--so-green-soft);
                "
            >
                <div class="so-panel-title">
                    <span
                        class="so-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-calculator"></i>
                    </span>

                    <div class="so-panel-copy">
                        <h2>Valores</h2>
                        <p>
                            Cobrança do cliente e repasse do prestador.
                        </p>
                    </div>
                </div>
            </header>

            <table
                class="money-table"
                aria-label="Valores da ordem de serviço"
            >
                <tbody>
                    @if($hasExecutedValues)
                        <tr>
                            <th>
                                Cliente paga

                                <span class="money-note">
                                    {{ $formatQty(
                                        $order->actual_quantity
                                    ) }}
                                    {{ $order->unit }}
                                    ×
                                    {{ $formatMoney(
                                        $order->unit_price
                                    ) }}
                                </span>
                            </th>

                            <td>
                                <span class="money-value client">
                                    {{ $formatMoney(
                                        $order->final_price
                                    ) }}
                                </span>

                                <span
                                    class="
                                        payment-state
                                        {{ $order->associate_payment_status?->value === 'paid'
                                            ? 'paid'
                                            : '' }}
                                    "
                                >
                                    <i
                                        class="
                                            ph-fill
                                            {{ $order->associate_payment_status?->value === 'paid'
                                                ? 'ph-check-circle'
                                                : 'ph-clock' }}
                                        "
                                    ></i>

                                    {{ $order->associate_payment_status?->value === 'paid'
                                        ? 'Pago'
                                        : 'Pendente' }}
                                </span>
                            </td>
                        </tr>

                        <tr>
                            <th>
                                Você recebe

                                <span class="money-note">
                                    {{ $formatQty(
                                        $order->actual_quantity
                                    ) }}
                                    {{ $order->unit }}
                                    ×
                                    {{ $formatMoney(
                                        $providerRateForUnit
                                    ) }}
                                </span>
                            </th>

                            <td>
                                <span class="money-value provider">
                                    {{ $formatMoney(
                                        $order->provider_payment
                                    ) }}
                                </span>

                                <span
                                    class="
                                        payment-state
                                        {{ $order->provider_payment_status?->value === 'paid'
                                            ? 'paid'
                                            : '' }}
                                    "
                                >
                                    <i
                                        class="
                                            ph-fill
                                            {{ $order->provider_payment_status?->value === 'paid'
                                                ? 'ph-check-circle'
                                                : 'ph-clock' }}
                                        "
                                    ></i>

                                    {{ $order->provider_payment_status?->value === 'paid'
                                        ? 'Pago'
                                        : 'Aguardando' }}
                                </span>
                            </td>
                        </tr>

                        @if($order->cooperative_profit > 0)
                            <tr>
                                <th>
                                    Cooperativa
                                </th>

                                <td>
                                    <span class="money-value coop">
                                        {{ $formatMoney(
                                            $order->cooperative_profit
                                        ) }}
                                    </span>
                                </td>
                            </tr>
                        @endif
                    @else
                        <tr>
                            <th>
                                Preço unitário do cliente
                            </th>

                            <td>
                                <span class="money-value client">
                                    {{ $formatMoney(
                                        $order->unit_price ?? 0
                                    ) }}
                                    /{{ $order->unit }}
                                </span>
                            </td>
                        </tr>

                        @if($providerService)
                            <tr>
                                <th>
                                    Sua taxa
                                </th>

                                <td>
                                    <span class="money-value provider">
                                        {{ $formatMoney(
                                            $providerRateForUnit
                                        ) }}
                                        /{{ $order->unit }}
                                    </span>
                                </td>
                            </tr>
                        @endif

                        <tr>
                            <th>
                                Situação
                            </th>

                            <td>
                                <span class="money-note">
                                    Os valores finais serão calculados
                                    quando o serviço for concluído.
                                </span>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </section>
    </div>

    {{-- =========================================================
         EXECUÇÃO / OBSERVAÇÕES
         ========================================================= --}}
    @if($order->work_description)
        <section class="so-panel">
            <header
                class="so-panel-head"
                style="
                    --panel-tone:var(--so-cyan);
                    --panel-soft:var(--so-cyan-soft);
                "
            >
                <div class="so-panel-title">
                    <span
                        class="so-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-file-text"></i>
                    </span>

                    <div class="so-panel-copy">
                        <h2>Descrição da execução</h2>
                        <p>
                            Registro informado na conclusão do serviço.
                        </p>
                    </div>
                </div>
            </header>

            <div class="text-section">
                {{ $order->work_description }}

                @if($order->receipt_path)
                    <div class="attachment-row">
                        <i class="ph-fill ph-paperclip"></i>

                        <span class="attachment-name">
                            {{ basename(
                                $order->receipt_path
                            ) }}
                        </span>

                        <a
                            class="so-btn"
                            href="{{ Storage::url(
                                $order->receipt_path
                            ) }}"
                            target="_blank"
                            rel="noopener"
                        >
                            <i class="ph ph-eye"></i>
                            Ver comprovante
                        </a>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if(
        $order->notes
        && !str_contains(
            $order->notes,
            '[PESSOA AVULSA]'
        )
    )
        <section class="so-panel">
            <header
                class="so-panel-head"
                style="
                    --panel-tone:var(--so-violet);
                    --panel-soft:var(--so-violet-soft);
                "
            >
                <div class="so-panel-title">
                    <span
                        class="so-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-note"></i>
                    </span>

                    <div class="so-panel-copy">
                        <h2>Observações</h2>
                    </div>
                </div>
            </header>

            <div class="text-section">
                {{ $order->notes }}
            </div>
        </section>
    @endif

    {{-- =========================================================
         PAGAMENTOS
         ========================================================= --}}
    @if($paymentsCount > 0)
        <section class="so-panel">
            <header
                class="so-panel-head"
                style="
                    --panel-tone:var(--so-green);
                    --panel-soft:var(--so-green-soft);
                "
            >
                <div class="so-panel-title">
                    <span
                        class="so-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-receipt"></i>
                    </span>

                    <div class="so-panel-copy">
                        <h2>Pagamentos registrados</h2>
                        <p>
                            Histórico financeiro vinculado à ordem.
                        </p>
                    </div>
                </div>

                <span class="mini-badge">
                    {{ $paymentsCount }}
                    {{ $paymentsCount === 1
                        ? 'registro'
                        : 'registros' }}
                </span>
            </header>

            <div class="payments-wrap">
                <table
                    class="payments-table"
                    aria-label="Pagamentos registrados"
                >
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Método</th>
                            <th>Comprovante</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($order->payments as $payment)
                            <tr>
                                <td data-label="Data">
                                    {{ $payment->payment_date
                                        ?->format('d/m/Y')
                                        ?? '-' }}
                                </td>

                                <td data-label="Tipo">
                                    @if($payment->type === 'client')
                                        <span
                                            class="
                                                payment-type
                                                client
                                            "
                                        >
                                            Recebido do cliente
                                        </span>
                                    @else
                                        <span
                                            class="
                                                payment-type
                                                provider
                                            "
                                        >
                                            Pago ao prestador
                                        </span>
                                    @endif
                                </td>

                                <td data-label="Valor">
                                    <span class="payment-amount">
                                        {{ $formatMoney(
                                            $payment->amount
                                        ) }}
                                    </span>
                                </td>

                                <td data-label="Método">
                                    {{ $payment->payment_method
                                        ?->getLabel()
                                        ?? '-' }}
                                </td>

                                <td data-label="Comprovante">
                                    @if($payment->receipt_path)
                                        <a
                                            class="table-link"
                                            href="{{ Storage::url(
                                                $payment->receipt_path
                                            ) }}"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <i class="ph ph-eye"></i>
                                            Ver
                                        </a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- =========================================================
         INICIAR EXECUÇÃO
         ========================================================= --}}
    @if($orderStatusValue === 'scheduled')
        <section class="so-panel">
            <form
                method="POST"
                action="{{ route(
                    'provider.orders.start',
                    [
                        'tenant' => $tenantSlug,
                        'order' => $order->id,
                    ]
                ) }}"
            >
                @csrf

                <div class="flow-action">
                    <div class="flow-copy">
                        <strong>
                            Serviço agendado
                        </strong>

                        <span>
                            Inicie a execução somente quando
                            o trabalho realmente começar.
                        </span>
                    </div>

                    <button
                        type="submit"
                        class="so-btn primary"
                    >
                        <i class="ph-fill ph-play"></i>
                        Iniciar execução
                    </button>
                </div>
            </form>
        </section>
    @endif

    {{-- =========================================================
         CONCLUSÃO DA EXECUÇÃO
         ========================================================= --}}
    @if($orderStatusValue === 'in_progress')
        <section
            class="so-panel"
            id="complete"
        >
            <header
                class="so-panel-head"
                style="
                    --panel-tone:var(--so-green);
                    --panel-soft:var(--so-green-soft);
                "
            >
                <div class="so-panel-title">
                    <span
                        class="so-panel-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-check-circle"></i>
                    </span>

                    <div class="so-panel-copy">
                        <h2>Finalizar execução</h2>

                        <p>
                            Registre o realizado e os ajustes financeiros.
                        </p>
                    </div>
                </div>
            </header>

            <div class="complete-body">
                <div class="complete-guide">
                    <i class="ph-fill ph-info"></i>

                    <div>
                        <strong>Informe a quantidade trabalhada.</strong>
                        O sistema calcula os valores automaticamente
                        usando as tabelas de preço.

                        @if($providerService)
                            <div style="margin-top:.2rem">
                                Cliente:
                                <strong>
                                    {{ $formatMoney(
                                        $order->unit_price ?? 0
                                    ) }}
                                    /{{ $order->unit }}
                                </strong>

                                ·

                                Prestador:
                                <strong>
                                    {{ $formatMoney(
                                        $providerRateForUnit
                                    ) }}
                                    /{{ $order->unit }}
                                </strong>
                            </div>
                        @endif
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route(
                        'provider.orders.complete',
                        [
                            'tenant' => $tenantSlug,
                            'order' => $order->id,
                        ]
                    ) }}"
                    enctype="multipart/form-data"
                >
                    @csrf

                    <div class="form-table">
                        <div class="form-field">
                            <label
                                class="form-label"
                                for="execution_date"
                            >
                                Data de execução *
                            </label>

                            <input
                                class="form-input"
                                id="execution_date"
                                type="date"
                                name="execution_date"
                                required
                                value="{{ old(
                                    'execution_date',
                                    date('Y-m-d')
                                ) }}"
                            >
                        </div>

                        <div class="form-field">
                            <label
                                class="form-label"
                                for="actual_quantity"
                            >
                                Quantidade trabalhada
                                ({{ $order->unit }}) *
                            </label>

                            <input
                                class="form-input"
                                id="actual_quantity"
                                type="number"
                                name="actual_quantity"
                                required
                                step="0.1"
                                min="0"
                                value="{{ old(
                                    'actual_quantity',
                                    $order->quantity
                                ) }}"
                                placeholder="Ex: 8"
                            >

                            <div
                                class="calc-preview"
                                id="calc-preview"
                            ></div>
                        </div>

                        @if($order->asset)
                            <div class="form-field">
                                <label
                                    class="form-label"
                                    for="horimeter_start"
                                >
                                    Horímetro inicial
                                </label>

                                <input
                                    class="form-input"
                                    id="horimeter_start"
                                    type="number"
                                    name="horimeter_start"
                                    step="0.1"
                                    value="{{ old(
                                        'horimeter_start'
                                    ) }}"
                                >
                            </div>

                            <div class="form-field">
                                <label
                                    class="form-label"
                                    for="horimeter_end"
                                >
                                    Horímetro final
                                </label>

                                <input
                                    class="form-input"
                                    id="horimeter_end"
                                    type="number"
                                    name="horimeter_end"
                                    step="0.1"
                                    value="{{ old(
                                        'horimeter_end'
                                    ) }}"
                                >
                            </div>

                            <div class="form-field">
                                <label
                                    class="form-label"
                                    for="fuel_used"
                                >
                                    Combustível (L)
                                </label>

                                <input
                                    class="form-input"
                                    id="fuel_used"
                                    type="number"
                                    name="fuel_used"
                                    step="0.1"
                                    value="{{ old(
                                        'fuel_used'
                                    ) }}"
                                >
                            </div>
                        @endif

                        <div class="form-field full">
                            <label
                                class="form-label"
                                for="work_description"
                            >
                                Descrição do trabalho *
                            </label>

                            <textarea
                                class="form-textarea"
                                id="work_description"
                                name="work_description"
                                required
                                rows="3"
                                placeholder="Descreva o serviço realizado..."
                            >{{ old('work_description') }}</textarea>
                        </div>
                    </div>

                    {{-- =========================================
                         DESPESAS, TAXAS E DESCONTOS
                         ========================================= --}}
                    <div class="additions-section">
                        <div class="additions-head">
                            <div class="additions-title">
                                <i class="ph-fill ph-list-plus"></i>
                                Despesas, taxas e descontos
                            </div>

                            <button
                                type="button"
                                id="btn-add-addition"
                                class="so-btn"
                            >
                                <i class="ph ph-plus"></i>
                                Adicionar
                            </button>
                        </div>

                        <p class="additions-help">
                            <strong>Despesa</strong> registra um custo
                            e não altera o valor do cliente.
                            <strong>Taxa</strong> soma ao valor.
                            <strong>Desconto</strong> reduz o valor.
                        </p>

                        <div
                            class="
                                additions-table-head
                                is-empty
                            "
                            id="additions-table-head"
                            aria-hidden="true"
                        >
                            <span>Tipo</span>
                            <span>Descrição</span>
                            <span>Valor</span>
                            <span>Plano de contas</span>
                            <span></span>
                        </div>

                        <div id="additions-container"></div>

                        <div
                            class="additions-summary"
                            id="additions-summary"
                        >
                            <div class="additions-summary-row">
                                <div
                                    class="
                                        addition-summary-item
                                        fee
                                    "
                                >
                                    <span>Taxas</span>

                                    <strong id="summary-fees">
                                        R$ 0,00
                                    </strong>
                                </div>

                                <div
                                    class="
                                        addition-summary-item
                                        discount
                                    "
                                >
                                    <span>Descontos</span>

                                    <strong id="summary-discounts">
                                        R$ 0,00
                                    </strong>
                                </div>

                                <div
                                    class="addition-summary-item"
                                >
                                    <span>Valor ajustado</span>

                                    <strong id="summary-adjusted">
                                        —
                                    </strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template id="addition-template">
                        <div class="addition-item">
                            <div>
                                <label class="form-label">
                                    Tipo *
                                </label>

                                <select
                                    name="additions[__IDX__][type]"
                                    class="
                                        form-select
                                        addition-type
                                    "
                                    required
                                >
                                    <option value="">
                                        Selecione
                                    </option>

                                    <option value="expense">
                                        Despesa
                                    </option>

                                    <option value="fee">
                                        Taxa
                                    </option>

                                    <option value="discount">
                                        Desconto
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="form-label">
                                    Descrição *
                                </label>

                                <input
                                    type="text"
                                    name="additions[__IDX__][description]"
                                    class="form-input"
                                    required
                                    placeholder="Ex: Taxa de deslocamento"
                                >
                            </div>

                            <div>
                                <label class="form-label">
                                    Valor (R$) *
                                </label>

                                <input
                                    type="number"
                                    name="additions[__IDX__][amount]"
                                    class="
                                        form-input
                                        addition-amount
                                    "
                                    required
                                    step="0.01"
                                    min="0.01"
                                    placeholder="0,00"
                                >
                            </div>

                            <div>
                                <label class="form-label">
                                    Plano de contas
                                </label>

                                <select
                                    name="additions[__IDX__][chart_account_id]"
                                    class="form-select"
                                >
                                    <option value="">
                                        Nenhum
                                    </option>

                                    @foreach(
                                        $chartAccounts ?? []
                                        as $id => $name
                                    )
                                        <option value="{{ $id }}">
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="addition-remove-wrap">
                                <button
                                    type="button"
                                    class="
                                        so-icon-btn
                                        btn-remove-addition
                                    "
                                    title="Remover"
                                    aria-label="Remover item"
                                >
                                    <i class="ph ph-trash"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <div class="complete-actions">
                        <a
                            class="so-btn"
                            href="{{ route(
                                'provider.orders',
                                ['tenant' => $tenantSlug]
                            ) }}"
                        >
                            Cancelar
                        </a>

                        <button
                            type="submit"
                            class="so-btn primary"
                        >
                            <i class="ph-fill ph-check-circle"></i>
                            Finalizar execução
                        </button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    {{-- =========================================================
         PAGAMENTO DO CLIENTE / ESTADO FINAL
         ========================================================= --}}
    @if(
        $orderStatusValue === 'awaiting_payment'
        && $order->client_remaining > 0
    )
        <div class="so-alert warning">
            <span
                class="so-alert-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-clock-countdown"></i>
            </span>

            <div class="so-alert-copy">
                <strong>
                    Serviço concluído.
                </strong>

                Registre o pagamento do cliente quando receber.

                Restante:
                <strong>
                    {{ $formatMoney(
                        $order->client_remaining
                    ) }}
                </strong>
            </div>

            <div class="so-alert-action">
                <a
                    class="so-btn warning"
                    href="{{ route(
                        'provider.orders.register-payment',
                        [
                            'tenant' => $tenantSlug,
                            'order' => $order->id,
                        ]
                    ) }}"
                >
                    <i class="ph-fill ph-currency-circle-dollar"></i>
                    Registrar pagamento
                </a>
            </div>
        </div>
    @endif

    @if(
        $orderStatusValue === 'awaiting_payment'
        && $order->client_remaining <= 0
    )
        <div class="so-alert success">
            <span
                class="so-alert-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-check-circle"></i>
            </span>

            <div class="so-alert-copy">
                <strong>
                    Cliente pagou totalmente.
                </strong>

                A ordem aguarda o faturamento administrativo.
            </div>
        </div>
    @endif

    @if(
        $orderStatusValue === 'paid'
        || $orderStatusValue === 'completed'
    )
        <div class="so-alert success">
            <span
                class="so-alert-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-seal-check"></i>
            </span>

            <div class="so-alert-copy">
                <strong>
                    Ordem totalmente paga.
                </strong>
            </div>
        </div>
    @endif
</main>

<script>
(() => {
    const clientRate =
        Number(@json((float) ($order->unit_price ?? 0)));

    const providerRate =
        Number(@json((float) ($providerRateForUnit ?? 0)));

    const quantityInput =
        document.getElementById('actual_quantity');

    const calcPreview =
        document.getElementById('calc-preview');

    const additionsContainer =
        document.getElementById('additions-container');

    const additionsSummary =
        document.getElementById('additions-summary');

    const additionsHead =
        document.getElementById('additions-table-head');

    const money = value =>
        Number(value || 0).toLocaleString(
            'pt-BR',
            {
                style: 'currency',
                currency: 'BRL',
            }
        );

    function updateExecutionPreview() {
        if (!quantityInput || !calcPreview) {
            return;
        }

        const quantity =
            Math.max(
                0,
                Number(quantityInput.value || 0)
            );

        const clientTotal =
            quantity * clientRate;

        const providerTotal =
            quantity * providerRate;

        calcPreview.innerHTML = `
            <span class="calc-chip client">
                <i class="ph-fill ph-user"></i>
                Cliente: ${money(clientTotal)}
            </span>

            <span class="calc-chip provider">
                <i class="ph-fill ph-wallet"></i>
                Você recebe: ${money(providerTotal)}
            </span>
        `;

        updateAdditionsSummary();
    }

    quantityInput?.addEventListener(
        'input',
        updateExecutionPreview
    );

    if (window.location.hash === '#complete') {
        document
            .getElementById('complete')
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
    }

    let additionIdx = 0;

    document
        .getElementById('btn-add-addition')
        ?.addEventListener(
            'click',
            () => {
                const template =
                    document.getElementById(
                        'addition-template'
                    );

                if (!template || !additionsContainer) {
                    return;
                }

                const clone =
                    template.content.cloneNode(true);

                const item =
                    clone.querySelector('.addition-item');

                if (!item) {
                    return;
                }

                item.innerHTML =
                    item.innerHTML.replace(
                        /__IDX__/g,
                        String(additionIdx)
                    );

                additionsContainer.appendChild(item);

                additionIdx += 1;

                bindAdditionEvents();
                updateAdditionsSummary();

                item
                    .querySelector('.addition-type')
                    ?.focus();
            }
        );

    function bindAdditionEvents() {
        document
            .querySelectorAll(
                '.btn-remove-addition'
            )
            .forEach(button => {
                button.onclick = function () {
                    this
                        .closest('.addition-item')
                        ?.remove();

                    updateAdditionsSummary();
                };
            });

        document
            .querySelectorAll(
                '.addition-type, .addition-amount'
            )
            .forEach(element => {
                element.onchange =
                    updateAdditionsSummary;

                element.oninput =
                    updateAdditionsSummary;
            });
    }

    function updateAdditionsSummary() {
        const items =
            document.querySelectorAll(
                '.addition-item'
            );

        if (additionsHead) {
            additionsHead.classList.toggle(
                'is-empty',
                items.length === 0
            );
        }

        if (!items.length) {
            if (additionsSummary) {
                additionsSummary.style.display =
                    'none';
            }

            return;
        }

        if (additionsSummary) {
            additionsSummary.style.display =
                'block';
        }

        let fees = 0;
        let discounts = 0;

        items.forEach(item => {
            const type =
                item.querySelector(
                    '.addition-type'
                )?.value;

            const amount =
                Math.max(
                    0,
                    Number(
                        item.querySelector(
                            '.addition-amount'
                        )?.value
                        || 0
                    )
                );

            if (type === 'fee') {
                fees += amount;
            }

            if (type === 'discount') {
                discounts += amount;
            }
        });

        const quantity =
            Math.max(
                0,
                Number(
                    quantityInput?.value
                    || 0
                )
            );

        const baseClient =
            quantity * clientRate;

        const adjusted =
            Math.max(
                0,
                baseClient
                + fees
                - discounts
            );

        const feesElement =
            document.getElementById(
                'summary-fees'
            );

        const discountsElement =
            document.getElementById(
                'summary-discounts'
            );

        const adjustedElement =
            document.getElementById(
                'summary-adjusted'
            );

        if (feesElement) {
            feesElement.textContent =
                money(fees);
        }

        if (discountsElement) {
            discountsElement.textContent =
                money(discounts);
        }

        if (adjustedElement) {
            adjustedElement.textContent =
                money(adjusted);
        }
    }

    updateExecutionPreview();
    bindAdditionEvents();
    updateAdditionsSummary();
})();
</script>
@endsection