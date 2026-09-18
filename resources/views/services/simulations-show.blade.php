@extends('layouts.bento')
@section('title','Resultado da simulação')
@section('page-title',$simulation->name)
@section('page-subtitle','Resultado descartável do motor de serviços; nenhum lançamento real foi criado.')
@section('user-role','Administração · ambiente de teste')
@php($tenantSlug=request()->route('tenant')->slug)
@php($bentoNavigation=\App\Support\PortalNavigation::make('services','simulations',$tenantSlug))
@php($result=$simulation->result??[])
@php($diagnostics=$simulation->diagnostics??[])
@section('content')
<section class="bento-card col-span-full" style="border-color:{{$simulation->status==='success'?'#86efac':'#fecaca'}};background:{{$simulation->status==='success'?'#f0fdf4':'#fef2f2'}}">
    <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;flex-wrap:wrap"><div><h2>{{$simulation->status==='success'?'Simulação concluída':'Configuração requer correção'}}</h2><p>{{$simulation->version->service->name}} · versão {{$simulation->version->version}} ({{$simulation->version->status}}) · {{$simulation->provider?->name??'sem prestador'}}</p></div><div style="display:flex;gap:.5rem"><a class="btn btn-outline" href="{{route('services.simulations.index',[$tenantSlug,'version'=>$simulation->service_version_id])}}">Nova simulação</a><form method="post" action="{{route('services.simulations.destroy',[$tenantSlug,$simulation])}}">@csrf @method('delete')<button class="btn btn-outline">Excluir</button></form></div></div>
</section>

@if($simulation->status==='error')
<section class="bento-card col-span-full"><h2>Problemas encontrados</h2><ul style="color:#b91c1c">@foreach(\Illuminate\Support\Arr::flatten($diagnostics['errors']??[]) as $error)<li>{{$error}}</li>@endforeach</ul><p>Corrija a versão do serviço e execute novamente. A tentativa temporária foi revertida integralmente.</p></section>
@else
<section class="bento-card col-span-full"><h2>Resumo financeiro previsto</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:1rem"><div><small>Quantidade operacional</small><strong style="display:block;font-size:1.5rem">{{number_format((float)($result['quantity']??0),4,',','.')}} {{$result['unit']??''}}</strong></div><div><small>Organização recebe</small><strong style="display:block;font-size:1.5rem;color:#15803d">R$ {{number_format((float)($result['receivable_total']??0),2,',','.')}}</strong></div><div><small>Prestador recebe</small><strong style="display:block;font-size:1.5rem;color:#0369a1">R$ {{number_format((float)($result['payable_total']??0),2,',','.')}}</strong></div></div></section>

@foreach(['receivable'=>'Composição da cobrança','payable'=>'Composição da remuneração'] as $direction=>$label)
<section class="bento-card col-span-full"><h2>{{$label}}</h2><div style="overflow:auto"><table style="width:100%"><thead><tr><th>Descrição</th><th>Origem</th><th>Quantidade</th><th>Valor unitário</th><th>Efeito</th><th>Total</th><th>Fórmula</th></tr></thead><tbody>@forelse($result[$direction]??[] as $line)<tr><td>{{$line['description']}}</td><td>{{$line['source_type']??'—'}}</td><td>{{number_format((float)($line['quantity']??0),4,',','.')}} {{$line['unit']??''}}</td><td>R$ {{number_format((float)($line['unit_price']??0),2,',','.')}}</td><td>{{($line['financial_effect']??'add')==='subtract'?'Desconto':'Acréscimo'}}</td><td>R$ {{number_format((float)($line['amount']??0),2,',','.')}}</td><td>{{data_get($line,'rule_snapshot.formula','—')}}</td></tr>@empty<tr><td colspan="7">Esta versão não gera valores neste lado.</td></tr>@endforelse</tbody></table></div></section>
@endforeach

<section class="bento-card col-span-full"><h2>Obrigações que seriam geradas</h2><div style="display:grid;gap:.5rem">@forelse($result['expected_obligations']??[] as $obligation)<div style="display:flex;justify-content:space-between;padding:.75rem;border:1px solid #e2e8f0;border-radius:.7rem"><span>{{$obligation['label']}}</span><strong>R$ {{number_format((float)$obligation['amount'],2,',','.')}}</strong></div>@empty<p>Nenhuma obrigação seria gerada.</p>@endforelse</div></section>
@endif

<section class="bento-card col-span-full"><h2>Dados injetados</h2><div style="overflow:auto"><table style="width:100%"><thead><tr><th>Campo</th><th>Valor</th></tr></thead><tbody>@foreach($simulation->values as $key=>$value)@php($field=$simulation->version->fields->firstWhere('key',$key))<tr><td>{{$field?->label??\Illuminate\Support\Str::headline($key)}} <small>({{$key}})</small></td><td>{{is_array($value)?json_encode($value,JSON_UNESCAPED_UNICODE):$value}}</td></tr>@endforeach</tbody></table></div></section>

@if($diagnostics['required_evidence']??false)<section class="bento-card col-span-full"><h2>Evidências exigidas na execução real</h2><ul>@foreach($diagnostics['required_evidence'] as $evidence)<li>{{$evidence['label']}} · etapa {{$evidence['phase']}}{{($evidence['conditional']??false)?' · exigida quando a regra financeira produzir valor':''}}</li>@endforeach</ul><p style="color:#64748b">O simulador valida a configuração da exigência, mas não armazena arquivos de teste.</p></section>@endif
@endsection
