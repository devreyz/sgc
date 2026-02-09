@extends('layouts.bento')

@section('title', 'Meus Projetos')
@section('page-title', 'Meus Projetos')
@section('user-role', 'Associado')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('associate.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('associate.projects') }}" class="nav-tab active">Projetos</a>
    <a href="{{ route('associate.deliveries') }}" class="nav-tab">Entregas</a>
    <a href="{{ route('associate.ledger') }}" class="nav-tab">Extrato</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">
    <!-- Filters -->
    <div class="bento-card col-span-full">
        <form method="GET" class="flex gap-4 items-center" style="flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Filtrar por Status</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="planning" {{ request('status') === 'planning' ? 'selected' : '' }}>Planejamento</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>Em Andamento</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Concluído</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                </select>
            </div>
            <div style="margin-top: auto;">
                <a href="{{ route('associate.projects') }}" class="btn btn-outline">Limpar Filtros</a>
            </div>
        </form>
    </div>

    <!-- Projects Grid -->
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Lista de Projetos ({{ $projects->total() }})</h2>

        @if($projects->count() > 0)
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem;">
                @foreach($projects as $project)
                <div style="padding: 1.5rem; background: var(--color-bg); border-radius: var(--radius-lg); border: 1px solid var(--color-border);">
                    <div class="flex justify-between items-start mb-3">
                        <h3 class="font-bold" style="font-size: 1.125rem;">{{ $project->title }}</h3>
                        <span class="badge badge-{{ $project->status->value === 'completed' ? 'success' : ($project->status->value === 'in_progress' ? 'secondary' : ($project->status->value === 'cancelled' ? 'danger' : 'warning')) }}">
                            {{ $project->status->getLabel() }}
                        </span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
                        <div>
                            <div class="text-xs text-muted">Cliente</div>
                            <div class="text-sm font-semibold">{{ $project->customer->name ?? '-' }}</div>
                        </div>

                        <div>
                            <div class="text-xs text-muted">Produto</div>
                            <div class="text-sm">{{ $project->product->name ?? '-' }}</div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div>
                                <div class="text-xs text-muted">Quantidade</div>
                                <div class="text-sm font-semibold">{{ $project->quantity ?? 0 }}</div>
                            </div>

                            <div>
                                <div class="text-xs text-muted">Entregue</div>
                                <div class="text-sm font-semibold text-success">{{ $project->delivered_quantity ?? 0 }}</div>
                            </div>
                        </div>

                        @if($project->total_value)
                        <div>
                            <div class="text-xs text-muted">Valor Total</div>
                            <div class="text-sm font-bold text-primary">R$ {{ number_format($project->total_value, 2, ',', '.') }}</div>
                        </div>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        <a href="{{ route('associate.projects.show', $project->id) }}" class="btn btn-primary" style="flex: 1; padding: 0.5rem;">
                            Ver Detalhes
                        </a>
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if($projects->hasPages())
                <div style="margin-top: 1.5rem; display: flex; justify-content: center;">
                    {{ $projects->links() }}
                </div>
            @endif
        @else
            <div style="text-align: center; padding: 3rem;">
                <p class="text-muted" style="font-size: 1.125rem;">Nenhum projeto encontrado.</p>
                <p class="text-muted text-sm" style="margin-top: 0.5rem;">Entre em contato com a cooperativa para iniciar novos projetos.</p>
            </div>
        @endif
    </div>
</div>
@endsection
