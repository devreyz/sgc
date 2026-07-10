<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Filtros e Dados do Lote</x-slot>

        {{ $this->form }}
    </x-filament::section>

    @php
        $distributions = $this->getUnbilledDistributions();
        $selectedIds = $this->selectedIds;
        $totalSelected = count($selectedIds);
        $totalNet = 0;
        $totalGross = 0;
        foreach ($distributions->whereIn('id', $selectedIds) as $d) {
            $gross = (float) ($d->gross_value ?? ($d->quantity * $d->unit_price));
            $fee = (float) ($d->admin_fee_amount ?? ($gross * ($d->admin_fee_percentage ?? 0) / 100));
            $net = (float) ($d->net_value ?? ($gross - $fee));
            $totalGross += $gross;
            $totalNet += $net;
        }
    @endphp

    <x-filament::section>
        <x-slot name="heading">
            Distribuições Disponíveis
            @if($distributions->count() > 0)
                <span class="ml-2 text-sm font-normal text-gray-500">({{ $distributions->count() }} não faturadas)</span>
            @endif
        </x-slot>

        @if($distributions->isEmpty())
            <div class="text-center py-8 text-gray-500">
                @if(is_null($this->form->getState()['sales_project_id'] ?? null))
                    Selecione um projeto para ver as distribuições disponíveis.
                @else
                    Nenhuma distribuição aprovada e não faturada encontrada para os filtros selecionados.
                @endif
            </div>
        @else
            {{-- Barra de seleção --}}
            <div class="flex items-center gap-4 mb-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <x-filament::button wire:click="selectAll" size="sm" color="gray" outlined>
                    Selecionar Todos ({{ $distributions->count() }})
                </x-filament::button>
                <x-filament::button wire:click="clearSelection" size="sm" color="gray" outlined>
                    Limpar Seleção
                </x-filament::button>
                @if($totalSelected > 0)
                    <span class="text-sm font-semibold text-primary-600 dark:text-primary-400">
                        {{ $totalSelected }} selecionadas |
                        Bruto: R$ {{ number_format($totalGross, 2, ',', '.') }} |
                        Líquido: R$ {{ number_format($totalNet, 2, ',', '.') }}
                    </span>
                @endif
            </div>

            {{-- Tabela de distribuições --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 px-3 w-10"></th>
                            <th class="py-2 px-3">Data</th>
                            <th class="py-2 px-3">Associado</th>
                            <th class="py-2 px-3">Produto</th>
                            <th class="py-2 px-3">Cliente</th>
                            <th class="py-2 px-3 text-right">Qtd</th>
                            <th class="py-2 px-3 text-right">Preço Un.</th>
                            <th class="py-2 px-3 text-right">Bruto</th>
                            <th class="py-2 px-3 text-right">Taxa</th>
                            <th class="py-2 px-3 text-right">Líquido</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($distributions as $dist)
                            @php
                                $isSelected = in_array($dist->id, $selectedIds);
                                $gross = (float) ($dist->gross_value ?? ($dist->quantity * $dist->unit_price));
                                $feeP  = (float) ($dist->admin_fee_percentage ?? 0);
                                $fee   = (float) ($dist->admin_fee_amount ?? ($gross * $feeP / 100));
                                $net   = (float) ($dist->net_value ?? ($gross - $fee));
                            @endphp
                            <tr
                                wire:click="toggleSelect({{ $dist->id }})"
                                class="border-b border-gray-100 dark:border-gray-800 cursor-pointer transition-colors
                                    {{ $isSelected ? 'bg-primary-50 dark:bg-primary-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50' }}"
                            >
                                <td class="py-2 px-3">
                                    <input type="checkbox" readonly
                                        {{ $isSelected ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-primary-600 pointer-events-none"
                                    >
                                </td>
                                <td class="py-2 px-3">{{ $dist->delivery_date?->format('d/m/Y') }}</td>
                                <td class="py-2 px-3">{{ $dist->associate->display_name ?? '—' }}</td>
                                <td class="py-2 px-3">{{ $dist->product->name ?? '—' }}</td>
                                <td class="py-2 px-3">{{ $dist->customer->name ?? '—' }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ number_format((float) $dist->quantity, 2, ',', '.') }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">R$ {{ number_format((float) $dist->unit_price, 2, ',', '.') }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">R$ {{ number_format($gross, 2, ',', '.') }}</td>
                                <td class="py-2 px-3 text-right tabular-nums text-red-600">R$ {{ number_format($fee, 2, ',', '.') }}</td>
                                <td class="py-2 px-3 text-right tabular-nums font-semibold text-green-600">R$ {{ number_format($net, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if($totalSelected > 0)
                        <tfoot>
                            <tr class="font-bold border-t-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">
                                <td colspan="7" class="py-2 px-3 text-right">
                                    Total Selecionado ({{ $totalSelected }} distribuições):
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums">R$ {{ number_format($totalGross, 2, ',', '.') }}</td>
                                <td class="py-2 px-3"></td>
                                <td class="py-2 px-3 text-right tabular-nums text-green-600">R$ {{ number_format($totalNet, 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
    </x-filament::section>

    @if($totalSelected > 0)
        <div class="flex justify-end gap-4 mt-2">
            <x-filament::button wire:click="bill" color="success" size="lg">
                Gerar Faturamento ({{ $totalSelected }} distribuições)
            </x-filament::button>
        </div>
    @endif
</x-filament-panels::page>
