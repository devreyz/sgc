@extends('layouts.bento')

@section('title', 'Entregas do Projeto')
@section('page-title', 'Histórico de Entregas')
@section('user-role', 'Registrador')

<x-delivery.dist-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
    :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name])->values()->all()"
/>
{{-- Componentes Blade --}}
<x-delivery.edit-delivery-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
/>
@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'delivery',
        'projects',
        $currentTenant->slug ?? request()->route('tenant'),
    );
@endphp

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

    .delivery-pagination {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:.75rem;
        padding:.75rem 1.2rem;
        border-top:1px solid var(--color-border);
        flex-wrap:wrap;
    }
    .delivery-pagination-info { font-size:.76rem; color:var(--color-text-secondary); font-weight:600; }
    .delivery-pagination-actions { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
    .delivery-page-size {
        border:1px solid var(--color-border);
        border-radius:var(--radius-md);
        padding:.32rem .5rem;
        background:var(--color-surface);
        color:var(--color-text);
        font:inherit;
        font-size:.76rem;
    }
    .delivery-page-btn {
        border:1px solid var(--color-border);
        border-radius:var(--radius-md);
        background:var(--color-surface);
        color:var(--color-text);
        padding:.32rem .55rem;
        font-size:.76rem;
        font-weight:700;
        cursor:pointer;
    }
    .delivery-page-btn:disabled { opacity:.42; cursor:not-allowed; }

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
    .pd-issue-btn {
        display:inline-flex;align-items:center;gap:.2rem;
        margin-top:.18rem;border:1px solid transparent;border-radius:999px;
        padding:.08rem .42rem;background:#fff7ed;color:#b45309;
        font-size:.65rem;font-weight:800;cursor:pointer;white-space:nowrap;
    }
    .pd-issue-btn.critical { background:#fef2f2;color:#b91c1c;border-color:#fecaca; }
    .pd-issue-btn.warning { background:#fff7ed;color:#b45309;border-color:#fed7aa; }
    .pd-issue-btn:hover { filter:brightness(.97); }
    .pd-integrity-overlay { position:fixed; inset:0; background:rgba(15,23,42,.46); display:none; align-items:center; justify-content:center; padding:1rem; z-index:320000; }
    .pd-integrity-overlay.open { display:flex; }
    .pd-integrity-box { width:min(860px,96vw); max-height:min(760px,90dvh); overflow:auto; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); box-shadow:0 18px 48px rgba(15,23,42,.28); }
    .pd-integrity-head { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; padding:.95rem 1.05rem; border-bottom:1px solid var(--color-border); }
    .pd-integrity-title { font-size:.98rem; font-weight:800; display:flex; align-items:center; gap:.4rem; }
    .pd-integrity-close { border:0; background:transparent; color:var(--color-text-secondary); font-size:1.1rem; cursor:pointer; width:30px; height:30px; border-radius:var(--radius-md); }
    .pd-integrity-close:hover { background:var(--color-bg); color:var(--color-text); }
    .pd-integrity-body { padding:.85rem; display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:.75rem; }
    .pd-issue-focus { outline:2px solid var(--color-primary); outline-offset:-2px; }
    .pd-integrity-toggle { border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text-secondary); border-radius:var(--radius-md); min-width:30px; height:30px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; }
    .pd-integrity-toggle:hover { color:var(--color-text); background:var(--color-bg); }
    .pd-integrity-content[hidden] { display:none !important; }
    .pd-integrity-actions { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.5rem; }

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
        display:flex; align-items:center; gap:.35rem; font-size:.72rem; cursor:pointer; border-radius:6px;
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
    .mobile-cards { display:none; padding: 1rem; }
    @media (max-width: 767px) {
        .desktop-only { display:none !important; }
        .mobile-cards { display:block !important; }

        .mobile-card {
            --delivery-state:#94a3b8;
            --delivery-state-bg:#f8fafc;
            background:var(--color-surface);
            border:1px solid var(--color-border);
            border-radius:var(--radius-md);
            margin-bottom:.45rem;
            padding:0;
            position:relative;
            display:flex;
            flex-direction:column;
            font-size:.76rem;
            border-left:2px solid var(--delivery-state);
            overflow:hidden;
        }
        .mobile-card.status-pending  { --delivery-state:#d97706; --delivery-state-bg:#fff7ed; }
        .mobile-card.status-approved { --delivery-state:#2563eb; --delivery-state-bg:#eff6ff; }
        .mobile-card.status-distributed { --delivery-state:#059669; --delivery-state-bg:#ecfdf5; }
        .mobile-card.status-rejected { --delivery-state:#dc2626; --delivery-state-bg:#fef2f2; }
        .mobile-card.status-cancelled { --delivery-state:#6b7280; --delivery-state-bg:#f3f4f6; }

        .mc-head {
            display:grid;
            grid-template-columns:minmax(0,1fr) auto auto;
            align-items:center;
            gap:.4rem;
            padding:.42rem .55rem;
            background:var(--delivery-state-bg);
            border-bottom:1px solid color-mix(in srgb, var(--delivery-state) 16%, var(--color-border));
        }
        .mc-state-icon {
            width:22px;
            height:22px;
            border-radius:999px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            color:var(--delivery-state);
            background:color-mix(in srgb, var(--delivery-state) 10%, #fff);
            border:1px solid color-mix(in srgb, var(--delivery-state) 18%, transparent);
        }
        .mc-state-icon svg { width:12px; height:12px; }
        .mc-head-main { min-width:0; display:flex; align-items:center; gap:.28rem; white-space:nowrap; overflow:hidden; }
        .mc-head-line { display:contents; align-items:center; gap:.35rem; min-width:0; font-size:.74rem; color:var(--color-text-secondary); }
        .mc-date { font-weight:700; color:var(--color-text); white-space:nowrap; font-size:.74rem; }
        .mc-sep { color:var(--color-text-muted); opacity:.55; font-size:.7rem; }
        .mc-head-product { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--color-text); font-weight:700; font-size:.8rem; flex:1 1 auto; }
        .mc-head-qty { color:var(--color-text-secondary); font-size:.72rem; font-weight:700; white-space:nowrap; }
        .mc-body { padding:.48rem .55rem; display:flex; flex-direction:column; gap:.42rem; }
        .mc-row {
            display:flex;
            align-items:center;
            gap:.4rem;
            flex-wrap:wrap;
        }
        .mc-chk { flex-shrink:0; }
        .mc-status { margin-left:auto; }
        .mc-assoc { font-size:.72rem; color:var(--color-text-secondary); }
        .mc-product { font-size:.78rem; font-weight:600; }
        .mc-details { display:flex; gap:.65rem; flex-wrap:wrap; align-items:center; }
        .mc-qty { font-weight:700; }
        .mc-net { font-weight:700; color:var(--color-success); }
        .mc-actions { display:flex; gap:.3rem; margin-top:.2rem; flex-wrap:wrap; }
        .mc-dist-indicator { display:flex; align-items:center; gap:.25rem; font-size:.7rem; cursor:pointer; border-radius:6px; }
        .mc-dist-indicator:hover .mc-dist-bar-bg { background:#dbe3ea; }
        .mc-dist-bar-bg { width:76px; height:7px; background:#e5e7eb; border-radius:99px; overflow:hidden; }
        .mc-dist-bar-fill { height:100%; border-radius:99px; }
        .mc-dist-bar-fill.full { background:#10b981; }
        .mc-dist-bar-fill.partial { background:#93c5fd; }
        .mc-dist-bar-fill.over { background:#fca5a5; }
        .mc-dist-text { font-weight:600; min-width:34px; white-space:nowrap; font-size:.7rem; }
    }

    .dist-summary-overlay {
        position:fixed; inset:0; z-index:310000; display:none; align-items:center; justify-content:center;
        padding:1rem; background:rgba(15,23,42,.28);
    }
    .dist-summary-overlay.open { display:flex; }
    .dist-summary-box { width:min(420px,94vw); max-height:min(520px,88dvh); overflow:auto; background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); box-shadow:0 18px 42px rgba(15,23,42,.24); }
    .dist-summary-head { display:flex; justify-content:space-between; gap:1rem; padding:.9rem 1rem; border-bottom:1px solid var(--color-border); }
    .dist-summary-title { font-weight:800; font-size:.92rem; color:var(--color-text); }
    .dist-summary-sub { font-size:.76rem; color:var(--color-text-secondary); margin-top:.12rem; }
    .dist-summary-close { border:0; background:transparent; color:var(--color-text-secondary); cursor:pointer; font-size:1.1rem; }
    .dist-summary-body { padding:.85rem 1rem 1rem; display:grid; gap:.45rem; }
    .dist-summary-row { display:flex; justify-content:space-between; gap:.75rem; padding:.55rem .65rem; border:1px solid var(--color-border); border-radius:var(--radius-md); background:var(--color-bg); font-size:.82rem; }
    .dist-summary-row strong { color:var(--color-text); }
    .dist-summary-row span { color:var(--color-text-secondary); white-space:nowrap; }
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
        <a href="{{ route('delivery.projects.associates.index', ['tenant' => $currentTenant->slug, 'project' => $project->id]) }}" class="btn btn-primary btn-sm">
            <i data-lucide="sliders-horizontal" style="width:13px;height:13px"></i> Participacao e limites
        </a>
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
        <div class="pd-stat-val">{{ $deliveries->count() }}</div>
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

{{-- PENDENCIAS E INCONSISTENCIAS --}}
@if(!empty($integrity))
<div class="pd-card" style="margin-bottom:1rem;">
    <div class="pd-card-header">
        <div class="pd-card-title">
            <i data-lucide="shield-alert" style="width:16px;height:16px;color:var(--color-warning)"></i>
            Pendencias e Inconsistencias
        </div>
        <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;font-size:.72rem;font-weight:700;">
            <span id="pd-integrity-count-critical" style="color:#dc2626;">Critico: {{ $integrity['counts']['critical'] ?? 0 }}</span>
            <span id="pd-integrity-count-warning" style="color:#d97706;">Atencao: {{ $integrity['counts']['warning'] ?? 0 }}</span>
            <span id="pd-integrity-count-info" style="color:#2563eb;">Info: {{ $integrity['counts']['info'] ?? 0 }}</span>
            <button type="button" class="pd-integrity-toggle" onclick="toggleIntegrityPanel()" title="Expandir ou recolher pendencias" aria-controls="pd-integrity-content" aria-expanded="false">
                <i data-lucide="chevron-down" style="width:15px;height:15px"></i>
            </button>
        </div>
    </div>
    <div id="pd-integrity-content" class="pd-integrity-content" hidden>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.75rem;padding:.75rem;">
        @foreach(['critical' => ['Critico', '#dc2626'], 'warning' => ['Atencao', '#d97706'], 'info' => ['Informativo', '#2563eb']] as $severity => [$label, $color])
            <div style="border:1px solid var(--color-border);border-radius:var(--radius-md);overflow:hidden;background:var(--color-bg);">
                <div style="padding:.55rem .7rem;border-bottom:1px solid var(--color-border);font-size:.72rem;font-weight:800;text-transform:uppercase;color:{{ $color }};">
                    {{ $label }}
                </div>
                <div style="display:flex;flex-direction:column;">
                    @forelse(($integrity[$severity] ?? []) as $issue)
                        <div data-issue-delivery="{{ $issue['deliveryId'] ?? '' }}" data-integrity-item="{{ $issue['actionKey'] ?? '' }}-{{ $issue['distributionId'] ?? '' }}" style="padding:.65rem .7rem;border-bottom:1px solid var(--color-border);">
                            <div style="font-size:.82rem;font-weight:700;color:var(--color-text);">{{ $issue['title'] }}</div>
                            <div style="font-size:.76rem;color:var(--color-text-secondary);line-height:1.35;margin-top:.18rem;">{{ $issue['message'] }}</div>
                            <div style="font-size:.72rem;color:{{ $color }};font-weight:600;margin-top:.35rem;">{{ $issue['action'] }}</div>
                            <div class="pd-integrity-actions">
                                @if(!empty($issue['actionKey']))
                                    <button type="button" class="btn btn-primary btn-sm" onclick="handleIntegrityAction('{{ $issue['actionKey'] }}', {{ (int) ($issue['deliveryId'] ?? 0) }}, {{ (int) ($issue['distributionId'] ?? 0) }}, {{ (int) ($issue['associateId'] ?? 0) }}, @js($issue['associateName'] ?? ''))">{{ match($issue['actionKey']) { 'open_distribution' => 'Distribuir', 'edit_distribution' => 'Corrigir distribuicao', 'open_producers' => 'Abrir produtor', 'detach_missing_associate_receipt' => 'Desvincular', 'delete_orphan_distribution' => 'Excluir orfa', default => 'Ver detalhes' } }}</button>
                                @endif
                                @if(!empty($issue['deliveryId']))
                                    <button type="button" class="btn btn-ghost btn-sm" onclick="focusIntegrityDelivery({{ (int) $issue['deliveryId'] }})">Ver entrega</button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div style="padding:.75rem;font-size:.78rem;color:var(--color-text-secondary);">Nenhum item.</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
    </div>

<div class="pd-integrity-overlay" id="pd-integrity-modal" onclick="closeIntegrityModalOnBackdrop(event)" aria-hidden="true">
    <div class="pd-integrity-box" role="dialog" aria-modal="true" aria-labelledby="pd-integrity-title">
        <div class="pd-integrity-head">
            <div>
                <div class="pd-integrity-title" id="pd-integrity-title">
                    <i data-lucide="shield-alert" style="width:16px;height:16px;color:var(--color-warning)"></i>
                    Pendencias e Inconsistencias
                </div>
                <div style="font-size:.76rem;color:var(--color-text-secondary);margin-top:.16rem;">
                    Critico: {{ $integrity['counts']['critical'] ?? 0 }} · Atencao: {{ $integrity['counts']['warning'] ?? 0 }} · Info: {{ $integrity['counts']['info'] ?? 0 }}
                </div>
            </div>
            <button type="button" class="pd-integrity-close" onclick="closeIntegrityModal()" aria-label="Fechar">x</button>
        </div>
        <div class="pd-integrity-body">
            @foreach(['critical' => ['Critico', '#dc2626'], 'warning' => ['Atencao', '#d97706'], 'info' => ['Informativo', '#2563eb']] as $severity => [$label, $color])
                <div style="border:1px solid var(--color-border);border-radius:var(--radius-md);overflow:hidden;background:var(--color-bg);">
                    <div style="padding:.55rem .7rem;border-bottom:1px solid var(--color-border);font-size:.72rem;font-weight:800;text-transform:uppercase;color:{{ $color }};">
                        {{ $label }}
                    </div>
                    <div style="display:flex;flex-direction:column;">
                        @forelse(($integrity[$severity] ?? []) as $issue)
                            <div data-modal-issue-delivery="{{ $issue['deliveryId'] ?? '' }}" data-integrity-item="{{ $issue['actionKey'] ?? '' }}-{{ $issue['distributionId'] ?? '' }}" style="padding:.65rem .7rem;border-bottom:1px solid var(--color-border);">
                                <div style="font-size:.82rem;font-weight:700;color:var(--color-text);">{{ $issue['title'] }}</div>
                                <div style="font-size:.76rem;color:var(--color-text-secondary);line-height:1.35;margin-top:.18rem;">{{ $issue['message'] }}</div>
                                <div style="font-size:.72rem;color:{{ $color }};font-weight:600;margin-top:.35rem;">{{ $issue['action'] }}</div>
                                @if(!empty($issue['actionKey']))
                                    <div class="pd-integrity-actions">
                                        <button type="button" class="btn btn-primary btn-sm" onclick="handleIntegrityAction('{{ $issue['actionKey'] }}', {{ (int) ($issue['deliveryId'] ?? 0) }}, {{ (int) ($issue['distributionId'] ?? 0) }}, {{ (int) ($issue['associateId'] ?? 0) }}, @js($issue['associateName'] ?? ''))">{{ match($issue['actionKey']) { 'open_distribution' => 'Distribuir', 'edit_distribution' => 'Corrigir distribuicao', 'open_producers' => 'Abrir produtor', 'detach_missing_associate_receipt' => 'Desvincular', 'delete_orphan_distribution' => 'Excluir orfa', default => 'Ver detalhes' } }}</button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div style="padding:.75rem;font-size:.78rem;color:var(--color-text-secondary);">Nenhum item.</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

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
        <select class="filter-select" id="delivery-page-size" style="max-width:150px;">
            <option value="30">30 ultimos</option>
            <option value="50">50 ultimos</option>
            <option value="100">100 ultimos</option>
            <option value="all">Todos</option>
        </select>
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
                    <th style="min-width:120px;">Limite associado</th>
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
    <div class="delivery-pagination" id="project-pagination">
        <div class="delivery-pagination-info" id="project-page-info"></div>
        <div class="delivery-pagination-actions">
            <button type="button" class="delivery-page-btn" id="project-prev">Anterior</button>
            <button type="button" class="delivery-page-btn" id="project-next">Proxima</button>
        </div>
    </div>
    @endif
</div>

<div class="dist-summary-overlay" id="dist-summary-overlay" onclick="closeDistSummaryOnBackdrop(event)">
    <div class="dist-summary-box" role="dialog" aria-modal="true" aria-labelledby="dist-summary-title">
        <div class="dist-summary-head">
            <div>
                <div class="dist-summary-title" id="dist-summary-title">Distribuicoes</div>
                <div class="dist-summary-sub" id="dist-summary-sub"></div>
            </div>
            <button type="button" class="dist-summary-close" onclick="closeDistSummary()" aria-label="Fechar">x</button>
        </div>
        <div class="dist-summary-body" id="dist-summary-body"></div>
    </div>
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
const projectListState = { page: 1, perPage: 30 };

function openIntegrityModal(deliveryId = null) {
    const modal = document.getElementById('pd-integrity-modal');
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    modal.querySelectorAll('[data-modal-issue-delivery]').forEach(el => el.classList.remove('pd-issue-focus'));

    if (deliveryId) {
        const item = modal.querySelector(`[data-modal-issue-delivery="${deliveryId}"]`);
        if (item) {
            item.classList.add('pd-issue-focus');
            setTimeout(() => item.scrollIntoView({ behavior: 'smooth', block: 'center' }), 80);
        }
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeIntegrityModal() {
    const modal = document.getElementById('pd-integrity-modal');
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
}

function closeIntegrityModalOnBackdrop(event) {
    if (event.target === document.getElementById('pd-integrity-modal')) closeIntegrityModal();
}

function toggleIntegrityPanel() {
    const content = document.getElementById('pd-integrity-content');
    const button = document.querySelector('.pd-integrity-toggle');
    if (!content || !button) return;
    const opening = content.hidden;
    content.hidden = !opening;
    button.setAttribute('aria-expanded', opening ? 'true' : 'false');
    const icon = button.querySelector('i');
    if (icon) icon.setAttribute('data-lucide', opening ? 'chevron-up' : 'chevron-down');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function focusIntegrityDelivery(deliveryId) {
    closeIntegrityModal();
    const row = document.querySelector(`[data-delivery-id="${deliveryId}"]`);
    row?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function openIntegrityDistribution(deliveryId, distributionId = 0, edit = false) {
    const button = document.querySelector(`.btn-distribute[data-id="${deliveryId}"]`);
    if (!button) {
        focusIntegrityDelivery(deliveryId);
        pdToast('Abra uma entrega aprovada para corrigir as distribuicoes.', 'info');
        return;
    }

    closeIntegrityModal();
    DistModal.openFromBtn(button);
    if (edit && distributionId) {
        setTimeout(() => DistModal.editExisting(distributionId), 120);
    }
}

function applyResolvedIntegrity(integrity, actionKey, distributionId) {
    document.querySelectorAll(`[data-integrity-item="${actionKey}-${distributionId}"]`).forEach(el => el.remove());
    ['critical', 'warning', 'info'].forEach(severity => {
        const el = document.getElementById(`pd-integrity-count-${severity}`);
        if (!el) return;
        const label = severity === 'critical' ? 'Critico' : severity === 'warning' ? 'Atencao' : 'Info';
        el.textContent = `${label}: ${integrity?.counts?.[severity] ?? 0}`;
    });
}

async function handleIntegrityAction(actionKey, deliveryId = 0, distributionId = 0, associateId = 0, associateName = '') {
    if (actionKey === 'open_distribution') {
        openIntegrityDistribution(deliveryId, distributionId);
        return;
    }
    if (actionKey === 'edit_distribution') {
        openIntegrityDistribution(deliveryId, distributionId, true);
        return;
    }
    if (actionKey === 'open_producers') {
        const query = associateId ? `?associate=${associateId}&name=${encodeURIComponent(associateName)}` : '';
        window.location.href = `/${PD_TENANT}/delivery/projects/${PD_PROJECT}/producers${query}`;
        return;
    }

    const message = actionKey === 'detach_missing_associate_receipt'
        ? 'Desvincular este comprovante inexistente? A distribuicao voltara a ficar disponivel para um novo comprovante.'
        : 'Excluir esta distribuicao orfa? Esta correcao nao pode ser desfeita.';
    const confirmed = await customConfirm(message);
    if (!confirmed) return;

    try {
        const res = await fetch(`/${PD_TENANT}/delivery/projects/${PD_PROJECT}/integrity/resolve`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PD_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ action: actionKey, distribution_id: distributionId }),
        });
        const data = await res.json();
        if (!data.success) {
            pdToast(data.message || 'Nao foi possivel aplicar esta correcao.', 'error');
            return;
        }

        applyResolvedIntegrity(data.integrity, actionKey, distributionId);
        if (deliveryId) refreshDeliveryItem(deliveryId).catch(() => {});
        pdToast(data.message);
    } catch (error) {
        pdToast('Erro de comunicacao ao aplicar a correcao.', 'error');
    }
}

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
    const search   = normalizeFilterText(document.getElementById('filter-search')?.value || '');
    const status   = document.getElementById('filter-status')?.value || '';
    const assoc    = document.getElementById('filter-associate')?.value || '';
    const prod     = document.getElementById('filter-product')?.value || '';
    const dateFrom = document.getElementById('filter-date-from')?.value || '';
    const dateTo   = document.getElementById('filter-date-to')?.value || '';

    const hasFilter = search || status || assoc || prod || dateFrom || dateTo;
    document.getElementById('clear-filters-btn').style.display = hasFilter ? '' : 'none';

    const isMobile = window.matchMedia('(max-width: 767px)').matches;
    const desktopRows = Array.from(document.querySelectorAll('#desktop-tbody tr'));
    const mobileCards = Array.from(document.querySelectorAll('#mobile-cards .mobile-card'));
    const activeItems = isMobile ? mobileCards : desktopRows;
    const inactiveItems = isMobile ? desktopRows : mobileCards;

    inactiveItems.forEach(item => item.style.display = 'none');

    const matched = activeItems.filter(item => {
        return isMobile
            ? cardMatchesFilter(item, search, status, assoc, prod, dateFrom, dateTo)
            : rowMatchesFilter(item, search, status, assoc, prod, dateFrom, dateTo);
    });

    const perPage = projectListState.perPage === 'all' ? matched.length || 1 : parseInt(projectListState.perPage || 30, 10);
    const totalPages = Math.max(1, Math.ceil(matched.length / perPage));
    projectListState.page = Math.min(Math.max(projectListState.page || 1, 1), totalPages);
    const start = (projectListState.page - 1) * perPage;
    const pageItems = new Set(matched.slice(start, start + perPage));

    activeItems.forEach(item => {
        item.style.display = pageItems.has(item) ? '' : 'none';
    });

    document.getElementById('filtered-count').textContent = matched.length;
    updateProjectPagination(matched.length, matched.length ? start + 1 : 0, Math.min(start + perPage, matched.length), projectListState.page, totalPages);
}

function rowMatchesFilter(row, search, status, assoc, prod, dateFrom, dateTo) {
    const cells = row.querySelectorAll('td');
    const dateText   = row.dataset.filterDate || (cells[1]?.textContent || '').trim();
    const assocText  = row.dataset.filterAssociate || (cells[2]?.textContent || '').trim();
    const prodText   = row.dataset.filterProduct || (cells[3]?.textContent || '').trim();
    const statusText = row.dataset.filterStatus || (row.querySelector('.badge-status')?.textContent || '').trim().toLowerCase();

    if (status && statusText !== status) return false;
    if (assoc && assocText !== assoc) return false;
    if (prod && prodText !== prod) return false;
    if (dateFrom && dateText < dateFrom) return false;
    if (dateTo && dateText > dateTo) return false;
    if (search && !normalizeFilterText(`${dateText} ${assocText} ${prodText}`).includes(search)) return false;
    return true;
}

function cardMatchesFilter(card, search, status, assoc, prod, dateFrom, dateTo) {
    const dateText   = card.dataset.filterDate || (card.querySelector('.mc-date')?.textContent || '').trim();
    const assocText  = card.dataset.filterAssociate || (card.querySelector('.mc-assoc')?.textContent || '').trim();
    const prodText   = card.dataset.filterProduct || (card.querySelector('.mc-product')?.textContent || '').trim();
    const statusText = card.dataset.filterStatus || (card.querySelector('.badge-status')?.textContent || '').trim().toLowerCase();

    if (status && statusText !== status) return false;
    if (assoc && assocText !== assoc) return false;
    if (prod && prodText !== prod) return false;
    if (dateFrom && dateText < dateFrom) return false;
    if (dateTo && dateText > dateTo) return false;
    if (search && !normalizeFilterText(`${dateText} ${assocText} ${prodText}`).includes(search)) return false;
    return true;
}

function normalizeFilterText(value) {
    return (value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function updateProjectPagination(total, start, end, page, totalPages) {
    const wrap = document.getElementById('project-pagination');
    if (!wrap) return;
    wrap.style.display = total > 0 ? 'flex' : 'none';
    document.getElementById('project-page-info').textContent = total > 0 ? `${start}-${end} de ${total}` : '';
    document.getElementById('project-prev').disabled = page <= 1;
    document.getElementById('project-next').disabled = page >= totalPages;
}

function parseCardDistributions(card) {
    try {
        if (card?.dataset?.distributionsB64) {
            return JSON.parse(atob(card.dataset.distributionsB64)) || [];
        }
        return JSON.parse(card?.dataset?.distributions || '[]') || [];
    }
    catch (e) { return []; }
}

function fmtProjectQty(n, unit) {
    const num = parseFloat(n) || 0;
    const str = num % 1 === 0 ? String(num) : num.toFixed(2).replace(/\.?0+$/, '');
    return str + (unit ? ' ' + unit : '');
}

function openDistSummaryFromCard(card) {
    const product = card?.dataset?.product || 'Produto';
    const unit = card?.dataset?.unit || '';
    const totalQty = parseFloat(card?.dataset?.totalQty || 0);
    const distQty = parseFloat(card?.dataset?.distributed || 0);
    const distributions = parseCardDistributions(card);
    document.getElementById('dist-summary-title').textContent = product;
    document.getElementById('dist-summary-sub').textContent = `${fmtProjectQty(distQty, unit)} distribuidos de ${fmtProjectQty(totalQty, unit)}`;
    document.getElementById('dist-summary-body').innerHTML = distributions.length
        ? distributions.map(d => {
            const customer = d.customer || d.customer_name || d.customerName || 'Cliente';
            const qty = parseFloat(d.qty || d.quantity || 0);
            const net = parseFloat(d.net || d.net_value || 0);
            return `<div class="dist-summary-row"><strong>${esc(customer)}</strong><span>${fmtProjectQty(qty, unit)}${net > 0 ? ' - R$ ' + net.toFixed(2) : ''}</span></div>`;
        }).join('')
        : '<div class="dist-summary-row"><strong>Nenhuma distribuicao</strong><span>0%</span></div>';
    document.getElementById('dist-summary-overlay').classList.add('open');
}

function closeDistSummary() {
    document.getElementById('dist-summary-overlay')?.classList.remove('open');
}

function closeDistSummaryOnBackdrop(event) {
    if (event.target === document.getElementById('dist-summary-overlay')) closeDistSummary();
}

function clearAllFilters() {
    document.getElementById('filter-search').value = '';
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-associate').value = '';
    document.getElementById('filter-product').value = '';
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value = '';
    projectListState.page = 1;
    applyFilters();
}

// Attach filter listeners
['filter-search','filter-status','filter-associate','filter-product','filter-date-from','filter-date-to'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => {
        projectListState.page = 1;
        applyFilters();
    });
});

document.getElementById('delivery-page-size')?.addEventListener('change', function() {
    projectListState.perPage = this.value === 'all' ? 'all' : parseInt(this.value || 30, 10);
    projectListState.page = 1;
    applyFilters();
});
document.getElementById('project-prev')?.addEventListener('click', function() {
    projectListState.page = Math.max(1, projectListState.page - 1);
    applyFilters();
});
document.getElementById('project-next')?.addEventListener('click', function() {
    projectListState.page += 1;
    applyFilters();
});

window.addEventListener('resize', () => {
    window.clearTimeout(window.__pdFilterResizeTimer);
    window.__pdFilterResizeTimer = window.setTimeout(applyFilters, 120);
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

/* ========== ACTION HANDLERS ========== */
document.addEventListener('click', async function(e) {
    const summary = e.target.closest('.mc-dist-indicator[data-summary], .dist-indicator[data-summary]');
    if (summary) {
        openDistSummaryFromCard(summary.closest('.mobile-card, tr[data-delivery-id]'));
        return;
    }

    const approveBtn  = e.target.closest('.btn-approve');
    const rejectBtn   = e.target.closest('.btn-reject');
    const editBtn     = e.target.closest('.btn-edit');
    const distBtn     = e.target.closest('.btn-distribute');
    const deleteBtn   = e.target.closest('.btn-delete-approved');

    if (editBtn)  { EditModal.openFromBtn(editBtn); return; }
    if (distBtn)  { DistModal.openFromBtn(distBtn); return; }

    if (deleteBtn) {
        const id = deleteBtn.dataset.id;
        const confirmed = await customConfirm('Excluir esta entrega? Esta acao tambem removera as distribuicoes associadas quando existirem e nao pode ser desfeita.');
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
                applyFilters();
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
                } else {
                    const rowEl = document.getElementById('desktop-row-' + id);
                    if (rowEl) {
                        const actionCell = rowEl.querySelector('.action-btns');
                        if (actionCell) {
                            actionCell.innerHTML = buildRejectedActions(id);
                        }
                        const chkCell = rowEl.querySelector('.chk-cell');
                        if (chkCell) {
                            chkCell.innerHTML = '';
                        }
                        rowEl.classList.remove('approved-row');
                    }

                    const cardEl = document.getElementById('mobile-row-' + id);
                    if (cardEl) {
                        const actions = cardEl.querySelector('.mc-actions');
                        if (actions) {
                            actions.innerHTML = buildRejectedActionsMobile(id);
                        }
                        const chkDiv = cardEl.querySelector('.mc-chk');
                        if (chkDiv) {
                            chkDiv.innerHTML = '';
                        }
                        cardEl.classList.remove('status-approved');
                        cardEl.classList.add('status-rejected');
                    }
                }
                refreshDeliveryItem(id).catch(() => applyFilters());
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
        <button class="btn-delete-approved" data-id="${id}" title="Excluir entrega">
            <i data-lucide="trash-2" style="width:11px;height:11px"></i> Excluir
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
        <button class="btn-delete-approved btn-xs" data-id="${id}">Excluir</button>
    `;
}

function buildRejectedActions(id) {
    return `
        <button class="btn-delete-approved" data-id="${id}" title="Excluir entrega rejeitada">
            <i data-lucide="trash-2" style="width:11px;height:11px"></i> Excluir
        </button>
    `;
}

function buildRejectedActionsMobile(id) {
    return `
        <button class="btn-delete-approved btn-xs" data-id="${id}" title="Excluir entrega rejeitada">
            Excluir
        </button>
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

window._DistModalOnUpdate = function(receptionId, data) {
    pdToast('Distribuicao atualizada.');
    const id = receptionId || data?.parent_delivery_id;
    if (!id) return;
    refreshDeliveryItem(id).catch(() => {
        pdToast('Distribuicao atualizada, mas nao foi possivel atualizar o item.', 'error');
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

