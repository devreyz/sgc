<?php

namespace App\Filament\Resources\ServiceOrderResource\Pages;

use App\Filament\Resources\ServiceOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\ServiceOrderStatus;

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
            'scheduled' => Tab::make('Agendadas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrderStatus::SCHEDULED))
                ->badge(fn () => \App\Models\ServiceOrder::where('status', ServiceOrderStatus::SCHEDULED)->count()),
            'executed' => Tab::make('Executadas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrderStatus::COMPLETED)),
            'billed' => Tab::make('Faturadas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ServiceOrderStatus::BILLED)),
        ];
    }
}
