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
        top: 1rem;
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
        <div class="card-header">Nova Entrega</div>
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
            <div class="selector-row" id="sel-date" onclick="focusDateInput()">
                <div class="sel-icon">
                    <i data-lucide="calendar" style="width:16px;height:16px"></i>
                </div>
                <div class="sel-info">
                    <div class="sel-label">Data da entrega</div>
                    <div class="sel-date-display" id="date-display">{{ date('d/m/Y') }}</div>
                </div>
                <input type="date" id="f-date" value="{{ date('Y-m-d') }}"
                    style="position:absolute;opacity:0;pointer-events:none;width:0;height:0"
                    onchange="onDateChange(this.value)">
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
                    <div>
                        <label class="field-label" for="f-notes">Observações</label>
                        <input class="field-input" type="text" id="f-notes" placeholder="Opcional">
                    </div>
                </div>

                <button class="btn-submit" id="btn-submit" disabled onclick="submitEntry()">
                    Registrar Entrega
                </button>
            </div>

        </div>
    </div>

    {{-- ─── SESSION LIST ─────────────────────────────── --}}
    <div class="card history-card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding-right:1rem;padding-bottom:.6rem;">
            <span id="session-list-title">Registros desta sessão</span>
            <span id="session-count" style="font-size:0.8rem;font-weight:600;color:var(--color-primary);text-transform:none;letter-spacing:0"></span>
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
            <button class="hf-clear" onclick="clearFilter()">Limpar</button>
        </div>
        <div id="session-list">
            <div class="session-empty" id="session-empty">Selecione um projeto para ver o histórico de entregas</div>
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
                <button type="button" class="delivery-page-btn" id="session-prev" onclick="changeSessionPage(-1)">Anterior</button>
                <button type="button" class="delivery-page-btn" id="session-next" onclick="changeSessionPage(1)">Proxima</button>
            </div>
        </div>
    </div>

</div>

{{-- ─────────────────── MODALS ──────────────────── --}}

<button type="button" class="scroll-top-btn" id="scroll-top-btn" onclick="scrollToRegisterTop()" aria-label="Voltar ao topo">
    <i data-lucide="arrow-up" style="width:18px;height:18px"></i>
</button>

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

{{-- Project modal --}}
<div class="modal-overlay" id="modal-project" onclick="closeModalOnBackdrop(event, 'project')">
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
<div class="modal-overlay" id="modal-assoc" onclick="closeModalOnBackdrop(event, 'assoc')">
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
<div class="modal-overlay" id="modal-product" onclick="closeModalOnBackdrop(event, 'product')">
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
        <div class="modal-list" id="list-product">
            <div class="modal-empty">Selecione um projeto primeiro</div>
        </div>
    </div>
</div>

{{-- Toast root --}}
<div id="toast-root"></div>

{{-- ─────────────── MODAL EDITAR ENTREGA (mantido) ──────────────── --}}
<div class="modal-overlay" id="modal-edit" onclick="closeModalOnBackdrop(event, 'edit')">
    <div class="modal-box" style="max-width:400px">
        <div class="modal-header">
            <span class="modal-title">Editar Entrega</span>
            <button class="modal-close" onclick="closeModal('edit')" aria-label="Fechar">
                <i data-lucide="x" style="width:16px;height:16px"></i>
            </button>
        </div>
        <div style="padding:1rem;display:flex;flex-direction:column;gap:.75rem">
            <div>
                <label class="field-label">Quantidade <span id="edit-unit-lbl" style="font-weight:400;color:var(--color-text-muted)"></span></label>
                <input class="field-input" type="number" id="edit-qty" min="0.001" step="0.001" style="margin-top:.35rem">
            </div>
            <div>
                <label class="field-label">Data da entrega</label>
                <input class="field-input" type="date" id="edit-date" style="margin-top:.35rem">
            </div>
            <div>
                <label class="field-label">Qualidade</label>
                <div class="quality-group" id="edit-quality-pills" style="margin-top:.35rem">
                    <button type="button" class="q-pill active" data-q="A">A</button>
                    <button type="button" class="q-pill" data-q="B">B</button>
                    <button type="button" class="q-pill" data-q="C">C</button>
                </div>
            </div>
            <button type="button" class="btn-submit" id="edit-save-btn" onclick="saveEdit()" style="margin-top:.25rem">Salvar</button>
        </div>
    </div>
</div>

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
    loadingDeliveries : false,
    dateConfirmed     : false,
    keyboardStage     : 'project',
    listPage          : 1,
    listPerPage       : 30,
};

/* ─── DOM refs ───────────────────────────────────── */
const $ = (id) => document.getElementById(id);

/* ─── Highlight state for modals ─────────────────── */
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

async function loadProjectDeliveries(projectId) {
    if (S.loadingDeliveries) return;
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
        }));
    } catch (e) {
        toast('Erro ao carregar histórico: ' + e.message, 'error');
        S.items = [];
    } finally {
        S.loadingDeliveries = false;
        renderSessionItems();
        loadRegisterIntegrity(projectId);
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
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function integrityActionLabel(key) {
    return ({
        open_distribution: 'Gerenciar distribuicoes',
        edit_distribution: 'Corrigir distribuicao',
        detach_missing_associate_receipt: 'Desvincular comprovante',
        delete_orphan_distribution: 'Excluir distribuicao orfa',
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
            ? `<button type="button" class="btn-edit btn-xs" data-integrity-action="${escAttr(issue.actionKey)}" data-delivery-id="${Number(issue.deliveryId || 0)}" data-distribution-id="${Number(issue.distributionId || 0)}">${escHtml(integrityActionLabel(issue.actionKey))}</button>`
            : '';
        return `<div class="reg-integrity-item ${issue.severity}" data-integrity-item="${escAttr(issue.actionKey || '')}-${Number(issue.distributionId || 0)}">
            <div class="reg-integrity-title">${escHtml(issue.title || '')}</div>
            <div class="reg-integrity-message">${escHtml(issue.message || '')}</div>
            <div class="reg-integrity-message" style="font-weight:700">${escHtml(issue.action || '')}</div>
            ${action ? `<div class="reg-integrity-actions">${action}</div>` : ''}
        </div>`;
    }).join('') || '<div class="reg-integrity-message">Nenhuma pendencia encontrada.</div>';
    if (typeof lucide !== 'undefined') lucide.createIcons();
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

    if (action === 'open_distribution' || action === 'edit_distribution') {
        openDistributeModal(deliveryId);
        if (action === 'edit_distribution' && distributionId) {
            setTimeout(() => DistModal.editExisting(distributionId), 120);
        }
        return;
    }
    if (action === 'open_producers') {
        window.location.href = '/' + TENANT + '/delivery/projects/' + S.project.id + '/producers';
        return;
    }

    const question = action === 'detach_missing_associate_receipt'
        ? 'Desvincular este comprovante inexistente? A distribuicao voltara a ficar disponivel.'
        : 'Excluir esta distribuicao orfa? Esta correcao nao pode ser desfeita.';
    if (!confirm(question)) return;
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
    if (S.loadingProjectId === projectId) return;
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
        S.demands = await res.json();
    } catch (e) {
        toast('Erro ao carregar produtos: ' + e.message, 'error');
    } finally {
        S.loadingProjectId = null;
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
    const inp = $('f-date');
    try { inp.showPicker(); } catch(e) { inp.focus(); inp.click(); }
}

function clearFilter() {
    ['filter-history-search', 'filter-status', 'filter-associate', 'filter-product', 'filter-date-from', 'filter-date-to'].forEach(id => {
        const el = $(id);
        if (el) el.value = '';
    });
    renderSessionItems();
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
                status       : 'pending',
                distributedQty: 0,
                distributions : [],
                has_billed   : false,
                dist_net_value: 0,
                customerIds   : S.project?.customerIds || [],
                limit: {
                    associate_limit: S.product.associate_limit ?? null,
                    associate_delivered: (S.product.associate_delivered || 0) + qty,
                    associate_remaining: S.product.associate_limit == null ? null : Math.max(0, S.product.associate_limit - (S.product.associate_delivered || 0) - qty),
                    associate_percent: S.product.associate_limit > 0 ? Math.min(100, (((S.product.associate_delivered || 0) + qty) / S.product.associate_limit) * 100) : null,
                    project_remaining: S.product.project_limit == null ? null : Math.max(0, S.product.project_remaining - qty),
                },
            });
            S.product = null;
            resetProductSelector();
            $('f-qty').value  = '';
            $('f-notes').value = '';
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
        updateSessionPagination(0, 0, 0, 0, 1);
        return;
    }

    if (empty) empty.style.display = 'none';
    count.textContent = renderList.length + (usingFilter ? '/' + filtered.length : '') + ' registro' + (renderList.length !== 1 ? 's' : '');
    updateSessionPagination(renderList.length, pageStart + 1, Math.min(pageStart + perPage, renderList.length), S.listPage, totalPages);

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

        const actionsHtml = (() => {
            let btns = '';
            if (isPending) {
                btns += `<button class="btn-approve btn-xs" data-action="approve" data-id="${item.id}">Aprovar</button>`;
                btns += `<button class="btn-reject btn-xs" data-action="reject" data-id="${item.id}">Rejeitar</button>`;
                btns += `<button class="btn-edit btn-xs" data-action="edit" data-id="${item.id}">Editar</button>`;
            } else if (isApproved) {
                btns += `<button class="btn-distribute btn-xs" data-action="distribute" data-id="${item.id}">Distribuir</button>`;
                btns += `<button class="btn-edit btn-xs" data-action="edit" data-id="${item.id}">Editar</button>`;
                if (!isBilled) {
                    btns += `<button class="btn-delete-approved btn-xs" data-action="delete-approved" data-id="${item.id}">Excluir</button>`;
                }
            } else if (isRejected) {
                btns += `<button class="btn-delete-approved btn-xs" data-action="delete" data-id="${item.id}">Excluir</button>`;
            }
            return btns;
        })();

        return `
        <div class="mobile-card status-${visualClass} variant-c" id="row-${item.id}" data-total-qty="${totalQty}" data-unit="${escAttr(item.productUnit || '')}" data-product="${escAttr(item.productName)}" data-distributions="${distJson}" data-distributed="${distQty}">
            <div class="mc-head">
                <div class="mc-head-main">
                    <span class="mc-date">${dateStr}</span>
                    <span class="mc-sep" aria-hidden="true">-</span>
                    <div class="mc-head-product" title="${escAttr(item.productName)}">${escHtml(item.productName)}</div>
                    <span class="mc-sep" aria-hidden="true">-</span>
                    <span class="mc-head-qty">${fmtQty(totalQty, item.productUnit)}</span>
                </div>
                <span class="mc-state-icon" title="${escAttr(stateLabel)}" aria-label="${escAttr(stateLabel)}">${stateIcon}</span>
                ${statusTag}
                ${billedTag}
            </div>
            <div class="mc-body">
                <div class="mc-info-grid">
                    <div class="mc-associate" title="${escAttr(item.associateName)}">${escHtml(item.associateName)}</div>
                    <div>${netValue > 0 ? '<span class="mc-net">R$ ' + netValue.toFixed(2) + '</span>' : ''}</div>
                </div>
                ${limitHtml}
                <div class="mc-footer">
                    <span class="mc-footer-label">Distrib.</span>
                    <div class="mc-dist-indicator" role="button" tabindex="0" data-summary="1" title="${overDist ? 'Excede. Total dist.: ' + distQty.toFixed(2) + ' ' + item.productUnit : (distPercent >= 100 ? 'Totalmente distribuido' : 'A distribuir: ' + (totalQty - distQty).toFixed(2) + ' ' + item.productUnit)}">
                        <div class="mc-dist-bar-bg"><div class="mc-dist-bar-fill ${overDist ? 'over' : (distPercent >= 100 ? 'full' : 'partial')}" style="width:${displayPercent}%;height:100%;border-radius:99px;"></div></div>
                        <span class="mc-dist-text">${overDist ? '! ' + distQty.toFixed(1) : distPercent + '%'}</span>
                    </div>
                    <div class="mc-actions">${actionsHtml}</div>
                </div>
            </div>
        </div>`;
    }

    function buildSectionHeader(label) {
        const h = document.createElement('div');
        h.className = 'session-section-header';
        h.textContent = label;
        return h;
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
            list.appendChild(buildSectionHeader('Outros produtores'));
            othersItems.forEach(item => {
                const card = document.createElement('div');
                card.innerHTML = buildCard(item);
                list.appendChild(card.firstElementChild);
            });
        }
    } else {
        pageItems.forEach(item => {
            const card = document.createElement('div');
            card.innerHTML = buildCard(item);
            list.appendChild(card.firstElementChild);
        });
    }

    if (typeof lucide !== 'undefined') lucide.createIcons();
}
/* Helpers */
function fmtQty(n, unit) {
    const num = parseFloat(n) || 0;
    const str = num % 1 === 0 ? num.toString() : num.toFixed(2).replace(/\.?0+$/, '');
    return str + (unit ? '\u00a0' + unit : '');
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
    if (over) return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"></path><path d="M12 17h.01"></path><path d="m10.3 3.9-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3.1l-8-14a2 2 0 0 0-3.4 0Z"></path></svg>';
    if (status === 'approved' && percent >= 100) return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>';
    if (status === 'approved') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18"></path><path d="M5 12h14"></path></svg>';
    if (status === 'pending') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6v6l4 2"></path><circle cx="12" cy="12" r="9"></circle></svg>';
    if (status === 'rejected') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path></svg>';
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
function openDistSummaryFromCard(card) {
    const product = card?.dataset?.product || 'Produto';
    const unit = card?.dataset?.unit || '';
    const totalQty = parseFloat(card?.dataset?.totalQty || 0);
    const distQty = parseFloat(card?.dataset?.distributed || 0);
    const distributions = parseCardDistributions(card);
    $('dist-summary-title').textContent = product;
    $('dist-summary-sub').textContent = `${fmtQty(distQty, unit)} distribuidos de ${fmtQty(totalQty, unit)}`;
    $('dist-summary-body').innerHTML = distributions.length
        ? distributions.map(d => {
            const customer = d.customer || d.customer_name || d.customerName || 'Cliente';
            const qty = parseFloat(d.qty || d.quantity || 0);
            const net = parseFloat(d.net || d.net_value || 0);
            return `<div class="dist-summary-row"><strong>${escHtml(customer)}</strong><span>${fmtQty(qty, unit)}${net > 0 ? ' - R$ ' + net.toFixed(2) : ''}</span></div>`;
        }).join('')
        : '<div class="dist-summary-row"><strong>Nenhuma distribuicao</strong><span>0%</span></div>';
    $('dist-summary-overlay').classList.add('open');
}
function closeDistSummary() {
    $('dist-summary-overlay')?.classList.remove('open');
}
function closeDistSummaryOnBackdrop(event) {
    if (event.target === $('dist-summary-overlay')) closeDistSummary();
}
async function deleteItem(id, btn, isApproved = false) {
    const msg = isApproved
        ? 'Excluir esta entrega aprovada? As distribuições associadas também serão removidas.'
        : 'Excluir este registro?';
    if (!confirm(msg)) return;
    btn.disabled = true;

    try {
        const res  = await fetch(ROUTES.del(id), {
            method : 'DELETE',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success || res.status === 200) {
            S.items = S.items.filter(i => i.id !== id);
            renderSessionItems();
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
    const summary = e.target.closest('.mc-dist-indicator[data-summary]');
    if (summary && summary.closest('#session-list')) {
        openDistSummaryFromCard(summary.closest('.mobile-card'));
        return;
    }

    const integrityBtn = e.target.closest('[data-integrity-action]');
    if (integrityBtn) {
        handleRegisterIntegrityAction(integrityBtn);
        return;
    }

    const btn = e.target.closest('[data-action]');
    if (!btn || !btn.closest('#session-list')) return;
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
    if (!confirm('Rejeitar esta entrega?')) return;
    btn.disabled = true;
    try {
        const res  = await fetch('/' + TENANT + '/delivery/deliveries/' + id + '/reject', {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success) {
            const item = S.items.find(i => i.id === id);
            if (item) { item.status = 'rejected'; renderSessionItems(); }
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
    if (!confirm('Aprovar esta entrega?')) return;
    btn.disabled = true;
    try {
        const res  = await fetch('/' + TENANT + '/delivery/deliveries/' + id + '/approve', {
            method : 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        if (data.success) {
            const item = S.items.find(i => i.id === id);
            if (item) { item.status = 'approved'; renderSessionItems(); }
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
            renderSessionItems();
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

function openModal(type) {
    if (type === 'product' && !S.project) {
        toast('Selecione um projeto primeiro.', 'info');
        return;
    }
    if (type === 'product' && S.demands.length === 0 && S.loadingProjectId) {
        toast('Aguarde, carregando produtos…', 'info');
        return;
    }

    const overlay = $('modal-' + type);
    overlay.classList.add('open');

    const search = $('search-' + type);
    if (search) { search.value = ''; setTimeout(() => search.focus(), 50); }

    renderModalList(type);
    // Destaca o primeiro item após abrir
    resetModalHighlight(type);
}

function closeModal(type) {
    const overlay = $('modal-' + type);
    overlay.classList.remove('open');
}

function closeModalOnBackdrop(event, type) {
    if (event.target === $('modal-' + type)) closeModal(type);
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

    if (typeof lucide !== 'undefined') lucide.createIcons();
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
        list.innerHTML = '<div class="modal-empty">Nenhum produto encontrado</div>';
        return;
    }
    list.innerHTML = items.map((d, i) => {
        const hasLimit   = d.remaining_quantity !== null;
        const delivered  = d.associate_delivered ?? d.delivered_quantity ?? 0;
        const remaining  = hasLimit ? Math.max(0, d.remaining_quantity) : null;
        const completed  = hasLimit && remaining <= 0;
        const baseLimit  = d.associate_limit ?? d.project_limit;
        const percent    = baseLimit > 0 ? Math.min(100, Math.round((delivered / baseLimit) * 100)) : 0;
        const badgeClass = completed ? 'red' : (percent >= 80 ? 'amber' : 'green');
        const badgeText  = hasLimit ? (completed ? 'Limite atingido' : 'Saldo: ' + fmtQty(remaining, d.product_unit)) : 'Sem limite';

        return '<div class="modal-item' + (S.product?.product_id === d.product_id ? ' highlighted' : '') + (completed ? ' disabled' : '') + '" data-idx="' + i + '" data-disabled="' + (completed ? '1' : '0') + '">' +
            '<div class="mi-avatar product">' + initials(d.product_name) + '</div>' +
            '<div class="mi-info">' +
                '<div class="mi-name">' + escHtml(d.product_name) + '</div>' +
                '<div class="mi-sub">Entregue: ' + fmtQty(delivered, d.product_unit) + (baseLimit ? ' de ' + fmtQty(baseLimit, d.product_unit) : '') + '</div>' +
                (baseLimit ? '<div style="height:4px;background:#e5e7eb;border-radius:3px;margin-top:5px;overflow:hidden"><span style="display:block;height:100%;width:' + percent + '%;background:' + (completed?'#dc2626':percent>=80?'#d97706':'#059669') + '"></span></div>' : '') +
            '</div>' +
            '<span class="mi-badge ' + badgeClass + '">' + badgeText + '</span>' +
        '</div>';
    }).join('');
    list.querySelectorAll('.modal-item').forEach(el => {
        el.addEventListener('click', () => {
            if (el.dataset.disabled === '1') return;
            const idx = parseInt(el.dataset.idx);
            selectProduct(items[idx]);
            setTimeout(()=> document.getElementById('f-qty').focus(),300)
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
        ['project', 'assoc', 'product', 'edit', 'dist'].forEach(t => closeModal(t));
        return;
    }

    if (hasOpenModal()) return;

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

window.openModal            = openModal;
window.closeModal           = closeModal;
window.closeModalOnBackdrop = closeModalOnBackdrop;
window.filterList           = filterList;
window.submitEntry          = submitEntry;
window.deleteItem           = deleteItem;
window.saveEdit             = saveEdit;
window.focusDateInput       = focusDateInput;
window.renderSessionItems   = renderSessionItems;
window.clearFilter          = clearFilter;
window.onDateChange         = onDateChange;
window.scrollToRegisterTop  = scrollToRegisterTop;
window.setSessionPageSize   = setSessionPageSize;
window.changeSessionPage    = changeSessionPage;
window.closeDistSummary     = closeDistSummary;
window.closeDistSummaryOnBackdrop = closeDistSummaryOnBackdrop;
window.toggleRegisterIntegrity = toggleRegisterIntegrity;
window.addDistRegRow        = function() {};
window.saveDist             = function() {};

})();
</script>
@endsection

