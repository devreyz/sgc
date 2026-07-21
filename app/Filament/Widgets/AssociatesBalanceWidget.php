<?php

namespace App\Filament\Widgets;

use App\Models\Associate;
use App\Models\AssociateLedger;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AssociatesBalanceWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = null;

    protected ?string $heading = 'Associados';

    protected function getStats(): array
    {
        $tenantId = session('tenant_id');

        $associateCounts = Associate::where('tenant_id', $tenantId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN EXISTS (SELECT 1 FROM users WHERE users.id = associates.user_id AND users.status = 1) THEN 1 ELSE 0 END) as active')
            ->first();
        $totalAssociates = (int) ($associateCounts?->total ?? 0);
        $activeAssociates = (int) ($associateCounts?->active ?? 0);

        // Saldo total dos associados (quanto a cooperativa deve pagar)
        $latestLedgerIds = AssociateLedger::where('tenant_id', $tenantId)
            ->selectRaw('associate_id, MAX(id) as last_id')
            ->groupBy('associate_id');
        $balances = DB::query()
            ->fromSub($latestLedgerIds, 'latest_ledgers')
            ->join('associate_ledgers as ledger', 'ledger.id', '=', 'latest_ledgers.last_id')
            ->selectRaw('COALESCE(SUM(ledger.balance_after), 0) as total_balance')
            ->selectRaw('SUM(CASE WHEN ledger.balance_after > 0 THEN 1 ELSE 0 END) as with_credit')
            ->selectRaw('SUM(CASE WHEN ledger.balance_after < 0 THEN 1 ELSE 0 END) as with_debt')
            ->first();
        $totalBalance = (float) ($balances?->total_balance ?? 0);
        $withCredit = (int) ($balances?->with_credit ?? 0);
        $withDebt = (int) ($balances?->with_debt ?? 0);

        // DAPs vencendo
        $dapsExpiring = Associate::where('tenant_id', $tenantId)
            ->whereBetween('dap_caf_expiry', [now(), now()->addDays(30)])
            ->count();

        return [
            Stat::make('Total de Associados', $totalAssociates)
                ->description("$activeAssociates ativos")
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Saldo Total Associados', 'R$ '.number_format($totalBalance, 2, ',', '.'))
                ->description("$withCredit com crédito | $withDebt com débito")
                ->descriptionIcon('heroicon-m-scale')
                ->color($totalBalance >= 0 ? 'warning' : 'success'),

            Stat::make('DAPs Vencendo', $dapsExpiring)
                ->description('Próximos 30 dias')
                ->descriptionIcon($dapsExpiring > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge')
                ->color($dapsExpiring > 0 ? 'warning' : 'success'),
        ];
    }
}
