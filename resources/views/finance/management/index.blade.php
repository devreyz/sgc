@extends('layouts.bento')

@section('title', $config['label'])
@section('page-title', $config['label'])
@section('page-subtitle', $tenant->name)
@section('user-role', 'Financeiro')
@php
    $bentoNavigation = \App\Support\PortalNavigation::make('finance', 'management', $tenant->slug);
    $clientConfig = [
        'label' => $config['label'],
        'columns' => $config['columns'],
        'fields' => collect($config['fields'])->map(fn ($field) => \Illuminate\Support\Arr::except($field, ['rules']))->all(),
        'writable' => $config['writable'],
        'viewable' => $config['viewable'],
        'printable' => $config['printable'],
    ];
@endphp

@section('content')
@include('finance.partials.styles')
<style>
    .fm-toolbar{display:flex;gap:.55rem;align-items:end;justify-content:space-between;flex-wrap:wrap}.fm-search{display:flex;gap:.45rem;flex:1;max-width:560px}.fm-loading{padding:2rem;text-align:center;color:var(--color-text-secondary)}.fm-row-actions{display:flex;gap:.3rem}.fm-row-actions button{width:34px;height:34px}.fm-dialog{width:min(94vw,720px);max-height:88vh;border:1px solid var(--color-border);border-radius:8px;padding:0;background:var(--color-surface);color:var(--color-text)}.fm-dialog::backdrop{background:rgba(12,20,16,.5);backdrop-filter:blur(2px)}.fm-dialog-body{padding:1rem;overflow:auto;max-height:88vh}.fm-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.fm-form .wide{grid-column:1/-1}.fm-pagination{display:flex;align-items:center;justify-content:space-between;gap:.7rem;margin-top:.8rem}.fm-readonly{padding:.75rem;border:1px solid var(--color-border);border-radius:7px;background:var(--color-surface-soft);font-size:.78rem;color:var(--color-text-secondary)}
    @media(max-width:640px){.fm-search{max-width:none;width:100%}.fm-form{grid-template-columns:1fr}.fm-form .wide{grid-column:auto}.fm-toolbar>.fin-btn{width:100%}.fin-table{min-width:680px}}
</style>
<main class="fin-shell">
    <header class="fin-head"><div><h1>{{ $config['label'] }}</h1><p>Gestao independente do painel administrativo</p></div><div class="fin-actions"><a class="fin-btn" href="{{ route('finance.index', ['tenant' => $tenant->slug]) }}"><i data-lucide="arrow-left"></i> Visao financeira</a></div></header>
    <section class="fin-card">
        <div class="fm-toolbar"><form id="fm-search" class="fm-search"><input class="fin-input" id="fm-query" maxlength="100" placeholder="Buscar nesta lista"><button class="fin-btn" type="submit"><i data-lucide="search"></i><span>Buscar</span></button></form><button class="fin-btn fin-btn-primary" id="fm-create" type="button" hidden><i data-lucide="plus"></i> Novo registro</button></div>
        @if(!$config['writable'])<div class="fm-readonly" style="margin-top:.7rem">Este modulo exibe documentos financeiros congelados. Criacao, faturamento, pagamento, cancelamento e regeneracao usam os fluxos especializados para preservar as distribuicoes e a auditoria.</div>@endif
        <div id="fm-message" style="margin-top:.7rem" hidden></div>
        <div class="fin-table-wrap" style="margin-top:.7rem"><table class="fin-table"><thead><tr id="fm-head"></tr></thead><tbody id="fm-body"><tr><td class="fm-loading">Carregando...</td></tr></tbody></table></div>
        <div class="fm-pagination"><button class="fin-btn" id="fm-prev" type="button"><i data-lucide="chevron-left"></i> Anterior</button><span id="fm-page"></span><button class="fin-btn" id="fm-next" type="button">Proxima <i data-lucide="chevron-right"></i></button></div>
    </section>
</main>
<dialog class="fm-dialog" id="fm-dialog"><div class="fm-dialog-body"><div class="fin-section-title"><h2 id="fm-dialog-title">Novo registro</h2><button class="fin-icon-btn" id="fm-close" type="button" aria-label="Fechar"><i data-lucide="x"></i></button></div><form id="fm-form" class="fm-form"></form><div class="fin-actions" style="justify-content:flex-end;margin-top:1rem"><button class="fin-btn" id="fm-cancel" type="button">Cancelar</button><button class="fin-btn fin-btn-primary" id="fm-save" type="button"><i data-lucide="save"></i> Salvar</button></div></div></dialog>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const config = @json($clientConfig);
    const baseUrl = @json(route('finance.management.data', ['tenant' => $tenant->slug, 'module' => $module]));
    const recordUrl = @json(route('finance.management.show', ['tenant' => $tenant->slug, 'module' => $module, 'record' => '__ID__']));
    const printUrl = @json(route('finance.management.print', ['tenant' => $tenant->slug, 'module' => $module, 'record' => '__ID__']));
    const csrf = @json(csrf_token());
    const state = { page: 1, query: '', rows: [], meta: {}, abilities: {}, editing: null, busy: false };
    const body = document.getElementById('fm-body'), head = document.getElementById('fm-head'), dialog = document.getElementById('fm-dialog'), form = document.getElementById('fm-form');
    const label = key => config.fields[key]?.label || ({movement_date:'Data',description:'Descricao',amount:'Valor',status:'Situacao',type:'Tipo',receipt_year:'Ano',receipt_number:'Numero',issued_at:'Emissao',total_net:'Total liquido',amount_paid:'Pago',service_order_id:'Ordem',payment_date:'Pagamento',final_amount:'Valor final',current_balance:'Saldo atual'}[key] || key.replaceAll('_',' '));
    const format = (key, value) => { if(value === null || value === '') return '-'; if(['amount','paid_amount','total_net','final_amount','current_balance','initial_balance','acquisition_value','current_value'].includes(key)) return new Intl.NumberFormat('pt-BR',{style:'currency',currency:'BRL'}).format(Number(value)); if(value === true || value === 1) return 'Sim'; if(value === false || value === 0) return 'Nao'; return String(value); };
    const notify = (message, error=false) => { const box=document.getElementById('fm-message'); box.hidden=false; box.className='fin-alert'+(error?' fin-alert-error':''); box.textContent=message; };
    async function request(url, options={}) { const response=await fetch(url,{headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},credentials:'same-origin',...options}); const payload=await response.json().catch(()=>({message:'Nao foi possivel concluir a solicitacao.'})); if(!response.ok) throw new Error(Object.values(payload.errors||{})[0]?.[0] || payload.message || 'Nao foi possivel concluir a solicitacao.'); return payload; }
    function renderHead(){ head.replaceChildren(...config.columns.map(key=>{const th=document.createElement('th');th.textContent=label(key);return th;})); const th=document.createElement('th');th.textContent='Acoes';head.append(th); }
    function renderRows(){ body.replaceChildren(); if(!state.rows.length){const tr=document.createElement('tr'),td=document.createElement('td');td.colSpan=config.columns.length+1;td.className='fin-empty';td.textContent='Nenhum registro encontrado.';tr.append(td);body.append(tr);return;} state.rows.forEach(row=>{const tr=document.createElement('tr');config.columns.forEach(key=>{const td=document.createElement('td');td.textContent=format(key,row[key]);tr.append(td);});const td=document.createElement('td'),actions=document.createElement('div');actions.className='fm-row-actions';if(state.abilities.view){const view=document.createElement('a');view.className='fin-icon-btn';view.title='Ver detalhes';view.href=recordUrl.replace('__ID__',row.id);view.innerHTML='<i data-lucide="eye"></i>';actions.append(view);}if(state.abilities.print){const print=document.createElement('a');print.className='fin-icon-btn';print.title='Imprimir';print.target='_blank';print.rel='noopener';print.href=printUrl.replace('__ID__',row.id);print.innerHTML='<i data-lucide="printer"></i>';actions.append(print);}if(state.abilities.update){const edit=document.createElement('button');edit.className='fin-icon-btn';edit.type='button';edit.title='Editar';edit.innerHTML='<i data-lucide="pencil"></i>';edit.onclick=()=>openForm(row);actions.append(edit);}td.append(actions);tr.append(td);body.append(tr);});lucide.createIcons(); }
    async function load(){ body.innerHTML='<tr><td class="fm-loading" colspan="20">Carregando...</td></tr>';try{const result=await request(`${baseUrl}?page=${state.page}&q=${encodeURIComponent(state.query)}`);state.rows=result.data;state.meta=result.meta;state.abilities=result.abilities;document.getElementById('fm-create').hidden=!state.abilities.create;renderRows();document.getElementById('fm-page').textContent=`Pagina ${state.meta.current_page} de ${state.meta.last_page} - ${state.meta.total} registro(s)`;document.getElementById('fm-prev').disabled=state.meta.current_page<=1;document.getElementById('fm-next').disabled=state.meta.current_page>=state.meta.last_page;}catch(error){notify(error.message,true);} }
    function control(key, field, value){const wrap=document.createElement('label');wrap.className='fin-field'+(field.type==='textarea'?' wide':'');const span=document.createElement('span');span.className='fin-label';span.textContent=field.label;let input;if(field.type==='select'){input=document.createElement('select');input.className='fin-select';Object.entries(field.options||{}).forEach(([optionValue,optionLabel])=>{const option=document.createElement('option');option.value=optionValue;option.textContent=optionLabel;input.append(option);});}else if(field.type==='textarea'){input=document.createElement('textarea');input.className='fin-textarea';}else if(field.type==='toggle'){input=document.createElement('input');input.type='checkbox';input.style.width='22px';input.style.height='22px';input.checked=Boolean(value); }else{input=document.createElement('input');input.className='fin-input';input.type=field.type==='money'||field.type==='number'?'number':field.type; if(field.type==='money')input.step='0.01';}input.name=key;if(field.type!=='toggle')input.value=value??'';if(state.editing&&field.immutable_on_update){input.readOnly=true;input.title='Este valor compoe o historico da conta e nao pode ser alterado.';}wrap.append(span,input);return wrap;}
    function openForm(row=null){state.editing=row;form.replaceChildren(...Object.entries(config.fields).map(([key,field])=>control(key,field,row?.[key])));document.getElementById('fm-dialog-title').textContent=row?'Editar registro':'Novo registro';dialog.showModal();}
    async function save(){if(state.busy)return;state.busy=true;document.getElementById('fm-save').disabled=true;const data={};new FormData(form).forEach((value,key)=>data[key]=value);Object.entries(config.fields).forEach(([key,field])=>{if(field.type==='toggle')data[key]=form.elements[key].checked;});try{const url=state.editing?`${baseUrl}/${state.editing.id}`:baseUrl;const result=await request(url,{method:state.editing?'PUT':'POST',body:JSON.stringify(data)});dialog.close();notify(result.message);await load();}catch(error){notify(error.message,true);}finally{state.busy=false;document.getElementById('fm-save').disabled=false;}}
    document.getElementById('fm-search').onsubmit=event=>{event.preventDefault();state.query=document.getElementById('fm-query').value.trim();state.page=1;load();};document.getElementById('fm-create').onclick=()=>openForm();document.getElementById('fm-save').onclick=save;document.getElementById('fm-close').onclick=()=>dialog.close();document.getElementById('fm-cancel').onclick=()=>dialog.close();document.getElementById('fm-prev').onclick=()=>{state.page--;load();};document.getElementById('fm-next').onclick=()=>{state.page++;load();};renderHead();load();
});
</script>
@endsection
