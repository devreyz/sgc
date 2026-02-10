<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Cards de resumo no topo --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @php
                $completed = \App\Models\ServiceOrder::where('status', \App\Enums\ServiceOrderStatus::COMPLETED)->count();
                $pendingFromAssociates = \App\Models\ServiceOrder::where('status', \App\Enums\ServiceOrderStatus::COMPLETED)
                    ->where('associate_payment_status', 'pending')
                    ->sum(\Illuminate\Support\Facades\DB::raw('final_price'));
                $pendingToProviders = \App\Models\ServiceOrder::where('status', \App\Enums\ServiceOrderStatus::COMPLETED)
                    ->where('associate_payment_status', 'paid')
                    ->where('provider_payment_status', 'pending')
                    ->sum('provider_payment');
                $totalProfit = \App\Models\ServiceOrder::where('status', \App\Enums\ServiceOrderStatus::COMPLETED)
                    ->where('associate_payment_status', 'paid')
                    ->sum(\Illuminate\Support\Facades\DB::raw('final_price - provider_payment'));
            @endphp

            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Ordens Concluídas</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($completed, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">A Receber</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">R$ {{ number_format($pendingFromAssociates, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">A Pagar</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">R$ {{ number_format($pendingToProviders, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Lucro Total</p>
                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">R$ {{ number_format($totalProfit, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabela com filament table --}}
        <div class="rounded-lg bg-white shadow dark:bg-gray-800">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
