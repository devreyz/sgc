<x-filament-panels::page>
    @php
        $push = $this->pushDiagnostics;
        $problems = $this->problemDeliveries;
    @endphp
    <x-filament::section>
        <x-slot name="heading">Como as notificações funcionam</x-slot>
        <div class="grid gap-4 text-sm text-gray-600 dark:text-gray-300 md:grid-cols-3">
            <div><strong class="block text-gray-950 dark:text-white">1. Central interna</strong>Guarda o histórico no SGC, mesmo quando o celular está desligado.</div>
            <div><strong class="block text-gray-950 dark:text-white">2. Android</strong>Entrega pelo Firebase em todos os aparelhos registrados desse usuário. Cada aparelho mantém seu próprio vínculo.</div>
            <div><strong class="block text-gray-950 dark:text-white">3. Destinatários</strong>Os papéis selecionados definem quem recebe cada tipo de evento.</div>
        </div>
    </x-filament::section>

    <div class="grid gap-4 md:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Saúde das notificações</x-slot>
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::badge :color="$push['healthy'] ? 'success' : 'warning'">
                    {{ $push['healthy'] ? 'Funcionando normalmente' : 'Atenção necessária' }}
                </x-filament::badge>
                <x-filament::badge :color="$push['active'] > 0 ? 'success' : 'warning'">{{ $push['active'] }} dispositivo(s) ativo(s)</x-filament::badge>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-gray-500">Entregues nas últimas 24h</dt><dd class="font-semibold">{{ $push['sent_24h'] }}</dd></div>
                <div><dt class="text-gray-500">Aguardando entrega</dt><dd class="font-semibold">{{ $push['pending'] }}</dd></div>
                <div><dt class="text-gray-500">Com problema nas últimas 24h</dt><dd class="font-semibold {{ $push['failures_24h'] ? 'text-danger-600' : 'text-success-600' }}">{{ $push['failures_24h'] }}</dd></div>
                <div><dt class="text-gray-500">Última entrega</dt><dd class="font-semibold">{{ $push['last_delivery_at'] ? \Carbon\Carbon::parse($push['last_delivery_at'])->diffForHumans() : 'Nenhuma ainda' }}</dd></div>
            </dl>
            @if(!$push['active'])
                <p class="mt-4 rounded-lg bg-warning-50 p-3 text-sm text-warning-800 dark:bg-warning-950 dark:text-warning-200">Abra o aplicativo, entre na conta e permita notificações. O aparelho será registrado novamente, inclusive após reinstalação.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Situação do serviço</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                A Central é gravada imediatamente. A entrega ao celular ocorre em segundo plano e não altera nem remove o aviso original.
            </p>
            <a
                href="{{ \App\Filament\Pages\SystemJobs::getUrl() }}"
                class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-500"
            >
                Ver diagnóstico técnico
            </a>
        </x-filament::section>
    </div>

    @if(count($problems))
        <x-filament::section>
            <x-slot name="heading">Com problema</x-slot>
            <div class="space-y-3">
                @foreach($problems as $problem)
                    <div class="flex flex-col justify-between gap-3 rounded-lg border border-danger-200 p-4 dark:border-danger-900 sm:flex-row sm:items-center">
                        <div>
                            <p class="font-semibold">{{ $problem['title'] }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ $problem['user'] }} · falha em {{ $problem['failed_devices'] }} aparelho(s)</p>
                        </div>
                        <x-filament::button
                            size="sm"
                            color="warning"
                            icon="heroicon-o-arrow-path"
                            wire:click="retryPushDelivery('{{ $problem['id'] }}')"
                            wire:loading.attr="disabled"
                            wire:target="retryPushDelivery('{{ $problem['id'] }}')"
                        >
                            Tentar novamente
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    <x-filament-panels::form wire:submit="savePreferences">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Salvar preferencias
            </x-filament::button>
            <x-filament::button
                type="button"
                color="gray"
                icon="heroicon-o-sparkles"
                wire:click="applyRecommendedDefaults"
                wire:confirm="Aplicar os padrões recomendados e substituir as preferências atuais desta organização?"
                wire:loading.attr="disabled"
                wire:target="applyRecommendedDefaults"
            >
                Definir padrões recomendados
            </x-filament::button>
            <x-filament::button
                type="button"
                color="info"
                icon="heroicon-o-bell-alert"
                wire:click="sendTestToMe"
                wire:loading.attr="disabled"
                wire:target="sendTestToMe"
            >
                Testar no meu aparelho
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
