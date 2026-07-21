<?php

namespace App\Filament\Widgets;

use App\Enums\CashMovementType;
use App\Enums\ExpenseStatus;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static ?string $pollingInterval = null;

    protected ?string $heading = 'Resumo Financeiro';

    protected function getStats(): array
    {
        $tenantId = session('tenant_id');

        $accounts = BankAccount::where('tenant_id', $tenantId)
            ->where('status', true)
            ->selectRaw('COALESCE(SUM(current_balance), 0) as total_balance')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'caixa' THEN current_balance ELSE 0 END), 0) as cash_balance")
            ->first();
        $totalInAccounts = (float) ($accounts?->total_balance ?? 0);
        $cashBalance = (float) ($accounts?->cash_balance ?? 0);

        // Movimentos do mês
        $currentMonth = now()->startOfMonth();
        $movements = CashMovement::where('tenant_id', $tenantId)
            ->whereBetween('movement_date', [$currentMonth, now()])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as income', [CashMovementType::INCOME->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as expense', [CashMovementType::EXPENSE->value])
            ->first();
        $monthlyIncome = (float) ($movements?->income ?? 0);
        $monthlyExpense = (float) ($movements?->expense ?? 0);

        // Despesas pendentes
        $expenses = Expense::where('tenant_id', $tenantId)
            ->where('status', ExpenseStatus::PENDING)
            ->selectRaw('COALESCE(SUM(amount), 0) as pending_total')
            ->selectRaw('SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) as overdue_count', [now()])
            ->first();
        $pendingExpenses = (float) ($expenses?->pending_total ?? 0);
        $overdueCount = (int) ($expenses?->overdue_count ?? 0);

        return [
            Stat::make('Saldo Total', 'R$ '.number_format($totalInAccounts, 2, ',', '.'))
                ->description('Caixa: R$ '.number_format($cashBalance, 2, ',', '.'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Entradas do Mês', 'R$ '.number_format($monthlyIncome, 2, ',', '.'))
                ->description(now()->format('F/Y'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Saídas do Mês', 'R$ '.number_format($monthlyExpense, 2, ',', '.'))
                ->description('Saldo: R$ '.number_format($monthlyIncome - $monthlyExpense, 2, ',', '.'))
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($monthlyIncome >= $monthlyExpense ? 'success' : 'danger'),

            Stat::make('Despesas Pendentes', 'R$ '.number_format($pendingExpenses, 2, ',', '.'))
                ->description($overdueCount > 0 ? "$overdueCount vencida(s)" : 'Em dia')
                ->descriptionIcon($overdueCount > 0 ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($overdueCount > 0 ? 'danger' : 'warning'),
        ];
    }
}
