@extends('layouts.bento')

@section('title', 'Autorizações')
@section('page-title', 'Autorizações')
@section('user-role', $organization->short_name ?: $organization->name)

@php
    $routeTenant = request()->route('tenant');
    $tenantSlug = is_object($routeTenant) ? $routeTenant->slug : $routeTenant;
    $bentoNavigation = \App\Support\PortalNavigation::make('buyer', 'authorizations', $tenantSlug);
@endphp

@push('styles')
<style>
.ba-shell{display:grid;gap:.8rem;grid-column:1/-1}.ba-head,.ba-panel{border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);overflow:hidden}.ba-head{padding:.9rem;border-left:4px solid var(--color-primary-dark)}.ba-head h1{margin:0;font-size:1.2rem}.ba-head p{margin:.25rem 0 0;color:var(--color-text-secondary);font-size:.78rem}.ba-table{width:100%;border-collapse:collapse;font-size:.76rem}.ba-table th,.ba-table td{padding:.72rem;border-bottom:1px solid var(--color-border);text-align:left;vertical-align:middle}.ba-table th{background:var(--color-surface-soft);font-size:.66rem;text-transform:uppercase}.ba-link{color:var(--color-primary-dark);font-weight:800;text-decoration:none}.ba-status{display:inline-flex;padding:.24rem .42rem;border:1px solid var(--color-border);border-radius:999px;font-size:.65rem;font-weight:750}.ba-mobile{display:none}.ba-mobile a{display:grid;gap:.45rem;padding:.8rem;border-bottom:1px solid var(--color-border);color:inherit;text-decoration:none}.ba-mobile strong{font-size:.82rem}.ba-mobile span{font-size:.7rem;color:var(--color-text-secondary)}
.ba-switcher{display:flex;gap:.4rem;overflow-x:auto;padding:.7rem;border-bottom:1px solid var(--color-border)}.ba-switcher a{flex:0 0 auto;padding:.42rem .58rem;border:1px solid var(--color-border);border-radius:7px;color:var(--color-text);font-size:.7rem;font-weight:750;text-decoration:none}.ba-switcher a.is-active{border-color:var(--color-primary-dark);background:var(--color-primary-dark);color:#fff}
@media(max-width:720px){.ba-table-wrap{display:none}.ba-mobile{display:block}}
</style>
@endpush

@section('content')
<main class="ba-shell">
    <header class="ba-head"><h1>Cobranças para análise</h1><p>Consulte as versões enviadas para sua organização.</p></header>
    <section class="ba-panel">
        @if($organizations->count() > 1)
        <nav class="ba-switcher" aria-label="Organização representada">
            @foreach($organizations as $availableOrganization)
                <a class="{{ $availableOrganization->is($organization) ? 'is-active' : '' }}" href="{{ route('buyer.authorizations.index', ['tenant' => $tenantSlug, 'organization' => $availableOrganization->id]) }}">{{ $availableOrganization->short_name ?: $availableOrganization->name }}</a>
            @endforeach
        </nav>
        @endif
        <div class="ba-table-wrap"><table class="ba-table"><thead><tr><th>Cobrança</th><th>Projeto</th><th>Período</th><th>Valor</th><th>Enviada em</th><th>Situação</th></tr></thead><tbody>
        @forelse($rounds as $round)
            <tr>
                <td><a class="ba-link" href="{{ route('buyer.authorizations.show',['tenant'=>$tenantSlug,'billingAuthorization'=>$round->id]) }}">{{ $round->receipt?->formatted_number }} · versão {{ $round->sequence }}</a></td>
                <td>{{ $round->receipt?->project?->title }}</td>
                <td>{{ data_get($round->snapshot,'identity.period.from') ? \Illuminate\Support\Carbon::parse(data_get($round->snapshot,'identity.period.from'))->format('d/m/Y') : '—' }} a {{ data_get($round->snapshot,'identity.period.to') ? \Illuminate\Support\Carbon::parse(data_get($round->snapshot,'identity.period.to'))->format('d/m/Y') : '—' }}</td>
                <td>R$ {{ number_format((float)data_get($round->snapshot,'totals.net',0),2,',','.') }}</td>
                <td>{{ $round->sent_at?->format('d/m/Y H:i') }}</td>
                <td><span class="ba-status">{{ $round->status->label() }}</span></td>
            </tr>
        @empty<tr><td colspan="6">Nenhuma cobrança foi enviada para análise.</td></tr>@endforelse
        </tbody></table></div>
        <div class="ba-mobile">
        @foreach($rounds as $round)
            <a href="{{ route('buyer.authorizations.show',['tenant'=>$tenantSlug,'billingAuthorization'=>$round->id]) }}">
                <strong>{{ $round->receipt?->formatted_number }} · versão {{ $round->sequence }}</strong>
                <span>{{ $round->receipt?->project?->title }}</span>
                <span>R$ {{ number_format((float)data_get($round->snapshot,'totals.net',0),2,',','.') }} · {{ $round->status->label() }}</span>
            </a>
        @endforeach
        </div>
    </section>
    <div>{{ $rounds->links() }}</div>
</main>
@endsection
