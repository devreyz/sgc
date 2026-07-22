<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Entrar - {{ config('app.name', 'ZeCoop SGC') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">

    <meta name="theme-color" content="#16803d">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root{
            --green:#22c55e;--green-dark:#16a34a;--green-deep:#15803d;--green-soft:#f0fdf4;
            --surface:#fff;--soft:#f8fafc;--muted:#eef4f0;
            --text:#0f172a;--text-2:#475569;--text-3:#64748b;
            --border:#e2e8f0;--border-strong:#cbd5e1;
            --danger:#dc2626;--danger-soft:#fef2f2;
            --shadow:0 16px 42px rgba(15,23,42,.09)
        }

        *,*::before,*::after{box-sizing:border-box}
        html{min-height:100%;background:#eff7f2;-webkit-text-size-adjust:100%}
        body{
            min-width:320px;min-height:100dvh;margin:0;overflow-x:hidden;
            background:linear-gradient(180deg,#f7fbf8 0%,#f3f7f4 38%,#eef4f0 100%);
            color:var(--text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            -webkit-font-smoothing:antialiased
        }
        body::before{
            position:fixed;inset:0;content:"";pointer-events:none;
            background-image:
                linear-gradient(rgba(16,32,24,.026) 1px,transparent 1px),
                linear-gradient(90deg,rgba(16,32,24,.026) 1px,transparent 1px);
            background-size:24px 24px;
            mask-image:linear-gradient(to bottom,rgba(0,0,0,.72),transparent 82%)
        }
        button,input{font:inherit}

        .login-page{
            position:relative;z-index:1;display:grid;min-height:100dvh;place-items:center;
            padding:max(1rem,env(safe-area-inset-top)) max(1rem,env(safe-area-inset-right))
                    max(1rem,env(safe-area-inset-bottom)) max(1rem,env(safe-area-inset-left))
        }

        .login-shell{
            display:grid;width:min(100%,480px);grid-template-columns:minmax(0,1fr);
            overflow:hidden;border:1px solid var(--border);border-radius:8px;
            background:#fff;box-shadow:var(--shadow)
        }

        .login-visual{
            position:relative;display:none;min-height:590px;justify-content:flex-end;flex-direction:column;
            overflow:hidden;padding:2rem 2rem 2.4rem;
            background:
                radial-gradient(circle at 76% 14%,rgba(255,255,255,.18),transparent 15rem),
                linear-gradient(145deg,var(--green) 0%,var(--green-dark) 58%,var(--green-deep) 100%);
            color:#fff
        }
        .login-visual::before{
            position:absolute;inset:0;content:"";pointer-events:none;
            background:
                linear-gradient(115deg,rgba(255,255,255,.11),transparent 42%),
                radial-gradient(circle at 8% 110%,rgba(255,255,255,.13),transparent 18rem)
        }
        .visual-wave{position:absolute;right:0;bottom:-1px;left:0;width:100%;height:94px;color:rgba(255,255,255,.10);pointer-events:none}
        .visual-brand,.visual-content,.visual-cards{position:relative;z-index:2}

        .visual-brand{position:absolute;top:1.5rem;left:1.5rem;display:inline-flex;align-items:center;gap:.65rem}
        .brand-icon{
            display:grid;width:42px;height:42px;place-items:center;border:1px solid rgba(255,255,255,.18);
            border-radius:14px;background:rgba(255,255,255,.12);backdrop-filter:blur(12px)
        }
        .brand-icon svg{width:22px;height:22px}
        .visual-brand strong,.visual-brand span{display:block}
        .visual-brand strong{font-size:.95rem;font-weight:800}
        .visual-brand span span{margin-top:.14rem;color:rgba(255,255,255,.68);font-size:.68rem;font-weight:600}

        .visual-content{max-width:520px;margin-bottom:1.25rem}
        .visual-content h1{margin:0;font-size:clamp(2rem,4vw,3.35rem);font-weight:800;letter-spacing:-.05em;line-height:1.05}
        .visual-content p{max-width:460px;margin:.75rem 0 0;color:rgba(255,255,255,.74);font-size:.95rem;line-height:1.65}

        .visual-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.55rem;margin-bottom:3.4rem}
        .visual-card{padding:.72rem;border:1px solid rgba(255,255,255,.15);border-radius:15px;background:rgba(255,255,255,.10);backdrop-filter:blur(12px)}
        .visual-card svg{width:18px;height:18px;margin-bottom:.5rem}
        .visual-card strong{display:block;font-size:.72rem;font-weight:760;line-height:1.25}

        .login-panel{display:flex;justify-content:center;flex-direction:column;padding:2rem;background:#fff}
        .mobile-brand{display:flex;align-items:center;gap:.6rem;margin-bottom:1.5rem}
        .mobile-brand .brand-icon{border-color:var(--border);background:var(--green-soft);color:var(--green-dark)}
        .mobile-brand strong{font-size:.94rem;font-weight:800}

        .login-heading{margin-bottom:1.35rem}
        .login-heading h2{margin:0;font-size:1.65rem;font-weight:800;letter-spacing:-.035em}
        .login-heading p{margin:.38rem 0 0;color:var(--text-2);font-size:.9rem}

        .error-box{
            display:flex;align-items:flex-start;gap:.65rem;margin-bottom:.8rem;padding:.72rem;
            border:1px solid rgba(220,38,38,.22);border-radius:14px;background:var(--danger-soft);color:#991b1b
        }
        .error-icon{display:grid;width:32px;height:32px;flex:0 0 auto;place-items:center;border-radius:10px;background:#fee2e2;color:var(--danger)}
        .error-icon svg{width:16px;height:16px}
        .error-box p{margin:.05rem 0 0;font-size:.78rem;font-weight:620;line-height:1.5}

        .login-actions{display:grid;gap:.65rem}
        .login-button{
            display:flex;width:100%;min-height:50px;align-items:center;justify-content:center;gap:.72rem;
            padding:.78rem 1rem;border:1px solid var(--border);border-radius:14px;background:#fff;color:var(--text);
            cursor:pointer;font-size:.92rem;font-weight:740;text-decoration:none;
            transition:transform .15s ease,border-color .15s ease,box-shadow .15s ease,background .15s ease
        }
        .login-button:hover,.login-button:focus-visible{
            border-color:rgba(32,169,87,.42);outline:none;box-shadow:0 10px 24px rgba(18,48,30,.08);transform:translateY(-1px)
        }
        .login-button:disabled{cursor:not-allowed;opacity:.52;transform:none}
        .login-button.primary{
            border-color:var(--green-dark);background:var(--green-dark);
            color:#fff;box-shadow:0 10px 24px rgba(32,169,87,.18)
        }
        .login-button.primary:hover{box-shadow:0 14px 30px rgba(32,169,87,.24)}
        .login-button-icon,.passkey-icon{display:grid;width:27px;height:27px;flex:0 0 auto;place-items:center}
        .login-button-icon svg,.passkey-icon svg{width:23px;height:23px}

        .passkey-helper{display:flex;align-items:center;justify-content:center;gap:.35rem;margin-top:.62rem;color:var(--text-3);font-size:.72rem;text-align:center}
        .passkey-helper svg{width:14px;height:14px}

        .divider{display:flex;align-items:center;gap:.7rem;margin:1rem 0;color:var(--text-3);font-size:.72rem;font-weight:650}
        .divider::before,.divider::after{height:1px;flex:1;background:var(--border);content:""}

        .panel-link{
            display:flex;min-height:44px;align-items:center;justify-content:space-between;gap:.7rem;
            padding:.66rem .72rem;border:1px solid var(--border);border-radius:13px;background:var(--soft);
            color:var(--text-2);font-size:.8rem;font-weight:680;text-decoration:none
        }
        .panel-link:hover{border-color:rgba(32,169,87,.32);background:var(--green-soft);color:var(--green-dark)}
        .panel-link span{display:inline-flex;align-items:center;gap:.48rem}
        .panel-link svg{width:16px;height:16px}

        .login-footer{display:flex;align-items:center;justify-content:space-between;gap:.8rem;margin-top:1.35rem;color:var(--text-3);font-size:.72rem}
        .login-footer a{color:var(--text-2);font-weight:650;text-decoration:none}
        .login-footer a:hover{color:var(--green-dark)}

        .login-status{
            display:none;align-items:center;gap:.6rem;margin-top:.75rem;padding:.68rem .72rem;
            border:1px solid var(--border);border-radius:13px;background:var(--soft);
            color:var(--text-2);font-size:.76rem;font-weight:650
        }
        .login-status.show{display:flex}
        .status-spinner{
            width:19px;height:19px;flex:0 0 auto;border:2px solid rgba(32,169,87,.18);
            border-top-color:var(--green-dark);border-radius:999px;animation:spin .72s linear infinite
        }
        @keyframes spin{to{transform:rotate(360deg)}}

        @media(max-width:820px){
            .login-shell{width:min(100%,440px);grid-template-columns:1fr;border-radius:8px}
            .login-visual{display:none}
            .login-panel{padding:1.5rem}
            .mobile-brand{display:flex}
        }

        @media(max-width:480px){
            .login-page{align-items:flex-start;padding:.7rem;padding-top:max(.7rem,env(safe-area-inset-top))}
            .login-shell{border-radius:8px}
            .login-panel{padding:1.2rem}
            .login-heading h2{font-size:1.5rem}
            .login-footer{align-items:flex-start;flex-direction:column}
        }

        @media(prefers-reduced-motion:reduce){
            *,*::before,*::after{transition-duration:.01ms!important;animation-duration:.01ms!important;animation-iteration-count:1!important}
        }
    </style>
</head>

@php
    $googleLoginUrl = \Illuminate\Support\Facades\Route::has('auth.google')
        ? route('auth.google')
        : url('/auth/google');

    /*
     * Caso suas rotas usem outros nomes, altere apenas estes dois valores.
     * options deve retornar PublicKeyCredentialRequestOptions em JSON.
     * verify deve validar a credencial e retornar uma URL de redirecionamento.
     */
    $passkeyOptionsUrl = \Illuminate\Support\Facades\Route::has('auth.passkey.options')
        ? route('auth.passkey.options')
        : url('/auth/passkey/options');

    $passkeyVerifyUrl = \Illuminate\Support\Facades\Route::has('auth.passkey.verify')
        ? route('auth.passkey.verify')
        : url('/auth/passkey/verify');
@endphp

<body>
<main class="login-page">
    <section class="login-shell">
        <aside class="login-visual">
            <svg class="visual-wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
                <path fill="currentColor" d="M0,64L60,69.3C120,75,240,85,360,80C480,75,600,53,720,53.3C840,53,960,75,1080,80C1200,85,1320,75,1380,69.3L1440,64L1440,120L0,120Z"></path>
            </svg>

            <div class="visual-brand">
                <span class="brand-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18"></path>
                        <path d="M6 21V7l6-4 6 4v14"></path>
                        <path d="M9 9h.01"></path>
                        <path d="M15 9h.01"></path>
                        <path d="M9 13h.01"></path>
                        <path d="M15 13h.01"></path>
                        <path d="M9 17h6"></path>
                    </svg>
                </span>

                <span>
                    <strong>{{ config('app.name', 'ZeCoop SGC') }}</strong>
                    <span>Gestão conectada</span>
                </span>
            </div>

            <div class="visual-content">
                <h1>Acesse sua organização.</h1>
                <p>Projetos, associados, financeiro e operações em um único ambiente.</p>
            </div>

            <div class="visual-cards">
                <div class="visual-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path></svg>
                    <strong>Acesso seguro</strong>
                </div>

                <div class="visual-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><rect x="7" y="2" width="10" height="20" rx="2"></rect><path d="M11 18h2"></path></svg>
                    <strong>Celular e computador</strong>
                </div>

                <div class="visual-card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
                    <strong>Acesso por função</strong>
                </div>
            </div>
        </aside>

        <div class="login-panel">
            <div class="mobile-brand">
                <span class="brand-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 21h18"></path>
                        <path d="M6 21V7l6-4 6 4v14"></path>
                        <path d="M9 9h.01"></path>
                        <path d="M15 9h.01"></path>
                        <path d="M9 13h.01"></path>
                        <path d="M15 13h.01"></path>
                        <path d="M9 17h6"></path>
                    </svg>
                </span>

                <strong>{{ config('app.name', 'ZeCoop SGC') }}</strong>
            </div>

            <header class="login-heading">
                <h2>Entrar</h2>
                <p>Escolha uma forma de acesso.</p>
            </header>

            @if(session('error'))
                <div class="error-box" role="alert">
                    <span class="error-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 8v4"></path>
                            <path d="M12 16h.01"></path>
                        </svg>
                    </span>

                    <p>{{ session('error') }}</p>
                </div>
            @endif

            <div class="login-actions">
                <button class="login-button primary" id="passkey-login" type="button">
                    <span class="passkey-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="8" cy="15" r="4"></circle>
                            <path d="M10.85 12.15 19 4"></path>
                            <path d="m18 5 2 2"></path>
                            <path d="m15 8 2 2"></path>
                        </svg>
                    </span>

                    Entrar com passkey
                </button>

                <a href="{{ $googleLoginUrl }}" class="login-button">
                    <span class="login-button-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                    </span>

                    Entrar com Google
                </a>
            </div>

            <div class="passkey-helper" id="passkey-helper" hidden></div>

            <div class="login-status" id="login-status" role="status" aria-live="polite">
                <span class="status-spinner"></span>
                <span id="login-status-text">Verificando credencial...</span>
            </div>

            <div class="divider">acesso administrativo</div>

            <a href="{{ url('/admin') }}" class="panel-link">
                <span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path>
                        <path d="m9 12 2 2 4-4"></path>
                    </svg>

                    Painel administrativo
                </span>

                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                    <path d="m9 18 6-6-6-6"></path>
                </svg>
            </a>

            <footer class="login-footer">
                <a href="{{ url('/') }}">Voltar ao início</a>
                <span>Ambiente seguro</span>
            </footer>
        </div>
    </section>
</main>

<script>
    window.addEventListener('pageshow', async function () {
        try {
            const response = await fetch(@json(route('auth.state')), {credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
            const state = await response.json();
            if (state.authenticated && state.redirect) window.location.replace(state.redirect);
        } catch (_) {}
    });

    const PASSKEY_OPTIONS_URL = @json($passkeyOptionsUrl);
    const PASSKEY_VERIFY_URL = @json($passkeyVerifyUrl);
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

    const passkeyButton = document.getElementById('passkey-login');
    const passkeyHelper = document.getElementById('passkey-helper');
    const statusBox = document.getElementById('login-status');
    const statusText = document.getElementById('login-status-text');

    function base64UrlToUint8Array(value) {
        const padding = '='.repeat((4 - value.length % 4) % 4);
        const normalized = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
        const binary = window.atob(normalized);

        return Uint8Array.from(binary, function (character) {
            return character.charCodeAt(0);
        });
    }

    function arrayBufferToBase64Url(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';

        bytes.forEach(function (byte) {
            binary += String.fromCharCode(byte);
        });

        return window.btoa(binary)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/g, '');
    }

    function normalizeRequestOptions(options) {
        const publicKey = options.publicKey || options;

        publicKey.challenge = base64UrlToUint8Array(publicKey.challenge);

        if (Array.isArray(publicKey.allowCredentials)) {
            publicKey.allowCredentials = publicKey.allowCredentials.map(function (credential) {
                return {
                    ...credential,
                    id: base64UrlToUint8Array(credential.id),
                };
            });
        }

        return publicKey;
    }

    function credentialToJson(credential) {
        return {
            id: credential.id,
            rawId: arrayBufferToBase64Url(credential.rawId),
            type: credential.type,
            response: {
                authenticatorData: arrayBufferToBase64Url(credential.response.authenticatorData),
                clientDataJSON: arrayBufferToBase64Url(credential.response.clientDataJSON),
                signature: arrayBufferToBase64Url(credential.response.signature),
                userHandle: credential.response.userHandle
                    ? arrayBufferToBase64Url(credential.response.userHandle)
                    : null,
            },
            clientExtensionResults: credential.getClientExtensionResults(),
            authenticatorAttachment: credential.authenticatorAttachment || null,
        };
    }

    function setStatus(message, visible = true) {
        statusText.textContent = message;
        statusBox.classList.toggle('show', visible);
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF_TOKEN,
                ...(options.headers || {}),
            },
        });

        const data = await response.json().catch(function () {
            return { message: 'Resposta inválida do servidor.' };
        });

        if (!response.ok) {
            throw new Error(
                data.message
                || Object.values(data.errors || {}).flat()[0]
                || 'Não foi possível concluir o acesso.'
            );
        }

        return data;
    }

    async function loginWithPasskey() {
        passkeyButton.disabled = true;
        setStatus('Aguardando sua confirmação...');

        try {
            const result = await window.SgcPasskeys.verify({
                routes: {
                    options: PASSKEY_OPTIONS_URL,
                    submit: PASSKEY_VERIFY_URL,
                },
            });

            window.location.href = result.redirect
                || result.redirect_url
                || result.url
                || '/';
        } catch (error) {
            const cancelled = error.name === 'UserCancelledError';

            setStatus(
                cancelled ? 'A autenticação foi cancelada.' : error.message,
                true
            );

            window.setTimeout(function () {
                setStatus('', false);
            }, 4500);
        } finally {
            passkeyButton.disabled = false;
        }
    }

    function initializePasskeys() {
        const passkeySupported = window.isSecureContext
            && window.SgcPasskeys
            && window.SgcPasskeys.isSupported();

        if (!passkeySupported) {
            passkeyButton.disabled = true;
            passkeyHelper.textContent =
                'Passkeys não estão disponíveis neste navegador ou conexão.';
            passkeyHelper.hidden = false;
            return;
        }

        passkeyButton.addEventListener('click', loginWithPasskey);
    }

    window.SgcPasskeys
        ? initializePasskeys()
        : window.addEventListener('sgc:passkeys-ready', initializePasskeys, { once: true });
</script>
</body>
</html>
