<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow,noarchive">

    <title>
        Segurança e acesso - {{ config('app.name', 'ZeCoop SGC') }}
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/duotone/style.css"
    >

    @php
        $activePasskeysCount = $activePasskeys->count();

        $googleAccounts = $oauthAccounts->where(
            'provider',
            'google'
        );

        $hasGoogleAccount = $googleAccounts->isNotEmpty();
    @endphp

    <style>
        :root {
            --security-primary: var(--color-primary, #22c55e);
            --security-primary-dark: var(--color-primary-dark, #16a34a);
            --security-primary-deep: var(--color-primary-deep, #15803d);

            --security-surface: var(--color-surface, #ffffff);
            --security-soft: var(--color-surface-soft, #f8faf9);
            --security-muted: var(--color-surface-muted, #eef4f0);

            --security-border: var(--color-border, #dce6df);
            --security-border-strong: var(--color-border-strong, #c8d6cd);

            --security-text: var(--color-text, #102018);
            --security-secondary: var(--color-text-secondary, #52645a);
            --security-faded: var(--color-text-muted, #809087);

            --security-danger: var(--color-danger, #dc2626);
            --security-danger-dark: #b91c1c;
            --security-danger-soft: #fff7f7;

            --security-warning: var(--color-warning, #d97706);
            --security-warning-soft: #fffbeb;

            --security-info: var(--color-info, #0284c7);
            --security-info-soft: #eff6ff;

            --security-shadow-sm:
                0 5px 18px rgba(15, 35, 24, .055);

            --security-shadow:
                0 15px 42px rgba(15, 35, 24, .09);

            --security-shadow-lg:
                0 28px 82px rgba(8, 24, 15, .28);
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            min-height: 100%;
            background: #eef4f0;
        }

        body {
            margin: 0;
            min-width: 320px;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            background:
                radial-gradient(
                    circle at 8% 4%,
                    rgba(34, 197, 94, .10),
                    transparent 24rem
                ),
                radial-gradient(
                    circle at 95% 96%,
                    rgba(22, 163, 74, .07),
                    transparent 26rem
                ),
                linear-gradient(
                    180deg,
                    #f8fbf9 0%,
                    #f1f6f3 48%,
                    #edf3ef 100%
                );
            color: var(--security-text);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button,
        a,
        input {
            -webkit-tap-highlight-color: transparent;
        }

        [hidden] {
            display: none !important;
        }

        .security-page {
            position: relative;
            isolation: isolate;
            width: min(100% - 28px, 980px);
            margin: 0 auto;
            padding:
                max(22px, env(safe-area-inset-top))
                0
                max(28px, env(safe-area-inset-bottom));
        }

        .security-page::before {
            position: fixed;
            z-index: -1;
            inset: 0;
            opacity: .65;
            background-image:
                linear-gradient(
                    rgba(21, 128, 61, .025) 1px,
                    transparent 1px
                ),
                linear-gradient(
                    90deg,
                    rgba(21, 128, 61, .025) 1px,
                    transparent 1px
                );
            background-size: 28px 28px;
            mask-image:
                linear-gradient(
                    to bottom,
                    rgba(0, 0, 0, .7),
                    transparent 90%
                );
            content: "";
            pointer-events: none;
        }

        .security-topbar {
            position: relative;
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .78rem;
            align-items: center;
            padding: .8rem .85rem;
            overflow: hidden;
            border: 1px solid var(--security-border);
            border-left: 4px solid var(--security-primary-dark);
            border-radius: 15px;
            background:
                linear-gradient(
                    90deg,
                    rgba(236, 253, 245, .80),
                    rgba(255, 255, 255, .985) 43%
                ),
                var(--security-surface);
            box-shadow: var(--security-shadow-sm);
        }

        .security-back {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border: 1px solid var(--security-border);
            border-radius: 10px;
            background: var(--security-surface);
            color: var(--security-secondary);
            text-decoration: none;
            transition:
                border-color 150ms ease,
                color 150ms ease,
                transform 150ms ease;
        }

        .security-back:hover,
        .security-back:focus-visible {
            border-color: rgba(34, 197, 94, .42);
            color: var(--security-primary-dark);
            outline: none;
            transform: translateX(-1px);
        }

        .security-back i {
            font-size: 1.05rem;
        }

        .security-topbar-copy {
            min-width: 0;
        }

        .security-kicker {
            display: flex;
            align-items: center;
            gap: .34rem;
            color: var(--security-primary-dark);
            font-size: .6rem;
            font-weight: 820;
            letter-spacing: .065em;
            text-transform: uppercase;
        }

        .security-kicker i {
            font-size: .84rem;
        }

        .security-topbar h1 {
            margin: .13rem 0 0;
            overflow: hidden;
            color: var(--security-text);
            font-size: clamp(1.02rem, 2vw, 1.28rem);
            font-weight: 870;
            letter-spacing: -.035em;
            line-height: 1.2;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .security-topbar p {
            margin: .23rem 0 0;
            overflow: hidden;
            color: var(--security-secondary);
            font-size: .66rem;
            line-height: 1.42;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .security-state {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            gap: .34rem;
            padding: .34rem .5rem;
            border: 1px solid rgba(34, 197, 94, .19);
            border-radius: 999px;
            background: #ecfdf5;
            color: var(--security-primary-deep);
            font-size: .56rem;
            font-weight: 810;
            white-space: nowrap;
        }

        .security-state i {
            font-size: .82rem;
        }

        .security-content {
            display: grid;
            gap: .75rem;
            margin-top: .75rem;
        }

        .status {
            display: none;
            align-items: flex-start;
            gap: .48rem;
            padding: .65rem .7rem;
            border: 1px solid var(--security-border);
            border-radius: 11px;
            background: var(--security-soft);
            color: var(--security-secondary);
            font-size: .65rem;
            font-weight: 640;
            line-height: 1.5;
            box-shadow: var(--security-shadow-sm);
        }

        .status.show {
            display: flex;
        }

        .status i {
            flex: 0 0 auto;
            margin-top: .04rem;
            font-size: 1rem;
        }

        .status.error {
            border-color: #fecaca;
            background: var(--security-danger-soft);
            color: #991b1b;
        }

        .status.success {
            border-color: #bbf7d0;
            background: #ecfdf5;
            color: #047857;
        }

        .status.warning {
            border-color: #fde68a;
            background: var(--security-warning-soft);
            color: #92400e;
        }

        .reauth {
            position: relative;
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .7rem;
            align-items: center;
            padding: .72rem;
            overflow: hidden;
            border: 1px solid #f3d79a;
            border-left: 4px solid var(--security-warning);
            border-radius: 13px;
            background:
                linear-gradient(
                    90deg,
                    #fff9e9,
                    rgba(255, 255, 255, .98) 48%
                );
            box-shadow: var(--security-shadow-sm);
        }

        .reauth-icon {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border-radius: 11px;
            background: #fef3c7;
            color: var(--security-warning);
        }

        .reauth-icon i {
            font-size: 1.18rem;
        }

        .reauth-copy {
            min-width: 0;
        }

        .reauth h2 {
            margin: 0;
            color: var(--security-text);
            font-size: .77rem;
            font-weight: 830;
        }

        .reauth p {
            margin: .16rem 0 0;
            color: #72551d;
            font-size: .62rem;
            line-height: 1.45;
        }

        .reauth-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .38rem;
        }

        .security-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .65rem;
        }

        .summary-card {
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr);
            gap: .6rem;
            align-items: center;
            padding: .68rem;
            border: 1px solid var(--security-border);
            border-radius: 13px;
            background: var(--security-surface);
            box-shadow: var(--security-shadow-sm);
        }

        .summary-icon {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 11px;
            background: var(--security-muted);
            color: var(--security-primary-dark);
        }

        .summary-icon i {
            font-size: 1.12rem;
        }

        .summary-card.is-google .summary-icon {
            background: var(--security-info-soft);
            color: var(--security-info);
        }

        .summary-card.is-session .summary-icon {
            background: var(--security-warning-soft);
            color: var(--security-warning);
        }

        .summary-copy {
            min-width: 0;
        }

        .summary-copy span,
        .summary-copy strong {
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .summary-copy span {
            color: var(--security-faded);
            font-size: .57rem;
            font-weight: 680;
        }

        .summary-copy strong {
            margin-top: .1rem;
            color: var(--security-text);
            font-size: .72rem;
            font-weight: 830;
        }

        .security-section {
            overflow: hidden;
            border: 1px solid var(--security-border);
            border-radius: 15px;
            background: var(--security-surface);
            box-shadow: var(--security-shadow);
        }

        .section-head {
            display: flex;
            min-height: 64px;
            align-items: center;
            justify-content: space-between;
            gap: .72rem;
            padding: .68rem .75rem;
            border-bottom: 1px solid var(--security-border);
            background:
                linear-gradient(
                    180deg,
                    var(--security-soft),
                    var(--security-surface)
                );
        }

        .section-heading {
            display: flex;
            min-width: 0;
            align-items: center;
            gap: .58rem;
        }

        .section-icon {
            display: grid;
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 11px;
            background: #ecfdf5;
            color: var(--security-primary-dark);
        }

        .section-icon.is-google {
            background: var(--security-info-soft);
            color: var(--security-info);
        }

        .section-icon i {
            font-size: 1.13rem;
        }

        .section-head h2 {
            margin: 0;
            color: var(--security-text);
            font-size: .86rem;
            font-weight: 840;
            letter-spacing: -.02em;
        }

        .section-head p {
            max-width: 590px;
            margin: .14rem 0 0;
            color: var(--security-faded);
            font-size: .59rem;
            line-height: 1.42;
        }

        .section-body {
            padding: .58rem;
        }

        .security-list {
            display: grid;
            gap: .42rem;
        }

        .security-row {
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .62rem;
            align-items: center;
            padding: .64rem;
            border: 1px solid transparent;
            border-radius: 11px;
            background: var(--security-surface);
            transition:
                border-color 150ms ease,
                background 150ms ease,
                box-shadow 150ms ease;
        }

        .security-row:hover {
            border-color: var(--security-border);
            background: var(--security-soft);
            box-shadow: 0 5px 15px rgba(15, 35, 24, .04);
        }

        .row-icon {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 10px;
            background: var(--security-muted);
            color: var(--security-primary-dark);
        }

        .row-icon.is-google {
            background: var(--security-info-soft);
            color: var(--security-info);
        }

        .row-icon i {
            font-size: 1rem;
        }

        .row-copy {
            min-width: 0;
        }

        .row-title-line {
            display: flex;
            min-width: 0;
            flex-wrap: wrap;
            align-items: center;
            gap: .34rem;
        }

        .row-title-line strong {
            overflow: hidden;
            color: var(--security-text);
            font-size: .72rem;
            font-weight: 810;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .row-copy > span {
            display: block;
            margin-top: .15rem;
            overflow: hidden;
            color: var(--security-faded);
            font-size: .59rem;
            line-height: 1.45;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .row-badge {
            display: inline-flex;
            min-height: 21px;
            align-items: center;
            gap: .22rem;
            padding: .16rem .32rem;
            border-radius: 999px;
            background: #ecfdf5;
            color: var(--security-primary-deep);
            font-size: .49rem;
            font-weight: 830;
            white-space: nowrap;
        }

        .row-badge.is-revoked,
        .row-badge.is-expired {
            background: #fef2f2;
            color: var(--security-danger);
        }

        .row-badge i {
            font-size: .66rem;
        }

        .empty {
            display: grid;
            min-height: 130px;
            place-items: center;
            padding: 1rem;
            border: 1px dashed var(--security-border-strong);
            border-radius: 11px;
            background: var(--security-soft);
            color: var(--security-secondary);
            text-align: center;
        }

        .empty-icon {
            display: grid;
            width: 46px;
            height: 46px;
            place-items: center;
            margin: 0 auto .48rem;
            border-radius: 13px;
            background: var(--security-muted);
            color: var(--security-faded);
        }

        .empty-icon i {
            font-size: 1.3rem;
        }

        .empty strong {
            display: block;
            color: var(--security-text);
            font-size: .72rem;
            font-weight: 820;
        }

        .empty p {
            max-width: 350px;
            margin: .16rem auto 0;
            color: var(--security-faded);
            font-size: .59rem;
            line-height: 1.45;
        }

        .btn {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            gap: .34rem;
            padding: .42rem .6rem;
            border: 1px solid var(--security-primary-dark);
            border-radius: 9px;
            background:
                linear-gradient(
                    135deg,
                    var(--security-primary),
                    var(--security-primary-dark)
                );
            color: #fff;
            cursor: pointer;
            font-size: .61rem;
            font-weight: 810;
            text-decoration: none;
            white-space: nowrap;
            box-shadow:
                0 7px 16px rgba(22, 163, 74, .14);
            transition:
                border-color 150ms ease,
                background 150ms ease,
                color 150ms ease,
                box-shadow 150ms ease,
                transform 150ms ease,
                opacity 150ms ease;
        }

        .btn:hover:not(:disabled),
        .btn:focus-visible:not(:disabled) {
            color: #fff;
            outline: none;
            box-shadow:
                0 10px 21px rgba(22, 163, 74, .19);
            transform: translateY(-1px);
        }

        .btn i {
            font-size: .9rem;
        }

        .btn.secondary {
            border-color: var(--security-border-strong);
            background: var(--security-surface);
            color: var(--security-text);
            box-shadow: none;
        }

        .btn.secondary:hover:not(:disabled),
        .btn.secondary:focus-visible:not(:disabled) {
            border-color: rgba(34, 197, 94, .36);
            background: var(--security-soft);
            color: var(--security-primary-dark);
            box-shadow: none;
        }

        .btn.danger {
            border-color: #fecaca;
            background: var(--security-surface);
            color: var(--security-danger);
            box-shadow: none;
        }

        .btn.danger:hover:not(:disabled),
        .btn.danger:focus-visible:not(:disabled) {
            border-color: var(--security-danger);
            background: var(--security-danger);
            color: #fff;
            box-shadow:
                0 8px 18px rgba(220, 38, 38, .15);
        }

        .btn:disabled {
            cursor: not-allowed;
            opacity: .46;
            transform: none;
            box-shadow: none;
        }

        .btn:focus-visible {
            outline: 3px solid rgba(34, 197, 94, .16);
            outline-offset: 2px;
        }

        .security-dialog {
            position: fixed;
            z-index: 2000;
            inset: 0;
            width: 100%;
            max-width: none;
            height: 100%;
            max-height: none;
            margin: 0;
            padding:
                max(16px, env(safe-area-inset-top))
                max(14px, env(safe-area-inset-right))
                max(16px, env(safe-area-inset-bottom))
                max(14px, env(safe-area-inset-left));
            overflow: auto;
            border: 0;
            background: transparent;
        }

        .security-dialog:not([open]) {
            display: none;
        }

        .security-dialog[open] {
            display: grid;
            place-items: center;
        }

        .security-dialog::backdrop {
            background: rgba(8, 24, 15, .64);
            backdrop-filter: blur(4px);
        }

        .dialog-panel {
            position: relative;
            width: min(100%, 430px);
            max-height: min(86dvh, 620px);
            overflow: auto;
            border: 1px solid rgba(220, 230, 223, .96);
            border-radius: 16px;
            background: var(--security-surface);
            box-shadow: var(--security-shadow-lg);
            animation:
                dialog-enter
                190ms
                cubic-bezier(.2, .8, .2, 1)
                both;
        }

        .dialog-panel::before {
            position: absolute;
            top: 0;
            right: 0;
            left: 0;
            height: 4px;
            background:
                linear-gradient(
                    90deg,
                    var(--security-primary),
                    var(--security-primary-dark)
                );
            content: "";
        }

        .dialog-panel.is-danger::before {
            background:
                linear-gradient(
                    90deg,
                    #ef4444,
                    var(--security-danger-dark)
                );
        }

        .dialog-header {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .62rem;
            align-items: center;
            padding: .82rem;
            border-bottom: 1px solid var(--security-border);
            background:
                linear-gradient(
                    180deg,
                    var(--security-soft),
                    var(--security-surface)
                );
        }

        .dialog-icon {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 11px;
            background: #ecfdf5;
            color: var(--security-primary-dark);
        }

        .dialog-panel.is-danger .dialog-icon {
            background: #fef2f2;
            color: var(--security-danger);
        }

        .dialog-icon i {
            font-size: 1.15rem;
        }

        .dialog-heading {
            min-width: 0;
        }

        .dialog-heading h2 {
            margin: 0;
            color: var(--security-text);
            font-size: .84rem;
            font-weight: 840;
            letter-spacing: -.02em;
        }

        .dialog-heading p {
            margin: .14rem 0 0;
            color: var(--security-faded);
            font-size: .59rem;
            line-height: 1.42;
        }

        .dialog-close {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border: 1px solid var(--security-border);
            border-radius: 9px;
            background: var(--security-surface);
            color: var(--security-secondary);
            cursor: pointer;
        }

        .dialog-close:hover,
        .dialog-close:focus-visible {
            border-color: rgba(34, 197, 94, .38);
            color: var(--security-primary-dark);
            outline: none;
        }

        .dialog-panel.is-danger .dialog-close:hover,
        .dialog-panel.is-danger .dialog-close:focus-visible {
            border-color: #fecaca;
            color: var(--security-danger);
        }

        .dialog-close i {
            font-size: 1rem;
        }

        .dialog-body {
            padding: .82rem;
        }

        .dialog-body label {
            display: block;
            margin-bottom: .34rem;
            color: var(--security-secondary);
            font-size: .63rem;
            font-weight: 760;
        }

        .dialog-field {
            width: 100%;
            min-height: 46px;
            padding: .56rem .68rem;
            border: 1px solid var(--security-border-strong);
            border-radius: 10px;
            outline: none;
            background: var(--security-surface);
            color: var(--security-text);
            font-size: .78rem;
            transition:
                border-color 150ms ease,
                box-shadow 150ms ease;
        }

        .dialog-field:focus {
            border-color: var(--security-primary);
            box-shadow:
                0 0 0 3px rgba(34, 197, 94, .12);
        }

        .dialog-hint {
            display: flex;
            align-items: flex-start;
            gap: .35rem;
            margin-top: .4rem;
            color: var(--security-faded);
            font-size: .57rem;
            line-height: 1.45;
        }

        .dialog-hint i {
            flex: 0 0 auto;
            margin-top: .02rem;
            font-size: .75rem;
            color: var(--security-primary-dark);
        }

        .dialog-warning {
            display: flex;
            align-items: flex-start;
            gap: .42rem;
            padding: .62rem;
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: var(--security-danger-soft);
            color: #991b1b;
            font-size: .62rem;
            line-height: 1.5;
        }

        .dialog-warning i {
            flex: 0 0 auto;
            margin-top: .03rem;
            font-size: .92rem;
        }

        .dialog-actions {
            display: flex;
            justify-content: flex-end;
            gap: .42rem;
            padding: .68rem .82rem .82rem;
            border-top: 1px solid var(--security-border);
            background: var(--security-soft);
        }

        .loading {
            position: fixed;
            z-index: 3000;
            inset: 0;
            display: none;
            place-items: center;
            padding: 1rem;
            background: rgba(244, 248, 246, .82);
            backdrop-filter: blur(5px);
        }

        .loading.show {
            display: grid;
        }

        .loading-card {
            display: grid;
            min-width: 150px;
            place-items: center;
            gap: .48rem;
            padding: .82rem 1rem;
            border: 1px solid var(--security-border);
            border-radius: 13px;
            background: var(--security-surface);
            color: var(--security-secondary);
            font-size: .65rem;
            font-weight: 760;
            box-shadow: var(--security-shadow);
            text-align: center;
        }

        .loading-card i {
            color: var(--security-primary-dark);
            font-size: 1.5rem;
            animation: loading-spin .75s linear infinite;
        }

        @keyframes loading-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes dialog-enter {
            from {
                opacity: 0;
                transform: translateY(8px) scale(.985);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 760px) {
            .security-summary {
                grid-template-columns: 1fr 1fr;
            }

            .summary-card.is-session {
                grid-column: 1 / -1;
            }

            .reauth {
                grid-template-columns: auto minmax(0, 1fr);
            }

            .reauth-actions {
                grid-column: 1 / -1;
                justify-content: stretch;
            }

            .reauth-actions .btn {
                flex: 1 1 180px;
            }
        }

        @media (max-width: 620px) {
            .security-page {
                width: min(100% - 20px, 980px);
                padding-top:
                    max(10px, env(safe-area-inset-top));
            }

            .security-topbar {
                grid-template-columns: auto minmax(0, 1fr);
                padding: .68rem;
                border-radius: 13px;
            }

            .security-state {
                position: absolute;
                top: .64rem;
                right: .64rem;
                width: 34px;
                min-width: 34px;
                height: 34px;
                justify-content: center;
                padding: 0;
                border-radius: 10px;
            }

            .security-state span {
                display: none;
            }

            .security-topbar-copy {
                padding-right: 2.5rem;
            }

            .security-topbar p {
                white-space: normal;
            }

            .security-summary {
                grid-template-columns: 1fr;
            }

            .summary-card.is-session {
                grid-column: auto;
            }

            .section-head {
                min-height: 0;
                align-items: stretch;
                flex-direction: column;
                padding: .62rem;
            }

            .section-head > .btn {
                width: 100%;
            }

            .section-body {
                padding: .5rem;
            }

            .security-row {
                grid-template-columns: auto minmax(0, 1fr);
                align-items: start;
                padding: .58rem;
            }

            .security-row > .btn {
                grid-column: 1 / -1;
                width: 100%;
            }

            .row-copy > span {
                overflow: visible;
                text-overflow: clip;
                white-space: normal;
            }

            .dialog-actions {
                align-items: stretch;
                flex-direction: column-reverse;
            }

            .dialog-actions .btn {
                width: 100%;
            }
        }

        @media (max-width: 390px) {
            .security-page {
                width: min(100% - 16px, 980px);
            }

            .security-back {
                width: 37px;
                height: 37px;
            }

            .security-topbar h1 {
                font-size: .98rem;
            }

            .security-topbar p {
                font-size: .61rem;
            }

            .reauth {
                padding: .62rem;
            }

            .dialog-header,
            .dialog-body {
                padding: .7rem;
            }

            .dialog-actions {
                padding: .62rem .7rem .7rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>
    <div
        class="loading"
        id="loading"
        role="status"
        aria-live="polite"
        aria-hidden="true"
    >
        <div class="loading-card">
            <i class="ph ph-spinner-gap" aria-hidden="true"></i>
            <span id="loading-label">Processando...</span>
        </div>
    </div>

    <main class="security-page">
        <header class="security-topbar">
            <a
                class="security-back"
                href="{{ route('home') }}"
                aria-label="Voltar"
                title="Voltar"
            >
                <i class="ph ph-arrow-left" aria-hidden="true"></i>
            </a>

            <div class="security-topbar-copy">
                <div class="security-kicker">
                    <i class="ph-duotone ph-shield-check" aria-hidden="true"></i>
                    Proteção da conta
                </div>

                <h1>Segurança e acesso</h1>

                <p>
                    Gerencie os métodos vinculados à sua conta global.
                </p>
            </div>

            <span class="security-state">
                <i class="ph ph-lock-key" aria-hidden="true"></i>
                <span>Ambiente protegido</span>
            </span>
        </header>

        <div class="security-content">
            @if(session('error'))
                <div class="status show error" role="alert">
                    <i class="ph ph-warning-circle" aria-hidden="true"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if(session('success'))
                <div class="status show success" role="status">
                    <i class="ph ph-check-circle" aria-hidden="true"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <div
                class="status"
                id="security-status"
                role="status"
                aria-live="polite"
            >
                <i
                    class="ph ph-info"
                    id="security-status-icon"
                    aria-hidden="true"
                ></i>

                <span id="security-status-message"></span>
            </div>

            @if(!$recentlyAuthenticated)
                <section class="reauth">
                    <span class="reauth-icon" aria-hidden="true">
                        <i class="ph-duotone ph-identification-badge"></i>
                    </span>

                    <div class="reauth-copy">
                        <h2>Confirme sua identidade</h2>

                        <p>
                            Uma nova confirmação é necessária antes de alterar
                            seus métodos de acesso.
                        </p>
                    </div>

                    <div class="reauth-actions">
                        @if($activePasskeys->isNotEmpty())
                            <button
                                type="button"
                                class="btn"
                                id="reauth-passkey"
                            >
                                <i class="ph ph-fingerprint" aria-hidden="true"></i>
                                Confirmar com passkey
                            </button>
                        @endif

                        @if($hasGoogleAccount)
                            <a
                                class="btn secondary"
                                href="{{ route('auth.google', [
                                    'intent' => 'reauth',
                                ]) }}"
                            >
                                <i class="ph ph-google-logo" aria-hidden="true"></i>
                                Confirmar com Google
                            </a>
                        @endif
                    </div>
                </section>
            @endif

            <section
                class="security-summary"
                aria-label="Resumo de segurança"
            >
                <article class="summary-card">
                    <span class="summary-icon" aria-hidden="true">
                        <i class="ph-duotone ph-fingerprint"></i>
                    </span>

                    <div class="summary-copy">
                        <span>Passkeys ativas</span>
                        <strong>{{ $activePasskeysCount }}</strong>
                    </div>
                </article>

                <article class="summary-card is-google">
                    <span class="summary-icon" aria-hidden="true">
                        <i class="ph ph-google-logo"></i>
                    </span>

                    <div class="summary-copy">
                        <span>Conta Google</span>
                        <strong>
                            {{ $hasGoogleAccount ? 'Vinculada' : 'Não vinculada' }}
                        </strong>
                    </div>
                </article>

                <article class="summary-card is-session">
                    <span class="summary-icon" aria-hidden="true">
                        <i class="ph-duotone ph-shield-check"></i>
                    </span>

                    <div class="summary-copy">
                        <span>Confirmação recente</span>
                        <strong>
                            {{ $recentlyAuthenticated ? 'Confirmada' : 'Necessária' }}
                        </strong>
                    </div>
                </article>
            </section>

            <section class="security-section">
                <header class="section-head">
                    <div class="section-heading">
                        <span class="section-icon" aria-hidden="true">
                            <i class="ph-duotone ph-fingerprint"></i>
                        </span>

                        <div>
                            <h2>Passkeys</h2>

                            <p>
                                Use biometria, PIN ou uma chave física para
                                acessar sua conta sem depender de senha.
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="btn"
                        id="add-passkey"
                        @disabled(!$recentlyAuthenticated)
                        @if(!$recentlyAuthenticated)
                            title="Confirme sua identidade para adicionar uma passkey"
                        @endif
                    >
                        <i class="ph ph-plus" aria-hidden="true"></i>
                        Adicionar passkey
                    </button>
                </header>

                <div class="section-body">
                    <div class="security-list">
                        @forelse($passkeys as $passkey)
                            @php
                                $isRevoked = filled($passkey->revoked_at);
                                $isExpired = !$isRevoked
                                    && $passkey->expires_at?->isPast();

                                $isActive = !$isRevoked && !$isExpired;

                                $passkeyStatus = $isRevoked
                                    ? 'Revogada'
                                    : ($isExpired ? 'Expirada' : 'Ativa');

                                $passkeyStatusClass = $isRevoked
                                    ? 'is-revoked'
                                    : ($isExpired ? 'is-expired' : '');

                                $passkeyName = $passkey->name ?: 'Passkey';
                            @endphp

                            <article class="security-row">
                                <span class="row-icon" aria-hidden="true">
                                    <i class="ph ph-key"></i>
                                </span>

                                <div class="row-copy">
                                    <div class="row-title-line">
                                        <strong>{{ $passkeyName }}</strong>

                                        <span
                                            class="row-badge {{ $passkeyStatusClass }}"
                                        >
                                            <i
                                                class="ph {{ $isActive
                                                    ? 'ph-check-circle'
                                                    : 'ph-warning-circle' }}"
                                                aria-hidden="true"
                                            ></i>

                                            {{ $passkeyStatus }}
                                        </span>
                                    </div>

                                    <span>
                                        Criada em
                                        {{ $passkey->created_at?->format('d/m/Y H:i') ?? 'data não informada' }}

                                        · Válida até
                                        {{ $passkey->expires_at?->format('d/m/Y H:i') ?? 'sem expiração informada' }}

                                        · Último uso
                                        {{ $passkey->last_used_at?->format('d/m/Y H:i') ?? 'nunca' }}
                                    </span>
                                </div>

                                @if($isActive)
                                    <button
                                        type="button"
                                        class="btn secondary danger revoke-passkey"
                                        data-id="{{ $passkey->id }}"
                                        data-name="{{ $passkeyName }}"
                                    >
                                        <i class="ph ph-trash" aria-hidden="true"></i>
                                        Revogar
                                    </button>
                                @endif
                            </article>
                        @empty
                            <div class="empty">
                                <div>
                                    <span class="empty-icon" aria-hidden="true">
                                        <i class="ph-duotone ph-key"></i>
                                    </span>

                                    <strong>Nenhuma passkey cadastrada</strong>

                                    <p>
                                        Após confirmar sua identidade, cadastre
                                        uma passkey para entrar com biometria ou PIN.
                                    </p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="security-section">
                <header class="section-head">
                    <div class="section-heading">
                        <span
                            class="section-icon is-google"
                            aria-hidden="true"
                        >
                            <i class="ph ph-google-logo"></i>
                        </span>

                        <div>
                            <h2>Conta Google</h2>

                            <p>
                                Identidade vinculada pelo identificador
                                permanente fornecido pelo Google.
                            </p>
                        </div>
                    </div>

                    @if(!$hasGoogleAccount)
                        <a
                            class="btn"
                            href="{{ route('auth.google', [
                                'intent' => 'link',
                            ]) }}"
                        >
                            <i class="ph ph-link-simple" aria-hidden="true"></i>
                            Vincular Google
                        </a>
                    @endif
                </header>

                <div class="section-body">
                    <div class="security-list">
                        @forelse($oauthAccounts as $account)
                            <article class="security-row">
                                <span
                                    class="row-icon is-google"
                                    aria-hidden="true"
                                >
                                    <i class="ph ph-google-logo"></i>
                                </span>

                                <div class="row-copy">
                                    <div class="row-title-line">
                                        <strong>
                                            {{ ucfirst($account->provider) }}
                                        </strong>

                                        <span class="row-badge">
                                            <i
                                                class="ph ph-check-circle"
                                                aria-hidden="true"
                                            ></i>

                                            Vinculada
                                        </span>
                                    </div>

                                    <span>
                                        {{ $account->provider_email }}

                                        · Vinculada em
                                        {{ $account->linked_at?->format('d/m/Y') ?? 'data não informada' }}
                                    </span>
                                </div>
                            </article>
                        @empty
                            <div class="empty">
                                <div>
                                    <span class="empty-icon" aria-hidden="true">
                                        <i class="ph ph-google-logo"></i>
                                    </span>

                                    <strong>Nenhuma conta externa vinculada</strong>

                                    <p>
                                        Vincule sua conta Google para facilitar
                                        o acesso em outros dispositivos.
                                    </p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </main>

    <dialog
        class="security-dialog"
        id="passkey-dialog"
        aria-labelledby="passkey-dialog-title"
    >
        <form
            method="dialog"
            class="dialog-panel"
            id="passkey-dialog-form"
        >
            <header class="dialog-header">
                <span class="dialog-icon" aria-hidden="true">
                    <i class="ph-duotone ph-fingerprint"></i>
                </span>

                <div class="dialog-heading">
                    <h2 id="passkey-dialog-title">Nova passkey</h2>

                    <p>
                        Dê um nome simples para reconhecer este dispositivo.
                    </p>
                </div>

                <button
                    type="submit"
                    class="dialog-close"
                    value="cancel"
                    aria-label="Fechar"
                >
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </header>

            <div class="dialog-body">
                <label for="new-passkey-name">
                    Nome da passkey
                </label>

                <input
                    class="dialog-field"
                    id="new-passkey-name"
                    maxlength="60"
                    autocomplete="off"
                    value="{{ $suggestedPasskeyName }}"
                    placeholder="Ex.: Celular pessoal"
                >

                <div class="dialog-hint">
                    <i class="ph ph-info" aria-hidden="true"></i>

                    <span>
                        Use no máximo três palavras. Este nome serve apenas
                        para identificar a credencial nesta página.
                    </span>
                </div>
            </div>

            <footer class="dialog-actions">
                <button
                    type="submit"
                    class="btn secondary"
                    value="cancel"
                >
                    Cancelar
                </button>

                <button
                    type="button"
                    class="btn"
                    id="confirm-passkey"
                >
                    <i class="ph ph-fingerprint" aria-hidden="true"></i>
                    Criar passkey
                </button>
            </footer>
        </form>
    </dialog>

    <dialog
        class="security-dialog"
        id="revoke-dialog"
        aria-labelledby="revoke-dialog-title"
    >
        <form
            method="dialog"
            class="dialog-panel is-danger"
            id="revoke-dialog-form"
        >
            <header class="dialog-header">
                <span class="dialog-icon" aria-hidden="true">
                    <i class="ph-duotone ph-warning"></i>
                </span>

                <div class="dialog-heading">
                    <h2 id="revoke-dialog-title">Revogar passkey</h2>

                    <p id="revoke-dialog-subtitle">
                        Confirme a remoção desta credencial.
                    </p>
                </div>

                <button
                    type="submit"
                    class="dialog-close"
                    value="cancel"
                    aria-label="Fechar"
                >
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </header>

            <div class="dialog-body">
                <div class="dialog-warning">
                    <i class="ph ph-warning-circle" aria-hidden="true"></i>

                    <span>
                        Esta passkey deixará de funcionar imediatamente.
                        A ação não pode ser desfeita, mas uma nova passkey
                        poderá ser cadastrada depois.
                    </span>
                </div>
            </div>

            <footer class="dialog-actions">
                <button
                    type="submit"
                    class="btn secondary"
                    value="cancel"
                >
                    Manter passkey
                </button>

                <button
                    type="button"
                    class="btn danger"
                    id="confirm-revoke"
                >
                    <i class="ph ph-trash" aria-hidden="true"></i>
                    Revogar passkey
                </button>
            </footer>
        </form>
    </dialog>

    <script>
        (() => {
            const csrf =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.content || '';

            const statusBox =
                document.getElementById('security-status');

            const statusIcon =
                document.getElementById('security-status-icon');

            const statusMessage =
                document.getElementById('security-status-message');

            const passkeyDialog =
                document.getElementById('passkey-dialog');

            const revokeDialog =
                document.getElementById('revoke-dialog');

            const loading =
                document.getElementById('loading');

            const loadingLabel =
                document.getElementById('loading-label');

            const passkeyName =
                document.getElementById('new-passkey-name');

            const addPasskeyButton =
                document.getElementById('add-passkey');

            const confirmPasskeyButton =
                document.getElementById('confirm-passkey');

            const confirmRevokeButton =
                document.getElementById('confirm-revoke');

            const revokeSubtitle =
                document.getElementById('revoke-dialog-subtitle');

            let selectedPasskeyId = null;

            function setStatus(message, type = 'error') {
                const iconClass = {
                    error: 'ph-warning-circle',
                    success: 'ph-check-circle',
                    warning: 'ph-warning',
                    info: 'ph-info',
                }[type] || 'ph-info';

                statusBox.className = `status show ${type}`;
                statusIcon.className = `ph ${iconClass}`;
                statusMessage.textContent =
                    message
                    || 'Não foi possível concluir a operação.';

                statusBox.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            }

            function busy(value, message = 'Processando...') {
                loading.classList.toggle('show', value);
                loading.setAttribute(
                    'aria-hidden',
                    value ? 'false' : 'true'
                );

                loadingLabel.textContent = message;
            }

            function closeDialog(dialog) {
                if (dialog?.open) {
                    dialog.close();
                }
            }

            function passkeysApi() {
                if (!window.SgcPasskeys) {
                    throw new Error(
                        'O recurso de passkeys não está disponível neste navegador.'
                    );
                }

                return window.SgcPasskeys;
            }

            async function readJson(response) {
                const raw = await response.text();

                if (!raw) {
                    return {};
                }

                try {
                    return JSON.parse(raw);
                } catch {
                    return {};
                }
            }

            function normalizePasskeyName() {
                const words = passkeyName.value
                    .trimStart()
                    .split(/\s+/)
                    .filter(Boolean);

                if (words.length > 3) {
                    passkeyName.value =
                        words.slice(0, 3).join(' ');
                }
            }

            passkeyName?.addEventListener(
                'input',
                normalizePasskeyName
            );

            document
                .getElementById('reauth-passkey')
                ?.addEventListener(
                    'click',
                    async () => {
                        busy(
                            true,
                            'Confirmando sua identidade...'
                        );

                        try {
                            await passkeysApi().verify({
                                routes: {
                                    options:
                                        @json(route('security.reauth.passkey.options')),

                                    submit:
                                        @json(route('security.reauth.passkey.store')),
                                },
                            });

                            window.location.reload();
                        } catch (error) {
                            busy(false);
                            setStatus(error.message, 'error');
                        }
                    }
                );

            addPasskeyButton?.addEventListener(
                'click',
                () => {
                    passkeyDialog.showModal();

                    window.requestAnimationFrame(() => {
                        passkeyName.focus();
                        passkeyName.select();
                    });
                }
            );

            confirmPasskeyButton?.addEventListener(
                'click',
                async () => {
                    const name = passkeyName.value.trim();

                    if (!name) {
                        setStatus(
                            'Informe um nome para identificar a passkey.',
                            'warning'
                        );

                        passkeyName.focus();
                        return;
                    }

                    busy(true, 'Criando sua passkey...');

                    try {
                        await passkeysApi().register({
                            name,
                            routes: {
                                options:
                                    @json(route('security.passkeys.options')),

                                submit:
                                    @json(route('security.passkeys.store')),
                            },
                        });

                        window.location.reload();
                    } catch (error) {
                        busy(false);
                        closeDialog(passkeyDialog);
                        setStatus(error.message, 'error');
                    }
                }
            );

            document
                .querySelectorAll('.revoke-passkey')
                .forEach(button => {
                    button.addEventListener(
                        'click',
                        () => {
                            selectedPasskeyId =
                                button.dataset.id || null;

                            const displayName =
                                button.dataset.name || 'esta passkey';

                            revokeSubtitle.textContent =
                                `Você está prestes a revogar “${displayName}”.`;

                            revokeDialog.showModal();
                        }
                    );
                });

            confirmRevokeButton?.addEventListener(
                'click',
                async () => {
                    if (!selectedPasskeyId) {
                        closeDialog(revokeDialog);

                        setStatus(
                            'Não foi possível identificar a passkey.',
                            'error'
                        );

                        return;
                    }

                    busy(true, 'Revogando a passkey...');

                    try {
                        const baseUrl =
                            @json(url('/security/passkeys'))
                                .replace(/\/$/, '');

                        const response = await fetch(
                            `${baseUrl}/${encodeURIComponent(selectedPasskeyId)}`,
                            {
                                method: 'DELETE',
                                credentials: 'same-origin',
                                headers: {
                                    Accept: 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            }
                        );

                        const data = await readJson(response);

                        if (!response.ok) {
                            throw new Error(
                                data.message
                                || 'Não foi possível revogar a passkey.'
                            );
                        }

                        window.location.reload();
                    } catch (error) {
                        busy(false);
                        closeDialog(revokeDialog);
                        setStatus(error.message, 'error');
                    }
                }
            );

            [passkeyDialog, revokeDialog].forEach(dialog => {
                dialog?.addEventListener(
                    'click',
                    event => {
                        if (event.target === dialog) {
                            dialog.close();
                        }
                    }
                );

                dialog?.addEventListener(
                    'close',
                    () => {
                        if (dialog === revokeDialog) {
                            selectedPasskeyId = null;
                        }
                    }
                );
            });
        })();
    </script>
</body>
</html>