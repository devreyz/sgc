@extends('layouts.bento')

@section('title', 'Financeiro')
@section('page-title', 'Financeiro')
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard') }}" class="nav-tab">Dashboard</a>
    <a href="{{ route('provider.orders') }}" class="nav-tab">Ordens de Serviço</a>
    <a href="{{ route('provider.financial') }}" class="nav-tab active">Financeiro</a>
    <form action="{{ route('logout') }}" method="POST" style="display: inline;">
        @csrf
        <button type="submit" class="nav-tab" style="background: none; cursor: pointer;">Sair</button>
    </form>
</nav>
@endsection

@section('content')
<div class="bento-grid">

    @if(session('success'))
    <div class="bento-card col-span-full" style="border-left: 4px solid var(--color-success); background: rgba(16, 185, 129, 0.05);">
        <p style="color: var(--color-success); font-weight: 500;"><i data-lucide="check-circle" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i> {{ session('success') }}</p>
    </div>
    @endif

    @if(session('error'))
    <div class="bento-card col-span-full" style="border-left: 4px solid var(--color-danger); background: rgba(239, 68, 68, 0.05);">
        <p style="color: var(--color-danger); font-weight: 500;"><i data-lucide="alert-circle" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i> {{ session('error') }}</p>
    </div>
    @endif

    <!-- Resumo Financeiro -->
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.5rem;">
            <i data-lucide="wallet" style="width:1.5rem;height:1.5rem;display:inline;vertical-align:text-bottom;"></i>
            Resumo Financeiro
        </h2>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.5rem;">
            <div style="text-align:center;padding:1.5rem;border-radius:0.75rem;background:linear-gradient(135deg,rgba(59,130,246,0.1) 0%,rgba(59,130,246,0.05) 100%);border:2px solid rgba(59,130,246,0.2);">
                <div style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:0.5rem;font-weight:600;">A Receber</div>
                <div style="font-size:2.25rem;font-weight:800;color:var(--color-info);margin-bottom:0.25rem;">
                    R$ {{ number_format($pending_receivable, 2, ',', '.') }}
                </div>
                <div style="font-size:0.75rem;color:var(--text-muted);">
                    Cliente pagou, aguardando saque
                </div>
            </div>

            <div style="text-align:center;padding:1.5rem;border-radius:0.75rem;background:linear-gradient(135deg,rgba(16,185,129,0.1) 0%,rgba(16,185,129,0.05) 100%);border:2px solid rgba(16,185,129,0.2);">
                <div style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:0.5rem;font-weight:600;">Disponível p/ Saque</div>
                <div style="font-size:2.25rem;font-weight:800;color:var(--color-success);margin-bottom:0.25rem;">
                    R$ {{ number_format($available_withdrawal, 2, ',', '.') }}
                </div>
                @if($pending_requests_total > 0)
                <div style="font-size:0.75rem;color:var(--text-muted);">
                    R$ {{ number_format($pending_requests_total, 2, ',', '.') }} em análise
                </div>
                @else
                <div style="font-size:0.75rem;color:var(--text-muted);">
                    Pode solicitar saque agora
                </div>
                @endif
            </div>

            <div style="text-align:center;padding:1.5rem;border-radius:0.75rem;background:linear-gradient(135deg,rgba(107,114,128,0.1) 0%,rgba(107,114,128,0.05) 100%);border:2px solid rgba(107,114,128,0.2);">
                <div style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:0.5rem;font-weight:600;">Total Recebido</div>
                <div style="font-size:2.25rem;font-weight:800;color:var(--text-primary);margin-bottom:0.25rem;">
                    R$ {{ number_format($total_received, 2, ',', '.') }}
                </div>
                <div style="font-size:0.75rem;color:var(--text-muted);">
                    Todos os tempos
                </div>
            </div>
        </div>
    </div>

    <!-- Valores Disponíveis para Saque -->
    @if(!$pendingOrders->isEmpty())
    <div class="bento-card col-span-full">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;flex-wrap:wrap;gap:1rem;">
            <h2 class="font-bold" style="font-size: 1.25rem;">
                <i data-lucide="banknote" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i>
                Valores Disponíveis para Saque
            </h2>
            @if($available_withdrawal > 0)
            <span style="padding:0.5rem 1rem;background:rgba(16,185,129,0.1);color:var(--color-success);border-radius:0.5rem;font-weight:600;font-size:0.875rem;">
                R$ {{ number_format($available_withdrawal, 2, ',', '.') }} disponível
            </span>
            @endif
        </div>

        <div style="border-left:4px solid var(--color-info);padding-left:1rem;background:rgba(59,130,246,0.03);padding:1rem;border-radius:0.5rem;margin-bottom:1rem;">
            <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:0.5rem;">
                <i data-lucide="info" style="width:1rem;height:1rem;display:inline;vertical-align:text-bottom;"></i>
                <strong>Como funciona:</strong>
            </p>
            <p style="font-size:0.875rem;color:var(--text-secondary);">
                Após o cliente pagar pelo serviço, você pode solicitar o saque do seu valor. 
                Informe seus dados bancários, envie a solicitação e aguarde aprovação da administração. 
                O pagamento será creditado diretamente na sua conta.
            </p>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Ordem</th>
                        <th>Serviço</th>
                        <th>Cliente</th>
                        <th>Data Execução</th>
                        <th>Valor Disponível</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pendingOrders as $order)
                    <tr>
                        <td class="font-semibold">{{ $order->number }}</td>
                        <td>{{ optional($order->service)->name ?? '-' }}</td>
                        <td>
                            @if($order->associate)
                                {{ $order->associate->name }}
                            @else
                                @php
                                    preg_match('/\[PESSOA AVULSA\]\nNome:\s*(.+)/m', $order->notes ?? '', $matches);
                                @endphp
                                {{ $matches[1] ?? 'Avulso' }}
                            @endif
                        </td>
                        <td>{{ $order->execution_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="font-bold text-primary">R$ {{ number_format($order->provider_remaining, 2, ',', '.') }}</td>
                        <td>
                            @php
                                $hasPendingRequest = $pendingRequests->where('service_order_id', $order->id)->isNotEmpty();
                            @endphp
                            @if($hasPendingRequest)
                                <span class="badge badge-warning" style="font-size:0.75rem;">Em análise</span>
                            @else
                                <a href="{{ route('provider.financial.request-payment', $order->id) }}" class="btn btn-primary" style="padding:0.375rem 0.75rem;font-size:0.75rem;">
                                    <i data-lucide="send" style="width:0.875rem;height:0.875rem;"></i>
                                    Solicitar Saque
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="font-bold" style="text-align:right;">Total Disponível:</td>
                        <td class="font-bold text-primary">R$ {{ number_format($available_withdrawal, 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @else
        <div class="bento-card col-span-full">
            <div style="text-align:center;padding:2rem;">
                <i data-lucide="wallet" style="width:3rem;height:3rem;color:var(--text-muted);margin:0 auto 1rem;"></i>
                <p style="color:var(--text-muted);font-size:1.125rem;margin-bottom:0.5rem;font-weight:600;">Nenhum valor disponível para saque</p>
                <p style="color:var(--text-muted);font-size:0.875rem;">Complete ordens de serviço e aguarde o pagamento dos clientes</p>
            </div>
        </div>
    @endif

    <!-- Solicitações de Saque Pendentes -->
    @if(!$pendingRequests->isEmpty())
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">
            <i data-lucide="clock" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i>
            Solicitações Pendentes de Aprovação
        </h2>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data Solicitação</th>
                        <th>Ordem</th>
                        <th>Serviço</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pendingRequests as $request)
                    <tr>
                        <td>{{ $request->request_date->format('d/m/Y H:i') }}</td>
                        <td class="font-semibold">{{ optional($request->serviceOrder)->number ?? '-' }}</td>
                        <td>{{ optional(optional($request->serviceOrder)->service)->name ?? '-' }}</td>
                        <td class="font-bold" style="color:var(--color-warning);">R$ {{ number_format($request->amount, 2, ',', '.') }}</td>
                        <td>
                            <span class="badge badge-warning">Aguardando Aprovação</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="font-bold" style="text-align:right;">Total em Análise:</td>
                        <td class="font-bold" style="color:var(--color-warning);">R$ {{ number_format($pending_requests_total, 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    <!-- Histórico de Pagamentos Recebidos -->
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">
            <i data-lucide="history" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i>
            Pagamentos Recebidos
        </h2>

        @if($receivedPayments->isEmpty())
            <p class="text-muted" style="text-align:center;padding:2rem;">Nenhum pagamento recebido até o momento.</p>
        @else
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Ordem</th>
                        <th>Serviço</th>
                        <th>Valor</th>
                        <th>Método</th>
                        <th>Comprovante</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($receivedPayments as $payment)
                    <tr>
                        <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                        <td>
                            <a href="{{ route('provider.orders.show', $payment->service_order_id) }}" style="color:var(--color-info);text-decoration:none;font-weight:600;">
                                {{ optional($payment->serviceOrder)->number ?? '-' }}
                            </a>
                        </td>
                        <td>{{ optional(optional($payment->serviceOrder)->service)->name ?? '-' }}</td>
                        <td class="font-bold text-primary">R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
                        <td>
                            <span class="badge" style="font-size:0.75rem;">{{ $payment->payment_method?->getLabel() ?? '-' }}</span>
                        </td>
                        <td>
                            @if($payment->receipt_path)
                                <a href="{{ Storage::url($payment->receipt_path) }}" target="_blank" class="text-sm" style="color:var(--color-info);">
                                    <i data-lucide="file-text" style="width:0.875rem;height:0.875rem;display:inline;vertical-align:text-bottom;"></i>
                                    Ver
                                </a>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 1rem;">
            {{ $receivedPayments->links() }}
        </div>
        @endif
    </div>

    <!-- Histórico de Saques Aprovados (Últimos 10) -->
    @if(!$approvedRequests->isEmpty())
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">
            <i data-lucide="check-circle" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i>
            Saques Aprovados Recentes
        </h2>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data Aprovação</th>
                        <th>Ordem</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($approvedRequests as $request)
                    <tr>
                        <td>{{ $request->approved_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="font-semibold">{{ optional($request->serviceOrder)->number ?? '-' }}</td>
                        <td class="font-bold" style="color:var(--color-success);">R$ {{ number_format($request->amount, 2, ',', '.') }}</td>
                        <td>
                            <span class="badge badge-success">Aprovado</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
