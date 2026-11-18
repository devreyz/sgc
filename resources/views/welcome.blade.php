<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#173c2e">
  <meta name="description" content="SGC — Sistema de Gestão Cooperativa. Pessoas, projetos, entregas, financeiro e prestação de contas em uma gestão conectada.">
  <meta name="robots" content="index,follow">
  <meta property="og:type" content="website">
  <meta property="og:title" content="SGC — Sistema de Gestão Cooperativa">
  <meta property="og:description" content="Da rotina à prestação de contas. Tudo no seu lugar.">
  <meta property="og:locale" content="pt_BR">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="SGC — Sistema de Gestão Cooperativa">
  <meta name="twitter:description" content="Gestão integrada para organizações, projetos, pessoas e operações.">
  <title>SGC — Gestão cooperativa com clareza</title>
  <script src="{{ asset('assets/pwa-entry.js') }}?v=2"></script>
  <style id="sgc-campo-welcome" nonce="{{ request()->attributes->get('welcome_style_nonce') }}">
.campo-landing {
  --lp-canvas:#f4f4ef;--lp-surface:#fffefb;--lp-surface-soft:#f0f1eb;
  --lp-ink:#1d2922;--lp-muted:#5f6b62;--lp-faint:#8a948a;--lp-rule:#dbe2d9;
  --lp-deep:#173c2e;--lp-deep-hover:#214d3a;--lp-on-deep:#f3f5ee;
  --lp-accent:#d9f172;--lp-on-accent:#273421;--lp-accent-hover:#c9e660;
  --lp-success:#207346;--lp-warn:#946525;--lp-alert-bg:#f8f1df;
  --lp-shadow:0 16px 55px rgba(28,48,34,.08);
  background:var(--lp-canvas);color:var(--lp-ink);margin:0;min-width:320px;
  font-family:Inter,ui-sans-serif,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;
  font-size:15px;line-height:1.55;-webkit-font-smoothing:antialiased;
}
.theme-toggle-input:checked ~ .campo-landing {
  color-scheme:dark;
  --lp-canvas:#111418;--lp-surface:#191e24;--lp-surface-soft:#212830;
  --lp-ink:#edf2f6;--lp-muted:#afbac6;--lp-faint:#8794a0;--lp-rule:#323c47;
  --lp-deep:#151a20;--lp-deep-hover:#222a33;--lp-on-deep:#f2f5f7;
  --lp-accent:#d9f172;--lp-on-accent:#173c2e;--lp-accent-hover:#e5fa98;
  --lp-success:#91d2aa;--lp-warn:#e8bf79;--lp-alert-bg:#302b22;
  --lp-shadow:0 22px 60px rgba(0,0,0,.16);
}
html {scroll-padding-top:90px}
.campo-landing *,.campo-landing *::before,.campo-landing *::after {box-sizing:border-box}
.campo-landing a {text-decoration:none;color:inherit}
.campo-landing a:focus-visible,.campo-landing button:focus-visible,.campo-landing input:focus-visible,.campo-landing textarea:focus-visible,.campo-landing summary:focus-visible {outline:3px solid var(--lp-accent);outline-offset:3px}
.campo-landing button,.campo-landing input,.campo-landing textarea {font:inherit}
.campo-landing button {cursor:pointer}
.campo-landing button[hidden] {display:none!important}
.campo-landing h1,.campo-landing h2,.campo-landing h3,.campo-landing h4,.campo-landing p {margin-top:0}
.campo-landing .lp-container {width:min(100% - 64px,1336px);margin-inline:auto;min-width:0}
.campo-landing .lp-icon {width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round;display:block;flex-shrink:0}
.campo-landing .lp-icons {position:absolute;width:0;height:0;overflow:hidden}
.campo-landing .lp-eyebrow,.campo-landing .lp-section-index {display:block;color:var(--lp-success);font-weight:850;font-size:11px;letter-spacing:.16em;line-height:1.3}
.campo-landing .lp-button {display:inline-flex;align-items:center;justify-content:center;gap:18px;min-height:52px;padding:13px 20px;border:1px solid var(--lp-rule);border-radius:9px;font-size:13px;font-weight:760;letter-spacing:-.01em;white-space:nowrap;transition:background .16s,border-color .16s,transform .16s}
.campo-landing .lp-button:hover {transform:translateY(-1px)}
.campo-landing .lp-button-primary {background:var(--lp-deep);color:var(--lp-on-deep);border-color:var(--lp-deep)}
.campo-landing .lp-button-primary:hover {background:var(--lp-deep-hover);border-color:var(--lp-deep-hover)}
.campo-landing .lp-button-outline {background:transparent;color:var(--lp-ink);border-color:var(--lp-rule)}
.campo-landing .lp-button-outline:hover {background:var(--lp-surface)}
.campo-landing .lp-button-gold {background:var(--lp-accent);color:var(--lp-on-accent);border-color:var(--lp-accent)}
.campo-landing .lp-button-gold:hover {background:var(--lp-accent-hover)}
.theme-toggle-input:checked ~ .campo-landing .lp-button-primary {background:var(--lp-accent);border-color:var(--lp-accent);color:var(--lp-on-accent)}
.theme-toggle-input:checked ~ .campo-landing .lp-button-primary:hover {background:var(--lp-accent-hover)}
.campo-landing .lp-text-link {display:inline-flex;align-items:center;gap:11px;color:var(--lp-ink);font-size:13px;font-weight:800;border-bottom:1px solid currentColor;padding-bottom:8px}
.campo-landing .lp-text-link svg {width:16px;height:16px;transition:transform .2s}
.campo-landing .lp-text-link:hover svg {transform:translateX(4px)}

/* HEADER: sólido e utilizável. */
.campo-landing .lp-header {position:sticky;top:0;z-index:60;border-bottom:1px solid var(--lp-rule);background:color-mix(in srgb,var(--lp-surface) 95%,transparent);backdrop-filter:blur(14px)}
.campo-landing .lp-header-inner {height:80px;display:flex;align-items:center;gap:26px}
.campo-landing .lp-logo {display:inline-flex;align-items:center;gap:12px;flex-shrink:0}
.campo-landing .lp-logo-mark {width:44px;height:44px;display:grid;place-items:center;background:var(--lp-accent);color:#173c2e;border-radius:11px 11px 4px 11px;overflow:hidden}
.campo-landing .lp-logo-mark svg,.campo-landing .lp-logo-mark img {width:31px;max-width:80%;max-height:80%;height:auto}
.campo-landing .lp-logo-copy strong {display:block;font-size:24px;font-weight:930;letter-spacing:-.1em;line-height:1}
.campo-landing .lp-logo-dot {color:var(--lp-success)}
.theme-toggle-input:checked ~ .campo-landing .lp-logo-dot {color:var(--lp-accent)}
.campo-landing .lp-logo-copy small {display:block;margin-top:5px;color:var(--lp-muted);font-size:8px;font-weight:830;letter-spacing:.155em;white-space:nowrap}
.campo-landing .lp-main-nav {display:flex;align-items:center;gap:clamp(14px,2vw,30px);margin-left:auto}
.campo-landing .lp-main-nav a {font-size:12px;font-weight:730;color:var(--lp-muted);transition:color .15s}
.campo-landing .lp-main-nav a:hover {color:var(--lp-ink)}
.campo-landing .lp-header-actions {display:flex;align-items:center;gap:10px;flex-shrink:0;margin-left:4px}
.campo-landing .lp-signin {display:inline-flex;align-items:center;gap:11px;padding:0 17px;height:43px;border-radius:8px;background:var(--lp-deep);color:var(--lp-on-deep);font-size:12px;font-weight:800}
.campo-landing .lp-signin:hover {background:var(--lp-deep-hover)}
.campo-landing .lp-signin .lp-icon {width:16px;height:16px}
.theme-toggle-input:checked ~ .campo-landing .lp-signin {background:var(--lp-accent);color:var(--lp-on-accent)}
.campo-landing .lp-install {display:inline-flex;align-items:center;gap:7px;padding:10px 13px;border:1px solid var(--lp-rule);border-radius:8px;background:var(--lp-surface);color:var(--lp-ink);font-size:11px;font-weight:750}
.campo-landing .lp-install .lp-icon {width:16px;height:16px}
.campo-landing .lp-menu-button {display:none;place-items:center;width:44px;height:44px;border:1px solid var(--lp-rule);background:var(--lp-surface);color:var(--lp-ink);border-radius:8px}
.campo-landing .lp-mobile-menu {border-top:1px solid var(--lp-rule);background:var(--lp-surface)}
.campo-landing .lp-mobile-menu.hidden {display:none!important}
.campo-landing .lp-mobile-list {display:grid;padding-block:12px 22px}
.campo-landing .lp-mobile-list a {padding:12px 4px;border-bottom:1px solid var(--lp-rule);font-size:14px;font-weight:740}

/* HERO */
.campo-landing .lp-hero {position:relative;overflow:hidden;padding-top:79px}
.campo-landing .lp-hero::before {content:"";position:absolute;width:390px;height:390px;border-radius:50%;border:1px solid var(--lp-rule);top:-262px;left:-170px;pointer-events:none}
.campo-landing .lp-hero-grid {display:grid;grid-template-columns:minmax(0,.92fr) minmax(0,1.08fr);gap:clamp(38px,5vw,84px);align-items:center}
.campo-landing .lp-hero-copy {min-width:0;padding-bottom:20px}
.campo-landing .lp-kicker {display:flex;align-items:center;gap:12px;font-size:10px;font-weight:850;letter-spacing:.16em;color:var(--lp-success)}
.campo-landing .lp-kicker-rule {height:2px;width:32px;background:var(--lp-success)}
.campo-landing .lp-hero h1 {font-size:clamp(45px,5.1vw,78px);max-width:720px;line-height:1.04;letter-spacing:-.067em;font-weight:880;margin:26px 0 23px}
.campo-landing .lp-hero h1 span {color:var(--lp-success)}
.theme-toggle-input:checked ~ .campo-landing .lp-hero h1 span {color:var(--lp-accent)}
.campo-landing .lp-lead {max-width:520px;font-size:clamp(15px,1.4vw,17px);line-height:1.75;color:var(--lp-muted)}
.campo-landing .lp-hero-cta {display:flex;flex-wrap:wrap;gap:11px;margin-top:31px}
.campo-landing .lp-hero-proof {display:flex;gap:11px;align-items:flex-start;margin-top:37px;max-width:420px}
.campo-landing .lp-hero-proof p {margin:0;color:var(--lp-muted);font-size:12px;line-height:1.55}
.campo-landing .lp-proof-mark {width:25px;height:25px;display:grid;place-items:center;border-radius:50%;background:var(--lp-surface-soft);color:var(--lp-success);flex-shrink:0}
.campo-landing .lp-proof-mark svg {width:14px;height:14px}
.campo-landing .lp-product-stage {min-width:0;position:relative;align-self:center}
.campo-landing .lp-stage-label {display:flex;gap:7px;font-size:10px;letter-spacing:.16em;font-weight:850;color:var(--lp-ink);margin-bottom:13px}
.campo-landing .lp-stage-label span {color:var(--lp-faint)}
.campo-landing .lp-app-shot {display:grid;grid-template-columns:145px minmax(0,1fr);min-width:0;background:var(--lp-surface);border:1px solid var(--lp-rule);border-radius:14px;overflow:hidden;box-shadow:var(--lp-shadow);min-height:410px}
.campo-landing .lp-shot-side {display:flex;flex-direction:column;background:var(--lp-deep);color:var(--lp-on-deep);padding:19px 9px 16px;min-width:0}
.campo-landing .lp-shot-mark {display:grid;place-items:center;width:33px;height:33px;background:var(--lp-accent);color:#173c2e;border-radius:9px;margin:0 0 23px 9px}
.campo-landing .lp-shot-mark svg {width:25px;height:25px}
.campo-landing .lp-shot-side-line {display:flex;align-items:center;gap:10px;min-height:38px;padding:6px 8px;border-radius:7px;font-size:10px;font-weight:650;color:color-mix(in srgb,var(--lp-on-deep) 68%,transparent)}
.campo-landing .lp-shot-side-line svg {height:16px;width:16px}
.campo-landing .lp-shot-side-line.active {background:var(--lp-accent);color:var(--lp-on-accent);font-weight:820}
.campo-landing .lp-shot-side-footer {margin-top:auto;color:color-mix(in srgb,var(--lp-on-deep) 40%,transparent);padding:0 8px;font-size:7px;letter-spacing:.13em}
.campo-landing .lp-shot-main {min-width:0}
.campo-landing .lp-shot-toolbar {display:flex;align-items:center;justify-content:space-between;height:54px;padding:0 20px;border-bottom:1px solid var(--lp-rule);font-size:10px;color:var(--lp-muted)}
.campo-landing .lp-shot-crumb b {padding:0 8px;color:var(--lp-faint);font-weight:400}
.campo-landing .lp-shot-avatar {width:27px;height:27px;border-radius:50%;display:grid;place-items:center;background:var(--lp-surface-soft);color:var(--lp-success);font-size:9px;font-weight:900}
.campo-landing .lp-shot-content {padding:22px}
.campo-landing .lp-shot-heading span {font-size:8px;letter-spacing:.15em;font-weight:880;color:var(--lp-success)}
.campo-landing .lp-shot-heading strong {display:block;margin-top:7px;font-size:19px;line-height:1.1;letter-spacing:-.05em}
.campo-landing .lp-shot-heading small {display:block;color:var(--lp-muted);font-size:10px;margin-top:8px}
.campo-landing .lp-shot-stats {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border:1px solid var(--lp-rule);border-radius:9px;overflow:hidden;margin-top:22px}
.campo-landing .lp-shot-stats>div {display:flex;flex-direction:column;gap:1px;min-width:0;padding:13px 10px;border-left:1px solid var(--lp-rule)}
.campo-landing .lp-shot-stats>div:first-child {border-left:0}
.campo-landing .lp-shot-stats span {font-size:9px;line-height:1.25;color:var(--lp-muted)}
.campo-landing .lp-shot-stats strong {font-size:25px;letter-spacing:-.06em;line-height:1.3;font-variant-numeric:tabular-nums}
.campo-landing .lp-shot-stats small {font-size:8px;color:var(--lp-faint);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.campo-landing .lp-shot-section-title {display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:19px;margin-bottom:8px}
.campo-landing .lp-shot-section-title b {font-size:11px}
.campo-landing .lp-shot-section-title span {font-size:9px;color:var(--lp-success);font-weight:780}
.campo-landing .lp-shot-list {border:1px solid var(--lp-rule);border-radius:8px;overflow:hidden}
.campo-landing .lp-shot-list>div {display:grid;grid-template-columns:27px minmax(0,1fr) auto;align-items:center;gap:9px;padding:9px 11px;border-top:1px solid var(--lp-rule)}
.campo-landing .lp-shot-list>div:first-child {border-top:0}
.campo-landing .lp-shot-list-icon {display:grid;place-items:center;width:25px;height:25px;border-radius:6px;background:var(--lp-surface-soft);color:var(--lp-success)}
.campo-landing .lp-shot-list-icon svg {width:13px;height:13px}
.campo-landing .lp-shot-list b {display:block;font-size:9px;line-height:1.2}
.campo-landing .lp-shot-list small {display:block;margin-top:2px;color:var(--lp-muted);font-size:8px;line-height:1.25}
.campo-landing .lp-shot-list em {border-radius:4px;background:var(--lp-alert-bg);color:var(--lp-warn);font-size:8px;font-weight:800;font-style:normal;padding:4px 6px}
.campo-landing .lp-shot-list em.complete {background:var(--lp-surface-soft);color:var(--lp-success)}
.campo-landing .lp-stage-footnote {text-align:right;margin-top:12px;font-size:10px;color:var(--lp-faint)}
.campo-landing .lp-hero-bottom {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-top:74px;border-top:1px solid var(--lp-rule);padding-block:18px 22px}
.campo-landing .lp-hero-bottom span {font-size:10px;font-weight:800;letter-spacing:.115em;color:var(--lp-muted)}

/* EDITORIAL / SECTIONS */
.campo-landing .lp-statement {border-top:1px solid var(--lp-rule);border-bottom:1px solid var(--lp-rule);background:var(--lp-surface)}
.campo-landing .lp-statement-inner {display:grid;grid-template-columns:1fr 3fr;align-items:start;gap:24px;padding-block:55px}
.campo-landing .lp-statement-inner .lp-section-index {padding-top:12px}
.campo-landing .lp-statement p {margin:0;max-width:940px;font-size:clamp(26px,3.35vw,45px);line-height:1.2;letter-spacing:-.055em;color:var(--lp-muted);font-weight:620}
.campo-landing .lp-statement strong {color:var(--lp-ink);font-weight:850}
.campo-landing .lp-section {padding-block:clamp(76px,8vw,122px)}
.campo-landing .lp-section-heading {max-width:830px;margin-bottom:47px}
.campo-landing .lp-section-heading h2,.campo-landing .lp-platform h2,.campo-landing .lp-assurance h2,.campo-landing .lp-faq h2,.campo-landing .lp-contact h2 {font-size:clamp(36px,4.1vw,61px);line-height:1.11;letter-spacing:-.062em;font-weight:850;margin:16px 0 18px}
.campo-landing .lp-section-heading h2 em,.campo-landing .lp-platform h2 em {font-style:normal;color:var(--lp-success)}
.theme-toggle-input:checked ~ .campo-landing .lp-section-heading h2 em,.theme-toggle-input:checked ~ .campo-landing .lp-platform h2 em {color:var(--lp-accent)}
.campo-landing .lp-section-heading p,.campo-landing .lp-platform-copy>p,.campo-landing .lp-assurance-layout>div>p,.campo-landing .lp-faq-grid>div>p,.campo-landing .lp-contact-copy>p {color:var(--lp-muted);max-width:570px;font-size:15px;line-height:1.75}
.campo-landing .lp-heading-split {max-width:none;display:grid;grid-template-columns:minmax(0,1fr) minmax(0,.7fr);gap:45px;align-items:end}
.campo-landing .lp-heading-split p {margin:0 0 11px}
.campo-landing .lp-features {background:var(--lp-canvas)}
.campo-landing .lp-feature-list {border-top:1px solid var(--lp-rule)}
.campo-landing .lp-feature-row {display:grid;grid-template-columns:45px 54px minmax(0,1fr) 125px;gap:24px;align-items:center;min-height:124px;padding:23px 16px;border-bottom:1px solid var(--lp-rule);transition:background .18s}
.campo-landing .lp-feature-row:hover {background:color-mix(in srgb,var(--lp-surface) 58%,transparent)}
.campo-landing .lp-feature-number {font-size:12px;font-weight:900;color:var(--lp-faint);letter-spacing:.02em}
.campo-landing .lp-feature-icon {display:grid;place-items:center;width:48px;height:48px;border:1px solid var(--lp-rule);background:var(--lp-surface);border-radius:10px;color:var(--lp-success)}
.campo-landing .lp-feature-icon .lp-icon {width:21px;height:21px}
.campo-landing .lp-feature-row h3 {font-size:clamp(18px,1.6vw,23px);margin:0 0 5px;letter-spacing:-.035em;font-weight:810}
.campo-landing .lp-feature-row p {font-size:13px;line-height:1.55;color:var(--lp-muted);margin:0;max-width:650px}
.campo-landing .lp-feature-tag {font-size:10px;color:var(--lp-faint);font-weight:800;letter-spacing:.12em;text-align:right}

/* PRODUCT PRESENTATION */
.campo-landing .lp-platform {background:var(--lp-surface);border-block:1px solid var(--lp-rule)}
.campo-landing .lp-platform-inner {display:grid;grid-template-columns:minmax(0,.72fr) minmax(0,1.28fr);gap:clamp(36px,5vw,85px);align-items:center}
.campo-landing .lp-platform-copy h2 {max-width:540px}
.campo-landing .lp-platform-points {margin:34px 0 33px;border-top:1px solid var(--lp-rule)}
.campo-landing .lp-platform-points>div {display:grid;grid-template-columns:30px minmax(0,1fr);gap:0 12px;padding:15px 0;border-bottom:1px solid var(--lp-rule)}
.campo-landing .lp-platform-points span {grid-row:span 2;color:var(--lp-success);font-weight:900;font-size:11px;margin-top:2px}
.campo-landing .lp-platform-points strong {font-size:13px;font-weight:830}
.campo-landing .lp-platform-points small {margin-top:4px;color:var(--lp-muted);font-size:11px;line-height:1.5}
.campo-landing .lp-platform-visual {display:grid;grid-template-columns:minmax(0,1fr) 170px;gap:14px;align-items:end;min-width:0}
.campo-landing .lp-platform-window {border:1px solid var(--lp-rule);border-radius:12px;overflow:hidden;min-height:408px;background:var(--lp-canvas)}
.campo-landing .lp-window-top {height:45px;border-bottom:1px solid var(--lp-rule);display:flex;justify-content:space-between;align-items:center;padding:0 18px}
.campo-landing .lp-window-dots {display:flex;gap:6px}.campo-landing .lp-window-dots i {width:7px;height:7px;background:var(--lp-faint);border-radius:50%;opacity:.7}
.campo-landing .lp-window-top small {font-size:9px;font-weight:780;letter-spacing:.11em;color:var(--lp-faint)}
.campo-landing .lp-platform-window-body {padding:24px}
.campo-landing .lp-window-label {color:var(--lp-success);font-size:10px;letter-spacing:.12em;font-weight:850}
.campo-landing .lp-platform-window h3 {font-size:clamp(23px,2.4vw,31px);line-height:1.13;letter-spacing:-.055em;font-weight:850;margin:8px 0 26px}
.campo-landing .lp-window-note {display:flex;align-items:center;gap:14px;padding:20px;background:var(--lp-surface);border:1px solid var(--lp-rule);border-radius:9px}
.campo-landing .lp-window-note-num {font-size:40px;line-height:1;color:var(--lp-success);font-weight:900;letter-spacing:-.07em}
.campo-landing .lp-window-note div {flex:1;min-width:0}
.campo-landing .lp-window-note strong {display:block;font-size:12px}.campo-landing .lp-window-note small {display:block;font-size:10px;line-height:1.4;color:var(--lp-muted);margin-top:4px}
.campo-landing .lp-window-note>.lp-icon {width:17px;height:17px;color:var(--lp-success)}
.campo-landing .lp-window-rows {margin-top:15px;border:1px solid var(--lp-rule);border-radius:9px;background:var(--lp-surface);overflow:hidden}
.campo-landing .lp-window-rows>div {display:flex;gap:12px;justify-content:space-between;align-items:center;padding:13px 14px;border-top:1px solid var(--lp-rule)}
.campo-landing .lp-window-rows>div:first-child {border-top:0}
.campo-landing .lp-window-rows span {display:inline-flex;gap:8px;align-items:center;font-size:11px;font-weight:650}
.campo-landing .lp-window-rows strong {font-size:10px;color:var(--lp-muted);font-weight:790;white-space:nowrap}
.campo-landing .lp-window-status {width:7px;height:7px;border-radius:50%}.campo-landing .lp-window-status.ok {background:var(--lp-success)}.campo-landing .lp-window-status.wait {background:var(--lp-warn)}
.campo-landing .lp-platform-aside {min-width:0;text-align:center}
.campo-landing .lp-platform-aside-label,.campo-landing .lp-platform-device-note {font-size:9px;letter-spacing:.13em;font-weight:820;color:var(--lp-muted);display:block}
.campo-landing .lp-platform-aside-label {margin-bottom:13px}
.campo-landing .lp-platform-device-note {margin-top:12px;line-height:1.4}
.campo-landing .lp-phone-illustration {position:relative;text-align:left;min-height:328px;border:6px solid var(--lp-deep);border-radius:25px;background:var(--lp-surface);padding:31px 12px 38px;box-shadow:var(--lp-shadow);overflow:hidden}
.campo-landing .lp-phone-top {position:absolute;top:7px;left:50%;transform:translateX(-50%);width:46px;height:5px;border-radius:10px;background:var(--lp-deep)}
.campo-landing .lp-phone-illustration>small {display:block;font-size:8px;letter-spacing:.13em;color:var(--lp-success);font-weight:900}
.campo-landing .lp-phone-illustration>strong {display:block;font-size:18px;letter-spacing:-.04em;margin-top:6px}
.campo-landing .lp-phone-illustration>span {font-size:9px;color:var(--lp-muted);display:block;margin-top:19px;margin-bottom:5px}
.campo-landing .lp-phone-line {display:flex;align-items:center;gap:6px;padding:11px 5px;border-bottom:1px solid var(--lp-rule);font-size:9px}
.campo-landing .lp-phone-line .lp-icon {width:13px;height:13px;color:var(--lp-success)}
.campo-landing .lp-phone-line span {flex:1}.campo-landing .lp-phone-line b {font-size:10px}
.campo-landing .lp-phone-bottom {position:absolute;bottom:0;left:0;right:0;display:flex;justify-content:space-around;align-items:center;height:36px;background:var(--lp-surface-soft);border-top:1px solid var(--lp-rule)}
.campo-landing .lp-phone-bottom i {height:11px;width:11px;border:1.5px solid var(--lp-faint);border-radius:3px}
.campo-landing .lp-phone-bottom i:first-child {background:var(--lp-success);border-color:var(--lp-success)}

/* PROCESS */
.campo-landing .lp-process {background:var(--lp-canvas)}
.campo-landing .lp-process-grid {display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:0;border-top:1px solid var(--lp-rule)}
.campo-landing .lp-process-grid article {padding:28px 24px 14px;border-left:1px solid var(--lp-rule)}
.campo-landing .lp-process-grid article:first-child {border-left:0;padding-left:0}
.campo-landing .lp-process-grid article:last-child {padding-right:0}
.campo-landing .lp-process-grid article>span {display:flex;justify-content:space-between;align-items:center;color:var(--lp-success);font-size:12px;font-weight:900;margin-bottom:30px}
.campo-landing .lp-process-grid article>span svg {width:17px;height:17px}
.campo-landing .lp-process-grid h3 {font-size:24px;line-height:1.16;letter-spacing:-.05em;margin-bottom:13px;font-weight:820}
.campo-landing .lp-process-grid p {max-width:240px;color:var(--lp-muted);font-size:12px;line-height:1.65}

/* ASSURANCE */
.campo-landing .lp-assurance {background:var(--lp-deep);color:var(--lp-on-deep)}
.campo-landing .lp-assurance-layout {display:grid;grid-template-columns:minmax(0,1fr) minmax(0,.92fr);gap:clamp(48px,8vw,125px);align-items:start}
.campo-landing .lp-assurance .lp-eyebrow {color:var(--lp-accent)}
.campo-landing .lp-assurance h2 {max-width:590px;font-size:clamp(36px,4.2vw,62px)}
.campo-landing .lp-assurance-layout>div>p {color:color-mix(in srgb,var(--lp-on-deep) 70%,transparent);margin:0 0 30px}
.campo-landing .lp-assurance-list {border-top:1px solid color-mix(in srgb,var(--lp-on-deep) 17%,transparent)}
.campo-landing .lp-assurance-list>div {display:flex;gap:18px;padding:21px 6px;border-bottom:1px solid color-mix(in srgb,var(--lp-on-deep) 17%,transparent)}
.campo-landing .lp-assurance-list>div>.lp-icon {width:24px;height:24px;color:var(--lp-accent);margin-top:3px}
.campo-landing .lp-assurance-list strong {display:block;font-size:14px;font-weight:790}
.campo-landing .lp-assurance-list small {display:block;margin-top:5px;font-size:12px;line-height:1.5;color:color-mix(in srgb,var(--lp-on-deep) 60%,transparent)}

/* FAQ */
.campo-landing .lp-faq {background:var(--lp-surface);border-bottom:1px solid var(--lp-rule)}
.campo-landing .lp-faq-grid {display:grid;grid-template-columns:minmax(0,.78fr) minmax(0,1fr);gap:clamp(45px,8vw,120px)}
.campo-landing .lp-faq-grid>div>p {margin:0 0 30px;max-width:360px}
.campo-landing .lp-faq-list {border-top:1px solid var(--lp-rule)}
.campo-landing .lp-faq-list details {border-bottom:1px solid var(--lp-rule)}
.campo-landing .lp-faq-list summary {list-style:none;display:flex;gap:16px;justify-content:space-between;align-items:center;padding:23px 0;font-size:15px;font-weight:770;cursor:pointer;line-height:1.4}
.campo-landing .lp-faq-list summary::-webkit-details-marker {display:none}
.campo-landing .lp-faq-list summary .lp-icon {width:18px;height:18px;transition:transform .18s;color:var(--lp-success)}
.campo-landing .lp-faq-list details[open] summary .lp-icon {transform:rotate(180deg)}
.campo-landing .lp-faq-list details p {padding:0 42px 23px 0;margin:0;color:var(--lp-muted);font-size:13px;line-height:1.75}

/* CONTACT */
.campo-landing .lp-contact {background:var(--lp-canvas)}
.campo-landing .lp-contact-grid {display:grid;grid-template-columns:minmax(0,.75fr) minmax(0,1fr);gap:clamp(40px,7vw,115px);align-items:start}
.campo-landing .lp-contact-copy {padding-top:25px}
.campo-landing .lp-contact-copy h2 {max-width:550px}
.campo-landing .lp-contact-note {border-top:1px solid var(--lp-rule);margin-top:60px;padding-top:21px;max-width:370px}
.campo-landing .lp-contact-note span {display:block;font-size:10px;font-weight:870;letter-spacing:.14em;color:var(--lp-success);margin-bottom:8px}
.campo-landing .lp-contact-note strong {font-size:15px;line-height:1.5;letter-spacing:-.02em;font-weight:740}
.campo-landing .lp-contact-form {background:var(--lp-surface);border:1px solid var(--lp-rule);border-radius:13px;overflow:hidden;box-shadow:var(--lp-shadow);min-width:0}
.campo-landing .lp-contact-form-head {padding:25px 28px 21px;border-bottom:1px solid var(--lp-rule)}
.campo-landing .lp-contact-form-head strong {display:block;font-size:21px;letter-spacing:-.045em}
.campo-landing .lp-contact-form-head small {display:block;font-size:12px;color:var(--lp-muted);margin-top:5px}
.campo-landing .lp-form-grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px;padding:24px 28px}
.campo-landing .lp-form-grid .field {display:flex;flex-direction:column;min-width:0;gap:7px}
.campo-landing .lp-form-grid .field.wide {grid-column:1/-1}
.campo-landing .lp-form-grid label span {font-size:12px;font-weight:790;color:var(--lp-ink)}
.campo-landing .lp-form-grid label em {font-style:normal;color:var(--lp-success)}
.campo-landing .lp-form-grid input,.campo-landing .lp-form-grid textarea {display:block;width:100%;min-width:0;padding:12px 13px;min-height:46px;background:var(--lp-canvas);border:1px solid var(--lp-rule);border-radius:8px;color:var(--lp-ink);font-size:13px;font-weight:500;outline:none;transition:border-color .15s}
.campo-landing .lp-form-grid textarea {min-height:118px;resize:vertical}
.campo-landing .lp-form-grid input::placeholder,.campo-landing .lp-form-grid textarea::placeholder {color:var(--lp-faint)}
.campo-landing .lp-form-grid input:focus,.campo-landing .lp-form-grid textarea:focus {border-color:var(--lp-success);box-shadow:0 0 0 3px color-mix(in srgb,var(--lp-success) 14%,transparent)}
.campo-landing .lp-form-footer {display:flex;align-items:center;justify-content:space-between;gap:20px;padding:20px 28px;border-top:1px solid var(--lp-rule)}
.campo-landing .lp-form-footer p {font-size:11px;line-height:1.6;color:var(--lp-muted);max-width:260px;margin:0}
.campo-landing .lp-form-footer p a {font-weight:790;text-decoration:underline;text-underline-offset:2px}
.campo-landing .lp-form-footer .lp-button {min-height:46px}

/* FOOTER & COOKIES */
.campo-landing .lp-footer {background:var(--lp-deep);color:var(--lp-on-deep);padding:65px 0 25px}
.campo-landing .lp-footer .lp-logo-copy small {color:color-mix(in srgb,var(--lp-on-deep) 58%,transparent)}
.campo-landing .lp-footer .lp-logo-dot {color:var(--lp-accent)}
.campo-landing .lp-footer-top {display:grid;grid-template-columns:1fr 1fr;gap:40px;padding-bottom:55px}
.campo-landing .lp-footer-top>div>p {max-width:360px;color:color-mix(in srgb,var(--lp-on-deep) 62%,transparent);font-size:13px;line-height:1.65;margin-top:19px}
.campo-landing .lp-footer-links {display:grid;grid-template-columns:1fr 1fr;gap:26px}
.campo-landing .lp-footer-links>div {display:flex;flex-direction:column;align-items:flex-start;gap:9px}
.campo-landing .lp-footer-links strong {font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--lp-accent);margin-bottom:8px}
.campo-landing .lp-footer-links a,.campo-landing .lp-footer-links button {background:none;border:0;padding:0;color:color-mix(in srgb,var(--lp-on-deep) 72%,transparent);font-size:12px;line-height:1.7;text-align:left}
.campo-landing .lp-footer-links a:hover,.campo-landing .lp-footer-links button:hover {color:var(--lp-on-deep)}
.campo-landing .lp-footer-bottom {border-top:1px solid color-mix(in srgb,var(--lp-on-deep) 14%,transparent);display:flex;justify-content:space-between;gap:22px;align-items:center;padding-top:22px;font-size:11px;color:color-mix(in srgb,var(--lp-on-deep) 54%,transparent)}
.campo-landing .lp-footer-bottom div {display:flex;gap:20px}
.campo-landing .lp-toast {position:fixed;z-index:100;left:50%;bottom:28px;transform:translateX(-50%);background:var(--lp-surface);color:var(--lp-ink);border:1px solid var(--lp-rule);padding:13px 20px;border-radius:9px;box-shadow:var(--lp-shadow);font-size:12px;font-weight:750;max-width:min(92vw,540px)}
.campo-landing .lp-toast.hidden {display:none!important}
.campo-landing .lp-cookie-modal-body {padding:20px}
.campo-landing .lp-cookie-actions {display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end;margin-top:22px}
.campo-landing .cookie-close {display:grid;place-items:center;min-width:38px;height:38px;border-radius:8px;border:1px solid var(--lp-rule);background:var(--lp-surface);color:var(--lp-muted);font-size:24px}
.campo-landing .cookie-banner,.campo-landing .cookie-modal {background:var(--lp-surface);color:var(--lp-ink);border-color:var(--lp-rule)}
.campo-landing .cookie-modal p,.campo-landing .cookie-banner p {color:var(--lp-muted)}
.campo-landing .cookie-modal-header {border-color:var(--lp-rule)}
.campo-landing .cookie-btn {border-color:var(--lp-rule);color:var(--lp-ink);background:var(--lp-surface-soft)}
.campo-landing .cookie-btn-primary {background:var(--lp-accent);color:var(--lp-on-accent);border-color:var(--lp-accent)}
.campo-landing .cookie-category {border-color:var(--lp-rule)}
.campo-landing .cookie-category h4 {color:var(--lp-ink)}

/* RESPONSIVE */
@media (max-width:1200px) {
  .campo-landing .lp-main-nav {gap:14px}
  .campo-landing .lp-header-inner {gap:18px}
  .campo-landing .lp-header-actions {gap:7px}
  .campo-landing .lp-hero-grid {gap:34px}
  .campo-landing .lp-app-shot {grid-template-columns:100px minmax(0,1fr)}
  .campo-landing .lp-shot-side-line {font-size:9px;gap:6px;padding-inline:5px}
  .campo-landing .lp-shot-content {padding:16px}
  .campo-landing .lp-platform-visual {grid-template-columns:minmax(0,1fr) 136px}
  .campo-landing .lp-phone-illustration {padding-inline:8px}
}
@media (max-width:1024px) {
  .campo-landing .lp-main-nav {display:none}
  .campo-landing .lp-menu-button {display:grid}
  .campo-landing .lp-header-actions {margin-left:auto}
  .campo-landing .lp-hero-grid {grid-template-columns:1fr}
  .campo-landing .lp-hero-copy {padding-bottom:8px}
  .campo-landing .lp-hero h1 {max-width:850px;font-size:clamp(45px,6.6vw,74px)}
  .campo-landing .lp-lead {max-width:700px}
  .campo-landing .lp-product-stage {max-width:850px;width:100%;margin-inline:auto}
  .campo-landing .lp-app-shot {grid-template-columns:145px minmax(0,1fr)}
  .campo-landing .lp-shot-content {padding:22px}
  .campo-landing .lp-platform-inner {grid-template-columns:1fr}
  .campo-landing .lp-platform-copy h2 {max-width:800px}
  .campo-landing .lp-platform-visual {max-width:810px;margin:auto;width:100%}
  .campo-landing .lp-assurance-layout {gap:42px}
}
@media (max-width:767px) {
  .campo-landing .lp-container {width:min(100% - 38px,1336px)}
  .campo-landing .lp-header-inner {height:68px}
  .campo-landing .lp-logo-mark {width:39px;height:39px}
  .campo-landing .lp-logo-copy strong {font-size:23px}
  .campo-landing .lp-logo-copy small {font-size:7px}
  .campo-landing .lp-header-actions {gap:7px}
  .campo-landing .lp-signin {height:42px;padding-inline:13px}
    .campo-landing .lp-hero {padding-top:51px}
  .campo-landing .lp-hero h1 {font-size:clamp(39px,9vw,61px);margin:23px 0 20px;max-width:640px}
  .campo-landing .lp-lead {font-size:14px;line-height:1.7}
  .campo-landing .lp-hero-cta {margin-top:25px}
  .campo-landing .lp-hero-proof {margin-top:26px}
  .campo-landing .lp-hero-bottom {margin-top:40px;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding-block:19px}
  .campo-landing .lp-statement-inner {grid-template-columns:1fr;padding-block:37px;gap:13px}
  .campo-landing .lp-statement-inner .lp-section-index {padding:0}
  .campo-landing .lp-statement p {font-size:clamp(25px,6vw,39px)}
  .campo-landing .lp-section {padding-block:71px}
  .campo-landing .lp-section-heading h2,.campo-landing .lp-platform h2,.campo-landing .lp-assurance h2,.campo-landing .lp-faq h2,.campo-landing .lp-contact h2 {font-size:clamp(35px,8vw,50px)}
  .campo-landing .lp-section-heading {margin-bottom:30px}
  .campo-landing .lp-heading-split {grid-template-columns:1fr;gap:10px}
  .campo-landing .lp-feature-row {grid-template-columns:28px 40px minmax(0,1fr);gap:12px;min-height:0;padding:22px 0}
  .campo-landing .lp-feature-icon {width:39px;height:39px}
  .campo-landing .lp-feature-icon .lp-icon {width:18px;height:18px}
  .campo-landing .lp-feature-number {font-size:10px}
  .campo-landing .lp-feature-row h3 {font-size:18px}
  .campo-landing .lp-feature-row p {font-size:12px}
  .campo-landing .lp-feature-tag {display:none}
  .campo-landing .lp-process-grid {grid-template-columns:repeat(2,minmax(0,1fr));row-gap:13px}
  .campo-landing .lp-process-grid article {padding:21px 13px;border-bottom:1px solid var(--lp-rule)}
  .campo-landing .lp-process-grid article:nth-child(odd) {padding-left:0;border-left:0}
  .campo-landing .lp-process-grid h3 {font-size:22px}
  .campo-landing .lp-process-grid article>span {margin-bottom:19px}
  .campo-landing .lp-assurance-layout,.campo-landing .lp-faq-grid,.campo-landing .lp-contact-grid {grid-template-columns:1fr;gap:38px}
  .campo-landing .lp-contact-copy {padding-top:0}
  .campo-landing .lp-contact-note {margin-top:29px}
  .campo-landing .lp-footer-top {grid-template-columns:1fr}
}
@media (max-width:520px) {
  .campo-landing .lp-container {width:min(100% - 32px,1336px)}
  .campo-landing .lp-logo-copy small {display:none}
  .campo-landing .lp-logo {gap:9px}
  .campo-landing .lp-menu-button {width:40px;height:40px}
  .campo-landing .lp-install:not([hidden]) {width:40px;height:40px;justify-content:center;padding:0}
  .campo-landing .lp-install:not([hidden]) span {display:none}
  .campo-landing .lp-install:not([hidden]) ~ .lp-signin {display:none}
  .campo-landing .lp-signin {height:40px;padding-inline:11px;gap:5px}
  .campo-landing .lp-signin .lp-icon {width:14px;height:14px}
  .campo-landing .lp-hero h1 {font-size:clamp(38px,10.5vw,49px)}
  .campo-landing .lp-hero-cta {display:grid;grid-template-columns:1fr;gap:8px}
  .campo-landing .lp-hero-cta .lp-button {justify-content:space-between;width:100%}
  .campo-landing .lp-app-shot {grid-template-columns:49px minmax(0,1fr);min-height:0}
  .campo-landing .lp-shot-side {padding:13px 4px}
  .campo-landing .lp-shot-mark {width:29px;height:29px;margin:0 auto 15px}
  .campo-landing .lp-shot-side-line {justify-content:center;padding:8px 0}
  .campo-landing .lp-shot-side-line span,.campo-landing .lp-shot-side-footer {display:none}
  .campo-landing .lp-shot-content {padding:13px}
  .campo-landing .lp-shot-toolbar {height:43px;padding-inline:12px}
  .campo-landing .lp-shot-heading strong {font-size:16px}
  .campo-landing .lp-shot-stats {margin-top:13px;grid-template-columns:repeat(2,minmax(0,1fr))}
  .campo-landing .lp-shot-stats>div:nth-child(3) {display:none}
  .campo-landing .lp-shot-stats>div {padding:10px}
  .campo-landing .lp-shot-stats strong {font-size:23px}
  .campo-landing .lp-shot-list>div {grid-template-columns:22px minmax(0,1fr) auto;gap:5px;padding:9px 7px}
  .campo-landing .lp-shot-list-icon {width:21px;height:21px}
  .campo-landing .lp-shot-list em {font-size:7px;padding:3px}
  .campo-landing .lp-shot-list small {max-width:150px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .campo-landing .lp-stage-label {font-size:9px}
  .campo-landing .lp-hero-bottom span {font-size:9px}
  .campo-landing .lp-platform-visual {grid-template-columns:1fr}
  .campo-landing .lp-platform-aside {display:none}
  .campo-landing .lp-platform-window-body {padding:17px}
  .campo-landing .lp-window-note {padding:13px}
  .campo-landing .lp-window-note-num {font-size:32px}
  .campo-landing .lp-window-rows>div {padding:11px 9px}
  .campo-landing .lp-window-rows strong {font-size:9px}
  .campo-landing .lp-form-grid {grid-template-columns:1fr;padding:18px 16px;gap:14px}
  .campo-landing .lp-contact-form-head {padding:20px 16px}
  .campo-landing .lp-form-footer {display:grid;padding:17px 16px;gap:13px}
  .campo-landing .lp-form-footer p {max-width:none}
  .campo-landing .lp-form-footer .lp-button {width:100%}
  .campo-landing .lp-footer-links {gap:18px}
  .campo-landing .lp-footer-bottom {align-items:flex-start;flex-direction:column;gap:12px}
  .campo-landing .lp-faq-list summary {font-size:13px}
}
@media (prefers-reduced-motion:reduce) {
  .campo-landing *, .campo-landing *::before,.campo-landing *::after {scroll-behavior:auto!important;animation-duration:.01ms!important;transition-duration:.01ms!important}
}

/* Grafite & Lima: ouro nos destaques da marca, verde somente em estados funcionais. */
.theme-toggle-input:checked ~ .campo-landing :is(.lp-eyebrow,.lp-kicker,.lp-section-index,.lp-window-label,.lp-shot-heading span,.lp-contact-note span) {color:var(--lp-accent)}
.theme-toggle-input:checked ~ .campo-landing .lp-kicker-rule {background:var(--lp-accent)}
.theme-toggle-input:checked ~ .campo-landing :is(.lp-feature-icon,.lp-platform-points>div>span,.lp-window-note-num,.lp-shot-list-icon,.lp-shot-section-title span) {color:var(--lp-accent)}

/* Independente de Tailwind, Vite, frameworks e imports externos. */
html { scroll-behavior:smooth; }
body { margin:0; background:#f4f4ef; }
[hidden] { display:none!important; }
.theme-toggle-input { position:absolute; width:1px; height:1px; opacity:0; overflow:hidden; clip-path:inset(50%); }
.theme-toggle-input:focus-visible ~ .campo-landing .lp-theme-toggle { outline:3px solid #a88952; outline-offset:3px; }
.campo-landing { display:block; min-height:100vh; }
.campo-landing .lp-theme-toggle { display:grid; place-items:center; width:43px; height:43px; flex:0 0 auto; border-radius:9px; border:1px solid var(--lp-rule); background:var(--lp-surface); color:var(--lp-ink); cursor:pointer; }
.campo-landing .lp-theme-toggle:hover { background:var(--lp-surface-soft); }
.campo-landing .lp-theme-toggle .lp-icon { width:20px; height:20px; }
.campo-landing .lp-theme-toggle .theme-sun { display:none; }
.theme-toggle-input:checked ~ .campo-landing .lp-theme-toggle .theme-sun { display:block; }
.theme-toggle-input:checked ~ .campo-landing .lp-theme-toggle .theme-moon { display:none; }
.campo-landing .lp-menu-details { display:none; position:relative; }
.campo-landing .lp-menu-details summary { list-style:none; cursor:pointer; }
.campo-landing .lp-menu-details summary::-webkit-details-marker { display:none; }
.campo-landing .lp-menu-details .lp-menu-button { display:grid; }
.campo-landing .lp-menu-dropdown { position:absolute; top:calc(100% + 13px); right:0; z-index:80; width:min(310px,calc(100vw - 28px)); padding:10px 16px 14px; border-radius:13px; border:1px solid var(--lp-rule); background:var(--lp-surface); box-shadow:var(--lp-shadow); }
.campo-landing .lp-menu-dropdown a { display:block; padding:12px 4px; border-bottom:1px solid var(--lp-rule); color:var(--lp-ink); font-size:14px; font-weight:750; }
.campo-landing .lp-menu-dropdown a:last-child { border-bottom:0; }
.campo-landing .lp-menu-dropdown a:hover { color:var(--lp-success); }
.campo-landing .lp-form-notice { margin:0 28px 20px; padding:12px 14px; border-radius:8px; border:1px solid var(--lp-rule); background:var(--lp-surface-soft); color:var(--lp-muted); font-size:12px; line-height:1.5; }
.campo-landing .lp-logo-mark svg,.campo-landing .lp-shot-mark svg { display:block; width:31px; height:31px; fill:none; stroke:currentColor; stroke-width:2.3; stroke-linecap:round; stroke-linejoin:round; }
.campo-landing .lp-shot-mark svg { width:24px; height:24px; }
.campo-landing .lp-shot-mark { color:#233523; }
@media (max-width:1024px) { .campo-landing .lp-menu-details { display:block; } }
@media (max-width:520px) { .campo-landing .lp-theme-toggle { width:40px; height:40px; } .campo-landing .lp-form-notice { margin-inline:16px; } }

    .campo-landing .lp-shot-mark img { width: 24px; height: 24px; object-fit: contain; }
  </style>
</head>
<body>
  <input id="sgcDarkMode" class="theme-toggle-input" type="checkbox" aria-label="Ativar tema escuro">
  <div class="campo-welcome campo-landing">

  <svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" class="lp-icons" viewBox="0 0 24 24">
    <symbol id="lp-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4m0-14.2-1.4 1.4M6.3 17.7l-1.4 1.4"/></symbol>
    <symbol id="lp-moon" viewBox="0 0 24 24"><path d="M20.4 15.3A9 9 0 0 1 8.7 3.6 9 9 0 1 0 20.4 15.3Z"/></symbol>
    <symbol id="lp-arrow" viewBox="0 0 24 24"><path d="M4 12h16m-7-7 7 7-7 7"/></symbol>
    <symbol id="lp-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
    <symbol id="lp-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
    <symbol id="lp-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
    <symbol id="lp-lock" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></symbol>
    <symbol id="lp-check" viewBox="0 0 24 24"><path d="m4 12 5 5L20 6"/></symbol>
    <symbol id="lp-users" viewBox="0 0 24 24"><path d="M16 20v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 20v-2a4 4 0 0 0-3-3.87M16 4a4 4 0 0 1 0 8"/><circle cx="9" cy="7" r="4"/></symbol>
    <symbol id="lp-box" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5Zm-9 5v9l9 5 9-5V8M12 13v9"/></symbol>
    <symbol id="lp-wallet" viewBox="0 0 24 24"><path d="M20 7V5a2 2 0 0 0-2-2H5a3 3 0 0 0-3 3v13a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-9a3 3 0 0 0-3-3H3"/><path d="M22 12h-7a2 2 0 0 0 0 4h7"/></symbol>
    <symbol id="lp-archive" viewBox="0 0 24 24"><path d="M4 4h16v4H4zM6 8v12h12V8m-9 5h6"/></symbol>
    <symbol id="lp-chart" viewBox="0 0 24 24"><path d="M3 3v18h18M7 16l5-5 4 3 5-7"/></symbol>
    <symbol id="lp-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></symbol>
    <symbol id="lp-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-13 5 2 2 5-5"/></symbol>
    <symbol id="lp-layers" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/></symbol>
    <symbol id="lp-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></symbol>
    <symbol id="lp-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h8"/></symbol>
    <symbol id="lp-spark" viewBox="0 0 24 24"><path d="m12 2 2.4 7.6L22 12l-7.6 2.4L12 22l-2.4-7.6L2 12l7.6-2.4L12 2z"/></symbol>
    <symbol id="lp-download" viewBox="0 0 24 24"><path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/></symbol>
    <symbol id="lp-phone" viewBox="0 0 24 24"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></symbol>
  </svg>

  <header id="top" class="lp-header">
    <div class="lp-container lp-header-inner">
      <a class="lp-logo" href="#inicio" aria-label="SGC, voltar ao início">
        <span class="lp-logo-mark" aria-hidden="true"><img src="{{ asset('assets/sgc-mark-light.png') }}" alt="" width="32" height="32"></span>
        <span class="lp-logo-copy"><strong>SGC<span class="lp-logo-dot">.</span></strong><small>GESTÃO COOPERATIVA</small></span>
      </a>
      <nav class="lp-main-nav" aria-label="Navegação principal">
        <a class="nav-link" href="#recursos">Recursos</a>
        <a class="nav-link" href="#plataforma">A plataforma</a>
        <a class="nav-link" href="#beneficios">Como funciona</a>
        <a class="nav-link" href="#seguranca">Segurança</a>
        <a class="nav-link" href="#faq">Dúvidas</a>
      </nav>
      <div class="lp-header-actions">
        <label for="sgcDarkMode" class="lp-theme-toggle" title="Alternar tema claro ou escuro" aria-label="Alternar tema claro ou escuro"><svg class="lp-icon theme-moon" aria-hidden="true"><use href="#lp-moon"/></svg><svg class="lp-icon theme-sun" aria-hidden="true"><use href="#lp-sun"/></svg></label>
<a class="lp-signin" href="/login">Entrar <svg class="lp-icon" aria-hidden="true"><use href="#lp-arrow"/></svg></a>
        <details class="lp-menu-details">
          <summary class="lp-menu-button" aria-label="Abrir navegação"><svg class="lp-icon" aria-hidden="true"><use href="#lp-menu"/></svg></summary>
          <nav class="lp-menu-dropdown" aria-label="Navegação mobile">
            <a href="#recursos">Recursos</a>
            <a href="#plataforma">A plataforma</a>
            <a href="#beneficios">Como funciona</a>
            <a href="#seguranca">Segurança</a>
            <a href="#faq">Perguntas frequentes</a>
            <a href="#contato">Contato</a>
            <a href="/login">Entrar no SGC</a>
          </nav>
        </details>
      </div>
    </div>
  </header>

  <main>
    <section id="inicio" class="lp-hero">
      <div class="lp-container lp-hero-grid">
        <div class="lp-hero-copy">
          <div class="lp-kicker"><span class="lp-kicker-rule"></span> GESTÃO PARA COOPERATIVAS E ASSOCIAÇÕES</div>
          <h1>Da entrega à <span>prestação de contas.</span></h1>
          <p class="lp-lead">Registre o que cada produtor entrega, acompanhe a distribuição para escolas e outros clientes e confira os valores antes de emitir recibos. Gerencie também as ordens de serviço e os pagamentos aos prestadores.</p>
          <div class="lp-hero-cta">
            <a href="#contato" class="lp-button lp-button-primary">Conversar sobre o SGC <svg class="lp-icon" aria-hidden="true"><use href="#lp-arrow"/></svg></a>
            <a href="#plataforma" class="lp-button lp-button-outline">Explorar a plataforma</a>
          </div>
        </div>
        <div class="lp-product-stage" aria-label="Exemplo de fluxo de entregas no SGC">
          <div class="lp-stage-label">EXEMPLO DE USO <span>/ PROJETO PNAE</span></div>
          <div class="lp-app-shot">
            <aside class="lp-shot-side" aria-hidden="true">
              <span class="lp-shot-mark"><img src="{{ asset('assets/sgc-mark-light.png') }}" alt="" width="24" height="24"></span>
              <div class="lp-shot-side-line active"><svg class="lp-icon"><use href="#lp-box"/></svg><span>Entregas</span></div>
              <div class="lp-shot-side-line"><svg class="lp-icon"><use href="#lp-users"/></svg><span>Produtores</span></div>
              <div class="lp-shot-side-line"><svg class="lp-icon"><use href="#lp-file"/></svg><span>Recibos</span></div>
            </aside>
            <div class="lp-shot-main">
              <div class="lp-shot-toolbar"><span class="lp-shot-crumb">Projetos <b>/</b> PNAE</span></div>
              <div class="lp-shot-content">
                <div class="lp-shot-heading"><strong>Da produção à escola</strong><small>Exemplo de uma entrega de banana</small></div>
                <div class="lp-shot-list">
                  <div><span class="lp-shot-list-icon"><svg class="lp-icon"><use href="#lp-box"/></svg></span><span><b>Recebimento</b><small>20 kg recebidos do produtor</small></span><em class="complete">Registrado</em></div>
                  <div><span class="lp-shot-list-icon"><svg class="lp-icon"><use href="#lp-layers"/></svg></span><span><b>Distribuição</b><small>12 kg para a escola e 8 kg para a creche</small></span><em class="complete">Distribuído</em></div>
                  <div><span class="lp-shot-list-icon"><svg class="lp-icon"><use href="#lp-file"/></svg></span><span><b>Conferência</b><small>Quantidades e destinos prontos para revisão</small></span><em>A conferir</em></div>
                </div>
              </div>
            </div>
          </div>
          <p class="lp-stage-footnote">Ilustração do fluxo de entregas e distribuição</p>
        </div>
      </div>
    </section>

    <section id="recursos" class="lp-section lp-features">
      <div class="lp-container">
        <div class="lp-section-heading lp-heading-split"><div><span class="lp-eyebrow">O QUE O SGC REÚNE</span><h2>Uma gestão só.<br><em>Sem informações soltas.</em></h2></div><p>Um cadastro alimenta a próxima etapa. Cada movimentação mantém o contexto da organização, do projeto e das pessoas envolvidas.</p></div>
        <div class="lp-feature-list">
          <article class="lp-feature-row"><span class="lp-feature-number">01</span><span class="lp-feature-icon"><svg class="lp-icon"><use href="#lp-users"/></svg></span><div><h3>Pessoas e organizações</h3><p>Associados, clientes, equipes, vínculos, acessos e responsabilidades em um lugar só.</p></div><span class="lp-feature-tag">CADASTROS</span></article>
          <article class="lp-feature-row"><span class="lp-feature-number">02</span><span class="lp-feature-icon"><svg class="lp-icon"><use href="#lp-box"/></svg></span><div><h3>Projetos e entregas</h3><p>Registros, quantidades, destinos e distribuições acompanhados de ponta a ponta.</p></div><span class="lp-feature-tag">OPERAÇÃO</span></article>
          <article class="lp-feature-row"><span class="lp-feature-number">03</span><span class="lp-feature-icon"><svg class="lp-icon"><use href="#lp-wallet"/></svg></span><div><h3>Financeiro conectado</h3><p>Recebimentos, pagamentos e saldos relacionados aos fatos que lhes deram origem.</p></div><span class="lp-feature-tag">FINANCEIRO</span></article>
          <article class="lp-feature-row"><span class="lp-feature-number">04</span><span class="lp-feature-icon"><svg class="lp-icon"><use href="#lp-file"/></svg></span><div><h3>Documentos e comprovantes</h3><p>Conferência e consulta de documentos dentro do processo ao qual pertencem.</p></div><span class="lp-feature-tag">ARQUIVOS</span></article>
          <article class="lp-feature-row"><span class="lp-feature-number">05</span><span class="lp-feature-icon"><svg class="lp-icon"><use href="#lp-chart"/></svg></span><div><h3>Relatórios para decidir</h3><p>Resultados, pendências e históricos construídos com os dados da operação.</p></div><span class="lp-feature-tag">ANÁLISE</span></article>
        </div>
      </div>
    </section>

    <section id="plataforma" class="lp-section lp-platform">
      <div class="lp-container lp-platform-inner">
        <div class="lp-platform-copy">
          <span class="lp-eyebrow">ORDENS DE SERVIÇO</span>
          <h2>Da agenda ao acerto com o prestador.</h2>
          <p>Uma associação pode agendar um serviço de trator, registrar as horas trabalhadas com fotos do horímetro e conferir os valores antes de concluir a ordem.</p>
          <a href="#contato" class="lp-text-link">Conversar sobre a implantação <svg class="lp-icon"><use href="#lp-arrow"/></svg></a>
        </div>
        <div class="lp-platform-window">
          <div class="lp-platform-window-body">
            <h3>Serviço de trator</h3>
            <div class="lp-window-rows">
              <div><span>Agenda</span><strong>Data, local e prestador</strong></div>
              <div><span>Execução</span><strong>Medições e fotos</strong></div>
              <div><span>Conferência</span><strong>Cobrança e remuneração</strong></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section id="beneficios" class="lp-section lp-process">
      <div class="lp-container">
        <div class="lp-section-heading"><span class="lp-eyebrow">UM FLUXO QUE FAZ SENTIDO</span><h2>Do primeiro registro<br>ao resultado compartilhado.</h2><p>O processo continua, mas a informação não precisa ser recomeçada a cada etapa.</p></div>
        <div class="lp-process-grid">
          <article><span>01 <svg class="lp-icon"><use href="#lp-arrow"/></svg></span><h3>Organize</h3><p>Cadastre pessoas, projetos, serviços e responsabilidades no contexto certo.</p></article>
          <article><span>02 <svg class="lp-icon"><use href="#lp-arrow"/></svg></span><h3>Execute</h3><p>Registre entregas, distribuições e movimentações enquanto acontecem.</p></article>
          <article><span>03 <svg class="lp-icon"><use href="#lp-arrow"/></svg></span><h3>Confira</h3><p>Revise pendências, vínculos, valores e comprovantes antes de concluir.</p></article>
          <article><span>04 <svg class="lp-icon"><use href="#lp-check"/></svg></span><h3>Acompanhe</h3><p>Consulte o histórico e gere relatórios com a origem dos dados preservada.</p></article>
        </div>
      </div>
    </section>

    <section id="seguranca" class="lp-section lp-assurance">
      <div class="lp-container lp-assurance-layout">
        <div><span class="lp-eyebrow">CONFIANÇA NO PROCESSO</span><h2>Controle e simplicidade podem andar juntos.</h2><p>Uma boa interface mantém o trabalho direto sem perder os cuidados necessários para operações responsáveis.</p><a href="#contato" class="lp-button lp-button-gold">Conversar sobre o SGC <svg class="lp-icon"><use href="#lp-arrow"/></svg></a></div>
        <div class="lp-assurance-list"><div><svg class="lp-icon"><use href="#lp-shield"/></svg><span><strong>Separação entre organizações</strong><small>Operações dentro do contexto e das permissões corretas.</small></span></div><div><svg class="lp-icon"><use href="#lp-archive"/></svg><span><strong>Histórico e rastreabilidade</strong><small>Informações relacionadas à origem dos registros.</small></span></div><div><svg class="lp-icon"><use href="#lp-check"/></svg><span><strong>Conferência antes de avançar</strong><small>Validações e etapas adequadas a cada fluxo.</small></span></div><div><svg class="lp-icon"><use href="#lp-lock"/></svg><span><strong>Acesso por responsabilidade</strong><small>Cada perfil visualiza o que precisa para trabalhar.</small></span></div></div>
      </div>
    </section>

    <section id="faq" class="lp-section lp-faq">
      <div class="lp-container lp-faq-grid">
        <div><span class="lp-eyebrow">PERGUNTAS FREQUENTES</span><h2>Antes de começar.</h2><p>O essencial sobre como a plataforma funciona e para quem foi pensada.</p><a class="lp-text-link" href="#contato">Ainda tem alguma dúvida? <svg class="lp-icon"><use href="#lp-arrow"/></svg></a></div>
        <div class="lp-faq-list">
          <details><summary>O SGC é somente para associações rurais?<svg class="lp-icon"><use href="#lp-chevron"/></svg></summary><p>Não. A plataforma pode atender cooperativas, associações e organizações que precisam coordenar pessoas, projetos, operações, documentos e finanças. Os módulos dependem do contexto de cada implantação.</p></details>
          <details><summary>É possível usar pelo celular?<svg class="lp-icon"><use href="#lp-chevron"/></svg></summary><p>Sim. A interface é responsiva e prioriza ações e informações operacionais no celular, sem perder a organização de uma tela grande.</p></details>
          <details><summary>Como funcionam os módulos?<svg class="lp-icon"><use href="#lp-chevron"/></svg></summary><p>Os módulos compartilham o contexto das informações para evitar controles isolados e manter a relação entre cadastros, entregas, documentos e valores.</p></details>
          <details><summary>Uma organização pode ter diferentes perfis?<svg class="lp-icon"><use href="#lp-chevron"/></svg></summary><p>Sim. O acesso pode ser configurado por responsabilidades e permissões, respeitando a separação de dados por organização.</p></details>
          <details><summary>É possível adaptar a implantação?<svg class="lp-icon"><use href="#lp-chevron"/></svg></summary><p>O conjunto de módulos e fluxos deve ser avaliado conforme a operação. Solicite uma demonstração para conversar sobre as necessidades da sua organização.</p></details>
        </div>
      </div>
    </section>

    <section id="contato" class="lp-section lp-contact">
      <div class="lp-container lp-contact-grid">
        <div class="lp-contact-copy">
          <h2>Conheça o SGC na sua operação.</h2>
          <p>Conte como sua organização acompanha entregas, serviços ou pagamentos para conversar sobre a implantação.</p>
        </div>
        <div>
          <a class="lp-button lp-button-primary" href="mailto:suporte@rzin.com.br?subject=Conhecer%20o%20SGC">Conversar por e-mail <svg class="lp-icon" aria-hidden="true"><use href="#lp-arrow"/></svg></a>
        </div>
      </div>
    </section>
  </main>

  <footer class="lp-footer">
    <div class="lp-container lp-footer-top"><div><a class="lp-logo" href="#top"><span class="lp-logo-mark" aria-hidden="true"><img src="{{ asset('assets/sgc-mark-light.png') }}" alt="" width="32" height="32"></span><span class="lp-logo-copy"><strong>SGC<span class="lp-logo-dot">.</span></strong><small>GESTÃO COOPERATIVA</small></span></a><p>O trabalho de muita gente, com a informação no lugar certo.</p></div><div class="lp-footer-links"><div><strong>Plataforma</strong><a href="#recursos">Recursos</a><a href="#plataforma">A plataforma</a><a href="#seguranca">Segurança</a><a href="#contato">Contato</a></div><div><strong>Informações legais</strong><a href="legal/index.html">Central Legal</a><a href="legal/privacidade.html">Privacidade</a><a href="legal/termos.html">Termos</a><a href="legal/cookies.html">Cookies</a></div></div></div>
    <div class="lp-container lp-footer-bottom"><span>© 2026 SGC — Sistema de Gestão Cooperativa.</span><div><a href="legal/direitos.html">Direitos LGPD</a><a href="legal/exclusao.html">Excluir conta/dados</a></div></div>
  </footer>
  </div>
</body>
</html>
