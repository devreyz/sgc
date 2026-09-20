@extends('layouts.bento')

@section('title', 'Despesas de serviços')
@section('page-title', 'Despesas')
@section('user-role', 'Operação de serviços')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'provider',
        'expenses',
        $tenantSlug
    );

    $expenseTotal = method_exists($expenses, 'total')
        ? $expenses->total()
        : $expenses->count();

    $expenseStatusMeta = static function ($status): array {
        $label = is_object($status) && method_exists($status, 'getLabel')
            ? (string) $status->getLabel()
            : (string) ($status ?? '');

        $normalized = \Illuminate\Support\Str::of($label)
            ->ascii()
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();

        if (
            str_contains($normalized, 'pago')
            || str_contains($normalized, 'paid')
            || str_contains($normalized, 'quitado')
            || str_contains($normalized, 'concluido')
        ) {
            return [
                'label' => $label ?: 'Pago',
                'class' => 'is-paid',
                'icon' => 'ph-check-circle',
            ];
        }

        if (
            str_contains($normalized, 'parcial')
            || str_contains($normalized, 'partial')
        ) {
            return [
                'label' => $label ?: 'Parcial',
                'class' => 'is-partial',
                'icon' => 'ph-circle-half',
            ];
        }

        if (
            str_contains($normalized, 'cancel')
            || str_contains($normalized, 'estorn')
        ) {
            return [
                'label' => $label ?: 'Cancelada',
                'class' => 'is-cancelled',
                'icon' => 'ph-x-circle',
            ];
        }

        return [
            'label' => $label ?: 'Pendente',
            'class' => 'is-pending',
            'icon' => 'ph-clock-countdown',
        ];
    };

    $oldDate = old('date', now()->toDateString());
    $oldDueDate = old('due_date', now()->toDateString());
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .expenses-page,
    .expense-dialog {
        --ex-green: var(--ws-green, #219653);
        --ex-green-soft: #edf8f2;
        --ex-green-border: #cce8d7;

        --ex-blue: var(--ws-blue, #3478d4);
        --ex-blue-soft: #edf4ff;
        --ex-blue-border: #cfe0f7;

        --ex-violet: var(--ws-purple, #8a4bd2);
        --ex-violet-soft: #f5efff;
        --ex-violet-border: #e1d2f4;

        --ex-amber: var(--ws-amber, #c38418);
        --ex-amber-soft: #fff7e8;
        --ex-amber-border: #f0dcae;

        --ex-red: var(--ws-red, #cf5050);
        --ex-red-soft: #fff0f0;
        --ex-red-border: #efcaca;

        --ex-cyan: #168eae;
        --ex-cyan-soft: #ecf8fb;
        --ex-cyan-border: #cae8ef;

        --ex-text: #17211d;
        --ex-text-2: #59655f;
        --ex-muted: #89938e;
        --ex-border: #dde5e0;
        --ex-soft: #f7faf8;
    }

    .expenses-page {
        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 1180px);
        min-width: 0;
        gap: .72rem;
        margin-inline: auto;
        color: var(--ex-text);
    }

    .expenses-page *,
    .expenses-page *::before,
    .expenses-page *::after,
    .expense-dialog *,
    .expense-dialog *::before,
    .expense-dialog *::after {
        box-sizing: border-box;
    }

    .expenses-page a {
        text-decoration: none;
    }

    /* =========================================================
       TOPO
       ========================================================= */

    .expenses-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .75rem;
        align-items: center;
        min-width: 0;
        padding: .76rem .84rem;
        border: 1px solid var(--ex-border);
        border-radius: 12px;
        background: #fff;
    }

    .expenses-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 40px minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
    }

    .expenses-head-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border-radius: 9px;
        background: var(--ex-amber-soft);
        color: var(--ex-amber);
        font-size: 1rem;
    }

    .expenses-head-copy {
        min-width: 0;
    }

    .expenses-head-copy small {
        display: block;
        color: var(--ex-muted);
        font-size: .61rem;
        font-weight: 750;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .expenses-head-copy h1 {
        margin: .06rem 0 0;
        color: var(--ex-text);
        font-size: clamp(1.02rem, 2vw, 1.22rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .expenses-head-copy p {
        margin: .12rem 0 0;
        color: var(--ex-muted);
        font-size: .67rem;
        line-height: 1.35;
    }

    .expense-new {
        display: inline-flex;
        min-height: 40px;
        gap: .32rem;
        align-items: center;
        justify-content: center;
        padding: .44rem .68rem;
        border: 1px solid var(--ex-green);
        border-radius: 8px;
        background: var(--ex-green);
        color: #fff;
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 810;
        white-space: nowrap;
    }

    .expense-new:focus-visible {
        outline: 2px solid var(--ex-green);
        outline-offset: 2px;
    }

    /* =========================================================
       ERROS
       ========================================================= */

    .expense-errors {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .5rem;
        align-items: start;
        padding: .62rem .68rem;
        border: 1px solid var(--ex-red-border);
        border-radius: 10px;
        background: var(--ex-red-soft);
    }

    .expense-errors-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: #fff;
        color: var(--ex-red);
        font-size: .86rem;
    }

    .expense-errors strong {
        display: block;
        color: var(--ex-red);
        font-size: .76rem;
        font-weight: 820;
    }

    .expense-errors ul {
        margin: .2rem 0 0;
        padding-left: 1rem;
        color: var(--ex-text-2);
        font-size: .68rem;
        line-height: 1.45;
    }

    /* =========================================================
       HISTÓRICO
       ========================================================= */

    .expenses-list {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--ex-border);
        border-radius: 12px;
        background: #fff;
    }

    .expenses-list-head {
        display: flex;
        min-width: 0;
        min-height: 49px;
        gap: .6rem;
        align-items: center;
        justify-content: space-between;
        padding: .52rem .64rem;
        border-bottom: 1px solid var(--ex-border);
    }

    .expenses-list-title {
        display: flex;
        min-width: 0;
        gap: .38rem;
        align-items: center;
    }

    .expenses-list-title-icon {
        display: grid;
        width: 30px;
        height: 30px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 7px;
        background: var(--ex-amber-soft);
        color: var(--ex-amber);
        font-size: .75rem;
    }

    .expenses-list-title strong {
        color: var(--ex-text);
        font-size: .72rem;
        font-weight: 820;
    }

    .expenses-total {
        display: inline-flex;
        min-height: 28px;
        align-items: center;
        padding: .24rem .4rem;
        border-radius: 7px;
        background: var(--ex-soft);
        color: var(--ex-muted);
        font-size: .58rem;
        font-weight: 720;
        white-space: nowrap;
    }

    /* =========================================================
       TABELA
       ========================================================= */

    .expenses-table-wrap {
        min-width: 0;
        overflow-x: auto;
    }

    .expenses-table {
        width: 100%;
        min-width: 820px;
        border-collapse: collapse;
        table-layout: fixed;
    }

    .expenses-table th {
        padding: .48rem .58rem;
        border-bottom: 1px solid var(--ex-border);
        background: var(--ex-soft);
        color: var(--ex-muted);
        font-size: .53rem;
        font-weight: 790;
        letter-spacing: .03em;
        text-align: left;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .expenses-table th:nth-child(1) {
        width: 108px;
    }

    .expenses-table th:nth-child(2) {
        width: 34%;
    }

    .expenses-table th:nth-child(3) {
        width: 24%;
    }

    .expenses-table th:nth-child(4) {
        width: 180px;
    }

    .expenses-table th:nth-child(5) {
        width: 138px;
    }

    .expenses-table td {
        min-width: 0;
        padding: .56rem .58rem;
        border-bottom: 1px solid var(--ex-border);
        color: var(--ex-text-2);
        font-size: .66rem;
        vertical-align: middle;
    }

    .expenses-table tbody tr:last-child td {
        border-bottom: 0;
    }

    @media (hover: hover) and (pointer: fine) {
        .expenses-table tbody tr:hover td {
            background: #fbfdfc;
        }
    }

    .expense-date {
        display: grid;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: .34rem;
        align-items: center;
    }

    .expense-date-icon {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 7px;
        background: var(--ex-blue-soft);
        color: var(--ex-blue);
        font-size: .68rem;
    }

    .expense-date strong {
        color: var(--ex-text);
        font-size: .64rem;
        font-weight: 770;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .expense-main {
        min-width: 0;
    }

    .expense-main strong,
    .expense-main small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .expense-main strong {
        color: var(--ex-text);
        font-size: .69rem;
        font-weight: 790;
    }

    .expense-main small {
        margin-top: .05rem;
        color: var(--ex-muted);
        font-size: .56rem;
    }

    .expense-origin {
        display: grid;
        min-width: 0;
        grid-template-columns: 28px minmax(0, 1fr);
        gap: .34rem;
        align-items: center;
    }

    .expense-origin-icon {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 7px;
        background: var(--ex-violet-soft);
        color: var(--ex-violet);
        font-size: .68rem;
    }

    .expense-origin-copy {
        min-width: 0;
    }

    .expense-origin-copy small,
    .expense-origin-copy strong {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .expense-origin-copy small {
        color: var(--ex-muted);
        font-size: .5rem;
    }

    .expense-origin-copy strong {
        margin-top: .02rem;
        color: var(--ex-text);
        font-size: .62rem;
        font-weight: 760;
    }

    .expense-values {
        display: grid;
        min-width: 0;
        gap: .18rem;
    }

    .expense-value-line {
        display: flex;
        min-width: 0;
        gap: .35rem;
        align-items: center;
        justify-content: space-between;
        white-space: nowrap;
    }

    .expense-value-line small {
        color: var(--ex-muted);
        font-size: .52rem;
    }

    .expense-value-line strong {
        color: var(--ex-text);
        font-size: .64rem;
        font-weight: 790;
        font-variant-numeric: tabular-nums;
    }

    .expense-value-line.paid strong {
        color: var(--ex-green);
    }

    .expense-status {
        --tone: var(--ex-amber);
        --soft: var(--ex-amber-soft);
        --border: var(--ex-amber-border);

        display: inline-flex;
        width: max-content;
        min-height: 28px;
        gap: .24rem;
        align-items: center;
        padding: .22rem .38rem;
        border: 1px solid var(--border);
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .57rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .expense-status.is-paid {
        --tone: var(--ex-green);
        --soft: var(--ex-green-soft);
        --border: var(--ex-green-border);
    }

    .expense-status.is-partial {
        --tone: var(--ex-blue);
        --soft: var(--ex-blue-soft);
        --border: var(--ex-blue-border);
    }

    .expense-status.is-cancelled {
        --tone: var(--ex-red);
        --soft: var(--ex-red-soft);
        --border: var(--ex-red-border);
    }

    /* =========================================================
       EMPTY
       ========================================================= */

    .expenses-empty {
        display: grid;
        min-height: 220px;
        place-items: center;
        padding: 1.2rem;
        text-align: center;
    }

    .expenses-empty-inner {
        display: grid;
        max-width: 320px;
        gap: .3rem;
        justify-items: center;
    }

    .expenses-empty-icon {
        display: grid;
        width: 46px;
        height: 46px;
        place-items: center;
        border-radius: 10px;
        background: var(--ex-amber-soft);
        color: var(--ex-amber);
        font-size: 1rem;
    }

    .expenses-empty strong {
        color: var(--ex-text);
        font-size: .78rem;
        font-weight: 830;
    }

    .expenses-empty p {
        margin: 0;
        color: var(--ex-muted);
        font-size: .66rem;
        line-height: 1.45;
    }

    /* =========================================================
       PAGINAÇÃO
       ========================================================= */

    .expenses-pagination {
        padding: .58rem .66rem;
        border-top: 1px solid var(--ex-border);
        background: var(--ex-soft);
    }

    .expenses-pagination nav {
        margin: 0;
    }

    /* =========================================================
       MOBILE LIST
       ========================================================= */

    .expenses-mobile {
        display: none;
    }

    @media (max-width: 760px) {
        .expenses-table-wrap {
            display: none;
        }

        .expenses-mobile {
            display: grid;
        }

        .expenses-mobile-item {
            display: grid;
            min-width: 0;
            gap: .48rem;
            padding: .62rem .66rem;
            border-bottom: 1px solid var(--ex-border);
        }

        .expenses-mobile-item:last-child {
            border-bottom: 0;
        }

        .expenses-mobile-top {
            display: flex;
            min-width: 0;
            gap: .5rem;
            align-items: flex-start;
            justify-content: space-between;
        }

        .expenses-mobile-main {
            min-width: 0;
        }

        .expenses-mobile-main strong,
        .expenses-mobile-main small {
            display: block;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .expenses-mobile-main strong {
            color: var(--ex-text);
            font-size: .72rem;
            font-weight: 800;
        }

        .expenses-mobile-main small {
            margin-top: .05rem;
            color: var(--ex-muted);
            font-size: .57rem;
        }

        .expenses-mobile-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .38rem;
        }

        .expenses-mobile-fact {
            display: flex;
            min-width: 0;
            gap: .26rem;
            align-items: center;
            color: var(--ex-text-2);
            font-size: .6rem;
        }

        .expenses-mobile-fact i {
            flex: 0 0 auto;
            color: var(--ex-muted);
            font-size: .68rem;
        }

        .expenses-mobile-fact strong {
            min-width: 0;
            overflow: hidden;
            color: var(--ex-text);
            font-weight: 760;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .expenses-mobile-values {
            display: flex;
            gap: .7rem;
            align-items: center;
            padding-top: .4rem;
            border-top: 1px solid var(--ex-border);
        }

        .expenses-mobile-values span {
            display: grid;
            gap: .03rem;
        }

        .expenses-mobile-values small {
            color: var(--ex-muted);
            font-size: .5rem;
        }

        .expenses-mobile-values strong {
            color: var(--ex-text);
            font-size: .65rem;
            font-weight: 790;
            font-variant-numeric: tabular-nums;
        }

        .expenses-mobile-values .paid strong {
            color: var(--ex-green);
        }
    }

    /* =========================================================
       DIALOG FORM
       ========================================================= */

    .expense-dialog {
        width: min(94vw, 620px);
        max-width: 620px;
        max-height: min(88dvh, 760px);
        margin: auto;
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 14px;
        background: #fff;
        color: var(--ex-text);
    }

    .expense-dialog::backdrop {
        background: rgba(12, 24, 16, .58);
    }

    .expense-dialog-layout {
        display: grid;
        max-height: min(88dvh, 760px);
        grid-template-rows: auto minmax(0, 1fr) auto;
    }

    .expense-dialog-head {
        display: flex;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        padding: .72rem .78rem;
        border-bottom: 1px solid var(--ex-border);
    }

    .expense-dialog-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .46rem;
        align-items: center;
    }

    .expense-dialog-head-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--ex-amber-soft);
        color: var(--ex-amber);
        font-size: .84rem;
    }

    .expense-dialog-head-copy {
        min-width: 0;
    }

    .expense-dialog-head-copy small,
    .expense-dialog-head-copy strong {
        display: block;
    }

    .expense-dialog-head-copy small {
        color: var(--ex-muted);
        font-size: .59rem;
        font-weight: 720;
    }

    .expense-dialog-head-copy strong {
        margin-top: .03rem;
        color: var(--ex-text);
        font-size: .86rem;
        font-weight: 830;
    }

    .expense-dialog-close {
        display: grid;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: var(--ex-soft);
        color: var(--ex-text-2);
        cursor: pointer;
        font-size: .88rem;
    }

    .expense-form-scroll {
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .expense-form {
        display: grid;
        min-width: 0;
        gap: 1rem;
        padding: .82rem;
    }

    .expense-section {
        display: grid;
        min-width: 0;
        gap: .72rem;
    }

    .expense-section + .expense-section {
        padding-top: .92rem;
        border-top: 1px solid var(--ex-border);
    }

    .expense-section-head {
        display: flex;
        gap: .4rem;
        align-items: center;
    }

    .expense-section-head i {
        color: var(--ex-muted);
        font-size: .76rem;
    }

    .expense-section-head strong {
        color: var(--ex-text);
        font-size: .74rem;
        font-weight: 810;
    }

    .expense-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .68rem;
    }

    .expense-field {
        display: grid;
        min-width: 0;
        gap: .3rem;
    }

    .expense-field.full {
        grid-column: 1 / -1;
    }

    .expense-attachment-input {
        width: 100%;
        padding: .72rem;
        border: 1px dashed #b9c8bf;
        border-radius: 10px;
        background: #f7faf8;
        color: var(--ex-text);
        font-size: .72rem;
    }

    .expense-attachment-help {
        color: var(--ex-muted);
        font-size: .64rem;
        line-height: 1.45;
    }

    .expense-attachment-previews,
    .expense-attachments-inline {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
    }

    .expense-attachment-preview,
    .expense-attachment-link {
        display: grid;
        width: 76px;
        min-height: 62px;
        place-items: center;
        overflow: hidden;
        border: 1px solid #dce6e0;
        border-radius: 9px;
        background: #fff;
        color: var(--ex-muted);
        text-decoration: none;
    }

    .expense-attachment-preview img,
    .expense-attachment-link img {
        width: 100%;
        height: 62px;
        object-fit: cover;
    }

    .expense-attachment-preview i,
    .expense-attachment-link i {
        font-size: 1.45rem;
    }

    .expense-attachment-preview small {
        width: 100%;
        padding: .2rem;
        overflow: hidden;
        font-size: .55rem;
        text-align: center;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .expense-submit-errors {
        display: grid;
        gap: .3rem;
        padding: .7rem .8rem;
        border: 1px solid #fecaca;
        border-radius: 9px;
        background: #fff1f2;
        color: #991b1b;
        font-size: .7rem;
    }

    .expense-label {
        display: flex;
        gap: .27rem;
        align-items: center;
        color: var(--ex-text);
        font-size: .73rem;
        font-weight: 750;
    }

    .expense-label i {
        color: var(--ex-muted);
        font-size: .74rem;
    }

    .expense-label .optional {
        color: var(--ex-muted);
        font-size: .6rem;
        font-weight: 650;
    }

    .expense-input {
        display: grid;
        min-width: 0;
        grid-template-columns: 38px minmax(0, 1fr);
        gap: .18rem;
        align-items: center;
        min-height: 48px;
        padding: .28rem .38rem .28rem .3rem;
        border: 1px solid transparent;
        border-radius: 9px;
        background: #f3f6f4;
    }

    .expense-input:focus-within {
        border-color: var(--ex-blue);
        background: #fff;
        box-shadow: 0 0 0 3px var(--ex-blue-soft);
    }

    .expense-input.filled {
        border-color: #e2e8e4;
        background: #fff;
    }

    .expense-input-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 7px;
        background: var(--ex-soft);
        color: var(--ex-text-2);
        font-size: .78rem;
    }

    .expense-input.amount .expense-input-icon {
        background: var(--ex-green-soft);
        color: var(--ex-green);
    }

    .expense-input.document .expense-input-icon {
        background: var(--ex-violet-soft);
        color: var(--ex-violet);
    }

    .expense-input input,
    .expense-input textarea {
        width: 100%;
        min-width: 0;
        min-height: 38px;
        padding: .44rem .48rem;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--ex-text);
        font: inherit;
        font-size: .78rem;
    }

    .expense-input textarea {
        min-height: 88px;
        resize: vertical;
    }

    /* Select/date triggers */
    .expense-native-select,
    .expense-native-date {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        margin: -1px !important;
        padding: 0 !important;
        overflow: hidden !important;
        clip: rect(0 0 0 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }

    .expense-select-trigger,
    .expense-date-trigger {
        --tone: var(--ex-blue);
        --soft: var(--ex-blue-soft);

        display: grid;
        width: 100%;
        min-width: 0;
        min-height: 48px;
        grid-template-columns: 36px minmax(0, 1fr) 26px;
        gap: .36rem;
        align-items: center;
        padding: .3rem .4rem .3rem .3rem;
        border: 1px solid transparent;
        border-radius: 9px;
        background: #f3f6f4;
        color: var(--ex-text);
        cursor: pointer;
        font: inherit;
        text-align: left;
    }

    .expense-select-trigger[data-tone="violet"] {
        --tone: var(--ex-violet);
        --soft: var(--ex-violet-soft);
    }

    .expense-select-trigger[data-tone="amber"] {
        --tone: var(--ex-amber);
        --soft: var(--ex-amber-soft);
    }

    .expense-date-trigger.due {
        --tone: var(--ex-amber);
        --soft: var(--ex-amber-soft);
    }

    .expense-select-trigger.filled,
    .expense-date-trigger.filled {
        border-color: #e2e8e4;
        background: #fff;
    }

    .expense-select-trigger.invalid,
    .expense-date-trigger.invalid {
        border-color: var(--ex-red);
        background: var(--ex-red-soft);
    }

    .expense-trigger-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .79rem;
    }

    .expense-trigger-copy {
        min-width: 0;
    }

    .expense-trigger-value,
    .expense-trigger-meta {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .expense-trigger-value {
        color: var(--ex-text);
        font-size: .77rem;
        font-weight: 740;
    }

    .expense-trigger-value.placeholder {
        color: var(--ex-muted);
        font-weight: 650;
    }

    .expense-trigger-meta {
        margin-top: .03rem;
        color: var(--ex-muted);
        font-size: .61rem;
    }

    .expense-trigger-caret {
        display: grid;
        width: 26px;
        height: 26px;
        place-items: center;
        color: var(--ex-muted);
        font-size: .7rem;
    }

    .expense-field-error {
        display: inline-flex;
        gap: .22rem;
        align-items: center;
        color: var(--ex-red);
        font-size: .63rem;
        font-weight: 720;
    }

    /* Optional block */
    .expense-optional {
        overflow: hidden;
        border: 1px solid var(--ex-border);
        border-radius: 9px;
        background: #fff;
    }

    .expense-optional summary {
        display: flex;
        gap: .42rem;
        align-items: center;
        min-height: 44px;
        padding: .5rem .6rem;
        color: var(--ex-text-2);
        cursor: pointer;
        font-size: .7rem;
        font-weight: 760;
        list-style: none;
    }

    .expense-optional summary::-webkit-details-marker {
        display: none;
    }

    .expense-optional summary i:first-child {
        color: var(--ex-violet);
        font-size: .78rem;
    }

    .expense-optional summary .caret {
        margin-left: auto;
        color: var(--ex-muted);
        transition: transform .15s ease;
    }

    .expense-optional[open] summary .caret {
        transform: rotate(180deg);
    }

    .expense-optional-body {
        padding: .68rem;
        border-top: 1px solid var(--ex-border);
    }

    /* Footer */
    .expense-dialog-foot {
        display: flex;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
        padding: .62rem .72rem;
        border-top: 1px solid var(--ex-border);
        background: #fbfdfc;
    }

    .expense-cancel,
    .expense-submit {
        display: inline-flex;
        min-height: 39px;
        gap: .28rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .62rem;
        border-radius: 8px;
        cursor: pointer;
        font: inherit;
        font-size: .71rem;
        font-weight: 780;
    }

    .expense-cancel {
        border: 1px solid var(--ex-border);
        background: #fff;
        color: var(--ex-text-2);
    }

    .expense-submit {
        border: 1px solid var(--ex-green);
        background: var(--ex-green);
        color: #fff;
    }

    .expense-submit[disabled] {
        cursor: wait;
        opacity: .72;
    }

    @keyframes expense-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .expense-submit.loading i {
        animation: expense-spin .8s linear infinite;
    }

    /* =========================================================
       PICKER DIALOG
       ========================================================= */

    .expense-picker-dialog,
    .expense-date-dialog {
        width: min(94vw, 520px);
        max-width: 520px;
        max-height: min(84dvh, 680px);
        margin: auto;
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 14px;
        background: #fff;
        color: var(--ex-text);
    }

    .expense-picker-dialog::backdrop,
    .expense-date-dialog::backdrop {
        background: rgba(12, 24, 16, .58);
    }

    .expense-picker-dialog,
    .expense-date-dialog {
        --ex-green: var(--ws-green, #219653);
        --ex-green-soft: #edf8f2;
        --ex-green-border: #cce8d7;
        --ex-blue: var(--ws-blue, #3478d4);
        --ex-blue-soft: #edf4ff;
        --ex-blue-border: #cfe0f7;
        --ex-violet: var(--ws-purple, #8a4bd2);
        --ex-violet-soft: #f5efff;
        --ex-amber: var(--ws-amber, #c38418);
        --ex-amber-soft: #fff7e8;
        --ex-amber-border: #f0dcae;
        --ex-text: #17211d;
        --ex-text-2: #59655f;
        --ex-muted: #89938e;
        --ex-border: #dde5e0;
        --ex-soft: #f7faf8;
    }

    .expense-picker-layout {
        display: grid;
        max-height: min(84dvh, 680px);
        grid-template-rows: auto minmax(0, 1fr) auto;
    }

    .expense-picker-head {
        display: flex;
        gap: .6rem;
        align-items: center;
        justify-content: space-between;
        padding: .7rem .76rem;
        border-bottom: 1px solid var(--ex-border);
    }

    .expense-picker-head strong {
        color: var(--ex-text);
        font-size: .84rem;
        font-weight: 820;
    }

    .expense-picker-close {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: var(--ex-soft);
        color: var(--ex-text-2);
        cursor: pointer;
        font-size: .86rem;
    }

    .expense-picker-body {
        display: grid;
        min-height: 0;
        grid-template-rows: auto minmax(0, 1fr);
    }

    .expense-picker-search {
        display: grid;
        grid-template-columns: 38px minmax(0, 1fr);
        align-items: center;
        margin: .66rem .7rem .4rem;
        overflow: hidden;
        border: 1px solid var(--ex-border);
        border-radius: 9px;
        background: var(--ex-soft);
    }

    .expense-picker-search i {
        display: grid;
        width: 38px;
        place-items: center;
        color: var(--ex-muted);
        font-size: .8rem;
    }

    .expense-picker-search input {
        min-width: 0;
        min-height: 42px;
        padding: .48rem .52rem;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--ex-text);
        font: inherit;
        font-size: .76rem;
    }

    .expense-picker-options {
        display: grid;
        min-height: 0;
        align-content: start;
        overflow-y: auto;
        padding: 0 .7rem .7rem;
    }

    .expense-picker-option {
        display: grid;
        width: 100%;
        min-width: 0;
        grid-template-columns: 34px minmax(0, 1fr) 24px;
        gap: .42rem;
        align-items: center;
        min-height: 50px;
        padding: .43rem .06rem;
        border: 0;
        border-bottom: 1px solid var(--ex-border);
        background: transparent;
        color: var(--ex-text);
        cursor: pointer;
        font: inherit;
        text-align: left;
    }

    .expense-picker-option:last-child {
        border-bottom: 0;
    }

    .expense-picker-option.selected {
        background: var(--ex-green-soft);
    }

    .expense-picker-option-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 7px;
        background: var(--ex-blue-soft);
        color: var(--ex-blue);
        font-size: .76rem;
    }

    .expense-picker-option-copy {
        min-width: 0;
    }

    .expense-picker-option-copy strong,
    .expense-picker-option-copy small {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .expense-picker-option-copy strong {
        color: var(--ex-text);
        font-size: .75rem;
        font-weight: 770;
    }

    .expense-picker-option-copy small {
        margin-top: .04rem;
        color: var(--ex-muted);
        font-size: .62rem;
    }

    .expense-picker-check {
        color: var(--ex-green);
        font-size: .8rem;
    }

    .expense-picker-empty {
        padding: 1.2rem .5rem;
        color: var(--ex-muted);
        font-size: .7rem;
        text-align: center;
    }

    /* Calendar */
    .expense-calendar-body {
        padding: .7rem;
        overflow-y: auto;
    }

    .expense-calendar-nav {
        display: grid;
        grid-template-columns: 38px minmax(0, 1fr) 38px;
        gap: .4rem;
        align-items: center;
        margin-bottom: .6rem;
    }

    .expense-calendar-nav button {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: var(--ex-soft);
        color: var(--ex-text-2);
        cursor: pointer;
        font-size: .8rem;
    }

    .expense-calendar-month {
        color: var(--ex-text);
        font-size: .8rem;
        font-weight: 800;
        text-align: center;
    }

    .expense-calendar-week,
    .expense-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: .27rem;
    }

    .expense-calendar-week {
        margin-bottom: .3rem;
    }

    .expense-calendar-week span {
        color: var(--ex-muted);
        font-size: .6rem;
        font-weight: 720;
        text-align: center;
    }

    .expense-calendar-week span:first-child {
        color: #a45b5b;
    }

    .expense-calendar-day {
        display: grid;
        aspect-ratio: 1;
        min-width: 0;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--ex-text);
        cursor: pointer;
        font: inherit;
        font-size: .72rem;
        font-weight: 700;
    }

    .expense-calendar-day.sunday:not(.selected) {
        background: #fbf2f2;
        color: #9f5454;
    }

    .expense-calendar-day.sunday.outside:not(.selected) {
        background: transparent;
        color: #c59a9a;
    }

    .expense-calendar-day.today:not(.selected) {
        background: var(--ex-green-soft);
        color: #1f754b;
        box-shadow: inset 0 0 0 1px var(--ex-green-border);
        font-weight: 840;
    }

    .expense-calendar-day.selected {
        background: var(--ex-blue);
        color: #fff;
    }

    .expense-calendar-day.outside:not(.selected):not(.sunday) {
        color: #bcc4c0;
    }

    .expense-date-foot {
        display: flex;
        justify-content: flex-end;
        padding: .62rem .7rem;
        border-top: 1px solid var(--ex-border);
    }

    .expense-today {
        display: inline-flex;
        min-height: 38px;
        align-items: center;
        justify-content: center;
        padding: .38rem .6rem;
        border: 1px solid var(--ex-green-border);
        border-radius: 8px;
        background: var(--ex-green-soft);
        color: #1f754b;
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 760;
    }

    @media (max-width: 620px) {
        .expenses-head {
            padding: .62rem .66rem;
        }

        .expenses-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .expenses-head-icon {
            width: 36px;
            height: 36px;
        }

        .expenses-head-copy p {
            display: none;
        }

        .expense-new {
            width: 39px;
            min-width: 39px;
            padding: 0;
        }

        .expense-new span {
            display: none;
        }

        .expenses-mobile-meta {
            grid-template-columns: 1fr;
        }

        .expense-dialog {
            width: calc(100vw - 1rem);
            max-height: calc(100dvh - 1rem);
        }

        .expense-dialog-layout {
            max-height: calc(100dvh - 1rem);
        }

        .expense-fields {
            grid-template-columns: 1fr;
        }

        .expense-field.full {
            grid-column: auto;
        }

        .expense-input input,
        .expense-input textarea,
        .expense-picker-search input {
            font-size: 16px;
        }

        .expense-dialog-foot {
            padding-bottom:
                max(.62rem, env(safe-area-inset-bottom));
        }

        .expense-submit {
            flex: 1 1 auto;
        }

        .expense-picker-dialog,
        .expense-date-dialog {
            width: calc(100vw - 1rem);
            max-height: calc(100dvh - 1rem);
        }

        .expense-picker-layout {
            max-height: calc(100dvh - 1rem);
        }
    }
</style>

<main class="expenses-page">
    <header class="expenses-head">
        <div class="expenses-head-main">
            <span
                class="expenses-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-receipt-x"></i>
            </span>

            <div class="expenses-head-copy">
                <small>Serviços</small>
                <h1>Despesas</h1>
                <p>Registros pendentes e pagamentos do módulo.</p>
            </div>
        </div>

        <button
            class="expense-new"
            id="open-expense-form"
            type="button"
        >
            <i class="ph-fill ph-plus-circle"></i>
            <span>Nova despesa</span>
        </button>
    </header>

    @if($errors->any())
        <section class="expense-errors" role="alert">
            <span
                class="expense-errors-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-warning-circle"></i>
            </span>

            <div>
                <strong>Confira os dados</strong>

                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    <section class="expenses-list">
        <header class="expenses-list-head">
            <div class="expenses-list-title">
                <span
                    class="expenses-list-title-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-list-checks"></i>
                </span>

                <strong>Despesas registradas</strong>
            </div>

            @if($expenseTotal > 0)
                <span class="expenses-total">
                    {{ $expenseTotal }}
                    {{ $expenseTotal === 1
                        ? 'registro'
                        : 'registros' }}
                </span>
            @endif
        </header>

        @if($expenses->isEmpty())
            <div class="expenses-empty">
                <div class="expenses-empty-inner">
                    <span
                        class="expenses-empty-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-fill ph-receipt"></i>
                    </span>

                    <strong>Nenhuma despesa</strong>

                    <p>
                        As despesas de serviços aparecerão aqui.
                    </p>

                    <button
                        class="expense-new"
                        type="button"
                        data-open-expense
                    >
                        <i class="ph-fill ph-plus-circle"></i>
                        <span>Nova despesa</span>
                    </button>
                </div>
            </div>
        @else
            <div class="expenses-table-wrap">
                <table
                    class="expenses-table"
                    aria-label="Despesas de serviços"
                >
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Despesa</th>
                            <th>Origem</th>
                            <th>Valores</th>
                            <th>Situação</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($expenses as $expense)
                            @php
                                $status = $expenseStatusMeta(
                                    $expense->status
                                );

                                $origin =
                                    $expense->expenseable?->number
                                    ?? 'Serviços gerais';
                            @endphp

                            <tr>
                                <td>
                                    <div class="expense-date">
                                        <span
                                            class="expense-date-icon"
                                            aria-hidden="true"
                                        >
                                            <i class="ph-fill ph-calendar-blank"></i>
                                        </span>

                                        <strong>
                                            {{ $expense->date?->format('d/m/Y') ?? '—' }}
                                        </strong>
                                    </div>
                                </td>

                                <td>
                                    <div class="expense-main">
                                        <strong
                                            title="{{ $expense->description }}"
                                        >
                                            {{ $expense->description }}
                                        </strong>

                                        @if($expense->document_number ?? null)
                                            <small>
                                                Doc. {{ $expense->document_number }}
                                            </small>
                                        @endif

                                        @if($expense->documents->isNotEmpty())
                                            <div class="expense-attachments-inline" aria-label="Comprovantes">
                                                @foreach($expense->documents as $document)
                                                    <a
                                                        class="expense-attachment-link"
                                                        href="{{ route('provider.expenses.documents.show', [$tenantSlug, $document]) }}"
                                                        target="_blank"
                                                        rel="noopener"
                                                        title="Visualizar {{ $document->name }}"
                                                    >
                                                        @if(str_starts_with((string) $document->mime_type, 'image/'))
                                                            <img
                                                                src="{{ route('provider.expenses.documents.show', [$tenantSlug, $document]) }}"
                                                                alt="Prévia de {{ $document->name }}"
                                                                loading="lazy"
                                                            >
                                                        @else
                                                            <i class="ph-fill ph-file-pdf" aria-hidden="true"></i>
                                                        @endif
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <div class="expense-origin">
                                        <span
                                            class="expense-origin-icon"
                                            aria-hidden="true"
                                        >
                                            <i
                                                class="
                                                    ph-fill
                                                    {{
                                                        $expense->expenseable
                                                            ? 'ph-clipboard-text'
                                                            : 'ph-stack'
                                                    }}
                                                "
                                            ></i>
                                        </span>

                                        <span class="expense-origin-copy">
                                            <small>
                                                {{
                                                    $expense->expenseable
                                                        ? 'Ordem'
                                                        : 'Origem'
                                                }}
                                            </small>

                                            <strong
                                                title="{{ $origin }}"
                                            >
                                                {{ $origin }}
                                            </strong>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <div class="expense-values">
                                        <span class="expense-value-line">
                                            <small>Total</small>

                                            <strong>
                                                R$ {{ number_format(
                                                    $expense->total_amount,
                                                    2,
                                                    ',',
                                                    '.'
                                                ) }}
                                            </strong>
                                        </span>

                                        <span class="expense-value-line paid">
                                            <small>Pago</small>

                                            <strong>
                                                R$ {{ number_format(
                                                    (float) $expense->paid_amount,
                                                    2,
                                                    ',',
                                                    '.'
                                                ) }}
                                            </strong>
                                        </span>
                                    </div>
                                </td>

                                <td>
                                    <span
                                        class="
                                            expense-status
                                            {{ $status['class'] }}
                                        "
                                    >
                                        <i
                                            class="
                                                ph-fill
                                                {{ $status['icon'] }}
                                            "
                                            aria-hidden="true"
                                        ></i>

                                        {{ $status['label'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="expenses-mobile">
                @foreach($expenses as $expense)
                    @php
                        $status = $expenseStatusMeta(
                            $expense->status
                        );

                        $origin =
                            $expense->expenseable?->number
                            ?? 'Serviços gerais';
                    @endphp

                    <article class="expenses-mobile-item">
                        <div class="expenses-mobile-top">
                            <div class="expenses-mobile-main">
                                <strong>
                                    {{ $expense->description }}
                                </strong>

                                <small>
                                    {{ $expense->date?->format('d/m/Y') ?? 'Sem data' }}
                                    · {{ $origin }}
                                </small>
                            </div>

                            <span
                                class="
                                    expense-status
                                    {{ $status['class'] }}
                                "
                            >
                                <i
                                    class="
                                        ph-fill
                                        {{ $status['icon'] }}
                                    "
                                ></i>

                                {{ $status['label'] }}
                            </span>
                        </div>

                        <div class="expenses-mobile-meta">
                            @if($expense->document_number ?? null)
                                <span class="expenses-mobile-fact">
                                    <i class="ph-fill ph-file-text"></i>

                                    <strong>
                                        {{ $expense->document_number }}
                                    </strong>
                                </span>
                            @endif

                            <span class="expenses-mobile-fact">
                                <i class="ph-fill ph-clipboard-text"></i>

                                <strong>
                                    {{ $origin }}
                                </strong>
                            </span>
                        </div>

                        @if($expense->documents->isNotEmpty())
                            <div class="expense-attachments-inline" aria-label="Comprovantes">
                                @foreach($expense->documents as $document)
                                    <a
                                        class="expense-attachment-link"
                                        href="{{ route('provider.expenses.documents.show', [$tenantSlug, $document]) }}"
                                        target="_blank"
                                        rel="noopener"
                                        title="Visualizar {{ $document->name }}"
                                    >
                                        @if(str_starts_with((string) $document->mime_type, 'image/'))
                                            <img
                                                src="{{ route('provider.expenses.documents.show', [$tenantSlug, $document]) }}"
                                                alt="Prévia de {{ $document->name }}"
                                                loading="lazy"
                                            >
                                        @else
                                            <i class="ph-fill ph-file-pdf" aria-hidden="true"></i>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        <div class="expenses-mobile-values">
                            <span>
                                <small>Total</small>

                                <strong>
                                    R$ {{ number_format(
                                        $expense->total_amount,
                                        2,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>
                            </span>

                            <span class="paid">
                                <small>Pago</small>

                                <strong>
                                    R$ {{ number_format(
                                        (float) $expense->paid_amount,
                                        2,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>

            @if(
                method_exists($expenses, 'hasPages')
                && $expenses->hasPages()
            )
                <div class="expenses-pagination">
                    {{ $expenses->links() }}
                </div>
            @endif
        @endif
    </section>
</main>

<dialog
    class="expense-dialog"
    id="expense-form-dialog"
    aria-label="Nova despesa"
>
    <div class="expense-dialog-layout">
        <header class="expense-dialog-head">
            <div class="expense-dialog-head-main">
                <span
                    class="expense-dialog-head-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-receipt-x"></i>
                </span>

                <span class="expense-dialog-head-copy">
                    <small>Serviços</small>
                    <strong>Nova despesa</strong>
                </span>
            </div>

            <button
                class="expense-dialog-close"
                type="button"
                data-close-dialog="expense-form-dialog"
                aria-label="Fechar"
            >
                <i class="ph-fill ph-x"></i>
            </button>
        </header>

        <div class="expense-form-scroll">
            <form
                id="expense-form"
                class="expense-form"
                method="post"
                enctype="multipart/form-data"
                action="{{ route(
                    'provider.expenses.store',
                    $tenantSlug
                ) }}"
            >
                @csrf

                <section class="expense-section">
                    <div class="expense-section-head">
                        <i class="ph-fill ph-receipt"></i>
                        <strong>Despesa</strong>
                    </div>

                    <div class="expense-fields">
                        <label class="expense-field full">
                            <span class="expense-label">
                                <i class="ph-fill ph-text-aa"></i>
                                Descrição
                            </span>

                            <div class="expense-input">
                                <span
                                    class="expense-input-icon"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill ph-receipt"></i>
                                </span>

                                <input
                                    name="description"
                                    required
                                    maxlength="191"
                                    value="{{ old('description') }}"
                                    placeholder="Ex.: combustível"
                                >
                            </div>
                        </label>

                        <label class="expense-field">
                            <span class="expense-label">
                                <i class="ph-fill ph-currency-circle-dollar"></i>
                                Valor
                            </span>

                            <div class="expense-input amount">
                                <span
                                    class="expense-input-icon"
                                    aria-hidden="true"
                                >
                                    <strong>R$</strong>
                                </span>

                                <input
                                    type="number"
                                    name="amount"
                                    min="0.01"
                                    step="0.01"
                                    inputmode="decimal"
                                    required
                                    value="{{ old('amount') }}"
                                    placeholder="0,00"
                                >
                            </div>
                        </label>

                        <div class="expense-field">
                            <span class="expense-label">
                                <i class="ph-fill ph-calendar-blank"></i>
                                Data
                            </span>

                            <input
                                id="expense-date"
                                class="expense-native-date"
                                type="hidden"
                                name="date"
                                value="{{ $oldDate }}"
                                data-required="1"
                            >

                            <button
                                class="expense-date-trigger"
                                type="button"
                                data-date-target="expense-date"
                                data-date-title="Escolher data"
                            >
                                <span
                                    class="expense-trigger-icon"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill ph-calendar-blank"></i>
                                </span>

                                <span class="expense-trigger-copy">
                                    <span
                                        class="expense-trigger-value"
                                    ></span>
                                </span>

                                <span
                                    class="expense-trigger-caret"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill ph-caret-down"></i>
                                </span>
                            </button>
                        </div>

                        <div class="expense-field">
                            <span class="expense-label">
                                <i class="ph-fill ph-calendar-check"></i>
                                Vencimento
                            </span>

                            <input
                                id="expense-due-date"
                                class="expense-native-date"
                                type="hidden"
                                name="due_date"
                                value="{{ $oldDueDate }}"
                                data-required="1"
                            >

                            <button
                                class="expense-date-trigger due"
                                type="button"
                                data-date-target="expense-due-date"
                                data-date-title="Escolher vencimento"
                            >
                                <span
                                    class="expense-trigger-icon"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill ph-calendar-check"></i>
                                </span>

                                <span class="expense-trigger-copy">
                                    <span
                                        class="expense-trigger-value"
                                    ></span>
                                </span>

                                <span
                                    class="expense-trigger-caret"
                                    aria-hidden="true"
                                >
                                    <i class="ph-fill ph-caret-down"></i>
                                </span>
                            </button>
                        </div>
                    </div>
                </section>

                <details
                    class="expense-optional"
                    id="expense-optional"
                    {{
                        old('service_order_id')
                        || old('chart_account_id')
                        || old('document_number')
                        || old('notes')
                            ? 'open'
                            : ''
                    }}
                >
                    <summary>
                        <i class="ph-fill ph-link"></i>
                        Vínculos e detalhes
                        <i class="ph-fill ph-caret-down caret"></i>
                    </summary>

                    <div class="expense-optional-body">
                        <div class="expense-fields">
                            <label class="expense-field full">
                                <span class="expense-label">
                                    <i class="ph-fill ph-clipboard-text"></i>
                                    Ordem de serviço
                                    <span class="optional">opcional</span>
                                </span>

                                <select
                                    id="expense-order"
                                    name="service_order_id"
                                    data-custom-select
                                    data-picker-title="Escolher ordem"
                                    data-picker-icon="ph-clipboard-text"
                                    data-picker-tone="violet"
                                >
                                    <option
                                        value=""
                                        data-primary="Serviços gerais"
                                        data-secondary="Sem ordem vinculada"
                                    >
                                        Despesa geral de serviços
                                    </option>

                                    @foreach($orders as $order)
                                        @php
                                            $orderBeneficiary =
                                                $order->beneficiary_snapshot['name']
                                                ?? 'Sem beneficiário';

                                            $orderDate =
                                                $order->scheduled_at?->format('d/m/Y')
                                                ?? 'Sem data';
                                        @endphp

                                        <option
                                            value="{{ $order->id }}"
                                            data-primary="OS {{ $order->number }}"
                                            data-secondary="{{ $orderBeneficiary }} · {{ $orderDate }}"
                                            data-search="{{ $order->number }} {{ $orderBeneficiary }} {{ $orderDate }}"
                                            data-option-icon="ph-clipboard-text"
                                            @selected(old('service_order_id') == $order->id)
                                        >
                                            {{ $order->number }}
                                            · {{ $orderBeneficiary }}
                                            · {{ $orderDate }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="expense-field full">
                                <span class="expense-label">
                                    <i class="ph-fill ph-tree-structure"></i>
                                    Plano de contas
                                    <span class="optional">opcional</span>
                                </span>

                                <select
                                    id="expense-account"
                                    name="chart_account_id"
                                    data-custom-select
                                    data-picker-title="Escolher conta"
                                    data-picker-icon="ph-tree-structure"
                                    data-picker-tone="amber"
                                >
                                    <option
                                        value=""
                                        data-primary="Não informado"
                                    >
                                        Não informado
                                    </option>

                                    @foreach($accounts as $account)
                                        <option
                                            value="{{ $account->id }}"
                                            data-primary="{{ $account->name }}"
                                            data-search="{{ $account->name }}"
                                            data-option-icon="ph-folder-simple"
                                            @selected(old('chart_account_id') == $account->id)
                                        >
                                            {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="expense-field full">
                                <span class="expense-label">
                                    <i class="ph-fill ph-file-text"></i>
                                    Nº do documento
                                    <span class="optional">opcional</span>
                                </span>

                                <div class="expense-input document">
                                    <span
                                        class="expense-input-icon"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-fill ph-file-text"></i>
                                    </span>

                                    <input
                                        name="document_number"
                                        maxlength="80"
                                        value="{{ old('document_number') }}"
                                        placeholder="Número, nota ou referência"
                                    >
                                </div>
                            </label>

                            <label class="expense-field full">
                                <span class="expense-label">
                                    <i class="ph-fill ph-paperclip"></i>
                                    Comprovantes
                                    <span class="optional">opcional</span>
                                </span>

                                <input
                                    id="expense-attachments"
                                    class="expense-attachment-input"
                                    type="file"
                                    name="attachments[]"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    multiple
                                >

                                <small class="expense-attachment-help">
                                    Até 5 imagens ou PDFs, com no máximo 12 MB cada. As imagens são reduzidas e convertidas para WebP antes do envio.
                                </small>

                                <div
                                    id="expense-attachment-previews"
                                    class="expense-attachment-previews"
                                    aria-live="polite"
                                ></div>
                            </label>

                            <label class="expense-field full">
                                <span class="expense-label">
                                    <i class="ph-fill ph-note-pencil"></i>
                                    Observações
                                    <span class="optional">opcional</span>
                                </span>

                                <div class="expense-input">
                                    <span
                                        class="expense-input-icon"
                                        aria-hidden="true"
                                    >
                                        <i class="ph-fill ph-note-pencil"></i>
                                    </span>

                                    <textarea
                                        name="notes"
                                        maxlength="2000"
                                        placeholder="Observações"
                                    >{{ old('notes') }}</textarea>
                                </div>
                            </label>
                        </div>
                    </div>
                </details>
            </form>
        </div>

        <footer class="expense-dialog-foot">
            <button
                class="expense-cancel"
                type="button"
                data-close-dialog="expense-form-dialog"
            >
                Cancelar
            </button>

            <button
                id="expense-submit"
                class="expense-submit"
                type="submit"
                form="expense-form"
            >
                <i class="ph-fill ph-check-circle"></i>
                Registrar despesa
            </button>
        </footer>
    </div>
</dialog>

<dialog
    class="expense-picker-dialog"
    id="expense-picker-dialog"
    aria-label="Escolher opção"
>
    <div class="expense-picker-layout">
        <header class="expense-picker-head">
            <strong id="expense-picker-title">
                Escolher
            </strong>

            <button
                class="expense-picker-close"
                type="button"
                data-close-dialog="expense-picker-dialog"
                aria-label="Fechar"
            >
                <i class="ph-fill ph-x"></i>
            </button>
        </header>

        <div class="expense-picker-body">
            <label class="expense-picker-search">
                <i class="ph-fill ph-magnifying-glass"></i>

                <input
                    id="expense-picker-search"
                    type="search"
                    placeholder="Buscar"
                    autocomplete="off"
                >
            </label>

            <div
                id="expense-picker-options"
                class="expense-picker-options"
            ></div>
        </div>

        <div></div>
    </div>
</dialog>

<dialog
    class="expense-date-dialog"
    id="expense-date-dialog"
    aria-label="Escolher data"
>
    <div class="expense-picker-layout">
        <header class="expense-picker-head">
            <strong id="expense-date-title">
                Escolher data
            </strong>

            <button
                class="expense-picker-close"
                type="button"
                data-close-dialog="expense-date-dialog"
                aria-label="Fechar"
            >
                <i class="ph-fill ph-x"></i>
            </button>
        </header>

        <div class="expense-calendar-body">
            <div class="expense-calendar-nav">
                <button
                    id="expense-calendar-prev"
                    type="button"
                    aria-label="Mês anterior"
                >
                    <i class="ph-fill ph-caret-left"></i>
                </button>

                <strong
                    id="expense-calendar-month"
                    class="expense-calendar-month"
                ></strong>

                <button
                    id="expense-calendar-next"
                    type="button"
                    aria-label="Próximo mês"
                >
                    <i class="ph-fill ph-caret-right"></i>
                </button>
            </div>

            <div class="expense-calendar-week" aria-hidden="true">
                <span>Dom</span>
                <span>Seg</span>
                <span>Ter</span>
                <span>Qua</span>
                <span>Qui</span>
                <span>Sex</span>
                <span>Sáb</span>
            </div>

            <div
                id="expense-calendar-grid"
                class="expense-calendar-grid"
            ></div>
        </div>

        <footer class="expense-date-foot">
            <button
                id="expense-calendar-today"
                class="expense-today"
                type="button"
            >
                Hoje
            </button>
        </footer>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form =
        document.getElementById('expense-form');

    const formDialog =
        document.getElementById('expense-form-dialog');

    const pickerDialog =
        document.getElementById('expense-picker-dialog');

    const dateDialog =
        document.getElementById('expense-date-dialog');

    const pickerTitle =
        document.getElementById('expense-picker-title');

    const pickerSearch =
        document.getElementById('expense-picker-search');

    const pickerOptions =
        document.getElementById('expense-picker-options');

    const dateTitle =
        document.getElementById('expense-date-title');

    const calendarMonth =
        document.getElementById('expense-calendar-month');

    const calendarGrid =
        document.getElementById('expense-calendar-grid');

    const calendarPrev =
        document.getElementById('expense-calendar-prev');

    const calendarNext =
        document.getElementById('expense-calendar-next');

    const calendarToday =
        document.getElementById('expense-calendar-today');

    const submitButton =
        document.getElementById('expense-submit');

    const attachmentInput =
        document.getElementById('expense-attachments');

    const attachmentPreviews =
        document.getElementById('expense-attachment-previews');

    const selectTriggers =
        new WeakMap();

    let activeSelect = null;
    let activeDateInput = null;
    let activeDateTrigger = null;
    let calendarView = new Date();
    let calendarSelected = '';

    const escapeHtml = value => {
        const node =
            document.createElement('div');

        node.textContent =
            String(value ?? '');

        return node.innerHTML;
    };

    const renderAttachmentPreviews = files => {
        attachmentPreviews.innerHTML = '';

        [...files].forEach(file => {
            const card = document.createElement('span');
            card.className = 'expense-attachment-preview';

            if (file.type.startsWith('image/')) {
                const image = document.createElement('img');
                image.alt = `Prévia de ${file.name}`;
                image.src = URL.createObjectURL(file);
                image.addEventListener('load', () => URL.revokeObjectURL(image.src), { once: true });
                card.appendChild(image);
            } else {
                const icon = document.createElement('i');
                icon.className = 'ph-fill ph-file-pdf';
                icon.setAttribute('aria-hidden', 'true');
                card.appendChild(icon);
            }

            const name = document.createElement('small');
            name.textContent = file.name;
            name.title = file.name;
            card.appendChild(name);
            attachmentPreviews.appendChild(card);
        });
    };

    const optimizeImage = file => new Promise(resolve => {
        if (!file.type.startsWith('image/') || file.type === 'image/webp') {
            resolve(file);
            return;
        }

        const image = new Image();
        const source = URL.createObjectURL(file);
        image.onload = () => {
            URL.revokeObjectURL(source);
            const scale = Math.min(1, 2048 / Math.max(image.naturalWidth, image.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
            canvas.getContext('2d').drawImage(image, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(blob => {
                if (!blob) {
                    resolve(file);
                    return;
                }

                resolve(new File(
                    [blob],
                    `${file.name.replace(/\.[^.]+$/, '')}.webp`,
                    { type: 'image/webp', lastModified: file.lastModified }
                ));
            }, 'image/webp', .82);
        };
        image.onerror = () => {
            URL.revokeObjectURL(source);
            resolve(file);
        };
        image.src = source;
    });

    attachmentInput?.addEventListener('change', async () => {
        const selected = [...attachmentInput.files].slice(0, 5);
        const optimized = await Promise.all(selected.map(optimizeImage));
        const transfer = new DataTransfer();
        optimized.forEach(file => transfer.items.add(file));
        attachmentInput.files = transfer.files;
        renderAttachmentPreviews(transfer.files);
    });

    const showSubmitErrors = errors => {
        form.querySelector('.expense-submit-errors')?.remove();
        const messages = Object.values(errors || {}).flat();

        if (!messages.length) {
            return;
        }

        const box = document.createElement('div');
        box.className = 'expense-submit-errors';
        box.setAttribute('role', 'alert');
        box.innerHTML = messages.map(message => `<span><i class="ph-fill ph-warning-circle"></i> ${escapeHtml(message)}</span>`).join('');
        form.prepend(box);
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    const pad2 = value =>
        String(value).padStart(2, '0');

    const parseDateOnly = value => {
        const match =
            /^(\d{4})-(\d{2})-(\d{2})$/
                .exec(String(value || ''));

        if (!match) {
            return null;
        }

        return {
            year: Number(match[1]),
            month: Number(match[2]),
            day: Number(match[3]),
        };
    };

    const dateOnlyToString = (
        year,
        month,
        day
    ) => {
        return [
            year,
            pad2(month),
            pad2(day),
        ].join('-');
    };

    const formatDate = value => {
        const parts =
            parseDateOnly(value);

        if (!parts) {
            return 'Escolher data';
        }

        return `${pad2(parts.day)}/${pad2(parts.month)}/${parts.year}`;
    };

    /* =====================================================
       DIALOG HISTORY
       ===================================================== */

    const dialogs = [
        formDialog,
        pickerDialog,
        dateDialog,
    ];

    const directClose = dialog => {
        if (!dialog) {
            return;
        }

        if (
            typeof dialog.close === 'function'
            && dialog.open
        ) {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
    };

    const openManagedDialog = dialog => {
        if (!dialog) {
            return;
        }

        if (!dialog.hasAttribute('open')) {
            if (
                typeof dialog.showModal
                === 'function'
            ) {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', '');
            }
        }

        if (
            history.state?.expenseDialog
            !== dialog.id
        ) {
            history.pushState(
                {
                    ...(history.state || {}),
                    expenseDialog: dialog.id,
                },
                '',
                window.location.href
            );
        }
    };

    const requestClose = dialog => {
        if (!dialog) {
            return;
        }

        if (
            history.state?.expenseDialog
            === dialog.id
        ) {
            history.back();
            return;
        }

        directClose(dialog);
    };

    window.addEventListener(
        'popstate',
        () => {
            dialogs.forEach(dialog => {
                if (
                    dialog?.hasAttribute('open')
                    && history.state?.expenseDialog
                        !== dialog.id
                ) {
                    directClose(dialog);
                }
            });
        }
    );

    dialogs.forEach(dialog => {
        dialog?.addEventListener(
            'cancel',
            event => {
                event.preventDefault();
                requestClose(dialog);
            }
        );

        dialog?.addEventListener(
            'click',
            event => {
                if (event.target === dialog) {
                    requestClose(dialog);
                }
            }
        );
    });

    document
        .querySelectorAll(
            '[data-close-dialog]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    requestClose(
                        document.getElementById(
                            button.dataset.closeDialog
                        )
                    );
                }
            );
        });

    const openForm = () => {
        openManagedDialog(formDialog);
    };

    document
        .querySelectorAll(
            '#open-expense-form, [data-open-expense]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                openForm
            );
        });

    /* =====================================================
       PLAIN INPUT VISUAL
       ===================================================== */

    const syncPlainInput = input => {
        input
            .closest('.expense-input')
            ?.classList.toggle(
                'filled',
                Boolean(
                    String(
                        input.value
                        || ''
                    ).trim()
                )
            );
    };

    form
        .querySelectorAll(
            '.expense-input input, .expense-input textarea'
        )
        .forEach(input => {
            syncPlainInput(input);

            input.addEventListener(
                'input',
                () => syncPlainInput(input)
            );
        });

    /* =====================================================
       CUSTOM SELECTS
       ===================================================== */

    const selectedOption = select => {
        if (!select) {
            return null;
        }

        return select.options[
            select.selectedIndex
        ] || null;
    };

    const optionPrimary = option => (
        option?.dataset.primary
        || option?.textContent
        || ''
    ).trim();

    const optionSecondary = option => (
        option?.dataset.secondary
        || ''
    ).trim();

    const updateSelectTrigger = select => {
        const trigger =
            selectTriggers.get(select);

        if (!trigger) {
            return;
        }

        const option =
            selectedOption(select);

        const value =
            optionPrimary(option)
            || 'Escolher';

        const meta =
            optionSecondary(option);

        const valueNode =
            trigger.querySelector(
                '.expense-trigger-value'
            );

        const metaNode =
            trigger.querySelector(
                '.expense-trigger-meta'
            );

        valueNode.textContent =
            value;

        valueNode.classList.toggle(
            'placeholder',
            !select.value
        );

        metaNode.textContent =
            meta;

        metaNode.hidden =
            !meta;

        trigger.classList.toggle(
            'filled',
            Boolean(select.value)
        );
    };

    const renderOptions = (
        select,
        query = ''
    ) => {
        const normalized =
            query
                .trim()
                .toLocaleLowerCase('pt-BR');

        const options = [
            ...select.options,
        ].filter(option => {
            if (!normalized) {
                return true;
            }

            const haystack = (
                option.dataset.search
                || [
                    optionPrimary(option),
                    optionSecondary(option),
                    option.textContent,
                ].join(' ')
            ).toLocaleLowerCase('pt-BR');

            return haystack.includes(
                normalized
            );
        });

        pickerOptions.innerHTML = '';

        if (!options.length) {
            pickerOptions.innerHTML = `
                <div class="expense-picker-empty">
                    Nenhum resultado.
                </div>
            `;
            return;
        }

        options.forEach(option => {
            const button =
                document.createElement('button');

            button.type = 'button';

            button.className =
                'expense-picker-option';

            if (option.selected) {
                button.classList.add(
                    'selected'
                );
            }

            const primary =
                optionPrimary(option);

            const secondary =
                optionSecondary(option);

            const icon =
                option.dataset.optionIcon
                || select.dataset.pickerIcon
                || 'ph-list';

            button.innerHTML = `
                <span class="expense-picker-option-icon">
                    <i class="ph-fill ${escapeHtml(icon)}"></i>
                </span>

                <span class="expense-picker-option-copy">
                    <strong>${escapeHtml(primary)}</strong>
                    ${
                        secondary
                            ? `<small>${escapeHtml(secondary)}</small>`
                            : ''
                    }
                </span>

                <span class="expense-picker-check">
                    ${
                        option.selected
                            ? '<i class="ph-fill ph-check-circle"></i>'
                            : ''
                    }
                </span>
            `;

            button.addEventListener(
                'click',
                () => {
                    select.value =
                        option.value;

                    select.dispatchEvent(
                        new Event(
                            'change',
                            {
                                bubbles: true,
                            }
                        )
                    );

                    updateSelectTrigger(select);
                    requestClose(pickerDialog);
                }
            );

            pickerOptions.appendChild(
                button
            );
        });
    };

    const openSelect = select => {
        activeSelect = select;

        pickerTitle.textContent =
            select.dataset.pickerTitle
            || 'Escolher';

        pickerSearch.value = '';

        renderOptions(select);

        openManagedDialog(pickerDialog);

        if (
            window.matchMedia(
                '(min-width: 621px)'
            ).matches
        ) {
            window.setTimeout(
                () => pickerSearch.focus(),
                40
            );
        }
    };

    const enhanceSelect = select => {
        select.classList.add(
            'expense-native-select'
        );

        const trigger =
            document.createElement('button');

        trigger.type = 'button';

        trigger.className =
            'expense-select-trigger';

        trigger.dataset.tone =
            select.dataset.pickerTone
            || 'blue';

        const icon =
            select.dataset.pickerIcon
            || 'ph-list';

        trigger.innerHTML = `
            <span class="expense-trigger-icon">
                <i class="ph-fill ${escapeHtml(icon)}"></i>
            </span>

            <span class="expense-trigger-copy">
                <span class="expense-trigger-value"></span>
                <span
                    class="expense-trigger-meta"
                    hidden
                ></span>
            </span>

            <span class="expense-trigger-caret">
                <i class="ph-fill ph-caret-down"></i>
            </span>
        `;

        select.insertAdjacentElement(
            'afterend',
            trigger
        );

        selectTriggers.set(
            select,
            trigger
        );

        trigger.addEventListener(
            'click',
            () => openSelect(select)
        );

        select.addEventListener(
            'change',
            () => updateSelectTrigger(select)
        );

        updateSelectTrigger(select);
    };

    form
        .querySelectorAll(
            'select[data-custom-select]'
        )
        .forEach(
            enhanceSelect
        );

    pickerSearch?.addEventListener(
        'input',
        () => {
            if (activeSelect) {
                renderOptions(
                    activeSelect,
                    pickerSearch.value
                );
            }
        }
    );

    /* =====================================================
       CUSTOM CALENDAR
       ===================================================== */

    const updateDateTrigger = input => {
        const trigger =
            form.querySelector(
                `[data-date-target="${input.id}"]`
            );

        if (!trigger) {
            return;
        }

        const value =
            trigger.querySelector(
                '.expense-trigger-value'
            );

        value.textContent =
            formatDate(input.value);

        value.classList.toggle(
            'placeholder',
            !input.value
        );

        trigger.classList.toggle(
            'filled',
            Boolean(input.value)
        );

        trigger.classList.remove(
            'invalid'
        );

        trigger
            .parentElement
            ?.querySelector(
                '.expense-field-error'
            )
            ?.remove();
    };

    const renderCalendar = () => {
        const year =
            calendarView.getFullYear();

        const month =
            calendarView.getMonth();

        calendarMonth.textContent =
            new Intl.DateTimeFormat(
                'pt-BR',
                {
                    month: 'long',
                    year: 'numeric',
                }
            ).format(
                new Date(
                    year,
                    month,
                    1
                )
            );

        const first =
            new Date(
                year,
                month,
                1
            );

        const start =
            new Date(
                year,
                month,
                1 - first.getDay()
            );

        const today =
            new Date();

        const todayValue =
            dateOnlyToString(
                today.getFullYear(),
                today.getMonth() + 1,
                today.getDate()
            );

        calendarGrid.innerHTML = '';

        for (
            let index = 0;
            index < 42;
            index += 1
        ) {
            const current =
                new Date(
                    start.getFullYear(),
                    start.getMonth(),
                    start.getDate() + index
                );

            const value =
                dateOnlyToString(
                    current.getFullYear(),
                    current.getMonth() + 1,
                    current.getDate()
                );

            const button =
                document.createElement('button');

            button.type = 'button';

            button.className =
                'expense-calendar-day';

            button.textContent =
                String(
                    current.getDate()
                );

            if (
                current.getMonth()
                !== month
            ) {
                button.classList.add(
                    'outside'
                );
            }

            if (
                current.getDay()
                === 0
            ) {
                button.classList.add(
                    'sunday'
                );
            }

            if (
                value === todayValue
            ) {
                button.classList.add(
                    'today'
                );
            }

            if (
                value
                === calendarSelected
            ) {
                button.classList.add(
                    'selected'
                );
            }

            button.addEventListener(
                'click',
                () => {
                    if (!activeDateInput) {
                        return;
                    }

                    activeDateInput.value =
                        value;

                    updateDateTrigger(
                        activeDateInput
                    );

                    requestClose(
                        dateDialog
                    );
                }
            );

            calendarGrid.appendChild(
                button
            );
        }
    };

    const openDatePicker = trigger => {
        activeDateTrigger =
            trigger;

        activeDateInput =
            document.getElementById(
                trigger.dataset.dateTarget
            );

        calendarSelected =
            activeDateInput?.value
            || '';

        const parsed =
            parseDateOnly(
                calendarSelected
            );

        if (parsed) {
            calendarView =
                new Date(
                    parsed.year,
                    parsed.month - 1,
                    1
                );
        } else {
            const now =
                new Date();

            calendarView =
                new Date(
                    now.getFullYear(),
                    now.getMonth(),
                    1
                );
        }

        dateTitle.textContent =
            trigger.dataset.dateTitle
            || 'Escolher data';

        renderCalendar();
        openManagedDialog(dateDialog);
    };

    form
        .querySelectorAll(
            '[data-date-target]'
        )
        .forEach(trigger => {
            trigger.addEventListener(
                'click',
                () => openDatePicker(
                    trigger
                )
            );

            const input =
                document.getElementById(
                    trigger.dataset.dateTarget
                );

            if (input) {
                updateDateTrigger(input);
            }
        });

    calendarPrev?.addEventListener(
        'click',
        () => {
            calendarView =
                new Date(
                    calendarView.getFullYear(),
                    calendarView.getMonth() - 1,
                    1
                );

            renderCalendar();
        }
    );

    calendarNext?.addEventListener(
        'click',
        () => {
            calendarView =
                new Date(
                    calendarView.getFullYear(),
                    calendarView.getMonth() + 1,
                    1
                );

            renderCalendar();
        }
    );

    calendarToday?.addEventListener(
        'click',
        () => {
            if (!activeDateInput) {
                return;
            }

            const now =
                new Date();

            activeDateInput.value =
                dateOnlyToString(
                    now.getFullYear(),
                    now.getMonth() + 1,
                    now.getDate()
                );

            updateDateTrigger(
                activeDateInput
            );

            requestClose(
                dateDialog
            );
        }
    );

    /* =====================================================
       FORM VALIDATION / SUBMIT
       ===================================================== */

    const showDateError = (
        input,
        message
    ) => {
        const trigger =
            form.querySelector(
                `[data-date-target="${input.id}"]`
            );

        if (!trigger) {
            return;
        }

        trigger.classList.add(
            'invalid'
        );

        const error =
            document.createElement(
                'span'
            );

        error.className =
            'expense-field-error';

        error.innerHTML = `
            <i class="ph-fill ph-warning-circle"></i>
            ${escapeHtml(message)}
        `;

        trigger.insertAdjacentElement(
            'afterend',
            error
        );
    };

    form.addEventListener(
        'submit',
        async event => {
            event.preventDefault();
            form.querySelector('.expense-submit-errors')?.remove();
            let invalidDate = null;

            form
                .querySelectorAll(
                    '.expense-native-date[data-required="1"]'
                )
                .forEach(input => {
                    input
                        .parentElement
                        ?.querySelector(
                            '.expense-field-error'
                        )
                        ?.remove();

                    if (!input.value) {
                        showDateError(
                            input,
                            'Escolha a data.'
                        );

                        invalidDate ??=
                            input;
                    }
                });

            if (invalidDate) {
                form.querySelector(
                    `[data-date-target="${invalidDate.id}"]`
                )?.focus();

                return;
            }

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            submitButton.disabled = true;

            submitButton.classList.add(
                'loading'
            );

            submitButton.innerHTML = `
                <i class="ph-fill ph-spinner-gap"></i>
                Registrando…
            `;

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    showSubmitErrors(payload.errors || {
                        form: [payload.message || 'Não foi possível registrar a despesa. Confira os dados e tente novamente.'],
                    });
                    return;
                }

                window.location.assign(payload.url || window.location.href);
            } catch (error) {
                showSubmitErrors({
                    connection: ['Não foi possível enviar agora. Seus dados continuam na tela; verifique a conexão e tente novamente.'],
                });
            } finally {
                submitButton.disabled = false;
                submitButton.classList.remove('loading');
                submitButton.innerHTML = `
                    <i class="ph-fill ph-check-circle"></i>
                    Registrar despesa
                `;
            }
        }
    );

    @if($errors->any())
        openManagedDialog(
            formDialog
        );
    @endif
});
</script>
@endsection
