@extends('layouts.bento')

@section('title', 'Associados do projeto')
@section('page-title', 'Participação e limites')
@section('page-subtitle', $project->title)
@section('user-role', 'Gestão de entregas')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'projects', request()->route('tenant'));
@endphp

@section('content')
@php
    $tenantSlug = request()->route('tenant') instanceof \App\Models\Tenant
        ? request()->route('tenant')->slug
        : request()->route('tenant');

    $projectPeriod = collect([
        $project->start_date?->format('d/m/Y'),
        $project->end_date?->format('d/m/Y'),
    ])->filter()->implode(' a ');
@endphp


<style>
    .pam-shell,
    .pam-modal,
    .pam-toast-root {
        --pam-green:#168a4d; --pam-green-soft:#eaf8ef;
        --pam-blue:#2563eb; --pam-blue-soft:#eef4ff;
        --pam-sky:#0284c7; --pam-sky-soft:#edf8fe;
        --pam-violet:#7c3aed; --pam-violet-soft:#f4f0ff;
        --pam-amber:#c87408; --pam-amber-soft:#fff7e8;
        --pam-red:#cf3f3f; --pam-red-soft:#fff0f0;
        --pam-slate:#64748b; --pam-slate-soft:#f1f5f9;
        --pam-surface:var(--color-surface,#fff);
        --pam-soft:var(--color-surface-soft,#f8faf9);
        --pam-muted:var(--color-surface-muted,#eef4f0);
        --pam-border:var(--color-border,#dce6df);
        --pam-border-strong:var(--color-border-strong,#c8d6cd);
        --pam-text:var(--color-text,#102018);
        --pam-secondary:var(--color-text-secondary,#52645a);
        --pam-faded:var(--color-text-muted,#809087);
        --pam-shadow-sm:0 4px 14px rgba(15,35,24,.045);
        --pam-shadow-md:0 18px 44px rgba(15,35,24,.13);
    }

    .pam-shell {
        display:grid;
        width: 100%;
        min-width:0;
        grid-column:1/-1;
        gap:.74rem;
        margin:0 auto;
        padding-bottom:1rem;
        color:var(--pam-text);
    }

    .pam-shell *, .pam-shell *::before, .pam-shell *::after,
    .pam-modal *, .pam-modal *::before, .pam-modal *::after { box-sizing:border-box; }

    /* Lucide: todos os contêineres de ícone usam flex para evitar desalinhamento após createIcons() */
    .pam-back,.pam-project-icon,.pam-brief-icon,.pam-workspace-icon,.pam-avatar,
    .pam-data-icon,.pam-state-icon,.pam-toast-icon,.pam-modal-close,.pam-modal-mark,
    .pam-clear-search {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        flex:0 0 auto;
        line-height:0;
    }

    .pam-back > svg,.pam-project-icon > svg,.pam-brief-icon > svg,.pam-workspace-icon > svg,
    .pam-data-icon > svg,.pam-state-icon > svg,.pam-toast-icon > svg,.pam-modal-close > svg,
    .pam-modal-mark > svg,.pam-clear-search > svg,
    .pam-top-action > svg,.pam-btn > svg,.pam-badge > svg,.pam-meta-part > svg {
        display:block;
        flex:0 0 auto;
        margin:0;
        vertical-align:middle;
    }

    /* CABEÇALHO */
    .pam-context {
        display:grid;
        min-width:0;
        grid-template-columns:auto auto minmax(0,1fr) auto;
        gap:.6rem;
        align-items:center;
        min-height:70px;
        padding:.66rem .72rem;
        overflow:hidden;
        border:1px solid var(--pam-border);
        border-radius:15px;
        background:radial-gradient(circle at 100% 0,rgba(124,58,237,.07),transparent 17rem),linear-gradient(180deg,var(--pam-soft),#fff);
        box-shadow:var(--pam-shadow-sm);
    }

    .pam-back,.pam-project-icon { width:40px; height:40px; border-radius:11px; }
    .pam-back { border:1px solid var(--pam-border); background:#fff; color:var(--pam-secondary); text-decoration:none; transition:.15s ease; }
    .pam-back:hover,.pam-back:focus-visible { outline:none; border-color:rgba(37,99,235,.24); background:var(--pam-blue-soft); color:var(--pam-blue); transform:translateX(-1px); }
    .pam-project-icon { background:var(--pam-violet-soft); color:var(--pam-violet); }
    .pam-back > svg,.pam-project-icon > svg { width:17px; height:17px; }

    .pam-context-copy { min-width:0; }
    .pam-context-kicker { display:flex; align-items:center; gap:.24rem; color:var(--pam-violet); font-size:.66rem; font-weight:800; line-height:1.25; }
    .pam-context-kicker > svg { width:12px; height:12px; }
    .pam-title { margin:.04rem 0 0; color:var(--pam-text); font-size:clamp(1rem,2vw,1.16rem); font-weight:860; letter-spacing:-.03em; line-height:1.26; overflow-wrap:anywhere; }
    .pam-project-meta { display:flex; gap:.42rem; align-items:center; flex-wrap:wrap; margin-top:.1rem; color:var(--pam-faded); font-size:.66rem; line-height:1.3; }
    .pam-project-meta > span { display:inline-flex; align-items:center; gap:.18rem; min-width:0; }
    .pam-project-meta svg { width:11px; height:11px; }

    .pam-context-actions { display:flex; gap:.32rem; align-items:center; justify-content:flex-end; flex-wrap:wrap; }
    .pam-top-action,.pam-btn {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:.28rem;
        border:1px solid var(--pam-border-strong);
        border-radius:9px;
        background:#fff;
        color:var(--pam-secondary);
        font:inherit;
        font-size:.69rem;
        font-weight:780;
        line-height:1;
        text-decoration:none;
        white-space:nowrap;
        cursor:pointer;
        transition:.15s ease;
    }
    .pam-top-action { min-height:38px; padding:.4rem .54rem; }
    .pam-top-action > svg,.pam-btn > svg { width:13px; height:13px; }
    .pam-top-action:hover,.pam-top-action:focus-visible,.pam-btn:hover,.pam-btn:focus-visible { outline:none; transform:translateY(-1px); }
    .pam-top-action.secondary { border-color:rgba(124,58,237,.17); background:var(--pam-violet-soft); color:var(--pam-violet); }
    .pam-top-action.primary { border-color:rgba(22,138,77,.17); background:var(--pam-green-soft); color:var(--pam-green); }

    /* RESUMO COMPACTO */
    .pam-brief {
        display:grid;
        min-width:0;
        grid-template-columns:repeat(3,minmax(0,1fr));
        overflow:hidden;
        border:1px solid var(--pam-border);
        border-radius:14px;
        background:#fff;
        box-shadow:var(--pam-shadow-sm);
    }
    .pam-brief-item { --tone:var(--pam-blue); --soft:var(--pam-blue-soft); display:grid; min-width:0; grid-template-columns:auto minmax(0,1fr); gap:.44rem; align-items:center; min-height:64px; padding:.48rem .56rem; }
    .pam-brief-item + .pam-brief-item { border-left:1px solid var(--pam-border); }
    .pam-brief-item.participation { --tone:var(--pam-sky); --soft:var(--pam-sky-soft); }
    .pam-brief-item.products { --tone:var(--pam-violet); --soft:var(--pam-violet-soft); }
    .pam-brief-icon { width:34px; height:34px; border-radius:10px; background:var(--soft); color:var(--tone); }
    .pam-brief-icon > svg { width:15px; height:15px; }
    .pam-brief-copy { min-width:0; }
    .pam-brief-label { color:var(--pam-faded); font-size:.62rem; font-weight:700; line-height:1.2; }
    .pam-brief-value { margin-top:.03rem; color:var(--tone); font-size:.78rem; font-weight:840; line-height:1.25; overflow-wrap:anywhere; }

    /* WORKSPACE */
    .pam-workspace { min-width:0; overflow:hidden; border:1px solid var(--pam-border); border-radius:15px; background:#fff; box-shadow:var(--pam-shadow-sm); }
    .pam-workspace-head { display:grid; min-width:0; grid-template-columns:auto minmax(130px,.55fr) minmax(360px,1fr); gap:.56rem; align-items:center; padding:.58rem .66rem; border-bottom:1px solid var(--pam-border); background:linear-gradient(180deg,var(--pam-soft),#fff); }
    .pam-workspace-icon { width:38px; height:38px; border-radius:10px; background:var(--pam-blue-soft); color:var(--pam-blue); }
    .pam-workspace-icon > svg { width:17px; height:17px; }
    .pam-workspace-title { display:flex; min-width:0; align-items:center; gap:.34rem; }
    .pam-workspace-title h2 { margin:0; color:var(--pam-text); font-size:.9rem; font-weight:840; letter-spacing:-.02em; }
    .pam-results-count { display:inline-flex; align-items:center; justify-content:center; min-width:24px; min-height:24px; padding:.15rem .32rem; border-radius:999px; background:var(--pam-blue-soft); color:var(--pam-blue); font-size:.62rem; font-weight:820; }

    .pam-toolbar { display:grid; min-width:0; grid-template-columns:minmax(220px,1fr) minmax(165px,210px); gap:.4rem; }
    .pam-search-wrap { position:relative; min-width:0; }
    .pam-search-icon { position:absolute; top:50%; left:.62rem; width:14px; height:14px; color:var(--pam-faded); pointer-events:none; transform:translateY(-50%); }
    .pam-input,.pam-select { width:100%; min-width:0; min-height:39px; border:1px solid var(--pam-border-strong); border-radius:9px; outline:none; background:#fff; color:var(--pam-text); font:inherit; font-size:.71rem; }
    .pam-input { padding:.46rem 2.1rem .46rem 1.85rem; }
    .pam-select { padding:.46rem 1.7rem .46rem .56rem; cursor:pointer; }
    .pam-input:focus,.pam-select:focus { border-color:var(--pam-blue); box-shadow:0 0 0 3px rgba(37,99,235,.08); }
    .pam-clear-search { position:absolute; top:50%; right:.35rem; display:none; width:28px; height:28px; border:0; border-radius:8px; background:transparent; color:var(--pam-faded); cursor:pointer; transform:translateY(-50%); }
    .pam-clear-search.visible { display:inline-flex; }
    .pam-clear-search:hover,.pam-clear-search:focus-visible { outline:none; background:var(--pam-slate-soft); color:var(--pam-text); }
    .pam-clear-search > svg { width:13px; height:13px; }

    /* LISTA */
    .pam-list,.pam-skeleton-list { display:grid; min-width:0; gap:.48rem; padding:.58rem .62rem; background:var(--pam-soft); }
    .pam-item { --participant-tone:var(--pam-slate); --participant-soft:var(--pam-slate-soft); display:grid; min-width:0; grid-template-columns:minmax(230px,.8fr) minmax(360px,1.2fr) auto; gap:.56rem; align-items:center; padding:.58rem; border:1px solid var(--pam-border); border-radius:12px; background:linear-gradient(90deg,var(--participant-soft) 0 3px,#fff 3px); box-shadow:0 3px 10px rgba(15,35,24,.025); transition:.15s ease; }
    .pam-item.is-active { --participant-tone:var(--pam-green); --participant-soft:var(--pam-green-soft); }
    .pam-item.is-blocked { --participant-tone:var(--pam-red); --participant-soft:var(--pam-red-soft); }
    .pam-item:hover { border-color:color-mix(in srgb,var(--participant-tone) 18%,var(--pam-border)); box-shadow:0 6px 18px rgba(15,35,24,.05); }

    .pam-item-head { min-width:0; }
    .pam-person { display:grid; min-width:0; grid-template-columns:auto minmax(0,1fr); gap:.48rem; align-items:center; }
    .pam-avatar { width:40px; height:40px; border-radius:11px; background:var(--participant-soft); color:var(--participant-tone); font-size:.69rem; font-weight:860; }
    .pam-person-copy { min-width:0; }
    .pam-name-line { display:flex; min-width:0; align-items:center; gap:.3rem; flex-wrap:wrap; }
    .pam-name { min-width:0; color:var(--pam-text); font-size:.79rem; font-weight:830; line-height:1.3; overflow-wrap:anywhere; }
    .pam-badge { display:inline-flex; align-items:center; gap:.18rem; min-height:22px; padding:.15rem .3rem; border-radius:999px; background:var(--participant-soft); color:var(--participant-tone); font-size:.59rem; font-weight:800; white-space:nowrap; line-height:1; }
    .pam-badge > svg { width:10px; height:10px; }
    .pam-meta { display:flex; min-width:0; gap:.3rem; align-items:center; flex-wrap:wrap; margin-top:.08rem; color:var(--pam-faded); font-size:.63rem; line-height:1.3; }
    .pam-meta-part { display:inline-flex; min-width:0; align-items:center; gap:.15rem; }
    .pam-meta-part > svg { width:10px; height:10px; }

    .pam-item-data { display:grid; min-width:0; grid-template-columns:minmax(210px,1.12fr) minmax(155px,.88fr); gap:.4rem; }
    .pam-data-box { min-width:0; min-height:72px; padding:.46rem .48rem; border:1px solid var(--pam-border); border-radius:10px; }
    .pam-financial { background:linear-gradient(135deg,#fff,var(--pam-green-soft)); border-color:rgba(22,138,77,.10); }
    .pam-financial.is-warning { background:linear-gradient(135deg,#fff,var(--pam-amber-soft)); border-color:rgba(200,116,8,.12); }
    .pam-financial.is-danger { background:linear-gradient(135deg,#fff,var(--pam-red-soft)); border-color:rgba(207,63,63,.12); }
    .pam-products { background:linear-gradient(135deg,#fff,var(--pam-violet-soft)); border-color:rgba(124,58,237,.10); }
    .pam-data-main { display:grid; min-width:0; grid-template-columns:auto minmax(0,1fr) auto; gap:.4rem; align-items:center; }
    .pam-data-icon { width:32px; height:32px; border-radius:9px; background:rgba(255,255,255,.82); box-shadow:inset 0 0 0 1px rgba(100,116,139,.10); color:var(--pam-green); }
    .pam-products .pam-data-icon { color:var(--pam-violet); }
    .pam-financial.is-warning .pam-data-icon { color:var(--pam-amber); }
    .pam-financial.is-danger .pam-data-icon { color:var(--pam-red); }
    .pam-data-icon > svg { width:14px; height:14px; }
    .pam-data-copy { min-width:0; }
    .pam-metric-label { color:var(--pam-faded); font-size:.61rem; font-weight:700; line-height:1.2; }
    .pam-metric-value { margin-top:.02rem; color:var(--pam-text); font-size:.77rem; font-weight:840; line-height:1.25; overflow-wrap:anywhere; }
    .pam-metric-helper { margin-top:.03rem; color:var(--pam-secondary); font-size:.61rem; line-height:1.3; overflow-wrap:anywhere; }
    .pam-financial-percent { min-height:21px; padding:.12rem .28rem; border-radius:999px; background:rgba(255,255,255,.8); color:var(--pam-green); font-size:.59rem; font-weight:820; white-space:nowrap; }
    .pam-financial.is-warning .pam-financial-percent { color:var(--pam-amber); }
    .pam-financial.is-danger .pam-financial-percent { color:var(--pam-red); }
    .pam-progress { height:6px; margin-top:.32rem; overflow:hidden; border-radius:999px; background:rgba(148,163,184,.18); }
    .pam-progress > span { display:block; height:100%; border-radius:inherit; background:var(--pam-green); }
    .pam-progress.is-warning > span { background:var(--pam-amber); }
    .pam-progress.is-danger > span { background:var(--pam-red); }

    .pam-row-actions { display:grid; min-width:0; gap:.26rem; }
    .pam-btn { min-height:34px; padding:.36rem .46rem; }
    .pam-btn.primary { border-color:rgba(124,58,237,.18); background:var(--pam-violet-soft); color:var(--pam-violet); }
    .pam-btn.warning { border-color:rgba(200,116,8,.18); background:var(--pam-amber-soft); color:#92400e; }
    .pam-btn.danger { border-color:rgba(207,63,63,.18); background:var(--pam-red-soft); color:var(--pam-red); }
    .pam-btn:hover,.pam-btn:focus-visible { border-color:rgba(37,99,235,.20); background:var(--pam-blue-soft); color:var(--pam-blue); }
    .pam-btn.primary:hover,.pam-btn.primary:focus-visible { border-color:rgba(124,58,237,.28); background:var(--pam-violet-soft); color:var(--pam-violet); }
    .pam-btn.warning:hover,.pam-btn.warning:focus-visible { border-color:rgba(200,116,8,.28); background:var(--pam-amber-soft); color:#92400e; }
    .pam-btn:disabled { cursor:not-allowed; opacity:.46; transform:none; }

    /* PAGINAÇÃO / ESTADOS */
    .pam-pager { display:grid; min-width:0; grid-template-columns:minmax(0,1fr) auto; gap:.5rem; align-items:center; padding:.52rem .62rem; border-top:1px solid var(--pam-border); background:linear-gradient(180deg,#fff,var(--pam-soft)); }
    .pam-pager-info { color:var(--pam-faded); font-size:.64rem; font-weight:680; }
    .pam-pager-actions { display:flex; gap:.28rem; }
    .pam-state { display:grid; min-height:160px; place-items:center; align-content:center; gap:.16rem; padding:1rem; text-align:center; }
    .pam-state-icon { width:44px; height:44px; margin-bottom:.26rem; border-radius:12px; background:var(--pam-blue-soft); color:var(--pam-blue); }
    .pam-state-icon > svg { width:19px; height:19px; }
    .pam-state strong { color:var(--pam-text); font-size:.77rem; font-weight:830; }
    .pam-state p { max-width:390px; margin:0; color:var(--pam-secondary); font-size:.67rem; line-height:1.4; }
    .pam-skeleton { height:96px; border:1px solid var(--pam-border); border-radius:12px; background:linear-gradient(90deg,#edf2ef 25%,#fafcfb 50%,#edf2ef 75%); background-size:200% 100%; animation:pam-shimmer 1.15s infinite linear; }
    @keyframes pam-shimmer { to { background-position:-200% 0; } }

    /* TOAST */
    .pam-toast-root { position:fixed; z-index:1200; top:1rem; right:1rem; display:grid; width:min(360px,calc(100vw - 2rem)); gap:.4rem; pointer-events:none; }
    .pam-toast { display:grid; grid-template-columns:32px minmax(0,1fr); gap:.46rem; align-items:center; padding:.54rem .58rem; border:1px solid var(--pam-border); border-radius:11px; background:#fff; box-shadow:var(--pam-shadow-md); color:var(--pam-text); font-size:.69rem; font-weight:700; pointer-events:auto; }
    .pam-toast-icon { width:32px; height:32px; border-radius:9px; background:var(--pam-green-soft); color:var(--pam-green); }
    .pam-toast.error .pam-toast-icon { background:var(--pam-red-soft); color:var(--pam-red); }
    .pam-toast-icon > svg { width:15px; height:15px; }

    /* CONFIRMAÇÃO */
    .pam-modal { position:fixed; z-index:1150; inset:0; display:none; place-items:center; padding:max(14px,env(safe-area-inset-top)) max(12px,env(safe-area-inset-right)) max(14px,env(safe-area-inset-bottom)) max(12px,env(safe-area-inset-left)); overflow:auto; background:rgba(8,24,15,.52); backdrop-filter:blur(2px); }
    .pam-modal.active { display:grid; }
    .pam-modal-card { width:min(100%,420px); overflow:hidden; border:1px solid var(--pam-border); border-radius:15px; background:#fff; box-shadow:0 24px 64px rgba(15,23,42,.22); }
    .pam-modal-head { display:grid; grid-template-columns:auto minmax(0,1fr) auto; gap:.46rem; align-items:center; padding:.62rem .66rem; border-bottom:1px solid var(--pam-border); background:linear-gradient(180deg,var(--pam-soft),#fff); }
    .pam-modal-mark { width:34px; height:34px; border-radius:9px; background:var(--pam-amber-soft); color:var(--pam-amber); }
    .pam-modal-mark > svg { width:16px; height:16px; }
    .pam-modal-head strong { color:var(--pam-text); font-size:.8rem; font-weight:830; }
    .pam-modal-close { width:34px; height:34px; border:1px solid var(--pam-border); border-radius:9px; background:#fff; color:var(--pam-secondary); cursor:pointer; }
    .pam-modal-close:hover,.pam-modal-close:focus-visible { outline:none; border-color:rgba(37,99,235,.22); background:var(--pam-blue-soft); color:var(--pam-blue); }
    .pam-modal-close > svg { width:15px; height:15px; }
    .pam-modal-body { padding:.66rem; }
    .pam-modal-message { margin:0; color:var(--pam-secondary); font-size:.7rem; line-height:1.45; }
    .pam-modal-actions { display:flex; gap:.32rem; justify-content:flex-end; padding:.56rem .66rem .62rem; border-top:1px solid var(--pam-border); background:var(--pam-soft); }

    /* RESPONSIVO */
    @media (max-width:1080px) {
        .pam-workspace-head { grid-template-columns:auto minmax(120px,.4fr) minmax(300px,1fr); }
        .pam-item { grid-template-columns:minmax(220px,.8fr) minmax(340px,1.2fr); }
        .pam-row-actions { grid-column:1/-1; grid-template-columns:repeat(3,max-content); justify-content:end; }
    }

    @media (max-width:820px) {
        .pam-context { grid-template-columns:auto auto minmax(0,1fr); }
        .pam-context-actions { grid-column:3; justify-content:flex-start; }
        .pam-workspace-head { grid-template-columns:auto minmax(0,1fr); }
        .pam-toolbar { grid-column:1/-1; }
        .pam-item { grid-template-columns:1fr; align-items:stretch; }
        .pam-row-actions { grid-column:auto; grid-template-columns:repeat(3,minmax(0,1fr)); justify-content:stretch; }
        .pam-row-actions .pam-btn { width:100%; }
    }

    @media (max-width:680px) {
        .pam-brief { display:flex; gap:.36rem; overflow-x:auto; padding:.36rem; scrollbar-width:none; scroll-snap-type:x proximity; }
        .pam-brief::-webkit-scrollbar { display:none; }
        .pam-brief-item { min-width:190px; min-height:58px; padding:.42rem .46rem; border:0 !important; border-radius:10px; background:color-mix(in srgb,var(--soft) 64%,#fff); scroll-snap-align:start; }
        .pam-toolbar { grid-template-columns:1fr; }
        .pam-item-data { grid-template-columns:1fr; }
    }

    @media (max-width:560px) {
        .pam-shell { gap:.64rem; }
        .pam-context { grid-template-columns:36px minmax(0,1fr); padding:.58rem; }
        .pam-back { width:36px; height:36px; }
        .pam-project-icon { display:none; }
        .pam-context-actions { grid-column:1/-1; display:grid; grid-template-columns:1fr 1fr; width:100%; }
        .pam-top-action { width:100%; }
        .pam-project-meta { gap:.24rem; }
        .pam-workspace-head { padding:.54rem .56rem; }
        .pam-workspace-icon { width:36px; height:36px; }
        .pam-toolbar { gap:.34rem; }
        .pam-list,.pam-skeleton-list { padding:.5rem; }
        .pam-item { padding:.54rem; gap:.48rem; }
        .pam-name-line { align-items:flex-start; }
        .pam-meta { gap:.22rem .3rem; }
        .pam-row-actions { grid-template-columns:1fr 1fr 1fr; }
        .pam-btn { min-width:0; padding:.36rem .35rem; }
        .pam-pager { grid-template-columns:1fr; }
        .pam-pager-actions { display:grid; grid-template-columns:1fr 1fr; }
        .pam-pager-actions .pam-btn { width:100%; }
        .pam-toast-root { top:auto; right:.65rem; bottom:calc(5rem + env(safe-area-inset-bottom)); left:.65rem; width:auto; }
        .pam-modal { place-items:end center; padding:0; }
        .pam-modal-card { width:100%; border-right:0; border-bottom:0; border-left:0; border-radius:16px 16px 0 0; }
        .pam-modal-actions { display:grid; grid-template-columns:1fr 1fr; padding-bottom:calc(.62rem + env(safe-area-inset-bottom)); }
        .pam-modal-actions .pam-btn { width:100%; min-height:40px; }
    }

    @media (max-width:390px) {
        .pam-context-actions { grid-template-columns:1fr; }
        .pam-row-actions { grid-template-columns:1fr; }
        .pam-modal-actions { grid-template-columns:1fr; }
    }

    @media (prefers-reduced-motion:reduce) {
        .pam-shell *, .pam-shell *::before, .pam-shell *::after,
        .pam-modal *, .pam-modal *::before, .pam-modal *::after { animation-duration:.01ms !important; animation-iteration-count:1 !important; scroll-behavior:auto !important; transition-duration:.01ms !important; }
    }
</style>

<main class="pam-shell" id="project-associates-manager">
    <section class="pam-context" aria-labelledby="pam-page-title">
        <a
            class="pam-back"
            href="{{ route('delivery.projects.producers', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            aria-label="Voltar"
            title="Voltar"
        >
            <i data-lucide="arrow-left"></i>
        </a>

        <span class="pam-project-icon" aria-hidden="true">
            <i data-lucide="users-round"></i>
        </span>

        <div class="pam-context-copy">
            <span class="pam-context-kicker">
                <i data-lucide="sliders-horizontal"></i>
                Participação e limites
            </span>

            <h1 class="pam-title" id="pam-page-title">{{ $project->title }}</h1>

            @if($projectPeriod)
                <div class="pam-project-meta">
                    <span>
                        <i data-lucide="calendar-days"></i>
                        <span>{{ $projectPeriod }}</span>
                    </span>
                </div>
            @endif
        </div>

        <div class="pam-context-actions">
            <a
                class="pam-top-action secondary"
                href="{{ route('delivery.projects.product-limits.index', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            >
                <i data-lucide="package-check"></i>
                Limites por produto
            </a>

            <a
                class="pam-top-action primary"
                href="{{ route('delivery.register', ['tenant' => $tenantSlug, 'project' => $project->id]) }}"
            >
                <i data-lucide="package-plus"></i>
                Registrar entrega
            </a>
        </div>
    </section>

    <section class="pam-brief" aria-label="Configuração do projeto">
        <article class="pam-brief-item">
            <span class="pam-brief-icon" aria-hidden="true">
                <i data-lucide="users-round"></i>
            </span>
            <div class="pam-brief-copy">
                <div class="pam-brief-label">Associados</div>
                <div class="pam-brief-value" id="pam-total">—</div>
            </div>
        </article>

        <article class="pam-brief-item participation">
            <span class="pam-brief-icon" aria-hidden="true">
                <i data-lucide="{{ $project->restrict_participants ? 'user-round-check' : 'users-round' }}"></i>
            </span>
            <div class="pam-brief-copy">
                <div class="pam-brief-label">Participação</div>
                <div class="pam-brief-value">
                    {{ $project->restrict_participants ? 'Somente autorizados' : 'Aberta' }}
                </div>
            </div>
        </article>

        <article class="pam-brief-item products">
            <span class="pam-brief-icon" aria-hidden="true">
                <i data-lucide="{{ $project->allow_any_product ? 'package-open' : 'package-check' }}"></i>
            </span>
            <div class="pam-brief-copy">
                <div class="pam-brief-label">Produtos</div>
                <div class="pam-brief-value">
                    {{ $project->allow_any_product ? 'Catálogo livre' : 'Por cotas' }}
                </div>
            </div>
        </article>
    </section>

    <section class="pam-workspace">
        <header class="pam-workspace-head">
            <span class="pam-workspace-icon" aria-hidden="true">
                <i data-lucide="contact-round"></i>
            </span>

            <div class="pam-workspace-title">
                <h2>Associados</h2>
            </div>

            <div class="pam-toolbar">
                <div class="pam-search-wrap">
                    <i class="pam-search-icon" data-lucide="search"></i>
                    <input
                        class="pam-input"
                        id="pam-search"
                        type="search"
                        autocomplete="off"
                        placeholder="Nome, matrícula ou localidade"
                        aria-label="Buscar associado"
                    >
                    <button
                        class="pam-clear-search"
                        id="pam-clear-search"
                        type="button"
                        aria-label="Limpar busca"
                        title="Limpar busca"
                    >
                        <i data-lucide="x"></i>
                    </button>
                </div>

                <select class="pam-select" id="pam-status" aria-label="Filtrar participação">
                    <option value="" @selected(! $project->restrict_participants)>Todos</option>
                    <option value="active" @selected($project->restrict_participants)>Pode entregar</option>
                    <option value="blocked">Bloqueados</option>
                    <option value="unconfigured">Não configurados</option>
                </select>
            </div>
        </header>

        <div class="pam-skeleton-list" id="pam-skeleton" aria-hidden="true">
            @for($index = 0; $index < 5; $index++)
                <div class="pam-skeleton"></div>
            @endfor
        </div>

        <section
            class="pam-list"
            id="pam-list"
            aria-live="polite"
            aria-busy="true"
            hidden
        ></section>

        <div class="pam-pager" id="pam-pager" hidden></div>
    </section>
</main>

<div class="pam-toast-root" id="pam-toast-root" aria-live="polite"></div>

<div
    class="pam-modal"
    id="pam-confirm-modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="pam-confirm-title"
    aria-hidden="true"
>
    <div class="pam-modal-card">
        <div class="pam-modal-head">
            <span class="pam-modal-mark" aria-hidden="true">
                <i data-lucide="triangle-alert"></i>
            </span>

            <strong id="pam-confirm-title">Confirmar alteração</strong>

            <button class="pam-modal-close" type="button" id="pam-confirm-close" aria-label="Fechar">
                <i data-lucide="x"></i>
            </button>
        </div>

        <div class="pam-modal-body">
            <p class="pam-modal-message" id="pam-confirm-message"></p>
        </div>

        <div class="pam-modal-actions">
            <button class="pam-btn" type="button" id="pam-confirm-cancel">Voltar</button>
            <button class="pam-btn warning" type="button" id="pam-confirm-action">Confirmar</button>
        </div>
    </div>
</div>
<script>
    const PAM_BASE = @json(url('/'.$tenantSlug.'/delivery/projects/'.$project->id));
    const PAM_CSRF = @json(csrf_token());
    const PAM_CAN_MANAGE = @json($canManage);

    let pamPage = 1;
    let pamAbort = null;
    let pamTimer = null;
    let pamPendingConfirmation = null;

    const pamElements = {
        search: document.getElementById('pam-search'),
        clearSearch: document.getElementById('pam-clear-search'),
        status: document.getElementById('pam-status'),
        total: document.getElementById('pam-total'),
        list: document.getElementById('pam-list'),
        skeleton: document.getElementById('pam-skeleton'),
        pager: document.getElementById('pam-pager'),
        toastRoot: document.getElementById('pam-toast-root'),
        confirmModal: document.getElementById('pam-confirm-modal'),
        confirmTitle: document.getElementById('pam-confirm-title'),
        confirmMessage: document.getElementById('pam-confirm-message'),
        confirmAction: document.getElementById('pam-confirm-action'),
    };

    const pamEsc = value => String(value ?? '').replace(
        /[&<>"']/g,
        character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[character])
    );

    const pamMoney = value => value === null
        ? 'Sem limite'
        : Number(value || 0).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });

    const pamNumber = value => Number(value || 0).toLocaleString('pt-BR', {
        maximumFractionDigits: 3,
    });

    function pamInitials(name) {
        return String(name || '?')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map(part => part.charAt(0))
            .join('')
            .toUpperCase();
    }

    function pamProgressTone(percent) {
        if (percent >= 100) return 'is-danger';
        if (percent >= 80) return 'is-warning';
        return '';
    }

    function pamStatusMeta(status) {
        return {
            active: {
                label: 'Pode entregar',
                icon: 'circle-check',
            },
            blocked: {
                label: 'Bloqueado',
                icon: 'circle-x',
            },
            unconfigured: {
                label: 'Não configurado',
                icon: 'circle-dashed',
            },
        }[status] || {
            label: 'Não configurado',
            icon: 'circle-dashed',
        };
    }

    function pamSetLoading(loading) {
        pamElements.skeleton.hidden = !loading;
        pamElements.list.hidden = loading;
        pamElements.list.setAttribute(
            'aria-busy',
            loading ? 'true' : 'false'
        );
        pamElements.pager.hidden = loading;
    }

    function pamEmptyState(title, description, icon = 'users-round') {
        return `
            <div class="pam-state" style="grid-column:1/-1">
                <div class="pam-state-icon">
                    <i data-lucide="${icon}"></i>
                </div>
                <strong>${pamEsc(title)}</strong>
                ${description ? `<p>${pamEsc(description)}</p>` : ''}
            </div>
        `;
    }

    function pamShowToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `pam-toast ${type === 'error' ? 'error' : ''}`;
        toast.innerHTML = `
            <div class="pam-toast-icon">
                <i data-lucide="${type === 'error' ? 'circle-alert' : 'circle-check'}"></i>
            </div>
            <span>${pamEsc(message)}</span>
        `;

        pamElements.toastRoot.appendChild(toast);
        pamIcons();

        window.setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-5px)';
            toast.style.transition = 'all .18s ease';

            window.setTimeout(() => toast.remove(), 190);
        }, 3400);
    }

    async function pamApi(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers || {}),
            },
        });

        const data = await response.json().catch(() => ({
            message: 'A resposta do servidor não pôde ser interpretada.',
        }));

        if (!response.ok || data.success === false) {
            throw new Error(
                data.message
                || Object.values(data.errors || {}).flat()[0]
                || 'Não foi possível concluir a solicitação.'
            );
        }

        return data;
    }

    async function pamLoad() {
        if (pamAbort) {
            pamAbort.abort();
        }

        pamAbort = new AbortController();

        const search = pamElements.search.value.trim();
        const status = pamElements.status.value;

        pamSetLoading(true);

        try {
            const data = await pamApi(
                `${PAM_BASE}/associates-data?page=${pamPage}&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`,
                {
                    signal: pamAbort.signal,
                }
            );

            pamRender(data);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            pamElements.total.textContent = '—';
            pamElements.list.innerHTML = pamEmptyState(
                'Erro ao carregar associados',
                error.message,
                'wifi-off'
            );
            pamElements.pager.innerHTML = `
                <div class="pam-pager-info">Falha ao carregar a página.</div>
                <div class="pam-pager-actions">
                    <button class="pam-btn primary" type="button" onclick="pamLoad()">
                        <i data-lucide="refresh-cw"></i>
                        Tentar novamente
                    </button>
                </div>
            `;
            pamSetLoading(false);
            pamIcons();
        }
    }

    function pamRender(data) {
        const items = Array.isArray(data.data) ? data.data : [];

        pamElements.total.textContent = pamNumber(data.total || 0);

        pamElements.list.innerHTML = items.length
            ? items.map(pamAssociateCard).join('')
            : pamEmptyState(
                'Nenhum associado encontrado',
                'Altere a busca ou o filtro.',
                'user-round-search'
            );

        pamElements.pager.innerHTML = pamPagination(data);

        pamSetLoading(false);
        pamIcons();
    }

    function pamAssociateCard(item) {
        const limit = item.financial_limit === null ? null : Number(item.financial_limit || 0);
        const consumed = Number(item.financial_consumed || 0);
        const remaining = item.financial_remaining === null ? null : Number(item.financial_remaining || 0);
        const rawPercent = limit && limit > 0 ? (consumed / limit) * 100 : 0;
        const percent = Math.max(0, Math.min(100, rawPercent));
        const status = item.participation_status || 'unconfigured';
        const meta = pamStatusMeta(status);
        const nextStatus = status === 'active' ? 'blocked' : 'active';
        const products = Number(item.product_limits || 0);
        const planned = Number(item.simulated_limit_value || 0);
        const location = item.location ? pamEsc(item.location) : 'Sem localidade';
        const code = item.code ? `#${pamEsc(item.code)}` : 'Sem matrícula';
        const progressTone = pamProgressTone(rawPercent);

        return `
            <article class="pam-item ${status === 'active' ? 'is-active' : status === 'blocked' ? 'is-blocked' : ''}">
                <div class="pam-item-head">
                    <div class="pam-person">
                        <span class="pam-avatar" aria-hidden="true">${pamEsc(pamInitials(item.name))}</span>

                        <div class="pam-person-copy">
                            <div class="pam-name-line">
                                <div class="pam-name" title="${pamEsc(item.name)}">${pamEsc(item.name)}</div>
                                <span class="pam-badge">
                                    <i data-lucide="${meta.icon}"></i>
                                    ${pamEsc(meta.label)}
                                </span>
                            </div>

                            <div class="pam-meta">
                                <span class="pam-meta-part">
                                    <i data-lucide="hash"></i>
                                    <span>${code}</span>
                                </span>
                                <span class="pam-meta-part">
                                    <i data-lucide="map-pin"></i>
                                    <span>${location}</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pam-item-data">
                    <div class="pam-data-box pam-financial ${progressTone}">
                        <div class="pam-data-main">
                            <span class="pam-data-icon" aria-hidden="true">
                                <i data-lucide="wallet-cards"></i>
                            </span>

                            <div class="pam-data-copy">
                                <div class="pam-metric-label">Saldo</div>
                                <div class="pam-metric-value">${pamMoney(remaining)}</div>
                                <div class="pam-metric-helper">
                                    ${limit === null ? `${pamMoney(consumed)} usado · sem teto` : `${pamMoney(consumed)} / ${pamMoney(limit)}`}
                                </div>
                            </div>

                            ${limit !== null ? `<span class="pam-financial-percent">${Math.round(rawPercent)}%</span>` : ''}
                        </div>

                        ${limit !== null ? `
                            <div class="pam-progress ${progressTone}" role="progressbar" aria-label="Uso do limite financeiro" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percent}">
                                <span style="width:${percent}%"></span>
                            </div>
                        ` : ''}
                    </div>

                    <div class="pam-data-box pam-products">
                        <div class="pam-data-main">
                            <span class="pam-data-icon" aria-hidden="true">
                                <i data-lucide="package-check"></i>
                            </span>

                            <div class="pam-data-copy">
                                <div class="pam-metric-label">Cotas</div>
                                <div class="pam-metric-value">${pamNumber(products)} ${products === 1 ? 'produto' : 'produtos'}</div>
                                <div class="pam-metric-helper">${pamMoney(planned)} previsto</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pam-row-actions">
                    <a class="pam-btn" href="${pamEsc(item.manage_url)}">
                        <i data-lucide="user-round-search"></i>
                        Detalhes
                    </a>

                    <a class="pam-btn primary" href="${pamEsc(item.limits_url)}">
                        <i data-lucide="sliders-horizontal"></i>
                        Cotas
                    </a>

                    ${PAM_CAN_MANAGE ? `
                        <button
                            class="pam-btn ${nextStatus === 'blocked' ? 'warning' : ''}"
                            type="button"
                            data-participation-id="${Number(item.id)}"
                            data-participation-status="${pamEsc(nextStatus)}"
                            data-participation-name="${pamEsc(item.name)}"
                        >
                            <i data-lucide="${nextStatus === 'active' ? 'user-round-check' : 'user-round-x'}"></i>
                            ${nextStatus === 'active' ? 'Permitir' : 'Bloquear'}
                        </button>
                    ` : ''}
                </div>
            </article>
        `;
    }

    function pamPagination(data) {
        const currentPage = Number(data.current_page || 1);
        const lastPage = Number(data.last_page || 1);
        const from = Number(data.from || 0);
        const to = Number(data.to || 0);
        const total = Number(data.total || 0);

        return `
            <div class="pam-pager-info">
                ${total
                    ? `Exibindo ${pamNumber(from)} a ${pamNumber(to)} de ${pamNumber(total)} associados`
                    : 'Nenhum resultado para exibir'}
            </div>

            <div class="pam-pager-actions">
                <button
                    class="pam-btn"
                    type="button"
                    ${currentPage <= 1 ? 'disabled' : ''}
                    onclick="pamGo(${currentPage - 1})"
                >
                    <i data-lucide="chevron-left"></i>
                    Anterior
                </button>

                <button
                    class="pam-btn"
                    type="button"
                    ${currentPage >= lastPage ? 'disabled' : ''}
                    onclick="pamGo(${currentPage + 1})"
                >
                    Próxima
                    <i data-lucide="chevron-right"></i>
                </button>
            </div>
        `;
    }

    function pamGo(page) {
        const targetPage = Number(page);

        if (!Number.isFinite(targetPage) || targetPage < 1) {
            return;
        }

        pamPage = targetPage;
        pamLoad();

        document
            .getElementById('project-associates-manager')
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
    }

    function pamRequestParticipation(id, status, name) {
        const allowing = status === 'active';

        pamPendingConfirmation = async () => {
            pamElements.confirmAction.disabled = true;

            try {
                await pamApi(`${PAM_BASE}/associates/${id}/participation`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': PAM_CSRF,
                    },
                    body: JSON.stringify({
                        status,
                    }),
                });

                pamCloseConfirm();
                pamShowToast(
                    allowing
                        ? `${name} agora pode registrar entregas.`
                        : `Novas entregas foram bloqueadas para ${name}.`
                );
                await pamLoad();
            } catch (error) {
                pamShowToast(error.message, 'error');
            } finally {
                pamElements.confirmAction.disabled = false;
            }
        };

        pamElements.confirmTitle.textContent = allowing
            ? 'Permitir novas entregas'
            : 'Bloquear novas entregas';

        pamElements.confirmMessage.textContent = allowing
            ? `${name} poderá registrar novas entregas.`
            : `${name} não poderá registrar novas entregas.`;

        pamElements.confirmAction.textContent = allowing
            ? 'Permitir entregas'
            : 'Bloquear entregas';

        pamElements.confirmModal.classList.add('active');
        pamElements.confirmModal.setAttribute('aria-hidden', 'false');
        pamElements.confirmAction.classList.toggle('warning', !allowing);
        pamElements.confirmAction.classList.toggle('primary', allowing);
        pamIcons();

        window.setTimeout(
            () => pamElements.confirmAction.focus(),
            30
        );
    }

    function pamCloseConfirm() {
        pamPendingConfirmation = null;
        pamElements.confirmModal.classList.remove('active');
        pamElements.confirmModal.setAttribute('aria-hidden', 'true');
    }

    function pamRunConfirmation() {
        if (typeof pamPendingConfirmation === 'function') {
            pamPendingConfirmation();
        }
    }

    function pamClearSearch() {
        pamElements.search.value = '';
        pamElements.clearSearch.classList.remove('visible');
        pamPage = 1;
        pamLoad();
        pamElements.search.focus();
    }

    function pamScheduleSearch() {
        window.clearTimeout(pamTimer);
        pamElements.clearSearch.classList.toggle(
            'visible',
            pamElements.search.value.length > 0
        );

        pamTimer = window.setTimeout(() => {
            pamPage = 1;
            pamLoad();
        }, 350);
    }

    function pamIcons() {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    pamElements.list.addEventListener('click', event => {
        const button = event.target.closest('[data-participation-id]');

        if (!button) {
            return;
        }

        pamRequestParticipation(
            Number(button.dataset.participationId),
            button.dataset.participationStatus,
            button.dataset.participationName || 'Associado'
        );
    });

    pamElements.confirmModal.addEventListener('click', event => {
        if (event.target === pamElements.confirmModal) {
            pamCloseConfirm();
        }
    });

    pamElements.search.addEventListener('input', pamScheduleSearch);
    pamElements.clearSearch.addEventListener('click', pamClearSearch);

    pamElements.status.addEventListener('change', () => {
        pamPage = 1;
        pamLoad();
    });

    document.getElementById('pam-confirm-close').addEventListener('click', pamCloseConfirm);
    document.getElementById('pam-confirm-cancel').addEventListener('click', pamCloseConfirm);
    pamElements.confirmAction.addEventListener('click', pamRunConfirmation);

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && pamElements.confirmModal.classList.contains('active')) {
            pamCloseConfirm();
        }
    });

    window.pamGo = pamGo;
    window.pamLoad = pamLoad;
    window.pamRequestParticipation = pamRequestParticipation;

    pamLoad();
    pamIcons();
</script>
@endsection