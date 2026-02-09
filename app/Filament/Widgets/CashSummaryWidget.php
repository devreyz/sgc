<?php

namespace App\Filament\Widgets;

use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Enums\CashMovementType;
use App\Enums\ExpenseStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    
    protected ?string $heading = 'Resumo Financeiro';

    protected function getStats(): array
    {
        // Saldo total em contas
        $totalInAccounts = BankAccount::where('status', true)->sum('current_balance');
        
        // Caixa principal
        $mainCash = BankAccount::where('type', 'caixa')->where('status', true)->first();
        $cashBalance = $mainCash ? $mainCash->current_balance : 0;

        // Movimentos do mês
        $currentMonth = now()->startOfMonth();
        $monthlyIncome = CashMovement::where('type', CashMovementType::INCOME)
            ->whereBetween('movement_date', [$currentMonth, now()])
            ->sum('amount');

        $monthlyExpense = CashMovement::where('type', CashMovementType::EXPENSE)
            ->whereBetween('movement_date', [$currentMonth, now()])
            ->sum('amount');

        // Despesas pendentes
        $pendingExpenses = Expense::where('status', ExpenseStatus::PENDING)->sum('amount');
        $overdueCount = Expense::where('status', ExpenseStatus::PENDING)
            ->where('due_date', '<', now())
            ->count();

        return [
            Stat::make('Saldo Total', 'R$ ' . number_format($totalInAccounts, 2, ',', '.'))
                ->description('Caixa: R$ ' . number_format($cashBalance, 2, ',', '.'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary')
                ->chart($this->getBalanceChart()),

            Stat::make('Entradas do Mês', 'R$ ' . number_format($monthlyIncome, 2, ',', '.'))
                ->description(now()->format('F/Y'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Saídas do Mês', 'R$ ' . number_format($monthlyExpense, 2, ',', '.'))
                ->description('Saldo: R$ ' . number_format($monthlyIncome - $monthlyExpense, 2, ',', '.'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($monthlyIncome >= $monthlyExpense ? 'success' : 'danger'),

            Stat::make('Despesas Pendentes', 'R$ ' . number_format($pendingExpenses, 2, ',', '.'))
                ->description($overdueCount > 0 ? "$overdueCount vencida(s)" : 'Em dia')
                ->descriptionIcon($overdueCount > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($overdueCount > 0 ? 'danger' : 'warning'),
        ];
    }

    protected function getBalanceChart(): array
    {
        // Últimos 7 dias de saldo
        return collect(range(6, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);
            
            $lastMovement = CashMovement::whereDate('movement_date', '<=', $date)
                ->orderBy('movement_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            return $lastMovement ? (float) $lastMovement->balance_after : 0;
        })->toArray();
    }
}
