@extends('layouts.bento')

@section('title', 'Serviços')
@section('page-title', 'Histórico de serviços')
@section('page-subtitle', 'Acompanhe as ordens de serviço, responsáveis, beneficiários e situação operacional.')
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

    /*
     * Mantém o valor original da situação para a lógica da aplicação,
     * usando apenas label/classe para apresentação.
     */
    $orderStatusMeta = static function ($status): array {
        $value = is_object($status)
            ? ($status->value ?? (string) $status)
            : (string) ($status ?? '');

        $normalized = \Illuminate\Support\Str::of($value)
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();

        return match ($normalized) {
            'pending',
            'waiting',
            'awaiting',
            'draft' => [
                'label' => match ($normalized) {
                    'draft' => 'Rascunho',
                    default => 'Pendente',
                },
                'class' => 'is-pending',
                'icon' => 'ph-clock-countdown',
            ],

            'scheduled',
            'agendada',
            'agendado' => [
                'label' => 'Agendada',
                'class' => 'is-scheduled',
                'icon' => 'ph-calendar-check',
            ],

            'in_progress',
            'processing',
            'started',
            'em_andamento' => [
                'label' => 'Em andamento',
                'class' => 'is-progress',
                'icon' => 'ph-play-circle',
            ],

            'completed',
            'done',
            'finished',
            'executed',
            'concluida',
            'concluido' => [
                'label' => 'Concluída',
                'class' => 'is-completed',
                'icon' => 'ph-check-circle',
            ],

            'approved',
            'validated',
            'aprovada',
            'aprovado' => [
                'label' => 'Aprovada',
                'class' => 'is-approved',
                'icon' => 'ph-seal-check',
            ],

            'cancelled',
            'canceled',
            'rejected',
            'cancelada',
            'cancelado' => [
                'label' => in_array(
                    $normalized,
                    ['rejected'],
                    true
                ) ? 'Rejeitada' : 'Cancelada',
                'class' => 'is-cancelled',
                'icon' => 'ph-x-circle',
            ],

            default => [
                'label' => $value !== ''
                    ? \Illuminate\Support\Str::headline($value)
                    : 'Sem situação',
                'class' => 'is-neutral',
                'icon' => 'ph-circle',
            ],
        };
    };

    $ordersTotal = method_exists($orders, 'total')
        ? $orders->total()
        : $orders->count();

    $ordersOnPage = $orders->count();
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
    .service-orders {
        --orders-green: #219653;
        --orders-green-dark: #177c43;
        --orders-green-soft: #edf8f1;
        --orders-green-border: #cde8d6;

        --orders-blue: #3478d4;
        --orders-blue-soft: #eef4ff;
        --orders-blue-border: #d4e2f8;

        --orders-violet: #8a4bd2;
        --orders-violet-soft: #f5effc;
        --orders-violet-border: #e5d8f5;

        --orders-cyan: #168eae;
        --orders-cyan-soft: #edf8fb;

        --orders-amber: #c38418;
        --orders-amber-soft: #fff7e8;
        --orders-amber-border: #efdcb8;

        --orders-red: #cf5050;
        --orders-red-soft: #fff1f1;
        --orders-red-border: #f1cccc;

        --orders-slate: #64748b;
        --orders-slate-soft: #f2f5f7;

        --orders-text: var(--color-text, #17251c);
        --orders-text-2: var(--color-text-secondary, #58685e);
        --orders-muted: var(--color-text-muted, #87938b);
        --orders-border: var(--color-border, #d7e2da);
        --orders-border-strong: var(--color-border-strong, #becdc3);
        --orders-surface: var(--color-surface, #ffffff);
        --orders-soft: var(--color-surface-soft, #f7faf8);
        --orders-shadow: 0 5px 18px rgba(25, 61, 39, .055);

        display: grid;
        width: min(100%, 1380px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .72rem;
        margin: 0 auto;
        padding-bottom: 1rem;
        color: var(--orders-text);
    }

    .service-orders *,
    .service-orders *::before,
    .service-orders *::after {
        box-sizing: border-box;
    }

    .orders-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--orders-border);
        border-radius: 12px;
        background: var(--orders-surface);
        box-shadow: var(--orders-shadow);
    }

    /* =========================================================
       CABEÇALHO
       ========================================================= */

    .orders-head {
        display: flex;
        min-width: 0;
        min-height: 68px;
        gap: .7rem;
        align-items: center;
        justify-content: space-between;
        padding: .68rem .74rem;
        border-bottom: 1px solid var(--orders-border);
        background:
            radial-gradient(
                circle at 100% 0,
                rgba(138, 75, 210, .08),
                transparent 17rem
            ),
            linear-gradient(
                180deg,
                #fafcfb,
                #fff
            );
    }

    .orders-title {
        display: flex;
        min-width: 0;
        gap: .58rem;
        align-items: center;
    }

    .orders-title-icon {
        display: grid;
        width: 40px;
        height: 40px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 9px;
        background: var(--orders-violet-soft);
        color: var(--orders-violet);
    }

    .orders-title-icon > i {
        display: block;
        font-size: 1.08rem;
        line-height: 1;
    }

    .orders-title-copy {
        min-width: 0;
    }

    .orders-title-copy h2,
    .orders-title-copy p {
        margin: 0;
    }

    .orders-title-copy h2 {
        color: var(--orders-text);
        font-size: .94rem;
        font-weight: 850;
        letter-spacing: -.02em;
    }

    .orders-title-copy p {
        margin-top: .08rem;
        color: var(--orders-muted);
        font-size: .69rem;
        line-height: 1.4;
    }

    .orders-head-actions {
        display: flex;
        gap: .36rem;
        align-items: center;
    }

    .orders-count {
        display: inline-flex;
        min-height: 31px;
        gap: .28rem;
        align-items: center;
        padding: .28rem .48rem;
        border-radius: 999px;
        background: var(--orders-slate-soft);
        color: var(--orders-text-2);
        font-size: .65rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .orders-count > i {
        color: var(--orders-violet);
        font-size: .78rem;
    }

    .orders-create {
        display: inline-flex;
        min-height: 39px;
        gap: .34rem;
        align-items: center;
        justify-content: center;
        padding: .44rem .64rem;
        border: 1px solid var(--orders-green-dark);
        border-radius: 8px;
        background:
            linear-gradient(
                180deg,
                #25a95f,
                #1d914f
            );
        color: #fff;
        font-size: .7rem;
        font-weight: 800;
        text-decoration: none;
        box-shadow: 0 6px 14px rgba(33, 150, 83, .14);
        transition:
            box-shadow 140ms ease,
            transform 140ms ease;
    }

    .orders-create:hover,
    .orders-create:focus-visible {
        color: #fff;
        outline: none;
        box-shadow: 0 9px 20px rgba(33, 150, 83, .2);
        transform: translateY(-1px);
    }

    /* =========================================================
       FAIXA DE CONTEXTO
       ========================================================= */

    .orders-context {
        display: flex;
        min-width: 0;
        gap: .55rem;
        align-items: center;
        justify-content: space-between;
        padding: .5rem .7rem;
        border-bottom: 1px solid var(--orders-border);
        background: var(--orders-soft);
    }

    .orders-context-copy {
        display: flex;
        min-width: 0;
        gap: .38rem;
        align-items: center;
        color: var(--orders-text-2);
        font-size: .66rem;
        line-height: 1.4;
    }

    .orders-context-copy > i {
        flex: 0 0 auto;
        color: var(--orders-blue);
        font-size: .85rem;
    }

    .orders-context-copy strong {
        color: var(--orders-text);
    }

    .orders-page-count {
        color: var(--orders-muted);
        font-size: .62rem;
        font-weight: 720;
        white-space: nowrap;
    }

    /* =========================================================
       TABELA
       ========================================================= */

    .orders-table-wrap {
        min-width: 0;
        overflow-x: auto;
    }

    .orders-table {
        width: 100%;
        min-width: 980px;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
        font-size: .7rem;
    }

    .orders-table th {
        min-height: 38px;
        padding: .56rem .62rem;
        border-bottom: 1px solid var(--orders-border-strong);
        background:
            linear-gradient(
                180deg,
                #f5f8f6,
                #eff4f1
            );
        color: #6f7c74;
        font-size: .58rem;
        font-weight: 820;
        letter-spacing: .045em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .orders-table td {
        min-width: 0;
        padding: .58rem .62rem;
        border-bottom: 1px solid var(--orders-border);
        color: var(--orders-text-2);
        vertical-align: middle;
    }

    .orders-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .orders-table tbody tr {
        transition: background 130ms ease;
    }

    .orders-table tbody tr:hover {
        background: #fafcfb;
    }

    .order-number {
        display: inline-flex;
        min-height: 29px;
        gap: .28rem;
        align-items: center;
        padding: .26rem .4rem;
        border-radius: 7px;
        background: var(--orders-violet-soft);
        color: var(--orders-violet);
        font-size: .67rem;
        font-weight: 840;
        white-space: nowrap;
    }

    .order-number > i {
        font-size: .76rem;
    }

    .order-primary {
        display: flex;
        min-width: 0;
        gap: .42rem;
        align-items: center;
    }

    .order-primary-icon {
        display: grid;
        width: 31px;
        height: 31px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 7px;
        background: var(--orders-blue-soft);
        color: var(--orders-blue);
    }

    .order-primary-copy {
        min-width: 0;
    }

    .order-primary-copy strong,
    .order-primary-copy span {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .order-primary-copy strong {
        color: var(--orders-text);
        font-size: .71rem;
        font-weight: 820;
    }

    .order-primary-copy span {
        margin-top: .05rem;
        color: var(--orders-muted);
        font-size: .58rem;
    }

    .person-cell {
        min-width: 0;
    }

    .person-cell strong,
    .person-cell span {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .person-cell strong {
        color: var(--orders-text);
        font-size: .68rem;
        font-weight: 760;
    }

    .person-cell span {
        margin-top: .05rem;
        color: var(--orders-muted);
        font-size: .58rem;
    }

    .schedule-cell {
        display: inline-flex;
        gap: .28rem;
        align-items: center;
        color: var(--orders-text-2);
        font-variant-numeric: tabular-nums;
        font-size: .66rem;
        white-space: nowrap;
    }

    .schedule-cell > i {
        color: var(--orders-cyan);
        font-size: .78rem;
    }

    .schedule-cell.is-empty {
        color: var(--orders-muted);
    }

    .order-status {
        --status-tone: var(--orders-slate);
        --status-soft: var(--orders-slate-soft);
        --status-border: var(--orders-border);

        display: inline-flex;
        width: max-content;
        min-height: 25px;
        gap: .25rem;
        align-items: center;
        padding: .2rem .4rem;
        border: 1px solid var(--status-border);
        border-radius: 999px;
        background: var(--status-soft);
        color: var(--status-tone);
        font-size: .59rem;
        font-weight: 810;
        white-space: nowrap;
    }

    .order-status.is-pending {
        --status-tone: #98630d;
        --status-soft: var(--orders-amber-soft);
        --status-border: var(--orders-amber-border);
    }

    .order-status.is-scheduled {
        --status-tone: var(--orders-blue);
        --status-soft: var(--orders-blue-soft);
        --status-border: var(--orders-blue-border);
    }

    .order-status.is-progress {
        --status-tone: var(--orders-cyan);
        --status-soft: var(--orders-cyan-soft);
        --status-border: #d2eaf0;
    }

    .order-status.is-completed,
    .order-status.is-approved {
        --status-tone: var(--orders-green);
        --status-soft: var(--orders-green-soft);
        --status-border: var(--orders-green-border);
    }

    .order-status.is-cancelled {
        --status-tone: var(--orders-red);
        --status-soft: var(--orders-red-soft);
        --status-border: var(--orders-red-border);
    }

    .order-open {
        display: inline-flex;
        min-height: 33px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .34rem .48rem;
        border: 1px solid var(--orders-border);
        border-radius: 7px;
        background: #fff;
        color: var(--orders-text-2);
        font-size: .64rem;
        font-weight: 780;
        text-decoration: none;
        transition: .13s ease;
        white-space: nowrap;
    }

    .order-open:hover,
    .order-open:focus-visible {
        border-color: var(--orders-violet-border);
        background: var(--orders-violet-soft);
        color: var(--orders-violet);
        outline: none;
    }

    .orders-empty {
        display: grid;
        min-height: 230px;
        place-items: center;
        padding: 1.2rem;
        text-align: center;
    }

    .orders-empty-icon {
        display: grid;
        width: 52px;
        height: 52px;
        place-items: center;
        margin: 0 auto .55rem;
        border-radius: 11px;
        background: var(--orders-violet-soft);
        color: var(--orders-violet);
    }

    .orders-empty strong,
    .orders-empty span {
        display: block;
    }

    .orders-empty strong {
        color: var(--orders-text);
        font-size: .79rem;
        font-weight: 830;
    }

    .orders-empty span {
        max-width: 370px;
        margin: .2rem auto 0;
        color: var(--orders-muted);
        font-size: .68rem;
        line-height: 1.45;
    }

    /* =========================================================
       PAGINAÇÃO
       ========================================================= */

    .orders-pagination {
        padding: .65rem .7rem;
        border-top: 1px solid var(--orders-border);
        background: var(--orders-soft);
    }

    .orders-pagination nav {
        margin: 0;
    }

    /* =========================================================
       MOBILE — tabela vira registros, sem scroll obrigatório
       ========================================================= */

    @media (max-width: 760px) {
        .orders-head {
            align-items: flex-start;
        }

        .orders-title-copy p {
            display: none;
        }

        .orders-count {
            display: none;
        }

        .orders-table-wrap {
            overflow: visible;
            padding: .58rem;
        }

        .orders-table {
            display: block;
            min-width: 0;
        }

        .orders-table thead {
            display: none;
        }

        .orders-table tbody {
            display: grid;
            gap: .45rem;
        }

        .orders-table tr {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: .4rem;
            padding: .56rem;
            border: 1px solid var(--orders-border);
            border-left: 3px solid var(--orders-violet);
            border-radius: 9px;
            background: #fff;
        }

        .orders-table tbody tr:hover {
            background: #fff;
        }

        .orders-table td {
            display: grid;
            min-width: 0;
            gap: .05rem;
            padding: 0;
            border: 0;
        }

        .orders-table td::before {
            color: var(--orders-muted);
            content: attr(data-label);
            font-size: .54rem;
            font-weight: 780;
            letter-spacing: .025em;
            text-transform: uppercase;
        }

        .orders-table td.order-main-cell {
            grid-column: 1 / -1;
        }

        .orders-table td.order-main-cell::before {
            display: none;
        }

        .orders-table td.order-action-cell {
            align-self: end;
        }

        .order-open {
            width: max-content;
        }
    }

    @media (max-width: 520px) {
        .orders-head {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
        }

        .orders-create {
            width: 38px;
            min-width: 38px;
            padding: 0;
        }

        .orders-create span {
            display: none;
        }

        .orders-context {
            align-items: flex-start;
            flex-direction: column;
        }

        .orders-table tr {
            grid-template-columns: 1fr;
        }

        .orders-table td.order-main-cell {
            grid-column: 1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .service-orders *,
        .service-orders *::before,
        .service-orders *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<main class="service-orders">
    <section class="orders-panel">
        <header class="orders-head">
            <div class="orders-title">
                <span
                    class="orders-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-wrench"></i>
                </span>

                <div class="orders-title-copy">
                    <h2>Ordens de serviço</h2>

                    <p>
                        Consulte agendamentos, responsáveis,
                        beneficiários e situação operacional.
                    </p>
                </div>
            </div>

            <div class="orders-head-actions">
                <span class="orders-count">
                    <i class="ph-fill ph-list-checks"></i>

                    {{ $ordersTotal }}
                    {{ $ordersTotal === 1
                        ? 'ordem'
                        : 'ordens' }}
                </span>

                <a
                    class="orders-create"
                    href="{{ route(
                        'provider.orders.create',
                        $tenantSlug
                    ) }}"
                >
                    <i class="ph ph-plus"></i>
                    <span>Nova ordem</span>
                </a>
            </div>
        </header>

        <div class="orders-context">
            <div class="orders-context-copy">
                <i
                    class="ph-fill ph-info"
                    aria-hidden="true"
                ></i>

                <span>
                    <strong>
                        {{ ($operator ?? false)
                            ? 'Operação de serviços'
                            : 'Histórico do prestador' }}
                    </strong>

                    · abra uma ordem para consultar
                    seus dados e movimentações.
                </span>
            </div>

            @if($ordersTotal > 0)
                <span class="orders-page-count">
                    {{ $ordersOnPage }}
                    {{ $ordersOnPage === 1
                        ? 'registro nesta página'
                        : 'registros nesta página' }}
                </span>
            @endif
        </div>

        @if($orders->isEmpty())
            <div class="orders-empty">
                <div>
                    <span
                        class="orders-empty-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-clipboard-text"></i>
                    </span>

                    <strong>
                        Nenhuma ordem de serviço
                    </strong>

                    <span>
                        As ordens registradas aparecerão aqui.
                        Use “Nova ordem” para iniciar um novo atendimento.
                    </span>
                </div>
            </div>
        @else
            <div class="orders-table-wrap">
                <table
                    class="orders-table"
                    aria-label="Ordens de serviço"
                >
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Serviço</th>
                            <th>Prestador</th>
                            <th>Beneficiário</th>
                            <th>Agendamento</th>
                            <th>Situação</th>
                            <th aria-label="Ações"></th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $status = $orderStatusMeta(
                                    $order->operational_status
                                );

                                $providerName =
                                    $order->provider_snapshot['name']
                                    ?? $order->serviceProvider?->name
                                    ?? 'Não informado';

                                $beneficiaryName =
                                    $order->beneficiary_snapshot['name']
                                    ?? 'Não informado';

                                $serviceName =
                                    $order->service?->name
                                    ?? 'Serviço não informado';
                            @endphp

                            <tr>
                                <td data-label="OS">
                                    <span class="order-number">
                                        <i class="ph-fill ph-hash"></i>
                                        {{ $order->number }}
                                    </span>
                                </td>

                                <td
                                    class="order-main-cell"
                                    data-label="Serviço"
                                >
                                    <div class="order-primary">
                                        <span
                                            class="order-primary-icon"
                                            aria-hidden="true"
                                        >
                                            <i class="ph-fill ph-wrench"></i>
                                        </span>

                                        <div class="order-primary-copy">
                                            <strong
                                                title="{{ $serviceName }}"
                                            >
                                                {{ $serviceName }}
                                            </strong>

                                            <span>
                                                Ordem de serviço
                                                {{ $order->number }}
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td data-label="Prestador">
                                    <div class="person-cell">
                                        <strong
                                            title="{{ $providerName }}"
                                        >
                                            {{ $providerName }}
                                        </strong>

                                        <span>Responsável</span>
                                    </div>
                                </td>

                                <td data-label="Beneficiário">
                                    <div class="person-cell">
                                        <strong
                                            title="{{ $beneficiaryName }}"
                                        >
                                            {{ $beneficiaryName }}
                                        </strong>

                                        <span>Atendido</span>
                                    </div>
                                </td>

                                <td data-label="Agendamento">
                                    @if($order->scheduled_at)
                                        <span class="schedule-cell">
                                            <i class="ph ph-calendar-dots"></i>

                                            {{ $order->scheduled_at
                                                ->format('d/m/Y H:i') }}
                                        </span>
                                    @else
                                        <span
                                            class="
                                                schedule-cell
                                                is-empty
                                            "
                                        >
                                            <i class="ph ph-calendar-x"></i>
                                            Não agendada
                                        </span>
                                    @endif
                                </td>

                                <td data-label="Situação">
                                    <span
                                        class="
                                            order-status
                                            {{ $status['class'] }}
                                        "
                                    >
                                        <i
                                            class="
                                                ph-fill
                                                {{ $status['icon'] }}
                                            "
                                            aria-hidden="true"
                                        ></i>

                                        {{ $status['label'] }}
                                    </span>
                                </td>

                                <td
                                    class="order-action-cell"
                                    data-label="Ação"
                                >
                                    <a
                                        class="order-open"
                                        href="{{ route(
                                            'provider.orders.show',
                                            [
                                                $tenantSlug,
                                                $order,
                                            ]
                                        ) }}"
                                        aria-label="Abrir ordem {{ $order->number }}"
                                    >
                                        <span>Abrir</span>
                                        <i class="ph ph-arrow-right"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(
                method_exists($orders, 'hasPages')
                && $orders->hasPages()
            )
                <div class="orders-pagination">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </section>
</main>
@endsection