@extends('layouts.bento')

@section('title', 'Histórico de Vendas PDV')
@section('page-title', 'Histórico de Vendas')
@section('user-role', 'PDV')

@section('navigation')
<a href="{{ route('pdv.index', ['tenant' => request()->route('tenant')]) }}" class="nav-tab">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
    PDV
</a>
<span class="nav-tab active">Histórico</span>
@endsection

@section('content')
<style>
    .filters-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        align-items: center;
        margin-bottom: 1rem;
    }
    .filter-input {
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 0.8125rem;
        background: var(--color-surface);
    }
    .filter-input:focus {
        outline: none;
        border-color: var(--color-primary);
    }
    .sale-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1rem;
        margin-bottom: 0.75rem;
    }
    .sale-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    .sale-code {
        font-weight: 700;
        font-size: 0.9375rem;
    }
    .sale-items-list {
        font-size: 0.8125rem;
        color: var(--color-text-muted);
        margin-bottom: 0.5rem;
    }
    .sale-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8125rem;
    }
    .sale-total {
        font-weight: 800;
        font-size: 1.1rem;
        color: var(--color-primary);
    }
    .badge-completed { background: rgba(16,185,129,0.1); color: #10b981; }
    .badge-cancelled { background: rgba(239,68,68,0.1); color: #ef4444; }
    .badge-fiado { background: rgba(245,158,11,0.1); color: #f59e0b; }
</style>

<!-- Filters -->
<div class="bento-card col-span-full" style="grid-column: 1/-1;">
    <form method="GET" class="filters-bar">
        <input type="date" name="date" class="filter-input" value="{{ request('date') }}" placeholder="Data">
        <select name="status" class="filter-input">
            <option value="">Todos Status</option>
            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Concluída</option>
            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelada</option>
        </select>
        <label style="display:flex;align-items:center;gap:0.375rem;font-size:0.8125rem;cursor:pointer;">
            <input type="checkbox" name="is_fiado" value="1" {{ request('is_fiado') ? 'checked' : '' }}>
            Apenas Fiado
        </label>
        <button type="submit" class="btn btn-primary" style="padding:0.5rem 1rem;font-size:0.8125rem;">Filtrar</button>
        <a href="{{ route('pdv.history', ['tenant' => request()->route('tenant')]) }}" class="btn btn-outline" style="padding:0.5rem 1rem;font-size:0.8125rem;">Limpar</a>
    </form>
</div>

<!-- Sales List -->
<div class="col-span-full" style="grid-column: 1/-1;">
    @forelse($sales as $sale)
        <div class="sale-card">
            <div class="sale-card-header">
                <div>
                    <span class="sale-code">{{ $sale->code }}</span>
                    @if($sale->is_fiado)
                        <span class="badge badge-fiado" style="margin-left:0.5rem;">Fiado</span>
                    @endif
                </div>
                <span class="badge {{ $sale->status === 'completed' ? 'badge-completed' : 'badge-cancelled' }}">
                    {{ $sale->status === 'completed' ? 'Concluída' : ($sale->status === 'cancelled' ? 'Cancelada' : 'Aberta') }}
                </span>
            </div>

            <div class="sale-items-list">
                @foreach($sale->items as $item)
                    {{ $item->product->name ?? 'Produto' }} ({{ number_format($item->quantity, 0) }}x R$ {{ number_format($item->unit_price, 2, ',', '.') }}){{ !$loop->last ? ' · ' : '' }}
                @endforeach
            </div>

            <div class="sale-footer">
                <div>
                    <span style="color:var(--color-text-muted);">
                        {{ $sale->display_name }} · {{ $sale->created_at->format('d/m/Y H:i') }}
                        @if($sale->payments->count())
                            · {{ $sale->payments->pluck('payment_method')->map(fn($m) => ucfirst($m))->join(', ') }}
                        @endif
                    </span>
                </div>
                <span class="sale-total">R$ {{ number_format($sale->total, 2, ',', '.') }}</span>
            </div>

            @if($sale->is_fiado && $sale->status === 'completed')
                @php $remaining = $sale->fiado_remaining; @endphp
                @if($remaining > 0)
                    <div style="margin-top:0.5rem;padding-top:0.5rem;border-top:1px solid var(--color-border);font-size:0.8125rem;color:var(--color-warning);">
                        Saldo devedor: R$ {{ number_format($remaining, 2, ',', '.') }}
                    </div>
                @else
                    <div style="margin-top:0.5rem;padding-top:0.5rem;border-top:1px solid var(--color-border);font-size:0.8125rem;color:var(--color-success);">
                        Fiado quitado
                    </div>
                @endif
            @endif
        </div>
    @empty
        <div style="text-align:center;padding:3rem;color:var(--color-text-muted);">
            Nenhuma venda encontrada.
        </div>
    @endforelse

    <div style="margin-top:1rem;">
        {{ $sales->withQueryString()->links() }}
    </div>
</div>
@endsection
