@php
    $execution = $order->execution;
    [$meterStartField, $meterEndField] = \App\Services\Services\ServiceCalculationRules::meterPair((array) $execution?->catalog_snapshot);
    $retry = (int) old('row_order') === (int) $order->id && $errors->any();
@endphp
@if($canEdit && \App\Services\Services\ServiceOrderDetails::editable($order))
    <dialog class="cm-dialog cm-operation-dialog sg-dialog" id="details-{{ $order->id }}" aria-labelledby="details-title-{{ $order->id }}" @if((int) old('details_order') === (int) $order->id && $errors->any()) data-reopen @endif>
        @include('services._order-details-form', ['detailsClose' => true])
    </dialog>
@endif
@if($canEdit && in_array($execution?->status, ['draft', 'in_progress', 'submitted', 'rejected'], true))
    <dialog class="cm-dialog cm-operation-dialog sg-dialog" id="execution-{{ $order->id }}" aria-labelledby="execution-title-{{ $order->id }}" @if($retry) data-reopen @endif>
            <form method="post" action="{{ route('services.management.execution.row', [$tenant->slug, $order]) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="row_order" value="{{ $order->id }}">
                <input type="hidden" name="lock_version" value="{{ $retry ? old('lock_version', $execution->lock_version) : $execution->lock_version }}">
                <input type="hidden" name="operation_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                <h3 id="execution-title-{{ $order->id }}"><i class="ph-duotone ph-pencil-simple" aria-hidden="true"></i> Execução · {{ $order->number }}</h3>
                @if($retry)
                    <ul class="cm-dialog-errors" role="alert">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @endif
                <div class="sg-fields">
                    @if($execution->status === 'submitted')
                        <p>A OS continuará aguardando conferência. As quantidades e a prévia dos valores serão atualizadas ao salvar.</p>
                        <label>Motivo da correção<input name="correction_reason" minlength="5" maxlength="500" required value="{{ $retry ? old('correction_reason') : '' }}" placeholder="Ex.: corrigir as horas anotadas"></label>
                    @endif
                    <label>Dia da execução, se conhecido
                        <input name="execution_date" type="date" max="{{ today()->toDateString() }}"
                            value="{{ $retry ? old('execution_date') : $order->execution_date?->toDateString() }}">
                    </label>
                    @if(auth()->user()->checkPermissionTo('view_service_financials') && data_get($execution->catalog_snapshot, 'payable_enabled')
                        && in_array(data_get($execution->catalog_snapshot, 'provider_pricing_method'), ['fixed', 'quantity_x_rate'], true))
                        @php
                            $currentRate = data_get($execution->catalog_snapshot, 'provider_compensation.rate') ?? data_get($execution->catalog_snapshot, 'default_provider_rate');
                            $rateUnit = data_get($execution->catalog_snapshot, 'execution_config.provider_unit') ?: $execution->unit;
                        @endphp
                        <label>{{ data_get($execution->catalog_snapshot, 'provider_pricing_method') === 'fixed' ? 'Pagamento nesta ordem (R$)' : 'Tarifa do prestador (R$ por '.$rateUnit.')' }}
                            <input name="provider_rate" type="number" min=".0001" step=".0001" value="{{ $retry ? old('provider_rate') : null }}" placeholder="{{ $currentRate }}">
                            <small>{{ $currentRate !== null ? 'Atual: R$ '.number_format((float) $currentRate, 2, ',', '.') .'. ' : '' }}Preencha só para alterar esta ordem.</small>
                        </label>
                    @endif
                    <label>Hora inicial<input type="time" name="start_time" value="{{ $retry ? old('start_time') : substr((string) $order->start_time, 0, 5) }}"></label>
                    <label>Hora final<input type="time" name="end_time" value="{{ $retry ? old('end_time') : substr((string) $order->end_time, 0, 5) }}"></label>
                    @foreach(data_get($execution->catalog_snapshot, 'fields', []) as $definition)
                        @if(($definition['visible_to_management'] ?? true) && in_array($definition['phase'] ?? null, ['start', 'execution', 'finish'], true) && !in_array($definition['type'] ?? null, ['image', 'file', 'signature'], true))
                            @include('services._order-field', [
                                'field' => (object) $definition,
                                'fieldPrefix' => 'values',
                                'fieldValue' => ($retry ? old('values.'.$definition['key']) : null) ?? data_get($execution->values, $definition['key'], $definition['default_value'] ?? ''),
                            ])
                            @if($meterStartField && $definition['key'] === $meterEndField)
                                @include('services._meter-pauses', ['restoreOld' => $retry])
                            @endif
                        @endif
                    @endforeach
                </div>
                <div class="sg-actions">
                    <button class="sg-button sg-primary" type="submit" name="intent" value="save" @if($execution->status !== 'submitted') formnovalidate @endif>{{ $execution->status === 'submitted' ? 'Salvar correções' : 'Salvar campos' }}</button>
                    @if($canOperate && $execution->status !== 'submitted')
                        <button class="sg-button" type="submit" name="intent" value="submit">{{ data_get($execution->catalog_snapshot, 'review_mode') === 'automatic' ? 'Concluir e validar' : 'Enviar para conferência' }}</button>
                        <a class="sg-link" href="{{ route('services.management.execute', [$tenant->slug, $order]) }}">Execução completa / anexos</a>
                    @endif
                    <button class="sg-link" type="button" data-dialog-close>Fechar</button>
                </div>
            </form>
        </dialog>
@endif
@if($accounts->isNotEmpty() && $execution?->isFrozen())
    @foreach($execution->obligations as $obligation)
        @continue($negotiated->contains($obligation->id) || !in_array($obligation->status, ['open', 'partially_paid'], true) || !auth()->user()->checkPermissionTo($obligation->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables'))
        @php
            $retryPayment = (int) old('row_payment') === (int) $obligation->id && $errors->any();
        @endphp
        <dialog class="cm-dialog cm-operation-dialog sg-dialog" id="payment-{{ $obligation->id }}" aria-labelledby="payment-title-{{ $obligation->id }}" @if($retryPayment) data-reopen @endif>
                <form method="post" action="{{ route('services.management.payments.store', [$tenant->slug, $obligation]) }}">
                    @csrf
                    <input type="hidden" name="row_payment" value="{{ $obligation->id }}">
                    <input type="hidden" name="operation_key" value="{{ $retryPayment ? old('operation_key') : \Illuminate\Support\Str::uuid() }}">
                    <h3 id="payment-title-{{ $obligation->id }}"><i class="ph-duotone ph-wallet" aria-hidden="true"></i> {{ $obligation->direction === 'payable' ? 'Pagar prestador' : 'Registrar recebimento' }} · {{ $obligation->number }} · Saldo R$ {{ number_format($obligation->balance, 2, ',', '.') }}</h3>
                    @if($retryPayment)
                        <ul class="cm-dialog-errors" role="alert">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="sg-fields">
                        <label>Valor<input name="amount" type="number" min=".01" max="{{ $obligation->balance }}" step=".01" value="{{ $retryPayment ? old('amount') : number_format($obligation->balance, 2, '.', '') }}" required></label>
                        <label>Data<input name="payment_date" type="date" value="{{ $retryPayment ? old('payment_date') : today()->toDateString() }}" required></label>
                        <label>Forma<select name="payment_method" required>
                            @foreach(['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'transferencia' => 'Transferência', 'boleto' => 'Boleto', 'cartao' => 'Cartão', 'outro' => 'Outro'] as $method => $label)
                                <option value="{{ $method }}" @selected($retryPayment && old('payment_method') === $method)>{{ $label }}</option>
                            @endforeach
                        </select></label>
                        <label>Conta<select name="bank_account_id" required><option value="">Selecione</option>
                            @foreach($accounts as $account)<option value="{{ $account->id }}" @selected($retryPayment && (int) old('bank_account_id') === (int) $account->id)>{{ $account->name }}</option>@endforeach
                        </select></label>
                    </div>
                    <div class="sg-actions">
                        <button class="sg-button sg-primary" type="submit">Confirmar {{ $obligation->direction === 'payable' ? 'pagamento' : 'recebimento' }}</button>
                        <button class="sg-link" type="button" data-dialog-close>Fechar</button>
                    </div>
                </form>
            </dialog>
    @endforeach
@endif
