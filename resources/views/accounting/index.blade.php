@extends('layouts.bento')

@section('title', 'Portal Contábil')
@section('page-title', 'Portal Contábil')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Contabilidade')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make(
        'accounting',
        'queue',
        $tenant->slug
    );
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/accounting-portal.css') }}">
@endpush

@section('content')
@once
<link
    rel="stylesheet"
    href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
>
@endonce

<style>
.accounting-dashboard{
    --green:#168a4d;
    --green-soft:#eaf8ef;
    --green-border:#cfe8d7;

    --blue:#2563eb;
    --blue-soft:#eef4ff;
    --blue-border:#d4e2f8;

    --purple:#7c3aed;
    --purple-soft:#f4f0ff;
    --purple-border:#e3d8f7;

    --amber:#c87408;
    --amber-soft:#fff7e8;
    --amber-border:#efdcb8;

    --red:#cf3f3f;
    --red-soft:#fff0f0;
    --red-border:#f0cccc;

    --slate:#64748b;
    --slate-soft:#f1f5f9;

    --text:var(--color-text,#102018);
    --text-2:var(--color-text-secondary,#52645a);
    --muted:var(--color-text-muted,#809087);
    --border:var(--color-border,#dce6df);
    --border-strong:var(--color-border-strong,#c8d6cd);
    --surface:#fff;
    --soft:var(--color-surface-soft,#f8faf9);
    --muted-surface:#eef4f0;

    display:grid;
    width:min(100%,1280px);
    min-width:0;
    grid-column:1/-1;
    gap:.82rem;
    margin:0 auto;
    padding-bottom:1rem;
    color:var(--text)
}

.accounting-dashboard *,
.accounting-dashboard *::before,
.accounting-dashboard *::after{
    box-sizing:border-box
}

.accounting-dashboard a{
    color:inherit;
    text-decoration:none
}

/* =========================================================
   SUPERFÍCIES — mesma construção do dashboard associado
   ========================================================= */

.accounting-dashboard .dashboard-section{
    min-width:0;
    overflow:hidden;
    border:1px solid var(--border);
    border-radius:15px;
    background:var(--surface);
    box-shadow:0 5px 18px rgba(25,61,39,.05)
}

.accounting-dashboard .dashboard-section-head{
    display:grid;
    min-height:64px;
    grid-template-columns:minmax(0,1fr) auto;
    gap:.62rem;
    align-items:center;
    padding:.68rem .76rem;
    border-bottom:1px solid var(--border);
    background:#fff
}

.accounting-dashboard .dashboard-section-title{
    display:grid;
    min-width:0;
    grid-template-columns:40px minmax(0,1fr);
    gap:.58rem;
    align-items:center
}

.accounting-dashboard .dashboard-section-icon{
    display:grid;
    width:40px;
    height:40px;
    place-items:center;
    border-radius:11px;
    background:var(--slate-soft);
    color:var(--slate)
}

.accounting-dashboard .dashboard-section-icon.finance{
    background:var(--green-soft);
    color:var(--green)
}

.accounting-dashboard .dashboard-section-icon.queue{
    background:var(--amber-soft);
    color:var(--amber)
}

.accounting-dashboard .dashboard-section-icon i{
    font-size:1.05rem
}

.accounting-dashboard .dashboard-section-copy{
    min-width:0
}

.accounting-dashboard .dashboard-section-copy h2,
.accounting-dashboard .dashboard-section-copy p{
    margin:0
}

.accounting-dashboard .dashboard-section-copy h2{
    color:var(--text);
    font-size:.95rem;
    font-weight:840;
    letter-spacing:-.02em
}

.accounting-dashboard .dashboard-section-copy p{
    margin-top:.08rem;
    color:var(--muted);
    font-size:.74rem;
    line-height:1.42
}

.accounting-dashboard .dashboard-section-actions{
    display:flex;
    gap:.34rem;
    align-items:center
}

.accounting-dashboard .section-count{
    display:inline-flex;
    min-height:30px;
    gap:.25rem;
    align-items:center;
    padding:.27rem .43rem;
    border-radius:999px;
    background:var(--muted-surface);
    color:var(--text-2);
    font-size:.68rem;
    font-weight:770;
    white-space:nowrap
}

.accounting-dashboard .section-count i{
    color:var(--muted);
    font-size:.78rem
}

.accounting-dashboard .dashboard-section-action{
    display:inline-flex;
    min-height:38px;
    gap:.3rem;
    align-items:center;
    justify-content:center;
    padding:.4rem .54rem;
    border:1px solid var(--border);
    border-radius:9px;
    background:#fff;
    color:var(--text-2);
    font-size:.73rem;
    font-weight:760;
    white-space:nowrap
}

.accounting-dashboard .dashboard-section-action:hover,
.accounting-dashboard .dashboard-section-action:focus-visible{
    border-color:var(--blue-border);
    background:var(--blue-soft);
    color:var(--blue);
    outline:none
}

/* =========================================================
   VISÃO CONTÁBIL — espelho do resumo financeiro do associado
   ========================================================= */

.accounting-dashboard .accounting-overview{
    display:grid;
    min-width:0;
    grid-template-columns:minmax(290px,.9fr) minmax(0,1.1fr)
}

.accounting-dashboard .accounting-primary{
    display:grid;
    min-width:0;
    min-height:230px;
    align-content:center;
    gap:.9rem;
    padding:1rem;
    border-right:1px solid var(--border);
    background:var(--soft)
}

.accounting-dashboard .accounting-primary-label{
    display:inline-flex;
    width:max-content;
    max-width:100%;
    gap:.32rem;
    align-items:center;
    color:var(--green);
    font-size:.74rem;
    font-weight:790
}

.accounting-dashboard .accounting-primary-label i{
    font-size:.92rem
}

.accounting-dashboard .accounting-primary-value{
    margin-top:.34rem;
    overflow-wrap:anywhere;
    color:var(--text);
    font-size:clamp(1.8rem,4vw,2.45rem);
    font-weight:875;
    letter-spacing:-.045em;
    line-height:1
}

.accounting-dashboard .accounting-primary-helper{
    max-width:420px;
    margin-top:.42rem;
    color:var(--text-2);
    font-size:.77rem;
    line-height:1.5
}

.accounting-dashboard .accounting-primary-foot{
    display:flex;
    flex-wrap:wrap;
    gap:.35rem .75rem;
    align-items:center;
    justify-content:space-between
}

.accounting-dashboard .accounting-primary-foot span{
    color:var(--muted);
    font-size:.68rem
}

.accounting-dashboard .accounting-primary-foot strong{
    color:var(--text);
    font-weight:810
}

.accounting-dashboard .accounting-secondary{
    display:grid;
    min-width:0;
    grid-template-rows:auto 1fr;
    background:#fff
}

.accounting-dashboard .accounting-kpis{
    display:grid;
    min-width:0;
    grid-template-columns:repeat(2,minmax(0,1fr));
    border-bottom:1px solid var(--border)
}

.accounting-dashboard .accounting-kpi{
    min-width:0;
    padding:.72rem;
    background:#fff
}

.accounting-dashboard .accounting-kpi+.accounting-kpi{
    border-left:1px solid var(--border)
}

.accounting-dashboard .accounting-kpi-head{
    display:flex;
    min-width:0;
    gap:.38rem;
    align-items:center
}

.accounting-dashboard .accounting-kpi-icon{
    display:grid;
    width:30px;
    height:30px;
    flex:0 0 auto;
    place-items:center;
    border-radius:8px;
    background:var(--blue-soft);
    color:var(--blue)
}

.accounting-dashboard .accounting-kpi.workflow .accounting-kpi-icon{
    background:var(--purple-soft);
    color:var(--purple)
}

.accounting-dashboard .accounting-kpi-label{
    min-width:0;
    overflow:hidden;
    color:var(--muted);
    font-size:.68rem;
    font-weight:700;
    text-overflow:ellipsis;
    white-space:nowrap
}

.accounting-dashboard .accounting-kpi strong{
    display:block;
    margin-top:.32rem;
    overflow:hidden;
    color:var(--text);
    font-size:.86rem;
    font-weight:830;
    text-overflow:ellipsis;
    white-space:nowrap
}

.accounting-dashboard .accounting-workflow{
    min-width:0;
    padding:.78rem;
    background:#fff
}

.accounting-dashboard .workflow-head{
    display:flex;
    gap:.4rem;
    align-items:center;
    justify-content:space-between;
    margin-bottom:.72rem
}

.accounting-dashboard .workflow-head strong{
    color:var(--text-2);
    font-size:.7rem;
    font-weight:800;
    letter-spacing:.03em;
    text-transform:uppercase
}

.accounting-dashboard .workflow-head span{
    color:var(--muted);
    font-size:.65rem;
    font-weight:680
}

.accounting-dashboard .workflow-steps{
    display:grid;
    gap:.44rem
}

.accounting-dashboard .workflow-step{
    display:grid;
    grid-template-columns:30px minmax(0,1fr);
    gap:.46rem;
    align-items:center;
    min-height:46px;
    padding:.38rem .46rem;
    border:1px solid var(--border);
    border-radius:8px;
    background:#fff
}

.accounting-dashboard .workflow-step-icon{
    display:grid;
    width:30px;
    height:30px;
    place-items:center;
    border-radius:8px;
    background:var(--slate-soft);
    color:var(--slate)
}

.accounting-dashboard .workflow-step:nth-child(1) .workflow-step-icon{
    background:var(--amber-soft);
    color:var(--amber)
}

.accounting-dashboard .workflow-step:nth-child(2) .workflow-step-icon{
    background:var(--blue-soft);
    color:var(--blue)
}

.accounting-dashboard .workflow-step:nth-child(3) .workflow-step-icon{
    background:var(--green-soft);
    color:var(--green)
}

.accounting-dashboard .workflow-step-copy strong,
.accounting-dashboard .workflow-step-copy span{
    display:block
}

.accounting-dashboard .workflow-step-copy strong{
    color:var(--text);
    font-size:.69rem;
    font-weight:800
}

.accounting-dashboard .workflow-step-copy span{
    margin-top:.05rem;
    color:var(--muted);
    font-size:.59rem
}

/* =========================================================
   FILA — baseada nas linhas de projetos/entregas do dashboard
   ========================================================= */

.accounting-dashboard .queue-wrap{
    min-width:0;
    padding:.68rem .72rem .72rem
}

.accounting-dashboard .queue-table{
    min-width:0;
    overflow:hidden;
    border:1px solid var(--border);
    border-radius:10px;
    background:#fff
}

.accounting-dashboard .queue-table-head,
.accounting-dashboard .acc-queue-row{
    display:grid;
    min-width:0;
    grid-template-columns:minmax(260px,1fr) 110px 110px 34px;
    gap:.42rem;
    align-items:center
}

.accounting-dashboard .queue-table-head{
    min-height:40px;
    padding:.38rem .58rem;
    border-bottom:1px solid var(--border-strong);
    background:var(--soft);
    color:#6f7c74;
    font-size:.62rem;
    font-weight:790;
    letter-spacing:.035em;
    text-transform:uppercase
}

.accounting-dashboard .queue-table-head span:not(:first-child){
    text-align:right
}

.accounting-dashboard .acc-queue{
    display:grid;
    min-width:0
}

.accounting-dashboard .acc-queue-row{
    --tone:var(--purple);
    --tone-soft:var(--purple-soft);

    min-height:70px;
    padding:.54rem .58rem;
    border-bottom:1px solid var(--border);
    background:#fff
}

.accounting-dashboard .acc-queue-row:last-child{
    border-bottom:0
}

.accounting-dashboard .acc-queue-row.acc-tone-warning{
    --tone:var(--amber);
    --tone-soft:var(--amber-soft)
}

.accounting-dashboard .acc-queue-row.acc-tone-danger{
    --tone:var(--red);
    --tone-soft:var(--red-soft)
}

.accounting-dashboard .acc-queue-row.acc-tone-success{
    --tone:var(--green);
    --tone-soft:var(--green-soft)
}

.accounting-dashboard .acc-queue-row.acc-tone-info{
    --tone:var(--blue);
    --tone-soft:var(--blue-soft)
}

.accounting-dashboard .acc-queue-row.acc-tone-cyan{
    --tone:var(--blue);
    --tone-soft:var(--blue-soft)
}

.accounting-dashboard .acc-queue-row:hover,
.accounting-dashboard .acc-queue-row:focus-visible{
    outline:none;
    background:#fbfcfb;
    box-shadow:inset 3px 0 0 var(--tone)
}

.accounting-dashboard .queue-main{
    display:flex;
    min-width:0;
    gap:.46rem;
    align-items:center
}

.accounting-dashboard .acc-queue-icon{
    display:grid;
    width:34px;
    height:34px;
    flex:0 0 auto;
    place-items:center;
    border-radius:8px;
    background:var(--tone-soft);
    color:var(--tone)
}

.accounting-dashboard .acc-queue-icon i{
    font-size:.95rem
}

.accounting-dashboard .acc-queue-copy{
    min-width:0
}

.accounting-dashboard .acc-queue-copy strong,
.accounting-dashboard .acc-queue-copy span{
    display:block;
    min-width:0;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap
}

.accounting-dashboard .acc-queue-copy strong{
    color:var(--text);
    font-size:.77rem;
    font-weight:810
}

.accounting-dashboard .acc-queue-copy span{
    margin-top:.07rem;
    color:var(--muted);
    font-size:.64rem
}

.accounting-dashboard .queue-status{
    justify-self:end;
    color:var(--text-2);
    font-size:.68rem;
    font-weight:730;
    text-align:right
}

.accounting-dashboard .acc-queue-count{
    display:inline-grid;
    min-width:30px;
    min-height:30px;
    place-items:center;
    justify-self:end;
    padding:.2rem .4rem;
    border-radius:8px;
    background:var(--tone-soft);
    color:var(--tone);
    font-size:.67rem;
    font-weight:840
}

.accounting-dashboard .acc-queue-arrow{
    display:grid;
    width:30px;
    height:30px;
    place-items:center;
    justify-self:end;
    border:1px solid var(--border);
    border-radius:8px;
    background:#fff;
    color:var(--tone)
}

.accounting-dashboard .acc-empty{
    margin:0;
    min-height:180px;
    border:0
}

/* =========================================================
   RESPONSIVO — igual filosofia do dashboard associado
   ========================================================= */

@media(max-width:1050px){
    .accounting-dashboard .accounting-overview{
        grid-template-columns:1fr
    }

    .accounting-dashboard .accounting-primary{
        min-height:195px;
        border-right:0;
        border-bottom:1px solid var(--border)
    }
}

@media(max-width:720px){
    .accounting-dashboard .dashboard-section-copy p{
        display:none
    }

    .accounting-dashboard .accounting-kpis{
        grid-template-columns:1fr
    }

    .accounting-dashboard .accounting-kpi{
        display:grid;
        min-height:54px;
        grid-template-columns:minmax(0,1fr) auto;
        gap:.5rem;
        align-items:center;
        padding:.58rem .65rem
    }

    .accounting-dashboard .accounting-kpi+.accounting-kpi{
        border-top:1px solid var(--border);
        border-left:0
    }

    .accounting-dashboard .accounting-kpi strong{
        margin-top:0;
        text-align:right
    }

    .accounting-dashboard .queue-table{
        overflow:visible;
        border:0;
        background:transparent
    }

    .accounting-dashboard .queue-table-head{
        display:none
    }

    .accounting-dashboard .acc-queue{
        gap:.46rem
    }

    .accounting-dashboard .acc-queue-row{
        position:relative;
        display:grid;
        min-height:0;
        grid-template-columns:minmax(0,1fr) auto;
        gap:.42rem;
        padding:.6rem;
        border:1px solid var(--border);
        border-left:3px solid var(--tone);
        border-radius:10px;
        background:#fff
    }

    .accounting-dashboard .queue-main{
        grid-column:1/-1
    }

    .accounting-dashboard .queue-status{
        justify-self:start;
        text-align:left
    }

    .accounting-dashboard .acc-queue-count{
        align-self:center;
        grid-column:2;
        grid-row:2
    }

    .accounting-dashboard .acc-queue-arrow{
        position:absolute;
        top:.6rem;
        right:.6rem
    }

    .accounting-dashboard .acc-queue-copy{
        padding-right:2.25rem
    }
}

@media(max-width:520px){
    .accounting-dashboard .dashboard-section-head{
        grid-template-columns:minmax(0,1fr) auto;
        min-height:60px;
        padding:.6rem
    }

    .accounting-dashboard .dashboard-section-title{
        grid-template-columns:36px minmax(0,1fr);
        gap:.5rem
    }

    .accounting-dashboard .dashboard-section-icon{
        width:36px;
        height:36px;
        border-radius:9px
    }

    .accounting-dashboard .dashboard-section-action span{
        display:none
    }

    .accounting-dashboard .dashboard-section-action{
        width:36px;
        min-height:36px;
        padding:0
    }

    .accounting-dashboard .section-count{
        display:none
    }

    .accounting-dashboard .accounting-primary{
        min-height:175px;
        padding:.82rem
    }

    .accounting-dashboard .accounting-primary-value{
        font-size:1.72rem
    }

    .accounting-dashboard .accounting-workflow{
        padding:.62rem
    }

    .accounting-dashboard .workflow-head span{
        display:none
    }

    .accounting-dashboard .queue-wrap{
        padding:.55rem
    }
}

@media(prefers-reduced-motion:reduce){
    .accounting-dashboard *,
    .accounting-dashboard *::before,
    .accounting-dashboard *::after{
        animation-duration:.01ms!important;
        animation-iteration-count:1!important;
        scroll-behavior:auto!important;
        transition-duration:.01ms!important
    }
}
</style>

<main
    class="acc-shell accounting-dashboard"
    data-accounting-page="queue"
    data-queue-url="{{ route('accounting.data.queue', ['tenant' => $tenant->slug]) }}"
    data-processes-url="{{ route('accounting.processes.index', ['tenant' => $tenant->slug]) }}"
>
    <section
        class="dashboard-section"
        aria-labelledby="accounting-overview-title"
    >
        <header class="dashboard-section-head">
            <div class="dashboard-section-title">
                <span
                    class="dashboard-section-icon finance"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-wallet"></i>
                </span>

                <div class="dashboard-section-copy">
                    <h2 id="accounting-overview-title">
                        Visão contábil
                    </h2>

                    <p>
                        Valores e andamento dos faturamentos da organização.
                    </p>
                </div>
            </div>

            <div class="dashboard-section-actions">
                <a
                    class="dashboard-section-action"
                    href="{{ route('accounting.documents.verify', ['tenant' => $tenant->slug]) }}"
                >
                    <i class="ph-fill ph-qr-code" aria-hidden="true"></i>
                    <span>Verificar documento</span>
                </a>
                <a
                    class="dashboard-section-action"
                    href="{{ route('accounting.processes.index', ['tenant' => $tenant->slug]) }}"
                >
                    <span>Processos</span>
                    <i class="ph-fill ph-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </header>

        <div
            class="accounting-overview"
            data-queue-summary
            aria-live="polite"
        >
            <article class="accounting-primary">
                <div>
                    <div class="accounting-primary-label">
                        <i class="ph-fill ph-clock-countdown" aria-hidden="true"></i>
                        Carregando saldo
                    </div>

                    <div class="accounting-primary-value">
                        ...
                    </div>

                    <div class="accounting-primary-helper">
                        Consultando os faturamentos em andamento.
                    </div>
                </div>
            </article>

            <div class="accounting-secondary">
                <div class="accounting-kpis">
                    <article class="accounting-kpi">
                        <div class="accounting-kpi-head">
                            <span
                                class="accounting-kpi-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-receipt"></i>
                            </span>

                            <span class="accounting-kpi-label">
                                Processos
                            </span>
                        </div>

                        <strong>...</strong>
                    </article>

                    <article class="accounting-kpi workflow">
                        <div class="accounting-kpi-head">
                            <span
                                class="accounting-kpi-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-check-circle"></i>
                            </span>

                            <span class="accounting-kpi-label">
                                Fluxo
                            </span>
                        </div>

                        <strong>...</strong>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section
        class="dashboard-section"
        aria-labelledby="accounting-queue-title"
    >
        <header class="dashboard-section-head">
            <div class="dashboard-section-title">
                <span
                    class="dashboard-section-icon queue"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-warning-circle"></i>
                </span>

                <div class="dashboard-section-copy">
                    <h2 id="accounting-queue-title">
                        Ações que exigem atenção
                    </h2>

                    <p>
                        Pendências organizadas pela próxima ação necessária.
                    </p>
                </div>
            </div>

            <div class="dashboard-section-actions">
                <span
                    class="section-count"
                    data-queue-total
                >
                    <i class="ph-fill ph-folder"></i>
                    ...
                </span>

                <a
                    class="dashboard-section-action"
                    href="{{ route('accounting.processes.index', ['tenant' => $tenant->slug]) }}"
                >
                    <span>Todos</span>
                    <i class="ph-fill ph-arrow-right"></i>
                </a>
            </div>
        </header>

        <div class="queue-wrap">
            <div class="queue-table">
                <div
                    class="queue-table-head"
                    aria-hidden="true"
                >
                    <span>Pendência</span>
                    <span>Situação</span>
                    <span>Processos</span>
                    <span></span>
                </div>

                <div
                    class="acc-queue"
                    data-queue-list
                    aria-live="polite"
                ></div>
            </div>
        </div>
    </section>
</main>
@endsection

@push('scripts')
    <script
        src="{{ asset('assets/accounting-portal.js') }}"
        defer
    ></script>
@endpush
