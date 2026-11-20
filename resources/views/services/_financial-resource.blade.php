@php
    $retryResource = old('finance_dialog') === 'resource-dialog' && $errors->any();
    $resourceValue = fn ($name, $default = '') => $retryResource ? old($name, $default) : $default;
    $resourceEffects = ['information_only' => 'Apenas registrar'];
    if (!$executionFrozen || ($receivableObligations->where('status', '!=', 'cancelled')->count() === 1 && auth()->user()->checkPermissionTo('manage_service_receivables'))) {
        $resourceEffects += ['deduct_from_receivable' => 'Descontar da cobrança', 'add_to_receivable' => 'Acrescentar à cobrança'];
    }
    if (!$executionFrozen || ($payableObligations->where('status', '!=', 'cancelled')->count() === 1 && auth()->user()->checkPermissionTo('manage_service_payables'))) {
        $resourceEffects['reimburse_provider'] = 'Reembolsar o prestador';
    }
    $resourceEffects['create_association_expense'] = 'Criar despesa da associação';
@endphp
<dialog class="dossier-dialog ds-quick-dialog" id="resource-dialog" aria-labelledby="resource-dialog-title" @if($retryResource) data-finance-reopen @endif>
    <header class="ds-dialog-head">
        <h3 id="resource-dialog-title">Adicionar recurso</h3>
        <button class="ds-dialog-close" type="button" data-close-dialog="resource-dialog" aria-label="Fechar"><i class="ph ph-x" aria-hidden="true"></i></button>
    </header>
    <form id="resource-form" class="ds-dialog-body ds-dialog-form" method="post" action="{{ route('services.management.resources.store', [$tenantSlug, $order]) }}">
        @csrf
        <input type="hidden" name="finance_dialog" value="resource-dialog">
        <input type="hidden" name="operation_key" value="{{ \Illuminate\Support\Str::uuid() }}">
        @if($retryResource)<ul role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        <div class="ds-doc-actions" aria-label="Sugestões de recursos">
            <button type="button" class="ds-button secondary" data-resource-preset="Combustível">Combustível</button>
            <button type="button" class="ds-button secondary" data-resource-preset="Material">Material</button>
            <button type="button" class="ds-button secondary" data-resource-preset="Transporte">Transporte</button>
        </div>
        <label class="ds-field"><span class="ds-label">O que foi usado?</span>
            <input class="ds-control" name="description" required maxlength="191" value="{{ $resourceValue('description') }}" placeholder="Ex.: diesel para o trator">
        </label>
        <div class="ds-dialog-grid">
            <label class="ds-field"><span class="ds-label">Fornecido por</span>
                <select class="ds-control" name="provided_by" required>
                    @foreach(['organization' => 'Associação', 'provider' => 'Prestador', 'customer' => 'Beneficiário', 'third_party' => 'Terceiro'] as $key => $label)
                        <option value="{{ $key }}" @selected($resourceValue('provided_by', 'organization') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ds-field"><span class="ds-label">Como lançar</span>
                <select class="ds-control" name="effect" required>
                    @foreach($resourceEffects as $key => $label)
                        <option value="{{ $key }}" @selected($resourceValue('effect') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <label class="ds-field"><span class="ds-label">Valor total (R$)</span>
            <input class="ds-control" type="number" name="amount" min="0" step="0.01" inputmode="decimal" value="{{ $resourceValue('amount') }}">
        </label>
        <label><input type="checkbox" data-resource-details @checked($resourceValue('quantity') !== '' || $resourceValue('unit_price') !== '' || $resourceValue('unit') !== '' || $resourceValue('resource_key') !== '')> Informar quantidade, unidade ou código</label>
        <div class="ds-dialog-grid" data-resource-detail-fields>
            <label class="ds-field"><span class="ds-label">Quantidade</span><input class="ds-control" name="quantity" type="number" min="0" step="0.0001" value="{{ $resourceValue('quantity') }}"></label>
            <label class="ds-field"><span class="ds-label">Unidade</span><input class="ds-control" name="unit" maxlength="30" value="{{ $resourceValue('unit') }}" placeholder="Ex.: litro"></label>
            <label class="ds-field"><span class="ds-label">Preço por unidade (R$)</span><input class="ds-control" name="unit_price" type="number" min="0" step="0.0001" value="{{ $resourceValue('unit_price') }}"></label>
            <label class="ds-field"><span class="ds-label">Código (opcional)</span><input class="ds-control" name="resource_key" maxlength="80" value="{{ $resourceValue('resource_key') }}"></label>
            <p class="ds-field full">Se o total ficar em branco, será calculado pela quantidade e pelo preço unitário.</p>
        </div>
        <button class="ds-button primary" type="submit">Salvar recurso</button>
    </form>
</dialog>
