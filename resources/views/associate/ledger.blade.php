@extends('layouts.bento')

@section('title', 'Extrato Financeiro')
@section('page-title', 'Extrato Financeiro')
@section('user-role', 'Associado')

@php
    $routeTenant = request()->route('tenant');

    $routeSlug = is_string($routeTenant)
        ? $routeTenant
        : (
            is_object($routeTenant)
                ? ($routeTenant->slug ?? null)
                : null
        );

    $tenantSlug = $currentTenant?->slug
        ?? session('tenant_slug')
        ?? $routeSlug
        ?? null;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'associate',
        'ledger',
        $tenantSlug
    );

    $statusValue = static function ($status): ?string {
        if (is_object($status)) {
            return $status->value ?? null;
        }

        return is_string($status)
            ? $status
            : null;
    };

    $statusLabel = static function ($status): string {
        if (
            is_object($status)
            && method_exists($status, 'getLabel')
        ) {
            return $status->getLabel();
        }

        return match (
            is_object($status)
                ? ($status->value ?? null)
                : $status
        ) {
            'paid' => 'Pago',
            'partially_paid' => 'Parcialmente pago',
            'issued' => 'Emitido',
            'pending' => 'Pendente',
            'cancelled' => 'Cancelado',
            default => 'Emitido',
        };
    };

    $formatMoney = static fn ($value): string =>
        'R$ ' . number_format(
            (float) $value,
            2,
            ',',
            '.'
        );

    /*
     * Rascunhos e registros sem status público não são exibidos.
     * O controller também deve excluí-los para a paginação ser exata.
     */
    $visibleReceipts = collect($receipts)
        ->filter(function ($receipt) use ($statusValue) {
            $status = $statusValue(
                $receipt->status
                ?? null
            );

            return $status
                && $status !== 'draft';
        })
        ->values();

    $visiblePayments = collect($receiptPayments)
        ->reject(function ($payment) use ($statusValue) {
            $receiptStatus = $statusValue(
                $payment->receipt?->status
                ?? null
            );

            return $receiptStatus === 'draft';
        })
        ->values();
@endphp

@section('content')
<style>
    .ledger-page {
        display: grid;
        width: min(100%, 1120px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .82rem;
        margin: 0 auto;
    }

    .ledger-page *,
    .ledger-page *::before,
    .ledger-page *::after {
        box-sizing: border-box;
    }

    .ledger-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 16px;
        background: var(--color-surface);
        box-shadow: var(--shadow-md);
    }

    .ledger-panel-head {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .72rem;
        padding: .74rem .82rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .ledger-panel-title {
        display: flex;
        min-width: 0;
        align-items: center;
        gap: .58rem;
    }

    .ledger-panel-icon,
    .ledger-summary-icon,
    .ledger-row-icon {
        display: grid;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 11px;
    }

    .ledger-panel-icon {
        width: 39px;
        height: 39px;
    }

    .ledger-panel-icon i {
        font-size: 1.12rem;
    }

    .ledger-panel-icon.overview {
        background: #ecfdf5;
        color: #059669;
    }

    .ledger-panel-icon.receipts {
        background: #eff6ff;
        color: #2563eb;
    }

    .ledger-panel-icon.payments {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .ledger-panel-icon.history {
        background: #fffbeb;
        color: #d97706;
    }

    .ledger-panel-copy {
        min-width: 0;
    }

    .ledger-panel-copy h2 {
        margin: 0;
        color: var(--color-text);
        font-size: .92rem;
        font-weight: 840;
        letter-spacing: -.02em;
    }

    .ledger-panel-copy p {
        margin: .12rem 0 0;
        color: var(--color-text-muted);
        font-size: .72rem;
    }

    .ledger-summary-grid {
        display: grid;
        min-width: 0;
        grid-template-columns:
            repeat(4, minmax(0, 1fr));
    }

    .ledger-summary-item {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
        padding: .76rem;
        border-left: 1px solid var(--color-border);
    }

    .ledger-summary-item:first-child {
        border-left: 0;
    }

    .ledger-summary-icon {
        width: 37px;
        height: 37px;
    }

    .ledger-summary-icon i {
        font-size: 1.05rem;
    }

    .ledger-summary-icon.distributed {
        background: #eff6ff;
        color: #2563eb;
    }

    .ledger-summary-icon.receivable {
        background: #fffbeb;
        color: #d97706;
    }

    .ledger-summary-icon.paid {
        background: #ecfdf5;
        color: #059669;
    }

    .ledger-summary-icon.fees {
        background: #f5f3ff;
        color: #7c3aed;
    }

    .ledger-summary-copy {
        min-width: 0;
    }

    .ledger-summary-copy span,
    .ledger-summary-copy strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ledger-summary-copy span {
        color: var(--color-text-muted);
        font-size: .69rem;
        font-weight: 690;
    }

    .ledger-summary-copy strong {
        margin-top: .12rem;
        color: var(--color-text);
        font-size: 1.02rem;
        font-weight: 850;
        letter-spacing: -.025em;
    }

    .ledger-list {
        min-width: 0;
        padding: 0 .8rem;
    }

    .ledger-row {
        display: grid;
        min-width: 0;
        grid-template-columns:
            auto
            minmax(180px, 1.3fr)
            minmax(105px, .62fr)
            minmax(105px, .62fr)
            minmax(105px, .62fr);
        gap: .62rem;
        align-items: center;
        padding: .72rem 0;
        border-top: 1px solid var(--color-border);
    }

    .ledger-row:first-child {
        border-top: 0;
    }

    .ledger-row-icon {
        width: 38px;
        height: 38px;
    }

    .ledger-row-icon.receipt {
        background: #eff6ff;
        color: #2563eb;
    }

    .ledger-row-icon.payment {
        background: #ecfdf5;
        color: #059669;
    }

    .ledger-row-icon.credit {
        background: #ecfdf5;
        color: #059669;
    }

    .ledger-row-icon.debit {
        background: #fef2f2;
        color: #dc2626;
    }

    .ledger-row-icon i {
        font-size: 1.08rem;
    }

    .ledger-row-copy {
        min-width: 0;
    }

    .ledger-row-title-line {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        align-items: center;
        gap: .34rem;
    }

    .ledger-row-title {
        overflow: hidden;
        color: var(--color-text);
        font-size: .83rem;
        font-weight: 820;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ledger-row-meta {
        display: flex;
        min-width: 0;
        flex-wrap: wrap;
        gap: .24rem .55rem;
        margin-top: .16rem;
        color: var(--color-text-muted);
        font-size: .69rem;
    }

    .ledger-row-meta span {
        display: inline-flex;
        min-width: 0;
        align-items: center;
        gap: .23rem;
    }

    .ledger-row-meta i {
        flex: 0 0 auto;
        font-size: .8rem;
    }

    .ledger-data {
        min-width: 0;
    }

    .ledger-data span,
    .ledger-data strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .ledger-data span {
        color: var(--color-text-muted);
        font-size: .67rem;
        font-weight: 680;
    }

    .ledger-data strong {
        margin-top: .1rem;
        color: var(--color-text);
        font-size: .77rem;
        font-weight: 820;
    }

    .ledger-data.positive strong {
        color: #047857;
    }

    .ledger-data.negative strong {
        color: #b91c1c;
    }

    .ledger-data.warning strong {
        color: #b45309;
    }

    .ledger-status {
        display: inline-flex;
        min-height: 24px;
        width: max-content;
        max-width: 100%;
        align-items: center;
        gap: .23rem;
        padding: .21rem .38rem;
        border-radius: 999px;
        background: var(--color-surface-muted);
        color: var(--color-text-secondary);
        font-size: .62rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .ledger-status.paid {
        background: #ecfdf5;
        color: #047857;
    }

    .ledger-status.partially_paid {
        background: #eff6ff;
        color: #1d4ed8;
    }

    .ledger-status.issued,
    .ledger-status.pending {
        background: #fffbeb;
        color: #92400e;
    }

    .ledger-status.cancelled {
        background: #fef2f2;
        color: #991b1b;
    }

    .ledger-filter {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(160px, 1fr)
            minmax(160px, 1fr)
            auto;
        gap: .58rem;
        align-items: end;
        padding: .72rem .8rem .8rem;
    }

    .ledger-field {
        min-width: 0;
    }

    .ledger-field label {
        display: block;
        margin-bottom: .32rem;
        color: var(--color-text-secondary);
        font-size: .69rem;
        font-weight: 750;
    }

    .ledger-field input {
        width: 100%;
        min-height: 42px;
        padding: .52rem .65rem;
        font-size: .75rem;
    }

    .ledger-filter-actions {
        display: flex;
        gap: .38rem;
    }

    .ledger-filter-button {
        display: inline-flex;
        min-height: 42px;
        align-items: center;
        justify-content: center;
        gap: .32rem;
        padding: .48rem .64rem;
        border: 1px solid var(--color-border-strong);
        border-radius: 10px;
        background: var(--color-surface);
        color: var(--color-text-secondary);
        font-size: .73rem;
        font-weight: 780;
        text-decoration: none;
        cursor: pointer;
        white-space: nowrap;
    }

    .ledger-filter-button.primary {
        border-color: #16a34a;
        background:
            linear-gradient(
                135deg,
                #22c55e,
                #16a34a
            );
        color: #fff;
    }

    .ledger-filter-button:hover,
    .ledger-filter-button:focus-visible {
        outline: none;
        transform: translateY(-1px);
    }

    .ledger-pagination {
        display: flex;
        justify-content: center;
        padding: .78rem;
        border-top: 1px solid var(--color-border);
    }

    .ledger-empty {
        display: grid;
        min-height: 200px;
        place-items: center;
        padding: 1.3rem;
        text-align: center;
    }

    .ledger-empty i {
        color: var(--color-text-muted);
        font-size: 1.6rem;
    }

    .ledger-empty strong {
        display: block;
        margin-top: .42rem;
        color: var(--color-text);
        font-size: .83rem;
        font-weight: 820;
    }

    @media (max-width: 900px) {
        .ledger-summary-grid {
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }

        .ledger-summary-item:nth-child(3) {
            border-top: 1px solid var(--color-border);
            border-left: 0;
        }

        .ledger-summary-item:nth-child(4) {
            border-top: 1px solid var(--color-border);
        }

        .ledger-row {
            grid-template-columns:
                auto
                minmax(0, 1fr)
                minmax(110px, auto);
        }

        .ledger-row .ledger-data:nth-last-child(2),
        .ledger-row .ledger-data:nth-last-child(1) {
            grid-column: 2;
        }

        .ledger-row .ledger-data:first-of-type {
            grid-column: 3;
            grid-row: 1;
            text-align: right;
        }
    }

    @media (max-width: 580px) {
        .ledger-page {
            gap: .7rem;
        }

        .ledger-panel-head {
            padding: .68rem;
        }

        .ledger-panel-copy p {
            display: none;
        }

        .ledger-summary-grid {
            grid-template-columns: 1fr;
        }

        .ledger-summary-item,
        .ledger-summary-item:nth-child(2),
        .ledger-summary-item:nth-child(3),
        .ledger-summary-item:nth-child(4) {
            border-top: 1px solid var(--color-border);
            border-left: 0;
        }

        .ledger-summary-item:first-child {
            border-top: 0;
        }

        .ledger-filter {
            grid-template-columns: 1fr;
            padding: .66rem;
        }

        .ledger-filter-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .ledger-filter-button {
            width: 100%;
        }

        .ledger-list {
            padding: 0 .68rem;
        }

        .ledger-row {
            grid-template-columns:
                auto
                minmax(0, 1fr);
            align-items: start;
        }

        .ledger-row .ledger-data,
        .ledger-row .ledger-data:first-of-type,
        .ledger-row .ledger-data:nth-last-child(2),
        .ledger-row .ledger-data:nth-last-child(1) {
            grid-column: 2;
            grid-row: auto;
            text-align: left;
        }

        .ledger-data {
            display: flex;
            align-items: baseline;
            gap: .35rem;
        }

        .ledger-data span,
        .ledger-data strong {
            display: inline;
        }
    }
</style>

<main class="ledger-page">
    <section class="ledger-panel">
        <header class="ledger-panel-head">
            <div class="ledger-panel-title">
                <span
                    class="ledger-panel-icon overview"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-wallet"></i>
                </span>

                <div class="ledger-panel-copy">
                    <h2>Resumo financeiro</h2>
                </div>
            </div>
        </header>

        <div class="ledger-summary-grid">
            <div class="ledger-summary-item">
                <span
                    class="ledger-summary-icon distributed"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-arrows-left-right"></i>
                </span>

                <div class="ledger-summary-copy">
                    <span>Líquido distribuído</span>
                    <strong>
                        {{ $formatMoney(
                            $financialSummary['total_net']
                            ?? 0
                        ) }}
                    </strong>
                </div>
            </div>

            <div class="ledger-summary-item">
                <span
                    class="ledger-summary-icon receivable"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-clock-countdown"></i>
                </span>

                <div class="ledger-summary-copy">
                    <span>A receber</span>
                    <strong>
                        {{ $formatMoney(
                            $financialSummary['receivable']
                            ?? 0
                        ) }}
                    </strong>
                </div>
            </div>

            <div class="ledger-summary-item">
                <span
                    class="ledger-summary-icon paid"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-check-circle"></i>
                </span>

                <div class="ledger-summary-copy">
                    <span>Pago</span>
                    <strong>
                        {{ $formatMoney(
                            $financialSummary['paid']
                            ?? 0
                        ) }}
                    </strong>
                </div>
            </div>

            <div class="ledger-summary-item">
                <span
                    class="ledger-summary-icon fees"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-percent"></i>
                </span>

                <div class="ledger-summary-copy">
                    <span>Taxas descontadas</span>
                    <strong>
                        {{ $formatMoney(
                            $financialSummary['total_fees']
                            ?? 0
                        ) }}
                    </strong>
                </div>
            </div>
        </div>
    </section>

    @if($visibleReceipts->isNotEmpty())
        <section class="ledger-panel">
            <header class="ledger-panel-head">
                <div class="ledger-panel-title">
                    <span
                        class="ledger-panel-icon receipts"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-receipt"></i>
                    </span>

                    <div class="ledger-panel-copy">
                        <h2>Comprovantes</h2>
                    </div>
                </div>
            </header>

            <div class="ledger-list">
                @foreach($visibleReceipts as $receipt)
                    @php
                        $receiptStatus = $statusValue(
                            $receipt->status
                            ?? null
                        );

                        $receiptPaid = (
                            $receiptStatus === 'paid'
                            && (float) $receipt->amount_paid <= 0
                        )
                            ? (float) $receipt->total_net
                            : (float) $receipt->amount_paid;

                        $receiptRemaining = max(
                            0,
                            (float) $receipt->total_net
                            - $receiptPaid
                        );
                    @endphp

                    <article class="ledger-row">
                        <span
                            class="ledger-row-icon receipt"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-receipt"></i>
                        </span>

                        <div class="ledger-row-copy">
                            <div class="ledger-row-title-line">
                                <strong class="ledger-row-title">
                                    {{ $receipt->formatted_number }}
                                </strong>

                                <span
                                    class="ledger-status {{ $receiptStatus }}"
                                >
                                    {{ $statusLabel(
                                        $receipt->status
                                        ?? null
                                    ) }}
                                </span>
                            </div>

                            <div class="ledger-row-meta">
                                <span>
                                    <i class="ph ph-folder"></i>

                                    {{ $receipt->project?->title
                                        ?? 'Projeto' }}
                                </span>

                                <span>
                                    <i class="ph ph-calendar-blank"></i>

                                    {{ $receipt->issued_at
                                        ?->format('d/m/Y')
                                        ?? 'Data não informada' }}
                                </span>
                            </div>
                        </div>

                        <div class="ledger-data">
                            <span>Líquido</span>

                            <strong>
                                {{ $formatMoney(
                                    $receipt->total_net
                                ) }}
                            </strong>
                        </div>

                        <div class="ledger-data positive">
                            <span>Pago</span>

                            <strong>
                                {{ $formatMoney($receiptPaid) }}
                            </strong>
                        </div>

                        <div class="ledger-data warning">
                            <span>A receber</span>

                            <strong>
                                {{ $formatMoney(
                                    $receiptRemaining
                                ) }}
                            </strong>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($visiblePayments->isNotEmpty())
        <section class="ledger-panel">
            <header class="ledger-panel-head">
                <div class="ledger-panel-title">
                    <span
                        class="ledger-panel-icon payments"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-hand-coins"></i>
                    </span>

                    <div class="ledger-panel-copy">
                        <h2>Pagamentos recebidos</h2>
                    </div>
                </div>
            </header>

            <div class="ledger-list">
                @foreach($visiblePayments as $payment)
                    <article class="ledger-row">
                        <span
                            class="ledger-row-icon payment"
                            aria-hidden="true"
                        >
                            <i class="ph-duotone ph-check-circle"></i>
                        </span>

                        <div class="ledger-row-copy">
                            <div class="ledger-row-title-line">
                                <strong class="ledger-row-title">
                                    Comprovante
                                    {{ $payment->receipt
                                        ?->formatted_number
                                        ?? '-' }}
                                </strong>

                                <span class="ledger-status paid">
                                    Pago
                                </span>
                            </div>

                            <div class="ledger-row-meta">
                                <span>
                                    <i class="ph ph-folder"></i>

                                    {{ $payment->receipt
                                        ?->project
                                        ?->title
                                        ?? 'Projeto' }}
                                </span>

                                <span>
                                    <i class="ph ph-calendar-blank"></i>

                                    {{ $payment->payment_date
                                        ?->format('d/m/Y')
                                        ?? 'Data não informada' }}
                                </span>
                            </div>
                        </div>

                        <div class="ledger-data positive">
                            <span>Valor recebido</span>

                            <strong>
                                {{ $formatMoney(
                                    $payment->amount
                                ) }}
                            </strong>
                        </div>

                        <div class="ledger-data"></div>
                        <div class="ledger-data"></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="ledger-panel">
        <header class="ledger-panel-head">
            <div class="ledger-panel-title">
                <span
                    class="ledger-panel-icon history"
                    aria-hidden="true"
                >
                    <i class="ph-duotone ph-clock-counter-clockwise"></i>
                </span>

                <div class="ledger-panel-copy">
                    <h2>Histórico de transações</h2>
                    <p>
                        {{ $transactions->total() }}
                        movimentações
                    </p>
                </div>
            </div>
        </header>

        <form
            method="GET"
            class="ledger-filter"
        >
            <div class="ledger-field">
                <label for="ledger-start">
                    Data inicial
                </label>

                <input
                    id="ledger-start"
                    type="date"
                    name="start_date"
                    value="{{ request('start_date') }}"
                >
            </div>

            <div class="ledger-field">
                <label for="ledger-end">
                    Data final
                </label>

                <input
                    id="ledger-end"
                    type="date"
                    name="end_date"
                    value="{{ request('end_date') }}"
                >
            </div>

            <div class="ledger-filter-actions">
                <button
                    type="submit"
                    class="ledger-filter-button primary"
                >
                    <i class="ph ph-funnel"></i>
                    Filtrar
                </button>

                @if(
                    request()->hasAny([
                        'start_date',
                        'end_date',
                    ])
                )
                    <a
                        class="ledger-filter-button"
                        href="{{ $tenantSlug
                            ? route(
                                'associate.ledger',
                                [
                                    'tenant' => $tenantSlug,
                                ]
                            )
                            : url('/') }}"
                    >
                        <i class="ph ph-x"></i>
                        Limpar
                    </a>
                @endif
            </div>
        </form>

        @if($transactions->isEmpty())
            <div class="ledger-empty">
                <div>
                    <i
                        class="ph-duotone ph-receipt-x"
                        aria-hidden="true"
                    ></i>

                    <strong>
                        Nenhuma transação encontrada
                    </strong>
                </div>
            </div>
        @else
            <div class="ledger-list">
                @foreach($transactions as $transaction)
                    @php
                        $isCredit =
                            $transaction->type->value
                            === 'credit';

                        $categoryLabel = method_exists(
                            $transaction->category,
                            'getLabel'
                        )
                            ? $transaction->category
                                ->getLabel()
                            : (
                                $transaction->category
                                    ->value
                                ?? 'Movimentação'
                            );
                    @endphp

                    <article class="ledger-row">
                        <span
                            class="ledger-row-icon {{ $isCredit
                                ? 'credit'
                                : 'debit' }}"
                            aria-hidden="true"
                        >
                            <i
                                class="ph-duotone {{ $isCredit
                                    ? 'ph-arrow-circle-down'
                                    : 'ph-arrow-circle-up' }}"
                            ></i>
                        </span>

                        <div class="ledger-row-copy">
                            <div class="ledger-row-title-line">
                                <strong class="ledger-row-title">
                                    {{ $transaction->description
                                        ?: $categoryLabel }}
                                </strong>

                                <span
                                    class="ledger-status {{ $isCredit
                                        ? 'paid'
                                        : 'cancelled' }}"
                                >
                                    {{ $transaction->type
                                        ->getLabel() }}
                                </span>
                            </div>

                            <div class="ledger-row-meta">
                                <span>
                                    <i class="ph ph-calendar-blank"></i>

                                    {{ $transaction
                                        ->transaction_date
                                        ->format('d/m/Y') }}
                                </span>

                                <span>
                                    <i class="ph ph-tag"></i>

                                    {{ $categoryLabel }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="ledger-data {{ $isCredit
                                ? 'positive'
                                : 'negative' }}"
                        >
                            <span>Valor</span>

                            <strong>
                                {{ $isCredit ? '+' : '-' }}
                                {{ $formatMoney(
                                    $transaction->amount
                                ) }}
                            </strong>
                        </div>

                        <div class="ledger-data">
                            <span>Saldo após</span>

                            <strong>
                                {{ $formatMoney(
                                    $transaction->balance_after
                                ) }}
                            </strong>
                        </div>

                        <div class="ledger-data"></div>
                    </article>
                @endforeach
            </div>

            @if($transactions->hasPages())
                <div class="ledger-pagination">
                    {{ $transactions
                        ->withQueryString()
                        ->links('vendor.pagination.bento') }}
                </div>
            @endif
        @endif
    </section>
</main>
@endsection
