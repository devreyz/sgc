<div class="fi-wi-tenant-selector">
    @if($hasMultipleTenants)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="shrink-0">
                        <svg class="h-8 w-8 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Organização Atual</div>
                        <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ $currentTenantName }}</div>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <div class="w-64">
                        <select 
                            wire:model="selectedTenant"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        >
                            <option value="">Selecione uma organização</option>
                            @foreach($availableTenants as $tenant)
                                <option value="{{ $tenant['id'] }}">{{ $tenant['name'] }}</option>
                            @endforeach
                        </select>
                        @error('selectedTenant')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    
                    <button 
                        wire:click="switchTenant"
                        type="button"
                        class="inline-flex items-center px-4 py-2 bg-primary hover:bg-primary-dark text-white font-semibold rounded-lg shadow transition-colors"
                    >
                        <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                        </svg>
                        Trocar
                    </button>
                </div>
            </div>
        </div>
    @else
        <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-4 mb-6 border border-emerald-200 dark:border-emerald-800">
            <div class="flex items-center space-x-3">
                <div class="shrink-0">
                    <svg class="h-6 w-6 text-primary dark:text-primary-dark" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div>
                    <div class="text-sm text-primary dark:text-primary-dark font-medium">Organização</div>
                    <div class="text-lg font-semibold text-primary dark:text-primary-dark">{{ $currentTenantName }}</div>
                </div>
            </div>
        </div>
    @endif
</div>
