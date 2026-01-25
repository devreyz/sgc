<?php

namespace App\Filament\Resources\CollectivePurchaseResource\Pages;

use App\Filament\Resources\CollectivePurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\CollectivePurchaseStatus;

class ListCollectivePurchases extends ListRecords
{
    protected static string $resource = CollectivePurchaseResource::class;

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
            'open' => Tab::make('Abertas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CollectivePurchaseStatus::OPEN))
                ->badge(fn () => \App\Models\CollectivePurchase::where('status', CollectivePurchaseStatus::OPEN)->count()),
            'closed' => Tab::make('Fechadas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CollectivePurchaseStatus::CLOSED)),
            'delivered' => Tab::make('Entregues')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CollectivePurchaseStatus::DELIVERED)),
        ];
    }
}
