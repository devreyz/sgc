<?php

namespace App\Filament\Widgets;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\Revenue;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $currentMonth = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        $monthlyExpenses = Expense::where('status', ExpenseStatus::PAID->value)
            ->whereBetween('paid_date', [$currentMonth, $currentMonthEnd])
            ->sum('amount');

        $monthlyRevenue = Revenue::where('status', 'received')
            ->whereBetween('received_date', [$currentMonth, $currentMonthEnd])
            ->sum('amount');

        $pendingExpenses = Expense::where('status', ExpenseStatus::PENDING->value)->sum('amount');

        $overdueExpenses = Expense::where('status', ExpenseStatus::PENDING->value)
            ->where('due_date', '<', now())
            ->count();

        return [
            Stat::make('Receita do Mês', 'R$ ' . number_format($monthlyRevenue, 2, ',', '.'))
                ->description('Taxas administrativas')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Despesas do Mês', 'R$ ' . number_format($monthlyExpenses, 2, ',', '.'))
                ->description('Pagas')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),

            Stat::make('Despesas Pendentes', 'R$ ' . number_format($pendingExpenses, 2, ',', '.'))
                ->description($overdueExpenses . ' vencidas')
                ->descriptionIcon('heroicon-m-clock')
                ->color($overdueExpenses > 0 ? 'danger' : 'warning'),
        ];
    }
}
