
@extends('layouts.campo')
@section('title', 'Despesa de serviço')
@section('page-title', 'Despesa')

@section('content')
@php
    $base = $management ? 'services.management.expenses' : 'provider.expenses';
    $url = route($base.'.manage', ['tenant' => $tenant->slug, 'expense' => $record->id]);
@endphp
<style>
    .expense-detail { grid-column:1/-1; width:min(100%,65rem); margin-inline:auto; display:grid; gap:1rem; min-width:0; }
    .expense-detail h1 { font-size:clamp(1.45rem,4vw,2.1rem); font-weight:780; overflow-wrap:anywhere; }
    .expense-detail h2 { font-size:1rem; font-weight:750; }
    .expense-detail .detail-panel { background:var(--ui-color-surface); border:1px solid var(--ui-color-border); padding:1rem; border-radius:1rem; display:grid; gap:.65rem; }
    .expense-detail .detail-form { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.65rem; }
    .expense-detail .detail-full { grid-column:1/-1; }
    .expense-detail label { display:grid; gap:.3rem; font-size:.85rem; font-weight:650; }
    .expense-detail :is(input,textarea,select) { min-height:44px; padding:.55rem; border:1px solid var(--ui-color-border); border-radius:.6rem; color:var(--ui-color-text); background:var(--ui-color-surface-soft); width:100%; }
    .expense-detail .detail-actions { display:flex; flex-wrap:wrap; gap:.55rem; align-items:center; }
    .expense-detail .detail-button { display:inline-flex; align-items:center; min-height:44px; gap:.4rem; padding:.5rem .8rem; border:1px solid var(--ui-color-border); border-radius:.65rem; background:var(--ui-color-surface-soft); font-size:.85rem; font-weight:700; }
    .expense-detail .detail-primary { background:var(--sgc-accent); color:var(--sgc-on-accent); border-color:transparent; }
    .expense-detail .detail-danger,.expense-detail .detail-error { color:var(--ui-color-danger-strong); }
    .expense-detail .detail-meta { color:var(--ui-color-text-secondary); overflow-wrap:anywhere; }
    .expense-detail .detail-link { color:var(--ui-color-primary-strong); text-decoration:underline; overflow-wrap:anywhere; }
    .expense-detail .expense-files { display:grid; gap:.55rem; margin-bottom:.55rem; }
    .expense-detail .expense-attachment-preview { display:grid; gap:.3rem; max-width:12rem; overflow-wrap:anywhere; padding:.5rem; background:var(--ui-color-surface-soft); border-radius:.55rem; font-size:.78rem; }
    .expense-detail .expense-attachment-preview button { color:var(--ui-color-danger-strong); text-align:left; min-height:32px; }
    @media(max-width:520px) { .expense-detail .detail-form { grid-template-columns:1fr; } }
</style>
<section class="expense-detail">
    <div>
        <a class="detail-link" href="{{ route($base, ['tenant' => $tenant->slug]) }}">Voltar às despesas</a>
        <h1>{{ $record->description }}</h1>
        <p class="detail-meta">{{ $record->status->getLabel() }} · R$ {{ number_format($record->total_amount, 2, ',', '.') }}
            @if($record->serviceProvider) · {{ $record->serviceProvider->name }} @endif
        </p>
    </div>

    @if($errors->any())
        <div class="detail-panel detail-error" role="alert">
            @foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif

    <section class="detail-panel">
        <h2>Dados da despesa</h2>
        @if($editable)
            <form class="detail-form" action="{{ $url }}" method="post">
                @csrf @method('PATCH')
                <input type="hidden" name="action" value="edit">
                <label class="detail-full">Descrição<input name="description" required maxlength="191" value="{{ old('description', $record->description) }}"></label>
                <label>Valor (R$)<input name="amount" required type="number" min=".01" step=".01" inputmode="decimal" value="{{ old('amount', $record->amount) }}"></label>
                <label>Número do documento<input name="document_number" maxlength="80" value="{{ old('document_number', $record->document_number) }}"></label>
                <label>Data<input type="date" name="date" required value="{{ old('date', $record->date?->toDateString()) }}"></label>
                <label>Vencimento<input type="date" name="due_date" required value="{{ old('due_date', $record->due_date?->toDateString()) }}"></label>
                <label class="detail-full">Observações<textarea name="notes" maxlength="2000" rows="2">{{ old('notes', $record->notes) }}</textarea></label>
                <div class="detail-actions detail-full"><button type="submit" class="detail-button detail-primary">Salvar alterações</button></div>
            </form>
        @else
            <p class="detail-meta">Data {{ $record->date?->format('d/m/Y') ?? 'sem registro' }} · Vencimento {{ $record->due_date?->format('d/m/Y') ?? 'sem registro' }}</p>
            @if($record->document_number)<p>{{ $record->document_number }}</p>@endif
            @if($record->notes)<p>{{ $record->notes }}</p>@endif
        @endif
        @if($record->documents->isNotEmpty())
            <h2>Comprovantes</h2>
            @foreach($record->documents as $document)
                <a class="detail-link" target="_blank" rel="noopener" href="{{ route($base.'.documents.show', ['tenant' => $tenant->slug, 'document' => $document->id]) }}">{{ $document->original_name }}</a>
            @endforeach
        @endif
        @if($record->status->value !== 'cancelled' && $record->documents->count() < 5)
            <form action="{{ $url }}" method="post" enctype="multipart/form-data">
                @csrf @method('PATCH')
                @include('provider._expense-attachments', ['attachmentLimit' => 5 - $record->documents->count()])
                <button class="detail-button" type="submit" name="action" value="attach">Adicionar comprovantes</button>
            </form>
        @endif
    </section>

    <section class="detail-panel">
        <h2>Ordem de serviço</h2>
        @if($order)
            <a class="detail-link" href="{{ route($management ? 'services.management.show' : 'provider.orders.show', ['tenant' => $tenant->slug, 'order' => $order->id]) }}">{{ $order->number }}</a>
        @else
            <p class="detail-meta">Sem ordem vinculada.</p>
        @endif
        @if($resource)
            <p class="detail-meta">{{ $resource->effect === 'deduct_from_receivable' ? 'Abatimento no valor a receber' : 'Recurso associado à despesa' }}</p>
        @elseif($canChangeLink && $order && (!$provider || (int) $order->service_provider_id === (int) $provider->id))
            <form action="{{ $url }}" method="post">
                @csrf @method('PATCH')
                <button class="detail-button" type="submit" name="action" value="unlink">Cancelar vínculo</button>
            </form>
        @endif
        @if($canLink)
            <form class="detail-form" method="get" action="{{ route($base.'.show', ['tenant' => $tenant->slug, 'expense' => $record->id]) }}">
                <label>Buscar ordem
                    <input type="search" name="ordem" value="{{ $orderSearch }}" maxlength="80" placeholder="Número da OS ou beneficiário">
                </label>
                <div class="detail-actions self-end"><button class="detail-button" type="submit">Buscar</button></div>
            </form>
            @if($orders->isEmpty())
                <p class="detail-meta">Nenhuma ordem disponível para vincular.</p>
            @endif
        @endif
        @if($canLink && $orders->isNotEmpty())
            <form class="detail-form" action="{{ $url }}" method="post">
                @csrf @method('PATCH')
                <label class="detail-full">Vincular a uma ordem
                    <select required name="service_order_id">
                        <option value="">Escolha uma ordem</option>
                        @foreach($orders as $option)
                            <option value="{{ $option->id }}" @selected(old('service_order_id') == $option->id)>{{ $option->number }} · {{ $option->beneficiary_snapshot['name'] ?? 'Ordem de serviço' }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="detail-actions detail-full"><button class="detail-button" type="submit" name="action" value="link">Vincular despesa</button></div>
            </form>
        @endif
        @if($canConvert && $orders->contains('execution_exists', true))
            <form class="detail-form" action="{{ $url }}" method="post">
                @csrf @method('PATCH')
                <label class="detail-full">Abater do valor a receber
                    <select required name="service_order_id">
                        <option value="">Escolha uma ordem</option>
                        @foreach($orders->where('execution_exists', true) as $option)
                            <option value="{{ $option->id }}" @selected(old('service_order_id', $order?->id) == $option->id)>{{ $option->number }} · {{ $option->beneficiary_snapshot['name'] ?? 'Ordem de serviço' }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="detail-actions detail-full">
                    <button class="detail-button" type="submit" name="action" value="convert" onclick="return confirm('Registrar esta despesa como abatimento no valor a receber da ordem?')">Transformar em recurso</button>
                </div>
            </form>
        @endif
    </section>

    @if($editable)
        <form action="{{ $url }}" method="post" class="detail-actions">
            @csrf @method('PATCH')
            <button class="detail-button detail-danger" type="submit" name="action" value="delete" onclick="return confirm('Excluir esta despesa? Os arquivos permanecem no Drive.')">Excluir despesa</button>
        </form>
    @endif
</section>
@endsection
