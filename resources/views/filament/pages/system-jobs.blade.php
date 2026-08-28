<x-filament-panels::page>
    @php
        $pending = $this->pendingJobs;
        $failed = $this->failedJobs;
        $waiting = collect($pending)->where('status', 'waiting')->count();
        $scheduled = collect($pending)->where('status', 'scheduled')->count();
        $processing = collect($pending)->where('status', 'processing')->count();
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-filament::section>
            <p class="text-sm text-gray-500">Aguardando</p>
            <p class="mt-1 text-2xl font-semibold">{{ $waiting }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">Agendadas</p>
            <p class="mt-1 text-2xl font-semibold">{{ $scheduled }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">Em processamento</p>
            <p class="mt-1 text-2xl font-semibold">{{ $processing }}</p>
        </x-filament::section>
        <x-filament::section>
            <p class="text-sm text-gray-500">Com falha</p>
            <p class="mt-1 text-2xl font-semibold {{ count($failed) ? 'text-danger-600' : 'text-success-600' }}">
                {{ count($failed) }}
            </p>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Fila desta organizacao</x-slot>
        <x-slot name="headerEnd">
            <x-filament::button size="sm" color="primary" icon="heroicon-o-cloud-arrow-up" wire:click="syncGoogleDrive" wire:loading.attr="disabled" wire:target="syncGoogleDrive">
                Sincronizar Google Drive
            </x-filament::button>
            <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-path" wire:click="$refresh">
                Atualizar
            </x-filament::button>
        </x-slot>

        @if(empty($pending))
            <p class="text-sm text-gray-500">Nenhuma tarefa pendente identificada para esta organizacao.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-700">
                        <tr>
                            <th class="px-3 py-2">Tarefa</th>
                            <th class="px-3 py-2">Fila</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2">Disponivel em</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($pending as $job)
                            <tr>
                                <td class="px-3 py-3 font-medium">{{ $job['name'] }}</td>
                                <td class="px-3 py-3">{{ $job['queue'] }}</td>
                                <td class="px-3 py-3">
                                    <x-filament::badge :color="match($job['status']) {
                                        'processing' => 'info',
                                        'scheduled' => 'warning',
                                        default => 'gray',
                                    }">
                                        {{ match($job['status']) {
                                            'processing' => 'Processando',
                                            'scheduled' => 'Agendada',
                                            default => 'Aguardando',
                                        } }}
                                    </x-filament::badge>
                                </td>
                                <td class="px-3 py-3">{{ $job['available_at']?->format('d/m/Y H:i:s') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Falhas</x-slot>

        @if(empty($failed))
            <p class="text-sm text-gray-500">Nenhuma falha identificada para esta organizacao.</p>
        @else
            <div class="space-y-3">
                @foreach($failed as $job)
                    <div class="rounded-lg border border-danger-200 p-4 dark:border-danger-900">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <div>
                                <p class="font-semibold">{{ $job['name'] }}</p>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ $job['queue'] }} · {{ $job['failed_at']?->format('d/m/Y H:i:s') }}
                                </p>
                                <p class="mt-2 text-sm text-danger-700 dark:text-danger-300">{{ $job['error'] }}</p>
                            </div>
                            <x-filament::button
                                size="sm"
                                color="warning"
                                icon="heroicon-o-arrow-path"
                                wire:click="retryFailed('{{ $job['uuid'] }}')"
                                wire:loading.attr="disabled"
                                wire:target="retryFailed('{{ $job['uuid'] }}')"
                            >
                                Tentar novamente
                            </x-filament::button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament::section collapsible collapsed>
        <x-slot name="heading">Processamento em hospedagem compartilhada</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Execute a fila por cron a cada minuto. O comando termina quando nao houver mais tarefas:
        </p>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">php artisan queue:work --stop-when-empty --queue=notifications,documents,default --tries=3</pre>
    </x-filament::section>
</x-filament-panels::page>
