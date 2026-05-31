{{-- resources/views/filament/modals/receipt-payments-history.blade.php --}}
{{-- Variáveis: $receipt, $payments (Collection), $label ('Recebimento' | 'Pagamento') --}}
@php
    $total   = (float) ($receipt->total_net ?? 0);
    $paid    = (float) ($receipt->amount_paid ?? 0);
    $remaining = max(0, $total - $paid);
    $isFull  = $remaining < 0.005;
@endphp

<div class="space-y-4 p-1">

    {{-- ── Resumo ── --}}
    <div class="grid grid-cols-3 gap-3 text-center">
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 border border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Valor Total</div>
            <div class="text-base font-bold text-gray-800 dark:text-gray-100">
                R$ {{ number_format($total, 2, ',', '.') }}
            </div>
        </div>
        <div class="rounded-lg {{ $isFull ? 'bg-green-50 dark:bg-green-900/30 border-green-200 dark:border-green-700' : 'bg-blue-50 dark:bg-blue-900/30 border-blue-200 dark:border-blue-700' }} p-3 border">
            <div class="text-xs {{ $isFull ? 'text-green-600' : 'text-blue-600' }} uppercase tracking-wide mb-1">
                Total {{ $label }}
            </div>
            <div class="text-base font-bold {{ $isFull ? 'text-green-700' : 'text-blue-700' }}">
                R$ {{ number_format($paid, 2, ',', '.') }}
            </div>
        </div>
        <div class="rounded-lg {{ $isFull ? 'bg-gray-50 dark:bg-gray-800 border-gray-200' : 'bg-amber-50 dark:bg-amber-900/30 border-amber-200 dark:border-amber-700' }} p-3 border">
            <div class="text-xs {{ $isFull ? 'text-gray-500' : 'text-amber-600' }} uppercase tracking-wide mb-1">Restante</div>
            <div class="text-base font-bold {{ $isFull ? 'text-gray-500 line-through' : 'text-amber-700' }}">
                R$ {{ number_format($remaining, 2, ',', '.') }}
            </div>
        </div>
    </div>

    {{-- ── Tabela de parcelas ── --}}
    @if($payments->isEmpty())
        <div class="text-center text-gray-500 py-6 text-sm">
            Nenhum {{ strtolower($label) }} registrado ainda.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">#</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Data</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Valor</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Forma</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Conta</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Doc.</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Obs.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
                    @foreach($payments as $i => $p)
                    <tr class="{{ $loop->even ? 'bg-gray-50 dark:bg-gray-800/50' : '' }}">
                        <td class="px-4 py-2 text-gray-500">{{ $loop->iteration }}</td>
                        <td class="px-4 py-2 font-medium">{{ $p->payment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-2 text-right font-semibold text-green-700 dark:text-green-400">
                            R$ {{ number_format((float) $p->amount, 2, ',', '.') }}
                        </td>
                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $p->payment_method ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400 text-xs">{{ $p->bankAccount?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400 text-xs">{{ $p->document_number ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500 text-xs max-w-xs truncate" title="{{ $p->notes }}">{{ $p->notes ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 dark:bg-gray-800 font-bold">
                        <td colspan="2" class="px-4 py-2 text-gray-700 dark:text-gray-200 text-xs uppercase">Total</td>
                        <td class="px-4 py-2 text-right text-green-700 dark:text-green-400">
                            R$ {{ number_format($payments->sum('amount'), 2, ',', '.') }}
                        </td>
                        <td colspan="4"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    {{-- ── Progresso ── --}}
    @if($total > 0)
    @php $pct = min(100, round($paid / $total * 100)); @endphp
    <div class="pt-1">
        <div class="flex justify-between text-xs text-gray-500 mb-1">
            <span>Progresso de {{ strtolower($label) }}</span>
            <span>{{ $pct }}%</span>
        </div>
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
            <div class="h-2 rounded-full {{ $isFull ? 'bg-green-500' : 'bg-blue-500' }}" style="width: {{ $pct }}%"></div>
        </div>
    </div>
    @endif
</div>
