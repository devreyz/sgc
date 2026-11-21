@php
    $personName = data_get($origin?->beneficiary_snapshot, 'name') ?: $beneficiary?->nickname
        ?: $beneficiary?->display_name ?: data_get($previous?->terms_snapshot, 'party.name');
    $selectedIds = array_map('intval', old('obligation_ids', $requiredIds));
    $oldSchedule = collect(old('installments', []));
    $oldEntry = $oldSchedule->firstWhere('kind', 'entry');
    $oldInstallments = $oldSchedule->where('kind', 'installment')->values();
@endphp

@if($origin || $beneficiary || $previous)
    <section class="billing-card billing-stack" aria-label="Beneficiário">
        <div>
            <h2>{{ $personName ?: 'Beneficiário' }}</h2>
            @if($origin)<p class="billing-muted">OS de origem {{ $origin->number }} · Saldo {{ $money($originDebt?->balance ?? 0) }}</p>@endif
            @if($previous)<p class="billing-muted">Acordo de origem {{ $previous->number }}</p>@endif
        </div>
        @if(! $origin && ! $previous)<a href="{{ $createUrl }}" class="billing-muted underline">Buscar outro beneficiário</a>@endif
        @if($activeAgreement)
            <a class="billing-button justify-self-start" href="{{ route('services.management.agreements.show', [$tenantSlug, $activeAgreement, 'origin' => $origin->id]) }}">Abrir acordo {{ $activeAgreement->number }}</a>
        @elseif($obligations->isEmpty())
            <p>Nenhum saldo elegível para negociação.</p>
        @endif
    </section>
@else
    <section class="billing-card billing-stack">
        <form method="get" action="{{ $createUrl }}" class="billing-actions items-end">
            <label class="billing-field flex-1 min-w-0">Beneficiário ou número da OS
                <input name="q" type="search" value="{{ request('q') }}" maxlength="100" required placeholder="Buscar pelo nome, apelido ou OS">
            </label>
            <button class="billing-button primary"><i class="ph ph-magnifying-glass" aria-hidden="true"></i> Buscar</button>
        </form>
        @if(request()->filled('q'))
            @forelse($matches as $match)
                @php
                    $matchOrder = $match->execution?->order;
                @endphp
                <a class="billing-row" href="{{ route('services.management.agreements.create', [$tenantSlug] + ($match->associate_id ? ['beneficiary' => $match->associate_id] : ['origin' => $matchOrder?->id])) }}">
                    <strong>{{ $match->associate?->nickname ?: $match->associate?->display_name ?: data_get($match->party_snapshot, 'name', 'Beneficiário') }}</strong>
                    <span class="billing-muted">{{ $match->associate_id ? 'Ver saldos em aberto' : $matchOrder?->number }}</span>
                    <i class="ph ph-caret-right" aria-hidden="true"></i>
                </a>
            @empty
                <p class="billing-muted">Nenhum beneficiário com saldo disponível foi encontrado.</p>
            @endforelse
        @endif
    </section>
@endif

@if($obligations->isNotEmpty())
    <form id="agreement-wizard" method="post" action="{{ route('services.management.agreements.store', $tenantSlug) }}" class="billing-card billing-stack" data-billing-submit>
        @csrf
        <input type="hidden" name="operation_key" value="{{ old('operation_key', $uuid()) }}">
        <input type="hidden" name="origin" value="{{ $origin?->id }}">
        <input type="hidden" name="supersedes" value="{{ $previous?->id }}">
        <ol class="billing-steps" aria-label="Etapas">
            <li aria-current="step">1 · Dívidas</li>
            <li>2 · Condições</li>
            <li>3 · Conferência</li>
        </ol>
        <section class="billing-stack" data-billing-step>
            <h2>Ordens com saldo</h2>
            <div>
                @foreach($obligations as $debt)
                    @php
                        $debtOrder = $debt->execution->order;
                        $required = in_array($debt->id, $requiredIds);
                    @endphp
                    <div class="billing-row">
                        <label class="billing-pick">
                            @if($required)<input type="hidden" name="obligation_ids[]" value="{{ $debt->id }}">@endif
                            <input type="checkbox" @if(! $required) name="obligation_ids[]" value="{{ $debt->id }}" @endif
                                data-debt="{{ $debt->balanceCents() }}" data-number="{{ $debtOrder->number }}"
                                @checked($required || in_array($debt->id, $selectedIds)) @disabled($required)>
                            <span>
                                <strong>{{ $debtOrder->number }}</strong>
                                <small class="billing-muted block">{{ $debtOrder->service?->name }}{{ $debt->id === $originDebt?->id ? ' · Ordem de origem' : ($required ? ' · Acordo anterior' : '') }}</small>
                            </span>
                        </label>
                        <dl class="billing-facts">
                            <div><dt class="billing-muted">Cobrado</dt><dd>{{ $money($debt->total_amount) }}</dd></div>
                            <div><dt class="billing-muted">Recebido</dt><dd>{{ $money($debt->paid_amount) }}</dd></div>
                            <div><dt class="billing-muted">Saldo</dt><dd><strong>{{ $money($debt->balance) }}</strong></dd></div>
                        </dl>
                    </div>
                @endforeach
            </div>
            <div class="billing-actions justify-between">
                <p>Total selecionado <strong data-debt-total>R$ 0,00</strong></p>
                <button type="button" class="billing-button primary" data-step-next>Definir condições</button>
            </div>
        </section>

        <section class="billing-stack" data-billing-step hidden>
            <h2>Condições</h2>
            <div class="billing-fields">
                <label class="billing-field">Entrada prevista (opcional)
                    <input data-entry type="number" min="0" step=".01" value="{{ $oldEntry['amount'] ?? '' }}" inputmode="decimal" placeholder="0,00">
                </label>
                <label class="billing-field" data-entry-due-field>Vencimento da entrada
                    <input data-entry-due type="date" value="{{ $oldEntry['due_date'] ?? today()->toDateString() }}">
                </label>
                <label class="billing-field">Parcelas mensais restantes
                    <input data-count type="number" min="0" max="120" step="1" value="{{ $oldSchedule->isEmpty() ? 1 : $oldInstallments->count() }}" inputmode="numeric">
                </label>
                <label class="billing-field" data-first-due-field>Primeiro vencimento
                    <input data-first-due type="date" value="{{ $oldInstallments->first()['due_date'] ?? today()->addMonthNoOverflow()->toDateString() }}">
                </label>
            </div>
            <details data-custom>
                <summary>Personalizar parcelas</summary>
                <div class="billing-stack mt-2" data-custom-rows></div>
                <button type="button" class="billing-button mt-2" data-reset-custom>Restaurar valores automáticos</button>
            </details>
            <div class="billing-actions justify-between">
                <button type="button" class="billing-button" data-step-back>Voltar às dívidas</button>
                <button type="button" class="billing-button primary" data-step-next>Conferir negociação</button>
            </div>
        </section>

        <section class="billing-stack" data-billing-step hidden>
            <h2>Conferir antes de salvar</h2>
            <p>{{ $personName }} · <span data-review-orders></span></p>
            <dl class="billing-summary">
                <div><dt class="billing-muted">Total devido</dt><dd data-debt-total></dd></div>
                <div><dt class="billing-muted">Entrada prevista</dt><dd data-review-entry></dd></div>
                <div><dt class="billing-muted">Restante em parcelas</dt><dd data-review-remaining></dd></div>
                <div><dt class="billing-muted">Total do acordo</dt><dd data-review-total></dd></div>
            </dl>
            <ol class="billing-stack" data-review-schedule></ol>
            <div data-installment-inputs></div>
            @if($canPay)
                <label class="billing-pick" data-receive-entry hidden>
                    <input type="checkbox" name="received_entry" value="1" @checked(old('received_entry'))>
                    <span>Já recebi a entrada. Abrir o registro de pagamento após salvar.</span>
                </label>
            @endif
            <div class="billing-actions justify-between">
                <button type="button" class="billing-button" data-step-back>Editar condições</button>
                <button class="billing-button primary">Confirmar negociação</button>
            </div>
        </section>
        <p class="billing-error" data-wizard-error role="alert" hidden></p>
    </form>
    <script>
    (() => {
        const form = document.getElementById('agreement-wizard');
        const query = (selector) => form.querySelector(selector);
        const steps = [...form.querySelectorAll('[data-billing-step]')];
        const debts = [...form.querySelectorAll('[data-debt]')];
        const entry = query('[data-entry]');
        const entryDue = query('[data-entry-due]');
        const count = query('[data-count]');
        const firstDue = query('[data-first-due]');
        const custom = query('[data-custom]');
        const rows = query('[data-custom-rows]');
        const error = query('[data-wizard-error]');
        const edits = new Map(@js($oldInstallments->all()).map((item, index) => [index, {due_date: item.due_date, amount: item.amount}]));
        const formatter = new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'});
        const money = (value) => formatter.format(value / 100);
        const cents = (value) => {
            const [whole, fraction = ''] = String(value).split('.');
            return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
        };
        const selected = () => debts.filter((input) => input.checked);
        const total = () => selected().reduce((amount, input) => amount + Number(input.dataset.debt), 0);
        let currentStep = 0;

        function monthlyDate(value, offset) {
            if (!value) return '';
            const [year, month, day] = value.split('-').map(Number);
            const target = new Date(Date.UTC(year, month - 1 + offset, 1));
            const lastDay = new Date(Date.UTC(target.getUTCFullYear(), target.getUTCMonth() + 1, 0)).getUTCDate();
            target.setUTCDate(Math.min(day, lastDay));
            return target.toISOString().slice(0, 10);
        }

        function automaticRows() {
            const quantity = Math.min(120, Math.max(0, Number(count.value) || 0));
            const remainder = Math.max(0, total() - cents(entry.value));
            const base = quantity ? Math.floor(remainder / quantity) : 0;
            return Array.from({length: quantity}, (_, index) => ({
                due_date: monthlyDate(firstDue.value, index),
                amount: ((base + (index < remainder % quantity ? 1 : 0)) / 100).toFixed(2),
                ...edits.get(index),
            }));
        }

        function refresh() {
            form.querySelectorAll('[data-debt-total]').forEach((label) => label.textContent = money(total()));
            query('[data-entry-due-field]').hidden = cents(entry.value) <= 0;
            query('[data-first-due-field]').hidden = Number(count.value) <= 0;
            rows.replaceChildren();
            if (!custom.open) return;
            automaticRows().forEach((item, index) => {
                const row = document.createElement('div');
                row.className = 'billing-fields';
                for (const [key, type, caption] of [['due_date', 'date', 'Vencimento'], ['amount', 'number', 'Valor']]) {
                    const label = document.createElement('label');
                    label.className = 'billing-field';
                    label.textContent = 'Parcela ' + (index + 1) + ' · ' + caption;
                    const input = document.createElement('input');
                    input.type = type;
                    input.value = item[key];
                    if (type === 'number') {
                        input.min = '.01';
                        input.step = '.01';
                        input.inputMode = 'decimal';
                    }
                    input.addEventListener('input', () => {
                        edits.set(index, {...edits.get(index), [key]: input.value});
                    });
                    label.append(input);
                    row.append(label);
                }
                rows.append(row);
            });
        }

        function fail(message) {
            error.textContent = message;
            error.hidden = false;
        }

        function showStep(index) {
            currentStep = index;
            error.hidden = true;
            steps.forEach((step, position) => step.hidden = position !== index);
            form.querySelectorAll('.billing-steps li').forEach((label, position) => {
                if (position === index) label.setAttribute('aria-current', 'step');
                else label.removeAttribute('aria-current');
            });
            const heading = steps[index].querySelector('h2');
            heading.tabIndex = -1;
            heading.focus();
        }

        function review() {
            const entryCents = cents(entry.value);
            const quantity = Number(count.value);
            if (!entry.checkValidity() || !count.value || !count.checkValidity() || (entryCents > 0 ? 1 : 0) + quantity > 120) {
                fail('Confira a entrada e a quantidade de parcelas. O acordo aceita até 120 lançamentos.');
                return false;
            }
            if (entryCents > total() || (entryCents > 0 && !entryDue.value)) {
                fail('Informe uma entrada de até o total devido e seu vencimento.');
                return false;
            }
            const schedule = automaticRows().map((item) => ({...item, kind: 'installment'}));
            if (entryCents > 0) schedule.unshift({amount: (entryCents / 100).toFixed(2), due_date: entryDue.value, kind: 'entry'});
            if (!schedule.length || schedule.some((item) => !item.due_date || !/^\d+(?:\.\d{1,2})?$/.test(item.amount) || cents(item.amount) <= 0)
                || schedule.reduce((sum, item) => sum + cents(item.amount), 0) !== total()) {
                fail('Preencha os vencimentos e confira se a soma da entrada e das parcelas é igual ao saldo selecionado.');
                return false;
            }
            query('[data-review-orders]').textContent = selected().map((input) => input.dataset.number).join(', ');
            query('[data-review-entry]').textContent = money(entryCents);
            query('[data-review-remaining]').textContent = money(total() - entryCents);
            query('[data-review-total]').textContent = money(total());
            const receiveEntry = query('[data-receive-entry]');
            if (receiveEntry) {
                receiveEntry.hidden = entryCents === 0;
                if (entryCents === 0) receiveEntry.querySelector('input').checked = false;
            }
            const preview = query('[data-review-schedule]');
            const inputs = query('[data-installment-inputs]');
            preview.replaceChildren();
            inputs.replaceChildren();
            schedule.forEach((item, index) => {
                const row = document.createElement('li');
                const number = index + (entryCents ? 0 : 1);
                row.textContent = (item.kind === 'entry' ? 'Entrada prevista' : 'Parcela ' + number)
                    + ' · ' + item.due_date.split('-').reverse().join('/') + ' · ' + money(cents(item.amount));
                preview.append(row);
                Object.entries(item).forEach(([key, value]) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'installments[' + index + '][' + key + ']';
                    input.value = value;
                    inputs.append(input);
                });
            });
            return true;
        }

        [...debts, entry, count, firstDue].forEach((control) => control.addEventListener('input', refresh));
        custom.addEventListener('toggle', refresh);
        query('[data-reset-custom]').addEventListener('click', () => { edits.clear(); refresh(); });
        form.querySelectorAll('[data-step-back]').forEach((button) => button.addEventListener('click', () => showStep(currentStep - 1)));
        form.querySelectorAll('[data-step-next]').forEach((button) => button.addEventListener('click', () => {
            if (currentStep === 0 && total() === 0) return fail('Selecione pelo menos uma ordem com saldo.');
            if (currentStep === 1 && !review()) return;
            showStep(currentStep + 1);
        }));
        form.addEventListener('submit', (event) => {
            if (currentStep !== 2 || !review()) event.preventDefault();
        });
        form.noValidate = true;
        if (edits.size) custom.open = true;
        refresh();
    })();
    </script>
@endif
