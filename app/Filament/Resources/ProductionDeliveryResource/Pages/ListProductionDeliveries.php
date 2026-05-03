<?php

namespace App\Filament\Resources\ProductionDeliveryResource\Pages;

use App\Enums\DeliveryStatus;
use App\Filament\Resources\ProductionDeliveryResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListProductionDeliveries extends ListRecords
{
    protected static string $resource = ProductionDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nova Recepção'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'receptions' => Tab::make('Recepções')
                ->icon('heroicon-o-inbox-arrow-down')
                ->badge(fn () => static::getResource()::getModel()
                    ::where('tenant_id', session('tenant_id'))
                    ->whereNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::PENDING)
                    ->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('parent_delivery_id')),

            'distributions' => Tab::make('Distribuições')
                ->icon('heroicon-o-arrows-right-left')
                ->badge(fn () => static::getResource()::getModel()
                    ::where('tenant_id', session('tenant_id'))
                    ->whereNotNull('parent_delivery_id')
                    ->where('paid', false)
                    ->where('status', DeliveryStatus::APPROVED)
                    ->count() ?: null)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('parent_delivery_id')),
        ];
    }
}
