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

$limitTone = $limitPercent === null
    ? 'slate'
    : ($limitPercent >= 100
        ? 'red'
        : ($limitPercent >= 80 ? 'amber' : 'green'));

$distributionTone = $overDistributed
    ? 'over'
    : ($distPercent >= 100 ? 'full' : 'partial');

$distributionLabel = $overDistributed
    ? 'Distribuição acima da quantidade registrada'
    : ($distPercent >= 100
        ? 'Totalmente distribuído'
        : 'Distribuição pendente');

$distributionTitle = $overDistributed
    ? 'Excede! Total distribuído: '.number_format($distQty, 2, ',', '.').' '.$delivery['unit']
    : ($distPercent >= 100
        ? 'Totalmente distribuído'
        : 'A distribuir: '.number_format($totalQty - $distQty, 2, ',', '.').' '.$delivery['unit']);
@endphp

@once
<style>
    /*
     * Linha desktop de entregas.
     * As classes funcionais já existentes continuam presentes.
     * O prefixo pdr- serve apenas para o refinamento visual.
     */

    .pdr-row {
        --pdr-green: #168a4d;
        --pdr-green-soft: #eaf8ef;
        --pdr-blue: #2563eb;
        --pdr-blue-soft: #eef4ff;
        --pdr-sky: #0284c7;
        --pdr-sky-soft: #edf8fe;
        --pdr-violet: #7c3aed;
        --pdr-violet-soft: #f4f0ff;
        --pdr-amber: #c87408;
        --pdr-amber-soft: #fff7e8;
        --pdr-red: #cf3f3f;
        --pdr-red-soft: #fff0f0;
        --pdr-slate: #64748b;
        --pdr-slate-soft: #f1f5f9;

        --pdr-border: var(--color-border, #dce7e0);
        --pdr-border-strong: var(--color-border-strong, #c8d6cd);
        --pdr-surface: var(--color-surface, #fff);
        --pdr-soft: var(--color-surface-soft, #f8faf9);
        --pdr-text: var(--color-text, #102018);
        --pdr-text-2: var(--color-text-secondary, #52645a);
        --pdr-text-3: var(--color-text-muted, #809087);

        transition:
            background 140ms ease,
            box-shadow 140ms ease;
    }

    .pdr-row > td {
        padding-top: .55rem;
        padding-bottom: .55rem;
        vertical-align: middle;
    }

    .pdr-row:hover > td {
        background:
            linear-gradient(
                180deg,
                color-mix(in srgb, var(--pdr-blue-soft) 26%, #fff),
                #fff
            );
    }

    .pdr-row.approved-row > td:first-child {
        box-shadow: inset 2px 0 0 rgba(22, 138, 77, .34);
    }

    .pdr-icon-center,
    .pdr-icon-center > i,
    .pdr-icon-center > svg,
    .pdr-actions button,
    .pdr-actions button > i,
    .pdr-actions button > svg,
    .pdr-note-btn,
    .pdr-note-btn > i,
    .pdr-note-btn > svg {
        flex: 0 0 auto;
    }

    .pdr-icon-center,
    .pdr-actions button,
    .pdr-note-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 0;
    }

    .pdr-icon-center > i,
    .pdr-icon-center > svg,
    .pdr-actions button > i,
    .pdr-actions button > svg,
    .pdr-note-btn > i,
    .pdr-note-btn > svg {
        display: block;
        margin: 0;
        vertical-align: middle;
    }

    /* Seleção */
    .pdr-check {
        display: inline-flex;
        width: 30px;
        height: 30px;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        cursor: pointer;
    }

    .pdr-check:hover {
        background: var(--pdr-violet-soft);
    }

    .pdr-check .delivery-chk {
        width: 15px;
        height: 15px;
        margin: 0;
        accent-color: var(--pdr-violet);
        cursor: pointer;
    }

    /* Conteúdo principal */
    .pdr-date {
        display: inline-flex;
        min-height: 27px;
        align-items: center;
        padding: .2rem .4rem;
        border: 1px solid var(--pdr-border);
        border-radius: 8px;
        background: var(--pdr-soft);
        color: var(--pdr-text-2);
        font-size: .7rem;
        font-weight: 760;
        white-space: nowrap;
    }

    .pdr-primary-text {
        display: block;
        max-width: 230px;
        overflow: hidden;
        color: var(--pdr-text);
        font-size: .76rem;
        font-weight: 780;
        line-height: 1.3;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pdr-product {
        max-width: 210px;
    }

    .pdr-qty {
        display: inline-flex;
        align-items: baseline;
        gap: .18rem;
        color: var(--pdr-text);
        font-size: .77rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .pdr-unit {
        color: var(--pdr-text-3);
        font-size: .64rem;
        font-weight: 680;
    }

    .pdr-money {
        color: var(--pdr-green);
        font-size: .75rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .pdr-empty-value {
        color: var(--pdr-text-3);
        font-size: .74rem;
    }

    .pdr-quality {
        display: inline-flex;
        min-width: 28px;
        min-height: 25px;
        align-items: center;
        justify-content: center;
        padding: .16rem .34rem;
        border: 1px solid var(--pdr-border);
        border-radius: 8px;
        background: var(--pdr-soft);
        color: var(--pdr-text-2);
        font-size: .67rem;
        font-weight: 820;
    }

    /* Status */
    .pdr-status-stack {
        display: flex;
        min-width: 104px;
        gap: .24rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .pdr-status-stack .badge-status {
        min-height: 25px;
        padding: .18rem .4rem;
        font-size: .61rem;
        letter-spacing: 0;
        text-transform: none;
    }

    .pdr-billed {
        display: inline-flex;
        min-height: 24px;
        align-items: center;
        gap: .2rem;
        padding: .16rem .35rem;
        border-radius: 7px;
        background: var(--pdr-violet-soft);
        color: var(--pdr-violet);
        font-size: .61rem;
        font-weight: 780;
        white-space: nowrap;
    }

    .pdr-billed svg {
        width: 10px;
        height: 10px;
    }

    .pdr-status-stack .pd-issue-btn {
        min-height: 24px;
        margin: 0;
        padding: .17rem .34rem;
        border-radius: 7px;
        font-size: .62rem;
    }

    /* Limite */
    .pdr-limit {
        --limit-tone: var(--pdr-slate);
        --limit-soft: var(--pdr-slate-soft);

        display: grid;
        min-width: 126px;
        max-width: 165px;
        gap: .24rem;
    }

    .pdr-limit.green {
        --limit-tone: var(--pdr-green);
        --limit-soft: var(--pdr-green-soft);
    }

    .pdr-limit.amber {
        --limit-tone: var(--pdr-amber);
        --limit-soft: var(--pdr-amber-soft);
    }

    .pdr-limit.red {
        --limit-tone: var(--pdr-red);
        --limit-soft: var(--pdr-red-soft);
    }

    .pdr-limit-head {
        display: flex;
        min-width: 0;
        align-items: baseline;
        justify-content: space-between;
        gap: .4rem;
    }

    .pdr-limit-used {
        color: var(--pdr-text-2);
        font-size: .66rem;
        font-weight: 720;
        white-space: nowrap;
    }

    .pdr-limit-free {
        color: var(--limit-tone);
        font-size: .64rem;
        font-weight: 820;
        white-space: nowrap;
    }

    .pdr-limit-track {
        height: 6px;
        overflow: hidden;
        border-radius: 999px;
        background: var(--limit-soft);
    }

    .pdr-limit-track > span {
        display: block;
        height: 100%;
        border-radius: inherit;
        background:
            linear-gradient(
                90deg,
                color-mix(in srgb, var(--limit-tone) 48%, #fff),
                var(--limit-tone)
            );
    }

    .pdr-no-limit {
        display: inline-flex;
        min-height: 26px;
        align-items: center;
        padding: .2rem .4rem;
        border-radius: 8px;
        background: var(--pdr-slate-soft);
        color: var(--pdr-slate);
        font-size: .65rem;
        font-weight: 740;
        white-space: nowrap;
    }

    /* Distribuição */
    .pdr-dist {
        --dist-tone: var(--pdr-blue);
        --dist-soft: var(--pdr-blue-soft);

        display: grid;
        min-width: 124px;
        max-width: 165px;
        gap: .24rem;
        padding: .35rem .42rem;
        border: 1px solid transparent;
        border-radius: 9px;
        background: var(--dist-soft);
        cursor: pointer;
        outline: none;
        transition:
            border-color 140ms ease,
            transform 140ms ease;
    }

    .pdr-dist.full {
        --dist-tone: var(--pdr-green);
        --dist-soft: var(--pdr-green-soft);
    }

    .pdr-dist.over {
        --dist-tone: var(--pdr-red);
        --dist-soft: var(--pdr-red-soft);
    }

    .pdr-dist:hover,
    .pdr-dist:focus-visible {
        border-color:
            color-mix(
                in srgb,
                var(--dist-tone) 22%,
                var(--pdr-border)
            );
        transform: translateY(-1px);
    }

    .pdr-dist-head {
        display: flex;
        min-width: 0;
        align-items: center;
        justify-content: space-between;
        gap: .35rem;
    }

    .pdr-dist-label {
        min-width: 0;
        overflow: hidden;
        color: var(--pdr-text-2);
        font-size: .62rem;
        font-weight: 720;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pdr-dist-percent {
        color: var(--dist-tone);
        font-size: .67rem;
        font-weight: 860;
        white-space: nowrap;
    }

    .pdr-dist .dist-bar-bg {
        width: 100%;
        min-width: 0;
        height: 6px;
        overflow: hidden;
        border-radius: 999px;
        background:
            color-mix(
                in srgb,
                var(--dist-soft) 72%,
                var(--pdr-border)
            );
    }

    .pdr-dist .dist-bar-fill {
        height: 100%;
        border-radius: inherit;
        background: var(--dist-tone);
    }

    .pdr-dist-foot {
        color: var(--pdr-text-3);
        font-size: .59rem;
        font-weight: 680;
        white-space: nowrap;
    }

    /* Ações */
    .pdr-action-cell {
        min-width: 0;
    }

    .pdr-actions-wrap {
        display: flex;
        min-width: 0;
        gap: .28rem;
        align-items: center;
        justify-content: flex-end;
    }

    .pdr-actions {
        display: flex;
        min-width: 0;
        gap: .24rem;
        align-items: center;
        flex-wrap: nowrap;
    }

    .pdr-actions button,
    .pdr-note-btn {
        min-height: 30px;
        gap: .22rem;
        padding: .3rem .42rem;
        border: 1px solid var(--pdr-border);
        border-radius: 8px;
        background: #fff;
        color: var(--pdr-text-2);
        cursor: pointer;
        font: inherit;
        font-size: .64rem;
        font-weight: 760;
        white-space: nowrap;
        transition:
            border-color 140ms ease,
            background 140ms ease,
            color 140ms ease,
            transform 140ms ease;
    }

    .pdr-actions button:hover:not(:disabled),
    .pdr-actions button:focus-visible:not(:disabled),
    .pdr-note-btn:hover,
    .pdr-note-btn:focus-visible {
        outline: none;
        transform: translateY(-1px);
    }

    .pdr-actions button > i,
    .pdr-actions button > svg,
    .pdr-note-btn > i,
    .pdr-note-btn > svg {
        width: 12px;
        height: 12px;
    }

    .pdr-actions .btn-approve {
        border-color: rgba(22, 138, 77, .16);
        background: var(--pdr-green-soft);
        color: var(--pdr-green);
    }

    .pdr-actions .btn-reject,
    .pdr-actions .btn-delete-approved {
        border-color: rgba(207, 63, 63, .14);
        background: #fff;
        color: var(--pdr-red);
    }

    .pdr-actions .btn-edit {
        border-color: var(--pdr-border);
        background: #fff;
        color: var(--pdr-blue);
    }

    .pdr-actions .btn-distribute {
        border-color: rgba(124, 58, 237, .16);
        background: var(--pdr-violet-soft);
        color: var(--pdr-violet);
    }

    .pdr-note-btn {
        width: 30px;
        min-width: 30px;
        padding: 0;
        background: var(--pdr-slate-soft);
        color: var(--pdr-slate);
    }

    .pdr-actions button:disabled {
        cursor: not-allowed;
        opacity: .48;
        transform: none;
    }

    .pdr-action-empty {
        color: var(--pdr-text-3);
        font-size: .7rem;
    }

    /*
     * Em larguras de desktop mais apertadas reduzimos as ações a ícones,
     * sem remover texto do DOM: ele continua disponível via title/aria-label.
     */
    @media (max-width: 1180px) {
        .pdr-actions button {
            width: 30px;
            min-width: 30px;
            padding: 0;
            font-size: 0;
        }

        .pdr-actions button > i,
        .pdr-actions button > svg {
            width: 13px;
            height: 13px;
        }

        .pdr-primary-text {
            max-width: 180px;
        }

        .pdr-product {
            max-width: 160px;
        }
    }

    @media (max-width: 980px) {
        .pdr-row > td {
            padding-right: .42rem;
            padding-left: .42rem;
        }

        .pdr-limit,
        .pdr-dist {
            min-width: 112px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .pdr-row,
        .pdr-row *,
        .pdr-row *::before,
        .pdr-row *::after {
            transition-duration: .01ms !important;
        }
    }
</style>

<style id="delivery-row-v3-refinement">
    /*
     * Linha desktop compacta, alinhada visualmente aos cards e ao workspace.
     * A ordem das células e classes funcionais é preservada.
     */
    .pdr-row {
        --pdr-state:var(--pdr-slate);
        --pdr-state-soft:var(--pdr-slate-soft);
    }

    .pdr-row.status-pending {
        --pdr-state:var(--pdr-amber);
        --pdr-state-soft:var(--pdr-amber-soft);
    }

    .pdr-row.status-approved {
        --pdr-state:var(--pdr-blue);
        --pdr-state-soft:var(--pdr-blue-soft);
    }

    .pdr-row.status-rejected {
        --pdr-state:var(--pdr-red);
        --pdr-state-soft:var(--pdr-red-soft);
    }

    .pdr-row.status-cancelled {
        --pdr-state:var(--pdr-slate);
        --pdr-state-soft:var(--pdr-slate-soft);
    }

    .pdr-row > td {
        padding-top:.46rem !important;
        padding-bottom:.46rem !important;
        border-color:var(--pdr-border);
        background:#fff;
    }

    .pdr-row > td:first-child {
        box-shadow:inset 3px 0 0 color-mix(in srgb,var(--pdr-state) 48%,transparent) !important;
    }

    .pdr-row:hover > td {
        background:
            linear-gradient(180deg,color-mix(in srgb,var(--pdr-state-soft) 30%,#fff),#fff) !important;
    }

    .pdr-date {
        min-height:26px;
        padding:.16rem .35rem;
        border-color:color-mix(in srgb,var(--pdr-state) 10%,var(--pdr-border));
        background:var(--pdr-state-soft);
        color:var(--pdr-state);
        font-size:.64rem;
        font-weight:800;
        font-variant-numeric:tabular-nums;
    }

    .pdr-primary-text {
        font-size:.72rem;
        font-weight:800;
    }

    .pdr-product {
        font-weight:840;
    }

    .pdr-qty {
        font-size:.76rem;
        font-weight:900;
        font-variant-numeric:tabular-nums;
    }

    .pdr-unit {
        font-size:.59rem;
    }

    .pdr-money {
        font-size:.7rem;
        font-weight:880;
        font-variant-numeric:tabular-nums;
    }

    .pdr-quality {
        min-width:27px;
        min-height:24px;
        border-radius:7px;
        font-size:.62rem;
    }

    .pdr-status-stack .badge-status,
    .pdr-billed,
    .pdr-status-stack .pd-issue-btn {
        min-height:23px;
        border:1px solid transparent;
        border-radius:7px;
        font-size:.57rem;
    }

    .pdr-billed {
        border-color:rgba(124,58,237,.11);
    }

    .pdr-billed .ph-duotone,
    .pdr-status-stack .pd-issue-btn .ph-duotone {
        width:auto !important;
        height:auto !important;
        font-size:12px !important;
    }

    .pdr-limit {
        min-width:120px;
        max-width:155px;
        gap:.2rem;
    }

    .pdr-limit-used,
    .pdr-limit-free {
        font-size:.59rem;
        font-variant-numeric:tabular-nums;
    }

    .pdr-limit-track {
        height:6px;
    }

    .pdr-no-limit {
        min-height:25px;
        padding:.16rem .34rem;
        font-size:.59rem;
    }

    .pdr-dist {
        min-width:118px;
        max-width:156px;
        gap:.2rem;
        padding:.3rem .36rem;
        border:1px solid color-mix(in srgb,var(--dist-tone) 10%,var(--pdr-border));
        border-radius:8px;
        background:#fff;
    }

    .pdr-dist.partial {
        --dist-tone:var(--pdr-sky);
        --dist-soft:var(--pdr-sky-soft);
    }

    .pdr-dist-label,
    .pdr-dist-foot {
        font-size:.56rem;
    }

    .pdr-dist-percent {
        font-size:.62rem;
        font-weight:900;
        font-variant-numeric:tabular-nums;
    }

    .pdr-dist .dist-bar-bg {
        height:7px;
        background:color-mix(in srgb,var(--dist-soft) 70%,var(--pdr-border));
    }

    .pdr-dist .dist-bar-fill.partial {
        background:var(--pdr-sky) !important;
    }

    .pdr-dist .dist-bar-fill.full {
        background:var(--pdr-green) !important;
    }

    .pdr-dist .dist-bar-fill.over {
        background:var(--pdr-red) !important;
    }

    .pdr-actions-wrap {
        gap:.2rem;
    }

    .pdr-actions {
        gap:.18rem;
    }

    .pdr-actions button,
    .pdr-note-btn {
        min-width:31px;
        min-height:31px;
        padding:.26rem .36rem;
        border-radius:8px;
        font-size:.6rem;
        font-weight:790;
    }

    .pdr-note-btn {
        width:31px;
        padding:0;
        border-color:rgba(100,116,139,.12);
    }

    .pdr-actions .btn-approve {
        border-color:rgba(22,138,77,.12);
        background:var(--pdr-green-soft);
    }

    .pdr-actions .btn-reject,
    .pdr-actions .btn-delete-approved {
        border-color:rgba(207,63,63,.12);
        background:var(--pdr-red-soft);
    }

    .pdr-actions .btn-edit {
        border-color:rgba(37,99,235,.12);
        background:var(--pdr-blue-soft);
    }

    .pdr-actions .btn-distribute {
        border-color:rgba(124,58,237,.13);
        background:var(--pdr-violet-soft);
    }

    .pdr-actions button > .ph-duotone,
    .pdr-note-btn > .ph-duotone {
        width:auto !important;
        height:auto !important;
        font-size:13px !important;
        line-height:1 !important;
    }

    .pdr-row .ph-duotone {
        font-family:"Phosphor-Duotone" !important;
        font-style:normal !important;
        font-weight:normal !important;
    }

    @media(max-width:1280px) {
        .pdr-actions button {
            width:31px !important;
            min-width:31px !important;
            height:31px !important;
            min-height:31px !important;
            padding:0 !important;
            font-size:0 !important;
        }

        .pdr-actions button span {
            display:none !important;
        }
    }
</style>
@endonce

<tr
    id="desktop-row-{{ $delivery['id'] }}"
    data-delivery-id="{{ $delivery['id'] }}"
    class="pdr-row status-{{ $delivery['status_value'] }} {{ $delivery['status_value'] === 'approved' ? 'approved-row' : '' }}"
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
    <td class="chk-cell">
        @if($delivery['status_value'] === 'approved')
            <label
                class="pdr-check"
                title="Selecionar para comprovante"
                aria-label="Selecionar entrega de {{ $delivery['associate_name'] }} para comprovante"
            >
                <input
                    type="checkbox"
                    class="delivery-chk"
                    value="{{ $delivery['id'] }}"
                    data-associate="{{ $delivery['associate_name'] }}"
                    data-net="{{ $delivery['dist_net_value'] }}"
                >
            </label>
        @endif
    </td>

    <td>
        <span class="pdr-date">
            {{ $delivery['delivery_date'] }}
        </span>
    </td>

    <td>
        <span
            class="pdr-primary-text"
            title="{{ $delivery['associate_name'] }}"
        >
            {{ $delivery['associate_name'] }}
        </span>
    </td>

    <td>
        <span
            class="pdr-primary-text pdr-product"
            title="{{ $delivery['product_name'] }}"
        >
            {{ $delivery['product_name'] }}
        </span>
    </td>

    <td>
        <span class="pdr-qty">
            {{ number_format($totalQty, 3, ',', '.') }}
            <span class="pdr-unit">
                {{ $delivery['unit'] }}
            </span>
        </span>
    </td>

    <td>
        @if($delivery['dist_net_value'] > 0)
            <span class="pdr-money">
                R$ {{ number_format($delivery['dist_net_value'], 2, ',', '.') }}
            </span>
        @else
            <span class="pdr-empty-value">—</span>
        @endif
    </td>

    <td>
        <span
            class="pdr-quality"
            title="Qualidade {{ $delivery['quality_grade'] ?? 'não informada' }}"
        >
            {{ $delivery['quality_grade'] ?? '—' }}
        </span>
    </td>

    <td>
        <div class="pdr-status-stack">
            <span class="badge-status {{ $delivery['status_value'] }}">
                {{ $delivery['status'] }}
            </span>

            @if($delivery['has_billed'])
                <span
                    class="pdr-billed"
                    title="Esta entrega possui distribuição faturada"
                >
                    <i class="ph-duotone ph-lock-simple" aria-hidden="true"></i>
                    Faturado
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
                    <i class="ph-duotone ph-warning"></i>
                    {{ $delivery['issue_count'] }}
                </button>
            @endif
        </div>
    </td>

    <td>
        @if($associateLimit !== null)
            <div
                class="pdr-limit {{ $limitTone }}"
                title="Saldo do associado: {{ number_format($associateRemaining, 3, ',', '.') }} {{ $delivery['unit'] }}{{ ($limit['project_remaining'] ?? null) !== null ? ' | Saldo do projeto: '.number_format($limit['project_remaining'], 3, ',', '.').' '.$delivery['unit'] : '' }}"
            >
                <div class="pdr-limit-head">
                    <span class="pdr-limit-used">
                        {{ number_format($limit['associate_delivered'], 3, ',', '.') }}
                        /
                        {{ number_format($associateLimit, 3, ',', '.') }}
                    </span>

                    <strong class="pdr-limit-free">
                        {{ number_format($associateRemaining, 3, ',', '.') }}
                        livres
                    </strong>
                </div>

                <div
                    class="pdr-limit-track"
                    role="progressbar"
                    aria-label="Uso do limite do associado"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-valuenow="{{ min(100, $limitPercent ?? 0) }}"
                >
                    <span
                        style="width:{{ min(100, $limitPercent ?? 0) }}%"
                    ></span>
                </div>
            </div>
        @else
            <span class="pdr-no-limit">
                Sem limite
            </span>
        @endif
    </td>

    <td>
        <div
            class="dist-indicator pdr-dist {{ $distributionTone }}"
            role="button"
            tabindex="0"
            data-summary="1"
            title="{{ $distributionTitle }}"
            aria-label="{{ $distributionLabel }}. {{ $distributionTitle }}"
        >
            <div class="pdr-dist-head">
                <span class="pdr-dist-label">
                    {{ $overDistributed
                        ? 'Excedido'
                        : ($distPercent >= 100 ? 'Concluída' : 'Distribuição') }}
                </span>

                <strong class="dist-text pdr-dist-percent">
                    {{ $overDistributed
                        ? '! '.number_format($distQty, 1, ',', '.')
                        : $distPercent.'%' }}
                </strong>
            </div>

            <div class="dist-bar-bg">
                <div
                    class="dist-bar-fill {{ $overDistributed ? 'over' : ($distPercent >= 100 ? 'full' : 'partial') }}"
                    style="width:{{ $distDisplayPercent }}%"
                ></div>
            </div>

            <span class="pdr-dist-foot">
                {{ number_format($distQty, 2, ',', '.') }}
                de
                {{ number_format($totalQty, 2, ',', '.') }}
                {{ $delivery['unit'] }}
            </span>
        </div>
    </td>

    <td class="pdr-action-cell">
        <div class="pdr-actions-wrap">
            @if(filled($delivery['notes'] ?? null))
                <button
                    type="button"
                    class="delivery-note-trigger pdr-note-btn"
                    data-delivery-notes="{{ $delivery['notes'] }}"
                    data-delivery-notes-title="Observações da entrega"
                    data-delivery-notes-meta="{{ $delivery['product_name'] }} · {{ $delivery['associate_name'] }}"
                    title="Ver observações"
                    aria-label="Ver observações da entrega"
                >
                    <i class="ph-duotone ph-chat-text"></i>
                </button>
            @endif

            @if($delivery['status_value'] === 'pending')
                <div class="action-btns pdr-actions">
                    <button
                        class="btn-approve"
                        data-id="{{ $delivery['id'] }}"
                        title="Aprovar entrega"
                        aria-label="Aprovar entrega"
                    >
                        <i class="ph-duotone ph-check-circle"></i>
                        <span>Aprovar</span>
                    </button>

                    <button
                        class="btn-reject"
                        data-id="{{ $delivery['id'] }}"
                        title="Rejeitar entrega"
                        aria-label="Rejeitar entrega"
                    >
                        <i class="ph-duotone ph-x-circle"></i>
                        <span>Rejeitar</span>
                    </button>

                    <button
                        class="btn-edit"
                        data-id="{{ $delivery['id'] }}"
                        data-date="{{ $delivery['delivery_date_raw'] }}"
                        data-qty="{{ $delivery['quantity'] }}"
                        data-price="{{ $delivery['unit_price'] }}"
                        data-quality="{{ $delivery['quality_grade'] }}"
                        data-notes="{{ $delivery['notes'] }}"
                        data-unit="{{ $delivery['unit'] }}"
                        data-distributions="{{ json_encode($delivery['distributions']) }}"
                        title="Editar entrega"
                        aria-label="Editar entrega"
                    >
                        <i class="ph-duotone ph-pencil-simple"></i>
                        <span>Editar</span>
                    </button>

                    @unless($delivery['has_billed'])
                        <button
                            class="btn-delete-approved"
                            data-id="{{ $delivery['id'] }}"
                            title="Excluir entrega pendente"
                            aria-label="Excluir entrega pendente"
                        >
                            <i class="ph-duotone ph-trash"></i>
                            <span>Excluir</span>
                        </button>
                    @endunless
                </div>

            @elseif($delivery['status_value'] === 'approved')
                <div class="action-btns pdr-actions">
                    <button
                        class="btn-distribute"
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
                        title="Distribuir para clientes"
                        aria-label="Distribuir entrega para clientes"
                    >
                        <i class="ph-duotone ph-git-merge"></i>
                        <span>Distribuir</span>
                    </button>

                    <button
                        class="btn-edit"
                        data-id="{{ $delivery['id'] }}"
                        data-date="{{ $delivery['delivery_date_raw'] }}"
                        data-qty="{{ $delivery['quantity'] }}"
                        data-price="{{ $delivery['unit_price'] }}"
                        data-quality="{{ $delivery['quality_grade'] }}"
                        data-notes="{{ $delivery['notes'] }}"
                        data-unit="{{ $delivery['unit'] }}"
                        data-distributions="{{ json_encode($delivery['distributions']) }}"
                        title="Editar entrega"
                        aria-label="Editar entrega"
                    >
                        <i class="ph-duotone ph-pencil-simple"></i>
                        <span>Editar</span>
                    </button>

                    @unless($delivery['has_billed'])
                        <button
                            class="btn-delete-approved"
                            data-id="{{ $delivery['id'] }}"
                            title="Excluir entrega aprovada"
                            aria-label="Excluir entrega aprovada"
                        >
                            <i class="ph-duotone ph-trash"></i>
                            <span>Excluir</span>
                        </button>
                    @endunless
                </div>

            @elseif($delivery['status_value'] === 'rejected')
                <div class="action-btns pdr-actions">
                    <button
                        class="btn-delete-approved"
                        data-id="{{ $delivery['id'] }}"
                        title="Excluir entrega rejeitada"
                        aria-label="Excluir entrega rejeitada"
                    >
                        <i class="ph-duotone ph-trash"></i>
                        <span>Excluir</span>
                    </button>
                </div>

            @else
                <span class="pdr-action-empty">—</span>
            @endif
        </div>
    </td>
</tr>