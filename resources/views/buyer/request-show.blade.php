@extends('layouts.bento')

@section('title', 'Solicitacao #'.$buyerRequest->id)
@section('page-title', 'Solicitacao #'.$buyerRequest->id)
@section('user-role', $organization->short_name ?: $organization->name)

@php
    $routeTenant = request()->route('tenant');
    $tenantSlug = is_object($routeTenant) ? $routeTenant->slug : $routeTenant;
@endphp

@php($bentoNavigation = \App\Support\PortalNavigation::make('buyer', 'projects', $tenantSlug))

@section('content')
<div class="bento-grid">
    <div class="bento-card col-span-full">
        <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <div>
                <div class="text-muted text-sm">{{ $buyerRequest->salesProject?->title }}</div>
                <h1 style="font-size:1.35rem;">Solicitacao #{{ $buyerRequest->id }}</h1>
            </div>
            <span class="badge badge-secondary">{{ $buyerRequest->statusLabel() }}</span>
        </div>
    </div>

    <div class="bento-card col-span-4"><div class="stat-label">Solicitado</div><div class="stat-value">{{ number_format($summary['requested'], 3, ',', '.') }}</div></div>
    <div class="bento-card col-span-4"><div class="stat-label">Distribuido</div><div class="stat-value">{{ number_format($summary['distributed'], 3, ',', '.') }}</div></div>
    <div class="bento-card col-span-4"><div class="stat-label">Pendente</div><div class="stat-value">{{ number_format($summary['pending'], 3, ',', '.') }}</div></div>

    <div class="bento-card col-span-full">
        <div class="table-container">
            <table class="table">
                <thead><tr><th>Unidade</th><th>Produto</th><th>Solicitado</th><th>Distribuido</th><th>Pendente</th><th>Excedente</th></tr></thead>
                <tbody>
                @foreach($summary['items'] as $row)
                    <tr>
                        <td>{{ $row['customer']?->name ?: '-' }}</td>
                        <td>{{ $row['product']?->name }}</td>
                        <td>{{ number_format($row['requested_quantity'], 3, ',', '.') }}</td>
                        <td>{{ number_format($row['distributed_quantity'], 3, ',', '.') }}</td>
                        <td>{{ number_format($row['pending_quantity'], 3, ',', '.') }}</td>
                        <td>{{ number_format($row['exceeded_quantity'], 3, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
