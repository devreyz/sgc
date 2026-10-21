@extends('layouts.bento')

@section('title', 'Comprovantes dos membros · '.$billing->formatted_number)
@section('page-title', 'Comprovantes dos membros')
@section('page-subtitle', $tenant->name)
@section('user-role', 'Contabilidade')
@php
    $bentoNavigation = \App\Support\PortalNavigation::make('accounting', 'processes', $tenant->slug);
    $routeArgs = ['tenant' => $tenant->slug, 'receipt' => $billing->id];
    $availableCount = $reasons->filter(fn ($reason) => $reason === null)->count();
@endphp
@push('styles')
    @vite('resources/css/accounting-portal.css')
    <style>
        .member-receipt-selection-total{display:grid;gap:.2rem;min-width:0}.member-receipt-selection-total strong{font-size:1.15rem;color:#166534}.member-receipt-selection-total small{color:#64748b;line-height:1.5;overflow-wrap:anywhere}.member-receipt-group-total{margin-bottom:.7rem;padding:.65rem .8rem;border-radius:10px;background:#f0fdf4}.member-receipt-group-total strong{font-size:.95rem}.member-receipt-toolbar .member-receipt-selection-total{flex:1 1 220px}
        .member-receipt-workspace{display:grid;gap:1rem}.member-receipt-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}.member-receipt-workspace h1{font-size:1.3rem;margin:0}.member-receipt-workspace p{color:var(--text-secondary,#64748b);font-size:.85rem;line-height:1.5}.member-receipt-group{padding:1rem;border:1px solid #dde5ec;border-radius:16px;background:var(--surface,#fff)}.member-receipt-group h2{font-size:1rem;margin:0}.member-receipt-group header{display:flex;justify-content:space-between;gap:.7rem;align-items:center;flex-wrap:wrap;margin-bottom:.8rem}.member-receipt-row{display:flex;align-items:flex-start;gap:.75rem;padding:.85rem 0;border-top:1px solid #e5eaf0}.member-receipt-row input{flex:none;margin-top:.25rem;width:19px;height:19px;accent-color:#6d28d9}.member-receipt-row>span{flex:1;min-width:0}.member-receipt-row small{display:block;margin-top:.25rem;color:#64748b;line-height:1.5}.member-receipt-row.is-unavailable{opacity:.7}.member-receipt-toolbar{position:sticky;bottom:.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.8rem;padding:1rem;border:1px solid #dce3ed;border-radius:14px;background:var(--surface,#fff);box-shadow:0 5px 25px #17255418}.member-receipt-toolbar input{padding:.5rem;border:1px solid #cbd5e1;border-radius:8px}.member-receipt-result{display:flex;gap:.5rem;flex-wrap:wrap}.member-receipt-checkall{display:flex;align-items:center;gap:.5rem;font-size:.85rem}.member-receipt-workspace .acc-error{padding:.8rem}
    </style>
@endpush

@section('content')
<main class="acc-shell member-receipt-workspace">
    <header class="member-receipt-head">
        <div><h1>Comprovantes dos membros</h1><p>Faturamento {{ $billing->formatted_number }} · {{ $availableCount }} distribuição(ões) disponível(is).</p></div>
        <a class="acc-button" href="{{ route('accounting.processes.show', $routeArgs) }}">Voltar ao faturamento</a>
    </header>
    <p>Selecione as distribuições deste faturamento. Será criado um comprovante para cada membro e projeto, com as taxas do membro calculadas separadamente das taxas do cliente.</p>
    @if($billing->status === \App\Enums\CustomerReceiptStatus::DRAFT)
        <div class="acc-alert">Este faturamento está em rascunho. A seleção usa os itens salvos; alterações futuras no faturamento não alteram automaticamente os comprovantes dos membros.</div>
    @endif
    @if(session('success'))<div class="acc-alert" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="acc-error" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('created_member_receipts'))
        <section class="member-receipt-group"><h2>Comprovantes criados</h2><p>Os documentos já estão disponíveis para conferir e imprimir.</p>
            <div class="member-receipt-result">@foreach(session('created_member_receipts') as $created)
                <a class="acc-button" href="{{ $created['url'] }}" data-sgc-pdf target="_blank" rel="noopener">Visualizar {{ $created['number'] }}</a>
            @endforeach</div>
        </section>
    @endif
    <form method="post" action="{{ route('accounting.billings.member-receipts.store', $routeArgs) }}" class="member-receipt-workspace" id="member-receipt-form">
        @csrf
        @if($availableCount)
            <label class="member-receipt-checkall"><input type="checkbox" data-select-all checked> Selecionar todas as disponíveis</label>
        @endif
        @forelse($groups as $group)
            @php($first = $group->first())
            <section class="member-receipt-group">
                <header><div><h2>{{ $names[$first->associate?->user_id] ?? $first->associate?->nickname ?? 'Membro não identificado' }}</h2><small>{{ $first->salesProject?->title }} · {{ $group->count() }} distribuição(ões)</small></div>
                    <label class="member-receipt-checkall"><input type="checkbox" data-select-group checked> Selecionar disponíveis</label>
                </header>
                <div class="member-receipt-selection-total member-receipt-group-total" data-group-selection-total role="status" aria-live="polite">
                    <strong data-selection-gross></strong>
                    <small data-selection-quantities></small>
                </div>
                @foreach($group as $row)
                    @php($reason = $reasons[$row->id])
                    <label class="member-receipt-row {{ $reason ? 'is-unavailable' : '' }}">
                        <input type="checkbox" name="distribution_ids[]" value="{{ $row->id }}" data-member="{{ $row->sales_project_id }}:{{ $row->associate_id }}" data-gross="{{ (float) $row->gross_value > 0 ? $row->gross_value : bcmul((string) $row->quantity, (string) $row->unit_price, 8) }}" data-quantity="{{ $row->quantity }}" data-unit="{{ $row->product?->unit ?: 'un' }}" @disabled($reason !== null) @checked($reason === null && (!session()->hasOldInput('distribution_ids') || in_array($row->id, old('distribution_ids', []))))>
                        <span><strong>{{ $row->product?->name ?? 'Produto não identificado' }}</strong>
                            <small>#{{ $row->id }} · {{ $row->delivery_date?->format('d/m/Y') }} · {{ $row->customer?->trade_name ?: $row->customer?->name }}</small>
                            <small>{{ number_format((float) $row->quantity, 3, ',', '.') }} {{ $row->product?->unit }} × R$ {{ number_format((float) $row->unit_price, 2, ',', '.') }} · Bruto R$ {{ number_format((float) $row->gross_value, 2, ',', '.') }}</small>
                            @if($reason)<small>{{ $reason }}</small>@endif
                            @if($row->associate_receipt_id)
                                <a class="acc-button" data-sgc-pdf target="_blank" rel="noopener" href="{{ route('accounting.billings.member-receipts.print', $routeArgs + ['associateReceipt' => $row->associate_receipt_id]) }}">Visualizar comprovante do membro</a>
                            @endif
                        </span>
                    </label>
                @endforeach
            </section>
        @empty
            <div class="acc-alert">Nenhuma distribuição ativa disponível neste faturamento.</div>
        @endforelse
        @if($availableCount)
            <footer class="member-receipt-toolbar">
                <div class="member-receipt-selection-total" role="status" aria-live="polite">
                    <small data-selection-count></small>
                    <strong data-selection-gross></strong>
                    <small data-selection-quantities></small>
                    <small>Valor bruto entregue, antes das taxas do membro.</small>
                </div>
                <label>Data de emissão <input type="date" name="issued_at" value="{{ old('issued_at', today()->format('Y-m-d')) }}" required></label>
                <button type="submit" class="acc-button primary" data-create-receipts>Criar comprovantes selecionados</button>
            </footer>
        @endif
    </form>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('member-receipt-form');
    if (!form) return;
    const available = scope => [...scope.querySelectorAll('input[name="distribution_ids[]"]:not(:disabled)')];
    const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    const quantity = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 4 });
    const renderTotals = (target, selected) => {
        if (!target) return;
        let gross = 0;
        const quantities = new Map();
        selected.forEach(input => {
            // As distribuições guardam quatro casas decimais. Somar em escala
            // inteira evita acumular erros binários na apresentação do total.
            gross += Math.round(Number(input.dataset.gross || 0) * 10000);
            const unit = input.dataset.unit || 'un';
            quantities.set(unit, (quantities.get(unit) || 0) + Math.round(Number(input.dataset.quantity || 0) * 10000));
        });
        target.querySelector('[data-selection-gross]').textContent = `Bruto selecionado: ${money.format(gross / 10000)}`;
        target.querySelector('[data-selection-quantities]').textContent = quantities.size
            ? `Quantidade: ${[...quantities].map(([unit, amount]) => `${quantity.format(amount / 10000)} ${unit}`).join(' · ')}`
            : 'Nenhuma distribuição selecionada';
    };
    const sync = () => {
        const selected = available(form).filter(input => input.checked);
        renderTotals(form.querySelector('.member-receipt-toolbar'), selected);
        form.querySelectorAll('[data-group-selection-total]').forEach(target => {
            renderTotals(target, available(target.closest('section')).filter(input => input.checked));
        });
        const counter = form.querySelector('[data-selection-count]');
        if (counter) counter.textContent = `${selected.length} distribuição(ões) · ${new Set(selected.map(input => input.dataset.member)).size} comprovante(s)`;
        const button = form.querySelector('[data-create-receipts]');
        if (button) button.disabled = !selected.length;
        form.querySelectorAll('[data-select-all], [data-select-group]').forEach(input => {
            const rows = available(input.hasAttribute('data-select-group') ? input.closest('section') : form);
            input.disabled = !rows.length;
            input.checked = rows.length > 0 && rows.every(row => row.checked);
            input.indeterminate = rows.some(row => row.checked) && !input.checked;
        });
    };
    form.addEventListener('change', event => {
        if (event.target.matches('[data-select-all], [data-select-group]')) {
            const scope = event.target.hasAttribute('data-select-group') ? event.target.closest('section') : form;
            available(scope).forEach(input => input.checked = event.target.checked);
        }
        sync();
    });
    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-create-receipts]');
        if (button) { button.disabled = true; button.textContent = 'Criando comprovantes…'; }
    });
    sync();
})();
</script>
@endpush
