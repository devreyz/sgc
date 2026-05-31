<?php

namespace App\Filament\Resources\PriceTableResource\Pages;

use App\Filament\Resources\PriceTableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPriceTable extends EditRecord
{
    protected static string $resource = PriceTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manage_items')
                ->label('Gerenciar Preços')
                ->icon('heroicon-o-table-cells')
                ->color('primary')
                ->url(fn () => PriceTableResource::getUrl('manage-items', ['record' => $this->record])),
            Actions\DeleteAction::make(),
        ];
    }
}
