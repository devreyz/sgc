@extends('layouts.bento')

@section('title', 'Dashboard do Associado')
@section('page-title', 'Meu Painel')
@section('user-role', 'Associado')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('associate.dashboard', ['tenant' => $currentTenant->slug]) }}" class="nav-tab active">Dashboard</a>
    <a href="{{ route('associate.projects', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Projetos</a>
    <a href="{{ route('associate.deliveries', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Entregas</a>
    <a href="{{ route('associate.ledger', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">Extrato</a>
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
            <div class="stat-icon primary">📦</div>
            <div class="stat-label">Projetos Ativos</div>
            <div class="stat-value">{{ $stats['active_projects'] }}</div>
        </div>
    </div>

    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon warning">🚚</div>
            <div class="stat-label">Entregas Pendentes</div>
            <div class="stat-value">{{ $stats['pending_deliveries'] }}</div>
        </div>
    </div>

    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon secondary">✅</div>
            <div class="stat-label">Entregue (Mês)</div>
            <div class="stat-value">{{ $stats['total_delivered_this_month'] }}</div>
        </div>
    </div>

    <div class="bento-card col-span-3">
        <div class="stat-card">
            <div class="stat-icon {{ $stats['current_balance'] >= 0 ? 'primary' : 'danger' }}">💰</div>
            <div class="stat-label">Saldo Atual</div>
            <div class="stat-value {{ $stats['current_balance'] < 0 ? 'text-danger' : '' }}">
                R$ {{ number_format($stats['current_balance'], 2, ',', '.') }}
            </div>
        </div>
    </div>

    <!-- Recent Projects -->
    <div class="bento-card col-span-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold" style="font-size: 1.25rem;">Projetos Recentes</h2>
            <a href="{{ route('associate.projects', ['tenant' => $currentTenant->slug]) }}" class="btn btn-outline">Ver Todos</a>
        </div>

        @if($recentProjects->count() > 0)
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Projeto</th>
                            <th>Cliente</th>
                            <th>Produtos</th>
                            <th>Progresso</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentProjects as $project)
                        <tr>
                            <td>
                                <div class="font-semibold">{{ $project->title }}</div>
                                <div class="text-xs text-muted">{{ $project->created_at->format('d/m/Y') }}</div>
                            </td>
                            <td>{{ $project->customer->name ?? '-' }}</td>
                            <td>
                                @if($project->demands && $project->demands->count() > 0)
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                        @foreach($project->demands->take(2) as $demand)
                                            <div class="text-xs">
                                                {{ $demand->product->name ?? '-' }}
                                                <span class="text-muted">({{ rtrim(rtrim(number_format($demand->target_quantity, 3, ',', '.'), '0'), ',') }} {{ $demand->product->unit ?? '' }})</span>
                                            </div>
                                        @endforeach
                                        @if($project->demands->count() > 2)
                                            <div class="text-xs text-muted">+{{ $project->demands->count() - 2 }} produto(s)</div>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $totalTarget = $project->demands->sum('target_quantity');
                                    $totalDelivered = $project->deliveries->where('associate_id', auth()->user()->associate->id)->sum('quantity');
                                    $progress = $totalTarget > 0 ? ($totalDelivered / $totalTarget) * 100 : 0;
                                @endphp
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="flex: 1; height: 6px; background: var(--color-bg); border-radius: 999px; overflow: hidden;">
                                        <div style="height: 100%; background: var(--color-success); width: {{ min($progress, 100) }}%;"></div>
                                    </div>
                                    <span class="text-xs font-semibold">{{ number_format(min($progress, 100), 0) }}%</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-{{ $project->status->value === 'completed' ? 'success' : ($project->status->value === 'in_progress' ? 'secondary' : 'warning') }}">
                                    {{ $project->status->getLabel() }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('associate.projects.show', ['tenant' => $currentTenant->slug, 'project' => $project->id]) }}" class="btn btn-outline" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                                    Ver Detalhes
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted">Nenhum projeto cadastrado ainda.</p>
        @endif
    </div>

    <!-- Recent Transactions -->
    <div class="bento-card col-span-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold" style="font-size: 1.25rem;">Últimas Transações</h2>
            <a href="{{ route('associate.ledger', ['tenant' => $currentTenant->slug]) }}" class="btn btn-outline" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">Ver Extrato</a>
        </div>

        @if($recentTransactions->count() > 0)
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                @foreach($recentTransactions->take(6) as $transaction)
                <div style="padding: 0.75rem; background: var(--color-bg); border-radius: var(--radius-md);">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-semibold">{{ $transaction->transaction_date->format('d/m/Y') }}</span>
                        <span class="badge badge-{{ $transaction->type->value === 'credit' ? 'success' : 'danger' }}">
                            {{ $transaction->type->value === 'credit' ? '+' : '-' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                        </span>
                    </div>
                    <p class="text-xs text-muted">{{ $transaction->category->getLabel() }}</p>
                    @if($transaction->description)
                        <p class="text-xs text-muted mt-1">{{ Str::limit($transaction->description, 50) }}</p>
                    @endif
                </div>
                @endforeach
            </div>
        @else
            <p class="text-muted text-sm">Nenhuma transação registrada ainda.</p>
        @endif
    </div>

    <!-- Quick Actions -->
    <div class="bento-card col-span-12">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Ações Rápidas</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <a href="{{ route('associate.projects', ['tenant' => $currentTenant->slug]) }}" class="btn btn-primary">
                📦 Meus Projetos
            </a>
            <a href="{{ route('associate.deliveries', ['tenant' => $currentTenant->slug]) }}" class="btn btn-secondary">
                🚚 Minhas Entregas
            </a>
            <a href="{{ route('associate.ledger', ['tenant' => $currentTenant->slug]) }}" class="btn btn-outline">
                💳 Ver Extrato
            </a>
            <a href="{{ route('associate.deliveries', ['tenant' => $currentTenant->slug, 'status' => 'pending']) }}" class="btn btn-outline">
                ⏳ Entregas Pendentes
            </a>
        </div>
    </div>
</div>
@endsection
