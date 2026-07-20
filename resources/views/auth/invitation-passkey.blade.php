@extends('layouts.security-public')

@section('title', 'Criar passkey')
@section('heading')
    <h1>Criar passkey</h1>
@endsection

@section('content')
    <label for="passkey-name">Nome deste acesso</label>
    <input id="passkey-name" class="field" style="letter-spacing:0;text-transform:none" maxlength="60" value="{{ $suggestedPasskeyName }}" autocomplete="off">
    <button class="btn" id="create-passkey" type="button">Criar passkey</button>
    <div class="status" id="status" role="status" aria-live="polite"></div>
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
