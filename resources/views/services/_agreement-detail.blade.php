@php
    $plan = $agreement->plan;
    $installments = $plan?->installments ?? collect();
    $isActive = $agreement->status === 'active' && $plan?->status === 'active';
    $pending = $installments->filter(fn ($item) => $item->status === 'scheduled' && $item->balanceCents($events) > 0);
    $received = $installments->sum(fn ($item) => $item->receivedCents($events)) / 100;
    $balance = $isActive ? $pending->sum(fn ($item) => $item->balanceCents($events)) / 100 : 0;
    $nextDue = $isActive ? $pending->sortBy('due_date')->first()?->due_date : null;
    $canCancel = $isActive && $received == 0 && $installments->every(fn ($item) => $item->status === 'scheduled');
    $label = fn ($item) => $item->kind === 'entry' ? 'Entrada' : 'Parcela '.($item->number - ($installments->first()?->kind === 'entry' ? 1 : 0));
    $payLabel = fn ($event) => \App\Enums\PaymentMethod::tryFrom($event->payment_method)?->getLabel() ?? $event->payment_method;
@endphp

<section class="billing-card billing-stack" aria-label="Resumo do acordo">
    <dl class="billing-summary">
        <div><dt class="billing-muted">Valor original</dt><dd>{{ $money($agreement->original_amount) }}</dd></div>
        <div><dt class="billing-muted">Recebido neste acordo</dt><dd>{{ $money($received) }}</dd></div>
        <div><dt class="billing-muted">Saldo deste acordo</dt><dd>{{ $money($balance) }}</dd></div>
        <div><dt class="billing-muted">Próximo vencimento</dt><dd>{{ $nextDue?->format('d/m/Y') ?? 'Sem vencimento' }}</dd></div>
    </dl>
    <div class="billing-actions">
        @if($isActive && $pending->isNotEmpty() && $canPay)
            <button class="billing-button primary" type="button" data-billing-open="agreement-payment"><i class="ph ph-hand-coins" aria-hidden="true"></i> Registrar pagamento</button>
        @endif
        <form method="post" action="{{ route('services.management.agreements.document', [$tenantSlug, $agreement]) }}" target="_blank" data-sgc-pdf-form="Nota interna de cobrança">
            @csrf
            <button class="billing-button" type="submit">Nota interna de cobrança</button>
            <small data-sgc-pdf-error role="alert"></small>
        </form>
        @if($canPay && $events->isNotEmpty())<a class="billing-button" href="#agreement-receipts">Recibos</a>@endif
        @if($isActive && $pending->isNotEmpty())
            <a class="billing-button" href="{{ route('services.management.agreements.create', [$tenantSlug, 'supersedes' => $agreement->id]) }}">Renegociar saldo</a>
        @endif
        @if($canCancel)
            <button type="button" class="billing-button billing-error" data-billing-open="agreement-cancel">Cancelar acordo</button>
        @endif
    </div>
    @if($agreement->supersedes)
        <a class="billing-muted underline" href="{{ route('services.management.agreements.show', [$tenantSlug, $agreement->supersedes]) }}">Ver acordo anterior {{ $agreement->supersedes->number }}</a>
    @endif
</section>

<section class="billing-card billing-stack">
    <h2>Parcelas</h2>
    <div>
        @forelse($installments as $item)
            @php
                $pendingCents = $item->status === 'cancelled' ? 0 : $item->balanceCents($events);
            @endphp
            <div class="billing-row">
                <div>
                    <strong>{{ $label($item) }}</strong>
                    <span class="billing-muted block">{{ $item->due_date?->format('d/m/Y') }} · {{ $item->financialLabel($events) }}</span>
                </div>
                <dl class="billing-facts">
                    <div><dt class="billing-muted">Previsto</dt><dd>{{ $money($item->amount) }}</dd></div>
                    <div><dt class="billing-muted">Recebido</dt><dd>{{ $money($item->receivedCents($events) / 100) }}</dd></div>
                    <div><dt class="billing-muted">Saldo</dt><dd><strong>{{ $money($pendingCents / 100) }}</strong></dd></div>
                </dl>
                <button type="button" class="billing-button justify-self-start" data-billing-open="agreement-item-{{ $item->id }}" aria-label="Ver {{ $label($item) }}">Detalhes</button>
            </div>
            <dialog id="agreement-item-{{ $item->id }}" aria-labelledby="installment-title-{{ $item->id }}">
                <div class="billing-stack">
                    <div class="billing-actions justify-between">
                        <h2 id="installment-title-{{ $item->id }}">{{ $label($item) }}</h2>
                        <button class="billing-button" type="button" data-billing-close aria-label="Fechar"><i class="ph ph-x" aria-hidden="true"></i></button>
                    </div>
                    <p class="billing-muted">{{ $item->financialLabel($events) }} · Vence em {{ $item->due_date?->format('d/m/Y') }} · Saldo {{ $money($pendingCents / 100) }}</p>
                    @forelse($events->filter(fn ($event) => $item->eventCents($event) > 0) as $event)
                        <div class="billing-actions justify-between">
                            <span>{{ $event->payment_date->format('d/m/Y') }} · {{ $money($item->eventCents($event) / 100) }}{{ $event->status === 'reversed' ? ' · Estornado' : '' }}</span>
                            @if($canPay)
                                <a class="billing-button" data-sgc-pdf target="_blank" rel="noopener" href="{{ route('services.management.payments.receipt', [$tenantSlug, $event, 'inline' => 1]) }}">Recibo</a>
                            @endif
                        </div>
                    @empty
                        <p class="billing-muted">Nenhum recebimento nesta parcela.</p>
                    @endforelse
                    @if($isActive && $pendingCents > 0)
                        <div class="billing-actions">
                            @if($canPay)
                                <button class="billing-button primary" type="button" data-pay-installment="{{ $item->id }}" data-pay-amount="{{ \App\Services\Services\ServiceMoney::decimal($pendingCents) }}">Registrar recebimento</button>
                            @endif
                            @if($item->verificationIdentity)
                                <a class="billing-button" href="{{ route('financial-documents.show', $item->verificationIdentity->public_id) }}" target="_blank" rel="noopener">Cobrança verificável</a>
                            @endif
                        </div>
                        @if($item->receivedCents($events) === 0)
                            <details>
                                <summary>Alterar vencimento</summary>
                                <form class="billing-stack mt-2" method="post" action="{{ route('services.management.agreements.installments.update', [$tenantSlug, $agreement, $item]) }}" data-billing-submit>
                                    @csrf
                                    @method('PATCH')
                                    <label class="billing-field">Novo vencimento<input required name="due_date" type="date" value="{{ $item->due_date?->toDateString() }}"></label>
                                    <label class="billing-field">Motivo<input required minlength="5" maxlength="500" name="reason"></label>
                                    <button class="billing-button justify-self-start">Salvar vencimento</button>
                                </form>
                            </details>
                        @endif
                    @endif
                </div>
            </dialog>
        @empty
            <p class="billing-muted">Nenhuma parcela cadastrada.</p>
        @endforelse
    </div>
</section>

<section class="billing-card billing-stack">
    <h2>Ordens incluídas</h2>
    @forelse(data_get($agreement->terms_snapshot, 'obligations') ?: ($plan?->obligations?->map(fn ($debt) => ['order_number' => $debt->execution?->order?->number, 'service' => $debt->execution?->order?->service?->name, 'included_amount' => $debt->pivot?->included_amount]) ?? []) as $debt)
        <div class="billing-row">
            <strong>{{ $debt['order_number'] ?? $debt['number'] ?? 'Ordem de serviço' }}</strong>
            <span>{{ $debt['service'] ?? '' }}</span>
            <span>{{ $money($debt['included_amount'] ?? $debt['balance'] ?? 0) }}</span>
        </div>
    @empty
        <p class="billing-muted">Nenhuma ordem vinculada.</p>
    @endforelse
</section>

@if($canPay && $events->isNotEmpty())
    <section class="billing-card billing-stack" id="agreement-receipts">
        <h2>Recebimentos e recibos</h2>
        @foreach($events as $event)
            <div class="billing-row">
                <div>
                    <strong>{{ $money($event->amount) }}</strong>
                    <div class="billing-muted">{{ $event->payment_date?->format('d/m/Y') }} · {{ $payLabel($event) }}{{ $event->status === 'reversed' ? ' · Estornado' : '' }}</div>
                    @if(filled(data_get($event->metadata, 'notes')))<p class="billing-muted">{{ data_get($event->metadata, 'notes') }}</p>@endif
                </div>
                <div class="billing-muted">
                    @foreach($event->allocations as $allocation)
                        <div>{{ $allocation->obligation?->execution?->order?->number ?? $allocation->obligation?->number }} · {{ $money($allocation->amount) }}</div>
                    @endforeach
                </div>
                <div class="billing-actions">
                    <a class="billing-button" data-sgc-pdf target="_blank" rel="noopener" href="{{ route('services.management.payments.receipt', [$tenantSlug, $event, 'inline' => 1]) }}">Recibo</a>
                    @if($event->receiptProof)
                        <a class="billing-button" href="{{ route('services.management.payments.proof', [$tenantSlug, $event]) }}">Anexo</a>
                    @endif
                    @if($canReverse && $event->status === 'confirmed')
                        <button class="billing-button billing-error" type="button" data-billing-open="agreement-reverse-{{ $event->id }}">Estornar</button>
                    @endif
                </div>
            </div>
            @if($canReverse && $event->status === 'confirmed')
                <dialog id="agreement-reverse-{{ $event->id }}" aria-label="Estornar recebimento">
                    <form method="post" action="{{ route('services.management.payments.reverse', [$tenantSlug, $event]) }}" class="billing-stack" data-billing-submit>
                        @csrf
                        <input type="hidden" name="operation_key" value="{{ $uuid() }}">
                        <h2>Estornar {{ $money($event->amount) }}</h2>
                        <p class="billing-muted">O caixa receberá uma movimentação inversa e o valor correspondente voltará ao saldo em aberto.</p>
                        <label class="billing-field">Motivo<textarea name="reason" minlength="5" maxlength="500" required></textarea></label>
                        <div class="billing-actions"><button class="billing-button" type="button" data-billing-close>Voltar</button><button class="billing-button billing-error">Confirmar estorno</button></div>
                    </form>
                </dialog>
            @endif
        @endforeach
    </section>
@endif

@if($canCancel)
    <dialog id="agreement-cancel" aria-label="Cancelar acordo">
        <form method="post" action="{{ route('services.management.agreements.cancel', [$tenantSlug, $agreement]) }}" class="billing-stack" data-billing-submit>
            @csrf
            @method('DELETE')
            <h2>Cancelar acordo</h2>
            <p class="billing-muted">As ordens permanecerão com seus saldos disponíveis.</p>
            <label class="billing-field">Motivo<textarea name="reason" minlength="5" maxlength="500" required></textarea></label>
            <div class="billing-actions"><button class="billing-button" type="button" data-billing-close>Voltar</button><button class="billing-button billing-error">Confirmar cancelamento</button></div>
        </form>
    </dialog>
@endif

@if($isActive && $pending->isNotEmpty() && $canPay)
    <dialog id="agreement-payment" aria-labelledby="agreement-pay-title">
        <form method="post" enctype="multipart/form-data" action="{{ route('services.management.agreements.payments', [$tenantSlug, $agreement]) }}" class="billing-stack" data-billing-submit>
            @csrf
            <input type="hidden" name="operation_key" value="{{ old('amounts') ? old('operation_key', $uuid()) : $uuid() }}">
            <div class="billing-actions justify-between">
                <h2 id="agreement-pay-title">Registrar pagamento</h2>
                <button class="billing-button" type="button" data-billing-close aria-label="Fechar"><i class="ph ph-x" aria-hidden="true"></i></button>
            </div>
            <p class="billing-muted">Informe quanto foi recebido em cada parcela que deseja pagar.</p>
            @foreach($pending as $item)
                @php
                    $maximum = \App\Services\Services\ServiceMoney::decimal($item->balanceCents($events));
                @endphp
                <div class="billing-actions items-end">
                    <label class="billing-field flex-1">{{ $label($item) }} · saldo {{ $money($maximum) }}
                        <input type="number" data-pay-value="{{ $item->id }}" name="amounts[{{ $item->id }}]" min=".01" max="{{ $maximum }}" step=".01" inputmode="decimal" value="{{ old('amounts.'.$item->id) }}" placeholder="0,00">
                    </label>
                    <button type="button" class="billing-button" data-pay-fill="{{ $item->id }}" data-pay-amount="{{ $maximum }}">Pagar saldo</button>
                </div>
            @endforeach
            <p aria-live="polite">Total recebido <strong data-payment-total>R$ 0,00</strong></p>
            <div class="billing-fields">
                <label class="billing-field">Data do recebimento
                    <input type="date" name="payment_date" required max="{{ today()->toDateString() }}" value="{{ old('payment_date', today()->toDateString()) }}">
                </label>
                <label class="billing-field">Forma de pagamento
                    <select name="payment_method" required>
                        <option value="">Selecione</option>
                        @foreach(\App\Enums\PaymentMethod::cases() as $method)
                            @continue($method === \App\Enums\PaymentMethod::CHEQUE)
                            <option value="{{ $method->value }}" @selected(old('payment_method') === $method->value)>{{ $method->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="billing-field">Conta ou caixa
                    <select name="bank_account_id" required>
                        <option value="">Selecione</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected(old('bank_account_id') == $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="billing-field">Referência da transação (opcional)
                    <input name="reference" maxlength="100" value="{{ old('reference') }}">
                </label>
            </div>
            <label class="billing-field">Observações (opcional)<textarea name="notes" maxlength="500" rows="2">{{ old('notes') }}</textarea></label>
            <label class="billing-field">Comprovante (opcional)<input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
            <div class="billing-actions justify-end">
                <button class="billing-button" type="button" data-billing-close>Voltar</button>
                <button class="billing-button primary" data-payment-submit @disabled($accounts->isEmpty())>Confirmar recebimento</button>
            </div>
            @if($accounts->isEmpty())<p class="billing-error">Cadastre uma conta ativa para confirmar o recebimento.</p>@endif
        </form>
    </dialog>
    <script>
    (() => {
        const dialog = document.getElementById('agreement-payment');
        const values = [...dialog.querySelectorAll('[data-pay-value]')];
        const total = dialog.querySelector('[data-payment-total]');
        const submit = dialog.querySelector('[data-payment-submit]');
        const hasAccount = !submit.disabled;
        const format = new Intl.NumberFormat('pt-BR', {style:'currency',currency:'BRL'});
        function updateTotal() {
            const cents = values.reduce((sum, input) => sum + Math.round(Number(input.value) * 100), 0);
            submit.disabled = !hasAccount || cents <= 0;
            total.textContent = format.format(cents / 100);
        }
        function fill(id, amount) {
            const input = values.find((item) => item.dataset.payValue === id);
            input.value = amount;
            updateTotal();
            input.focus();
        }

        dialog.addEventListener('input', updateTotal);
        document.querySelectorAll('[data-pay-installment], [data-pay-fill]').forEach((button) => button.addEventListener('click', () => {
            if (button.dataset.payInstallment) {
                button.closest('dialog').close();
                dialog.showModal();
            }
            fill(button.dataset.payInstallment || button.dataset.payFill, button.dataset.payAmount);
        }));
        updateTotal();
        const entry = @js(session('receive_agreement_entry') === $agreement->id ? $pending->firstWhere('kind', 'entry')?->id : null);
        if (@js((bool) old('amounts'))) {
            dialog.showModal();
        } else if (entry !== null) {
            const input = values.find((item) => item.dataset.payValue === String(entry));
            if (input) {
                dialog.showModal();
                fill(String(entry), input.max);
            }
        }
    })();
    </script>
@endif
