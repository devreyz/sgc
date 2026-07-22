@extends('layouts.bento')

@section('title', 'Notificacoes')
@section('page-title', 'Notificacoes')
@section('user-role', 'Central de avisos')

@section('content')
<style>
    .notification-shell{max-width:920px;margin:0 auto;display:grid;gap:1rem}.notification-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem}.notification-toolbar h2{font-size:1rem;margin:0}.notification-toolbar p{font-size:.78rem;color:var(--color-text-secondary);margin:.2rem 0 0}.notification-list{display:grid;gap:.55rem}.notification-item{display:grid;grid-template-columns:2.5rem minmax(0,1fr) auto;gap:.8rem;align-items:start;padding:1rem 1.05rem;border:1px solid var(--color-border);background:var(--color-surface);border-radius:var(--radius-md)}.notification-item.unread{border-left:4px solid var(--color-primary);background:color-mix(in srgb,var(--color-primary) 4%,var(--color-surface))}.notification-icon{width:2.5rem;height:2.5rem;border-radius:8px;background:var(--color-bg);display:grid;place-items:center;color:var(--color-primary)}.notification-copy{min-width:0}.notification-copy h3{font-size:.9rem;margin:0 0 .25rem}.notification-copy p{font-size:.8rem;line-height:1.45;color:var(--color-text-secondary);margin:0}.notification-meta{display:flex;align-items:center;gap:.45rem;margin-top:.55rem;font-size:.7rem;color:var(--color-text-secondary)}.priority{font-weight:700;text-transform:uppercase}.priority-critical,.priority-high{color:var(--color-danger)}.notification-open{width:2rem;height:2rem;display:grid;place-items:center;border:1px solid var(--color-border);border-radius:7px;color:var(--color-text);background:var(--color-surface)}.notification-empty{text-align:center;padding:3rem 1rem}.notification-empty svg{width:2rem;height:2rem;color:var(--color-text-secondary);margin-bottom:.6rem}.push-panel{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.1rem}.push-panel strong{display:block;font-size:.88rem}.push-panel span{font-size:.75rem;color:var(--color-text-secondary)}.action-button{border:0;border-radius:7px;background:var(--color-primary);color:#fff;font-size:.78rem;font-weight:700;padding:.65rem .85rem;cursor:pointer}.action-button.secondary{background:var(--color-bg);color:var(--color-text);border:1px solid var(--color-border)}@media(max-width:640px){.notification-item{grid-template-columns:2.25rem minmax(0,1fr) auto;padding:.85rem}.notification-icon{width:2.25rem;height:2.25rem}.push-panel,.notification-toolbar{align-items:flex-start;flex-direction:column}.action-button{width:100%}}
</style>

<div class="notification-shell">
    <section class="bento-card push-panel">
        <div><strong>Notificacoes neste dispositivo</strong><span id="push-status-label">Verificando permissao...</span></div>
        <button type="button" class="action-button" id="push-toggle">Ativar notificacoes</button>
    </section>

    <section class="bento-card notification-toolbar">
        <div><h2>Seus avisos</h2><p>{{ $notifications->total() }} registro(s) nesta organizacao</p></div>
        @if($notifications->whereNull('read_at')->isNotEmpty())
            <button type="button" class="action-button secondary" id="mark-all-read">Marcar todas como lidas</button>
        @endif
    </section>

    <div class="notification-list" id="notification-list">
        @forelse($notifications as $notification)
            @php($data = $notification->data)
            <article class="notification-item {{ $notification->read_at ? '' : 'unread' }}" data-notification-id="{{ $notification->id }}">
                <span class="notification-icon"><i data-lucide="{{ $data['display_icon'] ?? 'bell' }}"></i></span>
                <div class="notification-copy">
                    <h3>{{ $data['title'] ?? 'Notificacao' }}</h3>
                    <p>{{ $data['body'] ?? '' }}</p>
                    <div class="notification-meta"><span>{{ $notification->created_at->diffForHumans() }}</span><span class="priority priority-{{ $data['priority'] ?? 'normal' }}">{{ $data['priority'] ?? 'normal' }}</span></div>
                </div>
                <a class="notification-open" href="{{ route('notifications.open', ['tenant' => $tenant, 'notification' => $notification->id]) }}" aria-label="Abrir notificacao" title="Abrir"><i data-lucide="arrow-up-right"></i></a>
            </article>
        @empty
            <section class="bento-card notification-empty"><i data-lucide="bell-off"></i><strong>Nenhuma notificacao</strong></section>
        @endforelse
    </div>

    {{ $notifications->links('vendor.pagination.bento') }}
</div>
@endsection

@push('scripts')
<script>
document.getElementById('mark-all-read')?.addEventListener('click', async function () {
    const response = await fetch(@json(route('notifications.read-all', ['tenant' => $tenant])), {method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'}});
    if (!response.ok) return window.appToast?.('Nao foi possivel atualizar as notificacoes.', 'error');
    document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
    this.remove();
    window.dispatchEvent(new CustomEvent('notifications:changed', {detail:{count:0}}));
});
</script>
@endpush
