@extends('layouts.security-public')

@section('title', 'Criar passkey')
@section('heading')
    <h1>Criar passkey</h1>
    <p>Use a proteção de tela deste dispositivo para concluir.</p>
@endsection

@section('content')
    <label for="passkey-name">Nome deste acesso</label>
    <input id="passkey-name" class="field" style="letter-spacing:0;text-transform:none" maxlength="60" value="{{ $suggestedPasskeyName }}" autocomplete="off">
    <button class="btn" id="create-passkey" type="button">Criar passkey</button>
    <div class="status" id="status" role="status" aria-live="polite"></div>
    <div class="privacy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"></path></svg><span>A chave privada e a biometria permanecem no seu dispositivo. O sistema recebe somente a chave pública.</span></div>
@endsection

@push('scripts')
<script>
    const button = document.getElementById('create-passkey'); const status = document.getElementById('status'); const nameInput = document.getElementById('passkey-name');
    nameInput.addEventListener('input',()=>{const words=nameInput.value.trimStart().split(/\s+/).filter(Boolean);if(words.length>3)nameInput.value=words.slice(0,3).join(' ')});
    function ready() {
        if (!window.isSecureContext || !window.SgcPasskeys?.isSupported()) { button.disabled = true; status.className='status show error'; status.textContent='Este navegador ou conexão não oferece suporte a passkeys.'; return; }
        button.addEventListener('click', async () => {
            button.disabled=true; status.className='status show'; status.textContent='Aguardando confirmação no dispositivo...';
            try {
                const data = await window.SgcPasskeys.register({name:nameInput.value,routes:{options:@json(route('access.invitation.passkey.options')),submit:@json(route('access.invitation.passkey.store'))}});
                window.location.assign(data.redirect || '/');
            } catch (error) { status.className='status show error'; status.textContent=error.name === 'UserCancelledError' ? 'A operação foi cancelada.' : 'Não foi possível concluir o acesso.'; button.disabled=false; }
        });
    }
    window.SgcPasskeys ? ready() : window.addEventListener('sgc:passkeys-ready', ready, {once:true});
</script>
@endpush
