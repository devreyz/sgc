@extends('layouts.bento')

@section('title', 'Extrato Financeiro')
@section('page-title', 'Extrato Financeiro')
@section('user-role', 'Associado')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('associate.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('associate.projects') }}" class="nav-tab">Projetos</a>
    <a href="{{ route('associate.deliveries') }}" class="nav-tab">Entregas</a>
    <a href="{{ route('associate.ledger') }}" class="nav-tab active">Extrato</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">
    <!-- Current Balance -->
    <div class="bento-card col-span-full">
        <div class="stat-card">
            <div class="stat-label">Saldo Atual</div>
            <div class="stat-value {{ $currentBalance < 0 ? 'text-danger' : 'text-primary' }}">
                R$ {{ number_format($currentBalance, 2, ',', '.') }}
            </div>
            @if($currentBalance < 0)
                <p class="text-xs text-danger mt-2">💡 Saldo negativo indica que você possui crédito a receber da cooperativa.</p>
            @else
                <p class="text-xs text-muted mt-2">💡 Saldo positivo indica valores que você deve à cooperativa.</p>
            @endif
        </div>
    </div>

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
                <a href="{{ route('associate.ledger') }}" class="btn btn-outline">Limpar</a>
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
