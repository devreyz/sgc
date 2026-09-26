@extends('layouts.bento')
@section('title','Acessos da contabilidade')
@section('page-title','Portal Contábil')
@section('page-subtitle',$tenant->name)
@php($bentoNavigation=\App\Support\PortalNavigation::make('accounting','access',$tenant->slug))
@push('styles')<link rel="stylesheet" href="{{ asset('assets/accounting-portal.css') }}">@endpush
@section('content')
<main class="acc-shell">
 <header class="acc-topbar"><div class="acc-heading"><p class="acc-eyebrow">Segurança de acesso</p><h1>Projetos e documentos liberados</h1><p>Contadores veem somente os itens liberados. Tesouraria e administração mantêm acesso integral.</p></div></header>
 @if(session('success'))<div class="acc-alert">{{ session('success') }}</div>@endif
 <section class="acc-panel">
  <form method="POST" action="{{ route('accounting.access.store',['tenant'=>$tenant->slug]) }}" class="acc-settings-grid" data-accounting-access-form>@csrf
   <label class="acc-field"><span>Usuário da contabilidade</span><select class="acc-select" name="user_id" required><option value="">Selecione</option>@foreach($memberships as $membership)<option value="{{ $membership->user_id }}">{{ $membership->tenant_name ?: $membership->user?->name }} · {{ $membership->user?->email }}</option>@endforeach</select></label>
   <label class="acc-field"><span>O que liberar</span><select class="acc-select" name="scope_type" required><option value="project">Projeto específico</option><option value="project_type">Tipo de projeto</option><option value="customer_billing_receipt">Faturamento específico</option><option value="associate_receipt">Documento de origem específico</option></select></label>
   <label class="acc-field acc-span" data-scope-field="project"><span>Projeto</span><select class="acc-select" data-scope-select="project">@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->title }}{{ $project->code ? ' · '.$project->code : '' }}</option>@endforeach</select></label>
   <label class="acc-field acc-span" data-scope-field="project_type" hidden><span>Tipo de projeto</span><select class="acc-select" data-scope-value="project_type">@foreach($projectTypes as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></label>
   <label class="acc-field acc-span" data-scope-field="customer_billing_receipt" hidden><span>Faturamento</span><select class="acc-select" data-scope-select="customer_billing_receipt">@foreach($billings as $billing)<option value="{{ $billing->id }}">#{{ $billing->id }} · {{ $billing->receipt_label ?: 'Faturamento' }}</option>@endforeach</select></label>
   <label class="acc-field acc-span" data-scope-field="associate_receipt" hidden><span>Documento de origem já usado em faturamento</span><select class="acc-select" data-scope-select="associate_receipt">@foreach($sourceReceipts as $source)<option value="{{ $source->id }}">#{{ $source->id }} · {{ $source->receipt_label ?: 'Comprovante' }}</option>@endforeach</select></label>
   <input type="hidden" name="scope_id" data-scope-id><input type="hidden" name="scope_value" data-scope-value-input>
   <div class="acc-span"><button class="acc-button acc-button-primary" type="submit"><i data-lucide="shield-check"></i> Liberar acesso</button></div>
  </form>
 </section>
 <section class="acc-panel"><div class="acc-section-heading"><div><span>Acessos atuais</span><h2>Liberações registradas</h2></div></div>
  <div class="acc-table-wrap" style="display:block"><table class="acc-table"><thead><tr><th>Usuário</th><th>Escopo</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($scopes as $scope)<tr><td>#{{ $scope->user_id }}</td><td>{{ $scope->scope_key }}</td><td>{{ $scope->active ? 'Ativo' : 'Removido' }}</td><td>@if($scope->active)<form method="POST" action="{{ route('accounting.access.destroy',['tenant'=>$tenant->slug,'scope'=>$scope]) }}">@csrf @method('DELETE')<button class="acc-button" type="submit">Remover</button></form>@endif</td></tr>@empty<tr><td colspan="4">Nenhum acesso específico foi liberado.</td></tr>@endforelse</tbody></table></div>
 </section>
</main>
@endsection
@push('scripts')<script>document.addEventListener('DOMContentLoaded',()=>{const f=document.querySelector('[data-accounting-access-form]');if(!f)return;const sync=()=>{const t=f.scope_type.value;f.querySelectorAll('[data-scope-field]').forEach(e=>e.hidden=e.dataset.scopeField!==t);const s=f.querySelector(`[data-scope-select="${t}"]`);const v=f.querySelector(`[data-scope-value="${t}"]`);f.querySelector('[data-scope-id]').value=s?.value||'';f.querySelector('[data-scope-value-input]').value=v?.value||''};f.addEventListener('change',sync);f.addEventListener('submit',sync);sync()})</script>@endpush
