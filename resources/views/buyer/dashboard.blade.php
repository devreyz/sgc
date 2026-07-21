@extends('layouts.bento')

@section('title', 'Portal Comprador')
@section('page-title', 'Portal Comprador')
@section('user-role', $organization->short_name ?: $organization->name)

@php
    $routeTenant = request()->route('tenant');
    $tenantSlug = is_object($routeTenant) ? $routeTenant->slug : $routeTenant;
@endphp

@php($bentoNavigation = \App\Support\PortalNavigation::make('buyer', 'dashboard', $tenantSlug))

@section('content')
<style>
.buyer-card-link { display:block; text-decoration:none; color:var(--color-text); }
.buyer-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:.9rem 0; border-bottom:1px solid var(--color-border); }
.buyer-row:last-child { border-bottom:0; }
.buyer-muted { color:var(--color-text-muted); font-size:.875rem; }
</style>

<div class="bento-grid">
    <div class="bento-card col-span-full">
        <div style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
            <div>
                <div class="buyer-muted">Organizacao compradora</div>
                <h1 style="font-size:1.6rem;line-height:1.2;margin-top:.25rem;">{{ $organization->name }}</h1>
            </div>
            <a class="btn btn-primary" href="{{ route('buyer.projects', ['tenant' => $tenantSlug]) }}">Ver projetos</a>
        </div>
    </div>

    <div class="bento-card col-span-4">
        <div class="stat-card">
            <div class="stat-label">Projetos disponiveis</div>
            <div class="stat-value">{{ $projects->count() }}</div>
        </div>
    </div>
    <div class="bento-card col-span-4">
        <div class="stat-card">
            <div class="stat-label">Solicitacoes abertas</div>
            <div class="stat-value">{{ $projects->sum('open_count') }}</div>
        </div>
    </div>
    <div class="bento-card col-span-4">
        <div class="stat-card">
            <div class="stat-label">Solicitacoes enviadas</div>
            <div class="stat-value">{{ $projects->sum('requests_count') }}</div>
        </div>
    </div>

    <div class="bento-card col-span-8">
        <h2 style="font-size:1rem;margin-bottom:1rem;">Projetos em andamento</h2>
        @forelse($projects as $entry)
            <a class="buyer-card-link buyer-row" href="{{ route('buyer.projects.show', ['tenant' => $tenantSlug, 'project' => $entry['project']]) }}">
                <div>
                    <div style="font-weight:700;">{{ $entry['project']->title }}</div>
                    <div class="buyer-muted">{{ $entry['project']->start_date?->format('d/m/Y') }} - {{ $entry['project']->end_date?->format('d/m/Y') }}</div>
                </div>
                <span class="badge badge-info">{{ $entry['requests_count'] }} pedidos</span>
            </a>
        @empty
            <p class="buyer-muted">Nenhum projeto ativo liberado para esta organizacao.</p>
        @endforelse
    </div>

    <div class="bento-card col-span-4">
        <h2 style="font-size:1rem;margin-bottom:1rem;">Ultimas solicitacoes</h2>
        @forelse($recentRequests as $request)
            <a class="buyer-card-link buyer-row" href="{{ route('buyer.requests.show', ['tenant' => $tenantSlug, 'buyerRequest' => $request]) }}">
                <div>
                    <div style="font-weight:700;">Pedido #{{ $request->id }}</div>
                    <div class="buyer-muted">{{ $request->salesProject?->title }}</div>
                </div>
                <span class="badge badge-secondary">{{ $request->statusLabel() }}</span>
            </a>
        @empty
            <p class="buyer-muted">Ainda nao ha solicitacoes.</p>
        @endforelse
    </div>
</div>
@endsection
