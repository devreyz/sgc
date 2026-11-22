@php
    $valueExecution = $order->execution;
    $valuePreview = $valueExecution?->management_preview ?? [];
    $valueFrozen = $valueExecution?->isFrozen();
    $valueLines = $valueFrozen ? $valueExecution->compositionLines->where('direction', $valueDirection)->toArray() : ($valuePreview[$valueDirection] ?? []);
    $valueBase = collect($valueLines)->first(fn ($line) => ($line['type'] ?? null) === 'base');
    $valueObligations = $valueFrozen ? $valueExecution->obligations->where('direction', $valueDirection)->where('status', '!=', 'cancelled') : collect();
    $valueTotal = $valueFrozen ? (float) $valueObligations->sum('total_amount') : ($valuePreview[$valueDirection.'_total'] ?? null);
    $paidForValue = static fn ($item) => array_key_exists('settled_amount', $item->getAttributes()) ? (float) $item->settled_amount : $item->paid_amount;
    $valuePaid = round($valueObligations->sum($paidForValue), 2);
    $valueBalance = round($valueObligations->sum(fn ($item) => max(0, $item->total_amount - $paidForValue($item))), 2);
    $valueMoney = static fn ($amount) => 'R$ '.number_format($amount, 2, ',', '.');
    $valueMethod = data_get($valueBase, 'rule_snapshot.method');
    $valueRateAdjustment = $valueDirection === 'payable' ? $valueObligations->first()?->latestProviderRateAdjustment : null;
    $valueRate = data_get($valueRateAdjustment, 'metadata.new_rate') ?? ($valueBase['unit_price'] ?? 0);
    $valueRateFormatted = preg_replace('/(,\d{2,}?)0+$/', '$1', number_format((float) $valueRate, 4, ',', '.'));
@endphp
<strong>{{ $valueTotal === null ? 'A calcular' : 'R$ '.number_format((float) $valueTotal, 2, ',', '.') }}</strong>
@if($valueBase && $valueMethod === 'quantity_x_rate')
    <small style="display:block">{{ \App\Support\PdfQuantityFormatter::format($valueBase['quantity']) }} {{ $valueBase['unit'] ?? $valueExecution->unit }} × R$ {{ $valueRateFormatted }}</small>
@elseif($valueBase && $valueMethod === 'fixed')
    <small style="display:block">{{ $valueRateAdjustment ? 'Valor por serviço: R$ '.$valueRateFormatted : 'Valor por serviço' }}</small>
@endif
@if($valueObligations->isNotEmpty())
    @if($valuePaid > 0 || $valueBalance === 0.0)
        <small style="display:block">{{ $valueDirection === 'receivable' ? 'Recebido' : 'Pago' }}: {{ $valueMoney($valuePaid) }}</small>
    @endif
    @if($valueBalance > 0)
        <small style="display:block">{{ $valueDirection === 'receivable' ? 'A receber' : 'A pagar' }}: {{ $valueMoney($valueBalance) }}</small>
    @endif
@endif
@if(!$valueFrozen && $valueTotal !== null)<small style="display:block">{{ $valueExecution->status === 'submitted' ? 'Prévia para conferência' : 'Estimativa' }}</small>@endif
@if(isset($valuePreview['error']))<small style="display:block;white-space:normal;max-width:16rem" role="status">{{ $valuePreview['error'] }}</small>@endif
