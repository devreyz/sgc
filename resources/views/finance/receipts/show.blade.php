@extends('layouts.bento')

@section('title', $receipt->formatted_number)
@section('page-title', 'Recibo de Recebimento')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Financeiro')
@php
    $bentoNavigation = \App\Support\PortalNavigation::make('finance', 'receipts', $tenant->slug);
@endphp

@section('content')
@include('finance.partials.styles')
<main class="fin-shell">
    <header class="fin-head">
        <div><h1>{{ $receipt->formatted_number }}</h1><p>{{ $receipt->payer_name }} · {{ $receipt->received_on->format('d/m/Y') }}</p></div>
        <div class="fin-actions">
            <a class="fin-btn" href="{{ route('finance.receipts.index',['tenant'=>$tenant->slug]) }}"><i data-lucide="arrow-left"></i> Voltar</a>
            @can('update',$receipt)<a class="fin-btn" href="{{ route('finance.receipts.edit',['tenant'=>$tenant->slug,'financialReceipt'=>$receipt]) }}"><i data-lucide="pencil"></i> Editar</a>@endcan
            @if(!$receipt->isDraft())<a class="fin-btn fin-btn-primary" target="_blank" rel="noopener" href="{{ route('finance.receipts.print',['tenant'=>$tenant->slug,'financialReceipt'=>$receipt]) }}"><i data-lucide="printer"></i> Imprimir</a>@endif
        </div>
    </header>
    @if(session('success'))<div class="fin-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="fin-alert fin-alert-error">{{ $errors->first() }}</div>@endif
    @if($receipt->status->value==='cancelled')<div class="fin-alert fin-alert-error"><strong>Recibo cancelado.</strong> O lançamento original foi estornado. Motivo: {{ $receipt->cancellation_reason }}</div>@endif

    <section class="fin-card">
        <div class="fin-section-title"><h2>Dados do recebimento</h2><span class="fin-badge fin-badge-{{ $receipt->status->value }}">{{ $receipt->status->getLabel() }}</span></div>
        <div class="fin-detail-grid">
            <div class="fin-detail"><span>Pagador</span><strong>{{ $receipt->payer_name }}</strong></div>
            <div class="fin-detail"><span>Documento</span><strong>{{ $receipt->payer_document ?: 'Não informado' }}</strong></div>
            <div class="fin-detail"><span>Meio de pagamento</span><strong>{{ $receipt->payment_method->getLabel() }}</strong></div>
            <div class="fin-detail"><span>Conta de entrada</span><strong>{{ $receipt->bankAccount?->name ?? 'Conta indisponível' }}</strong></div>
            <div class="fin-detail"><span>Data</span><strong>{{ $receipt->received_on->format('d/m/Y') }}</strong></div>
            <div class="fin-detail"><span>Referência</span><strong>{{ $receipt->payment_reference ?: 'Não informada' }}</strong></div>
            <div class="fin-detail"><span>Classificação</span><strong>{{ $receipt->chartAccount ? $receipt->chartAccount->code.' · '.$receipt->chartAccount->name : 'Não informada' }}</strong></div>
            <div class="fin-detail"><span>Recebido por</span><strong>{{ $receipt->issued_by ? $receiverName : 'Ainda não emitido' }}</strong></div>
        </div>
        @if($receipt->purpose)<div class="fin-detail" style="margin-top:.7rem"><span>Referente a</span><strong>{{ $receipt->purpose }}</strong></div>@endif
    </section>

    <section class="fin-card">
        <div class="fin-section-title"><h2>{{ $receipt->items->isNotEmpty() ? 'Itens' : 'Valor informado' }}</h2><strong>R$ {{ number_format((float)$receipt->total_amount,2,',','.') }}</strong></div>
        @if($receipt->items->isNotEmpty())
            <div class="fin-table-wrap"><table class="fin-table"><thead><tr><th>#</th><th>Descrição</th><th>Referência</th><th>Quantidade</th><th>Unidade</th><th>Valor unitário</th><th>Total</th></tr></thead><tbody>
                @foreach($receipt->items as $item)<tr><td>{{ $loop->iteration }}</td><td>{{ $item->description }}</td><td>{{ $item->reference ?: '—' }}</td><td>{{ rtrim(rtrim(number_format((float)$item->quantity,4,',','.'),'0'),',') }}</td><td>{{ $item->unit }}</td><td>R$ {{ number_format((float)$item->unit_price,4,',','.') }}</td><td class="fin-money">R$ {{ number_format((float)$item->total_amount,2,',','.') }}</td></tr>@endforeach
            </tbody></table></div>
        @else
            <p style="margin:0;color:var(--color-text-secondary);font-size:.82rem">Recebimento registrado por referência, sem detalhamento de itens.</p>
        @endif
    </section>

    @if($receipt->isDraft())
        <section class="fin-card"><div class="fin-section-title"><div><h2>Emitir recibo</h2><p style="margin:.25rem 0 0;font-size:.75rem;color:var(--color-text-secondary)">A emissão registra a entrada no caixa e congela os dados.</p></div></div>
            @can('issue',$receipt)<form class="js-lock-form" method="POST" action="{{ route('finance.receipts.issue',['tenant'=>$tenant->slug,'financialReceipt'=>$receipt]) }}">@csrf<button class="fin-btn fin-btn-primary" type="submit"><i data-lucide="badge-check"></i> Confirmar emissão</button></form>@endcan
        </section>
    @elseif($receipt->isIssued())
        @can('cancel',$receipt)<section class="fin-card fin-danger-box"><div class="fin-section-title"><div><h2>Cancelar e estornar</h2><p style="margin:.25rem 0 0;font-size:.75rem;color:var(--color-text-secondary)">O documento permanece no histórico e uma saída de estorno é criada.</p></div></div><button class="fin-btn fin-btn-danger" type="button" onclick="document.getElementById('cancel-receipt-dialog').showModal()"><i data-lucide="ban"></i> Cancelar recibo</button></section>@endcan
    @endif
</main>

<dialog id="cancel-receipt-dialog" style="width:min(92vw,520px);border:1px solid var(--color-border);border-radius:8px;padding:0;background:var(--color-surface);color:var(--color-text)">
    <form class="js-lock-form" method="POST" action="{{ route('finance.receipts.cancel',['tenant'=>$tenant->slug,'financialReceipt'=>$receipt]) }}" style="padding:1rem">@csrf
        <div class="fin-section-title"><h2>Cancelar {{ $receipt->formatted_number }}</h2><button class="fin-icon-btn" type="button" onclick="this.closest('dialog').close()" aria-label="Fechar"><i data-lucide="x"></i></button></div>
        <p style="font-size:.8rem">O valor de R$ {{ number_format((float)$receipt->total_amount,2,',','.') }} será estornado da conta {{ $receipt->bankAccount?->name ?? 'indisponível' }}.</p>
        <div class="fin-field"><label for="reason">Motivo do cancelamento</label><textarea class="fin-textarea" id="reason" name="reason" minlength="10" maxlength="2000" required></textarea></div>
        <div class="fin-actions" style="justify-content:flex-end;margin-top:.8rem"><button class="fin-btn" type="button" onclick="this.closest('dialog').close()">Manter recibo</button><button class="fin-btn fin-btn-danger" type="submit">Confirmar cancelamento</button></div>
    </form>
</dialog>
<script>document.querySelectorAll('.js-lock-form').forEach(form=>form.addEventListener('submit',()=>form.querySelectorAll('button').forEach(button=>button.disabled=true)));</script>
@endsection
