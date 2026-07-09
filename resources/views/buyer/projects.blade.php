@extends('layouts.bento')

@section('title', 'Projetos')
@section('page-title', 'Projetos')
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
        <h1 style="font-size:1.4rem;margin-bottom:.25rem;">Projetos liberados</h1>
        <p class="text-muted">Apenas projetos ativos em que sua organizacao participa aparecem aqui.</p>
    </div>

    @forelse($projects as $project)
        <a class="bento-card col-span-4" style="text-decoration:none;color:var(--color-text);" href="{{ route('buyer.projects.show', ['tenant' => $tenantSlug, 'project' => $project]) }}">
            <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;">
                <div>
                    <h2 style="font-size:1.05rem;line-height:1.25;">{{ $project->title }}</h2>
                    <p class="text-muted text-sm" style="margin-top:.5rem;">{{ $project->code }}</p>
                </div>
                <span class="badge badge-success">Ativo</span>
            </div>
            <div style="margin-top:1.2rem;display:grid;gap:.35rem;font-size:.875rem;">
                <span>Inicio: {{ $project->start_date?->format('d/m/Y') ?: '-' }}</span>
                <span>Fim: {{ $project->end_date?->format('d/m/Y') ?: '-' }}</span>
                <span>{{ $project->buyer_requests_count }} solicitacoes</span>
            </div>
        </a>
    @empty
        <div class="bento-card col-span-full">
            <p class="text-muted">Nenhum projeto ativo liberado para esta organizacao.</p>
        </div>
    @endforelse

    <div class="col-span-full">
        {{ $projects->links() }}
    </div>
</div>
@endsection
