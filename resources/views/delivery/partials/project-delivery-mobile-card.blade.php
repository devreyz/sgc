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
$stateIcon = $overDistributed
    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="m10.3 3.9-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3.1l-8-14a2 2 0 0 0-3.4 0Z"></path></svg>'
    : ($delivery['status_value'] === 'approved' && $distPercent >= 100
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>'
        : ($delivery['status_value'] === 'approved'
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"></path><path d="M5 12h14"></path></svg>'
            : ($delivery['status_value'] === 'pending'
                ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v6l4 2"></path><circle cx="12" cy="12" r="9"></circle></svg>'
                : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>')));
@endphp

<div class="mobile-card status-{{ $visualStatus }} variant-c"
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
    <div class="mc-head">
        <div class="mc-head-main">
            <span class="mc-date">{{ $delivery['delivery_date'] }}</span>
            <span class="mc-sep" aria-hidden="true">-</span>
            <div class="mc-head-product" title="{{ $delivery['product_name'] }}">{{ $delivery['product_name'] }}</div>
            <span class="mc-sep" aria-hidden="true">-</span>
            <span class="mc-head-qty">{{ number_format($totalQty, 3, ',', '.') }} {{ $delivery['unit'] }}</span>
        </div>
        <span class="mc-state-icon" title="{{ $stateLabel }}" aria-label="{{ $stateLabel }}">{!! $stateIcon !!}</span>
        @if($delivery['status_value'] === 'approved')
        <input type="checkbox" class="delivery-chk" value="{{ $delivery['id'] }}" data-associate="{{ $delivery['associate_name'] }}" data-net="{{ $delivery['dist_net_value'] }}" title="Selecionar para comprovante" style="flex-shrink:0;">
        @endif
    </div>

    <div class="mc-body">
        <div style="display:grid; grid-template-columns:minmax(0,1fr) auto; gap:.3rem .8rem; font-size:.76rem;">
            <div class="mc-assoc" style="font-weight:700;">{{ $delivery['associate_name'] }}</div>
            <div>
                @if($delivery['has_billed'])
                <span style="font-size:.65rem; color:#4f46e5; background:#eef2ff; border-radius:99px; padding:.1rem .35rem;">Fat.</span>
                @endif
                @if(($delivery['issue_count'] ?? 0) > 0)
                <button type="button"
                    class="pd-issue-btn {{ $delivery['issue_severity'] ?? 'warning' }}"
                    onclick="openIntegrityModal({{ $delivery['id'] }})"
                    title="Ver pendencias desta entrega"
                    style="margin-right:.25rem;">
                    <i data-lucide="alert-triangle" style="width:10px;height:10px"></i>
                    {{ $delivery['issue_count'] }}
                </button>
                @elseif($delivery['dist_net_value'] > 0)
                <span class="mc-net">R$ {{ number_format($delivery['dist_net_value'], 2, ',', '.') }}</span>
                @endif
            </div>
        </div>

        @if($associateLimit !== null)
        <div style="display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.45rem;font-size:.68rem;color:var(--color-text-secondary);padding:.25rem .5rem;">
            <span>Limite</span>
            <div style="height:5px;background:#e5e7eb;border-radius:4px;overflow:hidden"><span style="display:block;height:100%;width:{{ min(100, $limitPercent) }}%;background:{{ $limitColor }}"></span></div>
            <strong style="color:var(--color-text)">{{ number_format($associateRemaining, 3, ',', '.') }} {{ $delivery['unit'] }} livres</strong>
        </div>
        @endif

        <div style="display:flex; align-items:center; gap:.5rem; background:var(--color-bg); padding:.3rem .5rem; border-radius:6px;">
            <span style="font-size:.65rem; text-transform:uppercase; color:var(--color-text-secondary); white-space:nowrap;">Distrib.</span>
            <div class="mc-dist-indicator" role="button" tabindex="0" data-summary="1" style="display:flex; align-items:center; gap:.3rem; flex:1; min-width:0;" title="{{ $overDistributed ? 'Excede. Total dist.: '.number_format($distQty,2,',','.').' '.$delivery['unit'] : ($distPercent >= 100 ? 'Totalmente distribuido' : 'A distribuir: '.number_format($totalQty - $distQty, 2, ',', '.').' '.$delivery['unit']) }}">
                <div class="mc-dist-bar-bg" style="flex:1; overflow:hidden;">
                    <div class="mc-dist-bar-fill {{ $overDistributed ? 'over' : ($distPercent >= 100 ? 'full' : 'partial') }}" style="width:{{ $distDisplayPercent }}%; height:100%; border-radius:99px;"></div>
                </div>
                <span class="mc-dist-text" style="font-weight:700; font-size:.72rem;">{{ $overDistributed ? '! '.number_format($distQty,1) : $distPercent }}%</span>
            </div>

            <div class="mc-actions" style="display:flex; gap:.3rem; margin-left:auto; flex-shrink:0;">
                @if($delivery['status_value'] === 'pending')
                <button class="btn-approve btn-xs" data-id="{{ $delivery['id'] }}">Aprovar</button>
                <button class="btn-reject btn-xs" data-id="{{ $delivery['id'] }}">Rejeitar</button>
                <button class="btn-edit btn-xs"
                    data-id="{{ $delivery['id'] }}"
                    data-date="{{ $delivery['delivery_date_raw'] }}"
                    data-qty="{{ $delivery['quantity'] }}"
                    data-price="{{ $delivery['unit_price'] }}"
                    data-quality="{{ $delivery['quality_grade'] }}"
                    data-notes="{{ $delivery['notes'] }}"
                    data-unit="{{ $delivery['unit'] }}"
                    data-distributions="{{ json_encode($delivery['distributions']) }}"
                >Editar</button>
                @elseif($delivery['status_value'] === 'approved')
                <button class="btn-distribute btn-xs"
                    data-id="{{ $delivery['id'] }}"
                    data-product="{{ $delivery['product_name'] }}"
                    data-unit="{{ $delivery['unit'] }}"
                    data-qty="{{ $delivery['quantity'] }}"
                    data-distributed="{{ $delivery['distributed_qty'] }}"
                    data-existing="{{ json_encode($delivery['distributions']) }}"
                    data-participants="{{ json_encode($customers->pluck('id')->values()->all()) }}"
                    data-context="{{ $delivery['sales_project_id'] }}:{{ $delivery['associate_id'] }}"
                >Distribuir</button>
                <button class="btn-edit btn-xs"
                    data-id="{{ $delivery['id'] }}"
                    data-date="{{ $delivery['delivery_date_raw'] }}"
                    data-qty="{{ $delivery['quantity'] }}"
                    data-price="{{ $delivery['unit_price'] }}"
                    data-quality="{{ $delivery['quality_grade'] }}"
                    data-notes="{{ $delivery['notes'] }}"
                    data-unit="{{ $delivery['unit'] }}"
                    data-distributions="{{ json_encode($delivery['distributions']) }}"
                >Editar</button>
                @unless($delivery['has_billed'])
                <button class="btn-delete-approved btn-xs" data-id="{{ $delivery['id'] }}">Excluir</button>
                @endunless
                @elseif($delivery['status_value'] === 'rejected')
                <button class="btn-delete-approved btn-xs" data-id="{{ $delivery['id'] }}">Excluir</button>
                @endif
            </div>
        </div>
    </div>
</div>
