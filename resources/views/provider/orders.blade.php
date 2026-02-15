@extends('layouts.bento')

@section('title', 'Ordens de Serviço')
@section('page-title', 'Ordens de Serviço')
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('provider.orders', ['tenant' => $currentTenant->slug]) }}" class="nav-tab active">Ordens de Serviço</a>
    <a href="{{ route('provider.financial', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Financeiro</a>
    <a href="{{ route('provider.financial', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Carteira</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">

    @if(session('success'))
    <div class="bento-card col-span-full" style="border-left: 4px solid var(--color-success); background: rgba(16,185,129,0.05);">
        <p style="color: var(--color-success); font-weight: 500;">✓ {{ session('success') }}</p>
    </div>
    @endif

    <!-- Header -->
    <div class="bento-card col-span-full">
        <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:1rem;">
            <div>
                <h2 class="font-bold" style="font-size: 1.5rem;">Minhas Ordens de Serviço</h2>
                <p class="text-muted text-sm">{{ $orders->total() }} ordens encontradas</p>
            </div>
            <a href="{{ route('provider.orders.create', ['tenant' => $currentTenant->slug]) }}" class="btn btn-primary">
                <i data-lucide="plus" style="width:1rem;height:1rem"></i> Nova Ordem
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bento-card col-span-full">
        <form method="GET" class="flex gap-4 items-center" style="flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach(\App\Enums\ServiceOrderStatus::cases() as $s)
                        <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>
                            {{ $s->getLabel() }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div style="margin-top: auto;">
                <a href="{{ route('provider.orders', ['tenant' => $currentTenant->slug]) }}" class="btn btn-outline">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Tabela -->
    <div class="bento-card col-span-full">
        @if($orders->isEmpty())
            <p class="text-muted">Nenhuma ordem encontrada.</p>
        @else
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Data</th>
                        <th>Serviço</th>
                        <th>Cliente</th>
                        <th>Local</th>
                        <th>Qtd. Est.</th>
                        <th>Valor Cliente</th>
                        <th>Seu Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td class="font-semibold">{{ $order->number }}</td>
                        <td>{{ $order->scheduled_date?->format('d/m/Y') }}</td>
                        <td>
                            <div class="font-semibold">{{ optional($order->service)->name ?? '-' }}</div>
                            <div class="text-xs text-muted">{{ $order->unit }}</div>
                        </td>
                        <td>
                            @if($order->associate_id)
                                {{ optional(optional($order->associate)->user)->name ?? optional($order->associate)->name ?? '-' }}
                            @else
                                @php
                                    $avulsoName = 'Avulso';
                                    if (preg_match('/\[PESSOA AVULSA\]\nNome:\s*(.+)/m', $order->notes ?? '', $m)) {
                                        $avulsoName = trim($m[1]);
                                    }
                                @endphp
                                {{ $avulsoName }}
                                <span class="text-xs text-muted">avulso</span>
                            @endif
                        </td>
                        <td class="text-xs">{{ $order->location }}</td>
                        <td class="text-xs text-muted">
                            @if($order->quantity)
                                {{ number_format($order->quantity, 1, ',', '.') }} {{ $order->unit }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="font-semibold">R$ {{ number_format($order->final_price ?? 0, 2, ',', '.') }}</td>
                        <td class="font-semibold text-primary">R$ {{ number_format($order->provider_payment ?? 0, 2, ',', '.') }}</td>
                        <td>
                            @php
                                $badgeMap = [
                                    'scheduled' => 'badge-info',
                                    'in_progress' => 'badge-warning',
                                    'awaiting_payment' => 'badge-warning',
                                    'completed' => 'badge-success',
                                    'paid' => 'badge-success',
                                    'cancelled' => 'badge-danger',
                                    'billed' => 'badge-secondary',
                                ];
                            @endphp
                            <span class="badge {{ $badgeMap[$order->status->value] ?? 'badge-secondary' }}">
                                {{ $order->status->getLabel() }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.5rem;">
                                <a href="{{ route('provider.orders.show', ['tenant' => $currentTenant->slug, 'order' => $order->id]) }}" class="btn btn-outline" style="padding:0.375rem 0.75rem;font-size:0.75rem;">
                                    Ver
                                </a>
                                @if(in_array($order->status->value, ['scheduled', 'in_progress']))
                                <a href="{{ route('provider.orders.show', ['tenant' => $currentTenant->slug, 'order' => $order->id]) }}#complete" class="btn btn-primary" style="padding:0.375rem 0.75rem;font-size:0.75rem;">
                                    Concluir
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $orders->appends(request()->query())->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
