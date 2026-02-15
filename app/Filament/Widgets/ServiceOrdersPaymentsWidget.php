<?php

namespace App\Filament\Widgets;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ServiceOrdersPaymentsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $tenantId = session('tenant_id');
        
        // Valores a receber dos associados
        $pendingFromAssociates = ServiceOrder::where('tenant_id', $tenantId)
            ->where('status', ServiceOrderStatus::COMPLETED)
            ->where('associate_payment_status', 'pending')
            ->sum(DB::raw('actual_quantity * unit_price'));

        $countPendingFromAssociates = ServiceOrder::where('tenant_id', $tenantId)
            ->where('status', ServiceOrderStatus::COMPLETED)
            ->where('associate_payment_status', 'pending')
            ->count();

        // Valores a pagar aos prestadores (onde associado já pagou)
        $pendingToProviders = ServiceOrder::where('tenant_id', $tenantId)
            ->where('status', ServiceOrderStatus::COMPLETED)
            ->where('associate_payment_status', 'paid')
            ->where('provider_payment_status', 'pending')
            ->sum('provider_payment');

        $countPendingToProviders = ServiceOrder::where('tenant_id', $tenantId)
            ->where('status', ServiceOrderStatus::COMPLETED)
            ->where('associate_payment_status', 'paid')
            ->where('provider_payment_status', 'pending')
            ->count();

        // Lucro acumulado (receita - pagamento ao prestador) dos pagos
        $totalRevenue = ServiceOrder::where('tenant_id', $tenantId)
            ->where('status', ServiceOrderStatus::COMPLETED)
            ->where('associate_payment_status', 'paid')
            ->sum(DB::raw('actual_quantity * unit_price'));

        $totalProviderPayments = ServiceOrder::where('tenant_id', $tenantId)
            ->where('status', ServiceOrderStatus::COMPLETED)
            ->where('associate_payment_status', 'paid')
            ->sum('provider_payment');

        $cooperativeProfit = $totalRevenue - $totalProviderPayments;

        // Lucro potencial (se todos pagarem)
        $totalPotentialRevenue = ServiceOrder::where('tenant_id', $tenantId)
            ->where('status', ServiceOrderStatus::COMPLETED)
            ->sum(DB::raw('actual_quantity * unit_price'));

        $totalPotentialProviderPayments = ServiceOrder::where('tenant_id', $tenantId)
            ->where('status', ServiceOrderStatus::COMPLETED)
            ->sum('provider_payment');

        $potentialProfit = $totalPotentialRevenue - $totalPotentialProviderPayments;

        return [
            Stat::make('A Receber dos Associados', 'R$ '.number_format($pendingFromAssociates, 2, ',', '.'))
                ->description($countPendingFromAssociates.' ordens pendentes')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('A Pagar aos Prestadores', 'R$ '.number_format($pendingToProviders, 2, ',', '.'))
                ->description($countPendingToProviders.' pagamentos pendentes')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->chart([7, 3, 4, 5, 6, 3, 5, 3]),

            Stat::make('Lucro Acumulado', 'R$ '.number_format($cooperativeProfit, 2, ',', '.'))
                ->description('Potencial: R$ '.number_format($potentialProfit, 2, ',', '.'))
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
                ->chart([3, 5, 6, 7, 8, 9, 10, 12]),
        ];
    }
}
