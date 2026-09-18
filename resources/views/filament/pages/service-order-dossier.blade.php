<x-filament-panels::page>
@php($order = $this->record->load(['execution.compositionLines','execution.evidences.document','execution.obligations.allocations.paymentEvent','execution.resources']))
<div class="space-y-4">
<p><strong>{{ $order->number }} — {{ $order->service->name }}</strong><br>Beneficiário: {{ data_get($order->beneficiary_snapshot,'name','—') }} · Prestador: {{ data_get($order->provider_snapshot,'name','—') }}<br>Local: {{ $order->location }} · Agendamento: {{ $order->scheduled_at?->format('d/m/Y H:i') }} · Execução: {{ $order->operational_status }}</p>
<x-filament::section heading="Dados da execução">
@foreach(data_get($order->execution->catalog_snapshot,'fields',[]) as $field)<p>{{ $field['label'] }}: {{ is_scalar($value = data_get($order->execution->values,$field['key'])) ? $value : '—' }}</p>@endforeach
@foreach(['customer' => 'Base para a cobrança', 'provider' => 'Base para o prestador'] as $direction => $label)
@php($calculation = data_get($order->execution->derived_values,"calculation.$direction",[]))
@if(data_get($calculation,'mode')==='meter_difference')
<div style="padding:.75rem;margin-top:.5rem;border-radius:.5rem;background:#eff6ff;color:#1e3a8a"><strong>{{ $label }}:</strong> {{ data_get($calculation,'meter_end_value') }} − {{ data_get($calculation,'meter_start_value') }} = {{ data_get($calculation,'result') }}</div>
@elseif(data_get($calculation,'mode')==='field')
<div style="padding:.75rem;margin-top:.5rem;border-radius:.5rem;background:#f8fafc"><strong>{{ $label }}:</strong> {{ data_get($calculation,'field') }} = {{ data_get($calculation,'result') }}</div>
@endif
@endforeach
@foreach($order->execution->evidences as $evidence)<p><a href="{{ route('services.management.evidences.download', [session('tenant_slug') ?: $order->tenant->slug, $evidence]) }}">{{ $evidence->document->name }}</a></p>@endforeach
</x-filament::section>
@if(auth()->user()->checkPermissionTo('view_service_financials'))
<x-filament::section heading="Composição e pagamentos"><div style="overflow-x:auto"><table style="width:100%"><thead><tr><th>Descrição</th><th>Destino</th><th>Fórmula aplicada</th><th>Total</th></tr></thead><tbody>@foreach($order->execution->compositionLines as $line)<tr><td>{{ $line->description }}</td><td>{{ $line->direction === 'payable' ? 'Prestador' : 'Organização' }}</td><td>{{ data_get($line->rule_snapshot,'formula') ?: (($line->quantity ?? 0).' × '.($line->unit_price ?? 0)) }}</td><td>{{ $line->amount < 0 ? '− ' : '' }}R$ {{ number_format(abs($line->amount),2,',','.') }}</td></tr>@endforeach</tbody></table></div>
@foreach($order->execution->obligations as $obligation)<p><strong>{{ $obligation->direction === 'payable' ? 'Pagar prestador' : 'Receber beneficiário' }}</strong>: devido R$ {{ number_format($obligation->total_amount,2,',','.') }} · pago R$ {{ number_format($obligation->paid_amount,2,',','.') }} · saldo R$ {{ number_format($obligation->balance,2,',','.') }}</p>@foreach($obligation->allocations as $allocation)<p>{{ $allocation->paymentEvent->payment_date->format('d/m/Y') }} — {{ $allocation->paymentEvent->payment_method }} — R$ {{ number_format($allocation->amount,2,',','.') }}</p>@endforeach @endforeach
</x-filament::section>
@endif
</div>
</x-filament-panels::page>
