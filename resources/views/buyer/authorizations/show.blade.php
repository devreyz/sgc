@extends('layouts.bento')

@section('title', 'Análise da cobrança')
@section('page-title', 'Análise da cobrança')
@section('user-role', $organization->short_name ?: $organization->name)

@php
    $routeTenant = request()->route('tenant');
    $tenantSlug = is_object($routeTenant) ? $routeTenant->slug : $routeTenant;
    $bentoNavigation = \App\Support\PortalNavigation::make('buyer', 'authorizations', $tenantSlug);
    $money = fn($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $periodFrom = data_get($snapshot, 'identity.period.from');
    $periodTo = data_get($snapshot, 'identity.period.to');
    $periodLabel = ($periodFrom ? \Illuminate\Support\Carbon::parse($periodFrom)->format('d/m/Y') : '—')
        .' a '.($periodTo ? \Illuminate\Support\Carbon::parse($periodTo)->format('d/m/Y') : '—');
@endphp

@push('styles')
<style>
.bas-shell{display:grid;gap:.8rem;grid-column:1/-1}.bas-head,.bas-panel{border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);overflow:hidden}.bas-head{display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:.9rem;border-left:4px solid var(--color-primary-dark)}.bas-head h1{margin:0;font-size:1.15rem}.bas-head p{margin:.2rem 0 0;color:var(--color-text-secondary);font-size:.75rem}.bas-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));border-bottom:1px solid var(--color-border)}.bas-data{padding:.7rem;border-right:1px solid var(--color-border)}.bas-data:last-child{border-right:0}.bas-data span{display:block;color:var(--color-text-secondary);font-size:.64rem}.bas-data strong{display:block;margin-top:.15rem;font-size:.76rem}.bas-table-wrap{overflow-x:auto}.bas-table{width:100%;border-collapse:collapse;font-size:.73rem}.bas-table th,.bas-table td{padding:.62rem .7rem;border-bottom:1px solid var(--color-border);vertical-align:middle}.bas-table th{background:var(--color-surface-soft);font-size:.63rem;text-align:left;text-transform:uppercase;white-space:nowrap}.bas-total{display:flex;justify-content:flex-end;gap:1.2rem;padding:.75rem;font-size:.76rem}.bas-total strong{font-size:.9rem}.bas-actions{display:flex;align-items:flex-start;gap:.7rem;padding:.8rem}.bas-btn{display:inline-flex;min-height:42px;align-items:center;justify-content:center;padding:.55rem .75rem;border:1px solid var(--color-border);border-radius:7px;background:var(--color-surface);color:var(--color-text);font:inherit;font-size:.75rem;font-weight:800;text-decoration:none;cursor:pointer}.bas-btn-primary{border-color:var(--color-primary-dark);background:var(--color-primary-dark);color:#fff}.bas-alert{padding:.7rem .8rem;border:1px solid var(--color-border);border-radius:7px;font-size:.75rem}.bas-alert-error{border-color:#efb8bd;background:#fff5f5;color:var(--color-danger)}.bas-subsection{padding:.75rem;border-top:1px solid var(--color-border)}.bas-subsection h2{margin:0 0 .55rem;font-size:.78rem}.bas-list{display:grid;gap:.4rem;margin:0;padding:0;list-style:none}.bas-list li{display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:.45rem 0;border-bottom:1px solid var(--color-border);font-size:.72rem}.bas-list li:last-child{border-bottom:0}.bas-dialog{width:min(92vw,460px);padding:0;border:1px solid var(--color-border);border-radius:8px;background:var(--color-surface);color:var(--color-text);box-shadow:0 18px 55px rgba(0,0,0,.24)}.bas-dialog::backdrop{background:rgba(15,23,42,.55);backdrop-filter:blur(2px)}.bas-dialog-head,.bas-dialog-body,.bas-dialog-actions{padding:.8rem}.bas-dialog-head{border-bottom:1px solid var(--color-border)}.bas-dialog-head h2{margin:0;font-size:.95rem}.bas-dialog-body{display:grid;gap:.65rem;font-size:.76rem}.bas-dialog-body textarea{width:100%;min-height:96px;padding:.6rem;border:1px solid var(--color-border);border-radius:7px;background:var(--color-surface);color:var(--color-text);font:inherit}.bas-dialog-facts{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}.bas-dialog-facts div{padding:.55rem;background:var(--color-surface-soft);border-radius:6px}.bas-dialog-facts span{display:block;color:var(--color-text-secondary);font-size:.65rem}.bas-dialog-actions{display:flex;justify-content:flex-end;gap:.5rem;border-top:1px solid var(--color-border)}
@media(max-width:720px){.bas-head,.bas-actions{align-items:stretch;flex-direction:column}.bas-grid{grid-template-columns:1fr 1fr}.bas-data:nth-child(2){border-right:0}.bas-data:nth-child(-n+2){border-bottom:1px solid var(--color-border)}.bas-actions .bas-btn{width:100%}.bas-total{display:grid;grid-template-columns:1fr 1fr}.bas-list li{align-items:flex-start;flex-direction:column}.bas-dialog-facts{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<main class="bas-shell">
    <header class="bas-head">
        <div><h1>{{ data_get($snapshot, 'identity.receipt.number') }}</h1><p>Versão {{ $round->sequence }} enviada em {{ $round->sent_at?->format('d/m/Y H:i') }}</p></div>
        <a class="bas-btn" href="{{ route('buyer.authorizations.index', ['tenant' => $tenantSlug, 'organization' => $organization->id]) }}">Voltar</a>
    </header>
    @if(session('success'))<div class="bas-alert">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="bas-alert bas-alert-error">{{ $errors->first() }}</div>@endif

    <section class="bas-panel">
        <div class="bas-grid">
            <div class="bas-data"><span>Projeto</span><strong>{{ data_get($snapshot, 'identity.project.name') }}</strong></div>
            <div class="bas-data"><span>Período</span><strong>{{ $periodLabel }}</strong></div>
            <div class="bas-data"><span>Valor</span><strong>{{ $money(data_get($snapshot, 'totals.net')) }}</strong></div>
            <div class="bas-data"><span>Situação</span><strong>{{ $round->status->label() }}</strong></div>
        </div>
        <div class="bas-table-wrap"><table class="bas-table"><thead><tr><th>Data</th><th>Unidade</th><th>Produto</th><th>Quantidade</th><th>Valor unitário</th><th>Valor</th></tr></thead><tbody>
        @foreach(data_get($snapshot, 'lines', []) as $line)
            <tr>
                <td>{{ data_get($line, 'delivery_date') ? \Illuminate\Support\Carbon::parse(data_get($line, 'delivery_date'))->format('d/m/Y') : '—' }}</td>
                <td>{{ data_get($line, 'customer.name') }}</td>
                <td>{{ data_get($line, 'product.name') }}</td>
                <td>{{ number_format((float) data_get($line, 'quantity'), 3, ',', '.') }} {{ data_get($line, 'product.unit') }}</td>
                <td>{{ $money(data_get($line, 'unit_price')) }}</td>
                <td>{{ $money(data_get($line, 'net')) }}</td>
            </tr>
        @endforeach
        </tbody></table></div>
        <div class="bas-total"><span>Bruto <strong>{{ $money(data_get($snapshot, 'totals.gross')) }}</strong></span><span>Taxas <strong>{{ $money(data_get($snapshot, 'totals.fees')) }}</strong></span><span>Total <strong>{{ $money(data_get($snapshot, 'totals.net')) }}</strong></span></div>

        @if(data_get($snapshot, 'fees.calculated', []))
        <div class="bas-subsection"><h2>Taxas aplicadas</h2><ul class="bas-list">
            @foreach(data_get($snapshot, 'fees.calculated', []) as $fee)
                <li><span>{{ data_get($fee, 'name') }} · {{ data_get($fee, 'type') === 'percentage' ? number_format((float) data_get($fee, 'rate'), 2, ',', '.').'%' : $money(data_get($fee, 'rate')) }}</span><strong>{{ $money(data_get($fee, 'amount')) }}</strong></li>
            @endforeach
        </ul></div>
        @endif
        @if(data_get($snapshot, 'document.notes'))
            <div class="bas-subsection"><h2>Observações</h2><p style="margin:0;font-size:.74rem">{{ data_get($snapshot, 'document.notes') }}</p></div>
        @endif
        @if(data_get($snapshot, 'document.attachments', []))
        <div class="bas-subsection"><h2>Anexos desta versão</h2><ul class="bas-list">
            @foreach(data_get($snapshot, 'document.attachments', []) as $attachment)
                <li><span>{{ data_get($attachment, 'name') }}</span><a class="bas-btn" href="{{ route('buyer.authorizations.attachments.download', ['tenant' => $tenantSlug, 'billingAuthorization' => $round->id, 'document' => data_get($attachment, 'id')]) }}">Abrir</a></li>
            @endforeach
        </ul></div>
        @endif
    </section>

    @if($canRespond)
    <section class="bas-panel bas-actions">
        <button class="bas-btn bas-btn-primary" type="button" data-open-dialog="authorize-dialog">Autorizar faturamento</button>
        <button class="bas-btn" type="button" data-open-dialog="correction-dialog">Solicitar correção</button>
    </section>
    @elseif($round->response_message)
    <section class="bas-panel bas-subsection"><h2>Resposta registrada</h2><p style="margin:0">{{ $round->response_message }}</p></section>
    @endif

    @if($canRespond)
    <dialog class="bas-dialog" id="authorize-dialog">
        <div class="bas-dialog-head"><h2>Autorizar faturamento?</h2></div>
        <form method="POST" action="{{ route('buyer.authorizations.authorize', ['tenant' => $tenantSlug, 'billingAuthorization' => $round->id]) }}">@csrf
            <div class="bas-dialog-body"><div class="bas-dialog-facts"><div><span>Valor</span><strong>{{ $money(data_get($snapshot, 'totals.net')) }}</strong></div><div><span>Período</span><strong>{{ $periodLabel }}</strong></div></div></div>
            <div class="bas-dialog-actions"><button class="bas-btn" type="button" data-close-dialog>Voltar</button><button class="bas-btn bas-btn-primary" type="submit">Autorizar</button></div>
        </form>
    </dialog>
    <dialog class="bas-dialog" id="correction-dialog">
        <div class="bas-dialog-head"><h2>Solicitar correção</h2></div>
        <form method="POST" action="{{ route('buyer.authorizations.request-correction', ['tenant' => $tenantSlug, 'billingAuthorization' => $round->id]) }}">@csrf
            <div class="bas-dialog-body"><label for="reason">O que precisa ser corrigido?</label><textarea id="reason" name="reason" minlength="5" maxlength="1000" required></textarea></div>
            <div class="bas-dialog-actions"><button class="bas-btn" type="button" data-close-dialog>Voltar</button><button class="bas-btn bas-btn-primary" type="submit">Enviar solicitação</button></div>
        </form>
    </dialog>
    @endif
</main>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-open-dialog]').forEach((button) => button.addEventListener('click', () => {
    document.getElementById(button.dataset.openDialog)?.showModal();
}));
document.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => {
    button.closest('dialog')?.close();
}));
</script>
@endpush
