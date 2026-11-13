@extends('layouts.bento')

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
        'scheduled_at' => $order->scheduled_at?->format('Y-m-d\TH:i'), 'location' => $order->location,
        'asset_id' => $order->asset_id,
        'order_data' => $order->order_data,
        'customer_rate' => $canPrice && data_get($order->execution->catalog_snapshot, 'customer_rate') != $order->serviceVersion?->customer_rate
            ? data_get($order->execution->catalog_snapshot, 'customer_rate') : null,
    ] : ['scheduled_at' => now()->format('Y-m-d\TH:i')];
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
<div class="service-grid sg-entry">
    <header class="sg-toolbar">
        <div><h1>{{ $order ? 'Editar '.$order->number : 'Lançar serviços' }}</h1>
        <p>{{ $order ? 'Revise o cadastro antes de iniciar a execução.' : 'Preencha uma linha por ordem. Use os atalhos para repetir serviço, prestador e data.' }}</p></div>
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
                <button class="sg-button" type="button" data-add>+ Nova linha</button>
                <button class="sg-button" type="button" data-repeat>Repetir configuração da última linha</button>
                <button class="sg-button" type="button" data-paste>Colar beneficiários</button>
                <output class="sg-hint" data-count></output>
            </div>
            <dialog class="sg-dialog" data-paste-dialog>
                <div>
                    <h2>Colar beneficiários</h2>
                    <p class="sg-hint">Cole uma coluna de nomes do Excel, um por linha. As ordens repetem o serviço, o prestador, a data e a base da última linha.</p>
                    <label>Nomes<textarea data-paste-text rows="7"></textarea></label>
                    <p class="sg-hint" role="status" data-paste-error></p>
                    <div class="sg-actions"><button class="sg-button" type="button" data-paste-close>Voltar</button><button class="sg-button sg-primary" type="button" data-paste-apply>Preencher linhas</button></div>
                </div>
            </dialog>
        @endif
        <div class="sg-scroll" role="region" aria-label="Planilha de lançamento" tabindex="0">
            <table class="sg-table">
                <thead><tr><th>#</th><th>Serviço / versão</th><th>Prestador</th><th>Beneficiário</th><th>Data e hora</th><th>Local</th><th>Preço e detalhes</th>@unless($order)<th>Ação</th>@endunless</tr></thead>
                <tbody data-rows></tbody>
            </table>
        </div>
        <div class="sg-actions"><button class="sg-button sg-primary" type="submit">{{ $order ? 'Salvar correção' : 'Salvar ordens' }}</button></div>
    </form>

    <template id="sg-row">
        <tr>
            <td data-line></td>
            <td><span class="sg-mobile-label">Serviço</span><select data-key="service_version_id" aria-label="Serviço" required>
                <option value="">Escolher serviço</option>
                @foreach($versions as $version)
                    <option value="{{ $version->id }}">{{ $version->service?->name }} · v{{ $version->version }}</option>
                @endforeach
            </select></td>
            <td><span class="sg-mobile-label">Prestador</span><select data-key="service_provider_id" aria-label="Prestador">
                <option value="">Escolher prestador</option>
                @foreach($providers as $provider)<option value="{{ $provider->id }}" data-services="{{ implode(',', $provider->services->modelKeys()) }}">{{ $provider->name }}</option>@endforeach
            </select></td>
            <td><span class="sg-mobile-label">Beneficiário</span>
                <select data-key="associate_id" aria-label="Membro"><option value="">Beneficiário avulso</option>
                    @foreach($associates as $associate)<option value="{{ $associate->id }}" data-name="{{ $associate->nickname ?: $associate->display_name }}">{{ $associate->nickname ?: $associate->display_name }}</option>@endforeach
                </select>
                <input data-key="beneficiary_name" aria-label="Nome do beneficiário" maxlength="191" placeholder="Nome ou apelido">
            </td>
            <td><span class="sg-mobile-label">Data e hora</span><input type="datetime-local" data-key="scheduled_at" aria-label="Data e hora" required></td>
            <td><span class="sg-mobile-label">Local</span><input data-key="location" aria-label="Local" maxlength="191" placeholder="Opcional"></td>
            <td><button class="sg-button" type="button" data-details>Detalhes</button><small data-price></small>
                <dialog class="sg-dialog">
                    <div>
                        <h2>Preço e detalhes da ordem</h2>
                        <p class="sg-hint" data-basis></p>
                        <div class="sg-fields">
                            <label data-charge>Valor base nesta ordem (R$)<input data-key="customer_rate" type="number" min="0" max="999999999" step=".0001" placeholder="Usar valor do catálogo"></label>
                            <label data-provider-rate>Base do prestador<select data-key="provider_rate_source">
                                <option value="">{{ $order ? 'Manter a base atual' : 'Tarifa do prestador, se cadastrada' }}</option>
                                <option value="provider">Tarifa do prestador, se cadastrada</option>
                                <option value="catalog">Tarifa padrão do catálogo</option>
                            </select></label>
                            @if($order)<label data-reset-rate><span>Voltar à base do catálogo</span><input type="checkbox" data-key="reset_customer_rate" value="1"></label>@endif
                            <label>Descrição<input data-field="description" maxlength="2000"></label>
                            <label>Observações<textarea data-field="notes" maxlength="2000" rows="2"></textarea></label>
                            @if($assets->isNotEmpty())
                                <label>Equipamento<select data-key="asset_id"><option value="">Sem equipamento</option>
                                    @foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->name }}</option>@endforeach
                                </select></label>
                            @endif
                        </div>
                        <div data-custom class="sg-fields"></div>
                        <div class="sg-actions"><button class="sg-button sg-primary" type="button" data-close>Concluir detalhes</button></div>
                    </div>
                </dialog>
            </td>
            @unless($order)<td><button class="sg-link sg-danger" type="button" data-remove>Remover</button></td>@endunless
        </tr>
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
        const names = () => [...rows.rows].forEach((row, index) => {
            const prefix = edit ? '' : 'rows[' + index + ']';
            const name = key => edit ? key : prefix + '[' + key + ']';
            row.querySelector('[data-line]').textContent = index + 1;
            row.querySelectorAll('[data-key]').forEach(input => input.name = name(input.dataset.key));
            row.querySelectorAll('[data-field]').forEach(input => input.name = name('order_data') + '[' + input.dataset.field + ']');
            const remove = row.querySelector('[data-remove]');
            if (remove) remove.disabled = rows.rows.length === 1;
            const count = form.querySelector('[data-count]');
            if (count) count.textContent = rows.rows.length + ' de 30 linhas';
        });
        const add = data => {
            if (rows.rows.length >= 30) return;
            const row = template.content.firstElementChild.cloneNode(true);
            rows.append(row);
            const field = key => row.querySelector('[data-key="' + key + '"]');
            const version = field('service_version_id');
            const provider = field('service_provider_id');
            const associate = field('associate_id');
            const beneficiary = field('beneficiary_name');
            const dialog = row.querySelector('dialog');
            const custom = row.querySelector('[data-custom]');
            const customerRate = field('customer_rate');
            const providerSource = field('provider_rate_source');
            const refresh = () => {
                const rate = rates[version.value];
                for (const option of provider.options) {
                    option.hidden = option.disabled = !!option.value && !option.dataset.services.split(',').includes(String(rate?.service));
                }
                if (provider.selectedOptions[0]?.disabled) provider.value = '';
                provider.required = !!rate?.provider;
                associate.required = !!rate?.membersOnly;
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
                const providerPrice = providerSource.value === 'catalog' ? rate?.providerDefault : (rate?.rates[provider.value] ?? rate?.providerDefault);
                const customerLabel = charges ? 'Cobrança: ' + money(customerPrice) + (rate.method === 'percent_of_base' ? ' × ' + rate.percentage + '%' : '') : '';
                const providerLabel = rate?.provider && providerPrice != null ? 'Prestador: ' + (rate.providerMethod === 'percent_of_base' ? providerPrice + '%' : money(providerPrice)) : '';
                row.querySelector('[data-price]').textContent = [customerLabel, providerLabel].filter(Boolean).join(' · ');
                row.querySelector('[data-basis]').textContent = [customerLabel, providerLabel].filter(Boolean).join(' · ') || 'Selecione um serviço para conferir a base.';
            };
            const loadFields = (values = {}) => {
                custom.replaceChildren();
                const fields = document.getElementById('sg-fields-' + version.value);
                if (fields) custom.append(fields.content.cloneNode(true));
                custom.querySelectorAll('[name]').forEach(input => {
                    input.dataset.field = input.name.slice(11, -1);
                    if (Object.hasOwn(values, input.dataset.field)) input.value = values[input.dataset.field] ?? '';
                });
                names();
            };
            row.querySelectorAll('[data-key]').forEach(input => {
                if (input.type === 'checkbox') input.checked = !!data[input.dataset.key];
                else input.value = data[input.dataset.key] ?? '';
            });
            row.querySelectorAll('[data-field]').forEach(input => input.value = data.order_data?.[input.dataset.field] ?? '');
            beneficiary.required = !associate.value;
            associate.addEventListener('change', () => {
                beneficiary.value = associate.selectedOptions[0]?.dataset.name || '';
                beneficiary.required = !associate.value;
            });
            version.addEventListener('change', () => {
                customerRate.value = '';
                providerSource.value = '';
                loadFields();
                refresh();
            });
            [provider, providerSource, customerRate, field('reset_customer_rate')].forEach(input => input?.addEventListener('input', refresh));
            row.querySelector('[data-details]').addEventListener('click', () => dialog.showModal());
            row.querySelector('[data-close]').addEventListener('click', () => dialog.close());
            row.querySelector('[data-remove]')?.addEventListener('click', () => { row.remove(); names(); });
            loadFields(data.order_data);
            refresh();
            return row;
        };
        initial.forEach(add);
        const scheduled = initial[0]?.scheduled_at || '';
        form.querySelector('[data-add]')?.addEventListener('click', () => add({scheduled_at: scheduled})?.querySelector('select')?.focus());
        const lastSettings = () => {
            const last = rows.rows[rows.rows.length - 1];
            return Object.fromEntries(['service_version_id', 'service_provider_id', 'scheduled_at', 'location', 'customer_rate', 'provider_rate_source', 'asset_id']
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
            const last = rows.rows[rows.rows.length - 1];
            const beneficiary = last.querySelector('[data-key="beneficiary_name"]');
            const useLast = !beneficiary.value && !last.querySelector('[data-key="associate_id"]').value;
            if (!base.service_version_id || rates[base.service_version_id]?.membersOnly) {
                error.textContent = 'Escolha na última linha um serviço que aceite beneficiários avulsos.';
                return;
            }
            if (!names.length || names.some(name => name.length > 191 || name.includes('\t')) || names.length > 30 - rows.rows.length + Number(useLast)) {
                error.textContent = 'Cole uma coluna de nomes com até 191 caracteres por nome, respeitando o limite de 30 linhas.';
                return;
            }
            if (useLast) beneficiary.value = names.shift();
            names.forEach(name => add({...base, beneficiary_name: name}));
            input.value = '';
            error.textContent = '';
            paste.close();
        });
        form.addEventListener('invalid', event => {
            const dialog = event.target.closest('dialog');
            if (dialog && !dialog.open) dialog.showModal();
        }, true);
        const submit = form.querySelector('button[type="submit"]');
        form.addEventListener('submit', () => submit.disabled = true);
        window.addEventListener('pageshow', () => submit.disabled = false);
    })();
    </script>
    @endif
</div>
@endsection
