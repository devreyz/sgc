@extends('layouts.bento')

@section('title', 'Registrar Entrega')
@section('page-title', 'Registrar Entrega')
@section('user-role', 'Registrador')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'register', $currentTenant->slug ?? request()->route('tenant'));
@endphp
{{-- ─────────────── MODAL DISTRIBUIR (componente unificado) ──────── --}}
<x-delivery.dist-modal
    :tenant-slug="$currentTenant->slug"
    :csrf="csrf_token()"
    :customers="$customers->map(fn($c)=>['id'=>$c->id,'name'=>$c->trade_name?:$c->name,'organization_name'=>$c->organization?->short_name??$c->organization?->name])->values()->all()"
/>
<x-delivery.notes-modal />


@section('content')
<style>
/* ─── Reset ─────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }

/* ─── Page wrapper ──────────────────────────────── */
.reg-page {
    width: 100%;
    max-width: none;
    margin: 0 auto;
    padding: 0.75rem 1rem 1rem;
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    align-items: start;
}
@media (min-width: 900px) {
    .reg-page {
        grid-template-columns: minmax(320px, 420px) minmax(0, 1fr);
        gap: 1.25rem;
    }
}
@media (min-width: 1280px) {
    .reg-page {
        grid-template-columns: minmax(360px, 440px) minmax(0, 1fr);
    }
}
/* ─── Cards ─────────────────────────────────────── */
.card {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    box-shadow: none;
    min-width: 0;
}
.card-header {
    padding: 0.9rem 1rem 0.75rem;
    border-bottom: 1px solid var(--color-border);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--color-text-muted);
}
.card-body {
    padding: 0.75rem 1rem 1rem;
}
.entry-card,
.history-card {
    width: 100%;
}
.history-card {
    display: flex;
    flex-direction: column;
    min-height: 0;
}
@media (min-width: 900px) {
    .entry-card {
        position: sticky;
        top: 6rem;
        align-self: start;
    }
    .history-card {
        min-height: calc(100vh - 8.5rem);
    }
}

/* ─── Project bar ────────────────────────────────── */
.project-bar {
    width: 100%;
    max-width: none;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.9rem 1rem;
    border-radius: var(--radius-md) var(--radius-md) 0 0;
    border: none;
    border-bottom: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface));
    box-shadow: none;
}
@media (max-width: 560px) {
    .project-bar {
        padding: 0.75rem;
        gap: 0.6rem;
    }
}
.project-bar-icon {
    width: 36px;
    height: 36px;
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-primary) 12%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-primary);
    flex-shrink: 0;
}
.project-bar-info {
    flex: 1;
    min-width: 0;
}
.project-bar-title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--color-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.project-bar-sub {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    margin-top: 1px;
}
.project-bar-btn {
    flex-shrink: 0;
    padding: 0.45rem 0.75rem;
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-primary) 28%, var(--color-border));
    background: var(--color-surface);
    font-size: 0.8rem;
    color: var(--color-text);
    cursor: pointer;
    transition: background 0.15s;
    white-space: nowrap;
}
.project-bar-btn:hover {
    background: color-mix(in srgb, var(--color-primary) 7%, var(--color-surface));
}

/* ─── Selector rows ──────────────────────────────── */
.selector-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    user-select: none;
}
.selector-row:hover { border-color: color-mix(in srgb, var(--color-primary) 45%, var(--color-border)); background: color-mix(in srgb, var(--color-primary) 3%, var(--color-surface)); }
.selector-row.selected { border-color: color-mix(in srgb, var(--color-primary) 60%, var(--color-border)); background: color-mix(in srgb, var(--color-primary) 4%, var(--color-surface)); }
.selector-row.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
.sel-icon {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-md);
    background: color-mix(in srgb, var(--color-border) 70%, transparent);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-muted);
    flex-shrink: 0;
    transition: background 0.15s, color 0.15s;
}
.selector-row.selected .sel-icon { background: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary); }
.sel-info { flex: 1; min-width: 0; }
.sel-label { font-size: 0.72rem; font-weight: 500; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-muted); }
.sel-value { font-size: 0.9rem; font-weight: 500; color: var(--color-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sel-meta { font-size: 0.75rem; color: var(--color-text-muted); margin-top: 1px; }
.sel-chevron { color: var(--color-text-muted); flex-shrink: 0; }
.selector-row.selected .sel-chevron { color: var(--color-primary); }

/* ─── Form fields ────────────────────────────────── */
.form-divider { height: 1px; background: var(--color-border); margin: 0.5rem 0; }

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}
@media (max-width: 480px) { .form-grid { grid-template-columns: 1fr; } }

.field-label {
    font-size: 0.72rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
    margin-bottom: 0.3rem;
    display: block;
}
.field-input {
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    color: var(--color-text);
    background: var(--color-surface);
    outline: none;
    transition: border-color 0.15s;
    font-family: inherit;
}
.field-input:focus { border-color: var(--color-primary); }

/* Quality pills */
.quality-pills { display: flex; gap: 0.4rem; }
.q-pill {
    flex: 1;
    padding: 0.5rem 0;
    text-align: center;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    color: var(--color-text-muted);
    background: transparent;
}
.q-pill:hover { border-color: var(--color-primary); color: var(--color-primary); }
.q-pill.active { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
.q-pill[data-q="B"].active { background: #f59e0b; border-color: #f59e0b; }
.q-pill[data-q="C"].active { background: #ef4444; border-color: #ef4444; }

/* Submit */
.btn-submit {
    width: 100%;
    padding: 0.85rem;
    margin-top: 0.75rem;
    background: var(--color-primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-md);
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s, opacity 0.15s;
    letter-spacing: 0.02em;
}
.btn-submit:hover:not(:disabled) { background: var(--color-primary-dark); }
.btn-submit:disabled { opacity: 0.45; cursor: not-allowed; }

/* ─── Mobile cards (histórico) ─────────────────── */
.mobile-card {
    --delivery-state: #94a3b8;
    --delivery-state-bg: #f8fafc;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    overflow: hidden;
    border-left: 2px solid var(--delivery-state);
    min-width: 0;
}
.mobile-card.status-pending  { --delivery-state:#d97706; --delivery-state-bg:#fff7ed; }
.mobile-card.status-approved { --delivery-state:#2563eb; --delivery-state-bg:#eff6ff; }
.mobile-card.status-distributed { --delivery-state:#059669; --delivery-state-bg:#ecfdf5; }
.mobile-card.status-rejected { --delivery-state:#dc2626; --delivery-state-bg:#fef2f2; }
.mobile-card.status-cancelled { --delivery-state:#6b7280; --delivery-state-bg:#f3f4f6; }

.mc-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto auto;
    align-items: center;
    gap: 0.4rem;
    padding: 0.42rem 0.55rem;
    background: var(--delivery-state-bg);
    border-bottom: 1px solid color-mix(in srgb, var(--delivery-state) 16%, var(--color-border));
    min-width: 0;
}
.mc-state-icon {
    width: 22px;
    height: 22px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--delivery-state);
    background: color-mix(in srgb, var(--delivery-state) 10%, #fff);
    border: 1px solid color-mix(in srgb, var(--delivery-state) 18%, transparent);
}
.mc-state-icon svg {
    width: 12px;
    height: 12px;
}
.mc-head-main {
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 0.28rem;
    white-space: nowrap;
    overflow: hidden;
}
.mc-head-line {
    display: contents;
    align-items: center;
    gap: 0.35rem;
    min-width: 0;
    font-size: 0.74rem;
    color: var(--color-text-secondary);
}
.mc-date {
    font-weight: 700;
    color: var(--color-text);
    white-space: nowrap;
    font-size: 0.74rem;
}
.mc-sep { color: var(--color-text-muted); opacity: .55; font-size: .7rem; }
.mc-head-product {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: var(--color-text);
    font-weight: 700;
    font-size: 0.8rem;
    flex: 1 1 auto;
}
.mc-head-qty {
    color: var(--color-text-secondary);
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
}
.mc-billed {
    font-size: 0.6rem;
    color: #4f46e5;
    background: #eef2ff;
    border-radius: 99px;
    padding: 0.1rem 0.35rem;
    white-space: nowrap;
}
.mc-quality {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    font-size: 0.65rem;
    font-weight: 700;
}
.mc-body {
    padding: 0.48rem 0.55rem;
    display: flex;
    flex-direction: column;
    gap: 0.42rem;
}
.mc-info-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 0.35rem 0.8rem;
    font-size: 0.76rem;
}
.mc-associate,
.mc-product {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.mc-associate { font-weight: 700; }
.mc-product { font-weight: 600; }
.mc-qty { font-weight: 700; }
.mc-net {
    color: var(--color-success);
    font-weight: 600;
    white-space: nowrap;
}
.mc-footer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: color-mix(in srgb, var(--color-border) 25%, var(--color-surface));
    padding: 0.3rem 0.45rem;
    border-radius: 6px;
    min-width: 0;
}
.mc-footer-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    color: var(--color-text-secondary);
    white-space: nowrap;
}
@media (max-width: 520px) {
    .mc-info-grid {
        grid-template-columns: 1fr;
    }
    .mc-footer {
        flex-wrap: wrap;
    }
}

.badge-status { display:inline-flex; align-items:center; gap:.2rem; padding:.18rem .5rem; border-radius:99px; font-size:.68rem; font-weight:600; text-transform:uppercase; white-space:nowrap; }
.badge-status.pending  { background:rgba(245,158,11,.14); color:#d97706; }
.badge-status.approved { background:rgba(16,185,129,.14); color:#059669; }
.badge-status.rejected { background:rgba(239,68,68,.14); color:#dc2626; }
.badge-status.cancelled { background:rgba(107,114,128,.14); color:#6b7280; }

/* Ações (mini botões) */
.btn-approve, .btn-reject, .btn-edit, .btn-distribute, .btn-delete-approved {
    display:inline-flex; align-items:center; gap:.2rem; font-size:.7rem; font-weight:600;
    border-radius:var(--radius-md); border:none; cursor:pointer; padding:.25rem .5rem;
    transition:.15s; white-space:nowrap;
    background:rgba(16,185,129,.12); color:#059669; /* exemplo */
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

.btn-xs { padding:.22rem .5rem; font-size:.7rem; }

/* ─── Distribuição (barra) ─────────────────────── */
.mc-dist-indicator { display:flex; align-items:center; gap:.3rem; flex:1; min-width:0; cursor:pointer; border-radius:6px; }
.mc-dist-indicator:hover .mc-dist-bar-bg { background:#dbe3ea; }
.mc-dist-bar-bg { flex:1; height:7px; background:#e5e7eb; border-radius:99px; overflow:hidden; min-width:64px; max-width:160px; }
.mc-dist-bar-fill { height:100%; border-radius:99px; }
.mc-dist-bar-fill.full { background:#10b981; }
.mc-dist-bar-fill.partial { background:#93c5fd; }
.mc-dist-bar-fill.over { background:#fca5a5; }
.mc-dist-text { font-weight:700; font-size:.72rem; white-space:nowrap; }
.mc-actions { display:flex; gap:.3rem; margin-left:auto; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end; }

.delivery-pagination {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.75rem;
    padding:.55rem .75rem .75rem;
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

.dist-summary-overlay {
    position:fixed;
    inset:0;
    z-index:310000;
    display:none;
    align-items:center;
    justify-content:center;
    padding:1rem;
    background:rgba(15,23,42,.28);
}
.dist-summary-overlay.open { display:flex; }
.dist-summary-box {
    width:min(420px, 94vw);
    max-height:min(520px, 88dvh);
    overflow:auto;
    background:var(--color-surface);
    border:1px solid var(--color-border);
    border-radius:var(--radius-lg);
    box-shadow:0 18px 42px rgba(15,23,42,.24);
}
.dist-summary-head {
    display:flex;
    justify-content:space-between;
    gap:1rem;
    padding:.9rem 1rem;
    border-bottom:1px solid var(--color-border);
}
.dist-summary-title { font-weight:800; font-size:.92rem; color:var(--color-text); }
.dist-summary-sub { font-size:.76rem; color:var(--color-text-secondary); margin-top:.12rem; }
.dist-summary-close { border:0; background:transparent; color:var(--color-text-secondary); cursor:pointer; font-size:1.1rem; }
.dist-summary-body { padding:.85rem 1rem 1rem; display:grid; gap:.45rem; }
.dist-summary-row {
    display:flex;
    justify-content:space-between;
    gap:.75rem;
    padding:.55rem .65rem;
    border:1px solid var(--color-border);
    border-radius:var(--radius-md);
    background:var(--color-bg);
    font-size:.82rem;
}
.dist-summary-row strong { color:var(--color-text); }
.dist-summary-row span { color:var(--color-text-secondary); white-space:nowrap; }

/* Filter bar */
.history-filter {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    border-bottom: 1px solid var(--color-border);
    background: color-mix(in srgb, var(--color-border) 30%, transparent);
    flex-wrap: wrap;
}
.history-filter label {
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
    white-space: nowrap;
}
.history-filter input[type=date] {
    padding: 0.3rem 0.5rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    color: var(--color-text);
    background: var(--color-surface);
    outline: none;
    font-family: inherit;
    min-width: 0;
    flex: 1;
    max-width: 140px;
}
.history-filter input,
.history-filter select {
    padding: 0.42rem 0.6rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 0.82rem;
    color: var(--color-text);
    background: var(--color-surface);
    outline: none;
    font-family: inherit;
    min-width: 0;
}
.history-filter input[type=search] {
    flex: 1 1 220px;
    max-width: none;
}
.history-filter select {
    flex: 1 1 130px;
    max-width: 180px;
}
.history-filter input[type=date] {
    flex: 1 1 120px;
    max-width: 145px;
}
.history-filter input:focus,
.history-filter select:focus { border-color: var(--color-primary); }
.history-filter .hf-clear {
    font-size: 0.75rem;
    color: var(--color-text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0.25rem 0.4rem;
    border-radius: var(--radius-md);
    white-space: nowrap;
}
.history-filter .hf-clear:hover { color: var(--color-danger); background: color-mix(in srgb, var(--color-danger) 8%, transparent); }

/* Session list */
#session-list {
    flex: 1;
    min-height: 0;
    padding: 0.6rem;
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.5rem;
    align-items: start;
}
.session-section-header {
    grid-column: 1 / -1;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--color-text-secondary);
    padding: 0.4rem 0.1rem 0;
    border-top: 1px solid var(--color-border);
    margin-top: 0.15rem;
}
.session-collapsible {
    grid-column: 1 / -1;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg);
    overflow: hidden;
}
.session-collapsible > summary {
    display: flex;
    min-height: 42px;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    padding: .55rem .7rem;
    color: var(--color-text-secondary);
    font-size: .7rem;
    font-weight: 750;
    cursor: pointer;
    list-style: none;
}
.session-collapsible > summary::-webkit-details-marker { display:none; }
.session-collapsible > summary::after { content:'+'; font-size:1rem; }
.session-collapsible[open] > summary::after { content:'-'; }
.session-collapsible-list { display:grid; gap:.5rem; padding:0 .5rem .5rem; }
.session-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 1rem;
    color: var(--color-text-muted);
    font-size: 0.85rem;
}
.reg-integrity { border-bottom:1px solid var(--color-border); background:var(--color-bg); }
.reg-integrity[hidden], .reg-integrity-list[hidden] { display:none !important; }
.reg-integrity-head { display:flex; align-items:center; justify-content:space-between; gap:.6rem; padding:.55rem .8rem; }
.reg-integrity-counts { display:flex; gap:.45rem; flex-wrap:wrap; font-size:.7rem; font-weight:700; }
.reg-integrity-toggle { width:30px; height:30px; border:1px solid var(--color-border); background:var(--color-surface); color:var(--color-text-secondary); border-radius:var(--radius-md); cursor:pointer; display:flex; align-items:center; justify-content:center; }
.reg-integrity-list { padding:0 .8rem .65rem; display:flex; flex-direction:column; gap:.45rem; }
.reg-integrity-item { padding:.55rem .6rem; border:1px solid var(--color-border); border-left:3px solid #d97706; border-radius:var(--radius-md); background:var(--color-surface); }
.reg-integrity-item.critical { border-left-color:#dc2626; }
.reg-integrity-item.info { border-left-color:#2563eb; }
.reg-integrity-title { font-size:.77rem; font-weight:800; }
.reg-integrity-message { font-size:.73rem; color:var(--color-text-secondary); line-height:1.35; margin-top:.12rem; }
.reg-integrity-actions { display:flex; gap:.35rem; flex-wrap:wrap; margin-top:.42rem; }
.mc-status-pill { font-size:.62rem; font-weight:800; padding:.12rem .38rem; border-radius:999px; white-space:nowrap; }
.mc-status-pill.rejected { color:#b91c1c; background:#fee2e2; border:1px solid #fecaca; }

/* ─── Modals ─────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 9000;
    align-items: flex-end;
    justify-content: center;
    padding: 0;
}
.modal-overlay.open { display: flex; }
@media (min-width: 600px) {
    .modal-overlay { align-items: center; padding: 1.5rem; }
}
.modal-box {
    background: var(--color-surface);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    width: 100%;
    max-width: 560px;
    max-height: 70vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 -4px 32px rgba(0,0,0,0.15);
    overflow: hidden;
}
@media (min-width: 600px) {
    .modal-box { border-radius: var(--radius-lg); max-height: 60vh; }
}
.modal-header {
    padding: 1rem 1rem 0.75rem;
    border-bottom: 1px solid var(--color-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-shrink: 0;
}
.modal-title { font-size: 0.95rem; font-weight: 600; color: var(--color-text); }
.modal-close {
    width: 28px;
    height: 28px;
    border: none;
    background: transparent;
    cursor: pointer;
    color: var(--color-text-muted);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.modal-close:hover { background: var(--color-border); }
.modal-search-wrap {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--color-border);
    flex-shrink: 0;
}
.modal-search {
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    color: var(--color-text);
    background: var(--color-surface);
    outline: none;
    font-family: inherit;
    transition: border-color 0.15s;
}
.modal-search:focus { border-color: var(--color-primary); }
.modal-list { overflow-y: auto; flex: 1; }
.modal-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    cursor: pointer;
    transition: background 0.1s;
    border-bottom: 1px solid color-mix(in srgb, var(--color-border) 50%, transparent);
}
.modal-item:last-child { border-bottom: none; }
.modal-item:hover { background: color-mix(in srgb, var(--color-primary) 6%, var(--color-surface)); }
.modal-item.highlighted { background: color-mix(in srgb, var(--color-primary) 10%, var(--color-surface)); }
.mi-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: color-mix(in srgb, var(--color-primary) 12%, transparent);
    color: var(--color-primary);
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.mi-avatar.product { border-radius: var(--radius-md); background: color-mix(in srgb, var(--color-secondary) 12%, transparent); color: var(--color-secondary); }
.mi-avatar.project { border-radius: var(--radius-md); background: color-mix(in srgb, #f59e0b 12%, transparent); color: #b45309; }
.mi-info { flex: 1; min-width: 0; }
.mi-name { font-size: 0.9rem; font-weight: 500; color: var(--color-text); }
.mi-sub { font-size: 0.75rem; color: var(--color-text-muted); margin-top: 2px; }
.mi-badge {
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.15rem 0.45rem;
    border-radius: 999px;
    white-space: nowrap;
}
.mi-badge.green { background: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary-dark); }
.mi-badge.amber { background: color-mix(in srgb, #f59e0b 15%, transparent); color: #92400e; }
.mi-badge.red   { background: color-mix(in srgb, #ef4444 15%, transparent); color: #991b1b; }
.mi-limit-summary{display:flex;flex-wrap:wrap;gap:.25rem .65rem;margin-top:.35rem;font-size:.68rem;color:var(--color-text-muted)}
.mi-limit-summary strong{color:var(--color-text);font-weight:700}
.mi-limit-track{display:flex;height:7px;margin-top:.38rem;overflow:hidden;border-radius:5px;background:#e5e7eb}
.mi-limit-used{height:100%;background:#d97706}
.mi-limit-free{height:100%;background:#15803d}
.product-modal-actions{display:flex;gap:.4rem;padding:.55rem .75rem;border-bottom:1px solid var(--color-border);background:var(--color-bg)}
.product-modal-actions .btn-small,.quota-footer .btn-small{min-height:36px;display:inline-flex;align-items:center;justify-content:center;gap:.3rem;border:1px solid var(--color-border);border-radius:7px;background:var(--color-surface);color:var(--color-text);padding:.42rem .6rem;font-size:.7rem;font-weight:750;cursor:pointer}
.product-modal-actions .btn-small.primary,.quota-footer .btn-small.primary{border-color:var(--color-primary);background:var(--color-primary);color:#fff}
.product-modal-actions .btn-small.danger,.quota-footer .btn-small.danger{border-color:#fecaca;background:#fff;color:#b91c1c}
.product-modal-actions .btn-small:disabled,.quota-footer .btn-small:disabled{opacity:.55;cursor:not-allowed}
.mi-quota-edit{flex:none;width:34px;min-height:34px;display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--color-border);border-radius:7px;background:var(--color-surface);color:var(--color-text);padding:.38rem;cursor:pointer}
.mi-quota-edit svg{width:14px;height:14px}
.quota-modal-box{width:min(620px,calc(100vw - 1rem));max-height:88dvh}
.quota-body{display:grid;gap:.7rem;padding:.75rem;overflow:auto;overscroll-behavior:contain}
.quota-picker-list{display:grid;gap:.35rem;max-height:52dvh;overflow:auto}
.quota-picker-item{display:flex;align-items:center;justify-content:space-between;gap:.6rem;min-height:58px;padding:.65rem;border:1px solid var(--color-border);border-radius:7px;background:var(--color-surface);color:var(--color-text);text-align:left;cursor:pointer}
.quota-picker-item strong{display:block;font-size:.82rem}.quota-picker-item span{display:block;margin-top:.12rem;color:var(--color-text-muted);font-size:.68rem}
.quota-picker-item:disabled{opacity:.55;cursor:not-allowed}
.quota-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.4rem}
.quota-metric{padding:.55rem;border-radius:7px;background:var(--color-bg)}
.quota-metric span{display:block;color:var(--color-text-muted);font-size:.64rem}.quota-metric strong{display:block;margin-top:.12rem;font-size:.78rem}
.quota-progress-block{display:grid;gap:.25rem}.quota-progress-head{display:flex;justify-content:space-between;gap:.5rem;color:var(--color-text-muted);font-size:.66rem;font-weight:700}
.quota-progress{height:9px;overflow:hidden;border-radius:999px;background:var(--color-bg)}.quota-progress span{display:block;height:100%;border-radius:inherit;background:var(--color-primary);transition:width .18s ease}.quota-progress.warning span{background:#d97706}.quota-progress.danger span{background:#dc2626}
.quota-edit-grid{display:grid;grid-template-columns:minmax(0,1fr) 140px;gap:.65rem;align-items:end}
.quota-edit-grid label{display:grid;gap:.25rem;color:var(--color-text-muted);font-size:.68rem;font-weight:750}.quota-slider{width:100%;min-height:38px;accent-color:var(--color-primary);touch-action:pan-y}
.quota-feedback{min-height:1.2rem;color:var(--color-text-muted);font-size:.7rem;line-height:1.4}.quota-feedback.error{color:#b91c1c;font-weight:750}
.quota-footer{display:flex;align-items:center;justify-content:space-between;gap:.45rem;padding:.65rem .75rem;border-top:1px solid var(--color-border);background:var(--color-surface)}
@media(max-width:600px){.product-modal-actions{display:grid;grid-template-columns:1fr 1fr}.product-limit-item{align-items:flex-start;flex-wrap:wrap}.product-limit-item .mi-info{min-width:calc(100% - 54px)}.product-limit-item .mi-quota-edit{margin-left:48px}.quota-summary-grid{grid-template-columns:1fr 1fr}.quota-edit-grid{grid-template-columns:1fr}.quota-footer{padding-bottom:calc(.65rem + env(safe-area-inset-bottom))}}
.modal-empty {
    padding: 2rem 1rem;
    text-align: center;
    color: var(--color-text-muted);
    font-size: 0.85rem;
}

/* ─── Toast ──────────────────────────────────────── */
#toast-root {
    position: fixed;
    bottom: 1.5rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    display: flex;
    flex-direction: column-reverse;
    align-items: center;
    gap: 0.5rem;
    pointer-events: none;
}
.toast {
    padding: 0.65rem 1.1rem;
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    font-weight: 500;
    color: #fff;
    box-shadow: var(--shadow-md);
    animation: toastIn 0.2s ease;
    pointer-events: all;
    max-width: 360px;
    text-align: center;
}
.toast.success { background: var(--color-primary-dark); }
.toast.error   { background: var(--color-danger); }
.toast.info    { background: #374151; }
@keyframes toastIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

.scroll-top-btn {
    position: fixed;
    right: 1rem;
    bottom: 1rem;
    z-index: 8500;
    width: 44px;
    height: 44px;
    border: 1px solid var(--color-border);
    border-radius: 50%;
    background: var(--color-surface);
    color: var(--color-primary);
    display: none;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-md);
    cursor: pointer;
    opacity: 0;
    pointer-events: none;
    transform: translateY(8px);
    transition: opacity 0.18s ease, transform 0.18s ease, background 0.15s ease;
}
.scroll-top-btn.visible {
    opacity: 1;
    pointer-events: auto;
    transform: translateY(0);
}
.scroll-top-btn:hover {
    background: color-mix(in srgb, var(--color-primary) 8%, var(--color-surface));
}
@media (max-width: 899px) {
    .scroll-top-btn {
        display: flex;
    }
}
</style>


<style id="register-refined-ui">
.reg-page,.modal-overlay,.dist-summary-overlay,#toast-root{
--r-green:#168a4d;--r-green-soft:#eaf8ef;--r-blue:#2563eb;--r-blue-soft:#eef4ff;
--r-sky:#0284c7;--r-sky-soft:#edf8fe;--r-violet:#7c3aed;--r-violet-soft:#f4f0ff;
--r-amber:#c87408;--r-amber-soft:#fff7e8;--r-red:#cf3f3f;--r-red-soft:#fff0f0;
--r-slate:#64748b;--r-slate-soft:#f1f5f9;--r-surface:var(--color-surface,#fff);
--r-soft:var(--color-surface-soft,#f8faf9);--r-border:var(--color-border,#dce7e0);
--r-border-2:var(--color-border-strong,#c8d6cd);--r-text:var(--color-text,#102018);
--r-text-2:var(--color-text-secondary,#52645a);--r-text-3:var(--color-text-muted,#809087);
--r-shadow:0 4px 14px rgba(15,35,24,.045);--r-shadow-md:0 14px 34px rgba(15,35,24,.09)}
.reg-page{width: 100%;max-width:1280px;padding:0 0 1rem;gap:.78rem}
.reg-page svg,.modal-overlay svg,.dist-summary-overlay svg{display:block;flex:0 0 auto;margin:0;vertical-align:middle}
.reg-page .card{overflow:hidden;border:1px solid var(--r-border);border-radius:15px;background:#fff;box-shadow:var(--r-shadow)}
.reg-page .card-header{min-height:46px;padding:.56rem .7rem;border-bottom:1px solid var(--r-border);background:linear-gradient(180deg,var(--r-soft),#fff);color:var(--r-text-2);font-size:.7rem;font-weight:800;letter-spacing:.03em}
.reg-page .card-body{padding:.62rem}
@media(min-width:1020px){.reg-page{grid-template-columns:minmax(330px,390px) minmax(0,1fr);gap:.82rem}.entry-card{position:sticky;top:5.7rem}.history-card{min-height:calc(100dvh - 7.6rem)}}
@media(min-width:1380px){.reg-page{grid-template-columns:405px minmax(0,1fr)}}

/* projeto */
.project-bar{min-height:64px;padding:.58rem .65rem;gap:.5rem;border:0;border-bottom:1px solid var(--r-border);border-radius:0;background:radial-gradient(circle at 100% 0,rgba(124,58,237,.08),transparent 13rem),linear-gradient(180deg,var(--r-blue-soft),#fff)}
.project-bar-icon{width:38px;height:38px;border-radius:10px;background:var(--r-blue-soft);color:var(--r-blue);display:inline-flex;align-items:center;justify-content:center}
.project-bar-title{font-size:.84rem;font-weight:840;letter-spacing:-.015em;color:var(--r-text)}
.project-bar-sub{font-size:.67rem;color:var(--r-text-3)}
.project-bar-btn{min-height:34px;padding:.35rem .5rem;border:1px solid rgba(37,99,235,.18);border-radius:9px;background:#fff;color:var(--r-blue);font-size:.67rem;font-weight:780}
#pb-badge{display:inline-flex!important;align-items:center;min-height:24px;padding:.14rem .38rem!important;border:1px solid rgba(22,138,77,.14);background:var(--r-green-soft)!important;color:var(--r-green)!important;font-size:.58rem!important;font-weight:820!important}

/* entrada */
.entry-card .card-body{gap:.42rem!important}
.selector-row{position:relative;min-height:53px;gap:.46rem;padding:.46rem .52rem;border:1px solid var(--r-border);border-radius:10px;background:#fff}
.selector-row:hover{border-color:color-mix(in srgb,var(--r-blue) 22%,var(--r-border));background:var(--r-soft)}
.selector-row.selected{border-color:color-mix(in srgb,var(--r-blue) 18%,var(--r-border));background:linear-gradient(90deg,var(--r-blue-soft),#fff 68%)}
.selector-row.disabled{opacity:.5}
.sel-icon{width:31px;height:31px;border-radius:9px;background:var(--r-slate-soft);color:var(--r-slate);display:inline-flex;align-items:center;justify-content:center}
#sel-assoc.selected .sel-icon{background:var(--r-blue-soft);color:var(--r-blue)}
#sel-date .sel-icon{background:var(--r-violet-soft);color:var(--r-violet)}
#sel-product .sel-icon{background:var(--r-amber-soft);color:var(--r-amber)}
.sel-label{font-size:.6rem;font-weight:760;color:var(--r-text-3)}
.sel-value,.sel-date-display{font-size:.78rem;font-weight:780;color:var(--r-text)}
.sel-meta{font-size:.62rem;line-height:1.3;color:var(--r-text-3)}
.sel-chevron{display:inline-flex;align-items:center;justify-content:center;color:var(--r-slate)}
.form-divider{margin:.34rem 0 .46rem;background:var(--r-border)}
.form-grid{grid-template-columns:minmax(0,1fr) minmax(118px,.72fr);gap:.46rem}.form-grid>div:nth-child(3){grid-column:1/-1}
.field-label{margin-bottom:.2rem;font-size:.61rem;font-weight:760;color:var(--r-text-3)}
.field-input{min-height:40px;padding:.44rem .54rem;border:1px solid var(--r-border-2);border-radius:9px;background:#fff;font-size:.79rem}
.field-input:focus{border-color:var(--r-blue);box-shadow:0 0 0 3px rgba(37,99,235,.07)}
.quality-pills{gap:.24rem}.q-pill{min-height:40px;padding:.35rem .25rem;border:1px solid var(--r-border);border-radius:9px;background:#fff;color:var(--r-text-2);font-size:.75rem}
.q-pill.active,.q-pill[data-q="A"].active{border-color:rgba(22,138,77,.18);background:var(--r-green-soft);color:var(--r-green)}
.q-pill[data-q="B"].active{border-color:rgba(200,116,8,.18);background:var(--r-amber-soft);color:#92400e}
.q-pill[data-q="C"].active{border-color:rgba(207,63,63,.18);background:var(--r-red-soft);color:#991b1b}
.btn-submit{min-height:44px;margin-top:.52rem;padding:.54rem .7rem;border:1px solid rgba(22,138,77,.2);border-radius:10px;background:linear-gradient(180deg,#1b9554,var(--r-green));box-shadow:0 4px 12px rgba(22,138,77,.1);font-size:.79rem;font-weight:820}
.btn-submit:disabled{box-shadow:none;opacity:.42}

/* histórico */
.history-card>.card-header{min-height:48px;padding:.56rem .68rem!important;color:var(--r-text);font-size:.73rem;letter-spacing:0;text-transform:none}
#session-list-title{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
#session-count{display:inline-flex;align-items:center;min-height:23px;padding:.12rem .36rem;border-radius:999px;background:var(--r-blue-soft);color:var(--r-blue)!important;font-size:.6rem!important}
.reg-integrity{border-bottom:1px solid var(--r-border);background:linear-gradient(90deg,var(--r-amber-soft),#fff 64%)}
.reg-integrity-head{min-height:50px;padding:.44rem .62rem}.reg-integrity-counts{gap:.34rem;font-size:.6rem}
.reg-integrity-toggle{width:31px;height:31px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center}
.reg-integrity-list{padding:0 .62rem .55rem;gap:.32rem}.reg-integrity-item{padding:.44rem .48rem;border-radius:8px}.reg-integrity-title{font-size:.7rem}.reg-integrity-message{font-size:.65rem}

/* filtros: desktop denso; mobile só busca + status */
.history-filter{display:grid;grid-template-columns:minmax(180px,1.25fr) 132px minmax(120px,.7fr) minmax(120px,.7fr) 118px auto 118px auto;gap:.3rem;align-items:center;padding:.44rem .56rem;border-bottom:1px solid var(--r-border);background:var(--r-soft)}
.history-filter>label{display:none}.history-filter input,.history-filter select,.history-filter input[type=date],.history-filter input[type=search]{width:100%;min-width:0;max-width:none;min-height:35px;padding:.34rem .44rem;border:1px solid var(--r-border);border-radius:8px;background:#fff;font-size:.67rem}
.history-filter input:focus,.history-filter select:focus{border-color:var(--r-blue);box-shadow:0 0 0 3px rgba(37,99,235,.055)}
.history-filter>span{font-size:.6rem!important;color:var(--r-text-3)!important;text-align:center}.history-filter .hf-clear{min-height:33px;padding:.3rem .4rem;border-radius:8px;color:var(--r-slate);font-size:.65rem;font-weight:720}

#session-list{padding:.46rem;gap:.38rem}
@media(min-width:1240px){#session-list{grid-template-columns:repeat(2,minmax(0,1fr))}.session-section-header,.session-collapsible,.session-empty{grid-column:1/-1}}
.session-section-header{margin-top:0;padding:.26rem .04rem .06rem;border-top:0;font-size:.61rem;color:var(--r-text-2)}
.session-collapsible{border:1px solid var(--r-border);border-radius:10px;background:var(--r-soft)}.session-collapsible>summary{min-height:38px;padding:.42rem .52rem;font-size:.64rem}.session-collapsible-list{gap:.36rem;padding:0 .4rem .4rem}

/* card de entrega */
.mobile-card{border:1px solid var(--r-border);border-left:3px solid var(--delivery-state);border-radius:10px;box-shadow:none}
.mc-head{min-height:37px;grid-template-columns:minmax(0,1fr) auto auto auto;gap:.28rem;padding:.32rem .42rem;background:linear-gradient(90deg,var(--delivery-state-bg),#fff 68%)}
.mc-head-main{gap:.22rem}.mc-date{font-size:.63rem}.mc-head-product{font-size:.7rem}.mc-head-qty{font-size:.64rem}.mc-sep{font-size:.58rem}
.mc-state-icon{width:23px;height:23px;background:#fff;display:inline-flex;align-items:center;justify-content:center}.mc-billed,.mc-status-pill{font-size:.54rem}
.mc-body{gap:.3rem;padding:.36rem .42rem}.mc-info-grid{grid-template-columns:minmax(0,1fr) auto;gap:.24rem .54rem;font-size:.67rem}.mc-associate{font-weight:790}.mc-net{font-size:.65rem;color:var(--r-green)}
.mc-footer{min-height:37px;gap:.3rem;padding:.27rem .3rem;border:1px solid var(--r-border);border-radius:8px;background:var(--r-soft)}.mc-footer-label{font-size:.56rem;color:var(--r-text-3)}
.mc-dist-bar-bg{height:6px;min-width:48px;max-width:116px}.mc-dist-text{font-size:.62rem}.mc-actions{gap:.2rem;flex-wrap:nowrap}
.btn-approve,.btn-reject,.btn-edit,.btn-distribute,.btn-delete-approved,.delivery-note-trigger{display:inline-flex;align-items:center;justify-content:center;min-height:30px;gap:.2rem;padding:.25rem .38rem;border:1px solid var(--r-border);border-radius:8px;background:#fff;font-size:.62rem;font-weight:760;line-height:1}
.btn-approve{border-color:rgba(22,138,77,.14);background:var(--r-green-soft);color:var(--r-green)}.btn-reject,.btn-delete-approved{border-color:rgba(207,63,63,.12);background:var(--r-red-soft);color:var(--r-red)}
.btn-edit{border-color:rgba(37,99,235,.12);background:var(--r-blue-soft);color:var(--r-blue)}.btn-distribute{border-color:rgba(124,58,237,.13);background:var(--r-violet-soft);color:var(--r-violet)}
.delivery-note-trigger{background:#fff;color:var(--r-slate)}.mc-actions svg,.delivery-note-trigger svg{width:13px;height:13px}

/* paginação */
.delivery-pagination{min-height:47px;gap:.4rem;padding:.42rem .54rem;border-top:1px solid var(--r-border);background:linear-gradient(180deg,#fff,var(--r-soft))}
.delivery-pagination-info{font-size:.63rem;color:var(--r-text-3)}.delivery-pagination-actions{gap:.24rem}.delivery-page-size,.delivery-page-btn{min-height:33px;padding:.3rem .42rem;border:1px solid var(--r-border);border-radius:8px;background:#fff;color:var(--r-text-2);font-size:.63rem}
.delivery-page-btn{display:inline-flex;align-items:center;justify-content:center;gap:.2rem;font-weight:760}

/* modais */
.modal-overlay{background:rgba(15,23,42,.46);backdrop-filter:blur(2px)}.modal-box{max-height:88dvh;border:1px solid var(--r-border);border-radius:16px 16px 0 0;background:#fff;box-shadow:0 -18px 48px rgba(15,23,42,.18)}
.modal-header{min-height:52px;padding:.54rem .62rem;border-bottom:1px solid var(--r-border);background:radial-gradient(circle at 100% 0,rgba(124,58,237,.06),transparent 11rem),linear-gradient(180deg,var(--r-soft),#fff)}
.modal-title{font-size:.78rem;font-weight:820}.modal-close{width:33px;height:33px;border:1px solid var(--r-border);border-radius:9px;background:#fff;display:inline-flex;align-items:center;justify-content:center}
.modal-search-wrap{padding:.44rem .56rem}.modal-search{min-height:39px;padding:.42rem .52rem;border:1px solid var(--r-border-2);border-radius:9px;font-size:.74rem}.modal-search:focus{border-color:var(--r-blue);box-shadow:0 0 0 3px rgba(37,99,235,.06)}
.modal-item{min-height:54px;gap:.44rem;padding:.44rem .58rem}.modal-item:hover,.modal-item.highlighted{background:linear-gradient(90deg,var(--r-blue-soft),#fff 74%)}
.mi-avatar{width:33px;height:33px;display:inline-flex;align-items:center;justify-content:center;background:var(--r-blue-soft);color:var(--r-blue);font-size:.68rem}.mi-avatar.product{background:var(--r-violet-soft);color:var(--r-violet)}.mi-avatar.project{background:var(--r-amber-soft);color:var(--r-amber)}
.mi-name{font-size:.74rem;font-weight:780}.mi-sub{font-size:.63rem}.mi-badge{font-size:.58rem}.mi-limit-summary{font-size:.59rem;gap:.16rem .44rem;margin-top:.2rem}.mi-limit-track{height:6px;margin-top:.24rem}
.mi-quota-edit{width:31px;min-height:31px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;color:var(--r-violet)}
.product-modal-actions{gap:.28rem;padding:.38rem .54rem;background:var(--r-soft)}.product-modal-actions .btn-small,.quota-footer .btn-small{min-height:33px;border-radius:8px;font-size:.63rem}
.product-modal-actions .btn-small.primary,.quota-footer .btn-small.primary{border-color:rgba(124,58,237,.14);background:var(--r-violet-soft);color:var(--r-violet)}.quota-footer .btn-small.danger{border-color:rgba(207,63,63,.14);background:var(--r-red-soft);color:var(--r-red)}
.quota-modal-box{width:min(640px,calc(100vw - 1rem))}.quota-body{gap:.52rem;padding:.58rem}.quota-picker-list{gap:.25rem}.quota-picker-item{min-height:52px;padding:.48rem .52rem;border-radius:8px}
.quota-summary-grid{gap:.28rem}.quota-metric{padding:.42rem;border:1px solid var(--r-border);border-radius:8px;background:var(--r-soft)}.quota-metric span{font-size:.57rem}.quota-metric strong{font-size:.68rem}.quota-progress{height:7px}.quota-edit-grid{gap:.45rem}.quota-slider{accent-color:var(--r-violet)}.quota-footer{padding:.46rem .56rem}

/* distribuição e toast */
.dist-summary-overlay{background:rgba(15,23,42,.44);backdrop-filter:blur(2px)}.dist-summary-box{border:1px solid var(--r-border);border-radius:15px}.dist-summary-head{padding:.58rem .64rem;background:linear-gradient(180deg,var(--r-soft),#fff)}.dist-summary-title{font-size:.78rem}.dist-summary-sub{font-size:.64rem}.dist-summary-body{gap:.28rem;padding:.5rem .58rem .6rem}.dist-summary-row{padding:.42rem .46rem;border-radius:8px;font-size:.69rem}
#toast-root{bottom:calc(1rem + env(safe-area-inset-bottom));width:min(420px,calc(100vw - 1.2rem))}.toast{width:fit-content;max-width:100%;padding:.48rem .68rem;border-radius:10px;font-size:.7rem;box-shadow:var(--r-shadow-md)}.toast.success{background:#166534}.toast.error{background:#991b1b}.toast.info{background:#334155}
.scroll-top-btn{display:none;right:.7rem;bottom:calc(4.8rem + env(safe-area-inset-bottom));width:40px;height:40px;border-radius:12px;color:var(--r-blue)}


.history-filter .hf-more{
    display:none;
    width:33px;
    min-width:33px;
    min-height:33px;
    padding:0;
    border:1px solid var(--r-border);
    border-radius:8px;
    background:#fff;
    color:var(--r-violet);
    cursor:pointer;
    align-items:center;
    justify-content:center;
}
.history-filter .hf-more svg{width:14px;height:14px}
.history-filter .hf-more[aria-expanded="true"]{
    border-color:rgba(124,58,237,.16);
    background:var(--r-violet-soft);
}

/* tablet/mobile */
@media(max-width:1019px){.reg-page{grid-template-columns:1fr}.entry-card{position:static}.history-card{min-height:0}.history-filter{grid-template-columns:minmax(200px,1fr) 132px minmax(130px,.7fr) minmax(130px,.7fr) 118px auto 118px auto;overflow-x:auto;scrollbar-width:thin}.history-filter>*{min-width:108px}.history-filter #filter-history-search{min-width:210px}.history-filter .hf-clear,.history-filter>span{min-width:auto}.scroll-top-btn{display:inline-flex;align-items:center;justify-content:center}}
@media(max-width:720px){
.reg-page{gap:.56rem;padding-bottom:.7rem}.reg-page .card{border-radius:13px}.project-bar{min-height:57px;padding:.45rem .5rem}.project-bar-icon{width:34px;height:34px}.project-bar-title{font-size:.76rem}.project-bar-sub{font-size:.6rem}
.reg-page .card-header{min-height:41px;padding:.46rem .54rem}.reg-page .card-body{padding:.48rem}.entry-card .card-body{gap:.34rem!important}
.selector-row{min-height:48px;padding:.38rem .44rem;gap:.38rem}.sel-icon{width:28px;height:28px}.sel-label{font-size:.55rem}.sel-value,.sel-date-display{font-size:.71rem}.sel-meta{font-size:.56rem}
.form-grid{grid-template-columns:minmax(0,1fr) minmax(108px,.7fr);gap:.38rem}.field-input,.q-pill{min-height:37px}.btn-submit{min-height:41px;margin-top:.44rem;font-size:.74rem}
.history-filter{grid-template-columns:minmax(0,1fr) 104px 32px 32px;gap:.26rem;overflow:visible;padding:.38rem .46rem}.history-filter>*{min-width:0}.history-filter #filter-history-search{min-width:0;grid-column:1}.history-filter #filter-status{grid-column:2}.history-filter #filter-associate,.history-filter #filter-product,.history-filter #filter-date-from,.history-filter #filter-date-to,.history-filter>span{display:none}.history-filter .hf-more{display:inline-flex;grid-column:3}.history-filter .hf-clear{grid-column:4;width:32px;min-width:32px;padding:0;font-size:0;border:1px solid var(--r-border);background:#fff}.history-filter .hf-clear::before{content:"×";font-size:.95rem;line-height:1}.history-filter.show-advanced #filter-associate,.history-filter.show-advanced #filter-product,.history-filter.show-advanced #filter-date-from,.history-filter.show-advanced #filter-date-to{display:block}.history-filter.show-advanced #filter-associate{grid-column:1/3}.history-filter.show-advanced #filter-product{grid-column:3/5}.history-filter.show-advanced #filter-date-from{grid-column:1/3}.history-filter.show-advanced #filter-date-to{grid-column:3/5}
#session-list{padding:.34rem;gap:.3rem}.mobile-card{border-radius:9px}.mc-head{min-height:34px;padding:.27rem .35rem}.mc-date{font-size:.58rem}.mc-head-product{font-size:.66rem}.mc-head-qty{font-size:.59rem}.mc-state-icon{width:21px;height:21px}.mc-body{padding:.3rem .35rem}.mc-info-grid{font-size:.63rem}.mc-footer{min-height:35px;padding:.23rem .25rem}.mc-footer-label{display:none}.mc-dist-bar-bg{max-width:none}.mc-actions{gap:.15rem}
.mc-actions .btn-approve,.mc-actions .btn-reject,.mc-actions .btn-edit,.mc-actions .btn-distribute,.mc-actions .btn-delete-approved,.mc-actions .delivery-note-trigger{width:31px;min-width:31px;height:31px;min-height:31px;padding:0;font-size:0}.mc-action-label{display:none}.mc-actions svg,.delivery-note-trigger svg{width:13px;height:13px}
.delivery-pagination{padding:.36rem .42rem}.delivery-pagination-info{font-size:.59rem}.delivery-page-size{max-width:106px}.delivery-page-btn{width:33px;min-width:33px;padding:0;font-size:0}.delivery-page-label{display:none}.delivery-page-btn svg{width:13px;height:13px}
.modal-overlay{align-items:flex-end;padding:0}.modal-box,.quota-modal-box{width:100%;max-width:none;max-height:88dvh;border-right:0;border-bottom:0;border-left:0;border-radius:16px 16px 0 0}.modal-header{min-height:49px;padding:.46rem .54rem}.modal-search-wrap{padding:.36rem .46rem}.modal-item{min-height:50px;padding:.38rem .48rem}.mi-avatar{width:30px;height:30px}.mi-name{font-size:.7rem}.mi-sub{font-size:.59rem}.mi-limit-summary{font-size:.55rem}
.product-modal-actions{grid-template-columns:38px minmax(0,1fr);padding:.34rem .46rem}.product-modal-actions .btn-small{min-height:35px}#refresh-products-btn{width:38px;padding:0;font-size:0}#refresh-products-btn svg{width:14px;height:14px}#add-product-limit-btn{font-size:.61rem}
.quota-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.quota-footer{padding-bottom:calc(.48rem + env(safe-area-inset-bottom))}
.dist-summary-overlay{align-items:flex-end;padding:0}.dist-summary-box{width:100%;max-height:72dvh;border-right:0;border-bottom:0;border-left:0;border-radius:16px 16px 0 0}
#toast-root{bottom:calc(5rem + env(safe-area-inset-bottom))}
}
@media(max-width:390px){.form-grid{grid-template-columns:minmax(0,1fr) 102px}.history-filter{grid-template-columns:minmax(0,1fr) 94px 31px 31px}.mc-billed{display:none}.mc-head-product{max-width:34vw}.mc-dist-text{min-width:27px;text-align:right}}
@media(max-width:340px){.form-grid{grid-template-columns:1fr}.form-grid>div:nth-child(3){grid-column:auto}.mc-head-qty{display:none}}
@media(prefers-reduced-motion:reduce){.reg-page *,.modal-overlay *{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}
</style>


<style id="register-desktop-system-ui">
/* =====================================================================
   REGISTER — padronização desktop/mobile
   Desktop: superfície operacional contínua, sem aparência de cards.
   Mobile: cards confortáveis, com áreas de toque maiores.
   ===================================================================== */

.reg-page {
    --rx-green:#168a4d;
    --rx-green-soft:#eaf8ef;
    --rx-blue:#2563eb;
    --rx-blue-soft:#eef4ff;
    --rx-violet:#7c3aed;
    --rx-violet-soft:#f4f0ff;
    --rx-amber:#c87408;
    --rx-amber-soft:#fff7e8;
    --rx-red:#cf3f3f;
    --rx-red-soft:#fff0f0;
    --rx-slate:#64748b;
    --rx-slate-soft:#f1f5f9;
    --rx-border:var(--color-border,#dce7e0);
    --rx-border-2:var(--color-border-strong,#c8d6cd);
    --rx-text:var(--color-text,#102018);
    --rx-text-2:var(--color-text-secondary,#52645a);
    --rx-text-3:var(--color-text-muted,#809087);
    --rx-soft:var(--color-surface-soft,#f8faf9);
    --rx-surface:var(--color-surface,#fff);
}

/* ícones sempre centralizados */
.reg-page .project-bar-icon,
.reg-page .sel-icon,
.reg-page .sel-chevron,
.reg-page .reg-integrity-toggle,
.reg-page .mc-state-icon,
.reg-page .mc-actions button,
.reg-page .delivery-note-trigger,
.reg-page .delivery-page-btn,
.modal-overlay .modal-close,
.modal-overlay .mi-quota-edit,
.scroll-top-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    line-height:0;
}
.reg-page svg,
.modal-overlay svg,
.scroll-top-btn svg {
    display:block;
    flex:0 0 auto;
    margin:0;
    vertical-align:middle;
}

/* ---------------------------------------------------------------------
   DESKTOP / TABLET — sem cards
   --------------------------------------------------------------------- */
@media (min-width: 768px) {
    .reg-page {
        display:grid;
        width: 100%;
        max-width:1280px;
        grid-template-columns:minmax(0,1fr);
        gap:0;
        margin:0 auto;
        padding:0 0 1rem;
        overflow:hidden;
        border:1px solid var(--rx-border);
        border-radius:15px;
        background:#fff;
        box-shadow:0 4px 14px rgba(15,35,24,.04);
    }

    /* Os wrappers permanecem por compatibilidade, mas deixam de parecer cards. */
    .reg-page .card,
    .reg-page .entry-card,
    .reg-page .history-card {
        width:100%;
        min-width:0;
        margin:0;
        overflow:visible;
        border:0;
        border-radius:0;
        background:transparent;
        box-shadow:none;
    }

    .reg-page .entry-card {
        position:static !important;
        top:auto !important;
        border-bottom:1px solid var(--rx-border);
    }

    .reg-page .history-card {
        min-height:0 !important;
    }

    /* Projeto = toolbar de contexto, não card. */
    .project-bar {
        min-height:58px;
        gap:.56rem;
        padding:.52rem .72rem;
        border:0;
        border-bottom:1px solid var(--rx-border);
        border-radius:0;
        background:
            radial-gradient(circle at 100% 0,rgba(37,99,235,.07),transparent 18rem),
            linear-gradient(180deg,var(--rx-soft),#fff);
        box-shadow:none;
    }
    .project-bar-icon {
        width:34px;
        height:34px;
        border-radius:9px;
        background:var(--rx-blue-soft);
        color:var(--rx-blue);
    }
    .project-bar-title {font-size:.82rem;font-weight:820;color:var(--rx-text)}
    .project-bar-sub {font-size:.65rem;color:var(--rx-text-3)}
    .project-bar-btn {
        min-height:34px;
        padding:.34rem .52rem;
        border:1px solid var(--rx-border-2);
        border-radius:8px;
        background:#fff;
        color:var(--rx-blue);
        font-size:.66rem;
        font-weight:780;
    }
    #pb-badge {
        min-height:23px !important;
        padding:.12rem .36rem !important;
        border:1px solid rgba(22,138,77,.13) !important;
        background:var(--rx-green-soft) !important;
        color:var(--rx-green) !important;
        font-size:.57rem !important;
    }

    /* Cabeçalho de seção simples. */
    .reg-page .card-header {
        display:flex;
        min-height:38px;
        align-items:center;
        padding:.42rem .72rem;
        border:0;
        border-bottom:1px solid var(--rx-border);
        border-radius:0;
        background:#fff;
        color:var(--rx-text-2);
        font-size:.65rem;
        font-weight:800;
        letter-spacing:.025em;
        text-transform:uppercase;
    }

    /* Formulário de entrada em fluxo horizontal. */
    .entry-card .card-body {
        display:grid !important;
        grid-template-columns:minmax(190px,1fr) minmax(165px,.72fr) minmax(230px,1.18fr);
        gap:0;
        padding:0 !important;
        background:#fff;
    }

    .selector-row {
        min-height:62px;
        gap:.48rem;
        padding:.52rem .64rem;
        border:0;
        border-right:1px solid var(--rx-border);
        border-radius:0;
        background:#fff;
        box-shadow:none;
    }
    .selector-row:nth-of-type(3) {border-right:0}
    .selector-row:hover,
    .selector-row.selected {
        border-color:var(--rx-border);
        background:linear-gradient(180deg,var(--rx-blue-soft),#fff);
    }
    .selector-row:focus-visible {
        outline:2px solid rgba(37,99,235,.22);
        outline-offset:-2px;
    }
    .sel-icon {
        width:31px;
        height:31px;
        border-radius:8px;
    }
    .sel-label {font-size:.58rem;font-weight:760;letter-spacing:.025em}
    .sel-value,.sel-date-display {font-size:.76rem;font-weight:790}
    .sel-meta {font-size:.59rem;line-height:1.3}

    #entry-fields {
        grid-column:1/-1;
        min-width:0;
        padding:.56rem .64rem .64rem;
        border-top:1px solid var(--rx-border);
        background:var(--rx-soft);
    }
    #entry-fields .form-divider {display:none}
    #entry-fields .form-grid {
        display:grid;
        grid-template-columns:minmax(150px,.75fr) minmax(180px,.9fr) minmax(260px,1.5fr);
        gap:.48rem;
        align-items:end;
    }
    #entry-fields .form-grid>div:nth-child(3) {grid-column:auto}
    .field-label {
        margin-bottom:.2rem;
        font-size:.59rem;
        font-weight:760;
        letter-spacing:.02em;
        text-transform:none;
    }
    .field-input {
        min-height:39px;
        padding:.42rem .52rem;
        border:1px solid var(--rx-border-2);
        border-radius:8px;
        background:#fff;
        font-size:.76rem;
    }
    .field-input:focus {
        border-color:var(--rx-blue);
        box-shadow:0 0 0 3px rgba(37,99,235,.06);
    }
    .quality-pills {gap:.28rem}
    .q-pill {
        min-height:39px;
        padding:.34rem .25rem;
        border-radius:8px;
        background:#fff;
        font-size:.72rem;
    }
    .btn-submit {
        width:auto;
        min-width:170px;
        min-height:40px;
        margin:.48rem 0 0 auto;
        padding:.46rem .78rem;
        border:1px solid rgba(22,138,77,.18);
        border-radius:8px;
        background:var(--rx-green-soft);
        box-shadow:none;
        color:var(--rx-green);
        font-size:.72rem;
        font-weight:820;
    }
    .btn-submit:hover:not(:disabled) {
        background:color-mix(in srgb,var(--rx-green-soft) 80%,#fff);
        color:var(--rx-green);
    }

    /* Histórico = seção contínua. */
    .history-card>.card-header {
        min-height:44px;
        padding:.48rem .7rem !important;
        background:linear-gradient(180deg,var(--rx-soft),#fff);
        color:var(--rx-text);
        font-size:.72rem;
        letter-spacing:0;
        text-transform:none;
    }
    #session-count {
        min-height:22px;
        padding:.1rem .34rem;
        border-radius:999px;
        background:var(--rx-blue-soft);
        color:var(--rx-blue) !important;
        font-size:.59rem !important;
    }

    /* Integridade só ocupa espaço quando o backend a exibe. */
    .reg-integrity {
        border:0;
        border-bottom:1px solid var(--rx-border);
        background:linear-gradient(90deg,var(--rx-amber-soft),#fff 48%);
    }
    .reg-integrity-head {min-height:44px;padding:.38rem .68rem}
    .reg-integrity-counts {font-size:.6rem}
    .reg-integrity-toggle {width:30px;height:30px;border-radius:8px}
    .reg-integrity-list {padding:0 .68rem .56rem;gap:0}
    .reg-integrity-item {
        padding:.48rem .04rem;
        border:0;
        border-top:1px solid var(--rx-border);
        border-radius:0;
        background:transparent;
    }
    .reg-integrity-item:first-child {border-top:0}

    /* Filtros = toolbar contínua. */
    .history-filter {
        display:grid;
        grid-template-columns:minmax(220px,1.4fr) 130px minmax(150px,.8fr) minmax(150px,.8fr) 120px auto 120px auto;
        gap:.32rem;
        align-items:center;
        padding:.48rem .64rem;
        overflow:visible;
        border:0;
        border-bottom:1px solid var(--rx-border);
        background:var(--rx-soft);
    }
    .history-filter>label {display:none}
    .history-filter input,
    .history-filter select,
    .history-filter input[type=date],
    .history-filter input[type=search] {
        width:100%;
        min-width:0;
        max-width:none;
        min-height:36px;
        padding:.36rem .46rem;
        border:1px solid var(--rx-border-2);
        border-radius:8px;
        background:#fff;
        font-size:.68rem;
    }
    .history-filter input:focus,
    .history-filter select:focus {
        border-color:var(--rx-blue);
        box-shadow:0 0 0 3px rgba(37,99,235,.055);
    }
    .history-filter>span {font-size:.59rem!important;color:var(--rx-text-3)!important;text-align:center}
    .history-filter .hf-clear {
        min-height:34px;
        padding:.3rem .42rem;
        border:1px solid var(--rx-border);
        border-radius:8px;
        background:#fff;
        color:var(--rx-slate);
        font-size:.63rem;
        font-weight:740;
    }

    /* Lista: sem grid de cards e sem caixas individuais. */
    #session-list {
        display:block;
        min-height:0;
        padding:0;
        background:#fff;
    }
    .session-section-header {
        margin:0;
        padding:.42rem .68rem;
        border:0;
        border-bottom:1px solid var(--rx-border);
        background:var(--rx-soft);
        color:var(--rx-text-2);
        font-size:.61rem;
        letter-spacing:.035em;
    }
    .session-collapsible {
        margin:0;
        border:0;
        border-bottom:1px solid var(--rx-border);
        border-radius:0;
        background:#fff;
    }
    .session-collapsible>summary {
        min-height:39px;
        padding:.4rem .68rem;
        border:0;
        background:var(--rx-soft);
        font-size:.63rem;
    }
    .session-collapsible-list {display:block;padding:0}
    .session-empty {padding:1.25rem .8rem;font-size:.76rem}

    /*
       O mesmo markup dos cards é reaproveitado por segurança, mas no desktop
       ele vira uma linha operacional contínua. Cards visuais só no mobile.
    */
    #session-list .mobile-card {
        display:grid;
        grid-template-columns:minmax(300px,1.05fr) minmax(0,1.95fr);
        min-width:0;
        margin:0;
        overflow:visible;
        border:0;
        border-bottom:1px solid var(--rx-border);
        border-left:0;
        border-radius:0;
        background:#fff;
        box-shadow:none;
    }
    #session-list>.mobile-card:last-child,
    .session-collapsible-list>.mobile-card:last-child {border-bottom:0}
    #session-list .mobile-card:hover {background:color-mix(in srgb,var(--rx-blue-soft) 24%,#fff)}

    .mc-head {
        display:grid;
        min-width:0;
        grid-template-columns:minmax(0,1fr) auto auto auto;
        gap:.3rem;
        align-items:center;
        padding:.52rem .64rem;
        border:0;
        border-right:1px solid var(--rx-border);
        border-radius:0;
        background:transparent;
    }
    .mc-head-main {
        display:grid;
        min-width:0;
        grid-template-columns:72px minmax(0,1fr) auto;
        gap:.38rem;
        align-items:center;
        overflow:hidden;
    }
    .mc-head-main .mc-sep {display:none}
    .mc-date {font-size:.64rem;font-weight:760;color:var(--rx-text-3)}
    .mc-head-product {font-size:.72rem;font-weight:820}
    .mc-head-qty {font-size:.65rem;font-weight:780}
    .mc-state-icon {width:26px;height:26px;border-radius:8px}
    .mc-billed,.mc-status-pill {font-size:.56rem}

    .mc-body {
        display:grid;
        min-width:0;
        grid-template-columns:minmax(150px,.78fr) minmax(150px,.9fr) minmax(265px,1.35fr);
        gap:.55rem;
        align-items:center;
        padding:.44rem .62rem;
        border-radius:0;
        background:transparent;
    }
    .mc-info-grid {
        display:grid;
        min-width:0;
        grid-template-columns:minmax(0,1fr);
        gap:.1rem;
        font-size:.66rem;
    }
    .mc-associate {font-size:.68rem;font-weight:790}
    .mc-net {font-size:.63rem;color:var(--rx-green)}
    .mc-body>div[style*="display:grid"] {
        min-width:0;
        padding:.18rem 0 !important;
        font-size:.62rem !important;
    }

    .mc-footer {
        display:flex;
        min-width:0;
        min-height:36px;
        align-items:center;
        gap:.34rem;
        padding:0;
        border:0;
        border-radius:0;
        background:transparent;
    }
    .mc-footer-label {display:none}
    .mc-dist-indicator {min-width:92px;max-width:160px}
    .mc-dist-bar-bg {width:auto;min-width:52px;max-width:none;flex:1;height:6px}
    .mc-dist-text {font-size:.61rem}
    .mc-actions {
        display:flex;
        gap:.22rem;
        margin-left:auto;
        flex-wrap:nowrap;
        justify-content:flex-end;
    }
    .mc-actions .btn-approve,
    .mc-actions .btn-reject,
    .mc-actions .btn-edit,
    .mc-actions .btn-distribute,
    .mc-actions .btn-delete-approved,
    .mc-actions .delivery-note-trigger {
        min-height:31px;
        padding:.28rem .38rem;
        border:1px solid var(--rx-border);
        border-radius:8px;
        background:#fff;
        font-size:.61rem;
        font-weight:760;
    }
    .mc-actions .btn-approve {color:var(--rx-green);background:var(--rx-green-soft);border-color:rgba(22,138,77,.13)}
    .mc-actions .btn-reject,
    .mc-actions .btn-delete-approved {color:var(--rx-red);background:#fff;border-color:rgba(207,63,63,.13)}
    .mc-actions .btn-edit {color:var(--rx-blue)}
    .mc-actions .btn-distribute {color:var(--rx-violet);background:var(--rx-violet-soft);border-color:rgba(124,58,237,.14)}
    .mc-actions svg,.delivery-note-trigger svg {width:13px;height:13px}

    .delivery-pagination {
        min-height:46px;
        margin:0;
        padding:.44rem .64rem;
        border:0;
        border-top:1px solid var(--rx-border);
        background:var(--rx-soft);
    }

    /* No desktop, modais continuam caixas centralizadas; cards não se aplicam. */
    .modal-overlay {align-items:center;padding:1rem}
    .modal-box,.quota-modal-box {
        width:min(620px,calc(100vw - 2rem));
        max-width:620px;
        max-height:82dvh;
        border:1px solid var(--rx-border);
        border-radius:14px;
        box-shadow:0 22px 58px rgba(15,23,42,.18);
    }
}

/* Notebook/tablet: linha continua em duas faixas, ainda sem cards. */
@media (min-width:768px) and (max-width:1040px) {
    .entry-card .card-body {
        grid-template-columns:1fr 1fr;
    }
    .selector-row:nth-of-type(2) {border-right:0}
    .selector-row:nth-of-type(3) {
        grid-column:1/-1;
        border-top:1px solid var(--rx-border);
    }
    #entry-fields .form-grid {grid-template-columns:1fr 1fr}
    #entry-fields .form-grid>div:nth-child(3) {grid-column:1/-1}

    .history-filter {
        grid-template-columns:minmax(190px,1fr) 120px minmax(120px,.7fr) minmax(120px,.7fr) 110px auto 110px auto;
        overflow-x:auto;
        scrollbar-width:thin;
    }

    #session-list .mobile-card {
        grid-template-columns:1fr;
    }
    .mc-head {
        border-right:0;
        border-bottom:1px solid color-mix(in srgb,var(--rx-border) 78%,transparent);
        padding:.45rem .62rem;
    }
    .mc-body {
        grid-template-columns:minmax(150px,.8fr) minmax(150px,.9fr) minmax(250px,1.3fr);
        padding:.4rem .62rem .48rem;
    }
}

/* ---------------------------------------------------------------------
   MOBILE — cards de verdade, confortáveis e tocáveis
   --------------------------------------------------------------------- */
@media (max-width:767px) {
    .reg-page {
        display:grid;
        width:100%;
        gap:.58rem;
        padding:0 0 .8rem;
        border:0;
        background:transparent;
        box-shadow:none;
    }

    .reg-page .card {
        overflow:hidden;
        border:1px solid var(--rx-border);
        border-radius:13px;
        background:#fff;
        box-shadow:0 3px 12px rgba(15,35,24,.04);
    }

    .project-bar {
        min-height:60px;
        padding:.5rem .56rem;
        border-bottom:1px solid var(--rx-border);
        background:
            radial-gradient(circle at 100% 0,rgba(37,99,235,.07),transparent 12rem),
            linear-gradient(180deg,var(--rx-blue-soft),#fff);
    }
    .project-bar-icon {width:36px;height:36px}
    .project-bar-title {font-size:.79rem}
    .project-bar-sub {font-size:.63rem}

    .reg-page .card-header {min-height:43px;padding:.48rem .58rem}
    .reg-page .card-body {padding:.54rem}
    .entry-card .card-body {gap:.42rem!important}

    .selector-row {
        min-height:54px;
        gap:.46rem;
        padding:.46rem .52rem;
        border:1px solid var(--rx-border);
        border-radius:10px;
        background:#fff;
    }
    .sel-icon {width:31px;height:31px}
    .sel-label {font-size:.59rem}
    .sel-value,.sel-date-display {font-size:.76rem}
    .sel-meta {font-size:.6rem}

    .form-grid {grid-template-columns:minmax(0,1fr) minmax(116px,.72fr);gap:.44rem}
    .form-grid>div:nth-child(3) {grid-column:1/-1}
    .field-input,.q-pill {min-height:41px}
    .field-input {font-size:.78rem}
    .q-pill {font-size:.74rem}
    .btn-submit {min-height:45px;font-size:.76rem}

    .history-filter {
        grid-template-columns:minmax(0,1fr) 106px 38px 38px;
        gap:.3rem;
        padding:.42rem .48rem;
    }
    .history-filter #filter-history-search {grid-column:1;min-width:0}
    .history-filter #filter-status {grid-column:2}
    .history-filter .hf-more {display:inline-flex;width:38px;min-width:38px;min-height:38px;grid-column:3}
    .history-filter .hf-clear {
        width:38px;
        min-width:38px;
        min-height:38px;
        grid-column:4;
        padding:0;
        border:1px solid var(--rx-border);
        background:#fff;
        font-size:0;
    }
    .history-filter .hf-clear::before {content:"×";font-size:1rem;line-height:1}

    #session-list {display:grid;padding:.48rem;gap:.48rem}
    .session-section-header {padding:.32rem .08rem .08rem;border:0;background:transparent}
    .session-collapsible {border:1px solid var(--rx-border);border-radius:11px;background:var(--rx-soft)}
    .session-collapsible>summary {min-height:42px;padding:.48rem .58rem}
    .session-collapsible-list {display:grid;gap:.48rem;padding:0 .48rem .48rem}

    #session-list .mobile-card {
        display:block;
        overflow:hidden;
        border:1px solid var(--rx-border);
        border-left:3px solid var(--delivery-state);
        border-radius:11px;
        background:#fff;
        box-shadow:none;
    }
    .mc-head {
        min-height:42px;
        padding:.36rem .46rem;
        background:linear-gradient(90deg,var(--delivery-state-bg),#fff 72%);
    }
    .mc-date {font-size:.62rem}
    .mc-head-product {font-size:.72rem}
    .mc-head-qty {font-size:.64rem}
    .mc-state-icon {width:24px;height:24px}
    .mc-body {gap:.38rem;padding:.45rem .48rem}
    .mc-info-grid {grid-template-columns:minmax(0,1fr) auto;font-size:.68rem}
    .mc-associate {font-size:.7rem}
    .mc-net {font-size:.67rem}
    .mc-footer {
        min-height:42px;
        gap:.34rem;
        padding:.3rem .34rem;
        border:1px solid var(--rx-border);
        border-radius:9px;
        background:var(--rx-soft);
    }
    .mc-footer-label {display:none}
    .mc-dist-indicator {min-width:0;flex:1}
    .mc-dist-bar-bg {max-width:none}
    .mc-dist-text {font-size:.65rem}
    .mc-actions {gap:.22rem;flex-wrap:nowrap}
    .mc-actions .btn-approve,
    .mc-actions .btn-reject,
    .mc-actions .btn-edit,
    .mc-actions .btn-distribute,
    .mc-actions .btn-delete-approved,
    .mc-actions .delivery-note-trigger {
        width:38px;
        min-width:38px;
        height:38px;
        min-height:38px;
        padding:0;
        border-radius:9px;
        font-size:0;
    }
    .mc-actions svg,.delivery-note-trigger svg {width:15px;height:15px}
    .mc-action-label {display:none}

    .delivery-pagination {padding:.42rem .48rem}
    .delivery-page-btn {width:38px;min-width:38px;min-height:38px;padding:0;font-size:0}
    .delivery-page-size {min-height:38px}

    .modal-overlay {align-items:flex-end;padding:0}
    .modal-box,.quota-modal-box {
        width:100%;
        max-width:none;
        max-height:90dvh;
        border-right:0;
        border-bottom:0;
        border-left:0;
        border-radius:16px 16px 0 0;
    }
}

@media (max-width:390px) {
    .form-grid {grid-template-columns:1fr}
    .form-grid>div:nth-child(3) {grid-column:auto}
    .history-filter {grid-template-columns:minmax(0,1fr) 96px 38px 38px}
    .mc-footer {flex-wrap:wrap}
    .mc-dist-indicator {flex:1 1 100%}
    .mc-actions {width:100%;justify-content:flex-end}
}

@media (prefers-reduced-motion:reduce) {
    .reg-page *,
    .reg-page *::before,
    .reg-page *::after {
        animation-duration:.01ms!important;
        animation-iteration-count:1!important;
        transition-duration:.01ms!important;
        scroll-behavior:auto!important;
    }
}

</style>

<style id="register-deliveries-refinement">
/* =====================================================================
   REGISTRO DE ENTREGAS — refinamento visual
   Mesma linguagem de project-deliveries, sem alterar regras de negócio.
   ===================================================================== */

.reg-page {
    --rr-green: #168a4d;
    --rr-green-soft: #eaf8ef;
    --rr-blue: #2563eb;
    --rr-blue-soft: #eef4ff;
    --rr-sky: #0284c7;
    --rr-sky-soft: #edf8fe;
    --rr-violet: #7c3aed;
    --rr-violet-soft: #f4f0ff;
    --rr-amber: #c87408;
    --rr-amber-soft: #fff7e8;
    --rr-red: #cf3f3f;
    --rr-red-soft: #fff0f0;
    --rr-slate: #64748b;
    --rr-slate-soft: #f1f5f9;

    --rr-border: var(--color-border, #dce7e0);
    --rr-border-strong: var(--color-border-strong, #c8d6cd);
    --rr-text: var(--color-text, #102018);
    --rr-text-2: var(--color-text-secondary, #52645a);
    --rr-text-3: var(--color-text-muted, #809087);
    --rr-soft: var(--color-surface-soft, #f8faf9);
    --rr-surface: var(--color-surface, #fff);

    width: 100%;
    max-width: none;
    min-width: 0;
    margin: 0;
    padding: 0 0 1rem;
    overflow: visible;
    border: 0;
    background: transparent;
    box-shadow: none;
}

/* ---------- Cards principais ---------- */

.reg-page .entry-card,
.reg-page .history-card {
    min-width: 0;
    overflow: hidden;
    border: 1px solid var(--rr-border);
    border-radius: 15px;
    background: #fff;
    box-shadow: 0 4px 14px rgba(15, 35, 24, .04);
}

.reg-page .entry-card {
    align-self: start;
}

.reg-page .history-card {
    min-height: 0 !important;
}

/* Desktop: registro fixo à esquerda, histórico livre à direita. */
@media (min-width: 1024px) {
    .reg-page {
        display: grid;
        grid-template-columns: minmax(330px, 390px) minmax(0, 1fr);
        gap: .82rem;
        align-items: start;
    }

    .reg-page .entry-card {
        position: sticky !important;
        top: var(--app-sticky-top, 92px) !important;
        z-index: 14;
    }
}

/* Tablet/mobile: fluxo único. */
@media (max-width: 1023px) {
    .reg-page {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: .66rem;
    }

    .reg-page .entry-card {
        position: static !important;
        top: auto !important;
    }
}

/* ---------- Cabeçalho do projeto ---------- */

.reg-page .project-bar {
    min-height: 64px;
    gap: .55rem;
    padding: .58rem .64rem;
    border: 0;
    border-bottom: 1px solid var(--rr-border);
    border-radius: 0;
    background:
        radial-gradient(circle at 100% 0, rgba(37,99,235,.075), transparent 15rem),
        linear-gradient(180deg, var(--rr-soft), #fff);
}

.reg-page .project-bar-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--rr-blue-soft);
    color: var(--rr-blue);
}

.reg-page .project-bar-title {
    color: var(--rr-text);
    font-size: .85rem;
    font-weight: 850;
    letter-spacing: -.015em;
}

.reg-page .project-bar-sub {
    margin-top: .03rem;
    color: var(--rr-text-3);
    font-size: .66rem;
    line-height: 1.3;
}

.reg-page .project-bar-btn {
    min-height: 35px;
    padding: .36rem .52rem;
    border: 1px solid rgba(37,99,235,.18);
    border-radius: 9px;
    background: var(--rr-blue-soft);
    color: var(--rr-blue);
    font-size: .66rem;
    font-weight: 800;
}

.reg-page #pb-badge {
    min-height: 24px !important;
    display: inline-flex;
    align-items: center;
    padding: .12rem .38rem !important;
    border: 1px solid rgba(22,138,77,.14) !important;
    background: var(--rr-green-soft) !important;
    color: var(--rr-green) !important;
    font-size: .57rem !important;
    font-weight: 820 !important;
}

/* ---------- Títulos ---------- */

.reg-page .card-header {
    min-height: 48px;
    padding: .52rem .62rem;
    border: 0;
    border-bottom: 1px solid var(--rr-border);
    background: #fff;
    color: var(--rr-text);
    font-size: .73rem;
    font-weight: 830;
    letter-spacing: 0;
    text-transform: none;
}

.reg-entry-title,
.reg-history-head,
.reg-history-title {
    display: flex !important;
    align-items: center;
}

.reg-entry-title {
    gap: .4rem;
}

.reg-history-head {
    justify-content: space-between !important;
    gap: .55rem;
}

.reg-history-title {
    min-width: 0;
    gap: .42rem;
}

.reg-history-title #session-list-title {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.reg-section-icon {
    display: inline-flex;
    width: 31px;
    height: 31px;
    flex: 0 0 31px;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
}

.reg-section-icon-amber {
    background: var(--rr-amber-soft);
    color: var(--rr-amber);
}

.reg-section-icon-violet {
    background: var(--rr-violet-soft);
    color: var(--rr-violet);
}

#session-count {
    display: inline-flex;
    min-height: 24px;
    align-items: center;
    padding: .12rem .38rem;
    border-radius: 999px;
    background: var(--rr-blue-soft);
    color: var(--rr-blue) !important;
    font-size: .59rem !important;
    font-weight: 820 !important;
    text-transform: none;
    letter-spacing: 0;
    white-space: nowrap;
}

/* ---------- Seletores ---------- */

.entry-card .card-body {
    display: grid !important;
    gap: .38rem !important;
    padding: .54rem !important;
    background: #fff;
}

.selector-row {
    --sel-tone: var(--rr-blue);
    --sel-soft: var(--rr-blue-soft);

    position: relative;
    min-height: 56px;
    gap: .46rem;
    padding: .46rem .5rem;
    border: 1px solid var(--rr-border);
    border-radius: 10px;
    background: #fff;
    box-shadow: none;
    transition:
        border-color .15s ease,
        background .15s ease,
        transform .15s ease;
}

#sel-date {
    --sel-tone: var(--rr-amber);
    --sel-soft: var(--rr-amber-soft);
}

#sel-product {
    --sel-tone: var(--rr-violet);
    --sel-soft: var(--rr-violet-soft);
}

.selector-row:hover:not(.disabled),
.selector-row:focus-visible:not(.disabled) {
    border-color: color-mix(in srgb, var(--sel-tone) 25%, var(--rr-border));
    background: linear-gradient(90deg, var(--sel-soft), #fff 78%);
    outline: none;
}

.selector-row.selected {
    border-color: color-mix(in srgb, var(--sel-tone) 22%, var(--rr-border));
    background: linear-gradient(90deg, var(--sel-soft), #fff 72%);
}

.selector-row.disabled {
    opacity: .52;
    background: var(--rr-soft);
}

.sel-icon {
    width: 32px;
    height: 32px;
    border-radius: 9px;
    background: var(--sel-soft);
    color: var(--sel-tone);
}

.selector-row.selected .sel-icon {
    background: var(--sel-soft);
    color: var(--sel-tone);
}

.sel-label {
    color: var(--rr-text-3);
    font-size: .58rem;
    font-weight: 760;
    letter-spacing: .025em;
}

.sel-value,
.sel-date-display {
    margin-top: .02rem;
    color: var(--rr-text);
    font-size: .77rem;
    font-weight: 810;
}

.sel-meta {
    color: var(--rr-text-3);
    font-size: .6rem;
    line-height: 1.3;
}

.sel-chevron {
    color: color-mix(in srgb, var(--sel-tone) 65%, var(--rr-text-3));
}

/* ---------- Campos da entrega ---------- */

#entry-fields {
    min-width: 0;
    margin-top: .04rem;
    padding: .54rem;
    border: 1px solid var(--rr-border);
    border-radius: 11px;
    background:
        radial-gradient(circle at 100% 0, rgba(22,138,77,.055), transparent 12rem),
        var(--rr-soft);
}

#entry-fields .form-divider {
    display: none;
}

#entry-fields .form-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(118px, .72fr);
    gap: .42rem;
    align-items: start;
}

#entry-fields .reg-notes-control {
    grid-column: 1 / -1;
}

.field-label {
    margin-bottom: .2rem;
    color: var(--rr-text-3);
    font-size: .59rem;
    font-weight: 760;
    letter-spacing: .02em;
    text-transform: none;
}

.field-input {
    min-height: 41px;
    padding: .43rem .52rem;
    border: 1px solid var(--rr-border-strong);
    border-radius: 9px;
    background: #fff;
    color: var(--rr-text);
    font-size: .77rem;
}

.field-input:focus {
    border-color: var(--rr-blue);
    box-shadow: 0 0 0 3px rgba(37,99,235,.06);
}

.quality-pills {
    gap: .28rem;
}

.q-pill {
    min-height: 41px;
    padding: .34rem .25rem;
    border: 1px solid var(--rr-border-strong);
    border-radius: 9px;
    background: #fff;
    color: var(--rr-text-2);
    font-size: .73rem;
}

.q-pill.active {
    border-color: rgba(22,138,77,.16);
    background: var(--rr-green-soft);
    color: var(--rr-green);
}

.q-pill[data-q="B"].active {
    border-color: rgba(200,116,8,.18);
    background: var(--rr-amber-soft);
    color: var(--rr-amber);
}

.q-pill[data-q="C"].active {
    border-color: rgba(207,63,63,.16);
    background: var(--rr-red-soft);
    color: var(--rr-red);
}

/* Observação discreta: permanece disponível inclusive no mobile. */

.reg-notes-control {
    min-width: 0;
}

.reg-notes-toggle {
    display: grid;
    width: 100%;
    min-height: 41px;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: .42rem;
    align-items: center;
    padding: .36rem .44rem;
    border: 1px solid var(--rr-border);
    border-radius: 9px;
    background: #fff;
    color: var(--rr-text-2);
    cursor: pointer;
    font: inherit;
    text-align: left;
    transition: .15s ease;
}

.reg-notes-toggle:hover,
.reg-notes-toggle:focus-visible,
.reg-notes-toggle.open {
    border-color: rgba(100,116,139,.24);
    background: var(--rr-slate-soft);
    outline: none;
}

.reg-notes-toggle.has-value {
    border-color: rgba(37,99,235,.16);
    background: var(--rr-blue-soft);
    color: var(--rr-blue);
}

.reg-notes-toggle-icon {
    display: inline-flex;
    width: 28px;
    height: 28px;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: var(--rr-slate-soft);
    color: var(--rr-slate);
}

.reg-notes-toggle.has-value .reg-notes-toggle-icon {
    background: #fff;
    color: var(--rr-blue);
}

.reg-notes-toggle-copy {
    display: grid;
    min-width: 0;
    gap: .02rem;
}

.reg-notes-toggle-copy strong {
    color: currentColor;
    font-size: .67rem;
    font-weight: 790;
}

.reg-notes-toggle-copy small {
    color: var(--rr-text-3);
    font-size: .56rem;
}

.reg-notes-toggle-chevron {
    transition: transform .16s ease;
}

.reg-notes-toggle.open .reg-notes-toggle-chevron {
    transform: rotate(180deg);
}

.reg-notes-field {
    margin-top: .32rem;
}

.reg-notes-field[hidden] {
    display: none !important;
}

.btn-submit {
    width: 100%;
    min-height: 44px;
    margin-top: .48rem;
    padding: .48rem .7rem;
    border: 1px solid rgba(22,138,77,.18);
    border-radius: 10px;
    background: var(--rr-green-soft);
    box-shadow: none;
    color: var(--rr-green);
    font-size: .74rem;
    font-weight: 840;
}

.btn-submit:hover:not(:disabled),
.btn-submit:focus-visible:not(:disabled) {
    border-color: rgba(22,138,77,.27);
    background: color-mix(in srgb, var(--rr-green-soft) 80%, #fff);
    color: var(--rr-green);
    outline: none;
}

/* ---------- Central de inconsistências ---------- */

.reg-integrity {
    border: 0;
    border-bottom: 1px solid var(--rr-border);
    background: linear-gradient(90deg, var(--rr-amber-soft), #fff 56%);
}

.reg-integrity-head {
    min-height: 44px;
    padding: .42rem .62rem;
}

.reg-integrity-counts {
    gap: .34rem;
    font-size: .6rem;
}

.reg-integrity-toggle {
    width: 31px;
    height: 31px;
    border: 1px solid var(--rr-border);
    border-radius: 9px;
    background: #fff;
}

.reg-integrity-list {
    padding: 0 .62rem .56rem;
    gap: .3rem;
}

.reg-integrity-item {
    padding: .46rem .5rem;
    border: 1px solid var(--rr-border);
    border-left: 3px solid var(--rr-amber);
    border-radius: 9px;
    background: #fff;
}

.reg-integrity-item.critical {
    border-left-color: var(--rr-red);
}

.reg-integrity-item.info {
    border-left-color: var(--rr-blue);
}

/* ---------- Filtros do histórico ---------- */

.history-filter {
    display: grid;
    grid-template-columns: minmax(200px, 1fr) 130px auto auto;
    gap: .32rem;
    align-items: center;
    padding: .48rem .56rem;
    overflow: visible;
    border: 0;
    border-bottom: 1px solid var(--rr-border);
    background: var(--rr-soft);
}

.history-filter > label,
.history-filter #filter-associate,
.history-filter #filter-product,
.history-filter #filter-date-from,
.history-filter #filter-date-to,
.history-filter > span {
    display: none;
}

.history-filter.filters-expanded {
    grid-template-columns:
        minmax(200px, 1.25fr)
        130px
        minmax(140px,.75fr)
        minmax(140px,.75fr)
        120px
        auto
        120px
        auto
        auto;
}

.history-filter.filters-expanded #filter-associate,
.history-filter.filters-expanded #filter-product,
.history-filter.filters-expanded #filter-date-from,
.history-filter.filters-expanded #filter-date-to,
.history-filter.filters-expanded > span {
    display: block;
}

.history-filter input,
.history-filter select,
.history-filter input[type=date],
.history-filter input[type=search] {
    width: 100%;
    min-width: 0;
    max-width: none;
    min-height: 38px;
    padding: .38rem .46rem;
    border: 1px solid var(--rr-border-strong);
    border-radius: 9px;
    background: #fff;
    color: var(--rr-text);
    font-size: .68rem;
}

.history-filter input:focus,
.history-filter select:focus {
    border-color: var(--rr-blue);
    box-shadow: 0 0 0 3px rgba(37,99,235,.055);
}

.history-filter .hf-more,
.history-filter .hf-clear {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--rr-border);
    border-radius: 9px;
    background: #fff;
    color: var(--rr-slate);
    font: inherit;
    font-size: .64rem;
    font-weight: 760;
}

.history-filter .hf-more {
    width: 38px;
    min-width: 38px;
    padding: 0;
}

.history-filter .hf-more[aria-expanded="true"] {
    border-color: rgba(124,58,237,.16);
    background: var(--rr-violet-soft);
    color: var(--rr-violet);
}

.history-filter .hf-clear::before {
    content: none !important;
}

.history-filter .hf-clear {
    gap: .25rem;
    padding: 0 .46rem;
}

.history-filter .hf-clear .ph-duotone {
    font-size: 14px !important;
}

/* ---------- Histórico / itens ---------- */

#session-list {
    display: grid;
    min-height: 0;
    grid-template-columns: minmax(0, 1fr);
    gap: .48rem;
    padding: .52rem;
    background: var(--rr-soft);
}

@media (min-width: 1320px) {
    #session-list {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .session-section-header,
    .session-collapsible,
    .session-empty {
        grid-column: 1 / -1;
    }
}

.session-section-header {
    margin: 0;
    padding: .28rem .08rem .04rem;
    border: 0;
    background: transparent;
    color: var(--rr-text-3);
    font-size: .59rem;
    font-weight: 810;
    letter-spacing: .035em;
}

.session-collapsible {
    overflow: hidden;
    border: 1px solid var(--rr-border);
    border-radius: 11px;
    background: #fff;
}

.session-collapsible > summary {
    min-height: 42px;
    padding: .45rem .56rem;
    background: var(--rr-soft);
    color: var(--rr-text-2);
    font-size: .63rem;
}

.session-collapsible-list {
    display: grid;
    gap: .48rem;
    padding: .48rem;
}

#session-list .mobile-card {
    --delivery-state: var(--rr-slate);
    --delivery-state-bg: var(--rr-slate-soft);

    display: block;
    min-width: 0;
    margin: 0;
    overflow: hidden;
    border: 1px solid var(--rr-border);
    border-left: 3px solid var(--delivery-state);
    border-radius: 11px;
    background: #fff;
    box-shadow: 0 2px 8px rgba(15,35,24,.025);
    transition:
        border-color .15s ease,
        box-shadow .15s ease,
        transform .15s ease;
}

#session-list .mobile-card:hover {
    border-color: color-mix(in srgb, var(--delivery-state) 22%, var(--rr-border));
    box-shadow: 0 6px 16px rgba(15,35,24,.055);
    transform: translateY(-1px);
}

#session-list .mobile-card.status-pending {
    --delivery-state: var(--rr-amber);
    --delivery-state-bg: var(--rr-amber-soft);
}

#session-list .mobile-card.status-approved {
    --delivery-state: var(--rr-sky);
    --delivery-state-bg: var(--rr-sky-soft);
}

#session-list .mobile-card.status-distributed {
    --delivery-state: var(--rr-green);
    --delivery-state-bg: var(--rr-green-soft);
}

#session-list .mobile-card.status-rejected {
    --delivery-state: var(--rr-red);
    --delivery-state-bg: var(--rr-red-soft);
}

#session-list .mobile-card.status-cancelled {
    --delivery-state: var(--rr-slate);
    --delivery-state-bg: var(--rr-slate-soft);
}

#session-list .mc-head {
    display: grid;
    min-height: 43px;
    grid-template-columns: minmax(0, 1fr) auto auto auto;
    gap: .3rem;
    align-items: center;
    padding: .37rem .46rem;
    border: 0;
    border-bottom: 1px solid color-mix(in srgb, var(--delivery-state) 12%, var(--rr-border));
    background: linear-gradient(90deg, var(--delivery-state-bg), #fff 76%);
}

#session-list .mc-head-main {
    display: flex;
    min-width: 0;
    gap: .24rem;
    align-items: center;
    overflow: hidden;
}

#session-list .mc-date {
    flex: 0 0 auto;
    color: var(--rr-text-3);
    font-size: .61rem;
    font-weight: 760;
}

#session-list .mc-sep {
    color: var(--rr-text-3);
    opacity: .48;
}

#session-list .mc-head-product {
    min-width: 0;
    flex: 1 1 auto;
    color: var(--rr-text);
    font-size: .72rem;
    font-weight: 840;
}

#session-list .mc-head-qty {
    flex: 0 0 auto;
    color: var(--rr-text-2);
    font-size: .63rem;
    font-weight: 790;
}

#session-list .mc-state-icon {
    width: 26px;
    height: 26px;
    border-radius: 8px;
    background: color-mix(in srgb, var(--delivery-state-bg) 74%, #fff);
    color: var(--delivery-state);
}

#session-list .mc-billed,
#session-list .mc-status-pill {
    font-size: .55rem;
}

#session-list .mc-body {
    display: grid;
    gap: .38rem;
    padding: .44rem .48rem .48rem;
    background: #fff;
}

#session-list .mc-info-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: .3rem;
    align-items: center;
    font-size: .67rem;
}

#session-list .mc-associate {
    min-width: 0;
    overflow: hidden;
    color: var(--rr-text-2);
    font-size: .69rem;
    font-weight: 790;
    text-overflow: ellipsis;
    white-space: nowrap;
}

#session-list .mc-net {
    color: var(--rr-green);
    font-size: .65rem;
    font-weight: 810;
}

#session-list .mc-body > div[style*="display:grid"] {
    padding: .15rem 0 !important;
    color: var(--rr-text-3) !important;
    font-size: .61rem !important;
}

#session-list .mc-footer {
    display: grid;
    min-width: 0;
    grid-template-columns: minmax(95px, 1fr) auto;
    gap: .34rem;
    align-items: center;
    min-height: 40px;
    padding: .28rem .3rem;
    border: 1px solid var(--rr-border);
    border-radius: 9px;
    background: var(--rr-soft);
}

#session-list .mc-footer-label {
    display: none;
}

#session-list .mc-dist-indicator {
    display: grid;
    min-width: 0;
    grid-template-columns: minmax(50px, 1fr) auto;
    gap: .3rem;
    align-items: center;
}

#session-list .mc-dist-bar-bg {
    width: 100%;
    min-width: 48px;
    max-width: none;
    height: 6px;
    background: color-mix(in srgb, var(--rr-border) 76%, #fff);
}

#session-list .mc-dist-bar-fill.partial {
    background: var(--rr-amber);
}

#session-list .mc-dist-bar-fill.full {
    background: var(--rr-green);
}

#session-list .mc-dist-bar-fill.over {
    background: var(--rr-red);
}

#session-list .mc-dist-text {
    min-width: 28px;
    color: var(--rr-text-2);
    font-size: .62rem;
    font-weight: 790;
}

#session-list .mc-actions {
    display: flex;
    gap: .2rem;
    margin: 0;
    flex-wrap: nowrap;
    justify-content: flex-end;
}

#session-list .mc-actions .btn-approve,
#session-list .mc-actions .btn-reject,
#session-list .mc-actions .btn-edit,
#session-list .mc-actions .btn-distribute,
#session-list .mc-actions .btn-delete-approved,
#session-list .mc-actions .delivery-note-trigger {
    display: inline-flex;
    width: 34px;
    min-width: 34px;
    height: 34px;
    min-height: 34px;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid var(--rr-border);
    border-radius: 9px;
    background: #fff;
    font-size: 0;
}

#session-list .mc-actions .btn-approve {
    border-color: rgba(22,138,77,.15);
    background: var(--rr-green-soft);
    color: var(--rr-green);
}

#session-list .mc-actions .btn-reject,
#session-list .mc-actions .btn-delete-approved {
    border-color: rgba(207,63,63,.14);
    background: var(--rr-red-soft);
    color: var(--rr-red);
}

#session-list .mc-actions .btn-edit {
    border-color: rgba(37,99,235,.14);
    background: var(--rr-blue-soft);
    color: var(--rr-blue);
}

#session-list .mc-actions .btn-distribute {
    border-color: rgba(124,58,237,.15);
    background: var(--rr-violet-soft);
    color: var(--rr-violet);
}

#session-list .mc-actions .delivery-note-trigger {
    border-color: rgba(100,116,139,.14);
    background: var(--rr-slate-soft);
    color: var(--rr-slate);
}

/* ---------- Paginação ---------- */

.delivery-pagination {
    min-height: 47px;
    margin: 0;
    padding: .44rem .56rem;
    border: 0;
    border-top: 1px solid var(--rr-border);
    background: #fff;
}

.delivery-pagination-info {
    color: var(--rr-text-3);
    font-size: .63rem;
}

.delivery-page-size,
.delivery-page-btn {
    min-height: 36px;
    border: 1px solid var(--rr-border);
    border-radius: 9px;
    background: #fff;
    color: var(--rr-text-2);
    font-size: .64rem;
}

/* ---------- Phosphor Duotone ---------- */

.reg-page .ph-duotone,
.modal-overlay .ph-duotone,
.dist-summary-overlay .ph-duotone,
.scroll-top-btn .ph-duotone {
    font-family: "Phosphor-Duotone" !important;
    font-style: normal !important;
    font-weight: normal !important;
    speak: never;
    line-height: 1 !important;
}

.project-bar-icon .ph-duotone,
.reg-section-icon .ph-duotone {
    font-size: 18px !important;
}

.selector-row .ph-duotone {
    font-size: 16px !important;
}

.reg-notes-toggle .ph-duotone {
    font-size: 15px !important;
}

.mc-state-icon .ph-duotone {
    font-size: 15px !important;
}

.mc-actions .ph-duotone,
.delivery-note-trigger .ph-duotone {
    font-size: 16px !important;
}

.history-filter .ph-duotone,
.delivery-page-btn .ph-duotone,
.reg-integrity-toggle .ph-duotone {
    font-size: 15px !important;
}

/* ---------- Responsivo ---------- */

@media (min-width: 768px) and (max-width: 1023px) {
    .entry-card .card-body {
        grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        gap: 0 !important;
        padding: 0 !important;
    }

    .selector-row {
        min-height: 61px;
        border-width: 0 1px 0 0;
        border-radius: 0;
    }

    .selector-row:nth-of-type(3) {
        border-right: 0;
    }

    #entry-fields {
        grid-column: 1 / -1;
        margin: 0;
        border-width: 1px 0 0;
        border-radius: 0;
    }

    #entry-fields .form-grid {
        grid-template-columns: minmax(150px,.78fr) minmax(150px,.72fr) minmax(220px,1.15fr);
    }

    #entry-fields .reg-notes-control {
        grid-column: auto;
    }
}

@media (max-width: 767px) {
    .reg-page {
        gap: .58rem;
        padding-bottom: .8rem;
    }

    .reg-page .entry-card,
    .reg-page .history-card {
        border-radius: 13px;
    }

    .reg-page .project-bar {
        min-height: 58px;
        padding: .48rem .52rem;
    }

    .reg-page .project-bar-icon {
        width: 35px;
        height: 35px;
    }

    .entry-card .card-body {
        padding: .48rem !important;
        gap: .36rem !important;
    }

    .selector-row {
        min-height: 52px;
        padding: .4rem .46rem;
    }

    #entry-fields {
        padding: .46rem;
    }

    #entry-fields .form-grid {
        grid-template-columns: minmax(0, 1fr) 118px;
        gap: .38rem;
    }

    #entry-fields .reg-notes-control {
        grid-column: 1 / -1;
    }

    .reg-notes-toggle {
        width: max-content;
        max-width: 100%;
        min-height: 36px;
        grid-template-columns: auto auto auto;
        padding: .26rem .34rem;
    }

    .reg-notes-toggle-icon {
        width: 25px;
        height: 25px;
    }

    .reg-notes-toggle-copy strong {
        font-size: .63rem;
    }

    .reg-notes-toggle-copy small {
        display: none;
    }

    .btn-submit {
        min-height: 44px;
    }

    .history-filter {
        grid-template-columns: minmax(0, 1fr) 104px 38px 38px;
        padding: .4rem .46rem;
    }

    .history-filter.filters-expanded {
        grid-template-columns: 1fr 1fr 38px 38px;
    }

    .history-filter.filters-expanded #filter-history-search {
        grid-column: 1 / -1;
    }

    .history-filter.filters-expanded #filter-status {
        grid-column: 1;
    }

    .history-filter.filters-expanded #filter-associate,
    .history-filter.filters-expanded #filter-product {
        display: block;
    }

    .history-filter.filters-expanded #filter-associate {
        grid-column: 1 / -1;
    }

    .history-filter.filters-expanded #filter-product {
        grid-column: 1 / -1;
    }

    .history-filter.filters-expanded #filter-date-from,
    .history-filter.filters-expanded #filter-date-to {
        display: block;
    }

    .history-filter.filters-expanded > span {
        display: none;
    }

    .history-filter .hf-clear {
        width: 38px;
        min-width: 38px;
        padding: 0;
    }

    .history-filter .hf-clear-label {
        display: none;
    }

    #session-list {
        padding: .44rem;
        gap: .44rem;
    }

    #session-list .mobile-card {
        border-radius: 10px;
    }

    #session-list .mc-head {
        min-height: 41px;
        padding: .34rem .42rem;
    }

    #session-list .mc-body {
        padding: .4rem .42rem .44rem;
    }

    #session-list .mc-footer {
        grid-template-columns: minmax(70px, 1fr) auto;
    }

    #session-list .mc-actions .btn-approve,
    #session-list .mc-actions .btn-reject,
    #session-list .mc-actions .btn-edit,
    #session-list .mc-actions .btn-distribute,
    #session-list .mc-actions .btn-delete-approved,
    #session-list .mc-actions .delivery-note-trigger {
        width: 36px;
        min-width: 36px;
        height: 36px;
        min-height: 36px;
    }

    .delivery-page-btn {
        width: 38px;
        min-width: 38px;
        padding: 0;
        font-size: 0;
    }
}

@media (max-width: 420px) {
    #entry-fields .form-grid {
        grid-template-columns: minmax(0, 1fr) 112px;
    }

    #session-list .mc-footer {
        grid-template-columns: 1fr;
    }

    #session-list .mc-actions {
        justify-content: flex-end;
    }
}

@media (prefers-reduced-motion: reduce) {
    .reg-page *,
    .reg-page *::before,
    .reg-page *::after {
        animation-duration: .01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .01ms !important;
        scroll-behavior: auto !important;
    }
}
</style>


<style id="register-v2-final">
/* =====================================================================
   Registro v2 — alinhado a project-deliveries
   ===================================================================== */

.reg-page {
    --rv-green:#168a4d;
    --rv-green-soft:#eaf8ef;
    --rv-blue:#2563eb;
    --rv-blue-soft:#eef4ff;
    --rv-sky:#0284c7;
    --rv-sky-soft:#edf8fe;
    --rv-violet:#7c3aed;
    --rv-violet-soft:#f4f0ff;
    --rv-amber:#c87408;
    --rv-amber-soft:#fff7e8;
    --rv-red:#cf3f3f;
    --rv-red-soft:#fff0f0;
    --rv-slate:#64748b;
    --rv-slate-soft:#f1f5f9;
    --rv-border:var(--color-border,#dce7e0);
    --rv-border-strong:var(--color-border-strong,#c8d6cd);
    --rv-text:var(--color-text,#102018);
    --rv-text-2:var(--color-text-secondary,#52645a);
    --rv-text-3:var(--color-text-muted,#809087);
    --rv-soft:var(--color-surface-soft,#f8faf9);
}

/* Seletores sempre empilhados, inclusive desktop. */
.entry-card .card-body {
    display:flex!important;
    flex-direction:column!important;
    gap:.38rem!important;
    padding:.54rem!important;
}

@media (min-width:768px) and (max-width:1023px) {
    .entry-card .card-body {
        display:flex!important;
        flex-direction:column!important;
        gap:.38rem!important;
        padding:.54rem!important;
    }

    .selector-row {
        min-height:56px!important;
        border:1px solid var(--rv-border)!important;
        border-radius:10px!important;
    }

    #entry-fields {
        margin-top:.04rem!important;
        border:1px solid var(--rv-border)!important;
        border-radius:11px!important;
    }
}

/* Mesma lógica de cores usada em project-deliveries. */
#sel-assoc {
    --sel-tone:var(--rv-blue)!important;
    --sel-soft:var(--rv-blue-soft)!important;
}
#sel-date {
    --sel-tone:var(--rv-amber)!important;
    --sel-soft:var(--rv-amber-soft)!important;
}
#sel-product {
    --sel-tone:var(--rv-violet)!important;
    --sel-soft:var(--rv-violet-soft)!important;
}

/* Quantidade: números maiores, fortes e tabulares. */
#f-qty,
#edit-qty,
.quota-number,
.mc-head-qty,
.mc-dist-text,
.reg-session-qty,
.reg-session-dist-text {
    font-variant-numeric:tabular-nums;
    font-weight:850!important;
}

#f-qty {
    font-size:1.02rem!important;
    letter-spacing:.015em;
}

.sel-date-display {
    font-variant-numeric:tabular-nums;
    font-weight:850!important;
}

/* Header histórico */
.reg-history-head-actions {
    display:flex;
    align-items:center;
    gap:.34rem;
}

.reg-history-view-switch {
    display:none;
    align-items:center;
    gap:.18rem;
    padding:.16rem;
    border:1px solid var(--rv-border);
    border-radius:9px;
    background:var(--rv-soft);
}

.reg-view-btn {
    display:inline-flex;
    width:31px;
    min-width:31px;
    height:31px;
    align-items:center;
    justify-content:center;
    padding:0;
    border:0;
    border-radius:7px;
    background:transparent;
    color:var(--rv-text-3);
    cursor:pointer;
}

.reg-view-btn.active {
    background:#fff;
    color:var(--rv-violet);
    box-shadow:0 1px 4px rgba(15,35,24,.08);
}

.reg-view-btn .ph-duotone {
    font-size:15px!important;
}

@media (min-width:1100px) {
    .reg-history-view-switch {
        display:flex;
    }
}

/* Cards = mesmo padrão da project-deliveries. */
#session-list {
    background:var(--rv-soft)!important;
}

#session-list .mobile-card {
    padding:0!important;
    overflow:hidden!important;
    border:1px solid var(--rv-border)!important;
    border-left:3px solid var(--delivery-state)!important;
    border-radius:12px!important;
    background:#fff!important;
    box-shadow:none!important;
}

#session-list .mobile-card .mc-head {
    min-height:45px!important;
    padding:.5rem .58rem!important;
    border-bottom:1px solid var(--rv-border)!important;
    background:linear-gradient(90deg,var(--delivery-state-bg),#fff 72%)!important;
}

#session-list .mobile-card .mc-head-product {
    font-size:.79rem!important;
}

#session-list .mobile-card .mc-body {
    gap:.48rem!important;
    padding:.56rem .58rem .6rem!important;
    background:#fff!important;
}

#session-list .mobile-card .mc-associate {
    font-size:.74rem!important;
    font-weight:790!important;
}

#session-list .mc-footer {
    display:flex!important;
    min-width:0;
    align-items:center;
    gap:.4rem;
    flex-wrap:wrap;
    min-height:0!important;
    padding:.42rem .46rem!important;
    border:1px solid var(--rv-border)!important;
    border-radius:9px!important;
    background:var(--rv-soft)!important;
}

#session-list .mc-dist-indicator {
    flex:1 1 118px;
    min-width:105px;
    display:flex!important;
    align-items:center;
    gap:.3rem;
}

#session-list .mc-dist-bar-bg {
    width:auto!important;
    max-width:none!important;
    min-width:52px;
    flex:1 1 auto;
    height:6px!important;
}

#session-list .mc-actions {
    display:flex!important;
    gap:.18rem!important;
    align-items:center;
    justify-content:flex-end;
    flex:0 0 auto;
    flex-wrap:nowrap!important;
    margin:0!important;
    padding:0!important;
    border:0!important;
}

#session-list .mc-actions button,
#session-list .mc-actions .delivery-note-trigger {
    width:38px!important;
    min-width:38px!important;
    height:38px!important;
    min-height:38px!important;
    padding:0!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    border-radius:9px!important;
    font-size:0!important;
    line-height:0!important;
}

#session-list .mc-actions .ph-duotone {
    font-size:16px!important;
}

/* Estado/ações iguais à tela project-deliveries. */
#session-list .btn-approve {
    background:var(--rv-green-soft)!important;
    color:var(--rv-green)!important;
    border-color:rgba(22,138,77,.15)!important;
}
#session-list .btn-reject,
#session-list .btn-delete-approved {
    background:var(--rv-red-soft)!important;
    color:var(--rv-red)!important;
    border-color:rgba(207,63,63,.14)!important;
}
#session-list .btn-edit {
    background:var(--rv-blue-soft)!important;
    color:var(--rv-blue)!important;
    border-color:rgba(37,99,235,.14)!important;
}
#session-list .btn-distribute {
    background:var(--rv-violet-soft)!important;
    color:var(--rv-violet)!important;
    border-color:rgba(124,58,237,.15)!important;
}
#session-list .delivery-note-trigger {
    background:var(--rv-slate-soft)!important;
    color:var(--rv-slate)!important;
    border-color:rgba(100,116,139,.14)!important;
}

/* Outros produtores: CTA claro e grande, sem parecer texto secundário. */
.session-collapsible {
    border:1px solid var(--rv-border)!important;
    border-radius:11px!important;
    background:#fff!important;
}

.session-collapsible > summary {
    display:flex!important;
    min-height:46px!important;
    align-items:center!important;
    justify-content:space-between!important;
    gap:.6rem!important;
    padding:.46rem .56rem!important;
    background:linear-gradient(90deg,var(--rv-blue-soft),#fff 72%)!important;
    color:var(--rv-blue)!important;
    list-style:none!important;
}

.session-collapsible > summary::after {
    content:none!important;
}

.session-other-main,
.session-other-cta {
    display:flex;
    align-items:center;
    gap:.34rem;
}

.session-other-main strong {
    color:var(--rv-text);
    font-size:.69rem;
    font-weight:820;
}

.session-other-main .ph-duotone {
    font-size:18px!important;
    color:var(--rv-blue);
}

.session-other-cta {
    min-height:30px;
    padding:.26rem .38rem;
    border:1px solid rgba(37,99,235,.14);
    border-radius:8px;
    background:#fff;
    color:var(--rv-blue);
    font-size:.61rem;
    font-weight:790;
}

.session-other-cta .ph-duotone {
    font-size:13px!important;
    transition:transform .16s ease;
}

.session-collapsible[open] .session-other-cta .ph-duotone {
    transform:rotate(180deg);
}

/* Tabela desktop compacta */
.reg-session-table-wrap {
    overflow-x:auto;
    background:#fff;
    scrollbar-width:thin;
}

.reg-session-table-wrap[hidden] {
    display:none!important;
}

.reg-session-table {
    width:100%;
    min-width:760px;
    border-collapse:separate;
    border-spacing:0;
    color:var(--rv-text);
    font-size:.69rem;
}

.reg-session-table th {
    padding:.52rem .52rem;
    border-bottom:1px solid var(--rv-border);
    background:#f7f9f8;
    color:var(--rv-text-3);
    font-size:.61rem;
    font-weight:780;
    text-align:left;
    white-space:nowrap;
}

.reg-session-table td {
    padding:.5rem .52rem;
    border-bottom:1px solid var(--rv-border);
    background:#fff;
    vertical-align:middle;
}

.reg-session-table tr:last-child td {
    border-bottom:0;
}

.reg-session-table tbody tr:hover td {
    background:color-mix(in srgb,var(--rv-blue-soft) 28%,#fff);
}

.reg-session-table tr.status-pending td:first-child {
    box-shadow:inset 3px 0 0 rgba(200,116,8,.52);
}
.reg-session-table tr.status-approved td:first-child,
.reg-session-table tr.status-distributed td:first-child {
    box-shadow:inset 3px 0 0 rgba(22,138,77,.42);
}
.reg-session-table tr.status-rejected td:first-child {
    box-shadow:inset 3px 0 0 rgba(207,63,63,.46);
}
.reg-session-table tr.status-cancelled td:first-child {
    box-shadow:inset 3px 0 0 rgba(100,116,139,.42);
}

.reg-table-date {
    display:flex;
    align-items:center;
    gap:.34rem;
    white-space:nowrap;
}

.reg-table-state {
    display:inline-flex;
    width:27px;
    height:27px;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    background:var(--rv-slate-soft);
    color:var(--rv-slate);
}
tr.status-pending .reg-table-state {background:var(--rv-amber-soft);color:var(--rv-amber)}
tr.status-approved .reg-table-state {background:var(--rv-sky-soft);color:var(--rv-sky)}
tr.status-distributed .reg-table-state {background:var(--rv-green-soft);color:var(--rv-green)}
tr.status-rejected .reg-table-state {background:var(--rv-red-soft);color:var(--rv-red)}

.reg-table-state .ph-duotone {font-size:15px!important}

.reg-session-associate,
.reg-session-product {
    max-width:220px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.reg-session-associate {
    font-weight:780;
}

.reg-session-qty {
    font-size:.72rem;
    white-space:nowrap;
}

.reg-table-control-row {
    display:flex;
    min-width:0;
    align-items:center;
    gap:.3rem;
    flex-wrap:nowrap;
}

.reg-table-dist {
    display:flex;
    min-width:92px;
    max-width:126px;
    flex:1 1 100px;
    align-items:center;
    gap:.28rem;
    padding:0;
    border:0;
    background:transparent;
    color:inherit;
    cursor:pointer;
    font:inherit;
}

.reg-table-dist-bar {
    min-width:54px;
    flex:1 1 auto;
    height:6px;
    overflow:hidden;
    border-radius:999px;
    background:color-mix(in srgb,var(--rv-border) 72%,#fff);
}

.reg-table-dist-fill {
    height:100%;
    border-radius:inherit;
}
.reg-table-dist-fill.partial {background:var(--rv-amber)}
.reg-table-dist-fill.full {background:var(--rv-green)}
.reg-table-dist-fill.over {background:var(--rv-red)}

.reg-table-actions {
    display:flex;
    gap:.18rem;
    align-items:center;
    flex:0 0 auto;
    flex-wrap:nowrap;
}

.reg-table-actions button,
.reg-table-actions .delivery-note-trigger {
    display:inline-flex;
    width:31px;
    min-width:31px;
    height:31px;
    min-height:31px;
    align-items:center;
    justify-content:center;
    padding:0;
    border:1px solid var(--rv-border);
    border-radius:8px;
    font-size:0;
    line-height:0;
}

.reg-table-actions .ph-duotone {
    font-size:14px!important;
}

.reg-table-action-label {
    display:none!important;
}

.reg-table-actions .btn-approve {
    background:var(--rv-green-soft)!important;
    color:var(--rv-green)!important;
    border-color:rgba(22,138,77,.15)!important;
}
.reg-table-actions .btn-reject,
.reg-table-actions .btn-delete-approved {
    background:var(--rv-red-soft)!important;
    color:var(--rv-red)!important;
    border-color:rgba(207,63,63,.14)!important;
}
.reg-table-actions .btn-edit {
    background:var(--rv-blue-soft)!important;
    color:var(--rv-blue)!important;
    border-color:rgba(37,99,235,.14)!important;
}
.reg-table-actions .btn-distribute {
    background:var(--rv-violet-soft)!important;
    color:var(--rv-violet)!important;
    border-color:rgba(124,58,237,.15)!important;
}
.reg-table-actions .delivery-note-trigger {
    background:var(--rv-slate-soft)!important;
    color:var(--rv-slate)!important;
    border-color:rgba(100,116,139,.14)!important;
}

/* Custom calendar */
.reg-calendar-overlay {
    z-index:350000!important;
}

.reg-calendar-box {
    width:min(430px,100%)!important;
    max-height:min(90dvh,720px)!important;
    overflow:hidden!important;
}

.reg-calendar-head {
    background:linear-gradient(180deg,var(--rv-soft),#fff);
}

.reg-calendar-title-wrap {
    display:flex;
    min-width:0;
    align-items:center;
    gap:.44rem;
}

.reg-calendar-icon {
    display:inline-flex;
    width:36px;
    height:36px;
    align-items:center;
    justify-content:center;
    border-radius:10px;
    background:var(--rv-amber-soft);
    color:var(--rv-amber);
}

.reg-calendar-icon .ph-duotone {
    font-size:18px!important;
}

.reg-calendar-sub {
    margin-top:.03rem;
    color:var(--rv-text-3);
    font-size:.63rem;
}

.reg-calendar-manual {
    padding:.58rem .62rem .48rem;
    border-bottom:1px solid var(--rv-border);
    background:#fff;
}

.reg-calendar-manual > label {
    display:block;
    margin-bottom:.22rem;
    color:var(--rv-text-3);
    font-size:.59rem;
    font-weight:760;
}

.reg-calendar-input-wrap {
    display:grid;
    grid-template-columns:auto minmax(0,1fr) auto;
    gap:.32rem;
    align-items:center;
    min-height:42px;
    padding:.25rem .28rem .25rem .5rem;
    border:1px solid var(--rv-border-strong);
    border-radius:10px;
    background:#fff;
}

.reg-calendar-input-wrap:focus-within {
    border-color:var(--rv-amber);
    box-shadow:0 0 0 3px rgba(200,116,8,.07);
}

.reg-calendar-input-wrap > .ph-duotone {
    color:var(--rv-amber);
    font-size:16px!important;
}

#calendar-manual-input {
    width:100%;
    min-width:0;
    border:0;
    outline:0;
    background:transparent;
    color:var(--rv-text);
    font:inherit;
    font-size:.9rem;
    font-weight:850;
    font-variant-numeric:tabular-nums;
    letter-spacing:.035em;
}

.reg-calendar-use-btn,
.reg-calendar-nav-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border:1px solid var(--rv-border);
    background:#fff;
    color:var(--rv-text-2);
    cursor:pointer;
}

.reg-calendar-use-btn {
    width:34px;
    height:34px;
    border-radius:8px;
    background:var(--rv-green-soft);
    color:var(--rv-green);
    border-color:rgba(22,138,77,.14);
}

.reg-calendar-manual-help {
    min-height:15px;
    margin-top:.18rem;
    color:var(--rv-text-3);
    font-size:.58rem;
}

.reg-calendar-manual-help.error {
    color:var(--rv-red);
    font-weight:720;
}

.reg-calendar-nav {
    display:grid;
    grid-template-columns:auto minmax(0,1fr) auto;
    gap:.4rem;
    align-items:center;
    padding:.48rem .62rem .4rem;
}

.reg-calendar-nav strong {
    color:var(--rv-text);
    font-size:.74rem;
    font-weight:840;
    text-align:center;
    text-transform:capitalize;
}

.reg-calendar-nav-btn {
    width:34px;
    height:34px;
    border-radius:9px;
}

.reg-calendar-weekdays,
.reg-calendar-grid {
    display:grid;
    grid-template-columns:repeat(7,minmax(0,1fr));
    gap:.2rem;
    padding:0 .62rem;
}

.reg-calendar-weekdays {
    margin-bottom:.2rem;
}

.reg-calendar-weekdays span {
    padding:.22rem 0;
    color:var(--rv-text-3);
    font-size:.56rem;
    font-weight:760;
    text-align:center;
}

.reg-calendar-grid {
    padding-bottom:.58rem;
}

.reg-calendar-day {
    position:relative;
    display:inline-flex;
    min-width:0;
    aspect-ratio:1;
    align-items:center;
    justify-content:center;
    border:1px solid transparent;
    border-radius:9px;
    background:#fff;
    color:var(--rv-text-2);
    cursor:pointer;
    font:inherit;
    font-size:.7rem;
    font-weight:740;
    font-variant-numeric:tabular-nums;
}

.reg-calendar-day:hover,
.reg-calendar-day:focus-visible,
.reg-calendar-day.cursor {
    border-color:rgba(37,99,235,.16);
    background:var(--rv-blue-soft);
    color:var(--rv-blue);
    outline:none;
}

.reg-calendar-day.today::after {
    content:"";
    position:absolute;
    bottom:4px;
    width:4px;
    height:4px;
    border-radius:999px;
    background:var(--rv-amber);
}

.reg-calendar-day.selected {
    border-color:rgba(22,138,77,.16);
    background:var(--rv-green-soft);
    color:var(--rv-green);
    font-weight:860;
}

.reg-calendar-day.outside {
    opacity:.25;
    cursor:default;
    pointer-events:none;
}

.reg-calendar-footer {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:.5rem;
    padding:.48rem .62rem;
    border-top:1px solid var(--rv-border);
    background:var(--rv-soft);
}

.reg-calendar-today {
    display:inline-flex;
    min-height:34px;
    align-items:center;
    gap:.28rem;
    padding:.32rem .44rem;
    border:1px solid rgba(200,116,8,.15);
    border-radius:9px;
    background:var(--rv-amber-soft);
    color:var(--rv-amber);
    cursor:pointer;
    font:inherit;
    font-size:.63rem;
    font-weight:790;
}

.reg-calendar-shortcut {
    display:flex;
    align-items:center;
    gap:.18rem;
    color:var(--rv-text-3);
    font-size:.56rem;
}

.reg-calendar-shortcut kbd {
    min-width:20px;
    padding:.12rem .2rem;
    border:1px solid var(--rv-border);
    border-bottom-width:2px;
    border-radius:5px;
    background:#fff;
    color:var(--rv-text-2);
    font:inherit;
    font-size:.54rem;
    text-align:center;
}

@media(max-width:767px) {
    .reg-calendar-overlay {
        align-items:flex-end!important;
        padding:0!important;
    }

    .reg-calendar-box {
        width:100%!important;
        max-height:92svh!important;
        border-radius:16px 16px 0 0!important;
    }

    .reg-calendar-shortcut {
        display:none;
    }
}

/* Em mobile/tablet os cards permanecem a visualização oficial. */
@media(max-width:1099px) {
    #session-list {
        display:grid!important;
    }

    #session-table-wrap {
        display:none!important;
    }

    .reg-history-view-switch {
        display:none!important;
    }
}

/* Foco do sheet não muda a densidade do layout. */
.modal-overlay.open .modal-box,
.dist-summary-overlay.open .dist-summary-box {
    animation:reg-sheet-in .18s cubic-bezier(.2,.75,.25,1);
}

@keyframes reg-sheet-in {
    from {opacity:.72;transform:translateY(10px) scale(.992)}
    to {opacity:1;transform:none}
}

@media(prefers-reduced-motion:reduce){
    .modal-overlay.open .modal-box,
    .dist-summary-overlay.open .dist-summary-box {
        animation:none;
    }
}

/* Cards escolhidos no desktop continuam em duas colunas, sem virar "mobile ampliado". */
@media(min-width:1100px) {
    #session-list[style*="display: grid"],
    #session-list[style*="display:grid"] {
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:.52rem!important;
        padding:.56rem!important;
    }

    #session-list .session-section-header,
    #session-list .session-collapsible,
    #session-list .session-empty {
        grid-column:1/-1;
    }
}

/* O número é a informação operacional principal. */
#f-qty::placeholder,
#edit-qty::placeholder {
    font-weight:650;
    color:var(--rv-text-3);
}
</style>


<div class="reg-page">

   
    
    {{-- ─── ENTRY CARD ───────────────────────────────── --}}
    <div class="card entry-card">
         {{-- ─── PROJECT BAR ──────────────────────────────── --}}
    <div class="project-bar" id="project-bar">
        <div class="project-bar-icon">
            <i data-lucide="folder-open" style="width:18px;height:18px"></i>
        </div>
        <div class="project-bar-info">
            <div class="project-bar-title" id="pb-title">
                @if($selectedProject) {{ $selectedProject['title'] }} @else Nenhum projeto selecionado @endif
            </div>
            <div class="project-bar-sub" id="pb-sub">
                @if($selectedProject) {{ $selectedProject['customer_name'] }} @else Selecione um projeto para começar @endif
            </div>
        </div>
        @if(!$selectedProject)
        <button class="project-bar-btn" onclick="openModal('project')" id="pb-btn">
            Selecionar
        </button>
        @else
        <span id="pb-badge" style="font-size:0.72rem;font-weight:600;padding:0.2rem 0.55rem;border-radius:999px;background:color-mix(in srgb, var(--color-primary) 15%, transparent);color:var(--color-primary-dark);">
            ATIVO
        </span>
        @endif
    </div>
        <div class="card-header reg-entry-title">
            <span class="reg-section-icon reg-section-icon-amber" aria-hidden="true">
                <i data-lucide="package-plus"></i>
            </span>
            <span>Nova Entrega</span>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:0.6rem;">

            {{-- Associate selector --}}
            <div class="selector-row" id="sel-assoc" onclick="openModal('assoc')">
                <div class="sel-icon">
                    <i data-lucide="user" style="width:16px;height:16px"></i>
                </div>
                <div class="sel-info">
                    <div class="sel-label">Associado</div>
                    <div class="sel-value" id="assoc-value">Nenhum selecionado</div>
                </div>
                <div class="sel-chevron">
                    <i data-lucide="chevron-right" style="width:16px;height:16px"></i>
                </div>
            </div>

            {{-- Date selector --}}
            <div
                class="selector-row"
                id="sel-date"
                role="button"
                tabindex="0"
                onclick="focusDateInput()"
                onkeydown="if(event.key === 'Enter' || event.key === ' '){ event.preventDefault(); focusDateInput(); }"
                aria-haspopup="dialog"
                aria-controls="modal-date"
            >
                <div class="sel-icon">
                    <i data-lucide="calendar" style="width:16px;height:16px"></i>
                </div>
                <div class="sel-info">
                    <div class="sel-label">Data da entrega</div>
                    <div class="sel-date-display" id="date-display">{{ date('d/m/Y') }}</div>
                </div>
                <input type="hidden" id="f-date" value="{{ date('Y-m-d') }}">
                <div class="sel-chevron">
                    <i data-lucide="chevron-right" style="width:16px;height:16px"></i>
                </div>
            </div>

            {{-- Product selector --}}
            <div class="selector-row disabled" id="sel-product" onclick="openModal('product')">
                <div class="sel-icon">
                    <i data-lucide="package" style="width:16px;height:16px"></i>
                </div>
                <div class="sel-info">
                    <div class="sel-label">Produto</div>
                    <div class="sel-value" id="product-value">Nenhum selecionado</div>
                    <div class="sel-meta" id="product-meta" style="display:none"></div>
                </div>
                <div class="sel-chevron">
                    <i data-lucide="chevron-right" style="width:16px;height:16px"></i>
                </div>
            </div>

            {{-- Entry fields (appear after both are selected) --}}
            <div id="entry-fields" style="display:none">
                <div class="form-divider"></div>

                <div class="form-grid">
                    <div>
                        <label class="field-label" for="f-qty">Quantidade <span id="f-unit-lbl"></span></label>
                        <input class="field-input" type="number" id="f-qty" min="0.001" step="0.001" placeholder="0">
                    </div>
                    <div>
                        <label class="field-label">Qualidade</label>
                        <div class="quality-group quality-pills" id="quality-group">
                            <button class="q-pill active" data-q="A">A</button>
                            <button class="q-pill" data-q="B">B</button>
                            <button class="q-pill" data-q="C">C</button>
                        </div>
                    </div>
                    <div class="reg-notes-control">
                        <label class="field-label">Observações</label>

                        <button
                            type="button"
                            class="reg-notes-toggle"
                            id="reg-notes-toggle"
                            onclick="toggleEntryNotes()"
                            aria-controls="reg-notes-field"
                            aria-expanded="false"
                        >
                            <span class="reg-notes-toggle-icon" aria-hidden="true">
                                <i data-lucide="message-square-plus"></i>
                            </span>

                            <span class="reg-notes-toggle-copy">
                                <strong>Adicionar observação</strong>
                                <small id="reg-notes-toggle-hint">Opcional</small>
                            </span>

                            <i data-lucide="chevron-down" class="reg-notes-toggle-chevron"></i>
                        </button>

                        <div class="reg-notes-field" id="reg-notes-field" hidden>
                            <input
                                class="field-input"
                                type="text"
                                id="f-notes"
                                placeholder="Digite uma observação opcional"
                                autocomplete="off"
                            >
                        </div>
                    </div>
                </div>

                <button class="btn-submit" id="btn-submit" disabled onclick="submitEntry()">
                    <span style="display:inline-flex;align-items:center;justify-content:center;gap:.35rem">
                        <i data-lucide="package-plus" style="width:15px;height:15px"></i>
                        Registrar Entrega
                    </span>
                </button>
            </div>

        </div>
    </div>

    @php
        $registerDeliveryStyleDummy = [
            'id' => 0, 'quantity' => 0, 'distributed_qty' => 0, 'unit' => 'un',
            'limit' => [], 'status_value' => 'pending', 'distributions' => [],
        ];
    @endphp
    @include('delivery.partials.project-delivery-mobile-card', [
        'delivery' => $registerDeliveryStyleDummy,
        'customers' => collect(),
        'stylesOnly' => true,
    ])

    {{-- ─── SESSION LIST ─────────────────────────────── --}}
    <div class="card history-card">
        <div class="card-header reg-history-head">
            <div class="reg-history-title">
                <span class="reg-section-icon reg-section-icon-violet" aria-hidden="true">
                    <i data-lucide="clock-counter-clockwise"></i>
                </span>
                <span id="session-list-title">Registros desta sessão</span>
            </div>

            <div class="reg-history-head-actions">
                <span id="session-count"></span>

                <div class="reg-history-view-switch" id="reg-history-view-switch" role="group" aria-label="Visualização do histórico">
                    <button
                        type="button"
                        class="reg-view-btn"
                        data-history-view="cards"
                        onclick="setHistoryView('cards')"
                        title="Visualizar em cards"
                        aria-label="Visualizar em cards"
                    >
                        <i class="ph-duotone ph-squares-four"></i>
                    </button>

                    <button
                        type="button"
                        class="reg-view-btn"
                        data-history-view="table"
                        onclick="setHistoryView('table')"
                        title="Visualizar em tabela"
                        aria-label="Visualizar em tabela"
                    >
                        <i class="ph-duotone ph-table"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="reg-integrity" id="reg-integrity" hidden>
            <div class="reg-integrity-head">
                <div>
                    <div style="display:flex;align-items:center;gap:.35rem;font-size:.76rem;font-weight:800;">
                        <i data-lucide="shield-alert" style="width:15px;height:15px;color:#d97706"></i>
                        Pendencias e Inconsistencias
                    </div>
                    <div class="reg-integrity-counts" style="margin-top:.25rem;">
                        <span id="reg-integrity-critical" style="color:#dc2626"></span>
                        <span id="reg-integrity-warning" style="color:#d97706"></span>
                        <span id="reg-integrity-info" style="color:#2563eb"></span>
                    </div>
                </div>
                <button type="button" class="reg-integrity-toggle" onclick="toggleRegisterIntegrity()" aria-controls="reg-integrity-list" aria-expanded="false" title="Expandir ou recolher pendencias">
                    <i data-lucide="chevron-down" style="width:15px;height:15px"></i>
                </button>
            </div>
            <div class="reg-integrity-list" id="reg-integrity-list" hidden></div>
        </div>
        <div class="history-filter" id="history-filter">
            <label for="filter-history-search">Filtrar:</label>
            <input type="search" id="filter-history-search" oninput="renderSessionItems()" placeholder="Buscar associado, produto ou data" autocomplete="off">
            <select id="filter-status" onchange="renderSessionItems()" aria-label="Status">
                <option value="">Todos os status</option>
                <option value="pending">Pendentes</option>
                <option value="approved">Aprovadas</option>
                <option value="rejected">Rejeitadas</option>
                <option value="cancelled">Canceladas</option>
            </select>
            <input type="search" id="filter-associate" oninput="renderSessionItems()" placeholder="Associado" autocomplete="off">
            <input type="search" id="filter-product" oninput="renderSessionItems()" placeholder="Produto" autocomplete="off">
            <input type="date" id="filter-date-from" oninput="renderSessionItems()" placeholder="De">
            <span style="font-size:.75rem;color:var(--color-text-muted)">ate</span>
            <input type="date" id="filter-date-to" oninput="renderSessionItems()" placeholder="Ate">
            <button class="hf-more" id="history-filter-toggle" type="button" onclick="toggleHistoryFilters()" aria-expanded="false" title="Mais filtros" aria-label="Mostrar mais filtros">
                <i data-lucide="sliders-horizontal"></i>
            </button>
            <button class="hf-clear" onclick="clearFilter()" title="Limpar filtros" aria-label="Limpar filtros">
                <i data-lucide="eraser"></i>
                <span class="hf-clear-label">Limpar</span>
            </button>
        </div>
        <div id="session-list">
            <div class="session-empty" id="session-empty">Selecione um projeto para ver o histórico de entregas</div>
        </div>

        <div class="reg-session-table-wrap" id="session-table-wrap" hidden>
            <table class="reg-session-table" aria-label="Histórico de entregas">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Produtor</th>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Distribuição</th>
                    </tr>
                </thead>
                <tbody id="session-table-body"></tbody>
            </table>
        </div>

        <div class="delivery-pagination" id="session-pagination" style="display:none">
            <div class="delivery-pagination-info" id="session-page-info"></div>
            <div class="delivery-pagination-actions">
                <select class="delivery-page-size" id="session-page-size" onchange="setSessionPageSize(this.value)">
                    <option value="30">30 ultimos</option>
                    <option value="50">50 ultimos</option>
                    <option value="100">100 ultimos</option>
                    <option value="all">Todos</option>
                </select>
                <button type="button" class="delivery-page-btn" id="session-prev" onclick="changeSessionPage(-1)" title="Página anterior" aria-label="Página anterior"><i data-lucide="chevron-left"></i><span class="delivery-page-label">Anterior</span></button>
                <button type="button" class="delivery-page-btn" id="session-next" onclick="changeSessionPage(1)" title="Próxima página" aria-label="Próxima página"><span class="delivery-page-label">Próxima</span><i data-lucide="chevron-right"></i></button>
            </div>
        </div>
    </div>

</div>

{{-- ─────────────────── MODALS ──────────────────── --}}

<button type="button" class="scroll-top-btn" id="scroll-top-btn" onclick="scrollToRegisterTop()" aria-label="Voltar ao topo">
    <i data-lucide="arrow-up" style="width:18px;height:18px"></i>
</button>


<div class="modal-overlay register-confirm-overlay" id="register-confirm-overlay" aria-hidden="true">
    <div
        class="modal-box register-confirm-box"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="register-confirm-title"
        aria-describedby="register-confirm-message"
    >
        <div class="register-confirm-head">
            <span class="register-confirm-icon" id="register-confirm-icon" aria-hidden="true">
                <i class="ph-duotone ph-question"></i>
            </span>

            <div class="register-confirm-copy">
                <strong id="register-confirm-title">Confirmar ação</strong>
                <p id="register-confirm-message"></p>
            </div>
        </div>

        <div class="register-confirm-footer">
            <button type="button" class="register-confirm-btn cancel" id="register-confirm-cancel">
                Cancelar
            </button>

            <button type="button" class="register-confirm-btn confirm" id="register-confirm-ok">
                Confirmar
            </button>
        </div>
    </div>
</div>

<div class="dist-summary-overlay" id="dist-summary-overlay" aria-hidden="true">
    <div class="dist-summary-box" role="dialog" aria-modal="true" aria-labelledby="dist-summary-title">
        <div class="dist-summary-head">
            <div class="dist-summary-head-main">
                <span class="dist-summary-head-icon" aria-hidden="true">
                    <i class="ph-duotone ph-git-merge"></i>
                </span>

                <div>
                    <div class="dist-summary-title" id="dist-summary-title">Distribuições</div>
                    <div class="dist-summary-sub" id="dist-summary-sub"></div>
                </div>
            </div>

            <button type="button" class="dist-summary-close" onclick="closeDistSummary()" aria-label="Fechar">
                <i class="ph-duotone ph-x"></i>
            </button>
        </div>

        <div class="dist-summary-body">
            <div class="dist-summary-overview" id="dist-summary-overview"></div>
            <div class="dist-summary-list" id="dist-summary-list"></div>
        </div>
    </div>
</div>


{{-- Custom calendar --}}
<div class="modal-overlay reg-calendar-overlay" id="modal-date" aria-hidden="true">
    <div class="modal-box reg-calendar-box" role="dialog" aria-modal="true" aria-labelledby="calendar-title">
        <div class="modal-header reg-calendar-head">
            <div class="reg-calendar-title-wrap">
                <span class="reg-calendar-icon" aria-hidden="true">
                    <i class="ph-duotone ph-calendar-dots"></i>
                </span>
                <div>
                    <span class="modal-title" id="calendar-title">Data da entrega</span>
                    <div class="reg-calendar-sub">Selecione no calendário ou digite a data</div>
                </div>
            </div>

            <button class="modal-close" type="button" onclick="closeCalendarSheet()" aria-label="Fechar calendário">
                <i class="ph-duotone ph-x"></i>
            </button>
        </div>

        <div class="reg-calendar-body register-sheet-scroll">
            <div class="reg-calendar-selection">
                <span class="reg-calendar-selection-icon" aria-hidden="true">
                    <i class="ph-duotone ph-calendar-check"></i>
                </span>
                <div>
                    <small>Data selecionada</small>
                    <strong id="calendar-selected-label">—</strong>
                </div>
            </div>

            <div class="reg-calendar-manual">
                <label for="calendar-manual-input">Digitar manualmente</label>
                <div class="reg-calendar-input-wrap">
                    <i class="ph-duotone ph-keyboard"></i>
                    <input
                        id="calendar-manual-input"
                        type="text"
                        inputmode="numeric"
                        maxlength="10"
                        placeholder="dd/mm/aaaa"
                        autocomplete="off"
                        aria-describedby="calendar-manual-help"
                    >
                    <button
                        type="button"
                        class="reg-calendar-use-btn"
                        onclick="applyManualCalendarDate()"
                        title="Usar data digitada"
                        aria-label="Usar data digitada"
                    >
                        <i class="ph-duotone ph-check"></i>
                    </button>
                </div>
                <div class="reg-calendar-manual-help" id="calendar-manual-help">Formato: dia/mês/ano</div>
            </div>

            <div class="reg-calendar-month-card">
                <div class="reg-calendar-nav">
                    <button type="button" class="reg-calendar-nav-btn" onclick="calendarChangeMonth(-1)" aria-label="Mês anterior">
                        <i class="ph-duotone ph-caret-left"></i>
                    </button>

                    <strong id="calendar-month-label"></strong>

                    <button type="button" class="reg-calendar-nav-btn" onclick="calendarChangeMonth(1)" aria-label="Próximo mês">
                        <i class="ph-duotone ph-caret-right"></i>
                    </button>
                </div>

                <div class="reg-calendar-weekdays" aria-hidden="true">
                    <span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span><span>Dom</span>
                </div>

                <div class="reg-calendar-grid" id="calendar-grid" role="grid" aria-label="Calendário"></div>
            </div>
        </div>

        <div class="reg-calendar-footer">
            <button type="button" class="reg-calendar-today" onclick="calendarUseToday()">
                <i class="ph-duotone ph-calendar-check"></i>
                Hoje
            </button>

            <span class="reg-calendar-shortcut">
                <kbd>←</kbd><kbd>→</kbd><kbd>↑</kbd><kbd>↓</kbd>
                <span>Navegar</span>
            </span>
        </div>
    </div>
</div>

{{-- Project modal --}}
<div class="modal-overlay" id="modal-project" aria-hidden="true">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Selecionar Projeto</span>
            <button class="modal-close" onclick="closeModal('project')">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>
        <div class="modal-search-wrap">
            <input class="modal-search" type="search" id="search-project" placeholder="Buscar projeto..." oninput="filterList('project')" autocomplete="off">
        </div>
        <div class="modal-list" id="list-project"></div>
    </div>
</div>

{{-- Associate modal --}}
<div class="modal-overlay" id="modal-assoc" aria-hidden="true">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Selecionar Associado</span>
            <button class="modal-close" onclick="closeModal('assoc')">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>
        <div class="modal-search-wrap">
            <input class="modal-search" type="search" id="search-assoc" placeholder="Buscar por nome ou registro..." oninput="filterList('assoc')" autocomplete="off">
        </div>
        <div class="modal-list" id="list-assoc"></div>
    </div>
</div>

{{-- Product modal --}}
<div class="modal-overlay" id="modal-product" aria-hidden="true">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-title">Selecionar Produto</span>
            <button class="modal-close" onclick="closeModal('product')">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>
        <div class="modal-search-wrap">
            <input class="modal-search" type="search" id="search-product" placeholder="Buscar produto..." oninput="filterList('product')" autocomplete="off">
        </div>
        <div class="product-modal-actions">
            <button class="btn-small" id="refresh-products-btn" type="button" onclick="refreshProductList()" title="Atualizar lista de produtos" aria-label="Atualizar lista de produtos">
                <i data-lucide="refresh-cw"></i>
                Atualizar lista
            </button>
            <button class="btn-small primary" id="add-product-limit-btn" type="button" onclick="openQuickQuota()" title="Adicionar produto e definir cota">
                <i data-lucide="package-plus"></i>
                Adicionar produto
            </button>
        </div>
        <div class="modal-list" id="list-product">
            <div class="modal-empty">Selecione um projeto primeiro</div>
        </div>
    </div>
</div>

{{-- Gestão rápida de cota do associado --}}
<div class="modal-overlay" id="modal-quota" aria-hidden="true">
    <div class="modal-box quota-modal-box" role="dialog" aria-modal="true" aria-labelledby="quota-modal-title">
        <div class="modal-header">
            <div>
                <span class="modal-title" id="quota-modal-title">Limite do produto</span>
                <div class="mi-sub" id="quota-associate-name"></div>
            </div>
            <button class="modal-close" type="button" onclick="closeQuickQuota()" aria-label="Fechar">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>
        <div class="quota-body" id="quota-body">
            <div class="modal-empty">Selecione um associado primeiro.</div>
        </div>
        <div class="quota-footer" id="quota-footer" hidden>
            <button class="btn-small danger" id="quota-delete-btn" type="button" onclick="deleteQuickQuota()" hidden>
                <i class="ph-duotone ph-trash"></i>
                Remover limite
            </button>
            <div style="display:flex;gap:.4rem;margin-left:auto">
                <button class="btn-small" type="button" onclick="closeQuickQuota()">Cancelar</button>
                <button class="btn-small primary" id="quota-save-btn" type="button" onclick="saveQuickQuota()">
                    <i data-lucide="save"></i>
                    Salvar limite
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Toast root --}}
<div id="toast-root"></div>

{{-- ─────────────── MODAL EDITAR ENTREGA (mantido) ──────────────── --}}
<div class="modal-overlay" id="modal-edit" aria-hidden="true">
    <div class="modal-box reg-edit-box" role="dialog" aria-modal="true" aria-labelledby="reg-edit-title">
        <div class="modal-header">
            <div class="reg-sheet-title-wrap">
                <span class="reg-sheet-title-icon blue" aria-hidden="true">
                    <i class="ph-duotone ph-pencil-simple"></i>
                </span>
                <div>
                    <span class="modal-title" id="reg-edit-title">Editar entrega</span>
                    <div class="reg-sheet-subtitle">Ajuste os dados do registro</div>
                </div>
            </div>
            <button class="modal-close" onclick="closeModal('edit')" aria-label="Fechar">
                <i class="ph-duotone ph-x"></i>
            </button>
        </div>

        <div class="reg-edit-body register-sheet-scroll">
            <div>
                <label class="field-label">Quantidade <span id="edit-unit-lbl"></span></label>
                <input class="field-input reg-number-input" type="number" id="edit-qty" min="0.001" step="0.001">
            </div>

            <div>
                <label class="field-label">Data da entrega</label>
                <input class="field-input reg-date-input" type="date" id="edit-date">
            </div>

            <div>
                <label class="field-label">Qualidade</label>
                <div class="quality-group" id="edit-quality-pills">
                    <button type="button" class="q-pill active" data-q="A">A</button>
                    <button type="button" class="q-pill" data-q="B">B</button>
                    <button type="button" class="q-pill" data-q="C">C</button>
                </div>
            </div>
        </div>

        <div class="reg-edit-footer">
            <button type="button" class="reg-sheet-action secondary" onclick="closeModal('edit')">
                Cancelar
            </button>
            <button type="button" class="reg-sheet-action primary" id="edit-save-btn" onclick="saveEdit()">
                <i class="ph-duotone ph-floppy-disk"></i>
                Salvar alterações
            </button>
        </div>
    </div>
</div>


<style id="register-mobile-sheet-system-v3">
/* =====================================================================
   Sheets, calendário, confirmação e viewport móvel
   ===================================================================== */

/*
 * Os sheets ficam fora de .reg-page no DOM. Portanto, repetimos aqui
 * a mesma paleta usada na project-deliveries para que calendário,
 * confirmação, cota, edição e distribuição herdem as cores corretas.
 */
.reg-page,
.modal-overlay,
.dist-summary-overlay,
#dm-overlay,
#delivery-notes-overlay {
    --rv-green:#168a4d;
    --rv-green-soft:#eaf8ef;
    --rv-blue:#2563eb;
    --rv-blue-soft:#eef4ff;
    --rv-sky:#0284c7;
    --rv-sky-soft:#edf8fe;
    --rv-violet:#7c3aed;
    --rv-violet-soft:#f4f0ff;
    --rv-amber:#c87408;
    --rv-amber-soft:#fff7e8;
    --rv-red:#cf3f3f;
    --rv-red-soft:#fff0f0;
    --rv-slate:#64748b;
    --rv-slate-soft:#f1f5f9;
    --rv-border:var(--color-border,#dce7e0);
    --rv-border-strong:var(--color-border-strong,#c8d6cd);
    --rv-text:var(--color-text,#102018);
    --rv-text-2:var(--color-text-secondary,#52645a);
    --rv-text-3:var(--color-text-muted,#809087);
    --rv-soft:var(--color-surface-soft,#f8faf9);
}

:root {
    --reg-vv-height: 100dvh;
    --reg-vv-top: 0px;
    --reg-keyboard-height: 0px;
}

html.register-sheet-open,
body.register-sheet-open {
    overscroll-behavior-y: none !important;
}

body.register-sheet-open {
    overflow: hidden !important;
}

/* Overlay ocupa exatamente a viewport visível, inclusive com teclado. */
.modal-overlay,
.dist-summary-overlay,
#dm-overlay,
#delivery-notes-overlay {
    box-sizing: border-box;
}

body.register-sheet-open .modal-overlay.open,
body.register-sheet-open .dist-summary-overlay.open,
body.register-sheet-open #dm-overlay.open,
body.register-sheet-open #delivery-notes-overlay.open {
    top: var(--reg-vv-top) !important;
    bottom: auto !important;
    height: var(--reg-vv-height) !important;
    max-height: var(--reg-vv-height) !important;
    overscroll-behavior: none !important;
}

/* Somente o conteúdo interno deve rolar. */
.modal-box,
.quota-modal-box,
.dist-summary-box,
.reg-calendar-box,
.reg-edit-box {
    display: flex !important;
    flex-direction: column !important;
    min-height: 0 !important;
    overflow: hidden !important;
}

.modal-header,
.product-modal-actions,
.quota-footer,
.reg-edit-footer,
.dist-summary-head,
.reg-calendar-head,
.reg-calendar-footer {
    flex: 0 0 auto !important;
}

.modal-list,
.quota-body,
.reg-edit-body,
.dist-summary-body,
.reg-calendar-body,
.register-sheet-scroll,
#dm-overlay .dm-body {
    min-height: 0 !important;
    overflow-y: auto !important;
    overscroll-behavior: contain !important;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}

.modal-list::-webkit-scrollbar,
.quota-body::-webkit-scrollbar,
.reg-edit-body::-webkit-scrollbar,
.dist-summary-body::-webkit-scrollbar,
.reg-calendar-body::-webkit-scrollbar,
.register-sheet-scroll::-webkit-scrollbar,
#dm-overlay .dm-body::-webkit-scrollbar {
    width: 0;
    height: 0;
}

/* Entrada do overlay e do sheet. */
.modal-overlay.open,
.dist-summary-overlay.open {
    animation: reg-overlay-fade .16s ease both;
}

.modal-overlay.open .modal-box,
.dist-summary-overlay.open .dist-summary-box,
#dm-overlay.open .dm-box,
#delivery-notes-overlay.open > :first-child {
    animation: reg-sheet-enter .24s cubic-bezier(.22,.78,.24,1) both;
    transform-origin: bottom center;
}

@keyframes reg-overlay-fade {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes reg-sheet-enter {
    from {
        opacity: .72;
        transform: translateY(22px) scale(.992);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* ---------- Custom confirm ---------- */

.register-confirm-overlay {
    z-index: 410000 !important;
    place-items: center !important;
    padding: 1rem !important;
}

.register-confirm-box {
    width: min(430px, 100%) !important;
    border-radius: 16px !important;
}

.register-confirm-head {
    display: grid;
    grid-template-columns: auto minmax(0,1fr);
    gap: .58rem;
    align-items: start;
    padding: .78rem;
}

.register-confirm-icon {
    display: inline-flex;
    width: 42px;
    height: 42px;
    align-items: center;
    justify-content: center;
    border-radius: 11px;
    background: var(--rv-blue-soft);
    color: var(--rv-blue);
}

.register-confirm-icon .ph-duotone {
    font-size: 21px !important;
}

.register-confirm-box.danger .register-confirm-icon {
    background: var(--rv-red-soft);
    color: var(--rv-red);
}

.register-confirm-box.success .register-confirm-icon {
    background: var(--rv-green-soft);
    color: var(--rv-green);
}

.register-confirm-copy {
    min-width: 0;
}

.register-confirm-copy strong {
    display: block;
    color: var(--rv-text);
    font-size: .82rem;
    font-weight: 850;
}

.register-confirm-copy p {
    margin: .13rem 0 0;
    color: var(--rv-text-2);
    font-size: .7rem;
    line-height: 1.48;
}

.register-confirm-footer {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .38rem;
    padding: .58rem .68rem calc(.58rem + env(safe-area-inset-bottom));
    border-top: 1px solid var(--rv-border);
    background: var(--rv-soft);
}

.register-confirm-btn {
    min-height: 42px;
    border: 1px solid var(--rv-border);
    border-radius: 10px;
    background: #fff;
    color: var(--rv-text-2);
    cursor: pointer;
    font: inherit;
    font-size: .7rem;
    font-weight: 810;
}

.register-confirm-btn.confirm {
    border-color: rgba(37,99,235,.16);
    background: var(--rv-blue-soft);
    color: var(--rv-blue);
}

.register-confirm-box.danger .register-confirm-btn.confirm {
    border-color: rgba(207,63,63,.16);
    background: var(--rv-red-soft);
    color: var(--rv-red);
}

.register-confirm-box.success .register-confirm-btn.confirm {
    border-color: rgba(22,138,77,.16);
    background: var(--rv-green-soft);
    color: var(--rv-green);
}

/* ---------- Todos os sheets ---------- */

.reg-sheet-title-wrap {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: .44rem;
}

.reg-sheet-title-icon {
    display: inline-flex;
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
}

.reg-sheet-title-icon.blue {
    background: var(--rv-blue-soft);
    color: var(--rv-blue);
}

.reg-sheet-subtitle {
    margin-top: .02rem;
    color: var(--rv-text-3);
    font-size: .61rem;
}

.reg-edit-box {
    width: min(430px, 100%) !important;
}

.reg-edit-body {
    display: grid;
    gap: .7rem;
    padding: .72rem;
}

.reg-edit-body .field-label {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: .28rem;
}

.reg-edit-body #edit-unit-lbl {
    color: var(--rv-text-3);
    font-size: .62rem;
    font-weight: 760;
}

.reg-edit-body .field-input {
    margin: 0 !important;
    min-height: 44px;
}

.reg-number-input {
    font-weight: 850 !important;
    font-variant-numeric: tabular-nums;
}

.reg-edit-body .quality-group {
    display: grid;
    grid-template-columns: repeat(3, minmax(0,1fr));
    gap: .35rem;
    margin: 0 !important;
}

.reg-edit-footer {
    display: grid;
    grid-template-columns: minmax(105px,.7fr) minmax(160px,1fr);
    gap: .4rem;
    padding: .58rem .68rem calc(.58rem + env(safe-area-inset-bottom));
    border-top: 1px solid var(--rv-border);
    background: #fff;
}

.reg-sheet-action {
    display: inline-flex;
    min-height: 43px;
    align-items: center;
    justify-content: center;
    gap: .28rem;
    border: 1px solid var(--rv-border);
    border-radius: 10px;
    background: #fff;
    color: var(--rv-text-2);
    cursor: pointer;
    font: inherit;
    font-size: .69rem;
    font-weight: 810;
}

.reg-sheet-action.primary {
    border-color: rgba(37,99,235,.16);
    background: var(--rv-blue-soft);
    color: var(--rv-blue);
}

/* Produto/cota: ações maiores e realmente tocáveis. */
.mi-quota-edit {
    width: 38px !important;
    min-width: 38px !important;
    height: 38px !important;
    min-height: 38px !important;
    padding: 0 !important;
    border-radius: 10px !important;
}

.mi-quota-edit .ph-duotone,
.mi-quota-edit i {
    font-size: 16px !important;
}

.product-modal-actions .btn-small,
.quota-footer .btn-small {
    min-height: 40px !important;
    padding: .42rem .62rem !important;
    border-radius: 10px !important;
    font-size: .68rem !important;
    font-weight: 810 !important;
}

.quota-footer {
    gap: .42rem !important;
    padding: .56rem .62rem calc(.56rem + env(safe-area-inset-bottom)) !important;
}

.quota-footer > div {
    display: grid !important;
    grid-template-columns: minmax(92px,.7fr) minmax(130px,1fr);
    gap: .4rem !important;
    flex: 1 1 auto;
    margin-left: 0 !important;
}

.quota-footer #quota-delete-btn {
    flex: 0 0 auto;
}

/* ---------- Calendário premium ---------- */

.reg-calendar-box {
    width: min(455px, 100%) !important;
    max-height: min(92dvh, 760px) !important;
    border-radius: 17px !important;
}

.reg-calendar-head {
    min-height: 64px;
    padding: .62rem .68rem !important;
    background:
        radial-gradient(circle at 100% 0, rgba(200,116,8,.10), transparent 13rem),
        linear-gradient(180deg,var(--rv-soft),#fff) !important;
}

.reg-calendar-icon {
    width: 40px !important;
    height: 40px !important;
    border-radius: 11px !important;
}

.reg-calendar-body {
    padding: .62rem;
    background: #fff;
}

.reg-calendar-selection {
    display: grid;
    grid-template-columns: auto minmax(0,1fr);
    gap: .48rem;
    align-items: center;
    margin-bottom: .5rem;
    padding: .48rem .54rem;
    border: 1px solid rgba(200,116,8,.15);
    border-radius: 11px;
    background: linear-gradient(90deg,var(--rv-amber-soft),#fff 82%);
}

.reg-calendar-selection-icon {
    display: inline-flex;
    width: 34px;
    height: 34px;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: #fff;
    color: var(--rv-amber);
}

.reg-calendar-selection small {
    display: block;
    color: var(--rv-text-3);
    font-size: .56rem;
    font-weight: 740;
}

.reg-calendar-selection strong {
    display: block;
    margin-top: .02rem;
    color: var(--rv-text);
    font-size: .82rem;
    font-weight: 880;
    font-variant-numeric: tabular-nums;
}

.reg-calendar-manual {
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

.reg-calendar-input-wrap {
    min-height: 45px !important;
    padding: .25rem .3rem .25rem .56rem !important;
    border-radius: 11px !important;
}

#calendar-manual-input {
    font-size: .96rem !important;
    font-weight: 880 !important;
}

.reg-calendar-use-btn {
    width: 36px !important;
    height: 36px !important;
    border-radius: 9px !important;
}

.reg-calendar-month-card {
    margin-top: .58rem;
    padding: .42rem .42rem .46rem;
    border: 1px solid var(--rv-border);
    border-radius: 13px;
    background: var(--rv-soft);
}

.reg-calendar-nav {
    padding: .08rem .05rem .42rem !important;
}

.reg-calendar-nav strong {
    font-size: .8rem !important;
    font-weight: 880 !important;
}

.reg-calendar-nav-btn {
    width: 37px !important;
    height: 37px !important;
    border-radius: 10px !important;
    background: #fff !important;
}

.reg-calendar-weekdays,
.reg-calendar-grid {
    gap: .28rem !important;
    padding: 0 !important;
}

.reg-calendar-weekdays {
    margin-bottom: .22rem !important;
}

.reg-calendar-weekdays span {
    padding: .2rem 0 !important;
    font-size: .57rem !important;
}

.reg-calendar-day {
    min-height: 40px;
    aspect-ratio: 1;
    border-radius: 10px !important;
    background: #fff !important;
    font-size: .72rem !important;
    font-weight: 790 !important;
}

.reg-calendar-day:hover,
.reg-calendar-day:focus-visible,
.reg-calendar-day.cursor {
    border-color: rgba(200,116,8,.22) !important;
    background: var(--rv-amber-soft) !important;
    color: var(--rv-amber) !important;
    box-shadow: 0 0 0 2px rgba(200,116,8,.045);
}

.reg-calendar-day.selected {
    border-color: var(--rv-amber) !important;
    background: var(--rv-amber) !important;
    color: #fff !important;
    box-shadow: 0 5px 12px rgba(200,116,8,.18);
}

.reg-calendar-day.today:not(.selected)::after {
    background: var(--rv-blue) !important;
}

.reg-calendar-footer {
    min-height: 52px;
    padding: .48rem .62rem calc(.48rem + env(safe-area-inset-bottom)) !important;
    background: linear-gradient(180deg,#fff,var(--rv-soft)) !important;
}

/* ---------- Detalhamento de distribuições ---------- */

.dist-summary-box {
    display: flex !important;
    flex-direction: column !important;
    width: min(520px, 100%) !important;
    max-height: min(86dvh, 680px) !important;
    overflow: hidden !important;
    border-radius: 16px !important;
}

.dist-summary-head {
    min-height: 60px;
    display: grid !important;
    grid-template-columns: minmax(0,1fr) auto !important;
    align-items: center !important;
    gap: .5rem !important;
    padding: .58rem .64rem !important;
}

.dist-summary-head-main {
    display: grid;
    min-width: 0;
    grid-template-columns: auto minmax(0,1fr);
    gap: .45rem;
    align-items: center;
}

.dist-summary-head-icon {
    display: inline-flex;
    width: 38px;
    height: 38px;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: var(--rv-violet-soft);
    color: var(--rv-violet);
}

.dist-summary-head-icon .ph-duotone {
    font-size: 18px !important;
}

.dist-summary-close {
    width: 34px !important;
    height: 34px !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--rv-border) !important;
    border-radius: 9px !important;
    background: #fff !important;
}

.dist-summary-body {
    flex: 1 1 auto;
    display: grid !important;
    align-content: start;
    gap: .48rem !important;
    padding: .56rem !important;
    background: var(--rv-soft);
}

.dist-summary-overview {
    display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    gap: .34rem;
    padding: .44rem;
    border: 1px solid var(--rv-border);
    border-radius: 11px;
    background: #fff;
}

.dist-summary-metric {
    min-width: 0;
    padding: .38rem .42rem;
    border-radius: 9px;
    background: var(--rv-soft);
}

.dist-summary-metric span {
    display: block;
    color: var(--rv-text-3);
    font-size: .55rem;
    font-weight: 730;
}

.dist-summary-metric strong {
    display: block;
    margin-top: .04rem;
    overflow: hidden;
    color: var(--rv-text);
    font-size: .72rem;
    font-weight: 880;
    font-variant-numeric: tabular-nums;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dist-summary-progress {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: minmax(0,1fr) auto;
    gap: .38rem;
    align-items: center;
}

.dist-summary-progress-track {
    height: 7px;
    overflow: hidden;
    border-radius: 999px;
    background: color-mix(in srgb,var(--rv-border) 80%,#fff);
}

.dist-summary-progress-fill {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: var(--rv-violet);
}

.dist-summary-progress-fill.complete {
    background: var(--rv-green);
}

.dist-summary-progress-fill.over {
    background: var(--rv-red);
}

.dist-summary-progress strong {
    color: var(--rv-text-2);
    font-size: .62rem;
    font-weight: 850;
}

.dist-summary-list {
    display: grid;
    gap: .34rem;
}

.dist-summary-row {
    display: grid !important;
    grid-template-columns: auto minmax(0,1fr) auto !important;
    gap: .45rem !important;
    align-items: center !important;
    padding: .48rem .5rem !important;
    border: 1px solid var(--rv-border) !important;
    border-radius: 10px !important;
    background: #fff !important;
}

.dist-summary-row-icon {
    display: inline-flex;
    width: 32px;
    height: 32px;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: var(--rv-blue-soft);
    color: var(--rv-blue);
}

.dist-summary-row-icon .ph-duotone {
    font-size: 15px !important;
}

.dist-summary-row-main {
    min-width: 0;
}

.dist-summary-row-main strong {
    display: block;
    overflow: hidden;
    color: var(--rv-text);
    font-size: .7rem;
    font-weight: 820;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.dist-summary-row-main small {
    display: block;
    margin-top: .04rem;
    color: var(--rv-text-3);
    font-size: .58rem;
}

.dist-summary-row-values {
    min-width: 0;
    text-align: right;
}

.dist-summary-row-values strong {
    display: block;
    color: var(--rv-text);
    font-size: .69rem;
    font-weight: 880;
    font-variant-numeric: tabular-nums;
}

.dist-summary-row-values small {
    display: block;
    margin-top: .04rem;
    color: var(--rv-green);
    font-size: .58rem;
    font-weight: 760;
}

/* ---------- Mobile ---------- */

@media(max-width:767px) {
    .modal-overlay,
    .dist-summary-overlay,
    #dm-overlay,
    #delivery-notes-overlay {
        align-items: flex-end !important;
        justify-content: center !important;
        padding: 0 !important;
    }

    .modal-box,
    .quota-modal-box,
    .reg-calendar-box,
    .reg-edit-box,
    .dist-summary-box,
    #dm-overlay .dm-box,
    #delivery-notes-overlay > :first-child {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        border-right: 0 !important;
        border-bottom: 0 !important;
        border-left: 0 !important;
        border-radius: 18px 18px 0 0 !important;
        max-height: calc(var(--reg-vv-height) - 8px) !important;
    }

    body.register-keyboard-visible .modal-box,
    body.register-keyboard-visible .quota-modal-box,
    body.register-keyboard-visible .reg-calendar-box,
    body.register-keyboard-visible .reg-edit-box,
    body.register-keyboard-visible .dist-summary-box,
    body.register-keyboard-visible #dm-overlay .dm-box,
    body.register-keyboard-visible #delivery-notes-overlay > :first-child {
        max-height: var(--reg-vv-height) !important;
        border-radius: 14px 14px 0 0 !important;
    }

    .product-modal-actions {
        grid-template-columns: 46px minmax(0,1fr) !important;
        gap: .38rem !important;
        padding: .46rem .52rem !important;
    }

    .product-modal-actions .btn-small {
        min-height: 44px !important;
    }

    #refresh-products-btn {
        width: 46px !important;
        min-width: 46px !important;
        height: 44px !important;
    }

    #add-product-limit-btn {
        font-size: .68rem !important;
    }

    .product-limit-item {
        min-height: 62px !important;
        padding: .48rem .52rem !important;
    }

    .product-limit-item .mi-quota-edit {
        width: 42px !important;
        min-width: 42px !important;
        height: 42px !important;
        min-height: 42px !important;
    }

    .quota-footer {
        display: grid !important;
        grid-template-columns: 1fr !important;
    }

    .quota-footer #quota-delete-btn {
        width: 100% !important;
        min-height: 44px !important;
    }

    .quota-footer > div {
        width: 100%;
        grid-template-columns: 1fr 1.35fr !important;
    }

    .quota-footer .btn-small {
        min-height: 44px !important;
        font-size: .68rem !important;
    }

    .reg-edit-footer {
        grid-template-columns: 1fr 1.35fr;
    }

    .reg-edit-footer .reg-sheet-action {
        min-height: 45px;
    }

    .reg-calendar-box {
        max-height: calc(var(--reg-vv-height) - 4px) !important;
    }

    .reg-calendar-body {
        padding: .54rem;
    }

    .reg-calendar-month-card {
        margin-top: .5rem;
    }

    .reg-calendar-day {
        min-height: 39px;
    }

    .reg-calendar-shortcut {
        display: none !important;
    }

    .dist-summary-box {
        max-height: min(calc(var(--reg-vv-height) - 6px), 78dvh) !important;
    }

    .dist-summary-overview {
        grid-template-columns: repeat(3,minmax(0,1fr));
    }

    .dist-summary-metric {
        padding: .34rem .3rem;
    }

    .dist-summary-metric span {
        font-size: .51rem;
    }

    .dist-summary-metric strong {
        font-size: .66rem;
    }

    .register-confirm-overlay {
        align-items: flex-end !important;
        padding: 0 !important;
    }

    .register-confirm-box {
        width: 100% !important;
        max-width: none !important;
        border-radius: 18px 18px 0 0 !important;
    }

    .register-confirm-btn {
        min-height: 45px;
    }
}

@media(max-width:380px) {
    .reg-calendar-body {
        padding: .46rem;
    }

    .reg-calendar-day {
        min-height: 36px;
        font-size: .68rem !important;
    }

    .dist-summary-overview {
        grid-template-columns: 1fr 1fr;
    }

    .dist-summary-progress {
        grid-column: 1 / -1;
    }

    .dist-summary-metric:nth-child(3) {
        grid-column: 1 / -1;
    }
}

@media(prefers-reduced-motion:reduce) {
    .modal-overlay.open,
    .dist-summary-overlay.open,
    .modal-overlay.open .modal-box,
    .dist-summary-overlay.open .dist-summary-box,
    #dm-overlay.open .dm-box,
    #delivery-notes-overlay.open > :first-child {
        animation: none !important;
    }
}
</style>

<script>
(function () {
'use strict';

/* ─── Constants ──────────────────────────────────── */
const TENANT      = @json($currentTenant->slug);
const CSRF        = @json(csrf_token());
const ITEMS_KEY   = 'sgc_items_' + TENANT;

const ROUTES = {
    demands    : (pid) => '/' + TENANT + '/delivery/projects/' + pid + '/demands',
    deliveries : (pid) => '/' + TENANT + '/delivery/projects/' + pid + '/deliveries-json',
    integrity  : (pid) => '/' + TENANT + '/delivery/projects/' + pid + '/integrity',
    resolveIntegrity : (pid) => '/' + TENANT + '/delivery/projects/' + pid + '/integrity/resolve',
    store      : '/' + TENANT + '/delivery/projects/' + @json($selectedProject['id']) + '/register',
    del        : (id)  => '/' + TENANT + '/delivery/deliveries/' + id,
};

/* ─── PHP data ───────────────────────────────────── */
const ALL_PROJECTS   = @json($projects);
const ALL_ASSOCIATES = @json($associates);
const ALL_CUSTOMERS  = @json($customers->map(fn($c) => ['id' => $c->id, 'name' => $c->trade_name ?: $c->name]));
const INITIAL_PROJECT = @json($selectedProject);  // null or project object
const INITIAL_ASSOCIATE_ID = Number(@json(request()->integer('associate'))) || null;

/* ─── State ──────────────────────────────────────── */
const S = {
    project   : null,
    associate : null,
    product   : null,
    demands   : [],
    quality   : 'A',
    submitting        : false,
    items             : [],
    loadingProjectId  : null,
    demandsRequestId  : 0,
    loadingDeliveries : false,
    deliveryReloadPending : null,
    dateConfirmed     : false,
    keyboardStage     : 'project',
    listPage          : 1,
    listPerPage       : 30,
    historyView       : null,
};

const Q = {
    limits: null,
    products: [],
    selected: null,
    current: null,
    quantity: 0,
    busy: false,
    confirmDelete: false,
};

/* ─── DOM refs ───────────────────────────────────── */
const $ = (id) => document.getElementById(id);


/* ─── History-aware sheets ───────────────────────── */
const REGISTER_SHEET_STATE_KEY = '__sgcRegisterSheet';
let registerCurrentSheet = null;
let registerHandlingPopState = false;
const registerSheetRegistry = new Map();

function isCompactDevice() {
    return window.matchMedia('(max-width: 767px)').matches;
}

function registerSheet(key, openDirect, closeDirect) {
    registerSheetRegistry.set(key, { openDirect, closeDirect });
}

function pushRegisterSheetState(key) {
    if (registerHandlingPopState) return;
    if (history.state?.[REGISTER_SHEET_STATE_KEY] === key) {
        registerCurrentSheet = key;
        return;
    }

    const nextState = {
        ...(history.state || {}),
        [REGISTER_SHEET_STATE_KEY]: key,
    };

    history.pushState(nextState, '', window.location.href);
    registerCurrentSheet = key;
}

function requestRegisterSheetClose(key, closeDirect) {
    if (registerHandlingPopState) {
        closeDirect?.();
        if (registerCurrentSheet === key) registerCurrentSheet = null;
        return;
    }

    if (
        registerCurrentSheet === key
        && history.state?.[REGISTER_SHEET_STATE_KEY] === key
    ) {
        history.back();
        return;
    }

    closeDirect?.();
    if (registerCurrentSheet === key) registerCurrentSheet = null;
}

function closeTopRegisterSheet() {
    if (!registerCurrentSheet) return false;

    if (history.state?.[REGISTER_SHEET_STATE_KEY] === registerCurrentSheet) {
        history.back();
        return true;
    }

    const handler = registerSheetRegistry.get(registerCurrentSheet);
    handler?.closeDirect?.();
    registerCurrentSheet = null;
    return true;
}

window.addEventListener('popstate', event => {
    const targetKey = event.state?.[REGISTER_SHEET_STATE_KEY] || null;
    const previousKey = registerCurrentSheet;

    registerHandlingPopState = true;

    try {
        if (previousKey && previousKey !== targetKey) {
            registerSheetRegistry.get(previousKey)?.closeDirect?.();
        }

        registerCurrentSheet = targetKey;

        if (targetKey) {
            registerSheetRegistry.get(targetKey)?.openDirect?.(true);
        }
    } finally {
        registerHandlingPopState = false;
        syncRegisterSheetEnvironment();
    }
});

/*
 * Um reload não deve restaurar um "sheet fantasma" no history.state.
 * Mantém as outras propriedades de state da aplicação.
 */
if (history.state?.[REGISTER_SHEET_STATE_KEY]) {
    history.replaceState(
        {
            ...(history.state || {}),
            [REGISTER_SHEET_STATE_KEY]: null,
        },
        '',
        window.location.href
    );
}


/* Sheets renderizados por componentes Blade externos (ex.: observações). */
function installExternalSheetHistoryObserver({
    selector,
    key,
    openClass = 'open',
}) {
    const element = document.querySelector(selector);
    if (!element || element.dataset.registerHistoryObserved === '1') return;

    element.dataset.registerHistoryObserved = '1';

    registerSheet(
        key,
        () => element.classList.add(openClass),
        () => element.classList.remove(openClass)
    );

    let wasOpen = element.classList.contains(openClass);

    const observer = new MutationObserver(() => {
        const isOpen = element.classList.contains(openClass);

        if (
            isOpen
            && !wasOpen
            && !registerHandlingPopState
            && registerCurrentSheet !== key
        ) {
            pushRegisterSheetState(key);
        } else if (
            !isOpen
            && wasOpen
            && !registerHandlingPopState
            && registerCurrentSheet === key
            && history.state?.[REGISTER_SHEET_STATE_KEY] === key
        ) {
            /*
             * O componente fechou por conta própria. Voltamos uma posição
             * para não deixar um estado de sheet fantasma no histórico.
             */
            history.back();
        }

        wasOpen = isOpen;
    });

    observer.observe(element, {
        attributes: true,
        attributeFilter: ['class'],
    });
}

function installExternalSheetHistoryObservers() {
    installExternalSheetHistoryObserver({
        selector: '#delivery-notes-overlay',
        key: 'notes',
    });
}


/* ─── Global sheet environment / custom confirm ──── */

let registerLockedScrollY = 0;
let registerBodyLockActive = false;
let registerTouchStartY = 0;
let registerConfirmResolver = null;
let registerConfirmLastFocus = null;

function getRegisterOpenSheets() {
    return Array.from(document.querySelectorAll([
        '.modal-overlay.open',
        '.dist-summary-overlay.open',
        '#dm-overlay.open',
        '#delivery-notes-overlay.open',
    ].join(',')));
}

function syncRegisterVisualViewport() {
    const vv = window.visualViewport;
    const height = vv?.height || window.innerHeight;
    const top = vv?.offsetTop || 0;
    const keyboardHeight = Math.max(
        0,
        window.innerHeight - height - top
    );
    const keyboardVisible = keyboardHeight > 110;

    document.documentElement.style.setProperty(
        '--reg-vv-height',
        `${Math.round(height)}px`
    );
    document.documentElement.style.setProperty(
        '--reg-vv-top',
        `${Math.round(top)}px`
    );
    document.documentElement.style.setProperty(
        '--reg-keyboard-height',
        `${Math.round(keyboardHeight)}px`
    );

    document.body.classList.toggle(
        'register-keyboard-visible',
        keyboardVisible
    );
}

function lockRegisterPageScroll() {
    if (registerBodyLockActive) return;

    registerLockedScrollY = window.scrollY || window.pageYOffset || 0;
    registerBodyLockActive = true;

    document.documentElement.classList.add('register-sheet-open');
    document.body.classList.add('register-sheet-open');

    document.body.style.position = 'fixed';
    document.body.style.top = `-${registerLockedScrollY}px`;
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';

    syncRegisterVisualViewport();
}

function unlockRegisterPageScroll() {
    if (!registerBodyLockActive) return;

    registerBodyLockActive = false;

    document.documentElement.classList.remove('register-sheet-open');
    document.body.classList.remove(
        'register-sheet-open',
        'register-keyboard-visible'
    );

    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';

    window.scrollTo(0, registerLockedScrollY);
}

function syncRegisterSheetEnvironment() {
    const hasOpen = getRegisterOpenSheets().length > 0;

    if (hasOpen) {
        lockRegisterPageScroll();
        syncRegisterVisualViewport();
    } else {
        unlockRegisterPageScroll();
    }
}

function registerFindScrollableElement(target) {
    const openSheet = target?.closest?.(
        '.modal-overlay.open, .dist-summary-overlay.open, #dm-overlay.open, #delivery-notes-overlay.open'
    );

    if (!openSheet) return null;

    let node = target instanceof Element ? target : null;

    while (node && node !== openSheet) {
        const style = window.getComputedStyle(node);
        const canScroll = (
            /(auto|scroll)/.test(style.overflowY)
            && node.scrollHeight > node.clientHeight + 1
        );

        if (canScroll) return node;
        node = node.parentElement;
    }

    return null;
}

/*
 * Evita pull-to-refresh e "bounce" no fundo da página enquanto há sheet.
 * Mantém o scroll normal da área interna do sheet.
 */
document.addEventListener('touchstart', event => {
    if (!registerBodyLockActive) return;
    registerTouchStartY = event.touches?.[0]?.clientY || 0;
}, { passive: true });

document.addEventListener('touchmove', event => {
    if (!registerBodyLockActive) return;
    if (!event.touches?.length) return;

    const target = event.target;

    if (
        target?.closest?.(
            'input[type="range"], [data-register-allow-touch="true"]'
        )
    ) {
        return;
    }

    const scrollable = registerFindScrollableElement(target);
    const currentY = event.touches[0].clientY;
    const deltaY = currentY - registerTouchStartY;

    if (!scrollable) {
        event.preventDefault();
        return;
    }

    const atTop = scrollable.scrollTop <= 0;
    const atBottom = (
        scrollable.scrollTop + scrollable.clientHeight
        >= scrollable.scrollHeight - 1
    );

    if ((atTop && deltaY > 0) || (atBottom && deltaY < 0)) {
        event.preventDefault();
    }
}, { passive: false });

if (window.visualViewport) {
    window.visualViewport.addEventListener(
        'resize',
        syncRegisterVisualViewport,
        { passive: true }
    );
    window.visualViewport.addEventListener(
        'scroll',
        syncRegisterVisualViewport,
        { passive: true }
    );
}

window.addEventListener(
    'orientationchange',
    () => window.setTimeout(syncRegisterVisualViewport, 80),
    { passive: true }
);

const registerSheetEnvironmentObserver = new MutationObserver(() => {
    syncRegisterSheetEnvironment();
});

document.querySelectorAll(
    '.modal-overlay, .dist-summary-overlay, #dm-overlay, #delivery-notes-overlay'
).forEach(element => {
    registerSheetEnvironmentObserver.observe(element, {
        attributes: true,
        attributeFilter: ['class'],
    });
});

function closeRegisterConfirmDirect(result = false) {
    const overlay = $('register-confirm-overlay');
    if (!overlay) return;

    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');

    const resolver = registerConfirmResolver;
    registerConfirmResolver = null;

    const focusTarget = registerConfirmLastFocus;
    registerConfirmLastFocus = null;

    resolver?.(result);
    focusTarget?.focus?.({ preventScroll: true });
}

function registerConfirm({
    title = 'Confirmar ação',
    message = 'Deseja continuar?',
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    tone = 'default',
    icon = null,
} = {}) {
    const overlay = $('register-confirm-overlay');
    const box = overlay?.querySelector('.register-confirm-box');

    if (!overlay || !box) {
        return Promise.resolve(false);
    }

    if (registerConfirmResolver) {
        registerConfirmResolver(false);
        registerConfirmResolver = null;
    }

    registerConfirmLastFocus = document.activeElement;

    $('register-confirm-title').textContent = title;
    $('register-confirm-message').textContent = message;
    $('register-confirm-ok').textContent = confirmLabel;
    $('register-confirm-cancel').textContent = cancelLabel;

    box.classList.remove('danger', 'success');
    if (tone === 'danger') box.classList.add('danger');
    if (tone === 'success') box.classList.add('success');

    const iconName = icon || (
        tone === 'danger'
            ? 'warning'
            : tone === 'success'
                ? 'check-circle'
                : 'question'
    );

    $('register-confirm-icon').innerHTML =
        `<i class="ph-duotone ph-${iconName}"></i>`;

    registerSheet(
        'confirm',
        () => {
            overlay.classList.add('open');
            overlay.setAttribute('aria-hidden', 'false');
        },
        () => closeRegisterConfirmDirect(false)
    );

    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    pushRegisterSheetState('confirm');

    return new Promise(resolve => {
        registerConfirmResolver = resolve;

        if (!isCompactDevice()) {
            window.setTimeout(() => {
                $('register-confirm-cancel')?.focus({ preventScroll: true });
            }, 40);
        }
    });
}

function resolveRegisterConfirm(value) {
    if (
        registerCurrentSheet === 'confirm'
        && history.state?.[REGISTER_SHEET_STATE_KEY] === 'confirm'
    ) {
        const resolver = registerConfirmResolver;
        registerConfirmResolver = null;

        registerConfirmLastFocus?.focus?.({ preventScroll: true });
        registerConfirmLastFocus = null;

        $('register-confirm-overlay')?.classList.remove('open');
        $('register-confirm-overlay')?.setAttribute('aria-hidden', 'true');

        history.back();
        resolver?.(value);
        return;
    }

    closeRegisterConfirmDirect(value);
}

$('register-confirm-ok')?.addEventListener(
    'click',
    () => resolveRegisterConfirm(true)
);

$('register-confirm-cancel')?.addEventListener(
    'click',
    () => resolveRegisterConfirm(false)
);

$('register-confirm-overlay')?.addEventListener('click', event => {
    if (event.target === $('register-confirm-overlay')) {
        resolveRegisterConfirm(false);
    }
});


/* ─── Custom calendar ────────────────────────────── */
const REGISTER_CALENDAR = {
    viewYear: new Date().getFullYear(),
    viewMonth: new Date().getMonth(),
    selectedIso: null,
    cursorIso: null,
};

function isoFromDateParts(year, month, day) {
    const y = Number(year);
    const m = Number(month);
    const d = Number(day);

    if (!Number.isInteger(y) || !Number.isInteger(m) || !Number.isInteger(d)) {
        return null;
    }

    const date = new Date(y, m - 1, d, 12, 0, 0, 0);

    if (
        date.getFullYear() !== y
        || date.getMonth() !== m - 1
        || date.getDate() !== d
    ) {
        return null;
    }

    return `${String(y).padStart(4, '0')}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

function isoToBr(iso) {
    if (!iso) return '';
    const [y, m, d] = String(iso).split('-');
    return `${d}/${m}/${y}`;
}

function parseBrDate(value) {
    const match = String(value || '').match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (!match) return null;
    return isoFromDateParts(match[3], match[2], match[1]);
}

function maskCalendarDateInput(value) {
    const digits = String(value || '').replace(/\D/g, '').slice(0, 8);
    const parts = [];

    if (digits.length > 0) parts.push(digits.slice(0, 2));
    if (digits.length > 2) parts.push(digits.slice(2, 4));
    if (digits.length > 4) parts.push(digits.slice(4, 8));

    return parts.join('/');
}

function setCalendarViewFromIso(iso) {
    const [y, m] = String(iso || '').split('-').map(Number);
    if (!y || !m) return;

    REGISTER_CALENDAR.viewYear = y;
    REGISTER_CALENDAR.viewMonth = m - 1;
}

function calendarMonthLabel() {
    return new Intl.DateTimeFormat('pt-BR', {
        month: 'long',
        year: 'numeric',
    }).format(
        new Date(
            REGISTER_CALENDAR.viewYear,
            REGISTER_CALENDAR.viewMonth,
            1,
            12
        )
    );
}

function renderCalendar() {
    const grid = $('calendar-grid');
    const label = $('calendar-month-label');
    if (!grid || !label) return;

    label.textContent = calendarMonthLabel();

    const selectedLabel = $('calendar-selected-label');
    if (selectedLabel) {
        selectedLabel.textContent = REGISTER_CALENDAR.selectedIso
            ? isoToBr(REGISTER_CALENDAR.selectedIso)
            : 'Nenhuma data';
    }

    const year = REGISTER_CALENDAR.viewYear;
    const month = REGISTER_CALENDAR.viewMonth;
    const first = new Date(year, month, 1, 12);
    const daysInMonth = new Date(year, month + 1, 0, 12).getDate();
    const mondayOffset = (first.getDay() + 6) % 7;
    const todayIso = new Date().toISOString().slice(0, 10);

    const cells = [];

    for (let i = 0; i < mondayOffset; i++) {
        cells.push('<span class="reg-calendar-day outside" aria-hidden="true"></span>');
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const iso = isoFromDateParts(year, month + 1, day);
        const selected = iso === REGISTER_CALENDAR.selectedIso;
        const cursor = iso === REGISTER_CALENDAR.cursorIso;
        const today = iso === todayIso;

        cells.push(`
            <button
                type="button"
                class="reg-calendar-day${selected ? ' selected' : ''}${cursor ? ' cursor' : ''}${today ? ' today' : ''}"
                data-calendar-date="${iso}"
                role="gridcell"
                aria-selected="${selected ? 'true' : 'false'}"
                title="${isoToBr(iso)}"
                onclick="calendarSelectDate('${iso}')"
            >${day}</button>
        `);
    }

    grid.innerHTML = cells.join('');
}

function openCalendarDirect(fromHistory = false) {
    const overlay = $('modal-date');
    if (!overlay) return;

    const currentIso = $('f-date')?.value || new Date().toISOString().slice(0, 10);

    REGISTER_CALENDAR.selectedIso = currentIso;
    REGISTER_CALENDAR.cursorIso = currentIso;
    setCalendarViewFromIso(currentIso);

    const manual = $('calendar-manual-input');
    if (manual) manual.value = isoToBr(currentIso);

    const help = $('calendar-manual-help');
    help?.classList.remove('error');
    if (help) help.textContent = 'Formato: dia/mês/ano';

    renderCalendar();
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    syncRegisterSheetEnvironment();

    if (!fromHistory && !isCompactDevice()) {
        window.setTimeout(() => {
            const selectedDay = document.querySelector(
                `[data-calendar-date="${REGISTER_CALENDAR.cursorIso}"]`
            );

            selectedDay?.focus({ preventScroll: true });
        }, 35);
    }
}

function openCalendarSheet() {
    registerSheet(
        'date',
        openCalendarDirect,
        () => {
            $('modal-date')?.classList.remove('open');
            $('modal-date')?.setAttribute('aria-hidden', 'true');
        }
    );

    openCalendarDirect(false);
    pushRegisterSheetState('date');
}

function closeCalendarDirect() {
    $('modal-date')?.classList.remove('open');
    $('modal-date')?.setAttribute('aria-hidden', 'true');
    syncRegisterSheetEnvironment();
}

function closeCalendarSheet() {
    requestRegisterSheetClose('date', closeCalendarDirect);
}

function calendarChangeMonth(delta) {
    REGISTER_CALENDAR.viewMonth += Number(delta || 0);

    if (REGISTER_CALENDAR.viewMonth < 0) {
        REGISTER_CALENDAR.viewMonth = 11;
        REGISTER_CALENDAR.viewYear -= 1;
    } else if (REGISTER_CALENDAR.viewMonth > 11) {
        REGISTER_CALENDAR.viewMonth = 0;
        REGISTER_CALENDAR.viewYear += 1;
    }

    renderCalendar();
}

function applyCalendarIso(iso) {
    if (!iso) return false;

    const input = $('f-date');
    if (!input) return false;

    input.value = iso;
    REGISTER_CALENDAR.selectedIso = iso;
    REGISTER_CALENDAR.cursorIso = iso;

    onDateChange(iso);
    closeCalendarSheet();

    return true;
}

function calendarSelectDate(iso) {
    applyCalendarIso(iso);
}

function calendarUseToday() {
    applyCalendarIso(new Date().toISOString().slice(0, 10));
}

function applyManualCalendarDate() {
    const input = $('calendar-manual-input');
    const help = $('calendar-manual-help');
    const iso = parseBrDate(input?.value || '');

    if (!iso) {
        help?.classList.add('error');
        if (help) help.textContent = 'Informe uma data válida no formato dd/mm/aaaa.';

        if (!isCompactDevice()) {
            input?.focus({ preventScroll: true });
            input?.select?.();
        }

        return;
    }

    applyCalendarIso(iso);
}

$('calendar-manual-input')?.addEventListener('input', event => {
    event.target.value = maskCalendarDateInput(event.target.value);

    const iso = parseBrDate(event.target.value);
    const help = $('calendar-manual-help');

    if (iso) {
        help?.classList.remove('error');
        if (help) help.textContent = 'Pressione Enter ou toque no botão para usar esta data.';

        REGISTER_CALENDAR.selectedIso = iso;
        REGISTER_CALENDAR.cursorIso = iso;
        setCalendarViewFromIso(iso);
        renderCalendar();
    } else if (String(event.target.value || '').length === 10) {
        help?.classList.add('error');
        if (help) help.textContent = 'Data inválida.';
    } else {
        help?.classList.remove('error');
        if (help) help.textContent = 'Ex.: 22/08/2026';
    }
});

$('calendar-manual-input')?.addEventListener('keydown', event => {
    if (event.key === 'Enter') {
        event.preventDefault();
        event.stopImmediatePropagation();
        applyManualCalendarDate();
    }
});

document.addEventListener('keydown', event => {
    if (!$('modal-date')?.classList.contains('open')) return;

    const manual = $('calendar-manual-input');

    if (event.target === manual) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopImmediatePropagation();
            closeCalendarSheet();
        }
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopImmediatePropagation();
        closeCalendarSheet();
        return;
    }

    const cursor = REGISTER_CALENDAR.cursorIso
        || REGISTER_CALENDAR.selectedIso
        || $('f-date')?.value
        || new Date().toISOString().slice(0, 10);

    const [y, m, d] = cursor.split('-').map(Number);
    const base = new Date(y, m - 1, d, 12);

    let delta = null;

    if (event.key === 'ArrowLeft') delta = -1;
    if (event.key === 'ArrowRight') delta = 1;
    if (event.key === 'ArrowUp') delta = -7;
    if (event.key === 'ArrowDown') delta = 7;

    if (event.key === 'PageUp' || event.key === 'PageDown') {
        event.preventDefault();
        event.stopImmediatePropagation();
        calendarChangeMonth(event.key === 'PageUp' ? -1 : 1);
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        event.stopImmediatePropagation();
        applyCalendarIso(cursor);
        return;
    }

    if (delta !== null) {
        event.preventDefault();
        event.stopImmediatePropagation();

        base.setDate(base.getDate() + delta);

        const nextIso = `${base.getFullYear()}-${String(base.getMonth() + 1).padStart(2, '0')}-${String(base.getDate()).padStart(2, '0')}`;

        REGISTER_CALENDAR.cursorIso = nextIso;
        REGISTER_CALENDAR.selectedIso = nextIso;
        REGISTER_CALENDAR.viewYear = base.getFullYear();
        REGISTER_CALENDAR.viewMonth = base.getMonth();

        if (manual) manual.value = isoToBr(nextIso);

        renderCalendar();

        window.requestAnimationFrame(() => {
            document.querySelector(`[data-calendar-date="${nextIso}"]`)?.focus?.({ preventScroll: true });
        });
    }
}, true);

/* ─── History view ───────────────────────────────── */
function defaultHistoryView() {
    if (window.matchMedia('(max-width: 1099px)').matches) return 'cards';

    const saved = window.localStorage?.getItem('sgc.register.historyView');
    return saved === 'cards' || saved === 'table' ? saved : 'table';
}

function applyHistoryView() {
    const cards = $('session-list');
    const table = $('session-table-wrap');
    const compact = window.matchMedia('(max-width: 1099px)').matches;
    const view = compact ? 'cards' : (S.historyView || defaultHistoryView());

    if (cards) cards.style.display = view === 'cards' ? 'grid' : 'none';

    if (table) {
        table.hidden = view !== 'table';
        table.style.display = view === 'table' ? 'block' : 'none';
    }

    document.querySelectorAll('[data-history-view]').forEach(button => {
        const active = button.dataset.historyView === view;
        button.classList.toggle('active', active);
        button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
}

function setHistoryView(view) {
    if (!['cards', 'table'].includes(view)) return;

    S.historyView = view;

    if (!window.matchMedia('(max-width: 1099px)').matches) {
        try {
            window.localStorage?.setItem('sgc.register.historyView', view);
        } catch (_) {}
    }

    applyHistoryView();
}

let registerHistoryResizeTimer = null;

window.addEventListener('resize', () => {
    window.clearTimeout(registerHistoryResizeTimer);
    registerHistoryResizeTimer = window.setTimeout(applyHistoryView, 80);
}, { passive: true });

/* ─── Highlight state for modals ─────────────────── */

const REGISTER_ICON_MAP = Object.freeze({
    'folder-open': 'folder-open',
    'user': 'user',
    'calendar': 'calendar-dots',
    'chevron-right': 'caret-right',
    'chevron-left': 'caret-left',
    'chevron-down': 'caret-down',
    'package': 'package',
    'package-plus': 'package',
    'message-square-plus': 'chat-circle-dots',
    'message-square-text': 'chat-text',
    'shield-alert': 'shield-warning',
    'sliders-horizontal': 'sliders-horizontal',
    'arrow-up': 'arrow-up',
    'refresh-cw': 'arrows-clockwise',
    'plus': 'plus',
    'trash-2': 'trash',
    'loader-circle': 'spinner-gap',
    'save': 'floppy-disk',
    'triangle-alert': 'warning',
    'panel-top-close': 'sliders-horizontal',
    'check': 'check-circle',
    'x': 'x-circle',
    'pencil': 'pencil-simple',
    'route': 'git-merge',
    'clock-counter-clockwise': 'clock-counter-clockwise',
    'eraser': 'eraser',
});

function upgradeRegisterIcons(root = document) {
    root.querySelectorAll?.('[data-lucide]').forEach(element => {
        const requested = element.getAttribute('data-lucide') || '';
        const icon = REGISTER_ICON_MAP[requested] || requested || 'circle';

        const next = document.createElement('i');
        next.className = `ph-duotone ph-${icon}`;

        const title = element.getAttribute('title');
        const aria = element.getAttribute('aria-label');
        const hidden = element.getAttribute('aria-hidden');

        if (title) next.setAttribute('title', title);
        if (aria) next.setAttribute('aria-label', aria);
        if (hidden) next.setAttribute('aria-hidden', hidden);

        element.replaceWith(next);
    });
}

const modalHighlightIndex = { project: -1, assoc: -1, product: -1 };

function resetModalHighlight(type) {
    highlightModalItem(type, 0);
}

function highlightModalItem(type, index) {
    const list = document.getElementById('list-' + type);
    if (!list) return;
    const items = list.querySelectorAll('.modal-item');
    items.forEach(el => el.classList.remove('highlighted'));
    if (items.length === 0) {
        modalHighlightIndex[type] = -1;
        return;
    }
    if (index >= items.length) index = items.length - 1;
    if (index < 0) index = 0;
    modalHighlightIndex[type] = index;
    items[index].classList.add('highlighted');
    items[index].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function selectModalHighlight(type) {
    const index = modalHighlightIndex[type];
    if (index < 0) return;
    const items = document.querySelectorAll('#list-' + type + ' .modal-item');
    if (items[index]) {
        items[index].click();
    }
}

/* ─── Keyboard navigation on modal search inputs ─── */
document.addEventListener('keydown', function(e) {
    if (!e.target.classList.contains('modal-search')) return;
    const type = e.target.id.replace('search-', '');
    if (!type || !['project','assoc','product'].includes(type)) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        e.stopImmediatePropagation();
        const idx = modalHighlightIndex[type] + 1;
        highlightModalItem(type, idx);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        e.stopImmediatePropagation();
        const idx = modalHighlightIndex[type] - 1;
        highlightModalItem(type, idx);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        e.stopImmediatePropagation();
        selectModalHighlight(type);
    }
});


/*
 * Navegação por teclado dos sheets de seleção também funciona quando
 * nenhum input recebeu foco (importante porque mobile não recebe autofocus).
 */
document.addEventListener('keydown', function(e) {
    if (e.target?.classList?.contains('modal-search')) return;
    if (isTypingField(e.target)) return;

    const type = ['project', 'assoc', 'product'].find(name =>
        $('modal-' + name)?.classList.contains('open')
    );

    if (!type) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        e.stopImmediatePropagation();
        highlightModalItem(type, modalHighlightIndex[type] + 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        e.stopImmediatePropagation();
        highlightModalItem(type, modalHighlightIndex[type] - 1);
    } else if (e.key === 'Enter') {
        e.preventDefault();
        e.stopImmediatePropagation();
        selectModalHighlight(type);
    }
}, true);

/* ─── Keep highlight in sync with mouse clicks ───── */
document.addEventListener('click', function(e) {
    const item = e.target.closest('.modal-item');
    if (!item) return;
    const list = item.closest('[id^="list-"]');
    if (!list) return;
    const type = list.id.replace('list-', '');
    const items = list.querySelectorAll('.modal-item');
    const index = Array.from(items).indexOf(item);
    if (index >= 0) {
        modalHighlightIndex[type] = index;
        items.forEach(el => el.classList.remove('highlighted'));
        item.classList.add('highlighted');
    }
});

/* ─── Init ───────────────────────────────────────── */
function init() {
    if (INITIAL_PROJECT) {
        applyProject(INITIAL_PROJECT);
        loadDemands(INITIAL_PROJECT.id, INITIAL_ASSOCIATE_ID);
    } else {
        renderSessionItems();
    }
    if (INITIAL_ASSOCIATE_ID) {
        const initialAssociate = ALL_ASSOCIATES.find(item => Number(item.id) === INITIAL_ASSOCIATE_ID);
        if (initialAssociate) selectAssociate(initialAssociate);
    }
    bindQualityPills();
    bindQtyInput();
    bindScrollTopButton();
    S.historyView = defaultHistoryView();
    applyHistoryView();
    
installDistModalHistoryBridge();

/*
 * O componente unificado ainda possui confirmações nativas em fluxos
 * legados de edição/remoção. Interceptamos somente esses pontos para
 * manter toda a experiência dentro do padrão visual do SGC.
 */
if (
    window.DistModal?.editExisting
    && window.DistModal?.saveExistingEdit
    && !window.__registerDistEditConfirmInstalled
) {
    window.__registerDistEditConfirmInstalled = true;

    const nativeDistEditExisting =
        window.DistModal.editExisting.bind(window.DistModal);
    const nativeDistSaveExisting =
        window.DistModal.saveExistingEdit.bind(window.DistModal);

    window.DistModal.editExisting = function(distributionId) {
        const row = document.getElementById('dmex-' + distributionId);

        if (row) {
            row.dataset.registerWasInReceipt =
                row.querySelector('.dm-status-badge.receipt') ? '1' : '0';
        }

        return nativeDistEditExisting(distributionId);
    };

    window.DistModal.saveExistingEdit = async function(distributionId) {
        const row = document.getElementById('dmex-' + distributionId);
        const needsReceiptConfirm =
            row?.dataset.registerWasInReceipt === '1';

        if (needsReceiptConfirm) {
            const confirmed = await registerConfirm({
                title: 'Editar distribuição vinculada',
                message: 'Esta distribuição está em um comprovante. Ao editar, o comprovante poderá precisar ser conferido novamente.',
                confirmLabel: 'Continuar edição',
                tone: 'danger',
                icon: 'warning',
            });

            if (!confirmed) {
                window.DistModal.cancelExistingEdit?.();
                return;
            }
        }

        const originalWindowConfirm = window.confirm;

        try {
            window.confirm = () => true;
            const result = nativeDistSaveExisting(distributionId);
            window.confirm = originalWindowConfirm;
            return await result;
        } finally {
            window.confirm = originalWindowConfirm;
        }
    };
}

if (
    window.DistModal?.deleteExisting
    && window.DistModal?.performDelete
    && !window.__registerDistDeleteConfirmInstalled
) {
    window.__registerDistDeleteConfirmInstalled = true;

    const nativeDistDeleteExisting =
        window.DistModal.deleteExisting.bind(window.DistModal);

    window.DistModal.deleteExisting = async function(distributionId) {
        const row = document.getElementById('dmex-' + distributionId);
        const inReceipt = !!row?.querySelector('.dm-status-badge.receipt');

        /*
         * Distribuição em comprovante já possui proteção especial no
         * componente. Mantemos esse fluxo próprio.
         */
        if (inReceipt) {
            return nativeDistDeleteExisting(distributionId);
        }

        const confirmed = await registerConfirm({
            title: 'Remover distribuição',
            message: 'Deseja remover esta distribuição? Os totais da entrega serão atualizados.',
            confirmLabel: 'Remover',
            tone: 'danger',
            icon: 'trash',
        });

        if (!confirmed) return;

        return window.DistModal.performDelete(distributionId, {});
    };
}

syncRegisterSheetEnvironment();

    installExternalSheetHistoryObservers();
    syncKeyboardStage();
}

/* ─── Project ────────────────────────────────────── */
function applyProject(proj) {
    S.project = {
        id           : proj.id,
        title        : proj.title,
        customerName : proj.customer_name,
        allowAny     : proj.allow_any_product,
        adminFee     : proj.admin_fee_percentage,
        customerIds  : proj.customer_ids || proj.customerIds || [],
        defaultCustomerId: proj.default_customer_id || proj.defaultCustomerId || null,
    };
    $('pb-title').textContent = proj.title;
    $('pb-sub').textContent   = proj.customer_name;
    $('sel-product').classList.remove('disabled');
    if (S.loadingProjectId !== proj.id) {
        S.demands = [];
        S.product = null;
        resetProductSelector();
    }
    syncKeyboardStage();
    loadProjectDeliveries(proj.id);
}

async function loadProjectDeliveries(projectId, force = false) {
    if (S.loadingDeliveries) {
        if (force) S.deliveryReloadPending = projectId;
        return;
    }
    S.loadingDeliveries = true;
    const empty = $('session-empty');
    if (empty) { empty.textContent = 'Carregando histórico…'; empty.style.display = 'block'; }
    try {
        const res = await fetch(ROUTES.deliveries(projectId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) throw new Error('Erro ' + res.status);
        S.items = (await res.json()).map(item => ({
            ...item,
            customerIds: item.customerIds || S.project?.customerIds || [],
            defaultCustomerId: item.defaultCustomerId || S.project?.defaultCustomerId || null,
        }));
    } catch (e) {
        toast('Erro ao carregar histórico: ' + e.message, 'error');
        S.items = [];
    } finally {
        S.loadingDeliveries = false;
        renderSessionItems();
        loadRegisterIntegrity(projectId);
        const pendingProjectId = S.deliveryReloadPending;
        S.deliveryReloadPending = null;
        if (pendingProjectId) loadProjectDeliveries(pendingProjectId, true);
    }
}

function toggleRegisterIntegrity() {
    const list = $('reg-integrity-list');
    const btn = document.querySelector('.reg-integrity-toggle');
    if (!list || !btn) return;
    const opening = list.hidden;
    list.hidden = !opening;
    btn.setAttribute('aria-expanded', opening ? 'true' : 'false');
    const icon = btn.querySelector('i');
    if (icon) icon.setAttribute('data-lucide', opening ? 'chevron-up' : 'chevron-down');
    upgradeRegisterIcons(list);
}

function integrityActionLabel(key) {
    return ({
        open_distribution: 'Gerenciar distribuicoes',
        edit_distribution: 'Corrigir distribuicao',
        detach_missing_associate_receipt: 'Desvincular comprovante',
        delete_orphan_distribution: 'Excluir distribuicao orfa',
        restore_parent_delivery: 'Restaurar entrega-pai',
        open_producers: 'Abrir comprovantes',
    })[key] || 'Ver detalhes';
}

function renderRegisterIntegrity(integrity) {
    const root = $('reg-integrity');
    const list = $('reg-integrity-list');
    if (!root || !list) return;
    const counts = integrity?.counts || {};
    root.hidden = false;
    $('reg-integrity-critical').textContent = 'Critico: ' + (counts.critical || 0);
    $('reg-integrity-warning').textContent = 'Atencao: ' + (counts.warning || 0);
    $('reg-integrity-info').textContent = 'Info: ' + (counts.info || 0);

    const issues = ['critical', 'warning', 'info'].flatMap(severity =>
        (integrity?.[severity] || []).map(issue => ({ ...issue, severity }))
    );
    list.innerHTML = issues.map(issue => {
        const action = issue.actionKey
            ? `<button type="button" class="btn-edit btn-xs" data-integrity-action="${escAttr(issue.actionKey)}" data-delivery-id="${Number(issue.deliveryId || 0)}" data-distribution-id="${Number(issue.distributionId || 0)}" data-associate-id="${Number(issue.associateId || 0)}" data-associate-name="${escAttr(issue.associateName || '')}">${escHtml(integrityActionLabel(issue.actionKey))}</button>`
            : '';
        return `<div class="reg-integrity-item ${issue.severity}" data-integrity-item="${escAttr(issue.actionKey || '')}-${Number(issue.distributionId || 0)}">
            <div class="reg-integrity-title">${escHtml(issue.title || '')}</div>
            <div class="reg-integrity-message">${escHtml(issue.message || '')}</div>
            <div class="reg-integrity-message" style="font-weight:700">${escHtml(issue.action || '')}</div>
            ${action ? `<div class="reg-integrity-actions">${action}</div>` : ''}
        </div>`;
    }).join('') || '<div class="reg-integrity-message">Nenhuma pendencia encontrada.</div>';
    upgradeRegisterIcons(document);
}

async function loadRegisterIntegrity(projectId) {
    if (!projectId) return;
    try {
        const res = await fetch(ROUTES.integrity(projectId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (data.success) renderRegisterIntegrity(data.integrity);
    } catch (error) {
        // The delivery history remains usable if the advisory feed is temporarily unavailable.
    }
}

async function handleRegisterIntegrityAction(button) {
    const action = button.dataset.integrityAction;
    const deliveryId = Number(button.dataset.deliveryId || 0);
    const distributionId = Number(button.dataset.distributionId || 0);
    const associateId = Number(button.dataset.associateId || 0);
    const associateName = button.dataset.associateName || '';

    if (action === 'open_distribution' || action === 'edit_distribution') {
        openDistributeModal(deliveryId);
        if (action === 'edit_distribution' && distributionId) {
            setTimeout(() => DistModal.editExisting(distributionId), 120);
        }
        return;
    }
    if (action === 'open_producers') {
        const query = associateId ? `?associate=${associateId}&name=${encodeURIComponent(associateName)}` : '';
        window.location.href = '/' + TENANT + '/delivery/projects/' + S.project.id + '/producers' + query;
        return;
    }

    const question = action === 'detach_missing_associate_receipt'
        ? 'Desvincular este comprovante inexistente? A distribuicao voltara a ficar disponivel.'
        : action === 'restore_parent_delivery'
            ? 'Restaurar a entrega-pai excluida? Quantidades, valores e comprovantes nao serao alterados.'
            : 'Excluir esta distribuicao orfa? Esta correcao nao pode ser desfeita.';
    const confirmed = await registerConfirm({
        title: 'Confirmar correção',
        message: question,
        confirmLabel: 'Continuar',
        tone: action === 'restore_parent_delivery' ? 'default' : 'danger',
        icon: action === 'restore_parent_delivery' ? 'arrow-counter-clockwise' : 'warning',
    });

    if (!confirmed) return;
    button.disabled = true;
    try {
        const res = await fetch(ROUTES.resolveIntegrity(S.project.id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ action, distribution_id: distributionId }),
        });
        const data = await res.json();
        if (!data.success) {
            toast(data.message || 'Nao foi possivel aplicar a correcao.', 'error');
            button.disabled = false;
            return;
        }
        renderRegisterIntegrity(data.integrity);
        loadProjectDeliveries(S.project.id);
        toast(data.message, 'success');
    } catch (error) {
        toast('Erro de comunicacao ao aplicar a correcao.', 'error');
        button.disabled = false;
    }
}

async function loadDemands(projectId, associateId = S.associate?.id) {
    const requestId = ++S.demandsRequestId;
    S.loadingProjectId = projectId;
    S.demands = [];
    S.product = null;
    resetProductSelector();

    try {
        const suffix = associateId ? '?associate_id=' + encodeURIComponent(associateId) : '';
        const res  = await fetch(ROUTES.demands(projectId) + suffix, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) throw new Error('Erro ' + res.status);
        const demands = await res.json();
        if (requestId === S.demandsRequestId) S.demands = demands;
    } catch (e) {
        if (requestId === S.demandsRequestId) {
            toast('Erro ao carregar produtos: ' + e.message, 'error');
        }
    } finally {
        if (requestId === S.demandsRequestId) S.loadingProjectId = null;
    }
}

async function refreshProductList() {
    if (!S.project) {
        toast('Selecione um projeto primeiro.', 'info');
        return;
    }

    const button = $('refresh-products-btn');
    const selectedProductId = S.product?.product_id || null;

    try {
        button.disabled = true;
        button.innerHTML = '<i data-lucide="loader-circle"></i> Atualizando';
        upgradeRegisterIcons(document);
        await loadDemands(S.project.id, S.associate?.id);

        if (selectedProductId) {
            S.product = S.demands.find(item => Number(item.product_id) === Number(selectedProductId)) || null;
            if (!S.product) resetProductSelector();
        }

        renderModalList('product');
        toast('Lista de produtos atualizada.', 'success');
    } finally {
        button.disabled = false;
        button.innerHTML = '<i data-lucide="refresh-cw"></i> Atualizar lista';
        upgradeRegisterIcons(document);
    }
}

function quotaBaseUrl() {
    return '/' + TENANT + '/delivery/projects/' + S.project.id + '/associates/' + S.associate.id;
}

async function openQuickQuota(productId = null) {
    if (!S.project || !S.associate) {
        toast('Selecione um associado antes de configurar produtos.', 'info');
        return;
    }

    const overlay = $('modal-quota');

    registerSheet(
        'quota',
        () => overlay.classList.add('open'),
        () => overlay.classList.remove('open')
    );

    overlay.classList.add('open');
    pushRegisterSheetState('quota');
    $('quota-associate-name').textContent = S.associate.nickname || S.associate.name;
    $('quota-footer').hidden = true;
    $('quota-body').innerHTML = '<div class="modal-empty">Carregando limites...</div>';
    Q.selected = null;
    Q.current = null;
    Q.confirmDelete = false;

    try {
        const [limitsResponse, productsResponse] = await Promise.all([
            fetch(quotaBaseUrl() + '/data/limits', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }),
            fetch(quotaBaseUrl() + '/data/products', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }),
        ]);
        const limits = await limitsResponse.json();
        const products = await productsResponse.json();
        if (!limitsResponse.ok) throw new Error(limits.message || 'Não foi possível carregar os limites.');
        if (!productsResponse.ok) throw new Error(products.message || 'Não foi possível carregar os produtos.');

        Q.limits = limits;
        Q.products = (products.data || []).filter(product =>
            S.project.allow_any_product || product.project_maximum !== null
        );

        if (productId) {
            selectQuickQuotaProduct(productId);
        } else {
            renderQuickQuotaPicker();
        }
    } catch (error) {
        $('quota-body').innerHTML = '<div class="modal-empty">' + escHtml(error.message) + '</div>';
    }
}

function closeQuickQuota(force = false) {
    if (Q.busy && !force) return;

    const closeDirect = () => {
        $('modal-quota')?.classList.remove('open');
        Q.selected = null;
        Q.current = null;
    };

    requestRegisterSheetClose('quota', closeDirect);
}

function renderQuickQuotaPicker() {
    $('quota-modal-title').textContent = 'Adicionar produto permitido';
    $('quota-footer').hidden = true;
    $('quota-body').innerHTML =
        '<input class="modal-search" id="quota-product-search" type="search" placeholder="Buscar produto..." autocomplete="off">' +
        '<div class="quota-picker-list" id="quota-picker-list"></div>';
    $('quota-product-search').addEventListener('input', renderQuickQuotaPickerItems);
    renderQuickQuotaPickerItems();
    if (!isCompactDevice()) {
        setTimeout(() => $('quota-product-search')?.focus({ preventScroll: true }), 40);
    }
}

function renderQuickQuotaPickerItems() {
    const list = $('quota-picker-list');
    if (!list) return;
    const term = normalizeSearch($('quota-product-search')?.value || '');
    const currentIds = new Set((Q.limits?.products || []).map(item => Number(item.product_id)));
    const items = Q.products.filter(product =>
        (!term || normalizeSearch(product.name).includes(term))
    );

    list.innerHTML = items.length ? items.map(product => {
        const existing = currentIds.has(Number(product.id));
        const available = product.available_for_associate === null
            ? null
            : Number(product.available_for_associate || 0);
        const delivered = Number(product.delivered_quantity || 0);
        const disabled = available !== null && available + .000001 < Math.max(delivered, .001);

        return '<button class="quota-picker-item" type="button" data-quota-product="' + Number(product.id) + '" ' + (disabled ? 'disabled' : '') + '>' +
            '<div><strong>' + escHtml(product.name) + '</strong><span>' +
                (existing ? 'Limite já configurado · ' : '') +
                money(Number(product.price || 0)) + ' por ' + escHtml(product.unit || 'unidade') +
            '</span></div><span>' +
                (disabled ? 'Sem saldo' : available === null ? 'Sem meta geral' : fmtQty(available, product.unit)) +
            '</span></button>';
    }).join('') : '<div class="modal-empty">Nenhum produto encontrado.</div>';

    list.querySelectorAll('[data-quota-product]').forEach(button => {
        button.addEventListener('click', () => selectQuickQuotaProduct(Number(button.dataset.quotaProduct)));
    });
}

function selectQuickQuotaProduct(productId) {
    const product = Q.products.find(item => Number(item.id) === Number(productId));
    if (!product) {
        $('quota-body').innerHTML = '<div class="modal-empty">Produto não disponível para este projeto.</div>';
        return;
    }

    Q.selected = product;
    Q.current = (Q.limits?.products || []).find(item => Number(item.product_id) === Number(productId)) || null;
    Q.quantity = Number(Q.current?.maximum_quantity ?? Math.max(Number(product.delivered_quantity || 0), .001));
    Q.confirmDelete = false;
    renderQuickQuotaEditor();
}

function quickQuotaOtherPlanned() {
    return Math.max(
        0,
        Number(Q.limits?.summary?.simulated_limit_value || 0)
        - Number(Q.current?.estimated_maximum_value || 0)
    );
}

function quickQuotaMaximum() {
    if (!Q.selected) return 0;
    const productMaximum = Q.selected.available_for_associate === null
        ? Infinity
        : Number(Q.selected.available_for_associate || 0);
    const financialLimit = Q.limits?.summary?.financial_limit === null
        ? Infinity
        : Number(Q.limits?.summary?.financial_limit || 0);
    const price = Number(Q.selected.price || 0);
    const financialMaximum = Number.isFinite(financialLimit) && price > 0
        ? Math.max(0, (financialLimit - quickQuotaOtherPlanned()) / price)
        : Infinity;

    return Math.max(
        Number(Q.selected.delivered_quantity || Q.current?.delivered_quantity || 0),
        Math.min(productMaximum, financialMaximum)
    );
}

function quickQuotaSliderMaximum() {
    const maximum = quickQuotaMaximum();
    return Number.isFinite(maximum)
        ? maximum
        : Math.max(100, Q.quantity, Math.ceil(Q.quantity * 1.5));
}

function renderQuickQuotaEditor() {
    const product = Q.selected;
    const delivered = Number(Q.current?.delivered_quantity ?? product.delivered_quantity ?? 0);
    const maximum = quickQuotaMaximum();
    Q.quantity = Math.max(delivered, Math.min(Q.quantity, maximum));
    $('quota-modal-title').textContent = Q.current ? 'Editar limite do produto' : 'Adicionar produto permitido';
    $('quota-footer').hidden = false;
    $('quota-delete-btn').hidden = !Q.current;
    $('quota-delete-btn').innerHTML = '<i class="ph-duotone ph-trash"></i> Remover limite';

    $('quota-body').innerHTML =
        '<div><strong style="font-size:.92rem">' + escHtml(product.name) + '</strong><div class="mi-sub">' +
            money(Number(product.price || 0)) + ' por ' + escHtml(product.unit || 'unidade') + '</div></div>' +
        '<div class="quota-summary-grid">' +
            '<div class="quota-metric"><span>Já entregue</span><strong id="quota-delivered"></strong></div>' +
            '<div class="quota-metric"><span>Nova cota</span><strong id="quota-value-label"></strong></div>' +
            '<div class="quota-metric"><span>Saldo</span><strong id="quota-balance"></strong></div>' +
            '<div class="quota-metric"><span>Valor planejado</span><strong id="quota-planned-value"></strong></div>' +
        '</div>' +
        '<div class="quota-progress-block"><div class="quota-progress-head"><span>Uso da cota do associado</span><span id="quota-use-label"></span></div><div class="quota-progress" id="quota-use-progress"><span></span></div></div>' +
        '<div class="quota-progress-block"><div class="quota-progress-head"><span>Meta geral do projeto</span><span id="quota-project-label"></span></div><div class="quota-progress" id="quota-project-progress"><span></span></div></div>' +
        '<div class="quota-progress-block"><div class="quota-progress-head"><span>Teto financeiro do associado</span><span id="quota-financial-label"></span></div><div class="quota-progress" id="quota-financial-progress"><span></span></div></div>' +
        '<div class="quota-edit-grid"><label>Ajustar deslizando<input class="quota-slider" id="quota-range" type="range" min="' + delivered + '" max="' + quickQuotaSliderMaximum() + '" step=".001" value="' + Q.quantity + '"></label>' +
        '<label>Cota máxima (' + escHtml(product.unit || 'un') + ')<input class="field-input" id="quota-number" type="number" min="' + delivered + '" ' + (Number.isFinite(maximum) ? 'max="' + maximum + '"' : '') + ' step=".001" value="' + Q.quantity + '"></label></div>' +
        '<div class="quota-feedback" id="quota-feedback"></div>';

    $('quota-range').addEventListener('input', event => updateQuickQuota(event.target.value));
    $('quota-number').addEventListener('input', event => updateQuickQuota(event.target.value, true));
    $('quota-number').addEventListener('blur', commitQuickQuota);
    refreshQuickQuotaEditor();
    upgradeRegisterIcons(document);
}

function progressTone(element, percent) {
    element.classList.toggle('warning', percent >= 80 && percent < 100);
    element.classList.toggle('danger', percent >= 100);
}

function refreshQuickQuotaEditor(message = '') {
    if (!Q.selected) return;
    const product = Q.selected;
    const delivered = Number(Q.current?.delivered_quantity ?? product.delivered_quantity ?? 0);
    const maximum = quickQuotaMaximum();
    const price = Number(product.price || 0);
    const quantity = Number.isFinite(Q.quantity) ? Q.quantity : 0;
    const financialLimit = Q.limits?.summary?.financial_limit === null
        ? null
        : Number(Q.limits?.summary?.financial_limit || 0);
    const plannedTotal = quickQuotaOtherPlanned() + quantity * price;
    const usePercent = quantity > 0 ? Math.min(100, delivered / quantity * 100) : 0;
    const projectMaximum = product.project_maximum === null ? null : Number(product.project_maximum || 0);
    const projectAllocated = Number(product.allocated_to_others || 0) + quantity;
    const projectPercent = projectMaximum && projectMaximum > 0
        ? Math.min(100, projectAllocated / projectMaximum * 100)
        : 0;
    const financialPercent = financialLimit && financialLimit > 0
        ? Math.min(100, plannedTotal / financialLimit * 100)
        : 0;

    $('quota-delivered').textContent = fmtQty(delivered, product.unit);
    $('quota-value-label').textContent = Number.isFinite(Q.quantity) ? fmtQty(quantity, product.unit) : '—';
    $('quota-balance').textContent = Number.isFinite(Q.quantity) ? fmtQty(Math.max(0, quantity - delivered), product.unit) : '—';
    $('quota-planned-value').textContent = Number.isFinite(Q.quantity) ? money(quantity * price) : '—';
    $('quota-use-label').textContent = Math.round(usePercent) + '% entregue';
    $('quota-use-progress').querySelector('span').style.width = usePercent + '%';
    progressTone($('quota-use-progress'), usePercent);
    $('quota-project-label').textContent = projectMaximum === null
        ? 'Sem meta geral'
        : fmtQty(projectAllocated, product.unit) + ' de ' + fmtQty(projectMaximum, product.unit);
    $('quota-project-progress').querySelector('span').style.width = projectPercent + '%';
    progressTone($('quota-project-progress'), projectPercent);
    $('quota-financial-label').textContent = financialLimit === null
        ? 'Sem teto definido'
        : money(plannedTotal) + ' de ' + money(financialLimit);
    $('quota-financial-progress').querySelector('span').style.width = financialPercent + '%';
    progressTone($('quota-financial-progress'), financialPercent);
    $('quota-feedback').textContent = message || (!Number.isFinite(Q.quantity)
        ? 'Informe uma cota para continuar.'
        : (Number.isFinite(maximum)
            ? 'Máximo disponível agora: ' + fmtQty(maximum, product.unit) + '.'
            : 'Sem limite máximo de quantidade para este produto.')
    );
    const invalid = !Number.isFinite(Q.quantity) || Q.quantity <= 0 || Q.quantity < delivered - .000001 || Q.quantity > maximum + .000001;
    $('quota-feedback').classList.toggle('error', Boolean(message) || invalid);
    $('quota-save-btn').disabled = Q.busy || invalid;
}

function updateQuickQuota(rawValue, fromNumber = false) {
    if (fromNumber) {
        Q.quantity = rawValue === '' ? Number.NaN : Number(String(rawValue).replace(',', '.'));
        refreshQuickQuotaEditor();
        return;
    }
    const parsed = Number(String(rawValue).replace(',', '.'));
    if (!Number.isFinite(parsed)) return;

    const delivered = Number(Q.current?.delivered_quantity ?? Q.selected?.delivered_quantity ?? 0);
    const maximum = quickQuotaMaximum();
    Q.quantity = Math.max(delivered, Math.min(parsed, maximum));
    $('quota-number').value = Q.quantity;
    $('quota-range').max = quickQuotaSliderMaximum();
    $('quota-range').value = Q.quantity;
    refreshQuickQuotaEditor(parsed > maximum + .000001
        ? 'A cota foi limitada pela meta do projeto ou pelo teto financeiro.'
        : ''
    );
}

function commitQuickQuota() {
    if (!Number.isFinite(Q.quantity) || !Q.selected) return;

    const delivered = Number(Q.current?.delivered_quantity ?? Q.selected.delivered_quantity ?? 0);
    const maximum = quickQuotaMaximum();
    Q.quantity = Math.max(delivered, Math.min(Q.quantity, maximum));
    $('quota-number').value = Q.quantity;
    $('quota-range').max = quickQuotaSliderMaximum();
    $('quota-range').value = Q.quantity;
    refreshQuickQuotaEditor();
}

async function saveQuickQuota() {
    if (!Q.selected || Q.busy || !Number.isFinite(Q.quantity)) return;
    const button = $('quota-save-btn');

    try {
        Q.busy = true;
        button.disabled = true;
        button.innerHTML = '<i data-lucide="loader-circle"></i> Salvando';
        const response = await fetch(quotaBaseUrl() + '/limits/product', {
            method: 'PUT',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: JSON.stringify({
                product_id: Q.selected.id,
                max_quantity: Number(Q.quantity.toFixed(3)),
            }),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Não foi possível salvar o limite.');

        await loadDemands(S.project.id, S.associate.id);
        closeQuickQuota(true);
        renderModalList('product');
        toast(data.message || 'Limite atualizado.', 'success');
    } catch (error) {
        refreshQuickQuotaEditor(error.message);
    } finally {
        Q.busy = false;
        button.disabled = false;
        button.innerHTML = '<i data-lucide="save"></i> Salvar limite';
        upgradeRegisterIcons(document);
    }
}

async function deleteQuickQuota() {
    if (!Q.current?.delete_url || Q.busy) return;

    const confirmed = await registerConfirm({
        title: 'Remover limite do produto',
        message: 'O limite será removido. As entregas já registradas serão preservadas.',
        confirmLabel: 'Remover limite',
        tone: 'danger',
        icon: 'trash',
    });

    if (!confirmed) return;

    const button = $('quota-delete-btn');

    try {
        Q.busy = true;
        button.disabled = true;

        const response = await fetch(Q.current.delete_url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
            },
            body: '{}',
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Não foi possível remover o limite.'
            );
        }

        await loadDemands(S.project.id, S.associate.id);
        closeQuickQuota(true);
        renderModalList('product');
        toast(data.message || 'Limite removido.', 'success');
    } catch (error) {
        refreshQuickQuotaEditor(error.message);
    } finally {
        Q.busy = false;
        button.disabled = false;
    }
}

/* ─── Associate ──────────────────────────────────── */
function selectAssociate(assoc) {
    S.associate = assoc;
    S.dateConfirmed = false;
    $('sel-assoc').classList.add('selected');
    $('assoc-value').textContent = assoc.nickname || assoc.name;
    closeModal('assoc');
    checkFormReady();
    renderSessionItems();
    syncKeyboardStage();
    if (S.project) loadDemands(S.project.id, assoc.id);
}

/* ─── Product ────────────────────────────────────── */
function selectProduct(demand) {
    S.product = demand;
    const el = $('sel-product');
    el.classList.add('selected');
    $('product-value').textContent = demand.product_name;
    const meta = $('product-meta');
    const parts = [];
    if (demand.associate_limit !== null && demand.associate_limit !== undefined) {
        parts.push('Seu limite: ' + fmtQty(demand.associate_limit, demand.product_unit));
        parts.push('Saldo: ' + fmtQty(Math.max(0, demand.associate_remaining), demand.product_unit));
    } else {
        parts.push('Entregue pelo associado: ' + fmtQty(demand.associate_delivered ?? demand.delivered_quantity, demand.product_unit));
    }
    if (demand.project_limit !== null && demand.project_limit !== undefined) {
        parts.push('Saldo do projeto: ' + fmtQty(Math.max(0, demand.project_remaining), demand.product_unit));
    }
    meta.textContent = parts.join(' | ');
    meta.style.display = 'block';
    const qtyInput = $('f-qty');
    if (demand.remaining_quantity !== null && demand.remaining_quantity !== undefined) {
        qtyInput.max = Math.max(0, demand.remaining_quantity);
    } else {
        qtyInput.removeAttribute('max');
    }
    $('f-unit-lbl').textContent = '(' + (demand.product_unit || 'un') + ')';
    closeModal('product');
    checkFormReady();
    syncKeyboardStage();
}

function resetProductSelector() {
    S.product = null;
    const el = $('sel-product');
    el.classList.remove('selected');
    $('product-value').textContent = 'Nenhum selecionado';
    $('product-meta').style.display = 'none';
    $('f-qty')?.removeAttribute('max');
    checkFormReady();
    syncKeyboardStage();
}

/* ─── Form logic ─────────────────────────────────── */
function bindQualityPills() {
    document.querySelectorAll('.quality-group .q-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            const group = btn.closest('.quality-group');
            group.querySelectorAll('.q-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (group.id !== 'edit-quality-pills') S.quality = btn.dataset.q;
        });
    });
}

function onDateChange(val) {
    if (!val) return;
    const [y, m, d] = val.split('-');
    $('date-display').textContent = d + '/' + m + '/' + y;
    $('sel-date').classList.add('selected');
    S.dateConfirmed = true;
    syncKeyboardStage();
}

function focusDateInput() {
    openCalendarSheet();
}

function clearFilter() {
    ['filter-history-search', 'filter-status', 'filter-associate', 'filter-product', 'filter-date-from', 'filter-date-to'].forEach(id => {
        const el = $(id);
        if (el) el.value = '';
    });
    renderSessionItems();
}

function toggleHistoryFilters() {
    const filter = $('history-filter');
    const button = $('history-filter-toggle');
    if (!filter || !button) return;

    const open = button.getAttribute('aria-expanded') === 'true';
    const nextOpen = !open;

    button.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
    button.setAttribute(
        'aria-label',
        nextOpen ? 'Ocultar filtros adicionais' : 'Mostrar mais filtros'
    );
    button.title = nextOpen ? 'Ocultar filtros' : 'Mais filtros';

    filter.classList.toggle('filters-expanded', nextOpen);

    upgradeRegisterIcons(filter);
}

function bindQtyInput() {
    $('f-qty').addEventListener('input', checkFormReady);
}

function checkFormReady() {
    const hasProject = !!S.project;
    const hasAssoc   = !!S.associate;
    const hasProd    = !!S.product;
    const hasQty     = parseFloat($('f-qty')?.value || 0) > 0;

    const showForm = hasAssoc && hasProd;
    $('entry-fields').style.display = showForm ? '' : 'none';

    if ($('btn-submit')) {
        $('btn-submit').disabled = !(hasProject && hasAssoc && hasProd && hasQty) || S.submitting;
    }

    const selProd = $('sel-product');
    if (hasProject) {
        selProd.classList.remove('disabled');
    } else {
        selProd.classList.add('disabled');
    }
}

function syncKeyboardStage() {
    if (!S.project) {
        S.keyboardStage = 'project';
        return;
    }
    if (!S.associate) {
        S.keyboardStage = 'associate';
        return;
    }
    if (!S.dateConfirmed) {
        S.keyboardStage = 'date';
        return;
    }
    if (!S.product) {
        S.keyboardStage = 'product';
        return;
    }
    S.keyboardStage = 'qty';
}

/* ─── Submit ─────────────────────────────────────── */
async function submitEntry() {
    if (S.submitting) return;

    const qty  = parseFloat($('f-qty').value || 0);
    const date = $('f-date').value;

    if (!S.project)   return toast('Selecione um projeto.', 'error');
    if (!S.associate) return toast('Selecione um associado.', 'error');
    if (!S.product)   return toast('Selecione um produto.', 'error');
    if (qty <= 0)     return toast('Informe a quantidade.', 'error');
    if (!date)        return toast('Informe a data.', 'error');

    S.submitting = true;
    checkFormReady();

    const payload = {
        sales_project_id  : S.project.id,
        project_demand_id : S.product.id ?? null,
        product_id        : S.product.product_id ?? null,
        associate_id      : S.associate.id,
        delivery_date     : date,
        quantity          : qty,
        quality_grade     : S.quality,
        notes             : $('f-notes').value.trim() || null,
    };

    try {
        const res  = await fetch(ROUTES.store, {
            method : 'POST',
            headers: {
                'Content-Type'     : 'application/json',
                'X-CSRF-TOKEN'     : CSRF,
                'X-Requested-With' : 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.success) {
            toast('Entrega registrada!', 'success');
            addSessionItem({
                id           : data.delivery.id,
                projectId    : S.project.id,
                associateId  : S.associate.id,
                productName  : S.product.product_name,
                productUnit  : S.product.product_unit || 'un',
                associateName: S.associate.name,
                qty          : qty,
                date         : date,
                quality      : S.quality,
                notes        : payload.notes || '',
                status       : 'pending',
                distributedQty: 0,
                distributions : [],
                has_billed   : false,
                dist_net_value: 0,
                customerIds   : S.project?.customerIds || [],
                defaultCustomerId: S.project?.defaultCustomerId || null,
                limit: {
                    associate_limit: S.product.associate_limit ?? null,
                    associate_delivered: (S.product.associate_delivered || 0) + qty,
                    associate_remaining: S.product.associate_limit == null ? null : Math.max(0, S.product.associate_limit - (S.product.associate_delivered || 0) - qty),
                    associate_percent: S.product.associate_limit > 0 ? Math.min(100, (((S.product.associate_delivered || 0) + qty) / S.product.associate_limit) * 100) : null,
                    project_remaining: S.product.project_limit == null ? null : Math.max(0, S.product.project_remaining - qty),
                },
            });
            await loadProjectDeliveries(S.project.id, true);
            S.product = null;
            resetProductSelector();
            $('f-qty').value  = '';
            $('f-notes').value = '';
            toggleEntryNotes(false);
            document.querySelectorAll('#quality-group .q-pill').forEach(b => b.classList.remove('active'));
            document.querySelector('#quality-group .q-pill[data-q="A"]').classList.add('active');
            S.quality = 'A';
            checkFormReady();
            if (S.project) loadDemands(S.project.id);
        } else {
            toast(data.message || 'Erro ao registrar.', 'error');
        }
    } catch (e) {
        toast('Erro de comunicação: ' + e.message, 'error');
    } finally {
        S.submitting = false;
        checkFormReady();
    }
}


/* ─── Observação da nova entrega ─────────────────── */
function syncEntryNotesUi() {
    const field = $('f-notes');
    const wrap = $('reg-notes-field');
    const button = $('reg-notes-toggle');
    const hint = $('reg-notes-toggle-hint');

    if (!field || !wrap || !button) return;

    const hasValue = !!String(field.value || '').trim();
    const open = !wrap.hidden;

    button.classList.toggle('open', open);
    button.classList.toggle('has-value', hasValue);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');

    if (hint) {
        hint.textContent = hasValue ? 'Observação adicionada' : 'Opcional';
    }
}

function toggleEntryNotes(force = null) {
    const field = $('f-notes');
    const wrap = $('reg-notes-field');

    if (!field || !wrap) return;

    const shouldOpen = force === null
        ? wrap.hidden
        : !!force;

    wrap.hidden = !shouldOpen;
    syncEntryNotesUi();

    if (shouldOpen && !isCompactDevice()) {
        window.setTimeout(() => {
            field.focus({ preventScroll: true });
        }, 20);
    }
}

/* ─── Session list (agora com cards mobile) ──────── */
function addSessionItem(item) {
    if (item.distributedQty  === undefined) item.distributedQty  = 0;
    if (item.distributions   === undefined) item.distributions   = [];
    if (item.has_billed      === undefined) item.has_billed      = false;
    if (item.dist_net_value  === undefined) item.dist_net_value  = 0;
    S.items.unshift(item);
    renderSessionItems();
}

function renderSessionItems() {
    const list  = $('session-list');
    const count = $('session-count');
    const empty = $('session-empty');
    const titleEl = $('session-list-title');
    const projectId = S.project?.id ?? null;

    if (titleEl) {
        titleEl.textContent = S.project ? 'Historico - ' + S.project.title : 'Historico de entregas';
    }

    const filtered = projectId
        ? S.items.filter(i => i.projectId === projectId)
        : S.items.filter(i => !i.projectId);

    const searchText  = normalizeSearch($('filter-history-search')?.value || '');
    const status      = ($('filter-status')?.value || '').trim();
    const assocSearch = normalizeSearch($('filter-associate')?.value || '');
    const prodSearch  = normalizeSearch($('filter-product')?.value || '');
    const dateFrom    = ($('filter-date-from')?.value || '').trim();
    const dateTo      = ($('filter-date-to')?.value || '').trim();
    const usingFilter = !!(searchText || status || assocSearch || prodSearch || dateFrom || dateTo);

    const renderList = filtered.filter(i => {
        const itemStatus  = i.status || 'pending';
        const assocText   = normalizeSearch(i.associateName || '');
        const productText = normalizeSearch(i.productName || '');
        const dateText    = normalizeSearch(`${i.date || ''} ${i.date ? fmtDate(i.date) : ''}`);
        const fullText    = `${dateText} ${assocText} ${productText}`;

        if (searchText && !fullText.includes(searchText)) return false;
        if (status && itemStatus !== status) return false;
        if (assocSearch && !assocText.includes(assocSearch)) return false;
        if (prodSearch && !productText.includes(prodSearch)) return false;
        if (dateFrom && i.date < dateFrom) return false;
        if (dateTo && i.date > dateTo) return false;
        return true;
    });

    const perPage = S.listPerPage === 'all' ? renderList.length || 1 : parseInt(S.listPerPage || 30, 10);
    const totalPages = Math.max(1, Math.ceil(renderList.length / perPage));
    S.listPage = Math.min(Math.max(S.listPage || 1, 1), totalPages);
    const pageStart = (S.listPage - 1) * perPage;
    const pageItems = renderList.slice(pageStart, pageStart + perPage);

    Array.from(list.children).forEach(c => { if (c !== empty) c.remove(); });

    if (renderList.length === 0) {
        if (empty) {
            empty.textContent = usingFilter
                ? 'Nenhuma entrega encontrada para os filtros aplicados'
                : (projectId ? 'Nenhuma entrega registrada para este projeto' : 'Selecione um projeto para ver o historico de entregas');
            empty.style.display = 'block';
        }
        count.textContent = '';
        const tableBody = $('session-table-body');
        if (tableBody) tableBody.innerHTML = '';
        updateSessionPagination(0, 0, 0, 0, 1);
        applyHistoryView();

        /*
         * Mesmo que a preferência desktop seja tabela, estados vazios
         * precisam continuar visíveis.
         */
        list.style.display = 'grid';
        const tableWrap = $('session-table-wrap');
        if (tableWrap) {
            tableWrap.hidden = true;
            tableWrap.style.display = 'none';
        }

        return;
    }

    if (empty) empty.style.display = 'none';
    count.textContent = renderList.length + (usingFilter ? '/' + filtered.length : '') + ' registro' + (renderList.length !== 1 ? 's' : '');
    updateSessionPagination(renderList.length, pageStart + 1, Math.min(pageStart + perPage, renderList.length), S.listPage, totalPages);

    function buildActionsHtml(item, labelClass = 'dc-action-label') {
        const statusClass = item.status || 'pending';
        const isPending = statusClass === 'pending';
        const isApproved = statusClass === 'approved';
        const isRejected = statusClass === 'rejected';
        const isBilled = !!item.has_billed;

        let buttons = item.notes
            ? `<button class="delivery-note-trigger dc-action notes" type="button"
                data-delivery-notes="${escAttr(item.notes)}"
                data-delivery-notes-title="Observações da entrega"
                data-delivery-notes-meta="${escAttr(item.productName + ' · ' + item.associateName)}"
                title="Observações" aria-label="Observações">
                    <i class="ph-duotone ph-chat-text"></i>
                    <span class="${labelClass}">Observações</span>
               </button>`
            : '';

        if (isPending) {
            buttons += `<button class="btn-approve btn-xs dc-action approve" data-action="approve" data-id="${item.id}" title="Aprovar entrega" aria-label="Aprovar entrega"><i class="ph-duotone ph-check-circle"></i><span class="${labelClass}">Aprovar</span></button>`;
            buttons += `<button class="btn-reject btn-xs dc-action reject" data-action="reject" data-id="${item.id}" title="Rejeitar entrega" aria-label="Rejeitar entrega"><i class="ph-duotone ph-x-circle"></i><span class="${labelClass}">Rejeitar</span></button>`;
            buttons += `<button class="btn-edit btn-xs dc-action edit" data-action="edit" data-id="${item.id}" title="Editar entrega" aria-label="Editar entrega"><i class="ph-duotone ph-pencil-simple"></i><span class="${labelClass}">Editar</span></button>`;
            if (!isBilled) {
                buttons += `<button class="btn-delete-approved btn-xs dc-action delete" data-action="delete" data-id="${item.id}" title="Excluir entrega pendente" aria-label="Excluir entrega pendente"><i class="ph-duotone ph-trash"></i><span class="${labelClass}">Excluir</span></button>`;
            }
        } else if (isApproved) {
            buttons += `<button class="btn-distribute btn-xs dc-action distribute" data-action="distribute" data-id="${item.id}" title="Distribuir entrega" aria-label="Distribuir entrega"><i class="ph-duotone ph-git-merge"></i><span class="${labelClass}">Distribuir</span></button>`;
            buttons += `<button class="btn-edit btn-xs dc-action edit" data-action="edit" data-id="${item.id}" title="Editar entrega" aria-label="Editar entrega"><i class="ph-duotone ph-pencil-simple"></i><span class="${labelClass}">Editar</span></button>`;

            if (!isBilled) {
                buttons += `<button class="btn-delete-approved btn-xs dc-action delete" data-action="delete-approved" data-id="${item.id}" title="Excluir entrega" aria-label="Excluir entrega"><i class="ph-duotone ph-trash"></i><span class="${labelClass}">Excluir</span></button>`;
            }
        } else if (isRejected) {
            if (!isBilled) {
                buttons += `<button class="btn-delete-approved btn-xs dc-action delete" data-action="delete" data-id="${item.id}" title="Excluir entrega rejeitada" aria-label="Excluir entrega rejeitada"><i class="ph-duotone ph-trash"></i><span class="${labelClass}">Excluir</span></button>`;
            }
        } else if (!isApproved && !isBilled) {
            buttons += `<button class="btn-delete-approved btn-xs dc-action delete" data-action="delete" data-id="${item.id}" title="Excluir entrega" aria-label="Excluir entrega"><i class="ph-duotone ph-trash"></i><span class="${labelClass}">Excluir</span></button>`;
        }

        return buttons;
    }

    function buildTableRow(item) {
        const distQty = parseFloat(item.distributedQty || 0);
        const totalQty = parseFloat(item.qty || 0);
        const rawPercent = totalQty > 0 ? Math.round((distQty / totalQty) * 100) : 0;
        const overDist = distQty > totalQty + 0.0005;
        const distPercent = Math.min(Math.max(rawPercent, 0), 100);
        const statusClass = item.status || 'pending';
        const visualClass = statusClass === 'approved' && distPercent >= 100 && !overDist
            ? 'distributed'
            : statusClass;
        const stateLabel = getDeliveryStateLabel(statusClass, distPercent, overDist);
        const stateIcon = getDeliveryStateIcon(statusClass, distPercent, overDist);
        const distJson = escAttr(JSON.stringify(item.distributions || []));
        const dateLabel = item.date ? isoToBr(item.date) : '—';
        const actions = buildActionsHtml(item, 'reg-table-action-label');

        return `
            <tr
                class="status-${visualClass}"
                data-delivery-id="${item.id}"
                data-total-qty="${totalQty}"
                data-unit="${escAttr(item.productUnit || '')}"
                data-product="${escAttr(item.productName)}"
                data-distributions="${distJson}"
                data-distributed="${distQty}"
            >
                <td>
                    <div class="reg-table-date">
                        <span class="reg-table-state" title="${escAttr(stateLabel)}" aria-label="${escAttr(stateLabel)}">${stateIcon}</span>
                        <strong>${escHtml(dateLabel)}</strong>
                    </div>
                </td>

                <td class="reg-session-associate" title="${escAttr(item.associateName)}">${escHtml(item.associateName)}</td>
                <td class="reg-session-product" title="${escAttr(item.productName)}">${escHtml(item.productName)}</td>
                <td class="reg-session-qty">${fmtQty(totalQty, item.productUnit)}</td>

                <td>
                    <div class="reg-table-control-row">
                        <button
                            type="button"
                            class="reg-table-dist"
                            data-summary="1"
                            title="${overDist ? 'Distribuição acima da quantidade registrada' : (distPercent >= 100 ? 'Totalmente distribuído' : 'Ver distribuição')}"
                            aria-label="Ver distribuição de ${escAttr(item.productName)}"
                        >
                            <span class="reg-table-dist-bar">
                                <span
                                    class="reg-table-dist-fill ${overDist ? 'over' : (distPercent >= 100 ? 'full' : 'partial')}"
                                    style="display:block;width:${overDist ? 100 : distPercent}%"
                                ></span>
                            </span>

                            <span class="reg-session-dist-text">${overDist ? '!' : distPercent + '%'}</span>
                        </button>

                        <div class="reg-table-actions">${actions}</div>
                    </div>
                </td>
            </tr>
        `;
    }

    function buildCard(item) {
        const distQty = parseFloat(item.distributedQty || 0);
        const totalQty = parseFloat(item.qty || 0);
        const distPercent = totalQty > 0 ? Math.min(Math.round((distQty / totalQty) * 100), 100) : 0;
        const overDist = distQty > totalQty;
        const displayPercent = overDist ? 100 : distPercent;
        const statusClass = item.status || 'pending';
        const visualClass = statusClass === 'approved' && distPercent >= 100 && !overDist ? 'distributed' : statusClass;
        const isPending = statusClass === 'pending';
        const isApproved = statusClass === 'approved';
        const isRejected = statusClass === 'rejected';
        const isBilled = !!item.has_billed;
        const netValue = parseFloat(item.dist_net_value || 0);
        const dateStr = item.date ? fmtDate(item.date) + '/' + (item.date.split('-')[0]?.slice(2) || '') : '';
        const stateLabel = getDeliveryStateLabel(statusClass, distPercent, overDist);
        const stateIcon = getDeliveryStateIcon(statusClass, distPercent, overDist);
        const distJson = escAttr(JSON.stringify(item.distributions || []));
        const billedTag = isBilled ? '<span class="mc-billed">Fat.</span>' : '';
        const statusTag = isRejected ? '<span class="mc-status-pill rejected">Rejeitada</span>' : '';
        const limit = item.limit || {};
        const limitPct = limit.associate_percent == null ? null : Math.min(100, Number(limit.associate_percent));
        const limitColor = limitPct == null ? '#94a3b8' : limitPct >= 100 ? '#dc2626' : limitPct >= 80 ? '#d97706' : '#059669';
        const limitHtml = limit.associate_limit == null ? '' : `<div style="display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.45rem;font-size:.68rem;color:var(--color-text-muted);padding:.2rem 0"><span>Limite</span><div style="height:5px;background:#e5e7eb;border-radius:4px;overflow:hidden"><span style="display:block;height:100%;width:${limitPct}%;background:${limitColor}"></span></div><strong style="color:var(--color-text)">${fmtQty(limit.associate_remaining,item.productUnit)} livres</strong></div>`;

        const actionsHtml = buildActionsHtml(item);

        return `<article class="mobile-card delivery-card-v2 status-${visualClass}" id="row-${item.id}" data-total-qty="${totalQty}" data-unit="${escAttr(item.productUnit || '')}" data-product="${escAttr(item.productName)}" data-distributions="${distJson}" data-distributed="${distQty}">
            <div class="dc-head mc-head"><div class="dc-main"><div class="dc-product-line"><strong class="dc-product mc-head-product" title="${escAttr(item.productName)}">${escHtml(item.productName)}</strong>${statusTag}${billedTag}</div><div class="dc-context"><span class="dc-context-item"><i class="ph-duotone ph-user"></i><span class="dc-associate mc-associate" title="${escAttr(item.associateName)}">${escHtml(item.associateName)}</span></span><span class="dc-context-item date"><i class="ph-duotone ph-calendar-dots"></i>${dateStr}</span></div></div><div class="dc-side"><strong class="dc-qty mc-head-qty">${fmtQty(totalQty,item.productUnit)}</strong><span class="mc-state-icon" title="${escAttr(stateLabel)}">${stateIcon}</span></div></div>
            <div class="dc-body mc-body">${limit.associate_limit == null ? '' : `<div class="dc-meter dc-limit-meter ${limitPct >= 100 ? 'red' : limitPct >= 80 ? 'amber' : 'green'}"><div class="dc-meter-head"><span class="dc-meter-label"><i class="ph-duotone ph-gauge"></i>Cota</span><strong class="dc-meter-value">${fmtQty(limit.associate_remaining,item.productUnit)} livres</strong></div><div class="dc-track"><span style="width:${limitPct}%"></span></div></div>`}<div class="dc-meter dc-distribution"><div class="dc-meter-head"><span class="dc-meter-label"><i class="ph-duotone ph-git-merge"></i>Distribuição</span><strong class="dc-meter-value">${overDist ? 'Excedeu ' + fmtQty(distQty-totalQty,item.productUnit) : distPercent >= 100 ? 'Concluída' : fmtQty(totalQty-distQty,item.productUnit) + ' restantes'}</strong></div><div class="mc-dist-indicator" role="button" tabindex="0" data-summary="1"><div class="mc-dist-bar-bg"><div class="mc-dist-bar-fill ${overDist ? 'over' : distPercent >= 100 ? 'full' : 'partial'}" style="width:${displayPercent}%"></div></div><span class="mc-dist-text">${overDist ? '!' : distPercent + '%'}</span></div></div>${netValue > 0 ? `<strong class="mc-net">${money(netValue)}</strong>` : ''}<div class="dc-actions mc-actions">${actionsHtml}</div></div></article>`;
    }

    function buildSectionHeader(label) {
        const h = document.createElement('div');
        h.className = 'session-section-header';
        h.textContent = label;
        return h;
    }

    function buildCollapsibleSection(label, items) {
        const section = document.createElement('details');
        section.className = 'session-collapsible';
        const summary = document.createElement('summary');
        summary.innerHTML = `
            <span class="session-other-main">
                <i class="ph-duotone ph-users-three" aria-hidden="true"></i>
                <strong>${escHtml(label)}</strong>
            </span>

            <span class="session-other-cta">
                Ver ${items.length}
                <i class="ph-duotone ph-caret-down" aria-hidden="true"></i>
            </span>
        `;
        const content = document.createElement('div');
        content.className = 'session-collapsible-list';
        items.forEach(item => {
            const card = document.createElement('div');
            card.innerHTML = buildCard(item);
            content.appendChild(card.firstElementChild);
        });
        section.append(summary, content);
        return section;
    }

    if (S.associate) {
        const assocItems = pageItems.filter(i => i.associateId === S.associate.id || (!i.associateId && i.associateName === S.associate.name));
        const othersItems = pageItems.filter(i => i.associateId !== S.associate.id && (i.associateId || i.associateName !== S.associate.name));

        if (assocItems.length > 0) {
            list.appendChild(buildSectionHeader(S.associate.name));
            assocItems.forEach(item => {
                const card = document.createElement('div');
                card.innerHTML = buildCard(item);
                list.appendChild(card.firstElementChild);
            });
        }
        if (othersItems.length > 0) {
            list.appendChild(buildCollapsibleSection('Outros produtores', othersItems));
        }
    } else {
        pageItems.forEach(item => {
            const card = document.createElement('div');
            card.innerHTML = buildCard(item);
            list.appendChild(card.firstElementChild);
        });
    }

    const tableBody = $('session-table-body');

    if (tableBody) {
        tableBody.innerHTML = pageItems.map(buildTableRow).join('');
    }

    applyHistoryView();
    upgradeRegisterIcons(document);
}
/* Helpers */
function fmtQty(n, unit) {
    const num = parseFloat(n) || 0;
    const str = num % 1 === 0 ? num.toString() : num.toFixed(2).replace(/\.?0+$/, '');
    return str + (unit ? '\u00a0' + unit : '');
}
function money(value) {
    return Number(value || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    });
}
function fmtDate(iso) {
    if (!iso) return '';
    const [y, m, d] = iso.split('-');
    return d + '/' + m;
}
function escHtml(str) {
    return (str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ─── Delete item (pendente ou aprovada) ──────────── */
function escAttr(str) {
    return escHtml(str).replace(/'/g, '&#039;');
}
function getDeliveryStateLabel(status, percent, over) {
    if (over) return 'Distribuicao acima da quantidade registrada';
    if (status === 'approved' && percent >= 100) return 'Aprovada e 100% distribuida';
    if (status === 'approved') return 'Aprovada com distribuicao pendente';
    if (status === 'pending') return 'Pendente de aprovacao';
    if (status === 'rejected') return 'Rejeitada';
    return 'Cancelada';
}
function getDeliveryStateIcon(status, percent, over) {
    if (over) {
        return '<i class="ph-duotone ph-warning"></i>';
    }

    if (status === 'approved' && percent >= 100) {
        return '<i class="ph-duotone ph-check-circle"></i>';
    }

    if (status === 'approved') {
        return '<i class="ph-duotone ph-plus-circle"></i>';
    }

    if (status === 'pending') {
        return '<i class="ph-duotone ph-clock"></i>';
    }

    if (status === 'rejected') {
        return '<i class="ph-duotone ph-x-circle"></i>';
    }

    return '<i class="ph-duotone ph-minus-circle"></i>';
}
function updateSessionPagination(total, start, end, page, totalPages) {
    const wrap = $('session-pagination');
    if (!wrap) return;
    wrap.style.display = total > 0 ? 'flex' : 'none';
    $('session-page-info').textContent = total > 0 ? `${start}-${end} de ${total}` : '';
    $('session-prev').disabled = page <= 1;
    $('session-next').disabled = page >= totalPages;
}
function setSessionPageSize(value) {
    S.listPerPage = value === 'all' ? 'all' : parseInt(value || 30, 10);
    S.listPage = 1;
    renderSessionItems();
}
function changeSessionPage(delta) {
    S.listPage = Math.max(1, (S.listPage || 1) + delta);
    renderSessionItems();
}
function parseCardDistributions(card) {
    try { return JSON.parse(card?.dataset?.distributions || '[]') || []; }
    catch (e) { return []; }
}
function openDistSummaryFromElement(element) {
    const product = element?.dataset?.product || 'Produto';
    const unit = element?.dataset?.unit || '';
    const totalQty = parseFloat(element?.dataset?.totalQty || 0);
    const distQty = parseFloat(element?.dataset?.distributed || 0);
    const distributions = parseCardDistributions(element);

    const remaining = Math.max(0, totalQty - distQty);
    const rawPercent = totalQty > 0
        ? Math.round((distQty / totalQty) * 100)
        : 0;
    const percent = Math.max(0, rawPercent);
    const widthPercent = Math.min(100, percent);
    const over = distQty > totalQty + 0.0005;
    const complete = !over && totalQty > 0 && distQty >= totalQty - 0.0005;

    $('dist-summary-title').textContent = product;
    $('dist-summary-sub').textContent = distributions.length
        ? `${distributions.length} destino${distributions.length !== 1 ? 's' : ''}`
        : 'Nenhuma distribuição registrada';

    const progressClass = over
        ? ' over'
        : complete
            ? ' complete'
            : '';

    $('dist-summary-overview').innerHTML = `
        <div class="dist-summary-metric">
            <span>Entrega</span>
            <strong>${fmtQty(totalQty, unit)}</strong>
        </div>

        <div class="dist-summary-metric">
            <span>Distribuído</span>
            <strong>${fmtQty(distQty, unit)}</strong>
        </div>

        <div class="dist-summary-metric">
            <span>${over ? 'Excedente' : 'Saldo'}</span>
            <strong>${fmtQty(over ? distQty - totalQty : remaining, unit)}</strong>
        </div>

        <div class="dist-summary-progress">
            <div class="dist-summary-progress-track">
                <span
                    class="dist-summary-progress-fill${progressClass}"
                    style="width:${widthPercent}%"
                ></span>
            </div>
            <strong>${percent}%</strong>
        </div>
    `;

    $('dist-summary-list').innerHTML = distributions.length
        ? distributions.map((d, index) => {
            const customer =
                d.customer
                || d.customer_name
                || d.customerName
                || 'Cliente';

            const qty = parseFloat(d.qty || d.quantity || 0);
            const net = parseFloat(d.net || d.net_value || 0);

            return `
                <div class="dist-summary-row">
                    <span class="dist-summary-row-icon" aria-hidden="true">
                        <i class="ph-duotone ph-buildings"></i>
                    </span>

                    <div class="dist-summary-row-main">
                        <strong>${escHtml(customer)}</strong>
                        <small>Destino ${index + 1}</small>
                    </div>

                    <div class="dist-summary-row-values">
                        <strong>${fmtQty(qty, unit)}</strong>
                        ${net > 0
                            ? `<small>R$ ${net.toFixed(2).replace('.', ',')}</small>`
                            : '<small>Sem valor líquido</small>'}
                    </div>
                </div>
            `;
        }).join('')
        : `
            <div class="dist-summary-row">
                <span class="dist-summary-row-icon" aria-hidden="true">
                    <i class="ph-duotone ph-git-merge"></i>
                </span>
                <div class="dist-summary-row-main">
                    <strong>Nenhuma distribuição</strong>
                    <small>Esta entrega ainda não foi rateada.</small>
                </div>
                <div class="dist-summary-row-values">
                    <strong>0%</strong>
                </div>
            </div>
        `;

    registerSheet(
        'dist-summary',
        () => {
            $('dist-summary-overlay')?.classList.add('open');
            $('dist-summary-overlay')?.setAttribute('aria-hidden', 'false');
        },
        () => {
            $('dist-summary-overlay')?.classList.remove('open');
            $('dist-summary-overlay')?.setAttribute('aria-hidden', 'true');
        }
    );

    $('dist-summary-overlay')?.classList.add('open');
    $('dist-summary-overlay')?.setAttribute('aria-hidden', 'false');

    pushRegisterSheetState('dist-summary');
    upgradeRegisterIcons(document);
}

function openDistSummaryFromCard(card) {
    openDistSummaryFromElement(card);
}

function closeDistSummary() {
    requestRegisterSheetClose(
        'dist-summary',
        () => {
            $('dist-summary-overlay')?.classList.remove('open');
            $('dist-summary-overlay')?.setAttribute('aria-hidden', 'true');
        }
    );
}
async function deleteItem(id, btn, isApproved = false) {
    const msg = isApproved
        ? 'Excluir esta entrega aprovada? As distribuições associadas também serão removidas.'
        : 'Excluir este registro?';
    const confirmed = await registerConfirm({
        title: isApproved ? 'Excluir entrega aprovada' : 'Excluir entrega',
        message: msg,
        confirmLabel: 'Excluir',
        tone: 'danger',
        icon: 'trash',
    });

    if (!confirmed) return;
    btn.disabled = true;

    try {
        const res  = await fetch(ROUTES.del(id), {
            method : 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success || res.status === 200) {
            S.items = S.items.filter(i => i.id !== id);
            if (S.project) await loadProjectDeliveries(S.project.id, true);
            else renderSessionItems();
            toast('Registro excluído.', 'info');
        } else {
            toast(data.message || 'Não foi possível excluir.', 'error');
            btn.disabled = false;
        }
    } catch (e) {
        toast('Erro: ' + e.message, 'error');
        btn.disabled = false;
    }
}

/* ─── Action delegation ────────────────────────── */
document.addEventListener('click', function (e) {
    const cardSummary = e.target.closest('.mc-dist-indicator[data-summary]');
    if (cardSummary && cardSummary.closest('#session-list')) {
        openDistSummaryFromElement(cardSummary.closest('.mobile-card'));
        return;
    }

    const tableSummary = e.target.closest('.reg-table-dist[data-summary]');
    if (tableSummary && tableSummary.closest('#session-table-wrap')) {
        openDistSummaryFromElement(tableSummary.closest('tr'));
        return;
    }

    const integrityBtn = e.target.closest('[data-integrity-action]');
    if (integrityBtn) {
        handleRegisterIntegrityAction(integrityBtn);
        return;
    }

    const btn = e.target.closest('[data-action]');
    if (!btn || (!btn.closest('#session-list') && !btn.closest('#session-table-wrap'))) return;
    if (btn.disabled) return;
    const id     = parseInt(btn.dataset.id);
    const action = btn.dataset.action;
    if (action === 'approve')         approveItem(id, btn);
    else if (action === 'edit')       openEditModal(id);
    else if (action === 'distribute') openDistributeModal(id);
    else if (action === 'delete')          deleteItem(id, btn);
    else if (action === 'delete-approved') deleteItem(id, btn, true);
    else if (action === 'reject')     rejectItem(id, btn);
});

/* ─── Rejeitar ─────────────────────────────────── */
async function rejectItem(id, btn) {
    const confirmed = await registerConfirm({
        title: 'Rejeitar entrega',
        message: 'Deseja rejeitar esta entrega? Ela continuará registrada no histórico.',
        confirmLabel: 'Rejeitar',
        tone: 'danger',
        icon: 'x-circle',
    });

    if (!confirmed) return;
    btn.disabled = true;
    try {
        const res  = await fetch('/' + TENANT + '/delivery/deliveries/' + id + '/reject', {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success) {
            const item = S.items.find(i => i.id === id);
            if (item) item.status = 'rejected';
            if (S.project) await loadProjectDeliveries(S.project.id, true);
            else renderSessionItems();
            toast('Entrega rejeitada.', 'info');
        } else {
            toast(data.message || 'Erro ao rejeitar.', 'error');
            btn.disabled = false;
        }
    } catch (e) {
        toast('Erro: ' + e.message, 'error');
        btn.disabled = false;
    }
}

/* ─── Approve ────────────────────────────────────── */
async function approveItem(id, btn) {
    const confirmed = await registerConfirm({
        title: 'Aprovar entrega',
        message: 'Confirma a aprovação desta entrega?',
        confirmLabel: 'Aprovar',
        tone: 'success',
        icon: 'check-circle',
    });

    if (!confirmed) return;
    btn.disabled = true;
    try {
        const res  = await fetch('/' + TENANT + '/delivery/deliveries/' + id + '/approve', {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success) {
            const item = S.items.find(i => i.id === id);
            if (item) item.status = 'approved';
            if (S.project) await loadProjectDeliveries(S.project.id, true);
            else renderSessionItems();
            toast('Entrega aprovada!', 'success');
        } else {
            toast(data.message || 'Erro ao aprovar.', 'error');
            btn.disabled = false;
        }
    } catch (e) {
        toast('Erro: ' + e.message, 'error');
        btn.disabled = false;
    }
}

/* ─── Edit modal ─────────────────────────────────── */
let editingId = null;

function openEditModal(id) {
    const item = S.items.find(i => i.id === id);
    if (!item) return;
    editingId = id;
    $('edit-qty').value  = item.qty;
    $('edit-date').value = item.date;
    $('edit-unit-lbl').textContent = '(' + (item.productUnit || 'un') + ')';
    document.querySelectorAll('#edit-quality-pills .q-pill').forEach(b => {
        b.classList.toggle('active', b.dataset.q === (item.quality || 'A'));
    });
    openModal('edit');
}

async function saveEdit() {
    const item = S.items.find(i => i.id === editingId);
    if (!editingId || !item) return;
    const qty  = parseFloat($('edit-qty').value || 0);
    const date = $('edit-date').value;
    const qual = document.querySelector('#edit-quality-pills .q-pill.active')?.dataset.q || 'A';
    if (qty <= 0) { toast('Quantidade inválida.', 'error'); return; }
    const saveBtn = $('edit-save-btn');
    saveBtn.disabled = true;
    try {
        const res  = await fetch('/' + TENANT + '/delivery/deliveries/' + editingId, {
            method : 'PUT',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body   : JSON.stringify({ quantity: qty, delivery_date: date, quality_grade: qual }),
        });
        const data = await res.json();
        if (data.success) {
            item.qty     = qty;
            item.date    = date;
            item.quality = qual;
            if (S.project) await loadProjectDeliveries(S.project.id, true);
            else renderSessionItems();
            closeModal('edit');
            toast('Entrega atualizada.', 'success');
        } else {
            toast(data.message || 'Erro ao editar.', 'error');
        }
    } catch (e) {
        toast('Erro: ' + e.message, 'error');
    } finally {
        saveBtn.disabled = false;
    }
}

/* ─── Distribute modal ───────────────────────────── */
let distRegId = null;

let registerNativeDistModalOpen = null;
let registerNativeDistModalClose = null;
let registerLastDistModalConfig = null;

function openDistributionDirect(config, fromHistory = false) {
    if (!registerNativeDistModalOpen || !config) return;

    registerLastDistModalConfig = config;

    const body = document.querySelector('#dm-overlay .dm-body');

    /*
     * O componente legado tenta focar um campo ao abrir.
     * Em mobile deixamos o corpo inert durante essa janela e removemos
     * imediatamente depois; assim o sheet abre sem teclado automático.
     */
    if (isCompactDevice()) {
        body?.setAttribute('inert', '');
    }

    const result = registerNativeDistModalOpen(config);

    if (isCompactDevice()) {
        window.setTimeout(() => {
            body?.removeAttribute('inert');

            const active = document.activeElement;
            const overlay = document.getElementById('dm-overlay');

            if (active && overlay?.contains(active)) {
                active.blur?.();
            }
        }, 150);
    }

    return result;
}

function installDistModalHistoryBridge() {
    if (!window.DistModal?.open || window.__registerDistHistoryInstalled) return;

    window.__registerDistHistoryInstalled = true;

    registerNativeDistModalOpen = window.DistModal.open.bind(window.DistModal);
    registerNativeDistModalClose = window.DistModal.close?.bind(window.DistModal);

    window.DistModal.open = function(config, fromHistory = false) {
        const result = openDistributionDirect(config, fromHistory);

        registerSheet(
            'distribution',
            () => {
                if (registerLastDistModalConfig) {
                    openDistributionDirect(registerLastDistModalConfig, true);
                }
            },
            () => registerNativeDistModalClose?.()
        );

        if (!fromHistory) {
            pushRegisterSheetState('distribution');
        }

        return result;
    };

    if (registerNativeDistModalClose) {
        window.DistModal.close = function() {
            requestRegisterSheetClose(
                'distribution',
                () => registerNativeDistModalClose?.()
            );
        };
    }
}

installDistModalHistoryBridge();

function openDistributeModal(id) {
    const item = S.items.find(i => i.id === id);
    if (!item) return;
    if (item.status !== 'approved') { toast('Aprove a entrega antes de distribuir.', 'info'); return; }
    distRegId = id;

    DistModal.open({
        id:          id,
        product:     item.productName,
        unit:        item.productUnit || 'un',
        qty:         item.qty,
        distributed: item.distributedQty || 0,
        existing:    (item.distributions || []).map(d => ({
            id: d.id || 0,
            customer_id: d.customer_id || d.customerId || null,
            customer: d.customer,
            qty: d.qty,
            net: d.net || 0,
            billed: !!d.billed,
            paid: !!d.paid,
            in_receipt: !!d.in_receipt,
            receipt_id: d.receipt_id || null,
            receipt_number: d.receipt_number || null,
            billing_receipt_id: d.billing_receipt_id || null,
            locked: !!d.locked,
            billing_status: d.billing_status || null,
        })),
        participants: item.customerIds || S.project?.customerIds || [],
        defaultCustomerId: item.defaultCustomerId || S.project?.defaultCustomerId || null,
        notes: item.notes || '',
        context: (item.projectId || S.project?.id || 0) + ':' + (item.associateId || S.associate?.id || 0),
    });
}

window._DistModalReload = function(data) {
    if (!distRegId || !S.project) { location.reload(); return; }
    distRegId = null;
    loadProjectDeliveries(S.project.id).then(() => {
        toast('Distribuição salva!', 'success');
    }).catch(() => {
        toast('Distribuição salva!', 'success');
    });
};

/* ─── Modals ─────────────────────────────────────── */
window._DistModalOnDelete = function(receptionId, data) {
    const id = receptionId || data?.parent_delivery_id;
    const item = S.items.find(i => i.id === id);
    if (!item) return;

    item.distributedQty = data.dist_total_qty || 0;
    item.dist_net_value = data.dist_total_net || 0;
    item.distributions = (item.distributions || []).filter(d => String(d.id) !== String(data.deleted_id));

    renderSessionItems();
    toast('Distribuicao removida.', 'success');
};

window._DistModalOnUpdate = function(receptionId, data) {
    const id = receptionId || data?.parent_delivery_id;
    const item = S.items.find(i => i.id === id);
    if (!item) return;

    item.distributedQty = data.dist_total_qty || 0;
    item.dist_net_value = data.dist_total_net || 0;
    if (data.distribution) {
        item.distributions = (item.distributions || []).map(d =>
            String(d.id) === String(data.distribution.id) ? data.distribution : d
        );
    }

    renderSessionItems();
    toast('Distribuicao atualizada.', 'success');
};

function openModalDirect(type, fromHistory = false) {
    const overlay = $('modal-' + type);
    if (!overlay) return;

    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
    syncRegisterSheetEnvironment();

    const search = $('search-' + type);

    if (search && !fromHistory) {
        search.value = '';

        /*
         * Em mobile não forçamos foco/teclado ao abrir sheets.
         * Desktop mantém a agilidade do teclado.
         */
        if (!isCompactDevice()) {
            window.setTimeout(() => search.focus({ preventScroll: true }), 50);
        }
    }

    if (['project', 'assoc', 'product'].includes(type)) {
        renderModalList(type);
        resetModalHighlight(type);
    }
}

function openModal(type) {
    if (type === 'product' && !S.project) {
        toast('Selecione um projeto primeiro.', 'info');
        return;
    }

    if (type === 'product' && S.demands.length === 0 && S.loadingProjectId) {
        toast('Aguarde, carregando produtos…', 'info');
        return;
    }

    const key = 'modal:' + type;

    registerSheet(
        key,
        () => openModalDirect(type, true),
        () => {
            $('modal-' + type)?.classList.remove('open');
            $('modal-' + type)?.setAttribute('aria-hidden', 'true');
            syncRegisterSheetEnvironment();
        }
    );

    openModalDirect(type, false);
    pushRegisterSheetState(key);
}

function closeModal(type) {
    const key = 'modal:' + type;

    requestRegisterSheetClose(
        key,
        () => {
            $('modal-' + type)?.classList.remove('open');
            $('modal-' + type)?.setAttribute('aria-hidden', 'true');
            syncRegisterSheetEnvironment();
        }
    );
}

function filterList(type) {
    renderModalList(type);
    // Sempre reposiciona o destaque para o primeiro item ao filtrar
    resetModalHighlight(type);
}

function renderModalList(type) {
    const list   = $('list-' + type);
    const search = normalizeSearch($('search-' + type)?.value || '');

    if (type === 'project') {
        renderProjectList(list, search);
    } else if (type === 'assoc') {
        renderAssocList(list, search);
    } else if (type === 'product') {
        renderProductList(list, search);
    }

    upgradeRegisterIcons(document);
}

function renderProjectList(list, search) {
    const items = ALL_PROJECTS.filter(p =>
        !search ||
        normalizeSearch(p.title).includes(search) ||
        normalizeSearch(p.customer_name || '').includes(search)
    );
    if (items.length === 0) {
        list.innerHTML = '<div class="modal-empty">Nenhum projeto encontrado</div>';
        return;
    }
    list.innerHTML = items.map((p, i) =>
        '<div class="modal-item' + (S.project?.id === p.id ? ' highlighted' : '') + '" data-idx="' + i + '">' +
            '<div class="mi-avatar project">' + initials(p.title) + '</div>' +
            '<div class="mi-info">' +
                '<div class="mi-name">' + escHtml(p.title) + '</div>' +
                '<div class="mi-sub">' + escHtml(p.customer_name) + '</div>' +
            '</div>' +
        '</div>'
    ).join('');
    list.querySelectorAll('.modal-item').forEach(el => {
        el.addEventListener('click', () => {
            const idx = parseInt(el.dataset.idx);
            selectProject(items[idx]);
        });
    });
}

function renderAssocList(list, search) {
    const items = ALL_ASSOCIATES.filter(a =>
        !search ||
        normalizeSearch(a.name).includes(search) ||
        normalizeSearch(a.nickname || '').includes(search) ||
        normalizeSearch(a.registration_number || '').includes(search)
    );
    if (items.length === 0) {
        list.innerHTML = '<div class="modal-empty">Nenhum associado encontrado</div>';
        return;
    }
    list.innerHTML = items.map((a, i) =>
        '<div class="modal-item' + (S.associate?.id === a.id ? ' highlighted' : '') + '" data-idx="' + i + '">' +
            '<div class="mi-avatar">' + initials(a.nickname || a.name) + '</div>' +
            '<div class="mi-info">' +
                '<div class="mi-name">' + escHtml(a.nickname || a.name) + (a.nickname ? ' <span style="font-size:.75rem;font-weight:400;color:var(--color-text-muted)">' + escHtml(a.name) + '</span>' : '') + '</div>' +
                '<div class="mi-sub">' + (a.registration_number ? 'Reg: ' + escHtml(a.registration_number) : '') + '</div>' +
            '</div>' +
        '</div>'
    ).join('');
    list.querySelectorAll('.modal-item').forEach(el => {
        el.addEventListener('click', () => {
            const idx = parseInt(el.dataset.idx);
            selectAssociate(items[idx]);
        });
    });
}

function renderProductList(list, search) {
    if ($('add-product-limit-btn')) {
        $('add-product-limit-btn').disabled = !S.associate;
        $('add-product-limit-btn').title = S.associate
            ? 'Adicionar produto e definir cota'
            : 'Selecione um associado primeiro';
    }
    if (!S.project) {
        list.innerHTML = '<div class="modal-empty">Selecione um projeto primeiro</div>';
        return;
    }
    if (S.loadingProjectId) {
        list.innerHTML = '<div class="modal-empty">Carregando produtos…</div>';
        return;
    }
    const items = S.demands.filter(d =>
        !search ||
        normalizeSearch(d.product_name).includes(search)
    );
    if (items.length === 0) {
        list.innerHTML = '<div class="modal-empty">' + (search
            ? 'Nenhum produto encontrado para esta busca'
            : 'Nenhum produto com preco disponivel para os clientes deste projeto') + '</div>';
        return;
    }
    list.innerHTML = items.map((d, i) => {
        const hasLimit   = d.remaining_quantity !== null;
        const delivered  = d.associate_delivered ?? d.delivered_quantity ?? 0;
        const remaining  = hasLimit ? Math.max(0, d.remaining_quantity) : null;
        const completed  = hasLimit && remaining <= 0;
        const baseLimit  = hasLimit ? delivered + remaining : null;
        const percent    = baseLimit > 0 ? Math.min(100, Math.round((delivered / baseLimit) * 100)) : (completed ? 100 : 0);
        const badgeClass = completed ? 'red' : (percent >= 80 ? 'amber' : 'green');
        const badgeText  = hasLimit ? (completed ? 'Limite atingido' : 'Saldo: ' + fmtQty(remaining, d.product_unit)) : 'Sem limite';

        return '<div class="modal-item product-limit-item' + (S.product?.product_id === d.product_id ? ' highlighted' : '') + (completed ? ' disabled' : '') + '" data-idx="' + i + '" data-disabled="' + (completed ? '1' : '0') + '">' +
            '<div class="mi-avatar product">' + initials(d.product_name) + '</div>' +
            '<div class="mi-info">' +
                '<div class="mi-name">' + escHtml(d.product_name) + '</div>' +
                (hasLimit ? '<div class="mi-limit-summary"><span>Limite efetivo <strong>' + fmtQty(baseLimit, d.product_unit) + '</strong></span><span>Entregue <strong>' + fmtQty(delivered, d.product_unit) + '</strong></span><span>Disponivel <strong>' + fmtQty(remaining, d.product_unit) + '</strong></span></div>' : '<div class="mi-sub">Produto sem limite de quantidade</div>') +
                (hasLimit ? '<div class="mi-limit-track" role="progressbar" aria-label="Quantidade entregue de ' + escHtml(d.product_name) + '" aria-valuemin="0" aria-valuemax="' + baseLimit + '" aria-valuenow="' + delivered + '"><span class="mi-limit-used" style="width:' + percent + '%" title="Entregue: ' + fmtQty(delivered, d.product_unit) + '"></span><span class="mi-limit-free" style="width:' + Math.max(0, 100 - percent) + '%" title="Disponivel: ' + fmtQty(remaining, d.product_unit) + '"></span></div>' : '') +
            '</div>' +
            '<button class="mi-quota-edit" type="button" data-edit-quota="' + Number(d.product_id) + '" title="' + (d.associate_limit != null ? 'Editar limite do associado' : 'Definir limite do associado') + '" aria-label="' + (d.associate_limit != null ? 'Editar limite do associado' : 'Definir limite do associado') + '">' +
                '<i data-lucide="' + (d.associate_limit != null ? 'pencil' : 'plus') + '"></i>' +
            '</button>' +
            '<span class="mi-badge ' + badgeClass + '">' + badgeText + '</span>' +
        '</div>';
    }).join('');
    list.querySelectorAll('[data-edit-quota]').forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            openQuickQuota(Number(button.dataset.editQuota));
        });
    });
    list.querySelectorAll('.modal-item').forEach(el => {
        el.addEventListener('click', event => {
            if (event.target.closest('[data-edit-quota]')) return;
            if (el.dataset.disabled === '1') return;
            const idx = parseInt(el.dataset.idx);
            selectProduct(items[idx]);
            if (!isCompactDevice()) {
                setTimeout(() => document.getElementById('f-qty')?.focus({ preventScroll: true }), 300);
            }
        });
    });
}

function selectProject(proj) {
    applyProject(proj);
    closeModal('project');
    loadDemands(proj.id, S.associate?.id);
}

/* ─── Keyboard ─────────────────────────────────── */
function hasOpenModal() {
    return !!document.querySelector('.modal-overlay.open');
}

function isTypingField(el) {
    if (!el) return false;
    const tag = (el.tagName || '').toUpperCase();
    return el.isContentEditable || tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
}

function isInteractiveControl(el) {
    if (!el) return false;
    const tag = (el.tagName || '').toUpperCase();
    if (tag === 'BUTTON' || tag === 'A') return true;
    return !!(el.closest && el.closest('[role="button"]'));
}

function nextRegisterStep() {
    if (!S.associate) {
        openModal('assoc');
        return;
    }
    if (!S.dateConfirmed) {
        focusDateInput();
        return;
    }
    if (!S.product) {
        if (!S.project) {
            openModal('project');
            return;
        }
        openModal('product');
        return;
    }

    const qty = $('f-qty');
    if (qty) qty.focus();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (closeTopRegisterSheet()) {
            e.preventDefault();
            return;
        }

        ['project', 'assoc', 'product', 'edit'].forEach(t => {
            $('modal-' + t)?.classList.remove('open');
        });

        return;
    }

    if (hasOpenModal() || $('dist-summary-overlay')?.classList.contains('open')) return;

    if (e.key === 'Enter') {
        if (isInteractiveControl(e.target)) return;
        e.preventDefault();
        submitEntry();
        return;
    }

    if (e.code === 'Space' || e.key === ' ' || e.key === 'Spacebar') {
        if (isTypingField(e.target) || isInteractiveControl(e.target)) return;
        e.preventDefault();
        nextRegisterStep();
    }
});

/* ─── Toast ──────────────────────────────────────── */
function toast(msg, type = 'info') {
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = msg;
    $('toast-root').appendChild(el);
    setTimeout(() => el.remove(), 3200);
}

/* ─── Helpers ────────────────────────────────────── */
function initials(name) {
    return (name || '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function normalizeSearch(value) {
    return (value || '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function bindScrollTopButton() {
    const btn = $('scroll-top-btn');
    if (!btn) return;

    const toggle = () => {
        btn.classList.toggle('visible', window.scrollY > 360);
    };

    toggle();
    window.addEventListener('scroll', toggle, { passive: true });
}

function scrollToRegisterTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ─── Boot ───────────────────────────────────────── */
init();
checkFormReady();
syncEntryNotesUi();
upgradeRegisterIcons(document);

/*
 * Conteúdo de histórico e modais é refeito via innerHTML em vários fluxos.
 * O observer garante que todos os novos ícones recebam o mesmo Duotone.
 */
const registerIconObserver = new MutationObserver(mutations => {
    for (const mutation of mutations) {
        mutation.addedNodes.forEach(node => {
            if (!(node instanceof Element)) return;

            if (node.matches?.('[data-lucide]')) {
                upgradeRegisterIcons(node.parentElement || document);
            } else if (node.querySelector?.('[data-lucide]')) {
                upgradeRegisterIcons(node);
            }
        });
    }
});

if (document.body) {
    registerIconObserver.observe(document.body, {
        childList: true,
        subtree: true,
    });
}

$('f-notes')?.addEventListener('input', syncEntryNotesUi);

window.openModal            = openModal;
window.closeModal           = closeModal;
window.filterList           = filterList;
window.submitEntry          = submitEntry;
window.toggleEntryNotes      = toggleEntryNotes;
window.deleteItem           = deleteItem;
window.saveEdit             = saveEdit;
window.focusDateInput       = focusDateInput;
window.openCalendarSheet     = openCalendarSheet;
window.closeCalendarSheet    = closeCalendarSheet;
window.calendarChangeMonth   = calendarChangeMonth;
window.calendarSelectDate    = calendarSelectDate;
window.calendarUseToday      = calendarUseToday;
window.applyManualCalendarDate = applyManualCalendarDate;
window.setHistoryView        = setHistoryView;
window.renderSessionItems   = renderSessionItems;
window.clearFilter          = clearFilter;
window.toggleHistoryFilters = toggleHistoryFilters;
window.onDateChange         = onDateChange;
window.scrollToRegisterTop  = scrollToRegisterTop;
window.setSessionPageSize   = setSessionPageSize;
window.changeSessionPage    = changeSessionPage;
window.closeDistSummary     = closeDistSummary;
window.toggleRegisterIntegrity = toggleRegisterIntegrity;
window.refreshProductList   = refreshProductList;
window.openQuickQuota       = openQuickQuota;
window.closeQuickQuota      = closeQuickQuota;
window.saveQuickQuota       = saveQuickQuota;
window.deleteQuickQuota     = deleteQuickQuota;
window.addDistRegRow        = function() {};
window.saveDist             = function() {};

})();
</script>
@endsection
