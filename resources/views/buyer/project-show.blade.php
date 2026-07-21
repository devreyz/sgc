@extends('layouts.bento')

@section('title', $project->title)
@section('page-title', $project->title)
@section('user-role', $organization->short_name ?: $organization->name)

@php
    $routeTenant = request()->route('tenant');
    $tenantSlug = is_object($routeTenant) ? $routeTenant->slug : $routeTenant;
@endphp

@php($bentoNavigation = \App\Support\PortalNavigation::make('buyer', 'projects', $tenantSlug))

@section('content')
<div class="bento-grid">
    <div class="bento-card col-span-full">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
            <div>
                <div class="text-muted text-sm">Projeto de venda</div>
                <h1 style="font-size:1.45rem;line-height:1.25;">{{ $project->title }}</h1>
                <p class="text-muted text-sm" style="margin-top:.35rem;">{{ $customers->count() }} unidades vinculadas neste projeto</p>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                <a class="btn btn-outline" href="{{ route('buyer.reports.distribution', ['tenant' => $tenantSlug, 'project' => $project]) }}">Relatorio</a>
                <a class="btn btn-primary" href="{{ route('buyer.requests.create', ['tenant' => $tenantSlug, 'project' => $project]) }}">Nova solicitacao</a>
            </div>
        </div>
    </div>

    <div class="bento-card col-span-4">
        <div class="stat-label">Unidades</div>
        <div class="stat-value">{{ $customers->count() }}</div>
    </div>
    <div class="bento-card col-span-4">
        <div class="stat-label">Solicitacoes</div>
        <div class="stat-value">{{ $buyerRequests->count() }}</div>
    </div>
    <div class="bento-card col-span-4">
        <div class="stat-label">Limite por pedido</div>
        <div class="stat-value" style="font-size:1.5rem;">{{ $limitEnabled ? 'Ativo' : 'Aviso' }}</div>
    </div>

    <div class="bento-card col-span-6">
        <h2 style="font-size:1rem;margin-bottom:1rem;">Solicitacoes</h2>
        <div class="table-container">
            <table class="table">
                <thead><tr><th>Pedido</th><th>Unidade</th><th>Status</th><th>Data</th></tr></thead>
                <tbody>
                @forelse($buyerRequests as $request)
                    <tr>
                        <td><a href="{{ route('buyer.requests.show', ['tenant' => $tenantSlug, 'buyerRequest' => $request]) }}">#{{ $request->id }}</a></td>
                        <td>{{ $request->customer?->name ?: 'Varias unidades' }}</td>
                        <td><span class="badge badge-secondary">{{ $request->statusLabel() }}</span></td>
                        <td>{{ $request->created_at?->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Nenhuma solicitacao enviada.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bento-card col-span-6">
        <h2 style="font-size:1rem;margin-bottom:1rem;">Distribuicoes registradas</h2>
        <div class="table-container">
            <table class="table">
                <thead><tr><th>Unidade</th><th>Produto</th><th>Solicitado</th><th>Distribuido</th></tr></thead>
                <tbody>
                @forelse($reportRows as $row)
                    <tr>
                        <td>{{ $row['customer']?->name }}</td>
                        <td>{{ $row['product']?->name }}</td>
                        <td>{{ number_format($row['requested_quantity'], 3, ',', '.') }}</td>
                        <td>{{ number_format($row['distributed_quantity'], 3, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted">Nenhuma distribuicao aprovada ainda.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
