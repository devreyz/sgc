@extends('layouts.bento')

@section('title', 'Detalhes do Projeto')
@section('page-title', $project->title ?? 'Projeto')
@section('user-role', 'Associado')

@php
    $routeTenant = request()->route('tenant');
    $routeSlug   = is_string($routeTenant) ? $routeTenant : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);
    $tenantSlug  = $currentTenant?->slug ?? session('tenant_slug') ?? $routeSlug ?? null;
@endphp

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ $tenantSlug ? route('associate.dashboard',  ['tenant'=>$tenantSlug]) : url('/') }}" class="nav-tab">Dashboard</a>
    <a href="{{ $tenantSlug ? route('associate.projects',   ['tenant'=>$tenantSlug]) : url('/') }}" class="nav-tab active">Projetos</a>
    <a href="{{ $tenantSlug ? route('associate.deliveries', ['tenant'=>$tenantSlug]) : url('/') }}" class="nav-tab">Entregas</a>
    <a href="{{ $tenantSlug ? route('associate.ledger',     ['tenant'=>$tenantSlug]) : url('/') }}" class="nav-tab">Extrato</a>
    <form action="{{ route('logout') }}" method="POST" style="display:inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background:none;cursor:pointer;border:none;font-family:inherit;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<style>
.prog-wrap { background:var(--color-border); border-radius:999px; height:10px; overflow:hidden; }
.prog-bar  { height:100%; border-radius:999px; transition:width .4s ease; }
.prog-sm   { height:7px !important; }
.prog-green  { background:var(--color-success); }
.prog-yellow { background:var(--color-warning); }
.prog-red    { background:var(--color-danger); }
.alert-banner { border-radius:var(--radius-md); padding:.875rem 1rem; display:flex; align-items:flex-start; gap:.75rem; margin-bottom:.5rem; }
.alert-warn   { background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.3); }
.alert-danger { background:rgba(239,68,68,.1);  border:1px solid rgba(239,68,68,.3); }
.big-num { font-size:1.6rem; font-weight:700; line-height:1.1; }
.num-lbl { font-size:.7rem; color:var(--color-text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:.2rem; }
</style>

<div class="bento-grid">

    {{-- ── VOLTAR + CABEÇALHO ───────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1rem 1.25rem;">
        <a href="{{ $tenantSlug ? route('associate.projects',['tenant'=>$tenantSlug]) : url('/') }}"
           style="font-size:.8125rem;color:var(--color-primary);text-decoration:none;font-weight:500;display:inline-flex;align-items:center;gap:.4rem;margin-bottom:.875rem;">
            ← Voltar para Projetos
        </a>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;flex-wrap:wrap;">
            <div>
                <h1 style="font-weight:700;font-size:1.25rem;line-height:1.3;">{{ $project->title }}</h1>
                @if($project->customer)
                <div style="font-size:.8125rem;color:var(--color-text-muted);margin-top:.2rem;">🏢 {{ $project->customer->name }}</div>
                @endif
            </div>
            <span class="badge badge-{{ $project->status->value === 'active' ? 'success' : ($project->status->value === 'draft' ? 'warning' : 'secondary') }}" style="font-size:.75rem;">
                {{ $project->status->getLabel() }}
            </span>
        </div>
    </div>

    {{-- ── ALERTAS ─────────────────────────────────────────────────────────── --}}
    @if($financialLimit['is_full'] || $financialLimit['is_near'] || $productLimits->where('is_full',true)->count() || $productLimits->where('is_near',true)->count())
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        @if($financialLimit['is_full'])
        <div class="alert-banner alert-danger">
            <span>🚫</span>
            <div style="font-size:.8125rem;"><strong>Limite de faturamento atingido.</strong> Nenhuma nova entrega pode ser registrada neste projeto.</div>
        </div>
        @elseif($financialLimit['is_near'])
        <div class="alert-banner alert-warn">
            <span>⚠️</span>
            <div style="font-size:.8125rem;"><strong>Atenção:</strong> Você utilizou {{ number_format($financialLimit['percent'],0) }}% do seu limite de faturamento. Restam R$ {{ number_format($financialLimit['remaining'],2,',','.') }}.</div>
        </div>
        @endif
        @foreach($productLimits->filter(fn($pl) => $pl->is_full || $pl->is_near) as $pl)
        <div class="alert-banner {{ $pl->is_full ? 'alert-danger' : 'alert-warn' }}">
            <span>{{ $pl->is_full ? '🚫' : '⚠️' }}</span>
            <div style="font-size:.8125rem;">
                @if($pl->is_full)
                <strong>{{ $pl->product?->name }}:</strong> Limite de quantidade atingido.
                @else
                <strong>{{ $pl->product?->name }}:</strong> {{ number_format($pl->percent_used,0) }}% utilizado — restam {{ rtrim(rtrim(number_format($pl->remaining_qty,3,',','.'), '0'),',') }} {{ $pl->product?->unit ?? '' }}.
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- ── LIMITE FINANCEIRO ────────────────────────────────────────────────── --}}
    @if($financialLimit['max'] !== null)
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <h2 style="font-weight:700;font-size:.9375rem;margin-bottom:1rem;">Meu Limite Financeiro</h2>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:1rem;">
            <div>
                <div class="num-lbl">Limite Total</div>
                <div class="big-num">R$ {{ number_format($financialLimit['max'],2,',','.') }}</div>
            </div>
            <div>
                <div class="num-lbl">Faturado</div>
                <div class="big-num" style="color:var(--color-{{ $financialLimit['is_full'] ? 'danger' : ($financialLimit['is_near'] ? 'warning' : 'success') }});">
                    R$ {{ number_format($financialLimit['accumulated'],2,',','.') }}
                </div>
            </div>
            <div>
                <div class="num-lbl">Disponível</div>
                <div class="big-num" style="color:var(--color-{{ $financialLimit['is_full'] ? 'danger' : 'text' }});">
                    R$ {{ number_format($financialLimit['remaining'],2,',','.') }}
                </div>
            </div>
        </div>
        @php
            $fp  = $financialLimit['percent'] ?? 0;
            $fb  = $fp >= 100 ? 'prog-red' : ($fp >= 80 ? 'prog-yellow' : 'prog-green');
        @endphp
        <div class="prog-wrap">
            <div class="prog-bar {{ $fb }}" style="width:{{ min($fp,100) }}%;"></div>
        </div>
        <div style="text-align:right;font-size:.75rem;font-weight:700;color:var(--color-{{ $financialLimit['is_full'] ? 'danger' : ($financialLimit['is_near'] ? 'warning' : 'text') }});margin-top:.35rem;">
            {{ number_format($fp,1) }}% utilizado
        </div>
    </div>
    @endif

    {{-- ── LIMITES POR PRODUTO ─────────────────────────────────────────────── --}}
    @if($productLimits->isNotEmpty())
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <h2 style="font-weight:700;font-size:.9375rem;margin-bottom:1rem;">Limites por Produto</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.875rem;">
            @foreach($productLimits as $pl)
            @php
                $pp = $pl->percent_used ?? 0;
                $pb = $pp >= 100 ? 'prog-red' : ($pp >= 80 ? 'prog-yellow' : 'prog-green');
            @endphp
            <div style="background:var(--color-bg);border:1px solid {{ $pl->is_full ? 'var(--color-danger)' : ($pl->is_near ? 'var(--color-warning)' : 'var(--color-border)') }};border-radius:var(--radius-md);padding:.875rem;">
                <div style="font-weight:600;font-size:.875rem;margin-bottom:.625rem;">{{ $pl->product?->name ?? '—' }}</div>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:.625rem;font-size:.75rem;">
                    <div>
                        <div style="color:var(--color-text-muted);margin-bottom:.15rem;">Limite</div>
                        <div style="font-weight:700;">{{ rtrim(rtrim(number_format($pl->max_quantity,3,',','.'), '0'),',') }}</div>
                    </div>
                    <div>
                        <div style="color:var(--color-text-muted);margin-bottom:.15rem;">Entregue</div>
                        <div style="font-weight:700;color:var(--color-{{ $pl->is_full ? 'danger' : ($pl->is_near ? 'warning' : 'success') }});">{{ rtrim(rtrim(number_format($pl->delivered_qty,3,',','.'), '0'),',') }}</div>
                    </div>
                    <div>
                        <div style="color:var(--color-text-muted);margin-bottom:.15rem;">Restante</div>
                        <div style="font-weight:700;">{{ rtrim(rtrim(number_format($pl->remaining_qty,3,',','.'), '0'),',') }}</div>
                    </div>
                </div>
                <div class="prog-wrap prog-sm">
                    <div class="prog-bar {{ $pb }}" style="width:{{ min($pp,100) }}%;"></div>
                </div>
                <div style="text-align:right;font-size:.7rem;font-weight:700;margin-top:.25rem;color:var(--color-{{ $pl->is_full ? 'danger' : ($pl->is_near ? 'warning' : 'text') }});">
                    {{ number_format($pp,0) }}%
                    {{ $pl->product?->unit ? '· '.$pl->product->unit : '' }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ── INFORMAÇÕES DO PROJETO ───────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <h2 style="font-weight:700;font-size:.9375rem;margin-bottom:.875rem;">Sobre o Projeto</h2>
        @if($project->demands && $project->demands->count() > 0)
        <div style="border-top:1px solid var(--color-border);padding-top:.875rem;">
            <div style="font-size:.75rem;color:var(--color-text-muted);font-weight:600;margin-bottom:.5rem;">PRODUTOS DEMANDADOS</div>
            @foreach($project->demands as $dem)
            <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid var(--color-border);">
                <span style="font-size:.875rem;">{{ $dem->product?->name ?? '—' }}</span>
                <div style="text-align:right;">
                    <span style="font-weight:700;font-size:.875rem;">{{ rtrim(rtrim(number_format($dem->target_quantity,3,',','.'), '0'),',') }}</span>
                    <span style="font-size:.75rem;color:var(--color-text-muted);"> {{ $dem->product?->unit ?? '' }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
        <div style="margin-top:.875rem;display:grid;grid-template-columns:1fr 1fr;gap:.75rem;font-size:.8125rem;">
            <div>
                <div style="color:var(--color-text-muted);font-size:.7rem;font-weight:600;margin-bottom:.2rem;">MINHAS ENTREGAS</div>
                <div style="font-weight:700;">{{ number_format($myTotalQty,3,',','.') }} un.</div>
            </div>
            @if($financialLimit['max'] === null)
            <div>
                <div style="color:var(--color-text-muted);font-size:.7rem;font-weight:600;margin-bottom:.2rem;">FATURADO</div>
                <div style="font-weight:700;color:var(--color-success);">R$ {{ number_format($financialLimit['accumulated'],2,',','.') }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- ── HISTÓRICO DE ENTREGAS ────────────────────────────────────────────── --}}
    <div class="bento-card col-span-full" style="padding:1.25rem;">
        <h2 style="font-weight:700;font-size:.9375rem;margin-bottom:.875rem;">Meu Histórico de Entregas</h2>

        {{-- Filtros --}}
        <form method="GET" style="display:flex;gap:.625rem;flex-wrap:wrap;margin-bottom:1rem;">
            <select name="status" class="form-select" onchange="this.form.submit()" style="font-size:.775rem;min-width:130px;">
                <option value="">Todos status</option>
                <option value="pending"   {{ request('status') === 'pending'   ? 'selected' : '' }}>Pendente</option>
                <option value="approved"  {{ request('status') === 'approved'  ? 'selected' : '' }}>Aprovado</option>
                <option value="rejected"  {{ request('status') === 'rejected'  ? 'selected' : '' }}>Rejeitado</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
            </select>
            <input type="date" name="start_date" class="form-input" value="{{ request('start_date') }}" style="font-size:.775rem;min-width:130px;" onchange="this.form.submit()">
            <input type="date" name="end_date"   class="form-input" value="{{ request('end_date') }}"   style="font-size:.775rem;min-width:130px;" onchange="this.form.submit()">
            @if(request()->hasAny(['status','start_date','end_date','product_id']))
            <a href="{{ $tenantSlug ? route('associate.projects.show',['tenant'=>$tenantSlug,'project'=>$project->id]) : url('/') }}" class="btn btn-outline" style="font-size:.775rem;padding:.45rem .75rem;">Limpar</a>
            @endif
        </form>

        @if($myDeliveries->isEmpty())
            <p style="color:var(--color-text-muted);font-size:.875rem;text-align:center;padding:1.5rem 0;">Nenhuma entrega encontrada.</p>
        @else
        {{-- Tabela (scrollável horizontalmente) --}}
        <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
            <table style="width:100%;border-collapse:collapse;font-size:.8rem;min-width:520px;">
                <thead>
                    <tr style="border-bottom:2px solid var(--color-border);text-align:left;">
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);">DATA</th>
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);">PRODUTO</th>
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);text-align:right;">QTD</th>
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);text-align:right;">VALOR</th>
                        <th style="padding:.5rem .625rem;font-size:.7rem;font-weight:600;color:var(--color-text-muted);">STATUS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($myDeliveries as $dl)
                    <tr style="border-bottom:1px solid var(--color-border);">
                        <td style="padding:.625rem .625rem;white-space:nowrap;">{{ $dl->delivery_date?->format('d/m/Y') ?? '—' }}</td>
                        <td style="padding:.625rem .625rem;">{{ $dl->product?->name ?? ($dl->projectDemand?->product?->name ?? '—') }}</td>
                        <td style="padding:.625rem .625rem;text-align:right;font-weight:600;">{{ rtrim(rtrim(number_format((float)$dl->quantity,3,',','.'), '0'),',') }}</td>
                        <td style="padding:.625rem .625rem;text-align:right;font-weight:700;color:var(--color-success);">
                            R$ {{ number_format((float)$dl->quantity * (float)$dl->unit_price, 2,',','.') }}
                        </td>
                        <td style="padding:.625rem .625rem;">
                            <span class="badge badge-{{ $dl->status->value === 'approved' ? 'success' : ($dl->status->value === 'cancelled' ? 'danger' : ($dl->status->value === 'rejected' ? 'danger' : 'warning')) }}" style="font-size:.65rem;">
                                {{ $dl->status->getLabel() }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($myDeliveries->hasPages())
        <div style="margin-top:1.25rem;display:flex;justify-content:center;">
            {{ $myDeliveries->withQueryString()->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
@endsection