@php
$distQty = $delivery['distributed_qty'] ?? 0;
$totalQty = $delivery['quantity'];
$distPercent = $totalQty > 0 ? min(round(($distQty / $totalQty) * 100), 100) : 0;
$overDistributed = $distQty > $totalQty;
$distDisplayPercent = $overDistributed ? 100 : $distPercent;
@endphp
<div class="mobile-card status-{{ $delivery['status_value'] }} variant-c"
     id="row-{{ $delivery['id'] }}"
     data-total-qty="{{ $totalQty }}"
     data-unit="{{ $delivery['unit'] }}"
     style="padding:0; border-radius:var(--radius-md); overflow:hidden;"
>
    {{-- Cabeçalho --}}
    <div style="display:flex; align-items:center; gap:.5rem; padding:.4rem .6rem; background:var(--color-bg); border-bottom:1px solid var(--color-border);">
        @if($delivery['status_value'] === 'approved')
        <input type="checkbox" class="delivery-chk" value="{{ $delivery['id'] }}" data-associate="{{ $delivery['associate_name'] }}" data-net="{{ $delivery['dist_net_value'] }}" style="flex-shrink:0;">
        @endif
        <span style="font-weight:700; white-space:nowrap; font-size:.82rem;">{{ $delivery['delivery_date'] }}</span>
        <span class="badge-status {{ $delivery['status_value'] }}" style="margin-left:auto; display:inline-flex; align-items:center; gap:.35rem;">
            {{ $delivery['status'] }}
            <span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:rgba(255,255,255,0.3); font-size:.7rem; font-weight:700; color:inherit;">{{ $delivery['quality_grade'] ?? '—' }}</span>
        </span>
        @if($delivery['has_billed'])
        <span style="font-size:.6rem; color:#4f46e5; background:#eef2ff; border-radius:99px; padding:.1rem .35rem;">Fat.</span>
        @endif
    </div>

    {{-- Corpo --}}
    <div style="padding:.5rem .6rem; display:flex; flex-direction:column; gap:.5rem;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:.3rem .8rem; font-size:.76rem;">
            <div style="font-weight: bold;">{{ $delivery['associate_name'] }}</div>
            <div style="font-weight:600;">{{ $delivery['product_name'] }}</div>
            <div>
                <span class="mc-qty" style="font-weight:700;">{{ number_format($totalQty, 3, ',', '.') }} {{ $delivery['unit'] }}</span>
            </div>
            <div>
                @if($delivery['dist_net_value'] > 0)
                <span style="color:var(--color-success); font-weight:600;">R$ {{ number_format($delivery['dist_net_value'], 2, ',', '.') }}</span>
                @endif
            </div>
        </div>

        {{-- Distribuição + Ações (mesma linha) --}}
        <div style="display:flex; align-items:center; gap:.5rem; background:var(--color-bg); padding:.3rem .5rem; border-radius:6px;">
            <span style="font-size:.65rem; text-transform:uppercase; color:var(--color-text-secondary); white-space:nowrap;">Distrib.</span>
            <div class="mc-dist-indicator" style="display:flex; align-items:center; gap:.3rem; flex:1; min-width:0;" title="{{ $overDistributed ? 'Excede! Total dist.: '.number_format($distQty,2,',','.').' '.$delivery['unit'] : ($distPercent >= 100 ? 'Totalmente distribuído' : 'A distribuir: '.number_format($totalQty - $distQty, 2, ',', '.').' '.$delivery['unit']) }}">
                <div class="mc-dist-bar-bg" style="flex:1; height:6px; background:#e5e7eb; border-radius:99px; overflow:hidden; max-width:80px;">
                    <div class="mc-dist-bar-fill {{ $overDistributed ? 'over' : ($distPercent >= 100 ? 'full' : 'partial') }}" style="width:{{ $distDisplayPercent }}%; height:100%; border-radius:99px;"></div>
                </div>
                <span class="mc-dist-text" style="font-weight:700; font-size:.72rem;">{{ $overDistributed ? '⚠ '.number_format($distQty,1) : $distPercent }}%</span>
            </div>

            {{-- Ações na mesma linha, à direita --}}
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
                >Distribuir</button>
                @unless($delivery['has_billed'])
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
                <button class="btn-delete-approved btn-xs" data-id="{{ $delivery['id'] }}">Excluir</button>
                @endunless
                @elseif($delivery['status_value'] === 'rejected')
                {{-- Entregas rejeitadas podem ser excluídas --}}
                <button class="btn-delete-approved btn-xs" data-id="{{ $delivery['id'] }}">Excluir</button>
                @endif
            </div>
        </div>
    </div>
</div>