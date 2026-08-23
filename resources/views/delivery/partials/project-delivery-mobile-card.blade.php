@php
$distQty = $delivery['distributed_qty'] ?? 0;
$totalQty = $delivery['quantity'];
$distPercent = $totalQty > 0 ? min(round(($distQty / $totalQty) * 100), 100) : 0;
$overDistributed = $distQty > $totalQty;
$distDisplayPercent = $overDistributed ? 100 : $distPercent;
$limit = $delivery['limit'] ?? [];
$associateLimit = $limit['associate_limit'] ?? null;
$associateRemaining = $limit['associate_remaining'] ?? null;
$limitPercent = $limit['associate_percent'] ?? null;
$limitColor = $limitPercent === null ? '#94a3b8' : ($limitPercent >= 100 ? '#dc2626' : ($limitPercent >= 80 ? '#d97706' : '#059669'));
$visualStatus = $delivery['status_value'] === 'approved' && $distPercent >= 100 && ! $overDistributed
    ? 'distributed'
    : $delivery['status_value'];
$stateLabel = $overDistributed
    ? 'Distribuicao acima da quantidade registrada'
    : ($delivery['status_value'] === 'approved' && $distPercent >= 100
        ? 'Aprovada e 100% distribuida'
        : ($delivery['status_value'] === 'approved'
            ? 'Aprovada com distribuicao pendente'
            : ($delivery['status_value'] === 'pending' ? 'Pendente de aprovacao' : $delivery['status'])));
if ($overDistributed) {
    $stateIcon = '<i class="ph-duotone ph-warning"></i>';
} elseif (
    $delivery['status_value'] === 'approved'
    && $distPercent >= 100
) {
    $stateIcon = '<i class="ph-duotone ph-check-circle"></i>';
} elseif ($delivery['status_value'] === 'approved') {
    $stateIcon = '<i class="ph-duotone ph-plus-circle"></i>';
} elseif ($delivery['status_value'] === 'pending') {
    $stateIcon = '<i class="ph-duotone ph-clock"></i>';
} else {
    $stateIcon = '<i class="ph-duotone ph-x-circle"></i>';
}
@endphp

@once
<style>
    .delivery-card-v2 {
        --dc-tone: #64748b;
        --dc-soft: #f1f5f9;
        --dc-border: var(--color-border, #dce7e0);
        --dc-text: var(--color-text, #102018);
        --dc-secondary: var(--color-text-secondary, #52645a);
        --dc-muted: var(--color-text-muted, #809087);
        --dc-surface: var(--color-surface, #fff);
        --dc-bg: var(--color-bg, #f7faf8);

        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--dc-border);
        border-left: 3px solid var(--dc-tone);
        border-radius: 12px;
        background: var(--dc-surface);
        box-shadow: 0 3px 12px rgba(15, 35, 24, .035);
    }

    .delivery-card-v2.status-pending {
        --dc-tone: #c87408;
        --dc-soft: #fff7e8;
    }

    .delivery-card-v2.status-approved {
        --dc-tone: #2563eb;
        --dc-soft: #eef4ff;
    }

    .delivery-card-v2.status-distributed {
        --dc-tone: #168a4d;
        --dc-soft: #eaf8ef;
    }

    .delivery-card-v2.status-rejected {
        --dc-tone: #cf3f3f;
        --dc-soft: #fff0f0;
    }

    .delivery-card-v2.status-cancelled {
        --dc-tone: #64748b;
        --dc-soft: #f1f5f9;
    }

    .delivery-card-v2 *,
    .delivery-card-v2 *::before,
    .delivery-card-v2 *::after {
        box-sizing: border-box;
    }

    .delivery-card-v2 .dc-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .55rem;
        align-items: start;
        padding: .62rem .68rem .56rem;
        border-bottom: 1px solid color-mix(in srgb, var(--dc-tone) 10%, var(--dc-border));
        background:
            radial-gradient(circle at 100% 0, color-mix(in srgb, var(--dc-tone) 7%, transparent), transparent 9rem),
            linear-gradient(180deg, color-mix(in srgb, var(--dc-soft) 42%, #fff), #fff);
    }

    .delivery-card-v2 .dc-main {
        min-width: 0;
    }

    .delivery-card-v2 .dc-product-line {
        display: flex;
        min-width: 0;
        gap: .42rem;
        align-items: center;
    }

    .delivery-card-v2 .dc-product {
        min-width: 0;
        overflow: hidden;
        color: var(--dc-text);
        font-size: .84rem;
        font-weight: 840;
        letter-spacing: -.015em;
        line-height: 1.28;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-card-v2 .mc-state-icon {
        display: block;
        width: 24px;
        height: 24px;
        border: 1px solid color-mix(in srgb, var(--dc-tone) 18%, transparent);
        border-radius: 999px;
        background: var(--dc-soft);
        color: var(--dc-tone);
        line-height: 0;
    }

    .delivery-card-v2 .mc-state-icon > i {
        font-size: 23px;
    }

    .delivery-card-v2 .dc-context {
        display: flex;
        min-width: 0;
        gap: .3rem .55rem;
        align-items: center;
        flex-wrap: wrap;
        margin-top: .18rem;
        color: var(--dc-muted);
        font-size: .67rem;
        line-height: 1.3;
    }

    .delivery-card-v2 .dc-context-item {
        display: inline-flex;
        min-width: 0;
        gap: .22rem;
        align-items: center;
    }

    .delivery-card-v2 .dc-context-item > i,
    .delivery-card-v2 .dc-context-item > svg {
        display: block;
        width: 11px;
        height: 11px;
        flex: 0 0 auto;
    }

    .delivery-card-v2 .dc-associate {
        min-width: 0;
        max-width: min(56vw, 310px);
        overflow: hidden;
        color: var(--dc-secondary);
        font-weight: 720;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .delivery-card-v2 .dc-side {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: max-content;
        gap: .42rem;
        align-items: center;
    }

    .delivery-card-v2 .dc-qty {
        color: var(--dc-text);
        font-size: .8rem;
        font-weight: 850;
        line-height: 1.2;
        white-space: nowrap;
    }

   

    .delivery-card-v2 .delivery-chk {
        width: 16px;
        height: 16px;
        margin: 0;
        accent-color: var(--dc-tone);
        cursor: pointer;
    }

    .delivery-card-v2 .dc-body {
        display: grid;
        min-width: 0;
        gap: .42rem;
        padding: .52rem .68rem .58rem;
    }

    .delivery-card-v2 .dc-signals {
        display: flex;
        min-width: 0;
        gap: .3rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .delivery-card-v2 .dc-signal,
    .delivery-card-v2 .mc-net,
    .delivery-card-v2 .pd-issue-btn {
        display: inline-flex;
        min-height: 24px;
        gap: .2rem;
        align-items: center;
        justify-content: center;
        padding: .16rem .38rem;
        border-radius: 999px;
        font-size: .63rem;
        font-weight: 790;
        line-height: 1;
        white-space: nowrap;
    }

    .delivery-card-v2 .dc-signal.billed {
        background: #f4f0ff;
        color: #6d28d9;
    }

    .delivery-card-v2 .mc-net {
        background: #eaf8ef;
        color: #168a4d;
    }

    .delivery-card-v2 .pd-issue-btn {
        border: 1px solid rgba(200, 116, 8, .16);
        background: #fff7e8;
        color: #a35f05;
        cursor: pointer;
    }

    .delivery-card-v2 .pd-issue-btn.critical {
        border-color: rgba(207, 63, 63, .16);
        background: #fff0f0;
        color: #b42318;
    }

    .delivery-card-v2 .pd-issue-btn.info {
        border-color: rgba(37, 99, 235, .14);
        background: #eef4ff;
        color: #2563eb;
    }

    .delivery-card-v2 .pd-issue-btn > i,
    .delivery-card-v2 .pd-issue-btn > svg {
        display: block;
        width: 11px;
        height: 11px;
        flex: 0 0 auto;
    }

    .delivery-card-v2 .dc-meter {
        display: grid;
        min-width: 0;
        gap: .28rem;
        padding: .38rem .46rem;
        border: 1px solid color-mix(in srgb, var(--dc-border) 82%, transparent);
        border-radius: 9px;
        background: color-mix(in srgb, var(--dc-bg) 76%, #fff);
    }

    .delivery-card-v2 .dc-meter-head {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .5rem;
        align-items: center;
    }

    .delivery-card-v2 .dc-meter-label {
        display: inline-flex;
        min-width: 0;
        gap: .26rem;
        align-items: center;
        color: var(--dc-secondary);
        font-size: .64rem;
        font-weight: 720;
    }

    .delivery-card-v2 .dc-meter-label > i,
    .delivery-card-v2 .dc-meter-label > svg {
        display: block;
        width: 11px;
        height: 11px;
        flex: 0 0 auto;
        color: var(--dc-tone);
    }

    .delivery-card-v2 .dc-meter-value {
        color: var(--dc-text);
        font-size: .66rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .delivery-card-v2 .dc-track,
    .delivery-card-v2 .mc-dist-bar-bg {
        width: 100%;
        height: 6px;
        overflow: hidden;
        border-radius: 999px;
        background: #e7ece9;
    }

    .delivery-card-v2 .dc-track > span {
        display: block;
        height: 100%;
        border-radius: inherit;
    }

    .delivery-card-v2 .dc-distribution {
        --dist-tone: #0284c7;
        --dist-soft: #edf8fe;
    }

    .delivery-card-v2.status-distributed .dc-distribution {
        --dist-tone: #168a4d;
        --dist-soft: #eaf8ef;
    }

    .delivery-card-v2 .mc-dist-indicator {
        display: grid;
        min-width: 0;
        grid-template-columns: minmax(70px, 1fr) auto;
        gap: .42rem;
        align-items: center;
        border-radius: 7px;
        cursor: pointer;
        outline: none;
    }

    .delivery-card-v2 .mc-dist-indicator:focus-visible {
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--dist-tone) 12%, transparent);
    }

    .delivery-card-v2 .mc-dist-indicator:hover .mc-dist-bar-bg {
        background: #dce4df;
    }

    .delivery-card-v2 .mc-dist-bar-fill.partial {
        background: linear-gradient(90deg, #7dd3fc, #3c80a0);
    }

    .delivery-card-v2 .mc-dist-bar-fill.full {
        background: linear-gradient(90deg, #86efac, #168a4d);
    }

    .delivery-card-v2 .mc-dist-bar-fill.over {
        background: linear-gradient(90deg, #fca5a5, #cf3f3f);
    }

    .delivery-card-v2 .mc-dist-text {
        min-width: 34px;
        color: var(--dist-tone);
        font-size: .68rem;
        font-weight: 840;
        text-align: right;
        white-space: nowrap;
    }

    .delivery-card-v2 .dc-actions {
        display: flex;
        min-width: 0;
        gap: .28rem;
        align-items: center;
        justify-content: flex-end;
        padding-top: .06rem;
    }

    .delivery-card-v2 .dc-action {
        --action-tone: #64748b;
        --action-soft: #f1f5f9;
        display: inline-flex;
        min-width: 34px;
        min-height: 34px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .48rem;
        border: 1px solid color-mix(in srgb, var(--action-tone) 14%, var(--dc-border));
        border-radius: 8px;
        background: #fff;
        color: var(--action-tone);
        cursor: pointer;
        font: inherit;
        font-size: .67rem;
        font-weight: 770;
        line-height: 1;
        text-decoration: none;
        white-space: nowrap;
        transition: background .14s ease, border-color .14s ease, transform .14s ease;
    }

    .delivery-card-v2 .dc-action:hover:not(:disabled),
    .delivery-card-v2 .dc-action:focus-visible:not(:disabled) {
        border-color: color-mix(in srgb, var(--action-tone) 24%, var(--dc-border));
        background: var(--action-soft);
        outline: none;
        transform: translateY(-1px);
    }

    .delivery-card-v2 .dc-action:disabled {
        cursor: not-allowed;
        opacity: .48;
    }

    .delivery-card-v2 .dc-action > i,
    .delivery-card-v2 .dc-action > svg {
        display: block;
        width: 14px;
        height: 14px;
        flex: 0 0 auto;
        margin: 0;
    }

    .delivery-card-v2 .dc-action.approve {
        --action-tone: #168a4d;
        --action-soft: #eaf8ef;
    }

    .delivery-card-v2 .dc-action.reject,
    .delivery-card-v2 .dc-action.delete {
        --action-tone: #cf3f3f;
        --action-soft: #fff0f0;
    }

    .delivery-card-v2 .dc-action.edit {
        --action-tone: #2563eb;
        --action-soft: #eef4ff;
    }

    .delivery-card-v2 .dc-action.distribute {
        --action-tone: #7c3aed;
        --action-soft: #f4f0ff;
    }

    .delivery-card-v2 .dc-action.notes {
        --action-tone: #64748b;
        --action-soft: #f1f5f9;
    }

    .delivery-card-v2 .dc-action-label {
        display: none;
    }

    @media (min-width: 680px) {
        .delivery-card-v2 .dc-action {
            min-width: 0;
            padding-right: .56rem;
            padding-left: .56rem;
        }

        .delivery-card-v2 .dc-action-label {
            display: inline;
        }
    }

    @media (max-width: 480px) {
        .delivery-card-v2 .dc-head {
            padding: .56rem .58rem .5rem;
        }

        .delivery-card-v2 .dc-body {
            gap: .36rem;
            padding: .46rem .58rem .52rem;
        }

        .delivery-card-v2 .dc-context {
            gap: .22rem .46rem;
        }

        .delivery-card-v2 .dc-associate {
            max-width: 58vw;
        }

        .delivery-card-v2 .dc-qty {
            font-size: 1rem;
        }

        .delivery-card-v2 .dc-actions {
            gap: .24rem;
        }

        .delivery-card-v2 .dc-action {
            width: 34px;
            min-width: 34px;
            height: 34px;
            min-height: 34px;
            padding: 0;
        }
    }

    @media (max-width: 360px) {
        .delivery-card-v2 .dc-context-item.date {
            display: none;
        }

        .delivery-card-v2 .dc-product {
            font-size: .8rem;
        }

    }
</style>
@endonce

<div class="mobile-card delivery-card-v2 status-{{ $visualStatus }} variant-c"
     id="mobile-row-{{ $delivery['id'] }}"
     data-delivery-id="{{ $delivery['id'] }}"
     data-total-qty="{{ $totalQty }}"
     data-unit="{{ $delivery['unit'] }}"
     data-product="{{ $delivery['product_name'] }}"
     data-distributed="{{ $distQty }}"
     data-distributions-b64="{{ base64_encode(json_encode($delivery['distributions'])) }}"
     data-filter-date="{{ $delivery['delivery_date_raw'] }}"
     data-filter-associate="{{ $delivery['associate_name'] }}"
     data-filter-product="{{ $delivery['product_name'] }}"
     data-filter-status="{{ $delivery['status_value'] }}"
>
    <div class="dc-head mc-head">
        <div class="dc-main">
            <div class="dc-product-line">
                <div class="dc-product mc-head-product" title="{{ $delivery['product_name'] }}">
                    {{ $delivery['product_name'] }}
                </div>

                <span
                    class="mc-state-icon"
                    role="img"
                    title="{{ $stateLabel }}"
                    aria-label="{{ $stateLabel }}"
                >
                    {!! $stateIcon !!}
                </span>
                @if($delivery['has_billed'] || ($delivery['issue_count'] ?? 0) > 0 || $delivery['dist_net_value'] > 0)
            <div class="dc-signals">
                @if($delivery['has_billed'])
                    <span class="dc-signal billed" title="Entrega faturada">
                        <i data-lucide="receipt-text" style="width:11px;height:11px"></i>
                        Faturada
                    </span>
                @endif

                @if(($delivery['issue_count'] ?? 0) > 0)
                    <button
                        type="button"
                        class="pd-issue-btn {{ $delivery['issue_severity'] ?? 'warning' }}"
                        onclick="openIntegrityModal({{ $delivery['id'] }})"
                        title="Ver pendências desta entrega"
                        aria-label="Ver {{ $delivery['issue_count'] }} pendência(s) desta entrega"
                    >
                        <i data-lucide="triangle-alert"></i>
                        {{ $delivery['issue_count'] }}
                    </button>
                @elseif($delivery['dist_net_value'] > 0)
                    <span class="mc-net" title="Valor líquido distribuído">
                        <i data-lucide="circle-dollar-sign" style="width:11px;height:11px"></i>
                        R$ {{ number_format($delivery['dist_net_value'], 2, ',', '.') }}
                    </span>
                @endif
            </div>
        @endif
            </div>

            <div class="dc-context">
                <span class="dc-context-item">
                    <i data-lucide="user-round"></i>
                    <span class="dc-associate" title="{{ $delivery['associate_name'] }}">
                        {{ $delivery['associate_name'] }}
                    </span>
                </span>

                <span class="dc-context-item date">
                    <i data-lucide="calendar-days"></i>
                    <span>{{ $delivery['delivery_date'] }}</span>
                </span>
            </div>
        </div>

        <div class="dc-side">
            <strong class="dc-qty mc-head-qty">
                {{ number_format($totalQty, 3, ',', '.') }} {{ $delivery['unit'] }}
            </strong>
        </div>
    </div>

    <div class="dc-body mc-body">
        

        @if($associateLimit !== null)
            <div class="dc-meter dc-limit-meter">
                <div class="dc-meter-head">
                    <span class="dc-meter-label">
                        <i data-lucide="gauge"></i>
                        Cota do associado
                    </span>

                    <strong class="dc-meter-value">
                        {{ number_format($associateRemaining, 3, ',', '.') }} {{ $delivery['unit'] }} livres
                    </strong>
                </div>

                <div
                    class="dc-track"
                    role="progressbar"
                    aria-label="Uso da cota do associado"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ min(100, $limitPercent ?? 0) }}"
                >
                    <span
                        style="width:{{ min(100, $limitPercent ?? 0) }}%;background:{{ $limitColor }}"
                    ></span>
                </div>
            </div>
        @endif

        <div class="dc-meter dc-distribution">
            <div class="dc-meter-head">
                <span class="dc-meter-label">
                    <i data-lucide="route"></i>
                    Distribuição
                </span>

                <strong class="dc-meter-value">
                    @if($overDistributed)
                        Excedeu {{ number_format($distQty - $totalQty, 3, ',', '.') }} {{ $delivery['unit'] }}
                    @elseif($distPercent >= 100)
                        Concluída
                    @else
                        {{ number_format(max(0, $totalQty - $distQty), 3, ',', '.') }} {{ $delivery['unit'] }} restantes
                    @endif
                </strong>
            </div>

            <div
                class="mc-dist-indicator"
                role="button"
                tabindex="0"
                data-summary="1"
                aria-label="{{ $overDistributed ? 'Distribuição acima da quantidade registrada. Abrir resumo' : ($distPercent >= 100 ? 'Distribuição concluída. Abrir resumo' : 'Abrir resumo da distribuição') }}"
                title="{{ $overDistributed ? 'Excede. Total dist.: '.number_format($distQty,2,',','.').' '.$delivery['unit'] : ($distPercent >= 100 ? 'Totalmente distribuido' : 'A distribuir: '.number_format($totalQty - $distQty, 2, ',', '.').' '.$delivery['unit']) }}"
            >
                <div class="mc-dist-bar-bg">
                    <div
                        class="mc-dist-bar-fill {{ $overDistributed ? 'over' : ($distPercent >= 100 ? 'full' : 'partial') }}"
                        style="width:{{ $distDisplayPercent }}%;height:100%;border-radius:99px"
                    ></div>
                </div>

                <span class="mc-dist-text">
                    {{ $overDistributed ? '! '.number_format($distQty,1) : $distPercent }}%
                </span>
            </div>
        </div>

        <div class="dc-actions mc-actions" aria-label="Ações da entrega">
            @if(filled($delivery['notes'] ?? null))
                <button
                    type="button"
                    class="delivery-note-trigger dc-action notes"
                    data-delivery-notes="{{ $delivery['notes'] }}"
                    data-delivery-notes-title="Observações da entrega"
                    data-delivery-notes-meta="{{ $delivery['product_name'] }} · {{ $delivery['associate_name'] }}"
                    title="Ver observações"
                    aria-label="Ver observações da entrega"
                >
                    <i data-lucide="message-square-text"></i>
                    <span class="dc-action-label">Observações</span>
                </button>
            @endif

            @if($delivery['status_value'] === 'pending')
                <button
                    class="btn-approve btn-xs dc-action approve"
                    data-id="{{ $delivery['id'] }}"
                    title="Aprovar entrega"
                    aria-label="Aprovar entrega de {{ $delivery['product_name'] }}"
                >
                    <i data-lucide="check"></i>
                    <span class="dc-action-label">Aprovar</span>
                </button>

                <button
                    class="btn-reject btn-xs dc-action reject"
                    data-id="{{ $delivery['id'] }}"
                    title="Rejeitar entrega"
                    aria-label="Rejeitar entrega de {{ $delivery['product_name'] }}"
                >
                    <i data-lucide="x"></i>
                    <span class="dc-action-label">Rejeitar</span>
                </button>

                <button
                    class="btn-edit btn-xs dc-action edit"
                    data-id="{{ $delivery['id'] }}"
                    data-date="{{ $delivery['delivery_date_raw'] }}"
                    data-qty="{{ $delivery['quantity'] }}"
                    data-price="{{ $delivery['unit_price'] }}"
                    data-quality="{{ $delivery['quality_grade'] }}"
                    data-notes="{{ $delivery['notes'] }}"
                    data-unit="{{ $delivery['unit'] }}"
                    data-distributions="{{ json_encode($delivery['distributions']) }}"
                    title="Editar entrega"
                    aria-label="Editar entrega de {{ $delivery['product_name'] }}"
                >
                    <i data-lucide="pencil"></i>
                    <span class="dc-action-label">Editar</span>
                </button>
                <button
                        class="btn-delete-approved btn-xs dc-action delete"
                        data-id="{{ $delivery['id'] }}"
                        title="Excluir entrega"
                        aria-label="Excluir entrega de {{ $delivery['product_name'] }}"
                    >
                        <i data-lucide="trash-2"></i>
                        <span class="dc-action-label">Excluir</span>
                    </button>
            @elseif($delivery['status_value'] === 'approved')
                <button
                    class="btn-distribute btn-xs dc-action distribute"
                    data-id="{{ $delivery['id'] }}"
                    data-product="{{ $delivery['product_name'] }}"
                    data-unit="{{ $delivery['unit'] }}"
                    data-qty="{{ $delivery['quantity'] }}"
                    data-distributed="{{ $delivery['distributed_qty'] }}"
                    data-existing="{{ json_encode($delivery['distributions']) }}"
                    data-participants="{{ json_encode($customers->pluck('id')->values()->all()) }}"
                    data-default-customer-id="{{ $delivery['default_customer_id'] ?? '' }}"
                    data-notes="{{ $delivery['notes'] ?? '' }}"
                    data-context="{{ $delivery['sales_project_id'] }}:{{ $delivery['associate_id'] }}"
                    title="Distribuir entrega"
                    aria-label="Distribuir {{ $delivery['product_name'] }}"
                >
                    <i class="ph-duotone ph-git-merge"></i>
                    <span class="dc-action-label">Distribuir</span>
                </button>

                <button
                    class="btn-edit btn-xs dc-action edit"
                    data-id="{{ $delivery['id'] }}"
                    data-date="{{ $delivery['delivery_date_raw'] }}"
                    data-qty="{{ $delivery['quantity'] }}"
                    data-price="{{ $delivery['unit_price'] }}"
                    data-quality="{{ $delivery['quality_grade'] }}"
                    data-notes="{{ $delivery['notes'] }}"
                    data-unit="{{ $delivery['unit'] }}"
                    data-distributions="{{ json_encode($delivery['distributions']) }}"
                    title="Editar entrega"
                    aria-label="Editar entrega de {{ $delivery['product_name'] }}"
                >
                    <i data-lucide="pencil"></i>
                    <span class="dc-action-label">Editar</span>
                </button>

                @unless($delivery['has_billed'])
                    <button
                        class="btn-delete-approved btn-xs dc-action delete"
                        data-id="{{ $delivery['id'] }}"
                        title="Excluir entrega"
                        aria-label="Excluir entrega de {{ $delivery['product_name'] }}"
                    >
                        <i data-lucide="trash-2"></i>
                        <span class="dc-action-label">Excluir</span>
                    </button>
                @endunless
            @elseif($delivery['status_value'] === 'rejected')
                <button
                    class="btn-delete-approved btn-xs dc-action delete"
                    data-id="{{ $delivery['id'] }}"
                    title="Excluir entrega rejeitada"
                    aria-label="Excluir entrega rejeitada de {{ $delivery['product_name'] }}"
                >
                    <i data-lucide="trash-2"></i>
                    <span class="dc-action-label">Excluir</span>
                </button>
            @endif
        </div>
    </div>
</div>