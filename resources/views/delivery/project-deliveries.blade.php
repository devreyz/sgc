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
    /* ── Page header ── */
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
    .pd-header-info {}
    .pd-title { font-size:1.2rem; font-weight:700; margin:0 0 .2rem; display:flex; align-items:center; gap:.45rem; }
    .pd-sub { font-size:.82rem; color:var(--color-text-secondary); display:flex; align-items:center; gap:.3rem; }
    .pd-header-actions { display:flex; gap:.5rem; flex-wrap:wrap; align-items:flex-start; }

    /* ── Stats strip ── */
    .pd-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:.65rem; margin-bottom:1.25rem; }
    .pd-stat  { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:.75rem 1rem; text-align:center; }
    .pd-stat-lbl { font-size:.65rem; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-secondary); }
    .pd-stat-val { font-size:1.35rem; font-weight:800; }

    /* ── Table card ── */
    .pd-card { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); overflow:hidden; margin-bottom:1.25rem; }
    .pd-card-header { padding:.9rem 1.2rem; border-bottom:1px solid var(--color-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; }
    .pd-card-title  { font-size:.95rem; font-weight:700; display:flex; align-items:center; gap:.4rem; }
    .table-scroll   { overflow-x:auto; }
    .data-table     { width:100%; border-collapse:collapse; font-size:.84rem; }
    .data-table th  { background:var(--color-bg); padding:.6rem .8rem; text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--color-text-secondary); font-weight:600; border-bottom:2px solid var(--color-border); white-space:nowrap; }
    .data-table td  { padding:.6rem .8rem; border-bottom:1px solid var(--color-border); vertical-align:middle; }
    .data-table tr:hover td { background:rgba(0,0,0,.02); }
    .data-table tr.approved-row td { opacity:.75; }

    /* Badges */
    .badge-status { display:inline-flex; align-items:center; gap:.2rem; padding:.18rem .5rem; border-radius:99px; font-size:.68rem; font-weight:600; text-transform:uppercase; white-space:nowrap; }
    .badge-status.pending  { background:rgba(245,158,11,.14); color:#d97706; }
    .badge-status.approved { background:rgba(16,185,129,.14); color:#059669; }
    .badge-status.rejected { background:rgba(239,68,68,.14); color:#dc2626; }
    .badge-status.cancelled { background:rgba(107,114,128,.14); color:#6b7280; }

    /* Buttons */
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
    .btn-approve { background:rgba(16,185,129,.12); color:#059669; border-radius:var(--radius-md); border:none; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:.2rem; padding:.28rem .6rem; font-size:.75rem; transition:.15s; white-space:nowrap; }
    .btn-approve:hover:not(:disabled) { background:var(--color-success); color:#fff; }
    .btn-reject  { background:rgba(239,68,68,.12); color:#dc2626; border-radius:var(--radius-md); border:none; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:.2rem; padding:.28rem .6rem; font-size:.75rem; transition:.15s; white-space:nowrap; }
    .btn-reject:hover:not(:disabled)  { background:var(--color-danger); color:#fff; }
    .btn-edit    { background:rgba(59,130,246,.12); color:#2563eb; border-radius:var(--radius-md); border:none; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:.2rem; padding:.28rem .6rem; font-size:.75rem; transition:.15s; white-space:nowrap; }
    .btn-edit:hover { background:#2563eb; color:#fff; }
    .btn-delete-approved { background:rgba(239,68,68,.08); color:#dc2626; border-radius:var(--radius-md); border:none; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:.2rem; padding:.28rem .6rem; font-size:.75rem; transition:.15s; white-space:nowrap; }
    .btn-delete-approved:hover:not(:disabled) { background:var(--color-danger); color:#fff; }
    .btn-approve:disabled, .btn-reject:disabled, .btn-edit:disabled, .btn-distribute:disabled, .btn-delete-approved:disabled { opacity:.45; cursor:not-allowed; }

    /* Edit modal */
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

    /* Reports bar */
    .reports-bar { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:.9rem 1.2rem; margin-bottom:1.25rem; }
    .reports-bar-title { font-size:.78rem; font-weight:700; margin-bottom:.55rem; display:flex; align-items:center; gap:.4rem; }
    .reports-row { display:flex; flex-wrap:wrap; gap:.45rem; }
    .report-btn { display:inline-flex; align-items:center; gap:.3rem; padding:.38rem .8rem; border-radius:var(--radius-md); border:1px solid var(--color-border); cursor:pointer; font-size:.77rem; font-weight:600; text-decoration:none; background:var(--color-bg); color:var(--color-text); transition:.15s; }
    .report-btn:hover { background:var(--color-primary); color:#fff; border-color:var(--color-primary); }

    /* Empty */
    .pd-empty { text-align:center; padding:3rem 1.5rem; color:var(--color-text-secondary); }
    .pd-empty-icon { width:48px; height:48px; margin:0 auto .75rem; opacity:.35; }

    /* Toast */
    #pd-toasts { position:fixed; bottom:1.5rem; right:1.5rem; z-index:99999; display:flex; flex-direction:column; gap:.5rem; }
    .pd-toast { background:var(--color-surface); border:1px solid var(--color-border); border-radius:var(--radius-md); padding:.7rem 1rem; display:flex; align-items:center; gap:.5rem; font-size:.85rem; box-shadow:0 4px 14px rgba(0,0,0,.14); min-width:240px; max-width:340px; animation:pd-fi .25s ease; }
    .pd-toast.success { border-left:3px solid var(--color-success); }
    .pd-toast.error   { border-left:3px solid var(--color-danger); }
    @keyframes pd-fi { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:none; } }

    /* Distribution */
    .btn-distribute { background:rgba(99,102,241,.12); color:#4f46e5; border-radius:var(--radius-md); border:none; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:.2rem; padding:.22rem .5rem; font-size:.7rem; transition:.15s; }
    .btn-distribute:hover:not(:disabled) { background:#4f46e5; color:#fff; }
    .dist-badge { display:inline-flex; align-items:center; gap:.2rem; font-size:.65rem; font-weight:600; color:#4f46e5; background:#eef2ff; border-radius:99px; padding:.1rem .45rem; white-space:nowrap; margin-top:.18rem; }
    .dist-customers { font-size:.68rem; color:var(--color-text-secondary); margin-top:.18rem; }

    /* Selection bar */
    .selection-bar { position:fixed; bottom:0; left:0; right:0; background:var(--color-surface); border-top:2px solid var(--color-primary); padding:.75rem 1.2rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; z-index:99998; box-shadow:0 -4px 18px rgba(0,0,0,.14); transform:translateY(100%); transition:transform .25s ease; }
    .selection-bar.visible { transform:translateY(0); }
    .selection-bar-info { font-size:.88rem; font-weight:600; display:flex; align-items:center; gap:.4rem; }
    .selection-bar-actions { display:flex; gap:.5rem; align-items:center; }
    .btn-primary { background:var(--color-primary); color:#fff; }
    .btn-primary:hover:not(:disabled) { opacity:.88; transform:translateY(-1px); }
    .chk-cell { width:32px; text-align:center; }
    .chk-cell input[type=checkbox] { width:16px; height:16px; cursor:pointer; accent-color:var(--color-primary); }

    @media(max-width:600px) {
        .pd-header { flex-direction:column; }
        .data-table th:nth-child(3), .data-table td:nth-child(3),
        .data-table th:nth-child(6), .data-table td:nth-child(6) { display:none; }
    }
</style>

<div id="pd-toasts"></div>

{{-- ── COMPONENTES CENTRALIZADOS ── --}}
<x-delivery.edit-delivery-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
/>
<x-delivery.dist-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
    :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name])->values()->all()"
/>

{{-- ── PROJECT HEADER ── --}}
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

{{-- ── STATS ── --}}
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

{{-- ── REPORTS BAR ── --}}
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

{{-- ── MODAL: RELATÓRIO POR CLIENTE ── --}}
<div class="modal-overlay hidden" id="customerReportModal">
    <div class="modal-box" style="width:min(560px,96vw);max-height:90vh;overflow-y:auto;">
        <div class="modal-title">
            <i data-lucide="file-badge" style="width:18px;height:18px;color:#1d4ed8"></i>
            Relatório por Cliente
        </div>

        <div id="crm-step-loading" style="text-align:center;padding:2rem 0;color:var(--color-text-secondary);">
            <svg style="width:32px;height:32px;animation:crm-spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/>
                <path d="M22 12a10 10 0 00-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <p style="margin-top:.5rem;font-size:.82rem;">Carregando opções...</p>
        </div>
        <style>@keyframes crm-spin{to{transform:rotate(360deg)}}</style>

        <div id="crm-step-form" style="display:none;">
            {{-- Tipo de relatório --}}
            <div class="form-group">
                <label class="form-label">Tipo de Relatório</label>
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;" id="crm-type-btns">
                    <button type="button" class="crm-type-btn active" data-type="statement">Extrato Simples</button>
                    <button type="button" class="crm-type-btn" data-type="full">Com Associados</button>
                    <button type="button" class="crm-type-btn" data-type="compact">Resumo Compacto</button>
                </div>
                <div id="crm-type-desc" style="font-size:.72rem;color:var(--color-text-secondary);margin-top:.35rem;min-height:1rem;"></div>
            </div>

            {{-- Cliente --}}
            <div class="form-group">
                <label class="form-label">Cliente *</label>
                <select id="crm-customer" class="form-control" onchange="crmOnCustomerChange()">
                    <option value="">— Selecione um cliente —</option>
                </select>
            </div>

            {{-- Período --}}
            <div class="form-group">
                <label class="form-label">Período de Entregas</label>
                <div id="crm-date-chips" style="display:flex;flex-wrap:wrap;gap:.3rem;margin-bottom:.45rem;min-height:.5rem;"></div>
                <details style="margin-bottom:.4rem;">
                    <summary style="font-size:.7rem;color:var(--color-text-secondary);cursor:pointer;padding:.1rem 0;list-style:none;">
                        <span id="crm-month-toggle">▸ Ver por mês</span>
                    </summary>
                    <div id="crm-month-chips" style="display:flex;flex-wrap:wrap;gap:.3rem;margin-top:.35rem;padding-left:.25rem;"></div>
                </details>
                <div style="font-size:.7rem;color:var(--color-text-secondary);margin-bottom:.4rem;">
                    Ou defina manualmente:
                </div>
                <div class="form-row">
                    <div>
                        <label class="form-label" style="font-size:.7rem;">De</label>
                        <input type="date" id="crm-date-from" class="form-control" oninput="crmClearActiveChip()">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.7rem;">Até</label>
                        <input type="date" id="crm-date-to" class="form-control" oninput="crmClearActiveChip()">
                    </div>
                </div>
                <div style="margin-top:.4rem;display:flex;gap:.4rem;">
                    <button type="button" class="report-btn" style="font-size:.7rem;padding:.25rem .55rem;" onclick="crmSetAllDates()">Todo o período</button>
                    <button type="button" class="report-btn" style="font-size:.7rem;padding:.25rem .55rem;" onclick="crmClearDates()">Limpar</button>
                </div>
            </div>

            <div id="crm-availability" style="display:none;margin-bottom:.75rem;padding:.45rem .7rem;border-radius:4px;font-size:.77rem;"></div>

            {{-- Colunas (só para Extrato Simples) --}}
           <div id="crm-col-section" class="form-group" style="margin-bottom:.75rem;">
                <label class="form-label">Opções de exibição</label>
                <div style="display:flex;gap:1.2rem;flex-wrap:wrap;margin-top:.25rem;">
                    <label style="display:flex;align-items:center;gap:.35rem;font-size:.82rem;cursor:pointer;">
                        <input type="checkbox" id="crm-col-unit-price" checked style="width:14px;height:14px;"> Preço Unitário
                    </label>
                    <label style="display:flex;align-items:center;gap:.35rem;font-size:.82rem;cursor:pointer;">
                        <input type="checkbox" id="crm-col-total" checked style="width:14px;height:14px;"> Preço Total
                    </label>
                    <label style="display:flex;align-items:center;gap:.35rem;font-size:.82rem;cursor:pointer;">
                        <input type="checkbox" id="crm-ungrouped" style="width:14px;height:14px;"> Listar entregas individualmente (sem agrupar por produto)
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost btn-sm" onclick="closeCustomerReportModal()">Cancelar</button>
                <button type="button" class="btn btn-sm" id="crm-btn-generate"
                    style="background:#1d4ed8;color:#fff;" onclick="crmGenerate()" disabled>
                    <i data-lucide="download" style="width:13px;height:13px"></i> Gerar PDF
                </button>
            </div>
        </div>

        <div id="crm-step-error" style="display:none;padding:1.5rem 0;text-align:center;color:var(--color-danger);">
            <p style="font-size:.85rem;">Erro ao carregar opções. Tente novamente.</p>
            <button type="button" class="btn btn-ghost btn-sm" style="margin-top:.75rem;" onclick="crmLoadOptions()">Tentar novamente</button>
        </div>
    </div>
</div>
<style>
.crm-type-btn {
    padding:.3rem .7rem; border-radius:6px; border:1px solid #d1d5db;
    font-size:.75rem; cursor:pointer; background:#f9fafb; color:#374151;
    transition:.12s; white-space:nowrap;
}
.crm-type-btn.active { background:#1d4ed8; color:#fff; border-color:#1d4ed8; }
.crm-type-btn:hover:not(.active) { background:#f3f4f6; border-color:#9ca3af; }
</style>

{{-- ── COMPROVANTES GERADOS ── --}}
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

{{-- ── DELIVERIES TABLE ── --}}
<div class="pd-card">
    <div class="pd-card-header">
        <div class="pd-card-title">
            <i data-lucide="package" style="width:16px;height:16px;color:var(--color-primary)"></i>
            Entregas ({{ $totalAll }})
        </div>
        @if($totalPending > 0)
        <span style="font-size:.78rem;color:var(--color-warning);font-weight:600;">
            <i data-lucide="clock" style="width:13px;height:13px"></i> {{ $totalPending }} aguardando aprovação
        </span>
        @endif
    </div>

    @if($deliveries->isEmpty())
        <div class="pd-empty">
            <svg class="pd-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
            <p>Nenhuma entrega registrada para este projeto.</p>
        </div>
    @else
    <div class="table-scroll">
        <table class="data-table">
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
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deliveries as $delivery)
                <tr id="row-{{ $delivery['id'] }}" class="{{ $delivery['status_value'] === 'approved' ? 'approved-row' : '' }}">
                    <td class="chk-cell">
                        @if($delivery['status_value'] === 'approved')
                        <input type="checkbox" class="delivery-chk" value="{{ $delivery['id'] }}" data-associate="{{ $delivery['associate_name'] }}" data-net="{{ $delivery['dist_net_value'] }}">
                        @endif
                    </td>
                    <td style="white-space:nowrap;">{{ $delivery['delivery_date'] }}</td>
                    <td style="font-weight:500;">{{ $delivery['associate_name'] }}</td>
                    <td>{{ $delivery['product_name'] }}</td>
                    <td style="white-space:nowrap;font-weight:600;">{{ number_format($delivery['quantity'], 3, ',', '.') }} <small style="font-weight:400;font-size:.72em;">{{ $delivery['unit'] }}</small></td>
                    <td style="white-space:nowrap;font-weight:600;">
                        @if($delivery['dist_net_value'] > 0)
                            <span style="color:var(--color-success)">R$ {{ number_format($delivery['dist_net_value'], 2, ',', '.') }}</span>
                        @else
                            <span style="color:var(--color-text-muted);font-size:.78rem">— sem distrib.</span>
                        @endif
                    </td>
                    <td>{{ $delivery['quality_grade'] ?? '—' }}</td>
                    <td>
                        <span class="badge-status {{ $delivery['status_value'] }}">{{ $delivery['status'] }}</span>
                        @if($delivery['has_billed'])
                        <div style="display:inline-flex;align-items:center;gap:.2rem;font-size:.65rem;font-weight:600;color:#4f46e5;background:#eef2ff;border-radius:99px;padding:.1rem .45rem;white-space:nowrap;margin-top:.18rem;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            Faturado
                        </div>
                        @elseif($delivery['distributed_qty'] > 0)
                        <div class="dist-badge">
                            <i data-lucide="git-branch" style="width:9px;height:9px"></i>
                            {{ number_format($delivery['distributed_qty'], 2, ',', '.') }} {{ $delivery['unit'] }} distrib.
                        </div>
                        @endif
                    </td>
                    <td>
                        @if($delivery['status_value'] === 'pending')
                        <div class="action-btns">
                            <button class="btn-approve" data-id="{{ $delivery['id'] }}" title="Aprovar">
                                <i data-lucide="check" style="width:11px;height:11px"></i> Aprovar
                            </button>
                            <button class="btn-reject" data-id="{{ $delivery['id'] }}" title="Rejeitar">
                                <i data-lucide="x" style="width:11px;height:11px"></i> Rejeitar
                            </button>
                            <button class="btn-edit"
                                data-id="{{ $delivery['id'] }}"
                                data-date="{{ $delivery['delivery_date_raw'] }}"
                                data-qty="{{ $delivery['quantity'] }}"
                                data-price="{{ $delivery['unit_price'] }}"
                                data-quality="{{ $delivery['quality_grade'] }}"
                                data-notes="{{ $delivery['notes'] }}"
                                data-unit="{{ $delivery['unit'] }}"
                                data-distributions="{{ json_encode($delivery['distributions']) }}"
                                title="Editar entrega">
                                <i data-lucide="pencil" style="width:11px;height:11px"></i> Editar
                            </button>
                        </div>
                        @elseif($delivery['status_value'] === 'approved')
                        <div class="action-btns">
                            <button class="btn-distribute"
                                data-id="{{ $delivery['id'] }}"
                                data-product="{{ $delivery['product_name'] }}"
                                data-unit="{{ $delivery['unit'] }}"
                                data-qty="{{ $delivery['quantity'] }}"
                                data-distributed="{{ $delivery['distributed_qty'] }}"
                                data-existing="{{ json_encode($delivery['distributions']) }}"
                                data-participants="{{ json_encode($customers->pluck('id')->values()->all()) }}"
                                title="Distribuir para clientes"
                                aria-label="Distribuir entrega para clientes">
                                <i data-lucide="git-branch" style="width:11px;height:11px"></i> Distribuir
                            </button>
                            @if($delivery['has_billed'])
                            <button class="btn-edit" disabled title="Entrega faturada — edição bloqueada" style="opacity:.4;cursor:not-allowed;">
                                <i data-lucide="lock" style="width:11px;height:11px"></i> Bloqueado
                            </button>
                            @else
                            <button class="btn-edit"
                                data-id="{{ $delivery['id'] }}"
                                data-date="{{ $delivery['delivery_date_raw'] }}"
                                data-qty="{{ $delivery['quantity'] }}"
                                data-price="{{ $delivery['unit_price'] }}"
                                data-quality="{{ $delivery['quality_grade'] }}"
                                data-notes="{{ $delivery['notes'] }}"
                                data-unit="{{ $delivery['unit'] }}"
                                data-distributions="{{ json_encode($delivery['distributions']) }}"
                                title="Editar entrega"
                                aria-label="Editar entrega">
                                <i data-lucide="pencil" style="width:11px;height:11px"></i> Editar
                            </button>
                            <button class="btn-delete-approved"
                                data-id="{{ $delivery['id'] }}"
                                title="Excluir entrega aprovada (remove distribuições)"
                                aria-label="Excluir entrega aprovada">
                                <i data-lucide="trash-2" style="width:11px;height:11px"></i> Excluir
                            </button>
                            @endif
                        </div>
                        @else
                        <span style="font-size:.7rem;color:var(--color-text-secondary)">—</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- ── BARRA DE SELEÇÃO (fixa no rodapé) ── --}}
<div class="selection-bar" id="selection-bar">
    <div class="selection-bar-info">
        <i data-lucide="check-square" style="width:16px;height:16px;color:var(--color-primary)"></i>
        <span id="sel-count">0</span> recepção(ões) selecionada(s)
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

function pdToast(msg, type = 'success') {
    const c = document.getElementById('pd-toasts');
    const el = document.createElement('div');
    el.className = `pd-toast ${type}`;
    el.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
    c.appendChild(el);
    setTimeout(() => { el.style.opacity = 0; setTimeout(() => el.remove(), 300); }, 4000);
}

/* ── Seleção de entregas ── */
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
    document.getElementById('select-all').checked = false;
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
            // Converter Base64 → Blob → download
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
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
}

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

// Carregar histórico ao abrir a página
loadReceiptsHistory();

document.addEventListener('click', async function(e) {
    const approveBtn  = e.target.closest('.btn-approve');
    const rejectBtn   = e.target.closest('.btn-reject');
    const editBtn     = e.target.closest('.btn-edit');
    const distBtn     = e.target.closest('.btn-distribute');

    if (editBtn)  { EditModal.openFromBtn(editBtn); return; }
    if (distBtn)  { DistModal.openFromBtn(distBtn); return; }
    if (!approveBtn && !rejectBtn) return;

    const btn    = approveBtn || rejectBtn;
    const id     = btn.dataset.id;
    const action = approveBtn ? 'approve' : 'reject';

    if (!confirm(action === 'approve' ? 'Aprovar esta entrega?' : 'Rejeitar esta entrega?')) return;

    const row  = document.getElementById('row-' + id);
    const btns = row ? row.querySelectorAll('.btn-approve, .btn-reject') : [btn];
    btns.forEach(b => b.disabled = true);

    try {
        const res  = await fetch(`/${PD_TENANT}/delivery/deliveries/${id}/${action}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': PD_CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            pdToast(data.message);
            if (row) {
                // Update status badge
                const statusCell = row.cells[7]; // 0-chk,1-date,2-assoc,3-prod,4-qty,5-val,6-qual,7-status,8-actions
                if (statusCell) {
                    const badge = statusCell.querySelector('.badge-status');
                    if (badge) {
                        badge.className = 'badge-status ' + (action === 'approve' ? 'approved' : 'rejected');
                        badge.textContent = action === 'approve' ? 'Aprovada' : 'Rejeitada';
                    }
                }
                // Update action buttons
                const actionCell = row.cells[8];
                if (actionCell) {
                    if (action === 'approve') {
                        // Build distribute + edit buttons
                        const qty  = parseFloat(row.querySelector('.btn-edit')?.dataset.qty || row.dataset.qty || 0);
                        const date = row.querySelector('.btn-approve')?.dataset.date || '';
                        const btnRow = row.querySelector('.action-btns');
                        if (btnRow) {
                            btnRow.innerHTML = `
                                <button class="btn-distribute"
                                    data-id="${id}"
                                    data-product="${esc(row.cells[3]?.textContent || '')}"
                                    data-unit=""
                                    data-qty="${qty}"
                                    data-distributed="0"
                                    data-existing="[]"
                                    data-participants="${esc(JSON.stringify(DM_PROJECT_PARTICIPANTS))}"
                                    title="Distribuir para clientes">
                                    <i data-lucide="git-branch" style="width:11px;height:11px"></i> Distribuir
                                </button>
                                <button class="btn-edit"
                                    data-id="${id}"
                                    data-date="${date}"
                                    data-qty="${qty}"
                                    data-price=""
                                    data-quality=""
                                    data-notes=""
                                    data-unit=""
                                    data-distributions="[]"
                                    title="Editar entrega">
                                    <i data-lucide="pencil" style="width:11px;height:11px"></i> Editar
                                </button>
                            `;
                        }
                        // Add checkbox for receipt generation (net=0 until distributions created)
                        const chkCell = row.cells[0];
                        if (chkCell && !chkCell.querySelector('input')) {
                            chkCell.innerHTML = `<input type="checkbox" class="delivery-chk" value="${id}" data-associate="" data-net="0">`;
                        }
                        row.classList.add('approved-row');
                    } else {
                        actionCell.innerHTML = '<span style="font-size:.7rem;color:var(--color-text-secondary)">—</span>';
                    }
                }
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        } else {
            pdToast(data.message || 'Erro ao processar.', 'error');
            btns.forEach(b => b.disabled = false);
        }
    } catch(err) {
        pdToast('Erro de comunicação com o servidor.', 'error');
        btns.forEach(b => b.disabled = false);
    }
});

function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

/* Participants list for this project (filtered customers) */
const DM_PROJECT_PARTICIPANTS = @json($customers->pluck('id')->values()->all());

/* ── EditModal callbacks (component x-delivery.edit-delivery-modal) ── */
EditModal.onSaved = function(d) {
    pdToast('Entrega atualizada!');
    const id  = d.id;
    const row = document.getElementById('row-' + id);
    if (row) {
        row.cells[1].textContent = d.delivery_date;
        row.cells[4].innerHTML   = parseFloat(d.quantity).toLocaleString('pt-BR',{minimumFractionDigits:3}) + ' <small>' + '' + '</small>';
        // col 5 = líquido (distribuições) — não atualiza aqui pois dependeria de nova API call
        if (d.quality_grade !== undefined) row.cells[6].textContent = d.quality_grade || '—';
        const editBtn = row.querySelector('.btn-edit');
        if (editBtn) {
            editBtn.dataset.date    = d.delivery_date;
            editBtn.dataset.qty     = d.quantity;
            editBtn.dataset.quality = d.quality_grade || '';
        }
        const distBtn = row.querySelector('.btn-distribute');
        if (distBtn) distBtn.dataset.qty = d.quantity;
    }
};

/* ── DistModal callbacks (component x-delivery.dist-modal) ── */
window._DistModalReload = function() {
    pdToast('Distribuição salva!');
    setTimeout(() => location.reload(), 600);
};
window._DistModalOnDelete = function(receptionId, data) {
    pdToast('Distribuição removida.');
    // Update distributed_qty badge on the row
    const row = document.getElementById('row-' + receptionId);
    if (row) {
        const badge = row.querySelector('.dist-badge');
        if (data.dist_total_qty > 0) {
            if (badge) badge.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg> ' + parseFloat(data.dist_total_qty).toLocaleString('pt-BR',{minimumFractionDigits:2}) + ' distrib.';
        } else {
            badge?.remove();
        }
        // Update net column
        const distBtn = row.querySelector('.btn-distribute');
        if (distBtn) distBtn.dataset.distributed = data.dist_total_qty;
    }
};

/* ── Delete approved delivery ── */
document.addEventListener('click', async function(e) {
    const btn = e.target.closest('.btn-delete-approved');
    if (!btn) return;
    const id = btn.dataset.id;
    if (!confirm('Excluir esta entrega aprovada? Esta ação também removerá as distribuições associadas e não pode ser desfeita.')) return;
    btn.disabled = true;
    const row = document.getElementById('row-' + id);
    try {
        const res  = await fetch(`/${PD_TENANT}/delivery/deliveries/${id}`, {
            method : 'DELETE',
            headers: { 'X-CSRF-TOKEN': PD_CSRF, 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (data.success) {
            row?.remove();
            pdToast('Entrega excluída.');
        } else {
            alert(data.message || 'Erro ao excluir.');
            btn.disabled = false;
        }
    } catch(err) {
        alert('Erro de comunicação com o servidor.');
        btn.disabled = false;
    }
});

/* ═══════════════════════════════════════════════════════════
   MODAL — RELATÓRIO POR CLIENTE
════════════════════════════════════════════════════════════ */
let _crmOptions    = null;
let _crmActiveChip = null;
let _crmType       = 'statement';

const _crmTypeDesc = {
    statement: 'Data · produto · valor por item — para cobrar o cliente.',
    full:      'Inclui associado (origem) por linha — para conferência interna.',
    compact:   'Só totais por produto — visão rápida de cobrança.',
};

const _crmEndpoints = {
    statement: 'customer-delivery-statement',
    full:      'distributions-by-customer',
    compact:   'distributions-by-customer-compact',
};

// Tipo de relatório
document.getElementById('crm-type-btns').addEventListener('click', function(e) {
    const btn = e.target.closest('.crm-type-btn');
    if (!btn) return;
    _crmType = btn.dataset.type;
    document.querySelectorAll('.crm-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('crm-type-desc').textContent = _crmTypeDesc[_crmType] || '';
    document.getElementById('crm-col-section').style.display = _crmType === 'statement' ? 'block' : 'none';
});

// Inicializar descrição
document.getElementById('crm-type-desc').textContent = _crmTypeDesc['statement'];

function openCustomerReportModal() {
    document.getElementById('customerReportModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    crmLoadOptions();
}

function closeCustomerReportModal() {
    document.getElementById('customerReportModal').classList.add('hidden');
    document.body.style.overflow = '';
}

document.getElementById('customerReportModal').addEventListener('click', function(e) {
    if (e.target === this) closeCustomerReportModal();
});

async function crmLoadOptions() {
    document.getElementById('crm-step-loading').style.display = '';
    document.getElementById('crm-step-form').style.display = 'none';
    document.getElementById('crm-step-error').style.display = 'none';

    try {
        const url = `/${PD_TENANT}/delivery/reports/customer-delivery-options?project_id=${PD_PROJECT}`;
        const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': PD_CSRF } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        _crmOptions = await res.json();
        crmRenderForm();
    } catch(e) {
        document.getElementById('crm-step-loading').style.display = 'none';
        document.getElementById('crm-step-error').style.display = '';
    }
}

function crmRenderForm() {
    document.getElementById('crm-step-loading').style.display = 'none';
    document.getElementById('crm-step-form').style.display = '';

    const sel = document.getElementById('crm-customer');
    sel.innerHTML = '<option value="">— Selecione um cliente —</option>';
    (_crmOptions.customers || []).forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id; opt.textContent = c.name;
        sel.appendChild(opt);
    });

    // Chips por período de entrega (agrupados por proximidade)
    crmRenderDateChips(_crmOptions.date_groups || []);
    // Chips por mês (colapsável)
    crmRenderMonthChips(_crmOptions.dates_by_month || []);

    crmUpdateGenerateBtn();
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function crmRenderDateChips(groups) {
    const container = document.getElementById('crm-date-chips');
    container.innerHTML = '';
    if (!groups.length) return;
    groups.forEach((g, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.from = g.date_from;
        btn.dataset.to   = g.date_to;
        btn.dataset.idx  = idx;
        btn.innerHTML = `${g.label}${g.count > 1 ? ` <span style="opacity:.55;font-size:.78em;">(${g.count})</span>` : ''}`;
        btn.style.cssText = `
            padding:.28rem .65rem; border-radius:999px; border:1px solid #bbb;
            font-size:.72rem; font-weight:600; cursor:pointer; background:#f5f5f5;
            color:#333; transition:.12s; white-space:nowrap;
        `;
        btn.addEventListener('click', () => crmSelectChip(btn, g));
        container.appendChild(btn);
    });
}

function crmRenderMonthChips(months) {
    const container = document.getElementById('crm-month-chips');
    container.innerHTML = '';
    months.forEach((m, idx) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.dataset.from = m.date_from;
        btn.dataset.to   = m.date_to;
        btn.dataset.idx  = 'm' + idx;
        btn.innerHTML = `${m.label} <span style="opacity:.6;font-size:.8em;">(${m.count})</span>`;
        btn.style.cssText = `
            padding:.25rem .6rem; border-radius:999px; border:1px solid #d1d5db;
            font-size:.7rem; cursor:pointer; background:#f9fafb; color:#555; white-space:nowrap;
        `;
        btn.addEventListener('click', () => crmSelectChip(btn, m));
        container.appendChild(btn);
    });
}

function crmSelectChip(btn, period) {
    if (_crmActiveChip) {
        _crmActiveChip.style.background = _crmActiveChip.dataset.idx?.startsWith('m') ? '#f9fafb' : '#f5f5f5';
        _crmActiveChip.style.color = _crmActiveChip.dataset.idx?.startsWith('m') ? '#555' : '#333';
        _crmActiveChip.style.borderColor = _crmActiveChip.dataset.idx?.startsWith('m') ? '#d1d5db' : '#bbb';
    }
    _crmActiveChip = btn;
    btn.style.background = '#1d4ed8';
    btn.style.color = '#fff';
    btn.style.borderColor = '#1d4ed8';

    document.getElementById('crm-date-from').value = period.date_from;
    document.getElementById('crm-date-to').value   = period.date_to;
    crmUpdateAvailability();
    crmUpdateGenerateBtn();
}

function crmClearActiveChip() {
    if (_crmActiveChip) {
        _crmActiveChip.style.background = _crmActiveChip.dataset.idx?.startsWith('m') ? '#f9fafb' : '#f5f5f5';
        _crmActiveChip.style.color = _crmActiveChip.dataset.idx?.startsWith('m') ? '#555' : '#333';
        _crmActiveChip.style.borderColor = _crmActiveChip.dataset.idx?.startsWith('m') ? '#d1d5db' : '#bbb';
        _crmActiveChip = null;
    }
    crmUpdateAvailability();
    crmUpdateGenerateBtn();
}

function crmSetAllDates() {
    if (!_crmOptions?.all_dates?.length) return;
    crmClearActiveChip();
    document.getElementById('crm-date-from').value = _crmOptions.all_dates[0];
    document.getElementById('crm-date-to').value   = _crmOptions.all_dates[_crmOptions.all_dates.length - 1];
    crmUpdateAvailability();
    crmUpdateGenerateBtn();
}

function crmClearDates() {
    crmClearActiveChip();
    document.getElementById('crm-date-from').value = '';
    document.getElementById('crm-date-to').value   = '';
    crmUpdateAvailability();
    crmUpdateGenerateBtn();
}

function crmOnCustomerChange() {
    crmUpdateAvailability();
    crmUpdateGenerateBtn();
}

function crmUpdateAvailability() {
    const customerId = document.getElementById('crm-customer').value;
    const dateFrom   = document.getElementById('crm-date-from').value;
    const dateTo     = document.getElementById('crm-date-to').value;
    const box        = document.getElementById('crm-availability');

    if (!customerId) { box.style.display = 'none'; return; }

    if (dateFrom && dateTo && dateTo < dateFrom) {
        box.style.cssText = 'display:block;background:#fef2f2;border-left:3px solid #dc2626;padding:.4rem .7rem;font-size:.77rem;color:#991b1b;margin-bottom:.75rem;border-radius:0 4px 4px 0;';
        box.textContent = '⚠ A data final deve ser igual ou posterior à data inicial.';
        return;
    }

    box.style.cssText = 'display:block;background:#f0fdf4;border-left:3px solid #16a34a;padding:.4rem .7rem;font-size:.77rem;color:#166534;margin-bottom:.75rem;border-radius:0 4px 4px 0;';
    if (!dateFrom && !dateTo) {
        box.textContent = 'Sem filtro de data — todas as entregas disponíveis serão incluídas.';
    } else {
        const from = dateFrom ? new Date(dateFrom).toLocaleDateString('pt-BR') : '—';
        const to   = dateTo   ? new Date(dateTo).toLocaleDateString('pt-BR')   : '—';
        box.textContent = `Período: ${from} a ${to}`;
    }
}

function crmUpdateGenerateBtn() {
    const btn        = document.getElementById('crm-btn-generate');
    const customerId = document.getElementById('crm-customer').value;
    const dateFrom   = document.getElementById('crm-date-from').value;
    const dateTo     = document.getElementById('crm-date-to').value;
    btn.disabled = !customerId || (dateFrom && dateTo && dateTo < dateFrom);
}

function crmGenerate() {
    const customerId = document.getElementById('crm-customer').value;
    if (!customerId) return;

    const dateFrom = document.getElementById('crm-date-from').value;
    const dateTo   = document.getElementById('crm-date-to').value;
    const ungrouped = document.getElementById('crm-ungrouped')?.checked ? '1' : '0';

    const params = new URLSearchParams({ customer_id: customerId, ungrouped: ungrouped });
    if (dateFrom) params.set('date_from', dateFrom);
    if (dateTo)   params.set('date_to',   dateTo);
    params.set('project_id', PD_PROJECT);

    if (_crmType === 'statement') {
        const colUP  = document.getElementById('crm-col-unit-price');
        const colTot = document.getElementById('crm-col-total');
        if (colUP  && !colUP.checked)  params.set('col_unit_price', '0');
        if (colTot && !colTot.checked) params.set('col_total', '0');
    }

    const endpoint = _crmEndpoints[_crmType] || 'customer-delivery-statement';
    const url = `/${PD_TENANT}/delivery/reports/${endpoint}?${params.toString()}`;
    window.open(url, '_blank');
    closeCustomerReportModal();
}
</script>
@endsection

