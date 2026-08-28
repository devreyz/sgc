<x-filament-panels::page>
    @php($push = $this->pushDiagnostics)
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
            <x-slot name="heading">Notificações Android (Firebase)</x-slot>
            <div class="flex flex-wrap items-center gap-3">
                <x-filament::badge :color="$push['configured'] ? 'success' : 'danger'">
                    {{ $push['configured'] ? 'Firebase configurado' : 'Firebase indisponível' }}
                </x-filament::badge>
                <x-filament::badge :color="$push['active'] > 0 ? 'success' : 'warning'">{{ $push['active'] }} dispositivo(s) ativo(s)</x-filament::badge>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-gray-500">Ativos recentemente</dt><dd class="font-semibold">{{ $push['recent'] }}</dd></div>
                <div><dt class="text-gray-500">Revogados</dt><dd class="font-semibold">{{ $push['revoked'] }}</dd></div>
                <div><dt class="text-gray-500">Aguardando envio</dt><dd class="font-semibold">{{ $push['pending'] }}</dd></div>
                <div><dt class="text-gray-500">Falhas em 24 horas</dt><dd class="font-semibold {{ $push['failures_24h'] ? 'text-danger-600' : 'text-success-600' }}">{{ $push['failures_24h'] }}</dd></div>
            </dl>
            @if(!$push['active'])
                <p class="mt-4 rounded-lg bg-warning-50 p-3 text-sm text-warning-800 dark:bg-warning-950 dark:text-warning-200">Abra o aplicativo, entre na conta e permita notificações. O aparelho será registrado novamente, inclusive após reinstalação.</p>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Processamento</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                A central interna é gravada imediatamente. O Firebase e o Google Drive dependem dos processadores de fila executados pelo cron.
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
