@php
    $valueExecution = $order->execution;
    $valuePreview = $valueExecution?->management_preview ?? [];
    $valueFrozen = $valueExecution?->isFrozen();
    $valueLines = $valueFrozen ? $valueExecution->compositionLines->where('direction', $valueDirection)->toArray() : ($valuePreview[$valueDirection] ?? []);
    $valueBase = collect($valueLines)->first(fn ($line) => ($line['type'] ?? null) === 'base');
    $valueTotal = $valueFrozen ? (float) $valueExecution->obligations->where('direction', $valueDirection)->where('status', '!=', 'cancelled')->sum('total_amount') : ($valuePreview[$valueDirection.'_total'] ?? null);
    $valueMethod = data_get($valueBase, 'rule_snapshot.method');
@endphp
<strong>{{ $valueTotal === null ? 'A calcular' : 'R$ '.number_format((float) $valueTotal, 2, ',', '.') }}</strong>
@if($valueBase && $valueMethod === 'quantity_x_rate')
    <small style="display:block">{{ \App\Support\PdfQuantityFormatter::format($valueBase['quantity']) }} {{ $valueBase['unit'] ?? $valueExecution->unit }} × R$ {{ number_format((float) ($valueBase['unit_price'] ?? 0), 2, ',', '.') }}</small>
@elseif($valueBase && $valueMethod === 'fixed')
    <small style="display:block">Valor por serviço</small>
@endif
@if(!$valueFrozen && $valueTotal !== null)<small style="display:block">Prévia para conferência</small>@endif
@if(isset($valuePreview['error']))<small style="display:block;var(--ui-color-surface)-space:normal;max-width:16rem" role="status">{{ $valuePreview['error'] }}</small>@endif
