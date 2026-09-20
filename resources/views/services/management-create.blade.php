@extends('layouts.bento')

@section('title', 'Nova ordem')
@section('page-title', 'Nova ordem')
@section('user-role', 'Workspace')

@php
    $routeTenant = request()->route('tenant');

    $tenantSlug = is_object($routeTenant)
        ? ($routeTenant->slug ?? null)
        : $routeTenant;

    $bentoNavigation = \App\Support\PortalNavigation::make(
        'services',
        'new',
        $tenantSlug
    );

    $scheduledValue = old(
        'scheduled_at',
        now()->format('Y-m-d\TH:i')
    );

    $scheduledDateValue = substr(
        (string) $scheduledValue,
        0,
        10
    );

    $scheduledTimeValue = substr(
        (string) $scheduledValue,
        11,
        5
    );
@endphp

@section('content')

@once
    <link
        rel="stylesheet"
        href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/fill/style.css"
    >
@endonce

<style>
    .svc-create {
        --svc-green: var(--ws-green, #219653);
        --svc-green-soft: #edf8f2;
        --svc-green-border: #cce8d7;

        --svc-blue: var(--ws-blue, #3478d4);
        --svc-blue-soft: #edf4ff;
        --svc-blue-border: #cfe0f7;

        --svc-violet: var(--ws-purple, #8a4bd2);
        --svc-violet-soft: #f5efff;
        --svc-violet-border: #e1d2f4;

        --svc-amber: var(--ws-amber, #c38418);
        --svc-amber-soft: #fff7e8;
        --svc-amber-border: #f0dcae;

        --svc-red: var(--ws-red, #cf5050);
        --svc-red-soft: #fff0f0;
        --svc-red-border: #efcaca;

        --svc-cyan: #168eae;
        --svc-cyan-soft: #ecf8fb;
        --svc-cyan-border: #cae8ef;

        --svc-text: #17211d;
        --svc-text-2: #59655f;
        --svc-muted: #89938e;
        --svc-border: #dde5e0;
        --svc-soft: #f7faf8;

        display: grid;
        grid-column: 1 / -1;
        width: min(100%, 900px);
        margin-inline: auto;
        gap: .72rem;
        min-width: 0;
        color: var(--svc-text);
    }

    .svc-create *,
    .svc-create *::before,
    .svc-create *::after {
        box-sizing: border-box;
    }

    .svc-create a {
        text-decoration: none;
    }

    /* =========================================================
       HEADER — MESMA LINGUAGEM DO DASHBOARD
       ========================================================= */

    .svc-create-head {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: .8rem;
        align-items: center;
        min-width: 0;
        padding: .78rem .86rem;
        border: 1px solid var(--svc-border);
        border-radius: 12px;
        background: #fff;
    }

    .svc-create-head-main {
        display: grid;
        min-width: 0;
        grid-template-columns: 42px minmax(0, 1fr);
        gap: .62rem;
        align-items: center;
    }

    .svc-create-head-icon {
        display: grid;
        width: 42px;
        height: 42px;
        place-items: center;
        border-radius: 10px;
        background: var(--svc-violet-soft);
        color: var(--svc-violet);
        font-size: 1.05rem;
    }

    .svc-create-head-copy {
        min-width: 0;
    }

    .svc-create-head-copy small {
        display: block;
        color: var(--svc-muted);
        font-size: .62rem;
        font-weight: 750;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .svc-create-head-copy h1 {
        margin: .07rem 0 0;
        color: var(--svc-text);
        font-size: clamp(1.02rem, 2vw, 1.24rem);
        font-weight: 860;
        line-height: 1.15;
    }

    .svc-create-head-copy p {
        margin: .14rem 0 0;
        color: var(--svc-text-2);
        font-size: .72rem;
        line-height: 1.4;
    }

    .svc-back {
        display: inline-flex;
        min-height: 38px;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .58rem;
        border: 1px solid var(--svc-border);
        border-radius: 8px;
        background: #fff;
        color: var(--svc-text-2);
        font-size: .72rem;
        font-weight: 760;
        white-space: nowrap;
    }

    /* =========================================================
       ERROS
       ========================================================= */

    .svc-errors {
        display: grid;
        grid-template-columns: 34px minmax(0, 1fr);
        gap: .5rem;
        align-items: start;
        padding: .62rem .68rem;
        border: 1px solid var(--svc-red-border);
        border-radius: 10px;
        background: var(--svc-red-soft);
    }

    .svc-errors-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: #fff;
        color: var(--svc-red);
        font-size: .86rem;
    }

    .svc-errors strong {
        display: block;
        color: var(--svc-red);
        font-size: .76rem;
        font-weight: 820;
    }

    .svc-errors ul {
        margin: .2rem 0 0;
        padding-left: 1rem;
        color: var(--svc-text-2);
        font-size: .69rem;
        line-height: 1.45;
    }

    /* =========================================================
       PROGRESSO
       ========================================================= */

    .svc-steps {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 10px;
        background: #fff;
    }

    .svc-step {
        --tone: var(--svc-muted);
        --soft: var(--svc-soft);

        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: .34rem;
        align-items: center;
        min-height: 48px;
        padding: .44rem .54rem;
        border: 0;
        border-right: 1px solid var(--svc-border);
        background: transparent;
        color: var(--svc-muted);
        cursor: default;
        font: inherit;
        text-align: left;
    }

    .svc-step:last-child {
        border-right: 0;
    }

    .svc-step.current {
        --tone: var(--svc-blue);
        --soft: var(--svc-blue-soft);

        color: var(--svc-text);
        background: #fbfdff;
    }

    .svc-step.done {
        --tone: var(--svc-green);
        --soft: var(--svc-green-soft);

        color: var(--svc-text);
        cursor: pointer;
    }

    .svc-step.done:hover {
        background: #fbfdfc;
    }

    .svc-step-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .73rem;
    }

    .svc-step-copy {
        min-width: 0;
    }

    .svc-step-copy small,
    .svc-step-copy strong {
        display: block;
    }

    .svc-step-copy small {
        color: var(--svc-muted);
        font-size: .5rem;
        font-weight: 720;
    }

    .svc-step-copy strong {
        margin-top: .02rem;
        overflow: hidden;
        color: inherit;
        font-size: .66rem;
        font-weight: 800;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* =========================================================
       FORM / UMA ETAPA POR VEZ
       ========================================================= */

    .svc-form {
        display: block;
        min-width: 0;
        margin: 0;
    }

    .svc-panel {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 12px;
        background: #fff;
    }

    .svc-panel[hidden] {
        display: none !important;
    }

    .svc-panel-head {
        --tone: var(--svc-blue);
        --soft: var(--svc-blue-soft);

        display: grid;
        grid-template-columns: 36px minmax(0, 1fr);
        gap: .55rem;
        align-items: center;
        padding: .7rem .76rem;
        border-bottom: 1px solid var(--svc-border);
    }

    .svc-panel.service .svc-panel-head {
        --tone: var(--svc-violet);
        --soft: var(--svc-violet-soft);
    }

    .svc-panel.person .svc-panel-head {
        --tone: var(--svc-green);
        --soft: var(--svc-green-soft);
    }

    .svc-panel.schedule .svc-panel-head {
        --tone: var(--svc-blue);
        --soft: var(--svc-blue-soft);
    }

    .svc-panel.finish .svc-panel-head {
        --tone: var(--svc-cyan);
        --soft: var(--svc-cyan-soft);
    }

    .svc-panel-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 9px;
        background: var(--soft);
        color: var(--tone);
        font-size: .86rem;
    }

    .svc-panel-title {
        min-width: 0;
    }

    .svc-panel-title h2 {
        margin: 0;
        color: var(--svc-text);
        font-size: .9rem;
        font-weight: 840;
    }

    .svc-panel-title p {
        margin: .06rem 0 0;
        color: var(--svc-muted);
        font-size: .68rem;
        line-height: 1.35;
    }

    .svc-panel-body {
        display: grid;
        min-width: 0;
        gap: .9rem;
        padding: .9rem .8rem 1rem;
    }

    .svc-fields {
        display: grid;
        min-width: 0;
        gap: .82rem;
    }

    .svc-field {
        display: grid;
        min-width: 0;
        gap: .32rem;
    }

    .svc-label {
        display: flex;
        min-width: 0;
        gap: .28rem;
        align-items: center;
        color: var(--svc-text);
        font-size: .75rem;
        font-weight: 760;
        line-height: 1.35;
    }

    .svc-label i {
        flex: 0 0 auto;
        color: var(--svc-muted);
        font-size: .76rem;
    }

    .svc-note {
        color: var(--svc-muted);
        font-size: .62rem;
        font-weight: 650;
    }

    .svc-note.required {
        color: var(--svc-amber);
    }

    .svc-help {
        color: var(--svc-muted);
        font-size: .64rem;
        line-height: 1.4;
    }

    /* =========================================================
       INPUT NORMAL
       ========================================================= */

    .svc-input {
        display: grid;
        min-width: 0;
        grid-template-columns: 38px minmax(0, 1fr);
        gap: .2rem;
        align-items: center;
        min-height: 48px;
        padding: .28rem .38rem .28rem .3rem;
        border: 1px solid transparent;
        border-radius: 9px;
        background: #f3f6f4;
    }

    .svc-input:focus-within {
        border-color: var(--svc-blue);
        background: #fff;
        box-shadow: 0 0 0 3px var(--svc-blue-soft);
    }

    .svc-input.filled {
        border-color: #e2e8e4;
        background: #fff;
    }

    .svc-input-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 7px;
        background: var(--svc-blue-soft);
        color: var(--svc-blue);
        font-size: .8rem;
    }

    .svc-input.person .svc-input-icon {
        background: var(--svc-green-soft);
        color: var(--svc-green);
    }

    .svc-input.location .svc-input-icon {
        background: var(--svc-red-soft);
        color: var(--svc-red);
    }

    .svc-input input,
    .svc-input textarea {
        width: 100%;
        min-width: 0;
        min-height: 38px;
        padding: .44rem .48rem;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--svc-text);
        font: inherit;
        font-size: .78rem;
    }

    .svc-input textarea {
        min-height: 92px;
        resize: vertical;
    }

    /* =========================================================
       SELECT CUSTOM
       ========================================================= */

    .svc-native-select,
    .svc-native-temporal {
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

    .svc-select-trigger,
    .svc-temporal-trigger {
        --tone: var(--svc-blue);
        --soft: var(--svc-blue-soft);

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
        color: var(--svc-text);
        cursor: pointer;
        font: inherit;
        text-align: left;
    }

    .svc-select-trigger[data-tone="violet"] {
        --tone: var(--svc-violet);
        --soft: var(--svc-violet-soft);
    }

    .svc-select-trigger[data-tone="green"] {
        --tone: var(--svc-green);
        --soft: var(--svc-green-soft);
    }

    .svc-select-trigger.filled,
    .svc-temporal-trigger.filled {
        border-color: #e2e8e4;
        background: #fff;
    }

    .svc-select-trigger.invalid,
    .svc-temporal-trigger.invalid {
        border-color: var(--svc-red);
        background: var(--svc-red-soft);
    }

    .svc-select-trigger:focus-visible,
    .svc-temporal-trigger:focus-visible {
        outline: 2px solid var(--tone);
        outline-offset: 2px;
    }

    .svc-trigger-icon {
        display: grid;
        width: 32px;
        height: 32px;
        place-items: center;
        border-radius: 7px;
        background: var(--soft);
        color: var(--tone);
        font-size: .8rem;
    }

    .svc-trigger-copy {
        min-width: 0;
    }

    .svc-trigger-value,
    .svc-trigger-meta {
        display: block;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .svc-trigger-value {
        color: var(--svc-text);
        font-size: .78rem;
        font-weight: 750;
    }

    .svc-trigger-value.placeholder {
        color: var(--svc-muted);
        font-weight: 650;
    }

    .svc-trigger-meta {
        margin-top: .03rem;
        color: var(--svc-muted);
        font-size: .62rem;
    }

    .svc-trigger-caret {
        display: grid;
        width: 26px;
        height: 26px;
        place-items: center;
        color: var(--svc-muted);
        font-size: .7rem;
    }

    .svc-custom-error {
        display: inline-flex;
        gap: .24rem;
        align-items: center;
        color: var(--svc-red);
        font-size: .64rem;
        font-weight: 720;
    }

    /* =========================================================
       DATA / HORÁRIO
       ========================================================= */

    .svc-when {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .62rem;
    }

    .svc-temporal-trigger.date {
        --tone: var(--svc-blue);
        --soft: var(--svc-blue-soft);
    }

    .svc-temporal-trigger.time {
        --tone: var(--svc-cyan);
        --soft: var(--svc-cyan-soft);
    }

    /* =========================================================
       FINAL / RESUMO
       ========================================================= */

    .svc-dynamic-fields {
        display: grid;
        min-width: 0;
        gap: .8rem;
    }

    .svc-dynamic-fields > label,
    .svc-dynamic-fields > div {
        display: grid;
        min-width: 0;
        gap: .32rem;
    }

    .svc-dynamic-fields label {
        color: var(--svc-text);
        font-size: .75rem;
        font-weight: 750;
        line-height: 1.4;
    }

    .svc-dynamic-fields input:not([type="checkbox"]):not([type="radio"]),
    .svc-dynamic-fields textarea {
        width: 100%;
        min-width: 0;
        min-height: 46px;
        padding: .52rem .6rem;
        border: 1px solid transparent;
        border-radius: 9px;
        outline: 0;
        background: #f3f6f4;
        color: var(--svc-text);
        font: inherit;
        font-size: .78rem;
    }

    .svc-dynamic-fields input:focus,
    .svc-dynamic-fields textarea:focus {
        border-color: var(--svc-blue);
        background: #fff;
        box-shadow: 0 0 0 3px var(--svc-blue-soft);
    }

    .svc-dynamic-fields small {
        color: var(--svc-muted);
        font-size: .64rem;
        line-height: 1.4;
    }

    .svc-dynamic-fields input[type="checkbox"],
    .svc-dynamic-fields input[type="radio"] {
        width: 18px;
        height: 18px;
        accent-color: var(--svc-green);
    }

    [data-version-fields][hidden] {
        display: none !important;
    }

    .svc-review {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 9px;
        background: #fff;
    }

    .svc-review-item {
        display: grid;
        min-width: 0;
        grid-template-columns: 30px minmax(0, 1fr);
        gap: .4rem;
        align-items: center;
        padding: .52rem .58rem;
    }

    .svc-review-item:nth-child(odd) {
        border-right: 1px solid var(--svc-border);
    }

    .svc-review-item:nth-child(n + 3) {
        border-top: 1px solid var(--svc-border);
    }

    .svc-review-icon {
        display: grid;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 7px;
        background: var(--svc-soft);
        color: var(--svc-text-2);
        font-size: .72rem;
    }

    .svc-review-copy {
        min-width: 0;
    }

    .svc-review-copy small,
    .svc-review-copy strong {
        display: block;
    }

    .svc-review-copy small {
        color: var(--svc-muted);
        font-size: .52rem;
        font-weight: 720;
    }

    .svc-review-copy strong {
        margin-top: .03rem;
        overflow: hidden;
        color: var(--svc-text);
        font-size: .68rem;
        font-weight: 780;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* =========================================================
       NAVEGAÇÃO DO WIZARD
       ========================================================= */

    .svc-panel-actions {
        display: flex;
        gap: .48rem;
        align-items: center;
        justify-content: flex-end;
        padding: .65rem .78rem;
        border-top: 1px solid var(--svc-border);
        background: #fbfdfc;
    }

    .svc-action {
        display: inline-flex;
        min-height: 39px;
        gap: .3rem;
        align-items: center;
        justify-content: center;
        padding: .4rem .62rem;
        border: 1px solid var(--svc-border);
        border-radius: 8px;
        background: #fff;
        color: var(--svc-text-2);
        cursor: pointer;
        font: inherit;
        font-size: .72rem;
        font-weight: 780;
    }

    .svc-action.primary {
        border-color: var(--svc-blue);
        background: var(--svc-blue);
        color: #fff;
    }

    .svc-action.create {
        border-color: var(--svc-green);
        background: var(--svc-green);
        color: #fff;
    }

    .svc-action[disabled] {
        cursor: wait;
        opacity: .72;
    }

    @keyframes svc-spin {
        to {
            transform: rotate(360deg);
        }
    }

    .svc-action.loading i {
        animation: svc-spin .8s linear infinite;
    }

    /* =========================================================
       DIALOGS
       ========================================================= */

    .svc-dialog {
        width: min(94vw, 540px);
        max-width: 540px;
        max-height: min(84dvh, 700px);
        margin: auto;
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 14px;
        background: #fff;
        color: var(--svc-text);
    }

    .svc-dialog::backdrop {
        background: rgba(12, 24, 16, .58);
    }

    .svc-dialog-layout {
        display: grid;
        max-height: min(84dvh, 700px);
        grid-template-rows: auto minmax(0, 1fr) auto;
    }

    .svc-dialog-head {
        display: flex;
        min-width: 0;
        gap: .65rem;
        align-items: center;
        justify-content: space-between;
        padding: .72rem .78rem;
        border-bottom: 1px solid var(--svc-border);
    }

    .svc-dialog-head-copy {
        min-width: 0;
    }

    .svc-dialog-head-copy small {
        display: block;
        color: var(--svc-muted);
        font-size: .61rem;
        font-weight: 720;
    }

    .svc-dialog-head-copy strong {
        display: block;
        margin-top: .04rem;
        color: var(--svc-text);
        font-size: .86rem;
        font-weight: 830;
    }

    .svc-dialog-close {
        display: grid;
        width: 38px;
        height: 38px;
        flex: 0 0 auto;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: var(--svc-soft);
        color: var(--svc-text-2);
        cursor: pointer;
        font-size: .88rem;
    }

    .svc-picker-body {
        display: grid;
        min-height: 0;
        grid-template-rows: auto minmax(0, 1fr);
    }

    .svc-picker-search {
        display: grid;
        grid-template-columns: 38px minmax(0, 1fr);
        align-items: center;
        margin: .68rem .72rem .4rem;
        overflow: hidden;
        border: 1px solid var(--svc-border);
        border-radius: 9px;
        background: var(--svc-soft);
    }

    .svc-picker-search i {
        display: grid;
        width: 38px;
        place-items: center;
        color: var(--svc-muted);
        font-size: .8rem;
    }

    .svc-picker-search input {
        min-width: 0;
        min-height: 42px;
        padding: .48rem .54rem;
        border: 0;
        outline: 0;
        background: transparent;
        color: var(--svc-text);
        font: inherit;
        font-size: .76rem;
    }

    .svc-picker-options {
        display: grid;
        min-height: 0;
        align-content: start;
        overflow-y: auto;
        padding: 0 .72rem .72rem;
    }

    .svc-picker-option {
        display: grid;
        width: 100%;
        min-width: 0;
        grid-template-columns: 36px minmax(0, 1fr) 25px;
        gap: .45rem;
        align-items: center;
        min-height: 50px;
        padding: .45rem .08rem;
        border: 0;
        border-bottom: 1px solid var(--svc-border);
        background: transparent;
        color: var(--svc-text);
        cursor: pointer;
        font: inherit;
        text-align: left;
    }

    .svc-picker-option:last-child {
        border-bottom: 0;
    }

    .svc-picker-option.selected {
        background: var(--svc-green-soft);
    }

    .svc-picker-option-icon {
        display: grid;
        width: 34px;
        height: 34px;
        place-items: center;
        border-radius: 8px;
        background: var(--svc-blue-soft);
        color: var(--svc-blue);
        font-size: .8rem;
    }

    .svc-picker-option-copy {
        min-width: 0;
    }

    .svc-picker-option-copy strong,
    .svc-picker-option-copy small {
        display: block;
        min-width: 0;
    }

    .svc-picker-option-copy strong {
        color: var(--svc-text);
        font-size: .76rem;
        font-weight: 780;
    }

    .svc-picker-option-copy small {
        margin-top: .04rem;
        color: var(--svc-muted);
        font-size: .63rem;
        line-height: 1.35;
    }

    .svc-picker-option-check {
        color: var(--svc-green);
        font-size: .82rem;
    }

    .svc-picker-empty {
        padding: 1.2rem .5rem;
        color: var(--svc-muted);
        font-size: .7rem;
        text-align: center;
    }

    /* Calendar */
    .svc-calendar-body {
        padding: .72rem;
        overflow-y: auto;
    }

    .svc-calendar-nav {
        display: grid;
        grid-template-columns: 38px minmax(0, 1fr) 38px;
        gap: .4rem;
        align-items: center;
        margin-bottom: .62rem;
    }

    .svc-calendar-nav button {
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: var(--svc-soft);
        color: var(--svc-text-2);
        cursor: pointer;
        font-size: .8rem;
    }

    .svc-calendar-month {
        color: var(--svc-text);
        font-size: .8rem;
        font-weight: 800;
        text-align: center;
    }

    .svc-calendar-week,
    .svc-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: .27rem;
    }

    .svc-calendar-week {
        margin-bottom: .3rem;
    }

    .svc-calendar-week span {
        color: var(--svc-muted);
        font-size: .6rem;
        font-weight: 720;
        text-align: center;
    }

    .svc-calendar-week span:first-child {
        color: #a45b5b;
    }

    .svc-calendar-day {
        display: grid;
        aspect-ratio: 1;
        min-width: 0;
        place-items: center;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--svc-text);
        cursor: pointer;
        font: inherit;
        font-size: .72rem;
        font-weight: 700;
    }

    .svc-calendar-day.sunday:not(.selected) {
        background: #fbf2f2;
        color: #9f5454;
    }

    .svc-calendar-day.sunday.outside:not(.selected) {
        background: transparent;
        color: #c59a9a;
    }

    .svc-calendar-day.today:not(.selected) {
        background: var(--svc-green-soft);
        color: #1d7248;
        box-shadow: inset 0 0 0 1px var(--svc-green-border);
        font-weight: 840;
    }

    .svc-calendar-day.selected {
        background: var(--svc-blue);
        color: #fff;
        box-shadow: none;
    }

    .svc-calendar-day.outside:not(.selected):not(.sunday) {
        color: #bcc4c0;
    }

    .svc-dialog-foot {
        display: flex;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
        padding: .62rem .72rem;
        border-top: 1px solid var(--svc-border);
    }

    .svc-dialog-button {
        display: inline-flex;
        min-height: 38px;
        align-items: center;
        justify-content: center;
        padding: .38rem .6rem;
        border: 0;
        border-radius: 8px;
        background: var(--svc-soft);
        color: var(--svc-text-2);
        cursor: pointer;
        font: inherit;
        font-size: .7rem;
        font-weight: 760;
    }

    .svc-dialog-button.primary {
        background: var(--svc-green);
        color: #fff;
    }

    /* Time */
    .svc-time-body {
        overflow: hidden;
        padding: .72rem;
    }

    .svc-time-preview {
        display: grid;
        place-items: center;
        margin-bottom: .68rem;
        padding: .56rem;
        border-radius: 9px;
        background: var(--svc-cyan-soft);
        color: #126f88;
        font-size: 1.06rem;
        font-weight: 850;
        font-variant-numeric: tabular-nums;
    }

    .svc-time-columns {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .68rem;
    }

    .svc-time-column {
        display: grid;
        min-width: 0;
        gap: .3rem;
    }

    .svc-time-column-title {
        color: var(--svc-muted);
        font-size: .64rem;
        font-weight: 750;
        text-align: center;
    }

    .svc-time-wheel {
        height: 240px;
        overflow-y: auto;
        padding: .26rem;
        border-radius: 10px;
        background: #f4f7f5;
        scroll-behavior: smooth;
        scroll-snap-type: y proximity;
        overscroll-behavior: contain;
    }

    .svc-time-option {
        width: 100%;
        min-height: 43px;
        margin: 0 0 .16rem;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--svc-text-2);
        cursor: pointer;
        scroll-snap-align: center;
        font: inherit;
        font-size: .76rem;
        font-weight: 720;
        font-variant-numeric: tabular-nums;
    }

    .svc-time-option.selected {
        background: var(--svc-blue-soft);
        color: #255fae;
        box-shadow: inset 0 0 0 1px var(--svc-blue-border);
        font-weight: 840;
    }

    /* =========================================================
       RESPONSIVO
       ========================================================= */

    @media (max-width: 700px) {
        .svc-create {
            gap: .58rem;
        }

        .svc-create-head {
            grid-template-columns: 1fr auto;
            padding: .64rem .68rem;
        }

        .svc-create-head-main {
            grid-template-columns: 36px minmax(0, 1fr);
        }

        .svc-create-head-icon {
            width: 36px;
            height: 36px;
        }

        .svc-create-head-copy p {
            display: none;
        }

        .svc-back {
            width: 38px;
            padding: 0;
        }

        .svc-back span {
            display: none;
        }

        .svc-steps {
            grid-template-columns: repeat(4, 1fr);
        }

        .svc-step {
            grid-template-columns: 1fr;
            justify-items: center;
            gap: .12rem;
            padding: .36rem .14rem;
            text-align: center;
        }

        .svc-step-copy small {
            display: none;
        }

        .svc-step-copy strong {
            max-width: 100%;
            font-size: .56rem;
        }

        .svc-panel-body {
            padding: .8rem .68rem .9rem;
        }

        .svc-when {
            grid-template-columns: 1fr;
        }

        .svc-panel-actions {
            position: sticky;
            z-index: 20;
            bottom: 0;
            padding:
                .56rem
                .68rem
                max(.56rem, env(safe-area-inset-bottom));
        }

        .svc-panel-actions .svc-action {
            min-height: 43px;
        }

        .svc-panel-actions .svc-action.primary,
        .svc-panel-actions .svc-action.create {
            flex: 1 1 auto;
        }

        .svc-review {
            grid-template-columns: 1fr;
        }

        .svc-review-item:nth-child(odd) {
            border-right: 0;
        }

        .svc-review-item:nth-child(n + 2) {
            border-top: 1px solid var(--svc-border);
        }

        .svc-input input,
        .svc-input textarea,
        .svc-dynamic-fields input:not([type="checkbox"]):not([type="radio"]),
        .svc-dynamic-fields textarea,
        .svc-picker-search input {
            font-size: 16px;
        }

        .svc-dialog {
            width: calc(100vw - 1rem);
            max-height: calc(100dvh - 1rem);
        }

        .svc-dialog-layout {
            max-height: calc(100dvh - 1rem);
        }

        .svc-picker-option {
            min-height: 56px;
        }

        .svc-time-wheel {
            height: min(43dvh, 270px);
        }
    }

    /* =========================================================
       CORES DOS MODAIS
       Os dialogs ficam fora de .svc-create no DOM, então
       precisam receber os mesmos tokens de cor.
       ========================================================= */

    .svc-dialog {
        --svc-green: var(--ws-green, #219653);
        --svc-green-soft: #edf8f2;
        --svc-green-border: #cce8d7;

        --svc-blue: var(--ws-blue, #3478d4);
        --svc-blue-soft: #edf4ff;
        --svc-blue-border: #cfe0f7;

        --svc-violet: var(--ws-purple, #8a4bd2);
        --svc-violet-soft: #f5efff;
        --svc-violet-border: #e1d2f4;

        --svc-amber: var(--ws-amber, #c38418);
        --svc-amber-soft: #fff7e8;
        --svc-amber-border: #f0dcae;

        --svc-red: var(--ws-red, #cf5050);
        --svc-red-soft: #fff0f0;
        --svc-red-border: #efcaca;

        --svc-cyan: #168eae;
        --svc-cyan-soft: #ecf8fb;
        --svc-cyan-border: #cae8ef;

        --svc-text: #17211d;
        --svc-text-2: #59655f;
        --svc-muted: #89938e;
        --svc-border: #dde5e0;
        --svc-soft: #f7faf8;
    }

    .svc-dialog-button.primary {
        border: 1px solid var(--svc-green);
        background: var(--svc-green);
        color: #fff;
    }

    #svc-calendar-today {
        border: 1px solid var(--svc-green-border);
        background: var(--svc-green-soft);
        color: #1f754b;
    }

    #svc-calendar-today:hover {
        border-color: var(--svc-green);
    }

    .svc-calendar-day.today:not(.selected) {
        background: var(--svc-green-soft);
        color: #1f754b;
        box-shadow: inset 0 0 0 1px var(--svc-green-border);
    }

    .svc-calendar-day.today.sunday:not(.selected) {
        background: var(--svc-green-soft);
        color: #1f754b;
        box-shadow: inset 0 0 0 1px var(--svc-green-border);
    }


    /* Gestão: pequenas diferenças sem sair do padrão do prestador. */
    .svc-panel.service .svc-help {
        margin-top: -.02rem;
    }

    @media (min-width: 701px) {
        .svc-panel.service .svc-fields {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

</style>

<div class="svc-create">
    <header class="svc-create-head">
        <div class="svc-create-head-main">
            <span
                class="svc-create-head-icon"
                aria-hidden="true"
            >
                <i class="ph-fill ph-plus-circle"></i>
            </span>

            <div class="svc-create-head-copy">
                <small>Serviços</small>
                <h1>Nova ordem</h1>
                <p>Preencha uma etapa por vez.</p>
            </div>
        </div>

        <a
            class="svc-back"
            href="#"
            onclick="event.preventDefault(); history.back();"
            aria-label="Voltar"
        >
            <i class="ph-fill ph-arrow-left"></i>
            <span>Voltar</span>
        </a>
    </header>

    @if($errors->any())
        <section class="svc-errors" role="alert">
            <span
                class="svc-errors-icon"
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

    <nav
        class="svc-steps"
        aria-label="Etapas da ordem"
    >
        <button
            class="svc-step current"
            type="button"
            data-step-button="1"
            aria-current="step"
        >
            <span class="svc-step-icon">
                <i class="ph-fill ph-wrench"></i>
            </span>

            <span class="svc-step-copy">
                <small>1 de 4</small>
                <strong>Serviço</strong>
            </span>
        </button>

        <button
            class="svc-step"
            type="button"
            data-step-button="2"
        >
            <span class="svc-step-icon">
                <i class="ph-fill ph-user-circle"></i>
            </span>

            <span class="svc-step-copy">
                <small>2 de 4</small>
                <strong>Pessoa</strong>
            </span>
        </button>

        <button
            class="svc-step"
            type="button"
            data-step-button="3"
        >
            <span class="svc-step-icon">
                <i class="ph-fill ph-calendar-check"></i>
            </span>

            <span class="svc-step-copy">
                <small>3 de 4</small>
                <strong>Agendamento</strong>
            </span>
        </button>

        <button
            class="svc-step"
            type="button"
            data-step-button="4"
        >
            <span class="svc-step-icon">
                <i class="ph-fill ph-check-circle"></i>
            </span>

            <span class="svc-step-copy">
                <small>4 de 4</small>
                <strong>Finalizar</strong>
            </span>
        </button>
    </nav>

    <form
        id="service-order-create-form"
        class="svc-form"
        method="post"
        action="{{ route('services.management.store', $tenantSlug) }}"
        novalidate
    >
        @csrf

        <section
            class="svc-panel service"
            data-wizard-step="1"
        >
            <header class="svc-panel-head">
                <span
                    class="svc-panel-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-wrench"></i>
                </span>

                <div class="svc-panel-title">
                    <h2>Serviço</h2>
                    <p>Escolha o serviço e o prestador.</p>
                </div>
            </header>

            <div class="svc-panel-body">
                <div class="svc-fields">
                    <label class="svc-field">
                        <span class="svc-label">
                            <i class="ph-fill ph-briefcase"></i>
                            Serviço
                        </span>

                        <select
                            id="service-version"
                            name="service_version_id"
                            required
                            data-custom-select
                            data-picker-title="Escolher serviço"
                            data-picker-icon="ph-briefcase"
                            data-picker-tone="violet"
                        >
                            <option value="">Escolher serviço</option>

                            @foreach($versions as $version)
                                <option
                                    value="{{ $version->id }}"
                                    data-service="{{ $version->service_id }}"
                                    data-members-only="{{ $version->members_only ? '1' : '0' }}"
                                    data-primary="{{ $version->service->name }}"
                                    data-secondary="Versão {{ $version->version }}{{ $version->members_only ? ' · somente membros' : '' }}"
                                    data-search="{{ $version->service->name }} versão {{ $version->version }}"
                                    data-option-icon="ph-wrench"
                                    @selected(old('service_version_id') == $version->id)
                                >
                                    {{ $version->service->name }} — versão {{ $version->version }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="svc-field">
                        <span class="svc-label">
                            <i class="ph-fill ph-user-gear"></i>
                            Prestador
                            <span class="svc-note">opcional</span>
                        </span>

                        <select
                            id="service-provider"
                            name="service_provider_id"
                            data-custom-select
                            data-picker-title="Escolher prestador"
                            data-picker-icon="ph-user-gear"
                            data-picker-tone="violet"
                        >
                            <option
                                value=""
                                data-primary="Sem prestador"
                                data-secondary="Definir depois"
                                data-option-icon="ph-user-minus"
                            >
                                Sem prestador
                            </option>

                            @foreach($providers as $provider)
                                <option
                                    value="{{ $provider->id }}"
                                    data-services="{{ $provider->services->pluck('id')->join(',') }}"
                                    data-primary="{{ $provider->name }}"
                                    data-search="{{ $provider->name }}"
                                    data-option-icon="ph-user-gear"
                                    @selected(old('service_provider_id') == $provider->id)
                                >
                                    {{ $provider->name }}
                                </option>
                            @endforeach
                        </select>

                        <small class="svc-help">
                            Mostramos somente prestadores habilitados para o serviço.
                        </small>
                    </label>
                </div>
            </div>

            <footer class="svc-panel-actions">
                <button
                    class="svc-action primary"
                    type="button"
                    data-next-step
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </footer>
        </section>

        <section
            class="svc-panel person"
            data-wizard-step="2"
            hidden
        >
            <header class="svc-panel-head">
                <span
                    class="svc-panel-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-user-circle"></i>
                </span>

                <div class="svc-panel-title">
                    <h2>Pessoa</h2>
                    <p>Quem receberá o serviço?</p>
                </div>
            </header>

            <div class="svc-panel-body">
                <div class="svc-fields">
                    <label
                        id="member-field"
                        class="svc-field"
                    >
                        <span class="svc-label">
                            <i class="ph-fill ph-users-three"></i>
                            Membro
                            <span
                                id="member-note"
                                class="svc-note"
                            >
                                opcional
                            </span>
                        </span>

                        <select
                            id="associate-select"
                            name="associate_id"
                            data-custom-select
                            data-picker-title="Escolher membro"
                            data-picker-icon="ph-users-three"
                            data-picker-tone="green"
                        >
                            <option value="">Sem membro</option>

                            @foreach($associates as $associate)
                                <option
                                    value="{{ $associate->id }}"
                                    data-name="{{ $associate->nickname ?: $associate->display_name }}"
                                    data-primary="{{ $associate->nickname ?: $associate->display_name }}"
                                    data-secondary="{{ $associate->nickname && $associate->nickname !== $associate->display_name ? $associate->display_name : '' }}"
                                    data-search="{{ $associate->nickname ?: '' }} {{ $associate->display_name }}"
                                    data-option-icon="ph-user-circle"
                                    @selected(old('associate_id') == $associate->id)
                                >
                                    {{ $associate->nickname ?: $associate->display_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="svc-field">
                        <span class="svc-label">
                            <i class="ph-fill ph-identification-card"></i>
                            Beneficiário
                        </span>

                        <div class="svc-input person">
                            <span
                                class="svc-input-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-identification-card"></i>
                            </span>

                            <input
                                id="beneficiary-name"
                                name="beneficiary_name"
                                value="{{ old('beneficiary_name') }}"
                                maxlength="191"
                                autocomplete="name"
                                placeholder="Nome ou apelido"
                            >
                        </div>

                        <small
                            id="beneficiary-note"
                            class="svc-help"
                        >
                            Preencha somente se não escolher um membro.
                        </small>
                    </label>
                </div>
            </div>

            <footer class="svc-panel-actions">
                <button
                    class="svc-action"
                    type="button"
                    data-prev-step
                >
                    <i class="ph-fill ph-arrow-left"></i>
                    Voltar
                </button>

                <button
                    class="svc-action primary"
                    type="button"
                    data-next-step
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </footer>
        </section>

        <section
            class="svc-panel schedule"
            data-wizard-step="3"
            hidden
        >
            <header class="svc-panel-head">
                <span
                    class="svc-panel-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-calendar-check"></i>
                </span>

                <div class="svc-panel-title">
                    <h2>Agendamento</h2>
                    <p>Dia, horário e local.</p>
                </div>
            </header>

            <div class="svc-panel-body">
                <input
                    id="scheduled-at"
                    type="hidden"
                    name="scheduled_at"
                    value="{{ $scheduledValue }}"
                >

                <input
                    id="scheduled-date-value"
                    type="hidden"
                    value="{{ $scheduledDateValue }}"
                >

                <input
                    id="scheduled-time-value"
                    type="hidden"
                    value="{{ $scheduledTimeValue }}"
                >

                <div class="svc-when">
                    <div class="svc-field">
                        <span class="svc-label">
                            <i class="ph-fill ph-calendar-blank"></i>
                            Dia
                        </span>

                        <button
                            id="scheduled-date-trigger"
                            class="svc-temporal-trigger date"
                            type="button"
                        >
                            <span
                                class="svc-trigger-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-calendar-blank"></i>
                            </span>

                            <span class="svc-trigger-copy">
                                <span
                                    id="scheduled-date-text"
                                    class="svc-trigger-value"
                                >
                                    Escolher dia
                                </span>

                                <span
                                    id="scheduled-date-meta"
                                    class="svc-trigger-meta"
                                    hidden
                                ></span>
                            </span>

                            <span
                                class="svc-trigger-caret"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-caret-down"></i>
                            </span>
                        </button>
                    </div>

                    <div class="svc-field">
                        <span class="svc-label">
                            <i class="ph-fill ph-clock"></i>
                            Horário
                        </span>

                        <button
                            id="scheduled-time-trigger"
                            class="svc-temporal-trigger time"
                            type="button"
                        >
                            <span
                                class="svc-trigger-icon"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-clock"></i>
                            </span>

                            <span class="svc-trigger-copy">
                                <span
                                    id="scheduled-time-text"
                                    class="svc-trigger-value"
                                >
                                    Escolher horário
                                </span>
                            </span>

                            <span
                                class="svc-trigger-caret"
                                aria-hidden="true"
                            >
                                <i class="ph-fill ph-caret-down"></i>
                            </span>
                        </button>
                    </div>
                </div>

                <label class="svc-field">
                    <span class="svc-label">
                        <i class="ph-fill ph-map-pin"></i>
                        Local
                    </span>

                    <div class="svc-input location">
                        <span
                            class="svc-input-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-map-pin"></i>
                        </span>

                        <input
                            id="service-location"
                            name="location"
                            value="{{ old('location') }}"
                            maxlength="191"
                            placeholder="Onde será feito?"
                        >
                    </div>
                </label>
            </div>

            <footer class="svc-panel-actions">
                <button
                    class="svc-action"
                    type="button"
                    data-prev-step
                >
                    <i class="ph-fill ph-arrow-left"></i>
                    Voltar
                </button>

                <button
                    class="svc-action primary"
                    type="button"
                    data-next-step
                >
                    Continuar
                    <i class="ph-fill ph-arrow-right"></i>
                </button>
            </footer>
        </section>

        <section
            class="svc-panel finish"
            data-wizard-step="4"
            hidden
        >
            <header class="svc-panel-head">
                <span
                    class="svc-panel-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-check-circle"></i>
                </span>

                <div class="svc-panel-title">
                    <h2>Finalizar</h2>
                    <p>Confira e crie a ordem.</p>
                </div>
            </header>

            <div class="svc-panel-body">
                @foreach($versions as $version)
                    @php
                        $orderFields = $version
                            ->fields
                            ->where('phase', 'order');
                    @endphp

                    @if($orderFields->isNotEmpty())
                        <div
                            class="svc-dynamic-fields"
                            data-version-fields="{{ $version->id }}"
                            hidden
                        >
                            @foreach($orderFields as $field)
                                @include('services._order-field', ['field' => $field])
                            @endforeach
                        </div>
                    @endif
                @endforeach

                <dl class="svc-review">
                    <div class="svc-review-item">
                        <span class="svc-review-icon">
                            <i class="ph-fill ph-wrench"></i>
                        </span>

                        <span class="svc-review-copy">
                            <small>Serviço / prestador</small>
                            <strong id="review-service">—</strong>
                        </span>
                    </div>

                    <div class="svc-review-item">
                        <span class="svc-review-icon">
                            <i class="ph-fill ph-user-circle"></i>
                        </span>

                        <span class="svc-review-copy">
                            <small>Pessoa</small>
                            <strong id="review-person">—</strong>
                        </span>
                    </div>

                    <div class="svc-review-item">
                        <span class="svc-review-icon">
                            <i class="ph-fill ph-calendar-check"></i>
                        </span>

                        <span class="svc-review-copy">
                            <small>Quando</small>
                            <strong id="review-when">—</strong>
                        </span>
                    </div>

                    <div class="svc-review-item">
                        <span class="svc-review-icon">
                            <i class="ph-fill ph-map-pin"></i>
                        </span>

                        <span class="svc-review-copy">
                            <small>Local</small>
                            <strong id="review-location">—</strong>
                        </span>
                    </div>
                </dl>
            </div>

            <footer class="svc-panel-actions">
                <button
                    class="svc-action"
                    type="button"
                    data-prev-step
                >
                    <i class="ph-fill ph-arrow-left"></i>
                    Voltar
                </button>

                <button
                    id="create-order-button"
                    class="svc-action create"
                    type="submit"
                >
                    <i class="ph-fill ph-check-circle"></i>
                    Criar ordem
                </button>
            </footer>
        </section>
    </form>
</div>

<dialog
    class="svc-dialog"
    id="svc-picker-dialog"
    aria-label="Escolher opção"
>
    <div class="svc-dialog-layout">
        <header class="svc-dialog-head">
            <div class="svc-dialog-head-copy">
                <small>Escolher</small>
                <strong id="svc-picker-title">
                    Escolha uma opção
                </strong>
            </div>

            <button
                class="svc-dialog-close"
                type="button"
                data-close-dialog="svc-picker-dialog"
                aria-label="Fechar"
            >
                <i class="ph-fill ph-x"></i>
            </button>
        </header>

        <div class="svc-picker-body">
            <label class="svc-picker-search">
                <i class="ph-fill ph-magnifying-glass"></i>

                <input
                    id="svc-picker-search"
                    type="search"
                    placeholder="Buscar"
                    autocomplete="off"
                >
            </label>

            <div
                id="svc-picker-options"
                class="svc-picker-options"
            ></div>
        </div>

        <div></div>
    </div>
</dialog>

<dialog
    class="svc-dialog"
    id="svc-date-dialog"
    aria-label="Escolher dia"
>
    <div class="svc-dialog-layout">
        <header class="svc-dialog-head">
            <div class="svc-dialog-head-copy">
                <small>Agendamento</small>
                <strong id="svc-date-dialog-title">
                    Escolher dia
                </strong>
            </div>

            <button
                class="svc-dialog-close"
                type="button"
                data-close-dialog="svc-date-dialog"
                aria-label="Fechar"
            >
                <i class="ph-fill ph-x"></i>
            </button>
        </header>

        <div class="svc-calendar-body">
            <div class="svc-calendar-nav">
                <button
                    id="svc-calendar-prev"
                    type="button"
                    aria-label="Mês anterior"
                >
                    <i class="ph-fill ph-caret-left"></i>
                </button>

                <strong
                    id="svc-calendar-month"
                    class="svc-calendar-month"
                ></strong>

                <button
                    id="svc-calendar-next"
                    type="button"
                    aria-label="Próximo mês"
                >
                    <i class="ph-fill ph-caret-right"></i>
                </button>
            </div>

            <div class="svc-calendar-week" aria-hidden="true">
                <span>Dom</span>
                <span>Seg</span>
                <span>Ter</span>
                <span>Qua</span>
                <span>Qui</span>
                <span>Sex</span>
                <span>Sáb</span>
            </div>

            <div
                id="svc-calendar-grid"
                class="svc-calendar-grid"
            ></div>
        </div>

        <footer class="svc-dialog-foot">
            <button
                id="svc-calendar-today"
                class="svc-dialog-button"
                type="button"
            >
                Hoje
            </button>
        </footer>
    </div>
</dialog>

<dialog
    class="svc-dialog"
    id="svc-time-dialog"
    aria-label="Escolher horário"
>
    <div class="svc-dialog-layout">
        <header class="svc-dialog-head">
            <div class="svc-dialog-head-copy">
                <small>Agendamento</small>
                <strong id="svc-time-dialog-title">
                    Escolher horário
                </strong>
            </div>

            <button
                class="svc-dialog-close"
                type="button"
                data-close-dialog="svc-time-dialog"
                aria-label="Fechar"
            >
                <i class="ph-fill ph-x"></i>
            </button>
        </header>

        <div class="svc-time-body">
            <div
                id="svc-time-preview"
                class="svc-time-preview"
                aria-live="polite"
            >
                08:00
            </div>

            <div class="svc-time-columns">
                <section class="svc-time-column">
                    <span class="svc-time-column-title">
                        Hora
                    </span>

                    <div
                        id="svc-hour-grid"
                        class="svc-time-wheel"
                    ></div>
                </section>

                <section class="svc-time-column">
                    <span class="svc-time-column-title">
                        Minutos
                    </span>

                    <div
                        id="svc-minute-grid"
                        class="svc-time-wheel"
                    ></div>
                </section>
            </div>
        </div>

        <footer class="svc-dialog-foot">
            <button
                id="svc-time-confirm"
                class="svc-dialog-button primary"
                type="button"
            >
                Usar horário
            </button>
        </footer>
    </div>
</dialog>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form =
        document.getElementById('service-order-create-form');

    const serviceSelect =
        document.getElementById('service-version');

    const providerSelect =
        document.getElementById('service-provider');

    const associateSelect =
        document.getElementById('associate-select');

    const beneficiaryInput =
        document.getElementById('beneficiary-name');

    const memberNote =
        document.getElementById('member-note');

    const beneficiaryNote =
        document.getElementById('beneficiary-note');

    const locationInput =
        document.getElementById('service-location');

    const scheduledAt =
        document.getElementById('scheduled-at');

    const scheduledDateValue =
        document.getElementById('scheduled-date-value');

    const scheduledTimeValue =
        document.getElementById('scheduled-time-value');

    const scheduledDateTrigger =
        document.getElementById('scheduled-date-trigger');

    const scheduledTimeTrigger =
        document.getElementById('scheduled-time-trigger');

    const scheduledDateText =
        document.getElementById('scheduled-date-text');

    const scheduledDateMeta =
        document.getElementById('scheduled-date-meta');

    const scheduledTimeText =
        document.getElementById('scheduled-time-text');

    const submitButton =
        document.getElementById('create-order-button');

    const panels = [
        ...document.querySelectorAll(
            '[data-wizard-step]'
        ),
    ];

    const stepButtons = [
        ...document.querySelectorAll(
            '[data-step-button]'
        ),
    ];

    const pickerDialog =
        document.getElementById('svc-picker-dialog');

    const pickerTitle =
        document.getElementById('svc-picker-title');

    const pickerSearch =
        document.getElementById('svc-picker-search');

    const pickerOptions =
        document.getElementById('svc-picker-options');

    const dateDialog =
        document.getElementById('svc-date-dialog');

    const dateDialogTitle =
        document.getElementById('svc-date-dialog-title');

    const calendarMonth =
        document.getElementById('svc-calendar-month');

    const calendarGrid =
        document.getElementById('svc-calendar-grid');

    const calendarPrev =
        document.getElementById('svc-calendar-prev');

    const calendarNext =
        document.getElementById('svc-calendar-next');

    const calendarToday =
        document.getElementById('svc-calendar-today');

    const timeDialog =
        document.getElementById('svc-time-dialog');

    const timeDialogTitle =
        document.getElementById('svc-time-dialog-title');

    const timePreview =
        document.getElementById('svc-time-preview');

    const hourGrid =
        document.getElementById('svc-hour-grid');

    const minuteGrid =
        document.getElementById('svc-minute-grid');

    const timeConfirm =
        document.getElementById('svc-time-confirm');

    const reviewService =
        document.getElementById('review-service');

    const reviewPerson =
        document.getElementById('review-person');

    const reviewWhen =
        document.getElementById('review-when');

    const reviewLocation =
        document.getElementById('review-location');

    const selectTriggers =
        new WeakMap();

    const temporalTriggers =
        new WeakMap();

    let currentStep = 1;
    let highestStep = 1;
    let activeSelect = null;

    let activeDateCallback = null;
    let calendarView = new Date();
    let calendarSelected = '';

    let activeTimeCallback = null;
    let chosenHour = '08';
    let chosenMinute = '00';

    const escapeHtml = value => {
        const node =
            document.createElement('div');

        node.textContent =
            String(value ?? '');

        return node.innerHTML;
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
            return 'Escolher dia';
        }

        return `${pad2(parts.day)}/${pad2(parts.month)}/${parts.year}`;
    };

    const weekdayFor = value => {
        const parts =
            parseDateOnly(value);

        if (!parts) {
            return '';
        }

        return new Intl.DateTimeFormat(
            'pt-BR',
            {
                weekday: 'long',
            }
        ).format(
            new Date(
                parts.year,
                parts.month - 1,
                parts.day
            )
        );
    };

    const formatTime = value => {
        const match =
            /^(\d{2}):(\d{2})/
                .exec(String(value || ''));

        return match
            ? `${match[1]}:${match[2]}`
            : 'Escolher horário';
    };

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

    /* =====================================================
       DIALOGS + VOLTAR DO APARELHO
       ===================================================== */

    const directCloseDialog = dialog => {
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

    const closeOtherDialogs = except => {
        [
            pickerDialog,
            dateDialog,
            timeDialog,
        ].forEach(dialog => {
            if (
                dialog
                && dialog !== except
                && dialog.hasAttribute('open')
            ) {
                directCloseDialog(dialog);
            }
        });
    };

    const openManagedDialog = dialog => {
        if (!dialog) {
            return;
        }

        closeOtherDialogs(dialog);

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
            history.state?.svcDialog
            !== dialog.id
        ) {
            history.pushState(
                {
                    ...(history.state || {}),
                    svcDialog: dialog.id,
                },
                '',
                window.location.href
            );
        }
    };

    const requestCloseDialog = dialog => {
        if (!dialog) {
            return;
        }

        if (
            history.state?.svcDialog
            === dialog.id
        ) {
            history.back();
            return;
        }

        directCloseDialog(dialog);
    };

    window.addEventListener(
        'popstate',
        () => {
            [
                pickerDialog,
                dateDialog,
                timeDialog,
            ].forEach(dialog => {
                if (
                    dialog?.hasAttribute('open')
                ) {
                    directCloseDialog(dialog);
                }
            });
        }
    );

    document
        .querySelectorAll(
            '[data-close-dialog]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    requestCloseDialog(
                        document.getElementById(
                            button.dataset.closeDialog
                        )
                    );
                }
            );
        });

    [
        pickerDialog,
        dateDialog,
        timeDialog,
    ].forEach(dialog => {
        dialog?.addEventListener(
            'cancel',
            event => {
                event.preventDefault();
                requestCloseDialog(dialog);
            }
        );

        dialog?.addEventListener(
            'click',
            event => {
                if (event.target === dialog) {
                    requestCloseDialog(dialog);
                }
            }
        );
    });

    /* =====================================================
       SELECT CUSTOM
       ===================================================== */

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
                '.svc-trigger-value'
            );

        const metaNode =
            trigger.querySelector(
                '.svc-trigger-meta'
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

        trigger.disabled =
            select.disabled;

        if (select.value) {
            trigger.classList.remove(
                'invalid'
            );

            trigger
                .parentElement
                ?.querySelector(
                    '.svc-custom-error'
                )
                ?.remove();
        }
    };

    const renderSelectOptions = (
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
            if (
                option.disabled
                || option.hidden
            ) {
                return false;
            }

            if (!normalized) {
                return true;
            }

            const text = (
                option.dataset.search
                || [
                    optionPrimary(option),
                    optionSecondary(option),
                    option.textContent,
                ].join(' ')
            ).toLocaleLowerCase('pt-BR');

            return text.includes(normalized);
        });

        pickerOptions.innerHTML = '';

        if (!options.length) {
            pickerOptions.innerHTML = `
                <div class="svc-picker-empty">
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
                'svc-picker-option';

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
                <span class="svc-picker-option-icon">
                    <i class="ph-fill ${escapeHtml(icon)}"></i>
                </span>

                <span class="svc-picker-option-copy">
                    <strong>${escapeHtml(primary)}</strong>
                    ${
                        secondary
                            ? `<small>${escapeHtml(secondary)}</small>`
                            : ''
                    }
                </span>

                <span class="svc-picker-option-check">
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
                    requestCloseDialog(pickerDialog);
                }
            );

            pickerOptions.appendChild(button);
        });
    };

    const openSelect = select => {
        if (!select || select.disabled) {
            return;
        }

        activeSelect = select;

        pickerTitle.textContent =
            select.dataset.pickerTitle
            || 'Escolher';

        pickerSearch.value = '';

        renderSelectOptions(select);

        openManagedDialog(pickerDialog);

        if (
            window.matchMedia(
                '(min-width: 701px)'
            ).matches
        ) {
            window.setTimeout(
                () => pickerSearch.focus(),
                40
            );
        }
    };

    const enhanceSelect = select => {
        if (
            !select
            || select.dataset.enhanced === '1'
        ) {
            return;
        }

        select.dataset.enhanced = '1';

        select.dataset.customRequired =
            select.required ? '1' : '0';

        select.required = false;

        select.classList.add(
            'svc-native-select'
        );

        const trigger =
            document.createElement('button');

        trigger.type = 'button';
        trigger.className =
            'svc-select-trigger';

        trigger.dataset.tone =
            select.dataset.pickerTone
            || 'blue';

        const icon =
            select.dataset.pickerIcon
            || 'ph-list';

        trigger.innerHTML = `
            <span class="svc-trigger-icon">
                <i class="ph-fill ${escapeHtml(icon)}"></i>
            </span>

            <span class="svc-trigger-copy">
                <span class="svc-trigger-value"></span>
                <span
                    class="svc-trigger-meta"
                    hidden
                ></span>
            </span>

            <span class="svc-trigger-caret">
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

    pickerSearch?.addEventListener(
        'input',
        () => {
            if (activeSelect) {
                renderSelectOptions(
                    activeSelect,
                    pickerSearch.value
                );
            }
        }
    );

    /* =====================================================
       CALENDÁRIO
       ===================================================== */

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
                new Date(year, month, 1)
            );

        const first =
            new Date(year, month, 1);

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
                'svc-calendar-day';

            button.textContent =
                String(current.getDate());

            if (
                current.getMonth()
                !== month
            ) {
                button.classList.add(
                    'outside'
                );
            }

            if (current.getDay() === 0) {
                button.classList.add(
                    'sunday'
                );
            }

            if (value === todayValue) {
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
                    calendarSelected =
                        value;

                    activeDateCallback?.(
                        value
                    );

                    requestCloseDialog(
                        dateDialog
                    );
                }
            );

            calendarGrid.appendChild(
                button
            );
        }
    };

    const openDatePicker = (
        value,
        title,
        callback
    ) => {
        calendarSelected =
            value || '';

        const parsed =
            parseDateOnly(value);

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

        activeDateCallback =
            callback;

        dateDialogTitle.textContent =
            title || 'Escolher dia';

        renderCalendar();
        openManagedDialog(dateDialog);
    };

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
            const now =
                new Date();

            const value =
                dateOnlyToString(
                    now.getFullYear(),
                    now.getMonth() + 1,
                    now.getDate()
                );

            activeDateCallback?.(value);
            requestCloseDialog(dateDialog);
        }
    );

    /* =====================================================
       HORÁRIO
       ===================================================== */

    const updateTimePreview = () => {
        timePreview.textContent =
            `${chosenHour}:${chosenMinute}`;

        hourGrid
            .querySelectorAll(
                '.svc-time-option'
            )
            .forEach(button => {
                button.classList.toggle(
                    'selected',
                    button.dataset.value
                        === chosenHour
                );
            });

        minuteGrid
            .querySelectorAll(
                '.svc-time-option'
            )
            .forEach(button => {
                button.classList.toggle(
                    'selected',
                    button.dataset.value
                        === chosenMinute
                );
            });
    };

    const centerTimeWheel = (
        wheel,
        value
    ) => {
        const selected = [
            ...wheel.querySelectorAll(
                '.svc-time-option'
            ),
        ].find(
            button =>
                button.dataset.value
                === value
        );

        if (!selected) {
            return;
        }

        wheel.scrollTop =
            Math.max(
                0,
                selected.offsetTop
                - (
                    wheel.clientHeight
                    - selected.offsetHeight
                ) / 2
            );
    };

    const renderTimeOptions = () => {
        hourGrid.innerHTML = '';

        for (
            let hour = 0;
            hour < 24;
            hour += 1
        ) {
            const value =
                pad2(hour);

            const button =
                document.createElement('button');

            button.type = 'button';
            button.className =
                'svc-time-option';

            button.dataset.value =
                value;

            button.textContent =
                value;

            button.addEventListener(
                'click',
                () => {
                    chosenHour =
                        value;

                    updateTimePreview();
                }
            );

            hourGrid.appendChild(
                button
            );
        }

        const minuteValues =
            new Set([
                '00',
                '05',
                '10',
                '15',
                '20',
                '25',
                '30',
                '35',
                '40',
                '45',
                '50',
                '55',
                chosenMinute,
            ]);

        const sortedMinutes = [
            ...minuteValues,
        ].sort(
            (a, b) =>
                Number(a) - Number(b)
        );

        minuteGrid.innerHTML = '';

        sortedMinutes.forEach(value => {
            const button =
                document.createElement('button');

            button.type = 'button';
            button.className =
                'svc-time-option';

            button.dataset.value =
                value;

            button.textContent =
                value;

            button.addEventListener(
                'click',
                () => {
                    chosenMinute =
                        value;

                    updateTimePreview();
                }
            );

            minuteGrid.appendChild(
                button
            );
        });

        updateTimePreview();
    };

    const openTimePicker = (
        value,
        title,
        callback
    ) => {
        const match =
            /^(\d{2}):(\d{2})/
                .exec(String(value || ''));

        chosenHour =
            match?.[1] || '08';

        chosenMinute =
            match?.[2] || '00';

        activeTimeCallback =
            callback;

        timeDialogTitle.textContent =
            title || 'Escolher horário';

        renderTimeOptions();
        openManagedDialog(timeDialog);

        window.requestAnimationFrame(
            () => {
                centerTimeWheel(
                    hourGrid,
                    chosenHour
                );

                centerTimeWheel(
                    minuteGrid,
                    chosenMinute
                );
            }
        );
    };

    timeConfirm?.addEventListener(
        'click',
        () => {
            activeTimeCallback?.(
                `${chosenHour}:${chosenMinute}`
            );

            requestCloseDialog(timeDialog);
        }
    );

    /* =====================================================
       DATA / HORA DO AGENDAMENTO
       ===================================================== */

    const syncScheduledAt = () => {
        const date =
            scheduledDateValue?.value
            || '';

        const time =
            scheduledTimeValue?.value
            || '';

        scheduledAt.value =
            date && time
                ? `${date}T${time}`
                : '';

        scheduledDateText.textContent =
            formatDate(date);

        scheduledDateText.classList.toggle(
            'placeholder',
            !date
        );

        scheduledDateTrigger.classList.toggle(
            'filled',
            Boolean(date)
        );

        scheduledTimeText.textContent =
            formatTime(time);

        scheduledTimeText.classList.toggle(
            'placeholder',
            !time
        );

        scheduledTimeTrigger.classList.toggle(
            'filled',
            Boolean(time)
        );

        const weekday =
            weekdayFor(date);

        scheduledDateMeta.textContent =
            weekday;

        scheduledDateMeta.hidden =
            !weekday;
    };

    scheduledDateTrigger?.addEventListener(
        'click',
        () => {
            openDatePicker(
                scheduledDateValue.value,
                'Escolher dia',
                value => {
                    scheduledDateValue.value =
                        value;

                    syncScheduledAt();
                    updateReview();
                }
            );
        }
    );

    scheduledTimeTrigger?.addEventListener(
        'click',
        () => {
            openTimePicker(
                scheduledTimeValue.value,
                'Escolher horário',
                value => {
                    scheduledTimeValue.value =
                        value;

                    syncScheduledAt();
                    updateReview();
                }
            );
        }
    );

    /* =====================================================
       CAMPOS DINÂMICOS DATE/TIME
       ===================================================== */

    const updateTemporalTrigger = input => {
        const trigger =
            temporalTriggers.get(input);

        if (!trigger) {
            return;
        }

        const valueNode =
            trigger.querySelector(
                '.svc-trigger-value'
            );

        valueNode.textContent =
            input.type === 'date'
                ? formatDate(input.value)
                : formatTime(input.value);

        valueNode.classList.toggle(
            'placeholder',
            !input.value
        );

        trigger.classList.toggle(
            'filled',
            Boolean(input.value)
        );

        trigger.disabled =
            input.disabled;
    };

    const enhanceTemporalInput = input => {
        if (
            !input
            || input.dataset.temporalEnhanced
                === '1'
            || !['date', 'time']
                .includes(input.type)
        ) {
            return;
        }

        input.dataset.temporalEnhanced =
            '1';

        input.dataset.customRequired =
            input.required ? '1' : '0';

        input.required = false;

        input.classList.add(
            'svc-native-temporal'
        );

        const trigger =
            document.createElement('button');

        trigger.type = 'button';

        trigger.className =
            `svc-temporal-trigger ${input.type}`;

        const isDate =
            input.type === 'date';

        trigger.innerHTML = `
            <span class="svc-trigger-icon">
                <i class="ph-fill ${isDate ? 'ph-calendar-blank' : 'ph-clock'}"></i>
            </span>

            <span class="svc-trigger-copy">
                <span class="svc-trigger-value"></span>
            </span>

            <span class="svc-trigger-caret">
                <i class="ph-fill ph-caret-down"></i>
            </span>
        `;

        input.insertAdjacentElement(
            'afterend',
            trigger
        );

        temporalTriggers.set(
            input,
            trigger
        );

        trigger.addEventListener(
            'click',
            () => {
                const label =
                    input
                        .closest('label')
                        ?.textContent
                        ?.replace(/\s+/g, ' ')
                        ?.trim()
                    || (
                        isDate
                            ? 'Escolher dia'
                            : 'Escolher horário'
                    );

                if (isDate) {
                    openDatePicker(
                        input.value,
                        label,
                        value => {
                            input.value =
                                value;

                            input.dispatchEvent(
                                new Event(
                                    'change',
                                    {
                                        bubbles: true,
                                    }
                                )
                            );

                            updateTemporalTrigger(
                                input
                            );
                        }
                    );
                } else {
                    openTimePicker(
                        input.value,
                        label,
                        value => {
                            input.value =
                                value;

                            input.dispatchEvent(
                                new Event(
                                    'change',
                                    {
                                        bubbles: true,
                                    }
                                )
                            );

                            updateTemporalTrigger(
                                input
                            );
                        }
                    );
                }
            }
        );

        input.addEventListener(
            'change',
            () => {
                updateTemporalTrigger(input);
            }
        );

        updateTemporalTrigger(input);
    };

    /* =====================================================
       REGRAS DA ORDEM
       ===================================================== */

    const syncOrderRules = () => {
        const serviceOption =
            selectedOption(serviceSelect);

        const selectedServiceId =
            serviceOption?.dataset.service
            || '';

        const memberRequired =
            serviceOption
                ?.dataset
                .membersOnly === '1';

        if (associateSelect) {
            associateSelect.dataset
                .customRequired =
                memberRequired
                    ? '1'
                    : '0';
        }

        if (memberNote) {
            memberNote.textContent =
                memberRequired
                    ? 'obrigatório'
                    : 'opcional';

            memberNote.className =
                memberRequired
                    ? 'svc-note required'
                    : 'svc-note';
        }

        if (beneficiaryInput) {
            beneficiaryInput.required =
                !associateSelect?.value;
        }

        if (beneficiaryNote) {
            beneficiaryNote.textContent =
                associateSelect?.value
                    ? 'Usaremos o apelido do membro.'
                    : 'Preencha somente se não escolher um membro.';
        }

        if (
            associateSelect?.value
            && beneficiaryInput
        ) {
            beneficiaryInput.value =
                selectedOption(
                    associateSelect
                )?.dataset.name
                || beneficiaryInput.value;
        }

        if (providerSelect) {
            [
                ...providerSelect.options,
            ].forEach(
                (
                    option,
                    index
                ) => {
                    if (index === 0) {
                        option.hidden = false;
                        option.disabled = false;
                        return;
                    }

                    const services = (
                        option.dataset.services
                        || ''
                    )
                        .split(',')
                        .map(value => value.trim())
                        .filter(Boolean);

                    const available =
                        Boolean(selectedServiceId)
                        && services.includes(
                            selectedServiceId
                        );

                    option.hidden =
                        !available;

                    option.disabled =
                        !available;
                }
            );

            if (
                providerSelect
                    .selectedOptions?.[0]
                    ?.disabled
            ) {
                providerSelect.value = '';
            }

            updateSelectTrigger(
                providerSelect
            );
        }

        document
            .querySelectorAll(
                '[data-version-fields]'
            )
            .forEach(group => {
                const visible =
                    group.dataset.versionFields
                    === serviceSelect?.value;

                group.hidden =
                    !visible;

                group
                    .querySelectorAll(
                        'input,select,textarea,button'
                    )
                    .forEach(field => {
                        field.disabled =
                            !visible;
                    });

                group
                    .querySelectorAll(
                        'select'
                    )
                    .forEach(
                        updateSelectTrigger
                    );

                group
                    .querySelectorAll(
                        'input[type="date"],input[type="time"]'
                    )
                    .forEach(
                        updateTemporalTrigger
                    );
            });

        updateSelectTrigger(
            associateSelect
        );

        updatePlainInputs();
        updateReview();
    };

    /* =====================================================
       VISUAL DOS INPUTS
       ===================================================== */

    const updatePlainInputs = () => {
        form
            .querySelectorAll(
                '.svc-input input, .svc-input textarea'
            )
            .forEach(input => {
                input
                    .closest('.svc-input')
                    ?.classList.toggle(
                        'filled',
                        Boolean(
                            String(
                                input.value
                                || ''
                            ).trim()
                        )
                    );
            });
    };

    /* =====================================================
       REVIEW
       ===================================================== */

    const updateReview = () => {
        const serviceName =
            serviceSelect?.value
                ? optionPrimary(
                    selectedOption(serviceSelect)
                )
                : '';

        const providerName =
            providerSelect?.value
                ? optionPrimary(
                    selectedOption(providerSelect)
                )
                : 'Sem prestador';

        const service =
            serviceName
                ? `${serviceName} · ${providerName}`
                : '—';

        const person =
            beneficiaryInput
                ?.value
                ?.trim()
            || (
                associateSelect?.value
                    ? optionPrimary(
                        selectedOption(
                            associateSelect
                        )
                    )
                    : ''
            )
            || '—';

        const date =
            scheduledDateValue?.value
                ? formatDate(
                    scheduledDateValue.value
                )
                : '';

        const time =
            scheduledTimeValue?.value
                ? formatTime(
                    scheduledTimeValue.value
                )
                : '';

        reviewService.textContent =
            service;

        reviewPerson.textContent =
            person;

        reviewWhen.textContent =
            date && time
                ? `${date} · ${time}`
                : '—';

        reviewLocation.textContent =
            locationInput
                ?.value
                ?.trim()
            || '—';
    };

    /* =====================================================
       VALIDAÇÃO
       ===================================================== */

    const clearCustomErrors = container => {
        container
            ?.querySelectorAll(
                '.svc-custom-error'
            )
            .forEach(
                node => node.remove()
            );

        container
            ?.querySelectorAll(
                '.invalid'
            )
            .forEach(
                node =>
                    node.classList.remove(
                        'invalid'
                    )
            );
    };

    const showCustomError = (
        trigger,
        message
    ) => {
        if (!trigger) {
            return;
        }

        trigger.classList.add(
            'invalid'
        );

        const error =
            document.createElement('span');

        error.className =
            'svc-custom-error';

        error.innerHTML = `
            <i class="ph-fill ph-warning-circle"></i>
            ${escapeHtml(message)}
        `;

        trigger.insertAdjacentElement(
            'afterend',
            error
        );
    };

    const validateContainer = container => {
        if (!container) {
            return true;
        }

        clearCustomErrors(container);

        let firstInvalid = null;

        container
            .querySelectorAll(
                'select[data-enhanced="1"]'
            )
            .forEach(select => {
                if (
                    select.disabled
                    || select.dataset.customRequired
                        !== '1'
                    || select.value
                ) {
                    return;
                }

                const trigger =
                    selectTriggers.get(select);

                showCustomError(
                    trigger,
                    'Escolha uma opção.'
                );

                firstInvalid ??=
                    trigger;
            });

        container
            .querySelectorAll(
                'input[data-temporal-enhanced="1"]'
            )
            .forEach(input => {
                if (
                    input.disabled
                    || input.dataset.customRequired
                        !== '1'
                    || input.value
                ) {
                    return;
                }

                const trigger =
                    temporalTriggers.get(input);

                showCustomError(
                    trigger,
                    input.type === 'date'
                        ? 'Escolha o dia.'
                        : 'Escolha o horário.'
                );

                firstInvalid ??=
                    trigger;
            });

        const plainRequired = [
            ...container.querySelectorAll(
                'input[required]:not([type="hidden"]):not(.svc-native-temporal), textarea[required]'
            ),
        ].filter(
            field => !field.disabled
        );

        for (
            const field of plainRequired
        ) {
            if (field.checkValidity()) {
                continue;
            }

            firstInvalid ??=
                field;

            break;
        }

        if (
            container.dataset.wizardStep
            === '3'
        ) {
            if (
                !scheduledDateValue.value
            ) {
                showCustomError(
                    scheduledDateTrigger,
                    'Escolha o dia.'
                );

                firstInvalid ??=
                    scheduledDateTrigger;
            }

            if (
                !scheduledTimeValue.value
            ) {
                showCustomError(
                    scheduledTimeTrigger,
                    'Escolha o horário.'
                );

                firstInvalid ??=
                    scheduledTimeTrigger;
            }
        }

        if (!firstInvalid) {
            return true;
        }

        firstInvalid.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });

        if (
            typeof firstInvalid.focus
            === 'function'
        ) {
            firstInvalid.focus({
                preventScroll: true,
            });
        }

        if (
            firstInvalid instanceof
                HTMLInputElement
            || firstInvalid instanceof
                HTMLTextAreaElement
        ) {
            firstInvalid.reportValidity();
        }

        return false;
    };

    const validateStep = step => {
        const panel =
            panels.find(
                item =>
                    Number(
                        item.dataset.wizardStep
                    ) === step
            );

        return validateContainer(panel);
    };

    /* =====================================================
       WIZARD
       ===================================================== */

    const showStep = step => {
        currentStep =
            Math.max(
                1,
                Math.min(4, step)
            );

        highestStep =
            Math.max(
                highestStep,
                currentStep
            );

        panels.forEach(panel => {
            const panelStep =
                Number(
                    panel.dataset.wizardStep
                );

            panel.hidden =
                panelStep !== currentStep;
        });

        stepButtons.forEach(button => {
            const buttonStep =
                Number(
                    button.dataset.stepButton
                );

            button.classList.toggle(
                'current',
                buttonStep === currentStep
            );

            button.classList.toggle(
                'done',
                buttonStep < currentStep
                || buttonStep < highestStep
            );

            if (
                buttonStep === currentStep
            ) {
                button.setAttribute(
                    'aria-current',
                    'step'
                );
            } else {
                button.removeAttribute(
                    'aria-current'
                );
            }
        });

        if (currentStep === 4) {
            updateReview();
        }

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    };

    document
        .querySelectorAll(
            '[data-next-step]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    if (
                        !validateStep(
                            currentStep
                        )
                    ) {
                        return;
                    }

                    showStep(
                        currentStep + 1
                    );
                }
            );
        });

    document
        .querySelectorAll(
            '[data-prev-step]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    showStep(
                        currentStep - 1
                    );
                }
            );
        });

    stepButtons.forEach(button => {
        button.addEventListener(
            'click',
            () => {
                const target =
                    Number(
                        button.dataset.stepButton
                    );

                if (
                    target < currentStep
                    || target < highestStep
                ) {
                    showStep(target);
                }
            }
        );
    });

    /* =====================================================
       ENHANCE
       ===================================================== */

    form
        .querySelectorAll('select')
        .forEach(select => {
            if (!select.dataset.pickerTitle) {
                select.dataset.pickerTitle =
                    select
                        .closest('label')
                        ?.querySelector(
                            '.svc-label'
                        )
                        ?.textContent
                        ?.replace(/\s+/g, ' ')
                        ?.trim()
                    || 'Escolher';
            }

            if (!select.dataset.pickerIcon) {
                select.dataset.pickerIcon =
                    'ph-list';
            }

            enhanceSelect(select);
        });

    form
        .querySelectorAll(
            'input[type="date"], input[type="time"]'
        )
        .forEach(
            enhanceTemporalInput
        );

    serviceSelect?.addEventListener(
        'change',
        syncOrderRules
    );

    providerSelect?.addEventListener(
        'change',
        () => {
            updateSelectTrigger(
                providerSelect
            );

            updateReview();
        }
    );

    associateSelect?.addEventListener(
        'change',
        syncOrderRules
    );

    beneficiaryInput?.addEventListener(
        'input',
        () => {
            updatePlainInputs();
            updateReview();
        }
    );

    locationInput?.addEventListener(
        'input',
        () => {
            updatePlainInputs();
            updateReview();
        }
    );

    form.addEventListener(
        'change',
        () => {
            updatePlainInputs();
            updateReview();
        }
    );

    form.addEventListener(
        'submit',
        event => {
            syncScheduledAt();
            syncOrderRules();

            for (
                let step = 1;
                step <= 4;
                step += 1
            ) {
                if (
                    !validateStep(step)
                ) {
                    event.preventDefault();
                    showStep(step);
                    return;
                }
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add(
                    'loading'
                );

                submitButton.innerHTML = `
                    <i class="ph-fill ph-spinner-gap"></i>
                    Criando…
                `;
            }
        }
    );

    syncScheduledAt();
    syncOrderRules();
    updatePlainInputs();
    updateReview();
    showStep(1);
});
</script>
@endsection