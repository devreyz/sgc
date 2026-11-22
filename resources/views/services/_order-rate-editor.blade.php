@php
    $rateMethod = data_get($rateLine, 'rule_snapshot.method');
    $lastRateChange = $obligation->latestRateAdjustment;
    $currentRate = data_get($lastRateChange, 'metadata.new_rate') ?? ($rateLine['unit_price'] ?? 0);
    $rateQuantity = $rateMethod === 'fixed' ? 1 : (float) ($rateLine['quantity'] ?? 0);
    $oldBase = data_get($lastRateChange, 'metadata.new_base') ?? ($rateLine['amount'] ?? 0);
    $rateField = $providerRate ? 'provider_rate' : 'customer_rate';
    $rateRoute = $providerRate ? 'services.management.provider-rate.update' : 'services.management.customer-rate.update';
    $retryRate = old('finance_dialog') === $rateDialog && $errors->any();
@endphp
    <section class="ds-fin-box" style="min-width:0">
        <div class="ds-fin-box-head"><i class="ph-duotone ph-currency-circle-dollar" aria-hidden="true"></i> Alterar valor por {{ $rateMethod === 'fixed' ? 'serviço' : ($rateLine['unit'] ?? $execution->unit) }}</div>
        <p>Tarifa atual: <strong>R$ {{ preg_replace('/(,\d{2,}?)0+$/', '$1', number_format((float) $currentRate, 4, ',', '.')) }}</strong>. Quantidade: {{ \App\Support\PdfQuantityFormatter::format($rateQuantity) }}. Descontos, acréscimos e pagamentos serão preservados.</p>
        <form class="ds-form-grid" method="post" action="{{ route($rateRoute, [$tenantSlug, $obligation]) }}" data-order-rate-form data-quantity="{{ $rateQuantity }}" data-base="{{ $oldBase }}" data-total="{{ $obligation->total_amount }}">
            @csrf
            @isset($rateDialog)<input type="hidden" name="finance_dialog" value="{{ $rateDialog }}">@endisset
            @if($retryRate)<ul class="ds-field full" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
            <input type="hidden" name="operation_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="expected_total" value="{{ $obligation->total_amount }}">
            <label class="ds-field"><span class="ds-label">Novo valor (R$)</span><input class="ds-control" type="number" name="{{ $rateField }}" data-rate-input min="0.0001" max="999999999" step="0.0001" required inputmode="decimal" value="{{ $retryRate ? old($rateField) : '' }}"></label>
            <label class="ds-field"><span class="ds-label">Motivo</span><input class="ds-control" name="reason" minlength="5" maxlength="500" required placeholder="Ex.: correção da tarifa de 25 para 30" value="{{ $retryRate ? old('reason') : '' }}"></label>
            <p class="ds-field full" data-rate-preview aria-live="polite">Informe a nova tarifa para conferir o total antes de salvar.</p>
            <button class="ds-form-submit adjust" type="submit">Aplicar nova tarifa nesta OS</button>
        </form>
    </section>
