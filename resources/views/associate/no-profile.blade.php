@extends('layouts.bento')

@section('title', 'Perfil não encontrado')
@section('page-title', 'Portal do Associado')
@section('user-role', 'Associado')

@section('content')
<style>
    .no-profile-page {
        display: grid;
        width: min(100%, 760px);
        min-width: 0;
        min-height: min(68dvh, 620px);
        grid-column: 1 / -1;
        place-items: center;
        margin: 0 auto;
        padding: 1rem 0;
    }

    .no-profile-page *,
    .no-profile-page *::before,
    .no-profile-page *::after {
        box-sizing: border-box;
    }

    .no-profile-card {
        position: relative;
        width: 100%;
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--color-border);
        border-left: 4px solid var(--color-warning);
        border-radius: 18px;
        background:
            linear-gradient(
                135deg,
                #fffbeb 0%,
                rgba(255, 255, 255, .98) 48%
            ),
            var(--color-surface);
        box-shadow: var(--shadow-lg);
    }

    .no-profile-card::before {
        position: absolute;
        top: -90px;
        right: -90px;
        width: 210px;
        height: 210px;
        border-radius: 50%;
        background: rgba(245, 158, 11, .055);
        content: "";
        pointer-events: none;
    }

    .no-profile-content {
        position: relative;
        z-index: 1;
        display: grid;
        justify-items: center;
        padding: clamp(1.35rem, 4vw, 2.3rem);
        text-align: center;
    }

    .no-profile-icon {
        display: grid;
        width: 68px;
        height: 68px;
        place-items: center;
        margin-bottom: 1rem;
        border: 1px solid rgba(217, 119, 6, .18);
        border-radius: 20px;
        background: #fef3c7;
        color: #b45309;
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .72),
            0 9px 24px rgba(217, 119, 6, .09);
    }

    .no-profile-icon i {
        font-size: 2rem;
    }

    .no-profile-title {
        max-width: 520px;
        margin: 0;
        color: var(--color-text);
        font-size: clamp(1.15rem, 3vw, 1.5rem);
        font-weight: 860;
        letter-spacing: -.035em;
        line-height: 1.25;
    }

    .no-profile-description {
        max-width: 540px;
        margin: .55rem 0 0;
        color: var(--color-text-secondary);
        font-size: .84rem;
        line-height: 1.62;
    }

    .no-profile-description strong {
        color: var(--color-text);
        font-weight: 800;
    }

    .no-profile-status {
        display: inline-flex;
        min-height: 32px;
        align-items: center;
        gap: .36rem;
        margin-top: 1rem;
        padding: .35rem .56rem;
        border: 1px solid rgba(217, 119, 6, .16);
        border-radius: 999px;
        background: rgba(255, 251, 235, .92);
        color: #92400e;
        font-size: .7rem;
        font-weight: 790;
    }

    .no-profile-status i {
        font-size: .9rem;
    }

    .no-profile-actions {
        display: flex;
        width: 100%;
        max-width: 470px;
        flex-wrap: wrap;
        justify-content: center;
        gap: .55rem;
        margin-top: 1.35rem;
    }

    .no-profile-button {
        display: inline-flex;
        min-height: 44px;
        flex: 1 1 180px;
        align-items: center;
        justify-content: center;
        gap: .42rem;
        padding: .58rem .78rem;
        border: 1px solid var(--color-border-strong);
        border-radius: 11px;
        background: var(--color-surface);
        color: var(--color-text-secondary);
        font-size: .78rem;
        font-weight: 790;
        text-decoration: none;
        transition:
            border-color 150ms ease,
            background 150ms ease,
            color 150ms ease,
            box-shadow 150ms ease,
            transform 150ms ease;
    }

    .no-profile-button:hover,
    .no-profile-button:focus-visible {
        border-color: rgba(34, 197, 94, .36);
        background: var(--color-primary-50);
        color: var(--color-primary-deep);
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
            0 8px 18px rgba(22, 163, 74, .15);
    }

    .no-profile-button.primary:hover,
    .no-profile-button.primary:focus-visible {
        color: #fff;
        box-shadow:
            0 11px 24px rgba(22, 163, 74, .21);
    }

    .no-profile-button i {
        font-size: 1rem;
    }

    .no-profile-footer {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        padding: .72rem 1rem;
        border-top: 1px solid var(--color-border);
        background: rgba(248, 250, 249, .86);
        color: var(--color-text-muted);
        font-size: .68rem;
        text-align: center;
    }

    .no-profile-footer i {
        flex: 0 0 auto;
        font-size: .86rem;
        color: var(--color-primary-dark);
    }

    @media (max-width: 560px) {
        .no-profile-page {
            min-height: 58dvh;
            padding: .5rem 0;
        }

        .no-profile-card {
            border-radius: 15px;
        }

        .no-profile-content {
            padding: 1.35rem 1rem;
        }

        .no-profile-icon {
            width: 60px;
            height: 60px;
            border-radius: 17px;
        }

        .no-profile-icon i {
            font-size: 1.75rem;
        }

        .no-profile-description {
            font-size: .78rem;
        }

        .no-profile-actions {
            display: grid;
            grid-template-columns: 1fr;
        }

        .no-profile-button {
            width: 100%;
            flex: none;
        }
    }
</style>

<main class="no-profile-page">
    <section
        class="no-profile-card"
        aria-labelledby="no-profile-title"
    >
        <div class="no-profile-content">
            <span
                class="no-profile-icon"
                aria-hidden="true"
            >
                <i class="ph-duotone ph-user-circle-minus"></i>
            </span>

            <h1
                class="no-profile-title"
                id="no-profile-title"
            >
                Perfil de associado não encontrado
            </h1>

            <p class="no-profile-description">
                Sua conta possui a função de
                <strong>Associado</strong>, mas ainda não está
                vinculada a um cadastro de associado nesta
                organização.
            </p>

            <span class="no-profile-status">
                <i
                    class="ph ph-clock-countdown"
                    aria-hidden="true"
                ></i>

                Vínculo necessário
            </span>

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

                        Abrir painel administrativo
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

                    Voltar ao início
                </a>
            </div>
        </div>

        <footer class="no-profile-footer">
            <i
                class="ph ph-info"
                aria-hidden="true"
            ></i>

            O vínculo deve ser realizado por um administrador da organização.
        </footer>
    </section>
</main>
@endsection