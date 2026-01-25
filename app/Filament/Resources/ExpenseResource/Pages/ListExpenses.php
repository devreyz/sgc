<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\ExpenseStatus;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas'),
            'pending' => Tab::make('Pendentes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ExpenseStatus::PENDING)),
            'overdue' => Tab::make('Vencidas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('due_date', '<', now())->where('status', ExpenseStatus::PENDING))
                ->badge(fn () => \App\Models\Expense::where('due_date', '<', now())->where('status', ExpenseStatus::PENDING)->count())
                ->badgeColor('danger'),
            'paid' => Tab::make('Pagas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ExpenseStatus::PAID)),
        ];
    }
}
