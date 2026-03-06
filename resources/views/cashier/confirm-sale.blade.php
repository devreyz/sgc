@extends('layouts.bento')

@section('title', 'Confirmar Venda')
@section('page-title', 'Confirmar Venda')
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
    <a href="{{ $tenantSlug ? route('cashier.history', ['tenant' => $tenantSlug]) : url('/') }}" class="nav-tab">Histórico</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">

    @if(session('error'))
    <div class="bento-card col-span-full" style="border-left: 4px solid var(--color-danger); background: rgba(239, 68, 68, 0.05);">
        <p style="color: var(--color-danger); font-weight: 500;">❌ {{ session('error') }}</p>
    </div>
    @endif

    <!-- Resumo da Venda -->
    <div class="bento-card col-span-full lg:col-span-8" style="margin:0 auto;max-width:600px;">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;text-align:center;">
            ✅ Confirmar Venda #{{ $sale->id }}
        </h2>

        <div style="padding:1.5rem;background:var(--color-bg);border-radius:var(--radius-lg);margin-bottom:1.5rem;">
            <div style="display:grid;gap:1rem;">
                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--color-border);">
                    <span class="text-muted">Produto</span>
                    <span class="font-semibold">{{ $sale->product->name }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--color-border);">
                    <span class="text-muted">Quantidade</span>
                    <span class="font-semibold">{{ number_format($sale->quantity, 3, ',', '.') }} {{ $sale->product->unit }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--color-border);">
                    <span class="text-muted">Preço Unitário</span>
                    <span class="font-semibold">R$ {{ number_format($sale->unit_price, 2, ',', '.') }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--color-border);">
                    <span class="text-muted">Pagamento</span>
                    <span class="font-semibold">
                        {{ match($sale->payment_method) {
                            'dinheiro' => '💵 Dinheiro',
                            'pix' => '📱 PIX',
                            'cartao_debito' => '💳 Débito',
                            'cartao_credito' => '💳 Crédito',
                            'boleto' => '📄 Boleto',
                            default => '🔹 Outro',
                        } }}
                    </span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:0.5rem 0;border-bottom:1px solid var(--color-border);">
                    <span class="text-muted">Estoque Disponível</span>
                    <span class="font-semibold {{ $sale->product->current_stock < $sale->quantity ? 'text-danger' : '' }}">
                        {{ number_format($sale->product->current_stock, 3, ',', '.') }} {{ $sale->product->unit }}
                    </span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:1rem 0;">
                    <span class="font-bold" style="font-size:1.125rem;">TOTAL</span>
                    <span class="font-bold" style="font-size:1.5rem;color:var(--color-success);">
                        R$ {{ number_format($sale->quantity * $sale->unit_price, 2, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>

        @if($sale->product->current_stock < $sale->quantity)
        <div style="padding:1rem;background:rgba(239,68,68,0.1);border-radius:var(--radius-md);margin-bottom:1rem;text-align:center;">
            <p style="color:var(--color-danger);font-weight:600;">
                ⚠️ Estoque insuficiente! Disponível: {{ number_format($sale->product->current_stock, 3, ',', '.') }} {{ $sale->product->unit }}
            </p>
        </div>
        @endif

        <div style="display:flex;gap:1rem;justify-content:center;">
            <form action="{{ $tenantSlug ? route('cashier.storeConfirm', ['tenant' => $tenantSlug, 'sale' => $sale->id]) : url('/') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary" style="padding:1rem 2rem;font-size:1rem;"
                        {{ $sale->product->current_stock < $sale->quantity ? 'disabled' : '' }}>
                    ✅ Confirmar Venda
                </button>
            </form>

            <form action="{{ $tenantSlug ? route('cashier.cancel', ['tenant' => $tenantSlug, 'sale' => $sale->id]) : url('/') }}" method="POST"
                  onsubmit="return confirm('Cancelar esta venda?')">
                @csrf
                <button type="submit" class="btn" style="padding:1rem 2rem;font-size:1rem;background:var(--color-danger);color:white;">
                    ❌ Cancelar
                </button>
            </form>

            <a href="{{ $tenantSlug ? route('cashier.dashboard', ['tenant' => $tenantSlug]) : url('/') }}" class="btn btn-outline" style="padding:1rem 2rem;font-size:1rem;">
                ← Voltar
            </a>
        </div>
    </div>
</div>
@endsection
