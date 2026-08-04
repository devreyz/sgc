@extends('layouts.bento')

@section('title', 'Gestão Financeira')
@section('page-title', 'Gestão Financeira')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Financeiro')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('finance', 'dashboard', $tenant->slug);
@endphp

@section('content')
@include('finance.partials.styles')
<main class="fin-shell">
    <header class="fin-head">
        <div><h1>Visão financeira</h1><p>{{ now()->translatedFormat('F \d\e Y') }}</p></div>
        <div class="fin-actions">
            <a class="fin-btn" href="{{ route('finance.receipts.index', ['tenant' => $tenant->slug]) }}"><i data-lucide="files"></i> Recibos</a>
            @can('create', \App\Models\FinancialReceipt::class)
                <a class="fin-btn fin-btn-primary" href="{{ route('finance.receipts.create', ['tenant' => $tenant->slug]) }}"><i data-lucide="plus"></i> Novo recebimento</a>
            @endcan
        </div>
    </header>

    @if(session('success'))<div class="fin-alert">{{ session('success') }}</div>@endif

    <section class="fin-grid">
        <article class="fin-card fin-stat"><span>Saldo das contas</span><strong>R$ {{ number_format($summary['balance'], 2, ',', '.') }}</strong><small>{{ $accounts->count() }} conta(s) ativa(s)</small></article>
        <article class="fin-card fin-stat"><span>Entradas no mês</span><strong class="fin-income">R$ {{ number_format($summary['income'], 2, ',', '.') }}</strong><small>Movimentações confirmadas</small></article>
        <article class="fin-card fin-stat"><span>Saídas no mês</span><strong class="fin-expense">R$ {{ number_format($summary['expense'], 2, ',', '.') }}</strong><small>Movimentações confirmadas</small></article>
        <article class="fin-card fin-stat"><span>Recibos em rascunho</span><strong>{{ $summary['drafts'] }}</strong><small>Aguardando emissão</small></article>
    </section>

    <section class="fin-card">
        <div class="fin-section-title"><h2>Ferramentas</h2></div>
        <div class="fin-tool-grid">
            <a class="fin-tool" href="{{ route('finance.receipts.index', ['tenant' => $tenant->slug]) }}"><span class="fin-tool-icon"><i data-lucide="receipt-text"></i></span><span><strong>Recebimentos e recibos</strong><span>Registrar, emitir e imprimir</span></span></a>
            @foreach($tools as $tool)
                <a class="fin-tool" href="{{ $tool['url'] }}"><span class="fin-tool-icon"><i data-lucide="{{ $tool['icon'] }}"></i></span><span><strong>{{ $tool['label'] }}</strong><span>{{ $tool['description'] }}</span></span></a>
            @endforeach
        </div>
    </section>

    <section class="fin-grid">
        <article class="fin-card fin-main">
            <div class="fin-section-title"><h2>Recibos recentes</h2><a href="{{ route('finance.receipts.index', ['tenant' => $tenant->slug]) }}">Ver todos</a></div>
            <div class="fin-table-wrap"><table class="fin-table"><thead><tr><th>Recibo</th><th>Pagador</th><th>Data</th><th>Valor</th><th>Situação</th></tr></thead><tbody>
                @forelse($recentReceipts as $receipt)
                    <tr><td><a href="{{ route('finance.receipts.show', ['tenant' => $tenant->slug, 'financialReceipt' => $receipt]) }}">{{ $receipt->formatted_number }}</a></td><td>{{ $receipt->payer_name }}</td><td>{{ $receipt->received_on->format('d/m/Y') }}</td><td class="fin-money">R$ {{ number_format((float)$receipt->total_amount,2,',','.') }}</td><td><span class="fin-badge fin-badge-{{ $receipt->status->value }}">{{ $receipt->status->getLabel() }}</span></td></tr>
                @empty<tr><td colspan="5" class="fin-empty">Nenhum recebimento registrado.</td></tr>@endforelse
            </tbody></table></div>
        </article>
        <article class="fin-card fin-side">
            <div class="fin-section-title"><h2>Últimas movimentações</h2></div>
            <div class="fin-table-wrap"><table class="fin-table"><thead><tr><th>Data</th><th>Descrição</th><th>Valor</th></tr></thead><tbody>
                @forelse($recentMovements as $movement)
                    <tr><td>{{ $movement->movement_date->format('d/m') }}</td><td>{{ \Illuminate\Support\Str::limit($movement->description, 30) }}</td><td class="fin-money {{ $movement->type->value === 'income' ? 'fin-income' : 'fin-expense' }}">{{ $movement->type->value === 'income' ? '+' : '-' }} R$ {{ number_format((float)$movement->amount,2,',','.') }}</td></tr>
                @empty<tr><td colspan="3" class="fin-empty">Sem movimentações.</td></tr>@endforelse
            </tbody></table></div>
        </article>
    </section>
</main>
@endsection
