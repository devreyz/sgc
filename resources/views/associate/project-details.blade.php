@extends('layouts.bento')

@section('title', 'Detalhes do Projeto')
@section('page-title', $project->title)
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
    <!-- Back Button -->
    <div class="bento-card col-span-full">
        <a href="{{ route('associate.projects') }}" class="btn btn-outline">
            ← Voltar para Projetos
        </a>
    </div>

    <!-- Project Info -->
    <div class="bento-card col-span-6">
        <div class="flex justify-between items-start mb-4">
            <h2 class="font-bold" style="font-size: 1.25rem;">Informações do Projeto</h2>
            <span class="badge badge-{{ $project->status->value === 'completed' ? 'success' : ($project->status->value === 'in_progress' ? 'secondary' : ($project->status->value === 'cancelled' ? 'danger' : 'warning')) }}">
                {{ $project->status->getLabel() }}
            </span>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div>
                <div class="text-xs text-muted">Cliente</div>
                <div class="font-semibold">{{ $project->customer->name ?? '-' }}</div>
                @if($project->customer->cpf_cnpj)
                    <div class="text-xs text-muted">CPF/CNPJ: {{ $project->customer->cpf_cnpj }}</div>
                @endif
            </div>

            <div>
                <div class="text-xs text-muted">Produto</div>
                <div class="font-semibold">{{ $project->product->name ?? '-' }}</div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <div class="text-xs text-muted">Quantidade Total</div>
                    <div class="font-bold" style="font-size: 1.5rem;">{{ $project->quantity ?? 0 }}</div>
                </div>

                <div>
                    <div class="text-xs text-muted">Entregue</div>
                    <div class="font-bold text-success" style="font-size: 1.5rem;">{{ $project->delivered_quantity ?? 0 }}</div>
                </div>
            </div>

            @if($project->start_date || $project->end_date)
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                @if($project->start_date)
                <div>
                    <div class="text-xs text-muted">Data Início</div>
                    <div class="text-sm">{{ $project->start_date->format('d/m/Y') }}</div>
                </div>
                @endif

                @if($project->end_date)
                <div>
                    <div class="text-xs text-muted">Data Término</div>
                    <div class="text-sm">{{ $project->end_date->format('d/m/Y') }}</div>
                </div>
                @endif
            </div>
            @endif

            @if($project->total_value)
            <div>
                <div class="text-xs text-muted">Valor Total do Projeto</div>
                <div class="font-bold text-primary" style="font-size: 1.75rem;">R$ {{ number_format($project->total_value, 2, ',', '.') }}</div>
            </div>
            @endif

            @if($project->notes)
            <div>
                <div class="text-xs text-muted">Observações</div>
                <div class="text-sm" style="padding: 0.75rem; background: var(--color-bg); border-radius: var(--radius-md);">
                    {{ $project->notes }}
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Progress Chart -->
    <div class="bento-card col-span-6">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Progresso de Entregas</h2>
        
        @php
            $progress = $project->quantity > 0 ? ($project->delivered_quantity / $project->quantity) * 100 : 0;
        @endphp

        <div style="margin-bottom: 2rem;">
            <div class="flex justify-between mb-2">
                <span class="text-sm text-muted">Progresso</span>
                <span class="font-bold">{{ number_format($progress, 1) }}%</span>
            </div>
            <div style="height: 12px; background: var(--color-bg); border-radius: 999px; overflow: hidden;">
                <div style="height: 100%; background: var(--color-primary); width: {{ $progress }}%; transition: width 0.3s;"></div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div style="padding: 1rem; background: var(--color-bg); border-radius: var(--radius-md); text-align: center;">
                <div class="text-xs text-muted mb-1">Total</div>
                <div class="font-bold" style="font-size: 1.5rem;">{{ $project->quantity ?? 0 }}</div>
            </div>

            <div style="padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: var(--radius-md); text-align: center;">
                <div class="text-xs text-muted mb-1">Entregue</div>
                <div class="font-bold text-success" style="font-size: 1.5rem;">{{ $project->delivered_quantity ?? 0 }}</div>
            </div>

            <div style="padding: 1rem; background: rgba(245, 158, 11, 0.1); border-radius: var(--radius-md); text-align: center;">
                <div class="text-xs text-muted mb-1">Restante</div>
                <div class="font-bold text-warning" style="font-size: 1.5rem;">{{ ($project->quantity ?? 0) - ($project->delivered_quantity ?? 0) }}</div>
            </div>
        </div>
    </div>

    <!-- Deliveries -->
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Entregas Realizadas</h2>

        @if($project->deliveries && $project->deliveries->count() > 0)
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data Entrega</th>
                            <th>Quantidade</th>
                            <th>Valor Unitário</th>
                            <th>Valor Total</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($project->deliveries as $delivery)
                        <tr>
                            <td>{{ $delivery->delivery_date ? $delivery->delivery_date->format('d/m/Y') : '-' }}</td>
                            <td class="font-semibold">{{ $delivery->quantity }}</td>
                            <td>R$ {{ number_format($delivery->unit_value ?? 0, 2, ',', '.') }}</td>
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
        @else
            <p class="text-muted">Nenhuma entrega registrada ainda.</p>
        @endif
    </div>

    <!-- Payments -->
    @if($project->payments && $project->payments->count() > 0)
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Pagamentos Recebidos</h2>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Referência</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($project->payments as $payment)
                    <tr>
                        <td>{{ $payment->payment_date ? $payment->payment_date->format('d/m/Y') : '-' }}</td>
                        <td class="font-bold text-success">R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                        <td>{{ $payment->payment_method->getLabel() ?? '-' }}</td>
                        <td class="text-sm text-muted">{{ $payment->reference ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
