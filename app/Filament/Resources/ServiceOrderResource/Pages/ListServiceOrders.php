<?php

namespace App\Filament\Resources\ServiceOrderResource\Pages;

use App\Filament\Resources\ServiceOrderResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListServiceOrders extends ListRecords
{
    protected static string $resource = ServiceOrderResource::class;

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
            'scheduled' => Tab::make('Agendadas')->modifyQueryUsing(fn (Builder $query) => $query->where('operational_status', 'scheduled')),
            'progress' => Tab::make('Em execução')->modifyQueryUsing(fn (Builder $query) => $query->where('operational_status', 'in_progress')),
            'review' => Tab::make('Conferência')->modifyQueryUsing(fn (Builder $query) => $query->where('operational_status', 'submitted')),
            'validated' => Tab::make('Validadas')->modifyQueryUsing(fn (Builder $query) => $query->where('operational_status', 'validated')),
        ];
    }
}
