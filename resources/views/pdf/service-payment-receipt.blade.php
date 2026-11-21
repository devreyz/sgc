@extends('pdf.partials.header')

@section('content')
@php
    $paymentLabel = \App\Enums\PaymentMethod::tryFrom($event->payment_method)?->getLabel() ?? $event->payment_method;
    $payer = data_get($agreement?->terms_snapshot, 'party.name') ?: data_get($event->metadata, 'payer_name')
        ?: data_get($event->allocations->first()?->obligation?->party_snapshot, 'name');
    $remaining = data_get($event->metadata, 'remaining_plan_cents') ?? data_get($event->metadata, 'remaining_obligation_cents');
    $money = fn ($amount) => 'R$ '.number_format((float) $amount, 2, ',', '.');
@endphp

@if($event->status === 'reversed')
    <div class="info-box"><strong>Recebimento estornado</strong> · Via para consulta do histórico.</div>
@endif
<div class="info-box">
    <table>
        <tr><td class="label">Pagador</td><td>{{ $payer }}</td></tr>
        <tr><td class="label">Valor recebido</td><td><strong>{{ $money($event->amount) }}</strong></td></tr>
        <tr><td class="label">Data do recebimento</td><td>{{ $event->payment_date->format('d/m/Y') }}</td></tr>
        <tr><td class="label">Data de emissão</td><td>{{ today()->format('d/m/Y') }}</td></tr>
        <tr><td class="label">Forma de pagamento</td><td>{{ $paymentLabel }}</td></tr>
        @if(filled(data_get($event->metadata, 'document_number')))
            <tr><td class="label">Referência do pagamento</td><td>{{ data_get($event->metadata, 'document_number') }}</td></tr>
        @endif
        @if($agreement)<tr><td class="label">Acordo</td><td>{{ $agreement->number }}</td></tr>@endif
        @if($remaining !== null)
            <tr><td class="label">Saldo após o recebimento</td><td>{{ $money($remaining / 100) }}</td></tr>
        @endif
        <tr><td class="label">Registrado por</td><td>{{ data_get($event->metadata, 'registered_by_name') ?: $event->registrar?->name }}</td></tr>
    </table>
</div>

<div class="section-title">Ordens de serviço</div>
<table class="data-table">
    <thead><tr><th>Ordem</th><th class="text-right">Valor destinado</th></tr></thead>
    <tbody>
    @foreach($event->allocations as $allocation)
        <tr><td>{{ $allocation->obligation?->execution?->order?->number ?? $allocation->obligation?->number }}</td><td class="text-right">{{ $money($allocation->amount) }}</td></tr>
    @endforeach
    </tbody>
</table>

@if($installments->isNotEmpty())
    <div class="section-title">Distribuição por parcela</div>
    <table class="data-table">
        <thead><tr><th>Parcela</th><th>Ordem de serviço</th><th class="text-right">Valor destinado</th></tr></thead>
        <tbody>
        @foreach($installments as $item)
            @php
                $label = $item->kind === 'entry' ? 'Entrada' : 'Parcela '.($item->number - ($agreement->plan->installments->first()?->kind === 'entry' ? 1 : 0));
                $splits = $event->metadata['installments'][$item->id]['obligations'] ?? [];
            @endphp
            @forelse($splits as $debtId => $cents)
                @php
                    $debt = $event->allocations->firstWhere('service_obligation_id', $debtId)?->obligation;
                @endphp
                <tr><td>{{ $label }}</td><td>{{ $debt?->execution?->order?->number ?? $debt?->number }}</td><td class="text-right">{{ $money($cents / 100) }}</td></tr>
            @empty
                <tr><td>{{ $label }}</td><td></td><td class="text-right">{{ $money($item->eventCents($event) / 100) }}</td></tr>
            @endforelse
        @endforeach
        </tbody>
    </table>
@endif
<p style="margin-top:12px;font-size:9px">Recibo administrativo de pagamento · Sem valor fiscal.</p>
@endsection
