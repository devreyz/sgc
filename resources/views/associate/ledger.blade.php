@extends('layouts.bento')

@section('title', 'Extrato Financeiro')
@section('page-title', 'Extrato Financeiro')
@section('user-role', 'Associado')

@php
    $routeTenant = request()->route('tenant');
    $routeSlug = is_string($routeTenant) ? $routeTenant : (is_object($routeTenant) ? ($routeTenant->slug ?? null) : null);
    $tenantSlug = $currentTenant?->slug ?? session('tenant_slug') ?? $routeSlug ?? null;
@endphp

@php($bentoNavigation = \App\Support\PortalNavigation::make('associate', 'ledger', $tenantSlug))

@section('content')
<div class="bento-grid">
    <!-- Distribution financial summary -->
    <div class="bento-card col-span-full">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
            <div class="stat-card">
                <div class="stat-label">Distribuido aprovado</div>
                <div class="stat-value text-primary">R$ {{ number_format($financialSummary['total_net'], 2, ',', '.') }}</div>
                <p class="text-xs text-muted mt-2">Valor liquido das distribuicoes aprovadas.</p>
            </div>
            <div class="stat-card">
                <div class="stat-label">A receber</div>
                <div class="stat-value" style="color:var(--color-warning);">R$ {{ number_format($financialSummary['receivable'], 2, ',', '.') }}</div>
                <p class="text-xs text-muted mt-2">Comprovantes emitidos ainda nao quitados.</p>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pago</div>
                <div class="stat-value text-success">R$ {{ number_format($financialSummary['paid'], 2, ',', '.') }}</div>
                <p class="text-xs text-muted mt-2">Pagamentos registrados em comprovantes.</p>
            </div>
            <div class="stat-card">
                <div class="stat-label">Taxas descontadas</div>
                <div class="stat-value" style="color:var(--color-text);">R$ {{ number_format($financialSummary['total_fees'], 2, ',', '.') }}</div>
                <p class="text-xs text-muted mt-2">{{ $financialSummary['distribution_count'] }} distribuicao(oes).</p>
            </div>
        </div>
    </div>

    @if($receipts->isNotEmpty())
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size:1.05rem;">Comprovantes do Associado</h2>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Numero</th>
                        <th>Projeto</th>
                        <th>Emissao</th>
                        <th>Status</th>
                        <th>Liquido</th>
                        <th>Pago</th>
                        <th>A receber</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receipts as $receipt)
                    @php
                        $receiptPaid = ($receipt->status?->value === 'paid' && (float) $receipt->amount_paid <= 0)
                            ? (float) $receipt->total_net
                            : (float) $receipt->amount_paid;
                        $receiptRemaining = max(0, (float) $receipt->total_net - $receiptPaid);
                    @endphp
                    <tr>
                        <td class="font-semibold">{{ $receipt->formatted_number }}</td>
                        <td>{{ $receipt->project?->title ?? '-' }}</td>
                        <td>{{ $receipt->issued_at?->format('d/m/Y') ?? '-' }}</td>
                        <td><span class="badge badge-{{ $receipt->status?->value === 'paid' ? 'success' : ($receipt->status?->value === 'partially_paid' ? 'info' : 'warning') }}">{{ $receipt->status?->getLabel() ?? 'Rascunho' }}</span></td>
                        <td class="font-semibold">R$ {{ number_format((float) $receipt->total_net, 2, ',', '.') }}</td>
                        <td class="text-success font-semibold">R$ {{ number_format($receiptPaid, 2, ',', '.') }}</td>
                        <td class="font-semibold" style="color:var(--color-warning);">R$ {{ number_format($receiptRemaining, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($receiptPayments->isNotEmpty())
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size:1.05rem;">Pagamentos Recebidos</h2>
        <div style="display:grid;gap:.75rem;">
            @foreach($receiptPayments as $payment)
            <div style="display:flex;justify-content:space-between;gap:1rem;align-items:center;border:1px solid var(--color-border);border-radius:var(--radius-md);padding:.85rem;background:var(--color-bg);">
                <div style="min-width:0;">
                    <div style="font-weight:700;">Comprovante {{ $payment->receipt?->formatted_number ?? '-' }}</div>
                    <div class="text-xs text-muted">{{ $payment->receipt?->project?->title ?? 'Projeto' }} - {{ $payment->payment_date?->format('d/m/Y') ?? '-' }}</div>
                </div>
                <div class="text-success font-bold" style="white-space:nowrap;">R$ {{ number_format((float) $payment->amount, 2, ',', '.') }}</div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Filters -->
    <div class="bento-card col-span-full">
        <form method="GET" class="flex gap-4 items-center" style="flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Data Inicial</label>
                <input type="date" name="start_date" class="form-input" value="{{ request('start_date') }}">
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label class="form-label">Data Final</label>
                <input type="date" name="end_date" class="form-input" value="{{ request('end_date') }}">
            </div>
            <div style="margin-top: auto; display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ $tenantSlug ? route('associate.ledger', ['tenant' => $tenantSlug]) : url('/') }}" class="btn btn-outline">Limpar</a>
            </div>
        </form>
    </div>

    <!-- Transactions -->
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">Histórico de Transações ({{ $transactions->total() }})</h2>

        @if($transactions->count() > 0)
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Tipo</th>
                            <th>Categoria</th>
                            <th>Descrição</th>
                            <th>Valor</th>
                            <th>Saldo Após</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->transaction_date->format('d/m/Y') }}</td>
                            <td>
                                <span class="badge badge-{{ $transaction->type->value === 'credit' ? 'success' : 'danger' }}">
                                    {{ $transaction->type->getLabel() }}
                                </span>
                            </td>
                            <td>
                                <span class="text-sm">{{ $transaction->category->getLabel() }}</span>
                            </td>
                            <td>
                                <div class="text-sm">{{ $transaction->description ?? '-' }}</div>
                                @if($transaction->reference_type)
                                    <div class="text-xs text-muted">Ref: {{ class_basename($transaction->reference_type) }} #{{ $transaction->reference_id }}</div>
                                @endif
                            </td>
                            <td class="font-bold {{ $transaction->type->value === 'credit' ? 'text-success' : 'text-danger' }}">
                                {{ $transaction->type->value === 'credit' ? '+' : '-' }} R$ {{ number_format($transaction->amount, 2, ',', '.') }}
                            </td>
                            <td class="font-semibold">
                                R$ {{ number_format($transaction->balance_after, 2, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($transactions->hasPages())
                <div style="margin-top: 1.5rem; display: flex; justify-content: center;">
                    {{ $transactions->links() }}
                </div>
            @endif
        @else
            <div style="text-align: center; padding: 3rem;">
                <p class="text-muted" style="font-size: 1.125rem;">Nenhuma transação encontrada no período.</p>
                <p class="text-muted text-sm" style="margin-top: 0.5rem;">Suas movimentações financeiras aparecerão aqui.</p>
            </div>
        @endif
    </div>

    <!-- Legend -->
    <div class="bento-card col-span-full">
        <h3 class="font-bold mb-3" style="font-size: 1rem;">Entendendo o Extrato</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
            <div style="padding: 1rem; background: rgba(16, 185, 129, 0.1); border-radius: var(--radius-md);">
                <div class="font-semibold text-success mb-1">💰 Créditos (Verde)</div>
                <p class="text-xs text-muted">Entregas aprovadas e valores a receber aumentam seu crédito com a cooperativa.</p>
            </div>

            <div style="padding: 1rem; background: rgba(239, 68, 68, 0.1); border-radius: var(--radius-md);">
                <div class="font-semibold text-danger mb-1">💸 Débitos (Vermelho)</div>
                <p class="text-xs text-muted">Pagamentos recebidos, compras e serviços reduzem seu saldo a receber.</p>
            </div>

            <div style="padding: 1rem; background: var(--color-bg); border-radius: var(--radius-md);">
                <div class="font-semibold mb-1">📊 Saldo Após</div>
                <p class="text-xs text-muted">Mostra o saldo acumulado após cada transação, atualizando sua posição financeira.</p>
            </div>
        </div>
    </div>
</div>
@endsection


