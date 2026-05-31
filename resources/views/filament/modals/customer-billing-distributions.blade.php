<div class="space-y-3">
    @if(empty($rows))
        <p class="text-sm text-gray-400 italic">Nenhuma distribuição vinculada a este comprovante.</p>
    @else
        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
            <strong>{{ count($rows) }}</strong> distribuição(ões) vinculada(s) ao comprovante
            <strong>{{ $receipt->formatted_number }}</strong>.
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Produto</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Associado</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Qtd</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Preço Unit.</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Bruto</th>
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($rows as $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['date'] }}</td>
                        <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $row['product'] }}</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $row['associate'] }}</td>
                        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $row['quantity'] }}</td>
                        <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">R$ {{ $row['unit_price'] }}</td>
                        <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-gray-100">R$ {{ $row['gross'] }}</td>
                        <td class="px-3 py-2 text-center">
                            @php
                                $bsLabel = $row['billing_status'] ?? '—';
                                $bsColor = match(strtolower($bsLabel)) {
                                    'pago'       => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                    'cobrado'    => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                    default      => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $bsColor }}">
                                {{ $bsLabel }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <td colspan="5" class="px-3 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">
                            Total Bruto
                        </td>
                        <td class="px-3 py-2 text-right text-sm font-bold text-gray-900 dark:text-gray-100">
                            R$ {{ number_format($totalGross, 2, ',', '.') }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($receipt->total_net)
        <div class="mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <div class="flex items-center justify-between">
                <p class="text-sm text-blue-800 dark:text-blue-300">
                    <strong>Valor a receber (líquido):</strong>
                    R$ {{ number_format((float) $receipt->total_net, 2, ',', '.') }}
                </p>
                @if($receipt->total_fees && $receipt->total_fees > 0)
                <p class="text-xs text-blue-600 dark:text-blue-400">
                    Taxas/descontos: R$ {{ number_format((float) $receipt->total_fees, 2, ',', '.') }}
                </p>
                @endif
            </div>
            @if($receipt->paid_at)
            <p class="text-xs text-green-600 dark:text-green-400 mt-1 font-medium">
                ✓ Recebido em {{ $receipt->paid_at->format('d/m/Y H:i') }}
            </p>
            @endif
        </div>
        @endif
    @endif
</div>
