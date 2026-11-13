@extends('layouts.bento')

@section('title', 'Gestão de serviços')
@section('page-title', 'Gestão de serviços')
@section('user-role', 'Gestão de serviços')

@php
    $tenant = request()->route('tenant');
    $bentoNavigation = \App\Support\PortalNavigation::make('services', 'queue', $tenant->slug);
    $canEdit = auth()->user()->checkPermissionTo('edit_service_order');
    $canExpense = auth()->user()->checkPermissionTo('manage_service_expenses');
    $canOperate = auth()->user()->checkPermissionTo('operate_all_service_orders_portal');
    $canViewFinancials = auth()->user()->checkPermissionTo('view_service_financials');
    $columnCount = 7 + ($canViewFinancials ? 2 : 0) + ($canExpense ? 1 : 0);
    $states = ['scheduled' => 'Agendado', 'in_progress' => 'Em execução', 'submitted' => 'Em conferência', 'rejected' => 'Em correção', 'validated' => 'Validado', 'cancelled' => 'Cancelado'];
@endphp

@section('content')
@include('services._management-index-style')
<div class="sg-orders">
    <header class="sg-toolbar">
        <div><h2>Ordens de serviço</h2><p>{{ $orders->total() }} {{ $orders->total() === 1 ? 'ordem encontrada' : 'ordens encontradas' }}</p></div>
        <div class="sg-actions">
            @if($canExpense)<a class="sg-button" href="{{ route('services.management.expenses', $tenant->slug) }}"><i class="ph ph-receipt" aria-hidden="true"></i> Despesas</a>@endif
            @if(auth()->user()->checkPermissionTo('manage_service_agreements'))<a class="sg-button" href="{{ route('services.management.agreements', $tenant->slug) }}"><i class="ph ph-handshake" aria-hidden="true"></i> Acordos</a>@endif
            @if(auth()->user()->checkPermissionTo('create_service_order'))<a class="sg-button sg-primary" href="{{ route('services.management.create', $tenant->slug) }}"><i class="ph ph-plus" aria-hidden="true"></i> Lançar serviços</a>@endif
        </div>
    </header>
    @include('services._workspace-messages')
    <form class="sg-filters" method="get" action="{{ route('services.management.index', $tenant->slug) }}">
        <label class="sg-search">Buscar<input name="q" type="search" value="{{ request('q') }}" placeholder="Número, serviço, beneficiário ou prestador"></label>
        <label>Situação<select name="status"><option value="">Todas</option>
            @foreach($states as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach
        </select></label>
        <label>De<input type="date" name="from" value="{{ request('from') }}"></label>
        <label>Até<input type="date" name="to" value="{{ request('to') }}"></label>
        <div class="sg-actions sg-filter-actions">
            <button class="sg-button" type="submit"><i class="ph ph-funnel" aria-hidden="true"></i> Filtrar</button>
            @if(request()->anyFilled(['q', 'status', 'from', 'to']))
                <a class="sg-link" href="{{ route('services.management.index', $tenant->slug) }}">Limpar</a>
            @endif
        </div>
    </form>
    <div class="sg-scroll" tabindex="0" role="region" aria-label="Tabela de ordens de serviço">
        <table class="sg-table" role="table">
            <thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Ordem / serviço</th><th scope="col" role="columnheader">Data da execução</th><th scope="col" role="columnheader">Beneficiário</th><th scope="col" role="columnheader">Prestador</th><th scope="col" role="columnheader">Execução</th><th scope="col" role="columnheader">Situação</th>
                @if($canViewFinancials)<th scope="col" role="columnheader" class="sg-number">Valor a receber</th><th scope="col" role="columnheader" class="sg-number">Valor a pagar</th>@endif
                @if($canExpense)<th scope="col" role="columnheader">Despesas</th>@endif<th scope="col" role="columnheader">Ações</th></tr></thead>
            <tbody role="rowgroup">
                @forelse($orders as $order)
                    <tr class="sg-order" role="row">
                        <th scope="row" role="rowheader"><a class="sg-link" href="{{ route('services.management.show', [$tenant->slug, $order]) }}">{{ $order->number }}</a><small>{{ $order->service?->name }}</small></th>
                        <td role="cell"><span class="sg-mobile-label" aria-hidden="true">Data da execução</span>{{ ($order->execution_date ?? $order->scheduled_at ?? $order->scheduled_date)?->format('d/m/Y') ?? '—' }}@if(!$order->execution_date)<small>Agendada</small>@endif</td>
                        <td role="cell"><span class="sg-mobile-label" aria-hidden="true">Beneficiário</span>{{ data_get($order->beneficiary_snapshot, 'name', '—') }}</td>
                        <td role="cell"><span class="sg-mobile-label" aria-hidden="true">Prestador</span>{{ data_get($order->provider_snapshot, 'name', '—') }}</td>
                        <td role="cell"><span class="sg-mobile-label" aria-hidden="true">Execução</span>
                            @if($order->start_time || $order->end_time)
                                <small>{{ $order->start_time ? substr($order->start_time, 0, 5) : '—' }} a {{ $order->end_time ? substr($order->end_time, 0, 5) : '—' }}</small>
                            @endif
                            @if($order->execution?->quantity !== null)
                                <strong>{{ \App\Support\PdfQuantityFormatter::format($order->execution->quantity) }} {{ $order->execution->unit }}</strong>
                            @endif
                            @foreach(collect(data_get($order->execution?->catalog_snapshot, 'fields', []))->filter(fn ($field) => ($field['visible_to_management'] ?? true) && ($field['reportable'] ?? false) && in_array($field['phase'] ?? null, ['start', 'execution', 'finish'], true) && is_scalar(data_get($order->execution?->values, $field['key'])))->take(3) as $field)
                                <small>{{ $field['label'] }} · {{ data_get($order->execution->values, $field['key']) }} {{ $field['unit'] ?? '' }}</small>
                            @endforeach
                        </td>
                        <td role="cell" class="sg-status"><span class="sg-state" data-state="{{ $order->operational_status }}">{{ $statuses->resolve($order) }}</span></td>
                        @if($canViewFinancials)
                            <td role="cell" class="sg-number"><span class="sg-mobile-label" aria-hidden="true">Valor a receber</span>{{ $order->execution?->isFrozen() ? 'R$ '.number_format((float) $order->final_price, 2, ',', '.') : '—' }}</td>
                            <td role="cell" class="sg-number"><span class="sg-mobile-label" aria-hidden="true">Valor a pagar</span>{{ $order->execution?->isFrozen() ? 'R$ '.number_format((float) $order->provider_payment, 2, ',', '.') : '—' }}</td>
                        @endif
                        @if($canExpense)<td role="cell"><span class="sg-mobile-label" aria-hidden="true">Despesas</span><a class="sg-link" href="{{ route('services.management.expenses', ['tenant' => $tenant->slug, 'order' => $order->id]) }}">Ver / lançar</a></td>@endif
                        <td role="cell" class="sg-order-actions"><div class="sg-actions sg-row-actions">
                            @if($canEdit && $order->execution?->status === 'draft')<a class="sg-link" href="{{ route('services.management.edit', [$tenant->slug, $order]) }}">Editar</a>@endif
                            @if($canEdit && in_array($order->execution?->status, ['draft', 'in_progress', 'rejected'], true))
                                <button type="button" class="sg-button sg-row-edit" data-row-toggle="execution-{{ $order->id }}" aria-controls="execution-{{ $order->id }}" aria-expanded="false">Editar execução</button>
                            @endif
                            @if($order->execution?->status === 'submitted' && auth()->user()->checkPermissionTo('approve_service_execution'))
                                <form method="post" action="{{ route('services.management.approve', [$tenant->slug, $order]) }}">
                                    @csrf
                                    <input type="hidden" name="operation_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                                    <button class="sg-button" type="submit">Validar</button>
                                </form>
                            @endif
                            @if($accounts->isNotEmpty() && $order->execution?->isFrozen())
                                @foreach($order->execution->obligations as $obligation)
                                    @if(in_array($obligation->status, ['open', 'partially_paid'], true) && auth()->user()->checkPermissionTo($obligation->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables'))
                                        @if($negotiated->contains($obligation->id))
                                            @if(auth()->user()->checkPermissionTo('manage_service_agreements'))<a class="sg-link" href="{{ route('services.management.agreements', $tenant->slug) }}">Ver parcelas</a>@endif
                                            @continue
                                        @endif
                                        <button class="sg-button" type="button" data-row-toggle="payment-{{ $obligation->id }}" aria-controls="payment-{{ $obligation->id }}" aria-expanded="false">{{ $obligation->direction === 'payable' ? 'Pagar' : 'Receber' }}</button>
                                    @endif
                                @endforeach
                            @endif
                            <a class="sg-link" href="{{ route('services.management.show', [$tenant->slug, $order]) }}">Detalhes</a>
                            @if($canEdit && in_array($order->execution?->status, ['draft', 'in_progress', 'submitted', 'rejected'], true))
                                <button type="button" class="sg-link sg-danger" data-remove-url="{{ route('services.management.remove', [$tenant->slug, $order]) }}"
                                    data-order="{{ $order->number }}" data-draft="{{ $order->execution->status === 'draft' ? '1' : '0' }}">Remover</button>
                            @endif
                        </div></td>
                    </tr>
                    @include('services._execution-row')
                @empty
                    <tr role="row">
                        <td role="cell" colspan="{{ $columnCount }}" class="sg-empty">
                            <i class="ph ph-clipboard-text" aria-hidden="true"></i>
                            <strong>Nenhuma ordem encontrada</strong>
                            @if(request()->anyFilled(['q', 'status', 'from', 'to']))
                                <small>Experimente ajustar os filtros.</small>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links('vendor.pagination.bento') }}
    <script>
        document.querySelectorAll('.sg-orders [data-row-toggle]').forEach(button => {
            const row = document.getElementById(button.dataset.rowToggle);
            if (!row) return;

            button.setAttribute('aria-expanded', String(!row.hidden));
            const toggle = open => {
                row.hidden = !open;
                button.setAttribute('aria-expanded', String(open));
                if (open) row.querySelector('input:not([type="hidden"]), select')?.focus();
                else button.focus();
            };
            button.addEventListener('click', () => toggle(row.hidden));
            row.querySelector('[data-row-close]')?.addEventListener('click', () => toggle(false));
        });
    </script>

    @if($canEdit)
    <dialog class="sg-dialog" id="sg-remove" aria-labelledby="sg-remove-title">
        <form method="post">
            @csrf
            @method('DELETE')
            <h3 id="sg-remove-title">Remover <span data-order-number></span></h3>
            <label>Ação<select name="action"><option value="cancel">Cancelar e manter no histórico</option><option value="delete">Excluir da lista de ordens</option></select></label>
            <label>Motivo<textarea name="reason" required minlength="5" maxlength="500" rows="3"></textarea></label>
            <div class="sg-actions"><button type="button" class="sg-button" data-close>Voltar</button><button class="sg-button sg-danger" type="submit">Confirmar</button></div>
        </form>
    </dialog>
    <script>
        (() => {
            const dialog = document.querySelector('#sg-remove');
            const form = dialog.querySelector('form');
            document.querySelectorAll('[data-remove-url]').forEach(button => button.addEventListener('click', () => {
                form.reset();
                form.action = button.dataset.removeUrl;
                form.querySelector('[data-order-number]').textContent = button.dataset.order;
                form.elements.action.options[1].disabled = button.dataset.draft !== '1';
                dialog.showModal();
            }));
            dialog.querySelector('[data-close]').addEventListener('click', () => dialog.close());
        })();
    </script>
    @endif
</div>
@endsection
