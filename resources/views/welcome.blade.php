<!doctype html>
<html lang="pt-BR" class="scroll-smooth">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#101923">
  <meta name="description" content="SGC — Sistema de Gestão Cooperativa. Gestão integrada para associações, cooperativas e organizações: pessoas, projetos, entregas, financeiro, documentos e relatórios em um só lugar.">
  <meta name="robots" content="index,follow">
  <meta property="og:type" content="website">
  <meta property="og:title" content="SGC — Sistema de Gestão Cooperativa">
  <meta property="og:description" content="Gestão que conecta pessoas, organiza processos e transforma cooperação em resultados.">
  <meta property="og:image" content="{{ asset('assets/og-sgc.webp') }}">
  <meta property="og:url" content="{{ url('/') }}">
  <meta property="og:locale" content="pt_BR">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="SGC — Sistema de Gestão Cooperativa">
  <meta name="twitter:description" content="Gestão integrada para organizações, projetos, pessoas e operações.">
  <meta name="twitter:image" content="{{ asset('assets/og-sgc.webp') }}">
  <title>SGC — Sistema de Gestão Cooperativa</title>
  <link rel="canonical" href="{{ url('/') }}">

  <link rel="icon" href="/assets/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon-180.png">
  <link rel="manifest" href="/manifest.json">
  <link rel="stylesheet" href="assets/styles.css">
  <link rel="stylesheet" href="assets/legal.css">
  <script src="/assets/pwa-entry.js" defer></script>
  <script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => 'SGC — Sistema de Gestão Cooperativa',
    'url' => url('/'),
    'applicationCategory' => 'BusinessApplication',
    'operatingSystem' => 'Web',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
</head>

<body class="bg-surface text-ink antialiased selection:bg-brand/20 selection:text-ink">
  <div id="pwa-launch-splash" class="pwa-launch-splash" hidden aria-live="polite" aria-label="Abrindo SGC">
    <div class="pwa-launch-splash__content">
      <img src="/assets/sgc-symbol.png" alt="SGC" class="pwa-launch-splash__logo">
      <strong>SGC</strong>
      <span>Preparando seu acesso seguro...</span>
      <i class="pwa-launch-splash__progress" aria-hidden="true"></i>
    </div>
  </div>
  <!-- SVG sprite -->
  <svg aria-hidden="true" class="absolute h-0 w-0 overflow-hidden">
    <defs>
      <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
      <symbol id="i-x" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></symbol>
      <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
      <symbol id="i-play" viewBox="0 0 24 24"><path d="M9 7l8 5-8 5V7z"/></symbol>
      <symbol id="i-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
      <symbol id="i-box" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16zM3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/></symbol>
      <symbol id="i-wallet" viewBox="0 0 24 24"><path d="M20 7V5a2 2 0 0 0-2-2H5a3 3 0 0 0 0 6h15v10a2 2 0 0 1-2 2H5a3 3 0 0 1-3-3V6M16 13h2"/></symbol>
      <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h8M8 9h2"/></symbol>
      <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18M7 16l4-5 4 3 5-7"/></symbol>
      <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10zM9 12l2 2 4-4"/></symbol>
      <symbol id="i-building" viewBox="0 0 24 24"><path d="M3 21h18M6 21V7l6-4 6 4v14M9 10h1M14 10h1M9 14h1M14 14h1M9 18h1M14 18h1"/></symbol>
      <symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></symbol>
      <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></symbol>
      <symbol id="i-layers" viewBox="0 0 24 24"><path d="M12 2l9 5-9 5-9-5 9-5zM3 12l9 5 9-5M3 17l9 5 9-5"/></symbol>
      <symbol id="i-zap" viewBox="0 0 24 24"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></symbol>
      <symbol id="i-circle-check" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/></symbol>
      <symbol id="i-phone" viewBox="0 0 24 24"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></symbol>
      <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></symbol>
      <symbol id="i-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></symbol>
    </defs>
  </svg>

  <!-- Header -->
  <header id="top" class="fixed inset-x-0 top-0 z-50 border-b border-white/5 bg-hero/80 backdrop-blur-xl">
    <div class="mx-auto flex h-18 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
      <a href="#inicio" class="group flex items-center gap-3" aria-label="SGC - início">
        <span class="grid h-10 w-10 place-items-center rounded-xl bg-white shadow-brand-soft ring-1 ring-white/10 transition group-hover:-translate-y-0.5">
          <img src="assets/sgc-symbol.png" alt="" class="h-8 w-8 object-contain">
        </span>
        <span class="leading-none">
          <strong class="block text-xl tracking-[0.08em] text-white">SGC</strong>
          <span class="mt-1 hidden text-[10px] font-semibold uppercase tracking-[0.18em] text-brand-light sm:block">Sistema de Gestão Cooperativa</span>
        </span>
      </a>

      <nav class="hidden items-center gap-7 lg:flex" aria-label="Navegação principal">
        <a class="nav-link" href="#recursos">Recursos</a>
        <a class="nav-link" href="#plataforma">Plataforma</a>
        <a class="nav-link" href="#beneficios">Benefícios</a>
        <a class="nav-link" href="#seguranca">Segurança</a>
        <a class="nav-link" href="#faq">FAQ</a>
      </nav>

      <div class="hidden items-center gap-3 lg:flex">
        <a href="#plataforma" class="btn-ghost">Ver plataforma</a>
        <a href="#contato" class="btn-primary">Solicitar demonstração</a>
      </div>

      <div class="flex items-center gap-2">
        <button type="button" class="btn-ghost" data-pwa-install hidden>
          Instalar app
        </button>
        <a href="{{ route('login') }}" class="inline-flex h-11 items-center gap-2 rounded-xl bg-brand px-4 text-sm font-bold text-white shadow-brand-soft transition hover:bg-[#1DA668] focus:outline-none focus:ring-4 focus:ring-brand/30" aria-label="Entrar na plataforma SGC">
          <svg class="icon h-5 w-5" aria-hidden="true"><use href="#i-lock"/></svg>
          <span>Entrar</span>
        </a>
        <button id="menuButton" class="grid h-11 w-11 place-items-center rounded-xl border border-white/10 bg-white/5 text-white lg:hidden" aria-label="Abrir menu" aria-expanded="false">
          <svg class="icon h-5 w-5"><use href="#i-menu"/></svg>
        </button>
      </div>
    </div>

    <div id="mobileMenu" class="hidden border-t border-white/5 bg-hero/95 px-4 pb-5 pt-3 backdrop-blur-xl lg:hidden">
      <nav class="mx-auto grid max-w-7xl gap-1">
        <a class="mobile-link" href="#recursos">Recursos</a>
        <a class="mobile-link" href="#plataforma">Plataforma</a>
        <a class="mobile-link" href="#beneficios">Benefícios</a>
        <a class="mobile-link" href="#seguranca">Segurança</a>
        <a class="mobile-link" href="#faq">Perguntas frequentes</a>
        <a class="mobile-link" href="{{ route('login') }}">Acessar plataforma</a>
        <a class="mt-2 inline-flex items-center justify-center rounded-xl bg-brand px-5 py-3.5 font-bold text-white" href="#contato">Solicitar demonstração</a>
      </nav>
    </div>
  </header>

  <main>
    <!-- Hero -->
    <section id="inicio" class="relative overflow-hidden bg-hero pt-28 text-white sm:pt-32 lg:pt-36">
      <div class="hero-glow hero-glow-green"></div>
      <div class="hero-glow hero-glow-purple"></div>
      <div class="hero-grid"></div>

      <div class="relative mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8 lg:pb-28">
        <div class="grid items-center gap-12 lg:grid-cols-[0.88fr_1.12fr] lg:gap-14">
          <div class="reveal">
            <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-brand/20 bg-brand/10 px-3.5 py-2 text-sm font-semibold text-brand-light">
              <span class="h-2 w-2 rounded-full bg-brand shadow-[0_0_18px_rgba(34,181,115,.8)]"></span>
              Gestão inteligente para quem coopera
            </div>

            <h1 class="max-w-3xl text-4xl font-black leading-[1.02] tracking-[-0.04em] sm:text-5xl lg:text-6xl xl:text-7xl">
              Gestão que conecta
              <span class="text-brand-light">pessoas</span>,
              organiza <span class="text-purple-light">processos</span>
              e gera <span class="text-orange">resultados.</span>
            </h1>

            <p class="mt-6 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
              O SGC centraliza pessoas, projetos, entregas, financeiro, documentos e indicadores em uma experiência simples, organizada e preparada para diferentes tipos de associações, cooperativas e organizações.
            </p>

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
              <a href="#contato" class="btn-primary btn-large">
                Solicitar demonstração
                <svg class="icon h-5 w-5"><use href="#i-arrow"/></svg>
              </a>
              <a href="#plataforma" class="btn-secondary btn-large">
                <svg class="icon h-5 w-5"><use href="#i-play"/></svg>
                Conhecer a plataforma
              </a>
              <a href="{{ route('login') }}" class="btn-secondary btn-large">
                <svg class="icon h-5 w-5"><use href="#i-lock"/></svg>
                Entrar na plataforma
              </a>
            </div>

            <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-300">
              <span class="inline-flex items-center gap-2"><svg class="icon h-4 w-4 text-brand-light"><use href="#i-circle-check"/></svg> Interface responsiva</span>
              <span class="inline-flex items-center gap-2"><svg class="icon h-4 w-4 text-orange"><use href="#i-circle-check"/></svg> Fluxos auditáveis</span>
              <span class="inline-flex items-center gap-2"><svg class="icon h-4 w-4 text-purple-light"><use href="#i-circle-check"/></svg> Gestão integrada</span>
            </div>
          </div>

          <div class="relative reveal lg:pl-3">
            <div class="absolute -inset-8 rounded-[3rem] bg-gradient-to-tr from-brand/15 via-purple/10 to-orange/10 blur-3xl"></div>
            <div class="relative rounded-[2rem] border border-white/10 bg-white/[0.055] p-2.5 shadow-2xl backdrop-blur-sm sm:p-3.5">
              <div class="overflow-hidden rounded-[1.45rem] border border-white/10 bg-slate-900 shadow-2xl">
                <div class="flex h-9 items-center gap-1.5 border-b border-white/10 bg-slate-950/80 px-4">
                  <span class="h-2.5 w-2.5 rounded-full bg-orange"></span>
                  <span class="h-2.5 w-2.5 rounded-full bg-purple"></span>
                  <span class="h-2.5 w-2.5 rounded-full bg-brand"></span>
                  <span class="ml-auto text-[10px] font-medium text-slate-500">SGC / workspace</span>
                </div>
                <img src="assets/sgc-desktop.webp" alt="Interface do SGC em tela ampla" class="block w-full object-cover" loading="eager">
              </div>
            </div>

            <div class="absolute -bottom-12 right-2 hidden w-[24%] min-w-[150px] overflow-hidden rounded-[1.8rem] border-[6px] border-slate-950 bg-white shadow-2xl sm:block lg:-right-3">
              <img src="assets/sgc-mobile.webp" alt="Interface mobile do SGC" class="aspect-[9/19.5] w-full object-cover object-top">
            </div>

            <div class="absolute -left-5 -top-5 hidden items-center gap-3 rounded-2xl border border-white/10 bg-slate-900/90 px-4 py-3 shadow-2xl backdrop-blur-md xl:flex">
              <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand/15 text-brand-light">
                <svg class="icon h-5 w-5"><use href="#i-layers"/></svg>
              </span>
              <div>
                <strong class="block text-sm text-white">Tudo conectado</strong>
                <span class="text-xs text-slate-400">Uma única base de gestão</span>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-20 grid gap-3 sm:grid-cols-3 lg:mt-24">
          <div class="hero-value reveal">
            <span class="value-icon bg-brand/15 text-brand-light"><svg class="icon h-5 w-5"><use href="#i-users"/></svg></span>
            <div><strong>Cooperação</strong><span>Pessoas e responsabilidades conectadas.</span></div>
          </div>
          <div class="hero-value reveal">
            <span class="value-icon bg-purple/15 text-purple-light"><svg class="icon h-5 w-5"><use href="#i-chart"/></svg></span>
            <div><strong>Gestão</strong><span>Informações organizadas para decidir melhor.</span></div>
          </div>
          <div class="hero-value reveal">
            <span class="value-icon bg-orange/15 text-orange"><svg class="icon h-5 w-5"><use href="#i-shield"/></svg></span>
            <div><strong>Confiança</strong><span>Rastreabilidade, clareza e controle.</span></div>
          </div>
        </div>
      </div>

      <div class="hero-wave" aria-hidden="true"></div>
    </section>

    <!-- Intro -->
    <section class="relative -mt-1 bg-surface py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl text-center reveal">
          <span class="eyebrow">Uma plataforma, vários contextos</span>
          <h2 class="section-title mt-4">O SGC não depende de um único tipo de organização.</h2>
          <p class="section-copy mx-auto mt-5">
            A estrutura foi pensada para adaptar processos de gestão a realidades diferentes — de associações comunitárias e cooperativas a organizações que trabalham com projetos, pessoas, repasses, entregas e prestação de contas.
          </p>
        </div>

        <div class="mt-12 grid gap-4 md:grid-cols-3">
          <article class="soft-card reveal">
            <span class="feature-icon feature-green"><svg class="icon h-6 w-6"><use href="#i-building"/></svg></span>
            <h3>Organizações e pessoas</h3>
            <p>Cadastros, papéis, vínculos, permissões e histórico em uma estrutura clara para cada organização.</p>
          </article>
          <article class="soft-card reveal">
            <span class="feature-icon feature-purple"><svg class="icon h-6 w-6"><use href="#i-layers"/></svg></span>
            <h3>Processos conectados</h3>
            <p>Projetos, documentos, entregas, pagamentos e informações operacionais sem depender de controles isolados.</p>
          </article>
          <article class="soft-card reveal">
            <span class="feature-icon feature-orange"><svg class="icon h-6 w-6"><use href="#i-chart"/></svg></span>
            <h3>Decisões com contexto</h3>
            <p>Indicadores e relatórios derivados do mesmo fluxo operacional para reduzir divergências e retrabalho.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Recursos -->
    <section id="recursos" class="border-y border-slate-200/80 bg-white py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[0.72fr_1.28fr] lg:items-end">
          <div class="reveal">
            <span class="eyebrow">Recursos integrados</span>
            <h2 class="section-title mt-4">Menos ilhas de informação. Mais continuidade.</h2>
          </div>
          <p class="section-copy reveal lg:pb-1">
            Cada módulo conversa com o restante do sistema. A ideia é simples: registrar uma informação uma vez, preservar o contexto e utilizá-la em toda a cadeia de gestão.
          </p>
        </div>

        <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <article class="module-card reveal">
            <span class="feature-icon feature-green"><svg class="icon h-6 w-6"><use href="#i-users"/></svg></span>
            <h3>Associados e pessoas</h3>
            <p>Cadastros, perfis, vínculos e participação organizados em um histórico consistente.</p>
            <span class="module-tag">Pessoas</span>
          </article>

          <article class="module-card reveal">
            <span class="feature-icon feature-purple"><svg class="icon h-6 w-6"><use href="#i-box"/></svg></span>
            <h3>Projetos e operações</h3>
            <p>Planejamento, limites, destinos, entregas, distribuições e acompanhamento em um único fluxo.</p>
            <span class="module-tag module-tag-purple">Operação</span>
          </article>

          <article class="module-card reveal">
            <span class="feature-icon feature-orange"><svg class="icon h-6 w-6"><use href="#i-wallet"/></svg></span>
            <h3>Financeiro</h3>
            <p>Recebimentos, pagamentos, taxas, saldos e rastreabilidade financeira associados à origem dos fatos.</p>
            <span class="module-tag module-tag-orange">Financeiro</span>
          </article>

          <article class="module-card reveal">
            <span class="feature-icon feature-blue"><svg class="icon h-6 w-6"><use href="#i-file"/></svg></span>
            <h3>Documentos e comprovantes</h3>
            <p>Documentação ligada ao processo correto, facilitando conferência, prestação de contas e consulta.</p>
            <span class="module-tag module-tag-blue">Documentos</span>
          </article>

          <article class="module-card reveal">
            <span class="feature-icon feature-green"><svg class="icon h-6 w-6"><use href="#i-chart"/></svg></span>
            <h3>Relatórios e indicadores</h3>
            <p>Visões resumidas e detalhadas para acompanhar resultados sem reconstruir dados manualmente.</p>
            <span class="module-tag">Análise</span>
          </article>

          <article class="module-card reveal">
            <span class="feature-icon feature-purple"><svg class="icon h-6 w-6"><use href="#i-shield"/></svg></span>
            <h3>Permissões e multi-organização</h3>
            <p>Controle de acesso por contexto, responsabilidades e organização para manter cada operação isolada.</p>
            <span class="module-tag module-tag-purple">Controle</span>
          </article>
        </div>
      </div>
    </section>

    <!-- Plataforma / screenshots -->
    <section id="plataforma" class="relative overflow-hidden bg-slate-50 py-20 sm:py-24">
      <div class="absolute -left-32 top-20 h-72 w-72 rounded-full bg-brand/10 blur-3xl"></div>
      <div class="absolute -right-32 bottom-12 h-72 w-72 rounded-full bg-purple/10 blur-3xl"></div>

      <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl text-center reveal">
          <span class="eyebrow">Desktop e mobile</span>
          <h2 class="section-title mt-4">A mesma lógica de gestão em qualquer tela.</h2>
          <p class="section-copy mx-auto mt-5">
            O SGC prioriza leitura rápida, navegação previsível e ações claras. No celular, a interface se comporta como um aplicativo; em telas maiores, aproveita o espaço sem transformar a gestão em uma planilha gigante.
          </p>
        </div>

        <div class="mt-14 grid items-center gap-8 lg:grid-cols-[1fr_0.34fr]">
          <div class="product-frame reveal">
            <div class="product-browserbar">
              <span class="bg-[#ff9e73]"></span><span class="bg-purple"></span><span class="bg-brand"></span>
              <div class="ml-3 h-6 flex-1 rounded-lg bg-slate-100"></div>
            </div>
            <img src="assets/sgc-desktop.webp" alt="Tela do SGC exibindo o projeto PNAE em layout amplo" class="w-full">
          </div>

          <div class="mx-auto w-full max-w-[280px] reveal">
            <div class="phone-frame">
              <div class="phone-speaker"></div>
              <img src="assets/sgc-mobile.webp" alt="Tela do SGC em um smartphone" class="h-full w-full object-cover object-top">
            </div>
          </div>
        </div>

        <div class="mt-12 grid gap-4 md:grid-cols-3">
          <div class="mini-benefit reveal"><svg class="icon h-5 w-5 text-brand"><use href="#i-zap"/></svg><span><strong>Menos atrito</strong> para registrar e consultar.</span></div>
          <div class="mini-benefit reveal"><svg class="icon h-5 w-5 text-purple"><use href="#i-phone"/></svg><span><strong>Mobile-first</strong> onde a operação acontece.</span></div>
          <div class="mini-benefit reveal"><svg class="icon h-5 w-5 text-orange-dark"><use href="#i-layers"/></svg><span><strong>Contexto preservado</strong> entre módulos.</span></div>
        </div>
      </div>
    </section>

    <!-- How it works -->
    <section id="beneficios" class="bg-white py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-[0.8fr_1.2fr] lg:items-center">
          <div class="reveal">
            <span class="eyebrow">Uma lógica simples</span>
            <h2 class="section-title mt-4">Do registro ao resultado, sem perder a origem.</h2>
            <p class="section-copy mt-5">
              O SGC foi desenhado para que a informação avance pelo fluxo mantendo sua relação com pessoas, projetos, documentos e movimentações.
            </p>

            <div class="mt-8 flex flex-wrap gap-2.5">
              <span class="pill pill-green">Registrar</span>
              <span class="pill pill-purple">Organizar</span>
              <span class="pill pill-orange">Executar</span>
              <span class="pill pill-green">Conferir</span>
              <span class="pill pill-purple">Analisar</span>
            </div>
          </div>

          <div class="relative">
            <div class="flow-line hidden lg:block"></div>

            <div class="space-y-4">
              <article class="flow-card reveal">
                <span class="flow-number bg-brand-soft text-brand-dark">01</span>
                <div><h3>Cadastre o contexto certo</h3><p>Pessoas, organizações, produtos, clientes, projetos ou outros elementos necessários ao processo.</p></div>
              </article>
              <article class="flow-card reveal">
                <span class="flow-number bg-purple-soft text-purple-dark">02</span>
                <div><h3>Execute o fluxo operacional</h3><p>Registre atividades no ponto em que acontecem, com regras claras e validações antes de avançar.</p></div>
              </article>
              <article class="flow-card reveal">
                <span class="flow-number bg-orange-soft text-orange-dark">03</span>
                <div><h3>Consolide financeiro e documentos</h3><p>Movimentações e comprovantes permanecem ligados àquilo que realmente originou o valor ou obrigação.</p></div>
              </article>
              <article class="flow-card reveal">
                <span class="flow-number bg-slate-100 text-slate-700">04</span>
                <div><h3>Transforme dados em acompanhamento</h3><p>Indicadores, pendências e relatórios ajudam a enxergar o que já aconteceu e o que ainda precisa de atenção.</p></div>
              </article>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Cooperation meaning -->
    <section class="overflow-hidden bg-hero py-20 text-white sm:py-24">
      <div class="mx-auto grid max-w-7xl gap-12 px-4 sm:px-6 lg:grid-cols-[0.78fr_1.22fr] lg:items-center lg:px-8">
        <div class="reveal">
          <div class="relative mx-auto aspect-square max-w-[390px]">
            <div class="absolute inset-0 rounded-full bg-gradient-to-br from-brand/20 via-purple/15 to-orange/15 blur-3xl"></div>
            <div class="absolute inset-[10%] rounded-full border border-white/8"></div>
            <div class="absolute inset-[22%] rounded-full border border-white/8"></div>
            <img src="assets/sgc-symbol.png" alt="Símbolo do SGC representando pessoas conectadas em cooperação" class="absolute inset-[11%] h-[78%] w-[78%] object-contain drop-shadow-2xl">
          </div>
        </div>

        <div class="reveal">
          <span class="eyebrow eyebrow-dark">O significado da marca</span>
          <h2 class="mt-4 text-3xl font-black tracking-[-0.035em] sm:text-4xl lg:text-5xl">Cooperação mútua no centro da gestão.</h2>
          <p class="mt-5 max-w-2xl text-base leading-7 text-slate-300 sm:text-lg">
            O símbolo aproxima três pessoas em um ciclo contínuo: diferentes responsabilidades, uma mesma estrutura e um resultado compartilhado. As cores deixam de representar um setor específico e passam a representar dimensões complementares da gestão.
          </p>

          <div class="mt-9 grid gap-3 sm:grid-cols-3">
            <div class="meaning-card">
              <span class="h-3 w-3 rounded-full bg-brand"></span>
              <strong>Verde</strong>
              <p>Pessoas, continuidade e cooperação.</p>
            </div>
            <div class="meaning-card">
              <span class="h-3 w-3 rounded-full bg-purple"></span>
              <strong>Roxo</strong>
              <p>Organização, inteligência e gestão.</p>
            </div>
            <div class="meaning-card">
              <span class="h-3 w-3 rounded-full bg-orange"></span>
              <strong>Laranja</strong>
              <p>Ação, entrega e resultado.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Security -->
    <section id="seguranca" class="bg-surface py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
          <div class="reveal">
            <span class="eyebrow">Segurança e consistência</span>
            <h2 class="section-title mt-4">Controle não precisa significar burocracia.</h2>
            <p class="section-copy mt-5">
              O sistema pode aplicar regras e proteções em segundo plano enquanto a interface mantém o foco no que o usuário precisa fazer.
            </p>

            <div class="mt-8 space-y-4">
              <div class="check-row"><span><svg class="icon h-5 w-5"><use href="#i-check"/></svg></span><div><strong>Separação por organização</strong><p>Dados e permissões permanecem no contexto correto.</p></div></div>
              <div class="check-row"><span><svg class="icon h-5 w-5"><use href="#i-check"/></svg></span><div><strong>Rastreabilidade de operações</strong><p>Histórico e vínculos ajudam a compreender a origem das movimentações.</p></div></div>
              <div class="check-row"><span><svg class="icon h-5 w-5"><use href="#i-check"/></svg></span><div><strong>Validações antes de concluir</strong><p>O sistema reduz inconsistências antes que elas se espalhem para outros módulos.</p></div></div>
              <div class="check-row"><span><svg class="icon h-5 w-5"><use href="#i-check"/></svg></span><div><strong>Permissões por responsabilidade</strong><p>Cada perfil acessa aquilo que realmente precisa para trabalhar.</p></div></div>
            </div>
          </div>

          <div class="reveal">
            <div class="security-panel">
              <div class="security-orb">
                <svg class="icon h-12 w-12"><use href="#i-lock"/></svg>
              </div>
              <div class="mt-7 grid grid-cols-2 gap-3">
                <div class="security-stat"><span class="security-dot bg-brand"></span><strong>Contexto</strong><small>Organização correta</small></div>
                <div class="security-stat"><span class="security-dot bg-purple"></span><strong>Permissão</strong><small>Acesso adequado</small></div>
                <div class="security-stat"><span class="security-dot bg-orange"></span><strong>Histórico</strong><small>Origem preservada</small></div>
                <div class="security-stat"><span class="security-dot bg-slate-400"></span><strong>Validação</strong><small>Fluxo consistente</small></div>
              </div>
              <div class="mt-5 rounded-2xl border border-white/8 bg-white/5 p-4">
                <div class="flex items-center gap-3">
                  <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand/15 text-brand-light"><svg class="icon h-5 w-5"><use href="#i-shield"/></svg></div>
                  <div>
                    <strong class="text-sm text-white">Fundação confiável</strong>
                    <p class="mt-0.5 text-xs leading-5 text-slate-400">Segurança e consistência como parte do fluxo, não como etapa separada.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Splash / brand -->
    <section class="border-y border-slate-200/80 bg-white py-20 sm:py-24">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[1fr_0.66fr] lg:items-center">
          <div class="reveal">
            <span class="eyebrow">Experiência consistente</span>
            <h2 class="section-title mt-4">Da identidade visual ao fluxo de trabalho.</h2>
            <p class="section-copy mt-5">
              A marca acompanha a mesma linguagem da interface: verde, roxo e laranja em tons suaves, tipografia limpa, espaços amplos e foco em hierarquia visual.
            </p>
            <div class="mt-8 grid gap-3 sm:grid-cols-2">
              <div class="brand-detail"><span class="bg-brand-soft text-brand-dark"><svg class="icon h-5 w-5"><use href="#i-users"/></svg></span><div><strong>Humana</strong><p>Fácil de reconhecer e de usar.</p></div></div>
              <div class="brand-detail"><span class="bg-purple-soft text-purple-dark"><svg class="icon h-5 w-5"><use href="#i-layers"/></svg></span><div><strong>Modular</strong><p>Cresce sem perder coerência.</p></div></div>
              <div class="brand-detail"><span class="bg-orange-soft text-orange-dark"><svg class="icon h-5 w-5"><use href="#i-zap"/></svg></span><div><strong>Direta</strong><p>Ações importantes em primeiro plano.</p></div></div>
              <div class="brand-detail"><span class="bg-slate-100 text-slate-700"><svg class="icon h-5 w-5"><use href="#i-shield"/></svg></span><div><strong>Confiável</strong><p>Visual consistente em cada contexto.</p></div></div>
            </div>
          </div>

          <div class="mx-auto w-full max-w-[340px] reveal">
            <div class="splash-preview">
              <img src="assets/splash-dark.webp" alt="Splash screen escura do aplicativo SGC" class="h-full w-full object-cover">
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="bg-slate-50 py-20 sm:py-24">
      <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center reveal">
          <span class="eyebrow">Perguntas frequentes</span>
          <h2 class="section-title mt-4">O essencial antes de conhecer o SGC.</h2>
        </div>

        <div class="mt-10 space-y-3">
          <details class="faq-item reveal">
            <summary>O SGC é somente para associações rurais?<svg class="icon h-5 w-5"><use href="#i-chevron"/></svg></summary>
            <div class="faq-answer">Não. O conceito é mais amplo: o sistema pode atender associações, cooperativas e organizações que precisem coordenar pessoas, projetos, operações, documentos, movimentações financeiras e relatórios. Os módulos podem ser adaptados ao contexto de cada implantação.</div>
          </details>

          <details class="faq-item reveal">
            <summary>O sistema funciona bem no celular?<svg class="icon h-5 w-5"><use href="#i-chevron"/></svg></summary>
            <div class="faq-answer">Sim. A interface foi desenhada com prioridade para uso mobile, mantendo navegação rápida e ações compactas. Em telas maiores, o layout se expande e aproveita melhor o espaço disponível.</div>
          </details>

          <details class="faq-item reveal">
            <summary>Os módulos trabalham separados?<svg class="icon h-5 w-5"><use href="#i-chevron"/></svg></summary>
            <div class="faq-answer">A proposta é justamente o contrário. O SGC procura manter os módulos conectados para preservar a origem e o contexto dos dados, evitando cadastros duplicados, relatórios divergentes e controles paralelos.</div>
          </details>

          <details class="faq-item reveal">
            <summary>É possível controlar diferentes organizações?<svg class="icon h-5 w-5"><use href="#i-chevron"/></svg></summary>
            <div class="faq-answer">A arquitetura pode trabalhar com separação por organização, permitindo aplicar permissões, fluxos e dados dentro do contexto correto para cada ambiente.</div>
          </details>

          <details class="faq-item reveal">
            <summary>É possível adaptar módulos e regras?<svg class="icon h-5 w-5"><use href="#i-chevron"/></svg></summary>
            <div class="faq-answer">Sim. A página apresenta a visão geral do produto. Em uma implantação real, módulos, regras, relatórios e fluxos podem ser configurados de acordo com a operação que será atendida.</div>
          </details>
        </div>
      </div>
    </section>

    <!-- Contact -->
    <section id="contato" class="relative overflow-hidden bg-hero py-20 text-white sm:py-24">
      <div class="absolute -left-24 bottom-0 h-72 w-72 rounded-full bg-brand/15 blur-3xl"></div>
      <div class="absolute -right-24 top-0 h-72 w-72 rounded-full bg-purple/15 blur-3xl"></div>

      <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="cta-panel reveal">
          <div class="max-w-2xl">
            <span class="eyebrow eyebrow-dark">Conheça o SGC</span>
            <h2 class="mt-4 text-3xl font-black tracking-[-0.035em] sm:text-4xl lg:text-5xl">Veja como o SGC pode organizar o seu fluxo de gestão.</h2>
            <p class="mt-5 max-w-xl text-base leading-7 text-slate-300">
              Envie seus dados para iniciar uma conversa sobre o contexto da sua organização e os módulos mais importantes para a sua operação.
            </p>
          </div>

          <form id="demoForm" class="mt-10 grid gap-4 rounded-[1.5rem] border border-white/10 bg-white/[0.055] p-5 backdrop-blur sm:grid-cols-2 sm:p-6">
            <label class="field">
              <span>Nome</span>
              <input required name="name" type="text" placeholder="Seu nome">
            </label>
            <label class="field">
              <span>Organização</span>
              <input name="organization" type="text" placeholder="Nome da organização">
            </label>
            <label class="field">
              <span>E-mail</span>
              <input required name="email" type="email" placeholder="voce@exemplo.com">
            </label>
            <label class="field">
              <span>Telefone</span>
              <input name="phone" type="tel" placeholder="(00) 00000-0000">
            </label>
            <label class="field sm:col-span-2">
              <span>O que você quer organizar com o SGC?</span>
              <textarea required name="message" rows="4" placeholder="Conte brevemente sobre seu fluxo, equipe ou projeto."></textarea>
            </label>
            <div class="flex flex-col gap-3 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between">
              <p class="text-xs leading-5 text-slate-400">Ao enviar, você concorda que os dados informados sejam usados para responder ao seu contato, conforme a <a href="legal/privacidade.html" class="font-bold text-brand-light underline underline-offset-2">Política de Privacidade</a>.</p>
              <button type="submit" class="btn-primary shrink-0">Enviar mensagem <svg class="icon h-5 w-5"><use href="#i-arrow"/></svg></button>
            </div>
          </form>
        </div>
      </div>
    </section>
  </main>

  <footer class="bg-[#0b121a] py-10 text-slate-400">
    <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 sm:px-6 lg:flex-row lg:items-end lg:justify-between lg:px-8">
      <div>
        <a href="#top" class="inline-flex items-center gap-3">
          <span class="grid h-11 w-11 place-items-center rounded-xl bg-white">
            <img src="assets/sgc-symbol.png" alt="" class="h-9 w-9 object-contain">
          </span>
          <span>
            <strong class="block text-2xl tracking-[0.08em] text-white">SGC</strong>
            <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-brand-light">Sistema de Gestão Cooperativa</span>
          </span>
        </a>
        <p class="mt-4 max-w-md text-sm leading-6">Gestão inteligente. Pessoas conectadas. Resultados compartilhados.</p>
      </div>

      <div class="grid gap-5 text-sm sm:grid-cols-2">
        <div>
          <strong class="mb-2 block text-xs uppercase tracking-[.14em] text-slate-500">Plataforma</strong>
          <div class="flex flex-wrap gap-x-5 gap-y-2">
            <a class="footer-link" href="#recursos">Recursos</a>
            <a class="footer-link" href="#plataforma">Plataforma</a>
            <a class="footer-link" href="#seguranca">Segurança</a>
            <a class="footer-link" href="#contato">Contato</a>
          </div>
        </div>
        <div>
          <strong class="mb-2 block text-xs uppercase tracking-[.14em] text-slate-500">Legal e privacidade</strong>
          <div class="flex flex-wrap gap-x-5 gap-y-2">
            <a class="footer-link" href="legal/index.html">Central Legal</a>
            <a class="footer-link" href="legal/privacidade.html">Privacidade</a>
            <a class="footer-link" href="legal/termos.html">Termos</a>
            <a class="footer-link" href="legal/cookies.html">Cookies</a>
            <button type="button" data-open-cookie-preferences class="footer-link">Preferências</button>
          </div>
        </div>
      </div>
    </div>

    <div class="mx-auto mt-8 max-w-7xl border-t border-white/5 px-4 pt-6 text-xs sm:px-6 lg:px-8">
      © <span id="year"></span> SGC — Sistema de Gestão Cooperativa.
      <span class="mx-2 text-slate-700">•</span>
      <a href="legal/direitos.html" class="footer-link">Direitos LGPD</a>
      <span class="mx-2 text-slate-700">•</span>
      <a href="legal/exclusao.html" class="footer-link">Excluir conta/dados</a>
    </div>
  </footer>

  <div id="toast" class="pointer-events-none fixed bottom-5 left-1/2 z-[80] hidden -translate-x-1/2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-800 shadow-2xl">
    Mensagem recebida. Em breve entraremos em contato.
  </div>


  <div id="cookieBanner" class="cookie-banner hidden" role="region" aria-label="Preferências de cookies">
    <div class="cookie-banner-inner">
      <div>
        <strong class="text-sm font-black">Privacidade primeiro.</strong>
        <p>Usamos armazenamento estritamente necessário para lembrar suas preferências. Categorias opcionais ficam desativadas até você autorizar. <a href="legal/cookies.html">Política de Cookies</a>.</p>
      </div>
      <div class="cookie-actions">
        <button class="cookie-btn" type="button" data-cookie-reject-optional>Recusar opcionais</button>
        <button class="cookie-btn" type="button" data-open-cookie-preferences>Preferências</button>
        <button class="cookie-btn cookie-btn-primary" type="button" data-cookie-accept-all>Aceitar opcionais</button>
      </div>
    </div>
  </div>

  <div id="cookieOverlay" class="cookie-overlay hidden" aria-hidden="true"></div>
  <section id="cookiePreferences" class="cookie-modal hidden" role="dialog" aria-modal="true" aria-labelledby="cookieTitle">
    <div class="cookie-modal-header">
      <div>
        <strong id="cookieTitle" class="block text-base font-black text-slate-900">Preferências de privacidade</strong>
        <span class="mt-1 block text-xs text-slate-500">Você pode alterar esta escolha a qualquer momento.</span>
      </div>
      <button type="button" data-cookie-close class="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-50" aria-label="Fechar">×</button>
    </div>
    <div class="p-5 sm:p-6">
      <div class="cookie-category">
        <div>
          <h4>Estritamente necessários</h4>
          <p>Essenciais para segurança, sessão, preferências de consentimento e funcionamento solicitado pelo usuário. Não podem ser desligados pelo painel.</p>
        </div>
        <label class="cookie-switch cookie-switch-fixed" aria-label="Cookies necessários sempre ativos">
          <input type="checkbox" checked disabled><span></span>
        </label>
      </div>

      <div class="cookie-category">
        <div>
          <h4>Preferências</h4>
          <p>Lembram opções que melhoram sua experiência, sem serem indispensáveis ao serviço principal.</p>
        </div>
        <label class="cookie-switch">
          <input type="checkbox" data-consent-toggle="preferences"><span></span>
        </label>
      </div>

      <div class="cookie-category">
        <div>
          <h4>Análise</h4>
          <p>Permitem medir uso e desempenho. Esta categoria permanece desativada até que seja adotada e informada nesta Política.</p>
        </div>
        <label class="cookie-switch">
          <input type="checkbox" data-consent-toggle="analytics"><span></span>
        </label>
      </div>

      <div class="cookie-category">
        <div>
          <h4>Marketing</h4>
          <p>Podem ser usados para publicidade, mensuração de campanhas ou personalização comercial. Não são ativados por padrão.</p>
        </div>
        <label class="cookie-switch">
          <input type="checkbox" data-consent-toggle="marketing"><span></span>
        </label>
      </div>

      <div class="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-end">
        <button type="button" class="cookie-btn !border-slate-200 !bg-white !text-slate-700" data-cookie-reject-optional>Recusar opcionais</button>
        <button type="button" class="cookie-btn cookie-btn-primary" data-cookie-save>Salvar preferências</button>
      </div>
    </div>
  </section>

  <script src="assets/legal-config.js"></script>
  <script src="/assets/pwa-install.js" defer></script>
  <script src="assets/app.js"></script>
  <script src="assets/privacy.js"></script>
</body>
</html>
