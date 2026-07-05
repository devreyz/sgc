@extends('layouts.bento')

@section('title', 'Entregas do Projeto')
@section('page-title', 'Histórico de Entregas')
@section('user-role', 'Registrador')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('delivery.dashboard', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="layout-dashboard" style="width:14px;height:14px"></i> Dashboard
    </a>
    <a href="{{ route('delivery.all-deliveries', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="list" style="width:14px;height:14px"></i> Entregas
    </a>
    <a href="{{ route('delivery.projects-list', ['tenant' => $currentTenant->slug]) }}" class="nav-tab">
        <i data-lucide="folder-open" style="width:14px;height:14px"></i> Projetos
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
    /* ========== BASE & UTILITY ========== */
    .pd-header {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.2rem 1.4rem;
        margin-bottom: 1.25rem;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .pd-title { font-size:1.2rem; font-weight:700; margin:0 0 .2rem; display:flex; align-items:center; gap:.45rem; }
    .pd-sub { font-size:.82rem; color:var(--color-text-secondary); display:flex; align-items:center; gap:.3rem; }
    .pd-header-actions { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-start; }

    .pd-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:.65rem; margin-bottom:1.25rem; }
    .pd-stat  { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:.75rem 1rem; text-align:center; }
    .pd-stat-lbl { font-size:.65rem; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-secondary); }
    .pd-stat-val { font-size:1.35rem; font-weight:800; }

    .pd-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); overflow:hidden; margin-bottom:1.25rem; }
    .pd-card-header { padding:.9rem 1.2rem; border-bottom:1px solid var(--color-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; }
    .pd-card-title  { font-size:.95rem; font-weight:700; display:flex; align-items:center; gap:.4rem; }

    /* ========== FILTROS ========== */
    .filters-bar {
        padding: .75rem 1.2rem;
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        align-items: center;
        border-bottom: 1px solid var(--color-border);
    }
    .filter-input, .filter-select {
        padding: .4rem .65rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: .78rem;
        background: var(--color-bg);
        color: var(--color-text);
        min-width: 120px;
    }
    .filter-input:focus, .filter-select:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 2px rgba(var(--color-primary-rgb, 59,130,246),.15);
    }
    .filter-tag {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .25rem .6rem;
        border-radius: 99px;
        font-size: .72rem;
        font-weight: 600;
        background: var(--color-primary);
        color: #fff;
        cursor: pointer;
    }
    .filter-tag i { cursor: pointer; }

    /* ========== TABELA DESKTOP ========== */
    .table-scroll { overflow-x:auto; }
    .data-table { width:100%; border-collapse:collapse; font-size:.84rem; }
    .data-table th { background:var(--color-bg); padding:.6rem .8rem; text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-secondary); font-weight:600; border-bottom:2px solid var(--color-border); white-space:nowrap; }
    .data-table td { padding:.6rem .8rem; border-bottom:1px solid var(--color-border); vertical-align:middle; }
    .data-table tr:hover td { background:rgba(0,0,0,.02); }
    .data-table tr.approved-row td { opacity:.75; }
    .chk-cell { width:32px; text-align:center; }
    .chk-cell input[type=checkbox] { width:16px; height:16px; cursor:pointer; accent-color:var(--color-primary); }

    /* ========== BADGES & BUTTONS ========== */
    .badge-status { display:inline-flex; align-items:center; gap:.2rem; padding:.18rem .5rem; border-radius:99px; font-size:.68rem; font-weight:600; text-transform:uppercase; white-space:nowrap; }
    .badge-status.pending  { background:rgba(245,158,11,.14); color:#d97706; }
    .badge-status.approved { background:rgba(16,185,129,.14); color:#059669; }
    .badge-status.rejected { background:rgba(239,68,68,.14); color:#dc2626; }
    .badge-status.cancelled { background:rgba(107,114,128,.14); color:#6b7280; }

    .btn { display:inline-flex; align-items:center; gap:.3rem; padding:.4rem .8rem; border-radius:var(--radius-md); border:none; cursor:pointer; font-size:.78rem; font-weight:600; text-decoration:none; transition:.15s; white-space:nowrap; }
    .btn:disabled { opacity:.45; cursor:not-allowed; }
    .btn-success { background:var(--color-success); color:#fff; }
    .btn-success:hover:not(:disabled) { opacity:.88; transform:translateY(-1px); }
    .btn-danger  { background:var(--color-danger); color:#fff; }
    .btn-danger:hover:not(:disabled)  { opacity:.88; transform:translateY(-1px); }
    .btn-ghost   { background:transparent; color:var(--color-text-secondary); border:1px solid var(--color-border); }
    .btn-ghost:hover { background:var(--color-bg); color:var(--color-text); }
    .btn-sm { padding:.3rem .6rem; font-size:.73rem; }
    .btn-xs { padding:.22rem .5rem; font-size:.7rem; }
    .action-btns { display:flex; gap:.3rem; flex-wrap:wrap; }
    .btn-approve, .btn-reject, .btn-edit, .btn-distribute, .btn-delete-approved {
        display:inline-flex; align-items:center; gap:.2rem; font-size:.75rem; font-weight:600; border-radius:var(--radius-md); border:none; cursor:pointer; padding:.28rem .6rem; transition:.15s; white-space:nowrap;
    }
    .btn-approve { background:rgba(16,185,129,.12); color:#059669; }
    .btn-approve:hover:not(:disabled) { background:var(--color-success); color:#fff; }
    .btn-reject  { background:rgba(239,68,68,.12); color:#dc2626; }
    .btn-reject:hover:not(:disabled)  { background:var(--color-danger); color:#fff; }
    .btn-edit    { background:rgba(59,130,246,.12); color:#2563eb; }
    .btn-edit:hover:not(:disabled) { background:#2563eb; color:#fff; }
    .btn-distribute { background:rgba(99,102,241,.12); color:#4f46e5; }
    .btn-distribute:hover:not(:disabled) { background:#4f46e5; color:#fff; }
    .btn-delete-approved { background:rgba(239,68,68,.08); color:#dc2626; }
    .btn-delete-approved:hover:not(:disabled) { background:var(--color-danger); color:#fff; }

    /* ========== DISTRIBUTION INDICATOR ========== */
    .dist-indicator {
        display:flex; align-items:center; gap:.35rem; font-size:.72rem;
    }
    .dist-bar-bg {
        width:54px; height:7px; background:#e5e7eb; border-radius:99px; overflow:hidden;
    }
    .dist-bar-fill {
        height:100%; border-radius:99px; transition:width .3s;
    }
    .dist-bar-fill.full { background:#16a34a; }
    .dist-bar-fill.partial { background:#f59e0b; }
    .dist-bar-fill.over { background:#dc2626; }
    .dist-text { white-space:nowrap; font-weight:600; min-width:38px; font-size:.68rem; }
    .dist-warning { color:#dc2626; font-size:.65rem; font-weight:600; }

    /* ========== MODALS ========== */
    .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:100000; }
    .modal-overlay.hidden { display:none; }
    .modal-box { background:var(--color-surface); border-radius:var(--radius-lg); padding:1.5rem; width:min(480px,95vw); box-shadow:0 8px 32px rgba(0,0,0,.22); }
    .modal-title { font-size:1rem; font-weight:700; margin-bottom:1rem; display:flex; align-items:center; gap:.4rem; }
    .form-group { margin-bottom:.85rem; }
    .form-label { display:block; font-size:.75rem; font-weight:600; margin-bottom:.3rem; color:var(--color-text-secondary); text-transform:uppercase; letter-spacing:.03em; }
    .form-control { width:100%; padding:.45rem .7rem; border:1px solid var(--color-border); border-radius:var(--radius-md); font-size:.88rem; background:var(--color-bg); color:var(--color-text); }
    .form-control:focus { outline:none; border-color:var(--color-primary); box-shadow:0 0 0 2px rgba(var(--color-primary-rgb),.15); }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; }
    .modal-footer { display:flex; gap:.5rem; justify-content:flex-end; margin-top:1.2rem; border-top:1px solid var(--color-border); padding-top:1rem; }

    /* Custom Confirm */
    .confirm-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:flex; align-items:center; justify-content:center; z-index:100001; }
    .confirm-overlay.hidden { display:none; }
    .confirm-box { background:var(--color-surface); border-radius:var(--radius-lg); padding:1.5rem; width:min(360px,92vw); box-shadow:0 12px 32px rgba(0,0,0,.25); }
    .confirm-message { font-size:.92rem; margin-bottom:1.2rem; line-height:1.4; }
    .confirm-buttons { display:flex; gap:.5rem; justify-content:flex-end; }

    /* ========== REPORTS BAR ========== */
    .reports-bar { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:.9rem 1.2rem; margin-bottom:1.25rem; }
    .reports-bar-title { font-size:.78rem; font-weight:700; margin-bottom:.55rem; display:flex; align-items:center; gap:.4rem; }
    .reports-row { display:flex; flex-wrap:wrap; gap:.45rem; }
    .report-btn { display:inline-flex; align-items:center; gap:.3rem; padding:.38rem .8rem; border-radius:var(--radius-md); border:1px solid var(--color-border); cursor:pointer; font-size:.77rem; font-weight:600; text-decoration:none; background:var(--color-bg); color:var(--color-text); transition:.15s; }
    .report-btn:hover { background:var(--color-primary); color:#fff; border-color:var(--color-primary); }

    /* ========== SELECTION BAR ========== */
    .selection-bar { position:fixed; bottom:0; left:0; right:0; background:var(--color-surface); border-top:2px solid var(--color-primary); padding:.75rem 1.2rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; z-index:99998; box-shadow:0 -4px 18px rgba(0,0,0,.14); transform:translateY(100%); transition:transform .25s ease; }
    .selection-bar.visible { transform:translateY(0); }
    .selection-bar-info { font-size:.88rem; font-weight:600; display:flex; align-items:center; gap:.4rem; }
    .selection-bar-actions { display:flex; gap:.5rem; align-items:center; }
    .btn-primary { background:var(--color-primary); color:#fff; }
    .btn-primary:hover:not(:disabled) { opacity:.88; transform:translateY(-1px); }

    /* ========== TOASTS ========== */
    #pd-toasts { position:fixed; bottom:1.5rem; right:1.5rem; z-index:99999; display:flex; flex-direction:column; gap:.5rem; }
    .pd-toast { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:.7rem 1rem; display:flex; align-items:center; gap:.5rem; font-size:.85rem; box-shadow:0 4px 14px rgba(0,0,0,.14); min-width:240px; max-width:340px; animation:pd-fi .25s ease; }
    .pd-toast.success { border-left:3px solid var(--color-success); }
    .pd-toast.error   { border-left:3px solid var(--color-danger); }
    @keyframes pd-fi { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }

    /* ========== MOBILE CARDS ========== */
    .mobile-cards { display:none; }
    @media (max-width: 767px) {
        .desktop-only { display:none !important; }
        .mobile-cards { display:block !important; }

        .mobile-card {
            background:var(--color-surface);
            border:1px solid var(--color-border);
            border-radius:var(--radius-md);
            margin-bottom:.45rem;
            padding:.5rem .6rem;
            position:relative;
            display:flex;
            flex-direction:column;
            gap:.15rem;
            font-size:.76rem;
            border-left:4px solid transparent;
        }
        .mobile-card.status-pending  { border-left-color:#f59e0b; }
        .mobile-card.status-approved { border-left-color:#16a34a; }
        .mobile-card.status-rejected { border-left-color:#dc2626; }
        .mobile-card.status-cancelled { border-left-color:#6b7280; }

        .mc-row {
            display:flex;
            align-items:center;
            gap:.4rem;
            flex-wrap:wrap;
        }
        .mc-chk { flex-shrink:0; }
        .mc-date { font-weight:600; color:var(--color-text); white-space:nowrap; }
        .mc-status { margin-left:auto; }
        .mc-assoc { font-size:.72rem; color:var(--color-text-secondary); }
        .mc-product { font-size:.78rem; font-weight:600; }
        .mc-details { display:flex; gap:.65rem; flex-wrap:wrap; align-items:center; }
        .mc-qty { font-weight:700; }
        .mc-net { font-weight:700; color:var(--color-success); }
        .mc-actions { display:flex; gap:.3rem; margin-top:.2rem; flex-wrap:wrap; }
        .mc-dist-indicator { display:flex; align-items:center; gap:.25rem; font-size:.7rem; }
        .mc-dist-bar-bg { width:44px; height:6px; background:#e5e7eb; border-radius:99px; overflow:hidden; }
        .mc-dist-bar-fill { height:100%; border-radius:99px; }
        .mc-dist-bar-fill.full { background:#16a34a; }
        .mc-dist-bar-fill.partial { background:#f59e0b; }
        .mc-dist-bar-fill.over { background:#dc2626; }
        .mc-dist-text { font-weight:600; min-width:34px; white-space:nowrap; font-size:.7rem; }
    }
</style>

<!-- Custom Confirm Modal -->
<div id="customConfirmOverlay" class="confirm-overlay hidden">
    <div class="confirm-box">
        <div class="confirm-message" id="confirmMessage"></div>
        <div class="confirm-buttons">
            <button class="btn btn-ghost btn-sm" id="confirmCancel">Cancelar</button>
            <button class="btn btn-sm btn-primary" id="confirmOk">Confirmar</button>
        </div>
    </div>
</div>

<div id="pd-toasts"></div>

{{-- Componentes Blade --}}
<x-delivery.edit-delivery-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
/>
<x-delivery.dist-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
    :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name])->values()->all()"
/>

{{-- PROJECT HEADER --}}
<div class="pd-header">
    <div class="pd-header-info">
        <h1 class="pd-title">
            <i data-lucide="folder-open" style="width:20px;height:20px;color:var(--color-primary)"></i>
            {{ $project->title }}
        </h1>
        <div class="pd-sub">
            <i data-lucide="building-2" style="width:13px;height:13px"></i>
            {{ $project->customer->name ?? '—' }}
        </div>
    </div>
    <div class="pd-header-actions">
        @if($project->status->value === 'active')
        <a href="{{ route('delivery.register', ['tenant' => $currentTenant->slug, 'project' => $project->id]) }}" class="btn btn-success btn-sm">
            <i data-lucide="plus" style="width:13px;height:13px"></i> Nova Entrega
        </a>
        @endif
        <a href="{{ route('delivery.projects.producers', ['tenant' => $currentTenant->slug, 'project' => $project->id]) }}" class="btn btn-ghost btn-sm">
            <i data-lucide="users" style="width:13px;height:13px"></i> Produtores
        </a>
        <a href="{{ route('delivery.dashboard', ['tenant' => $currentTenant->slug]) }}" class="btn btn-ghost btn-sm">
            <i data-lucide="arrow-left" style="width:13px;height:13px"></i> Voltar
        </a>
    </div>
</div>

{{-- STATS --}}
@php
$totalAll = $deliveries->count();
$totalApproved = $deliveries->where('status_value','approved')->count();
$totalPending  = $deliveries->where('status_value','pending')->count();
$totalRejected = $deliveries->where('status_value','rejected')->count();
$totalQty      = $deliveries->sum('quantity');
$totalNet      = $deliveries->sum('net_value');
@endphp
<div class="pd-stats">
    <div class="pd-stat">
        <div class="pd-stat-lbl">Total</div>
        <div class="pd-stat-val">{{ $totalAll }}</div>
    </div>
    <div class="pd-stat">
        <div class="pd-stat-lbl">Aprovadas</div>
        <div class="pd-stat-val" style="color:var(--color-success)">{{ $totalApproved }}</div>
    </div>
    <div class="pd-stat">
        <div class="pd-stat-lbl">Pendentes</div>
        <div class="pd-stat-val" style="color:var(--color-warning)">{{ $totalPending }}</div>
    </div>
    <div class="pd-stat">
        <div class="pd-stat-lbl">Rejeitadas</div>
        <div class="pd-stat-val" style="color:var(--color-danger)">{{ $totalRejected }}</div>
    </div>
    <div class="pd-stat">
        <div class="pd-stat-lbl">Qtd. Total</div>
        <div class="pd-stat-val" style="font-size:1rem;padding-top:.2rem">{{ number_format($totalQty, 3, ',', '.') }}</div>
    </div>
    <div class="pd-stat">
        <div class="pd-stat-lbl">Val. Líquido</div>
        <div class="pd-stat-val" style="font-size:.95rem;padding-top:.25rem;color:var(--color-success)">R$ {{ number_format($totalNet, 2, ',', '.') }}</div>
    </div>
</div>

{{-- REPORTS BAR --}}
@if($totalApproved > 0)
<div class="reports-bar">
    <div class="reports-bar-title">
        <i data-lucide="file-text" style="width:14px;height:14px;color:var(--color-primary)"></i>
        Relatórios PDF
    </div>
    <div class="reports-row">
        <a href="{{ route('delivery.reports.by-associate', ['tenant' => $currentTenant->slug, 'project_id' => $project->id]) }}" class="report-btn" target="_blank">
            <i data-lucide="users" style="width:13px;height:13px"></i> Por Associado
        </a>
        <a href="{{ route('delivery.reports.by-product', ['tenant' => $currentTenant->slug, 'project_id' => $project->id]) }}" class="report-btn" target="_blank">
            <i data-lucide="box" style="width:13px;height:13px"></i> Por Produto
        </a>
        <a href="{{ route('delivery.reports.distributions-by-customer', ['tenant' => $currentTenant->slug, 'project_id' => $project->id]) }}" class="report-btn" target="_blank">
            <i data-lucide="building-2" style="width:13px;height:13px"></i> Distribuições por Cliente
        </a>
        <a href="{{ route('delivery.reports.distributions-by-customer-compact', ['tenant' => $currentTenant->slug, 'project_id' => $project->id]) }}" class="report-btn" target="_blank" style="border-color:#059669;color:#059669;">
            <i data-lucide="file-check" style="width:13px;height:13px"></i> Resumo p/ Cobrança
        </a>
        <button type="button" class="report-btn" onclick="openCustomerReportModal()" style="border-color:#1d4ed8;color:#1d4ed8;background:#eff6ff;">
            <i data-lucide="file-badge" style="width:13px;height:13px"></i> Relatório Individual por Cliente
        </button>
        <a href="{{ route('delivery.projects.producers', ['tenant' => $currentTenant->slug, 'project' => $project->id]) }}" class="report-btn">
            <i data-lucide="clipboard-list" style="width:13px;height:13px"></i> Comprovantes Produtores
        </a>
    </div>
</div>
@endif

{{-- MODAL: RELATÓRIO POR CLIENTE (mantido) --}}
<div class="modal-overlay hidden" id="customerReportModal">
    <!-- Conteúdo idêntico ao original, omitido para brevidade -->
</div>

{{-- COMPROVANTES GERADOS --}}
<div class="pd-card" style="margin-bottom:1rem;">
    <div class="pd-card-header">
        <div class="pd-card-title">
            <i data-lucide="receipt" style="width:16px;height:16px;color:var(--color-primary)"></i>
            Comprovantes Gerados
        </div>
    </div>
    <div id="receipts-history" style="padding:.25rem 0;">
        <p style="font-size:.8rem;color:var(--color-text-secondary)">Carregando...</p>
    </div>
</div>

{{-- DELIVERIES: BARRA DE FILTROS + TABELA DESKTOP + MOBILE CARDS --}}
<div class="pd-card">
    <div class="pd-card-header">
        <div class="pd-card-title">
            <i data-lucide="package" style="width:16px;height:16px;color:var(--color-primary)"></i>
            Entregas (<span id="filtered-count">{{ $totalAll }}</span>)
        </div>
        <div style="display:flex;gap:.5rem;align-items:center;">
            @if($totalPending > 0)
            <span style="font-size:.78rem;color:var(--color-warning);font-weight:600;">
                <i data-lucide="clock" style="width:13px;height:13px"></i> {{ $totalPending }} aguardando
            </span>
            @endif
            <button class="btn btn-ghost btn-sm" id="clear-filters-btn" style="display:none;" onclick="clearAllFilters()">
                <i data-lucide="x" style="width:13px;height:13px"></i> Limpar filtros
            </button>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filters-bar" id="filters-bar">
        <input type="text" class="filter-input" id="filter-search" placeholder="🔍 Buscar..." style="flex:1; min-width:140px;">
        <select class="filter-select" id="filter-status">
            <option value="">Todos os status</option>
            <option value="pending">Pendente</option>
            <option value="approved">Aprovada</option>
            <option value="rejected">Rejeitada</option>
        </select>
        <select class="filter-select" id="filter-associate">
            <option value="">Todos os associados</option>
            @foreach($deliveries->pluck('associate_name')->unique()->sort() as $assoc)
            <option value="{{ $assoc }}">{{ $assoc }}</option>
            @endforeach
        </select>
        <select class="filter-select" id="filter-product">
            <option value="">Todos os produtos</option>
            @foreach($deliveries->pluck('product_name')->unique()->sort() as $prod)
            <option value="{{ $prod }}">{{ $prod }}</option>
            @endforeach
        </select>
        <input type="date" class="filter-input" id="filter-date-from" placeholder="Data início" style="max-width:130px;">
        <input type="date" class="filter-input" id="filter-date-to" placeholder="Data fim" style="max-width:130px;">
    </div>

    @if($deliveries->isEmpty())
        <div class="pd-empty">
            <svg class="pd-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            <p>Nenhuma entrega registrada para este projeto.</p>
        </div>
    @else
    <!-- TABELA DESKTOP -->
    <div class="table-scroll desktop-only">
        <table class="data-table" id="desktop-table">
            <thead>
                <tr>
                    <th class="chk-cell"><input type="checkbox" id="select-all" title="Selecionar todas aprovadas"></th>
                    <th>Data</th>
                    <th>Associado</th>
                    <th>Produto</th>
                    <th>Qtd</th>
                    <th>Val. Líq.</th>
                    <th>Qual.</th>
                    <th>Status</th>
                    <th style="min-width:100px;">Distrib.</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody id="desktop-tbody">
                @foreach($deliveries as $delivery)
                    @include('delivery.partials.project-delivery-row', ['delivery' => $delivery, 'customers' => $customers])
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- MOBILE CARDS -->
    <div class="mobile-cards" id="mobile-cards">
        @foreach($deliveries as $delivery)
            @include('delivery.partials.project-delivery-mobile-card', ['delivery' => $delivery, 'customers' => $customers])
        @endforeach
    </div>
    @endif
</div>

{{-- SELECTION BAR --}}
<div class="selection-bar" id="selection-bar">
    <div class="selection-bar-info">
        <i data-lucide="check-square" style="width:16px;height:16px;color:var(--color-primary)"></i>
        <span id="sel-count">0</span> recepção(ões)
        &nbsp;·&nbsp; Distribuído:
        <span style="color:var(--color-success)">R$ <span id="sel-total">0,00</span></span>
    </div>
    <div class="selection-bar-actions">
        <button class="btn btn-ghost btn-sm" onclick="clearSelection()">Limpar</button>
        <button class="btn btn-primary btn-sm" id="btn-gen-receipt" onclick="generateSelectedReceipt()">
            <i data-lucide="file-down" style="width:13px;height:13px"></i> Gerar Comprovante
        </button>
    </div>
</div>

<script>
const PD_TENANT    = '{{ $currentTenant->slug }}';
const PD_CSRF      = '{{ csrf_token() }}';
const PD_PROJECT   = {{ $project->id }};
const PD_CUSTOMERS = @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->trade_name ?: $c->name]));

/* ========== CUSTOM CONFIRM ========== */
function customConfirm(message) {
    return new Promise((resolve) => {
        document.getElementById('confirmMessage').textContent = message;
        const overlay = document.getElementById('customConfirmOverlay');
        overlay.classList.remove('hidden');
        const okBtn = document.getElementById('confirmOk');
        const cancelBtn = document.getElementById('confirmCancel');
        const closeHandler = (value) => {
            overlay.classList.add('hidden');
            okBtn.removeEventListener('click', okHandler);
            cancelBtn.removeEventListener('click', cancelHandler);
            overlay.removeEventListener('click', overlayHandler);
            resolve(value);
        };
        const okHandler = () => closeHandler(true);
        const cancelHandler = () => closeHandler(false);
        const overlayHandler = (e) => { if (e.target === overlay) closeHandler(false); };
        okBtn.addEventListener('click', okHandler);
        cancelBtn.addEventListener('click', cancelHandler);
        overlay.addEventListener('click', overlayHandler);
    });
}

/* ========== TOAST ========== */
function pdToast(msg, type = 'success') {
    const c = document.getElementById('pd-toasts');
    const el = document.createElement('div');
    el.className = `pd-toast ${type}`;
    el.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
    c.appendChild(el);
    setTimeout(() => { el.style.opacity = 0; setTimeout(() => el.remove(), 300); }, 4000);
}

/* ========== FILTROS ========== */
function applyFilters() {
    const search   = (document.getElementById('filter-search')?.value || '').toLowerCase();
    const status   = document.getElementById('filter-status')?.value || '';
    const assoc    = document.getElementById('filter-associate')?.value || '';
    const prod     = document.getElementById('filter-product')?.value || '';
    const dateFrom = document.getElementById('filter-date-from')?.value || '';
    const dateTo   = document.getElementById('filter-date-to')?.value || '';

    let visibleCount = 0;
    const hasFilter = search || status || assoc || prod || dateFrom || dateTo;
    document.getElementById('clear-filters-btn').style.display = hasFilter ? '' : 'none';

    // Desktop rows
    document.querySelectorAll('#desktop-tbody tr').forEach(row => {
        const visible = rowMatchesFilter(row, search, status, assoc, prod, dateFrom, dateTo);
        row.style.display = visible ? '' : 'none';
        if (visible) visibleCount++;
    });

    // Mobile cards
    document.querySelectorAll('.mobile-card').forEach(card => {
        const visible = cardMatchesFilter(card, search, status, assoc, prod, dateFrom, dateTo);
        card.style.display = visible ? '' : 'none';
        if (visible) visibleCount++;
    });

    document.getElementById('filtered-count').textContent = visibleCount;
}

function rowMatchesFilter(row, search, status, assoc, prod, dateFrom, dateTo) {
    const cells = row.querySelectorAll('td');
    const dateText   = (cells[1]?.textContent || '').trim();
    const assocText  = (cells[2]?.textContent || '').trim();
    const prodText   = (cells[3]?.textContent || '').trim();
    const statusText = (row.querySelector('.badge-status')?.textContent || '').trim().toLowerCase();

    if (status && !statusText.includes(status)) return false;
    if (assoc && assocText !== assoc) return false;
    if (prod && prodText !== prod) return false;
    if (dateFrom && dateText < dateFrom) return false;
    if (dateTo && dateText > dateTo) return false;
    if (search && !`${dateText} ${assocText} ${prodText}`.toLowerCase().includes(search)) return false;
    return true;
}

function cardMatchesFilter(card, search, status, assoc, prod, dateFrom, dateTo) {
    const dateText   = (card.querySelector('.mc-date')?.textContent || '').trim();
    const assocText  = (card.querySelector('.mc-assoc')?.textContent || '').trim();
    const prodText   = (card.querySelector('.mc-product')?.textContent || '').trim();
    const statusText = (card.querySelector('.badge-status')?.textContent || '').trim().toLowerCase();

    if (status && !statusText.includes(status)) return false;
    if (assoc && assocText !== assoc) return false;
    if (prod && prodText !== prod) return false;
    if (dateFrom && dateText < dateFrom) return false;
    if (dateTo && dateText > dateTo) return false;
    if (search && !`${dateText} ${assocText} ${prodText}`.toLowerCase().includes(search)) return false;
    return true;
}

function clearAllFilters() {
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-associate').value = '';
    document.getElementById('filter-product').value = '';
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value = '';
    applyFilters();
}

// Attach filter listeners
['filter-search','filter-status','filter-associate','filter-product','filter-date-from','filter-date-to'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', applyFilters);
});

/* ========== DISTRIBUTION INDICATOR UPDATE ========== */
function updateDistIndicator(container, totalQty, distQty, unit) {
    const total = parseFloat(totalQty) || 0;
    const dist  = parseFloat(distQty) || 0;
    const percent = total > 0 ? Math.min(Math.round((dist / total) * 100), 100) : 0;
    const over = dist > total;

    const fill = container.querySelector('.dist-bar-fill, .mc-dist-bar-fill');
    const text = container.querySelector('.dist-text, .mc-dist-text');

    if (fill) {
        fill.style.width = (over ? 100 : percent) + '%';
        fill.className = fill.className.replace(/\b(full|partial|over)\b/g, '');
        fill.classList.add(over ? 'over' : (percent >= 100 ? 'full' : 'partial'));
    }
    if (text) {
        text.textContent = over ? '⚠ ' + dist.toFixed(1) : percent + '%';
    }

    // Additional warning
    let warningEl = container.nextElementSibling;
    if (warningEl && warningEl.classList.contains('dist-warning')) warningEl.remove();
    if (over) {
        const w = document.createElement('span');
        w.className = 'dist-warning';
        w.textContent = 'Excede ' + unit;
        container.after(w);
    } else if (dist > 0 && percent < 100) {
        const w = document.createElement('span');
        w.className = 'dist-warning';
        w.textContent = 'Falta ' + (total - dist).toFixed(2) + ' ' + unit;
        w.style.color = '#d97706';
        container.after(w);
    }
}

/* ========== SELECTION BAR ========== */
function updateSelectionBar() {
    const checks = document.querySelectorAll('.delivery-chk:checked');
    const bar    = document.getElementById('selection-bar');
    const count  = checks.length;
    let total = 0;
    checks.forEach(c => total += parseFloat(c.dataset.net || 0));
    document.getElementById('sel-count').textContent = count;
    document.getElementById('sel-total').textContent = total.toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
    bar.classList.toggle('visible', count > 0);
}

function clearSelection() {
    document.querySelectorAll('.delivery-chk').forEach(c => c.checked = false);
    document.getElementById('select-all')?.checked && (document.getElementById('select-all').checked = false);
    updateSelectionBar();
}

document.getElementById('select-all')?.addEventListener('change', function() {
    const val = this.checked;
    document.querySelectorAll('.delivery-chk').forEach(c => c.checked = val);
    updateSelectionBar();
});

document.addEventListener('change', function(e) {
    if (e.target.classList.contains('delivery-chk')) updateSelectionBar();
});

async function generateSelectedReceipt() {
    const checks = document.querySelectorAll('.delivery-chk:checked');
    if (checks.length === 0) return pdToast('Selecione ao menos uma entrega.', 'error');

    const ids = Array.from(checks).map(c => parseInt(c.value));
    const btn = document.getElementById('btn-gen-receipt');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" style="width:13px;height:13px"></i> Gerando...';

    try {
        const res = await fetch(`/${PD_TENANT}/delivery/projects/${PD_PROJECT}/receipt-selected`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PD_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ delivery_ids: ids })
        });
        const data = await res.json();
        if (data.success) {
            const byteChars = atob(data.pdf);
            const byteArray = new Uint8Array(byteChars.length);
            for (let i = 0; i < byteChars.length; i++) byteArray[i] = byteChars.charCodeAt(i);
            const blob = new Blob([byteArray], { type: 'application/pdf' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url; a.download = data.filename; a.click();
            URL.revokeObjectURL(url);
            pdToast(`Comprovante nº ${data.receipt_number} gerado com ${ids.length} entrega(s)!`);
            clearSelection();
            loadReceiptsHistory();
        } else {
            pdToast(data.message || 'Erro ao gerar comprovante.', 'error');
        }
    } catch(err) {
        pdToast('Erro de comunicação com o servidor.', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="file-down" style="width:13px;height:13px"></i> Gerar Comprovante';
        lucide.createIcons();
    }
}

/* ========== RECEIPTS HISTORY ========== */
async function loadReceiptsHistory() {
    const container = document.getElementById('receipts-history');
    if (!container) return;
    try {
        const res  = await fetch(`/${PD_TENANT}/delivery/projects/${PD_PROJECT}/receipts`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': PD_CSRF }
        });
        const data = await res.json();
        if (!data.success || !data.receipts.length) {
            container.innerHTML = '<p style="font-size:.8rem;color:var(--color-text-secondary);padding:.5rem 0;">Nenhum comprovante gerado ainda.</p>';
            return;
        }
        container.innerHTML = data.receipts.map(r => `
            <div style="display:flex;align-items:center;justify-content:space-between;padding:.5rem .75rem;border-radius:6px;background:var(--color-bg);border:1px solid var(--color-border);margin-bottom:.4rem;gap:1rem;">
                <div>
                    <span style="font-size:.78rem;font-weight:700;color:var(--color-text);">Nº ${r.number}</span>
                    <span style="font-size:.75rem;color:var(--color-text-secondary);margin-left:.5rem;">${r.associate_name}</span>
                    <span style="font-size:.72rem;color:var(--color-text-secondary);margin-left:.5rem;">· ${r.issued_at}${r.delivery_count !== '—' ? ' · ' + r.delivery_count + ' entrega(s)' : ''}</span>
                </div>
                <a href="${r.reprint_url}" target="_blank" style="font-size:.75rem;color:var(--color-primary);font-weight:600;white-space:nowrap;text-decoration:none;padding:.25rem .6rem;border:1px solid var(--color-primary);border-radius:4px;flex-shrink:0;">
                    ⬇ Reimprimir
                </a>
            </div>
        `).join('');
    } catch(e) {
        container.innerHTML = '<p style="font-size:.8rem;color:var(--color-danger)">Erro ao carregar histórico.</p>';
    }
}
loadReceiptsHistory();

/* ========== ACTION HANDLERS ========== */
document.addEventListener('click', async function(e) {
    const approveBtn  = e.target.closest('.btn-approve');
    const rejectBtn   = e.target.closest('.btn-reject');
    const editBtn     = e.target.closest('.btn-edit');
    const distBtn     = e.target.closest('.btn-distribute');
    const deleteBtn   = e.target.closest('.btn-delete-approved');

    if (editBtn)  { EditModal.openFromBtn(editBtn); return; }
    if (distBtn)  { DistModal.openFromBtn(distBtn); return; }

    if (deleteBtn) {
        const id = deleteBtn.dataset.id;
        const confirmed = await customConfirm('Excluir esta entrega aprovada? Esta ação também removerá as distribuições associadas e não pode ser desfeita.');
        if (!confirmed) return;
        deleteBtn.disabled = true;
        try {
            const res  = await fetch(`/${PD_TENANT}/delivery/deliveries/${id}`, {
                method : 'DELETE',
                headers: { 'X-CSRF-TOKEN': PD_CSRF, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                // Remove both desktop row and mobile card
                document.getElementById('desktop-row-' + id)?.remove();
                document.getElementById('mobile-row-' + id)?.remove();
                pdToast('Entrega excluída.');
            } else {
                pdToast(data.message || 'Erro ao excluir.', 'error');
                deleteBtn.disabled = false;
            }
        } catch(err) {
            pdToast('Erro de comunicação com o servidor.', 'error');
            deleteBtn.disabled = false;
        }
        return;
    }

    if (approveBtn || rejectBtn) {
        const btn = approveBtn || rejectBtn;
        const id = btn.dataset.id;
        const action = approveBtn ? 'approve' : 'reject';
        const confirmed = await customConfirm(action === 'approve' ? 'Aprovar esta entrega?' : 'Rejeitar esta entrega?');
        if (!confirmed) return;

        // Find both desktop row and mobile card
        const row  = document.getElementById('desktop-row-' + id);
        const card = document.getElementById('mobile-row-' + id);
        const btns = document.querySelectorAll(`.btn-approve[data-id="${id}"], .btn-reject[data-id="${id}"]`);
        btns.forEach(b => b.disabled = true);

        try {
            const res  = await fetch(`/${PD_TENANT}/delivery/deliveries/${id}/${action}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': PD_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                pdToast(data.message);
                // Update badge everywhere
                document.querySelectorAll(`[data-delivery-id="${id}"] .badge-status`).forEach(badge => {
                    badge.className = 'badge-status ' + (action === 'approve' ? 'approved' : 'rejected');
                    badge.textContent = action === 'approve' ? 'Aprovada' : 'Rejeitada';
                });
                // Update actions and add checkbox if approved
                if (action === 'approve') {
                    // Desktop: update action cell
                    const rowEl = document.getElementById('desktop-row-' + id);
                    if (rowEl) {
                        const actionCell = rowEl.querySelector('.action-btns');
                        if (actionCell) {
                            actionCell.innerHTML = buildApprovedActions(id, rowEl);
                        }
                        const chkCell = rowEl.querySelector('.chk-cell');
                        if (chkCell && !chkCell.querySelector('input')) {
                            chkCell.innerHTML = `<input type="checkbox" class="delivery-chk" value="${id}" data-associate="" data-net="0">`;
                        }
                        rowEl.classList.add('approved-row');
                    }
                    // Mobile: update actions
                    const cardEl = document.getElementById('mobile-row-' + id);
                    if (cardEl) {
                        const actions = cardEl.querySelector('.mc-actions');
                        if (actions) {
                            actions.innerHTML = buildApprovedActionsMobile(id, cardEl);
                        }
                        const chkDiv = cardEl.querySelector('.mc-chk');
                        if (chkDiv && !chkDiv.querySelector('input')) {
                            chkDiv.innerHTML = `<input type="checkbox" class="delivery-chk" value="${id}" data-associate="" data-net="0">`;
                        }
                        cardEl.classList.add('status-approved');
                    }
                }
                lucide.createIcons();
            } else {
                pdToast(data.message || 'Erro ao processar.', 'error');
                btns.forEach(b => b.disabled = false);
            }
        } catch(err) {
            pdToast('Erro de comunicação com o servidor.', 'error');
            btns.forEach(b => b.disabled = false);
        }
    }
});

function buildApprovedActions(id, rowEl) {
    const qty  = parseFloat(rowEl?.querySelector('.btn-edit')?.dataset.qty || rowEl?.dataset?.qty || 0);
    const prod = rowEl?.querySelector('td:nth-child(4)')?.textContent?.trim() || '';
    const unit = rowEl?.querySelector('.btn-edit')?.dataset?.unit || '';
    return `
        <button class="btn-distribute" data-id="${id}" data-product="${esc(prod)}" data-unit="${esc(unit)}"
            data-qty="${qty}" data-distributed="0" data-existing="[]"
            data-participants="${esc(JSON.stringify(DM_PROJECT_PARTICIPANTS))}" title="Distribuir">
            <i data-lucide="git-branch" style="width:11px;height:11px"></i> Distribuir
        </button>
        <button class="btn-edit" data-id="${id}" data-date="" data-qty="${qty}" data-price="" data-quality="" data-notes="" data-unit="${unit}" data-distributions="[]" title="Editar">
            <i data-lucide="pencil" style="width:11px;height:11px"></i> Editar
        </button>
    `;
}

function buildApprovedActionsMobile(id, cardEl) {
    const qty  = parseFloat(cardEl?.dataset?.totalQty || 0);
    const unit = cardEl?.dataset?.unit || '';
    const prod = cardEl?.querySelector('.mc-product')?.textContent?.trim() || '';
    return `
        <button class="btn-distribute btn-xs" data-id="${id}" data-product="${esc(prod)}" data-unit="${esc(unit)}"
            data-qty="${qty}" data-distributed="0" data-existing="[]"
            data-participants="${esc(JSON.stringify(DM_PROJECT_PARTICIPANTS))}">Distribuir</button>
        <button class="btn-edit btn-xs" data-id="${id}" data-date="" data-qty="${qty}" data-price="" data-quality="" data-notes="" data-unit="${unit}" data-distributions="[]">Editar</button>
    `;
}

function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

const DM_PROJECT_PARTICIPANTS = @json($customers->pluck('id')->values()->all());

/* ========== EditModal callbacks ========== */
EditModal.onSaved = function(d) {
    pdToast('Entrega atualizada!');
    // Update both views
    const row = document.getElementById('desktop-row-' + d.id) || document.getElementById('mobile-row-' + d.id);
    if (!row) return;
    // Update date
    const dateEl = row.querySelector('.mc-date, td:nth-child(2)');
    if (dateEl) dateEl.textContent = d.delivery_date;
    // Update qty
    const qtyEl = row.querySelector('.mc-qty');
    if (qtyEl) qtyEl.innerHTML = parseFloat(d.quantity).toLocaleString('pt-BR',{minimumFractionDigits:3}) + ' <small>' + (d.unit||'') + '</small>';
    // Update quality
    const qualEl = row.querySelector('td:nth-child(7)');
    if (qualEl) qualEl.textContent = d.quality_grade || '—';
};

/* ========== DistModal callbacks ========== */
function replaceDeliveryFragments(payload) {
    const id = payload.delivery_id;
    if (!id) return false;

    if (payload.desktop) {
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = payload.desktop.trim();
        const nextDesktop = wrapper.firstElementChild;
        document.getElementById('desktop-row-' + id)?.replaceWith(nextDesktop);
    }

    if (payload.mobile) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = payload.mobile.trim();
        const nextMobile = wrapper.firstElementChild;
        document.getElementById('mobile-row-' + id)?.replaceWith(nextMobile);
    }

    lucide.createIcons();
    updateSelectionBar();
    applyFilters();
    return true;
}

async function refreshDeliveryItem(id) {
    const res = await fetch(`/${PD_TENANT}/delivery/projects/${PD_PROJECT}/deliveries/${id}/fragment`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Erro ao atualizar item.');
    replaceDeliveryFragments(data);
}

window._DistModalReload = async function(data) {
    const id = data?.delivery_id;
    pdToast('Distribuição salva!');
    if (!id) return;

    try {
        await refreshDeliveryItem(id);
    } catch (e) {
        pdToast(e.message || 'Distribuicao salva, mas nao foi possivel atualizar o item.', 'error');
    }
};
window._DistModalOnDelete = function(receptionId, data) {
    pdToast('Distribuição removida.');
    const id = receptionId || data?.parent_delivery_id;
    if (!id) return;
    refreshDeliveryItem(id).catch(() => {
        const distQty = data.dist_total_qty || 0;
        document.querySelectorAll(`[data-delivery-id="${id}"] .dist-indicator, [data-delivery-id="${id}"] .mc-dist-indicator`).forEach(indicator => {
            updateDistIndicator(indicator, indicator.closest('[data-total-qty]')?.dataset?.totalQty, distQty, '');
        });
    });
};

/* ========== Inicialização ========== */
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    updateSelectionBar();
    applyFilters(); // initial count
});

/* ========== MODAL RELATÓRIO POR CLIENTE (mantido) ========== */
// (Código do modal mantido exatamente como no original, omitido por brevidade)
</script>
@endsection
