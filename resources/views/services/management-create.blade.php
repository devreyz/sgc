@extends('layouts.campo')

@section('title', $order ? 'Editar ordem' : 'Lançar serviços')
@section('page-title', $order ? 'Editar ordem' : 'Lançar serviços')
@section('user-role', 'Gestão de serviços')

@php
    $tenant = request()->route('tenant');
    $canPrice = auth()->user()->checkPermissionTo('view_service_financials');
    $bentoNavigation = \App\Support\PortalNavigation::make('services', 'new', $tenant->slug);
    $saved = $order ? [
        'service_version_id' => $order->service_version_id, 'associate_id' => $order->associate_id,
        'beneficiary_name' => data_get($order->beneficiary_snapshot, 'name'), 'service_provider_id' => $order->service_provider_id,
        'scheduled_at' => ($order->scheduled_at ?? $order->scheduled_date)?->format('Y-m-d\TH:i'), 'location' => $order->location,
        'execution_date' => $order->execution_date?->toDateString(),
        'asset_id' => $order->asset_id,
        'order_data' => $order->order_data,
        'customer_rate' => $canPrice && data_get($order->execution->catalog_snapshot, 'customer_rate') != $order->serviceVersion?->customer_rate
            ? data_get($order->execution->catalog_snapshot, 'customer_rate') : null,
    ] : [];
    $initial = $order ? [array_replace($saved, session()->getOldInput())] : array_values(old('rows', [$saved]));
    $rates = $versions->mapWithKeys(fn ($version) => [$version->id => [
        'service' => $version->service_id, 'customer' => $canPrice && $version->receivable_enabled ? ($version->customer_rate ?? 0) : null,
        'provider' => $version->payable_enabled,
        'membersOnly' => $version->members_only,
        'method' => $version->customer_pricing_method, 'percentage' => $canPrice ? $version->customer_percentage : null,
        'providerMethod' => $version->provider_pricing_method,
        'providerDefault' => $canPrice ? ($version->provider_pricing_method === 'percent_of_base' ? $version->provider_percentage : $version->default_provider_rate) : null,
        'rates' => $canPrice ? $version->providerRates->mapWithKeys(fn ($rate) => [$rate->service_provider_id => $version->provider_pricing_method === 'percent_of_base'
            ? ($rate->percentage ?? $version->provider_percentage)
            : ($version->provider_pricing_method === 'fixed' ? ($rate->fixed_amount ?? $rate->rate) : ($rate->rate ?? $rate->fixed_amount)) ?? $version->default_provider_rate]) : [],
    ]]);
@endphp

@section('content')
@include('services._workspace-style')
<style>
    .sg-entry [hidden] { display: none !important; }
    .sg-entry .entry-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1rem; padding:clamp(1rem,3vw,1.6rem); border-radius:16px; background:#173c2e; color:#f6faf4; }
    .sg-entry .entry-head h1 { margin:0; color:inherit; font-size:clamp(1.35rem,3vw,1.9rem); }
    .sg-entry .entry-head p { margin:.3rem 0 0; color:#c7dacf; }
    .sg-entry .entry-head .sg-button { background:#ffffff12; color:#f6faf4; border-color:#ffffff55; }
    .sg-entry [data-entry], .sg-entry .entry-orders { display:grid; gap:1rem; min-width:0; }
    .sg-entry .entry-order { display:grid; gap:1rem; min-width:0; border:1px solid var(--ui-color-border); border-radius:14px; background:var(--ui-color-surface); padding:clamp(.8rem,2vw,1.2rem); }
    .sg-entry .entry-order-head, .sg-entry .entry-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.6rem; }
    .sg-entry .entry-order-head { border-bottom:1px solid var(--ui-color-border); padding-bottom:.65rem; }
    .sg-entry .entry-order-head h2 { margin:0; font-size:1rem; }
    .sg-entry .entry-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); align-items:start; gap:.8rem; }
    .sg-entry .entry-fields > *, .sg-entry .entry-beneficiary > * { min-width:0; }
    .sg-entry .entry-beneficiary { grid-column:1/-1; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.8rem; }
    .sg-entry .entry-order label { display:grid; gap:.35rem; }
    .sg-entry :is(input:not([type=checkbox],[type=hidden]),select,textarea) { min-width:0; width:100%; min-height:44px; background:var(--ui-color-surface); color:var(--ui-color-text); }
    .sg-entry .entry-extra { border-top:1px solid var(--ui-color-border); padding-top:.7rem; }
    .sg-entry summary { cursor:pointer; font-weight:700; }
    .sg-entry .entry-extra > .entry-fields { margin-top:.7rem; }
    .sg-entry .entry-footer { padding-top:.7rem; border-top:1px solid var(--ui-color-border); }
    .sg-entry .entry-footer .sg-primary { background:var(--sgc-accent,#d9f172); color:var(--sgc-on-accent,#173c2e); }
    .sg-entry .entry-footer .sg-primary:disabled { opacity:.7; cursor:wait; }
    @media (max-width: 640px) {
        .sg-entry .entry-fields, .sg-entry .entry-beneficiary { grid-template-columns:minmax(0,1fr); }
        .sg-entry .entry-footer .sg-primary { width:100%; }
        .sg-entry dialog.sg-dialog { margin:auto 0 0; width:100%; max-width:none; border-radius:16px 16px 0 0; }
    }
</style>
<div class="service-grid sg-entry">
    <header class="entry-head">
        <div><h1>{{ $order ? 'Editar '.$order->number : 'Lançar serviços' }}</h1>
        <p>{{ $order ? 'Corrija os dados da ordem ou escolha a tarifa que valia para este serviço.' : 'Registre uma ordem ou adicione outras ao mesmo lançamento.' }}</p></div>
        <a class="sg-button" href="{{ route('services.management.index', $tenant->slug) }}">Voltar às ordens</a>
    </header>
    @include('services._workspace-messages')
    @if($versions->isEmpty())
        <p class="sg-panel">Publique um serviço ativo no catálogo para lançar ordens.</p>
    @else
    <form class="sg-panel" data-entry method="post" action="{{ $order ? route('services.management.update', [$tenant->slug, $order]) : route('services.management.batch', $tenant->slug) }}">
        @csrf
        @if($order)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ old('lock_version', $order->execution->lock_version) }}">
        @else
            <div class="sg-actions">
                <button class="sg-button" type="button" data-add><i class="ph ph-plus" aria-hidden="true"></i> Adicionar ordem</button>
                <button class="sg-button" type="button" data-repeat><i class="ph ph-copy" aria-hidden="true"></i> Repetir configuração</button>
                <button class="sg-button" type="button" data-paste><i class="ph ph-clipboard-text" aria-hidden="true"></i> Colar beneficiários</button>
            </div>
            <dialog class="sg-dialog" data-paste-dialog>
                <div>
                    <h2>Colar beneficiários</h2>
                    <p class="sg-hint">Cole um nome por linha. As novas ordens usam a configuração da última ordem.</p>
                    <label>Nomes<textarea data-paste-text rows="7"></textarea></label>
                    <p class="sg-hint" role="status" data-paste-error></p>
                    <div class="sg-actions"><button class="sg-button" type="button" data-paste-close>Voltar</button><button class="sg-button sg-primary" type="button" data-paste-apply>Preencher linhas</button></div>
                </div>
            </dialog>
        @endif
        <div class="entry-orders" data-rows></div>
        <div class="entry-footer">
            <output class="sg-hint" data-count aria-live="polite"></output>
            <button class="sg-button sg-primary" type="submit">{{ $order ? 'Salvar correção' : 'Salvar ordem' }}</button>
        </div>
    </form>

    <template id="sg-row">
        <section class="entry-order">
            <header class="entry-order-head">
                <h2>{{ $order ? 'Dados da ordem' : 'Ordem' }} <span data-line></span></h2>
                @unless($order)<button class="sg-button sg-danger" type="button" data-remove>Remover</button>@endunless
            </header>
            <div class="entry-fields">
                <label>Serviço e versão
                    <select data-key="service_version_id" required>
                        <option value="">Selecione o serviço</option>
                        @foreach($versions as $version)
                            @continue($order && $order->execution->status !== 'draft' && $version->service_id !== $order->service_id)
                            <option value="{{ $version->id }}">{{ $version->service?->name }} · v{{ $version->version }}{{ $version->status === 'retired' ? ' (anterior)' : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Prestador
                    <select data-key="service_provider_id">
                        <option value="">Selecione o prestador</option>
                        @foreach($providers as $provider)<option value="{{ $provider->id }}" data-services="{{ implode(',', $provider->services->modelKeys()) }}">{{ $provider->name }}</option>@endforeach
                    </select>
                </label>
                <div class="entry-beneficiary">
                    <label>Membro
                        <select data-key="associate_id"><option value="">Beneficiário avulso</option>
                            @foreach($associates as $associate)<option value="{{ $associate->id }}" data-name="{{ $associate->nickname ?: $associate->display_name }}">{{ $associate->nickname ?: $associate->display_name }}</option>@endforeach
                        </select>
                    </label>
                    <label>Nome do beneficiário
                        <input data-key="beneficiary_name" maxlength="191" placeholder="Nome ou apelido">
                    </label>
                </div>
                <label>Data da execução <small class="sg-hint">Deixe em branco se não souber.</small>
                    <input type="date" data-key="execution_date" max="{{ today()->toDateString() }}">
                </label>
                <label>Local <small class="sg-hint">Opcional</small><input data-key="location" maxlength="191"></label>
            </div>
            <div data-custom class="entry-fields" hidden></div>
            <details class="entry-extra">
                <summary>Agendamento e detalhes</summary>
                <div class="entry-fields">
                    <label>Agendar para<input type="datetime-local" data-key="scheduled_at"></label>
                    <label>Descrição<input data-field="description" maxlength="2000"></label>
                    <label>Observações<textarea data-field="notes" maxlength="2000" rows="2"></textarea></label>
                    @if($assets->isNotEmpty())
                        <label>Equipamento<select data-key="asset_id"><option value="">Sem equipamento</option>
                            @foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->name }}</option>@endforeach
                        </select></label>
                    @endif
                </div>
            </details>
            <p class="sg-hint" data-price hidden></p>
            <details class="entry-extra" data-rates hidden>
                <summary>Ajustar tarifas desta ordem</summary>
                <div class="entry-fields">
                    <label data-charge>Base da cobrança (R$)<input data-key="customer_rate" type="number" min="0" max="999999999" step=".0001" placeholder="Usar valor do catálogo"></label>
                    <label data-provider-rate>Base do prestador<select data-key="provider_rate_source">
                        <option value="">{{ $order ? 'Manter a base atual' : 'Usar tarifa do prestador, se cadastrada' }}</option>
                        <option value="provider">Tarifa do prestador, se cadastrada</option>
                        <option value="catalog">Tarifa padrão do catálogo</option>
                    </select></label>
                    <label data-override-rate>Tarifa do prestador nesta ordem (R$)
                        <input type="number" data-key="provider_rate" min=".0001" step=".0001" max="999999999" placeholder="Manter tarifa aplicada">
                    </label>
                    @if($order)<label data-reset-rate><span>Voltar à base do catálogo</span><input type="checkbox" data-key="reset_customer_rate" value="1"></label>@endif
                </div>
            </details>
        </section>
    </template>
    @foreach($versions as $version)
        <template id="sg-fields-{{ $version->id }}">
            @foreach($version->fields->where('phase', 'order')->whereNotIn('key', ['location', 'description', 'notes']) as $field)
                @include('services._order-field', ['field' => $field])
            @endforeach
        </template>
    @endforeach
    <script>
    (() => {
        const rows = document.querySelector('[data-rows]');
        const template = document.querySelector('#sg-row');
        const initial = {{ \Illuminate\Support\Js::from($initial) }};
        const rates = {{ \Illuminate\Support\Js::from($rates) }};
        const edit = {{ \Illuminate\Support\Js::from((bool) $order) }};
        const canPrice = {{ \Illuminate\Support\Js::from($canPrice) }};
        const form = rows.closest('form');
        const money = value => new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL', maximumFractionDigits: 4}).format(Number(value));
        const submit = form.querySelector('button[type="submit"]');
        const names = () => {
            [...rows.children].forEach((row, index) => {
                const prefix = edit ? '' : 'rows[' + index + ']';
                const name = key => edit ? key : prefix + '[' + key + ']';
                row.querySelector('[data-line]').textContent = edit ? '' : index + 1;
                row.querySelectorAll('[data-key]').forEach(input => input.name = name(input.dataset.key));
                row.querySelectorAll('[data-field]').forEach(input => input.name = name('order_data') + '[' + input.dataset.field + ']');
                const remove = row.querySelector('[data-remove]');
                if (remove) remove.hidden = rows.children.length === 1;
            });
            const count = form.querySelector('[data-count]');
            count.textContent = edit ? '' : rows.children.length + ' de 30 ordens';
            if (!edit) submit.textContent = rows.children.length === 1 ? 'Salvar ordem' : 'Salvar ' + rows.children.length + ' ordens';
            form.querySelectorAll('[data-add], [data-repeat], [data-paste]').forEach(button => {
                button.disabled = rows.children.length >= 30;
            });
        };
        const add = data => {
            if (rows.children.length >= 30) return;
            const row = template.content.firstElementChild.cloneNode(true);
            rows.append(row);
            const field = key => row.querySelector('[data-key="' + key + '"]');
            const version = field('service_version_id');
            const provider = field('service_provider_id');
            const associate = field('associate_id');
            const beneficiary = field('beneficiary_name');
            const custom = row.querySelector('[data-custom]');
            const customerRate = field('customer_rate');
            const providerSource = field('provider_rate_source');
            const providerOverride = field('provider_rate');
            const savedVersion = {{ \Illuminate\Support\Js::from($order?->service_version_id) }};
            const savedProvider = {{ \Illuminate\Support\Js::from($order?->service_provider_id) }};
            const savedRate = {{ \Illuminate\Support\Js::from($canPrice && $order
                ? data_get($order->execution->catalog_snapshot, data_get($order->execution->catalog_snapshot, 'provider_compensation.method') === 'percent_of_base'
                    ? 'provider_compensation.percentage' : 'provider_compensation.rate')
                : null) }};
            const refresh = () => {
                const rate = rates[version.value];
                for (const option of provider.options) {
                    option.hidden = option.disabled = !!option.value && !option.dataset.services.split(',').includes(String(rate?.service));
                }
                if (provider.selectedOptions[0]?.disabled) provider.value = '';
                provider.required = !!rate?.provider;
                associate.required = !!rate?.membersOnly;
                associate.options[0].textContent = associate.required ? 'Selecione um membro' : 'Beneficiário avulso';
                const versionSelected = !!rate;
                const charges = versionSelected && rate.customer !== null;
                customerRate.disabled = !charges;
                row.querySelector('[data-charge]').hidden = !charges;
                const reset = field('reset_customer_rate');
                if (reset) {
                    reset.disabled = !charges;
                    row.querySelector('[data-reset-rate]').hidden = !charges;
                }
                providerSource.disabled = !canPrice || !rate?.provider;
                row.querySelector('[data-provider-rate]').hidden = providerSource.disabled;
                const customerPrice = reset?.checked ? rate?.customer : (customerRate.value || rate?.customer);
                providerOverride.disabled = providerSource.disabled || !['fixed', 'quantity_x_rate'].includes(rate?.providerMethod);
                row.querySelector('[data-override-rate]').hidden = providerOverride.disabled;
                row.querySelector('[data-rates]').hidden = !charges && providerSource.disabled;
                const keepingRate = edit && !providerSource.value && Number(version.value) === Number(savedVersion)
                    && Number(provider.value) === Number(savedProvider);
                const providerPrice = (!providerOverride.disabled && providerOverride.value) || (keepingRate && savedRate != null ? savedRate
                    : (providerSource.value === 'catalog' ? rate?.providerDefault : (rate?.rates[provider.value] ?? rate?.providerDefault)));
                const customerLabel = charges ? 'Cobrança: ' + money(customerPrice) + (rate.method === 'percent_of_base' ? ' × ' + rate.percentage + '%' : '') : '';
                const providerLabel = rate?.provider && providerPrice != null ? 'Prestador: ' + (rate.providerMethod === 'percent_of_base' ? providerPrice + '%' : money(providerPrice)) : '';
                const price = row.querySelector('[data-price]');
                price.textContent = [customerLabel, providerLabel].filter(Boolean).join(' · ');
                price.hidden = !price.textContent;
            };
            const loadFields = (values = {}) => {
                custom.replaceChildren();
                const fields = document.getElementById('sg-fields-' + version.value);
                if (fields) custom.append(fields.content.cloneNode(true));
                custom.querySelectorAll('[name]').forEach(input => {
                    input.dataset.field = input.name.slice(11, -1);
                    if (Object.hasOwn(values, input.dataset.field)) input.value = values[input.dataset.field] ?? '';
                });
                custom.hidden = !custom.childElementCount;
                names();
            };
            row.querySelectorAll('[data-key]').forEach(input => {
                if (input.type === 'checkbox') input.checked = !!data[input.dataset.key];
                else input.value = data[input.dataset.key] ?? '';
            });
            row.querySelectorAll('[data-field]').forEach(input => input.value = data.order_data?.[input.dataset.field] ?? '');
            field('scheduled_at').closest('details').open = Boolean(data.scheduled_at || data.asset_id || data.order_data?.description || data.order_data?.notes);
            row.querySelector('[data-rates]').open = Boolean(data.customer_rate || data.provider_rate || data.provider_rate_source || data.reset_customer_rate);
            beneficiary.required = !associate.value;
            associate.addEventListener('change', () => {
                beneficiary.value = associate.selectedOptions[0]?.dataset.name || '';
                beneficiary.required = !associate.value;
            });
            const customValues = {...data.order_data};
            version.addEventListener('change', () => {
                custom.querySelectorAll('[data-field]').forEach(input => customValues[input.dataset.field] = input.value);
                customerRate.value = '';
                providerSource.value = '';
                providerOverride.value = '';
                loadFields(customValues);
                refresh();
            });
            [provider, providerSource, providerOverride, customerRate, field('reset_customer_rate')].forEach(input => input?.addEventListener('input', refresh));
            row.querySelector('[data-remove]')?.addEventListener('click', () => { row.remove(); names(); });
            loadFields(data.order_data);
            refresh();
            return row;
        };
        (initial.length ? initial : [{}]).forEach(add);
        const scheduled = initial[0]?.scheduled_at || '';
        form.querySelector('[data-add]')?.addEventListener('click', () => add({scheduled_at: scheduled})?.querySelector('select')?.focus());
        const lastSettings = () => {
            const last = rows.children[rows.children.length - 1];
            return Object.fromEntries(['service_version_id', 'service_provider_id', 'scheduled_at', 'execution_date', 'location', 'customer_rate', 'provider_rate_source', 'provider_rate', 'asset_id']
                .map(key => [key, last.querySelector('[data-key="' + key + '"]')?.value]));
        };
        form.querySelector('[data-repeat]')?.addEventListener('click', () => add(lastSettings())?.querySelector('[data-key="associate_id"]')?.focus());
        const paste = form.querySelector('[data-paste-dialog]');
        form.querySelector('[data-paste]')?.addEventListener('click', () => paste.showModal());
        paste?.querySelector('[data-paste-close]').addEventListener('click', () => paste.close());
        paste?.querySelector('[data-paste-apply]').addEventListener('click', () => {
            const input = paste.querySelector('[data-paste-text]');
            const error = paste.querySelector('[data-paste-error]');
            const names = input.value.split(/\r?\n/).map(name => name.trim()).filter(Boolean);
            const base = lastSettings();
            const last = rows.children[rows.children.length - 1];
            const beneficiary = last.querySelector('[data-key="beneficiary_name"]');
            const useLast = !beneficiary.value && !last.querySelector('[data-key="associate_id"]').value;
            if (!base.service_version_id || rates[base.service_version_id]?.membersOnly) {
                error.textContent = 'Escolha na última ordem um serviço que aceite beneficiários avulsos.';
                return;
            }
            if (!names.length || names.some(name => name.length > 191 || name.includes('\t')) || names.length > 30 - rows.children.length + Number(useLast)) {
                error.textContent = 'Cole um nome por linha, com até 191 caracteres por nome e até 30 ordens no total.';
                return;
            }
            if (useLast) beneficiary.value = names.shift();
            names.forEach(name => add({...base, beneficiary_name: name}));
            input.value = '';
            error.textContent = '';
            paste.close();
        });
        form.addEventListener('invalid', event => {
            const details = event.target.closest('details');
            if (details) details.open = true;
        }, true);
        form.addEventListener('submit', event => {
            if (submit.disabled) {
                event.preventDefault();
                return;
            }
            submit.disabled = true;
            form.setAttribute('aria-busy', 'true');
            submit.textContent = 'Salvando…';
        });
        window.addEventListener('pageshow', event => {
            if (!event.persisted) return;
            submit.disabled = false;
            form.removeAttribute('aria-busy');
            submit.textContent = 'Salvar correção';
            names();
        });
    })();
    </script>
    @endif
</div>
@endsection
