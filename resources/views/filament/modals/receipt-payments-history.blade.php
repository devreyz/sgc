{{-- resources/views/filament/modals/receipt-payments-history.blade.php --}}
{{-- Variáveis: $receipt, $payments (Collection), $label ('Recebimento' | 'Pagamento') --}}
@php
    $total     = (float) ($receipt->total_net ?? 0);
    $paid      = (float) ($receipt->amount_paid ?? 0);
    $remaining = max(0, $total - $paid);
    $isFull    = $remaining < 0.005;
    $pct       = $total > 0 ? min(100, round($paid / $total * 100)) : 0;
@endphp

<div class="space-y-3 p-1">

    {{-- ── Resumo compacto horizontal ── --}}
    <div class="flex items-start gap-3 flex-wrap rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 p-3">
        <div class="flex-1 min-w-0">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Total a {{ strtolower($label === 'Pagamento' ? 'Pagar' : 'Receber') }}</div>
            <div class="text-lg font-bold text-gray-800 dark:text-gray-100 mt-0.5">R$ {{ number_format($total, 2, ',', '.') }}</div>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-xs {{ $isFull ? 'text-green-600' : 'text-blue-600' }} uppercase tracking-wide">{{ $label }}(s) Registrado(s)</div>
            <div class="text-lg font-bold {{ $isFull ? 'text-green-700 dark:text-green-400' : 'text-blue-700 dark:text-blue-400' }} mt-0.5">R$ {{ number_format($paid, 2, ',', '.') }}</div>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-xs {{ $isFull ? 'text-gray-400' : 'text-amber-600' }} uppercase tracking-wide">Saldo Restante</div>
            <div class="text-lg font-bold {{ $isFull ? 'text-gray-400 line-through' : 'text-amber-700 dark:text-amber-400' }} mt-0.5">R$ {{ number_format($remaining, 2, ',', '.') }}</div>
        </div>
        {{-- progresso --}}
        @if($total > 0)
        <div class="w-full mt-1">
            <div class="flex justify-between text-xs text-gray-400 mb-1">
                <span>{{ $pct }}% {{ strtolower($isFull ? 'quitado' : 'pago') }}</span>
                @if(! $isFull)<span>Faltam R$ {{ number_format($remaining, 2, ',', '.') }}</span>@endif
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                <div class="h-1.5 rounded-full {{ $isFull ? 'bg-green-500' : 'bg-blue-500' }}" style="width: {{ $pct }}%"></div>
            </div>
        </div>
        @endif
    </div>

    {{-- ── Tabela de parcelas ── --}}
    @if($payments->isEmpty())
        <div class="text-gray-500 dark:text-gray-400 py-4 text-sm">
            Nenhum {{ strtolower($label) }} registrado ainda.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase w-6">#</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Data</th>
                        <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Valor</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Forma Pgto.</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Conta</th>
                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Doc. / Obs.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
                    @foreach($payments as $p)
                    <tr class="{{ $loop->even ? 'bg-gray-50/60 dark:bg-gray-800/40' : '' }}">
                        <td class="px-3 py-2 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-3 py-2 font-medium whitespace-nowrap">{{ $p->payment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-3 py-2 text-right font-semibold text-green-700 dark:text-green-400 whitespace-nowrap">
                            R$ {{ number_format((float) $p->amount, 2, ',', '.') }}
                        </td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $p->payment_method ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400 text-xs">{{ $p->bankAccount?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-500 dark:text-gray-400 text-xs max-w-xs">
                            @if($p->document_number)<span class="font-medium text-gray-600 dark:text-gray-300">{{ $p->document_number }}</span>@endif
                            @if($p->document_number && $p->notes) · @endif
                            @if($p->notes)<span class="truncate" title="{{ $p->notes }}">{{ Str::limit($p->notes, 40) }}</span>@endif
                            @if(!$p->document_number && !$p->notes)—@endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 dark:bg-gray-800">
                        <td colspan="2" class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase">Total pago</td>
                        <td class="px-3 py-2 text-right font-bold text-green-700 dark:text-green-400 whitespace-nowrap">
                            R$ {{ number_format($payments->sum('amount'), 2, ',', '.') }}
                        </td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
