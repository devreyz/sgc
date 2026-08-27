<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SGC sem conexão</title>
    <style>*{box-sizing:border-box}html,body{height:100%;margin:0}body{display:grid;place-items:center;padding:24px;background:#101923;color:#fff;font-family:system-ui,sans-serif}main{width:min(100%,360px);padding:28px 22px;border:1px solid #ffffff24;border-radius:20px;background:#17232e;text-align:center}h1{margin:0;font-size:20px}p{color:#ffffffb8;line-height:1.5}button{min-height:44px;padding:10px 18px;border:0;border-radius:11px;background:#168a4d;color:#fff;font:inherit;font-weight:750}</style>
</head>
<body>
    <main><h1>Sem conexão com a internet</h1><p>O SGC funciona somente online. Reconecte-se para continuar.</p><button type="button" onclick="location.reload()">Tentar novamente</button></main>
</body>
</html>
