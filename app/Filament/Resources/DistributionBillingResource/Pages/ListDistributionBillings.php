<?php

namespace App\Filament\Resources\DistributionBillingResource\Pages;

use App\Filament\Resources\DistributionBillingResource;
use Filament\Resources\Pages\ListRecords;

class ListDistributionBillings extends ListRecords
{
    protected static string $resource = DistributionBillingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('create')
                ->label('Novo Faturamento')
                ->url(static::getResource()::getUrl('create'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
