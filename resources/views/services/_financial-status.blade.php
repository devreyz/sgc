@php
    [$label, $icon] = match ($financialState) {
        'incoming' => ['Cobrança em aberto', 'ph-arrow-down-left'],
        'outgoing' => ['Repasse em aberto', 'ph-arrow-up-right'],
        'both' => ['Recebimento e repasse pendentes', 'ph-arrows-left-right'],
        'settled' => ['Quitada', 'ph-check-circle'],
        default => ['Concluída', 'ph-seal-check'],
    };
@endphp
<span class="svc-financial-status" data-money-flow="{{ $financialState }}">
    <i class="ph {{ $icon }}" aria-hidden="true"></i>
    <span>{{ $label }}</span>
</span>
