@php
    $payment = $allocation->paymentEvent;
    $alternativeAccounts = $accounts->where('id', '!=', $payment?->bank_account_id);
    $dialogId = 'correct-receipt-account-'.$payment?->id;
    $retry = (int) old('account_correction_payment') === (int) $payment?->id && $errors->any();
@endphp
@if($obligation->direction === 'receivable'
    && $payment?->event_type === 'payment' && $payment->status === 'confirmed'
    && $payment->bank_account_id && $payment->cash_movement_id
    && !data_get($payment->metadata, 'payment_plan_installment_id')
    && (float) $payment->amount === (float) $allocation->amount
    && $alternativeAccounts->isNotEmpty()
    && auth()->user()->checkPermissionTo('manage_service_receivables')
    && auth()->user()->checkPermissionTo('reverse_service_payment'))
    <button type="button" class="ds-button secondary" data-open-dialog="{{ $dialogId }}">
        <i class="ph ph-bank" aria-hidden="true"></i> Corrigir conta
    </button>
    <dialog id="{{ $dialogId }}" class="dossier-dialog ds-quick-dialog" aria-labelledby="{{ $dialogId }}-title" @if($retry) data-finance-reopen @endif>
        <header class="ds-dialog-head">
            <h3 id="{{ $dialogId }}-title">Corrigir conta do recebimento</h3>
            <button type="button" class="ds-dialog-close" data-close-dialog="{{ $dialogId }}" aria-label="Fechar"><i class="ph ph-x" aria-hidden="true"></i></button>
        </header>
        <form method="post" class="ds-dialog-body ds-dialog-form" action="{{ route('services.management.payments.correct-account', [$tenantSlug, $payment]) }}">
            @csrf
            <input type="hidden" name="operation_key" value="{{ $retry ? old('operation_key') : \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="account_correction_payment" value="{{ $payment->id }}">
            <p>A conta atual, <strong>{{ $payment->bankAccount?->name ?? 'conta original' }}</strong>, receberá um estorno. O valor de <strong>R$ {{ number_format((float) $payment->amount, 2, ',', '.') }}</strong> será registrado na conta correta, com a data original.</p>
            @if($retry)<ul role="alert">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>@endif
            <label class="ds-field"><span class="ds-label">Conta correta</span>
                <select class="ds-control" name="bank_account_id" required>
                    <option value="">Selecione a conta</option>
                    @foreach($alternativeAccounts as $account)
                        <option value="{{ $account->id }}" @selected($retry && (int) old('bank_account_id') === (int) $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ds-field"><span class="ds-label">Motivo da correção</span>
                <input class="ds-control" name="reason" required minlength="5" maxlength="500" value="{{ $retry ? old('reason') : '' }}">
            </label>
            <button class="ds-button primary" type="submit">Confirmar correção</button>
        </form>
    </dialog>
@endif
