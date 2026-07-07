@php
$distQty = $delivery['distributed_qty'] ?? 0;
$totalQty = $delivery['quantity'];
$distPercent = $totalQty > 0 ? min(round(($distQty / $totalQty) * 100), 100) : 0;
$overDistributed = $distQty > $totalQty;
$distDisplayPercent = $overDistributed ? 100 : $distPercent;
@endphp
<tr id="desktop-row-{{ $delivery['id'] }}"
    data-delivery-id="{{ $delivery['id'] }}"
    class="{{ $delivery['status_value'] === 'approved' ? 'approved-row' : '' }}"
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
        <input type="checkbox" class="delivery-chk" value="{{ $delivery['id'] }}" data-associate="{{ $delivery['associate_name'] }}" data-net="{{ $delivery['dist_net_value'] }}">
        @endif
    </td>
    <td style="white-space:nowrap;">{{ $delivery['delivery_date'] }}</td>
    <td style="font-weight:500;">{{ $delivery['associate_name'] }}</td>
    <td>{{ $delivery['product_name'] }}</td>
    <td style="white-space:nowrap;font-weight:600;">{{ number_format($totalQty, 3, ',', '.') }} <small style="font-weight:400;font-size:.72em;">{{ $delivery['unit'] }}</small></td>
    <td style="white-space:nowrap;font-weight:600;">
        @if($delivery['dist_net_value'] > 0)
            <span style="color:var(--color-success)">R$ {{ number_format($delivery['dist_net_value'], 2, ',', '.') }}</span>
        @else
            <span style="color:var(--color-text-muted);font-size:.78rem">—</span>
        @endif
    </td>
    <td>{{ $delivery['quality_grade'] ?? '—' }}</td>
    <td>
        <span class="badge-status {{ $delivery['status_value'] }}">{{ $delivery['status'] }}</span>
        @if($delivery['has_billed'])
        <div style="display:inline-flex;align-items:center;gap:.2rem;font-size:.65rem;font-weight:600;color:#4f46e5;background:#eef2ff;border-radius:99px;padding:.1rem .45rem;white-space:nowrap;margin-top:.18rem;">
            <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Faturado
        </div>
        @endif
    </td>
    <td>
        <div class="dist-indicator" role="button" tabindex="0" data-summary="1" title="{{ $overDistributed ? 'Excede! Total dist.: '.number_format($distQty,2,',','.').' '.$delivery['unit'] : ($distPercent >= 100 ? 'Totalmente distribuído' : 'A distribuir: '.number_format($totalQty - $distQty, 2, ',', '.').' '.$delivery['unit']) }}">
            <div class="dist-bar-bg">
                <div class="dist-bar-fill {{ $overDistributed ? 'over' : ($distPercent >= 100 ? 'full' : 'partial') }}" style="width:{{ $distDisplayPercent }}%"></div>
            </div>
            <span class="dist-text">{{ $overDistributed ? '⚠ '.number_format($distQty,1) : $distPercent }}%</span>
        </div>
    </td>
    <td>
        @if($delivery['status_value'] === 'pending')
        <div class="action-btns">
            <button class="btn-approve" data-id="{{ $delivery['id'] }}" title="Aprovar">
                <i data-lucide="check" style="width:11px;height:11px"></i> Aprovar
            </button>
            <button class="btn-reject" data-id="{{ $delivery['id'] }}" title="Rejeitar">
                <i data-lucide="x" style="width:11px;height:11px"></i> Rejeitar
            </button>
            <button class="btn-edit"
                data-id="{{ $delivery['id'] }}"
                data-date="{{ $delivery['delivery_date_raw'] }}"
                data-qty="{{ $delivery['quantity'] }}"
                data-price="{{ $delivery['unit_price'] }}"
                data-quality="{{ $delivery['quality_grade'] }}"
                data-notes="{{ $delivery['notes'] }}"
                data-unit="{{ $delivery['unit'] }}"
                data-distributions="{{ json_encode($delivery['distributions']) }}"
                title="Editar entrega">
                <i data-lucide="pencil" style="width:11px;height:11px"></i> Editar
            </button>
        </div>
        @elseif($delivery['status_value'] === 'approved')
        <div class="action-btns">
            <button class="btn-distribute"
                data-id="{{ $delivery['id'] }}"
                data-product="{{ $delivery['product_name'] }}"
                data-unit="{{ $delivery['unit'] }}"
                data-qty="{{ $delivery['quantity'] }}"
                data-distributed="{{ $delivery['distributed_qty'] }}"
                data-existing="{{ json_encode($delivery['distributions']) }}"
                data-participants="{{ json_encode($customers->pluck('id')->values()->all()) }}"
                title="Distribuir para clientes">
                <i data-lucide="git-branch" style="width:11px;height:11px"></i> Distribuir
            </button>
            @if($delivery['has_billed'])
            <button class="btn-edit" disabled title="Entrega faturada — edição bloqueada" style="opacity:.4;cursor:not-allowed;">
                <i data-lucide="lock" style="width:11px;height:11px"></i> Bloqueado
            </button>
            @else
            <button class="btn-edit"
                data-id="{{ $delivery['id'] }}"
                data-date="{{ $delivery['delivery_date_raw'] }}"
                data-qty="{{ $delivery['quantity'] }}"
                data-price="{{ $delivery['unit_price'] }}"
                data-quality="{{ $delivery['quality_grade'] }}"
                data-notes="{{ $delivery['notes'] }}"
                data-unit="{{ $delivery['unit'] }}"
                data-distributions="{{ json_encode($delivery['distributions']) }}"
                title="Editar entrega">
                <i data-lucide="pencil" style="width:11px;height:11px"></i> Editar
            </button>
            <button class="btn-delete-approved"
                data-id="{{ $delivery['id'] }}"
                title="Excluir entrega aprovada">
                <i data-lucide="trash-2" style="width:11px;height:11px"></i> Excluir
            </button>
            @endif
        </div>
        @else
        <span style="font-size:.7rem;color:var(--color-text-secondary)">—</span>
        @endif
    </td>
</tr>
