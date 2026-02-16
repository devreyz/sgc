@extends('layouts.bento')

@section('title', 'Minha Carteira')
@section('page-title', 'Minha Carteira Digital')
@section('user-role', 'Extrato e Carteirinha')

@push('styles')
<style>
    .membership-card {
        background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
        border-radius: var(--radius-xl);
        padding: 2rem;
        color: white;
        box-shadow: var(--shadow-lg);
        position: relative;
        overflow: hidden;
    }

    .membership-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .membership-card::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 150px;
        height: 150px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }

    .financial-stat {
        padding: 1.25rem;
        background: var(--color-bg);
        border-radius: var(--radius-lg);
        border-left: 4px solid var(--color-primary);
    }

    .transaction-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border-radius: var(--radius-md);
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        transition: all 0.2s;
    }

    .transaction-item:hover {
        border-color: var(--color-primary);
        transform: translateX(4px);
    }

    @media print {
        body * {
            visibility: hidden;
        }
        .membership-card, .membership-card * {
            visibility: visible;
        }
        .membership-card {
            position: absolute;
            left: 0;
            top: 0;
        }
    }
</style>
@endpush

@section('content')
<div class="bento-grid">
    <!-- Membership Card -->
    <div class="bento-card col-span-12 lg:col-span-6">
        <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--color-text);">Carteirinha Digital</h3>
        
        <div class="membership-card">
            <div style="position: relative; z-index: 1;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 2rem;">
                    <div>
                        <h4 style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.25rem;">Associação</h4>
                        <h3 style="font-size: 1.25rem; font-weight: 700;">{{ $tenant->name }}</h3>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        @if($user->avatar)
                            <img src="{{ Storage::url($user->avatar) }}" alt="{{ $user->name }}" 
                                 style="width: 60px; height: 60px; border-radius: 12px; object-fit: cover; border: 2px solid rgba(255,255,255,0.3);">
                        @else
                            <div style="width: 60px; height: 60px; border-radius: 12px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700;">
                                {{ substr($user->name, 0, 1) }}
                            </div>
                        @endif
                        <div>
                            <h4 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.25rem;">{{ $user->name }}</h4>
                            <p style="font-size: 0.875rem; opacity: 0.9;">Nº {{ $membershipCard['member_number'] }}</p>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.2);">
                    <div>
                        <p style="font-size: 0.75rem; opacity: 0.8; margin-bottom: 0.25rem;">Membro desde</p>
                        <p style="font-size: 0.9rem; font-weight: 600;">{{ \Carbon\Carbon::parse($membershipCard['member_since'])->format('d/m/Y')  }}</p>
                    </div>
                    <div>
                        <p style="font-size: 0.75rem; opacity: 0.8; margin-bottom: 0.25rem;">Status</p>
                        <p style="font-size: 0.9rem; font-weight: 600;">✓ Ativo</p>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 1rem;">
            <a href="{{ route('wallet.print-card', ['tenant' => $tenant->slug]) }}" target="_blank" class="btn btn-outline" style="width: 100%;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Imprimir Carteirinha
            </a>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="bento-card col-span-12 lg:col-span-6">
        <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--color-text);">Resumo Financeiro</h3>
        
        <div style="display: grid; gap: 1rem;">
            <div class="financial-stat">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 0.25rem;">Recebido</p>
                        <p style="font-size: 1.5rem; font-weight: 700; color: var(--color-success);">R$ {{ number_format($financialSummary['total_earned'], 2, ',', '.') }}</p>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(16, 185, 129, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-success);">
                            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="financial-stat" style="border-left-color: var(--color-warning);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 0.25rem;">A Receber</p>
                        <p style="font-size: 1.5rem; font-weight: 700; color: var(--color-warning);">R$ {{ number_format($financialSummary['pending_payment'], 2, ',', '.') }}</p>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(245, 158, 11, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-warning);">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="financial-stat" style="border-left-color: var(--color-danger);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 0.25rem;">Pago</p>
                        <p style="font-size: 1.5rem; font-weight: 700; color: var(--color-danger);">R$ {{ number_format($financialSummary['total_paid'], 2, ',', '.') }}</p>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(239, 68, 68, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-danger);">
                            <line x1="12" y1="2" x2="12" y2="22"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="financial-stat" style="border-left-color: var(--color-info); background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.1) 100%);">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 0.25rem;">Saldo</p>
                        <p style="font-size: 1.75rem; font-weight: 700; color: var(--color-info);">R$ {{ number_format($financialSummary['balance'], 2, ',', '.') }}</p>
                    </div>
                    <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-info);">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Chart -->
    <div class="bento-card col-span-12 lg:col-span-7">
        <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--color-text);">Movimentação Financeira</h3>
        <canvas id="financialChart" style="max-height: 300px;"></canvas>
    </div>

    <!-- Recent Transactions -->
    <div class="bento-card col-span-12 lg:col-span-5">
        <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--color-text);">Transações Recentes</h3>
        
        <div style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 400px; overflow-y: auto;">
            @forelse($recentTransactions as $transaction)
                <div class="transaction-item">
                    <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: {{ $transaction['type'] === 'income' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' }};">
                        @if($transaction['type'] === 'income')
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-success);">
                                <line x1="12" y1="19" x2="12" y2="5"></line>
                                <polyline points="5 12 12 5 19 12"></polyline>
                            </svg>
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-danger);">
                                <line x1="12" y1="5" x2="12" y2="19"></line>
                                <polyline points="19 12 12 19 5 12"></polyline>
                            </svg>
                        @endif
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <p style="font-size: 0.9rem; font-weight: 600; color: var(--color-text); margin-bottom: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ $transaction['description'] }}</p>
                        <p style="font-size: 0.8rem; color: var(--color-text-muted);">{{ \Carbon\Carbon::parse($transaction['date'])->format('d/m/Y') }}</p>
                    </div>
                    <div style="text-align: right;">
                        <p style="font-size: 0.95rem; font-weight: 700; color: {{ $transaction['type'] === 'income' ? 'var(--color-success)' : 'var(--color-danger)' }};">
                            {{ $transaction['type'] === 'income' ? '+' : '-' }} R$ {{ number_format($transaction['amount'], 2, ',', '.') }}
                        </p>
                        <span class="badge badge-{{ $transaction['status'] === 'paid' ? 'success' : ($transaction['status'] === 'pending' ? 'warning' : 'secondary') }}">
                            {{ $transaction['status'] === 'paid' ? 'Pago' : ($transaction['status'] === 'pending' ? 'Pendente' : 'Aprovado') }}
                        </span>
                    </div>
                </div>
            @empty
                <div style="text-align: center; padding: 2rem; color: var(--color-text-muted);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 1rem; opacity: 0.3;">
                        <line x1="12" y1="2" x2="12" y2="22"></line>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    <p>Nenhuma transação registrada</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('financialChart');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Recebido', 'A Receber', 'Pago', 'Saldo'],
            datasets: [{
                label: 'Valor (R$)',
                data: [
                    {{ $financialSummary['total_earned'] }},
                    {{ $financialSummary['pending_payment'] }},
                    {{ $financialSummary['total_paid'] }},
                    {{ $financialSummary['balance'] }}
                ],
                backgroundColor: [
                    'rgba(16, 185, 129, 0.8)',
                    'rgba(245, 158, 11, 0.8)',
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(59, 130, 246, 0.8)'
                ],
                borderColor: [
                    'rgb(16, 185, 129)',
                    'rgb(245, 158, 11)',
                    'rgb(239, 68, 68)',
                    'rgb(59, 130, 246)'
                ],
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    bodyFont: {
                        size: 14
                    },
                    callbacks: {
                        label: function(context) {
                            return 'R$ ' + context.parsed.y.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
</script>
@endpush
