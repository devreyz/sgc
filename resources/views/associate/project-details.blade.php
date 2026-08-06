@extends('layouts.bento')

@section('title', 'Detalhes do Projeto')
@section('page-title', $project->title ?? 'Projeto')
@section('user-role', 'Associado')

@php
    $routeTenant = request()->route('tenant');
    $routeSlug = is_string($routeTenant)
        ? $routeTenant
        : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);

    $tenantSlug = $currentTenant?->slug
        ?? session('tenant_slug')
        ?? $routeSlug
        ?? null;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'associate',
        'projects',
        $tenantSlug
    );

    $statusValue = static fn ($status) => is_object($status)
        ? ($status->value ?? null)
        : (is_string($status) ? $status : null);

    $statusLabel = static function ($status): string {
        if (is_object($status) && method_exists($status, 'getLabel')) {
            return $status->getLabel();
        }

        return match (is_object($status) ? ($status->value ?? null) : $status) {
            'active' => 'Em execução',
            'approved' => 'Aprovada',
            'pending' => 'Pendente',
            'rejected' => 'Rejeitada',
            'cancelled' => 'Cancelada',
            'paid' => 'Pago',
            default => 'Registrada',
        };
    };

    $unitLabel = static function ($unit): string {
        if (is_object($unit)) {
            return method_exists($unit, 'getLabel')
                ? $unit->getLabel()
                : (string) ($unit->value ?? $unit->name ?? '');
        }

        return is_string($unit) ? $unit : '';
    };

    $money = static fn ($value): string =>
        'R$ ' . number_format((float) $value, 2, ',', '.');

    $quantity = static fn ($value): string =>
        rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');

    $projectStatus = $statusValue($project->status ?? null);
    $projectIsActive = $projectStatus === 'active';

    $visibleDeliveries = $myDeliveries
        ->getCollection()
        ->reject(fn ($delivery) => $statusValue($delivery->status ?? null) === 'draft')
        ->values();

    $visibleReceipts = collect($receipts)
        ->reject(fn ($receipt) => $statusValue($receipt->status ?? null) === 'draft')
        ->values();

    $financialPercent = max(0, (float) ($financialLimit['percent'] ?? 0));
    $financialTone = $financialPercent >= 100
        ? 'danger'
        : ($financialPercent >= 80 ? 'warning' : 'normal');

    $financialTotal = max(0, (float) ($financialStates['total'] ?? 0));
    $financialBase = max($financialTotal, .01);
    $unbilledWidth = ((float) ($financialStates['unbilled'] ?? 0) / $financialBase) * 100;
    $billedWidth = ((float) ($financialStates['billed'] ?? 0) / $financialBase) * 100;
    $paidWidth = ((float) ($financialStates['paid'] ?? 0) / $financialBase) * 100;
@endphp

@section('content')
<style>
    .pd-page{display:grid;width:min(100%,1120px);min-width:0;grid-column:1/-1;gap:.8rem;margin:0 auto}
    .pd-page *,.pd-page *::before,.pd-page *::after{box-sizing:border-box}
    .pd-panel{min-width:0;overflow:hidden;border:1px solid var(--color-border);border-radius:16px;background:var(--color-surface);box-shadow:var(--shadow-md)}
    .pd-main-head{display:grid;grid-template-columns:auto auto minmax(0,1fr) auto;gap:.62rem;align-items:center;padding:.75rem .8rem;border-left:4px solid var(--color-primary-dark);background:linear-gradient(90deg,#ecfdf5,rgba(255,255,255,.98) 48%)}
    .pd-back,.pd-head-icon{display:grid;width:40px;height:40px;place-items:center;border-radius:11px}
    .pd-back{border:1px solid var(--color-border);background:#fff;color:var(--color-text-secondary);text-decoration:none}
    .pd-back:hover,.pd-back:focus-visible{border-color:rgba(34,197,94,.36);background:var(--color-primary-50);color:var(--color-primary-deep);outline:none}
    .pd-head-icon{width:42px;height:42px;background:#dcfce7;color:#15803d}
    .pd-back i,.pd-head-icon i{font-size:1.12rem}
    .pd-head-copy{min-width:0}.pd-head-copy h1{margin:0;overflow:hidden;color:var(--color-text);font-size:clamp(1rem,2vw,1.2rem);font-weight:860;letter-spacing:-.03em;text-overflow:ellipsis;white-space:nowrap}
    .pd-head-meta{display:flex;flex-wrap:wrap;gap:.25rem .6rem;margin-top:.22rem;color:var(--color-text-muted);font-size:.72rem}
    .pd-head-meta span{display:inline-flex;min-width:0;align-items:center;gap:.25rem}.pd-head-meta i{font-size:.84rem}.pd-head-meta b{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:650}
    .pd-status{display:inline-flex;min-height:29px;align-items:center;gap:.28rem;padding:.3rem .46rem;border-radius:999px;background:#ecfdf5;color:#047857;font-size:.67rem;font-weight:820;white-space:nowrap}
    .pd-section-head{display:flex;align-items:center;justify-content:space-between;gap:.7rem;padding:.7rem .8rem;border-bottom:1px solid var(--color-border);background:linear-gradient(180deg,var(--color-surface-soft),var(--color-surface))}
    .pd-title{display:flex;min-width:0;align-items:center;gap:.55rem}.pd-title-icon,.pd-row-icon{display:grid;flex:0 0 auto;place-items:center;border-radius:11px}
    .pd-title-icon{width:38px;height:38px}.pd-title-icon i{font-size:1.08rem}.pd-title-icon.alert{background:#fffbeb;color:#d97706}.pd-title-icon.limit{background:#ecfdf5;color:#15803d}.pd-title-icon.finance{background:#eff6ff;color:#2563eb}.pd-title-icon.products{background:#f5f3ff;color:#7c3aed}.pd-title-icon.orgs{background:#f0f9ff;color:#0284c7}.pd-title-icon.deliveries{background:#fffbeb;color:#d97706}.pd-title-icon.receipts{background:#f1f5f9;color:#475569}
    .pd-title h2{margin:0;color:var(--color-text);font-size:.9rem;font-weight:840;letter-spacing:-.02em}.pd-count{display:inline-flex;min-height:27px;align-items:center;padding:.28rem .43rem;border-radius:999px;background:var(--color-surface-muted);color:var(--color-text-secondary);font-size:.65rem;font-weight:780;white-space:nowrap}
    .pd-body{min-width:0;padding:.75rem .8rem}
    .pd-restricted{display:grid;min-height:290px;place-items:center;padding:1.5rem;text-align:center}.pd-restricted i{display:grid;width:62px;height:62px;place-items:center;margin:0 auto .65rem;border-radius:18px;background:#fffbeb;color:#d97706;font-size:1.7rem}.pd-restricted strong{display:block;color:var(--color-text);font-size:.9rem;font-weight:840}.pd-restricted p{max-width:420px;margin:.24rem auto 0;color:var(--color-text-secondary);font-size:.76rem}
    .pd-alerts{display:grid;gap:.45rem}.pd-alert{display:grid;grid-template-columns:auto minmax(0,1fr);gap:.55rem;align-items:center;padding:.6rem;border:1px solid;border-radius:11px}.pd-alert.warning{border-color:rgba(217,119,6,.2);background:#fffbeb}.pd-alert.danger{border-color:rgba(220,38,38,.18);background:#fef2f2}.pd-alert i{display:grid;width:34px;height:34px;place-items:center;border-radius:10px;font-size:1rem}.pd-alert.warning i{background:#fef3c7;color:#b45309}.pd-alert.danger i{background:#fee2e2;color:#dc2626}.pd-alert div{color:var(--color-text-secondary);font-size:.75rem}.pd-alert strong{color:var(--color-text);font-weight:820}
    .pd-financial{display:grid;grid-template-columns:minmax(230px,.9fr) minmax(0,1.1fr);gap:.75rem}.pd-fin-main{padding:.78rem;border:1px solid rgba(34,197,94,.18);border-left:4px solid #16a34a;border-radius:13px;background:linear-gradient(135deg,#ecfdf5,#fff 68%)}.pd-fin-main.warning{border-left-color:#d97706;background:linear-gradient(135deg,#fffbeb,#fff 68%)}.pd-fin-main.danger{border-left-color:#dc2626;background:linear-gradient(135deg,#fef2f2,#fff 68%)}.pd-fin-main span,.pd-fin-main strong{display:block}.pd-fin-main span{color:var(--color-text-secondary);font-size:.73rem;font-weight:710}.pd-fin-main strong{margin-top:.25rem;color:var(--color-text);font-size:clamp(1.25rem,3vw,1.75rem);font-weight:870;letter-spacing:-.04em}.pd-fin-main small{display:block;margin-top:.22rem;color:var(--color-text-muted);font-size:.69rem}
    .pd-progress{height:8px;margin-top:.55rem;overflow:hidden;border-radius:999px;background:#e7ede9}.pd-progress span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#4ade80,#16a34a)}.warning .pd-progress span{background:linear-gradient(90deg,#fbbf24,#d97706)}.danger .pd-progress span{background:linear-gradient(90deg,#fb7185,#dc2626)}
    .pd-facts{overflow:hidden;border:1px solid var(--color-border);border-radius:13px}.pd-fact{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:.55rem;align-items:center;padding:.61rem .66rem;border-top:1px solid var(--color-border)}.pd-fact:first-child{border-top:0}.pd-fact i{font-size:1rem}.pd-fact:nth-child(1) i{color:#7c3aed}.pd-fact:nth-child(2) i{color:#d97706}.pd-fact:nth-child(3) i{color:#2563eb}.pd-fact span{color:var(--color-text-secondary);font-size:.72rem}.pd-fact strong{color:var(--color-text);font-size:.78rem;font-weight:830;white-space:nowrap}
    .pd-state-total{display:flex;align-items:baseline;justify-content:space-between;gap:.7rem;margin-bottom:.5rem}.pd-state-total span{color:var(--color-text-secondary);font-size:.73rem}.pd-state-total strong{font-size:1rem;font-weight:850;white-space:nowrap}.pd-state-bar{display:flex;height:9px;overflow:hidden;border-radius:999px;background:#e7ede9}.pd-state-bar span{display:block;height:100%}.pd-state-bar .unbilled{background:#f59e0b}.pd-state-bar .billed{background:#3b82f6}.pd-state-bar .paid{background:#10b981}.pd-state-list{display:grid;margin-top:.5rem}.pd-state-row{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:.48rem;align-items:center;padding:.45rem 0;border-top:1px solid var(--color-border)}.pd-state-row:first-child{border-top:0}.pd-state-row i{font-size:.95rem}.pd-state-row.unbilled i{color:#d97706}.pd-state-row.billed i{color:#2563eb}.pd-state-row.paid i{color:#059669}.pd-state-row span{color:var(--color-text-secondary);font-size:.73rem}.pd-state-row strong{font-size:.77rem;font-weight:830;white-space:nowrap}
    .pd-product{padding:.7rem 0;border-top:1px solid var(--color-border)}.pd-product:first-child{padding-top:0;border-top:0}.pd-product:last-child{padding-bottom:0}.pd-product-main{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:.56rem;align-items:center}.pd-row-icon{width:37px;height:37px}.pd-product.normal .pd-row-icon{background:#ecfdf5;color:#15803d}.pd-product.warning .pd-row-icon{background:#fffbeb;color:#d97706}.pd-product.danger .pd-row-icon{background:#fef2f2;color:#dc2626}.pd-row-icon i{font-size:1.05rem}.pd-product-copy{min-width:0}.pd-product-name{display:flex;min-width:0;flex-wrap:wrap;align-items:center;gap:.34rem}.pd-product-name strong{overflow:hidden;font-size:.84rem;font-weight:820;text-overflow:ellipsis;white-space:nowrap}.pd-unit{display:inline-flex;min-height:22px;align-items:center;padding:.17rem .35rem;border-radius:999px;background:var(--color-surface-muted);color:var(--color-text-secondary);font-size:.63rem;font-weight:760}.pd-product-copy>span{display:block;margin-top:.1rem;color:var(--color-text-muted);font-size:.69rem}.pd-ratio{font-size:.8rem;font-weight:850;text-align:right;white-space:nowrap}.pd-product-sub{display:flex;align-items:flex-end;justify-content:space-between;gap:.7rem;margin-top:.42rem;padding-left:2.92rem}.pd-delivered{color:var(--color-text-secondary);font-size:.74rem}.pd-delivered strong{color:var(--color-text);font-weight:810}.pd-remaining{color:#15803d;font-size:.74rem;font-weight:820;white-space:nowrap}.pd-product.warning .pd-remaining{color:#d97706}.pd-product.danger .pd-remaining{color:#dc2626}.pd-product .pd-progress{margin-left:2.92rem}
    .pd-org{overflow:hidden;margin-top:.5rem;border:1px solid var(--color-border);border-radius:12px}.pd-org:first-child{margin-top:0}.pd-org summary{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto;gap:.5rem;align-items:center;min-height:54px;padding:.58rem .64rem;background:var(--color-surface-soft);cursor:pointer;list-style:none}.pd-org summary::-webkit-details-marker{display:none}.pd-org-icon{display:grid;width:35px;height:35px;place-items:center;border-radius:10px;background:#f0f9ff;color:#0284c7}.pd-org-name{overflow:hidden;font-size:.8rem;font-weight:810;text-overflow:ellipsis;white-space:nowrap}.pd-org-value{color:#047857;font-size:.78rem;font-weight:830;white-space:nowrap}.pd-org-arrow{color:var(--color-text-muted);transition:transform .15s}.pd-org[open] .pd-org-arrow{transform:rotate(90deg)}.pd-org-customers{padding:0 .64rem;border-top:1px solid var(--color-border)}.pd-org-customer{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.6rem;padding:.5rem 0;border-top:1px solid var(--color-border)}.pd-org-customer:first-child{border-top:0}.pd-org-customer span{overflow:hidden;color:var(--color-text-secondary);font-size:.74rem;text-overflow:ellipsis;white-space:nowrap}.pd-org-customer strong{font-size:.75rem;font-weight:820;white-space:nowrap}
    .pd-toggle{overflow:hidden;border:1px solid var(--color-border);border-radius:12px}.pd-toggle summary{display:flex;min-height:48px;align-items:center;justify-content:space-between;gap:.6rem;padding:.58rem .64rem;background:var(--color-surface-soft);cursor:pointer;font-size:.77rem;font-weight:800;list-style:none}.pd-toggle summary::-webkit-details-marker{display:none}.pd-toggle-body{padding:.55rem .64rem;border-top:1px solid var(--color-border)}.pd-demand{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:.5rem;align-items:center;padding:.48rem 0;border-top:1px solid var(--color-border)}.pd-demand:first-child{border-top:0}.pd-demand i{color:#7c3aed}.pd-demand span{overflow:hidden;color:var(--color-text-secondary);font-size:.74rem;text-overflow:ellipsis;white-space:nowrap}.pd-demand strong{font-size:.75rem;font-weight:820;white-space:nowrap}
    .pd-filter{display:grid;grid-template-columns:minmax(150px,.8fr) minmax(145px,.7fr) minmax(145px,.7fr) auto;gap:.52rem;align-items:end;margin-bottom:.68rem}.pd-field label{display:block;margin-bottom:.3rem;color:var(--color-text-secondary);font-size:.68rem;font-weight:750}.pd-field select,.pd-field input{width:100%;min-height:41px;padding:.5rem .62rem;font-size:.74rem}.pd-actions{display:flex;gap:.36rem}.pd-button{display:inline-flex;min-height:41px;align-items:center;justify-content:center;gap:.32rem;padding:.47rem .62rem;border:1px solid var(--color-border-strong);border-radius:10px;background:#fff;color:var(--color-text-secondary);font-size:.72rem;font-weight:780;text-decoration:none;cursor:pointer}.pd-button.primary{border-color:#16a34a;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff}
    .pd-list{min-width:0}.pd-delivery,.pd-receipt{display:grid;gap:.58rem;align-items:center;padding:.68rem 0;border-top:1px solid var(--color-border)}.pd-delivery:first-child,.pd-receipt:first-child{border-top:0}.pd-delivery{grid-template-columns:auto minmax(180px,1.2fr) minmax(95px,.55fr) minmax(105px,.62fr) minmax(90px,.5fr)}.pd-receipt{grid-template-columns:auto minmax(180px,1.2fr) minmax(105px,.62fr) minmax(105px,.62fr) minmax(90px,.5fr)}.pd-delivery .pd-row-icon{background:#fffbeb;color:#d97706}.pd-receipt .pd-row-icon{background:#f1f5f9;color:#475569}.pd-copy{min-width:0}.pd-line{display:flex;min-width:0;flex-wrap:wrap;align-items:center;gap:.34rem}.pd-line strong{overflow:hidden;font-size:.82rem;font-weight:820;text-overflow:ellipsis;white-space:nowrap}.pd-meta{display:flex;flex-wrap:wrap;gap:.23rem .54rem;margin-top:.15rem;color:var(--color-text-muted);font-size:.68rem}.pd-data span,.pd-data strong{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pd-data span{color:var(--color-text-muted);font-size:.66rem}.pd-data strong{margin-top:.08rem;font-size:.76rem;font-weight:820}.pd-data.value strong{color:#047857}.pd-badge{display:inline-flex;min-height:23px;align-items:center;padding:.2rem .37rem;border-radius:999px;background:var(--color-surface-muted);color:var(--color-text-secondary);font-size:.61rem;font-weight:800}.pd-badge.approved,.pd-badge.paid{background:#ecfdf5;color:#047857}.pd-badge.pending{background:#fffbeb;color:#92400e}.pd-badge.rejected,.pd-badge.cancelled{background:#fef2f2;color:#991b1b}
    .pd-empty{display:grid;min-height:150px;place-items:center;text-align:center}.pd-empty i{color:var(--color-text-muted);font-size:1.5rem}.pd-empty strong{display:block;margin-top:.4rem;font-size:.8rem;font-weight:820}.pd-pagination{display:flex;justify-content:center;padding-top:.7rem}
    @media(max-width:840px){.pd-financial{grid-template-columns:1fr}.pd-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.pd-actions{grid-column:1/-1}.pd-delivery,.pd-receipt{grid-template-columns:auto minmax(0,1fr) minmax(105px,auto)}}
    @media(max-width:560px){.pd-page{gap:.7rem}.pd-main-head{grid-template-columns:auto minmax(0,1fr) auto;padding:.68rem}.pd-head-icon{display:none}.pd-status{grid-column:1/-1;justify-self:start;margin-left:3rem}.pd-section-head,.pd-body{padding:.66rem .68rem}.pd-product-sub{align-items:flex-start;flex-direction:column;gap:.16rem}.pd-org summary{grid-template-columns:auto minmax(0,1fr) auto}.pd-org-value{grid-column:2}.pd-org-arrow{grid-column:3;grid-row:1/span 2}.pd-filter{grid-template-columns:1fr}.pd-actions{display:grid;grid-template-columns:1fr 1fr}.pd-button{width:100%}.pd-delivery,.pd-receipt{grid-template-columns:auto minmax(0,1fr);align-items:start}.pd-delivery .pd-data,.pd-receipt .pd-data{grid-column:2}.pd-data{display:flex;align-items:baseline;gap:.34rem}.pd-data span,.pd-data strong{display:inline}}
    @media(max-width:390px){.pd-product-main{grid-template-columns:auto minmax(0,1fr)}.pd-ratio{grid-column:2;text-align:left}.pd-product-sub,.pd-product .pd-progress{margin-left:0;padding-left:0}}
</style>

<main class="pd-page">
    <section class="pd-panel">
        <header class="pd-main-head">
            <a class="pd-back" href="{{ $tenantSlug ? route('associate.projects', ['tenant' => $tenantSlug]) : url('/') }}" aria-label="Voltar aos projetos">
                <i class="ph ph-arrow-left"></i>
            </a>

            <span class="pd-head-icon" aria-hidden="true">
                <i class="ph-duotone ph-folder-open"></i>
            </span>

            <div class="pd-head-copy">
                <h1>{{ $project->title }}</h1>

                @if($project->customer)
                    <div class="pd-head-meta">
                        <span>
                            <i class="ph ph-buildings"></i>
                            <b>{{ $project->customer->name }}</b>
                        </span>
                    </div>
                @endif
            </div>

            @if($projectIsActive)
                <span class="pd-status">
                    <i class="ph ph-play-circle"></i>
                    Em execução
                </span>
            @endif
        </header>
    </section>

    @if(! $projectIsActive)
        <section class="pd-panel">
            <div class="pd-restricted">
                <div>
                    <i class="ph-duotone ph-lock-key" aria-hidden="true"></i>
                    <strong>Projeto indisponível</strong>
                    <p>Este projeto não está em execução e não pode ser acessado pelo Portal do Associado.</p>
                </div>
            </div>
        </section>
    @else
        @if(
            ($financialLimit['is_full'] ?? false)
            || ($financialLimit['is_near'] ?? false)
            || $productLimits->where('is_full', true)->isNotEmpty()
            || $productLimits->where('is_near', true)->isNotEmpty()
        )
            <section class="pd-panel">
                <header class="pd-section-head">
                    <div class="pd-title">
                        <span class="pd-title-icon alert"><i class="ph-duotone ph-warning"></i></span>
                        <h2>Atenção aos limites</h2>
                    </div>
                </header>

                <div class="pd-body">
                    <div class="pd-alerts">
                        @if($financialLimit['is_full'] ?? false)
                            <div class="pd-alert danger">
                                <i class="ph-duotone ph-x-circle"></i>
                                <div><strong>Limite financeiro atingido.</strong> Novas entregas não podem ser registradas.</div>
                            </div>
                        @elseif($financialLimit['is_near'] ?? false)
                            <div class="pd-alert warning">
                                <i class="ph-duotone ph-warning-circle"></i>
                                <div><strong>Limite financeiro próximo.</strong> Restam {{ $money($financialLimit['remaining'] ?? 0) }}.</div>
                            </div>
                        @endif

                        @foreach($productLimits->filter(fn ($limit) => $limit->is_full || $limit->is_near) as $limit)
                            <div class="pd-alert {{ $limit->is_full ? 'danger' : 'warning' }}">
                                <i class="ph-duotone {{ $limit->is_full ? 'ph-x-circle' : 'ph-warning-circle' }}"></i>
                                <div>
                                    <strong>{{ $limit->product?->name ?? 'Produto' }}:</strong>
                                    @if($limit->is_full)
                                        limite de quantidade atingido.
                                    @else
                                        ainda podem ser entregues {{ $quantity($limit->remaining_qty) }} {{ $unitLabel($limit->product?->unit) }}.
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        @if(($financialLimit['max'] ?? null) !== null)
            <section class="pd-panel">
                <header class="pd-section-head">
                    <div class="pd-title">
                        <span class="pd-title-icon limit"><i class="ph-duotone ph-gauge"></i></span>
                        <h2>Limite financeiro</h2>
                    </div>
                </header>

                <div class="pd-body">
                    <div class="pd-financial">
                        <article class="pd-fin-main {{ $financialTone }}">
                            <span>Valor disponível</span>
                            <strong>{{ $money($financialLimit['remaining'] ?? 0) }}</strong>
                            <small>{{ number_format($financialPercent, 0, ',', '.') }}% utilizado</small>

                            <div class="pd-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, round($financialPercent)) }}">
                                <span style="width:{{ min(100, $financialPercent) }}%"></span>
                            </div>
                        </article>

                        <div class="pd-facts">
                            <div class="pd-fact"><i class="ph-duotone ph-coins"></i><span>Limite total</span><strong>{{ $money($financialLimit['max']) }}</strong></div>
                            <div class="pd-fact"><i class="ph-duotone ph-chart-line-up"></i><span>Valor utilizado</span><strong>{{ $money($financialLimit['accumulated'] ?? 0) }}</strong></div>
                            <div class="pd-fact"><i class="ph-duotone ph-percent"></i><span>Percentual utilizado</span><strong>{{ number_format($financialPercent, 1, ',', '.') }}%</strong></div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if($financialTotal > 0)
            <section class="pd-panel">
                <header class="pd-section-head">
                    <div class="pd-title">
                        <span class="pd-title-icon finance"><i class="ph-duotone ph-wallet"></i></span>
                        <h2>Financeiro das distribuições</h2>
                    </div>
                </header>

                <div class="pd-body">
                    <div class="pd-state-total">
                        <span>Total distribuído</span>
                        <strong>{{ $money($financialTotal) }}</strong>
                    </div>

                    <div class="pd-state-bar">
                        @if($unbilledWidth > 0)<span class="unbilled" style="width:{{ $unbilledWidth }}%"></span>@endif
                        @if($billedWidth > 0)<span class="billed" style="width:{{ $billedWidth }}%"></span>@endif
                        @if($paidWidth > 0)<span class="paid" style="width:{{ $paidWidth }}%"></span>@endif
                    </div>

                    <div class="pd-state-list">
                        @if(($financialStates['unbilled'] ?? 0) > 0)
                            <div class="pd-state-row unbilled"><i class="ph-duotone ph-clock-countdown"></i><span>A faturar</span><strong>{{ $money($financialStates['unbilled']) }}</strong></div>
                        @endif
                        @if(($financialStates['billed'] ?? 0) > 0)
                            <div class="pd-state-row billed"><i class="ph-duotone ph-receipt"></i><span>Faturado e aguardando pagamento</span><strong>{{ $money($financialStates['billed']) }}</strong></div>
                        @endif
                        @if(($financialStates['paid'] ?? 0) > 0)
                            <div class="pd-state-row paid"><i class="ph-duotone ph-check-circle"></i><span>Pago</span><strong>{{ $money($financialStates['paid']) }}</strong></div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        @if($productLimits->isNotEmpty())
            <section class="pd-panel">
                <header class="pd-section-head">
                    <div class="pd-title">
                        <span class="pd-title-icon products"><i class="ph-duotone ph-package"></i></span>
                        <h2>Produtos e limites</h2>
                    </div>
                    <span class="pd-count">{{ $productLimits->count() }}</span>
                </header>

                <div class="pd-body">
                    @foreach($productLimits as $limit)
                        @php
                            $percent = max(0, (float) ($limit->percent_used ?? 0));
                            $tone = $percent >= 100 ? 'danger' : ($percent >= 80 ? 'warning' : 'normal');
                            $unit = $unitLabel($limit->product?->unit);
                            $remaining = max(0, (float) $limit->remaining_qty);
                            $isFull = $limit->is_full || $remaining <= 0;
                            $availability = $isFull
                                ? 'Limite atingido'
                                : ((float) $limit->delivered_qty <= 0
                                    ? 'Disponível para entregar ' . $quantity($remaining) . ($unit ? ' ' . $unit : '')
                                    : 'Pode entregar mais ' . $quantity($remaining) . ($unit ? ' ' . $unit : ''));
                        @endphp

                        <article class="pd-product {{ $tone }}">
                            <div class="pd-product-main">
                                <span class="pd-row-icon">
                                    <i class="ph-duotone {{ $isFull ? 'ph-check-circle' : ($tone === 'warning' ? 'ph-warning' : 'ph-cube') }}"></i>
                                </span>

                                <div class="pd-product-copy">
                                    <div class="pd-product-name">
                                        <strong>{{ $limit->product?->name ?? 'Produto' }}</strong>
                                        <span class="pd-unit">{{ $unit ?: 'Unidade não informada' }}</span>
                                    </div>
                                    <span>Limite individual</span>
                                </div>

                                <strong class="pd-ratio">
                                    {{ $quantity($limit->delivered_qty) }} /
                                    {{ $quantity($limit->max_quantity) }}
                                    {{ $unit }}
                                </strong>
                            </div>

                            <div class="pd-product-sub">
                                <span class="pd-delivered">
                                    Entregue <strong>{{ $quantity($limit->delivered_qty) }} {{ $unit }}</strong>
                                    de <strong>{{ $quantity($limit->max_quantity) }} {{ $unit }}</strong>
                                </span>
                                <strong class="pd-remaining">{{ $availability }}</strong>
                            </div>

                            <div class="pd-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ min(100, round($percent)) }}">
                                <span style="width:{{ min(100, $percent) }}%"></span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if($distributionsByOrg->isNotEmpty())
            <section class="pd-panel">
                <header class="pd-section-head">
                    <div class="pd-title">
                        <span class="pd-title-icon orgs"><i class="ph-duotone ph-buildings"></i></span>
                        <h2>Distribuições por organização</h2>
                    </div>
                    <span class="pd-count">{{ $distributionsByOrg->count() }}</span>
                </header>

                <div class="pd-body">
                    @foreach($distributionsByOrg as $organization)
                        <details class="pd-org">
                            <summary>
                                <span class="pd-org-icon"><i class="ph-duotone ph-buildings"></i></span>
                                <strong class="pd-org-name">{{ $organization['organization_name'] }}</strong>
                                <span class="pd-org-value">{{ $money($organization['total_net']) }}</span>
                                <i class="ph ph-caret-right pd-org-arrow"></i>
                            </summary>

                            <div class="pd-org-customers">
                                @foreach($organization['customers'] as $customer)
                                    <div class="pd-org-customer">
                                        <span>{{ $customer['customer_name'] }}</span>
                                        <strong>{{ $money($customer['total_net']) }} · {{ $customer['count'] }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        @if($project->demands && $project->demands->isNotEmpty())
            <section class="pd-panel">
                <div class="pd-body">
                    <details class="pd-toggle">
                        <summary>
                            <span><i class="ph-duotone ph-list-checks"></i> Produtos previstos no projeto</span>
                            <span class="pd-count">{{ $project->demands->count() }}</span>
                        </summary>

                        <div class="pd-toggle-body">
                            @foreach($project->demands as $demand)
                                <div class="pd-demand">
                                    <i class="ph-duotone ph-cube"></i>
                                    <span>{{ $demand->product?->name ?? 'Produto' }}</span>
                                    <strong>{{ $quantity($demand->target_quantity) }} {{ $unitLabel($demand->product?->unit) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </details>
                </div>
            </section>
        @endif

        <section class="pd-panel">
            <header class="pd-section-head">
                <div class="pd-title">
                    <span class="pd-title-icon deliveries"><i class="ph-duotone ph-package"></i></span>
                    <h2>Histórico de entregas</h2>
                </div>
                <span class="pd-count">{{ $myDeliveries->total() }}</span>
            </header>

            <div class="pd-body">
                <form method="GET" class="pd-filter">
                    <div class="pd-field">
                        <label for="pd-status">Status</label>
                        <select id="pd-status" name="status">
                            <option value="">Todos</option>
                            <option value="pending" @selected(request('status') === 'pending')>Pendentes</option>
                            <option value="approved" @selected(request('status') === 'approved')>Aprovadas</option>
                            <option value="rejected" @selected(request('status') === 'rejected')>Rejeitadas</option>
                            <option value="cancelled" @selected(request('status') === 'cancelled')>Canceladas</option>
                        </select>
                    </div>

                    <div class="pd-field">
                        <label for="pd-start">Data inicial</label>
                        <input id="pd-start" type="date" name="start_date" value="{{ request('start_date') }}">
                    </div>

                    <div class="pd-field">
                        <label for="pd-end">Data final</label>
                        <input id="pd-end" type="date" name="end_date" value="{{ request('end_date') }}">
                    </div>

                    <div class="pd-actions">
                        <button type="submit" class="pd-button primary"><i class="ph ph-funnel"></i> Filtrar</button>

                        @if(request()->hasAny(['status', 'start_date', 'end_date', 'product_id']))
                            <a class="pd-button" href="{{ $tenantSlug ? route('associate.projects.show', ['tenant' => $tenantSlug, 'project' => $project->id]) : url('/') }}">
                                <i class="ph ph-x"></i> Limpar
                            </a>
                        @endif
                    </div>
                </form>

                @if($visibleDeliveries->isEmpty())
                    <div class="pd-empty"><div><i class="ph-duotone ph-package"></i><strong>Nenhuma entrega encontrada</strong></div></div>
                @else
                    <div class="pd-list">
                        @foreach($visibleDeliveries as $delivery)
                            @php
                                $deliveryStatus = $statusValue($delivery->status ?? null);
                                $product = $delivery->product ?? $delivery->projectDemand?->product;
                                $deliveryUnit = $unitLabel($delivery->unit ?? $product?->unit ?? null);
                                $deliveryValue = (float) $delivery->quantity * (float) $delivery->unit_price;
                            @endphp

                            <article class="pd-delivery">
                                <span class="pd-row-icon"><i class="ph-duotone ph-package"></i></span>

                                <div class="pd-copy">
                                    <div class="pd-line">
                                        <strong>{{ $product?->name ?? 'Produto' }}</strong>
                                        <span class="pd-badge {{ $deliveryStatus }}">{{ $statusLabel($delivery->status ?? null) }}</span>
                                    </div>
                                    <div class="pd-meta"><span><i class="ph ph-calendar-blank"></i> {{ $delivery->delivery_date?->format('d/m/Y') ?? 'Data não informada' }}</span></div>
                                </div>

                                <div class="pd-data"><span>Quantidade</span><strong>{{ $quantity($delivery->quantity) }} {{ $deliveryUnit }}</strong></div>
                                <div class="pd-data value"><span>Valor estimado</span><strong>{{ $money($deliveryValue) }}</strong></div>
                                <div class="pd-data"><span>Situação</span><strong>{{ $statusLabel($delivery->status ?? null) }}</strong></div>
                            </article>
                        @endforeach
                    </div>

                    @if($myDeliveries->hasPages())
                        <div class="pd-pagination">
                            {{ $myDeliveries->withQueryString()->links('vendor.pagination.bento') }}
                        </div>
                    @endif
                @endif
            </div>
        </section>

        @if($visibleReceipts->isNotEmpty())
            <section class="pd-panel">
                <header class="pd-section-head">
                    <div class="pd-title">
                        <span class="pd-title-icon receipts"><i class="ph-duotone ph-receipt"></i></span>
                        <h2>Comprovantes de pagamento</h2>
                    </div>
                    <span class="pd-count">{{ $visibleReceipts->count() }}</span>
                </header>

                <div class="pd-body">
                    <div class="pd-list">
                        @foreach($visibleReceipts as $receipt)
                            <article class="pd-receipt">
                                <span class="pd-row-icon"><i class="ph-duotone ph-receipt"></i></span>

                                <div class="pd-copy">
                                    <div class="pd-line"><strong>{{ $receipt->formatted_number }}</strong></div>
                                    <div class="pd-meta"><span><i class="ph ph-calendar-blank"></i> {{ $receipt->issued_at?->format('d/m/Y') ?? 'Data não informada' }}</span></div>
                                </div>

                                <div class="pd-data"><span>Período</span><strong>
                                    @if($receipt->from_date && $receipt->to_date)
                                        {{ $receipt->from_date->format('d/m/Y') }} a {{ $receipt->to_date->format('d/m/Y') }}
                                    @else
                                        Não informado
                                    @endif
                                </strong></div>

                                <div class="pd-data"><span>Entregas</span><strong>{{ count($receipt->delivery_ids ?? []) }}</strong></div>
                                <div class="pd-data"><span>Observação</span><strong>{{ $receipt->notes ?: 'Sem observação' }}</strong></div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif
    @endif
</main>
@endsection