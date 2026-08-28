<x-filament-panels::page>
    @php
        $pending = $this->pendingJobs;
        $failed = $this->failedJobs;
        $drive = $this->driveDiagnostics;
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
        <x-slot name="heading">Diagnóstico do Google Drive</x-slot>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-sm text-gray-500">Conexão</p>
                <p class="mt-1 font-semibold {{ $drive['connected'] ? 'text-success-600' : 'text-danger-600' }}">
                    {{ $drive['connected'] ? 'Ativa' : 'Inativa ou não configurada' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Último envio concluído</p>
                <p class="mt-1 font-semibold">{{ $drive['last_sync_at']?->format('d/m/Y H:i:s') ?? 'Nunca' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Sincronizados</p>
                <p class="mt-1 font-semibold text-success-600">{{ $drive['synced_documents'] }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Rejeitados</p>
                <p class="mt-1 font-semibold {{ $drive['failed_documents'] ? 'text-danger-600' : '' }}">{{ $drive['failed_documents'] }}</p>
            </div>
        </div>

        @if($drive['last_error'])
            <div class="mt-4 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700 dark:border-danger-900 dark:bg-danger-950/30 dark:text-danger-300">
                <strong>Último impedimento:</strong> {{ $drive['last_error'] }}
            </div>
        @elseif($drive['connected'] && !$drive['last_sync_at'] && !$drive['queued_documents'])
            <p class="mt-4 text-sm text-warning-600">A conexão está ativa, mas nenhum documento elegível foi enviado ainda. Use “Sincronizar Google Drive” para fazer a primeira varredura.</p>
        @endif

        <p class="mt-4 text-sm text-gray-500">
            O worker processa até 3 documentos por minuto para respeitar os limites da hospedagem. Uma fila com 7 itens pode levar cerca de 3 minutos, além de novas tentativas quando houver erro.
        </p>
    </x-filament::section>

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
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">O scheduler executa workers separados: notificações e documentos a cada minuto; tarefas gerais a cada cinco minutos.</p>
        <pre class="mt-3 overflow-x-auto rounded-lg bg-gray-950 p-3 text-xs text-gray-100">php artisan schedule:run</pre>
    </x-filament::section>
</x-filament-panels::page>
