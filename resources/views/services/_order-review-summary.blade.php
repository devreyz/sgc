@if($order->execution?->status === 'submitted')
    <section class="order-review-summary" aria-label="Resumo para conferência" style="padding:.9rem;border:1px solid var(--cm-line,var(--ui-color-border,#d4dfd8));border-radius:12px;margin-block:.75rem">
        <strong>Execução para conferir</strong>
        <p>{{ \App\Support\PdfQuantityFormatter::format($order->execution->quantity) }} {{ $order->execution->unit }} registrados</p>
        @if(auth()->user()->checkPermissionTo('view_service_financials'))
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin-top:.6rem">
                <div><small>Beneficiário · Valor do serviço</small><br>@include('services._order-value', ['valueDirection' => 'receivable'])</div>
                <div><small>Prestador · Remuneração</small><br>@include('services._order-value', ['valueDirection' => 'payable'])</div>
            </div>
        @endif
    </section>
@endif
