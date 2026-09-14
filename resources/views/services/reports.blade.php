@extends('layouts.bento')
@section('title','Prestação de contas') @section('page-title','Prestação de contas de serviços') @section('user-role','Workspace')
@php($tenantSlug=request()->route('tenant')->slug) @php($bentoNavigation=\App\Support\PortalNavigation::make('services','reports',$tenantSlug))
@section('content')
<section class="bento-card col-span-full">
<form style="display:flex;gap:.5rem;flex-wrap:wrap">
<label>De<input type="date" name="from" value="{{$summary['from']}}"></label>
<label>Até<input type="date" name="to" value="{{$summary['to']}}"></label>
<label>Prestador<select name="provider_id"><option value="">Todos</option>@foreach($providers as $provider)<option value="{{$provider->id}}" @selected(request('provider_id') == $provider->id)>{{$provider->name}}</option>@endforeach</select></label>
<label>Serviço<select name="service_id"><option value="">Todos</option>@foreach($services as $service)<option value="{{$service->id}}" @selected(request('service_id') == $service->id)>{{$service->name}}</option>@endforeach</select></label>
<label>Equipamento<select name="asset_id"><option value="">Todos</option>@foreach($assets as $asset)<option value="{{$asset->id}}" @selected(request('asset_id') == $asset->id)>{{$asset->name}}</option>@endforeach</select></label>
<button class="btn btn-primary">Gerar prestação</button></form>
<form method="post" action="{{route('services.management.reports.document',$tenantSlug)}}">@csrf<input type="hidden" name="from" value="{{$summary['from']}}"><input type="hidden" name="to" value="{{$summary['to']}}"><input type="hidden" name="provider_id" value="{{request('provider_id')}}"><input type="hidden" name="service_id" value="{{request('service_id')}}"><input type="hidden" name="asset_id" value="{{request('asset_id')}}"><button class="btn btn-outline">Baixar PDF detalhado</button></form>
@include('services._accountability')
</section>
@endsection
