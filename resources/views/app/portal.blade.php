<!doctype html>
<html lang="pt-BR" data-theme="light" data-app-entry>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#173c2e">
    <meta name="robots" content="noindex,follow">
    <title>Entrar no SGC</title>
    <link rel="manifest" href="/manifest.json?v=campo">
    <link rel="icon" href="/assets/favicon.ico?v=campo-light">
    <script src="{{ asset('assets/pwa-entry.js') }}?v=3"></script>
    <link rel="stylesheet" href="{{ asset('assets/sgc-app-portal.css') }}?v=4">
</head>
<body>
<main class="entry-page">
    <div class="entry-shell">
        <header class="entry-brand">
            <a href="{{ route('home', ['apresentacao' => 1]) }}" aria-label="Conhecer o SGC">
                <span class="entry-mark">
                    <img class="entry-mark-light" src="{{ asset('assets/sgc-mark-light.png') }}" width="38" height="38" alt="">
                    <img class="entry-mark-dark" src="{{ asset('assets/sgc-mark-dark.png') }}" width="38" height="38" alt="">
                </span>
                <span><strong>SGC</strong><small>Sistema de Gestão Cooperativa</small></span>
            </a>
        </header>

        <section class="entry-card" aria-labelledby="entry-title">
            <div class="entry-hero">
                <h1 id="entry-title">Acesse sua<br>organização.</h1>
                <p>Projetos, entregas e serviços da sua organização.</p>
            </div>
            <div class="entry-actions">
                <a class="entry-login" href="{{ route('login') }}">Entrar no SGC <span aria-hidden="true">→</span></a>
                <a class="entry-code" href="{{ route('access.invitation.verify') }}">Tenho um código de acesso</a>
                <nav class="entry-links" aria-label="Acesso e demonstração">
                    <a href="mailto:{{ config('legal.contact_email') }}?subject=Solicitar%20acesso%20ao%20SGC">Solicitar acesso</a>
                    <a href="{{ route('home', ['apresentacao' => 1]) }}#contato">Solicitar demonstração</a>
                </nav>
            </div>
        </section>

        <nav class="entry-legal" aria-label="Informações legais e sobre o SGC">
            <a href="{{ route('home', ['apresentacao' => 1]) }}">Conhecer o SGC</a>
            <a href="/legal/">Central Legal</a>
            <a href="/legal/privacidade.html">Privacidade</a>
            <a href="/legal/termos.html">Termos de uso</a>
        </nav>
    </div>
</main>
</body>
</html>
