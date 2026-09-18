@extends('layouts.bento')
@section('title','Despesas de serviços')
@section('page-title','Despesas de serviços')
@section('user-role','Operação de serviços')
@php($tenantSlug=request()->route('tenant')->slug)
@php($bentoNavigation=\App\Support\PortalNavigation::make('provider','expenses',$tenantSlug))
@section('content')
<section class="bento-card col-span-full">
    <h2>Registrar despesa</h2>
    <p style="color:#64748b">A despesa pode ser vinculada a uma OS ou registrada como despesa geral do módulo de serviços. O pagamento e o saldo bancário continuam sob controle do motor financeiro.</p>
    <form method="post" action="{{route('provider.expenses.store',$tenantSlug)}}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:.75rem">@csrf
        <label>Descrição<input name="description" required maxlength="191" value="{{old('description')}}"></label>
        <label>Valor<input type="number" name="amount" min="0.01" step="0.01" required value="{{old('amount')}}"></label>
        <label>Data<input type="date" name="date" required value="{{old('date',now()->toDateString())}}"></label>
        <label>Vencimento<input type="date" name="due_date" required value="{{old('due_date',now()->toDateString())}}"></label>
        <label>Ordem de serviço (opcional)<select name="service_order_id"><option value="">Despesa geral de serviços</option>@foreach($orders as $order)<option value="{{$order->id}}" @selected(old('service_order_id')==$order->id)>{{$order->number}}</option>@endforeach</select></label>
        <label>Plano de contas (opcional)<select name="chart_account_id"><option value="">Não informado</option>@foreach($accounts as $account)<option value="{{$account->id}}" @selected(old('chart_account_id')==$account->id)>{{$account->name}}</option>@endforeach</select></label>
        <label>Nº documento<input name="document_number" maxlength="80" value="{{old('document_number')}}"></label>
        <label style="grid-column:1/-1">Observações<textarea name="notes" maxlength="2000">{{old('notes')}}</textarea></label>
        <div style="grid-column:1/-1"><button class="btn btn-primary">Registrar despesa pendente</button></div>
    </form>
</section>
<section class="bento-card col-span-full"><h2>Despesas registradas</h2><div style="overflow:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th>Data</th><th>Origem</th><th>Descrição</th><th>Valor</th><th>Pago</th><th>Situação</th></tr></thead><tbody>@forelse($expenses as $expense)<tr><td>{{$expense->date?->format('d/m/Y')}}</td><td>{{$expense->expenseable?->number??'Serviços gerais'}}</td><td>{{$expense->description}}</td><td>R$ {{number_format($expense->total_amount,2,',','.')}}</td><td>R$ {{number_format((float)$expense->paid_amount,2,',','.')}}</td><td>{{$expense->status->getLabel()}}</td></tr>@empty<tr><td colspan="6">Nenhuma despesa de serviço registrada.</td></tr>@endforelse</tbody></table></div>{{$expenses->links()}}</section>
@endsection
