@extends('layouts.bento')

@section('title', 'Minhas Entregas')
@section('page-title', 'Minhas Entregas')
@section('user-role', 'Associado')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('associate.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('associate.projects') }}" class="nav-tab">Projetos</a>
    <a href="{{ route('associate.deliveries') }}" class="nav-tab active">Entregas</a>
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
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pendente</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Aprovada</option>
                    <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>Entregue</option>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Data Inicial</label>
                <input type="date" name="start_date" class="form-input" value="{{ request('start_date') }}">
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Data Final</label>
                <input type="date" name="end_date" class="form-input" value="{{ request('end_date') }}">
            </div>
            <div style="margin-top: auto; display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ route('associate.deliveries') }}" class="btn btn-outline">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Deliveries List -->
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Lista de Entregas ({{ $deliveries->total() }})</h2>

        @if($deliveries->count() > 0)
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Projeto</th>
                            <th>Cliente</th>
                            <th>Produto</th>
                            <th>Quantidade</th>
                            <th>Valor Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deliveries as $delivery)
                        <tr>
                            <td>{{ $delivery->delivery_date ? $delivery->delivery_date->format('d/m/Y') : '-' }}</td>
                            <td>
                                <div class="font-semibold">{{ $delivery->project->title ?? '-' }}</div>
                            </td>
                            <td>{{ $delivery->project->customer->name ?? '-' }}</td>
                            <td>{{ $delivery->project->product->name ?? '-' }}</td>
                            <td class="font-semibold">{{ $delivery->quantity }}</td>
                            <td class="font-bold text-primary">R$ {{ number_format($delivery->total_value ?? 0, 2, ',', '.') }}</td>
                            <td>
                                <span class="badge badge-{{ $delivery->status->value === 'delivered' ? 'success' : ($delivery->status->value === 'approved' ? 'secondary' : 'warning') }}">
                                    {{ $delivery->status->getLabel() }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($deliveries->hasPages())
                <div style="margin-top: 1.5rem; display: flex; justify-content: center;">
                    {{ $deliveries->links() }}
                </div>
            @endif
        @else
            <div style="text-align: center; padding: 3rem;">
                <p class="text-muted" style="font-size: 1.125rem;">Nenhuma entrega encontrada.</p>
                <p class="text-muted text-sm" style="margin-top: 0.5rem;">As entregas dos seus projetos aparecerão aqui.</p>
            </div>
        @endif
    </div>
</div>
@endsection
