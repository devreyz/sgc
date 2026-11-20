@php
    $adjustDialog = 'adjust-obligation-'.$obligation->id;
    $rateDialog = 'change-rate-'.$obligation->id;
    $rateLine = collect(data_get($obligation->composition_snapshot, 'lines', []))->first(
        fn ($line) => ($line['type'] ?? null) === 'base' && ($line['source_key'] ?? null) === 'provider_compensation'
    );
    $canChangeRate = $obligation->direction === 'payable'
        && in_array(data_get($rateLine, 'rule_snapshot.method'), ['fixed', 'quantity_x_rate'], true);
    $target = $obligation->direction === 'payable' ? 'Pagamento do prestador' : 'Cobrança do beneficiário';
    $retryAdjustment = old('finance_dialog') === $adjustDialog && $errors->any();
@endphp
<div class="ds-quick-finance">
    <h3>{{ $target }}</h3>
    <div class="ds-doc-actions">
        <button class="ds-button secondary" type="button" data-open-dialog="{{ $adjustDialog }}" data-adjust-effect="decrease">
            <i class="ph-duotone ph-minus-circle" aria-hidden="true"></i> Desconto
        </button>
        <button class="ds-button secondary" type="button" data-open-dialog="{{ $adjustDialog }}" data-adjust-effect="increase">
            <i class="ph-duotone ph-plus-circle" aria-hidden="true"></i> Acréscimo
        </button>
        @if($canChangeRate)
            <button class="ds-button secondary" type="button" data-open-dialog="{{ $rateDialog }}">
                <i class="ph-duotone ph-currency-circle-dollar" aria-hidden="true"></i> Alterar tarifa
            </button>
        @endif
    </div>
</div>
<dialog class="dossier-dialog ds-quick-dialog" id="{{ $adjustDialog }}" aria-labelledby="{{ $adjustDialog }}-title" @if($retryAdjustment) data-finance-reopen @endif>
    <header class="ds-dialog-head">
        <div><h3 id="{{ $adjustDialog }}-title" data-adjust-title>Desconto ou acréscimo</h3><p>{{ $target }}</p></div>
        <button class="ds-dialog-close" type="button" data-close-dialog="{{ $adjustDialog }}" aria-label="Fechar"><i class="ph ph-x" aria-hidden="true"></i></button>
    </header>
    <form class="ds-dialog-body ds-dialog-form" method="post" action="{{ route('services.management.adjustments.store', [$tenantSlug, $obligation]) }}"
        data-obligation-adjustment data-total="{{ $obligation->total_amount }}" data-paid="{{ $obligation->paid_amount }}">
        @csrf
        <input type="hidden" name="finance_dialog" value="{{ $adjustDialog }}">
        <input type="hidden" name="operation_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        <input type="hidden" name="effect" value="{{ $retryAdjustment ? old('effect') : 'decrease' }}">
        @if($retryAdjustment)<ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        <label class="ds-field"><span class="ds-label">Valor (R$)</span>
            <input class="ds-control" type="number" name="amount" step="0.01" min="0.01" required inputmode="decimal" value="{{ $retryAdjustment ? old('amount') : '' }}">
        </label>
        <label class="ds-field"><span class="ds-label">Motivo</span>
            <input class="ds-control" name="reason" minlength="5" maxlength="500" required value="{{ $retryAdjustment ? old('reason') : '' }}">
        </label>
        <p data-adjust-preview aria-live="polite"></p>
        <button type="submit" class="ds-button primary" data-adjust-submit>Registrar</button>
    </form>
</dialog>
@if($canChangeRate)
    <dialog class="dossier-dialog ds-quick-dialog" id="{{ $rateDialog }}" aria-labelledby="{{ $rateDialog }}-title" @if(old('finance_dialog') === $rateDialog && $errors->any()) data-finance-reopen @endif>
        <header class="ds-dialog-head">
            <h3 id="{{ $rateDialog }}-title">Alterar tarifa do prestador</h3>
            <button class="ds-dialog-close" type="button" data-close-dialog="{{ $rateDialog }}" aria-label="Fechar"><i class="ph ph-x" aria-hidden="true"></i></button>
        </header>
        <div class="ds-dialog-body">
            @include('services._provider-rate-editor', ['rateDialog' => $rateDialog])
        </div>
    </dialog>
@endif
