@extends('layouts.bento')

@section('title', 'Histórico de Serviços')
@section('page-title', 'Meus Serviços')
@section('user-role', 'Prestador de Serviço')

@php($bentoNavigation = \App\Support\PortalNavigation::make('provider', 'orders', request()->route('tenant')))

@section('content')
<div class="bento-grid">
    <!-- Filters -->
    <div class="bento-card col-span-full">
        <form method="GET" class="flex gap-4 items-center" style="flex-wrap: wrap;">
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
                <a href="{{ route('provider.works') }}" class="btn btn-outline">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Total Value -->
    <div class="bento-card col-span-full">
        <div class="stat-card">
            <div class="stat-label">Valor Total do Período</div>
            <div class="stat-value text-primary">R$ {{ number_format($totalValue, 2, ',', '.') }}</div>
        </div>
    </div>

    <!-- Works Table -->
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Histórico de Serviços ({{ $works->total() }})</h2>

        @if($works->count() > 0)
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Ordem de Serviço</th>
                            <th>Descrição</th>
                            <th>Horas</th>
                            <th>Valor</th>
                            <th>Comprovante</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($works as $work)
                        <tr>
                            <td>{{ $work->work_date->format('d/m/Y') }}</td>
                            <td>
                                @if($work->serviceOrder)
                                    <div class="font-semibold">{{ $work->serviceOrder->project->title ?? '-' }}</div>
                                    <div class="text-xs text-muted">{{ $work->serviceOrder->equipment->name ?? '-' }}</div>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                <div class="text-sm">{{ Str::limit($work->description, 80) }}</div>
                            </td>
                            <td>{{ $work->hours_worked }}h</td>
                            <td class="font-semibold text-success">R$ {{ number_format($work->value, 2, ',', '.') }}</td>
                            <td>
                                @if($work->receipt_path)
                                    <a href="{{ Storage::url($work->receipt_path) }}" target="_blank" class="text-primary text-sm" style="text-decoration: underline;">
                                        📎 Ver
                                    </a>
                                @else
                                    <span class="text-xs text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($works->hasPages())
                <div style="margin-top: 1.5rem; display: flex; justify-content: center;">
                    {{ $works->links() }}
                </div>
            @endif
        @else
            <div style="text-align: center; padding: 3rem;">
                <p class="text-muted" style="font-size: 1.125rem;">Nenhum serviço registrado no período.</p>
                <p class="text-muted text-sm" style="margin-top: 0.5rem;">Comece registrando serviços nas suas ordens.</p>
                <a href="{{ route('provider.orders') }}" class="btn btn-primary" style="margin-top: 1rem;">
                    Ver Ordens de Serviço
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

