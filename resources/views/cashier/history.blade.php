@extends('layouts.bento')

@section('title', 'Histórico de Vendas')
@section('page-title', 'Histórico')
@section('user-role', 'Operador de Caixa')

@php
    $routeTenant = request()->route('tenant');
    $routeSlug = is_string($routeTenant) ? $routeTenant : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);
    $tenantSlug = $currentTenant?->slug ?? session('tenant_slug') ?? $routeSlug ?? null;
@endphp

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ $tenantSlug ? route('cashier.dashboard', ['tenant' => $tenantSlug]) : url('/') }}" class="nav-tab">Caixa</a>
    <a href="{{ $tenantSlug ? route('cashier.create', ['tenant' => $tenantSlug]) : url('/') }}" class="nav-tab">Nova Venda</a>
    <a href="{{ $tenantSlug ? route('cashier.history', ['tenant' => $tenantSlug]) : url('/') }}" class="nav-tab active">Histórico</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">

    

    <!-- Filtros -->
    <div class="bento-card col-span-full">
        <form method="GET" action="{{ $tenantSlug ? route('cashier.history', ['tenant' => $tenantSlug]) : url('/') }}" style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pendente</option>
                    <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmada</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label">Data</label>
                <input type="date" name="date" class="form-input" value="{{ request('date') }}">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
            <a href="{{ $tenantSlug ? route('cashier.history', ['tenant' => $tenantSlug]) : url('/') }}" class="btn btn-outline">Limpar</a>
        </form>
    </div>

    <!-- Tabela de Vendas -->
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">📋 Histórico de Vendas</h2>

        @if($sales->count() > 0)
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Data</th>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Preço/Un</th>
                        <th>Total</th>
                        <th>Pagamento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($sales as $sale)
                    <tr>
                        <td class="font-semibold">{{ $sale->id }}</td>
                        <td>{{ $sale->sale_date?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $sale->product->name ?? '-' }}</td>
                        <td>{{ number_format($sale->quantity, 3, ',', '.') }} {{ $sale->product->unit ?? '' }}</td>
                        <td>R$ {{ number_format($sale->unit_price, 2, ',', '.') }}</td>
                        <td class="font-bold">R$ {{ number_format($sale->quantity * $sale->unit_price, 2, ',', '.') }}</td>
                        <td>
                            {{ match($sale->payment_method) {
                                'dinheiro' => '💵 Dinheiro',
                                'pix' => '📱 PIX',
                                'cartao_debito' => '💳 Débito',
                                'cartao_credito' => '💳 Crédito',
                                'boleto' => '📄 Boleto',
                                default => '🔹 Outro',
                            } }}
                        </td>
                        <td>
                            <span class="badge badge-{{ match($sale->status) { 'pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'danger', default => 'secondary' } }}">
                                {{ match($sale->status) { 'pending' => 'Pendente', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', default => $sale->status } }}
                            </span>
                        </td>
                        <td>
                            @if($sale->status === 'pending')
                                <div style="display:flex;gap:0.25rem;">
                                    <a href="{{ $tenantSlug ? route('cashier.confirm', ['tenant' => $tenantSlug, 'sale' => $sale->id]) : url('/') }}" class="btn btn-primary" style="padding:0.25rem 0.5rem;font-size:0.7rem;">✅</a>
                                    <form action="{{ $tenantSlug ? route('cashier.cancel', ['tenant' => $tenantSlug, 'sale' => $sale->id]) : url('/') }}" method="POST" onsubmit="return confirm('Cancelar?')">
                                        @csrf
                                        <button type="submit" class="btn" style="padding:0.25rem 0.5rem;font-size:0.7rem;background:var(--color-danger);color:white;">❌</button>
                                    </form>
                                </div>
                            @elseif($sale->status === 'confirmed')
                                <form action="{{ $tenantSlug ? route('cashier.cancel', ['tenant' => $tenantSlug, 'sale' => $sale->id]) : url('/') }}" method="POST" onsubmit="return confirm('Cancelar e estornar estoque?')">
                                    @csrf
                                    <button type="submit" class="btn" style="padding:0.25rem 0.5rem;font-size:0.7rem;background:var(--color-danger);color:white;">Estornar</button>
                                </form>
                            @else
                                <span class="text-muted text-xs">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:1rem;">
            {{ $sales->withQueryString()->links() }}
        </div>
        @else
            <div style="text-align:center;padding:2rem;">
                <p class="text-muted">Nenhuma venda encontrada com os filtros selecionados.</p>
            </div>
        @endif
    </div>
</div>
@endsection
