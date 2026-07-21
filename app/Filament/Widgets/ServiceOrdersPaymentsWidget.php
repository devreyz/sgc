<?php

namespace App\Filament\Widgets;

use App\Enums\ServiceOrderStatus;
use App\Models\ServiceOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServiceOrdersPaymentsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $tenantId = session('tenant_id');

        $summary = ServiceOrder::where('tenant_id', $tenantId)
            ->where('status', ServiceOrderStatus::COMPLETED)
            ->selectRaw("COALESCE(SUM(CASE WHEN associate_payment_status = 'pending' THEN actual_quantity * unit_price ELSE 0 END), 0) as pending_from_associates")
            ->selectRaw("SUM(CASE WHEN associate_payment_status = 'pending' THEN 1 ELSE 0 END) as pending_from_associates_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN associate_payment_status = 'paid' AND provider_payment_status = 'pending' THEN provider_payment ELSE 0 END), 0) as pending_to_providers")
            ->selectRaw("SUM(CASE WHEN associate_payment_status = 'paid' AND provider_payment_status = 'pending' THEN 1 ELSE 0 END) as pending_to_providers_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN associate_payment_status = 'paid' THEN (actual_quantity * unit_price) - provider_payment ELSE 0 END), 0) as cooperative_profit")
            ->selectRaw('COALESCE(SUM((actual_quantity * unit_price) - provider_payment), 0) as potential_profit')
            ->first();

        $pendingFromAssociates = (float) ($summary?->pending_from_associates ?? 0);
        $countPendingFromAssociates = (int) ($summary?->pending_from_associates_count ?? 0);
        $pendingToProviders = (float) ($summary?->pending_to_providers ?? 0);
        $countPendingToProviders = (int) ($summary?->pending_to_providers_count ?? 0);
        $cooperativeProfit = (float) ($summary?->cooperative_profit ?? 0);
        $potentialProfit = (float) ($summary?->potential_profit ?? 0);

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
