<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>@yield('title') - {{ config('app.name', 'ZeCoop SGC') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root{--green:#16803d;--green-2:#20a957;--ink:#132018;--muted:#617066;--line:#dce7e0;--soft:#f3f8f5;--danger:#b42318}
        *{box-sizing:border-box}body{margin:0;min-width:320px;min-height:100dvh;background:#f2f7f4;color:var(--ink);font-family:Inter,system-ui,sans-serif}
        .secure-page{display:grid;min-height:100dvh;place-items:center;padding:18px}.secure-panel{width:min(100%,440px);border:1px solid var(--line);border-radius:8px;background:#fff;box-shadow:0 18px 54px rgba(22,61,37,.12)}
        .secure-head{padding:22px 22px 16px;border-bottom:1px solid var(--line)}.brand{display:flex;align-items:center;gap:10px;color:var(--green);font-size:14px;font-weight:800}.brand-mark{display:grid;width:36px;height:36px;place-items:center;border-radius:8px;background:#e8f7ed}.brand-mark svg{width:19px}
        h1{margin:20px 0 6px;font-size:23px;letter-spacing:0}p{margin:0;color:var(--muted);font-size:14px;line-height:1.55}.secure-body{padding:22px}
        label{display:block;margin-bottom:7px;font-size:13px;font-weight:750}.field{width:100%;height:48px;padding:0 13px;border:1px solid #bacbc0;border-radius:6px;outline:none;font-size:17px;letter-spacing:2px;text-transform:uppercase}.field:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(22,128,61,.12)}
        .btn{display:flex;width:100%;height:48px;align-items:center;justify-content:center;gap:8px;margin-top:14px;border:0;border-radius:6px;background:var(--green);color:#fff;cursor:pointer;font-weight:750}.btn:disabled{cursor:wait;opacity:.58}.status{display:none;margin-top:14px;padding:11px;border-radius:6px;background:var(--soft);color:var(--muted);font-size:13px}.status.show{display:block}.status.error{background:#fff1f0;color:var(--danger)}
        .privacy{display:flex;gap:8px;margin-top:18px;padding-top:16px;border-top:1px solid var(--line);color:var(--muted);font-size:12px;line-height:1.5}.privacy svg{width:17px;flex:none;color:var(--green)}
        @media(max-width:480px){.secure-page{align-items:start;padding:10px}.secure-head,.secure-body{padding:18px}}
    </style>
    @stack('head')
</head>
<body>
<main class="secure-page"><section class="secure-panel">
    <header class="secure-head">
        <div class="brand"><span class="brand-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path><path d="m9 12 2 2 4-4"></path></svg></span>{{ config('app.name', 'ZeCoop SGC') }}</div>
        @yield('heading')
    </header>
    <div class="secure-body">@yield('content')</div>
</section></main>
@stack('scripts')
</body>
</html>
