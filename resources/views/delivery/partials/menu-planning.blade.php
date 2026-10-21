<section id="cardapios">
    <h2>Receitas e cardápios</h2>
    @if(!$menusAvailable)
        <p>Aplique a migração da V3 para habilitar receitas e cardápios.</p>
    @else
        <p class="muted">Receitas têm versões imutáveis. Os ingredientes são informados em gramas brutas, mesmo quando a unidade comercial é diferente. O rendimento e o peso da porção são declarados na ficha técnica; o sistema não presume ganho ou perda de cocção.</p>
        <details><summary>Cadastrar nova versão de receita</summary>
            <form method="post" action="{{ $store('recipes') }}">@csrf
                <div class="fields">
                    <label>Código da receita<input name="code" required maxlength="80" pattern="[a-z0-9][a-z0-9_-]*"></label>
                    <label>Nome<input name="name" required maxlength="255"></label>
                    <label>Rendimento (porções)<input type="number" name="yield_portions" min="0.00000001" step="0.00000001" required></label>
                    <label>Peso declarado da porção pronta (g)<input type="number" name="portion_weight_g" min="0.00000001" step="0.00000001" required></label>
                    <label>Referência da ficha técnica<input name="source_reference" required maxlength="255"></label>
                </div>
                <div id="recipe-ingredient-rows"><div class="fields" data-recipe-ingredient>
                    <label>Ingrediente<select name="items[0][product_id]" required><option value="">Selecione</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></label>
                    <label>Quantidade bruta para a receita inteira (g)<input name="items[0][gross_quantity_g]" type="number" min="0.00000001" step="0.00000001" required></label>
                </div></div>
                <label>Modo de preparo<textarea name="instructions" maxlength="20000"></textarea></label>
                <button type="button" id="recipe-add-ingredient">Adicionar ingrediente</button> <button>Salvar nova versão</button>
            </form>
        </details>
        @foreach($recipes as $recipe)
            <details><summary>{{ $recipe->name }} · {{ $recipe->code }} v{{ $recipe->version }}</summary>
                <p>Rendimento: {{ $recipe->yield_portions }} porções · peso declarado: {{ $recipe->portion_weight_g }} g/porção · {{ $recipe->source_reference }}</p>
                @foreach($recipe->items as $item)<p>{{ $products->firstWhere('id', $item->product_id)?->name ?? 'Produto indisponível' }}: {{ $item->gross_quantity_g }} g brutos</p>@endforeach
                <p style="white-space:pre-wrap">{{ $recipe->instructions }}</p>
            </details>
        @endforeach
        <details><summary>Criar cardápio para cliente e público</summary>
            <form method="post" action="{{ $store('menus') }}">@csrf
                <div class="fields">
                    <label>Código do cardápio<input name="code" required maxlength="80" pattern="[a-z0-9][a-z0-9_-]*"></label>
                    <label>Nome<input name="name" required maxlength="255"></label>
                    <label>Cliente/unidade<select name="customer_id" required>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }}</option>@endforeach</select></label>
                    <label>Público<select name="beneficiary_group_id" required>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select></label>
                    <label>Data inicial<input type="date" name="date_from" required></label><label>Data final<input type="date" name="date_to" required></label>
                </div><button>Criar e planejar refeições</button>
            </form>
        </details>
        @php($menuStatuses = ['planned' => 'Planejado', 'calculated' => 'Calculado', 'awaiting_validation' => 'Aguardando validação', 'approved' => 'Aprovado', 'in_execution' => 'Em execução', 'finalized' => 'Finalizado'])
        @forelse($menus as $menu)
            <p><a href="{{ route('delivery.projects.menus.show', ['tenant' => $currentTenant->slug, 'project' => $project->id, 'menu' => $menu->id]) }}">{{ $menu->name }} v{{ $menu->version }}</a> · {{ $customers->firstWhere('id', $menu->customer_id)?->name ?? 'Cliente histórico' }} · {{ $groups->firstWhere('id', $menu->beneficiary_group_id)?->name }} · {{ $menu->date_from->format('d/m/Y') }} a {{ $menu->date_to->format('d/m/Y') }} · {{ $menuStatuses[$menu->status] ?? $menu->status }}</p>
        @empty<p>Nenhum cardápio cadastrado.</p>@endforelse
    @endif
</section>
<script>
document.getElementById('recipe-add-ingredient')?.addEventListener('click', function () {
    const container = document.getElementById('recipe-ingredient-rows');
    const index = Number(container.dataset.nextIndex || 1);
    if (container.children.length >= 100) return;
    const row = container.firstElementChild.cloneNode(true);
    row.querySelectorAll('[name]').forEach(function (field) { field.name = field.name.replace(/\[\d+\]/, '[' + index + ']'); field.value = ''; });
    const remove = document.createElement('button'); remove.type = 'button'; remove.textContent = 'Remover'; remove.addEventListener('click', () => row.remove());
    row.appendChild(remove); container.appendChild(row); container.dataset.nextIndex = String(index + 1);
});
</script>
