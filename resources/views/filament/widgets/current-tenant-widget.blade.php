<x-filament-widgets::widget>
    @php
        $tenant = $this->getCurrentTenant();
        $isAdmin = $this->isAdminOfCurrentTenant();
    @endphp

    @if($tenant)
        <x-filament::section
            :heading="__('Organização Ativa')"
            icon="heroicon-o-building-office-2"
            :description="__('Você está trabalhando nesta organização')"
        >
            <div class="flex items-center justify-between py-2">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $tenant->name }}
                    </h3>
                    
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $tenant->slug }}
                        </span>
                        
                        @if($isAdmin)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                @if(auth()->user()->isSuperAdmin())
                                    Super Admin
                                @else
                                    Admin
                                @endif
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex gap-2">
                    @if(auth()->user()->tenants()->count() > 1 || auth()->user()->isSuperAdmin())
                        <x-filament::button
                            tag="a"
                            href="{{ route('filament.admin.pages.tenant-selector') }}"
                            color="gray"
                            size="sm"
                            icon="heroicon-m-arrow-path"
                        >
                            Trocar Organização
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section
            :heading="__('Nenhuma Organização Ativa')"
            icon="heroicon-o-exclamation-triangle"
            icon-color="warning"
        >
            <div class="flex items-center justify-between py-2">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Selecione uma organização para começar a trabalhar.
                </p>

                <x-filament::button
                    tag="a"
                    href="{{ route('filament.admin.pages.tenant-selector') }}"
                    color="primary"
                    size="sm"
                    icon="heroicon-m-building-office-2"
                >
                    Selecionar Organização
                </x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
