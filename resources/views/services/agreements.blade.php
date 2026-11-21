@extends('layouts.campo')

@section('title', 'Negociação de cobranças')
@section('page-title', 'Negociação de cobranças')
@section('user-role', 'Financeiro')

@php
    $tenantSlug = $tenant->slug;
    $bentoNavigation = \App\Support\PortalNavigation::make('services', 'agreements', $tenantSlug);
    $listUrl = route('services.management.agreements', $tenantSlug);
    $createUrl = route('services.management.agreements.create', $tenantSlug);
    $previousUrl = $previous ? route('services.management.agreements.show', [$tenantSlug, $previous]) : null;
    $orderUrl = $origin && auth()->user()->checkPermissionTo('view_service_management')
        ? route('services.management.show', [$tenantSlug, $origin]) : null;
    $status = fn ($record) => match ($record->plan?->status ?? $record->status) {
        'active' => 'Em aberto', 'completed' => 'Quitado', 'superseded' => 'Renegociado', 'cancelled' => 'Cancelado', default => 'Sem cronograma',
    };
    $money = fn ($amount) => 'R$ '.number_format((float) $amount, 2, ',', '.');
    $canPay = auth()->user()->checkPermissionTo('manage_service_receivables');
    $canReverse = auth()->user()->checkPermissionTo('reverse_service_payment');
    $uuid = fn () => (string) \Illuminate\Support\Str::uuid();
@endphp
@section('content')
@include('services._campo-style')
<style>
    .billing-workspace {display:grid;grid-column:1/-1;gap:1rem;width:100%;min-width:0;color:var(--ui-color-text)}
    .billing-workspace [hidden] {display:none!important}
    .billing-workspace h1 {font-size:clamp(1.45rem,3vw,2rem);font-weight:780;line-height:1.2;overflow-wrap:anywhere}
    .billing-workspace h2 {font-size:1.125rem;font-weight:740}
    .billing-workspace h3 {font-size:1rem;font-weight:720}
    .billing-card {min-width:0;background:var(--ui-color-surface);border:1px solid var(--ui-color-border);border-radius:1rem;padding:clamp(.85rem,2vw,1.35rem)}
    .billing-stack {display:grid;gap:.8rem;min-width:0}
    .billing-actions {display:flex;align-items:center;flex-wrap:wrap;gap:.55rem;min-width:0}
    .billing-actions > * {min-width:0}
    .billing-button {display:inline-flex;align-items:center;justify-content:center;gap:.4rem;min-height:44px;padding:.45rem .85rem;border:1px solid var(--ui-color-border);border-radius:.7rem;color:var(--ui-color-text);background:var(--ui-color-surface-soft);font-size:.875rem;font-weight:720;text-align:center;cursor:pointer}
    .billing-button:hover {border-color:var(--ui-color-primary)}
    .billing-muted {color:var(--ui-color-text-muted);font-size:.875rem}
    .billing-error {color:var(--ui-color-danger);overflow-wrap:anywhere}
    .billing-summary {display:grid;grid-template-columns:repeat(auto-fit,minmax(min(8rem,100%),1fr));gap:.55rem}
    .billing-summary > div {display:grid;gap:.25rem;padding:.65rem;background:var(--ui-color-surface-soft);border-radius:.65rem;min-width:0;overflow-wrap:anywhere}
    .billing-summary dd {font-weight:750;font-variant-numeric:tabular-nums}
    .billing-fields {display:grid;grid-template-columns:repeat(auto-fit,minmax(min(12rem,100%),1fr));gap:.65rem}
    .billing-field {display:grid;align-content:start;gap:.3rem;min-width:0;font-size:.875rem;font-weight:650}
    .billing-field :is(input,select,textarea) {width:100%;min-width:0;max-width:100%;padding:.5rem .6rem;border:1px solid var(--ui-color-border);border-radius:.6rem;font-weight:400}
    .billing-row {display:grid;grid-template-columns:minmax(0,2fr) minmax(0,3fr) auto;gap:.65rem;align-items:center;min-width:0;padding:.7rem 0;border-bottom:1px solid var(--ui-color-border);overflow-wrap:anywhere}
    .billing-row > * {min-width:0}
    .billing-row:first-of-type {border-top:1px solid var(--ui-color-border)}
    .billing-row:last-child {border-bottom:0}
    .billing-row strong {font-weight:740}
    .billing-pick {display:flex;align-items:flex-start;gap:.55rem;min-width:0;cursor:pointer}
    .billing-pick input {width:1.15rem;height:1.15rem;flex:none;margin-top:.2rem}
    .billing-facts {display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.45rem;font-size:.875rem}
    .billing-facts span {display:block}
    .billing-steps {display:flex;flex-wrap:wrap;gap:.45rem;font-size:.82rem;font-weight:650}
    .billing-steps li {padding:.35rem .58rem;border:1px solid var(--ui-color-border);border-radius:2rem;color:var(--ui-color-text-muted)}
    .billing-steps [aria-current=step] {background:var(--ui-color-primary-soft);color:var(--ui-color-primary);border-color:var(--ui-color-primary-border)}
    .billing-workspace summary {cursor:pointer;font-weight:680}
    .billing-workspace dialog {width:min(calc(100% - 2rem),42rem);max-height:86dvh;margin:auto;overflow:auto;overscroll-behavior:contain;border:1px solid var(--ui-color-border);border-radius:1rem;padding:1rem;color:var(--ui-color-text);background:var(--ui-color-surface)}
    .billing-workspace dialog::backdrop {background:#14251e88}
    .billing-workspace dialog[open] {animation:billing-enter .16s ease-out}
    @keyframes billing-enter {from {opacity:.6;transform:translateY(.5rem)} to {opacity:1;transform:translateY(0)}}
    @media(max-width:640px) {
        .billing-row {grid-template-columns:minmax(0,1fr);gap:.45rem}
        .billing-workspace dialog {width:100%;max-width:none;margin:auto 0 0;border-radius:1rem 1rem 0 0;padding-bottom:calc(1rem + env(safe-area-inset-bottom,0px))}
    }
    @media(prefers-reduced-motion:reduce) {.billing-workspace dialog[open] {animation:none}}
</style>

<div class="services-campo billing-workspace" id="service-billing">
    <header class="billing-card billing-stack">
        @if($orderUrl || $creating || $agreement)
            <a href="{{ $orderUrl ?? $previousUrl ?? $listUrl }}" class="billing-muted"><i class="ph ph-arrow-left" aria-hidden="true"></i> {{ $orderUrl ? 'Voltar à '.$origin->number : ($previousUrl ? 'Voltar ao acordo anterior' : 'Voltar aos acordos') }}</a>
        @endif
        <div class="billing-actions justify-between">
            <div>
                @if($agreement)<p class="billing-muted">{{ $agreement->number }} · {{ $status($agreement) }}</p>@endif
                <h1>{{ $agreement ? (data_get($agreement->terms_snapshot, 'party.name') ?: 'Beneficiário') : ($previous ? 'Renegociar saldo' : ($creating ? 'Negociar cobrança' : 'Acordos de cobrança')) }}</h1>
                @if($creating)<p class="billing-muted">{{ $tenant->name }}</p>@endif
            </div>
            @if(! $creating && ! $agreement)
                <a href="{{ $createUrl }}" class="billing-button primary" data-next-agreement><i class="ph ph-plus" data-next-icon aria-hidden="true"></i> Nova negociação</a>
            @endif
        </div>
    </header>

    @if(session('success'))
        <p class="billing-card" role="status">{{ session('success') }}
            @if(session('received_payment_id') && $canPay)
                <a data-sgc-pdf class="underline" href="{{ route('services.management.payments.receipt', [$tenantSlug, session('received_payment_id'), 'inline' => 1]) }}">Abrir recibo</a>
            @endif
        </p>
    @endif
    @if($errors->any())
        <ul class="billing-card billing-error" role="alert">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    @if($creating)
        @include('services._agreement-create')
    @elseif($agreement)
        @include('services._agreement-detail')
    @else
        <section class="billing-card billing-stack" aria-label="Acordos registrados">
            @forelse($agreements as $record)
                <a class="billing-row" href="{{ route('services.management.agreements.show', [$tenantSlug, $record]) }}" data-next-agreement>
                    <span>
                        <strong>{{ data_get($record->terms_snapshot, 'party.name') ?: 'Beneficiário' }}</strong>
                        <span class="billing-muted block">{{ $record->number }} · {{ $record->created_at?->format('d/m/Y') }}</span>
                    </span>
                    <span class="billing-muted">{{ $status($record) }}</span>
                    <strong>{{ $money($record->negotiated_amount) }} <i class="ph ph-caret-right" data-next-icon aria-hidden="true"></i></strong>
                </a>
            @empty
                <p>Nenhuma negociação registrada.</p>
            @endforelse
            @if($agreements->hasPages())
                <nav class="billing-actions justify-between" aria-label="Páginas de acordos">
                    @if($agreements->previousPageUrl())<a class="billing-button" href="{{ $agreements->previousPageUrl() }}">Anterior</a>@endif
                    <span class="billing-muted">Página {{ $agreements->currentPage() }} de {{ $agreements->lastPage() }}</span>
                    @if($agreements->nextPageUrl())<a class="billing-button" href="{{ $agreements->nextPageUrl() }}">Próxima</a>@endif
                </nav>
            @endif
        </section>
    @endif
</div>
<script>
(() => {
    const root = document.getElementById('service-billing');
    root.addEventListener('click', (event) => {
        const open = event.target.closest('[data-billing-open]');
        if (open) document.getElementById(open.dataset.billingOpen)?.showModal();
        const close = event.target.closest('[data-billing-close]');
        if (close) close.closest('dialog').close();
        const next = event.target.closest('[data-next-agreement]');
        if (next) {
            next.setAttribute('aria-busy', 'true');
            const icon = next.querySelector('[data-next-icon]');
            icon.dataset.previousClass = icon.className;
            icon.className = 'ph ph-circle-notch animate-spin';
        }
    });
    root.addEventListener('submit', (event) => {
        if (event.defaultPrevented || !event.submitter || !event.target.matches('[data-billing-submit]')) return;
        event.submitter.dataset.previousLabel = event.submitter.textContent;
        event.submitter.disabled = true;
        event.submitter.textContent = 'Salvando…';
        event.target.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) return;
        root.querySelectorAll('[aria-busy=true]').forEach(element => element.removeAttribute('aria-busy'));
        root.querySelectorAll('[data-previous-class]').forEach(icon => icon.className = icon.dataset.previousClass);
        root.querySelectorAll('[data-previous-label]').forEach(button => {
            button.disabled = false;
            button.textContent = button.dataset.previousLabel;
        });
    });
})();
</script>
@endsection
