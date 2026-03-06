@extends('layouts.bento')

@section('title', 'Caixa - Vendas Rápidas')
@section('page-title', 'Caixa')
@section('user-role', 'Operador de Caixa')

@php
    $routeTenant = request()->route('tenant');
    $routeSlug = is_string($routeTenant) ? $routeTenant : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);
    $tenantSlug = $currentTenant?->slug ?? session('tenant_slug') ?? $routeSlug ?? null;
@endphp

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ $tenantSlug ? route('cashier.dashboard', ['tenant' => $tenantSlug]) : url('/') }}" class="nav-tab active">Caixa</a>
    <a href="{{ $tenantSlug ? route('cashier.create', ['tenant' => $tenantSlug]) : url('/') }}" class="nav-tab">Nova Venda</a>
    <a href="{{ $tenantSlug ? route('cashier.history', ['tenant' => $tenantSlug]) : url('/') }}" class="nav-tab">Histórico</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">

    @if(session('success'))
    <div class="bento-card col-span-full" style="border-left: 4px solid var(--color-success); background: rgba(16, 185, 129, 0.05);">
        <p style="color: var(--color-success); font-weight: 500;">✅ {{ session('success') }}</p>
    </div>
    @endif

    @if(session('error'))
    <div class="bento-card col-span-full" style="border-left: 4px solid var(--color-danger); background: rgba(239, 68, 68, 0.05);">
        <p style="color: var(--color-danger); font-weight: 500;">❌ {{ session('error') }}</p>
    </div>
    @endif

    <!-- Stats -->
    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon primary">💰</div>
            <div class="stat-label">Total Hoje</div>
            <div class="stat-value">R$ {{ number_format($stats['total_today'], 2, ',', '.') }}</div>
        </div>
    </div>

    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon secondary">🛒</div>
            <div class="stat-label">Vendas Hoje</div>
            <div class="stat-value">{{ $stats['sales_count'] }}</div>
        </div>
    </div>

    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon warning">⏳</div>
            <div class="stat-label">Pendentes</div>
            <div class="stat-value">{{ $stats['pending_count'] }}</div>
        </div>
    </div>

    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon danger">📦</div>
            <div class="stat-label">Estoque Baixo</div>
            <div class="stat-value">{{ $stats['products_low_stock'] }}</div>
        </div>
    </div>

    <!-- Ação Principal -->
    <div class="bento-card col-span-full lg:col-span-4">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">⚡ Ação Rápida</h2>
        <a href="{{ $tenantSlug ? route('cashier.create', ['tenant' => $tenantSlug]) : url('/') }}" class="btn btn-primary" style="width:100%;justify-content:center;padding:1rem;font-size:1.125rem;">
            🛒 Nova Venda
        </a>

        <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border-color, #e5e7eb);">
            <a href="{{ $tenantSlug ? route('cashier.history', ['tenant' => $tenantSlug]) : url('/') }}" class="btn btn-outline" style="width:100%;justify-content:center;">
                📋 Ver Histórico Completo
            </a>
        </div>
    </div>

    <!-- Vendas Pendentes -->
    <div class="bento-card col-span-full lg:col-span-8">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">
            ⏳ Vendas Pendentes
        </h2>

        @if($pendingSales->count() > 0)
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Total</th>
                        <th>Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pendingSales as $sale)
                    <tr>
                        <td class="font-semibold">{{ $sale->id }}</td>
                        <td>{{ $sale->product->name ?? '-' }}</td>
                        <td>{{ number_format($sale->quantity, 3, ',', '.') }} {{ $sale->product->unit ?? '' }}</td>
                        <td class="font-bold">R$ {{ number_format($sale->quantity * $sale->unit_price, 2, ',', '.') }}</td>
                        <td>
                            <span class="badge badge-info">
                                {{ match($sale->payment_method) {
                                    'dinheiro' => '💵 Dinheiro',
                                    'pix' => '📱 PIX',
                                    'cartao_debito' => '💳 Débito',
                                    'cartao_credito' => '💳 Crédito',
                                    'boleto' => '📄 Boleto',
                                    default => '🔹 Outro',
                                } }}
                            </span>
                        </td>
                        <td style="display:flex;gap:0.5rem;">
                            <a href="{{ $tenantSlug ? route('cashier.confirm', ['tenant' => $tenantSlug, 'sale' => $sale->id]) : url('/') }}" class="btn btn-primary" style="padding:0.375rem 0.75rem;font-size:0.75rem;">
                                ✅ Confirmar
                            </a>
                            <form action="{{ $tenantSlug ? route('cashier.cancel', ['tenant' => $tenantSlug, 'sale' => $sale->id]) : url('/') }}" method="POST" onsubmit="return confirm('Tem certeza que deseja cancelar?')">
                                @csrf
                                <button type="submit" class="btn" style="padding:0.375rem 0.75rem;font-size:0.75rem;background:var(--color-danger);color:white;">
                                    ❌ Cancelar
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
            <div style="text-align:center;padding:2rem;">
                <p style="color:var(--color-text-muted);">Nenhuma venda pendente</p>
            </div>
        @endif
    </div>

    <!-- Vendas Confirmadas Hoje -->
    @if($todaySales->count() > 0)
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">
            ✅ Vendas Confirmadas Hoje ({{ $todaySales->count() }})
        </h2>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Produto</th>
                        <th>Qtd</th>
                        <th>Preço/Un</th>
                        <th>Total</th>
                        <th>Pagamento</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($todaySales as $sale)
                    <tr>
                        <td>{{ $sale->id }}</td>
                        <td>{{ $sale->product->name ?? '-' }}</td>
                        <td>{{ number_format($sale->quantity, 3, ',', '.') }} {{ $sale->product->unit ?? '' }}</td>
                        <td>R$ {{ number_format($sale->unit_price, 2, ',', '.') }}</td>
                        <td class="font-bold" style="color:var(--color-success);">R$ {{ number_format($sale->quantity * $sale->unit_price, 2, ',', '.') }}</td>
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
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr style="font-weight:700;background:var(--color-bg);">
                        <td colspan="4" style="text-align:right;">Total do Dia:</td>
                        <td style="color:var(--color-success);">R$ {{ number_format($stats['total_today'], 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    <!-- Produtos Disponíveis (Referência Rápida) -->
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">📦 Produtos com Estoque</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;">
            @foreach($products->take(12) as $product)
            <div style="padding:1rem;border:1px solid var(--color-border);border-radius:var(--radius-lg);background:var(--color-bg);">
                <div class="font-semibold" style="margin-bottom:0.25rem;">{{ $product->name }}</div>
                <div class="text-xs text-muted">Estoque: {{ number_format($product->current_stock, 3, ',', '.') }} {{ $product->unit }}</div>
                <div style="margin-top:0.5rem;display:flex;justify-content:space-between;align-items:center;">
                    <span class="font-bold" style="color:var(--color-success);">R$ {{ number_format($product->sale_price, 2, ',', '.') }}</span>
                    @if($product->isLowStock())
                        <span class="badge badge-danger">Baixo</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
