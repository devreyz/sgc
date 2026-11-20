@php
    $rateBase = collect(data_get($obligation->composition_snapshot, 'lines', []))->first(fn ($line) => ($line['type'] ?? null) === 'base' && ($line['source_key'] ?? null) === 'provider_compensation');
    $rateMethod = data_get($rateBase, 'rule_snapshot.method');
    $lastRateChange = $obligation->adjustments->sortByDesc('id')->first(fn ($item) => data_get($item->metadata, 'kind') === 'provider_rate');
    $currentProviderRate = $lastRateChange ? data_get($lastRateChange->metadata, 'new_rate') : ($rateBase['unit_price'] ?? 0);
    $rateQuantity = $rateMethod === 'fixed' ? 1 : (float) ($rateBase['quantity'] ?? 0);
    $oldProviderBase = $lastRateChange ? data_get($lastRateChange->metadata, 'new_base') : ($rateBase['amount'] ?? 0);
    $retryRate = isset($rateDialog) && old('finance_dialog') === $rateDialog && $errors->any();
@endphp
@if($obligation->direction === 'payable' && $obligation->status !== 'cancelled' && in_array($rateMethod, ['fixed', 'quantity_x_rate'], true))
    <section class="ds-fin-box" style="min-width:0">
        <div class="ds-fin-box-head"><i class="ph-duotone ph-currency-circle-dollar" aria-hidden="true"></i> Alterar valor por {{ $rateMethod === 'fixed' ? 'serviço' : ($rateBase['unit'] ?? $execution->unit) }}</div>
        <p>Tarifa atual: <strong>R$ {{ number_format((float) $currentProviderRate, 2, ',', '.') }}</strong>. Quantidade: {{ \App\Support\PdfQuantityFormatter::format($rateQuantity) }}. Adicionais e pagamentos serão preservados.</p>
        <form class="ds-form-grid" method="post" action="{{ route('services.management.provider-rate.update', [$tenantSlug, $obligation]) }}" data-provider-rate-form data-quantity="{{ $rateQuantity }}" data-base="{{ $oldProviderBase }}" data-total="{{ $obligation->total_amount }}">
            @csrf
            @isset($rateDialog)<input type="hidden" name="finance_dialog" value="{{ $rateDialog }}">@endisset
            @if($retryRate)<ul class="ds-field full" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
            <input type="hidden" name="operation_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="expected_total" value="{{ $obligation->total_amount }}">
            <label class="ds-field"><span class="ds-label">Novo valor (R$)</span><input class="ds-control" type="number" name="provider_rate" min="0.0001" max="999999999" step="0.0001" required placeholder="Ex.: 30" inputmode="decimal" value="{{ $retryRate ? old('provider_rate') : '' }}"></label>
            <label class="ds-field"><span class="ds-label">Motivo</span><input class="ds-control" name="reason" minlength="5" maxlength="500" required placeholder="Ex.: correção da tarifa de 25 para 30" value="{{ $retryRate ? old('reason') : '' }}"></label>
            <p class="ds-field full" data-rate-preview aria-live="polite">Informe a nova tarifa para conferir o total antes de salvar.</p>
            <button class="ds-form-submit adjust" type="submit">Aplicar nova tarifa nesta OS</button>
        </form>
    </section>
@endif
