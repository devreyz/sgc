@extends('layouts.bento')

@section('title', $project->title)
@section('page-title', $project->title)
@section('page-subtitle', 'Acompanhe sua participação, limites e movimentações neste projeto.')
@section('user-role', 'Associado')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('associate', 'projects', request()->route('tenant'));

    $tenantSlug = request()->route('tenant') instanceof \App\Models\Tenant
        ? request()->route('tenant')->slug
        : request()->route('tenant');

    $projectPeriod = collect([
        $project->start_date?->format('d/m/Y'),
        $project->end_date?->format('d/m/Y'),
    ])->filter()->implode(' a ');

    $projectStatusValue = $project->status->value
        ?? (is_string($project->status ?? null) ? $project->status : 'active');

    $projectStatusLabel = is_object($project->status ?? null) && method_exists($project->status, 'getLabel')
        ? $project->status->getLabel()
        : match ($projectStatusValue) {
            'active' => 'Em execução',
            'draft' => 'Rascunho',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            default => ucfirst((string) $projectStatusValue),
        };

    $projectStatusIcon = match ($projectStatusValue) {
        'active' => 'ph-play-circle',
        'draft' => 'ph-note-pencil',
        'completed' => 'ph-check-circle',
        'cancelled' => 'ph-x-circle',
        default => 'ph-circle',
    };

    $projectSimulatorUrl = route('associate.projects.simulator', [
        'tenant' => $tenantSlug,
        'project' => $project->id,
    ]);

    $projectQuotaShareUrl = route('associate.projects.simulator', [
        'tenant' => $tenantSlug,
        'project' => $project->id,
        'share' => 'real-quotas',
    ]);
@endphp

@section('content')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">

<style>
.project-workspace{
    --green:#219653;--green-strong:#177c43;--green-soft:#edf8f1;--green-border:#cde8d6;
    --blue:#3478d4;--blue-soft:#eef4ff;--blue-border:#d4e2f8;
    --purple:#8a4bd2;--purple-soft:#f5effc;--purple-border:#e5d8f5;
    --cyan:#168eae;--cyan-soft:#edf8fb;--cyan-border:#d2eaf0;
    --amber:#c38418;--amber-soft:#fff7e8;--amber-border:#efdcb8;
    --red:#cf5050;--red-soft:#fff1f1;--red-border:#f1cccc;
    --slate:#64748b;--slate-soft:#f2f5f7;--slate-border:#dfe5e9;
    --text:var(--color-text,#17251c);--text2:var(--color-text-secondary,#58685e);
    --muted:var(--color-text-muted,#87938b);--border:var(--color-border,#d7e2da);
    --border-strong:var(--color-border-strong,#becdc3);--surface:var(--color-surface,#fff);
    --soft:var(--color-surface-soft,#f7faf8);--shadow:0 5px 18px rgba(25,61,39,.055);
    display:grid;width:min(100%,1380px);min-width:0;grid-column:1/-1;gap:.78rem;margin:0 auto;padding-bottom:1rem;color:var(--text)
}
.project-workspace *, .project-workspace *::before,.project-workspace *::after{box-sizing:border-box}

/* Header */
.project-head{--tone:var(--green);--tone-soft:var(--green-soft);display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.7rem;align-items:center;min-height:76px;padding:.75rem .8rem;border:1px solid var(--border);border-radius:12px;background:radial-gradient(circle at 100% 0,color-mix(in srgb,var(--tone) 10%,transparent),transparent 19rem),linear-gradient(180deg,#fbfdfb,#fff);box-shadow:var(--shadow)}
.project-head.status-draft{--tone:var(--amber);--tone-soft:var(--amber-soft)}
.project-head.status-completed{--tone:var(--blue);--tone-soft:var(--blue-soft)}
.project-head.status-cancelled{--tone:var(--red);--tone-soft:var(--red-soft)}
.head-start,.head-actions,.meta{display:flex;align-items:center}.head-start{min-width:0;gap:.65rem}.head-actions{gap:.36rem;justify-content:flex-end}.meta{min-width:0;flex-wrap:wrap;gap:.18rem .65rem;margin-top:.2rem;color:var(--muted);font-size:.7rem;font-weight:600}
.meta span{display:inline-flex;min-width:0;gap:.26rem;align-items:center}.meta i{color:var(--blue);font-size:.78rem}.meta-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.square-btn,.head-icon{display:grid;width:42px;height:42px;flex:0 0 auto;place-items:center;border-radius:9px}
.square-btn{border:1px solid var(--border);background:#fff;color:var(--text2);cursor:pointer;text-decoration:none;transition:.15s}
.square-btn:hover,.square-btn:focus-visible{border-color:var(--blue-border);background:var(--blue-soft);color:var(--blue);outline:0}
.head-icon{background:var(--tone-soft);color:var(--tone)}.head-icon i,.square-btn i{font-size:1.05rem}
.head-copy{min-width:0}.head-copy h1{margin:0;overflow:hidden;color:var(--text);font-size:clamp(1.03rem,2vw,1.25rem);font-weight:850;letter-spacing:-.03em;line-height:1.24;text-overflow:ellipsis;white-space:nowrap}
.project-status{display:inline-flex;min-height:32px;gap:.28rem;align-items:center;padding:.32rem .52rem;border:1px solid color-mix(in srgb,var(--tone) 15%,transparent);border-radius:999px;background:var(--tone-soft);color:var(--tone);font-size:.68rem;font-weight:800;white-space:nowrap}

/* Tabs */
.tabs-wrap{position:sticky;z-index:35;top:.2rem;min-width:0}.tabs{display:flex;gap:.22rem;padding:.34rem;overflow-x:auto;border:1px solid var(--border);border-radius:12px;background:rgba(255,255,255,.96);box-shadow:0 4px 15px rgba(25,61,39,.05);scrollbar-width:none}.tabs::-webkit-scrollbar{display:none}
.tab{--tone:var(--slate);--tone-soft:var(--slate-soft);--tone-border:var(--slate-border);display:inline-flex;min-width:max-content;min-height:39px;gap:.34rem;align-items:center;padding:.42rem .62rem;border:1px solid transparent;border-radius:8px;background:transparent;color:#66736b;cursor:pointer;font:inherit;font-size:.72rem;font-weight:760;white-space:nowrap}
.tab[data-section=summary]{--tone:var(--blue);--tone-soft:var(--blue-soft);--tone-border:var(--blue-border)}
.tab[data-section=limits]{--tone:var(--purple);--tone-soft:var(--purple-soft);--tone-border:var(--purple-border)}
.tab[data-section=prices]{--tone:var(--cyan);--tone-soft:var(--cyan-soft);--tone-border:var(--cyan-border)}
.tab[data-section=simulator]{--tone:var(--green);--tone-soft:var(--green-soft);--tone-border:var(--green-border)}
.tab[data-section=deliveries]{--tone:var(--amber);--tone-soft:var(--amber-soft);--tone-border:var(--amber-border)}
.tab[data-section=distributions]{--tone:var(--blue);--tone-soft:var(--blue-soft);--tone-border:var(--blue-border)}
.tab[data-section=receipts]{--tone:var(--purple);--tone-soft:var(--purple-soft);--tone-border:var(--purple-border)}
.tab[data-section=payments]{--tone:var(--green);--tone-soft:var(--green-soft);--tone-border:var(--green-border)}
.tab i{color:var(--tone);font-size:.92rem}.tab:hover,.tab:focus-visible{background:#f7f9f8;color:var(--tone);outline:0}.tab.active{border-color:var(--tone-border);background:var(--tone-soft);color:var(--tone);box-shadow:inset 0 -2px 0 color-mix(in srgb,var(--tone) 45%,transparent)}

/* Section shell */
.section-shell{min-width:0;overflow:hidden;border:1px solid var(--border);border-radius:12px;background:var(--surface);box-shadow:var(--shadow)}
.section-head{display:flex;min-height:62px;gap:.65rem;align-items:center;justify-content:space-between;padding:.65rem .72rem;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fafcfb,#fff)}
.section-title{display:flex;min-width:0;gap:.58rem;align-items:center}.section-title-icon{display:grid;width:39px;height:39px;flex:0 0 auto;place-items:center;border-radius:9px;background:var(--section-soft,var(--blue-soft));color:var(--section-tone,var(--blue))}
.section-copy{min-width:0}.section-copy h2,.section-copy p{margin:0}.section-copy h2{font-size:.92rem;font-weight:840;letter-spacing:-.02em}.section-copy p{margin-top:.08rem;color:var(--muted);font-size:.69rem;line-height:1.35}
.section-actions{display:flex;gap:.32rem;align-items:center}.section-count{display:inline-flex;min-height:29px;align-items:center;padding:.25rem .48rem;border-radius:999px;background:var(--slate-soft);color:var(--text2);font-size:.65rem;font-weight:780;white-space:nowrap}.section-body{padding:.7rem}
.guide{--tone:var(--blue);--tone-soft:var(--blue-soft);--tone-border:var(--blue-border);display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.62rem;padding:.52rem .6rem;border:1px solid var(--tone-border);border-radius:9px;background:var(--tone-soft);color:var(--text2);font-size:.68rem;line-height:1.42}.guide.limits{--tone:var(--purple);--tone-soft:var(--purple-soft);--tone-border:var(--purple-border)}.guide.prices{--tone:var(--cyan);--tone-soft:var(--cyan-soft);--tone-border:var(--cyan-border)}.guide.simulator,.guide.payments{--tone:var(--green);--tone-soft:var(--green-soft);--tone-border:var(--green-border)}.guide.deliveries{--tone:var(--amber);--tone-soft:var(--amber-soft);--tone-border:var(--amber-border)}.guide.receipts{--tone:var(--purple);--tone-soft:var(--purple-soft);--tone-border:var(--purple-border)}
.guide-icon{display:grid;width:28px;height:28px;flex:0 0 auto;place-items:center;border-radius:7px;background:#fff;color:var(--tone)}.guide strong{color:var(--text)}

/* Buttons / filters */
.btn,.pager-btn,.info-btn{display:inline-flex;min-height:38px;gap:.34rem;align-items:center;justify-content:center;padding:.42rem .62rem;border:1px solid var(--border-strong);border-radius:8px;background:#fff;color:var(--text);cursor:pointer;font:inherit;font-size:.7rem;font-weight:780;text-decoration:none}
.btn:hover,.btn:focus-visible,.pager-btn:hover:not(:disabled),.pager-btn:focus-visible:not(:disabled){border-color:var(--blue-border);background:var(--blue-soft);color:var(--blue);outline:0}.btn.primary{border-color:var(--green);background:linear-gradient(180deg,#25a95f,#1d914f);color:#fff}.info-btn{width:34px;min-height:34px;padding:0}
.filterbar{display:grid;grid-template-columns:minmax(220px,1fr) minmax(150px,190px);gap:.45rem;margin-bottom:.62rem}.filterbar.single{grid-template-columns:minmax(220px,1fr)}.searchbox{position:relative}.searchbox i{position:absolute;top:50%;left:.66rem;color:var(--muted);font-size:.86rem;transform:translateY(-50%);pointer-events:none}
.filter-input,.filter-select,.sim-control{width:100%;min-height:40px;border:1px solid var(--border-strong);border-radius:8px;outline:0;background:#fff;color:var(--text);font:inherit;font-size:.72rem}.filter-input{padding:.48rem .62rem .48rem 2rem}.filter-select,.sim-control{padding:.48rem .58rem}.filter-input:focus,.filter-select:focus,.sim-control:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(52,120,212,.1)}

/* Summary */
.overview{display:grid;grid-template-columns:minmax(300px,.92fr) minmax(0,1.08fr);gap:.62rem}.hero{--hero-tone:var(--green);display:grid;min-height:245px;align-content:space-between;gap:1rem;padding:.9rem;border:1px solid var(--green-border);border-radius:10px;background:radial-gradient(circle at 100% 0,rgba(33,150,83,.12),transparent 16rem),linear-gradient(145deg,#fff,var(--green-soft))}
.hero.is-warning{--hero-tone:var(--amber);border-color:var(--amber-border);background:radial-gradient(circle at 100% 0,rgba(195,132,24,.12),transparent 16rem),linear-gradient(145deg,#fff,var(--amber-soft))}.hero.is-danger{--hero-tone:var(--red);border-color:var(--red-border);background:radial-gradient(circle at 100% 0,rgba(207,80,80,.12),transparent 16rem),linear-gradient(145deg,#fff,var(--red-soft))}
.hero-kicker{display:inline-flex;gap:.32rem;align-items:center;color:var(--hero-tone);font-size:.72rem;font-weight:820}.hero-value{margin-top:.34rem;font-size:clamp(2rem,5vw,2.8rem);font-weight:880;letter-spacing:-.05em;line-height:1}.hero-helper{max-width:460px;margin-top:.4rem;color:var(--text2);font-size:.74rem;line-height:1.5}.hero-footer{display:flex;flex-wrap:wrap;gap:.38rem;align-items:center;justify-content:space-between}.hero-link{display:inline-flex;min-height:36px;gap:.34rem;align-items:center;padding:.4rem .56rem;border:1px solid color-mix(in srgb,var(--hero-tone) 24%,var(--border));border-radius:8px;background:rgba(255,255,255,.74);color:var(--hero-tone);font-size:.68rem;font-weight:800;text-decoration:none}.hero-mini{color:var(--muted);font-size:.65rem}.hero-mini strong{color:var(--text)}
.overview-side{display:grid;grid-template-rows:auto 1fr;gap:.62rem}.kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.42rem}.kpi{--tone:var(--blue);padding:.58rem .6rem;border:1px solid color-mix(in srgb,var(--tone) 13%,var(--border));border-radius:9px;background:#fff;min-width:0}.kpi.purple{--tone:var(--purple)}.kpi.amber{--tone:var(--amber)}.kpi.green{--tone:var(--green)}.kpi span,.kpi strong{display:block}.kpi span{color:var(--muted);font-size:.61rem;font-weight:720}.kpi strong{margin-top:.1rem;overflow:hidden;color:var(--tone);font-size:.78rem;font-weight:850;text-overflow:ellipsis;white-space:nowrap}
.charts{display:grid;grid-template-columns:minmax(150px,.62fr) minmax(0,1.38fr);gap:.42rem}.chart{padding:.65rem;border:1px solid var(--border);border-radius:9px;background:#fff}.chart-title{color:var(--text2);font-size:.64rem;font-weight:800;text-transform:uppercase;letter-spacing:.035em}.donut-wrap{display:grid;min-height:135px;place-items:center;padding-top:.35rem}.donut{--pct:0;display:grid;width:112px;height:112px;place-items:center;border-radius:50%;background:radial-gradient(circle at center,#fff 0 57%,transparent 58%),conic-gradient(var(--donut-tone,var(--green)) calc(var(--pct)*1%),#e8eee9 0)}.donut strong,.donut span{display:block;text-align:center}.donut strong{font-size:1.02rem;font-weight:860}.donut span{margin-top:.2rem;color:var(--muted);font-size:.58rem;font-weight:700}
.bar-chart{display:grid;gap:.58rem;padding-top:.65rem}.bar-row{display:grid;grid-template-columns:64px minmax(0,1fr) auto;gap:.42rem;align-items:center}.bar-label{color:var(--text2);font-size:.63rem;font-weight:740}.bar-track{height:10px;overflow:hidden;border-radius:4px;background:#edf1ee}.bar-fill{display:block;height:100%;min-width:2px;border-radius:inherit;background:var(--bar-tone,var(--blue))}.bar-value{min-width:72px;font-size:.63rem;font-weight:820;text-align:right;white-space:nowrap}
.metric-table{margin-top:.62rem;overflow:hidden;border:1px solid var(--border);border-radius:9px}.metric-row{display:grid;grid-template-columns:34px minmax(0,1fr) auto;gap:.5rem;align-items:center;min-height:54px;padding:.46rem .6rem;background:#fff}.metric-row+.metric-row{border-top:1px solid var(--border)}.metric-icon{display:grid;width:30px;height:30px;place-items:center;border-radius:7px;background:var(--metric-soft,var(--blue-soft));color:var(--metric-tone,var(--blue))}.metric-copy span,.metric-copy strong{display:block}.metric-copy span{color:var(--muted);font-size:.62rem;font-weight:710}.metric-copy strong{font-size:.72rem;font-weight:790;line-height:1.35}.metric-value{color:var(--metric-tone,var(--text));font-size:.77rem;font-weight:850;text-align:right;white-space:nowrap}.fee-breakdown{grid-column:2/-1;display:flex;flex-wrap:wrap;gap:.25rem}.fee-chip{display:inline-flex;gap:.25rem;padding:.18rem .34rem;border:1px solid var(--border);border-radius:6px;background:var(--soft);color:var(--text2);font-size:.61rem;font-weight:700}.fee-chip strong{color:var(--text)}

/* Tables */
.data-table{overflow:hidden;border:1px solid var(--border);border-radius:9px;background:#fff}.table-head,.table-row{display:grid;gap:.42rem;align-items:center}.table-head{min-height:38px;padding:.35rem .58rem;border-bottom:1px solid var(--border-strong);background:linear-gradient(180deg,#f5f8f6,#eff4f1);color:#6f7c74;font-size:.58rem;font-weight:820;text-transform:uppercase;letter-spacing:.045em}.table-row{min-height:58px;padding:.48rem .58rem;border-bottom:1px solid var(--border);background:#fff}.table-row:last-child{border-bottom:0}.table-row:hover{background:#fafcfb}
.limits-table .table-head,.limits-table .table-row{grid-template-columns:minmax(190px,1.3fr) 105px 100px 100px 100px minmax(130px,.9fr)}.deliveries-table .table-head,.deliveries-table .table-row{grid-template-columns:90px minmax(150px,1.2fr) 100px 110px 110px 110px}.distributions-table .table-head,.distributions-table .table-row{grid-template-columns:90px minmax(140px,.9fr) minmax(170px,1.2fr) 100px 105px 110px 110px}.receipts-table .table-head,.receipts-table .table-row{grid-template-columns:100px minmax(145px,1fr) 105px 110px 110px 110px 110px}.payments-table .table-head,.payments-table .table-row{grid-template-columns:105px minmax(160px,1fr) minmax(135px,.7fr) 130px}
.table-cell{min-width:0;color:var(--text2);font-size:.69rem;line-height:1.35}.table-cell strong{color:var(--text);font-size:.72rem;font-weight:800}.table-cell.value{font-variant-numeric:tabular-nums;font-weight:780;text-align:right;white-space:nowrap}.table-head>span:not(:first-child){text-align:right}.table-head>span:nth-child(2){text-align:left}
.product-cell{display:flex;min-width:0;gap:.45rem;align-items:center}.product-icon{display:grid;width:32px;height:32px;flex:0 0 auto;place-items:center;border-radius:7px;background:var(--row-soft,var(--green-soft));color:var(--row-tone,var(--green))}.product-copy{min-width:0}.product-copy strong,.product-copy span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.product-copy span{margin-top:.08rem;color:var(--muted);font-size:.6rem}
.progress-cell{min-width:0}.tiny-progress{height:7px;overflow:hidden;border-radius:3px;background:#e8eeea}.tiny-progress span{display:block;height:100%;border-radius:inherit;background:var(--row-tone,var(--green))}.progress-caption{display:flex;justify-content:space-between;gap:.3rem;margin-top:.18rem;color:var(--muted);font-size:.57rem;font-weight:700}.row-warning{--row-tone:var(--amber);--row-soft:var(--amber-soft)}.row-danger{--row-tone:var(--red);--row-soft:var(--red-soft)}
.status-badge{display:inline-flex;width:max-content;min-height:23px;align-items:center;padding:.18rem .36rem;border-radius:999px;background:var(--slate-soft);color:var(--slate);font-size:.59rem;font-weight:800;white-space:nowrap}.status-badge.pending{background:var(--amber-soft);color:#975f0f}.status-badge.approved,.status-badge.paid,.status-badge.completed{background:var(--green-soft);color:var(--green)}.status-badge.rejected,.status-badge.cancelled{background:var(--red-soft);color:#a43d3d}.status-badge.obsolete{background:var(--slate-soft);color:#536273}.row-extra{grid-column:1/-1;display:flex;flex-wrap:wrap;gap:.3rem}.note-btn{display:inline-flex;min-height:31px;gap:.28rem;align-items:center;padding:.3rem .45rem;border:1px solid var(--border);border-radius:7px;background:var(--soft);color:var(--text2);cursor:pointer;font:inherit;font-size:.62rem;font-weight:750}
.record-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(285px,1fr));gap:.5rem}.record-card{--record-tone:var(--green);overflow:hidden;border:1px solid var(--border);border-top:3px solid var(--record-tone);border-radius:10px;background:#fff;box-shadow:0 3px 12px rgba(18,42,27,.045)}.record-card.warning{--record-tone:var(--amber)}.record-card.danger{--record-tone:var(--red)}.record-card.distribution{--record-tone:var(--blue)}.record-head{display:grid;grid-template-columns:36px minmax(0,1fr) auto;gap:.48rem;align-items:center;padding:.58rem .62rem;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fff,#fafcfb)}.record-icon{display:grid;width:36px;height:36px;place-items:center;border-radius:8px;background:color-mix(in srgb,var(--record-tone) 10%,#fff);color:var(--record-tone);font-size:.92rem}.record-title{min-width:0}.record-title strong,.record-title span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.record-title strong{font-size:.75rem;font-weight:830}.record-title span{margin-top:.08rem;color:var(--muted);font-size:.59rem}.record-date{color:var(--text2);font-size:.62rem;font-weight:760;white-space:nowrap}.record-body{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--border)}.record-stat{min-width:0;padding:.48rem .58rem;background:#fff}.record-stat span,.record-stat strong{display:block}.record-stat span{color:var(--muted);font-size:.56rem;font-weight:740;text-transform:uppercase}.record-stat strong{margin-top:.1rem;overflow:hidden;color:var(--text);font-size:.7rem;font-weight:820;text-overflow:ellipsis;white-space:nowrap}.record-stat.emphasis strong{color:var(--record-tone);font-size:.78rem}.record-foot{display:flex;flex-wrap:wrap;gap:.3rem;align-items:center;padding:.46rem .58rem;border-top:1px solid var(--border);background:var(--soft)}.record-foot:empty{display:none}

/* Prices */
.price-list{display:grid;gap:.42rem}.price-card{overflow:hidden;border:1px solid var(--border);border-radius:9px;background:#fff}.price-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.55rem;align-items:center;padding:.54rem .58rem;border-bottom:1px solid var(--border);background:#fbfdfc}.price-product strong,.price-product span{display:block}.price-product strong{font-size:.75rem;font-weight:820}.price-product span{margin-top:.08rem;color:var(--muted);font-size:.61rem}.price-range{color:var(--green);font-size:.75rem;font-weight:850;white-space:nowrap}.price-destination{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.5rem;align-items:center;min-height:40px;padding:.38rem .58rem;color:var(--text2);font-size:.66rem}.price-destination+.price-destination{border-top:1px solid var(--border)}.price-destination strong{font-size:.68rem;white-space:nowrap}.price-destination small{color:var(--muted)}

/* Simulator */
.sim-summary{position:sticky;z-index:8;top:4.15rem;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.38rem;margin-bottom:.55rem;padding:.5rem;border:1px solid var(--green-border);border-radius:9px;background:rgba(255,255,255,.96);box-shadow:var(--shadow)}.sim-summary.is-over{border-color:var(--red-border)}.sim-kpi{padding:.38rem .42rem;border-radius:7px;background:var(--soft)}.sim-kpi span,.sim-kpi strong{display:block}.sim-kpi span{color:var(--muted);font-size:.58rem;font-weight:720}.sim-kpi strong{margin-top:.08rem;font-size:.76rem;font-weight:850}.sim-kpi.emphasis strong{color:var(--green)}.sim-summary.is-over .sim-kpi.emphasis strong{color:var(--red)}.sim-progress{grid-column:1/-1;height:6px;overflow:hidden;border-radius:3px;background:#e8eeea}.sim-progress span{display:block;height:100%;background:var(--green)}.sim-summary.is-over .sim-progress span{background:var(--red)}
.sim-actions{display:flex;flex-wrap:wrap;gap:.38rem;align-items:center;margin-bottom:.55rem}.sim-hint{margin-right:auto;color:var(--muted);font-size:.64rem}.sim-picker{margin-bottom:.55rem;padding:.55rem;border:1px solid var(--border);border-radius:9px;background:var(--soft)}.sim-picker[hidden]{display:none}.sim-picker-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:.3rem;margin-top:.4rem}.sim-picker-group+.sim-picker-group{margin-top:.62rem;padding-top:.55rem;border-top:1px solid var(--border)}.sim-picker-group-head{margin-bottom:.34rem}.sim-picker-group-head strong,.sim-picker-group-head span{display:block}.sim-picker-group-head strong{font-size:.68rem}.sim-picker-group-head span{margin-top:.05rem;color:var(--muted);font-size:.59rem}.sim-picker-item{display:flex;min-width:0;gap:.38rem;align-items:center;padding:.44rem .48rem;border:1px solid var(--border);border-radius:7px;background:#fff;color:var(--text);cursor:pointer;font:inherit;text-align:left}.sim-picker-item:disabled{opacity:.45;cursor:default}.sim-picker-item strong,.sim-picker-item small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.sim-picker-item strong{font-size:.67rem}.sim-picker-item small{margin-top:.06rem;color:var(--muted);font-size:.57rem}
.sim-list{display:grid;gap:.42rem}.sim-row{display:grid;grid-template-columns:minmax(170px,1.15fr) minmax(170px,.9fr) minmax(130px,.7fr) auto;gap:.48rem;align-items:end;padding:.58rem;border:1px solid var(--border);border-radius:9px;background:#fff}.sim-product{display:flex;min-width:0;gap:.42rem;align-items:center;align-self:center}.sim-product-icon{display:grid;width:33px;height:33px;flex:0 0 auto;place-items:center;border-radius:7px;background:var(--green-soft);color:var(--green)}.sim-product-copy{min-width:0}.sim-product-copy strong,.sim-product-copy span{display:block}.sim-product-copy strong{overflow:hidden;font-size:.7rem;text-overflow:ellipsis;white-space:nowrap}.sim-product-copy span{margin-top:.05rem;color:var(--muted);font-size:.59rem}.sim-tag{display:inline-flex!important;width:max-content;margin-top:.16rem!important;padding:.1rem .27rem;border-radius:999px;background:var(--purple-soft);color:var(--purple)!important;font-size:.54rem!important;font-weight:780}.sim-tag.extra{background:var(--slate-soft);color:var(--slate)!important}.sim-field label{display:block;margin-bottom:.14rem;color:var(--muted);font-size:.58rem;font-weight:720}.sim-qty{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.28rem}.sim-fill,.sim-remove{min-height:40px;border:1px solid var(--border);border-radius:7px;background:#fff;cursor:pointer;font:inherit}.sim-fill{padding:.4rem .48rem;color:var(--green);font-size:.62rem;font-weight:780}.sim-remove{width:38px;color:var(--red)}.sim-total{display:block;margin-bottom:.28rem;color:var(--green);font-size:.72rem;font-weight:850;text-align:right}.sim-note{grid-column:1/-1;margin-top:-.18rem;color:var(--muted);font-size:.59rem}.sim-note.warning{color:var(--amber)}

/* Pager / state / dialog */
.pager{display:grid;width:max-content;max-width:100%;grid-template-columns:auto auto auto;gap:.32rem;align-items:center;margin:.65rem auto 0;padding:.3rem;border:1px solid var(--border);border-radius:9px;background:var(--soft)}.pager-btn{min-width:96px;min-height:36px}.pager-btn:disabled{opacity:.42;cursor:not-allowed}.pager-label{display:grid;min-height:36px;place-items:center;padding:.28rem .5rem;border-radius:7px;background:#fff;color:var(--text2);font-size:.66rem;font-weight:770}
.state-box{display:grid;min-height:210px;place-items:center;padding:1.2rem .8rem;border-radius:9px;background:var(--soft);text-align:center}.state-icon{display:grid;width:50px;height:50px;place-items:center;margin:0 auto .5rem;border-radius:10px;background:var(--slate-soft);color:var(--slate)}.state-box.error .state-icon{background:var(--red-soft);color:var(--red)}.state-box strong,.state-box p{display:block}.state-box strong{font-size:.78rem;font-weight:820}.state-box p{max-width:390px;margin:.18rem auto 0;color:var(--text2);font-size:.68rem;line-height:1.48}.state-box .btn{margin-top:.55rem}
.skeleton-list{display:grid;gap:.45rem}.skeleton{position:relative;height:64px;overflow:hidden;border-radius:8px;background:#e9efeb}.skeleton::after{display:block;width:45%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.72),transparent);content:"";animation:skeleton 1.05s infinite}@keyframes skeleton{from{transform:translateX(-120%)}to{transform:translateX(245%)}}
.info-dialog{position:fixed;z-index:2500;inset:0;width:100%;max-width:none;height:100%;max-height:none;margin:0;padding:max(16px,env(safe-area-inset-top)) max(14px,env(safe-area-inset-right)) max(16px,env(safe-area-inset-bottom)) max(14px,env(safe-area-inset-left));overflow:auto;border:0;background:transparent}.info-dialog:not([open]){display:none}.info-dialog[open]{display:grid;place-items:center}.info-dialog::backdrop{background:rgba(9,27,16,.55)}.dialog-panel{width:min(100%,440px);overflow:hidden;border:1px solid var(--border);border-radius:11px;background:#fff;box-shadow:0 24px 62px rgba(8,24,15,.23)}.dialog-head{display:grid;grid-template-columns:36px minmax(0,1fr) 34px;gap:.5rem;align-items:center;padding:.65rem;border-bottom:1px solid var(--border);background:linear-gradient(180deg,#fafcfb,#fff)}.dialog-icon{display:grid;width:36px;height:36px;place-items:center;border-radius:8px;background:var(--blue-soft);color:var(--blue)}.dialog-head h2{margin:0;font-size:.8rem;font-weight:840}.dialog-close{display:grid;width:34px;height:34px;place-items:center;border:1px solid var(--border);border-radius:8px;background:#fff;color:var(--text2);cursor:pointer}.dialog-body{padding:.76rem;color:var(--text2);font-size:.72rem;line-height:1.56;white-space:pre-wrap}.dialog-foot{display:flex;justify-content:flex-end;padding:.58rem .7rem .68rem;border-top:1px solid var(--border);background:var(--soft)}

@media(max-width:1050px){.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.limits-table .table-head,.limits-table .table-row{grid-template-columns:minmax(180px,1.2fr) 95px 90px 90px minmax(125px,.8fr)}.limits-table .limit-col{display:none}}
@media(max-width:900px){.overview{grid-template-columns:1fr}.hero{min-height:205px}.deliveries-table .table-head,.deliveries-table .table-row{grid-template-columns:80px minmax(140px,1fr) 95px 100px 100px}.deliveries-table .net-col{display:none}.distributions-table .table-head,.distributions-table .table-row{grid-template-columns:80px minmax(125px,.8fr) minmax(145px,1fr) 95px 100px 105px}.distributions-table .receipt-col{display:none}.receipts-table .table-head,.receipts-table .table-row{grid-template-columns:90px minmax(130px,1fr) 95px 100px 100px 105px}.receipts-table .action-col{display:none}.sim-row{grid-template-columns:minmax(0,1fr) minmax(150px,.85fr)}}
@media(max-width:700px){
    .project-head{grid-template-columns:1fr}.head-actions{justify-content:flex-start;flex-wrap:wrap}.project-status{margin-right:auto}.section-copy p{display:none}.filterbar,.filterbar.single{grid-template-columns:1fr}.charts{grid-template-columns:1fr}.metric-row{grid-template-columns:32px minmax(0,1fr)}.metric-value{grid-column:2;justify-self:start;text-align:left}.fee-breakdown{grid-column:2}
    .data-table{overflow:visible;border:0;background:transparent}.table-head{display:none}.table-row,.limits-table .table-row,.deliveries-table .table-row,.distributions-table .table-row,.receipts-table .table-row,.payments-table .table-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.4rem;margin-bottom:.45rem;padding:.55rem;border:1px solid var(--border);border-left:3px solid var(--row-tone,var(--blue));border-radius:9px;background:#fff}.table-row:last-child{margin-bottom:0;border-bottom:1px solid var(--border)}.table-cell,.table-cell.value{display:grid;gap:.06rem;text-align:left;white-space:normal}.table-cell::before{color:var(--muted);content:attr(data-label);font-size:.56rem;font-weight:760;text-transform:uppercase}.table-cell.product-wide,.progress-cell,.row-extra{grid-column:1/-1}.product-wide::before{display:none}.limits-table .limit-col,.deliveries-table .net-col,.distributions-table .receipt-col,.receipts-table .action-col{display:grid}
    .sim-summary{top:4rem;grid-template-columns:1fr 1fr}.sim-kpi.emphasis{grid-column:1/-1}.sim-row{grid-template-columns:minmax(0,1fr) auto}.sim-row .sim-field{grid-column:1/-1}.sim-remove{grid-column:2;grid-row:1}.sim-total{grid-column:1;grid-row:1;align-self:center;margin:0;text-align:left}
}
@media(max-width:460px){.head-icon{display:none}.head-copy h1{white-space:normal}.section-body{padding:.58rem}.kpis{gap:.3rem}.bar-row{grid-template-columns:58px minmax(0,1fr)}.bar-value{grid-column:2;text-align:left}.table-row,.limits-table .table-row,.deliveries-table .table-row,.distributions-table .table-row,.receipts-table .table-row,.payments-table .table-row{grid-template-columns:1fr}.table-cell.product-wide,.progress-cell,.row-extra{grid-column:1}.pager{width:100%;grid-template-columns:1fr 1fr}.pager-label{grid-column:1/-1;grid-row:1}}
@media(prefers-reduced-motion:reduce){.project-workspace *{animation-duration:.01ms!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}
</style>

<main class="project-workspace" id="associate-workspace">
    <header class="project-head status-{{ $projectStatusValue }}" aria-labelledby="workspace-title">
        <div class="head-start">
            <a class="square-btn" href="{{ route('associate.projects', ['tenant' => $tenantSlug]) }}" aria-label="Voltar aos projetos" title="Voltar aos projetos">
                <i class="ph ph-arrow-left"></i>
            </a>

            <span class="head-icon" aria-hidden="true">
                <i class="ph-fill ph-folder-open"></i>
            </span>

            <div class="head-copy">
                <h1 id="workspace-title">{{ $project->title }}</h1>

                <div class="meta">
                    <span>
                        <i class="ph ph-user-circle"></i>
                        <span class="meta-text">{{ $associate->display_name }}</span>
                    </span>

                    @if($projectPeriod)
                        <span>
                            <i class="ph ph-calendar-dots"></i>
                            <span class="meta-text">{{ $projectPeriod }}</span>
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="head-actions">
            <span class="project-status">
                <i class="ph-fill {{ $projectStatusIcon }}"></i>
                {{ $projectStatusLabel }}
            </span>

            <a class="square-btn" href="{{ $projectQuotaShareUrl }}" aria-label="Compartilhar cotas reais" title="Compartilhar cotas reais">
                <i class="ph ph-share-network"></i>
            </a>

            <a class="square-btn" href="{{ $projectSimulatorUrl }}" aria-label="Abrir simulador em página própria" title="Abrir simulador em página própria">
                <i class="ph ph-calculator"></i>
            </a>

            <button class="square-btn" type="button" onclick="awRefresh()" aria-label="Atualizar dados" title="Atualizar dados">
                <i class="ph ph-arrows-clockwise"></i>
            </button>
        </div>
    </header>

    <div class="tabs-wrap">
        <nav class="tabs" aria-label="Seções do projeto" role="tablist">
            <button class="tab active" type="button" data-section="summary" role="tab" aria-selected="true"><i class="ph-fill ph-chart-donut"></i><span>Resumo</span></button>
            <button class="tab" type="button" data-section="limits" role="tab" aria-selected="false"><i class="ph-fill ph-gauge"></i><span>Limites</span></button>
            <button class="tab" type="button" data-section="prices" role="tab" aria-selected="false"><i class="ph-fill ph-tag"></i><span>Preços</span></button>
            <button class="tab" type="button" data-section="simulator" role="tab" aria-selected="false"><i class="ph-fill ph-calculator"></i><span>Simular</span></button>
            <button class="tab" type="button" data-section="deliveries" role="tab" aria-selected="false"><i class="ph-fill ph-package"></i><span>Entregas</span></button>
            <button class="tab" type="button" data-section="distributions" role="tab" aria-selected="false"><i class="ph-fill ph-map-pin"></i><span>Destinos</span></button>
            <button class="tab" type="button" data-section="receipts" role="tab" aria-selected="false"><i class="ph-fill ph-receipt"></i><span>Comprovantes</span></button>
            <button class="tab" type="button" data-section="payments" role="tab" aria-selected="false"><i class="ph-fill ph-wallet"></i><span>Pagamentos</span></button>
        </nav>
    </div>

    <section class="project-content" id="aw-content" aria-live="polite" aria-busy="true">
        <section class="section-shell"><div class="section-body"><div class="skeleton-list">@for($index = 0; $index < 5; $index++)<div class="skeleton"></div>@endfor</div></div></section>
    </section>
</main>

<dialog class="info-dialog" id="workspace-info-dialog" aria-labelledby="workspace-info-title">
    <div class="dialog-panel">
        <header class="dialog-head">
            <span class="dialog-icon"><i class="ph-fill ph-info"></i></span>
            <h2 id="workspace-info-title">Sobre esta informação</h2>
            <button type="button" class="dialog-close" id="workspace-info-close" aria-label="Fechar"><i class="ph ph-x"></i></button>
        </header>
        <div class="dialog-body" id="workspace-info-body"></div>
        <footer class="dialog-foot"><button type="button" class="btn" id="workspace-info-confirm">Entendi</button></footer>
    </div>
</dialog>
@endsection

@push('scripts')
<script>
(() => {
    const AW_BASE = @json(url('/'.$tenantSlug.'/associate/projects/'.$project->id));
    const AW_SIMULATOR_URL = @json($projectSimulatorUrl);
    const root = document.getElementById('aw-content');
    const dialog = document.getElementById('workspace-info-dialog');
    const dialogTitle = document.getElementById('workspace-info-title');
    const dialogBody = document.getElementById('workspace-info-body');

    const state = {
        section:'summary', page:1, abort:null, timer:null,
        filters:{
            prices:{search:''},
            deliveries:{search:'',status:''},
            distributions:{search:'',status:''},
        },
        simulator:{
            products:[], rows:[], initialized:false, pickerOpen:false,
            pickerSearch:'', summary:{}, catalogTruncated:false, deliveryEnabledTotal:0,
        },
    };

    const sections = {
        summary:{title:'Resumo do projeto',subtitle:'Visão rápida dos valores da sua participação.',icon:'ph-chart-donut',tone:'var(--blue)',soft:'var(--blue-soft)'},
        limits:{title:'Produtos e limites',subtitle:'Entregue, limite, saldo e uso por produto.',icon:'ph-gauge',tone:'var(--purple)',soft:'var(--purple-soft)'},
        prices:{title:'Tabela de preços',subtitle:'Preço por produto e destino.',icon:'ph-tag',tone:'var(--cyan)',soft:'var(--cyan-soft)'},
        simulator:{title:'Simulação',subtitle:'Planeje quantidades sem alterar dados do projeto.',icon:'ph-calculator',tone:'var(--green)',soft:'var(--green-soft)'},
        deliveries:{title:'Minhas entregas',subtitle:'Registros físicos e situação das quantidades.',icon:'ph-package',tone:'var(--amber)',soft:'var(--amber-soft)'},
        distributions:{title:'Entregas por destino',subtitle:'Para onde cada quantidade foi destinada.',icon:'ph-map-pin',tone:'var(--blue)',soft:'var(--blue-soft)'},
        receipts:{title:'Comprovantes',subtitle:'Documentos financeiros gerados.',icon:'ph-receipt',tone:'var(--purple)',soft:'var(--purple-soft)'},
        payments:{title:'Pagamentos',subtitle:'Valores já registrados como pagos.',icon:'ph-wallet',tone:'var(--green)',soft:'var(--green-soft)'},
    };

    const info = {
        summary:['Como ler o resumo','O resumo apresenta somente valores financeiros. Quantidades físicas não são somadas porque produtos podem usar unidades diferentes, como kg, unidades, litros ou maços.'],
        limits:['Como ler os limites','Cada linha mostra o produto, preço de referência, quantidade entregue, limite, saldo e percentual usado. Amarelo sinaliza aproximação do limite e vermelho indica limite atingido.'],
        prices:['Sobre os preços','O preço definitivo depende do destino da distribuição. Quando o mesmo produto possui preços diferentes, os destinos são apresentados separadamente.'],
        simulator:['Como usar a simulação','A simulação utiliza o saldo financeiro disponível e os preços atuais do projeto. Ela serve apenas para planejamento e não registra entrega nem altera limites.'],
        deliveries:['Sobre as entregas','Cada linha representa uma entrega física. A quantidade destinada é a parcela já distribuída para um cliente; o restante ainda aguarda destino.'],
        distributions:['Sobre os destinos','Cada registro representa uma parte de uma entrega vinculada a um cliente. É nesta etapa que preço, valor bruto e comprovante passam a ter significado financeiro.'],
        receipts:['Sobre os comprovantes','O comprovante reúne distribuições processadas. O valor líquido é o valor bruto após taxas, descontos ou acréscimos.'],
        payments:['Sobre os pagamentos','Esta seção mostra somente pagamentos já registrados e vinculados aos comprovantes deste projeto.'],
    };

    const money = v => Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
    const qty = v => Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:0,maximumFractionDigits:3});
    const pct = v => Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})+'%';
    const esc = v => String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const clamp = v => Math.max(0,Math.min(100,Number(v||0)));
    const toneClass = v => Number(v||0)>=100?'row-danger':Number(v||0)>=80?'row-warning':'';
    const heroTone = v => Number(v||0)>=100?'is-danger':Number(v||0)>=80?'is-warning':'';

    function badge(status,label){
        return `<span class="status-badge ${esc(status)}">${esc(label||status||'-')}</span>`;
    }

    function sectionHead(count='',section=state.section){
        const s=sections[section]||sections.summary;
        return `<header class="section-head" style="--section-tone:${s.tone};--section-soft:${s.soft}">
            <div class="section-title"><span class="section-title-icon"><i class="ph-fill ${esc(s.icon)}"></i></span>
            <div class="section-copy"><h2>${esc(s.title)}</h2><p>${esc(s.subtitle)}</p></div></div>
            <div class="section-actions">${count?`<span class="section-count">${esc(count)}</span>`:''}
            <button type="button" class="info-btn" onclick="awOpenInfo('${esc(section)}')" aria-label="Explicar esta seção"><i class="ph ph-info"></i></button></div>
        </header>`;
    }

    function guide(section){
        const all={
            limits:['ph-gauge','Leitura rápida:','veja entregue, saldo e percentual usado em cada produto.'],
            prices:['ph-tag','Preço por destino:','o valor final usa o preço do cliente que recebeu a distribuição.'],
            simulator:['ph-calculator','Planejamento:','teste produtos, destinos e quantidades sem registrar nenhuma entrega.'],
            deliveries:['ph-path','Fluxo:','entrega física → destino → valor financeiro → comprovante → pagamento.'],
            distributions:['ph-map-trifold','Destinos:','cada linha mostra uma quantidade já vinculada a um cliente.'],
            receipts:['ph-receipt','Comprovantes:','compare bruto, ajustes e líquido sem abrir cada documento.'],
            payments:['ph-check-circle','Pagamentos:','aparecem apenas valores já registrados como pagos.'],
        };
        const g=all[section]; if(!g)return '';
        return `<div class="guide ${esc(section)}"><span class="guide-icon"><i class="ph-fill ${g[0]}"></i></span><div><strong>${g[1]}</strong> ${g[2]}</div></div>`;
    }

    const loading=()=>`<section class="section-shell"><div class="section-body"><div class="skeleton-list">${Array.from({length:5},()=>'<div class="skeleton"></div>').join('')}</div></div></section>`;
    const empty=(title,text,icon='ph-inbox')=>`<div class="state-box"><div><span class="state-icon"><i class="ph-fill ${esc(icon)}"></i></span><strong>${esc(title)}</strong><p>${esc(text)}</p></div></div>`;
    const errorBox=message=>`<section class="section-shell"><div class="section-body"><div class="state-box error"><div><span class="state-icon"><i class="ph-fill ph-warning-circle"></i></span><strong>Não foi possível carregar esta seção</strong><p>${esc(message)}</p><button class="btn primary" type="button" onclick="awRefresh()"><i class="ph ph-arrows-clockwise"></i>Tentar novamente</button></div></div></div></section>`;

    function openInfo(section){
        const item=info[section]||info.summary;
        dialogTitle.textContent=item[0]; dialogBody.textContent=item[1]; dialog.showModal();
    }
    window.awOpenInfo=openInfo;
    document.getElementById('workspace-info-close')?.addEventListener('click',()=>dialog.close());
    document.getElementById('workspace-info-confirm')?.addEventListener('click',()=>dialog.close());
    dialog?.addEventListener('click',e=>{if(e.target===dialog)dialog.close()});

    root?.addEventListener('click',e=>{
        const b=e.target.closest('[data-aw-note]'); if(!b)return;
        const note=String(b.dataset.awNote||'').trim(); if(!note)return;
        dialogTitle.textContent=b.dataset.awNoteTitle||'Observações';
        const meta=String(b.dataset.awNoteMeta||'').trim();
        dialogBody.textContent=meta?`${meta}\n\n${note}`:note;
        dialog.showModal();
    });

    document.querySelectorAll('.tab[data-section]').forEach(button=>{
        button.addEventListener('click',()=>setSection(button.dataset.section));
    });

    function setSection(section,options={}){
        if(!sections[section])return;
        state.section=section; state.page=1;
        document.querySelectorAll('.tab[data-section]').forEach(button=>{
            const active=button.dataset.section===section;
            button.classList.toggle('active',active);
            button.setAttribute('aria-selected',active?'true':'false');
            active?button.setAttribute('aria-current','page'):button.removeAttribute('aria-current');
            if(active&&innerWidth<760)button.scrollIntoView({behavior:'smooth',inline:'center',block:'nearest'});
        });
        if(!options.skipHash)history.replaceState(null,'',`#${section}`);
        load();
        document.getElementById('associate-workspace')?.scrollIntoView({behavior:options.instant?'auto':'smooth',block:'start'});
    }
    window.awSetSection=setSection;

    async function api(url){
        const response=await fetch(url,{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'},signal:state.abort?.signal});
        const data=await response.json().catch(()=>({message:'O servidor retornou uma resposta inválida.'}));
        if(!response.ok)throw new Error(data.message||'Não foi possível carregar os dados.');
        return data;
    }

    function params(){
        const filters=state.filters[state.section]||{};
        const p=new URLSearchParams({page:String(state.page)});
        if(filters.search)p.set('search',filters.search);
        if(filters.status)p.set('status',filters.status);
        return p.toString();
    }

    async function load(){
        state.abort?.abort(); state.abort=new AbortController();
        root.setAttribute('aria-busy','true'); root.innerHTML=loading();
        try{
            const data=await api(`${AW_BASE}/data/${state.section}?${params()}`);
            ({summary:renderSummary,limits:renderLimits,prices:renderPrices,simulator:renderSimulator,deliveries:renderDeliveries,distributions:renderDistributions,receipts:renderReceipts,payments:renderPayments}[state.section]||renderSummary)(data);
        }catch(e){if(e.name!=='AbortError')root.innerHTML=errorBox(e.message)}
        finally{root.setAttribute('aria-busy','false')}
    }

    function refresh(){load()}
    window.awRefresh=refresh;

    function renderSummary(data){
        const raw=Number(data.financial_percent||0), percent=clamp(raw);
        const hasLimit=data.financial_limit!==null&&data.financial_limit!==undefined;
        const committed=Number(data.financial_consumed||0);
        const available=data.financial_remaining==null?null:Number(data.financial_remaining||0);
        const gross=Number(data.total_gross||0), net=Number(data.total_net||0), paid=Number(data.paid||0);
        const receivable=Math.max(0,net-paid), adjustment=net-gross, adjValue=Math.abs(adjustment);
        const adjSignal=adjustment<-.005?'-':adjustment>.005?'+':'';
        const adjLabel=adjustment<-.005?'taxas e descontos':adjustment>.005?'acréscimos e ajustes':'sem ajustes';
        const chartMax=Math.max(gross,net,paid,1), gW=gross/chartMax*100,nW=net/chartMax*100,pW=paid/chartMax*100;
        const donutTone=raw>=100?'var(--red)':raw>=80?'var(--amber)':'var(--green)';
        const breakdown=Array.isArray(data.fee_breakdown)?data.fee_breakdown.filter(f=>Math.abs(Number(f.amount||0))>.0005):[];
        const breakdownHtml=breakdown.length?`<div class="fee-breakdown">${breakdown.map(f=>`<span class="fee-chip">${esc(f.name||f.label||'Taxa ou desconto')} <strong>${f.nature==='accrual'?'+':'-'}${money(Math.abs(Number(f.amount||0)))}</strong></span>`).join('')}</div>`:'';

        root.innerHTML=`<section class="section-shell">${sectionHead('','summary')}<div class="section-body">
            <div class="overview">
                <article class="hero ${heroTone(raw)}">
                    <div><div class="hero-kicker"><i class="ph-fill ph-wallet"></i>Valor líquido das entregas</div><div class="hero-value">${money(net)}</div>
                    <div class="hero-helper">${money(gross)} em entregas${adjSignal?` ${adjSignal} ${money(adjValue)} em ${adjLabel}`:`, ${adjLabel}`} = ${money(net)} líquido.</div></div>
                    <div class="hero-footer"><a class="hero-link" href="${AW_SIMULATOR_URL}"><i class="ph ph-calculator"></i>Simular entrega</a><span class="hero-mini">Pago: <strong>${money(paid)}</strong></span></div>
                </article>

                <div class="overview-side">
                    <div class="kpis">
                        <article class="kpi purple"><span>Cota comprometida</span><strong>${money(committed)}</strong></article>
                        <article class="kpi amber"><span>Saldo da cota</span><strong>${available===null?'Sem limite':money(available)}</strong></article>
                        <article class="kpi"><span>Bruto confirmado</span><strong>${money(gross)}</strong></article>
                        <article class="kpi green"><span>Ainda a receber</span><strong>${money(receivable)}</strong></article>
                    </div>
                    <div class="charts">
                        <article class="chart"><div class="chart-title">Uso da cota</div><div class="donut-wrap">
                            ${hasLimit?`<div class="donut" style="--pct:${percent};--donut-tone:${donutTone}" role="img" aria-label="${pct(raw)} da cota utilizada"><div><strong>${pct(raw)}</strong><span>utilizado</span></div></div>`:`<div class="donut" style="--pct:0"><div><strong>—</strong><span>sem limite</span></div></div>`}
                        </div></article>
                        <article class="chart"><div class="chart-title">Fluxo financeiro</div><div class="bar-chart">
                            <div class="bar-row"><span class="bar-label">Bruto</span><div class="bar-track"><span class="bar-fill" style="--bar-tone:var(--blue);width:${gW}%"></span></div><span class="bar-value">${money(gross)}</span></div>
                            <div class="bar-row"><span class="bar-label">Líquido</span><div class="bar-track"><span class="bar-fill" style="--bar-tone:var(--purple);width:${nW}%"></span></div><span class="bar-value">${money(net)}</span></div>
                            <div class="bar-row"><span class="bar-label">Pago</span><div class="bar-track"><span class="bar-fill" style="--bar-tone:var(--green);width:${pW}%"></span></div><span class="bar-value">${money(paid)}</span></div>
                        </div></article>
                    </div>
                </div>
            </div>

            <div class="metric-table">
                ${metric('var(--amber)','var(--amber-soft)','ph-gauge','Cota comprometida','Valor bruto das entregas com destino confirmado',money(committed))}
                ${hasLimit?metric('var(--green)','var(--green-soft)','ph-wallet','Saldo da cota','Valor bruto ainda disponível',money(available)):''}
                ${metric('var(--blue)','var(--blue-soft)','ph-coins','Entregas confirmadas','Valor antes dos ajustes aplicados',money(gross))}
                <div class="metric-row" style="--metric-tone:var(--amber);--metric-soft:var(--amber-soft)"><span class="metric-icon"><i class="ph-fill ph-percent"></i></span><div class="metric-copy"><span>${adjustment>.005?'Acréscimos e ajustes':'Taxas e descontos'}</span><strong>${pct(data.effective_fee_percentage)} efetivos</strong></div><span class="metric-value">${adjSignal}${money(adjValue)}</span>${breakdownHtml}</div>
                ${metric('var(--purple)','var(--purple-soft)','ph-receipt','Líquido das entregas','Valor após os ajustes aplicados',money(net))}
                ${metric('var(--green)','var(--green-soft)','ph-check-circle','Valor pago','Pagamentos já registrados',money(paid))}
                ${data.project?.payment_forecast?metric('var(--cyan)','var(--cyan-soft)','ph-calendar-check','Previsão de pagamento',data.project.payment_forecast_note||'Data estimada pelo projeto',esc(data.project.payment_forecast)):''}
            </div>
        </div></section>`;
    }

    function metric(tone,soft,icon,label,help,value){
        return `<div class="metric-row" style="--metric-tone:${tone};--metric-soft:${soft}"><span class="metric-icon"><i class="ph-fill ${icon}"></i></span><div class="metric-copy"><span>${esc(label)}</span><strong>${esc(help)}</strong></div><span class="metric-value">${value}</span></div>`;
    }

    function renderLimits(data){
        const s=data.summary||{}, products=data.products||[];
        const financialPercent=s.financial_limit===null?0:(Number(s.financial_limit)>0?Number(s.financial_consumed||0)/Number(s.financial_limit)*100:0);
        const rows=products.map(p=>{
            const name=p.product||p.product_name||'Produto', unit=p.unit||p.product_unit||'';
            const max=p.maximum_quantity??p.associate_limit??p.project_limit;
            const delivered=Number(p.delivered_quantity??p.associate_delivered??0);
            const remaining=p.remaining_quantity??p.associate_remaining??p.project_remaining;
            const raw=p.percent??p.limit_percent??(Number(max)>0?delivered/Number(max)*100:0);
            const price=Number(p.reference_unit_price??p.unit_price??0), has=max!==null&&max!==undefined;
            return `<div class="table-row ${toneClass(raw)}">
                <div class="table-cell product-wide" data-label="Produto"><div class="product-cell"><span class="product-icon"><i class="ph-fill ph-cube"></i></span><div class="product-copy"><strong>${esc(name)}</strong><span>${esc(unit||'Unidade não informada')}</span></div></div></div>
                <div class="table-cell value" data-label="Preço">${price>0?money(price):'—'}</div>
                <div class="table-cell value" data-label="Entregue">${qty(delivered)} ${esc(unit)}</div>
                <div class="table-cell value limit-col" data-label="Limite">${has?`${qty(max)} ${esc(unit)}`:'Sem limite'}</div>
                <div class="table-cell value" data-label="Disponível">${has?`${qty(Math.max(0,Number(remaining||0)))} ${esc(unit)}`:'Livre'}</div>
                <div class="table-cell progress-cell" data-label="Uso">${has?`<div class="tiny-progress"><span style="width:${clamp(raw)}%"></span></div><div class="progress-caption"><span>${pct(raw)}</span><span>${Number(raw)>=100?'Atingido':Number(raw)>=80?'Atenção':'Disponível'}</span></div>`:'<span class="status-badge approved">Sem limite</span>'}</div>
            </div>`;
        }).join('');

        root.innerHTML=`<section class="section-shell">${sectionHead(`${products.length} ${products.length===1?'produto':'produtos'}`,'limits')}<div class="section-body">${guide('limits')}
            <div class="kpis" style="margin-bottom:.6rem">
                <article class="kpi purple"><span>Limite financeiro</span><strong>${s.financial_limit===null?'Sem limite':money(s.financial_limit)}</strong></article>
                <article class="kpi amber"><span>Utilizado</span><strong>${money(s.financial_consumed)}</strong></article>
                <article class="kpi green"><span>Disponível</span><strong>${s.financial_remaining===null?'Sem limite':money(s.financial_remaining)}</strong></article>
                <article class="kpi"><span>Uso da cota</span><strong>${s.financial_limit===null?'Livre':pct(financialPercent)}</strong></article>
            </div>
            ${rows?`<div class="data-table limits-table"><div class="table-head"><span>Produto</span><span>Preço</span><span>Entregue</span><span class="limit-col">Limite</span><span>Disponível</span><span>Uso</span></div>${rows}</div>`:empty('Nenhum produto disponível','Ainda não há produtos liberados para entrega neste projeto.','ph-package')}
        </div></section>`;
    }

    function priceTools(){
        return `<div class="filterbar single"><div class="searchbox"><i class="ph ph-magnifying-glass"></i><input class="filter-input" id="aw-search" type="search" value="${esc(state.filters.prices.search||'')}" placeholder="Buscar produto ou cliente..." autocomplete="off" oninput="awDebounce()"></div></div>`;
    }

    function renderPrices(data){
        const records=data.data||[];
        const rows=records.map(item=>`<article class="price-card"><div class="price-head"><div class="price-product"><strong>${esc(item.product_name)}</strong><span>${esc(item.unit||'un')} · ${Number(item.destination_count||0)} destino(s)</span></div><div class="price-range">${esc(item.price_label)}</div></div><div>${(item.destinations||[]).map(d=>`<div class="price-destination"><span>${esc(d.customer)}${d.price_table?` <small>· ${esc(d.price_table)}</small>`:''}</span><strong>${money(d.price)}</strong></div>`).join('')}</div></article>`).join('');
        root.innerHTML=`<section class="section-shell">${sectionHead(`${Number(data.total||0)} ${Number(data.total||0)===1?'produto':'produtos'}`,'prices')}<div class="section-body">${guide('prices')}${priceTools()}${rows?`<div class="price-list">${rows}</div>`:empty('Nenhum preço encontrado','Não há produto com preço para os clientes e filtros selecionados.','ph-tag')}${pager(data)}</div></section>`;
    }

    function simProduct(id){return state.simulator.products.find(p=>Number(p.product_id)===Number(id))}
    function defaultDestination(product){return [...(product?.destinations||[])].filter(d=>Number(d.price||0)>0).sort((a,b)=>Number(b.price)-Number(a.price))[0]||null}
    function rowDestination(row,product){return (product?.destinations||[]).find(d=>Number(d.customer_id)===Number(row.destinationId))||defaultDestination(product)}
    function simInit(){state.simulator.rows=[];state.simulator.initialized=true}
    function simTotals(){
        const rows=state.simulator.rows.map(row=>{const product=simProduct(row.productId),destination=rowDestination(row,product),price=Number(destination?.price||0),quantity=Math.max(0,Number(row.quantity||0));return{row,product,destination,price,quantity,total:price*quantity}});
        return{rows,total:rows.reduce((s,i)=>s+i.total,0)};
    }

    function pickerItems(){
        const search=state.simulator.pickerSearch.trim().toLocaleLowerCase('pt-BR');
        const selected=new Set(state.simulator.rows.map(r=>Number(r.productId)));
        const products=state.simulator.products.filter(p=>!search||`${p.product_name} ${p.unit}`.toLocaleLowerCase('pt-BR').includes(search));
        if(!products.length)return empty('Nenhum produto encontrado','Tente outro termo.','ph-magnifying-glass');
        const button=p=>`<button type="button" class="sim-picker-item" onclick="awSimAdd(${Number(p.product_id)})" ${selected.has(Number(p.product_id))?'disabled':''}><i class="ph-fill ${p.delivery_enabled?'ph-check-circle':'ph-cube'}"></i><span style="min-width:0"><strong>${esc(p.product_name)}</strong><small>${p.delivery_enabled?'Liberado · ':''}${esc(p.price_label)} · ${esc(p.unit||'un')}</small></span></button>`;
        const group=(title,text,items)=>items.length?`<div class="sim-picker-group"><div class="sim-picker-group-head"><strong>${title}</strong><span>${text}</span></div><div class="sim-picker-list">${items.map(button).join('')}</div></div>`:'';
        return group('Produtos liberados para entrega','Têm demanda ou cota configurada para este projeto.',products.filter(p=>p.delivery_enabled).slice(0,12))+group('Outros produtos para simular','Servem para comparar cenários.',products.filter(p=>!p.delivery_enabled).slice(0,search?18:12));
    }

    function simRow(item){
        const {row,product,destination,price,quantity,total}=item;if(!product||!destination)return'';
        const options=(product.destinations||[]).map(o=>`<option value="${Number(o.customer_id)}" ${Number(o.customer_id)===Number(destination.customer_id)?'selected':''}>${esc(o.customer)} · ${money(o.price)}</option>`).join('');
        const remaining=product.remaining_quantity;
        const exceeds=product.configured&&remaining!==null&&remaining!==undefined&&quantity>Number(remaining)+.0005;
        return `<article class="sim-row"><div class="sim-product"><span class="sim-product-icon"><i class="ph-fill ph-cube"></i></span><div class="sim-product-copy"><strong>${esc(product.product_name)}</strong><span>${esc(product.unit||'un')} · ${money(price)} por ${esc(product.unit||'un')}</span>${product.delivery_enabled?'<span class="sim-tag">Liberado para entrega</span>':'<span class="sim-tag extra">Somente simulação</span>'}</div></div>
        <div class="sim-field"><label>Preço e destino</label><select class="sim-control" onchange="awSimDestinationChange(${Number(product.product_id)},this.value)" ${product.destinations.length===1?'disabled':''}>${options}</select></div>
        <div class="sim-field"><label>Quantidade (${esc(product.unit||'un')})</label><div class="sim-qty"><input class="sim-control" id="sim-quantity-${Number(product.product_id)}" type="number" min="0" step="0.001" inputmode="decimal" value="${quantity>0?quantity:''}" oninput="awSimQuantity(${Number(product.product_id)},this.value)"><button type="button" class="sim-fill" onclick="awSimFill(${Number(product.product_id)})">Completar</button></div></div>
        <div><span class="sim-total" id="sim-total-${Number(product.product_id)}">${money(total)}</span><button type="button" class="sim-remove" onclick="awSimRemove(${Number(product.product_id)})" aria-label="Remover"><i class="ph ph-trash"></i></button></div>
        <div class="sim-note ${exceeds?'warning':''}" id="sim-note-${Number(product.product_id)}">${exceeds?`Acima dos ${qty(remaining)} ${esc(product.unit||'')} disponíveis.`:(product.configured&&remaining!==null?`${qty(remaining)} ${esc(product.unit||'')} disponíveis na configuração atual.`:'Produto sem cota configurada: use apenas para comparar um cenário.')}</div></article>`;
    }

    function renderSimulator(data){
        const s=state.simulator;s.products=data.products||[];s.summary=data.summary||{};s.catalogTruncated=!!data.catalog_truncated;s.deliveryEnabledTotal=Number(data.delivery_enabled_total??s.deliveryEnabledTotal??0);
        if(!s.initialized)simInit();else s.rows=s.rows.filter(r=>simProduct(r.productId));
        const totals=simTotals(),available=s.summary.financial_remaining,remaining=available===null?null:Number(available||0)-totals.total,percent=available&&Number(available)>0?Math.min(100,totals.total/Number(available)*100):0;
        root.innerHTML=`<section class="section-shell">${sectionHead(`${s.rows.length} produto(s)`,'simulator')}<div class="section-body">${guide('simulator')}
            <div class="sim-summary ${remaining!==null&&remaining<-.005?'is-over':''}" id="simulation-summary">
                <div class="sim-kpi"><span>Disponível</span><strong>${available===null?'Sem limite':money(available)}</strong></div>
                <div class="sim-kpi"><span>Total simulado</span><strong id="simulation-total">${money(totals.total)}</strong></div>
                <div class="sim-kpi emphasis"><span id="simulation-balance-label">${remaining!==null&&remaining<0?'Valor excedente':'Saldo após simulação'}</span><strong id="simulation-balance">${remaining===null?'Livre':money(Math.abs(remaining))}</strong></div>
                <div class="sim-progress"><span id="simulation-progress" style="width:${percent}%"></span></div>
            </div>
            <div class="sim-actions"><span class="sim-hint">${s.deliveryEnabledTotal} produto(s) liberado(s) para entrega</span><button type="button" class="btn primary" onclick="awSimTogglePicker()"><i class="ph ph-plus"></i>Escolher produto</button>${s.rows.length?'<button type="button" class="btn" onclick="awSimReset()"><i class="ph ph-arrow-counter-clockwise"></i>Recomeçar</button>':''}</div>
            <div class="sim-picker" id="simulation-picker" ${s.pickerOpen?'':'hidden'}><div class="searchbox"><i class="ph ph-magnifying-glass"></i><input class="filter-input" type="search" value="${esc(s.pickerSearch)}" placeholder="Buscar produto para simular..." oninput="awSimSearch(this.value)"></div><div id="simulation-picker-list">${pickerItems()}</div></div>
            ${totals.rows.length?`<div class="sim-list">${totals.rows.map(simRow).join('')}</div>`:empty('Escolha um produto','Comece pelos produtos liberados ou compare outro item da tabela de preços.','ph-calculator')}
            ${s.catalogTruncated?'<div class="guide prices" style="margin-top:.55rem"><span class="guide-icon"><i class="ph-fill ph-info"></i></span><div>O catálogo é grande. Os primeiros 250 produtos estão disponíveis nesta simulação.</div></div>':''}
        </div></section>`;
    }

    function simUpdate(){
        const totals=simTotals(),available=state.simulator.summary.financial_remaining,remaining=available===null?null:Number(available||0)-totals.total;
        document.getElementById('simulation-summary')?.classList.toggle('is-over',remaining!==null&&remaining<-.005);
        const t=document.getElementById('simulation-total');if(t)t.textContent=money(totals.total);
        const b=document.getElementById('simulation-balance');if(b)b.textContent=remaining===null?'Livre':money(Math.abs(remaining));
        const l=document.getElementById('simulation-balance-label');if(l)l.textContent=remaining!==null&&remaining<0?'Valor excedente':'Saldo após simulação';
        const p=document.getElementById('simulation-progress');if(p)p.style.width=`${available&&Number(available)>0?Math.min(100,totals.total/Number(available)*100):0}%`;
        totals.rows.forEach(item=>{
            const total=document.getElementById(`sim-total-${item.row.productId}`);if(total)total.textContent=money(item.total);
            const note=document.getElementById(`sim-note-${item.row.productId}`),r=item.product?.remaining_quantity;
            const exceeds=item.product?.configured&&r!==null&&r!==undefined&&item.quantity>Number(r)+.0005;
            if(note){note.classList.toggle('warning',exceeds);note.textContent=exceeds?`Acima dos ${qty(r)} ${item.product.unit||''} disponíveis.`:(item.product?.configured&&r!==null?`${qty(r)} ${item.product.unit||''} disponíveis na configuração atual.`:'Produto sem cota configurada: use apenas para comparar um cenário.')}
        });
    }

    window.awSimQuantity=(id,value)=>{const row=state.simulator.rows.find(r=>Number(r.productId)===Number(id));if(!row)return;row.quantity=value===''?0:Math.max(0,Number(String(value).replace(',','.'))||0);simUpdate()};
    window.awSimDestinationChange=(id,destinationId)=>{const row=state.simulator.rows.find(r=>Number(r.productId)===Number(id));if(!row)return;row.destinationId=Number(destinationId);renderSimulator({products:state.simulator.products,summary:state.simulator.summary,catalog_truncated:state.simulator.catalogTruncated,delivery_enabled_total:state.simulator.deliveryEnabledTotal})};
    window.awSimFill=id=>{const totals=simTotals(),current=totals.rows.find(i=>Number(i.row.productId)===Number(id)),available=state.simulator.summary.financial_remaining;if(!current||current.price<=0||available===null)return;const others=totals.total-current.total;let q=Math.max(0,(Number(available)-others)/current.price);if(current.product.configured&&current.product.remaining_quantity!==null)q=Math.min(q,Number(current.product.remaining_quantity));current.row.quantity=Math.floor(q*1000)/1000;const input=document.getElementById(`sim-quantity-${id}`);if(input)input.value=current.row.quantity>0?current.row.quantity:'';simUpdate()};
    window.awSimAdd=id=>{if(state.simulator.rows.some(r=>Number(r.productId)===Number(id)))return;const product=simProduct(id),destination=defaultDestination(product);if(!product||!destination)return;state.simulator.rows.push({productId:Number(id),destinationId:Number(destination.customer_id),quantity:0});state.simulator.pickerOpen=false;renderSimulator({products:state.simulator.products,summary:state.simulator.summary,catalog_truncated:state.simulator.catalogTruncated,delivery_enabled_total:state.simulator.deliveryEnabledTotal})};
    window.awSimRemove=id=>{state.simulator.rows=state.simulator.rows.filter(r=>Number(r.productId)!==Number(id));renderSimulator({products:state.simulator.products,summary:state.simulator.summary,catalog_truncated:state.simulator.catalogTruncated,delivery_enabled_total:state.simulator.deliveryEnabledTotal})};
    window.awSimTogglePicker=()=>{state.simulator.pickerOpen=!state.simulator.pickerOpen;const p=document.getElementById('simulation-picker');if(p)p.hidden=!state.simulator.pickerOpen;if(state.simulator.pickerOpen)p?.querySelector('input')?.focus()};
    window.awSimSearch=value=>{state.simulator.pickerSearch=value||'';const list=document.getElementById('simulation-picker-list');if(list)list.innerHTML=pickerItems()};
    window.awSimReset=()=>{state.simulator.initialized=false;state.simulator.pickerOpen=false;state.simulator.pickerSearch='';simInit();renderSimulator({products:state.simulator.products,summary:state.simulator.summary,catalog_truncated:state.simulator.catalogTruncated,delivery_enabled_total:state.simulator.deliveryEnabledTotal})};

    function tools(section){
        const f=state.filters[section]||{search:'',status:''};
        const placeholder=section==='distributions'?'Buscar produto ou cliente...':'Buscar produto...';
        return `<div class="filterbar"><div class="searchbox"><i class="ph ph-magnifying-glass"></i><input class="filter-input" id="aw-search" type="search" value="${esc(f.search)}" placeholder="${placeholder}" oninput="awDebounce()"></div><select class="filter-select" id="aw-status" onchange="awApplyFilters()">
            <option value="" ${f.status===''?'selected':''}>Todos os status</option><option value="pending" ${f.status==='pending'?'selected':''}>Pendentes</option><option value="approved" ${f.status==='approved'?'selected':''}>Aprovadas</option><option value="rejected" ${f.status==='rejected'?'selected':''}>Rejeitadas</option><option value="cancelled" ${f.status==='cancelled'?'selected':''}>Canceladas</option>
        </select></div>`;
    }

    function pager(data){
        const current=Number(data.current_page||1),last=Number(data.last_page||1);if(last<=1)return'';
        return `<div class="pager"><button class="pager-btn" type="button" ${current<=1?'disabled':''} onclick="awGo(${current-1})"><i class="ph ph-caret-left"></i>Anterior</button><span class="pager-label">${current} de ${last}</span><button class="pager-btn" type="button" ${current>=last?'disabled':''} onclick="awGo(${current+1})">Próxima<i class="ph ph-caret-right"></i></button></div>`;
    }

    function renderDeliveries(data){
        const records=data.data||[];
        const rows=records.map(item=>{
            const remaining=Number(item.remaining||0),note=String(item.notes||'').trim(),reject=String(item.rejection_reason||'').trim();
            const tone=item.status==='rejected'||item.status==='cancelled'?'danger':item.status==='pending'||remaining>0?'warning':'';
            return `<article class="record-card ${tone}"><header class="record-head"><span class="record-icon"><i class="ph-fill ph-package"></i></span><div class="record-title"><strong>${esc(item.product||'Produto')}</strong><span>${esc(item.quality||'Qualidade não informada')}</span></div><span class="record-date">${esc(item.date||'-')}</span></header><div class="record-body"><div class="record-stat"><span>Situação</span><strong>${badge(item.status,item.status_label)}</strong></div><div class="record-stat"><span>Quantidade entregue</span><strong>${qty(item.quantity)} ${esc(item.unit)}</strong></div><div class="record-stat"><span>Com destino</span><strong>${qty(item.distributed)} ${esc(item.unit)}</strong></div><div class="record-stat emphasis"><span>Valor líquido</span><strong>${Number(item.distribution_count||0)>0?money(item.net):'Ainda não calculado'}</strong></div></div><footer class="record-foot">${remaining>0?`<span class="status-badge pending">${qty(remaining)} ${esc(item.unit)} sem destino</span>`:''}${note?`<button type="button" class="note-btn" data-aw-note="${esc(note)}" data-aw-note-title="Observações da entrega" data-aw-note-meta="${esc(`${item.product||'Produto'} · ${item.date||''}`)}"><i class="ph ph-note"></i>Observações</button>`:''}${reject?`<button type="button" class="note-btn" data-aw-note="${esc(reject)}" data-aw-note-title="Avaliação de qualidade" data-aw-note-meta="${esc(`${item.product||'Produto'} · ${item.date||''}`)}"><i class="ph ph-warning-circle"></i>Avaliação</button>`:''}</footer></article>`;
        }).join('');
        root.innerHTML=`<section class="section-shell">${sectionHead(`${records.length} ${records.length===1?'registro nesta página':'registros nesta página'}`,'deliveries')}<div class="section-body">${guide('deliveries')}${tools('deliveries')}${rows?`<div class="record-grid">${rows}</div>`:empty('Nenhuma entrega encontrada','Ajuste os filtros ou aguarde novas entregas.','ph-package')}${pager(data)}</div></section>`;
    }

    function renderDistributions(data){
        const records=data.data||[];
        const rows=records.map(item=>`<article class="record-card distribution"><header class="record-head"><span class="record-icon"><i class="ph-fill ph-map-pin"></i></span><div class="record-title"><strong>${esc(item.product||'Produto')}</strong><span>${esc(item.customer||'Destino não informado')}</span></div><span class="record-date">${esc(item.date||'-')}</span></header><div class="record-body"><div class="record-stat"><span>Quantidade destinada</span><strong>${qty(item.quantity)} ${esc(item.unit)}</strong></div><div class="record-stat"><span>Preço unitário</span><strong>${money(item.unit_price)}</strong></div><div class="record-stat emphasis"><span>Valor bruto</span><strong>${money(item.gross)}</strong></div><div class="record-stat"><span>Comprovante</span><strong>${item.receipt?esc(item.receipt):'Pendente'}</strong></div></div><footer class="record-foot">${item.receipt?`<span class="status-badge approved"><i class="ph ph-receipt"></i>${esc(item.receipt)}</span>`:'<span class="status-badge pending">Pendente de comprovante</span>'}${String(item.notes||'').trim()?`<button type="button" class="note-btn" data-aw-note="${esc(item.notes)}" data-aw-note-title="Observações da distribuição" data-aw-note-meta="${esc(`${item.product||'Produto'} · ${item.customer||'Cliente'} · ${item.date||''}`)}"><i class="ph ph-note"></i>Observações</button>`:''}</footer></article>`).join('');
        root.innerHTML=`<section class="section-shell">${sectionHead(`${records.length} ${records.length===1?'destino nesta página':'destinos nesta página'}`,'distributions')}<div class="section-body">${guide('distributions')}${tools('distributions')}${rows?`<div class="record-grid">${rows}</div>`:empty('Nenhuma entrega por destino encontrada','Ainda não há destinos para os filtros selecionados.','ph-map-pin')}${pager(data)}</div></section>`;
    }

    function renderReceipts(data){
        const records=data.data||[];
        const rows=records.map(item=>`<div class="table-row">
            <div class="table-cell" data-label="Data"><strong>${esc(item.date||'—')}</strong></div>
            <div class="table-cell product-wide" data-label="Comprovante"><div class="product-cell"><span class="product-icon" style="--row-tone:var(--purple);--row-soft:var(--purple-soft)"><i class="ph-fill ph-receipt"></i></span><div class="product-copy"><strong>${esc(item.number)}</strong><span>Documento financeiro</span></div></div></div>
            <div class="table-cell" data-label="Status">${badge(item.status,item.status_label)}</div>
            <div class="table-cell value" data-label="Bruto">${money(item.gross)}</div>
            <div class="table-cell value" data-label="Ajustes">${money(item.fees)}</div>
            <div class="table-cell value" data-label="Líquido">${money(item.net)}</div>
            <div class="table-cell action-col" data-label="Ação">${item.preview_url?`<a class="btn" href="${esc(item.preview_url)}" target="_blank" rel="noopener"><i class="ph ph-eye"></i>Visualizar</a>`:'<span class="status-badge obsolete">Histórico</span>'}</div>
        </div>`).join('');
        root.innerHTML=`<section class="section-shell">${sectionHead(`${records.length} ${records.length===1?'comprovante nesta página':'comprovantes nesta página'}`,'receipts')}<div class="section-body">${guide('receipts')}${rows?`<div class="data-table receipts-table"><div class="table-head"><span>Data</span><span>Comprovante</span><span>Status</span><span>Bruto</span><span>Ajustes</span><span>Líquido</span><span class="action-col">Ação</span></div>${rows}</div>`:empty('Nenhum comprovante','Os comprovantes gerados para este projeto aparecerão aqui.','ph-receipt')}${pager(data)}</div></section>`;
    }

    function renderPayments(data){
        const records=data.data||[];
        const rows=records.map(item=>`<div class="table-row">
            <div class="table-cell" data-label="Data"><strong>${esc(item.date||'—')}</strong></div>
            <div class="table-cell product-wide" data-label="Comprovante"><div class="product-cell"><span class="product-icon" style="--row-tone:var(--green);--row-soft:var(--green-soft)"><i class="ph-fill ph-wallet"></i></span><div class="product-copy"><strong>${esc(item.receipt||'Pagamento')}</strong><span>Pagamento registrado</span></div></div></div>
            <div class="table-cell" data-label="Método">${esc(item.method||'Não informado')}</div>
            <div class="table-cell value" data-label="Valor pago">${money(item.amount)}</div>
        </div>`).join('');
        root.innerHTML=`<section class="section-shell">${sectionHead(`${records.length} ${records.length===1?'pagamento nesta página':'pagamentos nesta página'}`,'payments')}<div class="section-body">${guide('payments')}${rows?`<div class="data-table payments-table"><div class="table-head"><span>Data</span><span>Comprovante</span><span>Método</span><span>Valor pago</span></div>${rows}</div>`:empty('Nenhum pagamento registrado','Os pagamentos vinculados aos comprovantes aparecerão aqui.','ph-wallet')}${pager(data)}</div></section>`;
    }

    window.awDebounce=()=>{clearTimeout(state.timer);state.timer=setTimeout(()=>applyFilters(),350)};
    function applyFilters(){
        const f=state.filters[state.section];if(!f)return;
        f.search=document.getElementById('aw-search')?.value||'';
        if(Object.prototype.hasOwnProperty.call(f,'status'))f.status=document.getElementById('aw-status')?.value||'';
        state.page=1;load();
    }
    window.awApplyFilters=applyFilters;
    window.awGo=page=>{state.page=Math.max(1,Number(page||1));load();document.querySelector('.section-head')?.scrollIntoView({behavior:'smooth',block:'start'})};

    const initial=location.hash.replace('#','');
    setSection(sections[initial]?initial:'summary',{skipHash:!sections[initial],instant:true});
})();
</script>
@endpush
