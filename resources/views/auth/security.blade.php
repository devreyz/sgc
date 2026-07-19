<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Seguranca e acesso</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body{margin:0;background:#f4f7f5;color:#142018;font-family:Inter,system-ui,sans-serif}.page{width:min(100% - 28px,880px);margin:32px auto}.top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px}.top h1{margin:0;font-size:26px;letter-spacing:0}.top p{margin:6px 0 0;color:#637168}.top a{color:#166534;text-decoration:none;font-weight:700}.section{padding:20px 0;border-top:1px solid #d9e4dd}.section-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}.section h2{margin:0;font-size:17px}.section p{margin:5px 0 0;color:#68766d}.btn{display:inline-flex;height:40px;align-items:center;justify-content:center;padding:0 14px;border:0;border-radius:6px;background:#16803d;color:#fff;cursor:pointer;font-weight:700;text-decoration:none}.btn.secondary{border:1px solid #cbd8cf;background:#fff;color:#263b2e}.row{display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center;padding:14px 0;border-bottom:1px solid #e5ece7}.row strong,.row span{display:block}.row span{margin-top:4px;color:#6a786e;font-size:13px}.danger{color:#b42318;border-color:#efc2bd!important}.status{display:none;margin:12px 0;padding:11px;border-radius:6px;background:#eef6f1;color:#365542}.status.show{display:block}.status.error{background:#fff0ef;color:#b42318}.reauth{padding:16px;border:1px solid #ead29a;background:#fff9e9;border-radius:6px;margin-bottom:20px}.reauth h2{margin:0 0 5px;font-size:16px}.reauth p{margin:0 0 13px;color:#685322}.reauth-actions{display:flex;flex-wrap:wrap;gap:8px}.empty{padding:18px 0;color:#6a786e}.dialog{width:min(92vw,420px);border:0;border-radius:8px;padding:22px;box-shadow:0 22px 70px #0003}.dialog::backdrop{background:#0b1c1299}.dialog input{box-sizing:border-box;width:100%;height:44px;padding:0 11px;border:1px solid #bdcbc2;border-radius:6px;margin:10px 0}.dialog-actions{display:flex;justify-content:flex-end;gap:8px}.loading{position:fixed;inset:0;display:none;place-items:center;background:#f7faf8d9;z-index:1000;font-weight:750}.loading.show{display:grid}@media(max-width:600px){.page{margin:18px auto}.top,.section-head{align-items:flex-start;flex-direction:column}.row{grid-template-columns:1fr}.btn{width:100%}}
    </style>
</head>
<body>
<div class="loading" id="loading" role="status">Processando...</div>
<main class="page">
    <header class="top">
        <div><h1>Seguranca e acesso</h1><p>Metodos vinculados a sua conta global.</p></div>
        <a href="{{ route('home') }}">Voltar</a>
    </header>

    @if(session('error'))<div class="status show error">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="status show">{{ session('success') }}</div>@endif
    <div class="status" id="security-status"></div>

    @if(!$recentlyAuthenticated)
        <section class="reauth">
            <h2>Confirme sua identidade</h2>
            <p>Uma nova confirmacao e necessaria para alterar seus metodos de acesso.</p>
            <div class="reauth-actions">
                @if($activePasskeys->isNotEmpty())
                    <button class="btn" id="reauth-passkey">Confirmar com passkey</button>
                @endif
                @if($oauthAccounts->where('provider', 'google')->isNotEmpty())
                    <a class="btn secondary" href="{{ route('auth.google', ['intent' => 'reauth']) }}">Confirmar com Google</a>
                @endif
            </div>
        </section>
    @endif

    <section class="section">
        <div class="section-head"><div><h2>Passkeys</h2><p>Biometria, PIN ou chave fisica. Somente voce pode cadastrar uma passkey apos confirmar sua identidade.</p></div><button class="btn" id="add-passkey" @disabled(!$recentlyAuthenticated)>Adicionar passkey</button></div>
        @forelse($passkeys as $passkey)
            <div class="row"><div><strong>{{ $passkey->name ?: 'Passkey' }} @if($passkey->revoked_at) · Revogada @elseif($passkey->expires_at?->isPast()) · Expirada @endif</strong><span>Criada em {{ $passkey->created_at?->format('d/m/Y H:i') }} · Valida ate {{ $passkey->expires_at?->format('d/m/Y H:i') ?? 'nao informada' }} · Ultimo uso {{ $passkey->last_used_at?->format('d/m/Y H:i') ?? 'nunca' }}</span></div>@if(!$passkey->revoked_at && !$passkey->expires_at?->isPast())<button class="btn secondary danger revoke-passkey" data-id="{{ $passkey->id }}">Revogar</button>@endif</div>
        @empty
            <div class="empty">Nenhuma passkey cadastrada.</div>
        @endforelse
    </section>

    <section class="section">
        <div class="section-head"><div><h2>Conta Google</h2><p>Identidade vinculada pelo identificador permanente do provedor.</p></div>@if($oauthAccounts->where('provider', 'google')->isEmpty())<a class="btn" href="{{ route('auth.google', ['intent' => 'link']) }}">Vincular Google</a>@endif</div>
        @forelse($oauthAccounts as $account)
            <div class="row"><div><strong>{{ ucfirst($account->provider) }}</strong><span>{{ $account->provider_email }} · Vinculada em {{ $account->linked_at?->format('d/m/Y') }}</span></div></div>
        @empty
            <div class="empty">Nenhuma conta externa vinculada.</div>
        @endforelse
    </section>
</main>

<dialog class="dialog" id="passkey-dialog">
    <form method="dialog"><h2>Nova passkey</h2><p>Escolha um nome de ate tres palavras.</p><input id="new-passkey-name" maxlength="60" value="{{ $suggestedPasskeyName }}"><div class="dialog-actions"><button class="btn secondary" value="cancel">Cancelar</button><button class="btn" id="confirm-passkey" value="default">Criar</button></div></form>
</dialog>

<script>
const csrf=document.querySelector('meta[name="csrf-token"]').content;
const statusBox=document.getElementById('security-status');
const dialog=document.getElementById('passkey-dialog');
const loading=document.getElementById('loading');
const passkeyName=document.getElementById('new-passkey-name');
const showError=message=>{statusBox.className='status show error';statusBox.textContent=message||'Nao foi possivel concluir a operacao.'};
const busy=value=>loading.classList.toggle('show',value);
passkeyName.addEventListener('input',()=>{const words=passkeyName.value.trimStart().split(/\s+/).filter(Boolean);if(words.length>3)passkeyName.value=words.slice(0,3).join(' ')});

document.getElementById('reauth-passkey')?.addEventListener('click',async()=>{
    busy(true);
    try{
        await window.SgcPasskeys.verify({routes:{options:@json(route('security.reauth.passkey.options')),submit:@json(route('security.reauth.passkey.store'))}});
        location.reload();
    }catch(error){busy(false);showError(error.message)}
});

document.getElementById('add-passkey')?.addEventListener('click',()=>dialog.showModal());
document.getElementById('confirm-passkey').addEventListener('click',async event=>{
    event.preventDefault();busy(true);
    try{
        await window.SgcPasskeys.register({name:passkeyName.value,routes:{options:@json(route('security.passkeys.options')),submit:@json(route('security.passkeys.store'))}});
        location.reload();
    }catch(error){busy(false);dialog.close();showError(error.message)}
});

document.querySelectorAll('.revoke-passkey').forEach(button=>button.addEventListener('click',async()=>{
    if(!confirm('Revogar esta passkey?'))return;
    busy(true);
    try{
        const response=await fetch(@json(url('/security/passkeys')).replace(/\/$/,'')+'/'+button.dataset.id,{method:'DELETE',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});
        const data=await response.json();
        if(!response.ok)throw new Error(data.message);
        location.reload();
    }catch(error){busy(false);showError(error.message)}
}));
</script>
</body>
</html>
