@extends('layouts.bento')

@section('title', 'Perfil não encontrado')
@section('page-title', 'Portal do Associado')
@section('user-role', 'Associado')


@section('content')
<style>
    .no-profile-page {
        --np-amber: #c87408;
        --np-amber-soft: #fff7e8;

        --np-green: #168a4d;
        --np-green-soft: #eaf8ef;

        --np-blue: #2563eb;
        --np-blue-soft: #eef4ff;

        --np-slate: #64748b;
        --np-slate-soft: #f1f5f9;

        display: grid;
        width: min(100%, 940px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .78rem;
        margin: 0 auto;
        padding: .45rem 0 1rem;
    }

    .no-profile-page *,
    .no-profile-page *::before,
    .no-profile-page *::after {
        box-sizing: border-box;
    }

    .no-profile-shell {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-radius: 15px;
        background: var(--color-surface);
        box-shadow: var(--shadow-sm);
    }

    .no-profile-head {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .7rem;
        align-items: center;
        padding: .78rem .82rem;
        border-bottom: 1px solid var(--color-border);
        background:
            linear-gradient(
                180deg,
                var(--color-surface-soft),
                var(--color-surface)
            );
    }

    .no-profile-head-icon {
        display: grid;
        width: 44px;
        height: 44px;
        place-items: center;
        border-radius: 12px;
        background: var(--np-amber-soft);
        color: var(--np-amber);
    }

    .no-profile-head
    .no-profile-head-icon > i {
        display: block;
        font-size: 1.24rem;
        line-height: 1;
    }

    .no-profile-head-copy {
        min-width: 0;
    }

    .no-profile-head-copy h1,
    .no-profile-head-copy p {
        margin: 0;
    }

    .no-profile-head-copy h1 {
        color: var(--color-text);
        font-size: clamp(1rem, 2vw, 1.18rem);
        font-weight: 850;
        letter-spacing: -.025em;
        line-height: 1.25;
    }

    .no-profile-head-copy p {
        margin-top: .12rem;
        color: var(--color-text-muted);
        font-size: .76rem;
        line-height: 1.45;
    }

    .no-profile-status {
        display: grid;
        min-height: 30px;
        grid-template-columns: auto auto;
        gap: .28rem;
        align-items: center;
        padding: .28rem .48rem;
        border-radius: 999px;
        background: var(--np-amber-soft);
        color: #92400e;
        font-size: .7rem;
        font-weight: 790;
        white-space: nowrap;
    }

    .no-profile-status > i {
        display: block;
        font-size: .82rem;
        line-height: 1;
    }

    .no-profile-body {
        display: grid;
        min-width: 0;
        grid-template-columns:
            minmax(0, 1.15fr)
            minmax(280px, .85fr);
        gap: 0;
    }

    .no-profile-main {
        display: grid;
        min-width: 0;
        align-content: start;
        gap: .72rem;
        padding: .9rem;
    }

    .no-profile-message {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .62rem;
        align-items: start;
        padding: .68rem .72rem;
        border: 1px solid rgba(200, 116, 8, .16);
        border-radius: 11px;
        background: var(--np-amber-soft);
    }

    .no-profile-message .no-profile-message-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 10px;
        background: #fef3c7;
        color: var(--np-amber);
    }

    .no-profile-message
    .no-profile-message-icon > i {
        display: block;
        font-size: 1rem;
        line-height: 1;
    }

    .no-profile-message strong,
    .no-profile-message span {
        display: block;
    }

    .no-profile-message strong {
        color: #78350f;
        font-size: .8rem;
        font-weight: 820;
    }

    .no-profile-message span {
        margin-top: .08rem;
        color: #92400e;
        font-size: .75rem;
        line-height: 1.48;
    }

    .no-profile-section {
        min-width: 0;
    }

    .no-profile-section-title {
        display: grid;
        width: max-content;
        max-width: 100%;
        grid-template-columns: auto auto;
        gap: .35rem;
        align-items: center;
        margin: 0 0 .45rem;
        color: var(--color-text);
        font-size: .79rem;
        font-weight: 820;
    }

    .no-profile-section-title > i {
        display: block;
        color: var(--np-blue);
        font-size: .95rem;
        line-height: 1;
    }

    .no-profile-steps {
        display: grid;
        min-width: 0;
        gap: .1rem;
    }

    .no-profile-step {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .56rem;
        align-items: start;
        padding: .52rem .1rem;
    }

    .no-profile-step + .no-profile-step {
        border-top: 1px solid var(--color-border);
    }

    .no-profile-step-number {
        display: grid;
        width: 28px;
        height: 28px;
        place-items: center;
        border-radius: 9px;
        background: var(--np-blue-soft);
        color: var(--np-blue);
        font-size: .7rem;
        font-weight: 850;
    }

    .no-profile-step-copy {
        min-width: 0;
    }

    .no-profile-step-copy strong,
    .no-profile-step-copy span {
        display: block;
    }

    .no-profile-step-copy strong {
        color: var(--color-text);
        font-size: .77rem;
        font-weight: 810;
        line-height: 1.35;
    }

    .no-profile-step-copy span {
        margin-top: .05rem;
        color: var(--color-text-muted);
        font-size: .73rem;
        line-height: 1.45;
    }

    .no-profile-side {
        display: grid;
        min-width: 0;
        align-content: start;
        gap: .7rem;
        padding: .9rem;
        border-top: 0;
        background: var(--color-surface-soft);
    }

    .no-profile-account-box {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .58rem;
        align-items: center;
        padding: .65rem;
        border: 1px solid var(--color-border);
        border-radius: 11px;
        background: #fff;
    }

    .no-profile-account-box .no-profile-account-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 10px;
        background: var(--np-green-soft);
        color: var(--np-green);
    }

    .no-profile-account-box
    .no-profile-account-icon > i {
        display: block;
        font-size: 1rem;
        line-height: 1;
    }

    .no-profile-account-box strong,
    .no-profile-account-box span {
        display: block;
    }

    .no-profile-account-box strong {
        color: var(--color-text);
        font-size: .77rem;
        font-weight: 810;
    }

    .no-profile-account-box span {
        margin-top: .04rem;
        color: var(--color-text-muted);
        font-size: .7rem;
        line-height: 1.4;
    }

    .no-profile-actions {
        display: grid;
        gap: .45rem;
    }

    .no-profile-button {
        display: grid;
        width: 100%;
        min-height: 44px;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .45rem;
        align-items: center;
        padding: .55rem .62rem;
        border: 1px solid var(--color-border-strong);
        border-radius: 10px;
        background: #fff;
        color: var(--color-text-secondary);
        font-size: .75rem;
        font-weight: 780;
        text-align: left;
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            transform 150ms ease;
    }

    .no-profile-button > i {
        display: block;
        font-size: .95rem;
        line-height: 1;
    }

    .no-profile-button > i:last-child {
        color: var(--color-text-muted);
        font-size: .8rem;
    }

    .no-profile-button:hover,
    .no-profile-button:focus-visible {
        border-color: rgba(37, 99, 235, .22);
        background: var(--np-blue-soft);
        color: var(--np-blue);
        outline: none;
        transform: translateY(-1px);
    }

    .no-profile-button.primary {
        border-color: var(--color-primary-dark);
        background:
            linear-gradient(
                135deg,
                var(--color-primary),
                var(--color-primary-dark)
            );
        color: #fff;
        box-shadow:
            0 7px 16px rgba(22, 163, 74, .13);
    }

    .no-profile-button.primary > i:last-child {
        color: rgba(255, 255, 255, .78);
    }

    .no-profile-button.primary:hover,
    .no-profile-button.primary:focus-visible {
        border-color: var(--color-primary-dark);
        background:
            linear-gradient(
                135deg,
                var(--color-primary-dark),
                var(--color-primary-deep)
            );
        color: #fff;
    }

    .no-profile-help {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .48rem;
        align-items: start;
        padding: .58rem .62rem;
        border-radius: 10px;
        background: var(--np-slate-soft);
        color: var(--color-text-secondary);
        font-size: .71rem;
        line-height: 1.45;
    }

    .no-profile-help > i {
        display: block;
        color: var(--np-slate);
        font-size: .9rem;
        line-height: 1;
        margin-top: .05rem;
    }

    .no-profile-footer {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .42rem;
        align-items: center;
        padding: .62rem .78rem;
        border-top: 1px solid var(--color-border);
        background: #fff;
        color: var(--color-text-muted);
        font-size: .69rem;
        line-height: 1.4;
    }

    .no-profile-footer > i {
        display: block;
        color: var(--np-green);
        font-size: .86rem;
        line-height: 1;
    }

    @media (max-width: 760px) {
        .no-profile-page {
            padding-top: .15rem;
        }

        .no-profile-body {
            grid-template-columns: 1fr;
        }

        .no-profile-side {
            border-top: 1px solid var(--color-border);
            background: #fff;
        }
    }

    @media (max-width: 520px) {
        .no-profile-page {
            gap: .65rem;
        }

        .no-profile-head {
            grid-template-columns: auto minmax(0, 1fr);
            padding: .68rem;
        }

        .no-profile-status {
            grid-column: 2;
            justify-self: start;
        }

        .no-profile-main,
        .no-profile-side {
            padding: .68rem;
        }

        .no-profile-message {
            padding: .6rem;
        }

        .no-profile-head-copy p {
            display: none;
        }
    }

    @media (max-width: 380px) {
        .no-profile-head {
            grid-template-columns: 1fr;
        }

        .no-profile-head-icon {
            width: 40px;
            height: 40px;
        }

        .no-profile-status {
            grid-column: auto;
        }

        .no-profile-message {
            grid-template-columns: 1fr;
        }

        .no-profile-step {
            grid-template-columns: 24px minmax(0, 1fr);
            gap: .45rem;
        }

        .no-profile-step-number {
            width: 24px;
            height: 24px;
            border-radius: 8px;
        }
    }
</style>

<main class="no-profile-page">
    <section
        class="no-profile-shell"
        aria-labelledby="no-profile-title"
    >
        <header class="no-profile-head">
            <span
                class="no-profile-head-icon"
                aria-hidden="true"
            >
                <i class="ph-duotone ph-user-circle-minus"></i>
            </span>

            <div class="no-profile-head-copy">
                <h1 id="no-profile-title">
                    Perfil de associado ainda não vinculado
                </h1>

                <p>
                    Sua conta está ativa, mas falta concluir
                    a ligação com o cadastro de associado.
                </p>
            </div>

            <span class="no-profile-status">
                <i
                    class="ph ph-clock-countdown"
                    aria-hidden="true"
                ></i>

                Aguardando vínculo
            </span>
        </header>

        <div class="no-profile-body">
            <div class="no-profile-main">
                <div class="no-profile-message">
                    <span
                        class="no-profile-message-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-warning-circle"></i>
                    </span>

                    <div>
                        <strong>
                            O acesso ao portal do associado ainda
                            não pode ser liberado.
                        </strong>

                        <span>
                            Sua conta possui a função
                            <strong>Associado</strong>, porém ela ainda
                            não está ligada ao seu cadastro dentro
                            desta organização.
                        </span>
                    </div>
                </div>

                <section class="no-profile-section">
                    <h2 class="no-profile-section-title">
                        <i class="ph-duotone ph-path"></i>
                        Como resolver
                    </h2>

                    <div class="no-profile-steps">
                        <div class="no-profile-step">
                            <span class="no-profile-step-number">
                                1
                            </span>

                            <div class="no-profile-step-copy">
                                <strong>
                                    Confirme que já possui cadastro
                                de associado
                                </strong>

                                <span>
                                    O cadastro precisa existir
                                    dentro desta organização.
                                </span>
                            </div>
                        </div>

                        <div class="no-profile-step">
                            <span class="no-profile-step-number">
                                2
                            </span>

                            <div class="no-profile-step-copy">
                                <strong>
                                    Solicite o vínculo da sua conta
                                </strong>

                                <span>
                                    Um administrador deve associar
                                    seu usuário ao cadastro correto.
                                </span>
                            </div>
                        </div>

                        <div class="no-profile-step">
                            <span class="no-profile-step-number">
                                3
                            </span>

                            <div class="no-profile-step-copy">
                                <strong>
                                    Acesse novamente o portal
                                </strong>

                                <span>
                                    Depois do vínculo, suas entregas,
                                    projetos e informações financeiras
                                    ficarão disponíveis normalmente.
                                </span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="no-profile-side">
                <div class="no-profile-account-box">
                    <span
                        class="no-profile-account-icon"
                        aria-hidden="true"
                    >
                        <i class="ph-duotone ph-user-check"></i>
                    </span>

                    <div>
                        <strong>
                            Sua conta está funcionando
                        </strong>

                        <span>
                            O problema é apenas o vínculo
                            com o cadastro de associado.
                        </span>
                    </div>
                </div>

                <div class="no-profile-actions">
                    @if(
                        Auth::user()->hasAnyRole([
                            'super_admin',
                            'admin',
                            'financeiro',
                        ])
                    )
                        <a
                            class="no-profile-button primary"
                            href="{{ url('/admin') }}"
                        >
                            <i
                                class="ph-duotone ph-layout"
                                aria-hidden="true"
                            ></i>

                            <span>
                                Abrir painel administrativo
                            </span>

                            <i
                                class="ph ph-arrow-right"
                                aria-hidden="true"
                            ></i>
                        </a>
                    @endif

                    <a
                        class="no-profile-button"
                        href="{{ route('home') }}"
                    >
                        <i
                            class="ph ph-house-line"
                            aria-hidden="true"
                        ></i>

                        <span>
                            Voltar ao início
                        </span>

                        <i
                            class="ph ph-arrow-right"
                            aria-hidden="true"
                        ></i>
                    </a>
                </div>

                <div class="no-profile-help">
                    <i
                        class="ph-duotone ph-info"
                        aria-hidden="true"
                    ></i>

                    <span>
                        Se você já é associado e acredita que
                        esta mensagem apareceu por engano,
                        procure um administrador da organização.
                    </span>
                </div>
            </aside>
        </div>

        <footer class="no-profile-footer">
            <i
                class="ph ph-shield-check"
                aria-hidden="true"
            ></i>

            <span>
                O vínculo deve ser realizado por um administrador
                para evitar que uma conta acesse dados de outro associado.
            </span>
        </footer>
    </section>
</main>
@endsection