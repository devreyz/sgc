@extends('layouts.bento')

@section('title', 'Relatorio de Distribuicao')
@section('page-title', 'Relatorio')
@section('user-role', $organization->short_name ?: $organization->name)

@php
    $routeTenant = request()->route('tenant');
    $tenantSlug = is_object($routeTenant) ? $routeTenant->slug : $routeTenant;
@endphp

@section('navigation')
<x-portal.nav portal="buyer" active="projects" :tenant="$tenantSlug" />
@endsection

@section('content')
<div class="bento-grid">
    <div class="bento-card col-span-full">
        <h1 style="font-size:1.35rem;">Relatorio de Distribuicao</h1>
        <p class="text-muted text-sm">{{ $project->title }}</p>
    </div>

    <div class="bento-card col-span-full">
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr><th>Unidade</th><th>Produto</th><th>Solicitado</th><th>Distribuido</th><th>Saldo</th><th>Excedente</th><th>Valor unit.</th><th>Total</th></tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['customer']?->name }}</td>
                        <td>{{ $row['product']?->name }}</td>
                        <td>{{ number_format($row['requested_quantity'], 3, ',', '.') }}</td>
                        <td>{{ number_format($row['distributed_quantity'], 3, ',', '.') }}</td>
                        <td>{{ number_format($row['pending_quantity'], 3, ',', '.') }}</td>
                        <td>{{ number_format($row['exceeded_quantity'], 3, ',', '.') }}</td>
                        <td>R$ {{ number_format($row['unit_price'], 2, ',', '.') }}</td>
                        <td>R$ {{ number_format($row['total_value'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">Nenhuma distribuicao aprovada para este projeto.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                    <tr><th colspan="7">Total geral</th><th>R$ {{ number_format($total, 2, ',', '.') }}</th></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
