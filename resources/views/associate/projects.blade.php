@extends('layouts.bento')

@section('title', 'Meus Projetos')
@section('page-title', 'Meus Projetos')
@section('user-role', 'Associado')

@php
    $routeTenant = request()->route('tenant');
    $routeSlug   = is_string($routeTenant) ? $routeTenant : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);
    $tenantSlug  = $currentTenant?->slug ?? session('tenant_slug') ?? $routeSlug ?? null;
@endphp

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('associate', 'projects', $tenantSlug);
@endphp

@section('content')
<style>
.prog-wrap { background:var(--color-border); border-radius:999px; height:8px; overflow:hidden; }
.prog-bar  { height:100%; border-radius:999px; transition:width .4s ease; }
.prog-green  { background:var(--color-success); }
.prog-yellow { background:var(--color-warning); }
.prog-red    { background:var(--color-danger); }
.proj-card { background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:1.125rem; display:flex; flex-direction:column; gap:.75rem; transition:all .2s; }
.proj-card:hover { border-color:var(--color-primary); box-shadow:0 0 0 2px rgba(16,185,129,.12); }
.limit-pill { display:inline-flex; align-items:center; gap:.3rem; font-size:.65rem; font-weight:600; padding:.15rem .45rem; border-radius:999px; }
.limit-pill.warn   { background:rgba(245,158,11,.15); color:#b45309; }
.limit-pill.danger { background:rgba(239,68,68,.12);  color:#b91c1c; }
</style>

<div class="bento-grid">

    {{-- ── FILTROS ──────────────────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1rem 1.25rem;">
        <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
            <div style="flex:1;min-width:160px;">
                <label class="form-label" style="font-size:.75rem;">Status</label>
                <select name="status" class="form-select" onchange="this.form.submit()" style="font-size:.8125rem;">
                    <option value="">Todos os projetos</option>
                    <option value="active"    {{ request('status') === 'active'    ? 'selected' : '' }}>Ativo</option>
                    <option value="draft"     {{ request('status') === 'draft'     ? 'selected' : '' }}>Rascunho</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Concluído</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                </select>
            </div>
            @if(request('status'))
            <a href="{{ $tenantSlug ? route('associate.projects',['tenant'=>$tenantSlug]) : url('/') }}" class="btn btn-outline" style="font-size:.8rem;padding:.5rem .875rem;">Limpar</a>
            @endif
            <span style="font-size:.8rem;color:var(--color-text-muted);align-self:center;margin-left:auto;">{{ $projects->total() }} projeto(s)</span>
        </form>
    </div>

    {{-- ── GRID DE PROJETOS ─────────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        @if($projects->isEmpty())
            <div style="text-align:center;padding:3rem 1rem;">
                <div style="font-size:2.5rem;margin-bottom:.75rem;">📦</div>
                <p style="color:var(--color-text-muted);font-size:.9375rem;font-weight:500;">Nenhum projeto encontrado.</p>
                <p style="color:var(--color-text-muted);font-size:.8125rem;margin-top:.375rem;">Entre em contato com a cooperativa para participar de projetos.</p>
            </div>
        @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem;">
            @foreach($projects as $project)
            @php
                $ld   = $projectLimitData[$project->id] ?? ['max'=>null,'accumulated'=>0,'remaining'=>null,'percent'=>null,'is_near'=>false,'is_full'=>false];
                $plim = $productLimitData[$project->id] ?? collect();
                $fs   = $financialStateData[$project->id] ?? ['unbilled'=>0,'billed'=>0,'paid'=>0,'total'=>0];
                $pct  = $ld['percent'];
                $bar  = $pct === null ? '' : ($pct >= 100 ? 'prog-red' : ($pct >= 80 ? 'prog-yellow' : 'prog-green'));
                $st   = $project->status->value ?? '';
                $stBadge = $st === 'active' ? 'success' : ($st === 'draft' ? 'warning' : ($st === 'completed' ? 'secondary' : 'danger'));
            @endphp
            <div class="proj-card">
                {{-- Header --}}
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;">
                    <h3 style="font-weight:700;font-size:.9375rem;line-height:1.3;">{{ $project->title }}</h3>
                    <span class="badge badge-{{ $stBadge }}" style="font-size:.65rem;flex-shrink:0;">{{ $project->status->getLabel() }}</span>
                </div>

                {{-- Cliente --}}
                @if($project->customer)
                <div style="font-size:.775rem;color:var(--color-text-muted);">🏢 {{ $project->customer->name }}</div>
                @endif

                {{-- Tipo --}}
                @if($project->type ?? null)
                <div style="font-size:.75rem;color:var(--color-text-muted);">Tipo: <span style="font-weight:600;color:var(--color-text);">{{ $project->type }}</span></div>
                @endif

                {{-- Produtos do projeto --}}
                @if($project->demands && $project->demands->count() > 0)
                <div style="border-top:1px solid var(--color-border);padding-top:.625rem;">
                    <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:600;margin-bottom:.4rem;">PRODUTOS</div>
                    @foreach($project->demands->take(3) as $dem)
                    <div style="display:flex;justify-content:space-between;font-size:.775rem;margin-bottom:.2rem;">
                        <span>{{ $dem->product?->name ?? '—' }}</span>
                        <span style="font-weight:600;">{{ rtrim(rtrim(number_format($dem->target_quantity,3,',','.'), '0'),',') }} {{ $dem->product?->unit ?? '' }}</span>
                    </div>
                    @endforeach
                    @if($project->demands->count() > 3)
                    <div style="font-size:.7rem;color:var(--color-text-muted);">+{{ $project->demands->count()-3 }} mais</div>
                    @endif
                </div>
                @endif

                {{-- Limite financeiro --}}
                @if($ld['max'] !== null)
                <div style="border-top:1px solid var(--color-border);padding-top:.625rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.35rem;">
                        <span style="font-size:.7rem;color:var(--color-text-muted);font-weight:600;">LIMITE FINANCEIRO</span>
                        @if($ld['is_full'])
                            <span class="limit-pill danger">🚫 Atingido</span>
                        @elseif($ld['is_near'])
                            <span class="limit-pill warn">⚠️ {{ number_format($pct,0) }}%</span>
                        @else
                            <span style="font-size:.7rem;font-weight:600;">{{ number_format($pct,0) }}%</span>
                        @endif
                    </div>
                    <div class="prog-wrap" style="margin-bottom:.35rem;"><div class="prog-bar {{ $bar }}" style="width:{{ min($pct,100) }}%;"></div></div>
                    <div style="display:flex;justify-content:space-between;font-size:.7rem;color:var(--color-text-muted);">
                        <span>Utilizado: R$ {{ number_format($ld['accumulated'],2,',','.') }}</span>
                        <span>Limite: R$ {{ number_format($ld['max'],2,',','.') }}</span>
                    </div>
                </div>
                @endif

                {{-- Limites por produto --}}
                @if($plim->isNotEmpty())
                <div style="border-top:1px solid var(--color-border);padding-top:.625rem;">
                    <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:600;margin-bottom:.4rem;">LIMITES POR PRODUTO</div>
                    @foreach($plim as $pl)
                    @php
                        $pp  = $pl->percent_used ?? 0;
                        $pb  = $pp >= 100 ? 'prog-red' : ($pp >= 80 ? 'prog-yellow' : 'prog-green');
                    @endphp
                    <div style="margin-bottom:.5rem;">
                        <div style="display:flex;justify-content:space-between;font-size:.7rem;margin-bottom:.2rem;">
                            <span>{{ $pl->product?->name ?? '—' }}</span>
                            <span style="font-weight:600;color:{{ $pl->is_full ? 'var(--color-danger)' : ($pl->is_near ? 'var(--color-warning)' : 'var(--color-text)') }};">
                                {{ rtrim(rtrim(number_format($pl->delivered_qty,3,',','.'), '0'),',') }} / {{ rtrim(rtrim(number_format($pl->max_quantity,3,',','.'), '0'),',') }}
                            </span>
                        </div>
                        <div class="prog-wrap" style="height:5px;"><div class="prog-bar {{ $pb }}" style="width:{{ min($pp,100) }}%;"></div></div>
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Estados Financeiros das Distribuições --}}
                @if($fs['total'] > 0)
                <div style="border-top:1px solid var(--color-border);padding-top:.625rem;">
                    <div style="font-size:.7rem;color:var(--color-text-muted);font-weight:600;margin-bottom:.4rem;">FINANCEIRO (DISTRIBUIÇÕES)</div>
                    @php
                        $fsT = max($fs['total'], 0.01);
                        $wU = round($fs['unbilled'] / $fsT * 100, 1);
                        $wB = round($fs['billed']   / $fsT * 100, 1);
                        $wP = round($fs['paid']     / $fsT * 100, 1);
                    @endphp
                    <div style="display:flex;height:8px;border-radius:999px;overflow:hidden;background:var(--color-border);margin-bottom:.35rem;">
                        @if($wU > 0)<div style="width:{{ $wU }}%;background:#f59e0b;"></div>@endif
                        @if($wB > 0)<div style="width:{{ $wB }}%;background:#3b82f6;"></div>@endif
                        @if($wP > 0)<div style="width:{{ $wP }}%;background:#10b981;"></div>@endif
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:.68rem;color:var(--color-text-muted);">
                        @if($fs['unbilled'] > 0)<span style="color:#d97706;">🟡 R$ {{ number_format($fs['unbilled'],2,',','.') }}</span>@endif
                        @if($fs['billed'] > 0)<span style="color:#2563eb;">🔵 R$ {{ number_format($fs['billed'],2,',','.') }}</span>@endif
                        @if($fs['paid'] > 0)<span style="color:#059669;">🟢 R$ {{ number_format($fs['paid'],2,',','.') }}</span>@endif
                    </div>
                </div>
                @endif

                {{-- Botão --}}
                <div style="margin-top:auto;padding-top:.5rem;">
                    <a href="{{ $tenantSlug ? route('associate.projects.show',['tenant'=>$tenantSlug,'project'=>$project->id]) : url('/') }}"
                       class="btn btn-primary" style="width:100%;justify-content:center;font-size:.8125rem;">
                        Ver Detalhes
                    </a>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Paginação --}}
        @if($projects->hasPages())
        <div style="margin-top:1.5rem;display:flex;justify-content:center;">
            {{ $projects->withQueryString()->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
@endsection
