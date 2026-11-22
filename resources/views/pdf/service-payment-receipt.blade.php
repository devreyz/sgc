@extends('pdf.partials.header')

@section('extra-styles')
    @include('pdf.partials.service-document-style')
@endsection
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
<div class="service-document">
    <table class="service-facts" role="presentation">
        <tr>
            <td width="58%"><span>Recebido de</span><strong>{{ $payer }}</strong></td>
            <td width="42%" class="service-highlight"><span>Valor recebido</span><strong>{{ $money($event->amount) }}</strong></td>
        </tr>
        <tr>
            <td><span>Data do pagamento</span>{{ $event->payment_date->format('d/m/Y') }}</td>
            <td><span>Forma de pagamento</span>{{ $paymentLabel }}</td>
        </tr>
        @if($agreement || filled(data_get($event->metadata, 'document_number')))
            <tr>
                <td><span>Referência do pagamento</span>{{ data_get($event->metadata, 'document_number') }}</td>
                <td><span>Acordo</span>{{ $agreement?->number }}</td>
            </tr>
        @endif
    </table>

<div class="section-title">Ordens de serviço</div>
<table class="data-table service-table">
    <thead><tr><th>Ordem</th><th class="text-right">Valor destinado</th></tr></thead>
    <tbody>
    @foreach($event->allocations as $allocation)
        <tr><td>{{ $allocation->obligation?->execution?->order?->number ?? $allocation->obligation?->number }}</td><td class="text-right">{{ $money($allocation->amount) }}</td></tr>
    @endforeach
    </tbody>
</table>

@if($installments->isNotEmpty())
    <div class="section-title">Distribuição por parcela</div>
    <table class="data-table service-table">
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
<div class="service-document-section">
    <h2 class="section-title">Registro do recebimento</h2>
    <table class="service-facts" role="presentation"><tr>
        <td><span>Registrado por</span>{{ data_get($event->metadata, 'registered_by_name') ?: $event->registrar?->name }}</td>
        <td><span>Emissão</span>{{ today()->format('d/m/Y') }}</td>
        @if($remaining !== null)<td><span>Saldo após o recebimento</span><strong>{{ $money($remaining / 100) }}</strong></td>@endif
    </tr></table>
</div>
<p class="service-disclaimer">Recibo administrativo de pagamento · Sem valor fiscal.</p>
</div>
@endsection
