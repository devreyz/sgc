<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        {{ $documentView
            ? 'Comprovante '.$documentView['reference']
            : 'Comprovante indisponível' }}
    </title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css"
    >

    <style>
        :root {
            color-scheme: light;

            --green: #1f8450;
            --green-strong: #17683f;
            --green-soft: #edf8f2;

            --blue: #3975bf;
            --blue-soft: #eef4fb;

            --amber: #a96f17;
            --amber-soft: #fff7e8;

            --red: #b94a48;
            --red-soft: #fff0ef;

            --violet: #7857a6;
            --violet-soft: #f5f1fa;

            --text: #18231d;
            --text-2: #526159;
            --muted: #7f8c85;

            --border: #dce5df;
            --border-soft: #eaf0ec;

            --surface: #ffffff;
            --surface-soft: #f8faf9;

            --page:
                linear-gradient(
                    180deg,
                    #eef6f1 0,
                    #f6f8f7 260px,
                    #f7f9f8 100%
                );

            --hero:
                linear-gradient(
                    135deg,
                    #ffffff 0%,
                    #f4faf6 55%,
                    #eef5f1 100%
                );
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: #f7f9f8;
        }

        body {
            min-height: 100dvh;
            margin: 0;
            background: var(--page);
            color: var(--text);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        a {
            color: inherit;
        }

        .page {
            width: min(100% - 1.5rem, 920px);
            margin: 0 auto;
            padding:
                max(1rem, env(safe-area-inset-top))
                0
                max(2rem, env(safe-area-inset-bottom));
        }

        /* =====================================================
           TOPO
           ===================================================== */

        .topbar {
            display: flex;
            min-height: 44px;
            gap: .6rem;
            align-items: center;
            justify-content: space-between;
            padding: .1rem .1rem .75rem;
        }

        .brand {
            display: inline-flex;
            gap: .42rem;
            align-items: center;
            color: var(--text);
            font-size: .75rem;
            font-weight: 820;
            letter-spacing: -.01em;
        }

        .brand i {
            color: var(--green);
            font-size: 1.05rem;
        }

        .reference-top {
            color: var(--muted);
            font-size: .64rem;
            font-weight: 700;
            letter-spacing: .035em;
        }

        /* =====================================================
           SUPERFÍCIE ÚNICA
           ===================================================== */

        .document {
            overflow: hidden;
            border: 1px solid rgba(199, 213, 204, .9);
            border-radius: 18px;
            background: var(--surface);
            box-shadow:
                0 18px 50px rgba(32, 68, 47, .07),
                0 2px 8px rgba(32, 68, 47, .025);
        }

        /* =====================================================
           HERO
           ===================================================== */

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 1rem;
            align-items: center;
            padding: 1.15rem 1.2rem;
            background: var(--hero);
        }

        .hero-main {
            min-width: 0;
        }

        .eyebrow {
            display: inline-flex;
            gap: .3rem;
            align-items: center;
            color: var(--green-strong);
            font-size: .65rem;
            font-weight: 790;
        }

        .eyebrow i {
            font-size: .75rem;
        }

        .reference {
            margin-top: .55rem;
            color: var(--muted);
            font-size: .62rem;
            font-weight: 720;
            letter-spacing: .065em;
            text-transform: uppercase;
        }

        .number {
            margin: .09rem 0 0;
            color: var(--text);
            font-size: clamp(1rem, 2.6vw, 1.22rem);
            font-weight: 850;
            line-height: 1.15;
        }

        .hero-finance {
            display: flex;
            min-width: 0;
            gap: .8rem;
            align-items: flex-end;
            margin-top: .8rem;
            flex-wrap: wrap;
        }

        .amount-block {
            min-width: 0;
        }

        .amount-block small {
            display: block;
            margin-bottom: .08rem;
            color: var(--muted);
            font-size: .58rem;
            font-weight: 700;
        }

        .amount {
            color: var(--text);
            font-size: clamp(1.65rem, 6vw, 2.4rem);
            font-weight: 900;
            letter-spacing: -.055em;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .status {
            --tone: var(--text-2);
            --soft: var(--surface-soft);

            display: inline-flex;
            min-height: 30px;
            gap: .26rem;
            align-items: center;
            padding: .25rem .46rem;
            border-radius: 8px;
            background: var(--soft);
            color: var(--tone);
            font-size: .61rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .status.success {
            --tone: var(--green);
            --soft: var(--green-soft);
        }

        .status.warning {
            --tone: var(--amber);
            --soft: var(--amber-soft);
        }

        .status.info {
            --tone: var(--blue);
            --soft: var(--blue-soft);
        }

        .status.danger {
            --tone: var(--red);
            --soft: var(--red-soft);
        }

        .qr-shell {
            display: grid;
            gap: .28rem;
            justify-items: center;
        }

        .qr {
            display: block;
            width: 82px;
            height: 82px;
            padding: 5px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
        }

        .qr-shell small {
            color: var(--muted);
            font-size: .49rem;
            font-weight: 680;
        }

        /* =====================================================
           RESUMO
           ===================================================== */

        .summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .summary-item {
            min-width: 0;
            padding: .72rem .85rem;
        }

        .summary-item + .summary-item {
            border-left: 1px solid var(--border);
        }

        .summary-item small,
        .summary-item strong {
            display: block;
        }

        .summary-item small {
            color: var(--muted);
            font-size: .57rem;
            font-weight: 690;
        }

        .summary-item strong {
            margin-top: .06rem;
            overflow: hidden;
            color: var(--text);
            font-size: .8rem;
            font-weight: 830;
            font-variant-numeric: tabular-nums;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .summary-item.paid strong {
            color: var(--green);
        }

        .summary-item.balance strong {
            color: var(--amber);
        }

        /* =====================================================
           INFORMAÇÕES
           ===================================================== */

        .info-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            border-bottom: 1px solid var(--border);
        }

        .info-item {
            display: grid;
            min-width: 0;
            gap: .05rem;
            padding: .68rem .8rem;
        }

        .info-item + .info-item {
            border-left: 1px solid var(--border-soft);
        }

        .info-item small {
            color: var(--muted);
            font-size: .53rem;
            font-weight: 680;
        }

        .info-item strong {
            overflow: hidden;
            color: var(--text);
            font-size: .68rem;
            font-weight: 760;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* =====================================================
           AÇÕES
           ===================================================== */

        .action-area {
            display: grid;
            gap: .55rem;
            padding: .82rem .9rem;
            border-bottom: 1px solid var(--border);
        }

        .action-area-head {
            display: flex;
            gap: .6rem;
            align-items: center;
            justify-content: space-between;
        }

        .action-area-head strong {
            color: var(--text);
            font-size: .7rem;
            font-weight: 820;
        }

        .action-area-head span {
            color: var(--muted);
            font-size: .58rem;
        }

        .actions {
            display: flex;
            gap: .45rem;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            min-height: 39px;
            gap: .3rem;
            align-items: center;
            justify-content: center;
            padding: .38rem .6rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            color: var(--text-2);
            cursor: pointer;
            font-size: .66rem;
            font-weight: 790;
            text-decoration: none;
        }

        .btn.primary {
            border-color: var(--green);
            background: var(--green);
            color: #fff;
        }

        .btn.danger {
            border-color: #ebc7c5;
            background: var(--red-soft);
            color: var(--red);
        }

        .btn:disabled {
            cursor: wait;
            opacity: .58;
        }

        @media print {
            .document-print-action,
            .action-area,
            dialog {
                display: none !important;
            }
        }

        .btn:focus-visible,
        .dialog-close:focus-visible,
        .history-toggle:focus-visible {
            outline: 2px solid var(--blue);
            outline-offset: 2px;
        }

        /* =====================================================
           HISTÓRICO RECOLHIDO
           ===================================================== */

        .history-panel {
            border-bottom: 1px solid var(--border);
        }

        .history-panel > summary {
            display: flex;
            min-height: 48px;
            gap: .5rem;
            align-items: center;
            padding: .65rem .9rem;
            cursor: pointer;
            list-style: none;
        }

        .history-panel > summary::-webkit-details-marker {
            display: none;
        }

        .history-panel > summary i {
            color: var(--blue);
            font-size: .78rem;
        }

        .history-toggle-copy {
            min-width: 0;
            flex: 1;
        }

        .history-toggle-copy strong,
        .history-toggle-copy small {
            display: block;
        }

        .history-toggle-copy strong {
            color: var(--text);
            font-size: .68rem;
            font-weight: 790;
        }

        .history-toggle-copy small {
            margin-top: .02rem;
            color: var(--muted);
            font-size: .54rem;
        }

        .history-caret {
            color: var(--muted) !important;
            transition: transform .15s ease;
        }

        .history-panel[open] .history-caret {
            transform: rotate(180deg);
        }

        .history {
            display: grid;
            gap: .45rem;
            padding: 0 .9rem .85rem;
        }

        .event {
            display: grid;
            grid-template-columns: 30px minmax(0, 1fr);
            gap: .45rem;
            align-items: center;
        }

        .event-icon {
            display: grid;
            width: 30px;
            height: 30px;
            place-items: center;
            border-radius: 7px;
            background: var(--green-soft);
            color: var(--green);
            font-size: .7rem;
        }

        .event-copy {
            min-width: 0;
        }

        .event-copy p,
        .event-copy small {
            display: block;
            margin: 0;
        }

        .event-copy p {
            overflow: hidden;
            color: var(--text);
            font-size: .63rem;
            font-weight: 750;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .event-copy small {
            margin-top: .02rem;
            color: var(--muted);
            font-size: .52rem;
        }

        /* =====================================================
           ACESSO RESTRITO / INDISPONÍVEL
           ===================================================== */

        .restricted {
            display: grid;
            min-height: 230px;
            gap: .55rem;
            place-items: center;
            padding: 1.6rem 1rem;
            text-align: center;
        }

        .restricted-icon {
            display: grid;
            width: 50px;
            height: 50px;
            place-items: center;
            border-radius: 12px;
            background: var(--surface-soft);
            color: var(--text-2);
            font-size: 1.25rem;
        }

        .restricted h1 {
            margin: 0;
            font-size: 1rem;
            font-weight: 850;
        }

        .restricted p {
            max-width: 440px;
            margin: 0;
            color: var(--text-2);
            font-size: .7rem;
            line-height: 1.55;
        }

        .restricted a {
            margin-top: .1rem;
        }

        /* =====================================================
           DIALOGS
           ===================================================== */

        .finance-dialog {
            width: min(94vw, 650px);
            max-width: 650px;
            max-height: min(90dvh, 760px);
            margin: auto;
            padding: 0;
            overflow: hidden;
            border: 0;
            border-radius: 14px;
            background: #fff;
            color: var(--text);
            box-shadow: 0 26px 80px rgba(18, 40, 28, .22);
        }

        .finance-dialog::backdrop {
            background: rgba(12, 24, 16, .58);
        }

        .dialog-layout {
            display: grid;
            max-height: min(90dvh, 760px);
            grid-template-rows: auto minmax(0, 1fr) auto;
        }

        .dialog-head {
            display: flex;
            gap: .7rem;
            align-items: center;
            justify-content: space-between;
            padding: .75rem .8rem;
            border-bottom: 1px solid var(--border);
            background:
                linear-gradient(
                    135deg,
                    #fff,
                    #f5faf7
                );
        }

        .dialog-title {
            display: grid;
            min-width: 0;
            grid-template-columns: 34px minmax(0, 1fr);
            gap: .45rem;
            align-items: center;
        }

        .dialog-icon {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 8px;
            background: var(--green-soft);
            color: var(--green);
            font-size: .82rem;
        }

        .dialog-title-copy {
            min-width: 0;
        }

        .dialog-title-copy small,
        .dialog-title-copy strong {
            display: block;
        }

        .dialog-title-copy small {
            color: var(--muted);
            font-size: .54rem;
        }

        .dialog-title-copy strong {
            margin-top: .02rem;
            color: var(--text);
            font-size: .82rem;
            font-weight: 820;
        }

        .dialog-close {
            display: grid;
            width: 38px;
            height: 38px;
            place-items: center;
            border: 0;
            border-radius: 8px;
            background: var(--surface-soft);
            color: var(--text-2);
            cursor: pointer;
            font-size: .86rem;
        }

        .dialog-body {
            min-height: 0;
            overflow-y: auto;
            padding: .8rem;
        }

        .async-financial-form {
            display: grid;
            gap: .65rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .55rem;
        }

        .field {
            display: grid;
            min-width: 0;
            gap: .25rem;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .field > span {
            color: var(--text-2);
            font-size: .59rem;
            font-weight: 720;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            min-width: 0;
            min-height: 42px;
            padding: .5rem .55rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            outline: 0;
            background: #fff;
            color: var(--text);
            font: inherit;
            font-size: .71rem;
        }

        .field textarea {
            min-height: 84px;
            resize: vertical;
        }

        .field input:focus,
        .field select:focus,
        .field textarea:focus {
            border-color: var(--green);
            box-shadow: 0 0 0 3px var(--green-soft);
        }

        .dialog-facts {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .5rem;
            margin-bottom: .65rem;
        }

        .dialog-fact {
            padding: .55rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--surface-soft);
        }

        .dialog-fact small,
        .dialog-fact strong {
            display: block;
        }

        .dialog-fact small {
            color: var(--muted);
            font-size: .52rem;
        }

        .dialog-fact strong {
            margin-top: .04rem;
            color: var(--text);
            font-size: .68rem;
            font-weight: 790;
        }

        .message {
            display: none;
            padding: .6rem .65rem;
            border-radius: 8px;
            font-size: .65rem;
            line-height: 1.45;
        }

        .message.show {
            display: block;
        }

        .message.error {
            border: 1px solid #efcdca;
            background: var(--red-soft);
            color: #8f3735;
        }

        .dialog-foot {
            display: flex;
            gap: .45rem;
            align-items: center;
            justify-content: flex-end;
            padding: .65rem .75rem;
            border-top: 1px solid var(--border);
            background: var(--surface-soft);
        }

        /* =====================================================
           RESPONSIVO
           ===================================================== */

        @media (max-width: 680px) {
            .page {
                width: min(100% - .8rem, 920px);
                padding-top: .45rem;
            }

            .topbar {
                padding-bottom: .45rem;
            }

            .document {
                border-radius: 14px;
            }

            .hero {
                grid-template-columns: minmax(0, 1fr) auto;
                padding: .85rem;
            }

            .qr {
                width: 66px;
                height: 66px;
            }

            .summary {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .summary-item {
                padding: .6rem .55rem;
            }

            .summary-item strong {
                font-size: .69rem;
            }

            .info-strip {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .info-item:nth-child(3) {
                border-top: 1px solid var(--border-soft);
                border-left: 0;
            }

            .info-item:nth-child(4) {
                border-top: 1px solid var(--border-soft);
            }

            .actions {
                display: grid;
                grid-template-columns:
                    repeat(
                        auto-fit,
                        minmax(130px, 1fr)
                    );
            }

            .btn {
                width: 100%;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }

            .field input,
            .field select,
            .field textarea {
                font-size: 16px;
            }

            .dialog-facts {
                grid-template-columns: 1fr;
            }

            .finance-dialog {
                width: calc(100vw - .7rem);
                max-height: calc(100dvh - .7rem);
            }

            .dialog-layout {
                max-height: calc(100dvh - .7rem);
            }
        }

        @media (max-width: 430px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .qr-shell {
                display: none;
            }

            .hero-finance {
                align-items: center;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .summary-item + .summary-item {
                border-top: 1px solid var(--border);
                border-left: 0;
            }

            .info-strip {
                grid-template-columns: 1fr;
            }

            .info-item + .info-item,
            .info-item:nth-child(3),
            .info-item:nth-child(4) {
                border-top: 1px solid var(--border-soft);
                border-left: 0;
            }
        }
    </style>
</head>

<body>
    <main class="page">
        <header class="topbar">
            <div class="brand">
                <i class="ph-fill ph-receipt"></i>
                <span>Comprovante financeiro</span>
            </div>

            @if($documentView)
                <span class="reference-top">
                    {{ $documentView['reference'] }}
                </span>
            @endif
        </header>

        @if(!$documentView)
            <section class="document restricted">
                <span
                    class="restricted-icon"
                    aria-hidden="true"
                >
                    <i class="ph-fill ph-file-x"></i>
                </span>

                <h1>Comprovante indisponível</h1>

                <p>
                    Não foi possível localizar este comprovante.
                    Confira a referência utilizada e tente novamente.
                </p>
            </section>
        @else
            <article class="document">
                <section class="hero">
                    <div class="hero-main">
                        <span class="eyebrow">
                            <i class="ph-fill ph-seal-check"></i>
                            Comprovante identificado
                        </span>

                        <div class="reference">
                            {{ $documentView['reference'] }}
                        </div>

                        <h1 class="number">
                            {{
                                $documentView['can_view_details']
                                    ? $documentView['number']
                                    : 'Comprovante financeiro'
                            }}
                        </h1>

                        <div class="hero-finance">
                            @if($documentView['can_view_details'])
                                <div class="amount-block">
                                    <small>Valor total</small>

                                    <div class="amount">
                                        R$ {{ number_format(
                                            $documentView['total'],
                                            2,
                                            ',',
                                            '.'
                                        ) }}
                                    </div>
                                </div>
                            @endif

                            <span
                                class="
                                    status
                                    {{ $documentView['tone'] }}
                                "
                            >
                                {{ $documentView['human_status'] }}
                            </span>
                        </div>
                    </div>

                    <div class="qr-shell">
                        <img
                            class="qr"
                            src="{{ route(
                                'financial-documents.qr',
                                $documentView['identity']->public_id
                            ) }}"
                            alt="QR Code do comprovante"
                        >

                        <small>Referência do documento</small>
                    </div>
                </section>

                @if($documentView['can_view_details'])
                    <div class="document-print-action" style="display:flex;justify-content:flex-end;padding:.55rem .9rem;border-bottom:1px solid var(--border)">
                        <button class="btn" type="button" data-print-document><i class="ph-fill ph-printer"></i> Imprimir cobrança</button>
                    </div>
                    <section
                        class="summary"
                        aria-label="Resumo financeiro"
                    >
                        <div class="summary-item">
                            <small>Valor total</small>

                            <strong>
                                R$ {{ number_format(
                                    $documentView['total'],
                                    2,
                                    ',',
                                    '.'
                                ) }}
                            </strong>
                        </div>

                        <div class="summary-item paid">
                            <small>Liquidado</small>

                            <strong>
                                R$ {{ number_format(
                                    $documentView['paid'],
                                    2,
                                    ',',
                                    '.'
                                ) }}
                            </strong>
                        </div>

                        <div class="summary-item balance">
                            <small>Saldo</small>

                            <strong>
                                R$ {{ number_format(
                                    $documentView['balance'],
                                    2,
                                    ',',
                                    '.'
                                ) }}
                            </strong>
                        </div>
                    </section>

                    <section class="info-strip">
                        <div class="info-item">
                            <small>Tipo</small>
                            <strong>{{ $documentView['kind'] }}</strong>
                        </div>

                        <div class="info-item">
                            <small>Parte relacionada</small>
                            <strong>{{ $documentView['party'] }}</strong>
                        </div>

                        <div class="info-item">
                            <small>Origem</small>
                            <strong>
                                {{ $documentView['project'] ?: '—' }}
                            </strong>
                        </div>

                        <div class="info-item">
                            <small>Emissão</small>

                            <strong>
                                {{
                                    $documentView['issued_at']
                                        ?->format('d/m/Y H:i')
                                    ?? '—'
                                }}
                            </strong>
                        </div>
                    </section>

                    @if(
                        $documentView['can_pay']
                        && count($documentView['actions'])
                    )
                        <section class="action-area">
                            <div class="action-area-head">
                                <strong>Ações disponíveis</strong>

                                <span>
                                    Escolha apenas a operação desejada.
                                </span>
                            </div>

                            <div class="actions">
                                @foreach($documentView['actions'] as $action)
                                    <button
                                        class="
                                            btn
                                            {{ $action['primary'] ? 'primary' : '' }}
                                        "
                                        type="button"
                                        data-open-dialog="{{ $action['key'] }}"
                                    >
                                        <i
                                            class="
                                                ph-fill
                                                {{
                                                    $action['key'] === 'pay'
                                                        ? 'ph-currency-circle-dollar'
                                                        : (
                                                            $action['key'] === 'issue_check'
                                                                ? 'ph-file-text'
                                                                : 'ph-handshake'
                                                        )
                                                }}
                                            "
                                        ></i>

                                        {{ $action['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if(
                        $documentView['payments']->isNotEmpty()
                        || $documentView['checks']->isNotEmpty()
                    )
                        <details class="history-panel">
                            <summary class="history-toggle">
                                <i class="ph-fill ph-clock-counter-clockwise"></i>

                                <span class="history-toggle-copy">
                                    <strong>Histórico</strong>

                                    <small>
                                        Movimentações registradas neste comprovante.
                                    </small>
                                </span>

                                <i
                                    class="
                                        ph-fill
                                        ph-caret-down
                                        history-caret
                                    "
                                ></i>
                            </summary>

                            <div class="history">
                                @foreach($documentView['checks'] as $check)
                                    <div class="event">
                                        <span class="event-icon">
                                            <i class="ph-fill ph-file-text"></i>
                                        </span>

                                        <span class="event-copy">
                                            <p>
                                                Cheque {{ $check->check_number }}
                                                {{
                                                    $check->status === 'delivered'
                                                        ? 'entregue'
                                                        : (
                                                            $check->status === 'cancelled'
                                                                ? 'cancelado'
                                                                : 'emitido'
                                                        )
                                                }}
                                            </p>

                                            <small>
                                                R$ {{ number_format(
                                                    (float) $check->amount,
                                                    2,
                                                    ',',
                                                    '.'
                                                ) }}
                                                ·
                                                {{
                                                    (
                                                        $check->delivered_at
                                                        ?? $check->issued_at
                                                    )
                                                        ?->format('d/m/Y H:i')
                                                    ?? '—'
                                                }}
                                            </small>
                                        </span>
                                    </div>
                                @endforeach

                                @foreach($documentView['payments'] as $payment)
                                    @php(
                                        $event =
                                            $payment->paymentEvent
                                            ?? null
                                    )

                                    <div class="event">
                                        <span class="event-icon">
                                            <i class="ph-fill ph-currency-circle-dollar"></i>
                                        </span>

                                        <span class="event-copy">
                                            <p>
                                                {{
                                                    strtoupper(
                                                        (string) (
                                                            $payment->payment_method
                                                            ?? $event?->payment_method
                                                            ?? 'Pagamento'
                                                        )
                                                    )
                                                }}
                                                confirmado
                                            </p>

                                            <small>
                                                R$ {{ number_format(
                                                    (float) $payment->amount,
                                                    2,
                                                    ',',
                                                    '.'
                                                ) }}
                                                ·
                                                {{
                                                    (
                                                        $payment->payment_date
                                                        ?? $event?->payment_date
                                                    )
                                                        ?->format('d/m/Y')
                                                    ?? '—'
                                                }}
                                            </small>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                @else
                    <section class="restricted">
                        <span
                            class="restricted-icon"
                            aria-hidden="true"
                        >
                            <i class="ph-fill ph-lock-key"></i>
                        </span>

                        <h1>Detalhes protegidos</h1>

                        <p>
                            O conteúdo completo deste comprovante
                            está disponível somente para usuários
                            autorizados.
                        </p>

                        @guest
                            <a
                                class="btn primary"
                                href="{{ route('login') }}"
                            >
                                <i class="ph-fill ph-sign-in"></i>
                                Entrar
                            </a>
                        @endguest
                    </section>
                @endif
            </article>
        @endif
    </main>

    @if($documentView && $documentView['can_view_details'])
        @if(collect($documentView['actions'])->contains('key', 'pay'))
            <dialog
                class="finance-dialog"
                id="dialog-pay"
                aria-label="Registrar pagamento"
            >
                <div class="dialog-layout">
                    <header class="dialog-head">
                        <div class="dialog-title">
                            <span class="dialog-icon">
                                <i class="ph-fill ph-currency-circle-dollar"></i>
                            </span>

                            <span class="dialog-title-copy">
                                <small>Financeiro</small>
                                <strong>Registrar pagamento</strong>
                            </span>
                        </div>

                        <button
                            class="dialog-close"
                            type="button"
                            data-close-dialog="pay"
                            aria-label="Fechar"
                        >
                            <i class="ph-fill ph-x"></i>
                        </button>
                    </header>

                    <div class="dialog-body">
                        <form
                            class="async-financial-form"
                            id="form-pay"
                            method="post"
                            action="{{ route(
                                'financial-documents.pay',
                                $documentView['identity']->public_id
                            ) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="operation_key"
                                data-operation-key
                            >

                            <div class="grid">
                                <label class="field">
                                    <span>Valor</span>

                                    <input
                                        type="number"
                                        name="amount"
                                        min="0.01"
                                        max="{{ $documentView['balance'] }}"
                                        step="0.01"
                                        value="{{ number_format(
                                            $documentView['balance'],
                                            2,
                                            '.',
                                            ''
                                        ) }}"
                                        required
                                    >
                                </label>

                                <label class="field">
                                    <span>Data</span>

                                    <input
                                        type="date"
                                        name="payment_date"
                                        value="{{ now()->toDateString() }}"
                                        required
                                    >
                                </label>

                                <label class="field">
                                    <span>Forma de pagamento</span>

                                    <select
                                        name="payment_method"
                                        required
                                    >
                                        @foreach($paymentMethods as $method)
                                            <option value="{{ $method->value }}">
                                                {{ $method->getLabel() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field">
                                    <span>Conta ou caixa</span>

                                    <select
                                        name="bank_account_id"
                                        required
                                    >
                                        <option value="">Selecione</option>

                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}">
                                                {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field full">
                                    <span>Referência do pagamento</span>

                                    <input
                                        name="document_number"
                                        maxlength="100"
                                    >
                                </label>

                                <label class="field full">
                                    <span>Observações</span>

                                    <textarea
                                        name="notes"
                                        maxlength="2000"
                                    ></textarea>
                                </label>
                            </div>

                            <div
                                class="message"
                                role="alert"
                            ></div>
                        </form>
                    </div>

                    <footer class="dialog-foot">
                        <button
                            class="btn"
                            type="button"
                            data-close-dialog="pay"
                        >
                            Cancelar
                        </button>

                        <button
                            class="btn primary"
                            type="submit"
                            form="form-pay"
                        >
                            <i class="ph-fill ph-check-circle"></i>
                            Confirmar pagamento
                        </button>
                    </footer>
                </div>
            </dialog>
        @endif

        @if(collect($documentView['actions'])->contains('key', 'issue_check'))
            <dialog
                class="finance-dialog"
                id="dialog-issue_check"
                aria-label="Emitir cheque"
            >
                <div class="dialog-layout">
                    <header class="dialog-head">
                        <div class="dialog-title">
                            <span class="dialog-icon">
                                <i class="ph-fill ph-file-text"></i>
                            </span>

                            <span class="dialog-title-copy">
                                <small>Financeiro</small>
                                <strong>Emitir cheque</strong>
                            </span>
                        </div>

                        <button
                            class="dialog-close"
                            type="button"
                            data-close-dialog="issue_check"
                            aria-label="Fechar"
                        >
                            <i class="ph-fill ph-x"></i>
                        </button>
                    </header>

                    <div class="dialog-body">
                        <form
                            class="async-financial-form"
                            id="form-issue_check"
                            method="post"
                            action="{{ route(
                                'financial-documents.checks.issue',
                                $documentView['identity']->public_id
                            ) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="operation_key"
                                data-operation-key
                            >

                            <div class="grid">
                                <label class="field">
                                    <span>Valor do cheque</span>

                                    <input
                                        type="number"
                                        name="amount"
                                        min="0.01"
                                        max="{{ $documentView['balance'] }}"
                                        step="0.01"
                                        value="{{ number_format(
                                            $documentView['balance'],
                                            2,
                                            '.',
                                            ''
                                        ) }}"
                                        required
                                    >
                                </label>

                                <label class="field">
                                    <span>Número do cheque</span>

                                    <input
                                        name="check_number"
                                        maxlength="80"
                                        required
                                    >
                                </label>

                                <label class="field">
                                    <span>Banco</span>

                                    <input
                                        name="bank_name"
                                        maxlength="120"
                                        required
                                    >
                                </label>

                                <label class="field">
                                    <span>Conta/agência</span>

                                    <input
                                        name="account_reference"
                                        maxlength="120"
                                    >
                                </label>

                                <label class="field">
                                    <span>Data de emissão</span>

                                    <input
                                        type="date"
                                        name="issue_date"
                                        value="{{ now()->toDateString() }}"
                                        required
                                    >
                                </label>

                                <label class="field">
                                    <span>Previsão de entrega</span>

                                    <input
                                        type="date"
                                        name="expected_delivery_date"
                                    >
                                </label>

                                <label class="field full">
                                    <span>Observações</span>

                                    <textarea
                                        name="notes"
                                        maxlength="2000"
                                    ></textarea>
                                </label>
                            </div>

                            <div
                                class="message"
                                role="alert"
                            ></div>
                        </form>
                    </div>

                    <footer class="dialog-foot">
                        <button
                            class="btn"
                            type="button"
                            data-close-dialog="issue_check"
                        >
                            Cancelar
                        </button>

                        <button
                            class="btn primary"
                            type="submit"
                            form="form-issue_check"
                        >
                            <i class="ph-fill ph-file-text"></i>
                            Emitir cheque
                        </button>
                    </footer>
                </div>
            </dialog>
        @endif

        @if($documentView['pending_check'])
            <dialog
                class="finance-dialog"
                id="dialog-deliver_check"
                aria-label="Confirmar entrega do cheque"
            >
                <div class="dialog-layout">
                    <header class="dialog-head">
                        <div class="dialog-title">
                            <span class="dialog-icon">
                                <i class="ph-fill ph-handshake"></i>
                            </span>

                            <span class="dialog-title-copy">
                                <small>Cheque</small>
                                <strong>Confirmar entrega</strong>
                            </span>
                        </div>

                        <button
                            class="dialog-close"
                            type="button"
                            data-close-dialog="deliver_check"
                            aria-label="Fechar"
                        >
                            <i class="ph-fill ph-x"></i>
                        </button>
                    </header>

                    <div class="dialog-body">
                        <div class="dialog-facts">
                            <div class="dialog-fact">
                                <small>Número</small>

                                <strong>
                                    {{ $documentView['pending_check']->check_number }}
                                </strong>
                            </div>

                            <div class="dialog-fact">
                                <small>Valor</small>

                                <strong>
                                    R$ {{ number_format(
                                        (float) $documentView['pending_check']->amount,
                                        2,
                                        ',',
                                        '.'
                                    ) }}
                                </strong>
                            </div>
                        </div>

                        <form
                            class="async-financial-form"
                            id="form-deliver_check"
                            method="post"
                            action="{{ route(
                                'financial-documents.checks.deliver',
                                [
                                    $documentView['identity']->public_id,
                                    $documentView['pending_check'],
                                ]
                            ) }}"
                        >
                            @csrf

                            <input
                                type="hidden"
                                name="operation_key"
                                data-operation-key
                            >

                            <div class="grid">
                                <label class="field">
                                    <span>Data da entrega</span>

                                    <input
                                        type="date"
                                        name="payment_date"
                                        value="{{ now()->toDateString() }}"
                                        required
                                    >
                                </label>

                                <label class="field">
                                    <span>Conta ou caixa</span>

                                    <select
                                        name="bank_account_id"
                                        required
                                    >
                                        <option value="">Selecione</option>

                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}">
                                                {{ $account->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="field full">
                                    <span>Observações da entrega</span>

                                    <textarea
                                        name="notes"
                                        maxlength="2000"
                                    ></textarea>
                                </label>
                            </div>

                            <div
                                class="message"
                                role="alert"
                            ></div>
                        </form>
                    </div>

                    <footer class="dialog-foot">
                        <button
                            class="btn"
                            type="button"
                            data-close-dialog="deliver_check"
                        >
                            Cancelar
                        </button>

                        <button
                            class="btn primary"
                            type="submit"
                            form="form-deliver_check"
                        >
                            <i class="ph-fill ph-handshake"></i>
                            Confirmar entrega
                        </button>
                    </footer>
                </div>
            </dialog>

            <dialog
                class="finance-dialog"
                id="dialog-cancel_check"
                aria-label="Cancelar cheque"
            >
                <div class="dialog-layout">
                    <header class="dialog-head">
                        <div class="dialog-title">
                            <span
                                class="dialog-icon"
                                style="
                                    background:var(--red-soft);
                                    color:var(--red);
                                "
                            >
                                <i class="ph-fill ph-x-circle"></i>
                            </span>

                            <span class="dialog-title-copy">
                                <small>Cheque</small>
                                <strong>Cancelar cheque</strong>
                            </span>
                        </div>

                        <button
                            class="dialog-close"
                            type="button"
                            data-close-dialog="cancel_check"
                            aria-label="Fechar"
                        >
                            <i class="ph-fill ph-x"></i>
                        </button>
                    </header>

                    <div class="dialog-body">
                        <form
                            class="async-financial-form"
                            id="form-cancel_check"
                            method="post"
                            action="{{ route(
                                'financial-documents.checks.cancel',
                                [
                                    $documentView['identity']->public_id,
                                    $documentView['pending_check'],
                                ]
                            ) }}"
                        >
                            @csrf

                            <label class="field">
                                <span>Motivo do cancelamento</span>

                                <textarea
                                    name="reason"
                                    minlength="5"
                                    maxlength="1000"
                                    required
                                ></textarea>
                            </label>

                            <div
                                class="message"
                                role="alert"
                            ></div>
                        </form>
                    </div>

                    <footer class="dialog-foot">
                        <button
                            class="btn"
                            type="button"
                            data-close-dialog="cancel_check"
                        >
                            Voltar
                        </button>

                        <button
                            class="btn danger"
                            type="submit"
                            form="form-cancel_check"
                        >
                            <i class="ph-fill ph-x-circle"></i>
                            Confirmar cancelamento
                        </button>
                    </footer>
                </div>
            </dialog>
        @endif
    @endif

    <script>
        const uuid = () =>
            globalThis.crypto?.randomUUID?.()
            || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'
                .replace(
                    /[xy]/g,
                    character => {
                        const random =
                            Math.random() * 16
                            | 0;

                        return (
                            character === 'x'
                                ? random
                                : (
                                    random & 3
                                    | 8
                                )
                        ).toString(16);
                    }
                );

        document
            .querySelectorAll(
                '[data-operation-key]'
            )
            .forEach(input => {
                input.value ||= uuid();
            });

        document.querySelector('[data-print-document]')?.addEventListener('click', () => window.print());

        const dialogs = [
            ...document.querySelectorAll(
                '.finance-dialog'
            ),
        ];

        const directCloseDialog = dialog => {
            if (!dialog) {
                return;
            }

            if (
                typeof dialog.close
                    === 'function'
                && dialog.open
            ) {
                dialog.close();
            } else {
                dialog.removeAttribute(
                    'open'
                );
            }
        };

        const openDialog = key => {
            const dialog =
                document.getElementById(
                    `dialog-${key}`
                );

            if (!dialog) {
                return;
            }

            dialogs.forEach(other => {
                if (other !== dialog) {
                    directCloseDialog(other);
                }
            });

            if (
                typeof dialog.showModal
                    === 'function'
            ) {
                dialog.showModal();
            } else {
                dialog.setAttribute(
                    'open',
                    ''
                );
            }

            if (
                history.state
                    ?.financialDialog
                !== key
            ) {
                history.pushState(
                    {
                        ...(history.state || {}),
                        financialDialog: key,
                    },
                    '',
                    window.location.href
                );
            }
        };

        const requestCloseDialog = key => {
            const dialog =
                document.getElementById(
                    `dialog-${key}`
                );

            if (!dialog) {
                return;
            }

            if (
                history.state
                    ?.financialDialog
                === key
            ) {
                history.back();
                return;
            }

            directCloseDialog(dialog);
        };

        document
            .querySelectorAll(
                '[data-open-dialog]'
            )
            .forEach(button => {
                button.addEventListener(
                    'click',
                    () => {
                        openDialog(
                            button.dataset
                                .openDialog
                        );
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
                        requestCloseDialog(
                            button.dataset
                                .closeDialog
                        );
                    }
                );
            });

        dialogs.forEach(dialog => {
            dialog.addEventListener(
                'cancel',
                event => {
                    event.preventDefault();

                    const key =
                        dialog.id.replace(
                            'dialog-',
                            ''
                        );

                    requestCloseDialog(key);
                }
            );
        });

        window.addEventListener(
            'popstate',
            () => {
                dialogs.forEach(dialog => {
                    if (
                        dialog.hasAttribute(
                            'open'
                        )
                        && history.state
                            ?.financialDialog
                            !== dialog.id.replace(
                                'dialog-',
                                ''
                            )
                    ) {
                        directCloseDialog(
                            dialog
                        );
                    }
                });
            }
        );

        document
            .querySelectorAll(
                '.async-financial-form'
            )
            .forEach(form => {
                form.addEventListener(
                    'submit',
                    async event => {
                        event.preventDefault();

                        const submitButton =
                            document.querySelector(
                                `[form="${form.id}"]`
                            )
                            || form.querySelector(
                                '[type="submit"]'
                            );

                        const message =
                            form.querySelector(
                                '.message'
                            );

                        if (submitButton) {
                            submitButton.disabled =
                                true;
                        }

                        if (message) {
                            message.className =
                                'message';

                            message.textContent =
                                '';
                        }

                        try {
                            const response =
                                await fetch(
                                    form.action,
                                    {
                                        method:
                                            'POST',

                                        body:
                                            new FormData(
                                                form
                                            ),

                                        headers: {
                                            Accept:
                                                'application/json',

                                            'X-Requested-With':
                                                'XMLHttpRequest',
                                        },
                                    }
                                );

                            const payload =
                                await response
                                    .json()
                                    .catch(
                                        () => ({})
                                    );

                            if (!response.ok) {
                                const errors =
                                    Object
                                        .values(
                                            payload.errors
                                            || {}
                                        )
                                        .flat();

                                throw new Error(
                                    errors.join(' ')
                                    || payload.message
                                    || 'Não foi possível concluir a operação.'
                                );
                            }

                            window.location.reload();
                        } catch (error) {
                            if (message) {
                                message.textContent =
                                    error.message;

                                message.className =
                                    'message error show';
                            }

                            const operationKey =
                                form.querySelector(
                                    '[data-operation-key]'
                                );

                            if (operationKey) {
                                operationKey.value =
                                    uuid();
                            }
                        } finally {
                            if (submitButton) {
                                submitButton.disabled =
                                    false;
                            }
                        }
                    }
                );
            });
    </script>
</body>
</html>
