<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0d5f38">
    <title>SGC Aplicativo</title>
    <link rel="stylesheet" href="/assets/sgc-app-portal.css">
</head>
<body>
<main class="page">
    <section class="hero">
        <div class="brand"><span class="mark">S</span><span>SGC · Aplicativo</span></div>
        <span class="eyebrow">Acesso à sua organização</span>
        <h1>Gestão cooperativa no ritmo do seu trabalho.</h1>
        <p>Entre para acompanhar projetos, entregas, documentos e informações da sua organização.</p>
        <a class="login" href="{{ route('login') }}">Entrar no SGC</a>
    </section>
    <nav class="panel" aria-label="Links úteis">
        <a class="item" href="/legal/privacidade.html"><strong>Privacidade</strong><span>Como protegemos seus dados.</span></a>
        <a class="item" href="/legal/termos.html"><strong>Termos de uso</strong><span>Regras para utilização do SGC.</span></a>
        <a class="item" href="mailto:{{ config('legal.contact_email') }}"><strong>Suporte</strong><span>Fale com nossa equipe.</span></a>
        <a class="item" href="mailto:{{ config('legal.privacy_email') }}"><strong>Contato de privacidade</strong><span>Dúvidas sobre dados pessoais.</span></a>
    </nav>
    <div class="status">Conexão segura necessária</div>
</main>
</body>
</html>
