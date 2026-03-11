<x-filament-panels::page>
    @php
        $stats = $this->stats;
        $topProducts = $this->topProducts;
        $paymentBreakdown = $this->paymentBreakdown;
        $weeklyChart = $this->weeklyChart;
        $maxChartValue = collect($weeklyChart)->max('total') ?: 1;
        $totalPayments = collect($paymentBreakdown)->sum('total') ?: 1;
    @endphp

    <style>
        .pdv-dash-grid { display: grid; gap: 1rem; }
        .pdv-stats-grid { grid-template-columns: repeat(4, 1fr); }
        .pdv-content-grid { grid-template-columns: 1fr 1fr; }
        @media (max-width: 1200px) { .pdv-stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .pdv-stats-grid, .pdv-content-grid { grid-template-columns: 1fr; } }

        .pdv-stat-card {
            background: var(--fi-bg, white);
            border: 1px solid rgb(var(--gray-200));
            border-radius: 0.75rem;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
        }
        .pdv-stat-card .stat-icon {
            width: 2.5rem; height: 2.5rem;
            border-radius: 0.5rem;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 0.75rem;
        }
        .pdv-stat-card .stat-value { font-size: 1.5rem; font-weight: 800; }
        .pdv-stat-card .stat-label { font-size: 0.75rem; color: rgb(var(--gray-500)); text-transform: uppercase; letter-spacing: 0.05em; }
        .pdv-stat-card .stat-sub { font-size: 0.8rem; color: rgb(var(--gray-400)); margin-top: 0.25rem; }
        .pdv-stat-card::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 80px; height: 80px;
            border-radius: 50%;
            opacity: 0.08;
            transform: translate(25%, -25%);
        }
        .pdv-stat-card.green::after { background: #10b981; }
        .pdv-stat-card.blue::after { background: #3b82f6; }
        .pdv-stat-card.yellow::after { background: #f59e0b; }
        .pdv-stat-card.red::after { background: #ef4444; }

        .pdv-panel {
            background: var(--fi-bg, white);
            border: 1px solid rgb(var(--gray-200));
            border-radius: 0.75rem;
            padding: 1.25rem;
        }
        .pdv-panel-title {
            font-size: 0.875rem; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.05em;
            color: rgb(var(--gray-500));
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid rgb(var(--gray-100));
        }

        /* Gráfico de barras */
        .chart-bars { display: flex; align-items: flex-end; gap: 8px; height: 100px; }
        .chart-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
        .chart-bar {
            width: 100%; border-radius: 4px 4px 0 0;
            background: linear-gradient(to top, #2563eb, #60a5fa);
            transition: height 0.3s ease;
            position: relative;
        }
        .chart-bar:hover::after {
            content: attr(title);
            position: absolute;
            top: -28px; left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            color: #fff;
            font-size: 10px;
            white-space: nowrap;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .chart-label { font-size: 10px; color: rgb(var(--gray-500)); }

        /* Pagamentos breakdown */
        .payment-bar-row { margin-bottom: 10px; }
        .payment-bar-row label { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 3px; }
        .payment-bar-bg { background: rgb(var(--gray-100)); border-radius: 4px; height: 8px; }
        .payment-bar-fill { height: 8px; border-radius: 4px; background: linear-gradient(to right, #3b82f6, #60a5fa); }

        /* Top produtos */
        .top-product-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid rgb(var(--gray-100)); }
        .top-product-row:last-child { border-bottom: none; }
        .top-product-rank { width: 1.5rem; height: 1.5rem; border-radius: 50%; background: rgb(var(--gray-100)); font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        .top-product-rank.gold { background: #fef3c7; color: #92400e; }
        .top-product-rank.silver { background: #f1f5f9; color: #475569; }
        .top-product-rank.bronze { background: #faf5eb; color: #78350f; }

        /* Período seletor */
        .period-selector {
            display: flex; gap: 0.5rem; flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .period-btn {
            padding: 0.375rem 1rem;
            border: 1px solid rgb(var(--gray-300));
            border-radius: 9999px;
            font-size: 0.8rem;
            cursor: pointer;
            background: transparent;
            color: rgb(var(--gray-600));
            transition: all 0.15s;
        }
        .period-btn.active, .period-btn:hover {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        /* Ações rápidas */
        .quick-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
        .quick-action-btn {
            display: flex; align-items: center; gap: 0.75rem;
            padding: 0.875rem 1rem;
            border: 1px solid rgb(var(--gray-200));
            border-radius: 0.5rem;
            text-decoration: none;
            color: inherit;
            transition: all 0.15s;
            font-size: 0.875rem;
        }
        .quick-action-btn:hover { border-color: #2563eb; background: #eff6ff; }
        .quick-action-icon { width: 2rem; height: 2rem; border-radius: 0.375rem; display: flex; align-items: center; justify-content: center; }
    </style>

    <style>
        /* Dark theme overrides */
        .dark .pdv-stat-card,
        [data-theme="dark"] .pdv-stat-card {
            background: #0b1220; /* darker panel bg */
            border-color: rgba(148,163,184,0.06);
        }
        .dark .pdv-panel,
        [data-theme="dark"] .pdv-panel {
            background: #061226;
            border-color: rgba(148,163,184,0.06);
        }
        .dark .pdv-stat-card .stat-label,
        [data-theme="dark"] .pdv-stat-card .stat-label,
        .dark .chart-label,
        [data-theme="dark"] .chart-label {
            color: rgba(203,213,225,0.8);
        }
        .dark .pdv-stat-card .stat-sub,
        [data-theme="dark"] .pdv-stat-card .stat-sub {
            color: rgba(148,163,184,0.9);
        }
        .dark .pdv-stat-card .stat-value,
        [data-theme="dark"] .pdv-stat-card .stat-value {
            color: #e6f4ff;
        }
        .dark .chart-bar::after,
        [data-theme="dark"] .chart-bar::after {
            background: #0f172a;
            color: #e2e8f0;
        }
        .dark .period-btn,
        [data-theme="dark"] .period-btn {
            border-color: rgba(148,163,184,0.06);
            color: rgba(203,213,225,0.9);
        }
        .dark .period-btn.active,
        [data-theme="dark"] .period-btn.active {
            background: #1f2937;
            color: #fff;
            border-color: #111827;
        }
        .pdv-theme-toggle {
            margin-left: auto; display:inline-flex; align-items:center; gap:0.5rem;
        }
        .pdv-theme-toggle button { background: transparent; border: none; cursor: pointer; color: inherit }
    </style>

    {{-- Seletor de período --}}
    <div class="period-selector">
        <span style="font-size:0.875rem;color:rgb(var(--gray-500));align-self:center">Período:</span>
        <div class="pdv-theme-toggle" role="group" aria-label="Tema">
            <button id="pdv-theme-toggle" title="Alternar tema">🌙</button>
        </div>
        @foreach([7 => '7 dias', 15 => '15 dias', 30 => '30 dias', 60 => '60 dias', 90 => '90 dias'] as $days => $label)
        <button
            wire:click="$set('period', {{ $days }})"
            class="period-btn {{ $this->period == $days ? 'active' : '' }}"
        >{{ $label }}</button>
        @endforeach
    </div>

    {{-- Cards de stats --}}
    <div class="pdv-dash-grid pdv-stats-grid mb-6">
        {{-- Total Hoje --}}
        <div class="pdv-stat-card green">
            <div class="stat-icon" style="background:#dcfce7">
                <x-heroicon-o-currency-dollar class="w-5 h-5 text-green-600"/>
            </div>
            <div class="stat-value" style="color:#10b981">R$ {{ number_format($stats['total_today'] ?? 0, 2, ',', '.') }}</div>
            <div class="stat-label">Total hoje</div>
            <div class="stat-sub">{{ $stats['count_today'] ?? 0 }} venda(s)</div>
        </div>

        {{-- Ticket médio --}}
        <div class="pdv-stat-card blue">
            <div class="stat-icon" style="background:#dbeafe">
                <x-heroicon-o-receipt-percent class="w-5 h-5 text-blue-600"/>
            </div>
            <div class="stat-value" style="color:#3b82f6">R$ {{ number_format($stats['ticket_today'] ?? 0, 2, ',', '.') }}</div>
            <div class="stat-label">Ticket médio (hoje)</div>
            <div class="stat-sub">Últ. {{ $this->period }} dias: R$ {{ number_format(($stats['count_period'] ?? 0) > 0 ? ($stats['total_period'] ?? 0) / ($stats['count_period'] ?? 1) : 0, 2, ',', '.') }}</div>
        </div>

        {{-- Fiado pendente --}}
        <div class="pdv-stat-card yellow">
            <div class="stat-icon" style="background:#fef3c7">
                <x-heroicon-o-clock class="w-5 h-5 text-yellow-600"/>
            </div>
            <div class="stat-value" style="color:#f59e0b">R$ {{ number_format($stats['fiado_amount'] ?? 0, 2, ',', '.') }}</div>
            <div class="stat-label">Fiado pendente</div>
            <div class="stat-sub">{{ $stats['fiado_count'] ?? 0 }} conta(s) em aberto</div>
        </div>

        {{-- Estoque baixo --}}
        <div class="pdv-stat-card {{ ($stats['low_stock'] ?? 0) > 0 ? 'red' : 'green' }}">
            <div class="stat-icon" style="background:{{ ($stats['low_stock'] ?? 0) > 0 ? '#fee2e2' : '#dcfce7' }}">
                <x-heroicon-o-archive-box class="w-5 h-5" style="color:{{ ($stats['low_stock'] ?? 0) > 0 ? '#dc2626' : '#16a34a' }}"/>
            </div>
            <div class="stat-value" style="color:{{ ($stats['low_stock'] ?? 0) > 0 ? '#ef4444' : '#10b981' }}">
                {{ $stats['low_stock'] ?? 0 }}
            </div>
            <div class="stat-label">Estoque abaixo do mínimo</div>
            <div class="stat-sub">{{ ($stats['low_stock'] ?? 0) > 0 ? 'Atenção necessária' : 'Estoque OK' }}</div>
        </div>
    </div>

    <script>
        (function(){
            const toggle = document.getElementById('pdv-theme-toggle');
            if (!toggle) return;

            const apply = (mode) => {
                const html = document.documentElement;
                if (mode === 'dark') {
                    html.classList.add('dark');
                    html.setAttribute('data-theme','dark');
                    toggle.textContent = '☀️';
                } else {
                    html.classList.remove('dark');
                    html.setAttribute('data-theme','light');
                    toggle.textContent = '🌙';
                }
            };

            const saved = localStorage.getItem('pdv_theme') || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            apply(saved);

            toggle.addEventListener('click', function(){
                const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                localStorage.setItem('pdv_theme', next);
                apply(next);
            });
        })();
    </script>

    {{-- Linha período --}}
    <div style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; background: rgb(var(--gray-50)); border-radius: 0.5rem; border: 1px solid rgb(var(--gray-200));">
        <span style="font-size: 0.875rem; color: rgb(var(--gray-600))">
            Últimos <strong>{{ $this->period }} dias</strong>:
            <strong style="color:#10b981">R$ {{ number_format($stats['total_period'] ?? 0, 2, ',', '.') }}</strong>
            em {{ $stats['count_period'] ?? 0 }} venda(s)
        </span>
    </div>

    <div class="pdv-dash-grid pdv-content-grid">
        {{-- Gráfico de vendas (7 dias) --}}
        <div class="pdv-panel">
            <div class="pdv-panel-title">Vendas — Últimos 7 dias</div>
            @if(collect($weeklyChart)->sum('total') > 0)
            <div class="chart-bars">
                @foreach($weeklyChart as $day)
                @php $pct = $maxChartValue > 0 ? ($day['total'] / $maxChartValue * 100) : 0; @endphp
                <div class="chart-bar-wrap">
                    <div class="chart-bar" style="height: {{ max(4, $pct) }}%" title="R$ {{ number_format($day['total'], 2, ',', '.') }}"></div>
                    <span class="chart-label">{{ $day['date'] }}</span>
                </div>
                @endforeach
            </div>
            <div style="margin-top:0.75rem;display:flex;justify-content:space-between;font-size:0.75rem;color:rgb(var(--gray-400))">
                <span>0</span>
                <span>R$ {{ number_format($maxChartValue, 0, ',', '.') }}</span>
            </div>
            @else
            <div style="text-align:center;padding:2rem;color:rgb(var(--gray-400))">
                <x-heroicon-o-chart-bar class="w-10 h-10 mx-auto mb-2"/>
                <p>Sem dados para exibir</p>
            </div>
            @endif
        </div>

        {{-- Ações rápidas + link PDV --}}
        <div class="pdv-panel">
            <div class="pdv-panel-title">Ações Rápidas</div>
            <div class="quick-actions">
                <a href="{{ session('tenant_slug') ? '/' . session('tenant_slug') . '/pdv' : '#' }}" target="_blank" class="quick-action-btn">
                    <div class="quick-action-icon" style="background:#dbeafe">
                        <x-heroicon-o-computer-desktop class="w-4 h-4 text-blue-600"/>
                    </div>
                    <span>Abrir PDV</span>
                </a>
                <a href="{{ \App\Filament\Resources\PdvSaleResource::getUrl('index') }}" class="quick-action-btn">
                    <div class="quick-action-icon" style="background:#dcfce7">
                        <x-heroicon-o-list-bullet class="w-4 h-4 text-green-600"/>
                    </div>
                    <span>Ver Vendas</span>
                </a>
                <a href="{{ \App\Filament\Resources\PdvSaleResource::getUrl('index') }}?tableFilters[is_fiado][value]=1" class="quick-action-btn">
                    <div class="quick-action-icon" style="background:#fef3c7">
                        <x-heroicon-o-clock class="w-4 h-4 text-yellow-600"/>
                    </div>
                    <span>Fiado Pendente</span>
                </a>
                <a href="{{ session('tenant_slug') ? '/' . session('tenant_slug') . '/pdv/history' : '#' }}" class="quick-action-btn">
                    <div class="quick-action-icon" style="background:#f3e8ff">
                        <x-heroicon-o-archive-box-arrow-down class="w-4 h-4 text-purple-600"/>
                    </div>
                    <span>Histórico PDV</span>
                </a>
            </div>
        </div>
    </div>

    <div class="pdv-dash-grid pdv-content-grid mt-4">
        {{-- Top produtos --}}
        <div class="pdv-panel">
            <div class="pdv-panel-title">Top Produtos — Últimos {{ $this->period }} dias</div>
            @forelse($topProducts as $i => $product)
            <div class="top-product-row">
                <div class="top-product-rank {{ $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : '')) }}">{{ $i + 1 }}</div>
                <div style="flex:1">
                    <div style="font-size:0.875rem;font-weight:500">{{ $product->name }}</div>
                    <div style="font-size:0.75rem;color:rgb(var(--gray-500))">{{ number_format($product->total_qty, 0, ',', '.') }} unidades</div>
                </div>
                <div style="font-size:0.875rem;font-weight:700;color:#10b981">
                    R$ {{ number_format($product->total_value, 2, ',', '.') }}
                </div>
            </div>
            @empty
            <div style="text-align:center;padding:2rem;color:rgb(var(--gray-400))">
                <x-heroicon-o-shopping-bag class="w-10 h-10 mx-auto mb-2"/>
                <p>Sem vendas no período</p>
            </div>
            @endforelse
        </div>

        {{-- Pagamentos breakdown --}}
        <div class="pdv-panel">
            <div class="pdv-panel-title">Formas de Pagamento — Últimos {{ $this->period }} dias</div>
            @php
                $methodLabels = [
                    'dinheiro' => 'Dinheiro',
                    'pix' => 'PIX',
                    'cartao' => 'Cartão',
                    'transferencia' => 'Transferência',
                    'cheque' => 'Cheque',
                    'boleto' => 'Boleto',
                    'outro' => 'Outro',
                ];
            @endphp
            @forelse($paymentBreakdown as $payment)
            @php $pct = $totalPayments > 0 ? ($payment->total / $totalPayments * 100) : 0; @endphp
            <div class="payment-bar-row">
                <label>
                    <span>{{ $methodLabels[$payment->payment_method] ?? ucfirst($payment->payment_method) }}</span>
                    <span>R$ {{ number_format($payment->total, 2, ',', '.') }} <small style="color:rgb(var(--gray-400))">({{ number_format($pct, 1) }}%)</small></span>
                </label>
                <div class="payment-bar-bg">
                    <div class="payment-bar-fill" style="width:{{ $pct }}%"></div>
                </div>
            </div>
            @empty
            <div style="text-align:center;padding:2rem;color:rgb(var(--gray-400))">
                <x-heroicon-o-banknotes class="w-10 h-10 mx-auto mb-2"/>
                <p>Sem dados de pagamento no período</p>
            </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
