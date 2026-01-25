<?php

namespace App\Filament\Widgets;

use App\Models\Associate;
use App\Services\FinancialDistributionService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssociatesBalanceWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $financialService = app(FinancialDistributionService::class);
        $totalBalance = $financialService->getTotalAssociatesBalance();
        $associatesCount = Associate::count();
        $activeAssociates = Associate::whereHas('user', fn ($q) => $q->where('status', true))->count();

        return [
            Stat::make('Total Associados', $associatesCount)
                ->description($activeAssociates . ' ativos')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Saldo Total Associados', 'R$ ' . number_format($totalBalance, 2, ',', '.'))
                ->description('Passivo da cooperativa')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($totalBalance >= 0 ? 'success' : 'danger'),

            Stat::make('DAPs Vencendo', Associate::whereBetween('dap_caf_expiry', [now(), now()->addDays(30)])->count())
                ->description('Próximos 30 dias')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
        ];
    }
}
