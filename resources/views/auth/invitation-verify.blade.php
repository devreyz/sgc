@extends('layouts.security-public')

@section('title', 'Validar acesso')
@section('heading')
    <h1>Validar acesso</h1>
    <p>Digite o código recebido em um canal separado.</p>
@endsection

@section('content')
    <form id="code-form">
        <label for="access-code">Código de acesso</label>
        <input id="access-code" class="field" name="code" maxlength="10" autocomplete="one-time-code" inputmode="text" required autofocus>
        <button class="btn" id="submit-code" type="submit">Continuar</button>
    </form>
    <div class="status {{ session('error') ? 'show error' : '' }}" id="status" role="status" aria-live="polite">{{ session('error') }}</div>
    <div class="privacy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg><span>Seus dados não são exibidos antes da validação. O link e o código funcionam uma única vez.</span></div>
@endsection

@push('scripts')
<script>
    const form = document.getElementById('code-form');
    const button = document.getElementById('submit-code');
    const status = document.getElementById('status');
    form.addEventListener('submit', async (event) => {
        event.preventDefault(); button.disabled = true; status.className = 'status show'; status.textContent = 'Validando...';
        try {
            const response = await fetch(@json(route('access.invitation.code')), {method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({code:document.getElementById('access-code').value})});
            const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Não foi possível validar este acesso.');
            window.location.assign(data.redirect);
        } catch (error) { status.className = 'status show error'; status.textContent = 'Não foi possível validar este acesso.'; button.disabled = false; }
    });
</script>
@endpush
