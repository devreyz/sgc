@extends('layouts.bento')

@section('title', 'Projetos')
@section('page-title', 'Projetos')
@section('user-role', 'Registrador')

@section('navigation')
<x-portal.nav portal="delivery" active="projects" :tenant="$tenant->slug ?? request()->route('tenant')" />
@endsection

@section('content')
<style>
/* ── Page header ── */
.pl-header {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    padding: 1.2rem 1.5rem;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.pl-title { font-size:1.2rem; font-weight:700; margin:0 0 .3rem; display:flex; align-items:center; gap:.45rem; }
.pl-meta  { font-size:.82rem; color:var(--color-text-secondary); }

/* ── Filter bar ── */
.filter-bar {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    align-items: center;
    margin-bottom: 1rem;
}
.filter-btn { display:inline-flex; align-items:center; gap:.3rem; padding:.35rem .75rem; border-radius:var(--radius-md); border:1px solid var(--color-border); cursor:pointer; font-size:.78rem; font-weight:600; background:var(--color-surface); color:var(--color-text-secondary); text-decoration:none; transition:.15s; }
.filter-btn:hover, .filter-btn.active { background:var(--color-primary); color:#fff; border-color:var(--color-primary); }

/* ── Project grid ── */
.proj-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:1rem; }
.proj-card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: box-shadow .15s, transform .15s;
}
.proj-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.1); transform: translateY(-1px); }
.proj-card-header {
    padding: .85rem 1rem .7rem;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .5rem;
}
.proj-name { font-size:.92rem; font-weight:700; color:var(--color-text); line-height:1.3; }
.proj-customer { font-size:.76rem; color:var(--color-text-secondary); margin-top:.2rem; }
.proj-body { padding: .75rem 1rem; flex:1; }
.proj-meta { display:flex; flex-wrap:wrap; gap:.4rem .75rem; font-size:.76rem; color:var(--color-text-secondary); margin-bottom:.75rem; }
.proj-meta span { display:inline-flex; align-items:center; gap:.25rem; }
.proj-stats { display:grid; grid-template-columns:1fr 1fr 1fr; gap:.4rem; margin-bottom:.8rem; }
.proj-stat { background:var(--color-bg); border-radius:var(--radius-sm); padding:.45rem .5rem; text-align:center; }
.proj-stat-val { font-size:.95rem; font-weight:700; color:var(--color-text); line-height:1; }
.proj-stat-lbl { font-size:.65rem; color:var(--color-text-secondary); text-transform:uppercase; letter-spacing:.03em; margin-top:.2rem; }
.proj-actions { padding: .7rem 1rem; border-top:1px solid var(--color-border); display:flex; gap:.4rem; flex-wrap:wrap; }
.btn { display:inline-flex; align-items:center; gap:.3rem; padding:.38rem .75rem; border-radius:var(--radius-md); border:none; cursor:pointer; font-size:.76rem; font-weight:600; text-decoration:none; transition:.15s; white-space:nowrap; }
.btn:hover { transform:translateY(-1px); }
.btn-primary  { background:var(--color-primary); color:#fff; }
.btn-primary:hover { opacity:.88; }
.btn-success  { background:var(--color-success); color:#fff; }
.btn-success:hover { opacity:.88; }
.btn-ghost { background:transparent; color:var(--color-text-secondary); border:1px solid var(--color-border); }
.btn-ghost:hover { background:var(--color-bg); color:var(--color-text); }
.btn-warning { background:#f59e0b; color:#fff; }
.btn-warning:hover { opacity:.88; }

/* ── Status badge ── */
.status-badge { display:inline-flex; align-items:center; gap:.25rem; padding:.22rem .6rem; border-radius:99px; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; white-space:nowrap; }
.status-active      { background:#dcfce7; color:#16a34a; }
.status-draft       { background:#f3f4f6; color:#6b7280; }
.status-awaiting    { background:#fef9c3; color:#a16207; }
.status-delivered   { background:#dbeafe; color:#1d4ed8; }
.status-completed   { background:#f0fdf4; color:#15803d; }
.status-cancelled   { background:#fee2e2; color:#dc2626; }
.status-other       { background:#f3f4f6; color:#6b7280; }

/* ── Empty ── */
.empty-msg { text-align:center; padding:3rem 1rem; color:var(--color-text-secondary); font-size:.9rem; }

/* ── Progress bar ── */
.prog-bar-track { height:5px; background:var(--color-border); border-radius:3px; overflow:hidden; margin-bottom:.6rem; }
.prog-bar-fill  { height:100%; border-radius:3px; transition:width .3s; }
</style>

{{-- HEADER --}}
<div class="pl-header">
    <div>
        <h1 class="pl-title">
            <i data-lucide="folder-open" style="width:20px;height:20px;color:var(--color-primary)"></i>
            Todos os Projetos
        </h1>
        <p class="pl-meta">{{ $projects->total() }} projeto(s) encontrado(s)</p>
    </div>
    <div style="font-size:.8rem;color:var(--color-text-secondary);text-align:right;line-height:1.6;">
        <strong>{{ $tenant->name }}</strong><br>
        Somente leitura para projetos finalizados
    </div>
</div>

{{-- FILTROS --}}
<div class="filter-bar">
    <a href="{{ request()->fullUrlWithQuery(['status' => null]) }}" class="filter-btn {{ !request('status') ? 'active' : '' }}">Todos</a>
    <a href="{{ request()->fullUrlWithQuery(['status' => 'active']) }}" class="filter-btn {{ request('status') === 'active' ? 'active' : '' }}">Em execução</a>
    <a href="{{ request()->fullUrlWithQuery(['status' => 'awaiting_delivery']) }}" class="filter-btn {{ request('status') === 'awaiting_delivery' ? 'active' : '' }}">Aguardando entrega</a>
    <a href="{{ request()->fullUrlWithQuery(['status' => 'delivered']) }}" class="filter-btn {{ request('status') === 'delivered' ? 'active' : '' }}">Entregue</a>
    <a href="{{ request()->fullUrlWithQuery(['status' => 'completed']) }}" class="filter-btn {{ request('status') === 'completed' ? 'active' : '' }}">Concluído</a>
    <a href="{{ request()->fullUrlWithQuery(['status' => 'draft']) }}" class="filter-btn {{ request('status') === 'draft' ? 'active' : '' }}">Rascunho</a>

    {{-- Campo de busca --}}
    <form method="GET" action="" style="display:flex;gap:.4rem;margin-left:auto;">
        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        <input type="text" name="search" value="{{ request('search') }}"
            placeholder="Buscar projeto..."
            style="padding:.35rem .7rem;border:1px solid var(--color-border);border-radius:var(--radius-md);font-size:.8rem;background:var(--color-bg);color:var(--color-text);min-width:180px;">
        <button type="submit" class="btn btn-ghost" style="padding:.35rem .65rem;">
            <i data-lucide="search" style="width:13px;height:13px"></i>
        </button>
    </form>
</div>

{{-- GRID DE PROJETOS --}}
@if($projects->isEmpty())
    <div class="empty-msg">
        <i data-lucide="folder-x" style="width:40px;height:40px;opacity:.3;margin-bottom:.75rem;display:block;margin-inline:auto;"></i>
        Nenhum projeto encontrado com esses filtros.
    </div>
@else
<div class="proj-grid">
    @foreach($projects as $proj)
    @php
        $statusClass = match($proj->status->value) {
            'active'            => 'status-active',
            'draft'             => 'status-draft',
            'awaiting_delivery' => 'status-awaiting',
            'delivered'         => 'status-delivered',
            'completed'         => 'status-completed',
            'cancelled'         => 'status-cancelled',
            default             => 'status-other',
        };
        $isEditable = in_array($proj->status->value, ['active', 'draft', 'awaiting_delivery']);
        $approvedDists = $proj->deliveries_approved_count;
        $netTotal = $proj->net_total;
        $progressPct = $proj->progress_percentage ?? 0;
        $progressColor = $progressPct >= 100 ? '#16a34a' : ($progressPct >= 50 ? '#f59e0b' : '#ef4444');
    @endphp
    <div class="proj-card">
        <div class="proj-card-header">
            <div style="flex:1;min-width:0;">
                <div class="proj-name">{{ $proj->title }}</div>
                <div class="proj-customer">
                    <i data-lucide="building-2" style="width:11px;height:11px"></i>
                    {{ $proj->customer->name ?? '—' }}
                </div>
            </div>
            <span class="status-badge {{ $statusClass }}">{{ $proj->status->getLabel() }}</span>
        </div>

        <div class="proj-body">
            <div class="proj-meta">
                @if($proj->reference_year)
                    <span><i data-lucide="calendar" style="width:11px;height:11px"></i> {{ $proj->reference_year }}</span>
                @endif
                @if($proj->start_date)
                    <span><i data-lucide="play" style="width:11px;height:11px"></i> {{ $proj->start_date->format('d/m/Y') }}</span>
                @endif
                @if($proj->end_date)
                    <span><i data-lucide="stop-circle" style="width:11px;height:11px"></i> {{ $proj->end_date->format('d/m/Y') }}</span>
                @endif
                @if($proj->contract_number)
                    <span><i data-lucide="file-text" style="width:11px;height:11px"></i> {{ $proj->contract_number }}</span>
                @endif
            </div>

            {{-- Barra de progresso --}}
            @if($progressPct > 0)
            <div class="prog-bar-track">
                <div class="prog-bar-fill" style="width:{{ min(100, $progressPct) }}%;background:{{ $progressColor }};"></div>
            </div>
            @endif

            <div class="proj-stats">
                <div class="proj-stat">
                    <div class="proj-stat-val" style="color:var(--color-success)">{{ number_format($approvedDists) }}</div>
                    <div class="proj-stat-lbl">Distribuições</div>
                </div>
                <div class="proj-stat">
                    <div class="proj-stat-val" style="color:var(--color-primary)">{{ number_format($progressPct, 0) }}%</div>
                    <div class="proj-stat-lbl">Progresso</div>
                </div>
                <div class="proj-stat">
                    <div class="proj-stat-val" style="font-size:.78rem;color:var(--color-success)">R$&nbsp;{{ number_format($netTotal, 0, ',', '.') }}</div>
                    <div class="proj-stat-lbl">Val. Líquido</div>
                </div>
            </div>
        </div>

        <div class="proj-actions">
            {{-- Sempre disponível: ver produtores e comprovantes --}}
            <a href="{{ route('delivery.projects.producers', ['tenant' => $tenant->slug, 'project' => $proj->id]) }}"
               class="btn btn-primary" title="Ver produtores e gerar comprovantes">
                <i data-lucide="users" style="width:13px;height:13px"></i> Produtores
            </a>

            <a href="{{ route('delivery.projects.deliveries', ['tenant' => $tenant->slug, 'project' => $proj->id]) }}"
               class="btn btn-ghost" title="Ver entregas do projeto">
                <i data-lucide="list" style="width:13px;height:13px"></i> Entregas
            </a>

            @if($isEditable)
                <a href="{{ route('delivery.register', ['tenant' => $tenant->slug, 'project' => $proj->id]) }}"
                   class="btn btn-success" title="Registrar entregas">
                    <i data-lucide="plus" style="width:13px;height:13px"></i> Registrar
                </a>
            @endif
        </div>
    </div>
    @endforeach
</div>

{{-- PAGINAÇÃO --}}
@if($projects->hasPages())
<div style="margin-top:1.5rem;display:flex;justify-content:center;">
    {{ $projects->appends(request()->query())->links() }}
</div>
@endif
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>
@endsection

