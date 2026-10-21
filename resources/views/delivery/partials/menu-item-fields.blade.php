@php($recipeSelected = !empty($item['recipe_id']))
<div class="fields" data-menu-item>
    <label>Tipo<select data-menu-kind><option value="product" @selected(!$recipeSelected)>Produto</option><option value="recipe" @selected($recipeSelected)>Receita</option></select></label>
    <label data-menu-product-label @if($recipeSelected) hidden @endif>Produto<select name="items[{{ $index }}][product_id]" data-menu-product @disabled($recipeSelected) required><option value="">Selecione</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($item['product_id'] ?? null) == $product->id)>{{ $product->name }}</option>@endforeach</select></label>
    <label data-menu-recipe-label @if(!$recipeSelected) hidden @endif>Versão da receita<select name="items[{{ $index }}][recipe_id]" data-menu-recipe @disabled(!$recipeSelected) required><option value="">Selecione</option>@foreach($recipes as $recipe)<option value="{{ $recipe->id }}" @selected(($item['recipe_id'] ?? null) == $recipe->id)>{{ $recipe->name }} · {{ $recipe->code }} v{{ $recipe->version }}</option>@endforeach</select></label>
    <label><span data-menu-quantity-label>{{ $recipeSelected ? 'Porções por pessoa' : 'Gramas brutas por pessoa' }}</span><input name="items[{{ $index }}][quantity_per_person]" type="number" min="0.00000001" step="0.00000001" required value="{{ $item['quantity_per_person'] ?? '' }}"></label>
    <input type="hidden" name="items[{{ $index }}][quantity_unit]" data-menu-unit value="{{ $recipeSelected ? 'portion' : 'g' }}">
    <button type="button" data-menu-remove>Remover item</button>
</div>
