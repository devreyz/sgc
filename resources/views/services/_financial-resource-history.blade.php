<section class="ds-fin-box ds-resource-history" aria-label="Recursos da ordem">
    <h3 class="ds-fin-box-head"><i class="ph-duotone ph-package" aria-hidden="true"></i> Recursos registrados</h3>
    @forelse($execution->resources as $resource)
        <div class="ds-resource-record">
            <div>
                <strong>{{ $resource->description }}</strong>
                <p>{{ match ($resource->effect) {
                    'deduct_from_receivable' => 'Desconto na cobrança',
                    'add_to_receivable' => 'Acréscimo na cobrança',
                    'reimburse_provider' => 'Reembolso ao prestador',
                    'create_association_expense' => 'Despesa da associação',
                    default => 'Registro de uso',
                } }} · {{ match ($resource->provided_by) {
                    'organization' => 'Associação',
                    'provider' => 'Prestador',
                    'customer' => 'Beneficiário',
                    default => 'Terceiro',
                } }}@if($resource->quantity !== null) · {{ \App\Support\PdfQuantityFormatter::format($resource->quantity) }} {{ $resource->unit }}@endif</p>
            </div>
            @if((float) $resource->amount > 0)<strong>R$ {{ number_format((float) $resource->amount, 2, ',', '.') }}</strong>@endif
        </div>
    @empty
        <p>Nenhum recurso registrado.</p>
    @endforelse
</section>
