@extends('layouts.bento')

@section('title', 'Ordens de Serviço')
@section('page-title', 'Ordens de Serviço')
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('provider.orders') }}" class="nav-tab active">Ordens de Serviço</a>
    <a href="{{ route('provider.works') }}" class="nav-tab">Meus Serviços</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">
    <!-- Header with Create Button -->
    <div class="bento-card col-span-full">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-bold" style="font-size: 1.5rem;">Minhas Ordens de Serviço</h2>
                <p class="text-muted text-sm">Gerencie suas ordens de serviço</p>
            </div>
            <a href="{{ route('provider.orders.create') }}" class="btn btn-success">
                ➕ Criar Nova Ordem
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="bento-card col-span-full">
        <form method="GET" class="flex gap-4 items-center" style="flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Filtrar por Status</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>Agendado</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>Em Andamento</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Concluída</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>
            <div style="margin-top: auto;">
                <a href="{{ route('provider.orders') }}" class="btn btn-outline">Limpar Filtros</a>
            </div>
        </form>
    </div>

    <!-- Orders Table -->
    <div class="bento-card col-span-full">
        @if (session('success'))
            <div class="alert alert-success mb-4">{{ session('success') }}</div>
        @endif

        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Lista de Ordens ({{ $orders->total() }})</h2>

        @if($orders->count() > 0)
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Data Agendada</th>
                            <th>Serviço</th>
                            <th>Associado</th>
                            <th>Local</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                        <tr>
                            <td class="font-semibold">{{ $order->number ?? '#'.$order->id }}</td>
                            <td>{{ $order->scheduled_date ? $order->scheduled_date->format('d/m/Y') : '-' }}</td>
                            <td>
                                <div class="font-semibold">{{ $order->service->name ?? '-' }}</div>
                                <div class="text-xs text-muted">{{ $order->quantity }} {{ $order->unit }}</div>
                            </td>
                            <td>{{ $order->associate->name ?? '-' }}</td>
                            <td class="text-xs">{{ Str::limit($order->location, 30) }}</td>
                            <td class="font-semibold">R$ {{ number_format($order->final_price ?? 0, 2, ',', '.') }}</td>
                            <td>
                                <span class="badge badge-{{ $order->status->value === 'scheduled' ? 'warning' : ($order->status->value === 'in_progress' ? 'secondary' : ($order->status->value === 'completed' ? 'success' : 'danger')) }}">
                                    {{ $order->status->getLabel() }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="{{ route('provider.orders.show', $order->id) }}" class="btn btn-outline" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                                        Ver
                                    </a>
                                    @if($order->status->value === 'scheduled' || $order->status->value === 'in_progress')
                                        <a href="{{ route('provider.orders.show', $order->id) }}#complete" class="btn btn-success" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
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

            <!-- Pagination -->
            @if($orders->hasPages())
                <div style="margin-top: 1.5rem; display: flex; justify-content: center;">
                    {{ $orders->links() }}
                </div>
            @endif
        @else
            <div style="text-align: center; padding: 3rem;">
                <p class="text-muted" style="font-size: 1.125rem;">Nenhuma ordem de serviço encontrada.</p>
                <p class="text-muted text-sm" style="margin-top: 0.5rem;">Clique em "Criar Nova Ordem" para começar.</p>
                <a href="{{ route('provider.orders.create') }}" class="btn btn-success" style="margin-top: 1rem;">
                    ➕ Criar Minha Primeira Ordem
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
