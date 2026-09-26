@extends('layouts.bento')

@section('title', 'Faturamentos')
@section('page-title', 'Faturamentos')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Contabilidade')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('accounting', 'processes', $tenant->slug);
@endphp

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/accounting-portal.css') }}">
@endpush

@section('content')
@once
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
@endonce

<style>
/*
 * Faturamentos — visual alinhado diretamente ao Project Workspace do associado.
 *
 * IMPORTANTE:
 * - a paleta abaixo é a MESMA do project-workspace;
 * - accounting-portal.css continua carregado;
 * - accounting-portal.js continua carregado;
 * - todos os hooks data-* e names usados pelo JS foram preservados.
 */

.accounting-processes{
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

    display:grid;
    width:min(100%,1380px);
    min-width:0;
    grid-column:1/-1;
    gap:.78rem;
    margin:0 auto;
    padding-bottom:1rem;
    color:var(--text)
}

.accounting-processes *,
.accounting-processes *::before,
.accounting-processes *::after{
    box-sizing:border-box
}

/* =========================================================
   CABEÇALHO — mesma geometria do project-head
   ========================================================= */

.accounting-processes .acc-topbar{
    --tone:var(--purple);
    --tone-soft:var(--purple-soft);

    display:grid;
    min-width:0;
    min-height:76px;
    grid-template-columns:minmax(0,1fr) auto;
    gap:.7rem;
    align-items:center;
    padding:.75rem .8rem;
    border:1px solid var(--border);
    border-radius:12px;
    background:
        radial-gradient(
            circle at 100% 0,
            color-mix(in srgb,var(--tone) 10%,transparent),
            transparent 19rem
        ),
        linear-gradient(180deg,#fbfdfb,#fff);
    box-shadow:var(--shadow)
}

.accounting-processes .acc-heading{
    display:grid;
    min-width:0;
    grid-template-columns:42px minmax(0,1fr);
    gap:.65rem;
    align-items:center
}

.accounting-processes .acc-heading-icon{
    display:grid;
    width:42px;
    height:42px;
    flex:0 0 auto;
    place-items:center;
    border-radius:9px;
    background:var(--tone-soft);
    color:var(--tone)
}

.accounting-processes .acc-heading-icon i{
    font-size:1.05rem
}

.accounting-processes .acc-heading-copy{
    min-width:0
}

.accounting-processes .acc-eyebrow{
    display:flex;
    gap:.3rem;
    align-items:center;
    margin:0;
    color:var(--purple);
    font-size:.62rem;
    font-weight:820;
    letter-spacing:.045em;
    text-transform:uppercase
}

.accounting-processes .acc-eyebrow i{
    font-size:.75rem
}

.accounting-processes .acc-heading h1{
    margin:.06rem 0 0;
    overflow:hidden;
    color:var(--text);
    font-size:clamp(1.03rem,2vw,1.25rem);
    font-weight:850;
    letter-spacing:-.03em;
    line-height:1.24;
    text-overflow:ellipsis;
    white-space:nowrap
}

.accounting-processes .acc-heading-description{
    display:flex;
    min-width:0;
    flex-wrap:wrap;
    gap:.18rem .65rem;
    align-items:center;
    margin-top:.2rem;
    color:var(--muted);
    font-size:.7rem;
    font-weight:600
}

.accounting-processes .acc-heading-description span{
    display:inline-flex;
    min-width:0;
    gap:.26rem;
    align-items:center
}

.accounting-processes .acc-heading-description i{
    color:var(--blue);
    font-size:.78rem
}

.accounting-processes .acc-inline-actions{
    display:flex;
    flex-wrap:wrap;
    gap:.36rem;
    align-items:center;
    justify-content:flex-end
}

/* =========================================================
   BOTÕES — mesma linguagem do Project Workspace
   ========================================================= */

.accounting-processes .acc-button{
    display:inline-flex;
    min-height:38px;
    gap:.34rem;
    align-items:center;
    justify-content:center;
    padding:.42rem .62rem;
    border:1px solid var(--border-strong);
    border-radius:8px;
    background:#fff;
    color:var(--text);
    cursor:pointer;
    font:inherit;
    font-size:.7rem;
    font-weight:780;
    line-height:1;
    text-decoration:none;
    white-space:nowrap;
    transition:
        border-color .15s ease,
        background .15s ease,
        color .15s ease
}

.accounting-processes .acc-button i{
    font-size:.9rem;
    line-height:1
}

.accounting-processes .acc-button:hover,
.accounting-processes .acc-button:focus-visible{
    border-color:var(--blue-border);
    background:var(--blue-soft);
    color:var(--blue);
    outline:0
}

.accounting-processes .acc-button-primary{
    border-color:var(--green);
    background:linear-gradient(180deg,#25a95f,#1d914f);
    color:#fff
}

.accounting-processes .acc-button-primary:hover,
.accounting-processes .acc-button-primary:focus-visible{
    border-color:var(--green-strong);
    background:var(--green-strong);
    color:#fff
}

.accounting-processes .acc-icon-button{
    width:38px;
    padding:0
}

/* =========================================================
   SECTION SHELL — mesma construção do workspace
   ========================================================= */

.accounting-processes .acc-panel{
    min-width:0;
    overflow:hidden;
    border:1px solid var(--border);
    border-radius:12px;
    background:var(--surface);
    box-shadow:var(--shadow)
}

.accounting-processes .process-section-head{
    display:flex;
    min-height:62px;
    gap:.65rem;
    align-items:center;
    justify-content:space-between;
    padding:.65rem .72rem;
    border-bottom:1px solid var(--border);
    background:linear-gradient(180deg,#fafcfb,#fff)
}

.accounting-processes .process-section-title{
    display:flex;
    min-width:0;
    gap:.58rem;
    align-items:center
}

.accounting-processes .process-section-icon{
    display:grid;
    width:39px;
    height:39px;
    flex:0 0 auto;
    place-items:center;
    border-radius:9px;
    background:var(--purple-soft);
    color:var(--purple)
}

.accounting-processes .process-section-icon i{
    font-size:1rem
}

.accounting-processes .process-section-copy{
    min-width:0
}

.accounting-processes .process-section-copy h2,
.accounting-processes .process-section-copy p{
    margin:0
}

.accounting-processes .process-section-copy h2{
    color:var(--text);
    font-size:.92rem;
    font-weight:840;
    letter-spacing:-.02em
}

.accounting-processes .process-section-copy p{
    margin-top:.08rem;
    color:var(--muted);
    font-size:.69rem;
    line-height:1.35
}

.accounting-processes .process-section-actions{
    display:flex;
    gap:.32rem;
    align-items:center
}

.accounting-processes .process-section-count{
    display:inline-flex;
    min-height:29px;
    gap:.25rem;
    align-items:center;
    padding:.25rem .48rem;
    border-radius:999px;
    background:var(--slate-soft);
    color:var(--text2);
    font-size:.65rem;
    font-weight:780;
    white-space:nowrap
}

.accounting-processes .process-section-count i{
    color:var(--purple);
    font-size:.78rem
}

/* =========================================================
   FILTROS
   ========================================================= */

.accounting-processes .acc-filters{
    display:grid;
    min-width:0;
    grid-template-columns:
        minmax(260px,1.18fr)
        minmax(175px,.72fr)
        minmax(190px,.82fr)
        minmax(190px,.82fr)
        auto;
    gap:.48rem;
    align-items:end;
    padding:.7rem;
    border-bottom:1px solid var(--border);
    background:#fff
}

.accounting-processes .acc-field{
    display:grid;
    min-width:0;
    gap:.24rem
}

.accounting-processes .acc-field>span{
    display:flex;
    min-width:0;
    gap:.28rem;
    align-items:center;
    color:var(--text2);
    font-size:.63rem;
    font-weight:740
}

.accounting-processes .acc-field>span i{
    color:var(--blue);
    font-size:.75rem
}

.accounting-processes .acc-input,
.accounting-processes .acc-select{
    width:100%;
    min-width:0;
    min-height:40px;
    border:1px solid var(--border-strong);
    border-radius:8px;
    outline:0;
    background:#fff;
    color:var(--text);
    font:inherit;
    font-size:.72rem
}

.accounting-processes .acc-input{
    padding:.48rem .58rem
}

.accounting-processes .acc-select{
    padding:.48rem .58rem
}

.accounting-processes .acc-input::placeholder{
    color:var(--muted)
}

.accounting-processes .acc-input:focus,
.accounting-processes .acc-select:focus{
    border-color:var(--blue);
    box-shadow:0 0 0 3px rgba(52,120,212,.1)
}

.accounting-processes .acc-filter-actions{
    display:flex;
    gap:.32rem;
    align-items:center;
    justify-content:flex-end
}

.accounting-processes .acc-advanced{
    grid-column:1/-1;
    min-width:0;
    margin-top:.1rem;
    padding-top:.5rem;
    border-top:1px solid var(--border)
}

.accounting-processes .acc-advanced>summary{
    display:inline-flex;
    min-height:34px;
    gap:.34rem;
    align-items:center;
    padding:.34rem .48rem;
    border:1px solid transparent;
    border-radius:8px;
    color:var(--blue);
    cursor:pointer;
    font-size:.68rem;
    font-weight:780;
    list-style:none;
    user-select:none
}

.accounting-processes .acc-advanced>summary::-webkit-details-marker{
    display:none
}

.accounting-processes .acc-advanced>summary:hover,
.accounting-processes .acc-advanced>summary:focus-visible{
    border-color:var(--blue-border);
    background:var(--blue-soft);
    outline:0
}

.accounting-processes .acc-advanced>summary i{
    font-size:.84rem
}

.accounting-processes .acc-advanced .acc-advanced-caret{
    transition:transform .15s ease
}

.accounting-processes .acc-advanced[open] .acc-advanced-caret{
    transform:rotate(180deg)
}

.accounting-processes .acc-advanced-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:.48rem;
    margin-top:.48rem;
    padding:.58rem;
    border:1px solid var(--border);
    border-radius:9px;
    background:var(--soft)
}

/* =========================================================
   TABELA — mesma linguagem das tabelas do Project Workspace
   ========================================================= */

.accounting-processes .acc-table-wrap{
    min-width:0;
    overflow-x:auto;
    background:#fff
}

.accounting-processes .acc-table{
    width:100%;
    min-width:980px;
    border-collapse:collapse;
    color:var(--text2);
    font-size:.69rem
}

.accounting-processes .acc-table th{
    min-height:38px;
    padding:.35rem .58rem;
    border-bottom:1px solid var(--border-strong);
    background:linear-gradient(180deg,#f5f8f6,#eff4f1);
    color:#6f7c74;
    font-size:.58rem;
    font-weight:820;
    letter-spacing:.045em;
    text-align:left;
    text-transform:uppercase;
    white-space:nowrap
}

.accounting-processes .acc-table td{
    min-height:58px;
    padding:.48rem .58rem;
    border-bottom:1px solid var(--border);
    background:#fff;
    vertical-align:middle
}

.accounting-processes .acc-table tbody tr:last-child td{
    border-bottom:0
}

.accounting-processes .acc-table tbody tr:hover td{
    background:#fafcfb
}

.accounting-processes .acc-table td strong{
    color:var(--text);
    font-size:.72rem;
    font-weight:800
}

.accounting-processes .acc-table td a{
    color:inherit;
    text-decoration:none
}

/*
 * O renderer do accounting-portal.js cria badges e estados usando classes acc-*.
 * Estas regras são intencionalmente abrangentes para aproximar o conteúdo
 * dinâmico da mesma paleta, sem trocar hooks nem lógica do renderer.
 */
.accounting-processes :where(
    .acc-badge,
    .acc-status,
    .acc-pill,
    [class*="status-badge"]
){
    border-radius:999px;
    font-size:.59rem;
    font-weight:800
}

.accounting-processes :where(
    .acc-badge,
    .acc-status,
    .acc-pill
).paid,
.accounting-processes :where(
    .acc-badge,
    .acc-status,
    .acc-pill
).authorized{
    background:var(--green-soft);
    color:var(--green)
}

.accounting-processes :where(
    .acc-badge,
    .acc-status,
    .acc-pill
).draft{
    background:var(--amber-soft);
    color:#975f0f
}

.accounting-processes :where(
    .acc-badge,
    .acc-status,
    .acc-pill
).pending_payment,
.accounting-processes :where(
    .acc-badge,
    .acc-status,
    .acc-pill
).partially_paid,
.accounting-processes :where(
    .acc-badge,
    .acc-status,
    .acc-pill
).sent{
    background:var(--blue-soft);
    color:var(--blue)
}

.accounting-processes :where(
    .acc-badge,
    .acc-status,
    .acc-pill
).correction_requested,
.accounting-processes :where(
    .acc-badge,
    .acc-status,
    .acc-pill
).invalidated{
    background:var(--red-soft);
    color:#a43d3d
}

/* =========================================================
   MOBILE / PAGINAÇÃO
   ========================================================= */

.accounting-processes .acc-mobile-list{
    display:none
}

.accounting-processes .acc-pagination{
    display:flex;
    min-height:52px;
    flex-wrap:wrap;
    gap:.32rem;
    align-items:center;
    justify-content:center;
    padding:.58rem .72rem;
    border-top:1px solid var(--border);
    background:var(--soft)
}

.accounting-processes .acc-pagination:empty{
    display:none
}

.accounting-processes .acc-mobile-list>*{
    border-color:var(--border)!important;
    border-radius:10px!important;
    background:#fff!important;
    box-shadow:0 3px 12px rgba(18,42,27,.045)!important
}

@media(max-width:1180px){
    .accounting-processes .acc-filters{
        grid-template-columns:minmax(240px,1fr) repeat(2,minmax(180px,.72fr))
    }

    .accounting-processes .acc-field:nth-of-type(4),
    .accounting-processes .acc-filter-actions{
        grid-row:2
    }

    .accounting-processes .acc-field:nth-of-type(4){
        grid-column:1/3
    }

    .accounting-processes .acc-filter-actions{
        grid-column:3
    }
}

@media(max-width:820px){
    .accounting-processes{
        gap:.68rem
    }

    .accounting-processes .acc-topbar{
        min-height:0;
        grid-template-columns:1fr;
        gap:.55rem;
        padding:.62rem;
        border-radius:12px;
        box-shadow:none
    }

    .accounting-processes .acc-heading{
        grid-template-columns:39px minmax(0,1fr);
        gap:.58rem
    }

    .accounting-processes .acc-heading-icon{
        width:39px;
        height:39px;
        border-radius:9px
    }

    .accounting-processes .acc-heading h1{
        font-size:1rem
    }

    .accounting-processes .acc-heading-description .acc-head-long{
        display:none
    }

    .accounting-processes .acc-inline-actions{
        justify-content:flex-start
    }

    .accounting-processes .acc-inline-actions .acc-button{
        flex:1 1 auto
    }

    .accounting-processes .acc-panel{
        border-radius:12px;
        box-shadow:none
    }

    .accounting-processes .process-section-head{
        min-height:58px;
        padding:.6rem
    }

    .accounting-processes .process-section-icon{
        width:36px;
        height:36px;
        border-radius:8px
    }

    .accounting-processes .process-section-copy p{
        display:none
    }

    .accounting-processes .acc-filters{
        grid-template-columns:1fr 1fr;
        gap:.48rem;
        padding:.58rem
    }

    .accounting-processes .acc-field:nth-of-type(1){
        grid-column:1/-1
    }

    .accounting-processes .acc-field:nth-of-type(4),
    .accounting-processes .acc-filter-actions{
        grid-row:auto;
        grid-column:auto
    }

    .accounting-processes .acc-filter-actions{
        grid-column:1/-1;
        justify-content:stretch
    }

    .accounting-processes .acc-filter-actions .acc-button:first-child{
        flex:1
    }

    .accounting-processes .acc-input,
    .accounting-processes .acc-select{
        min-height:46px;
        font-size:16px
    }

    .accounting-processes .acc-advanced-grid{
        grid-template-columns:1fr 1fr
    }

    .accounting-processes .acc-table-wrap{
        display:none
    }

    .accounting-processes .acc-mobile-list{
        display:grid;
        gap:.5rem;
        padding:.58rem;
        background:var(--soft)
    }
}

@media(max-width:540px){
    .accounting-processes .acc-filters{
        grid-template-columns:1fr
    }

    .accounting-processes .acc-field,
    .accounting-processes .acc-filter-actions{
        grid-column:1!important
    }

    .accounting-processes .acc-advanced-grid{
        grid-template-columns:1fr;
        padding:.5rem
    }

    .accounting-processes .acc-inline-actions{
        display:grid;
        grid-template-columns:1fr auto;
        width:100%
    }

    .accounting-processes .process-section-count{
        display:none
    }
}

@media(prefers-reduced-motion:reduce){
    .accounting-processes *,
    .accounting-processes *::before,
    .accounting-processes *::after{
        animation-duration:.01ms!important;
        animation-iteration-count:1!important;
        scroll-behavior:auto!important;
        transition-duration:.01ms!important
    }
}
</style>

<main
    class="acc-shell accounting-processes"
    data-accounting-page="processes"
    data-processes-data-url="{{ route('accounting.data.processes', ['tenant' => $tenant->slug]) }}"
>
    <header class="acc-topbar">
        <div class="acc-heading">
            <span class="acc-heading-icon" aria-hidden="true">
                <i class="ph-fill ph-receipt"></i>
            </span>

            <div class="acc-heading-copy">
                <p class="acc-eyebrow">
                    <i class="ph-fill ph-calculator"></i>
                    Contabilidade
                </p>

                <h1>Faturamentos</h1>

                <div class="acc-heading-description">
                    <span>
                        <i class="ph ph-buildings"></i>
                        {{ $tenant->name }}
                    </span>

                    <span class="acc-head-long">
                        <i class="ph ph-arrows-left-right"></i>
                        Distribuições, cobrança e recebimento
                    </span>
                </div>
            </div>
        </div>

        <div class="acc-inline-actions">
            <a
                class="acc-button"
                href="{{ route('accounting.index', ['tenant' => $tenant->slug]) }}"
            >
                <i class="ph ph-list-checks" aria-hidden="true"></i>
                Visão geral
            </a>

            @can('create', \App\Models\CustomerBillingReceipt::class)
                <a
                    class="acc-button acc-button-primary"
                    href="{{ route('accounting.billings.create', ['tenant' => $tenant->slug]) }}"
                >
                    <i class="ph ph-plus" aria-hidden="true"></i>
                    Novo faturamento
                </a>
            @endcan
        </div>
    </header>

    <section class="acc-panel">
        <header class="process-section-head">
            <div class="process-section-title">
                <span class="process-section-icon" aria-hidden="true">
                    <i class="ph-fill ph-file-text"></i>
                </span>

                <div class="process-section-copy">
                    <h2>Processos de faturamento</h2>
                    <p>Localize cobranças e acompanhe a etapa financeira de cada processo.</p>
                </div>
            </div>

            <div class="process-section-actions">
                <span class="process-section-count">
                    <i class="ph ph-funnel"></i>
                    Filtros
                </span>
            </div>
        </header>

        <form class="acc-filters" data-process-filters>
            <label class="acc-field">
                <span>
                    <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                    Buscar
                </span>

                <input
                    class="acc-input"
                    type="search"
                    name="search"
                    maxlength="100"
                    autocomplete="off"
                    placeholder="Número, projeto ou destinatário"
                >
            </label>

            <label class="acc-field">
                <span>
                    <i class="ph ph-folder-open" aria-hidden="true"></i>
                    Projeto
                </span>

                <select class="acc-select" name="project">
                    <option value="">Todos os projetos</option>
                </select>
            </label>

            <label class="acc-field">
                <span>
                    <i class="ph ph-wallet" aria-hidden="true"></i>
                    Situação financeira
                </span>

                <select class="acc-select" name="financial_status">
                    <option value="">Todas</option>
                    <option value="draft">Rascunho</option>
                    <option value="pending_payment">Aguardando recebimento</option>
                    <option value="partially_paid">Parcialmente recebido</option>
                    <option value="paid">Recebido</option>
                </select>
            </label>

            <label class="acc-field">
                <span>
                    <i class="ph ph-warning-circle" aria-hidden="true"></i>
                    Pendência
                </span>

                <select class="acc-select" name="pending">
                    <option value="">Todas</option>
                    <option value="review_inconsistency">Corrigir erro de integridade</option>
                    <option value="review_draft">Completar rascunho</option>
                    <option value="review_closed">Enviar para autorização</option>
                    <option value="track_balance">Acompanhar saldo</option>
                </select>
            </label>

            <div class="acc-filter-actions">
                <button
                    class="acc-button acc-button-primary"
                    type="submit"
                >
                    <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
                    Filtrar
                </button>

                <button
                    class="acc-button acc-icon-button"
                    type="button"
                    data-clear-filters
                    aria-label="Limpar filtros"
                    title="Limpar filtros"
                >
                    <i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i>
                </button>
            </div>

            <details class="acc-advanced">
                <summary>
                    <i class="ph ph-sliders-horizontal" aria-hidden="true"></i>
                    Mais filtros
                    <i
                        class="ph ph-caret-down acc-advanced-caret"
                        aria-hidden="true"
                    ></i>
                </summary>

                <div class="acc-advanced-grid">
                    <label class="acc-field">
                        <span>
                            <i class="ph ph-buildings" aria-hidden="true"></i>
                            Organização compradora
                        </span>

                        <select class="acc-select" name="organization">
                            <option value="">Todas as organizações</option>
                        </select>
                    </label>

                    <label class="acc-field">
                        <span>
                            <i class="ph ph-user-square" aria-hidden="true"></i>
                            Cliente
                        </span>

                        <select class="acc-select" name="customer">
                            <option value="">Todos os clientes</option>
                        </select>
                    </label>

                    <label class="acc-field">
                        <span>
                            <i class="ph ph-calendar-blank" aria-hidden="true"></i>
                            Emissão a partir de
                        </span>

                        <input
                            class="acc-input"
                            type="date"
                            name="from"
                        >
                    </label>

                    <label class="acc-field">
                        <span>
                            <i class="ph ph-calendar-check" aria-hidden="true"></i>
                            Emissão até
                        </span>

                        <input
                            class="acc-input"
                            type="date"
                            name="until"
                        >
                    </label>

                    <label class="acc-field">
                        <span>
                            <i class="ph ph-seal-check" aria-hidden="true"></i>
                            Autorização
                        </span>

                        <select class="acc-select" name="authorization_status">
                            <option value="">Todas</option>
                            <option value="legacy_unsubmitted">Processo anterior ao workflow</option>
                            <option value="sent">Aguardando organização</option>
                            <option value="authorized">Autorizada</option>
                            <option value="correction_requested">Correção solicitada</option>
                            <option value="invalidated">Invalidada</option>
                        </select>
                    </label>

                    <label class="acc-field">
                        <span>
                            <i class="ph ph-file-search" aria-hidden="true"></i>
                            Fiscal
                        </span>

                        <select class="acc-select" name="fiscal_status">
                            <option value="">Todos</option>
                            <option value="not_started">Não iniciado</option>
                        </select>
                    </label>
                </div>
            </details>
        </form>

        <div class="acc-table-wrap">
            <table class="acc-table">
                <thead>
                    <tr>
                        <th>Processo</th>
                        <th>Projeto</th>
                        <th>Destinatário</th>
                        <th>Etapa e próxima ação</th>
                        <th>Valor</th>
                        <th>Conferência</th>
                    </tr>
                </thead>

                <tbody data-process-table></tbody>
            </table>
        </div>

        <div
            class="acc-mobile-list"
            data-process-mobile
        ></div>

        <footer
            class="acc-pagination"
            data-process-pagination
        ></footer>
    </section>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('assets/accounting-portal.js') }}" defer></script>
@endpush
