<x-filament-panels::page>
    <form wire:submit="generate" class="space-y-4">{{ $this->form }}<x-filament::button type="submit">Gerar prestação</x-filament::button> <x-filament::button wire:click="download" color="gray">Baixar PDF detalhado</x-filament::button></form>
    @include('services._accountability', ['summary' => $this->report()])
</x-filament-panels::page>
