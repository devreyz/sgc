<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Links de acesso</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body{margin:0;background:#f5f7f5;color:#17231b;font-family:Inter,system-ui,sans-serif}.page{width:min(100% - 28px,900px);margin:28px auto}.head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:24px}.head h1{margin:0;font-size:25px}.head p{margin:5px 0 0;color:#647269}.create{display:flex;align-items:end;gap:8px}.field label{display:block;margin-bottom:5px;font-size:12px;font-weight:800;color:#55645a}.field select{height:40px;padding:0 34px 0 10px;border:1px solid #c8d5cc;border-radius:6px;background:#fff}.btn{display:inline-flex;height:40px;align-items:center;justify-content:center;padding:0 14px;border:0;border-radius:6px;background:#16803d;color:#fff;cursor:pointer;font-weight:720;text-decoration:none}.btn.secondary{border:1px solid #c8d5cc;background:#fff;color:#31483a}.row{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center;padding:15px 0;border-top:1px solid #dce5df}.row strong,.row span{display:block}.row span{margin-top:4px;color:#68766d;font-size:13px}.badge{display:inline-block!important;width:max-content;margin-top:7px!important;padding:3px 7px;border-radius:4px;background:#edf5ef;color:#27623b;font-size:11px!important;font-weight:800;text-transform:uppercase}.modal{width:min(92vw,520px);border:0;border-radius:8px;padding:22px;box-shadow:0 24px 80px #0004}.modal::backdrop{background:#102018a8}.secret{margin:12px 0;padding:12px;background:#f2f7f4;border:1px solid #d8e4dc;border-radius:6px}.secret label{display:block;margin-bottom:6px;font-size:12px;font-weight:800}.secret code{display:block;overflow-wrap:anywhere;font-size:14px}.actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:15px}.notice{padding:11px;border-left:3px solid #c88719;background:#fff8e8;color:#6d4a0d;font-size:13px}.message{margin-bottom:14px;color:#b42318}.loading{position:fixed;inset:0;display:none;place-items:center;background:#f7faf8d9;z-index:1000;font-weight:750}.loading.show{display:grid}@media(max-width:600px){.head{align-items:flex-start;flex-direction:column}.create{width:100%;align-items:stretch;flex-direction:column}.row{grid-template-columns:1fr}.btn{width:100%}}
    </style>
</head>
<body>
<div class="loading" id="loading" role="status">Processando...</div>
<main class="page">
    <header class="head">
        <div><h1>Links de acesso</h1><p>{{ $targetLabel }}</p></div>
        <div class="create"><div class="field"><label for="invite-ttl">Validade</label><select id="invite-ttl"><option value="24">24 horas</option><option value="36" selected>36 horas</option><option value="48">48 horas</option></select></div><button class="btn" id="new-invite">Gerar link de acesso</button></div>
    </header>
    <div class="message" id="message"></div>

    @forelse($invitations as $invitation)
        <div class="row" data-invitation="{{ $invitation->id }}">
            <div><strong>Convite de {{ $invitation->created_at->format('d/m/Y H:i') }}</strong><span>Criado por {{ $issuerNames[$invitation->issued_by_user_id] ?? 'Membro nao identificado' }} · Expira em {{ $invitation->expires_at->format('d/m/Y H:i') }}</span>@if($invitation->consumed_at)<span>Consumido em {{ $invitation->consumed_at->format('d/m/Y H:i') }}</span>@endif<span class="badge">{{ $invitation->status }}</span></div>
            @if(in_array($invitation->status, ['pending', 'claimed'], true))<button class="btn secondary revoke" data-id="{{ $invitation->id }}">Revogar</button>@endif
        </div>
    @empty
        <p>Nenhum convite emitido.</p>
    @endforelse
</main>

<dialog class="modal" id="invite-modal">
    <h2>Convite criado</h2>
    <div class="notice">Envie o link e o codigo por canais diferentes. Estes dados nao poderao ser exibidos novamente.</div>
    <div class="secret"><label>Link</label><code id="invite-link"></code></div>
    <div class="secret"><label>Codigo</label><code id="invite-code"></code></div>
    <div class="actions"><button class="btn secondary" id="copy-link">Copiar link</button><button class="btn secondary" id="copy-code">Copiar codigo</button><button class="btn secondary" id="share-link">Enviar link</button><button class="btn" id="close-modal">Concluir</button></div>
</dialog>

<script>
const csrf=document.querySelector('meta[name="csrf-token"]').content;
const modal=document.getElementById('invite-modal');
const loading=document.getElementById('loading');
const message=document.getElementById('message');
let currentInvitationId=null;
const busy=value=>loading.classList.toggle('show',value);
const invitationUrl=id=>@json($sentUrlTemplate).replace('__ID__',id);

document.getElementById('new-invite').addEventListener('click',async()=>{
    busy(true);message.textContent='';
    try{
        const response=await fetch(@json($storeUrl),{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({expires_in_hours:Number(document.getElementById('invite-ttl').value)})});
        const data=await response.json();
        if(!response.ok)throw new Error(data.message||Object.values(data.errors||{}).flat()[0]);
        currentInvitationId=data.id;document.getElementById('invite-link').textContent=data.link;document.getElementById('invite-code').textContent=data.code;modal.showModal();
    }catch(error){message.textContent=error.message||'Nao foi possivel criar o convite.'}finally{busy(false)}
});

document.getElementById('copy-link').addEventListener('click',()=>navigator.clipboard.writeText(document.getElementById('invite-link').textContent));
document.getElementById('copy-code').addEventListener('click',()=>navigator.clipboard.writeText(document.getElementById('invite-code').textContent));
document.getElementById('share-link').addEventListener('click',async()=>{
    const link=document.getElementById('invite-link').textContent;
    try{
        if(navigator.share)await navigator.share({title:'Link de acesso ao SGC',text:'Use este link para iniciar seu acesso. O codigo sera enviado separadamente.',url:link});
        else await navigator.clipboard.writeText(link);
        if(currentInvitationId)await fetch(invitationUrl(currentInvitationId),{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});
    }catch(error){if(error.name!=='AbortError')message.textContent='Nao foi possivel compartilhar o link.'}
});
document.getElementById('close-modal').addEventListener('click',()=>{modal.close();location.reload()});

document.querySelectorAll('.revoke').forEach(button=>button.addEventListener('click',async()=>{
    if(!confirm('Revogar este convite?'))return;
    busy(true);
    const url=@json($revokeUrlTemplate).replace('__ID__',button.dataset.id);
    try{const response=await fetch(url,{method:'DELETE',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-TOKEN':csrf}});const data=await response.json();if(!response.ok)throw new Error(data.message);location.reload()}catch(error){busy(false);message.textContent=error.message||'Nao foi possivel revogar o convite.'}
}));
</script>
</body>
</html>
