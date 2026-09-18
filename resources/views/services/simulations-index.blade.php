@extends('layouts.bento')
@section('title','Simulador de serviços')
@section('page-title','Laboratório de serviços')
@section('page-subtitle','Teste versões, medições, preços e obrigações sem criar lançamentos reais.')
@section('user-role','Administração · ambiente de teste')
@php($tenantSlug=request()->route('tenant')->slug)
@php($bentoNavigation=\App\Support\PortalNavigation::make('services','simulations',$tenantSlug))

@section('content')
<style>
.sim-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(18rem,.65fr);gap:1rem}.sim-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,15rem),1fr));gap:.8rem}.sim-note{padding:.85rem 1rem;border:1px solid #bfdbfe;border-radius:.8rem;background:#eff6ff;color:#1e3a8a}.sim-field{display:grid;gap:.3rem}.sim-field small{color:#64748b}@media(max-width:850px){.sim-grid{grid-template-columns:1fr}}
</style>
<section class="bento-card col-span-full sim-note">
    <strong>Ambiente seguro de simulação.</strong> O sistema usa o mesmo validador e motor financeiro das OS reais dentro de uma transação descartável. Não gera número oficial, OS, conta a receber, pagamento, despesa ou movimento bancário.
</section>

<div class="col-span-full sim-grid">
<section class="bento-card">
    <h2>Nova simulação</h2>
    <p>Escolha qualquer versão, inclusive rascunhos. O preenchimento automático injeta valores seguros; você pode sobrescrever qualquer campo para testar cenários específicos.</p>
    <form method="post" action="{{route('services.simulations.store',$tenantSlug)}}" style="display:grid;gap:1rem">@csrf
        <label class="sim-field">Nome do cenário <input name="name" maxlength="191" value="{{old('name')}}" placeholder="Ex.: 20 horas com desconto de óleo"></label>
        <label class="sim-field">Versão do serviço
            <select id="simulation-version" name="service_version_id" required>
                <option value="">Selecione</option>
                @foreach($versions as $version)
                    <option value="{{$version->id}}" data-payable="{{$version->payable_enabled?'1':'0'}}" @selected((string)old('service_version_id',request('version'))===(string)$version->id)>{{$version->service->name}} · v{{$version->version}} · {{$version->status==='draft'?'rascunho':($version->status==='published'?'publicada':'encerrada')}}</option>
                @endforeach
            </select>
        </label>
        <label class="sim-field">Prestador usado no cálculo <select id="simulation-provider" name="service_provider_id"><option value="">Usar automaticamente um prestador ativo</option>@foreach($providers as $provider)<option value="{{$provider->id}}" @selected(old('service_provider_id')==$provider->id)>{{$provider->name}}</option>@endforeach</select><small>Necessário quando a versão gera remuneração. Tarifas individuais serão consideradas.</small></label>
        <label style="display:flex;align-items:flex-start;gap:.55rem;padding:.8rem;border:1px solid #dbe4dd;border-radius:.75rem"><input id="auto-fill" type="checkbox" name="auto_fill" value="1" @checked(old('auto_fill','1'))><span><strong>Injetar dados automaticamente</strong><small style="display:block;color:#64748b">Gera números, textos e uma diferença padrão de medidor de 20 unidades. Valores preenchidos abaixo substituem os automáticos.</small></span></label>

        @foreach($versions as $version)
            @php($snapshotFields=collect($version->snapshot()['fields']??[]))
            <div data-simulation-fields="{{$version->id}}" hidden class="sim-fields">
                <div style="grid-column:1/-1"><strong>Dados da execução simulada</strong><p style="margin:.2rem 0;color:#64748b">Campos operacionais alimentam a execução; somente os selecionados pelas fórmulas ou termos financeiros alteram valores.</p></div>
                @foreach($snapshotFields->reject(fn($field)=>in_array($field['type']??null,['image','file','signature'],true)) as $field)
                    @php($type=$field['type']??'text')
                    <label class="sim-field"><span>{{$field['label']}} @if($field['required']??false)<b style="color:#dc2626">*</b>@endif @if($field['unit']??null)<small>({{$field['unit']}})</small>@endif</span>
                        @if($type==='textarea')
                            <textarea name="values[{{$field['key']}}]" disabled>{{old('values.'.$field['key'])}}</textarea>
                        @elseif($type==='boolean')
                            <select name="values[{{$field['key']}}]" disabled><option value="">Automático/vazio</option><option value="1">Sim</option><option value="0">Não</option></select>
                        @elseif($type==='select')
                            <select name="values[{{$field['key']}}]" disabled><option value="">Automático/vazio</option>@foreach(($field['options']??[]) as $key=>$option)<option value="{{is_int($key)?$option:$key}}">{{$option}}</option>@endforeach</select>
                        @else
                            <input name="values[{{$field['key']}}]" disabled value="{{old('values.'.$field['key'])}}" type="{{in_array($type,['integer','decimal','money','quantity','meter'])?'number':($type==='date'?'date':($type==='datetime'?'datetime-local':'text'))}}" @if(in_array($type,['decimal','money','quantity','meter'])) step="any" @endif placeholder="Deixe vazio para valor automático">
                        @endif
                        <small>{{$field['phase']??'execution'}} · chave: {{$field['key']}}{{$field['help']??false?' · '.$field['help']:''}}</small>
                    </label>
                @endforeach
                @if($snapshotFields->whereIn('type',['image','file','signature'])->isNotEmpty())
                    <div style="grid-column:1/-1;padding:.75rem;border:1px dashed #94a3b8;border-radius:.75rem"><strong>Evidências configuradas</strong><p style="margin:.3rem 0 0;color:#64748b">@foreach($snapshotFields->whereIn('type',['image','file','signature']) as $field)<span style="display:inline-block;margin-right:.75rem">{{$field['label']}}{{$field['required']??false?' (obrigatória)':' (opcional)'}}</span>@endforeach</p></div>
                @endif
            </div>
        @endforeach
        <button class="btn btn-primary">Executar simulação</button>
    </form>
</section>

<aside class="bento-card">
    <h2>O que será conferido</h2>
    <ul style="display:grid;gap:.55rem;padding-left:1.2rem"><li>campos obrigatórios e limites;</li><li>diferenças de medidores;</li><li>quantidades independentes da cobrança e remuneração;</li><li>tarifas padrão e individuais;</li><li>adicionais, descontos e percentuais;</li><li>contas a receber e pagar previstas;</li><li>exigências de evidências.</li></ul>
    <p style="color:#64748b">Os cenários expiram automaticamente após 30 dias e podem ser excluídos antes disso.</p>
</aside>
</div>

<section class="bento-card col-span-full">
    <h2>Simulações recentes</h2>
    <div style="overflow:auto"><table style="width:100%"><thead><tr><th>Cenário</th><th>Versão</th><th>Prestador</th><th>Resultado</th><th>Criada em</th><th></th></tr></thead><tbody>
    @forelse($simulations as $simulation)<tr><td><a href="{{route('services.simulations.show',[$tenantSlug,$simulation])}}"><strong>{{$simulation->name}}</strong></a></td><td>{{$simulation->version->service->name}} · v{{$simulation->version->version}} · {{$simulation->version->status}}</td><td>{{$simulation->provider?->name??'—'}}</td><td><span style="color:{{$simulation->status==='success'?'#15803d':'#b91c1c'}}">{{$simulation->status==='success'?'Funcionou':'Requer correção'}}</span></td><td>{{$simulation->created_at->format('d/m/Y H:i')}}</td><td><form method="post" action="{{route('services.simulations.destroy',[$tenantSlug,$simulation])}}" onsubmit="return confirm('Excluir esta simulação?')">@csrf @method('delete')<button class="btn btn-outline">Excluir</button></form></td></tr>
    @empty<tr><td colspan="6">Nenhuma simulação executada.</td></tr>@endforelse
    </tbody></table></div>{{$simulations->links()}}
</section>

<script>document.addEventListener('DOMContentLoaded',()=>{const select=document.getElementById('simulation-version');const sync=()=>{document.querySelectorAll('[data-simulation-fields]').forEach(section=>{const active=section.dataset.simulationFields===select.value;section.hidden=!active;section.querySelectorAll('input,select,textarea').forEach(input=>input.disabled=!active)})};select.addEventListener('change',sync);sync()})</script>
@endsection
