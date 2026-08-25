<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Distribuições da cobrança</p>
            <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $totalDistributions }}</p>
        </div>
        <div class="rounded-xl border border-success-200 bg-success-50 p-3 dark:border-success-800 dark:bg-success-950/30">
            <p class="text-xs font-medium uppercase tracking-wide text-success-700 dark:text-success-400">Com folha válida</p>
            <p class="mt-1 text-2xl font-semibold text-success-700 dark:text-success-400">{{ $coveredDistributions }}</p>
        </div>
        <div class="rounded-xl border {{ $uncoveredDistributions ? 'border-warning-200 bg-warning-50 dark:border-warning-800 dark:bg-warning-950/30' : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800' }} p-3">
            <p class="text-xs font-medium uppercase tracking-wide {{ $uncoveredDistributions ? 'text-warning-700 dark:text-warning-400' : 'text-gray-500' }}">Sem folha válida</p>
            <p class="mt-1 text-2xl font-semibold {{ $uncoveredDistributions ? 'text-warning-700 dark:text-warning-400' : 'text-gray-950 dark:text-white' }}">{{ $uncoveredDistributions }}</p>
        </div>
    </div>

    @if($sheets->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 px-6 py-8 text-center dark:border-gray-700">
            <p class="font-medium text-gray-900 dark:text-white">Nenhuma folha de conferência encontrada</p>
            <p class="mt-1 text-sm text-gray-500">As distribuições desta cobrança ainda não aparecem em uma folha deste projeto.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Folha</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Destinatário</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Período</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">Cobertura</th>
                        <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">Situação</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                    @foreach($sheets as $sheet)
                        @php
                            $isInvalid = $sheet->invalidated_at !== null;
                            $statusColor = $isInvalid ? 'gray' : $sheet->status->color();
                            $statusLabel = $isInvalid ? 'Desatualizada' : $sheet->status->label();
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-950 dark:text-white">{{ $sheet->formatted_number }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $sheet->recipient_name }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-400">{{ $sheet->period_start->format('d/m/Y') }} a {{ $sheet->period_end->format('d/m/Y') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center text-gray-700 dark:text-gray-300">
                                <strong>{{ $sheet->receipt_distributions_count }}</strong> desta cobrança
                                <span class="block text-xs text-gray-500">{{ $sheet->distributions_count }} na folha</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-filament::badge :color="$statusColor">{{ $statusLabel }}</x-filament::badge>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <x-filament::link
                                    :href="\App\Filament\Resources\DeliveryConferenceSheetResource::getUrl('view', ['record' => $sheet])"
                                    icon="heroicon-m-arrow-top-right-on-square"
                                >Abrir</x-filament::link>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
