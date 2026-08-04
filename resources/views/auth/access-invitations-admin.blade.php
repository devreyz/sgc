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
        Links de acesso - {{ config('app.name', 'ZeCoop SGC') }}
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
        $totalInvitations = $invitations->count();

        $activeInvitations = $invitations
            ->whereIn('status', ['pending', 'claimed'])
            ->count();

        $consumedInvitations = $invitations
            ->whereNotNull('consumed_at')
            ->count();
    @endphp

    <style>
        :root {
            --access-primary: var(--color-primary, #22c55e);
            --access-primary-dark: var(--color-primary-dark, #16a34a);
            --access-primary-deep: var(--color-primary-deep, #15803d);

            --access-surface: var(--color-surface, #ffffff);
            --access-soft: var(--color-surface-soft, #f8faf9);
            --access-muted: var(--color-surface-muted, #eef4f0);

            --access-border: var(--color-border, #dce6df);
            --access-border-strong: var(--color-border-strong, #c8d6cd);

            --access-text: var(--color-text, #102018);
            --access-secondary: var(--color-text-secondary, #52645a);
            --access-faded: var(--color-text-muted, #809087);

            --access-danger: var(--color-danger, #dc2626);
            --access-danger-dark: #b91c1c;
            --access-danger-soft: #fff7f7;

            --access-warning: var(--color-warning, #d97706);
            --access-warning-soft: #fffbeb;

            --access-info: var(--color-info, #0284c7);
            --access-info-soft: #eff6ff;

            --access-shadow-sm:
                0 5px 18px rgba(15, 35, 24, .055);

            --access-shadow:
                0 15px 42px rgba(15, 35, 24, .09);

            --access-shadow-lg:
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
            color: var(--access-text);
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
        input,
        select {
            -webkit-tap-highlight-color: transparent;
        }

        [hidden] {
            display: none !important;
        }

        .access-page {
            position: relative;
            isolation: isolate;
            width: min(100% - 28px, 1020px);
            margin: 0 auto;
            padding:
                max(22px, env(safe-area-inset-top))
                0
                max(28px, env(safe-area-inset-bottom));
        }

        .access-page::before {
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

        .access-header {
            position: relative;
            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .78rem;
            align-items: center;
            padding: .8rem .85rem;
            overflow: hidden;
            border: 1px solid var(--access-border);
            border-left: 4px solid var(--access-primary-dark);
            border-radius: 15px;
            background:
                linear-gradient(
                    90deg,
                    rgba(236, 253, 245, .80),
                    rgba(255, 255, 255, .985) 43%
                ),
                var(--access-surface);
            box-shadow: var(--access-shadow-sm);
        }

        .access-header-icon {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border: 1px solid rgba(34, 197, 94, .16);
            border-radius: 11px;
            background: #ecfdf5;
            color: var(--access-primary-dark);
        }

        .access-header-icon i {
            font-size: 1.15rem;
        }

        .access-header-copy {
            min-width: 0;
        }

        .access-kicker {
            display: flex;
            align-items: center;
            gap: .34rem;
            color: var(--access-primary-dark);
            font-size: .6rem;
            font-weight: 820;
            letter-spacing: .065em;
            text-transform: uppercase;
        }

        .access-kicker i {
            font-size: .84rem;
        }

        .access-header h1 {
            margin: .13rem 0 0;
            overflow: hidden;
            color: var(--access-text);
            font-size: clamp(1.02rem, 2vw, 1.28rem);
            font-weight: 870;
            letter-spacing: -.035em;
            line-height: 1.2;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .access-header p {
            margin: .23rem 0 0;
            overflow: hidden;
            color: var(--access-secondary);
            font-size: .66rem;
            line-height: 1.42;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .access-header-state {
            display: inline-flex;
            min-height: 34px;
            align-items: center;
            gap: .34rem;
            padding: .34rem .5rem;
            border: 1px solid rgba(34, 197, 94, .19);
            border-radius: 999px;
            background: #ecfdf5;
            color: var(--access-primary-deep);
            font-size: .56rem;
            font-weight: 810;
            white-space: nowrap;
        }

        .access-header-state i {
            font-size: .82rem;
        }

        .access-content {
            display: grid;
            gap: .75rem;
            margin-top: .75rem;
        }

        .access-summary {
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
            border: 1px solid var(--access-border);
            border-radius: 13px;
            background: var(--access-surface);
            box-shadow: var(--access-shadow-sm);
        }

        .summary-icon {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 11px;
            background: var(--access-muted);
            color: var(--access-primary-dark);
        }

        .summary-icon i {
            font-size: 1.12rem;
        }

        .summary-card.is-active .summary-icon {
            background: #ecfdf5;
            color: var(--access-primary-dark);
        }

        .summary-card.is-used .summary-icon {
            background: var(--access-info-soft);
            color: var(--access-info);
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
            color: var(--access-faded);
            font-size: .57rem;
            font-weight: 680;
        }

        .summary-copy strong {
            margin-top: .1rem;
            color: var(--access-text);
            font-size: .74rem;
            font-weight: 840;
        }

        .message {
            display: none;
            align-items: flex-start;
            gap: .48rem;
            padding: .65rem .7rem;
            border: 1px solid var(--access-border);
            border-radius: 11px;
            background: var(--access-soft);
            color: var(--access-secondary);
            font-size: .65rem;
            font-weight: 640;
            line-height: 1.5;
            box-shadow: var(--access-shadow-sm);
        }

        .message.show {
            display: flex;
        }

        .message i {
            flex: 0 0 auto;
            margin-top: .04rem;
            font-size: 1rem;
        }

        .message.error {
            border-color: #fecaca;
            background: var(--access-danger-soft);
            color: #991b1b;
        }

        .message.success {
            border-color: #bbf7d0;
            background: #ecfdf5;
            color: #047857;
        }

        .message.warning {
            border-color: #fde68a;
            background: var(--access-warning-soft);
            color: #92400e;
        }

        .access-section {
            overflow: hidden;
            border: 1px solid var(--access-border);
            border-radius: 15px;
            background: var(--access-surface);
            box-shadow: var(--access-shadow);
        }

        .access-section-head {
            display: flex;
            min-height: 66px;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            padding: .68rem .75rem;
            border-bottom: 1px solid var(--access-border);
            background:
                linear-gradient(
                    180deg,
                    var(--access-soft),
                    var(--access-surface)
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
            color: var(--access-primary-dark);
        }

        .section-icon i {
            font-size: 1.13rem;
        }

        .access-section-head h2 {
            margin: 0;
            color: var(--access-text);
            font-size: .86rem;
            font-weight: 840;
            letter-spacing: -.02em;
        }

        .access-section-head p {
            margin: .14rem 0 0;
            color: var(--access-faded);
            font-size: .59rem;
            line-height: 1.42;
        }

        .create-panel {
            display: flex;
            min-width: 0;
            align-items: flex-end;
            gap: .42rem;
        }

        .field {
            min-width: 128px;
        }

        .field label {
            display: block;
            margin-bottom: .27rem;
            color: var(--access-secondary);
            font-size: .57rem;
            font-weight: 760;
        }

        .field select {
            width: 100%;
            min-height: 38px;
            padding: .42rem 2rem .42rem .58rem;
            border: 1px solid var(--access-border-strong);
            border-radius: 9px;
            outline: none;
            background-color: var(--access-surface);
            color: var(--access-text);
            font-size: .64rem;
            font-weight: 690;
            cursor: pointer;
            transition:
                border-color 150ms ease,
                box-shadow 150ms ease;
        }

        .field select:focus {
            border-color: var(--access-primary);
            box-shadow:
                0 0 0 3px rgba(34, 197, 94, .12);
        }

        .access-list {
            display: grid;
            gap: .42rem;
            padding: .58rem;
        }

        .access-row {
            --row-tone: var(--access-primary-dark);
            --row-soft: #ecfdf5;

            display: grid;
            min-width: 0;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .62rem;
            align-items: center;
            padding: .64rem;
            border: 1px solid transparent;
            border-radius: 11px;
            background: var(--access-surface);
            transition:
                border-color 150ms ease,
                background 150ms ease,
                box-shadow 150ms ease;
        }

        .access-row:hover {
            border-color: var(--access-border);
            background: var(--access-soft);
            box-shadow: 0 5px 15px rgba(15, 35, 24, .04);
        }

        .access-row.status-consumed,
        .access-row.status-used {
            --row-tone: var(--access-info);
            --row-soft: var(--access-info-soft);
        }

        .access-row.status-revoked,
        .access-row.status-expired {
            --row-tone: var(--access-danger);
            --row-soft: #fef2f2;
        }

        .access-row.status-claimed {
            --row-tone: var(--access-warning);
            --row-soft: var(--access-warning-soft);
        }

        .row-icon {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 10px;
            background: var(--row-soft);
            color: var(--row-tone);
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
            color: var(--access-text);
            font-size: .72rem;
            font-weight: 810;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .row-copy > span {
            display: block;
            margin-top: .15rem;
            overflow: hidden;
            color: var(--access-faded);
            font-size: .59rem;
            line-height: 1.45;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-flex;
            min-height: 21px;
            align-items: center;
            gap: .22rem;
            padding: .16rem .32rem;
            border-radius: 999px;
            background: var(--row-soft);
            color: var(--row-tone);
            font-size: .49rem;
            font-weight: 830;
            white-space: nowrap;
            text-transform: uppercase;
        }

        .status-badge i {
            font-size: .66rem;
        }

        .btn {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            gap: .34rem;
            padding: .42rem .6rem;
            border: 1px solid var(--access-primary-dark);
            border-radius: 9px;
            background:
                linear-gradient(
                    135deg,
                    var(--access-primary),
                    var(--access-primary-dark)
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
            border-color: var(--access-border-strong);
            background: var(--access-surface);
            color: var(--access-text);
            box-shadow: none;
        }

        .btn.secondary:hover:not(:disabled),
        .btn.secondary:focus-visible:not(:disabled) {
            border-color: rgba(34, 197, 94, .36);
            background: var(--access-soft);
            color: var(--access-primary-dark);
            box-shadow: none;
        }

        .btn.danger {
            border-color: #fecaca;
            background: var(--access-surface);
            color: var(--access-danger);
            box-shadow: none;
        }

        .btn.danger:hover:not(:disabled),
        .btn.danger:focus-visible:not(:disabled) {
            border-color: var(--access-danger);
            background: var(--access-danger);
            color: #fff;
            box-shadow:
                0 8px 18px rgba(220, 38, 38, .15);
        }

        .btn:disabled {
            cursor: not-allowed;
            opacity: .5;
            transform: none;
            box-shadow: none;
        }

        .btn:focus-visible {
            outline: 3px solid rgba(34, 197, 94, .16);
            outline-offset: 2px;
        }

        .empty {
            display: grid;
            min-height: 170px;
            place-items: center;
            padding: 1rem;
            border: 1px dashed var(--access-border-strong);
            border-radius: 11px;
            background: var(--access-soft);
            color: var(--access-secondary);
            text-align: center;
        }

        .empty-icon {
            display: grid;
            width: 48px;
            height: 48px;
            place-items: center;
            margin: 0 auto .48rem;
            border-radius: 13px;
            background: var(--access-muted);
            color: var(--access-faded);
        }

        .empty-icon i {
            font-size: 1.35rem;
        }

        .empty strong {
            display: block;
            color: var(--access-text);
            font-size: .72rem;
            font-weight: 820;
        }

        .empty p {
            max-width: 360px;
            margin: .16rem auto 0;
            color: var(--access-faded);
            font-size: .59rem;
            line-height: 1.45;
        }

        .access-dialog {
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

        .access-dialog:not([open]) {
            display: none;
        }

        .access-dialog[open] {
            display: grid;
            place-items: center;
        }

        .access-dialog::backdrop {
            background: rgba(8, 24, 15, .66);
            backdrop-filter: blur(4px);
        }

        .dialog-panel {
            position: relative;
            width: min(100%, 530px);
            max-height: min(88dvh, 720px);
            overflow: auto;
            border: 1px solid rgba(220, 230, 223, .96);
            border-radius: 16px;
            background: var(--access-surface);
            box-shadow: var(--access-shadow-lg);
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
                    var(--access-primary),
                    var(--access-primary-dark)
                );
            content: "";
        }

        .dialog-panel.is-danger::before {
            background:
                linear-gradient(
                    90deg,
                    #ef4444,
                    var(--access-danger-dark)
                );
        }

        .dialog-header {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            gap: .62rem;
            align-items: center;
            padding: .82rem;
            border-bottom: 1px solid var(--access-border);
            background:
                linear-gradient(
                    180deg,
                    var(--access-soft),
                    var(--access-surface)
                );
        }

        .dialog-icon {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border-radius: 11px;
            background: #ecfdf5;
            color: var(--access-primary-dark);
        }

        .dialog-panel.is-danger .dialog-icon {
            background: #fef2f2;
            color: var(--access-danger);
        }

        .dialog-icon i {
            font-size: 1.15rem;
        }

        .dialog-heading {
            min-width: 0;
        }

        .dialog-heading h2 {
            margin: 0;
            color: var(--access-text);
            font-size: .84rem;
            font-weight: 840;
            letter-spacing: -.02em;
        }

        .dialog-heading p {
            margin: .14rem 0 0;
            color: var(--access-faded);
            font-size: .59rem;
            line-height: 1.42;
        }

        .dialog-close {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border: 1px solid var(--access-border);
            border-radius: 9px;
            background: var(--access-surface);
            color: var(--access-secondary);
            cursor: pointer;
        }

        .dialog-close:hover,
        .dialog-close:focus-visible {
            border-color: rgba(34, 197, 94, .38);
            color: var(--access-primary-dark);
            outline: none;
        }

        .dialog-panel.is-danger .dialog-close:hover,
        .dialog-panel.is-danger .dialog-close:focus-visible {
            border-color: #fecaca;
            color: var(--access-danger);
        }

        .dialog-close i {
            font-size: 1rem;
        }

        .dialog-body {
            display: grid;
            gap: .62rem;
            padding: .82rem;
        }

        .dialog-notice {
            display: flex;
            align-items: flex-start;
            gap: .42rem;
            padding: .62rem;
            border: 1px solid #fde68a;
            border-radius: 10px;
            background: var(--access-warning-soft);
            color: #92400e;
            font-size: .62rem;
            line-height: 1.5;
        }

        .dialog-notice i {
            flex: 0 0 auto;
            margin-top: .03rem;
            font-size: .92rem;
        }

        .dialog-warning {
            display: flex;
            align-items: flex-start;
            gap: .42rem;
            padding: .62rem;
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: var(--access-danger-soft);
            color: #991b1b;
            font-size: .62rem;
            line-height: 1.5;
        }

        .dialog-warning i {
            flex: 0 0 auto;
            margin-top: .03rem;
            font-size: .92rem;
        }

        .secret-card {
            display: grid;
            gap: .38rem;
            padding: .62rem;
            border: 1px solid var(--access-border);
            border-radius: 11px;
            background: var(--access-soft);
        }

        .secret-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .secret-card-head strong {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            color: var(--access-text);
            font-size: .62rem;
            font-weight: 810;
        }

        .secret-card-head i {
            color: var(--access-primary-dark);
            font-size: .85rem;
        }

        .secret-card code {
            display: block;
            max-height: 112px;
            overflow: auto;
            padding: .52rem;
            border: 1px solid var(--access-border);
            border-radius: 9px;
            background: var(--access-surface);
            color: var(--access-text);
            font-family:
                ui-monospace,
                SFMono-Regular,
                Menlo,
                Monaco,
                Consolas,
                monospace;
            font-size: .65rem;
            line-height: 1.5;
            overflow-wrap: anywhere;
            user-select: all;
        }

        .copy-button {
            display: inline-flex;
            min-height: 28px;
            align-items: center;
            justify-content: center;
            gap: .25rem;
            padding: .28rem .4rem;
            border: 1px solid var(--access-border);
            border-radius: 8px;
            background: var(--access-surface);
            color: var(--access-secondary);
            cursor: pointer;
            font-size: .54rem;
            font-weight: 760;
        }

        .copy-button:hover,
        .copy-button:focus-visible {
            border-color: rgba(34, 197, 94, .38);
            background: #ecfdf5;
            color: var(--access-primary-dark);
            outline: none;
        }

        .copy-button i {
            font-size: .75rem;
        }

        .dialog-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .42rem;
            padding: .68rem .82rem .82rem;
            border-top: 1px solid var(--access-border);
            background: var(--access-soft);
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
            min-width: 160px;
            place-items: center;
            gap: .48rem;
            padding: .82rem 1rem;
            border: 1px solid var(--access-border);
            border-radius: 13px;
            background: var(--access-surface);
            color: var(--access-secondary);
            font-size: .65rem;
            font-weight: 760;
            box-shadow: var(--access-shadow);
            text-align: center;
        }

        .loading-card i {
            color: var(--access-primary-dark);
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

        @media (max-width: 820px) {
            .access-summary {
                grid-template-columns: 1fr 1fr;
            }

            .summary-card:last-child {
                grid-column: 1 / -1;
            }

            .access-section-head {
                align-items: stretch;
                flex-direction: column;
            }

            .create-panel {
                width: 100%;
            }

            .field {
                flex: 1;
            }
        }

        @media (max-width: 620px) {
            .access-page {
                width: min(100% - 20px, 1020px);
                padding-top:
                    max(10px, env(safe-area-inset-top));
            }

            .access-header {
                grid-template-columns: auto minmax(0, 1fr);
                padding: .68rem;
                border-radius: 13px;
            }

            .access-header-state {
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

            .access-header-state span {
                display: none;
            }

            .access-header-copy {
                padding-right: 2.5rem;
            }

            .access-header p {
                white-space: normal;
            }

            .access-summary {
                grid-template-columns: 1fr;
            }

            .summary-card:last-child {
                grid-column: auto;
            }

            .create-panel {
                align-items: stretch;
                flex-direction: column;
            }

            .field {
                width: 100%;
            }

            .create-panel .btn {
                width: 100%;
            }

            .access-list {
                padding: .5rem;
            }

            .access-row {
                grid-template-columns: auto minmax(0, 1fr);
                align-items: start;
                padding: .58rem;
            }

            .access-row > .btn {
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
            .access-page {
                width: min(100% - 16px, 1020px);
            }

            .access-header-icon {
                width: 37px;
                height: 37px;
            }

            .access-header h1 {
                font-size: .98rem;
            }

            .access-header p {
                font-size: .61rem;
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

    <main class="access-page">
        <header class="access-header">
            <span class="access-header-icon" aria-hidden="true">
                <i class="ph-duotone ph-link"></i>
            </span>

            <div class="access-header-copy">
                <div class="access-kicker">
                    <i class="ph-duotone ph-shield-check" aria-hidden="true"></i>
                    Convites protegidos
                </div>

                <h1>Links de acesso</h1>

                <p>{{ $targetLabel }}</p>
            </div>

            <span class="access-header-state">
                <i class="ph ph-lock-key" aria-hidden="true"></i>
                <span>Dados protegidos</span>
            </span>
        </header>

        <div class="access-content">
            <div
                class="message"
                id="message"
                role="status"
                aria-live="polite"
            >
                <i
                    class="ph ph-info"
                    id="message-icon"
                    aria-hidden="true"
                ></i>

                <span id="message-text"></span>
            </div>

            <section
                class="access-summary"
                aria-label="Resumo dos convites"
            >
                <article class="summary-card">
                    <span class="summary-icon" aria-hidden="true">
                        <i class="ph-duotone ph-files"></i>
                    </span>

                    <div class="summary-copy">
                        <span>Total emitido</span>
                        <strong>{{ $totalInvitations }}</strong>
                    </div>
                </article>

                <article class="summary-card is-active">
                    <span class="summary-icon" aria-hidden="true">
                        <i class="ph-duotone ph-clock-countdown"></i>
                    </span>

                    <div class="summary-copy">
                        <span>Ativos</span>
                        <strong>{{ $activeInvitations }}</strong>
                    </div>
                </article>

                <article class="summary-card is-used">
                    <span class="summary-icon" aria-hidden="true">
                        <i class="ph-duotone ph-check-circle"></i>
                    </span>

                    <div class="summary-copy">
                        <span>Consumidos</span>
                        <strong>{{ $consumedInvitations }}</strong>
                    </div>
                </article>
            </section>

            <section class="access-section">
                <header class="access-section-head">
                    <div class="section-heading">
                        <span class="section-icon" aria-hidden="true">
                            <i class="ph-duotone ph-user-plus"></i>
                        </span>

                        <div>
                            <h2>Convites emitidos</h2>

                            <p>
                                Gere um link temporário e acompanhe sua utilização.
                            </p>
                        </div>
                    </div>

                    <div class="create-panel">
                        <div class="field">
                            <label for="invite-ttl">Validade</label>

                            <select id="invite-ttl">
                                <option value="24" selected>
                                    24 horas
                                </option>

                                <option value="36">
                                    36 horas
                                </option>

                                <option value="48">
                                    48 horas
                                </option>
                            </select>
                        </div>

                        <button
                            type="button"
                            class="btn"
                            id="new-invite"
                        >
                            <i class="ph ph-plus" aria-hidden="true"></i>
                            Gerar link
                        </button>
                    </div>
                </header>

                <div class="access-list">
                    @forelse($invitations as $invitation)
                        @php
                            $status = strtolower(
                                (string) $invitation->status
                            );

                            $statusLabel = match ($status) {
                                'pending' => 'Pendente',
                                'claimed' => 'Reivindicado',
                                'consumed' => 'Consumido',
                                'used' => 'Utilizado',
                                'revoked' => 'Revogado',
                                'expired' => 'Expirado',
                                default => ucfirst($status),
                            };

                            $statusIcon = match ($status) {
                                'pending' => 'ph-clock-countdown',
                                'claimed' => 'ph-user-check',
                                'consumed', 'used' => 'ph-check-circle',
                                'revoked' => 'ph-prohibit',
                                'expired' => 'ph-hourglass-high',
                                default => 'ph-link',
                            };

                            $canRevoke = in_array(
                                $status,
                                ['pending', 'claimed'],
                                true
                            );
                        @endphp

                        <article
                            class="access-row status-{{ $status }}"
                            data-invitation="{{ $invitation->id }}"
                        >
                            <span class="row-icon" aria-hidden="true">
                                <i class="ph {{ $statusIcon }}"></i>
                            </span>

                            <div class="row-copy">
                                <div class="row-title-line">
                                    <strong>
                                        Convite de
                                        {{ $invitation->created_at->format('d/m/Y H:i') }}
                                    </strong>

                                    <span class="status-badge">
                                        <i
                                            class="ph {{ $statusIcon }}"
                                            aria-hidden="true"
                                        ></i>

                                        {{ $statusLabel }}
                                    </span>
                                </div>

                                <span>
                                    Criado por
                                    {{ $issuerNames[$invitation->issued_by_user_id]
                                        ?? 'Membro não identificado' }}

                                    · Expira em
                                    {{ $invitation->expires_at->format('d/m/Y H:i') }}

                                    @if($invitation->consumed_at)
                                        · Consumido em
                                        {{ $invitation->consumed_at->format('d/m/Y H:i') }}
                                    @endif
                                </span>
                            </div>

                            @if($canRevoke)
                                <button
                                    type="button"
                                    class="btn secondary danger revoke"
                                    data-id="{{ $invitation->id }}"
                                    data-label="Convite de {{ $invitation->created_at->format('d/m/Y H:i') }}"
                                >
                                    <i class="ph ph-prohibit" aria-hidden="true"></i>
                                    Revogar
                                </button>
                            @endif
                        </article>
                    @empty
                        <div class="empty">
                            <div>
                                <span class="empty-icon" aria-hidden="true">
                                    <i class="ph-duotone ph-link-break"></i>
                                </span>

                                <strong>Nenhum convite emitido</strong>

                                <p>
                                    Selecione a validade e gere um link temporário
                                    para iniciar o acesso.
                                </p>
                            </div>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </main>

    <dialog
        class="access-dialog"
        id="invite-modal"
        aria-labelledby="invite-modal-title"
    >
        <div class="dialog-panel">
            <header class="dialog-header">
                <span class="dialog-icon" aria-hidden="true">
                    <i class="ph-duotone ph-link-simple"></i>
                </span>

                <div class="dialog-heading">
                    <h2 id="invite-modal-title">Convite criado</h2>

                    <p>
                        Copie e envie as duas informações com segurança.
                    </p>
                </div>

                <button
                    type="button"
                    class="dialog-close"
                    id="invite-modal-close"
                    aria-label="Fechar"
                >
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </header>

            <div class="dialog-body">
                <div class="dialog-notice">
                    <i class="ph ph-warning-circle" aria-hidden="true"></i>

                    <span>
                        Envie o link e o código por canais diferentes.
                        Por segurança, estes dados não poderão ser exibidos
                        novamente após fechar esta janela.
                    </span>
                </div>

                <section class="secret-card">
                    <header class="secret-card-head">
                        <strong>
                            <i class="ph ph-link" aria-hidden="true"></i>
                            Link de acesso
                        </strong>

                        <button
                            type="button"
                            class="copy-button"
                            id="copy-link"
                        >
                            <i class="ph ph-copy" aria-hidden="true"></i>
                            Copiar
                        </button>
                    </header>

                    <code id="invite-link"></code>
                </section>

                <section class="secret-card">
                    <header class="secret-card-head">
                        <strong>
                            <i class="ph ph-password" aria-hidden="true"></i>
                            Código de confirmação
                        </strong>

                        <button
                            type="button"
                            class="copy-button"
                            id="copy-code"
                        >
                            <i class="ph ph-copy" aria-hidden="true"></i>
                            Copiar
                        </button>
                    </header>

                    <code id="invite-code"></code>
                </section>
            </div>

            <footer class="dialog-actions">
                <button
                    type="button"
                    class="btn secondary"
                    id="share-link"
                >
                    <i class="ph ph-share-network" aria-hidden="true"></i>
                    Enviar link
                </button>

                <button
                    type="button"
                    class="btn"
                    id="close-modal"
                >
                    <i class="ph ph-check" aria-hidden="true"></i>
                    Concluir
                </button>
            </footer>
        </div>
    </dialog>

    <dialog
        class="access-dialog"
        id="revoke-modal"
        aria-labelledby="revoke-modal-title"
    >
        <div class="dialog-panel is-danger">
            <header class="dialog-header">
                <span class="dialog-icon" aria-hidden="true">
                    <i class="ph-duotone ph-warning"></i>
                </span>

                <div class="dialog-heading">
                    <h2 id="revoke-modal-title">Revogar convite</h2>

                    <p id="revoke-modal-subtitle">
                        Confirme a revogação deste link de acesso.
                    </p>
                </div>

                <button
                    type="button"
                    class="dialog-close"
                    id="revoke-modal-close"
                    aria-label="Fechar"
                >
                    <i class="ph ph-x" aria-hidden="true"></i>
                </button>
            </header>

            <div class="dialog-body">
                <div class="dialog-warning">
                    <i class="ph ph-warning-circle" aria-hidden="true"></i>

                    <span>
                        O link deixará de funcionar imediatamente.
                        Esta ação não pode ser desfeita.
                    </span>
                </div>
            </div>

            <footer class="dialog-actions">
                <button
                    type="button"
                    class="btn secondary"
                    id="cancel-revoke"
                >
                    Manter convite
                </button>

                <button
                    type="button"
                    class="btn danger"
                    id="confirm-revoke"
                >
                    <i class="ph ph-prohibit" aria-hidden="true"></i>
                    Revogar convite
                </button>
            </footer>
        </div>
    </dialog>

    <script>
        (() => {
            const csrf =
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.content || '';

            const inviteModal =
                document.getElementById('invite-modal');

            const revokeModal =
                document.getElementById('revoke-modal');

            const loading =
                document.getElementById('loading');

            const loadingLabel =
                document.getElementById('loading-label');

            const message =
                document.getElementById('message');

            const messageIcon =
                document.getElementById('message-icon');

            const messageText =
                document.getElementById('message-text');

            const inviteLink =
                document.getElementById('invite-link');

            const inviteCode =
                document.getElementById('invite-code');

            const revokeSubtitle =
                document.getElementById('revoke-modal-subtitle');

            let currentInvitationId = null;
            let selectedInvitationId = null;

            const invitationUrl = id =>
                @json($sentUrlTemplate)
                    .replace('__ID__', id);

            function busy(value, label = 'Processando...') {
                loading.classList.toggle('show', value);
                loading.setAttribute(
                    'aria-hidden',
                    value ? 'false' : 'true'
                );

                loadingLabel.textContent = label;
            }

            function showMessage(
                text,
                type = 'error'
            ) {
                const iconClass = {
                    error: 'ph-warning-circle',
                    success: 'ph-check-circle',
                    warning: 'ph-warning',
                    info: 'ph-info',
                }[type] || 'ph-info';

                message.className = `message show ${type}`;
                messageIcon.className = `ph ${iconClass}`;
                messageText.textContent =
                    text
                    || 'Não foi possível concluir a operação.';

                message.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                });
            }

            function clearMessage() {
                message.className = 'message';
                messageText.textContent = '';
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

            async function copyText(
                value,
                successMessage
            ) {
                if (!value) {
                    throw new Error(
                        'Não há conteúdo disponível para copiar.'
                    );
                }

                if (
                    navigator.clipboard
                    && window.isSecureContext
                ) {
                    await navigator.clipboard.writeText(value);
                } else {
                    const textarea =
                        document.createElement('textarea');

                    textarea.value = value;
                    textarea.setAttribute('readonly', '');
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';

                    document.body.appendChild(textarea);
                    textarea.select();

                    const copied =
                        document.execCommand('copy');

                    textarea.remove();

                    if (!copied) {
                        throw new Error(
                            'O navegador não permitiu copiar automaticamente.'
                        );
                    }
                }

                showMessage(successMessage, 'success');
            }

            function closeInviteAndReload() {
                if (inviteModal.open) {
                    inviteModal.close();
                }

                window.location.reload();
            }

            document
                .getElementById('new-invite')
                .addEventListener(
                    'click',
                    async () => {
                        busy(true, 'Gerando link de acesso...');
                        clearMessage();

                        try {
                            const response = await fetch(
                                @json($storeUrl),
                                {
                                    method: 'POST',
                                    credentials: 'same-origin',
                                    headers: {
                                        Accept: 'application/json',
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': csrf,
                                        'X-Requested-With': 'XMLHttpRequest',
                                    },
                                    body: JSON.stringify({
                                        expires_in_hours: Number(
                                            document
                                                .getElementById('invite-ttl')
                                                .value
                                        ),
                                    }),
                                }
                            );

                            const data = await readJson(response);

                            if (!response.ok) {
                                const firstValidationError =
                                    Object
                                        .values(data.errors || {})
                                        .flat()
                                        .find(Boolean);

                                throw new Error(
                                    data.message
                                    || firstValidationError
                                    || 'Não foi possível criar o convite.'
                                );
                            }

                            if (
                                !data.id
                                || !data.link
                                || !data.code
                            ) {
                                throw new Error(
                                    'O servidor não retornou todos os dados do convite.'
                                );
                            }

                            currentInvitationId = data.id;
                            inviteLink.textContent = data.link;
                            inviteCode.textContent = data.code;

                            inviteModal.showModal();
                        } catch (error) {
                            showMessage(error.message, 'error');
                        } finally {
                            busy(false);
                        }
                    }
                );

            document
                .getElementById('copy-link')
                .addEventListener(
                    'click',
                    async () => {
                        try {
                            await copyText(
                                inviteLink.textContent.trim(),
                                'Link copiado.'
                            );
                        } catch (error) {
                            showMessage(error.message, 'error');
                        }
                    }
                );

            document
                .getElementById('copy-code')
                .addEventListener(
                    'click',
                    async () => {
                        try {
                            await copyText(
                                inviteCode.textContent.trim(),
                                'Código copiado.'
                            );
                        } catch (error) {
                            showMessage(error.message, 'error');
                        }
                    }
                );

            document
                .getElementById('share-link')
                .addEventListener(
                    'click',
                    async () => {
                        const link =
                            inviteLink.textContent.trim();

                        try {
                            if (navigator.share) {
                                await navigator.share({
                                    title:
                                        'Link de acesso ao SGC',

                                    text:
                                        'Use este link para iniciar seu acesso. '
                                        + 'O código será enviado separadamente.',

                                    url: link,
                                });
                            } else {
                                await copyText(
                                    link,
                                    'Compartilhamento indisponível. O link foi copiado.'
                                );
                            }

                            if (currentInvitationId) {
                                const response = await fetch(
                                    invitationUrl(
                                        currentInvitationId
                                    ),
                                    {
                                        method: 'POST',
                                        credentials: 'same-origin',
                                        headers: {
                                            Accept: 'application/json',
                                            'X-CSRF-TOKEN': csrf,
                                            'X-Requested-With': 'XMLHttpRequest',
                                        },
                                    }
                                );

                                if (!response.ok) {
                                    /*
                                     * O compartilhamento já ocorreu.
                                     * A falha ao registrar o envio não deve
                                     * apagar os dados exibidos ao usuário.
                                     */
                                }
                            }
                        } catch (error) {
                            if (error.name !== 'AbortError') {
                                showMessage(
                                    'Não foi possível compartilhar o link.',
                                    'error'
                                );
                            }
                        }
                    }
                );

            document
                .getElementById('close-modal')
                .addEventListener(
                    'click',
                    closeInviteAndReload
                );

            document
                .getElementById('invite-modal-close')
                .addEventListener(
                    'click',
                    closeInviteAndReload
                );

            document
                .querySelectorAll('.revoke')
                .forEach(button => {
                    button.addEventListener(
                        'click',
                        () => {
                            selectedInvitationId =
                                button.dataset.id || null;

                            const label =
                                button.dataset.label
                                || 'este convite';

                            revokeSubtitle.textContent =
                                `Você está prestes a revogar ${label}.`;

                            revokeModal.showModal();
                        }
                    );
                });

            document
                .getElementById('revoke-modal-close')
                .addEventListener(
                    'click',
                    () => revokeModal.close()
                );

            document
                .getElementById('cancel-revoke')
                .addEventListener(
                    'click',
                    () => revokeModal.close()
                );

            document
                .getElementById('confirm-revoke')
                .addEventListener(
                    'click',
                    async () => {
                        if (!selectedInvitationId) {
                            revokeModal.close();

                            showMessage(
                                'Não foi possível identificar o convite.',
                                'error'
                            );

                            return;
                        }

                        busy(true, 'Revogando convite...');

                        const url =
                            @json($revokeUrlTemplate)
                                .replace(
                                    '__ID__',
                                    selectedInvitationId
                                );

                        try {
                            const response = await fetch(
                                url,
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
                                    || 'Não foi possível revogar o convite.'
                                );
                            }

                            window.location.reload();
                        } catch (error) {
                            busy(false);
                            revokeModal.close();
                            showMessage(error.message, 'error');
                        }
                    }
                );

            revokeModal.addEventListener(
                'close',
                () => {
                    selectedInvitationId = null;
                }
            );

            inviteModal.addEventListener(
                'cancel',
                event => {
                    event.preventDefault();
                    closeInviteAndReload();
                }
            );

            revokeModal.addEventListener(
                'cancel',
                event => {
                    event.preventDefault();
                    revokeModal.close();
                }
            );
        })();
    </script>
</body>
</html>