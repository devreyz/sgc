<x-filament-panels::page>

    {{-- Cabeçalho da tabela --}}
    <div class="flex items-center gap-3 mb-4">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ $this->record->name }}</h2>
            <p class="text-sm text-gray-500">
                @if($this->record->code) Código: <strong>{{ $this->record->code }}</strong> ·@endif
                Ano: <strong>{{ $this->record->year }}</strong> ·
                @php $active = collect($rows)->filter(fn($r) => $r['active'])->count(); @endphp
                <span class="text-primary-600 font-semibold">{{ $active }} produto(s) com preço</span>
                de {{ count($rows) }} cadastrados
            </p>
        </div>
        @if(!$this->record->active)
        <span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">Inativa</span>
        @endif
    </div>

    {{-- Barra de busca + botão salvar --}}
    <div class="flex items-center gap-3 mb-4">
        <div class="relative flex-1 max-w-xs">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Filtrar produto…"
                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-600 dark:text-white"
            >
            <x-heroicon-o-magnifying-glass class="absolute left-2.5 top-2.5 w-4 h-4 text-gray-400" />
        </div>
        <button
            wire:click="saveAll"
            wire:loading.attr="disabled"
            class="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-success-600 rounded-lg hover:bg-success-700 disabled:opacity-50 transition"
        >
            <x-heroicon-o-check class="w-4 h-4" />
            <span wire:loading.remove>Salvar Alterações</span>
            <span wire:loading>Salvando…</span>
        </button>
    </div>

    {{-- Legenda --}}
    <p class="text-xs text-gray-500 mb-3">
        Marque a coluna <strong>Ativo</strong> para incluir um produto nesta tabela. Digite o preço de venda e, opcionalmente, o custo.
        Clique em <strong>Salvar Alterações</strong> para confirmar.
    </p>

    {{-- Tabela inline --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <th class="py-3 px-4 text-left font-semibold text-gray-600 dark:text-gray-300 w-8">Ativo</th>
                    <th class="py-3 px-4 text-left font-semibold text-gray-600 dark:text-gray-300">Produto</th>
                    <th class="py-3 px-4 text-center font-semibold text-gray-600 dark:text-gray-300 w-16">Un.</th>
                    <th class="py-3 px-4 text-right font-semibold text-gray-600 dark:text-gray-300 w-44">Preço de Venda (R$)</th>
                    <th class="py-3 px-4 text-right font-semibold text-gray-600 dark:text-gray-300 w-44">Custo (R$) <span class="font-normal text-gray-400">opcional</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($this->filteredRows as $productId => $row)
                <tr
                    class="{{ $row['active'] ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800 opacity-60' }} hover:bg-primary-50 dark:hover:bg-gray-750 transition-colors"
                >
                    {{-- Toggle Ativo --}}
                    <td class="py-2.5 px-4">
                        <button
                            wire:click="toggleActive({{ $productId }})"
                            title="{{ $row['active'] ? 'Remover desta tabela' : 'Adicionar à tabela' }}"
                            class="w-6 h-6 rounded flex items-center justify-center transition {{ $row['active'] ? 'bg-success-500 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-400' }}"
                        >
                            @if($row['active'])
                            <x-heroicon-o-check class="w-3.5 h-3.5" />
                            @else
                            <x-heroicon-o-plus class="w-3.5 h-3.5" />
                            @endif
                        </button>
                    </td>

                    {{-- Nome --}}
                    <td class="py-2.5 px-4 font-medium text-gray-800 dark:text-gray-200">
                        {{ $row['product_name'] }}
                    </td>

                    {{-- Unidade --}}
                    <td class="py-2.5 px-4 text-center text-gray-500">{{ $row['unit'] }}</td>

                    {{-- Preço de venda --}}
                    <td class="py-2 px-4">
                        <div class="flex items-center justify-end gap-1">
                            <span class="text-gray-400 text-xs">R$</span>
                            <input
                                type="number"
                                wire:model.defer="rows.{{ $productId }}.sale_price"
                                step="0.01"
                                min="0"
                                placeholder="0,00"
                                @if(!$row['active']) disabled @endif
                                class="w-32 text-right px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-primary-400
                                    {{ $row['active'] ? 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white' : 'border-transparent bg-transparent text-gray-400 cursor-not-allowed' }}"
                            >
                        </div>
                    </td>

                    {{-- Custo --}}
                    <td class="py-2 px-4">
                        <div class="flex items-center justify-end gap-1">
                            <span class="text-gray-400 text-xs">R$</span>
                            <input
                                type="number"
                                wire:model.defer="rows.{{ $productId }}.cost_price"
                                step="0.01"
                                min="0"
                                placeholder="0,00"
                                @if(!$row['active']) disabled @endif
                                class="w-32 text-right px-2 py-1.5 text-sm border rounded focus:ring-2 focus:ring-primary-400
                                    {{ $row['active'] ? 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-white' : 'border-transparent bg-transparent text-gray-400 cursor-not-allowed' }}"
                            >
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="py-10 text-center text-gray-400">
                        @if($search)
                        Nenhum produto encontrado para "{{ $search }}"
                        @else
                        Nenhum produto cadastrado no sistema.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Rodapé: botão salvar --}}
    @if(count($rows) > 10)
    <div class="flex justify-end mt-4">
        <button
            wire:click="saveAll"
            wire:loading.attr="disabled"
            class="flex items-center gap-1.5 px-5 py-2.5 text-sm font-semibold text-white bg-success-600 rounded-lg hover:bg-success-700 disabled:opacity-50 transition"
        >
            <x-heroicon-o-check class="w-4 h-4" />
            <span wire:loading.remove>Salvar Alterações</span>
            <span wire:loading>Salvando…</span>
        </button>
    </div>
    @endif

</x-filament-panels::page>
