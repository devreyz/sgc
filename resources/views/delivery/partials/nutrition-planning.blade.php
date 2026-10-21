<section id="nutricao">
    <h2>Composição e metas nutricionais</h2>
    @if(!$nutritionAvailable)
        <p>A migração da V2 precisa ser aplicada para habilitar os cadastros nutricionais.</p>
    @else
        <p class="muted">Cadastre composições com fonte e versão. A fração comestível representa a parte utilizável da massa distribuída, entre zero e um. Não há metas nutricionais preenchidas automaticamente.</p>
        <details><summary>Cadastrar nutriente</summary>
            <form method="post" action="{{ $store('nutrients') }}">@csrf
                <div class="fields">
                    <label>Código<input name="code" required maxlength="80" pattern="[a-z][a-z0-9_]*" placeholder="protein_g"></label>
                    <label>Nome<input name="name" required maxlength="255"></label>
                    <label>Unidade<select name="unit">@foreach(['g', 'mg', 'mcg', 'kcal', 'kJ'] as $unit)<option>{{ $unit }}</option>@endforeach</select></label>
                </div><button>Cadastrar nutriente</button>
            </form>
        </details>
        <details><summary>Adicionar versão de composição de produto</summary>
            <form method="post" action="{{ $store('food-profiles') }}">@csrf
                <div class="fields">
                    <label>Produto<select name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></label>
                    <label>Nome da composição<input name="name" required maxlength="255"></label>
                    <label>Quantidade de referência (g)<input name="reference_quantity_g" type="number" step="0.00000001" min="0.00000001" max="1000000000" required></label>
                    <label>Fração comestível (0 a 1)<input name="edible_fraction" type="number" step="0.00000001" min="0.00000001" max="1" required></label>
                    <label>Fonte<input name="source" required maxlength="255"></label>
                    <label>Versão da fonte<input name="source_version" required maxlength="255"></label>
                    <label>Vigência inicial<input type="date" name="effective_from" required></label>
                    <label>Vigência final<input type="date" name="effective_to"></label>
                </div>
                <div id="nutrition-food-values"><div class="fields" data-nutrition-row>
                    <label>Nutriente<select name="values[0][nutrient_id]" required><option value="">Selecione</option>@foreach($nutrients as $nutrient)<option value="{{ $nutrient->id }}">{{ $nutrient->name }} ({{ $nutrient->unit }})</option>@endforeach</select></label>
                    <label>Valor na quantidade de referência<input type="number" name="values[0][amount]" min="0" step="0.00000001" required></label>
                </div></div>
                <p class="muted">Valor zero deve ser explícito. Nutrientes não cadastrados na composição permanecem desconhecidos. Cada nova versão contém a composição completa.</p>
                <button type="button" data-nutrition-add="nutrition-food-values" data-nutrition-prefix="values">Adicionar nutriente</button>
                <button>Salvar nova versão de composição</button>
            </form>
        </details>
        @foreach($foodProfiles as $profile)
            <details><summary>{{ $products->firstWhere('id', $profile->product_id)?->name }} · {{ $profile->name }} v{{ $profile->version }}</summary>
                <p>{{ $profile->source }} · versão da fonte {{ $profile->source_version }} · {{ $profile->effective_from->format('d/m/Y') }} a {{ $profile->effective_to?->format('d/m/Y') ?? 'em aberto' }}</p>
                <p>Referência {{ $profile->reference_quantity_g }} g · fração comestível {{ $profile->edible_fraction }}</p>
                @foreach($profile->nutrientValues as $value)<p>{{ $value->nutrient?->name }}: {{ $value->amount }} {{ $value->nutrient?->unit }}</p>@endforeach
            </details>
        @endforeach
        <details><summary>Adicionar versão de metas por público e cliente</summary>
            <form method="post" action="{{ $store('nutrition-targets') }}">@csrf
                <div class="fields">
                    <label>Grupo<select name="beneficiary_group_id" required>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select></label>
                    <label>Cliente/unidade<select name="customer_id" required>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></label>
                    <label>Nome do conjunto<input name="name" maxlength="255" required></label>
                    <label>Origem<select name="source_type">@foreach(['internal' => 'Interna', 'project' => 'Projeto', 'notice' => 'Edital', 'agreement' => 'Convênio', 'municipal' => 'Municipal', 'state' => 'Estadual', 'federal' => 'Federal', 'other' => 'Outra'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                    <label>Referência da fonte<input name="source_reference" required maxlength="255"></label>
                    <label>Vigência inicial<input type="date" name="effective_from" required></label>
                    <label>Vigência final<input type="date" name="effective_to"></label>
                </div>
                <div id="nutrition-target-values"><div class="fields" data-nutrition-row>
                    <label>Nutriente<select name="targets[0][nutrient_id]" required><option value="">Selecione</option>@foreach($nutrients as $nutrient)<option value="{{ $nutrient->id }}">{{ $nutrient->name }} ({{ $nutrient->unit }}/pessoa/dia)</option>@endforeach</select></label>
                    <label>Mínimo por pessoa-dia<input name="targets[0][min_value]" type="number" min="0" step="0.00000001"></label>
                    <label>Máximo por pessoa-dia<input name="targets[0][max_value]" type="number" min="0" step="0.00000001"></label>
                    <label>Severidade<select name="targets[0][severity]"><option value="warning">Atenção</option><option value="critical">Crítico</option><option value="info">Informação</option></select></label>
                </div></div>
                <p class="muted">Informe ao menos um limite por nutriente. As metas são avaliadas somente no público e cliente escolhidos, com dados completos. Geram alertas de nutrição no Centro de Integridade.</p>
                <button type="button" data-nutrition-add="nutrition-target-values" data-nutrition-prefix="targets">Adicionar meta</button>
                <button>Salvar nova versão de metas</button>
            </form>
        </details>
        @foreach($nutritionTargetSets as $set)
            <details><summary>{{ $set->name }} v{{ $set->version }} · {{ $customers->firstWhere('id', $set->customer_id)?->name }} · {{ $groups->firstWhere('id', $set->beneficiary_group_id)?->name }}</summary>
                <p>{{ $set->source_type }}: {{ $set->source_reference }} · {{ $set->effective_from->format('d/m/Y') }} a {{ $set->effective_to?->format('d/m/Y') ?? 'em aberto' }}</p>
                @foreach($set->targets as $target)@php($nutrient = $nutrients->firstWhere('id', $target->nutrient_id))<p>{{ $nutrient?->name }}: {{ $target->min_value ?? 'sem mínimo' }} a {{ $target->max_value ?? 'sem máximo' }} {{ $nutrient?->unit }}/pessoa/dia · {{ $target->severity }}</p>@endforeach
            </details>
        @endforeach
    @endif
</section>
<script>
document.querySelectorAll('[data-nutrition-add]').forEach(function (button) {
    button.addEventListener('click', function () {
        const container = document.getElementById(button.dataset.nutritionAdd);
        const index = Number(container.dataset.nextIndex || 1);
        if (container.children.length >= 100) return;
        const row = container.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-remove-nutrition]').forEach(el => el.remove());
        row.querySelectorAll('[name]').forEach(function (field) {
            field.name = field.name.replace(/\[\d+\]/, '[' + index + ']');
            if (field.tagName === 'INPUT') field.value = '';
        });
        const remove = document.createElement('button');
        remove.type = 'button'; remove.textContent = 'Remover'; remove.dataset.removeNutrition = '';
        remove.addEventListener('click', () => row.remove());
        row.appendChild(remove); container.appendChild(row); container.dataset.nextIndex = String(index + 1);
    });
});
</script>
