@extends('layouts.bento')
@section('title','Configurar folha para emissão')
@section('page-title','Portal Contábil')
@section('page-subtitle',$tenant->name)
@php($bentoNavigation=\App\Support\PortalNavigation::make('accounting','settings',$tenant->slug))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/accounting-portal.css') }}">@endpush
@section('content')
<main class="acc-shell">
 <header class="acc-topbar">
  <div class="acc-heading"><p class="acc-eyebrow">Folha de apoio contábil</p><h1>Configurar dados para emissão externa</h1><p>O SGC organiza e imprime os dados. A nota pode ser emitida depois no sistema da contabilidade, CONAB, prefeitura ou SEFAZ.</p></div>
  <a class="acc-button" href="{{ $receipt ? route('accounting.processes.show',['tenant'=>$tenant->slug,'receipt'=>$receipt]) : route('accounting.fiscal.index',['tenant'=>$tenant->slug]) }}"><i data-lucide="arrow-left"></i> {{ $receipt ? 'Voltar ao faturamento' : 'Voltar à fila' }}</a>
 </header>
 @if($errors->any())<div class="acc-error"><strong>Confira os campos:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 @if($receipt)<section class="acc-panel"><div class="acc-action-box"><strong>{{ $receipt->formatted_number }} · {{ $receipt->recipient_name }}</strong><span class="acc-muted">Projeto: {{ $receipt->project?->title ?: 'Não identificado' }} · Total: R$ {{ number_format((float)$receipt->total_net,2,',','.') }}</span><span class="acc-muted">A organização ou cliente não precisa possuir acesso ao SGC. A autorização eletrônica é opcional.</span></div></section>@endif

 @unless($receipt)
 <section class="acc-panel"><form class="acc-settings-form" method="GET"><label class="acc-field"><span>Aplicar configuração em</span><select class="acc-select" name="project" onchange="this.form.submit()"><option value="">Todos os projetos sem configuração própria</option>@foreach($projects as $item)<option value="{{ $item->id }}" @selected($project?->id===$item->id)>{{ $item->title }}</option>@endforeach</select></label></form></section>
 @endunless

 <form method="POST" action="{{ route('accounting.fiscal.settings.store',['tenant'=>$tenant->slug]) }}" class="acc-panel">@csrf
  <input type="hidden" name="project_id" value="{{ $project?->id }}"><input type="hidden" name="receipt_id" value="{{ $receipt?->id }}">
  <header class="acc-panel-head"><div><h2>O que constará na folha</h2><p>{{ $profile ? 'Configuração atual: versão '.$profile->version.' · '.($profile->status==='active'?'ativa':'rascunho') : 'Primeira configuração' }}</p></div></header>
  <div class="acc-settings-grid">
   <label class="acc-field"><span>Documento que será emitido *</span><select class="acc-select" name="document_type" required><option value="">Selecione</option>@foreach($documentTypes as $type)<option value="{{ $type->value }}" @selected(old('document_type',$profile?->document_type?->value)===$type->value)>{{ $type->label() }}</option>@endforeach</select><small>É apenas a indicação para o contador; o SGC não transmite a nota.</small></label>
   <label class="acc-field"><span>Valor a apresentar *</span><select class="acc-select" name="amount_source" required><option value="">Selecione</option>@foreach($amountSources as $source)<option value="{{ $source->value }}" @selected(old('amount_source',$profile?->amount_source?->value)===$source->value)>{{ $source->label() }}</option>@endforeach</select></label>
   <label class="acc-check"><input type="checkbox" name="require_issuer_tax_id" value="1" @checked(old('require_issuer_tax_id',$profile?->require_issuer_tax_id??true))> Conferir CNPJ do emitente</label>
   <label class="acc-check"><input type="checkbox" name="require_issuer_address" value="1" @checked(old('require_issuer_address',$profile?->require_issuer_address??true))> Conferir endereço do emitente</label>
   <label class="acc-check"><input type="checkbox" name="require_recipient_tax_id" value="1" @checked(old('require_recipient_tax_id',$profile?->require_recipient_tax_id??true))> Conferir CPF/CNPJ do destinatário</label>
   <label class="acc-field acc-span"><span>Instruções para quem emitirá a nota</span><textarea class="acc-input" name="standard_notes" rows="4" maxlength="2000" placeholder="Ex.: emitir no portal da CONAB e anexar o PDF/XML posteriormente.">{{ old('standard_notes',$profile?->standard_notes) }}</textarea></label>
   <details class="acc-advanced acc-span"><summary>Controle posterior dos arquivos emitidos</summary><div class="acc-settings-grid"><label class="acc-check"><input type="checkbox" name="require_pdf" value="1" @checked(old('require_pdf',$profile?->require_pdf??false))> Esperar PDF da nota posteriormente</label><label class="acc-check"><input type="checkbox" name="require_xml" value="1" @checked(old('require_xml',$profile?->require_xml??false))> Esperar XML posteriormente</label></div><p class="acc-help">Estas opções servem para conferência futura e não significam integração automática com SEFAZ.</p></details>
   @if($receipt)<input type="hidden" name="active" value="1">@else<label class="acc-check acc-span"><input type="checkbox" name="active" value="1" @checked(old('active',$profile?->status==='active' || !$profile))> Usar esta configuração imediatamente</label>@endif
  </div>
  <div class="acc-action-box"><p class="acc-muted">Será criada uma nova versão para preservar o histórico. Nenhuma nota fiscal será transmitida automaticamente.</p>@can('manage_accounting_fiscal_settings')<button class="acc-button acc-button-primary" type="submit"><i data-lucide="file-check"></i> {{ $receipt ? 'Salvar e abrir faturamento completo' : 'Salvar configuração' }}</button>@endcan</div>
 </form>
</main>
@endsection
