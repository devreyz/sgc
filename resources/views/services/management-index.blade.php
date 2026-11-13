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
@include('services._workspace-style')
<div class="service-grid sg-orders">
    <header class="sg-toolbar">
        <div><h1>Ordens de serviço</h1><p>{{ $orders->total() }} {{ $orders->total() === 1 ? 'ordem encontrada' : 'ordens encontradas' }}</p></div>
        <div class="sg-actions">
            @if($canExpense)<a class="sg-button" href="{{ route('services.management.expenses', $tenant->slug) }}">Despesas</a>@endif
            @if(auth()->user()->checkPermissionTo('manage_service_agreements'))<a class="sg-button" href="{{ route('services.management.agreements', $tenant->slug) }}">Acordos</a>@endif
            @if(auth()->user()->checkPermissionTo('create_service_order'))<a class="sg-button sg-primary" href="{{ route('services.management.create', $tenant->slug) }}">+ Lançar serviços</a>@endif
        </div>
    </header>
    @include('services._workspace-messages')
    <form class="sg-filters" method="get" action="{{ route('services.management.index', $tenant->slug) }}">
        <label>Buscar<input name="q" type="search" value="{{ request('q') }}" placeholder="Número, serviço, beneficiário ou prestador"></label>
        <label>Situação<select name="status"><option value="">Todas</option>
            @foreach($states as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach
        </select></label>
        <label>De<input type="date" name="from" value="{{ request('from') }}"></label>
        <label>Até<input type="date" name="to" value="{{ request('to') }}"></label>
        <button class="sg-button">Filtrar</button>
        @if(request()->anyFilled(['q', 'status', 'from', 'to']))<a href="{{ route('services.management.index', $tenant->slug) }}">Limpar</a>@endif
    </form>
    <div class="sg-scroll" tabindex="0" role="region" aria-label="Tabela de ordens de serviço">
        <table class="sg-table">
            <thead><tr><th>Ordem / serviço</th><th>Data da execução</th><th>Beneficiário</th><th>Prestador</th><th>Execução</th><th>Situação</th>
                @if($canViewFinancials)<th class="sg-number">Valor a receber</th><th class="sg-number">Valor a pagar</th>@endif
                @if($canExpense)<th>Despesas</th>@endif<th>Ações</th></tr></thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <th scope="row"><a href="{{ route('services.management.show', [$tenant->slug, $order]) }}">{{ $order->number }}</a><small>{{ $order->service?->name }}</small></th>
                        <td>{{ ($order->execution_date ?? $order->scheduled_at ?? $order->scheduled_date)?->format('d/m/Y') ?? '—' }}@if(!$order->execution_date)<small>Agendada</small>@endif</td>
                        <td>{{ data_get($order->beneficiary_snapshot, 'name', '—') }}</td>
                        <td>{{ data_get($order->provider_snapshot, 'name', '—') }}</td>
                        <td>
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
                        <td><span class="sg-state" data-state="{{ $order->operational_status }}">{{ $statuses->resolve($order) }}</span></td>
                        @if($canViewFinancials)
                            <td class="sg-number">{{ $order->execution?->isFrozen() ? 'R$ '.number_format((float) $order->final_price, 2, ',', '.') : '—' }}</td>
                            <td class="sg-number">{{ $order->execution?->isFrozen() ? 'R$ '.number_format((float) $order->provider_payment, 2, ',', '.') : '—' }}</td>
                        @endif
                        @if($canExpense)<td><a href="{{ route('services.management.expenses', ['tenant' => $tenant->slug, 'order' => $order->id]) }}">Ver / lançar</a></td>@endif
                        <td><div class="sg-actions sg-row-actions">
                            @if($canEdit && $order->execution?->status === 'draft')<a href="{{ route('services.management.edit', [$tenant->slug, $order]) }}">Editar</a>@endif
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
                                            @if(auth()->user()->checkPermissionTo('manage_service_agreements'))<a href="{{ route('services.management.agreements', $tenant->slug) }}">Ver parcelas</a>@endif
                                            @continue
                                        @endif
                                        <button class="sg-button" type="button" data-row-toggle="payment-{{ $obligation->id }}" aria-controls="payment-{{ $obligation->id }}" aria-expanded="false">{{ $obligation->direction === 'payable' ? 'Pagar' : 'Receber' }}</button>
                                    @endif
                                @endforeach
                            @endif
                            <a href="{{ route('services.management.show', [$tenant->slug, $order]) }}">Detalhes</a>
                            @if($canEdit && in_array($order->execution?->status, ['draft', 'in_progress', 'submitted', 'rejected'], true))
                                <button type="button" class="sg-link sg-danger" data-remove-url="{{ route('services.management.remove', [$tenant->slug, $order]) }}"
                                    data-order="{{ $order->number }}" data-draft="{{ $order->execution->status === 'draft' ? '1' : '0' }}">Remover</button>
                            @endif
                        </div></td>
                    </tr>
                    @include('services._execution-row')
                @empty
                    <tr><td colspan="{{ $columnCount }}" class="sg-empty">Nenhuma ordem para os filtros selecionados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $orders->links() }}</div>
    <script>
        document.querySelectorAll('.sg-orders [data-row-toggle]').forEach(button => {
            const row = document.getElementById(button.dataset.rowToggle);
            const toggle = open => {
                row.hidden = !open;
                button.setAttribute('aria-expanded', String(open));
                if (open) row.querySelector('input:not([type="hidden"]), select')?.focus();
                else button.focus();
            };
            button.addEventListener('click', () => toggle(row.hidden));
            row.querySelector('[data-row-close]').addEventListener('click', () => toggle(false));
        });
    </script>

    @if($canEdit)
    <dialog class="sg-dialog" id="sg-remove">
        <form method="post">
            @csrf
            @method('DELETE')
            <h2>Remover <span data-order-number></span></h2>
            <label>Ação<select name="action"><option value="cancel">Cancelar e manter no histórico</option><option value="delete">Excluir da lista de ordens</option></select></label>
            <label>Motivo<textarea name="reason" required minlength="5" maxlength="500" rows="3"></textarea></label>
            <div class="sg-actions"><button type="button" class="sg-button" data-close>Voltar</button><button class="sg-button sg-primary">Confirmar</button></div>
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
