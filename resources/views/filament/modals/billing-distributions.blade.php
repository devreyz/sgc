<div class="space-y-3">
    @if($distributions->isEmpty())
        <p class="text-sm text-gray-400 italic">Nenhuma distribuição vinculada a este comprovante.</p>
    @else
        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            <strong>{{ $distributions->count() }}</strong> distribuição(ões) vinculada(s) ao comprovante {{ $r->formatted_number }}.
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Produto</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qtd</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor Bruto</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Valor Líquido</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status Cobr.</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($distributions as $dist)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-3 py-2 text-gray-800 dark:text-gray-200">
                            {{ optional($dist->customer)->name ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                            {{ optional($dist->product)->name ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
                            {{ number_format((float)($dist->quantity ?? 0), 2, ',', '.') }}
                        </td>
                        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
                            R$ {{ number_format((float)($dist->gross_value ?? 0), 2, ',', '.') }}
                        </td>
                        <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">
                            R$ {{ number_format((float)($dist->net_value ?? 0), 2, ',', '.') }}
                        </td>
                        <td class="px-3 py-2 text-center">
                            @php
                                $bs = $dist->billing_status;
                                $color = match($bs?->value ?? '') {
                                    'paid'    => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                    'billed'  => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                    default   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                                };
                                $label = $bs?->getLabel() ?? 'Não Cobrado';
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $color }}">
                                {{ $label }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <td colspan="3" class="px-3 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Total</td>
                        <td class="px-3 py-2 text-right text-sm font-semibold text-gray-800 dark:text-gray-200">
                            R$ {{ number_format($distributions->sum(fn($d) => (float)($d->gross_value ?? 0)), 2, ',', '.') }}
                        </td>
                        <td class="px-3 py-2 text-right text-sm font-bold text-gray-900 dark:text-gray-100">
                            R$ {{ number_format($distributions->sum(fn($d) => (float)($d->net_value ?? 0)), 2, ',', '.') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($r->total_net)
        <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <p class="text-sm text-blue-800 dark:text-blue-300">
                <strong>Valor congelado no comprovante:</strong>
                R$ {{ number_format((float)$r->total_net, 2, ',', '.') }}
            </p>
            @if($r->paid_at)
            <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                Recebido em {{ $r->paid_at->format('d/m/Y H:i') }}
            </p>
            @endif
        </div>
        @endif
    @endif
</div>
