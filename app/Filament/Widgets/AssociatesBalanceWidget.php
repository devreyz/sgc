<?php

namespace App\Filament\Widgets;

use App\Models\Associate;
use App\Models\AssociateLedger;
use App\Enums\LedgerType;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssociatesBalanceWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    
    protected static ?string $heading = 'Associados';

    protected function getStats(): array
    {
        $totalAssociates = Associate::count();
        $activeAssociates = Associate::whereHas('user', fn ($q) => $q->where('status', true))->count();
        
        // Saldo total dos associados (quanto a cooperativa deve pagar)
        $totalBalance = AssociateLedger::selectRaw('associate_id, MAX(id) as last_id')
            ->groupBy('associate_id')
            ->get()
            ->map(fn ($item) => AssociateLedger::find($item->last_id))
            ->sum('balance_after');
        
        // Contar associados com saldo positivo (tem a receber) e negativo (devem)
        $withCredit = AssociateLedger::selectRaw('associate_id, MAX(id) as last_id')
            ->groupBy('associate_id')
            ->get()
            ->map(fn ($item) => AssociateLedger::find($item->last_id))
            ->where('balance_after', '>', 0)
            ->count();
            
        $withDebt = AssociateLedger::selectRaw('associate_id, MAX(id) as last_id')
            ->groupBy('associate_id')
            ->get()
            ->map(fn ($item) => AssociateLedger::find($item->last_id))
            ->where('balance_after', '<', 0)
            ->count();

        // DAPs vencendo
        $dapsExpiring = Associate::whereBetween('dap_caf_expiry', [now(), now()->addDays(30)])->count();

        return [
            Stat::make('Total de Associados', $totalAssociates)
                ->description("$activeAssociates ativos")
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Saldo Total Associados', 'R$ ' . number_format($totalBalance, 2, ',', '.'))
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
