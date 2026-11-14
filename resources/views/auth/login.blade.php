<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#173c2e">
<meta name="description" content="Acesse o SGC com sua chave de acesso ou conta Google.">
<meta name="robots" content="noindex,nofollow">
<link rel="manifest" href="/manifest.json?v=campo">
<link rel="icon" href="/assets/favicon.ico?v=campo-light" sizes="any">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon-180.png?v=campo-mark">
<title>Entrar — {{ config('app.name', 'SGC') }}</title>
<script>
(function(){try{let value=localStorage.getItem('sgc-theme')||localStorage.getItem('sgc-campo-theme');if(!value&&matchMedia('(prefers-color-scheme:dark)').matches)value='dark';document.documentElement.dataset.theme=value==='dark'?'dark':'light';if(value==='dark')document.querySelector('meta[name="theme-color"]').content='#111418';}catch(e){}})();
</script>
<style>

:root {
  color-scheme:light;
  --canvas:#f4f4ef;--surface:#fffefb;--surface-soft:#f0f0e9;
  --text:#1d2922;--muted:#647167;--border:#d9e0d7;
  --primary:#173c2e;--primary-hover:#214e3b;--on-primary:#f9fdf6;
  --accent:#d9f172;--accent-hover:#c9e660;--accent-text:#233523;--focus:#84ae4c;
  --soft-green:#eaf3e9;--soft-green-hover:#deeedb;
  --success:#276f43;--success-soft:#e8f5e7;
  --danger:#a8423d;--danger-soft:#fff0ec;
}
html[data-theme="dark"]{
  color-scheme:dark;
  --canvas:#111418;--surface:#191e24;--surface-soft:#212830;
  --text:#edf2f6;--muted:#afbac6;--border:#323c47;
  --primary:#e7c87d;--primary-hover:#f0d693;--on-primary:#2e2617;
  --accent:#e7c87d;--accent-hover:#f0d693;--accent-text:#2e2617;--focus:#d8b465;
  --soft-green:#252e32;--soft-green-hover:#303a40;
  --success:#94d7a6;--success-soft:#203329;
  --danger:#eda9a2;--danger-soft:#422825;
}
*,*::before,*::after{box-sizing:border-box}
html{min-width:320px;background:var(--canvas);-webkit-text-size-adjust:100%}
body{min-width:320px;min-height:100dvh;margin:0;background:var(--canvas);color:var(--text);font:400 15px/1.5 system-ui,-apple-system,"Segoe UI",Arial,sans-serif;-webkit-font-smoothing:antialiased}
button,a,summary{-webkit-tap-highlight-color:transparent}button{font:inherit;cursor:pointer}a{color:inherit;text-decoration:none}button:focus-visible,a:focus-visible,summary:focus-visible{outline:3px solid var(--focus);outline-offset:3px}[hidden]{display:none!important}
.icon{display:block;flex:none;width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linejoin:round;stroke-linecap:round}
.icon-sprite{position:absolute;width:0;height:0;overflow:hidden}
.login-page{display:flex;flex-direction:column;align-items:center;height:100vh;height:100dvh;overflow-y:auto;padding:16px;isolation:isolate}
.login-shell{flex:none;margin-block:auto}
.login-shell{width:min(100%,448px);border:1px solid var(--border);border-radius:22px;background:var(--surface);box-shadow:0 22px 70px rgba(19,49,33,.07);overflow:hidden}
html[data-theme="dark"] .login-shell{box-shadow:0 24px 60px rgba(0,0,0,.16)}
.login-accent{height:5px;background:var(--primary)}
.login-content{padding:27px 30px 26px}
.login-brand{display:flex;align-items:center;gap:12px;margin-bottom:34px}
.brand-mark{display:grid;place-items:center;flex:none;width:50px;height:50px;border:1px solid transparent;border-radius:12px;background:var(--accent);overflow:hidden}
.brand-mark img{display:block;width:38px;height:38px;object-fit:contain}
html[data-theme="dark"] .brand-mark{background:#151a20;border-color:var(--border)}
.brand-mark .brand-dark,html[data-theme="dark"] .brand-mark .brand-light{display:none}
html[data-theme="dark"] .brand-mark .brand-dark{display:block}
.brand-copy{display:grid;align-content:center;min-width:0;gap:1px}
.brand-copy strong{display:block;font-size:23px;font-weight:850;letter-spacing:-.07em;line-height:1.1}
.brand-copy small{display:block;font-size:11px;font-weight:650;color:var(--muted)}
.brand-actions{display:flex;align-items:center;gap:6px;margin-left:auto}
.icon-button{display:grid;place-items:center;width:36px;height:36px;border:1px solid transparent;border-radius:10px;background:transparent;color:var(--muted)}
.icon-button:hover{background:var(--surface-soft);border-color:var(--border);color:var(--text)}
.theme-button .sun{display:none}html[data-theme="dark"] .theme-button .sun{display:block}html[data-theme="dark"] .theme-button .moon{display:none}
.heading{margin:0 0 6px;font-size:clamp(27px,5vw,33px);font-weight:830;line-height:1.15;letter-spacing:-.055em}
.lead{margin:0 0 26px;font-size:13px;color:var(--muted)}
.server-feedback{margin-bottom:17px}
.auth-options{display:grid;gap:11px}
.auth-option{position:relative;display:grid;grid-template-columns:42px minmax(0,1fr) 19px;align-items:center;gap:13px;width:100%;min-height:74px;padding:12px 14px;text-align:left;border:1px solid var(--border);border-radius:13px;background:var(--soft-green);color:var(--text);transition:background .15s,border-color .15s,transform .15s}
.auth-option:hover{background:var(--soft-green-hover);border-color:var(--focus);transform:translateY(-1px)}
.auth-option{isolation:isolate;overflow:hidden}
.auth-option::before{position:absolute;z-index:-1;inset:0;pointer-events:none;content:"";background:radial-gradient(circle at 98% 145%,transparent 47px,currentColor 48px,transparent 49px 65px,currentColor 66px,transparent 67px 84px,currentColor 85px,transparent 86px);opacity:.1}
.auth-option.is-primary{background:var(--accent);border-color:var(--accent);color:var(--accent-text)}
.auth-option.is-primary:hover{background:var(--accent-hover);border-color:var(--accent-hover)}
.auth-option:disabled,.auth-option.is-primary:disabled{opacity:.64;cursor:not-allowed;transform:none;box-shadow:none}
.auth-option-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:11px;background:rgba(21,67,41,.08);color:inherit}
.auth-option.is-primary .auth-option-icon{background:color-mix(in srgb,var(--accent-text) 11%,transparent)}
html[data-theme="dark"] .auth-option.is-primary .auth-option-icon{background:rgba(46,38,23,.11)}
.auth-option-icon .icon{width:23px;height:23px}.google-icon{display:block;width:23px;height:23px}
.auth-option-copy{display:block;min-width:0}.auth-option-copy strong{display:block;font-size:14px;line-height:1.32;font-weight:780;letter-spacing:-.01em}.auth-option-copy span{display:block;margin-top:3px;font-size:11px;color:var(--muted)}
.auth-option.is-primary .auth-option-copy span{color:color-mix(in srgb,var(--accent-text) 86%,transparent)}
.auth-option-end{width:17px;height:17px;color:var(--muted)}.auth-option.is-primary .auth-option-end{color:var(--accent-text)}.auth-option-end .icon{width:17px;height:17px}
.auth-option.is-authenticating{outline:2px solid var(--accent);outline-offset:2px}
.login-status{display:none;margin-top:16px}.login-status.show{display:flex}
.feedback{display:flex;gap:11px;align-items:flex-start;border:1px solid var(--border);border-radius:12px;padding:12px;background:var(--surface-soft)}
.feedback.is-error{background:var(--danger-soft);border-color:color-mix(in srgb,var(--danger) 26%,var(--border))}
.feedback.is-success{background:var(--success-soft);border-color:color-mix(in srgb,var(--success) 26%,var(--border))}
.feedback-icon{display:grid;place-items:center;flex:none;width:29px;height:29px;border-radius:8px;background:var(--surface);color:var(--muted)}.is-error .feedback-icon{color:var(--danger)}.is-success .feedback-icon{color:var(--success)}.feedback-icon .icon{width:17px;height:17px}
.feedback-copy{display:block;min-width:0;flex:1}.feedback-copy strong{display:block;font-size:12px;font-weight:770}.feedback-copy span,.feedback-copy p,.feedback-copy li{display:block;margin:4px 0 0;color:var(--muted);font-size:12px;line-height:1.45}.feedback-copy ul{margin:6px 0 0;padding-left:16px}.feedback-copy li{display:list-item}
.login-status:not(.show){display:none}
.login-status.is-progress{align-items:center;border:0;border-inline-start:2px solid var(--focus);border-radius:0;background:transparent;padding:.25rem .65rem}
.login-status.is-progress .feedback-icon{width:20px;height:20px;background:transparent}
.passkey-help{margin-top:21px}.passkey-help summary{display:flex;align-items:center;gap:7px;list-style:none;width:max-content;max-width:100%;font-size:12px;font-weight:660;color:var(--muted);cursor:pointer}.passkey-help summary::-webkit-details-marker{display:none}.passkey-help summary .icon{width:16px;height:16px}.passkey-help summary .chev{transition:transform .15s}.passkey-help[open] .chev{transform:rotate(180deg)}.help-copy{padding:9px 2px 0 23px;color:var(--muted);font-size:12px;line-height:1.55}.help-copy p{margin:0}
.login-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:27px;padding-top:19px;border-top:1px solid var(--border)}
.footer-actions{display:flex;align-items:center;flex-wrap:wrap;gap:12px}.footer-link,.pwa-install-button{border:0;background:transparent;padding:0;color:var(--muted);font-size:12px;font-weight:680}.footer-link:hover,.pwa-install-button:hover{color:var(--text)}
.safe-label{display:inline-flex;align-items:center;gap:5px;color:var(--muted);font-size:11px;white-space:nowrap}.safe-label .icon{color:var(--success);width:15px;height:15px}
.legal-links{display:flex;gap:16px;flex-wrap:wrap;margin-top:18px}.legal-links a{color:var(--muted);font-size:11px}.legal-links a:hover{color:var(--text);text-decoration:underline}
.auth-success-layer{position:fixed;inset:0;z-index:4000;display:none;place-items:center;background:var(--canvas);padding:20px}.auth-success-layer.show{display:grid}
.auth-success-card{display:grid;gap:9px;justify-items:center;max-width:310px;text-align:center}.success-icon{display:grid;place-items:center;width:60px;height:60px;border-radius:18px;background:var(--primary);color:var(--on-primary)}.success-icon .icon{width:32px;height:32px}.auth-success-card strong{font-size:20px;letter-spacing:-.03em}.auth-success-card>span:last-child{font-size:13px;color:var(--muted)}
@media (max-width:520px){
  .login-page{align-items:center;padding:max(14px,env(safe-area-inset-top)) 14px max(14px,env(safe-area-inset-bottom))}
  .login-shell{border-radius:18px;box-shadow:0 12px 38px rgba(20,46,30,.065)}
  .login-content{padding:23px 21px 22px}
  .login-brand{margin-bottom:27px}.brand-mark{width:49px;height:49px}.heading{font-size:30px}.lead{margin-bottom:22px}
}
@media (max-width:360px){.login-content{padding:21px 15px}.auth-option{grid-template-columns:38px minmax(0,1fr) 17px;gap:10px;padding:11px}.auth-option-icon{width:38px;height:38px}.auth-option-copy strong{font-size:13px}.auth-option-copy span{font-size:10px}}
@media(max-height:650px){.login-content{padding-top:18px;padding-bottom:18px}.login-brand{margin-bottom:21px}.lead{margin-bottom:18px}.login-footer{margin-top:18px}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}}

</style>
</head>
@php
    $googleLoginUrl = \Illuminate\Support\Facades\Route::has(
        'auth.google'
    )
        ? route('auth.google')
        : url('/auth/google');
    $nativeGoogleChallengeUrl = route('auth.google.native.challenge');
    $nativeGoogleLoginUrl = route('auth.google.native');
    $passkeyOptionsUrl = \Illuminate\Support\Facades\Route::has(
        'auth.passkey.options'
    )
        ? route('auth.passkey.options')
        : url('/auth/passkey/options');
    $passkeyVerifyUrl = \Illuminate\Support\Facades\Route::has(
        'auth.passkey.verify'
    )
        ? route('auth.passkey.verify')
        : url('/auth/passkey/verify');
@endphp
<body>
<svg class="icon-sprite" aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
<symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></symbol>
<symbol id="i-back" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6M9 12h12"/></symbol>
<symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></symbol>
<symbol id="i-fingerprint" viewBox="0 0 24 24"><path d="M12 11a2 2 0 0 0-2 2v2m4-2v3M7 13a5 5 0 0 1 10 0v3M5 16v-3a7 7 0 0 1 14 0v3M9 19c.5 1.5 1.5 2.5 3 3m5-4c-.3 1.5-1 2.7-2 3.7M4 9a9 9 0 0 1 16 0M7 20l-1-2"/></symbol>
<symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 10v6m0-9h.01"/></symbol>
<symbol id="i-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
<symbol id="i-moon" viewBox="0 0 24 24"><path d="M20.4 15.3A9 9 0 0 1 8.7 3.6 9 9 0 1 0 20.4 15.3Z"/></symbol>
<symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M19.1 4.9l-1.4 1.4M6.3 17.7l-1.4 1.4"/></symbol>
<symbol id="i-check-circle" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></symbol>
<symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/></symbol>
<symbol id="i-lock" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></symbol>
</svg>
<main class="login-page">
  <section class="login-shell" aria-labelledby="login-title">
    <div class="login-accent" aria-hidden="true"></div>
    <div class="login-content">
      <header class="login-brand">
        <span class="brand-mark" aria-hidden="true">
          <img class="brand-light" src="{{ asset('assets/sgc-mark-light.png') }}" alt="" width="44" height="44">
          <img class="brand-dark" src="{{ asset('assets/sgc-mark-dark.png') }}" alt="" width="44" height="44">
        </span>
        <span class="brand-copy"><strong>SGC</strong><small>Sistema de Gestão Cooperativa</small></span>
        <span class="brand-actions">
          <a class="icon-button" href="{{ url('/') }}" title="Voltar ao início" aria-label="Voltar ao início"><svg class="icon" aria-hidden="true"><use href="#i-back"/></svg></a>
          <button class="icon-button theme-button" id="theme-toggle" type="button" title="Alternar tema" aria-label="Alternar tema"><svg class="icon moon" aria-hidden="true"><use href="#i-moon"/></svg><svg class="icon sun" aria-hidden="true"><use href="#i-sun"/></svg></button>
        </span>
      </header>
      <h1 class="heading" id="login-title">Entrar no SGC</h1>
      <p class="lead">Escolha uma forma de acesso.</p>
      @if(session('status') || session('error') || $errors->any())
      <div class="server-feedback" role="region" aria-label="Retorno do acesso">
        @if(session('status'))
        <div class="feedback is-success" role="status"><span class="feedback-icon"><svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg></span><span class="feedback-copy"><strong>Solicitação concluída</strong><span>{{ session('status') }}</span></span></div>
        @endif
        @if(session('error') || $errors->any())
        <div class="feedback is-error" role="alert"><span class="feedback-icon"><svg class="icon" aria-hidden="true"><use href="#i-alert"/></svg></span><div class="feedback-copy"><strong>Não foi possível entrar</strong>
          @if(session('error'))<p>{{ session('error') }}</p>@endif
          @if($errors->any())<ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
        </div></div>
        @endif
      </div>
      @endif
      <div class="auth-options" role="group" aria-label="Formas de acesso">
        <button type="button" class="auth-option is-primary" id="passkey-login">
          <span class="auth-option-icon"><svg class="icon" aria-hidden="true"><use href="#i-fingerprint"/></svg></span>
          <span class="auth-option-copy"><strong>Entrar com biometria ou PIN</strong><span>Chave de acesso do dispositivo</span></span>
          <span class="auth-option-end"><svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></span>
        </button>
        <a class="auth-option" id="google-login" href="{{ $googleLoginUrl }}">
          <span class="auth-option-icon"><svg class="google-icon" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg></span>
          <span class="auth-option-copy"><strong>Continuar com Google</strong><span>Conta vinculada ao SGC</span></span>
          <span class="auth-option-end"><svg class="icon" aria-hidden="true"><use href="#i-arrow"/></svg></span>
        </a>
      </div>
      <div class="feedback login-status is-progress" id="login-status" role="status" aria-live="polite" aria-atomic="true">
        <span class="feedback-icon"><svg class="icon" aria-hidden="true"><use href="#i-fingerprint" id="login-status-icon"/></svg></span>
        <span class="feedback-copy"><strong id="login-status-title">Confirme no seu aparelho</strong><span id="login-status-text">Aguardando sua confirmação...</span></span>
      </div>
      <details class="passkey-help">
        <summary><svg class="icon" aria-hidden="true"><use href="#i-info"/></svg>Como funciona a biometria?<svg class="icon chev" aria-hidden="true"><use href="#i-chevron"/></svg></summary>
        <div class="help-copy"><p>Confirme com sua digital, rosto ou PIN. Seus dados biométricos não são enviados ao SGC.</p></div>
      </details>
      <footer class="login-footer">
        <span class="footer-actions">
          <a class="footer-link" href="{{ url('/') }}">Voltar ao início</a>
          <button type="button" class="pwa-install-button" data-pwa-install hidden>Instalar app</button>
        </span>
        <span class="safe-label"><svg class="icon" aria-hidden="true"><use href="#i-lock"/></svg>Acesso seguro</span>
      </footer>
      <nav class="legal-links" aria-label="Documentos legais">
        <a href="{{ url('/legal/privacidade.html') }}">Privacidade</a>
        <a href="{{ url('/legal/termos.html') }}">Termos de uso</a>
      </nav>
    </div>
  </section>
</main>
<div class="auth-success-layer" id="auth-success-layer" aria-hidden="true"><div class="auth-success-card"><span class="success-icon"><svg class="icon" aria-hidden="true"><use href="#i-check-circle"/></svg></span><strong>Acesso confirmado</strong><span>Abrindo sua organização...</span></div></div>
<script>

        window.addEventListener(
            'pageshow',
            async function () {
                try {
                    const response = await fetch(
                        @json(route('auth.state')),
                        {
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: {
                                Accept: 'application/json'
                            }
                        }
                    );
                    const state = await response.json();
                    if (state.authenticated && state.redirect) {
                        window.location.replace(state.redirect);
                    }
                } catch (_) {
                    /* O estado remoto não impede o login manual. */
                }
            }
        );
        // Implementação web local: substitui a dependência de resources/js/app.js.
        // O fluxo nativo Android/Capacitor abaixo permanece como no projeto original.
        if (!window.SgcPasskeys) {
            const encodeBase64Url = (value) => {
                if (!value) return null;
                const bytes = new Uint8Array(value);
                let binary = '';
                for (let index = 0; index < bytes.length; index += 1) binary += String.fromCharCode(bytes[index]);
                return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
            };
            const decodeBase64Url = (value) => {
                const base64 = String(value).replace(/-/g, '+').replace(/_/g, '/');
                const binary = atob(base64.padEnd(Math.ceil(base64.length / 4) * 4, '='));
                return Uint8Array.from(binary, (character) => character.charCodeAt(0));
            };
            window.SgcPasskeys = {
                isSupported: () => Boolean(window.isSecureContext && navigator.credentials?.get && window.PublicKeyCredential),
                async verify({ routes }) {
                    const optionsResponse = await fetch(routes.options, {
                        credentials: 'same-origin', cache: 'no-store',
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const optionsData = await optionsResponse.json().catch(() => ({}));
                    if (!optionsResponse.ok) {
                        const error = new Error(optionsData.message || 'Não foi possível solicitar a chave de acesso.');
                        error.code = optionsData.code || 'PASSKEY_OPTIONS_ERROR';
                        throw error;
                    }
                    const serializedOptions = optionsData.options?.publicKey || optionsData.options?.public_key || optionsData.options || optionsData.publicKey;
                    if (!serializedOptions?.challenge) throw new Error('O servidor retornou opções de autenticação inválidas.');
                    const publicKey = window.PublicKeyCredential.parseRequestOptionsFromJSON
                        ? window.PublicKeyCredential.parseRequestOptionsFromJSON(serializedOptions)
                        : {
                            ...serializedOptions,
                            challenge: decodeBase64Url(serializedOptions.challenge),
                            allowCredentials: (serializedOptions.allowCredentials || []).map((item) => ({
                                ...item, id: decodeBase64Url(item.id)
                            }))
                        };
                    const credential = await navigator.credentials.get({ publicKey });
                    if (!credential) {
                        const error = new Error('Nenhuma chave de acesso foi selecionada.');
                        error.code = 'NO_PASSKEY';
                        throw error;
                    }
                    const payload = typeof credential.toJSON === 'function' ? credential.toJSON() : {
                        id: credential.id,
                        rawId: encodeBase64Url(credential.rawId),
                        type: credential.type,
                        authenticatorAttachment: credential.authenticatorAttachment ?? null,
                        response: {
                            clientDataJSON: encodeBase64Url(credential.response.clientDataJSON),
                            authenticatorData: encodeBase64Url(credential.response.authenticatorData),
                            signature: encodeBase64Url(credential.response.signature),
                            userHandle: encodeBase64Url(credential.response.userHandle)
                        },
                        clientExtensionResults: credential.getClientExtensionResults?.() || {}
                    };
                    const verifyResponse = await fetch(routes.submit, {
                        method: 'POST', credentials: 'same-origin', cache: 'no-store',
                        headers: {
                            Accept: 'application/json', 'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({ credential: payload })
                    });
                    const result = await verifyResponse.json().catch(() => ({}));
                    if (!verifyResponse.ok) {
                        const error = new Error(result.message || 'Chave de acesso não validada.');
                        error.code = result.code || 'PASSKEY_VERIFY_ERROR';
                        throw error;
                    }
                    return result;
                }
            };
        }
        const PASSKEY_OPTIONS_URL = @json($passkeyOptionsUrl);
        const PASSKEY_VERIFY_URL = @json($passkeyVerifyUrl);
        const NATIVE_GOOGLE_CHALLENGE_URL = @json($nativeGoogleChallengeUrl);
        const NATIVE_GOOGLE_LOGIN_URL = @json($nativeGoogleLoginUrl);
        const passkeyButton = document.getElementById('passkey-login');
        const googleButton = document.getElementById('google-login');
        const statusBox = document.getElementById('login-status');
        const statusTitle = document.getElementById('login-status-title');
        const statusText = document.getElementById('login-status-text');
        const statusIcon = document.getElementById('login-status-icon');
        const successLayer = document.getElementById('auth-success-layer');
        let statusTimer = null;
        let passkeysInitialized = false;
        let googleLoginPending = false;
        function setStatus({
            title,
            message,
            type = 'progress',
            visible = true,
            dismissAfter = null
        }) {
            window.clearTimeout(statusTimer);
            statusTitle.textContent = title;
            statusText.textContent = message;
            statusBox.classList.remove(
                'is-progress',
                'is-success',
                'is-error'
            );
            statusBox.classList.add(`is-${type}`);
            statusBox.classList.toggle('show', visible);
            statusBox.setAttribute(
                'role',
                type === 'error' ? 'alert' : 'status'
            );
            statusBox.setAttribute(
                'aria-live',
                type === 'error' ? 'assertive' : 'polite'
            );
            statusIcon.setAttribute('href', type === 'success' ? '#i-check-circle' : type === 'error' ? '#i-alert' : '#i-fingerprint');
            if (visible && dismissAfter) {
                statusTimer = window.setTimeout(
                    function () {
                        statusBox.classList.remove('show');
                    },
                    dismissAfter
                );
            }
        }
        function showSuccessAndRedirect(url) {
            successLayer.classList.add('show');
            successLayer.setAttribute('aria-hidden', 'false');
            window.setTimeout(
                function () {
                    window.location.href = url;
                },
                520
            );
        }
        function isNativeAndroid() {
            return Boolean(
                window.Capacitor?.isNativePlatform?.()
                && window.Capacitor?.getPlatform?.() === 'android'
            );
        }
        async function jsonResponse(response) {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(
                    data.message || 'Não foi possível concluir a autenticação.'
                );
                error.code = data.code || null;
                throw error;
            }
            return data;
        }
        function csrfToken() {
            return document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') || '';
        }
        async function loginWithNativePasskey() {
            const nativeAuth = window.Capacitor?.Plugins?.NativeAuth;
            if (!nativeAuth?.passkeySignIn) {
                const error = new Error('Atualize o aplicativo e tente novamente.');
                error.code = 'NATIVE_PASSKEY_UNAVAILABLE';
                throw error;
            }
            const optionResponse = await fetch(PASSKEY_OPTIONS_URL, {
                credentials: 'same-origin',
                cache: 'no-store',
                globalLoader: false,
                headers: {
                    Accept: 'application/json',
                    'X-SGC-Platform': 'android'
                }
            }).then(jsonResponse);
            if (!optionResponse?.options) {
                const error = new Error('O servidor retornou uma solicitação de biometria inválida.');
                error.code = 'INVALID_PASSKEY_OPTIONS';
                throw error;
            }
            const nativeCredential = await nativeAuth.passkeySignIn({
                requestJson: JSON.stringify(optionResponse.options)
            });
            let credential;
            try {
                credential = JSON.parse(nativeCredential.credentialJson);
            } catch (_) {
                const error = new Error('O dispositivo retornou uma credencial inválida.');
                error.code = 'INVALID_PASSKEY_RESPONSE';
                throw error;
            }
            return fetch(PASSKEY_VERIFY_URL, {
                method: 'POST',
                globalLoader: false,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-SGC-Platform': 'android'
                },
                body: JSON.stringify({ credential })
            }).then(jsonResponse);
        }
        async function loginWithNativeGoogle(event) {
            if (!isNativeAndroid()) {
                return;
            }
            event.preventDefault();
            if (googleLoginPending) {
                return;
            }
            const nativeAuth = window.Capacitor?.Plugins?.NativeAuth;
            if (!nativeAuth?.googleSignIn) {
                setStatus({
                    title: 'Login indisponível',
                    message: 'Atualize o aplicativo e tente novamente.',
                    type: 'error',
                    dismissAfter: 5000
                });
                return;
            }
            googleLoginPending = true;
            googleButton.setAttribute('aria-disabled', 'true');
            googleButton.setAttribute('aria-busy', 'true');
            googleButton.classList.add('is-authenticating');
            setStatus({
                title: 'Entrando com Google',
                message: 'Escolha sua conta para continuar.'
            });
            let authenticationStage = 'challenge';
            try {
                const challenge = await fetch(
                    NATIVE_GOOGLE_CHALLENGE_URL,
                    {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        globalLoader: false,
                        headers: { Accept: 'application/json' }
                    }
                ).then(jsonResponse);
                authenticationStage = 'native_google';
                const credential = await nativeAuth.googleSignIn({
                    nonce: challenge.nonce
                });
                authenticationStage = 'server_validation';
                const result = await fetch(NATIVE_GOOGLE_LOGIN_URL, {
                    method: 'POST',
                    globalLoader: false,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken()
                    },
                    body: JSON.stringify({ id_token: credential.idToken })
                }).then(jsonResponse);
                setStatus({
                    title: 'Identidade confirmada',
                    message: 'Acesso autorizado. Abrindo o SGC...',
                    type: 'success'
                });
                showSuccessAndRedirect(result.redirect || '/');
            } catch (error) {
                window.resetGlobalLoading?.();
                const errorCode = error?.code || error?.data?.code || 'GOOGLE_LOGIN_FAILED';
                const cancelled = errorCode === 'SIGN_IN_CANCELLED';
                if (!cancelled) {
                    window.SgcDiagnostics?.report({
                        category: 'authentication',
                        code: errorCode,
                        stage: authenticationStage,
                        message: error?.message || 'Falha no login Google'
                    });
                }
                setStatus({
                    title: cancelled ? 'Login cancelado' : 'Não foi possível entrar',
                    message: cancelled
                        ? 'Tente novamente quando quiser.'
                        : (error?.message || 'Verifique sua conexão e tente novamente.'),
                    type: 'error',
                    dismissAfter: cancelled ? 5000 : null
                });
            } finally {
                window.resetGlobalLoading?.();
                googleLoginPending = false;
                googleButton.removeAttribute('aria-disabled');
                googleButton.removeAttribute('aria-busy');
                googleButton.classList.remove('is-authenticating');
            }
        }
        googleButton?.addEventListener('click', loginWithNativeGoogle);
        function resetTransientLoginState() {
            if (googleLoginPending) {
                return;
            }
            window.resetGlobalLoading?.();
            successLayer.classList.remove('show');
            successLayer.setAttribute('aria-hidden', 'true');
            googleButton?.removeAttribute('aria-disabled');
            googleButton?.removeAttribute('aria-busy');
            googleButton?.classList.remove('is-authenticating');
            statusBox.classList.remove('show');
        }
        window.addEventListener('pageshow', resetTransientLoginState);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                resetTransientLoginState();
            }
        });
        async function loginWithPasskey() {
            passkeyButton.disabled = true;
            passkeyButton.setAttribute('aria-busy', 'true');
            passkeyButton.classList.add('is-authenticating');
            setStatus({
                title: 'Confirme no seu aparelho',
                message: 'Use sua digital, rosto ou PIN para continuar.'
            });
            try {
                const result = isNativeAndroid()
                    ? await loginWithNativePasskey()
                    : await window.SgcPasskeys.verify({
                        routes: {
                            options: PASSKEY_OPTIONS_URL,
                            submit: PASSKEY_VERIFY_URL
                        }
                    });
                setStatus({
                    title: 'Identidade confirmada',
                    message: 'Acesso autorizado. Abrindo o SGC...',
                    type: 'success'
                });
                const redirect =
                    result.redirect
                    || result.redirect_url
                    || result.url
                    || '/';
                showSuccessAndRedirect(redirect);
            } catch (error) {
                window.resetGlobalLoading?.();
                const errorCode = error?.code || error?.data?.code || 'PASSKEY_LOGIN_FAILED';
                const nativeErrorType = error?.data?.nativeErrorType
                    || error?.nativeErrorType
                    || null;
                const diagnosticCode = nativeErrorType
                    ? `${errorCode}:${nativeErrorType}`.slice(0, 100)
                    : errorCode;
                const cancelled = error.name === 'UserCancelledError'
                    || errorCode === 'SIGN_IN_CANCELLED';
                const offline = navigator.onLine === false;
                const friendlyMessage = offline
                    ? 'Sem conexão com a internet. Conecte-se e tente novamente.'
                    : errorCode === 'NO_PASSKEY'
                        ? 'Nenhuma chave de acesso está disponível para o SGC neste dispositivo.'
                        : errorCode === 'CREDENTIAL_MANAGER_UNAVAILABLE'
                            ? 'O gerenciador de credenciais não está disponível neste aparelho.'
                            : (error.message || 'Tente novamente ou use o Google.');
                if (!cancelled) {
                    window.SgcDiagnostics?.report({
                        category: 'authentication',
                        code: diagnosticCode,
                        stage: isNativeAndroid() ? 'native_passkey' : 'web_passkey',
                        message: error?.message || 'Falha no login por chave de acesso'
                    });
                }
                setStatus({
                    title: cancelled
                        ? 'Acesso cancelado'
                        : 'Não foi possível entrar',
                    message: cancelled
                        ? 'Tente novamente quando quiser.'
                        : friendlyMessage,
                    type: 'error',
                    dismissAfter: 5000
                });
            } finally {
                window.resetGlobalLoading?.();
                passkeyButton.disabled = false;
                passkeyButton.removeAttribute('aria-busy');
                passkeyButton.classList.remove('is-authenticating');
            }
        }
        function initializePasskeys() {
            if (passkeysInitialized) {
                return;
            }
            passkeysInitialized = true;
            const nativePasskeySupported = Boolean(
                isNativeAndroid()
                && window.Capacitor?.Plugins?.NativeAuth?.passkeySignIn
            );
            const webPasskeySupported = Boolean(
                window.isSecureContext
                && window.SgcPasskeys
                && window.SgcPasskeys.isSupported()
            );
            const passkeySupported = nativePasskeySupported || webPasskeySupported;
            if (!passkeySupported) {
                passkeyButton.disabled = true;
                setStatus({
                    title: 'Biometria indisponível',
                    message: 'Use o Google ou abra em um navegador compatível.',
                    type: 'error'
                });
                return;
            }
            passkeyButton.addEventListener('click', loginWithPasskey);
        }
        window.SgcPasskeys
            ? initializePasskeys()
            : window.addEventListener(
                'sgc:passkeys-ready',
                initializePasskeys,
                { once: true }
            );
        // Tema independente do Vite/includes. O tema escolhido se mantém entre páginas.
        const themeToggle = document.getElementById('theme-toggle');
        const applyTheme = (value) => {
            const next = value === 'dark' ? 'dark' : 'light';
            document.documentElement.dataset.theme = next;
            themeToggle?.setAttribute('aria-label', next === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro');
            themeToggle?.setAttribute('title', next === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro');
            const meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.content = next === 'dark' ? '#111418' : '#173c2e';
            try { localStorage.setItem('sgc-theme', next); } catch (_) {}
        };
        themeToggle?.addEventListener('click', () => {
            applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
        });
        // Instalação PWA somente quando o navegador oferecer o evento real.
        let installPrompt = null;
        const installButton = document.querySelector('[data-pwa-install]');
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            installPrompt = event;
            if (installButton) installButton.hidden = false;
        });
        installButton?.addEventListener('click', async () => {
            if (!installPrompt) return;
            const prompt = installPrompt;
            installPrompt = null;
            installButton.hidden = true;
            await prompt.prompt();
        });
        window.addEventListener('appinstalled', () => {
            installPrompt = null;
            if (installButton) installButton.hidden = true;
        });

</script>
</body>
</html>
