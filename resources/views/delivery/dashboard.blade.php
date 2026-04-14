@extends('layouts.bento')

@section('title', 'Painel de Entregas')
@section('page-title', 'Painel de Entregas')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard', ['tenant' => $currentTenant->slug]) }}" class="nav-tab active">
        <i data-lucide="layout-dashboard" style="width:14px;height:14px"></i> Dashboard
    </a>
    <a href="{{ route('delivery.all-deliveries', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="list" style="width:14px;height:14px"></i> Entregas
    </a>
    <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="plus-circle" style="width:14px;height:14px"></i> Registrar
    </a>
    <a href="{{ route('delivery.sheet.index', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="file-text" style="width:14px;height:14px"></i> Fichas
    </a>
    <form action="{{ route('logout') }}" method="POST" style="display:inline">
        @csrf
        <button type="submit" class="nav-tab" style="background:none;cursor:pointer;color:var(--color-danger)">
            <i data-lucide="log-out" style="width:14px;height:14px"></i> Sair
        </button>
    </form>
</nav>
@endsection

@section('content')
<style>
/* ── Layout ── */
.dp-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.85rem; margin-bottom:1.75rem; }
.dp-stat  { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:1.1rem 1.3rem; transition:.2s; }
.dp-stat:hover { box-shadow:0 4px 14px rgba(0,0,0,.07); transform:translateY(-2px); }
.dp-stat-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--color-text-secondary); margin-bottom:.3rem; }
.dp-stat-value { font-size:1.9rem; font-weight:800; line-height:1; }
.dp-stat-sub   { font-size:.72rem; color:var(--color-text-secondary); margin-top:.2rem; }

/* ── Section header ── */
.dp-section-header { display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:1.1rem; }
.dp-section-title  { font-size:1.05rem; font-weight:700; display:flex; align-items:center; gap:.45rem; }
.dp-badge { background:var(--color-primary); color:#fff; font-size:.65rem; font-weight:800; padding:.1rem .42rem; border-radius:99px; }

/* ── Project cards ── */
.dp-projects { display:grid; gap:1.1rem; }
.dp-card {
    background:var(--color-surface);
    border:1px solid var(--color-border);
    border-radius:var(--radius-lg);
    overflow:hidden;
    transition:.2s;
}
.dp-card:hover  { border-color:var(--color-primary); box-shadow:0 4px 18px rgba(0,0,0,.09); transform:translateY(-2px); }
.dp-card.draft  { border-color:var(--color-warning); opacity:.9; }
.dp-card.awaiting { border-color:var(--color-info,#0ea5e9); }

.dp-card-head {
    padding:1.1rem 1.3rem;
    border-bottom:1px solid var(--color-border);
    display:flex; justify-content:space-between; align-items:flex-start; gap:.75rem;
}
.dp-card-head-info { flex:1; min-width:0; }
.dp-card-title {
    font-size:1.05rem; font-weight:700; margin:0 0 .2rem;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    display:flex; align-items:center; gap:.35rem;
}
.dp-card-meta { font-size:.77rem; color:var(--color-text-secondary); display:flex; align-items:center; gap:.3rem; }

.dp-badge-status { display:inline-flex; align-items:center; gap:.3rem; padding:.25rem .65rem; border-radius:99px; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; }
.dp-badge-status.draft          { background:rgba(245,158,11,.13); color:#d97706; }
.dp-badge-status.active         { background:rgba(16,185,129,.13); color:#059669; }
.dp-badge-status.awaiting_delivery { background:rgba(14,165,233,.13); color:#0284c7; }

.dp-badge-free { display:inline-flex; align-items:center; gap:.2rem; background:rgba(139,92,246,.13); color:#7c3aed; font-size:.6rem; font-weight:700; padding:.1rem .35rem; border-radius:99px; text-transform:uppercase; }

.dp-card-body   { padding:1.1rem 1.3rem; }
.dp-info-grid   { display:grid; grid-template-columns:repeat(auto-fit,minmax(70px,1fr)); gap:.6rem; margin-bottom:.9rem; }
.dp-info-item   { text-align:center; }
.dp-info-label  { font-size:.65rem; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-secondary); margin-bottom:.18rem; }
.dp-info-value  { font-size:1.05rem; font-weight:700; }
.dp-info-value.ok  { color:var(--color-success); }
.dp-info-value.warn { color:var(--color-warning); }
.dp-info-value.danger { color:var(--color-danger); }

.dp-progress-wrap { margin-bottom:.9rem; }
.dp-progress-head { display:flex; justify-content:space-between; margin-bottom:.3rem; font-size:.78rem; font-weight:600; color:var(--color-text-secondary); }
.dp-progress-pct  { color:var(--color-primary); font-weight:700; }
.dp-progress-bar  { height:7px; background:var(--color-border); border-radius:99px; overflow:hidden; }
.dp-progress-fill { height:100%; background:linear-gradient(90deg,var(--color-primary),var(--color-success)); transition:width .8s cubic-bezier(.4,0,.2,1); }

.dp-card-footer {
    padding:.75rem 1.3rem;
    background:var(--color-bg);
    display:flex; justify-content:space-between; align-items:center; gap:.6rem; flex-wrap:wrap;
    border-top:1px solid var(--color-border);
}
.dp-deadline { display:flex; align-items:center; gap:.3rem; font-size:.77rem; color:var(--color-text-secondary); }
.dp-deadline.urgent { color:var(--color-danger); font-weight:600; }

.dp-draft-bar { padding:.7rem 1.3rem; background:rgba(245,158,11,.08); border-top:1px solid rgba(245,158,11,.22); display:flex; justify-content:space-between; align-items:center; gap:.6rem; flex-wrap:wrap; }
.dp-draft-msg { display:flex; align-items:center; gap:.35rem; font-size:.8rem; color:var(--color-text-secondary); }

/* ── Buttons ── */
.btn { display:inline-flex; align-items:center; gap:.3rem; padding:.42rem .9rem; border-radius:var(--radius-md); border:none; cursor:pointer; font-size:.78rem; font-weight:600; text-decoration:none; transition:.15s; white-space:nowrap; }
.btn:disabled { opacity:.45; pointer-events:none; }
.btn-primary { background:var(--color-primary); color:#fff; }
.btn-primary:hover { opacity:.88; transform:translateY(-1px); }
.btn-secondary { background:var(--color-secondary,#7c3aed); color:#fff; }
.btn-secondary:hover { opacity:.88; transform:translateY(-1px); }
.btn-warning { background:var(--color-warning); color:#fff; }
.btn-warning:hover { opacity:.88; transform:translateY(-1px); }
.btn-success { background:var(--color-success); color:#fff; }
.btn-success:hover { opacity:.88; transform:translateY(-1px); }
.btn-info { background:var(--color-info,#0ea5e9); color:#fff; }
.btn-info:hover { opacity:.88; transform:translateY(-1px); }
.btn-ghost { background:transparent; color:var(--color-text-secondary); border:1px solid var(--color-border); }
.btn-ghost:hover { background:var(--color-bg); color:var(--color-text); }
.btn-danger { background:var(--color-danger); color:#fff; }
.btn-danger:hover { opacity:.88; transform:translateY(-1px); }
.btn-sm { padding:.3rem .65rem; font-size:.72rem; }
.btn-group { display:flex; gap:.4rem; flex-wrap:wrap; }

/* ── Empty state ── */
.dp-empty { text-align:center; padding:3rem 1.5rem; color:var(--color-text-secondary); }
.dp-empty-icon { width:50px; height:50px; margin:0 auto .9rem; opacity:.35; }
.dp-empty-title { font-size:1.05rem; font-weight:700; color:var(--color-text); margin-bottom:.4rem; }
.dp-empty-msg   { font-size:.85rem; }

/* ── Modal / Qty ── */
.dp-modal-overlay { position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,.5); backdrop-filter:blur(3px); display:none; align-items:center; justify-content:center; padding:1rem; }
.dp-modal-overlay.open { display:flex; }
.dp-modal { background:var(--color-surface); border-radius:var(--radius-lg); padding:1.75rem; max-width:480px; width:100%; box-shadow:0 24px 60px rgba(0,0,0,.28); max-height:88vh; overflow-y:auto; }
.dp-modal-title { font-size:1.1rem; font-weight:700; display:flex; align-items:center; gap:.45rem; margin-bottom:.25rem; }
.dp-modal-sub   { font-size:.8rem; color:var(--color-text-secondary); margin-bottom:1.2rem; }
.dp-modal-footer { display:flex; justify-content:flex-end; gap:.5rem; flex-wrap:wrap; margin-top:1.2rem; }
.dp-form-group { margin-bottom:.85rem; }
.dp-form-group label { display:block; font-size:.78rem; font-weight:600; margin-bottom:.3rem; }
.dp-form-group input, .dp-form-group textarea {
    width:100%; padding:.42rem .65rem; border:1px solid var(--color-border);
    border-radius:var(--radius-md); background:var(--color-bg); color:var(--color-text); font-size:.875rem;
}
.dp-form-group textarea { min-height:56px; resize:vertical; }
.dp-product-rows { display:flex; flex-direction:column; gap:.6rem; margin-bottom:.85rem; }
.dp-product-row { background:var(--color-bg); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:.75rem; }
.dp-product-row-name { font-size:.85rem; font-weight:600; margin-bottom:.25rem; }
.dp-product-row-meta { font-size:.7rem; color:var(--color-text-secondary); margin-bottom:.4rem; }
.dp-qty-line { display:flex; align-items:center; gap:.45rem; }
.dp-qty-line label { font-size:.75rem; color:var(--color-text-secondary); white-space:nowrap; }
.dp-qty-line input { flex:1; padding:.32rem .5rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-surface); color:var(--color-text); font-size:.85rem; }
.dp-qty-unit { font-size:.72rem; color:var(--color-text-secondary); }

/* ── Confirm modal ── */
.dp-confirm { background:var(--color-surface); border-radius:var(--radius-lg); padding:2rem; max-width:400px; width:100%; text-align:center; box-shadow:0 24px 60px rgba(0,0,0,.28); }
.dp-confirm-icon { margin-bottom:1rem; }
.dp-confirm-title { font-size:1.15rem; font-weight:700; margin-bottom:.4rem; }
.dp-confirm-msg   { font-size:.875rem; color:var(--color-text-secondary); margin-bottom:1.5rem; line-height:1.5; }
.dp-confirm-footer { display:flex; justify-content:center; gap:.6rem; }

/* ── Toast ── */
#dp-toasts { position:fixed; bottom:1.5rem; right:1.5rem; z-index:99999; display:flex; flex-direction:column; gap:.5rem; }
.dp-toast { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:.7rem 1rem; display:flex; align-items:center; gap:.5rem; font-size:.85rem; box-shadow:0 4px 14px rgba(0,0,0,.14); min-width:260px; max-width:360px; animation:dp-fadein .28s ease; }
.dp-toast.success { border-left:3px solid var(--color-success); }
.dp-toast.error   { border-left:3px solid var(--color-danger); }
.dp-toast.info    { border-left:3px solid #0ea5e9; }
@keyframes dp-fadein { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }

/* ── Responsive ── */
@media(max-width:480px) {
    .dp-stats { grid-template-columns:1fr 1fr; }
    .dp-card-head { flex-direction:column; }
    .btn-group { flex-direction:column; }
    .btn-group .btn { width:100%; justify-content:center; }
    .dp-info-grid { grid-template-columns:repeat(3,1fr); }
}
</style>

<div id="dp-toasts"></div>

{{-- ── STATS ── --}}
<div class="dp-stats">
    <div class="dp-stat">
        <div class="dp-stat-label">Projetos ativos</div>
        <div class="dp-stat-value" style="color:var(--color-primary)">{{ $stats['active_projects'] }}</div>
        <div class="dp-stat-sub">em andamento</div>
    </div>
    <div class="dp-stat">
        <div class="dp-stat-label">Entregas hoje</div>
        <div class="dp-stat-value" style="color:var(--color-success)">{{ $stats['deliveries_today'] }}</div>
        <div class="dp-stat-sub">registros do dia</div>
    </div>
    <div class="dp-stat">
        <div class="dp-stat-label">Pendentes</div>
        <div class="dp-stat-value" style="color:var(--color-warning)">{{ $stats['pending_approvals'] }}</div>
        <div class="dp-stat-sub">aguardando aprovação</div>
    </div>
    <div class="dp-stat">
        <div class="dp-stat-label">Semana atual</div>
        <div class="dp-stat-value" style="font-size:1.45rem">{{ number_format($stats['total_delivered_this_week'], 0, ',', '.') }}</div>
        <div class="dp-stat-sub">unidades entregues</div>
    </div>
</div>

{{-- ── SECTION HEADER ── --}}
<div class="dp-section-header">
    <div class="dp-section-title">
        <i data-lucide="folder-open" style="width:18px;height:18px;color:var(--color-primary)"></i>
        Projetos
        <span class="dp-badge">{{ count($projects) }}</span>
    </div>
    <div class="btn-group">
        <a href="{{ route('delivery.all-deliveries', ['tenant' => $currentTenant->slug]) }}" class="btn btn-ghost btn-sm">
            <i data-lucide="list" style="width:13px;height:13px"></i>Todas entregas
        </a>
        <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug]) }}" class="btn btn-primary btn-sm">
            <i data-lucide="package-plus" style="width:13px;height:13px"></i>Entrega avulsa
        </a>
    </div>
</div>

{{-- ── PROJECTS LIST ── --}}
@if($projects->isEmpty())
    <div class="dp-empty">
        <svg class="dp-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
        </svg>
        <div class="dp-empty-title">Nenhum Projeto Disponível</div>
        <div class="dp-empty-msg">Não há projetos em andamento ou em rascunho para este período.</div>
    </div>
@else
    <div class="dp-projects">
        @foreach($projects as $i => $project)
        <div class="dp-card {{ $project['status_value'] }}"
             data-id="{{ $project['id'] }}"
             data-title="{{ e($project['title']) }}"
             data-allow-any="{{ $project['allow_any_product'] ? '1' : '0' }}">

            {{-- Header --}}
            <div class="dp-card-head">
                <div class="dp-card-head-info">
                    <h3 class="dp-card-title" title="{{ $project['title'] }}">
                        {{ $project['title'] }}
                        @if($project['allow_any_product'])
                            <span class="dp-badge-free">
                                <i data-lucide="infinity" style="width:9px;height:9px"></i> Livre
                            </span>
                        @endif
                    </h3>
                    <div class="dp-card-meta">
                        <i data-lucide="building-2" style="width:11px;height:11px"></i>
                        {{ $project['customer_name'] }}
                        @if($project['start_date'])
                            &nbsp;·&nbsp;
                            <i data-lucide="calendar" style="width:11px;height:11px"></i>
                            {{ $project['start_date'] }}
                            @if($project['end_date']) → {{ $project['end_date'] }} @endif
                        @endif
                    </div>
                </div>
                <span class="dp-badge-status {{ $project['status_value'] }}">
                    @if($project['status_value'] === 'draft') <i data-lucide="file-edit" style="width:9px;height:9px"></i>
                    @elseif($project['status_value'] === 'awaiting_delivery') <i data-lucide="package-check" style="width:9px;height:9px"></i>
                    @else <i data-lucide="play" style="width:9px;height:9px"></i>
                    @endif
                    {{ $project['status'] }}
                </span>
            </div>

            {{-- Body --}}
            <div class="dp-card-body">
                {{-- Stats grid --}}
                <div class="dp-info-grid">
                    @if(!$project['allow_any_product'])
                    <div class="dp-info-item">
                        <div class="dp-info-label">Meta</div>
                        <div class="dp-info-value">{{ number_format($project['total_target'],0,',','.') }}</div>
                    </div>
                    @endif
                    <div class="dp-info-item">
                        <div class="dp-info-label">Entregue</div>
                        <div class="dp-info-value ok">{{ number_format($project['total_delivered'],0,',','.') }}</div>
                    </div>
                    <div class="dp-info-item">
                        <div class="dp-info-label">Aprovadas</div>
                        <div class="dp-info-value ok">{{ $project['approved_deliveries'] }}</div>
                    </div>
                    <div class="dp-info-item">
                        <div class="dp-info-label">Pendentes</div>
                        <div class="dp-info-value {{ $project['pending_deliveries'] > 0 ? 'warn' : '' }}">{{ $project['pending_deliveries'] }}</div>
                    </div>
                    <div class="dp-info-item">
                        <div class="dp-info-label">Rejeitadas</div>
                        <div class="dp-info-value {{ $project['rejected_deliveries'] > 0 ? 'danger' : '' }}">{{ $project['rejected_deliveries'] }}</div>
                    </div>
                    @if($project['days_remaining'] !== null)
                    <div class="dp-info-item">
                        <div class="dp-info-label">Dias rest.</div>
                        <div class="dp-info-value {{ $project['days_remaining'] < 3 ? 'danger' : ($project['days_remaining'] < 7 ? 'warn' : '') }}">
                            {{ max(0, $project['days_remaining']) }}
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Progress bar (only when has target) --}}
                @if(!$project['allow_any_product'] && $project['total_target'] > 0)
                <div class="dp-progress-wrap">
                    <div class="dp-progress-head">
                        <span>Progresso do projeto</span>
                        <span class="dp-progress-pct">{{ number_format($project['progress'],1,',','.') }}%</span>
                    </div>
                    <div class="dp-progress-bar">
                        <div class="dp-progress-fill" style="width:{{ $project['progress'] }}%"></div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Draft banner --}}
            @if($project['status_value'] === 'draft')
            <div class="dp-draft-bar">
                <div class="dp-draft-msg">
                    <i data-lucide="info" style="width:14px;height:14px;color:var(--color-warning)"></i>
                    Projeto em rascunho. Inicie para habilitar registros.
                </div>
                <button class="btn btn-warning btn-sm" onclick="confirmStartProject({{ $project['id'] }}, `{{ e($project['title']) }}`)">
                    <i data-lucide="play" style="width:12px;height:12px"></i> Iniciar Projeto
                </button>
            </div>
            @endif

            {{-- Footer --}}
            <div class="dp-card-footer">
                @if($project['days_remaining'] !== null)
                <div class="dp-deadline {{ $project['days_remaining'] < 3 ? 'urgent' : '' }}">
                    <i data-lucide="clock" style="width:13px;height:13px"></i>
                    @if($project['days_remaining'] < 0)
                        Prazo encerrado
                    @elseif($project['days_remaining'] === 0)
                        Último dia!
                    @else
                        {{ $project['days_remaining'] }} dia(s) restante(s)
                    @endif
                </div>
                @else
                <div></div>
                @endif

                <div class="btn-group">
                    @if($project['status_value'] === 'active')
                        <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug, 'project' => $project['id']]) }}"
                           class="btn btn-primary btn-sm">
                            <i data-lucide="plus" style="width:12px;height:12px"></i> Registrar
                        </a>
                    @endif

                    <a href="{{ route('delivery.projects.deliveries', ['tenant' => $currentTenant->slug, 'project' => $project['id']]) }}"
                       class="btn btn-ghost btn-sm">
                        <i data-lucide="list" style="width:12px;height:12px"></i> Entregas
                    </a>

                    <a href="{{ route('delivery.projects.producers', ['tenant' => $currentTenant->slug, 'project' => $project['id']]) }}"
                       class="btn btn-ghost btn-sm">
                        <i data-lucide="users" style="width:12px;height:12px"></i> Produtores
                    </a>

                    @if($project['status_value'] === 'active')
                    <button class="btn btn-info btn-sm"
                            onclick="confirmFinalizeProject({{ $project['id'] }},`{{ e($project['title']) }}`,{{ $project['pending_deliveries'] }})">
                        <i data-lucide="check-circle" style="width:12px;height:12px"></i> Finalizar entregas
                    </button>
                    @elseif($project['status_value'] === 'awaiting_delivery')
                    <button class="btn btn-success btn-sm"
                            onclick="openDeliverToClientModal({{ $project['id'] }},`{{ e($project['title']) }}`)">
                        <i data-lucide="truck" style="width:12px;height:12px"></i> Entregar ao cliente
                    </button>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif

{{-- ───────── CONFIRM START ───────── --}}
<div id="modal-start" class="dp-modal-overlay">
    <div class="dp-confirm">
        <div class="dp-confirm-icon"><i data-lucide="play-circle" style="width:48px;height:48px;color:var(--color-warning)"></i></div>
        <div class="dp-confirm-title" id="modal-start-title">Iniciar projeto?</div>
        <div class="dp-confirm-msg" id="modal-start-msg">Esta ação muda o status para "Em Execução".</div>
        <div class="dp-confirm-footer">
            <button class="btn btn-ghost" onclick="document.getElementById('modal-start').classList.remove('open')">Cancelar</button>
            <button class="btn btn-warning" id="modal-start-btn">
                <span id="modal-start-spinner" class="dp-spinner"></span> Iniciar
            </button>
        </div>
    </div>
</div>

{{-- ───────── CONFIRM FINALIZE ───────── --}}
<div id="modal-finalize" class="dp-modal-overlay">
    <div class="dp-confirm">
        <div class="dp-confirm-icon"><i data-lucide="check-circle" style="width:48px;height:48px;color:var(--color-info,#0ea5e9)"></i></div>
        <div class="dp-confirm-title">Finalizar entregas?</div>
        <div class="dp-confirm-msg" id="modal-finalize-msg">As entregas pendentes precisam ser processadas antes.</div>
        <div class="dp-confirm-footer">
            <button class="btn btn-ghost" onclick="document.getElementById('modal-finalize').classList.remove('open')">Cancelar</button>
            <button class="btn btn-info" id="modal-finalize-btn">
                <span id="modal-finalize-spinner" class="dp-spinner"></span> Finalizar
            </button>
        </div>
    </div>
</div>

{{-- ───────── DELIVER TO CLIENT MODAL ───────── --}}
<div id="modal-deliver" class="dp-modal-overlay">
    <div class="dp-modal">
        <div class="dp-modal-title">
            <i data-lucide="truck" style="width:20px;height:20px;color:var(--color-success)"></i>
            Entregar ao Cliente
        </div>
        <div class="dp-modal-sub" id="modal-deliver-sub">Informe as quantidades a serem baixadas do estoque.</div>
        <div class="dp-form-group">
            <label>Data da Entrega</label>
            <input type="date" id="deliver-date" value="{{ now()->format('Y-m-d') }}">
        </div>
        <div id="dp-product-rows" class="dp-product-rows"></div>
        <div class="dp-form-group">
            <label>Observações (opcional)</label>
            <textarea id="deliver-notes" placeholder="Anotações sobre a entrega..."></textarea>
        </div>
        <div class="dp-modal-footer">
            <button class="btn btn-ghost" onclick="document.getElementById('modal-deliver').classList.remove('open')">Cancelar</button>
            <button class="btn btn-success" id="modal-deliver-btn">
                <span id="modal-deliver-spinner" class="dp-spinner" style="display:none"></span>
                <i data-lucide="check" style="width:13px;height:13px"></i> Confirmar Entrega
            </button>
        </div>
    </div>
</div>

<style>
.dp-spinner { display:inline-block; width:12px; height:12px; border:2px solid rgba(255,255,255,.35); border-top-color:#fff; border-radius:50%; animation:dp-spin .6s linear infinite; }
@keyframes dp-spin { to { transform:rotate(360deg); } }
</style>

<script>
const TENANT = '{{ $currentTenant->slug }}';
const CSRF   = '{{ csrf_token() }}';

function toast(msg, type='success') {
    const c = document.getElementById('dp-toasts');
    const el = document.createElement('div');
    el.className = `dp-toast ${type}`;
    const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
    el.innerHTML = `<span>${icon}</span><span>${msg}</span>`;
    c.appendChild(el);
    setTimeout(() => { el.style.opacity=0; setTimeout(() => el.remove(), 300); }, 4000);
}

async function apiPost(url, body={}) {
    const r = await fetch(url, {
        method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body)
    });
    return r.json();
}

// ── Start Project ──
let _startProjectId = null;
function confirmStartProject(id, title) {
    _startProjectId = id;
    document.getElementById('modal-start-title').textContent = `Iniciar: ${title}`;
    document.getElementById('modal-start-msg').textContent = 'O projeto será marcado como "Em Execução" e as entregas serão habilitadas.';
    document.getElementById('modal-start').classList.add('open');
}
document.getElementById('modal-start-btn').addEventListener('click', async () => {
    const btn = document.getElementById('modal-start-btn');
    btn.disabled = true;
    const data = await apiPost(`/${TENANT}/delivery/projects/${_startProjectId}/start`);
    btn.disabled = false;
    document.getElementById('modal-start').classList.remove('open');
    if (data.success) { toast(data.message); setTimeout(() => location.reload(), 1000); }
    else toast(data.message || 'Erro ao iniciar.', 'error');
});

// ── Finalize Project ──
let _finalizeProjectId = null;
function confirmFinalizeProject(id, title, pending) {
    _finalizeProjectId = id;
    const msg = pending > 0
        ? `⚠️ Existem ${pending} entrega(s) pendente(s). Informe: elas serão rejeitadas, ou processe-as antes.`
        : `O projeto "${title}" terá as entregas finalizadas. Confirma?`;
    document.getElementById('modal-finalize-msg').textContent = msg;
    document.getElementById('modal-finalize').classList.add('open');
}
document.getElementById('modal-finalize-btn').addEventListener('click', async () => {
    const btn = document.getElementById('modal-finalize-btn');
    btn.disabled = true;
    const data = await apiPost(`/${TENANT}/delivery/projects/${_finalizeProjectId}/finalize`);
    btn.disabled = false;
    document.getElementById('modal-finalize').classList.remove('open');
    if (data.success) { toast(data.message); setTimeout(() => location.reload(), 1000); }
    else toast(data.message || 'Erro ao finalizar.', 'error');
});

// ── Deliver to Client ──
let _deliverProjectId = null;
async function openDeliverToClientModal(id, title) {
    _deliverProjectId = id;
    document.getElementById('modal-deliver-sub').textContent = `Projeto: ${title}`;
    const rows = document.getElementById('dp-product-rows');
    rows.innerHTML = '<div style="text-align:center;padding:1rem;color:var(--color-text-secondary)">Carregando produtos...</div>';
    document.getElementById('modal-deliver').classList.add('open');
    try {
        const r = await fetch(`/${TENANT}/delivery/projects/${id}/stock-summary`, { headers: { Accept: 'application/json' } });
        const products = await r.json();
        if (!products.length) {
            rows.innerHTML = '<div style="color:var(--color-warning);font-size:.85rem">Nenhum produto aprovado encontrado.</div>';
            return;
        }
        rows.innerHTML = products.map(p => `
            <div class="dp-product-row">
                <div class="dp-product-row-name">${p.product_name}</div>
                <div class="dp-product-row-meta">Aprovado: ${p.approved_qty.toFixed(3)} ${p.product_unit} · Estoque: ${p.current_stock.toFixed(3)} ${p.product_unit}</div>
                <div class="dp-qty-line">
                    <label>Qtd. a entregar</label>
                    <input class="deliver-qty" type="number" step="0.001" min="0" max="${p.max_deliverable}"
                           value="${p.max_deliverable.toFixed(3)}" data-product="${p.product_id}">
                    <span class="dp-qty-unit">${p.product_unit}</span>
                </div>
            </div>`).join('');
    } catch(e) { rows.innerHTML = '<div style="color:var(--color-danger);">Erro ao carregar produtos.</div>'; }
}
document.getElementById('modal-deliver-btn').addEventListener('click', async () => {
    const btn = document.getElementById('modal-deliver-btn');
    const sp = document.getElementById('modal-deliver-spinner');
    btn.disabled = true; sp.style.display = 'inline-block';
    const quantities = {};
    document.querySelectorAll('.deliver-qty').forEach(i => { quantities[i.dataset.product] = parseFloat(i.value)||0; });
    const data = await apiPost(`/${TENANT}/delivery/projects/${_deliverProjectId}/deliver-to-client`, {
        delivery_date: document.getElementById('deliver-date').value,
        notes: document.getElementById('deliver-notes').value,
        quantities
    });
    btn.disabled = false; sp.style.display = 'none';
    document.getElementById('modal-deliver').classList.remove('open');
    if (data.success) { toast(data.message); setTimeout(() => location.reload(), 1200); }
    else toast(data.message || 'Erro ao registrar entrega.', 'error');
});

// Close modals on overlay click
document.querySelectorAll('.dp-modal-overlay').forEach(el => {
    el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});
</script>
@endsection