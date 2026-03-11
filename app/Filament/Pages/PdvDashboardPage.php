<?php

namespace App\Filament\Pages;

use App\Models\PdvSale;
use App\Models\Product;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class PdvDashboardPage extends Page
{
    use HasPageShield;

    protected static string $view = 'filament.pages.pdv-dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'PDV';

    protected static ?string $navigationLabel = 'Dashboard PDV';

    protected static ?string $title = 'Dashboard PDV';

    protected static ?int $navigationSort = 5;

    public array $stats = [];

    public array $topProducts = [];

    public array $paymentBreakdown = [];

    public array $weeklyChart = [];

    public int $period = 30; // dias

    public function mount(): void
    {
        $this->loadData();
    }

    public function updatedPeriod(): void
    {
        $this->loadData();
    }

    private function loadData(): void
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return;
        }

        $from = now()->subDays((int) $this->period)->startOfDay();
        $today = today();

        // Stats gerais
        $todaySales = PdvSale::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDate('created_at', $today);

        $periodSales = PdvSale::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', $from);

        $fiadoPending = PdvSale::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('is_fiado', true)
            ->whereRaw('total > amount_paid');

        // Calcular fiado restante (total - amount_paid menos pagamentos de fiado feitos)
        $fiadoSales = (clone $fiadoPending)->with('fiadoPayments')->get();
        $fiadoTotalRemaining = $fiadoSales->sum(fn ($s) => $s->fiado_remaining);

        $this->stats = [
            'total_today' => (float) $todaySales->sum('total'),
            'count_today' => (int) $todaySales->count(),
            'ticket_today' => $todaySales->count() > 0
                ? (float) $todaySales->sum('total') / $todaySales->count()
                : 0,
            'total_period' => (float) $periodSales->sum('total'),
            'count_period' => (int) $periodSales->count(),
            'fiado_amount' => $fiadoTotalRemaining,
            'fiado_count' => (int) $fiadoPending->count(),
            'low_stock' => (int) Product::where('tenant_id', $tenantId)
                ->where('status', true)
                ->whereColumn('current_stock', '<=', 'min_stock')
                ->count(),
        ];

        // Top 5 produtos mais vendidos no período
        $this->topProducts = DB::table('pdv_sale_items')
            ->join('pdv_sales', 'pdv_sale_items.pdv_sale_id', '=', 'pdv_sales.id')
            ->join('products', 'pdv_sale_items.product_id', '=', 'products.id')
            ->where('pdv_sales.tenant_id', $tenantId)
            ->where('pdv_sales.status', 'completed')
            ->where('pdv_sales.created_at', '>=', $from)
            ->groupBy('products.id', 'products.name')
            ->select('products.name', DB::raw('SUM(pdv_sale_items.quantity) as total_qty'), DB::raw('SUM(pdv_sale_items.total) as total_value'))
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->toArray();

        // Breakdown por forma de pagamento no período
        $this->paymentBreakdown = DB::table('pdv_sale_payments')
            ->join('pdv_sales', 'pdv_sale_payments.pdv_sale_id', '=', 'pdv_sales.id')
            ->where('pdv_sales.tenant_id', $tenantId)
            ->where('pdv_sales.status', 'completed')
            ->where('pdv_sales.created_at', '>=', $from)
            ->groupBy('pdv_sale_payments.payment_method')
            ->select('pdv_sale_payments.payment_method', DB::raw('SUM(pdv_sale_payments.amount) as total'))
            ->orderByDesc('total')
            ->get()
            ->toArray();

        // Chart: vendas dos últimos 7 dias
        $this->weeklyChart = collect(range(6, 0))->map(function ($daysAgo) use ($tenantId) {
            $date = today()->subDays($daysAgo);
            $total = PdvSale::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereDate('created_at', $date)
                ->sum('total');

            return [
                'date' => $date->format('d/m'),
                'total' => (float) $total,
            ];
        })->toArray();
    }
}
