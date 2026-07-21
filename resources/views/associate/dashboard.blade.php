@extends('layouts.bento')

@section('title', 'Meu Painel')
@section('page-title', 'Meu Painel')
@section('user-role', 'Associado')

@php
    $routeTenant = request()->route('tenant');
    $routeSlug   = is_string($routeTenant) ? $routeTenant : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);
    $tenantSlug  = $currentTenant?->slug ?? session('tenant_slug') ?? $routeSlug ?? null;
@endphp

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('associate', 'dashboard', $tenantSlug);
@endphp

@section('content')
<style>
.prog-wrap { background:var(--color-border); border-radius:999px; height:8px; overflow:hidden; }
.prog-bar  { height:100%; border-radius:999px; transition:width .4s ease; }
.prog-green  { background:var(--color-success); }
.prog-yellow { background:var(--color-warning); }
.prog-red    { background:var(--color-danger); }
.alert-banner { border-radius:var(--radius-md); padding:.75rem 1rem; display:flex; align-items:flex-start; gap:.75rem; }
.alert-banner+.alert-banner { margin-top:.5rem; }
.alert-warn   { background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.3); }
.alert-danger { background:rgba(239,68,68,.1);  border:1px solid rgba(239,68,68,.3); }
.proj-mini { border-radius:var(--radius-lg); border:1px solid var(--color-border); background:var(--color-bg); padding:1rem; display:flex; flex-direction:column; gap:.6rem; text-decoration:none; color:var(--color-text); transition:all .2s; }
.proj-mini:hover { border-color:var(--color-primary); box-shadow:0 0 0 2px rgba(16,185,129,.15); transform:translateY(-1px); }
.dl-row { display:flex; justify-content:space-between; align-items:center; padding:.75rem .875rem; border-radius:var(--radius-md); border:1px solid var(--color-border); background:var(--color-bg); }
.dl-row+.dl-row { margin-top:.5rem; }
</style>

<div class="bento-grid">

    {{-- ── STATS 2×2 ───────────────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:1rem;">
            <div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:.5rem;">💰</div>
                <div style="font-size:1.6rem;font-weight:700;color:var(--color-success);line-height:1.1;">R$ {{ number_format($stats['earnings_this_month'],2,',','.') }}</div>
                <div style="font-size:.75rem;color:var(--color-text-muted);font-weight:500;margin-top:.2rem;">Faturado este mes</div>
            </div>
            <div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(99,102,241,.1);display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:.5rem;">💳</div>
                <div style="font-size:1.6rem;font-weight:700;color:var(--color-secondary);line-height:1.1;">R$ {{ number_format($stats['unpaid_value'],2,',','.') }}</div>
                <div style="font-size:.75rem;color:var(--color-text-muted);font-weight:500;margin-top:.2rem;">A receber por comprovantes</div>
            </div>
            <div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(16,185,129,.1);display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:.5rem;">📦</div>
                <div style="font-size:1.6rem;font-weight:700;line-height:1.1;">R$ {{ number_format($stats['paid_this_month'],2,',','.') }}</div>
                <div style="font-size:.75rem;color:var(--color-text-muted);font-weight:500;margin-top:.2rem;">Pago este mes</div>
            </div>
            <div>
                <div style="width:2.25rem;height:2.25rem;border-radius:.625rem;background:rgba(245,158,11,.1);display:flex;align-items:center;justify-content:center;font-size:1.1rem;margin-bottom:.5rem;">⏳</div>
                <div style="font-size:1.6rem;font-weight:700;color:var(--color-warning);line-height:1.1;">R$ {{ number_format($stats['distributed_net'],2,',','.') }}</div>
                <div style="font-size:.75rem;color:var(--color-text-muted);font-weight:500;margin-top:.2rem;">Distribuido aprovado</div>
            </div>
        </div>
    </div>

    {{-- ── ALERTAS DE LIMITE ────────────────────────────────────────────────── --}}
    @if($limitAlerts->isNotEmpty())
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.75rem;">
            <span>⚠️</span>
            <span style="font-weight:600;font-size:.875rem;">Atenção — Limites de faturamento</span>
        </div>
        @foreach($recentProjects as $p)
            @if(isset($projectLimitData[$p->id]) && ($projectLimitData[$p->id]['is_near'] || $projectLimitData[$p->id]['is_full']))
            @php $ld = $projectLimitData[$p->id]; @endphp
            <div class="alert-banner {{ $ld['is_full'] ? 'alert-danger' : 'alert-warn' }}">
                <span>{{ $ld['is_full'] ? '🚫' : '⚠️' }}</span>
                <div style="flex:1;font-size:.8125rem;">
                    <strong>{{ $p->title }}</strong>
                    @if($ld['is_full'])
                        — Limite atingido (R$ {{ number_format($ld['max'],2,',','.') }})
                    @else
                        — {{ number_format($ld['percent'],0) }}% utilizado. Restam R$ {{ number_format($ld['remaining'],2,',','.') }}
                    @endif
                </div>
            </div>
            @endif
        @endforeach
    </div>
    @endif

    {{-- ── PROJETOS ATIVOS ─────────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h2 style="font-weight:700;font-size:1rem;">Projetos em Andamento</h2>
            <a href="{{ $tenantSlug ? route('associate.projects',['tenant'=>$tenantSlug]) : url('/') }}"
               style="font-size:.8rem;color:var(--color-primary);text-decoration:none;font-weight:500;">Ver todos →</a>
        </div>

        @if($recentProjects->isEmpty())
            <p style="color:var(--color-text-muted);font-size:.875rem;text-align:center;padding:2rem 0;">Nenhum projeto ativo no momento.</p>
        @else
        <div style="display:flex;flex-direction:column;gap:.625rem;">
            @foreach($recentProjects as $project)
            @php
                $ld  = $projectLimitData[$project->id] ?? ['max'=>null,'accumulated'=>0,'remaining'=>null,'percent'=>null,'is_near'=>false,'is_full'=>false];
                $pct = $ld['percent'];
                $bar = $pct === null ? '' : ($pct >= 100 ? 'prog-red' : ($pct >= 80 ? 'prog-yellow' : 'prog-green'));
            @endphp
            <a href="{{ $tenantSlug ? route('associate.projects.show',['tenant'=>$tenantSlug,'project'=>$project->id]) : url('/') }}" class="proj-mini">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                    <span style="font-weight:600;font-size:.875rem;line-height:1.3;">{{ $project->title }}</span>
                    <span class="badge badge-{{ $project->status->value === 'active' ? 'success' : 'warning' }}" style="white-space:nowrap;flex-shrink:0;font-size:.65rem;">
                        {{ $project->status->getLabel() }}
                    </span>
                </div>
                @if($project->customer)
                <span style="font-size:.725rem;color:var(--color-text-muted);">{{ $project->customer->name }}</span>
                @endif
                @if($ld['max'] !== null)
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:.7rem;margin-bottom:.3rem;color:var(--color-text-muted);">
                        <span>R$ {{ number_format($ld['accumulated'],2,',','.') }} / R$ {{ number_format($ld['max'],2,',','.') }}</span>
                        <span style="font-weight:600;color:{{ $ld['is_full'] ? 'var(--color-danger)' : ($ld['is_near'] ? 'var(--color-warning)' : 'var(--color-text)') }};">
                            {{ number_format($pct,0) }}%
                        </span>
                    </div>
                    <div class="prog-wrap"><div class="prog-bar {{ $bar }}" style="width:{{ min($pct,100) }}%;"></div></div>
                </div>
                @endif
            </a>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ── ÚLTIMAS ENTREGAS ────────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
            <h2 style="font-weight:700;font-size:1rem;">Últimas Entregas</h2>
            <a href="{{ $tenantSlug ? route('associate.deliveries',['tenant'=>$tenantSlug]) : url('/') }}"
               style="font-size:.8rem;color:var(--color-primary);text-decoration:none;font-weight:500;">Ver todas →</a>
        </div>

        @if($recentDeliveries->isEmpty())
            <p style="color:var(--color-text-muted);font-size:.875rem;text-align:center;padding:1.5rem 0;">Nenhuma entrega registrada ainda.</p>
        @else
        <div style="display:flex;flex-direction:column;gap:.5rem;">
            @foreach($recentDeliveries as $dl)
            <div class="dl-row">
                <div style="min-width:0;">
                    <div style="font-weight:600;font-size:.8125rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ $dl->product?->name ?? 'Produto' }}
                    </div>
                    <div style="font-size:.7rem;color:var(--color-text-muted);">
                        {{ $dl->delivery_date?->format('d/m/Y') }} · {{ Str::limit($dl->salesProject?->title ?? '—', 30) }}
                    </div>
                </div>
                <div style="text-align:right;flex-shrink:0;margin-left:1rem;">
                    <div style="font-weight:700;font-size:.875rem;color:var(--color-success);">
                        R$ {{ number_format((float)$dl->quantity * (float)$dl->unit_price, 2, ',', '.') }}
                    </div>
                    <span class="badge badge-{{ $dl->status->value === 'approved' ? 'success' : ($dl->status->value === 'cancelled' ? 'danger' : 'warning') }}" style="font-size:.65rem;">
                        {{ $dl->status->getLabel() }}
                    </span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ── AÇÕES RÁPIDAS ───────────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <h2 style="font-weight:700;font-size:1rem;margin-bottom:1rem;">Ações Rápidas</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
            <a href="{{ $tenantSlug ? route('associate.projects',   ['tenant'=>$tenantSlug]) : url('/') }}" class="btn btn-primary"   style="flex-direction:column;gap:.2rem;padding:.875rem .5rem;font-size:.8rem;"><span>📦</span>Projetos</a>
            <a href="{{ $tenantSlug ? route('associate.deliveries', ['tenant'=>$tenantSlug]) : url('/') }}" class="btn btn-secondary" style="flex-direction:column;gap:.2rem;padding:.875rem .5rem;font-size:.8rem;"><span>🚚</span>Entregas</a>
            <a href="{{ $tenantSlug ? route('associate.ledger',     ['tenant'=>$tenantSlug]) : url('/') }}" class="btn btn-outline"   style="flex-direction:column;gap:.2rem;padding:.875rem .5rem;font-size:.8rem;"><span>💳</span>Extrato</a>
            <a href="{{ $tenantSlug ? route('associate.deliveries', ['tenant'=>$tenantSlug,'status'=>'pending']) : url('/') }}" class="btn btn-outline" style="flex-direction:column;gap:.2rem;padding:.875rem .5rem;font-size:.8rem;"><span>⏳</span>Pendentes</a>
        </div>
    </div>

</div>
@endsection

