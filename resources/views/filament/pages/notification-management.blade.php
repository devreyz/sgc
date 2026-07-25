<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Push no servidor</x-slot>
            <div class="flex items-center gap-3">
                <x-filament::badge :color="$this->pushConfigured ? 'success' : 'warning'">
                    {{ $this->pushConfigured ? 'Configurado' : 'Configuracao pendente' }}
                </x-filament::badge>
                <span class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $this->activeDevices }} dispositivo(s) ativo(s) nesta organizacao
                </span>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Processamento</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                A central interna e gravada imediatamente. Notificacoes push sao processadas pela fila.
            </p>
            <a
                href="{{ \App\Filament\Pages\SystemJobs::getUrl() }}"
                class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-500"
            >
                Ver tarefas e falhas
            </a>
        </x-filament::section>
    </div>

    <x-filament-panels::form wire:submit="savePreferences">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Salvar preferencias
            </x-filament::button>
            <x-filament::button
                type="button"
                color="success"
                icon="heroicon-o-paper-airplane"
                wire:click="sendManual"
                wire:loading.attr="disabled"
                wire:target="sendManual"
            >
                Enviar mensagem
            </x-filament::button>
        </div>
    </x-filament-panels::form>
</x-filament-panels::page>
