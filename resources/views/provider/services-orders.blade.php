@extends('layouts.campo')

@section('title', 'Serviços')
@section('page-title', 'Ordens de serviço')
@section('user-role', ($operator ?? false) ? 'Operação de serviços' : 'Prestador')

@php
    $routeTenant = request()->route('tenant');
    $tenantSlug = is_object($routeTenant) ? ($routeTenant->slug ?? null) : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make('provider', 'orders', $tenantSlug);
    $isOperator = (bool) ($operator ?? false);

    // Preserva os estados reais do backend; este mapeamento é somente visual.
    $orderStatusMeta = static function ($status): array {
        $value = is_object($status) ? ($status->value ?? (string) $status) : (string) ($status ?? '');
        $normalized = \Illuminate\Support\Str::of($value)->lower()->replace([' ', '-'], '_')->value();

        return match ($normalized) {
            'draft' => ['label' => 'Rascunho', 'class' => 'is-draft'],
            'pending', 'waiting', 'awaiting' => ['label' => 'Pendente', 'class' => 'is-pending'],
            'scheduled', 'agendada', 'agendado' => ['label' => 'Agendada', 'class' => 'is-scheduled'],
            'in_progress', 'processing', 'started', 'em_andamento' => ['label' => 'Em execução', 'class' => 'is-progress'],
            'submitted', 'awaiting_review', 'under_review', 'em_conferencia' => ['label' => 'Em conferência', 'class' => 'is-review'],
            'completed', 'done', 'finished', 'executed', 'concluida', 'concluido' => ['label' => 'Concluída', 'class' => 'is-completed'],
            'approved', 'validated', 'aprovada', 'aprovado' => ['label' => 'Aprovada', 'class' => 'is-approved'],
            'rejected', 'correction_requested', 'correcao_solicitada' => ['label' => 'Correção solicitada', 'class' => 'is-rejected'],
            'cancelled', 'canceled', 'cancelada', 'cancelado' => ['label' => 'Cancelada', 'class' => 'is-cancelled'],
            default => [
                'label' => $value !== '' ? \Illuminate\Support\Str::headline($value) : 'Sem situação',
                'class' => 'is-neutral',
            ],
        };
    };

    $ordersTotal = method_exists($orders, 'total') ? $orders->total() : $orders->count();
    $ordersOnPage = $orders->count();
    $currentPage = method_exists($orders, 'currentPage') ? $orders->currentPage() : 1;
    $lastPage = method_exists($orders, 'lastPage') ? $orders->lastPage() : 1;
    $pageFrom = method_exists($orders, 'firstItem') ? ($orders->firstItem() ?? 0) : ($ordersOnPage ? 1 : 0);
    $pageTo = method_exists($orders, 'lastItem') ? ($orders->lastItem() ?? 0) : $ordersOnPage;

    // Filtro local intencional: não altera o escopo do tenant nem a consulta paginada.
    $pageStatusOptions = [];
    foreach ($orders as $order) {
        $state = $orderStatusMeta($order->operational_status);
        $key = $state['class'];
        if (!isset($pageStatusOptions[$key])) {
            $pageStatusOptions[$key] = ['label' => $state['label'], 'count' => 0];
        }
        $pageStatusOptions[$key]['count']++;
    }
@endphp

@section('content')
<style>
/* SGC Campo — Lista de ordens. Componentes isolados do CSS do shell. */
.orders-page {
    --ow-bg: var(--ui-color-canvas, #f4f4ef);
    --ow-surface: var(--ui-color-surface, #fffefb);
    --ow-raised: var(--ui-color-surface-raised, #fffefb);
    --ow-soft: var(--ui-color-surface-soft, #f0f0e9);
    --ow-border: var(--ui-color-border, #d9e0d7);
    --ow-border-strong: var(--ui-color-border-strong, #b9c7bc);
    --ow-text: var(--ui-color-text, #1d2922);
    --ow-secondary: var(--ui-color-text-secondary, #647167);
    --ow-muted: var(--ui-color-text-muted, #879087);
    --ow-rail: var(--sgc-rail, #173c2e);
    --ow-rail-text: var(--sgc-rail-text, #eff5ed);
    --ow-accent: var(--sgc-accent, #d9f172);
    --ow-on-accent: var(--sgc-on-accent, #233523);
    --ow-primary: var(--ui-color-primary-strong, #214d3a);
    --ow-primary-soft: var(--ui-color-primary-soft, #e7f0e7);
    --ow-green: var(--ui-color-success-strong, #177c43);
    --ow-green-bg: var(--ui-color-success-soft, #edf8f1);
    --ow-green-border: var(--ui-color-success-border, #cde8d6);
    --ow-amber: var(--ui-color-warning-strong, #975f0f);
    --ow-amber-bg: var(--ui-color-warning-soft, #fff7e8);
    --ow-amber-border: var(--ui-color-warning-border, #efdcb8);
    --ow-red: var(--ui-color-danger-strong, #a43d3d);
    --ow-red-bg: var(--ui-color-danger-soft, #fff1f1);
    --ow-red-border: var(--ui-color-danger-border, #f1cccc);
    --ow-blue: var(--ui-color-info-strong, #285fa9);
    --ow-blue-bg: var(--ui-color-info-soft, #eef4ff);
    --ow-blue-border: var(--ui-color-info-border, #d4e2f8);
    --ow-teal: var(--ui-color-cyan-strong, #11758f);
    --ow-teal-bg: var(--ui-color-cyan-soft, #edf8fb);
    --ow-teal-border: var(--ui-color-cyan-border, #d2eaf0);
    --ow-violet: var(--ui-color-violet-strong, #6f37b5);
    --ow-violet-bg: var(--ui-color-violet-soft, #f5effc);
    --ow-violet-border: var(--ui-color-violet-border, #e5d8f5);
    display: grid;
    grid-column: 1 / -1;
    width: min(100%, 1390px);
    min-width: 0;
    margin-inline: auto;
    gap: .88rem;
    padding: 0 0 1.25rem;
    color: var(--ow-text);
    font-family: var(--ui-font-sans, Inter, system-ui, sans-serif);
}
:is(html[data-theme="dark"], [data-theme="dark"]) .orders-page {
    /* Grafite e ouro prevalecem sobre CSS legados que ainda usem fundo verde. */
    --ow-bg: #111418;
    --ow-surface: #191e24;
    --ow-raised: #212830;
    --ow-soft: #212830;
    --ow-border: #323c47;
    --ow-border-strong: #46525e;
    --ow-text: #edf2f6;
    --ow-secondary: #afbac6;
    --ow-muted: #94a2ae;
    --ow-rail: #151a20;
    --ow-rail-text: #f2f5f7;
    --ow-accent: #d9f172;
    --ow-on-accent: #173c2e;
    --ow-primary: #d9f172;
    --ow-primary-soft: #34352c;
    --ow-green: #69d49b;
    --ow-green-bg: #20392f;
    --ow-green-border: #355c47;
    --ow-amber: #f0c574;
    --ow-amber-bg: #393021;
    --ow-amber-border: #5f492c;
    --ow-red: #f2989a;
    --ow-red-bg: #3c282c;
    --ow-red-border: #654045;
    --ow-blue: #9fc5ff;
    --ow-blue-bg: #222f44;
    --ow-blue-border: #3e577c;
    --ow-teal: #91dce8;
    --ow-teal-bg: #1e353d;
    --ow-teal-border: #385964;
    --ow-violet: #d1b2fb;
    --ow-violet-bg: #302840;
    --ow-violet-border: #514064;
}
.orders-page, .orders-page *, .orders-page *::before, .orders-page *::after { box-sizing: border-box; }
.orders-page [hidden] { display: none !important; }
.orders-page a { text-decoration: none; }
.orders-page :is(button, input) { font-family: inherit; }
.orders-page :is(a, button, input):focus-visible { outline: 2px solid var(--ow-primary); outline-offset: 3px; }
.orders-page svg.ow-icon { display: block; width: 1.12rem; height: 1.12rem; flex: none; }
.orders-page svg.ow-icon-lg { width: 1.3rem; height: 1.3rem; }
.orders-page svg.ow-icon-sm { width: .96rem; height: .96rem; }
.ow-sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; border: 0; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }

/* Ações e título: um pouco de verde-lima, sem uma página de marketing. */
.orders-page .ow-header { display: flex; gap: 1rem; align-items: center; justify-content: space-between; min-width: 0; padding: .12rem 0 .25rem; }
.ow-heading { min-width: 0; }
.ow-eyebrow { display: inline-flex; align-items: center; gap: .48rem; color: var(--ow-primary); font-size: .77rem; font-weight: 780; }
.ow-eyebrow::before { display: block; width: 19px; height: 5px; border-radius: 6px; background: var(--ow-accent); content: ''; }
.ow-title { margin: .26rem 0 .18rem; color: var(--ow-text); font-size: clamp(1.5rem, 2.1vw, 1.93rem); font-weight: 810; letter-spacing: -.035em; line-height: 1.16; }
.ow-subtitle { margin: 0; color: var(--ow-secondary); font-size: .83rem; line-height: 1.42; }
.ow-new { display: inline-flex; gap: .56rem; align-items: center; justify-content: center; min-height: 44px; flex: none; padding: .4rem .82rem .4rem .42rem; border-radius: 10px; border: 1px solid var(--ow-rail); background: var(--ow-rail); color: var(--ow-rail-text); font-size: .84rem; font-weight: 790; white-space: nowrap; transition: filter .15s ease; }
.ow-new:hover { filter: brightness(1.08); }
.ow-new-symbol { display: grid; width: 31px; height: 31px; place-items: center; border-radius: 8px; background: var(--ow-primary-soft); color: var(--ow-primary); }
:is(html[data-theme="dark"], [data-theme="dark"]) .orders-page .ow-new { background: var(--ow-accent); border-color: var(--ow-accent); color: var(--ow-on-accent); }
:is(html[data-theme="dark"], [data-theme="dark"]) .orders-page .ow-new-symbol { background: #2e26171f; }

/* Busca: somente UM contorno, aplicado ao input real. O shell global usa !important. */
.ow-toolbar { display: grid; min-width: 0; gap: .8rem; padding: .85rem .95rem .92rem; border: 1px solid var(--ow-border); border-radius: 13px; background: var(--ow-surface); }
.ow-search-row { display: flex; gap: .65rem; align-items: center; min-width: 0; }
.ow-search { position: relative; display: flex; flex: 1 1 auto; align-items: center; min-width: 0; }
.ow-search > .ow-icon { position: absolute; left: .93rem; z-index: 1; color: var(--ow-primary); pointer-events: none; }
/* Regras específicas vencem os inputs globais do layout bento.blade.php. */
#orders-page .ow-search input,
#orders-page .ow-search input:not([type="checkbox"]) {
    display: block;
    width: 100% !important;
    max-width: none !important;
    height: 48px;
    min-width: 0;
    min-height: 48px;
    padding: .67rem 2.8rem .67rem 2.85rem;
    border: 1px solid var(--ow-border-strong) !important;
    border-radius: 10px !important;
    outline: none !important;
    background: var(--ow-raised) !important;
    color: var(--ow-text) !important;
    box-shadow: none !important;
    font-size: .91rem;
    font-weight: 530;
    -webkit-appearance: none;
    appearance: none;
    transition: border-color .15s ease, box-shadow .15s ease;
}
#orders-page .ow-search input::placeholder { color: var(--ow-muted); opacity: 1; }
#orders-page .ow-search input::-webkit-search-cancel-button { -webkit-appearance: none; appearance: none; display: none; }
#orders-page .ow-search input:focus {
    border-color: var(--ow-primary) !important;
    background: var(--ow-surface) !important;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ow-accent) 37%, transparent) !important;
}
.ow-search-clear { position: absolute; right: .48rem; z-index: 1; display: grid; width: 34px; height: 34px; place-items: center; border: 0; border-radius: 8px; background: var(--ow-soft); color: var(--ow-secondary); cursor: pointer; }
.ow-search-clear:hover { background: var(--ow-primary-soft); color: var(--ow-primary); }
.ow-clear { display: inline-flex; flex: none; gap: .4rem; align-items: center; justify-content: center; min-height: 42px; padding: .5rem .65rem; border: 1px solid var(--ow-border); border-radius: 9px; background: var(--ow-soft); color: var(--ow-secondary); font-size: .75rem; font-weight: 730; cursor: pointer; }
.ow-clear:hover { border-color: var(--ow-primary); color: var(--ow-primary); }
.ow-status-filter { min-width: 0; }
.ow-filter-header { display: flex; min-width: 0; justify-content: space-between; align-items: center; gap: .6rem; margin-bottom: .48rem; }
.ow-filter-header strong { color: var(--ow-secondary); font-size: .76rem; font-weight: 790; }
.ow-filter-context { color: var(--ow-muted); font-size: .69rem; white-space: nowrap; }
.ow-state-scroller { display: flex; width: 100%; gap: .43rem; align-items: center; min-width: 0; overflow-x: auto; padding: .08rem .05rem .3rem; scrollbar-width: thin; scrollbar-color: var(--ow-border-strong) transparent; overscroll-behavior-x: contain; }
.ow-state-chip { --chip-tone: var(--ow-secondary); --chip-bg: var(--ow-soft); --chip-border: var(--ow-border); display: inline-flex; flex: none; align-items: center; justify-content: center; gap: .42rem; min-height: 38px; max-width: 100%; padding: .38rem .73rem; border: 1px solid var(--ow-border); border-radius: 999px; background: var(--ow-raised); color: var(--ow-secondary); font-size: .75rem; font-weight: 680; line-height: 1.1; cursor: pointer; white-space: nowrap; transition: border-color .14s, background .14s; }
.ow-state-chip::before { content: ''; display: block; width: 6px; height: 6px; flex: none; border-radius: 50%; background: var(--chip-tone); }
.ow-state-chip small { display: grid; min-width: 21px; height: 21px; place-items: center; padding-inline: .25rem; border-radius: 999px; background: var(--ow-soft); color: var(--ow-secondary); font-size: .68rem; font-weight: 790; font-variant-numeric: tabular-nums; }
.ow-state-chip:hover { border-color: var(--ow-border-strong); background: var(--ow-soft); }
.ow-state-chip.is-active { border-color: var(--chip-border); background: var(--chip-bg); color: var(--chip-tone); font-weight: 790; }
.ow-state-chip.is-active small { background: color-mix(in srgb, var(--chip-tone) 13%, transparent); color: var(--chip-tone); }
.ow-state-chip.is-active:focus-visible { outline-color: var(--chip-tone); }
.ow-state-chip.is-all { --chip-tone: var(--ow-rail); --chip-bg: var(--ow-accent); --chip-border: var(--ow-accent); }
.ow-state-chip.is-all::before { width: 0; }
.ow-state-chip.is-all.is-active { color: var(--ow-on-accent); }
.ow-state-chip.is-all.is-active small { color: var(--ow-on-accent); background: color-mix(in srgb, var(--ow-on-accent) 10%, transparent); }
.ow-state-chip.is-pending { --chip-tone: var(--ow-amber); --chip-bg: var(--ow-amber-bg); --chip-border: var(--ow-amber-border); }
.ow-state-chip.is-scheduled { --chip-tone: var(--ow-blue); --chip-bg: var(--ow-blue-bg); --chip-border: var(--ow-blue-border); }
.ow-state-chip.is-progress { --chip-tone: var(--ow-teal); --chip-bg: var(--ow-teal-bg); --chip-border: var(--ow-teal-border); }
.ow-state-chip.is-review { --chip-tone: var(--ow-violet); --chip-bg: var(--ow-violet-bg); --chip-border: var(--ow-violet-border); }
.ow-state-chip.is-completed, .ow-state-chip.is-approved { --chip-tone: var(--ow-green); --chip-bg: var(--ow-green-bg); --chip-border: var(--ow-green-border); }
.ow-state-chip.is-rejected { --chip-tone: var(--ow-red); --chip-bg: var(--ow-red-bg); --chip-border: var(--ow-red-border); }
.ow-feedback { display: flex; gap: .45rem; flex-wrap: wrap; align-items: center; margin: -.1rem 0 .05rem; color: var(--ow-secondary); font-size: .73rem; line-height: 1.35; }
.ow-feedback > #ow-result { color: var(--ow-primary); font-weight: 790; }
.ow-feedback-context { color: var(--ow-muted); }

/* Lista: separação sem bordas espessas, beneficiário como título. */
.ow-history { min-width: 0; border: 1px solid var(--ow-border); border-radius: 13px; overflow: hidden; background: var(--ow-surface); }
.ow-history-head { display: flex; flex-wrap: wrap; gap: .7rem; align-items: center; justify-content: space-between; padding: .85rem .96rem; border-bottom: 1px solid var(--ow-border); }
.ow-history-title { display: flex; gap: .62rem; align-items: center; min-width: 0; }
.ow-history-mark { display: grid; place-items: center; flex: none; width: 37px; height: 37px; border-radius: 10px; background: var(--ow-primary-soft); color: var(--ow-primary); }
.ow-history-title h2 { margin: 0; color: var(--ow-text); font-size: .99rem; font-weight: 800; line-height: 1.23; }
.ow-history-title p { margin: .12rem 0 0; color: var(--ow-secondary); font-size: .75rem; }
.ow-range { flex: none; padding: .38rem .55rem; border: 1px solid var(--ow-border); border-radius: 8px; background: var(--ow-soft); color: var(--ow-secondary); font-size: .74rem; font-variant-numeric: tabular-nums; white-space: nowrap; }
.ow-range strong { color: var(--ow-text); }
.ow-table-scroll { min-width: 0; overflow-x: auto; padding: .05rem .72rem .78rem; background: var(--ow-bg); }
.ow-table { width: 100%; min-width: 1000px; border-collapse: separate; border-spacing: 0 8px; table-layout: fixed; }
.ow-table th { padding: .68rem .74rem .26rem; color: var(--ow-secondary); font-size: .73rem; font-weight: 750; text-align: left; background: transparent; }
.ow-table th:nth-child(1) { width: 31%; }
.ow-table th:nth-child(2) { width: 20%; }
.ow-table th:nth-child(3) { width: 14%; }
.ow-table th:nth-child(4) { width: 13%; }
.ow-table th:nth-child(5) { width: 16%; }
.ow-table th:nth-child(6) { width: 6%; }
.ow-table tbody :is(td,th) { padding: .86rem .74rem; border-top: 1px solid var(--ow-border); border-bottom: 1px solid var(--ow-border); background: var(--ow-surface); color: var(--ow-secondary); font-size: .81rem; vertical-align: middle; transition: background .14s ease; }
.ow-table tbody th[scope="row"] { border-left: 3px solid var(--row-color, var(--ow-border-strong)); border-radius: 10px 0 0 10px; padding-left: .85rem; text-align: left; }
.ow-table td:last-child { border-right: 1px solid var(--ow-border); border-radius: 0 10px 10px 0; }
.ow-table tbody tr:hover :is(td,th) { background: color-mix(in srgb, var(--ow-primary-soft) 55%, var(--ow-surface)); }
.ow-row { --row-color: var(--ow-border-strong); }
.ow-row.is-pending { --row-color: var(--ow-amber); }
.ow-row.is-scheduled { --row-color: var(--ow-blue); }
.ow-row.is-progress { --row-color: var(--ow-teal); }
.ow-row.is-review { --row-color: var(--ow-violet); }
.ow-row.is-completed, .ow-row.is-approved { --row-color: var(--ow-green); }
.ow-row.is-rejected { --row-color: var(--ow-red); }
.ow-person { display: flex; min-width: 0; gap: .66rem; align-items: center; }
.ow-avatar { display: grid; width: 43px; height: 43px; flex: none; place-items: center; border-radius: 10px; background: var(--ow-primary-soft); color: var(--ow-primary); font-size: 1.02rem; font-weight: 850; line-height: 1; text-transform: uppercase; }
.ow-person-text { display: grid; min-width: 0; gap: .15rem; }
.ow-person-name { color: var(--ow-text); font-size: .98rem; font-weight: 820; line-height: 1.33; overflow-wrap: anywhere; letter-spacing: -.01em; }
.ow-order-no { display: inline-flex; align-items: center; gap: .32rem; color: var(--ow-secondary); font-size: .71rem; font-weight: 680; line-height: 1.25; }
.ow-order-no::before { content: ''; width: 5px; height: 5px; flex: none; border-radius: 50%; background: var(--ow-accent); box-shadow: 0 0 0 1px var(--ow-border-strong); }
.ow-service { color: var(--ow-text); font-size: .83rem; font-weight: 640; line-height: 1.4; overflow-wrap: anywhere; }
.ow-provider { color: var(--ow-secondary); font-size: .81rem; font-weight: 620; line-height: 1.44; overflow-wrap: anywhere; }
.ow-datetime { display: grid; gap: .11rem; font-variant-numeric: tabular-nums; }
.ow-datetime strong { color: var(--ow-text); font-size: .82rem; font-weight: 730; }
.ow-datetime small { color: var(--ow-secondary); font-size: .74rem; }
.ow-not-scheduled { color: var(--ow-muted); font-size: .78rem; }
.ow-open { display: grid; place-items: center; width: 40px; height: 40px; margin-left: auto; border: 1px solid var(--ow-border); border-radius: 9px; background: var(--ow-raised); color: var(--ow-primary); }
.ow-open:hover { border-color: var(--ow-accent); background: var(--ow-primary-soft); color: var(--ow-primary); }

/* Status semânticos, incluindo os estados de revisão e correção. */
.ow-status { --st-ink: var(--ow-secondary); --st-bg: var(--ow-soft); --st-border: var(--ow-border); display: inline-flex; align-items: center; gap: .43rem; min-height: 31px; max-width: 100%; padding: .34rem .57rem; border: 1px solid var(--st-border); border-radius: 7px; background: var(--st-bg); color: var(--st-ink); font-size: .74rem; font-weight: 780; line-height: 1.25; white-space: nowrap; }
.ow-status::before { content: ''; width: 7px; height: 7px; flex: none; border-radius: 50%; background: currentColor; }
.ow-status.is-pending { --st-ink: var(--ow-amber); --st-bg: var(--ow-amber-bg); --st-border: var(--ow-amber-border); }
.ow-status.is-scheduled { --st-ink: var(--ow-blue); --st-bg: var(--ow-blue-bg); --st-border: var(--ow-blue-border); }
.ow-status.is-progress { --st-ink: var(--ow-teal); --st-bg: var(--ow-teal-bg); --st-border: var(--ow-teal-border); }
.ow-status.is-review { --st-ink: var(--ow-violet); --st-bg: var(--ow-violet-bg); --st-border: var(--ow-violet-border); }
.ow-status.is-completed, .ow-status.is-approved { --st-ink: var(--ow-green); --st-bg: var(--ow-green-bg); --st-border: var(--ow-green-border); }
.ow-status.is-rejected { --st-ink: var(--ow-red); --st-bg: var(--ow-red-bg); --st-border: var(--ow-red-border); }

/* Cards operacionais: destaque no beneficiário e no serviço, mas pesos distintos. */
.ow-cards { display: none; }
.ow-card { --row-color: var(--ow-border-strong); display: grid; min-width: 0; gap: .64rem; padding: .85rem .9rem .77rem; border: 1px solid var(--ow-border); border-top: 3px solid var(--row-color); border-radius: 11px; background: var(--ow-surface); color: var(--ow-text); transition: border-color .14s, background .14s; }
.ow-card:hover { background: color-mix(in srgb, var(--ow-primary-soft) 55%, var(--ow-surface)); }
.ow-card.is-pending { --row-color: var(--ow-amber); }
.ow-card.is-scheduled { --row-color: var(--ow-blue); }
.ow-card.is-progress { --row-color: var(--ow-teal); }
.ow-card.is-review { --row-color: var(--ow-violet); }
.ow-card.is-completed, .ow-card.is-approved { --row-color: var(--ow-green); }
.ow-card.is-rejected { --row-color: var(--ow-red); }
.ow-card-top { display: flex; min-width: 0; gap: .4rem; align-items: center; justify-content: space-between; }
.ow-card-top .ow-order-no { font-size: .75rem; }
.ow-card-person { display: flex; min-width: 0; gap: .67rem; align-items: center; }
.ow-card-person .ow-avatar { width: 45px; height: 45px; }
.ow-card-person .ow-person-text { flex: 1; }
.ow-card-person .ow-person-name { font-size: 1.07rem; }
.ow-card-label { color: var(--ow-muted); font-size: .7rem; font-weight: 660; }
.ow-card-arrow { color: var(--ow-secondary); }
.ow-card-service { display: grid; gap: .13rem; padding: .57rem .66rem; border: 1px solid color-mix(in srgb, var(--ow-primary-soft) 25%, var(--ow-border)); border-radius: 8px; background: color-mix(in srgb, var(--ow-primary-soft) 40%, var(--ow-surface)); }
.ow-card-service strong { color: var(--ow-text); font-size: .82rem; font-weight: 660; line-height: 1.42; overflow-wrap: anywhere; }
.ow-card-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .7rem; padding-top: .6rem; border-top: 1px solid var(--ow-border); }
.ow-card-detail { display: grid; min-width: 0; gap: .12rem; }
.ow-card-detail strong { color: var(--ow-secondary); font-size: .77rem; font-weight: 650; line-height: 1.4; overflow-wrap: anywhere; }

.ow-empty { display: grid; min-height: 215px; padding: 1.6rem 1rem; place-items: center; text-align: center; background: var(--ow-surface); }
.ow-empty-body { display: grid; justify-items: center; max-width: 350px; gap: .57rem; }
.ow-empty-icon { display: grid; place-items: center; width: 46px; height: 46px; border-radius: 11px; color: var(--ow-primary); background: var(--ow-primary-soft); }
.ow-empty-body strong { color: var(--ow-text); font-size: .97rem; font-weight: 780; }
.ow-empty-body p { margin: 0; color: var(--ow-secondary); font-size: .81rem; line-height: 1.48; }
.ow-empty-body .ow-new { margin-top: .35rem; }
.ow-pagination {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: center;
    gap: .4rem;
    padding: .65rem .7rem;
    border-top: 1px solid var(--ow-border);
    background: var(--ow-surface);
}
.ow-page-link {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    min-height: 44px;
    padding: 0 .62rem;
    border: 1px solid var(--ow-border);
    border-radius: 10px;
    background: var(--ow-soft);
    color: var(--ow-text);
    font-size: .8125rem;
    font-weight: 700;
    line-height: 1.15;
    text-decoration: none;
    justify-self: start;
}
.ow-page-link.is-next { justify-self: end; }
.ow-pagination svg.ow-icon { width: .94rem; height: .94rem; }
.ow-page-link .ow-arrow-previous { transform: rotate(180deg); }
.ow-pagination a:hover { border-color: var(--ow-border-strong); background: var(--ow-primary-soft); color: var(--ow-text); }
.ow-pagination a:focus-visible { outline: 2px solid var(--ow-primary); outline-offset: 2px; }
.ow-page-link[aria-disabled="true"] { background: transparent; color: var(--ow-secondary); opacity: .7; }
.ow-page-current { text-align: center; color: var(--ow-secondary); font-size: .8125rem; line-height: 1.15; }
.ow-page-current strong { color: var(--ow-text); font-variant-numeric: tabular-nums; }

@media (max-width: 1140px) {
    .orders-page .ow-table-scroll { display: none; }
    .orders-page .ow-cards { display: grid; gap: .65rem; padding: .7rem; background: var(--ow-bg); }
}
.orders-page .ow-history[data-order-view="table"] .ow-table-scroll:not([hidden]) { display: block; }
.orders-page .ow-history[data-order-view="cards"] .ow-table-scroll,
.orders-page .ow-history[data-order-view="table"] .ow-cards { display: none; }
.orders-page .ow-history[data-order-view="cards"] .ow-cards:not([hidden]) { display: grid; gap: .65rem; padding: .7rem; }
.orders-page .ow-table-scroll:focus-visible { outline: 2px solid var(--ow-primary); outline-offset: -2px; }
@media (max-width: 710px) {
    .orders-page { gap: .72rem; }
    .orders-page .ow-header { align-items: center; }
    .ow-title { font-size: 1.46rem; }
    .ow-subtitle { font-size: .78rem; }
    .ow-toolbar { padding: .72rem; gap: .67rem; }
    .ow-filter-header { margin-bottom: .38rem; }
    .ow-filter-context { font-size: .66rem; }
    .ow-new { width: 45px; min-width: 45px; height: 45px; padding: 0; }
    .ow-new-symbol { width: 100%; height: 100%; border-radius: 9px; background: transparent; color: inherit; }
    .ow-new-text { display: none; }
    .ow-history-head { padding: .7rem .75rem; }
    .orders-page .ow-cards { padding: .62rem; gap: .62rem; }
    .ow-card { padding: .81rem .76rem; }
    #orders-page .ow-search input { font-size: 16px; }
    .ow-state-chip { min-height: 39px; }
}
@media (max-width: 375px) {
    .ow-subtitle { font-size: .75rem; }
    .ow-status { font-size: .69rem; padding-inline: .43rem; }
    .ow-card-top { flex-wrap: wrap; gap: .3rem; }
    .ow-card-details { gap: .55rem; }
    .ow-history-title h2 { font-size: .91rem; }
    .ow-range { padding: .3rem .38rem; font-size: .69rem; }
    .ow-filter-context { display: none; }
}
@media (prefers-reduced-motion: reduce) {
    .orders-page *, .orders-page *::before, .orders-page *::after { transition: none !important; }
}
</style>

<main class="orders-page" id="orders-page">
    <svg aria-hidden="true" width="0" height="0" xmlns="http://www.w3.org/2000/svg" style="position:absolute;overflow:hidden;pointer-events:none">
        <symbol id="ow-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2"/></symbol>
        <symbol id="ow-search" viewBox="0 0 24 24"><circle cx="10.6" cy="10.6" r="6.6" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m15.7 15.7 4.5 4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
        <symbol id="ow-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
        <symbol id="ow-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="ow-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="ow-list" viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"/></symbol>
        <symbol id="ow-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M5 20a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></symbol>
    </svg>

    <header class="ow-header">
        <div class="ow-heading">
            <span class="ow-eyebrow">{{ $isOperator ? 'Operação de serviços' : 'Área do prestador' }}</span>
            <h1 class="ow-title">Ordens de serviço</h1>
            <p class="ow-subtitle">Acompanhe beneficiários, agendamentos e execução.</p>
        </div>
        <a class="ow-new" href="{{ route('provider.orders.create', $tenantSlug) }}" aria-label="Criar nova ordem">
            <span class="ow-new-symbol"><svg class="ow-icon" aria-hidden="true"><use href="#ow-plus"/></svg></span>
            <span class="ow-new-text">Nova ordem</span>
        </a>
    </header>

    @if($orders->isNotEmpty())
        <div class="ow-toolbar" role="search" aria-label="Filtros das ordens nesta página">
            <div class="ow-search-row">
                <label class="ow-sr-only" for="ow-search-field">Buscar beneficiário, serviço ou número da ordem</label>
                <div class="ow-search">
                    <svg class="ow-icon" aria-hidden="true"><use href="#ow-search"/></svg>
                    <input id="ow-search-field" type="search" placeholder="Beneficiário ou número da OS" autocomplete="off" spellcheck="false">
                    <button type="button" id="ow-search-clear" class="ow-search-clear" aria-label="Limpar busca" title="Limpar busca" hidden>
                        <svg class="ow-icon ow-icon-sm" aria-hidden="true"><use href="#ow-x"/></svg>
                    </button>
                </div>
                <button class="ow-clear" id="ow-clear-filters" type="button" hidden>
                    <svg class="ow-icon ow-icon-sm" aria-hidden="true"><use href="#ow-x"/></svg>
                    <span>Limpar</span>
                </button>
            </div>
            <div class="ow-status-filter">
                <div class="ow-filter-header">
                    <strong id="ow-status-label">Filtrar por situação</strong>
                    <span class="ow-filter-context">Nesta página</span>
                </div>
                <div class="ow-state-scroller" role="group" aria-labelledby="ow-status-label">
                    <button class="ow-state-chip is-all is-active" type="button" data-ow-status-choice="" aria-pressed="true">
                        Todas <small>{{ $ordersOnPage }}</small>
                    </button>
                    @foreach($pageStatusOptions as $stateKey => $stateOption)
                        <button class="ow-state-chip {{ $stateKey }}" type="button" data-ow-status-choice="{{ $stateKey }}" aria-pressed="false">
                            {{ $stateOption['label'] }} <small>{{ $stateOption['count'] }}</small>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
        <p class="ow-feedback" id="ow-filter-feedback" hidden>
            <span id="ow-result" role="status" aria-live="polite"></span>
            <span class="ow-feedback-context">• Apenas nesta página</span>
        </p>
    @endif

    <section class="ow-history" aria-labelledby="ow-history-title" data-order-list="provider" data-compact-query="(max-width: 1140px)">
        <header class="ow-history-head">
            <div class="ow-history-title">
                <span class="ow-history-mark" aria-hidden="true"><svg class="ow-icon ow-icon-lg"><use href="#ow-list"/></svg></span>
                <div>
                    <h2 id="ow-history-title">Histórico de ordens</h2>
                    <p>{{ $ordersTotal === 1 ? '1 ordem registrada' : number_format($ordersTotal, 0, ',', '.') . ' ordens registradas' }}</p>
                </div>
            </div>
            @if($ordersTotal > 0)
                @if($orders->isNotEmpty()) @include('services._order-view-switch') @endif
                <span class="ow-range"><strong>{{ $pageFrom }}–{{ $pageTo }}</strong> de {{ number_format($ordersTotal, 0, ',', '.') }}</span>
            @endif
        </header>

        @if($orders->isEmpty())
            <div class="ow-empty">
                <div class="ow-empty-body">
                    <span class="ow-empty-icon" aria-hidden="true"><svg class="ow-icon ow-icon-lg"><use href="#ow-list"/></svg></span>
                    <strong>Nenhuma ordem registrada</strong>
                    <p>As ordens criadas aparecerão aqui para acompanhamento.</p>
                    <a href="{{ route('provider.orders.create', $tenantSlug) }}" class="ow-new" aria-label="Criar nova ordem"><span class="ow-new-symbol"><svg class="ow-icon" aria-hidden="true"><use href="#ow-plus"/></svg></span><span class="ow-new-text">Nova ordem</span></a>
                </div>
            </div>
        @else
            <div class="ow-table-scroll" id="ow-table-wrap" tabindex="0" role="region" aria-label="Tabela de ordens com rolagem horizontal">
                <table class="ow-table" aria-label="Ordens de serviço por beneficiário">
                    <thead><tr>
                        <th scope="col">Beneficiário</th>
                        <th scope="col">Serviço</th>
                        <th scope="col">Prestador</th>
                        <th scope="col">Agendamento</th>
                        <th scope="col">Situação</th>
                        <th scope="col">Ações</th>
                    </tr></thead>
                    <tbody>
                        @foreach($orders as $order)
                            @php
                                $state = $orderStatusMeta($order->operational_status);
                                $providerName = $order->provider_snapshot['name'] ?? $order->serviceProvider?->name ?? 'Não informado';
                                $beneficiaryName = $order->beneficiary_snapshot['name'] ?? 'Não informado';
                                $serviceName = $order->service?->name ?? 'Serviço não informado';
                                $initial = mb_strtoupper(mb_substr(trim((string) $beneficiaryName), 0, 1));
                                $searchText = implode(' ', [$beneficiaryName, $order->number, $serviceName, $providerName, $state['label'], $order->location ?? '']);
                            @endphp
                            <tr class="ow-row {{ $state['class'] }}" data-ow-item data-ow-status="{{ $state['class'] }}" data-ow-search="{{ $searchText }}">
                                <th scope="row">
                                    <div class="ow-person">
                                        <span class="ow-avatar" aria-hidden="true">{{ $initial ?: '?' }}</span>
                                        <span class="ow-person-text">
                                            <strong class="ow-person-name">{{ $beneficiaryName }}</strong>
                                            <span class="ow-order-no">OS {{ $order->number }}</span>
                                        </span>
                                    </div>
                                </th>
                                <td><span class="ow-service">{{ $serviceName }}</span></td>
                                <td><span class="ow-provider">{{ $providerName }}</span></td>
                                <td>
                                    @if($order->scheduled_at)
                                        <span class="ow-datetime"><strong>{{ $order->scheduled_at->format('d/m/Y') }}</strong><small>{{ $order->scheduled_at->format('H:i') }}</small></span>
                                    @else
                                        <span class="ow-not-scheduled">Não agendada</span>
                                    @endif
                                </td>
                                <td><span class="ow-status {{ $state['class'] }}">{{ $state['label'] }}</span></td>
                                <td><a class="ow-open" href="{{ route('provider.orders.show', [$tenantSlug, $order]) }}" aria-label="Abrir ordem {{ $order->number }} de {{ $beneficiaryName }}" title="Abrir ordem"><svg class="ow-icon" aria-hidden="true"><use href="#ow-arrow"/></svg></a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ow-cards" id="ow-cards-wrap">
                @foreach($orders as $order)
                    @php
                        $state = $orderStatusMeta($order->operational_status);
                        $providerName = $order->provider_snapshot['name'] ?? $order->serviceProvider?->name ?? 'Não informado';
                        $beneficiaryName = $order->beneficiary_snapshot['name'] ?? 'Não informado';
                        $serviceName = $order->service?->name ?? 'Serviço não informado';
                        $initial = mb_strtoupper(mb_substr(trim((string) $beneficiaryName), 0, 1));
                        $searchText = implode(' ', [$beneficiaryName, $order->number, $serviceName, $providerName, $state['label'], $order->location ?? '']);
                    @endphp
                    <a class="ow-card {{ $state['class'] }}" data-ow-item data-ow-status="{{ $state['class'] }}" data-ow-search="{{ $searchText }}" href="{{ route('provider.orders.show', [$tenantSlug, $order]) }}" aria-label="Abrir ordem {{ $order->number }} de {{ $beneficiaryName }}">
                        <div class="ow-card-top">
                            <span class="ow-order-no">OS {{ $order->number }}</span>
                            <span class="ow-status {{ $state['class'] }}">{{ $state['label'] }}</span>
                        </div>
                        <div class="ow-card-person">
                            <span class="ow-avatar" aria-hidden="true">{{ $initial ?: '?' }}</span>
                            <span class="ow-person-text"><span class="ow-card-label">Beneficiário</span><strong class="ow-person-name">{{ $beneficiaryName }}</strong></span>
                            <svg class="ow-icon ow-card-arrow" aria-hidden="true"><use href="#ow-arrow"/></svg>
                        </div>
                        <div class="ow-card-service"><span class="ow-card-label">Serviço</span><strong>{{ $serviceName }}</strong></div>
                        <div class="ow-card-details">
                            <span class="ow-card-detail"><span class="ow-card-label">Prestador</span><strong>{{ $providerName }}</strong></span>
                            <span class="ow-card-detail"><span class="ow-card-label">Agendamento</span><strong>{{ $order->scheduled_at?->format('d/m/Y · H:i') ?? 'Não agendada' }}</strong></span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="ow-empty" id="ow-filter-empty" hidden>
                <div class="ow-empty-body">
                    <span class="ow-empty-icon" aria-hidden="true"><svg class="ow-icon ow-icon-lg"><use href="#ow-search"/></svg></span>
                    <strong>Nenhuma ordem encontrada nesta página</strong>
                    <p>Tente outro beneficiário ou situação, ou navegue pelas páginas do histórico.</p>
                    <button class="ow-clear" type="button" data-ow-reset>Limpar filtros</button>
                </div>
            </div>
        @endif

        @if(method_exists($orders, 'hasPages') && $orders->hasPages())
            <nav class="ow-pagination" aria-label="Paginação do histórico de ordens">
                @if($orders->onFirstPage())
                    <span class="ow-page-link" aria-disabled="true">
                        <svg class="ow-icon ow-arrow-previous" aria-hidden="true"><use href="#ow-arrow"/></svg>
                        Anterior
                    </span>
                @else
                    <a class="ow-page-link" href="{{ $orders->previousPageUrl() }}" rel="prev">
                        <svg class="ow-icon ow-arrow-previous" aria-hidden="true"><use href="#ow-arrow"/></svg>
                        Anterior
                    </a>
                @endif

                <span class="ow-page-current" aria-current="page">Página <strong>{{ $currentPage }}</strong> de {{ $lastPage }}</span>

                @if($orders->hasMorePages())
                    <a class="ow-page-link is-next" href="{{ $orders->nextPageUrl() }}" rel="next">
                        Próxima
                        <svg class="ow-icon" aria-hidden="true"><use href="#ow-arrow"/></svg>
                    </a>
                @else
                    <span class="ow-page-link is-next" aria-disabled="true">
                        Próxima
                        <svg class="ow-icon" aria-hidden="true"><use href="#ow-arrow"/></svg>
                    </span>
                @endif
            </nav>
        @endif
    </section>
</main>

@if($orders->isNotEmpty())
<script>
(() => {
    const page = document.getElementById('orders-page');
    if (!page) return;

    const search = page.querySelector('#ow-search-field');
    const clear = page.querySelector('#ow-clear-filters');
    const searchClear = page.querySelector('#ow-search-clear');
    const feedback = page.querySelector('#ow-filter-feedback');
    const empty = page.querySelector('#ow-filter-empty');
    const result = page.querySelector('#ow-result');
    const tableWrap = page.querySelector('#ow-table-wrap');
    const cardsWrap = page.querySelector('#ow-cards-wrap');
    const statusButtons = Array.from(page.querySelectorAll('[data-ow-status-choice]'));
    const rows = Array.from(page.querySelectorAll('.ow-table tbody [data-ow-item]'));
    const items = Array.from(page.querySelectorAll('[data-ow-item]'));
    let selectedStatus = '';

    const normalize = value => String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('pt-BR')
        .trim();

    function applyFilters() {
        const query = normalize(search.value);
        let matches = 0;

        items.forEach(item => {
            const matchText = !query || normalize(item.dataset.owSearch).includes(query);
            const matchStatus = !selectedStatus || item.dataset.owStatus === selectedStatus;
            item.hidden = !(matchText && matchStatus);
        });
        rows.forEach(row => { if (!row.hidden) matches += 1; });
        statusButtons.forEach(button => {
            const active = button.dataset.owStatusChoice === selectedStatus;
            button.setAttribute('aria-pressed', String(active));
            button.classList.toggle('is-active', active);
        });

        const hasFilters = Boolean(query || selectedStatus);
        clear.hidden = !hasFilters;
        searchClear.hidden = !search.value;
        feedback.hidden = !hasFilters;
        const noResults = hasFilters && matches === 0;
        empty.hidden = !noResults;
        tableWrap.hidden = noResults;
        cardsWrap.hidden = noResults;
        result.textContent = hasFilters
            ? `${matches} ${matches === 1 ? 'ordem encontrada' : 'ordens encontradas'}`
            : '';
    }

    function resetFilters() {
        search.value = '';
        selectedStatus = '';
        applyFilters();
        search.focus();
    }

    search.addEventListener('input', applyFilters);
    search.addEventListener('keydown', event => {
        if (event.key === 'Escape' && search.value) {
            search.value = '';
            applyFilters();
        }
    });
    statusButtons.forEach(button => {
        button.addEventListener('click', () => {
            selectedStatus = button.dataset.owStatusChoice ?? '';
            applyFilters();
        });
    });
    searchClear.addEventListener('click', () => {
        search.value = '';
        applyFilters();
        search.focus();
    });
    clear.addEventListener('click', resetFilters);
    page.querySelector('[data-ow-reset]')?.addEventListener('click', resetFilters);
    applyFilters();
})();
</script>
@endif
@endsection
