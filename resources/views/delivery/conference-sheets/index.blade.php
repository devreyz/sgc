@extends('layouts.bento')

@section('title', 'Folhas de Conferência')
@section('page-title', 'Folhas de Conferência')
@section('page-subtitle', 'Conferência operacional das distribuições realizadas')
@section('user-role', 'Entregas')

@php
    $bentoNavigation = \App\Support\PortalNavigation::make('delivery', 'conference', $currentTenant->slug ?? request()->route('tenant'));
    $projectData = $projects->map(function ($project) {
        $customers = $project->customers->filter->status;
        if ($project->customer?->status && ! $customers->contains('id', $project->customer_id)) $customers->prepend($project->customer);
        return [
            'id' => $project->id,
            'customers' => $customers->map(fn ($customer) => ['id' => $customer->id, 'name' => $customer->trade_name ?: $customer->name])->values(),
            'organizations' => $project->organizations->map(fn ($organization) => ['id' => $organization->id, 'name' => $organization->name])->values(),
        ];
    })->keyBy('id');
@endphp

@section('content')
<style>
.fc-page{--fc-green:#168a4d;--fc-border:var(--color-border,#dce7e0);--fc-surface:var(--color-surface,#fff);--fc-soft:var(--color-surface-soft,#f8faf9);--fc-text:var(--color-text,#102018);--fc-muted:var(--color-text-secondary,#607067);display:grid;gap:.85rem;color:var(--fc-text)}
.fc-toolbar,.fc-form,.fc-table-wrap{border:1px solid var(--fc-border);border-radius:15px;background:var(--fc-surface);box-shadow:0 4px 14px rgba(15,35,24,.045)}
.fc-toolbar{display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:.75rem .85rem}.fc-toolbar h2{margin:0;font-size:1rem}.fc-toolbar p{margin:.15rem 0 0;color:var(--fc-muted);font-size:.82rem}
.fc-btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;min-height:40px;padding:.55rem .8rem;border:1px solid var(--fc-border);border-radius:10px;background:#fff;color:var(--fc-text);font-weight:700;text-decoration:none;cursor:pointer}.fc-btn-primary{border-color:var(--fc-green);background:var(--fc-green);color:#fff}.fc-form{display:none;padding:.9rem}.fc-form.open{display:block}.fc-grid{display:grid;grid-template-columns:2fr 1fr 2fr 1fr 1fr 2fr;gap:.7rem}.fc-field{display:grid;gap:.3rem}.fc-field label{font-size:.76rem;font-weight:700;color:var(--fc-muted)}.fc-field input,.fc-field select{width:100%;min-height:42px;border:1px solid var(--fc-border);border-radius:9px;background:#fff;padding:.55rem .65rem;color:var(--fc-text)}
.fc-help{grid-column:1/-1;margin:0;color:var(--fc-muted);font-size:.8rem}.fc-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:.5rem}.fc-table-wrap{overflow:hidden}.fc-table{width:100%;border-collapse:collapse}.fc-table th{padding:.68rem .72rem;background:var(--fc-soft);border-bottom:1px solid var(--fc-border);text-align:left;font-size:.72rem;text-transform:uppercase;color:var(--fc-muted)}.fc-table td{padding:.72rem;border-bottom:1px solid var(--fc-border);font-size:.86rem;vertical-align:middle}.fc-table tr:last-child td{border-bottom:0}.fc-number{font-weight:800;color:var(--fc-green)}.fc-badge{display:inline-flex;padding:.25rem .48rem;border-radius:99px;background:#eef4f0;font-size:.72rem;font-weight:700}.fc-link{color:var(--fc-green);font-weight:700;text-decoration:none}.fc-mobile{display:none}.fc-empty{padding:2.3rem;text-align:center;color:var(--fc-muted)}
@media(max-width:900px){.fc-grid{grid-template-columns:1fr 1fr}.fc-field:first-child,.fc-field:nth-child(3),.fc-field:nth-child(6){grid-column:1/-1}.fc-desktop{display:none}.fc-mobile{display:grid;gap:.65rem}.fc-card{display:grid;gap:.5rem;padding:.85rem;border-bottom:1px solid var(--fc-border)}.fc-card:last-child{border-bottom:0}.fc-card-head{display:flex;justify-content:space-between;gap:.5rem}.fc-card-meta{color:var(--fc-muted);font-size:.8rem}}@media(max-width:560px){.fc-toolbar{align-items:flex-start;flex-direction:column}.fc-toolbar .fc-btn{width:100%}.fc-grid{grid-template-columns:1fr}.fc-field{grid-column:1!important}.fc-actions{flex-direction:column}.fc-actions .fc-btn{width:100%}}
</style>
<div class="fc-page">
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="fc-toolbar">
        <div><h2>Conferência das entregas distribuídas</h2><p>Documento operacional sem valor fiscal, anterior e independente da cobrança.</p></div>
        @can('create', \App\Models\DeliveryConferenceSheet::class)<button class="fc-btn fc-btn-primary" id="fc-new" type="button"><i class="ph ph-plus"></i> Nova folha</button>@endcan
    </div>

    @can('create', \App\Models\DeliveryConferenceSheet::class)
    <form class="fc-form {{ $errors->any() ? 'open' : '' }}" id="fc-form" method="post" action="{{ route('delivery.conference-sheets.store', request()->route('tenant')) }}">
        @csrf
        <div class="fc-grid">
            <div class="fc-field"><label for="fc-project">Projeto</label><select id="fc-project" name="sales_project_id" required><option value="">Selecione</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(old('sales_project_id')==$project->id)>{{ $project->title }}</option>@endforeach</select></div>
            <div class="fc-field"><label for="fc-type">Destinatário</label><select id="fc-type" name="recipient_type" required><option value="customer">Cliente</option><option value="organization">Organização</option></select></div>
            <div class="fc-field"><label for="fc-recipient">Cliente / organização</label><select id="fc-recipient" name="recipient_id" required disabled><option value="">Escolha o projeto</option></select></div>
            <div class="fc-field"><label for="fc-start">De</label><input id="fc-start" type="date" name="period_start" value="{{ old('period_start', now()->startOfMonth()->format('Y-m-d')) }}" required></div>
            <div class="fc-field"><label for="fc-end">Até</label><input id="fc-end" type="date" name="period_end" value="{{ old('period_end', now()->format('Y-m-d')) }}" required></div>
            <div class="fc-field"><label for="fc-mode">Apresentação</label><select id="fc-mode" name="grouping_mode" required></select></div>
            <p class="fc-help">O sistema selecionará automaticamente somente distribuições aprovadas, do projeto, destinatário e período escolhidos. Entregas-pai nunca são incluídas.</p>
            <div class="fc-actions"><button class="fc-btn" id="fc-close" type="button">Cancelar</button><button class="fc-btn fc-btn-primary" type="submit">Preparar folha</button></div>
        </div>
    </form>
    @endcan

    <div class="fc-table-wrap">
        @if($sheets->isEmpty())<div class="fc-empty"><i class="ph-duotone ph-clipboard-text" style="font-size:2rem"></i><p>Nenhuma folha foi criada ainda.</p></div>@else
        <table class="fc-table fc-desktop"><thead><tr><th></th><th>Folha</th><th>Destinatário</th><th>Período</th><th>Modo</th><th>Entregas</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
        @foreach($sheets as $sheet)<tr>
            <td>@if($sheet->isApproved())<input type="checkbox" form="billing-form" name="sheet_ids[]" value="{{ $sheet->id }}" aria-label="Selecionar {{ $sheet->formatted_number }}">@endif</td>
            <td class="fc-number">{{ $sheet->formatted_number }} @if($sheet->revision>1)<small>r{{ $sheet->revision }}</small>@endif</td><td>{{ $sheet->recipient_name }}</td>
            <td>{{ $sheet->period_start->format('d/m/Y') }}–{{ $sheet->period_end->format('d/m/Y') }}</td><td>{{ $sheet->grouping_mode->label() }}</td><td>{{ $sheet->distributions_count }}</td>
            <td><span class="fc-badge">{{ $sheet->status->label() }}</span></td><td><a class="fc-link" href="{{ route('delivery.conference-sheets.show',[request()->route('tenant'),$sheet]) }}">Conferir</a></td>
        </tr>@endforeach</tbody></table>
        <div class="fc-mobile">@foreach($sheets as $sheet)<a class="fc-card" href="{{ route('delivery.conference-sheets.show',[request()->route('tenant'),$sheet]) }}"><span class="fc-card-head"><strong class="fc-number">{{ $sheet->formatted_number }}</strong><span class="fc-badge">{{ $sheet->status->label() }}</span></span><strong>{{ $sheet->recipient_name }}</strong><span class="fc-card-meta">{{ $sheet->period_start->format('d/m/Y') }}–{{ $sheet->period_end->format('d/m/Y') }} · {{ $sheet->distributions_count }} entregas</span></a>@endforeach</div>
        @can('prepare_billing_from_delivery_conference')<form id="billing-form" method="post" action="{{ route('delivery.conference-sheets.prepare-billing',request()->route('tenant')) }}" style="display:flex;justify-content:flex-end;padding:.7rem">@csrf<button class="fc-btn fc-btn-primary" type="submit">Preparar cobrança das selecionadas</button></form>@endcan
        @endif
    </div>
    {{ $sheets->links() }}
</div>
<script>
(()=>{const data=@json($projectData),form=document.getElementById('fc-form'),project=document.getElementById('fc-project'),type=document.getElementById('fc-type'),recipient=document.getElementById('fc-recipient'),mode=document.getElementById('fc-mode');
const render=()=>{const row=data[project?.value]||{},list=type?.value==='organization'?(row.organizations||[]):row.customers||[];recipient.innerHTML='<option value="">Selecione</option>'+list.map(x=>`<option value="${x.id}">${String(x.name).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}</option>`).join('');recipient.disabled=!project.value;mode.innerHTML=type.value==='organization'?'<option value="organization_detailed">Detalhado por cliente</option><option value="organization_consolidated">Consolidado por produto</option>':'<option value="customer">Por produto e unidade</option>';};
project?.addEventListener('change',render);type?.addEventListener('change',render);document.getElementById('fc-new')?.addEventListener('click',()=>form.classList.toggle('open'));document.getElementById('fc-close')?.addEventListener('click',()=>form.classList.remove('open'));render();})();
</script>
@endsection
