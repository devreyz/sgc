<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#F6F7F4">
  <meta name="color-scheme" content="light">
  <title>SGC • Offline</title>

  <style>
    :root {
      --bg: #f6f7f4;
      --surface: #ffffff;
      --surface-soft: #f3f4f1;
      --text: #17221d;
      --text-2: #5f6b65;
      --muted: #87918c;
      --border: #dce1db;
      --border-soft: #e9ece7;
      --green: #168a46;
      --green-hover: #12783d;
      --green-soft: #e8f4ec;
      --purple: #8c59e8;
      --purple-soft: #f1ebfb;
      --orange: #c9780c;
      --danger: #c85f59;
      --danger-soft: #faecea;
      --shadow: 0 16px 42px rgba(30, 42, 35, .07);
    }

    * {
      box-sizing: border-box;
      -webkit-tap-highlight-color: transparent;
    }

    html,
    body {
      width: 100%;
      height: 100%;
      margin: 0;
      overflow: hidden;
      overscroll-behavior: none;
      background: var(--bg);
    }

    body {
      font-family: Inter, ui-sans-serif, system-ui, -apple-system,
        BlinkMacSystemFont, "Segoe UI", sans-serif;
      color: var(--text);
    }

    button {
      font: inherit;
    }

    svg {
      display: block;
    }

    .icon {
      width: 1em;
      height: 1em;
      fill: none;
      stroke: currentColor;
      stroke-width: 1.8;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .page {
      width: 100%;
      height: 100vh;
      height: 100dvh;
      max-height: 100dvh;
      overflow: hidden;
      display: grid;
      grid-template-rows: 5px minmax(0, 1fr);
      background: var(--bg);
    }

    .accent-bar {
      display: grid;
      grid-template-columns: 1.55fr .78fr .67fr;
    }

    .accent-bar span:nth-child(1) { background: var(--green); }
    .accent-bar span:nth-child(2) { background: var(--purple); }
    .accent-bar span:nth-child(3) { background: var(--orange); }

    .viewport {
      min-height: 0;
      overflow: hidden;
      padding:
        max(14px, env(safe-area-inset-top))
        max(16px, env(safe-area-inset-right))
        max(14px, env(safe-area-inset-bottom))
        max(16px, env(safe-area-inset-left));
    }

    .shell {
      width: min(100%, 1120px);
      height: 100%;
      min-height: 0;
      margin-inline: auto;
      display: grid;
      grid-template-rows: auto minmax(0, 1fr) auto;
      gap: 10px;
    }

    .header {
      min-height: 50px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .brand strong {
      display: block;
      font-size: 17px;
      line-height: 1;
      letter-spacing: -.025em;
    }

    .brand span {
      display: block;
      margin-top: 4px;
      color: var(--muted);
      font-size: 10px;
      font-weight: 600;
    }

    .back-btn {
      width: 42px;
      height: 42px;
      border: 1px solid var(--border);
      border-radius: 14px;
      display: grid;
      place-items: center;
      color: #6d7772;
      background: rgba(255,255,255,.7);
      cursor: pointer;
    }

    .back-btn .icon {
      width: 19px;
      height: 19px;
    }

    .main {
      min-height: 0;
      display: grid;
      align-items: center;
    }

    .layout {
      min-height: 0;
      display: grid;
      grid-template-columns: minmax(0, 1.05fr) minmax(340px, .95fr);
      gap: clamp(28px, 6vw, 72px);
      align-items: center;
    }

    .hero {
      min-width: 0;
    }

    .state-mark {
      width: 74px;
      height: 74px;
      margin-bottom: 20px;
      border: 1px solid #ded7ec;
      border-radius: 23px;
      display: grid;
      place-items: center;
      color: var(--purple);
      background: var(--purple-soft);
    }

    .state-mark .icon {
      width: 33px;
      height: 33px;
    }

    .eyebrow {
      margin: 0 0 7px;
      color: var(--green);
      font-size: 10px;
      font-weight: 850;
      letter-spacing: .1em;
      text-transform: uppercase;
    }

    .hero h1 {
      max-width: 620px;
      margin: 0;
      font-size: clamp(34px, 5vw, 56px);
      line-height: 1;
      letter-spacing: -.055em;
      text-wrap: balance;
    }

    .hero p {
      max-width: 560px;
      margin: 14px 0 0;
      color: var(--text-2);
      font-size: clamp(14px, 1.5vw, 16px);
      line-height: 1.5;
    }

    .actions {
      margin-top: 22px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .primary-btn {
      min-height: 46px;
      padding: 0 16px;
      border: 0;
      border-radius: 15px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 9px;
      color: #fff;
      background: var(--green);
      font-size: 12px;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 9px 22px rgba(22,138,70,.16);
    }

    .primary-btn:hover {
      background: var(--green-hover);
    }

    .primary-btn:disabled {
      opacity: .7;
      cursor: wait;
    }

    .primary-btn .icon {
      width: 18px;
      height: 18px;
    }

    .status-card {
      border: 1px solid var(--border);
      border-radius: 26px;
      overflow: hidden;
      background: rgba(255,255,255,.84);
      box-shadow: var(--shadow);
    }

    .status-head {
      padding: 17px 19px;
      border-bottom: 1px solid var(--border-soft);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .status-head-left {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
    }

    .status-icon {
      width: 38px;
      height: 38px;
      border-radius: 13px;
      display: grid;
      place-items: center;
      color: var(--purple);
      background: var(--purple-soft);
    }

    .status-icon .icon {
      width: 19px;
      height: 19px;
    }

    .status-head strong {
      font-size: 13px;
    }

    .badge {
      padding: 6px 9px;
      border-radius: 999px;
      color: #9a6116;
      background: #fff1dd;
      font-size: 9px;
      font-weight: 900;
      letter-spacing: .05em;
      text-transform: uppercase;
    }

    .badge.online {
      color: #15773d;
      background: var(--green-soft);
    }

    .badge.offline {
      color: #9e4642;
      background: var(--danger-soft);
    }

    .status-row {
      min-height: 64px;
      padding: 10px 19px;
      display: grid;
      grid-template-columns: 34px minmax(0, 1fr) auto;
      gap: 11px;
      align-items: center;
    }

    .status-row + .status-row {
      border-top: 1px solid var(--border-soft);
    }

    .row-icon {
      width: 34px;
      height: 34px;
      border-radius: 11px;
      display: grid;
      place-items: center;
      color: #6d7772;
      background: var(--surface-soft);
    }

    .row-icon .icon {
      width: 17px;
      height: 17px;
    }

    .row-label {
      font-size: 11px;
      font-weight: 750;
    }

    .row-value {
      color: #7b8580;
      font-size: 10px;
      font-weight: 800;
      text-align: right;
      white-space: nowrap;
    }

    .row-value.good { color: var(--green); }
    .row-value.bad { color: var(--danger); }

    .footer {
      min-height: 38px;
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      color: var(--muted);
      font-size: 9px;
    }

    .footer-part {
      display: flex;
      align-items: center;
      gap: 7px;
      min-width: 0;
    }

    .footer-part .icon {
      width: 14px;
      height: 14px;
      flex: 0 0 auto;
    }

    .footer-part span {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .connected {
      position: fixed;
      z-index: 20;
      inset: 0;
      display: grid;
      place-items: center;
      padding: 18px;
      background: rgba(246,247,244,.82);
      backdrop-filter: blur(8px);
      opacity: 0;
      visibility: hidden;
      transition: opacity .2s ease, visibility .2s ease;
    }

    .connected.show {
      opacity: 1;
      visibility: visible;
    }

    .connected-card {
      width: min(100%, 350px);
      padding: 23px;
      border: 1px solid var(--border);
      border-radius: 23px;
      text-align: center;
      background: #fff;
      box-shadow: 0 20px 50px rgba(31,42,35,.12);
    }

    .connected-icon {
      width: 56px;
      height: 56px;
      margin: 0 auto 12px;
      border-radius: 18px;
      display: grid;
      place-items: center;
      color: var(--green);
      background: var(--green-soft);
    }

    .connected-icon .icon {
      width: 28px;
      height: 28px;
    }

    .connected-card h2 {
      margin: 0;
      font-size: 20px;
      letter-spacing: -.03em;
    }

    .connected-card p {
      margin: 7px 0 0;
      color: var(--text-2);
      font-size: 12px;
    }

    .spin {
      animation: spin .8s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    @media (max-width: 820px) {
      .layout {
        width: min(100%, 580px);
        margin-inline: auto;
        grid-template-columns: 1fr;
        gap: 18px;
      }

      .hero {
        text-align: center;
      }

      .state-mark {
        width: 64px;
        height: 64px;
        margin: 0 auto 14px;
        border-radius: 20px;
      }

      .state-mark .icon {
        width: 29px;
        height: 29px;
      }

      .hero h1,
      .hero p {
        margin-inline: auto;
      }

      .hero h1 {
        font-size: clamp(28px, 7vw, 40px);
      }

      .hero p {
        margin-top: 9px;
        font-size: 13px;
      }

      .actions {
        margin-top: 15px;
        justify-content: center;
      }

      .status-row {
        min-height: 54px;
      }
    }

    @media (max-width: 520px) {
      .viewport {
        padding:
          max(10px, env(safe-area-inset-top))
          max(12px, env(safe-area-inset-right))
          max(10px, env(safe-area-inset-bottom))
          max(12px, env(safe-area-inset-left));
      }

      .shell {
        gap: 6px;
      }

      .header {
        min-height: 40px;
      }

      .brand strong {
        font-size: 14px;
      }

      .brand span {
        font-size: 9px;
      }

      .back-btn {
        width: 36px;
        height: 36px;
      }

      .layout {
        gap: 12px;
      }

      .state-mark {
        width: 56px;
        height: 56px;
        margin-bottom: 9px;
      }

      .eyebrow {
        margin-bottom: 4px;
        font-size: 8px;
      }

      .hero h1 {
        font-size: clamp(25px, 8vw, 33px);
      }

      .hero p {
        margin-top: 6px;
        font-size: 11px;
        line-height: 1.38;
      }

      .actions {
        margin-top: 10px;
      }

      .primary-btn {
        min-height: 40px;
        padding-inline: 13px;
        font-size: 10px;
      }

      .status-card {
        border-radius: 20px;
      }

      .status-head {
        padding: 11px 13px;
      }

      .status-icon {
        width: 33px;
        height: 33px;
      }

      .status-head strong {
        font-size: 11px;
      }

      .status-row {
        min-height: 46px;
        padding: 7px 13px;
      }

      .row-icon {
        width: 29px;
        height: 29px;
      }

      .footer {
        min-height: 28px;
      }

      .footer-part:last-child {
        display: none;
      }
    }

    @media (max-height: 650px) {
      .brand span,
      .eyebrow {
        display: none;
      }

      .header {
        min-height: 34px;
      }

      .state-mark {
        width: 46px;
        height: 46px;
        margin-bottom: 7px;
      }

      .state-mark .icon {
        width: 22px;
        height: 22px;
      }

      .hero h1 {
        font-size: clamp(24px, 4vw, 34px);
      }

      .hero p {
        margin-top: 5px;
        font-size: 10px;
      }

      .actions {
        margin-top: 8px;
      }

      .primary-btn {
        min-height: 36px;
      }

      .status-head {
        padding-block: 9px;
      }

      .status-row {
        min-height: 39px;
        padding-block: 5px;
      }

      .footer {
        min-height: 22px;
      }
    }

    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        animation-duration: .001ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .001ms !important;
      }
    }
  </style>
</head>

<body>

  <svg width="0" height="0" style="position:absolute;overflow:hidden" aria-hidden="true">
    <symbol id="i-arrow-left" viewBox="0 0 24 24">
      <path d="M15 5 8 12l7 7"/>
      <path d="M9 12h10"/>
    </symbol>

    <symbol id="i-wifi-off" viewBox="0 0 24 24">
      <path d="M4.8 8.8A11.5 11.5 0 0 1 12 6.3c2.8 0 5.3 1 7.3 2.5"/>
      <path d="M7.7 12a7 7 0 0 1 4.3-1.4c1.6 0 3 .5 4.3 1.4"/>
      <path d="M10.5 15.2a3.1 3.1 0 0 1 3 0"/>
      <circle cx="12" cy="18.2" r=".8" fill="currentColor" stroke="none"/>
      <path d="M4 4l16 16"/>
    </symbol>

    <symbol id="i-refresh" viewBox="0 0 24 24">
      <path d="M19 8V4l-2 2"/>
      <path d="M18.2 6.3A8 8 0 1 0 20 12"/>
    </symbol>

    <symbol id="i-pulse" viewBox="0 0 24 24">
      <path d="M3 12h4l2-5 4 10 2.6-7 2 2H21"/>
    </symbol>

    <symbol id="i-wifi" viewBox="0 0 24 24">
      <path d="M4.5 8.7a11.8 11.8 0 0 1 15 0"/>
      <path d="M7.5 12a7.2 7.2 0 0 1 9 0"/>
      <path d="M10.3 15.3a3 3 0 0 1 3.4 0"/>
      <circle cx="12" cy="18.4" r=".8" fill="currentColor" stroke="none"/>
    </symbol>

    <symbol id="i-server" viewBox="0 0 24 24">
      <rect x="4" y="4" width="16" height="6" rx="2"/>
      <rect x="4" y="14" width="16" height="6" rx="2"/>
      <path d="M8 7h.01M8 17h.01"/>
      <path d="M12 7h5M12 17h5"/>
    </symbol>

    <symbol id="i-clock" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="9"/>
      <path d="M12 7v5l3.4 2"/>
    </symbol>

    <symbol id="i-lock" viewBox="0 0 24 24">
      <rect x="5" y="10" width="14" height="10" rx="2"/>
      <path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>
    </symbol>

    <symbol id="i-check" viewBox="0 0 24 24">
      <circle cx="12" cy="12" r="9"/>
      <path d="m8 12 2.6 2.6L16.5 9"/>
    </symbol>

    <symbol id="i-bolt" viewBox="0 0 24 24">
      <path d="M13.5 2 6 13h5l-.5 9L18 11h-5l.5-9Z"/>
    </symbol>
  </svg>

  <main class="page">
    <div class="accent-bar" aria-hidden="true">
      <span></span><span></span><span></span>
    </div>

    <div class="viewport">
      <div class="shell">

        <header class="header">
          <div class="brand">
            <strong>SGC</strong>
            <span>Sistema de Gestão de Cooperativas</span>
          </div>

          <button class="back-btn" id="backButton" type="button" aria-label="Voltar">
            <svg class="icon"><use href="#i-arrow-left"></use></svg>
          </button>
        </header>

        <section class="main">
          <div class="layout">

            <section class="hero">
              <div class="state-mark" aria-hidden="true">
                <svg class="icon"><use href="#i-wifi-off"></use></svg>
              </div>

              <p class="eyebrow">Modo offline</p>

              <h1 id="mainTitle">Sem conexão com o SGC.</h1>

              <p id="mainText">
                Tentaremos novamente automaticamente.
              </p>

              <div class="actions">
                <button class="primary-btn" id="retryButton" type="button">
                  <svg class="icon" id="retryIcon"><use href="#i-refresh"></use></svg>
                  <span id="retryLabel">Tentar novamente</span>
                </button>
              </div>
            </section>

            <aside class="status-card" aria-label="Status da conexão">
              <div class="status-head">
                <div class="status-head-left">
                  <div class="status-icon" aria-hidden="true">
                    <svg class="icon"><use href="#i-pulse"></use></svg>
                  </div>
                  <strong>Status</strong>
                </div>

                <span class="badge" id="statusBadge">Verificando</span>
              </div>

              <div class="status-row">
                <div class="row-icon" aria-hidden="true">
                  <svg class="icon"><use href="#i-wifi"></use></svg>
                </div>
                <div class="row-label">Internet</div>
                <div class="row-value" id="internetValue">Verificando</div>
              </div>

              <div class="status-row">
                <div class="row-icon" aria-hidden="true">
                  <svg class="icon"><use href="#i-server"></use></svg>
                </div>
                <div class="row-label">SGC</div>
                <div class="row-value" id="serverValue">Aguardando</div>
              </div>

              <div class="status-row">
                <div class="row-icon" aria-hidden="true">
                  <svg class="icon"><use href="#i-clock"></use></svg>
                </div>
                <div class="row-label">Nova tentativa</div>
                <div class="row-value" id="countdownValue">5 s</div>
              </div>
            </aside>

          </div>
        </section>

        <footer class="footer">
          <div class="footer-part">
            <svg class="icon"><use href="#i-lock"></use></svg>
            <span>SGC • Modo offline</span>
          </div>

          <div class="footer-part">
            <svg class="icon"><use href="#i-bolt"></use></svg>
            <span id="footerStatus">Reconectando automaticamente</span>
          </div>
        </footer>

      </div>
    </div>
  </main>

  <div class="connected" id="connectedOverlay" role="status" aria-live="assertive">
    <div class="connected-card">
      <div class="connected-icon" aria-hidden="true">
        <svg class="icon"><use href="#i-check"></use></svg>
      </div>
      <h2>Conexão restabelecida</h2>
      <p>Retornando ao SGC…</p>
    </div>
  </div>

  <script>
    (() => {
      "use strict";

      const CONFIG = {
        HEALTHCHECK_URL: "/up",
        CHECK_INTERVAL_SECONDS: 10,
        REQUEST_TIMEOUT_MS: 3500,
        RETURN_DELAY_MS: 1300,
        AUTO_RELOAD: true
      };

      const els = {
        backButton: document.getElementById("backButton"),
        mainTitle: document.getElementById("mainTitle"),
        mainText: document.getElementById("mainText"),
        retryButton: document.getElementById("retryButton"),
        retryIcon: document.getElementById("retryIcon"),
        retryLabel: document.getElementById("retryLabel"),
        statusBadge: document.getElementById("statusBadge"),
        internetValue: document.getElementById("internetValue"),
        serverValue: document.getElementById("serverValue"),
        countdownValue: document.getElementById("countdownValue"),
        footerStatus: document.getElementById("footerStatus"),
        connectedOverlay: document.getElementById("connectedOverlay")
      };

      const state = {
        checking: false,
        connected: false,
        countdown: CONFIG.CHECK_INTERVAL_SECONDS,
        timer: null
      };

      function setValueClass(el, type) {
        el.classList.remove("good", "bad");
        if (type) el.classList.add(type);
      }

      function resetCountdown() {
        state.countdown = CONFIG.CHECK_INTERVAL_SECONDS;
        els.countdownValue.textContent = state.countdown + " s";
      }

      function withTimeout(promise, ms) {
        return Promise.race([
          promise,
          new Promise((_, reject) => {
            setTimeout(() => reject(new Error("timeout")), ms);
          })
        ]);
      }

      function updateInternet() {
        if (navigator.onLine) {
          els.internetValue.textContent = "Disponível";
          setValueClass(els.internetValue, "good");
          return true;
        }

        els.internetValue.textContent = "Offline";
        setValueClass(els.internetValue, "bad");
        return false;
      }

      function setChecking() {
        els.statusBadge.className = "badge";
        els.statusBadge.textContent = "Verificando";

        els.retryButton.disabled = true;
        els.retryIcon.classList.add("spin");
        els.retryLabel.textContent = "Verificando";

        els.serverValue.textContent = "Verificando";
        setValueClass(els.serverValue, null);
      }

      function setNoInternet() {
        els.statusBadge.className = "badge offline";
        els.statusBadge.textContent = "Offline";

        els.retryButton.disabled = false;
        els.retryIcon.classList.remove("spin");
        els.retryLabel.textContent = "Tentar novamente";

        els.mainTitle.textContent = "Sem conexão com a internet.";
        els.mainText.textContent = "Tentaremos novamente quando a rede voltar.";

        els.serverValue.textContent = "Aguardando";
        setValueClass(els.serverValue, "bad");

        els.footerStatus.textContent = "Aguardando conexão";
      }

      function setServerUnavailable() {
        els.statusBadge.className = "badge offline";
        els.statusBadge.textContent = "Indisponível";

        els.retryButton.disabled = false;
        els.retryIcon.classList.remove("spin");
        els.retryLabel.textContent = "Tentar novamente";

        els.mainTitle.textContent = "Sem conexão com o SGC.";
        els.mainText.textContent = "Tentaremos novamente automaticamente.";

        els.serverValue.textContent = "Sem resposta";
        setValueClass(els.serverValue, "bad");

        els.footerStatus.textContent = "Reconectando automaticamente";
      }

      function setConnected() {
        if (state.connected) return;

        state.connected = true;
        clearInterval(state.timer);

        els.statusBadge.className = "badge online";
        els.statusBadge.textContent = "Online";

        els.internetValue.textContent = "Disponível";
        els.serverValue.textContent = "Disponível";
        setValueClass(els.internetValue, "good");
        setValueClass(els.serverValue, "good");

        els.retryIcon.classList.remove("spin");
        els.retryLabel.textContent = "Conectado";

        els.connectedOverlay.classList.add("show");

        if (CONFIG.AUTO_RELOAD) {
          setTimeout(() => location.reload(), CONFIG.RETURN_DELAY_MS);
        }
      }

      async function checkConnection() {
        if (state.checking || state.connected) return;

        state.checking = true;

        if (!updateInternet()) {
          setNoInternet();
          state.checking = false;
          resetCountdown();
          return;
        }

        setChecking();

        const separator = CONFIG.HEALTHCHECK_URL.includes("?") ? "&" : "?";
        const url = CONFIG.HEALTHCHECK_URL + separator + "_offline_check=" + Date.now();

        try {
          const response = await withTimeout(
            fetch(url, {
              method: "GET",
              cache: "no-store",
              credentials: "same-origin"
            }),
            CONFIG.REQUEST_TIMEOUT_MS
          );

          if (!response.ok) throw new Error("bad_status");

          setConnected();
        } catch {
          setServerUnavailable();
        } finally {
          state.checking = false;
          if (!state.connected) resetCountdown();
        }
      }

      function tick() {
        if (state.connected || state.checking) return;

        state.countdown -= 1;

        if (state.countdown <= 0) {
          checkConnection();
          return;
        }

        els.countdownValue.textContent = state.countdown + " s";
      }

      els.retryButton.addEventListener("click", () => {
        resetCountdown();
        checkConnection();
      });

      els.backButton.addEventListener("click", () => {
        if (history.length > 1) {
          history.back();
        } else {
          location.href = "/";
        }
      });

      window.addEventListener("online", checkConnection);
      window.addEventListener("offline", () => {
        updateInternet();
        setNoInternet();
      });

      document.addEventListener("visibilitychange", () => {
        if (!document.hidden && !state.connected) checkConnection();
      });

      document.addEventListener("touchmove", event => {
        event.preventDefault();
      }, { passive: false });

      updateInternet();
      resetCountdown();

      state.timer = setInterval(tick, 1000);
      setTimeout(checkConnection, 500);
    })();
  </script>
</body>
</html>