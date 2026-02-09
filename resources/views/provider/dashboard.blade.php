@extends('layouts.bento')

@section('title', 'Dashboard do Prestador')
@section('page-title', 'Dashboard')
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard') }}" class="nav-tab active">Dashboard</a>
    <a href="{{ route('provider.orders') }}" class="nav-tab">Ordens de Serviço</a>
    <a href="{{ route('provider.works') }}" class="nav-tab">Meus Serviços</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">
    <!-- Stats Cards -->
    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon primary">📋</div>
            <div class="stat-label">Ordens Pendentes</div>
            <div class="stat-value">{{ $stats['pending_orders'] }}</div>
        </div>
    </div>

    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon secondary">🔧</div>
            <div class="stat-label">Em Andamento</div>
            <div class="stat-value">{{ $stats['in_progress_orders'] }}</div>
        </div>
    </div>

    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon warning">✅</div>
            <div class="stat-label">Concluídos (Mês)</div>
            <div class="stat-value">{{ $stats['completed_this_month'] }}</div>
        </div>
    </div>

    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon primary">💰</div>
            <div class="stat-label">Saldo Atual</div>
            <div class="stat-value">R$ {{ number_format($stats['current_balance'], 2, ',', '.') }}</div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="bento-card col-span-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold" style="font-size: 1.25rem;">Ordens de Serviço Recentes</h2>
            <a href="{{ route('provider.orders') }}" class="btn btn-outline">Ver Todas</a>
        </div>

        @if($recentOrders->count() > 0)
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Projeto</th>
                            <th>Equipamento</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentOrders as $order)
                        <tr>
                            <td>{{ $order->scheduled_date ? $order->scheduled_date->format('d/m/Y') : '-' }}</td>
                            <td>{{ $order->project->title ?? '-' }}</td>
                            <td>{{ $order->equipment->name ?? '-' }}</td>
                            <td>
                                <span class="badge badge-{{ $order->status->value === 'pending' ? 'warning' : ($order->status->value === 'in_progress' ? 'secondary' : 'success') }}">
                                    {{ $order->status->getLabel() }}
                                </span>
                            </td>
                            <td>
                                @if($order->status->value !== 'completed')
                                    <a href="{{ route('provider.work.create', $order->id) }}" class="btn btn-primary" style="padding: 0.375rem 0.75rem;">
                                        Registrar Serviço
                                    </a>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted">Nenhuma ordem de serviço recente.</p>
        @endif
    </div>

    <!-- Recent Works -->
    <div class="bento-card col-span-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold" style="font-size: 1.25rem;">Últimos Serviços</h2>
            <a href="{{ route('provider.works') }}" class="btn btn-outline" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">Ver Todos</a>
        </div>

        @if($recentWorks->count() > 0)
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                @foreach($recentWorks->take(5) as $work)
                <div style="padding: 0.75rem; background: var(--color-bg); border-radius: var(--radius-md);">
                        <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-semibold">{{ $work->work_date->format('d/m/Y') }}</span>
                        <span class="badge badge-success">R$ {{ number_format($work->total_value, 2, ',', '.') }}</span>
                    </div>
                    <p class="text-xs text-muted">{{ Str::limit($work->description, 60) }}</p>
                    <p class="text-xs text-muted mt-1">{{ $work->hours_worked }}h trabalhadas</p>
                </div>
                @endforeach
            </div>
        @else
            <p class="text-muted text-sm">Nenhum serviço registrado ainda.</p>
        @endif
    </div>

    <!-- Quick Actions -->
    <div class="bento-card col-span-12">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Ações Rápidas</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <a href="{{ route('provider.orders.create') }}" class="btn btn-success" style="font-weight: 600;">
                ➕ Criar Nova Ordem de Serviço
            </a>
            <a href="{{ route('provider.orders') }}" class="btn btn-primary">
                📋 Ver Ordens de Serviço
            </a>
            <a href="{{ route('provider.works') }}" class="btn btn-secondary">
                📊 Histórico de Serviços
            </a>
            <a href="{{ route('provider.orders', ['status' => 'pending']) }}" class="btn btn-outline">
                🔔 Ordens Pendentes
            </a>
        </div>
    </div>
</div>
@endsection
