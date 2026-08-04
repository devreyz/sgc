@extends('layouts.bento')

@section('title', 'Recebimentos e Recibos')
@section('page-title', 'Recebimentos e Recibos')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Financeiro')
@php
    $bentoNavigation = \App\Support\PortalNavigation::make('finance', 'receipts', $tenant->slug);
@endphp

@section('content')
@include('finance.partials.styles')
<main class="fin-shell">
    <header class="fin-head">
        <div><h1>Recebimentos</h1><p>Registros emitidos e rascunhos</p></div>
        <div class="fin-actions"><a class="fin-btn" href="{{ route('finance.index', ['tenant'=>$tenant->slug]) }}"><i data-lucide="arrow-left"></i> Visão geral</a>@can('create', \App\Models\FinancialReceipt::class)<a class="fin-btn fin-btn-primary" href="{{ route('finance.receipts.create',['tenant'=>$tenant->slug]) }}"><i data-lucide="plus"></i> Novo recebimento</a>@endcan</div>
    </header>
    @if(session('success'))<div class="fin-alert">{{ session('success') }}</div>@endif
    <section class="fin-card">
        <form class="fin-filter" method="GET">
            <div class="fin-field fin-search"><label for="q">Buscar</label><input class="fin-input" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Pagador, documento ou número"></div>
            <div class="fin-field"><label for="status">Situação</label><select class="fin-select" id="status" name="status"><option value="">Todas</option>@foreach(\App\Enums\FinancialReceiptStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status']??'')===$status->value)>{{ $status->getLabel() }}</option>@endforeach</select></div>
            <div class="fin-field"><label for="from">De</label><input class="fin-input" id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="fin-field"><label for="until">Até</label><input class="fin-input" id="until" type="date" name="until" value="{{ $filters['until'] ?? '' }}"></div>
            <button class="fin-btn" type="submit"><i data-lucide="search"></i> Filtrar</button>
        </form>
    </section>
    <section class="fin-card">
        <div class="fin-table-wrap"><table class="fin-table"><thead><tr><th>Número</th><th>Data</th><th>Pagador</th><th>Meio</th><th>Conta</th><th>Valor</th><th>Situação</th><th></th></tr></thead><tbody>
            @forelse($receipts as $receipt)
                <tr><td>{{ $receipt->formatted_number }}</td><td>{{ $receipt->received_on->format('d/m/Y') }}</td><td>{{ $receipt->payer_name }}</td><td>{{ $receipt->payment_method->getLabel() }}</td><td>{{ $receipt->bankAccount?->name }}</td><td class="fin-money">R$ {{ number_format((float)$receipt->total_amount,2,',','.') }}</td><td><span class="fin-badge fin-badge-{{ $receipt->status->value }}">{{ $receipt->status->getLabel() }}</span></td><td><a class="fin-btn" href="{{ route('finance.receipts.show',['tenant'=>$tenant->slug,'financialReceipt'=>$receipt]) }}"><i data-lucide="eye"></i> Abrir</a></td></tr>
            @empty<tr><td colspan="8" class="fin-empty">Nenhum recibo encontrado.</td></tr>@endforelse
        </tbody></table></div>
        <div class="fin-pagination">{{ $receipts->links('vendor.pagination.bento') }}</div>
    </section>
</main>
@endsection
