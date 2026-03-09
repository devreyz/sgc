<x-filament-panels::page>
    <div style="padding: 2rem; text-align:center;">
        <h1>PDV</h1>
        <p>Link rápido para o PDV público:</p>
        <p>
            <a href="/{{ session('tenant_slug') ?? '' }}/pdv" target="_blank" class="filament-button filament-button-primary">Abrir PDV</a>
        </p>
    </div>
</x-filament-panels::page>
