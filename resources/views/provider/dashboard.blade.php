@extends('layouts.bento')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('user-role', 'Prestador de Serviço')

@section('navigation')
<nav class="nav-tabs">
    <a href="{{ route('provider.dashboard') }}" class="nav-tab active">Dashboard</a>
    <a href="{{ route('provider.orders') }}" class="nav-tab">Ordens de Serviço</a>
    <a href="{{ route('provider.financial') }}" class="nav-tab">Financeiro</a>
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

    <!-- Resumo Financeiro Consolidado -->
    <div class="bento-card col-span-full lg:col-span-8">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
            <h2 class="font-bold" style="font-size: 1.25rem;">
                <i data-lucide="wallet" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i>
                Resumo Financeiro
            </h2>
            <a href="{{ route('provider.financial') }}" class="btn btn-outline" style="padding:0.5rem 1rem;font-size:0.875rem;">
                <i data-lucide="arrow-right" style="width:1rem;height:1rem;"></i>
                Ver Detalhes
            </a>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1.5rem;">
            <div style="text-align:center;padding:1rem;border-radius:0.75rem;background:rgba(59,130,246,0.05);">
                <div style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:0.5rem;">A Receber</div>
                <div style="font-size:2rem;font-weight:700;color:var(--color-info);">
                    R$ {{ number_format($stats['pending_receivable'], 2, ',', '.') }}
                </div>
                @if($stats['pending_receivable'] > 0)
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">
                    Cliente pagou, aguardando saque
                </div>
                @endif
            </div>

            <div style="text-align:center;padding:1rem;border-radius:0.75rem;background:rgba(16,185,129,0.05);">
                <div style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:0.5rem;">Disponível p/ Saque</div>
                <div style="font-size:2rem;font-weight:700;color:var(--color-success);">
                    R$ {{ number_format($stats['available_withdrawal'], 2, ',', '.') }}
                </div>
                @if($stats['pending_requests'] > 0)
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">
                    R$ {{ number_format($stats['pending_requests'], 2, ',', '.') }} em análise
                </div>
                @endif
            </div>

            <div style="text-align:center;padding:1rem;border-radius:0.75rem;background:rgba(107,114,128,0.05);">
                <div style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:0.5rem;">Total Recebido</div>
                <div style="font-size:2rem;font-weight:700;color:var(--text-primary);">
                    R$ {{ number_format($stats['total_received'], 2, ',', '.') }}
                </div>
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">
                    Todos os tempos
                </div>
            </div>
        </div>
    </div>

    <!-- Ações Rápidas -->
    <div class="bento-card col-span-full lg:col-span-4">
        <h2 class="font-bold mb-4" style="font-size: 1.125rem;">Ações Rápidas</h2>
        <div style="display:flex;flex-direction:column;gap:0.75rem;">
            <a href="{{ route('provider.orders.create') }}" class="btn btn-primary" style="width:100%;justify-content:center;">
                <i data-lucide="plus" style="width:1rem;height:1rem"></i> Nova Ordem
            </a>
            <a href="{{ route('provider.orders') }}" class="btn btn-outline" style="width:100%;justify-content:center;">
                <i data-lucide="list" style="width:1rem;height:1rem"></i> Todas as Ordens
            </a>
            <a href="{{ route('provider.financial') }}" class="btn btn-outline" style="width:100%;justify-content:center;">
                <i data-lucide="banknote" style="width:1rem;height:1rem"></i> Solicitar Saque
            </a>
        </div>

        <div style="margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--border-color);">
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;text-align:center;">
                <div>
                    <div style="font-size:1.5rem;font-weight:700;color:var(--color-warning);">{{ $stats['awaiting_payment'] }}</div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">Aguardando Pgto</div>
                </div>
                <div>
                    <div style="font-size:1.5rem;font-weight:700;color:var(--color-secondary);">{{ $stats['completed_month'] }}</div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">Concluídas (mês)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Agenda de Ordens Agendadas -->
    @if(!$upcomingOrders->isEmpty())
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">
            <i data-lucide="calendar-days" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i>
            Agenda (Próximos 30 dias)
        </h2>

        <div style="display:grid;gap:1rem;">
            @php
                $groupedOrders = $upcomingOrders->groupBy(fn($o) => $o->scheduled_date->format('Y-m-d'));
            @endphp

            @foreach($groupedOrders as $date => $ordersOnDate)
                @php
                    $dateObj = \Carbon\Carbon::parse($date);
                    $isToday = $dateObj->isToday();
                    $isTomorrow = $dateObj->isTomorrow();
                @endphp
                
                <div style="border-left:4px solid {{ $isToday ? 'var(--color-success)' : ($isTomorrow ? 'var(--color-warning)' : 'var(--color-info)') }};padding-left:1rem;background:rgba({{ $isToday ? '16,185,129' : ($isTomorrow ? '245,158,11' : '59,130,246') }},0.03);padding:1rem;border-radius:0.5rem;">
                    <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem;">
                        <div>
                            <div style="font-weight:700;font-size:1.125rem;color:var(--text-primary);">
                                {{ $dateObj->format('d/m/Y') }} · {{ ucfirst($dateObj->locale('pt_BR')->translatedFormat('l')) }}
                            </div>
                            @if($isToday)
                                <span style="display:inline-block;padding:0.125rem 0.5rem;background:var(--color-success);color:white;border-radius:0.25rem;font-size:0.75rem;font-weight:600;margin-top:0.25rem;">HOJE</span>
                            @elseif($isTomorrow)
                                <span style="display:inline-block;padding:0.125rem 0.5rem;background:var(--color-warning);color:white;border-radius:0.25rem;font-size:0.75rem;font-weight:600;margin-top:0.25rem;">AMANHÃ</span>
                            @else
                                <div style="font-size:0.875rem;color:var(--text-muted);margin-top:0.25rem;">
                                    {{ $dateObj->diffForHumans() }}
                                </div>
                            @endif
                        </div>
                        <div style="font-size:0.875rem;color:var(--text-secondary);font-weight:600;">
                            {{ $ordersOnDate->count() }} {{ $ordersOnDate->count() === 1 ? 'ordem' : 'ordens' }}
                        </div>
                    </div>

                    <div style="display:grid;gap:0.75rem;">
                        @foreach($ordersOnDate as $order)
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem;background:var(--bg-secondary);border-radius:0.5rem;gap:1rem;flex-wrap:wrap;">
                                <div style="flex:1;min-width:200px;">
                                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem;">
                                        @if($order->start_time)
                                            <span style="font-weight:700;color:var(--color-info);font-size:0.875rem;">
                                                {{ \Carbon\Carbon::parse($order->start_time)->format('H:i') }}
                                            </span>
                                        @endif
                                        <span style="font-weight:600;color:var(--text-primary);">
                                            {{ $order->service->name ?? '-' }}
                                        </span>
                                    </div>
                                    <div style="font-size:0.875rem;color:var(--text-secondary);">
                                        Cliente: {{ $order->associate ? $order->associate->name : 'Avulso' }}
                                    </div>
                                    @if($order->location)
                                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">
                                            <i data-lucide="map-pin" style="width:0.75rem;height:0.75rem;display:inline;vertical-align:text-bottom;"></i>
                                            {{ $order->location }}
                                        </div>
                                    @endif
                                </div>
                                <a href="{{ route('provider.orders.show', $order->id) }}" class="btn btn-outline" style="padding:0.375rem 0.75rem;font-size:0.875rem;">
                                    Ver Detalhes
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @else
        <div class="bento-card col-span-full">
            <div style="text-align:center;padding:2rem;">
                <i data-lucide="calendar-x" style="width:3rem;height:3rem;color:var(--text-muted);margin:0 auto 1rem;"></i>
                <p style="color:var(--text-muted);">Nenhuma ordem agendada para os próximos 30 dias</p>
                <a href="{{ route('provider.orders.create') }}" class="btn btn-primary" style="margin-top:1rem;">
                    <i data-lucide="plus" style="width:1rem;height:1rem"></i> Criar Nova Ordem
                </a>
            </div>
        </div>
    @endif

    <!-- Ordens Recentes -->
    @if(!$recentOrders->isEmpty())
    <div class="bento-card col-span-full">
        <h2 class="font-bold mb-4" style="font-size: 1.25rem;">
            <i data-lucide="history" style="width:1.25rem;height:1.25rem;display:inline;vertical-align:text-bottom;"></i>
            Atividade Recente
        </h2>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Data</th>
                        <th>Serviço</th>
                        <th>Cliente</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recentOrders as $order)
                    <tr>
                        <td class="font-semibold">{{ $order->number }}</td>
                        <td>{{ $order->scheduled_date?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ optional($order->service)->name ?? '-' }}</td>
                        <td>
                            @if($order->associate_id)
                                {{ $order->associate->name }}
                            @else
                                Avulso
                            @endif
                        </td>
                        <td>
                            <span class="badge badge-{{ $order->status->value }}">
                                {{ $order->status->getLabel() }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('provider.orders.show', $order->id) }}" class="btn btn-outline" style="padding:0.375rem 0.75rem;font-size:0.75rem;">
                                Ver
                            </a>
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
