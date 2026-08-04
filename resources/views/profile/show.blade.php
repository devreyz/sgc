@extends('layouts.bento')

@section('title', 'Meu Perfil')
@section('page-title', 'Meu Perfil')
@section('user-role', 'Configurações da Conta')

@section('content')
<style>
    .profile-shell {
        --profile-primary: var(--color-primary, #22c55e);
        --profile-primary-dark: var(--color-primary-dark, #16a34a);
        --profile-primary-deep: var(--color-primary-deep, #15803d);

        --profile-surface: var(--color-surface, #ffffff);
        --profile-soft: var(--color-surface-soft, #f8faf9);
        --profile-muted: var(--color-surface-muted, #eef4f0);

        --profile-border: var(--color-border, #dce6df);
        --profile-border-strong: var(--color-border-strong, #c8d6cd);

        --profile-text: var(--color-text, #102018);
        --profile-secondary: var(--color-text-secondary, #52645a);
        --profile-faded: var(--color-text-muted, #809087);

        --profile-danger: var(--color-danger, #dc2626);

        --profile-shadow-sm:
            0 5px 18px rgba(15, 35, 24, .055);

        --profile-shadow:
            0 15px 42px rgba(15, 35, 24, .085);

        display: grid;
        width: min(100%, 980px);
        min-width: 0;
        grid-column: 1 / -1;
        gap: .75rem;
        margin: 0 auto;
        padding-bottom: 1.2rem;
        color: var(--profile-text);
    }

    .profile-shell *,
    .profile-shell *::before,
    .profile-shell *::after {
        box-sizing: border-box;
    }

    .profile-header {
        position: relative;
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .72rem;
        align-items: center;
        padding: .76rem .82rem;
        overflow: hidden;
        border: 1px solid var(--profile-border);
        border-left: 4px solid var(--profile-primary-dark);
        border-radius: 14px;
        background:
            linear-gradient(
                90deg,
                rgba(236, 253, 245, .78),
                rgba(255, 255, 255, .98) 44%
            ),
            var(--profile-surface);
        box-shadow: var(--profile-shadow-sm);
    }

    .profile-header-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        border: 1px solid rgba(34, 197, 94, .15);
        border-radius: 11px;
        background: #ecfdf5;
        color: var(--profile-primary-dark);
    }

    .profile-header-icon svg {
        width: 19px;
        height: 19px;
    }

    .profile-header-copy {
        min-width: 0;
    }

    .profile-kicker {
        display: flex;
        align-items: center;
        gap: .32rem;
        color: var(--profile-primary-dark);
        font-size: .59rem;
        font-weight: 820;
        letter-spacing: .065em;
        text-transform: uppercase;
    }

    .profile-kicker svg {
        width: 13px;
        height: 13px;
    }

    .profile-header h1 {
        margin: .12rem 0 0;
        color: var(--profile-text);
        font-size: clamp(1.02rem, 2vw, 1.26rem);
        font-weight: 860;
        letter-spacing: -.035em;
        line-height: 1.2;
    }

    .profile-header p {
        margin: .2rem 0 0;
        color: var(--profile-secondary);
        font-size: .64rem;
        line-height: 1.45;
    }

    .profile-layout {
        display: grid;
        grid-template-columns: minmax(260px, .72fr) minmax(0, 1.28fr);
        gap: .75rem;
        align-items: stretch;
    }

    .profile-card {
        min-width: 0;
        overflow: hidden;
        border: 1px solid var(--profile-border);
        border-radius: 15px;
        background: var(--profile-surface);
        box-shadow: var(--profile-shadow);
    }

    .profile-card-head {
        display: flex;
        min-height: 58px;
        align-items: center;
        gap: .55rem;
        padding: .65rem .72rem;
        border-bottom: 1px solid var(--profile-border);
        background:
            linear-gradient(
                180deg,
                var(--profile-soft),
                var(--profile-surface)
            );
    }

    .profile-card-icon {
        display: grid;
        width: 36px;
        height: 36px;
        flex: 0 0 auto;
        place-items: center;
        border-radius: 10px;
        background: #ecfdf5;
        color: var(--profile-primary-dark);
    }

    .profile-card-icon svg {
        width: 17px;
        height: 17px;
    }

    .profile-card-head h2 {
        margin: 0;
        color: var(--profile-text);
        font-size: .82rem;
        font-weight: 830;
        letter-spacing: -.02em;
    }

    .profile-card-head p {
        margin: .12rem 0 0;
        color: var(--profile-faded);
        font-size: .57rem;
        line-height: 1.4;
    }

    .profile-photo-body {
        display: grid;
        place-items: center;
        gap: .8rem;
        padding: 1rem;
        text-align: center;
    }

    .profile-avatar-wrap {
        position: relative;
        width: 112px;
        height: 112px;
    }

    .profile-avatar {
        display: grid;
        width: 112px;
        height: 112px;
        place-items: center;
        overflow: hidden;
        border: 3px solid rgba(34, 197, 94, .22);
        border-radius: 28px;
        background:
            linear-gradient(
                135deg,
                var(--profile-primary),
                var(--profile-primary-dark)
            );
        color: #fff;
        font-size: 1.8rem;
        font-weight: 850;
        object-fit: cover;
        box-shadow:
            0 12px 28px rgba(22, 163, 74, .16);
        transition: opacity 150ms ease;
    }

    .profile-avatar.is-processing {
        opacity: .46;
    }

    .profile-avatar-trigger {
        position: absolute;
        right: -4px;
        bottom: -4px;
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        border: 3px solid var(--profile-surface);
        border-radius: 12px;
        background:
            linear-gradient(
                135deg,
                var(--profile-primary),
                var(--profile-primary-dark)
            );
        color: #fff;
        cursor: pointer;
        box-shadow:
            0 8px 18px rgba(22, 163, 74, .19);
        transition:
            transform 150ms ease,
            box-shadow 150ms ease;
    }

    .profile-avatar-trigger:hover,
    .profile-avatar-trigger:focus-visible {
        outline: none;
        box-shadow:
            0 11px 22px rgba(22, 163, 74, .23);
        transform: translateY(-1px);
    }

    .profile-avatar-trigger svg {
        width: 17px;
        height: 17px;
    }

    .profile-photo-copy strong,
    .profile-photo-copy span {
        display: block;
    }

    .profile-photo-copy strong {
        color: var(--profile-text);
        font-size: .75rem;
        font-weight: 820;
    }

    .profile-photo-copy span {
        max-width: 280px;
        margin-top: .18rem;
        color: var(--profile-faded);
        font-size: .59rem;
        line-height: 1.45;
    }

    .profile-photo-actions {
        display: flex;
        width: 100%;
        flex-wrap: wrap;
        justify-content: center;
        gap: .42rem;
    }

    .profile-button {
        display: inline-flex;
        min-height: 38px;
        align-items: center;
        justify-content: center;
        gap: .34rem;
        padding: .42rem .6rem;
        border: 1px solid var(--profile-primary-dark);
        border-radius: 9px;
        background:
            linear-gradient(
                135deg,
                var(--profile-primary),
                var(--profile-primary-dark)
            );
        color: #fff;
        cursor: pointer;
        font: inherit;
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

    .profile-button:hover:not(:disabled),
    .profile-button:focus-visible:not(:disabled) {
        color: #fff;
        outline: none;
        box-shadow:
            0 10px 21px rgba(22, 163, 74, .19);
        transform: translateY(-1px);
    }

    .profile-button svg {
        width: 15px;
        height: 15px;
    }

    .profile-button.secondary {
        border-color: var(--profile-border-strong);
        background: var(--profile-surface);
        color: var(--profile-text);
        box-shadow: none;
    }

    .profile-button.secondary:hover,
    .profile-button.secondary:focus-visible {
        border-color: rgba(34, 197, 94, .38);
        background: var(--profile-soft);
        color: var(--profile-primary-dark);
        box-shadow: none;
    }

    .profile-button.danger {
        border-color: #fecaca;
        background: var(--profile-surface);
        color: var(--profile-danger);
        box-shadow: none;
    }

    .profile-button.danger:hover,
    .profile-button.danger:focus-visible {
        border-color: var(--profile-danger);
        background: var(--profile-danger);
        color: #fff;
    }

    .profile-button:disabled {
        cursor: not-allowed;
        opacity: .5;
        transform: none;
    }

    .profile-details {
        display: grid;
        gap: .52rem;
        padding: .72rem;
    }

    .profile-detail {
        display: grid;
        min-width: 0;
        grid-template-columns: auto minmax(0, 1fr);
        gap: .6rem;
        align-items: center;
        padding: .62rem;
        border: 1px solid var(--profile-border);
        border-radius: 11px;
        background: var(--profile-soft);
    }

    .profile-detail-icon {
        display: grid;
        width: 36px;
        height: 36px;
        place-items: center;
        border-radius: 10px;
        background: var(--profile-surface);
        color: var(--profile-primary-dark);
        box-shadow: inset 0 0 0 1px var(--profile-border);
    }

    .profile-detail-icon svg {
        width: 17px;
        height: 17px;
    }

    .profile-detail-copy {
        min-width: 0;
    }

    .profile-detail-copy span,
    .profile-detail-copy strong {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .profile-detail-copy span {
        color: var(--profile-faded);
        font-size: .56rem;
        font-weight: 690;
    }

    .profile-detail-copy strong {
        margin-top: .1rem;
        color: var(--profile-text);
        font-size: .72rem;
        font-weight: 810;
    }

    .profile-account-note {
        display: flex;
        align-items: flex-start;
        gap: .45rem;
        padding: .62rem;
        border: 1px solid var(--profile-border);
        border-radius: 10px;
        background: var(--profile-surface);
        color: var(--profile-secondary);
        font-size: .59rem;
        line-height: 1.5;
    }

    .profile-account-note svg {
        width: 15px;
        height: 15px;
        flex: 0 0 auto;
        margin-top: .03rem;
        color: var(--profile-primary-dark);
    }

    .profile-footer-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: .42rem;
        padding: .68rem .72rem;
        border-top: 1px solid var(--profile-border);
        background: var(--profile-soft);
    }

    .profile-status {
        display: none;
        align-items: flex-start;
        gap: .45rem;
        padding: .65rem .7rem;
        border: 1px solid var(--profile-border);
        border-radius: 11px;
        background: var(--profile-soft);
        color: var(--profile-secondary);
        font-size: .64rem;
        font-weight: 650;
        line-height: 1.5;
        box-shadow: var(--profile-shadow-sm);
    }

    .profile-status.show {
        display: flex;
    }

    .profile-status.error {
        border-color: #fecaca;
        background: #fff7f7;
        color: #991b1b;
    }

    .profile-status.success {
        border-color: #bbf7d0;
        background: #ecfdf5;
        color: #047857;
    }

    .profile-status svg {
        width: 16px;
        height: 16px;
        flex: 0 0 auto;
        margin-top: .02rem;
    }

    .profile-loading {
        position: fixed;
        z-index: 3000;
        inset: 0;
        display: none;
        place-items: center;
        padding: 1rem;
        background: rgba(244, 248, 246, .82);
        backdrop-filter: blur(5px);
    }

    .profile-loading.show {
        display: grid;
    }

    .profile-loading-card {
        display: grid;
        min-width: 160px;
        place-items: center;
        gap: .48rem;
        padding: .82rem 1rem;
        border: 1px solid var(--profile-border);
        border-radius: 13px;
        background: var(--profile-surface);
        color: var(--profile-secondary);
        font-size: .65rem;
        font-weight: 760;
        box-shadow: var(--profile-shadow);
        text-align: center;
    }

    .profile-loading-card svg {
        width: 23px;
        height: 23px;
        color: var(--profile-primary-dark);
        animation: profile-spin .75s linear infinite;
    }

    .profile-dialog {
        position: fixed;
        z-index: 2500;
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

    .profile-dialog:not([open]) {
        display: none;
    }

    .profile-dialog[open] {
        display: grid;
        place-items: center;
    }

    .profile-dialog::backdrop {
        background: rgba(8, 24, 15, .65);
        backdrop-filter: blur(4px);
    }

    .profile-dialog-panel {
        position: relative;
        width: min(100%, 420px);
        overflow: hidden;
        border: 1px solid var(--profile-border);
        border-radius: 15px;
        background: var(--profile-surface);
        box-shadow:
            0 28px 82px rgba(8, 24, 15, .28);
    }

    .profile-dialog-panel::before {
        position: absolute;
        top: 0;
        right: 0;
        left: 0;
        height: 4px;
        background:
            linear-gradient(
                90deg,
                #ef4444,
                #b91c1c
            );
        content: "";
    }

    .profile-dialog-head {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        gap: .58rem;
        align-items: center;
        padding: .78rem;
        border-bottom: 1px solid var(--profile-border);
        background:
            linear-gradient(
                180deg,
                var(--profile-soft),
                var(--profile-surface)
            );
    }

    .profile-dialog-icon {
        display: grid;
        width: 37px;
        height: 37px;
        place-items: center;
        border-radius: 10px;
        background: #fef2f2;
        color: var(--profile-danger);
    }

    .profile-dialog-icon svg {
        width: 18px;
        height: 18px;
    }

    .profile-dialog-head h2 {
        margin: 0;
        color: var(--profile-text);
        font-size: .8rem;
        font-weight: 830;
    }

    .profile-dialog-head p {
        margin: .13rem 0 0;
        color: var(--profile-faded);
        font-size: .57rem;
        line-height: 1.42;
    }

    .profile-dialog-close {
        display: grid;
        width: 33px;
        height: 33px;
        place-items: center;
        border: 1px solid var(--profile-border);
        border-radius: 9px;
        background: var(--profile-surface);
        color: var(--profile-secondary);
        cursor: pointer;
    }

    .profile-dialog-close:hover,
    .profile-dialog-close:focus-visible {
        border-color: #fecaca;
        color: var(--profile-danger);
        outline: none;
    }

    .profile-dialog-close svg {
        width: 15px;
        height: 15px;
    }

    .profile-dialog-body {
        padding: .78rem;
        color: var(--profile-secondary);
        font-size: .63rem;
        line-height: 1.5;
    }

    .profile-dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: .42rem;
        padding: .65rem .78rem .78rem;
        border-top: 1px solid var(--profile-border);
        background: var(--profile-soft);
    }

    @keyframes profile-spin {
        to {
            transform: rotate(360deg);
        }
    }

    @media (max-width: 760px) {
        .profile-layout {
            grid-template-columns: 1fr;
        }

        .profile-photo-body {
            padding: .85rem;
        }
    }

    @media (max-width: 520px) {
        .profile-shell {
            gap: .65rem;
        }

        .profile-header {
            padding: .66rem;
            border-radius: 12px;
        }

        .profile-card {
            border-radius: 13px;
        }

        .profile-avatar-wrap,
        .profile-avatar {
            width: 98px;
            height: 98px;
        }

        .profile-avatar {
            border-radius: 24px;
            font-size: 1.55rem;
        }

        .profile-photo-actions,
        .profile-footer-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .profile-button {
            width: 100%;
        }

        .profile-dialog-actions {
            align-items: stretch;
            flex-direction: column-reverse;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: .01ms !important;
            animation-iteration-count: 1 !important;
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }
</style>

<div
    class="profile-loading"
    id="profile-loading"
    role="status"
    aria-live="polite"
    aria-hidden="true"
>
    <div class="profile-loading-card">
        <i data-lucide="loader-circle" aria-hidden="true"></i>
        <span id="profile-loading-label">Processando imagem...</span>
    </div>
</div>

<div class="profile-shell">
    <header class="profile-header">
        <span class="profile-header-icon" aria-hidden="true">
            <i data-lucide="circle-user-round"></i>
        </span>

        <div class="profile-header-copy">
            <div class="profile-kicker">
                <i data-lucide="shield-check"></i>
                Minha conta
            </div>

            <h1>Meu perfil</h1>

            <p>
                Consulte seus dados e mantenha sua foto de perfil atualizada.
            </p>
        </div>
    </header>

    @if(session('error'))
        <div class="profile-status show error" role="alert">
            <i data-lucide="circle-alert" aria-hidden="true"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if(session('success'))
        <div class="profile-status show success" role="status">
            <i data-lucide="circle-check" aria-hidden="true"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div
        class="profile-status"
        id="profile-status"
        role="status"
        aria-live="polite"
    >
        <i
            data-lucide="info"
            id="profile-status-icon"
            aria-hidden="true"
        ></i>

        <span id="profile-status-message"></span>
    </div>

    <div class="profile-layout">
        <section class="profile-card">
            <header class="profile-card-head">
                <span class="profile-card-icon" aria-hidden="true">
                    <i data-lucide="image"></i>
                </span>

                <div>
                    <h2>Foto de perfil</h2>
                    <p>Imagem exibida nos menus e registros.</p>
                </div>
            </header>

            <div class="profile-photo-body">
                <div class="profile-avatar-wrap">
                    @if($user->avatar)
                        <img
                            id="avatar-preview"
                            class="profile-avatar"
                            src="{{ Storage::url($user->avatar) }}"
                            alt="Foto de {{ $user->name }}"
                        >
                    @else
                        <div
                            id="avatar-preview"
                            class="profile-avatar"
                            aria-label="Iniciais de {{ $user->name }}"
                        >
                            {{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}
                        </div>
                    @endif

                    <label
                        for="avatar-upload"
                        class="profile-avatar-trigger"
                        aria-label="Alterar foto de perfil"
                        title="Alterar foto"
                    >
                        <i data-lucide="camera"></i>
                    </label>
                </div>

                <div class="profile-photo-copy">
                    <strong>Alterar foto</strong>

                    <span>
                        Selecione uma imagem. Ela será otimizada e salva
                        automaticamente em formato WebP.
                    </span>
                </div>

                <div class="profile-photo-actions">
                    <label
                        for="avatar-upload"
                        class="profile-button"
                    >
                        <i data-lucide="upload"></i>
                        Escolher imagem
                    </label>

                    @if($user->avatar)
                        <button
                            type="button"
                            class="profile-button danger"
                            id="open-remove-avatar"
                        >
                            <i data-lucide="trash-2"></i>
                            Remover foto
                        </button>
                    @endif
                </div>
            </div>

            <form
                id="profile-form"
                action="{{ route('profile.update', [
                    'tenant' => $tenant->slug ?? '',
                ]) }}"
                method="POST"
                enctype="multipart/form-data"
                hidden
            >
                @csrf

                <input
                    type="hidden"
                    name="name"
                    value="{{ $user->name }}"
                >

                <input
                    type="hidden"
                    name="email"
                    value="{{ $user->email }}"
                >

                <input
                    type="file"
                    id="avatar-upload"
                    name="avatar_raw"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                >

                <input
                    type="hidden"
                    id="avatar-compressed"
                    name="avatar"
                >
            </form>
        </section>

        <section class="profile-card">
            <header class="profile-card-head">
                <span class="profile-card-icon" aria-hidden="true">
                    <i data-lucide="id-card"></i>
                </span>

                <div>
                    <h2>Dados da conta</h2>
                    <p>Informações vinculadas ao seu acesso.</p>
                </div>
            </header>

            <div class="profile-details">
                <article class="profile-detail">
                    <span class="profile-detail-icon" aria-hidden="true">
                        <i data-lucide="user-round"></i>
                    </span>

                    <div class="profile-detail-copy">
                        <span>Nome</span>
                        <strong title="{{ $user->name }}">
                            {{ $user->name }}
                        </strong>
                    </div>
                </article>

                <article class="profile-detail">
                    <span class="profile-detail-icon" aria-hidden="true">
                        <i data-lucide="mail"></i>
                    </span>

                    <div class="profile-detail-copy">
                        <span>E-mail</span>
                        <strong title="{{ $user->email }}">
                            {{ $user->email }}
                        </strong>
                    </div>
                </article>

                @if($tenant)
                    <article class="profile-detail">
                        <span class="profile-detail-icon" aria-hidden="true">
                            <i data-lucide="building-2"></i>
                        </span>

                        <div class="profile-detail-copy">
                            <span>Organização atual</span>
                            <strong title="{{ $tenant->name }}">
                                {{ $tenant->name }}
                            </strong>
                        </div>
                    </article>
                @endif

                <div class="profile-account-note">
                    <i data-lucide="lock-keyhole"></i>

                    <span>
                        Nome e e-mail são dados protegidos da conta.
                        Alterações administrativas devem ser realizadas por
                        usuários autorizados.
                    </span>
                </div>
            </div>

            <footer class="profile-footer-actions">
                <a
                    href="{{ route('security.index') }}"
                    class="profile-button secondary"
                >
                    <i data-lucide="key-round"></i>
                    Segurança e acesso
                </a>

                <a
                    href="{{ url('/') }}"
                    class="profile-button"
                >
                    <i data-lucide="arrow-left"></i>
                    Voltar ao sistema
                </a>
            </footer>
        </section>
    </div>
</div>

@if($user->avatar)
    <dialog
        class="profile-dialog"
        id="remove-avatar-dialog"
        aria-labelledby="remove-avatar-title"
    >
        <div class="profile-dialog-panel">
            <header class="profile-dialog-head">
                <span class="profile-dialog-icon" aria-hidden="true">
                    <i data-lucide="trash-2"></i>
                </span>

                <div>
                    <h2 id="remove-avatar-title">Remover foto</h2>

                    <p>
                        Confirme a remoção da foto atual.
                    </p>
                </div>

                <button
                    type="button"
                    class="profile-dialog-close"
                    id="close-remove-avatar"
                    aria-label="Fechar"
                >
                    <i data-lucide="x"></i>
                </button>
            </header>

            <div class="profile-dialog-body">
                A foto será removida do seu perfil e suas iniciais voltarão
                a ser exibidas. Esta ação não altera os demais dados da conta.
            </div>

            <footer class="profile-dialog-actions">
                <button
                    type="button"
                    class="profile-button secondary"
                    id="cancel-remove-avatar"
                >
                    Cancelar
                </button>

                <form
                    action="{{ route('profile.remove-avatar', [
                        'tenant' => $tenant->slug ?? '',
                    ]) }}"
                    method="POST"
                    id="remove-avatar-form"
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        class="profile-button danger"
                    >
                        <i data-lucide="trash-2"></i>
                        Remover foto
                    </button>
                </form>
            </footer>
        </div>
    </dialog>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const avatarUpload =
        document.getElementById('avatar-upload');

    let avatarPreview =
        document.getElementById('avatar-preview');

    const avatarCompressed =
        document.getElementById('avatar-compressed');

    const profileForm =
        document.getElementById('profile-form');

    const loading =
        document.getElementById('profile-loading');

    const loadingLabel =
        document.getElementById('profile-loading-label');

    const statusBox =
        document.getElementById('profile-status');

    const statusIcon =
        document.getElementById('profile-status-icon');

    const statusMessage =
        document.getElementById('profile-status-message');

    const removeDialog =
        document.getElementById('remove-avatar-dialog');

    function renderLucideIcons() {
        if (window.lucide?.createIcons) {
            window.lucide.createIcons({
                attrs: {
                    'stroke-width': 2,
                    'aria-hidden': 'true',
                },
            });

            return true;
        }

        return false;
    }

    function ensureLucideIcons() {
        if (renderLucideIcons()) {
            return;
        }

        const existingScript =
            document.querySelector(
                'script[data-profile-lucide-fallback]'
            );

        if (existingScript) {
            existingScript.addEventListener(
                'load',
                renderLucideIcons,
                {
                    once: true,
                }
            );

            return;
        }

        const script =
            document.createElement('script');

        script.src =
            'https://unpkg.com/lucide@latest/dist/umd/lucide.js';

        script.async = true;
        script.dataset.profileLucideFallback = 'true';

        script.addEventListener(
            'load',
            renderLucideIcons,
            {
                once: true,
            }
        );

        document.head.appendChild(script);
    }

    function busy(
        value,
        label = 'Processando imagem...'
    ) {
        loading?.classList.toggle('show', value);

        loading?.setAttribute(
            'aria-hidden',
            value ? 'false' : 'true'
        );

        if (loadingLabel) {
            loadingLabel.textContent = label;
        }
    }

    function showStatus(
        message,
        type = 'error'
    ) {
        if (
            !statusBox
            || !statusMessage
            || !statusIcon
        ) {
            return;
        }

        const icon = type === 'success'
            ? 'circle-check'
            : 'circle-alert';

        statusBox.className =
            `profile-status show ${type}`;

        statusIcon.setAttribute(
            'data-lucide',
            icon
        );

        statusMessage.textContent =
            message
            || 'Não foi possível concluir a operação.';

        renderLucideIcons();

        statusBox.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest',
        });
    }

    function setPreviewProcessing(value) {
        avatarPreview?.classList.toggle(
            'is-processing',
            value
        );
    }

    function validateImage(file) {
        const acceptedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
        ];

        if (!acceptedTypes.includes(file.type)) {
            throw new Error(
                'Selecione uma imagem JPG, PNG, WebP ou GIF.'
            );
        }

        const maxFileSize = 12 * 1024 * 1024;

        if (file.size > maxFileSize) {
            throw new Error(
                'A imagem deve ter no máximo 12 MB.'
            );
        }
    }

    function loadImage(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.addEventListener(
                'load',
                event => {
                    const image = new Image();

                    image.addEventListener(
                        'load',
                        () => resolve(image),
                        {
                            once: true,
                        }
                    );

                    image.addEventListener(
                        'error',
                        () => reject(
                            new Error(
                                'Não foi possível abrir a imagem.'
                            )
                        ),
                        {
                            once: true,
                        }
                    );

                    image.src = event.target.result;
                },
                {
                    once: true,
                }
            );

            reader.addEventListener(
                'error',
                () => reject(
                    new Error(
                        'Não foi possível ler o arquivo.'
                    )
                ),
                {
                    once: true,
                }
            );

            reader.readAsDataURL(file);
        });
    }

    async function compressImageToWebP(file) {
        const image = await loadImage(file);

        const maxSize = 800;

        let width = image.naturalWidth;
        let height = image.naturalHeight;

        if (
            !width
            || !height
        ) {
            throw new Error(
                'A imagem selecionada não possui dimensões válidas.'
            );
        }

        const ratio = Math.min(
            1,
            maxSize / Math.max(width, height)
        );

        width = Math.max(
            1,
            Math.round(width * ratio)
        );

        height = Math.max(
            1,
            Math.round(height * ratio)
        );

        const canvas =
            document.createElement('canvas');

        canvas.width = width;
        canvas.height = height;

        const context =
            canvas.getContext('2d', {
                alpha: false,
            });

        if (!context) {
            throw new Error(
                'O navegador não conseguiu processar a imagem.'
            );
        }

        context.fillStyle = '#ffffff';
        context.fillRect(
            0,
            0,
            width,
            height
        );

        context.drawImage(
            image,
            0,
            0,
            width,
            height
        );

        return new Promise((resolve, reject) => {
            canvas.toBlob(
                blob => {
                    if (blob) {
                        resolve(blob);
                        return;
                    }

                    reject(
                        new Error(
                            'Não foi possível converter a imagem.'
                        )
                    );
                },
                'image/webp',
                .84
            );
        });
    }

    function blobToDataUrl(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.addEventListener(
                'load',
                () => resolve(reader.result),
                {
                    once: true,
                }
            );

            reader.addEventListener(
                'error',
                () => reject(
                    new Error(
                        'Não foi possível preparar a imagem.'
                    )
                ),
                {
                    once: true,
                }
            );

            reader.readAsDataURL(blob);
        });
    }

    function updateAvatarPreview(dataUrl) {
        if (!avatarPreview) {
            return;
        }

        if (avatarPreview.tagName === 'IMG') {
            avatarPreview.src = dataUrl;
            avatarPreview.classList.remove(
                'is-processing'
            );

            return;
        }

        const newImage =
            document.createElement('img');

        newImage.id = 'avatar-preview';
        newImage.className = 'profile-avatar';
        newImage.src = dataUrl;
        newImage.alt =
            @json('Foto de ' . $user->name);

        avatarPreview.replaceWith(newImage);
        avatarPreview = newImage;
    }

    avatarUpload?.addEventListener(
        'change',
        async event => {
            const file =
                event.target.files?.[0];

            if (!file) {
                return;
            }

            busy(true);
            setPreviewProcessing(true);

            try {
                validateImage(file);

                const compressedBlob =
                    await compressImageToWebP(file);

                const dataUrl =
                    await blobToDataUrl(compressedBlob);

                updateAvatarPreview(dataUrl);
                avatarCompressed.value = dataUrl;

                busy(
                    true,
                    'Salvando foto de perfil...'
                );

                if (
                    typeof profileForm.requestSubmit
                    === 'function'
                ) {
                    profileForm.requestSubmit();
                } else {
                    profileForm.submit();
                }
            } catch (error) {
                busy(false);
                setPreviewProcessing(false);

                avatarUpload.value = '';

                showStatus(
                    error.message
                    || 'Não foi possível processar a imagem.',
                    'error'
                );
            }
        }
    );

    document
        .getElementById('open-remove-avatar')
        ?.addEventListener(
            'click',
            () => removeDialog?.showModal()
        );

    document
        .getElementById('close-remove-avatar')
        ?.addEventListener(
            'click',
            () => removeDialog?.close()
        );

    document
        .getElementById('cancel-remove-avatar')
        ?.addEventListener(
            'click',
            () => removeDialog?.close()
        );

    removeDialog?.addEventListener(
        'click',
        event => {
            if (event.target === removeDialog) {
                removeDialog.close();
            }
        }
    );

    document
        .getElementById('remove-avatar-form')
        ?.addEventListener(
            'submit',
            () => busy(
                true,
                'Removendo foto de perfil...'
            )
        );

    document.addEventListener(
        'DOMContentLoaded',
        ensureLucideIcons,
        {
            once: true,
        }
    );

    window.addEventListener(
        'load',
        ensureLucideIcons,
        {
            once: true,
        }
    );

    ensureLucideIcons();
})();
</script>
@endpush