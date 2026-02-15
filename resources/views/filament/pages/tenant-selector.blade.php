<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Organização Atual
        </x-slot>

        @php
            $currentTenant = $this->getCurrentTenant();
        @endphp

        @if($currentTenant)
            <div class="flex items-center justify-between p-4 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
                <div>
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-building-office-2 class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                        <h3 class="text-lg font-semibold text-primary-900 dark:text-primary-100">
                            {{ $currentTenant->name }}
                        </h3>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Você está trabalhando nesta organização
                    </p>
                </div>

                @if(auth()->user()->isSuperAdmin())
                    <x-filament::button 
                        color="gray" 
                        size="sm"
                        wire:click="clearTenant"
                    >
                        Limpar Seleção
                    </x-filament::button>
                @endif
            </div>
        @else
            <div class="p-4 bg-warning-50 dark:bg-warning-900/20 rounded-lg">
                <div class="flex items-center gap-2">
                    <x-heroicon-m-exclamation-triangle class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                    <p class="text-warning-900 dark:text-warning-100">
                        Nenhuma organização ativa. Selecione uma abaixo.
                    </p>
                </div>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">
            Minhas Organizações
        </x-slot>

        <x-slot name="description">
            @if(auth()->user()->isSuperAdmin())
                Como super administrador, você tem acesso a todas as organizações do sistema.
            @else
                Selecione a organização na qual deseja trabalhar.
            @endif
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
            @forelse($this->getAvailableTenants() as $tenant)
                <button
                    wire:click="switchTenant({{ $tenant->id }})"
                    class="p-6 bg-white dark:bg-gray-800 border-2 rounded-lg shadow-sm transition-all hover:shadow-md hover:border-primary-500 focus:ring-2 focus:ring-primary-500 text-left
                        {{ $currentTenant && $currentTenant->id === $tenant->id ? 'border-primary-600 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-700' }}"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                {{ $tenant->name }}
                            </h4>
                            
                            @if($tenant->slug)
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $tenant->slug }}
                                </p>
                            @endif

                            @php
                                $isAdmin = $tenant->userIsAdmin(auth()->user());
                            @endphp

                            @if($isAdmin || auth()->user()->isSuperAdmin())
                                <div class="mt-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                        @if(auth()->user()->isSuperAdmin())
                                            Super Admin
                                        @else
                                            Admin
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>

                        @if($currentTenant && $currentTenant->id === $tenant->id)
                            <x-heroicon-m-check-circle class="w-6 h-6 text-primary-600 flex-shrink-0" />
                        @else
                            <x-heroicon-o-arrow-right class="w-5 h-5 text-gray-400 flex-shrink-0 mt-1" />
                        @endif
                    </div>
                </button>
            @empty
                <div class="col-span-full p-8 text-center">
                    <x-heroicon-o-building-office-2 class="w-12 h-12 mx-auto text-gray-400" />
                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        Nenhuma organização disponível.
                    </p>
                </div>
            @endforelse
        </div>
    </x-filament::section>

    @if(auth()->user()->isSuperAdmin())
        <x-filament::section class="mt-6">
            <x-slot name="heading">
                Gerenciamento de Organizações
            </x-slot>

            <div class="flex gap-4">
                <x-filament::button 
                    color="success" 
                    tag="a"
                    href="{{ route('filament.admin.resources.tenants.create') }}"
                    icon="heroicon-m-plus"
                >
                    Nova Organização
                </x-filament::button>
                
                <x-filament::button 
                    color="gray" 
                    tag="a"
                    href="{{ route('filament.admin.resources.tenants.index') }}"
                    icon="heroicon-m-cog-6-tooth"
                    outlined
                >
                    Gerenciar Organizações
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
