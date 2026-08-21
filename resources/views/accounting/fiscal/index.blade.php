@extends('layouts.bento')
@section('title','Fila Fiscal')
@section('page-title','Portal Contábil')
@section('page-subtitle',$tenant->name)
@php($bentoNavigation=\App\Support\PortalNavigation::make('accounting','fiscal',$tenant->slug))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/accounting-portal.css') }}">@endpush
@section('content')
<main class="acc-shell" data-fiscal-queue data-url="{{ route('accounting.fiscal.data',['tenant'=>$tenant->slug]) }}" data-csrf="{{ csrf_token() }}">
 <header class="acc-topbar"><div class="acc-heading"><p class="acc-eyebrow">Fiscal</p><h1>Fila para emissão</h1><p>Somente cobranças que chegaram ao estágio fiscal.</p></div>
 @can('view_accounting_fiscal_settings')<a class="acc-button" href="{{ route('accounting.fiscal.settings',['tenant'=>$tenant->slug]) }}"><i data-lucide="settings"></i> Configuração</a>@endcan</header>
 <section class="acc-panel"><form class="acc-filters" data-fiscal-filters><label class="acc-field"><span>Buscar</span><input class="acc-input" name="search" placeholder="Cobrança ou destinatário"></label><label class="acc-field"><span>Projeto</span><select class="acc-select" name="project"><option value="">Todos</option></select></label><label class="acc-field"><span>Organização</span><select class="acc-select" name="organization"><option value="">Todas</option></select></label><label class="acc-field"><span>Documento</span><select class="acc-select" name="document_type"><option value="">Todos</option><option value="nfe">NF-e</option><option value="nfse">NFS-e</option><option value="other">Outro</option></select></label><label class="acc-field"><span>Situação</span><select class="acc-select" name="gate"><option value="">Todas</option><option value="ready">Prontos</option><option value="blocked">Bloqueados</option></select></label><label class="acc-field"><span>De</span><input class="acc-input" type="date" name="from"></label><label class="acc-field"><span>Até</span><input class="acc-input" type="date" name="until"></label><div class="acc-filter-actions"><button class="acc-button" type="button" data-clear>Limpar</button></div></form>
 <div class="acc-table-wrap"><table class="acc-table"><thead><tr><th>Cobrança</th><th>Destinatário</th><th>Projeto</th><th>Autorizada em</th><th>Valor fiscal</th><th>Situação</th><th>Ação</th></tr></thead><tbody data-fiscal-table><tr><td colspan="7"><div class="acc-empty">Carregando...</div></td></tr></tbody></table></div><div class="acc-mobile-list" data-fiscal-mobile></div><div class="acc-pagination" data-fiscal-pagination></div></section>
</main>
@endsection
@push('scripts')<script src="{{ asset('assets/accounting-fiscal.js') }}" defer></script>@endpush
