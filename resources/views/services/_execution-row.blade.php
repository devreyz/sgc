@php
    $execution = $order->execution;
    $retry = (int) old('row_order') === (int) $order->id && $errors->any();
@endphp
@if($canEdit && in_array($execution?->status, ['draft', 'in_progress', 'rejected'], true))
    <tr role="row" class="sg-inline" id="execution-{{ $order->id }}" @if(!$retry) hidden @endif>
        <td role="cell" colspan="{{ $columnCount }}">
            <form method="post" action="{{ route('services.management.execution.row', [$tenant->slug, $order]) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="row_order" value="{{ $order->id }}">
                <input type="hidden" name="lock_version" value="{{ $retry ? old('lock_version', $execution->lock_version) : $execution->lock_version }}">
                <input type="hidden" name="operation_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                <h3 class="font-bold"><i class="ph ph-pencil-simple" aria-hidden="true"></i> Execução · {{ $order->number }}</h3>
                <div class="sg-fields">
                    <label>Dia em que foi executado
                        <input name="execution_date" type="date" required max="{{ today()->toDateString() }}"
                            value="{{ ($retry ? old('execution_date') : null) ?? ($order->execution_date ?? $order->scheduled_date ?? $order->scheduled_at ?? today())->format('Y-m-d') }}">
                    </label>
                    <label>Hora inicial<input type="time" name="start_time" value="{{ $retry ? old('start_time') : substr((string) $order->start_time, 0, 5) }}"></label>
                    <label>Hora final<input type="time" name="end_time" value="{{ $retry ? old('end_time') : substr((string) $order->end_time, 0, 5) }}"></label>
                    @foreach(data_get($execution->catalog_snapshot, 'fields', []) as $definition)
                        @if(($definition['visible_to_management'] ?? true) && in_array($definition['phase'] ?? null, ['start', 'execution', 'finish'], true) && !in_array($definition['type'] ?? null, ['image', 'file', 'signature'], true))
                            @include('services._order-field', [
                                'field' => (object) $definition,
                                'fieldPrefix' => 'values',
                                'fieldValue' => ($retry ? old('values.'.$definition['key']) : null) ?? data_get($execution->values, $definition['key'], $definition['default_value'] ?? ''),
                            ])
                        @endif
                    @endforeach
                </div>
                <div class="sg-actions">
                    <button class="sg-button sg-primary" type="submit" name="intent" value="save" formnovalidate>Salvar campos</button>
                    @if($canOperate)
                        <button class="sg-button" type="submit" name="intent" value="submit">{{ data_get($execution->catalog_snapshot, 'review_mode') === 'automatic' ? 'Concluir e validar' : 'Enviar para conferência' }}</button>
                        <a class="sg-link" href="{{ route('services.management.execute', [$tenant->slug, $order]) }}">Execução completa / anexos</a>
                    @endif
                    <button class="sg-link" type="button" data-row-close>Fechar</button>
                </div>
            </form>
        </td>
    </tr>
@endif
@if($accounts->isNotEmpty() && $execution?->isFrozen())
    @foreach($execution->obligations as $obligation)
        @continue($negotiated->contains($obligation->id) || !in_array($obligation->status, ['open', 'partially_paid'], true) || !auth()->user()->checkPermissionTo($obligation->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables'))
        @php($retryPayment = (int) old('row_payment') === (int) $obligation->id && $errors->any())
        <tr role="row" class="sg-inline" id="payment-{{ $obligation->id }}" @if(!$retryPayment) hidden @endif>
            <td role="cell" colspan="{{ $columnCount }}">
                <form method="post" action="{{ route('services.management.payments.store', [$tenant->slug, $obligation]) }}">
                    @csrf
                    <input type="hidden" name="row_payment" value="{{ $obligation->id }}">
                    <input type="hidden" name="operation_key" value="{{ $retryPayment ? old('operation_key') : \Illuminate\Support\Str::uuid() }}">
                    <h3 class="font-bold"><i class="ph ph-wallet" aria-hidden="true"></i> {{ $obligation->direction === 'payable' ? 'Pagar prestador' : 'Registrar recebimento' }} · {{ $obligation->number }} · Saldo R$ {{ number_format($obligation->balance, 2, ',', '.') }}</h3>
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
                        <button class="sg-link" type="button" data-row-close>Fechar</button>
                    </div>
                </form>
            </td>
        </tr>
    @endforeach
@endif
